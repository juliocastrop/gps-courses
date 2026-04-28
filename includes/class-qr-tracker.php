<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Promotional QR codes: tracking + redirect layer.
 *
 * QRs encode short URLs like /qr/{short_code}. Each scan is logged to
 * wp_gps_qr_scans, then redirected to the live permalink of the linked
 * post (event or seminar) with UTM params appended. Permalinks are read
 * fresh on each scan via get_permalink($post_id), so slug changes do
 * not break printed QRs.
 *
 * Phase 1: tables + tracker. UI generator and dashboard come in
 * subsequent phases.
 */
class QR_Tracker {

    const QUERY_VAR = 'gps_qr';
    const SHORT_CODE_LENGTH = 8;
    const SHORT_CODE_ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789'; // base32-ish, no ambiguous chars
    const GEO_CACHE_HOURS = 24 * 30;

    public static function init() {
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_filter('query_vars', [__CLASS__, 'register_query_var']);
        add_action('template_redirect', [__CLASS__, 'handle_scan'], 1);
        add_action('admin_init', [__CLASS__, 'maybe_flush_rewrite_rules']);
    }

    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^qr/([a-z0-9]{6,16})/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    public static function register_query_var($vars) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function maybe_flush_rewrite_rules() {
        if (get_option('gps_qr_rewrite_flushed') !== GPSC_VERSION) {
            self::add_rewrite_rules();
            flush_rewrite_rules();
            update_option('gps_qr_rewrite_flushed', GPSC_VERSION);
        }
    }

    /* ====================================================================
     * Scan handler — entry point for /qr/{short_code}
     * ==================================================================== */

