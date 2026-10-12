<?php
/**
 * GPS account screens: the navy side panel shared by login, registration
 * and password reset. Expects $gps from GPSC\Account_Auth::view_data().
 */
defined('ABSPATH') || exit;
?>
<aside class="gps-auth__panel">
    <?php if (!empty($gps['logo'])) : ?>
        <img class="gps-auth__logo" src="<?php echo esc_url($gps['logo']); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="180" height="34" loading="lazy">
    <?php endif; ?>
    <p class="gps-auth__pitch">
        <?php
        /* translators: the part in <em> is highlighted in gold */
        echo wp_kses(__('One account for your <em>courses, tickets and CE credits</em>', 'gps-courses'), ['em' => []]);
        ?>
    </p>
    <ul class="gps-auth__perks">
        <li><span class="gps-auth__ico" aria-hidden="true">QR</span><span><b><?php esc_html_e('Your tickets', 'gps-courses'); ?></b><?php esc_html_e('QR codes ready for check-in on course day', 'gps-courses'); ?></span></li>
        <li><span class="gps-auth__ico" aria-hidden="true">CE</span><span><b><?php esc_html_e('CE credits and certificates', 'gps-courses'); ?></b><?php esc_html_e('Every credit you earn, with a downloadable certificate', 'gps-courses'); ?></span></li>
        <li><span class="gps-auth__ico" aria-hidden="true">10</span><span><b><?php esc_html_e('Monthly seminars', 'gps-courses'); ?></b><?php esc_html_e('Track your 10 sessions and your makeup session', 'gps-courses'); ?></span></li>
    </ul>
    <p class="gps-auth__pace"><?php esc_html_e('Nationally Approved PACE Program Provider for FAGD/MAGD credit.', 'gps-courses'); ?></p>
</aside>
