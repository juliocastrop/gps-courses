<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Custom display label per WooCommerce coupon.
 *
 * Lets admins set a friendly text (e.g. "Georgia Prosthodontics
 * Discount") that replaces the bare coupon code in the cart, checkout,
 * order admin and email summaries. Stored as `_gps_coupon_label`
 * post meta on the shop_coupon CPT.
 */
class Coupon_Labels {

    const META_KEY = '_gps_coupon_label';

    public static function init() {
        // Admin: render + save the custom label field on the coupon edit screen
        add_action('woocommerce_coupon_options',      [__CLASS__, 'render_field'], 10, 2);
        add_action('woocommerce_coupon_options_save', [__CLASS__, 'save_field'],   10, 2);

        // Cart, checkout, my-account, order received page
        add_filter('woocommerce_cart_totals_coupon_label', [__CLASS__, 'filter_cart_label'], 10, 2);

        // Order admin + order emails (coupon line item name)
        add_filter('woocommerce_order_item_name', [__CLASS__, 'filter_order_item_name'], 10, 2);
    }

    /* ====================================================================
     * Admin field
     * ==================================================================== */

    public static function render_field($coupon_id, $coupon) {
        $label = get_post_meta($coupon_id, self::META_KEY, true);
        ?>
        <p class="form-field gps-coupon-label-field">
            <label for="gps_coupon_label"><?php esc_html_e('Custom Display Label', 'gps-courses'); ?></label>
            <input type="text"
                   id="gps_coupon_label"
                   name="gps_coupon_label"
                   class="short"
                   style="width: 50%;"
                   value="<?php echo esc_attr($label); ?>"
                   placeholder="<?php esc_attr_e('e.g. Georgia Prosthodontics Discount', 'gps-courses'); ?>">
            <span class="description" style="display:block; margin-top:6px;">
                <?php esc_html_e('Shown in cart, checkout, orders and emails instead of the coupon code. Leave empty to display the code as usual.', 'gps-courses'); ?>
            </span>
        </p>
        <?php
    }

    public static function save_field($post_id, $coupon) {
        $value = isset($_POST['gps_coupon_label']) ? trim(wp_unslash($_POST['gps_coupon_label'])) : '';
        if ($value === '') {
            delete_post_meta($post_id, self::META_KEY);
        } else {
            update_post_meta($post_id, self::META_KEY, sanitize_text_field($value));
        }
    }

    /* ====================================================================
     * Display filters
     * ==================================================================== */

    /**
     * Cart / checkout / my-account / order received.
     *
     * @param string     $label  e.g. "Coupon: PRFGPS"
     * @param \WC_Coupon $coupon
     */
    public static function filter_cart_label($label, $coupon) {
        if (!$coupon instanceof \WC_Coupon) return $label;

        $custom = self::get_label_for_code($coupon->get_code());
        if ($custom) {
            return esc_html($custom);
        }
        return $label;
    }

    /**
     * Order line item name. Applies in admin order screens, emails,
     * thank-you page and my-account order details.
     *
     * Signature: ($item_name, $item, $is_visible = false)
     */
    public static function filter_order_item_name($item_name, $item) {
        if (!is_object($item) || !method_exists($item, 'get_type') || $item->get_type() !== 'coupon') {
            return $item_name;
        }

        // Coupon items expose get_code() in WC 3.0+
        if (!method_exists($item, 'get_code')) {
            return $item_name;
        }

        $custom = self::get_label_for_code($item->get_code());
        if ($custom) {
            return esc_html($custom);
        }
        return $item_name;
    }

    /* ====================================================================
     * Helper
     * ==================================================================== */

    /**
     * Resolve the custom label for a coupon code, with a small request-
     * scoped cache so repeated calls during a single render don't hit
     * the DB once per row.
     */
    public static function get_label_for_code($code) {
        static $cache = [];

        $code = strtolower(trim((string) $code));
        if ($code === '') return '';

        if (array_key_exists($code, $cache)) {
            return $cache[$code];
        }

        $coupon = new \WC_Coupon($code);
        $id = $coupon->get_id();
        if (!$id) {
            return $cache[$code] = '';
        }

        $label = get_post_meta($id, self::META_KEY, true);
        return $cache[$code] = is_string($label) ? $label : '';
    }
}
