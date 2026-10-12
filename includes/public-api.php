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
        return '1.0';
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
