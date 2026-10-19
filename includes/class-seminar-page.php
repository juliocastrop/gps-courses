<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Monthly seminars product page served by the plugin
 * (templates/single-seminar-product.php) instead of the Elementor single
 * product template. Only WooCommerce products linked to a gps_seminar
 * (product meta _gps_seminar_id) use it; every other product is untouched.
 *
 * Rollout is reversible:
 *   - GPS Courses → Settings → Seminar page: Elementor (default) | GPS template
 *   - editors can preview either version with ?gps_seminar_template=1 / =0
 *     (never cached)
 *
 * Content comes from the seminar data: the product (title, price, image,
 * short description), the seminar cycles and their sessions (including
 * single-session tickets), the moderator's Speaker profile and the settings
 * tab (topics, location, FAQs). The page reuses the course page design
 * (gps-course.css / gps-course.js) plus gps-seminar.css.
 */
class Seminar_Page {

    const QUERY_ARG = 'gps_seminar_template';

    const OPT_TEMPLATE  = 'gps_seminar_template';   // elementor | gps
    const OPT_MODERATOR = 'gps_seminar_moderator';  // gps_speaker ID
    const OPT_TOPICS    = 'gps_seminar_topics';     // "Title | description" per line
    const OPT_LOCATION  = 'gps_seminar_location';   // venue / street / city lines
    const OPT_FAQS      = 'gps_seminar_faqs';       // [{q, a}]

    /** CE credits per program session (as Seminar_Attendance awards them). */
    const CE_PER_SESSION = 2;
    const PROGRAM_SESSIONS = 10;
    const FEW_SPOTS = 5;

    /** @var array<int, bool> */
    protected static $use = [];

    /** @var array<int, array> */
    protected static $cache = [];

