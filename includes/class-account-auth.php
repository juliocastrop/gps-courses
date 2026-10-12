<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * One place to log in, create an account and reset a password: WooCommerce
 * My Account, in the GPS layout (navy panel + tabs, 3-step registration).
 *
 * WooCommerce keeps doing the real work (login, registration, lost/reset
 * password, nonces, redirects); this class only swaps the logged-out
 * templates, adds the profile fields to the new customer and sends the old
 * Fluent Forms pages (/login/, /register/ …) to My Account.
 */
class Account_Auth {

    /** Hidden field that marks a POST as coming from the GPS templates. */
    const FORM_MARKER = 'gps_auth_form';

    const MIN_PASSWORD_LENGTH = 8;

    /** Templates replaced by templates/myaccount/<same name>. */
    const TEMPLATES = [
        'myaccount/form-login.php',
        'myaccount/form-lost-password.php',
        'myaccount/lost-password-confirmation.php',
        'myaccount/form-reset-password.php',
    ];

    public static function init() {
        if (!apply_filters('gps_account_auth_enabled', true)) {
            return;
        }

        add_filter('wc_get_template', [__CLASS__, 'locate_template'], 20, 2);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 110);

        // Registration: our fields, checked before WooCommerce creates the user
        add_filter('woocommerce_process_registration_errors', [__CLASS__, 'validate_registration'], 5, 4);
        add_filter('woocommerce_new_customer_data', [__CLASS__, 'new_customer_data']);
        add_action('woocommerce_created_customer', [__CLASS__, 'save_profile'], 5, 3);

        // New password (reset form): same rule as registration
        add_action('validate_password_reset', [__CLASS__, 'validate_password_reset'], 10, 2);

