<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Cart in the GPS look: the header mini-cart (Elementor Pro "Menu Cart") and
 * the /cart/ page (Elementor Pro "Cart"). Both widgets keep their own markup;
 * this class adds the pieces CSS can't (empty states with links, course date
 * and image on each line, the page heading) and loads the styles:
 *   - gps-mini-cart.css + gps-cart.js on every front-end page (small: header)
 *   - gps-cart.css on the cart page only
 */
class Shop_Cart {

    /** Per-request cache: product id => course/seminar context. */
    protected static $context = [];

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 110);

        // Mini-cart: empty state with links (Elementor's template has no hook there)
        add_action('woocommerce_after_template_part', [__CLASS__, 'mini_cart_empty'], 10, 1);

        // Cart page
        add_action('woocommerce_before_cart', [__CLASS__, 'cart_heading'], 5);
        add_action('woocommerce_cart_is_empty', [__CLASS__, 'cart_empty'], 5);
        add_action('woocommerce_after_cart_totals', [__CLASS__, 'secure_note']);
        add_action('template_redirect', [__CLASS__, 'replace_empty_notice']);

        // Cart lines (cart page + mini-cart; checkout is left as it is)
        add_filter('woocommerce_get_item_data', [__CLASS__, 'item_data'], 10, 2);
        add_filter('woocommerce_cart_item_thumbnail', [__CLASS__, 'item_thumbnail'], 10, 2);
    }

    public static function enqueue_assets() {
        if (is_admin() || !function_exists('WC')) {
            return;
        }
        $deps = wp_style_is('gps-forms', 'registered') ? ['gps-forms'] : [];

        $mini = GPSC_PATH . 'assets/css/gps-mini-cart.css';
        wp_enqueue_style('gps-mini-cart', GPSC_URL . 'assets/css/gps-mini-cart.css', $deps, file_exists($mini) ? (string) filemtime($mini) : GPSC_VERSION);

        $js = GPSC_PATH . 'assets/js/gps-cart.js';
        wp_enqueue_script('gps-cart', GPSC_URL . 'assets/js/gps-cart.js', ['jquery'], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, true);
        wp_localize_script('gps-cart', 'gpsCart', [
            'title'    => __('Your cart', 'gps-courses'),
            'close'    => __('Close cart', 'gps-courses'),
            'cart'     => __('Cart', 'gps-courses'),
            /* translators: %d: number of items in the cart */
            'items'    => __('%d items', 'gps-courses'),
            'item'     => __('1 item', 'gps-courses'),
            'less'     => __('Decrease quantity', 'gps-courses'),
            'more'     => __('Increase quantity', 'gps-courses'),
            'coupon'   => __('Coupon code', 'gps-courses'),
        ]);

        if (function_exists('is_cart') && is_cart()) {
            $css = GPSC_PATH . 'assets/css/gps-cart.css';
            wp_enqueue_style('gps-cart', GPSC_URL . 'assets/css/gps-cart.css', ['gps-mini-cart'], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        }
    }

    /* ============================================================
     * Empty states
     * ============================================================ */

    /** Links shown when there is nothing in the cart. */
    protected static function empty_links($context) {
        $courses  = get_post_type_archive_link('gps_event') ?: home_url('/courses/');
        $seminars = home_url('/monthly-seminars/');
        ?>
        <div class="gps-cart-empty gps-cart-empty--<?php echo esc_attr($context); ?>">
            <span class="gps-cart-empty__ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.2 10.4a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.5L20.5 8H6.2"/><circle cx="10" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/></svg>
            </span>
            <?php if ('page' === $context) : ?>
                <h1 class="gps-cart-empty__title"><?php esc_html_e('Your cart is empty', 'gps-courses'); ?></h1>
            <?php else : ?>
                <p class="gps-cart-empty__title"><?php esc_html_e('Your cart is empty', 'gps-courses'); ?></p>
            <?php endif; ?>
            <p class="gps-cart-empty__text"><?php esc_html_e('Find your next hands-on course and earn CE credits with GPS Dental Training.', 'gps-courses'); ?></p>
            <div class="gps-cart-empty__acts">
                <a class="gps-cbtn gps-cbtn--navy" href="<?php echo esc_url($courses); ?>"><?php esc_html_e('Browse courses', 'gps-courses'); ?></a>
                <a class="gps-cbtn gps-cbtn--line" href="<?php echo esc_url($seminars); ?>"><?php esc_html_e('Monthly seminars', 'gps-courses'); ?></a>
            </div>
        </div>
        <?php
    }

    /** After the mini-cart template, when the cart is empty. */
    public static function mini_cart_empty($template_name) {
        if ('cart/mini-cart.php' !== $template_name || !function_exists('WC') || !WC()->cart || !WC()->cart->is_empty()) {
            return;
        }
        self::empty_links('mini');
    }

    /** Our empty panel replaces WooCommerce's "Your cart is currently empty." notice. */
    public static function replace_empty_notice() {
        if (function_exists('is_cart') && is_cart()) {
            remove_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);
        }
    }

    public static function cart_empty() {
        self::empty_links('page');
    }

    /* ============================================================
     * Cart page
     * ============================================================ */

    public static function cart_heading() {
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }
        $count   = (int) WC()->cart->get_cart_contents_count();
        $courses = get_post_type_archive_link('gps_event') ?: home_url('/courses/');
        ?>
        <div class="gps-cart-head">
            <div class="gps-cart-head__txt">
                <h1 class="gps-cart-head__title"><?php esc_html_e('Your cart', 'gps-courses'); ?></h1>
                <p class="gps-cart-head__sub">
                    <?php
                    /* translators: %d: number of items in the cart */
                    ?><span class="gps-cart-head__count"><?php echo esc_html(sprintf(_n('%d item', '%d items', $count, 'gps-courses'), $count)); ?></span><?php
                    ?>
                    <span aria-hidden="true">&middot;</span>
                    <?php esc_html_e('Your seat is confirmed by email once payment is complete.', 'gps-courses'); ?>
                </p>
            </div>
            <a class="gps-cart-head__back" href="<?php echo esc_url($courses); ?>">
                <span aria-hidden="true">&larr;</span> <?php esc_html_e('Continue browsing', 'gps-courses'); ?>
            </a>
            <ol class="gps-cart-steps" aria-label="<?php esc_attr_e('Checkout progress', 'gps-courses'); ?>">
                <li class="is-current" aria-current="step"><span aria-hidden="true">1</span> <?php esc_html_e('Cart', 'gps-courses'); ?></li>
                <li><span aria-hidden="true">2</span> <?php esc_html_e('Details & payment', 'gps-courses'); ?></li>
                <li><span aria-hidden="true">3</span> <?php esc_html_e('Confirmation', 'gps-courses'); ?></li>
            </ol>
        </div>
        <?php
    }

    public static function secure_note() {
        ?>
        <p class="gps-cart-secure">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            <?php esc_html_e('Secure payment. Tickets and QR codes are emailed right after purchase.', 'gps-courses'); ?>
        </p>
        <?php
    }

    /* ============================================================
     * Cart lines: course date, CE credits and course image
     * ============================================================ */

    /** On checkout the other templates decide what to show. */
    protected static function on_checkout() {
        return function_exists('is_checkout') && is_checkout();
    }

    /**
     * What a product is in GPS terms: a course ticket, a seminar enrollment or
     * a single seminar session. Returns ['post_id', 'when', 'ce'] or [].
     */
    protected static function context($product_id) {
        $product_id = (int) $product_id;
        if (isset(self::$context[$product_id])) {
            return self::$context[$product_id];
        }
        $ctx = [];

        // Course ticket
        $ticket_id = (int) get_post_meta($product_id, '_gps_ticket_type_id', true);
        if (!$ticket_id) {
            $found = get_posts([
                'post_type'      => 'gps_ticket',
                'post_status'    => 'publish',
                'numberposts'    => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    ['key' => '_gps_wc_product_id', 'value' => $product_id, 'type' => 'NUMERIC'],
                ],
            ]);
            $ticket_id = $found ? (int) $found[0] : 0;
        }
        $event_id = $ticket_id ? (int) get_post_meta($ticket_id, '_gps_event_id', true) : 0;

        if ($event_id) {
            $start = (string) get_post_meta($event_id, '_gps_start_date', true);
            $end   = (string) get_post_meta($event_id, '_gps_end_date', true);
            $ctx = [
                'post_id' => $event_id,
                'when'    => self::date_range($start, $end),
                'ce'      => (float) get_post_meta($event_id, '_gps_ce_credits', true),
            ];
        } elseif (class_exists(__NAMESPACE__ . '\\Session_Tickets') && ($session = Session_Tickets::get_session_by_product($product_id))) {
            // Single seminar session
            $ctx = [
                'post_id' => (int) $session->seminar_id,
                'when'    => self::date_range((string) $session->session_date, ''),
                'ce'      => isset($session->individual_ce_credits) ? (float) $session->individual_ce_credits : 0.0,
            ];
        } elseif ($seminar_id = (int) get_post_meta($product_id, '_gps_seminar_id', true)) {
            // Monthly seminar program
            $ctx = [
                'post_id' => $seminar_id,
                'when'    => (string) get_post_meta($seminar_id, '_gps_seminar_year', true),
                'ce'      => 0.0,
            ];
        }

        self::$context[$product_id] = $ctx;
        return $ctx;
    }

    protected static function date_range($start, $end) {
        if (class_exists(__NAMESPACE__ . '\\Account_Dashboard')) {
            return Account_Dashboard::date_range($start, $end);
        }
        $s = $start ? strtotime($start) : 0;
        return $s ? date_i18n('M j, Y', $s) : '';
    }

    public static function item_data($data, $cart_item) {
        if (self::on_checkout() || empty($cart_item['product_id'])) {
            return $data;
        }
        $ctx = self::context($cart_item['product_id']);
        if (!$ctx) {
            return $data;
        }
        $data = is_array($data) ? $data : [];
        if ('' !== $ctx['when']) {
            $data[] = ['key' => __('Date', 'gps-courses'), 'value' => $ctx['when']];
        }
        if ($ctx['ce'] > 0) {
            $data[] = ['key' => __('CE credits', 'gps-courses'), 'value' => wc_format_localized_decimal(rtrim(rtrim(number_format((float) $ctx['ce'], 2, '.', ''), '0'), '.'))];
        }
        return $data;
    }

    /** Products without their own image show the course (or seminar) image. */
    public static function item_thumbnail($html, $cart_item) {
        if (self::on_checkout() || empty($cart_item['data']) || !is_object($cart_item['data']) || $cart_item['data']->get_image_id()) {
            return $html;
        }
        $ctx = self::context($cart_item['product_id']);
        if ($ctx && has_post_thumbnail($ctx['post_id'])) {
            return get_the_post_thumbnail($ctx['post_id'], 'woocommerce_thumbnail', ['alt' => '', 'loading' => 'lazy']);
        }
        return $html;
    }
}
