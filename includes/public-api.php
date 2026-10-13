<?php
/**
 * Public PHP API of GPS Courses for other plugins (GIA / WC Cart Recovery Pro).
 *
 * Stable functions so other plugins stop reading GPS Courses tables directly.
 * Contract version in gpsc_api_version(); add functions, don't change shapes.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('gpsc_api_version')) {
    function gpsc_api_version() {
        return '1.1'; // 1.1: gpsc_catalog()
    }
}

if (!function_exists('gpsc_waitlist_add')) {
    /**
     * Add someone to a course waitlist.
     *
     * @param array $data event_id (required), email (required), ticket_id, first_name,
     *                    last_name, phone, user_id.
     * @return array|WP_Error ['id', 'position', 'event_id', 'event_title', 'ticket_id', 'ticket_name']
     */
    function gpsc_waitlist_add(array $data) {
        $event_id = isset($data['event_id']) ? (int) $data['event_id'] : 0;
        $email    = isset($data['email']) ? sanitize_email($data['email']) : '';

        if (!$email || !is_email($email)) {
            return new WP_Error('invalid_email', __('Please provide a valid email address.', 'gps-courses'));
        }

        $event = $event_id ? get_post($event_id) : null;
        if (!$event || 'gps_event' !== $event->post_type || 'publish' !== $event->post_status) {
            return new WP_Error('invalid_event', __('The course was not found.', 'gps-courses'));
        }

        // Upcoming courses only
        $start = (string) get_post_meta($event_id, '_gps_start_date', true);
        $end   = (string) get_post_meta($event_id, '_gps_end_date', true) ?: $start;
        if ($end && $end < current_time('Y-m-d')) {
            return new WP_Error('past_event', __('This course has already taken place.', 'gps-courses'));
        }

        // The ticket must belong to this course; otherwise use its first active ticket
        $ticket_id = isset($data['ticket_id']) ? (int) $data['ticket_id'] : 0;
        if ($ticket_id && (int) get_post_meta($ticket_id, '_gps_event_id', true) !== $event_id) {
            $ticket_id = 0;
        }
        if (!$ticket_id) {
            $tickets = \GPSC\Tickets::get_active_tickets($event_id);
            if (!$tickets) {
                $tickets = get_posts([
                    'post_type'   => 'gps_ticket',
                    'numberposts' => 1,
                    'post_status' => 'publish',
                    'meta_query'  => [['key' => '_gps_event_id', 'value' => $event_id, 'type' => 'NUMERIC']],
                ]);
            }
            $ticket_id = $tickets ? (int) (is_object($tickets[0]) ? $tickets[0]->ID : $tickets[0]) : 0;
        }
        if (!$ticket_id) {
            return new WP_Error('no_ticket', __('This course has no ticket types yet.', 'gps-courses'));
        }

        // Abuse guard: per email and per IP, per hour
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        foreach (['e' => strtolower($email), 'i' => $ip] as $kind => $who) {
            $key  = 'gpsc_wl_' . $kind . '_' . md5($who . '|' . (int) floor(time() / HOUR_IN_SECONDS));
            $hits = (int) get_transient($key);
            if ($hits >= ('e' === $kind ? 5 : 10)) {
                return new WP_Error('rate_limited', __('Too many waitlist requests. Please try again later.', 'gps-courses'));
            }
            set_transient($key, $hits + 1, HOUR_IN_SECONDS);
        }

        $result = \GPSC\Waitlist::add_to_waitlist(
            $ticket_id,
            $event_id,
            $email,
            isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '',
            isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '',
            isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
            isset($data['user_id']) ? (int) $data['user_id'] : 0
        );
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'id'          => (int) $result['id'],
            'position'    => (int) $result['position'],
            'event_id'    => $event_id,
            'event_title' => get_the_title($event_id),
            'ticket_id'   => $ticket_id,
            'ticket_name' => get_the_title($ticket_id),
        ];
    }
}

if (!function_exists('gpsc_waitlist_status')) {
    /**
     * Server-side only: is this email on the waitlist for this course?
     * Never expose the result through a public endpoint (it would reveal
     * which courses a person is waiting for).
     *
     * @return array|null null when not on the waitlist; otherwise ['position', 'status', 'ticket_id']
     */
    function gpsc_waitlist_status($email, $event_id) {
        global $wpdb;
        $email = sanitize_email($email);
        if (!$email || !(int) $event_id) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT position, status, ticket_type_id FROM {$wpdb->prefix}gps_waitlist
             WHERE email = %s AND event_id = %d AND status IN ('waiting', 'notified')
             ORDER BY created_at DESC LIMIT 1",
            $email,
            (int) $event_id
        ));
        return $row ? ['position' => (int) $row->position, 'status' => $row->status, 'ticket_id' => (int) $row->ticket_type_id] : null;
    }
}

