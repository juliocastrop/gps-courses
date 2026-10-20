<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Individual Session Tickets
 *
 * Handles the sale, check-in, CE credit awarding, and certificate generation
 * for individually purchased seminar sessions.
 *
 * This is completely independent from the Monthly Seminars package (10 sessions).
 * Individual session buyers get their own ticket, QR code, and certificate.
 */
class Session_Tickets {

    /**
     * Initialize hooks
     */
    public static function init() {
        // Process individual session orders on WooCommerce order completion
        add_action('woocommerce_order_status_completed', [__CLASS__, 'process_session_order'], 25);

        // AJAX handlers
        add_action('wp_ajax_gps_checkin_session_ticket', [__CLASS__, 'ajax_checkin_session_ticket']);
        add_action('wp_ajax_gps_get_session_individual_tickets', [__CLASS__, 'ajax_get_session_individual_tickets']);
    }

    /* ============================================================
     * Order Processing
     * ============================================================ */

    /**
     * Process individual session purchase from a completed WooCommerce order
     *
     * @param int $order_id WooCommerce order ID
     */
    public static function process_session_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if already processed for individual sessions
        if ($order->get_meta('_gps_session_tickets_processed')) {
            return;
        }

        $user_id = $order->get_user_id();
        $created_any = false;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            // Check if this product is linked to an individual session
            $session_data = self::get_session_by_product($product_id);
            if (!$session_data) {
                continue;
            }

            $quantity = $item->get_quantity();

            for ($i = 0; $i < $quantity; $i++) {
                $ticket_id = self::create_session_ticket([
                    'session_id'      => $session_data->id,
                    'seminar_id'      => $session_data->seminar_id,
                    'user_id'         => $user_id ?: 0,
                    'order_id'        => $order_id,
                    'attendee_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'attendee_email'  => $order->get_billing_email(),
                    'ce_credits'      => $session_data->individual_ce_credits ?: 2,
                ]);

                if ($ticket_id) {
                    $created_any = true;
                    error_log("GPS Session Tickets: Created ticket #{$ticket_id} for session #{$session_data->id} (order #{$order_id})");

                    // Fire action for cache invalidation and notifications
                    do_action('gps_session_ticket_created', $ticket_id, $user_id);
                }
            }

