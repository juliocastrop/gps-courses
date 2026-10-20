<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Seminar Notifications
 *
 * Automated emails for the Monthly Seminars, all sent through Emails
 * (catalog keys seminar_welcome, seminar_reminder, seminar_credits,
 * seminar_missed):
 * - Registration confirmation (welcome)
 * - Session reminders (days before each session: gps_seminar_reminder_offsets)
 * - Credits awarded after a check-in
 * - Missed session, the day after
 */
class Seminar_Notifications {

    /** Reminder offsets (days before a session) when none are saved. */
    const DEFAULT_REMINDER_OFFSETS = [14, 7, 1];

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

    /* ====================================================================
     * Senders (triggers and return values unchanged)
     * ==================================================================== */

    /**
     * Send registration confirmation email with QR code
     */
    public static function send_registration_confirmation($registration_id, $user_id, $seminar_id, $order_id) {
        $parts = self::email_welcome_parts($registration_id);
        if (!$parts) {
            error_log("GPS Seminars: welcome email skipped - registration $registration_id, user $user_id, seminar $seminar_id not found");
            return false;
        }
        return Emails::send_parts('seminar_welcome', $parts);
    }

    /**
     * Send credits awarded notification
     */
    public static function send_credits_notification($user_id, $seminar_id, $session_id, $credits) {
        return Emails::send_parts('seminar_credits', self::email_credits_parts($user_id, $seminar_id, $session_id, $credits));
    }

    /**
     * Send session reminder
     */
    public static function send_session_reminder($registration_id, $session_id, $days_before) {
        return Emails::send_parts('seminar_reminder', self::email_reminder_parts($registration_id, $session_id, $days_before));
    }

    /**
     * Send missed session notification
     */
    public static function send_missed_session_alert($registration_id, $session_id) {
        return Emails::send_parts('seminar_missed', self::email_missed_parts($registration_id, $session_id));
    }

    /* ====================================================================
     * Parts builders (catalog "parts") — all email content lives here
     * ==================================================================== */

    /** seminar_welcome: first session, check-in code, how the program works. */
    public static function email_welcome_parts($registration_id) {
        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration) {
            return null;
        }
        $user = get_userdata($registration->user_id);
        $seminar = Seminars::get_seminar($registration->seminar_id);
        if (!$user || !$seminar) {
            return null;
        }

        $seminar_id = (int) $registration->seminar_id;
        $total = self::program_sessions($seminar_id);
        $sessions = Seminars::get_sessions($seminar_id);

        // First session: the first one on or after the registration's start date
        $first = null;
        $from = $registration->start_session_date ? $registration->start_session_date : substr((string) $registration->registration_date, 0, 10);
        foreach ($sessions as $s) {
            if (!$from || $s->session_date >= $from) {
                $first = $s;
                break;
            }
        }

        $blocks = '';
        if ($first) {
            $blocks .= Emails::paragraph('<strong>' . esc_html__('Your first session', 'gps-courses') . '</strong>');
            $blocks .= self::session_panel($first, $total, true);
        }

        $qr_url = self::get_qr_code_url($registration);
        if (!empty($registration->qr_code)) {
            $blocks .= Emails::code_box(
                __('Your check-in code', 'gps-courses'),
                $registration->qr_code,
                __('Show this QR code at each session to check in.', 'gps-courses'),
                $qr_url
            );
        }

        $per = self::credits_per_session();
        $blocks .= Emails::paragraph('<strong>' . esc_html__('How it works', 'gps-courses') . '</strong>');
        $blocks .= Emails::paragraph(esc_html(sprintf(
            /* translators: 1: sessions, 2: CE per session, 3: CE total */
            __('The program has %1$d sessions. Each session you attend earns %2$s CE credits (%3$s in total); attendance is required to earn them. Certificates are issued on June 30 and December 31 for the sessions you attended in each half of the year.', 'gps-courses'),
            $total,
            Credits::format($per),
            Credits::format($per * $total)
        )));

