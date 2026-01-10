<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Attendance Management
 * Handles QR code scanning, check-ins, and attendance tracking
 */
class Attendance {

    public static function init() {
        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // AJAX handlers
        add_action('wp_ajax_gps_scan_ticket', [__CLASS__, 'ajax_scan_ticket']);
        add_action('wp_ajax_gps_manual_checkin', [__CLASS__, 'ajax_manual_checkin']);
        add_action('wp_ajax_gps_search_attendees', [__CLASS__, 'ajax_search_attendees']);
        add_action('wp_ajax_gps_bulk_checkin', [__CLASS__, 'ajax_bulk_checkin']);
        add_action('wp_ajax_gps_get_attendance_stats', [__CLASS__, 'ajax_get_attendance_stats']);
        add_action('wp_ajax_gps_get_recent_checkins', [__CLASS__, 'ajax_get_recent_checkins']);
        add_action('wp_ajax_gps_get_event_stats', [__CLASS__, 'ajax_get_event_stats']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Attendance Scanner', 'gps-courses'),
            __('Attendance Scanner', 'gps-courses'),
            'manage_options',
            'gps-attendance',
            [__CLASS__, 'render_scanner_page']
        );

        add_submenu_page(
            'gps-dashboard',
            __('Attendance Report', 'gps-courses'),
            __('Attendance Report', 'gps-courses'),
            'manage_options',
            'gps-attendance-report',
            [__CLASS__, 'render_report_page']
        );
    }

