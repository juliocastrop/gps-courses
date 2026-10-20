<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * List screens of the Courses hub (Courses, Ticket Types, Agendas, Speakers,
 * Sponsors, Categories / Tags / Programs): the columns that answer "what is
 * this and how is it doing", status views and filters.
 *
 * Only presentation and read-only queries: nothing here saves data. Row
 * actions (Edit, Quick Edit, Trash, View) stay WordPress's own: the custom
 * first column is the list's primary column and prints the inline data
 * Quick Edit reads.
 */
class Admin_Lists {

    const POST_TYPES = ['gps_event', 'gps_ticket', 'gps_schedule', 'gps_speaker', 'gps_sponsor'];
    const TAXONOMIES = ['gps_event_category', 'gps_event_tag', 'gps_course_program'];

    /** Per-request caches */
    protected static $course_state = [];
    protected static $speaker_counts = null;

    public static function init() {
        add_filter('manage_gps_event_posts_columns', [__CLASS__, 'course_columns'], 20);
        add_action('manage_gps_event_posts_custom_column', [__CLASS__, 'course_cell'], 10, 2);
        add_filter('manage_edit-gps_event_sortable_columns', [__CLASS__, 'course_sortable']);

        add_filter('manage_gps_ticket_posts_columns', [__CLASS__, 'ticket_columns'], 20);
        add_action('manage_gps_ticket_posts_custom_column', [__CLASS__, 'ticket_cell'], 10, 2);
        add_filter('manage_edit-gps_ticket_sortable_columns', [__CLASS__, 'ticket_sortable'], 20);

        add_filter('manage_gps_schedule_posts_columns', [__CLASS__, 'agenda_columns'], 20);
        add_action('manage_gps_schedule_posts_custom_column', [__CLASS__, 'agenda_cell'], 10, 2);
        add_filter('manage_edit-gps_schedule_sortable_columns', [__CLASS__, 'agenda_sortable']);

        add_filter('manage_gps_speaker_posts_columns', [__CLASS__, 'speaker_columns'], 20);
        add_action('manage_gps_speaker_posts_custom_column', [__CLASS__, 'speaker_cell'], 10, 2);

        add_filter('manage_gps_sponsor_posts_columns', [__CLASS__, 'sponsor_columns'], 20);
        add_action('manage_gps_sponsor_posts_custom_column', [__CLASS__, 'sponsor_cell'], 10, 2);

        add_filter('list_table_primary_column', [__CLASS__, 'primary_column'], 10, 2);
        add_filter('default_hidden_columns', [__CLASS__, 'default_hidden'], 10, 2);
        add_action('admin_init', [__CLASS__, 'reset_hidden_columns_once']);

        add_filter('views_edit-gps_event', [__CLASS__, 'course_views']);
        add_filter('views_edit-gps_ticket', [__CLASS__, 'ticket_views']);
        add_action('restrict_manage_posts', [__CLASS__, 'filters'], 10, 2);
        add_action('pre_get_posts', [__CLASS__, 'filter_query']);

        add_action('admin_footer-edit.php', [__CLASS__, 'subtitle']);
    }

    /* ============================================================
     * Shared bits
     * ============================================================ */