        if (count($sessions) > 1) {
            $rows = [[esc_html__('Session', 'gps-courses'), esc_html__('Date', 'gps-courses'), esc_html__('Topic', 'gps-courses')]];
            foreach ($sessions as $s) {
                $rows[] = [
                    (int) $s->session_number,
                    '<span style="white-space:nowrap;">' . esc_html(date_i18n('M j, Y', strtotime($s->session_date))) . '</span>',
                    esc_html((string) $s->topic),
                ];
            }
            $blocks .= Emails::paragraph('<strong>' . esc_html__('Session schedule', 'gps-courses') . '</strong>');
            $blocks .= Emails::table($rows);
        }

        return [
            'to'     => $user->user_email,
            'vars'   => [
                'first_name' => Emails::first_name($user),
                'seminar'    => self::seminar_title($seminar),
            ],
            'blocks' => $blocks,
            'opts'   => ['button_url' => self::seminars_url()],
        ];
    }

    /**
     * seminar_reminder: one key for every offset (14 / 7 / 1 days ...).
     */
    public static function email_reminder_parts($registration_id, $session_id, $days_before) {
        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration) {
            return null;
        }
        $user = get_userdata($registration->user_id);
        $session = self::get_session($session_id);
        if (!$user || !$session) {
            return null;
        }
        $seminar = Seminars::get_seminar($session->seminar_id);
        $total = self::program_sessions($session->seminar_id);
        $days = max(1, (int) $days_before);

        $blocks = self::session_panel($session, $total, true)
                . self::progress_block($registration, $total)
                . Emails::paragraph(esc_html__('Please bring your check-in QR code.', 'gps-courses'), true);

        return [
            'to'     => $user->user_email,
            'vars'   => [
                'first_name'     => Emails::first_name($user),
                'seminar'        => self::seminar_title($seminar),
                'session_number' => (int) $session->session_number,
                'date'           => date_i18n('D, M j', strtotime($session->session_date)),
                /* translators: %d: number of days */
                'days_label'     => sprintf(_n('%d day', '%d days', $days, 'gps-courses'), $days),
            ],
            'blocks' => $blocks,
            'opts'   => ['button_url' => self::seminars_url()],
        ];
    }

    /** seminar_credits: credits badge for the session + total progress. */
    public static function email_credits_parts($user_id, $seminar_id, $session_id, $credits) {
        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }
        $registration = Seminar_Registrations::get_user_registration($user_id, $seminar_id);
        if (!$registration) {
            return null;
        }
        $seminar = Seminars::get_seminar($seminar_id);
        $session = self::get_session($session_id);
        $total = self::program_sessions($seminar_id);
        $title = self::seminar_title($seminar);

        $lines = [];
        if ($session) {
            $lines[] = esc_html(sprintf(
                /* translators: 1: session number, 2: topic */
                __('Session %1$d%2$s', 'gps-courses'),
                (int) $session->session_number,
                $session->topic ? ': ' . $session->topic : ''
            ));
            $lines[] = esc_html(date_i18n('M j, Y', strtotime($session->session_date)));
        }

        $blocks = Emails::credits_badge(Credits::format($credits), 'CE', $title, $lines)
                . self::progress_block($registration, $total);

        return [
            'to'     => $user->user_email,
            'vars'   => [
                'first_name'     => Emails::first_name($user),
                'seminar'        => $title,
                'session_number' => $session ? (int) $session->session_number : '',
                'credits'        => Credits::format($credits),
            ],
            'blocks' => $blocks,
            'opts'   => ['button_url' => self::seminars_url()],
        ];
    }

    /** seminar_missed: the session that was missed. */
    public static function email_missed_parts($registration_id, $session_id) {
        $registration = Seminar_Registrations::get_registration($registration_id);
        if (!$registration) {
            return null;
        }
        $user = get_userdata($registration->user_id);
        $session = self::get_session($session_id);
        if (!$user || !$session) {
            return null;
        }
        $seminar = Seminars::get_seminar($session->seminar_id);
        $total = self::program_sessions($session->seminar_id);

        $blocks = self::session_panel($session, $total, false)
                . Emails::paragraph(esc_html__('Attendance is required to earn CE credits. We hope to see you at the next session.', 'gps-courses'));

        return [
            'to'     => $user->user_email,
            'vars'   => [
                'first_name'     => Emails::first_name($user),
                'seminar'        => self::seminar_title($seminar),
                'session_number' => (int) $session->session_number,
            ],
            'blocks' => $blocks,
            'opts'   => ['button_url' => self::seminars_url()],
        ];
    }

    /* ====================================================================
     * Samples (catalog "sample") — read-only, latest real records
     * ==================================================================== */

    public static function email_welcome_sample() {
        global $wpdb;
        $id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}gps_seminar_registrations ORDER BY id DESC LIMIT 1");
        return $id ? self::email_welcome_parts($id) : null;
    }

    public static function email_reminder_sample() {
        global $wpdb;
        $registration = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}gps_seminar_registrations WHERE status = 'active' ORDER BY id DESC LIMIT 1");
        if (!$registration) {
            return null;
        }
        $session = Seminars::get_next_session($registration->seminar_id);
        if (!$session) {
            $session = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE seminar_id = %d ORDER BY session_date DESC LIMIT 1",
                $registration->seminar_id
            ));
        }
        if (!$session) {
            return null;
        }
        $days = (int) round((strtotime($session->session_date) - strtotime(current_time('Y-m-d'))) / DAY_IN_SECONDS);
        return self::email_reminder_parts($registration->id, $session->id, $days >= 1 ? $days : 7);
    }

    public static function email_credits_sample() {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT sa.user_id, sa.seminar_id, sa.session_id, sa.credits_awarded
             FROM {$wpdb->prefix}gps_seminar_attendance sa
             INNER JOIN {$wpdb->prefix}gps_seminar_registrations sr ON sr.id = sa.registration_id
             WHERE sr.status IN ('active', 'completed')
             ORDER BY sa.id DESC LIMIT 1"
        );
        return $row ? self::email_credits_parts($row->user_id, $row->seminar_id, $row->session_id, $row->credits_awarded) : null;
    }

    public static function email_missed_sample() {
        global $wpdb;
        $today = current_time('Y-m-d');
        // Latest past session with a registrant who was not checked in to it
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT sr.id AS registration_id, ss.id AS session_id
             FROM {$wpdb->prefix}gps_seminar_sessions ss
             INNER JOIN {$wpdb->prefix}gps_seminar_registrations sr ON sr.seminar_id = ss.seminar_id
             LEFT JOIN {$wpdb->prefix}gps_seminar_attendance sa ON sa.registration_id = sr.id AND sa.session_id = ss.id
             WHERE ss.session_date < %s AND sa.id IS NULL
             ORDER BY ss.session_date DESC, sr.id DESC LIMIT 1",
            $today
        ));
        if (!$row) {
            // Any past session with any registrant
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT sr.id AS registration_id, ss.id AS session_id
                 FROM {$wpdb->prefix}gps_seminar_sessions ss
                 INNER JOIN {$wpdb->prefix}gps_seminar_registrations sr ON sr.seminar_id = ss.seminar_id
                 WHERE ss.session_date < %s
                 ORDER BY ss.session_date DESC, sr.id DESC LIMIT 1",
                $today
            ));
        }
        return $row ? self::email_missed_parts($row->registration_id, $row->session_id) : null;
    }

    /* ====================================================================
     * Cron
     * ==================================================================== */

    /**
     * Reminder offsets in days before a session (Emails › Schedule).
     *
     * @return int[]
     */
    public static function reminder_offsets() {
        $saved = get_option('gps_seminar_reminder_offsets', self::DEFAULT_REMINDER_OFFSETS);
        if (!is_array($saved)) {
            $saved = self::DEFAULT_REMINDER_OFFSETS;
        }
        $offsets = array_values(array_unique(array_filter(array_map('intval', $saved), function ($d) {
            return $d > 0;
        })));
        sort($offsets);
        return $offsets;
    }

    /**
     * Process daily notifications (cron job)
     */
    public static function process_daily_notifications() {
        global $wpdb;

        $today = current_time('Y-m-d');
        $offsets = self::reminder_offsets();

        if ($offsets) {
            // Sessions within the furthest reminder offset
            $upcoming_sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions
                 WHERE session_date BETWEEN %s AND DATE_ADD(%s, INTERVAL %d DAY)
                 ORDER BY session_date ASC",
                $today,
                $today,
                max($offsets)
            ));

            foreach ($upcoming_sessions as $session) {
                // Whole days (round: a DST change makes a day 23 or 25 hours)
                $days_until = (int) round((strtotime($session->session_date) - strtotime($today)) / 86400);

                if (in_array($days_until, $offsets, true)) {
                    self::send_reminders_for_session($session->id, $days_until);
                }
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
        $session = self::get_session($session_id);
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

        $session = self::get_session($session_id);
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

    /* ====================================================================
     * Helpers
     * ==================================================================== */

    private static function get_session($session_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
            $session_id
        ));
    }

    /** Sessions in the program (10), as the seminar page counts them. */
    private static function program_sessions($seminar_id) {
        return max(1, (int) apply_filters('gpsc_seminar_program_sessions', Seminar_Page::PROGRAM_SESSIONS, (int) $seminar_id));
    }

    private static function credits_per_session() {
        return Seminar_Page::CE_PER_SESSION;
    }

    private static function seminar_title($seminar) {
        if (is_array($seminar) && !empty($seminar['title'])) {
            return (string) $seminar['title'];
        }
        return __('GPS Monthly Seminars', 'gps-courses');
    }

    private static function seminars_url() {
        return function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('gps-seminars')
            : home_url('/my-account/gps-seminars/');
    }

    /** Venue / street / city lines from Seminars › Settings, escaped, one per line. */
    private static function location_html() {
        $raw = (string) get_option(Seminar_Page::OPT_LOCATION, Seminar_Page::default_location());
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));
        return implode('<br>', array_map('esc_html', $lines));
    }

    /**
     * Session details panel: badge "Session N of 10", topic, date, time,
     * location and (optionally) CE credits.
     */
    private static function session_panel($session, $total, $with_credits) {
        $rows = [
            __('Date', 'gps-courses') => esc_html(date_i18n('l, F j, Y', strtotime($session->session_date))),
        ];
        $time = Seminar_Page::time_range($session->session_time_start, $session->session_time_end);
        if ('' !== $time) {
            $rows[__('Time', 'gps-courses')] = esc_html($time);
        }
        $location = self::location_html();
        if ('' !== $location) {
            $rows[__('Location', 'gps-courses')] = $location;
        }
        if ($with_credits) {
            $rows[__('CE credits', 'gps-courses')] = esc_html(Credits::format(self::credits_per_session()) . ' CE');
        }
        /* translators: 1: session number, 2: sessions in the program */
        $badge = sprintf(__('Session %1$d of %2$d', 'gps-courses'), (int) $session->session_number, (int) $total);
        $title = '' !== trim((string) $session->topic)
            ? (string) $session->topic
            /* translators: %d: session number */
            : sprintf(__('Session %d', 'gps-courses'), (int) $session->session_number);
        return Emails::panel($title, $rows, $badge);
    }

    /** Progress bar: "X of 10 sessions attended · Y CE earned". */
    private static function progress_block($registration, $total) {
        global $wpdb;
        $attended = (int) $registration->sessions_completed;
        $earned = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(credits_awarded) FROM {$wpdb->prefix}gps_seminar_attendance WHERE registration_id = %d",
            $registration->id
        ));
        $percent = $total > 0 ? round($attended / $total * 100) : 0;
        return Emails::progress($percent, sprintf(
            /* translators: 1: attended, 2: total sessions, 3: CE credits */
            __('%1$d of %2$d sessions attended · %3$s CE earned', 'gps-courses'),
            $attended,
            $total,
            Credits::format($earned)
        ));
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
}