    /**
     * Enqueue scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-attendance' && $hook !== 'gps-courses_page_gps-attendance-report') {
            return;
        }

        // QR Scanner library
        wp_enqueue_script(
            'html5-qrcode',
            'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
            [],
            '2.3.8',
            true
        );

        // Admin attendance script
        wp_enqueue_script(
            'gps-admin-attendance',
            GPSC_URL . 'assets/js/admin-attendance.js',
            ['jquery', 'html5-qrcode'],
            GPSC_VERSION,
            true
        );

        // Admin attendance style
        wp_enqueue_style(
            'gps-admin-attendance',
            GPSC_URL . 'assets/css/admin-attendance.css',
            [],
            GPSC_VERSION
        );

        // Localize script
        wp_localize_script('gps-admin-attendance', 'gpsAttendance', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_attendance_nonce'),
            'i18n' => [
                'starting' => __('Starting...', 'gps-courses'),
                'start_scanner' => __('Start Scanner', 'gps-courses'),
                'scanner_started' => __('Scanner started. Point camera at QR code.', 'gps-courses'),
                'camera_error' => __('Camera access error', 'gps-courses'),
                'scanner_stopped' => __('Scanner stopped.', 'gps-courses'),
                'processing' => __('Processing...', 'gps-courses'),
                'check_in_success' => __('Check-in Successful!', 'gps-courses'),
                'attendee' => __('Attendee', 'gps-courses'),
                'event' => __('Event', 'gps-courses'),
                'ticket' => __('Ticket', 'gps-courses'),
                'time' => __('Time', 'gps-courses'),
                'credits_awarded' => __('CE Credits Awarded', 'gps-courses'),
                'enter_ticket_code' => __('Please enter a ticket code.', 'gps-courses'),
                'ajax_error' => __('An error occurred. Please try again.', 'gps-courses'),
                'searching' => __('Searching...', 'gps-courses'),
                'search_min_chars' => __('Please enter at least 3 characters to search.', 'gps-courses'),
                'no_results' => __('No attendees found.', 'gps-courses'),
                'attendees_found' => __('attendees found', 'gps-courses'),
                'status' => __('Status', 'gps-courses'),
                'action' => __('Action', 'gps-courses'),
                'checked_in' => __('Checked In', 'gps-courses'),
                'not_checked_in' => __('Not Checked In', 'gps-courses'),
                'check_in' => __('Check In', 'gps-courses'),
                'method' => __('Method', 'gps-courses'),
            ],
        ]);
    }

    /**
     * Render scanner page
     */
    public static function render_scanner_page() {
        // Get all upcoming events
        $events = get_posts([
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_gps_start_date',
            'orderby' => 'meta_value',
            'order' => 'DESC',
        ]);

        ?>
        <div class="wrap gps-attendance-scanner">
            <h1><?php _e('Attendance Scanner', 'gps-courses'); ?></h1>

            <div class="gps-scanner-container">
                <!-- Event Selector -->
                <div class="gps-scanner-header">
                    <div class="gps-event-selector">
                        <label for="gps-select-event"><?php _e('Select Event:', 'gps-courses'); ?></label>
                        <select id="gps-select-event" class="gps-select-event">
                            <option value=""><?php _e('— Select Event —', 'gps-courses'); ?></option>
                            <?php foreach ($events as $event): ?>
                                <?php
                                $start_date = get_post_meta($event->ID, '_gps_start_date', true);
                                $date_label = $start_date ? ' - ' . date_i18n('M j, Y', strtotime($start_date)) : '';
                                ?>
                                <option value="<?php echo (int) $event->ID; ?>">
                                    <?php echo esc_html($event->post_title . $date_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="gps-stats-summary" style="display: none;">
                        <div class="stat-box">
                            <span class="stat-label"><?php _e('Total Tickets', 'gps-courses'); ?></span>
                            <span class="stat-value" id="total-tickets">0</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label"><?php _e('Checked In', 'gps-courses'); ?></span>
                            <span class="stat-value" id="checked-in">0</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label"><?php _e('Remaining', 'gps-courses'); ?></span>
                            <span class="stat-value" id="remaining">0</span>
                        </div>
                    </div>
                </div>

                <!-- Scanner Interface -->
                <div class="gps-scanner-body" style="display: none;">
                    <div class="gps-scanner-modes">
                        <button class="gps-scan-mode-btn mode-btn active" data-mode="qr"><?php _e('QR Scanner', 'gps-courses'); ?></button>
                        <button class="gps-scan-mode-btn mode-btn" data-mode="manual"><?php _e('Manual Entry', 'gps-courses'); ?></button>
                        <button class="gps-scan-mode-btn mode-btn" data-mode="search"><?php _e('Search', 'gps-courses'); ?></button>
                    </div>

                    <!-- QR Scanner Mode -->
                    <div class="scanner-mode scanner-mode-qr active">
                        <div class="gps-qr-scanner-wrapper" style="padding: 20px; background: #f9f9f9; border-radius: 8px; margin: 20px 0;">
                            <div style="text-align: center; margin-bottom: 20px;">
                                <button id="gps-start-scanner" class="button button-primary button-large">
                                    <span class="dashicons dashicons-camera" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php _e('Start QR Scanner', 'gps-courses'); ?>
                                </button>
                                <button id="gps-stop-scanner" class="button button-large" style="display: none;">
                                    <span class="dashicons dashicons-no" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php _e('Stop Scanner', 'gps-courses'); ?>
                                </button>
                            </div>

                            <div id="gps-qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto; display: none;"></div>

                            <div id="gps-scan-result" style="margin-top: 20px;"></div>

                            <div class="gps-scanner-messages" style="margin-top: 15px;"></div>
                        </div>
                    </div>

                    <!-- Manual Entry Mode -->
                    <div class="scanner-mode scanner-mode-manual" style="display: none;">
                        <div class="manual-entry-form" style="padding: 30px; background: #f9f9f9; border-radius: 8px; margin: 20px 0; text-align: center;">
                            <h3 style="margin-top: 0;"><?php _e('Manual Ticket Entry', 'gps-courses'); ?></h3>
                            <p style="color: #666; margin-bottom: 20px;"><?php _e('Enter the ticket code to check in an attendee', 'gps-courses'); ?></p>

                            <div style="max-width: 500px; margin: 0 auto;">
                                <input type="text"
                                       id="manual-ticket-code"
                                       class="gps-input-large"
                                       placeholder="<?php _e('GPST-XXXX-XXXX-XXXX', 'gps-courses'); ?>"
                                       style="width: 100%; padding: 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 15px; text-align: center; font-family: monospace; text-transform: uppercase;">

                                <button id="btn-manual-checkin" class="button button-primary button-large" style="width: 100%; padding: 12px 24px;">
                                    <span class="dashicons dashicons-yes" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php _e('Check In', 'gps-courses'); ?>
                                </button>
                            </div>

                            <div class="scan-result" style="margin-top: 20px;"></div>
                        </div>
                    </div>

                    <!-- Search Mode -->
                    <div class="scanner-mode scanner-mode-search" style="display: none;">
                        <div class="search-form" style="padding: 30px; background: #f9f9f9; border-radius: 8px; margin: 20px 0;">
                            <h3 style="margin-top: 0;"><?php _e('Search Attendees', 'gps-courses'); ?></h3>
                            <p style="color: #666; margin-bottom: 20px;"><?php _e('Search by name, email, or ticket code', 'gps-courses'); ?></p>

                            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                                <input type="text"
                                       id="search-attendee"
                                       class="gps-input-large"
                                       placeholder="<?php _e('Type name, email, or ticket code...', 'gps-courses'); ?>"
                                       style="flex: 1; padding: 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 4px;">

                                <button id="btn-search" class="button button-primary button-large" style="padding: 12px 24px;">
                                    <span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php _e('Search', 'gps-courses'); ?>
                                </button>
                            </div>

                            <div id="search-results" style="margin-top: 20px;"></div>
                        </div>
                    </div>

                    <!-- Scan Result -->
                    <div class="scan-result" style="display: none;">
                        <div class="result-content"></div>
                    </div>

                    <!-- Recent Check-ins -->
                    <div class="recent-checkins">
                        <h3><?php _e('Recent Check-ins', 'gps-courses'); ?></h3>
                        <div id="recent-checkins-list"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render report page
     */
    public static function render_report_page() {
        global $wpdb;

        // Get all events
        $events = get_posts([
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_gps_date_start',
            'order' => 'DESC',
        ]);

        ?>
        <div class="wrap gps-attendance-report">
            <h1><?php _e('Attendance Report', 'gps-courses'); ?></h1>

            <div class="gps-report-filters">
                <select id="report-event-select">
                    <option value=""><?php _e('— Select Event —', 'gps-courses'); ?></option>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $start_date = get_post_meta($event->ID, '_gps_date_start', true);
                        $date_label = $start_date ? ' - ' . date_i18n('M j, Y', strtotime($start_date)) : '';
                        ?>
                        <option value="<?php echo (int) $event->ID; ?>">
                            <?php echo esc_html($event->post_title . $date_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button id="export-csv" class="button">
                    <?php _e('Export to CSV', 'gps-courses'); ?>
                </button>
            </div>

            <div id="attendance-report-content">
                <p><?php _e('Select an event to view attendance report.', 'gps-courses'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Scan ticket
     */
    public static function ajax_scan_ticket() {
        check_ajax_referer('gps_attendance_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $qr_data = isset($_POST['qr_data']) ? $_POST['qr_data'] : '';
        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        if (empty($qr_data) || empty($event_id)) {
            wp_send_json_error(['message' => __('Invalid data', 'gps-courses')]);
        }

        // Decode QR data
        $qr_data = json_decode(stripslashes($qr_data), true);

        // Verify QR code
        $verification = QRCodeGenerator::verify_qr_data($qr_data);

        if (!$verification['valid']) {
            wp_send_json_error([
                'message' => $verification['message'] ?? __('Invalid ticket', 'gps-courses'),
                'error' => $verification['error'] ?? 'invalid',
            ]);
        }

        $ticket = $verification['ticket'];

        // Check if ticket is for this event
        if ((int) $ticket->event_id !== $event_id) {
            wp_send_json_error(['message' => __('Ticket is not for this event', 'gps-courses')]);
        }

        // Perform check-in
        $result = self::check_in_ticket($ticket->id, get_current_user_id(), 'qr_code');

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
        check_ajax_referer('gps_attendance_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $ticket_code = isset($_POST['ticket_code']) ? sanitize_text_field($_POST['ticket_code']) : '';
        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        if (empty($ticket_code) || empty($event_id)) {
            wp_send_json_error(['message' => __('Invalid data', 'gps-courses')]);
        }

        global $wpdb;

        // Find ticket by code
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE ticket_code = %s AND event_id = %d",
            $ticket_code,
            $event_id
        ));

        if (!$ticket) {
            wp_send_json_error(['message' => __('Ticket not found', 'gps-courses')]);
        }

        // Perform check-in
        $result = self::check_in_ticket($ticket->id, get_current_user_id(), 'manual');

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Search attendees
     */
    public static function ajax_search_attendees() {
        check_ajax_referer('gps_attendance_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        // Accept both 'query' and 'search' parameters
        $search = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        if (empty($search)) {
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        if (empty($search)) {
            wp_send_json_error(['message' => __('Please enter a search query', 'gps-courses')]);
        }

        if (empty($event_id)) {
            wp_send_json_error(['message' => __('Please select an event', 'gps-courses')]);
        }

        global $wpdb;

        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*,
                    p.post_title as event_title,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}gps_attendance a WHERE a.ticket_id = t.id) as is_checked_in,
                    (SELECT checked_in_at FROM {$wpdb->prefix}gps_attendance a WHERE a.ticket_id = t.id ORDER BY checked_in_at DESC LIMIT 1) as checked_in_at
             FROM {$wpdb->prefix}gps_tickets t
             INNER JOIN {$wpdb->posts} p ON t.event_id = p.ID
             WHERE t.event_id = %d
             AND (t.attendee_name LIKE %s OR t.attendee_email LIKE %s OR t.ticket_code LIKE %s)
             ORDER BY t.attendee_name ASC
             LIMIT 20",
            $event_id,
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        ));

        // Format results for frontend
        $results = [];
        foreach ($tickets as $ticket) {
            $results[] = [
                'ticket_id' => $ticket->id,
                'ticket_code' => $ticket->ticket_code,
                'attendee_name' => $ticket->attendee_name,
                'attendee_email' => $ticket->attendee_email,
                'event_title' => $ticket->event_title,
                'checked_in' => ($ticket->is_checked_in > 0),
                'checked_in_at' => $ticket->checked_in_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ticket->checked_in_at)) : null,
            ];
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX: Get attendance stats
     */
    public static function ajax_get_attendance_stats() {
        check_ajax_referer('gps_attendance_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        if (empty($event_id)) {
            wp_send_json_error(['message' => __('Invalid event', 'gps-courses')]);
        }

        $stats = self::get_event_stats($event_id);

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Get event-specific stats (for scanner page)
     */
    public static function ajax_get_event_stats() {
        check_ajax_referer('gps_attendance_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        if (empty($event_id)) {
            wp_send_json_error(['message' => __('Invalid event', 'gps-courses')]);
        }

        global $wpdb;

        // Get total tickets for this event
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_tickets WHERE event_id = %d AND status = 'valid'",
            $event_id
        ));

        // Get checked in count
        $checked_in = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ticket_id)
            FROM {$wpdb->prefix}gps_attendance a
            INNER JOIN {$wpdb->prefix}gps_tickets t ON a.ticket_id = t.id
            WHERE t.event_id = %d",
            $event_id
        ));

        $remaining = $total - $checked_in;

        wp_send_json_success([
            'total' => $total,
            'checked_in' => $checked_in,
            'remaining' => max(0, $remaining)
        ]);
    }

