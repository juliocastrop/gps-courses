<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Certificate Settings Management
 * Handles certificate customization options
 */
class Certificate_Settings {

    /**
     * Initialize the class
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'gps-dashboard',
            __('Certificate Settings', 'gps-courses'),
            __('Certificate Settings', 'gps-courses'),
            'manage_options',
            'gps-certificate-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // Register regular course certificate settings
        register_setting('gps_certificate_settings', 'gps_certificate_settings', [
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
        ]);

        // Register seminar certificate settings
        register_setting('gps_seminar_certificate_settings', 'gps_seminar_certificate_settings', [
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
        ]);

        // Design Section
        add_settings_section(
            'gps_cert_design',
            __('Certificate Design', 'gps-courses'),
            [__CLASS__, 'design_section_callback'],
            'gps-certificate-settings'
        );

        // Logo
        add_settings_field(
            'logo',
            __('Certificate Logo', 'gps-courses'),
            [__CLASS__, 'logo_field_callback'],
            'gps-certificate-settings',
            'gps_cert_design'
        );

        // Header Section
        add_settings_section(
            'gps_cert_header',
            __('Header Settings', 'gps-courses'),
            [__CLASS__, 'header_section_callback'],
            'gps-certificate-settings'
        );

        // Header Title
        add_settings_field(
            'header_title',
            __('Header Title', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_header',
            ['id' => 'header_title', 'default' => 'GPS DENTAL']
        );

        // Header Subtitle
        add_settings_field(
            'header_subtitle',
            __('Header Subtitle', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_header',
            ['id' => 'header_subtitle', 'default' => 'TRAINING']
        );

        // Header Background Color
        add_settings_field(
            'header_bg_color',
            __('Header Background Color', 'gps-courses'),
            [__CLASS__, 'color_field_callback'],
            'gps-certificate-settings',
            'gps_cert_header',
            ['id' => 'header_bg_color', 'default' => '#193463']
        );

        // Header Text Color
        add_settings_field(
            'header_text_color',
            __('Header Text Color', 'gps-courses'),
            [__CLASS__, 'color_field_callback'],
            'gps-certificate-settings',
            'gps_cert_header',
            ['id' => 'header_text_color', 'default' => '#FFFFFF']
        );

        // Certificate Title Section
        add_settings_section(
            'gps_cert_title',
            __('Certificate Title Settings', 'gps-courses'),
            [__CLASS__, 'title_section_callback'],
            'gps-certificate-settings'
        );

        // Main Title
        add_settings_field(
            'main_title',
            __('Main Title', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_title',
            ['id' => 'main_title', 'default' => 'CERTIFICATE']
        );

        // Subtitle
        add_settings_field(
            'main_subtitle',
            __('Subtitle', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_title',
            ['id' => 'main_subtitle', 'default' => 'OF COMPLETION']
        );

        // Description Text
        add_settings_field(
            'description_text',
            __('Description Text', 'gps-courses'),
            [__CLASS__, 'textarea_field_callback'],
            'gps-certificate-settings',
            'gps_cert_title',
            ['id' => 'description_text', 'default' => 'This letter certified the person below participated in the following course by GPS Dental Training.']
        );

        // Content Section
        add_settings_section(
            'gps_cert_content',
            __('Content Settings', 'gps-courses'),
            [__CLASS__, 'content_section_callback'],
            'gps-certificate-settings'
        );

        // Program Provider Text
        add_settings_field(
            'program_provider',
            __('Program Provider Text', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_content',
            ['id' => 'program_provider', 'default' => 'Program Provider: GPS Dental Training']
        );

        // Course Title Label
        add_settings_field(
            'course_title_label',
            __('Course Title Label', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_content',
            ['id' => 'course_title_label', 'default' => 'Course Title']
        );

        // Certificate Code Label
        add_settings_field(
            'code_label',
            __('Certificate Code Label', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_content',
            ['id' => 'code_label', 'default' => 'CODE']
        );

        // Code Background Color
        add_settings_field(
            'code_bg_color',
            __('Code Background Color', 'gps-courses'),
            [__CLASS__, 'color_field_callback'],
            'gps-certificate-settings',
            'gps_cert_content',
            ['id' => 'code_bg_color', 'default' => '#BC9D67']
        );

        // Colors Section
        add_settings_section(
            'gps_cert_colors',
            __('Color Settings', 'gps-courses'),
            [__CLASS__, 'colors_section_callback'],
            'gps-certificate-settings'
        );

        // Primary Color (Dark Blue)
        add_settings_field(
            'primary_color',
            __('Primary Color (Titles)', 'gps-courses'),
            [__CLASS__, 'color_field_callback'],
            'gps-certificate-settings',
            'gps_cert_colors',
            ['id' => 'primary_color', 'default' => '#193463']
        );

        // Secondary Color (Gold)
        add_settings_field(
            'secondary_color',
            __('Secondary Color (Accents)', 'gps-courses'),
            [__CLASS__, 'color_field_callback'],
            'gps-certificate-settings',
            'gps_cert_colors',
            ['id' => 'secondary_color', 'default' => '#BC9D67']
        );

        // Date Color
        add_settings_field(
            'date_color',
            __('Date Color', 'gps-courses'),
            [__CLASS__, 'color_field_callback'],
            'gps-certificate-settings',
            'gps_cert_colors',
            ['id' => 'date_color', 'default' => '#3498db']
        );

        // Footer Section
        add_settings_section(
            'gps_cert_footer',
            __('Footer Settings', 'gps-courses'),
            [__CLASS__, 'footer_section_callback'],
            'gps-certificate-settings'
        );

        // Instructor Label
        add_settings_field(
            'instructor_label',
            __('Instructor Label', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_footer',
            ['id' => 'instructor_label', 'default' => 'Instructor Name:']
        );

        // Course Method Label
        add_settings_field(
            'course_method_label',
            __('Course Method Label', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_footer',
            ['id' => 'course_method_label', 'default' => 'Course Method:']
        );

        // Course Method Default
        add_settings_field(
            'course_method_default',
            __('Default Course Method', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_footer',
            ['id' => 'course_method_default', 'default' => 'In Person']
        );

        // Location Label
        add_settings_field(
            'location_label',
            __('Location Label', 'gps-courses'),
            [__CLASS__, 'text_field_callback'],
            'gps-certificate-settings',
            'gps_cert_footer',
            ['id' => 'location_label', 'default' => 'Course Location:']
        );

        // Instructor Signature
        add_settings_field(
            'signature_image',
            __('Instructor Signature', 'gps-courses'),
            [__CLASS__, 'logo_field_callback'],
            'gps-certificate-settings',
            'gps_cert_footer',
            ['id' => 'signature_image', 'description' => __('Upload instructor signature (PNG with transparent background recommended)', 'gps-courses')]
        );

        // Font Sizes Section
        add_settings_section(
            'gps_cert_font_sizes',
            __('Font Size Settings', 'gps-courses'),
            [__CLASS__, 'font_sizes_section_callback'],
            'gps-certificate-settings'
        );

        // Header Title Font Size
        add_settings_field(
            'header_title_size',
            __('Header Title Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'header_title_size', 'default' => 20, 'min' => 8, 'max' => 40]
        );

        // Header Subtitle Font Size
        add_settings_field(
            'header_subtitle_size',
            __('Header Subtitle Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'header_subtitle_size', 'default' => 14, 'min' => 8, 'max' => 24]
        );

        // Main Title Font Size
        add_settings_field(
            'main_title_size',
            __('Main Title Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'main_title_size', 'default' => 32, 'min' => 16, 'max' => 48]
        );

        // Subtitle Font Size
        add_settings_field(
            'main_subtitle_size',
            __('Subtitle Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'main_subtitle_size', 'default' => 14, 'min' => 8, 'max' => 24]
        );

        // Attendee Name Font Size
        add_settings_field(
            'attendee_name_size',
            __('Attendee Name Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'attendee_name_size', 'default' => 24, 'min' => 12, 'max' => 36]
        );

        // Event Title Font Size
        add_settings_field(
            'event_title_size',
            __('Event Title Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'event_title_size', 'default' => 16, 'min' => 10, 'max' => 28]
        );

        // Description Font Size
        add_settings_field(
            'description_size',
            __('Description Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'description_size', 'default' => 10, 'min' => 6, 'max' => 16]
        );

        // Date Font Size
        add_settings_field(
            'date_size',
            __('Date Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'date_size', 'default' => 11, 'min' => 7, 'max' => 18]
        );

        // Footer Font Size
        add_settings_field(
            'footer_size',
            __('Footer Info Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'footer_size', 'default' => 9, 'min' => 6, 'max' => 14]
        );

        // PACE Font Size
        add_settings_field(
            'pace_text_size',
            __('PACE Text Font Size', 'gps-courses'),
            [__CLASS__, 'number_field_callback'],
            'gps-certificate-settings',
            'gps_cert_font_sizes',
            ['id' => 'pace_text_size', 'default' => 6.5, 'min' => 4, 'max' => 12, 'step' => 0.5]
        );

        // PACE Section
        add_settings_section(
            'gps_cert_pace',
            __('PACE Accreditation Settings', 'gps-courses'),
            [__CLASS__, 'pace_section_callback'],
            'gps-certificate-settings'
        );

        // PACE Logo
        add_settings_field(
            'pace_logo',
            __('PACE Logo', 'gps-courses'),
            [__CLASS__, 'logo_field_callback'],
            'gps-certificate-settings',
            'gps_cert_pace',
            ['id' => 'pace_logo']
        );

        // PACE Text
        add_settings_field(
            'pace_text',
            __('PACE Accreditation Text', 'gps-courses'),
            [__CLASS__, 'textarea_field_callback'],
            'gps-certificate-settings',
            'gps_cert_pace',
            [
                'id' => 'pace_text',
                'default' => "GPS Dental Training LLC.\nNationally Approved PACE Program\nProvider for FAGD/MAGD credit.\nApproval does not imply acceptance by any\nregulatory authority or AGD endorsement.\n1/1/2024 to 12/31/2025.\nProvider ID# 421027."
            ]
        );

        // Show PACE Section
        add_settings_field(
            'show_pace',
            __('Show PACE Section', 'gps-courses'),
            [__CLASS__, 'checkbox_field_callback'],
            'gps-certificate-settings',
            'gps_cert_pace',
            ['id' => 'show_pace', 'default' => true]
        );

        // Advanced Section
        add_settings_section(
            'gps_cert_advanced',
            __('Advanced Settings', 'gps-courses'),
            [__CLASS__, 'advanced_section_callback'],
            'gps-certificate-settings'
        );

        // Enable QR Code
        add_settings_field(
            'enable_qr_code',
            __('Enable QR Code', 'gps-courses'),
            [__CLASS__, 'checkbox_field_callback'],
            'gps-certificate-settings',
            'gps_cert_advanced',
            ['id' => 'enable_qr_code', 'default' => true, 'description' => __('Display validation QR code on certificate', 'gps-courses')]
        );

        // QR Code Position
        add_settings_field(
            'qr_code_position',
            __('QR Code Position', 'gps-courses'),
            [__CLASS__, 'select_field_callback'],
            'gps-certificate-settings',
            'gps_cert_advanced',
            [
                'id' => 'qr_code_position',
                'default' => 'bottom-right',
                'options' => [
                    'bottom-right' => __('Bottom Right', 'gps-courses'),
                    'bottom-left' => __('Bottom Left', 'gps-courses'),
                ]
            ]
        );

        // ============================================
        // SEMINAR CERTIFICATE SETTINGS
        // ============================================

        // Seminar Design Section
        add_settings_section(
            'gps_sem_cert_design',
            __('Certificate Design', 'gps-courses'),
            [__CLASS__, 'design_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field(
            'logo',
            __('Certificate Logo', 'gps-courses'),
            [__CLASS__, 'logo_field_callback'],
            'gps-seminar-certificate-settings',
            'gps_sem_cert_design',
            ['settings_group' => 'seminar']
        );

        // Seminar Header Section
        add_settings_section(
            'gps_sem_cert_header',
            __('Header Settings', 'gps-courses'),
            [__CLASS__, 'header_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field('header_title', __('Header Title', 'gps-courses'), [__CLASS__, 'text_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_header', ['id' => 'header_title', 'default' => 'GPS DENTAL', 'settings_group' => 'seminar']);
        add_settings_field('header_subtitle', __('Header Subtitle', 'gps-courses'), [__CLASS__, 'text_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_header', ['id' => 'header_subtitle', 'default' => 'TRAINING', 'settings_group' => 'seminar']);
        add_settings_field('header_bg_color', __('Header Background Color', 'gps-courses'), [__CLASS__, 'color_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_header', ['id' => 'header_bg_color', 'default' => '#193463', 'settings_group' => 'seminar']);
        add_settings_field('header_text_color', __('Header Text Color', 'gps-courses'), [__CLASS__, 'color_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_header', ['id' => 'header_text_color', 'default' => '#FFFFFF', 'settings_group' => 'seminar']);

        // Seminar Title Section
        add_settings_section(
            'gps_sem_cert_title',
            __('Certificate Title Settings', 'gps-courses'),
            [__CLASS__, 'title_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field('main_title', __('Main Title', 'gps-courses'), [__CLASS__, 'text_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_title', ['id' => 'main_title', 'default' => 'CERTIFICATE', 'settings_group' => 'seminar']);
        add_settings_field('main_subtitle', __('Subtitle', 'gps-courses'), [__CLASS__, 'text_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_title', ['id' => 'main_subtitle', 'default' => 'OF COMPLETION', 'settings_group' => 'seminar']);
        add_settings_field('description_text', __('Description Text', 'gps-courses'), [__CLASS__, 'textarea_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_title', ['id' => 'description_text', 'default' => 'This letter certifies that the person below has successfully completed the GPS Monthly Seminars continuing education program.', 'settings_group' => 'seminar']);

        // Seminar Content Section
        add_settings_section(
            'gps_sem_cert_content',
            __('Content Settings', 'gps-courses'),
            [__CLASS__, 'content_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field('program_provider', __('Program Provider Text', 'gps-courses'), [__CLASS__, 'text_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_content', ['id' => 'program_provider', 'default' => 'Program Provider: GPS Dental Training', 'settings_group' => 'seminar']);
        add_settings_field('code_label', __('Certificate Code Label', 'gps-courses'), [__CLASS__, 'text_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_content', ['id' => 'code_label', 'default' => 'CODE', 'settings_group' => 'seminar']);
        add_settings_field('code_bg_color', __('Code Background Color', 'gps-courses'), [__CLASS__, 'color_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_content', ['id' => 'code_bg_color', 'default' => '#BC9D67', 'settings_group' => 'seminar']);

        // Seminar Colors Section
        add_settings_section(
            'gps_sem_cert_colors',
            __('Color Settings', 'gps-courses'),
            [__CLASS__, 'colors_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field('primary_color', __('Primary Color (Titles)', 'gps-courses'), [__CLASS__, 'color_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_colors', ['id' => 'primary_color', 'default' => '#193463', 'settings_group' => 'seminar']);
        add_settings_field('secondary_color', __('Secondary Color (Accents)', 'gps-courses'), [__CLASS__, 'color_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_colors', ['id' => 'secondary_color', 'default' => '#BC9D67', 'settings_group' => 'seminar']);
        add_settings_field('date_color', __('Date Color', 'gps-courses'), [__CLASS__, 'color_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_colors', ['id' => 'date_color', 'default' => '#3498db', 'settings_group' => 'seminar']);

        // Seminar Footer Section
        add_settings_section(
            'gps_sem_cert_footer',
            __('Footer Settings', 'gps-courses'),
            [__CLASS__, 'footer_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field('instructor_label', __('Instructor Label', 'gps-courses'), [__CLASS__, 'text_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_footer', ['id' => 'instructor_label', 'default' => 'Instructor Name:', 'settings_group' => 'seminar']);
        add_settings_field('signature_image', __('Instructor Signature', 'gps-courses'), [__CLASS__, 'logo_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_footer', ['id' => 'signature_image', 'description' => __('Upload instructor signature (PNG with transparent background recommended)', 'gps-courses'), 'settings_group' => 'seminar']);

        // Seminar Font Sizes Section
        add_settings_section(
            'gps_sem_cert_font_sizes',
            __('Font Size Settings', 'gps-courses'),
            [__CLASS__, 'font_sizes_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field('header_title_size', __('Header Title Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'header_title_size', 'default' => 20, 'min' => 8, 'max' => 40, 'settings_group' => 'seminar']);
        add_settings_field('header_subtitle_size', __('Header Subtitle Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'header_subtitle_size', 'default' => 14, 'min' => 8, 'max' => 24, 'settings_group' => 'seminar']);
        add_settings_field('main_title_size', __('Main Title Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'main_title_size', 'default' => 32, 'min' => 16, 'max' => 48, 'settings_group' => 'seminar']);
        add_settings_field('main_subtitle_size', __('Subtitle Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'main_subtitle_size', 'default' => 14, 'min' => 8, 'max' => 24, 'settings_group' => 'seminar']);
        add_settings_field('attendee_name_size', __('Attendee Name Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'attendee_name_size', 'default' => 24, 'min' => 12, 'max' => 36, 'settings_group' => 'seminar']);
        add_settings_field('description_size', __('Description Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'description_size', 'default' => 10, 'min' => 6, 'max' => 16, 'settings_group' => 'seminar']);
        add_settings_field('date_size', __('Date Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'date_size', 'default' => 11, 'min' => 7, 'max' => 18, 'settings_group' => 'seminar']);
        add_settings_field('footer_size', __('Footer Info Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'footer_size', 'default' => 9, 'min' => 6, 'max' => 14, 'settings_group' => 'seminar']);
        add_settings_field('pace_text_size', __('PACE Text Font Size', 'gps-courses'), [__CLASS__, 'number_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_font_sizes', ['id' => 'pace_text_size', 'default' => 6.5, 'min' => 4, 'max' => 12, 'step' => 0.5, 'settings_group' => 'seminar']);

        // Seminar PACE Section
        add_settings_section(
            'gps_sem_cert_pace',
            __('PACE Accreditation Settings', 'gps-courses'),
            [__CLASS__, 'pace_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field('pace_logo', __('PACE Logo', 'gps-courses'), [__CLASS__, 'logo_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_pace', ['id' => 'pace_logo', 'settings_group' => 'seminar']);
        add_settings_field('pace_text', __('PACE Accreditation Text', 'gps-courses'), [__CLASS__, 'textarea_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_pace', ['id' => 'pace_text', 'default' => "GPS Dental Training LLC.\nNationally Approved PACE Program\nProvider for FAGD/MAGD credit.\nApproval does not imply acceptance by any\nregulatory authority or AGD endorsement.\n1/1/2024 to 12/31/2025.\nProvider ID# 421027.", 'settings_group' => 'seminar']);
        add_settings_field('show_pace', __('Show PACE Section', 'gps-courses'), [__CLASS__, 'checkbox_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_pace', ['id' => 'show_pace', 'default' => true, 'settings_group' => 'seminar']);

        // Seminar Advanced Section
        add_settings_section(
            'gps_sem_cert_advanced',
            __('Advanced Settings', 'gps-courses'),
            [__CLASS__, 'advanced_section_callback'],
            'gps-seminar-certificate-settings'
        );

        add_settings_field('enable_qr_code', __('Enable QR Code', 'gps-courses'), [__CLASS__, 'checkbox_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_advanced', ['id' => 'enable_qr_code', 'default' => true, 'description' => __('Display validation QR code on certificate', 'gps-courses'), 'settings_group' => 'seminar']);
        add_settings_field('qr_code_position', __('QR Code Position', 'gps-courses'), [__CLASS__, 'select_field_callback'], 'gps-seminar-certificate-settings', 'gps_sem_cert_advanced', ['id' => 'qr_code_position', 'default' => 'bottom-right', 'options' => ['bottom-right' => __('Bottom Right', 'gps-courses'), 'bottom-left' => __('Bottom Left', 'gps-courses')], 'settings_group' => 'seminar']);
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = [];

        // Text fields
        $text_fields = [
            'header_title', 'header_subtitle', 'main_title', 'main_subtitle',
            'program_provider', 'course_title_label', 'code_label',
            'instructor_label', 'course_method_label', 'course_method_default',
            'location_label', 'qr_code_position'
        ];

        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            }
        }

        // Textarea fields
        $textarea_fields = ['description_text', 'pace_text'];
        foreach ($textarea_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_textarea_field($input[$field]);
            }
        }

        // Color fields
        $color_fields = [
            'header_bg_color', 'header_text_color', 'code_bg_color',
            'primary_color', 'secondary_color', 'date_color'
        ];

        foreach ($color_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_hex_color($input[$field]);
            }
        }

        // Number fields (font sizes)
        $number_fields = [
            'header_title_size', 'header_subtitle_size', 'main_title_size', 'main_subtitle_size',
            'attendee_name_size', 'event_title_size', 'description_size', 'date_size',
            'footer_size', 'pace_text_size'
        ];

        foreach ($number_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = floatval($input[$field]);
            }
        }

        // URLs (logos and signature)
        if (isset($input['logo'])) {
            $sanitized['logo'] = esc_url_raw($input['logo']);
        }
        if (isset($input['pace_logo'])) {
            $sanitized['pace_logo'] = esc_url_raw($input['pace_logo']);
        }
        if (isset($input['signature_image'])) {
            $sanitized['signature_image'] = esc_url_raw($input['signature_image']);
        }

        // Checkboxes
        $sanitized['show_pace'] = isset($input['show_pace']) ? true : false;
        $sanitized['enable_qr_code'] = isset($input['enable_qr_code']) ? true : false;

        return $sanitized;
    }

    /**
     * Get a setting value for seminar certificates
     */
    public static function get_seminar($key, $default = '') {
        $settings = get_option('gps_seminar_certificate_settings', []);

        // Default values for seminar certificates
        $defaults = [
            'header_title' => 'GPS DENTAL',
            'header_subtitle' => 'TRAINING',
            'header_bg_color' => '#193463',
            'header_text_color' => '#FFFFFF',
            'main_title' => 'CERTIFICATE',
            'main_subtitle' => 'OF COMPLETION',
            'description_text' => 'This letter certifies that the person below has successfully completed the GPS Monthly Seminars continuing education program.',
            'program_provider' => 'Program Provider: GPS Dental Training',
            'code_label' => 'CODE',
            'code_bg_color' => '#BC9D67',
            'primary_color' => '#193463',
            'secondary_color' => '#BC9D67',
            'date_color' => '#3498db',
            'instructor_label' => 'Instructor Name:',
            'pace_text' => "GPS Dental Training LLC.\nNationally Approved PACE Program\nProvider for FAGD/MAGD credit.\nApproval does not imply acceptance by any\nregulatory authority or AGD endorsement.\n1/1/2024 to 12/31/2025.\nProvider ID# 421027.",
            'show_pace' => true,
            'logo' => '',
            'pace_logo' => '',
            'signature_image' => '',
            // Font sizes
            'header_title_size' => 20,
            'header_subtitle_size' => 14,
            'main_title_size' => 32,
            'main_subtitle_size' => 14,
            'attendee_name_size' => 24,
            'description_size' => 10,
            'date_size' => 11,
            'footer_size' => 9,
            'pace_text_size' => 6.5,
            // Advanced
            'enable_qr_code' => true,
            'qr_code_position' => 'bottom-right',
        ];

        if (isset($settings[$key])) {
            return $settings[$key];
        }

        return isset($defaults[$key]) ? $defaults[$key] : $default;
    }

