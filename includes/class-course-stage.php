<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Where a course is in its life, the single source of truth for what the
 * course page shows (countdown, ticket selector, other dates):
 *
 *   open         upcoming, at least one active ticket that is not sold out
 *   sold_out     upcoming, every active ticket sold out
 *   soon         upcoming, no active ticket yet
 *   in_progress  today is between the start and end date (inclusive)
 *   past         the end date (the start date when there is none) is before today
 *
 * Dates are compared in the site timezone (current_time()).
 *
 * Editions of the same course are separate gps_event posts. The private
 * gps_course_program taxonomy groups them, so each page can list the other
 * dates and a past edition can hand its interest list to the next one.
 */
class Course_Stage {

    const TAXONOMY = 'gps_course_program';

    /** Stages that still lie ahead (registration may open, be open or be full). */
    const UPCOMING = ['open', 'sold_out', 'soon'];

    /** @var array "event_id|Y-m-d" => stage, per request */
    protected static $cache = [];

    public static function init() {
        add_action('init', [__CLASS__, 'register_taxonomy'], 9);
        add_action('save_post_gps_event', [__CLASS__, 'save_program'], 20);

        // Front-end assets, registered early so Elementor widgets can depend on them
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets'], 5);
        add_action('elementor/frontend/after_register_scripts', [__CLASS__, 'register_assets']);
        add_action('elementor/frontend/after_register_styles', [__CLASS__, 'register_assets']);

        // Programs feed gpsc_catalog() ('program', 'other_editions')
        foreach (['created_', 'edited_', 'delete_'] as $prefix) {
            add_action($prefix . self::TAXONOMY, [__CLASS__, 'bump_catalog']);
        }
        add_action('set_object_terms', [__CLASS__, 'on_set_terms'], 10, 6);

        // Editions show each other's dates: refresh their cached pages too
        add_action('gpsc_course_program_changed', [__CLASS__, 'purge_program_pages']);
        add_action('save_post_gps_event', [__CLASS__, 'purge_program_pages'], 40);
        add_action('gpsc_ticket_activated', [__CLASS__, 'purge_program_pages_for_ticket'], 20, 2);

        // Stages change at midnight (soon/open -> in_progress -> past): refresh cached pages
        add_action(self::CRON_DAILY, [__CLASS__, 'daily_refresh']);
        add_action('init', [__CLASS__, 'schedule_daily']);
    }

    const CRON_DAILY = 'gpsc_stage_daily';

    /** Daily at 00:05 site time. */
    public static function schedule_daily() {
        if (wp_next_scheduled(self::CRON_DAILY)) {
            return;
        }
        $local_now  = (int) current_time('timestamp');
        $local_next = strtotime('tomorrow 00:05', $local_now);
        wp_schedule_event(time() + max(60, $local_next - $local_now), 'daily', self::CRON_DAILY);
    }