    public static function init() {
        add_filter('template_include', [__CLASS__, 'template'], 99);
        add_action('template_redirect', [__CLASS__, 'no_cache_preview'], 1);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 110);
        add_action('wp_enqueue_scripts', [__CLASS__, 'skip_elementor_post_css'], 999);
        add_action('wp_head', [__CLASS__, 'print_json_ld'], 30);
        add_filter('wpseo_metadesc', [__CLASS__, 'meta_description'], 20);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        foreach ([self::OPT_TEMPLATE, self::OPT_MODERATOR, self::OPT_TOPICS, self::OPT_LOCATION, self::OPT_FAQS] as $opt) {
            add_action('update_option_' . $opt, [__CLASS__, 'purge_pages']);
            add_action('add_option_' . $opt, [__CLASS__, 'purge_pages']);
        }
    }

    /* ============================================================
     * Routing
     * ============================================================ */

    public static function site_mode() {
        return 'gps' === get_option(self::OPT_TEMPLATE, 'elementor') ? 'gps' : 'elementor';
    }

    /** The gps_seminar a product sells, or 0. */
    public static function seminar_for_product($product_id) {
        $seminar_id = (int) get_post_meta((int) $product_id, '_gps_seminar_id', true);
        return ($seminar_id && 'gps_seminar' === get_post_type($seminar_id)) ? $seminar_id : 0;
    }

    /** ?gps_seminar_template=1 / =0 for people who can edit the product; null otherwise. */
    public static function preview_flag($post_id = 0) {
        if (!isset($_GET[self::QUERY_ARG])) {
            return null;
        }
        $post_id = $post_id ?: (int) get_queried_object_id();
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            return null;
        }
        return '0' !== sanitize_key(wp_unslash($_GET[self::QUERY_ARG]));
    }

    public static function uses_template($post_id = 0) {
        $post_id = (int) ($post_id ?: get_queried_object_id());
        if (!$post_id || 'product' !== get_post_type($post_id)) {
            return false;
        }
        if (isset(self::$use[$post_id])) {
            return self::$use[$post_id];
        }
        if (!function_exists('wc_get_product') || !self::seminar_for_product($post_id)) {
            return self::$use[$post_id] = false;
        }
        if (isset($_GET['elementor-preview']) || (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->preview) && \Elementor\Plugin::$instance->preview->is_preview_mode())) {
            return self::$use[$post_id] = false;
        }
        $preview = self::preview_flag($post_id);
        $use     = null !== $preview ? $preview : 'gps' === self::site_mode();
        if ($use && post_password_required($post_id)) {
            $use = false;
        }
        return self::$use[$post_id] = (bool) apply_filters('gpsc_seminar_page_template', $use, $post_id);
    }

    public static function is_active() {
        return is_singular('product') && !is_embed() && self::uses_template();
    }

    public static function template($template) {
        if (!self::is_active()) {
            return $template;
        }
        $ours = GPSC_PATH . 'templates/single-seminar-product.php';
        return file_exists($ours) ? $ours : $template;
    }

    public static function no_cache_preview() {
        if (!is_singular('product') || null === self::preview_flag()) {
            return;
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow', true);
        }
    }

    public static function body_class($classes) {
        if (self::is_active()) {
            $classes[] = 'gps-seminar-page';
            $d = self::data((int) get_queried_object_id());
            if ($d['can_enroll']) {
                $classes[] = 'gps-cp-has-bar';
            }
        }
        return $classes;
    }

    public static function enqueue_assets() {
        if (!self::is_active()) {
            return;
        }
        if (class_exists(__NAMESPACE__ . '\\Course_Stage')) {
            Course_Stage::register_assets(); // .gps-sr and the shared form styles
        }
        $deps = [];
        foreach (['gps-forms', 'gps-course-stage'] as $handle) {
            if (wp_style_is($handle, 'registered') || wp_style_is($handle, 'enqueued')) {
                $deps[] = $handle;
            }
        }
        $course_css = GPSC_PATH . 'assets/css/gps-course.css';
        $css        = GPSC_PATH . 'assets/css/gps-seminar.css';
        $js         = GPSC_PATH . 'assets/js/gps-course.js';
        wp_enqueue_style('gps-course', GPSC_URL . 'assets/css/gps-course.css', $deps, file_exists($course_css) ? (string) filemtime($course_css) : GPSC_VERSION);
        wp_enqueue_style('gps-seminar', GPSC_URL . 'assets/css/gps-seminar.css', ['gps-course'], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        wp_enqueue_script('gps-course', GPSC_URL . 'assets/js/gps-course.js', [], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        wp_localize_script('gps-course', 'gpsCourse', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n'    => [
                'adding'  => __('Adding…', 'gps-courses'),
                'joining' => __('Joining…', 'gps-courses'),
                'joined'  => __('Joined!', 'gps-courses'),
                'join'    => __('Join the waitlist', 'gps-courses'),
                'error'   => __('Something went wrong. Please try again.', 'gps-courses'),
                'copied'  => __('Link copied', 'gps-courses'),
                'more'    => __('Read more', 'gps-courses'),
                'less'    => __('Show less', 'gps-courses'),
                'close'   => __('Close', 'gps-courses'),
                'prev'    => __('Previous image', 'gps-courses'),
                'next'    => __('Next image', 'gps-courses'),
            ],
        ]);
        // Fresh-nonce helper for the waitlist form on cached pages
        if (wp_script_is('gps-interest', 'registered')) {
            wp_enqueue_script('gps-interest');
        }
    }

    /** The product's own Elementor document isn't rendered: skip its post CSS. */
    public static function skip_elementor_post_css() {
        if (self::is_active()) {
            wp_dequeue_style('elementor-post-' . (int) get_queried_object_id());
        }
    }

    /** A setting change: clear the cached copies of the seminar product pages. */
    public static function purge_pages() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        global $wpdb;
        $ids  = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gps_seminar_id' AND meta_value <> ''");
        $urls = [];
        foreach ((array) $ids as $id) {
            if ('product' === get_post_type((int) $id) && ($url = get_permalink((int) $id))) {
                $urls[] = $url;
            }
        }
        if (is_callable(['\\FlyingPress\\Purge', 'purge_url'])) {
            foreach ($urls as $url) {
                call_user_func(['\\FlyingPress\\Purge', 'purge_url'], $url);
            }
        }
        do_action('gpsc_purge_course_urls', $urls, 0);
    }

    /* ============================================================
     * Settings
     * ============================================================ */

    public static function default_topics() {
        return implode("\n", [
            __('Treatment planning seminars | Work through complex restorative cases step by step, from diagnosis to sequencing.', 'gps-courses'),
            __('Literature reviews | Read and discuss the evidence behind materials, techniques and protocols.', 'gps-courses'),
            __('Case presentations | Bring your own cases and get feedback from the group and the moderator.', 'gps-courses'),
            __('Surgical seminars | Surgical planning and execution for implant and restorative treatment.', 'gps-courses'),
        ]);
    }

    public static function default_location() {
        return implode("\n", ['GPS Dental Training', '6320 Sugarloaf Parkway', 'Duluth, GA 30097']);
    }

    public static function default_faqs() {
        $saved = get_option(self::OPT_FAQS, null);
        if (is_array($saved)) {
            return Course_Page::clean_faqs($saved);
        }
        return Course_Page::clean_faqs([
            ['q' => __('When does my membership start and end?', 'gps-courses'), 'a' => __('Your membership starts at the session right after you enroll and ends after your 10th session. You can enroll at any time during the year.', 'gps-courses')],
            ['q' => __('What if I miss a session?', 'gps-courses'), 'a' => __('One make-up session is allowed per calendar year.', 'gps-courses')],
            ['q' => __('How are CE credits awarded?', 'gps-courses'), 'a' => __('Attendance is mandatory to obtain the 20 CE credits. Credits are prorated at 2 CE per session attended.', 'gps-courses')],
            ['q' => __('When do I get my certificate?', 'gps-courses'), 'a' => __('CE certificates are issued per semester, at the end of June and at the end of December.', 'gps-courses')],
            ['q' => __('Can I get a refund or transfer my seat?', 'gps-courses'), 'a' => __('Tuition is not refundable or transferable.', 'gps-courses')],
            ['q' => __('Who can I contact with questions?', 'gps-courses'), 'a' => __('Write to us at {contact_email}.', 'gps-courses')],
        ]);
    }

    public static function register_settings() {
        $group = 'gps_seminar_page_settings';
        register_setting($group, self::OPT_TEMPLATE, [
            'type' => 'string', 'default' => 'elementor',
            'sanitize_callback' => function ($v) { return 'gps' === $v ? 'gps' : 'elementor'; },
        ]);
        register_setting($group, self::OPT_MODERATOR, [
            'type' => 'integer', 'default' => 0,
            'sanitize_callback' => function ($v) {
                $v = absint($v);
                return ($v && 'gps_speaker' === get_post_type($v)) ? $v : 0;
            },
        ]);
        register_setting($group, self::OPT_TOPICS, [
            'type' => 'string', 'default' => self::default_topics(),
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting($group, self::OPT_LOCATION, [
            'type' => 'string', 'default' => self::default_location(),
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting($group, self::OPT_FAQS, [
            'type' => 'array',
            'sanitize_callback' => ['\\GPSC\\Course_Page', 'sanitize_faqs_input'],
        ]);
    }

    /** Settings → Seminar page tab (rendered by Settings::render_settings_page). */
    public static function render_settings_tab() {
        $mode      = self::site_mode();
        $moderator = self::moderator_id();
        $speakers  = get_posts([
            'post_type'   => 'gps_speaker',
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);
        $products = [];
        global $wpdb;
        foreach ((array) $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gps_seminar_id' AND meta_value <> ''") as $pid) {
            if ('product' === get_post_type((int) $pid) && 'publish' === get_post_status((int) $pid)) {
                $products[] = (int) $pid;
            }
        }
        ?>
        <form method="post" action="options.php" class="gps-cp-settings">
            <?php settings_fields('gps_seminar_page_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="gps-seminar-template"><?php esc_html_e('Seminar page template', 'gps-courses'); ?></label></th>
                    <td>
                        <select id="gps-seminar-template" name="<?php echo esc_attr(self::OPT_TEMPLATE); ?>">
                            <option value="elementor" <?php selected($mode, 'elementor'); ?>><?php esc_html_e('Elementor (current product page)', 'gps-courses'); ?></option>
                            <option value="gps" <?php selected($mode, 'gps'); ?>><?php esc_html_e('GPS template (built from the seminar data)', 'gps-courses'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Applies only to WooCommerce products linked to a monthly seminar. Editors can preview the GPS template by adding ?gps_seminar_template=1 to the product address (=0 shows the Elementor page).', 'gps-courses'); ?></p>
                        <?php foreach ($products as $pid) : ?>
                            <p><a href="<?php echo esc_url(add_query_arg(self::QUERY_ARG, '1', get_permalink($pid))); ?>" target="_blank" rel="noopener"><?php echo esc_html(sprintf(__('Preview: %s', 'gps-courses'), get_the_title($pid))); ?></a></p>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gps-seminar-moderator"><?php esc_html_e('Moderator', 'gps-courses'); ?></label></th>
                    <td>
                        <select id="gps-seminar-moderator" name="<?php echo esc_attr(self::OPT_MODERATOR); ?>">
                            <option value="0"><?php esc_html_e('— None —', 'gps-courses'); ?></option>
                            <?php foreach ($speakers as $sp) : ?>
                                <option value="<?php echo (int) $sp->ID; ?>" <?php selected($moderator, (int) $sp->ID); ?>><?php echo esc_html(get_the_title($sp)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Photo, title and bio come from the Speaker profile.', 'gps-courses'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gps-seminar-topics"><?php esc_html_e('Seminar topics', 'gps-courses'); ?></label></th>
                    <td>
                        <textarea id="gps-seminar-topics" name="<?php echo esc_attr(self::OPT_TOPICS); ?>" rows="5" class="large-text"><?php echo esc_textarea((string) get_option(self::OPT_TOPICS, self::default_topics())); ?></textarea>
                        <p class="description"><?php esc_html_e('One topic per line: Title | short description (the description is optional).', 'gps-courses'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gps-seminar-location"><?php esc_html_e('Location', 'gps-courses'); ?></label></th>
                    <td>
                        <textarea id="gps-seminar-location" name="<?php echo esc_attr(self::OPT_LOCATION); ?>" rows="3" class="large-text"><?php echo esc_textarea((string) get_option(self::OPT_LOCATION, self::default_location())); ?></textarea>
                        <p class="description"><?php esc_html_e('Three lines: venue name, street address, city / state / ZIP. The map searches the street address and city.', 'gps-courses'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Program policies (FAQ)', 'gps-courses'); ?></th>
                    <td>
                        <?php Course_Page::render_faq_editor(self::OPT_FAQS . '[q][]', self::OPT_FAQS . '[a][]', self::default_faqs(), 'gps-seminar-faqs'); ?>
                        <p class="description"><?php esc_html_e('Placeholders: {contact_email}, {tuition}, {ce_credits}.', 'gps-courses'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /* ============================================================
     * Data
     * ============================================================ */

    /** The moderator's Speaker ID: the setting, else (never saved) a speaker named "Carlos Castro". */
    public static function moderator_id() {
        $saved = get_option(self::OPT_MODERATOR, false);
        if (false !== $saved) {
            // Saved: that speaker, or none on purpose
            $id = (int) $saved;
            return ($id && 'gps_speaker' === get_post_type($id) && 'publish' === get_post_status($id)) ? $id : 0;
        }
        $found = get_posts([
            'post_type'   => 'gps_speaker',
            'post_status' => 'publish',
            'numberposts' => 1,
            's'           => 'Carlos Castro',
            'fields'      => 'ids',
        ]);
        return $found ? (int) $found[0] : 0;
    }

    /** "18:00:00" → "6 PM", "18:30:00" → "6:30 PM". */
    protected static function clock($time) {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', (string) $time, $m)) {
            return '';
        }
        $h   = (int) $m[1] % 24;
        $mer = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12 ?: 12;
        return '00' === $m[2] ? $h12 . ' ' . $mer : $h12 . ':' . $m[2] . ' ' . $mer;
    }

    /** "6–8 PM", "9 AM–12 PM". */
    public static function time_range($start, $end) {
        $a = self::clock($start);
        $b = $end ? self::clock($end) : '';
        if ('' === $a || '' === $b) {
            return $a . $b;
        }
        $a_mer = substr($a, -2);
        if ($a_mer === substr($b, -2)) {
            $a = trim(substr($a, 0, -2));
        }
        return $a . '–' . $b;
    }

    protected static function session_rows($seminar_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, session_number, session_date, session_time_start, session_time_end, topic, description,
                    individual_sales_enabled, individual_price, individual_product_id
             FROM {$wpdb->prefix}gps_seminar_sessions
             WHERE seminar_id = %d
             ORDER BY session_date ASC, session_time_start ASC",
            $seminar_id
        )) ?: [];
    }

    /** A single-session ticket that can be bought now, or null. */
    protected static function single_ticket($row) {
        if (empty($row->individual_sales_enabled) || empty($row->individual_product_id)) {
            return null;
        }
        $product = wc_get_product((int) $row->individual_product_id);
        if (!$product || 'publish' !== $product->get_status() || !$product->is_purchasable() || !$product->is_in_stock()) {
            return null;
        }
        return ['product' => (int) $product->get_id(), 'price' => (float) $product->get_price()];
    }

    /**
     * Everything the template shows, from the product and its seminar.
     *
     * Cycles: the product's seminar plus any later seminar that already has
     * sessions, so the next cycle shows up as soon as its dates are set.
     */
    public static function data($product_id) {
        $product_id = (int) $product_id;
        if (isset(self::$cache[$product_id])) {
            return self::$cache[$product_id];
        }
        $product    = wc_get_product($product_id);
        $seminar_id = self::seminar_for_product($product_id);
        $today      = current_time('Y-m-d');

        // Title: "GPS Monthly Seminars - 10-session cycle" → white + gold parts
        $title = trim(html_entity_decode(get_the_title($product_id), ENT_QUOTES, 'UTF-8'));
        $parts = preg_split('/\s+[-–—]\s+/u', $title, 2);
        $white = $parts[0];
        $gold  = isset($parts[1]) ? $parts[1] : '';

        // Cycles: the product's own, then later ones that still have sessions to come
        $raw  = [$seminar_id => self::session_rows($seminar_id)];
        $tail = $raw[$seminar_id] ? end($raw[$seminar_id])->session_date : '';
        $after = max($today, (string) $tail);
        foreach (Seminars::get_all_seminars() as $s) {
            $sid = (int) $s->ID;
            if ($sid === $seminar_id) {
                continue;
            }
            $rows     = self::session_rows($sid);
            $upcoming = array_filter($rows, function ($r) use ($after) { return $r->session_date >= $after; });
            if ($upcoming) {
                $raw[$sid] = $rows;
            }
        }
        $order = array_keys($raw);
        $first = array_shift($order);
        usort($order, function ($a, $b) use ($raw) { return strcmp($raw[$a][0]->session_date, $raw[$b][0]->session_date); });
        array_unshift($order, $first);

        $cycles    = [];
        $next      = null;
        $times     = [];
        $durations = [];
        foreach ($order as $sid) {
            $sessions = [];
            $done     = 0;
            foreach ($raw[$sid] as $r) {
                $ts     = strtotime($r->session_date);
                $past   = $r->session_date < $today;
                $status = $past ? 'done' : ($next ? 'upcoming' : 'next');
                $done  += $past ? 1 : 0;
                $range  = self::time_range($r->session_time_start, $r->session_time_end);
                if ('' !== $range) {
                    $times[$range] = isset($times[$range]) ? $times[$range] + 1 : 1;
                }
                if ($r->session_time_start && $r->session_time_end) {
                    $mins = (int) round((strtotime('2000-01-01 ' . $r->session_time_end) - strtotime('2000-01-01 ' . $r->session_time_start)) / 60);
                    if ($mins > 0) {
                        $durations[$mins] = isset($durations[$mins]) ? $durations[$mins] + 1 : 1;
                    }
                }
                $row = [
                    'id'     => (int) $r->id,
                    'number' => (int) $r->session_number,
                    'date'   => $r->session_date,
                    'mon'    => $ts ? date_i18n('M', $ts) : '',
                    'day'    => $ts ? date_i18n('j', $ts) : '',
                    'long'   => $ts ? date_i18n('D, M j', $ts) : '',
                    'full'   => $ts ? date_i18n(get_option('date_format'), $ts) : '',
                    'time'   => $range,
                    'start'  => (string) $r->session_time_start,
                    'end'    => (string) $r->session_time_end,
                    'topic'  => trim(html_entity_decode((string) $r->topic, ENT_QUOTES, 'UTF-8')),
                    'status' => $status,
                    'single' => $past ? null : self::single_ticket($r),
                ];
                if ('next' === $status) {
                    $next = $row;
                }
                $sessions[] = $row;
            }
            $cycles[] = [
                'id'       => $sid,
                'title'    => trim(html_entity_decode(get_the_title($sid), ENT_QUOTES, 'UTF-8')),
                'sessions' => $sessions,
                'done'     => $done,
                'total'    => count($sessions),
            ];
        }
        $current = $cycles[0];
        $later   = array_slice($cycles, 1);

        arsort($times);
        arsort($durations);
        $common_time = $times ? (string) key($times) : '';
        $all_same    = count($times) <= 1;
        $minutes     = $durations ? (int) key($durations) : 120;
        $hours       = $minutes % 60 ? round($minutes / 60, 1) : (int) ($minutes / 60);

        // Price, availability
        $price      = $product ? (float) $product->get_price() : 0.0;
        if ($price <= 0) {
            $price = (float) (get_post_meta($seminar_id, '_gps_seminar_tuition', true) ?: 750);
        }
        $spots      = (int) Seminars::get_available_spots($seminar_id);
        $full       = $spots <= 0;
        $purchasable = $product && 'publish' === $product->get_status() && $product->is_purchasable() && $product->is_in_stock();
        $can_enroll = $purchasable && !$full;

        // Membership size: the program's, not how many dates are entered so far
        $total_sessions = (int) apply_filters('gpsc_seminar_program_sessions', self::PROGRAM_SESSIONS, $seminar_id);
        $ce_total       = $total_sessions * self::CE_PER_SESSION;

        // Location
        $loc_lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_option(self::OPT_LOCATION, self::default_location())))));
        $venue     = isset($loc_lines[0]) ? $loc_lines[0] : '';
        $street    = isset($loc_lines[1]) ? $loc_lines[1] : '';
        $cityline  = isset($loc_lines[2]) ? $loc_lines[2] : '';
        $city      = trim(preg_replace('/\s+\d{5}(-\d{4})?$/', '', $cityline));
        $map_q     = implode(', ', array_filter([$street, $cityline])) ?: $venue;

        // Moderator
        $moderator = null;
        $mid       = self::moderator_id();
        if ($mid) {
            $moderator = Course_Page::speaker($mid);
        }

        // Topics
        $topics = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) get_option(self::OPT_TOPICS, self::default_topics())) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $bits     = array_map('trim', explode('|', $line, 2));
            $topics[] = ['title' => $bits[0], 'text' => isset($bits[1]) ? $bits[1] : ''];
        }

        // FAQs with placeholders
        $contact = Course_Page::contact_email();
        $faqs    = [];
        foreach (self::default_faqs() as $f) {
            $a = strtr($f['a'], [
                '{contact_email}' => '<a href="mailto:' . esc_attr($contact) . '">' . esc_html($contact) . '</a>',
                '{tuition}'       => esc_html(html_entity_decode(wp_strip_all_tags(Course_Page::price($price)), ENT_QUOTES, 'UTF-8')),
                '{ce_credits}'    => (string) $ce_total,
            ]);
            $faqs[] = ['q' => $f['q'], 'a' => $a];
        }

        // Tagline: the product's short description when it is short, else a sentence from the data
        $tagline = $product ? trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes((string) $product->get_short_description())))) : '';
        // A short description that reads as a paragraph (the old policy text) is not a tagline
        if ((function_exists('mb_strlen') ? mb_strlen($tagline, 'UTF-8') : strlen($tagline)) > 200) {
            $tagline = '';
        }
        if ('' === $tagline) {
            $tagline = $moderator
                /* translators: %s: moderator name */
                ? sprintf(__('Literature reviews, case discussions and treatment-planning seminars with %s — one evening a month, in a small group of peers.', 'gps-courses'), $moderator['name'])
                : __('Literature reviews, case discussions and treatment-planning seminars — one evening a month, in a small group of peers.', 'gps-courses');
        }

        $d = [
            'id'          => $product_id,
            'seminar_id'  => $seminar_id,
            'url'         => get_permalink($product_id),
            'title'       => $title,
            'white'       => $white,
            'gold'        => $gold,
            'tagline'     => $tagline,
            'image'       => $product ? (int) $product->get_image_id() : 0,
            'cycle'       => $current,
            'later'       => $later,
            'next'        => $next,
            'time'        => $common_time,
            'time_all'    => $all_same,
            'hours'       => $hours,
            'price'       => $price,
            'spots'       => $spots,
            'few'         => $purchasable && !$full && $spots <= self::FEW_SPOTS,
            'full'        => $full,
            'purchasable' => $purchasable,
            'can_enroll'  => $can_enroll,
            'sessions'    => $total_sessions,
            'ce_total'    => $ce_total,
            'ce_each'     => self::CE_PER_SESSION,
            'venue'       => $venue,
            'street'      => $street,
            'cityline'    => $cityline,
            'city'        => $city,
            'map_q'       => $map_q,
            'moderator'   => $moderator,
            'topics'      => $topics,
            'faqs'        => $faqs,
            'contact'     => $contact,
            'cart'        => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'),
        ];
        return self::$cache[$product_id] = apply_filters('gpsc_seminar_page_data', $d, $product_id);
    }

    /* ============================================================
     * SEO
     * ============================================================ */

    public static function meta_description($desc) {
        if ('' !== trim((string) $desc) || !self::is_active()) {
            return $desc;
        }
        $d = self::data((int) get_queried_object_id());
        return wp_trim_words($d['tagline'], 30, '…');
    }

    /** Course (with its Offer) + FAQPage structured data. */
    public static function json_ld(array $d) {
        $text = function ($s) {
            return trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags((string) $s), ENT_QUOTES, 'UTF-8')));
        };
        $site = $text(get_bloginfo('name'));
        $tz   = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $iso  = function ($ymd, $time) use ($tz) {
            try {
                return (new \DateTime($ymd . ' ' . ($time ? substr($time, 0, 5) : '00:00'), $tz))->format('c');
            } catch (\Exception $e) {
                return $ymd;
            }
        };
        $course = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Course',
            '@id'         => $d['url'] . '#course',
            'name'        => $text($d['title']),
            'description' => $text($d['tagline']),
            'url'         => $d['url'],
            'provider'    => ['@type' => 'Organization', 'name' => $site, 'url' => home_url('/')],
            /* translators: %d: number of CE credits */
            'educationalCredentialAwarded' => sprintf(__('%d CE credits', 'gps-courses'), $d['ce_total']),
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => $d['price'],
                'priceCurrency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                'availability'  => 'https://schema.org/' . ($d['can_enroll'] ? 'InStock' : 'SoldOut'),
                'url'           => $d['url'],
                'category'      => 'Paid',
            ],
        ];
        if ($d['image'] && ($img = wp_get_attachment_image_url($d['image'], 'full'))) {
            $course['image'] = $img;
        }
        if ($d['topics']) {
            $course['teaches'] = array_map(function ($t) use ($text) { return $text($t['title']); }, $d['topics']);
        }
        $instances = [];
        foreach (array_merge([$d['cycle']], $d['later']) as $cycle) {
            foreach ($cycle['sessions'] as $s) {
                if ('done' === $s['status']) {
                    continue;
                }
                $inst = [
                    '@type'      => 'CourseInstance',
                    'name'       => $text($s['topic']),
                    'courseMode' => 'Onsite',
                    'startDate'  => $iso($s['date'], $s['start']),
                    'endDate'    => $iso($s['date'], $s['end'] ?: $s['start']),
                    'location'   => [
                        '@type'   => 'Place',
                        'name'    => $text($d['venue'] ?: $site),
                        'address' => $text(implode(', ', array_filter([$d['street'], $d['cityline']]))),
                    ],
                ];
                if ($d['moderator']) {
                    $inst['instructor'] = ['@type' => 'Person', 'name' => $text($d['moderator']['full'])];
                }
                $instances[] = $inst;
            }
        }
        if ($instances) {
            $course['hasCourseInstance'] = $instances;
        }
        $graph = [apply_filters('gpsc_seminar_json_ld', $course, $d)];
        if ($d['faqs']) {
            $graph[] = [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                '@id'        => $d['url'] . '#faq',
                'mainEntity' => array_map(function ($f) use ($text) {
                    return [
                        '@type'          => 'Question',
                        'name'           => $text($f['q']),
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(wp_kses($f['a'], ['a' => ['href' => true], 'strong' => [], 'em' => [], 'br' => []]))],
                    ];
                }, $d['faqs']),
            ];
        }
        $out = '';
        foreach ($graph as $node) {
            $out .= '<script type="application/ld+json">' . wp_json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
        }
        return $out;
    }

    public static function print_json_ld() {
        if (!self::is_active()) {
            return;
        }
        echo self::json_ld(self::data((int) get_queried_object_id())); // JSON encoded with JSON_HEX_TAG
    }
}
