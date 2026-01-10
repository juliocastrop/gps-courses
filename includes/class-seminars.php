<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Monthly Seminars Management
 *
 * Handles GPS Monthly Seminars - a 10-session program with one-time $750 payment,
 * automatic CE credits (2 per session), and comprehensive tracking.
 */
class Seminars {

    /**
     * Initialize seminars functionality
     */
    public static function init() {
        // Admin hooks
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        // AJAX handlers
        add_action('wp_ajax_gps_create_seminar_session', [__CLASS__, 'ajax_create_session']);
        add_action('wp_ajax_gps_delete_seminar_session', [__CLASS__, 'ajax_delete_session']);
        add_action('wp_ajax_gps_update_seminar_session', [__CLASS__, 'ajax_update_session']);
        add_action('wp_ajax_gps_get_seminar_stats', [__CLASS__, 'ajax_get_stats']);
    }

    /**
     * Add admin menu pages
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Monthly Seminars', 'gps-courses'),
            __('Monthly Seminars', 'gps-courses'),
            'manage_options',
            'gps-seminars',
            [__CLASS__, 'render_seminars_page']
        );

        add_submenu_page(
            'gps-dashboard',
            __('Seminar Registrants', 'gps-courses'),
            __('Seminar Registrants', 'gps-courses'),
            'manage_options',
            'gps-seminar-registrants',
            [__CLASS__, 'render_registrants_page']
        );

        add_submenu_page(
            'gps-dashboard',
            __('Session Attendance', 'gps-courses'),
            __('Session Attendance', 'gps-courses'),
            'manage_options',
            'gps-seminar-attendance',
            [__CLASS__, 'render_attendance_page']
        );

        add_submenu_page(
            'gps-dashboard',
            __('Seminar Waitlist', 'gps-courses'),
            __('Seminar Waitlist', 'gps-courses'),
            'manage_options',
            'gps-seminar-waitlist',
            [__CLASS__, 'render_waitlist_page']
        );

        add_submenu_page(
            'gps-dashboard',
            __('Seminar Reports', 'gps-courses'),
            __('Seminar Reports', 'gps-courses'),
            'manage_options',
            'gps-seminar-reports',
            [__CLASS__, 'render_reports_page']
        );

        add_submenu_page(
            'gps-dashboard',
            __('Email Notifications', 'gps-courses'),
            __('Email Notifications', 'gps-courses'),
            'manage_options',
            'gps-seminar-notifications',
            [__CLASS__, 'render_notifications_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'gps-seminar') === false) {
            return;
        }

        // Check if CSS file exists before enqueuing
        $css_path = GPSC_PATH . 'assets/css/admin-seminars.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'gps-seminars-admin',
                GPSC_URL . 'assets/css/admin-seminars.css',
                [],
                GPSC_VERSION
            );
        }

        // For attendance page, enqueue QR scanner library
        if ($hook === 'gps-courses_page_gps-seminar-attendance') {
            wp_enqueue_script(
                'html5-qrcode',
                'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
                [],
                '2.3.8',
                true
            );
        }

        // Check if JS file exists before enqueuing
        $js_path = GPSC_PATH . 'assets/js/admin-seminars.js';
        if (file_exists($js_path)) {
            $dependencies = ['jquery', 'jquery-ui-datepicker'];

            // Add html5-qrcode dependency for attendance page
            if ($hook === 'gps-courses_page_gps-seminar-attendance') {
                $dependencies[] = 'html5-qrcode';
            }

            wp_enqueue_script(
                'gps-seminars-admin',
                GPSC_URL . 'assets/js/admin-seminars.js',
                $dependencies,
                GPSC_VERSION,
                true
            );

            wp_localize_script('gps-seminars-admin', 'gpsSeminars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gps_seminars_nonce'),
                'i18n' => [
                    'starting' => __('Starting...', 'gps-courses'),
                    'start_camera' => __('Start Camera Scanner', 'gps-courses'),
                    'stop_camera' => __('Stop Camera', 'gps-courses'),
                    'scanner_started' => __('Scanner started. Point camera at QR code.', 'gps-courses'),
                    'camera_error' => __('Camera access error', 'gps-courses'),
                    'scanner_stopped' => __('Scanner stopped.', 'gps-courses'),
                    'processing' => __('Processing...', 'gps-courses'),
                    'check_in_success' => __('Check-in Successful!', 'gps-courses'),
                    'check_in_failed' => __('Check-in Failed', 'gps-courses'),
                ],
            ]);
        }
    }

    /**
     * Get all seminars
     */
    public static function get_all_seminars($args = []) {
        $defaults = [
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_gps_seminar_year',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);
        return get_posts($args);
    }

    /**
     * Get seminar by ID
     */
    public static function get_seminar($seminar_id) {
        $post = get_post($seminar_id);

        if (!$post || $post->post_type !== 'gps_seminar') {
            return null;
        }

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'year' => get_post_meta($post->ID, '_gps_seminar_year', true),
            'capacity' => (int) get_post_meta($post->ID, '_gps_seminar_capacity', true) ?: 50,
            'enrolled_count' => self::get_enrollment_count($post->ID),
            'product_id' => get_post_meta($post->ID, '_gps_seminar_product_id', true),
            'status' => $post->post_status,
        ];
    }