    /**
     * Courses that start or end today or ended yesterday changed stage at
     * midnight: purge their pages (and their editions') and refresh the catalog.
     */
    public static function daily_refresh() {
        global $wpdb;
        $today     = self::today();
        $yesterday = gmdate('Y-m-d', strtotime($today . ' 00:00:00 UTC') - DAY_IN_SECONDS);
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = 'gps_event' AND p.post_status = 'publish'
               AND pm.meta_key IN ('_gps_start_date', '_gps_end_date') AND pm.meta_value IN (%s, %s)",
            $today,
            $yesterday
        ));
        self::bump_catalog();
        foreach ((array) $ids as $id) {
            self::purge_program_pages((int) $id);
        }
        return array_map('intval', (array) $ids);
    }

    /**
     * Purge the page cache of every edition in the course's program (FlyingPress
     * when active; other caches can hook 'gpsc_purge_course_urls').
     */
    public static function purge_program_pages($event_id) {
        $event_id = (int) $event_id;
        if (!$event_id || 'gps_event' !== get_post_type($event_id) || wp_is_post_revision($event_id)) {
            return;
        }
        $term = self::program($event_id);
        $ids  = $term ? self::program_event_ids($term->term_id) : [];
        $ids[] = $event_id;
        $urls = [];
        foreach (array_unique($ids) as $id) {
            $url = get_permalink($id);
            if ($url) {
                $urls[] = $url;
            }
        }
        if (is_callable(['\\FlyingPress\\Purge', 'purge_url'])) {
            foreach ($urls as $url) {
                call_user_func(['\\FlyingPress\\Purge', 'purge_url'], $url);
            }
        }
        do_action('gpsc_purge_course_urls', $urls, $event_id);
    }

    public static function purge_program_pages_for_ticket($ticket_id, $event_id = 0) {
        self::purge_program_pages($event_id ?: (int) get_post_meta($ticket_id, '_gps_event_id', true));
    }

    /* ============================================================
     * Stage
     * ============================================================ */

    /** Today in the site timezone (Y-m-d). */
    public static function today() {
        return current_time('Y-m-d');
    }

    /** Forget cached stages (after tickets or dates change in this request). */
    public static function reset() {
        self::$cache = [];
    }

    /**
     * Course start and end date (Y-m-d). The end falls back to the start;
     * malformed values count as missing.
     *
     * @return array [start, end]
     */
    public static function dates($event_id) {
        $start = self::clean_date(get_post_meta($event_id, '_gps_start_date', true));
        $end   = self::clean_date(get_post_meta($event_id, '_gps_end_date', true));
        if ('' === $end || ('' !== $start && $end < $start)) {
            $end = $start;
        }
        return [$start, $end];
    }

    protected static function clean_date($value) {
        $value = substr(trim((string) $value), 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * @param int         $event_id gps_event ID
     * @param string|null $today    Y-m-d (tests / previews); default: today in the site timezone
     * @return string open | sold_out | soon | in_progress | past
     */
    public static function get($event_id, $today = null) {
        $event_id = (int) $event_id;
        $today    = $today ?: self::today();
        $key      = $event_id . '|' . $today;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        list($start, $end) = self::dates($event_id);
        if ('' !== $end && $end < $today) {
            $stage = 'past';
        } elseif ('' !== $start && $start <= $today) {
            // During the course: still 'open' while a ticket can be bought (ticket
            // sale dates decide, as in the catalog); otherwise registration is closed
            $stage = 'open' === self::ticket_stage($event_id) ? 'open' : 'in_progress';
        } else {
            $stage = self::ticket_stage($event_id);
        }

        $stage = (string) apply_filters('gpsc_course_stage', $stage, $event_id, $today);
        return self::$cache[$key] = $stage;
    }

    /** Is today between the course's start and end date (whatever its stage)? */
    public static function is_running($event_id, $today = null) {
        $today = $today ?: self::today();
        list($start, $end) = self::dates($event_id);
        return '' !== $start && $start <= $today && $today <= $end;
    }

    /** open | sold_out | soon from the course's active tickets (same rule as the catalog cards). */
    public static function ticket_stage($event_id) {
        if (!class_exists(__NAMESPACE__ . '\\Tickets')) {
            return 'soon';
        }
        $tickets = (array) Tickets::get_active_tickets($event_id);
        if (!$tickets) {
            return 'soon';
        }
        foreach ($tickets as $t) {
            if (!Tickets::is_sold_out(is_object($t) ? $t->ID : (int) $t)) {
                return 'open';
            }
        }
        return 'sold_out';
    }

    public static function is_upcoming($stage) {
        return in_array($stage, self::UPCOMING, true);
    }

    /** Short public label for a stage (status chips). */
    public static function label($stage) {
        switch ($stage) {
            case 'open':
                return __('Registration open', 'gps-courses');
            case 'sold_out':
                return __('Sold out', 'gps-courses');
            case 'soon':
                return __('Opens soon', 'gps-courses');
            case 'in_progress':
                return __('Happening now', 'gps-courses');
            case 'past':
                return __('Took place', 'gps-courses');
        }
        return '';
    }

    /** "Sep 17–18, 2027" style range, or '' without dates. */
    public static function date_range($event_id) {
        list($start, $end) = self::dates($event_id);
        return self::format_range($start, $end);
    }

    public static function format_range($start, $end) {
        if ('' === (string) $start) {
            return '';
        }
        if (class_exists(__NAMESPACE__ . '\\Account_Dashboard')) {
            return Account_Dashboard::date_range($start, $end);
        }
        return date_i18n('M j, Y', strtotime($start));
    }

    /**
     * When the first not-yet-open ticket goes on sale (a "soon" course with a
     * scheduled ticket), as a timestamp in site time; 0 when unknown.
     */
    public static function registration_opens($event_id) {
        $tickets = get_posts([
            'post_type'   => 'gps_ticket',
            'post_status' => 'publish',
            'numberposts' => 20,
            'fields'      => 'ids',
            'no_found_rows' => true,
            'meta_query'  => [['key' => '_gps_event_id', 'value' => (int) $event_id, 'type' => 'NUMERIC']],
        ]);
        $now  = str_replace(' ', 'T', current_time('Y-m-d H:i'));
        $next = '';
        foreach ((array) $tickets as $tid) {
            $start = str_replace(' ', 'T', substr((string) get_post_meta($tid, '_gps_ticket_start_date', true), 0, 16));
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $start) && $start > $now && ('' === $next || $start < $next)) {
                $next = $start;
            }
        }
        return '' === $next ? 0 : (int) strtotime(str_replace('T', ' ', $next));
    }

    /**
     * True when the course has tickets and every one's sale has ended (stage
     * stays 'soon' since nothing is on sale; the ticket selector then says
     * registration is closed instead of "opens soon").
     */
    public static function sales_closed($event_id) {
        // Same window as gpsc_catalog()'s 'closed': an old edition's tickets don't close next year's
        list($start) = self::dates($event_id);
        if ('' === $start || strtotime($start) - strtotime(self::today()) > 45 * DAY_IN_SECONDS) {
            return false;
        }
        $tickets = get_posts([
            'post_type'     => 'gps_ticket',
            'post_status'   => 'publish',
            'numberposts'   => 20,
            'fields'        => 'ids',
            'no_found_rows' => true,
            'meta_query'    => [['key' => '_gps_event_id', 'value' => (int) $event_id, 'type' => 'NUMERIC']],
        ]);
        if (!$tickets || !class_exists(__NAMESPACE__ . '\\Tickets')) {
            return false;
        }
        foreach ((array) $tickets as $tid) {
            if (!Tickets::sale_ended($tid)) {
                return false;
            }
        }
        return true;
    }

    /* ============================================================
     * Programs (editions of the same course)
     * ============================================================ */

    public static function register_taxonomy() {
        register_taxonomy(self::TAXONOMY, 'gps_event', [
            'labels' => [
                'name'          => __('Course programs', 'gps-courses'),
                'singular_name' => __('Course program', 'gps-courses'),
                'search_items'  => __('Search programs', 'gps-courses'),
                'all_items'     => __('All programs', 'gps-courses'),
                'edit_item'     => __('Edit program', 'gps-courses'),
                'update_item'   => __('Update program', 'gps-courses'),
                'add_new_item'  => __('Add new program', 'gps-courses'),
                'new_item_name' => __('New program name', 'gps-courses'),
                'not_found'     => __('No programs found.', 'gps-courses'),
                'back_to_items' => __('Back to programs', 'gps-courses'),
            ],
            'description'        => __('Groups the editions (dates) of the same course.', 'gps-courses'),
            'public'             => false,
            'publicly_queryable' => false,
            'hierarchical'       => false,
            'show_ui'            => true,  // edit-tags.php screen for renaming / merging
            'show_in_menu'       => false, // picked in the course editor's "Interest list" tab
            'show_in_nav_menus'  => false,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => false,
            'show_admin_column'  => true,
            'show_in_rest'       => false,
            'meta_box_cb'        => false,
            'query_var'          => false,
            'rewrite'            => false,
        ]);
    }

    /** The course's program term, or null. */
    public static function program($event_id) {
        if (!(int) $event_id) {
            return null; // get_the_terms(0) would read the global post
        }
        $terms = get_the_terms((int) $event_id, self::TAXONOMY);
        return is_array($terms) && $terms ? $terms[0] : null;
    }

    /** Published course IDs in a program. */
    public static function program_event_ids($term_id) {
        $term_id = (int) $term_id;
        if (!$term_id) {
            return [];
        }
        $ids = get_posts([
            'post_type'     => 'gps_event',
            'post_status'   => 'publish',
            'numberposts'   => 100,
            'fields'        => 'ids',
            'no_found_rows' => true,
            'tax_query'     => [['taxonomy' => self::TAXONOMY, 'field' => 'term_id', 'terms' => $term_id]],
        ]);
        return array_values(array_unique(array_map('intval', (array) $ids)));
    }

    /**
     * Every published edition in the course's program (itself included when
     * published), any stage, soonest first.
     *
     * @return array[] id, title, url, start, end, when, status
     */
    public static function editions($event_id, $today = null) {
        $term = self::program($event_id);
        if (!$term) {
            return [];
        }
        $out = [];
        foreach (self::program_event_ids($term->term_id) as $id) {
            $out[] = self::edition_row($id, $today);
        }
        return self::sort_editions($out);
    }

    /**
     * Upcoming editions (open / sold_out / soon) in the same program, the
     * course itself excluded, soonest first.
     *
     * @return array[] id, title, url, start, end, when, status
     */
    public static function other_editions($event_id, $today = null) {
        $event_id = (int) $event_id;
        $term = self::program($event_id);
        if (!$term) {
            return [];
        }
        $out = [];
        foreach (self::program_event_ids($term->term_id) as $id) {
            if ($id === $event_id) {
                continue;
            }
            $row = self::edition_row($id, $today);
            if (self::is_upcoming($row['status'])) {
                $out[] = $row;
            }
        }
        return (array) apply_filters('gpsc_other_editions', self::sort_editions($out), $event_id);
    }

    /** First open edition in the program other than this one (for hand-offs), or null. */
    public static function next_open_edition($event_id, $today = null) {
        foreach (self::other_editions($event_id, $today) as $row) {
            if ('open' === $row['status']) {
                return $row;
            }
        }
        return null;
    }

    protected static function edition_row($id, $today = null) {
        list($start, $end) = self::dates($id);
        return [
            'id'     => (int) $id,
            'title'  => html_entity_decode(wp_strip_all_tags(get_the_title($id)), ENT_QUOTES, 'UTF-8'),
            'url'    => (string) get_permalink($id),
            'start'  => $start,
            'end'    => $end,
            'when'   => html_entity_decode(self::format_range($start, $end), ENT_QUOTES, 'UTF-8'),
            'status' => self::get($id, $today),
        ];
    }

    protected static function sort_editions(array $rows) {
        usort($rows, function ($a, $b) {
            // Undated editions last
            $sa = '' === $a['start'] ? '9999-99-99' : $a['start'];
            $sb = '' === $b['start'] ? '9999-99-99' : $b['start'];
            return strcmp($sa, $sb) ?: ($a['id'] - $b['id']);
        });
        return $rows;
    }

    /** Course editor: program picked in the "Interest list" tab. */
    public static function save_program($post_id) {
        if (!isset($_POST['gps_program_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gps_program_nonce'])), 'gps_program_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $term_id = isset($_POST['gps_course_program']) ? absint($_POST['gps_course_program']) : 0;
        $new     = isset($_POST['gps_course_program_new']) ? trim(sanitize_text_field(wp_unslash($_POST['gps_course_program_new']))) : '';

        if ('' !== $new && current_user_can('manage_categories')) {
            $exists = term_exists($new, self::TAXONOMY);
            if ($exists) {
                $term_id = (int) (is_array($exists) ? $exists['term_id'] : $exists);
            } else {
                $created = wp_insert_term($new, self::TAXONOMY);
                if (!is_wp_error($created)) {
                    $term_id = (int) $created['term_id'];
                }
            }
        }

        if ($term_id && !(get_term($term_id, self::TAXONOMY) instanceof \WP_Term)) {
            $term_id = 0;
        }
        wp_set_object_terms($post_id, $term_id ? [$term_id] : [], self::TAXONOMY, false);
    }

    public static function bump_catalog() {
        self::reset();
        if (class_exists(__NAMESPACE__ . '\\Course_Catalog')) {
            Course_Catalog::bump();
        }
    }

    /** A course joined or left a program: refresh the catalog and let others react. */
    public static function on_set_terms($object_id, $terms, $tt_ids, $taxonomy, $append = false, $old_tt_ids = []) {
        if (self::TAXONOMY !== $taxonomy) {
            return;
        }
        $new = array_map('intval', (array) $tt_ids);
        $old = array_map('intval', (array) $old_tt_ids);
        sort($new);
        sort($old);
        if ($new === $old) {
            return;
        }
        self::bump_catalog();
        do_action('gpsc_course_program_changed', (int) $object_id);
    }

    /* ============================================================
     * Front end
     * ============================================================ */

    public static function register_assets() {
        if (wp_style_is('gps-course-stage', 'registered')) {
            return;
        }
        $css = GPSC_PATH . 'assets/css/gps-course-stage.css';
        $js  = GPSC_PATH . 'assets/js/gps-interest.js';
        wp_register_style('gps-course-stage', GPSC_URL . 'assets/css/gps-course-stage.css', [], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        wp_register_script('gps-interest', GPSC_URL . 'assets/js/gps-interest.js', [], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, true);
        wp_localize_script('gps-interest', 'gpsInterest', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n'    => [
                'sending' => __('Sending…', 'gps-courses'),
                'email'   => __('Please enter a valid email address.', 'gps-courses'),
                'error'   => __('Something went wrong. Please try again.', 'gps-courses'),
            ],
        ]);
    }

    /**
     * "Other dates for this course": date range, status chip and link per
     * upcoming edition. '' when there are none.
     *
     * @param array $args title, heading_tag, editions (precomputed rows), class
     */
    public static function render_other_dates($event_id, array $args = []) {
        $args = wp_parse_args($args, [
            'title'       => __('Other dates for this course', 'gps-courses'),
            'heading_tag' => 'h3',
            'editions'    => null,
            'class'       => '',
        ]);
        $editions = is_array($args['editions']) ? $args['editions'] : self::other_editions($event_id);
        if (!$editions) {
            return '';
        }
        $tag = in_array($args['heading_tag'], ['h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div'], true) ? $args['heading_tag'] : 'h3';
        static $n = 0;
        $uid = 'gps-other-dates-' . (int) $event_id . '-' . (++$n);

        $label = '' !== (string) $args['title']
            ? 'aria-labelledby="' . esc_attr($uid) . '"'
            : 'aria-label="' . esc_attr__('Other dates for this course', 'gps-courses') . '"';
        $html = '<section class="gps-other-dates ' . esc_attr($args['class']) . '" ' . $label . '>';
        if ('' !== (string) $args['title']) {
            $html .= '<' . $tag . ' class="gps-other-dates__title" id="' . esc_attr($uid) . '">' . esc_html($args['title']) . '</' . $tag . '>';
        }
        $html .= '<ul class="gps-other-dates__list">';
        foreach ($editions as $row) {
            $status = sanitize_html_class($row['status']);
            $when   = '' !== $row['when'] ? $row['when'] : __('Date to be announced', 'gps-courses');
            $html  .= '<li class="gps-other-dates__item">'
                    . '<a class="gps-other-dates__link" href="' . esc_url($row['url']) . '">'
                    . '<span class="gps-other-dates__when">' . esc_html($when) . '</span>'
                    . '<span class="gps-chip gps-chip--' . esc_attr($status) . '">' . esc_html(self::label($row['status'])) . '</span>'
                    . '<span class="gps-other-dates__go" aria-hidden="true">&rarr;</span>'
                    . '</a></li>';
        }
        $html .= '</ul></section>';
        return $html;
    }
}
