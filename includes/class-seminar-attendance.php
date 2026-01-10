<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Seminar Attendance Management
 *
 * Handles QR code scanning, session check-ins, makeup tracking,
 * and CE credits awarding for Monthly Seminars.
 */
class Seminar_Attendance {

    /**
     * Initialize
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_gps_scan_seminar_qr', [__CLASS__, 'ajax_scan_qr']);
        add_action('wp_ajax_gps_manual_seminar_checkin', [__CLASS__, 'ajax_manual_checkin']);
        add_action('wp_ajax_gps_get_session_attendance', [__CLASS__, 'ajax_get_session_attendance']);
    }

    /**
     * Check in a participant via QR code scan
     *
     * @param string $qr_data JSON decoded QR data
     * @param int $session_id Session being checked into
     * @return array Result with success status and message
     */
    public static function check_in($qr_data, $session_id) {
        global $wpdb;

        // Verify QR code
        if (!Seminar_Registrations::verify_qr_code($qr_data)) {
            return [
                'success' => false,
                'message' => __('Invalid QR code', 'gps-courses'),
            ];
        }

        $registration_id = $qr_data['registration_id'];
        $registration = Seminar_Registrations::get_registration($registration_id);

        if (!$registration) {
            return [
                'success' => false,
                'message' => __('Registration not found', 'gps-courses'),
            ];
        }

        // Check registration status
        if ($registration->status !== 'active') {
            return [
                'success' => false,
                'message' => __('Registration is not active', 'gps-courses'),
            ];
        }

        // Check if already completed all sessions
        if ($registration->sessions_remaining <= 0) {
            return [
                'success' => false,
                'message' => __('All sessions completed', 'gps-courses'),
            ];
        }

        // Check QR scan count (max 10)
        if ($registration->qr_scan_count >= 10) {
            return [
                'success' => false,
                'message' => __('Maximum sessions (10) already attended', 'gps-courses'),
            ];
        }

        // Get session details
        $session = self::get_session($session_id);
        if (!$session) {
            return [
                'success' => false,
                'message' => __('Session not found', 'gps-courses'),
            ];
        }

        // Check if session belongs to the same seminar
        if ($session->seminar_id != $registration->seminar_id) {
            return [
                'success' => false,
                'message' => __('Session does not belong to your seminar', 'gps-courses'),
            ];
        }

        // Check if already checked in to this session
        $already_checked = self::get_attendance($registration_id, $session_id);
        if ($already_checked) {
            return [
                'success' => false,
                'message' => __('Already checked in to this session', 'gps-courses'),
            ];
        }

        // Determine if this is a makeup session
        $is_makeup = self::is_makeup_session($registration, $session);

        // If makeup, check if already used
        if ($is_makeup && $registration->makeup_used >= 1) {
            return [
                'success' => false,
                'message' => __('Makeup session already used this year', 'gps-courses'),
            ];
        }

        // Create attendance record
        $attendance_id = self::create_attendance([
            'registration_id' => $registration_id,
            'session_id' => $session_id,
            'user_id' => $registration->user_id,
            'seminar_id' => $registration->seminar_id,
            'attended' => 1,
            'checked_in_at' => current_time('mysql'),
            'checked_in_by' => get_current_user_id(),
            'is_makeup' => $is_makeup ? 1 : 0,
            'credits_awarded' => 2,
        ]);

        if (!$attendance_id) {
            return [
                'success' => false,
                'message' => __('Failed to create attendance record', 'gps-courses'),
            ];
        }

        // Update registration counts
        Seminar_Registrations::update_session_counts($registration_id);

        // Increment QR scan count
        $wpdb->update(
            $wpdb->prefix . 'gps_seminar_registrations',
            ['qr_scan_count' => $registration->qr_scan_count + 1],
            ['id' => $registration_id],
            ['%d'],
            ['%d']
        );

        // If makeup, mark as used
        if ($is_makeup) {
            Seminar_Registrations::use_makeup($registration_id);
        }

        // Award CE credits (2 per session)
        self::award_ce_credits($registration->user_id, $registration->seminar_id, $session_id, 2);

        // Get updated user info
        $user = get_userdata($registration->user_id);

        return [
            'success' => true,
            'message' => __('Check-in successful', 'gps-courses'),
            'data' => [
                'attendance_id' => $attendance_id,
                'user_name' => $user ? $user->display_name : 'Guest',
                'sessions_completed' => $registration->sessions_completed + 1,
                'sessions_remaining' => max(0, $registration->sessions_remaining - 1),
                'credits_awarded' => 2,
                'is_makeup' => $is_makeup,
            ],
        ];
    }

