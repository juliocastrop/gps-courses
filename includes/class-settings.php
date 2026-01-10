<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Settings
 * Handles plugin settings and configuration
 */
class Settings {

    public static function init() {
        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // Register settings
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Settings', 'gps-courses'),
            __('Settings', 'gps-courses'),
            'manage_options',
            'gps-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // General Settings
        register_setting('gps_general_settings', 'gps_google_maps_api_key');
        register_setting('gps_general_settings', 'gps_ticket_prefix');
        register_setting('gps_general_settings', 'gps_company_name');
        register_setting('gps_general_settings', 'gps_company_email');
        register_setting('gps_general_settings', 'gps_company_phone');
        register_setting('gps_general_settings', 'gps_company_address');

        // Email Settings
        register_setting('gps_email_settings', 'gps_email_from_name');
        register_setting('gps_email_settings', 'gps_email_from_address');
        register_setting('gps_email_settings', 'gps_email_header_image');
        register_setting('gps_email_settings', 'gps_email_footer_text');
        register_setting('gps_email_settings', 'gps_email_primary_color');

        // Ticket Settings
        register_setting('gps_ticket_settings', 'gps_ticket_logo');
        register_setting('gps_ticket_settings', 'gps_ticket_header_text');
        register_setting('gps_ticket_settings', 'gps_ticket_footer_text');
        register_setting('gps_ticket_settings', 'gps_qr_code_size');
        register_setting('gps_ticket_settings', 'gps_ticket_include_qr');

        // CE Credits Settings
        register_setting('gps_credits_settings', 'gps_credits_enabled');
        register_setting('gps_credits_settings', 'gps_credits_require_attendance');
        register_setting('gps_credits_settings', 'gps_credits_certificate_template');

        // WooCommerce Settings
        register_setting('gps_woo_settings', 'gps_woo_enable_sync');
        register_setting('gps_woo_settings', 'gps_woo_product_category');
        register_setting('gps_woo_settings', 'gps_stripe_publishable_key');
        register_setting('gps_woo_settings', 'gps_stripe_secret_key');
    }

    /**
     * Enqueue scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-settings') {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_style(
            'gps-settings',
            GPSC_URL . 'assets/css/admin-settings.css',
            [],
            GPSC_VERSION
        );

        wp_enqueue_script(
            'gps-settings',
            GPSC_URL . 'assets/js/admin-settings.js',
            ['jquery', 'wp-color-picker'],
            GPSC_VERSION,
            true
        );

        wp_localize_script('gps-settings', 'gpsSettings', [
            'media_title' => __('Select Image', 'gps-courses'),
            'media_button' => __('Use Image', 'gps-courses'),
        ]);
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap gps-settings-page">
            <h1><?php _e('GPS Courses Settings', 'gps-courses'); ?></h1>

            <?php settings_errors(); ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=gps-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php _e('General', 'gps-courses'); ?>
                </a>
                <?php /* Email tab hidden - use GPS Courses → Email Settings instead */ ?>
                <a href="?page=gps-settings&tab=tickets" class="nav-tab <?php echo $active_tab === 'tickets' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-tickets-alt"></span>
                    <?php _e('Tickets', 'gps-courses'); ?>
                </a>
                <a href="?page=gps-settings&tab=credits" class="nav-tab <?php echo $active_tab === 'credits' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-awards"></span>
                    <?php _e('CE Credits', 'gps-courses'); ?>
                </a>
                <a href="?page=gps-settings&tab=woocommerce" class="nav-tab <?php echo $active_tab === 'woocommerce' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-cart"></span>
                    <?php _e('WooCommerce', 'gps-courses'); ?>
                </a>
            </nav>

