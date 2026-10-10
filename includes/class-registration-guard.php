<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Registration Guard
 *
 * Keeps user registration OPEN for real dentists while blocking automated
 * bot injection. Layered, low-friction defenses — no external service required:
 *
 *   1. Honeypot field           — bots fill hidden fields; humans don't.
 *   2. Time trap                — reject submissions faster than a human can type.
 *   3. Disposable-email block   — reject known throwaway mail providers.
 *   4. Bot-signature block      — reject 100%-bot email shapes (pure hex local part).
 *   5. Per-IP rate limit        — cap registrations per IP per hour.
 *   6. Logging                  — record every block for monitoring / tuning.
 *
 * Hooks both WordPress core (wp-login.php?action=register) and WooCommerce
 * (My Account / checkout) registration flows — the two vectors that create
 * `customer` accounts on this site.
 *
 * @since 1.0.5
 */
class Registration_Guard {

    /** Hidden honeypot field name (looks tempting to bots). */
    const HONEYPOT_FIELD = 'gps_url';

    /** Hidden timestamp field name. */
    const TIMESTAMP_FIELD = 'gps_rt';

    /** Minimum seconds a real human needs to fill the form. */
    const MIN_FILL_SECONDS = 4;

    /** Max accounts an IP may actually CREATE within the rate window. */
    const RATE_LIMIT_MAX = 8;

    /** Rate-limit window in seconds. */
    const RATE_LIMIT_WINDOW = 3600;

    public static function init() {
        // Render honeypot + timestamp on both registration forms
        add_action('register_form', [__CLASS__, 'render_honeypot']);
        add_action('woocommerce_register_form', [__CLASS__, 'render_honeypot']);

        // Validate WordPress core registration
        add_filter('registration_errors', [__CLASS__, 'validate_core'], 10, 3);

        // Validate WooCommerce registration
        add_action('woocommerce_register_post', [__CLASS__, 'validate_woocommerce'], 10, 3);

        // Count only accounts that are actually created (not failed attempts),
        // so a real user who mistypes their password isn't locked out.
        add_action('user_register', [__CLASS__, 'record_registration']);

        // Admin monitoring page
        add_action('admin_menu', [__CLASS__, 'add_admin_menu'], 99);
    }

