<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * About page and faculty profiles, served by the plugin in the course page
 * design:
 *
 *  - the About page (slug "about-us") → templates/page-about.php;
 *  - each published Speaker (gps_speaker) gets a profile at /team/<slug>/
 *    → templates/single-gps_speaker.php (photo = featured image, bio = the
 *    main text, plus the "Profile page" box: headline, tagline, key facts,
 *    "show in The Team", and the old Elementor profile page that now
 *    redirects to it).
 *
 * Reversible: GPS Courses › Settings › Course page › "About & faculty pages"
 * = Elementor (default) | GPS template. While it is Elementor, speaker
 * profiles send visitors to the About page and the old profile pages stay as
 * they are. Editors can preview with ?gps_about_template=1 / =0 (never cached).
 */
class About_Page {

    const QUERY_ARG    = 'gps_about_template';
    const OPT_TEMPLATE = 'gps_about_template';     // elementor | gps
    const OPT_PAGE     = 'gps_about_page_id';      // 0 = the page with slug "about-us"
    const OPT_FACILITY = 'gps_about_facility_img'; // attachment ID (facility photo)
    const OPT_WIDE     = 'gps_about_wide_img';     // attachment ID (wide photo under the programs)
    const OPT_REWRITE  = 'gps_speaker_rewrite';    // rewrite rules flushed for /team/

    const REWRITE_VER  = '1';
    const SLUG         = 'team';

    const META_HEADLINE = '_gps_speaker_headline'; // gold line above the name
    const META_TAGLINE  = '_gps_speaker_tagline';  // line under the name
    const META_FACTS    = '_gps_speaker_facts';    // "Label | value" per line
    const META_TEAM     = '_gps_speaker_team';     // '1': shown in The Team
    const META_LEGACY   = '_gps_speaker_legacy_page'; // old profile page ID (redirects here)

    /** @var bool|null */
    protected static $use = null;

    public static function init() {
        add_action('init', [__CLASS__, 'register_meta'], 20);
        add_action('wp_loaded', [__CLASS__, 'maybe_flush_rewrite']);
        add_filter('template_include', [__CLASS__, 'template'], 99);
        add_action('template_redirect', [__CLASS__, 'no_cache_preview'], 1);
        add_action('template_redirect', [__CLASS__, 'redirects'], 4);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 110);
        add_action('wp_enqueue_scripts', [__CLASS__, 'skip_elementor_post_css'], 999);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_action('wp_head', [__CLASS__, 'person_json_ld'], 30);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('gpsc_course_page_settings_rows', [__CLASS__, 'settings_rows']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_gps_speaker', [__CLASS__, 'save_meta_box'], 20);
        // Yoast: profiles in the sitemap only while they are served; the old pages never
        add_filter('wpseo_sitemap_exclude_post_type', [__CLASS__, 'sitemap_exclude_post_type'], 10, 2);
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', [__CLASS__, 'sitemap_exclude_legacy']);
        foreach ([self::OPT_TEMPLATE, self::OPT_FACILITY, self::OPT_WIDE, 'gps_contact_email', 'gps_seminar_location'] as $opt) {
            add_action('update_option_' . $opt, [__CLASS__, 'purge']);
            add_action('add_option_' . $opt, [__CLASS__, 'purge']);
        }
        // Course and seminar pages link to the profiles: their links change with the setting
        add_action('update_option_' . self::OPT_TEMPLATE, [__CLASS__, 'purge_linking_pages']);
        add_action('add_option_' . self::OPT_TEMPLATE, [__CLASS__, 'purge_linking_pages']);
        add_action('save_post_gps_speaker', [__CLASS__, 'purge'], 30);
    }

    /* ============================================================
     * Routing
     * ============================================================ */

    public static function page_id() {
        $id = (int) get_option(self::OPT_PAGE, 0);
        if ($id && 'page' === get_post_type($id)) {
            return $id;
        }
        $page = get_page_by_path('about-us', OBJECT, 'page');
        return ($page && 'publish' === $page->post_status) ? (int) $page->ID : 0;
    }

