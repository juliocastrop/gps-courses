<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Logged-in My Account: GPS dashboard (welcome, alerts, summary, next course,
 * recommended courses, activity) and a grouped side menu.
 *
 * Swaps WooCommerce's myaccount/dashboard.php and myaccount/navigation.php for
 * the plugin's templates and gathers the data they show from the plugin's own
 * tables (tickets, attendance, CE ledger, seminars, certificates).
 */
class Account_Dashboard {

    const TEMPLATES = [
        'myaccount/dashboard.php',
        'myaccount/navigation.php',
    ];

    /** Menu groups; endpoints not listed here (other plugins) go to "Account". */
    const GROUPS = [
        'learning'  => ['dashboard', 'gps-courses', 'gps-seminars', 'gps-ce-credits', 'gps-tickets', 'gps-attendance'],
        'purchases' => ['orders', 'downloads', 'payment-methods'],
        'account'   => ['edit-account', 'edit-address'],
    ];

    /** Interest (from registration) => words looked up in course titles, categories and tags. */
    const INTEREST_KEYWORDS = [
        'Complex Treatment Planning'                 => ['treatment planning'],
        'Literature Review'                          => ['literature'],
        'Site development and Site preservation'     => ['site development', 'site preservation', 'graft', 'prf', 'bone'],
        'Implant placement'                          => ['implant placement', 'immediate implant', 'implant surgery'],
        'Guided surgery'                             => ['guided'],
        'Digital workflows'                          => ['digital'],
        'Full Arch zirconia prosthesis design'       => ['full arch', 'zirconia'],
        'All-on-X digital protocols'                 => ['all-on', 'all on', 'full arch'],
        'Implant restorations in the esthetic zone'  => ['esthetic', 'aesthetic', 'pink'],
    ];

