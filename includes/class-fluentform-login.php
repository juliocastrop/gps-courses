<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Login through the Fluent Forms login form (#4, on /login/, /access-account/
 * and /registration-confirmation/).
 *
 * Replaces WPCode snippet #7774, which checked the password by hand and set the
 * auth cookie directly: that skipped wp_signon(), so the wp_login /
 * wp_login_failed hooks (login limiters, security plugins, guest-order linking)
 * never ran, and its refer_url check allowed redirects to other sites.
 *
 * Temporary: login moves to WooCommerce My Account in phase 2 of the forms
 * redesign, and this class goes away with form #4.
 */
class Fluentform_Login {

    const FORM_ID = 4;

    public static function init() {
        add_action('fluentform/before_insert_submission', [__CLASS__, 'handle_login'], 10, 3);
    }

    /**
     * Sign the user in and answer Fluent Forms' AJAX submit. Ends the request,
     * so login attempts are never stored as form entries.
     */
    public static function handle_login($insert_data, $data, $form) {
        $form_id = (int) apply_filters('gps_fluentform_login_form_id', self::FORM_ID);
        if (!$form || (int) $form->id !== $form_id) {
            return;
        }

        $fallback     = home_url();
        $redirect_url = !empty($data['refer_url'])
            ? wp_validate_redirect(esc_url_raw((string) $data['refer_url']), $fallback)
            : $fallback;

        if (is_user_logged_in()) {
            wp_send_json_success([
                'result' => [
                    'redirectUrl' => $redirect_url,
                    'message'     => __('You are already logged in. Redirecting now...', 'gps-courses'),
                ],
            ]);
        }

        // Fluent passes the raw parse_str() values: no slashes to strip, and the
        // password must reach wp_signon() byte for byte.
        $email    = isset($data['email']) ? sanitize_text_field((string) $data['email']) : '';
        $password = isset($data['password']) ? (string) $data['password'] : '';

        if ($email === '' || $password === '') {
            self::send_error(__('Please enter your email and password.', 'gps-courses'));
        }

        $user = wp_signon([
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => false,
        ], is_ssl());

        if (is_wp_error($user)) {
            self::send_error(__('Email / password is not correct.', 'gps-courses'));
        }

        wp_set_current_user($user->ID);

        wp_send_json_success([
            'result' => [
                'redirectUrl' => $redirect_url,
                'message'     => __('You are logged in. Please wait while we redirect you...', 'gps-courses'),
            ],
        ]);
    }

    /**
     * Same shape as Fluent's own validation errors; 'login' is not a field
     * name, so the message shows in the form's general error area.
     */
    private static function send_error($message) {
        wp_send_json(['errors' => ['login' => [$message]]], 423);
    }
}
