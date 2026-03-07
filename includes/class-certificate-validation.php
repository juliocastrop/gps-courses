<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

class Certificate_Validation {

    public static function init() {
        // Add rewrite rule for certificate validation
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_action('template_redirect', [__CLASS__, 'handle_validation_page']);

        // AJAX handler for validation (optional, for dynamic checks)
        add_action('wp_ajax_nopriv_gps_validate_certificate', [__CLASS__, 'ajax_validate_certificate']);
        add_action('wp_ajax_gps_validate_certificate', [__CLASS__, 'ajax_validate_certificate']);

        // Add shortcode for validation form
        add_shortcode('gps_certificate_validator', [__CLASS__, 'validation_form_shortcode']);

        // Admin action to manually flush rewrite rules
        add_action('admin_init', [__CLASS__, 'maybe_flush_rewrite_rules']);
    }

    /**
     * Flush rewrite rules if needed
     */
    public static function maybe_flush_rewrite_rules() {
        // Check if we need to flush (only once after plugin update)
        if (get_option('gps_validation_rewrite_flushed') !== GPSC_VERSION) {
            self::add_rewrite_rules();
            flush_rewrite_rules();
            update_option('gps_validation_rewrite_flushed', GPSC_VERSION);
            error_log('GPS Courses: Rewrite rules flushed for certificate validation');
        }
    }

    /**
     * Add rewrite rules for certificate validation
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^certificate-validation/?$',
            'index.php?gps_certificate_validation=1',
            'top'
        );
        add_rewrite_tag('%gps_certificate_validation%', '([^&]+)');
    }

    /**
     * Handle certificate validation page
     */
    public static function handle_validation_page() {
        if (!get_query_var('gps_certificate_validation')) {
            return;
        }

        // Get certificate code from URL parameter
        $certificate_code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';

        // Validate the certificate
        $validation_result = self::validate_certificate_code($certificate_code);

        // Render the validation page
        self::render_validation_page($validation_result, $certificate_code);
        exit;
    }

