<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Course editions: one course (the "master" gps_event) with several dated
 * editions, each a gps_event child post (post_parent = master).
 *
 * The master keeps the shared content (title, Elementor page, image, SEO,
 * taxonomies) plus a MIRROR of its current edition's dates, venue and CE
 * credits, so every meta_query / raw SQL / widget that reads the master keeps
 * working. Ticket types, agendas, tickets, attendance and certificates point
 * to the edition (child) ID; Editions::resolve() maps a master to the edition
 * on sale.
 *
 * A course without children is untouched: resolve() returns the ID it was
 * given and none of the filters below change a query. Which posts are
 * editions is kept in one autoloaded option (child => parent), so the checks
 * cost no database query.
 */
class Editions {

    /** Edition fields copied to the master (the master's public dates, venue, CE). */
    const FIELDS = [
        '_gps_start_date',
        '_gps_end_date',
        '_gps_start_time',
        '_gps_end_time',
        '_gps_venue',
        '_gps_address',
        '_gps_city',
        '_gps_state',
        '_gps_zip',
        '_gps_country',
        '_gps_ce_credits',
        '_gps_registration_deadline',
    ];

    const META_CURRENT = '_gps_current_edition';
    const META_LABEL   = '_gps_edition_label';

    /** child ID => master ID, every gps_event child not trashed (autoloaded). */
    const OPTION = 'gpsc_edition_map';

    /** WP_Query var: true = list editions too (admin pickers), never hidden. */
    const QUERY_VAR = 'gpsc_editions';

    /** Statuses a child can have and still count as an edition. */
    const GONE = ['trash', 'auto-draft', 'inherit'];

    /** @var array|null child => parent, per request */
    protected static $map = null;

    /** @var array|null parent => true */
    protected static $masters = null;

    /** @var array masters being synced (no re-entry) */
    protected static $syncing = [];

    public static function init() {
        // Keep the child => parent map current (cheap: rebuilt only when it changed)
        add_action('save_post_gps_event', [__CLASS__, 'refresh'], 1, 2);
        add_action('transition_post_status', [__CLASS__, 'on_transition'], 10, 3);
        add_action('trashed_post', [__CLASS__, 'on_trash_change']);
        add_action('untrashed_post', [__CLASS__, 'on_trash_change']);
        add_action('deleted_post', [__CLASS__, 'on_deleted'], 10, 2);

        // Mirror the current edition onto the master
        add_action('save_post_gps_event', [__CLASS__, 'on_save_child'], 50, 2);
        add_action('save_post_gps_event', [__CLASS__, 'on_save_master'], 99, 2);
        // Before Course_Stage::daily_refresh (10) purges pages and bumps the catalog
        add_action(Course_Stage::CRON_DAILY, [__CLASS__, 'sync_all'], 5);

        // Editions are not public pages: the course URL shows the current one
        add_action('pre_get_posts', [__CLASS__, 'hide_children'], 5);
        add_filter('posts_where', [__CLASS__, 'search_where'], 10, 2);
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', [__CLASS__, 'sitemap_exclude']);
        add_filter('wp_sitemaps_posts_query_args', [__CLASS__, 'core_sitemap_args'], 10, 2);
        add_filter('wpseo_robots', [__CLASS__, 'yoast_robots']);
        add_filter('wp_robots', [__CLASS__, 'core_robots']);
        add_action('template_redirect', [__CLASS__, 'redirect_child'], 2);
    }

    /* ============================================================
     * Map (no query per row)
     * ============================================================ */

    /** @return array<int,int> child ID => master ID */
    public static function map() {
        if (null === self::$map) {
            $stored = get_option(self::OPTION, null);
            if (is_array($stored)) {
                self::$map = array_map('intval', $stored);
            } else {
                self::rebuild_map(); // first run: one query, then autoloaded
            }
        }
        return self::$map;
    }

