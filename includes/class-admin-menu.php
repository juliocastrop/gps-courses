<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * The "GPS Courses" admin menu, organized in 9 hubs with tabs.
 *
 * Nothing is unregistered or renamed: every screen keeps its slug, URL, hook
 * suffix (gps-courses_page_*) and capability. This class only
 *   - rebuilds the submenu to one entry per hub (admin_menu, priority 999,
 *     after every page registers),
 *   - prints the hub's tab bar on each of its screens,
 *   - keeps the menu highlight and the page <title> right on screens that
 *     no longer have their own menu entry.
 *
 * A new admin screen must be added to hubs() or it won't be listed.
 */
class Admin_Menu {

    const PARENT    = 'gps-dashboard';
    const OPT_DEBUG = 'gps_show_debug_tools';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'rebuild'], 999);
        add_filter('parent_file', [__CLASS__, 'parent_file']);
        add_filter('submenu_file', [__CLASS__, 'submenu_file'], 10, 2);
        add_action('current_screen', [__CLASS__, 'page_title']);
        add_action('all_admin_notices', [__CLASS__, 'tabs'], 1);
        add_action('admin_head', [__CLASS__, 'styles']);
    }

    /* ============================================================
     * The map
     * ============================================================ */

    /**
     * Hubs in menu order. Each tab: label, url (relative to wp-admin), cap,
     * and how to recognize its screen: 'page' (admin.php?page=…), 'post_type'
     * (list / edit screens) or 'taxonomy'.
     */
    public static function hubs() {
        $debug = (defined('WP_DEBUG') && WP_DEBUG) || get_option(self::OPT_DEBUG);
        $hubs = [
            'courses' => ['label' => __('Courses', 'gps-courses'), 'tabs' => [
                ['label' => __('All Courses', 'gps-courses'), 'url' => 'edit.php?post_type=gps_event', 'cap' => 'edit_posts', 'post_type' => 'gps_event'],
                ['label' => __('Ticket Types', 'gps-courses'), 'url' => 'edit.php?post_type=gps_ticket', 'cap' => 'edit_posts', 'post_type' => 'gps_ticket'],
                ['label' => __('Agendas', 'gps-courses'), 'url' => 'edit.php?post_type=gps_schedule', 'cap' => 'edit_posts', 'post_type' => 'gps_schedule'],
                ['label' => __('Speakers', 'gps-courses'), 'url' => 'edit.php?post_type=gps_speaker', 'cap' => 'edit_posts', 'post_type' => 'gps_speaker'],
                ['label' => __('Sponsors', 'gps-courses'), 'url' => 'edit.php?post_type=gps_sponsor', 'cap' => 'edit_posts', 'post_type' => 'gps_sponsor'],
                ['label' => __('Categories', 'gps-courses'), 'url' => 'edit-tags.php?taxonomy=gps_event_category&post_type=gps_event', 'cap' => 'manage_categories', 'taxonomy' => 'gps_event_category'],
                ['label' => __('Tags', 'gps-courses'), 'url' => 'edit-tags.php?taxonomy=gps_event_tag&post_type=gps_event', 'cap' => 'manage_categories', 'taxonomy' => 'gps_event_tag'],
                ['label' => __('Programs', 'gps-courses'), 'url' => 'edit-tags.php?taxonomy=gps_course_program&post_type=gps_event', 'cap' => 'manage_categories', 'taxonomy' => 'gps_course_program'],
            ]],
            'seminars' => ['label' => __('Seminars', 'gps-courses'), 'tabs' => [
                ['label' => __('Overview', 'gps-courses'), 'url' => 'admin.php?page=gps-seminars', 'cap' => 'manage_options', 'page' => 'gps-seminars'],
                ['label' => __('All Seminars', 'gps-courses'), 'url' => 'edit.php?post_type=gps_seminar', 'cap' => 'edit_posts', 'post_type' => 'gps_seminar'],
                ['label' => __('Registrants', 'gps-courses'), 'url' => 'admin.php?page=gps-seminar-registrants', 'cap' => 'manage_options', 'page' => 'gps-seminar-registrants'],
                ['label' => __('Waitlist', 'gps-courses'), 'url' => 'admin.php?page=gps-seminar-waitlist', 'cap' => 'manage_options', 'page' => 'gps-seminar-waitlist'],
                ['label' => __('Reports', 'gps-courses'), 'url' => 'admin.php?page=gps-seminar-reports', 'cap' => 'manage_options', 'page' => 'gps-seminar-reports'],
            ]],
            'tickets' => ['label' => __('Tickets & Waitlist', 'gps-courses'), 'tabs' => [
                ['label' => __('Purchased Tickets', 'gps-courses'), 'url' => 'admin.php?page=gps-purchased-tickets', 'cap' => 'manage_options', 'page' => 'gps-purchased-tickets'],
                ['label' => __('Waitlist & Interest', 'gps-courses'), 'url' => 'admin.php?page=gps-waitlist', 'cap' => 'manage_options', 'page' => 'gps-waitlist'],
            ]],
            'checkin' => ['label' => __('Check-in', 'gps-courses'), 'tabs' => [
                ['label' => __('Course Scanner', 'gps-courses'), 'url' => 'admin.php?page=gps-attendance', 'cap' => 'manage_options', 'page' => 'gps-attendance'],
                ['label' => __('Seminar Sessions', 'gps-courses'), 'url' => 'admin.php?page=gps-seminar-attendance', 'cap' => 'manage_options', 'page' => 'gps-seminar-attendance'],
                ['label' => __('Attendance Report', 'gps-courses'), 'url' => 'admin.php?page=gps-attendance-report', 'cap' => 'manage_options', 'page' => 'gps-attendance-report'],
            ]],
            'certificates' => ['label' => __('Certificates', 'gps-courses'), 'tabs' => [
                ['label' => __('Courses', 'gps-courses'), 'url' => 'admin.php?page=gps-certificates', 'cap' => 'manage_options', 'page' => 'gps-certificates'],
                ['label' => __('Seminars', 'gps-courses'), 'url' => 'admin.php?page=gps-seminar-certificates', 'cap' => 'manage_options', 'page' => 'gps-seminar-certificates'],
                ['label' => __('Design', 'gps-courses'), 'url' => 'admin.php?page=gps-certificate-settings', 'cap' => 'manage_options', 'page' => 'gps-certificate-settings'],
            ]],
            'emails' => ['label' => __('Emails', 'gps-courses'), 'tabs' => [
                ['label' => __('Branding & Messages', 'gps-courses'), 'url' => 'admin.php?page=gps-email-settings', 'cap' => 'manage_options', 'page' => 'gps-email-settings'],
                ['label' => __('Templates', 'gps-courses'), 'url' => 'admin.php?page=gps-email-templates', 'cap' => 'manage_options', 'page' => 'gps-email-templates'],
                ['label' => __('Seminar Notifications', 'gps-courses'), 'url' => 'admin.php?page=gps-seminar-notifications', 'cap' => 'manage_options', 'page' => 'gps-seminar-notifications'],
            ]],
            'reports' => ['label' => __('Reports', 'gps-courses'), 'tabs' => [
                ['label' => __('Courses', 'gps-courses'), 'url' => 'admin.php?page=gps-reports', 'cap' => 'manage_options', 'page' => 'gps-reports'],
                ['label' => __('QR Analytics', 'gps-courses'), 'url' => 'admin.php?page=gps-qr-analytics', 'cap' => 'manage_options', 'page' => 'gps-qr-analytics'],
                ['label' => __('AI Search', 'gps-courses'), 'url' => 'admin.php?page=gps-ai-search', 'cap' => 'edit_pages', 'page' => 'gps-ai-search'],
            ]],
            'tools' => ['label' => __('Tools', 'gps-courses'), 'tabs' => [
                ['label' => __('Orders Diagnostic', 'gps-courses'), 'url' => 'admin.php?page=gps-orders-diagnostic', 'cap' => 'manage_options', 'page' => 'gps-orders-diagnostic'],
                ['label' => __('Registration Guard', 'gps-courses'), 'url' => 'admin.php?page=gps-registration-guard', 'cap' => 'manage_options', 'page' => 'gps-registration-guard'],
                // Destructive buttons (recreate tables): only while debugging
                ['label' => __('Debug Info', 'gps-courses'), 'url' => 'admin.php?page=gps-debug', 'cap' => 'manage_options', 'page' => 'gps-debug', 'hidden' => !$debug],
            ]],
            'settings' => ['label' => __('Settings', 'gps-courses'), 'tabs' => [
                ['label' => __('Settings', 'gps-courses'), 'url' => 'admin.php?page=gps-settings', 'cap' => 'manage_options', 'page' => 'gps-settings'],
            ]],
        ];
        return apply_filters('gpsc_admin_hubs', $hubs);
    }

    /** The submenu slug WordPress uses for a tab's URL. */
    protected static function slug($url) {
        return 0 === strpos($url, 'admin.php?page=') ? substr($url, strlen('admin.php?page=')) : $url;
    }

    /** Hub key and tab index of the current screen, or null. */
    public static function current() {
        $page   = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        foreach (self::hubs() as $key => $hub) {
            foreach ($hub['tabs'] as $n => $tab) {
                if ('' !== $page) {
                    if (isset($tab['page']) && $tab['page'] === $page) {
                        return [$key, $n];
                    }
                    continue;
                }
                if (!$screen) {
                    continue;
                }
                if (isset($tab['taxonomy']) && $screen->taxonomy === $tab['taxonomy']) {
                    return [$key, $n];
                }
                if (isset($tab['post_type']) && '' === (string) $screen->taxonomy && $screen->post_type === $tab['post_type'] && in_array($screen->base, ['edit', 'post'], true)) {
                    return [$key, $n];
                }
            }
        }
        return null;
    }

    /** The first tab of a hub the user can open. */
    protected static function first_tab(array $hub) {
        foreach ($hub['tabs'] as $tab) {
            if (empty($tab['hidden']) && current_user_can($tab['cap'])) {
                return $tab;
            }
        }
        return null;
    }

    /* ============================================================
     * Menu
     * ============================================================ */

    public static function rebuild() {
        global $submenu;
        if (empty($submenu[self::PARENT])) {
            return;
        }
        // Existing entries by slug (keep their capability and page title)
        $existing = [];
        foreach ($submenu[self::PARENT] as $item) {
            $existing[$item[2]] = $item;
        }
        $new = [];
        foreach (self::hubs() as $hub) {
            $tab = self::first_tab($hub);
            if (!$tab) {
                continue;
            }
            $slug = self::slug($tab['url']);
            $item = isset($existing[$slug]) ? $existing[$slug] : [$hub['label'], $tab['cap'], $slug, $tab['label']];
            $item[0] = $hub['label'];
            $new[]   = $item;
        }
        // The top-level link opens the first entry: keep All Courses first
        $submenu[self::PARENT] = $new;
    }

    public static function parent_file($parent) {
        return self::current() ? self::PARENT : $parent;
    }

    public static function submenu_file($submenu_file, $parent = '') {
        $cur = self::current();
        if (!$cur) {
            return $submenu_file;
        }
        $hubs = self::hubs();
        $tab  = self::first_tab($hubs[$cur[0]]);
        return $tab ? self::slug($tab['url']) : $submenu_file;
    }

    /** Screens without a menu entry lose their <title>: give it back. */
    public static function page_title() {
        global $title;
        $cur = self::current();
        if (!$cur || !empty($title)) {
            return;
        }
        $hubs  = self::hubs();
        $title = $hubs[$cur[0]]['tabs'][$cur[1]]['label'];
    }

    /* ============================================================
     * Tab bar
     * ============================================================ */

    public static function tabs() {
        $cur = self::current();
        if (!$cur) {
            return;
        }
        $hubs = self::hubs();
        $hub  = $hubs[$cur[0]];
        $tabs = [];
        foreach ($hub['tabs'] as $n => $tab) {
            if ((!empty($tab['hidden']) && $n !== $cur[1]) || !current_user_can($tab['cap'])) {
                continue;
            }
            $tabs[$n] = $tab;
        }
        if (count($tabs) < 2) {
            return; // a hub of one screen (Settings) needs no bar
        }
        echo '<nav class="gps-hub-nav" aria-label="' . esc_attr($hub['label']) . '">';
        echo '<span class="gps-hub-nav__hub">' . esc_html($hub['label']) . '</span>';
        foreach ($tabs as $n => $tab) {
            $on = $n === $cur[1];
            echo '<a href="' . esc_url(admin_url($tab['url'])) . '" class="gps-hub-nav__tab' . ($on ? ' is-current' : '') . '"' . ($on ? ' aria-current="page"' : '') . '>' . esc_html($tab['label']) . '</a>';
        }
        echo '</nav>';
    }

    public static function styles() {
        if (!self::current()) {
            return;
        }
        ?>
        <style id="gps-hub-nav-css">
        .gps-hub-nav{display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin:12px 20px 0 2px;padding:6px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
        .gps-hub-nav__hub{margin:0 10px 0 6px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#50575e}
        .gps-hub-nav__tab{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:6px;font-size:13px;font-weight:600;color:#1d2327;text-decoration:none}
        .gps-hub-nav__tab:hover,.gps-hub-nav__tab:focus{background:#f0f0f1;color:#193463}
        .gps-hub-nav__tab.is-current{background:#193463;color:#fff}
        .gps-hub-nav__tab.is-current:hover,.gps-hub-nav__tab.is-current:focus{background:#12264a;color:#fff}
        </style>
        <?php
    }
}
