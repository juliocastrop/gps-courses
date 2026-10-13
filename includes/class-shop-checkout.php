<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Checkout, order received and My Account orders in the GPS look.
 *
 * The checkout itself is the Elementor Pro "Checkout" widget on page 71
 * (classic [woocommerce_checkout] underneath), so this class only adds
 * small pieces through WooCommerce hooks and one scoped stylesheet:
 *
 *   - Checkout:       page heading with progress steps, trust notes under
 *                     the Place order button.
 *   - Order received: confirmation header, "what happens next" panel with
 *                     the courses / seminars bought and the account and
 *                     receipt buttons.
 *   - My Account:     "Receipt" action in the Orders list.
 *
 * No WooCommerce template is replaced, so payment gateways (Stripe) and
 * other plugins hooking the checkout keep working unchanged.
 */
class Shop_Checkout {

    public static function init() {
        if (!apply_filters('gps_shop_checkout_enabled', true)) {
            return;
        }

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 120);
        add_filter('body_class', [__CLASS__, 'body_class']);

        // Checkout
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'render_checkout_header'], 5);
        add_action('woocommerce_review_order_after_submit', [__CLASS__, 'render_trust_notes']);

        // Order received
        add_action('woocommerce_before_thankyou', [__CLASS__, 'render_thankyou_header'], 5);
        add_filter('woocommerce_thankyou_order_received_text', [__CLASS__, 'order_received_text'], 20, 2);
        add_action('woocommerce_thankyou', [__CLASS__, 'render_next_steps'], 5);
        add_filter('gps_receipt_show_customer_button', [__CLASS__, 'receipt_button_outside_thankyou']);

        // My Account > Orders
        add_filter('woocommerce_my_account_my_orders_actions', [__CLASS__, 'add_receipt_action'], 10, 2);
    }

    /* ====================================================================
     * Where we are
     * ==================================================================== */

    /** 'checkout' | 'thankyou' | 'orders' | '' */
    protected static function context() {
        if (!function_exists('is_checkout')) {
            return '';
        }
        if (is_order_received_page()) {
            return 'thankyou';
        }
        if (is_checkout()) {
            return 'checkout';
        }
        if (is_account_page() && (is_wc_endpoint_url('orders') || is_wc_endpoint_url('view-order'))) {
            return 'orders';
        }
        return '';
    }

    public static function enqueue_assets() {
        if (self::context() === '') {
            return;
        }
        $css = GPSC_PATH . 'assets/css/gps-checkout.css';
        wp_enqueue_style('gps-checkout', GPSC_URL . 'assets/css/gps-checkout.css', ['gps-forms'], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
    }

    /** Scopes every rule in gps-checkout.css to these pages. */
    public static function body_class($classes) {
        $context = self::context();
        if ($context !== '') {
            $classes[] = 'gps-shop';
            $classes[] = 'gps-shop--' . $context;
        }
        return $classes;
    }

    /* ====================================================================
     * Checkout
     * ==================================================================== */

    public static function render_checkout_header() {
        if (is_wc_endpoint_url('order-pay')) {
            return;
        }
        ?>
        <div class="gps-co-head">
            <p class="gps-co-head__eyebrow">
                <?php echo self::icon('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                <?php esc_html_e('Secure checkout', 'gps-courses'); ?>
            </p>
            <h1 class="gps-co-head__title"><?php esc_html_e('Complete your registration', 'gps-courses'); ?></h1>
            <ol class="gps-co-steps" aria-label="<?php esc_attr_e('Checkout progress', 'gps-courses'); ?>">
                <li class="gps-co-steps__step is-done">
                    <a href="<?php echo esc_url(wc_get_cart_url()); ?>">
                        <span class="gps-co-steps__dot" aria-hidden="true"><?php echo self::icon('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
                        <?php esc_html_e('Cart', 'gps-courses'); ?>
                    </a>
                </li>
                <li class="gps-co-steps__step is-current" aria-current="step">
                    <span class="gps-co-steps__dot" aria-hidden="true">2</span>
                    <?php esc_html_e('Details & payment', 'gps-courses'); ?>
                </li>
                <li class="gps-co-steps__step">
                    <span class="gps-co-steps__dot" aria-hidden="true">3</span>
                    <?php esc_html_e('Confirmation', 'gps-courses'); ?>
                </li>
            </ol>
        </div>
        <?php
    }

    /**
     * Under the Place order button. Printed inside #payment, so it is
     * re-rendered with the payment box on every checkout refresh.
     */
    public static function render_trust_notes() {
        $stripe = false;
        if (function_exists('WC') && WC()->payment_gateways()) {
            foreach (array_keys(WC()->payment_gateways()->get_available_payment_gateways()) as $id) {
                if (strpos((string) $id, 'stripe') === 0) {
                    $stripe = true;
                    break;
                }
            }
        }
        $payment_note = $stripe
            ? __('Payments are encrypted and processed by Stripe. We never see or store your card details.', 'gps-courses')
            : __('Payments are encrypted and processed securely. We never store your card details.', 'gps-courses');
        ?>
        <ul class="gps-co-trust">
            <li>
                <?php echo self::icon('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                <span><?php echo esc_html($payment_note); ?></span>
            </li>
            <li>
                <?php echo self::icon('award'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                <span><?php esc_html_e('Nationally Approved PACE Program Provider for FAGD/MAGD credit.', 'gps-courses'); ?></span>
            </li>
        </ul>
        <?php
    }

    /* ====================================================================
     * Order received
     * ==================================================================== */

    /** GPS lines of the order, described for the page. */
    protected static function gps_items($order) {
        $items = [];
        if (!class_exists(__NAMESPACE__ . '\\Receipts')) {
            return $items;
        }
        foreach ($order->get_items() as $item) {
            $info = Receipts::describe_item($item);
            if ($info['type'] !== 'other') {
                $items[] = $info;
            }
        }
        return $items;
    }

    public static function render_thankyou_header($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->has_status('failed')) {
            return;
        }

        $name  = $order->get_billing_first_name();
        $paid  = $order->is_paid();
        $email = $order->get_billing_email();
        $gps   = (bool) self::gps_items($order);

        if ($paid && $gps) {
            $title = $name
                /* translators: %s: customer first name */
                ? sprintf(__('Thank you, %s! You are registered.', 'gps-courses'), $name)
                : __('Thank you! You are registered.', 'gps-courses');
        } elseif ($paid) {
            $title = $name
                /* translators: %s: customer first name */
                ? sprintf(__('Thank you, %s! Your order is confirmed.', 'gps-courses'), $name)
                : __('Thank you! Your order is confirmed.', 'gps-courses');
        } else {
            $title = $name
                /* translators: %s: customer first name */
                ? sprintf(__('Thank you, %s! We have received your order.', 'gps-courses'), $name)
                : __('Thank you! We have received your order.', 'gps-courses');
        }
        ?>
        <header class="gps-ty-hero<?php echo $paid ? '' : ' gps-ty-hero--pending'; ?>">
            <span class="gps-ty-hero__icon" aria-hidden="true"><?php echo self::icon($paid ? 'check' : 'clock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
            <div class="gps-ty-hero__body">
                <p class="gps-ty-hero__eyebrow">
                    <?php
                    /* translators: %s: order number */
                    echo esc_html(sprintf(__('Order #%s', 'gps-courses'), $order->get_order_number()));
                    echo ' · ';
                    echo $paid ? esc_html__('Payment received', 'gps-courses') : esc_html(wc_get_order_status_name($order->get_status()));
                    ?>
                </p>
                <h1 class="gps-ty-hero__title"><?php echo esc_html($title); ?></h1>
                <?php if ($email) : ?>
                    <p class="gps-ty-hero__text">
                        <?php
                        if ($paid) {
                            /* translators: %s: email address */
                            printf(esc_html__('A confirmation has been sent to %s.', 'gps-courses'), '<strong>' . esc_html($email) . '</strong>');
                        } else {
                            /* translators: %s: email address */
                            printf(esc_html__('We will email %s as soon as your payment is confirmed.', 'gps-courses'), '<strong>' . esc_html($email) . '</strong>');
                        }
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        </header>
        <?php
    }

    /** Our header already says it; WooCommerce's sentence would repeat it. */
    public static function order_received_text($text, $order = null) {
        if ($order instanceof \WC_Order && !$order->has_status('failed')) {
            return '';
        }
        return $text;
    }

    public static function render_next_steps($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->has_status('failed')) {
            return;
        }

        $items     = self::gps_items($order);
        $types     = array_unique(wp_list_pluck($items, 'type'));
        $course    = in_array('course', $types, true);
        $seminar   = in_array('seminar', $types, true);
        $session   = in_array('session', $types, true);
        $paid      = $order->is_paid();
        $email     = $order->get_billing_email();
        $member    = (bool) $order->get_customer_id();
        $receipt   = class_exists(__NAMESPACE__ . '\\Receipts') ? Receipts::customer_download_url($order) : '';
        $courses   = (string) apply_filters('gps_thankyou_courses_url', home_url('/courses/'));

        // Course tickets live in My Tickets; seminar enrollments and single
        // session tickets live in Monthly Seminars.
        if ($member && $course) {
            $primary = ['url' => wc_get_account_endpoint_url('gps-tickets'), 'label' => __('View my tickets', 'gps-courses')];
        } elseif ($member && ($seminar || $session)) {
            $primary = ['url' => wc_get_account_endpoint_url('gps-seminars'), 'label' => __('View my seminar', 'gps-courses')];
        } else {
            $primary = ['url' => wc_get_page_permalink('myaccount'), 'label' => __('Go to My Account', 'gps-courses')];
        }

        $steps = [];
        if ($items) {
            $when = $paid ? __('within a few minutes', 'gps-courses') : __('as soon as your payment is confirmed', 'gps-courses');
            if ($course && ($seminar || $session)) {
                /* translators: 1: email address, 2: "within a few minutes" or "as soon as your payment is confirmed" */
                $mail = __('Your course ticket and your seminar QR code are sent to %1$s %2$s. If you do not see them, check your spam folder.', 'gps-courses');
            } elseif ($course) {
                /* translators: 1: email address, 2: "within a few minutes" or "as soon as your payment is confirmed" */
                $mail = __('Your ticket with its QR code is sent to %1$s %2$s. If you do not see it, check your spam folder.', 'gps-courses');
            } elseif ($session && !$seminar) {
                /* translators: 1: email address, 2: "within a few minutes" or "as soon as your payment is confirmed" */
                $mail = __('Your session ticket with its QR code is sent to %1$s %2$s. If you do not see it, check your spam folder.', 'gps-courses');
            } else {
                /* translators: 1: email address, 2: "within a few minutes" or "as soon as your payment is confirmed" */
                $mail = __('Your seminar QR code is sent to %1$s %2$s. If you do not see it, check your spam folder.', 'gps-courses');
            }
            $steps[] = [__('Check your email.', 'gps-courses'), sprintf($mail, $email, $when)];

            if ($member) {
                $places = [];
                if ($course) $places[] = __('My Tickets', 'gps-courses');
                if ($seminar || $session) $places[] = __('Monthly Seminars', 'gps-courses');
                $steps[] = [
                    __('Find it anytime.', 'gps-courses'),
                    /* translators: %s: account section names, e.g. "My Tickets" */
                    sprintf(__('It is also saved in My Account under %s.', 'gps-courses'), implode(__(' and ', 'gps-courses'), $places)),
                ];
            }
            if ($course) {
                $steps[] = [
                    __('On course day,', 'gps-courses'),
                    __('show the QR code at check-in, on your phone or printed. Your CE credits are added to your account when your certificate is issued.', 'gps-courses'),
                ];
            }
            if ($seminar) {
                $steps[] = [
                    __('At every session,', 'gps-courses'),
                    __('the same QR code checks you in. Each session earns 2 CE credits, and one makeup session is available if you miss a month.', 'gps-courses'),
                ];
            } elseif ($session) {
                $steps[] = [
                    __('On the session day,', 'gps-courses'),
                    __('show the QR code at check-in, on your phone or printed.', 'gps-courses'),
                ];
            }
        }
        ?>
        <section class="gps-ty-next" aria-labelledby="gps-ty-next-title">
            <?php if ($items) : ?>
                <h2 id="gps-ty-next-title" class="gps-ty-next__title"><?php esc_html_e('What happens next', 'gps-courses'); ?></h2>

                <ul class="gps-ty-items">
                    <?php foreach ($items as $info) : ?>
                        <li class="gps-ty-item gps-ty-item--<?php echo esc_attr($info['type']); ?>">
                            <span class="gps-ty-item__tag"><?php echo esc_html(self::type_label($info['type'])); ?></span>
                            <p class="gps-ty-item__title">
                                <?php if ($info['url']) : ?>
                                    <a href="<?php echo esc_url($info['url']); ?>"><?php echo esc_html($info['title']); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($info['title']); ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($info['when'] || $info['where']) : ?>
                                <p class="gps-ty-item__meta">
                                    <?php if ($info['when']) : ?>
                                        <span><?php echo self::icon('calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?><?php echo esc_html($info['when']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($info['where']) : ?>
                                        <span><?php echo self::icon('pin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?><?php echo esc_html($info['where']); ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <ol class="gps-ty-steps">
                    <?php foreach ($steps as $step) : ?>
                        <li><strong><?php echo esc_html($step[0]); ?></strong> <?php echo esc_html($step[1]); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?>
                <h2 id="gps-ty-next-title" class="screen-reader-text"><?php esc_html_e('Next steps', 'gps-courses'); ?></h2>
            <?php endif; ?>

            <div class="gps-ty-actions">
                <a class="gps-ty-btn gps-ty-btn--navy" href="<?php echo esc_url($primary['url']); ?>"><?php echo esc_html($primary['label']); ?></a>
                <?php if ($receipt) : ?>
                    <a class="gps-ty-btn gps-ty-btn--line" href="<?php echo esc_url($receipt); ?>" target="_blank" rel="noopener">
                        <?php echo self::icon('download'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                        <?php esc_html_e('Download receipt (PDF)', 'gps-courses'); ?>
                        <span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'gps-courses'); ?></span>
                    </a>
                <?php endif; ?>
                <a class="gps-ty-link" href="<?php echo esc_url($courses); ?>"><?php esc_html_e('Browse more courses', 'gps-courses'); ?></a>
            </div>
        </section>
        <?php
    }

    /** The thank-you page has its own receipt button in the actions panel. */
    public static function receipt_button_outside_thankyou($show) {
        return (function_exists('is_order_received_page') && is_order_received_page()) ? false : $show;
    }

    protected static function type_label($type) {
        switch ($type) {
            case 'course':
                return __('Course', 'gps-courses');
            case 'seminar':
                return __('Monthly Seminar', 'gps-courses');
            case 'session':
                return __('Seminar session', 'gps-courses');
        }
        return '';
    }

    /* ====================================================================
     * My Account > Orders
     * ==================================================================== */

    public static function add_receipt_action($actions, $order) {
        // Only the Orders list: order-details.php also prints these actions, and the
        // thank-you and view-order pages already have their own receipt button
        if (!class_exists(__NAMESPACE__ . '\\Receipts') || !is_wc_endpoint_url('orders')) {
            return $actions;
        }
        $url = Receipts::customer_download_url($order);
        if ($url !== '') {
            $actions['gps-receipt'] = [
                'url'        => $url,
                'name'       => __('Receipt', 'gps-courses'),
                /* translators: %s: order number */
                'aria-label' => sprintf(__('Download the receipt for order %s', 'gps-courses'), $order->get_order_number()),
            ];
        }
        return $actions;
    }

    /* ====================================================================
     * Icons (inline, decorative)
     * ==================================================================== */

    protected static function icon($name) {
        $paths = [
            'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
            'check'    => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'shield'   => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-4.5"/>',
            'award'    => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>',
            'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
            'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
            'download' => '<path d="M12 4v11M7 10.5l5 5 5-5M5 20h14"/>',
        ];
        if (!isset($paths[$name])) {
            return '';
        }
        return '<svg class="gps-ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}