    /**
     * Get enrollment count for a seminar
     */
    public static function get_enrollment_count($seminar_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_registrations
             WHERE seminar_id = %d AND status IN ('active', 'completed')",
            $seminar_id
        ));
    }

    /**
     * Get sessions for a seminar
     */
    public static function get_sessions($seminar_id, $orderby = 'session_date ASC') {
        global $wpdb;

        $order_clause = $orderby === 'session_number' ? 'ORDER BY session_number ASC' : 'ORDER BY session_date ASC, session_time_start ASC';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions
             WHERE seminar_id = %d
             {$order_clause}",
            $seminar_id
        ));
    }

    /**
     * Create a new session
     */
    public static function create_session($data) {
        global $wpdb;

        $defaults = [
            'seminar_id' => 0,
            'session_number' => 1,
            'session_date' => '',
            'session_time_start' => '18:00:00',
            'session_time_end' => '20:00:00',
            'topic' => '',
            'description' => '',
            'capacity' => 50,
            'registered_count' => 0,
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $wpdb->prefix . 'gps_seminar_sessions',
            $data,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a session
     */
    public static function update_session($session_id, $data) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'gps_seminar_sessions',
            $data,
            ['id' => $session_id],
            null,
            ['%d']
        );
    }

    /**
     * Delete a session
     */
    public static function delete_session($session_id) {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->prefix . 'gps_seminar_sessions',
            ['id' => $session_id],
            ['%d']
        );
    }

    /**
     * Get next upcoming session for a seminar
     */
    public static function get_next_session($seminar_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions
             WHERE seminar_id = %d
             AND session_date >= CURDATE()
             ORDER BY session_date ASC, session_time_start ASC
             LIMIT 1",
            $seminar_id
        ));
    }

    /**
     * Check if seminar is full
     */
    public static function is_full($seminar_id) {
        $seminar = self::get_seminar($seminar_id);
        return $seminar && $seminar['enrolled_count'] >= $seminar['capacity'];
    }

    /**
     * Get available spots
     */
    public static function get_available_spots($seminar_id) {
        $seminar = self::get_seminar($seminar_id);
        return $seminar ? max(0, $seminar['capacity'] - $seminar['enrolled_count']) : 0;
    }

    /**
     * AJAX: Create session
     */
    public static function ajax_create_session() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $session_id = self::create_session($_POST);

        if ($session_id) {
            wp_send_json_success(['session_id' => $session_id]);
        } else {
            wp_send_json_error(['message' => __('Failed to create session', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Delete session
     */
    public static function ajax_delete_session() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $session_id = (int) $_POST['session_id'];
        $result = self::delete_session($session_id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => __('Failed to delete session', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Update session
     */
    public static function ajax_update_session() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $session_id = (int) $_POST['session_id'];
        unset($_POST['session_id'], $_POST['action'], $_POST['nonce']);

        $result = self::update_session($session_id, $_POST);

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => __('Failed to update session', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Get seminar stats
     */
    public static function ajax_get_stats() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        $seminar_id = (int) $_POST['seminar_id'];
        $seminar = self::get_seminar($seminar_id);

        if (!$seminar) {
            wp_send_json_error(['message' => __('Seminar not found', 'gps-courses')]);
        }

        wp_send_json_success([
            'enrolled' => $seminar['enrolled_count'],
            'capacity' => $seminar['capacity'],
            'available' => self::get_available_spots($seminar_id),
            'sessions_count' => count(self::get_sessions($seminar_id)),
        ]);
    }

    /**
     * Render seminars list page
     */
    public static function render_seminars_page() {
        global $wpdb;

        // Get all seminars
        $seminars = get_posts([
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_gps_seminar_year',
            'order' => 'DESC',
        ]);

        // Calculate overall statistics
        $total_registrations = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_registrations WHERE status IN ('active', 'completed')"
        );

        $active_registrations = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_registrations WHERE status = 'active'"
        );

        $total_sessions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_sessions"
        );

        $total_attendance = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_attendance"
        );

        $total_credits = (int) $wpdb->get_var(
            "SELECT SUM(credits_awarded) FROM {$wpdb->prefix}gps_seminar_attendance"
        );

        // Get upcoming sessions
        $upcoming_sessions = $wpdb->get_results(
            "SELECT ss.*, p.post_title as seminar_name
             FROM {$wpdb->prefix}gps_seminar_sessions ss
             INNER JOIN {$wpdb->prefix}posts p ON ss.seminar_id = p.ID
             WHERE ss.session_date >= CURDATE()
             ORDER BY ss.session_date ASC
             LIMIT 5"
        );

        // Get recent registrations
        $recent_registrations = $wpdb->get_results(
            "SELECT sr.*, u.display_name, u.user_email, p.post_title as seminar_name
             FROM {$wpdb->prefix}gps_seminar_registrations sr
             LEFT JOIN {$wpdb->prefix}users u ON sr.user_id = u.ID
             INNER JOIN {$wpdb->prefix}posts p ON sr.seminar_id = p.ID
             ORDER BY sr.registration_date DESC
             LIMIT 5"
        );

        ?>
        <div class="wrap gps-seminars-wrap">
            <h1><?php _e('Monthly Seminars Dashboard', 'gps-courses'); ?></h1>

            <!-- Quick Actions -->
            <div style="margin: 20px 0;">
                <a href="<?php echo admin_url('post-new.php?post_type=gps_seminar'); ?>" class="button button-primary">
                    <?php _e('Create New Seminar', 'gps-courses'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=gps_seminar'); ?>" class="button">
                    <?php _e('Manage Seminars', 'gps-courses'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=gps-seminar-registrants'); ?>" class="button">
                    <?php _e('View Registrants', 'gps-courses'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=gps-seminar-attendance'); ?>" class="button">
                    <?php _e('Check-in Session', 'gps-courses'); ?>
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="gps-dashboard-cards">
                <div class="gps-card card-primary">
                    <h3><?php _e('Total Registrations', 'gps-courses'); ?></h3>
                    <div class="card-value"><?php echo number_format($total_registrations); ?></div>
                    <div class="card-label"><?php echo $active_registrations; ?> <?php _e('currently active', 'gps-courses'); ?></div>
                </div>

                <div class="gps-card card-success">
                    <h3><?php _e('Total Sessions', 'gps-courses'); ?></h3>
                    <div class="card-value"><?php echo number_format($total_sessions); ?></div>
                    <div class="card-label"><?php _e('across all seminars', 'gps-courses'); ?></div>
                </div>

                <div class="gps-card card-info">
                    <h3><?php _e('Total Attendance', 'gps-courses'); ?></h3>
                    <div class="card-value"><?php echo number_format($total_attendance); ?></div>
                    <div class="card-label"><?php _e('check-ins recorded', 'gps-courses'); ?></div>
                </div>

                <div class="gps-card card-warning">
                    <h3><?php _e('CE Credits Awarded', 'gps-courses'); ?></h3>
                    <div class="card-value"><?php echo number_format($total_credits); ?></div>
                    <div class="card-label"><?php _e('total credits issued', 'gps-courses'); ?></div>
                </div>
            </div>

            <!-- Seminars List -->
            <div class="gps-seminar-list">
                <h2 style="padding: 20px; margin: 0; border-bottom: 1px solid #ddd;">
                    <?php _e('Active Seminars', 'gps-courses'); ?>
                </h2>
                <?php if (!empty($seminars)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Seminar', 'gps-courses'); ?></th>
                                <th><?php _e('Year', 'gps-courses'); ?></th>
                                <th><?php _e('Enrollment', 'gps-courses'); ?></th>
                                <th><?php _e('Capacity', 'gps-courses'); ?></th>
                                <th><?php _e('Sessions', 'gps-courses'); ?></th>
                                <th><?php _e('Actions', 'gps-courses'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seminars as $seminar):
                                $year = get_post_meta($seminar->ID, '_gps_seminar_year', true);
                                $capacity = (int) get_post_meta($seminar->ID, '_gps_seminar_capacity', true) ?: 50;
                                $enrolled = self::get_enrollment_count($seminar->ID);
                                $sessions = self::get_sessions($seminar->ID);
                                $percentage = $capacity > 0 ? ($enrolled / $capacity) * 100 : 0;
                                $progress_class = $percentage < 50 ? 'progress-low' : ($percentage < 80 ? 'progress-medium' : 'progress-high');
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($seminar->post_title); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($year); ?></td>
                                    <td>
                                        <strong><?php echo $enrolled; ?></strong> / <?php echo $capacity; ?>
                                        <div class="progress-bar">
                                            <div class="progress-bar-fill <?php echo $progress_class; ?>"
                                                 style="width: <?php echo min(100, $percentage); ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $available = $capacity - $enrolled;
                                        echo $available > 0 ? $available : '<span style="color: #dc3232;">Full</span>';
                                        ?>
                                    </td>
                                    <td><?php echo count($sessions); ?> sessions</td>
                                    <td>
                                        <a href="<?php echo admin_url('post.php?post=' . $seminar->ID . '&action=edit'); ?>"
                                           class="button button-small"><?php _e('Edit', 'gps-courses'); ?></a>
                                        <a href="<?php echo admin_url('admin.php?page=gps-seminar-registrants&seminar_id=' . $seminar->ID); ?>"
                                           class="button button-small"><?php _e('Registrants', 'gps-courses'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 40px; text-align: center;">
                        <p><?php _e('No seminars found.', 'gps-courses'); ?></p>
                        <a href="<?php echo admin_url('post-new.php?post_type=gps_seminar'); ?>" class="button button-primary">
                            <?php _e('Create Your First Seminar', 'gps-courses'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">

                <!-- Upcoming Sessions -->
                <div class="gps-seminar-list">
                    <h2 style="padding: 20px; margin: 0; border-bottom: 1px solid #ddd;">
                        <?php _e('Upcoming Sessions', 'gps-courses'); ?>
                    </h2>
                    <?php if (!empty($upcoming_sessions)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><?php _e('Session', 'gps-courses'); ?></th>
                                    <th><?php _e('Date', 'gps-courses'); ?></th>
                                    <th><?php _e('Topic', 'gps-courses'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_sessions as $session): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($session->seminar_name); ?></strong><br>
                                            <small>Session <?php echo $session->session_number; ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($session->session_date)); ?><br>
                                            <small><?php echo date('g:i A', strtotime($session->session_time_start)); ?></small>
                                        </td>
                                        <td><?php echo esc_html($session->topic); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 20px;">
                            <p><?php _e('No upcoming sessions scheduled.', 'gps-courses'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Registrations -->
                <div class="gps-seminar-list">
                    <h2 style="padding: 20px; margin: 0; border-bottom: 1px solid #ddd;">
                        <?php _e('Recent Registrations', 'gps-courses'); ?>
                    </h2>
                    <?php if (!empty($recent_registrations)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><?php _e('Participant', 'gps-courses'); ?></th>
                                    <th><?php _e('Seminar', 'gps-courses'); ?></th>
                                    <th><?php _e('Date', 'gps-courses'); ?></th>
                                    <th><?php _e('Status', 'gps-courses'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_registrations as $reg): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($reg->display_name ?: 'Guest'); ?></strong><br>
                                            <small><?php echo esc_html($reg->user_email); ?></small>
                                        </td>
                                        <td><?php echo esc_html($reg->seminar_name); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($reg->registration_date)); ?></td>
                                        <td>
                                            <span class="registration-status status-<?php echo esc_attr($reg->status); ?>">
                                                <?php echo ucfirst($reg->status); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 20px;">
                            <p><?php _e('No recent registrations.', 'gps-courses'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Render registrants page
     */
    public static function render_registrants_page() {
        global $wpdb;

        // Get all seminars
        $seminars = get_posts([
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        // Get selected seminar
        $selected_seminar = isset($_GET['seminar_id']) ? (int) $_GET['seminar_id'] : 0;

        // Get registrations for selected seminar
        $registrations = [];
        if ($selected_seminar) {
            $registrations = $wpdb->get_results($wpdb->prepare(
                "SELECT sr.*, u.display_name, u.user_email
                 FROM {$wpdb->prefix}gps_seminar_registrations sr
                 LEFT JOIN {$wpdb->prefix}users u ON sr.user_id = u.ID
                 WHERE sr.seminar_id = %d
                 ORDER BY sr.registration_date DESC",
                $selected_seminar
            ));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Seminar Registrants', 'gps-courses'); ?></h1>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="gps-seminar-registrants">
                        <label for="seminar-filter"><?php _e('Filter by Seminar:', 'gps-courses'); ?></label>
                        <select name="seminar_id" id="seminar-filter" onchange="this.form.submit()">
                            <option value=""><?php _e('Select a seminar...', 'gps-courses'); ?></option>
                            <?php foreach ($seminars as $seminar): ?>
                                <option value="<?php echo $seminar->ID; ?>" <?php selected($selected_seminar, $seminar->ID); ?>>
                                    <?php echo esc_html($seminar->post_title); ?>
                                    (<?php echo get_post_meta($seminar->ID, '_gps_seminar_year', true); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <?php if ($selected_seminar && !empty($registrations)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'gps-courses'); ?></th>
                            <th><?php _e('Participant', 'gps-courses'); ?></th>
                            <th><?php _e('Email', 'gps-courses'); ?></th>
                            <th><?php _e('QR Code', 'gps-courses'); ?></th>
                            <th><?php _e('Registration Date', 'gps-courses'); ?></th>
                            <th><?php _e('Sessions', 'gps-courses'); ?></th>
                            <th><?php _e('Scans', 'gps-courses'); ?></th>
                            <th><?php _e('Status', 'gps-courses'); ?></th>
                            <th><?php _e('Order ID', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td><strong>#<?php echo $reg->id; ?></strong></td>
                            <td><?php echo esc_html($reg->display_name ?: 'Guest'); ?></td>
                            <td><?php echo esc_html($reg->user_email); ?></td>
                            <td>
                                <code style="font-size: 10px;"><?php echo esc_html($reg->qr_code); ?></code>
                                <?php if ($reg->qr_code_path): ?>
                                    <?php
                                    $upload_dir = wp_upload_dir();
                                    $qr_url = $upload_dir['baseurl'] . '/' . $reg->qr_code_path;
                                    ?>
                                    <br><a href="<?php echo esc_url($qr_url); ?>" target="_blank"><?php _e('View QR', 'gps-courses'); ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($reg->registration_date)); ?></td>
                            <td>
                                <strong><?php echo $reg->sessions_completed; ?></strong> / 10
                                <br><small><?php echo $reg->sessions_remaining; ?> remaining</small>
                            </td>
                            <td>
                                <span class="<?php echo $reg->qr_scan_count >= 10 ? 'error' : ''; ?>">
                                    <?php echo $reg->qr_scan_count; ?> / 10
                                </span>
                            </td>
                            <td>
                                <span class="status-<?php echo esc_attr($reg->status); ?>">
                                    <?php echo ucfirst($reg->status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($reg->order_id): ?>
                                    <a href="<?php echo admin_url('post.php?post=' . $reg->order_id . '&action=edit'); ?>">
                                        #<?php echo $reg->order_id; ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="description">
                    <?php printf(__('Showing %d registrations', 'gps-courses'), count($registrations)); ?>
                </p>

            <?php elseif ($selected_seminar): ?>
                <div class="notice notice-warning">
                    <p><?php _e('No registrations found for this seminar.', 'gps-courses'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php _e('Please select a seminar to view registrations.', 'gps-courses'); ?></p>
                </div>
            <?php endif; ?>

            <style>
                .status-active { color: #46b450; font-weight: 600; }
                .status-completed { color: #00a0d2; font-weight: 600; }
                .status-cancelled { color: #dc3232; font-weight: 600; }
                .tablenav { margin: 20px 0; }
                .tablenav select { margin-left: 10px; }
            </style>
        </div>
        <?php
    }

    /**
     * Render attendance page
     */
    public static function render_attendance_page() {
        global $wpdb;

        // Get all active seminars
        $seminars = get_posts([
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        // Get selected seminar and session
        $selected_seminar = isset($_GET['seminar_id']) ? (int) $_GET['seminar_id'] : 0;
        $selected_session = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

        // Get sessions for selected seminar
        $sessions = [];
        if ($selected_seminar) {
            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions
                 WHERE seminar_id = %d
                 ORDER BY session_date ASC",
                $selected_seminar
            ));
        }

        // Get attendance data if session is selected
        $attendance_list = [];
        $unchecked_list = [];
        $stats = null;
        if ($selected_session) {
            $stats = \GPSC\Seminar_Attendance::get_session_stats($selected_session);
            $attendance_list = \GPSC\Seminar_Attendance::get_session_attendance($selected_session);
            $unchecked_list = \GPSC\Seminar_Attendance::get_unchecked_registrants($selected_session);
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Session Attendance & Check-in', 'gps-courses'); ?></h1>

            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="" id="attendance-filter-form">
                        <input type="hidden" name="page" value="gps-seminar-attendance">

                        <label for="seminar-filter"><?php _e('Seminar:', 'gps-courses'); ?></label>
                        <select name="seminar_id" id="seminar-filter">
                            <option value=""><?php _e('Select seminar...', 'gps-courses'); ?></option>
                            <?php foreach ($seminars as $seminar): ?>
                                <option value="<?php echo $seminar->ID; ?>" <?php selected($selected_seminar, $seminar->ID); ?>>
                                    <?php echo esc_html($seminar->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="session-filter"><?php _e('Session:', 'gps-courses'); ?></label>
                        <select name="session_id" id="session-filter" <?php echo !$selected_seminar ? 'disabled' : ''; ?>>
                            <option value=""><?php _e('Select session...', 'gps-courses'); ?></option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo $session->id; ?>" <?php selected($selected_session, $session->id); ?>>
                                    Session <?php echo $session->session_number; ?> -
                                    <?php echo date('M j, Y', strtotime($session->session_date)); ?> -
                                    <?php echo esc_html($session->topic); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="button"><?php _e('Filter', 'gps-courses'); ?></button>
                    </form>
                </div>
            </div>

            <?php if ($selected_session && $stats): ?>

                <!-- Session Stats -->
                <div class="gps-session-stats">
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('Total Registrants', 'gps-courses'); ?></div>
                        <div class="stat-value"><?php echo $stats['total_registrants']; ?></div>
                    </div>
                    <div class="stat-box stat-checked">
                        <div class="stat-label"><?php _e('Checked In', 'gps-courses'); ?></div>
                        <div class="stat-value"><?php echo $stats['checked_in']; ?></div>
                    </div>
                    <div class="stat-box stat-unchecked">
                        <div class="stat-label"><?php _e('Not Checked In', 'gps-courses'); ?></div>
                        <div class="stat-value"><?php echo $stats['not_checked_in']; ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label"><?php _e('Attendance Rate', 'gps-courses'); ?></div>
                        <div class="stat-value"><?php echo $stats['attendance_rate']; ?>%</div>
                    </div>
                </div>

                <!-- QR Code Scanner -->
                <div class="gps-qr-scanner-box">
                    <h2>📱 <?php _e('Session Attendance & Check-in', 'gps-courses'); ?></h2>
                    <p style="color: #666; margin-bottom: 20px;">
                        <?php _e('Use manual entry or camera scanner to check in participants for this session.', 'gps-courses'); ?>
                    </p>

                    <!-- Scanner Tabs -->
                    <div class="scanner-tabs">
                        <button type="button" class="scanner-tab active" data-tab="manual">
                            ⌨️ <?php _e('Manual Entry', 'gps-courses'); ?>
                        </button>
                        <button type="button" class="scanner-tab" data-tab="camera">
                            📷 <?php _e('Camera Scanner', 'gps-courses'); ?>
                        </button>
                    </div>

                    <!-- Manual Entry Tab -->
                    <div class="scanner-tab-content active" id="manual-tab">
                        <div class="scanner-input-group">
                            <input type="text"
                                   id="qr-code-input"
                                   placeholder="<?php _e('Scan QR code or type code manually...', 'gps-courses'); ?>"
                                   class="regular-text"
                                   autofocus>
                            <button type="button" id="check-in-btn" class="button button-primary">
                                <?php _e('Check In', 'gps-courses'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Camera Scanner Tab -->
                    <div class="scanner-tab-content" id="camera-tab">
                        <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                            <?php _e('Click the button below to activate your camera and scan QR codes.', 'gps-courses'); ?>
                        </p>
                        <div class="camera-scanner-controls">
                            <button type="button" id="start-camera-btn" class="button button-primary">
                                📷 <?php _e('Start Camera Scanner', 'gps-courses'); ?>
                            </button>
                            <button type="button" id="stop-camera-btn" class="button" style="display: none;">
                                ⏸️ <?php _e('Stop Camera', 'gps-courses'); ?>
                            </button>
                        </div>
                        <div id="camera-reader" style="display: none;"></div>
                        <div id="camera-status" class="scanner-status"></div>
                    </div>

                    <!-- Check-in Result -->
                    <div id="check-in-result"></div>

                    <!-- Hidden field to store session ID for JavaScript -->
                    <input type="hidden" id="current-session-id" value="<?php echo esc_attr($selected_session); ?>">
                </div>

                <style>
                    .scanner-tabs {
                        display: flex;
                        gap: 10px;
                        margin-bottom: 20px;
                        border-bottom: 2px solid #e0e0e0;
                    }

                    .scanner-tab {
                        padding: 10px 20px;
                        background: none;
                        border: none;
                        border-bottom: 3px solid transparent;
                        cursor: pointer;
                        font-size: 14px;
                        font-weight: 600;
                        color: #666;
                        transition: all 0.3s ease;
                        margin-bottom: -2px;
                    }

                    .scanner-tab:hover {
                        color: #2271b1;
                    }

                    .scanner-tab.active {
                        color: #2271b1;
                        border-bottom-color: #2271b1;
                    }

                    .scanner-tab-content {
                        display: none;
                        padding: 20px 0;
                    }

                    .scanner-tab-content.active {
                        display: block;
                    }

                    .camera-scanner-controls {
                        margin-bottom: 20px;
                    }

                    #camera-reader {
                        width: 100%;
                        max-width: 600px;
                        margin: 20px auto;
                        border: 2px solid #e0e0e0;
                        border-radius: 8px;
                        overflow: hidden;
                    }

                    .scanner-status {
                        margin-top: 15px;
                        padding: 0;
                        border-radius: 4px;
                        font-weight: 600;
                        display: none;
                        text-align: center;
                        font-size: 14px;
                    }

                    .scanner-status:not(:empty) {
                        display: block;
                        padding: 12px;
                    }

                    .scanner-status.info {
                        background: #e5f5fa;
                        color: #00527c;
                        border: 1px solid #bee5eb;
                        display: block;
                    }

                    .scanner-status.error {
                        background: #f8d7da;
                        color: #721c24;
                        border: 1px solid #f5c6cb;
                        display: block;
                    }

                    .scanner-status.success {
                        background: #d4edda;
                        color: #155724;
                        border: 1px solid #c3e6cb;
                        display: block;
                    }

                    .camera-scanner-controls .button {
                        min-width: 180px;
                        height: 40px;
                        font-size: 14px;
                        font-weight: 600;
                    }

                    .camera-scanner-controls .button-primary {
                        background: #2271b1;
                        border-color: #2271b1;
                    }

                    .camera-scanner-controls .button-primary:hover {
                        background: #135e96;
                        border-color: #135e96;
                    }

                    .camera-scanner-controls .button-primary:disabled {
                        background: #7e8993 !important;
                        border-color: #7e8993 !important;
                        color: #fff !important;
                        cursor: not-allowed;
                    }
                </style>

                <!-- Checked In List -->
                <?php if (!empty($attendance_list)): ?>
                    <h2><?php _e('Checked In Participants', 'gps-courses'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Time', 'gps-courses'); ?></th>
                                <th><?php _e('Participant', 'gps-courses'); ?></th>
                                <th><?php _e('QR Code', 'gps-courses'); ?></th>
                                <th><?php _e('Credits', 'gps-courses'); ?></th>
                                <th><?php _e('Makeup', 'gps-courses'); ?></th>
                                <th><?php _e('Notes', 'gps-courses'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_list as $att):
                                $user = get_userdata($att->user_id);
                            ?>
                            <tr>
                                <td><?php echo date('g:i A', strtotime($att->checked_in_at)); ?></td>
                                <td><?php echo esc_html($user ? $user->display_name : 'Guest'); ?></td>
                                <td><code style="font-size: 10px;"><?php echo esc_html($att->qr_code); ?></code></td>
                                <td><strong><?php echo $att->credits_awarded; ?> CE</strong></td>
                                <td><?php echo $att->is_makeup ? '<span class="makeup-badge">Makeup</span>' : '—'; ?></td>
                                <td><?php echo esc_html($att->notes); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Not Checked In List -->
                <?php if (!empty($unchecked_list)): ?>
                    <h2><?php _e('Not Yet Checked In', 'gps-courses'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Participant', 'gps-courses'); ?></th>
                                <th><?php _e('Email', 'gps-courses'); ?></th>
                                <th><?php _e('QR Code', 'gps-courses'); ?></th>
                                <th><?php _e('Progress', 'gps-courses'); ?></th>
                                <th><?php _e('Actions', 'gps-courses'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unchecked_list as $reg): ?>
                            <tr>
                                <td><?php echo esc_html($reg->display_name); ?></td>
                                <td><?php echo esc_html($reg->user_email); ?></td>
                                <td><code style="font-size: 10px;"><?php echo esc_html($reg->qr_code); ?></code></td>
                                <td><?php echo $reg->sessions_completed; ?> / 10</td>
                                <td>
                                    <button type="button"
                                            class="button button-small manual-checkin-btn"
                                            data-registration-id="<?php echo $reg->id; ?>"
                                            data-name="<?php echo esc_attr($reg->display_name); ?>">
                                        <?php _e('Manual Check-in', 'gps-courses'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php elseif ($selected_seminar): ?>
                <div class="notice notice-info">
                    <p><?php _e('Please select a session to view attendance.', 'gps-courses'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php _e('Please select a seminar to begin.', 'gps-courses'); ?></p>
                </div>
            <?php endif; ?>

            <style>
                .gps-session-stats {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    margin: 20px 0 30px 0;
                }
                .stat-box {
                    background: #fff;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                    padding: 20px;
                    text-align: center;
                }
                .stat-box.stat-checked {
                    border-left: 4px solid #46b450;
                }
                .stat-box.stat-unchecked {
                    border-left: 4px solid #dc3232;
                }
                .stat-label {
                    font-size: 13px;
                    color: #646970;
                    margin-bottom: 8px;
                }
                .stat-value {
                    font-size: 32px;
                    font-weight: 600;
                    color: #1d2327;
                }
                .gps-qr-scanner-box {
                    background: #f0f0f1;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                    padding: 20px;
                    margin: 20px 0 30px 0;
                }
                .gps-qr-scanner-box h2 {
                    margin-top: 0;
                }
                .scanner-input-group {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 15px;
                }
                .scanner-input-group input {
                    flex: 1;
                    font-size: 16px;
                    padding: 10px 14px;
                    border: 2px solid #ddd;
                    border-radius: 4px;
                    transition: border-color 0.3s ease;
                }
                .scanner-input-group input:focus {
                    border-color: #2271b1;
                    outline: none;
                    box-shadow: 0 0 0 1px #2271b1;
                }
                .scanner-input-group .button {
                    min-width: 120px;
                    height: 42px;
                    font-weight: 600;
                }
                #check-in-result {
                    padding: 15px;
                    border-radius: 6px;
                    display: none;
                    margin-top: 20px;
                    font-weight: 600;
                    font-size: 15px;
                    text-align: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                #check-in-result.success {
                    background: #d4edda;
                    border: 2px solid #46b450;
                    color: #155724;
                    display: block;
                }
                #check-in-result.error {
                    background: #f8d7da;
                    border: 2px solid #dc3232;
                    color: #721c24;
                    display: block;
                }
                .makeup-badge {
                    background: #fcf3cd;
                    color: #886300;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                }
                .tablenav select {
                    margin: 0 5px;
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                var sessionId = <?php echo $selected_session ?: 0; ?>;

                // Auto-submit form when seminar changes
                $('#seminar-filter').on('change', function() {
                    $('#session-filter').prop('disabled', !$(this).val());
                    if ($(this).val()) {
                        $('#attendance-filter-form').submit();
                    }
                });

                // QR Code check-in
                $('#check-in-btn, #qr-code-input').on('click keypress', function(e) {
                    if (e.type === 'click' || e.which === 13) {
                        e.preventDefault();
                        var qrCode = $('#qr-code-input').val().trim();

                        if (!qrCode) {
                            showResult('error', '<?php _e('Please enter a QR code', 'gps-courses'); ?>');
                            return;
                        }

                        if (!sessionId) {
                            showResult('error', '<?php _e('No session selected', 'gps-courses'); ?>');
                            return;
                        }

                        performCheckIn(qrCode);
                    }
                });

                function performCheckIn(qrCode) {
                    $('#check-in-btn').prop('disabled', true).text('<?php _e('Processing...', 'gps-courses'); ?>');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'gps_scan_seminar_qr',
                            nonce: '<?php echo wp_create_nonce('gps_seminars_nonce'); ?>',
                            qr_code: qrCode,
                            session_id: sessionId
                        },
                        success: function(response) {
                            if (response.success) {
                                showResult('success', response.data.message + ' - ' + response.data.data.user_name);
                                $('#qr-code-input').val('');
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                showResult('error', response.data.message || '<?php _e('Check-in failed', 'gps-courses'); ?>');
                            }
                        },
                        error: function() {
                            showResult('error', '<?php _e('Connection error', 'gps-courses'); ?>');
                        },
                        complete: function() {
                            $('#check-in-btn').prop('disabled', false).text('<?php _e('Check In', 'gps-courses'); ?>');
                        }
                    });
                }

                // Manual check-in
                $('.manual-checkin-btn').on('click', function() {
                    var regId = $(this).data('registration-id');
                    var name = $(this).data('name');

                    if (!confirm('<?php _e('Check in', 'gps-courses'); ?> ' + name + '?')) {
                        return;
                    }

                    $(this).prop('disabled', true).text('<?php _e('Processing...', 'gps-courses'); ?>');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'gps_manual_seminar_checkin',
                            nonce: '<?php echo wp_create_nonce('gps_seminars_nonce'); ?>',
                            registration_id: regId,
                            session_id: sessionId,
                            is_makeup: false
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('<?php _e('Check-in successful!', 'gps-courses'); ?>');
                                location.reload();
                            } else {
                                alert(response.data.message || '<?php _e('Check-in failed', 'gps-courses'); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php _e('Connection error', 'gps-courses'); ?>');
                        }
                    });
                });

                function showResult(type, message) {
                    var $result = $('#check-in-result');
                    $result.removeClass('success error').addClass(type).html(message).show();
                    setTimeout(function() {
                        $result.fadeOut();
                    }, 5000);
                }
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render reports page
     */
    public static function render_reports_page() {
        global $wpdb;

        // Get selected seminar or show all
        $selected_seminar = isset($_GET['seminar_id']) ? (int) $_GET['seminar_id'] : 0;

        // Get all seminars for filter
        $seminars = get_posts([
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        // Build WHERE clause for seminar filter
        $where_clause = $selected_seminar ? "WHERE sr.seminar_id = $selected_seminar" : "";
        $and_or_where = $selected_seminar ? "AND" : "WHERE";

        // Get registration statistics
        $total_registrations = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_registrations sr $where_clause"
        );

        $active_registrations = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_registrations sr $where_clause $and_or_where status = 'active'"
        );

        $completed_registrations = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_registrations sr $where_clause $and_or_where status = 'completed'"
        );

        $cancelled_registrations = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_registrations sr $where_clause $and_or_where status = 'cancelled'"
        );

        // Get attendance statistics
        $total_checkins = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_attendance sa
             INNER JOIN {$wpdb->prefix}gps_seminar_registrations sr ON sa.registration_id = sr.id
             $where_clause"
        );

        $total_credits_awarded = (int) $wpdb->get_var(
            "SELECT SUM(sa.credits_awarded) FROM {$wpdb->prefix}gps_seminar_attendance sa
             INNER JOIN {$wpdb->prefix}gps_seminar_registrations sr ON sa.registration_id = sr.id
             $where_clause"
        );

        $makeup_sessions_used = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_attendance sa
             INNER JOIN {$wpdb->prefix}gps_seminar_registrations sr ON sa.registration_id = sr.id
             $where_clause $and_or_where sa.is_makeup = 1"
        );

        // Get session attendance rates
        $session_attendance = $wpdb->get_results(
            "SELECT ss.session_number, ss.topic, ss.session_date,
                    COUNT(DISTINCT sr.id) as total_registrants,
                    COUNT(sa.id) as checked_in,
                    ROUND((COUNT(sa.id) / COUNT(DISTINCT sr.id) * 100), 1) as attendance_rate
             FROM {$wpdb->prefix}gps_seminar_sessions ss
             LEFT JOIN {$wpdb->prefix}gps_seminar_registrations sr ON ss.seminar_id = sr.seminar_id
             LEFT JOIN {$wpdb->prefix}gps_seminar_attendance sa ON ss.id = sa.session_id AND sa.registration_id = sr.id
             " . ($selected_seminar ? "WHERE ss.seminar_id = $selected_seminar" : "") . "
             GROUP BY ss.id
             ORDER BY ss.session_date DESC
             LIMIT 20"
        );

        // Get top participants
        $top_participants = $wpdb->get_results(
            "SELECT u.display_name, u.user_email,
                    sr.sessions_completed,
                    COUNT(sa.id) as total_checkins,
                    SUM(sa.credits_awarded) as total_credits
             FROM {$wpdb->prefix}gps_seminar_registrations sr
             LEFT JOIN {$wpdb->prefix}users u ON sr.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}gps_seminar_attendance sa ON sr.id = sa.registration_id
             $where_clause
             GROUP BY sr.id
             ORDER BY sr.sessions_completed DESC, total_credits DESC
             LIMIT 10"
        );

        // Calculate revenue
        $revenue = $total_registrations * 750; // $750 per registration

        ?>
        <div class="wrap gps-seminars-wrap">
            <h1><?php _e('Seminar Reports & Analytics', 'gps-courses'); ?></h1>

            <!-- Filter -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="gps-seminar-reports">
                        <label for="seminar-filter"><?php _e('Filter by Seminar:', 'gps-courses'); ?></label>
                        <select name="seminar_id" id="seminar-filter" onchange="this.form.submit()">
                            <option value=""><?php _e('All Seminars', 'gps-courses'); ?></option>
                            <?php foreach ($seminars as $seminar): ?>
                                <option value="<?php echo $seminar->ID; ?>" <?php selected($selected_seminar, $seminar->ID); ?>>
                                    <?php echo esc_html($seminar->post_title); ?>
                                    (<?php echo get_post_meta($seminar->ID, '_gps_seminar_year', true); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Overview Statistics -->
            <div class="gps-dashboard-cards">
                <div class="gps-card card-primary">
                    <h3><?php _e('Total Registrations', 'gps-courses'); ?></h3>
                    <div class="card-value"><?php echo number_format($total_registrations); ?></div>
                    <div class="card-label">
                        <?php echo $active_registrations; ?> active,
                        <?php echo $completed_registrations; ?> completed
                    </div>
                </div>

                <div class="gps-card card-success">
                    <h3><?php _e('Total Check-ins', 'gps-courses'); ?></h3>
                    <div class="card-value"><?php echo number_format($total_checkins); ?></div>
                    <div class="card-label">
                        <?php echo $makeup_sessions_used; ?> makeup sessions
                    </div>
                </div>

                <div class="gps-card card-info">
                    <h3><?php _e('CE Credits Awarded', 'gps-courses'); ?></h3>
                    <div class="card-value"><?php echo number_format($total_credits_awarded); ?></div>
                    <div class="card-label">
                        <?php printf(__('Avg: %.1f per participant', 'gps-courses'),
                                     $total_registrations > 0 ? $total_credits_awarded / $total_registrations : 0); ?>
                    </div>
                </div>

                <div class="gps-card card-warning">
                    <h3><?php _e('Total Revenue', 'gps-courses'); ?></h3>
                    <div class="card-value">$<?php echo number_format($revenue); ?></div>
                    <div class="card-label">
                        <?php printf(__('%d @ $750 each', 'gps-courses'), $total_registrations); ?>
                    </div>
                </div>
            </div>

            <!-- Registration Status Breakdown -->
            <div class="gps-seminar-list">
                <h2 style="padding: 20px; margin: 0; border-bottom: 1px solid #ddd;">
                    <?php _e('Registration Status Breakdown', 'gps-courses'); ?>
                </h2>
                <div style="padding: 30px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                    <div style="text-align: center;">
                        <div style="font-size: 48px; font-weight: 700; color: #46b450;">
                            <?php echo $active_registrations; ?>
                        </div>
                        <div style="font-size: 14px; color: #646970; margin-top: 10px;">
                            <?php _e('Active Registrations', 'gps-courses'); ?>
                            <div class="progress-bar" style="margin-top: 10px;">
                                <div class="progress-bar-fill progress-high"
                                     style="width: <?php echo $total_registrations > 0 ? ($active_registrations / $total_registrations * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 48px; font-weight: 700; color: #00a0d2;">
                            <?php echo $completed_registrations; ?>
                        </div>
                        <div style="font-size: 14px; color: #646970; margin-top: 10px;">
                            <?php _e('Completed Programs', 'gps-courses'); ?>
                            <div class="progress-bar" style="margin-top: 10px;">
                                <div class="progress-bar-fill progress-high"
                                     style="width: <?php echo $total_registrations > 0 ? ($completed_registrations / $total_registrations * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 48px; font-weight: 700; color: #dc3232;">
                            <?php echo $cancelled_registrations; ?>
                        </div>
                        <div style="font-size: 14px; color: #646970; margin-top: 10px;">
                            <?php _e('Cancelled', 'gps-courses'); ?>
                            <div class="progress-bar" style="margin-top: 10px;">
                                <div class="progress-bar-fill progress-low"
                                     style="width: <?php echo $total_registrations > 0 ? ($cancelled_registrations / $total_registrations * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Session Attendance Rates -->
            <?php if (!empty($session_attendance)): ?>
                <div class="gps-seminar-list">
                    <h2 style="padding: 20px; margin: 0; border-bottom: 1px solid #ddd;">
                        <?php _e('Session Attendance Rates', 'gps-courses'); ?>
                    </h2>
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Session', 'gps-courses'); ?></th>
                                <th><?php _e('Date', 'gps-courses'); ?></th>
                                <th><?php _e('Topic', 'gps-courses'); ?></th>
                                <th><?php _e('Registrants', 'gps-courses'); ?></th>
                                <th><?php _e('Checked In', 'gps-courses'); ?></th>
                                <th><?php _e('Attendance Rate', 'gps-courses'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($session_attendance as $session):
                                $rate = (float) $session->attendance_rate;
                                $rate_class = $rate >= 80 ? 'progress-high' : ($rate >= 60 ? 'progress-medium' : 'progress-low');
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $session->session_number; ?></strong></td>
                                    <td><?php echo date('M j, Y', strtotime($session->session_date)); ?></td>
                                    <td><?php echo esc_html($session->topic); ?></td>
                                    <td><?php echo $session->total_registrants; ?></td>
                                    <td><?php echo $session->checked_in; ?></td>
                                    <td>
                                        <strong><?php echo $rate; ?>%</strong>
                                        <div class="progress-bar">
                                            <div class="progress-bar-fill <?php echo $rate_class; ?>"
                                                 style="width: <?php echo $rate; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Top Participants -->
            <?php if (!empty($top_participants)): ?>
                <div class="gps-seminar-list">
                    <h2 style="padding: 20px; margin: 0; border-bottom: 1px solid #ddd;">
                        <?php _e('Top Participants', 'gps-courses'); ?>
                    </h2>
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Participant', 'gps-courses'); ?></th>
                                <th><?php _e('Email', 'gps-courses'); ?></th>
                                <th><?php _e('Sessions Completed', 'gps-courses'); ?></th>
                                <th><?php _e('Check-ins', 'gps-courses'); ?></th>
                                <th><?php _e('CE Credits', 'gps-courses'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_participants as $participant): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($participant->display_name ?: 'Guest'); ?></strong></td>
                                    <td><?php echo esc_html($participant->user_email); ?></td>
                                    <td><?php echo $participant->sessions_completed; ?> / 10</td>
                                    <td><?php echo $participant->total_checkins; ?></td>
                                    <td><strong><?php echo $participant->total_credits; ?> CE</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Export Options -->
            <div style="margin: 20px 0; padding: 20px; background: #f6f7f7; border-radius: 8px;">
                <h3><?php _e('Export Reports', 'gps-courses'); ?></h3>
                <p><?php _e('Generate detailed reports for analysis:', 'gps-courses'); ?></p>
                <a href="#" class="button button-secondary export-registrants-btn"
                   data-seminar-id="<?php echo $selected_seminar; ?>">
                    <?php _e('Export All Registrations (CSV)', 'gps-courses'); ?>
                </a>
                <a href="#" class="button button-secondary export-attendance-btn"
                   data-seminar-id="<?php echo $selected_seminar; ?>">
                    <?php _e('Export Attendance Records (CSV)', 'gps-courses'); ?>
                </a>
            </div>

        </div>
        <?php
    }

    /**
     * Render notifications page
     */
    public static function render_notifications_page() {
        global $wpdb;

        // Handle test email sending
        if (isset($_POST['send_test_email']) && check_admin_referer('gps_test_email', 'gps_test_email_nonce')) {
            $email_type = sanitize_text_field($_POST['email_type']);
            $test_user_id = (int) $_POST['test_user_id'];

            $result = false;
            switch ($email_type) {
                case 'registration':
                    // Find a registration for testing
                    $registration = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}gps_seminar_registrations WHERE user_id = %d LIMIT 1",
                        $test_user_id
                    ));
                    if ($registration) {
                        $result = Seminar_Notifications::send_registration_confirmation(
                            $registration->id,
                            $registration->user_id,
                            $registration->seminar_id,
                            $registration->order_id
                        );
                    }
                    break;

                case 'reminder':
                    // Find next upcoming session
                    $session = $wpdb->get_row(
                        "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions
                         WHERE session_date >= CURDATE()
                         ORDER BY session_date ASC LIMIT 1"
                    );
                    if ($session) {
                        $registration = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}gps_seminar_registrations
                             WHERE user_id = %d AND seminar_id = %d AND status = 'active' LIMIT 1",
                            $test_user_id,
                            $session->seminar_id
                        ));
                        if ($registration) {
                            $result = Seminar_Notifications::send_session_reminder($registration->id, $session->id, 7);
                        }
                    }
                    break;

                case 'credits':
                    $registration = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}gps_seminar_registrations WHERE user_id = %d LIMIT 1",
                        $test_user_id
                    ));
                    if ($registration) {
                        $result = Seminar_Notifications::send_credits_notification(
                            $test_user_id,
                            $registration->seminar_id,
                            1,
                            2
                        );
                    }
                    break;
            }

            if ($result) {
                echo '<div class="notice notice-success"><p>' . __('Test email sent successfully!', 'gps-courses') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Failed to send test email.', 'gps-courses') . '</p></div>';
            }
        }

        // Get cron info
        $next_cron = wp_next_scheduled('gps_seminar_daily_cron');
        $cron_status = $next_cron ? date('F j, Y g:i A', $next_cron) : __('Not scheduled', 'gps-courses');

        // Get recent notification activity (would need logging table for full implementation)
        $recent_registrations = $wpdb->get_results(
            "SELECT sr.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}gps_seminar_registrations sr
             LEFT JOIN {$wpdb->prefix}users u ON sr.user_id = u.ID
             ORDER BY sr.registration_date DESC LIMIT 5"
        );

        // Get upcoming reminders
        $upcoming_sessions = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions
             WHERE session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             ORDER BY session_date ASC"
        );

        ?>
        <div class="wrap">
            <h1><?php _e('Email Notifications', 'gps-courses'); ?></h1>

            <style>
                .notification-card {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                .notification-card h2 {
                    margin-top: 0;
                    color: #2271b1;
                    font-size: 18px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 5px 12px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: bold;
                }
                .status-active {
                    background: #d4edda;
                    color: #155724;
                }
                .status-pending {
                    background: #fff3cd;
                    color: #856404;
                }
                .test-email-form {
                    background: #f9f9f9;
                    padding: 20px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
            </style>

            <!-- Cron Status -->
            <div class="notification-card">
                <h2><?php _e('Automated Notifications Status', 'gps-courses'); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><strong><?php _e('Daily Cron Job', 'gps-courses'); ?></strong></td>
                        <td>
                            <span class="status-badge <?php echo $next_cron ? 'status-active' : 'status-pending'; ?>">
                                <?php echo $next_cron ? __('Scheduled', 'gps-courses') : __('Not Scheduled', 'gps-courses'); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Next Run', 'gps-courses'); ?></strong></td>
                        <td><?php echo esc_html($cron_status); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Active Reminders', 'gps-courses'); ?></strong></td>
                        <td><?php echo count($upcoming_sessions); ?> <?php _e('sessions in next 14 days', 'gps-courses'); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Email Types -->
            <div class="notification-card">
                <h2><?php _e('Email Types Configured', 'gps-courses'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Email Type', 'gps-courses'); ?></th>
                            <th><?php _e('Trigger', 'gps-courses'); ?></th>
                            <th><?php _e('Status', 'gps-courses'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Registration Confirmation', 'gps-courses'); ?></strong></td>
                            <td><?php _e('Sent immediately after registration with QR code', 'gps-courses'); ?></td>
                            <td><span class="status-badge status-active"><?php _e('Active', 'gps-courses'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Session Reminder (14 days)', 'gps-courses'); ?></strong></td>
                            <td><?php _e('Sent 2 weeks before each session', 'gps-courses'); ?></td>
                            <td><span class="status-badge status-active"><?php _e('Active', 'gps-courses'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Session Reminder (7 days)', 'gps-courses'); ?></strong></td>
                            <td><?php _e('Sent 1 week before each session', 'gps-courses'); ?></td>
                            <td><span class="status-badge status-active"><?php _e('Active', 'gps-courses'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Session Reminder (1 day)', 'gps-courses'); ?></strong></td>
                            <td><?php _e('Sent 1 day before each session', 'gps-courses'); ?></td>
                            <td><span class="status-badge status-active"><?php _e('Active', 'gps-courses'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('CE Credits Awarded', 'gps-courses'); ?></strong></td>
                            <td><?php _e('Sent after successful check-in', 'gps-courses'); ?></td>
                            <td><span class="status-badge status-active"><?php _e('Active', 'gps-courses'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Missed Session Alert', 'gps-courses'); ?></strong></td>
                            <td><?php _e('Sent day after session if not checked in', 'gps-courses'); ?></td>
                            <td><span class="status-badge status-active"><?php _e('Active', 'gps-courses'); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Test Email Form -->
            <div class="notification-card">
                <h2><?php _e('Send Test Email', 'gps-courses'); ?></h2>
                <div class="test-email-form">
                    <form method="post">
                        <?php wp_nonce_field('gps_test_email', 'gps_test_email_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="email_type"><?php _e('Email Type', 'gps-courses'); ?></label></th>
                                <td>
                                    <select name="email_type" id="email_type" required>
                                        <option value="registration"><?php _e('Registration Confirmation', 'gps-courses'); ?></option>
                                        <option value="reminder"><?php _e('Session Reminder', 'gps-courses'); ?></option>
                                        <option value="credits"><?php _e('CE Credits Awarded', 'gps-courses'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="test_user_id"><?php _e('Test User ID', 'gps-courses'); ?></label></th>
                                <td>
                                    <input type="number" name="test_user_id" id="test_user_id" value="<?php echo get_current_user_id(); ?>" required>
                                    <p class="description"><?php _e('Enter a user ID that has a seminar registration', 'gps-courses'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <p>
                            <button type="submit" name="send_test_email" class="button button-primary">
                                <?php _e('Send Test Email', 'gps-courses'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="notification-card">
                <h2><?php _e('Recent Registrations (Last 5)', 'gps-courses'); ?></h2>
                <?php if ($recent_registrations): ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'gps-courses'); ?></th>
                                <th><?php _e('Participant', 'gps-courses'); ?></th>
                                <th><?php _e('Email', 'gps-courses'); ?></th>
                                <th><?php _e('Confirmation Sent', 'gps-courses'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_registrations as $reg): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($reg->registration_date)); ?></td>
                                    <td><?php echo esc_html($reg->display_name ?: 'Guest'); ?></td>
                                    <td><?php echo esc_html($reg->user_email); ?></td>
                                    <td><span class="status-badge status-active"><?php _e('Yes', 'gps-courses'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No recent registrations found.', 'gps-courses'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Upcoming Reminders -->
            <div class="notification-card">
                <h2><?php _e('Upcoming Session Reminders', 'gps-courses'); ?></h2>
                <?php if ($upcoming_sessions): ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Session', 'gps-courses'); ?></th>
                                <th><?php _e('Date', 'gps-courses'); ?></th>
                                <th><?php _e('Topic', 'gps-courses'); ?></th>
                                <th><?php _e('Reminders', 'gps-courses'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_sessions as $session):
                                $days_until = (strtotime($session->session_date) - strtotime(current_time('Y-m-d'))) / 86400;
                                $reminder_status = [];
                                if ($days_until >= 14) $reminder_status[] = '14-day pending';
                                if ($days_until >= 7) $reminder_status[] = '7-day pending';
                                if ($days_until >= 1) $reminder_status[] = '1-day pending';
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $session->session_number; ?></strong></td>
                                    <td><?php echo date('F j, Y', strtotime($session->session_date)); ?></td>
                                    <td><?php echo esc_html($session->topic); ?></td>
                                    <td><?php echo implode(', ', $reminder_status); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No upcoming sessions in the next 14 days.', 'gps-courses'); ?></p>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    /**
     * Render waitlist management page
     */
    public static function render_waitlist_page() {
        global $wpdb;

        // Get all seminars
        $seminars = get_posts([
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        // Get selected seminar
        $selected_seminar = isset($_GET['seminar_id']) ? (int) $_GET['seminar_id'] : 0;

        // Get waitlist for selected seminar
        $waitlist_entries = [];
        if ($selected_seminar) {
            $waitlist_entries = Seminar_Waitlist::get_seminar_waitlist($selected_seminar, null); // All statuses
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Seminar Waitlist Management', 'gps-courses'); ?></h1>

            <!-- Seminar Filter -->
            <div class="gps-filter-bar">
                <form method="get" action="">
                    <input type="hidden" name="page" value="gps-seminar-waitlist">
                    <select name="seminar_id" id="seminar_id" onchange="this.form.submit()">
                        <option value=""><?php _e('Select a seminar...', 'gps-courses'); ?></option>
                        <?php foreach ($seminars as $seminar):
                            $year = get_post_meta($seminar->ID, '_gps_seminar_year', true);
                            $capacity = (int) get_post_meta($seminar->ID, '_gps_seminar_capacity', true) ?: 50;
                            $enrolled = self::get_enrollment_count($seminar->ID);
                            $available = $capacity - $enrolled;
                            $waitlist_count = Seminar_Waitlist::get_waitlist_count($seminar->ID);
                        ?>
                            <option value="<?php echo $seminar->ID; ?>" <?php selected($selected_seminar, $seminar->ID); ?>>
                                <?php echo esc_html($seminar->post_title . ' (' . $year . ')'); ?>
                                - <?php echo $enrolled . '/' . $capacity; ?> enrolled
                                <?php if ($waitlist_count > 0): ?>
                                    (<?php echo $waitlist_count; ?> waiting)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_seminar): ?>
                <?php
                $seminar = get_post($selected_seminar);
                $year = get_post_meta($selected_seminar, '_gps_seminar_year', true);
                $capacity = (int) get_post_meta($selected_seminar, '_gps_seminar_capacity', true) ?: 50;
                $enrolled = self::get_enrollment_count($selected_seminar);
                $available = $capacity - $enrolled;
                $is_full = $available <= 0;
                ?>

                <div class="gps-seminar-card">
                    <div class="gps-card-header">
                        <h2><?php echo esc_html($seminar->post_title . ' (' . $year . ')'); ?></h2>
                        <div class="gps-capacity-info">
                            <span class="capacity-label"><?php _e('Capacity:', 'gps-courses'); ?></span>
                            <span class="capacity-value <?php echo $is_full ? 'full' : ''; ?>">
                                <?php echo $enrolled . ' / ' . $capacity; ?>
                            </span>
                            <?php if ($is_full): ?>
                                <span class="capacity-status full"><?php _e('FULL', 'gps-courses'); ?></span>
                            <?php else: ?>
                                <span class="capacity-status available"><?php echo sprintf(__('%d spots available', 'gps-courses'), $available); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($available > 0): ?>
                        <div class="gps-notice notice-info">
                            <p>
                                <strong><?php _e('Note:', 'gps-courses'); ?></strong>
                                <?php printf(__('This seminar has %d spots available. You can manually notify people on the waitlist below.', 'gps-courses'), $available); ?>
                            </p>
                            <?php if (!empty($waitlist_entries)): ?>
                                <button type="button" class="button button-primary gps-notify-next-waitlist" data-seminar-id="<?php echo $selected_seminar; ?>">
                                    <?php _e('Notify Next Person on Waitlist', 'gps-courses'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($waitlist_entries)): ?>
                        <table class="gps-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Position', 'gps-courses'); ?></th>
                                    <th><?php _e('Name', 'gps-courses'); ?></th>
                                    <th><?php _e('Email', 'gps-courses'); ?></th>
                                    <th><?php _e('Phone', 'gps-courses'); ?></th>
                                    <th><?php _e('Joined', 'gps-courses'); ?></th>
                                    <th><?php _e('Status', 'gps-courses'); ?></th>
                                    <th><?php _e('Expires', 'gps-courses'); ?></th>
                                    <th><?php _e('Actions', 'gps-courses'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waitlist_entries as $entry): ?>
                                    <tr class="waitlist-status-<?php echo esc_attr($entry->status); ?>">
                                        <td><strong>#<?php echo $entry->position; ?></strong></td>
                                        <td><?php echo esc_html(trim($entry->first_name . ' ' . $entry->last_name) ?: 'N/A'); ?></td>
                                        <td><?php echo esc_html($entry->email); ?></td>
                                        <td><?php echo esc_html($entry->phone ?: 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($entry->created_at)); ?></td>
                                        <td>
                                            <span class="waitlist-badge status-<?php echo esc_attr($entry->status); ?>">
                                                <?php echo ucfirst($entry->status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($entry->status === 'notified' && $entry->expires_at): ?>
                                                <?php
                                                $expires = strtotime($entry->expires_at);
                                                $now = current_time('timestamp');
                                                $hours_left = round(($expires - $now) / 3600);
                                                ?>
                                                <span class="expires-time <?php echo $hours_left < 12 ? 'urgent' : ''; ?>">
                                                    <?php echo date('M j, g:i A', $expires); ?>
                                                    <small>(<?php echo $hours_left; ?>h left)</small>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($entry->status === 'waiting'): ?>
                                                <button type="button" class="button button-small gps-notify-waitlist-entry" data-waitlist-id="<?php echo $entry->id; ?>" data-seminar-id="<?php echo $selected_seminar; ?>">
                                                    <?php _e('Notify', 'gps-courses'); ?>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (in_array($entry->status, ['waiting', 'notified'])): ?>
                                                <button type="button" class="button button-small button-link-delete gps-remove-waitlist" data-waitlist-id="<?php echo $entry->id; ?>">
                                                    <?php _e('Remove', 'gps-courses'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <style>
                            .waitlist-status-waiting {
                                background: #f9f9f9;
                            }
                            .waitlist-status-notified {
                                background: #fff9e6;
                            }
                            .waitlist-status-converted,
                            .waitlist-status-expired,
                            .waitlist-status-removed {
                                opacity: 0.6;
                            }
                            .waitlist-badge {
                                padding: 4px 10px;
                                border-radius: 12px;
                                font-size: 11px;
                                font-weight: 600;
                                text-transform: uppercase;
                            }
                            .waitlist-badge.status-waiting {
                                background: #e5f5fa;
                                color: #00527c;
                            }
                            .waitlist-badge.status-notified {
                                background: #fff9e6;
                                color: #856404;
                            }
                            .waitlist-badge.status-converted {
                                background: #d4edda;
                                color: #155724;
                            }
                            .waitlist-badge.status-expired {
                                background: #f8d7da;
                                color: #721c24;
                            }
                            .waitlist-badge.status-removed {
                                background: #e2e3e5;
                                color: #383d41;
                            }
                            .expires-time.urgent {
                                color: #dc3232;
                                font-weight: 600;
                            }
                            .gps-capacity-info {
                                display: flex;
                                align-items: center;
                                gap: 10px;
                            }
                            .capacity-status {
                                padding: 4px 12px;
                                border-radius: 4px;
                                font-size: 12px;
                                font-weight: 600;
                                text-transform: uppercase;
                            }
                            .capacity-status.full {
                                background: #f8d7da;
                                color: #721c24;
                            }
                            .capacity-status.available {
                                background: #d4edda;
                                color: #155724;
                            }
                            .capacity-value.full {
                                color: #dc3232;
                                font-weight: 700;
                            }
                            .gps-notice {
                                padding: 15px;
                                margin: 20px 0;
                                border-left: 4px solid #007bff;
                                background: #e5f5fa;
                            }
                        </style>

                        <script>
                        jQuery(document).ready(function($) {
                            // Notify next person on waitlist
                            $('.gps-notify-next-waitlist').on('click', function() {
                                const $btn = $(this);
                                const seminarId = $btn.data('seminar-id');

                                if (!confirm('<?php _e('Notify the next person on the waitlist?', 'gps-courses'); ?>')) {
                                    return;
                                }

                                $btn.prop('disabled', true).text('<?php _e('Notifying...', 'gps-courses'); ?>');

                                $.ajax({
                                    url: ajaxurl,
                                    method: 'POST',
                                    data: {
                                        action: 'gps_notify_waitlist',
                                        seminar_id: seminarId,
                                        nonce: '<?php echo wp_create_nonce('gps_courses_nonce'); ?>'
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            alert(response.data.message);
                                            location.reload();
                                        } else {
                                            alert(response.data.message);
                                            $btn.prop('disabled', false).text('<?php _e('Notify Next Person on Waitlist', 'gps-courses'); ?>');
                                        }
                                    },
                                    error: function() {
                                        alert('<?php _e('An error occurred. Please try again.', 'gps-courses'); ?>');
                                        $btn.prop('disabled', false).text('<?php _e('Notify Next Person on Waitlist', 'gps-courses'); ?>');
                                    }
                                });
                            });

                            // Notify specific waitlist entry
                            $('.gps-notify-waitlist-entry').on('click', function() {
                                const $btn = $(this);
                                const seminarId = $btn.data('seminar-id');

                                if (!confirm('<?php _e('Notify this person?', 'gps-courses'); ?>')) {
                                    return;
                                }

                                $btn.prop('disabled', true).text('<?php _e('Notifying...', 'gps-courses'); ?>');

                                $.ajax({
                                    url: ajaxurl,
                                    method: 'POST',
                                    data: {
                                        action: 'gps_notify_waitlist',
                                        seminar_id: seminarId,
                                        nonce: '<?php echo wp_create_nonce('gps_courses_nonce'); ?>'
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            alert(response.data.message);
                                            location.reload();
                                        } else {
                                            alert(response.data.message);
                                            $btn.prop('disabled', false).text('<?php _e('Notify', 'gps-courses'); ?>');
                                        }
                                    },
                                    error: function() {
                                        alert('<?php _e('An error occurred. Please try again.', 'gps-courses'); ?>');
                                        $btn.prop('disabled', false).text('<?php _e('Notify', 'gps-courses'); ?>');
                                    }
                                });
                            });

                            // Remove from waitlist
                            $('.gps-remove-waitlist').on('click', function() {
                                const $btn = $(this);
                                const waitlistId = $btn.data('waitlist-id');

                                if (!confirm('<?php _e('Remove this person from the waitlist?', 'gps-courses'); ?>')) {
                                    return;
                                }

                                $btn.prop('disabled', true).text('<?php _e('Removing...', 'gps-courses'); ?>');

                                $.ajax({
                                    url: ajaxurl,
                                    method: 'POST',
                                    data: {
                                        action: 'gps_remove_from_waitlist',
                                        waitlist_id: waitlistId,
                                        nonce: '<?php echo wp_create_nonce('gps_courses_nonce'); ?>'
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            alert(response.data.message);
                                            location.reload();
                                        } else {
                                            alert(response.data.message);
                                            $btn.prop('disabled', false).text('<?php _e('Remove', 'gps-courses'); ?>');
                                        }
                                    },
                                    error: function() {
                                        alert('<?php _e('An error occurred. Please try again.', 'gps-courses'); ?>');
                                        $btn.prop('disabled', false).text('<?php _e('Remove', 'gps-courses'); ?>');
                                    }
                                });
                            });
                        });
                        </script>

                    <?php else: ?>
                        <div class="gps-no-data">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                            <h3><?php _e('No Waitlist Entries', 'gps-courses'); ?></h3>
                            <p><?php _e('There are no people on the waitlist for this seminar.', 'gps-courses'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="gps-no-selection">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    <h3><?php _e('Select a Seminar', 'gps-courses'); ?></h3>
                    <p><?php _e('Choose a seminar from the dropdown above to view and manage its waitlist.', 'gps-courses'); ?></p>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
