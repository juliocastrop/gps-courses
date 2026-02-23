<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Seminar Makeup Requests
 *
 * Handles makeup session requests from users and admin approval workflow.
 */
class Seminar_Makeup_Requests {

    /**
     * Request statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED = 'expired';

    /**
     * Initialize
     */
    public static function init() {
        // AJAX handlers for users
        add_action('wp_ajax_gps_submit_makeup_request', [__CLASS__, 'ajax_submit_request']);
        add_action('wp_ajax_gps_get_available_makeup_sessions', [__CLASS__, 'ajax_get_available_sessions']);

        // AJAX handlers for admin
        add_action('wp_ajax_gps_approve_makeup_request', [__CLASS__, 'ajax_approve_request']);
        add_action('wp_ajax_gps_reject_makeup_request', [__CLASS__, 'ajax_reject_request']);
        add_action('wp_ajax_gps_get_makeup_requests', [__CLASS__, 'ajax_get_requests']);
    }

    /**
     * Submit a makeup request
     *
     * @param int $registration_id Registration ID
     * @param int $missed_session_id Session that was missed
     * @param int|null $requested_session_id Session requested for makeup (optional)
     * @param string $reason Reason for missing the session
     * @return array Result with success status
     */
    public static function submit_request($registration_id, $missed_session_id, $requested_session_id = null, $reason = '') {
        global $wpdb;

        // Get registration details
        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration) {
            return [
                'success' => false,
                'message' => __('Registration not found', 'gps-courses'),
            ];
        }

        // Check if makeup is still available
        if ($registration->makeup_used >= 1) {
            return [
                'success' => false,
                'message' => __('Makeup session already used for this registration', 'gps-courses'),
            ];
        }

        // Check for existing pending request
        $existing = self::get_pending_request($registration_id);
        if ($existing) {
            return [
                'success' => false,
                'message' => __('You already have a pending makeup request', 'gps-courses'),
            ];
        }