    /**
     * Get a setting value (for regular course certificates)
     */
    public static function get($key, $default = '') {
        $settings = get_option('gps_certificate_settings', []);

        // Default values
        $defaults = [
            'header_title' => 'GPS DENTAL',
            'header_subtitle' => 'TRAINING',
            'header_bg_color' => '#193463',
            'header_text_color' => '#FFFFFF',
            'main_title' => 'CERTIFICATE',
            'main_subtitle' => 'OF COMPLETION',
            'description_text' => 'This letter certified the person below participated in the following course by GPS Dental Training.',
            'program_provider' => 'Program Provider: GPS Dental Training',
            'course_title_label' => 'Course Title',
            'code_label' => 'CODE',
            'code_bg_color' => '#BC9D67',
            'primary_color' => '#193463',
            'secondary_color' => '#BC9D67',
            'date_color' => '#3498db',
            'instructor_label' => 'Instructor Name:',
            'course_method_label' => 'Course Method:',
            'course_method_default' => 'In Person',
            'location_label' => 'Course Location:',
            'pace_text' => "GPS Dental Training LLC.\nNationally Approved PACE Program\nProvider for FAGD/MAGD credit.\nApproval does not imply acceptance by any\nregulatory authority or AGD endorsement.\n1/1/2024 to 12/31/2025.\nProvider ID# 421027.",
            'show_pace' => true,
            'logo' => '',
            'pace_logo' => '',
            'signature_image' => '',
            // Font sizes
            'header_title_size' => 20,
            'header_subtitle_size' => 14,
            'main_title_size' => 32,
            'main_subtitle_size' => 14,
            'attendee_name_size' => 24,
            'event_title_size' => 16,
            'description_size' => 10,
            'date_size' => 11,
            'footer_size' => 9,
            'pace_text_size' => 6.5,
            // Advanced
            'enable_qr_code' => true,
            'qr_code_position' => 'bottom-right',
        ];

        if (isset($settings[$key])) {
            return $settings[$key];
        }

        return isset($defaults[$key]) ? $defaults[$key] : $default;
    }

