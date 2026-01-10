<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Seminar Registrations Management
 *
 * Handles registration workflow, enrollment tracking,
 * and participant progress for Monthly Seminars.
 */
class Seminar_Registrations {

    /**
     * Initialize
     */
    public static function init() {
        // WooCommerce integration
        add_action('woocommerce_order_status_completed', [__CLASS__, 'process_seminar_order'], 20);

        // AJAX handlers
        add_action('wp_ajax_gps_get_user_progress', [__CLASS__, 'ajax_get_user_progress']);
        add_action('wp_ajax_gps_cancel_registration', [__CLASS__, 'ajax_cancel_registration']);
        add_action('wp_ajax_gps_export_registrants', [__CLASS__, 'ajax_export_registrants']);
    }

    /**
     * Create a seminar registration
     *
     * @param int $user_id
     * @param int $seminar_id
     * @param int $order_id
     * @return int|false Registration ID or false on failure
     */
    public static function create_registration($user_id, $seminar_id, $order_id = null) {
        global $wpdb;

        // Check if already registered
        $existing = self::get_user_registration($user_id, $seminar_id);
        if ($existing) {
            return $existing->id;
        }

        // Get next session date
        $next_session = Seminars::get_next_session($seminar_id);
        $start_session_date = $next_session ? $next_session->session_date : null;

        // Generate QR code
        $qr_code = self::generate_qr_code($user_id, $seminar_id);

        // Insert registration
        $result = $wpdb->insert(
            $wpdb->prefix . 'gps_seminar_registrations',
            [
                'user_id' => $user_id,
                'seminar_id' => $seminar_id,
                'order_id' => $order_id,
                'registration_date' => current_time('mysql'),
                'start_session_date' => $start_session_date,
                'sessions_completed' => 0,
                'sessions_remaining' => 10,
                'makeup_used' => 0,
                'status' => 'active',
                'qr_code' => $qr_code,
                'qr_scan_count' => 0,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d']
        );

        if ($result) {
            $registration_id = $wpdb->insert_id;

            // Generate QR code image
            $qr_path = self::generate_qr_image($registration_id, $qr_code);
            if ($qr_path) {
                $wpdb->update(
                    $wpdb->prefix . 'gps_seminar_registrations',
                    ['qr_code_path' => $qr_path],
                    ['id' => $registration_id],
                    ['%s'],
                    ['%d']
                );
            }

            // Trigger action for email notification
            do_action('gps_seminar_registered', $registration_id, $user_id, $seminar_id, $order_id);

            return $registration_id;
        }

        return false;
    }

    /**
     * Get registration by ID
     */
    public static function get_registration($registration_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_registrations WHERE id = %d",
            $registration_id
        ));
    }

    /**
     * Get user's registration for a seminar
     */
    public static function get_user_registration($user_id, $seminar_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_registrations
             WHERE user_id = %d AND seminar_id = %d AND status IN ('active', 'completed')
             ORDER BY registration_date DESC LIMIT 1",
            $user_id,
            $seminar_id
        ));
    }

    /**
     * Get all registrations for a seminar
     */
    public static function get_seminar_registrations($seminar_id, $status = null) {
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}gps_seminar_registrations WHERE seminar_id = %d";
        $params = [$seminar_id];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY registration_date DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get user's active registrations
     */
    public static function get_user_registrations($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_registrations
             WHERE user_id = %d AND status IN ('active', 'completed')
             ORDER BY registration_date DESC",
            $user_id
        ));
    }

    /**
     * Update registration
     */
    public static function update_registration($registration_id, $data) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'gps_seminar_registrations',
            $data,
            ['id' => $registration_id],
            null,
            ['%d']
        );
    }

    /**
     * Update session counts after check-in
     */
    public static function update_session_counts($registration_id) {
        global $wpdb;

        $registration = self::get_registration($registration_id);
        if (!$registration) {
            return false;
        }

        $completed = $registration->sessions_completed + 1;
        $remaining = max(0, 10 - $completed);
        $status = $remaining === 0 ? 'completed' : 'active';

        return $wpdb->update(
            $wpdb->prefix . 'gps_seminar_registrations',
            [
                'sessions_completed' => $completed,
                'sessions_remaining' => $remaining,
                'status' => $status,
            ],
            ['id' => $registration_id],
            ['%d', '%d', '%s'],
            ['%d']
        );
    }

    /**
     * Mark makeup session as used
     */
    public static function use_makeup($registration_id) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'gps_seminar_registrations',
            ['makeup_used' => 1],
            ['id' => $registration_id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Cancel registration
     */
    public static function cancel_registration($registration_id, $reason = '') {
        global $wpdb;

        // Get registration info before cancelling
        $registration = self::get_registration($registration_id);
        if (!$registration) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'gps_seminar_registrations',
            [
                'status' => 'cancelled',
                'notes' => $reason,
            ],
            ['id' => $registration_id],
            ['%s', '%s'],
            ['%d']
        );

        // If cancellation successful, notify next person on waitlist
        if ($result !== false) {
            Seminar_Waitlist::notify_next_on_waitlist($registration->seminar_id);
            error_log("GPS Seminars: Registration cancelled - ID: $registration_id, Notifying waitlist for seminar {$registration->seminar_id}");
        }

        return $result;
    }

    /**
     * Get user progress details
     */
    public static function get_user_progress($registration_id) {
        global $wpdb;

        $registration = self::get_registration($registration_id);
        if (!$registration) {
            return null;
        }

        // Get attendance history
        $attendance = $wpdb->get_results($wpdb->prepare(
            "SELECT sa.*, ss.session_number, ss.session_date, ss.topic
             FROM {$wpdb->prefix}gps_seminar_attendance sa
             INNER JOIN {$wpdb->prefix}gps_seminar_sessions ss ON sa.session_id = ss.id
             WHERE sa.registration_id = %d
             ORDER BY ss.session_date ASC",
            $registration_id
        ));

        // Get total CE credits earned
        $total_credits = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(credits_awarded) FROM {$wpdb->prefix}gps_seminar_attendance
             WHERE registration_id = %d",
            $registration_id
        ));

        // Get next session
        $next_session = Seminars::get_next_session($registration->seminar_id);

        return [
            'registration' => $registration,
            'attendance' => $attendance,
            'total_credits' => (int) $total_credits,
            'next_session' => $next_session,
            'completion_percentage' => ($registration->sessions_completed / 10) * 100,
        ];
    }

    /**
     * Generate unique QR code for registration
     */
    private static function generate_qr_code($user_id, $seminar_id) {
        $timestamp = time();
        $random = wp_generate_password(8, false);
        return sprintf('GPSS-%d-%d-%d-%s', $user_id, $seminar_id, $timestamp, $random);
    }

    /**
     * Generate QR code image
     */
    private static function generate_qr_image($registration_id, $qr_code) {
        $registration = self::get_registration($registration_id);
        if (!$registration) {
            return false;
        }

        // Prepare data array for QR code generation
        $data = [
            'type' => 'seminar',
            'registration_id' => $registration_id,
            'user_id' => $registration->user_id,
            'seminar_id' => $registration->seminar_id,
            'event_id' => $registration->seminar_id, // For compatibility with QRCodeGenerator
        ];

        // Generate QR code image using existing generator
        // Parameters: ticket_code, ticket_id, data
        $qr_path = QRCodeGenerator::generate_qr_code($qr_code, $registration_id, $data);

        return $qr_path;
    }

    /**
     * Generate verification hash
     */
    private static function generate_verification_hash($registration_id) {
        $registration = self::get_registration($registration_id);
        if (!$registration) {
            return '';
        }

        return hash_hmac(
            'sha256',
            $registration->id . $registration->user_id . $registration->seminar_id,
            wp_salt('auth')
        );
    }

    /**
     * Verify QR code
     */
    public static function verify_qr_code($qr_data) {
        if (!isset($qr_data['registration_id'], $qr_data['hash'])) {
            return false;
        }

        $expected_hash = self::generate_verification_hash($qr_data['registration_id']);
        return hash_equals($expected_hash, $qr_data['hash']);
    }

    /**
     * Process seminar order from WooCommerce
     */
    public static function process_seminar_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if already processed
        if ($order->get_meta('_gps_seminar_processed')) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            $user_id = 0; // Guest checkout
        }

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            // Check if this is a seminar product
            $seminar_id = self::get_seminar_by_product($product_id);
            if ($seminar_id) {
                // Create registration
                $registration_id = self::create_registration($user_id, $seminar_id, $order_id);

                if ($registration_id) {
                    error_log("GPS Seminars: Created registration #{$registration_id} for order #{$order_id}");
                }
            }
        }

        // Mark as processed
        $order->update_meta_data('_gps_seminar_processed', true);
        $order->save();
    }

    /**
     * Get seminar ID by WooCommerce product ID
     */
    private static function get_seminar_by_product($product_id) {
        $seminars = get_posts([
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'meta_key' => '_gps_seminar_product_id',
            'meta_value' => $product_id,
            'posts_per_page' => 1,
        ]);

        return !empty($seminars) ? $seminars[0]->ID : null;
    }

    /**
     * AJAX: Get user progress
     */
    public static function ajax_get_user_progress() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        $registration_id = (int) $_POST['registration_id'];
        $progress = self::get_user_progress($registration_id);

        if ($progress) {
            wp_send_json_success($progress);
        } else {
            wp_send_json_error(['message' => __('Registration not found', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Cancel registration
     */
    public static function ajax_cancel_registration() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $registration_id = (int) $_POST['registration_id'];
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        $result = self::cancel_registration($registration_id, $reason);

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => __('Failed to cancel registration', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Export registrants
     */
    public static function ajax_export_registrants() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'gps-courses'));
        }

        $seminar_id = (int) $_POST['seminar_id'];
        $registrations = self::get_seminar_registrations($seminar_id);

        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="seminar-registrants-' . $seminar_id . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Registration Date', 'Sessions Completed', 'Sessions Remaining', 'CEs Earned', 'Status']);

        foreach ($registrations as $reg) {
            $user = get_userdata($reg->user_id);
            $progress = self::get_user_progress($reg->id);

            fputcsv($output, [
                $user ? $user->display_name : 'Guest',
                $user ? $user->user_email : '',
                $reg->registration_date,
                $reg->sessions_completed,
                $reg->sessions_remaining,
                $progress['total_credits'],
                $reg->status,
            ]);
        }

        fclose($output);
        exit;
    }
}
