<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Refunded / cancelled orders: their tickets stop being valid (no check-in,
 * certificate, report or email blast) and the CE credits earned through them
 * are revoked with a ledger row. Partial refunds leave the order untouched.
 */
class Order_Cancellations {

    const VOID_STATUSES = ['refunded', 'cancelled'];

    public static function init() {
        // Before Waitlist (priority 10), so the waitlist sees the freed seats
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'void_order'], 5);
        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'void_order'], 5);

        // Designated attendee who registers later: link the tickets, award waiting credits
        add_action('user_register', [__CLASS__, 'link_designated_tickets'], 20);

        // One-time repairs for data created before these rules existed
        add_action('admin_init', [__CLASS__, 'reconcile_once']);
    }

    /**
     * Cancel every ticket of the order and revoke the credits they gave.
     */
    public static function void_order($order_id) {
        global $wpdb;
        $order_id = (int) $order_id;
        $order    = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        $status   = $order ? $order->get_status() : 'cancelled';
        $note     = sprintf('Order #%d %s', $order_id, $status);

        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE order_id = %d AND status != 'cancelled'",
            $order_id
        ));

        $cancelled = 0;
        $revoked   = 0.0;
        foreach ((array) $tickets as $ticket) {
            $wpdb->update($wpdb->prefix . 'gps_tickets', ['status' => 'cancelled'], ['id' => (int) $ticket->id], ['%s'], ['%d']);
            $cancelled++;

            $user_id = Tickets_Admin::credit_user_id($ticket);
            if ($user_id > 0 && !self::has_other_valid_ticket($user_id, (int) $ticket->event_id, (int) $ticket->id)) {
                $revoked += Credits::revoke($user_id, (int) $ticket->event_id, $note . ', ticket ' . $ticket->ticket_code);
            }
            do_action('gps_ticket_cancelled', (int) $ticket->id, $order_id);
        }

        // Individual seminar session tickets
        $session_table = $wpdb->prefix . 'gps_session_tickets';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $session_table)) === $session_table) {
            $cancelled += (int) $wpdb->query($wpdb->prepare(
                "UPDATE {$session_table} SET status = 'cancelled' WHERE order_id = %d AND status != 'cancelled'",
                $order_id
            ));
        }

        if ($cancelled && $order) {
            $order->add_order_note(sprintf(
                /* translators: 1: tickets cancelled, 2: CE credits revoked */
                __('GPS Courses: %1$d ticket(s) cancelled, %2$s CE credit(s) revoked.', 'gps-courses'),
                $cancelled,
                Credits::format($revoked)
            ));
        }
        if ($cancelled) {
            error_log(sprintf('GPS Courses: %s — %d ticket(s) cancelled, %s CE credits revoked.', $note, $cancelled, $revoked));
        }
    }

    /**
     * Another valid ticket of the same event still credits this person (e.g. a
     * second order), so the credits must stay.
     */
    protected static function has_other_valid_ticket($user_id, $event_id, $except_ticket_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}gps_tickets
             WHERE event_id = %d AND id != %d AND status != 'cancelled'
               AND (designated_attendee_id = %d
                    OR (user_id = %d AND (designated_attendee_email IS NULL OR designated_attendee_email = '')))
             LIMIT 1",
            $event_id, $except_ticket_id, $user_id, $user_id
        ));
    }

    public static function link_designated_tickets($user_id) {
        global $wpdb;
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return;
        }
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gps_tickets
             WHERE designated_attendee_email = %s AND (designated_attendee_id IS NULL OR designated_attendee_id = 0)",
            $user->user_email
        ));
        foreach ((array) $ids as $id) {
            $wpdb->update($wpdb->prefix . 'gps_tickets', ['designated_attendee_id' => (int) $user_id], ['id' => (int) $id], ['%d'], ['%d']);
            Tickets_Admin::award_pending_credits((int) $id);
        }
    }

    /**
     * Tickets of orders refunded / cancelled before this class existed, and
     * duplicate awards from the old check-in logic. Runs once.
     */
    public static function reconcile_once() {
        if (!current_user_can('manage_options') || wp_doing_ajax()) {
            return;
        }
        // Duplicates first, so a refund never revokes credits that belong to an attendee
        Credits::reconcile_duplicate_awards();

        if (!get_option('gps_void_orders_reconciled') && function_exists('wc_get_order')) {
            global $wpdb;
            $order_ids = $wpdb->get_col("SELECT DISTINCT order_id FROM {$wpdb->prefix}gps_tickets WHERE status != 'cancelled'");
            foreach ((array) $order_ids as $order_id) {
                $order = wc_get_order((int) $order_id);
                if ($order && in_array($order->get_status(), self::VOID_STATUSES, true)) {
                    self::void_order((int) $order_id);
                }
            }
            update_option('gps_void_orders_reconciled', current_time('mysql'), false);
        }
    }
}