            <div class="gps-settings-content">
                <?php
                switch ($active_tab) {
                    case 'general':
                        self::render_general_tab();
                        break;
                    case 'email':
                        self::render_email_tab();
                        break;
                    case 'tickets':
                        self::render_tickets_tab();
                        break;
                    case 'credits':
                        self::render_credits_tab();
                        break;
                    case 'woocommerce':
                        self::render_woocommerce_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render General tab
     */
    private static function render_general_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('gps_general_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gps_google_maps_api_key"><?php _e('Google Maps API Key', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_google_maps_api_key" name="gps_google_maps_api_key" value="<?php echo esc_attr(get_option('gps_google_maps_api_key')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Required for Google Maps integration in widgets and venue display.', 'gps-courses'); ?>
                            <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank"><?php _e('Get API Key', 'gps-courses'); ?></a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_ticket_prefix"><?php _e('Ticket Code Prefix', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_ticket_prefix" name="gps_ticket_prefix" value="<?php echo esc_attr(get_option('gps_ticket_prefix', 'GPST')); ?>" class="small-text">
                        <p class="description">
                            <?php _e('Prefix for ticket codes (e.g., GPST-12345-67890)', 'gps-courses'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_company_name"><?php _e('Company Name', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_company_name" name="gps_company_name" value="<?php echo esc_attr(get_option('gps_company_name', 'GPS Dental Training')); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_company_email"><?php _e('Company Email', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="gps_company_email" name="gps_company_email" value="<?php echo esc_attr(get_option('gps_company_email', get_option('admin_email'))); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_company_phone"><?php _e('Company Phone', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_company_phone" name="gps_company_phone" value="<?php echo esc_attr(get_option('gps_company_phone')); ?>" class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_company_address"><?php _e('Company Address', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <textarea id="gps_company_address" name="gps_company_address" rows="3" class="large-text"><?php echo esc_textarea(get_option('gps_company_address')); ?></textarea>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render Email tab
     */
    private static function render_email_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('gps_email_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gps_email_from_name"><?php _e('From Name', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_email_from_name" name="gps_email_from_name" value="<?php echo esc_attr(get_option('gps_email_from_name', get_option('gps_company_name', 'GPS Dental Training'))); ?>" class="regular-text">
                        <p class="description"><?php _e('The name emails will appear from.', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_email_from_address"><?php _e('From Email', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="gps_email_from_address" name="gps_email_from_address" value="<?php echo esc_attr(get_option('gps_email_from_address', get_option('admin_email'))); ?>" class="regular-text">
                        <p class="description"><?php _e('The email address emails will appear from.', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_email_header_image"><?php _e('Header Image', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_email_header_image" name="gps_email_header_image" value="<?php echo esc_url(get_option('gps_email_header_image')); ?>" class="regular-text">
                        <button type="button" class="button gps-upload-button" data-target="gps_email_header_image"><?php _e('Select Image', 'gps-courses'); ?></button>
                        <p class="description"><?php _e('Logo displayed at the top of email templates.', 'gps-courses'); ?></p>
                        <?php if (get_option('gps_email_header_image')): ?>
                            <div class="gps-image-preview">
                                <img src="<?php echo esc_url(get_option('gps_email_header_image')); ?>" style="max-width: 300px; margin-top: 10px;">
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_email_primary_color"><?php _e('Primary Color', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_email_primary_color" name="gps_email_primary_color" value="<?php echo esc_attr(get_option('gps_email_primary_color', '#2271b1')); ?>" class="gps-color-picker">
                        <p class="description"><?php _e('Primary color for email templates (buttons, headers, etc).', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_email_footer_text"><?php _e('Footer Text', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <textarea id="gps_email_footer_text" name="gps_email_footer_text" rows="3" class="large-text"><?php echo esc_textarea(get_option('gps_email_footer_text')); ?></textarea>
                        <p class="description"><?php _e('Text displayed at the bottom of email templates.', 'gps-courses'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render Tickets tab
     */
    private static function render_tickets_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('gps_ticket_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gps_ticket_logo"><?php _e('Ticket Logo', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_ticket_logo" name="gps_ticket_logo" value="<?php echo esc_url(get_option('gps_ticket_logo')); ?>" class="regular-text">
                        <button type="button" class="button gps-upload-button" data-target="gps_ticket_logo"><?php _e('Select Image', 'gps-courses'); ?></button>
                        <p class="description"><?php _e('Logo displayed on PDF tickets.', 'gps-courses'); ?></p>
                        <?php if (get_option('gps_ticket_logo')): ?>
                            <div class="gps-image-preview">
                                <img src="<?php echo esc_url(get_option('gps_ticket_logo')); ?>" style="max-width: 200px; margin-top: 10px;">
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_ticket_header_text"><?php _e('Header Text', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_ticket_header_text" name="gps_ticket_header_text" value="<?php echo esc_attr(get_option('gps_ticket_header_text', 'EVENT TICKET')); ?>" class="regular-text">
                        <p class="description"><?php _e('Text displayed at the top of tickets.', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_ticket_footer_text"><?php _e('Footer Text', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <textarea id="gps_ticket_footer_text" name="gps_ticket_footer_text" rows="3" class="large-text"><?php echo esc_textarea(get_option('gps_ticket_footer_text')); ?></textarea>
                        <p class="description"><?php _e('Text displayed at the bottom of tickets.', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_qr_code_size"><?php _e('QR Code Size', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <select id="gps_qr_code_size" name="gps_qr_code_size">
                            <option value="small" <?php selected(get_option('gps_qr_code_size', 'medium'), 'small'); ?>><?php _e('Small (200x200)', 'gps-courses'); ?></option>
                            <option value="medium" <?php selected(get_option('gps_qr_code_size', 'medium'), 'medium'); ?>><?php _e('Medium (300x300)', 'gps-courses'); ?></option>
                            <option value="large" <?php selected(get_option('gps_qr_code_size', 'medium'), 'large'); ?>><?php _e('Large (400x400)', 'gps-courses'); ?></option>
                        </select>
                        <p class="description"><?php _e('Size of QR codes in pixels.', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_ticket_include_qr"><?php _e('Include QR Code', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="gps_ticket_include_qr" name="gps_ticket_include_qr" value="1" <?php checked(get_option('gps_ticket_include_qr', '1'), '1'); ?>>
                            <?php _e('Display QR code on tickets', 'gps-courses'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render CE Credits tab
     */
    private static function render_credits_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('gps_credits_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gps_credits_enabled"><?php _e('Enable CE Credits', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="gps_credits_enabled" name="gps_credits_enabled" value="1" <?php checked(get_option('gps_credits_enabled', '1'), '1'); ?>>
                            <?php _e('Enable CE credits system', 'gps-courses'); ?>
                        </label>
                        <p class="description"><?php _e('Allow events to award CE credits to attendees.', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_credits_require_attendance"><?php _e('Require Attendance', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="gps_credits_require_attendance" name="gps_credits_require_attendance" value="1" <?php checked(get_option('gps_credits_require_attendance', '1'), '1'); ?>>
                            <?php _e('Only award credits when attendee checks in', 'gps-courses'); ?>
                        </label>
                        <p class="description"><?php _e('If enabled, CE credits are only awarded when QR code is scanned at event.', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_credits_certificate_template"><?php _e('Certificate Template', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <select id="gps_credits_certificate_template" name="gps_credits_certificate_template">
                            <option value="default" <?php selected(get_option('gps_credits_certificate_template', 'default'), 'default'); ?>><?php _e('Default', 'gps-courses'); ?></option>
                            <option value="modern" <?php selected(get_option('gps_credits_certificate_template', 'default'), 'modern'); ?>><?php _e('Modern', 'gps-courses'); ?></option>
                            <option value="classic" <?php selected(get_option('gps_credits_certificate_template', 'default'), 'classic'); ?>><?php _e('Classic', 'gps-courses'); ?></option>
                        </select>
                        <p class="description"><?php _e('Template for CE credit certificates.', 'gps-courses'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render WooCommerce tab
     */
    private static function render_woocommerce_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('gps_woo_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gps_woo_enable_sync"><?php _e('Enable WooCommerce Sync', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="gps_woo_enable_sync" name="gps_woo_enable_sync" value="1" <?php checked(get_option('gps_woo_enable_sync', '1'), '1'); ?>>
                            <?php _e('Sync event tickets with WooCommerce products', 'gps-courses'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_woo_product_category"><?php _e('Product Category', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <?php
                        $categories = get_terms([
                            'taxonomy' => 'product_cat',
                            'hide_empty' => false,
                        ]);
                        ?>
                        <select id="gps_woo_product_category" name="gps_woo_product_category">
                            <option value=""><?php _e('None', 'gps-courses'); ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category->term_id; ?>" <?php selected(get_option('gps_woo_product_category'), $category->term_id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Default category for event products.', 'gps-courses'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php _e('Stripe Integration', 'gps-courses'); ?></h3>
                    </th>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_stripe_publishable_key"><?php _e('Stripe Publishable Key', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="gps_stripe_publishable_key" name="gps_stripe_publishable_key" value="<?php echo esc_attr(get_option('gps_stripe_publishable_key')); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Your Stripe publishable API key.', 'gps-courses'); ?>
                            <a href="https://dashboard.stripe.com/apikeys" target="_blank"><?php _e('Get Keys', 'gps-courses'); ?></a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gps_stripe_secret_key"><?php _e('Stripe Secret Key', 'gps-courses'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="gps_stripe_secret_key" name="gps_stripe_secret_key" value="<?php echo esc_attr(get_option('gps_stripe_secret_key')); ?>" class="regular-text">
                        <p class="description"><?php _e('Your Stripe secret API key.', 'gps-courses'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }
}
