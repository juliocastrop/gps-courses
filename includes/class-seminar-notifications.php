<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Seminar Notifications
 *
 * Handles automated email notifications for Monthly Seminars:
 * - Registration confirmation
 * - Session reminders (2 weeks, 1 week, 1 day before)
 * - Missed session alerts
 * - Makeup session availability
 * - Certificate delivery (June/December)
 */
class Seminar_Notifications {

    /**
     * Initialize
     */
    public static function init() {
        // Hook into registration
        add_action('gps_seminar_registered', [__CLASS__, 'send_registration_confirmation'], 10, 4);

        // Hook into credits awarded
        add_action('gps_seminar_credits_awarded', [__CLASS__, 'send_credits_notification'], 10, 4);

        // Schedule daily cron for reminders
        add_action('gps_seminar_daily_cron', [__CLASS__, 'process_daily_notifications']);

        // Register custom cron schedule
        if (!wp_next_scheduled('gps_seminar_daily_cron')) {
            wp_schedule_event(strtotime('tomorrow 08:00:00'), 'daily', 'gps_seminar_daily_cron');
        }
    }

    /**
     * Send registration confirmation email with QR code
     */
    public static function send_registration_confirmation($registration_id, $user_id, $seminar_id, $order_id) {
        error_log("GPS Seminars: send_registration_confirmation called - Registration: $registration_id, User: $user_id, Seminar: $seminar_id, Order: $order_id");

        $registration = Seminar_Registrations::get_registration($registration_id);
        $seminar = Seminars::get_seminar($seminar_id);
        $user = get_userdata($user_id);

        if (!$registration) {
            error_log("GPS Seminars: Registration not found - ID: $registration_id");
            return false;
        }
        if (!$seminar) {
            error_log("GPS Seminars: Seminar not found - ID: $seminar_id");
            return false;
        }
        if (!$user) {
            error_log("GPS Seminars: User not found - ID: $user_id");
            return false;
        }

        error_log("GPS Seminars: Preparing email to: " . $user->user_email);

        $to = $user->user_email;
        $subject = sprintf(__('Welcome to GPS Monthly Seminars %s', 'gps-courses'), $seminar['year']);

        // Get upcoming sessions
        $sessions = Seminars::get_sessions($seminar_id);
        $sessions_html = '';
        foreach ($sessions as $session) {
            $sessions_html .= sprintf(
                '<li style="padding: 15px; border-bottom: 1px solid #e2e8f0; list-style: none; margin-left: -20px;">
                    <div style="display: table; width: 100%%;">
                        <div style="display: table-cell; width: 50px; vertical-align: top;">
                            <span style="display: inline-block; width: 35px; height: 35px; background-color: #3b82f6; color: #ffffff; border-radius: 50%%; text-align: center; line-height: 35px; font-weight: bold; font-size: 14px;">%d</span>
                        </div>
                        <div style="display: table-cell; vertical-align: top;">
                            <strong style="color: #1e293b; font-size: 16px;">%s</strong><br>
                            <span style="color: #64748b; font-size: 14px;">📅 %s</span>
                        </div>
                    </div>
                </li>',
                $session->session_number,
                esc_html($session->topic),
                date('F j, Y', strtotime($session->session_date))
            );
        }

        $message = '
        <h1 style="margin: 0 0 20px 0; font-size: 28px; font-weight: bold; color: #1e293b;">
            Welcome to GPS Monthly Seminars!
        </h1>
        <p style="margin: 0 0 20px 0; font-size: 16px; color: #64748b;">
            Dear ' . esc_html($user->display_name) . ',
        </p>
        <p style="margin: 0 0 30px 0; font-size: 16px; color: #475569;">
            Thank you for registering for the <strong>GPS Monthly Seminars ' . esc_html($seminar['year']) . '</strong>!
        </p>

        <!-- Registration Details Card -->
        <div style="background-color: #f8fafc; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
            <h2 style="margin: 0 0 15px 0; font-size: 20px; font-weight: 600; color: #1e293b;">
                Your Registration Details
            </h2>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #64748b;">
                        <strong style="color: #1e293b;">Registration ID:</strong>
                    </td>
                    <td style="padding: 8px 0; font-size: 14px; color: #1e293b; text-align: right;">
                        #' . $registration_id . '
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #64748b;">
                        <strong style="color: #1e293b;">QR Code:</strong>
                    </td>
                    <td style="padding: 8px 0; font-size: 14px; color: #1e293b; text-align: right;">
                        <code style="background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-size: 12px;">' . esc_html($registration->qr_code) . '</code>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #64748b;">
                        <strong style="color: #1e293b;">Total Sessions:</strong>
                    </td>
                    <td style="padding: 8px 0; font-size: 14px; color: #1e293b; text-align: right;">
                        10 sessions
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-size: 14px; color: #64748b; border-top: 2px solid #e2e8f0; padding-top: 15px;">
                        <strong style="color: #1e293b;">Total CE Credits:</strong>
                    </td>
                    <td style="padding: 8px 0; font-size: 18px; font-weight: bold; color: #2271b1; text-align: right; border-top: 2px solid #e2e8f0; padding-top: 15px;">
                        20 Credits
                    </td>
                </tr>
            </table>
        </div>

        <!-- Important Information -->
        <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 30px;">
            <h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: 600; color: #92400e;">
                ⚠️ Important Information
            </h3>
            <ul style="margin: 0; padding-left: 20px; color: #78350f;">
                <li style="margin-bottom: 8px;"><strong>Location:</strong> GPS Dental Training Center, 6320 Sugarloaf Parkway, Duluth, GA 30097</li>
                <li style="margin-bottom: 8px;"><strong>Duration:</strong> Each session is 2 hours (6:00 PM - 8:00 PM)</li>
                <li style="margin-bottom: 8px;"><strong>Attendance:</strong> Mandatory for CE credits</li>
                <li style="margin-bottom: 8px;"><strong>Makeup Sessions:</strong> One allowed per calendar year</li>
                <li style="margin-bottom: 0;"><strong>Tuition:</strong> Non-refundable</li>
            </ul>
        </div>

        <!-- Session Schedule -->
        <h2 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 600; color: #1e293b;">
            📅 Session Schedule
        </h2>
        <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 30px;">
            <ul style="margin: 0; padding: 20px 20px 20px 40px; list-style: none;">
                ' . $sessions_html . '
            </ul>
        </div>

        <!-- QR Code Section -->
        <div style="background-color: #f8fafc; padding: 30px; border-radius: 8px; text-align: center; margin-bottom: 30px; border: 2px dashed #cbd5e1;">
            <h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: 600; color: #1e293b;">
                📱 Your QR Code for Check-In
            </h3>
            <p style="margin: 0 0 20px 0; font-size: 14px; color: #64748b;">
                Present this QR code at each session. Save this email or download the image.
            </p>
            <img src="' . esc_url(self::get_qr_code_url($registration)) . '" alt="QR Code" style="max-width: 250px; height: auto; border: 3px solid #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" />
        </div>

        <!-- CE Certificates Info -->
        <div style="background-color: #eff6ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6;">
            <h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: 600; color: #1e40af;">
                🏆 CE Credit Certificates
            </h3>
            <p style="margin: 0 0 15px 0; font-size: 14px; color: #1e40af;">
                Certificates will be issued twice per year:
            </p>
            <ul style="margin: 0; padding-left: 20px; color: #1e40af;">
                <li style="margin-bottom: 8px;"><strong>June 30:</strong> For sessions completed January-June</li>
                <li style="margin-bottom: 0;"><strong>December 31:</strong> For sessions completed July-December</li>
            </ul>
        </div>

        <p style="margin: 30px 0 10px 0; font-size: 16px; color: #475569;">
            We look forward to seeing you at the first session!
        </p>
        ';

        $result = self::send_email($to, $subject, $message);
        error_log("GPS Seminars: Email send result: " . ($result ? 'SUCCESS' : 'FAILED'));
        return $result;
    }

