<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

use TCPDF;

/**
 * Certificate Generation and Management
 * Handles certificate generation for completed courses
 */
class Certificates {

    public static function init() {
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // AJAX handlers
        add_action('wp_ajax_gps_generate_certificate', [__CLASS__, 'ajax_generate_certificate']);
        add_action('wp_ajax_gps_regenerate_certificate', [__CLASS__, 'ajax_regenerate_certificate']);
        add_action('wp_ajax_gps_send_certificate', [__CLASS__, 'ajax_send_certificate']);
        add_action('wp_ajax_gps_get_event_attendees', [__CLASS__, 'ajax_get_event_attendees']);
        add_action('wp_ajax_gps_bulk_send_certificates', [__CLASS__, 'ajax_bulk_send_certificates']);
        add_action('wp_ajax_gps_bulk_regenerate_certificates', [__CLASS__, 'ajax_bulk_regenerate_certificates']);
        add_action('wp_ajax_gps_preview_certificate', [__CLASS__, 'ajax_preview_certificate']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);

        // Handle certificate downloads
        add_action('template_redirect', [__CLASS__, 'handle_download']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Certificates', 'gps-courses'),
            __('Certificates', 'gps-courses'),
            'manage_options',
            'gps-certificates',
            [__CLASS__, 'render_certificates_page']
        );
    }