if (!function_exists('gpsc_catalog')) {
    /**
     * What GPS offers right now: upcoming courses and current monthly seminars.
     * Same rules as the /courses/ page (upcoming until the end date, price from
     * tickets on sale, open / sold_out / soon). Cached; any change to courses,
     * tickets, seminars or an order refreshes it, and it expires every 15 minutes.
     *
     * @return array ['date', 'generated_at', 'courses' => [...], 'seminars' => [...]]
     */
    function gpsc_catalog() {
        $today = current_time('Y-m-d');
        $key   = 'gpsc_catalog_' . md5($today . '|' . (int) get_option('gpsc_catalog_ver', 0));
        $data  = get_transient($key);
        if (is_array($data)) {
            return $data;
        }

        $courses = [];
        foreach (\GPSC\Course_Catalog::courses('upcoming', 50) as $c) {
            $tickets = [];
            foreach ((array) \GPSC\Tickets::get_active_tickets($c['id']) as $t) {
                $tid   = is_object($t) ? $t->ID : (int) $t;
                $stock = \GPSC\Tickets::get_ticket_stock($tid);
                $price = get_post_meta($tid, '_gps_ticket_price', true);
                $tickets[] = [
                    'id'        => $tid,
                    'name'      => html_entity_decode(get_the_title($tid), ENT_QUOTES, 'UTF-8'),
                    'price'     => '' === $price ? null : (float) $price,
                    'sold_out'  => \GPSC\Tickets::is_sold_out($tid),
                    'seats_left' => $stock['unlimited'] ? null : (int) $stock['available'],
                    'sale_ends' => str_replace('T', ' ', (string) get_post_meta($tid, '_gps_ticket_end_date', true)),
                ];
            }

            // No ticket on sale: "soon" unless every ticket's sale already ended
            $status = $c['status'];
            if ('soon' === $status) {
                $all = get_posts([
                    'post_type'   => 'gps_ticket',
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'fields'      => 'ids',
                    'meta_query'  => [['key' => '_gps_event_id', 'value' => $c['id'], 'type' => 'NUMERIC']],
                ]);
                $now   = str_replace(' ', 'T', current_time('Y-m-d H:i'));
                $ended = 0;
                foreach ($all as $tid) {
                    $end = (string) get_post_meta($tid, '_gps_ticket_end_date', true);
                    if ('' !== $end && $end < $now) {
                        $ended++;
                    }
                }
                if ($all && $ended === count($all)) {
                    $status = 'closed';
                }
            }

            $description = (string) get_post_meta($c['id'], '_gps_course_description', true)
                ?: wp_strip_all_tags((string) get_post_meta($c['id'], '_gps_description', true));
            $courses[] = [
                'id'       => (int) $c['id'],
                'title'    => html_entity_decode(wp_strip_all_tags($c['title']), ENT_QUOTES, 'UTF-8'),
                'url'      => $c['url'],
                'start'    => $c['start'],
                'end'      => $c['end'],
                'when'     => html_entity_decode($c['when'], ENT_QUOTES, 'UTF-8'),
                'ce'       => (float) $c['ce'],
                'venue'    => $c['venue'],
                'city'     => $c['city'],
                'category' => $c['category'],
                'price'    => $c['price'],
                'status'   => $status, // open | sold_out | soon | closed
                'summary'  => html_entity_decode($c['excerpt'], ENT_QUOTES, 'UTF-8'),
                'description' => html_entity_decode(wp_trim_words($description, 150), ENT_QUOTES, 'UTF-8'),
                'objectives'  => trim((string) get_post_meta($c['id'], '_gps_objectives', true)),
                'tickets'  => $tickets,
            ];
        }

        global $wpdb;
        $seminars = [];
        $year     = (int) current_time('Y');
        foreach (\GPSC\Seminars::get_all_seminars() as $s) {
            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT session_number, session_date, session_time_start, session_time_end, topic,
                        individual_price, individual_ce_credits, individual_sales_enabled
                 FROM {$wpdb->prefix}gps_seminar_sessions
                 WHERE seminar_id = %d AND session_date >= %s
                 ORDER BY session_date ASC, session_time_start ASC",
                $s->ID,
                $today
            )) ?: [];
            $s_year = (int) get_post_meta($s->ID, '_gps_seminar_year', true);
            if (!$sessions && $s_year <= $year) {
                continue; // finished program (a future year may have no sessions yet)
            }
            $seminars[] = [
                'id'          => (int) $s->ID,
                'title'       => html_entity_decode(get_the_title($s), ENT_QUOTES, 'UTF-8'),
                'url'         => get_permalink($s),
                'year'        => $s_year,
                'tuition'     => (float) (get_post_meta($s->ID, '_gps_seminar_tuition', true) ?: 750),
                'spots_left'  => (int) \GPSC\Seminars::get_available_spots($s->ID),
                'sessions'    => array_map(function ($r) {
                    return [
                        'number'     => (int) $r->session_number,
                        'date'       => $r->session_date,
                        'start_time' => $r->session_time_start ? substr($r->session_time_start, 0, 5) : '',
                        'end_time'   => $r->session_time_end ? substr($r->session_time_end, 0, 5) : '',
                        'topic'      => (string) $r->topic,
                        // A single session can be bought on its own
                        'single_price' => !empty($r->individual_sales_enabled) && null !== $r->individual_price ? (float) $r->individual_price : null,
                        'ce'         => null !== $r->individual_ce_credits ? (float) $r->individual_ce_credits : 2.0,
                    ];
                }, $sessions),
            ];
        }

        $data = [
            'date'         => $today,
            'generated_at' => current_time('mysql'),
            'courses'      => $courses,
            'seminars'     => $seminars,
        ];
        set_transient($key, $data, 15 * MINUTE_IN_SECONDS);
        return $data;
    }
}
