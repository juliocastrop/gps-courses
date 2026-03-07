<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Tickets Administration
 * Handles ticket management admin page, resend email, and attendee assignment
 */
class Tickets_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_resend_email']);
        add_action('admin_init', [__CLASS__, 'reconcile_designated_attendees']);
        add_action('admin_notices', [__CLASS__, 'show_admin_notices']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_gps_assign_attendee', [__CLASS__, 'ajax_assign_attendee']);
    }

    /**
     * Reconcile designated attendees that have email but no user_id.
     * Creates WP accounts for them so credits/certificates link correctly.
     * Runs once via a transient check to avoid repeated processing.
     */
    public static function reconcile_designated_attendees() {
        // Only run once per day (or after cache clear)
        if (get_transient('gps_reconcile_attendees_done')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gps_tickets';

        // Find tickets with designated email but no designated user_id
        $orphans = $wpdb->get_results(
            "SELECT id, designated_attendee_name, designated_attendee_email
             FROM $table
             WHERE designated_attendee_email IS NOT NULL
             AND designated_attendee_email != ''
             AND (designated_attendee_id IS NULL OR designated_attendee_id = 0)"
        );

        if (empty($orphans)) {
            set_transient('gps_reconcile_attendees_done', 1, DAY_IN_SECONDS);
            return;
        }

        foreach ($orphans as $ticket) {
            $email = $ticket->designated_attendee_email;
            $name  = $ticket->designated_attendee_name;

            // Check if user exists now
            $wp_user = get_user_by('email', $email);

            if (!$wp_user) {
                // Create account
                $name_parts = explode(' ', $name, 2);
                $first_name = $name_parts[0];
                $last_name  = isset($name_parts[1]) ? $name_parts[1] : '';

                $username = sanitize_user(strstr($email, '@', true), true);
                if (username_exists($username)) {
                    $username = $username . '_' . wp_rand(100, 999);
                }

                $new_user_id = wp_insert_user([
                    'user_login'   => $username,
                    'user_email'   => $email,
                    'user_pass'    => wp_generate_password(12, true),
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'display_name' => $name,
                    'role'         => 'customer',
                ]);

                if (!is_wp_error($new_user_id)) {
                    $wp_user = get_user_by('ID', $new_user_id);
                    wp_new_user_notification($new_user_id, null, 'user');
                    error_log(sprintf('GPS Courses: Reconcile — created WP account #%d for "%s" (%s)', $new_user_id, $name, $email));
                } else {
                    error_log('GPS Courses: Reconcile — failed to create account for ' . $email . ': ' . $new_user_id->get_error_message());
                    continue;
                }
            }

            // Update the ticket with the user ID
            $wpdb->update(
                $table,
                ['designated_attendee_id' => $wp_user->ID],
                ['id' => $ticket->id],
                ['%d'],
                ['%d']
            );

            error_log(sprintf('GPS Courses: Reconcile — linked ticket #%d to WP user #%d (%s)', $ticket->id, $wp_user->ID, $email));
        }

        set_transient('gps_reconcile_attendees_done', 1, DAY_IN_SECONDS);
    }

    /**
     * Get the effective attendee data for a ticket (designated or buyer)
     */
    public static function get_effective_attendee($ticket) {
        return (object) [
            'name'    => !empty($ticket->designated_attendee_name) ? $ticket->designated_attendee_name : $ticket->attendee_name,
            'email'   => !empty($ticket->designated_attendee_email) ? $ticket->designated_attendee_email : $ticket->attendee_email,
            'user_id' => !empty($ticket->designated_attendee_id) ? $ticket->designated_attendee_id : $ticket->user_id,
        ];
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
     * Enqueue scripts for the Purchased Tickets page
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'gps-courses_page_gps-purchased-tickets') {
            return;
        }

        wp_enqueue_script(
            'gps-tickets-admin',
            GPSC_URL . 'assets/js/tickets-admin.js',
            ['jquery'],
            GPSC_VERSION,
            true
        );

        wp_localize_script('gps-tickets-admin', 'gpsTicketsAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('gps_assign_attendee_nonce'),
        ]);
    }

    /**
     * Handle resend email request
     */
    public static function handle_resend_email() {
        if (!isset($_POST['gps_resend_email']) || !isset($_POST['gps_resend_nonce'])) {
            return;
        }

        $ticket_id = (int) $_POST['gps_resend_email'];

        if (!wp_verify_nonce($_POST['gps_resend_nonce'], 'gps_resend_email_' . $ticket_id)) {
            wp_die(__('Security check failed', 'gps-courses'));
        }

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

        $sent = Emails::send_ticket_email($ticket_id, $ticket->order_id);

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
     * AJAX handler: Assign designated attendee to a ticket
     */
    public static function ajax_assign_attendee() {
        check_ajax_referer('gps_assign_attendee_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $ticket_id = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
        $name      = isset($_POST['attendee_name']) ? sanitize_text_field($_POST['attendee_name']) : '';
        $email     = isset($_POST['attendee_email']) ? sanitize_email($_POST['attendee_email']) : '';
        $clear     = isset($_POST['clear_attendee']) && $_POST['clear_attendee'] === '1';
        $regen_qr  = isset($_POST['regenerate_qr']) && $_POST['regenerate_qr'] === '1';
        $send_email = isset($_POST['send_email']) && $_POST['send_email'] === '1';

        if (!$ticket_id) {
            wp_send_json_error(['message' => __('Invalid ticket ID.', 'gps-courses')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gps_tickets';

        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $ticket_id));
        if (!$ticket) {
            wp_send_json_error(['message' => __('Ticket not found.', 'gps-courses')]);
        }

        // Clear designated attendee (revert to buyer)
        if ($clear) {
            $wpdb->update(
                $table,
                [
                    'designated_attendee_name'  => null,
                    'designated_attendee_email' => null,
                    'designated_attendee_id'    => null,
                ],
                ['id' => $ticket_id],
                [null, null, null],
                ['%d']
            );

            error_log(sprintf(
                'GPS Courses: Designated attendee cleared from ticket #%d by admin #%d',
                $ticket_id,
                get_current_user_id()
            ));

            wp_send_json_success([
                'message'          => __('Designated attendee removed. Buyer will be used as attendee.', 'gps-courses'),
                'ticket_id'        => $ticket_id,
                'has_designated'   => false,
                'effective_name'   => $ticket->attendee_name,
                'effective_email'  => $ticket->attendee_email,
            ]);
        }

        // Assign designated attendee
        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => __('Name and email are required.', 'gps-courses')]);
        }

        // Look up or create WP user by email
        $wp_user = get_user_by('email', $email);
        $account_created = false;

        if (!$wp_user) {
            // Auto-create WordPress account for the attendee
            $name_parts = explode(' ', $name, 2);
            $first_name = $name_parts[0];
            $last_name  = isset($name_parts[1]) ? $name_parts[1] : '';

            // Generate username from email (part before @)
            $username = sanitize_user(strstr($email, '@', true), true);
            if (username_exists($username)) {
                $username = $username . '_' . wp_rand(100, 999);
            }

            $password = wp_generate_password(12, true);
            $new_user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => $password,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'display_name' => $name,
                'role'       => 'customer',
            ]);

            if (!is_wp_error($new_user_id)) {
                $wp_user = get_user_by('ID', $new_user_id);
                $account_created = true;

                // Send WP new account notification with password reset link
                wp_new_user_notification($new_user_id, null, 'user');

                error_log(sprintf(
                    'GPS Courses: Auto-created WP account #%d for designated attendee "%s" (%s)',
                    $new_user_id, $name, $email
                ));
            } else {
                error_log('GPS Courses: Failed to create WP account for ' . $email . ': ' . $new_user_id->get_error_message());
            }
        }

        $designated_id = $wp_user ? $wp_user->ID : null;

        $updated = $wpdb->update(
            $table,
            [
                'designated_attendee_name'  => $name,
                'designated_attendee_email' => $email,
                'designated_attendee_id'    => $designated_id,
            ],
            ['id' => $ticket_id],
            ['%s', '%s', $designated_id ? '%d' : null],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error(['message' => __('Failed to update ticket.', 'gps-courses')]);
        }

        $warnings = [];

        // Regenerate QR code if requested
        $qr_regenerated = false;
        if ($regen_qr) {
            $new_qr = QRCodeGenerator::regenerate_qr_code($ticket_id);
            $qr_regenerated = (bool) $new_qr;
        }

        // Send ticket email to designated attendee
        $email_sent = false;
        if ($send_email) {
            $email_sent = Emails::send_ticket_email($ticket_id, $ticket->order_id);
        }

        if ($account_created) {
            $warnings[] = sprintf(
                __('WordPress account created for %s. They will receive a password setup email.', 'gps-courses'),
                $email
            );
        } elseif (!$wp_user) {
            $warnings[] = __('Could not create a WordPress account for this email. CE credits will be linked to the buyer\'s account.', 'gps-courses');
        }

        error_log(sprintf(
            'GPS Courses: Designated attendee assigned to ticket #%d — Name: "%s", Email: "%s", WP User: %s — by admin #%d',
            $ticket_id,
            $name,
            $email,
            $designated_id ?: 'none',
            get_current_user_id()
        ));

        wp_send_json_success([
            'message'          => __('Attendee assigned successfully!', 'gps-courses'),
            'ticket_id'        => $ticket_id,
            'has_designated'   => true,
            'designated_name'  => $name,
            'designated_email' => $email,
            'effective_name'   => $name,
            'effective_email'  => $email,
            'qr_regenerated'   => $qr_regenerated,
            'email_sent'       => $email_sent,
            'warnings'         => $warnings,
        ]);
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

        $event_filter = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

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

        $where_clauses = [];

        if ($event_filter) {
            $where_clauses[] = $wpdb->prepare("t.event_id = %d", $event_filter);
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY t.created_at DESC";

        $tickets = $wpdb->get_results($query);

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
                        <th><?php _e('Buyer', 'gps-courses'); ?></th>
                        <th><?php _e('Attendee', 'gps-courses'); ?></th>
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
                        <?php foreach ($tickets as $ticket):
                            $buyer_name = $ticket->attendee_name ?: $ticket->display_name;
                            $buyer_email = $ticket->attendee_email ?: $ticket->user_email;
                            $has_designated = !empty($ticket->designated_attendee_name);
                            $effective = self::get_effective_attendee($ticket);
                        ?>
                            <tr data-ticket-id="<?php echo $ticket->id; ?>">
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
                                    <span class="gps-buyer-name"><?php echo esc_html($buyer_name); ?></span>
                                    <br><small class="gps-buyer-email" style="color: #666;"><?php echo esc_html($buyer_email); ?></small>
                                </td>
                                <td class="gps-attendee-cell">
                                    <?php if ($has_designated): ?>
                                        <span class="gps-designated-badge"><?php _e('Designated', 'gps-courses'); ?></span><br>
                                        <span class="gps-attendee-name"><?php echo esc_html($ticket->designated_attendee_name); ?></span>
                                        <br><small class="gps-attendee-email" style="color: #666;"><?php echo esc_html($ticket->designated_attendee_email); ?></small>
                                    <?php else: ?>
                                        <span class="gps-same-as-buyer"><?php _e('= Buyer', 'gps-courses'); ?></span>
                                    <?php endif; ?>
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
                                    $order = wc_get_order($ticket->order_id);
                                    $order_status = $order ? $order->get_status() : 'unknown';

                                    $status_map = [
                                        'completed' => ['label' => 'Valid', 'color' => '#46b450'],
                                        'processing' => ['label' => 'Processing', 'color' => '#f0b849'],
                                        'pending' => ['label' => 'Pending', 'color' => '#999'],
                                        'on-hold' => ['label' => 'On Hold', 'color' => '#f0b849'],
                                        'cancelled' => ['label' => 'Cancelled', 'color' => '#d63638'],
                                        'refunded' => ['label' => 'Refunded', 'color' => '#d63638'],
                                        'failed' => ['label' => 'Failed', 'color' => '#d63638'],
                                    ];

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
                                    <!-- Assign Attendee Button -->
                                    <button type="button" class="button button-small gps-assign-attendee-btn"
                                            data-ticket-id="<?php echo $ticket->id; ?>"
                                            data-buyer-name="<?php echo esc_attr($buyer_name); ?>"
                                            data-buyer-email="<?php echo esc_attr($buyer_email); ?>"
                                            data-designated-name="<?php echo esc_attr($ticket->designated_attendee_name ?? ''); ?>"
                                            data-designated-email="<?php echo esc_attr($ticket->designated_attendee_email ?? ''); ?>"
                                            data-has-designated="<?php echo $has_designated ? '1' : '0'; ?>">
                                        <span class="dashicons dashicons-groups" style="font-size: 14px; margin-top: 3px;"></span>
                                        <?php echo $has_designated ? __('Edit Attendee', 'gps-courses') : __('Assign Attendee', 'gps-courses'); ?>
                                    </button>

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

            <!-- Assign Attendee Modal -->
            <div id="gps-assign-modal" style="display:none;">
                <div class="gps-modal-overlay"></div>
                <div class="gps-modal-content">
                    <div class="gps-modal-header">
                        <h2><?php _e('Assign Attendee', 'gps-courses'); ?></h2>
                        <button class="gps-modal-close" type="button">&times;</button>
                    </div>
                    <div class="gps-modal-body">
                        <input type="hidden" id="assign-ticket-id" value="">

                        <div class="gps-buyer-info-box">
                            <label><?php _e('Buyer (purchaser)', 'gps-courses'); ?></label>
                            <p id="assign-buyer-info"></p>
                        </div>

                        <div class="gps-field-group">
                            <label for="assign-attendee-name"><?php _e('Attendee Name', 'gps-courses'); ?> <span style="color: #d63638;">*</span></label>
                            <input type="text" id="assign-attendee-name" class="widefat" placeholder="<?php esc_attr_e('Full name of the person attending', 'gps-courses'); ?>">
                        </div>

                        <div class="gps-field-group">
                            <label for="assign-attendee-email"><?php _e('Attendee Email', 'gps-courses'); ?> <span style="color: #d63638;">*</span></label>
                            <input type="email" id="assign-attendee-email" class="widefat" placeholder="<?php esc_attr_e('Email for ticket delivery and communications', 'gps-courses'); ?>">
                        </div>

                        <div class="gps-field-group">
                            <label>
                                <input type="checkbox" id="assign-regenerate-qr" checked>
                                <?php _e('Regenerate QR Code for the attendee', 'gps-courses'); ?>
                            </label>
                        </div>

                        <div class="gps-field-group">
                            <label>
                                <input type="checkbox" id="assign-send-email" checked>
                                <?php _e('Send ticket email to the attendee', 'gps-courses'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="gps-modal-footer">
                        <button type="button" class="button gps-modal-close-btn"><?php _e('Cancel', 'gps-courses'); ?></button>
                        <button type="button" class="button" id="gps-clear-attendee" style="color: #d63638; display: none;">
                            <?php _e('Remove Attendee', 'gps-courses'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="gps-save-attendee">
                            <?php _e('Save Attendee', 'gps-courses'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <style>
                .wp-list-table th { font-weight: 600; }
                .wp-list-table td { vertical-align: middle; }
                .wp-list-table .button-small { margin: 2px; }

                .gps-designated-badge {
                    display: inline-block;
                    background: #e8f0fe;
                    color: #0B52AC;
                    padding: 1px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    margin-bottom: 2px;
                }
                .gps-same-as-buyer {
                    color: #999;
                    font-style: italic;
                    font-size: 13px;
                }

                /* Modal */
                #gps-assign-modal {
                    position: fixed;
                    top: 0; left: 0; right: 0; bottom: 0;
                    z-index: 100000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .gps-modal-overlay {
                    position: absolute;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.5);
                }
                .gps-modal-content {
                    position: relative;
                    background: #fff;
                    border-radius: 8px;
                    width: 500px;
                    max-width: 90%;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                }
                .gps-modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 16px 20px;
                    border-bottom: 1px solid #ddd;
                }
                .gps-modal-header h2 { margin: 0; font-size: 18px; }
                .gps-modal-close {
                    background: none; border: none;
                    font-size: 24px; cursor: pointer; color: #666;
                    padding: 0; line-height: 1;
                }
                .gps-modal-body { padding: 20px; }
                .gps-modal-footer {
                    padding: 16px 20px;
                    border-top: 1px solid #ddd;
                    text-align: right;
                    display: flex;
                    justify-content: flex-end;
                    gap: 8px;
                }
                .gps-field-group { margin-bottom: 16px; }
                .gps-field-group label { display: block; margin-bottom: 4px; font-weight: 600; }
                .gps-buyer-info-box {
                    background: #f0f0f1;
                    padding: 12px;
                    border-radius: 4px;
                    margin-bottom: 16px;
                }
                .gps-buyer-info-box label { font-weight: 600; color: #666; margin-bottom: 4px; display: block; }
                .gps-buyer-info-box p { margin: 0; }
            </style>
        </div>
        <?php
    }
}