        // Old Fluent Forms pages -> My Account
        add_action('template_redirect', [__CLASS__, 'redirect_legacy_pages'], 1);
    }

    /* ====================================================================
     * Templates and assets
     * ==================================================================== */

    public static function locate_template($template, $template_name) {
        if (!in_array($template_name, self::TEMPLATES, true)) {
            return $template;
        }
        $ours = GPSC_PATH . 'templates/' . $template_name;
        return file_exists($ours) ? $ours : $template;
    }

    public static function enqueue_assets() {
        if (is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
            return;
        }

        $css = GPSC_PATH . 'assets/css/gps-auth.css';
        $js  = GPSC_PATH . 'assets/js/gps-auth.js';
        wp_enqueue_style('gps-auth', GPSC_URL . 'assets/css/gps-auth.css', ['gps-forms'], file_exists($css) ? (string) filemtime($css) : GPSC_VERSION);
        wp_enqueue_script('gps-auth', GPSC_URL . 'assets/js/gps-auth.js', [], file_exists($js) ? (string) filemtime($js) : GPSC_VERSION, true);
        wp_localize_script('gps-auth', 'gpsAuth', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'cooldown' => Registration_Guard::OTP_COOLDOWN,
            'minPass'  => self::MIN_PASSWORD_LENGTH,
            'i18n'     => [
                'required'     => __('This field is required.', 'gps-courses'),
                'firstName'    => __('Enter your first name.', 'gps-courses'),
                'lastName'     => __('Enter your last name.', 'gps-courses'),
                'email'        => __('Enter a valid email address.', 'gps-courses'),
                'password'     => sprintf(__('Use at least %d characters, including a number.', 'gps-courses'), self::MIN_PASSWORD_LENGTH),
                'passwordMatch'=> __('The passwords do not match.', 'gps-courses'),
                'terms'        => __('Please accept the terms to continue.', 'gps-courses'),
                'code'         => __('Enter the 6-digit code from the email.', 'gps-courses'),
                'phone'        => __('Enter a valid phone number, or leave it empty.', 'gps-courses'),
                'sending'      => __('Sending…', 'gps-courses'),
                'checking'     => __('Checking…', 'gps-courses'),
                'creating'     => __('Creating your account…', 'gps-courses'),
                'error'        => __('Something went wrong. Please try again.', 'gps-courses'),
                'resend'       => __('Resend code', 'gps-courses'),
                /* translators: %d: seconds */
                'resendIn'     => __('Resend code in %ds', 'gps-courses'),
                'weak'         => __('Too short', 'gps-courses'),
                'fair'         => __('Add a number', 'gps-courses'),
                'good'         => __('Good password', 'gps-courses'),
                'strong'       => __('Strong password', 'gps-courses'),
                'show'         => __('Show', 'gps-courses'),
                'hide'         => __('Hide', 'gps-courses'),
            ],
        ]);
    }

    /**
     * Data shared by the templates.
     */
    public static function view_data() {
        $redirect_to = isset($_GET['redirect_to']) ? wp_validate_redirect(esc_url_raw(wp_unslash($_GET['redirect_to'])), '') : '';
        $account_url = wc_get_page_permalink('myaccount');
        $action_url  = $redirect_to ? add_query_arg('redirect_to', rawurlencode($redirect_to), $account_url) : $account_url;

        $view = 'login';
        if ((isset($_GET['action']) && $_GET['action'] === 'register') || isset($_POST['register'])) {
            $view = 'register';
        }

        return [
            'account_url'  => $account_url,
            'action_url'   => $action_url,
            'redirect_to'  => $redirect_to,
            'view'         => $view,
            'can_register' => 'yes' === get_option('woocommerce_enable_myaccount_registration'),
            'logo'         => self::logo_url(),
            'terms_url'    => self::terms_url(),
            'privacy_url'  => get_privacy_policy_url(),
            'roles'        => self::roles(),
            'interests'    => self::interests(),
        ];
    }

    /** White logo for the navy panel (PNG, also works in emails). */
    protected static function logo_url() {
        $id  = (int) apply_filters('gps_account_auth_logo_id', 7626);
        $url = $id ? wp_get_attachment_image_url($id, 'full') : '';
        return (string) apply_filters('gps_account_auth_logo_url', $url ?: '');
    }

    protected static function terms_url() {
        $id = function_exists('wc_terms_and_conditions_page_id') ? wc_terms_and_conditions_page_id() : 0;
        return $id ? get_permalink($id) : get_privacy_policy_url();
    }

    /** Same options as the old Fluent Forms registration (#6). */
    public static function roles() {
        return apply_filters('gps_account_auth_roles', [
            'Surgical and Restorative Dentist',
            'Surgical Dentist',
            'Restorative Dentist',
            'Dental Assistant',
            'Dental Technician',
            'Office Manager',
        ]);
    }

    public static function interests() {
        return apply_filters('gps_account_auth_interests', [
            'Complex Treatment Planning',
            'Literature Review',
            'Site development and Site preservation',
            'Implant placement',
            'Guided surgery',
            'Digital workflows',
            'Full Arch zirconia prosthesis design',
            'All-on-X digital protocols',
            'Implant restorations in the esthetic zone',
            'Other',
        ]);
    }

    /** Value of a posted field, unslashed and sanitized, for re-filling the form. */
    public static function posted($key) {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    /* ====================================================================
     * Registration
     * ==================================================================== */

    /** Password rule shared by registration and reset: length + a number. */
    public static function password_ok($password) {
        return strlen((string) $password) >= self::MIN_PASSWORD_LENGTH && preg_match('/\d/', (string) $password);
    }

    /**
     * Only for registrations sent from our template (the marker field); the
     * email code itself is checked by Registration_Guard on the same filter.
     */
    public static function validate_registration($errors, $username, $password, $email) {
        if (empty($_POST[self::FORM_MARKER])) {
            return $errors;
        }

        if (self::posted('gps_first_name') === '') {
            $errors->add('gps_first_name', __('Please enter your first name.', 'gps-courses'));
        }
        if (self::posted('gps_last_name') === '') {
            $errors->add('gps_last_name', __('Please enter your last name.', 'gps-courses'));
        }
        if ('no' === get_option('woocommerce_registration_generate_password') && !self::password_ok($password)) {
            $errors->add('gps_password', sprintf(__('Use a password with at least %d characters, including a number.', 'gps-courses'), self::MIN_PASSWORD_LENGTH));
        }
        if (empty($_POST['gps_terms'])) {
            $errors->add('gps_terms', __('Please accept the Terms and Conditions and Privacy Policy.', 'gps-courses'));
        }
        $phone = self::posted('gps_phone');
        if ($phone !== '' && !self::phone_ok($phone)) {
            $errors->add('gps_phone', __('Enter a valid phone number, or leave it empty.', 'gps-courses'));
        }
        return $errors;
    }

    /**
     * Names are set when the user is inserted, so user_register listeners
     * (FluentCRM sync, etc.) already see them.
     */
    public static function new_customer_data($data) {
        if (empty($_POST[self::FORM_MARKER])) {
            return $data;
        }
        $first = self::posted('gps_first_name');
        $last  = self::posted('gps_last_name');
        if ($first !== '') {
            $data['first_name'] = $first;
        }
        if ($last !== '') {
            $data['last_name'] = $last;
        }
        if ($first !== '' || $last !== '') {
            $data['display_name'] = trim($first . ' ' . $last);
        }
        return $data;
    }

    protected static function phone_ok($phone) {
        $digits = preg_replace('/\D/', '', $phone);
        return (bool) preg_match('/^\+?[\d\s().-]+$/', $phone) && strlen($digits) >= 7 && strlen($digits) <= 15;
    }

    /**
     * Store name, phone and profile on the new customer. Runs before the
     * WooCommerce "new account" email (priority 10) so it can greet by name.
     */
    public static function save_profile($customer_id, $new_customer_data = [], $password_generated = false) {
        if (empty($_POST[self::FORM_MARKER])) {
            return;
        }

        $first = self::posted('gps_first_name');
        $last  = self::posted('gps_last_name');
        $phone = self::posted('gps_phone');
        $role  = self::posted('gps_profession');
        $role  = in_array($role, self::roles(), true) ? $role : '';

        $interests = [];
        if (!empty($_POST['gps_interests']) && is_array($_POST['gps_interests'])) {
            $allowed   = self::interests();
            $interests = array_values(array_intersect($allowed, array_map('sanitize_text_field', wp_unslash($_POST['gps_interests']))));
        }
        $marketing = !empty($_POST['gps_marketing']);

        $customer = new \WC_Customer($customer_id);
        if ($first !== '') {
            $customer->set_billing_first_name($first);
        }
        if ($last !== '') {
            $customer->set_billing_last_name($last);
        }
        if ($phone !== '') {
            $customer->set_billing_phone($phone);
        }
        $customer->save();

        if ($role !== '') {
            update_user_meta($customer_id, 'gps_profession', $role);
        }
        if ($interests) {
            update_user_meta($customer_id, 'gps_interests', $interests);
        }
        update_user_meta($customer_id, 'gps_marketing_consent', $marketing ? current_time('mysql') : '');
        update_user_meta($customer_id, 'gps_terms_accepted', current_time('mysql'));

        if ($marketing) {
            self::subscribe_to_crm($customer_id, $first, $last, $phone, $role, $interests);
        }

        do_action('gps_account_registered', $customer_id, [
            'first_name' => $first,
            'last_name'  => $last,
            'phone'      => $phone,
            'profession' => $role,
            'interests'  => $interests,
            'marketing'  => $marketing,
        ]);
    }

    /**
     * Opted-in customers go to FluentCRM as subscribed contacts (the email is
     * already verified by the code). Role and interests travel as custom
     * fields only when FluentCRM has fields with those slugs.
     */
    protected static function subscribe_to_crm($customer_id, $first, $last, $phone, $role, $interests) {
        if (!function_exists('FluentCrmApi')) {
            return;
        }
        $user = get_userdata($customer_id);
        if (!$user) {
            return;
        }

        $data = apply_filters('gps_account_auth_crm_contact', [
            'email'      => $user->user_email,
            'first_name' => $first,
            'last_name'  => $last,
            'phone'      => $phone,
            'user_id'    => $customer_id,
            'status'     => 'subscribed',
            'custom_values' => [
                'profession' => $role,
                'interests'  => $interests,
            ],
        ], $customer_id);

        try {
            FluentCrmApi('contacts')->createOrUpdate($data);
        } catch (\Throwable $e) {
            error_log('GPS account: FluentCRM sync failed for user ' . $customer_id . ': ' . $e->getMessage());
        }
    }

    /* ====================================================================
     * Password reset
     * ==================================================================== */

    public static function validate_password_reset($errors, $user) {
        if (empty($_POST[self::FORM_MARKER]) || empty($_POST['password_1'])) {
            return;
        }
        if (!self::password_ok(wp_unslash($_POST['password_1']))) {
            $errors->add('gps_password', sprintf(__('Use a password with at least %d characters, including a number.', 'gps-courses'), self::MIN_PASSWORD_LENGTH));
        }
    }

    /* ====================================================================
     * Legacy pages
     * ==================================================================== */

    /**
     * page slug => where it goes now. 'register' opens the Create account tab.
     */
    protected static function legacy_pages() {
        return apply_filters('gps_account_auth_legacy_pages', [
            'login'                     => 'login',
            'access-account'            => 'login',
            'registration-confirmation' => 'login',
            'register'                  => 'register',
            'reset-password'            => 'lost-password',
        ]);
    }

    public static function redirect_legacy_pages() {
        if (is_admin() || wp_doing_ajax() || !is_page() || 'GET' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
            return;
        }

        $page = get_queried_object();
        if (!$page || empty($page->post_name) || (int) $page->ID === (int) wc_get_page_id('myaccount')) {
            return;
        }
        $map = self::legacy_pages();
        if (!isset($map[$page->post_name])) {
            return;
        }

        $target = wc_get_page_permalink('myaccount');
        if ('lost-password' === $map[$page->post_name] && !is_user_logged_in()) {
            $target = wc_lostpassword_url($target) ?: $target;
        } elseif ('register' === $map[$page->post_name] && !is_user_logged_in()) {
            $target = add_query_arg('action', 'register', $target);
        }

        if (!empty($_GET['redirect_to'])) {
            $back = wp_validate_redirect(esc_url_raw(wp_unslash($_GET['redirect_to'])), '');
            if ($back) {
                $target = add_query_arg('redirect_to', rawurlencode($back), $target);
            }
        }

        // 302 while the new screens settle in; switch to 301 with the filter later.
        wp_safe_redirect($target, (int) apply_filters('gps_account_auth_legacy_status', 302));
        exit;
    }
}
