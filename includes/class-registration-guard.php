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
 *   7. Email verification code  — the My Account form requires a 6-digit code
 *                                 emailed to the address being registered.
 *
 * Hooks both WordPress core (wp-login.php?action=register) and WooCommerce
 * (My Account / checkout) registration flows — the two vectors that create
 * `customer` accounts on this site. The email code applies to the My Account
 * form only; checkout account creation is already gated by payment.
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

    /** Email verification code: lifetime in seconds. */
    const OTP_TTL = 600;

    /** Wrong guesses allowed per code before it is invalidated. */
    const OTP_MAX_ATTEMPTS = 5;

    /** Codes that can be sent per email within OTP_SEND_WINDOW. */
    const OTP_SEND_MAX = 3;

    /** Codes that can be sent per IP within OTP_SEND_WINDOW (clinics/venues share an IP). */
    const OTP_IP_SEND_MAX = 15;

    /** Window for OTP_SEND_MAX, in seconds. */
    const OTP_SEND_WINDOW = 900;

    /** Minimum seconds between two codes for the same email. */
    const OTP_COOLDOWN = 60;

    /**
     * Set by a template that renders its own code input (the GPS My Account
     * template), so the default field + "Send code" button is not added twice.
     *
     * @var bool
     */
    public static $custom_code_field = false;

    public static function init() {
        // Render honeypot + timestamp on both registration forms
        add_action('register_form', [__CLASS__, 'render_honeypot']);
        add_action('woocommerce_register_form', [__CLASS__, 'render_honeypot']);

        // Email verification code on the My Account registration form.
        // `woocommerce_process_registration_errors` fires only in the My Account
        // registration handler — never in checkout or the Store API — so the code
        // can't be skipped by renaming the form's nonce field.
        add_action('woocommerce_register_form', [__CLASS__, 'render_otp_field'], 20);
        add_filter('woocommerce_process_registration_errors', [__CLASS__, 'validate_registration_code'], 10, 4);
        add_action('wp_ajax_nopriv_gps_reg_send_code', [__CLASS__, 'ajax_send_code']);
        add_action('wp_ajax_gps_reg_send_code', [__CLASS__, 'ajax_send_code']);
        add_action('wp_ajax_nopriv_gps_reg_check_code', [__CLASS__, 'ajax_check_code']);
        add_action('wp_ajax_gps_reg_check_code', [__CLASS__, 'ajax_check_code']);

        // Same email code on Fluent Forms user-registration forms (e.g. /register/).
        // The field renders right before the submit button (Fluent Forms prints
        // the button at priority 10); validation uses the filter Fluent Forms
        // applies only to forms with user registration and only for guests.
        add_action('fluentform/render_item_submit_button', [__CLASS__, 'render_otp_field_fluentform'], 5, 2);
        add_action('fluentform/render_item_step_end', [__CLASS__, 'render_otp_field_fluentform'], 5, 2); // multi-step forms
        // Late priority so Fluent Forms Pro's own checks (email/username taken)
        // are already in $errors when we decide whether to flag a welcome email.
        add_filter('fluentform/validation_user_registration_errors', [__CLASS__, 'validate_fluentform_registration'], 999, 4);
        // Fluent Forms drops submitted keys that aren't form fields; whitelist ours
        // for registration forms, then keep it out of the stored entry.
        add_filter('fluentform/white_listed_fields', [__CLASS__, 'fluentform_whitelist_code_field'], 10, 2);
        add_filter('fluentform/insert_response_data', [__CLASS__, 'fluentform_strip_code_field'], 10, 3);

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
            'otp_missing'      => __('No email code', 'gps-courses'),
            'otp_expired'      => __('Expired email code', 'gps-courses'),
            'otp_invalid'      => __('Wrong email code', 'gps-courses'),
            'otp_too_many'     => __('Too many wrong codes', 'gps-courses'),
            'otp_send_limit'   => __('Code request limit', 'gps-courses'),
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
     * My Account registration only: require a valid emailed code.
     *
     * @param \WP_Error $errors
     * @param string    $username
     * @param string    $password
     * @param string    $email
     * @return \WP_Error
     */
    public static function validate_registration_code($errors, $username, $password, $email) {
        // Run the bot checks first so the dashboard records the real reason
        // (this filter fires before woocommerce_register_post).
        $reason = self::detect_bot($email, $username);
        if ($reason) {
            self::log_block($email, $username, $reason, 'my_account');
            $errors->add('gps_registration_blocked', self::user_message());
            return $errors;
        }

        $otp_error = self::verify_otp($email, isset($_POST['gps_reg_code']) ? wp_unslash($_POST['gps_reg_code']) : '');
        if ($otp_error) {
            self::log_block($email, $username, $otp_error['reason'], 'my_account');
            $errors->add('gps_registration_code', $otp_error['message']);
        }
        return $errors;
    }

    /* ====================================================================
     * Email verification code (My Account registration)
     * ==================================================================== */

    /**
     * Render the verification-code field + "Send code" button below the
     * WooCommerce registration fields. The click handler is delegated and reads
     * its config from data attributes, so it keeps working when JS optimizers
     * (FlyingPress delay/defer, Rocket Loader) move or delay inline scripts.
     */
    public static function render_otp_field() {
        if (self::$custom_code_field) {
            return;
        }
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide gps-reg-code-row">
            <label for="gps_reg_code"><?php esc_html_e('Email verification code', 'gps-courses'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
            <span style="display:flex; gap:8px;">
                <input type="text" class="woocommerce-Input input-text" name="gps_reg_code" id="gps_reg_code"
                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                       placeholder="123456" style="flex:1; letter-spacing:4px;" />
                <button type="button" class="button gps-reg-send-code" style="white-space:nowrap;"<?php echo self::send_code_button_attrs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in send_code_button_attrs() ?>><?php esc_html_e('Send code', 'gps-courses'); ?></button>
            </span>
            <small class="gps-reg-code-msg" style="display:block; margin-top:6px;" aria-live="polite">
                <?php esc_html_e('Enter your email above, then click "Send code". We will email you a 6-digit code.', 'gps-courses'); ?>
            </small>
        </p>
        <?php
        self::print_send_code_script();
    }

    /**
     * Print the delegated "Send code" click handler once per page.
     */
    protected static function print_send_code_script() {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <script id="gps-reg-send-code-js">
        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('.gps-reg-send-code') : null;
            if (!btn) { return; }
            e.preventDefault();
            var form = btn.closest('form');
            var row = btn.closest('.gps-reg-code-row');
            var msg = row ? row.querySelector('.gps-reg-code-msg') : null;
            var say = function (t) { if (msg) { msg.textContent = t; } };
            var email = form ? (form.querySelector('input[name="email"]') || form.querySelector('input[type="email"]')) : null;
            if (!email || !email.value) { say(btn.getAttribute('data-msg-email')); return; }
            btn.disabled = true;
            say(btn.getAttribute('data-msg-sending'));
            var body = new FormData();
            body.append('action', 'gps_reg_send_code');
            body.append('email', email.value);
            var hp = form.querySelector('[name="<?php echo esc_js(self::HONEYPOT_FIELD); ?>"]');
            if (hp) { body.append('<?php echo esc_js(self::HONEYPOT_FIELD); ?>', hp.value); }
            fetch(btn.getAttribute('data-ajax'), { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    say(res && res.data && res.data.message ? res.data.message : btn.getAttribute('data-msg-error'));
                    var wait = (res && res.success) ? parseInt(btn.getAttribute('data-cooldown'), 10) * 1000 : 0;
                    setTimeout(function () { btn.disabled = false; }, wait);
                })
                .catch(function () { say(btn.getAttribute('data-msg-error')); btn.disabled = false; });
        });
        </script>
        <?php
    }

    /** Data attributes the "Send code" button needs (shared by both forms). */
    protected static function send_code_button_attrs() {
        $attrs = [
            'data-ajax'        => admin_url('admin-ajax.php'),
            'data-cooldown'    => (int) self::OTP_COOLDOWN,
            'data-msg-email'   => __('Please enter your email address first.', 'gps-courses'),
            'data-msg-sending' => __('Sending…', 'gps-courses'),
            'data-msg-error'   => __('Something went wrong. Please try again.', 'gps-courses'),
        ];
        $out = '';
        foreach ($attrs as $k => $v) {
            $out .= ' ' . $k . '="' . esc_attr($v) . '"';
        }
        return $out;
    }

    /**
     * Fluent Forms: render the code field before the submit button of forms
     * that register users (e.g. /register/), for guests only.
     *
     * @param array  $button Submit button config (unused)
     * @param object $form   Fluent Forms form object
     */
    public static function render_otp_field_fluentform($button, $form) {
        if (is_user_logged_in() || !self::fluentform_registers_users($form)) {
            return;
        }
        ?>
        <div class="ff-el-group gps-reg-code-row">
            <div class="ff-el-input--label ff-el-is-required asterisk-right">
                <label for="gps_reg_code_ff_<?php echo (int) $form->id; ?>"><?php esc_html_e('Email verification code', 'gps-courses'); ?></label>
            </div>
            <div class="ff-el-input--content">
                <span style="display:flex; gap:8px;">
                    <input type="text" class="ff-el-form-control" name="gps_reg_code" id="gps_reg_code_ff_<?php echo (int) $form->id; ?>"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                           placeholder="123456" style="flex:1; letter-spacing:4px;" />
                    <button type="button" class="ff-btn ff-btn-md gps-reg-send-code" style="white-space:nowrap;"<?php echo self::send_code_button_attrs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in send_code_button_attrs() ?>><?php esc_html_e('Send code', 'gps-courses'); ?></button>
                </span>
                <small class="gps-reg-code-msg" style="display:block; margin-top:6px;" aria-live="polite">
                    <?php esc_html_e('Enter your email above, then click "Send code". We will email you a 6-digit code.', 'gps-courses'); ?>
                </small>
            </div>
        </div>
        <?php
        self::print_send_code_script();
    }

    /**
     * Fluent Forms: require a valid email code before the user is registered.
     * Applied by Fluent Forms only to forms with user registration, for guests.
     *
     * @param array  $errors   Field name => messages
     * @param array  $formData Sanitized submitted data
     * @param object $form
     * @param array  $fields
     * @return array
     */
    public static function validate_fluentform_registration($errors, $formData, $form, $fields) {
        $email = self::fluentform_email($formData, $fields);

        $reason = self::detect_bot($email, '', false);
        if ($reason) {
            self::log_block($email, '', $reason, 'fluentform');
            $errors['gps_reg_code'] = [self::user_message()];
            return $errors;
        }

        $otp_error = self::verify_otp($email, isset($formData['gps_reg_code']) ? $formData['gps_reg_code'] : '');
        if ($otp_error) {
            self::log_block($email, '', $otp_error['reason'], 'fluentform');
            $errors['gps_reg_code'] = [$otp_error['message']];
            return $errors;
        }

        // Verified: send our branded welcome once the account is actually created.
        if (empty($errors)) {
            set_transient('gps_reg_welcome_' . md5(strtolower(trim($email))), 1, self::OTP_TTL);
        }
        return $errors;
    }

    /**
     * Fluent Forms keeps only form fields + whitelisted keys in the submission
     * (SubmissionHandlerService::prepareHandler). Whitelist the code field on
     * registration forms so the validator receives it.
     */
    public static function fluentform_whitelist_code_field($fields, $form_id) {
        if (self::fluentform_registers_users((object) ['id' => $form_id])) {
            $fields[] = 'gps_reg_code';
        }
        return $fields;
    }

    /** Never store the verification code in the form entry. */
    public static function fluentform_strip_code_field($formData, $form_id = 0, $inputConfigs = []) {
        if (is_array($formData)) {
            unset($formData['gps_reg_code']);
        }
        return $formData;
    }

    /** Whether a Fluent Forms form has a user-registration integration. */
    protected static function fluentform_registers_users($form) {
        if (!$form || empty($form->id) || !class_exists('\FluentForm\App\Helpers\Helper')) {
            return false;
        }
        return 'yes' === \FluentForm\App\Helpers\Helper::getFormMeta($form->id, '_has_user_registration');
    }

    /** The submitted email: the `email` field, else the form's first email-type field. */
    protected static function fluentform_email($formData, $fields) {
        if (!empty($formData['email']) && is_string($formData['email'])) {
            return strtolower(trim($formData['email']));
        }
        foreach ((array) $fields as $name => $field) {
            if (isset($field['element']) && 'input_email' === $field['element'] && !empty($formData[$name]) && is_string($formData[$name])) {
                return strtolower(trim((string) $formData[$name]));
            }
        }
        return '';
    }

    /**
     * AJAX: email a 6-digit verification code to the address being registered.
     * Runs the bot checks so the endpoint can't be used to send mail for bots,
     * and throttles per email and per IP.
     */
    public static function ajax_send_code() {
        // No nonce: for guests it is the same public value for everyone and it
        // goes stale on cached pages (e.g. /register/ under FlyingPress). The
        // endpoint is protected by the bot checks and the per-email/IP throttles.
        $email = sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : ''));
        if (!is_email($email)) {
            wp_send_json_error(['code' => 'invalid_email', 'message' => __('Please enter a valid email address.', 'gps-courses')]);
        }
        $email = strtolower($email);

        // No time trap here: autofill + a quick click is a legitimate human pattern.
        $reason = self::detect_bot($email, '', false);
        if ($reason) {
            self::log_block($email, '', $reason, 'send_code');
            wp_send_json_error(['code' => 'blocked', 'message' => self::user_message()]);
        }

        // Throttle before anything else touches the email (also limits enumeration).
        $email_key = md5($email);
        $ip        = self::client_ip();
        $ip_key    = $ip ? md5($ip) : '';
        $ip_max    = (int) apply_filters('gps_reg_otp_ip_max', self::OTP_IP_SEND_MAX);
        if (get_transient('gps_reg_otp_cd_' . $email_key)) {
            wp_send_json_error(['code' => 'cooldown', 'message' => __('Please wait a minute before requesting another code.', 'gps-courses')]);
        }
        $sent_email = (int) get_transient('gps_reg_otp_n_' . $email_key);
        $sent_ip    = $ip_key ? (int) get_transient('gps_reg_otp_ip_' . $ip_key) : 0;
        if ($sent_email >= self::OTP_SEND_MAX || ($ip_key && $sent_ip >= $ip_max)) {
            self::log_block($email, '', 'otp_send_limit', 'send_code');
            wp_send_json_error(['code' => 'limit', 'message' => __('Too many codes requested. Please try again in 15 minutes.', 'gps-courses')]);
        }

        if (email_exists($email)) {
            // Count lookups of existing accounts toward the IP cap so this
            // endpoint can't be used for unlimited email enumeration.
            if ($ip_key) {
                set_transient('gps_reg_otp_ip_' . $ip_key, $sent_ip + 1, self::OTP_SEND_WINDOW);
            }
            wp_send_json_error(['code' => 'exists', 'message' => __('An account with this email already exists. Please log in instead.', 'gps-courses')]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        set_transient(self::otp_key($email), [
            'hash'     => self::hash_code($code),
            'attempts' => 0,
            'expires'  => time() + self::OTP_TTL,
        ], self::OTP_TTL);

        if (!self::send_code_email($email, $code)) {
            delete_transient(self::otp_key($email));
            wp_send_json_error(['code' => 'mail_failed', 'message' => __('We could not send the email. Please try again or contact us.', 'gps-courses')]);
        }

        set_transient('gps_reg_otp_cd_' . $email_key, 1, self::OTP_COOLDOWN);
        set_transient('gps_reg_otp_n_' . $email_key, $sent_email + 1, self::OTP_SEND_WINDOW);
        if ($ip_key) {
            set_transient('gps_reg_otp_ip_' . $ip_key, $sent_ip + 1, self::OTP_SEND_WINDOW);
        }

        wp_send_json_success([
            'code'    => 'sent',
            'message' => sprintf(
                /* translators: %s: email address */
                __('We sent a 6-digit code to %s. It expires in 10 minutes — check your spam folder if you don\'t see it.', 'gps-courses'),
                $email
            ),
        ]);
    }

    /**
     * AJAX: check a code before the last registration step, so a typo is caught
     * right away instead of after the profile step. Uses the same attempt
     * counter as the final check and does not consume the code.
     */
    public static function ajax_check_code() {
        $email = strtolower(sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : '')));
        $code  = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        if (!is_email($email)) {
            wp_send_json_error(['code' => 'invalid_email', 'message' => __('Please enter a valid email address.', 'gps-courses')]);
        }

        // Per-IP cap on guesses across all emails (the per-code cap is OTP_MAX_ATTEMPTS).
        $ip     = self::client_ip();
        $ip_key = $ip ? 'gps_reg_otp_chk_' . md5($ip) : '';
        if ($ip_key) {
            $checks = (int) get_transient($ip_key);
            if ($checks >= (int) apply_filters('gps_reg_otp_check_ip_max', 20)) {
                wp_send_json_error(['code' => 'limit', 'message' => __('Too many attempts. Please try again in 15 minutes.', 'gps-courses')]);
            }
            set_transient($ip_key, $checks + 1, self::OTP_SEND_WINDOW);
        }

        $error = self::verify_otp($email, $code);
        if ($error) {
            // Log real guesses only, so calls without a pending code can't flood the log.
            if (in_array($error['reason'], ['otp_invalid', 'otp_too_many'], true)) {
                self::log_block($email, '', $error['reason'], 'check_code');
            }
            wp_send_json_error(['code' => $error['reason'], 'message' => $error['message']]);
        }
        wp_send_json_success(['code' => 'ok']);
    }

    /**
     * Check the submitted code against the stored one. A correct code stays
     * valid until the account is actually created (record_registration deletes
     * it), so a later validation error doesn't force the user to request a new one.
     *
     * @return array|null ['reason' => string, 'message' => string] on failure, null on success
     */
    protected static function verify_otp($email, $code) {
        $key    = self::otp_key($email);
        $stored = get_transient($key);
        $code   = preg_replace('/\D/', '', (string) $code);

        if (!is_array($stored) || empty($stored['hash']) || time() > (int) $stored['expires']) {
            delete_transient($key);
            return [
                'reason'  => $code === '' ? 'otp_missing' : 'otp_expired',
                'message' => __('Please request a verification code and enter it to create your account.', 'gps-courses'),
            ];
        }

        if (!hash_equals($stored['hash'], self::hash_code($code))) {
            $stored['attempts'] = (int) $stored['attempts'] + 1;
            if ($stored['attempts'] >= self::OTP_MAX_ATTEMPTS) {
                delete_transient($key);
                return [
                    'reason'  => 'otp_too_many',
                    'message' => __('Too many incorrect codes. Please request a new code.', 'gps-courses'),
                ];
            }
            set_transient($key, $stored, max(1, (int) $stored['expires'] - time()));
            return [
                'reason'  => 'otp_invalid',
                'message' => __('The verification code is incorrect. Please check the email and try again.', 'gps-courses'),
            ];
        }

        return null;
    }

    /** Transient key for an email's pending code. */
    protected static function otp_key($email) {
        return 'gps_reg_otp_' . md5(strtolower(trim((string) $email)));
    }

    /** Keyed hash so codes are never stored in plain text. */
    protected static function hash_code($code) {
        return hash_hmac('sha256', (string) $code, wp_salt('auth'));
    }

    /**
     * Send the branded verification email from the plugin's configured sender.
     */
    protected static function send_code_email($email, $code) {
        $site = self::site_name();
        /* translators: 1: site name, 2: 6-digit code */
        $subject = sprintf(__('Your %1$s verification code: %2$s', 'gps-courses'), $site, $code);
        /* translators: %s: site name */
        $intro = sprintf(__('Use this code to create your %s account:', 'gps-courses'), $site);
        $body  = '<p style="margin:0 0 20px;">' . esc_html($intro) . '</p>'
            . '<div style="display:inline-block;background:#f8f9fa;border:2px dashed #BC9D67;border-radius:8px;padding:14px 28px;font-size:32px;font-weight:bold;letter-spacing:8px;color:#193463;font-family:\'Courier New\',monospace;">' . esc_html($code) . '</div>'
            . '<p style="margin:20px 0 0;color:#6c757d;font-size:13px;">' . esc_html__('The code expires in 10 minutes. If you did not try to create an account, you can ignore this email.', 'gps-courses') . '</p>';

        return wp_mail($email, $subject, self::email_layout(__('Verify your email', 'gps-courses'), $body), self::mail_headers());
    }

    /**
     * Welcome email for accounts created through a verified Fluent Forms
     * registration (the WordPress default notification is disabled on this site).
     *
     * @param \WP_User $user
     */
    protected static function send_welcome_email($user) {
        $site  = self::site_name();
        $first = trim((string) $user->first_name);
        /* translators: %s: site name */
        $subject = sprintf(__('Welcome to %s — your account is ready', 'gps-courses'), $site);
        /* translators: %s: first name */
        $hello = $first ? sprintf(__('Hi %s,', 'gps-courses'), $first) : __('Hi,', 'gps-courses');
        $login = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        $body  = '<p style="margin:0 0 14px;text-align:left;">' . esc_html($hello) . '</p>'
            . '<p style="margin:0 0 22px;text-align:left;">' . esc_html__('Your account has been created and your email is verified. Log in with your email and the password you chose to see your courses, tickets and CE credits.', 'gps-courses') . '</p>'
            . '<a href="' . esc_url($login) . '" style="display:inline-block;background:#0B52AC;color:#ffffff;padding:14px 32px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:16px;">' . esc_html__('Log in to my account', 'gps-courses') . '</a>'
            . '<p style="margin:22px 0 0;color:#6c757d;font-size:13px;">' . esc_html__('Forgot your password? Use "Lost your password?" on the login page.', 'gps-courses') . '</p>';

        return wp_mail($user->user_email, $subject, self::email_layout(__('Welcome!', 'gps-courses'), $body), self::mail_headers());
    }

    /** Site name, HTML entities decoded. */
    protected static function site_name() {
        return wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    }

    /**
     * HTML + From headers. Email Settings stores `gps_email_from_email`; General
     * Settings stores `gps_email_from_address` — prefer either before admin_email.
     */
    protected static function mail_headers() {
        $from_name = get_option('gps_email_from_name') ?: self::site_name();
        $from_mail = get_option('gps_email_from_email') ?: (get_option('gps_email_from_address') ?: get_option('admin_email'));
        return ['Content-Type: text/html; charset=UTF-8', sprintf('From: %s <%s>', $from_name, $from_mail)];
    }

    /** Shared branded layout: navy header + white card. $body is already-escaped HTML. */
    protected static function email_layout($heading, $body) {
        return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f5f5;"><tr><td align="center" style="padding:40px 20px;">'
            . '<table width="560" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">'
            . '<tr><td style="background:#193463;color:#ffffff;padding:28px 30px;text-align:center;border-radius:8px 8px 0 0;">'
            . '<h1 style="margin:0;font-size:22px;color:#ffffff;">' . esc_html($heading) . '</h1></td></tr>'
            . '<tr><td style="padding:30px;text-align:center;color:#333333;font-size:15px;line-height:1.5;">' . $body . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /**
     * Core detection logic. Returns a short reason string if the current
     * registration request looks automated, or null if it looks human.
     *
     * @param string $email
     * @param string $username
     * @param bool   $check_timing Apply the time trap (off for the "Send code" request).
     * @return string|null
     */
    public static function detect_bot($email, $username = '', $check_timing = true) {
        // Allow developers/admins to bypass all checks
        if (apply_filters('gps_registration_guard_bypass', false, $email, $username)) {
            return null;
        }

        // 1. Honeypot — must be present and empty
        if (!empty($_POST[self::HONEYPOT_FIELD])) {
            return 'honeypot';
        }

        // 2. Time trap — reject impossibly fast submissions
        if ($check_timing && isset($_POST[self::TIMESTAMP_FIELD])) {
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
     * Hooked to `user_register` (fires for WordPress and WooCommerce account
     * creation): consume the email code — it is single-use once the account
     * exists — and increment the per-IP created-account counter.
     *
     * @param int $user_id
     */
    public static function record_registration($user_id) {
        $user = get_userdata($user_id);
        if ($user && $user->user_email) {
            delete_transient(self::otp_key($user->user_email));

            // Fluent Forms registrations that passed the email code get our
            // welcome email (WooCommerce sends its own new-account email). Only
            // when this account is being created by that Fluent Forms submission,
            // and sent at shutdown so the names Pro saves after insert are set.
            // NOTE: WordPress's default "new user" email is turned off on this
            // site (Manage Notification E-mails). If it is turned back on, disable
            // ours with: add_filter('gps_reg_send_welcome_email', '__return_false');
            $welcome_key = 'gps_reg_welcome_' . md5(strtolower(trim($user->user_email)));
            if (get_transient($welcome_key)) {
                delete_transient($welcome_key);
                $is_ff_submit = wp_doing_ajax() && isset($_REQUEST['action']) && 'fluentform_submit' === $_REQUEST['action'];
                if ($is_ff_submit && apply_filters('gps_reg_send_welcome_email', true, $user_id)) {
                    add_action('shutdown', function () use ($user_id) {
                        $fresh = get_userdata($user_id);
                        if ($fresh) {
                            self::send_welcome_email($fresh);
                        }
                    });
                }
            }
        }

        $ip = self::client_ip();
        if (!$ip) {
            return;
        }
        $key   = 'gps_reg_rl_' . md5($ip);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
    }

    /**
     * Client IP. Uses REMOTE_ADDR (the site is served directly, no proxy/CDN in
     * front). Forwarded headers are client-controlled and spoofable, so they are
     * only honored when the direct peer is a proxy listed via the
     * `gps_registration_guard_trusted_proxies` filter.
     *
     * @return string
     */
    protected static function client_ip() {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
        $trusted = (array) apply_filters('gps_registration_guard_trusted_proxies', []);

        if ($remote && in_array($remote, $trusted, true)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
                if (!empty($_SERVER[$h])) {
                    $ip = trim(explode(',', $_SERVER[$h])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
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
