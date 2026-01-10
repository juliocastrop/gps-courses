<?php
/**
 * CE Credits Awarded Email for WooCommerce
 */

if (!defined('ABSPATH')) exit;

class GPSC_Credits_Email extends WC_Email {

    public function __construct() {
        $this->id             = 'gps_credits_email';
        $this->title          = __('GPS CE Credits Awarded', 'gps-courses');
        $this->description    = __('Email sent to customers when CE credits are awarded.', 'gps-courses');
        $this->template_html  = 'emails/credits.php';
        $this->template_plain = 'emails/plain/credits.php';
        $this->template_base  = GPSC_PATH . 'templates/';
        $this->placeholders   = [];

        // Triggers
        add_action('gps_credits_awarded_notification', [$this, 'trigger'], 10, 3);

        // Call parent constructor
        parent::__construct();
    }

    /**
     * Get email subject
     */
    public function get_default_subject() {
        return __('CE Credits Awarded: {credits} Credits from {event_name}', 'gps-courses');
    }

    /**
     * Get email heading
     */
    public function get_default_heading() {
        return __('Congratulations! You Earned CE Credits', 'gps-courses');
    }

    /**
     * Trigger email
     */
    public function trigger($user_id, $event_id, $credits) {
        $this->setup_locale();

        // Get user
        $this->user = get_userdata($user_id);

        if (!$this->user) {
            $this->restore_locale();
            return;
        }

        // Get event
        $this->event = get_post($event_id);

        if (!$this->event) {
            $this->restore_locale();
            return;
        }

        $this->credits = $credits;
        $this->total_credits = \GPSC\Credits::user_total($user_id);

        // Set recipient
        $this->recipient = $this->user->user_email;

        // Replace placeholders
        $this->placeholders['{event_name}'] = $this->event->post_title;
        $this->placeholders['{credits}'] = $credits;

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }

        $this->restore_locale();
    }

    /**
     * Get email content HTML
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            [
                'user'           => $this->user,
                'event'          => $this->event,
                'credits'        => $this->credits,
                'total_credits'  => $this->total_credits,
                'email_heading'  => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'  => false,
                'plain_text'     => false,
                'email'          => $this,
            ],
            '',
            $this->template_base
        );
    }

    /**
     * Get email content plain
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            [
                'user'           => $this->user,
                'event'          => $this->event,
                'credits'        => $this->credits,
                'total_credits'  => $this->total_credits,
                'email_heading'  => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'  => false,
                'plain_text'     => true,
                'email'          => $this,
            ],
            '',
            $this->template_base
        );
    }
}
