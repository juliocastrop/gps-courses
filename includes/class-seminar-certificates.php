<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Seminar Certificates
 *
 * Handles bi-annual certificate generation and delivery (June 30 & December 31)
 * for GPS Monthly Seminars participants.
 */
class Seminar_Certificates {

    /**
     * Initialize
     */
    public static function init() {
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // Schedule certificate generation cron
        add_action('gps_generate_seminar_certificates', [__CLASS__, 'generate_biannual_certificates']);

        // Register cron schedules
        self::schedule_certificate_generation();

        // AJAX handlers
        add_action('wp_ajax_gps_generate_seminar_certificate', [__CLASS__, 'ajax_generate_certificate']);
        add_action('wp_ajax_gps_regenerate_seminar_certificate', [__CLASS__, 'ajax_regenerate_certificate']);
        add_action('wp_ajax_gps_send_seminar_certificate', [__CLASS__, 'ajax_send_certificate']);
        add_action('wp_ajax_gps_get_seminar_registrations', [__CLASS__, 'ajax_get_seminar_registrations']);
        add_action('wp_ajax_gps_bulk_send_seminar_certificates', [__CLASS__, 'ajax_bulk_send_certificates']);
        add_action('wp_ajax_gps_bulk_regenerate_seminar_certificates', [__CLASS__, 'ajax_bulk_regenerate_certificates']);
        add_action('wp_ajax_gps_download_certificate', [__CLASS__, 'ajax_download_certificate']);
        add_action('wp_ajax_gps_preview_seminar_certificate', [__CLASS__, 'ajax_preview_certificate']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Seminar Certificates', 'gps-courses'),
            __('Seminar Certificates', 'gps-courses'),
            'manage_options',
            'gps-seminar-certificates',
            [__CLASS__, 'render_certificates_page']
        );
    }

