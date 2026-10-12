<?php
/**
 * GPS account screen: reset link sent.
 * Replaces woocommerce/templates/myaccount/lost-password-confirmation.php.
 */
defined('ABSPATH') || exit;

$gps = GPSC\Account_Auth::view_data();
?>
<div id="gps-auth" class="gps-auth" data-view="lost-password-sent">

    <?php include __DIR__ . '/gps-auth-panel.php'; ?>

    <div class="gps-auth__main">
        <section class="gps-auth__view">
            <?php do_action('woocommerce_before_lost_password_confirmation_message'); ?>

            <h2 class="gps-auth__title"><?php esc_html_e('Check your inbox', 'gps-courses'); ?></h2>
            <div class="gps-alert gps-alert--ok" role="status">
                <span>
                    <?php echo esc_html(apply_filters('woocommerce_lost_password_confirmation_message', __('If an account exists for that email, we sent a link to choose a new password. It can take a few minutes to arrive, so check your spam folder too, and wait at least 10 minutes before asking for another one.', 'gps-courses'))); ?>
                </span>
            </div>
            <p class="gps-auth__foot"><a class="gps-link" href="<?php echo esc_url($gps['account_url']); ?>"><?php esc_html_e('Back to log in', 'gps-courses'); ?></a></p>

            <?php do_action('woocommerce_after_lost_password_confirmation_message'); ?>
        </section>
    </div>
</div>