            // Update sold count
            self::update_sold_count($session_data->id);
        }

        if ($created_any) {
            $order->update_meta_data('_gps_session_tickets_processed', current_time('mysql'));
            $order->save();

            // Send confirmation email
            self::send_purchase_confirmation($order_id);
        }
    }

    /* ============================================================
     * Ticket CRUD
     * ============================================================ */

    /**
     * Create a session ticket with QR code
     *
     * @param array $data Ticket data
     * @return int|false Ticket ID or false on failure
     */
    public static function create_session_ticket($data) {
        global $wpdb;

        // Generate unique ticket code
        $ticket_code = self::generate_ticket_code();

        $insert_data = [
            'session_id'     => (int) $data['session_id'],
            'seminar_id'     => (int) $data['seminar_id'],
            'user_id'        => (int) ($data['user_id'] ?? 0),
            'order_id'       => (int) ($data['order_id'] ?? 0),
            'ticket_code'    => $ticket_code,
            'attendee_name'  => sanitize_text_field($data['attendee_name'] ?? ''),
            'attendee_email' => sanitize_email($data['attendee_email'] ?? ''),
            'ce_credits'     => floatval($data['ce_credits'] ?? 2),
            'status'         => 'valid',
            'created_at'     => current_time('mysql'),
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'gps_session_tickets',
            $insert_data,
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s']
        );

        if (!$result) {
            error_log('GPS Session Tickets: Failed to create ticket - ' . $wpdb->last_error);
            return false;
        }

        $ticket_id = $wpdb->insert_id;

        // Generate QR code
        $qr_path = self::generate_qr_code($ticket_id, $ticket_code, $data['session_id']);
        if ($qr_path) {
            $wpdb->update(
                $wpdb->prefix . 'gps_session_tickets',
                ['qr_code_path' => $qr_path],
                ['id' => $ticket_id],
                ['%s'],
                ['%d']
            );
        }

        return $ticket_id;
    }

    /**
     * Get a session ticket by ID
     */
    public static function get_ticket($ticket_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_session_tickets WHERE id = %d",
            $ticket_id
        ));
    }

    /**
     * Get a session ticket by ticket code
     */
    public static function get_ticket_by_code($ticket_code) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_session_tickets WHERE ticket_code = %s",
            $ticket_code
        ));
    }

    /**
     * Get all individual tickets for a session
     */
    public static function get_session_tickets($session_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT st.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}gps_session_tickets st
             LEFT JOIN {$wpdb->users} u ON st.user_id = u.ID
             WHERE st.session_id = %d AND st.status != 'cancelled'
             ORDER BY st.created_at DESC",
            $session_id
        ));
    }

    /**
     * Get all individual session tickets for a user
     */
    public static function get_user_session_tickets($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT st.*,
                    ss.session_number, ss.session_date, ss.session_time_start,
                    ss.session_time_end, ss.topic, ss.individual_ce_credits,
                    p.post_title as seminar_title
             FROM {$wpdb->prefix}gps_session_tickets st
             INNER JOIN {$wpdb->prefix}gps_seminar_sessions ss ON st.session_id = ss.id
             INNER JOIN {$wpdb->posts} p ON st.seminar_id = p.ID
             WHERE st.user_id = %d AND st.status != 'cancelled'
             ORDER BY ss.session_date DESC",
            $user_id
        ));
    }

    /* ============================================================
     * Check-In & Attendance
     * ============================================================ */

    /**
     * Check in an individual session ticket holder
     *
     * @param int $ticket_id Session ticket ID
     * @param int|null $checked_in_by Admin user ID performing check-in
     * @return array Result with success status and message
     */
    public static function check_in($ticket_id, $checked_in_by = null) {
        global $wpdb;

        $ticket = self::get_ticket($ticket_id);
        if (!$ticket) {
            return [
                'success' => false,
                'message' => __('Ticket not found', 'gps-courses'),
            ];
        }

        if ($ticket->status !== 'valid') {
            return [
                'success' => false,
                'message' => sprintf(__('Ticket is %s', 'gps-courses'), $ticket->status),
            ];
        }

        if ($ticket->checked_in_at) {
            return [
                'success' => false,
                'message' => __('Already checked in', 'gps-courses'),
            ];
        }

        // Perform check-in
        $wpdb->update(
            $wpdb->prefix . 'gps_session_tickets',
            [
                'status'         => 'used',
                'checked_in_at'  => current_time('mysql'),
                'checked_in_by'  => $checked_in_by ?: get_current_user_id(),
            ],
            ['id' => $ticket_id],
            ['%s', '%s', '%d'],
            ['%d']
        );

        // Award CE credits
        if ($ticket->user_id && $ticket->ce_credits > 0) {
            self::award_ce_credits(
                $ticket->user_id,
                $ticket->seminar_id,
                $ticket->session_id,
                $ticket->ce_credits
            );
        }

        // Get user info for response
        $user = $ticket->user_id ? get_userdata($ticket->user_id) : null;

        return [
            'success' => true,
            'message' => __('Check-in successful', 'gps-courses'),
            'data' => [
                'ticket_id'       => $ticket_id,
                'user_name'       => $user ? $user->display_name : $ticket->attendee_name,
                'credits_awarded' => $ticket->ce_credits,
                'session_id'      => $ticket->session_id,
            ],
        ];
    }

    /**
     * Check in by ticket code (for QR scanning)
     */
    public static function check_in_by_code($ticket_code, $session_id = null) {
        $ticket = self::get_ticket_by_code($ticket_code);

        if (!$ticket) {
            return [
                'success' => false,
                'message' => __('Invalid ticket code', 'gps-courses'),
            ];
        }

        // If session_id provided, verify it matches
        if ($session_id && $ticket->session_id != $session_id) {
            return [
                'success' => false,
                'message' => __('This ticket is for a different session', 'gps-courses'),
            ];
        }

        return self::check_in($ticket->id);
    }

    /* ============================================================
     * CE Credits
     * ============================================================ */

    /**
     * Award CE credits for an individual session attendance
     */
    private static function award_ce_credits($user_id, $seminar_id, $session_id, $credits) {
        global $wpdb;

        // Get session info for notes
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
            $session_id
        ));

        $notes = sprintf(
            'Individual Session #%d - %s',
            $session->session_number ?? 0,
            $session->topic ?? 'Session'
        );

        // Insert into CE ledger
        $wpdb->insert(
            $wpdb->prefix . 'gps_ce_ledger',
            [
                'user_id'          => $user_id,
                'event_id'         => $seminar_id,
                'credits'          => $credits,
                'source'           => 'session_individual',
                'transaction_type' => 'attendance',
                'notes'            => $notes,
                'awarded_at'       => current_time('mysql'),
            ],
            ['%d', '%d', '%f', '%s', '%s', '%s', '%s'] // credits can be fractional (1.5)
        );

        // Refresh My Account. (This used to fire gps_credits_awarded with two
        // arguments in the wrong order; its email handler needs three, so the
        // check-in ended in a fatal ArgumentCountError on PHP 8.)
        Woo::clear_user_cache(0, $user_id);
    }

    /* ============================================================
     * Certificate Generation
     * ============================================================ */

    /**
     * Generate a certificate for an individual session ticket
     */
    public static function generate_certificate($ticket_id) {
        $ticket = self::get_ticket($ticket_id);
        if (!$ticket || !$ticket->checked_in_at) {
            return false;
        }

        global $wpdb;

        // Get session details
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_seminar_sessions WHERE id = %d",
            $ticket->session_id
        ));

        if (!$session) {
            return false;
        }

        // Get seminar title
        $seminar = get_post($ticket->seminar_id);
        $user = $ticket->user_id ? get_userdata($ticket->user_id) : null;
        $attendee_name = $user ? $user->display_name : $ticket->attendee_name;

        // Create certificate directory
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/gps-certificates/session/';
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }

        $filename = 'session-cert-' . $ticket_id . '-' . time() . '.pdf';
        $filepath = $cert_dir . $filename;

        // Create PDF using TCPDF
        $pdf = new \TCPDF('L', 'mm', 'LETTER', true, 'UTF-8', false);

        $pdf->SetCreator('GPS Dental Training');
        $pdf->SetAuthor('GPS Dental Training');
        $pdf->SetTitle('CE Credit Certificate - Individual Session');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // Use the existing Certificate class to render content
        // This ensures consistency with course certificates
        $cert_data = [
            'attendee_name'    => ucwords(strtolower($attendee_name), " \t\r\n\f\v-"),
            'event_title'      => $seminar->post_title . ' - Session #' . $session->session_number,
            'event_date'       => date_i18n('F j, Y', strtotime($session->session_date)),
            'venue'            => 'GPS Dental Training Center',
            'instructor'       => 'Carlos Castro DDS, FACP',
            'ce_credits'       => floatval($ticket->ce_credits),
            'certificate_code' => $ticket->ticket_code,
        ];

        // Render using the existing certificate system
        Certificates::render_certificate_content_static($pdf, $cert_data);

        $pdf->Output($filepath, 'F');

        // Save certificate record
        $cert_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filepath);

        $wpdb->insert(
            $wpdb->prefix . 'gps_certificates',
            [
                // ticket_id stays NULL: it only links course tickets (unique)
                'user_id'          => $ticket->user_id ?: 0,
                'event_id'         => $ticket->seminar_id,
                'certificate_path' => $filepath,
                'certificate_url'  => $cert_url,
                'generated_at'     => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        return $filepath;
    }

    /* ============================================================
     * Product / Session Lookup
     * ============================================================ */

    /**
     * Find a session by its WooCommerce product ID
     *
     * @param int $product_id WooCommerce product ID
     * @return object|null Session row or null
     */
    public static function get_session_by_product($product_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}gps_seminar_sessions
             WHERE individual_product_id = %d
             AND individual_sales_enabled = 1",
            $product_id
        ));
    }

    /**
     * Check if a product is an individual session product
     *
     * @param int $product_id
     * @return bool
     */
    public static function is_session_product($product_id) {
        return self::get_session_by_product($product_id) !== null;
    }

    /* ============================================================
     * Stock / Availability
     * ============================================================ */

    /**
     * Get available individual ticket count for a session
     */
    public static function get_available_count($session_id) {
        global $wpdb;

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT individual_capacity, individual_sold_count
             FROM {$wpdb->prefix}gps_seminar_sessions
             WHERE id = %d",
            $session_id
        ));

        if (!$session) {
            return 0;
        }

        // 0 capacity = unlimited
        if ($session->individual_capacity <= 0) {
            return PHP_INT_MAX;
        }

        return max(0, $session->individual_capacity - $session->individual_sold_count);
    }

    /**
     * Update the sold count for a session
     */
    private static function update_sold_count($session_id) {
        global $wpdb;

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gps_session_tickets
             WHERE session_id = %d AND status != 'cancelled'",
            $session_id
        ));

        $wpdb->update(
            $wpdb->prefix . 'gps_seminar_sessions',
            ['individual_sold_count' => $count],
            ['id' => $session_id],
            ['%d'],
            ['%d']
        );
    }

    /* ============================================================
     * QR Code Generation
     * ============================================================ */

    /**
     * Generate QR code for a session ticket
     */
    private static function generate_qr_code($ticket_id, $ticket_code, $session_id) {
        // Same generator as course tickets; returns a path relative to uploads
        // (use QRCodeGenerator::get_qr_code_url() to display it).
        if (!class_exists(__NAMESPACE__ . '\\QRCodeGenerator')) {
            return null;
        }
        $path = QRCodeGenerator::generate_qr_code($ticket_code, $ticket_id, [
            'type'     => 'session_ticket',
            'event_id' => (int) $session_id,
        ]);
        return $path ?: null;
    }

    /**
     * Generate unique ticket code
     */
    private static function generate_ticket_code() {
        return 'ST-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    /* ============================================================
     * Email Notifications
     * ============================================================ */

    /**
     * Send purchase confirmation email for individual session purchase
     */
    private static function send_purchase_confirmation($order_id) {
        return Emails::send_parts('session_ticket', self::email_parts($order_id));
    }

    /**
     * Session ticket email (catalog key "session_ticket"): every individual
     * session ticket of an order, to the buyer.
     *
     * @return array|null ['to','vars','blocks','opts']
     */
    public static function email_parts($order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order((int) $order_id) : null;
        if (!$order) {
            return null;
        }

        global $wpdb;
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT st.*, ss.session_number, ss.session_date, ss.session_time_start,
                    ss.session_time_end, ss.topic, p.post_title as seminar_title
             FROM {$wpdb->prefix}gps_session_tickets st
             INNER JOIN {$wpdb->prefix}gps_seminar_sessions ss ON st.session_id = ss.id
             INNER JOIN {$wpdb->posts} p ON st.seminar_id = p.ID
             WHERE st.order_id = %d
             ORDER BY ss.session_date ASC, st.id ASC",
            (int) $order_id
        ));
        $to = (string) $order->get_billing_email();
        if (empty($tickets) || '' === $to) {
            return null;
        }

        $blocks = '';
        foreach ($tickets as $t) {
            $seminar = Emails::plain($t->seminar_title);
            $rows    = [];
            if (!empty($t->topic)) {
                $rows[__('Topic', 'gps-courses')] = esc_html(Emails::plain($t->topic));
            }
            if (!empty($t->session_date)) {
                $rows[__('Date', 'gps-courses')] = esc_html(date_i18n('l, F j, Y', strtotime($t->session_date)));
            }
            $time = Emails::time_range_label($t->session_time_start, $t->session_time_end);
            if ('' !== $time) {
                $rows[__('Time', 'gps-courses')] = esc_html($time);
            }
            $location = (string) apply_filters('gps_seminar_location', 'GPS Dental Training Center · 6320 Sugarloaf Parkway, Duluth, GA 30097', (int) $t->seminar_id);
            if ('' !== $location) {
                $rows[__('Location', 'gps-courses')] = esc_html($location);
            }
            if ((float) $t->ce_credits > 0) {
                $rows[__('CE credits', 'gps-courses')] = esc_html(Emails::credits_label($t->ce_credits));
            }
            /* translators: %d: session number */
            $badge  = sprintf(__('Session %d', 'gps-courses'), (int) $t->session_number);
            $qr     = !empty($t->qr_code_path) ? QRCodeGenerator::get_qr_code_url($t->qr_code_path) : '';
            $blocks .= Emails::panel($seminar, $rows, $badge)
                     . Emails::code_box(__('Your ticket code', 'gps-courses'), (string) $t->ticket_code, __('Show this code at the front desk to check in.', 'gps-courses'), $qr);
        }
        $blocks .= Emails::paragraph(esc_html__('CE credits are awarded when you check in at the session.', 'gps-courses'), true);

        $first = $tickets[0];
        $label = count($tickets) > 1
            /* translators: %d: number of sessions */
            ? sprintf(__('%d seminar sessions', 'gps-courses'), count($tickets))
            /* translators: 1: seminar title, 2: session number */
            : sprintf(__('%1$s, session %2$d', 'gps-courses'), Emails::plain($first->seminar_title), (int) $first->session_number);

        return [
            'to'     => $to,
            'vars'   => [
                'first_name' => Emails::first_name(Emails::person_name($order->get_billing_first_name())),
                'session'    => $label,
                'date'       => !empty($first->session_date) ? date_i18n('F j, Y', strtotime($first->session_date)) : '',
            ],
            'blocks' => $blocks,
            'opts'   => ['button_url' => Emails::account_url('gps-seminars')],
        ];
    }

    /** Preview / test: the latest order with an individual session ticket. Read-only. */
    public static function email_sample() {
        global $wpdb;
        $table = $wpdb->prefix . 'gps_session_tickets';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return null;
        }
        $order_id = (int) $wpdb->get_var("SELECT order_id FROM {$table} WHERE order_id > 0 ORDER BY id DESC LIMIT 1");
        return $order_id ? self::email_parts($order_id) : null;
    }

    /* ============================================================
     * AJAX Handlers
     * ============================================================ */

    /**
     * AJAX: Check in an individual session ticket
     */
    public static function ajax_checkin_session_ticket() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $ticket_id = (int) $_POST['ticket_id'];
        $result = self::check_in($ticket_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Get individual ticket holders for a session
     */
    public static function ajax_get_session_individual_tickets() {
        check_ajax_referer('gps_seminars_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'gps-courses')]);
        }

        $session_id = (int) $_POST['session_id'];
        $tickets = self::get_session_tickets($session_id);

        // Format for response
        $formatted = [];
        foreach ($tickets as $ticket) {
            $formatted[] = [
                'id'              => $ticket->id,
                'ticket_code'     => $ticket->ticket_code,
                'attendee_name'   => $ticket->display_name ?: $ticket->attendee_name,
                'attendee_email'  => $ticket->user_email ?: $ticket->attendee_email,
                'ce_credits'      => $ticket->ce_credits,
                'status'          => $ticket->status,
                'checked_in_at'   => $ticket->checked_in_at,
            ];
        }

        wp_send_json_success(['tickets' => $formatted]);
    }
}
