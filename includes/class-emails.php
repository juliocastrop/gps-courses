<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Email handling for GPS Courses
 */
class Emails {

    public static function init() {
        // Register custom WooCommerce email
        add_filter('woocommerce_email_classes', [__CLASS__, 'register_email_classes']);

        // Hook into order completion to send ticket emails
        add_action('gps_ticket_created', [__CLASS__, 'send_ticket_email'], 10, 2);

        // CE Credits awarded email
        add_action('gps_credits_awarded', [__CLASS__, 'send_credits_email'], 10, 3);
    }

    /**
     * Register custom email classes with WooCommerce
     */
    public static function register_email_classes($email_classes) {
        require_once GPSC_PATH . 'includes/emails/class-ticket-email.php';
        require_once GPSC_PATH . 'includes/emails/class-credits-email.php';

        $email_classes['GPSC_Ticket_Email'] = new \GPSC_Ticket_Email();
        $email_classes['GPSC_Credits_Email'] = new \GPSC_Credits_Email();

        return $email_classes;
    }

    /**
     * Send ticket confirmation email with QR code
     */
    public static function send_ticket_email($ticket_id, $order_id) {
        global $wpdb;

        error_log('GPS Courses: Attempting to send ticket email for ticket #' . $ticket_id);

        // Get ticket data
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            $ticket_id
        ));

        if (!$ticket) {
            error_log('GPS Courses: Ticket not found in database: #' . $ticket_id);
            return false;
        }

        // Get event data
        $event = get_post($ticket->event_id);
        if (!$event) {
            error_log('GPS Courses: Event not found: #' . $ticket->event_id);
            return false;
        }
        error_log('GPS Courses: Found event: ' . $event->post_title);

        // Handle both guest checkout (user_id = 0) and registered users
        $user = null;
        $recipient_email = '';
        $recipient_name = '';

        if ($ticket->user_id > 0) {
            // Registered user
            $user = get_userdata($ticket->user_id);
            if (!$user) {
                error_log('GPS Courses: User not found: #' . $ticket->user_id);
                return false;
            }
            error_log('GPS Courses: Found user: ' . $user->user_email);
            $recipient_email = $ticket->attendee_email ?: $user->user_email;
            $recipient_name = $ticket->attendee_name ?: $user->display_name;
        } else {
            // Guest checkout - use attendee info from ticket
            error_log('GPS Courses: Guest checkout detected, using attendee info from ticket');
            if (empty($ticket->attendee_email)) {
                error_log('GPS Courses: No attendee email found for guest checkout ticket');
                return false;
            }
            $recipient_email = $ticket->attendee_email;
            $recipient_name = $ticket->attendee_name ?: __('Guest', 'gps-courses');
        }

        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('GPS Courses: Order not found: #' . $order_id);
            return false;
        }
        error_log('GPS Courses: Found order: #' . $order->get_order_number());

        // Get event metadata
        $event_start = get_post_meta($ticket->event_id, '_gps_start_date', true);
        $event_end = get_post_meta($ticket->event_id, '_gps_end_date', true);
        $event_venue = get_post_meta($ticket->event_id, '_gps_venue', true);
        $ce_credits = (int) get_post_meta($ticket->event_id, '_gps_ce_credits', true);

        // Get ticket type
        $ticket_type_post = get_post($ticket->ticket_type_id);
        $ticket_type_name = $ticket_type_post ? $ticket_type_post->post_title : __('Ticket', 'gps-courses');
        $ticket_price = get_post_meta($ticket->ticket_type_id, '_gps_ticket_price', true);

        // Get QR code
        $qr_code_url = QRCodeGenerator::get_qr_code_url($ticket->qr_code_path);
        $qr_code_path = QRCodeGenerator::get_qr_code_path($ticket->qr_code_path);

        // Debug QR code
        if (empty($ticket->qr_code_path)) {
            error_log('GPS Courses: No QR code path found for ticket #' . $ticket_id);
        } else {
            error_log('GPS Courses: QR code for ticket #' . $ticket_id . ': ' . $qr_code_url);
            if (!empty($qr_code_path) && !file_exists($qr_code_path)) {
                error_log('GPS Courses: QR code file does not exist: ' . $qr_code_path);
            }
        }

        // Prepare email data
        $email_data = [
            'ticket' => $ticket,
            'event' => $event,
            'user' => $user,
            'order' => $order,
            'ticket_type_name' => $ticket_type_name,
            'ticket_price' => $ticket_price,
            'event_start' => $event_start,
            'event_end' => $event_end,
            'event_venue' => $event_venue,
            'ce_credits' => $ce_credits,
            'qr_code_url' => $qr_code_url,
            'qr_code_path' => $qr_code_path,
        ];

        // Get email content
        $subject = self::get_email_subject('ticket', $email_data);
        $message = self::get_email_content('ticket', $email_data);

        // Email headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        error_log('GPS Courses: Sending ticket email to: ' . $recipient_email);
        error_log('GPS Courses: Email subject: ' . $subject);

        // Send email
        $sent = wp_mail(
            $recipient_email,
            $subject,
            $message,
            $headers
        );

        if ($sent) {
            error_log('GPS Courses: Ticket email sent successfully to ' . $recipient_email);
        } else {
            error_log('GPS Courses: Failed to send ticket email to ' . $recipient_email);
        }

        return $sent;
    }

    /**
     * Send CE credits awarded email
     */
    public static function send_credits_email($user_id, $event_id, $credits) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $event = get_post($event_id);
        if (!$event) {
            return false;
        }

        // Get total credits
        $total_credits = Credits::user_total($user_id);

        // Prepare email data
        $email_data = [
            'user' => $user,
            'event' => $event,
            'credits' => $credits,
            'total_credits' => $total_credits,
        ];

        // Get email content
        $subject = self::get_email_subject('credits', $email_data);
        $message = self::get_email_content('credits', $email_data);

        // Email headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Send email
        return wp_mail(
            $user->user_email,
            $subject,
            $message,
            $headers
        );
    }

    /**
     * Get email subject
     */
    private static function get_email_subject($type, $data) {
        switch ($type) {
            case 'ticket':
                return sprintf(
                    __('Your Ticket for %s - Order #%s', 'gps-courses'),
                    $data['event']->post_title,
                    $data['order']->get_order_number()
                );

            case 'credits':
                return sprintf(
                    __('CE Credits Awarded: %d Credits from %s', 'gps-courses'),
                    $data['credits'],
                    $data['event']->post_title
                );

            default:
                return __('GPS Dental Training Notification', 'gps-courses');
        }
    }

    /**
     * Get email content HTML
     */
    private static function get_email_content($type, $data) {
        ob_start();

        // Load template
        $template_path = GPSC_PATH . "templates/emails/{$type}.php";

        if (file_exists($template_path)) {
            // Extract data array so template can access variables directly
            extract($data);
            include $template_path;
        } else {
            // Fallback to inline template
            if ($type === 'ticket') {
                self::render_ticket_email_inline($data);
            } elseif ($type === 'credits') {
                self::render_credits_email_inline($data);
            }
        }

        return ob_get_clean();
    }

    /**
     * Render ticket email inline (fallback)
     */
    private static function render_ticket_email_inline($data) {
        $ticket = $data['ticket'];
        $event = $data['event'];
        $order = $data['order'];
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Your Event Booking is Complete', 'gps-courses'); ?></title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5;">
                <tr>
                    <td align="center" style="padding: 40px 20px;">
                        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                            <!-- Header -->
                            <tr>
                                <td style="padding: 40px 40px 20px;">
                                    <h1 style="margin: 0; font-size: 28px; font-weight: bold; color: #1e293b;">
                                        <?php _e('Your Event Booking is Complete.', 'gps-courses'); ?>
                                    </h1>
                                    <p style="margin: 10px 0 0; font-size: 16px; color: #64748b;">
                                        <?php _e('You have purchased ticket(s). Attendee ticket details are as follows.', 'gps-courses'); ?>
                                    </p>
                                </td>
                            </tr>

                            <!-- Order Info -->
                            <tr>
                                <td style="padding: 20px 40px;">
                                    <div style="background-color: #f8fafc; padding: 20px; border-radius: 6px;">
                                        <p style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">
                                            <?php echo sprintf(__('Order #%s', 'gps-courses'), $order->get_order_number()); ?>
                                            <span style="float: right; font-weight: normal; color: #64748b;">
                                                <?php echo date_i18n(get_option('date_format'), strtotime($order->get_date_created())); ?>
                                            </span>
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Ticket Details -->
                            <tr>
                                <td style="padding: 20px 40px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="padding-bottom: 10px;">
                                                <p style="margin: 0; font-size: 14px; color: #64748b;">
                                                    <?php echo esc_html($data['ticket_type_name']); ?> × 1
                                                </p>
                                            </td>
                                            <td align="right" style="padding-bottom: 10px;">
                                                <p style="margin: 0; font-size: 14px; color: #1e293b;">
                                                    <?php echo wc_price($data['ticket_price']); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        <tr style="border-top: 2px solid #e2e8f0;">
                                            <td style="padding-top: 10px;">
                                                <p style="margin: 0; font-size: 16px; font-weight: 600; color: #1e293b;">
                                                    <?php _e('Total:', 'gps-courses'); ?>
                                                </p>
                                            </td>
                                            <td align="right" style="padding-top: 10px;">
                                                <p style="margin: 0; font-size: 18px; font-weight: bold; color: #1e293b;">
                                                    <?php echo $order->get_formatted_order_total(); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style="margin: 20px 0 5px; font-size: 14px; color: #64748b;">
                                        <?php _e('Payment Method:', 'gps-courses'); ?>
                                        <strong><?php echo esc_html($order->get_payment_method_title()); ?></strong>
                                    </p>
                                </td>
                            </tr>

                            <!-- Download Ticket Section -->
                            <tr>
                                <td style="padding: 20px 40px; border-top: 1px solid #e2e8f0;">
                                    <h2 style="margin: 0 0 15px; font-size: 20px; font-weight: 600; color: #1e293b;">
                                        <?php _e('Download Ticket', 'gps-courses'); ?>
                                    </h2>
                                    <p style="margin: 0 0 5px; font-size: 14px; color: #1e293b;">
                                        <strong><?php _e('Ticket name:', 'gps-courses'); ?></strong>
                                        <?php echo esc_html($data['ticket_type_name']); ?>
                                    </p>
                                    <p style="margin: 0; font-size: 14px; color: #1e293b;">
                                        <strong><?php _e('Attendee:', 'gps-courses'); ?></strong>
                                        <?php echo esc_html($ticket->attendee_name); ?>
                                    </p>

                                    <div style="margin-top: 20px; text-align: center;">
                                        <a href="<?php echo esc_url(home_url('/my-account/my-tickets/?ticket_id=' . $ticket->id)); ?>"
                                           style="display: inline-block; padding: 12px 30px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                                            <?php _e('Download Ticket', 'gps-courses'); ?>
                                        </a>
                                        <a href="<?php echo esc_url(home_url('/my-account/my-tickets/?ticket_id=' . $ticket->id)); ?>"
                                           style="display: inline-block; padding: 12px 30px; background-color: transparent; color: #3b82f6; text-decoration: none; border: 2px solid #3b82f6; border-radius: 6px; font-weight: 600; font-size: 14px; margin-left: 10px;">
                                            <?php _e('Edit Information', 'gps-courses'); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            <!-- Event Details -->
                            <tr>
                                <td style="padding: 20px 40px; border-top: 1px solid #e2e8f0;">
                                    <h2 style="margin: 0 0 15px; font-size: 20px; font-weight: 600; color: #1e293b;">
                                        <?php echo esc_html($event->post_title); ?>
                                    </h2>

                                    <p style="margin: 0 0 10px; font-size: 14px; color: #64748b;">
                                        <strong style="color: #1e293b;"><?php _e('Date:', 'gps-courses'); ?></strong><br>
                                        <?php
                                        if ($data['event_start']) {
                                            echo date_i18n('F j, Y \a\t g:i a', strtotime($data['event_start']));
                                            if ($data['event_end'] && $data['event_start'] !== $data['event_end']) {
                                                echo ' - ' . date_i18n('g:i a', strtotime($data['event_end']));
                                            }
                                        }
                                        ?>
                                    </p>

                                    <?php if ($data['event_venue']): ?>
                                    <p style="margin: 0 0 10px; font-size: 14px; color: #64748b;">
                                        <strong style="color: #1e293b;"><?php _e('Venue:', 'gps-courses'); ?></strong><br>
                                        <?php echo esc_html($data['event_venue']); ?>
                                    </p>
                                    <?php endif; ?>

                                    <?php if ($data['ce_credits'] > 0): ?>
                                    <p style="margin: 0; font-size: 14px; color: #64748b;">
                                        <strong style="color: #1e293b;"><?php _e('CE Credits:', 'gps-courses'); ?></strong>
                                        <?php echo (int) $data['ce_credits']; ?>
                                    </p>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- QR Code -->
                            <?php if (!empty($data['qr_code_url'])): ?>
                            <tr>
                                <td style="padding: 20px 40px; text-align: center; border-top: 1px solid #e2e8f0;">
                                    <h3 style="margin: 0 0 15px; font-size: 16px; font-weight: 600; color: #1e293b;">
                                        <?php _e('Scan the QR Code:', 'gps-courses'); ?>
                                    </h3>
                                    <img src="<?php echo esc_url($data['qr_code_url']); ?>"
                                         alt="<?php _e('Ticket QR Code', 'gps-courses'); ?>"
                                         style="max-width: 200px; height: auto; border: 2px solid #e2e8f0; border-radius: 8px;">
                                    <p style="margin: 15px 0 0; font-size: 12px; color: #94a3b8;">
                                        <?php _e('Present this QR code at the event entrance', 'gps-courses'); ?>
                                    </p>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Footer -->
                            <tr>
                                <td style="padding: 30px 40px; background-color: #f8fafc; border-radius: 0 0 8px 8px;">
                                    <p style="margin: 0; font-size: 14px; color: #64748b; text-align: center;">
                                        <?php _e('Thank you!', 'gps-courses'); ?>
                                    </p>
                                    <p style="margin: 10px 0 0; font-size: 12px; color: #94a3b8; text-align: center;">
                                        © <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
    }

    /**
     * Render CE credits email inline (fallback)
     */
    private static function render_credits_email_inline($data) {
        $user = $data['user'];
        $event = $data['event'];
        $credits = $data['credits'];
        $total_credits = $data['total_credits'];
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('CE Credits Awarded', 'gps-courses'); ?></title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5;">
                <tr>
                    <td align="center" style="padding: 40px 20px;">
                        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                            <!-- Header -->
                            <tr>
                                <td style="padding: 40px 40px 20px; text-align: center;">
                                    <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <span style="color: #ffffff; font-size: 36px; font-weight: bold;">🏆</span>
                                    </div>
                                    <h1 style="margin: 0; font-size: 28px; font-weight: bold; color: #1e293b;">
                                        <?php _e('Congratulations!', 'gps-courses'); ?>
                                    </h1>
                                    <p style="margin: 10px 0 0; font-size: 16px; color: #64748b;">
                                        <?php _e('You have earned CE credits', 'gps-courses'); ?>
                                    </p>
                                </td>
                            </tr>

                            <!-- Credits Info -->
                            <tr>
                                <td style="padding: 20px 40px;">
                                    <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 30px; border-radius: 8px; text-align: center;">
                                        <p style="margin: 0 0 10px; font-size: 14px; color: #0369a1; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                            <?php _e('Credits Earned', 'gps-courses'); ?>
                                        </p>
                                        <p style="margin: 0; font-size: 48px; font-weight: bold; color: #0c4a6e;">
                                            <?php echo (int) $credits; ?>
                                        </p>
                                        <p style="margin: 5px 0 0; font-size: 16px; color: #0369a1;">
                                            <?php _e('CE Credits', 'gps-courses'); ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Event Details -->
                            <tr>
                                <td style="padding: 20px 40px;">
                                    <h2 style="margin: 0 0 15px; font-size: 18px; font-weight: 600; color: #1e293b;">
                                        <?php _e('From Course:', 'gps-courses'); ?>
                                    </h2>
                                    <p style="margin: 0; font-size: 16px; color: #475569;">
                                        <?php echo esc_html($event->post_title); ?>
                                    </p>
                                </td>
                            </tr>

                            <!-- Total Credits -->
                            <tr>
                                <td style="padding: 20px 40px; background-color: #f8fafc;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td>
                                                <p style="margin: 0; font-size: 16px; font-weight: 600; color: #1e293b;">
                                                    <?php _e('Your Total CE Credits:', 'gps-courses'); ?>
                                                </p>
                                            </td>
                                            <td align="right">
                                                <p style="margin: 0; font-size: 24px; font-weight: bold; color: #3b82f6;">
                                                    <?php echo (int) $total_credits; ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- CTA -->
                            <tr>
                                <td style="padding: 30px 40px; text-align: center;">
                                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('ce-credits')); ?>"
                                       style="display: inline-block; padding: 14px 40px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">
                                        <?php _e('View All My Credits', 'gps-courses'); ?>
                                    </a>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="padding: 30px 40px; background-color: #f8fafc; border-radius: 0 0 8px 8px;">
                                    <p style="margin: 0; font-size: 14px; color: #64748b; text-align: center;">
                                        <?php _e('Keep up the great work!', 'gps-courses'); ?>
                                    </p>
                                    <p style="margin: 10px 0 0; font-size: 12px; color: #94a3b8; text-align: center;">
                                        © <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
    }

    /**
     * Send bulk email to attendees
     */
    public static function send_bulk_email($event_id, $subject, $message, $recipient_type = 'all') {
        global $wpdb;

        $results = [
            'sent' => 0,
            'failed' => 0,
            'emails' => []
        ];

        // Get attendees based on type
        $sql = "SELECT DISTINCT t.attendee_email, t.attendee_name, t.user_id
                FROM {$wpdb->prefix}gps_tickets t
                WHERE t.event_id = %d AND t.status = 'valid'";

        if ($recipient_type === 'checked_in') {
            $sql .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->prefix}gps_attendance a
                WHERE a.ticket_id = t.id
            )";
        } elseif ($recipient_type === 'not_checked_in') {
            $sql .= " AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->prefix}gps_attendance a
                WHERE a.ticket_id = t.id
            )";
        }

        $attendees = $wpdb->get_results($wpdb->prepare($sql, $event_id));

        // Email headers
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        foreach ($attendees as $attendee) {
            $email = $attendee->attendee_email;

            if (empty($email)) {
                $user = get_userdata($attendee->user_id);
                $email = $user ? $user->user_email : '';
            }

            if (empty($email) || !is_email($email)) {
                $results['failed']++;
                continue;
            }

            // Personalize message
            $personalized_message = str_replace(
                ['{{name}}', '{{email}}'],
                [$attendee->attendee_name, $email],
                $message
            );

            $sent = wp_mail($email, $subject, $personalized_message, $headers);

            if ($sent) {
                $results['sent']++;
                $results['emails'][] = $email;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
