<?php
/**
 * WooCommerce email footer in the GPS frame (grey footer, address, copyright).
 * Replaces woocommerce/templates/emails/email-footer.php.
 */
defined('ABSPATH') || exit;
?>
</div>
</td></tr></table>
<?php
echo GPSC\Emails::frame_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside frame_close()
