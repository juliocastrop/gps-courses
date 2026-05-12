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
 * Print and Email buttons. Customer: download link inside the My
 * Account view-order page.
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
        if (!self::has_gps_items($order)) return;

        $url = add_query_arg([
            'gps_receipt' => $order->get_id(),
            'key'         => $order->get_order_key(),
        ], home_url('/'));

        echo '<p style="margin-top:20px;"><a href="' . esc_url($url) . '" class="button" target="_blank">' .
             esc_html__('Download Receipt (PDF)', 'gps-courses') .
             '</a></p>';
    }

    public static function maybe_handle_customer_download() {
        if (empty($_GET['gps_receipt']) || empty($_GET['key'])) return;

        $order_id = (int) $_GET['gps_receipt'];
        $key      = sanitize_text_field($_GET['key']);

        $order = wc_get_order($order_id);
        if (!$order || !hash_equals($order->get_order_key(), $key)) {
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
     * Native TCPDF rendering — mirrors the certificate's approach
     * (class-certificates.php::render_certificate_content) so the
     * receipt sits in the same visual family.
     * ==================================================================== */

    protected static function render_native($pdf, $order) {
        $W = 215.9; // Letter portrait width in mm
        $H = 279.4; // height

        // Colors lifted from Certificate_Settings so the receipt
        // automatically follows any future brand tweak there.
        $navy_hex   = Certificate_Settings::get('primary_color')   ?: '#193463';
        $gold_hex   = Certificate_Settings::get('secondary_color') ?: '#BC9D67';
        $deep_hex   = '#0C2044'; // matches certificate footer
        $navy   = self::hex2rgb($navy_hex);
        $gold   = self::hex2rgb($gold_hex);
        $deep   = self::hex2rgb($deep_hex);
        $dark   = [31, 41, 55];
        $muted  = [107, 114, 128];
        $note_c = [71, 85, 105];
        $light  = [248, 249, 251];

        $currency = ['currency' => $order->get_currency()];

        /* ---------- Background + outer rounded border ---------- */
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, $W, $H, 'F');
        $pdf->SetLineWidth(0.4);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->RoundedRect(10, 10, $W - 20, $H - 20, 5, '1111', 'D');

        /* ---------- Header navy band with logo or typography ---------- */
        $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
        $pdf->Rect(15, 15, $W - 30, 28, 'F');

        $logo_url  = Certificate_Settings::get('logo'); // light version intended for navy bg
        $logo_done = false;
        if ($logo_url) {
            $uploads   = \wp_upload_dir();
            $logo_path = str_replace($uploads['baseurl'], $uploads['basedir'], $logo_url);
            if (file_exists($logo_path)) {
                $info = @getimagesize($logo_path);
                if ($info && !empty($info[0]) && !empty($info[1])) {
                    $lh = 16;
                    $lw = ($info[0] / $info[1]) * $lh;
                    if ($lw > $W - 60) $lw = $W - 60;
                    $lx = ($W - $lw) / 2;
                    $pdf->Image($logo_path, $lx, 21, $lw, $lh, '', '', '', true, 300);
                    $logo_done = true;
                }
            }
        }
        if (!$logo_done) {
            // Typographic fallback — exact treatment from the certificate
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 20);
            $pdf->SetXY(20, 21);
            $pdf->Cell($W - 40, 8, 'GPS DENTAL', 0, 1, 'C');

            $pdf->SetTextColor($gold[0], $gold[1], $gold[2]);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetXY(20, 32);
            $pdf->Cell($W - 40, 6, 'T R A I N I N G', 0, 1, 'C');
        }

        /* ---------- RECEIPT title ---------- */
        $y = 56;
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetXY(20, $y);
        $pdf->Cell($W - 40, 11, 'RECEIPT', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->SetXY(20, $y + 11);
        $pdf->Cell($W - 40, 5, 'OF PURCHASE', 0, 1, 'C');

        // Decorative gold rule
        $pdf->SetDrawColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetLineWidth(0.8);
        $pdf->Line($W / 2 - 18, $y + 19, $W / 2 + 18, $y + 19);

        /* ---------- Meta strip: 3 columns ---------- */
        $y = 86;
        $box_x = 25;
        $box_w = $W - 50;
        $box_h = 16;
        $pdf->SetFillColor($light[0], $light[1], $light[2]);
        $pdf->RoundedRect($box_x, $y, $box_w, $box_h, 2, '1111', 'F');

        $cols = [
            ['ORDER #', '#' . $order->get_order_number()],
            ['DATE',    wc_format_datetime($order->get_date_created(), get_option('date_format'))],
            ['STATUS',  wc_get_order_status_name($order->get_status())],
        ];
        $col_w = $box_w / 3;
        foreach ($cols as $i => $c) {
            $cx = $box_x + ($i * $col_w);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($cx, $y + 2.5);
            $pdf->Cell($col_w, 4, $c[0], 0, 0, 'C');

            $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetXY($cx, $y + 7.5);
            $pdf->Cell($col_w, 6, $c[1], 0, 0, 'C');
        }

        /* ---------- Bill To ---------- */
        $y = 112;
        $pdf->SetTextColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(25, $y);
        $pdf->Cell(60, 5, 'BILL TO', 0, 1, 'L');

        $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
        $pdf->SetFont('helvetica', '', 10);
        $billing = $order->get_formatted_billing_address('');
        $lines = $billing ? explode("\n", $billing) : [];
        $contact = $order->get_billing_email();
        if ($order->get_billing_phone()) {
            $contact .= ' · ' . $order->get_billing_phone();
        }
        if ($contact) $lines[] = $contact;

        $by = $y + 5;
        foreach ($lines as $line) {
            $pdf->SetXY(25, $by);
            $pdf->Cell($W - 50, 5, $line, 0, 1, 'L');
            $by += 5;
        }

        /* ---------- Items table ---------- */
        $y = max($by + 6, 142);

        // Header row
        $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
        $pdf->Rect(20, $y, $W - 40, 8, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(22, $y + 2);
        $pdf->Cell(100, 4, 'ITEM', 0, 0, 'L');
        $pdf->SetXY(125, $y + 2);
        $pdf->Cell(15, 4, 'QTY', 0, 0, 'C');
        $pdf->SetXY(160, $y + 2);
        $pdf->Cell(35, 4, 'TOTAL', 0, 0, 'R');

        $y += 8;

        // Item rows
        $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $attendee   = $item->get_meta('Attendee') ?: $item->get_meta('attendee_name');
            $event_date = $item->get_meta('Event Date') ?: $item->get_meta('event_date');

            $row_h = 8;
            if ($attendee)   $row_h += 4;
            if ($event_date) $row_h += 4;
            $row_h += 2;

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
            $pdf->SetXY(22, $y + 2);
            $pdf->MultiCell(100, 5, $product_name, 0, 'L');

            $sub_y = $pdf->GetY();
            if ($attendee) {
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
                $pdf->SetXY(22, $sub_y);
                $pdf->Cell(100, 4, 'Attendee: ' . $attendee, 0, 0, 'L');
                $sub_y += 4;
            }
            if ($event_date) {
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
                $pdf->SetXY(22, $sub_y);
                $pdf->Cell(100, 4, 'Date: ' . $event_date, 0, 0, 'L');
                $sub_y += 4;
            }

            // Right-aligned values vertically centered on first line
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
            $pdf->SetXY(125, $y + 2);
            $pdf->Cell(15, 5, (string) (int) $item->get_quantity(), 0, 0, 'C');
            $pdf->SetXY(160, $y + 2);
            $pdf->Cell(35, 5, html_entity_decode(wp_strip_all_tags(wc_price($item->get_total(), $currency))), 0, 0, 'R');

            $row_bottom = max($sub_y, $y + 8) + 2;
            $pdf->SetDrawColor(229, 231, 235);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(20, $row_bottom, $W - 20, $row_bottom);
            $y = $row_bottom + 2;
        }

        /* ---------- Totals (right-aligned) ---------- */
        $y += 4;
        $tx = 110;     // totals left edge
        $tw = $W - 20 - $tx;

        $rows = [];
        $rows[] = ['Subtotal', wc_price($order->get_subtotal(), $currency), null];

        foreach ($order->get_items('coupon') as $coupon_item) {
            $code = method_exists($coupon_item, 'get_code') ? $coupon_item->get_code() : $coupon_item->get_name();
            $label = class_exists('\\GPSC\\Coupon_Labels') ? Coupon_Labels::get_label_for_code($code) : '';
            $display = $label ?: strtoupper($code);
            $note    = class_exists('\\GPSC\\Discount_Notes') ? Discount_Notes::get_note($order, $code) : '';
            $rows[] = [
                'Discount: ' . $display,
                '-' . wc_price($coupon_item->get_discount(), $currency),
                $note ?: null,
                '#059669',
            ];
        }
        if ($order->get_shipping_total() > 0) {
            $rows[] = ['Shipping', wc_price($order->get_shipping_total(), $currency), null];
        }
        if ($order->get_total_tax() > 0) {
            $rows[] = ['Tax', wc_price($order->get_total_tax(), $currency), null];
        }

        foreach ($rows as $row) {
            list($label, $value, $note) = array_pad($row, 3, null);
            $value_color = $row[3] ?? null;

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->SetXY($tx, $y);
            $pdf->Cell($tw - 35, 5, $label, 0, 0, 'L');

            if ($value_color) {
                $rgb = self::hex2rgb($value_color);
                $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
            } else {
                $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
            }
            $pdf->SetXY($tx + $tw - 35, $y);
            $pdf->Cell(35, 5, html_entity_decode(wp_strip_all_tags($value)), 0, 0, 'R');
            $y += 5;

            if ($note) {
                $pdf->SetFont('helvetica', 'I', 7.5);
                $pdf->SetTextColor($note_c[0], $note_c[1], $note_c[2]);
                $pdf->SetXY($tx, $y);
                $pdf->Cell($tw, 4, $note, 0, 0, 'L');
                $y += 4;
            }
        }

        // TOTAL row in navy
        $y += 2;
        $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
        $pdf->Rect($tx, $y, $tw, 10, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY($tx + 3, $y + 2.5);
        $pdf->Cell($tw - 38, 5, 'TOTAL', 0, 0, 'L');
        $pdf->SetXY($tx + $tw - 38, $y + 2.5);
        $pdf->Cell(35, 5, html_entity_decode(wp_strip_all_tags(wc_price($order->get_total(), $currency))), 0, 0, 'R');
        $y += 14;

        /* ---------- Payment line ---------- */
        $payment = $order->get_payment_method_title();
        if ($payment) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->SetXY(25, $y);
            $pdf->Cell($W - 50, 5, 'Payment Method: ' . $payment, 0, 0, 'L');
            $y += 8;
        }

        /* ---------- Footer (deep navy band at the bottom of the page) ---------- */
        $foot_h  = 32;
        $foot_y  = $H - 10 - $foot_h - 5; // sit inside the rounded border
        $pdf->SetFillColor($deep[0], $deep[1], $deep[2]);
        $pdf->Rect(15, $foot_y, $W - 30, $foot_h, 'F');

        $company = get_option('gps_company_name')    ?: 'GPS Dental Training';
        $email   = get_option('gps_company_email')   ?: 'info@gpsdentaltraining.com';
        $phone   = get_option('gps_company_phone')   ?: '';
        $address = get_option('gps_company_address') ?: '';

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $foot_y + 4);
        $pdf->Cell($W - 30, 5, strtoupper($company), 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(180, 195, 215);
        if ($address) {
            $pdf->SetXY(15, $foot_y + 10);
            $pdf->Cell($W - 30, 4, str_replace("\n", ' · ', $address), 0, 1, 'C');
        }
        $contact_bits = [];
        if ($phone) $contact_bits[] = $phone;
        $contact_bits[] = $email;
        $pdf->SetXY(15, $foot_y + 15);
        $pdf->Cell($W - 30, 4, implode(' · ', $contact_bits), 0, 1, 'C');

        // Gold "thanks" line
        $pdf->SetTextColor($gold[0], $gold[1], $gold[2]);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetXY(15, $foot_y + 23);
        $pdf->Cell($W - 30, 4, 'Thank you for choosing GPS Dental Training', 0, 1, 'C');
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
    protected static function has_gps_items($order) {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (!$product_id) continue;

            // Match the same detection used elsewhere in the plugin
            if (class_exists('\\GPSC\\Session_Tickets') && Session_Tickets::is_session_product($product_id)) {
                return true;
            }
            // Linked ticket/seminar product detection: any product tied to a gps CPT
            if (get_post_meta($product_id, '_gps_event_id', true) ||
                get_post_meta($product_id, '_gps_seminar_product_id', true)) {
                return true;
            }
        }
        // Fall back to true so admins can always download; customer button is the only stricter check.
        return false;
    }
}
