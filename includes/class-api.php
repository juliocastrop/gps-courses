<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * REST API
 * Handles REST API endpoints for events, tickets, and CE credits
 */
class API {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);

        // AJAX handlers for frontend
        add_action('wp_ajax_gps_add_tickets_to_cart', [__CLASS__, 'ajax_add_tickets_to_cart']);
        add_action('wp_ajax_nopriv_gps_add_tickets_to_cart', [__CLASS__, 'ajax_add_tickets_to_cart']);

        add_action('wp_ajax_gps_get_calendar_events', [__CLASS__, 'ajax_get_calendar_events']);
        add_action('wp_ajax_nopriv_gps_get_calendar_events', [__CLASS__, 'ajax_get_calendar_events']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        $namespace = 'gps-courses/v1';

        // Events endpoints
        register_rest_route($namespace, '/events', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_events'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/events/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_event'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/events/calendar', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_calendar_events'],
            'permission_callback' => '__return_true',
        ]);

        // Tickets endpoints
        register_rest_route($namespace, '/tickets', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_tickets'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        register_rest_route($namespace, '/tickets/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_ticket'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        register_rest_route($namespace, '/tickets/verify', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'verify_ticket'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        // CE Credits endpoints
        register_rest_route($namespace, '/credits/user/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_user_credits'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        register_rest_route($namespace, '/credits/ledger', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_credits_ledger'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        // Attendance endpoints
        register_rest_route($namespace, '/attendance/event/(?P<event_id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_event_attendance'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        // Public availability endpoint (for AI Assistant integration)
        register_rest_route($namespace, '/availability/event/(?P<event_id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_event_availability'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);

        register_rest_route($namespace, '/availability/ticket/(?P<ticket_id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_ticket_availability'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);

        // Waitlist endpoint for AI Assistant
        register_rest_route($namespace, '/waitlist/add', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_to_waitlist'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);

        register_rest_route($namespace, '/waitlist/check', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'check_waitlist_status'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);
    }

    /**
     * Permission check for authenticated endpoints
     */
    public static function permissions_check($request) {
        return is_user_logged_in();
    }

    /**
     * Get events
     */
    public static function get_events($request) {
        $per_page = $request->get_param('per_page') ?: 10;
        $page = $request->get_param('page') ?: 1;
        $orderby = $request->get_param('orderby') ?: 'date';
        $order = $request->get_param('order') ?: 'ASC';
        $upcoming = $request->get_param('upcoming') === 'true';

        $args = [
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => $orderby === 'date' ? 'meta_value' : $orderby,
            'order' => $order,
        ];

        if ($orderby === 'date') {
            $args['meta_key'] = '_gps_start_date';
        }

        if ($upcoming) {
            $args['meta_query'] = [
                [
                    'key' => '_gps_start_date',
                    'value' => current_time('mysql'),
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
            ];
        }

        $query = new \WP_Query($args);

        $events = [];
        foreach ($query->posts as $post) {
            $events[] = self::format_event($post);
        }

        return new \WP_REST_Response([
            'events' => $events,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ], 200);
    }

    /**
     * Get single event
     */
    public static function get_event($request) {
        $event_id = $request->get_param('id');
        $event = get_post($event_id);

        if (!$event || $event->post_type !== 'gps_event') {
            return new \WP_Error('not_found', 'Event not found', ['status' => 404]);
        }

        return new \WP_REST_Response(self::format_event($event), 200);
    }

    /**
     * Get calendar events
     */
    public static function get_calendar_events($request) {
        $start = $request->get_param('start');
        $end = $request->get_param('end');

        $args = [
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_gps_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ];

        if ($start && $end) {
            $args['meta_query'] = [
                [
                    'key' => '_gps_start_date',
                    'value' => [$start, $end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE',
                ],
            ];
        }

        $query = new \WP_Query($args);

        $events = [];
        foreach ($query->posts as $post) {
            $start_date = get_post_meta($post->ID, '_gps_start_date', true);
            $venue = get_post_meta($post->ID, '_gps_venue', true);

            $events[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => date('Y-m-d', strtotime($start_date)),
                'time' => date('H:i', strtotime($start_date)),
                'url' => get_permalink($post->ID),
                'venue' => $venue,
            ];
        }

        return new \WP_REST_Response($events, 200);
    }

    /**
     * Get tickets
     */
    public static function get_tickets($request) {
        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        $event_id = $request->get_param('event_id');

        global $wpdb;

        $sql = "SELECT t.*, p.post_title as event_title
                FROM {$wpdb->prefix}gps_tickets t
                INNER JOIN {$wpdb->posts} p ON t.event_id = p.ID";

        $where = [];
        $params = [];

        if ($user_id) {
            $where[] = "t.user_id = %d";
            $params[] = $user_id;
        }

        if ($event_id) {
            $where[] = "t.event_id = %d";
            $params[] = $event_id;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY t.created_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $tickets = $wpdb->get_results($sql);

        return new \WP_REST_Response($tickets, 200);
    }

    /**
     * Get single ticket
     */
    public static function get_ticket($request) {
        $ticket_id = $request->get_param('id');

        global $wpdb;

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, p.post_title as event_title
             FROM {$wpdb->prefix}gps_tickets t
             INNER JOIN {$wpdb->posts} p ON t.event_id = p.ID
             WHERE t.id = %d",
            $ticket_id
        ));

        if (!$ticket) {
            return new \WP_Error('not_found', 'Ticket not found', ['status' => 404]);
        }

        // Verify user owns this ticket or is admin
        if ($ticket->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            return new \WP_Error('forbidden', 'Access denied', ['status' => 403]);
        }

        return new \WP_REST_Response($ticket, 200);
    }

    /**
     * Verify ticket QR code
     */
    public static function verify_ticket($request) {
        $qr_data = $request->get_param('qr_data');

        if (!$qr_data) {
            return new \WP_Error('invalid_request', 'QR data required', ['status' => 400]);
        }

        $result = QRCodeGenerator::verify_qr_data($qr_data);

        if (!$result['valid']) {
            return new \WP_Error('invalid_ticket', $result['error'], ['status' => 400]);
        }

        return new \WP_REST_Response([
            'valid' => true,
            'ticket' => $result['ticket'],
            'message' => 'Ticket verified successfully',
        ], 200);
    }

    /**
     * Get user CE credits
     */
    public static function get_user_credits($request) {
        $user_id = $request->get_param('user_id');

        // Verify user can access this data
        if ($user_id != get_current_user_id() && !current_user_can('manage_options')) {
            return new \WP_Error('forbidden', 'Access denied', ['status' => 403]);
        }

        $total = Credits::get_total($user_id);
        $ledger = Credits::get_ledger($user_id);

        return new \WP_REST_Response([
            'user_id' => $user_id,
            'total_credits' => $total,
            'ledger' => $ledger,
        ], 200);
    }

    /**
     * Get CE credits ledger
     */
    public static function get_credits_ledger($request) {
        $user_id = $request->get_param('user_id');
        $event_id = $request->get_param('event_id');

        global $wpdb;

        $sql = "SELECT l.*, p.post_title as event_title, u.display_name, u.user_email
                FROM {$wpdb->prefix}gps_ce_ledger l
                LEFT JOIN {$wpdb->posts} p ON l.event_id = p.ID
                INNER JOIN {$wpdb->users} u ON l.user_id = u.ID";

        $where = [];
        $params = [];

        if ($user_id) {
            $where[] = "l.user_id = %d";
            $params[] = $user_id;
        }

        if ($event_id) {
            $where[] = "l.event_id = %d";
            $params[] = $event_id;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY l.awarded_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $ledger = $wpdb->get_results($sql);

        return new \WP_REST_Response($ledger, 200);
    }

    /**
     * Get event attendance
     */
    public static function get_event_attendance($request) {
        $event_id = $request->get_param('event_id');

        if (!current_user_can('manage_options')) {
            return new \WP_Error('forbidden', 'Access denied', ['status' => 403]);
        }

        global $wpdb;

        $attendance = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, t.ticket_code, t.attendee_name, t.attendee_email
             FROM {$wpdb->prefix}gps_attendance a
             INNER JOIN {$wpdb->prefix}gps_tickets t ON a.ticket_id = t.id
             WHERE a.event_id = %d
             ORDER BY a.checked_in_at DESC",
            $event_id
        ));

        $stats = Attendance::get_event_stats($event_id);

        return new \WP_REST_Response([
            'event_id' => $event_id,
            'attendance' => $attendance,
            'stats' => $stats,
        ], 200);
    }

    /**
     * Format event for API response
     */
    private static function format_event($post) {
        $event_id = $post->ID;

        return [
            'id' => $event_id,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'url' => get_permalink($event_id),
            'featured_image' => get_the_post_thumbnail_url($event_id, 'large'),
            'start_date' => get_post_meta($event_id, '_gps_start_date', true),
            'end_date' => get_post_meta($event_id, '_gps_end_date', true),
            'venue' => get_post_meta($event_id, '_gps_venue', true),
            'address' => get_post_meta($event_id, '_gps_address', true),
            'ce_credits' => (int) get_post_meta($event_id, '_gps_ce_credits', true),
            'capacity' => (int) get_post_meta($event_id, '_gps_capacity', true),
            'registration_deadline' => get_post_meta($event_id, '_gps_registration_deadline', true),
        ];
    }

    /**
     * Get event availability (Public endpoint for AI Assistant)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_event_availability($request) {
        $event_id = (int) $request->get_param('event_id');

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'gps_event') {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Event not found',
                'event_id' => $event_id,
            ], 404);
        }

        // Get all active tickets for this event
        $tickets = get_posts([
            'post_type' => 'gps_ticket',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => '_gps_event_id', 'value' => $event_id],
            ],
        ]);

        $all_sold_out = true;
        $has_tickets = false;
        $ticket_info = [];

        foreach ($tickets as $ticket) {
            $status = get_post_meta($ticket->ID, '_gps_ticket_status', true);
            if ($status !== 'active') {
                continue;
            }

            $has_tickets = true;
            $is_sold_out = Tickets::is_sold_out($ticket->ID);
            $is_manual_sold_out = Tickets::is_manually_sold_out($ticket->ID);
            $stock = Tickets::get_ticket_stock($ticket->ID);

            $ticket_info[] = [
                'id' => $ticket->ID,
                'name' => $ticket->post_title,
                'price' => (float) get_post_meta($ticket->ID, '_gps_ticket_price', true),
                'is_sold_out' => $is_sold_out,
                'is_manual_sold_out' => $is_manual_sold_out,
                'stock' => [
                    'total' => $stock['total'],
                    'sold' => $stock['sold'],
                    'available' => $stock['available'],
                    'unlimited' => $stock['unlimited'],
                ],
            ];

            if (!$is_sold_out) {
                $all_sold_out = false;
            }
        }

        // Get event details
        $start_date = get_post_meta($event_id, '_gps_start_date', true);
        $end_date = get_post_meta($event_id, '_gps_end_date', true);

        $response = [
            'success' => true,
            'event' => [
                'id' => $event_id,
                'title' => $event->post_title,
                'url' => get_permalink($event_id),
                'start_date' => $start_date,
                'end_date' => $end_date,
                'start_date_formatted' => $start_date ? date_i18n(get_option('date_format'), strtotime($start_date)) : '',
            ],
            'availability' => [
                'is_available' => !$all_sold_out && $has_tickets,
                'is_sold_out' => $all_sold_out || !$has_tickets,
                'has_active_tickets' => $has_tickets,
                'reason' => !$has_tickets ? 'no_tickets' : ($all_sold_out ? 'sold_out' : 'available'),
            ],
            'tickets' => $ticket_info,
            'waitlist_enabled' => $all_sold_out || !$has_tickets,
        ];

        return new \WP_REST_Response($response, 200);
    }

    /**
     * Get single ticket availability (Public endpoint for AI Assistant)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_ticket_availability($request) {
        $ticket_id = (int) $request->get_param('ticket_id');

        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_type !== 'gps_ticket') {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Ticket not found',
                'ticket_id' => $ticket_id,
            ], 404);
        }

        $event_id = (int) get_post_meta($ticket_id, '_gps_event_id', true);
        $event = get_post($event_id);

        $is_sold_out = Tickets::is_sold_out($ticket_id);
        $is_manual_sold_out = Tickets::is_manually_sold_out($ticket_id);
        $stock = Tickets::get_ticket_stock($ticket_id);
        $status = get_post_meta($ticket_id, '_gps_ticket_status', true);

        return new \WP_REST_Response([
            'success' => true,
            'ticket' => [
                'id' => $ticket_id,
                'name' => $ticket->post_title,
                'price' => (float) get_post_meta($ticket_id, '_gps_ticket_price', true),
                'status' => $status ?: 'inactive',
            ],
            'event' => $event ? [
                'id' => $event_id,
                'title' => $event->post_title,
                'url' => get_permalink($event_id),
            ] : null,
            'availability' => [
                'is_sold_out' => $is_sold_out,
                'is_manual_sold_out' => $is_manual_sold_out,
                'stock' => $stock,
                'reason' => $is_manual_sold_out ? 'manual_override' : ($is_sold_out ? 'stock_depleted' : 'available'),
            ],
            'waitlist_enabled' => $is_sold_out,
        ], 200);
    }

    /**
     * Add to waitlist via API (for AI Assistant)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function add_to_waitlist($request) {
        $ticket_id = (int) $request->get_param('ticket_id');
        $event_id = (int) $request->get_param('event_id');
        $email = sanitize_email($request->get_param('email'));
        $first_name = sanitize_text_field($request->get_param('first_name') ?: '');
        $last_name = sanitize_text_field($request->get_param('last_name') ?: '');
        $phone = sanitize_text_field($request->get_param('phone') ?: '');

        // Validate required fields
        if (!$ticket_id || !$event_id || !$email || !is_email($email)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Missing required fields: ticket_id, event_id, and valid email are required',
            ], 400);
        }

        // Verify ticket and event exist
        $ticket = get_post($ticket_id);
        $event = get_post($event_id);

        if (!$ticket || !$event) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Invalid ticket or event ID',
            ], 404);
        }

        // Add to waitlist
        $result = Waitlist::add_to_waitlist($ticket_id, $event_id, $email, $first_name, $last_name, $phone);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_message(),
                'error_code' => $result->get_error_code(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Successfully added to waitlist',
            'data' => [
                'waitlist_id' => $result['id'],
                'position' => $result['position'],
                'email' => $email,
                'event' => $event->post_title,
                'ticket' => $ticket->post_title,
            ],
        ], 200);
    }

    /**
     * Check waitlist status for an email (for AI Assistant)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function check_waitlist_status($request) {
        $email = sanitize_email($request->get_param('email'));
        $event_id = (int) $request->get_param('event_id');

        if (!$email || !is_email($email)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Valid email is required',
            ], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gps_waitlist';

        $where = "email = %s AND status IN ('waiting', 'notified')";
        $params = [$email];

        if ($event_id) {
            $where .= " AND event_id = %d";
            $params[] = $event_id;
        }

        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT w.*, p.post_title as event_title, t.post_title as ticket_title
             FROM $table w
             LEFT JOIN {$wpdb->posts} p ON w.event_id = p.ID
             LEFT JOIN {$wpdb->posts} t ON w.ticket_type_id = t.ID
             WHERE $where
             ORDER BY w.created_at DESC",
            $params
        ));

        $waitlist_entries = [];
        foreach ($entries as $entry) {
            $waitlist_entries[] = [
                'id' => $entry->id,
                'event_id' => $entry->event_id,
                'event_title' => $entry->event_title,
                'ticket_id' => $entry->ticket_type_id,
                'ticket_title' => $entry->ticket_title,
                'position' => $entry->position,
                'status' => $entry->status,
                'created_at' => $entry->created_at,
                'notified_at' => $entry->notified_at,
                'expires_at' => $entry->expires_at,
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'email' => $email,
            'on_waitlist' => !empty($waitlist_entries),
            'entries' => $waitlist_entries,
            'count' => count($waitlist_entries),
        ], 200);
    }

    /**
     * AJAX: Add tickets to cart
     */
    public static function ajax_add_tickets_to_cart() {
        check_ajax_referer('gps_ticket_selector_nonce', 'nonce');

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        $tickets = isset($_POST['tickets']) ? $_POST['tickets'] : [];

        if (!$event_id || empty($tickets)) {
            wp_send_json_error(['message' => __('Invalid request.', 'gps-courses')]);
        }

        if (!function_exists('WC')) {
            wp_send_json_error(['message' => __('WooCommerce is not active.', 'gps-courses')]);
        }

        $added_count = 0;

        foreach ($tickets as $ticket) {
            $product_id = (int) $ticket['product_id'];
            $quantity = (int) $ticket['quantity'];

            if ($product_id && $quantity > 0) {
                WC()->cart->add_to_cart($product_id, $quantity);
                $added_count += $quantity;
            }
        }

        if ($added_count > 0) {
            wp_send_json_success([
                'message' => sprintf(_n('%d ticket added to cart!', '%d tickets added to cart!', $added_count, 'gps-courses'), $added_count),
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_hash' => WC()->cart->get_cart_hash(),
                'fragments' => [],
            ]);
        } else {
            wp_send_json_error(['message' => __('No tickets were added.', 'gps-courses')]);
        }
    }

    /**
     * AJAX: Get calendar events
     */
    public static function ajax_get_calendar_events() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gps_calendar_nonce')) {
            wp_send_json_error(['message' => __('Invalid security token', 'gps-courses')]);
            return;
        }

        $year = isset($_POST['year']) ? (int) $_POST['year'] : date('Y');
        $month = isset($_POST['month']) ? (int) $_POST['month'] : date('n');
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'all';
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'all';

        // Legacy support for start/end dates
        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '';

        // Calculate date range for the month (including days from prev/next month shown in calendar)
        if (!$start || !$end) {
            // Get first and last day of the month
            $first_day = date('Y-m-01', strtotime("$year-$month-01"));
            $last_day = date('Y-m-t', strtotime("$year-$month-01"));

            // Extend range to include full calendar grid (42 days)
            $start = date('Y-m-d', strtotime($first_day . ' -7 days'));
            $end = date('Y-m-d', strtotime($last_day . ' +14 days'));
        }

        // Check transient cache (5 minutes)
        $cache_key = 'gps_calendar_' . md5($year . '_' . $month . '_' . $category . '_' . $event_type . '_' . $start . '_' . $end);
        $cached_events = get_transient($cache_key);

        if (false !== $cached_events && is_array($cached_events)) {
            wp_send_json_success(['events' => $cached_events, 'cached' => true]);
            return;
        }

        global $wpdb;
        $events = [];

        // Query courses (gps_event) if event_type is 'all' or 'courses'
        if ($event_type === 'all' || $event_type === 'courses') {
            $args = [
                'post_type' => 'gps_event',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_key' => '_gps_start_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_gps_start_date',
                        'value' => [$start, $end],
                        'compare' => 'BETWEEN',
                        'type' => 'DATE',
                    ],
                ],
            ];

            // Add category filter if specified
            if ($category !== 'all') {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'gps_event_category',
                        'field' => 'term_id',
                        'terms' => (int) $category,
                    ],
                ];
            }

            $query = new \WP_Query($args);

            if (!empty($query->posts)) {
                $post_ids = implode(',', array_map('intval', $query->posts));

                // Get all posts data at once
                $posts_data = $wpdb->get_results(
                    "SELECT ID, post_title, post_excerpt, post_content
                     FROM {$wpdb->posts}
                     WHERE ID IN ($post_ids)",
                    OBJECT_K
                );

                // Get all meta data at once
                $meta_data = $wpdb->get_results(
                    "SELECT post_id, meta_key, meta_value
                     FROM {$wpdb->postmeta}
                     WHERE post_id IN ($post_ids)
                     AND meta_key IN ('_gps_start_date', '_gps_end_date', '_gps_start_time', '_gps_end_time', '_gps_venue', '_gps_location', '_gps_ce_credits')",
                    ARRAY_A
                );

                // Organize meta by post ID
                $meta_by_post = [];
                foreach ($meta_data as $meta) {
                    $meta_by_post[$meta['post_id']][$meta['meta_key']] = $meta['meta_value'];
                }

                foreach ($query->posts as $post_id) {
                    if (!isset($posts_data[$post_id])) {
                        continue;
                    }

                    $post = $posts_data[$post_id];
                    $meta = isset($meta_by_post[$post_id]) ? $meta_by_post[$post_id] : [];

                    $start_date = isset($meta['_gps_start_date']) ? $meta['_gps_start_date'] : '';
                    $end_date = isset($meta['_gps_end_date']) ? $meta['_gps_end_date'] : '';
                    $start_time = isset($meta['_gps_start_time']) ? $meta['_gps_start_time'] : '';
                    $end_time = isset($meta['_gps_end_time']) ? $meta['_gps_end_time'] : '';
                    $venue = isset($meta['_gps_venue']) ? $meta['_gps_venue'] : '';
                    $location = isset($meta['_gps_location']) ? $meta['_gps_location'] : '';
                    $credits = isset($meta['_gps_ce_credits']) ? (int) $meta['_gps_ce_credits'] : 0;

                    // Format times
                    $formatted_start_time = $start_time ? date('g:i A', strtotime($start_time)) : '';
                    $formatted_end_time = $end_time ? date('g:i A', strtotime($end_time)) : '';

                    $events[] = [
                        'id' => $post_id,
                        'title' => $post->post_title,
                        'start_date' => $start_date ? date('Y-m-d', strtotime($start_date)) : '',
                        'end_date' => $end_date ? date('Y-m-d', strtotime($end_date)) : '',
                        'start_time' => $formatted_start_time,
                        'end_time' => $formatted_end_time,
                        'url' => get_permalink($post_id),
                        'location' => $venue ?: $location,
                        'credits' => $credits,
                        'type' => 'course',
                    ];
                }
            }
        }

        // Query seminar sessions if event_type is 'all' or 'seminars'
        if ($event_type === 'all' || $event_type === 'seminars') {
            $sessions_table = $wpdb->prefix . 'gps_seminar_sessions';

            // Check if table exists before querying
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$sessions_table}'");

            if ($table_exists) {
                $seminar_sessions = $wpdb->get_results($wpdb->prepare(
                    "SELECT ss.*, p.post_title as seminar_title
                     FROM {$sessions_table} ss
                     INNER JOIN {$wpdb->posts} p ON ss.seminar_id = p.ID
                     WHERE p.post_status = 'publish'
                     AND ss.session_date BETWEEN %s AND %s
                     ORDER BY ss.session_date ASC, ss.session_time_start ASC",
                    $start, $end
                ));

                foreach ($seminar_sessions as $session) {
                    // Build session title: "2025 Monthly Seminar - Topic" or "2025 Monthly Seminar - Session X"
                    $session_title = $session->seminar_title . ' - ' . ($session->topic ?: 'Session ' . $session->session_number);

                    $events[] = [
                        'id' => 'session_' . $session->id,
                        'title' => $session_title,
                        'start_date' => $session->session_date,
                        'end_date' => $session->session_date,
                        'start_time' => $session->session_time_start ? date('g:i A', strtotime($session->session_time_start)) : '',
                        'end_time' => $session->session_time_end ? date('g:i A', strtotime($session->session_time_end)) : '',
                        'url' => 'https://gpsdentaltraining.com/product/gps-monthly-seminars/',
                        'location' => '',
                        'credits' => 2,
                        'type' => 'seminar-session',
                        'session_number' => $session->session_number,
                    ];
                }
            }
        }

        // Sort all events by start_date
        usort($events, function($a, $b) {
            return strcmp($a['start_date'], $b['start_date']);
        });

        // Cache for 5 minutes
        set_transient($cache_key, $events, 5 * MINUTE_IN_SECONDS);

        wp_send_json_success(['events' => $events]);
    }
}
