<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Every email the site sends, in one list: what it is, when it goes out, to
 * whom, its editable texts (subject, heading, intro, button) and whether it is
 * switched on. The Emails › All emails screen is built from this list, and
 * Emails::send() reads texts and the on/off switch from it.
 *
 * Each entry may name two callables, both "Class::method" strings:
 *   parts  — builds the real email from a record: returns
 *            ['to', 'vars', 'blocks', 'opts'] (the same call the sender uses);
 *   sample — finds a real record for the preview / test and returns parts,
 *            or null when there is none yet.
 */
class Email_Catalog {

    const OPT_TEXTS    = 'gps_email_texts';      // [key => [subject, heading, intro, button]]
    const OPT_DISABLED = 'gps_email_disabled';   // [key, ...]
    const OPT_SENT     = 'gps_email_last_sent';  // [key => mysql datetime]

    const GROUPS = ['courses', 'seminars', 'account', 'admin'];

    public static function group_label($group) {
        $labels = [
            'courses'  => __('Courses & tickets', 'gps-courses'),
            'seminars' => __('Seminars', 'gps-courses'),
            'account'  => __('Account', 'gps-courses'),
            'admin'    => __('Admin', 'gps-courses'),
        ];
        return isset($labels[$group]) ? $labels[$group] : $group;
    }

