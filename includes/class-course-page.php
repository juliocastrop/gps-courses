<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Single course page served by the plugin (templates/single-gps_event.php)
 * instead of each course's Elementor document.
 *
 * Rollout is reversible at three levels:
 *   - site setting "Course page template" (GPS Courses → Settings → Course page):
 *     Elementor (default, nothing changes) | GPS template
 *   - per course, "Page content" tab: Use site default | GPS template | Elementor
 *   - editors can preview either version with ?gps_course_template=1 / =0
 *     (never cached)
 *
 * Everything the page shows comes from course data: the course fields, the
 * "Page content" tab (this class), Course_Stage, tickets, schedules, speakers
 * and sponsors. Site-wide defaults (refund policy, FAQs, sponsors, contact
 * email) live in the settings tab.
 */
class Course_Page {

    const QUERY_ARG = 'gps_course_template';

    /* Per-course meta (Page content tab) */
    const META_TEMPLATE      = '_gps_page_template';   // '' (site default) | gps | elementor
    const META_TAGLINE       = '_gps_tagline';
    const META_FORMAT        = '_gps_format';
    const META_AUDIENCE      = '_gps_audience';
    const META_INCLUDED      = '_gps_included';         // one item per line
    const META_PREREQ        = '_gps_prerequisites';
    const META_SPEAKERS      = '_gps_speaker_ids';      // registered in Posttypes (array)
    const META_GALLERY       = '_gps_gallery_ids';      // "12,34,56"
    const META_FAQS          = '_gps_faqs';             // JSON [{q, a}]
    const META_FAQS_SITE     = '_gps_faqs_site';        // yes | no (include the site-wide FAQs)
    const META_SPONSORS_MODE = '_gps_sponsors_mode';    // default | none | custom
    const META_SPONSOR_IDS   = '_gps_sponsor_ids';      // int[]
    const META_REFUND_MODE   = '_gps_refund_mode';      // default | custom
    const META_REFUND_TEXT   = '_gps_refund_text';      // one rule per line
    const META_EXTRA_TITLE   = '_gps_extra_title';
    const META_EXTRA_CONTENT = '_gps_extra_content';
    const META_CTA_LABEL     = '_gps_cta_label';

    /* Speaker / sponsor extras */
    const META_SPEAKER_URL = '_gps_profile_url';
    const META_SPONSOR_URL = '_gps_sponsor_url';
    const META_SPONSOR_DARK = '_gps_sponsor_dark_logo'; // '1': white / light logo, shown on a navy plate

    /* Site settings (Settings → Course page) */
    const OPT_TEMPLATE = 'gps_course_template';         // elementor | gps
    const OPT_REFUND   = 'gps_refund_policy';
    const OPT_FAQS     = 'gps_default_faqs';            // [{q, a}]
    const OPT_SPONSORS = 'gps_default_sponsor_ids';     // int[]
    const OPT_CONTACT  = 'gps_contact_email';

    const DEFAULT_CONTACT = 'gpsdentaltraining@gaprostho.com';

    /** Seats left at or under this count show "Only N seats left" (same as the home hero). */
    const FEW_SEATS = 5;

    /** @var array post_id => bool, per request */
    protected static $use = [];

    /** @var array post_id => data(), per request */
    protected static $data = [];

    public static function init() {
        add_action('init', [__CLASS__, 'register_meta']);
        add_filter('template_include', [__CLASS__, 'template'], 99);
        add_action('template_redirect', [__CLASS__, 'no_cache_preview'], 1);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 110);
        add_action('wp_enqueue_scripts', [__CLASS__, 'skip_elementor_post_css'], 999);
        add_action('wp_head', [__CLASS__, 'print_json_ld'], 30);
        add_filter('wpseo_metadesc', [__CLASS__, 'meta_description'], 20);
        add_filter('body_class', [__CLASS__, 'body_class']);

        // Course editor "Page content" tab, speaker / sponsor extras
        add_action('save_post_gps_event', [__CLASS__, 'save_meta']);
        add_action('add_meta_boxes_gps_sponsor', [__CLASS__, 'add_sponsor_box']);
        add_action('save_post_gps_sponsor', [__CLASS__, 'save_sponsor_meta']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);

        // Settings → Course page
        add_action('admin_init', [__CLASS__, 'register_settings']);
        // Any site-wide choice shown on course pages: refresh their cached copies
        foreach ([self::OPT_TEMPLATE, self::OPT_REFUND, self::OPT_FAQS, self::OPT_SPONSORS, self::OPT_CONTACT] as $opt) {
            add_action('update_option_' . $opt, [__CLASS__, 'purge_all_courses']);
            add_action('add_option_' . $opt, [__CLASS__, 'purge_all_courses']);
        }

