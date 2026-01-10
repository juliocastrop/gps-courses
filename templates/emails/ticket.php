<?php
/**
 * Ticket Email Template
 *
 * @var object $ticket Ticket database object
 * @var WP_Post $event Event post object
 * @var WP_User $user User object
 * @var WC_Order $order WooCommerce order object
 * @var string $ticket_type_name Ticket type name
 * @var float $ticket_price Ticket price
 * @var string $event_start Event start date/time
 * @var string $event_end Event end date/time
 * @var string $event_venue Event venue
 * @var int $ce_credits CE credits
 * @var string $qr_code_url QR code image URL
 * @var string $qr_code_path QR code file path
 */

if (!defined('ABSPATH')) exit;

// Get email settings
use GPSC\Email_Settings;
$settings = [
    'logo' => Email_Settings::get('logo'),
    'header_text' => Email_Settings::get('header_text'),
    'header_bg_color' => Email_Settings::get('header_bg_color'),
    'header_text_color' => Email_Settings::get('header_text_color'),
    'ticket_label' => Email_Settings::get('ticket_label'),
    'ticket_bg_color' => Email_Settings::get('ticket_bg_color'),
    'ticket_code_color' => Email_Settings::get('ticket_code_color'),
    'event_heading' => Email_Settings::get('event_heading'),
    'event_heading_color' => Email_Settings::get('event_heading_color'),
    'qr_heading' => Email_Settings::get('qr_heading'),
    'qr_bg_color' => Email_Settings::get('qr_bg_color'),
    'show_qr_code' => Email_Settings::get('show_qr_code'),
    'ce_badge_bg_color' => Email_Settings::get('ce_badge_bg_color'),
    'ce_badge_text_color' => Email_Settings::get('ce_badge_text_color'),
    'footer_text' => Email_Settings::get('footer_text'),
    'footer_bg_color' => Email_Settings::get('footer_bg_color'),
    'footer_text_color' => Email_Settings::get('footer_text_color'),
    'button_text' => Email_Settings::get('button_text'),
    'button_bg_color' => Email_Settings::get('button_bg_color'),
    'button_text_color' => Email_Settings::get('button_text_color'),
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($settings['header_text']); ?></title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <?php if (!empty($settings['logo'])): ?>
                    <!-- Logo -->
                    <tr>
                        <td style="text-align: center; padding: 30px 40px 20px; background: white;">
                            <img src="<?php echo esc_url($settings['logo']); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" style="max-width: 200px; height: auto;">
                        </td>
                    </tr>
                    <?php endif; ?>

                    <!-- Hero Section -->
                    <tr>
                        <td style="background: <?php echo esc_attr($settings['header_bg_color']); ?>; color: <?php echo esc_attr($settings['header_text_color']); ?>; padding: 40px 30px; text-align: center; <?php echo empty($settings['logo']) ? 'border-radius: 8px 8px 0 0;' : ''; ?>">
                            <h1 style="margin: 0 0 10px 0; font-size: 28px; font-weight: bold; color: <?php echo esc_attr($settings['header_text_color']); ?>;">
                                🎫 <?php echo esc_html($settings['header_text']); ?>
                            </h1>
                            <p style="margin: 0; font-size: 16px; opacity: 0.9; color: <?php echo esc_attr($settings['header_text_color']); ?>;">
                                <?php echo esc_html($event->post_title); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Ticket Code -->
                    <tr>
                        <td style="background: <?php echo esc_attr($settings['ticket_bg_color']); ?>; padding: 25px; text-align: center; border-bottom: 3px dashed #dee2e6;">
                            <p style="margin: 0 0 10px 0; font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 1px;">
                                <?php echo esc_html($settings['ticket_label']); ?>
                            </p>
                            <div style="font-size: 24px; font-weight: bold; color: <?php echo esc_attr($settings['ticket_code_color']); ?>; font-family: 'Courier New', monospace; letter-spacing: 2px;">
                                <?php echo esc_html($ticket->ticket_code); ?>
                            </div>
                        </td>
                    </tr>

                    <!-- Event Details -->
                    <tr>
                        <td style="background: white; padding: 30px;">
                            <h2 style="margin: 0 0 20px 0; font-size: 20px; color: <?php echo esc_attr($settings['event_heading_color']); ?>; border-bottom: 2px solid <?php echo esc_attr($settings['event_heading_color']); ?>; padding-bottom: 10px;">
                                📅 <?php echo esc_html($settings['event_heading']); ?>
                            </h2>

                            <table style="width: 100%; border-collapse: collapse;">
                                <?php if ($event_start): ?>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; color: #6c757d; width: 30%;">
                                        <strong>Start:</strong>
                                    </td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; color: #333;">
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event_start))); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <?php if ($event_end): ?>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; color: #6c757d;">
                                        <strong>End:</strong>
                                    </td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; color: #333;">
                                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event_end))); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <?php if ($event_venue): ?>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; color: #6c757d;">
                                        <strong>Location:</strong>
                                    </td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; color: #333;">
                                        <?php echo esc_html($event_venue); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; color: #6c757d;">
                                        <strong>Ticket Type:</strong>
                                    </td>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; color: #333;">
                                        <?php echo esc_html($ticket_type_name); ?>
                                    </td>
                                </tr>

                                <?php if ($ce_credits > 0): ?>
                                <tr>
                                    <td style="padding: 12px 0; color: #6c757d;">
                                        <strong>CE Credits:</strong>
                                    </td>
                                    <td style="padding: 12px 0; color: #333;">
                                        <span style="background: <?php echo esc_attr($settings['ce_badge_bg_color']); ?>; color: <?php echo esc_attr($settings['ce_badge_text_color']); ?>; padding: 4px 12px; border-radius: 12px; font-weight: bold; display: inline-block;">
                                            <?php echo (int) $ce_credits; ?> Credits
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>

                    <!-- QR Code -->
                    <?php if (!empty($qr_code_url) && $settings['show_qr_code']): ?>
                    <tr>
                        <td style="background: <?php echo esc_attr($settings['qr_bg_color']); ?>; padding: 30px; text-align: center;">
                            <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #333;">
                                📱 <?php echo esc_html($settings['qr_heading']); ?>
                            </h3>
                            <p style="margin: 0 0 20px 0; color: #6c757d; font-size: 14px;">
                                Show this QR code at check-in
                            </p>
                            <div style="background: white; display: inline-block; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                <img src="<?php echo esc_url($qr_code_url); ?>"
                                     alt="Ticket QR Code"
                                     style="display: block; width: 250px; height: 250px; border: none;" />
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <!-- Order Information -->
                    <?php if (!empty($order) && is_object($order)): ?>
                    <tr>
                        <td style="background: white; padding: 30px; border-top: 1px solid #dee2e6;">
                            <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #333;">
                                📋 Order Information
                            </h3>
                            <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                <strong>Order #:</strong> <?php echo esc_html($order->get_order_number()); ?>
                            </p>
                            <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                <strong>Purchase Date:</strong> <?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format'))); ?>
                            </p>
                            <?php if ($ticket_price): ?>
                            <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                <strong>Amount Paid:</strong> <?php echo wc_price($ticket_price); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <!-- Call to Action -->
                    <tr>
                        <td style="background: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 8px 8px;">
                            <p style="margin: 0 0 20px 0; color: #333; font-size: 16px;">
                                <strong>Ready for the event?</strong>
                            </p>
                            <a href="<?php echo esc_url(home_url('/my-account/gps-tickets/')); ?>"
                               style="display: inline-block; background: <?php echo esc_attr($settings['button_bg_color']); ?>; color: <?php echo esc_attr($settings['button_text_color']); ?>; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                                <?php echo esc_html($settings['button_text']); ?>
                            </a>

                            <p style="margin: 20px 0 0 0; color: #6c757d; font-size: 12px;">
                                You can also download your ticket PDF from your account dashboard
                            </p>
                        </td>
                    </tr>

                </table>

                <!-- Footer (outside main table) -->
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="margin-top: 30px;">
                    <tr>
                        <td style="background: <?php echo esc_attr($settings['footer_bg_color']); ?>; color: <?php echo esc_attr($settings['footer_text_color']); ?>; padding: 25px; text-align: center; border-radius: 8px;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: <?php echo esc_attr($settings['footer_text_color']); ?>;">
                                <?php echo nl2br(esc_html($settings['footer_text'])); ?>
                            </p>
                            <p style="margin: 10px 0 0 0; font-size: 14px; color: <?php echo esc_attr($settings['footer_text_color']); ?>;">
                                Questions? Contact us at <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>" style="color: #4dabf7; text-decoration: underline;"><?php echo esc_html(get_option('admin_email')); ?></a>
                            </p>
                            <p style="margin: 10px 0 0 0; font-size: 12px; opacity: 0.7; color: <?php echo esc_attr($settings['footer_text_color']); ?>;">
                                © <?php echo date('Y'); ?> <?php echo esc_html(get_bloginfo('name')); ?>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
