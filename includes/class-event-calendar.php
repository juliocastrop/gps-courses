<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Event calendar: data + markup shared by the "GPS Event Calendar" Elementor
 * widget (server render) and the gps_get_calendar_events AJAX action, so the
 * calendar is complete without JavaScript and both always match.
 *
 * - The current month (site timezone) is rendered in PHP: a month table for
 *   wide screens, an agenda of the days with events for phones, and the next
 *   events from today in the "Upcoming events" panel.
 * - Month navigation, filters and the month/year selects also work without JS
 *   (GET parameters gpsc_cal=YYYY-MM, gpsc_cal_type, gpsc_cal_cat; such URLs
 *   are noindex); assets/js/calendar.js intercepts them and swaps in the AJAX
 *   markup.
 * - The AJAX action is public read-only data: no nonce, so pages served from
 *   the page cache (FlyingPress) never break on an expired token. Every input
 *   is bounded (months, types, existing categories, counts), so the transient
 *   cache can't be bloated.
 * - Pages showing the calendar are purged from the page cache by the daily
 *   Course_Stage cron, so no-JS visitors see the new day / month.
 *
 * Courses (gps_event) take their status from Course_Stage; monthly seminar
 * sessions come from {prefix}gps_seminar_sessions.
 *
 * Dates are Y-m-d strings; timestamps are always midnight UTC (ts()) and
 * formatted with gmdate() / date_i18n(), whatever PHP's default timezone is.
 */
class Event_Calendar {

    const TYPES       = ['all', 'courses', 'seminars'];
    const PARAMS      = ['gpsc_cal', 'gpsc_cal_y', 'gpsc_cal_m', 'gpsc_cal_type', 'gpsc_cal_cat'];
    const CACHE_TTL   = 300;
    const YEARS_BACK  = 1;
    const YEARS_AHEAD = 3;
    const MAX_UPCOMING = 20;

    /** Seminar session CE credits (Seminars module: 2 per session). */
    const SEMINAR_CREDITS = 2;

    protected static $table_exists = null;
    protected static $rendered_ids = [];

    public static function init() {
        // No-JS month / filter URLs: keep them out of search indexes
        add_filter('wp_robots', [__CLASS__, 'robots']);
        add_action('send_headers', [__CLASS__, 'robots_header']);

        // Refresh cached pages that show the calendar after midnight
        if (class_exists(__NAMESPACE__ . '\\Course_Stage')) {
            add_action(Course_Stage::CRON_DAILY, [__CLASS__, 'purge_calendar_pages'], 20);
        }
        add_action('elementor/editor/after_save', [__CLASS__, 'forget_calendar_pages']);
    }

    /* ============================================================
     * Robots / page cache
     * ============================================================ */

    public static function has_view_params() {
        foreach (self::PARAMS as $p) {
            if (isset($_GET[$p])) {
                return true;
            }
        }
        return false;
    }

    public static function robots($robots) {
        if (self::has_view_params()) {
            $robots['noindex'] = true;
            $robots['follow'] = true;
            unset($robots['index']);
        }
        return $robots;
    }

    public static function robots_header() {
        if (!is_admin() && self::has_view_params() && !headers_sent()) {
            header('X-Robots-Tag: noindex, follow', true);
        }
    }