    /**
     * Register the "Registration Guard" monitoring page under GPS Courses.
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gps-dashboard',
            __('Registration Guard', 'gps-courses'),
            __('Registration Guard', 'gps-courses'),
            'manage_options',
            'gps-registration-guard',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Render the monitoring page: total blocked, breakdown by reason,
     * and the last 100 blocked attempts.
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $total = (int) get_option('gps_reg_guard_blocked_total', 0);
        $log   = get_option('gps_reg_guard_log', []);
        if (!is_array($log)) {
            $log = [];
        }

        $reason_counts = [];
        foreach ($log as $row) {
            $r = $row['reason'] ?? 'unknown';
            $reason_counts[$r] = ($reason_counts[$r] ?? 0) + 1;
        }
        arsort($reason_counts);

        $reason_labels = [
            'honeypot'         => __('Honeypot filled', 'gps-courses'),
            'too_fast'         => __('Submitted too fast', 'gps-courses'),
            'disposable_email' => __('Disposable email', 'gps-courses'),
            'hex_email'        => __('Bot-signature email', 'gps-courses'),
            'rate_limited'     => __('IP rate limited', 'gps-courses'),
        ];
        ?>
        <div class="wrap">
            <h1><?php _e('Registration Guard', 'gps-courses'); ?></h1>
            <p style="color:#666;max-width:720px;">
                <?php _e('Blocks automated bot registrations while keeping sign-up open for real users. Below are the attempts it has stopped (most recent 100 kept).', 'gps-courses'); ?>
            </p>

            <div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0;">
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 24px;min-width:180px;">
                    <div style="font-size:32px;font-weight:700;color:#0B52AC;"><?php echo esc_html(number_format_i18n($total)); ?></div>
                    <div style="color:#666;"><?php _e('Total bots blocked', 'gps-courses'); ?></div>
                </div>
                <?php foreach ($reason_counts as $reason => $count): ?>
                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 24px;min-width:150px;">
                        <div style="font-size:24px;font-weight:700;color:#333;"><?php echo esc_html(number_format_i18n($count)); ?></div>
                        <div style="color:#666;"><?php echo esc_html($reason_labels[$reason] ?? $reason); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php _e('Recent blocked attempts', 'gps-courses'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:150px;"><?php _e('Time', 'gps-courses'); ?></th>
                        <th style="width:140px;"><?php _e('Reason', 'gps-courses'); ?></th>
                        <th style="width:110px;"><?php _e('Vector', 'gps-courses'); ?></th>
                        <th><?php _e('Email', 'gps-courses'); ?></th>
                        <th><?php _e('Username', 'gps-courses'); ?></th>
                        <th style="width:130px;"><?php _e('IP', 'gps-courses'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($log)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:30px;color:#666;">
                            <?php _e('No blocked attempts recorded yet.', 'gps-courses'); ?>
                        </td></tr>
                    <?php else: foreach ($log as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row['time'] ?? ''); ?></td>
                            <td><?php echo esc_html($reason_labels[$row['reason'] ?? ''] ?? ($row['reason'] ?? '')); ?></td>
                            <td><?php echo esc_html($row['vector'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['email'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['user'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['ip'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Output the honeypot + timestamp fields.
     * The honeypot is visually hidden but left in the DOM so bots fill it.
     */
    public static function render_honeypot() {
        $ts = time();
        ?>
        <div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:-9999px!important;height:0;overflow:hidden;" tabindex="-1">
            <label for="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>">Leave this field empty</label>
            <input type="text" name="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" id="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" tabindex="-1" autocomplete="off" value="">
        </div>
        <input type="hidden" name="<?php echo esc_attr(self::TIMESTAMP_FIELD); ?>" value="<?php echo esc_attr($ts); ?>">
        <?php
    }

    /**
     * WordPress core registration validation.
     *
     * @param \WP_Error $errors
     * @param string    $sanitized_user_login
     * @param string    $user_email
     * @return \WP_Error
     */
    public static function validate_core($errors, $sanitized_user_login, $user_email) {
        $reason = self::detect_bot($user_email, $sanitized_user_login);
        if ($reason) {
            self::log_block($user_email, $sanitized_user_login, $reason, 'core');
            $errors->add('gps_registration_blocked', self::user_message());
        }
        return $errors;
    }

    /**
     * WooCommerce registration validation.
     *
     * @param string    $username
     * @param string    $email
     * @param \WP_Error $errors
     */
    public static function validate_woocommerce($username, $email, $errors) {
        $reason = self::detect_bot($email, $username);
        if ($reason) {
            self::log_block($email, $username, $reason, 'woocommerce');
            $errors->add('gps_registration_blocked', self::user_message());
        }
    }

    /**
     * Core detection logic. Returns a short reason string if the current
     * registration request looks automated, or null if it looks human.
     *
     * @param string $email
     * @param string $username
     * @return string|null
     */
    public static function detect_bot($email, $username = '') {
        // Allow developers/admins to bypass all checks
        if (apply_filters('gps_registration_guard_bypass', false, $email, $username)) {
            return null;
        }

        // 1. Honeypot — must be present and empty
        if (!empty($_POST[self::HONEYPOT_FIELD])) {
            return 'honeypot';
        }

        // 2. Time trap — reject impossibly fast submissions
        if (isset($_POST[self::TIMESTAMP_FIELD])) {
            $elapsed = time() - (int) $_POST[self::TIMESTAMP_FIELD];
            // Negative = tampered clock; too small = bot. (Large is fine — page cache.)
            if ($elapsed >= 0 && $elapsed < self::MIN_FILL_SECONDS) {
                return 'too_fast';
            }
        }

        $email  = strtolower(trim((string) $email));
        $domain = strpos($email, '@') !== false ? substr(strrchr($email, '@'), 1) : '';
        $local  = strpos($email, '@') !== false ? substr($email, 0, strpos($email, '@')) : $email;

        // 3. Disposable / throwaway email domains
        if ($domain && in_array($domain, self::disposable_domains(), true)) {
            return 'disposable_email';
        }

        // 4. Bot-signature: pure-hex local part (e.g. a0878ec76cca4901@gmail.com).
        //    Real people never have 12+ hex-only mailbox names. Deliberately does
        //    NOT block name+digits (real customers can match that).
        if (preg_match('/^[0-9a-f]{12,}$/', $local)) {
            return 'hex_email';
        }

        // 5. Per-IP rate limit
        if (self::rate_limited()) {
            return 'rate_limited';
        }

        return null;
    }