    public static function init() {
        if (!apply_filters('gps_account_dashboard_enabled', true)) {
            return;
        }
        add_filter('wc_get_template', [__CLASS__, 'locate_template'], 20, 2);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 110);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'menu_items'], 99);

        // Profile fields in Account details (phone, role, topics)
        add_action('woocommerce_edit_account_form', [__CLASS__, 'render_profile_fields']);
        add_action('woocommerce_save_account_details_errors', [__CLASS__, 'validate_profile_fields'], 10, 2);
        add_action('woocommerce_save_account_details', [__CLASS__, 'save_profile_fields']);
    }

    /* ====================================================================
     * Templates, assets, menu
     * ==================================================================== */

    public static function locate_template($template, $template_name) {
        if (!in_array($template_name, self::TEMPLATES, true) || !is_user_logged_in()) {
            return $template;
        }
        $ours = GPSC_PATH . 'templates/' . $template_name;
        return file_exists($ours) ? $ours : $template;
    }

    public static function enqueue_assets() {
        if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
            return;
        }
        $css = GPSC_PATH . 'assets/css/gps-account.css';
        wp_enqueue_style('gps-account', GPSC_URL . 'assets/css/gps-account.css', ['gps-forms'], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
    }

    /** Hide "Downloads" when the customer has nothing to download. */
    public static function menu_items($items) {
        static $has_downloads = null;
        if (isset($items['downloads']) && is_user_logged_in() && function_exists('wc_get_customer_available_downloads')) {
            if (null === $has_downloads) {
                $has_downloads = (bool) wc_get_customer_available_downloads(get_current_user_id());
            }
            if (!$has_downloads) {
                unset($items['downloads']);
            }
        }
        return $items;
    }

    /**
     * Menu items grouped for the side menu.
     *
     * @return array<string, array{label: string, items: array<string, string>}>
     */
    public static function grouped_menu() {
        $items  = wc_get_account_menu_items();
        $labels = [
            'learning'  => __('Learning', 'gps-courses'),
            'purchases' => __('Purchases', 'gps-courses'),
            'account'   => __('Account', 'gps-courses'),
        ];
        $groups = [];
        foreach ($labels as $key => $label) {
            $groups[$key] = ['label' => $label, 'items' => []];
        }
        foreach ($items as $endpoint => $label) {
            if ('customer-logout' === $endpoint) {
                continue;
            }
            $group = 'account';
            foreach (self::GROUPS as $g => $endpoints) {
                if (in_array($endpoint, $endpoints, true)) {
                    $group = $g;
                    break;
                }
            }
            $groups[$group]['items'][$endpoint] = $label;
        }
        return array_filter($groups, function ($g) { return !empty($g['items']); });
    }

    /** Current menu item, also on WooCommerce versions without wc_is_current_account_menu_item(). */
    public static function is_current($endpoint) {
        if (function_exists('wc_is_current_account_menu_item')) {
            return wc_is_current_account_menu_item($endpoint);
        }
        return false !== strpos(wc_get_account_menu_item_classes($endpoint), 'is-active');
    }

    /** Small line icons for the menu (inline SVG, currentColor). */
    public static function icon($endpoint) {
        $paths = [
            'dashboard'       => '<path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z"/>',
            'gps-courses'     => '<path d="M4 5h11a3 3 0 0 1 3 3v12H7a3 3 0 0 1-3-3z"/><path d="M8 9h6M8 13h6"/>',
            'gps-seminars'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'gps-ce-credits'  => '<circle cx="12" cy="9" r="6"/><path d="m9 14-2 7 5-3 5 3-2-7"/>',
            'gps-tickets'     => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/>',
            'gps-attendance'  => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/>',
            'orders'          => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
            'downloads'       => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
            'payment-methods' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
            'edit-account'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'edit-address'    => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
            'customer-logout' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l-5-5 5-5M5 12h12"/>',
            'certificate'     => '<circle cx="12" cy="9" r="6"/><path d="m9 14-2 7 5-3 5 3-2-7"/>',
            'warning'         => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
            'clock'           => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'pin'             => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        ];
        $path = $paths[$endpoint] ?? '<circle cx="12" cy="12" r="8"/>';
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
    }

    /* ====================================================================
     * Dashboard data
     * ==================================================================== */

    public static function data($user_id) {
        $user  = get_userdata($user_id);
        $today = current_time('Y-m-d');

        $next_course  = self::next_course($user_id, $today);
        $seminar      = self::seminar($user_id, $today);

        // The next thing on the calendar: a course, a seminar session, or a single session ticket
        $next = $next_course;
        foreach ([$seminar['next_session'] ?? null, self::next_session_ticket($user_id, $today)] as $candidate) {
            if ($candidate && (!$next || $candidate['start_date'] < $next['start_date'])) {
                $next = $candidate;
            }
        }

        $certificates = self::certificates($user_id);

        $data = [
            'first_name'   => $user ? ($user->first_name ?: $user->display_name) : '',
            'full_name'    => $user ? (trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name) : '',
            'profession'   => (string) get_user_meta($user_id, 'gps_profession', true),
            'member_since' => $user ? date_i18n('F Y', strtotime($user->user_registered)) : '',
            'next'         => $next,
            'credits'      => self::credits($user_id),
            'seminar'      => $seminar,
            'certificates' => $certificates,
            'alerts'       => self::alerts($user_id, $certificates, $seminar),
            'recommended'  => self::recommended($user_id, $today),
            'activity'     => self::activity($user_id),
            'courses_url'  => self::courses_url(),
            'tickets_url'  => wc_get_account_endpoint_url('gps-tickets'),
            'help_email'   => apply_filters('gps_account_help_email', get_option('woocommerce_email_from_address') ?: get_option('admin_email')),
        ];
        return apply_filters('gps_account_dashboard_data', $data, $user_id);
    }

    public static function courses_url() {
        $url = get_post_type_archive_link('gps_event');
        return apply_filters('gps_account_courses_url', $url ?: home_url('/courses/'));
    }

    /** Earliest course the user holds a valid ticket for that hasn't ended yet. */
    protected static function next_course($user_id, $today) {
        global $wpdb;
        $t = $wpdb->prefix . 'gps_tickets';

        $user  = get_userdata($user_id);
        $email = $user ? $user->user_email : '';

        // Tickets the user attends: designated to them, or bought and not handed to someone else.
        // When they bought several for one event, prefer the one in their own name.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.id, t.ticket_code, t.event_id, t.qr_code_path, s.meta_value AS start_date, e.meta_value AS end_date
             FROM {$t} t
             JOIN {$wpdb->posts} p ON p.ID = t.event_id AND p.post_status = 'publish'
             JOIN {$wpdb->postmeta} s ON s.post_id = t.event_id AND s.meta_key = '_gps_start_date' AND s.meta_value <> ''
             LEFT JOIN {$wpdb->postmeta} e ON e.post_id = t.event_id AND e.meta_key = '_gps_end_date'
             WHERE ((t.user_id = %d AND (t.designated_attendee_id IS NULL OR t.designated_attendee_id IN (0, %d)))
                    OR t.designated_attendee_id = %d)
               AND t.status = 'valid'
               AND COALESCE(NULLIF(e.meta_value, ''), s.meta_value) >= %s
             ORDER BY s.meta_value ASC, (t.designated_attendee_id = %d OR t.attendee_email = %s) DESC, t.id ASC
             LIMIT 1",
            $user_id, $user_id, $user_id, $today, $user_id, $email
        ));
        if (!$row) {
            return null;
        }

        $event_id   = (int) $row->event_id;
        $start_date = substr((string) $row->start_date, 0, 10);
        $end_date   = $row->end_date ? substr((string) $row->end_date, 0, 10) : $start_date;
        $start_time = (string) get_post_meta($event_id, '_gps_start_time', true);
        $end_time   = (string) get_post_meta($event_id, '_gps_end_time', true);
        $location   = self::event_location($event_id);
        $checked_in = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gps_attendance WHERE ticket_id = %d LIMIT 1",
            (int) $row->id
        ));

        return [
            'kind'        => 'course',
            'title'       => get_the_title($event_id),
            'url'         => get_permalink($event_id),
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'start_time'  => $start_time,
            'end_time'    => $end_time,
            'location'    => $location,
            'ce_credits'  => (float) get_post_meta($event_id, '_gps_ce_credits', true),
            'ticket_code' => (string) $row->ticket_code,
            'qr_url'      => self::qr_url((string) $row->qr_code_path),
            'ticket_url'  => add_query_arg('download_ticket', (int) $row->id, wc_get_account_endpoint_url('gps-tickets')),
            'checked_in'  => $checked_in,
            'calendar'    => self::calendar_url(get_the_title($event_id), $start_date, $end_date, $start_time, $end_time, $location, get_permalink($event_id)),
            'directions'  => $location ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($location) : '',
        ];
    }

    /** Venue + address on one line. */
    protected static function event_location($event_id) {
        $venue = (string) get_post_meta($event_id, '_gps_venue', true) ?: (string) get_post_meta($event_id, '_gps_location', true);
        $parts = array_filter([
            (string) get_post_meta($event_id, '_gps_address', true),
            (string) get_post_meta($event_id, '_gps_city', true),
            trim((string) get_post_meta($event_id, '_gps_state', true) . ' ' . (string) get_post_meta($event_id, '_gps_zip', true)),
        ]);
        $line = $venue;
        if ($parts) {
            $line .= ($line ? ' · ' : '') . implode(', ', $parts);
        }
        return trim($line);
    }

    protected static function qr_url($path) {
        if ($path === '') {
            return '';
        }
        if (class_exists(__NAMESPACE__ . '\\QRCodeGenerator') && method_exists(__NAMESPACE__ . '\\QRCodeGenerator', 'get_qr_code_url')) {
            return (string) QRCodeGenerator::get_qr_code_url($path);
        }
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['baseurl']) . ltrim($path, '/');
    }

    /** Google Calendar link in the site's timezone. */
    protected static function calendar_url($title, $start_date, $end_date, $start_time, $end_time, $location, $url) {
        $start = str_replace('-', '', $start_date) . 'T' . self::cal_time($start_time, '090000');
        $end   = str_replace('-', '', $end_date ?: $start_date) . 'T' . self::cal_time($end_time, '170000');
        $args  = [
            'action'   => 'TEMPLATE',
            'text'     => rawurlencode($title),
            'dates'    => $start . '/' . $end,
            'location' => rawurlencode($location),
            'details'  => rawurlencode($url),
        ];
        // Google needs a named zone (America/New_York); offsets like -05:00 are rejected
        $tz = wp_timezone_string();
        if (false !== strpos($tz, '/')) {
            $args['ctz'] = rawurlencode($tz);
        }
        return add_query_arg($args, 'https://calendar.google.com/calendar/render');
    }

    /** '8:00', '08:00', '08:00:00' or '8:00 AM' -> '080000'. */
    protected static function cal_time($time, $default) {
        $ts = $time ? strtotime('1970-01-01 ' . $time . ' UTC') : false;
        return false === $ts ? $default : gmdate('His', $ts);
    }

    protected static function credits($user_id) {
        global $wpdb;
        $l = $wpdb->prefix . 'gps_ce_ledger';
        $year  = (int) current_time('Y');
        $total = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(credits), 0) FROM {$l} WHERE user_id = %d", $user_id));
        $this_year = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(credits), 0) FROM {$l} WHERE user_id = %d AND YEAR(awarded_at) = %d",
            $user_id, $year
        ));
        return ['year' => $year, 'this_year' => $this_year, 'total' => $total, 'url' => wc_get_account_endpoint_url('gps-ce-credits')];
    }

    /** Earliest upcoming individual seminar session the user bought a ticket for. */
    protected static function next_session_ticket($user_id, $today) {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_session_tickets';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return null;
        }
        $r = $wpdb->get_row($wpdb->prepare(
            "SELECT st.id, st.ticket_code, st.seminar_id, st.ce_credits, st.checked_in_at,
                    s.session_number, s.session_date, s.session_time_start, s.session_time_end, s.topic
             FROM {$table} st
             JOIN {$wpdb->prefix}gps_seminar_sessions s ON s.id = st.session_id
             WHERE st.user_id = %d AND st.status = 'valid' AND s.session_date >= %s
             ORDER BY s.session_date ASC, s.session_time_start ASC LIMIT 1",
            $user_id, $today
        ));
        if (!$r) {
            return null;
        }
        $title    = sprintf(
            /* translators: 1: session number, 2: topic */
            __('Monthly Seminar · Session %1$d%2$s', 'gps-courses'),
            (int) $r->session_number,
            $r->topic ? ': ' . $r->topic : ''
        );
        $address  = '6320 Sugarloaf Parkway, Duluth, GA 30097';
        $location = (string) apply_filters('gps_seminar_location', 'GPS Dental Training Center · ' . $address, (int) $r->seminar_id);
        $start_t  = $r->session_time_start ? substr($r->session_time_start, 0, 5) : '';
        $end_t    = $r->session_time_end ? substr($r->session_time_end, 0, 5) : '';
        return [
            'kind'        => 'seminar',
            'title'       => $title,
            'url'         => wc_get_account_endpoint_url('gps-seminars'),
            'start_date'  => $r->session_date,
            'end_date'    => $r->session_date,
            'start_time'  => $start_t,
            'end_time'    => $end_t,
            'location'    => $location,
            'ce_credits'  => (float) $r->ce_credits,
            'ticket_code' => (string) $r->ticket_code,
            'qr_url'      => '',
            'ticket_url'  => wc_get_account_endpoint_url('gps-seminars'),
            'checked_in'  => !empty($r->checked_in_at),
            'calendar'    => self::calendar_url($title, $r->session_date, $r->session_date, $start_t, $end_t, $location, home_url('/')),
            'directions'  => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address),
        ];
    }

    /** Latest active (or completed) seminar registration with progress and next session. */
    protected static function seminar($user_id, $today) {
        global $wpdb;
        $reg = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_registrations
             WHERE user_id = %d AND status IN ('active', 'completed')
             ORDER BY (status = 'active') DESC, id DESC LIMIT 1",
            $user_id
        ));
        if (!$reg) {
            return null;
        }

        $seminar_id = (int) $reg->seminar_id;
        $credits    = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(credits_awarded), 0) FROM {$wpdb->prefix}gps_seminar_attendance WHERE registration_id = %d AND attended = 1",
            (int) $reg->id
        ));

        // Sessions already held since the registration started that the user missed
        $since  = $reg->start_session_date ?: $reg->registration_date;
        $missed = 0;
        if ('active' === $reg->status && $since) {
            $missed = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gps_seminar_sessions s
                 WHERE s.seminar_id = %d AND s.session_date >= %s AND s.session_date < %s
                   AND NOT EXISTS (SELECT 1 FROM {$wpdb->prefix}gps_seminar_attendance a WHERE a.registration_id = %d AND a.session_id = s.id AND a.attended = 1)",
                $seminar_id, $since, $today, (int) $reg->id
            ));
        }

        $next_session = null;
        if ('active' === $reg->status) {
            $s = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE seminar_id = %d AND session_date >= %s ORDER BY session_date ASC, session_time_start ASC LIMIT 1",
                $seminar_id, $today
            ));
            if ($s) {
                $title    = sprintf(
                    /* translators: 1: session number, 2: topic */
                    __('Monthly Seminar · Session %1$d%2$s', 'gps-courses'),
                    (int) $s->session_number,
                    $s->topic ? ': ' . $s->topic : ''
                );
                $location = (string) apply_filters('gps_seminar_location', 'GPS Dental Training Center · 6320 Sugarloaf Parkway, Duluth, GA 30097', $seminar_id);
                $start_t  = $s->session_time_start ? substr($s->session_time_start, 0, 5) : '';
                $end_t    = $s->session_time_end ? substr($s->session_time_end, 0, 5) : '';
                $next_session = [
                    'kind'        => 'seminar',
                    'title'       => $title,
                    'url'         => wc_get_account_endpoint_url('gps-seminars'),
                    'start_date'  => $s->session_date,
                    'end_date'    => $s->session_date,
                    'start_time'  => $start_t,
                    'end_time'    => $end_t,
                    'location'    => $location,
                    'ce_credits'  => 2.0,
                    'ticket_code' => (string) $reg->qr_code,
                    'qr_url'      => self::qr_url((string) $reg->qr_code_path),
                    'ticket_url'  => wc_get_account_endpoint_url('gps-seminars'),
                    'checked_in'  => false,
                    'calendar'    => self::calendar_url($title, $s->session_date, $s->session_date, $start_t, $end_t, $location, home_url('/')),
                    'directions'  => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('6320 Sugarloaf Parkway, Duluth, GA 30097'),
                ];
            }
        }

        $completed = (int) $reg->sessions_completed;
        return [
            'status'       => $reg->status,
            'completed'    => $completed,
            'total'        => max(10, $completed + (int) $reg->sessions_remaining),
            'makeup_used'  => (bool) $reg->makeup_used,
            'missed'       => $missed,
            'credits'      => $credits,
            'next_session' => $next_session,
            'url'          => wc_get_account_endpoint_url('gps-seminars'),
        ];
    }

    /** The user's certificates, newest first, with a download link each. */
    protected static function certificates($user_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.ticket_id, c.event_id, c.generated_at, p.post_title
             FROM {$wpdb->prefix}gps_certificates c
             LEFT JOIN {$wpdb->posts} p ON p.ID = c.event_id
             WHERE c.user_id = %d
             ORDER BY c.generated_at DESC",
            $user_id
        ));
        $base = wc_get_account_endpoint_url('gps-ce-credits');
        $list = [];
        foreach ((array) $rows as $r) {
            $list[] = [
                'id'        => (int) $r->id,
                'title'     => (string) $r->post_title,
                'generated' => (string) $r->generated_at,
                // Certificates::handle_download checks that the row belongs to the user
                'url'       => add_query_arg('download_certificate', (int) $r->id, $base),
            ];
        }
        return $list;
    }

    protected static function alerts($user_id, $certificates, $seminar) {
        $alerts = [];

        if ($certificates) {
            $latest = $certificates[0];
            if ($latest['generated'] && strtotime($latest['generated']) >= strtotime('-30 days', current_time('timestamp'))) {
                $alerts[] = [
                    'tone'  => 'ok',
                    'icon'  => 'certificate',
                    'title' => __('Your certificate is ready.', 'gps-courses'),
                    'text'  => $latest['title'],
                    'link'  => $latest['url'],
                    'cta'   => __('Download PDF', 'gps-courses'),
                ];
            }
        }

        if ($seminar && 'active' === $seminar['status'] && $seminar['missed'] > 0 && !$seminar['makeup_used']) {
            $alerts[] = [
                'tone'  => 'warn',
                'icon'  => 'warning',
                'title' => __('Makeup session available.', 'gps-courses'),
                'text'  => __('You missed a monthly seminar session. You can make up one session.', 'gps-courses'),
                'link'  => $seminar['url'],
                'cta'   => __('See options', 'gps-courses'),
            ];
        }

        $phone = (string) get_user_meta($user_id, 'billing_phone', true);
        $role  = (string) get_user_meta($user_id, 'gps_profession', true);
        if ($phone === '' || $role === '') {
            $alerts[] = [
                'tone'  => 'info',
                'icon'  => 'edit-account',
                'title' => __('Complete your profile.', 'gps-courses'),
                'text'  => __('Add your mobile phone and role so we can send course-day updates and recommend the right courses.', 'gps-courses'),
                'link'  => wc_get_account_endpoint_url('edit-account') . '#gps-profile',
                'cta'   => __('Complete profile', 'gps-courses'),
            ];
        }

        return apply_filters('gps_account_dashboard_alerts', $alerts, $user_id);
    }

    /**
     * Up to three upcoming courses the user has no ticket for, ranked by the
     * topics they picked at sign-up (title, categories and tags), then by date.
     */
    protected static function recommended($user_id, $today) {
        global $wpdb;
        $owned = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT event_id FROM {$wpdb->prefix}gps_tickets WHERE user_id = %d OR designated_attendee_id = %d",
            $user_id, $user_id
        )));
        // A ticket for an edition means the user has that course
        foreach ($owned as $owned_id) {
            $master = Editions::of($owned_id);
            if ($master && !in_array($master, $owned, true)) {
                $owned[] = $master;
            }
        }

        $query = new \WP_Query([
            'post_type'           => 'gps_event',
            'post_status'         => 'publish',
            'posts_per_page'      => 12,
            'post__not_in'        => $owned,
            'meta_key'            => '_gps_start_date',
            'orderby'             => 'meta_value',
            'order'               => 'ASC',
            'meta_query'          => [[
                'key'     => '_gps_start_date',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE',
            ]],
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ]);

        $interests = array_filter((array) get_user_meta($user_id, 'gps_interests', true));
        $ranked    = [];
        foreach ($query->posts as $i => $post) {
            $cats = get_the_terms($post, 'gps_event_category');
            $tags = get_the_terms($post, 'gps_event_tag');
            $cats = is_array($cats) ? wp_list_pluck($cats, 'name') : [];
            $tags = is_array($tags) ? wp_list_pluck($tags, 'name') : [];
            $haystack = strtolower($post->post_title . ' ' . implode(' ', array_merge($cats, $tags)));

            $match = '';
            $score = 0;
            foreach ($interests as $topic) {
                foreach (self::INTEREST_KEYWORDS[$topic] ?? [] as $word) {
                    if (false !== strpos($haystack, $word)) {
                        $score++;
                        $match = $match ?: $topic;
                        break;
                    }
                }
            }
            $ranked[] = ['post' => $post, 'cats' => $cats, 'match' => $match, 'score' => $score, 'order' => $i];
        }

        usort($ranked, function ($a, $b) {
            return [$b['score'], $a['order']] <=> [$a['score'], $b['order']];
        });

        // Stock only for the courses we are about to show. Courses on sale come
        // first; ones whose registration hasn't opened yet fill the remaining
        // slots; sold-out courses are skipped.
        $courses = [];
        $soon    = [];
        foreach ($ranked as $r) {
            if (count($courses) >= 3) {
                break;
            }
            $post  = $r['post'];
            $seats = null;
            $is_soon = false;
            if (class_exists(__NAMESPACE__ . '\\Tickets') && method_exists(__NAMESPACE__ . '\\Tickets', 'get_active_tickets')) {
                $types = (array) Tickets::get_active_tickets($post->ID);
                $open  = array_filter($types, function ($t) {
                    return !Tickets::is_sold_out(is_object($t) ? $t->ID : (int) $t);
                });
                if ($types && !$open) {
                    continue; // all sold out (also by hand)
                }
                $is_soon = !$types; // no ticket on sale yet
                if (!$is_soon) {
                    $stock = Tickets::get_event_total_stock($post->ID);
                    if (is_array($stock) && empty($stock['unlimited'])) {
                        $seats = (int) ($stock['total_available'] ?? 0);
                    }
                }
            }
            if ($is_soon && count($soon) >= 3) {
                continue;
            }

            $start = (string) get_post_meta($post->ID, '_gps_start_date', true);
            $end   = (string) get_post_meta($post->ID, '_gps_end_date', true);
            $card = [
                'title'    => get_the_title($post),
                'url'      => get_permalink($post),
                'image'    => (string) get_the_post_thumbnail_url($post, 'medium_large'),
                'category' => $r['cats'] ? $r['cats'][0] : '',
                'ce'       => (float) get_post_meta($post->ID, '_gps_ce_credits', true),
                'when'     => self::date_range($start, $end),
                'city'     => (string) get_post_meta($post->ID, '_gps_city', true),
                'seats'    => $seats,
                'match'    => $r['match'],
                'soon'     => $is_soon,
            ];
            if ($is_soon) {
                $soon[] = $card;
            } else {
                $courses[] = $card;
            }
        }
        return array_slice(array_merge($courses, $soon), 0, 3);
    }

    /** "Nov 13–14, 2026", "Nov 30 – Dec 1, 2026", "Nov 13, 2026". */
    public static function date_range($start, $end) {
        $s = $start ? strtotime($start) : 0;
        if (!$s) {
            return '';
        }
        $e = $end ? strtotime($end) : $s;
        if (!$e || $e <= $s) {
            return date_i18n('M j, Y', $s);
        }
        if (date('Y-m', $s) === date('Y-m', $e)) {
            return date_i18n('M j', $s) . '–' . date_i18n('j, Y', $e);
        }
        if (date('Y', $s) === date('Y', $e)) {
            return date_i18n('M j', $s) . ' – ' . date_i18n('M j, Y', $e);
        }
        return date_i18n('M j, Y', $s) . ' – ' . date_i18n('M j, Y', $e);
    }

    /** Credits awarded, check-ins and orders, newest first. */
    protected static function activity($user_id) {
        global $wpdb;
        $items = [];

        $credits = $wpdb->get_results($wpdb->prepare(
            "SELECT l.credits, l.awarded_at, p.post_title FROM {$wpdb->prefix}gps_ce_ledger l
             LEFT JOIN {$wpdb->posts} p ON p.ID = l.event_id
             WHERE l.user_id = %d ORDER BY l.awarded_at DESC LIMIT 4",
            $user_id
        ));
        foreach ((array) $credits as $c) {
            $n = (float) $c->credits;
            $items[] = [
                'icon'  => 'gps-ce-credits',
                'title' => $n < 0
                    /* translators: %s: number of credits */
                    ? sprintf(_n('%s CE credit revoked', '%s CE credits revoked', (int) ceil(abs($n)), 'gps-courses'), self::num(abs($n)))
                    /* translators: %s: number of credits */
                    : sprintf(_n('%s CE credit awarded', '%s CE credits awarded', (int) ceil($n), 'gps-courses'), self::num($n)),
                'text'  => (string) $c->post_title,
                'date'  => (string) $c->awarded_at,
            ];
        }

        $checkins = $wpdb->get_results($wpdb->prepare(
            "SELECT a.checked_in_at, p.post_title FROM {$wpdb->prefix}gps_attendance a
             LEFT JOIN {$wpdb->posts} p ON p.ID = a.event_id
             WHERE a.user_id = %d ORDER BY a.checked_in_at DESC LIMIT 4",
            $user_id
        ));
        foreach ((array) $checkins as $c) {
            $items[] = ['icon' => 'gps-attendance', 'title' => __('Checked in', 'gps-courses'), 'text' => (string) $c->post_title, 'date' => (string) $c->checked_in_at];
        }

        $seminar_checkins = $wpdb->get_results($wpdb->prepare(
            "SELECT a.checked_in_at, s.session_number FROM {$wpdb->prefix}gps_seminar_attendance a
             LEFT JOIN {$wpdb->prefix}gps_seminar_sessions s ON s.id = a.session_id
             WHERE a.user_id = %d AND a.attended = 1 ORDER BY a.checked_in_at DESC LIMIT 4",
            $user_id
        ));
        foreach ((array) $seminar_checkins as $c) {
            $items[] = [
                'icon'  => 'gps-attendance',
                'title' => __('Checked in', 'gps-courses'),
                /* translators: %d: session number */
                'text'  => sprintf(__('Monthly Seminar, session %d', 'gps-courses'), (int) $c->session_number),
                'date'  => (string) $c->checked_in_at,
            ];
        }

        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders(['customer_id' => $user_id, 'limit' => 3, 'orderby' => 'date', 'order' => 'DESC', 'status' => ['wc-completed', 'wc-processing']]);
            foreach ($orders as $order) {
                $first = '';
                foreach ($order->get_items() as $item) {
                    $first = $item->get_name();
                    break;
                }
                $items[] = [
                    'icon'  => 'orders',
                    /* translators: %s: order number */
                    'title' => sprintf(__('Order #%s', 'gps-courses'), $order->get_order_number()) . ' ' . ($order->has_status('completed') ? __('completed', 'gps-courses') : __('received', 'gps-courses')),
                    'text'  => $first,
                    'date'  => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                    'url'   => $order->get_view_order_url(),
                ];
            }
        }

        usort($items, function ($a, $b) { return strcmp($b['date'], $a['date']); });
        return array_slice($items, 0, 4);
    }

    /** 12 / 12.5, without trailing zeros. */
    public static function num($n) {
        $n = (float) $n;
        return floor($n) == $n ? number_format_i18n($n) : number_format_i18n($n, 1);
    }

    /* ====================================================================
     * Account details: phone, role, topics
     * ==================================================================== */

    public static function render_profile_fields() {
        $user_id   = get_current_user_id();
        $phone     = (string) get_user_meta($user_id, 'billing_phone', true);
        $role      = (string) get_user_meta($user_id, 'gps_profession', true);
        $interests = (array) get_user_meta($user_id, 'gps_interests', true);
        ?>
        <fieldset class="gps-profile" id="gps-profile">
            <legend><?php esc_html_e('Your practice', 'gps-courses'); ?></legend>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="gps_account_phone"><?php esc_html_e('Mobile phone', 'gps-courses'); ?></label>
                <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="gps_account_phone" id="gps_account_phone" autocomplete="tel" value="<?php echo esc_attr($phone); ?>">
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="gps_account_profession"><?php esc_html_e('Your role', 'gps-courses'); ?></label>
                <select name="gps_account_profession" id="gps_account_profession">
                    <option value=""><?php esc_html_e('Select your role', 'gps-courses'); ?></option>
                    <?php foreach (Account_Auth::roles() as $r) : ?>
                        <option value="<?php echo esc_attr($r); ?>"<?php selected($role, $r); ?>><?php echo esc_html($r); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <div class="gps-profile__topics">
                <span class="gps-profile__label"><?php esc_html_e('Topics you\'re interested in', 'gps-courses'); ?></span>
                <div class="gps-chips">
                    <?php foreach (Account_Auth::interests() as $topic) : ?>
                        <label class="gps-chip">
                            <input type="checkbox" name="gps_account_interests[]" value="<?php echo esc_attr($topic); ?>"<?php checked(in_array($topic, $interests, true)); ?>>
                            <span><?php echo esc_html($topic); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </fieldset>
        <?php
    }

    public static function validate_profile_fields($errors, $user) {
        $phone = isset($_POST['gps_account_phone']) ? sanitize_text_field(wp_unslash($_POST['gps_account_phone'])) : '';
        if ($phone !== '') {
            $digits = preg_replace('/\D/', '', $phone);
            if (!preg_match('/^\+?[\d\s().-]+$/', $phone) || strlen($digits) < 7 || strlen($digits) > 15) {
                $errors->add('gps_account_phone', __('Please enter a valid mobile phone number.', 'gps-courses'));
            }
        }
    }

    public static function save_profile_fields($user_id) {
        if (!isset($_POST['gps_account_profession'])) {
            return; // form without our fields
        }
        $phone = isset($_POST['gps_account_phone']) ? sanitize_text_field(wp_unslash($_POST['gps_account_phone'])) : '';
        update_user_meta($user_id, 'billing_phone', $phone);

        $role = sanitize_text_field(wp_unslash($_POST['gps_account_profession']));
        if ($role === '' || in_array($role, Account_Auth::roles(), true)) {
            update_user_meta($user_id, 'gps_profession', $role);
        }

        $posted    = (isset($_POST['gps_account_interests']) && is_array($_POST['gps_account_interests'])) ? array_map('sanitize_text_field', wp_unslash($_POST['gps_account_interests'])) : [];
        $interests = array_values(array_intersect(Account_Auth::interests(), $posted));
        update_user_meta($user_id, 'gps_interests', $interests);
    }
}