    /**
     * Enqueue scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-seminar-certificates') {
            return;
        }

        wp_enqueue_script(
            'gps-seminar-certificates',
            GPSC_URL . 'assets/js/admin-seminar-certificates.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        wp_enqueue_style(
            'gps-seminar-certificates',
            GPSC_URL . 'assets/css/admin-seminar-certificates.css',
            [],
            GPSC_VERSION
        );

        wp_localize_script('gps-seminar-certificates', 'gpsSeminarCertificates', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_seminar_certificates_nonce'),
            'i18n' => [
                'generating' => __('Generating certificate...', 'gps-courses'),
                'sending' => __('Sending certificate...', 'gps-courses'),
                'success' => __('Certificate sent successfully!', 'gps-courses'),
                'error' => __('An error occurred. Please try again.', 'gps-courses'),
                'confirm_bulk' => __('Send certificates to {count} participants?', 'gps-courses'),
                'loading' => __('Loading...', 'gps-courses'),
                'select_seminar' => __('Please select a seminar first.', 'gps-courses'),
            ],
        ]);
    }

    /**
     * Render certificates admin page
     */
    public static function render_certificates_page() {
        // Get all seminars
        $seminars = get_posts([
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_gps_seminar_year',
            'order' => 'DESC',
        ]);

        ?>
        <div class="wrap" id="gps-seminar-certificates-page">
            <h1>📜 <?php _e('Monthly Seminar Certificates', 'gps-courses'); ?></h1>
            <p class="description">
                <?php _e('Manage CE credit certificates for GPS Monthly Seminars. Certificates are issued bi-annually (June 30 & December 31) but can be generated manually here.', 'gps-courses'); ?>
            </p>

            <!-- Seminar Selector Section -->
            <div class="seminar-selector-section">
                <label for="seminar-selector"><strong><?php _e('Select Seminar:', 'gps-courses'); ?></strong></label>
                <select id="seminar-selector" class="gps-select">
                    <option value=""><?php _e('— Select Seminar —', 'gps-courses'); ?></option>
                    <?php foreach ($seminars as $seminar): ?>
                        <?php
                        $year = get_post_meta($seminar->ID, '_gps_seminar_year', true);
                        $label = $seminar->post_title . ($year ? ' (' . $year . ')' : '');
                        ?>
                        <option value="<?php echo esc_attr($seminar->ID); ?>">
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="period-selector" style="margin-left: 20px;"><strong><?php _e('Certificate Period:', 'gps-courses'); ?></strong></label>
                <select id="period-selector" class="gps-select">
                    <option value="first_half"><?php _e('January - June', 'gps-courses'); ?></option>
                    <option value="second_half" selected><?php _e('July - December', 'gps-courses'); ?></option>
                </select>
            </div>

            <!-- Statistics Section -->
            <div class="certificate-stats">
                <div class="stat-card total">
                    <span class="stat-icon">👥</span>
                    <span class="stat-value" id="stat-total">0</span>
                    <span class="stat-label"><?php _e('Total Registrations', 'gps-courses'); ?></span>
                </div>
                <div class="stat-card eligible">
                    <span class="stat-icon">✅</span>
                    <span class="stat-value" id="stat-eligible">0</span>
                    <span class="stat-label"><?php _e('Eligible for Certificate', 'gps-courses'); ?></span>
                </div>
                <div class="stat-card generated">
                    <span class="stat-icon">📜</span>
                    <span class="stat-value" id="stat-generated">0</span>
                    <span class="stat-label"><?php _e('Certificates Generated', 'gps-courses'); ?></span>
                </div>
                <div class="stat-card sent">
                    <span class="stat-icon">📧</span>
                    <span class="stat-value" id="stat-sent">0</span>
                    <span class="stat-label"><?php _e('Certificates Sent', 'gps-courses'); ?></span>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div id="loading-indicator" style="display: none;">
                <div class="loading-spinner"></div>
                <?php _e('Loading registrations...', 'gps-courses'); ?>
            </div>

            <!-- Registrations Section -->
            <div class="registrations-section">
                <div class="registrations-section-header">
                    <h2><?php _e('Participant Registrations', 'gps-courses'); ?></h2>
                    <div class="header-actions">
                        <button type="button" id="generate-all-certificates" class="button button-primary" style="display: none;">
                            📜 <?php _e('Generate All Missing Certificates', 'gps-courses'); ?>
                        </button>
                    </div>
                </div>

                <div id="bulk-actions" class="bulk-actions" style="display: none;">
                    <label>
                        <input type="checkbox" id="select-all-registrations">
                        <?php _e('Select All', 'gps-courses'); ?>
                    </label>
                    <button type="button" id="bulk-regenerate-certificates" class="button" disabled>
                        🔄 <?php _e('Regenerate Selected', 'gps-courses'); ?>
                    </button>
                    <button type="button" id="bulk-send-certificates" class="button button-primary" disabled>
                        📧 <?php _e('Send Selected Certificates', 'gps-courses'); ?>
                    </button>
                </div>

                <table id="registrations-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="select-all-header"></th>
                            <th><?php _e('Participant', 'gps-courses'); ?></th>
                            <th><?php _e('Email', 'gps-courses'); ?></th>
                            <th><?php _e('Sessions Attended', 'gps-courses'); ?></th>
                            <th><?php _e('Credits Earned', 'gps-courses'); ?></th>
                            <th><?php _e('Certificate Status', 'gps-courses'); ?></th>
                            <th><?php _e('Actions', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="no-registrations">
                                <?php _e('Please select a seminar to view registrations.', 'gps-courses'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Certificate Preview Modal -->
            <div id="certificate-preview-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><?php _e('Certificate Preview', 'gps-courses'); ?></h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <iframe id="certificate-preview-frame"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Schedule certificate generation for June 30 and December 31
     */
    private static function schedule_certificate_generation() {
        // Check if already scheduled
        if (!wp_next_scheduled('gps_generate_seminar_certificates')) {
            // Schedule for June 30 and December 31 at 11:00 PM
            $june_30 = strtotime('June 30 ' . date('Y') . ' 23:00:00');
            $dec_31 = strtotime('December 31 ' . date('Y') . ' 23:00:00');

            $now = time();

            if ($june_30 > $now) {
                wp_schedule_single_event($june_30, 'gps_generate_seminar_certificates', ['period' => 'first_half']);
            }

            if ($dec_31 > $now) {
                wp_schedule_single_event($dec_31, 'gps_generate_seminar_certificates', ['period' => 'second_half']);
            }
        }
    }

    /**
     * Generate certificates for all active participants (bi-annual)
     */
    public static function generate_biannual_certificates($period = 'second_half') {
        global $wpdb;

        $current_year = date('Y');

        // Define date ranges
        if ($period === 'first_half') {
            $start_date = $current_year . '-01-01';
            $end_date = $current_year . '-06-30';
            $certificate_period = 'January - June ' . $current_year;
        } else {
            $start_date = $current_year . '-07-01';
            $end_date = $current_year . '-12-31';
            $certificate_period = 'July - December ' . $current_year;
        }

        // Get all active registrations
        $registrations = $wpdb->get_results(
            "SELECT DISTINCT registration_id
             FROM {$wpdb->prefix}gps_seminar_attendance
             WHERE checked_in_at BETWEEN '{$start_date} 00:00:00' AND '{$end_date} 23:59:59'"
        );

        $generated_count = 0;

        foreach ($registrations as $reg) {
            $certificate_path = self::generate_certificate($reg->registration_id, $period, $certificate_period);

            if ($certificate_path) {
                // Send certificate email
                self::send_certificate_email($reg->registration_id, $certificate_path, $certificate_period);
                $generated_count++;
            }
        }

        error_log("GPS Seminars: Generated {$generated_count} certificates for {$certificate_period}");

        // Reschedule for next year
        self::schedule_certificate_generation();

        return $generated_count;
    }

    /**
     * Generate certificate PDF for a registration
     */
    public static function generate_certificate($registration_id, $period = 'second_half', $certificate_period = null) {
        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration) {
            return false;
        }

        $user = get_userdata($registration->user_id);
        if (!$user) {
            return false;
        }

        // Calculate credits earned in period
        $credits = self::get_credits_for_period($registration_id, $period);

        if ($credits <= 0) {
            return false; // No sessions attended in this period
        }

        // Generate certificate period text if not provided
        if (!$certificate_period) {
            $current_year = date('Y');
            $certificate_period = $period === 'first_half'
                ? 'January - June ' . $current_year
                : 'July - December ' . $current_year;
        }

        // Create certificate using existing certificate system
        $certificate_data = [
            'user_name' => $user->display_name,
            'credits' => $credits,
            'period' => $certificate_period,
            'issue_date' => date('F j, Y'),
            'registration_id' => $registration_id,
        ];

        $certificate_path = self::create_pdf_certificate($certificate_data);

        // Save certificate record
        if ($certificate_path) {
            self::save_certificate_record($registration_id, $certificate_path, $period);
        }

        return $certificate_path;
    }

    /**
     * Get credits earned during a specific period
     */
    private static function get_credits_for_period($registration_id, $period) {
        global $wpdb;

        $current_year = date('Y');

        if ($period === 'first_half') {
            $start_date = $current_year . '-01-01';
            $end_date = $current_year . '-06-30';
        } else {
            $start_date = $current_year . '-07-01';
            $end_date = $current_year . '-12-31';
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(credits_awarded)
             FROM {$wpdb->prefix}gps_seminar_attendance
             WHERE registration_id = %d
             AND checked_in_at BETWEEN %s AND %s",
            $registration_id,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    }

    /**
     * Create PDF certificate using TCPDF
     */
    private static function create_pdf_certificate($data) {
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/gps-certificates/seminar/';

        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }

        $filename = 'seminar-cert-' . $data['registration_id'] . '-' . time() . '.pdf';
        $filepath = $cert_dir . $filename;

        // Create PDF using TCPDF
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('GPS Dental Training');
        $pdf->SetAuthor('GPS Dental Training');
        $pdf->SetTitle('CE Credit Certificate - Monthly Seminars');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        // Add a page
        $pdf->AddPage();

        // Render certificate content
        self::render_seminar_certificate_content($pdf, $data);

        // Output PDF to file
        $pdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Render seminar certificate content using TCPDF
     */
    private static function render_seminar_certificate_content($pdf, $data) {
        $w = 297; // A4 landscape width in mm
        $h = 210; // A4 landscape height in mm

        // Get certificate settings for logo
        $settings = \get_option('gps_certificate_settings', []);
        $logo = $settings['logo'] ?? '';

        // Background
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, $w, $h, 'F');

        // Decorative border
        $pdf->SetLineWidth(1);
        $pdf->SetDrawColor(34, 113, 177); // #2271b1
        $pdf->RoundedRect(10, 10, $w - 20, $h - 20, 5, '1111', 'D');

        $pdf->SetLineWidth(0.5);
        $pdf->RoundedRect(15, 15, $w - 30, $h - 30, 4, '1111', 'D');

        // Header section
        $pdf->SetFillColor(34, 113, 177);
        $pdf->Rect(20, 20, $w - 40, 30, 'F');

        // Logo (if available)
        if (!empty($logo)) {
            // Convert URL to file path
            $upload_dir = \wp_upload_dir();
            $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo);

            if (file_exists($logo_path)) {
                $pdf->Image($logo_path, 25, 23, 30, 0, '', '', '', false, 300, '', false, false, 0);
            }
        }

        // Header text
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetXY(20, 27);
        $pdf->Cell($w - 40, 8, 'GPS DENTAL TRAINING', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetXY(20, 37);
        $pdf->Cell($w - 40, 6, 'Monthly Seminars Program', 0, 1, 'C');

        // Main title
        $pdf->SetTextColor(34, 113, 177);
        $pdf->SetFont('helvetica', 'B', 32);
        $pdf->SetXY(20, 65);
        $pdf->Cell($w - 40, 10, 'Certificate of Completion', 0, 1, 'C');

        // Subtitle
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetXY(20, 78);
        $pdf->Cell($w - 40, 6, 'Continuing Education Credits', 0, 1, 'C');

        // "This is to certify" text
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetXY(20, 92);
        $pdf->Cell($w - 40, 6, 'This is to certify that', 0, 1, 'C');

        // Participant name
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'BI', 24);
        $pdf->SetXY(20, 102);
        $pdf->Cell($w - 40, 10, $data['user_name'], 0, 1, 'C');

        // Underline for name
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(34, 113, 177);
        $name_width = $pdf->GetStringWidth($data['user_name']) + 20;
        $start_x = ($w - $name_width) / 2;
        $pdf->Line($start_x, 113, $start_x + $name_width, 113);

        // "has successfully completed" text
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetXY(20, 120);
        $pdf->Cell($w - 40, 6, 'has successfully completed', 0, 1, 'C');

        // Credits box
        $pdf->SetFillColor(240, 248, 255);
        $pdf->SetDrawColor(34, 113, 177);
        $pdf->SetLineWidth(0.5);
        $box_width = 120;
        $box_x = ($w - $box_width) / 2;
        $pdf->RoundedRect($box_x, 132, $box_width, 18, 3, '1111', 'DF');

        $pdf->SetTextColor(34, 113, 177);
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetXY($box_x, 136);
        $pdf->Cell($box_width, 10, $data['credits'] . ' CE Credits', 0, 1, 'C');

        // Period
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont('helvetica', '', 13);
        $pdf->SetXY(20, 156);
        $pdf->Cell($w - 40, 6, 'Period: ' . $data['period'], 0, 1, 'C');

        // Issue date
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetXY(20, 166);
        $pdf->Cell($w - 40, 5, 'Issued: ' . $data['issue_date'], 0, 1, 'C');

        // Footer section
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(20, 180);
        $pdf->Cell($w - 40, 4, 'GPS Dental Training Center', 0, 1, 'C');

        $pdf->SetXY(20, 185);
        $pdf->Cell($w - 40, 4, '6320 Sugarloaf Parkway, Duluth, GA 30097', 0, 1, 'C');

        // Registration ID (small, bottom right)
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($w - 80, $h - 15);
        $pdf->Cell(60, 4, 'Registration ID: ' . $data['registration_id'], 0, 0, 'R');
    }

    /**
     * Save certificate record to database
     */
    private static function save_certificate_record($registration_id, $certificate_path, $period) {
        global $wpdb;

        $registration = Seminar_Registrations::get_registration($registration_id);

        // Use existing certificates table
        return $wpdb->insert(
            $wpdb->prefix . 'gps_certificates',
            [
                'ticket_id' => 0, // N/A for seminars
                'user_id' => $registration->user_id,
                'event_id' => $registration->seminar_id,
                'certificate_path' => $certificate_path,
                'certificate_url' => self::get_certificate_url($certificate_path),
                'generated_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Get certificate URL
     */
    private static function get_certificate_url($certificate_path) {
        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $certificate_path);
    }

    /**
     * Send certificate email
     */
    private static function send_certificate_email($registration_id, $certificate_path, $period) {
        $registration = Seminar_Registrations::get_registration($registration_id);
        $user = get_userdata($registration->user_id);

        if (!$user) {
            return false;
        }

        $certificate_url = self::get_certificate_url($certificate_path);
        $credits = self::get_credits_for_period($registration_id, strpos($period, 'January') !== false ? 'first_half' : 'second_half');

        $to = $user->user_email;
        $subject = sprintf(__('Your CE Credit Certificate - %s', 'gps-courses'), $period);

        $message = '
        <h2>Your CE Credit Certificate is Ready!</h2>
        <p>Dear ' . esc_html($user->display_name) . ',</p>
        <p>Congratulations! Your CE Credit certificate for the GPS Monthly Seminars program is now available.</p>

        <h3>Certificate Details:</h3>
        <ul>
            <li><strong>Period:</strong> ' . esc_html($period) . '</li>
            <li><strong>Credits Earned:</strong> ' . $credits . ' CE Credits</li>
            <li><strong>Issue Date:</strong> ' . date('F j, Y') . '</li>
        </ul>

        <p style="text-align: center; margin: 30px 0;">
            <a href="' . esc_url($certificate_url) . '" style="display: inline-block; padding: 15px 30px; background: #2271b1; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Download Your Certificate
            </a>
        </p>

        <p>Keep up the excellent work in your continuing education!</p>
        <p>Best regards,<br>GPS Dental Training</p>
        ';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: GPS Dental Training <noreply@gpsdentaltraining.com>',
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * AJAX: Generate certificate manually
     */
    public static function ajax_generate_certificate() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $registration_id = (int) $_POST['registration_id'];
        $period = sanitize_text_field($_POST['period'] ?? 'second_half');

        $certificate_path = self::generate_certificate($registration_id, $period);

        if ($certificate_path) {
            wp_send_json_success([
                'certificate_url' => self::get_certificate_url($certificate_path),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to generate certificate', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Download certificate
     */
    public static function ajax_download_certificate() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        $registration_id = (int) $_GET['registration_id'];
        $registration = Seminar_Registrations::get_registration($registration_id);

        if (!$registration) {
            wp_die(__('Registration not found', 'gps-courses'));
        }

        // Verify user owns this registration or is admin
        if ($registration->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'gps-courses'));
        }

        // Find latest certificate
        global $wpdb;
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_certificates
             WHERE user_id = %d AND event_id = %d
             ORDER BY generated_at DESC LIMIT 1",
            $registration->user_id,
            $registration->seminar_id
        ));

        if (!$certificate || !file_exists($certificate->certificate_path)) {
            wp_die(__('Certificate not found', 'gps-courses'));
        }

        // Serve file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="gps-seminar-certificate.pdf"');
        readfile($certificate->certificate_path);
        exit;
    }

    /**
     * AJAX: Get seminar registrations with certificate status
     */
    public static function ajax_get_seminar_registrations() {
        check_ajax_referer('gps_seminar_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $seminar_id = isset($_POST['seminar_id']) ? (int) $_POST['seminar_id'] : 0;
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'second_half';

        if (empty($seminar_id)) {
            wp_send_json_error(['message' => __('Invalid seminar', 'gps-courses')]);
        }

        global $wpdb;

        // Determine date range
        $current_year = date('Y');
        if ($period === 'first_half') {
            $start_date = $current_year . '-01-01';
            $end_date = $current_year . '-06-30';
            $period_label = 'January - June ' . $current_year;
        } else {
            $start_date = $current_year . '-07-01';
            $end_date = $current_year . '-12-31';
            $period_label = 'July - December ' . $current_year;
        }

        // Get all registrations for this seminar with attendance data
        $registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT
                r.id as registration_id,
                r.user_id,
                r.qr_code,
                u.display_name as user_name,
                u.user_email,
                (SELECT COUNT(*)
                 FROM {$wpdb->prefix}gps_seminar_attendance sa
                 WHERE sa.registration_id = r.id
                 AND sa.checked_in_at BETWEEN %s AND %s) as sessions_attended,
                (SELECT SUM(credits_awarded)
                 FROM {$wpdb->prefix}gps_seminar_attendance sa
                 WHERE sa.registration_id = r.id
                 AND sa.checked_in_at BETWEEN %s AND %s) as credits_earned,
                c.certificate_path,
                c.certificate_url,
                c.generated_at as certificate_generated_at
            FROM {$wpdb->prefix}gps_seminar_registrations r
            INNER JOIN {$wpdb->users} u ON r.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}gps_certificates c ON c.user_id = r.user_id AND c.event_id = r.seminar_id
            WHERE r.seminar_id = %d
            AND r.status = 'active'
            ORDER BY u.display_name ASC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
            $seminar_id
        ));

        // Format the data
        foreach ($registrations as &$reg) {
            $reg->sessions_attended = (int) $reg->sessions_attended;
            $reg->credits_earned = (int) $reg->credits_earned;
            $reg->has_certificate = !empty($reg->certificate_path);
            $reg->eligible_for_certificate = $reg->credits_earned > 0;
        }

        wp_send_json_success([
            'registrations' => $registrations,
            'period_label' => $period_label,
        ]);
    }

