<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

class Credits {
    public static function award($user_id, $event_id, $source='auto', $notes=null) {
        global $wpdb;
        $credits = (int) get_post_meta($event_id, '_gps_ce_credits', true);
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

    public static function user_total($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
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

    // Aliases for compatibility
    public static function get_total($user_id) {
        return self::user_total($user_id);
    }

    public static function get_ledger($user_id) {
        return self::user_ledger($user_id);
    }
}