    /** Front page + published pages whose Elementor data contains the widget (cached for a day). */
    public static function calendar_page_urls() {
        $urls = get_transient('gpsc_cal_pages');
        if (!is_array($urls)) {
            global $wpdb;
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_elementor_data' AND pm.meta_value LIKE %s
                   AND p.post_status = 'publish' AND p.post_type NOT IN ('revision', 'elementor_library')
                 LIMIT 50",
                '%' . $wpdb->esc_like('"widgetType":"gps-event-calendar"') . '%'
            ));
            $urls = [home_url('/')];
            foreach ((array) $ids as $id) {
                $url = get_permalink((int) $id);
                if ($url) {
                    $urls[] = $url;
                }
            }
            $urls = array_values(array_unique($urls));
            set_transient('gpsc_cal_pages', $urls, DAY_IN_SECONDS);
        }
        return (array) apply_filters('gpsc_calendar_page_urls', $urls);
    }

    public static function forget_calendar_pages() {
        delete_transient('gpsc_cal_pages');
    }

    /** Daily (Course_Stage cron): purge pages showing the calendar (FlyingPress when active). */
    public static function purge_calendar_pages() {
        $urls = self::calendar_page_urls();
        if (is_callable(['\\FlyingPress\\Purge', 'purge_url'])) {
            foreach ($urls as $url) {
                call_user_func(['\\FlyingPress\\Purge', 'purge_url'], $url);
            }
        }
        do_action('gpsc_purge_calendar_urls', $urls);
        return $urls;
    }

    /* ============================================================
     * Arguments
     * ============================================================ */

    /**
     * Normalised render arguments (from the widget settings or the AJAX request).
     *
     * base_type      all | courses | seminars: what the widget is set to show
     * type           the visitor's filter (only when base_type is 'all')
     * category       existing gps_event_category term ID or 'all' (courses only)
     * first_day      0 Sunday, 1 Monday
     * per_day        event pills per day cell before "+N more"
     * upcoming       how many upcoming events in the side panel (1-20)
     * show_upcoming  side panel on/off
     * show_today     "Today" button
     * show_category  category select
     */
    public static function args(array $a) {
        $a = wp_parse_args($a, [
            'id'             => '',
            'base_type'      => 'all',
            'type'           => '',
            'category'       => 'all',
            'first_day'      => 0,
            'per_day'        => 3,
            'upcoming'       => 5,
            'show_upcoming'  => true,
            'show_today'     => true,
            'show_category'  => false,
            'upcoming_title' => __('Upcoming events', 'gps-courses'),
            'base_url'       => '',
        ]);

        $a['id']        = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $a['id']);
        $a['base_type'] = in_array($a['base_type'], self::TYPES, true) ? $a['base_type'] : 'all';
        if ('all' !== $a['base_type'] || !in_array($a['type'], self::TYPES, true)) {
            $a['type'] = $a['base_type'];
        }
        $a['category']  = self::clean_category($a['category']);
        $a['first_day'] = 1 === (int) $a['first_day'] ? 1 : 0;
        $a['per_day']   = max(1, min(10, (int) $a['per_day']));
        $a['upcoming']  = max(1, min(self::MAX_UPCOMING, (int) $a['upcoming']));
        foreach (['show_upcoming', 'show_today', 'show_category'] as $k) {
            $a[$k] = (bool) $a[$k];
        }
        $a['upcoming_title'] = (string) $a['upcoming_title'];
        $a['base_url'] = (string) $a['base_url'];
        return $a;
    }

    /** An existing gps_event_category term ID (as string), else 'all'. */
    public static function clean_category($value) {
        $value = trim((string) $value);
        if ('' === $value || 'all' === $value || !preg_match('/^\d{1,10}$/', $value)) {
            return 'all';
        }
        $term = term_exists((int) $value, 'gps_event_category');
        return $term ? (string) (int) $value : 'all';
    }

    public static function today() {
        return current_time('Y-m-d');
    }

    /** First and last month that can be shown (Y-m). */
    public static function bounds() {
        $year = (int) substr(self::today(), 0, 4);
        return [($year - self::YEARS_BACK) . '-01', ($year + self::YEARS_AHEAD) . '-12'];
    }

    /** Valid Y-m within bounds, or '' (= the current month). */
    public static function clean_month($value) {
        $value = substr(trim((string) $value), 0, 7);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $value, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            return '';
        }
        list($min, $max) = self::bounds();
        if ($value < $min) {
            return $min;
        }
        return $value > $max ? $max : $value;
    }

    /** Month requested by the no-JS controls (?gpsc_cal=Y-m or gpsc_cal_y + gpsc_cal_m), '' when none. */
    public static function requested_month() {
        if (isset($_GET['gpsc_cal']) && is_string($_GET['gpsc_cal'])) {
            return self::clean_month(sanitize_text_field(wp_unslash($_GET['gpsc_cal'])));
        }
        if (isset($_GET['gpsc_cal_y'], $_GET['gpsc_cal_m']) && is_string($_GET['gpsc_cal_y']) && is_string($_GET['gpsc_cal_m'])) {
            return self::clean_month(sprintf('%04d-%02d', (int) $_GET['gpsc_cal_y'], (int) $_GET['gpsc_cal_m']));
        }
        return '';
    }

    /** Visitor filters from the no-JS controls (validated again by args()). */
    public static function requested_filters() {
        $out = [];
        if (isset($_GET['gpsc_cal_type']) && is_string($_GET['gpsc_cal_type'])) {
            $out['type'] = sanitize_key(wp_unslash($_GET['gpsc_cal_type']));
        }
        if (isset($_GET['gpsc_cal_cat']) && is_string($_GET['gpsc_cal_cat'])) {
            $out['category'] = sanitize_key(wp_unslash($_GET['gpsc_cal_cat']));
        }
        return $out;
    }

    /* ============================================================
     * Data
     * ============================================================ */

    /**
     * Events overlapping [$from, $to] (Y-m-d, inclusive), soonest first.
     *
     * @return array[] id, type (course|seminar), title, kicker, start, end, time,
     *                 start_time, sort_time, url, location, credits, status, status_label
     */
    public static function events_between($from, $to, $type = 'all', $category = 'all') {
        $key = self::cache_key(['between', $from, $to, $type, $category]);
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }

        $events = [];
        if ('seminars' !== $type) {
            // Courses rarely last more than a few days: look back two months for ones still running
            $ids = self::course_ids([
                'meta_query' => [[
                    'key'     => '_gps_start_date',
                    'value'   => [self::add_days($from, -62), $to],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ]],
            ], $category, 300);
            foreach ($ids as $id) {
                $row = self::course_row($id);
                if ($row && $row['start'] <= $to && $row['end'] >= $from) {
                    $events[] = $row;
                }
            }
        }
        if ('courses' !== $type) {
            foreach (self::sessions($from, $to) as $s) {
                $events[] = self::session_row($s);
            }
        }

        $events = self::sort($events);
        set_transient($key, $events, self::CACHE_TTL);
        return $events;
    }

    /** The next $limit events from today (running courses included), soonest first. */
    public static function upcoming($limit = 5, $type = 'all', $category = 'all') {
        $limit = max(1, min(self::MAX_UPCOMING, (int) $limit));
        $key = self::cache_key(['upcoming', $limit, $type, $category]);
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }

        $today  = self::today();
        $events = [];
        if ('seminars' !== $type) {
            // Same rule as the course catalog: upcoming until the end date (the start date without one)
            $ids = self::course_ids([
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => '_gps_end_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
                    [
                        'relation' => 'AND',
                        ['key' => '_gps_start_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
                        ['relation' => 'OR', ['key' => '_gps_end_date', 'compare' => 'NOT EXISTS'], ['key' => '_gps_end_date', 'value' => '']],
                    ],
                ],
            ], $category, $limit + 10);
            foreach ($ids as $id) {
                $row = self::course_row($id);
                if ($row && $row['end'] >= $today && 'past' !== $row['status']) {
                    $events[] = $row;
                }
            }
        }
        if ('courses' !== $type) {
            $until = ((int) substr($today, 0, 4) + self::YEARS_AHEAD + 1) . substr($today, 4);
            foreach (self::sessions($today, $until, $limit) as $s) {
                $events[] = self::session_row($s);
            }
        }

        $events = array_slice(self::sort($events), 0, $limit);
        set_transient($key, $events, self::CACHE_TTL);
        return $events;
    }

    protected static function cache_key(array $parts) {
        $parts[] = self::today();
        $parts[] = (string) get_option('gpsc_catalog_ver', 0);
        return 'gpsc_cal_' . md5(wp_json_encode($parts));
    }

    protected static function sort(array $events) {
        usort($events, function ($a, $b) {
            $c = strcmp($a['start'], $b['start']);
            if (0 === $c) {
                $c = strcmp($a['sort_time'], $b['sort_time']);
            }
            return 0 === $c ? strcmp($a['title'], $b['title']) : $c;
        });
        return $events;
    }

    protected static function course_ids(array $args, $category, $limit) {
        $args = array_merge([
            'post_type'      => 'gps_event',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $limit,
            'meta_key'       => '_gps_start_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ], $args);
        if ('all' !== $category) {
            $args['tax_query'] = [[
                'taxonomy' => 'gps_event_category',
                'field'    => 'term_id',
                'terms'    => (int) $category,
            ]];
        }
        $query = new \WP_Query(apply_filters('gpsc_calendar_course_query_args', $args));
        return array_map('intval', (array) $query->posts);
    }

    protected static function plain_title($post_id) {
        $title = (string) get_post_field('post_title', $post_id);
        return html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES, 'UTF-8');
    }

    /** One course as a calendar event, null without a valid start date. */
    protected static function course_row($id) {
        if (class_exists(__NAMESPACE__ . '\\Course_Stage')) {
            list($start, $end) = Course_Stage::dates($id);
        } else {
            $start = substr((string) get_post_meta($id, '_gps_start_date', true), 0, 10);
            $end   = substr((string) get_post_meta($id, '_gps_end_date', true), 0, 10);
            $end   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && $end >= $start) ? $end : $start;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $start)) {
            return null;
        }

        $today = self::today();
        if (class_exists(__NAMESPACE__ . '\\Course_Stage')) {
            $status = Course_Stage::get($id);
            $label  = Course_Stage::label($status);
        } else {
            $status = $end < $today ? 'past' : 'open';
            $label  = 'past' === $status ? __('Took place', 'gps-courses') : '';
        }

        $start_time = (string) get_post_meta($id, '_gps_start_time', true);
        $end_time   = (string) get_post_meta($id, '_gps_end_time', true);
        $venue      = (string) get_post_meta($id, '_gps_venue', true);

        return [
            'id'           => 'course-' . (int) $id,
            'type'         => 'course',
            'title'        => self::plain_title($id),
            'kicker'       => __('Course', 'gps-courses'),
            'start'        => $start,
            'end'          => $end,
            'time'         => self::time_range($start_time, $end_time),
            'start_time'   => self::time_label($start_time),
            'sort_time'    => self::sort_time($start_time),
            'url'          => (string) get_permalink($id),
            'location'     => $venue ?: (string) get_post_meta($id, '_gps_location', true),
            'credits'      => (float) get_post_meta($id, '_gps_ce_credits', true),
            'status'       => $status,
            'status_label' => $label,
        ];
    }

    /** Published seminar sessions between two dates (optionally the first $limit). */
    protected static function sessions($from, $to, $limit = 0) {
        global $wpdb;
        if (!self::sessions_table_exists()) {
            return [];
        }
        $table = $wpdb->prefix . 'gps_seminar_sessions';
        $sql = "SELECT ss.id, ss.seminar_id, ss.session_number, ss.session_date, ss.session_time_start,
                       ss.session_time_end, ss.topic, p.post_title AS seminar_title
                FROM {$table} ss
                INNER JOIN {$wpdb->posts} p ON ss.seminar_id = p.ID
                WHERE p.post_status = 'publish'
                AND ss.session_date BETWEEN %s AND %s
                ORDER BY ss.session_date ASC, ss.session_time_start ASC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        $rows = $wpdb->get_results($wpdb->prepare($sql, $from, $to));
        return is_array($rows) ? $rows : [];
    }

    protected static function sessions_table_exists() {
        if (null === self::$table_exists) {
            global $wpdb;
            $table = $wpdb->prefix . 'gps_seminar_sessions';
            self::$table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table);
        }
        return self::$table_exists;
    }

    protected static function session_row($s) {
        $date   = substr((string) $s->session_date, 0, 10);
        $today  = self::today();
        $number = (int) $s->session_number;
        $topic  = trim(html_entity_decode((string) $s->topic, ENT_QUOTES, 'UTF-8'));

        if ($date < $today) {
            $status = 'past';
            $label  = __('Took place', 'gps-courses');
        } elseif ($date === $today) {
            $status = 'in_progress';
            $label  = __('Today', 'gps-courses');
        } else {
            $status = 'seminar';
            $label  = __('Monthly seminar', 'gps-courses');
        }

        /* translators: %d: session number */
        $session = sprintf(__('Session %d', 'gps-courses'), $number);
        $kicker  = __('Monthly Seminar', 'gps-courses');
        if ($number > 0) {
            $kicker .= ' · ' . str_replace(' ', "\u{00A0}", $session);
        }

        return [
            'id'           => 'session-' . (int) $s->id,
            'type'         => 'seminar',
            'title'        => '' !== $topic ? $topic : $session,
            'kicker'       => $kicker,
            'start'        => $date,
            'end'          => $date,
            'time'         => self::time_range((string) $s->session_time_start, (string) $s->session_time_end),
            'start_time'   => self::time_label((string) $s->session_time_start),
            'sort_time'    => self::sort_time((string) $s->session_time_start),
            'url'          => self::seminar_url((int) $s->seminar_id),
            'location'     => '',
            'credits'      => (float) apply_filters('gpsc_calendar_seminar_credits', self::SEMINAR_CREDITS, $s),
            'status'       => $status,
            'status_label' => $label,
        ];
    }

    /** Where a seminar session links: the seminar's WooCommerce product. */
    public static function seminar_url($seminar_id) {
        static $urls = [];
        if (!isset($urls[$seminar_id])) {
            $url = '';
            $product = (int) get_post_meta($seminar_id, '_gps_seminar_product_id', true);
            if ($product && 'publish' === get_post_status($product)) {
                $url = (string) get_permalink($product);
            }
            $urls[$seminar_id] = (string) apply_filters('gpsc_calendar_seminar_url', $url ?: home_url('/product/gps-monthly-seminars/'), $seminar_id);
        }
        return $urls[$seminar_id];
    }

    /** "6 PM", "9:30 AM" from "18:00", "09:30:00" or "9:30 AM"; '' when unreadable. */
    public static function time_label($time, $meridiem = true) {
        $ts = self::time_ts($time);
        if (!$ts) {
            return '';
        }
        $label = gmdate('i', $ts) === '00' ? gmdate('g', $ts) : gmdate('g:i', $ts);
        return $meridiem ? $label . "\u{00A0}" . gmdate('A', $ts) : $label;
    }

    /** "6–8 PM", "11 AM – 1 PM", "9 AM"; '' without a start time. */
    public static function time_range($start, $end) {
        $s = self::time_ts($start);
        if (!$s) {
            return '';
        }
        $e = self::time_ts($end);
        if (!$e || $e <= $s) {
            return self::time_label($start);
        }
        if (gmdate('A', $s) === gmdate('A', $e)) {
            return self::time_label($start, false) . '–' . self::time_label($end);
        }
        return self::time_label($start) . ' – ' . self::time_label($end);
    }

    protected static function time_ts($time) {
        $time = trim((string) $time);
        if ('' === $time || !preg_match('/^\d{1,2}:\d{2}/', $time)) {
            return 0;
        }
        $ts = strtotime('2000-01-01 ' . $time . ' UTC');
        return $ts ?: 0;
    }

    protected static function sort_time($time) {
        $ts = self::time_ts($time);
        return $ts ? gmdate('H:i', $ts) : '99:99';
    }

    /* ============================================================
     * Formatting (midnight-UTC timestamps, independent of PHP's timezone)
     * ============================================================ */

    protected static function ts($ymd) {
        list($y, $m, $d) = array_map('intval', explode('-', substr((string) $ymd, 0, 10)) + [0, 1, 1]);
        return gmmktime(0, 0, 0, $m, $d, $y);
    }

    protected static function add_days($ymd, $days) {
        return gmdate('Y-m-d', self::ts($ymd) + (int) $days * DAY_IN_SECONDS);
    }

    public static function month_title($ym) {
        return date_i18n('F Y', self::ts($ym . '-01'));
    }

    /** "Sep 17–18, 2027", "Sep 30 – Oct 2, 2027", "Oct 26, 2026" */
    public static function date_range($start, $end) {
        $s = self::ts($start);
        $e = $end ? self::ts($end) : $s;
        if ($e <= $s) {
            return date_i18n('M j, Y', $s);
        }
        if (gmdate('Y-m', $s) === gmdate('Y-m', $e)) {
            return date_i18n('M j', $s) . '–' . date_i18n('j, Y', $e);
        }
        if (gmdate('Y', $s) === gmdate('Y', $e)) {
            return date_i18n('M j', $s) . ' – ' . date_i18n('M j, Y', $e);
        }
        return date_i18n('M j, Y', $s) . ' – ' . date_i18n('M j, Y', $e);
    }

    public static function credits_label($credits) {
        $credits = (float) $credits;
        if ($credits <= 0) {
            return '';
        }
        $n = floor($credits) == $credits ? number_format_i18n($credits) : number_format_i18n($credits, 1);
        /* translators: %s: number of CE credits */
        return sprintf(_n('%s CE credit', '%s CE credits', $credits == 1 ? 1 : 2, 'gps-courses'), $n);
    }

    protected static function type_label($type) {
        return 'seminar' === $type ? __('Monthly seminar', 'gps-courses') : __('Course', 'gps-courses');
    }

    /** Spoken name of a pill: starts with its visible text (time + title), then type, date, status. */
    protected static function describe(array $e, $visible) {
        $parts = [$visible, self::type_label($e['type'])];
        $parts[] = $e['start'] === $e['end']
            ? date_i18n('l, F j, Y', self::ts($e['start']))
            : self::date_range($e['start'], $e['end']);
        if ('' !== $e['time']) {
            $parts[] = $e['time'];
        }
        if ('' !== $e['status_label'] && 'seminar' !== $e['status']) {
            $parts[] = $e['status_label'];
        }
        return implode(', ', $parts);
    }

    /** Link that reproduces a view without JS: clean page URL + calendar params only. */
    public static function view_url(array $a, $month = null, $type = null, $category = null) {
        $base = '' !== $a['base_url'] ? $a['base_url'] : home_url('/');
        $params = [];
        if (null !== $month && '' !== $month) {
            $params['gpsc_cal'] = $month;
        }
        $type = null === $type ? $a['type'] : $type;
        if ('all' === $a['base_type'] && 'all' !== $type) {
            $params['gpsc_cal_type'] = $type;
        }
        $category = null === $category ? $a['category'] : $category;
        if ($a['show_category'] && 'all' !== $category) {
            $params['gpsc_cal_cat'] = $category;
        }
        return $params ? add_query_arg($params, $base) : $base;
    }

    /** The page the widget is on, without any query string. */
    protected static function base_url() {
        $url = '';
        $id = (int) get_queried_object_id();
        if ($id && is_singular()) {
            $url = (string) get_permalink($id);
        }
        if ('' === $url && (is_front_page() || is_home())) {
            $url = home_url('/');
        }
        if ('' === $url) {
            $url = (string) get_permalink();
        }
        $url = $url ?: home_url('/');
        return strtok($url, '?#');
    }

    protected static function shift_month($ym, $delta) {
        list($y, $m) = array_map('intval', explode('-', $ym));
        $m += $delta;
        while ($m < 1) { $m += 12; $y--; }
        while ($m > 12) { $m -= 12; $y++; }
        return sprintf('%04d-%02d', $y, $m);
    }

    /** prev / next month info for the navigation (null at the bounds). */
    public static function neighbours($ym) {
        list($min, $max) = self::bounds();
        $out = [];
        foreach (['prev' => -1, 'next' => 1] as $k => $d) {
            $n = self::shift_month($ym, $d);
            $out[$k] = ($n < $min || $n > $max) ? null : ['month' => $n, 'title' => self::month_title($n)];
        }
        return $out;
    }

    /** "Showing all events – October 2026" (polite status after a change). */
    protected static function showing($type, $month) {
        $labels = [
            'all'      => __('all events', 'gps-courses'),
            'courses'  => __('courses', 'gps-courses'),
            'seminars' => __('monthly seminars', 'gps-courses'),
        ];
        /* translators: 1: what is shown, 2: month and year */
        return sprintf(__('Showing %1$s – %2$s', 'gps-courses'), isset($labels[$type]) ? $labels[$type] : $labels['all'], self::month_title($month));
    }

    /* ============================================================
     * Markup
     * ============================================================ */

    /**
     * The whole calendar (widget output).
     *
     * @param array  $a     see args()
     * @param string $month Y-m to show; '' = the visitor's request or the current month
     */
    public static function render(array $a, $month = '') {
        $requested = self::requested_month();
        if (empty($a['base_url'])) {
            $a['base_url'] = self::base_url();
        }
        $a = self::args(array_merge($a, self::requested_filters_for($a)));
        $explicit = '' !== $month || '' !== $requested;
        $month = self::clean_month($month ?: $requested) ?: substr(self::today(), 0, 7);

        // Unique ids even when the same widget renders twice (global widget, template)
        $uid = 'gps-calendar-' . ($a['id'] ?: 'cal');
        if (isset(self::$rendered_ids[$uid])) {
            $uid .= '-' . (++self::$rendered_ids[$uid]);
        } else {
            self::$rendered_ids[$uid] = 1;
        }
        $title_id = $uid . '-title';
        $nb    = self::neighbours($month);
        $today = self::today();
        list($min, $max) = self::bounds();
        $year  = (int) substr($month, 0, 4);
        $mon   = (int) substr($month, 5, 2);

        $html  = '<div class="gpsc-cal' . ($a['show_upcoming'] ? '' : ' gpsc-cal--no-side') . '" id="' . esc_attr($uid) . '" data-gpsc-cal'
               . ' data-id="' . esc_attr($a['id']) . '"'
               . ' data-ajax="' . esc_url(admin_url('admin-ajax.php')) . '"'
               . ' data-month="' . esc_attr($month) . '" data-explicit="' . ($explicit ? '1' : '0') . '"'
               . ' data-today="' . esc_attr($today) . '"'
               . ' data-rendered="' . (int) time() . '"'
               . ' data-tz-offset="' . (int) ((int) current_time('timestamp') - time()) . '"'
               . ' data-type="' . esc_attr($a['type']) . '" data-base-type="' . esc_attr($a['base_type']) . '"'
               . ' data-category="' . esc_attr($a['category']) . '"'
               . ' data-first-day="' . (int) $a['first_day'] . '" data-per-day="' . (int) $a['per_day'] . '"'
               . ' data-upcoming="' . ($a['show_upcoming'] ? (int) $a['upcoming'] : 0) . '"'
               . ' data-i18n="' . esc_attr(wp_json_encode(self::js_strings())) . '">';

        // ---- Header: month title + prev / today / next
        $html .= '<div class="gpsc-cal__top">';
        $html .= '<h3 class="gpsc-cal__title" id="' . esc_attr($title_id) . '" data-gpsc-title tabindex="-1">' . esc_html(self::month_title($month)) . '</h3>';
        $html .= '<nav class="gpsc-cal__nav" aria-label="' . esc_attr__('Change month', 'gps-courses') . '">';
        $html .= self::nav_link($a, 'prev', $nb['prev']);
        if ($a['show_today']) {
            $html .= '<a class="gpsc-cal__btn gpsc-cal__btn--today" data-gpsc-nav="today" rel="nofollow" href="' . esc_url(self::view_url($a)) . '">'
                   . esc_html__('Today', 'gps-courses') . '</a>';
        }
        $html .= self::nav_link($a, 'next', $nb['next']);
        $html .= '</nav></div>';

        // ---- Toolbar: jump selects (+ category) and type filters
        $html .= '<div class="gpsc-cal__bar">';
        $html .= '<form class="gpsc-cal__jump" method="get" action="' . esc_url($a['base_url']) . '" data-gpsc-jump>';
        $html .= '<label class="gpsc-cal__sr" for="' . esc_attr($uid) . '-m">' . esc_html__('Month', 'gps-courses') . '</label>';
        $html .= '<select class="gpsc-cal__select" id="' . esc_attr($uid) . '-m" name="gpsc_cal_m" data-gpsc-month-select>';
        for ($i = 1; $i <= 12; $i++) {
            $html .= '<option value="' . $i . '"' . ($i === $mon ? ' selected' : '') . '>' . esc_html(date_i18n('F', self::ts(sprintf('2001-%02d-01', $i)))) . '</option>';
        }
        $html .= '</select>';
        $html .= '<label class="gpsc-cal__sr" for="' . esc_attr($uid) . '-y">' . esc_html__('Year', 'gps-courses') . '</label>';
        $html .= '<select class="gpsc-cal__select" id="' . esc_attr($uid) . '-y" name="gpsc_cal_y" data-gpsc-year-select>';
        for ($y = (int) substr($min, 0, 4); $y <= (int) substr($max, 0, 4); $y++) {
            $html .= '<option value="' . $y . '"' . ($y === $year ? ' selected' : '') . '>' . $y . '</option>';
        }
        $html .= '</select>';
        if ($a['show_category']) {
            $html .= self::category_select($uid, $a);
        }
        if ('all' === $a['base_type'] && 'all' !== $a['type']) {
            $html .= '<input type="hidden" name="gpsc_cal_type" value="' . esc_attr($a['type']) . '">';
        }
        $html .= '<noscript><button type="submit" class="gpsc-cal__btn gpsc-cal__btn--go">' . esc_html__('Go', 'gps-courses') . '</button></noscript>';
        $html .= '</form>';

        if ('all' === $a['base_type']) {
            $html .= '<ul class="gpsc-cal__filters" aria-label="' . esc_attr__('Show', 'gps-courses') . '">';
            foreach ([
                'all'      => __('All', 'gps-courses'),
                'courses'  => __('Courses', 'gps-courses'),
                'seminars' => __('Seminars', 'gps-courses'),
            ] as $t => $label) {
                $on = $t === $a['type'];
                $html .= '<li><a class="gpsc-cal__filter gpsc-cal__filter--' . esc_attr($t) . ($on ? ' is-active' : '') . '"'
                       . ' data-gpsc-type="' . esc_attr($t) . '" rel="nofollow"'
                       . ' href="' . esc_url(self::view_url($a, $explicit ? $month : null, $t)) . '"'
                       . ($on ? ' aria-current="true"' : '') . '>'
                       . ('all' !== $t ? '<span class="gpsc-cal__swatch" aria-hidden="true"></span>' : '')
                       . esc_html($label) . '</a></li>';
            }
            $html .= '</ul>';
        }
        $html .= '</div>';

        // Errors (visible) and a concise screen-reader status after each change
        $html .= '<div class="gpsc-cal__status" data-gpsc-status role="status" aria-live="polite"></div>';
        $html .= '<div class="gpsc-cal__sr" data-gpsc-live role="status" aria-live="polite"></div>';

        // ---- Month + side panel (or day details below the month)
        $daypanel = self::render_day_panel($a, $uid);
        $html .= '<div class="gpsc-cal__layout">';
        $html .= '<div class="gpsc-cal__main" data-gpsc-main>' . self::render_month($month, $a, $title_id) . '</div>';
        if ($a['show_upcoming']) {
            $html .= '<aside class="gpsc-cal__side" aria-label="' . esc_attr($a['upcoming_title'] ?: __('Upcoming events', 'gps-courses')) . '">';
            $html .= '<div data-gpsc-upcoming>' . self::render_upcoming($a, $uid) . '</div>';
            $html .= $daypanel;
            $html .= '</aside>';
        } else {
            $html .= '<div class="gpsc-cal__dayarea">' . $daypanel . '</div>';
        }
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    protected static function render_day_panel(array $a, $uid) {
        $back = $a['show_upcoming'] ? __('Back to upcoming events', 'gps-courses') : __('Close day details', 'gps-courses');
        return '<section class="gpsc-cal__panel gpsc-cal__panel--day" data-gpsc-daypanel aria-labelledby="' . esc_attr($uid) . '-day" hidden>'
             . '<div class="gpsc-cal__panelhead">'
             . '<h3 class="gpsc-cal__paneltitle" id="' . esc_attr($uid) . '-day" data-gpsc-daytitle tabindex="-1"></h3>'
             . '<button type="button" class="gpsc-cal__back" data-gpsc-back>' . esc_html($back) . '</button>'
             . '</div><div data-gpsc-daylist></div></section>';
    }

    /** No-JS filters only apply when the widget lets the visitor choose. */
    protected static function requested_filters_for(array $a) {
        $f = self::requested_filters();
        $base = isset($a['base_type']) && in_array($a['base_type'], self::TYPES, true) ? $a['base_type'] : 'all';
        if ('all' !== $base) {
            unset($f['type']);
        }
        if (empty($a['show_category'])) {
            unset($f['category']);
        }
        return $f;
    }

    protected static function nav_link(array $a, $dir, $target) {
        $icon = 'prev' === $dir
            ? '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            : '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $label = 'prev' === $dir ? __('Previous month', 'gps-courses') : __('Next month', 'gps-courses');
        if (!$target) {
            // Kept in place (no href): focus never lands on a removed element
            return '<a class="gpsc-cal__btn gpsc-cal__btn--icon is-disabled" data-gpsc-nav="' . esc_attr($dir) . '" role="link" aria-disabled="true" tabindex="-1"'
                 . ' aria-label="' . esc_attr($label) . '">' . $icon . '</a>';
        }
        return '<a class="gpsc-cal__btn gpsc-cal__btn--icon" data-gpsc-nav="' . esc_attr($dir) . '" data-month="' . esc_attr($target['month']) . '" rel="nofollow"'
             . ' href="' . esc_url(self::view_url($a, $target['month'])) . '"'
             . ' aria-label="' . esc_attr($label . ': ' . $target['title']) . '">' . $icon . '</a>';
    }

    protected static function category_select($uid, array $a) {
        $terms = get_terms(['taxonomy' => 'gps_event_category', 'hide_empty' => false]);
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }
        $html  = '<label class="gpsc-cal__sr" for="' . esc_attr($uid) . '-c">' . esc_html__('Category', 'gps-courses') . '</label>';
        $html .= '<select class="gpsc-cal__select gpsc-cal__select--cat" id="' . esc_attr($uid) . '-c" name="gpsc_cal_cat" data-gpsc-cat-select>';
        $html .= '<option value="all">' . esc_html__('All categories', 'gps-courses') . '</option>';
        foreach ($terms as $t) {
            $html .= '<option value="' . (int) $t->term_id . '"' . ((string) (int) $t->term_id === $a['category'] ? ' selected' : '') . '>' . esc_html($t->name) . '</option>';
        }
        return $html . '</select>';
    }

    /**
     * One month: the table (wide screens) and the agenda of days with events
     * (phones, and the source of the day panel). Also what the AJAX action
     * returns as month_html.
     */
    public static function render_month($month, array $a, $title_id = '') {
        $today  = self::today();
        $first  = $month . '-01';
        $last   = gmdate('Y-m-t', self::ts($first));
        $events = self::events_between($first, $last, $a['type'], $a['category']);

        // Events by day (multi-day courses on each of their days within the month)
        $by_day = [];
        foreach ($events as $e) {
            $d = max($e['start'], $first);
            $end = min($e['end'], $last);
            while ($d <= $end) {
                $by_day[$d][] = $e;
                $d = self::add_days($d, 1);
            }
        }

        // Leading days from the previous month
        $lead  = ((int) gmdate('w', self::ts($first)) - $a['first_day'] + 7) % 7;
        $days  = (int) gmdate('t', self::ts($first));
        $cells = (int) ceil(($lead + $days) / 7) * 7;
        $start = self::add_days($first, -$lead);

        $html  = '<table class="gpsc-cal__grid"' . ($title_id ? ' aria-labelledby="' . esc_attr($title_id) . '"' : '') . '><thead><tr>';
        for ($i = 0; $i < 7; $i++) {
            // 2001-01-07 was a Sunday
            $ts = self::ts(sprintf('2001-01-%02d', 7 + ($i + $a['first_day']) % 7));
            $html .= '<th scope="col"><abbr title="' . esc_attr(date_i18n('l', $ts)) . '">' . esc_html(date_i18n('D', $ts)) . '</abbr></th>';
        }
        $html .= '</tr></thead><tbody>';

        for ($i = 0; $i < $cells; $i++) {
            $d = self::add_days($start, $i);
            if (0 === $i % 7) {
                $html .= '<tr>';
            }
            $in    = substr($d, 0, 7) === $month;
            $list  = $in && isset($by_day[$d]) ? $by_day[$d] : [];
            $class = ['gpsc-cal__cell'];
            if (!$in) { $class[] = 'is-other'; }
            if ($d < $today) { $class[] = 'is-past'; }
            if ($d === $today) { $class[] = 'is-today'; }
            if ($list) { $class[] = 'has-events'; }

            $html .= '<td class="' . esc_attr(implode(' ', $class)) . '" data-date="' . esc_attr($d) . '"' . ($d === $today ? ' aria-current="date"' : '') . '>';
            $html .= '<div class="gpsc-cal__cellin">';
            $long = date_i18n('l, F j, Y', self::ts($d));
            if ($d === $today) {
                /* translators: %s: full date */
                $long = sprintf(__('Today, %s', 'gps-courses'), $long);
            }
            $num  = (int) substr($d, 8, 2);
            // Plain text without JS; calendar.js turns [data-gpsc-btn] into buttons
            $html .= '<span class="gpsc-cal__daynum"';
            if ($list) {
                /* translators: 1: full date, 2: number of events */
                $label = sprintf(_n('%1$s: %2$d event. Show details', '%1$s: %2$d events. Show details', count($list), 'gps-courses'), $long, count($list));
                $html .= ' data-gpsc-btn data-gpsc-day="' . esc_attr($d) . '" data-label="' . esc_attr($label) . '" data-num="' . $num . '"';
            }
            $html .= '><span class="gpsc-cal__sr">' . esc_html($long) . '</span><span aria-hidden="true">' . $num . '</span></span>';
            if ($list) {
                $html .= '<ul class="gpsc-cal__pills">';
                foreach ($list as $n => $e) {
                    $html .= self::render_pill($e, $d, $n >= $a['per_day']);
                }
                $html .= '</ul>';
                $more = count($list) - $a['per_day'];
                if ($more > 0) {
                    /* translators: 1: number of hidden events, 2: full date */
                    $more_label = sprintf(_n('%1$d more event on %2$s', '%1$d more events on %2$s', $more, 'gps-courses'), $more, $long);
                    // Only shown once enhanced (without JS every pill is visible)
                    $html .= '<span class="gpsc-cal__more" data-gpsc-btn data-gpsc-day="' . esc_attr($d) . '" data-label="' . esc_attr($more_label) . '">'
                           /* translators: %d: number of hidden events */
                           . esc_html(sprintf(__('+%d more', 'gps-courses'), $more)) . '</span>';
                }
            }
            $html .= '</div></td>';
            if (6 === $i % 7) {
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';

        // Legend
        $html .= '<ul class="gpsc-cal__legend" aria-label="' . esc_attr__('Legend', 'gps-courses') . '">';
        if ('seminars' !== $a['base_type']) {
            $html .= '<li><span class="gpsc-cal__swatch gpsc-cal__swatch--course" aria-hidden="true"></span>' . esc_html__('Course', 'gps-courses') . '</li>';
        }
        if ('courses' !== $a['base_type']) {
            $html .= '<li><span class="gpsc-cal__swatch gpsc-cal__swatch--seminar" aria-hidden="true"></span>' . esc_html__('Monthly seminar', 'gps-courses') . '</li>';
        }
        $html .= '<li><span class="gpsc-cal__swatch gpsc-cal__swatch--today" aria-hidden="true"></span>' . esc_html__('Today', 'gps-courses') . '</li>';
        $html .= '</ul>';

        // Agenda: days with events (phones; also the source of the day panel)
        $html .= '<div class="gpsc-cal__agenda">';
        if ($by_day) {
            ksort($by_day);
            $html .= '<ol class="gpsc-cal__days">';
            foreach ($by_day as $d => $list) {
                // Multi-day courses are listed once (first day in the month) in the agenda;
                // the other days stay in the markup, hidden, for the day panel.
                $cont = 0;
                foreach ($list as $e) {
                    if ($d !== max($e['start'], $first)) {
                        $cont++;
                    }
                }
                $html .= '<li class="gpsc-cal__aday' . ($d < $today ? ' is-past' : '') . ($d === $today ? ' is-today' : '') . ($cont === count($list) ? ' is-cont' : '') . '" data-gpsc-agenda="' . esc_attr($d) . '">'
                       . '<h4 class="gpsc-cal__adate"><time datetime="' . esc_attr($d) . '">' . esc_html(date_i18n('l, F j', self::ts($d))) . '</time>'
                       . ($d === $today ? ' <span class="gpsc-cal__todaytag">' . esc_html__('Today', 'gps-courses') . '</span>' : '') . '</h4>'
                       . '<ul class="gpsc-cal__cards">';
                foreach ($list as $e) {
                    $html .= self::render_card($e, $d !== max($e['start'], $first));
                }
                $html .= '</ul></li>';
            }
            $html .= '</ol>';
        } else {
            /* translators: %s: month and year */
            $html .= '<p class="gpsc-cal__empty gpsc-cal__empty--month">' . esc_html(sprintf(__('No events in %s.', 'gps-courses'), self::month_title($month))) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    protected static function render_pill(array $e, $day, $overflow = false) {
        $class = 'gpsc-cal__pill gpsc-cal__pill--' . sanitize_html_class($e['type']);
        if ($e['end'] < self::today()) {
            $class .= ' is-past';
        }
        $continuation = false;
        if ($e['start'] !== $e['end']) {
            if ($day === $e['start']) {
                $class .= ' is-first';
            } else {
                $class .= $day === $e['end'] ? ' is-last' : ' is-mid';
                $continuation = true;
            }
        }
        if ('sold_out' === $e['status']) {
            $class .= ' is-sold-out';
        }
        $show_time = '' !== $e['start_time'] && $day === $e['start'];
        $visible   = ($show_time ? $e['start_time'] . ' ' : '') . $e['title'];
        $attrs = $continuation
            // Same link as the first day of the bar: hidden from tab order and assistive tech
            ? ' aria-hidden="true" tabindex="-1"'
            : ' aria-label="' . esc_attr(self::describe($e, $visible)) . '"';
        return '<li' . ($overflow ? ' class="is-overflow"' : '') . '><a class="' . esc_attr($class) . '" href="' . esc_url($e['url']) . '"' . $attrs
             . ' title="' . esc_attr($e['title'] . ('' !== $e['time'] ? ' · ' . $e['time'] : '')) . '">'
             . ($show_time ? '<span class="gpsc-cal__pilltime">' . esc_html($e['start_time']) . '</span> ' : '')
             . '<span class="gpsc-cal__pilltitle">' . esc_html($e['title']) . '</span></a></li>';
    }

    /** Event card (upcoming panel, agenda, day panel). */
    public static function render_card(array $e, $continued = false) {
        $today = self::today();
        $past  = $e['end'] < $today;
        $s     = self::ts($e['start']);
        $x     = self::ts($e['end']);

        // Date tile: "OCT / 26", "SEP / 17–18"; across months the start day only
        $tile_m = date_i18n('M', $s);
        $tile_d = date_i18n('j', $s);
        if ($e['end'] !== $e['start'] && gmdate('Y-m', $s) === gmdate('Y-m', $x)) {
            $tile_d .= '–' . date_i18n('j', $x);
        }

        $when = $e['start'] === $e['end']
            ? date_i18n('D, M j, Y', $s)
            : self::date_range($e['start'], $e['end']);
        if ('' !== $e['time']) {
            $when .= ' · ' . $e['time'];
        }

        $chip = '';
        if ('' !== $e['status_label'] && 'seminar' !== $e['status']) {
            $chip = '<span class="gpsc-cal__chip gpsc-cal__chip--' . esc_attr(sanitize_html_class($e['status'])) . '">' . esc_html($e['status_label']) . '</span>';
        }
        $credits = self::credits_label($e['credits']);

        return '<li class="gpsc-cal__card gpsc-cal__card--' . esc_attr(sanitize_html_class($e['type'])) . ($past ? ' is-past' : '') . ($continued ? ' is-cont' : '') . '">'
             . '<a class="gpsc-cal__cardlink" href="' . esc_url($e['url']) . '">'
             . '<span class="gpsc-cal__tile' . (strlen($tile_d) > 2 ? ' is-range' : '') . '" aria-hidden="true"><span class="gpsc-cal__tile-m">' . esc_html($tile_m) . '</span><span class="gpsc-cal__tile-d">' . esc_html($tile_d) . '</span></span>'
             . '<span class="gpsc-cal__cardbody">'
             . '<span class="gpsc-cal__kicker">' . esc_html($e['kicker']) . '</span>'
             . '<span class="gpsc-cal__cardtitle">' . esc_html($e['title']) . '</span>'
             . '<span class="gpsc-cal__meta"><span class="gpsc-cal__when">' . esc_html($when) . '</span>'
             . ('' !== $credits ? '<span class="gpsc-cal__credits">' . esc_html($credits) . '</span>' : '')
             . ('' !== (string) $e['location'] ? '<span class="gpsc-cal__where">' . esc_html($e['location']) . '</span>' : '')
             . '</span>'
             . $chip
             . '</span></a></li>';
    }

    /** "Upcoming events" panel: the next events from today, or an empty state. */
    public static function render_upcoming(array $a, $uid = '') {
        $events = self::upcoming($a['upcoming'], $a['type'], $a['category']);
        $courses_url = (string) apply_filters('gpsc_calendar_courses_url', home_url('/courses/'));
        $head_id = ($uid ?: 'gps-calendar') . '-upcoming';

        $html  = '<section class="gpsc-cal__panel" aria-labelledby="' . esc_attr($head_id) . '">';
        $html .= '<div class="gpsc-cal__panelhead"><h3 class="gpsc-cal__paneltitle" id="' . esc_attr($head_id) . '">' . esc_html($a['upcoming_title'] ?: __('Upcoming events', 'gps-courses')) . '</h3>';
        if ($events) {
            $html .= '<a class="gpsc-cal__all" href="' . esc_url($courses_url) . '">' . esc_html__('All courses', 'gps-courses') . ' <span aria-hidden="true">&rarr;</span></a>';
        }
        $html .= '</div>';
        if ($events) {
            $html .= '<ul class="gpsc-cal__cards">';
            foreach ($events as $e) {
                $html .= self::render_card($e);
            }
            $html .= '</ul>';
        } else {
            $html .= '<div class="gpsc-cal__empty gpsc-cal__empty--upcoming">'
                   . '<p>' . esc_html__('No upcoming events are scheduled right now. New dates are added regularly.', 'gps-courses') . '</p>'
                   . '<a class="gpsc-cal__btn gpsc-cal__btn--primary" href="' . esc_url($courses_url) . '">' . esc_html__('Browse courses', 'gps-courses') . '</a>'
                   . '</div>';
        }
        return $html . '</section>';
    }

    protected static function js_strings() {
        return [
            'error' => __('We couldn’t load %s. Check your connection and try again.', 'gps-courses'),
            'retry' => __('Try again', 'gps-courses'),
            'prev'  => __('Previous month', 'gps-courses'),
            'next'  => __('Next month', 'gps-courses'),
        ];
    }

    /* ============================================================
     * AJAX: gps_get_calendar_events (public, read-only, no nonce)
     * ============================================================ */

    public static function ajax() {
        $in = wp_unslash($_POST);
        $get = function ($k, $d = '') use ($in) {
            return isset($in[$k]) && is_scalar($in[$k]) ? sanitize_text_field((string) $in[$k]) : $d;
        };

        // Legacy clients sent year + month (1-12)
        $month = $get('ym');
        if ('' === $month && '' !== $get('year') && '' !== $get('month')) {
            $month = sprintf('%04d-%02d', (int) $get('year'), (int) $get('month'));
        }
        $month = self::clean_month($month) ?: substr(self::today(), 0, 7);

        $base = $get('base_type', '');
        $type = $get('event_type', 'all');
        // All inputs bounded by args(): types, existing categories, 1-20 upcoming, 1-10 per day
        $a = self::args([
            'id'             => $get('id'),
            'base_type'      => in_array($base, self::TYPES, true) ? $base : 'all',
            'type'           => $type,
            'category'       => $get('category', 'all'),
            'first_day'      => (int) $get('first_day', '0'),
            'per_day'        => (int) $get('per_day', '3'),
            'upcoming'       => (int) $get('upcoming', '5') ?: 5,
            'show_category'  => 'all' !== $get('category', 'all'),
            'upcoming_title' => $get('upcoming_title', __('Upcoming events', 'gps-courses')),
        ]);
        // A widget locked to one type (legacy clients send only event_type)
        if ('' === $base && in_array($type, self::TYPES, true)) {
            $a['type'] = $type;
        }

        $uid   = 'gps-calendar-' . ($a['id'] ?: 'cal');
        $first = $month . '-01';
        $last  = gmdate('Y-m-t', self::ts($first));
        $nb    = self::neighbours($month);

        $data = [
            'month'      => $month,
            'title'      => self::month_title($month),
            'showing'    => self::showing($a['type'], $month),
            'today'      => self::today(),
            'rendered'   => time(),
            'prev'       => $nb['prev'],
            'next'       => $nb['next'],
            'month_html' => self::render_month($month, $a, $uid . '-title'),
            // Legacy shape for older scripts
            'events'     => array_map([__CLASS__, 'legacy_event'], self::events_between($first, $last, $a['type'], $a['category'])),
        ];
        if ('1' === $get('with_upcoming')) {
            $data['upcoming_html'] = self::render_upcoming($a, $uid);
        }

        nocache_headers();
        wp_send_json_success($data);
    }

    public static function legacy_event(array $e) {
        return [
            'id'         => $e['id'],
            'title'      => $e['title'],
            'start_date' => $e['start'],
            'end_date'   => $e['end'],
            'start_time' => $e['start_time'],
            'time'       => $e['time'],
            'url'        => $e['url'],
            'location'   => $e['location'],
            'credits'    => $e['credits'],
            'type'       => 'seminar' === $e['type'] ? 'seminar-session' : 'course',
            'status'     => $e['status'],
        ];
    }
}
