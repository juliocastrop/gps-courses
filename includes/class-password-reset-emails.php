<?php
namespace GPSC;

if (!defined('ABSPATH')) exit;

/**
 * Password Reset Email Handler
 *
 * Handles custom password reset email templates for GPS Dental Training
 */
class Password_Reset_Emails {

    /**
     * Initialize the class
     */
    public static function init() {
        // Hook into FluentForm submission for password reset
        add_action('fluentform_before_insert_submission', [__CLASS__, 'handle_password_reset'], 10, 3);
    }

    /**
     * Handle password reset from FluentForm
     */
    public static function handle_password_reset($insertData, $data, $form) {
        // Check if this is the password reset form (form ID 5)
        if ($form->id != 5) {
            return;
        }

        $redirectUrl = home_url();

        // Check if user is already logged in
        if (get_current_user_id()) {
            wp_send_json_success([
                'result' => [
                    'redirectUrl' => $redirectUrl,
                    'message' => __('You are already logged in. Redirecting now...', 'gps-courses')
                ]
            ]);
        }

        // Get email from form submission
        $email = \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'email');

        if (!$email) {
            wp_send_json_error([
                'errors' => [__('Please provide email', 'gps-courses')]
            ], 423);
        }

        // Get user by email
        $user = get_user_by('email', $email);

