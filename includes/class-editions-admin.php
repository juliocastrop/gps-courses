<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Course editions in wp-admin:
 * - course editor: "Editions" tab (cards, Add edition, Resync); the edition
 *   fields of a course with editions are read-only (set per edition);
 * - edition editor: banner back to the course, only the edition's fields
 *   (dates, venue, CE, label, speakers), its ticket types and agenda days in
 *   the sidebar; the shared boxes (Yoast, AI Search, course image) are hidden
 *   and the title always equals the course's.
 *
 * Nothing here changes a course without editions.
 */
class Editions_Admin {

    const ACTION_ADD    = 'gpsc_add_edition';
    const ACTION_RESYNC = 'gpsc_resync_edition';

    /** An edition / ticket type / agenda counts unless trashed. */
    const STATUSES = ['publish', 'draft', 'pending', 'future', 'private'];

    /** Edition fields moved one year by "Add edition". */
    const DATE_FIELDS = ['_gps_start_date', '_gps_end_date', '_gps_registration_deadline'];

    public static function init() {
        add_action('admin_post_' . self::ACTION_ADD, [__CLASS__, 'handle_add']);
        add_action('admin_post_' . self::ACTION_RESYNC, [__CLASS__, 'handle_resync']);
        add_action('admin_notices', [__CLASS__, 'notices']);
        add_action('edit_form_top', [__CLASS__, 'child_banner']);
        add_action('add_meta_boxes_gps_event', [__CLASS__, 'meta_boxes'], 99);
        add_filter('admin_body_class', [__CLASS__, 'body_class']);
        add_filter('wp_insert_post_data', [__CLASS__, 'lock_child_title'], 10, 2);
        add_action('save_post_gps_event', [__CLASS__, 'titles_follow_master'], 60, 2);
        add_action('wp_insert_post', [__CLASS__, 'preset_course'], 10, 3);
        add_action('admin_print_footer_scripts', [__CLASS__, 'preselect_script'], 99);
        add_action('transition_post_status', [__CLASS__, 'release_held_product'], 10, 3);
    }

    /** WooCommerce product status through its own API (keeps WC caches/lookup tables right). */
    protected static function set_product_status($product_id, $status, $only_from = '') {
        $product = function_exists('wc_get_product') ? wc_get_product((int) $product_id) : null;
        if ($product) {
            if ('' !== $only_from && $product->get_status() !== $only_from) {
                return;
            }
            $product->set_status($status);
            $product->save();
            return;
        }
        if ('' === $only_from || get_post_status($product_id) === $only_from) {
            wp_update_post(['ID' => (int) $product_id, 'post_status' => $status]);
        }
    }

    /** Ticket types copied by "Add edition" keep their WooCommerce product as a draft. */
    const META_HELD_PRODUCT = '_gps_product_held';

    /**
     * A copied ticket type is published: its product goes live too (it was held
     * as a draft so the shop never sells a ticket of a draft edition).
     */
    public static function release_held_product($new_status, $old_status, $post) {
        if (!$post instanceof \WP_Post || 'gps_ticket' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status) {
            return;
        }
        if (!get_post_meta($post->ID, self::META_HELD_PRODUCT, true)) {
            return;
        }
        $product_id = (int) get_post_meta($post->ID, '_gps_wc_product_id', true);
        if ($product_id) {
            self::set_product_status($product_id, 'publish', 'draft');
        }
        delete_post_meta($post->ID, self::META_HELD_PRODUCT);
    }

    /* ============================================================
     * Data
     * ============================================================ */

    /** Every edition of a course (drafts too), earliest start first. */
    public static function editions($master) {
        return Editions::all($master, self::STATUSES);
    }

    /** Ticket types / agendas of an edition (any status but trash), oldest first. */
    public static function linked_posts($post_type, $event_id) {
        return get_posts([
            'post_type'     => $post_type,
            'post_status'   => self::STATUSES,
            'numberposts'   => -1,
            'orderby'       => 'ID',
            'order'         => 'ASC',
            'no_found_rows' => true,
            'meta_query'    => [['key' => '_gps_event_id', 'value' => (int) $event_id, 'type' => 'NUMERIC']],
        ]);
    }