    /**
     * Per-IP rate limit check (READ-ONLY). Returns true if this IP has already
     * created RATE_LIMIT_MAX accounts within the window. The counter is only
     * incremented on real account creation (see record_registration), so failed
     * legitimate submissions never count against a user.
     *
     * @return bool
     */
    protected static function rate_limited() {
        $ip = self::client_ip();
        if (!$ip) {
            return false;
        }
        $count = (int) get_transient('gps_reg_rl_' . md5($ip));
        return $count >= self::RATE_LIMIT_MAX;
    }

    /**
     * Increment the per-IP created-account counter. Hooked to `user_register`,
     * which fires for both WordPress and WooCommerce account creation.
     *
     * @param int $user_id
     */
    public static function record_registration($user_id) {
        $ip = self::client_ip();
        if (!$ip) {
            return;
        }
        $key   = 'gps_reg_rl_' . md5($ip);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
    }

    /**
     * Best-effort client IP (respects common proxy headers but falls back safely).
     *
     * @return string
     */
    protected static function client_ip() {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }

    /**
     * Generic message shown to a blocked registrant.
     * Intentionally vague so bots can't fingerprint which layer tripped.
     */
    protected static function user_message() {
        return __('Registration could not be completed. If you believe this is an error, please contact us.', 'gps-courses');
    }

    /**
     * Log a blocked registration attempt: to error_log, a running counter,
     * and a rolling buffer of the last 100 blocks (for an admin view later).
     */
    protected static function log_block($email, $username, $reason, $vector) {
        $ip = self::client_ip();

        error_log(sprintf(
            'GPS Registration Guard: BLOCKED [%s] via %s — email="%s" user="%s" ip=%s',
            $reason, $vector, $email, $username, $ip
        ));

        // Total counter
        $total = (int) get_option('gps_reg_guard_blocked_total', 0);
        update_option('gps_reg_guard_blocked_total', $total + 1, false);

        // Rolling buffer (last 100)
        $log = get_option('gps_reg_guard_log', []);
        if (!is_array($log)) {
            $log = [];
        }
        array_unshift($log, [
            'time'    => current_time('mysql'),
            'reason'  => $reason,
            'vector'  => $vector,
            'email'   => $email,
            'user'    => $username,
            'ip'      => $ip,
        ]);
        $log = array_slice($log, 0, 100);
        update_option('gps_reg_guard_log', $log, false);
    }

    /**
     * Known disposable / temporary email domains. Extendable via filter.
     * Seeded with providers observed in the Sept-2026 bot registration wave
     * plus common throwaway services.
     *
     * @return array
     */
    public static function disposable_domains() {
        $domains = [
            // Observed in the bot wave on this site
            'pngzero.com', 'gotemv.com', 'simerm.com', 'kimdyn.com',
            'imfaya.com', 'pacfut.com',
            // RFC 2606 reserved domains — can never receive mail
            'example.com', 'example.net', 'example.org',
            // Common throwaway providers
            'mailinator.com', 'guerrillamail.com', 'guerrillamail.info',
            '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'tempmailo.com',
            'yopmail.com', 'trashmail.com', 'getnada.com', 'dispostable.com',
            'sharklasers.com', 'grr.la', 'maildrop.cc', 'throwawaymail.com',
            'fakeinbox.com', 'mohmal.com', 'emailondeck.com', 'mailnesia.com',
            'moakt.com', 'tempr.email', 'spam4.me', 'mailcatch.com',
        ];
        return apply_filters('gps_registration_guard_disposable_domains', $domains);
    }
}
