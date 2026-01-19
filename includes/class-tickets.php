<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Ticket management class
 * Handles ticket types (Early Bird, VIP, General) and their scheduling
 */
class Tickets {

    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('admin_init', [__CLASS__, 'register_metaboxes']);
        add_action('save_post_gps_ticket', [__CLASS__, 'save_ticket_meta']);

        // Auto-schedule ticket availability
        add_action('wp', [__CLASS__, 'check_ticket_schedules']);

        // Admin columns
        add_filter('manage_gps_ticket_posts_columns', [__CLASS__, 'add_ticket_columns']);
        add_action('manage_gps_ticket_posts_custom_column', [__CLASS__, 'render_ticket_columns'], 10, 2);
        add_filter('manage_edit-gps_ticket_sortable_columns', [__CLASS__, 'sortable_ticket_columns']);
    }

    /**
     * Register Ticket CPT
     */
    public static function register_cpt() {
        register_post_type('gps_ticket', [
            'label'         => __('Tickets', 'gps-courses'),
            'description'   => __('Ticket types for courses', 'gps-courses'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'gps-dashboard',
            'menu_icon'     => 'dashicons-tickets-alt',
            'supports'      => ['title'],
            'show_in_rest'  => true,
        ]);

        // Ticket metadata
        register_post_meta('gps_ticket', '_gps_event_id', [
            'type'          => 'integer',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_ticket_type', [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_ticket_price', [
            'type'          => 'number',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_ticket_quantity', [
            'type'          => 'integer',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_ticket_start_date', [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_ticket_end_date', [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_wc_product_id', [
            'type'          => 'integer',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_ticket_status', [
            'type'          => 'string',
            'single'        => true,
            'default'       => 'inactive',
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_ticket_features', [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_ticket_internal_label', [
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => false, // Not exposed to REST API - admin only
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('gps_ticket', '_gps_manual_sold_out', [
            'type'          => 'boolean',
            'single'        => true,
            'default'       => false,
            'show_in_rest'  => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);
    }

    /**
     * Register metaboxes
     */
    public static function register_metaboxes() {
        add_meta_box(
            'gps_ticket_meta',
            __('Ticket Configuration', 'gps-courses'),
            [__CLASS__, 'render_ticket_meta'],
            'gps_ticket',
            'normal',
            'high'
        );
    }

    /**
     * Render ticket metabox
     */
    public static function render_ticket_meta($post) {
        wp_nonce_field('gps_ticket_meta', 'gps_ticket_nonce');

        $event_id   = (int) get_post_meta($post->ID, '_gps_event_id', true);
        $type       = get_post_meta($post->ID, '_gps_ticket_type', true);
        $price      = get_post_meta($post->ID, '_gps_ticket_price', true);
        $quantity   = (int) get_post_meta($post->ID, '_gps_ticket_quantity', true);
        $start_date = get_post_meta($post->ID, '_gps_ticket_start_date', true);
        $end_date   = get_post_meta($post->ID, '_gps_ticket_end_date', true);
        $product_id = (int) get_post_meta($post->ID, '_gps_wc_product_id', true);
        $status     = get_post_meta($post->ID, '_gps_ticket_status', true) ?: 'inactive';
        $features   = get_post_meta($post->ID, '_gps_ticket_features', true);
        $internal_label = get_post_meta($post->ID, '_gps_ticket_internal_label', true);
        $manual_sold_out = get_post_meta($post->ID, '_gps_manual_sold_out', true);

        // Get all events
        $events = get_posts([
            'post_type'   => 'gps_event',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby'     => 'title',
            'order'       => 'ASC'
        ]);

        ?>
        <style>
            .gps-ticket-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .gps-ticket-meta .full-width { grid-column: 1 / -1; }
            .gps-ticket-field { margin-bottom: 15px; }
            .gps-ticket-field label { display: block; font-weight: 600; margin-bottom: 5px; }
            .gps-ticket-field input, .gps-ticket-field select { width: 100%; }
            .gps-ticket-status { padding: 10px; background: #f0f0f1; border-radius: 4px; margin-top: 20px; }
        </style>

        <div class="gps-ticket-meta">
            <div class="gps-ticket-field full-width">
                <label for="gps_event_id"><?php _e('Course/Event', 'gps-courses'); ?> *</label>
                <select name="gps_event_id" id="gps_event_id" required>
                    <option value=""><?php _e('— Select Course —', 'gps-courses'); ?></option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int) $event->ID; ?>" <?php selected($event_id, $event->ID); ?>>
                            <?php echo esc_html($event->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="gps-ticket-field">
                <label for="gps_ticket_type"><?php _e('Ticket Type', 'gps-courses'); ?> *</label>
                <select name="gps_ticket_type" id="gps_ticket_type" required>
                    <option value=""><?php _e('— Select Type —', 'gps-courses'); ?></option>
                    <option value="early_bird" <?php selected($type, 'early_bird'); ?>><?php _e('Early Bird', 'gps-courses'); ?></option>
                    <option value="general" <?php selected($type, 'general'); ?>><?php _e('General Admission', 'gps-courses'); ?></option>
                    <option value="vip" <?php selected($type, 'vip'); ?>><?php _e('VIP', 'gps-courses'); ?></option>
                    <option value="group" <?php selected($type, 'group'); ?>><?php _e('Group', 'gps-courses'); ?></option>
                </select>
            </div>

            <div class="gps-ticket-field">
                <label for="gps_ticket_internal_label"><?php _e('Internal Label', 'gps-courses'); ?></label>
                <input type="text" name="gps_ticket_internal_label" id="gps_ticket_internal_label"
                       value="<?php echo esc_attr($internal_label); ?>"
                       placeholder="<?php _e('e.g., Early Bird - Promo Code', 'gps-courses'); ?>">
                <small style="color: #d63638;">
                    <strong><?php _e('⚠ Admin Only:', 'gps-courses'); ?></strong>
                    <?php _e('This label is only visible to administrators for internal organization. It will never be displayed to the public.', 'gps-courses'); ?>
                </small>
            </div>

            <div class="gps-ticket-field">
                <label for="gps_ticket_price"><?php _e('Price', 'gps-courses'); ?> *</label>
                <input type="number" name="gps_ticket_price" id="gps_ticket_price"
                       value="<?php echo esc_attr($price); ?>"
                       min="0" step="0.01" required
                       placeholder="200.00">
            </div>

            <div class="gps-ticket-field">
                <label for="gps_ticket_quantity"><?php _e('Available Quantity', 'gps-courses'); ?></label>
                <input type="number" name="gps_ticket_quantity" id="gps_ticket_quantity"
                       value="<?php echo esc_attr($quantity); ?>"
                       min="0" step="1"
                       placeholder="<?php _e('Leave empty for unlimited', 'gps-courses'); ?>">
            </div>

            <div class="gps-ticket-field">
                <label for="gps_ticket_start_date"><?php _e('Available From', 'gps-courses'); ?></label>
                <input type="datetime-local" name="gps_ticket_start_date" id="gps_ticket_start_date"
                       value="<?php echo esc_attr($start_date); ?>">
                <small><?php _e('When this ticket type becomes available for purchase', 'gps-courses'); ?></small>
            </div>

            <div class="gps-ticket-field">
                <label for="gps_ticket_end_date"><?php _e('Available Until', 'gps-courses'); ?></label>
                <input type="datetime-local" name="gps_ticket_end_date" id="gps_ticket_end_date"
                       value="<?php echo esc_attr($end_date); ?>">
                <small><?php _e('When this ticket type stops being available', 'gps-courses'); ?></small>
            </div>

            <div class="gps-ticket-field full-width">
                <label for="gps_wc_product_id"><?php _e('WooCommerce Product ID', 'gps-courses'); ?></label>
                <input type="number" name="gps_wc_product_id" id="gps_wc_product_id"
                       value="<?php echo esc_attr($product_id); ?>"
                       min="0" step="1">
                <small><?php _e('Link to existing WooCommerce product, or leave empty to auto-create', 'gps-courses'); ?></small>
            </div>

            <div class="gps-ticket-field full-width">
                <label for="gps_ticket_features"><?php _e('Ticket Features', 'gps-courses'); ?></label>
                <textarea name="gps_ticket_features" id="gps_ticket_features" rows="5" style="width: 100%;"><?php echo esc_textarea($features); ?></textarea>
                <small><?php _e('Enter one feature per line (e.g., "Access to all sessions", "Course materials included", "CE Credits certificate")', 'gps-courses'); ?></small>
            </div>

            <div class="gps-ticket-status full-width">
                <strong><?php _e('Current Status:', 'gps-courses'); ?></strong>
                <span style="margin-left: 10px; font-weight: bold; color: <?php echo $status === 'active' ? '#46b450' : '#999'; ?>">
                    <?php echo esc_html(ucfirst($status)); ?>
                </span>
                <p><small><?php _e('Status is automatically updated based on availability dates', 'gps-courses'); ?></small></p>
            </div>

            <div class="gps-ticket-field full-width" style="margin-top: 15px; padding: 15px; background: <?php echo $manual_sold_out ? '#fff3cd' : '#f8f9fa'; ?>; border: 2px solid <?php echo $manual_sold_out ? '#ffc107' : '#dee2e6'; ?>; border-radius: 8px;">
                <label for="gps_manual_sold_out" style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox"
                           name="gps_manual_sold_out"
                           id="gps_manual_sold_out"
                           value="1"
                           style="width: 20px; height: 20px; margin-right: 10px;"
                           <?php checked($manual_sold_out, '1'); ?>>
                    <span style="font-size: 14px; font-weight: 600;">
                        <?php _e('Mark as Sold Out (Manual Override)', 'gps-courses'); ?>
                    </span>
                </label>
                <p style="margin: 10px 0 0 30px; color: #666;">
                    <small>
                        <?php _e('When enabled, this ticket will display as "Sold Out" regardless of actual stock levels. Customers will be able to join a waitlist. Use this to manually close ticket sales.', 'gps-courses'); ?>
                    </small>
                </p>
                <?php if ($manual_sold_out): ?>
                <p style="margin: 10px 0 0 30px; color: #856404; font-weight: 600;">
                    <span class="dashicons dashicons-warning" style="font-size: 16px; vertical-align: text-bottom;"></span>
                    <?php _e('This ticket is currently marked as Sold Out manually.', 'gps-courses'); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save ticket metadata
     */
    public static function save_ticket_meta($post_id) {
        if (!isset($_POST['gps_ticket_nonce']) || !wp_verify_nonce($_POST['gps_ticket_nonce'], 'gps_ticket_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $event_id       = (int) ($_POST['gps_event_id'] ?? 0);
        $type           = sanitize_text_field($_POST['gps_ticket_type'] ?? '');
        $price          = floatval($_POST['gps_ticket_price'] ?? 0);
        $quantity       = (int) ($_POST['gps_ticket_quantity'] ?? 0);
        $start_date     = sanitize_text_field($_POST['gps_ticket_start_date'] ?? '');
        $end_date       = sanitize_text_field($_POST['gps_ticket_end_date'] ?? '');
        $product_id     = (int) ($_POST['gps_wc_product_id'] ?? 0);
        $features       = sanitize_textarea_field($_POST['gps_ticket_features'] ?? '');
        $internal_label = sanitize_text_field($_POST['gps_ticket_internal_label'] ?? '');
        $manual_sold_out = isset($_POST['gps_manual_sold_out']) ? '1' : '0';

        update_post_meta($post_id, '_gps_event_id', $event_id);
        update_post_meta($post_id, '_gps_ticket_type', $type);
        update_post_meta($post_id, '_gps_ticket_price', $price);
        update_post_meta($post_id, '_gps_ticket_quantity', $quantity);
        update_post_meta($post_id, '_gps_ticket_start_date', $start_date);
        update_post_meta($post_id, '_gps_ticket_end_date', $end_date);
        update_post_meta($post_id, '_gps_ticket_features', $features);
        update_post_meta($post_id, '_gps_ticket_internal_label', $internal_label);
        update_post_meta($post_id, '_gps_manual_sold_out', $manual_sold_out);

        // Auto-create WooCommerce product if ID is 0 or empty
        if ($product_id === 0 && function_exists('wc_get_product')) {
            $product_id = self::create_woocommerce_product($post_id, $event_id, $type, $price, $quantity);
            update_post_meta($post_id, '_gps_wc_product_id', $product_id);
        } else {
            update_post_meta($post_id, '_gps_wc_product_id', $product_id);
        }

        // Update status based on dates
        self::update_ticket_status($post_id);
    }

    /**
     * Create WooCommerce product for ticket
     */
    public static function create_woocommerce_product($ticket_id, $event_id, $type, $price, $quantity) {
        if (!function_exists('wc_get_product')) {
            return 0;
        }

        $ticket_title = get_the_title($ticket_id);
        $event_title = get_the_title($event_id);

        // Create product
        $product = new \WC_Product_Simple();
        $product->set_name($ticket_title);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_description(sprintf(__('Ticket for %s', 'gps-courses'), $event_title));
        $product->set_regular_price($price);
        $product->set_price($price);

        // Set stock
        if ($quantity > 0) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($quantity);
            $product->set_stock_status('instock');
        } else {
            $product->set_manage_stock(false);
            $product->set_stock_status('instock');
        }

        // Set categories
        $default_cat = get_option('gps_woo_product_category');
        if ($default_cat) {
            $product->set_category_ids([$default_cat]);
        }

        // Save product
        $product_id = $product->save();

        // Link product to event and ticket
        if ($product_id) {
            update_post_meta($product_id, '_gps_event_id', $event_id);
            update_post_meta($product_id, '_gps_ticket_id', $ticket_id);
        }

        return $product_id;
    }

    /**
     * Update ticket status based on availability dates
     */
    public static function update_ticket_status($ticket_id) {
        $start_date = get_post_meta($ticket_id, '_gps_ticket_start_date', true);
        $end_date   = get_post_meta($ticket_id, '_gps_ticket_end_date', true);

        // Convert datetime-local format to MySQL format for comparison
        if ($start_date) {
            $start_date = str_replace('T', ' ', $start_date) . ':00';
        }
        if ($end_date) {
            $end_date = str_replace('T', ' ', $end_date) . ':00';
        }

        $now = current_time('mysql');

        $status = 'inactive';

        if (empty($start_date) && empty($end_date)) {
            // No dates set, always active
            $status = 'active';
        } elseif (!empty($start_date) && empty($end_date)) {
            // Only start date set
            if ($now >= $start_date) {
                $status = 'active';
            }
        } elseif (empty($start_date) && !empty($end_date)) {
            // Only end date set
            if ($now <= $end_date) {
                $status = 'active';
            }
        } else {
            // Both dates set
            if ($now >= $start_date && $now <= $end_date) {
                $status = 'active';
            }
        }

        update_post_meta($ticket_id, '_gps_ticket_status', $status);
        return $status;
    }

    /**
     * Check and update ticket schedules (run hourly)
     */
    public static function check_ticket_schedules() {
        // Only run on admin or front-end requests, not AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Check if we need to run (once per hour)
        $last_check = get_transient('gps_ticket_schedule_check');
        if ($last_check) {
            return;
        }

        // Get all tickets
        $tickets = get_posts([
            'post_type'   => 'gps_ticket',
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);

        foreach ($tickets as $ticket) {
            self::update_ticket_status($ticket->ID);
        }

        // Set transient to prevent running again for 1 hour
        set_transient('gps_ticket_schedule_check', true, HOUR_IN_SECONDS);
    }

    /**
     * Get active tickets for an event
     */
    public static function get_active_tickets($event_id) {
        $tickets = get_posts([
            'post_type'   => 'gps_ticket',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_query'  => [
                'relation' => 'AND',
                [
                    'key'   => '_gps_event_id',
                    'value' => $event_id,
                    'type'  => 'NUMERIC',
                ],
                [
                    'key'   => '_gps_ticket_status',
                    'value' => 'active',
                ],
            ],
            'orderby'     => 'meta_value',
            'meta_key'    => '_gps_ticket_type',
            'order'       => 'ASC',
        ]);

        return $tickets;
    }

    /**
     * Get ticket by type and event
     */
    public static function get_ticket_by_type($event_id, $type) {
        $tickets = get_posts([
            'post_type'   => 'gps_ticket',
            'numberposts' => 1,
            'post_status' => 'publish',
            'meta_query'  => [
                'relation' => 'AND',
                [
                    'key'   => '_gps_event_id',
                    'value' => $event_id,
                    'type'  => 'NUMERIC',
                ],
                [
                    'key'   => '_gps_ticket_type',
                    'value' => $type,
                ],
            ],
        ]);

        return !empty($tickets) ? $tickets[0] : null;
    }

    /**
     * Add custom columns to tickets list
     */
    public static function add_ticket_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;

            // Add internal label after title
            if ($key === 'title') {
                $new_columns['internal_label'] = __('Internal Label', 'gps-courses');
            }
        }

        // Add other columns before date
        $date_column = $new_columns['date'] ?? null;
        unset($new_columns['date']);

        $new_columns['event'] = __('Event', 'gps-courses');
        $new_columns['ticket_type'] = __('Type', 'gps-courses');
        $new_columns['price'] = __('Price', 'gps-courses');
        $new_columns['stock'] = __('Stock', 'gps-courses');
        $new_columns['status'] = __('Status', 'gps-courses');

        if ($date_column) {
            $new_columns['date'] = $date_column;
        }

        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public static function render_ticket_columns($column, $post_id) {
        switch ($column) {
            case 'internal_label':
                $label = get_post_meta($post_id, '_gps_ticket_internal_label', true);
                if (!empty($label)) {
                    echo '<span style="background: #f0f0f1; padding: 3px 8px; border-radius: 3px; font-size: 12px; display: inline-block;">';
                    echo '<strong style="color: #d63638;">🏷</strong> ' . esc_html($label);
                    echo '</span>';
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;

            case 'event':
                $event_id = get_post_meta($post_id, '_gps_event_id', true);
                if ($event_id) {
                    $event = get_post($event_id);
                    if ($event) {
                        echo '<a href="' . get_edit_post_link($event_id) . '">' . esc_html($event->post_title) . '</a>';
                    }
                }
                break;

            case 'ticket_type':
                $type = get_post_meta($post_id, '_gps_ticket_type', true);
                if ($type) {
                    $badge_colors = [
                        'early_bird' => '#46b450',
                        'general'    => '#2271b1',
                        'vip'        => '#d63638',
                        'group'      => '#996800',
                    ];
                    $color = $badge_colors[$type] ?? '#646970';
                    $label = ucwords(str_replace('_', ' ', $type));
                    echo '<span style="background: ' . esc_attr($color) . '; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase;">';
                    echo esc_html($label);
                    echo '</span>';
                }
                break;

            case 'price':
                $price = get_post_meta($post_id, '_gps_ticket_price', true);
                if (function_exists('wc_price')) {
                    echo wc_price($price);
                } else {
                    echo '$' . number_format((float)$price, 2);
                }
                break;

            case 'stock':
                global $wpdb;

                // Check if manually sold out
                $manual_sold_out = get_post_meta($post_id, '_gps_manual_sold_out', true);

                // Get the actual meta value to check if it's set or empty
                $quantity_meta = get_post_meta($post_id, '_gps_ticket_quantity', true);
                $is_unlimited = ($quantity_meta === '' || $quantity_meta === false);
                $total_quantity = (int) $quantity_meta;

                // Count sold tickets from COMPLETED orders only (HPOS compatible)
                $sold = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT t.id)
                    FROM {$wpdb->prefix}gps_tickets t
                    LEFT JOIN {$wpdb->prefix}wc_orders o ON t.order_id = o.id
                    LEFT JOIN {$wpdb->posts} p ON t.order_id = p.ID
                    WHERE t.ticket_type_id = %d AND (o.status = 'wc-completed' OR p.post_status = 'wc-completed')",
                    $post_id
                ));

                // Calculate available based on unlimited flag
                if ($is_unlimited) {
                    $available = '∞';
                    $total_display = '∞';
                } else {
                    $available = max(0, $total_quantity - $sold);
                    $total_display = $total_quantity;
                }

                // Color coding
                $color = '#46b450'; // Green
                if (!$is_unlimited) {
                    if ($available == 0) {
                        $color = '#d63638'; // Red - sold out
                    } else {
                        $percentage = ($available / $total_quantity) * 100;
                        if ($percentage <= 10) {
                            $color = '#d63638'; // Red
                        } elseif ($percentage <= 30) {
                            $color = '#f0b849'; // Orange
                        }
                    }
                }

                echo '<span style="font-weight: 600;">';
                echo '<span style="color: ' . esc_attr($color) . ';">' . esc_html($available) . '</span>';
                echo ' / ' . esc_html($total_display);
                echo '</span>';

                if ($sold > 0) {
                    echo '<br><small style="color: #646970;">(' . $sold . ' ' . __('sold', 'gps-courses') . ')</small>';
                }

                // Show manual sold out indicator
                if ($manual_sold_out) {
                    echo '<br><span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 3px; font-size: 11px; display: inline-block; margin-top: 5px;">';
                    echo '<strong>⚠️ ' . __('Manual Sold Out', 'gps-courses') . '</strong>';
                    echo '</span>';
                }
                break;

            case 'status':
                $status = get_post_meta($post_id, '_gps_ticket_status', true) ?: 'inactive';
                $status_colors = [
                    'active'   => '#46b450',
                    'inactive' => '#999',
                    'expired'  => '#d63638',
                ];
                $color = $status_colors[$status] ?? '#646970';
                echo '<span style="color: ' . esc_attr($color) . '; font-weight: 600;">';
                echo '● ' . esc_html(ucfirst($status));
                echo '</span>';
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public static function sortable_ticket_columns($columns) {
        $columns['event'] = 'event';
        $columns['ticket_type'] = 'ticket_type';
        $columns['price'] = 'price';
        $columns['status'] = 'status';
        return $columns;
    }

    /**
     * Get stock information for a ticket type
     * Returns array with total, sold, and available counts
     */
    public static function get_ticket_stock($ticket_id) {
        global $wpdb;

        $total_meta = get_post_meta($ticket_id, '_gps_ticket_quantity', true);

        // Empty/not set = unlimited, "0" = sold out/disabled
        $is_unlimited = ($total_meta === '' || $total_meta === false);
        $total = (int) $total_meta;

        // Count sold tickets from COMPLETED orders only (HPOS compatible)
        $sold = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT t.id)
            FROM {$wpdb->prefix}gps_tickets t
            LEFT JOIN {$wpdb->prefix}wc_orders o ON t.order_id = o.id
            LEFT JOIN {$wpdb->posts} p ON t.order_id = p.ID
            WHERE t.ticket_type_id = %d AND (o.status = 'wc-completed' OR p.post_status = 'wc-completed')",
            $ticket_id
        ));

        // Calculate available
        if ($is_unlimited) {
            $available = 999999; // Unlimited
        } else {
            $available = max(0, $total - (int) $sold);
        }

        return [
            'total' => $total,
            'sold' => (int) $sold,
            'available' => $available,
            'unlimited' => $is_unlimited,
        ];
    }

    /**
     * Get combined stock for all ticket types of an event
     * This is used to sync WooCommerce product stock
     */
    public static function get_event_total_stock($event_id) {
        global $wpdb;

        // Get all ticket types for this event
        $tickets = get_posts([
            'post_type' => 'gps_ticket',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => '_gps_event_id',
                    'value' => $event_id,
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        $total_available = 0;
        $has_unlimited = false;

        foreach ($tickets as $ticket) {
            $stock = self::get_ticket_stock($ticket->ID);

            if ($stock['unlimited']) {
                $has_unlimited = true;
                break;
            }

            $total_available += $stock['available'];
        }

        return [
            'total_available' => $has_unlimited ? 999999 : $total_available,
            'unlimited' => $has_unlimited,
        ];
    }

    /**
     * Check if a ticket is sold out (either manually or by stock)
     *
     * @param int $ticket_id The ticket post ID
     * @return bool True if sold out, false otherwise
     */
    public static function is_sold_out($ticket_id) {
        // Check manual override first
        $manual_sold_out = get_post_meta($ticket_id, '_gps_manual_sold_out', true);
        if ($manual_sold_out) {
            return true;
        }

        // Check actual stock
        $stock = self::get_ticket_stock($ticket_id);
        return !$stock['unlimited'] && $stock['available'] == 0;
    }

    /**
     * Check if ticket is manually marked as sold out
     *
     * @param int $ticket_id The ticket post ID
     * @return bool True if manually sold out
     */
    public static function is_manually_sold_out($ticket_id) {
        return (bool) get_post_meta($ticket_id, '_gps_manual_sold_out', true);
    }
}
