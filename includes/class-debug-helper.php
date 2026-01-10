<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Debug Helper
 * Temporary admin page to debug database issues
 */
class Debug_Helper {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_create_enrollments']);
        add_action('admin_init', [__CLASS__, 'handle_recreate_tables']);
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Debug Info', 'gps-courses'),
            __('Debug Info', 'gps-courses'),
            'manage_options',
            'gps-debug',
            [__CLASS__, 'render_debug_page']
        );
    }

    public static function handle_recreate_tables() {
        if (!isset($_POST['gps_debug_recreate_tables'])) {
            return;
        }

        check_admin_referer('gps_debug_recreate_nonce');

        if (!current_user_can('activate_plugins')) {
            wp_die(__('You do not have permission to perform this action.', 'gps-courses'));
        }

        // Recreate all tables
        Activator::recreate_tables();

        // Redirect back with success message
        $redirect_url = add_query_arg([
            'page' => 'gps-debug',
            'tables_recreated' => '1'
        ], admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }

    public static function handle_create_enrollments() {
        if (!isset($_POST['gps_create_enrollments'])) {
            return;
        }

        check_admin_referer('gps_create_enrollments_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'gps-courses'));
        }

        global $wpdb;

        // Force MySQL to use buffered queries to prevent "Commands out of sync" error
        $wpdb->flush();

        // Get all tickets that don't have enrollments
        // Using DISTINCT and simpler query to avoid connection issues
        $ticket_ids = $wpdb->get_col("
            SELECT DISTINCT t.id
            FROM {$wpdb->prefix}gps_tickets t
            LEFT JOIN {$wpdb->prefix}gps_enrollments e ON t.order_id = e.order_id AND t.event_id = e.session_id
            WHERE e.id IS NULL
        ");

        $created = 0;
        $failed = 0;

        // Process tickets one by one to avoid connection issues
        foreach ($ticket_ids as $ticket_id) {
            // Get ticket data
            $ticket = $wpdb->get_row($wpdb->prepare(
                "SELECT id, user_id, event_id, order_id FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
                $ticket_id
            ));

            if (!$ticket) {
                $failed++;
                continue;
            }

            // Create enrollment
            $result = $wpdb->insert(
                $wpdb->prefix . 'gps_enrollments',
                [
                    'user_id' => $ticket->user_id,
                    'session_id' => $ticket->event_id,
                    'order_id' => $ticket->order_id,
                    'status' => 'completed',
                    'attended' => 0,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%s', '%d', '%s']
            );

            if ($result) {
                $created++;
            } else {
                $failed++;
            }

            // Flush after each operation to prevent connection issues
            $wpdb->flush();
        }

        $redirect_url = add_query_arg([
            'page' => 'gps-debug',
            'enrollments_created' => $created,
            'enrollments_failed' => $failed
        ], admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }

    public static function render_debug_page() {
        global $wpdb;

        // Show success message if tables were recreated
        if (isset($_GET['tables_recreated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo '<strong>' . __('Success!', 'gps-courses') . '</strong> ';
            echo __('All database tables have been recreated successfully.', 'gps-courses');
            echo '</p></div>';
        }

        // Show success message if enrollments were created
        if (isset($_GET['enrollments_created'])) {
            $created = (int) $_GET['enrollments_created'];
            $failed = isset($_GET['enrollments_failed']) ? (int) $_GET['enrollments_failed'] : 0;

            echo '<div class="notice notice-success is-dismissible"><p>';
            echo sprintf(__('Created %d enrollments successfully.', 'gps-courses'), $created);
            if ($failed > 0) {
                echo ' ' . sprintf(__('%d failed.', 'gps-courses'), $failed);
            }
            echo '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php _e('GPS Courses Debug Information', 'gps-courses'); ?></h1>

            <!-- Recreate Database Tables -->
            <div class="card" style="border-left: 4px solid #d63638;">
                <h2>⚠️ Recreate Database Tables</h2>
                <p><?php _e('If you\'re experiencing database errors or missing tables, you can recreate all database tables here.', 'gps-courses'); ?></p>
                <p><strong style="color: #d63638;"><?php _e('Warning:', 'gps-courses'); ?></strong> <?php _e('This will DROP all existing GPS Courses tables and recreate them. All data will be lost!', 'gps-courses'); ?></p>
                <form method="post" action="" onsubmit="return confirm('⚠️ WARNING: This will DELETE all GPS Courses data!\n\nAre you absolutely sure you want to continue?');">
                    <?php wp_nonce_field('gps_debug_recreate_nonce'); ?>
                    <button type="submit" name="gps_debug_recreate_tables" class="button button-primary" style="background: #d63638; border-color: #d63638;">
                        <span class="dashicons dashicons-database-remove" style="margin-top: 3px;"></span>
                        <?php _e('Recreate All Database Tables', 'gps-courses'); ?>
                    </button>
                </form>
            </div>

            <!-- Fix Missing Enrollments -->
            <div class="card">
                <h2>Fix Missing Enrollments</h2>
                <?php
                // Count tickets without enrollments
                $missing_enrollments = $wpdb->get_var("
                    SELECT COUNT(*)
                    FROM {$wpdb->prefix}gps_tickets t
                    LEFT JOIN {$wpdb->prefix}gps_enrollments e ON t.id = e.order_id AND t.event_id = e.session_id
                    WHERE e.id IS NULL
                ");
                ?>
                <p><?php echo sprintf(__('Tickets without enrollments: %d', 'gps-courses'), $missing_enrollments); ?></p>
                <?php if ($missing_enrollments > 0): ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('gps_create_enrollments_nonce'); ?>
                        <button type="submit" name="gps_create_enrollments" class="button button-primary">
                            <?php _e('Create Missing Enrollments', 'gps-courses'); ?>
                        </button>
                        <p class="description"><?php _e('This will create enrollment records for all existing tickets that don\'t have them yet.', 'gps-courses'); ?></p>
                    </form>
                <?php else: ?>
                    <p class="description"><?php _e('All tickets have enrollments!', 'gps-courses'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Database Prefix -->
            <div class="card">
                <h2>Database Prefix</h2>
                <p><strong>Current Prefix:</strong> <code><?php echo $wpdb->prefix; ?></code></p>
            </div>

            <!-- Tables Check -->
            <div class="card">
                <h2>Database Tables Status</h2>
                <?php
                $tables = [
                    'gps_tickets' => 'Tickets',
                    'gps_enrollments' => 'Enrollments',
                    'gps_attendance' => 'Attendance',
                    'gps_ce_ledger' => 'CE Credits Ledger',
                    'gps_waitlist' => 'Course Waitlist',
                    'gps_certificates' => 'Certificates',
                    'gps_seminar_registrations' => 'Seminar Registrations',
                    'gps_seminar_sessions' => 'Seminar Sessions',
                    'gps_seminar_attendance' => 'Seminar Attendance',
                    'gps_seminar_waitlist' => 'Seminar Waitlist',
                ];

                echo '<table class="widefat">';
                echo '<thead><tr><th>Table</th><th>Full Name</th><th>Exists</th><th>Row Count</th></tr></thead>';
                echo '<tbody>';

                foreach ($tables as $table => $label) {
                    $full_table = $wpdb->prefix . $table;
                    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
                    $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $full_table") : 'N/A';

                    $status_icon = $exists ? '✅' : '❌';
                    $status_color = $exists ? '' : 'color: #d63638;';

                    echo '<tr style="' . $status_color . '">';
                    echo '<td>' . esc_html($label) . '</td>';
                    echo '<td><code>' . esc_html($full_table) . '</code></td>';
                    echo '<td>' . $status_icon . ' ' . ($exists ? 'Yes' : '<strong>Missing!</strong>') . '</td>';
                    echo '<td>' . ($exists ? '<strong>' . $count . '</strong>' : '—') . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';

                // Check if any tables are missing
                $missing_tables = [];
                foreach ($tables as $table => $label) {
                    $full_table = $wpdb->prefix . $table;
                    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
                    if (!$exists) {
                        $missing_tables[] = $label;
                    }
                }

                if (!empty($missing_tables)) {
                    echo '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">';
                    echo '<strong>⚠️ Warning:</strong> Missing tables detected: ' . implode(', ', $missing_tables);
                    echo '<br>Please use the "Recreate All Database Tables" button above to fix this.';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Recent Tickets -->
            <div class="card">
                <h2>Recent Tickets (Last 10)</h2>
                <?php
                $tickets = $wpdb->get_results("
                    SELECT id, ticket_code, event_id, user_id, order_id, status, created_at
                    FROM {$wpdb->prefix}gps_tickets
                    ORDER BY id DESC
                    LIMIT 10
                ");

                if ($tickets) {
                    echo '<table class="widefat striped">';
                    echo '<thead><tr><th>ID</th><th>Ticket Code</th><th>Event ID</th><th>User ID</th><th>Order ID</th><th>Status</th><th>Created</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($tickets as $ticket) {
                        echo '<tr>';
                        echo '<td>' . $ticket->id . '</td>';
                        echo '<td><code>' . esc_html($ticket->ticket_code) . '</code></td>';
                        echo '<td>' . $ticket->event_id . '</td>';
                        echo '<td>' . $ticket->user_id . '</td>';
                        echo '<td>#' . $ticket->order_id . '</td>';
                        echo '<td>' . $ticket->status . '</td>';
                        echo '<td>' . $ticket->created_at . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>No tickets found.</p>';
                }
                ?>
            </div>

            <!-- Recent Enrollments -->
            <div class="card">
                <h2>Recent Enrollments (Last 10)</h2>
                <?php
                $enrollments = $wpdb->get_results("
                    SELECT id, user_id, session_id, order_id, status, attended, created_at
                    FROM {$wpdb->prefix}gps_enrollments
                    ORDER BY id DESC
                    LIMIT 10
                ");

                if ($enrollments) {
                    echo '<table class="widefat striped">';
                    echo '<thead><tr><th>ID</th><th>User ID</th><th>Session/Event ID</th><th>Order ID</th><th>Status</th><th>Attended</th><th>Created</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($enrollments as $enroll) {
                        echo '<tr>';
                        echo '<td>' . $enroll->id . '</td>';
                        echo '<td>' . $enroll->user_id . '</td>';
                        echo '<td>' . $enroll->session_id . '</td>';
                        echo '<td>#' . $enroll->order_id . '</td>';
                        echo '<td>' . $enroll->status . '</td>';
                        echo '<td>' . ($enroll->attended ? '✅ Yes' : '❌ No') . '</td>';
                        echo '<td>' . $enroll->created_at . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p><strong>⚠️ No enrollments found!</strong> This is why "Total Enrollments" shows 0.</p>';
                }
                ?>
            </div>

            <!-- Recent Attendance -->
            <div class="card">
                <h2>Recent Attendance (Last 10)</h2>
                <?php
                $attendance = $wpdb->get_results("
                    SELECT id, ticket_id, event_id, user_id, checked_in_at, check_in_method
                    FROM {$wpdb->prefix}gps_attendance
                    ORDER BY id DESC
                    LIMIT 10
                ");

                if ($attendance) {
                    echo '<table class="widefat striped">';
                    echo '<thead><tr><th>ID</th><th>Ticket ID</th><th>Event ID</th><th>User ID</th><th>Checked In At</th><th>Method</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($attendance as $att) {
                        echo '<tr>';
                        echo '<td>' . $att->id . '</td>';
                        echo '<td>' . $att->ticket_id . '</td>';
                        echo '<td>' . $att->event_id . '</td>';
                        echo '<td>' . $att->user_id . '</td>';
                        echo '<td>' . $att->checked_in_at . '</td>';
                        echo '<td>' . $att->check_in_method . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>No attendance records found.</p>';
                }
                ?>
            </div>

            <style>
                .card {
                    background: white;
                    padding: 20px;
                    margin: 20px 0;
                    border: 1px solid #ccd0d4;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                }
                .card h2 {
                    margin-top: 0;
                    border-bottom: 1px solid #ccd0d4;
                    padding-bottom: 10px;
                }
                .card table {
                    margin-top: 15px;
                }
            </style>
        </div>
        <?php
    }
}
