<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Enhanced Email Settings and Customization
 * Complete redesign with modern UI, tabbed interface, and full test email functionality
 */
class Email_Settings {

    /**
     * Initialize the class
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page'], 99);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_gps_send_test_email', [__CLASS__, 'handle_test_email']);
        add_action('wp_ajax_gps_preview_ticket_email', [__CLASS__, 'handle_preview_ticket_email']);
        add_action('wp_ajax_gps_preview_seminar_welcome', [__CLASS__, 'handle_preview_seminar_welcome']);
        add_action('wp_ajax_gps_preview_ce_credits', [__CLASS__, 'handle_preview_ce_credits']);
        add_action('wp_ajax_gps_preview_session_reminder', [__CLASS__, 'handle_preview_session_reminder']);
        add_action('wp_ajax_gps_preview_missed_session', [__CLASS__, 'handle_preview_missed_session']);
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
            [__CLASS__, 'render_settings_page'],
            52  // Settings section
        );
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts($hook) {
        // Check if we're on the Email Settings page by checking the page parameter
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';

        if ($current_page !== 'gps-email-settings') {
            return;
        }

        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');

        // Enqueue media uploader
        wp_enqueue_media();

        // Enqueue custom admin script
        wp_enqueue_script(
            'gps-email-settings',
            GPSC_URL . 'assets/js/admin-email-settings.js',
            ['jquery', 'wp-color-picker', 'media-editor'],
            GPSC_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('gps-email-settings', 'gpsEmailSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_test_email'),
            'strings' => [
                'sending' => __('Sending...', 'gps-courses'),
                'sent' => __('Test email sent successfully!', 'gps-courses'),
                'error' => __('Failed to send test email.', 'gps-courses'),
            ]
        ]);
    }

    /**
     * Register all settings
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
     * Handle AJAX test email sending
     */
    public static function handle_test_email() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'gps-courses')]);
        }

        // Verify nonce
        check_ajax_referer('gps_test_email', 'nonce');

        // Get and validate email
        $to = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : 'ticket';

        if (empty($to) || !is_email($to)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'gps-courses')]);
        }

        // Prepare email content based on template
        ob_start();
        switch ($template) {
            case 'seminar_welcome':
                self::render_seminar_welcome_preview();
                $subject = __('Test Email - Seminar Welcome', 'gps-courses');
                break;
            case 'ce_credits':
                self::render_ce_credits_preview();
                $subject = __('Test Email - CE Credits Awarded', 'gps-courses');
                break;
            case 'session_reminder':
                self::render_session_reminder_preview();
                $subject = __('Test Email - Session Reminder', 'gps-courses');
                break;
            case 'missed_session':
                self::render_missed_session_preview();
                $subject = __('Test Email - Missed Session Alert', 'gps-courses');
                break;
            case 'ticket':
            default:
                self::render_test_email_content();
                $subject = __('Test Email - Purchase Ticket', 'gps-courses');
                break;
        }
        $message = ob_get_clean();

        // Prepare headers
        $from_name = self::get('from_name');
        $from_email = self::get('from_email');

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        ];

        // Log email attempt
        error_log('GPS Test Email - Template: ' . $template);
        error_log('GPS Test Email - Attempting to send to: ' . $to);
        error_log('GPS Test Email - From: ' . $from_name . ' <' . $from_email . '>');
        error_log('GPS Test Email - Subject: ' . $subject);

        // Send email
        $sent = wp_mail($to, $subject, $message, $headers);

        // Log result
        error_log('GPS Test Email - wp_mail result: ' . ($sent ? 'true' : 'false'));

        if ($sent) {
            wp_send_json_success(['message' => __('Test email sent successfully! Check your inbox (and spam folder). If you don\'t receive it, your server may need SMTP configuration.', 'gps-courses')]);
        } else {
            // Get the last error from wp_mail
            global $phpmailer;
            $error_message = '';
            if (isset($phpmailer) && isset($phpmailer->ErrorInfo)) {
                $error_message = $phpmailer->ErrorInfo;
                error_log('GPS Test Email - PHPMailer Error: ' . $error_message);
            }

            $message = __('Failed to send test email. ', 'gps-courses');
            if ($error_message) {
                $message .= 'Error: ' . $error_message;
            } else {
                $message .= __('Please check your email configuration or install an SMTP plugin.', 'gps-courses');
            }

            wp_send_json_error(['message' => $message]);
        }
    }

    /**
     * Render test email content
     */
    private static function render_test_email_content() {
        // Get all current settings
        $logo = self::get('logo');
        $font_family = self::get('font_family');
        $heading_font_size = self::get('heading_font_size');
        $body_font_size = self::get('body_font_size');
        $body_text_color = self::get('body_text_color');
        $body_bg_color = self::get('body_bg_color');
        $header_text = self::get('header_text');
        $header_subtitle = self::get('header_subtitle');
        $header_bg_color = self::get('header_bg_color');
        $header_text_color = self::get('header_text_color');
        $ticket_label = self::get('ticket_label');
        $ticket_bg_color = self::get('ticket_bg_color');
        $ticket_code_color = self::get('ticket_code_color');
        $ticket_code_size = self::get('ticket_code_size');
        $event_heading = self::get('event_heading');
        $event_heading_color = self::get('event_heading_color');
        $event_details_bg_color = self::get('event_details_bg_color');
        $event_label_color = self::get('event_label_color');
        $qr_heading = self::get('qr_heading');
        $qr_bg_color = self::get('qr_bg_color');
        $show_qr_code = self::get('show_qr_code');
        $qr_size = self::get('qr_size');
        $qr_instructions = self::get('qr_instructions');
        $ce_badge_bg_color = self::get('ce_badge_bg_color');
        $ce_badge_text_color = self::get('ce_badge_text_color');
        $footer_text = self::get('footer_text');
        $footer_bg_color = self::get('footer_bg_color');
        $footer_text_color = self::get('footer_text_color');
        $footer_address = self::get('footer_address');
        $button_text = self::get('button_text');
        $button_bg_color = self::get('button_bg_color');
        $button_text_color = self::get('button_text_color');
        $button_border_radius = self::get('button_border_radius');
        $container_width = self::get('container_width');
        $border_radius = self::get('border_radius');
        $inner_padding = self::get('inner_padding');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: <?php echo esc_attr($font_family); ?>; background: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f5f5f5; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table width="<?php echo esc_attr($container_width); ?>" cellpadding="0" cellspacing="0" border="0" style="max-width: <?php echo esc_attr($container_width); ?>; background: <?php echo esc_attr($body_bg_color); ?>; border-radius: <?php echo esc_attr($border_radius); ?>; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                            <?php if (!empty($logo)): ?>
                            <tr>
                                <td style="text-align: center; padding: 30px; background: #ffffff;">
                                    <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 200px; height: auto;">
                                </td>
                            </tr>
                            <?php endif; ?>

                            <tr>
                                <td style="background: <?php echo esc_attr($header_bg_color); ?>; color: <?php echo esc_attr($header_text_color); ?>; padding: <?php echo esc_attr($inner_padding); ?>; text-align: center;">
                                    <h1 style="margin: 0; font-size: <?php echo esc_attr($heading_font_size); ?>; font-weight: bold;">
                                        <?php echo esc_html($header_text); ?>
                                    </h1>
                                    <?php if (!empty($header_subtitle)): ?>
                                    <p style="margin: 10px 0 0; font-size: <?php echo esc_attr($body_font_size); ?>; opacity: 0.9;">
                                        <?php echo esc_html($header_subtitle); ?>
                                    </p>
                                    <?php endif; ?>
                                    <p style="margin: 10px 0 0; font-size: <?php echo esc_attr($body_font_size); ?>; opacity: 0.9;">
                                        Sample Event - Test Email Preview
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td style="background: <?php echo esc_attr($ticket_bg_color); ?>; padding: 25px; text-align: center; border-bottom: 3px dashed #dee2e6;">
                                    <p style="margin: 0 0 10px 0; font-size: 12px; color: <?php echo esc_attr($event_label_color); ?>; text-transform: uppercase; letter-spacing: 1px;">
                                        <?php echo esc_html($ticket_label); ?>
                                    </p>
                                    <div style="font-size: <?php echo esc_attr($ticket_code_size); ?>; font-weight: bold; color: <?php echo esc_attr($ticket_code_color); ?>; font-family: 'Courier New', monospace;">
                                        TEST-1234-5678-ABCD
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td style="background: <?php echo esc_attr($event_details_bg_color); ?>; padding: <?php echo esc_attr($inner_padding); ?>;">
                                    <h2 style="margin: 0 0 20px 0; font-size: calc(<?php echo esc_attr($heading_font_size); ?> - 6px); color: <?php echo esc_attr($event_heading_color); ?>; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                                        <?php echo esc_html($event_heading); ?>
                                    </h2>

                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: <?php echo esc_attr($body_font_size); ?>; color: <?php echo esc_attr($body_text_color); ?>;">
                                        <tr>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: <?php echo esc_attr($event_label_color); ?>;">
                                                <strong>Start:</strong>
                                            </td>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                December 15, 2025 at 9:00 AM
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: <?php echo esc_attr($event_label_color); ?>;">
                                                <strong>End:</strong>
                                            </td>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                December 15, 2025 at 5:00 PM
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: <?php echo esc_attr($event_label_color); ?>;">
                                                <strong>Location:</strong>
                                            </td>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                GPS Training Center
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 12px 0; color: <?php echo esc_attr($event_label_color); ?>;">
                                                <strong>CE Credits:</strong>
                                            </td>
                                            <td style="padding: 12px 0; text-align: right;">
                                                <span style="display: inline-block; padding: 5px 15px; background: <?php echo esc_attr($ce_badge_bg_color); ?>; color: <?php echo esc_attr($ce_badge_text_color); ?>; border-radius: 20px; font-weight: bold; font-size: <?php echo esc_attr($body_font_size); ?>;">
                                                    10 Credits
                                                </span>
                                            </td>
                                        </tr>
                                    </table>

                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="text-align: center; padding: 30px 0;">
                                                <a href="#" style="display: inline-block; padding: 14px 40px; background: <?php echo esc_attr($button_bg_color); ?>; color: <?php echo esc_attr($button_text_color); ?>; text-decoration: none; border-radius: <?php echo esc_attr($button_border_radius); ?>; font-weight: 600; font-size: <?php echo esc_attr($body_font_size); ?>;">
                                                    <?php echo esc_html($button_text); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <?php if ($show_qr_code): ?>
                            <tr>
                                <td style="background: <?php echo esc_attr($qr_bg_color); ?>; padding: <?php echo esc_attr($inner_padding); ?>; text-align: center; border-top: 1px solid #e2e8f0;">
                                    <h3 style="margin: 0 0 20px 0; font-size: calc(<?php echo esc_attr($heading_font_size); ?> - 10px); font-weight: 600; color: <?php echo esc_attr($body_text_color); ?>;">
                                        <?php echo esc_html($qr_heading); ?>
                                    </h3>
                                    <p style="margin: 0 0 15px 0; color: <?php echo esc_attr($event_label_color); ?>; font-size: <?php echo esc_attr($body_font_size); ?>;">
                                        <?php echo esc_html($qr_instructions); ?>
                                    </p>
                                    <div style="background: white; display: inline-block; padding: 15px; border-radius: <?php echo esc_attr($border_radius); ?>;">
                                        <div style="width: <?php echo esc_attr($qr_size); ?>px; height: <?php echo esc_attr($qr_size); ?>px; background: #f0f0f0; display: inline-flex; align-items: center; justify-content: center; color: #999; font-size: <?php echo esc_attr($body_font_size); ?>;">
                                            [QR Code]
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <tr>
                                <td style="background: <?php echo esc_attr($footer_bg_color); ?>; color: <?php echo esc_attr($footer_text_color); ?>; padding: <?php echo esc_attr($inner_padding); ?>; text-align: center; font-size: <?php echo esc_attr($body_font_size); ?>;">
                                    <p style="margin: 0 0 15px 0;">
                                        <?php echo nl2br(esc_html($footer_text)); ?>
                                    </p>
                                    <?php if (!empty($footer_address)): ?>
                                    <p style="margin: 15px 0; font-size: calc(<?php echo esc_attr($body_font_size); ?> - 2px); opacity: 0.8;">
                                        <?php echo nl2br(esc_html($footer_address)); ?>
                                    </p>
                                    <?php endif; ?>
                                    <p style="margin: 15px 0 0; font-size: calc(<?php echo esc_attr($body_font_size); ?> - 2px); opacity: 0.7;">
                                        &copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
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
     * Render settings page with modern tabbed UI
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
                margin: 20px 20px 20px 0;
            }

            .gps-settings-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px;
                margin: 0 0 0 0;
                border-radius: 0;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .gps-settings-header h1 {
                margin: 0 0 10px 0;
                color: white;
                font-size: 36px;
                font-weight: 600;
            }

            .gps-settings-header p {
                margin: 0;
                opacity: 0.95;
                font-size: 16px;
            }

            .gps-settings-navigation {
                background: #fff;
                border-bottom: 3px solid #e5e7eb;
                padding: 0;
                margin: 0;
                display: flex;
                flex-wrap: wrap;
                gap: 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }

            .gps-nav-tab {
                flex: 1;
                min-width: 140px;
                padding: 18px 24px;
                text-decoration: none;
                color: #6b7280;
                border-bottom: 3px solid transparent;
                transition: all 0.3s ease;
                font-weight: 500;
                text-align: center;
                font-size: 14px;
                cursor: pointer;
                background: #fff;
            }

            .gps-nav-tab:hover {
                background: #f9fafb;
                color: #374151;
                border-bottom-color: #d1d5db;
            }

            .gps-nav-tab.active {
                background: #fff;
                color: #667eea;
                border-bottom-color: #667eea;
                font-weight: 600;
            }

            .gps-settings-container {
                max-width: 1600px;
                padding: 30px;
                background: #f9fafb;
            }

            .gps-tab-content {
                display: none;
            }

            .gps-tab-content.active {
                display: block;
                animation: fadeIn 0.3s ease-in;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .gps-settings-row {
                display: grid;
                grid-template-columns: 1fr;
                gap: 25px;
                margin-bottom: 25px;
            }

            .gps-postbox {
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                overflow: hidden;
            }

            .gps-postbox-header {
                background: linear-gradient(to right, #f9fafb, #ffffff);
                border-bottom: 1px solid #e5e7eb;
                padding: 20px 25px;
            }

            .gps-postbox-header h2 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: #1f2937;
            }

            .gps-postbox-content {
                padding: 25px;
            }

            .gps-form-table {
                width: 100%;
            }

            .gps-form-table tr {
                border-bottom: 1px solid #f3f4f6;
            }

            .gps-form-table tr:last-child {
                border-bottom: none;
            }

            .gps-form-table th {
                padding: 20px 20px 20px 0;
                text-align: left;
                width: 30%;
                font-weight: 600;
                color: #374151;
                vertical-align: top;
                font-size: 14px;
            }

            .gps-form-table td {
                padding: 20px 0;
            }

            .gps-form-table input[type="text"],
            .gps-form-table input[type="email"],
            .gps-form-table textarea,
            .gps-form-table select {
                width: 100%;
                max-width: 500px;
                padding: 10px 14px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.2s;
            }

            .gps-form-table input[type="text"]:focus,
            .gps-form-table input[type="email"]:focus,
            .gps-form-table textarea:focus,
            .gps-form-table select:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .gps-form-table input[type="text"].small-text {
                max-width: 120px;
            }

            .gps-form-table textarea {
                resize: vertical;
                min-height: 100px;
            }

            .gps-form-table .description {
                margin: 8px 0 0 0;
                color: #6b7280;
                font-size: 13px;
                font-style: italic;
            }

            .gps-logo-upload-wrapper {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .gps-logo-preview {
                margin-bottom: 0;
            }

            .gps-logo-preview img {
                max-width: 200px;
                height: auto;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                padding: 10px;
                background: #fff;
            }

            .button.gps-upload-logo-button,
            .button.gps-remove-logo-button {
                width: fit-content;
                padding: 8px 20px;
                font-weight: 500;
            }

            .gps-test-email-section {
                background: linear-gradient(135deg, #e0e7ff 0%, #f3e8ff 100%);
                border: 2px solid #a5b4fc;
                border-radius: 12px;
                padding: 30px;
                margin-top: 20px;
            }

            .gps-test-email-section h3 {
                margin: 0 0 20px 0;
                color: #4338ca;
                font-size: 22px;
                font-weight: 600;
            }

            .gps-test-email-form {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .gps-test-email-form input[type="email"] {
                flex: 1;
                min-width: 280px;
                padding: 12px 16px;
                border: 2px solid #a5b4fc;
                border-radius: 8px;
                font-size: 15px;
            }

            .gps-test-email-form input[type="email"]:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
            }

            .gps-test-email-form .button-primary {
                padding: 12px 30px;
                font-size: 15px;
                height: auto;
                background: #667eea;
                border-color: #667eea;
                font-weight: 600;
                box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
            }

            .gps-test-email-form .button-primary:hover {
                background: #5568d3;
                border-color: #5568d3;
                transform: translateY(-1px);
                box-shadow: 0 4px 6px rgba(102, 126, 234, 0.4);
            }

            .gps-test-email-result {
                margin-top: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                display: none;
                font-size: 14px;
                font-weight: 500;
            }

            .gps-test-email-result.success {
                background: #d1fae5;
                border: 2px solid #6ee7b7;
                color: #065f46;
            }

            .gps-test-email-result.error {
                background: #fee2e2;
                border: 2px solid #fca5a5;
                color: #991b1b;
            }

            .gps-email-preview-section {
                margin-top: 30px;
            }

            .gps-preview-container {
                background: #f5f5f5;
                padding: 30px;
                border-radius: 12px;
                margin-top: 20px;
            }

            .wp-color-result {
                height: 38px;
                width: 100px;
            }

            .submit-button-wrapper {
                position: sticky;
                bottom: 20px;
                background: white;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                margin-top: 30px;
                text-align: center;
            }

            .submit-button-wrapper .button-primary {
                padding: 14px 40px;
                font-size: 16px;
                height: auto;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                font-weight: 600;
                box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
            }

            .submit-button-wrapper .button-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
            }

            @media (max-width: 1200px) {
                .gps-settings-row {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 782px) {
                .gps-nav-tab {
                    min-width: 100px;
                    padding: 15px 12px;
                    font-size: 13px;
                }

                .gps-settings-container {
                    padding: 20px;
                }

                .gps-form-table th,
                .gps-form-table td {
                    display: block;
                    width: 100%;
                    padding: 10px 0;
                }
            }
        </style>

        <div class="wrap gps-email-settings-wrap">
            <div class="gps-settings-header">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p><?php _e('Customize every aspect of your ticket confirmation emails with live preview', 'gps-courses'); ?></p>
            </div>

            <?php settings_errors('gps_email_settings'); ?>

            <nav class="gps-settings-navigation">
                <a href="#general" class="gps-nav-tab active" data-tab="general">
                    <span style="font-size: 16px;">&#127912;</span> <?php _e('General', 'gps-courses'); ?>
                </a>
                <a href="#typography" class="gps-nav-tab" data-tab="typography">
                    <span style="font-size: 16px;">&#9997;</span> <?php _e('Typography', 'gps-courses'); ?>
                </a>
                <a href="#header" class="gps-nav-tab" data-tab="header">
                    <span style="font-size: 16px;">&#127919;</span> <?php _e('Header', 'gps-courses'); ?>
                </a>
                <a href="#content" class="gps-nav-tab" data-tab="content">
                    <span style="font-size: 16px;">&#128196;</span> <?php _e('Content', 'gps-courses'); ?>
                </a>
                <a href="#footer" class="gps-nav-tab" data-tab="footer">
                    <span style="font-size: 16px;">&#128279;</span> <?php _e('Footer', 'gps-courses'); ?>
                </a>
                <a href="#layout" class="gps-nav-tab" data-tab="layout">
                    <span style="font-size: 16px;">&#128208;</span> <?php _e('Layout', 'gps-courses'); ?>
                </a>
                <a href="#test" class="gps-nav-tab" data-tab="test">
                    <span style="font-size: 16px;">&#129514;</span> <?php _e('Test Email', 'gps-courses'); ?>
                </a>
            </nav>

            <form method="post" action="options.php" id="gps-email-settings-form">
                <?php settings_fields('gps_email_settings'); ?>

                <div class="gps-settings-container">

                    <!-- General Tab -->
                    <div id="tab-general" class="gps-tab-content active">
                        <div class="gps-settings-row">
                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('General Settings', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_logo"><?php _e('Email Logo', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <?php $logo = self::get('logo'); ?>
                                                <div class="gps-logo-upload-wrapper">
                                                    <input type="hidden" id="gps_email_logo" name="gps_email_logo" value="<?php echo esc_attr($logo); ?>">
                                                    <div class="gps-logo-preview">
                                                        <?php if ($logo): ?>
                                                            <img src="<?php echo esc_url($logo); ?>" style="max-width: 200px; height: auto; display: block; margin-bottom: 10px;">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <button type="button" class="button gps-upload-logo-button"><?php _e('Upload Logo', 'gps-courses'); ?></button>
                                                        <?php if ($logo): ?>
                                                            <button type="button" class="button gps-remove-logo-button"><?php _e('Remove Logo', 'gps-courses'); ?></button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <p class="description"><?php _e('Upload a logo to display at the top of ticket emails (max width: 200px recommended).', 'gps-courses'); ?></p>
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
                        </div>
                    </div>

                    <!-- Typography Tab -->
                    <div id="tab-typography" class="gps-tab-content">
                        <div class="gps-settings-row">
                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Typography Settings', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
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
                                                <p class="description"><?php _e('Color of body text throughout the email.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_body_bg_color"><?php _e('Body Background Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_body_bg_color" name="gps_email_body_bg_color" value="<?php echo esc_attr(self::get('body_bg_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Main background color of the email container.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Header Tab -->
                    <div id="tab-header" class="gps-tab-content">
                        <div class="gps-settings-row">
                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Header Section', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_header_text"><?php _e('Header Text', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_header_text" name="gps_email_header_text" value="<?php echo esc_attr(self::get('header_text')); ?>" class="regular-text">
                                                <p class="description"><?php _e('Main heading text at the top of the email.', 'gps-courses'); ?></p>
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
                                                <p class="description"><?php _e('Background color of the header section.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_header_text_color"><?php _e('Header Text Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_header_text_color" name="gps_email_header_text_color" value="<?php echo esc_attr(self::get('header_text_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Color of text in the header section.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Ticket Code Section', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_ticket_label"><?php _e('Ticket Code Label', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_ticket_label" name="gps_email_ticket_label" value="<?php echo esc_attr(self::get('ticket_label')); ?>" class="regular-text">
                                                <p class="description"><?php _e('Label text above the ticket code.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_ticket_bg_color"><?php _e('Background Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_ticket_bg_color" name="gps_email_ticket_bg_color" value="<?php echo esc_attr(self::get('ticket_bg_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Background color of the ticket code section.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_ticket_code_color"><?php _e('Ticket Code Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_ticket_code_color" name="gps_email_ticket_code_color" value="<?php echo esc_attr(self::get('ticket_code_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Color of the ticket code text.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_ticket_code_size"><?php _e('Ticket Code Size', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_ticket_code_size" name="gps_email_ticket_code_size" value="<?php echo esc_attr(self::get('ticket_code_size')); ?>" class="small-text">
                                                <p class="description"><?php _e('Font size of the ticket code (e.g., 24px).', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Tab -->
                    <div id="tab-content" class="gps-tab-content">
                        <div class="gps-settings-row">
                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Event Details Section', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_event_heading"><?php _e('Section Heading', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_event_heading" name="gps_email_event_heading" value="<?php echo esc_attr(self::get('event_heading')); ?>" class="regular-text">
                                                <p class="description"><?php _e('Heading for the event details section.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_event_heading_color"><?php _e('Heading Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_event_heading_color" name="gps_email_event_heading_color" value="<?php echo esc_attr(self::get('event_heading_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Color of the event details heading.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_event_details_bg_color"><?php _e('Background Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_event_details_bg_color" name="gps_email_event_details_bg_color" value="<?php echo esc_attr(self::get('event_details_bg_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Background color of the event details section.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_event_label_color"><?php _e('Label Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_event_label_color" name="gps_email_event_label_color" value="<?php echo esc_attr(self::get('event_label_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Color of labels in the event details (e.g., "Start:", "Location:").', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Button Settings', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_button_text"><?php _e('Primary Button Text', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_button_text" name="gps_email_button_text" value="<?php echo esc_attr(self::get('button_text')); ?>" class="regular-text">
                                                <p class="description"><?php _e('Text displayed on the primary action button.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_secondary_button_text"><?php _e('Secondary Button Text', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_secondary_button_text" name="gps_email_secondary_button_text" value="<?php echo esc_attr(self::get('secondary_button_text')); ?>" class="regular-text">
                                                <p class="description"><?php _e('Text for optional secondary button.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_button_bg_color"><?php _e('Button Background Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_button_bg_color" name="gps_email_button_bg_color" value="<?php echo esc_attr(self::get('button_bg_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Background color of the button.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_button_text_color"><?php _e('Button Text Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_button_text_color" name="gps_email_button_text_color" value="<?php echo esc_attr(self::get('button_text_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Color of text on the button.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_button_border_radius"><?php _e('Button Border Radius', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_button_border_radius" name="gps_email_button_border_radius" value="<?php echo esc_attr(self::get('button_border_radius')); ?>" class="small-text">
                                                <p class="description"><?php _e('Border radius for rounded button corners (e.g., 6px).', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('QR Code Section', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
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
                                                <p class="description"><?php _e('Heading text above the QR code.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_qr_instructions"><?php _e('QR Instructions', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_qr_instructions" name="gps_email_qr_instructions" value="<?php echo esc_attr(self::get('qr_instructions')); ?>" class="regular-text">
                                                <p class="description"><?php _e('Instruction text for using the QR code.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_qr_size"><?php _e('QR Code Size', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_qr_size" name="gps_email_qr_size" value="<?php echo esc_attr(self::get('qr_size')); ?>" class="small-text">
                                                <p class="description"><?php _e('Size in pixels (e.g., 200).', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_qr_bg_color"><?php _e('Background Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_qr_bg_color" name="gps_email_qr_bg_color" value="<?php echo esc_attr(self::get('qr_bg_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Background color of the QR code section.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('CE Credits Badge', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_ce_badge_bg_color"><?php _e('Badge Background Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_ce_badge_bg_color" name="gps_email_ce_badge_bg_color" value="<?php echo esc_attr(self::get('ce_badge_bg_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Background color of the CE credits badge.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_ce_badge_text_color"><?php _e('Badge Text Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_ce_badge_text_color" name="gps_email_ce_badge_text_color" value="<?php echo esc_attr(self::get('ce_badge_text_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Text color of the CE credits badge.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Additional Content', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_welcome_message"><?php _e('Welcome Message', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="gps_email_welcome_message" name="gps_email_welcome_message" rows="4" class="large-text"><?php echo esc_textarea(self::get('welcome_message')); ?></textarea>
                                                <p class="description"><?php _e('Optional welcome message to display in the email.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_additional_info"><?php _e('Additional Information', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="gps_email_additional_info" name="gps_email_additional_info" rows="4" class="large-text"><?php echo esc_textarea(self::get('additional_info')); ?></textarea>
                                                <p class="description"><?php _e('Any additional information to include in the email.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_support_email"><?php _e('Support Email', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="email" id="gps_email_support_email" name="gps_email_support_email" value="<?php echo esc_attr(self::get('support_email')); ?>" class="regular-text">
                                                <p class="description"><?php _e('Support email address for customer inquiries.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_support_phone"><?php _e('Support Phone', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_support_phone" name="gps_email_support_phone" value="<?php echo esc_attr(self::get('support_phone')); ?>" class="regular-text">
                                                <p class="description"><?php _e('Support phone number for customer inquiries.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Tab -->
                    <div id="tab-footer" class="gps-tab-content">
                        <div class="gps-settings-row">
                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Footer Section', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_footer_text"><?php _e('Footer Text', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="gps_email_footer_text" name="gps_email_footer_text" rows="4" class="large-text"><?php echo esc_textarea(self::get('footer_text')); ?></textarea>
                                                <p class="description"><?php _e('Main footer text or message.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_footer_address"><?php _e('Footer Address', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="gps_email_footer_address" name="gps_email_footer_address" rows="3" class="large-text"><?php echo esc_textarea(self::get('footer_address')); ?></textarea>
                                                <p class="description"><?php _e('Physical address or company information to display in footer.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_footer_social_links"><?php _e('Social Links', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="gps_email_footer_social_links" name="gps_email_footer_social_links" rows="3" class="large-text"><?php echo esc_textarea(self::get('footer_social_links')); ?></textarea>
                                                <p class="description"><?php _e('Social media links (one per line, format: Label|URL).', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_footer_bg_color"><?php _e('Footer Background Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_footer_bg_color" name="gps_email_footer_bg_color" value="<?php echo esc_attr(self::get('footer_bg_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Background color of the footer section.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_footer_text_color"><?php _e('Footer Text Color', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_footer_text_color" name="gps_email_footer_text_color" value="<?php echo esc_attr(self::get('footer_text_color')); ?>" class="gps-color-picker">
                                                <p class="description"><?php _e('Color of text in the footer section.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Tab -->
                    <div id="tab-layout" class="gps-tab-content">
                        <div class="gps-settings-row">
                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Layout & Spacing', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <table class="gps-form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_container_width"><?php _e('Container Width', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_container_width" name="gps_email_container_width" value="<?php echo esc_attr(self::get('container_width')); ?>" class="small-text">
                                                <p class="description"><?php _e('Maximum width of the email container (e.g., 600px). Standard is 600px for best email client compatibility.', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_border_radius"><?php _e('Border Radius', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_border_radius" name="gps_email_border_radius" value="<?php echo esc_attr(self::get('border_radius')); ?>" class="small-text">
                                                <p class="description"><?php _e('Border radius for rounded corners throughout the email (e.g., 8px).', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="gps_email_inner_padding"><?php _e('Inner Padding', 'gps-courses'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="gps_email_inner_padding" name="gps_email_inner_padding" value="<?php echo esc_attr(self::get('inner_padding')); ?>" class="small-text">
                                                <p class="description"><?php _e('Padding inside content sections (e.g., 30px).', 'gps-courses'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Test Email Tab -->
                    <div id="tab-test" class="gps-tab-content">
                        <div class="gps-test-email-section">
                            <h3><?php _e('Send Test Email', 'gps-courses'); ?></h3>
                            <p style="margin: 0 0 20px 0; color: #4338ca; font-size: 14px;">
                                <?php _e('Select a template and enter your email address to send a test email with current settings. Make sure to save your changes first!', 'gps-courses'); ?>
                            </p>
                            <div style="margin-bottom: 16px;">
                                <label for="gps_test_email_template" style="display: block; font-weight: 600; margin-bottom: 8px; color: #1e293b;">
                                    <?php _e('Template to Send:', 'gps-courses'); ?>
                                </label>
                                <select id="gps_test_email_template" style="min-width: 300px; padding: 8px 12px; border: 2px solid #a5b4fc; border-radius: 8px; font-size: 14px;">
                                    <option value="ticket"><?php _e('Purchase Ticket Email', 'gps-courses'); ?></option>
                                    <option value="seminar_welcome"><?php _e('Seminar Welcome Email', 'gps-courses'); ?></option>
                                    <option value="ce_credits"><?php _e('CE Credits Awarded Email', 'gps-courses'); ?></option>
                                    <option value="session_reminder"><?php _e('Session Reminder Email', 'gps-courses'); ?></option>
                                    <option value="missed_session"><?php _e('Missed Session Alert Email', 'gps-courses'); ?></option>
                                </select>
                            </div>
                            <div class="gps-test-email-form">
                                <input type="email" id="gps_test_email_address" placeholder="<?php esc_attr_e('your-email@example.com', 'gps-courses'); ?>" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
                                <button type="button" id="gps_send_test_email" class="button button-primary">
                                    <?php _e('Send Test Email', 'gps-courses'); ?>
                                </button>
                            </div>
                            <div id="gps_test_email_result" class="gps-test-email-result"></div>
                        </div>

                        <div class="gps-email-preview-section">
                            <div class="gps-postbox">
                                <div class="gps-postbox-header">
                                    <h2><?php _e('Email Preview', 'gps-courses'); ?></h2>
                                </div>
                                <div class="gps-postbox-content">
                                    <div style="margin-bottom: 20px;">
                                        <label for="gps_email_template_selector" style="display: block; font-weight: 600; margin-bottom: 8px; color: #1e293b;">
                                            <?php _e('Select Email Template:', 'gps-courses'); ?>
                                        </label>
                                        <select id="gps_email_template_selector" style="min-width: 300px; padding: 8px 12px; border: 2px solid #a5b4fc; border-radius: 8px; font-size: 14px;">
                                            <option value="ticket"><?php _e('Purchase Ticket Email', 'gps-courses'); ?></option>
                                            <option value="seminar_welcome"><?php _e('Seminar Welcome Email', 'gps-courses'); ?></option>
                                            <option value="ce_credits"><?php _e('CE Credits Awarded Email', 'gps-courses'); ?></option>
                                            <option value="session_reminder"><?php _e('Session Reminder Email', 'gps-courses'); ?></option>
                                            <option value="missed_session"><?php _e('Missed Session Alert Email', 'gps-courses'); ?></option>
                                        </select>
                                        <p class="description" style="margin-top: 10px;">
                                            <?php _e('Select a template to preview. The preview will update automatically with current branding settings.', 'gps-courses'); ?>
                                        </p>
                                    </div>
                                    <div class="gps-preview-container" id="gps-preview-container">
                                        <div style="text-align: center; padding: 40px; color: #64748b;">
                                            <div style="font-size: 48px; margin-bottom: 16px;">📧</div>
                                            <p><?php _e('Loading preview...', 'gps-courses'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sticky Submit Button -->
                    <div class="submit-button-wrapper">
                        <?php submit_button(__('Save All Settings', 'gps-courses'), 'primary', 'submit', false); ?>
                    </div>

                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render email preview
     */
    private static function render_email_preview() {
        $logo = self::get('logo');
        $header_bg = self::get('header_bg_color');
        $header_text_color = self::get('header_text_color');
        $header_text = self::get('header_text');
        $header_subtitle = self::get('header_subtitle');
        $ticket_bg = self::get('ticket_bg_color');
        $ticket_code_color = self::get('ticket_code_color');
        $ticket_label = self::get('ticket_label');
        $event_heading = self::get('event_heading');
        $event_heading_color = self::get('event_heading_color');
        $event_label_color = self::get('event_label_color');
        $qr_bg = self::get('qr_bg_color');
        $qr_heading = self::get('qr_heading');
        $qr_size = self::get('qr_size');
        $show_qr = self::get('show_qr_code');
        $ce_badge_bg = self::get('ce_badge_bg_color');
        $ce_badge_text = self::get('ce_badge_text_color');
        $footer_bg = self::get('footer_bg_color');
        $footer_text_color = self::get('footer_text_color');
        $footer_text = self::get('footer_text');
        $button_bg = self::get('button_bg_color');
        $button_text_color = self::get('button_text_color');
        $button_text = self::get('button_text');
        $button_border_radius = self::get('button_border_radius');
        $container_width = self::get('container_width');
        $border_radius = self::get('border_radius');
        ?>
        <div style="font-family: Arial, sans-serif; max-width: <?php echo esc_attr($container_width); ?>; margin: 0 auto; background: white; border-radius: <?php echo esc_attr($border_radius); ?>; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

            <?php if ($logo): ?>
            <div style="text-align: center; padding: 30px; background: white;">
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 200px; height: auto;">
            </div>
            <?php endif; ?>

            <div style="background: <?php echo esc_attr($header_bg); ?>; color: <?php echo esc_attr($header_text_color); ?>; padding: 40px 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px; font-weight: bold; color: <?php echo esc_attr($header_text_color); ?>;">
                    <?php echo esc_html($header_text); ?>
                </h1>
                <?php if ($header_subtitle): ?>
                <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">
                    <?php echo esc_html($header_subtitle); ?>
                </p>
                <?php endif; ?>
                <p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">
                    Sample Event Name
                </p>
            </div>

            <div style="background: <?php echo esc_attr($ticket_bg); ?>; padding: 25px; text-align: center; border-bottom: 3px dashed #dee2e6;">
                <p style="margin: 0 0 10px 0; font-size: 12px; color: <?php echo esc_attr($event_label_color); ?>; text-transform: uppercase; letter-spacing: 1px;">
                    <?php echo esc_html($ticket_label); ?>
                </p>
                <div style="font-size: 24px; font-weight: bold; color: <?php echo esc_attr($ticket_code_color); ?>; font-family: 'Courier New', monospace;">
                    GPST-1234-5678-ABCD
                </div>
            </div>

            <div style="padding: 30px;">
                <h2 style="margin: 0 0 20px 0; font-size: 20px; color: <?php echo esc_attr($event_heading_color); ?>; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0;">
                    <?php echo esc_html($event_heading); ?>
                </h2>

                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr>
                        <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: <?php echo esc_attr($event_label_color); ?>;">
                            <strong>Start:</strong>
                        </td>
                        <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                            December 15, 2025 at 9:00 AM
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: <?php echo esc_attr($event_label_color); ?>;">
                            <strong>Location:</strong>
                        </td>
                        <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                            GPS Training Center
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; color: <?php echo esc_attr($event_label_color); ?>;">
                            <strong>CE Credits:</strong>
                        </td>
                        <td style="padding: 12px 0; text-align: right;">
                            <span style="display: inline-block; padding: 5px 15px; background: <?php echo esc_attr($ce_badge_bg); ?>; color: <?php echo esc_attr($ce_badge_text); ?>; border-radius: 20px; font-weight: bold; font-size: 14px;">
                                10 Credits
                            </span>
                        </td>
                    </tr>
                </table>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="display: inline-block; padding: 14px 40px; background: <?php echo esc_attr($button_bg); ?>; color: <?php echo esc_attr($button_text_color); ?>; text-decoration: none; border-radius: <?php echo esc_attr($button_border_radius); ?>; font-weight: 600; font-size: 16px;">
                        <?php echo esc_html($button_text); ?>
                    </a>
                </div>
            </div>

            <?php if ($show_qr): ?>
            <div style="background: <?php echo esc_attr($qr_bg); ?>; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: #1e293b;">
                    <?php echo esc_html($qr_heading); ?>
                </h3>
                <p style="margin: 0 0 15px 0; color: <?php echo esc_attr($event_label_color); ?>;">Show this QR code at check-in</p>
                <div style="background: white; display: inline-block; padding: 15px; border-radius: <?php echo esc_attr($border_radius); ?>;">
                    <div style="width: <?php echo esc_attr($qr_size); ?>px; height: <?php echo esc_attr($qr_size); ?>px; background: #f0f0f0; display: inline-flex; align-items: center; justify-content: center; color: #999;">
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
                    &copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX preview for ticket email
     */
    public static function handle_preview_ticket_email() {
        check_ajax_referer('gps_test_email', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        ob_start();
        self::render_email_preview();
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Handle AJAX preview for seminar welcome email
     */
    public static function handle_preview_seminar_welcome() {
        check_ajax_referer('gps_test_email', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        ob_start();
        self::render_seminar_welcome_preview();
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Handle AJAX preview for CE credits email
     */
    public static function handle_preview_ce_credits() {
        check_ajax_referer('gps_test_email', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        ob_start();
        self::render_ce_credits_preview();
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Handle AJAX preview for session reminder email
     */
    public static function handle_preview_session_reminder() {
        check_ajax_referer('gps_test_email', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        ob_start();
        self::render_session_reminder_preview();
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Handle AJAX preview for missed session email
     */
    public static function handle_preview_missed_session() {
        check_ajax_referer('gps_test_email', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        ob_start();
        self::render_missed_session_preview();
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Render seminar welcome email preview
     */
    private static function render_seminar_welcome_preview() {
        $logo = self::get('logo');
        $header_bg = self::get('header_bg_color');
        $header_text_color = self::get('header_text_color');
        $body_bg = self::get('body_bg_color');
        $body_text_color = self::get('body_text_color');
        $footer_bg = self::get('footer_bg_color');
        $footer_text_color = self::get('footer_text_color');
        $footer_text = self::get('footer_text');
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

            <?php if ($logo): ?>
            <div style="text-align: center; padding: 30px; background: <?php echo esc_attr($header_bg); ?>;">
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 200px; height: auto;">
            </div>
            <?php endif; ?>

            <div style="padding: 40px 40px 20px;">
                <h1 style="margin: 0 0 24px 0; font-size: 28px; color: <?php echo esc_attr($body_text_color); ?>; text-align: center;">
                    🎓 Welcome to GPS Monthly Seminars!
                </h1>

                <p style="margin: 0 0 20px 0; font-size: 16px; color: <?php echo esc_attr($body_text_color); ?>; line-height: 1.6;">
                    Dear John Doe,
                </p>

                <p style="margin: 0 0 20px 0; font-size: 16px; color: <?php echo esc_attr($body_text_color); ?>; line-height: 1.6;">
                    Thank you for registering for <strong>GPS Monthly Seminars</strong>! We're excited to have you join us for professional development sessions.
                </p>

                <div style="background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%); border-left: 4px solid <?php echo esc_attr($header_bg); ?>; padding: 20px; margin: 24px 0; border-radius: 8px;">
                    <h3 style="margin: 0 0 16px 0; font-size: 18px; color: <?php echo esc_attr($header_bg); ?>;">
                        📋 Registration Details
                    </h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Participant:</strong></td>
                            <td style="padding: 8px 0; text-align: right; font-size: 14px;">John Doe</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Email:</strong></td>
                            <td style="padding: 8px 0; text-align: right; font-size: 14px;">john@example.com</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; color: #64748b; font-size: 14px;"><strong>Status:</strong></td>
                            <td style="padding: 8px 0; text-align: right; font-size: 14px;"><span style="background: #22c55e; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">✓ Active</span></td>
                        </tr>
                    </table>
                </div>

                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; padding: 20px; margin: 24px 0; border-radius: 8px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #92400e;">
                        ⚠️ Important Information
                    </h3>
                    <ul style="margin: 0; padding-left: 20px; color: #92400e; font-size: 14px; line-height: 1.6;">
                        <li>Sessions are held on the <strong>third Thursday of each month</strong></li>
                        <li>Attendance at <strong>80% of sessions</strong> is required for CE credits</li>
                        <li>Each session awards <strong>1 CE credit</strong></li>
                    </ul>
                </div>

                <h3 style="margin: 32px 0 16px 0; font-size: 20px; color: <?php echo esc_attr($body_text_color); ?>; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0;">
                    📅 Upcoming Sessions
                </h3>

                <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="background: <?php echo esc_attr($header_bg); ?>; color: white; width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">1</div>
                        <div style="flex-grow: 1;">
                            <div style="font-weight: 600; font-size: 16px; color: <?php echo esc_attr($body_text_color); ?>; margin-bottom: 4px;">Professional Ethics in Practice</div>
                            <div style="color: #64748b; font-size: 14px;">📍 Main Training Room | 🕒 January 16, 2025 at 2:00 PM</div>
                        </div>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="background: <?php echo esc_attr($header_bg); ?>; color: white; width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">2</div>
                        <div style="flex-grow: 1;">
                            <div style="font-weight: 600; font-size: 16px; color: <?php echo esc_attr($body_text_color); ?>; margin-bottom: 4px;">Clinical Documentation Best Practices</div>
                            <div style="color: #64748b; font-size: 14px;">📍 Main Training Room | 🕒 February 20, 2025 at 2:00 PM</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="background: <?php echo esc_attr($footer_bg); ?>; color: <?php echo esc_attr($footer_text_color); ?>; padding: 30px 40px; text-align: center;">
                <p style="margin: 0; font-size: 14px;">
                    <?php echo nl2br(esc_html($footer_text)); ?>
                </p>
                <p style="margin: 15px 0 0; font-size: 12px; opacity: 0.7;">
                    &copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render CE credits email preview
     */
    private static function render_ce_credits_preview() {
        $logo = self::get('logo');
        $header_bg = self::get('header_bg_color');
        $header_text_color = self::get('header_text_color');
        $body_text_color = self::get('body_text_color');
        $footer_bg = self::get('footer_bg_color');
        $footer_text_color = self::get('footer_text_color');
        $footer_text = self::get('footer_text');
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

            <?php if ($logo): ?>
            <div style="text-align: center; padding: 30px; background: <?php echo esc_attr($header_bg); ?>;">
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 200px; height: auto;">
            </div>
            <?php endif; ?>

            <div style="padding: 40px 40px 20px;">
                <div style="text-align: center; margin-bottom: 32px;">
                    <div style="display: inline-block; width: 80px; height: 80px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                        <span style="font-size: 40px;">🏆</span>
                    </div>
                    <h1 style="margin: 0; font-size: 28px; color: <?php echo esc_attr($body_text_color); ?>;">
                        CE Credits Awarded!
                    </h1>
                </div>

                <p style="margin: 0 0 24px 0; font-size: 16px; color: <?php echo esc_attr($body_text_color); ?>; line-height: 1.6; text-align: center;">
                    Congratulations! You've earned CE credits for completing a GPS Monthly Seminar session.
                </p>

                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 3px solid #f59e0b; padding: 30px; margin: 24px 0; border-radius: 12px; text-align: center;">
                    <div style="font-size: 48px; font-weight: bold; color: #92400e; margin-bottom: 8px;">1.0</div>
                    <div style="font-size: 18px; color: #92400e; font-weight: 600;">CE Credits Earned</div>
                </div>

                <div style="background: #f8fafc; border-left: 4px solid <?php echo esc_attr($header_bg); ?>; padding: 20px; margin: 24px 0; border-radius: 8px;">
                    <h3 style="margin: 0 0 16px 0; font-size: 18px; color: <?php echo esc_attr($header_bg); ?>;">
                        📊 Your CE Credits Dashboard
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <div style="color: #64748b; font-size: 12px; margin-bottom: 4px;">Total Earned</div>
                            <div style="font-size: 24px; font-weight: bold; color: #22c55e;">8.0</div>
                        </div>
                        <div>
                            <div style="color: #64748b; font-size: 12px; margin-bottom: 4px;">Sessions Attended</div>
                            <div style="font-size: 24px; font-weight: bold; color: <?php echo esc_attr($header_bg); ?>;">8/10</div>
                        </div>
                    </div>
                </div>

                <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 2px solid #22c55e; padding: 20px; margin: 24px 0; border-radius: 8px; text-align: center;">
                    <div style="font-size: 18px; font-weight: 600; color: #065f46; margin-bottom: 8px;">
                        🎯 Keep Up the Great Work!
                    </div>
                    <div style="font-size: 14px; color: #065f46;">
                        You're 80% towards completing all sessions this year.
                    </div>
                </div>
            </div>

            <div style="background: <?php echo esc_attr($footer_bg); ?>; color: <?php echo esc_attr($footer_text_color); ?>; padding: 30px 40px; text-align: center;">
                <p style="margin: 0; font-size: 14px;">
                    <?php echo nl2br(esc_html($footer_text)); ?>
                </p>
                <p style="margin: 15px 0 0; font-size: 12px; opacity: 0.7;">
                    &copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render session reminder email preview
     */
    private static function render_session_reminder_preview() {
        $logo = self::get('logo');
        $header_bg = self::get('header_bg_color');
        $header_text_color = self::get('header_text_color');
        $body_text_color = self::get('body_text_color');
        $footer_bg = self::get('footer_bg_color');
        $footer_text_color = self::get('footer_text_color');
        $footer_text = self::get('footer_text');
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

            <?php if ($logo): ?>
            <div style="text-align: center; padding: 30px; background: <?php echo esc_attr($header_bg); ?>;">
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 200px; height: auto;">
            </div>
            <?php endif; ?>

            <div style="padding: 40px 40px 20px;">
                <h1 style="margin: 0 0 24px 0; font-size: 28px; color: <?php echo esc_attr($body_text_color); ?>; text-align: center;">
                    🔔 Upcoming Seminar Session
                </h1>

                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; padding: 20px; margin: 24px 0; border-radius: 8px; text-align: center;">
                    <div style="font-size: 18px; font-weight: 600; color: #92400e; margin-bottom: 8px;">
                        ⏰ Tomorrow at 2:00 PM
                    </div>
                    <div style="font-size: 14px; color: #92400e;">
                        Don't forget to attend!
                    </div>
                </div>

                <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                        <div style="background: <?php echo esc_attr($header_bg); ?>; color: white; width: 48px; height: 48px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 20px; flex-shrink: 0;">5</div>
                        <div>
                            <div style="font-weight: 600; font-size: 18px; color: <?php echo esc_attr($body_text_color); ?>; margin-bottom: 4px;">Clinical Documentation Best Practices</div>
                            <div style="color: #64748b; font-size: 14px;">Session 5 of 12</div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
                        <div style="margin-bottom: 12px;">
                            <div style="color: #64748b; font-size: 12px; margin-bottom: 4px;">📍 Location</div>
                            <div style="font-size: 14px; color: <?php echo esc_attr($body_text_color); ?>; font-weight: 500;">Main Training Room</div>
                        </div>
                        <div style="margin-bottom: 12px;">
                            <div style="color: #64748b; font-size: 12px; margin-bottom: 4px;">📅 Date</div>
                            <div style="font-size: 14px; color: <?php echo esc_attr($body_text_color); ?>; font-weight: 500;">Thursday, January 16, 2025</div>
                        </div>
                        <div>
                            <div style="color: #64748b; font-size: 12px; margin-bottom: 4px;">🕒 Time</div>
                            <div style="font-size: 14px; color: <?php echo esc_attr($body_text_color); ?>; font-weight: 500;">2:00 PM - 3:00 PM</div>
                        </div>
                    </div>
                </div>

                <div style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-left: 4px solid <?php echo esc_attr($header_bg); ?>; padding: 16px; margin: 24px 0; border-radius: 8px;">
                    <div style="font-size: 14px; color: #1e40af; line-height: 1.6;">
                        <strong>💡 Reminder:</strong> QR code attendance will be required for CE credit.
                    </div>
                </div>
            </div>

            <div style="background: <?php echo esc_attr($footer_bg); ?>; color: <?php echo esc_attr($footer_text_color); ?>; padding: 30px 40px; text-align: center;">
                <p style="margin: 0; font-size: 14px;">
                    <?php echo nl2br(esc_html($footer_text)); ?>
                </p>
                <p style="margin: 15px 0 0; font-size: 12px; opacity: 0.7;">
                    &copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render missed session email preview
     */
    private static function render_missed_session_preview() {
        $logo = self::get('logo');
        $header_bg = self::get('header_bg_color');
        $header_text_color = self::get('header_text_color');
        $body_text_color = self::get('body_text_color');
        $footer_bg = self::get('footer_bg_color');
        $footer_text_color = self::get('footer_text_color');
        $footer_text = self::get('footer_text');
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

            <?php if ($logo): ?>
            <div style="text-align: center; padding: 30px; background: <?php echo esc_attr($header_bg); ?>;">
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 200px; height: auto;">
            </div>
            <?php endif; ?>

            <div style="padding: 40px 40px 20px;">
                <h1 style="margin: 0 0 24px 0; font-size: 28px; color: <?php echo esc_attr($body_text_color); ?>; text-align: center;">
                    😔 We Missed You!
                </h1>

                <p style="margin: 0 0 24px 0; font-size: 16px; color: <?php echo esc_attr($body_text_color); ?>; line-height: 1.6;">
                    Dear John Doe,
                </p>

                <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border: 2px solid #ef4444; padding: 20px; margin: 24px 0; border-radius: 8px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #991b1b;">
                        📋 Missed Session Alert
                    </h3>
                    <p style="margin: 0; font-size: 14px; color: #991b1b; line-height: 1.6;">
                        We noticed you were not able to attend the <strong>Clinical Documentation Best Practices</strong> session on <strong>January 16, 2025</strong>.
                    </p>
                </div>

                <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-left: 4px solid #22c55e; padding: 20px; margin: 24px 0; border-radius: 8px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #065f46;">
                        ✅ Good News - Makeup Session Available!
                    </h3>
                    <p style="margin: 0; font-size: 14px; color: #065f46; line-height: 1.6;">
                        A makeup session has been scheduled for <strong>January 23, 2025 at 4:00 PM</strong> in the Secondary Training Room. You can still earn your CE credit!
                    </p>
                </div>

                <div style="background: #f8fafc; border: 2px solid #e2e8f0; padding: 20px; margin: 24px 0; border-radius: 8px;">
                    <div style="color: #64748b; font-size: 12px; margin-bottom: 4px;">⚠️ Important Reminder</div>
                    <p style="margin: 8px 0 0 0; font-size: 14px; color: <?php echo esc_attr($body_text_color); ?>; line-height: 1.6;">
                        To receive CE credits at the end of the year, you must attend at least <strong>80% of all sessions</strong> (including makeup sessions).
                    </p>
                </div>
            </div>

            <div style="background: <?php echo esc_attr($footer_bg); ?>; color: <?php echo esc_attr($footer_text_color); ?>; padding: 30px 40px; text-align: center;">
                <p style="margin: 0; font-size: 14px;">
                    <?php echo nl2br(esc_html($footer_text)); ?>
                </p>
                <p style="margin: 15px 0 0; font-size: 12px; opacity: 0.7;">
                    &copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