    /**
     * Enqueue scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-certificates') {
            return;
        }

        wp_enqueue_script(
            'gps-certificates',
            GPSC_URL . 'assets/js/admin-certificates.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        wp_enqueue_style(
            'gps-certificates',
            GPSC_URL . 'assets/css/admin-certificates.css',
            [],
            GPSC_VERSION
        );

        wp_localize_script('gps-certificates', 'gpsCertificates', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_certificates_nonce'),
            'i18n' => [
                'generating' => __('Generating certificate...', 'gps-courses'),
                'sending' => __('Sending certificate...', 'gps-courses'),
                'success' => __('Certificate sent successfully!', 'gps-courses'),
                'error' => __('An error occurred. Please try again.', 'gps-courses'),
                'confirm_bulk' => __('Send certificates to {count} attendees?', 'gps-courses'),
                'loading' => __('Loading...', 'gps-courses'),
                'select_event' => __('Please select an event first.', 'gps-courses'),
            ],
        ]);
    }

    /**
     * Render certificates page
     */
    public static function render_certificates_page() {
        // Get all events
        $events = get_posts([
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_gps_start_date',
            'order' => 'DESC',
        ]);

        ?>
        <div class="wrap" id="gps-certificates-page">
            <h1><?php _e('Certificate Management', 'gps-courses'); ?></h1>

            <!-- Event Selector Section -->
            <div class="event-selector-section">
                <label for="event-selector"><?php _e('Select Event:', 'gps-courses'); ?></label>
                <select id="event-selector">
                    <option value=""><?php _e('— Select Event —', 'gps-courses'); ?></option>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $start_date = get_post_meta($event->ID, '_gps_start_date', true);
                        $date_label = $start_date ? ' - ' . date_i18n('M j, Y', strtotime($start_date)) : '';
                        ?>
                        <option value="<?php echo esc_attr($event->ID); ?>">
                            <?php echo esc_html($event->post_title . $date_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Statistics Section -->
            <div class="certificate-stats">
                <div class="stat-card total">
                    <span class="stat-value" id="stat-total">0</span>
                    <span class="stat-label"><?php _e('Total Attendees', 'gps-courses'); ?></span>
                </div>
                <div class="stat-card sent">
                    <span class="stat-value" id="stat-sent">0</span>
                    <span class="stat-label"><?php _e('Certificates Sent', 'gps-courses'); ?></span>
                </div>
                <div class="stat-card pending">
                    <span class="stat-value" id="stat-pending">0</span>
                    <span class="stat-label"><?php _e('Pending', 'gps-courses'); ?></span>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div id="loading-indicator" style="display: none;">
                <?php _e('Loading attendees...', 'gps-courses'); ?>
            </div>

            <!-- Attendees Section -->
            <div class="attendees-section">
                <div class="attendees-section-header">
                    <h2><?php _e('Checked-in Attendees', 'gps-courses'); ?></h2>
                </div>

                <div id="bulk-actions" class="bulk-actions" style="display: none;">
                    <label>
                        <input type="checkbox" id="select-all-attendees">
                        <?php _e('Select All', 'gps-courses'); ?>
                    </label>
                    <button type="button" id="bulk-regenerate-certificates" class="button" disabled>
                        <?php _e('Regenerate Selected', 'gps-courses'); ?>
                    </button>
                    <button type="button" id="bulk-send-certificates" class="button button-primary" disabled>
                        <?php _e('Send Selected Certificates', 'gps-courses'); ?>
                    </button>
                </div>

                <table id="attendees-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th><?php _e('Name', 'gps-courses'); ?></th>
                            <th><?php _e('Email', 'gps-courses'); ?></th>
                            <th><?php _e('Ticket Code', 'gps-courses'); ?></th>
                            <th><?php _e('Status', 'gps-courses'); ?></th>
                            <th><?php _e('Actions', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="no-attendees">
                                <?php _e('Please select an event to view attendees.', 'gps-courses'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get event attendees
     */
    public static function ajax_get_event_attendees() {
        check_ajax_referer('gps_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

        if (empty($event_id)) {
            wp_send_json_error(['message' => __('Invalid event', 'gps-courses')]);
        }

        global $wpdb;

        // Get all attendees who checked in to this event
        $attendees = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT
                t.id as ticket_id,
                t.ticket_code,
                t.attendee_name as user_name,
                t.attendee_email as user_email,
                t.user_id,
                a.checked_in_at,
                c.certificate_path,
                c.certificate_url,
                c.certificate_sent_at
            FROM {$wpdb->prefix}gps_tickets t
            INNER JOIN {$wpdb->prefix}gps_attendance a ON t.id = a.ticket_id
            LEFT JOIN {$wpdb->prefix}gps_certificates c ON c.ticket_id = t.id
            WHERE t.event_id = %d
            ORDER BY t.attendee_name ASC",
            $event_id
        ));

        wp_send_json_success($attendees);
    }

    /**
     * AJAX: Generate certificate
     */
    public static function ajax_generate_certificate() {
        check_ajax_referer('gps_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;

        if (empty($ticket_id)) {
            wp_send_json_error(['message' => __('Invalid ticket', 'gps-courses')]);
        }

        // Generate certificate
        $result = self::generate_certificate($ticket_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Regenerate certificate
     */
    public static function ajax_regenerate_certificate() {
        check_ajax_referer('gps_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;

        if (empty($ticket_id)) {
            wp_send_json_error(['message' => __('Invalid ticket', 'gps-courses')]);
        }

        // Regenerate certificate
        $result = self::generate_certificate($ticket_id, true);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Send certificate
     */
    public static function ajax_send_certificate() {
        check_ajax_referer('gps_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;

        if (empty($ticket_id)) {
            wp_send_json_error(['message' => __('Invalid ticket', 'gps-courses')]);
        }

        // Send certificate
        $result = self::send_certificate($ticket_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Bulk send certificates
     */
    public static function ajax_bulk_send_certificates() {
        check_ajax_referer('gps_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $ticket_ids = isset($_POST['ticket_ids']) ? $_POST['ticket_ids'] : [];

        if (empty($ticket_ids) || !is_array($ticket_ids)) {
            wp_send_json_error(['message' => __('No tickets selected', 'gps-courses')]);
        }

        $results = [
            'success' => [],
            'errors' => [],
        ];

        foreach ($ticket_ids as $ticket_id) {
            $result = self::send_certificate((int) $ticket_id);
            if ($result['success']) {
                $results['success'][] = (int) $ticket_id;
            } else {
                $results['errors'][] = $result['message'];
            }
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX: Bulk regenerate certificates
     */
    public static function ajax_bulk_regenerate_certificates() {
        check_ajax_referer('gps_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $ticket_ids = isset($_POST['ticket_ids']) ? $_POST['ticket_ids'] : [];

        if (empty($ticket_ids) || !is_array($ticket_ids)) {
            wp_send_json_error(['message' => __('No tickets selected', 'gps-courses')]);
        }

        $results = [
            'success' => [],
            'errors' => [],
        ];

        foreach ($ticket_ids as $ticket_id) {
            $result = self::generate_certificate((int) $ticket_id, true);
            if ($result['success']) {
                $results['success'][] = (int) $ticket_id;
            } else {
                $results['errors'][] = $result['message'];
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Generate certificate PDF
     */
    public static function generate_certificate($ticket_id, $regenerate = false) {
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

        // Check if attended
        $attendance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_attendance WHERE ticket_id = %d",
            $ticket_id
        ));

        if (!$attendance) {
            return [
                'success' => false,
                'message' => __('Attendee did not check in', 'gps-courses'),
            ];
        }

        // If regenerating, delete old certificate file
        if ($regenerate) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_certificates WHERE ticket_id = %d",
                $ticket_id
            ));

            if ($existing && !empty($existing->certificate_path) && file_exists($existing->certificate_path)) {
                @unlink($existing->certificate_path);
            }
        }

        // Get event details
        $event = get_post($ticket->event_id);
        $start_date = get_post_meta($ticket->event_id, '_gps_start_date', true);
        $venue = get_post_meta($ticket->event_id, '_gps_venue', true);
        $instructor = get_post_meta($ticket->event_id, '_gps_instructor', true);

        // Create certificate directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/gps-certificates';
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }

        // Generate certificate filename
        $cert_filename = 'certificate-' . $ticket_id . '-' . time() . '.pdf';
        $cert_path = $cert_dir . '/' . $cert_filename;
        $cert_url = $upload_dir['baseurl'] . '/gps-certificates/' . $cert_filename;

        // Create PDF
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('GPS Dental Training');
        $pdf->SetAuthor('GPS Dental Training');
        $pdf->SetTitle('Certificate of Completion');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        // Add a page
        $pdf->AddPage();

        // Render certificate content
        self::render_certificate_content($pdf, [
            'attendee_name' => $ticket->attendee_name,
            'event_title' => $event->post_title,
            'event_date' => $start_date ? date('F j, Y', strtotime($start_date)) : '',
            'venue' => $venue,
            'instructor' => $instructor ?: 'Dr Carlos Castro DDS, FACP',
            'certificate_code' => $ticket->ticket_code,
        ]);

        // Output PDF to file
        $pdf->Output($cert_path, 'F');

        // Save or update certificate record
        if ($regenerate) {
            $wpdb->update(
                $wpdb->prefix . 'gps_certificates',
                [
                    'certificate_path' => $cert_path,
                    'certificate_url' => $cert_url,
                    'generated_at' => current_time('mysql'),
                ],
                ['ticket_id' => $ticket_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'gps_certificates',
                [
                    'ticket_id' => $ticket_id,
                    'user_id' => $ticket->user_id,
                    'event_id' => $ticket->event_id,
                    'certificate_path' => $cert_path,
                    'certificate_url' => $cert_url,
                    'generated_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%s', '%s', '%s']
            );
        }

        return [
            'success' => true,
            'certificate_url' => $cert_url,
            'certificate_path' => $cert_path,
        ];
    }

    /**
     * Render certificate content
     */
    private static function render_certificate_content($pdf, $data) {
        $w = 297; // A4 landscape width in mm
        $h = 210; // A4 landscape height in mm

        // Get settings
        $logo = Certificate_Settings::get('logo');
        $header_title = Certificate_Settings::get('header_title');
        $header_subtitle = Certificate_Settings::get('header_subtitle');
        $header_bg = Certificate_Settings::get('header_bg_color');
        $header_text_color = Certificate_Settings::get('header_text_color');
        $main_title = Certificate_Settings::get('main_title');
        $main_subtitle = Certificate_Settings::get('main_subtitle');
        $description = Certificate_Settings::get('description_text');
        $program_provider = Certificate_Settings::get('program_provider');
        $course_title_label = Certificate_Settings::get('course_title_label');
        $code_label = Certificate_Settings::get('code_label');
        $code_bg = Certificate_Settings::get('code_bg_color');
        $primary_color = Certificate_Settings::get('primary_color');
        $secondary_color = Certificate_Settings::get('secondary_color');
        $date_color = Certificate_Settings::get('date_color');
        $instructor_label = Certificate_Settings::get('instructor_label');
        $course_method_label = Certificate_Settings::get('course_method_label');
        $course_method = Certificate_Settings::get('course_method_default');
        $location_label = Certificate_Settings::get('location_label');
        $pace_text = Certificate_Settings::get('pace_text');
        $pace_logo = Certificate_Settings::get('pace_logo');
        $show_pace = Certificate_Settings::get('show_pace');
        $signature_image = Certificate_Settings::get('signature_image');
        $enable_qr_code = Certificate_Settings::get('enable_qr_code');
        $qr_code_position = Certificate_Settings::get('qr_code_position');

        // Get font sizes
        $header_title_size = Certificate_Settings::get('header_title_size');
        $header_subtitle_size = Certificate_Settings::get('header_subtitle_size');
        $main_title_size = Certificate_Settings::get('main_title_size');
        $main_subtitle_size = Certificate_Settings::get('main_subtitle_size');
        $attendee_name_size = Certificate_Settings::get('attendee_name_size');
        $event_title_size = Certificate_Settings::get('event_title_size');
        $description_size = Certificate_Settings::get('description_size');
        $date_size = Certificate_Settings::get('date_size');
        $footer_size = Certificate_Settings::get('footer_size');
        $pace_text_size = Certificate_Settings::get('pace_text_size');

        // Convert hex colors to RGB
        $header_bg_rgb = self::hex_to_rgb($header_bg);
        $header_text_rgb = self::hex_to_rgb($header_text_color);
        $code_bg_rgb = self::hex_to_rgb($code_bg);
        $primary_rgb = self::hex_to_rgb($primary_color);
        $secondary_rgb = self::hex_to_rgb($secondary_color);
        $date_rgb = self::hex_to_rgb($date_color);

        // Background
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, $w, $h, 'F');

        // Outer border
        $pdf->SetLineWidth(0.5);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->RoundedRect(10, 10, $w - 20, $h - 20, 5, '1111', 'D');

        // Header Section - Reduced height from 30 to 25mm
        $pdf->SetFillColor($header_bg_rgb[0], $header_bg_rgb[1], $header_bg_rgb[2]);
        $pdf->Rect(15, 15, $w - 30, 25, 'F');

        // Logo or Header Title
        if (!empty($logo)) {
            // Convert URL to file path
            $upload_dir = \wp_upload_dir();
            $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo);

            // Display logo - reduced size
            if (file_exists($logo_path)) {
                list($img_width, $img_height) = @getimagesize($logo_path);
                if ($img_width && $img_height) {
                    $logo_height = 16;
                    $logo_width = ($img_width / $img_height) * $logo_height;
                    $logo_x = ($w - $logo_width) / 2;
                    $pdf->Image($logo_path, $logo_x, 19, $logo_width, $logo_height, '', '', '', true, 300);
                }
            }
        } else {
            // Display text header
            $pdf->SetTextColor($header_text_rgb[0], $header_text_rgb[1], $header_text_rgb[2]);
            $pdf->SetFont('helvetica', 'B', $header_title_size);
            $pdf->SetXY(20, 20);
            $pdf->Cell($w - 40, 8, $header_title, 0, 1, 'C');

            $pdf->SetTextColor($secondary_rgb[0], $secondary_rgb[1], $secondary_rgb[2]);
            $pdf->SetFont('helvetica', '', $header_subtitle_size);
            $pdf->SetXY(20, 28);
            $pdf->Cell($w - 40, 6, $header_subtitle, 0, 1, 'C');
        }

        // Main Certificate Title - moved up
        $pdf->SetFont('helvetica', 'B', $main_title_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 48);
        $pdf->Cell($w - 40, 10, $main_title, 0, 1, 'C');

        // OF COMPLETION Box - moved up, no border
        $pdf->SetFont('helvetica', '', $main_subtitle_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 60);
        $pdf->Cell($w - 40, 8, $main_subtitle, 0, 1, 'C');

        // Description Text - moved up and reduced spacing
        $pdf->SetFont('helvetica', '', $description_size);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(40, 72);
        $pdf->MultiCell($w - 80, 4, $description, 0, 'C');

        // Attendee Name (Large) - moved up
        $pdf->SetFont('helvetica', 'B', $attendee_name_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 85);
        $pdf->Cell($w - 40, 8, $data['attendee_name'], 0, 1, 'C');

        // Program Provider - moved up
        $pdf->SetFont('helvetica', 'B', $description_size);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(20, 96);
        $pdf->Cell($w - 40, 4, $program_provider, 0, 1, 'C');

        // Event Date - moved up
        $pdf->SetFont('helvetica', '', $date_size);
        $pdf->SetTextColor($date_rgb[0], $date_rgb[1], $date_rgb[2]);
        $pdf->SetXY(20, 102);
        $pdf->Cell($w - 40, 5, $data['event_date'], 0, 1, 'C');

        // Course Title Label - moved up
        $pdf->SetFont('helvetica', '', $footer_size);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(20, 112);
        $pdf->Cell($w - 40, 3, $course_title_label, 0, 1, 'C');

        // Event Title (Course Name) - moved up
        $pdf->SetFont('helvetica', 'B', $event_title_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(30, 118);
        $pdf->Cell($w - 60, 7, $data['event_title'], 0, 1, 'C');

        // Certificate Code with Background - moved up
        $pdf->SetFont('helvetica', '', $footer_size);
        $pdf->SetFillColor($code_bg_rgb[0], $code_bg_rgb[1], $code_bg_rgb[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->RoundedRect(($w - 70) / 2, 130, 70, 7, 2, '1111', 'F');
        $pdf->SetXY(($w - 70) / 2, 131);
        $pdf->Cell(70, 5, $code_label . ' #' . $data['certificate_code'], 0, 1, 'C', false);

        // Instructor Signature - moved up and reduced size
        if (!empty($signature_image)) {
            // Convert URL to file path
            $upload_dir = \wp_upload_dir();
            $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_image);

            if (file_exists($signature_path)) {
                list($img_width, $img_height) = @getimagesize($signature_path);
                if ($img_width && $img_height) {
                    $sig_height = 12;
                    $sig_width = ($img_width / $img_height) * $sig_height;
                    $sig_x = ($w - $sig_width) / 2;
                    $pdf->Image($signature_path, $sig_x, 141, $sig_width, $sig_height, '', '', '', true, 300);
                }
            }
        } else {
            // Signature Placeholder
            $pdf->SetFont('zapfdingbats', '', 20);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY(20, 142);
            $pdf->Cell($w - 40, 7, chr(252), 0, 1, 'C');
        }

        // Instructor Name - centered to align with signature
        $pdf->SetFont('helvetica', 'B', $footer_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 155);
        $pdf->Cell($w - 40, 4, $instructor_label . ' ' . $data['instructor'], 0, 1, 'C');

        // Course Method - new line, centered
        $pdf->SetFont('helvetica', 'B', $footer_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 160);
        $pdf->Cell($w - 40, 4, $course_method_label . ' ' . $course_method, 0, 1, 'C');

        // Course Location - moved up
        if (!empty($data['venue'])) {
            $pdf->SetFont('helvetica', '', $footer_size);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetXY(20, 166);
            $pdf->Cell($w - 40, 4, $location_label . ' ' . $data['venue'], 0, 1, 'C');
        }

        // QR Code for Certificate Validation - positioned first to avoid overlap
        if ($enable_qr_code && !empty($data['certificate_code'])) {
            // Generate QR code content (validation URL)
            $validation_url = home_url('/certificate-validation?code=' . $data['certificate_code']);

            // Create QR code using TCPDF's built-in 2D barcode
            $qr_size = 18; // Reduced size from 20 to 18mm
            $qr_y = 173; // Position from top - moved down

            if ($qr_code_position === 'bottom-left') {
                $qr_x = 18;
            } else {
                // bottom-right (default)
                $qr_x = $w - $qr_size - 18;
            }

            // write2DBarcode($code, $type, $x, $y, $w, $h, $style, $align)
            $pdf->write2DBarcode($validation_url, 'QRCODE,L', $qr_x, $qr_y, $qr_size, $qr_size, [], 'N');
        }

        // PACE Section at Bottom - full height background with rounded corners
        if ($show_pace) {
            // Light background for PACE section - extends close to bottom border without overlap
            $pace_y = 173;
            $pace_height = 25; // Reduced from 27mm to 25mm to avoid border overlap

            $pdf->SetFillColor(240, 245, 250);

            // Adjust PACE section width if QR code is on the left
            if ($enable_qr_code && $qr_code_position === 'bottom-left') {
                // Start PACE section after QR code
                $pace_start_x = 45;
                $pace_width = $w - 60;
                $pdf->RoundedRect($pace_start_x, $pace_y, $pace_width, $pace_height, 3.5, '1111', 'F');
                $pace_x = $pace_start_x + 5;
            } else {
                // Standard full width, QR code will be on the right
                $pace_start_x = 15;
                $pace_width = ($enable_qr_code) ? $w - 55 : $w - 30;
                $pdf->RoundedRect($pace_start_x, $pace_y, $pace_width, $pace_height, 3.5, '1111', 'F');
                $pace_x = $pace_start_x + 10;
            }

            // PACE Logo (if available) - vertically centered in the section
            if (!empty($pace_logo)) {
                // Convert URL to file path
                $upload_dir = \wp_upload_dir();
                $pace_logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pace_logo);

                if (file_exists($pace_logo_path) && !preg_match('/\.svg$/i', $pace_logo_path)) {
                    list($img_width, $img_height) = @getimagesize($pace_logo_path);
                    if ($img_width && $img_height) {
                        $pace_logo_height = 12;
                        $pace_logo_width = ($img_width / $img_height) * $pace_logo_height;
                        // Center vertically in pace section
                        $logo_y = $pace_y + ($pace_height - $pace_logo_height) / 2;
                        $pdf->Image($pace_logo_path, $pace_x, $logo_y, $pace_logo_width, $pace_logo_height, '', '', '', true, 300);
                        $pace_x += $pace_logo_width + 6;
                    }
                }
            }

            // PACE Text - vertically centered
            if (!empty($pace_text)) {
                $pdf->SetFont('helvetica', '', $pace_text_size);
                $pdf->SetTextColor(40, 40, 40);
                // Center text vertically
                $pdf->SetXY($pace_x, $pace_y + 3);
                // Limit width to prevent overflow with QR code
                $available_width = $pace_start_x + $pace_width - $pace_x - 5;
                $pdf->MultiCell($available_width, 2, $pace_text, 0, 'L');
            }
        }
    }

    /**
     * Convert hex color to RGB array
     */
    private static function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }

    /**
     * Send certificate via email
     */
    public static function send_certificate($ticket_id) {
        global $wpdb;

        // Check if certificate already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_certificates WHERE ticket_id = %d",
            $ticket_id
        ));

        if (!$existing) {
            // Generate certificate first
            $result = self::generate_certificate($ticket_id);
            if (!$result['success']) {
                return $result;
            }

            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_certificates WHERE ticket_id = %d",
                $ticket_id
            ));
        }

        // Get ticket and attendee info
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            $ticket_id
        ));

        $event = get_post($ticket->event_id);

        // Prepare email
        $to = $ticket->attendee_email;
        $subject = sprintf(__('Your Certificate of Completion - %s', 'gps-courses'), $event->post_title);

        $message = self::get_certificate_email_template([
            'attendee_name' => $ticket->attendee_name,
            'event_title' => $event->post_title,
            'certificate_url' => $existing->certificate_url,
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: GPS Dental Training <' . get_option('admin_email') . '>',
        ];

        // Send email
        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            // Update sent timestamp
            $wpdb->update(
                $wpdb->prefix . 'gps_certificates',
                ['certificate_sent_at' => current_time('mysql')],
                ['id' => $existing->id],
                ['%s'],
                ['%d']
            );

            return [
                'success' => true,
                'message' => __('Certificate sent successfully', 'gps-courses'),
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to send certificate email', 'gps-courses'),
        ];
    }

    /**
     * Get certificate email template
     */
    private static function get_certificate_email_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                            <!-- Header -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #19346380 0%, #764ba2 100%); color: white; padding: 40px; text-align: center; border-radius: 8px 8px 0 0;">
                                    <h1 style="margin: 0; font-size: 28px; font-weight: bold; color: white;">
                                        🎓 Certificate of Completion
                                    </h1>
                                    <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">
                                        Congratulations on completing your course!
                                    </p>
                                </td>
                            </tr>

                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px;">
                                    <p style="margin: 0 0 20px; font-size: 16px; color: #333;">
                                        Dear <strong><?php echo esc_html($data['attendee_name']); ?></strong>,
                                    </p>

                                    <p style="margin: 0 0 20px; font-size: 15px; color: #555; line-height: 1.6;">
                                        Congratulations! Your Certificate of Completion for <strong><?php echo esc_html($data['event_title']); ?></strong> is ready.
                                    </p>

                                    <p style="margin: 0 0 30px; font-size: 15px; color: #555; line-height: 1.6;">
                                        Thank you for attending and completing this course. Your certificate is attached to this email and can also be downloaded using the button below.
                                    </p>

                                    <div style="text-align: center; margin: 30px 0;">
                                        <a href="<?php echo esc_url($data['certificate_url']); ?>"
                                           style="display: inline-block; padding: 14px 32px; background: #2271b1; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                                            Download Certificate
                                        </a>
                                    </div>

                                    <p style="margin: 30px 0 0; font-size: 14px; color: #666;">
                                        <strong>Note:</strong> Please keep this certificate for your records. You may need it for continuing education credits or professional development documentation.
                                    </p>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background: #f8f9fa; padding: 25px; text-align: center; border-radius: 0 0 8px 8px;">
                                    <p style="margin: 0; font-size: 14px; color: #666;">
                                        Thank you for choosing GPS Dental Training
                                    </p>
                                    <p style="margin: 10px 0 0; font-size: 12px; color: #999;">
                                        © <?php echo date('Y'); ?> GPS Dental Training. All rights reserved.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle certificate downloads
     */
    public static function handle_download() {
        if (!isset($_GET['download_certificate'])) {
            return;
        }

        $cert_id = (int) $_GET['download_certificate'];

        global $wpdb;

        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_certificates WHERE id = %d",
            $cert_id
        ));

        if (!$certificate) {
            wp_die(__('Certificate not found.', 'gps-courses'));
        }

        // Verify user owns this certificate or is admin
        if ($certificate->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            wp_die(__('Unauthorized access.', 'gps-courses'));
        }

        if (file_exists($certificate->certificate_path)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="certificate.pdf"');
            header('Content-Length: ' . filesize($certificate->certificate_path));
            readfile($certificate->certificate_path);
            exit;
        }

        wp_die(__('Certificate file not found.', 'gps-courses'));
    }

    /**
     * AJAX: Preview certificate with current settings
     */
    public static function ajax_preview_certificate() {
        check_ajax_referer('gps_preview_certificate', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        // Prevent admin notices and other output during PDF generation
        \remove_all_actions('admin_notices');
        \remove_all_actions('all_admin_notices');
        \remove_all_actions('network_admin_notices');
        \remove_all_actions('user_admin_notices');

        // Disable WordPress debug output for this request
        @ini_set('display_errors', 0);

        // Get preview settings from AJAX request
        $preview_settings = isset($_POST['settings']) ? $_POST['settings'] : [];

        // Clean ALL output buffers before starting to prevent contamination
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        try {
            // Create PDF
            $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

            // Set document information
            $pdf->SetCreator('GPS Dental Training');
            $pdf->SetAuthor('GPS Dental Training');
            $pdf->SetTitle('Certificate Preview');

            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Set margins
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);

            // Add a page
            $pdf->AddPage();

            // Render certificate content with preview data
            self::render_certificate_content_preview($pdf, [
                'attendee_name' => 'John Doe Sample',
                'event_title' => 'Sample Course Title - Advanced Dental Implants',
                'event_date' => date('F j, Y'),
                'venue' => 'GPS Dental Training Center',
                'instructor' => 'Dr Carlos Castro DDS, FACP',
                'certificate_code' => 'PREVIEW-' . strtoupper(substr(md5(time()), 0, 8)),
            ], $preview_settings);

            // Get PDF as string
            $pdf_content = $pdf->Output('certificate-preview.pdf', 'S');

            // Set proper headers
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdf_content));
            header('Content-Disposition: inline; filename="certificate-preview.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            // Output PDF
            echo $pdf_content;
            exit;
        } catch (\Exception $e) {
            \wp_send_json_error(['message' => 'PDF generation failed: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Render certificate content for preview with custom settings
     */
    private static function render_certificate_content_preview($pdf, $data, $settings) {
        $w = 297; // A4 landscape width in mm
        $h = 210; // A4 landscape height in mm

        // Helper to get setting value
        $get_setting = function($key, $default = '') use ($settings) {
            return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
        };

        // Get settings from preview
        $logo = $get_setting('logo', '');
        $header_title = $get_setting('header_title', 'GPS DENTAL');
        $header_subtitle = $get_setting('header_subtitle', 'TRAINING');
        $header_bg = $get_setting('header_bg_color', '#193463');
        $header_text_color = $get_setting('header_text_color', '#FFFFFF');
        $main_title = $get_setting('main_title', 'CERTIFICATE');
        $main_subtitle = $get_setting('main_subtitle', 'OF COMPLETION');
        $description = $get_setting('description_text', 'This letter certified the person below participated in the following course by GPS Dental Training.');
        $program_provider = $get_setting('program_provider', 'Program Provider: GPS Dental Training');
        $course_title_label = $get_setting('course_title_label', 'Course Title');
        $code_label = $get_setting('code_label', 'CODE');
        $code_bg = $get_setting('code_bg_color', '#BC9D67');
        $primary_color = $get_setting('primary_color', '#193463');
        $secondary_color = $get_setting('secondary_color', '#BC9D67');
        $date_color = $get_setting('date_color', '#3498db');
        $instructor_label = $get_setting('instructor_label', 'Instructor Name:');
        $course_method_label = $get_setting('course_method_label', 'Course Method:');
        $course_method = $get_setting('course_method_default', 'In Person');
        $location_label = $get_setting('location_label', 'Course Location:');
        $pace_text = $get_setting('pace_text', "GPS Dental Training LLC.\nNationally Approved PACE Program\nProvider for FAGD/MAGD credit.");
        $pace_logo = $get_setting('pace_logo', '');
        $show_pace = $get_setting('show_pace', true);
        $signature_image = $get_setting('signature_image', '');
        $enable_qr_code = $get_setting('enable_qr_code', true);
        $qr_code_position = $get_setting('qr_code_position', 'bottom-right');

        // Get font sizes
        $header_title_size = floatval($get_setting('header_title_size', 20));
        $header_subtitle_size = floatval($get_setting('header_subtitle_size', 14));
        $main_title_size = floatval($get_setting('main_title_size', 32));
        $main_subtitle_size = floatval($get_setting('main_subtitle_size', 14));
        $attendee_name_size = floatval($get_setting('attendee_name_size', 24));
        $event_title_size = floatval($get_setting('event_title_size', 16));
        $description_size = floatval($get_setting('description_size', 10));
        $date_size = floatval($get_setting('date_size', 11));
        $footer_size = floatval($get_setting('footer_size', 9));
        $pace_text_size = floatval($get_setting('pace_text_size', 6.5));

        // Convert hex colors to RGB
        $header_bg_rgb = self::hex_to_rgb($header_bg);
        $header_text_rgb = self::hex_to_rgb($header_text_color);
        $code_bg_rgb = self::hex_to_rgb($code_bg);
        $primary_rgb = self::hex_to_rgb($primary_color);
        $secondary_rgb = self::hex_to_rgb($secondary_color);
        $date_rgb = self::hex_to_rgb($date_color);

        // Background
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, $w, $h, 'F');

        // Outer border
        $pdf->SetLineWidth(0.5);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->RoundedRect(10, 10, $w - 20, $h - 20, 5, '1111', 'D');

        // Header Section - Reduced height from 30 to 25mm
        $pdf->SetFillColor($header_bg_rgb[0], $header_bg_rgb[1], $header_bg_rgb[2]);
        $pdf->Rect(15, 15, $w - 30, 25, 'F');

        // Logo or Header Title
        if (!empty($logo)) {
            // Convert URL to file path
            $upload_dir = \wp_upload_dir();
            $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo);

            // Display logo - reduced size (skip SVG files as TCPDF doesn't support them)
            if (file_exists($logo_path) && !preg_match('/\.svg$/i', $logo_path)) {
                list($img_width, $img_height) = @getimagesize($logo_path);
                if ($img_width && $img_height) {
                    $logo_height = 16;
                    $logo_width = ($img_width / $img_height) * $logo_height;
                    $logo_x = ($w - $logo_width) / 2;
                    $pdf->Image($logo_path, $logo_x, 19, $logo_width, $logo_height, '', '', '', true, 300);
                }
            } else {
                // Logo not found or is SVG - show text header instead
                $pdf->SetTextColor($header_text_rgb[0], $header_text_rgb[1], $header_text_rgb[2]);
                $pdf->SetFont('helvetica', 'B', $header_title_size);
                $pdf->SetXY(20, 20);
                $pdf->Cell($w - 40, 8, $header_title, 0, 1, 'C');

                $pdf->SetTextColor($secondary_rgb[0], $secondary_rgb[1], $secondary_rgb[2]);
                $pdf->SetFont('helvetica', '', $header_subtitle_size);
                $pdf->SetXY(20, 28);
                $pdf->Cell($w - 40, 6, $header_subtitle, 0, 1, 'C');
            }
        } else {
            $pdf->SetTextColor($header_text_rgb[0], $header_text_rgb[1], $header_text_rgb[2]);
            $pdf->SetFont('helvetica', 'B', $header_title_size);
            $pdf->SetXY(20, 20);
            $pdf->Cell($w - 40, 8, $header_title, 0, 1, 'C');

            $pdf->SetTextColor($secondary_rgb[0], $secondary_rgb[1], $secondary_rgb[2]);
            $pdf->SetFont('helvetica', '', $header_subtitle_size);
            $pdf->SetXY(20, 28);
            $pdf->Cell($w - 40, 6, $header_subtitle, 0, 1, 'C');
        }

        // Main Certificate Title - moved up
        $pdf->SetFont('helvetica', 'B', $main_title_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 48);
        $pdf->Cell($w - 40, 10, $main_title, 0, 1, 'C');

        // OF COMPLETION Box - moved up, no border
        $pdf->SetFont('helvetica', '', $main_subtitle_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 60);
        $pdf->Cell($w - 40, 8, $main_subtitle, 0, 1, 'C');

        // Description Text - moved up and reduced spacing
        $pdf->SetFont('helvetica', '', $description_size);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(40, 72);
        $pdf->MultiCell($w - 80, 4, $description, 0, 'C');

        // Attendee Name (Large) - moved up
        $pdf->SetFont('helvetica', 'B', $attendee_name_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 85);
        $pdf->Cell($w - 40, 8, $data['attendee_name'], 0, 1, 'C');

        // Program Provider - moved up
        $pdf->SetFont('helvetica', 'B', $description_size);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(20, 96);
        $pdf->Cell($w - 40, 4, $program_provider, 0, 1, 'C');

        // Event Date - moved up
        $pdf->SetFont('helvetica', '', $date_size);
        $pdf->SetTextColor($date_rgb[0], $date_rgb[1], $date_rgb[2]);
        $pdf->SetXY(20, 102);
        $pdf->Cell($w - 40, 5, $data['event_date'], 0, 1, 'C');

        // Course Title Label - moved up
        $pdf->SetFont('helvetica', '', $footer_size);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(20, 112);
        $pdf->Cell($w - 40, 3, $course_title_label, 0, 1, 'C');

        // Event Title (Course Name) - moved up
        $pdf->SetFont('helvetica', 'B', $event_title_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(30, 118);
        $pdf->Cell($w - 60, 7, $data['event_title'], 0, 1, 'C');

        // Certificate Code with Background - moved up
        $pdf->SetFont('helvetica', '', $footer_size);
        $pdf->SetFillColor($code_bg_rgb[0], $code_bg_rgb[1], $code_bg_rgb[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->RoundedRect(($w - 70) / 2, 130, 70, 7, 2, '1111', 'F');
        $pdf->SetXY(($w - 70) / 2, 131);
        $pdf->Cell(70, 5, $code_label . ' #' . $data['certificate_code'], 0, 1, 'C', false);

        // Instructor Signature - moved up and reduced size
        if (!empty($signature_image)) {
            // Convert URL to file path
            $upload_dir = \wp_upload_dir();
            $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_image);

            if (file_exists($signature_path) && !preg_match('/\.svg$/i', $signature_path)) {
                list($img_width, $img_height) = @getimagesize($signature_path);
                if ($img_width && $img_height) {
                    $sig_height = 12;
                    $sig_width = ($img_width / $img_height) * $sig_height;
                    $sig_x = ($w - $sig_width) / 2;
                    $pdf->Image($signature_path, $sig_x, 141, $sig_width, $sig_height, '', '', '', true, 300);
                }
            } else {
                $pdf->SetFont('zapfdingbats', '', 20);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->SetXY(20, 142);
                $pdf->Cell($w - 40, 7, chr(252), 0, 1, 'C');
            }
        } else {
            $pdf->SetFont('zapfdingbats', '', 20);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY(20, 142);
            $pdf->Cell($w - 40, 7, chr(252), 0, 1, 'C');
        }

        // Instructor Name - centered to align with signature
        $pdf->SetFont('helvetica', 'B', $footer_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 155);
        $pdf->Cell($w - 40, 4, $instructor_label . ' ' . $data['instructor'], 0, 1, 'C');

        // Course Method - new line, centered
        $pdf->SetFont('helvetica', 'B', $footer_size);
        $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
        $pdf->SetXY(20, 160);
        $pdf->Cell($w - 40, 4, $course_method_label . ' ' . $course_method, 0, 1, 'C');

        // Course Location - moved up
        if (!empty($data['venue'])) {
            $pdf->SetFont('helvetica', '', $footer_size);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetXY(20, 166);
            $pdf->Cell($w - 40, 4, $location_label . ' ' . $data['venue'], 0, 1, 'C');
        }

        // QR Code for Certificate Validation - positioned first to avoid overlap
        if (($enable_qr_code == 'true' || $enable_qr_code === true || $enable_qr_code == 1) && !empty($data['certificate_code'])) {
            $validation_url = home_url('/certificate-validation?code=' . $data['certificate_code']);
            $qr_size = 18; // Reduced size from 20 to 18mm
            $qr_y = 173; // Position from top - matches main function

            if ($qr_code_position === 'bottom-left') {
                $qr_x = 18;
            } else {
                $qr_x = $w - $qr_size - 18;
            }

            $pdf->write2DBarcode($validation_url, 'QRCODE,L', $qr_x, $qr_y, $qr_size, $qr_size, [], 'N');
        }

        // PACE Section at Bottom - full height background with rounded corners
        if ($show_pace == 'true' || $show_pace === true || $show_pace == 1) {
            // Light background for PACE section - extends close to bottom border without overlap
            $pace_y = 173;
            $pace_height = 25; // Reduced from 27mm to 25mm to avoid border overlap

            $pdf->SetFillColor(240, 245, 250);

            // Adjust PACE section width if QR code is on the left
            if (($enable_qr_code == 'true' || $enable_qr_code === true || $enable_qr_code == 1) && $qr_code_position === 'bottom-left') {
                // Start PACE section after QR code
                $pace_start_x = 45;
                $pace_width = $w - 60;
                $pdf->RoundedRect($pace_start_x, $pace_y, $pace_width, $pace_height, 3.5, '1111', 'F');
                $pace_x = $pace_start_x + 5;
            } else {
                // Standard full width, QR code will be on the right
                $pace_start_x = 15;
                $pace_width = (($enable_qr_code == 'true' || $enable_qr_code === true || $enable_qr_code == 1)) ? $w - 55 : $w - 30;
                $pdf->RoundedRect($pace_start_x, $pace_y, $pace_width, $pace_height, 3.5, '1111', 'F');
                $pace_x = $pace_start_x + 10;
            }

            // PACE Logo (if available) - vertically centered in the section
            if (!empty($pace_logo)) {
                // Convert URL to file path
                $upload_dir = \wp_upload_dir();
                $pace_logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pace_logo);

                if (file_exists($pace_logo_path) && !preg_match('/\.svg$/i', $pace_logo_path)) {
                    list($img_width, $img_height) = @getimagesize($pace_logo_path);
                    if ($img_width && $img_height) {
                        $pace_logo_height = 12;
                        $pace_logo_width = ($img_width / $img_height) * $pace_logo_height;
                        // Center vertically in pace section
                        $logo_y = $pace_y + ($pace_height - $pace_logo_height) / 2;
                        $pdf->Image($pace_logo_path, $pace_x, $logo_y, $pace_logo_width, $pace_logo_height, '', '', '', true, 300);
                        $pace_x += $pace_logo_width + 6;
                    }
                }
            }

            // PACE Text - vertically centered
            if (!empty($pace_text)) {
                $pdf->SetFont('helvetica', '', $pace_text_size);
                $pdf->SetTextColor(40, 40, 40);
                // Center text vertically
                $pdf->SetXY($pace_x, $pace_y + 3);
                // Limit width to prevent overflow with QR code
                $available_width = $pace_start_x + $pace_width - $pace_x - 5;
                $pdf->MultiCell($available_width, 2, $pace_text, 0, 'L');
            }
        }
    }
}