    /** Read the map from the posts table (one query) and store it when it changed. */
    public static function rebuild_map() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT ID, post_parent FROM {$wpdb->posts}
             WHERE post_type = 'gps_event' AND post_parent > 0
               AND post_status NOT IN ('trash', 'auto-draft', 'inherit')"
        );
        $map = [];
        foreach ((array) $rows as $row) {
            $map[(int) $row->ID] = (int) $row->post_parent;
        }
        ksort($map);
        $stored = get_option(self::OPTION, null);
        if (!is_array($stored) || array_map('intval', $stored) !== $map) {
            update_option(self::OPTION, $map, true);
        }
        self::$map     = $map;
        self::$masters = null;
        return $map;
    }

    /** Forget the per-request map (tests, imports). */
    public static function flush() {
        self::$map     = null;
        self::$masters = null;
    }

    /** Is there any edition at all? (false on a site without editions: every filter is a no-op) */
    public static function has_children() {
        return (bool) self::map();
    }

    /** @return int[] every edition (child) ID */
    public static function child_ids() {
        return array_keys(self::map());
    }

    /** The edition's course (master) ID, or 0 when $id is not an edition. */
    public static function of($id) {
        $map = self::map();
        $id  = (int) $id;
        return isset($map[$id]) ? $map[$id] : 0;
    }

    /** Does this course have editions? */
    public static function is_master($id) {
        if (null === self::$masters) {
            self::$masters = array_fill_keys(array_values(self::map()), true);
        }
        return isset(self::$masters[(int) $id]);
    }

    /** Re-check the map when a course's parent or status may have changed. */
    public static function refresh($post_id, $post = null) {
        $post = $post instanceof \WP_Post ? $post : get_post($post_id);
        if (!$post || 'gps_event' !== $post->post_type) {
            return;
        }
        $id       = (int) $post->ID;
        $parent   = (int) $post->post_parent;
        $expected = ($parent > 0 && !in_array($post->post_status, self::GONE, true)) ? $parent : 0;
        if (self::of($id) !== $expected) {
            self::rebuild_map();
        }
    }

    /* ============================================================
     * Editions of a course
     * ============================================================ */

    /**
     * The course's editions, earliest start first (undated last).
     *
     * @param int          $master
     * @param string|array $statuses
     * @return \WP_Post[]
     */
    public static function all($master, $statuses = ['publish']) {
        $master = (int) $master;
        if (!$master || !self::is_master($master)) {
            return [];
        }
        $posts = get_posts([
            'post_type'        => 'gps_event',
            'post_parent'      => $master,
            'post_status'      => $statuses,
            'numberposts'      => -1,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'no_found_rows'    => true,
            self::QUERY_VAR    => true,
        ]);
        $rows = [];
        foreach ((array) $posts as $p) {
            list($start) = self::dates($p->ID);
            $rows[] = [$p, $start];
        }
        usort($rows, function ($a, $b) {
            if ($a[1] === $b[1]) {
                return $a[0]->ID - $b[0]->ID;
            }
            if ('' === $a[1]) {
                return 1;
            }
            if ('' === $b[1]) {
                return -1;
            }
            return strcmp($a[1], $b[1]);
        });
        $out = [];
        foreach ($rows as $r) {
            $out[] = $r[0];
        }
        return $out;
    }

    /**
     * The edition the course shows: the earliest published edition that has
     * not ended, else the latest past one; undated editions last. 0 = none.
     */
    public static function current($master, $today = null) {
        $today    = $today ?: current_time('Y-m-d');
        $upcoming = 0;
        $past     = 0;
        $past_end = '';
        $undated  = 0;
        foreach (self::all($master) as $p) {
            list($start, $end) = self::dates($p->ID);
            if ('' === $start) {
                if (!$undated) {
                    $undated = (int) $p->ID;
                }
                continue;
            }
            if ($end >= $today) {
                if (!$upcoming) {
                    $upcoming = (int) $p->ID; // all() is sorted by start
                }
            } elseif ($end >= $past_end) {
                $past     = (int) $p->ID;
                $past_end = $end;
            }
        }
        return $upcoming ?: ($past ?: $undated);
    }

    /**
     * The edition to sell / show for this course: the master's current edition,
     * or the ID itself (any course without editions, or an edition).
     * Uses the map and the meta cache only.
     */
    public static function resolve($id) {
        $id = (int) $id;
        if (!$id || !self::is_master($id)) {
            return $id;
        }
        $current = (int) get_post_meta($id, self::META_CURRENT, true);
        if ($current && self::of($current) === $id && 'publish' === get_post_status($current)) {
            return $current;
        }
        return $id;
    }

    /** "2026": the edition's label, else the year it starts. */
    public static function edition_label($id) {
        $label = trim((string) get_post_meta((int) $id, self::META_LABEL, true));
        if ('' === $label) {
            list($start) = self::dates($id);
            $label = '' !== $start ? substr($start, 0, 4) : '#' . (int) $id;
        }
        return $label;
    }

    /**
     * "Course — Edition 2026" for an edition, the plain (raw) title otherwise.
     *
     * @param int|\WP_Post $post
     */
    public static function label($post) {
        $post = $post instanceof \WP_Post ? $post : get_post((int) $post);
        if (!$post) {
            return '';
        }
        return (string) $post->post_title . self::title_suffix($post->ID);
    }

    /** " — Edition 2026" for an edition, '' otherwise (to append to a displayed title). */
    public static function title_suffix($id) {
        if (!self::of($id)) {
            return '';
        }
        /* translators: %s: edition label (year) */
        return sprintf(__(' — Edition %s', 'gps-courses'), self::edition_label($id));
    }

    /** [start, end] Y-m-d (end falls back to start). */
    protected static function dates($id) {
        if (class_exists(__NAMESPACE__ . '\\Course_Stage')) {
            return Course_Stage::dates($id);
        }
        $start = substr((string) get_post_meta($id, '_gps_start_date', true), 0, 10);
        $end   = substr((string) get_post_meta($id, '_gps_end_date', true), 0, 10) ?: $start;
        return [$start, $end];
    }

    /* ============================================================
     * Mirror
     * ============================================================ */

    /**
     * Copy the current edition's fields to the master (writes only what
     * changed) and remember which edition that is. Nothing happens for a
     * course without editions.
     *
     * @return bool something changed
     */
    public static function sync($master) {
        $master = (int) $master;
        if (!$master || !self::is_master($master) || isset(self::$syncing[$master])) {
            return false;
        }
        self::$syncing[$master] = true;
        $changed = false;

        $current = self::current($master);
        if ($current) {
            foreach (self::FIELDS as $key) {
                $value = get_post_meta($current, $key, true);
                $old   = get_post_meta($master, $key, true);
                if ((string) $value === (string) $old) {
                    continue;
                }
                if ('' === (string) $value) {
                    delete_post_meta($master, $key);
                } else {
                    update_post_meta($master, $key, $value);
                }
                $changed = true;
            }
        }
        if ((int) get_post_meta($master, self::META_CURRENT, true) !== $current) {
            if ($current) {
                update_post_meta($master, self::META_CURRENT, $current);
            } else {
                delete_post_meta($master, self::META_CURRENT);
            }
            $changed = true;
        }

        if ($changed) {
            if (class_exists(__NAMESPACE__ . '\\Course_Stage')) {
                Course_Stage::reset();
                Course_Stage::purge_program_pages($master);
            }
            if (class_exists(__NAMESPACE__ . '\\Course_Catalog')) {
                Course_Catalog::bump();
            }
            if (class_exists(__NAMESPACE__ . '\\Posttypes')) {
                Posttypes::clear_calendar_cache($master);
            }
            if (class_exists(__NAMESPACE__ . '\\AI_Search')) {
                AI_Search::touch($master); // its page changed: fresh modified date, Yoast, page cache
            }
            do_action('gpsc_edition_synced', $master, $current);
        }

        unset(self::$syncing[$master]);
        return $changed;
    }

    /** Nightly (and on demand): resync every course with editions. */
    public static function sync_all() {
        self::rebuild_map();
        $done = [];
        foreach (array_unique(array_values(self::map())) as $master) {
            $done[$master] = self::sync($master);
        }
        return $done;
    }

    /** An edition was saved: its course's mirror and interest list follow. */
    public static function on_save_child($post_id, $post = null) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        $master = self::of($post_id);
        if (!$master) {
            return;
        }
        self::sync($master);
        if (class_exists(__NAMESPACE__ . '\\Interest_List')) {
            Interest_List::queue($master); // after the mirror: the course may have just opened
        }
    }

    /** The course itself was saved: put the edition's fields back (prio 99, after every save handler). */
    public static function on_save_master($post_id, $post = null) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (self::is_master($post_id)) {
            self::sync($post_id);
        }
    }

    /** An edition published, drafted, trashed...: the course may show another one. */
    public static function on_transition($new_status, $old_status, $post) {
        if (!$post instanceof \WP_Post || 'gps_event' !== $post->post_type) {
            return;
        }
        $before = self::of($post->ID);
        self::refresh($post->ID, $post);
        if ($new_status === $old_status) {
            // Same status but moved to another course: the old course shows another edition now
            if ($before && $before !== (int) $post->post_parent) {
                self::after_leaving($before);
            }
            return;
        }
        $master = (int) $post->post_parent ?: $before;
        if ($master && self::is_master($master)) {
            self::sync($master);
        } elseif ($before) {
            self::sync($before);
        }
    }

    public static function on_trash_change($post_id) {
        $before = self::of($post_id);
        $post   = get_post($post_id);
        if ($post) {
            self::refresh($post_id, $post);
        }
        $master = $before ?: ($post && 'gps_event' === $post->post_type ? (int) $post->post_parent : 0);
        if ($master) {
            self::sync($master);
        }
    }

    public static function on_deleted($post_id, $post = null) {
        if (self::is_master($post_id)) {
            // WordPress re-parents the editions to 0 with raw SQL (no hooks)
            self::rebuild_map();
            return;
        }
        $master = self::of($post_id);
        if (!$master) {
            return;
        }
        self::after_leaving($master);
    }

    /** An edition left $master (deleted or moved): resync it, or make it a plain course again. */
    protected static function after_leaving($master) {
        self::rebuild_map();
        if (self::is_master($master)) {
            self::sync($master);
        } else {
            // Last edition gone: the course is a plain course again
            delete_post_meta($master, self::META_CURRENT);
            if (class_exists(__NAMESPACE__ . '\\Course_Catalog')) {
                Course_Catalog::bump();
            }
        }
    }

    /* ============================================================
     * Public site: editions are not listed, indexed or reachable
     * ============================================================ */

    /**
     * Front end (and AJAX / REST): a gps_event query that does not ask for a
     * specific post lists courses only (post_parent = 0). Admin Courses list:
     * one row per course.
     */
    public static function hide_children($query) {
        if (!$query instanceof \WP_Query || !self::has_children()) {
            return;
        }
        if ('gps_event' !== $query->get('post_type')) {
            return;
        }
        foreach (['p', 'name', 'gps_event', 'post__in', 'post_parent__in', self::QUERY_VAR] as $var) {
            if (!empty($query->get($var))) {
                return;
            }
        }
        $parent = $query->get('post_parent');
        if ('' !== $parent && null !== $parent) {
            return; // asked for children of a course (or post_parent = 0 already)
        }
        if (is_admin() && !wp_doing_ajax()) {
            global $pagenow;
            if ($query->is_main_query() && 'edit.php' === $pagenow) {
                $query->set('post_parent', 0);
            }
            return;
        }
        if ($query->is_singular()) {
            return;
        }
        $query->set('post_parent', 0);
    }

    /** Explicit "courses only" args for listings that also run in wp-admin (catalog, calendar). */
    public static function top_level_args(array $args) {
        if (self::has_children() && !isset($args['post_parent']) && empty($args['post__in'])) {
            $args['post_parent'] = 0;
        }
        return $args;
    }

    /** Site search: never an edition (search queries are not post_type = gps_event only). */
    public static function search_where($where, $query) {
        if (is_admin() || !$query instanceof \WP_Query || !$query->is_search() || !self::has_children()) {
            return $where;
        }
        global $wpdb;
        $ids = implode(',', array_map('intval', self::child_ids()));
        return $where . " AND {$wpdb->posts}.ID NOT IN ($ids)";
    }

    public static function sitemap_exclude($ids) {
        if (!self::has_children()) {
            return $ids;
        }
        return array_values(array_unique(array_merge(array_map('intval', (array) $ids), self::child_ids())));
    }

    public static function core_sitemap_args($args, $post_type = '') {
        if ('gps_event' !== $post_type || !self::has_children()) {
            return $args;
        }
        $args['post__not_in'] = array_merge(isset($args['post__not_in']) ? (array) $args['post__not_in'] : [], self::child_ids());
        return $args;
    }

    /** The edition being viewed (editor preview), or 0. */
    protected static function viewed_child() {
        if (!self::has_children() || !is_singular('gps_event')) {
            return 0;
        }
        $id = (int) get_queried_object_id();
        return self::of($id) ? $id : 0;
    }

    public static function yoast_robots($robots) {
        return self::viewed_child() ? 'noindex, follow' : $robots;
    }

    public static function core_robots($robots) {
        if (self::viewed_child()) {
            $robots = (array) $robots;
            unset($robots['index']);
            $robots['noindex'] = true;
            $robots['follow']  = true;
        }
        return $robots;
    }

    /**
     * Where an edition's URL goes: its course (301), keeping the query string.
     * '' = stay (not an edition, editor previewing, course not published).
     */
    public static function redirect_target($id) {
        $id     = (int) $id;
        $master = self::of($id);
        if (!$master) {
            return '';
        }
        $previewing = is_preview() || isset($_GET['preview']) || isset($_GET['preview_id']) || isset($_GET['elementor-preview']);
        if ($previewing && current_user_can('edit_post', $id)) {
            return '';
        }
        if ('publish' !== get_post_status($master)) {
            return '';
        }
        $url = (string) get_permalink($master);
        if ('' === $url) {
            return '';
        }
        // Keep tracking params (utm_*, gclid…) but never the ones that select a
        // post: WordPress would load the edition again (endless 301) or 404.
        $args = [];
        foreach ((array) $_GET as $k => $v) {
            if (is_scalar($v) && !in_array($k, ['p', 'page_id', 'name', 'gps_event', 'post_type', 'preview', 'preview_id', 'preview_nonce', 'attachment_id', 'elementor-preview', 'ver'], true)) {
                $args[$k] = rawurlencode(wp_unslash((string) $v));
            }
        }
        return $args ? add_query_arg($args, $url) : $url;
    }

    public static function redirect_child() {
        if (!self::has_children() || !is_singular('gps_event')) {
            return;
        }
        $url = self::redirect_target(get_queried_object_id());
        if ('' !== $url) {
            wp_safe_redirect($url, 301);
            exit;
        }
    }

    /* ============================================================
     * Admin pickers
     * ============================================================ */

    /**
     * Drop courses that have editions (pick an edition instead), except $keep_id.
     *
     * @param \WP_Post[] $posts
     * @return \WP_Post[]
     */
    public static function picker_posts(array $posts, $keep_id = 0) {
        if (!self::has_children()) {
            return $posts;
        }
        $keep_id = (int) $keep_id;
        $out = [];
        foreach ($posts as $p) {
            $pid = is_object($p) ? (int) $p->ID : (int) $p;
            if ($pid === $keep_id || !self::is_master($pid)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * Courses for an admin course picker: [ID => label]. Editions are listed as
     * "Course — Edition 2026"; a course with editions is left out (unless it is
     * $current_id). Without editions the result is exactly the list it always was.
     *
     * Default (no $args): published, draft, scheduled, pending and private
     * courses by title, non-published labelled "Title (Draft)", plus the
     * currently linked course even when trashed (course_select_options()).
     *
     * @param int   $current_id the course currently linked (0 = none)
     * @param array $args {
     *     @type array         $query    get_posts() args replacing the default query
     *     @type callable|null $suffix   function (WP_Post) => string appended to the label
     *     @type string|null   $no_title label of an untitled course (null = "(no title)")
     *     @type bool          $status   append "(Draft)" etc. to non-published courses
     * }
     * @return array<int,string>
     */
    public static function admin_options($current_id = 0, array $args = []) {
        $current_id = (int) $current_id;
        $query = isset($args['query']) ? (array) $args['query'] : [
            'post_type'   => 'gps_event',
            'numberposts' => -1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'post_status' => ['publish', 'draft', 'future', 'pending', 'private'],
            'orderby'     => 'title',
            'order'       => 'ASC',
        ];
        $query[self::QUERY_VAR] = true;
        $suffix   = isset($args['suffix']) && is_callable($args['suffix']) ? $args['suffix'] : null;
        $no_title = array_key_exists('no_title', $args) && null !== $args['no_title'] ? (string) $args['no_title'] : __('(no title)', 'gps-courses');
        $status   = !isset($args['status']) || $args['status'];

        $courses = get_posts($query);

        if ($current_id) {
            $ids = array_map('intval', wp_list_pluck($courses, 'ID'));
            if (!in_array($current_id, $ids, true)) {
                $linked = get_post($current_id);
                if ($linked && 'gps_event' === $linked->post_type) {
                    $courses[] = $linked;
                }
            }
        }

        $courses = self::picker_posts($courses, $current_id);

        $options = [];
        foreach ($courses as $course) {
            $title = '' !== $course->post_title ? self::label($course) : $no_title;
            if ($status && 'publish' !== $course->post_status) {
                $status_obj = get_post_status_object($course->post_status);
                $label      = $status_obj ? $status_obj->label : $course->post_status;
                /* translators: 1: course title, 2: post status label (Draft, Private...) */
                $title = sprintf(__('%1$s (%2$s)', 'gps-courses'), $title, $label);
            }
            if ($suffix) {
                $title .= (string) call_user_func($suffix, $course);
            }
            $options[(int) $course->ID] = $title;
        }

        // Title order: an edition sorts with its year ("Course — Edition 2026" < "... 2027")
        if (self::has_children() && 'title' === (isset($query['orderby']) ? $query['orderby'] : '')) {
            $desc = isset($query['order']) && 'DESC' === strtoupper((string) $query['order']);
            uasort($options, function ($a, $b) use ($desc) {
                $c = strnatcasecmp($a, $b);
                return $desc ? -$c : $c;
            });
        }
        return $options;
    }
}