        // "Register now" posts to the cart: redirect after adding so a refresh can't add it twice
        add_filter('woocommerce_add_to_cart_redirect', [__CLASS__, 'add_to_cart_redirect']);
    }

    /* ============================================================
     * Routing
     * ============================================================ */

    /** Site-wide choice: 'gps' or 'elementor'. */
    public static function site_mode() {
        return 'gps' === get_option(self::OPT_TEMPLATE, 'elementor') ? 'gps' : 'elementor';
    }

    /** Per-course choice: '' (site default), 'gps' or 'elementor'. */
    public static function post_mode($post_id) {
        $mode = (string) get_post_meta($post_id, self::META_TEMPLATE, true);
        return in_array($mode, ['gps', 'elementor'], true) ? $mode : '';
    }

    /**
     * ?gps_course_template=1 (GPS template) / =0 (Elementor) for people who can
     * edit the course; null otherwise.
     */
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

    /** Does this course page use the GPS template on this request? */
    public static function uses_template($post_id = 0) {
        $post_id = (int) ($post_id ?: get_queried_object_id());
        if (!$post_id || 'gps_event' !== get_post_type($post_id)) {
            return false;
        }
        if (isset(self::$use[$post_id])) {
            return self::$use[$post_id];
        }
        // The Elementor editor loads the page in an iframe: keep the Elementor document there
        if (isset($_GET['elementor-preview']) || (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->preview) && \Elementor\Plugin::$instance->preview->is_preview_mode())) {
            return self::$use[$post_id] = false;
        }
        $preview = self::preview_flag($post_id);
        if (null !== $preview) {
            $use = $preview;
        } else {
            $mode = self::post_mode($post_id);
            $use  = 'gps' === ('' === $mode ? self::site_mode() : $mode);
        }
        // Password-protected courses keep the theme's password form
        if ($use && post_password_required($post_id)) {
            $use = false;
        }
        return self::$use[$post_id] = (bool) apply_filters('gpsc_course_page_template', $use, $post_id);
    }

    /** Is the current request a single course page served by the GPS template? */
    public static function is_active() {
        return is_singular('gps_event') && !is_embed() && self::uses_template();
    }

    /**
     * Serve templates/single-gps_event.php. Runs after Elementor's own
     * template_include (Elementor Full Width / theme builder), so the course's
     * Elementor document is never rendered: the template doesn't call the_content().
     */
    public static function template($template) {
        if (!self::is_active()) {
            return $template;
        }
        $ours = GPSC_PATH . 'templates/single-gps_event.php';
        return file_exists($ours) ? $ours : $template;
    }

    /** Previews must never land in (or come from) the page cache. */
    public static function no_cache_preview() {
        if (!is_singular('gps_event') || null === self::preview_flag()) {
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
            $classes[] = 'gps-course-page';
            $d = self::data((int) get_queried_object_id());
            if ('open' === $d['stage'] && $d['buyable']) {
                $classes[] = 'gps-cp-has-bar'; // phone register bar: room under the footer
            }
        }
        return $classes;
    }

    /**
     * The course's own Elementor document isn't rendered, so its post CSS
     * (elementor-post-{ID}) isn't needed. Header/footer templates keep theirs.
     */
    public static function skip_elementor_post_css() {
        if (!self::is_active()) {
            return;
        }
        $handle = 'elementor-post-' . (int) get_queried_object_id();
        wp_dequeue_style($handle);
    }

    public static function enqueue_assets() {
        if (!self::is_active()) {
            return;
        }
        if (class_exists(__NAMESPACE__ . '\\Course_Stage')) {
            Course_Stage::register_assets();
        }
        $css  = GPSC_PATH . 'assets/css/gps-course.css';
        $js   = GPSC_PATH . 'assets/js/gps-course.js';
        $deps = [];
        foreach (['gps-forms', 'gps-course-stage'] as $handle) {
            if (wp_style_is($handle, 'registered') || wp_style_is($handle, 'enqueued')) {
                $deps[] = $handle;
            }
        }
        wp_enqueue_style('gps-course', GPSC_URL . 'assets/css/gps-course.css', $deps, file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
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
        // "Notify me" form (Interest_List) and the fresh-nonce helper for the waitlist
        if (wp_script_is('gps-interest', 'registered')) {
            wp_enqueue_script('gps-interest');
        }
    }

    public static function add_to_cart_redirect($url) {
        if (!empty($_REQUEST['gps_cp_add']) && function_exists('wc_get_cart_url')) {
            return wc_get_cart_url();
        }
        return $url;
    }

    /** A setting change switches every course page: clear their cached copies. */
    public static function purge_all_courses() {
        static $done = false; // several options saved in one request
        if ($done) {
            return;
        }
        $done = true;
        $ids = get_posts([
            'post_type'     => 'gps_event',
            'post_status'   => 'publish',
            'numberposts'   => -1,
            'fields'        => 'ids',
            'no_found_rows' => true,
        ]);
        $urls = [];
        foreach ((array) $ids as $id) {
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
        do_action('gpsc_purge_course_urls', $urls, 0);
    }

    /* ============================================================
     * Settings (site-wide defaults)
     * ============================================================ */

    public static function default_refund_text() {
        return implode("\n", [
            __('30+ days before: 100% refund', 'gps-courses'),
            __('15-29 days before: 50% refund', 'gps-courses'),
            __('14 days or less: No refund', 'gps-courses'),
        ]);
    }

    /** Site-wide FAQs (the option, or the starter set until it is saved). */
    public static function default_faqs() {
        $saved = get_option(self::OPT_FAQS, null);
        if (is_array($saved)) {
            return self::clean_faqs($saved);
        }
        return [
            ['q' => __('How many CE credits will I earn?', 'gps-courses'), 'a' => __('{ce_credits} CE credits. GPS Dental Training is a Nationally Approved PACE Program Provider for FAGD/MAGD credit. Credits are added to your account when your certificate is issued.', 'gps-courses')],
            ['q' => __('When do I get my ticket?', 'gps-courses'), 'a' => __('Right after payment we email your ticket with its QR code. It is also saved in My Account › My Tickets.', 'gps-courses')],
            ['q' => __('What is the refund policy?', 'gps-courses'), 'a' => '{refund_policy}'],
        ];
    }

    public static function contact_email() {
        $email = sanitize_email((string) get_option(self::OPT_CONTACT, self::DEFAULT_CONTACT));
        return is_email($email) ? $email : self::DEFAULT_CONTACT;
    }

    public static function register_settings() {
        $group = 'gps_course_page_settings';
        register_setting($group, self::OPT_TEMPLATE, [
            'type' => 'string', 'default' => 'elementor',
            'sanitize_callback' => function ($v) { return 'gps' === $v ? 'gps' : 'elementor'; },
        ]);
        register_setting($group, self::OPT_REFUND, [
            'type' => 'string', 'default' => self::default_refund_text(),
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting($group, self::OPT_FAQS, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_faqs_input'],
        ]);
        register_setting($group, self::OPT_SPONSORS, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_ids'],
        ]);
        register_setting($group, self::OPT_CONTACT, [
            'type' => 'string', 'default' => self::DEFAULT_CONTACT,
            'sanitize_callback' => function ($v) {
                $v = sanitize_email((string) $v);
                return is_email($v) ? $v : self::DEFAULT_CONTACT;
            },
        ]);
    }

    /** Settings → Course page tab (rendered by Settings::render_settings_page). */
    public static function render_settings_tab() {
        $mode     = self::site_mode();
        $refund   = (string) get_option(self::OPT_REFUND, self::default_refund_text());
        $faqs     = self::default_faqs();
        $sponsors = self::sanitize_ids(get_option(self::OPT_SPONSORS, []));
        $all      = self::all_sponsors();
        $key_set  = '' !== trim((string) get_option('gps_google_maps_api_key', ''));
        ?>
        <form method="post" action="options.php" class="gps-cp-settings">
            <?php settings_fields('gps_course_page_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="gps-course-template"><?php esc_html_e('Course page template', 'gps-courses'); ?></label></th>
                    <td>
                        <select id="gps-course-template" name="<?php echo esc_attr(self::OPT_TEMPLATE); ?>">
                            <option value="elementor" <?php selected($mode, 'elementor'); ?>><?php esc_html_e('Elementor (current page of each course)', 'gps-courses'); ?></option>
                            <option value="gps" <?php selected($mode, 'gps'); ?>><?php esc_html_e('GPS template (built from the course data)', 'gps-courses'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Each course can override this in its "Page content" tab. Editors can preview the GPS template on any course by adding ?gps_course_template=1 to its address (=0 shows the Elementor page).', 'gps-courses'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gps-refund-policy"><?php esc_html_e('Default refund policy', 'gps-courses'); ?></label></th>
                    <td>
                        <textarea id="gps-refund-policy" name="<?php echo esc_attr(self::OPT_REFUND); ?>" rows="4" class="large-text"><?php echo esc_textarea($refund); ?></textarea>
                        <p class="description"><?php esc_html_e('One rule per line. Shown in the registration card, the Ticket Selector widget and wherever {refund_policy} is used in a FAQ answer. A course can use its own policy.', 'gps-courses'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Site-wide FAQs', 'gps-courses'); ?></th>
                    <td>
                        <?php self::render_faq_editor(self::OPT_FAQS . '[q][]', self::OPT_FAQS . '[a][]', $faqs, 'gps-site-faqs'); ?>
                        <p class="description"><?php esc_html_e('Shown first on every course page (a course can turn them off). Placeholders: {ce_credits}, {course}, {dates}, {venue}, {refund_policy}, {contact_email}. A question whose answer uses {ce_credits} is skipped on courses without CE credits.', 'gps-courses'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Default sponsors', 'gps-courses'); ?></th>
                    <td>
                        <?php self::render_sponsor_checkboxes(self::OPT_SPONSORS . '[]', $sponsors, $all); ?>
                        <p class="description">
                            <?php esc_html_e('Logos in the sponsor strip of every course set to "Site default sponsors".', 'gps-courses'); ?>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=gps_sponsor')); ?>"><?php esc_html_e('Manage sponsors', 'gps-courses'); ?></a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gps-contact-email"><?php esc_html_e('Contact email', 'gps-courses'); ?></label></th>
                    <td>
                        <input type="email" id="gps-contact-email" name="<?php echo esc_attr(self::OPT_CONTACT); ?>" value="<?php echo esc_attr(self::contact_email()); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('"Questions?" address in the Location section.', 'gps-courses'); ?></p>
                    </td>
                </tr>
                <?php do_action('gpsc_course_page_settings_rows'); // Contact_Page adds its rows ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Map', 'gps-courses'); ?></th>
                    <td>
                        <p class="description">
                            <?php
                            echo $key_set
                                ? esc_html__('A Google Maps API key is set (General tab): course pages show an embedded map. The key needs the "Maps Embed API" enabled.', 'gps-courses')
                                : esc_html__('No Google Maps API key (General tab): course pages show a "View on Google Maps" card instead of an embedded map.', 'gps-courses');
                            ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=gps-settings&tab=general')); ?>"><?php esc_html_e('General settings', 'gps-courses'); ?></a>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /* ============================================================
     * Per-course meta
     * ============================================================ */

    public static function format_presets() {
        return [
            __('Hands-on + live surgery', 'gps-courses'),
            __('Hands-on (models)', 'gps-courses'),
            __('Lecture', 'gps-courses'),
            __('Hybrid', 'gps-courses'),
        ];
    }

    public static function register_meta() {
        $auth = function () { return current_user_can('edit_posts'); };
        $text = function ($key, $cb) use ($auth) {
            register_post_meta('gps_event', $key, [
                'type' => 'string', 'single' => true, 'show_in_rest' => false,
                'auth_callback' => $auth, 'sanitize_callback' => $cb,
            ]);
        };
        $text(self::META_TEMPLATE, function ($v) { return in_array($v, ['gps', 'elementor'], true) ? $v : ''; });
        $text(self::META_TAGLINE, 'sanitize_text_field');
        $text(self::META_FORMAT, 'sanitize_text_field');
        $text(self::META_AUDIENCE, 'sanitize_text_field');
        $text(self::META_INCLUDED, 'sanitize_textarea_field');
        $text(self::META_PREREQ, 'sanitize_textarea_field');
        $text(self::META_GALLERY, [__CLASS__, 'sanitize_id_list']);
        $text(self::META_FAQS, [__CLASS__, 'sanitize_faqs_json']);
        $text(self::META_FAQS_SITE, function ($v) { return 'no' === $v ? 'no' : 'yes'; });
        $text(self::META_SPONSORS_MODE, function ($v) { return in_array($v, ['none', 'custom'], true) ? $v : 'default'; });
        $text(self::META_REFUND_MODE, function ($v) { return 'custom' === $v ? 'custom' : 'default'; });
        $text(self::META_REFUND_TEXT, 'sanitize_textarea_field');
        $text(self::META_EXTRA_TITLE, 'sanitize_text_field');
        $text(self::META_EXTRA_CONTENT, 'wp_kses_post');
        $text(self::META_CTA_LABEL, 'sanitize_text_field');
        register_post_meta('gps_event', self::META_SPONSOR_IDS, [
            'type' => 'array', 'single' => true, 'auth_callback' => $auth,
            'show_in_rest' => ['schema' => ['type' => 'array', 'items' => ['type' => 'integer']]],
            'sanitize_callback' => [__CLASS__, 'sanitize_ids'],
        ]);
        register_post_meta('gps_speaker', self::META_SPEAKER_URL, [
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'auth_callback' => $auth, 'sanitize_callback' => 'esc_url_raw',
        ]);
        register_post_meta('gps_sponsor', self::META_SPONSOR_URL, [
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'auth_callback' => $auth, 'sanitize_callback' => 'esc_url_raw',
        ]);
    }

    /** @return int[] unique positive IDs */
    public static function sanitize_ids($value) {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        $ids = array_filter(array_map('absint', (array) $value));
        return array_values(array_unique($ids));
    }

    public static function sanitize_id_list($value) {
        return implode(',', self::sanitize_ids($value));
    }

    /** FAQ answers keep links and emphasis only. */
    protected static function kses_answer($text) {
        return trim(wp_kses((string) $text, [
            'a'      => ['href' => true, 'target' => true, 'rel' => true],
            'strong' => [], 'em' => [], 'b' => [], 'i' => [], 'br' => [],
        ]));
    }

    /** [{q, a}] with both parts present, sanitized. */
    public static function clean_faqs($rows) {
        $out = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = sanitize_text_field((string) ($row['q'] ?? ''));
            $a = self::kses_answer($row['a'] ?? '');
            if ('' !== $q && '' !== $a) {
                $out[] = ['q' => $q, 'a' => $a];
            }
        }
        return $out;
    }

    /** Settings form posts parallel arrays (q[], a[]); update_option() may pass rows. */
    public static function sanitize_faqs_input($value) {
        if (is_array($value) && isset($value['q']) && is_array($value['q'])) {
            $rows = [];
            foreach (array_values($value['q']) as $i => $q) {
                $rows[] = ['q' => $q, 'a' => $value['a'][$i] ?? '']; // options.php already unslashed
            }
            $value = $rows;
        }
        return self::clean_faqs($value);
    }

    public static function sanitize_faqs_json($value) {
        $rows = is_string($value) ? json_decode($value, true) : $value;
        $rows = self::clean_faqs(is_array($rows) ? $rows : []);
        return $rows ? wp_json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    }

    public static function course_faqs($post_id) {
        $rows = json_decode((string) get_post_meta($post_id, self::META_FAQS, true), true);
        return self::clean_faqs(is_array($rows) ? $rows : []);
    }

    /** Speaker IDs as stored by register_post_meta (array) or older serialized/CSV values. */
    public static function speaker_ids($post_id) {
        $raw = get_post_meta($post_id, self::META_SPEAKERS, true);
        if (is_string($raw) && '' !== $raw) {
            $maybe = json_decode($raw, true);
            $raw   = is_array($maybe) ? $maybe : explode(',', $raw);
        }
        return self::sanitize_ids($raw);
    }

    /** Save the "Page content" tab. */
    public static function save_meta($post_id) {
        if (!isset($_POST['gps_page_content_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gps_page_content_nonce'])), 'gps_page_content')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $post = function ($key) {
            return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
        };
        $set = function ($key, $value) use ($post_id) {
            if ('' === $value || [] === $value || null === $value) {
                delete_post_meta($post_id, $key);
            } else {
                // update_post_meta() unslashes: keep backslashes (and the FAQ JSON) intact
                update_post_meta($post_id, $key, wp_slash($value));
            }
        };

        $template = sanitize_key($post('gps_page_template'));
        $set(self::META_TEMPLATE, in_array($template, ['gps', 'elementor'], true) ? $template : '');

        $set(self::META_TAGLINE, sanitize_text_field($post('gps_tagline')));
        $format = sanitize_text_field($post('gps_format'));
        if ('__custom' === $format) {
            $format = sanitize_text_field($post('gps_format_custom'));
        }
        $set(self::META_FORMAT, $format);
        $set(self::META_AUDIENCE, sanitize_text_field($post('gps_audience')));
        $set(self::META_INCLUDED, sanitize_textarea_field($post('gps_included')));
        $set(self::META_PREREQ, sanitize_textarea_field($post('gps_prerequisites')));

        // Instructors: only published-or-draft speakers, in the chosen order
        $speakers = array_values(array_filter(self::sanitize_ids(isset($_POST['gps_speaker_ids']) ? (array) $_POST['gps_speaker_ids'] : []), function ($id) {
            return 'gps_speaker' === get_post_type($id);
        }));
        $set(self::META_SPEAKERS, $speakers);

        $gallery = array_values(array_filter(self::sanitize_ids($post('gps_gallery_ids')), 'wp_attachment_is_image'));
        $set(self::META_GALLERY, implode(',', $gallery));

        $rows = [];
        $qs   = isset($_POST['gps_faq_q']) ? (array) wp_unslash($_POST['gps_faq_q']) : [];
        $as   = isset($_POST['gps_faq_a']) ? (array) wp_unslash($_POST['gps_faq_a']) : [];
        foreach (array_values($qs) as $i => $q) {
            $rows[] = ['q' => $q, 'a' => $as[$i] ?? ''];
        }
        $set(self::META_FAQS, self::sanitize_faqs_json($rows));
        update_post_meta($post_id, self::META_FAQS_SITE, empty($_POST['gps_faqs_site']) ? 'no' : 'yes');

        $mode = sanitize_key($post('gps_sponsors_mode'));
        $set(self::META_SPONSORS_MODE, in_array($mode, ['none', 'custom'], true) ? $mode : '');
        $sponsor_ids = array_values(array_filter(self::sanitize_ids(isset($_POST['gps_sponsor_ids']) ? (array) $_POST['gps_sponsor_ids'] : []), function ($id) {
            return 'gps_sponsor' === get_post_type($id);
        }));
        $set(self::META_SPONSOR_IDS, $sponsor_ids);

        $refund_mode = 'custom' === sanitize_key($post('gps_refund_mode')) ? 'custom' : '';
        $set(self::META_REFUND_MODE, $refund_mode);
        $set(self::META_REFUND_TEXT, sanitize_textarea_field($post('gps_refund_text')));

        $set(self::META_EXTRA_TITLE, sanitize_text_field($post('gps_extra_title')));
        $set(self::META_EXTRA_CONTENT, trim(wp_kses_post($post('gps_extra_content'))));
        $set(self::META_CTA_LABEL, sanitize_text_field($post('gps_cta_label')));
    }

    /* ---------------- Sponsor link box ---------------- */

    public static function add_sponsor_box() {
        add_meta_box('gps_sponsor_link', __('Sponsor link', 'gps-courses'), [__CLASS__, 'render_sponsor_box'], 'gps_sponsor', 'side');
    }

    public static function render_sponsor_box($post) {
        wp_nonce_field('gps_sponsor_meta', 'gps_sponsor_nonce');
        ?>
        <p><label for="gps-sponsor-url"><strong><?php esc_html_e('Website', 'gps-courses'); ?></strong></label>
        <input type="url" id="gps-sponsor-url" name="gps_sponsor_url" class="widefat" value="<?php echo esc_attr((string) get_post_meta($post->ID, self::META_SPONSOR_URL, true)); ?>" placeholder="https://"></p>
        <p class="description"><?php esc_html_e('The logo is the featured image (transparent PNG or SVG, about 280 × 112 px). Optional link for the logo on course pages.', 'gps-courses'); ?></p>
        <p><label><input type="checkbox" name="gps_sponsor_dark_logo" value="1" <?php checked('1', (string) get_post_meta($post->ID, self::META_SPONSOR_DARK, true)); ?>> <?php esc_html_e('Light / white logo: darken it so it shows on white', 'gps-courses'); ?></label></p>
        <?php
    }

    public static function save_sponsor_meta($post_id) {
        if (!isset($_POST['gps_sponsor_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gps_sponsor_nonce'])), 'gps_sponsor_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $url = isset($_POST['gps_sponsor_url']) ? esc_url_raw(wp_unslash($_POST['gps_sponsor_url'])) : '';
        if ('' === $url) {
            delete_post_meta($post_id, self::META_SPONSOR_URL);
        } else {
            update_post_meta($post_id, self::META_SPONSOR_URL, $url);
        }
        if (!empty($_POST['gps_sponsor_dark_logo'])) {
            update_post_meta($post_id, self::META_SPONSOR_DARK, '1');
        } else {
            delete_post_meta($post_id, self::META_SPONSOR_DARK);
        }
    }

    /* ============================================================
     * Admin: "Page content" tab
     * ============================================================ */

    public static function admin_assets($hook) {
        $screen   = function_exists('get_current_screen') ? get_current_screen() : null;
        $course   = in_array($hook, ['post.php', 'post-new.php'], true) && $screen && 'gps_event' === $screen->post_type;
        // By page slug: the hook name follows the (translated) menu title
        $settings = is_admin() && isset($_GET['page']) && 'gps-settings' === $_GET['page'];
        if (!$course && !$settings) {
            return;
        }
        wp_enqueue_media();
        $js = GPSC_PATH . 'assets/js/admin-course-page.js';
        wp_enqueue_script('gps-admin-course-page', GPSC_URL . 'assets/js/admin-course-page.js', ['jquery', 'jquery-ui-sortable'], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, true);
        wp_localize_script('gps-admin-course-page', 'gpsCoursePageAdmin', [
            'i18n' => [
                'gallery'    => __('Course gallery', 'gps-courses'),
                'addImages'  => __('Add to gallery', 'gps-courses'),
                'remove'     => __('Remove', 'gps-courses'),
                'question'   => __('Question', 'gps-courses'),
                'answer'     => __('Answer', 'gps-courses'),
                'moveHandle' => __('Drag to reorder', 'gps-courses'),
                'notFound'   => __('No speaker with that name. Create it under GPS Courses → Speakers first.', 'gps-courses'),
            ],
        ]);
    }

    /** Admin styles shared by the tab and the settings page (printed once). */
    protected static function admin_css() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
        <style>
            .gps-pc{max-width:980px}
            .gps-pc__note{background:#f0f6fc;border-left:4px solid #193463;padding:12px 14px;margin:0 0 18px;border-radius:4px}
            .gps-pc__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 28px}
            .gps-pc__grid .gps-form-field--wide{grid-column:1/-1}
            .gps-pc h3{margin:26px 0 8px;padding-top:16px;border-top:1px solid #dcdcde}
            .gps-pc-chips{display:flex;flex-wrap:wrap;gap:8px;margin:6px 0 8px;min-height:10px}
            .gps-pc-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 6px 5px 12px;border-radius:999px;background:#e8eef8;color:#193463;font-weight:600;cursor:move}
            .gps-pc-chip button{border:0;background:none;color:#193463;cursor:pointer;font-size:16px;line-height:1;padding:0 4px}
            .gps-pc-gallery{list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:8px;margin:6px 0 8px}
            .gps-pc-gallery li{position:relative;margin:0;cursor:move}
            .gps-pc-gallery img{display:block;width:100%;height:72px;object-fit:cover;border-radius:4px}
            .gps-pc-gallery button{position:absolute;top:3px;right:3px;width:22px;height:22px;border:0;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;line-height:1}
            .gps-pc-faqs{margin:6px 0 8px}
            .gps-pc-faq{display:grid;grid-template-columns:22px 1fr auto;gap:6px 10px;align-items:start;padding:10px;border:1px solid #dcdcde;border-radius:4px;background:#fff;margin-bottom:8px}
            .gps-pc-faq__handle{cursor:move;color:#8c8f94;padding-top:6px}
            .gps-pc-faq__fields{display:flex;flex-direction:column;gap:6px}
            .gps-pc-faq__fields input,.gps-pc-faq__fields textarea{width:100%}
            .gps-pc-faq--site{background:#f6f7f7;color:#50575e}
            .gps-pc-sponsors{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;max-width:760px}
            .gps-pc-sponsors label{display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #dcdcde;border-radius:4px;background:#fff}
            .gps-pc-sponsors img{max-height:28px;width:auto;max-width:90px}
            @media (max-width:900px){.gps-pc__grid{grid-template-columns:1fr}}
        </style>
        <?php
    }

    /** FAQ repeater (course tab and settings page). */
    public static function render_faq_editor($q_name, $a_name, array $rows, $id) {
        self::admin_css();
        echo '<div class="gps-pc-faqs" id="' . esc_attr($id) . '" data-q-name="' . esc_attr($q_name) . '" data-a-name="' . esc_attr($a_name) . '">';
        foreach ($rows as $row) {
            self::faq_row($q_name, $a_name, $row['q'], $row['a']);
        }
        echo '</div>';
        echo '<button type="button" class="button gps-pc-faq-add" data-target="' . esc_attr($id) . '">' . esc_html__('+ Add question', 'gps-courses') . '</button>';
        // Row template for the script
        echo '<script type="text/html" id="' . esc_attr($id) . '-tpl">';
        self::faq_row($q_name, $a_name, '', '');
        echo '</script>';
    }

    protected static function faq_row($q_name, $a_name, $q, $a) {
        ?>
        <div class="gps-pc-faq">
            <span class="gps-pc-faq__handle dashicons dashicons-menu" title="<?php esc_attr_e('Drag to reorder', 'gps-courses'); ?>" aria-hidden="true"></span>
            <div class="gps-pc-faq__fields">
                <input type="text" name="<?php echo esc_attr($q_name); ?>" value="<?php echo esc_attr($q); ?>" placeholder="<?php esc_attr_e('Question', 'gps-courses'); ?>" aria-label="<?php esc_attr_e('Question', 'gps-courses'); ?>">
                <textarea name="<?php echo esc_attr($a_name); ?>" rows="3" placeholder="<?php esc_attr_e('Answer', 'gps-courses'); ?>" aria-label="<?php esc_attr_e('Answer', 'gps-courses'); ?>"><?php echo esc_textarea(wp_specialchars_decode($a, ENT_QUOTES)); ?></textarea>
            </div>
            <button type="button" class="button-link gps-pc-faq-remove" aria-label="<?php esc_attr_e('Remove question', 'gps-courses'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
        </div>
        <?php
    }

    /** Published sponsors, alphabetical. */
    protected static function all_sponsors() {
        return get_posts([
            'post_type'     => 'gps_sponsor',
            'post_status'   => 'publish',
            'numberposts'   => 100,
            'orderby'       => 'title',
            'order'         => 'ASC',
            'no_found_rows' => true,
        ]);
    }

    protected static function render_sponsor_checkboxes($name, array $checked, array $all) {
        self::admin_css();
        if (!$all) {
            echo '<p><em>' . esc_html__('No sponsors yet.', 'gps-courses') . '</em> <a href="' . esc_url(admin_url('post-new.php?post_type=gps_sponsor')) . '">' . esc_html__('Add a sponsor', 'gps-courses') . '</a></p>';
            return;
        }
        echo '<div class="gps-pc-sponsors">';
        foreach ($all as $s) {
            $logo = get_the_post_thumbnail($s->ID, 'thumbnail', ['alt' => '']);
            printf(
                '<label><input type="checkbox" name="%s" value="%d"%s> %s <span>%s</span></label>',
                esc_attr($name),
                (int) $s->ID,
                in_array((int) $s->ID, $checked, true) ? ' checked' : '',
                $logo, // get_the_post_thumbnail() output
                esc_html(get_the_title($s))
            );
        }
        echo '</div>';
    }

    /** "Page content" tab of the course edit screen (Posttypes::custom_event_edit_screen). */
    public static function render_event_tab($post) {
        self::admin_css();
        $id          = (int) $post->ID;
        $template    = self::post_mode($id);
        $site        = self::site_mode();
        $format      = (string) get_post_meta($id, self::META_FORMAT, true);
        $presets     = self::format_presets();
        $is_custom   = '' !== $format && !in_array($format, $presets, true);
        $speaker_ids = self::speaker_ids($id);
        $speakers    = get_posts(['post_type' => 'gps_speaker', 'post_status' => ['publish', 'draft', 'private'], 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true]);
        $by_id       = [];
        foreach ($speakers as $s) {
            $by_id[(int) $s->ID] = self::speaker_label($s->ID);
        }
        $gallery     = self::sanitize_ids(get_post_meta($id, self::META_GALLERY, true));
        $faqs        = self::course_faqs($id);
        $faqs_site   = 'no' !== get_post_meta($id, self::META_FAQS_SITE, true);
        $sp_mode     = (string) get_post_meta($id, self::META_SPONSORS_MODE, true) ?: 'default';
        $sp_ids      = self::sanitize_ids(get_post_meta($id, self::META_SPONSOR_IDS, true));
        $refund_mode = 'custom' === get_post_meta($id, self::META_REFUND_MODE, true) ? 'custom' : 'default';
        $hero_id     = (int) get_post_meta($id, '_gps_hero_image', true);
        $thumb_id    = (int) get_post_thumbnail_id($id);
        $link        = get_permalink($id);

        wp_nonce_field('gps_page_content', 'gps_page_content_nonce');
        ?>
        <div class="gps-pc">
            <div class="gps-pc__note">
                <?php
                echo esc_html(self::uses_template_for_admin($id)
                    ? __('This course page uses the GPS template, built from the fields below and the course details.', 'gps-courses')
                    : __('This course page currently uses its Elementor design. The fields below are used when the GPS template is on.', 'gps-courses'));
                ?>
                <?php if ($link && 'publish' === $post->post_status): ?>
                    <a href="<?php echo esc_url(add_query_arg(self::QUERY_ARG, '1', $link)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview the GPS template', 'gps-courses'); ?></a>
                <?php endif; ?>
                <br><strong><?php esc_html_e('Duplicate a course to create a new edition, then update dates, tickets and program.', 'gps-courses'); ?></strong>
            </div>

            <div class="gps-pc__grid">
                <div class="gps-form-field">
                    <label for="gps-page-template"><?php esc_html_e('Course page template', 'gps-courses'); ?></label>
                    <select name="gps_page_template" id="gps-page-template">
                        <option value="" <?php selected($template, ''); ?>><?php
                            /* translators: %s: Elementor or GPS template */
                            echo esc_html(sprintf(__('Use site default (%s)', 'gps-courses'), 'gps' === $site ? __('GPS template', 'gps-courses') : __('Elementor', 'gps-courses')));
                        ?></option>
                        <option value="gps" <?php selected($template, 'gps'); ?>><?php esc_html_e('GPS template', 'gps-courses'); ?></option>
                        <option value="elementor" <?php selected($template, 'elementor'); ?>><?php esc_html_e('Elementor', 'gps-courses'); ?></option>
                    </select>
                </div>

                <div class="gps-form-field">
                    <label for="gps-cta-label"><?php esc_html_e('Register button label (optional)', 'gps-courses'); ?></label>
                    <input type="text" class="widefat" name="gps_cta_label" id="gps-cta-label" value="<?php echo esc_attr((string) get_post_meta($id, self::META_CTA_LABEL, true)); ?>" placeholder="<?php esc_attr_e('Register now', 'gps-courses'); ?>">
                </div>

                <div class="gps-form-field gps-form-field--wide">
                    <label for="gps-tagline"><?php esc_html_e('Tagline (under the title)', 'gps-courses'); ?></label>
                    <input type="text" class="widefat" name="gps_tagline" id="gps-tagline" maxlength="220" value="<?php echo esc_attr((string) get_post_meta($id, self::META_TAGLINE, true)); ?>">
                    <p class="description"><?php esc_html_e('One sentence. Also the search description when the SEO description is empty. Empty = the "Short line" of the Home hero tab, if any.', 'gps-courses'); ?></p>
                </div>

                <div class="gps-form-field">
                    <label for="gps-format"><?php esc_html_e('Format', 'gps-courses'); ?></label>
                    <select name="gps_format" id="gps-format" data-gps-custom="#gps-format-custom">
                        <option value=""><?php esc_html_e('— Not shown —', 'gps-courses'); ?></option>
                        <?php foreach ($presets as $p): ?>
                            <option value="<?php echo esc_attr($p); ?>" <?php selected($format, $p); ?>><?php echo esc_html($p); ?></option>
                        <?php endforeach; ?>
                        <option value="__custom" <?php selected($is_custom); ?>><?php esc_html_e('Custom…', 'gps-courses'); ?></option>
                    </select>
                    <input type="text" class="widefat" name="gps_format_custom" id="gps-format-custom" value="<?php echo esc_attr($is_custom ? $format : ''); ?>" placeholder="<?php esc_attr_e('e.g. Hands-on with live patients', 'gps-courses'); ?>" style="margin-top:6px;<?php echo $is_custom ? '' : 'display:none;'; ?>">
                </div>

                <div class="gps-form-field">
                    <label for="gps-audience"><?php esc_html_e('Who should attend', 'gps-courses'); ?></label>
                    <input type="text" class="widefat" name="gps_audience" id="gps-audience" value="<?php echo esc_attr((string) get_post_meta($id, self::META_AUDIENCE, true)); ?>" placeholder="<?php esc_attr_e('e.g. General dentists and specialists placing implants', 'gps-courses'); ?>">
                </div>

                <div class="gps-form-field">
                    <label for="gps-included"><?php esc_html_e("What's included", 'gps-courses'); ?></label>
                    <textarea class="widefat" rows="3" name="gps_included" id="gps-included" placeholder="<?php esc_attr_e('Breakfast, coffee breaks and lunch', 'gps-courses'); ?>"><?php echo esc_textarea((string) get_post_meta($id, self::META_INCLUDED, true)); ?></textarea>
                    <p class="description"><?php esc_html_e('One item per line.', 'gps-courses'); ?></p>
                </div>

                <div class="gps-form-field">
                    <label for="gps-prerequisites"><?php esc_html_e('Prerequisites (optional)', 'gps-courses'); ?></label>
                    <textarea class="widefat" rows="3" name="gps_prerequisites" id="gps-prerequisites"><?php echo esc_textarea((string) get_post_meta($id, self::META_PREREQ, true)); ?></textarea>
                </div>

                <div class="gps-form-field">
                    <label><?php esc_html_e('Hero image', 'gps-courses'); ?></label>
                    <div style="margin:4px 0 6px;"><?php
                        $show = $hero_id && wp_attachment_is_image($hero_id) ? $hero_id : $thumb_id;
                        echo $show ? wp_get_attachment_image($show, 'medium', false, ['style' => 'max-width:260px;height:auto;border-radius:6px;display:block;']) : ''; // core output
                    ?></div>
                    <p class="description"><?php
                        echo esc_html($hero_id
                            ? __('Uses the image of the "Home hero" tab. Change it there.', 'gps-courses')
                            : __('Uses the course image (Basic Info tab). Set an image in the "Home hero" tab to use a different one here and in the home hero.', 'gps-courses'));
                    ?></p>
                </div>

                <div class="gps-form-field">
                    <label for="gps-speaker-search"><?php esc_html_e('Instructors', 'gps-courses'); ?></label>
                    <div class="gps-pc-chips" id="gps-speaker-chips">
                        <?php foreach ($speaker_ids as $sid): if (!isset($by_id[$sid])) continue; ?>
                            <span class="gps-pc-chip"><?php echo esc_html($by_id[$sid]); ?><input type="hidden" name="gps_speaker_ids[]" value="<?php echo (int) $sid; ?>"><button type="button" aria-label="<?php echo esc_attr(sprintf(__('Remove %s', 'gps-courses'), $by_id[$sid])); ?>">&times;</button></span>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" class="widefat" id="gps-speaker-search" list="gps-speaker-options" autocomplete="off" placeholder="<?php esc_attr_e('Search speakers… (Enter to add)', 'gps-courses'); ?>">
                    <datalist id="gps-speaker-options">
                        <?php foreach ($by_id as $sid => $label): ?>
                            <option value="<?php echo esc_attr($label); ?>" data-id="<?php echo (int) $sid; ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <p class="description">
                        <?php esc_html_e('Photo, title and bio come from the Speaker profile. The first one is shown in the hero. Drag to reorder.', 'gps-courses'); ?>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=gps_speaker')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add a speaker', 'gps-courses'); ?></a>
                    </p>
                </div>

                <div class="gps-form-field gps-form-field--wide">
                    <label><?php esc_html_e('Gallery', 'gps-courses'); ?></label>
                    <input type="hidden" name="gps_gallery_ids" id="gps-gallery-ids" value="<?php echo esc_attr(implode(',', $gallery)); ?>">
                    <ul class="gps-pc-gallery" id="gps-gallery-list">
                        <?php foreach ($gallery as $gid): ?>
                            <li data-id="<?php echo (int) $gid; ?>"><?php echo wp_get_attachment_image($gid, 'thumbnail', false, ['alt' => '']); // core output ?><button type="button" aria-label="<?php esc_attr_e('Remove image', 'gps-courses'); ?>">&times;</button></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="button" id="gps-gallery-add"><?php esc_html_e('+ Add images', 'gps-courses'); ?></button>
                    <p class="description"><?php esc_html_e('Resized automatically; alt text comes from the media library. Drag to reorder.', 'gps-courses'); ?></p>
                </div>

                <div class="gps-form-field gps-form-field--wide">
                    <label><?php esc_html_e('FAQ', 'gps-courses'); ?></label>
                    <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin:0 0 8px;">
                        <input type="checkbox" name="gps_faqs_site" value="yes" <?php checked($faqs_site); ?>>
                        <?php
                        $site_faqs = self::default_faqs();
                        /* translators: %d: number of site-wide questions */
                        echo esc_html(sprintf(__('Show the %d site-wide questions first', 'gps-courses'), count($site_faqs)));
                        ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gps-settings&tab=course_page')); ?>"><?php esc_html_e('Edit site-wide questions', 'gps-courses'); ?></a>
                    </label>
                    <?php foreach ($site_faqs as $row): ?>
                        <div class="gps-pc-faq gps-pc-faq--site"><span></span><span><?php echo esc_html($row['q']); ?> <em>(<?php esc_html_e('site-wide', 'gps-courses'); ?>)</em></span><span></span></div>
                    <?php endforeach; ?>
                    <?php self::render_faq_editor('gps_faq_q[]', 'gps_faq_a[]', $faqs, 'gps-course-faqs'); ?>
                    <p class="description"><?php esc_html_e('Questions for this course only. Placeholders: {ce_credits}, {course}, {dates}, {venue}, {refund_policy}, {contact_email}.', 'gps-courses'); ?></p>
                </div>

                <div class="gps-form-field">
                    <label for="gps-sponsors-mode"><?php esc_html_e('Sponsors', 'gps-courses'); ?></label>
                    <select name="gps_sponsors_mode" id="gps-sponsors-mode">
                        <option value="default" <?php selected($sp_mode, 'default'); ?>><?php esc_html_e('Site default sponsors', 'gps-courses'); ?></option>
                        <option value="custom" <?php selected($sp_mode, 'custom'); ?>><?php esc_html_e('Choose for this course…', 'gps-courses'); ?></option>
                        <option value="none" <?php selected($sp_mode, 'none'); ?>><?php esc_html_e('None', 'gps-courses'); ?></option>
                    </select>
                    <div id="gps-sponsors-custom" style="margin-top:8px;<?php echo 'custom' === $sp_mode ? '' : 'display:none;'; ?>">
                        <?php self::render_sponsor_checkboxes('gps_sponsor_ids[]', $sp_ids, self::all_sponsors()); ?>
                    </div>
                </div>

                <div class="gps-form-field">
                    <label for="gps-refund-mode"><?php esc_html_e('Refund policy', 'gps-courses'); ?></label>
                    <select name="gps_refund_mode" id="gps-refund-mode">
                        <option value="default" <?php selected($refund_mode, 'default'); ?>><?php
                            /* translators: %s: default refund rules on one line */
                            echo esc_html(sprintf(__('Site default (%s)', 'gps-courses'), wp_trim_words(implode('; ', self::refund_lines(0)), 8, '…')));
                        ?></option>
                        <option value="custom" <?php selected($refund_mode, 'custom'); ?>><?php esc_html_e('Custom for this course…', 'gps-courses'); ?></option>
                    </select>
                    <textarea class="widefat" rows="3" name="gps_refund_text" id="gps-refund-text" style="margin-top:6px;<?php echo 'custom' === $refund_mode ? '' : 'display:none;'; ?>" placeholder="<?php echo esc_attr(self::default_refund_text()); ?>"><?php echo esc_textarea((string) get_post_meta($id, self::META_REFUND_TEXT, true)); ?></textarea>
                </div>

                <div class="gps-form-field gps-form-field--wide">
                    <label for="gps-extra-title"><?php esc_html_e('Extra content (optional)', 'gps-courses'); ?></label>
                    <input type="text" class="widefat" name="gps_extra_title" id="gps-extra-title" value="<?php echo esc_attr((string) get_post_meta($id, self::META_EXTRA_TITLE, true)); ?>" placeholder="<?php esc_attr_e('Heading, e.g. Why this course will transform your practice', 'gps-courses'); ?>" style="margin-bottom:8px;">
                    <?php
                    wp_editor((string) get_post_meta($id, self::META_EXTRA_CONTENT, true), 'gps_extra_content', [
                        'textarea_name' => 'gps_extra_content',
                        'textarea_rows' => 8,
                        'media_buttons' => true,
                        'teeny'         => false,
                    ]);
                    ?>
                    <p class="description"><?php esc_html_e('Anything unique to this course. Shown as its own section after the instructors.', 'gps-courses'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /** Admin status (no preview flag, no request cache). */
    protected static function uses_template_for_admin($post_id) {
        $mode = self::post_mode($post_id);
        return 'gps' === ('' === $mode ? self::site_mode() : $mode);
    }

    protected static function speaker_label($speaker_id) {
        $name  = html_entity_decode(get_the_title($speaker_id), ENT_QUOTES, 'UTF-8');
        $cred  = trim((string) get_post_meta($speaker_id, '_gps_designation', true));
        return ('' !== $cred && false === stripos($name, $cred)) ? $name . ', ' . $cred : $name;
    }

    /* ============================================================
     * Data for the template
     * ============================================================ */

    /** Refund rules (one per line): the course's own or the site default. */
    public static function refund_lines($event_id) {
        $text = '';
        if ($event_id && 'custom' === get_post_meta($event_id, self::META_REFUND_MODE, true)) {
            $text = (string) get_post_meta($event_id, self::META_REFUND_TEXT, true);
        }
        if ('' === trim($text)) {
            $text = (string) get_option(self::OPT_REFUND, '');
        }
        if ('' === trim($text)) {
            $text = self::default_refund_text();
        }
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    }

    /** "Sep 17–18, 2027" */
    protected static function when($start, $end) {
        if (class_exists(__NAMESPACE__ . '\\Course_Stage')) {
            return Course_Stage::format_range($start, $end);
        }
        return $start ? date_i18n('M j, Y', strtotime($start)) : '';
    }

    /** "08:00" -> timestamp on 2000-01-01, or 0. */
    protected static function clock($time) {
        $time = trim((string) $time);
        if ('' === $time) {
            return 0;
        }
        $ts = strtotime('2000-01-01 ' . $time);
        return $ts ?: 0;
    }

    protected static function time_label($ts) {
        return $ts ? date_i18n('g:i A', $ts) : '';
    }

    /** "2 h", "1 h 30 min", "45 min" */
    public static function duration_label($minutes) {
        $minutes = (int) $minutes;
        if ($minutes <= 0) {
            return '';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h && $m) {
            /* translators: 1: hours, 2: minutes */
            return sprintf(__('%1$d h %2$d min', 'gps-courses'), $h, $m);
        }
        /* translators: %d: hours or minutes */
        return $h ? sprintf(__('%d h', 'gps-courses'), $h) : sprintf(__('%d min', 'gps-courses'), $m);
    }

    /** Break rows (registration, meals, coffee): matched case-insensitively on the title. */
    public static function break_kind($title) {
        $pattern = (string) apply_filters('gpsc_agenda_break_pattern', '/\b(registration|breakfast|coffee|lunch|break)\b/i');
        if (!@preg_match($pattern, (string) $title)) {
            return '';
        }
        if (preg_match('/registration/i', $title)) {
            return 'door';
        }
        if (preg_match('/lunch|breakfast|dinner/i', $title)) {
            return 'utensils';
        }
        return 'cup';
    }

    /**
     * Agenda days from the course's gps_schedule posts.
     *
     * @return array[] label, date (Y-m-d), long, short, rows[] (kind, start, end, until, duration, title, description, icon)
     */
    public static function agenda($event_id) {
        if (!class_exists(__NAMESPACE__ . '\\Schedules')) {
            return [];
        }
        $days = [];
        foreach ((array) Schedules::get_event_schedules($event_id) as $n => $schedule) {
            $date  = substr((string) get_post_meta($schedule->ID, '_gps_schedule_date', true), 0, 10);
            $ts    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? strtotime($date) : 0;
            $label = trim((string) get_post_meta($schedule->ID, '_gps_tab_label', true));
            /* translators: %d: day number */
            $label = '' !== $label ? $label : sprintf(__('Day %d', 'gps-courses'), $n + 1);
            $rows  = [];
            foreach ((array) Schedules::get_schedule_topics($schedule->ID) as $topic) {
                $title = trim(html_entity_decode(wp_strip_all_tags((string) ($topic['name'] ?? '')), ENT_QUOTES, 'UTF-8'));
                if ('' === $title) {
                    continue; // skip invalid rows
                }
                $s   = self::clock($topic['start_time'] ?? '');
                $e   = self::clock($topic['end_time'] ?? '');
                $min = ($s && $e && $e > $s) ? (int) round(($e - $s) / 60) : 0;
                $icon = self::break_kind($title);
                $rows[] = [
                    'kind'        => '' !== $icon ? 'break' : 'session',
                    'icon'        => $icon,
                    'start'       => self::time_label($s),
                    'until'       => $min ? self::time_label($e) : '',
                    'duration'    => self::duration_label($min),
                    'title'       => $title,
                    'description' => trim(wp_kses_post((string) ($topic['description'] ?? ''))),
                ];
            }
            if (!$rows) {
                continue;
            }
            $days[] = [
                'label' => $label,
                'date'  => $date,
                'long'  => $ts ? date_i18n('D, M j', $ts) : '',
                'short' => $ts ? date_i18n('M j', $ts) : '',
                'rows'  => $rows,
            ];
        }
        return $days;
    }

    /** Instructors from the course's speaker IDs (published speakers only). */
    public static function speakers($event_id) {
        $out = [];
        $ids = self::speaker_ids($event_id);
        // A course with editions: the current edition's own speakers, when it lists any
        $edition = Editions::resolve($event_id);
        if ($edition && $edition !== (int) $event_id) {
            $ids = self::speaker_ids($edition) ?: $ids;
        }
        foreach ($ids as $sid) {
            if ($s = self::speaker($sid)) {
                $out[] = $s;
            }
        }
        return $out;
    }

    /** One published Speaker profile for a person card, or null. */
    public static function speaker($sid) {
        $sid = (int) $sid;
        if ('gps_speaker' !== get_post_type($sid) || 'publish' !== get_post_status($sid)) {
            return null;
        }
        $speaker = get_post($sid);
        $name    = trim(html_entity_decode(get_the_title($sid), ENT_QUOTES, 'UTF-8'));
        $cred    = trim((string) get_post_meta($sid, '_gps_designation', true));
        $bio     = (string) $speaker->post_content;
        $plain   = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes($bio))));
        $short   = wp_trim_words($plain, 48, '…');
        return [
            'id'       => (int) $sid,
            'name'     => $name,
            'full'     => ('' !== $cred && false === stripos($name, $cred)) ? $name . ', ' . $cred : $name,
            'cred'     => $cred,
            'title'    => trim((string) get_post_meta($sid, '_gps_company', true)),
            'photo'    => (int) get_post_thumbnail_id($sid),
            'short'    => $short,
            'bio'      => $short !== $plain ? wpautop(wp_kses_post($bio)) : '',
            'url'      => (string) get_post_meta($sid, self::META_SPEAKER_URL, true),
            'linkedin' => (string) get_post_meta($sid, '_gps_social_linkedin', true),
        ];
    }

    /** Sponsors for the strip: default list, the course's own, or none. */
    public static function sponsors($event_id) {
        $mode = (string) get_post_meta($event_id, self::META_SPONSORS_MODE, true);
        if ('none' === $mode) {
            return [];
        }
        $ids = 'custom' === $mode
            ? self::sanitize_ids(get_post_meta($event_id, self::META_SPONSOR_IDS, true))
            : self::sanitize_ids(get_option(self::OPT_SPONSORS, []));
        $out = [];
        foreach ($ids as $sid) {
            if ('gps_sponsor' !== get_post_type($sid) || 'publish' !== get_post_status($sid)) {
                continue;
            }
            $out[] = [
                'id'   => (int) $sid,
                'name' => html_entity_decode(get_the_title($sid), ENT_QUOTES, 'UTF-8'),
                'logo' => (int) get_post_thumbnail_id($sid),
                'url'  => (string) get_post_meta($sid, self::META_SPONSOR_URL, true),
                'dark' => '1' === (string) get_post_meta($sid, self::META_SPONSOR_DARK, true),
            ];
        }
        return $out;
    }

    /**
     * Tickets on sale with stock, price and product.
     *
     * @return array[] id, title, price, product, sold_out, available (null = unlimited)
     */
    public static function tickets($event_id) {
        if (!class_exists(__NAMESPACE__ . '\\Tickets')) {
            return [];
        }
        $out = [];
        foreach ((array) Tickets::get_active_tickets($event_id) as $t) {
            $tid   = is_object($t) ? (int) $t->ID : (int) $t;
            if (Tickets::sale_ended($tid)) {
                continue; // its product is closed; status flips on the hourly check
            }
            $stock = Tickets::get_ticket_stock($tid);
            $price = get_post_meta($tid, '_gps_ticket_price', true);
            $out[] = [
                'id'        => $tid,
                'title'     => html_entity_decode(get_the_title($tid), ENT_QUOTES, 'UTF-8'),
                'price'     => '' === $price || null === $price ? null : (float) $price,
                'product'   => (int) get_post_meta($tid, '_gps_wc_product_id', true),
                'sold_out'  => Tickets::is_sold_out($tid),
                'available' => !empty($stock['unlimited']) ? null : max(0, (int) $stock['available']),
                'from'      => substr(str_replace(' ', 'T', (string) get_post_meta($tid, '_gps_ticket_start_date', true)), 0, 16),
            ];
        }
        return $out;
    }

    /** Course start as a DateTime in the site timezone (start time when set). */
    protected static function start_datetime($date, $time) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            return null;
        }
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $time = preg_match('/^\d{1,2}:\d{2}/', (string) $time) ? substr((string) $time, 0, 5) : '00:00';
        try {
            return new \DateTime($date . ' ' . $time, $tz);
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Overview HTML: the rich description, or the plain course description. */
    protected static function overview_html($event_id) {
        $rich  = (string) get_post_meta($event_id, '_gps_description', true);
        // A placeholder left in the editor ("desc") doesn't count as a description
        if (self::text_length($rich) >= 20) {
            return wpautop(do_shortcode(wp_kses_post($rich)));
        }
        $plain = trim((string) get_post_meta($event_id, '_gps_course_description', true));
        return '' !== $plain ? wpautop(esc_html($plain)) : '';
    }

    protected static function text_length($html) {
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $html)));
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    /** Replace FAQ placeholders; null when the answer needs data the course doesn't have. */
    protected static function faq_text($text, array $d) {
        if (false !== strpos($text, '{ce_credits}') && '' === $d['ce']) {
            return null;
        }
        $refund = implode('; ', $d['refund']);
        $map = [
            '{ce_credits}'    => $d['ce'],
            '{course}'        => esc_html($d['title']),
            '{dates}'         => esc_html($d['when']),
            '{venue}'         => esc_html($d['venue']),
            '{refund_policy}' => esc_html('' !== $refund ? $refund . '.' : ''),
            '{contact_email}' => '<a href="mailto:' . esc_attr($d['contact']) . '">' . esc_html($d['contact']) . '</a>',
        ];
        return strtr($text, $map);
    }

    /**
     * Everything the template shows, computed once per request.
     */
    public static function data($event_id) {
        $event_id = (int) $event_id;
        if (isset(self::$data[$event_id])) {
            return self::$data[$event_id];
        }
        $title = html_entity_decode(get_the_title($event_id), ENT_QUOTES, 'UTF-8');
        list($start, $end) = class_exists(__NAMESPACE__ . '\\Course_Stage') ? Course_Stage::dates($event_id) : ['', ''];
        $stage = class_exists(__NAMESPACE__ . '\\Course_Stage') ? Course_Stage::get($event_id) : 'open';

        // Title: the part after the first colon in gold (same rule as the home hero)
        list($white, $gold) = class_exists(__NAMESPACE__ . '\\Hero_Slider') ? Hero_Slider::split_title($title) : [$title, ''];
        $gold_len = function_exists('mb_strlen') ? mb_strlen($gold, 'UTF-8') : strlen($gold);
        $keep     = class_exists(__NAMESPACE__ . '\\Hero_Slider') ? Hero_Slider::KEEP_TOGETHER_CHARS : 22;
        $keep_sm  = class_exists(__NAMESPACE__ . '\\Hero_Slider') ? Hero_Slider::KEEP_TOGETHER_CHARS_PHONE : 14;

        $cats     = get_the_terms($event_id, 'gps_event_category');
        $category = is_array($cats) && $cats ? $cats[0] : null;

        $tagline = trim((string) get_post_meta($event_id, self::META_TAGLINE, true));
        if ('' === $tagline) {
            $tagline = trim((string) get_post_meta($event_id, '_gps_hero_line', true));
        }

        // Image: Home hero image > course image
        $image = (int) get_post_meta($event_id, '_gps_hero_image', true);
        if (!$image || !wp_attachment_is_image($image)) {
            $image = (int) get_post_thumbnail_id($event_id);
        }

        $t_start = (string) get_post_meta($event_id, '_gps_start_time', true);
        $t_end   = (string) get_post_meta($event_id, '_gps_end_time', true);
        $times   = class_exists(__NAMESPACE__ . '\\Hero_Slider') ? Hero_Slider::time_range($t_start, $t_end) : '';
        $times   = str_replace('–', ' – ', $times);

        $venue   = trim((string) get_post_meta($event_id, '_gps_venue', true));
        $address = trim((string) get_post_meta($event_id, '_gps_address', true));
        $city    = trim((string) get_post_meta($event_id, '_gps_city', true));
        $state   = trim((string) get_post_meta($event_id, '_gps_state', true));
        $zip     = trim((string) get_post_meta($event_id, '_gps_zip', true));
        $country = trim((string) get_post_meta($event_id, '_gps_country', true));
        $city_st = trim($city . ($state ? ', ' . $state : ''), ', ');
        $line2   = trim($city_st . ($zip ? ' ' . $zip : ''));
        // Some courses keep the whole address in the street field
        if ('' !== $address && '' !== $city && false !== stripos($address, $city)) {
            $line2 = '';
        }
        // Street address only: a venue name Google doesn't know moves the map off the pin
        $map_q = implode(', ', array_filter([$address, $line2 ?: '', $country]));
        if ('' === $map_q) {
            $map_q = $venue;
        }

        $ce_num = (float) get_post_meta($event_id, '_gps_ce_credits', true);
        $ce     = $ce_num > 0 ? (class_exists(__NAMESPACE__ . '\\Credits') ? Credits::format($ce_num) : (string) $ce_num) : '';

        $speakers = self::speakers($event_id);
        $fallback = trim((string) get_post_meta($event_id, '_gps_instructor', true));

        // Tickets: purchasable = on sale, not sold out, with a product
        $tickets  = in_array($stage, ['open', 'sold_out'], true) ? self::tickets($event_id) : [];
        $buyable  = array_values(array_filter($tickets, function ($t) { return !$t['sold_out'] && $t['product']; }));
        $prices   = array_filter(array_map(function ($t) { return $t['price']; }, $buyable), function ($p) { return null !== $p; });
        $seats    = null; // null = unlimited / unknown
        if ($buyable) {
            $seats = 0;
            foreach ($buyable as $t) {
                if (null === $t['available']) {
                    $seats = null;
                    break;
                }
                $seats += $t['available'];
            }
        }

        $chip = ['tone' => $stage, 'label' => class_exists(__NAMESPACE__ . '\\Course_Stage') ? Course_Stage::label($stage) : ''];
        if ('open' === $stage && null !== $seats && $seats > 0 && $seats <= self::FEW_SEATS) {
            /* translators: %d: seats left */
            $chip = ['tone' => 'few', 'label' => sprintf(_n('Only %d seat left', 'Only %d seats left', $seats, 'gps-courses'), $seats)];
        } elseif ('soon' === $stage) {
            $closed = class_exists(__NAMESPACE__ . '\\Course_Stage') && Course_Stage::sales_closed($event_id);
            $chip   = $closed
                ? ['tone' => 'past', 'label' => __('Registration closed', 'gps-courses')]
                : ['tone' => 'soon', 'label' => __('Registration opens soon', 'gps-courses')];
        }

        $start_dt = self::start_datetime($start, $t_start);
        $now      = time();
        $countdown = null;
        if ($start_dt && in_array($stage, ['open', 'sold_out'], true) && $start_dt->getTimestamp() > $now) {
            $left = $start_dt->getTimestamp() - $now;
            $countdown = [
                'target'  => $start_dt->format('c'),
                'days'    => intdiv($left, DAY_IN_SECONDS),
                'hours'   => intdiv($left % DAY_IN_SECONDS, HOUR_IN_SECONDS),
                'minutes' => intdiv($left % HOUR_IN_SECONDS, MINUTE_IN_SECONDS),
            ];
        }

        $objectives = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($event_id, '_gps_objectives', true)))));
        $included   = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_post_meta($event_id, self::META_INCLUDED, true)))));

        $extra_html  = trim((string) get_post_meta($event_id, self::META_EXTRA_CONTENT, true));
        $cta         = trim((string) get_post_meta($event_id, self::META_CTA_LABEL, true));

        $d = [
            'id'        => $event_id,
            'title'     => $title,
            'white'     => $white,
            'gold'      => $gold,
            'gold_keep' => '' !== $gold && $gold_len <= $keep,
            'gold_keep_sm' => '' !== $gold && $gold_len <= $keep_sm,
            'url'       => (string) get_permalink($event_id),
            'stage'     => $stage,
            'chip'      => $chip,
            'category'  => $category,
            'archive'   => (string) get_post_type_archive_link('gps_event'),
            'tagline'   => $tagline,
            'image'     => $image,
            'start'     => $start,
            'end'       => $end,
            'start_dt'  => $start_dt,
            'when'      => self::when($start, $end),
            'times'     => $times,
            'venue'     => $venue,
            'address'   => $address,
            'line2'     => $line2,
            'city'      => $city_st,
            'zip'       => $zip,
            'state'     => $state,
            'city_only' => $city,
            'country'   => $country,
            'map_q'     => $map_q,
            'ce'        => $ce,
            'ce_num'    => $ce_num,
            'speakers'  => $speakers,
            'instructor' => $speakers ? $speakers[0] : ('' !== $fallback ? ['name' => $fallback, 'full' => $fallback, 'cred' => '', 'title' => ''] : null),
            'overview'  => self::overview_html($event_id),
            'format'    => trim((string) get_post_meta($event_id, self::META_FORMAT, true)),
            'audience'  => trim((string) get_post_meta($event_id, self::META_AUDIENCE, true)),
            'included'  => $included,
            'prereq'    => trim((string) get_post_meta($event_id, self::META_PREREQ, true)),
            'objectives' => $objectives,
            'agenda'    => self::agenda($event_id),
            'extra_title' => trim((string) get_post_meta($event_id, self::META_EXTRA_TITLE, true)),
            'extra'     => '' !== $extra_html ? wpautop(do_shortcode(wp_kses_post($extra_html))) : '',
            'gallery'   => array_values(array_filter(self::sanitize_ids(get_post_meta($event_id, self::META_GALLERY, true)), 'wp_attachment_is_image')),
            'sponsors'  => self::sponsors($event_id),
            'refund'    => self::refund_lines($event_id),
            'contact'   => self::contact_email(),
            'tickets'   => $tickets,
            'buyable'   => $buyable,
            'price_min' => $prices ? min($prices) : null,
            'price_multi' => count(array_unique($prices)) > 1,
            'seats'     => $seats,
            'countdown' => $countdown,
            'cta'       => '' !== $cta ? $cta : __('Register now', 'gps-courses'),
            'others'    => class_exists(__NAMESPACE__ . '\\Course_Stage') ? Course_Stage::other_editions($event_id) : [],
            'map_key'   => trim((string) get_option('gps_google_maps_api_key', '')),
        ];

        // FAQs: site-wide first (unless turned off), then the course's own
        $rows = 'no' !== get_post_meta($event_id, self::META_FAQS_SITE, true) ? self::default_faqs() : [];
        $rows = array_merge($rows, self::course_faqs($event_id));
        $faqs = [];
        foreach ($rows as $row) {
            $a = self::faq_text($row['a'], $d);
            if (null !== $a && '' !== trim(wp_strip_all_tags($a))) {
                $faqs[] = ['q' => strtr($row['q'], ['{course}' => $title, '{dates}' => $d['when'], '{venue}' => $d['venue'], '{ce_credits}' => $d['ce']]), 'a' => $a];
            }
        }
        $d['faqs'] = $faqs;

        return self::$data[$event_id] = apply_filters('gpsc_course_page_data', $d, $event_id);
    }

    /** Sections in page order that have content: anchor => nav label. */
    public static function sections(array $d) {
        $s = [];
        if ('' !== $d['overview'] || $d['format'] || $d['audience'] || $d['included']) {
            $s['overview'] = __('Overview', 'gps-courses');
        }
        if ($d['objectives']) {
            $s['learn'] = __("What you'll learn", 'gps-courses');
        }
        if ($d['agenda']) {
            $s['agenda'] = __('Agenda', 'gps-courses');
        }
        if ($d['speakers']) {
            $s['instructor'] = _n('Instructor', 'Instructors', count($d['speakers']), 'gps-courses');
        }
        if ('' !== $d['venue'] || '' !== $d['address'] || '' !== $d['city']) {
            $s['location'] = __('Location', 'gps-courses');
        }
        if ($d['faqs']) {
            $s['faq'] = __('FAQ', 'gps-courses');
        }
        return $s;
    }

    /* ============================================================
     * Markup helpers (used by the template)
     * ============================================================ */

    /** Inline stroke icons (fixed markup, no user input). */
    public static function icon($name, $size = 22) {
        $paths = [
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'pin'      => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
            'award'    => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/>',
            'people'   => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c.8-3.4 3.2-5 6-5s5.2 1.6 6 5M16 4.5a3.5 3.5 0 0 1 0 7M18.5 20c-.3-1.8-1.1-3.1-2.2-4"/>',
            'check'    => '<circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/>',
            'door'     => '<path d="M14 4h5v16h-5M10 8l4 4-4 4M14 12H3"/>',
            'cup'      => '<path d="M4 9h13v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9zM17 11h1.5a2.5 2.5 0 0 1 0 5H17M8 3v3M12 3v3"/>',
            'utensils' => '<path d="M7 3v8M5 3v5a2 2 0 0 0 4 0V3M7 11v10M17 3c-2 2-3 4-3 7h3v11"/>',
            'lock'     => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            'share'    => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
            'plus-cal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M12 13v5M9.5 15.5h5"/>',
            'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'map'      => '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14"/>',
            'minus'    => '<path d="M5 12h14"/>',
            'plus'     => '<path d="M12 5v14M5 12h14"/>',
            'chevron'  => '<path d="m6 9 6 6 6-6"/>',
            'link'     => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        ];
        if (!isset($paths[$name])) {
            return '';
        }
        return '<svg class="gps-cp-icon gps-cp-icon--' . esc_attr($name) . '" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }

    /** Price for display ("$2,500"), whole dollars without cents. */
    public static function price($amount) {
        if (null === $amount) {
            return '';
        }
        if ($amount <= 0) {
            return esc_html__('Free', 'gps-courses');
        }
        $decimals = floor($amount) == $amount ? 0 : 2;
        if (function_exists('wc_price')) {
            return wc_price($amount, ['decimals' => $decimals]);
        }
        return esc_html('$' . number_format_i18n($amount, $decimals));
    }

    /** Calendar links for the course (times in the site timezone, as UTC). */
    public static function calendar_links(array $d) {
        if (!$d['start_dt']) {
            return [];
        }
        $tz    = $d['start_dt']->getTimezone();
        $t_end = (string) get_post_meta($d['id'], '_gps_end_time', true);
        $first = clone $d['start_dt'];
        try {
            $last = new \DateTime(($d['end'] ?: $d['start']) . ' ' . (preg_match('/^\d{1,2}:\d{2}/', $t_end) ? substr($t_end, 0, 5) : '23:59'), $tz);
        } catch (\Exception $e) {
            $last = clone $first;
        }
        if ($last <= $first) {
            $last = (clone $first)->modify('+1 hour');
        }
        $utc   = new \DateTimeZone('UTC');
        $fmt   = function ($dt) use ($utc) { $c = clone $dt; $c->setTimezone($utc); return $c->format('Ymd\THis\Z'); };
        $iso   = function ($dt) use ($utc) { $c = clone $dt; $c->setTimezone($utc); return $c->format('Y-m-d\TH:i:s\Z'); };
        $title = $d['title'];
        $where = implode(', ', array_filter([$d['venue'], $d['address'], $d['line2']]));
        $desc  = trim(($d['tagline'] ? $d['tagline'] . "\n\n" : '') . $d['url']);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//GPS Courses//Course//EN', 'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:gps-course-' . (int) $d['id'] . '@' . wp_parse_url(home_url('/'), PHP_URL_HOST),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $fmt($first),
            'DTEND:' . $fmt($last),
            'SUMMARY:' . self::ics_escape($title),
            'DESCRIPTION:' . self::ics_escape($desc),
            'LOCATION:' . self::ics_escape($where),
            'URL:' . $d['url'],
            'END:VEVENT', 'END:VCALENDAR',
        ]);

        return [
            'google'  => ['label' => __('Google Calendar', 'gps-courses'), 'url' => 'https://calendar.google.com/calendar/render?' . http_build_query(['action' => 'TEMPLATE', 'text' => $title, 'dates' => $fmt($first) . '/' . $fmt($last), 'details' => $desc, 'location' => $where], '', '&', PHP_QUERY_RFC3986)],
            'outlook' => ['label' => __('Outlook.com', 'gps-courses'), 'url' => 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query(['path' => '/calendar/action/compose', 'rru' => 'addevent', 'startdt' => $iso($first), 'enddt' => $iso($last), 'subject' => $title, 'body' => $desc, 'location' => $where], '', '&', PHP_QUERY_RFC3986)],
            'office'  => ['label' => __('Office 365', 'gps-courses'), 'url' => 'https://outlook.office.com/calendar/0/deeplink/compose?' . http_build_query(['path' => '/calendar/action/compose', 'rru' => 'addevent', 'startdt' => $iso($first), 'enddt' => $iso($last), 'subject' => $title, 'body' => $desc, 'location' => $where], '', '&', PHP_QUERY_RFC3986)],
            'yahoo'   => ['label' => __('Yahoo Calendar', 'gps-courses'), 'url' => 'https://calendar.yahoo.com/?' . http_build_query(['v' => 60, 'title' => $title, 'st' => $fmt($first), 'et' => $fmt($last), 'desc' => $desc, 'in_loc' => $where], '', '&', PHP_QUERY_RFC3986)],
            'ics'     => ['label' => __('Apple / Outlook (.ics)', 'gps-courses'), 'url' => 'data:text/calendar;charset=utf-8,' . rawurlencode($ics), 'download' => sanitize_title($title) . '.ics'],
        ];
    }

    protected static function ics_escape($text) {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], (string) $text);
    }

    /** Share links (same networks as the Share Course widget). */
    public static function share_links(array $d) {
        $url   = rawurlencode($d['url']);
        $title = rawurlencode($d['title']);
        return [
            'email'    => ['label' => __('Email', 'gps-courses'), 'url' => 'mailto:?subject=' . $title . '&body=' . $url],
            'linkedin' => ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url],
            'facebook' => ['label' => 'Facebook', 'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url],
            'x'        => ['label' => 'X', 'url' => 'https://x.com/intent/post?url=' . $url . '&text=' . $title],
            'whatsapp' => ['label' => 'WhatsApp', 'url' => 'https://wa.me/?text=' . $title . '%20' . $url],
        ];
    }

    /**
     * Registration card body for courses on sale (open / sold out): tickets,
     * quantity, add to cart (posts to the cart like the Ticket Selector
     * widget) and the sold-out waitlist.
     */
    public static function render_tickets(array $d) {
        $cart  = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
        $buy   = $d['buyable'];
        $multi = count($buy) > 1;
        $user  = is_user_logged_in() ? wp_get_current_user() : null;

        if ($buy) {
            echo '<form class="gps-cp-buy" method="post" action="' . esc_url($cart) . '">';
            echo '<input type="hidden" name="gps_cp_add" value="1">';
            echo '<div class="gps-cp-buy__box">';
            if ($multi) {
                echo '<fieldset class="gps-cp-tickets"><legend class="gps-sr">' . esc_html__('Choose a ticket', 'gps-courses') . '</legend>';
            } else {
                echo '<div class="gps-cp-tickets">';
            }
            foreach ($buy as $i => $t) {
                $max = null === $t['available'] ? 10 : max(1, min(10, $t['available']));
                echo '<div class="gps-cp-ticket" data-max="' . (int) $max . '">';
                echo '<div class="gps-cp-ticket__row">';
                if ($multi) {
                    printf(
                        '<label class="gps-cp-ticket__name"><input type="radio" name="add-to-cart" value="%d"%s> %s</label>',
                        (int) $t['product'],
                        0 === $i ? ' checked' : '',
                        esc_html($t['title'])
                    );
                } else {
                    echo '<input type="hidden" name="add-to-cart" value="' . (int) $t['product'] . '">';
                    echo '<span class="gps-cp-ticket__name">' . esc_html($t['title']) . '</span>';
                }
                echo '<span class="gps-cp-ticket__price">' . self::price($t['price']) . '</span>'; // wc_price() output
                echo '</div>';
                if (null !== $t['available']) {
                    /* translators: %d: seats left */
                    echo '<p class="gps-cp-ticket__seats">' . esc_html(sprintf(_n('%d seat left', '%d seats left', $t['available'], 'gps-courses'), $t['available'])) . '</p>';
                }
                echo '</div>';
            }
            echo $multi ? '</fieldset>' : '</div>';

            $first_max = null === $buy[0]['available'] ? 10 : max(1, min(10, $buy[0]['available']));
            echo '<div class="gps-cp-qty">';
            echo '<label for="gps-cp-qty-' . (int) $d['id'] . '">' . esc_html__('Qty', 'gps-courses') . '</label>';
            echo '<div class="gps-cp-qty__box">';
            echo '<button type="button" class="gps-cp-qty__btn" data-step="-1" aria-label="' . esc_attr__('Decrease quantity', 'gps-courses') . '">' . self::icon('minus', 18) . '</button>';
            echo '<input id="gps-cp-qty-' . (int) $d['id'] . '" class="gps-cp-qty__input" type="number" name="quantity" value="1" min="1" max="' . (int) $first_max . '" inputmode="numeric">';
            echo '<button type="button" class="gps-cp-qty__btn" data-step="1" aria-label="' . esc_attr__('Increase quantity', 'gps-courses') . '">' . self::icon('plus', 18) . '</button>';
            echo '</div></div>';
            echo '</div>'; // .gps-cp-buy__box
            echo '<button type="submit" class="gps-cp-btn gps-cp-btn--primary gps-cp-buy__submit">' . esc_html($d['cta']) . '</button>';
            echo '</form>';
        }

        // Sold-out tickets: join the waitlist (same AJAX action as the Ticket Selector widget)
        foreach ($d['tickets'] as $t) {
            if (!$t['sold_out']) {
                continue;
            }
            $uid = 'gps-cp-wl-' . (int) $t['id'];
            echo '<form method="post" class="gps-waitlist-form gps-cp-waitlist" data-ticket-id="' . (int) $t['id'] . '" data-event-id="' . (int) $d['id'] . '" data-nonce="' . esc_attr(wp_create_nonce('gps_waitlist')) . '" aria-labelledby="' . esc_attr($uid) . '-h" novalidate>';
            echo '<div class="gps-cp-ticket gps-cp-ticket--sold"><div class="gps-cp-ticket__row"><span class="gps-cp-ticket__name">' . esc_html($t['title']) . '</span><span class="gps-chip gps-chip--sold_out">' . esc_html__('Sold out', 'gps-courses') . '</span></div></div>';
            echo '<p class="gps-cp-waitlist__title" id="' . esc_attr($uid) . '-h">' . esc_html__('Join the waitlist and we will email you if a seat opens.', 'gps-courses') . '</p>';
            echo '<div class="gps-waitlist-fields">';
            echo '<div class="gps-waitlist-row">';
            echo '<label class="gps-sr" for="' . esc_attr($uid) . '-f">' . esc_html__('First name', 'gps-courses') . '</label>';
            echo '<input type="text" id="' . esc_attr($uid) . '-f" name="first_name" class="gps-waitlist-input" autocomplete="given-name" placeholder="' . esc_attr__('First name', 'gps-courses') . '" value="' . ($user ? esc_attr($user->first_name) : '') . '">';
            echo '<label class="gps-sr" for="' . esc_attr($uid) . '-l">' . esc_html__('Last name', 'gps-courses') . '</label>';
            echo '<input type="text" id="' . esc_attr($uid) . '-l" name="last_name" class="gps-waitlist-input" autocomplete="family-name" placeholder="' . esc_attr__('Last name', 'gps-courses') . '" value="' . ($user ? esc_attr($user->last_name) : '') . '">';
            echo '</div>';
            echo '<label class="gps-sr" for="' . esc_attr($uid) . '-e">' . esc_html__('Email address (required)', 'gps-courses') . '</label>';
            echo '<input type="email" id="' . esc_attr($uid) . '-e" name="email" class="gps-waitlist-input" autocomplete="email" required aria-required="true" placeholder="' . esc_attr__('Email address *', 'gps-courses') . '" value="' . ($user ? esc_attr($user->user_email) : '') . '">';
            echo '<label class="gps-sr" for="' . esc_attr($uid) . '-p">' . esc_html__('Phone (optional)', 'gps-courses') . '</label>';
            echo '<input type="tel" id="' . esc_attr($uid) . '-p" name="phone" class="gps-waitlist-input" autocomplete="tel" placeholder="' . esc_attr__('Phone (optional)', 'gps-courses') . '">';
            echo '<button type="submit" class="gps-waitlist-submit">' . esc_html__('Join the waitlist', 'gps-courses') . '</button>';
            echo '</div>';
            echo '<div class="gps-waitlist-response" role="status" aria-live="polite" hidden></div>';
            echo '</form>';
        }
    }

    /* ============================================================
     * SEO
     * ============================================================ */

    /** Yoast description for courses without one: tagline, else the summary (no dates). */
    public static function meta_description($desc) {
        if ('' !== trim((string) $desc) || !self::is_active()) {
            return $desc;
        }
        $id   = (int) get_queried_object_id();
        $text = trim((string) get_post_meta($id, self::META_TAGLINE, true));
        if ('' === $text && class_exists(__NAMESPACE__ . '\\Course_Catalog')) {
            $text = Course_Catalog::summary(get_post($id));
        }
        return '' !== $text ? wp_trim_words(wp_strip_all_tags($text), 30, '…') : $desc;
    }

    /**
     * Course (+ instructors, FAQPage) schema nodes, without @context. AI_Search
     * puts them in Yoast's @graph (Yoast already outputs WebPage, BreadcrumbList
     * and the Organization); json_ld() prints them on their own otherwise.
     */
    public static function schema_nodes(array $d) {
        $text = function ($s) {
            return trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags((string) $s), ENT_QUOTES, 'UTF-8')));
        };
        $site = $text(get_bloginfo('name'));
        $tz   = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $date = function ($ymd, $time) use ($tz) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $ymd)) {
                return '';
            }
            if (!preg_match('/^\d{1,2}:\d{2}/', (string) $time)) {
                return $ymd;
            }
            try {
                return (new \DateTime($ymd . ' ' . substr($time, 0, 5), $tz))->format('c');
            } catch (\Exception $e) {
                return $ymd;
            }
        };

        $description = $d['tagline'] ?: (class_exists(__NAMESPACE__ . '\\Course_Catalog') ? Course_Catalog::summary(get_post($d['id'])) : '');
        $course = [
            '@type'       => 'Course',
            '@id'         => $d['url'] . '#course',
            'name'        => $text($d['title']),
            'description' => $text($description ?: $d['title']),
            'url'         => $d['url'],
            'mainEntityOfPage' => ['@id' => $d['url']],
            'provider'    => AI_Search::org_ref(),
            'inLanguage'  => 'en',
        ];
        if ($d['image']) {
            $img = wp_get_attachment_image_url($d['image'], 'full');
            if ($img) {
                $course['image'] = $img;
            }
        }
        if ('' !== $d['ce']) {
            /* translators: %s: number of CE credits */
            $course['educationalCredentialAwarded'] = sprintf(__('%s CE credits', 'gps-courses'), $d['ce']);
            if (is_numeric($d['ce'])) {
                $course['numberOfCredits'] = ['@type' => 'QuantitativeValue', 'value' => (float) $d['ce'], 'unitText' => 'CE credits'];
            }
        }
        if ($d['objectives']) {
            $course['teaches'] = array_map($text, $d['objectives']);
        }
        if ('' !== $d['prereq']) {
            $course['coursePrerequisites'] = $text($d['prereq']);
        }
        if ('' !== $d['audience']) {
            $course['audience'] = ['@type' => 'Audience', 'audienceType' => $text($d['audience'])];
        }

        $instance = ['@type' => 'CourseInstance', 'courseMode' => 'Onsite'];
        if ($d['start']) {
            $instance['startDate'] = $date($d['start'], get_post_meta($d['id'], '_gps_start_time', true));
            $instance['endDate']   = $date($d['end'] ?: $d['start'], get_post_meta($d['id'], '_gps_end_time', true));
        }
        if ($d['venue'] || $d['address'] || $d['city']) {
            $postal = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $text($d['address']),
                'addressLocality' => $text($d['city_only']),
                'addressRegion'   => $text($d['state']),
                'postalCode'      => $text($d['zip']),
                'addressCountry'  => $text($d['country']),
            ]);
            $instance['location'] = ['@type' => 'Place', 'name' => $text($d['venue'] ?: $site), 'address' => $postal];
        }
        // Instructors: their own Person nodes, referenced by @id
        $people  = [];
        $persons = [];
        foreach ($d['speakers'] as $s) {
            $persons[] = AI_Search::person_node($s);
            $people[]  = ['@id' => AI_Search::person_id($s['id'])];
        }
        if (!$people && $d['instructor']) {
            $people[] = ['@type' => 'Person', 'name' => $text($d['instructor']['full'])];
        }
        if ($people) {
            $instance['instructor'] = 1 === count($people) ? $people[0] : $people;
        }

        // Offer while registration is open or full; not for past / running courses
        $offer = null;
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
        if (in_array($d['stage'], ['open', 'sold_out'], true) && $d['tickets']) {
            $pool  = $d['buyable'] ?: $d['tickets'];
            $price = null;
            $from  = '';
            foreach ($pool as $t) {
                if (null !== $t['price'] && (null === $price || $t['price'] < $price)) {
                    $price = $t['price'];
                    $from  = $t['from'];
                }
            }
            if (null !== $price) {
                $availability = 'sold_out' === $d['stage'] ? 'SoldOut' : ((null !== $d['seats'] && $d['seats'] <= self::FEW_SEATS) ? 'LimitedAvailability' : 'InStock');
                $offer = [
                    '@type'         => 'Offer',
                    'price'         => $price,
                    'priceCurrency' => $currency,
                    'availability'  => 'https://schema.org/' . $availability,
                    'url'           => $d['url'],
                    'category'      => $price > 0 ? 'Paid' : 'Free',
                ];
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $from)) {
                    $offer['validFrom'] = $date(substr($from, 0, 10), substr($from, 11, 5));
                }
            }
        }
        if ($offer) {
            $instance['offers'] = $offer;
            $course['offers']   = $offer;
            $course['isAccessibleForFree'] = $offer['price'] <= 0;
        }
        $course['hasCourseInstance'] = $instance;

        $graph = [apply_filters('gpsc_course_json_ld', $course, $d)];
        if ($d['faqs']) {
            $graph[] = [
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
        return array_merge($graph, $persons);
    }

    /** The schema nodes as standalone scripts (no Yoast). */
    public static function json_ld(array $d) {
        return AI_Search::scripts(self::schema_nodes($d));
    }

    public static function print_json_ld() {
        if (!self::is_active() || AI_Search::merged()) {
            return; // inside Yoast's graph already
        }
        echo self::json_ld(self::data((int) get_queried_object_id())); // JSON encoded with JSON_HEX_TAG
    }
}
