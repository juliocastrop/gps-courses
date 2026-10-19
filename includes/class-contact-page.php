<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Contact page served by the plugin (templates/page-contact.php) instead of
 * its Elementor document, in the course page design. The form stays the
 * Fluent Forms contact form (shared look from gps-forms.css); email and
 * address come from the existing settings (Course page › Contact email,
 * Seminar page › Location).
 *
 * Reversible: GPS Courses › Settings › Course page › "Contact page" =
 * Elementor (default) | GPS template; editors can preview with
 * ?gps_contact_template=1 / =0 (never cached).
 */
class Contact_Page {

    const QUERY_ARG    = 'gps_contact_template';
    const OPT_TEMPLATE = 'gps_contact_template';  // elementor | gps
    const OPT_FORM     = 'gps_contact_form_id';   // Fluent Forms id
    const OPT_PAGE     = 'gps_contact_page_id';   // 0 = the page with slug "contact"
    const DEFAULT_FORM = 3;

    /** @var bool|null */
    protected static $use = null;

    public static function init() {
        add_filter('template_include', [__CLASS__, 'template'], 99);
        add_action('template_redirect', [__CLASS__, 'no_cache_preview'], 1);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 110);
        add_action('wp_enqueue_scripts', [__CLASS__, 'skip_elementor_post_css'], 999);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('gpsc_course_page_settings_rows', [__CLASS__, 'settings_rows']);
        // Fluent Forms prints its styles in <head> only when it sees the form in the
        // post content (Elementor's widget did that before): ask for them here
        add_filter('fluentform/load_styles', function ($load) { return $load || self::is_active(); });
        // The page shows the contact email and the location: refresh it when they change
        foreach ([self::OPT_TEMPLATE, self::OPT_FORM, 'gps_contact_email', 'gps_seminar_location'] as $opt) {
            add_action('update_option_' . $opt, [__CLASS__, 'purge']);
            add_action('add_option_' . $opt, [__CLASS__, 'purge']);
        }
    }

    /* ============================================================
     * Routing
     * ============================================================ */

    public static function page_id() {
        $id = (int) get_option(self::OPT_PAGE, 0);
        if ($id && 'page' === get_post_type($id)) {
            return $id;
        }
        $page = get_page_by_path('contact', OBJECT, 'page');
        return ($page && 'publish' === $page->post_status) ? (int) $page->ID : 0;
    }

    public static function site_mode() {
        return 'gps' === get_option(self::OPT_TEMPLATE, 'elementor') ? 'gps' : 'elementor';
    }

    public static function preview_flag() {
        if (!isset($_GET[self::QUERY_ARG])) {
            return null;
        }
        $id = self::page_id();
        if (!$id || !current_user_can('edit_post', $id)) {
            return null;
        }
        return '0' !== sanitize_key(wp_unslash($_GET[self::QUERY_ARG]));
    }

    public static function is_active() {
        if (null !== self::$use) {
            return self::$use;
        }
        $id = self::page_id();
        if (!$id || !is_page($id) || is_embed() || post_password_required($id)) {
            return self::$use = false;
        }
        if (isset($_GET['elementor-preview']) || (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->preview) && \Elementor\Plugin::$instance->preview->is_preview_mode())) {
            return self::$use = false;
        }
        $preview = self::preview_flag();
        $use     = null !== $preview ? $preview : 'gps' === self::site_mode();
        return self::$use = (bool) apply_filters('gpsc_contact_page_template', $use, $id);
    }

    public static function template($template) {
        if (!self::is_active()) {
            return $template;
        }
        $ours = GPSC_PATH . 'templates/page-contact.php';
        return file_exists($ours) ? $ours : $template;
    }

    public static function no_cache_preview() {
        if (null === self::preview_flag() || !is_page(self::page_id())) {
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
            $classes[] = 'gps-contact-page';
        }
        return $classes;
    }

    public static function skip_elementor_post_css() {
        if (self::is_active()) {
            wp_dequeue_style('elementor-post-' . self::page_id());
        }
    }

    public static function enqueue_assets() {
        if (!self::is_active()) {
            return;
        }
        $deps = [];
        if (wp_style_is('gps-forms', 'registered') || wp_style_is('gps-forms', 'enqueued')) {
            $deps[] = 'gps-forms';
        }
        $course = GPSC_PATH . 'assets/css/gps-course.css';
        $css    = GPSC_PATH . 'assets/css/gps-contact.css';
        $js     = GPSC_PATH . 'assets/js/gps-contact.js';
        wp_enqueue_style('gps-course', GPSC_URL . 'assets/css/gps-course.css', $deps, file_exists($course) ? (string) filemtime($course) : GPSC_VERSION);
        wp_enqueue_style('gps-contact', GPSC_URL . 'assets/css/gps-contact.css', ['gps-course'], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        wp_enqueue_script('gps-contact', GPSC_URL . 'assets/js/gps-contact.js', [], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        foreach (['fluent-form-styles', 'fluentform-public-default'] as $handle) {
            if (wp_style_is($handle, 'registered')) {
                wp_enqueue_style($handle);
            }
        }
    }

    public static function purge() {
        $id  = self::page_id();
        $url = $id ? get_permalink($id) : '';
        if ($url && is_callable(['\\FlyingPress\\Purge', 'purge_url'])) {
            call_user_func(['\\FlyingPress\\Purge', 'purge_url'], $url);
        }
    }

    /* ============================================================
     * Settings (rows in Settings › Course page)
     * ============================================================ */

    public static function register_settings() {
        register_setting('gps_course_page_settings', self::OPT_TEMPLATE, [
            'type' => 'string', 'default' => 'elementor',
            'sanitize_callback' => function ($v) { return 'gps' === $v ? 'gps' : 'elementor'; },
        ]);
        register_setting('gps_course_page_settings', self::OPT_FORM, [
            'type' => 'integer', 'default' => self::DEFAULT_FORM,
            'sanitize_callback' => function ($v) { $v = absint($v); return $v ?: self::DEFAULT_FORM; },
        ]);
    }

    public static function settings_rows() {
        $mode = self::site_mode();
        $id   = self::page_id();
        ?>
        <tr>
            <th scope="row"><label for="gps-contact-template"><?php esc_html_e('Contact page', 'gps-courses'); ?></label></th>
            <td>
                <select id="gps-contact-template" name="<?php echo esc_attr(self::OPT_TEMPLATE); ?>">
                    <option value="elementor" <?php selected($mode, 'elementor'); ?>><?php esc_html_e('Elementor (current page)', 'gps-courses'); ?></option>
                    <option value="gps" <?php selected($mode, 'gps'); ?>><?php esc_html_e('GPS template (course page design)', 'gps-courses'); ?></option>
                </select>
                <label style="margin-left:12px"><?php esc_html_e('Fluent Forms form ID', 'gps-courses'); ?>
                    <input type="number" min="1" name="<?php echo esc_attr(self::OPT_FORM); ?>" value="<?php echo (int) get_option(self::OPT_FORM, self::DEFAULT_FORM); ?>" class="small-text">
                </label>
                <p class="description">
                    <?php esc_html_e('Email and address come from "Contact email" above and Settings › Seminar page › Location.', 'gps-courses'); ?>
                    <?php if ($id) : ?>
                        <a href="<?php echo esc_url(add_query_arg(self::QUERY_ARG, '1', get_permalink($id))); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview the GPS template', 'gps-courses'); ?></a>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /* ============================================================
     * Data
     * ============================================================ */

    public static function data() {
        $loc = class_exists(__NAMESPACE__ . '\\Seminar_Page')
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) get_option(Seminar_Page::OPT_LOCATION, Seminar_Page::default_location())))))
            : ['GPS Dental Training', '6320 Sugarloaf Parkway', 'Duluth, GA 30097'];
        $venue    = isset($loc[0]) ? $loc[0] : '';
        $street   = isset($loc[1]) ? $loc[1] : '';
        $cityline = isset($loc[2]) ? $loc[2] : '';
        $city     = trim(preg_replace('/\s+\d{5}(-\d{4})?$/', '', $cityline));

        // Quick links: only the ones that exist
        $links   = [];
        $archive = get_post_type_archive_link('gps_event');
        if ($archive) {
            $links[] = ['label' => __('Upcoming courses', 'gps-courses'), 'url' => $archive];
        }
        $seminar = get_posts([
            'post_type'   => 'product',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields'      => 'ids',
            'meta_query'  => [['key' => '_gps_seminar_id', 'compare' => 'EXISTS']],
        ]);
        if ($seminar) {
            $links[] = ['label' => __('Monthly seminars', 'gps-courses'), 'url' => get_permalink($seminar[0])];
        }
        if (function_exists('wc_get_account_endpoint_url')) {
            $links[] = ['label' => __('My tickets & CE certificates', 'gps-courses'), 'url' => wc_get_account_endpoint_url('gps-tickets')];
        }
        $sponsor = get_page_by_path('gps-sponsorship', OBJECT, 'product');
        if ($sponsor && 'publish' === $sponsor->post_status) {
            $links[] = ['label' => __('Sponsor a GPS course', 'gps-courses'), 'url' => get_permalink($sponsor)];
        }

        $page = self::page_id();
        return apply_filters('gpsc_contact_page_data', [
            'title'    => __('Contact GPS Dental Training', 'gps-courses'),
            'gold'     => __("We'd like to hear from you", 'gps-courses'),
            'tagline'  => __('Questions about a course, the monthly seminars, CE credits or sponsorship? Send us a message and our team will get back to you.', 'gps-courses'),
            'email'    => class_exists(__NAMESPACE__ . '\\Course_Page') ? Course_Page::contact_email() : get_option('admin_email'),
            'venue'    => $venue,
            'street'   => $street,
            'cityline' => $cityline,
            'city'     => $city,
            'map_q'    => implode(', ', array_filter([$street, $cityline])) ?: $venue,
            'form_id'  => (int) get_option(self::OPT_FORM, self::DEFAULT_FORM),
            'links'    => apply_filters('gpsc_contact_links', $links),
            'image'    => $page ? (int) get_post_thumbnail_id($page) : 0,
            'gia'      => defined('WCRP_VERSION'), // GIA (WC Cart Recovery Pro) chat on the site
        ]);
    }
}