    public static function handle_scan() {
        $code = get_query_var(self::QUERY_VAR);
        if (empty($code)) {
            return;
        }

        global $wpdb;
        $qr = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_qr_codes WHERE short_code = %s AND deleted_at IS NULL LIMIT 1",
            sanitize_text_field($code)
        ));

        if (!$qr) {
            self::serve_not_found();
            return;
        }

        $target = self::resolve_target_url($qr);

        if (!$target) {
            self::serve_not_found();
            return;
        }

        // Log scan asynchronously-style: insert before redirect, but keep it cheap.
        self::log_scan($qr);

        wp_redirect($target, 302);
        exit;
    }

    /**
     * Resolve the destination URL for a QR. Reads the post's permalink
     * fresh and appends UTM params from the QR row.
     *
     * @return string|null
     */
    public static function resolve_target_url($qr) {
        $post = get_post($qr->post_id);
        if (!$post || $post->post_status === 'trash') {
            return null;
        }

        // Custom override takes precedence over the post permalink.
        // Lets seminars (or any post) point to a WooCommerce product
        // page or any other landing URL while still tracking scans.
        if (!empty($qr->custom_target_url)) {
            $base = esc_url_raw($qr->custom_target_url);
        } else {
            $base = get_permalink($qr->post_id);
        }

        if (!$base) {
            return null;
        }

        $utms = [
            'utm_source'   => $qr->utm_source ?: 'qr',
            'utm_medium'   => $qr->utm_medium ?: 'print',
            'utm_campaign' => $qr->utm_campaign ?: $post->post_name,
            'qr_id'        => $qr->short_code,
        ];

        if (!empty($qr->utm_content)) {
            $utms['utm_content'] = $qr->utm_content;
        }
        if (!empty($qr->utm_term)) {
            $utms['utm_term'] = $qr->utm_term;
        }

        return add_query_arg($utms, $base);
    }

    protected static function serve_not_found() {
        // Redirect to homepage with a flag; later phases may render a
        // custom "QR no longer active" page.
        wp_redirect(add_query_arg('gps_qr_invalid', '1', home_url('/')), 302);
        exit;
    }

    /* ====================================================================
     * Scan logging
     * ==================================================================== */

    protected static function log_scan($qr) {
        global $wpdb;

        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $ip = self::get_client_ip();

        $is_bot = self::is_bot($ua) ? 1 : 0;
        $device = self::detect_device($ua);

        // Geo lookup is best-effort and capped — never block redirect on failure.
        $geo = ['country' => null, 'country_code' => null, 'city' => null, 'region' => null];
        if (!$is_bot && $ip) {
            $cached = self::get_cached_geo($ip);
            if ($cached !== null) {
                $geo = $cached;
            }
            // Schedule async lookup if not cached. We do not block.
            if ($cached === null) {
                wp_schedule_single_event(time() + 1, 'gps_qr_geo_lookup', [$ip]);
            }
        }

        $wpdb->insert(
            $wpdb->prefix . 'gps_qr_scans',
            [
                'qr_id'        => $qr->id,
                'post_id'      => $qr->post_id,
                'scanned_at'   => current_time('mysql'),
                'ip_hash'      => self::hash_ip($ip),
                'user_agent'   => $ua,
                'device_type'  => $device,
                'is_bot'       => $is_bot,
                'country'      => $geo['country'],
                'country_code' => $geo['country_code'],
                'region'       => $geo['region'],
                'city'         => $geo['city'],
                'referrer'     => substr($referrer, 0, 500),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$is_bot) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}gps_qr_codes SET scan_count = scan_count + 1, last_scanned_at = %s WHERE id = %d",
                current_time('mysql'),
                $qr->id
            ));
        }
    }

    /* ====================================================================
     * QR row CRUD — public API for Phase 2 (generator UI)
     * ==================================================================== */

    /**
     * Get or create a QR row for a post.
     */
    public static function get_or_create_for_post($post_id) {
        global $wpdb;

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_qr_codes WHERE post_id = %d AND deleted_at IS NULL LIMIT 1",
            $post_id
        ));

        if ($existing) {
            return $existing;
        }

        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        $short_code = self::generate_unique_short_code();

        $wpdb->insert(
            $wpdb->prefix . 'gps_qr_codes',
            [
                'post_id'      => $post_id,
                'post_type'    => $post->post_type,
                'short_code'   => $short_code,
                'target_url'   => get_permalink($post_id),
                'utm_source'   => 'qr',
                'utm_medium'   => 'print',
                'utm_campaign' => $post->post_name,
                'has_logo'     => 0,
                'scan_count'   => 0,
                'created_at'   => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );

        $id = $wpdb->insert_id;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_qr_codes WHERE id = %d",
            $id
        ));
    }

    public static function update_qr($qr_id, array $fields) {
        global $wpdb;

        $allowed = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'custom_target_url', 'has_logo'];
        $data = [];
        $formats = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) continue;

            if ($key === 'has_logo') {
                $data[$key] = (int) $fields[$key];
                $formats[] = '%d';
            } elseif ($key === 'custom_target_url') {
                $val = trim((string) $fields[$key]);
                $data[$key] = $val === '' ? null : esc_url_raw($val);
                $formats[] = '%s';
            } else {
                $data[$key] = sanitize_text_field($fields[$key]);
                $formats[] = '%s';
            }
        }

        if (empty($data)) {
            return false;
        }

        return $wpdb->update(
            $wpdb->prefix . 'gps_qr_codes',
            $data,
            ['id' => (int) $qr_id],
            $formats,
            ['%d']
        );
    }

    public static function get_qr_url($qr) {
        return home_url('/qr/' . $qr->short_code);
    }

    /* ====================================================================
     * Helpers
     * ==================================================================== */

    protected static function generate_unique_short_code() {
        global $wpdb;
        $alphabet = self::SHORT_CODE_ALPHABET;
        $len = self::SHORT_CODE_LENGTH;
        $max = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';
            for ($i = 0; $i < $len; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}gps_qr_codes WHERE short_code = %s LIMIT 1",
                $code
            ));
            if (!$exists) {
                return $code;
            }
        }
        // Extreme fallback: longer code
        return substr(bin2hex(random_bytes(8)), 0, 16);
    }

    protected static function get_client_ip() {
        $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }

    protected static function hash_ip($ip) {
        if (!$ip) return '';
        $salt = wp_salt('auth');
        return hash('sha256', $ip . '|' . $salt);
    }

    public static function detect_device($ua) {
        if (empty($ua)) return 'unknown';
        $ua = strtolower($ua);

        if (preg_match('/(ipad|tablet|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/(mobile|iphone|ipod|blackberry|opera mini|iemobile)/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public static function is_bot($ua) {
        if (empty($ua)) return true;
        $patterns = '/bot|crawler|spider|crawling|facebookexternalhit|slurp|google-pagespeed|lighthouse|headlesschrome|preview/i';
        return (bool) preg_match($patterns, $ua);
    }

    /* ====================================================================
     * Geo lookup — async via cron, cached as transient
     * ==================================================================== */

    protected static function get_cached_geo($ip) {
        $key = 'gps_qr_geo_' . md5($ip);
        $cached = get_transient($key);
        return is_array($cached) ? $cached : null;
    }

    /**
     * Called via wp_schedule_single_event('gps_qr_geo_lookup', [$ip]).
     * Hits ip-api.com (free tier: 45 req/min, no key) and caches.
     */
    public static function async_geo_lookup($ip) {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) return;

        $key = 'gps_qr_geo_' . md5($ip);
        if (get_transient($key)) return;

        $response = wp_remote_get(
            'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,countryCode,regionName,city',
            ['timeout' => 5]
        );

        if (is_wp_error($response)) return;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || ($body['status'] ?? '') !== 'success') {
            // Cache empty result briefly to avoid hammering on bad IPs
            set_transient($key, [
                'country' => null, 'country_code' => null, 'region' => null, 'city' => null,
            ], HOUR_IN_SECONDS);
            return;
        }

        $geo = [
            'country'      => $body['country'] ?? null,
            'country_code' => $body['countryCode'] ?? null,
            'region'       => $body['regionName'] ?? null,
            'city'         => $body['city'] ?? null,
        ];

        set_transient($key, $geo, self::GEO_CACHE_HOURS * HOUR_IN_SECONDS);

        // Backfill recent scan rows for this IP hash that lack geo.
        global $wpdb;
        $ip_hash = self::hash_ip($ip);
        $wpdb->update(
            $wpdb->prefix . 'gps_qr_scans',
            [
                'country'      => $geo['country'],
                'country_code' => $geo['country_code'],
                'region'       => $geo['region'],
                'city'         => $geo['city'],
            ],
            [
                'ip_hash' => $ip_hash,
                'country' => null,
            ],
            ['%s', '%s', '%s', '%s'],
            ['%s', '%s']
        );
    }
}

// Cron handler for async geo lookup
add_action('gps_qr_geo_lookup', [QR_Tracker::class, 'async_geo_lookup']);
