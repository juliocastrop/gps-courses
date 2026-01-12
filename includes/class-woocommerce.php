<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * WooCommerce Integration
 * Handles order completion, ticket generation, and enrollment
 */
class Woo {

    // Admin emails for GPS order notifications
    const ADMIN_NOTIFICATION_EMAILS = [
        'info@gpsdentaltraining.com',
        'juliocastro@thewebminds.agency'
    ];

    public static function hooks() {
        // Order completion - create tickets ONLY when order is completed
        add_action('woocommerce_order_status_completed', [__CLASS__, 'on_order_completed'], 10, 1);

        // Auto-complete orders with GPS products when payment is confirmed
        add_action('woocommerce_payment_complete', [__CLASS__, 'auto_complete_gps_orders'], 10, 1);

        // Track ALL order status changes for GPS products
        add_action('woocommerce_order_status_changed', [__CLASS__, 'track_order_status_change'], 10, 4);

        // Add ticket meta to order items
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_ticket_meta_to_order_item'], 10, 4);

        // Display ticket info in admin order
        add_action('woocommerce_admin_order_item_headers', [__CLASS__, 'admin_order_item_headers']);
        add_action('woocommerce_admin_order_item_values', [__CLASS__, 'admin_order_item_values'], 10, 3);

        // Add custom order meta box
        add_action('add_meta_boxes', [__CLASS__, 'add_order_metabox']);

        // Handle manual reprocess
        add_action('admin_init', [__CLASS__, 'handle_reprocess_order']);
        add_action('admin_notices', [__CLASS__, 'reprocess_admin_notices']);
        add_action('admin_notices', [__CLASS__, 'test_email_admin_notices']);

        // Add test email button to orders page (legacy and HPOS)
        add_action('manage_posts_extra_tablenav', [__CLASS__, 'add_test_email_button']);
        add_action('woocommerce_order_list_table_extra_tablenav', [__CLASS__, 'add_test_email_button_hpos']);
        add_action('admin_footer', [__CLASS__, 'add_test_email_button_js']);

        // Sync ticket stock with WooCommerce products
        add_action('save_post_gps_ticket', [__CLASS__, 'sync_product_stock'], 20, 1);

        // Sync product stock after tickets are created
        add_action('gps_ticket_created', [__CLASS__, 'sync_product_stock_after_purchase'], 10, 2);

        // My Account tabs
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'add_account_menu_items']);
        add_action('init', [__CLASS__, 'add_account_endpoints']);
        add_action('woocommerce_account_gps-courses_endpoint', [__CLASS__, 'my_courses_content']);
        add_action('woocommerce_account_gps-seminars_endpoint', [__CLASS__, 'my_seminars_content']);
        add_action('woocommerce_account_gps-ce-credits_endpoint', [__CLASS__, 'ce_credits_content']);
        add_action('woocommerce_account_gps-tickets_endpoint', [__CLASS__, 'my_tickets_content']);
        add_action('woocommerce_account_gps-attendance_endpoint', [__CLASS__, 'attendance_history_content']);

        // Flush rewrite rules on activation
        add_action('after_switch_theme', 'flush_rewrite_rules');

        // Test email endpoint (for admins only)
        add_action('admin_init', [__CLASS__, 'handle_test_email']);

        // Cache invalidation hooks
        add_action('gps_enrollment_created', [__CLASS__, 'clear_user_cache'], 10, 2);
        add_action('gps_seminar_registered', [__CLASS__, 'clear_user_cache'], 10, 2);
        add_action('gps_ticket_created', [__CLASS__, 'clear_user_cache'], 10, 2);
        add_action('gps_attendance_recorded', [__CLASS__, 'clear_user_cache'], 10, 2);
        add_action('gps_credits_awarded', [__CLASS__, 'clear_user_cache'], 10, 2);

        // Link guest orders when user creates account or logs in
        add_action('user_register', [__CLASS__, 'link_guest_orders_on_register'], 10, 1);
        add_action('wp_login', [__CLASS__, 'link_guest_orders_on_login'], 10, 2);

        // Admin tool to link guest orders
        add_action('admin_init', [__CLASS__, 'handle_link_guest_orders']);

        // GPS Orders diagnostic page
        add_action('admin_menu', [__CLASS__, 'add_diagnostic_menu']);

        // Redirect product links to event/course page in cart and checkout
        add_filter('woocommerce_cart_item_permalink', [__CLASS__, 'change_cart_item_permalink'], 10, 3);
        add_filter('woocommerce_order_item_permalink', [__CLASS__, 'change_order_item_permalink'], 10, 3);
    }

    /**
     * Change cart item permalink to point to the event/course page instead of product page
     */
    public static function change_cart_item_permalink($permalink, $cart_item, $cart_item_key) {
        $product_id = $cart_item['product_id'];

        // Check if this product is linked to a GPS ticket type
        $ticket_type_id = self::get_ticket_type_for_product($product_id);

        if ($ticket_type_id) {
            // Get the event ID from the ticket type
            $event_id = get_post_meta($ticket_type_id, '_gps_event_id', true);

            if ($event_id) {
                $event_permalink = get_permalink($event_id);
                if ($event_permalink) {
                    return $event_permalink;
                }
            }
        }

        // Check if this product is linked to a GPS seminar
        $seminar_id = get_post_meta($product_id, '_gps_seminar_id', true);
        if ($seminar_id) {
            $seminar_permalink = get_permalink($seminar_id);
            if ($seminar_permalink) {
                return $seminar_permalink;
            }
        }

        return $permalink;
    }

    /**
     * Change order item permalink to point to the event/course page instead of product page
     */
    public static function change_order_item_permalink($permalink, $item, $order) {
        $product_id = $item->get_product_id();

        // Check if this product is linked to a GPS ticket type
        $ticket_type_id = self::get_ticket_type_for_product($product_id);

        if ($ticket_type_id) {
            // Get the event ID from the ticket type
            $event_id = get_post_meta($ticket_type_id, '_gps_event_id', true);

            if ($event_id) {
                $event_permalink = get_permalink($event_id);
                if ($event_permalink) {
                    return $event_permalink;
                }
            }
        }

        // Check if this product is linked to a GPS seminar
        $seminar_id = get_post_meta($product_id, '_gps_seminar_id', true);
        if ($seminar_id) {
            $seminar_permalink = get_permalink($seminar_id);
            if ($seminar_permalink) {
                return $seminar_permalink;
            }
        }

        return $permalink;
    }

    /**
     * Clear user's cached My Account content
     */
    public static function clear_user_cache($id, $user_id) {
        // Clear all My Account page caches for this user
        delete_transient('gps_my_courses_' . $user_id);
        delete_transient('gps_my_seminars_' . $user_id);
        delete_transient('gps_ce_credits_' . $user_id);
        delete_transient('gps_my_tickets_' . $user_id);
        delete_transient('gps_attendance_history_' . $user_id);
    }

    /**
     * Link guest orders to user account when they register
     */
    public static function link_guest_orders_on_register($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return;
        }

        self::link_guest_orders_by_email($user->user_email, $user_id);
    }

    /**
     * Link guest orders to user account when they log in
     */
    public static function link_guest_orders_on_login($user_login, $user) {
        if (!$user || !$user->user_email) {
            return;
        }

        self::link_guest_orders_by_email($user->user_email, $user->ID);
    }

    /**
     * Link all guest orders with matching email to a user account
     * Updates: WooCommerce orders, GPS tickets, GPS enrollments
     */
    public static function link_guest_orders_by_email($email, $user_id) {
        global $wpdb;

        if (!$email || !$user_id) {
            return ['linked' => 0, 'errors' => ['Invalid email or user ID']];
        }

        $email = sanitize_email($email);
        $user_id = (int) $user_id;
        $linked_count = 0;
        $errors = [];

        error_log("GPS Courses: Attempting to link guest orders for email: {$email} to user #{$user_id}");

        // Find WooCommerce orders with this email that have no user assigned
        $orders = wc_get_orders([
            'billing_email' => $email,
            'customer_id' => 0,
            'limit' => -1,
            'status' => ['completed', 'processing', 'on-hold'],
        ]);

        if (empty($orders)) {
            error_log("GPS Courses: No guest orders found for email: {$email}");
            return ['linked' => 0, 'errors' => []];
        }

        error_log("GPS Courses: Found " . count($orders) . " guest order(s) for email: {$email}");

        foreach ($orders as $order) {
            $order_id = $order->get_id();

            try {
                // 1. Update WooCommerce order to assign user
                $order->set_customer_id($user_id);
                $order->save();
                error_log("GPS Courses: Linked order #{$order_id} to user #{$user_id}");

                // 2. Update GPS tickets for this order
                $tickets_updated = $wpdb->update(
                    $wpdb->prefix . 'gps_tickets',
                    ['user_id' => $user_id],
                    ['order_id' => $order_id, 'user_id' => 0],
                    ['%d'],
                    ['%d', '%d']
                );

                if ($tickets_updated !== false) {
                    error_log("GPS Courses: Updated {$tickets_updated} ticket(s) for order #{$order_id}");
                }

                // 3. Update GPS enrollments for this order
                $enrollments_updated = $wpdb->update(
                    $wpdb->prefix . 'gps_enrollments',
                    ['user_id' => $user_id],
                    ['order_id' => $order_id, 'user_id' => 0],
                    ['%d'],
                    ['%d', '%d']
                );

                if ($enrollments_updated !== false) {
                    error_log("GPS Courses: Updated {$enrollments_updated} enrollment(s) for order #{$order_id}");
                }

                // 4. Update attendee email in tickets table if blank
                $wpdb->update(
                    $wpdb->prefix . 'gps_tickets',
                    ['attendee_email' => $email],
                    ['order_id' => $order_id, 'attendee_email' => ''],
                    ['%s'],
                    ['%d', '%s']
                );

                $linked_count++;

            } catch (\Exception $e) {
                $errors[] = "Order #{$order_id}: " . $e->getMessage();
                error_log("GPS Courses: Error linking order #{$order_id}: " . $e->getMessage());
            }
        }

        // Clear user cache so they see updated data
        if ($linked_count > 0) {
            self::clear_user_cache(0, $user_id);
        }

        error_log("GPS Courses: Successfully linked {$linked_count} order(s) for email: {$email}");

        return [
            'linked' => $linked_count,
            'errors' => $errors
        ];
    }

    /**
     * Handle admin request to link guest orders
     */
    public static function handle_link_guest_orders() {
        if (!isset($_GET['gps_link_guest_orders']) || $_GET['gps_link_guest_orders'] !== '1') {
            return;
        }

        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gps_link_guest_orders')) {
            wp_die('Invalid nonce');
        }

        $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
        $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

        // If no user_id provided, try to find user by email
        if (!$user_id && $email) {
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        if (!$email || !$user_id) {
            wp_redirect(add_query_arg('gps_link_error', urlencode('Email and user ID are required'), admin_url('users.php')));
            exit;
        }

        $result = self::link_guest_orders_by_email($email, $user_id);

        $redirect_url = admin_url('edit.php?post_type=shop_order');
        if (isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
            $redirect_url = admin_url('admin.php?page=wc-orders');
        }

        if ($result['linked'] > 0) {
            $redirect_url = add_query_arg('gps_linked_orders', $result['linked'], $redirect_url);
        } else {
            $redirect_url = add_query_arg('gps_link_error', urlencode('No guest orders found for this email'), $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Add diagnostic menu under GPS Courses
     */
    public static function add_diagnostic_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Orders Diagnostic', 'gps-courses'),
            __('🔍 Orders Diagnostic', 'gps-courses'),
            'manage_options',
            'gps-orders-diagnostic',
            [__CLASS__, 'render_diagnostic_page']
        );
    }

    /**
     * Render the diagnostic page
     */
    public static function render_diagnostic_page() {
        global $wpdb;

        // Handle fix action
        if (isset($_POST['gps_fix_order']) && isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'gps_fix_order')) {
                $order_id = (int) $_POST['gps_fix_order'];
                $order = wc_get_order($order_id);

                if ($order) {
                    // Reprocess the order
                    $order->delete_meta_data('_gps_tickets_created');
                    $order->save();
                    self::create_tickets_for_order($order);
                    $order->update_meta_data('_gps_tickets_created', current_time('mysql'));
                    $order->save();

                    echo '<div class="notice notice-success"><p>Order #' . $order_id . ' has been reprocessed.</p></div>';
                }
            }
        }

        // Handle link user action
        if (isset($_POST['gps_link_user']) && isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'gps_link_user')) {
                $email = sanitize_email($_POST['gps_link_email']);
                $user = get_user_by('email', $email);

                if ($user) {
                    $result = self::link_guest_orders_by_email($email, $user->ID);
                    if ($result['linked'] > 0) {
                        echo '<div class="notice notice-success"><p>Linked ' . $result['linked'] . ' order(s) to user account.</p></div>';
                    } else {
                        echo '<div class="notice notice-warning"><p>No guest orders found for this email.</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>No user found with email: ' . esc_html($email) . '</p></div>';
                }
            }
        }

        // Handle sync tickets/enrollments to order user
        if (isset($_POST['gps_sync_user']) && isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'gps_sync_user')) {
                $order_id = (int) $_POST['gps_sync_order_id'];
                $order = wc_get_order($order_id);

                if ($order && $order->get_customer_id() > 0) {
                    $user_id = $order->get_customer_id();

                    // Update tickets
                    $tickets_updated = $wpdb->update(
                        $wpdb->prefix . 'gps_tickets',
                        ['user_id' => $user_id],
                        ['order_id' => $order_id, 'user_id' => 0],
                        ['%d'],
                        ['%d', '%d']
                    );

                    // Update enrollments
                    $enrollments_updated = $wpdb->update(
                        $wpdb->prefix . 'gps_enrollments',
                        ['user_id' => $user_id],
                        ['order_id' => $order_id, 'user_id' => 0],
                        ['%d'],
                        ['%d', '%d']
                    );

                    // Clear cache
                    self::clear_user_cache(0, $user_id);

                    echo '<div class="notice notice-success"><p>Order #' . $order_id . ': Updated ' . (int)$tickets_updated . ' ticket(s) and ' . (int)$enrollments_updated . ' enrollment(s) to User #' . $user_id . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Order has no user assigned. Link user first.</p></div>';
                }
            }
        }

        // Handle fix all guest records
        if (isset($_POST['gps_fix_all_guest']) && isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'gps_fix_all_guest')) {
                $fixed_count = 0;

                // Get all orders with GPS products that have user assigned
                $all_orders = wc_get_orders([
                    'limit' => -1,
                    'status' => ['completed', 'processing'],
                ]);

                foreach ($all_orders as $order) {
                    $user_id = $order->get_customer_id();
                    if ($user_id <= 0) continue;

                    $order_id = $order->get_id();

                    // Check if order has guest tickets/enrollments
                    $guest_tickets = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}gps_tickets WHERE order_id = %d AND user_id = 0",
                        $order_id
                    ));

                    $guest_enrollments = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}gps_enrollments WHERE order_id = %d AND user_id = 0",
                        $order_id
                    ));

                    if ($guest_tickets > 0 || $guest_enrollments > 0) {
                        // Update tickets
                        $wpdb->update(
                            $wpdb->prefix . 'gps_tickets',
                            ['user_id' => $user_id],
                            ['order_id' => $order_id, 'user_id' => 0],
                            ['%d'],
                            ['%d', '%d']
                        );

                        // Update enrollments
                        $wpdb->update(
                            $wpdb->prefix . 'gps_enrollments',
                            ['user_id' => $user_id],
                            ['order_id' => $order_id, 'user_id' => 0],
                            ['%d'],
                            ['%d', '%d']
                        );

                        // Clear cache
                        self::clear_user_cache(0, $user_id);
                        $fixed_count++;
                    }
                }

                echo '<div class="notice notice-success"><p>Fixed ' . $fixed_count . ' order(s) with guest tickets/enrollments.</p></div>';
            }
        }

        // Get GPS orders (orders containing GPS products)
        $orders = wc_get_orders([
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['completed', 'processing', 'on-hold', 'pending'],
        ]);

        $gps_orders = [];

        foreach ($orders as $order) {
            $has_gps_product = false;
            $gps_items = [];

            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();

                // Check if GPS ticket product
                $ticket_type_id = self::get_ticket_type_for_product($product_id);
                if ($ticket_type_id) {
                    $has_gps_product = true;
                    $gps_items[] = [
                        'name' => $item->get_name(),
                        'type' => 'Course Ticket',
                        'ticket_type_id' => $ticket_type_id,
                    ];
                }

                // Check if GPS seminar product
                $seminar_id = get_post_meta($product_id, '_gps_seminar_id', true);
                if ($seminar_id) {
                    $has_gps_product = true;
                    $gps_items[] = [
                        'name' => $item->get_name(),
                        'type' => 'Monthly Seminar',
                        'seminar_id' => $seminar_id,
                    ];
                }
            }

            if ($has_gps_product) {
                $order_id = $order->get_id();
                $user_id = $order->get_customer_id();

                // Get tickets for this order
                $tickets = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE order_id = %d",
                    $order_id
                ));

                // Get enrollments for this order
                $enrollments = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}gps_enrollments WHERE order_id = %d",
                    $order_id
                ));

                $gps_orders[] = [
                    'order' => $order,
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'email' => $order->get_billing_email(),
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'status' => $order->get_status(),
                    'date' => $order->get_date_created()->date_i18n('Y-m-d H:i'),
                    'gps_items' => $gps_items,
                    'tickets' => $tickets,
                    'enrollments' => $enrollments,
                    'tickets_created' => $order->get_meta('_gps_tickets_created'),
                ];
            }
        }

        ?>
        <div class="wrap">
            <h1>🔍 GPS Orders Diagnostic</h1>
            <p>This page shows all orders containing GPS products and their status.</p>

            <style>
                .gps-diagnostic-table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                .gps-diagnostic-table th, .gps-diagnostic-table td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
                .gps-diagnostic-table th { background: #f5f5f5; }
                .status-ok { color: #46b450; font-weight: bold; }
                .status-warning { color: #f0ad4e; font-weight: bold; }
                .status-error { color: #dc3232; font-weight: bold; }
                .gps-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; margin: 2px; }
                .gps-badge-guest { background: #f0ad4e; color: #fff; }
                .gps-badge-user { background: #46b450; color: #fff; }
                .gps-badge-ticket { background: #0073aa; color: #fff; }
                .gps-badge-enrollment { background: #826eb4; color: #fff; }
                .gps-badge-missing { background: #dc3232; color: #fff; }
                .gps-items-list { margin: 0; padding-left: 20px; }
                .gps-fix-form { display: inline; }
            </style>

            <h2>Summary</h2>
            <table class="gps-diagnostic-table" style="max-width: 400px;">
                <tr>
                    <td>Total GPS Orders (last 50)</td>
                    <td><strong><?php echo count($gps_orders); ?></strong></td>
                </tr>
                <tr>
                    <td>Guest Orders (no user linked)</td>
                    <td><strong><?php echo count(array_filter($gps_orders, function($o) { return $o['user_id'] == 0; })); ?></strong></td>
                </tr>
                <tr>
                    <td>Orders Missing Tickets</td>
                    <td><strong><?php echo count(array_filter($gps_orders, function($o) { return empty($o['tickets']) && $o['status'] === 'completed'; })); ?></strong></td>
                </tr>
                <tr>
                    <td>Orders Missing Enrollments</td>
                    <td><strong><?php echo count(array_filter($gps_orders, function($o) { return empty($o['enrollments']) && $o['status'] === 'completed'; })); ?></strong></td>
                </tr>
                <tr>
                    <td>Tickets/Enrollments with guest user_id</td>
                    <td><strong><?php
                        $guest_records = count(array_filter($gps_orders, function($o) {
                            if ($o['user_id'] == 0) return false;
                            foreach ($o['tickets'] as $t) { if ($t->user_id == 0) return true; }
                            foreach ($o['enrollments'] as $e) { if ($e->user_id == 0) return true; }
                            return false;
                        }));
                        echo $guest_records;
                    ?></strong></td>
                </tr>
            </table>

            <?php if ($guest_records > 0): ?>
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h3 style="margin-top: 0;">⚠️ Found orders with guest tickets/enrollments</h3>
                <p>These orders have a user assigned, but their tickets/enrollments still have user_id = 0 (guest). Click the button below to fix all at once:</p>
                <form method="post">
                    <?php wp_nonce_field('gps_fix_all_guest'); ?>
                    <button type="submit" name="gps_fix_all_guest" value="1" class="button button-primary button-hero">
                        🔧 Fix All Guest Tickets/Enrollments
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <h2>Orders Detail</h2>
            <table class="gps-diagnostic-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>GPS Products</th>
                        <th>Tickets</th>
                        <th>Enrollments</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gps_orders as $data): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wc-orders&action=edit&id=' . $data['order_id']); ?>" target="_blank">
                                <strong>#<?php echo $data['order_id']; ?></strong>
                            </a><br>
                            <small><?php echo $data['date']; ?></small><br>
                            <span class="gps-badge <?php echo $data['status'] === 'completed' ? 'gps-badge-user' : 'gps-badge-guest'; ?>">
                                <?php echo ucfirst($data['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($data['customer_name']); ?><br>
                            <small><?php echo esc_html($data['email']); ?></small><br>
                            <?php if ($data['user_id'] > 0): ?>
                                <span class="gps-badge gps-badge-user">User #<?php echo $data['user_id']; ?></span>
                            <?php else: ?>
                                <span class="gps-badge gps-badge-guest">⚠️ Guest</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <ul class="gps-items-list">
                                <?php foreach ($data['gps_items'] as $item): ?>
                                <li><?php echo esc_html($item['name']); ?> <small>(<?php echo $item['type']; ?>)</small></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td>
                            <?php if (!empty($data['tickets'])): ?>
                                <?php foreach ($data['tickets'] as $ticket): ?>
                                    <span class="gps-badge gps-badge-ticket">
                                        <?php echo esc_html($ticket->ticket_code); ?>
                                        <?php if ($ticket->user_id == 0): ?> (guest)<?php endif; ?>
                                    </span><br>
                                <?php endforeach; ?>
                            <?php elseif ($data['status'] === 'completed'): ?>
                                <span class="gps-badge gps-badge-missing">❌ Missing</span>
                            <?php else: ?>
                                <small>Pending order completion</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($data['enrollments'])): ?>
                                <?php foreach ($data['enrollments'] as $enrollment): ?>
                                    <span class="gps-badge gps-badge-enrollment">
                                        ID: <?php echo $enrollment->id; ?>
                                        <?php if ($enrollment->user_id == 0): ?> (guest)<?php endif; ?>
                                    </span><br>
                                <?php endforeach; ?>
                            <?php elseif ($data['status'] === 'completed'): ?>
                                <span class="gps-badge gps-badge-missing">❌ Missing</span>
                            <?php else: ?>
                                <small>Pending order completion</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $issues = [];
                            $has_guest_records = false;
                            if ($data['user_id'] == 0) $issues[] = 'Guest order';
                            if (empty($data['tickets']) && $data['status'] === 'completed') $issues[] = 'No tickets';
                            if (empty($data['enrollments']) && $data['status'] === 'completed') $issues[] = 'No enrollments';

                            // Check for guest user_id in tickets/enrollments when order has user
                            if ($data['user_id'] > 0) {
                                foreach ($data['tickets'] as $t) {
                                    if ($t->user_id == 0) { $has_guest_records = true; break; }
                                }
                                if (!$has_guest_records) {
                                    foreach ($data['enrollments'] as $e) {
                                        if ($e->user_id == 0) { $has_guest_records = true; break; }
                                    }
                                }
                                if ($has_guest_records) $issues[] = 'Guest user_id in records';
                            }

                            if (empty($issues)): ?>
                                <span class="status-ok">✅ OK</span>
                            <?php else: ?>
                                <span class="status-error">⚠️ Issues:</span>
                                <ul style="margin: 5px 0; padding-left: 15px;">
                                    <?php foreach ($issues as $issue): ?>
                                        <li><small><?php echo $issue; ?></small></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($data['user_id'] == 0): ?>
                                <form method="post" class="gps-fix-form">
                                    <?php wp_nonce_field('gps_link_user'); ?>
                                    <input type="hidden" name="gps_link_email" value="<?php echo esc_attr($data['email']); ?>">
                                    <button type="submit" name="gps_link_user" value="1" class="button button-small">
                                        🔗 Link User
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ((empty($data['tickets']) || empty($data['enrollments'])) && $data['status'] === 'completed'): ?>
                                <form method="post" class="gps-fix-form" style="margin-top: 5px;">
                                    <?php wp_nonce_field('gps_fix_order'); ?>
                                    <button type="submit" name="gps_fix_order" value="<?php echo $data['order_id']; ?>" class="button button-small button-primary">
                                        🔧 Reprocess
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($has_guest_records && $data['user_id'] > 0): ?>
                                <form method="post" class="gps-fix-form" style="margin-top: 5px;">
                                    <?php wp_nonce_field('gps_sync_user'); ?>
                                    <input type="hidden" name="gps_sync_order_id" value="<?php echo $data['order_id']; ?>">
                                    <button type="submit" name="gps_sync_user" value="1" class="button button-small" style="background: #f0ad4e; border-color: #eea236; color: #fff;">
                                        🔄 Sync User ID
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Legend</h2>
            <p>
                <span class="gps-badge gps-badge-user">User #X</span> Order linked to registered user<br>
                <span class="gps-badge gps-badge-guest">Guest</span> Order not linked to any user account<br>
                <span class="gps-badge gps-badge-ticket">Ticket</span> Ticket created for order<br>
                <span class="gps-badge gps-badge-enrollment">Enrollment</span> Course enrollment created<br>
                <span class="gps-badge gps-badge-missing">Missing</span> Expected record not found
            </p>
        </div>
        <?php
    }

    /**
     * Handle order completion
     */
    public static function on_order_completed($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if tickets already created (HPOS compatible)
        if ($order->get_meta('_gps_tickets_created')) {
            return;
        }

        self::create_tickets_for_order($order);

        // Mark as processed (HPOS compatible)
        $order->update_meta_data('_gps_tickets_created', current_time('mysql'));
        $order->save();

        // Clear user cache after order completion
        $user_id = $order->get_user_id();
        if ($user_id) {
            self::clear_user_cache(0, $user_id);
        }
    }

    /**
     * Auto-complete orders containing GPS products when payment is confirmed
     * This ensures tickets are created immediately after successful payment
     */
    public static function auto_complete_gps_orders($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Skip if order is already completed
        if ($order->get_status() === 'completed') {
            return;
        }

        // Check if order contains GPS products (tickets or seminars)
        $has_gps_products = false;
        $has_physical_products = false;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = $item->get_product();

            // Check if this is a GPS ticket product
            $ticket_type_id = self::get_ticket_type_for_product($product_id);
            if ($ticket_type_id) {
                $has_gps_products = true;
                continue;
            }

            // Check if this is a GPS seminar product
            $seminar_id = get_post_meta($product_id, '_gps_seminar_id', true);
            if ($seminar_id) {
                $has_gps_products = true;
                continue;
            }

            // Check if product is virtual/downloadable (not physical)
            if ($product && !$product->is_virtual() && !$product->is_downloadable()) {
                $has_physical_products = true;
            }
        }

        // Auto-complete if order has GPS products and no physical products
        if ($has_gps_products && !$has_physical_products) {
            error_log('GPS Courses: Auto-completing order #' . $order_id . ' (GPS products only)');
            $order->update_status('completed', __('Order auto-completed - GPS digital products.', 'gps-courses'));
        }
    }

    /**
     * Handle test email request (admin only)
     */
    public static function handle_test_email() {
        if (!isset($_GET['gps_test_email']) || $_GET['gps_test_email'] !== '1') {
            return;
        }

        // Security check - admin only
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gps_test_email')) {
            wp_die('Invalid nonce');
        }

        // Send test email
        $current_user = wp_get_current_user();
        $subject = '[GPS Test] Order Notification System Test';

        $message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #0B52AC; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .success { background: #dff0d8; padding: 15px; border-left: 4px solid #5cb85c; margin: 15px 0; border-radius: 4px; }
        .info { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>✅ Email Test Successful</h1>
    </div>
    <div class='content'>
        <div class='success'>
            <strong>Great news!</strong> Your GPS order notification system is working correctly.
        </div>

        <div class='info'>
            <h3>Test Details</h3>
            <table style='width: 100%;'>
                <tr><td><strong>Triggered by:</strong></td><td>{$current_user->display_name} ({$current_user->user_email})</td></tr>
                <tr><td><strong>Timestamp:</strong></td><td>" . current_time('mysql') . "</td></tr>
                <tr><td><strong>Recipients:</strong></td><td>" . implode(', ', self::ADMIN_NOTIFICATION_EMAILS) . "</td></tr>
            </table>
        </div>

        <h3>What This Means</h3>
        <p>When GPS orders change status, you will receive email notifications like this one. The notifications include:</p>
        <ul>
            <li>Visual status change indicators (from → to)</li>
            <li>Customer details and order information</li>
            <li>GPS product list (courses, seminars)</li>
            <li>Direct link to view the order in admin</li>
        </ul>

        <p style='margin-top: 20px; color: #666;'>
            <em>This is a test email. No actual order was created.</em>
        </p>
    </div>
    <div class='footer'>
        <p>GPS Dental Training - Order Tracking System</p>
    </div>
</body>
</html>";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: GPS Dental Training <noreply@gpsdentaltraining.com>',
        ];

        // Send to all admin emails
        $emails = implode(', ', self::ADMIN_NOTIFICATION_EMAILS);
        $sent = wp_mail($emails, $subject, $message, $headers);

        // Redirect with result
        $redirect_url = admin_url('edit.php?post_type=shop_order');
        if ($sent) {
            $redirect_url = add_query_arg('gps_test_email_sent', '1', $redirect_url);
        } else {
            $redirect_url = add_query_arg('gps_test_email_failed', '1', $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Track ALL order status changes and send email notifications
     */
    public static function track_order_status_change($order_id, $old_status, $new_status, $order) {
        // Check if order contains GPS products
        $gps_products = [];
        $has_gps_products = false;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();

            // Check if this is a GPS ticket product
            $ticket_type_id = self::get_ticket_type_for_product($product_id);
            if ($ticket_type_id) {
                $has_gps_products = true;
                $gps_products[] = $quantity . 'x ' . $product_name . ' (Course Ticket)';
                continue;
            }

            // Check if this is a GPS seminar product
            $seminar_id = get_post_meta($product_id, '_gps_seminar_id', true);
            if ($seminar_id) {
                $has_gps_products = true;
                $gps_products[] = $quantity . 'x ' . $product_name . ' (Monthly Seminar)';
                continue;
            }
        }

        // Only track orders with GPS products
        if (!$has_gps_products) {
            return;
        }

        // Log status change
        error_log("GPS Courses: Order #{$order_id} status changed from '{$old_status}' to '{$new_status}'");

        // Define status colors and labels
        $status_config = [
            'pending'    => ['color' => '#777777', 'label' => 'Pending Payment', 'icon' => '⏳'],
            'processing' => ['color' => '#F0AD4E', 'label' => 'Processing', 'icon' => '🔄'],
            'on-hold'    => ['color' => '#F0AD4E', 'label' => 'On Hold', 'icon' => '⏸️'],
            'completed'  => ['color' => '#5CB85C', 'label' => 'Completed', 'icon' => '✅'],
            'cancelled'  => ['color' => '#D9534F', 'label' => 'Cancelled', 'icon' => '❌'],
            'refunded'   => ['color' => '#D9534F', 'label' => 'Refunded', 'icon' => '💸'],
            'failed'     => ['color' => '#D9534F', 'label' => 'Failed', 'icon' => '⚠️'],
        ];

        $old_config = $status_config[$old_status] ?? ['color' => '#777777', 'label' => ucfirst($old_status), 'icon' => '•'];
        $new_config = $status_config[$new_status] ?? ['color' => '#777777', 'label' => ucfirst($new_status), 'icon' => '•'];

        // Build email content
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_email = $order->get_billing_email();
        $order_total = $order->get_formatted_order_total();
        $payment_method = $order->get_payment_method_title();
        $order_date = $order->get_date_created() ? $order->get_date_created()->date_i18n('F j, Y \a\t g:i A') : 'N/A';
        $order_url = admin_url('post.php?post=' . $order_id . '&action=edit');

        $subject = "[GPS Order #{$order_id}] {$new_config['icon']} Status: {$old_config['label']} → {$new_config['label']}";

        $tickets_created = $order->get_meta('_gps_tickets_created') ? 'Yes ✓' : 'Not yet';

        $message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: {$new_config['color']}; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .status-change { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 15px 0; text-align: center; }
        .status-box { display: inline-block; padding: 10px 20px; border-radius: 5px; margin: 5px; font-weight: bold; }
        .old-status { background: {$old_config['color']}; color: white; }
        .new-status { background: {$new_config['color']}; color: white; }
        .arrow { font-size: 24px; margin: 0 10px; }
        .order-details { background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .products { background: #fff; border: 1px solid #ddd; padding: 15px; margin: 15px 0; }
        .product-item { padding: 8px 0; border-bottom: 1px solid #eee; }
        .product-item:last-child { border-bottom: none; }
        .button { display: inline-block; background: #0B52AC; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
        .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
        .highlight { background: #fffbcc; padding: 10px; border-left: 4px solid #f0ad4e; margin: 15px 0; }
        .success { background: #dff0d8; padding: 10px; border-left: 4px solid #5cb85c; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>{$new_config['icon']} Order Status Update</h1>
        <p>Order #{$order_id}</p>
    </div>
    <div class='content'>
        <div class='status-change'>
            <span class='status-box old-status'>{$old_config['label']}</span>
            <span class='arrow'>→</span>
            <span class='status-box new-status'>{$new_config['label']}</span>
        </div>";

        // Add contextual message based on status
        if ($new_status === 'completed') {
            $message .= "<div class='success'><strong>✅ Order Completed!</strong> Tickets/registrations should now be created automatically.</div>";
        } elseif ($new_status === 'processing') {
            $message .= "<div class='highlight'><strong>🔄 Payment Received!</strong> Order is being processed. If auto-complete is enabled, it should complete shortly.</div>";
        } elseif ($new_status === 'failed') {
            $message .= "<div class='highlight' style='border-color: #d9534f; background: #f2dede;'><strong>⚠️ Payment Failed!</strong> Please check the payment gateway for details.</div>";
        } elseif ($new_status === 'cancelled') {
            $message .= "<div class='highlight' style='border-color: #d9534f; background: #f2dede;'><strong>❌ Order Cancelled!</strong> No tickets will be created.</div>";
        }

        $message .= "
        <div class='order-details'>
            <h3>Order Details</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 8px 0;'><strong>Customer:</strong></td><td>{$customer_name}</td></tr>
                <tr><td style='padding: 8px 0;'><strong>Email:</strong></td><td>{$customer_email}</td></tr>
                <tr><td style='padding: 8px 0;'><strong>Order Date:</strong></td><td>{$order_date}</td></tr>
                <tr><td style='padding: 8px 0;'><strong>Payment Method:</strong></td><td>{$payment_method}</td></tr>
                <tr><td style='padding: 8px 0;'><strong>Order Total:</strong></td><td>{$order_total}</td></tr>
                <tr><td style='padding: 8px 0;'><strong>Tickets Created:</strong></td><td>{$tickets_created}</td></tr>
            </table>
        </div>

        <div class='products'>
            <h3>GPS Products</h3>";

        foreach ($gps_products as $product) {
            $message .= "<div class='product-item'>• {$product}</div>";
        }

        $message .= "
        </div>

        <center>
            <a href='{$order_url}' class='button'>View Order in Admin</a>
        </center>
    </div>
    <div class='footer'>
        <p>GPS Dental Training - Order Tracking System</p>
        <p>Timestamp: " . current_time('mysql') . "</p>
    </div>
</body>
</html>";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: GPS Dental Training <noreply@gpsdentaltraining.com>',
        ];

        // Send to all admin emails
        $emails = implode(', ', self::ADMIN_NOTIFICATION_EMAILS);
        $sent = wp_mail($emails, $subject, $message, $headers);

        if ($sent) {
            error_log("GPS Courses: Status change notification sent for order #{$order_id} ({$old_status} → {$new_status})");
        } else {
            error_log("GPS Courses: Failed to send status change notification for order #{$order_id}");
        }
    }

    /**
     * Create tickets for an order
     */
    public static function create_tickets_for_order($order) {
        global $wpdb;

        $order_id = $order->get_id();
        $user_id = $order->get_user_id();
        $user = $user_id ? get_userdata($user_id) : null;

        $tickets_created = [];

        error_log('GPS Courses: Processing order #' . $order_id . ' for ticket creation');

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();

            error_log('GPS Courses: Processing product #' . $product_id . ' (quantity: ' . $quantity . ')');

            // Check if product is linked to a GPS ticket type
            $ticket_type_id = self::get_ticket_type_for_product($product_id);

            if (!$ticket_type_id) {
                error_log('GPS Courses: No ticket type found for product #' . $product_id);
                continue;
            }

            error_log('GPS Courses: Found ticket type #' . $ticket_type_id . ' for product #' . $product_id);

            // Get event ID from ticket type
            $event_id = (int) get_post_meta($ticket_type_id, '_gps_event_id', true);

            if (!$event_id) {
                error_log('GPS Courses: No event ID found for ticket type #' . $ticket_type_id);
                continue;
            }

            error_log('GPS Courses: Found event #' . $event_id . ' for ticket type #' . $ticket_type_id);

            // Get attendee info from order
            $attendee_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $attendee_email = $order->get_billing_email();

            // Create tickets (one for each quantity)
            for ($i = 0; $i < $quantity; $i++) {
                $ticket_id = self::create_ticket(
                    $ticket_type_id,
                    $event_id,
                    $user_id,
                    $order_id,
                    $item_id,
                    $attendee_name,
                    $attendee_email
                );

                if ($ticket_id) {
                    $tickets_created[] = $ticket_id;
                    error_log('GPS Courses: Created ticket #' . $ticket_id . ' for order #' . $order_id);

                    // Create enrollment
                    self::create_enrollment($user_id, $event_id, $order_id, $ticket_id);

                    // Trigger ticket email (legacy system only)
                    do_action('gps_ticket_created', $ticket_id, $order_id);
                } else {
                    error_log('GPS Courses: Failed to create ticket for order #' . $order_id);
                }
            }
        }

        // Store ticket IDs with order (HPOS compatible)
        if (!empty($tickets_created)) {
            $order->update_meta_data('_gps_ticket_ids', $tickets_created);
            $order->save();
            error_log('GPS Courses: Successfully created ' . count($tickets_created) . ' ticket(s) for order #' . $order_id);
        } else {
            error_log('GPS Courses: No tickets created for order #' . $order_id);
        }

        return $tickets_created;
    }

    /**
     * Manually reprocess an order to create tickets
     * Useful for fixing orders that didn't create tickets properly
     */
    public static function reprocess_order($order_id) {
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            return new \WP_Error('invalid_order', 'Order not found');
        }

        // Remove the processed flag to allow reprocessing (HPOS compatible)
        $order->delete_meta_data('_gps_tickets_created');
        $order->save();

        // Reprocess the order
        self::create_tickets_for_order($order);

        // Mark as processed (HPOS compatible)
        $order->update_meta_data('_gps_tickets_created', current_time('mysql'));
        $order->save();

        // Get created ticket IDs (HPOS compatible)
        $tickets = $order->get_meta('_gps_ticket_ids');
        return $tickets ? $tickets : [];
    }

    /**
     * Handle reprocess order form submission
     */
    public static function handle_reprocess_order() {
        if (!isset($_POST['gps_reprocess_order']) || !isset($_POST['gps_reprocess_nonce'])) {
            return;
        }

        $order_id = (int) $_POST['gps_reprocess_order'];

        // Verify nonce
        if (!wp_verify_nonce($_POST['gps_reprocess_nonce'], 'gps_reprocess_order_' . $order_id)) {
            wp_die(__('Security check failed', 'gps-courses'));
        }

        // Check permissions
        if (!current_user_can('edit_shop_orders') && !current_user_can('edit_shop_order', $order_id)) {
            wp_die(__('You do not have permission to do this', 'gps-courses'));
        }

        // Reprocess the order
        $result = self::reprocess_order($order_id);

        // Get the redirect URL
        $redirect_url = admin_url('post.php?post=' . $order_id . '&action=edit');

        if (is_wp_error($result)) {
            $redirect_url = add_query_arg('gps_error', urlencode($result->get_error_message()), $redirect_url);
        } else if (is_array($result) && !empty($result)) {
            $redirect_url = add_query_arg('gps_success', count($result), $redirect_url);
        } else {
            $redirect_url = add_query_arg('gps_error', urlencode(__('No tickets were created. Check error logs.', 'gps-courses')), $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Display admin notices for reprocess results
     */
    public static function reprocess_admin_notices() {
        if (isset($_GET['gps_success'])) {
            $count = (int) $_GET['gps_success'];
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf(_n('Successfully created %d ticket.', 'Successfully created %d tickets.', $count, 'gps-courses'), $count) . '</p>';
            echo '</div>';
        }

        if (isset($_GET['gps_error'])) {
            $error = urldecode($_GET['gps_error']);
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . esc_html($error) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Display admin notices for test email results
     */
    public static function test_email_admin_notices() {
        if (isset($_GET['gps_test_email_sent'])) {
            $emails = implode(', ', self::ADMIN_NOTIFICATION_EMAILS);
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>✅ Test email sent successfully!</strong> Check the following inboxes: ' . esc_html($emails) . '</p>';
            echo '</div>';
        }

        if (isset($_GET['gps_test_email_failed'])) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>❌ Test email failed to send.</strong> Please check your WordPress email configuration (SMTP settings, etc.)</p>';
            echo '</div>';
        }

        // Guest orders linked notice
        if (isset($_GET['gps_linked_orders'])) {
            $count = (int) $_GET['gps_linked_orders'];
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>✅ Successfully linked ' . $count . ' guest order(s) to user account!</strong> The user can now see their courses in My Account.</p>';
            echo '</div>';
        }

        if (isset($_GET['gps_link_error'])) {
            $error = urldecode($_GET['gps_link_error']);
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>❌ Error:</strong> ' . esc_html($error) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Add test email button to orders page (legacy)
     */
    public static function add_test_email_button($which) {
        global $typenow;

        // Only on legacy shop_order page
        if ($typenow !== 'shop_order') {
            return;
        }

        // Only show on top tablenav
        if ($which !== 'top') {
            return;
        }

        // Only for admins
        if (!current_user_can('manage_options')) {
            return;
        }

        self::render_test_email_button();
    }

    /**
     * Add test email button to HPOS orders page
     */
    public static function add_test_email_button_hpos($which) {
        // Only show on top tablenav
        if ($which !== 'top') {
            return;
        }

        // Only for admins
        if (!current_user_can('manage_options')) {
            return;
        }

        self::render_test_email_button();
    }

    /**
     * Render the test email button HTML
     */
    private static function render_test_email_button() {
        $test_url = wp_nonce_url(admin_url('admin.php?gps_test_email=1'), 'gps_test_email');
        ?>
        <div class="alignleft actions" style="margin-left: 10px;">
            <a href="<?php echo esc_url($test_url); ?>" class="button" title="Send test email to verify GPS order notifications are working">
                📧 Test GPS Email
            </a>
        </div>
        <?php
    }

    /**
     * Add test email button via JavaScript (fallback for HPOS)
     */
    public static function add_test_email_button_js() {
        // Only on WooCommerce orders page (HPOS)
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'woocommerce_page_wc-orders') {
            return;
        }

        // Only for admins
        if (!current_user_can('manage_options')) {
            return;
        }

        $test_url = wp_nonce_url(admin_url('admin.php?gps_test_email=1'), 'gps_test_email');
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Check if button already exists
            if ($('#gps-test-email-btn').length) {
                return;
            }

            // Find the filters area and add button
            var $filters = $('.wc-orders-list-table-filters, .tablenav.top .actions');
            if ($filters.length) {
                $filters.first().after(
                    '<div class="alignleft actions" style="margin-left: 10px;">' +
                    '<a href="<?php echo esc_url($test_url); ?>" id="gps-test-email-btn" class="button" title="Send test email to verify GPS order notifications are working">' +
                    '📧 Test GPS Email' +
                    '</a>' +
                    '</div>'
                );
            } else {
                // Fallback: add after page title
                $('.wp-header-end').after(
                    '<div style="margin: 10px 0 20px 0;">' +
                    '<a href="<?php echo esc_url($test_url); ?>" id="gps-test-email-btn" class="button button-secondary" title="Send test email to verify GPS order notifications are working">' +
                    '📧 Test GPS Email Notification' +
                    '</a>' +
                    '</div>'
                );
            }

            // Add Link Guest Orders tool
            self.addLinkGuestOrdersTool();
        });

        // Function to add link guest orders tool
        jQuery.fn.addLinkGuestOrdersTool = function() {
            var $container = $('.wp-header-end');
            if (!$container.length) {
                $container = $('.wrap h1').first();
            }

            if ($container.length && !$('#gps-link-guest-orders-form').length) {
                $container.after(
                    '<div id="gps-link-guest-orders-form" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 15px 0; border-radius: 4px; max-width: 500px;">' +
                    '<h3 style="margin: 0 0 10px 0; font-size: 14px;">🔗 Link Guest Orders to User Account</h3>' +
                    '<p style="margin: 0 0 10px 0; color: #666; font-size: 12px;">Enter a customer email to link their guest orders to their account.</p>' +
                    '<form method="get" action="<?php echo admin_url('admin.php'); ?>" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">' +
                    '<input type="hidden" name="gps_link_guest_orders" value="1">' +
                    '<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('gps_link_guest_orders'); ?>">' +
                    '<div>' +
                    '<label style="display: block; font-size: 12px; margin-bottom: 3px;">Customer Email</label>' +
                    '<input type="email" name="email" placeholder="customer@email.com" required style="width: 250px;">' +
                    '</div>' +
                    '<button type="submit" class="button button-primary">Link Orders</button>' +
                    '</form>' +
                    '</div>'
                );
            }
        };
        jQuery(document).addLinkGuestOrdersTool();
        </script>
        <?php
    }

    /**
     * Create a single ticket
     */
    private static function create_ticket($ticket_type_id, $event_id, $user_id, $order_id, $item_id, $attendee_name, $attendee_email) {
        global $wpdb;

        // Generate unique ticket code
        $ticket_code = QRCodeGenerator::generate_ticket_code($order_id, $item_id, $user_id);

        // Insert ticket into database
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'gps_tickets',
            [
                'ticket_code' => $ticket_code,
                'ticket_type_id' => $ticket_type_id,
                'event_id' => $event_id,
                'user_id' => $user_id,
                'order_id' => $order_id,
                'order_item_id' => $item_id,
                'attendee_name' => $attendee_name,
                'attendee_email' => $attendee_email,
                'status' => 'valid',
                'created_at' => current_time('mysql'),
            ],
            [
                '%s', // ticket_code
                '%d', // ticket_type_id
                '%d', // event_id
                '%d', // user_id
                '%d', // order_id
                '%d', // order_item_id
                '%s', // attendee_name
                '%s', // attendee_email
                '%s', // status
                '%s', // created_at
            ]
        );

        if (!$inserted) {
            error_log('GPS Courses: Failed to create ticket for order ' . $order_id);
            return false;
        }

        $ticket_id = $wpdb->insert_id;

        // Generate QR code
        $qr_code_path = QRCodeGenerator::generate_qr_code(
            $ticket_code,
            $ticket_id,
            [
                'event_id' => $event_id,
                'user_id' => $user_id,
                'order_id' => $order_id,
            ]
        );

        if ($qr_code_path) {
            // Update ticket with QR code path
            $wpdb->update(
                $wpdb->prefix . 'gps_tickets',
                ['qr_code_path' => $qr_code_path],
                ['id' => $ticket_id],
                ['%s'],
                ['%d']
            );
        } else {
            error_log('GPS Courses: Failed to generate QR code for ticket ' . $ticket_id);
        }

        return $ticket_id;
    }

    /**
     * Create enrollment record
     */
    private static function create_enrollment($user_id, $event_id, $order_id, $ticket_id) {
        global $wpdb;

        // Allow user_id = 0 for guest checkouts, but still require event_id
        if (!$event_id) {
            error_log('GPS Courses: Cannot create enrollment - missing event_id');
            return false;
        }

        $user_type = $user_id > 0 ? 'registered user' : 'guest checkout';
        error_log('GPS Courses: Creating enrollment for ' . $user_type . ' (user_id: ' . $user_id . '), event #' . $event_id);

        // Use event_id instead of session_id for new structure
        $result = $wpdb->insert(
            $wpdb->prefix . 'gps_enrollments',
            [
                'user_id' => $user_id,
                'session_id' => $event_id, // Store event_id here for now
                'order_id' => $order_id,
                'status' => 'completed',
                'attended' => 0,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d', // user_id
                '%d', // session_id (event_id)
                '%d', // order_id
                '%s', // status
                '%d', // attended
                '%s', // created_at
            ]
        );

        if ($result === false) {
            error_log('GPS Courses: Failed to create enrollment - DB Error: ' . $wpdb->last_error);
            return false;
        }

        $enrollment_id = $wpdb->insert_id;
        error_log('GPS Courses: Created enrollment #' . $enrollment_id);

        return $enrollment_id;
    }

    /**
     * Get ticket type for a product
     */
    private static function get_ticket_type_for_product($product_id) {
        // First check if product has direct link to ticket type
        $ticket_type_id = get_post_meta($product_id, '_gps_ticket_type_id', true);

        if ($ticket_type_id) {
            return $ticket_type_id;
        }

        // Otherwise, search for ticket type linked to this product
        $ticket_types = get_posts([
            'post_type' => 'gps_ticket',
            'post_status' => 'publish',
            'numberposts' => 1,
            'meta_query' => [
                [
                    'key' => '_gps_wc_product_id',
                    'value' => $product_id,
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        if (!empty($ticket_types)) {
            return $ticket_types[0]->ID;
        }

        return false;
    }

    /**
     * Add ticket meta to order items during checkout
     */
    public static function add_ticket_meta_to_order_item($item, $cart_item_key, $values, $order) {
        $product_id = $item->get_product_id();
        $ticket_type_id = self::get_ticket_type_for_product($product_id);

        if ($ticket_type_id) {
            $item->add_meta_data('_gps_ticket_type_id', $ticket_type_id, true);

            $event_id = get_post_meta($ticket_type_id, '_gps_event_id', true);
            if ($event_id) {
                $item->add_meta_data('_gps_event_id', $event_id, true);
            }
        }
    }

    /**
     * Add ticket column header in admin order items
     */
    public static function admin_order_item_headers($order) {
        echo '<th class="gps-tickets">' . __('Tickets', 'gps-courses') . '</th>';
    }

    /**
     * Display ticket info in admin order items
     */
    public static function admin_order_item_values($_product, $item, $item_id) {
        global $wpdb;

        echo '<td class="gps-tickets">';

        $order_id = $item->get_order_id();
        $product_id = $item->get_product_id();

        // Get tickets for this order item
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets
            WHERE order_id = %d AND order_item_id = %d",
            $order_id,
            $item_id
        ));

        if ($tickets) {
            echo '<ul style="margin: 0; padding-left: 20px;">';
            foreach ($tickets as $ticket) {
                $event = get_post($ticket->event_id);
                echo '<li>';
                echo '<strong>' . esc_html($ticket->ticket_code) . '</strong><br>';
                echo '<small>' . esc_html($event->post_title) . '</small><br>';
                echo '<span class="dashicons dashicons-yes" style="color: #46b450;"></span> ';
                echo '<small>' . esc_html($ticket->status) . '</small>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '—';
        }

        echo '</td>';
    }

    /**
     * Add order metabox for tickets
     */
    public static function add_order_metabox() {
        add_meta_box(
            'gps_order_tickets',
            __('GPS Course Tickets', 'gps-courses'),
            [__CLASS__, 'render_order_metabox'],
            'shop_order',
            'side',
            'default'
        );

        // WooCommerce HPOS compatibility
        add_meta_box(
            'gps_order_tickets',
            __('GPS Course Tickets', 'gps-courses'),
            [__CLASS__, 'render_order_metabox'],
            'woocommerce_page_wc-orders',
            'side',
            'default'
        );
    }

    /**
     * Render order tickets metabox
     */
    public static function render_order_metabox($post_or_order) {
        global $wpdb;

        $order_id = $post_or_order instanceof \WP_Post ? $post_or_order->ID : $post_or_order->get_id();

        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE order_id = %d ORDER BY id DESC",
            $order_id
        ));

        if (empty($tickets)) {
            echo '<p>' . __('No tickets created yet.', 'gps-courses') . '</p>';

            // Check if order has ticket products
            $order = wc_get_order($order_id);
            if ($order && $order->get_status() === 'completed') {
                echo '<p><em>' . __('This order may not contain ticket products, or tickets failed to create.', 'gps-courses') . '</em></p>';

                // Add reprocess button
                echo '<form method="post" style="margin-top: 10px;">';
                echo '<input type="hidden" name="gps_reprocess_order" value="' . esc_attr($order_id) . '">';
                echo wp_nonce_field('gps_reprocess_order_' . $order_id, 'gps_reprocess_nonce', true, false);
                echo '<button type="submit" class="button button-primary" onclick="return confirm(\'' . esc_js(__('This will attempt to create tickets for this order. Continue?', 'gps-courses')) . '\');">';
                echo '<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> ';
                echo __('Reprocess Order', 'gps-courses');
                echo '</button>';
                echo '</form>';
            }

            return;
        }

        echo '<div class="gps-tickets-list">';
        foreach ($tickets as $ticket) {
            $event = get_post($ticket->event_id);
            $qr_url = QRCodeGenerator::get_qr_code_url($ticket->qr_code_path);

            echo '<div style="margin-bottom: 20px; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
            echo '<strong>' . esc_html($ticket->ticket_code) . '</strong><br>';
            echo '<small style="color: #666;">' . esc_html($event->post_title) . '</small><br>';

            // Status badge
            $status_color = $ticket->status === 'valid' ? '#46b450' : '#999';
            echo '<span style="display: inline-block; margin-top: 5px; padding: 3px 8px; background: ' . $status_color . '; color: white; border-radius: 3px; font-size: 11px;">';
            echo esc_html(strtoupper($ticket->status));
            echo '</span>';

            // Check if attended
            $attended = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gps_attendance WHERE ticket_id = %d",
                $ticket->id
            ));

            if ($attended) {
                echo ' <span style="display: inline-block; margin-top: 5px; padding: 3px 8px; background: #2271b1; color: white; border-radius: 3px; font-size: 11px;">';
                echo __('CHECKED IN', 'gps-courses');
                echo '</span>';
            }

            echo '<div style="margin-top: 10px;">';
            if ($qr_url) {
                echo '<a href="' . esc_url($qr_url) . '" target="_blank" style="font-size: 11px;">' . __('View QR Code', 'gps-courses') . '</a>';
            }
            echo '</div>';

            echo '</div>';
        }
        echo '</div>';

        // Resend emails button
        echo '<p style="margin-top: 15px;">';
        echo '<a href="#" class="button button-secondary gps-resend-tickets" data-order="' . $order_id . '">';
        echo __('Resend Ticket Emails', 'gps-courses');
        echo '</a>';
        echo '</p>';
    }

    /**
     * Get tickets for order
     */
    public static function get_order_tickets($order_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE order_id = %d ORDER BY id ASC",
            $order_id
        ));
    }

    /**
     * Get tickets for user
     */
    public static function get_user_tickets($user_id, $status = 'valid') {
        global $wpdb;

        $sql = "SELECT t.*, e.post_title as event_title
                FROM {$wpdb->prefix}gps_tickets t
                LEFT JOIN {$wpdb->posts} e ON t.event_id = e.ID
                WHERE t.user_id = %d";

        if ($status) {
            $sql .= $wpdb->prepare(" AND t.status = %s", $status);
        }

        $sql .= " ORDER BY t.created_at DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $user_id));
    }

    /**
     * Add My Account menu items
     */
    public static function add_account_menu_items($items) {
        $new_items = [];

        // Insert after dashboard
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;

            if ($key === 'dashboard') {
                $new_items['gps-courses'] = __('My Courses', 'gps-courses');
                $new_items['gps-seminars'] = __('Monthly Seminars', 'gps-courses');
                $new_items['gps-ce-credits'] = __('CE Credits', 'gps-courses');
                $new_items['gps-tickets'] = __('My Tickets', 'gps-courses');
                $new_items['gps-attendance'] = __('Attendance History', 'gps-courses');
            }
        }

        return $new_items;
    }

    /**
     * Add My Account endpoints
     */
    public static function add_account_endpoints() {
        add_rewrite_endpoint('gps-courses', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('gps-seminars', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('gps-ce-credits', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('gps-tickets', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('gps-attendance', EP_ROOT | EP_PAGES);
    }

    /**
     * My Courses content
     */
    public static function my_courses_content() {
        $user_id = get_current_user_id();

        // Check for cached content
        $cache_key = 'gps_my_courses_' . $user_id;
        $cached_html = get_transient($cache_key);

        if (false !== $cached_html) {
            echo $cached_html;
            return;
        }

        // Start output buffering to cache the result
        ob_start();

        global $wpdb;

        // Get user's enrollments
        $enrollments = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, p.post_title as event_title, p.ID as event_id, e.created_at as enrolled_at
             FROM {$wpdb->prefix}gps_enrollments e
             INNER JOIN {$wpdb->posts} p ON e.session_id = p.ID
             WHERE e.user_id = %d
             ORDER BY e.created_at DESC",
            $user_id
        ));

        // Performance optimization: Fetch all post meta in one query to avoid N+1 problem
        $event_meta = [];
        if (!empty($enrollments)) {
            $event_ids = wp_list_pluck($enrollments, 'event_id');
            $meta_results = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id, meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id IN (" . implode(',', array_map('intval', $event_ids)) . ")
                 AND meta_key IN ('_gps_ce_credits', '_gps_start_date')"
            ));

            foreach ($meta_results as $meta) {
                $event_meta[$meta->post_id][$meta->meta_key] = $meta->meta_value;
            }
        }

        ?>
        <div class="gps-my-courses">
            <h2><?php _e('My Courses', 'gps-courses'); ?></h2>

            <?php if (empty($enrollments)): ?>
                <p><?php _e('You are not enrolled in any courses yet.', 'gps-courses'); ?></p>
            <?php else: ?>
                <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive">
                    <thead>
                        <tr>
                            <th><?php _e('Event', 'gps-courses'); ?></th>
                            <th><?php _e('Enrollment Date', 'gps-courses'); ?></th>
                            <th><?php _e('Status', 'gps-courses'); ?></th>
                            <th><?php _e('CE Credits', 'gps-courses'); ?></th>
                            <th><?php _e('Actions', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $enrollment):
                            // Fast lookup from pre-fetched meta (no database query)
                            $ce_credits = isset($event_meta[$enrollment->event_id]['_gps_ce_credits']) ? $event_meta[$enrollment->event_id]['_gps_ce_credits'] : '';
                            $start_date = isset($event_meta[$enrollment->event_id]['_gps_start_date']) ? $event_meta[$enrollment->event_id]['_gps_start_date'] : '';
                        ?>
                        <tr>
                            <td data-title="<?php esc_attr_e('Event', 'gps-courses'); ?>">
                                <strong><?php echo esc_html($enrollment->event_title); ?></strong><br>
                                <?php if ($start_date): ?>
                                    <small><?php echo date_i18n(get_option('date_format'), strtotime($start_date)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Enrollment Date', 'gps-courses'); ?>">
                                <?php echo date_i18n(get_option('date_format'), strtotime($enrollment->enrolled_at)); ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Status', 'gps-courses'); ?>">
                                <span class="gps-status-badge <?php echo esc_attr($enrollment->status); ?>">
                                    <?php echo esc_html(ucwords($enrollment->status)); ?>
                                </span>
                            </td>
                            <td data-title="<?php esc_attr_e('CE Credits', 'gps-courses'); ?>">
                                <?php if ($ce_credits): ?>
                                    <?php echo (int) $ce_credits; ?> <?php _e('Credits', 'gps-courses'); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Actions', 'gps-courses'); ?>">
                                <a href="<?php echo get_permalink($enrollment->event_id); ?>" class="woocommerce-button button view">
                                    <?php _e('View Event', 'gps-courses'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php

        // Store cached content (cache for 10 minutes)
        $html = ob_get_clean();
        set_transient($cache_key, $html, 10 * MINUTE_IN_SECONDS);
        echo $html;
    }

    /**
     * My Monthly Seminars content
     */
    public static function my_seminars_content() {
        $user_id = get_current_user_id();

        // Check for cached content
        $cache_key = 'gps_my_seminars_' . $user_id;
        $cached_html = get_transient($cache_key);

        if (false !== $cached_html) {
            echo $cached_html;
            return;
        }

        // Start output buffering to cache the result
        ob_start();

        global $wpdb;

        // Get user's seminar registrations
        $registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title as seminar_title, p.ID as seminar_id
             FROM {$wpdb->prefix}gps_seminar_registrations r
             INNER JOIN {$wpdb->posts} p ON r.seminar_id = p.ID
             WHERE r.user_id = %d AND r.status = 'active'
             ORDER BY r.registered_at DESC",
            $user_id
        ));

        // Performance optimization: Pre-fetch ALL data needed for all registrations
        $seminar_meta = [];
        $all_sessions = [];
        $all_attendance = [];
        $all_credits = [];

        if (!empty($registrations)) {
            // Fetch all seminar meta (years) in one query
            $seminar_ids = wp_list_pluck($registrations, 'seminar_id');
            $meta_results = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id IN (" . implode(',', array_map('intval', $seminar_ids)) . ")
                 AND meta_key = '_gps_seminar_year'"
            ));
            foreach ($meta_results as $meta) {
                $seminar_meta[$meta->post_id] = $meta->meta_value;
            }

            // Fetch all sessions for all seminars in one query
            $sessions_results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions
                 WHERE seminar_id IN (" . implode(',', array_map('intval', $seminar_ids)) . ")
                 ORDER BY session_date ASC, session_time_start ASC"
            ));
            foreach ($sessions_results as $session) {
                if (!isset($all_sessions[$session->seminar_id])) {
                    $all_sessions[$session->seminar_id] = [];
                }
                $all_sessions[$session->seminar_id][] = $session;
            }

            // Fetch all attendance records for all registrations in one query
            $registration_ids = wp_list_pluck($registrations, 'id');
            $attendance_results = $wpdb->get_results($wpdb->prepare(
                "SELECT registration_id, session_id, credits_awarded
                 FROM {$wpdb->prefix}gps_seminar_attendance
                 WHERE registration_id IN (" . implode(',', array_map('intval', $registration_ids)) . ")"
            ));
            foreach ($attendance_results as $att) {
                if (!isset($all_attendance[$att->registration_id])) {
                    $all_attendance[$att->registration_id] = [];
                }
                $all_attendance[$att->registration_id][$att->session_id] = $att;
            }

            // Fetch all total credits for all registrations in one query
            $credits_results = $wpdb->get_results($wpdb->prepare(
                "SELECT registration_id, SUM(credits_awarded) as total_credits
                 FROM {$wpdb->prefix}gps_seminar_attendance
                 WHERE registration_id IN (" . implode(',', array_map('intval', $registration_ids)) . ")
                 GROUP BY registration_id"
            ));
            foreach ($credits_results as $credit) {
                $all_credits[$credit->registration_id] = (int) $credit->total_credits;
            }
        }

        ?>
        <div class="gps-my-seminars">
            <h2><?php _e('My Monthly Seminars', 'gps-courses'); ?></h2>

            <?php if (empty($registrations)): ?>
                <p><?php _e('You are not registered for any Monthly Seminars yet.', 'gps-courses'); ?></p>
            <?php else: ?>
                <?php foreach ($registrations as $registration):
                    // Fast lookups from pre-fetched data (no database queries!)
                    $year = isset($seminar_meta[$registration->seminar_id]) ? $seminar_meta[$registration->seminar_id] : '';
                    $total_credits = isset($all_credits[$registration->id]) ? $all_credits[$registration->id] : 0;
                    $sessions = isset($all_sessions[$registration->seminar_id]) ? $all_sessions[$registration->seminar_id] : [];
                    $attendance_records = isset($all_attendance[$registration->id]) ? $all_attendance[$registration->id] : [];
                ?>
                <div class="gps-seminar-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                        <div>
                            <h3 style="margin: 0 0 5px 0; font-size: 20px; color: #1e293b;">
                                <?php echo esc_html($registration->seminar_title); ?>
                                <?php if ($year): ?>
                                    <span style="color: #64748b; font-weight: normal;">(<?php echo esc_html($year); ?>)</span>
                                <?php endif; ?>
                            </h3>
                            <p style="margin: 0; color: #64748b; font-size: 14px;">
                                <?php _e('Registered:', 'gps-courses'); ?>
                                <?php echo date_i18n(get_option('date_format'), strtotime($registration->registered_at)); ?>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <div style="background: #eff6ff; padding: 10px 15px; border-radius: 6px; border-left: 3px solid #3b82f6;">
                                <div style="font-size: 12px; color: #1e40af; margin-bottom: 3px;"><?php _e('Total Credits', 'gps-courses'); ?></div>
                                <div style="font-size: 24px; font-weight: bold; color: #1e40af;"><?php echo (int) $total_credits; ?></div>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <div style="background: #f8fafc; padding: 15px; border-radius: 6px;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;"><?php _e('Sessions Completed', 'gps-courses'); ?></div>
                            <div style="font-size: 20px; font-weight: bold; color: #3b82f6;">
                                <?php echo (int) $registration->sessions_completed; ?> / 10
                            </div>
                        </div>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 6px;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;"><?php _e('Remaining', 'gps-courses'); ?></div>
                            <div style="font-size: 20px; font-weight: bold; color: #10b981;">
                                <?php echo (int) $registration->sessions_remaining; ?>
                            </div>
                        </div>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 6px;">
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;"><?php _e('Makeup Used', 'gps-courses'); ?></div>
                            <div style="font-size: 20px; font-weight: bold; color: <?php echo $registration->makeup_used ? '#ef4444' : '#64748b'; ?>;">
                                <?php echo $registration->makeup_used ? __('Yes', 'gps-courses') : __('No', 'gps-courses'); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($sessions)): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="margin: 0 0 15px 0; font-size: 16px; color: #1e293b;"><?php _e('Sessions Schedule', 'gps-courses'); ?></h4>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($sessions as $session):
                                // Fast lookup - no database query needed!
                                $attendance = isset($attendance_records[$session->id]) ? $attendance_records[$session->id] : null;
                                $attended = !empty($attendance);
                                $credits_awarded = $attended ? (int) $attendance->credits_awarded : 0;
                                $is_past = strtotime($session->session_date) < time();
                            ?>
                            <div style="display: flex; align-items: center; padding: 12px; border-bottom: 1px solid #e2e8f0; <?php echo $attended ? 'background: #f0fdf4;' : ''; ?>">
                                <div style="width: 40px; height: 40px; background: <?php echo $attended ? '#10b981' : '#e2e8f0'; ?>; color: <?php echo $attended ? '#fff' : '#64748b'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; flex-shrink: 0;">
                                    <?php echo (int) $session->session_number; ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #1e293b; margin-bottom: 3px;">
                                        <?php echo esc_html($session->topic); ?>
                                        <?php if ($attended): ?>
                                            <span style="color: #10b981; font-size: 18px; margin-left: 8px;">✓</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 13px; color: #64748b;">
                                        📅 <?php echo date_i18n('F j, Y', strtotime($session->session_date)); ?>
                                        &nbsp;•&nbsp;
                                        🕐 <?php echo date('g:i A', strtotime($session->session_time_start)); ?> - <?php echo date('g:i A', strtotime($session->session_time_end)); ?>
                                        <?php if ($attended): ?>
                                            &nbsp;•&nbsp;
                                            <strong style="color: #10b981;"><?php echo (int) $credits_awarded; ?> <?php _e('Credits', 'gps-courses'); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <a href="<?php echo get_permalink($registration->seminar_id); ?>" class="woocommerce-button button view">
                            <?php _e('View Seminar Details', 'gps-courses'); ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php

        // Store cached content (cache for 10 minutes)
        $html = ob_get_clean();
        set_transient($cache_key, $html, 10 * MINUTE_IN_SECONDS);
        echo $html;
    }

    /**
     * CE Credits content
     */
    public static function ce_credits_content() {
        $user_id = get_current_user_id();

        // Check for cached content
        $cache_key = 'gps_ce_credits_' . $user_id;
        $cached_html = get_transient($cache_key);

        if (false !== $cached_html) {
            echo $cached_html;
            return;
        }

        // Start output buffering to cache the result
        ob_start();
        $total_credits = Credits::get_total($user_id);
        $ledger = Credits::get_ledger($user_id);

        // Performance optimization: Fetch all event titles in one query to avoid N+1 problem
        global $wpdb;
        $event_titles = [];
        if (!empty($ledger)) {
            $event_ids = array_filter(wp_list_pluck($ledger, 'event_id'));
            if (!empty($event_ids)) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title FROM {$wpdb->posts}
                     WHERE ID IN (" . implode(',', array_map('intval', $event_ids)) . ")"
                ));
                foreach ($results as $post) {
                    $event_titles[$post->ID] = $post->post_title;
                }
            }
        }

        ?>
        <div class="gps-ce-credits-account">
            <h2><?php _e('CE Credits', 'gps-courses'); ?></h2>

            <div class="gps-credits-summary">
                <div class="gps-credits-box">
                    <span class="gps-credits-number"><?php echo (int) $total_credits; ?></span>
                    <span class="gps-credits-label"><?php _e('Total CE Credits Earned', 'gps-courses'); ?></span>
                </div>
            </div>

            <?php if (empty($ledger)): ?>
                <p><?php _e('You have not earned any CE credits yet.', 'gps-courses'); ?></p>
            <?php else: ?>
                <h3><?php _e('Credits History', 'gps-courses'); ?></h3>
                <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'gps-courses'); ?></th>
                            <th><?php _e('Event', 'gps-courses'); ?></th>
                            <th><?php _e('Credits', 'gps-courses'); ?></th>
                            <th><?php _e('Type', 'gps-courses'); ?></th>
                            <th><?php _e('Notes', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ledger as $entry): ?>
                        <tr>
                            <td data-title="<?php esc_attr_e('Date', 'gps-courses'); ?>">
                                <?php echo date_i18n(get_option('date_format'), strtotime($entry->awarded_at)); ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Event', 'gps-courses'); ?>">
                                <?php if ($entry->event_id && isset($event_titles[$entry->event_id])): ?>
                                    <a href="<?php echo get_permalink($entry->event_id); ?>">
                                        <?php echo esc_html($event_titles[$entry->event_id]); ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Credits', 'gps-courses'); ?>">
                                <strong><?php echo (int) $entry->credits; ?></strong>
                            </td>
                            <td data-title="<?php esc_attr_e('Type', 'gps-courses'); ?>">
                                <span class="gps-credit-type <?php echo esc_attr($entry->transaction_type); ?>">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $entry->transaction_type))); ?>
                                </span>
                            </td>
                            <td data-title="<?php esc_attr_e('Notes', 'gps-courses'); ?>">
                                <?php echo esc_html($entry->notes); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php

        // Store cached content (cache for 10 minutes)
        $html = ob_get_clean();
        set_transient($cache_key, $html, 10 * MINUTE_IN_SECONDS);
        echo $html;
    }

    /**
     * My Tickets content
     */
    public static function my_tickets_content() {
        $user_id = get_current_user_id();

        // Check for cached content
        $cache_key = 'gps_my_tickets_' . $user_id;
        $cached_html = get_transient($cache_key);

        if (false !== $cached_html) {
            echo $cached_html;
            return;
        }

        // Start output buffering to cache the result
        ob_start();

        global $wpdb;

        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, p.post_title as event_title,
                    a.checked_in_at,
                    a.check_in_method
             FROM {$wpdb->prefix}gps_tickets t
             INNER JOIN {$wpdb->posts} p ON t.event_id = p.ID
             LEFT JOIN {$wpdb->prefix}gps_attendance a ON t.id = a.ticket_id
             WHERE t.user_id = %d
             ORDER BY t.created_at DESC",
            $user_id
        ));

        ?>
        <div class="gps-my-tickets-account">
            <h2><?php _e('My Tickets', 'gps-courses'); ?></h2>

            <?php if (empty($tickets)): ?>
                <p><?php _e('You have no tickets yet.', 'gps-courses'); ?></p>
            <?php else: ?>
                <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive">
                    <thead>
                        <tr>
                            <th><?php _e('Event', 'gps-courses'); ?></th>
                            <th><?php _e('Ticket Code', 'gps-courses'); ?></th>
                            <th><?php _e('Attendee', 'gps-courses'); ?></th>
                            <th><?php _e('Status', 'gps-courses'); ?></th>
                            <th><?php _e('QR Code', 'gps-courses'); ?></th>
                            <th><?php _e('Actions', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td data-title="<?php esc_attr_e('Event', 'gps-courses'); ?>">
                                <strong><?php echo esc_html($ticket->event_title); ?></strong><br>
                                <small><?php echo date_i18n(get_option('date_format'), strtotime($ticket->created_at)); ?></small>
                            </td>
                            <td data-title="<?php esc_attr_e('Ticket Code', 'gps-courses'); ?>">
                                <code><?php echo esc_html($ticket->ticket_code); ?></code>
                            </td>
                            <td data-title="<?php esc_attr_e('Attendee', 'gps-courses'); ?>">
                                <?php echo esc_html($ticket->attendee_name); ?><br>
                                <small><?php echo esc_html($ticket->attendee_email); ?></small>
                            </td>
                            <td data-title="<?php esc_attr_e('Status', 'gps-courses'); ?>">
                                <?php if ($ticket->checked_in_at): ?>
                                    <span class="gps-status-badge checked-in">
                                        <?php _e('Checked In', 'gps-courses'); ?>
                                    </span><br>
                                    <small><?php echo date_i18n(get_option('date_format'), strtotime($ticket->checked_in_at)); ?></small>
                                <?php else: ?>
                                    <span class="gps-status-badge <?php echo esc_attr($ticket->status); ?>">
                                        <?php echo esc_html(ucwords($ticket->status)); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-title="<?php esc_attr_e('QR Code', 'gps-courses'); ?>">
                                <?php if ($ticket->qr_code_path): ?>
                                    <img src="<?php echo site_url($ticket->qr_code_path); ?>" alt="QR Code" style="max-width: 100px;" onerror="this.style.display='none'">
                                <?php endif; ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Actions', 'gps-courses'); ?>">
                                <a href="<?php echo esc_url(add_query_arg('download_ticket', $ticket->id)); ?>" class="woocommerce-button button">
                                    <i class="fas fa-download"></i> <?php _e('Download PDF', 'gps-courses'); ?>
                                </a>
                                <a href="<?php echo get_permalink($ticket->event_id); ?>" class="woocommerce-button button view">
                                    <?php _e('View Event', 'gps-courses'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php

        // Store cached content (cache for 10 minutes)
        $html = ob_get_clean();
        set_transient($cache_key, $html, 10 * MINUTE_IN_SECONDS);
        echo $html;
    }

    /**
     * Attendance History content
     */
    public static function attendance_history_content() {
        $user_id = get_current_user_id();

        // Check for cached content
        $cache_key = 'gps_attendance_history_' . $user_id;
        $cached_html = get_transient($cache_key);

        if (false !== $cached_html) {
            echo $cached_html;
            return;
        }

        // Start output buffering to cache the result
        ob_start();

        global $wpdb;

        $attendance = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.post_title as event_title, t.ticket_code
             FROM {$wpdb->prefix}gps_attendance a
             INNER JOIN {$wpdb->posts} p ON a.event_id = p.ID
             INNER JOIN {$wpdb->prefix}gps_tickets t ON a.ticket_id = t.id
             WHERE a.user_id = %d
             ORDER BY a.checked_in_at DESC",
            $user_id
        ));

        ?>
        <div class="gps-attendance-history-account">
            <h2><?php _e('Attendance History', 'gps-courses'); ?></h2>

            <?php if (empty($attendance)): ?>
                <p><?php _e('You have not checked in to any events yet.', 'gps-courses'); ?></p>
            <?php else: ?>
                <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive">
                    <thead>
                        <tr>
                            <th><?php _e('Event', 'gps-courses'); ?></th>
                            <th><?php _e('Ticket Code', 'gps-courses'); ?></th>
                            <th><?php _e('Check-in Date', 'gps-courses'); ?></th>
                            <th><?php _e('Method', 'gps-courses'); ?></th>
                            <th><?php _e('Notes', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $record): ?>
                        <tr>
                            <td data-title="<?php esc_attr_e('Event', 'gps-courses'); ?>">
                                <a href="<?php echo get_permalink($record->event_id); ?>">
                                    <?php echo esc_html($record->event_title); ?>
                                </a>
                            </td>
                            <td data-title="<?php esc_attr_e('Ticket Code', 'gps-courses'); ?>">
                                <code><?php echo esc_html($record->ticket_code); ?></code>
                            </td>
                            <td data-title="<?php esc_attr_e('Check-in Date', 'gps-courses'); ?>">
                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($record->checked_in_at)); ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Method', 'gps-courses'); ?>">
                                <span class="gps-method-badge <?php echo esc_attr($record->check_in_method); ?>">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $record->check_in_method))); ?>
                                </span>
                            </td>
                            <td data-title="<?php esc_attr_e('Notes', 'gps-courses'); ?>">
                                <?php echo esc_html($record->notes); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php

        // Store cached content (cache for 10 minutes)
        $html = ob_get_clean();
        set_transient($cache_key, $html, 10 * MINUTE_IN_SECONDS);
        echo $html;
    }

    /**
     * Sync WooCommerce product stock with GPS ticket quantity
     * This ensures products always reflect the correct available stock across ALL ticket variations
     */
    public static function sync_product_stock($ticket_id) {
        // Get ticket data
        $product_id = get_post_meta($ticket_id, '_gps_wc_product_id', true);
        $event_id = get_post_meta($ticket_id, '_gps_event_id', true);

        if (!$product_id || !$event_id) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Get total stock across ALL ticket variations for this event
        $event_stock = Tickets::get_event_total_stock($event_id);
        $total_available = $event_stock['total_available'];

        error_log('GPS Courses: Syncing stock for product #' . $product_id . ' (event #' . $event_id . ')');
        error_log('GPS Courses: Total available across all ticket types: ' . $total_available);

        // Update product stock
        if ($total_available > 0) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($total_available);
            $product->set_stock_status('instock');
            $product->set_backorders('no');
        } else {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(0);
            $product->set_stock_status('outofstock');
        }

        $product->save();

        error_log('GPS Courses: Product #' . $product_id . ' stock synced to ' . $total_available . ($event_stock['unlimited'] ? ' (unlimited)' : ' (limited)'));
    }

    /**
     * Sync product stock after a ticket is created from purchase
     * This ensures WooCommerce product stock accurately reflects GPS ticket availability
     */
    public static function sync_product_stock_after_purchase($ticket_id, $order_id) {
        global $wpdb;

        // Get the ticket
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT ticket_type_id, event_id FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            $ticket_id
        ));

        if (!$ticket) {
            return;
        }

        // Sync the stock for this ticket type
        self::sync_product_stock($ticket->ticket_type_id);

        error_log('GPS Courses: Stock synced after ticket #' . $ticket_id . ' created for order #' . $order_id);
    }
}
