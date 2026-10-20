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
        add_action('admin_init', [__CLASS__, 'handle_repair_tables']);
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

    /**
     * "Repair missing tables": creates missing tables / columns, never drops data.
     * (The old "Recreate All Database Tables" dropped every table and the old
     * "Create Missing Enrollments" marked cancelled tickets as completed; both removed.)
     */
    public static function handle_repair_tables() {
        if (!isset($_POST['gps_debug_repair_tables'])) {
            return;
        }

        check_admin_referer('gps_debug_repair_nonce');

        if (!current_user_can('activate_plugins')) {
            wp_die(__('You do not have permission to perform this action.', 'gps-courses'));
        }

        Activator::repair_tables();

        wp_redirect(add_query_arg(['page' => 'gps-debug', 'tables_repaired' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function render_debug_page() {
        global $wpdb;

        if (isset($_GET['tables_repaired'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Missing tables and columns were created. Existing data was not touched.', 'gps-courses');
            echo '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Debug Info', 'gps-courses'); ?></h1>

            <!-- Repair (non-destructive) -->
            <div class="card">
                <h2><?php esc_html_e('Repair Missing Tables', 'gps-courses'); ?></h2>
                <p><?php esc_html_e('Creates any GPS Courses table or column that is missing. Existing tables and their data are never changed or deleted.', 'gps-courses'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('gps_debug_repair_nonce'); ?>
                    <button type="submit" name="gps_debug_repair_tables" class="button"><?php esc_html_e('Repair Missing Tables', 'gps-courses'); ?></button>
                </form>
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
                    echo '<br>Please use the "Repair Missing Tables" button above to fix this.';
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
