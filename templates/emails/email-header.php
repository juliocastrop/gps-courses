<?php
/**
 * WooCommerce email header in the GPS frame (logo on the navy band, heading).
 * Replaces woocommerce/templates/emails/email-header.php.
 *
 * The body keeps WooCommerce's ids (#body_content, #body_content_inner…) so
 * its email styles still format order tables, addresses and buttons.
 *
 * @var string $email_heading
 */
defined('ABSPATH') || exit;

echo GPSC\Emails::frame_open((string) $email_heading, 'id="body_content" valign="top"'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside frame_open()
?>
<?php // WooCommerce styles order tables with "#body_content table td td", so the content sits in a table cell. ?>
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td valign="top" style="padding:0;">
<div id="body_content_inner">