    /**
     * Validate certificate code
     */
    private static function validate_certificate_code($code) {
        global $wpdb;

        if (empty($code)) {
            return [
                'valid' => false,
                'message' => __('No certificate code provided', 'gps-courses'),
            ];
        }

        // Query to get certificate details
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT
                c.*,
                t.attendee_name,
                t.attendee_email,
                t.ticket_code,
                e.post_title as event_title
            FROM {$wpdb->prefix}gps_certificates c
            INNER JOIN {$wpdb->prefix}gps_tickets t ON c.ticket_id = t.id
            INNER JOIN {$wpdb->posts} e ON c.event_id = e.ID
            WHERE t.ticket_code = %s
            AND c.certificate_path IS NOT NULL
            LIMIT 1",
            $code
        ));

        if (!$certificate) {
            return [
                'valid' => false,
                'message' => __('Certificate not found or invalid', 'gps-courses'),
            ];
        }

        // Get event details
        $event_date = get_post_meta($certificate->event_id, '_gps_start_date', true);
        $event_end_date = get_post_meta($certificate->event_id, '_gps_end_date', true);
        $venue = get_post_meta($certificate->event_id, '_gps_venue', true);
        $instructor = get_post_meta($certificate->event_id, '_gps_instructor', true);
        $ce_credits = get_post_meta($certificate->event_id, '_gps_ce_credits', true);

        return [
            'valid' => true,
            'certificate' => [
                'attendee_name' => $certificate->attendee_name,
                'attendee_email' => $certificate->attendee_email,
                'event_title' => $certificate->event_title,
                'event_date' => Certificates::format_event_date_range($event_date, $event_end_date),
                'venue' => $venue,
                'instructor' => $instructor ?: 'Dr Carlos Castro DDS, FACP',
                'ce_credits' => $ce_credits ? floatval($ce_credits) : 0,
                'generated_at' => $certificate->generated_at ? date_i18n('F j, Y g:i A', strtotime($certificate->generated_at)) : '',
                'certificate_code' => $certificate->ticket_code,
                'certificate_url' => $certificate->certificate_url,
            ],
        ];
    }

    /**
     * Render validation page
     */
    private static function render_validation_page($validation_result, $code) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        // Get logo from certificate settings
        $logo = Certificate_Settings::get('logo');

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php _e('Certificate Validation', 'gps-courses'); ?> - <?php echo esc_html($site_name); ?></title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }

                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: #0C2044;
                    min-height: 100vh;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-start;
                    padding: 40px 20px 60px 20px;
                    position: relative;
                }

                /* Subtle background pattern */
                body::before {
                    content: '';
                    position: absolute;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background:
                        radial-gradient(circle at 20% 20%, rgba(221, 200, 157, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(11, 82, 172, 0.12) 0%, transparent 50%);
                    pointer-events: none;
                }

                .container {
                    max-width: 560px;
                    width: 100%;
                    position: relative;
                    z-index: 1;
                }

                .validation-card {
                    background: #fff;
                    border-radius: 20px;
                    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
                    overflow: hidden;
                    animation: slideUp 0.5s cubic-bezier(0.22, 1, 0.36, 1);
                }

                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(40px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .validation-header {
                    background: linear-gradient(135deg, #0C2044 0%, #173D84 100%);
                    padding: 32px 30px;
                    text-align: center;
                    position: relative;
                }

                .validation-header::after {
                    content: '';
                    position: absolute;
                    bottom: 0; left: 0; right: 0;
                    height: 3px;
                    background: linear-gradient(90deg, #DDC89D, #BC9D67, #DDC89D);
                }

                .header-logo {
                    max-height: 50px;
                    max-width: 220px;
                    margin-bottom: 14px;
                }

                .validation-header h1 {
                    font-size: 13px;
                    color: #DDC89D;
                    text-transform: uppercase;
                    letter-spacing: 3px;
                    font-weight: 500;
                    margin: 0;
                }

                .validation-status {
                    padding: 36px 30px 28px;
                    text-align: center;
                }

                .status-icon {
                    width: 72px;
                    height: 72px;
                    margin: 0 auto 18px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 36px;
                }

                .status-icon.valid {
                    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                    color: #155724;
                    box-shadow: 0 4px 16px rgba(21, 87, 36, 0.15);
                }

                .status-icon.invalid {
                    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                    color: #721c24;
                    box-shadow: 0 4px 16px rgba(114, 28, 36, 0.15);
                }

                .status-title {
                    font-size: 24px;
                    font-weight: 700;
                    margin-bottom: 8px;
                }

                .status-title.valid { color: #155724; }
                .status-title.invalid { color: #721c24; }

                .status-message {
                    color: #666;
                    font-size: 15px;
                    line-height: 1.6;
                    font-weight: 400;
                }

                .certificate-details {
                    padding: 0 30px 24px;
                }

                .detail-group {
                    padding: 16px 0;
                    border-bottom: 1px solid #f0f0f0;
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .detail-group:last-child {
                    border-bottom: none;
                }

                .detail-label {
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    color: #999;
                    font-weight: 600;
                }

                .detail-value {
                    font-size: 16px;
                    color: #0C2044;
                    font-weight: 500;
                }

                .detail-value.highlight {
                    color: #0B52AC;
                    font-weight: 600;
                }

                .certificate-code {
                    background: linear-gradient(135deg, #f8f6f1 0%, #f0ece3 100%);
                    padding: 14px 16px;
                    border-radius: 10px;
                    font-family: 'Courier New', monospace;
                    font-size: 18px;
                    letter-spacing: 2px;
                    color: #0B52AC;
                    font-weight: bold;
                    border: 1px solid #DDC89D;
                }

                .ce-credits-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: linear-gradient(135deg, #0C2044 0%, #173D84 100%);
                    color: #DDC89D;
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-size: 15px;
                    font-weight: 600;
                }

                .action-buttons {
                    padding: 0 30px 28px;
                    display: flex;
                    gap: 12px;
                }

                .btn {
                    flex: 1;
                    padding: 14px 24px;
                    border: none;
                    border-radius: 10px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    text-decoration: none;
                    text-align: center;
                    transition: all 0.25s ease;
                    display: inline-block;
                    font-family: 'Inter', sans-serif;
                }

                .btn-primary {
                    background: linear-gradient(135deg, #0B52AC 0%, #173D84 100%);
                    color: #fff;
                }

                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(11, 82, 172, 0.35);
                }

                .btn-secondary {
                    background: #f8f6f1;
                    color: #0C2044;
                    border: 1px solid #DDC89D;
                }

                .btn-secondary:hover {
                    background: #f0ece3;
                    transform: translateY(-2px);
                }

                .btn-download {
                    background: linear-gradient(135deg, #BC9D67 0%, #DDC89D 100%);
                    color: #0C2044;
                }

                .btn-download:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(188, 157, 103, 0.4);
                }

                .footer-note {
                    padding: 18px 30px;
                    background: #f8f6f1;
                    text-align: center;
                    font-size: 12px;
                    color: #888;
                    border-top: 1px solid #f0ece3;
                    line-height: 1.6;
                }

                .footer-note strong {
                    color: #0C2044;
                    font-weight: 600;
                }

                @media (max-width: 600px) {
                    body { padding: 12px; }

                    .validation-header { padding: 24px 20px; }
                    .header-logo { max-height: 40px; }

                    .validation-status { padding: 28px 20px 20px; }
                    .status-icon { width: 60px; height: 60px; font-size: 28px; }
                    .status-title { font-size: 20px; }

                    .certificate-details { padding: 0 20px 20px; }
                    .action-buttons { flex-direction: column; padding: 0 20px 24px; }
                    .certificate-code { font-size: 14px; letter-spacing: 1px; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="validation-card">
                    <div class="validation-header">
                        <?php if (!empty($logo)): ?>
                            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site_name); ?>" class="header-logo"><br>
                        <?php endif; ?>
                        <h1><?php _e('Certificate Validation', 'gps-courses'); ?></h1>
                    </div>

                    <?php if ($validation_result['valid']): ?>
                        <?php $cert = $validation_result['certificate']; ?>

                        <div class="validation-status">
                            <div class="status-icon valid">&#10003;</div>
                            <h2 class="status-title valid"><?php _e('Valid Certificate', 'gps-courses'); ?></h2>
                            <p class="status-message">
                                <?php _e('This certificate has been verified and is authentic.', 'gps-courses'); ?>
                            </p>
                        </div>

                        <div class="certificate-details">
                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Certificate Code', 'gps-courses'); ?></div>
                                <div class="certificate-code"><?php echo esc_html($cert['certificate_code']); ?></div>
                            </div>

                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Recipient', 'gps-courses'); ?></div>
                                <div class="detail-value highlight"><?php echo esc_html($cert['attendee_name']); ?></div>
                            </div>

                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Course / Event', 'gps-courses'); ?></div>
                                <div class="detail-value"><?php echo esc_html($cert['event_title']); ?></div>
                            </div>

                            <?php if (!empty($cert['event_date'])): ?>
                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Completion Date', 'gps-courses'); ?></div>
                                <div class="detail-value"><?php echo esc_html($cert['event_date']); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($cert['venue'])): ?>
                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Location', 'gps-courses'); ?></div>
                                <div class="detail-value"><?php echo esc_html($cert['venue']); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($cert['instructor'])): ?>
                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Instructor', 'gps-courses'); ?></div>
                                <div class="detail-value"><?php echo esc_html($cert['instructor']); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($cert['ce_credits']) && $cert['ce_credits'] > 0): ?>
                            <div class="detail-group">
                                <div class="detail-label"><?php _e('CE Credits Awarded', 'gps-courses'); ?></div>
                                <div class="ce-credits-badge">
                                    <?php
                                    $credits_val = $cert['ce_credits'];
                                    echo esc_html(number_format($credits_val, ($credits_val == intval($credits_val)) ? 0 : 1) . ' ' . ($credits_val == 1 ? 'Credit Hour' : 'Credit Hours'));
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Issue Date', 'gps-courses'); ?></div>
                                <div class="detail-value"><?php echo esc_html($cert['generated_at']); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($cert['certificate_url'])): ?>
                        <div class="action-buttons">
                            <a href="<?php echo esc_url($cert['certificate_url']); ?>" class="btn btn-download" target="_blank">
                                <?php _e('Download Certificate', 'gps-courses'); ?>
                            </a>
                            <a href="<?php echo esc_url($site_url); ?>" class="btn btn-secondary">
                                <?php _e('Visit Website', 'gps-courses'); ?>
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="footer-note">
                            <?php _e('Issued by', 'gps-courses'); ?> <strong><?php echo esc_html($site_name); ?></strong><br>
                            <?php _e('Verified on', 'gps-courses'); ?> <?php echo esc_html(date_i18n('F j, Y \a\t g:i A')); ?>
                        </div>

                    <?php else: ?>

                        <div class="validation-status">
                            <div class="status-icon invalid">&#10007;</div>
                            <h2 class="status-title invalid"><?php _e('Invalid Certificate', 'gps-courses'); ?></h2>
                            <p class="status-message">
                                <?php echo esc_html($validation_result['message']); ?>
                            </p>
                            <?php if (!empty($code)): ?>
                                <div style="margin-top: 20px;">
                                    <div class="detail-label" style="text-align: center; margin-bottom: 8px;"><?php _e('Code Provided', 'gps-courses'); ?></div>
                                    <div class="certificate-code" style="text-align: center;"><?php echo esc_html($code); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="action-buttons">
                            <a href="<?php echo esc_url($site_url); ?>" class="btn btn-primary">
                                <?php _e('Visit Website', 'gps-courses'); ?>
                            </a>
                        </div>

                        <div class="footer-note">
                            <?php _e('If you believe this is an error, please contact us at', 'gps-courses'); ?>
                            <strong>info@gpsdentaltraining.com</strong>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * AJAX handler for certificate validation (optional)
     */
    public static function ajax_validate_certificate() {
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $result = self::validate_certificate_code($code);
        wp_send_json($result);
    }

    /**
     * Shortcode for embedding validation form
     * Usage: [gps_certificate_validator]
     */
    public static function validation_form_shortcode($atts) {
        $atts = shortcode_atts([
            'title' => __('Validate Certificate', 'gps-courses'),
            'button_text' => __('Validate', 'gps-courses'),
        ], $atts);

        ob_start();
        ?>
        <div class="gps-certificate-validator">
            <style>
                .gps-certificate-validator {
                    max-width: 500px;
                    margin: 30px auto;
                    padding: 30px;
                    background: #f8f6f1;
                    border-radius: 14px;
                    box-shadow: 0 2px 12px rgba(12, 32, 68, 0.08);
                    border: 1px solid #e8e0d0;
                }
                .gps-validator-title {
                    font-size: 22px;
                    font-weight: 700;
                    margin-bottom: 20px;
                    text-align: center;
                    color: #0C2044;
                }
                .gps-validator-form {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 15px;
                }
                .gps-validator-input {
                    flex: 1;
                    padding: 12px 16px;
                    border: 2px solid #DDC89D;
                    border-radius: 10px;
                    font-size: 16px;
                    transition: border-color 0.2s;
                    font-family: 'Courier New', monospace;
                    letter-spacing: 1px;
                }
                .gps-validator-input:focus {
                    outline: none;
                    border-color: #0B52AC;
                    box-shadow: 0 0 0 3px rgba(11, 82, 172, 0.1);
                }
                .gps-validator-button {
                    padding: 12px 30px;
                    background: linear-gradient(135deg, #0B52AC 0%, #173D84 100%);
                    color: white;
                    border: none;
                    border-radius: 10px;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.25s ease;
                }
                .gps-validator-button:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(11, 82, 172, 0.3);
                }
                .gps-validator-button:disabled {
                    background: #ccc;
                    cursor: not-allowed;
                }
                .gps-validator-result {
                    padding: 15px;
                    border-radius: 10px;
                    margin-top: 20px;
                    display: none;
                }
                .gps-validator-result.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                .gps-validator-result.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                .gps-validator-help {
                    font-size: 13px;
                    color: #888;
                    text-align: center;
                    margin-top: 10px;
                }
            </style>

            <h3 class="gps-validator-title"><?php echo esc_html($atts['title']); ?></h3>

            <form class="gps-validator-form" id="gps-validator-form">
                <input
                    type="text"
                    class="gps-validator-input"
                    id="gps-certificate-code"
                    placeholder="<?php _e('Enter certificate code', 'gps-courses'); ?>"
                    required
                >
                <button type="submit" class="gps-validator-button">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
            </form>

            <div class="gps-validator-result" id="gps-validator-result"></div>

            <p class="gps-validator-help">
                <?php _e('Enter the certificate code found on your certificate to verify its authenticity.', 'gps-courses'); ?>
            </p>

            <script>
            (function() {
                const form = document.getElementById('gps-validator-form');
                const input = document.getElementById('gps-certificate-code');
                const result = document.getElementById('gps-validator-result');
                const button = form.querySelector('button');

                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const code = input.value.trim();
                    if (!code) return;

                    // Redirect to validation page
                    window.location.href = '<?php echo home_url('/certificate-validation'); ?>?code=' + encodeURIComponent(code);
                });
            })();
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
}
