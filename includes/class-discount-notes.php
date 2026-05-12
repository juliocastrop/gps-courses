<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Per-order discount notes.
 *
 * Lets admins attach an explanatory note to each coupon applied on a
 * specific order (different from the global per-coupon display label
 * handled by Coupon_Labels). Stored as order meta as a JSON map of
 * { coupon_code: note }.
 *
 * The note surfaces in the GPS receipt PDF next to the discount line
 * and inside the order admin metabox.
 */
class Discount_Notes {

    const META_KEY = '_gps_discount_notes';

    public static function init() {
        add_action('add_meta_boxes',    [__CLASS__, 'register_metabox']);
        add_action('save_post_shop_order',           [__CLASS__, 'save'], 10, 1);
        // HPOS (WC custom order tables) save hook
        add_action('woocommerce_process_shop_order_meta', [__CLASS__, 'save'], 10, 1);
    }

    public static function register_metabox() {
        // Support both classic post-based orders and HPOS screen
        $screens = ['shop_order'];
        if (class_exists('\\Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\CustomOrdersTableController')) {
            $hpos_screen = wc_get_page_screen_id('shop-order');
            if ($hpos_screen) $screens[] = $hpos_screen;
        }

        foreach ($screens as $screen) {
            add_meta_box(
                'gps_discount_notes',
                __('Discount Notes', 'gps-courses'),
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
            echo '<p style="color:#777;">' . esc_html__('Order not found.', 'gps-courses') . '</p>';
            return;
        }

        $coupons = $order->get_coupon_codes();
        if (empty($coupons)) {
            echo '<p style="color:#777; font-size:12px;">' . esc_html__('No coupons were applied to this order.', 'gps-courses') . '</p>';
            return;
        }

        $notes = self::get_notes($order);
        wp_nonce_field('gps_discount_notes_save', 'gps_discount_notes_nonce');
        ?>
        <p style="font-size:12px; color:#64748b; margin:0 0 12px;">
            <?php esc_html_e('Optional explanatory note shown next to each discount in the receipt and admin views.', 'gps-courses'); ?>
        </p>
        <?php foreach ($coupons as $code):
            $key = strtolower($code);
            $note = $notes[$key] ?? '';
            $global_label = Coupon_Labels::get_label_for_code($code);
            ?>
            <p style="margin:0 0 8px;">
                <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">
                    <?php echo esc_html($global_label ?: strtoupper($code)); ?>
                    <span style="color:#94a3b8; font-weight:normal;">— <?php echo esc_html(strtoupper($code)); ?></span>
                </label>
                <textarea name="gps_discount_notes[<?php echo esc_attr($key); ?>]"
                          rows="2"
                          class="widefat"
                          style="font-size:12px;"
                          placeholder="<?php esc_attr_e('e.g. Dr. Smith referral — March promo', 'gps-courses'); ?>"><?php echo esc_textarea($note); ?></textarea>
            </p>
        <?php endforeach; ?>
        <?php
    }

    public static function save($order_id) {
        if (!isset($_POST['gps_discount_notes_nonce']) || !wp_verify_nonce($_POST['gps_discount_notes_nonce'], 'gps_discount_notes_save')) {
            return;
        }
        if (!current_user_can('edit_shop_orders')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) return;

        $input = $_POST['gps_discount_notes'] ?? [];
        if (!is_array($input)) return;

        $clean = [];
        foreach ($input as $code => $note) {
            $note = trim(wp_unslash($note));
            if ($note === '') continue;
            $clean[strtolower(sanitize_text_field($code))] = sanitize_textarea_field($note);
        }

        if (empty($clean)) {
            $order->delete_meta_data(self::META_KEY);
        } else {
            $order->update_meta_data(self::META_KEY, wp_json_encode($clean));
        }
        $order->save();
    }

    /* ====================================================================
     * Public helpers
     * ==================================================================== */

    /**
     * @param \WC_Order $order
     * @return array<string,string> map of lowercased coupon code => note
     */
    public static function get_notes($order) {
        if (!$order instanceof \WC_Order) return [];

        $raw = $order->get_meta(self::META_KEY);
        if (empty($raw)) return [];

        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function get_note($order, $code) {
        $notes = self::get_notes($order);
        return $notes[strtolower($code)] ?? '';
    }

    /* ====================================================================
     * Internal
     * ==================================================================== */

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
}
