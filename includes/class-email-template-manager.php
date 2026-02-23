<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Email Template Manager
 *
 * Manages email templates with visual editor and preview functionality
 */
class Email_Template_Manager {

    /**
     * Available email templates
     *
     * Note: Only simple templates with {{variable}} syntax are editable here.
     * Complex templates (ticket, credits) use Email Settings for customization.
     */
    const TEMPLATES = [
        'password_reset' => [
            'name' => 'Password Reset',
            'description' => 'Email sent when user requests password reset',
            'editable' => true,
            'variables' => [
                'user_name' => 'User display name',
                'reset_url' => 'Password reset URL'
            ]
        ],
        'seminar_certificate' => [
            'name' => 'Seminar Certificate',
            'description' => 'Email sent with seminar completion certificate (Coming Soon)',
            'editable' => true,
            'variables' => [
                'user_name' => 'User display name',
                'seminar_title' => 'Seminar title',
                'sessions_completed' => 'Number of sessions completed',
                'certificate_url' => 'Certificate download URL'
            ]
        ]
    ];

    /**
     * Templates managed by Email Settings (not editable here)
     */
    const EMAIL_SETTINGS_TEMPLATES = [
        'ticket' => [
            'name' => 'Ticket Confirmation',
            'description' => 'Customizable via GPS Courses → Email Settings',
            'settings_page' => 'gps-email-settings'
        ],
        'credits' => [
            'name' => 'CE Credits Awarded',
            'description' => 'Customizable via GPS Courses → Email Settings',
            'settings_page' => 'gps-email-settings'
        ]
    ];

