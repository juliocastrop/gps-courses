<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

use TCPDF;

/**
 * GPS Receipt — purchase receipt PDF for any WooCommerce order
 * (course / seminar / individual session ticket).
 *
 * Works for past and recent orders alike — all data is pulled from the
 * order in runtime. No migration. No new tables.
 *
 * Admin: metabox "GPS Receipt" on the order edit screen with Download,
 * Print and Email buttons. Customer: download link on the My Account
 * view-order page and on the order-received (thank-you) page.
 */
class Receipts {

    public static function init() {
        // Admin metabox + actions
        add_action('add_meta_boxes', [__CLASS__, 'register_metabox']);
        add_action('admin_post_gps_receipt_download', [__CLASS__, 'handle_download']);
        add_action('admin_post_gps_receipt_email',    [__CLASS__, 'handle_email']);
        add_action('admin_notices',                   [__CLASS__, 'maybe_admin_notice']);

        // Customer-facing: button in My Account view-order
        add_action('woocommerce_order_details_after_order_table', [__CLASS__, 'render_customer_button']);
        // Public download endpoint for the customer (verified by order key)
        add_action('init', [__CLASS__, 'maybe_handle_customer_download']);
    }

    /* ====================================================================
     * Admin metabox
     * ==================================================================== */

    public static function register_metabox() {
        $screens = ['shop_order'];
        if (class_exists('\\Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController')) {
            $hpos = wc_get_page_screen_id('shop-order');
            if ($hpos) $screens[] = $hpos;
        }
        foreach ($screens as $screen) {
            add_meta_box(
                'gps_receipt',
                __('GPS Receipt', 'gps-courses'),
                [__CLASS__, 'render_metabox'],
                $screen,
                'side',
                'default'
            );
        }
    }

