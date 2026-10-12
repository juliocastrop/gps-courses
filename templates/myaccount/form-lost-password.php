<?php
/**
 * GPS account screen: ask for a password reset link.
 * Replaces woocommerce/templates/myaccount/form-lost-password.php (same fields and nonce).
 */
defined('ABSPATH') || exit;

$gps = GPSC\Account_Auth::view_data();

do_action('woocommerce_before_lost_password_form');
?>
<div id="gps-auth" class="gps-auth" data-view="lost-password">

    <?php include __DIR__ . '/gps-auth-panel.php'; ?>

    <div class="gps-auth__main">
        <section class="gps-auth__view">
            <h2 class="gps-auth__title"><?php esc_html_e('Reset your password', 'gps-courses'); ?></h2>
            <p class="gps-auth__sub"><?php esc_html_e('Enter your email and we will send you a link to choose a new password.', 'gps-courses'); ?></p>

            <form method="post" class="gps-auth__form" data-gps-form="lost" novalidate>
                <div class="gps-field">
                    <label for="gps_user_login"><?php esc_html_e('Email or username', 'gps-courses'); ?></label>
                    <input type="text" name="user_login" id="gps_user_login" autocomplete="username" required value="<?php echo esc_attr(isset($_POST['user_login']) && is_string($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : ''); ?>">
                    <span class="gps-field__err" data-gps-err aria-live="polite"></span>
                </div>

                <?php do_action('woocommerce_lostpassword_form'); ?>

                <input type="hidden" name="wc_reset_password" value="true">
                <?php wp_nonce_field('lost_password', 'woocommerce-lost-password-nonce'); ?>

                <button type="submit" class="gps-btn" value="<?php esc_attr_e('Reset password', 'gps-courses'); ?>"><?php esc_html_e('Send reset link', 'gps-courses'); ?></button>
                <p class="gps-auth__foot"><a class="gps-link" href="<?php echo esc_url($gps['account_url']); ?>"><?php esc_html_e('Back to log in', 'gps-courses'); ?></a></p>
            </form>
        </section>
    </div>
</div>
<?php do_action('woocommerce_after_lost_password_form'); ?>
