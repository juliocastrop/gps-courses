<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

class Credits {
    public static function award($user_id, $event_id, $source='auto', $notes=null) {
        global $wpdb;
        // Keep fractional credits (ledger column is DECIMAL(5,2)); some events
        // award e.g. 3.5 CE — casting to int would silently drop the decimal.
        $credits = (float) get_post_meta($event_id, '_gps_ce_credits', true);
        if ($credits <= 0) return false;

        return $wpdb->insert("{$wpdb->prefix}gps_ce_ledger", [
            'user_id'   => $user_id,
            'event_id'  => $event_id,
            'credits'   => $credits,
            'source'    => $source,
            'transaction_type' => 'attendance',
            'notes'     => $notes,
            'awarded_at'=> current_time('mysql'),
        ]);
    }

    /**
     * Whether the user has already been awarded CE credits for an event.
     * Used to prevent double-awarding (e.g. when a certificate is regenerated).
     */
    public static function has_award($user_id, $event_id) {
        global $wpdb;
        // Net balance, so a revoked award (refund) can be earned again later
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(credits), 0) FROM {$wpdb->prefix}gps_ce_ledger
             WHERE user_id = %d AND event_id = %d",
            $user_id,
            $event_id
        )) > 0;
    }

    /**
     * Revoke a user's credits for an event (refunded / cancelled order) with a
     * negative 'revoked' row, so the ledger keeps the history. Returns the
     * credits revoked (0 when there was nothing to revoke).
     */
    public static function revoke($user_id, $event_id, $notes = null) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return 0;
        }
        // One ticket is worth the event's credits at most (old duplicate awards
        // that belong to a designated attendee are not taken here)
        $event_credits = (float) get_post_meta($event_id, '_gps_ce_credits', true);
        $net = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(credits), 0) FROM {$wpdb->prefix}gps_ce_ledger
             WHERE user_id = %d AND event_id = %d",
            $user_id,
            $event_id
        ));
        if ($event_credits > 0) {
            $net = min($net, $event_credits);
        }
        if ($net <= 0) {
            return 0;
        }
        $wpdb->insert("{$wpdb->prefix}gps_ce_ledger", [
            'user_id'          => $user_id,
            'event_id'         => (int) $event_id,
            'credits'          => -$net,
            'source'           => 'order_refund',
            'transaction_type' => 'revoked',
            'notes'            => $notes,
            'awarded_at'       => current_time('mysql'),
        ]);
        Woo::clear_user_cache(0, $user_id);
        return $net;
    }

    /**
     * One-time repair: before credits moved to certificate generation, check-in
     * credited the BUYER for every ticket of an order, so a buyer who designated
     * a second attendee got the event credits twice and the attendee none.
     * Each duplicate award row is moved (user_id changed, note appended) to a
     * designated attendee of the same buyer/event who has an account and no award.
     */
    public static function reconcile_duplicate_awards() {
        if (get_option('gps_credits_duplicates_reconciled')) {
            return;
        }
        global $wpdb;
        $l = $wpdb->prefix . 'gps_ce_ledger';
        $t = $wpdb->prefix . 'gps_tickets';

        $dupes = $wpdb->get_results(
            "SELECT l.user_id, l.event_id, COUNT(*) AS n
             FROM {$l} l
             JOIN {$wpdb->posts} p ON p.ID = l.event_id AND p.post_type = 'gps_event'
             WHERE l.transaction_type = 'attendance'
             GROUP BY l.user_id, l.event_id HAVING n > 1"
        );

        foreach ((array) $dupes as $d) {
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$l} WHERE user_id = %d AND event_id = %d AND transaction_type = 'attendance' ORDER BY id DESC",
                $d->user_id, $d->event_id
            ));
            array_pop($rows); // the oldest award stays with the buyer

            $tickets = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$t} WHERE user_id = %d AND event_id = %d AND status != 'cancelled'
                 AND designated_attendee_email IS NOT NULL AND designated_attendee_email != ''",
                $d->user_id, $d->event_id
            ));
            foreach ((array) $tickets as $ticket) {
                if (empty($rows)) {
                    break;
                }
                $to = Tickets_Admin::credit_user_id($ticket);
                if ($to <= 0 || $to === (int) $d->user_id || self::has_award($to, $d->event_id)) {
                    continue;
                }
                $row_id = (int) array_shift($rows);
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$l} SET user_id = %d, notes = CONCAT(COALESCE(notes, ''), %s) WHERE id = %d",
                    $to,
                    sprintf(' | Moved from user #%d (duplicate award; designated attendee of ticket %s)', $d->user_id, $ticket->ticket_code),
                    $row_id
                ));
                error_log(sprintf('GPS Courses: moved CE ledger row #%d (event %d) from user #%d to designated attendee #%d (ticket %s)',
                    $row_id, $d->event_id, $d->user_id, $to, $ticket->ticket_code));
                Woo::clear_user_cache(0, $to);
                Woo::clear_user_cache(0, (int) $d->user_id);
            }
            foreach ($rows as $left) {
                error_log(sprintf('GPS Courses: CE ledger row #%d is a duplicate award (user #%d, event %d) with no designated attendee to move it to; review manually.',
                    $left, $d->user_id, $d->event_id));
            }
        }
        update_option('gps_credits_duplicates_reconciled', current_time('mysql'), false);
    }

    public static function user_total($user_id) {
        global $wpdb;
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(credits),0) FROM {$wpdb->prefix}gps_ce_ledger WHERE user_id=%d",
            $user_id
        ));
    }

    public static function user_ledger($user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_ce_ledger WHERE user_id=%d ORDER BY awarded_at DESC",
            $user_id
        ));
    }

    /** 12 -> "12", 3.5 -> "3.5", 2.25 -> "2.25" for display. */
    public static function format($credits) {
        $credits = round((float) $credits, 2);
        $decimals = (floor($credits) == $credits) ? 0 : ((round($credits, 1) == $credits) ? 1 : 2);
        return number_format_i18n($credits, $decimals);
    }

    // Aliases for compatibility
    public static function get_total($user_id) {
        return self::user_total($user_id);
    }

    public static function get_ledger($user_id) {
        return self::user_ledger($user_id);
    }
}