        // Verify missed session exists and belongs to the seminar
        $missed_session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d AND seminar_id = %d",
            $missed_session_id,
            $registration->seminar_id
        ));

        if (!$missed_session) {
            return [
                'success' => false,
                'message' => __('Invalid session', 'gps-courses'),
            ];
        }

        // Check if user already attended this session
        $already_attended = Seminar_Attendance::get_attendance($registration_id, $missed_session_id);
        if ($already_attended) {
            return [
                'success' => false,
                'message' => __('You already attended this session', 'gps-courses'),
            ];
        }

        // Insert request
        $result = $wpdb->insert(
            $wpdb->prefix . 'gps_seminar_makeup_requests',
            [
                'registration_id' => $registration_id,
                'user_id' => $registration->user_id,
                'seminar_id' => $registration->seminar_id,
                'missed_session_id' => $missed_session_id,
                'requested_session_id' => $requested_session_id,
                'reason' => sanitize_textarea_field($reason),
                'status' => self::STATUS_PENDING,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        if (!$result) {
            return [
                'success' => false,
                'message' => __('Failed to submit request', 'gps-courses'),
            ];
        }

        $request_id = $wpdb->insert_id;

        // Send notification email to admin
        self::notify_admin_new_request($request_id);

        // Send confirmation email to user
        self::notify_user_request_submitted($request_id);

        return [
            'success' => true,
            'message' => __('Makeup request submitted successfully. You will be notified once it is reviewed.', 'gps-courses'),
            'request_id' => $request_id,
        ];
    }

    /**
     * Get pending request for a registration
     */
    public static function get_pending_request($registration_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_makeup_requests
             WHERE registration_id = %d AND status = %s",
            $registration_id,
            self::STATUS_PENDING
        ));
    }

    /**
     * Get request by ID
     */
    public static function get_request($request_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.*,
                    u.display_name as user_name,
                    u.user_email,
                    ms.session_number as missed_session_number,
                    ms.session_date as missed_session_date,
                    ms.topic as missed_session_topic,
                    rs.session_number as requested_session_number,
                    rs.session_date as requested_session_date,
                    rs.topic as requested_session_topic,
                    reg.qr_code
             FROM {$wpdb->prefix}gps_seminar_makeup_requests r
             LEFT JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}gps_seminar_sessions ms ON r.missed_session_id = ms.id
             LEFT JOIN {$wpdb->prefix}gps_seminar_sessions rs ON r.requested_session_id = rs.id
             LEFT JOIN {$wpdb->prefix}gps_seminar_registrations reg ON r.registration_id = reg.id
             WHERE r.id = %d",
            $request_id
        ));
    }

    /**
     * Get all requests with optional filters
     */
    public static function get_requests($args = []) {
        global $wpdb;

        $defaults = [
            'status' => '',
            'seminar_id' => 0,
            'user_id' => 0,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = 'r.status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['seminar_id'])) {
            $where[] = 'r.seminar_id = %d';
            $params[] = $args['seminar_id'];
        }

        if (!empty($args['user_id'])) {
            $where[] = 'r.user_id = %d';
            $params[] = $args['user_id'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $allowed_orderby = ['created_at', 'processed_at', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT r.*,
                       u.display_name as user_name,
                       u.user_email,
                       ms.session_number as missed_session_number,
                       ms.session_date as missed_session_date,
                       ms.topic as missed_session_topic,
                       rs.session_number as requested_session_number,
                       rs.session_date as requested_session_date,
                       rs.topic as requested_session_topic,
                       s.title as seminar_title
                FROM {$wpdb->prefix}gps_seminar_makeup_requests r
                LEFT JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
                LEFT JOIN {$wpdb->prefix}gps_seminar_sessions ms ON r.missed_session_id = ms.id
                LEFT JOIN {$wpdb->prefix}gps_seminar_sessions rs ON r.requested_session_id = rs.id
                LEFT JOIN {$wpdb->prefix}posts s ON r.seminar_id = s.ID
                $where_clause
                ORDER BY r.$orderby $order
                LIMIT %d OFFSET %d";

        $params[] = $args['limit'];
        $params[] = $args['offset'];

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Approve a makeup request
     */
    public static function approve_request($request_id, $admin_notes = '') {
        global $wpdb;

        $request = self::get_request($request_id);
        if (!$request) {
            return [
                'success' => false,
                'message' => __('Request not found', 'gps-courses'),
            ];
        }

        if ($request->status !== self::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => __('Request has already been processed', 'gps-courses'),
            ];
        }

        // Update request status
        $result = $wpdb->update(
            $wpdb->prefix . 'gps_seminar_makeup_requests',
            [
                'status' => self::STATUS_APPROVED,
                'admin_notes' => sanitize_textarea_field($admin_notes),
                'processed_by' => get_current_user_id(),
                'processed_at' => current_time('mysql'),
            ],
            ['id' => $request_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to approve request', 'gps-courses'),
            ];
        }

        // Send approval email to user
        self::notify_user_request_approved($request_id);

        return [
            'success' => true,
            'message' => __('Request approved successfully', 'gps-courses'),
        ];
    }

    /**
     * Reject a makeup request
     */
    public static function reject_request($request_id, $admin_notes = '') {
        global $wpdb;

        $request = self::get_request($request_id);
        if (!$request) {
            return [
                'success' => false,
                'message' => __('Request not found', 'gps-courses'),
            ];
        }

        if ($request->status !== self::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => __('Request has already been processed', 'gps-courses'),
            ];
        }

        // Update request status
        $result = $wpdb->update(
            $wpdb->prefix . 'gps_seminar_makeup_requests',
            [
                'status' => self::STATUS_REJECTED,
                'admin_notes' => sanitize_textarea_field($admin_notes),
                'processed_by' => get_current_user_id(),
                'processed_at' => current_time('mysql'),
            ],
            ['id' => $request_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to reject request', 'gps-courses'),
            ];
        }

        // Send rejection email to user
        self::notify_user_request_rejected($request_id);

        return [
            'success' => true,
            'message' => __('Request rejected', 'gps-courses'),
        ];
    }

    /**
     * Get available sessions for makeup
     */
    public static function get_available_makeup_sessions($registration_id) {
        global $wpdb;

        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration) {
            return [];
        }

        // Get all future sessions for this seminar
        $today = current_time('Y-m-d');
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT ss.*
             FROM {$wpdb->prefix}gps_seminar_sessions ss
             WHERE ss.seminar_id = %d
             AND ss.session_date >= %s
             ORDER BY ss.session_date ASC",
            $registration->seminar_id,
            $today
        ));

        // Filter out sessions user already attended
        $available = [];
        foreach ($sessions as $session) {
            $attended = Seminar_Attendance::get_attendance($registration_id, $session->id);
            if (!$attended) {
                $available[] = $session;
            }
        }

        return $available;
    }

    /**
     * Get missed sessions for a registration
     */
    public static function get_missed_sessions($registration_id) {
        global $wpdb;

        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration) {
            return [];
        }

        // Get past sessions for this seminar that user didn't attend
        $today = current_time('Y-m-d');
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT ss.*
             FROM {$wpdb->prefix}gps_seminar_sessions ss
             LEFT JOIN {$wpdb->prefix}gps_seminar_attendance sa
                ON ss.id = sa.session_id AND sa.registration_id = %d
             WHERE ss.seminar_id = %d
             AND ss.session_date < %s
             AND sa.id IS NULL
             ORDER BY ss.session_date ASC",
            $registration_id,
            $registration->seminar_id,
            $today
        ));

        return $sessions;
    }

    /**
     * Count pending requests
     */
    public static function count_pending_requests() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_makeup_requests WHERE status = 'pending'"
        );
    }

    /**
     * Notify admin of new request
     */
    private static function notify_admin_new_request($request_id) {
        $request = self::get_request($request_id);
        if (!$request) return;

        $admin_emails = apply_filters('gps_admin_notification_emails', [get_option('admin_email')]);

        $subject = sprintf(
            __('[GPS Seminars] New Makeup Request from %s', 'gps-courses'),
            $request->user_name
        );

        $message = sprintf(
            __("A new makeup session request has been submitted.\n\nParticipant: %s (%s)\nMissed Session: #%d - %s (%s)\nReason: %s\n\nPlease review and process this request in the admin panel.", 'gps-courses'),
            $request->user_name,
            $request->user_email,
            $request->missed_session_number,
            $request->missed_session_topic ?? 'N/A',
            $request->missed_session_date,
            $request->reason ?: 'Not provided'
        );

        $admin_url = admin_url('admin.php?page=gps-seminars-makeup');
        $message .= "\n\n" . sprintf(__('Review requests: %s', 'gps-courses'), $admin_url);

        foreach ($admin_emails as $email) {
            wp_mail($email, $subject, $message);
        }
    }

    /**
     * Notify user their request was submitted
     */
    private static function notify_user_request_submitted($request_id) {
        $request = self::get_request($request_id);
        if (!$request) return;

        $subject = __('[GPS Seminars] Makeup Request Received', 'gps-courses');

        $message = sprintf(
            __("Hello %s,\n\nWe have received your makeup session request.\n\nMissed Session: #%d - %s (%s)\n\nOur team will review your request and notify you once a decision is made. This typically takes 1-2 business days.\n\nThank you for your patience.", 'gps-courses'),
            $request->user_name,
            $request->missed_session_number,
            $request->missed_session_topic ?? 'N/A',
            $request->missed_session_date
        );

        wp_mail($request->user_email, $subject, $message);
    }

    /**
     * Notify user their request was approved
     */
    private static function notify_user_request_approved($request_id) {
        $request = self::get_request($request_id);
        if (!$request) return;

        $subject = __('[GPS Seminars] Makeup Request Approved!', 'gps-courses');

        $message = sprintf(
            __("Hello %s,\n\nGreat news! Your makeup session request has been approved.\n\nMissed Session: #%d - %s\n\nYou may now attend any upcoming session as your makeup. Simply use your same QR code when checking in, and let the staff know you are attending as a makeup session.\n\nRemember: You have ONE makeup session per registration.\n\nSee you at the seminar!", 'gps-courses'),
            $request->user_name,
            $request->missed_session_number,
            $request->missed_session_topic ?? 'N/A'
        );

        if (!empty($request->admin_notes)) {
            $message .= "\n\n" . sprintf(__('Note from admin: %s', 'gps-courses'), $request->admin_notes);
        }

        wp_mail($request->user_email, $subject, $message);
    }

    /**
     * Notify user their request was rejected
     */
    private static function notify_user_request_rejected($request_id) {
        $request = self::get_request($request_id);
        if (!$request) return;

        $subject = __('[GPS Seminars] Makeup Request Update', 'gps-courses');

        $message = sprintf(
            __("Hello %s,\n\nWe have reviewed your makeup session request.\n\nUnfortunately, we are unable to approve your request at this time.\n\nMissed Session: #%d - %s", 'gps-courses'),
            $request->user_name,
            $request->missed_session_number,
            $request->missed_session_topic ?? 'N/A'
        );

        if (!empty($request->admin_notes)) {
            $message .= "\n\n" . sprintf(__('Reason: %s', 'gps-courses'), $request->admin_notes);
        }

        $message .= "\n\n" . __('If you have questions, please contact us.', 'gps-courses');

        wp_mail($request->user_email, $subject, $message);
    }

    /**
     * AJAX: Submit makeup request
     */
    public static function ajax_submit_request() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to submit a request', 'gps-courses')]);
        }

        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
        $missed_session_id = isset($_POST['missed_session_id']) ? (int) $_POST['missed_session_id'] : 0;
        $requested_session_id = isset($_POST['requested_session_id']) ? (int) $_POST['requested_session_id'] : null;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

        if (!$registration_id || !$missed_session_id) {
            wp_send_json_error(['message' => __('Missing required fields', 'gps-courses')]);
        }

        // Verify user owns this registration
        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration || $registration->user_id != get_current_user_id()) {
            wp_send_json_error(['message' => __('Invalid registration', 'gps-courses')]);
        }

        $result = self::submit_request($registration_id, $missed_session_id, $requested_session_id, $reason);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Get available makeup sessions
     */
    public static function ajax_get_available_sessions() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in', 'gps-courses')]);
        }

        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;

        if (!$registration_id) {
            wp_send_json_error(['message' => __('Missing registration ID', 'gps-courses')]);
        }

        // Verify user owns this registration
        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration || $registration->user_id != get_current_user_id()) {
            wp_send_json_error(['message' => __('Invalid registration', 'gps-courses')]);
        }

        $missed = self::get_missed_sessions($registration_id);
        $available = self::get_available_makeup_sessions($registration_id);

        wp_send_json_success([
            'missed_sessions' => $missed,
            'available_sessions' => $available,
        ]);
    }

    /**
     * AJAX: Approve makeup request (admin only)
     */
    public static function ajax_approve_request() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';

        if (!$request_id) {
            wp_send_json_error(['message' => __('Missing request ID', 'gps-courses')]);
        }

        $result = self::approve_request($request_id, $admin_notes);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Reject makeup request (admin only)
     */
    public static function ajax_reject_request() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';

        if (!$request_id) {
            wp_send_json_error(['message' => __('Missing request ID', 'gps-courses')]);
        }

        $result = self::reject_request($request_id, $admin_notes);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Get makeup requests (admin only)
     */
    public static function ajax_get_requests() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $args = [
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'seminar_id' => isset($_POST['seminar_id']) ? (int) $_POST['seminar_id'] : 0,
            'limit' => isset($_POST['limit']) ? (int) $_POST['limit'] : 50,
            'offset' => isset($_POST['offset']) ? (int) $_POST['offset'] : 0,
        ];

        $requests = self::get_requests($args);
        $pending_count = self::count_pending_requests();

        wp_send_json_success([
            'requests' => $requests,
            'pending_count' => $pending_count,
        ]);
    }
}