    /** "2026-09-17" => "2027-09-17" (keeps any time part; Feb 29 => Feb 28). */
    public static function shift_year($value) {
        $value = (string) $value;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(.*)$/s', $value, $m)) {
            return $value;
        }
        $y = (int) $m[1] + 1;
        $d = (int) $m[3];
        if (2 === (int) $m[2] && 29 === $d && !checkdate(2, 29, $y)) {
            $d = 28;
        }
        return sprintf('%04d-%s-%02d', $y, $m[2], $d) . $m[4];
    }

    /** "GA 2027" => "GA 2028" when the edition label is in the text. */
    protected static function relabel($text, $old, $new) {
        $text = (string) $text;
        if ('' === (string) $old || '' === (string) $new) {
            return $text;
        }
        return str_replace((string) $old, (string) $new, $text);
    }

    /** update_post_meta() unslashes: keep backslashes (JSON) intact. */
    protected static function put($post_id, $key, $value) {
        update_post_meta($post_id, $key, is_string($value) ? wp_slash($value) : $value);
    }

    /** Seats of an edition over all its ticket types: [sold, total|null]. */
    protected static function seats($edition_id) {
        $sold = 0;
        $total = 0;
        $unlimited = false;
        foreach (self::linked_posts('gps_ticket', $edition_id) as $t) {
            $stock = Tickets::get_ticket_stock($t->ID);
            $sold += (int) $stock['sold'];
            if ($stock['unlimited']) {
                $unlimited = true;
            } else {
                $total += (int) $stock['total'];
            }
        }
        return [$sold, $unlimited ? null : $total];
    }

    protected static function attended($edition_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ticket_id) FROM {$wpdb->prefix}gps_attendance WHERE event_id = %d",
            (int) $edition_id
        ));
    }

    /** [label, tone] of an edition's status badge. */
    public static function state_badge($edition) {
        $s   = Admin_Lists::course_state($edition);
        $key = $s ? $s['key'] : 'draft';
        $map = [
            'open'     => [__('Registration open', 'gps-courses'), 'ok'],
            'sold_out' => [__('Sold out', 'gps-courses'), 'bad'],
            'soon'     => [__('Coming soon', 'gps-courses'), 'info'],
            'past'     => [__('Took place', 'gps-courses'), 'neutral'],
            'draft'    => [__('Draft', 'gps-courses'), 'warn'],
        ];
        return isset($map[$key]) ? $map[$key] : $map['draft'];
    }

    protected static function badge($label, $tone) {
        return '<span class="gps-badge gps-badge--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    protected static function action_url($action, $master) {
        return wp_nonce_url(
            add_query_arg(['action' => $action, 'post' => (int) $master], admin_url('admin-post.php')),
            $action . '_' . (int) $master
        );
    }

    public static function resync_url($master) {
        return self::action_url(self::ACTION_RESYNC, $master);
    }

    protected static function time_label($time) {
        $time = (string) $time;
        if ('' === $time) {
            return '';
        }
        $ts = strtotime('2000-01-01 ' . $time);
        return $ts ? date_i18n(get_option('time_format') ?: 'g:i a', $ts) : $time;
    }

    protected static function dates_label($id) {
        $start = (string) get_post_meta($id, '_gps_start_date', true);
        $end   = (string) get_post_meta($id, '_gps_end_date', true) ?: $start;
        if ('' === $start) {
            return '';
        }
        return format_date_range($start, $end) ?: $start;
    }

    /* ============================================================
     * Course editor: Editions tab
     * ============================================================ */

    /** Number shown on the tab. */
    public static function count($master) {
        return count(self::editions($master));
    }

    public static function render_tab($post) {
        $master = (int) $post->ID;
        $eds    = array_reverse(self::editions($master)); // newest first
        echo '<p class="gps-tab-intro">' . esc_html__('Each edition has its own dates, location, CE credits, ticket types and agenda. The course page always shows the next edition; title, description and page design are shared.', 'gps-courses') . '</p>';

        if (!$eds) {
            echo '<div class="gps-editions-empty">';
            echo '<p><strong>' . esc_html__('This course has not been split into editions yet.', 'gps-courses') . '</strong> ';
            echo esc_html__('Its dates, location, CE credits, ticket types and agenda are set on the course itself.', 'gps-courses') . '</p>';
            echo '<p><button type="button" class="button" disabled>' . esc_html__('Split into editions', 'gps-courses') . '</button> ';
            echo '<span class="description">' . esc_html__('Coming in the next step.', 'gps-courses') . '</span></p>';
            echo '</div>';
            return;
        }

        $current = Editions::current($master);
        $latest  = self::source_edition($master);
        echo '<div class="gps-editions-toolbar">';
        if ($latest && current_user_can('edit_post', $master)) {
            /* translators: %s: edition label (year) */
            $confirm = sprintf(__('Create a draft edition copied from Edition %s (dates one year later)? Unsaved changes on this page will be lost.', 'gps-courses'), Editions::edition_label($latest->ID));
            echo '<a class="button button-primary" href="' . esc_url(self::action_url(self::ACTION_ADD, $master)) . '" onclick="return window.confirm(' . esc_attr(wp_json_encode($confirm)) . ');">'
                . '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__('Add edition', 'gps-courses') . '</a>';
            echo '<span class="description">' . esc_html(sprintf(
                /* translators: %s: edition label (year) */
                __('Copies Edition %s as a draft: dates one year later, its agenda, and its ticket types as inactive drafts.', 'gps-courses'),
                Editions::edition_label($latest->ID)
            )) . '</span>';
        }
        echo '<a class="gps-editions-resync" href="' . esc_url(self::resync_url($master)) . '">' . esc_html__('Resync course page', 'gps-courses') . '</a>';
        echo '</div>';

        foreach ($eds as $ed) {
            self::render_card($ed, $ed->ID === $current);
        }
    }

    protected static function render_card($ed, $is_current) {
        $id    = (int) $ed->ID;
        $state = Admin_Lists::course_state($ed);
        $past  = $state && $state['past'];
        list($badge, $tone) = self::state_badge($ed);

        $tickets = self::linked_posts('gps_ticket', $id);
        $ce      = (float) get_post_meta($id, '_gps_ce_credits', true);
        $venue   = (string) get_post_meta($id, '_gps_venue', true);
        $city    = (string) get_post_meta($id, '_gps_city', true);
        $st      = (string) get_post_meta($id, '_gps_state', true);
        $times   = array_filter([self::time_label(get_post_meta($id, '_gps_start_time', true)), self::time_label(get_post_meta($id, '_gps_end_time', true))]);
        $muted   = '<span class="gps-muted">—</span>';

        echo '<div class="gps-edition-card' . ($is_current ? ' is-current' : '') . '">';
        echo '<div class="gps-edition-card__head">';
        /* translators: %s: edition label (year) */
        echo '<h3>' . esc_html(sprintf(__('Edition %s', 'gps-courses'), Editions::edition_label($id))) . '</h3> ';
        echo self::badge($badge, $tone);
        if ($is_current) {
            echo '<span class="gps-edition-card__current"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span>' . esc_html__('Next edition · shown on the course page', 'gps-courses') . '</span>';
        }
        echo '<span class="gps-edition-card__links">';
        $links = [];
        if (current_user_can('edit_post', $id)) {
            $links[] = '<a href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html__('Edit', 'gps-courses') . '</a>';
        }
        $links[] = '<a href="' . esc_url(add_query_arg(['page' => 'gps-purchased-tickets', 'event_id' => $id], admin_url('admin.php'))) . '">' . esc_html__('Report', 'gps-courses') . '</a>';
        if ($past) {
            $links[] = '<a href="' . esc_url(add_query_arg(['page' => 'gps-certificates', 'event_id' => $id], admin_url('admin.php'))) . '">' . esc_html__('Certificates', 'gps-courses') . '</a>';
        } else {
            $links[] = '<a href="' . esc_url(add_query_arg(['page' => 'gps-attendance', 'event_id' => $id], admin_url('admin.php'))) . '">' . esc_html__('Check-in', 'gps-courses') . '</a>';
        }
        echo implode(' <span class="gps-sep" aria-hidden="true">·</span> ', $links);
        echo '</span></div>';

        echo '<dl class="gps-edition-card__grid">';

        $dates = self::dates_label($id);
        echo '<div><dt>' . esc_html__('Dates', 'gps-courses') . '</dt><dd>' . ('' !== $dates ? esc_html($dates) : $muted);
        if ($times) {
            echo '<span class="gps-sub">' . esc_html(implode(' – ', $times)) . '</span>';
        }
        echo '</dd></div>';

        echo '<div><dt>' . esc_html__('Location', 'gps-courses') . '</dt><dd>' . ('' !== $venue ? esc_html($venue) : $muted);
        if ('' !== $city) {
            echo '<span class="gps-sub">' . esc_html($city . ('' !== $st ? ', ' . $st : '')) . '</span>';
        }
        echo '</dd></div>';

        echo '<div><dt>' . esc_html__('CE credits', 'gps-courses') . '</dt><dd>' . ($ce > 0 ? esc_html(Credits::format($ce)) : $muted) . '</dd></div>';

        echo '<div><dt>' . esc_html__('Ticket types', 'gps-courses') . '</dt><dd>';
        if ($tickets) {
            echo esc_html((string) count($tickets));
            $first = $tickets[0];
            $price = get_post_meta($first->ID, '_gps_ticket_price', true);
            $line  = $first->post_title . ('' !== (string) $price ? ' · $' . number_format_i18n((float) $price, floor((float) $price) == (float) $price ? 0 : 2) : '');
            echo '<span class="gps-sub">' . esc_html($line) . (count($tickets) > 1 ? esc_html(sprintf(
                /* translators: %d: number of other ticket types */
                __(' +%d more', 'gps-courses'),
                count($tickets) - 1
            )) : '') . '</span>';
        } else {
            echo $muted;
        }
        echo '</dd></div>';

        echo '<div><dt>' . esc_html__('Seats', 'gps-courses') . '</dt><dd>';
        if ($past) {
            $n = self::attended($id);
            /* translators: %d: tickets checked in */
            echo esc_html(sprintf(_n('Attended %d ticket', 'Attended %d tickets', $n, 'gps-courses'), $n));
        } elseif (!$tickets) {
            echo $muted;
        } else {
            list($sold, $total) = self::seats($id);
            echo Admin_Lists::meter($sold, $total, 'publish' === $ed->post_status ? 'info' : 'warn');
        }
        echo '</dd></div>';

        echo '</dl></div>';
    }

    /**
     * The read-only note over a course's edition fields (they come from the
     * edition on the course page).
     */
    public static function locked_note($master) {
        $master  = (int) $master;
        $current = Editions::current($master);
        $html = '<div class="gps-edition-locked"><span class="dashicons dashicons-lock" aria-hidden="true"></span><div>';
        $html .= '<strong>' . esc_html__('Set per edition (Editions tab).', 'gps-courses') . '</strong> ';
        if ($current) {
            /* translators: %s: edition label (year) */
            $html .= esc_html(sprintf(__('Showing Edition %s, the one on the course page.', 'gps-courses'), Editions::edition_label($current)));
        } else {
            $html .= esc_html__('No edition is published yet: the course page still shows these values.', 'gps-courses');
        }
        $html .= ' <a href="' . esc_url(self::resync_url($master)) . '">' . esc_html__('Resync', 'gps-courses') . '</a>';
        $html .= '</div></div>';
        return $html;
    }

    /* ============================================================
     * Edition editor
     * ============================================================ */

    /** The edition being edited on post.php, or null. */
    protected static function screen_child() {
        global $pagenow;
        if ('post.php' !== $pagenow || empty($_GET['post'])) {
            return null;
        }
        $post = get_post(absint($_GET['post']));
        return ($post && 'gps_event' === $post->post_type && Editions::of($post->ID)) ? $post : null;
    }

    public static function body_class($classes) {
        return self::screen_child() ? $classes . ' gps-edition-child' : $classes;
    }

    /** Above the title: "Edition 2027 of <course> · Back to the course". */
    public static function child_banner($post) {
        if (!$post instanceof \WP_Post || 'gps_event' !== $post->post_type) {
            return;
        }
        $master = Editions::of($post->ID);
        if (!$master) {
            return;
        }
        list($label, $tone) = self::state_badge($post);
        echo '<div class="gps-edition-banner">';
        echo '<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>';
        echo '<span class="gps-edition-banner__text">';
        echo esc_html(sprintf(
            /* translators: %s: edition label (year) */
            __('Edition %s of', 'gps-courses'),
            Editions::edition_label($post->ID)
        )) . ' <strong>' . esc_html(get_the_title($master)) . '</strong></span> ';
        echo self::badge($label, $tone);
        echo ' <a class="gps-edition-banner__back" href="' . esc_url(add_query_arg('gps_tab', 'editions', get_edit_post_link($master, 'raw'))) . '">&larr; ' . esc_html__('Back to the course', 'gps-courses') . '</a>';
        echo '</div>';
        // The title is the course's (shared): shown, not edited here
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("title");if(t){t.readOnly=true;t.setAttribute("aria-readonly","true");t.title=' . wp_json_encode(__('The title is shared with the course. Change it on the course.', 'gps-courses')) . ';}});</script>';
    }

    /** The edition editor form (replaces the course tabs for an edition). */
    public static function render_child_screen($post) {
        $id     = (int) $post->ID;
        $master = Editions::of($id);
        $v = function ($key) use ($id) {
            return (string) get_post_meta($id, $key, true);
        };
        $field = function ($type, $name, $label, $value, $attrs = '', $half = true) {
            echo '<div class="gps-form-field' . ($half ? ' gps-form-field-half' : '') . '">';
            echo '<label for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
            echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . $attrs . ' />';
            echo '</div>';
        };

        wp_nonce_field('gps_event_meta_nonce', 'gps_event_meta_nonce');
        echo '<div class="gps-event-edit-container gps-edition-editor">';

        echo '<section class="gps-edition-section"><h2>' . esc_html__('Edition', 'gps-courses') . '</h2>';
        echo '<p class="gps-tab-intro">' . esc_html__('Only what changes from one edition to the next is set here. Title, description, images and page design are shared with the course.', 'gps-courses') . '</p>';
        echo '<div class="gps-form-grid">';
        $field('text', 'gps_edition_label', __('Edition name', 'gps-courses'), $v(Editions::META_LABEL), 'maxlength="40" placeholder="' . esc_attr(substr($v('_gps_start_date'), 0, 4) ?: '2027') . '"');
        $field('date', 'gps_registration_deadline', __('Registration deadline (optional)', 'gps-courses'), substr($v('_gps_registration_deadline'), 0, 10));
        echo '</div></section>';

        echo '<section class="gps-edition-section"><h2>' . esc_html__('Dates & times', 'gps-courses') . '</h2><div class="gps-form-grid">';
        $field('date', 'gps_start_date', __('Start Date', 'gps-courses'), $v('_gps_start_date'), 'required');
        $field('date', 'gps_end_date', __('End Date', 'gps-courses'), $v('_gps_end_date'), 'required');
        $field('time', 'gps_start_time', __('Start Time', 'gps-courses'), $v('_gps_start_time'));
        $field('time', 'gps_end_time', __('End Time', 'gps-courses'), $v('_gps_end_time'));
        echo '</div></section>';

        echo '<section class="gps-edition-section"><h2>' . esc_html__('Location', 'gps-courses') . '</h2><div class="gps-form-grid">';
        $field('text', 'gps_venue', __('Venue Name', 'gps-courses'), $v('_gps_venue'), 'class="widefat"');
        $field('text', 'gps_address', __('Address', 'gps-courses'), $v('_gps_address'), 'class="widefat"');
        $field('text', 'gps_city', __('City', 'gps-courses'), $v('_gps_city'));
        $field('text', 'gps_state', __('State/Province', 'gps-courses'), $v('_gps_state'));
        $field('text', 'gps_zip', __('ZIP/Postal Code', 'gps-courses'), $v('_gps_zip'));
        $field('text', 'gps_country', __('Country', 'gps-courses'), $v('_gps_country'));
        echo '</div></section>';

        echo '<section class="gps-edition-section"><h2>' . esc_html__('CE credits', 'gps-courses') . '</h2><div class="gps-form-grid">';
        $field('number', 'gps_ce_credits', __('CE Credits', 'gps-courses'), $v('_gps_ce_credits'), 'min="0" step="0.5"');
        echo '</div></section>';

        // Speakers override: empty = the course's speakers
        $chosen   = class_exists(__NAMESPACE__ . '\\Course_Page') ? Course_Page::speaker_ids($id) : [];
        $course_n = class_exists(__NAMESPACE__ . '\\Course_Page') ? count(Course_Page::speaker_ids($master)) : 0;
        $speakers = get_posts([
            'post_type'     => 'gps_speaker',
            'post_status'   => 'publish',
            'numberposts'   => -1,
            'orderby'       => 'title',
            'order'         => 'ASC',
            'no_found_rows' => true,
        ]);
        echo '<section class="gps-edition-section"><h2>' . esc_html__('Speakers (optional)', 'gps-courses') . '</h2>';
        echo '<div class="gps-form-field"><label for="gps_edition_speaker_ids">' . esc_html__('Speakers of this edition', 'gps-courses') . '</label>';
        echo '<select id="gps_edition_speaker_ids" name="gps_edition_speaker_ids[]" multiple class="widefat" size="6">';
        foreach ((array) $speakers as $sp) {
            echo '<option value="' . (int) $sp->ID . '"' . (in_array((int) $sp->ID, $chosen, true) ? ' selected' : '') . '>' . esc_html($sp->post_title) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html(sprintf(
            /* translators: %d: number of speakers on the course */
            _n('Leave empty to use the course\'s speaker (%d). Hold Ctrl/Cmd to select several.', 'Leave empty to use the course\'s speakers (%d). Hold Ctrl/Cmd to select several.', $course_n, 'gps-courses'),
            $course_n
        )) . '</p></div></section>';

        echo '</div>';
    }

    /** Save an edition's own fields (called by Posttypes::save_event_meta after its nonce / cap checks). */
    public static function save_child_meta($post_id) {
        $text = function ($key) {
            return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        };
        $set = function ($key, $value) use ($post_id) {
            if ('' === $value || [] === $value || null === $value) {
                delete_post_meta($post_id, $key);
            } else {
                update_post_meta($post_id, $key, wp_slash($value));
            }
        };
        $map = [
            'gps_start_date' => '_gps_start_date',
            'gps_end_date'   => '_gps_end_date',
            'gps_start_time' => '_gps_start_time',
            'gps_end_time'   => '_gps_end_time',
            'gps_venue'      => '_gps_venue',
            'gps_address'    => '_gps_address',
            'gps_city'       => '_gps_city',
            'gps_state'      => '_gps_state',
            'gps_zip'        => '_gps_zip',
            'gps_country'    => '_gps_country',
            'gps_registration_deadline' => '_gps_registration_deadline',
        ];
        foreach ($map as $field => $key) {
            if (isset($_POST[$field])) {
                $set($key, $text($field));
            }
        }
        if (isset($_POST['gps_ce_credits'])) {
            $ce = $text('gps_ce_credits');
            $set('_gps_ce_credits', '' === $ce ? '' : (string) max(0, (float) $ce));
        }
        if (isset($_POST['gps_edition_label'])) {
            $set(Editions::META_LABEL, function_exists('mb_substr') ? mb_substr($text('gps_edition_label'), 0, 40) : substr($text('gps_edition_label'), 0, 40));
        }
        $ids = isset($_POST['gps_edition_speaker_ids']) ? array_map('absint', (array) $_POST['gps_edition_speaker_ids']) : [];
        $ids = array_values(array_unique(array_filter($ids, function ($sid) {
            return $sid && 'gps_speaker' === get_post_type($sid);
        })));
        $set('_gps_speaker_ids', $ids ?: '');
    }

    /**
     * Edition editor: no shared boxes (SEO, AI Search, course image); its
     * ticket types and agenda days in the sidebar.
     */
    public static function meta_boxes($post) {
        if (!$post instanceof \WP_Post || !Editions::of($post->ID)) {
            return;
        }
        foreach (['wpseo_meta', 'yoast_internal_linking', 'gpsc-ai-search', 'gps_course_image', 'postimagediv'] as $box) {
            foreach (['normal', 'side', 'advanced'] as $context) {
                remove_meta_box($box, 'gps_event', $context);
            }
        }
        add_meta_box('gps_edition_tickets', __('Ticket types', 'gps-courses'), [__CLASS__, 'render_tickets_box'], 'gps_event', 'side', 'high');
        add_meta_box('gps_edition_agenda', __('Agenda days', 'gps-courses'), [__CLASS__, 'render_agenda_box'], 'gps_event', 'side', 'high');
    }

    protected static function status_suffix($p) {
        if ('publish' === $p->post_status) {
            return '';
        }
        $obj = get_post_status_object($p->post_status);
        return ' ' . self::badge($obj ? $obj->label : $p->post_status, 'warn');
    }

    public static function render_tickets_box($post) {
        $tickets = self::linked_posts('gps_ticket', $post->ID);
        echo '<ul class="gps-edition-side-list">';
        if (!$tickets) {
            echo '<li class="gps-muted">' . esc_html__('No ticket types yet.', 'gps-courses') . '</li>';
        }
        foreach ($tickets as $t) {
            $price = get_post_meta($t->ID, '_gps_ticket_price', true);
            echo '<li><a href="' . esc_url(get_edit_post_link($t->ID)) . '">' . esc_html($t->post_title ?: __('(no title)', 'gps-courses')) . '</a>'
                . ('' !== (string) $price ? ' <span class="gps-muted">$' . esc_html(number_format_i18n((float) $price, floor((float) $price) == (float) $price ? 0 : 2)) . '</span>' : '')
                . self::status_suffix($t) . '</li>';
        }
        echo '</ul>';
        echo '<p><a class="button" href="' . esc_url(add_query_arg(['post_type' => 'gps_ticket', 'gps_event_id' => (int) $post->ID], admin_url('post-new.php'))) . '">+ ' . esc_html__('Add ticket type', 'gps-courses') . '</a></p>';
    }

    public static function render_agenda_box($post) {
        $days = self::linked_posts('gps_schedule', $post->ID);
        usort($days, function ($a, $b) {
            return strcmp((string) get_post_meta($a->ID, '_gps_schedule_date', true), (string) get_post_meta($b->ID, '_gps_schedule_date', true)) ?: $a->ID - $b->ID;
        });
        echo '<ul class="gps-edition-side-list">';
        if (!$days) {
            echo '<li class="gps-muted">' . esc_html__('No agenda days yet.', 'gps-courses') . '</li>';
        }
        foreach ($days as $d) {
            $date = (string) get_post_meta($d->ID, '_gps_schedule_date', true);
            $when = $date ? date_i18n('M j, Y', strtotime($date)) : '';
            echo '<li><a href="' . esc_url(get_edit_post_link($d->ID)) . '">' . esc_html($d->post_title ?: __('(no title)', 'gps-courses')) . '</a>'
                . ('' !== $when ? ' <span class="gps-muted">' . esc_html($when) . '</span>' : '')
                . self::status_suffix($d) . '</li>';
        }
        echo '</ul>';
        echo '<p><a class="button" href="' . esc_url(add_query_arg(['post_type' => 'gps_schedule', 'gps_event_id' => (int) $post->ID], admin_url('post-new.php'))) . '">+ ' . esc_html__('Add day', 'gps-courses') . '</a></p>';
    }

    /** An edition's title is always its course's. */
    public static function lock_child_title($data, $postarr = []) {
        if (!isset($data['post_type'], $data['post_parent']) || 'gps_event' !== $data['post_type'] || (int) $data['post_parent'] <= 0) {
            return $data;
        }
        $parent = get_post((int) $data['post_parent']);
        if ($parent && 'gps_event' === $parent->post_type && 0 === (int) $parent->post_parent) {
            $data['post_title'] = wp_slash($parent->post_title); // $data is slashed here
        }
        return $data;
    }

    /** The course was renamed: its editions follow (no save hooks re-run). */
    public static function titles_follow_master($post_id, $post = null) {
        if (wp_is_post_revision($post_id) || !Editions::is_master($post_id)) {
            return;
        }
        $post = $post instanceof \WP_Post ? $post : get_post($post_id);
        if (!$post) {
            return;
        }
        global $wpdb;
        foreach (Editions::map() as $child => $parent) {
            if ((int) $parent !== (int) $post_id) {
                continue;
            }
            $c = get_post($child);
            if ($c && $c->post_title !== $post->post_title) {
                $wpdb->update($wpdb->posts, ['post_title' => $post->post_title], ['ID' => (int) $child]);
                clean_post_cache($child);
            }
        }
    }

    /** "+ Add ticket type" / "+ Add day" from an edition: the new post starts linked to it. */
    public static function preset_course($post_id, $post = null, $update = false) {
        global $pagenow;
        if ($update || !$post instanceof \WP_Post || 'auto-draft' !== $post->post_status
            || !in_array($post->post_type, ['gps_ticket', 'gps_schedule'], true)
            || !is_admin() || 'post-new.php' !== $pagenow || empty($_GET['gps_event_id'])) {
            return;
        }
        $event_id = absint($_GET['gps_event_id']);
        if ('gps_event' !== get_post_type($event_id) || !current_user_can('edit_post', $event_id)) {
            return;
        }
        update_post_meta($post_id, '_gps_event_id', $event_id);
    }

    /** Check-in / Certificates opened from an edition card: that edition is selected. */
    public static function preselect_script() {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $ids  = ['gps-attendance' => 'gps-select-event', 'gps-certificates' => 'event-selector'];
        if (!isset($ids[$page]) || empty($_GET['event_id'])) {
            return;
        }
        $event_id = absint($_GET['event_id']);
        ?>
        <script>
        window.addEventListener('load', function () {
            var s = document.getElementById(<?php echo wp_json_encode($ids[$page]); ?>);
            var v = <?php echo wp_json_encode((string) $event_id); ?>;
            if (!s || !s.querySelector('option[value="' + v + '"]')) { return; }
            s.value = v;
            if (window.jQuery) { window.jQuery(s).trigger('change'); } else { s.dispatchEvent(new Event('change')); }
        });
        </script>
        <?php
    }

    /* ============================================================
     * Add edition / Resync
     * ============================================================ */

    /** The edition "Add edition" copies: the latest dated one (else the last). */
    public static function source_edition($master) {
        $eds = self::editions($master);
        for ($i = count($eds) - 1; $i >= 0; $i--) {
            if ('' !== (string) get_post_meta($eds[$i]->ID, '_gps_start_date', true)) {
                return $eds[$i];
            }
        }
        return $eds ? $eds[count($eds) - 1] : null;
    }

    /**
     * Create a DRAFT edition copied from the latest one: edition fields with
     * dates one year later, label +1, its agenda days (+1 year) and its ticket
     * types as draft, inactive copies with sale dates +1 year and a new
     * WooCommerce product each. The source edition is never modified.
     *
     * @return int|\WP_Error new edition ID
     */
    public static function add_edition($master) {
        $master = (int) $master;
        $course = get_post($master);
        if (!$course || 'gps_event' !== $course->post_type || Editions::of($master)) {
            return new \WP_Error('gpsc_not_course', __('This is not a course.', 'gps-courses'));
        }
        $src = self::source_edition($master);
        if (!$src) {
            return new \WP_Error('gpsc_no_editions', __('This course has no edition to copy yet.', 'gps-courses'));
        }
        $src_id    = (int) $src->ID;
        $old_label = Editions::edition_label($src_id);
        $start     = self::shift_year(get_post_meta($src_id, '_gps_start_date', true));
        $numeric   = (bool) preg_match('/^\d+$/', $old_label);
        $new_label = $numeric ? (string) ((int) $old_label + 1) : (preg_match('/^\d{4}/', $start) ? substr($start, 0, 4) : '');
        // Titles are only rewritten when the label is a year ("2026 GA" → "2027 GA")
        $rename_from = $numeric ? $old_label : '';

        $id = wp_insert_post(wp_slash([
            'post_type'   => 'gps_event',
            'post_status' => 'draft',
            'post_parent' => $master,
            'post_title'  => $course->post_title,
            'post_name'   => sanitize_title($course->post_name . ('' !== $new_label ? '-' . $new_label : '-edition')),
            'post_author' => get_current_user_id(),
        ]), true);
        if (is_wp_error($id) || !$id) {
            return is_wp_error($id) ? $id : new \WP_Error('gpsc_insert', __('The edition could not be created.', 'gps-courses'));
        }
        $id = (int) $id;

        foreach (Editions::FIELDS as $key) {
            $value = get_post_meta($src_id, $key, true);
            if ('' === (string) $value) {
                continue;
            }
            self::put($id, $key, in_array($key, self::DATE_FIELDS, true) ? self::shift_year($value) : $value);
        }
        if ('' !== $new_label) {
            self::put($id, Editions::META_LABEL, $new_label);
        }
        $speakers = get_post_meta($src_id, '_gps_speaker_ids', true);
        if (!empty($speakers)) {
            update_post_meta($id, '_gps_speaker_ids', $speakers);
        }

        // Agenda days, one year later (same status: they show once the edition is published)
        foreach (self::linked_posts('gps_schedule', $src_id) as $day) {
            $new_day = wp_insert_post(wp_slash([
                'post_type'   => 'gps_schedule',
                'post_status' => 'publish' === $day->post_status ? 'publish' : 'draft',
                'post_title'  => self::relabel($day->post_title, $rename_from, $new_label),
                'post_author' => get_current_user_id(),
                'menu_order'  => (int) $day->menu_order,
            ]), true);
            if (is_wp_error($new_day) || !$new_day) {
                continue;
            }
            self::put($new_day, '_gps_event_id', $id);
            self::put($new_day, '_gps_schedule_date', self::shift_year(get_post_meta($day->ID, '_gps_schedule_date', true)));
            foreach (['_gps_tab_label', '_gps_schedule_topics'] as $key) {
                $value = get_post_meta($day->ID, $key, true);
                if ('' !== (string) $value) {
                    self::put($new_day, $key, $value);
                }
            }
        }

        // Ticket types: drafts, inactive, sale dates one year later, own WooCommerce product
        foreach (self::linked_posts('gps_ticket', $src_id) as $t) {
            $new_t = wp_insert_post(wp_slash([
                'post_type'   => 'gps_ticket',
                'post_status' => 'draft',
                'post_title'  => self::relabel($t->post_title, $rename_from, $new_label),
                'post_author' => get_current_user_id(),
                'menu_order'  => (int) $t->menu_order,
            ]), true);
            if (is_wp_error($new_t) || !$new_t) {
                continue;
            }
            $new_t = (int) $new_t;
            $get = function ($key) use ($t) {
                return get_post_meta($t->ID, $key, true);
            };
            self::put($new_t, '_gps_event_id', $id);
            foreach (['_gps_ticket_type', '_gps_ticket_price', '_gps_ticket_quantity', '_gps_ticket_features'] as $key) {
                self::put($new_t, $key, $get($key));
            }
            self::put($new_t, '_gps_ticket_internal_label', self::relabel($get('_gps_ticket_internal_label'), $rename_from, $new_label));
            self::put($new_t, '_gps_ticket_start_date', self::shift_year($get('_gps_ticket_start_date')));
            self::put($new_t, '_gps_ticket_end_date', self::shift_year($get('_gps_ticket_end_date')));
            self::put($new_t, '_gps_manual_sold_out', '0');
            self::put($new_t, '_gps_ticket_status', 'inactive');
            // Same path as a ticket type saved without a product (Tickets::save_ticket_meta)
            $product_id = 0;
            if (function_exists('wc_get_product')) {
                $product_id = (int) Tickets::create_woocommerce_product($new_t, $id, (string) $get('_gps_ticket_type'), (float) $get('_gps_ticket_price'), (int) $get('_gps_ticket_quantity'));
            }
            self::put($new_t, '_gps_wc_product_id', $product_id);
            if ($product_id) {
                // Not buyable until the ticket type is published (release_held_product)
                self::set_product_status($product_id, 'draft');
                self::put($new_t, self::META_HELD_PRODUCT, '1');
            }
        }

        do_action('gpsc_edition_added', $id, $src_id, $master);
        return $id;
    }

    /** Nonce + capability for an admin-post action on a course; dies (403) otherwise. */
    protected static function allowed($action, $master) {
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (!$master || !wp_verify_nonce($nonce, $action . '_' . $master)) {
            wp_die(esc_html__('This link has expired. Go back to the course and try again.', 'gps-courses'), '', ['response' => 403, 'back_link' => true]);
            return false;
        }
        if ('gps_event' !== get_post_type($master) || !current_user_can('edit_post', $master)) {
            wp_die(esc_html__('You are not allowed to edit this course.', 'gps-courses'), '', ['response' => 403, 'back_link' => true]);
            return false;
        }
        return true;
    }

    public static function handle_add() {
        $master = isset($_REQUEST['post']) ? absint($_REQUEST['post']) : 0;
        if (!self::allowed(self::ACTION_ADD, $master)) {
            return;
        }
        $new = self::add_edition($master);
        if (is_wp_error($new)) {
            wp_safe_redirect(add_query_arg('gpsc_edition_error', rawurlencode($new->get_error_code()), get_edit_post_link($master, 'raw')));
            exit;
        }
        wp_safe_redirect(add_query_arg('gpsc_edition_added', 1, get_edit_post_link($new, 'raw')));
        exit;
    }

    public static function handle_resync() {
        $master = isset($_REQUEST['post']) ? absint($_REQUEST['post']) : 0;
        if (!self::allowed(self::ACTION_RESYNC, $master)) {
            return;
        }
        Editions::rebuild_map();
        $result = Editions::is_master($master) ? (Editions::sync($master) ? 'changed' : 'same') : 'none';
        wp_safe_redirect(add_query_arg(['gpsc_resynced' => $result, 'gps_tab' => 'editions'], get_edit_post_link($master, 'raw')));
        exit;
    }

    public static function notices() {
        global $pagenow;
        if ('post.php' !== $pagenow) {
            return;
        }
        if (!empty($_GET['gpsc_edition_added'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Draft edition created: dates moved one year later, agenda days copied, ticket types copied as inactive drafts. Review them, publish the ticket types, then publish this edition.', 'gps-courses') . '</p></div>';
        }
        if (!empty($_GET['gpsc_edition_error'])) {
            $code = sanitize_key(wp_unslash($_GET['gpsc_edition_error']));
            $msg  = 'gpsc_no_editions' === $code ? __('This course has no edition to copy yet.', 'gps-courses') : __('The edition could not be created.', 'gps-courses');
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
        if (!empty($_GET['gpsc_resynced'])) {
            $r = sanitize_key(wp_unslash($_GET['gpsc_resynced']));
            $msg = 'changed' === $r ? __('Course page updated from its current edition.', 'gps-courses')
                : ('same' === $r ? __('The course page already matches its current edition.', 'gps-courses') : __('This course has no editions.', 'gps-courses'));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
    }
}
