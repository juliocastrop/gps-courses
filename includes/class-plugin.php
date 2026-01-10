<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

class Plugin {

    public static function init() {
        // Autocargar otras clases
        require_once GPSC_PATH . 'includes/class-activator.php';
        require_once GPSC_PATH . 'includes/class-posttypes.php';
        require_once GPSC_PATH . 'includes/class-woocommerce.php';
        require_once GPSC_PATH . 'includes/class-credits.php';
        require_once GPSC_PATH . 'includes/class-tickets.php';
        require_once GPSC_PATH . 'includes/class-tickets-admin.php';
        require_once GPSC_PATH . 'includes/class-schedules.php';
        require_once GPSC_PATH . 'includes/class-qrcode.php';
        require_once GPSC_PATH . 'includes/class-emails.php';
        require_once GPSC_PATH . 'includes/class-email-settings.php';
        require_once GPSC_PATH . 'includes/class-waitlist.php';
        require_once GPSC_PATH . 'includes/class-elementor.php';
        require_once GPSC_PATH . 'includes/class-attendance.php';
        require_once GPSC_PATH . 'includes/class-shortcodes.php';
        require_once GPSC_PATH . 'includes/class-pdf.php';
        require_once GPSC_PATH . 'includes/class-certificate-settings.php';
        require_once GPSC_PATH . 'includes/class-certificates.php';
        require_once GPSC_PATH . 'includes/class-certificate-validation.php';
        require_once GPSC_PATH . 'includes/class-reports.php';
        require_once GPSC_PATH . 'includes/class-settings.php';
        require_once GPSC_PATH . 'includes/class-api.php';
        require_once GPSC_PATH . 'includes/class-debug-helper.php';
        // require_once GPSC_PATH . 'includes/class-cart-debug.php'; // Disabled - only for troubleshooting

        // Monthly Seminars
        require_once GPSC_PATH . 'includes/class-seminars.php';
        require_once GPSC_PATH . 'includes/class-seminar-registrations.php';
        require_once GPSC_PATH . 'includes/class-seminar-attendance.php';
        require_once GPSC_PATH . 'includes/class-seminar-notifications.php';
        require_once GPSC_PATH . 'includes/class-seminar-certificates.php';
        require_once GPSC_PATH . 'includes/class-seminar-waitlist.php';

        require_once GPSC_PATH . 'includes/helpers.php';

        // Hooks base
        add_action('init', [__CLASS__, 'register']);
        add_action('admin_init', [Activator::class, 'handle_recreate_tables_request']);
        add_action('admin_notices', [Activator::class, 'show_recreate_tables_notice']);
        Posttypes::init();
        Tickets::init();
        Tickets_Admin::init();
        Schedules::init();
        Emails::init();
        Email_Settings::init();
        Waitlist::init();
        Elementor_Integration::init();
        Attendance::init();
        Shortcodes::init();
        PDF_Generator::init();
        Certificate_Settings::init();
        Certificates::init();
        Certificate_Validation::init();
        Reports::init();
        Settings::init();
        API::init();
        Debug_Helper::init();
        // Cart_Debug::init(); // Disabled - only for troubleshooting

        // Initialize Monthly Seminars
        Seminars::init();
        Seminar_Registrations::init();
        Seminar_Attendance::init();
        Seminar_Notifications::init();
        Seminar_Certificates::init();
        Seminar_Waitlist::init();

        Woo::hooks();
    }

    public static function register() {
        Posttypes::register();
    }
}
