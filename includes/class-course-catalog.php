<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * /courses/ (gps_event archive) as a designed GPS course catalog: upcoming
 * courses as cards (date, CE credits, price, availability), past courses
 * below, plus an ItemList of the upcoming courses for search engines and AI.
 */
class Course_Catalog {

    const TAXONOMIES = ['gps_event_category', 'gps_event_tag'];

    public static function init() {
        add_filter('template_include', [__CLASS__, 'template'], 99);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 110);
        add_action('pre_get_posts', [__CLASS__, 'main_query']);
        add_action('template_redirect', [__CLASS__, 'no_paging']);
        add_filter('get_the_excerpt', [__CLASS__, 'excerpt'], 9, 2);

        // gpsc_catalog() cache: anything that changes what we offer refreshes it
        foreach (['gps_event', 'gps_ticket', 'gps_seminar'] as $type) {
            add_action('save_post_' . $type, [__CLASS__, 'bump']);
        }
        add_action('deleted_post', [__CLASS__, 'bump_on_delete'], 10, 2);
        add_action('trashed_post', [__CLASS__, 'bump_on_delete']);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'bump']);
        add_action('gps_ticket_created', [__CLASS__, 'bump']);
    }

    /** Invalidate the cached catalog (gpsc_catalog). */
    public static function bump() {
        update_option('gpsc_catalog_ver', (int) get_option('gpsc_catalog_ver', 0) + 1, false);
    }

    public static function bump_on_delete($post_id, $post = null) {
        $type = $post instanceof \WP_Post ? $post->post_type : get_post_type($post_id);
        if (in_array($type, ['gps_event', 'gps_ticket', 'gps_seminar'], true)) {
            self::bump();
        }
    }

    /**
     * Courses without a manual excerpt: use the course description instead of the
     * auto excerpt (Elementor's stale text copy). Share and calendar widgets use it.
     */
    public static function excerpt($text, $post = null) {
        if ($post instanceof \WP_Post && 'gps_event' === $post->post_type && !has_excerpt($post)) {
            $summary = self::summary($post);
            return '' !== $summary ? $summary : $text;
        }
        return $text;
    }

    /** The template runs its own queries: keep the main one tiny. */
    public static function main_query($query) {
        if (!is_admin() && $query->is_main_query() && ($query->is_post_type_archive('gps_event') || $query->is_tax(self::TAXONOMIES))) {
            $query->set('posts_per_page', 1);
            $query->set('no_found_rows', true);
        }
    }

    /** /courses/page/2/ would repeat page 1. */
    public static function no_paging() {
        if (self::is_catalog() && get_query_var('paged') > 1) {
            wp_safe_redirect(get_pagenum_link(1), 301);
            exit;
        }
    }

    public static function is_catalog() {
        return is_post_type_archive('gps_event') || is_tax(self::TAXONOMIES);
    }

    public static function template($template) {
        if (!self::is_catalog() || !apply_filters('gps_course_catalog_enabled', true)) {
            return $template;
        }
        $ours = GPSC_PATH . 'templates/archive-gps_event.php';
        return file_exists($ours) ? $ours : $template;
    }

    public static function enqueue_assets() {
        if (!self::is_catalog()) {
            return;
        }
        $css = GPSC_PATH . 'assets/css/gps-catalog.css';
        $deps = wp_style_is('gps-forms', 'registered') ? ['gps-forms'] : [];
        wp_enqueue_style('gps-catalog', GPSC_URL . 'assets/css/gps-catalog.css', $deps, file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
    }

    /**
     * Courses for the catalog.
     *
     * @param string        $when  'upcoming' (soonest first) or 'past' (most recent first)
     * @param \WP_Term|null $term  only courses in this category/tag
     */
    public static function courses($when = 'upcoming', $limit = 50, $term = null) {
        $today = current_time('Y-m-d');
        $args = [
            'post_type'      => 'gps_event',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_key'       => '_gps_start_date',
            'orderby'        => 'meta_value',
            'order'          => 'upcoming' === $when ? 'ASC' : 'DESC',
            'no_found_rows'  => true,
        ];

        // A course is upcoming until its end date (its start date when it has no end date)
        $cmp = 'upcoming' === $when ? '>=' : '<';
        $args['meta_query'] = [
            'relation' => 'OR',
            ['key' => '_gps_end_date', 'value' => $today, 'compare' => $cmp, 'type' => 'DATE'],
            [
                'relation' => 'AND',
                ['key' => '_gps_start_date', 'value' => $today, 'compare' => $cmp, 'type' => 'DATE'],
                ['relation' => 'OR', ['key' => '_gps_end_date', 'compare' => 'NOT EXISTS'], ['key' => '_gps_end_date', 'value' => '']],
            ],
        ];

        if ($term instanceof \WP_Term && in_array($term->taxonomy, self::TAXONOMIES, true)) {
            $args['tax_query'] = [['taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => (int) $term->term_id]];
        }

        // Courses only, also when built in wp-admin (gpsc_catalog() is cached for everyone)
        $args  = Editions::top_level_args($args);
        $query = new \WP_Query(apply_filters('gps_course_catalog_query_args', $args, $when));
        $full = 'upcoming' === $when;
        return array_map(function ($post) use ($full) {
            return self::card_data($post, $full);
        }, $query->posts);
    }

    /** Everything a course card shows ($full = false: just what the past list needs). */
    public static function card_data($post, $full = true) {
        $id    = $post->ID;
        $start = (string) get_post_meta($id, '_gps_start_date', true);
        $end   = (string) get_post_meta($id, '_gps_end_date', true);
        $cats  = get_the_terms($post, 'gps_event_category');

        $price   = null;
        $status  = 'soon';
        $tickets = $full && class_exists(__NAMESPACE__ . '\\Tickets') ? (array) Tickets::get_active_tickets($id) : [];
        if ($tickets) {
            $open = array_filter($tickets, function ($t) {
                return !Tickets::is_sold_out(is_object($t) ? $t->ID : (int) $t);
            });
            $status = $open ? 'open' : 'sold_out';
            foreach ($open as $t) {
                $p = get_post_meta(is_object($t) ? $t->ID : (int) $t, '_gps_ticket_price', true);
                if ('' !== $p && null !== $p && (null === $price || (float) $p < $price)) {
                    $price = (float) $p;
                }
            }
        }

        return [
            'id'       => $id,
            'title'    => get_the_title($post),
            'url'      => get_permalink($post),
            'image'    => (string) get_the_post_thumbnail_url($post, 'medium_large'),
            'excerpt'  => !$full ? '' : self::summary($post),
            'category' => is_array($cats) && $cats ? $cats[0]->name : '',
            'ce'       => (float) get_post_meta($id, '_gps_ce_credits', true),
            'start'    => $start,
            'end'      => $end ?: $start,
            'when'     => class_exists(__NAMESPACE__ . '\\Account_Dashboard') ? Account_Dashboard::date_range($start, $end) : $start,
            'venue'    => (string) get_post_meta($id, '_gps_venue', true),
            'city'     => trim((string) get_post_meta($id, '_gps_city', true) . ((string) get_post_meta($id, '_gps_state', true) ? ', ' . get_post_meta($id, '_gps_state', true) : '')),
            'price'    => $price,
            'status'   => $status,
        ];
    }

    /**
     * Short description: the manual excerpt or the course description field.
     * Never the auto excerpt, which on Elementor pages is button and widget text.
     */
    public static function summary($post) {
        $text = trim((string) $post->post_excerpt);
        foreach (['_gps_course_description', '_gps_description'] as $key) {
            $text = $text ?: (string) get_post_meta($post->ID, $key, true);
        }
        $text = preg_replace('#</(p|div|li|h[1-6])>|<br\s*/?>#i', ' ', strip_shortcodes($text));
        return wp_trim_words(wp_strip_all_tags($text), 26);
    }

    /** ItemList of upcoming courses (each item a Course with its next CourseInstance). */
    public static function json_ld(array $courses) {
        if (!$courses) {
            return '';
        }
        $items = [];
        $text = function ($s) {
            return trim(html_entity_decode(wp_strip_all_tags((string) $s), ENT_QUOTES, 'UTF-8'));
        };
        foreach (array_values($courses) as $i => $c) {
            $course = [
                '@type'       => 'Course',
                '@id'         => $c['url'] . '#course',
                'name'        => $text($c['title']),
                'url'         => $c['url'],
                'description' => $text($c['excerpt'] ?: $c['title']),
                'provider'    => ['@type' => 'Organization', 'name' => $text(get_bloginfo('name')), 'url' => home_url('/')],
                'hasCourseInstance' => [
                    '@type'      => 'CourseInstance',
                    'courseMode' => 'Onsite',
                    'startDate'  => $c['start'],
                    'endDate'    => $c['end'],
                    'location'   => ['@type' => 'Place', 'name' => $c['venue'] ?: get_bloginfo('name'), 'address' => $c['city'] ?: 'Duluth, GA'],
                ],
            ];
            if ($c['ce'] > 0) {
                $course['educationalCredentialAwarded'] = sprintf('%s CE credits', Credits::format($c['ce']));
            }
            if (null !== $c['price']) {
                $course['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => $c['price'],
                    'priceCurrency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                    'availability'  => 'sold_out' === $c['status'] ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
                    'url'           => $c['url'],
                    'category'      => 'Paid',
                ];
            }
            if ($c['image']) {
                $course['image'] = $c['image'];
            }
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $course];
        }
        $data = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => __('Upcoming GPS Dental Training courses', 'gps-courses'), 'itemListElement' => $items];
        return '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
