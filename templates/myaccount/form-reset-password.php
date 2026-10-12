<?php
/**
 * GPS account screen: choose a new password (link from the reset email).
 * Replaces woocommerce/templates/myaccount/form-reset-password.php (same fields and nonce).
 *
 * @var array $args 'key' and 'login' from WooCommerce.
 */
defined('ABSPATH') || exit;

use GPSC\Account_Auth;

$gps = Account_Auth::view_data();

do_action('woocommerce_before_reset_password_form');
?>
<div id="gps-auth" class="gps-auth" data-view="reset-password">

    <?php include __DIR__ . '/gps-auth-panel.php'; ?>

    <div class="gps-auth__main">
        <section class="gps-auth__view">
            <h2 class="gps-auth__title"><?php esc_html_e('Choose a new password', 'gps-courses'); ?></h2>
            <p class="gps-auth__sub"><?php printf(esc_html__('Use %d+ characters with a number. You will log in with it from now on.', 'gps-courses'), (int) Account_Auth::MIN_PASSWORD_LENGTH); ?></p>

            <form method="post" class="gps-auth__form" data-gps-form="reset" novalidate>
                <input type="hidden" name="<?php echo esc_attr(Account_Auth::FORM_MARKER); ?>" value="1">

                <div class="gps-field">
                    <label for="gps_password_1"><?php esc_html_e('New password', 'gps-courses'); ?></label>
                    <span class="gps-pw">
                        <input type="password" name="password_1" id="gps_password_1" autocomplete="new-password" required data-gps-meter="gps_reset_meter" data-gps-msg="password">
                        <button type="button" class="gps-pw__toggle" data-gps-toggle="gps_password_1" aria-controls="gps_password_1" aria-pressed="false"><?php esc_html_e('Show', 'gps-courses'); ?></button>
                    </span>
                    <span class="gps-meter" aria-hidden="true"><span id="gps_reset_meter"></span></span>
                    <span class="gps-field__hint" data-gps-meter-text="gps_reset_meter"><?php printf(esc_html__('Use %d+ characters with a number.', 'gps-courses'), (int) Account_Auth::MIN_PASSWORD_LENGTH); ?></span>
                    <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                </div>

                <div class="gps-field">
                    <label for="gps_password_2"><?php esc_html_e('Re-enter new password', 'gps-courses'); ?></label>
                    <input type="password" name="password_2" id="gps_password_2" autocomplete="new-password" required data-gps-match="gps_password_1">
                    <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                </div>

                <input type="hidden" name="reset_key" value="<?php echo esc_attr($args['key']); ?>">
                <input type="hidden" name="reset_login" value="<?php echo esc_attr($args['login']); ?>">

                <?php do_action('woocommerce_resetpassword_form'); ?>

                <input type="hidden" name="wc_reset_password" value="true">
                <?php wp_nonce_field('reset_password', 'woocommerce-reset-password-nonce'); ?>

                <button type="submit" class="gps-btn" value="<?php esc_attr_e('Save', 'gps-courses'); ?>"><?php esc_html_e('Save new password', 'gps-courses'); ?></button>
            </form>
        </section>
    </div>
</div>
<?php do_action('woocommerce_after_reset_password_form'); ?>
