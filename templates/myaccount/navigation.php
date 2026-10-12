<?php
/**
 * My Account menu, grouped (Learning / Purchases / Account) with icons, plus a
 * horizontal tab row for small screens. Replaces woocommerce/templates/myaccount/navigation.php.
 */
defined('ABSPATH') || exit;

use GPSC\Account_Dashboard;

$gps_user   = wp_get_current_user();
$gps_name   = trim($gps_user->first_name . ' ' . $gps_user->last_name) ?: $gps_user->display_name;
$gps_role   = (string) get_user_meta($gps_user->ID, 'gps_profession', true);
$gps_words  = preg_split('/\s+/', trim($gps_name));
$gps_initials = strtoupper(substr($gps_words[0] ?? '', 0, 1) . substr(count($gps_words) > 1 ? end($gps_words) : '', 0, 1));
$gps_groups = Account_Dashboard::grouped_menu();
$gps_items  = wc_get_account_menu_items();

do_action('woocommerce_before_account_navigation');
?>
<nav class="woocommerce-MyAccount-navigation" id="gps-acct-nav" aria-label="<?php esc_attr_e('Account pages', 'woocommerce'); ?>">
    <div class="gps-me">
        <span class="gps-me__avatar" aria-hidden="true"><?php echo esc_html($gps_initials ?: '•'); ?></span>
        <span class="gps-me__who">
            <b><?php echo esc_html($gps_name); ?></b>
            <?php if ($gps_role) : ?><span><?php echo esc_html($gps_role); ?></span><?php endif; ?>
        </span>
    </div>

    <div class="gps-menu">
        <?php foreach ($gps_groups as $gps_group) : ?>
            <p class="gps-menu__group"><?php echo esc_html($gps_group['label']); ?></p>
            <ul>
                <?php foreach ($gps_group['items'] as $endpoint => $label) : ?>
                    <li class="<?php echo esc_attr(wc_get_account_menu_item_classes($endpoint)); ?>">
                        <a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>"<?php echo Account_Dashboard::is_current($endpoint) ? ' aria-current="page"' : ''; ?>>
                            <?php echo Account_Dashboard::icon($endpoint); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                            <span><?php echo esc_html($label); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
        <?php if (isset($gps_items['customer-logout'])) : ?>
            <ul class="gps-menu__out">
                <li class="<?php echo esc_attr(wc_get_account_menu_item_classes('customer-logout')); ?>">
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('customer-logout')); ?>">
                        <?php echo Account_Dashboard::icon('customer-logout'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
                        <span><?php echo esc_html($gps_items['customer-logout']); ?></span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </div>

    <div class="gps-tabs" aria-label="<?php esc_attr_e('Account pages', 'woocommerce'); ?>">
        <?php foreach ($gps_items as $endpoint => $label) : ?>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>"<?php echo Account_Dashboard::is_current($endpoint) ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </div>
</nav>
<?php do_action('woocommerce_after_account_navigation'); ?>
