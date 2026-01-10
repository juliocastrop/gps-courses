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
        $venue = get_post_meta($certificate->event_id, '_gps_venue', true);
        $instructor = get_post_meta($certificate->event_id, '_gps_instructor', true);

        return [
            'valid' => true,
            'certificate' => [
                'attendee_name' => $certificate->attendee_name,
                'attendee_email' => $certificate->attendee_email,
                'event_title' => $certificate->event_title,
                'event_date' => $event_date ? date_i18n('F j, Y', strtotime($event_date)) : '',
                'venue' => $venue,
                'instructor' => $instructor ?: 'Dr Carlos Castro DDS, FACP',
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
        // Get site title
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php _e('Certificate Validation', 'gps-courses'); ?> - <?php echo esc_html($site_name); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .container {
                    max-width: 600px;
                    width: 100%;
                }

                .validation-card {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    overflow: hidden;
                    animation: slideUp 0.4s ease-out;
                }

                @keyframes slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .validation-header {
                    padding: 30px;
                    text-align: center;
                    border-bottom: 1px solid #e0e0e0;
                }

                .validation-header h1 {
                    font-size: 24px;
                    color: #333;
                    margin-bottom: 8px;
                }

                .validation-header .site-link {
                    color: #667eea;
                    text-decoration: none;
                    font-size: 14px;
                }

                .validation-header .site-link:hover {
                    text-decoration: underline;
                }

                .validation-status {
                    padding: 40px 30px;
                    text-align: center;
                }

                .status-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 20px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 40px;
                }

                .status-icon.valid {
                    background: #d4edda;
                    color: #155724;
                }

                .status-icon.invalid {
                    background: #f8d7da;
                    color: #721c24;
                }

                .status-title {
                    font-size: 28px;
                    font-weight: 600;
                    margin-bottom: 12px;
                }

                .status-title.valid {
                    color: #155724;
                }

                .status-title.invalid {
                    color: #721c24;
                }

                .status-message {
                    color: #666;
                    font-size: 16px;
                    line-height: 1.6;
                }

                .certificate-details {
                    padding: 0 30px 30px;
                }

                .detail-group {
                    margin-bottom: 20px;
                    padding-bottom: 20px;
                    border-bottom: 1px solid #f0f0f0;
                }

                .detail-group:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                }

                .detail-label {
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    color: #999;
                    margin-bottom: 6px;
                    font-weight: 600;
                }

                .detail-value {
                    font-size: 16px;
                    color: #333;
                    font-weight: 500;
                }

                .certificate-code {
                    background: #f8f9fa;
                    padding: 12px;
                    border-radius: 8px;
                    font-family: 'Courier New', monospace;
                    font-size: 18px;
                    letter-spacing: 1px;
                    color: #667eea;
                    font-weight: bold;
                }

                .action-buttons {
                    padding: 0 30px 30px;
                    display: flex;
                    gap: 12px;
                }

                .btn {
                    flex: 1;
                    padding: 14px 24px;
                    border: none;
                    border-radius: 8px;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    text-decoration: none;
                    text-align: center;
                    transition: all 0.2s;
                    display: inline-block;
                }

                .btn-primary {
                    background: #667eea;
                    color: white;
                }

                .btn-primary:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }

                .btn-secondary {
                    background: #f8f9fa;
                    color: #333;
                    border: 1px solid #e0e0e0;
                }

                .btn-secondary:hover {
                    background: #e9ecef;
                    transform: translateY(-2px);
                }

                .footer-note {
                    padding: 20px 30px;
                    background: #f8f9fa;
                    text-align: center;
                    font-size: 13px;
                    color: #666;
                }

                @media (max-width: 600px) {
                    .validation-header h1 {
                        font-size: 20px;
                    }

                    .status-icon {
                        width: 60px;
                        height: 60px;
                        font-size: 30px;
                    }

                    .status-title {
                        font-size: 22px;
                    }

                    .action-buttons {
                        flex-direction: column;
                    }

                    .certificate-code {
                        font-size: 14px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="validation-card">
                    <div class="validation-header">
                        <h1><?php _e('Certificate Validation', 'gps-courses'); ?></h1>
                        <a href="<?php echo esc_url($site_url); ?>" class="site-link"><?php echo esc_html($site_name); ?></a>
                    </div>

                    <?php if ($validation_result['valid']): ?>
                        <?php $cert = $validation_result['certificate']; ?>

                        <div class="validation-status">
                            <div class="status-icon valid">✓</div>
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
                                <div class="detail-value"><?php echo esc_html($cert['attendee_name']); ?></div>
                            </div>

                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Course/Event', 'gps-courses'); ?></div>
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

                            <div class="detail-group">
                                <div class="detail-label"><?php _e('Issue Date', 'gps-courses'); ?></div>
                                <div class="detail-value"><?php echo esc_html($cert['generated_at']); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($cert['certificate_url'])): ?>
                        <div class="action-buttons">
                            <a href="<?php echo esc_url($cert['certificate_url']); ?>" class="btn btn-primary" target="_blank">
                                <?php _e('View Certificate', 'gps-courses'); ?>
                            </a>
                            <a href="<?php echo esc_url($site_url); ?>" class="btn btn-secondary">
                                <?php _e('Back to Home', 'gps-courses'); ?>
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="footer-note">
                            <?php _e('This certificate was issued by', 'gps-courses'); ?> <?php echo esc_html($site_name); ?><br>
                            <?php _e('Verification timestamp:', 'gps-courses'); ?> <?php echo esc_html(date_i18n('F j, Y g:i A')); ?>
                        </div>

                    <?php else: ?>

                        <div class="validation-status">
                            <div class="status-icon invalid">✕</div>
                            <h2 class="status-title invalid"><?php _e('Invalid Certificate', 'gps-courses'); ?></h2>
                            <p class="status-message">
                                <?php echo esc_html($validation_result['message']); ?>
                            </p>
                            <?php if (!empty($code)): ?>
                                <div style="margin-top: 20px;">
                                    <div class="detail-label"><?php _e('Code Provided', 'gps-courses'); ?></div>
                                    <div class="certificate-code"><?php echo esc_html($code); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="action-buttons">
                            <a href="<?php echo esc_url($site_url); ?>" class="btn btn-primary">
                                <?php _e('Back to Home', 'gps-courses'); ?>
                            </a>
                        </div>

                        <div class="footer-note">
                            <?php _e('If you believe this is an error, please contact us for assistance.', 'gps-courses'); ?>
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
                    background: #f8f9fa;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .gps-validator-title {
                    font-size: 24px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    text-align: center;
                    color: #333;
                }
                .gps-validator-form {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 15px;
                }
                .gps-validator-input {
                    flex: 1;
                    padding: 12px 16px;
                    border: 2px solid #e0e0e0;
                    border-radius: 8px;
                    font-size: 16px;
                    transition: border-color 0.2s;
                }
                .gps-validator-input:focus {
                    outline: none;
                    border-color: #667eea;
                }
                .gps-validator-button {
                    padding: 12px 30px;
                    background: #667eea;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .gps-validator-button:hover {
                    background: #5568d3;
                }
                .gps-validator-button:disabled {
                    background: #ccc;
                    cursor: not-allowed;
                }
                .gps-validator-result {
                    padding: 15px;
                    border-radius: 8px;
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
                    color: #666;
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