    /**
     * Initialize the template manager
     */
    public static function init() {
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // AJAX handlers
        add_action('wp_ajax_gps_preview_email_template', [__CLASS__, 'ajax_preview_template']);
        add_action('wp_ajax_gps_save_email_template', [__CLASS__, 'ajax_save_template']);
        add_action('wp_ajax_gps_send_test_email', [__CLASS__, 'ajax_send_test_email']);
        add_action('wp_ajax_gps_reset_email_template', [__CLASS__, 'ajax_reset_template']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-courses-settings',
            __('Email Templates', 'gps-courses'),
            __('Email Templates', 'gps-courses'),
            'manage_options',
            'gps-email-templates',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-email-templates') {
            return;
        }

        // CodeMirror for HTML editing
        wp_enqueue_code_editor(['type' => 'text/html']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');

        // Custom admin script
        wp_enqueue_script(
            'gps-email-template-manager',
            GPSC_URL . 'assets/js/email-template-manager.js',
            ['jquery', 'wp-code-editor'],
            GPSC_VERSION,
            true
        );

        wp_localize_script('gps-email-template-manager', 'gpsEmailTemplates', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_email_templates'),
            'templates' => self::TEMPLATES
        ]);

        // Custom admin styles
        wp_enqueue_style(
            'gps-email-template-manager',
            GPSC_URL . 'assets/css/email-template-manager.css',
            [],
            GPSC_VERSION
        );
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        $current_template = isset($_GET['template']) ? sanitize_key($_GET['template']) : 'password_reset';

        if (!isset(self::TEMPLATES[$current_template])) {
            $current_template = 'password_reset';
        }

        $template_data = self::TEMPLATES[$current_template];
        $template_content = self::get_template_content($current_template);

        ?>
        <div class="wrap gps-email-templates-wrap">
            <h1><?php _e('Email Templates', 'gps-courses'); ?></h1>

            <div class="gps-template-manager">
                <!-- Template Selector -->
                <div class="gps-template-selector">
                    <h2><?php _e('Select Template', 'gps-courses'); ?></h2>

                    <h3 style="margin: 15px 0 10px 0; font-size: 13px; color: #646970; font-weight: 600; text-transform: uppercase;">
                        <?php _e('Editable Templates', 'gps-courses'); ?>
                    </h3>
                    <div class="gps-template-tabs">
                        <?php foreach (self::TEMPLATES as $key => $template): ?>
                            <a href="<?php echo admin_url('admin.php?page=gps-email-templates&template=' . $key); ?>"
                               class="gps-template-tab <?php echo $key === $current_template ? 'active' : ''; ?>">
                                <span class="dashicons dashicons-email"></span>
                                <span><?php echo esc_html($template['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <h3 style="margin: 25px 0 10px 0; font-size: 13px; color: #646970; font-weight: 600; text-transform: uppercase;">
                        <?php _e('Email Settings Templates', 'gps-courses'); ?>
                    </h3>
                    <div class="gps-template-tabs">
                        <?php foreach (self::EMAIL_SETTINGS_TEMPLATES as $key => $template): ?>
                            <a href="<?php echo admin_url('admin.php?page=' . $template['settings_page']); ?>"
                               class="gps-template-tab gps-template-external">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <span><?php echo esc_html($template['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <p class="description" style="margin-top: 10px;">
                        <?php _e('Ticket and CE Credits templates are customizable via Email Settings (colors, logo, texts).', 'gps-courses'); ?>
                    </p>
                </div>

                <div class="gps-template-content">
                    <div class="gps-template-header">
                        <div class="gps-template-info">
                            <h2><?php echo esc_html($template_data['name']); ?></h2>
                            <p class="description"><?php echo esc_html($template_data['description']); ?></p>
                        </div>
                        <div class="gps-template-actions">
                            <button type="button" class="button button-secondary" id="gps-reset-template">
                                <span class="dashicons dashicons-image-rotate"></span>
                                <?php _e('Reset to Default', 'gps-courses'); ?>
                            </button>
                            <button type="button" class="button button-primary" id="gps-save-template">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Save Template', 'gps-courses'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="gps-template-editor-wrapper">
                        <!-- Editor Pane -->
                        <div class="gps-editor-pane">
                            <div class="gps-editor-toolbar">
                                <h3><?php _e('Template Editor', 'gps-courses'); ?></h3>
                                <div class="gps-editor-mode">
                                    <label>
                                        <input type="radio" name="editor_mode" value="visual" checked>
                                        <?php _e('Visual', 'gps-courses'); ?>
                                    </label>
                                    <label>
                                        <input type="radio" name="editor_mode" value="code">
                                        <?php _e('HTML', 'gps-courses'); ?>
                                    </label>
                                </div>
                            </div>

                            <textarea id="gps-template-editor"
                                      name="template_content"
                                      data-template="<?php echo esc_attr($current_template); ?>"
                                      class="gps-template-textarea"><?php echo esc_textarea($template_content); ?></textarea>

                            <!-- Variables Reference -->
                            <div class="gps-variables-reference">
                                <h4>
                                    <span class="dashicons dashicons-editor-code"></span>
                                    <?php _e('Available Variables', 'gps-courses'); ?>
                                </h4>
                                <div class="gps-variables-list">
                                    <?php foreach ($template_data['variables'] as $var => $desc): ?>
                                        <div class="gps-variable-item">
                                            <code class="gps-variable-code" title="<?php _e('Click to copy', 'gps-courses'); ?>">
                                                {{<?php echo esc_html($var); ?>}}
                                            </code>
                                            <span class="gps-variable-desc"><?php echo esc_html($desc); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">
                                    <?php _e('Click on a variable to copy it to clipboard', 'gps-courses'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Preview Pane -->
                        <div class="gps-preview-pane">
                            <div class="gps-preview-toolbar">
                                <h3><?php _e('Live Preview', 'gps-courses'); ?></h3>
                                <div class="gps-preview-actions">
                                    <button type="button" class="button" id="gps-refresh-preview">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php _e('Refresh', 'gps-courses'); ?>
                                    </button>
                                    <button type="button" class="button" id="gps-send-test-email">
                                        <span class="dashicons dashicons-email-alt"></span>
                                        <?php _e('Send Test', 'gps-courses'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Device Preview Tabs -->
                            <div class="gps-device-preview-tabs">
                                <button type="button" class="gps-device-tab active" data-device="desktop">
                                    <span class="dashicons dashicons-desktop"></span>
                                    <?php _e('Desktop', 'gps-courses'); ?>
                                </button>
                                <button type="button" class="gps-device-tab" data-device="mobile">
                                    <span class="dashicons dashicons-smartphone"></span>
                                    <?php _e('Mobile', 'gps-courses'); ?>
                                </button>
                            </div>

                            <div class="gps-preview-frame-wrapper desktop">
                                <iframe id="gps-preview-frame" class="gps-preview-iframe"></iframe>
                            </div>

                            <div class="gps-preview-loading">
                                <span class="spinner is-active"></span>
                                <p><?php _e('Loading preview...', 'gps-courses'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Test Email Modal -->
                    <div id="gps-test-email-modal" class="gps-modal" style="display: none;">
                        <div class="gps-modal-content">
                            <div class="gps-modal-header">
                                <h3><?php _e('Send Test Email', 'gps-courses'); ?></h3>
                                <button type="button" class="gps-modal-close">&times;</button>
                            </div>
                            <div class="gps-modal-body">
                                <p><?php _e('Send a test email to verify how the template looks in your inbox:', 'gps-courses'); ?></p>
                                <p>
                                    <label for="gps-test-email-address">
                                        <?php _e('Email Address:', 'gps-courses'); ?>
                                    </label>
                                    <input type="email"
                                           id="gps-test-email-address"
                                           class="widefat"
                                           value="<?php echo esc_attr(get_option('admin_email')); ?>"
                                           placeholder="email@example.com">
                                </p>
                            </div>
                            <div class="gps-modal-footer">
                                <button type="button" class="button button-secondary gps-modal-close">
                                    <?php _e('Cancel', 'gps-courses'); ?>
                                </button>
                                <button type="button" class="button button-primary" id="gps-confirm-send-test">
                                    <?php _e('Send Test Email', 'gps-courses'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get template content from database or default
     */
    public static function get_template_content($template_key) {
        // Try to get from database first
        $saved_template = get_option('gps_email_template_' . $template_key);

        if ($saved_template) {
            return $saved_template;
        }

        // Return default template
        return self::get_default_template($template_key);
    }

    /**
     * Get default template content
     */
    public static function get_default_template($template_key) {
        // Only handle editable templates
        if (!isset(self::TEMPLATES[$template_key])) {
            return '<!-- Template not editable via Template Manager -->';
        }

        // Map template keys to actual template files
        $template_files = [
            'password_reset' => 'password-reset-default.php',
            'seminar_certificate' => 'seminar-certificate-default.php'
        ];

        $template_file = isset($template_files[$template_key]) ? $template_files[$template_key] : null;

        if (!$template_file) {
            return '<!-- Template file not configured -->';
        }

        $template_path = GPSC_PATH . 'templates/emails/' . $template_file;

        if (!file_exists($template_path)) {
            return '<!-- Template file not found: ' . $template_file . ' -->';
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * AJAX: Preview template
     */
    public static function ajax_preview_template() {
        check_ajax_referer('gps_email_templates', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $template_key = sanitize_key($_POST['template']);
        $content = wp_kses_post($_POST['content']);

        // Replace variables with sample data
        $preview_content = self::replace_variables_with_samples($template_key, $content);

        wp_send_json_success(['html' => $preview_content]);
    }

    /**
     * AJAX: Save template
     */
    public static function ajax_save_template() {
        check_ajax_referer('gps_email_templates', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $template_key = sanitize_key($_POST['template']);
        $content = wp_kses_post($_POST['content']);

        update_option('gps_email_template_' . $template_key, $content);

        wp_send_json_success(['message' => __('Template saved successfully', 'gps-courses')]);
    }

    /**
     * AJAX: Send test email
     */
    public static function ajax_send_test_email() {
        check_ajax_referer('gps_email_templates', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $template_key = sanitize_key($_POST['template']);
        $content = wp_kses_post($_POST['content']);
        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Invalid email address', 'gps-courses')]);
        }

        // Replace variables with sample data
        $email_content = self::replace_variables_with_samples($template_key, $content);

        $subject = sprintf(__('[TEST] %s - GPS Dental Training', 'gps-courses'), self::TEMPLATES[$template_key]['name']);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <noreply@gpsdentaltraining.com>'
        ];

        $sent = wp_mail($email, $subject, $email_content, $headers);

        if ($sent) {
            wp_send_json_success(['message' => __('Test email sent successfully', 'gps-courses')]);
        } else {
            wp_send_json_error(['message' => __('Failed to send test email', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Reset template to default
     */
    public static function ajax_reset_template() {
        check_ajax_referer('gps_email_templates', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $template_key = sanitize_key($_POST['template']);

        delete_option('gps_email_template_' . $template_key);

        $default_content = self::get_default_template($template_key);

        wp_send_json_success([
            'message' => __('Template reset to default', 'gps-courses'),
            'content' => $default_content
        ]);
    }

    /**
     * Replace template variables with sample data for preview
     */
    private static function replace_variables_with_samples($template_key, $content) {
        $samples = [
            'user_name' => 'Dr. John Smith',
            'event_title' => 'Comprehensive PRF Protocols & Handling Clinical Integration',
            'event_date' => 'June 15, 2026',
            'event_venue' => 'GPS Dental Training Center, Miami, FL',
            'ticket_code' => 'GPS-2026-ABC123',
            'qr_code_url' => GPSC_URL . 'assets/images/sample-qr.png',
            'order_number' => '#12345',
            'ce_credits' => '8',
            'reset_url' => home_url('/wp-login.php?action=rp&key=sample123&login=drsmith'),
            'credits' => '8',
            'total_credits' => '24',
            'seminar_title' => 'Monthly Seminar Series 2026',
            'sessions_completed' => '10',
            'certificate_url' => home_url('/certificate/sample-cert-123')
        ];

        foreach ($samples as $var => $value) {
            $content = str_replace('{{' . $var . '}}', $value, $content);
        }

        return $content;
    }
}
