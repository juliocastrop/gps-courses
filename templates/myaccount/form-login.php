<?php
/**
 * GPS account screen: log in + create account (3 steps).
 *
 * Replaces woocommerce/templates/myaccount/form-login.php. Field names, nonces
 * and actions are WooCommerce's own, so WC_Form_Handler still does the login
 * and the registration; the email code is checked by Registration_Guard.
 */
defined('ABSPATH') || exit;

use GPSC\Account_Auth;
use GPSC\Registration_Guard;

$gps      = Account_Auth::view_data();
$can_reg  = $gps['can_register'];
$view     = $can_reg ? $gps['view'] : 'login';
$login_url    = remove_query_arg('action', $gps['action_url']);
$register_url = add_query_arg('action', 'register', $gps['action_url']);
$need_username = 'no' === get_option('woocommerce_registration_generate_username');
$need_password = 'no' === get_option('woocommerce_registration_generate_password');
$posted_interests = (isset($_POST['gps_interests']) && is_array($_POST['gps_interests'])) ? array_map('sanitize_text_field', wp_unslash($_POST['gps_interests'])) : [];

do_action('woocommerce_before_customer_login_form');
?>
<div id="gps-auth" class="gps-auth" data-view="<?php echo esc_attr($view); ?>">

    <?php include __DIR__ . '/gps-auth-panel.php'; ?>

    <div class="gps-auth__main">

        <?php if ($can_reg) : ?>
            <div class="gps-auth__tabs" role="tablist" aria-label="<?php esc_attr_e('Account', 'gps-courses'); ?>">
                <a href="<?php echo esc_url($login_url); ?>" role="tab" id="gps-tab-login" aria-controls="gps-panel-login" aria-selected="<?php echo 'login' === $view ? 'true' : 'false'; ?>" data-gps-tab="login"><?php esc_html_e('Log in', 'gps-courses'); ?></a>
                <a href="<?php echo esc_url($register_url); ?>" role="tab" id="gps-tab-register" aria-controls="gps-panel-register" aria-selected="<?php echo 'register' === $view ? 'true' : 'false'; ?>" data-gps-tab="register"><?php esc_html_e('Create account', 'gps-courses'); ?></a>
            </div>
        <?php endif; ?>

        <!-- Log in -->
        <section id="gps-panel-login" class="gps-auth__view"<?php echo $can_reg ? ' role="tabpanel" aria-labelledby="gps-tab-login"' : ''; ?><?php echo 'login' === $view ? '' : ' hidden'; ?>>
            <h2 class="gps-auth__title"><?php esc_html_e('Welcome back', 'gps-courses'); ?></h2>
            <p class="gps-auth__sub"><?php esc_html_e('Log in to see your courses, tickets and CE credits.', 'gps-courses'); ?></p>

            <form method="post" action="<?php echo esc_url($login_url); ?>" class="gps-auth__form" data-gps-form="login" novalidate>
                <?php do_action('woocommerce_login_form_start'); ?>

                <div class="gps-field">
                    <label for="gps_login_user"><?php esc_html_e('Email or username', 'gps-courses'); ?></label>
                    <input type="text" name="username" id="gps_login_user" autocomplete="username" required value="<?php echo esc_attr(isset($_POST['username']) && is_string($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : ''); ?>">
                    <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                </div>

                <div class="gps-field">
                    <label for="gps_login_pass"><?php esc_html_e('Password', 'gps-courses'); ?></label>
                    <span class="gps-pw">
                        <input type="password" name="password" id="gps_login_pass" autocomplete="current-password" required>
                        <button type="button" class="gps-pw__toggle" data-gps-toggle="gps_login_pass" aria-controls="gps_login_pass" aria-pressed="false"><?php esc_html_e('Show', 'gps-courses'); ?></button>
                    </span>
                    <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                </div>

                <?php do_action('woocommerce_login_form'); ?>

                <div class="gps-auth__between">
                    <label class="gps-check">
                        <input type="checkbox" name="rememberme" value="forever"<?php checked(!empty($_POST['rememberme'])); ?>>
                        <span><?php esc_html_e('Keep me logged in', 'gps-courses'); ?></span>
                    </label>
                    <a class="gps-link" href="<?php echo esc_url(wc_lostpassword_url()); ?>"><?php esc_html_e('Forgot your password?', 'gps-courses'); ?></a>
                </div>

                <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
                <?php // Always set: without it WooCommerce returns to the referer, which after an Elementor logout carries ?elementor_wc_logout. ?>
                <input type="hidden" name="redirect" value="<?php echo esc_url($gps['redirect_to'] ?: $gps['account_url']); ?>">

                <button type="submit" class="gps-btn" name="login" value="<?php esc_attr_e('Log in', 'gps-courses'); ?>"><?php esc_html_e('Log in', 'gps-courses'); ?></button>

                <?php do_action('woocommerce_login_form_end'); ?>

                <?php if ($can_reg) : ?>
                    <p class="gps-auth__foot"><?php esc_html_e('New to GPS?', 'gps-courses'); ?> <a class="gps-link" href="<?php echo esc_url($register_url); ?>" data-gps-tab="register"><?php esc_html_e('Create an account', 'gps-courses'); ?></a></p>
                <?php endif; ?>
            </form>
        </section>

        <?php if ($can_reg) : ?>
        <!-- Create account: 1 Account, 2 Verify email, 3 Your profile -->
        <section id="gps-panel-register" class="gps-auth__view" role="tabpanel" aria-labelledby="gps-tab-register"<?php echo 'register' === $view ? '' : ' hidden'; ?>>
            <ol class="gps-steps" aria-label="<?php esc_attr_e('Progress', 'gps-courses'); ?>">
                <li class="is-current" aria-current="step" data-gps-step-label="1"><?php esc_html_e('Account', 'gps-courses'); ?></li>
                <li data-gps-step-label="2"><?php esc_html_e('Verify email', 'gps-courses'); ?></li>
                <li data-gps-step-label="3"><?php esc_html_e('Your profile', 'gps-courses'); ?></li>
            </ol>

            <form method="post" action="<?php echo esc_url($register_url); ?>" class="gps-auth__form gps-reg" data-gps-form="register" novalidate <?php do_action('woocommerce_register_form_tag'); ?>>
                <?php do_action('woocommerce_register_form_start'); ?>
                <input type="hidden" name="<?php echo esc_attr(Account_Auth::FORM_MARKER); ?>" value="1">

                <!-- Step 1 -->
                <div class="gps-step is-active" data-gps-step="1">
                    <h2 class="gps-auth__title"><?php esc_html_e('Create your account', 'gps-courses'); ?></h2>
                    <p class="gps-auth__sub"><?php esc_html_e('It takes a minute. We will email you a code to confirm it\'s you.', 'gps-courses'); ?></p>

                    <div class="gps-row2">
                        <div class="gps-field">
                            <label for="gps_first_name"><?php esc_html_e('First name', 'gps-courses'); ?></label>
                            <input type="text" name="gps_first_name" id="gps_first_name" autocomplete="given-name" required value="<?php echo esc_attr(Account_Auth::posted('gps_first_name')); ?>" data-gps-msg="firstName">
                            <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                        </div>
                        <div class="gps-field">
                            <label for="gps_last_name"><?php esc_html_e('Last name', 'gps-courses'); ?></label>
                            <input type="text" name="gps_last_name" id="gps_last_name" autocomplete="family-name" required value="<?php echo esc_attr(Account_Auth::posted('gps_last_name')); ?>" data-gps-msg="lastName">
                            <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                        </div>
                    </div>

                    <?php if ($need_username) : ?>
                        <div class="gps-field">
                            <label for="gps_reg_username"><?php esc_html_e('Username', 'gps-courses'); ?></label>
                            <input type="text" name="username" id="gps_reg_username" autocomplete="username" required value="<?php echo esc_attr(Account_Auth::posted('username')); ?>">
                            <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                        </div>
                    <?php endif; ?>

                    <div class="gps-field">
                        <label for="gps_reg_email"><?php esc_html_e('Email', 'gps-courses'); ?></label>
                        <input type="email" name="email" id="gps_reg_email" autocomplete="email" required value="<?php echo esc_attr(isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : ''); ?>" data-gps-msg="email">
                        <span class="gps-field__hint"><?php esc_html_e('Your tickets, receipts and certificates go here.', 'gps-courses'); ?></span>
                        <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                    </div>

                    <?php if ($need_password) : ?>
                        <div class="gps-field">
                            <label for="gps_reg_pass"><?php esc_html_e('Password', 'gps-courses'); ?></label>
                            <span class="gps-pw">
                                <input type="password" name="password" id="gps_reg_pass" autocomplete="new-password" required data-gps-meter="gps_reg_meter" data-gps-msg="password">
                                <button type="button" class="gps-pw__toggle" data-gps-toggle="gps_reg_pass" aria-controls="gps_reg_pass" aria-pressed="false"><?php esc_html_e('Show', 'gps-courses'); ?></button>
                            </span>
                            <span class="gps-meter" aria-hidden="true"><span id="gps_reg_meter"></span></span>
                            <span class="gps-field__hint" data-gps-meter-text="gps_reg_meter"><?php printf(esc_html__('Use %d+ characters with a number.', 'gps-courses'), (int) Account_Auth::MIN_PASSWORD_LENGTH); ?></span>
                            <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                        </div>
                    <?php endif; ?>

                    <div class="gps-field">
                        <label class="gps-check">
                            <input type="checkbox" name="gps_terms" value="1" required data-gps-msg="terms"<?php checked(!empty($_POST['gps_terms'])); ?>>
                            <span>
                                <?php
                                printf(
                                    /* translators: 1: terms link, 2: privacy link */
                                    esc_html__('I agree to the %1$s and %2$s.', 'gps-courses'),
                                    '<a href="' . esc_url($gps['terms_url']) . '" target="_blank" rel="noopener">' . esc_html__('Terms and Conditions', 'gps-courses') . '</a>',
                                    '<a href="' . esc_url($gps['privacy_url']) . '" target="_blank" rel="noopener">' . esc_html__('Privacy Policy', 'gps-courses') . '</a>'
                                );
                                ?>
                            </span>
                        </label>
                        <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                    </div>

                    <div class="gps-alert" data-gps-alert role="alert" hidden></div>

                    <button type="button" class="gps-btn" data-gps-next><?php esc_html_e('Continue', 'gps-courses'); ?></button>
                    <p class="gps-auth__foot"><?php esc_html_e('Already have an account?', 'gps-courses'); ?> <a class="gps-link" href="<?php echo esc_url($login_url); ?>" data-gps-tab="login"><?php esc_html_e('Log in', 'gps-courses'); ?></a></p>
                </div>

                <!-- Step 2 -->
                <div class="gps-step" data-gps-step="2">
                    <h2 class="gps-auth__title"><?php esc_html_e('Check your email', 'gps-courses'); ?></h2>
                    <p class="gps-auth__sub">
                        <?php
                        printf(
                            /* translators: %s: email address */
                            esc_html__('We sent a 6-digit code to %s. It expires in 10 minutes.', 'gps-courses'),
                            '<b data-gps-sent-to></b>'
                        );
                        ?>
                    </p>

                    <div class="gps-field">
                        <span class="gps-field__label" id="gps_code_label"><?php esc_html_e('Verification code', 'gps-courses'); ?></span>
                        <div class="gps-code" role="group" aria-labelledby="gps_code_label" data-gps-code>
                            <?php for ($i = 1; $i <= 6; $i++) : ?>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" <?php echo 1 === $i ? 'autocomplete="one-time-code"' : 'autocomplete="off"'; ?> aria-label="<?php echo esc_attr(sprintf(__('Digit %d', 'gps-courses'), $i)); ?>">
                            <?php endfor; ?>
                        </div>
                        <?php // Kept after a failed POST: the code stays valid until the account exists. ?>
                        <input type="hidden" name="gps_reg_code" id="gps_reg_code" value="<?php echo esc_attr(preg_replace('/\D/', '', Account_Auth::posted('gps_reg_code'))); ?>" data-email="<?php echo esc_attr(strtolower(isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '')); ?>">
                        <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                    </div>

                    <div class="gps-alert" data-gps-alert role="alert" hidden></div>

                    <button type="button" class="gps-btn" data-gps-verify><?php esc_html_e('Verify email', 'gps-courses'); ?></button>
                    <div class="gps-auth__between">
                        <span class="gps-field__hint"><?php esc_html_e('Didn\'t get it? Check your spam folder.', 'gps-courses'); ?></span>
                        <button type="button" class="gps-link" data-gps-resend disabled><?php esc_html_e('Resend code', 'gps-courses'); ?></button>
                    </div>
                    <p class="gps-auth__foot"><button type="button" class="gps-link" data-gps-back><?php esc_html_e('Use a different email', 'gps-courses'); ?></button></p>
                </div>

                <!-- Step 3 -->
                <div class="gps-step" data-gps-step="3">
                    <h2 class="gps-auth__title"><?php esc_html_e('Tell us about your practice', 'gps-courses'); ?></h2>
                    <p class="gps-auth__sub"><?php esc_html_e('Your email is verified. This helps us recommend the right courses. You can skip it.', 'gps-courses'); ?></p>

                    <div class="gps-field">
                        <label for="gps_profession"><?php esc_html_e('Your role', 'gps-courses'); ?></label>
                        <select name="gps_profession" id="gps_profession">
                            <option value=""><?php esc_html_e('Select your role', 'gps-courses'); ?></option>
                            <?php foreach ($gps['roles'] as $role) : ?>
                                <option value="<?php echo esc_attr($role); ?>"<?php selected(Account_Auth::posted('gps_profession'), $role); ?>><?php echo esc_html($role); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <fieldset class="gps-field gps-field--group">
                        <legend class="gps-field__label"><?php esc_html_e('Topics you\'re interested in', 'gps-courses'); ?> <span class="gps-opt"><?php esc_html_e('(pick any)', 'gps-courses'); ?></span></legend>
                        <div class="gps-chips">
                            <?php foreach ($gps['interests'] as $topic) : ?>
                                <label class="gps-chip">
                                    <input type="checkbox" name="gps_interests[]" value="<?php echo esc_attr($topic); ?>"<?php checked(in_array($topic, $posted_interests, true)); ?>>
                                    <span><?php echo esc_html($topic); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="gps-field">
                        <label for="gps_phone"><?php esc_html_e('Mobile phone', 'gps-courses'); ?> <span class="gps-opt"><?php esc_html_e('(optional, for course-day updates)', 'gps-courses'); ?></span></label>
                        <input type="tel" name="gps_phone" id="gps_phone" autocomplete="tel" placeholder="(404) 555-0123" value="<?php echo esc_attr(Account_Auth::posted('gps_phone')); ?>" data-gps-msg="phone">
                        <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                    </div>

                    <label class="gps-check">
                        <input type="checkbox" name="gps_marketing" value="1"<?php checked(!empty($_POST['gps_marketing'])); ?>>
                        <span><?php esc_html_e('Email me about new courses and seminars. Unsubscribe anytime.', 'gps-courses'); ?></span>
                    </label>

                    <?php
                    // Honeypot, time trap and other plugins' fields. Our own terms
                    // checkbox and code input replace WooCommerce's privacy text and
                    // the default code field.
                    Registration_Guard::$custom_code_field = true;
                    $had_privacy_text = remove_action('woocommerce_register_form', 'wc_registration_privacy_policy_text', 20);
                    do_action('woocommerce_register_form');
                    if ($had_privacy_text) {
                        add_action('woocommerce_register_form', 'wc_registration_privacy_policy_text', 20);
                    }
                    Registration_Guard::$custom_code_field = false;

                    wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce');
                    ?>
                    <input type="hidden" name="redirect" value="<?php echo esc_url($gps['redirect_to'] ?: $gps['account_url']); ?>">

                    <div class="gps-alert" data-gps-alert role="alert" hidden></div>

                    <button type="submit" class="gps-btn" name="register" value="<?php esc_attr_e('Register', 'gps-courses'); ?>" data-gps-submit><?php esc_html_e('Create account', 'gps-courses'); ?></button>
                    <p class="gps-auth__foot"><button type="submit" class="gps-link" name="register" value="<?php esc_attr_e('Register', 'gps-courses'); ?>" data-gps-skip formnovalidate><?php esc_html_e('Skip for now', 'gps-courses'); ?></button></p>
                </div>

                <?php do_action('woocommerce_register_form_end'); ?>
            </form>
        </section>
        <?php endif; ?>

    </div>
</div>
<?php do_action('woocommerce_after_customer_login_form'); ?>