    public static function site_mode() {
        return 'gps' === get_option(self::OPT_TEMPLATE, 'elementor') ? 'gps' : 'elementor';
    }

    /** true / false from ?gps_about_template for editors, null otherwise. */
    public static function preview_flag() {
        if (!isset($_GET[self::QUERY_ARG]) || !current_user_can('edit_pages')) {
            return null;
        }
        return '0' !== sanitize_key(wp_unslash($_GET[self::QUERY_ARG]));
    }

    /** Are the GPS About / profile templates on for this request? */
    public static function enabled() {
        $preview = self::preview_flag();
        return null !== $preview ? $preview : 'gps' === self::site_mode();
    }

    protected static function is_elementor_preview() {
        return isset($_GET['elementor-preview'])
            || (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->preview) && \Elementor\Plugin::$instance->preview->is_preview_mode());
    }

    /** 'about', 'speaker' or '' for the current request. */
    public static function context() {
        if (null !== self::$use) {
            return self::$use;
        }
        if (!did_action('wp')) {
            return ''; // main query not ready: decide later
        }
        if (is_embed() || self::is_elementor_preview()) {
            return self::$use = '';
        }
        $id = self::page_id();
        if ($id && is_page($id) && !post_password_required($id) && self::enabled()) {
            return self::$use = (string) apply_filters('gpsc_about_page_template', 'about', $id);
        }
        if (is_singular('gps_speaker') && self::enabled()) {
            return self::$use = 'speaker';
        }
        return self::$use = '';
    }

    public static function template($template) {
        $ctx  = self::context();
        $file = 'about' === $ctx ? 'page-about.php' : ('speaker' === $ctx ? 'single-gps_speaker.php' : '');
        if ('' === $file) {
            return $template;
        }
        $ours = GPSC_PATH . 'templates/' . $file;
        return file_exists($ours) ? $ours : $template;
    }

    public static function no_cache_preview() {
        if (null === self::preview_flag()) {
            return;
        }
        $id = self::page_id();
        if (!($id && is_page($id)) && !is_singular('gps_speaker') && !self::legacy_target(get_queried_object_id())) {
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

    /**
     * Old Elementor profile page → the speaker's profile (templates on).
     * Profile while the templates are off → the About page (temporary).
     */
    public static function redirects() {
        if (is_admin() || is_preview() || self::is_elementor_preview()) {
            return;
        }
        if (is_page() && ($sid = self::legacy_target(get_queried_object_id())) && self::enabled()) {
            $url = get_permalink($sid);
            if (null !== self::preview_flag()) {
                $url = add_query_arg(self::QUERY_ARG, '1', $url);
            }
            wp_safe_redirect($url, null !== self::preview_flag() ? 302 : 301);
            exit;
        }
        if (is_singular('gps_speaker') && !self::enabled()) {
            $about = self::page_id();
            wp_safe_redirect($about ? self::link(get_permalink($about)) : home_url('/'), 302);
            exit;
        }
    }

    /** Internal link that keeps an editor's ?gps_about_template preview. */
    public static function link($url) {
        $flag = self::preview_flag();
        return null === $flag ? (string) $url : add_query_arg(self::QUERY_ARG, $flag ? '1' : '0', $url);
    }

    /** Published speaker whose old profile page is $page_id, or 0. */
    public static function legacy_target($page_id) {
        $page_id = (int) $page_id;
        if (!$page_id) {
            return 0;
        }
        $map = self::legacy_map();
        return isset($map[$page_id]) ? $map[$page_id] : 0;
    }

    /** [old page ID => speaker ID] for published speakers. */
    public static function legacy_map() {
        static $map = null;
        if (null !== $map) {
            return $map;
        }
        $map = [];
        $ids = get_posts([
            'post_type'      => 'gps_speaker',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [['key' => self::META_LEGACY, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']],
        ]);
        $about = self::page_id();
        foreach ($ids as $sid) {
            $pid = (int) get_post_meta($sid, self::META_LEGACY, true);
            if ($pid && $pid !== $about && 'page' === get_post_type($pid)) {
                $map[$pid] = (int) $sid;
            }
        }
        return $map;
    }

    /** Profile link for a speaker: the GPS profile while it is served, else the manual link. */
    public static function profile_url($sid) {
        $sid = (int) $sid;
        if ('gps' === self::site_mode() && 'publish' === get_post_status($sid)) {
            return (string) get_permalink($sid);
        }
        return (string) get_post_meta($sid, Course_Page::META_SPEAKER_URL, true);
    }

    public static function maybe_flush_rewrite() {
        if (self::REWRITE_VER !== get_option(self::OPT_REWRITE)) {
            flush_rewrite_rules(false);
            update_option(self::OPT_REWRITE, self::REWRITE_VER, true);
        }
    }

    public static function body_class($classes) {
        $ctx = self::context();
        if ('' !== $ctx) {
            $classes[] = 'about' === $ctx ? 'gps-about-page' : 'gps-speaker-page';
        }
        return $classes;
    }

    public static function skip_elementor_post_css() {
        if ('about' === self::context()) {
            wp_dequeue_style('elementor-post-' . self::page_id());
        }
    }

    public static function enqueue_assets() {
        if ('' === self::context()) {
            return;
        }
        $deps = [];
        if (wp_style_is('gps-forms', 'registered') || wp_style_is('gps-forms', 'enqueued')) {
            $deps[] = 'gps-forms';
        }
        $course  = GPSC_PATH . 'assets/css/gps-course.css';
        $catalog = GPSC_PATH . 'assets/css/gps-catalog.css';
        $css     = GPSC_PATH . 'assets/css/gps-about.css';
        wp_enqueue_style('gps-course', GPSC_URL . 'assets/css/gps-course.css', $deps, file_exists($course) ? (string) filemtime($course) : GPSC_VERSION);
        if ('speaker' === self::context()) {
            // Upcoming course cards: the same card as /courses/
            wp_enqueue_style('gps-catalog', GPSC_URL . 'assets/css/gps-catalog.css', $deps, file_exists($catalog) ? (string) filemtime($catalog) : GPSC_VERSION);
        }
        wp_enqueue_style('gps-about', GPSC_URL . 'assets/css/gps-about.css', ['gps-course'], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
    }

    public static function purge() {
        if (!is_callable(['\\FlyingPress\\Purge', 'purge_url'])) {
            return;
        }
        $urls = [];
        if ($id = self::page_id()) {
            $urls[] = get_permalink($id);
        }
        $speakers = get_posts(['post_type' => 'gps_speaker', 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true]);
        foreach ($speakers as $sid) {
            $urls[] = get_permalink($sid);
        }
        foreach (array_keys(self::legacy_map()) as $pid) {
            $urls[] = get_permalink($pid);
        }
        foreach (array_unique(array_filter($urls)) as $url) {
            call_user_func(['\\FlyingPress\\Purge', 'purge_url'], $url);
        }
    }

    public static function purge_linking_pages() {
        Course_Page::purge_all_courses();
        Seminar_Page::purge_pages();
    }

    /* ============================================================
     * Settings (rows in Settings › Course page)
     * ============================================================ */

    public static function register_settings() {
        register_setting('gps_course_page_settings', self::OPT_TEMPLATE, [
            'type' => 'string', 'default' => 'elementor',
            'sanitize_callback' => function ($v) { return 'gps' === $v ? 'gps' : 'elementor'; },
        ]);
        foreach ([self::OPT_FACILITY, self::OPT_WIDE] as $opt) {
            register_setting('gps_course_page_settings', $opt, [
                'type' => 'integer', 'default' => 0,
                'sanitize_callback' => function ($v) { $v = absint($v); return ($v && wp_attachment_is_image($v)) ? $v : 0; },
            ]);
        }
    }

    public static function settings_rows() {
        $mode = self::site_mode();
        $id   = self::page_id();
        ?>
        <tr>
            <th scope="row"><label for="gps-about-template"><?php esc_html_e('About & faculty pages', 'gps-courses'); ?></label></th>
            <td>
                <select id="gps-about-template" name="<?php echo esc_attr(self::OPT_TEMPLATE); ?>">
                    <option value="elementor" <?php selected($mode, 'elementor'); ?>><?php esc_html_e('Elementor (current pages)', 'gps-courses'); ?></option>
                    <option value="gps" <?php selected($mode, 'gps'); ?>><?php esc_html_e('GPS templates (course page design)', 'gps-courses'); ?></option>
                </select>
                <p class="description">
                    <?php esc_html_e('GPS templates: About page plus one profile per published Speaker at /team/<name>/. Old profile pages chosen in each speaker redirect to it.', 'gps-courses'); ?>
                    <?php if ($id) : ?>
                        <a href="<?php echo esc_url(add_query_arg(self::QUERY_ARG, '1', get_permalink($id))); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview the GPS template', 'gps-courses'); ?></a>
                    <?php endif; ?>
                </p>
                <p>
                    <label><?php esc_html_e('Facility photo (attachment ID)', 'gps-courses'); ?>
                        <input type="number" min="0" name="<?php echo esc_attr(self::OPT_FACILITY); ?>" value="<?php echo (int) get_option(self::OPT_FACILITY, 0); ?>" class="small-text"></label>
                    <label style="margin-left:12px"><?php esc_html_e('Wide photo under the programs (attachment ID)', 'gps-courses'); ?>
                        <input type="number" min="0" name="<?php echo esc_attr(self::OPT_WIDE); ?>" value="<?php echo (int) get_option(self::OPT_WIDE, 0); ?>" class="small-text"></label>
                </p>
                <p class="description"><?php esc_html_e('Hero photo = the About page featured image. Email and address come from "Contact email" above and Settings › Seminar page › Location. The Team = speakers with "Show in The Team" ticked, by their Order.', 'gps-courses'); ?></p>
            </td>
        </tr>
        <?php
    }

    /* ============================================================
     * Speaker profile fields
     * ============================================================ */

    public static function register_meta() {
        $auth = function () { return current_user_can('edit_posts'); };
        foreach ([self::META_HEADLINE, self::META_TAGLINE, self::META_FACTS, self::META_TEAM] as $key) {
            register_post_meta('gps_speaker', $key, ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth]);
        }
        register_post_meta('gps_speaker', self::META_LEGACY, [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth,
            'sanitize_callback' => [__CLASS__, 'sanitize_legacy'],
        ]);
        // The REST API only reads / writes meta for types that support custom fields
        add_post_type_support('gps_speaker', 'custom-fields');
    }

    /** Old profile page: a page that is not the About page, else 0. */
    public static function sanitize_legacy($value) {
        $value = absint($value);
        return ($value && 'page' === get_post_type($value) && $value !== self::page_id()) ? $value : 0;
    }

    public static function add_meta_box() {
        add_meta_box('gps_speaker_profile', __('Profile page', 'gps-courses'), [__CLASS__, 'render_meta_box'], 'gps_speaker', 'normal', 'high');
    }

    public static function render_meta_box($post) {
        wp_nonce_field('gps_speaker_profile', 'gps_speaker_profile_nonce');
        $legacy = (int) get_post_meta($post->ID, self::META_LEGACY, true);
        $pages  = get_pages(['post_status' => 'publish,draft,private', 'sort_column' => 'post_title']);
        ?>
        <p><label for="gps-sp-headline"><strong><?php esc_html_e('Headline (gold line above the name)', 'gps-courses'); ?></strong></label>
            <input type="text" id="gps-sp-headline" name="gps_speaker_headline" class="widefat" value="<?php echo esc_attr(get_post_meta($post->ID, self::META_HEADLINE, true)); ?>" placeholder="Diplomate of the American Board of Prosthodontics"></p>
        <p><label for="gps-sp-tagline"><strong><?php esc_html_e('Tagline (under the name)', 'gps-courses'); ?></strong></label>
            <input type="text" id="gps-sp-tagline" name="gps_speaker_tagline" class="widefat" value="<?php echo esc_attr(get_post_meta($post->ID, self::META_TAGLINE, true)); ?>" placeholder="Advanced Implant, Cosmetic and Reconstructive Dentistry"></p>
        <p><label for="gps-sp-facts"><strong><?php esc_html_e('Key facts (one per line: Label | value)', 'gps-courses'); ?></strong></label>
            <textarea id="gps-sp-facts" name="gps_speaker_facts" class="widefat" rows="3" placeholder="Role | Founder and director, GPS"><?php echo esc_textarea(get_post_meta($post->ID, self::META_FACTS, true)); ?></textarea></p>
        <p><label><input type="checkbox" name="gps_speaker_team" value="1" <?php checked('1', (string) get_post_meta($post->ID, self::META_TEAM, true)); ?>> <?php esc_html_e('Show in The Team (About page). Order: the Order field.', 'gps-courses'); ?></label></p>
        <p><label for="gps-sp-legacy"><strong><?php esc_html_e('Old profile page (redirects here)', 'gps-courses'); ?></strong></label>
            <select id="gps-sp-legacy" name="gps_speaker_legacy_page" class="widefat">
                <option value="0"><?php esc_html_e('— None —', 'gps-courses'); ?></option>
                <?php foreach ($pages as $p) : ?>
                    <option value="<?php echo (int) $p->ID; ?>" <?php selected($legacy, (int) $p->ID); ?>><?php echo esc_html($p->post_title . ' (/' . $p->post_name . '/)'); ?></option>
                <?php endforeach; ?>
            </select></p>
        <p class="description"><?php esc_html_e('Photo = featured image (portrait, plain light background). Bio = the main text; headings and lists in it show as they are.', 'gps-courses'); ?></p>
        <?php
    }

    public static function save_meta_box($post_id) {
        if (!isset($_POST['gps_speaker_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gps_speaker_profile_nonce'])), 'gps_speaker_profile')) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
            return;
        }
        $text = [
            self::META_HEADLINE => isset($_POST['gps_speaker_headline']) ? sanitize_text_field(wp_unslash($_POST['gps_speaker_headline'])) : '',
            self::META_TAGLINE  => isset($_POST['gps_speaker_tagline']) ? sanitize_text_field(wp_unslash($_POST['gps_speaker_tagline'])) : '',
            self::META_FACTS    => isset($_POST['gps_speaker_facts']) ? sanitize_textarea_field(wp_unslash($_POST['gps_speaker_facts'])) : '',
            self::META_TEAM     => empty($_POST['gps_speaker_team']) ? '' : '1',
        ];
        foreach ($text as $key => $val) {
            if ('' === trim($val)) {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, $val);
            }
        }
        $legacy = self::sanitize_legacy(isset($_POST['gps_speaker_legacy_page']) ? wp_unslash($_POST['gps_speaker_legacy_page']) : 0);
        if ($legacy) {
            update_post_meta($post_id, self::META_LEGACY, $legacy);
        } else {
            delete_post_meta($post_id, self::META_LEGACY);
        }
    }

    /* ============================================================
     * Sitemap
     * ============================================================ */

    public static function sitemap_exclude_post_type($excluded, $post_type) {
        return ('gps_speaker' === $post_type && 'gps' !== self::site_mode()) ? true : $excluded;
    }

    public static function sitemap_exclude_legacy($ids) {
        $ids = is_array($ids) ? $ids : [];
        if ('gps' === self::site_mode()) {
            $ids = array_merge($ids, array_keys(self::legacy_map()));
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /* ============================================================
     * Data
     * ============================================================ */

    /** Profile data for one published speaker, or null. */
    public static function speaker($sid) {
        $base = Course_Page::speaker($sid);
        if (!$base) {
            return null;
        }
        $facts = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) get_post_meta($sid, self::META_FACTS, true)) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            $label = isset($parts[1]) ? $parts[0] : '';
            $value = isset($parts[1]) ? $parts[1] : $parts[0];
            if ('' !== $value) {
                $facts[] = ['label' => $label, 'value' => $value];
            }
        }
        $headline = trim((string) get_post_meta($sid, self::META_HEADLINE, true));
        return array_merge($base, [
            'headline' => $headline,
            'tagline'  => trim((string) get_post_meta($sid, self::META_TAGLINE, true)),
            'facts'    => $facts,
            'content'  => (string) get_post_field('post_content', $sid),
            'url'      => (string) get_permalink($sid),
            'summary'  => '' !== $headline ? $headline : ($base['title'] ?: $base['cred']),
        ]);
    }

    /** Speakers in The Team, by Order then name (without $exclude). */
    public static function team($exclude = 0, $limit = 12) {
        $ids = get_posts([
            'post_type'      => 'gps_speaker',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'meta_query'     => [['key' => self::META_TEAM, 'value' => '1']],
            'post__not_in'   => $exclude ? [(int) $exclude] : [],
        ]);
        return array_values(array_filter(array_map([__CLASS__, 'speaker'], $ids)));
    }

    /** Upcoming courses (catalog cards) that list this speaker. */
    public static function courses_for($sid, $limit = 6) {
        $out = [];
        foreach (Course_Catalog::courses('upcoming', 60) as $c) {
            $ids = array_map(function ($s) { return (int) $s['id']; }, Course_Page::speakers($c['id']));
            if (in_array((int) $sid, $ids, true)) {
                $out[] = $c;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    /** Location lines from Settings › Seminar page › Location. */
    protected static function location() {
        $loc = class_exists(__NAMESPACE__ . '\\Seminar_Page')
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_option(Seminar_Page::OPT_LOCATION, Seminar_Page::default_location())))))
            : ['GPS Dental Training', '6320 Sugarloaf Parkway', 'Duluth, GA 30097'];
        $street   = isset($loc[1]) ? $loc[1] : '';
        $cityline = isset($loc[2]) ? $loc[2] : '';
        return [
            'venue'    => isset($loc[0]) ? $loc[0] : '',
            'street'   => $street,
            'cityline' => $cityline,
            'city'     => trim(preg_replace('/\s+\d{5}(-\d{4})?$/', '', $cityline)),
            'map_q'    => implode(', ', array_filter([$street, $cityline])),
        ];
    }

    /** Monthly seminar product URL, or ''. */
    public static function seminar_url() {
        $ids = get_posts([
            'post_type'   => 'product',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields'      => 'ids',
            'meta_query'  => [['key' => '_gps_seminar_id', 'compare' => 'EXISTS']],
        ]);
        return $ids ? (string) get_permalink($ids[0]) : '';
    }

    public static function data() {
        $page = self::page_id();
        return apply_filters('gpsc_about_page_data', [
            'eyebrow'   => __('About GPS', 'gps-courses'),
            'title'     => __('Georgia Prosthodontics Study Club', 'gps-courses'),
            'gold'      => __('& Training Center', 'gps-courses'),
            'tagline'   => __('Advanced Education in Surgical, Restorative and Digital Dentistry', 'gps-courses'),
            'hero'      => $page ? (int) get_post_thumbnail_id($page) : 0,
            'mission'   => __('To provide advanced dental education that enhances the knowledge, skills, and abilities of dental professionals.', 'gps-courses'),
            'vision'    => __('To lead advanced continued dental education through collaborative partnerships and individualized mentorships. We bring innovation, research, and outreach to fill in the voids in training and knowledge between formal education and the rapidly changing fields of surgical, restorative and digital dentistry.', 'gps-courses'),
            'facility'  => [
                'image' => (int) get_option(self::OPT_FACILITY, 0),
                'title' => __('State-of-the-Art facility with cutting edge Dental Technology', 'gps-courses'),
                'text'  => [
                    __('Our trainees take courses within the clinical setting of the Dental Implant Center of Georgia Prosthodontics and the in-house laboratory, Prostho Dental Lab. Both are equipped with cutting-edge technology for advanced diagnostic, surgical, and restorative procedures in implant, reconstructive, and digital dentistry.', 'gps-courses'),
                    __('It is the right setting to observe and learn the latest techniques step by step, alongside a high-standard team.', 'gps-courses'),
                ],
                'quote' => __('Unlock the potential of collaboration, partnership and mentorship.', 'gps-courses'),
            ],
            'programs'  => [
                'title' => __('Training Programs', 'gps-courses'),
                'lead'  => __('Hands-on training in the latest digital technologies: diagnostic procedures, restorative and surgical treatment planning, and the digital design and manufacture of prostheses.', 'gps-courses'),
                'items' => [
                    ['icon' => 'check', 'title' => __('Step-by-step workflows', 'gps-courses'), 'text' => __('Every procedure taught with its scientific rationale, from diagnosis to delivery.', 'gps-courses')],
                    ['icon' => 'share', 'title' => __('A fully digital workflow', 'gps-courses'), 'text' => __('See and interact with the full digital chain, from scan and design to manufacture.', 'gps-courses')],
                    ['icon' => 'people', 'title' => __('Expert instructors', 'gps-courses'), 'text' => __('Clinicians and master technicians committed to the highest quality of training.', 'gps-courses')],
                ],
                'image' => (int) get_option(self::OPT_WIDE, 0),
            ],
            'team'      => self::team(),
            'seminar'   => [
                'url'   => self::seminar_url(),
                'title' => __('Be part of the Club', 'gps-courses'),
                'text'  => __('A 10-session cycle dedicated to literature review, case discussions, and treatment planning seminars.', 'gps-courses'),
                'chips' => [__('10 sessions', 'gps-courses'), __('20 CE credits', 'gps-courses')],
            ],
            'location'  => array_merge(self::location(), [
                'title' => __('Train in Duluth, GA', 'gps-courses'),
                'text'  => __('Easy to reach from I-85, with ample parking. Hotels and restaurants are close by, and the Gas South Arena convention center and the arts center are within walking distance.', 'gps-courses'),
            ]),
            'email'     => Course_Page::contact_email(),
            'courses'   => get_post_type_archive_link('gps_event') ?: home_url('/courses/'),
        ]);
    }

    /** Person schema on a profile (Yoast covers the page itself). */
    public static function person_json_ld() {
        if ('speaker' !== self::context()) {
            return;
        }
        $s = self::speaker(get_queried_object_id());
        if (!$s) {
            return;
        }
        $plain  = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes($s['content']))));
        $person = array_filter([
            '@context'    => 'https://schema.org',
            '@type'       => 'Person',
            'name'        => $s['name'],
            'honorificSuffix' => $s['cred'],
            'jobTitle'    => $s['headline'] ?: $s['title'],
            'description' => wp_trim_words($plain, 60, '…'),
            'image'       => $s['photo'] ? (string) wp_get_attachment_image_url($s['photo'], 'large') : '',
            'url'         => $s['url'],
            'sameAs'      => $s['linkedin'] ? [$s['linkedin']] : [],
            'affiliation' => ['@type' => 'Organization', 'name' => get_bloginfo('name'), 'url' => home_url('/')],
        ]);
        echo '<script type="application/ld+json">' . wp_json_encode($person, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
    }
}