    /**
     * The list. Keys are stable ids (saved texts and switches use them).
     * 'editable' => false: texts come from elsewhere (WooCommerce, the person
     * sending a blast) and the screen links there instead.
     */
    public static function all() {
        static $all = null;
        if (null !== $all) {
            return $all;
        }
        $e = function ($group, $name, $when, $to, array $texts, array $more = []) {
            return array_merge([
                'group'        => $group,
                'name'         => $name,
                'when'         => $when,
                'to'           => $to,
                'texts'        => $texts + ['subject' => '', 'heading' => '', 'intro' => '', 'button' => ''],
                'placeholders' => [],
                'editable'     => true,
                'can_disable'  => true,
                'parts'        => '',
                'sample'       => '',
                'manage_url'   => '',
            ], $more);
        };

        $all = [
            /* ---------------- Courses & tickets ---------------- */
            'ticket' => $e('courses', __('Ticket confirmation', 'gps-courses'), __('An order with a course ticket is completed', 'gps-courses'), __('Attendee', 'gps-courses'), [
                'subject' => 'Your ticket for {course}',
                'heading' => "You're registered!",
                'intro'   => 'Thank you for registering. Your seat is confirmed. Bring this ticket (on your phone is fine) to check in.',
                'button'  => 'View my tickets',
            ], ['placeholders' => ['first_name', 'course', 'dates', 'ce_credits'], 'can_disable' => false, 'parts' => 'GPSC\Emails::ticket_parts', 'sample' => 'GPSC\Emails::ticket_sample']),

            'credits' => $e('courses', __('CE credits awarded', 'gps-courses'), __('A course certificate is generated', 'gps-courses'), __('Attendee', 'gps-courses'), [
                'subject' => 'You earned {credits} CE credits',
                'heading' => 'You earned {credits} CE credits',
                'intro'   => 'Congratulations on completing the course. Your continuing education credits have been added to your account.',
                'button'  => 'View my CE credits',
            ], ['placeholders' => ['first_name', 'course', 'credits', 'total_credits'], 'parts' => 'GPSC\Emails::credits_parts', 'sample' => 'GPSC\Emails::credits_sample']),

            'certificate' => $e('courses', __('Course certificate', 'gps-courses'), __('Sent from Certificates (single or bulk)', 'gps-courses'), __('Attendee', 'gps-courses'), [
                'subject' => 'Your certificate: {course}',
                'heading' => 'Your certificate is ready',
                'intro'   => 'Thank you for attending. Your certificate of completion is attached to this email as a PDF.',
                'button'  => 'Download certificate',
            ], ['placeholders' => ['first_name', 'course', 'ce_credits'], 'can_disable' => false, 'parts' => 'GPSC\Certificates::email_parts', 'sample' => 'GPSC\Certificates::email_sample']),

            'session_ticket' => $e('courses', __('Seminar session ticket', 'gps-courses'), __('An individual seminar session is purchased', 'gps-courses'), __('Buyer', 'gps-courses'), [
                'subject' => 'Your ticket for {session}',
                'heading' => "You're registered!",
                'intro'   => 'Thank you for your purchase. Bring this ticket (on your phone is fine) to check in.',
                'button'  => 'View my seminar sessions',
            ], ['placeholders' => ['first_name', 'session', 'date'], 'can_disable' => false, 'parts' => 'GPSC\Session_Tickets::email_parts', 'sample' => 'GPSC\Session_Tickets::email_sample']),

            'receipt' => $e('courses', __('Receipt', 'gps-courses'), __('Sent from the order screen', 'gps-courses'), __('Buyer', 'gps-courses'), [
                'subject' => 'Your receipt for order #{order_number}',
                'heading' => 'Your receipt',
                'intro'   => 'Thank you for your payment. Your receipt for order #{order_number} is attached as a PDF.',
                'button'  => '',
            ], ['placeholders' => ['first_name', 'order_number'], 'can_disable' => false, 'parts' => 'GPSC\Receipts::email_parts', 'sample' => 'GPSC\Receipts::email_sample']),

            'waitlist_joined' => $e('courses', __('Waitlist: joined', 'gps-courses'), __('Someone joins a course waitlist', 'gps-courses'), __('Waitlisted person', 'gps-courses'), [
                'subject' => "You're on the waitlist: {course}",
                'heading' => "You're on the waitlist",
                'intro'   => 'We will email you as soon as a seat opens. Seats are offered in the order people joined.',
                'button'  => 'View course',
            ], ['placeholders' => ['first_name', 'course', 'position'], 'parts' => 'GPSC\Waitlist::email_joined_parts', 'sample' => 'GPSC\Waitlist::email_joined_sample']),

            'waitlist_spot' => $e('courses', __('Waitlist: a spot opened', 'gps-courses'), __('A seat frees up for the next person', 'gps-courses'), __('Waitlisted person', 'gps-courses'), [
                'subject' => 'A spot just opened: {course}',
                'heading' => 'A spot just opened for you',
                'intro'   => 'Good news: a seat is available in the course you were waiting for. It is held for you for a limited time, then offered to the next person on the list.',
                'button'  => 'Complete my registration',
            ], ['placeholders' => ['first_name', 'course', 'expires'], 'parts' => 'GPSC\Waitlist::email_spot_parts', 'sample' => 'GPSC\Waitlist::email_spot_sample']),

            'interest_new_dates' => $e('courses', __('New dates announced', 'gps-courses'), __('A course on the interest list goes on sale', 'gps-courses'), __('Interest list', 'gps-courses'), [
                'subject' => 'New dates: {course}',
                'heading' => 'New dates are open',
                'intro'   => 'You asked us to let you know when this course had new dates. Registration is now open.',
                'button'  => 'See dates and register',
            ], ['placeholders' => ['first_name', 'course', 'dates'], 'parts' => 'GPSC\Interest_List::email_parts', 'sample' => 'GPSC\Interest_List::email_sample']),

            'blast' => $e('courses', __('Email blast', 'gps-courses'), __('Sent from Reports › Email Blast', 'gps-courses'), __('Course attendees', 'gps-courses'), [
                'subject' => '', 'heading' => '', 'intro' => '', 'button' => '',
            ], ['editable' => false, 'can_disable' => false, 'manage_url' => 'admin.php?page=gps-reports']),

            /* ---------------- Seminars ---------------- */
            'seminar_welcome' => $e('seminars', __('Seminar welcome', 'gps-courses'), __('Someone registers for the Monthly Seminars', 'gps-courses'), __('Student', 'gps-courses'), [
                'subject' => 'Welcome to {seminar}',
                'heading' => 'Welcome to the Monthly Seminars',
                'intro'   => 'Your registration is confirmed. Below are your first session and how the program works.',
                'button'  => 'View my seminar',
            ], ['placeholders' => ['first_name', 'seminar'], 'parts' => 'GPSC\Seminar_Notifications::email_welcome_parts', 'sample' => 'GPSC\Seminar_Notifications::email_welcome_sample']),

            'seminar_reminder' => $e('seminars', __('Session reminder', 'gps-courses'), __('14, 7 and 1 days before each session (see Schedule)', 'gps-courses'), __('Student', 'gps-courses'), [
                'subject' => 'Reminder: session {session_number} on {date}',
                'heading' => 'Your session is in {days_label}',
                'intro'   => 'A reminder of your next Monthly Seminar session. We look forward to seeing you.',
                'button'  => 'View my seminar',
            ], ['placeholders' => ['first_name', 'seminar', 'session_number', 'date', 'days_label'], 'parts' => 'GPSC\Seminar_Notifications::email_reminder_parts', 'sample' => 'GPSC\Seminar_Notifications::email_reminder_sample']),

            'seminar_credits' => $e('seminars', __('Seminar credits awarded', 'gps-courses'), __('A student is checked in to a session', 'gps-courses'), __('Student', 'gps-courses'), [
                'subject' => 'You earned {credits} CE credits',
                'heading' => 'Thanks for attending',
                'intro'   => 'Your CE credits for this session have been added to your account.',
                'button'  => 'View my seminar',
            ], ['placeholders' => ['first_name', 'seminar', 'session_number', 'credits'], 'parts' => 'GPSC\Seminar_Notifications::email_credits_parts', 'sample' => 'GPSC\Seminar_Notifications::email_credits_sample']),

            'seminar_missed' => $e('seminars', __('Missed session', 'gps-courses'), __('The day after a session the student missed', 'gps-courses'), __('Student', 'gps-courses'), [
                'subject' => 'We missed you at session {session_number}',
                'heading' => 'We missed you',
                'intro'   => 'You were not checked in to the last session. Reply to this email if you would like to arrange a makeup session.',
                'button'  => 'View my seminar',
            ], ['placeholders' => ['first_name', 'seminar', 'session_number'], 'parts' => 'GPSC\Seminar_Notifications::email_missed_parts', 'sample' => 'GPSC\Seminar_Notifications::email_missed_sample']),

            'seminar_certificate' => $e('seminars', __('Seminar certificate', 'gps-courses'), __('Jun 30 and Dec 31, or sent from Seminar Certificates', 'gps-courses'), __('Student', 'gps-courses'), [
                'subject' => 'Your Monthly Seminars certificate',
                'heading' => 'Your certificate is ready',
                'intro'   => 'Your certificate for the sessions you attended this period is attached to this email as a PDF.',
                'button'  => 'View my seminar',
            ], ['placeholders' => ['first_name', 'seminar', 'period', 'credits'], 'can_disable' => false, 'parts' => 'GPSC\Seminar_Certificates::email_parts', 'sample' => 'GPSC\Seminar_Certificates::email_sample']),

            'seminar_waitlist_joined' => $e('seminars', __('Seminar waitlist: joined', 'gps-courses'), __('Someone joins the seminar waitlist', 'gps-courses'), __('Waitlisted person', 'gps-courses'), [
                'subject' => "You're on the waitlist: {seminar}",
                'heading' => "You're on the waitlist",
                'intro'   => 'We will email you if a spot becomes available. You will have 48 hours to complete your registration once notified.',
                'button'  => '',
            ], ['placeholders' => ['first_name', 'seminar', 'position'], 'parts' => 'GPSC\Seminar_Waitlist::email_joined_parts', 'sample' => 'GPSC\Seminar_Waitlist::email_joined_sample']),

            'seminar_waitlist_spot' => $e('seminars', __('Seminar waitlist: a spot opened', 'gps-courses'), __('A seminar spot frees up for the next person', 'gps-courses'), __('Waitlisted person', 'gps-courses'), [
                'subject' => 'A spot just opened: {seminar}',
                'heading' => 'A spot just opened for you',
                'intro'   => 'A spot is available in the Monthly Seminars. If you do not register in time, it will be offered to the next person on the waitlist.',
                'button'  => 'Register now',
            ], ['placeholders' => ['first_name', 'seminar', 'expires'], 'parts' => 'GPSC\Seminar_Waitlist::email_spot_parts', 'sample' => 'GPSC\Seminar_Waitlist::email_spot_sample']),




            /* ---------------- Account ---------------- */
            'signup_code' => $e('account', __('Sign-up code', 'gps-courses'), __('Someone starts creating an account', 'gps-courses'), __('New user', 'gps-courses'), [
                'subject' => 'Your GPS Dental Training verification code',
                'heading' => 'Your verification code',
                'intro'   => 'Enter this code on the sign-up page to create your GPS Dental Training account.',
                'button'  => '',
            ], ['can_disable' => false, 'parts' => 'GPSC\Registration_Guard::email_code_parts', 'sample' => 'GPSC\Registration_Guard::email_code_sample']),

            'account_welcome' => $e('account', __('Account welcome', 'gps-courses'), __('An account is created', 'gps-courses'), __('New user', 'gps-courses'), [
                'subject' => 'Welcome to GPS Dental Training',
                'heading' => 'Welcome, {first_name}',
                'intro'   => 'Your account is ready. From your account you can see your courses, tickets, CE credits and certificates.',
                'button'  => 'Go to my account',
            ], ['placeholders' => ['first_name'], 'parts' => 'GPSC\Registration_Guard::email_welcome_parts', 'sample' => 'GPSC\Registration_Guard::email_welcome_sample']),

            'wc_emails' => $e('account', __('Password reset, new account and order emails', 'gps-courses'), __('Sent by WooCommerce, in the GPS design', 'gps-courses'), __('Customer', 'gps-courses'), [
                'subject' => '', 'heading' => '', 'intro' => '', 'button' => '',
            ], ['editable' => false, 'can_disable' => false, 'manage_url' => 'admin.php?page=wc-settings&tab=email']),

            /* ---------------- Admin ---------------- */
            'admin_order_status' => $e('admin', __('Order status changed', 'gps-courses'), __('A GPS order changes status', 'gps-courses'), __('Notification recipients', 'gps-courses'), [
                'subject' => 'Order #{order_number} is now {status}',
                'heading' => 'Order #{order_number} is now {status}',
                'intro'   => '',
                'button'  => 'Open order in WooCommerce',
            ], ['placeholders' => ['order_number', 'status', 'customer'], 'parts' => 'GPSC\Woo::email_order_status_parts', 'sample' => 'GPSC\Woo::email_order_status_sample']),


            'ai_search_digest' => $e('admin', __('AI Search weekly report', 'gps-courses'), __('Every week', 'gps-courses'), __('Notification recipients', 'gps-courses'), [
                'subject' => 'AI Search: {count} pages need attention',
                'heading' => 'AI Search weekly report',
                'intro'   => '',
                'button'  => 'Open AI Search',
            ], ['placeholders' => ['count'], 'parts' => 'GPSC\AI_Search_Admin::email_parts', 'sample' => 'GPSC\AI_Search_Admin::email_sample']),
        ];

        $all = apply_filters('gpsc_email_catalog', $all);
        return $all;
    }

