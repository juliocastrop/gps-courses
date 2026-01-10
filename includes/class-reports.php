<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Reports and Analytics
 * Handles admin reporting dashboard, exports, and bulk operations
 */
class Reports {

    public static function init() {
        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // AJAX handlers
        add_action('wp_ajax_gps_export_attendees', [__CLASS__, 'ajax_export_attendees']);
        add_action('wp_ajax_gps_export_credits', [__CLASS__, 'ajax_export_credits']);
        add_action('wp_ajax_gps_export_enrollments', [__CLASS__, 'ajax_export_enrollments']);
        add_action('wp_ajax_gps_send_email_blast', [__CLASS__, 'ajax_send_email_blast']);
        add_action('wp_ajax_gps_bulk_award_credits', [__CLASS__, 'ajax_bulk_award_credits']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Reports & Analytics', 'gps-courses'),
            __('Reports', 'gps-courses'),
            'manage_options',
            'gps-reports',
            [__CLASS__, 'render_reports_page']
        );
    }

    /**
     * Enqueue scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-reports') {
            return;
        }

        wp_enqueue_style(
            'gps-reports',
            GPSC_URL . 'assets/css/admin-reports.css',
            [],
            GPSC_VERSION
        );

        wp_enqueue_script(
            'gps-reports',
            GPSC_URL . 'assets/js/admin-reports.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        wp_localize_script('gps-reports', 'gpsReports', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_reports_nonce'),
            'i18n' => [
                'exporting' => __('Exporting...', 'gps-courses'),
                'sending' => __('Sending emails...', 'gps-courses'),
                'success' => __('Operation completed successfully!', 'gps-courses'),
                'error' => __('An error occurred. Please try again.', 'gps-courses'),
                'confirm_bulk' => __('Are you sure you want to proceed with this bulk operation?', 'gps-courses'),
            ],
        ]);
    }

    /**
     * Render reports page
     */
    public static function render_reports_page() {
        global $wpdb;

        // Get statistics
        $total_events = wp_count_posts('gps_event')->publish;

        // Count only tickets from completed orders (HPOS compatible)
        // Check both HPOS table and legacy posts table
        $total_tickets = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT t.id)
            FROM {$wpdb->prefix}gps_tickets t
            LEFT JOIN {$wpdb->prefix}wc_orders o ON t.order_id = o.id
            LEFT JOIN {$wpdb->posts} p ON t.order_id = p.ID
            WHERE o.status = 'wc-completed' OR p.post_status = 'wc-completed'
        ");

        $total_checked_in = (int) $wpdb->get_var("SELECT COUNT(DISTINCT ticket_id) FROM {$wpdb->prefix}gps_attendance");
        $total_enrollments = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gps_enrollments");
        $total_credits_awarded = (int) $wpdb->get_var("SELECT COALESCE(SUM(credits), 0) FROM {$wpdb->prefix}gps_ce_ledger WHERE transaction_type = 'attendance'");

        // Get recent events
        $events = get_posts([
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_key' => '_gps_start_date',
        ]);

        ?>
        <div class="wrap gps-reports-page">
            <h1><?php _e('Reports & Analytics', 'gps-courses'); ?></h1>

            <!-- Overview Stats -->
            <div class="gps-stats-grid">
                <div class="gps-stat-card">
                    <div class="gps-stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="gps-stat-content">
                        <h3><?php echo number_format_i18n($total_events); ?></h3>
                        <p><?php _e('Total Events', 'gps-courses'); ?></p>
                    </div>
                </div>

                <div class="gps-stat-card">
                    <div class="gps-stat-icon">
                        <span class="dashicons dashicons-tickets-alt"></span>
                    </div>
                    <div class="gps-stat-content">
                        <h3><?php echo number_format_i18n($total_tickets); ?></h3>
                        <p><?php _e('Total Tickets', 'gps-courses'); ?></p>
                    </div>
                </div>