    /**
     * Manual check-in (admin only)
     */
    public static function manual_checkin($registration_id, $session_id, $is_makeup = false) {
        $registration = Seminar_Registrations::get_registration($registration_id);

        if (!$registration) {
            return [
                'success' => false,
                'message' => __('Registration not found', 'gps-courses'),
            ];
        }

        // Check if already checked in
        $already_checked = self::get_attendance($registration_id, $session_id);
        if ($already_checked) {
            return [
                'success' => false,
                'message' => __('Already checked in to this session', 'gps-courses'),
            ];
        }

        // Create attendance record
        $attendance_id = self::create_attendance([
            'registration_id' => $registration_id,
            'session_id' => $session_id,
            'user_id' => $registration->user_id,
            'seminar_id' => $registration->seminar_id,
            'attended' => 1,
            'checked_in_at' => current_time('mysql'),
            'checked_in_by' => get_current_user_id(),
            'is_makeup' => $is_makeup ? 1 : 0,
            'credits_awarded' => 2,
            'notes' => 'Manual check-in',
        ]);

        if ($attendance_id) {
            // Update counts
            Seminar_Registrations::update_session_counts($registration_id);

            // Award credits
            self::award_ce_credits($registration->user_id, $registration->seminar_id, $session_id, 2);

            // Mark makeup if applicable
            if ($is_makeup) {
                Seminar_Registrations::use_makeup($registration_id);
            }

            return [
                'success' => true,
                'message' => __('Manual check-in successful', 'gps-courses'),
                'attendance_id' => $attendance_id,
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to create attendance record', 'gps-courses'),
        ];
    }

    /**
     * Create attendance record
     */
    private static function create_attendance($data) {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'gps_seminar_attendance',
            $data,
            ['%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get attendance record
     */
    public static function get_attendance($registration_id, $session_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_attendance
             WHERE registration_id = %d AND session_id = %d",
            $registration_id,
            $session_id
        ));
    }

    /**
     * Get all attendance for a session
     */
    public static function get_session_attendance($session_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT sa.*, sr.user_id, sr.qr_code
             FROM {$wpdb->prefix}gps_seminar_attendance sa
             INNER JOIN {$wpdb->prefix}gps_seminar_registrations sr ON sa.registration_id = sr.id
             WHERE sa.session_id = %d
             ORDER BY sa.checked_in_at DESC",
            $session_id
        ));
    }

    /**
     * Get session details
     */
    private static function get_session($session_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
            $session_id
        ));
    }

    /**
     * Check if this is a makeup session
     * (participant missed their regular session and attending another)
     */
    private static function is_makeup_session($registration, $session) {
        // For simplicity, we'll consider it a makeup if:
        // 1. Session date is after their start date
        // 2. They haven't attended 10 sessions yet
        // This is a simplified logic - you may want to add more sophisticated tracking

        return false; // Admin will manually mark makeup sessions
    }

    /**
     * Award CE credits
     */
    private static function award_ce_credits($user_id, $seminar_id, $session_id, $credits) {
        global $wpdb;

        // Get session info for notes
        $session = self::get_session($session_id);
        $notes = sprintf(
            'Seminar Session #%d - %s',
            $session->session_number ?? 0,
            $session->topic ?? 'Session'
        );

        // Insert into CE ledger
        $wpdb->insert(
            $wpdb->prefix . 'gps_ce_ledger',
            [
                'user_id' => $user_id,
                'event_id' => $seminar_id,
                'credits' => $credits,
                'source' => 'seminar',
                'transaction_type' => 'attendance',
                'notes' => $notes,
                'awarded_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        // Trigger action for notification
        do_action('gps_seminar_credits_awarded', $user_id, $seminar_id, $session_id, $credits);
    }

    /**
     * Get attendance statistics for a session
     */
    public static function get_session_stats($session_id) {
        global $wpdb;

        $session = self::get_session($session_id);
        if (!$session) {
            return null;
        }

        $total_registrants = Seminars::get_enrollment_count($session->seminar_id);
        $checked_in = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_attendance WHERE session_id = %d",
            $session_id
        ));

        return [
            'session' => $session,
            'total_registrants' => $total_registrants,
            'checked_in' => $checked_in,
            'not_checked_in' => max(0, $total_registrants - $checked_in),
            'attendance_rate' => $total_registrants > 0 ? round(($checked_in / $total_registrants) * 100, 2) : 0,
        ];
    }

    /**
     * Get registrants not yet checked in for a session
     */
    public static function get_unchecked_registrants($session_id) {
        global $wpdb;

        $session = self::get_session($session_id);
        if (!$session) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT sr.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}gps_seminar_registrations sr
             LEFT JOIN {$wpdb->prefix}users u ON sr.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}gps_seminar_attendance sa
                ON sr.id = sa.registration_id AND sa.session_id = %d
             WHERE sr.seminar_id = %d
             AND sr.status = 'active'
             AND sa.id IS NULL
             ORDER BY u.display_name ASC",
            $session_id,
            $session->seminar_id
        ));
    }

    /**
     * AJAX: Scan QR code
     */
    public static function ajax_scan_qr() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $qr_data = json_decode(stripslashes($_POST['qr_data']), true);
        $session_id = (int) $_POST['session_id'];

        $result = self::check_in($qr_data, $session_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Manual check-in
     */
    public static function ajax_manual_checkin() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $registration_id = (int) $_POST['registration_id'];
        $session_id = (int) $_POST['session_id'];
        $is_makeup = isset($_POST['is_makeup']) && $_POST['is_makeup'] === 'true';

        $result = self::manual_checkin($registration_id, $session_id, $is_makeup);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Get session attendance
     */
    public static function ajax_get_session_attendance() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $session_id = (int) $_POST['session_id'];

        $stats = self::get_session_stats($session_id);
        $attendance = self::get_session_attendance($session_id);
        $unchecked = self::get_unchecked_registrants($session_id);

        wp_send_json_success([
            'stats' => $stats,
            'attendance' => $attendance,
            'unchecked' => $unchecked,
        ]);
    }
}
