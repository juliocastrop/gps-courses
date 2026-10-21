<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Public pages that should not exist on their own:
 *
 *  - a course ticket's WooCommerce product page → the course page (the ticket
 *    is bought there; the bare product page had no design and no menu);
 *  - the WooCommerce shop and product category / tag archives → /courses/;
 *  - author, post category and post tag archives (the site has no blog) → home;
 *  - ElementsKit internal posts (header/footer templates, widget content) → 404;
 *  - none of the above in the Yoast sitemap.
 *
 * Editors previewing (or logged-in users who can edit the post) are never
 * redirected. Each rule can be switched off with the gpsc_cleanup_rules filter.
 */
class Site_Cleanup {

    const EKIT_TYPES = ['elementskit_template', 'elementskit_content'];

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'redirect'], 3);
        add_filter('wpseo_sitemap_exclude_post_type', [__CLASS__, 'sitemap_exclude_post_type'], 10, 2);
        add_filter('wpseo_sitemap_exclude_taxonomy', [__CLASS__, 'sitemap_exclude_taxonomy'], 10, 2);
        add_filter('wpseo_sitemap_exclude_author', [__CLASS__, 'sitemap_exclude_authors']);
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', [__CLASS__, 'sitemap_exclude_ticket_products']);
    }

    protected static function rules() {
        return (array) apply_filters('gpsc_cleanup_rules', [
            'ticket_products' => true,
            'shop_archives'   => true,
            'blog_archives'   => true,
            'ekit_posts'      => true,
        ]);
    }

    protected static function on($rule) {
        $rules = self::rules();
        return !empty($rules[$rule]);
    }

    /** The course (master course for an edition) a ticket product sells, or 0. */
    public static function course_for_product($product_id) {
        $product_id = (int) $product_id;
        $ticket_id  = (int) get_post_meta($product_id, '_gps_ticket_type_id', true);
        if (!$ticket_id) {
            $ids = get_posts([
                'post_type'      => 'gps_ticket',
                'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [['key' => '_gps_wc_product_id', 'value' => $product_id, 'type' => 'NUMERIC']],
            ]);
            $ticket_id = $ids ? (int) $ids[0] : 0;
        }
        if (!$ticket_id || 'gps_ticket' !== get_post_type($ticket_id)) {
            return 0;
        }
        $event_id = (int) get_post_meta($ticket_id, '_gps_event_id', true);
        if (!$event_id || 'gps_event' !== get_post_type($event_id)) {
            return 0;
        }
        $master = Editions::of($event_id);
        return $master ?: $event_id;
    }

    /** Seminar page of an individual session ticket product, '' when it is not one. */
    public static function seminar_url_for_session_product($product_id) {
        global $wpdb;
        $product_id = (int) $product_id;
        // The sessions table is the source of truth (the product meta is written
        // before a new session has an id, so it can be 0)
        $seminar_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT seminar_id FROM {$wpdb->prefix}gps_seminar_sessions WHERE individual_product_id = %d LIMIT 1",
            $product_id
        ));
        if (!$seminar_id) {
            $session_id = (int) get_post_meta($product_id, '_gps_seminar_session_id', true);
            if ($session_id) {
                $seminar_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT seminar_id FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
                    $session_id
                ));
            }
        }
        if (!$seminar_id) {
            return '';
        }
        $seminar_product = (int) get_post_meta($seminar_id, '_gps_seminar_product_id', true);
        $url = ($seminar_product && $seminar_product !== $product_id && 'publish' === get_post_status($seminar_product))
            ? (string) get_permalink($seminar_product)
            : home_url('/product/gps-monthly-seminars/');
        // Never redirect a page to itself
        return untrailingslashit($url) === untrailingslashit((string) get_permalink($product_id)) ? '' : $url;
    }

    protected static function catalog_url() {
        $url = get_post_type_archive_link('gps_event');
        return $url ? $url : home_url('/courses/');
    }

    public static function redirect() {
        if (is_admin() || is_preview() || isset($_GET['elementor-preview'])) {
            return;
        }

        // Ticket product → its course (or the catalog when the course is not public)
        if ($product_id = (is_singular('product') ? (int) get_queried_object_id() : 0)) {
            if (self::on('ticket_products') && !current_user_can('edit_post', $product_id)) {
                $course = self::course_for_product($product_id);
                if ($course) {
                    $url = 'publish' === get_post_status($course) ? get_permalink($course) : self::catalog_url();
                    self::go($url);
                }
                // Individual seminar session ticket → its seminar's page
                $seminar_url = self::seminar_url_for_session_product($product_id);
                if ('' !== $seminar_url) {
                    self::go($seminar_url);
                }
            }
            return;
        }

        if (self::on('shop_archives') && function_exists('is_shop') && (is_shop() || is_product_taxonomy())) {
            self::go(self::catalog_url());
        }

        if (self::on('blog_archives') && (is_author() || is_category() || is_tag())) {
            self::go(home_url('/'));
        }

        if (self::on('ekit_posts') && is_singular(self::EKIT_TYPES) && !current_user_can('edit_post', (int) get_queried_object_id())) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
        }
    }

    protected static function go($url) {
        $url = (string) $url;
        if ('' !== $url) {
            wp_safe_redirect($url, 301);
            exit;
        }
    }

    public static function sitemap_exclude_post_type($excluded, $post_type) {
        return in_array($post_type, self::EKIT_TYPES, true) && self::on('ekit_posts') ? true : $excluded;
    }

    public static function sitemap_exclude_taxonomy($excluded, $taxonomy) {
        if (self::on('shop_archives') && in_array($taxonomy, ['product_cat', 'product_tag'], true)) {
            return true;
        }
        if (self::on('blog_archives') && in_array($taxonomy, ['category', 'post_tag'], true)) {
            return true;
        }
        return $excluded;
    }

    public static function sitemap_exclude_authors($users) {
        return self::on('blog_archives') ? [] : $users;
    }

    /** Ticket products redirect to their course: keep them out of the sitemap. */
    public static function sitemap_exclude_ticket_products($ids) {
        if (!self::on('ticket_products')) {
            return $ids;
        }
        $ids = is_array($ids) ? $ids : [];
        $product_ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        foreach ($product_ids as $pid) {
            if (self::course_for_product($pid) || '' !== self::seminar_url_for_session_product($pid)) {
                $ids[] = (int) $pid;
            }
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