    /**
     * Send credits awarded notification
     */
    public static function send_credits_notification($user_id, $seminar_id, $session_id, $credits) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Get progress
        $registration = Seminar_Registrations::get_user_registration($user_id, $seminar_id);
        if (!$registration) {
            return false;
        }

        $progress = Seminar_Registrations::get_user_progress($registration->id);

        $to = $user->user_email;
        $subject = sprintf(__('🏆 CE Credits Awarded - %d Credits', 'gps-courses'), $credits);

        $message = '
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                <span style="color: #ffffff; font-size: 36px;">🏆</span>
            </div>
            <h1 style="margin: 0 0 10px 0; font-size: 28px; font-weight: bold; color: #1e293b;">
                Congratulations!
            </h1>
            <p style="margin: 0; font-size: 16px; color: #64748b;">
                You have earned CE credits
            </p>
        </div>

        <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 30px; border-radius: 8px; text-align: center; margin-bottom: 30px;">
            <p style="margin: 0 0 10px; font-size: 14px; color: #0369a1; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                Credits Earned
            </p>
            <p style="margin: 0; font-size: 48px; font-weight: bold; color: #0c4a6e;">
                ' . (int) $credits . '
            </p>
            <p style="margin: 5px 0 0; font-size: 16px; color: #0369a1;">
                CE Credits
            </p>
        </div>