    public static function render_metabox($post_or_order) {
        $order = self::resolve_order($post_or_order);
        if (!$order) {
            echo '<p>' . esc_html__('Order not found.', 'gps-courses') . '</p>';
            return;
        }

        $oid = $order->get_id();
        $download = wp_nonce_url(admin_url('admin-post.php?action=gps_receipt_download&order_id=' . $oid), 'gps_receipt_' . $oid);
        $email    = wp_nonce_url(admin_url('admin-post.php?action=gps_receipt_email&order_id=' . $oid),    'gps_receipt_' . $oid);
        ?>
        <p style="font-size:12px; color:#64748b; margin:0 0 12px;">
            <?php esc_html_e('Generate or resend the purchase receipt for this order.', 'gps-courses'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url($download); ?>" class="button button-primary" style="width:100%; text-align:center; margin-bottom:6px;">
                <?php esc_html_e('Download / Print PDF', 'gps-courses'); ?>
            </a>
        </p>
        <p>
            <a href="<?php echo esc_url($email); ?>"
               class="button"
               style="width:100%; text-align:center;"
               onclick="return confirm('<?php echo esc_js(__('Send a receipt PDF to', 'gps-courses')) . ' ' . esc_js($order->get_billing_email()) . '?'; ?>');">
                <?php esc_html_e('Email Receipt to Customer', 'gps-courses'); ?>
            </a>
        </p>
        <p style="font-size:11px; color:#94a3b8; margin:8px 0 0;">
            <strong><?php esc_html_e('Customer:', 'gps-courses'); ?></strong>
            <?php echo esc_html($order->get_billing_email() ?: '—'); ?>
        </p>
        <?php
    }

    public static function maybe_admin_notice() {
        if (isset($_GET['gps_receipt_sent']) && $_GET['gps_receipt_sent'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 esc_html__('Receipt emailed to customer.', 'gps-courses') .
                 '</p></div>';
        } elseif (isset($_GET['gps_receipt_sent']) && $_GET['gps_receipt_sent'] === '0') {
            echo '<div class="notice notice-error is-dismissible"><p>' .
                 esc_html__('Receipt could not be emailed. Check mail configuration.', 'gps-courses') .
                 '</p></div>';
        }
    }

    /* ====================================================================
     * Admin actions
     * ==================================================================== */

    public static function handle_download() {
        $order = self::guard_admin_action();
        $pdf   = self::generate_pdf($order);
        self::stream_pdf($pdf, self::filename($order), false);
    }

    public static function handle_email() {
        $order = self::guard_admin_action();

        $email = $order->get_billing_email();
        if (!$email) {
            wp_safe_redirect(add_query_arg('gps_receipt_sent', '0', wp_get_referer() ?: admin_url()));
            exit;
        }

        $pdf_data = self::generate_pdf($order);
        $tmp = wp_tempnam('gps-receipt-' . $order->get_id() . '.pdf');
        file_put_contents($tmp, $pdf_data);

        $subject = sprintf(__('Your GPS Dental Training receipt — order #%s', 'gps-courses'), $order->get_order_number());
        $body    = self::email_body($order);
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::from_name_and_email(),
        ];

        $ok = wp_mail($email, $subject, $body, $headers, [$tmp]);

        @unlink($tmp);

        wp_safe_redirect(add_query_arg('gps_receipt_sent', $ok ? '1' : '0', wp_get_referer() ?: admin_url()));
        exit;
    }

    protected static function guard_admin_action() {
        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('Insufficient permissions', 'gps-courses'), 403);
        }
        $order_id = (int) ($_GET['order_id'] ?? 0);
        check_admin_referer('gps_receipt_' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found', 'gps-courses'), 404);
        }
        return $order;
    }

    /* ====================================================================
     * Customer-facing
     * ==================================================================== */

    public static function render_customer_button($order) {
        if (!$order instanceof \WC_Order) return;

        $url = self::customer_download_url($order);
        if ($url === '') return;

        // The thank-you page shows the same link in its own actions panel.
        if (!apply_filters('gps_receipt_show_customer_button', true, $order)) return;

        echo '<p class="gps-receipt-actions"><a href="' . esc_url($url) . '" class="button gps-receipt-btn" target="_blank" rel="noopener">' .
             esc_html__('Download Receipt (PDF)', 'gps-courses') .
             '<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'gps-courses') . '</span>' .
             '</a></p>';
    }

    /**
     * Public receipt link for the customer (verified by order key), or ''
     * when the order has no GPS item or is not paid yet.
     */
    public static function customer_download_url($order) {
        if (!$order instanceof \WC_Order || !self::has_gps_items($order)) return '';
        if (!$order->is_paid() && !$order->has_status('refunded')) return '';

        return add_query_arg([
            'gps_receipt' => $order->get_id(),
            'key'         => $order->get_order_key(),
        ], home_url('/'));
    }

    public static function maybe_handle_customer_download() {
        if (empty($_GET['gps_receipt']) || empty($_GET['key'])) return;

        $order_id = (int) $_GET['gps_receipt'];
        $key      = sanitize_text_field($_GET['key']);

        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order || !hash_equals($order->get_order_key(), $key) || '' === self::customer_download_url($order)) {
            wp_die(__('Invalid receipt link.', 'gps-courses'), 403);
        }

        // Logged-in user must own the order; guest with the correct key passes.
        if (is_user_logged_in() && $order->get_user_id() && $order->get_user_id() !== get_current_user_id()
            && !current_user_can('edit_shop_orders')) {
            wp_die(__('Unauthorized.', 'gps-courses'), 403);
        }

        $pdf = self::generate_pdf($order);
        self::stream_pdf($pdf, self::filename($order), true);
    }

    /* ====================================================================
     * PDF generation
     * ==================================================================== */

    public static function generate_pdf($order) {
        if (!class_exists('TCPDF')) {
            require_once GPSC_PATH . 'vendor/autoload.php';
        }

        $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('GPS Dental Training');
        $pdf->SetAuthor('GPS Dental Training');
        $pdf->SetTitle('Receipt — Order #' . $order->get_order_number());
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        self::render_native($pdf, $order);

        return $pdf->Output('receipt.pdf', 'S');
    }

    /* ====================================================================
     * Native TCPDF rendering. Same printed family as the certificate
     * (navy band, gold accent, deep navy footer) and the GPS emails
     * (white logo on the navy band). Long orders flow onto extra pages.
     * ==================================================================== */

    const PAGE_W    = 215.9; // Letter portrait, mm
    const PAGE_H    = 279.4;
    const CONTENT_X = 22;    // content left edge
    const CONTENT_R = 193.9; // content right edge (PAGE_W - 22)
    const FOOTER_H  = 26;

    protected static function render_native($pdf, $order) {
        $x0 = self::CONTENT_X;
        $x1 = self::CONTENT_R;
        $cw = $x1 - $x0;
        $c  = self::palette();
        $currency = ['currency' => $order->get_currency()];
        $limit    = self::content_bottom();

        self::draw_page_frame($pdf, $order, false);

        /* ---------- Summary strip ---------- */
        $y = 52;
        $pdf->SetFillColor($c['light'][0], $c['light'][1], $c['light'][2]);
        $pdf->RoundedRect($x0, $y, $cw, 17, 2, '1111', 'F');

        $paid = $order->is_paid();
        $cols = [
            ['ORDER NUMBER', '#' . $order->get_order_number()],
            ['ORDER DATE',   wc_format_datetime($order->get_date_created(), get_option('date_format'))],
            ['STATUS',       $paid ? 'Paid' : self::clean_text(wc_get_order_status_name($order->get_status()))],
            ['ORDER TOTAL',  self::clean_text(wc_price($order->get_total(), $currency))],
        ];
        $col_w = $cw / count($cols);
        foreach ($cols as $i => $col) {
            $cx = $x0 + ($i * $col_w);
            $pdf->SetTextColor($c['muted'][0], $c['muted'][1], $c['muted'][2]);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($cx, $y + 3);
            $pdf->Cell($col_w, 4, $col[0], 0, 0, 'C');

            $value_c = ($i === 2 && $paid) ? $c['ok'] : $c['navy'];
            $pdf->SetTextColor($value_c[0], $value_c[1], $value_c[2]);
            $pdf->SetFont('helvetica', 'B', 10.5);
            $pdf->SetXY($cx, $y + 8.5);
            $pdf->Cell($col_w, 6, $col[1], 0, 0, 'C', false, '', 1);
        }

        /* ---------- Bill to (left) + Payment (right) ---------- */
        $y  = 79;
        $rx = 124;
        $rw = $x1 - $rx;

        self::section_label($pdf, 'BILL TO', $x0, $y);
        self::section_label($pdf, 'PAYMENT', $rx, $y);

        // WC returns the formatted address with literal <br/> separators.
        $billing = $order->get_formatted_billing_address();
        $billing = preg_replace('#<br\s*/?>#i', "\n", (string) $billing);
        $billing = html_entity_decode(wp_strip_all_tags($billing), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines   = $billing !== '' ? array_values(array_filter(array_map('trim', explode("\n", $billing)))) : [];
        if ($order->get_billing_email()) $lines[] = $order->get_billing_email();
        if ($order->get_billing_phone()) $lines[] = $order->get_billing_phone();

        $by = $y + 6;
        foreach ($lines as $i => $line) {
            $pdf->SetFont('helvetica', $i === 0 ? 'B' : '', 9.5);
            $pdf->SetTextColor($c['dark'][0], $c['dark'][1], $c['dark'][2]);
            $pdf->SetXY($x0, $by);
            $pdf->Cell($rx - $x0 - 6, 4.8, $line, 0, 0, 'L', false, '', 1);
            $by += 4.8;
        }

        $pay_rows = [];
        $method   = self::clean_text($order->get_payment_method_title());
        if ($method) $pay_rows[] = ['Method', $method];
        $date_paid = $order->get_date_paid();
        if ($date_paid) $pay_rows[] = ['Paid on', wc_format_datetime($date_paid, get_option('date_format'))];
        $txn = self::clean_text($order->get_transaction_id());
        if ($txn) $pay_rows[] = ['Reference', $txn];
        if (!$pay_rows) $pay_rows[] = ['Status', self::clean_text(wc_get_order_status_name($order->get_status()))];

        $py = $y + 6;
        foreach ($pay_rows as $row) {
            $is_ref = ($row[0] === 'Reference');
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetTextColor($c['muted'][0], $c['muted'][1], $c['muted'][2]);
            $pdf->SetXY($rx, $py);
            $pdf->Cell(20, 4.8, $row[0], 0, 0, 'L');
            $pdf->SetFont('helvetica', $is_ref ? '' : 'B', $is_ref ? 8 : 9.5);
            $pdf->SetTextColor($c['dark'][0], $c['dark'][1], $c['dark'][2]);
            $pdf->SetXY($rx + 20, $py);
            $pdf->Cell($rw - 20, 4.8, $row[1], 0, 0, 'L', false, '', 1);
            $py += 4.8;
        }

        /* ---------- Items ---------- */
        $y = max($by, $py) + 9;
        $y = self::draw_items_head($pdf, $y);

        $name_w = 104;
        foreach ($order->get_items() as $item) {
            $name    = self::clean_text($item->get_name());
            $details = self::item_detail_lines($item);

            $pdf->SetFont('helvetica', 'B', 10);
            $name_h = max(1, $pdf->getNumLines($name, $name_w)) * 5;
            $row_h  = 3 + $name_h + (count($details) * 4.2) + 3;

            if ($y + $row_h > $limit) {
                $y = self::new_page($pdf, $order);
                $y = self::draw_items_head($pdf, $y);
            }

            $pdf->SetTextColor($c['dark'][0], $c['dark'][1], $c['dark'][2]);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->MultiCell($name_w, 5, $name, 0, 'L', false, 1, $x0 + 2, $y + 3);

            $sub_y = $y + 3 + $name_h;
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor($c['muted'][0], $c['muted'][1], $c['muted'][2]);
            foreach ($details as $detail) {
                $pdf->SetXY($x0 + 2, $sub_y);
                $pdf->Cell($name_w, 4.2, $detail, 0, 0, 'L', false, '', 1);
                $sub_y += 4.2;
            }

            // Line amount before discounts, so the rows add up to the Subtotal below.
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor($c['dark'][0], $c['dark'][1], $c['dark'][2]);
            $pdf->SetXY(130, $y + 3);
            $pdf->Cell(16, 5, (string) (int) $item->get_quantity(), 0, 0, 'C');
            $pdf->SetXY(150, $y + 3);
            $pdf->Cell($x1 - 152, 5, self::clean_text(wc_price($item->get_subtotal(), $currency)), 0, 0, 'R');

            $y += $row_h;
            $pdf->SetDrawColor($c['line'][0], $c['line'][1], $c['line'][2]);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($x0, $y, $x1, $y);
        }

        /* ---------- Totals ---------- */
        $rows   = [];
        $rows[] = ['Subtotal', wc_price($order->get_subtotal(), $currency), null, null];

        foreach ($order->get_items('coupon') as $coupon_item) {
            $code    = method_exists($coupon_item, 'get_code') ? $coupon_item->get_code() : $coupon_item->get_name();
            $label   = class_exists('\\GPSC\\Coupon_Labels') ? Coupon_Labels::get_label_for_code($code) : '';
            $display = self::clean_text($label ?: strtoupper($code));
            $note    = class_exists('\\GPSC\\Discount_Notes') ? Discount_Notes::get_note($order, $code) : '';
            $rows[]  = ['Discount: ' . $display, '-' . wc_price($coupon_item->get_discount(), $currency), self::clean_text($note) ?: null, $c['ok']];
        }
        foreach ($order->get_fees() as $fee) {
            $rows[] = [self::clean_text($fee->get_name()), wc_price($fee->get_total(), $currency), null, null];
        }
        if ($order->get_shipping_total() > 0) {
            $rows[] = ['Shipping', wc_price($order->get_shipping_total(), $currency), null, null];
        }
        if ($order->get_total_tax() > 0) {
            $rows[] = ['Tax', wc_price($order->get_total_tax(), $currency), null, null];
        }

        $refunds = [];
        foreach ($order->get_refunds() as $refund) {
            $reason    = self::clean_text($refund->get_reason());
            $refunds[] = ['Refund', '-' . wc_price($refund->get_amount(), $currency), $reason ?: null, $c['err']];
        }
        if ($refunds) {
            $refunds[] = ['Net paid', wc_price($order->get_total() - $order->get_total_refunded(), $currency), null, $c['navy']];
        }

        $needed = 6 + 2 + 13; // gap, gap above the bar, TOTAL bar
        foreach (array_merge($rows, $refunds) as $row) {
            $needed += 5.5 + ($row[2] ? 4 : 0);
        }
        if ($y + $needed > $limit) {
            $y = self::new_page($pdf, $order);
        }

        $y += 6;
        $tx = 112;
        $tw = $x1 - $tx;
        $y  = self::draw_total_rows($pdf, $rows, $tx, $tw, $y, $c);

        // TOTAL bar
        $y += 2;
        $pdf->SetFillColor($c['navy'][0], $c['navy'][1], $c['navy'][2]);
        $pdf->RoundedRect($tx, $y, $tw, 10, 1.5, '1111', 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 11.5);
        $pdf->SetXY($tx + 4, $y + 2.5);
        $pdf->Cell($tw - 48, 5, 'TOTAL', 0, 0, 'L');
        $pdf->SetXY($tx + $tw - 44, $y + 2.5);
        $pdf->Cell(40, 5, self::clean_text(wc_price($order->get_total(), $currency)), 0, 0, 'R');
        $y += 13;

        if ($refunds) {
            self::draw_total_rows($pdf, $refunds, $tx, $tw, $y, $c);
        }

        self::draw_footers($pdf);
    }

    /** Colors: certificate settings first, so the receipt follows a brand tweak there. */
    protected static function palette() {
        return [
            'navy'  => self::hex2rgb(Certificate_Settings::get('primary_color') ?: self::BRAND_NAVY),
            'gold'  => self::hex2rgb(Certificate_Settings::get('secondary_color') ?: self::BRAND_GOLD),
            'deep'  => self::hex2rgb(self::BRAND_NAVY_DEEP),
            'dark'  => self::hex2rgb(self::TEXT_DARK),
            'muted' => self::hex2rgb(self::TEXT_MUTED),
            'note'  => self::hex2rgb(self::TEXT_NOTE),
            'light' => self::hex2rgb(self::SURFACE_LIGHT),
            'line'  => self::hex2rgb(self::BORDER_LIGHT),
            'ok'    => self::hex2rgb(self::DISCOUNT_GREEN),
            'err'   => self::hex2rgb('#b4322a'),
        ];
    }

    /** Lowest y the page content may reach before the footer band. */
    protected static function content_bottom() {
        return self::PAGE_H - 15 - self::FOOTER_H - 6;
    }

    /**
     * White logo for the navy band: the same PNG the GPS emails use
     * (attachment 7626, logo-gps-light.png), then the certificate logo.
     * '' means "use the typographic fallback".
     */
    protected static function logo_path() {
        $path = '';
        $id   = (int) apply_filters('gps_receipt_logo_id', 7626);
        if ($id) {
            $path = (string) get_attached_file($id);
        }
        if ($path === '' || !file_exists($path)) {
            $url = (string) Certificate_Settings::get('logo');
            if ($url !== '') {
                $uploads = \wp_upload_dir();
                $path    = str_replace($uploads['baseurl'], $uploads['basedir'], $url);
            }
        }
        $path = (string) apply_filters('gps_receipt_logo_path', $path);
        if ($path === '' || !file_exists($path) || preg_match('/\.svg$/i', $path)) {
            return '';
        }
        $info = @getimagesize($path);
        return ($info && !empty($info[0]) && !empty($info[1])) ? $path : '';
    }

    /** Background, border and the navy header band of one page. */
    protected static function draw_page_frame($pdf, $order, $continued) {
        $W = self::PAGE_W;
        $c = self::palette();

        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, $W, self::PAGE_H, 'F');
        $pdf->SetLineWidth(0.4);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->RoundedRect(10, 10, $W - 20, self::PAGE_H - 20, 5, '1111', 'D');

        $band_y = 15;
        $band_h = 26;
        $pdf->SetFillColor($c['navy'][0], $c['navy'][1], $c['navy'][2]);
        $pdf->Rect(15, $band_y, $W - 30, $band_h, 'F');

        $logo = self::logo_path();
        if ($logo) {
            $info = getimagesize($logo);
            $lh   = 10;
            $lw   = min(($info[0] / $info[1]) * $lh, 80);
            // $resize = false: TCPDF's own PNG parser keeps the transparency (GD would flatten it on white).
            $pdf->Image($logo, self::CONTENT_X, $band_y + ($band_h - $lh) / 2, $lw, $lh, '', '', '', false, 300);
        } else {
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetXY(self::CONTENT_X, $band_y + 6);
            $pdf->Cell(80, 7, 'GPS DENTAL', 0, 0, 'L');
            $pdf->SetTextColor($c['gold'][0], $c['gold'][1], $c['gold'][2]);
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetXY(self::CONTENT_X, $band_y + 14);
            $pdf->Cell(80, 5, 'T R A I N I N G', 0, 0, 'L');
        }

        $rw = 80;
        $rx = self::CONTENT_R - $rw;
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', $continued ? 13 : 17);
        $pdf->SetXY($rx, $band_y + 5.5);
        $pdf->Cell($rw, 8, $continued ? 'RECEIPT (CONTINUED)' : 'RECEIPT', 0, 0, 'R');
        $pdf->SetTextColor($c['gold'][0], $c['gold'][1], $c['gold'][2]);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($rx, $band_y + 14);
        $pdf->Cell($rw, 5, 'Order #' . $order->get_order_number(), 0, 0, 'R');
    }

    /** Starts a continuation page and returns the y where content starts. */
    protected static function new_page($pdf, $order) {
        $pdf->AddPage();
        self::draw_page_frame($pdf, $order, true);
        return 50;
    }

    protected static function section_label($pdf, $text, $x, $y) {
        $c = self::palette();
        $pdf->SetTextColor($c['gold'][0], $c['gold'][1], $c['gold'][2]);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetXY($x, $y);
        $pdf->Cell(60, 5, $text, 0, 0, 'L');
    }

    /** Navy header row of the items table; returns the y below it. */
    protected static function draw_items_head($pdf, $y) {
        $c  = self::palette();
        $x0 = self::CONTENT_X;
        $x1 = self::CONTENT_R;
        $pdf->SetFillColor($c['navy'][0], $c['navy'][1], $c['navy'][2]);
        $pdf->RoundedRect($x0, $y, $x1 - $x0, 8, 1.5, '1111', 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetXY($x0 + 2, $y + 2);
        $pdf->Cell(100, 4, 'ITEM', 0, 0, 'L');
        $pdf->SetXY(130, $y + 2);
        $pdf->Cell(16, 4, 'QTY', 0, 0, 'C');
        $pdf->SetXY(150, $y + 2);
        $pdf->Cell($x1 - 152, 4, 'AMOUNT', 0, 0, 'R');
        return $y + 8;
    }

    /** Label / value rows of the totals block; returns the y below them. */
    protected static function draw_total_rows($pdf, $rows, $tx, $tw, $y, $c) {
        foreach ($rows as $row) {
            list($label, $value, $note, $value_c) = array_pad($row, 4, null);

            $pdf->SetFont('helvetica', $label === 'Net paid' ? 'B' : '', 9.5);
            $pdf->SetTextColor($c['muted'][0], $c['muted'][1], $c['muted'][2]);
            $pdf->SetXY($tx, $y);
            $pdf->Cell($tw - 42, 5, $label, 0, 0, 'L', false, '', 1);

            $value_c = $value_c ?: $c['dark'];
            $pdf->SetTextColor($value_c[0], $value_c[1], $value_c[2]);
            $pdf->SetXY($tx + $tw - 42, $y);
            $pdf->Cell(42, 5, self::clean_text($value), 0, 0, 'R');
            $y += 5.5;

            if ($note) {
                $pdf->SetFont('helvetica', 'I', 7.5);
                $pdf->SetTextColor($c['note'][0], $c['note'][1], $c['note'][2]);
                $pdf->SetXY($tx, $y - 1);
                $pdf->Cell($tw, 4, $note, 0, 0, 'L', false, '', 1);
                $y += 4;
            }
        }
        return $y;
    }

    /** Deep navy footer on every page, plus "Page x of y" on long receipts. */
    protected static function draw_footers($pdf) {
        $W     = self::PAGE_W;
        $c     = self::palette();
        $total = $pdf->getNumPages();

        $company = self::clean_text(get_option('gps_company_name'))    ?: 'GPS Dental Training';
        $email   = self::clean_text(get_option('gps_company_email'))   ?: 'info@gpsdentaltraining.com';
        $phone   = self::clean_text(get_option('gps_company_phone'))   ?: '';
        $address = (string) get_option('gps_company_address');
        $address = self::clean_text(preg_replace('/\s*\R\s*/', ', ', $address));
        $contact = array_filter([$address, $phone, $email]);

        for ($p = 1; $p <= $total; $p++) {
            $pdf->setPage($p);
            $fy = self::PAGE_H - 15 - self::FOOTER_H;

            $pdf->SetFillColor($c['deep'][0], $c['deep'][1], $c['deep'][2]);
            $pdf->Rect(15, $fy, $W - 30, self::FOOTER_H, 'F');

            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 9.5);
            $pdf->SetXY(15, $fy + 4.5);
            $pdf->Cell($W - 30, 5, strtoupper($company), 0, 0, 'C');

            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetTextColor(180, 195, 215);
            $pdf->SetXY(20, $fy + 10.5);
            $pdf->Cell($W - 40, 4, implode('  ·  ', $contact), 0, 0, 'C', false, '', 1);

            $pdf->SetTextColor($c['gold'][0], $c['gold'][1], $c['gold'][2]);
            $pdf->SetFont('helvetica', 'I', 8.5);
            $pdf->SetXY(15, $fy + 17);
            $pdf->Cell($W - 30, 4, 'Thank you for choosing ' . $company, 0, 0, 'C');

            if ($total > 1) {
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetTextColor(180, 195, 215);
                $pdf->SetXY(self::CONTENT_R - 40, $fy + 17);
                $pdf->Cell(40, 4, 'Page ' . $p . ' of ' . $total, 0, 0, 'R');
            }
        }
        $pdf->lastPage();
    }

    /** Small grey lines under an item: date, place, attendee. */
    protected static function item_detail_lines($item) {
        $lines = [];
        $ctx   = self::describe_item($item);
        if ($ctx['when']) {
            $lines[] = ($ctx['type'] === 'seminar' ? 'Program: ' : 'Date: ') . $ctx['when'];
        }
        if ($ctx['where']) {
            $lines[] = 'Location: ' . $ctx['where'];
        }

        // Older orders may carry these visible meta keys.
        $attendee   = self::clean_text($item->get_meta('Attendee') ?: $item->get_meta('attendee_name'));
        $event_date = self::clean_text($item->get_meta('Event Date') ?: $item->get_meta('event_date'));
        if ($attendee) {
            $lines[] = 'Attendee: ' . $attendee;
        }
        if ($event_date && !$ctx['when']) {
            $lines[] = 'Date: ' . $event_date;
        }
        return $lines;
    }

    /**
     * What a GPS order line is: a course ticket, a monthly seminar
     * enrollment or a single seminar session, with its date and place.
     * Shared by the receipt and the thank-you page.
     *
     * @param \WC_Order_Item_Product $item
     * @return array{type:string,id:int,title:string,url:string,when:string,where:string}
     */
    public static function describe_item($item) {
        static $memo = [];
        $out = ['type' => 'other', 'id' => 0, 'title' => '', 'url' => '', 'when' => '', 'where' => ''];
        if (!is_object($item) || !method_exists($item, 'get_product_id')) {
            return $out;
        }
        $key = method_exists($item, 'get_id') && $item->get_id() ? (int) $item->get_id() : 0;
        if ($key && isset($memo[$key])) {
            return $memo[$key];
        }
        $info = self::lookup_item($item, $out);
        if ($key) {
            $memo[$key] = $info;
        }
        return $info;
    }

    protected static function lookup_item($item, $out) {
        $product_id = (int) $item->get_product_id();

        // Course ticket: the event id is stored on the line at checkout.
        $event_id = (int) $item->get_meta('_gps_event_id');
        if (!$event_id && $product_id) {
            $event_id = (int) get_post_meta($product_id, '_gps_event_id', true);
        }
        if ($event_id && get_post_type($event_id) === 'gps_event') {
            $start = (string) get_post_meta($event_id, '_gps_start_date', true);
            $end   = (string) get_post_meta($event_id, '_gps_end_date', true);
            $city  = trim((string) get_post_meta($event_id, '_gps_city', true));
            $state = trim((string) get_post_meta($event_id, '_gps_state', true));
            $where = array_filter([
                trim((string) get_post_meta($event_id, '_gps_venue', true)),
                trim($city . ($state !== '' ? ', ' . $state : ''), ', '),
            ]);
            return [
                'type'  => 'course',
                'id'    => $event_id,
                'title' => self::clean_text(get_the_title($event_id)),
                'url'   => (string) get_permalink($event_id),
                'when'  => self::date_range($start, $end),
                'where' => implode(', ', array_unique($where)),
            ];
        }

        // Monthly seminar enrollment
        $seminar_id = $product_id ? (int) get_post_meta($product_id, '_gps_seminar_id', true) : 0;
        if ($seminar_id) {
            $year = (int) get_post_meta($seminar_id, '_gps_seminar_year', true);
            return [
                'type'  => 'seminar',
                'id'    => $seminar_id,
                'title' => self::clean_text(get_the_title($seminar_id)),
                'url'   => (string) get_permalink($seminar_id),
                'when'  => $year ? sprintf('%d · 10 monthly sessions', $year) : '10 monthly sessions',
                'where' => '',
            ];
        }

        // Single seminar session
        if ($product_id && class_exists('\\GPSC\\Session_Tickets')) {
            $session = Session_Tickets::get_session_by_product($product_id);
            if ($session) {
                $when = '';
                if (!empty($session->session_date)) {
                    $when = date_i18n('M j, Y', strtotime($session->session_date));
                    if (!empty($session->session_time_start)) {
                        $when .= ' · ' . date_i18n('g:i A', strtotime($session->session_date . ' ' . $session->session_time_start));
                    }
                }
                return [
                    'type'  => 'session',
                    'id'    => (int) $session->seminar_id,
                    'title' => self::clean_text(get_the_title((int) $session->seminar_id)) .
                               (!empty($session->session_number) ? ' · Session ' . (int) $session->session_number : ''),
                    'url'   => (string) get_permalink((int) $session->seminar_id),
                    'when'  => $when,
                    'where' => '',
                ];
            }
        }

        return $out;
    }

    /** "Nov 14–15, 2026": same format as the account dashboard. */
    protected static function date_range($start, $end) {
        if ($start === '') return '';
        if (class_exists('\\GPSC\\Account_Dashboard') && method_exists('\\GPSC\\Account_Dashboard', 'date_range')) {
            return (string) Account_Dashboard::date_range($start, $end);
        }
        $ts = strtotime($start);
        return $ts ? date_i18n('M j, Y', $ts) : '';
    }

    /**
     * Strip tags + decode HTML entities so text rendered via TCPDF
     * Cell()/MultiCell() does not show literal &#8211; / <br/> etc.
     * Cell() does not parse HTML — only writeHTML() does — so every
     * string we feed to Cell must be pre-decoded.
     */
    protected static function clean_text($s) {
        if ($s === null || $s === '') return '';
        $s = preg_replace('#<br\s*/?>#i', ' ', (string) $s);
        $s = wp_strip_all_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($s);
    }

    /**
     * Convert "#RRGGBB" to [r, g, b].
     */
    protected static function hex2rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * GPS brand colors. Aligned with the certificate template
     * (class-certificates.php) so the receipt visually belongs to the
     * same printed family — NOT the brighter email blue.
     *
     * Reference values (also used in cert: #193463, #BC9D67, #0C2044).
     */
    const BRAND_NAVY     = '#193463';   // primary header/footer
    const BRAND_NAVY_DEEP = '#0C2044';  // deep navy for emphasis
    const BRAND_GOLD     = '#BC9D67';   // accent
    const TEXT_DARK      = '#1f2937';
    const TEXT_MUTED     = '#6b7280';
    const TEXT_NOTE      = '#475569';
    const SURFACE_LIGHT  = '#f8f9fb';   // same as certificate's light surface
    const BORDER_LIGHT   = '#e5e7eb';
    const DISCOUNT_GREEN = '#059669';


    /* ====================================================================
     * Helpers
     * ==================================================================== */

    protected static function stream_pdf($data, $filename, $inline) {
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }

    protected static function filename($order) {
        return 'gps-receipt-' . $order->get_order_number() . '.pdf';
    }

    protected static function email_body($order) {
        $name = $order->get_billing_first_name() ?: __('there', 'gps-courses');
        $company = get_option('gps_company_name') ?: 'GPS Dental Training';

        ob_start();
        ?>
        <p><?php printf(esc_html__('Hi %s,', 'gps-courses'), esc_html($name)); ?></p>
        <p><?php printf(
            esc_html__('Attached is the receipt for your order #%s. Please keep this for your records.', 'gps-courses'),
            esc_html($order->get_order_number())
        ); ?></p>
        <p><?php esc_html_e('If you have any questions, just reply to this email.', 'gps-courses'); ?></p>
        <p>— <?php echo esc_html($company); ?></p>
        <?php
        return ob_get_clean();
    }

    protected static function from_name_and_email() {
        $name  = get_option('gps_email_from_name')    ?: 'GPS Dental Training';
        $email = get_option('gps_email_from_address') ?: get_option('admin_email');
        return sprintf('%s <%s>', $name, $email);
    }

    protected static function resolve_order($post_or_order) {
        if ($post_or_order instanceof \WC_Order) return $post_or_order;
        if (is_object($post_or_order) && isset($post_or_order->ID)) {
            return wc_get_order($post_or_order->ID);
        }
        if (is_numeric($post_or_order)) {
            return wc_get_order((int) $post_or_order);
        }
        return null;
    }

    /**
     * True if the order contains at least one GPS item (course ticket,
     * seminar enrollment or individual session ticket). Used to decide
     * whether to show the customer-facing download button.
     */
    public static function has_gps_items($order) {
        foreach ($order->get_items() as $item) {
            if (self::describe_item($item)['type'] !== 'other') {
                return true;
            }
        }
        return false;
    }
}
