<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Waitlist Management
 * Handles waitlist signups and notifications when tickets become available
 */
class Waitlist {

    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_gps_join_waitlist', [__CLASS__, 'ajax_join_waitlist']);
        add_action('wp_ajax_nopriv_gps_join_waitlist', [__CLASS__, 'ajax_join_waitlist']);
    }

    /**
     * AJAX handler for joining waitlist
     */
    public static function ajax_join_waitlist() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gps_waitlist')) {
            wp_send_json_error(['message' => __('Security check failed.', 'gps-courses')]);
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
        $event_id = (int) ($_POST['event_id'] ?? 0);

        // Validate
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'gps-courses')]);
        }

        if (empty($ticket_id) || empty($event_id)) {
            wp_send_json_error(['message' => __('Invalid ticket or event.', 'gps-courses')]);
        }

        global $wpdb;

        // Check if already on waitlist
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gps_waitlist
            WHERE email = %s AND ticket_type_id = %d AND event_id = %d",
            $email,
            $ticket_id,
            $event_id
        ));

        if ($exists) {
            wp_send_json_success(['message' => __('You\'re already on the waitlist for this ticket!', 'gps-courses')]);
        }

        // Add to waitlist
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'gps_waitlist',
            [
                'email' => $email,
                'ticket_type_id' => $ticket_id,
                'event_id' => $event_id,
                'created_at' => current_time('mysql'),
                'status' => 'pending',
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );

        if ($inserted) {
            // Send confirmation email
            self::send_waitlist_confirmation($email, $ticket_id, $event_id);

            wp_send_json_success(['message' => __('Success! You\'ve been added to the waitlist. We\'ll notify you if tickets become available.', 'gps-courses')]);
        } else {
            error_log('GPS Courses: Failed to insert waitlist entry: ' . $wpdb->last_error);
            wp_send_json_error(['message' => __('Error adding to waitlist. Please try again.', 'gps-courses')]);
        }
    }

    /**
     * Send waitlist confirmation email
     */
    private static function send_waitlist_confirmation($email, $ticket_id, $event_id) {
        $ticket = get_post($ticket_id);
        $event = get_post($event_id);

        if (!$ticket || !$event) {
            return false;
        }

        $subject = sprintf(
            __('You\'re on the waitlist for %s', 'gps-courses'),
            $event->post_title
        );

        $message = sprintf(
            __('Thank you for joining the waitlist!<br><br>You\'ll be notified by email if tickets for <strong>%s</strong> become available.<br><br>Ticket Type: %s<br><br>We appreciate your interest!', 'gps-courses'),
            esc_html($event->post_title),
            esc_html($ticket->post_title)
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Notify waitlist when tickets become available
     * Called when an order is cancelled or refunded
     */
    public static function notify_waitlist($ticket_type_id, $event_id, $quantity = 1) {
        global $wpdb;

        // Get waitlist entries for this ticket type
        $waitlist = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_waitlist
            WHERE ticket_type_id = %d
            AND event_id = %d
            AND status = 'pending'
            ORDER BY created_at ASC
            LIMIT %d",
            $ticket_type_id,
            $event_id,
            $quantity
        ));

        if (empty($waitlist)) {
            return;
        }

        $ticket = get_post($ticket_type_id);
        $event = get_post($event_id);

        if (!$ticket || !$event) {
            return;
        }

        foreach ($waitlist as $entry) {
            // Send notification
            $subject = sprintf(
                __('Tickets Available: %s', 'gps-courses'),
                $event->post_title
            );

            $event_url = get_permalink($event_id);

            $message = sprintf(
                __('Great news!<br><br>Tickets are now available for <strong>%s</strong> - %s.<br><br><a href="%s" style="display: inline-block; padding: 12px 24px; background: #0d6efd; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Get Your Ticket Now</a><br><br>Tickets are limited and available on a first-come, first-served basis.', 'gps-courses'),
                esc_html($event->post_title),
                esc_html($ticket->post_title),
                esc_url($event_url)
            );

            $headers = ['Content-Type: text/html; charset=UTF-8'];

            if (wp_mail($entry->email, $subject, $message, $headers)) {
                // Mark as notified
                $wpdb->update(
                    $wpdb->prefix . 'gps_waitlist',
                    ['status' => 'notified', 'notified_at' => current_time('mysql')],
                    ['id' => $entry->id],
                    ['%s', '%s'],
                    ['%d']
                );

                error_log('GPS Courses: Waitlist notification sent to ' . $entry->email);
            }
        }
    }

    /**
     * Create waitlist table on activation
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gps_waitlist';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            ticket_type_id bigint(20) UNSIGNED NOT NULL,
            event_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL,
            notified_at datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            PRIMARY KEY  (id),
            KEY email (email),
            KEY ticket_type_id (ticket_type_id),
            KEY event_id (event_id),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
