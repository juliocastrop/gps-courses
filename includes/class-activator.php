<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

class Activator {
    public static function activate() {
        global $wpdb;

        // 🔹 Asegurarse de que las clases necesarias estén disponibles durante la activación
        require_once GPSC_PATH . 'includes/class-posttypes.php';
        require_once GPSC_PATH . 'includes/class-certificate-validation.php';

        $charset = $wpdb->get_charset_collate();

        $enroll = "CREATE TABLE {$wpdb->prefix}gps_enrollments (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            session_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'completed',
            attended TINYINT(1) DEFAULT 0,
            checked_in_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) $charset;";

        $ledger = "CREATE TABLE {$wpdb->prefix}gps_ce_ledger (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            event_id BIGINT(20) UNSIGNED NOT NULL,
            credits INT(11) NOT NULL,
            source VARCHAR(20) DEFAULT 'auto',
            transaction_type VARCHAR(20) DEFAULT 'attendance',
            notes VARCHAR(255) DEFAULT NULL,
            awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY event_id (event_id)
        ) $charset;";

        $tickets = "CREATE TABLE {$wpdb->prefix}gps_tickets (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_code VARCHAR(50) NOT NULL,
            ticket_type_id BIGINT(20) UNSIGNED NOT NULL,
            event_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            order_item_id BIGINT(20) UNSIGNED DEFAULT NULL,
            attendee_name VARCHAR(255) DEFAULT NULL,
            attendee_email VARCHAR(255) DEFAULT NULL,
            qr_code_path VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'valid',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_code (ticket_code),
            KEY event_id (event_id),
            KEY user_id (user_id),
            KEY order_id (order_id)
        ) $charset;";

        $attendance = "CREATE TABLE {$wpdb->prefix}gps_attendance (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT(20) UNSIGNED NOT NULL,
            event_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            checked_in_at DATETIME NOT NULL,
            checked_in_by BIGINT(20) UNSIGNED DEFAULT NULL,
            check_in_method VARCHAR(20) DEFAULT 'qr_code',
            notes TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY event_id (event_id),
            KEY user_id (user_id)
        ) $charset;";

        $waitlist = "CREATE TABLE {$wpdb->prefix}gps_waitlist (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            ticket_type_id BIGINT(20) UNSIGNED NOT NULL,
            event_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            notified_at DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            PRIMARY KEY  (id),
            KEY email (email),
            KEY ticket_type_id (ticket_type_id),
            KEY event_id (event_id),
            KEY status (status)
        ) $charset;";

        $certificates = "CREATE TABLE {$wpdb->prefix}gps_certificates (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ticket_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            event_id BIGINT(20) UNSIGNED NOT NULL,
            certificate_path VARCHAR(255) DEFAULT NULL,
            certificate_url VARCHAR(255) DEFAULT NULL,
            generated_at DATETIME DEFAULT NULL,
            certificate_sent_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ticket_id (ticket_id),
            KEY user_id (user_id),
            KEY event_id (event_id)
        ) $charset;";

        // Monthly Seminars Tables
        $seminar_registrations = "CREATE TABLE {$wpdb->prefix}gps_seminar_registrations (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            seminar_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            start_session_date DATETIME DEFAULT NULL,
            sessions_completed INT(11) DEFAULT 0,
            sessions_remaining INT(11) DEFAULT 10,
            makeup_used TINYINT(1) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            qr_code VARCHAR(255) DEFAULT NULL,
            qr_code_path VARCHAR(255) DEFAULT NULL,
            qr_scan_count INT(11) DEFAULT 0,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY seminar_id (seminar_id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset;";

        $seminar_sessions = "CREATE TABLE {$wpdb->prefix}gps_seminar_sessions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            seminar_id BIGINT(20) UNSIGNED NOT NULL,
            session_number INT(11) NOT NULL,
            session_date DATE NOT NULL,
            session_time_start TIME DEFAULT NULL,
            session_time_end TIME DEFAULT NULL,
            topic VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            capacity INT(11) DEFAULT 50,
            registered_count INT(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY seminar_id (seminar_id),
            KEY session_date (session_date),
            KEY session_number (session_number)
        ) $charset;";

        $seminar_attendance = "CREATE TABLE {$wpdb->prefix}gps_seminar_attendance (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            registration_id BIGINT(20) UNSIGNED NOT NULL,
            session_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            seminar_id BIGINT(20) UNSIGNED NOT NULL,
            attended TINYINT(1) DEFAULT 1,
            checked_in_at DATETIME NOT NULL,
            checked_in_by BIGINT(20) UNSIGNED DEFAULT NULL,
            is_makeup TINYINT(1) DEFAULT 0,
            credits_awarded INT(11) DEFAULT 2,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY registration_id (registration_id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY seminar_id (seminar_id)
        ) $charset;";

        $seminar_waitlist = "CREATE TABLE {$wpdb->prefix}gps_seminar_waitlist (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            seminar_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            position INT(11) DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'waiting',
            created_at DATETIME NOT NULL,
            notified_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY seminar_id (seminar_id),
            KEY user_id (user_id),
            KEY email (email),
            KEY status (status),
            KEY position (position)
        ) $charset;";

        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        // Create all tables silently
        dbDelta($enroll);
        dbDelta($ledger);
        dbDelta($tickets);
        dbDelta($attendance);
        dbDelta($waitlist);
        dbDelta($certificates);
        dbDelta($seminar_registrations);
        dbDelta($seminar_sessions);
        dbDelta($seminar_attendance);
        dbDelta($seminar_waitlist);

        // 🔹 Registrar CPTs y rewrite rules para que los permalinks se actualicen correctamente
        Posttypes::register();

        // Register certificate validation rewrite rules
        Certificate_Validation::add_rewrite_rules();

        flush_rewrite_rules();

        update_option('gps_courses_db_version', GPSC_VERSION);
    }

    /**
     * Check if tables exist and create them if missing
     */
    public static function maybe_create_tables() {
        global $wpdb;

        // Check if tickets table exists
        $table_name = $wpdb->prefix . 'gps_tickets';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        // If table doesn't exist, recreate all tables (safer than trying to modify)
        if (!$table_exists) {
            error_log('GPS Courses: Database tables missing, recreating now...');
            self::recreate_tables();
        } else {
            // Check if transaction_type column exists in ce_ledger
            $column_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$wpdb->prefix}gps_ce_ledger LIKE 'transaction_type'"
            );

            if (empty($column_exists)) {
                error_log('GPS Courses: Adding missing transaction_type column...');
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}gps_ce_ledger
                     ADD COLUMN transaction_type VARCHAR(20) DEFAULT 'attendance' AFTER source"
                );
            }
        }
    }

    /**
     * Manually recreate all database tables
     * Useful for fixing database issues
     */
    public static function recreate_tables() {
        global $wpdb;

        error_log('GPS Courses: Starting table recreation...');

        // Suppress errors during drop
        $wpdb->hide_errors();

        // Drop existing tables in correct order (reverse of foreign key dependencies)
        $tables = [
            $wpdb->prefix . 'gps_seminar_waitlist',
            $wpdb->prefix . 'gps_seminar_attendance',
            $wpdb->prefix . 'gps_seminar_sessions',
            $wpdb->prefix . 'gps_seminar_registrations',
            $wpdb->prefix . 'gps_attendance',
            $wpdb->prefix . 'gps_ce_ledger',
            $wpdb->prefix . 'gps_tickets',
            $wpdb->prefix . 'gps_enrollments',
            $wpdb->prefix . 'gps_waitlist',
            $wpdb->prefix . 'gps_certificates',
        ];

        foreach ($tables as $table) {
            $result = $wpdb->query("DROP TABLE IF EXISTS $table");
            error_log("GPS Courses: Dropped table $table - Result: " . ($result !== false ? 'success' : 'failed'));
        }

        // Show errors again
        $wpdb->show_errors();

        // Recreate tables
        error_log('GPS Courses: Creating fresh tables...');
        self::activate();

        error_log('GPS Courses: Table recreation completed');

        return true;
    }

    /**
     * Handle manual table recreation from admin
     */
    public static function handle_recreate_tables_request() {
        if (!isset($_POST['gps_recreate_tables']) || !isset($_POST['gps_recreate_nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['gps_recreate_nonce'], 'gps_recreate_tables')) {
            wp_die(__('Security check failed', 'gps-courses'));
        }

        // Check permissions
        if (!current_user_can('activate_plugins')) {
            wp_die(__('You do not have permission to do this', 'gps-courses'));
        }

        // Recreate tables
        self::recreate_tables();

        // Redirect with success message
        $redirect_url = admin_url('admin.php?page=gps-dashboard&tables_recreated=1');
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Show admin notice for table recreation
     */
    public static function show_recreate_tables_notice() {
        // Show success message
        if (isset($_GET['tables_recreated'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . __('GPS Courses:', 'gps-courses') . '</strong> ' . __('Database tables have been recreated successfully!', 'gps-courses') . '</p>';
            echo '</div>';
            return;
        }

        global $wpdb;

        // Check if tables exist
        $table_name = $wpdb->prefix . 'gps_tickets';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . __('GPS Courses:', 'gps-courses') . '</strong> ' . __('Database tables are missing! Please recreate them.', 'gps-courses') . '</p>';
            echo '<form method="post" style="display: inline;">';
            echo '<input type="hidden" name="gps_recreate_tables" value="1">';
            echo wp_nonce_field('gps_recreate_tables', 'gps_recreate_nonce', true, false);
            echo '<button type="submit" class="button button-primary">';
            echo '<span class="dashicons dashicons-database-add" style="margin-top: 3px;"></span> ';
            echo __('Recreate Database Tables', 'gps-courses');
            echo '</button>';
            echo '</form>';
            echo '</div>';
        }
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