    /**
     * AJAX: Regenerate certificate
     */
    public static function ajax_regenerate_certificate() {
        check_ajax_referer('gps_seminar_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'second_half';

        if (empty($registration_id)) {
            wp_send_json_error(['message' => __('Invalid registration', 'gps-courses')]);
        }

        $certificate_path = self::generate_certificate($registration_id, $period);

        if ($certificate_path) {
            wp_send_json_success([
                'message' => __('Certificate regenerated successfully', 'gps-courses'),
                'certificate_url' => self::get_certificate_url($certificate_path),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to regenerate certificate', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Send certificate
     */
    public static function ajax_send_certificate() {
        check_ajax_referer('gps_seminar_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'second_half';

        if (empty($registration_id)) {
            wp_send_json_error(['message' => __('Invalid registration', 'gps-courses')]);
        }

        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration) {
            wp_send_json_error(['message' => __('Registration not found', 'gps-courses')]);
        }

        // Check if certificate exists
        global $wpdb;
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_certificates
             WHERE user_id = %d AND event_id = %d
             ORDER BY generated_at DESC LIMIT 1",
            $registration->user_id,
            $registration->seminar_id
        ));

        if (!$certificate) {
            // Generate certificate if it doesn't exist
            $certificate_path = self::generate_certificate($registration_id, $period);
            if (!$certificate_path) {
                wp_send_json_error(['message' => __('Failed to generate certificate', 'gps-courses')]);
            }
        } else {
            $certificate_path = $certificate->certificate_path;
        }

        // Determine period label
        $current_year = date('Y');
        $period_label = $period === 'first_half'
            ? 'January - June ' . $current_year
            : 'July - December ' . $current_year;

        // Send certificate email
        $result = self::send_certificate_email($registration_id, $certificate_path, $period_label);

        if ($result) {
            wp_send_json_success([
                'message' => __('Certificate sent successfully', 'gps-courses'),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to send certificate', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Bulk send certificates
     */
    public static function ajax_bulk_send_certificates() {
        check_ajax_referer('gps_seminar_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $registration_ids = isset($_POST['registration_ids']) ? array_map('intval', $_POST['registration_ids']) : [];
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'second_half';

        if (empty($registration_ids)) {
            wp_send_json_error(['message' => __('No registrations selected', 'gps-courses')]);
        }

        $current_year = date('Y');
        $period_label = $period === 'first_half'
            ? 'January - June ' . $current_year
            : 'July - December ' . $current_year;

        $sent_count = 0;
        $failed_count = 0;

        foreach ($registration_ids as $registration_id) {
            $registration = Seminar_Registrations::get_registration($registration_id);
            if (!$registration) {
                $failed_count++;
                continue;
            }

            // Check if certificate exists
            global $wpdb;
            $certificate = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_certificates
                 WHERE user_id = %d AND event_id = %d
                 ORDER BY generated_at DESC LIMIT 1",
                $registration->user_id,
                $registration->seminar_id
            ));

            if (!$certificate) {
                // Generate certificate
                $certificate_path = self::generate_certificate($registration_id, $period);
                if (!$certificate_path) {
                    $failed_count++;
                    continue;
                }
            } else {
                $certificate_path = $certificate->certificate_path;
            }

            // Send email
            if (self::send_certificate_email($registration_id, $certificate_path, $period_label)) {
                $sent_count++;
            } else {
                $failed_count++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('Sent %d certificates. %d failed.', 'gps-courses'), $sent_count, $failed_count),
            'sent_count' => $sent_count,
            'failed_count' => $failed_count,
        ]);
    }

    /**
     * AJAX: Bulk regenerate certificates
     */
    public static function ajax_bulk_regenerate_certificates() {
        check_ajax_referer('gps_seminar_certificates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $registration_ids = isset($_POST['registration_ids']) ? array_map('intval', $_POST['registration_ids']) : [];
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'second_half';

        if (empty($registration_ids)) {
            wp_send_json_error(['message' => __('No registrations selected', 'gps-courses')]);
        }

        $generated_count = 0;
        $failed_count = 0;

        foreach ($registration_ids as $registration_id) {
            if (self::generate_certificate($registration_id, $period)) {
                $generated_count++;
            } else {
                $failed_count++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('Generated %d certificates. %d failed.', 'gps-courses'), $generated_count, $failed_count),
            'generated_count' => $generated_count,
            'failed_count' => $failed_count,
        ]);
    }

    /**
     * AJAX: Preview certificate
     */
    public static function ajax_preview_certificate() {
        check_ajax_referer('gps_preview_seminar_certificate', 'nonce');

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
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

            // Set document information
            $pdf->SetCreator('GPS Dental Training');
            $pdf->SetAuthor('GPS Dental Training');
            $pdf->SetTitle('Seminar Certificate Preview');

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
                'participant_name' => 'John Doe Sample',
                'credits_earned' => 24,
                'period_start' => 'January 1, ' . date('Y'),
                'period_end' => 'June 30, ' . date('Y'),
                'certificate_code' => 'PREVIEW-SEM-' . strtoupper(substr(md5(time()), 0, 8)),
            ], $preview_settings);

            // Get PDF as string
            $pdf_content = $pdf->Output('seminar-certificate-preview.pdf', 'S');

            // Set proper headers
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdf_content));
            header('Content-Disposition: inline; filename="seminar-certificate-preview.pdf"');
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

        // Helper to safely parse color hex to RGB
        $parse_color = function($color, $default_rgb = [25, 52, 99]) {
            if (empty($color) || !is_string($color)) {
                return $default_rgb;
            }
            $result = sscanf($color, "#%02x%02x%02x");
            if ($result && count($result) === 3) {
                return $result;
            }
            return $default_rgb;
        };

        // Get settings from preview (using same settings as regular certificates)
        $logo = $get_setting('logo', '');
        $header_title = $get_setting('header_title', 'GPS DENTAL');
        $header_subtitle = $get_setting('header_subtitle', 'TRAINING');
        $header_bg = $get_setting('header_bg_color', '#193463');
        $header_text_color = $get_setting('header_text_color', '#FFFFFF');
        $main_title = $get_setting('main_title', 'CERTIFICATE');
        $main_subtitle = $get_setting('main_subtitle', 'OF COMPLETION');
        $description = $get_setting('description_text', 'This letter certifies that the person below has successfully completed the GPS Monthly Seminars continuing education program.');
        $program_provider = $get_setting('program_provider', 'Program Provider: GPS Dental Training');
        $code_label = $get_setting('code_label', 'CODE');
        $code_bg = $get_setting('code_bg_color', '#BC9D67');
        $primary_color = $get_setting('primary_color', '#193463');
        $secondary_color = $get_setting('secondary_color', '#BC9D67');
        $date_color = $get_setting('date_color', '#3498db');
        $instructor_label = $get_setting('instructor_label', 'Instructor Name:');
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
        $description_size = floatval($get_setting('description_size', 10));
        $date_size = floatval($get_setting('date_size', 11));
        $footer_size = floatval($get_setting('footer_size', 9));
        $pace_text_size = floatval($get_setting('pace_text_size', 6.5));

        // Convert hex colors to RGB using parse_color helper
        list($header_bg_r, $header_bg_g, $header_bg_b) = $parse_color($header_bg);
        list($header_text_r, $header_text_g, $header_text_b) = $parse_color($header_text_color, [255, 255, 255]);
        list($code_bg_r, $code_bg_g, $code_bg_b) = $parse_color($code_bg, [188, 157, 103]);
        list($primary_r, $primary_g, $primary_b) = $parse_color($primary_color);
        list($secondary_r, $secondary_g, $secondary_b) = $parse_color($secondary_color, [188, 157, 103]);
        list($date_r, $date_g, $date_b) = $parse_color($date_color, [52, 152, 219]);

        // Background
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, $w, $h, 'F');

        // Outer border
        $pdf->SetLineWidth(0.5);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->RoundedRect(10, 10, $w - 20, $h - 20, 5, '1111', 'D');

        // Header Section - same style as regular certificates
        $pdf->SetFillColor($header_bg_r, $header_bg_g, $header_bg_b);
        $pdf->Rect(15, 15, $w - 30, 25, 'F');

        // Logo or Header Title
        if (!empty($logo)) {
            $upload_dir = wp_upload_dir();
            $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo);

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
                $pdf->SetTextColor($header_text_r, $header_text_g, $header_text_b);
                $pdf->SetFont('helvetica', 'B', $header_title_size);
                $pdf->SetXY(20, 20);
                $pdf->Cell($w - 40, 8, $header_title, 0, 1, 'C');

                $pdf->SetTextColor($secondary_r, $secondary_g, $secondary_b);
                $pdf->SetFont('helvetica', '', $header_subtitle_size);
                $pdf->SetXY(20, 28);
                $pdf->Cell($w - 40, 6, $header_subtitle, 0, 1, 'C');
            }
        } else {
            $pdf->SetTextColor($header_text_r, $header_text_g, $header_text_b);
            $pdf->SetFont('helvetica', 'B', $header_title_size);
            $pdf->SetXY(20, 20);
            $pdf->Cell($w - 40, 8, $header_title, 0, 1, 'C');

            $pdf->SetTextColor($secondary_r, $secondary_g, $secondary_b);
            $pdf->SetFont('helvetica', '', $header_subtitle_size);
            $pdf->SetXY(20, 28);
            $pdf->Cell($w - 40, 6, $header_subtitle, 0, 1, 'C');
        }

        // Main Certificate Title
        $pdf->SetFont('helvetica', 'B', $main_title_size);
        $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
        $pdf->SetXY(20, 48);
        $pdf->Cell($w - 40, 10, $main_title, 0, 1, 'C');

        // Subtitle
        $pdf->SetFont('helvetica', '', $main_subtitle_size);
        $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
        $pdf->SetXY(20, 60);
        $pdf->Cell($w - 40, 8, $main_subtitle, 0, 1, 'C');

        // Description Text
        $pdf->SetFont('helvetica', '', $description_size);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(40, 72);
        $pdf->MultiCell($w - 80, 4, $description, 0, 'C');

        // Participant Name (Large)
        $pdf->SetFont('helvetica', 'B', $attendee_name_size);
        $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
        $pdf->SetXY(20, 85);
        $pdf->Cell($w - 40, 8, $data['participant_name'], 0, 1, 'C');

        // Program Provider
        $pdf->SetFont('helvetica', 'B', $description_size);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(20, 96);
        $pdf->Cell($w - 40, 4, $program_provider, 0, 1, 'C');

        // CE Credits with background box (replacing event date position)
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetFillColor($secondary_r, $secondary_g, $secondary_b);
        $pdf->SetTextColor(255, 255, 255);
        $credit_box_width = 70;
        $pdf->RoundedRect(($w - $credit_box_width) / 2, 104, $credit_box_width, 10, 2, '1111', 'F');
        $pdf->SetXY(($w - $credit_box_width) / 2, 106);
        $pdf->Cell($credit_box_width, 6, $data['credits_earned'] . ' CE Credits Earned', 0, 1, 'C', false);

        // Program Period Label
        $pdf->SetFont('helvetica', '', $footer_size);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(20, 118);
        $pdf->Cell($w - 40, 3, 'Program Period:', 0, 1, 'C');

        // Period Dates
        $pdf->SetFont('helvetica', 'B', $date_size);
        $pdf->SetTextColor($date_r, $date_g, $date_b);
        $pdf->SetXY(20, 124);
        $pdf->Cell($w - 40, 5, $data['period_start'] . ' - ' . $data['period_end'], 0, 1, 'C');

        // Certificate Code with Background
        $pdf->SetFont('helvetica', '', $footer_size);
        $pdf->SetFillColor($code_bg_r, $code_bg_g, $code_bg_b);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->RoundedRect(($w - 70) / 2, 134, 70, 7, 2, '1111', 'F');
        $pdf->SetXY(($w - 70) / 2, 135);
        $pdf->Cell(70, 5, $code_label . ' #' . $data['certificate_code'], 0, 1, 'C', false);

        // Instructor Signature
        if (!empty($signature_image)) {
            $upload_dir = wp_upload_dir();
            $signature_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $signature_image);

            if (file_exists($signature_path) && !preg_match('/\.svg$/i', $signature_path)) {
                list($img_width, $img_height) = @getimagesize($signature_path);
                if ($img_width && $img_height) {
                    $sig_height = 12;
                    $sig_width = ($img_width / $img_height) * $sig_height;
                    $sig_x = ($w - $sig_width) / 2;
                    $pdf->Image($signature_path, $sig_x, 145, $sig_width, $sig_height, '', '', '', true, 300);
                }
            } else {
                $pdf->SetFont('zapfdingbats', '', 20);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->SetXY(20, 146);
                $pdf->Cell($w - 40, 7, chr(252), 0, 1, 'C');
            }
        } else {
            $pdf->SetFont('zapfdingbats', '', 20);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY(20, 146);
            $pdf->Cell($w - 40, 7, chr(252), 0, 1, 'C');
        }

        // Instructor Name
        $pdf->SetFont('helvetica', 'B', $footer_size);
        $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
        $pdf->SetXY(20, 159);
        $pdf->Cell($w - 40, 4, $instructor_label . ' Dr Carlos Castro DDS, FACP', 0, 1, 'C');

        // Program Name
        $pdf->SetFont('helvetica', 'B', $footer_size);
        $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
        $pdf->SetXY(20, 164);
        $pdf->Cell($w - 40, 4, 'GPS Monthly Seminars Program', 0, 1, 'C');

        // QR Code for Certificate Validation
        if (($enable_qr_code == 'true' || $enable_qr_code === true || $enable_qr_code == 1) && !empty($data['certificate_code'])) {
            $validation_url = home_url('/certificate-validation?code=' . $data['certificate_code']);
            $qr_size = 18;
            $qr_y = 173;

            if ($qr_code_position === 'bottom-left') {
                $qr_x = 18;
            } else {
                $qr_x = $w - $qr_size - 18;
            }

            $pdf->write2DBarcode($validation_url, 'QRCODE,L', $qr_x, $qr_y, $qr_size, $qr_size, [], 'N');
        }

        // PACE Section at Bottom
        if ($show_pace == 'true' || $show_pace === true || $show_pace == 1) {
            $pace_y = 173;
            $pace_height = 25;

            $pdf->SetFillColor(240, 245, 250);

            // Adjust PACE section width if QR code is on the left
            if (($enable_qr_code == 'true' || $enable_qr_code === true || $enable_qr_code == 1) && $qr_code_position === 'bottom-left') {
                $pace_start_x = 45;
                $pace_width = $w - 60;
                $pdf->RoundedRect($pace_start_x, $pace_y, $pace_width, $pace_height, 3.5, '1111', 'F');
                $pace_x = $pace_start_x + 5;
            } else {
                $pace_start_x = 15;
                $pace_width = (($enable_qr_code == 'true' || $enable_qr_code === true || $enable_qr_code == 1)) ? $w - 55 : $w - 30;
                $pdf->RoundedRect($pace_start_x, $pace_y, $pace_width, $pace_height, 3.5, '1111', 'F');
                $pace_x = $pace_start_x + 10;
            }

            // PACE Logo (if available)
            if (!empty($pace_logo)) {
                $upload_dir = wp_upload_dir();
                $pace_logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pace_logo);

                if (file_exists($pace_logo_path) && !preg_match('/\.svg$/i', $pace_logo_path)) {
                    list($img_width, $img_height) = @getimagesize($pace_logo_path);
                    if ($img_width && $img_height) {
                        $pace_logo_height = 12;
                        $pace_logo_width = ($img_width / $img_height) * $pace_logo_height;
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