                <div class="gps-stat-card">
                    <div class="gps-stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="gps-stat-content">
                        <h3><?php echo number_format_i18n($total_checked_in); ?></h3>
                        <p><?php _e('Checked In', 'gps-courses'); ?></p>
                    </div>
                </div>

                <div class="gps-stat-card">
                    <div class="gps-stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="gps-stat-content">
                        <h3><?php echo number_format_i18n($total_enrollments); ?></h3>
                        <p><?php _e('Total Enrollments', 'gps-courses'); ?></p>
                    </div>
                </div>

                <div class="gps-stat-card">
                    <div class="gps-stat-icon">
                        <span class="dashicons dashicons-awards"></span>
                    </div>
                    <div class="gps-stat-content">
                        <h3><?php echo number_format_i18n($total_credits_awarded); ?></h3>
                        <p><?php _e('CE Credits Awarded', 'gps-courses'); ?></p>
                    </div>
                </div>

                <div class="gps-stat-card">
                    <div class="gps-stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="gps-stat-content">
                        <?php
                        $attendance_rate = $total_tickets > 0 ? round(($total_checked_in / $total_tickets) * 100, 1) : 0;
                        ?>
                        <h3><?php echo $attendance_rate; ?>%</h3>
                        <p><?php _e('Attendance Rate', 'gps-courses'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="gps-action-cards">
                <!-- Export Data -->
                <div class="gps-action-card">
                    <h2><span class="dashicons dashicons-download"></span> <?php _e('Export Data', 'gps-courses'); ?></h2>
                    <p><?php _e('Export attendance, enrollments, and CE credits data to CSV.', 'gps-courses'); ?></p>

                    <div class="gps-export-form">
                        <label for="export-event"><?php _e('Select Event (optional):', 'gps-courses'); ?></label>
                        <select id="export-event" class="widefat">
                            <option value=""><?php _e('All Events', 'gps-courses'); ?></option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event->ID; ?>"><?php echo esc_html($event->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="gps-button-group">
                            <button class="button button-primary gps-export-btn" data-type="attendees">
                                <span class="dashicons dashicons-tickets-alt"></span>
                                <?php _e('Export Attendees', 'gps-courses'); ?>
                            </button>
                            <button class="button button-primary gps-export-btn" data-type="enrollments">
                                <span class="dashicons dashicons-groups"></span>
                                <?php _e('Export Enrollments', 'gps-courses'); ?>
                            </button>
                            <button class="button button-primary gps-export-btn" data-type="credits">
                                <span class="dashicons dashicons-awards"></span>
                                <?php _e('Export CE Credits', 'gps-courses'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Email Blast -->
                <div class="gps-action-card">
                    <h2><span class="dashicons dashicons-email"></span> <?php _e('Email Blast', 'gps-courses'); ?></h2>
                    <p><?php _e('Send emails to event attendees.', 'gps-courses'); ?></p>

                    <form id="gps-email-blast-form">
                        <div class="gps-form-group">
                            <label for="email-event"><?php _e('Select Event:', 'gps-courses'); ?></label>
                            <select id="email-event" name="event_id" class="widefat" required>
                                <option value=""><?php _e('Select Event', 'gps-courses'); ?></option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event->ID; ?>"><?php echo esc_html($event->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="gps-form-group">
                            <label for="email-recipients"><?php _e('Recipients:', 'gps-courses'); ?></label>
                            <select id="email-recipients" name="recipients" class="widefat" required>
                                <option value="all"><?php _e('All Enrolled', 'gps-courses'); ?></option>
                                <option value="checked_in"><?php _e('Checked In Only', 'gps-courses'); ?></option>
                                <option value="not_checked_in"><?php _e('Not Checked In', 'gps-courses'); ?></option>
                            </select>
                        </div>

                        <div class="gps-form-group">
                            <label for="email-subject"><?php _e('Subject:', 'gps-courses'); ?></label>
                            <input type="text" id="email-subject" name="subject" class="widefat" required>
                        </div>

                        <div class="gps-form-group">
                            <label for="email-message"><?php _e('Message:', 'gps-courses'); ?></label>
                            <?php
                            wp_editor('', 'email-message', [
                                'textarea_name' => 'message',
                                'textarea_rows' => 10,
                                'media_buttons' => false,
                                'teeny' => true,
                            ]);
                            ?>
                        </div>

                        <div class="gps-form-group">
                            <p class="description">
                                <?php _e('Available placeholders: {attendee_name}, {event_title}, {ticket_code}, {event_date}', 'gps-courses'); ?>
                            </p>
                        </div>

                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php _e('Send Email Blast', 'gps-courses'); ?>
                        </button>
                    </form>

                    <div id="email-blast-result"></div>
                </div>

                <!-- Bulk Operations -->
                <div class="gps-action-card">
                    <h2><span class="dashicons dashicons-admin-tools"></span> <?php _e('Bulk Operations', 'gps-courses'); ?></h2>
                    <p><?php _e('Perform bulk operations on attendance and credits.', 'gps-courses'); ?></p>

                    <form id="gps-bulk-operations-form">
                        <div class="gps-form-group">
                            <label for="bulk-event"><?php _e('Select Event:', 'gps-courses'); ?></label>
                            <select id="bulk-event" name="event_id" class="widefat" required>
                                <option value=""><?php _e('Select Event', 'gps-courses'); ?></option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event->ID; ?>"><?php echo esc_html($event->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="gps-form-group">
                            <label for="bulk-operation"><?php _e('Operation:', 'gps-courses'); ?></label>
                            <select id="bulk-operation" name="operation" class="widefat" required>
                                <option value=""><?php _e('Select Operation', 'gps-courses'); ?></option>
                                <option value="award_credits"><?php _e('Award CE Credits (Checked In)', 'gps-courses'); ?></option>
                                <option value="resend_tickets"><?php _e('Resend Tickets (All)', 'gps-courses'); ?></option>
                                <option value="mark_attended"><?php _e('Mark All as Attended', 'gps-courses'); ?></option>
                            </select>
                        </div>

                        <button type="submit" class="button button-secondary button-large">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php _e('Execute Bulk Operation', 'gps-courses'); ?>
                        </button>
                    </form>

                    <div id="bulk-operation-result"></div>
                </div>
            </div>

            <!-- Event Reports -->
            <div class="gps-event-reports">
                <h2><?php _e('Event Reports', 'gps-courses'); ?></h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Event', 'gps-courses'); ?></th>
                            <th><?php _e('Date', 'gps-courses'); ?></th>
                            <th><?php _e('Total Tickets', 'gps-courses'); ?></th>
                            <th><?php _e('Tickets Sold', 'gps-courses'); ?></th>
                            <th><?php _e('Checked In', 'gps-courses'); ?></th>
                            <th><?php _e('Attendance Rate', 'gps-courses'); ?></th>
                            <th><?php _e('CE Credits', 'gps-courses'); ?></th>
                            <th><?php _e('Actions', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event):
                            $event_id = $event->ID;
                            $start_date = get_post_meta($event_id, '_gps_start_date', true);
                            $ce_credits = get_post_meta($event_id, '_gps_ce_credits', true);

                            // Get total capacity for this event (original quantity across all ticket types)
                            $event_tickets = get_posts([
                                'post_type' => 'gps_ticket',
                                'post_status' => 'publish',
                                'numberposts' => -1,
                                'meta_query' => [
                                    ['key' => '_gps_event_id', 'value' => $event_id, 'type' => 'NUMERIC'],
                                ],
                            ]);

                            $total_capacity = 0;
                            $has_unlimited = false;

                            foreach ($event_tickets as $ticket) {
                                $quantity_meta = get_post_meta($ticket->ID, '_gps_ticket_quantity', true);
                                if ($quantity_meta === '' || $quantity_meta === false) {
                                    $has_unlimited = true;
                                    break;
                                }
                                $total_capacity += (int) $quantity_meta;
                            }

                            // Store numeric value for calculations
                            $total_tickets = $total_capacity;

                            // Count only tickets from completed orders (HPOS compatible)
                            $tickets_sold = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(DISTINCT t.id)
                                FROM {$wpdb->prefix}gps_tickets t
                                LEFT JOIN {$wpdb->prefix}wc_orders o ON t.order_id = o.id
                                LEFT JOIN {$wpdb->posts} p ON t.order_id = p.ID
                                WHERE t.event_id = %d AND (o.status = 'wc-completed' OR p.post_status = 'wc-completed')",
                                $event_id
                            ));