    /**
     * Enqueue scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-certificate-settings') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'course';
        ?>
        <div class="wrap">
            <h1><?php _e('Certificate Settings', 'gps-courses'); ?></h1>
            <p><?php _e('Customize the appearance and content of course completion certificates.', 'gps-courses'); ?></p>

            <!-- Tab Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="#" data-tab="course"
                   class="gps-cert-tab nav-tab <?php echo $active_tab === 'course' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Regular Course Certificate', 'gps-courses'); ?>
                </a>
                <a href="#" data-tab="seminar"
                   class="gps-cert-tab nav-tab <?php echo $active_tab === 'seminar' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Monthly Seminar Certificate', 'gps-courses'); ?>
                </a>
            </h2>

            <!-- Preview Button -->
            <div style="margin: 20px 0; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <button type="button" id="generate-pdf-btn" class="button button-secondary">
                    <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                    <?php _e('Generate PDF Preview', 'gps-courses'); ?>
                </button>
                <p class="description" style="margin: 10px 0 0 0;">
                    <?php _e('Generate a PDF preview with current settings.', 'gps-courses'); ?>
                </p>
            </div>

            <!-- Course Certificate Settings -->
            <div class="gps-cert-tab-content" data-tab-content="course" style="display: <?php echo $active_tab === 'course' ? 'block' : 'none'; ?>;">
                <!-- Settings Form -->
                <form method="post" action="options.php" id="course-certificate-form">
                    <?php
                    settings_fields('gps_certificate_settings');
                    do_settings_sections('gps-certificate-settings');
                    submit_button();
                    ?>
                </form>

                <!-- Live Preview Section -->
                <div style="margin-top: 30px;">
                    <h2><?php _e('Live Preview', 'gps-courses'); ?></h2>
                    <p class="description"><?php _e('This preview updates in real-time as you change settings above.', 'gps-courses'); ?></p>
                    <div id="course-certificate-preview" class="certificate-preview" style="
                        width: 100%;
                        max-width: 1000px;
                        height: 707px;
                        background: white;
                        border: 1px solid #ddd;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        position: relative;
                        overflow: auto;
                        margin-top: 15px;
                    ">
                        <!-- Preview will be rendered here -->
                    </div>
                </div>
            </div>

            <!-- Seminar Certificate Settings -->
            <div class="gps-cert-tab-content" data-tab-content="seminar" style="display: <?php echo $active_tab === 'seminar' ? 'block' : 'none'; ?>;">
                <!-- Settings Form -->
                <form method="post" action="options.php" id="seminar-certificate-form">
                    <?php
                    settings_fields('gps_seminar_certificate_settings');
                    do_settings_sections('gps-seminar-certificate-settings');
                    submit_button();
                    ?>
                </form>

                <!-- Live Preview Section -->
                <div style="margin-top: 30px;">
                    <h2><?php _e('Live Preview', 'gps-courses'); ?></h2>
                    <p class="description"><?php _e('This preview updates in real-time as you change settings above.', 'gps-courses'); ?></p>
                    <div id="seminar-certificate-preview" class="certificate-preview" style="
                        width: 100%;
                        max-width: 1000px;
                        height: 707px;
                        background: white;
                        border: 1px solid #ddd;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        position: relative;
                        overflow: auto;
                        margin-top: 15px;
                    ">
                        <!-- Preview will be rendered here -->
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Detect active tab
            var activeTab = '<?php echo esc_js($active_tab); ?>';

            // Tab switching (instant, no page reload)
            $('.gps-cert-tab').on('click', function(e) {
                e.preventDefault();
                var targetTab = $(this).data('tab');

                // Update active tab
                activeTab = targetTab;

                // Update tab navigation
                $('.gps-cert-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Show/hide tab content
                $('.gps-cert-tab-content').hide();
                $('[data-tab-content="' + targetTab + '"]').show();

                // Re-initialize color pickers for newly visible tab
                $('[data-tab-content="' + targetTab + '"] .gps-color-picker').wpColorPicker();

                // Update preview for new tab
                renderLivePreview(targetTab);
            });

            // Color picker (initialize for visible tab)
            $('[data-tab-content="' + activeTab + '"] .gps-color-picker').wpColorPicker();

            // Function to collect settings
            function collectSettings(certificateType) {
                var settings = {};
                var settingsPrefix = certificateType === 'seminar' ? 'gps_seminar_certificate_settings' : 'gps_certificate_settings';
                var activeForm = $('[data-tab-content="' + certificateType + '"]').find('form');

                activeForm.find('[name^="' + settingsPrefix + '"]').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        var key = name.replace(settingsPrefix + '[', '').replace(']', '');
                        if ($(this).attr('type') === 'checkbox') {
                            settings[key] = $(this).is(':checked');
                        } else {
                            settings[key] = $(this).val();
                        }
                    }
                });

                return settings;
            }

            // Function to render live preview
            function renderLivePreview(certificateType) {
                var settings = collectSettings(certificateType);
                var previewId = certificateType === 'seminar' ? '#seminar-certificate-preview' : '#course-certificate-preview';
                var $preview = $(previewId);

                // Get colors
                var headerBg = settings.header_bg_color || '#193463';
                var headerText = settings.header_text_color || '#FFFFFF';
                var primaryColor = settings.primary_color || '#193463';
                var secondaryColor = settings.secondary_color || '#BC9D67';
                var dateColor = settings.date_color || '#3498db';
                var codeBg = settings.code_bg_color || '#BC9D67';

                // Build HTML preview (landscape A4 scaled to fit - 297mm x 210mm = 1.414 ratio)
                var html = '<div style="width: 100%; height: 100%; padding: 15px; font-family: Helvetica, Arial, sans-serif; position: relative; box-sizing: border-box;">';

                // Inner container with landscape aspect ratio
                html += '<div style="border: 2px solid #c8c8c8; border-radius: 8px; width: 100%; height: 100%; position: relative; padding: 15px; box-sizing: border-box;">';

                // Header section
                html += '<div style="background: ' + headerBg + '; color: ' + headerText + '; padding: 18px; text-align: center; border-radius: 4px; margin-bottom: 15px;">';
                if (settings.logo) {
                    html += '<img src="' + settings.logo + '" style="max-height: 50px; max-width: 250px;" />';
                } else {
                    html += '<div style="font-size: 24px; font-weight: bold;">' + (settings.header_title || 'GPS DENTAL') + '</div>';
                    html += '<div style="font-size: 16px; color: ' + headerText + '; margin-top: 3px;">' + (settings.header_subtitle || 'TRAINING') + '</div>';
                }
                html += '</div>';

                // Main title
                html += '<div style="text-align: center; margin: 18px 0 12px 0;">';
                html += '<div style="font-size: 36px; font-weight: bold; color: ' + primaryColor + '; letter-spacing: 2px;">' + (settings.main_title || 'CERTIFICATE') + '</div>';
                html += '<div style="font-size: 15px; color: ' + secondaryColor + '; margin-top: 5px; letter-spacing: 1px;">' + (settings.main_subtitle || 'OF COMPLETION') + '</div>';
                html += '</div>';

                // Description
                html += '<div style="text-align: center; margin: 12px 30px; font-size: 11px; line-height: 1.5; color: #333;">';
                html += (settings.description_text || 'This letter certifies completion of the course.');
                html += '</div>';

                // Participant name
                html += '<div style="text-align: center; margin: 15px 0; font-size: 26px; font-weight: bold; color: ' + primaryColor + ';">John Doe Sample</div>';

                // Program provider (appears after name on actual cert)
                if (settings.program_provider) {
                    html += '<div style="text-align: center; margin: 8px 0; font-size: 11px; color: #666;">';
                    html += settings.program_provider;
                    html += '</div>';
                }

                if (certificateType === 'seminar') {
                    // CE Credits box
                    html += '<div style="text-align: center; margin: 15px 0;">';
                    html += '<div style="background: ' + secondaryColor + '; color: white; padding: 8px 25px; display: inline-block; border-radius: 4px; font-weight: bold; font-size: 15px;">24 CE Credits Earned</div>';
                    html += '</div>';

                    // Period dates
                    html += '<div style="text-align: center; margin: 12px 0; font-size: 14px; font-weight: bold; color: ' + dateColor + ';">January 1, 2025 - June 30, 2025</div>';
                } else {
                    // Course Title label and value
                    html += '<div style="text-align: center; margin: 10px 0; font-size: 11px; color: #666;">Course Title</div>';
                    html += '<div style="text-align: center; margin: 5px 0; font-size: 17px; font-weight: bold; color: ' + primaryColor + ';">Sample Course Title - Advanced Dental Implants</div>';

                    // Event date
                    html += '<div style="text-align: center; margin: 12px 0; font-size: 14px; font-weight: bold; color: ' + dateColor + ';">November 19, 2025</div>';

                    // Course method
                    html += '<div style="text-align: center; margin: 8px 0; font-size: 11px; color: #555;">';
                    html += '<strong>' + (settings.course_method_label || 'Course Method:') + '</strong> ' + (settings.course_method_default || 'In Person');
                    html += '</div>';

                    // Location
                    html += '<div style="text-align: center; margin: 6px 0; font-size: 11px; color: #555;">';
                    html += '<strong>' + (settings.location_label || 'Course Location:') + '</strong> GPS Dental Training Center';
                    html += '</div>';
                }

                // Certificate code
                html += '<div style="text-align: center; margin: 15px 0;">';
                html += '<div style="background: ' + codeBg + '; color: white; padding: 6px 18px; display: inline-block; border-radius: 4px; font-size: 11px; font-weight: bold;">';
                html += (settings.code_label || 'CODE') + ' #PREVIEW-' + Math.random().toString(36).substr(2, 6).toUpperCase();
                html += '</div>';
                html += '</div>';

                // Signature section
                if (settings.signature_image) {
                    html += '<div style="text-align: center; margin: 18px 0 8px 0;">';
                    html += '<img src="' + settings.signature_image + '" style="max-height: 45px; max-width: 180px;" />';
                    html += '</div>';
                } else {
                    html += '<div style="text-align: center; margin: 18px 0 8px 0; font-size: 36px; color: #bbb;">✓</div>';
                }

                // Instructor label
                html += '<div style="text-align: center; margin: 6px 0; font-size: 11px; font-weight: bold; color: ' + primaryColor + ';">';
                html += (settings.instructor_label || 'Instructor Name:') + ' Dr Carlos Castro DDS, FACP';
                html += '</div>';

                // Program name (for seminars)
                if (certificateType === 'seminar') {
                    html += '<div style="text-align: center; margin: 6px 0; font-size: 11px; font-weight: bold; color: ' + primaryColor + ';">GPS Monthly Seminars Program</div>';
                }

                // QR Code placeholder (positioned at bottom right or left based on settings)
                if (settings.enable_qr_code == '1' || settings.enable_qr_code === true) {
                    var qrPosition = settings.qr_code_position === 'bottom-left' ? 'left: 25px;' : 'right: 25px;';
                    html += '<div style="position: absolute; bottom: 25px; ' + qrPosition + ' width: 70px; height: 70px; background: #fff; border: 2px solid #ddd; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999; text-align: center; padding: 5px; box-sizing: border-box;">QR Code</div>';
                }

                // PACE section at bottom
                if (settings.show_pace == '1' || settings.show_pace === true) {
                    var paceMargin = '';
                    if (settings.enable_qr_code == '1' || settings.enable_qr_code === true) {
                        // Adjust PACE section width if QR code is present
                        if (settings.qr_code_position === 'bottom-left') {
                            paceMargin = 'left: 110px;';
                        } else {
                            paceMargin = 'right: 110px;';
                        }
                    }
                    html += '<div style="position: absolute; bottom: 25px; left: ' + (settings.qr_code_position === 'bottom-left' ? '110px' : '25px') + '; right: ' + (settings.qr_code_position === 'bottom-left' ? '25px' : '110px') + '; background: #f0f5fa; padding: 12px 15px; border-radius: 5px; font-size: 10px; line-height: 1.5; color: #444;">';
                    html += (settings.pace_text || 'GPS Dental Training LLC.<br>Nationally Approved PACE Program Provider for FAGD/MAGD credit.').replace(/\n/g, '<br>');
                    html += '</div>';
                }

                html += '</div></div>';

                $preview.html(html);
            }

            // Initial preview render
            renderLivePreview(activeTab);

            // Update preview when settings change
            $('input, textarea, select').on('change keyup', function() {
                renderLivePreview(activeTab);
            });

            // Update preview when color picker changes
            $('.gps-color-picker').on('change', function() {
                renderLivePreview(activeTab);
            });

            // PDF Generation Button
            $('#generate-pdf-btn').on('click', function() {
                var button = $(this);
                var certificateType = activeTab;

                button.prop('disabled', true).text('<?php _e('Generating PDF...', 'gps-courses'); ?>');

                var settings = collectSettings(certificateType);

                // Determine which action to use based on certificate type
                var ajaxAction = certificateType === 'seminar' ? 'gps_preview_seminar_certificate' : 'gps_preview_certificate';
                var ajaxNonce = certificateType === 'seminar' ? '<?php echo wp_create_nonce('gps_preview_seminar_certificate'); ?>' : '<?php echo wp_create_nonce('gps_preview_certificate'); ?>';

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: ajaxAction,
                        settings: settings,
                        nonce: ajaxNonce
                    },
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function(blob) {
                        var url = window.URL.createObjectURL(blob);
                        window.open(url, '_blank');
                        button.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span> <?php _e('Generate PDF Preview', 'gps-courses'); ?>');
                    },
                    error: function(xhr, status, error) {
                        alert('<?php _e('Error generating PDF. Please try again.', 'gps-courses'); ?>');
                        button.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span> <?php _e('Generate PDF Preview', 'gps-courses'); ?>');
                    }
                });
            });

            // Image upload
            $('.gps-upload-button').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var inputField = button.siblings('.gps-logo-input');
                var previewImage = button.siblings('.gps-logo-preview');

                var uploader = wp.media({
                    title: '<?php _e('Select Logo', 'gps-courses'); ?>',
                    button: { text: '<?php _e('Use this image', 'gps-courses'); ?>' },
                    multiple: false
                }).on('select', function() {
                    var attachment = uploader.state().get('selection').first().toJSON();
                    inputField.val(attachment.url);
                    if (previewImage.length) {
                        previewImage.attr('src', attachment.url).show();
                    } else {
                        button.before('<img src="' + attachment.url + '" class="gps-logo-preview" style="max-width: 200px; display: block; margin: 10px 0;">');
                    }
                }).open();
            });

            // Remove image
            $('.gps-remove-button').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var inputField = button.siblings('.gps-logo-input');
                var previewImage = button.siblings('.gps-logo-preview');

                inputField.val('');
                previewImage.hide();
            });
        });
        </script>

        <style>
            .form-table th {
                width: 250px;
            }
            .gps-logo-preview {
                max-width: 200px;
                height: auto;
                display: block;
                margin: 10px 0;
                border: 1px solid #ddd;
                padding: 5px;
                background: #fff;
            }
            .gps-upload-button,
            .gps-remove-button {
                margin-top: 5px;
            }
            .gps-field-description {
                color: #666;
                font-style: italic;
                margin-top: 5px;
            }
        </style>
        <?php
    }

    // Section Callbacks
    public static function design_section_callback() {
        echo '<p>' . __('Upload your logo to display on the certificate.', 'gps-courses') . '</p>';
    }

    public static function header_section_callback() {
        echo '<p>' . __('Customize the header section at the top of the certificate.', 'gps-courses') . '</p>';
    }

    public static function title_section_callback() {
        echo '<p>' . __('Customize the main certificate title and description.', 'gps-courses') . '</p>';
    }

    public static function content_section_callback() {
        echo '<p>' . __('Customize the content labels and text.', 'gps-courses') . '</p>';
    }

    public static function colors_section_callback() {
        echo '<p>' . __('Choose colors for different certificate elements.', 'gps-courses') . '</p>';
    }

    public static function footer_section_callback() {
        echo '<p>' . __('Customize the footer section with instructor and location information.', 'gps-courses') . '</p>';
    }

    public static function pace_section_callback() {
        echo '<p>' . __('Configure PACE accreditation information displayed at the bottom of the certificate.', 'gps-courses') . '</p>';
    }

    // Field Callbacks
    public static function text_field_callback($args) {
        $is_seminar = isset($args['settings_group']) && $args['settings_group'] === 'seminar';
        $settings_name = $is_seminar ? 'gps_seminar_certificate_settings' : 'gps_certificate_settings';
        $value = $is_seminar ? self::get_seminar($args['id'], $args['default'] ?? '') : self::get($args['id'], $args['default'] ?? '');
        ?>
        <input type="text"
               id="<?php echo esc_attr($settings_name); ?>_<?php echo esc_attr($args['id']); ?>"
               name="<?php echo esc_attr($settings_name); ?>[<?php echo esc_attr($args['id']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php
        if (isset($args['description'])) {
            echo '<p class="gps-field-description">' . esc_html($args['description']) . '</p>';
        }
    }

    public static function textarea_field_callback($args) {
        $is_seminar = isset($args['settings_group']) && $args['settings_group'] === 'seminar';
        $settings_name = $is_seminar ? 'gps_seminar_certificate_settings' : 'gps_certificate_settings';
        $value = $is_seminar ? self::get_seminar($args['id'], $args['default'] ?? '') : self::get($args['id'], $args['default'] ?? '');
        ?>
        <textarea id="<?php echo esc_attr($settings_name); ?>_<?php echo esc_attr($args['id']); ?>"
                  name="<?php echo esc_attr($settings_name); ?>[<?php echo esc_attr($args['id']); ?>]"
                  rows="5"
                  class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php
        if (isset($args['description'])) {
            echo '<p class="gps-field-description">' . esc_html($args['description']) . '</p>';
        }
    }

    public static function color_field_callback($args) {
        $is_seminar = isset($args['settings_group']) && $args['settings_group'] === 'seminar';
        $settings_name = $is_seminar ? 'gps_seminar_certificate_settings' : 'gps_certificate_settings';
        $value = $is_seminar ? self::get_seminar($args['id'], $args['default'] ?? '#000000') : self::get($args['id'], $args['default'] ?? '#000000');
        ?>
        <input type="text"
               id="<?php echo esc_attr($settings_name); ?>_<?php echo esc_attr($args['id']); ?>"
               name="<?php echo esc_attr($settings_name); ?>[<?php echo esc_attr($args['id']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               class="gps-color-picker">
        <?php
    }

    public static function checkbox_field_callback($args) {
        $is_seminar = isset($args['settings_group']) && $args['settings_group'] === 'seminar';
        $settings_name = $is_seminar ? 'gps_seminar_certificate_settings' : 'gps_certificate_settings';
        $value = $is_seminar ? self::get_seminar($args['id'], $args['default'] ?? false) : self::get($args['id'], $args['default'] ?? false);
        ?>
        <label>
            <input type="checkbox"
                   id="<?php echo esc_attr($settings_name); ?>_<?php echo esc_attr($args['id']); ?>"
                   name="<?php echo esc_attr($settings_name); ?>[<?php echo esc_attr($args['id']); ?>]"
                   value="1"
                   <?php checked($value, true); ?>>
            <?php echo isset($args['label']) ? esc_html($args['label']) : __('Enable', 'gps-courses'); ?>
        </label>
        <?php
        if (isset($args['description'])) {
            echo '<p class="gps-field-description">' . esc_html($args['description']) . '</p>';
        }
    }

    public static function logo_field_callback($args) {
        $id = isset($args['id']) ? $args['id'] : 'logo';
        $is_seminar = isset($args['settings_group']) && $args['settings_group'] === 'seminar';
        $settings_name = $is_seminar ? 'gps_seminar_certificate_settings' : 'gps_certificate_settings';
        $value = $is_seminar ? self::get_seminar($id, '') : self::get($id, '');
        ?>
        <input type="hidden"
               id="<?php echo esc_attr($settings_name); ?>_<?php echo esc_attr($id); ?>"
               name="<?php echo esc_attr($settings_name); ?>[<?php echo esc_attr($id); ?>]"
               value="<?php echo esc_url($value); ?>"
               class="gps-logo-input">

        <?php if ($value): ?>
            <img src="<?php echo esc_url($value); ?>" class="gps-logo-preview">
        <?php endif; ?>

        <br>
        <button type="button" class="button gps-upload-button">
            <?php _e('Upload Image', 'gps-courses'); ?>
        </button>

        <?php if ($value): ?>
            <button type="button" class="button gps-remove-button">
                <?php _e('Remove Image', 'gps-courses'); ?>
            </button>
        <?php endif; ?>

        <?php if (isset($args['description'])): ?>
            <p class="gps-field-description">
                <?php echo esc_html($args['description']); ?>
            </p>
        <?php else: ?>
            <p class="gps-field-description">
                <?php _e('Recommended size: 400x100px (PNG format with transparency for best results)', 'gps-courses'); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    public static function number_field_callback($args) {
        $is_seminar = isset($args['settings_group']) && $args['settings_group'] === 'seminar';
        $settings_name = $is_seminar ? 'gps_seminar_certificate_settings' : 'gps_certificate_settings';
        $value = $is_seminar ? self::get_seminar($args['id'], $args['default'] ?? 0) : self::get($args['id'], $args['default'] ?? 0);
        $min = $args['min'] ?? 1;
        $max = $args['max'] ?? 100;
        $step = $args['step'] ?? 1;
        ?>
        <input type="number"
               id="<?php echo esc_attr($settings_name); ?>_<?php echo esc_attr($args['id']); ?>"
               name="<?php echo esc_attr($settings_name); ?>[<?php echo esc_attr($args['id']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($min); ?>"
               max="<?php echo esc_attr($max); ?>"
               step="<?php echo esc_attr($step); ?>"
               class="small-text">
        <span class="description"><?php _e('pt', 'gps-courses'); ?></span>
        <?php
        if (isset($args['description'])) {
            echo '<p class="gps-field-description">' . esc_html($args['description']) . '</p>';
        }
    }

    public static function select_field_callback($args) {
        $is_seminar = isset($args['settings_group']) && $args['settings_group'] === 'seminar';
        $settings_name = $is_seminar ? 'gps_seminar_certificate_settings' : 'gps_certificate_settings';
        $value = $is_seminar ? self::get_seminar($args['id'], $args['default'] ?? '') : self::get($args['id'], $args['default'] ?? '');
        $options = $args['options'] ?? [];
        ?>
        <select id="<?php echo esc_attr($settings_name); ?>_<?php echo esc_attr($args['id']); ?>"
                name="<?php echo esc_attr($settings_name); ?>[<?php echo esc_attr($args['id']); ?>]">
            <?php foreach ($options as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        if (isset($args['description'])) {
            echo '<p class="gps-field-description">' . esc_html($args['description']) . '</p>';
        }
    }

    public static function font_sizes_section_callback() {
        echo '<p>' . __('Control the font sizes for different elements on the certificate. Adjust these to fit your content properly.', 'gps-courses') . '</p>';
    }

    public static function advanced_section_callback() {
        echo '<p>' . __('Advanced certificate features including QR code validation and preview.', 'gps-courses') . '</p>';
    }
}
