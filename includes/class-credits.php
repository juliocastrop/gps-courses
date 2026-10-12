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
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}gps_ce_ledger
             WHERE user_id = %d AND event_id = %d
             AND transaction_type = 'attendance' LIMIT 1",
            $user_id,
            $event_id
        ));
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
