<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Seminar Waitlist Management
 * Handles waitlist functionality when seminars reach capacity
 */
class Seminar_Waitlist {

    /**
     * Initialize waitlist functionality
     */
    public static function init() {
        // WooCommerce hooks for capacity checking
        add_filter('woocommerce_is_purchasable', [__CLASS__, 'check_seminar_capacity'], 10, 2);
        add_filter('woocommerce_get_availability', [__CLASS__, 'show_waitlist_availability'], 10, 2);

        // Add waitlist button after add to cart
        add_action('woocommerce_after_add_to_cart_button', [__CLASS__, 'show_waitlist_button']);

        // Handle waitlist form submission
        add_action('wp_ajax_gps_join_seminar_waitlist', [__CLASS__, 'ajax_join_waitlist']);
        add_action('wp_ajax_nopriv_gps_join_seminar_waitlist', [__CLASS__, 'ajax_join_waitlist']);

        // Handle admin removal from waitlist
        add_action('wp_ajax_gps_remove_from_waitlist', [__CLASS__, 'ajax_remove_from_waitlist']);

        // Handle admin notification to waitlist
        add_action('wp_ajax_gps_notify_waitlist', [__CLASS__, 'ajax_notify_waitlist']);

        // Cron job to handle expired notifications
        add_action('gps_process_expired_waitlist', [__CLASS__, 'process_expired_notifications']);

        // Register cron schedule if not exists
        if (!wp_next_scheduled('gps_process_expired_waitlist')) {
            wp_schedule_event(time(), 'hourly', 'gps_process_expired_waitlist');
        }
    }

    /**
     * Add user to waitlist
     */
    public static function add_to_waitlist($seminar_id, $user_id, $email = '', $first_name = '', $last_name = '', $phone = '') {
        global $wpdb;

        // Get user info if user_id provided
        if ($user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                $email = $email ?: $user->user_email;
                $first_name = $first_name ?: $user->first_name;
                $last_name = $last_name ?: $user->last_name;
            }
        }

        // Validate email
        if (!is_email($email)) {
            return new \WP_Error('invalid_email', __('Invalid email address.', 'gps-courses'));
        }

