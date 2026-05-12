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
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $pdf->writeHTML(self::pdf_html($order), true, false, true, false, '');

        return $pdf->Output('receipt.pdf', 'S');
    }

    protected static function pdf_html($order) {
        // Pull brand colors from the Email Settings module so the receipt
        // visually matches the ticket-confirmation email.
        $logo_url    = Email_Settings::get('logo');
        $header_bg   = Email_Settings::get('header_bg_color');
        $header_fg   = Email_Settings::get('header_text_color');
        $body_bg     = Email_Settings::get('body_bg_color');
        $body_fg     = Email_Settings::get('body_text_color');
        $accent      = Email_Settings::get('event_heading_color');
        $section_bg  = Email_Settings::get('ticket_bg_color');     // light surface
        $footer_bg   = Email_Settings::get('footer_bg_color');
        $footer_fg   = Email_Settings::get('footer_text_color');

        $company = get_option('gps_company_name')    ?: 'GPS Dental Training';
        $email   = get_option('gps_company_email')   ?: 'info@gpsdentaltraining.com';
        $phone   = get_option('gps_company_phone')   ?: '';
        $address = get_option('gps_company_address') ?: '';

        $date_str = wc_format_datetime($order->get_date_created(), get_option('date_format'));
        $currency = ['currency' => $order->get_currency()];

        // ------- Logo strip (white background, like the email) -------
        $html = '';
        if ($logo_url) {
            $html .= '<table cellpadding="0" cellspacing="0" style="width:100%;"><tr>';
            $html .= '<td style="text-align:center; padding:4px 0 14px;">';
            $html .= '<img src="' . esc_url($logo_url) . '" style="max-height:80px;">';
            $html .= '</td></tr></table>';
        }

        // ------- Brand header bar -------
        $html .= '<table cellpadding="14" cellspacing="0" style="width:100%; background-color:' . esc_attr($header_bg) . '; color:' . esc_attr($header_fg) . ';"><tr>';
        $html .= '<td style="width:60%; vertical-align:middle;">';
        $html .= '<h1 style="margin:0; font-size:22pt; color:' . esc_attr($header_fg) . ';">RECEIPT</h1>';
        $html .= '<div style="font-size:10pt; color:' . esc_attr($header_fg) . '; opacity:0.9;">' . esc_html($company) . '</div>';
        $html .= '</td>';
        $html .= '<td style="width:40%; vertical-align:middle; text-align:right; font-size:10pt; color:' . esc_attr($header_fg) . ';">';
        $html .= '<div><strong>Order #</strong> ' . esc_html($order->get_order_number()) . '</div>';
        $html .= '<div><strong>Date</strong> ' . esc_html($date_str) . '</div>';
        $html .= '<div><strong>Status</strong> ' . esc_html(wc_get_order_status_name($order->get_status())) . '</div>';
        $html .= '</td></tr></table>';

        // ------- Bill to -------
        // get_formatted_billing_address() already contains name + company,
        // so we don't print them separately (that's what caused the
        // duplicate name in the previous version).
        $html .= '<br><table cellpadding="10" cellspacing="0" style="width:100%; background-color:' . esc_attr($section_bg) . ';"><tr><td style="color:' . esc_attr($body_fg) . ';">';
        $html .= '<strong style="color:' . esc_attr($accent) . ';">BILL TO</strong><br>';
        $billing_addr = $order->get_formatted_billing_address();
        if ($billing_addr) {
            $html .= str_replace("\n", '<br>', wp_kses_post($billing_addr)) . '<br>';
        }
        $html .= esc_html($order->get_billing_email());
        if ($order->get_billing_phone()) {
            $html .= ' &middot; ' . esc_html($order->get_billing_phone());
        }
        $html .= '</td></tr></table>';

        // ------- Items table -------
        $html .= '<br><table cellpadding="8" cellspacing="0" style="width:100%; font-size:10pt; color:' . esc_attr($body_fg) . ';">';
        $html .= '<thead><tr style="background-color:' . esc_attr($header_bg) . '; color:' . esc_attr($header_fg) . ';">';
        $html .= '<th style="text-align:left;">Item</th>';
        $html .= '<th style="text-align:center; width:60px;">Qty</th>';
        $html .= '<th style="text-align:right; width:110px;">Total</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $attendee   = $item->get_meta('Attendee') ?: $item->get_meta('attendee_name');
            $event_date = $item->get_meta('Event Date') ?: $item->get_meta('event_date');

            $html .= '<tr style="border-bottom:1px solid #e2e8f0;">';
            $html .= '<td><strong>' . esc_html($product_name) . '</strong>';
            if ($attendee)   $html .= '<br><span style="font-size:9pt; color:#64748b;">Attendee: ' . esc_html($attendee) . '</span>';
            if ($event_date) $html .= '<br><span style="font-size:9pt; color:#64748b;">Date: ' . esc_html($event_date) . '</span>';
            $html .= '</td>';
            $html .= '<td style="text-align:center;">' . (int) $item->get_quantity() . '</td>';
            $html .= '<td style="text-align:right;">' . wp_kses_post(wc_price($item->get_total(), $currency)) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // ------- Totals -------
        $html .= '<br><table cellpadding="4" cellspacing="0" style="width:100%; font-size:10pt;"><tr>';
        $html .= '<td style="width:55%;">&nbsp;</td>';
        $html .= '<td style="width:45%;"><table cellpadding="6" cellspacing="0" style="width:100%; color:' . esc_attr($body_fg) . ';">';

        $html .= '<tr><td style="color:#64748b;">Subtotal</td><td style="text-align:right;">' . wp_kses_post(wc_price($order->get_subtotal(), $currency)) . '</td></tr>';

        foreach ($order->get_items('coupon') as $coupon_item) {
            $code = method_exists($coupon_item, 'get_code') ? $coupon_item->get_code() : $coupon_item->get_name();
            $label = class_exists('\\GPSC\\Coupon_Labels') ? Coupon_Labels::get_label_for_code($code) : '';
            $display = $label ?: strtoupper($code);
            $note    = class_exists('\\GPSC\\Discount_Notes') ? Discount_Notes::get_note($order, $code) : '';

            $html .= '<tr><td style="color:#64748b;">Discount: ' . esc_html($display);
            if ($note) {
                $html .= '<br><span style="font-size:8pt; color:#94a3b8;">' . esc_html($note) . '</span>';
            }
            $html .= '</td><td style="text-align:right; color:#10b981;">-' . wp_kses_post(wc_price($coupon_item->get_discount(), $currency)) . '</td></tr>';
        }

        if ($order->get_shipping_total() > 0) {
            $html .= '<tr><td style="color:#64748b;">Shipping</td><td style="text-align:right;">' . wp_kses_post(wc_price($order->get_shipping_total(), $currency)) . '</td></tr>';
        }
        if ($order->get_total_tax() > 0) {
            $html .= '<tr><td style="color:#64748b;">Tax</td><td style="text-align:right;">' . wp_kses_post(wc_price($order->get_total_tax(), $currency)) . '</td></tr>';
        }

        $html .= '<tr style="background-color:' . esc_attr($header_bg) . '; color:' . esc_attr($header_fg) . ';">';
        $html .= '<td><strong>TOTAL</strong></td>';
        $html .= '<td style="text-align:right;"><strong>' . wp_kses_post(wc_price($order->get_total(), $currency)) . '</strong></td></tr>';

        $html .= '</table></td></tr></table>';

        // ------- Payment -------
        $payment_method = $order->get_payment_method_title();
        if ($payment_method) {
            $html .= '<br><p style="font-size:9pt; color:' . esc_attr($body_fg) . ';"><strong>Payment Method:</strong> ' . esc_html($payment_method) . '</p>';
        }

        // ------- Footer -------
        $html .= '<br><br><table cellpadding="12" cellspacing="0" style="width:100%; background-color:' . esc_attr($footer_bg) . ';"><tr>';
        $html .= '<td style="text-align:center; font-size:9pt; color:' . esc_attr($footer_fg) . ';">';
        $html .= '<strong>' . esc_html($company) . '</strong><br>';
        if ($address) $html .= esc_html(str_replace("\n", ' · ', $address)) . '<br>';
        $contact_bits = [];
        if ($phone) $contact_bits[] = esc_html($phone);
        $contact_bits[] = esc_html($email);
        $html .= implode(' &middot; ', $contact_bits);
        $html .= '<br><br><span style="font-size:8pt;">' . esc_html__('Thank you for choosing GPS Dental Training.', 'gps-courses') . '</span>';
        $html .= '</td></tr></table>';

        return $html;
    }

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