    public static function get($key) {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : null;
    }

    /** Saved texts over the defaults. */
    public static function texts($key) {
        $def = self::get($key);
        if (!$def) {
            return ['subject' => '', 'heading' => '', 'intro' => '', 'button' => ''];
        }
        $saved = get_option(self::OPT_TEXTS, []);
        $saved = is_array($saved) && isset($saved[$key]) && is_array($saved[$key]) ? $saved[$key] : [];
        $out = $def['texts'];
        foreach ($out as $field => $default) {
            if (isset($saved[$field]) && '' !== trim((string) $saved[$field])) {
                $out[$field] = (string) $saved[$field];
            }
        }
        return $out;
    }

    public static function save_texts($key, array $texts) {
        $def = self::get($key);
        if (!$def || empty($def['editable'])) {
            return false;
        }
        $saved = get_option(self::OPT_TEXTS, []);
        $saved = is_array($saved) ? $saved : [];
        $clean = [];
        foreach (array_keys($def['texts']) as $field) {
            if (!isset($texts[$field])) {
                continue;
            }
            $value = 'intro' === $field ? sanitize_textarea_field($texts[$field]) : sanitize_text_field($texts[$field]);
            // Same as the default: store nothing, so a later default change applies
            if ($value !== $def['texts'][$field]) {
                $clean[$field] = $value;
            }
        }
        if ($clean) {
            $saved[$key] = $clean;
        } else {
            unset($saved[$key]);
        }
        update_option(self::OPT_TEXTS, $saved, false);
        return true;
    }

