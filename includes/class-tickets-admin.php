<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Tickets Administration
 * Handles ticket management admin page with resend email functionality
 */
class Tickets_Admin {

    public static function init() {
        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // Handle resend email action
        add_action('admin_init', [__CLASS__, 'handle_resend_email']);

        // Admin notices
        add_action('admin_notices', [__CLASS__, 'show_admin_notices']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Purchased Tickets', 'gps-courses'),
            __('Purchased Tickets', 'gps-courses'),
            'manage_options',
            'gps-purchased-tickets',
            [__CLASS__, 'render_tickets_page']
        );
    }

    /**
     * Handle resend email request
     */
    public static function handle_resend_email() {
        if (!isset($_POST['gps_resend_email']) || !isset($_POST['gps_resend_nonce'])) {
            return;
        }

        $ticket_id = (int) $_POST['gps_resend_email'];

        // Verify nonce
        if (!wp_verify_nonce($_POST['gps_resend_nonce'], 'gps_resend_email_' . $ticket_id)) {
            wp_die(__('Security check failed', 'gps-courses'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to do this', 'gps-courses'));
        }

        global $wpdb;
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            $ticket_id
        ));

        if (!$ticket) {
            wp_die(__('Ticket not found', 'gps-courses'));
        }

        // Resend email
        $sent = Emails::send_ticket_email($ticket_id, $ticket->order_id);

        // Redirect with message
        $redirect_url = admin_url('admin.php?page=gps-purchased-tickets');
        if ($sent) {
            $redirect_url = add_query_arg('email_sent', $ticket_id, $redirect_url);
        } else {
            $redirect_url = add_query_arg('email_failed', $ticket_id, $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Show admin notices
     */
    public static function show_admin_notices() {
        if (isset($_GET['email_sent'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Ticket email sent successfully!', 'gps-courses') . '</p>';
            echo '</div>';
        }

        if (isset($_GET['email_failed'])) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . __('Failed to send ticket email. Check error logs for details.', 'gps-courses') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Render tickets page
     */
    public static function render_tickets_page() {
        global $wpdb;

        // Get filter
        $event_filter = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

        // Build query - Include tickets from both 'completed' and 'processing' WooCommerce orders
        $query = "SELECT t.*,
                         p.post_title as event_title,
                         tt.post_title as ticket_type,
                         u.display_name, u.user_email,
                         po.post_status as order_status
                  FROM {$wpdb->prefix}gps_tickets t
                  INNER JOIN {$wpdb->posts} p ON t.event_id = p.ID
                  LEFT JOIN {$wpdb->posts} tt ON t.ticket_type_id = tt.ID
                  LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                  LEFT JOIN {$wpdb->posts} po ON t.order_id = po.ID";

        // Build WHERE clause
        $where_clauses = [];

        if ($event_filter) {
            $where_clauses[] = $wpdb->prepare("t.event_id = %d", $event_filter);
        }

        // Only filter by order status if there are WooCommerce orders
        // This ensures tickets without orders or with other statuses still show
        // Priority is showing 'completed' and 'processing' but we don't hide others completely
        // Note: Removed strict filtering to show all tickets, but processing/completed will be highlighted in status column

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY t.created_at DESC";

        $tickets = $wpdb->get_results($query);

        // Get events for filter
        $events = get_posts([
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        ?>
        <div class="wrap">
            <h1><?php _e('Purchased Tickets', 'gps-courses'); ?></h1>

            <!-- Filter -->
            <form method="get" style="margin: 20px 0;">
                <input type="hidden" name="page" value="gps-purchased-tickets">
                <select name="event_id" onchange="this.form.submit()">
                    <option value=""><?php _e('All Events', 'gps-courses'); ?></option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo $event->ID; ?>" <?php selected($event_filter, $event->ID); ?>>
                            <?php echo esc_html($event->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Tickets Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Ticket Code', 'gps-courses'); ?></th>
                        <th><?php _e('Event', 'gps-courses'); ?></th>
                        <th><?php _e('Ticket Type', 'gps-courses'); ?></th>
                        <th><?php _e('Customer', 'gps-courses'); ?></th>
                        <th><?php _e('Email', 'gps-courses'); ?></th>
                        <th><?php _e('Order ID', 'gps-courses'); ?></th>
                        <th><?php _e('Purchase Date', 'gps-courses'); ?></th>
                        <th><?php _e('Status', 'gps-courses'); ?></th>
                        <th><?php _e('Actions', 'gps-courses'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                <p style="color: #666;"><?php _e('No tickets found.', 'gps-courses'); ?></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($ticket->ticket_code); ?></strong>
                                </td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($ticket->event_id); ?>">
                                        <?php echo esc_html($ticket->event_title); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo esc_html($ticket->ticket_type ?: '—'); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($ticket->attendee_name ?: $ticket->display_name); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($ticket->attendee_email ?: $ticket->user_email); ?>
                                </td>
                                <td>
                                    <?php if ($ticket->order_id): ?>
                                        <a href="<?php echo admin_url('post.php?post=' . $ticket->order_id . '&action=edit'); ?>">
                                            #<?php echo $ticket->order_id; ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ticket->created_at)); ?>
                                </td>
                                <td>
                                    <?php
                                    // Get order status from WooCommerce
                                    $order = wc_get_order($ticket->order_id);
                                    $order_status = $order ? $order->get_status() : 'unknown';

                                    // Map WooCommerce status to display
                                    $status_map = [
                                        'completed' => ['label' => 'Valid', 'color' => '#46b450'],
                                        'processing' => ['label' => 'Processing', 'color' => '#f0b849'],
                                        'pending' => ['label' => 'Pending', 'color' => '#999'],
                                        'on-hold' => ['label' => 'On Hold', 'color' => '#f0b849'],
                                        'cancelled' => ['label' => 'Cancelled', 'color' => '#d63638'],
                                        'refunded' => ['label' => 'Refunded', 'color' => '#d63638'],
                                        'failed' => ['label' => 'Failed', 'color' => '#d63638'],
                                    ];

                                    // Check if ticket was manually marked as used
                                    if ($ticket->status === 'used') {
                                        $status_display = ['label' => 'Used', 'color' => '#999'];
                                    } else {
                                        $status_display = $status_map[$order_status] ?? ['label' => ucfirst($order_status), 'color' => '#646970'];
                                    }
                                    ?>
                                    <span style="color: <?php echo esc_attr($status_display['color']); ?>; font-weight: 600;">
                                        <?php echo esc_html($status_display['label']); ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Resend Email Button -->
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="gps_resend_email" value="<?php echo $ticket->id; ?>">
                                        <?php wp_nonce_field('gps_resend_email_' . $ticket->id, 'gps_resend_nonce'); ?>
                                        <button type="submit" class="button button-small"
                                                onclick="return confirm('<?php esc_attr_e('Send ticket email to this customer?', 'gps-courses'); ?>');">
                                            <span class="dashicons dashicons-email" style="font-size: 14px; margin-top: 3px;"></span>
                                            <?php _e('Resend Email', 'gps-courses'); ?>
                                        </button>
                                    </form>

                                    <!-- View QR Code -->
                                    <?php if (!empty($ticket->qr_code_path)): ?>
                                        <?php $qr_url = QRCodeGenerator::get_qr_code_url($ticket->qr_code_path); ?>
                                        <a href="<?php echo esc_url($qr_url); ?>" target="_blank" class="button button-small">
                                            <span class="dashicons dashicons-tickets" style="font-size: 14px; margin-top: 3px;"></span>
                                            <?php _e('QR Code', 'gps-courses'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <style>
                .wp-list-table th {
                    font-weight: 600;
                }
                .wp-list-table td {
                    vertical-align: middle;
                }
                .wp-list-table .button-small {
                    margin: 2px;
                }
            </style>
        </div>
        <?php
    }
}