        <p style="margin: 0 0 20px 0; font-size: 16px; color: #475569;">
            Dear ' . esc_html($user->display_name) . ',
        </p>
        <p style="margin: 0 0 30px 0; font-size: 16px; color: #475569;">
            You have been awarded <strong style="color: #2271b1;">' . $credits . ' CE credits</strong> for attending a GPS Monthly Seminar session.
        </p>

        <div style="background-color: #f8fafc; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
            <h2 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 600; color: #1e293b;">
                📊 Your Progress
            </h2>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 12px; background-color: #ffffff; border-radius: 6px 6px 0 0;">
                        <div style="font-size: 14px; color: #64748b; margin-bottom: 5px;">Sessions Completed</div>
                        <div style="font-size: 24px; font-weight: bold; color: #3b82f6;">' . $registration->sessions_completed . ' <span style="font-size: 16px; color: #94a3b8;">/ 10</span></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px; background-color: #ffffff; border-top: 1px solid #e2e8f0;">
                        <div style="font-size: 14px; color: #64748b; margin-bottom: 5px;">Sessions Remaining</div>
                        <div style="font-size: 24px; font-weight: bold; color: #10b981;">' . $registration->sessions_remaining . '</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px; background-color: #eff6ff; border-radius: 0 0 6px 6px; border-top: 2px solid #3b82f6;">
                        <div style="font-size: 14px; color: #1e40af; margin-bottom: 5px; font-weight: 600;">Total CE Credits Earned</div>
                        <div style="font-size: 28px; font-weight: bold; color: #1e40af;">' . $progress['total_credits'] . '</div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #10b981;">
            <p style="margin: 0; font-size: 18px; font-weight: 600; color: #065f46;">
                ✨ Keep up the great work!
            </p>
        </div>
        ';