        if ($user && !is_wp_error($user)) {
            // Send the password reset email
            $sent = self::send_reset_email($user);

            if ($sent) {
                wp_send_json_success([
                    'result' => [
                        'message' => __('Your password reset link has been sent to your email.', 'gps-courses')
                    ]
                ]);
            } else {
                wp_send_json_error([
                    'errors' => [__('Could not send email. Please contact support.', 'gps-courses')]
                ], 423);
            }
        } else {
            // Don't reveal if email exists or not (security)
            wp_send_json_success([
                'result' => [
                    'message' => __('If that email address is in our system, you will receive a password reset link.', 'gps-courses')
                ]
            ]);
        }
    }

    /**
     * Send password reset email with custom template
     *
     * @param WP_User $user User object
     * @return bool True if email sent successfully
     */
    public static function send_reset_email($user) {
        // Generate reset key manually (bypass WordPress restrictions)
        $key = wp_generate_password(20, false);
        $hashed = time() . ':' . wp_hash_password($key);

        // Store the key in user meta
        update_user_meta($user->ID, 'password_reset_key', $hashed);

        // Build reset URL
        $reset_url = add_query_arg([
            'action' => 'rp',
            'key' => $key,
            'login' => rawurlencode($user->user_login)
        ], wp_login_url());

        // Get user's display name
        $user_name = !empty($user->display_name) ? $user->display_name : $user->user_login;

        // Build email content
        $subject = sprintf(__('Password Reset Request - %s', 'gps-courses'), get_bloginfo('name'));
        $message = self::get_email_template($user_name, $reset_url);

        // Set email headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <noreply@gpsdentaltraining.com>'
        ];

        // Send email
        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    /**
     * Get HTML email template
     *
     * @param string $user_name User's display name
     * @param string $reset_url Password reset URL
     * @return string HTML email content
     */
    private static function get_email_template($user_name, $reset_url) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Password Reset', 'gps-courses'); ?></title>
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f5f5;">

            <!-- Email Container -->
            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f5f5;" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="padding: 40px 20px;">

                        <!-- Email Card -->
                        <table role="presentation" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 24px rgba(12, 32, 68, 0.08);" cellpadding="0" cellspacing="0">

                            <!-- Header with GPS Branding -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #0B52AC 0%, #173D84 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                                    <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">
                                        <?php echo esc_html(get_bloginfo('name')); ?>
                                    </h1>
                                    <p style="margin: 10px 0 0 0; color: #DDC89D; font-size: 14px; font-weight: 500;">
                                        <?php _e('Dental Training Excellence', 'gps-courses'); ?>
                                    </p>
                                </td>
                            </tr>

                            <!-- Body Content -->
                            <tr>
                                <td style="padding: 40px 30px;">

                                    <!-- Icon -->
                                    <table role="presentation" style="width: 100%; margin-bottom: 30px;" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center">
                                                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, rgba(11, 82, 172, 0.1) 0%, rgba(11, 82, 172, 0.05) 100%); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M12 2C9.243 2 7 4.243 7 7v3H6c-1.103 0-2 .897-2 2v8c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-8c0-1.103-.897-2-2-2h-1V7c0-2.757-2.243-5-5-5zm6 10 .002 8H6v-8h12zm-9-2V7c0-1.654 1.346-3 3-3s3 1.346 3 3v3H9z" fill="#0B52AC"/>
                                                        <circle cx="12" cy="16" r="1.5" fill="#DDC89D"/>
                                                    </svg>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Greeting -->
                                    <h2 style="margin: 0 0 20px 0; color: #0C2044; font-size: 24px; font-weight: 600; line-height: 1.3;">
                                        <?php printf(__('Hi %s,', 'gps-courses'), esc_html($user_name)); ?>
                                    </h2>

                                    <!-- Message -->
                                    <p style="margin: 0 0 20px 0; color: #4a5568; font-size: 16px; line-height: 1.6;">
                                        <?php _e('We received a request to reset your password. Click the button below to choose a new password:', 'gps-courses'); ?>
                                    </p>

                                    <!-- Reset Button -->
                                    <table role="presentation" style="width: 100%; margin: 30px 0;" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center">
                                                <a href="<?php echo esc_url($reset_url); ?>"
                                                   style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #0B52AC 0%, #173D84 100%); color: #DDC89D; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 12px rgba(11, 82, 172, 0.3); transition: all 0.3s ease;">
                                                    <?php _e('Reset Password', 'gps-courses'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Alternative Link -->
                                    <p style="margin: 30px 0 0 0; padding: 20px; background-color: #f8f9fa; border-left: 4px solid #DDC89D; border-radius: 4px; color: #4a5568; font-size: 13px; line-height: 1.6;">
                                        <strong style="color: #0C2044;"><?php _e('Or copy this link:', 'gps-courses'); ?></strong><br>
                                        <a href="<?php echo esc_url($reset_url); ?>" style="color: #0B52AC; word-break: break-all;">
                                            <?php echo esc_url($reset_url); ?>
                                        </a>
                                    </p>

                                    <!-- Security Notice -->
                                    <div style="margin-top: 30px; padding: 16px; background-color: #fff9e6; border: 1px solid #ffeaa7; border-radius: 8px;">
                                        <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.5;">
                                            <strong>⚠️ <?php _e('Security Notice:', 'gps-courses'); ?></strong><br>
                                            <?php _e('If you didn\'t request this password reset, please ignore this email. Your password will remain unchanged.', 'gps-courses'); ?>
                                        </p>
                                    </div>

                                    <!-- Expiration Notice -->
                                    <p style="margin: 20px 0 0 0; color: #718096; font-size: 13px; line-height: 1.5; text-align: center;">
                                        <?php _e('This password reset link will expire in 24 hours.', 'gps-courses'); ?>
                                    </p>

                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #e2e8f0;">

                                    <!-- Support Info -->
                                    <p style="margin: 0 0 15px 0; color: #718096; font-size: 14px; line-height: 1.5;">
                                        <?php _e('Need help? Contact our support team:', 'gps-courses'); ?><br>
                                        <a href="mailto:info@gpsdentaltraining.com" style="color: #0B52AC; text-decoration: none; font-weight: 500;">
                                            info@gpsdentaltraining.com
                                        </a>
                                    </p>

                                    <!-- Divider -->
                                    <div style="margin: 20px 0; height: 1px; background-color: #e2e8f0;"></div>

                                    <!-- Copyright -->
                                    <p style="margin: 0; color: #a0aec0; font-size: 12px;">
                                        &copy; <?php echo date('Y'); ?> <?php echo esc_html(get_bloginfo('name')); ?>. <?php _e('All rights reserved.', 'gps-courses'); ?>
                                    </p>

                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>
            </table>

        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
