<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Email Settings and Customization
 */
class Email_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page'], 99);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Add settings page to GPS Courses menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'gps-dashboard',
            __('Email Settings', 'gps-courses'),
            __('Email Settings', 'gps-courses'),
            'manage_options',
            'gps-email-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Enqueue color picker scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-dashboard_page_gps-email-settings') {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        wp_enqueue_script('gps-email-settings', GPSC_URL . 'assets/js/admin-email-settings.js', ['jquery', 'wp-color-picker'], GPSC_VERSION, true);
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // General Settings
        register_setting('gps_email_settings', 'gps_email_logo');
        register_setting('gps_email_settings', 'gps_email_from_name');
        register_setting('gps_email_settings', 'gps_email_from_email');

        // Typography Settings
        register_setting('gps_email_settings', 'gps_email_font_family');
        register_setting('gps_email_settings', 'gps_email_heading_font_size');
        register_setting('gps_email_settings', 'gps_email_body_font_size');
        register_setting('gps_email_settings', 'gps_email_body_text_color');
        register_setting('gps_email_settings', 'gps_email_body_bg_color');

        // Header Settings
        register_setting('gps_email_settings', 'gps_email_header_text');
        register_setting('gps_email_settings', 'gps_email_header_bg_color');
        register_setting('gps_email_settings', 'gps_email_header_text_color');
        register_setting('gps_email_settings', 'gps_email_header_subtitle');

        // Ticket Section Settings
        register_setting('gps_email_settings', 'gps_email_ticket_label');
        register_setting('gps_email_settings', 'gps_email_ticket_bg_color');
        register_setting('gps_email_settings', 'gps_email_ticket_code_color');
        register_setting('gps_email_settings', 'gps_email_ticket_code_size');

        // Event Details Settings
        register_setting('gps_email_settings', 'gps_email_event_heading');
        register_setting('gps_email_settings', 'gps_email_event_heading_color');
        register_setting('gps_email_settings', 'gps_email_event_details_bg_color');
        register_setting('gps_email_settings', 'gps_email_event_label_color');

        // QR Code Settings
        register_setting('gps_email_settings', 'gps_email_qr_heading');
        register_setting('gps_email_settings', 'gps_email_qr_bg_color');
        register_setting('gps_email_settings', 'gps_email_show_qr_code');
        register_setting('gps_email_settings', 'gps_email_qr_size');
        register_setting('gps_email_settings', 'gps_email_qr_instructions');

        // CE Credits Badge Settings
        register_setting('gps_email_settings', 'gps_email_ce_badge_bg_color');
        register_setting('gps_email_settings', 'gps_email_ce_badge_text_color');

        // Footer Settings
        register_setting('gps_email_settings', 'gps_email_footer_text');
        register_setting('gps_email_settings', 'gps_email_footer_bg_color');
        register_setting('gps_email_settings', 'gps_email_footer_text_color');
        register_setting('gps_email_settings', 'gps_email_footer_social_links');
        register_setting('gps_email_settings', 'gps_email_footer_address');

        // Button Settings
        register_setting('gps_email_settings', 'gps_email_button_text');
        register_setting('gps_email_settings', 'gps_email_button_bg_color');
        register_setting('gps_email_settings', 'gps_email_button_text_color');
        register_setting('gps_email_settings', 'gps_email_button_border_radius');
        register_setting('gps_email_settings', 'gps_email_secondary_button_text');

        // Layout Settings
        register_setting('gps_email_settings', 'gps_email_container_width');
        register_setting('gps_email_settings', 'gps_email_border_radius');
        register_setting('gps_email_settings', 'gps_email_inner_padding');

        // Content Settings
        register_setting('gps_email_settings', 'gps_email_welcome_message');
        register_setting('gps_email_settings', 'gps_email_additional_info');
        register_setting('gps_email_settings', 'gps_email_support_email');
        register_setting('gps_email_settings', 'gps_email_support_phone');
    }

    /**
     * Get default settings
     */
    public static function get_defaults() {
        return [
            'logo' => '',
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),

            // Typography
            'font_family' => 'Arial, sans-serif',
            'heading_font_size' => '28px',
            'body_font_size' => '14px',
            'body_text_color' => '#333333',
            'body_bg_color' => '#ffffff',

            // Header
            'header_text' => __('Your Ticket Confirmation', 'gps-courses'),
            'header_bg_color' => '#2271b1',
            'header_text_color' => '#ffffff',
            'header_subtitle' => '',

            // Ticket Code Section
            'ticket_label' => __('Ticket Code', 'gps-courses'),
            'ticket_bg_color' => '#f8f9fa',
            'ticket_code_color' => '#2271b1',
            'ticket_code_size' => '24px',

            // Event Details
            'event_heading' => __('Event Details', 'gps-courses'),
            'event_heading_color' => '#1e293b',
            'event_details_bg_color' => '#ffffff',
            'event_label_color' => '#6c757d',

            // QR Code
            'qr_heading' => __('Your Digital Ticket', 'gps-courses'),
            'qr_bg_color' => '#f8f9fa',
            'show_qr_code' => true,
            'qr_size' => '200',
            'qr_instructions' => __('Show this QR code at check-in', 'gps-courses'),

            // CE Credits Badge
            'ce_badge_bg_color' => '#28a745',
            'ce_badge_text_color' => '#ffffff',

            // Footer
            'footer_text' => __('Thank you for your purchase!', 'gps-courses'),
            'footer_bg_color' => '#f8f9fa',
            'footer_text_color' => '#6c757d',
            'footer_social_links' => '',
            'footer_address' => '',

            // Button
            'button_text' => __('View My Tickets', 'gps-courses'),
            'button_bg_color' => '#2271b1',
            'button_text_color' => '#ffffff',
            'button_border_radius' => '6px',
            'secondary_button_text' => __('Edit Information', 'gps-courses'),

            // Layout
            'container_width' => '600px',
            'border_radius' => '8px',
            'inner_padding' => '30px',

            // Content
            'welcome_message' => '',
            'additional_info' => '',
            'support_email' => get_option('admin_email'),
            'support_phone' => '',
        ];
    }

    /**
     * Get setting value with fallback to default
     */
    public static function get($key) {
        $defaults = self::get_defaults();
        $option_key = 'gps_email_' . $key;
        $value = get_option($option_key);

        // Return saved value if exists, otherwise return default
        if ($value !== false && $value !== '') {
            return $value;
        }

        return isset($defaults[$key]) ? $defaults[$key] : '';
    }

    /**
     * Handle test email sending
     */
    public static function handle_test_email() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'gps-courses'));
        }

        check_ajax_referer('gps_test_email', 'nonce');

        $to = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';

        if (empty($to) || !is_email($to)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'gps-courses')]);
        }

        // Get current settings
        $settings = [
            'logo' => self::get('logo'),
            'from_name' => self::get('from_name'),
            'from_email' => self::get('from_email'),
            'header_text' => self::get('header_text'),
            'header_subtitle' => self::get('header_subtitle'),
            'header_bg_color' => self::get('header_bg_color'),
            'header_text_color' => self::get('header_text_color'),
            'font_family' => self::get('font_family'),
            'heading_font_size' => self::get('heading_font_size'),
            'body_font_size' => self::get('body_font_size'),
            'body_text_color' => self::get('body_text_color'),
            'body_bg_color' => self::get('body_bg_color'),
            'ticket_label' => self::get('ticket_label'),
            'ticket_bg_color' => self::get('ticket_bg_color'),
            'ticket_code_color' => self::get('ticket_code_color'),
            'ticket_code_size' => self::get('ticket_code_size'),
            'event_heading' => self::get('event_heading'),
            'event_heading_color' => self::get('event_heading_color'),
            'event_details_bg_color' => self::get('event_details_bg_color'),
            'event_label_color' => self::get('event_label_color'),
            'qr_heading' => self::get('qr_heading'),
            'qr_bg_color' => self::get('qr_bg_color'),
            'show_qr_code' => self::get('show_qr_code'),
            'qr_size' => self::get('qr_size'),
            'qr_instructions' => self::get('qr_instructions'),
            'ce_badge_bg_color' => self::get('ce_badge_bg_color'),
            'ce_badge_text_color' => self::get('ce_badge_text_color'),
            'footer_text' => self::get('footer_text'),
            'footer_bg_color' => self::get('footer_bg_color'),
            'footer_text_color' => self::get('footer_text_color'),
            'footer_social_links' => self::get('footer_social_links'),
            'footer_address' => self::get('footer_address'),
            'button_text' => self::get('button_text'),
            'button_bg_color' => self::get('button_bg_color'),
            'button_text_color' => self::get('button_text_color'),
            'button_border_radius' => self::get('button_border_radius'),
            'secondary_button_text' => self::get('secondary_button_text'),
            'container_width' => self::get('container_width'),
            'border_radius' => self::get('border_radius'),
            'inner_padding' => self::get('inner_padding'),
            'welcome_message' => self::get('welcome_message'),
            'additional_info' => self::get('additional_info'),
            'support_email' => self::get('support_email'),
            'support_phone' => self::get('support_phone'),
        ];

        // Build test email HTML
        ob_start();
        self::render_test_email_content($settings);
        $message = ob_get_clean();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>'
        ];

        $subject = __('Test Email - GPS Courses Ticket System', 'gps-courses');

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            wp_send_json_success(['message' => __('Test email sent successfully! Check your inbox.', 'gps-courses')]);
        } else {
            wp_send_json_error(['message' => __('Failed to send test email. Please check your email configuration.', 'gps-courses')]);
        }
    }

    /**
     * Render test email content
     */
    private static function render_test_email_content($settings) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: <?php echo esc_attr($settings['font_family']); ?>; background: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f5f5f5; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table width="<?php echo esc_attr($settings['container_width']); ?>" cellpadding="0" cellspacing="0" border="0" style="max-width: <?php echo esc_attr($settings['container_width']); ?>; background: <?php echo esc_attr($settings['body_bg_color']); ?>; border-radius: <?php echo esc_attr($settings['border_radius']); ?>; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">

                            <?php if (!empty($settings['logo'])): ?>
                            <tr>
                                <td style="text-align: center; padding: 30px; background: #ffffff;">
                                    <img src="<?php echo esc_url($settings['logo']); ?>" alt="Logo" style="max-width: 200px; height: auto;">
                                </td>
                            </tr>
                            <?php endif; ?>

                            <tr>
                                <td style="background: <?php echo esc_attr($settings['header_bg_color']); ?>; color: <?php echo esc_attr($settings['header_text_color']); ?>; padding: <?php echo esc_attr($settings['inner_padding']); ?>; text-align: center;">
                                    <h1 style="margin: 0; font-size: <?php echo esc_attr($settings['heading_font_size']); ?>; font-weight: bold;">
                                        🎫 <?php echo esc_html($settings['header_text']); ?>
                                    </h1>
                                    <?php if (!empty($settings['header_subtitle'])): ?>
                                    <p style="margin: 10px 0 0; font-size: <?php echo esc_attr($settings['body_font_size']); ?>; opacity: 0.9;">
                                        <?php echo esc_html($settings['header_subtitle']); ?>
                                    </p>
                                    <?php endif; ?>
                                    <p style="margin: 10px 0 0; font-size: <?php echo esc_attr($settings['body_font_size']); ?>; opacity: 0.9;">
                                        Sample Event - Test Email Preview
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td style="background: <?php echo esc_attr($settings['ticket_bg_color']); ?>; padding: 25px; text-align: center; border-bottom: 3px dashed #dee2e6;">
                                    <p style="margin: 0 0 10px 0; font-size: 12px; color: <?php echo esc_attr($settings['event_label_color']); ?>; text-transform: uppercase; letter-spacing: 1px;">
                                        <?php echo esc_html($settings['ticket_label']); ?>
                                    </p>
                                    <div style="font-size: <?php echo esc_attr($settings['ticket_code_size']); ?>; font-weight: bold; color: <?php echo esc_attr($settings['ticket_code_color']); ?>; font-family: 'Courier New', monospace;">
                                        TEST-1234-5678-ABCD
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td style="background: <?php echo esc_attr($settings['event_details_bg_color']); ?>; padding: <?php echo esc_attr($settings['inner_padding']); ?>;">
                                    <h2 style="margin: 0 0 20px 0; font-size: calc(<?php echo esc_attr($settings['heading_font_size']); ?> - 6px); color: <?php echo esc_attr($settings['event_heading_color']); ?>; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                                        📅 <?php echo esc_html($settings['event_heading']); ?>
                                    </h2>

                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: <?php echo esc_attr($settings['body_font_size']); ?>; color: <?php echo esc_attr($settings['body_text_color']); ?>;">
                                        <tr>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: <?php echo esc_attr($settings['event_label_color']); ?>;">
                                                <strong>Start:</strong>
                                            </td>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                December 15, 2025 at 9:00 AM
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: <?php echo esc_attr($settings['event_label_color']); ?>;">
                                                <strong>End:</strong>
                                            </td>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                December 15, 2025 at 5:00 PM
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: <?php echo esc_attr($settings['event_label_color']); ?>;">
                                                <strong>Location:</strong>
                                            </td>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                GPS Training Center
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 12px 0; color: <?php echo esc_attr($settings['event_label_color']); ?>;">
                                                <strong>CE Credits:</strong>
                                            </td>
                                            <td style="padding: 12px 0; text-align: right;">
                                                <span style="display: inline-block; padding: 5px 15px; background: <?php echo esc_attr($settings['ce_badge_bg_color']); ?>; color: <?php echo esc_attr($settings['ce_badge_text_color']); ?>; border-radius: 20px; font-weight: bold; font-size: <?php echo esc_attr($settings['body_font_size']); ?>;">
                                                    10 Credits
                                                </span>
                                            </td>
                                        </tr>
                                    </table>

                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="text-align: center; padding: 30px 0;">
                                                <a href="#" style="display: inline-block; padding: 14px 40px; background: <?php echo esc_attr($settings['button_bg_color']); ?>; color: <?php echo esc_attr($settings['button_text_color']); ?>; text-decoration: none; border-radius: <?php echo esc_attr($settings['button_border_radius']); ?>; font-weight: 600; font-size: <?php echo esc_attr($settings['body_font_size']); ?>;">
                                                    <?php echo esc_html($settings['button_text']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <?php if ($settings['show_qr_code']): ?>
                            <tr>
                                <td style="background: <?php echo esc_attr($settings['qr_bg_color']); ?>; padding: <?php echo esc_attr($settings['inner_padding']); ?>; text-align: center; border-top: 1px solid #e2e8f0;">
                                    <h3 style="margin: 0 0 20px 0; font-size: calc(<?php echo esc_attr($settings['heading_font_size']); ?> - 10px); font-weight: 600; color: <?php echo esc_attr($settings['body_text_color']); ?>;">
                                        📱 <?php echo esc_html($settings['qr_heading']); ?>
                                    </h3>
                                    <p style="margin: 0 0 15px 0; color: <?php echo esc_attr($settings['event_label_color']); ?>; font-size: <?php echo esc_attr($settings['body_font_size']); ?>;">
                                        <?php echo esc_html($settings['qr_instructions']); ?>
                                    </p>
                                    <div style="background: white; display: inline-block; padding: 15px; border-radius: <?php echo esc_attr($settings['border_radius']); ?>;">
                                        <div style="width: <?php echo esc_attr($settings['qr_size']); ?>px; height: <?php echo esc_attr($settings['qr_size']); ?>px; background: #f0f0f0; display: inline-flex; align-items: center; justify-content: center; color: #999; font-size: <?php echo esc_attr($settings['body_font_size']); ?>;">
                                            [QR Code]
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <tr>
                                <td style="background: <?php echo esc_attr($settings['footer_bg_color']); ?>; color: <?php echo esc_attr($settings['footer_text_color']); ?>; padding: <?php echo esc_attr($settings['inner_padding']); ?>; text-align: center; font-size: <?php echo esc_attr($settings['body_font_size']); ?>;">
                                    <p style="margin: 0 0 15px 0;">
                                        <?php echo nl2br(esc_html($settings['footer_text'])); ?>
                                    </p>
                                    <?php if (!empty($settings['footer_address'])): ?>
                                    <p style="margin: 15px 0; font-size: calc(<?php echo esc_attr($settings['body_font_size']); ?> - 2px); opacity: 0.8;">
                                        <?php echo nl2br(esc_html($settings['footer_address'])); ?>
                                    </p>
                                    <?php endif; ?>
                                    <p style="margin: 15px 0 0; font-size: calc(<?php echo esc_attr($settings['body_font_size']); ?> - 2px); opacity: 0.7;">
                                        © <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
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
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle settings save
        if (isset($_GET['settings-updated'])) {
            add_settings_error('gps_email_settings', 'gps_email_settings_updated', __('Settings saved successfully!', 'gps-courses'), 'updated');
        }

        ?>
        <style>
            .gps-email-settings-wrap {
                background: #fff;
                margin: 20px 0;
            }
            .gps-settings-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                margin: 0 0 30px 0;
                border-radius: 8px;
            }
            .gps-settings-header h1 {
                margin: 0 0 10px 0;
                color: white;
                font-size: 32px;
            }
            .gps-settings-header p {
                margin: 0;
                opacity: 0.95;
                font-size: 16px;
            }
            .gps-settings-navigation {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 0;
                margin: 0 0 30px 0;
                display: flex;
                flex-wrap: wrap;
                overflow: hidden;
            }
            .gps-settings-navigation a {
                flex: 1;
                min-width: 150px;
                padding: 15px 20px;
                text-decoration: none;
                color: #495057;
                border-right: 1px solid #dee2e6;
                transition: all 0.3s;
                font-weight: 500;
                text-align: center;
            }
            .gps-settings-navigation a:last-child {
                border-right: none;
            }
            .gps-settings-navigation a:hover {
                background: #e9ecef;
                color: #212529;
            }
            .gps-settings-navigation a.active {
                background: #667eea;
                color: white;
            }
            .gps-settings-container {
                max-width: 1400px;
            }
            .gps-settings-row {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 30px;
                margin-bottom: 30px;
            }
            @media (max-width: 1200px) {
                .gps-settings-row {
                    grid-template-columns: 1fr;
                }
            }
            .gps-settings-column {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            .gps-postbox {
                background: white;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .gps-postbox-header {
                background: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
                padding: 15px 20px;
                border-radius: 8px 8px 0 0;
            }
            .gps-postbox-header h2 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                color: #212529;
            }
            .gps-postbox-content {
                padding: 20px;
            }
            .gps-form-table {
                width: 100%;
            }
            .gps-form-table tr {
                border-bottom: 1px solid #f0f0f0;
            }
            .gps-form-table tr:last-child {
                border-bottom: none;
            }
            .gps-form-table th {
                padding: 15px 15px 15px 0;
                text-align: left;
                width: 35%;
                font-weight: 600;
                color: #495057;
                vertical-align: top;
                padding-top: 20px;
            }
            .gps-form-table td {
                padding: 15px 0;
            }
            .gps-test-email-box {
                background: #e7f3ff;
                border: 2px solid #2271b1;
                border-radius: 8px;
                padding: 20px;
            }
            .gps-test-email-box h3 {
                margin: 0 0 15px 0;
                color: #2271b1;
                font-size: 18px;
            }
            .gps-test-email-form {
                display: flex;
                gap: 10px;
                align-items: flex-start;
                flex-wrap: wrap;
            }
            .gps-test-email-form input[type="email"] {
                flex: 1;
                min-width: 250px;
            }
            .gps-test-email-result {
                margin-top: 15px;
                padding: 12px;
                border-radius: 4px;
                display: none;
            }
            .gps-test-email-result.success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }
            .gps-test-email-result.error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
            .gps-color-picker-wrapper {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .wp-picker-container {
                margin-top: 0 !important;
            }
        </style>

        <div class="wrap gps-email-settings-wrap">
            <div class="gps-settings-header">
                <h1>✉️ <?php echo esc_html(get_admin_page_title()); ?></h1>
                <p><?php _e('Customize every aspect of your ticket confirmation emails with live preview', 'gps-courses'); ?></p>
            </div>

            <?php settings_errors('gps_email_settings'); ?>

            <div class="gps-settings-navigation">
                <a href="#general" class="gps-nav-tab active" data-target="general">🎨 <?php _e('General', 'gps-courses'); ?></a>
                <a href="#typography" class="gps-nav-tab" data-target="typography">✍️ <?php _e('Typography', 'gps-courses'); ?></a>
                <a href="#header" class="gps-nav-tab" data-target="header">🎯 <?php _e('Header', 'gps-courses'); ?></a>
                <a href="#content" class="gps-nav-tab" data-target="content">📄 <?php _e('Content', 'gps-courses'); ?></a>
                <a href="#footer" class="gps-nav-tab" data-target="footer">🔗 <?php _e('Footer', 'gps-courses'); ?></a>
                <a href="#layout" class="gps-nav-tab" data-target="layout">📐 <?php _e('Layout', 'gps-courses'); ?></a>
                <a href="#test" class="gps-nav-tab" data-target="test">🧪 <?php _e('Test Email', 'gps-courses'); ?></a>
            </div>

            <form method="post" action="options.php" id="gps-email-settings-form">
                <?php
                settings_fields('gps_email_settings');
                ?>

                <div class="gps-settings-container">

                    <!-- General Settings -->
                    <div class="postbox" style="margin-top: 20px;">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('General Settings', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_logo"><?php _e('Email Logo', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        $logo = self::get('logo');
                                        ?>
                                        <div class="gps-logo-upload-wrapper">
                                            <input type="hidden" id="gps_email_logo" name="gps_email_logo" value="<?php echo esc_attr($logo); ?>">
                                            <div class="gps-logo-preview" style="margin-bottom: 10px;">
                                                <?php if ($logo): ?>
                                                    <img src="<?php echo esc_url($logo); ?>" style="max-width: 200px; height: auto; display: block; margin-bottom: 10px;">
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="button gps-upload-logo-button"><?php _e('Upload Logo', 'gps-courses'); ?></button>
                                            <?php if ($logo): ?>
                                                <button type="button" class="button gps-remove-logo-button"><?php _e('Remove Logo', 'gps-courses'); ?></button>
                                            <?php endif; ?>
                                        </div>
                                        <p class="description"><?php _e('Upload a logo to display at the top of ticket emails.', 'gps-courses'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_from_name"><?php _e('From Name', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_from_name" name="gps_email_from_name" value="<?php echo esc_attr(self::get('from_name')); ?>" class="regular-text">
                                        <p class="description"><?php _e('The name that appears in the "From" field of emails.', 'gps-courses'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_from_email"><?php _e('From Email', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="email" id="gps_email_from_email" name="gps_email_from_email" value="<?php echo esc_attr(self::get('from_email')); ?>" class="regular-text">
                                        <p class="description"><?php _e('The email address that appears in the "From" field of emails.', 'gps-courses'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Typography Settings -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Typography Settings', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_font_family"><?php _e('Font Family', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <select id="gps_email_font_family" name="gps_email_font_family" class="regular-text">
                                            <option value="Arial, sans-serif" <?php selected(self::get('font_family'), 'Arial, sans-serif'); ?>>Arial</option>
                                            <option value="Helvetica, sans-serif" <?php selected(self::get('font_family'), 'Helvetica, sans-serif'); ?>>Helvetica</option>
                                            <option value="Georgia, serif" <?php selected(self::get('font_family'), 'Georgia, serif'); ?>>Georgia</option>
                                            <option value="'Times New Roman', serif" <?php selected(self::get('font_family'), "'Times New Roman', serif"); ?>>Times New Roman</option>
                                            <option value="'Courier New', monospace" <?php selected(self::get('font_family'), "'Courier New', monospace"); ?>>Courier New</option>
                                        </select>
                                        <p class="description"><?php _e('Font family for all email text.', 'gps-courses'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_heading_font_size"><?php _e('Heading Font Size', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_heading_font_size" name="gps_email_heading_font_size" value="<?php echo esc_attr(self::get('heading_font_size')); ?>" class="small-text">
                                        <p class="description"><?php _e('Default: 28px. Controls main heading size.', 'gps-courses'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_body_font_size"><?php _e('Body Font Size', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_body_font_size" name="gps_email_body_font_size" value="<?php echo esc_attr(self::get('body_font_size')); ?>" class="small-text">
                                        <p class="description"><?php _e('Default: 14px. Controls body text size.', 'gps-courses'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_body_text_color"><?php _e('Body Text Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_body_text_color" name="gps_email_body_text_color" value="<?php echo esc_attr(self::get('body_text_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_body_bg_color"><?php _e('Body Background Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_body_bg_color" name="gps_email_body_bg_color" value="<?php echo esc_attr(self::get('body_bg_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Header Settings -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Header Settings', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_header_text"><?php _e('Header Text', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_header_text" name="gps_email_header_text" value="<?php echo esc_attr(self::get('header_text')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_header_subtitle"><?php _e('Header Subtitle', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_header_subtitle" name="gps_email_header_subtitle" value="<?php echo esc_attr(self::get('header_subtitle')); ?>" class="regular-text">
                                        <p class="description"><?php _e('Optional subtitle text below main header (e.g., "Your tickets are confirmed!").', 'gps-courses'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_header_bg_color"><?php _e('Header Background Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_header_bg_color" name="gps_email_header_bg_color" value="<?php echo esc_attr(self::get('header_bg_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_header_text_color"><?php _e('Header Text Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_header_text_color" name="gps_email_header_text_color" value="<?php echo esc_attr(self::get('header_text_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Ticket Code Section -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Ticket Code Section', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_ticket_label"><?php _e('Ticket Code Label', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_ticket_label" name="gps_email_ticket_label" value="<?php echo esc_attr(self::get('ticket_label')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_ticket_bg_color"><?php _e('Background Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_ticket_bg_color" name="gps_email_ticket_bg_color" value="<?php echo esc_attr(self::get('ticket_bg_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_ticket_code_color"><?php _e('Ticket Code Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_ticket_code_color" name="gps_email_ticket_code_color" value="<?php echo esc_attr(self::get('ticket_code_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Event Details Section -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Event Details Section', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_event_heading"><?php _e('Section Heading', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_event_heading" name="gps_email_event_heading" value="<?php echo esc_attr(self::get('event_heading')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_event_heading_color"><?php _e('Heading Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_event_heading_color" name="gps_email_event_heading_color" value="<?php echo esc_attr(self::get('event_heading_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- QR Code Section -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('QR Code Section', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_show_qr_code"><?php _e('Show QR Code', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="gps_email_show_qr_code" name="gps_email_show_qr_code" value="1" <?php checked(self::get('show_qr_code'), true); ?>>
                                            <?php _e('Display QR code in ticket emails', 'gps-courses'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_qr_heading"><?php _e('QR Section Heading', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_qr_heading" name="gps_email_qr_heading" value="<?php echo esc_attr(self::get('qr_heading')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_qr_bg_color"><?php _e('Background Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_qr_bg_color" name="gps_email_qr_bg_color" value="<?php echo esc_attr(self::get('qr_bg_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- CE Credits Badge -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('CE Credits Badge', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_ce_badge_bg_color"><?php _e('Badge Background Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_ce_badge_bg_color" name="gps_email_ce_badge_bg_color" value="<?php echo esc_attr(self::get('ce_badge_bg_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_ce_badge_text_color"><?php _e('Badge Text Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_ce_badge_text_color" name="gps_email_ce_badge_text_color" value="<?php echo esc_attr(self::get('ce_badge_text_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Footer Section -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Footer Section', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_footer_text"><?php _e('Footer Text', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <textarea id="gps_email_footer_text" name="gps_email_footer_text" rows="3" class="large-text"><?php echo esc_textarea(self::get('footer_text')); ?></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_footer_bg_color"><?php _e('Background Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_footer_bg_color" name="gps_email_footer_bg_color" value="<?php echo esc_attr(self::get('footer_bg_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_footer_text_color"><?php _e('Text Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_footer_text_color" name="gps_email_footer_text_color" value="<?php echo esc_attr(self::get('footer_text_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Button Settings -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Button Settings', 'gps-courses'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_button_text"><?php _e('Button Text', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_button_text" name="gps_email_button_text" value="<?php echo esc_attr(self::get('button_text')); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_button_bg_color"><?php _e('Button Background Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_button_bg_color" name="gps_email_button_bg_color" value="<?php echo esc_attr(self::get('button_bg_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="gps_email_button_text_color"><?php _e('Button Text Color', 'gps-courses'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="gps_email_button_text_color" name="gps_email_button_text_color" value="<?php echo esc_attr(self::get('button_text_color')); ?>" class="gps-color-picker">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php submit_button(__('Save Settings', 'gps-courses')); ?>

                </div>
            </form>

            <!-- Preview Section -->
            <div class="postbox" style="max-width: 1200px; margin-top: 20px;">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('Email Preview', 'gps-courses'); ?></h2>
                </div>
                <div class="inside">
                    <p class="description"><?php _e('This is a preview of how your ticket email will look. Save settings to see changes.', 'gps-courses'); ?></p>
                    <div style="background: #f5f5f5; padding: 20px; margin-top: 20px;">
                        <?php self::render_email_preview(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render email preview
     */
    private static function render_email_preview() {
        $header_bg = self::get('header_bg_color');
        $header_text_color = self::get('header_text_color');
        $header_text = self::get('header_text');
        $ticket_bg = self::get('ticket_bg_color');
        $ticket_code_color = self::get('ticket_code_color');
        $ticket_label = self::get('ticket_label');
        $event_heading = self::get('event_heading');
        $event_heading_color = self::get('event_heading_color');
        $qr_bg = self::get('qr_bg_color');
        $qr_heading = self::get('qr_heading');
        $ce_badge_bg = self::get('ce_badge_bg_color');
        $ce_badge_text = self::get('ce_badge_text_color');
        $footer_bg = self::get('footer_bg_color');
        $footer_text_color = self::get('footer_text_color');
        $footer_text = self::get('footer_text');
        $button_bg = self::get('button_bg_color');
        $button_text_color = self::get('button_text_color');
        $button_text = self::get('button_text');
        $logo = self::get('logo');
        $show_qr = self::get('show_qr_code');
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white;">

            <?php if ($logo): ?>
            <div style="text-align: center; padding: 20px; background: white;">
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 200px; height: auto;">
            </div>
            <?php endif; ?>

            <div style="background: <?php echo esc_attr($header_bg); ?>; color: <?php echo esc_attr($header_text_color); ?>; padding: 40px 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: bold;">
                    🎫 <?php echo esc_html($header_text); ?>
                </h1>
                <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">
                    Sample Event Name
                </p>
            </div>

            <div style="background: <?php echo esc_attr($ticket_bg); ?>; padding: 25px; text-align: center; border-bottom: 3px dashed #dee2e6;">
                <p style="margin: 0 0 10px 0; font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 1px;">
                    <?php echo esc_html($ticket_label); ?>
                </p>
                <div style="font-size: 24px; font-weight: bold; color: <?php echo esc_attr($ticket_code_color); ?>; font-family: 'Courier New', monospace;">
                    GPST-1234-5678-ABCD
                </div>
            </div>

            <div style="padding: 30px;">
                <h2 style="margin: 0 0 20px 0; font-size: 20px; color: <?php echo esc_attr($event_heading_color); ?>; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    📅 <?php echo esc_html($event_heading); ?>
                </h2>

                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                            <strong>Location:</strong>
                        </td>
                        <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                            GPS Dental Training Center
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                            <strong>Ticket Type:</strong>
                        </td>
                        <td style="padding: 10px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                            General Admission
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0;">
                            <strong>CE Credits:</strong>
                        </td>
                        <td style="padding: 10px 0; text-align: right;">
                            <span style="display: inline-block; padding: 5px 15px; background: <?php echo esc_attr($ce_badge_bg); ?>; color: <?php echo esc_attr($ce_badge_text); ?>; border-radius: 20px; font-weight: bold; font-size: 14px;">
                                10 Credits
                            </span>
                        </td>
                    </tr>
                </table>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="display: inline-block; padding: 14px 40px; background: <?php echo esc_attr($button_bg); ?>; color: <?php echo esc_attr($button_text_color); ?>; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">
                        <?php echo esc_html($button_text); ?>
                    </a>
                </div>
            </div>

            <?php if ($show_qr): ?>
            <div style="background: <?php echo esc_attr($qr_bg); ?>; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: #1e293b;">
                    📱 <?php echo esc_html($qr_heading); ?>
                </h3>
                <p style="margin: 0 0 15px 0; color: #6c757d;">Show this QR code at check-in</p>
                <div style="background: white; display: inline-block; padding: 15px; border-radius: 8px;">
                    <div style="width: 150px; height: 150px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">
                        [QR Code]
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div style="background: <?php echo esc_attr($footer_bg); ?>; color: <?php echo esc_attr($footer_text_color); ?>; padding: 30px; text-align: center;">
                <p style="margin: 0; font-size: 14px;">
                    <?php echo nl2br(esc_html($footer_text)); ?>
                </p>
                <p style="margin: 15px 0 0; font-size: 12px; opacity: 0.7;">
                    © <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