    protected static function badge($label, $tone = 'neutral') {
        return '<span class="gps-badge gps-badge--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    /** "13 / 20 sold" with a meter; $total null = unlimited. */
    protected static function meter($sold, $total, $tone = 'info', $format = null) {
        if (null === $total) {
            /* translators: %d: tickets sold */
            return '<div class="gps-meter__label">' . esc_html(sprintf(__('%d sold · no limit', 'gps-courses'), $sold)) . '</div>';
        }
        $pct  = $total > 0 ? min(100, max($sold > 0 ? 2 : 0, round($sold * 100 / $total))) : 0;
        /* translators: 1: sold, 2: total */
        $text = sprintf($format ?: __('%1$d / %2$d sold', 'gps-courses'), $sold, $total);
        return '<div class="gps-meter__label">' . esc_html($text) . '</div>'
            . '<div class="gps-meter" role="presentation"><span class="gps-meter__bar gps-meter__bar--' . esc_attr($tone) . '" style="width:' . (int) $pct . '%"></span></div>';
    }

    protected static function money($amount) {
        if (null === $amount || '' === $amount) {
            return '<span class="gps-muted">—</span>';
        }
        $amount = (float) $amount;
        if (function_exists('wc_price')) {
            return wp_kses_post(wc_price($amount, ['decimals' => floor($amount) == $amount ? 0 : 2]));
        }
        return esc_html('$' . number_format($amount, floor($amount) == $amount ? 0 : 2));
    }

    protected static function dash() {
        return '<span class="gps-muted">—</span>';
    }

    /** Status badge for posts that are not published. Null when published. */
    protected static function post_status_badge($post) {
        switch ($post->post_status) {
            case 'publish':
                return null;
            case 'future':
                return self::badge(__('Scheduled', 'gps-courses'), 'info');
            case 'pending':
                return self::badge(__('Pending review', 'gps-courses'), 'warn');
            case 'private':
                return self::badge(__('Private', 'gps-courses'), 'neutral');
            case 'trash':
                return self::badge(__('In trash', 'gps-courses'), 'bad');
            default:
                return self::badge(__('Draft', 'gps-courses'), 'warn');
        }
    }

    /**
     * The custom first column: title link, post states and Quick Edit data,
     * as WordPress prints them in its own Title column. Row actions are added
     * by WordPress because this is the primary column.
     */
    protected static function title_cell($post, $sub = '') {
        $can_edit = current_user_can('edit_post', $post->ID);
        $title    = _draft_or_post_title($post);
        echo '<strong>';
        if ($can_edit && 'trash' !== $post->post_status) {
            echo '<a class="row-title" href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html($title) . '</a>';
        } else {
            echo '<span class="row-title">' . esc_html($title) . '</span>';
        }
        _post_states($post);
        echo '</strong>';
        if ('' !== $sub) {
            echo '<div class="gps-sub">' . $sub . '</div>'; // escaped by callers
        }
        if ($can_edit && function_exists('get_inline_data')) {
            get_inline_data($post);
        }
    }

    public static function primary_column($default, $screen_id) {
        $map = [
            'edit-gps_event'    => 'gps_course',
            'edit-gps_ticket'   => 'gps_ticket',
            'edit-gps_schedule' => 'gps_agenda',
            'edit-gps_speaker'  => 'gps_speaker',
            'edit-gps_sponsor'  => 'gps_sponsor',
        ];
        return isset($map[$screen_id]) ? $map[$screen_id] : $default;
    }

    /** Columns that stay available in Screen Options but start hidden. */
    protected static function hidden_by_default($screen_id) {
        $yoast = ['wpseo-score-readability', 'wpseo-title', 'wpseo-metadesc', 'wpseo-focuskw', 'wpseo-links', 'wpseo-linked', 'wpseo-cornerstone'];
        switch ($screen_id) {
            case 'edit-gps_event':
                return array_merge($yoast, ['taxonomy-gps_event_category', 'taxonomy-gps_event_tag', 'date']);
            case 'edit-gps_ticket':
            case 'edit-gps_schedule':
            case 'edit-gps_speaker':
            case 'edit-gps_sponsor':
                return array_merge($yoast, ['wpseo-score', 'date']);
            case 'edit-gps_event_category':
            case 'edit-gps_event_tag':
            case 'edit-gps_course_program':
                return ['wpseo-score', 'wpseo-score-readability', 'wpseo-cornerstone'];
        }
        return [];
    }

    public static function default_hidden($hidden, $screen) {
        $extra = $screen ? self::hidden_by_default($screen->id) : [];
        return $extra ? array_values(array_unique(array_merge((array) $hidden, $extra))) : $hidden;
    }

    /**
     * Saved Screen Options from before the redesign would keep every Yoast
     * column visible; clear them once so the new defaults apply. Users can
     * still show any column again from Screen Options.
     */
    public static function reset_hidden_columns_once() {
        if (get_option('gps_admin_lists_columns_v1')) {
            return;
        }
        $screens = array_merge(
            array_map(function ($pt) { return 'edit-' . $pt; }, self::POST_TYPES),
            array_map(function ($tx) { return 'edit-' . $tx; }, self::TAXONOMIES)
        );
        foreach ($screens as $id) {
            delete_metadata('user', 0, 'manage' . $id . 'columnshidden', '', true);
        }
        update_option('gps_admin_lists_columns_v1', 1, false);
    }

    /** Only the published / any-status ticket types of a course. */
    protected static function course_tickets($course_id) {
        return get_posts([
            'post_type'      => 'gps_ticket',
            'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'meta_query'     => [['key' => '_gps_event_id', 'value' => (int) $course_id, 'type' => 'NUMERIC']],
        ]);
    }

    /* ============================================================
     * Courses
     * ============================================================ */

    /**
     * Where a course stands: status key/label/tone, seats of the current
     * edition and the lowest price on sale. Upcoming courses count only the
     * ticket types whose sale has not ended (a reused course keeps last
     * year's tickets); past courses count all of them.
     */
    public static function course_state($post) {
        $post = get_post($post);
        if (!$post) {
            return null;
        }
        if (isset(self::$course_state[$post->ID])) {
            return self::$course_state[$post->ID];
        }
        $today = current_time('Y-m-d');
        $start = (string) get_post_meta($post->ID, '_gps_start_date', true);
        $end   = (string) get_post_meta($post->ID, '_gps_end_date', true) ?: $start;
        $past  = '' !== $end && $end < $today;

        $tickets = array_filter(self::course_tickets($post->ID), function ($t) {
            return 'publish' === $t->post_status;
        });
        $cutoff  = $start ? gmdate('Y-m-d', strtotime($start . ' -6 months')) : '';
        $current = $past ? $tickets : array_filter($tickets, function ($t) use ($cutoff) {
            if (!Tickets::sale_ended($t->ID)) {
                return true;
            }
            $ended = substr((string) get_post_meta($t->ID, '_gps_ticket_end_date', true), 0, 10);
            return $cutoff && $ended >= $cutoff;
        });

        $sold = 0;
        $total = 0;
        $unlimited = false;
        $price = null;
        $on_sale = 0;
        $opens = '';
        // Sold out = every ticket type still selling is sold out (ended early
        // birds count for seats, not for this)
        $selling = array_filter($current, function ($t) {
            return !Tickets::sale_ended($t->ID);
        });
        $all_sold_out = (bool) $selling;
        foreach ($selling as $t) {
            if (!Tickets::is_sold_out($t->ID)) {
                $all_sold_out = false;
            }
        }
        foreach ($current as $t) {
            $stock = Tickets::get_ticket_stock($t->ID);
            $sold += $stock['sold'];
            if ($stock['unlimited']) {
                $unlimited = true;
            } else {
                $total += $stock['total'];
            }
            $active = 'active' === get_post_meta($t->ID, '_gps_ticket_status', true);
            if ($active && !Tickets::is_sold_out($t->ID)) {
                $on_sale++;
                $p = get_post_meta($t->ID, '_gps_ticket_price', true);
                if ('' !== $p && (null === $price || (float) $p < $price)) {
                    $price = (float) $p;
                }
            } elseif (!$active) {
                $s = (string) get_post_meta($t->ID, '_gps_ticket_start_date', true);
                if ($s && str_replace('T', ' ', $s) > current_time('Y-m-d H:i') && ('' === $opens || $s < $opens)) {
                    $opens = $s;
                }
            }
        }
        if (null === $price) {
            foreach ($current as $t) {
                $p = get_post_meta($t->ID, '_gps_ticket_price', true);
                if ('' !== $p && (null === $price || (float) $p < $price)) {
                    $price = (float) $p;
                }
            }
        }

        if ('publish' !== $post->post_status) {
            $key = 'draft';
        } elseif ($past) {
            $key = 'past';
        } elseif ($on_sale) {
            $key = 'open';
        } elseif ($all_sold_out) {
            $key = 'sold_out';
        } else {
            $key = 'soon';
        }

        return self::$course_state[$post->ID] = [
            'key'     => $key,
            'past'    => $past,
            'start'   => $start,
            'end'     => $end,
            'opens'   => $opens,
            'sold'    => $sold,
            'total'   => $unlimited ? null : $total,
            'tickets' => count($current),
            'price'   => $price,
        ];
    }

    public static function course_columns($columns) {
        $out = [];
        $out['cb']         = isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />';
        $out['gps_course'] = __('Course', 'gps-courses');
        $out['gps_status'] = __('Status', 'gps-courses');
        $out['gps_seats']  = __('Seats', 'gps-courses');
        $out['gps_from']   = __('From', 'gps-courses');
        $out['gps_program'] = __('Program', 'gps-courses');
        unset($columns['cb'], $columns['title'], $columns['taxonomy-gps_course_program']);
        // Everything else (AI score, Yoast, categories, tags, date) keeps its
        // place after ours and can be shown / hidden in Screen Options
        return array_merge($out, $columns);
    }

    public static function course_sortable($columns) {
        $columns['gps_course'] = 'title';
        $columns['gps_status'] = 'gps_start';
        return $columns;
    }

    public static function course_cell($column, $post_id) {
        $post = get_post($post_id);
        switch ($column) {
            case 'gps_course':
                $parts = [];
                $s = self::course_state($post);
                if ($s['start']) {
                    $parts[] = format_date_range($s['start'], $s['end']) ?: date_i18n(get_option('date_format'), strtotime($s['start']));
                }
                $city  = (string) get_post_meta($post_id, '_gps_city', true);
                $state = (string) get_post_meta($post_id, '_gps_state', true);
                if ($city) {
                    $parts[] = $city . ($state ? ', ' . $state : '');
                }
                $ce = (float) get_post_meta($post_id, '_gps_ce_credits', true);
                if ($ce > 0) {
                    /* translators: %s: CE credits */
                    $parts[] = sprintf(__('%s CE', 'gps-courses'), Credits::format($ce));
                }
                if ('publish' !== $post->post_status) {
                    /* translators: %s: date */
                    $parts[] = sprintf(__('last edited %s', 'gps-courses'), date_i18n(get_option('date_format'), strtotime($post->post_modified)));
                }
                self::title_cell($post, esc_html(implode(' · ', $parts)));
                break;

            case 'gps_status':
                $s = self::course_state($post);
                $badge = self::post_status_badge($post);
                if ($badge) {
                    echo $badge;
                    break;
                }
                $map = [
                    'open'     => [__('Registration open', 'gps-courses'), 'ok'],
                    'sold_out' => [__('Sold out', 'gps-courses'), 'bad'],
                    'soon'     => [__('Coming soon', 'gps-courses'), 'info'],
                    'past'     => [__('Took place', 'gps-courses'), 'neutral'],
                ];
                echo self::badge($map[$s['key']][0], $map[$s['key']][1]);
                if ('soon' === $s['key'] && $s['opens']) {
                    /* translators: %s: date */
                    echo '<div class="gps-sub">' . esc_html(sprintf(__('Sales open %s', 'gps-courses'), date_i18n(get_option('date_format'), strtotime(str_replace('T', ' ', $s['opens']))))) . '</div>';
                } elseif ('soon' === $s['key'] && !$s['tickets']) {
                    echo '<div class="gps-sub">' . esc_html__('No ticket on sale', 'gps-courses') . '</div>';
                }
                break;

            case 'gps_seats':
                $s = self::course_state($post);
                if (!$s['tickets']) {
                    echo '<span class="gps-muted">' . esc_html__('No tickets yet', 'gps-courses') . '</span>';
                    break;
                }
                $tone = $s['past'] ? 'neutral' : ('draft' === $s['key'] ? 'warn' : 'info');
                echo self::meter($s['sold'], $s['total'], $tone);
                break;

            case 'gps_from':
                echo self::money(self::course_state($post)['price']);
                break;

            case 'gps_program':
                $terms = get_the_terms($post_id, 'gps_course_program');
                if (!$terms || is_wp_error($terms)) {
                    echo self::dash();
                    break;
                }
                $links = [];
                foreach ($terms as $term) {
                    $links[] = '<a href="' . esc_url(add_query_arg(['post_type' => 'gps_event', 'gps_program' => $term->slug], admin_url('edit.php'))) . '">' . esc_html($term->name) . '</a>';
                }
                echo implode(', ', $links);
                break;
        }
    }

    /** Course ids per state, for the views and the "Open" / "Coming soon" filters. */
    protected static function course_ids_by_state() {
        static $ids = null;
        if (null !== $ids) {
            return $ids;
        }
        $ids = ['open' => [], 'soon' => [], 'sold_out' => [], 'past' => []];
        $posts = get_posts([
            'post_type'      => 'gps_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);
        foreach ($posts as $p) {
            $s = self::course_state($p);
            if (isset($ids[$s['key']])) {
                $ids[$s['key']][] = $p->ID;
            }
        }
        return $ids;
    }

    protected static function view_link($post_type, $param, $value, $label, $count, $current) {
        $url = add_query_arg(['post_type' => $post_type, $param => $value], admin_url('edit.php'));
        $on  = $current === $value;
        return sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            esc_url($url),
            $on ? ' class="current" aria-current="page"' : '',
            esc_html($label),
            (int) $count
        );
    }

    public static function course_views($views) {
        $ids = self::course_ids_by_state();
        $cur = isset($_GET['gps_when']) ? sanitize_key(wp_unslash($_GET['gps_when'])) : '';
        if ($cur) {
            // Our view is the current one, not "All"
            foreach ($views as $k => $v) {
                $views[$k] = str_replace([' class="current" aria-current="page"', 'class="current"'], '', $v);
            }
        }
        $new = [];
        foreach ($views as $key => $view) {
            $new[$key] = $view;
            if ('all' === $key) {
                $new['gps_open']     = self::view_link('gps_event', 'gps_when', 'open', __('Registration open', 'gps-courses'), count($ids['open']), $cur);
                $new['gps_soon']     = self::view_link('gps_event', 'gps_when', 'soon', __('Coming soon', 'gps-courses'), count($ids['soon']), $cur);
                if ($ids['sold_out']) {
                    $new['gps_sold_out'] = self::view_link('gps_event', 'gps_when', 'sold_out', __('Sold out', 'gps-courses'), count($ids['sold_out']), $cur);
                }
                $new['gps_past']     = self::view_link('gps_event', 'gps_when', 'past', __('Took place', 'gps-courses'), count($ids['past']), $cur);
            }
        }
        return $new;
    }

    /* ============================================================
     * Ticket types
     * ============================================================ */

    /** on_sale | scheduled | ended | sold_out | inactive (+ draft) */
    public static function ticket_state($ticket_id) {
        if (Tickets::sale_ended($ticket_id)) {
            return 'ended';
        }
        if (Tickets::is_sold_out($ticket_id)) {
            return 'sold_out';
        }
        if ('active' === get_post_meta($ticket_id, '_gps_ticket_status', true)) {
            return 'on_sale';
        }
        $start = (string) get_post_meta($ticket_id, '_gps_ticket_start_date', true);
        if ($start && str_replace('T', ' ', $start) > current_time('Y-m-d H:i')) {
            return 'scheduled';
        }
        return 'inactive';
    }

    public static function ticket_columns($columns) {
        $out = [
            'cb'            => isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />',
            'gps_ticket'    => __('Ticket type', 'gps-courses'),
            'gps_course'    => __('Course', 'gps-courses'),
            'gps_type'      => __('Type', 'gps-courses'),
            'gps_price'     => __('Price', 'gps-courses'),
            'gps_sold'      => __('Sold', 'gps-courses'),
            'gps_sale'      => __('Sale', 'gps-courses'),
        ];
        // The old columns (internal label, event, type, price, stock, status) are replaced
        unset($columns['cb'], $columns['title'], $columns['internal_label'], $columns['event'], $columns['ticket_type'], $columns['price'], $columns['stock'], $columns['status']);
        return array_merge($out, $columns);
    }

    public static function ticket_sortable($columns) {
        unset($columns['event'], $columns['ticket_type'], $columns['price'], $columns['status']);
        $columns['gps_ticket'] = 'title';
        $columns['gps_price']  = 'gps_price';
        return $columns;
    }

    public static function ticket_cell($column, $post_id) {
        $post = get_post($post_id);
        switch ($column) {
            case 'gps_ticket':
                $label = (string) get_post_meta($post_id, '_gps_ticket_internal_label', true);
                /* translators: %s: internal label */
                self::title_cell($post, $label ? esc_html(sprintf(__('Internal label: %s', 'gps-courses'), $label)) : '');
                break;

            case 'gps_course':
                $event_id = (int) get_post_meta($post_id, '_gps_event_id', true);
                $event = $event_id ? get_post($event_id) : null;
                if (!$event) {
                    echo '<span class="gps-badge gps-badge--bad">' . esc_html__('No course', 'gps-courses') . '</span>';
                    break;
                }
                echo '<a href="' . esc_url(get_edit_post_link($event_id)) . '">' . esc_html(get_the_title($event)) . '</a>';
                break;

            case 'gps_type':
                $type = (string) get_post_meta($post_id, '_gps_ticket_type', true);
                if (!$type) {
                    echo self::dash();
                    break;
                }
                $tones = ['early_bird' => 'gold', 'general' => 'info', 'vip' => 'bad', 'group' => 'warn'];
                $labels = ['early_bird' => __('Early Bird', 'gps-courses'), 'general' => __('General', 'gps-courses'), 'vip' => __('VIP', 'gps-courses'), 'group' => __('Group', 'gps-courses')];
                echo self::badge(isset($labels[$type]) ? $labels[$type] : ucwords(str_replace('_', ' ', $type)), isset($tones[$type]) ? $tones[$type] : 'neutral');
                break;

            case 'gps_price':
                echo self::money(get_post_meta($post_id, '_gps_ticket_price', true));
                break;

            case 'gps_sold':
                $stock = Tickets::get_ticket_stock($post_id);
                if (!$stock['unlimited'] && 0 === (int) $stock['total']) {
                    echo '<span class="gps-muted">' . esc_html__('No seats set', 'gps-courses') . '</span>';
                    break;
                }
                $state = self::ticket_state($post_id);
                /* translators: 1: sold, 2: total */
                echo self::meter($stock['sold'], $stock['unlimited'] ? null : (int) $stock['total'], 'on_sale' === $state ? 'info' : 'neutral', __('%1$d of %2$d', 'gps-courses'));
                break;

            case 'gps_sale':
                if ($badge = self::post_status_badge($post)) {
                    echo $badge;
                    break;
                }
                $state = self::ticket_state($post_id);
                $fmt   = function ($v) {
                    $v = str_replace('T', ' ', (string) $v);
                    return $v ? date_i18n(get_option('date_format'), strtotime($v)) : '';
                };
                $start = $fmt(get_post_meta($post_id, '_gps_ticket_start_date', true));
                $end   = $fmt(get_post_meta($post_id, '_gps_ticket_end_date', true));
                $map = [
                    'on_sale'   => [__('On sale', 'gps-courses'), 'ok', $end ? sprintf(__('Until %s', 'gps-courses'), $end) : __('No end date', 'gps-courses')],
                    'scheduled' => [__('Scheduled', 'gps-courses'), 'info', $start ? sprintf(__('Opens %s', 'gps-courses'), $start) : ''],
                    'ended'     => [__('Ended', 'gps-courses'), 'neutral', $end ? sprintf(__('Ended %s', 'gps-courses'), $end) : ''],
                    'sold_out'  => [__('Sold out', 'gps-courses'), 'bad', get_post_meta($post_id, '_gps_manual_sold_out', true) ? __('Marked sold out', 'gps-courses') : ''],
                    'inactive'  => [__('Not on sale', 'gps-courses'), 'warn', ''],
                ];
                echo self::badge($map[$state][0], $map[$state][1]);
                if ($map[$state][2]) {
                    echo '<div class="gps-sub">' . esc_html($map[$state][2]) . '</div>';
                }
                break;
        }
    }

    public static function ticket_views($views) {
        $cur = isset($_GET['gps_sale']) ? sanitize_key(wp_unslash($_GET['gps_sale'])) : '';
        $counts = ['on_sale' => 0, 'ended' => 0, 'sold_out' => 0];
        foreach (get_posts(['post_type' => 'gps_ticket', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]) as $id) {
            $s = self::ticket_state($id);
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }
        if ($cur) {
            foreach ($views as $k => $v) {
                $views[$k] = str_replace([' class="current" aria-current="page"', 'class="current"'], '', $v);
            }
        }
        $new = [];
        foreach ($views as $key => $view) {
            $new[$key] = $view;
            if ('all' === $key) {
                $new['gps_on_sale']  = self::view_link('gps_ticket', 'gps_sale', 'on_sale', __('On sale', 'gps-courses'), $counts['on_sale'], $cur);
                $new['gps_ended']    = self::view_link('gps_ticket', 'gps_sale', 'ended', __('Ended', 'gps-courses'), $counts['ended'], $cur);
                $new['gps_sold_out'] = self::view_link('gps_ticket', 'gps_sale', 'sold_out', __('Sold out', 'gps-courses'), $counts['sold_out'], $cur);
            }
        }
        return $new;
    }

    /* ============================================================
     * Agendas
     * ============================================================ */

    public static function agenda_columns($columns) {
        $out = [
            'cb'          => isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />',
            'gps_agenda'  => __('Agenda', 'gps-courses'),
            'gps_course'  => __('Course', 'gps-courses'),
            'gps_day'     => __('Day', 'gps-courses'),
            'gps_topics'  => __('Topics', 'gps-courses'),
            'gps_pstatus' => __('Status', 'gps-courses'),
        ];
        unset($columns['cb'], $columns['title'], $columns['event'], $columns['schedule_date'], $columns['topics_count']);
        return array_merge($out, $columns);
    }

    public static function agenda_sortable($columns) {
        $columns['gps_agenda'] = 'title';
        $columns['gps_day']    = 'gps_day';
        return $columns;
    }

    /** "Day 2 of 3" among the agendas of the same course, by date. */
    protected static function agenda_day($post_id) {
        $event_id = (int) get_post_meta($post_id, '_gps_event_id', true);
        if (!$event_id) {
            return '';
        }
        static $by_course = [];
        if (!isset($by_course[$event_id])) {
            $ids = get_posts([
                'post_type'      => 'gps_schedule',
                'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_key'       => '_gps_schedule_date',
                'orderby'        => ['meta_value' => 'ASC', 'ID' => 'ASC'],
                'meta_query'     => [['key' => '_gps_event_id', 'value' => $event_id, 'type' => 'NUMERIC']],
            ]);
            $by_course[$event_id] = array_map('intval', $ids);
        }
        $n = array_search((int) $post_id, $by_course[$event_id], true);
        if (false === $n || count($by_course[$event_id]) < 2) {
            return '';
        }
        /* translators: 1: day number, 2: number of days */
        return sprintf(__('Day %1$d of %2$d', 'gps-courses'), $n + 1, count($by_course[$event_id]));
    }

    public static function agenda_cell($column, $post_id) {
        $post = get_post($post_id);
        switch ($column) {
            case 'gps_agenda':
                self::title_cell($post, esc_html(self::agenda_day($post_id)));
                break;

            case 'gps_course':
                $event_id = (int) get_post_meta($post_id, '_gps_event_id', true);
                $event = $event_id ? get_post($event_id) : null;
                echo $event ? '<a href="' . esc_url(get_edit_post_link($event_id)) . '">' . esc_html(get_the_title($event)) . '</a>' : '<span class="gps-badge gps-badge--bad">' . esc_html__('No course', 'gps-courses') . '</span>';
                break;

            case 'gps_day':
                $date = (string) get_post_meta($post_id, '_gps_schedule_date', true);
                echo $date ? esc_html(date_i18n('D, ' . get_option('date_format'), strtotime($date))) : self::dash();
                break;

            case 'gps_topics':
                $topics = json_decode((string) get_post_meta($post_id, '_gps_schedule_topics', true), true);
                $n = is_array($topics) ? count($topics) : 0;
                /* translators: %d: number of topics */
                echo $n ? self::badge(sprintf(_n('%d topic', '%d topics', $n, 'gps-courses'), $n), 'info') : self::badge(__('No topics', 'gps-courses'), 'warn');
                break;

            case 'gps_pstatus':
                echo self::post_status_badge($post) ?: self::badge(__('Published', 'gps-courses'), 'ok');
                break;
        }
    }

    /* ============================================================
     * Speakers and sponsors
     * ============================================================ */

    /** Courses (any status but trash) listing each speaker. */
    protected static function speaker_counts() {
        if (null !== self::$speaker_counts) {
            return self::$speaker_counts;
        }
        self::$speaker_counts = [];
        $courses = get_posts(['post_type' => 'gps_event', 'post_status' => ['publish', 'draft', 'pending', 'future', 'private'], 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
        foreach ($courses as $cid) {
            $ids = get_post_meta($cid, '_gps_speaker_ids', true);
            foreach (array_unique(array_map('intval', (array) $ids)) as $sid) {
                if ($sid > 0) {
                    self::$speaker_counts[$sid] = (isset(self::$speaker_counts[$sid]) ? self::$speaker_counts[$sid] : 0) + 1;
                }
            }
        }
        return self::$speaker_counts;
    }

    /** Published courses showing a sponsor (their own list, or the site default). */
    protected static function sponsor_count($sponsor_id) {
        static $counts = null;
        if (null === $counts) {
            $counts   = [];
            $defaults = array_map('intval', (array) get_option('gps_default_sponsor_ids', []));
            $courses  = get_posts(['post_type' => 'gps_event', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
            foreach ($courses as $cid) {
                $mode = (string) get_post_meta($cid, '_gps_sponsors_mode', true);
                if ('none' === $mode) {
                    continue;
                }
                $ids = 'custom' === $mode ? array_map('intval', (array) get_post_meta($cid, '_gps_sponsor_ids', true)) : $defaults;
                foreach (array_unique($ids) as $sid) {
                    $counts[$sid] = (isset($counts[$sid]) ? $counts[$sid] : 0) + 1;
                }
            }
        }
        return isset($counts[(int) $sponsor_id]) ? $counts[(int) $sponsor_id] : 0;
    }

    protected static function thumb($post_id, $shape) {
        $img = get_the_post_thumbnail($post_id, [80, 80], ['class' => 'gps-thumb gps-thumb--' . $shape, 'alt' => '']);
        return $img ?: '<span class="gps-thumb gps-thumb--' . esc_attr($shape) . ' gps-thumb--empty" aria-hidden="true"></span>';
    }

    public static function speaker_columns($columns) {
        $out = [
            'cb'           => isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />',
            'gps_photo'    => '<span class="screen-reader-text">' . esc_html__('Photo', 'gps-courses') . '</span>',
            'gps_speaker'  => __('Speaker', 'gps-courses'),
            'gps_role'     => __('Title / company', 'gps-courses'),
            'gps_courses'  => __('Courses', 'gps-courses'),
            'gps_pstatus'  => __('Status', 'gps-courses'),
        ];
        unset($columns['cb'], $columns['title']);
        return array_merge($out, $columns);
    }

    public static function speaker_cell($column, $post_id) {
        $post = get_post($post_id);
        switch ($column) {
            case 'gps_photo':
                echo self::thumb($post_id, 'round');
                break;
            case 'gps_speaker':
                self::title_cell($post, esc_html((string) get_post_meta($post_id, '_gps_designation', true)));
                break;
            case 'gps_role':
                $company = (string) get_post_meta($post_id, '_gps_company', true);
                echo $company ? esc_html($company) : self::dash();
                break;
            case 'gps_courses':
                $counts = self::speaker_counts();
                $n = isset($counts[$post_id]) ? $counts[$post_id] : 0;
                /* translators: %d: number of courses */
                echo $n ? esc_html(sprintf(_n('%d course', '%d courses', $n, 'gps-courses'), $n)) : '<span class="gps-muted">' . esc_html__('Not on a course', 'gps-courses') . '</span>';
                break;
            case 'gps_pstatus':
                echo self::post_status_badge($post) ?: self::badge(__('Published', 'gps-courses'), 'ok');
                break;
        }
    }

    public static function sponsor_columns($columns) {
        $out = [
            'cb'          => isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />',
            'gps_logo'    => '<span class="screen-reader-text">' . esc_html__('Logo', 'gps-courses') . '</span>',
            'gps_sponsor' => __('Sponsor', 'gps-courses'),
            'gps_website' => __('Website', 'gps-courses'),
            'gps_courses' => __('Courses', 'gps-courses'),
            'gps_pstatus' => __('Status', 'gps-courses'),
        ];
        unset($columns['cb'], $columns['title']);
        return array_merge($out, $columns);
    }

    public static function sponsor_cell($column, $post_id) {
        $post = get_post($post_id);
        switch ($column) {
            case 'gps_logo':
                echo self::thumb($post_id, 'logo');
                break;
            case 'gps_sponsor':
                /* translators: %s: date */
                self::title_cell($post, esc_html(sprintf(__('Added %s', 'gps-courses'), get_the_date('', $post))));
                break;
            case 'gps_website':
                $url = (string) get_post_meta($post_id, '_gps_sponsor_url', true);
                echo $url ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html(preg_replace('#^https?://(www\.)?#', '', untrailingslashit($url))) . '</a>' : self::dash();
                break;
            case 'gps_courses':
                $n = self::sponsor_count($post_id);
                /* translators: %d: number of courses */
                echo $n ? esc_html(sprintf(_n('%d course', '%d courses', $n, 'gps-courses'), $n)) : '<span class="gps-muted">' . esc_html__('Not shown on a course', 'gps-courses') . '</span>';
                break;
            case 'gps_pstatus':
                echo self::post_status_badge($post) ?: self::badge(__('Published', 'gps-courses'), 'ok');
                break;
        }
    }

    /* ============================================================
     * Filters
     * ============================================================ */

    /** Course dropdown (ticket types, agendas) and Program dropdown (courses). */
    public static function filters($post_type, $which = 'top') {
        if ('top' !== $which) {
            return;
        }
        if (in_array($post_type, ['gps_ticket', 'gps_schedule'], true)) {
            $cur = isset($_GET['gps_course']) ? absint($_GET['gps_course']) : 0;
            echo '<label class="screen-reader-text" for="gps-filter-course">' . esc_html__('Filter by course', 'gps-courses') . '</label>';
            echo '<select name="gps_course" id="gps-filter-course"><option value="">' . esc_html__('All courses', 'gps-courses') . '</option>';
            foreach (course_select_options($cur) as $id => $title) {
                echo '<option value="' . (int) $id . '"' . selected($cur, $id, false) . '>' . esc_html($title) . '</option>';
            }
            echo '</select>';
        }
        if ('gps_event' === $post_type) {
            if (isset($_GET['gps_when'])) {
                echo '<input type="hidden" name="gps_when" value="' . esc_attr(sanitize_key(wp_unslash($_GET['gps_when']))) . '">';
            }
            $terms = get_terms(['taxonomy' => 'gps_course_program', 'hide_empty' => false]);
            if (!$terms || is_wp_error($terms)) {
                return;
            }
            $cur = isset($_GET['gps_program']) ? sanitize_title(wp_unslash($_GET['gps_program'])) : '';
            echo '<label class="screen-reader-text" for="gps-filter-program">' . esc_html__('Filter by program', 'gps-courses') . '</label>';
            echo '<select name="gps_program" id="gps-filter-program"><option value="">' . esc_html__('All programs', 'gps-courses') . '</option>';
            foreach ($terms as $t) {
                echo '<option value="' . esc_attr($t->slug) . '"' . selected($cur, $t->slug, false) . '>' . esc_html($t->name) . '</option>';
            }
            echo '</select>';
        }
        if ('gps_ticket' === $post_type && isset($_GET['gps_sale'])) {
            echo '<input type="hidden" name="gps_sale" value="' . esc_attr(sanitize_key(wp_unslash($_GET['gps_sale']))) . '">';
        }
    }

    public static function filter_query($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        global $pagenow;
        if ('edit.php' !== $pagenow) {
            return;
        }
        $pt = $query->get('post_type');

        if ('gps_event' === $pt) {
            if (!empty($_GET['gps_program'])) {
                $tax = array_filter((array) $query->get('tax_query'), 'is_array');
                $tax[] = ['taxonomy' => 'gps_course_program', 'field' => 'slug', 'terms' => sanitize_title(wp_unslash($_GET['gps_program']))];
                $query->set('tax_query', $tax);
            }
            $when = isset($_GET['gps_when']) ? sanitize_key(wp_unslash($_GET['gps_when'])) : '';
            if ($when) {
                $ids = self::course_ids_by_state();
                $query->set('post__in', isset($ids[$when]) && $ids[$when] ? $ids[$when] : [0]);
                $query->set('post_status', 'publish');
            }
            if ('gps_start' === $query->get('orderby')) {
                self::sort_by_meta($query, '_gps_start_date', 'CHAR');
            }
        }

        if (in_array($pt, ['gps_ticket', 'gps_schedule'], true) && !empty($_GET['gps_course'])) {
            $meta = array_filter((array) $query->get('meta_query'), 'is_array');
            $meta[] = ['key' => '_gps_event_id', 'value' => absint($_GET['gps_course']), 'type' => 'NUMERIC'];
            $query->set('meta_query', $meta);
        }

        if ('gps_ticket' === $pt) {
            $sale = isset($_GET['gps_sale']) ? sanitize_key(wp_unslash($_GET['gps_sale'])) : '';
            if ($sale) {
                $ids = array_filter(
                    get_posts(['post_type' => 'gps_ticket', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]),
                    function ($id) use ($sale) {
                        return self::ticket_state($id) === $sale;
                    }
                );
                $query->set('post__in', $ids ? array_values($ids) : [0]);
                $query->set('post_status', 'publish');
            }
            if ('gps_price' === $query->get('orderby')) {
                self::sort_by_meta($query, '_gps_ticket_price', 'DECIMAL(10,2)');
            }
        }

        if ('gps_schedule' === $pt && 'gps_day' === $query->get('orderby')) {
            self::sort_by_meta($query, '_gps_schedule_date', 'CHAR');
        }
    }

    /**
     * Order by a meta value without dropping the rows that don't have it
     * (meta_key alone is an inner join: undated drafts would disappear).
     */
    protected static function sort_by_meta($query, $key, $type) {
        $meta = array_filter((array) $query->get('meta_query'), 'is_array');
        $meta[] = [
            'relation' => 'OR',
            'gps_sort' => ['key' => $key, 'compare' => 'EXISTS', 'type' => $type],
            ['key' => $key, 'compare' => 'NOT EXISTS'],
        ];
        $meta['relation'] = 'AND';
        $query->set('meta_query', $meta);
        $order = 'ASC' === strtoupper((string) $query->get('order')) ? 'ASC' : 'DESC';
        $query->set('orderby', ['gps_sort' => $order, 'title' => 'ASC']);
    }

    /* ============================================================
     * One-line description next to the screen title
     * ============================================================ */

    public static function subtitle() {
        $screen = get_current_screen();
        if (!$screen || 'edit' !== $screen->base || !in_array($screen->post_type, self::POST_TYPES, true)) {
            return;
        }
        $texts = [
            'gps_ticket'   => __('What each course sells: price, seats and sale dates.', 'gps-courses'),
            'gps_schedule' => __('The day-by-day schedule shown on each course page.', 'gps-courses'),
            'gps_speaker'  => __('Faculty shown on course pages and the speaker grid.', 'gps-courses'),
            'gps_sponsor'  => __('Partner logos shown on course pages.', 'gps-courses'),
        ];
        if ('gps_event' === $screen->post_type) {
            $ids   = self::course_ids_by_state();
            $count = wp_count_posts('gps_event');
            $all   = (int) $count->publish + (int) $count->draft + (int) $count->pending + (int) $count->future + (int) $count->private;
            $text  = implode(' · ', [
                /* translators: %d: number of courses */
                sprintf(_n('%d course', '%d courses', $all, 'gps-courses'), $all),
                /* translators: %d: number of courses open for registration */
                sprintf(__('%d open for registration', 'gps-courses'), count($ids['open'])),
                /* translators: %d: number of drafts */
                sprintf(_n('%d draft', '%d drafts', (int) $count->draft, 'gps-courses'), (int) $count->draft),
            ]);
        } else {
            $text = $texts[$screen->post_type];
        }
        ?>
        <script>
        (function () {
            var after = document.querySelector('.wrap .page-title-action') || document.querySelector('.wrap .wp-heading-inline');
            if (!after) { return; }
            var p = document.createElement('span');
            p.className = 'gps-list-subtitle';
            p.textContent = <?php echo wp_json_encode($text); ?>;
            after.insertAdjacentElement('afterend', p);
        })();
        </script>
        <?php
    }
}