                            $checked_in = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(DISTINCT ticket_id) FROM {$wpdb->prefix}gps_attendance WHERE event_id = %d",
                                $event_id
                            ));

                            $rate = $tickets_sold > 0 ? round(($checked_in / $tickets_sold) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo get_edit_post_link($event_id); ?>"><?php echo esc_html($event->post_title); ?></a></strong>
                            </td>
                            <td>
                                <?php echo $start_date ? date_i18n(get_option('date_format'), strtotime($start_date)) : '—'; ?>
                            </td>
                            <td>
                                <?php if ($has_unlimited): ?>
                                    <span style="color: #46b450; font-weight: 600;">∞</span>
                                <?php else: ?>
                                    <?php echo number_format_i18n($total_tickets); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format_i18n($tickets_sold); ?></td>
                            <td><?php echo number_format_i18n($checked_in); ?></td>
                            <td>
                                <div class="gps-progress-bar">
                                    <div class="gps-progress-fill" style="width: <?php echo $rate; ?>%"></div>
                                    <span class="gps-progress-label"><?php echo $rate; ?>%</span>
                                </div>
                            </td>
                            <td>
                                <?php if ($ce_credits): ?>
                                    <span class="gps-credits-badge"><?php echo (int) $ce_credits; ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=gps-attendance-report&event_id=' . $event_id); ?>" class="button button-small">
                                    <?php _e('View Report', 'gps-courses'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Export attendees
     */
    public static function ajax_export_attendees() {
        check_ajax_referer('gps_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gps-courses')]);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        global $wpdb;

        $sql = "SELECT t.*, p.post_title as event_title,
                       a.checked_in_at, a.check_in_method
                FROM {$wpdb->prefix}gps_tickets t
                INNER JOIN {$wpdb->posts} p ON t.event_id = p.ID
                LEFT JOIN {$wpdb->prefix}gps_attendance a ON t.id = a.ticket_id";

        if ($event_id) {
            $sql .= $wpdb->prepare(" WHERE t.event_id = %d", $event_id);
        }

        $sql .= " ORDER BY t.created_at DESC";

        $results = $wpdb->get_results($sql);

        // Generate CSV
        $filename = 'attendees-' . date('Y-m-d') . '.csv';
        $csv_data = self::generate_csv($results, [
            'ticket_code' => 'Ticket Code',
            'attendee_name' => 'Attendee Name',
            'attendee_email' => 'Attendee Email',
            'event_title' => 'Event',
            'created_at' => 'Purchase Date',
            'checked_in_at' => 'Check-in Date',
            'check_in_method' => 'Check-in Method',
            'status' => 'Status',
        ]);

        wp_send_json_success([
            'filename' => $filename,
            'data' => $csv_data,
            'count' => count($results),
        ]);
    }

    /**
     * AJAX: Export credits
     */
    public static function ajax_export_credits() {
        check_ajax_referer('gps_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gps-courses')]);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        global $wpdb;

        $sql = "SELECT l.*, p.post_title as event_title, u.display_name, u.user_email
                FROM {$wpdb->prefix}gps_ce_ledger l
                LEFT JOIN {$wpdb->posts} p ON l.event_id = p.ID
                INNER JOIN {$wpdb->users} u ON l.user_id = u.ID";

        if ($event_id) {
            $sql .= $wpdb->prepare(" WHERE l.event_id = %d", $event_id);
        }

        $sql .= " ORDER BY l.awarded_at DESC";

        $results = $wpdb->get_results($sql);

        $filename = 'ce-credits-' . date('Y-m-d') . '.csv';
        $csv_data = self::generate_csv($results, [
            'display_name' => 'Name',
            'user_email' => 'Email',
            'event_title' => 'Event',
            'credits' => 'Credits',
            'transaction_type' => 'Type',
            'awarded_at' => 'Date Awarded',
            'notes' => 'Notes',
        ]);

        wp_send_json_success([
            'filename' => $filename,
            'data' => $csv_data,
            'count' => count($results),
        ]);
    }

    /**
     * AJAX: Export enrollments
     */
    public static function ajax_export_enrollments() {
        check_ajax_referer('gps_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gps-courses')]);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        global $wpdb;

        $sql = "SELECT e.*, p.post_title as event_title, u.display_name, u.user_email
                FROM {$wpdb->prefix}gps_enrollments e
                INNER JOIN {$wpdb->posts} p ON e.session_id = p.ID
                INNER JOIN {$wpdb->users} u ON e.user_id = u.ID";

        if ($event_id) {
            $sql .= $wpdb->prepare(" WHERE e.session_id = %d", $event_id);
        }

        $sql .= " ORDER BY e.created_at DESC";

        $results = $wpdb->get_results($sql);

        $filename = 'enrollments-' . date('Y-m-d') . '.csv';
        $csv_data = self::generate_csv($results, [
            'display_name' => 'Name',
            'user_email' => 'Email',
            'event_title' => 'Event',
            'status' => 'Status',
            'created_at' => 'Enrollment Date',
            'order_id' => 'Order ID',
        ]);

        wp_send_json_success([
            'filename' => $filename,
            'data' => $csv_data,
            'count' => count($results),
        ]);
    }

    /**
     * Generate CSV from data
     */
    private static function generate_csv($data, $columns) {
        $output = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($output, array_values($columns));

        // Write data rows
        foreach ($data as $row) {
            $csv_row = [];
            foreach (array_keys($columns) as $key) {
                $csv_row[] = isset($row->$key) ? $row->$key : '';
            }
            fputcsv($output, $csv_row);
        }

        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return base64_encode($csv_content);
    }

    /**
     * AJAX: Send email blast
     */
    public static function ajax_send_email_blast() {
        check_ajax_referer('gps_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gps-courses')]);
        }

        $event_id = (int) $_POST['event_id'];
        $recipients = sanitize_text_field($_POST['recipients']);
        $subject = sanitize_text_field($_POST['subject']);
        $message = wp_kses_post($_POST['message']);

        if (!$event_id || !$subject || !$message) {
            wp_send_json_error(['message' => __('Please fill all required fields.', 'gps-courses')]);
        }

        global $wpdb;

        // Get recipients based on filter
        $sql = "SELECT DISTINCT t.attendee_name, t.attendee_email, t.ticket_code
                FROM {$wpdb->prefix}gps_tickets t";

        if ($recipients === 'checked_in') {
            $sql .= " INNER JOIN {$wpdb->prefix}gps_attendance a ON t.id = a.ticket_id";
        } elseif ($recipients === 'not_checked_in') {
            $sql .= " LEFT JOIN {$wpdb->prefix}gps_attendance a ON t.id = a.ticket_id
                     WHERE a.id IS NULL AND";
        } else {
            $sql .= " WHERE";
        }

        $sql .= $wpdb->prepare(" t.event_id = %d", $event_id);

        $attendees = $wpdb->get_results($sql);

        if (empty($attendees)) {
            wp_send_json_error(['message' => __('No recipients found.', 'gps-courses')]);
        }

        // Get event details
        $event = get_post($event_id);
        $start_date = get_post_meta($event_id, '_gps_start_date', true);

        $sent_count = 0;
        $failed = [];

        foreach ($attendees as $attendee) {
            // Replace placeholders
            $personalized_message = str_replace(
                ['{attendee_name}', '{event_title}', '{ticket_code}', '{event_date}'],
                [$attendee->attendee_name, $event->post_title, $attendee->ticket_code, date_i18n(get_option('date_format'), strtotime($start_date))],
                $message
            );

            $sent = wp_mail(
                $attendee->attendee_email,
                $subject,
                $personalized_message,
                ['Content-Type: text/html; charset=UTF-8']
            );

            if ($sent) {
                $sent_count++;
            } else {
                $failed[] = $attendee->attendee_email;
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('%d emails sent successfully.', 'gps-courses'), $sent_count),
            'sent' => $sent_count,
            'failed' => count($failed),
        ]);
    }

    /**
     * AJAX: Bulk award credits
     */
    public static function ajax_bulk_award_credits() {
        check_ajax_referer('gps_reports_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gps-courses')]);
        }

        $event_id = (int) $_POST['event_id'];
        $operation = sanitize_text_field($_POST['operation']);

        if (!$event_id) {
            wp_send_json_error(['message' => __('Please select an event.', 'gps-courses')]);
        }

        global $wpdb;

        switch ($operation) {
            case 'award_credits':
                // Award credits to all checked-in attendees who haven't received them
                $credits = (int) get_post_meta($event_id, '_gps_ce_credits', true);

                if (!$credits) {
                    wp_send_json_error(['message' => __('This event has no CE credits configured.', 'gps-courses')]);
                }

                $attendees = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT a.user_id, a.ticket_id
                     FROM {$wpdb->prefix}gps_attendance a
                     WHERE a.event_id = %d
                     AND NOT EXISTS (
                         SELECT 1 FROM {$wpdb->prefix}gps_ce_ledger l
                         WHERE l.user_id = a.user_id AND l.event_id = %d
                     )",
                    $event_id, $event_id
                ));

                $count = 0;
                foreach ($attendees as $attendee) {
                    Credits::award($attendee->user_id, $event_id, 'attendance', 'Bulk awarded by admin');
                    $count++;
                }

                wp_send_json_success([
                    'message' => sprintf(__('Awarded %d CE credits to %d attendees.', 'gps-courses'), $credits, $count),
                ]);
                break;

            case 'resend_tickets':
                // Resend ticket emails
                $tickets = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE event_id = %d",
                    $event_id
                ));

                $count = 0;
                foreach ($tickets as $ticket) {
                    // Trigger ticket email
                    do_action('gps_resend_ticket', $ticket->id);
                    $count++;
                }

                wp_send_json_success([
                    'message' => sprintf(__('Resent %d tickets.', 'gps-courses'), $count),
                ]);
                break;

            case 'mark_attended':
                // Mark all tickets as attended
                $tickets = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE event_id = %d",
                    $event_id
                ));

                $count = 0;
                foreach ($tickets as $ticket) {
                    // Check if already attended
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}gps_attendance WHERE ticket_id = %d",
                        $ticket->id
                    ));

                    if (!$exists) {
                        Attendance::check_in_ticket($ticket->id, get_current_user_id(), 'manual', 'Bulk marked as attended by admin');
                        $count++;
                    }
                }

                wp_send_json_success([
                    'message' => sprintf(__('Marked %d attendees as checked in.', 'gps-courses'), $count),
                ]);
                break;

            default:
                wp_send_json_error(['message' => __('Invalid operation.', 'gps-courses')]);
        }
    }
}