    /**
     * Check in a ticket
     */
    public static function check_in_ticket($ticket_id, $checked_in_by, $method = 'qr_code', $notes = '') {
        global $wpdb;

        // Get ticket
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            $ticket_id
        ));

        if (!$ticket) {
            return [
                'success' => false,
                'message' => __('Ticket not found', 'gps-courses'),
            ];
        }

        // Check if already checked in
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_attendance WHERE ticket_id = %d",
            $ticket_id
        ));

        if ($existing) {
            return [
                'success' => false,
                'message' => __('Already checked in', 'gps-courses'),
                'error' => 'duplicate',
                'checked_in_at' => $existing->checked_in_at,
            ];
        }

        // Insert attendance record
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'gps_attendance',
            [
                'ticket_id' => $ticket_id,
                'event_id' => $ticket->event_id,
                'user_id' => $ticket->user_id,
                'checked_in_at' => current_time('mysql'),
                'checked_in_by' => $checked_in_by,
                'check_in_method' => $method,
                'notes' => $notes,
            ],
            [
                '%d', // ticket_id
                '%d', // event_id
                '%d', // user_id
                '%s', // checked_in_at
                '%d', // checked_in_by
                '%s', // check_in_method
                '%s', // notes
            ]
        );

        if (!$inserted) {
            return [
                'success' => false,
                'message' => __('Database error', 'gps-courses'),
            ];
        }

        // Update enrollment status
        $wpdb->update(
            $wpdb->prefix . 'gps_enrollments',
            [
                'attended' => 1,
                'checked_in_at' => current_time('mysql'),
            ],
            [
                'user_id' => $ticket->user_id,
                'session_id' => $ticket->event_id,
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        // Award CE credits
        $credits = (int) get_post_meta($ticket->event_id, '_gps_ce_credits', true);
        if ($credits > 0) {
            Credits::award(
                $ticket->user_id,
                $ticket->event_id,
                'attendance',
                'Awarded upon check-in'
            );

            // Trigger credits email
            do_action('gps_credits_awarded', $ticket->user_id, $ticket->event_id, $credits);
            do_action('gps_credits_awarded_notification', $ticket->user_id, $ticket->event_id, $credits);
        }

        // Get attendee info
        $event = get_post($ticket->event_id);

        return [
            'success' => true,
            'message' => __('Check-in successful!', 'gps-courses'),
            'ticket' => $ticket,
            'event' => $event,
            'credits_awarded' => $credits,
            'checked_in_at' => current_time('mysql'),
        ];
    }

    /**
     * Get event stats
     */
    public static function get_event_stats($event_id) {
        global $wpdb;

        $total_tickets = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_tickets WHERE event_id = %d",
            $event_id
        ));

        $checked_in = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT a.ticket_id)
             FROM {$wpdb->prefix}gps_attendance a
             INNER JOIN {$wpdb->prefix}gps_tickets t ON a.ticket_id = t.id
             WHERE t.event_id = %d",
            $event_id
        ));

        return [
            'total_tickets' => (int) $total_tickets,
            'checked_in' => (int) $checked_in,
            'remaining' => (int) $total_tickets - (int) $checked_in,
            'percentage' => $total_tickets > 0 ? round(($checked_in / $total_tickets) * 100, 2) : 0,
        ];
    }

    /**
     * Get event attendance report
     */
    public static function get_attendance_report($event_id) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*,
                    a.checked_in_at,
                    a.check_in_method,
                    u.display_name as user_name,
                    u.user_email
             FROM {$wpdb->prefix}gps_tickets t
             LEFT JOIN {$wpdb->prefix}gps_attendance a ON t.id = a.ticket_id
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             WHERE t.event_id = %d
             ORDER BY t.attendee_name ASC",
            $event_id
        ));

        return $results;
    }

    /**
     * AJAX: Get recent check-ins
     */
    public static function ajax_get_recent_checkins() {
        check_ajax_referer('gps_attendance_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gps-courses')]);
        }

        global $wpdb;

        $recent = $wpdb->get_results(
            "SELECT a.*,
                    t.ticket_code,
                    t.attendee_name,
                    p.post_title as event_title
             FROM {$wpdb->prefix}gps_attendance a
             INNER JOIN {$wpdb->prefix}gps_tickets t ON a.ticket_id = t.id
             INNER JOIN {$wpdb->posts} p ON a.event_id = p.ID
             ORDER BY a.checked_in_at DESC
             LIMIT 10"
        );

        $formatted = [];
        foreach ($recent as $checkin) {
            $formatted[] = [
                'attendee_name' => $checkin->attendee_name,
                'event_title' => $checkin->event_title,
                'ticket_code' => $checkin->ticket_code,
                'checked_in_at' => $checkin->checked_in_at,
                'time_ago' => human_time_diff(strtotime($checkin->checked_in_at), current_time('timestamp')) . ' ' . __('ago', 'gps-courses'),
                'method' => $checkin->check_in_method,
                'method_label' => ucwords(str_replace('_', ' ', $checkin->check_in_method)),
            ];
        }

        wp_send_json_success($formatted);
    }
}
