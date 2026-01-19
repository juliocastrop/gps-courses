<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Enhanced Waitlist Management for Course Tickets
 * Handles waitlist signups, notifications, and admin management
 */
class Waitlist {

    public static function init() {
        // Frontend AJAX handlers
        add_action('wp_ajax_gps_join_waitlist', [__CLASS__, 'ajax_join_waitlist']);
        add_action('wp_ajax_nopriv_gps_join_waitlist', [__CLASS__, 'ajax_join_waitlist']);

        // Admin AJAX handlers
        add_action('wp_ajax_gps_admin_remove_waitlist', [__CLASS__, 'ajax_admin_remove']);
        add_action('wp_ajax_gps_admin_notify_waitlist', [__CLASS__, 'ajax_admin_notify']);
        add_action('wp_ajax_gps_admin_mark_converted', [__CLASS__, 'ajax_admin_mark_converted']);
        add_action('wp_ajax_gps_waitlist_bulk_action', [__CLASS__, 'ajax_bulk_action']);

        // Admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);

        // WooCommerce hooks for auto-notification
        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'on_order_cancelled']);
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'on_order_refunded']);

        // Cron job for expired notifications
        add_action('gps_process_expired_ticket_waitlist', [__CLASS__, 'process_expired_notifications']);
        if (!wp_next_scheduled('gps_process_expired_ticket_waitlist')) {
            wp_schedule_event(time(), 'hourly', 'gps_process_expired_ticket_waitlist');
        }
    }

    /**
     * Add admin menu page
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Waitlist Management', 'gps-courses'),
            __('Waitlist', 'gps-courses'),
            'manage_options',
            'gps-waitlist',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-waitlist') {
            return;
        }

        wp_enqueue_style(
            'gps-admin-waitlist',
            GPSC_URL . 'assets/css/admin-waitlist.css',
            [],
            GPSC_VERSION
        );

        wp_enqueue_script(
            'gps-admin-waitlist',
            GPSC_URL . 'assets/js/admin-waitlist.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        wp_localize_script('gps-admin-waitlist', 'gpsWaitlistAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_waitlist_admin'),
            'i18n' => [
                'confirmRemove' => __('Are you sure you want to remove this entry from the waitlist?', 'gps-courses'),
                'confirmNotify' => __('Send notification email to this person?', 'gps-courses'),
                'confirmBulkRemove' => __('Are you sure you want to remove all selected entries?', 'gps-courses'),
                'sending' => __('Sending...', 'gps-courses'),
                'removing' => __('Removing...', 'gps-courses'),
            ],
        ]);
    }

    /**
     * AJAX handler for joining waitlist (frontend)
     */
    public static function ajax_join_waitlist() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gps_waitlist')) {
            wp_send_json_error(['message' => __('Security check failed.', 'gps-courses')]);
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
        $event_id = (int) ($_POST['event_id'] ?? 0);
        $user_id = get_current_user_id();

        // Validate
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'gps-courses')]);
        }

        if (empty($ticket_id) || empty($event_id)) {
            wp_send_json_error(['message' => __('Invalid ticket or event.', 'gps-courses')]);
        }

        // Add to waitlist
        $result = self::add_to_waitlist($ticket_id, $event_id, $email, $first_name, $last_name, $phone, $user_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Success! You\'ve been added to the waitlist at position #%d. We\'ll notify you if tickets become available.', 'gps-courses'),
                $result['position']
            ),
            'position' => $result['position'],
        ]);
    }

    /**
     * Add user to waitlist
     */
    public static function add_to_waitlist($ticket_id, $event_id, $email, $first_name = '', $last_name = '', $phone = '', $user_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        // Check if already on waitlist
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table
            WHERE email = %s AND ticket_type_id = %d AND event_id = %d AND status IN ('waiting', 'notified')",
            $email,
            $ticket_id,
            $event_id
        ));

        if ($exists) {
            return new \WP_Error('already_on_waitlist', __('You\'re already on the waitlist for this ticket!', 'gps-courses'));
        }

        // Get next position
        $position = self::get_next_position($ticket_id, $event_id);

        // Build data array - handle user_id null case properly
        $data = [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'ticket_type_id' => $ticket_id,
            'event_id' => $event_id,
            'position' => $position,
            'status' => 'waiting',
            'created_at' => current_time('mysql'),
        ];
        $format = ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s'];

        // Only include user_id if it's a valid ID
        if ($user_id > 0) {
            $data['user_id'] = $user_id;
            $format[] = '%d';
        }

        // Insert
        $inserted = $wpdb->insert($table, $data, $format);

        if (!$inserted) {
            error_log('GPS Courses: Failed to insert waitlist entry: ' . $wpdb->last_error);
            return new \WP_Error('insert_failed', __('Error adding to waitlist. Please try again.', 'gps-courses'));
        }

        $waitlist_id = $wpdb->insert_id;

        // Send confirmation email
        self::send_waitlist_confirmation($waitlist_id);

        return [
            'id' => $waitlist_id,
            'position' => $position,
        ];
    }

    /**
     * Get next position in waitlist
     */
    public static function get_next_position($ticket_id, $event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        $max_position = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) FROM $table
            WHERE ticket_type_id = %d AND event_id = %d AND status = 'waiting'",
            $ticket_id,
            $event_id
        ));

        return ($max_position ?: 0) + 1;
    }

    /**
     * Remove from waitlist
     */
    public static function remove_from_waitlist($waitlist_id, $new_status = 'removed') {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        // Get entry info before removing
        $entry = self::get_waitlist_entry($waitlist_id);
        if (!$entry) {
            return false;
        }

        // Update status
        $updated = $wpdb->update(
            $table,
            ['status' => $new_status],
            ['id' => $waitlist_id],
            ['%s'],
            ['%d']
        );

        if ($updated !== false) {
            // Reorder remaining entries
            self::reorder_waitlist($entry->ticket_type_id, $entry->event_id);
            return true;
        }

        return false;
    }

    /**
     * Reorder waitlist positions
     */
    public static function reorder_waitlist($ticket_id, $event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        // Get all waiting entries ordered by creation date
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table
            WHERE ticket_type_id = %d AND event_id = %d AND status = 'waiting'
            ORDER BY created_at ASC",
            $ticket_id,
            $event_id
        ));

        // Reassign positions
        $position = 1;
        foreach ($entries as $entry) {
            $wpdb->update(
                $table,
                ['position' => $position],
                ['id' => $entry->id],
                ['%d'],
                ['%d']
            );
            $position++;
        }
    }

    /**
     * Notify next person on waitlist
     */
    public static function notify_next_on_waitlist($ticket_id, $event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        // Get next waiting entry
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table
            WHERE ticket_type_id = %d AND event_id = %d AND status = 'waiting'
            ORDER BY position ASC
            LIMIT 1",
            $ticket_id,
            $event_id
        ));

        if (!$entry) {
            return false;
        }

        return self::send_spot_available_notification($entry->id);
    }

    /**
     * Send spot available notification
     */
    public static function send_spot_available_notification($waitlist_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        $entry = self::get_waitlist_entry($waitlist_id);
        if (!$entry || $entry->status !== 'waiting') {
            return false;
        }

        $ticket = get_post($entry->ticket_type_id);
        $event = get_post($entry->event_id);

        if (!$ticket || !$event) {
            return false;
        }

        // Set expiration (48 hours)
        $expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));

        // Update status
        $wpdb->update(
            $table,
            [
                'status' => 'notified',
                'notified_at' => current_time('mysql'),
                'expires_at' => $expires_at,
            ],
            ['id' => $waitlist_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        // Send email
        $event_url = get_permalink($entry->event_id);
        $expires_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expires_at));
        $name = $entry->first_name ?: 'there';

        $subject = sprintf(
            __('A Spot is Available! - %s', 'gps-courses'),
            $event->post_title
        );

        $message = sprintf(
            __('Hello %s,<br><br>Great news! A spot has become available for:<br><br><strong>%s</strong><br>Ticket Type: %s<br><br><strong style="color: #d63638;">You have until %s to complete your purchase.</strong><br><br><a href="%s" style="display: inline-block; padding: 12px 24px; background: #6200ea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Get Your Ticket Now</a><br><br>If you do not purchase within 48 hours, the spot will be offered to the next person on the waitlist.<br><br>Thank you!<br><br>GPS Dental Training', 'gps-courses'),
            esc_html($name),
            esc_html($event->post_title),
            esc_html($ticket->post_title),
            $expires_formatted,
            esc_url($event_url)
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($entry->email, $subject, $message, $headers);

        if ($sent) {
            error_log('GPS Courses: Spot available notification sent to ' . $entry->email . ' for waitlist #' . $waitlist_id);
        }

        return $sent;
    }

    /**
     * Send waitlist confirmation email
     */
    private static function send_waitlist_confirmation($waitlist_id) {
        $entry = self::get_waitlist_entry($waitlist_id);
        if (!$entry) {
            return false;
        }

        $ticket = get_post($entry->ticket_type_id);
        $event = get_post($entry->event_id);

        if (!$ticket || !$event) {
            return false;
        }

        $name = $entry->first_name ?: 'there';

        $subject = sprintf(
            __('You\'re on the Waitlist - %s', 'gps-courses'),
            $event->post_title
        );

        $message = sprintf(
            __('Hello %s,<br><br>You have been added to the waitlist for:<br><br><strong>%s</strong><br>Ticket Type: %s<br><br>Your position: <strong>#%d</strong><br><br>We will notify you by email if a spot becomes available. You will have 48 hours to complete your purchase once notified.<br><br>Thank you for your interest!<br><br>GPS Dental Training', 'gps-courses'),
            esc_html($name),
            esc_html($event->post_title),
            esc_html($ticket->post_title),
            $entry->position
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($entry->email, $subject, $message, $headers);
    }

    /**
     * Process expired notifications (cron job)
     */
    public static function process_expired_notifications() {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        // Find expired notifications
        $expired = $wpdb->get_results(
            "SELECT * FROM $table
            WHERE status = 'notified' AND expires_at IS NOT NULL AND expires_at < NOW()"
        );

        foreach ($expired as $entry) {
            // Mark as expired
            $wpdb->update(
                $table,
                ['status' => 'expired'],
                ['id' => $entry->id],
                ['%s'],
                ['%d']
            );

            error_log('GPS Courses: Waitlist notification expired for #' . $entry->id . ' (' . $entry->email . ')');

            // Notify next person
            self::notify_next_on_waitlist($entry->ticket_type_id, $entry->event_id);
        }
    }

    /**
     * Get waitlist entry by ID
     */
    public static function get_waitlist_entry($waitlist_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_waitlist WHERE id = %d",
            $waitlist_id
        ));
    }

    /**
     * Get waitlist entries with filters
     */
    public static function get_waitlist_entries($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        $defaults = [
            'ticket_id' => 0,
            'event_id' => 0,
            'status' => '',
            'orderby' => 'position',
            'order' => 'ASC',
            'limit' => 50,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $values = [];

        if ($args['ticket_id']) {
            $where[] = 'ticket_type_id = %d';
            $values[] = $args['ticket_id'];
        }

        if ($args['event_id']) {
            $where[] = 'event_id = %d';
            $values[] = $args['event_id'];
        }

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'position ASC';

        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * Get waitlist count
     */
    public static function get_waitlist_count($ticket_id = 0, $event_id = 0, $status = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        $where = ['1=1'];
        $values = [];

        if ($ticket_id) {
            $where[] = 'ticket_type_id = %d';
            $values[] = $ticket_id;
        }

        if ($event_id) {
            $where[] = 'event_id = %d';
            $values[] = $event_id;
        }

        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $where_clause = implode(' AND ', $where);

        if (empty($values)) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_clause");
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $where_clause",
            $values
        ));
    }

    /**
     * Handle order cancelled - notify waitlist
     */
    public static function on_order_cancelled($order_id) {
        self::check_and_notify_waitlist_for_order($order_id);
    }

    /**
     * Handle order refunded - notify waitlist
     */
    public static function on_order_refunded($order_id) {
        self::check_and_notify_waitlist_for_order($order_id);
    }

    /**
     * Check order and notify waitlist if tickets available
     */
    private static function check_and_notify_waitlist_for_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            // Get ticket ID from product
            $ticket_id = get_post_meta($product_id, '_gps_ticket_id', true);
            if (!$ticket_id) {
                continue;
            }

            // Get event ID from ticket
            $event_id = get_post_meta($ticket_id, '_gps_event_id', true);
            if (!$event_id) {
                continue;
            }

            // Check if ticket is no longer sold out
            if (!Tickets::is_sold_out($ticket_id)) {
                // Notify next person on waitlist
                self::notify_next_on_waitlist($ticket_id, $event_id);
                error_log('GPS Courses: Order #' . $order_id . ' cancelled/refunded - notifying waitlist for ticket #' . $ticket_id);
            }
        }
    }

    /**
     * Admin AJAX - Remove from waitlist
     */
    public static function ajax_admin_remove() {
        check_ajax_referer('gps_waitlist_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'gps-courses')]);
        }

        $waitlist_id = (int) ($_POST['waitlist_id'] ?? 0);

        if (self::remove_from_waitlist($waitlist_id, 'removed')) {
            wp_send_json_success(['message' => __('Entry removed from waitlist.', 'gps-courses')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove entry.', 'gps-courses')]);
        }
    }

    /**
     * Admin AJAX - Notify waitlist entry
     */
    public static function ajax_admin_notify() {
        check_ajax_referer('gps_waitlist_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'gps-courses')]);
        }

        $waitlist_id = (int) ($_POST['waitlist_id'] ?? 0);

        if (self::send_spot_available_notification($waitlist_id)) {
            wp_send_json_success(['message' => __('Notification sent successfully.', 'gps-courses')]);
        } else {
            wp_send_json_error(['message' => __('Failed to send notification.', 'gps-courses')]);
        }
    }

    /**
     * Admin AJAX - Mark as converted
     */
    public static function ajax_admin_mark_converted() {
        check_ajax_referer('gps_waitlist_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'gps-courses')]);
        }

        $waitlist_id = (int) ($_POST['waitlist_id'] ?? 0);

        if (self::remove_from_waitlist($waitlist_id, 'converted')) {
            wp_send_json_success(['message' => __('Entry marked as converted.', 'gps-courses')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update entry.', 'gps-courses')]);
        }
    }

    /**
     * Admin AJAX - Bulk action
     */
    public static function ajax_bulk_action() {
        check_ajax_referer('gps_waitlist_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'gps-courses')]);
        }

        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $ids = array_map('intval', $_POST['ids'] ?? []);

        if (empty($ids)) {
            wp_send_json_error(['message' => __('No entries selected.', 'gps-courses')]);
        }

        $count = 0;

        switch ($action) {
            case 'remove':
                foreach ($ids as $id) {
                    if (self::remove_from_waitlist($id, 'removed')) {
                        $count++;
                    }
                }
                break;

            case 'notify':
                foreach ($ids as $id) {
                    if (self::send_spot_available_notification($id)) {
                        $count++;
                    }
                }
                break;
        }

        wp_send_json_success([
            'message' => sprintf(__('%d entries processed.', 'gps-courses'), $count),
            'count' => $count,
        ]);
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        global $wpdb;

        // Get filter values
        $filter_event = (int) ($_GET['event_id'] ?? 0);
        $filter_status = sanitize_text_field($_GET['status'] ?? '');

        // Get all events that have waitlist entries
        $events_with_waitlist = $wpdb->get_results(
            "SELECT DISTINCT e.ID, e.post_title
            FROM {$wpdb->posts} e
            INNER JOIN {$wpdb->prefix}gps_waitlist w ON e.ID = w.event_id
            WHERE e.post_type = 'gps_event'
            ORDER BY e.post_title ASC"
        );

        // Get statistics
        $total_waiting = self::get_waitlist_count(0, $filter_event, 'waiting');
        $total_notified = self::get_waitlist_count(0, $filter_event, 'notified');
        $total_converted = self::get_waitlist_count(0, $filter_event, 'converted');
        $total_expired = self::get_waitlist_count(0, $filter_event, 'expired');

        // Build query args
        $query_args = [
            'event_id' => $filter_event,
            'status' => $filter_status,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 100,
        ];

        $entries = self::get_waitlist_entries($query_args);

        ?>
        <div class="wrap gps-waitlist-page">
            <h1><?php _e('Waitlist Management', 'gps-courses'); ?></h1>

            <!-- Statistics Cards -->
            <div class="gps-waitlist-stats">
                <div class="gps-stat-card">
                    <div class="stat-value"><?php echo esc_html($total_waiting); ?></div>
                    <div class="stat-label"><?php _e('Waiting', 'gps-courses'); ?></div>
                </div>
                <div class="gps-stat-card gps-stat-notified">
                    <div class="stat-value"><?php echo esc_html($total_notified); ?></div>
                    <div class="stat-label"><?php _e('Notified (48h)', 'gps-courses'); ?></div>
                </div>
                <div class="gps-stat-card gps-stat-converted">
                    <div class="stat-value"><?php echo esc_html($total_converted); ?></div>
                    <div class="stat-label"><?php _e('Converted', 'gps-courses'); ?></div>
                </div>
                <div class="gps-stat-card gps-stat-expired">
                    <div class="stat-value"><?php echo esc_html($total_expired); ?></div>
                    <div class="stat-label"><?php _e('Expired', 'gps-courses'); ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="gps-waitlist-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="gps-waitlist">

                    <select name="event_id" id="gps-waitlist-event-filter">
                        <option value=""><?php _e('All Events', 'gps-courses'); ?></option>
                        <?php foreach ($events_with_waitlist as $event): ?>
                            <option value="<?php echo (int) $event->ID; ?>" <?php selected($filter_event, $event->ID); ?>>
                                <?php echo esc_html($event->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" id="gps-waitlist-status-filter">
                        <option value=""><?php _e('All Statuses', 'gps-courses'); ?></option>
                        <option value="waiting" <?php selected($filter_status, 'waiting'); ?>><?php _e('Waiting', 'gps-courses'); ?></option>
                        <option value="notified" <?php selected($filter_status, 'notified'); ?>><?php _e('Notified', 'gps-courses'); ?></option>
                        <option value="converted" <?php selected($filter_status, 'converted'); ?>><?php _e('Converted', 'gps-courses'); ?></option>
                        <option value="expired" <?php selected($filter_status, 'expired'); ?>><?php _e('Expired', 'gps-courses'); ?></option>
                        <option value="removed" <?php selected($filter_status, 'removed'); ?>><?php _e('Removed', 'gps-courses'); ?></option>
                    </select>

                    <button type="submit" class="button"><?php _e('Filter', 'gps-courses'); ?></button>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="gps-waitlist-bulk-actions">
                <select id="gps-waitlist-bulk-action">
                    <option value=""><?php _e('Bulk Actions', 'gps-courses'); ?></option>
                    <option value="remove"><?php _e('Remove Selected', 'gps-courses'); ?></option>
                    <option value="notify"><?php _e('Notify Selected', 'gps-courses'); ?></option>
                </select>
                <button type="button" id="gps-waitlist-bulk-apply" class="button"><?php _e('Apply', 'gps-courses'); ?></button>
            </div>

            <!-- Waitlist Table -->
            <table class="wp-list-table widefat fixed striped gps-waitlist-table">
                <thead>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" id="gps-waitlist-select-all">
                        </td>
                        <th style="width: 50px;"><?php _e('#', 'gps-courses'); ?></th>
                        <th><?php _e('Name', 'gps-courses'); ?></th>
                        <th><?php _e('Email', 'gps-courses'); ?></th>
                        <th><?php _e('Phone', 'gps-courses'); ?></th>
                        <th><?php _e('Event', 'gps-courses'); ?></th>
                        <th><?php _e('Ticket Type', 'gps-courses'); ?></th>
                        <th><?php _e('Status', 'gps-courses'); ?></th>
                        <th><?php _e('Date', 'gps-courses'); ?></th>
                        <th><?php _e('Actions', 'gps-courses'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 20px;">
                                <?php _e('No waitlist entries found.', 'gps-courses'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry):
                            $ticket = get_post($entry->ticket_type_id);
                            $event = get_post($entry->event_id);
                            $status_class = 'gps-status-' . $entry->status;
                            $full_name = trim($entry->first_name . ' ' . $entry->last_name) ?: '—';
                        ?>
                        <tr data-id="<?php echo (int) $entry->id; ?>">
                            <th class="check-column">
                                <input type="checkbox" class="gps-waitlist-checkbox" value="<?php echo (int) $entry->id; ?>">
                            </th>
                            <td>
                                <?php if ($entry->status === 'waiting'): ?>
                                    <span class="gps-position-badge"><?php echo (int) $entry->position; ?></span>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($full_name); ?></strong></td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($entry->email); ?>">
                                    <?php echo esc_html($entry->email); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($entry->phone ?: '—'); ?></td>
                            <td>
                                <?php if ($event): ?>
                                    <a href="<?php echo get_edit_post_link($event->ID); ?>">
                                        <?php echo esc_html($event->post_title); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;"><?php _e('Deleted', 'gps-courses'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ticket): ?>
                                    <?php echo esc_html($ticket->post_title); ?>
                                <?php else: ?>
                                    <span style="color: #999;"><?php _e('Deleted', 'gps-courses'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="gps-status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($entry->status)); ?>
                                </span>
                                <?php if ($entry->status === 'notified' && $entry->expires_at): ?>
                                    <br><small style="color: #856404;">
                                        <?php
                                        $expires = strtotime($entry->expires_at);
                                        $now = time();
                                        $remaining = $expires - $now;
                                        if ($remaining > 0) {
                                            $hours = floor($remaining / 3600);
                                            $minutes = floor(($remaining % 3600) / 60);
                                            printf(__('%dh %dm left', 'gps-courses'), $hours, $minutes);
                                        } else {
                                            _e('Expiring...', 'gps-courses');
                                        }
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->created_at))); ?>
                            </td>
                            <td>
                                <?php if ($entry->status === 'waiting'): ?>
                                    <button type="button" class="button button-small gps-waitlist-notify" data-id="<?php echo (int) $entry->id; ?>">
                                        <?php _e('Notify', 'gps-courses'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($entry->status === 'notified'): ?>
                                    <button type="button" class="button button-small gps-waitlist-converted" data-id="<?php echo (int) $entry->id; ?>">
                                        <?php _e('Converted', 'gps-courses'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if (in_array($entry->status, ['waiting', 'notified'])): ?>
                                    <button type="button" class="button button-small gps-waitlist-remove" data-id="<?php echo (int) $entry->id; ?>">
                                        <?php _e('Remove', 'gps-courses'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Legacy method for backwards compatibility
     * @deprecated Use notify_next_on_waitlist instead
     */
    public static function notify_waitlist($ticket_type_id, $event_id, $quantity = 1) {
        for ($i = 0; $i < $quantity; $i++) {
            self::notify_next_on_waitlist($ticket_type_id, $event_id);
        }
    }

    /**
     * Create waitlist table on activation
     * @deprecated Table is now created in class-activator.php
     */
    public static function create_table() {
        // Kept for backwards compatibility
        // Table creation is now handled by Activator::activate()
    }
}
