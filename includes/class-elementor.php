<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Elementor Integration
 * Registers and manages all GPS Courses Elementor widgets
 */
class Elementor_Integration {

    /**
     * Initialize Elementor integration
     */
    public static function init() {
        // Check if Elementor is active
        add_action('plugins_loaded', [__CLASS__, 'check_elementor']);

        // Register widget categories
        add_action('elementor/elements/categories_registered', [__CLASS__, 'register_widget_categories']);

        // Register widgets
        add_action('elementor/widgets/register', [__CLASS__, 'register_widgets']);

        // Enqueue editor scripts
        add_action('elementor/editor/before_enqueue_scripts', [__CLASS__, 'editor_scripts']);

        // Enqueue frontend scripts and styles
        add_action('elementor/frontend/after_enqueue_styles', [__CLASS__, 'frontend_styles']);
        add_action('elementor/frontend/after_register_scripts', [__CLASS__, 'frontend_scripts']);
    }

    /**
     * Check if Elementor is installed and active
     */
    public static function check_elementor() {
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Check Elementor version
        if (!version_compare(ELEMENTOR_VERSION, '3.0.0', '>=')) {
            add_action('admin_notices', [__CLASS__, 'elementor_version_notice']);
            return;
        }
    }

    /**
     * Admin notice for Elementor version
     */
    public static function elementor_version_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('GPS Courses requires Elementor version 3.0 or higher.', 'gps-courses'); ?></p>
        </div>
        <?php
    }

    /**
     * Register widget categories
     */
    public static function register_widget_categories($elements_manager) {
        $elements_manager->add_category(
            'gps-courses',
            [
                'title' => __('GPS Courses', 'gps-courses'),
                'icon' => 'fa fa-graduation-cap',
            ]
        );
    }

    /**
     * Register all widgets
     */
    public static function register_widgets($widgets_manager) {
        // Load widget base class
        require_once GPSC_PATH . 'widgets/base-widget.php';

        // Load and register each widget
        $widgets = [
            'event-grid',
            'event-list',
            'event-slider',
            'event-calendar',
            'single-event',
            'speaker-grid',
            'ticket-selector',
            'schedule-display',
            'google-maps',
            'countdown-timer',
            'ce-credits-display',
            'course-objectives',
            'course-description',
            'event-dates-display',
            'share-course',
            'add-to-calendar',
            // Monthly Seminars widgets
            'seminar-registration',
            'seminar-progress',
            'seminar-schedule',
        ];

        foreach ($widgets as $widget) {
            try {
                $widget_file = GPSC_PATH . 'widgets/' . $widget . '.php';

                if (file_exists($widget_file)) {
                    require_once $widget_file;

                    // Convert widget slug to class name
                    // event-grid -> Event_Grid_Widget
                    $class_name = str_replace('-', '_', $widget);
                    $class_name = str_replace(' ', '_', ucwords(str_replace('_', ' ', $class_name)));
                    $class_name = 'GPSC\\Widgets\\' . $class_name . '_Widget';

                    if (class_exists($class_name)) {
                        $widgets_manager->register(new $class_name());
                    } else {
                        error_log('GPS Courses: Widget class not found - ' . $class_name);
                    }
                } else {
                    error_log('GPS Courses: Widget file not found - ' . $widget_file);
                }
            } catch (\Exception $e) {
                error_log('GPS Courses: Error loading widget ' . $widget . ' - ' . $e->getMessage());
            } catch (\Error $e) {
                error_log('GPS Courses: Fatal error loading widget ' . $widget . ' - ' . $e->getMessage());
            }
        }
    }

    /**
     * Enqueue editor scripts
     */
    public static function editor_scripts() {
        wp_enqueue_script(
            'gps-courses-elementor-editor',
            GPSC_URL . 'assets/js/elementor-editor.js',
            ['jquery', 'elementor-editor'],
            GPSC_VERSION,
            true
        );

        wp_localize_script('gps-courses-elementor-editor', 'gpsCourses', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_courses_nonce'),
        ]);
    }

    /**
     * Enqueue frontend styles
     */
    public static function frontend_styles() {
        wp_enqueue_style(
            'gps-courses-elementor',
            GPSC_URL . 'assets/css/elementor-widgets.css',
            [],
            GPSC_VERSION
        );

        // Register calendar styles
        wp_register_style(
            'gps-courses-calendar',
            GPSC_URL . 'assets/css/calendar.css',
            [],
            GPSC_VERSION
        );

        // Register share course styles
        wp_register_style(
            'gps-courses-share',
            GPSC_URL . 'assets/css/share-course.css',
            [],
            GPSC_VERSION
        );

        // Register add to calendar styles
        wp_register_style(
            'gps-courses-add-calendar',
            GPSC_URL . 'assets/css/add-to-calendar.css',
            [],
            GPSC_VERSION
        );
    }

    /**
     * Register frontend scripts
     */
    public static function frontend_scripts() {
        // Main widgets script
        wp_register_script(
            'gps-courses-widgets',
            GPSC_URL . 'assets/js/elementor-widgets.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        // Calendar script
        wp_register_script(
            'gps-courses-calendar',
            GPSC_URL . 'assets/js/calendar.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        // Countdown timer script
        wp_register_script(
            'gps-courses-countdown',
            GPSC_URL . 'assets/js/countdown.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        // Ticket selector script
        wp_register_script(
            'gps-courses-ticket-selector',
            GPSC_URL . 'assets/js/ticket-selector.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        // Share course script
        wp_register_script(
            'gps-courses-share',
            GPSC_URL . 'assets/js/share-course.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        // Add to calendar script
        wp_register_script(
            'gps-courses-add-calendar',
            GPSC_URL . 'assets/js/add-to-calendar.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        // Slider (uses Swiper if available)
        wp_register_script(
            'swiper',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
            [],
            '11.0.0',
            true
        );

        wp_register_style(
            'swiper',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
            [],
            '11.0.0'
        );

        // Google Maps API
        $google_maps_api_key = get_option('gps_google_maps_api_key', '');
        if (!empty($google_maps_api_key)) {
            wp_register_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_maps_api_key) . '&libraries=places',
                [],
                null,
                true
            );
        }

        // Localize main script
        wp_localize_script('gps-courses-widgets', 'gpsCourses', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gps_courses_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'gps-courses'),
                'no_events' => __('No events found.', 'gps-courses'),
                'error' => __('An error occurred. Please try again.', 'gps-courses'),
            ],
        ]);
    }

    /**
     * Check if we're in Elementor editor
     */
    public static function is_elementor_editor() {
        return \Elementor\Plugin::$instance->editor->is_edit_mode();
    }

    /**
     * Check if we're in Elementor preview
     */
    public static function is_elementor_preview() {
        return \Elementor\Plugin::$instance->preview->is_preview_mode();
    }
}