        // Check if already on waitlist
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gps_seminar_waitlist
             WHERE seminar_id = %d AND email = %s AND status = 'waiting'",
            $seminar_id,
            $email
        ));

        if ($exists) {
            return new \WP_Error('already_waitlisted', __('You are already on the waitlist for this seminar.', 'gps-courses'));
        }

        // Check if already registered
        $registered = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gps_seminar_registrations
             WHERE seminar_id = %d AND user_id = %d AND status IN ('active', 'completed')",
            $seminar_id,
            $user_id
        ));

        if ($registered) {
            return new \WP_Error('already_registered', __('You are already registered for this seminar.', 'gps-courses'));
        }

        // Get next position in waitlist
        $position = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 FROM {$wpdb->prefix}gps_seminar_waitlist
             WHERE seminar_id = %d",
            $seminar_id
        ));

        // Insert into waitlist
        $result = $wpdb->insert(
            $wpdb->prefix . 'gps_seminar_waitlist',
            [
                'seminar_id' => $seminar_id,
                'user_id' => $user_id,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'position' => $position,
                'status' => 'waiting',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if (!$result) {
            error_log('GPS Seminars: Failed to add to waitlist - ' . $wpdb->last_error);
            return new \WP_Error('db_error', __('Failed to add to waitlist. Please try again.', 'gps-courses'));
        }

        $waitlist_id = $wpdb->insert_id;

        // Send confirmation email
        self::send_waitlist_confirmation($waitlist_id);

        // Log the action
        error_log("GPS Seminars: Added to waitlist - ID: $waitlist_id, Seminar: $seminar_id, Email: $email, Position: $position");

        return $waitlist_id;
    }

    /**
     * Remove from waitlist
     */
    public static function remove_from_waitlist($waitlist_id, $reason = 'removed') {
        global $wpdb;

        $entry = self::get_waitlist_entry($waitlist_id);
        if (!$entry) {
            return false;
        }

        // Update status
        $result = $wpdb->update(
            $wpdb->prefix . 'gps_seminar_waitlist',
            [
                'status' => $reason, // 'removed', 'converted', 'expired'
            ],
            ['id' => $waitlist_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            // Reorder positions for remaining waitlist entries
            self::reorder_waitlist($entry->seminar_id);

            error_log("GPS Seminars: Removed from waitlist - ID: $waitlist_id, Reason: $reason");
            return true;
        }

        return false;
    }

    /**
     * Notify next person on waitlist
     */
    public static function notify_next_on_waitlist($seminar_id) {
        global $wpdb;

        // Get next person on waitlist
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_waitlist
             WHERE seminar_id = %d AND status = 'waiting'
             ORDER BY position ASC
             LIMIT 1",
            $seminar_id
        ));

        if (!$entry) {
            error_log("GPS Seminars: No waitlist entries found for seminar $seminar_id");
            return false;
        }

        // Send notification email with 48-hour expiration
        $expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $wpdb->update(
            $wpdb->prefix . 'gps_seminar_waitlist',
            [
                'status' => 'notified',
                'notified_at' => current_time('mysql'),
                'expires_at' => $expires_at,
            ],
            ['id' => $entry->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        // Send notification email
        self::send_spot_available_notification($entry->id);

        error_log("GPS Seminars: Notified waitlist entry {$entry->id} - Email: {$entry->email}, Expires: $expires_at");

        return $entry->id;
    }

    /**
     * Get waitlist entry
     */
    public static function get_waitlist_entry($waitlist_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_waitlist WHERE id = %d",
            $waitlist_id
        ));
    }

    /**
     * Get waitlist for seminar
     */
    public static function get_seminar_waitlist($seminar_id, $status = 'waiting') {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}gps_seminar_waitlist
                  WHERE seminar_id = %d";

        $params = [$seminar_id];

        if ($status) {
            $query .= " AND status = %s";
            $params[] = $status;
        }

        $query .= " ORDER BY position ASC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get waitlist count
     */
    public static function get_waitlist_count($seminar_id, $status = 'waiting') {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_waitlist
             WHERE seminar_id = %d AND status = %s",
            $seminar_id,
            $status
        ));
    }

    /**
     * Reorder waitlist positions after removal
     */
    private static function reorder_waitlist($seminar_id) {
        global $wpdb;

        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gps_seminar_waitlist
             WHERE seminar_id = %d AND status = 'waiting'
             ORDER BY position ASC",
            $seminar_id
        ));

        $position = 1;
        foreach ($entries as $entry) {
            $wpdb->update(
                $wpdb->prefix . 'gps_seminar_waitlist',
                ['position' => $position],
                ['id' => $entry->id],
                ['%d'],
                ['%d']
            );
            $position++;
        }
    }

    /**
     * Process expired waitlist notifications
     */
    public static function process_expired_notifications() {
        global $wpdb;

        $expired = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_waitlist
             WHERE status = 'notified'
             AND expires_at IS NOT NULL
             AND expires_at < NOW()"
        );

        foreach ($expired as $entry) {
            // Mark as expired
            self::remove_from_waitlist($entry->id, 'expired');

            // Notify next person
            self::notify_next_on_waitlist($entry->seminar_id);

            error_log("GPS Seminars: Expired waitlist notification - ID: {$entry->id}, Email: {$entry->email}");
        }
    }

    /**
     * Check if seminar is at capacity
     */
    public static function is_at_capacity($seminar_id) {
        $capacity = (int) get_post_meta($seminar_id, '_gps_seminar_capacity', true) ?: 50;
        $enrolled = Seminars::get_enrollment_count($seminar_id);

        return $enrolled >= $capacity;
    }

    /**
     * Check seminar capacity for WooCommerce
     */
    public static function check_seminar_capacity($purchasable, $product) {
        // Check if product is linked to a seminar
        $seminar_id = get_post_meta($product->get_id(), '_gps_linked_seminar', true);

        if (!$seminar_id) {
            return $purchasable;
        }

        // Check if at capacity
        if (self::is_at_capacity($seminar_id)) {
            return false;
        }

        return $purchasable;
    }

    /**
     * Show waitlist availability message
     */
    public static function show_waitlist_availability($availability, $product) {
        $seminar_id = get_post_meta($product->get_id(), '_gps_linked_seminar', true);

        if (!$seminar_id) {
            return $availability;
        }

        if (self::is_at_capacity($seminar_id)) {
            $waitlist_count = self::get_waitlist_count($seminar_id);
            $availability['availability'] = sprintf(
                __('Seminar Full - %d on waitlist', 'gps-courses'),
                $waitlist_count
            );
            $availability['class'] = 'out-of-stock';
        }

        return $availability;
    }

    /**
     * Show waitlist button on product page
     */
    public static function show_waitlist_button() {
        global $product;

        $seminar_id = get_post_meta($product->get_id(), '_gps_linked_seminar', true);

        if (!$seminar_id || !self::is_at_capacity($seminar_id)) {
            return;
        }

        $user_id = get_current_user_id();
        $email = $user_id ? wp_get_current_user()->user_email : '';

        // Check if already on waitlist
        if ($email && self::is_on_waitlist($seminar_id, $email)) {
            $position = self::get_waitlist_position($seminar_id, $email);
            echo '<div class="gps-waitlist-status">';
            echo '<p>' . sprintf(__('You are on the waitlist (Position: %d)', 'gps-courses'), $position) . '</p>';
            echo '</div>';
            return;
        }

        ?>
        <div class="gps-waitlist-section">
            <button type="button" class="button alt gps-join-waitlist-btn" data-seminar-id="<?php echo esc_attr($seminar_id); ?>">
                <?php _e('Join Waitlist', 'gps-courses'); ?>
            </button>

            <div class="gps-waitlist-form" style="display: none;">
                <h3><?php _e('Join Waitlist', 'gps-courses'); ?></h3>
                <p><?php _e('Enter your information to be notified when a spot becomes available.', 'gps-courses'); ?></p>

                <?php if (!$user_id): ?>
                    <p>
                        <label for="waitlist_email"><?php _e('Email', 'gps-courses'); ?> *</label>
                        <input type="email" id="waitlist_email" name="waitlist_email" required>
                    </p>
                    <p>
                        <label for="waitlist_first_name"><?php _e('First Name', 'gps-courses'); ?></label>
                        <input type="text" id="waitlist_first_name" name="waitlist_first_name">
                    </p>
                    <p>
                        <label for="waitlist_last_name"><?php _e('Last Name', 'gps-courses'); ?></label>
                        <input type="text" id="waitlist_last_name" name="waitlist_last_name">
                    </p>
                    <p>
                        <label for="waitlist_phone"><?php _e('Phone', 'gps-courses'); ?></label>
                        <input type="tel" id="waitlist_phone" name="waitlist_phone">
                    </p>
                <?php else: ?>
                    <input type="hidden" id="waitlist_email" value="<?php echo esc_attr($email); ?>">
                <?php endif; ?>

                <p>
                    <button type="button" class="button alt gps-submit-waitlist">
                        <?php _e('Submit', 'gps-courses'); ?>
                    </button>
                    <button type="button" class="button gps-cancel-waitlist">
                        <?php _e('Cancel', 'gps-courses'); ?>
                    </button>
                </p>

                <div class="gps-waitlist-message"></div>
            </div>
        </div>

        <style>
            .gps-waitlist-section {
                margin-top: 20px;
            }

            .gps-waitlist-form {
                margin-top: 20px;
                padding: 20px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .gps-waitlist-form h3 {
                margin-top: 0;
            }

            .gps-waitlist-form p {
                margin-bottom: 15px;
            }

            .gps-waitlist-form label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
            }

            .gps-waitlist-form input[type="text"],
            .gps-waitlist-form input[type="email"],
            .gps-waitlist-form input[type="tel"] {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .gps-waitlist-message {
                margin-top: 15px;
                padding: 10px;
                border-radius: 4px;
            }

            .gps-waitlist-message.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .gps-waitlist-message.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .gps-waitlist-status {
                margin-top: 20px;
                padding: 15px;
                background: #e5f5fa;
                border: 1px solid #bee5eb;
                border-radius: 4px;
                color: #00527c;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.gps-join-waitlist-btn').on('click', function() {
                $(this).hide();
                $('.gps-waitlist-form').slideDown();
            });

            $('.gps-cancel-waitlist').on('click', function() {
                $('.gps-waitlist-form').slideUp();
                $('.gps-join-waitlist-btn').show();
            });

            $('.gps-submit-waitlist').on('click', function() {
                const $btn = $(this);
                const $form = $('.gps-waitlist-form');
                const $message = $('.gps-waitlist-message');
                const seminarId = $('.gps-join-waitlist-btn').data('seminar-id');

                $btn.prop('disabled', true).text('<?php _e('Submitting...', 'gps-courses'); ?>');
                $message.removeClass('success error').empty();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'gps_join_seminar_waitlist',
                        seminar_id: seminarId,
                        email: $('#waitlist_email').val(),
                        first_name: $('#waitlist_first_name').val() || '',
                        last_name: $('#waitlist_last_name').val() || '',
                        phone: $('#waitlist_phone').val() || '',
                        nonce: '<?php echo wp_create_nonce('gps_waitlist_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.addClass('success').html(response.data.message);
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $message.addClass('error').html(response.data.message);
                            $btn.prop('disabled', false).text('<?php _e('Submit', 'gps-courses'); ?>');
                        }
                    },
                    error: function() {
                        $message.addClass('error').html('<?php _e('An error occurred. Please try again.', 'gps-courses'); ?>');
                        $btn.prop('disabled', false).text('<?php _e('Submit', 'gps-courses'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for joining waitlist
     */
    public static function ajax_join_waitlist() {
        check_ajax_referer('gps_waitlist_nonce', 'nonce');

        $seminar_id = isset($_POST['seminar_id']) ? intval($_POST['seminar_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

        $user_id = get_current_user_id();

        if (!$seminar_id || !$email) {
            wp_send_json_error([
                'message' => __('Missing required information.', 'gps-courses')
            ]);
        }

        $result = self::add_to_waitlist($seminar_id, $user_id, $email, $first_name, $last_name, $phone);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        $position = self::get_waitlist_position($seminar_id, $email);

        wp_send_json_success([
            'message' => sprintf(
                __('You have been added to the waitlist (Position: %d). We will notify you when a spot becomes available.', 'gps-courses'),
                $position
            ),
            'waitlist_id' => $result,
            'position' => $position
        ]);
    }

    /**
     * AJAX handler for removing from waitlist
     */
    public static function ajax_remove_from_waitlist() {
        check_ajax_referer('gps_courses_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'gps-courses')]);
        }

        $waitlist_id = isset($_POST['waitlist_id']) ? intval($_POST['waitlist_id']) : 0;

        if (!$waitlist_id) {
            wp_send_json_error(['message' => __('Invalid waitlist entry.', 'gps-courses')]);
        }

        $result = self::remove_from_waitlist($waitlist_id, 'removed');

        if ($result) {
            wp_send_json_success(['message' => __('Removed from waitlist.', 'gps-courses')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove from waitlist.', 'gps-courses')]);
        }
    }

    /**
     * AJAX handler for notifying waitlist
     */
    public static function ajax_notify_waitlist() {
        check_ajax_referer('gps_courses_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'gps-courses')]);
        }

        $seminar_id = isset($_POST['seminar_id']) ? intval($_POST['seminar_id']) : 0;

        if (!$seminar_id) {
            wp_send_json_error(['message' => __('Invalid seminar.', 'gps-courses')]);
        }

        $result = self::notify_next_on_waitlist($seminar_id);

        if ($result) {
            wp_send_json_success(['message' => __('Notification sent to next person on waitlist.', 'gps-courses')]);
        } else {
            wp_send_json_error(['message' => __('No one on waitlist to notify.', 'gps-courses')]);
        }
    }

    /**
     * Check if email is on waitlist
     */
    private static function is_on_waitlist($seminar_id, $email) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gps_seminar_waitlist
             WHERE seminar_id = %d AND email = %s AND status IN ('waiting', 'notified')",
            $seminar_id,
            $email
        ));
    }

    /**
     * Get waitlist position
     */
    private static function get_waitlist_position($seminar_id, $email) {
        global $wpdb;

        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT position FROM {$wpdb->prefix}gps_seminar_waitlist
             WHERE seminar_id = %d AND email = %s AND status = 'waiting'",
            $seminar_id,
            $email
        ));

        return $entry ? $entry->position : 0;
    }

    /**
     * Send waitlist confirmation email
     */
    private static function send_waitlist_confirmation($waitlist_id) {
        $entry = self::get_waitlist_entry($waitlist_id);
        if (!$entry) {
            return false;
        }

        $seminar = get_post($entry->seminar_id);
        $year = get_post_meta($entry->seminar_id, '_gps_seminar_year', true);

        $to = $entry->email;
        $subject = sprintf(__('Waitlist Confirmation - %s', 'gps-courses'), $seminar->post_title);

        $message = sprintf(
            __("Hello %s,\n\nYou have been added to the waitlist for:\n\n%s (%s)\n\nYour position: %d\n\nWe will notify you by email if a spot becomes available. You will have 48 hours to complete your registration once notified.\n\nThank you for your interest!\n\nGPS Dental Training", 'gps-courses'),
            $entry->first_name ?: 'there',
            $seminar->post_title,
            $year,
            $entry->position
        );

        return Seminar_Notifications::send_email($to, $subject, $message);
    }

    /**
     * Send spot available notification
     */
    private static function send_spot_available_notification($waitlist_id) {
        $entry = self::get_waitlist_entry($waitlist_id);
        if (!$entry) {
            return false;
        }

        $seminar = get_post($entry->seminar_id);
        $year = get_post_meta($entry->seminar_id, '_gps_seminar_year', true);
        $product_id = get_post_meta($entry->seminar_id, '_gps_seminar_product_id', true);
        $product_url = $product_id ? get_permalink($product_id) : '';

        $expires = date('F j, Y g:i A', strtotime($entry->expires_at));

        $to = $entry->email;
        $subject = sprintf(__('Spot Available - %s', 'gps-courses'), $seminar->post_title);

        $message = sprintf(
            __("Hello %s,\n\nGreat news! A spot has become available in:\n\n%s (%s)\n\nYou have until %s to complete your registration.\n\n%s\n\nIf you do not register within 48 hours, the spot will be offered to the next person on the waitlist.\n\nThank you!\n\nGPS Dental Training", 'gps-courses'),
            $entry->first_name ?: 'there',
            $seminar->post_title,
            $year,
            $expires,
            $product_url ? "Register here: $product_url" : ''
        );

        return Seminar_Notifications::send_email($to, $subject, $message);
    }
}