    public static function reset_texts($key) {
        $saved = get_option(self::OPT_TEXTS, []);
        if (is_array($saved) && isset($saved[$key])) {
            unset($saved[$key]);
            update_option(self::OPT_TEXTS, $saved, false);
        }
    }

    public static function enabled($key) {
        $def = self::get($key);
        if ($def && empty($def['can_disable'])) {
            return true;
        }
        $off = get_option(self::OPT_DISABLED, []);
        return !(is_array($off) && in_array($key, $off, true));
    }

    public static function set_enabled($key, $on) {
        $def = self::get($key);
        if (!$def || empty($def['can_disable'])) {
            return false;
        }
        $off = get_option(self::OPT_DISABLED, []);
        $off = is_array($off) ? array_values(array_diff($off, [$key])) : [];
        if (!$on) {
            $off[] = $key;
        }
        update_option(self::OPT_DISABLED, $off, false);
        return true;
    }

    public static function mark_sent($key) {
        $sent = get_option(self::OPT_SENT, []);
        $sent = is_array($sent) ? $sent : [];
        $sent[$key] = current_time('mysql');
        update_option(self::OPT_SENT, $sent, false);
    }

    public static function last_sent($key) {
        $sent = get_option(self::OPT_SENT, []);
        return is_array($sent) && !empty($sent[$key]) ? (string) $sent[$key] : '';
    }

    /**
     * Parts for the preview / test of an email, from a real record when one
     * exists: ['to', 'vars', 'blocks', 'opts'] or null.
     */
    public static function sample_parts($key) {
        $def = self::get($key);
        if (!$def || empty($def['sample'])) {
            return null;
        }
        $cb = $def['sample'];
        if (!is_callable($cb)) {
            return null;
        }
        try {
            $parts = call_user_func($cb);
        } catch (\Throwable $e) {
            error_log('GPS Courses: email sample "' . $key . '" failed: ' . $e->getMessage());
            return null;
        }
        return is_array($parts) ? $parts + ['to' => '', 'vars' => [], 'blocks' => '', 'opts' => []] : null;
    }
}
