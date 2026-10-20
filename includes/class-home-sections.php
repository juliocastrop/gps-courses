<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Home page sections below the hero (Elementor widgets GPS Section Heading,
 * GPS Seminar Promo, GPS Upcoming Courses, GPS Sponsors): shared markup and
 * data, in the design language of /courses/, the course and seminar pages.
 * Styles: assets/css/gps-home.css (+ gps-catalog.css for the course cards),
 * loaded only where one of these widgets renders.
 */
class Home_Sections {

    public static function init() {
        // Handles must exist before Elementor resolves the widgets' depends
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets'], 5);
        add_action('elementor/frontend/after_register_styles', [__CLASS__, 'register_assets']);
    }

    public static function register_assets() {
        if (!wp_style_is('gps-catalog', 'registered')) {
            $cat = GPSC_PATH . 'assets/css/gps-catalog.css';
            // Same dependency as Course_Catalog's own registration (fonts / tokens of the forms CSS)
            $deps = wp_style_is('gps-forms', 'registered') ? ['gps-forms'] : [];
            wp_register_style('gps-catalog', GPSC_URL . 'assets/css/gps-catalog.css', $deps, file_exists($cat) ? (string) filemtime($cat) : GPSC_VERSION);
        }
        if (!wp_style_is('gps-home', 'registered')) {
            $css = GPSC_PATH . 'assets/css/gps-home.css';
            wp_register_style('gps-home', GPSC_URL . 'assets/css/gps-home.css', [], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        }
    }

    /* ============================================================
     * Section header
     * ============================================================ */

    /** Allowed heading tags for the section titles. */
    public static function tag($tag, $default = 'h2') {
        return in_array($tag, ['h1', 'h2', 'h3', 'h4', 'p', 'div'], true) ? $tag : $default;
    }

    /**
     * Eyebrow + title + optional right-aligned link.
     *
     * @param array $a eyebrow, title, tag, align (left|center), link_label,
     *                 link_url, external (bool), nofollow (bool)
     */
    public static function head(array $a) {
        $eyebrow = trim((string) ($a['eyebrow'] ?? ''));
        $title   = trim((string) ($a['title'] ?? ''));
        $label   = trim((string) ($a['link_label'] ?? ''));
        $url     = trim((string) ($a['link_url'] ?? ''));
        if ('' === $eyebrow && '' === $title && ('' === $label || '' === $url)) {
            return '';
        }
        $tag   = self::tag($a['tag'] ?? 'h2');
        $align = 'center' === ($a['align'] ?? '') ? 'center' : 'left';

        $html = '<div class="gps-home-head gps-home-head--' . $align . '"><div class="gps-home-head__text">';
        if ('' !== $eyebrow) {
            $html .= '<p class="gps-home-eyebrow">' . esc_html($eyebrow) . '</p>';
        }
        if ('' !== $title) {
            $html .= '<' . $tag . ' class="gps-home-title">' . esc_html($title) . '</' . $tag . '>';
        }
        $html .= '</div>';
        if ('' !== $label && '' !== $url) {
            $rel = [];
            if (!empty($a['external'])) {
                $rel[] = 'noopener';
            }
            if (!empty($a['nofollow'])) {
                $rel[] = 'nofollow';
            }
            $html .= '<a class="gps-home-link" href="' . esc_url($url) . '"'
                . (!empty($a['external']) ? ' target="_blank"' : '')
                . ($rel ? ' rel="' . esc_attr(implode(' ', $rel)) . '"' : '')
                . '>' . esc_html($label) . '</a>';
        }
        return $html . '</div>';
    }

    /** Elementor URL control value -> [url, external, nofollow]. */
    public static function link_value($link) {
        if (!is_array($link)) {
            return ['url' => trim((string) $link), 'external' => false, 'nofollow' => false];
        }
        return [
            'url'      => trim((string) ($link['url'] ?? '')),
            'external' => !empty($link['is_external']),
            'nofollow' => !empty($link['nofollow']),
        ];
    }

    /* ============================================================
     * Courses
     * ============================================================ */

    public static function catalog_url() {
        $url = function_exists('get_post_type_archive_link') ? get_post_type_archive_link('gps_event') : '';
        return $url ?: home_url('/courses/');
    }

    /** "PRF: Platelet Rich Fibrin" -> "PRF"; "Pink Ceramics – Hands-on" -> "Pink Ceramics". */
    public static function short_title($title) {
        $title = trim(html_entity_decode(wp_strip_all_tags((string) $title), ENT_QUOTES, 'UTF-8'));
        $parts = preg_split('/\s*:\s*|\s+[-–—|]\s+/u', $title, 2);
        $short = trim((string) $parts[0]);
        return '' !== $short ? $short : $title;
    }

    /**
     * Up to $n past courses, one per short title, most recent first:
     * [title (short), url, image, when, ce].
     */
    public static function past_courses($n = 3) {
        $out  = [];
        $seen = [];
        foreach (Course_Catalog::courses('past', 12) as $c) {
            $short = self::short_title($c['title']);
            $key   = function_exists('mb_strtolower') ? mb_strtolower($short, 'UTF-8') : strtolower($short);
            if ('' === $short || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'title' => html_entity_decode(wp_strip_all_tags((string) $c['title']), ENT_QUOTES, 'UTF-8'),
                'url'   => (string) $c['url'],
                'image' => (string) $c['image'],
                'when'  => (string) $c['when'],
                'ce'    => (float) $c['ce'],
            ];
            if (count($out) >= $n) {
                break;
            }
        }
        return $out;
    }

    /** URL of the PACE logo (white) for the CE credits card. */
    public static function pace_logo_url() {
        $id  = (int) apply_filters('gpsc_home_pace_logo_id', 7120);
        $url = $id ? (string) wp_get_attachment_image_url($id, 'medium') : '';
        return (string) apply_filters('gpsc_home_pace_logo_url', $url);
    }

    /** Up to $n distinct short titles of past courses, most recent first. */
    public static function past_titles($n = 3) {
        $out  = [];
        $seen = [];
        foreach (Course_Catalog::courses('past', 12) as $c) {
            $short = self::short_title($c['title']);
            $key   = function_exists('mb_strtolower') ? mb_strtolower($short, 'UTF-8') : strtolower($short);
            if ('' === $short || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[]      = $short;
            if (count($out) >= $n) {
                break;
            }
        }
        return $out;
    }

    /** ["A","B","C"] -> "A, B and C run again"; [] -> $fallback. */
    public static function runs_again(array $titles, $fallback) {
        $titles = array_values(array_filter(array_map('trim', $titles), 'strlen'));
        if (!$titles) {
            return (string) $fallback;
        }
        if (1 === count($titles)) {
            /* translators: %s: course name */
            return sprintf(__('%s runs again', 'gps-courses'), $titles[0]);
        }
        $last = array_pop($titles);
        /* translators: 1: course names separated by commas, 2: last course name */
        return sprintf(__('%1$s and %2$s run again', 'gps-courses'), implode(', ', $titles), $last);
    }

    /* ============================================================
     * Monthly seminars
     * ============================================================ */

    /**
     * The seminar product to promote: the seminar whose next session comes
     * first (gpsc_catalog()), else the newest seminar with a published product.
     */
    public static function seminar_product_id() {
        $order = [];
        if (function_exists('gpsc_catalog')) {
            $catalog = gpsc_catalog();
            $rows    = [];
            foreach ((array) ($catalog['seminars'] ?? []) as $s) {
                $first  = isset($s['sessions'][0]) ? $s['sessions'][0] : null;
                $rows[] = [$first ? $first['date'] . ' ' . $first['start_time'] : '9999', (int) $s['id']];
            }
            usort($rows, function ($a, $b) { return strcmp($a[0], $b[0]); });
            foreach ($rows as $r) {
                $order[] = $r[1];
            }
        }
        $newest = get_posts([
            'post_type'      => 'gps_seminar',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        foreach (array_unique(array_merge($order, array_map('intval', (array) $newest))) as $sid) {
            $product = (int) get_post_meta($sid, '_gps_seminar_product_id', true);
            if ($product && 'publish' === get_post_status($product) && Seminar_Page::seminar_for_product($product)) {
                return $product;
            }
        }
        return 0;
    }

    /**
     * What the seminar promo shows; null when there is no seminar product.
     *
     * @return array|null url, sessions, ce_total, ce_each, price, city, next (null|[mon, day, number, topic, weekday, time])
     */
    public static function seminar_promo() {
        if (!function_exists('wc_get_product') || !class_exists(__NAMESPACE__ . '\\Seminar_Page')) {
            return null;
        }
        $product = self::seminar_product_id();
        if (!$product) {
            return null;
        }
        $d    = Seminar_Page::data($product);
        $next = null;
        if (!empty($d['next']['date'])) {
            $ts   = strtotime($d['next']['date']);
            $next = [
                'mon'     => $d['next']['mon'],
                'day'     => $d['next']['day'],
                'number'  => (int) $d['next']['number'],
                'topic'   => (string) $d['next']['topic'],
                'weekday' => $ts ? date_i18n('l', $ts) : '',
                'time'    => (string) $d['next']['time'],
            ];
        }
        return [
            'url'      => (string) $d['url'],
            'sessions' => (int) $d['sessions'],
            'ce_total' => (float) $d['ce_total'],
            'ce_each'  => (float) $d['ce_each'],
            'price'    => (float) $d['price'],
            'city'     => (string) $d['city'],
            'next'     => $next,
        ];
    }

    /* ============================================================
     * Sponsors
     * ============================================================ */

    /**
     * Sponsors, one per sponsor. $source 'all': every published sponsor (by
     * menu order, then title); 'default': the default list of the course pages.
     */
    public static function sponsors($source = 'all') {
        $out  = [];
        $seen = [];
        $list = 'default' === $source ? Course_Page::sponsors(0) : self::all_sponsors();
        foreach ($list as $s) {
            $key = 'id' . (int) $s['id'];
            $name = function_exists('mb_strtolower') ? mb_strtolower(trim($s['name']), 'UTF-8') : strtolower(trim($s['name']));
            if (isset($seen[$key]) || ('' !== $name && isset($seen['n' . $name])) || ($s['logo'] && isset($seen['l' . $s['logo']]))) {
                continue;
            }
            $seen[$key] = true;
            if ('' !== $name) {
                $seen['n' . $name] = true;
            }
            if ($s['logo']) {
                $seen['l' . $s['logo']] = true;
            }
            $out[] = $s;
        }
        return $out;
    }

    /** Every published gps_sponsor, same shape as Course_Page::sponsors(). */
    protected static function all_sponsors() {
        $posts = get_posts([
            'post_type'      => 'gps_sponsor',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'no_found_rows'  => true,
        ]);
        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'id'   => (int) $p->ID,
                'name' => html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8'),
                'logo' => (int) get_post_thumbnail_id($p->ID),
                'url'  => (string) get_post_meta($p->ID, Course_Page::META_SPONSOR_URL, true),
            ];
        }
        return $out;
    }
}