        return self::send_email($to, $subject, $message);
    }

    /**
     * Send session reminder
     */
    public static function send_session_reminder($registration_id, $session_id, $days_before) {
        $registration = Seminar_Registrations::get_registration($registration_id);
        $user = get_userdata($registration->user_id);

        if (!$registration || !$user) {
            return false;
        }

        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
            $session_id
        ));

        if (!$session) {
            return false;
        }

        $to = $user->user_email;

        if ($days_before == 14) {
            $subject = sprintf(__('Upcoming Session in 2 Weeks - %s', 'gps-courses'), $session->topic);
        } elseif ($days_before == 7) {
            $subject = sprintf(__('Session Next Week - %s', 'gps-courses'), $session->topic);
        } else {
            $subject = sprintf(__('Session Tomorrow - %s', 'gps-courses'), $session->topic);
        }

        $session_date = date('l, F j, Y', strtotime($session->session_date));
        $session_time_start = date('g:i A', strtotime($session->session_time_start));
        $session_time_end = date('g:i A', strtotime($session->session_time_end));

        $reminder_icon = $days_before == 14 ? '📅' : ($days_before == 7 ? '⏰' : '🔔');
        $urgency_color = $days_before == 1 ? '#dc2626' : '#3b82f6';

        $message = '
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="font-size: 48px; margin-bottom: 15px;">' . $reminder_icon . '</div>
            <h1 style="margin: 0 0 10px 0; font-size: 28px; font-weight: bold; color: #1e293b;">
                Upcoming Session Reminder
            </h1>
            <p style="margin: 0; font-size: 16px; color: #64748b;">
                GPS Monthly Seminar - Session #' . $session->session_number . '
            </p>
        </div>

        <p style="margin: 0 0 30px 0; font-size: 16px; color: #475569;">
            Dear ' . esc_html($user->display_name) . ',
        </p>

        <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid ' . $urgency_color . '; margin-bottom: 30px;">
            <p style="margin: 0; font-size: 18px; font-weight: 600; color: #78350f;">
                This session is coming up in ' . ($days_before == 14 ? '2 weeks' : ($days_before == 7 ? '1 week' : '1 day')) . '!
            </p>
        </div>

        <div style="background-color: #f8fafc; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
            <h2 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 600; color: #1e293b;">
                📚 Session Details
            </h2>

            <div style="margin-bottom: 15px;">
                <div style="display: inline-block; width: 40px; height: 40px; background-color: #3b82f6; color: #ffffff; border-radius: 50%; text-align: center; line-height: 40px; font-weight: bold; font-size: 16px; margin-right: 15px; vertical-align: middle;">' . $session->session_number . '</div>
                <span style="font-size: 18px; font-weight: 600; color: #1e293b; vertical-align: middle;">' . esc_html($session->topic) . '</span>
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 20px;">
                <tr>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0;">
                        <div style="font-size: 14px; color: #64748b; margin-bottom: 5px;">📅 Date</div>
                        <div style="font-size: 16px; font-weight: 600; color: #1e293b;">' . $session_date . '</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0;">
                        <div style="font-size: 14px; color: #64748b; margin-bottom: 5px;">🕐 Time</div>
                        <div style="font-size: 16px; font-weight: 600; color: #1e293b;">' . $session_time_start . ' - ' . $session_time_end . '</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-top: 1px solid #e2e8f0;">
                        <div style="font-size: 14px; color: #64748b; margin-bottom: 5px;">📍 Location</div>
                        <div style="font-size: 16px; font-weight: 600; color: #1e293b;">
                            GPS Dental Training Center<br>
                            <span style="font-weight: normal; font-size: 14px; color: #64748b;">6320 Sugarloaf Parkway, Duluth, GA 30097</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="background-color: #eff6ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; margin-bottom: 30px;">
            <p style="margin: 0; font-size: 16px; font-weight: 600; color: #1e40af;">
                📱 Please bring your QR code for check-in
            </p>
        </div>

        <p style="margin: 0; font-size: 16px; color: #475569; text-align: center;">
            We look forward to seeing you!
        </p>
        ';

        return self::send_email($to, $subject, $message);
    }

    /**
     * Send missed session notification
     */
    public static function send_missed_session_alert($registration_id, $session_id) {
        $registration = Seminar_Registrations::get_registration($registration_id);
        $user = get_userdata($registration->user_id);

        if (!$registration || !$user) {
            return false;
        }

        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
            $session_id
        ));

        $to = $user->user_email;
        $subject = __('⚠️ Missed Session - Makeup Option Available', 'gps-courses');

        $makeup_available = !$registration->makeup_used;

        $message = '
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="font-size: 48px; margin-bottom: 15px;">😔</div>
            <h1 style="margin: 0 0 10px 0; font-size: 28px; font-weight: bold; color: #1e293b;">
                Missed Session Notice
            </h1>
            <p style="margin: 0; font-size: 16px; color: #64748b;">
                GPS Monthly Seminar
            </p>
        </div>

        <p style="margin: 0 0 30px 0; font-size: 16px; color: #475569;">
            Dear ' . esc_html($user->display_name) . ',
        </p>

        <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); padding: 20px; border-radius: 8px; border-left: 4px solid #dc2626; margin-bottom: 30px;">
            <p style="margin: 0 0 10px 0; font-size: 16px; font-weight: 600; color: #7f1d1d;">
                We noticed you were not able to attend the following session:
            </p>
            <div style="margin-top: 15px; background-color: rgba(255,255,255,0.5); padding: 15px; border-radius: 6px;">
                <div style="margin-bottom: 10px;">
                    <div style="display: inline-block; width: 35px; height: 35px; background-color: #dc2626; color: #ffffff; border-radius: 50%; text-align: center; line-height: 35px; font-weight: bold; font-size: 14px; margin-right: 10px; vertical-align: middle;">' . $session->session_number . '</div>
                    <span style="font-size: 16px; font-weight: 600; color: #7f1d1d; vertical-align: middle;">' . esc_html($session->topic) . '</span>
                </div>
                <div style="font-size: 14px; color: #991b1b;">
                    📅 ' . date('F j, Y', strtotime($session->session_date)) . '
                </div>
            </div>
        </div>

        ' . ($makeup_available ? '
        <div style="background-color: #d1fae5; padding: 25px; border-radius: 8px; border-left: 4px solid #10b981; margin-bottom: 30px;">
            <h2 style="margin: 0 0 15px 0; font-size: 20px; font-weight: 600; color: #065f46;">
                ✅ Makeup Session Available
            </h2>
            <p style="margin: 0 0 10px 0; font-size: 16px; color: #047857;">
                You are eligible to attend one makeup session. Contact us to arrange a makeup session at a future date.
            </p>
            <p style="margin: 0; font-size: 14px; color: #059669;">
                This will ensure you can still earn your CE credits for this session.
            </p>
        </div>
        ' : '
        <div style="background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%); padding: 20px; border-radius: 8px; border-left: 4px solid #f97316; margin-bottom: 30px;">
            <h2 style="margin: 0 0 10px 0; font-size: 18px; font-weight: 600; color: #7c2d12;">
                ⚠️ Makeup Session Already Used
            </h2>
            <p style="margin: 0; font-size: 16px; color: #9a3412;">
                You have already used your one allowed makeup session for this calendar year.
            </p>
        </div>
        ') . '

        <div style="background-color: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 30px;">
            <p style="margin: 0; font-size: 16px; color: #475569;">
                <strong style="color: #1e293b;">⚠️ Important Reminder:</strong> Attendance is mandatory to receive CE credits. Please make every effort to attend all remaining sessions.
            </p>
        </div>

        <p style="margin: 0 0 10px 0; font-size: 16px; color: #475569;">
            If you have any questions, please contact us.
        </p>
        ';

        return self::send_email($to, $subject, $message);
    }

    /**
     * Process daily notifications (cron job)
     */
    public static function process_daily_notifications() {
        global $wpdb;

        $today = current_time('Y-m-d');

        // Get upcoming sessions in next 14 days
        $upcoming_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions
             WHERE session_date BETWEEN %s AND DATE_ADD(%s, INTERVAL 14 DAY)
             ORDER BY session_date ASC",
            $today,
            $today
        ));

        foreach ($upcoming_sessions as $session) {
            $days_until = (strtotime($session->session_date) - strtotime($today)) / 86400;

            // Send reminders at 14, 7, and 1 day before
            if (in_array($days_until, [14, 7, 1])) {
                self::send_reminders_for_session($session->id, (int) $days_until);
            }
        }

        // Check for missed sessions (yesterday)
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterday_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE session_date = %s",
            $yesterday
        ));

        foreach ($yesterday_sessions as $session) {
            self::check_missed_attendance($session->id);
        }
    }

    /**
     * Send reminders for all registrants of a session
     */
    private static function send_reminders_for_session($session_id, $days_before) {
        global $wpdb;

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
            $session_id
        ));

        if (!$session) {
            return;
        }

        // Get all active registrations for this seminar
        $registrations = Seminar_Registrations::get_seminar_registrations($session->seminar_id, 'active');

        foreach ($registrations as $registration) {
            self::send_session_reminder($registration->id, $session_id, $days_before);
        }
    }

    /**
     * Check for missed attendance and send alerts
     */
    private static function check_missed_attendance($session_id) {
        global $wpdb;

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
            $session_id
        ));

        if (!$session) {
            return;
        }

        // Get registrants who didn't attend
        $missed = $wpdb->get_results($wpdb->prepare(
            "SELECT sr.id as registration_id
             FROM {$wpdb->prefix}gps_seminar_registrations sr
             LEFT JOIN {$wpdb->prefix}gps_seminar_attendance sa
                ON sr.id = sa.registration_id AND sa.session_id = %d
             WHERE sr.seminar_id = %d
             AND sr.status = 'active'
             AND sa.id IS NULL",
            $session_id,
            $session->seminar_id
        ));

        foreach ($missed as $reg) {
            self::send_missed_session_alert($reg->registration_id, $session_id);
        }
    }

    /**
     * Get QR code URL
     */
    private static function get_qr_code_url($registration) {
        if (empty($registration->qr_code_path)) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/' . $registration->qr_code_path;
    }

    /**
     * Get email settings from Email_Settings class
     */
    private static function get_email_settings() {
        return [
            'logo' => get_option('gps_email_logo', ''),
            'from_name' => get_option('gps_email_from_name', get_bloginfo('name')),
            'from_email' => get_option('gps_email_from_email', get_bloginfo('admin_email')),
            'header_bg_color' => get_option('gps_email_header_bg_color', '#2271b1'),
            'header_text_color' => get_option('gps_email_header_text_color', '#ffffff'),
            'body_bg_color' => get_option('gps_email_body_bg_color', '#f5f5f5'),
            'body_text_color' => get_option('gps_email_body_text_color', '#333333'),
            'footer_text' => get_option('gps_email_footer_text', ''),
        ];
    }

    /**
     * Wrap message in branded email template
     */
    private static function wrap_email_template($content, $title = '') {
        $settings = self::get_email_settings();

        $logo_html = '';
        if (!empty($settings['logo'])) {
            $logo_html = '<img src="' . esc_url($settings['logo']) . '" alt="' . esc_attr($settings['from_name']) . '" style="max-width: 200px; height: auto; margin-bottom: 20px;">';
        }

        $footer_text = !empty($settings['footer_text']) ? $settings['footer_text'] : 'Thank you for choosing ' . get_bloginfo('name');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?></title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: <?php echo esc_attr($settings['body_bg_color']); ?>;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: <?php echo esc_attr($settings['body_bg_color']); ?>;">
                <tr>
                    <td align="center" style="padding: 40px 20px;">
                        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                            <!-- Header with Logo -->
                            <?php if (!empty($logo_html)): ?>
                            <tr>
                                <td style="padding: 30px 40px; text-align: center; background-color: <?php echo esc_attr($settings['header_bg_color']); ?>; border-radius: 8px 8px 0 0;">
                                    <?php echo $logo_html; ?>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px 40px 20px; color: <?php echo esc_attr($settings['body_text_color']); ?>;">
                                    <?php echo $content; ?>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="padding: 30px 40px; background-color: #f8fafc; border-radius: 0 0 8px 8px;">
                                    <p style="margin: 0; font-size: 14px; color: #64748b; text-align: center;">
                                        <?php echo esc_html($footer_text); ?>
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
        return ob_get_clean();
    }

    /**
     * Send email helper
     */
    private static function send_email($to, $subject, $message) {
        error_log("GPS Seminars: Sending email - To: $to, Subject: $subject");

        $settings = self::get_email_settings();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
        ];

        $wrapped_message = self::wrap_email_template($message, $subject);

        $result = wp_mail($to, $subject, $wrapped_message, $headers);

        if (!$result) {
            global $phpmailer;
            if (isset($phpmailer) && is_object($phpmailer)) {
                error_log("GPS Seminars: wp_mail error - " . $phpmailer->ErrorInfo);
            } else {
                error_log("GPS Seminars: wp_mail returned false (no error info available)");
            }
        }

        return $result;
    }
}
