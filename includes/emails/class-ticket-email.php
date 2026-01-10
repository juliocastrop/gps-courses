<?php
/**
 * Ticket Confirmation Email for WooCommerce
 */

if (!defined('ABSPATH')) exit;

class GPSC_Ticket_Email extends WC_Email {

    /**
     * Ticket data
     * @var object
     */
    public $ticket;

    /**
     * Event post object
     * @var WP_Post
     */
    public $event;

    public function __construct() {
        $this->id             = 'gps_ticket_email';
        $this->title          = __('GPS Ticket Confirmation', 'gps-courses');
        $this->description    = __('Ticket confirmation email sent to customers with QR code.', 'gps-courses');
        $this->template_html  = 'emails/ticket.php';
        $this->template_plain = 'emails/plain/ticket.php';
        $this->template_base  = GPSC_PATH . 'templates/';
        $this->placeholders   = [];

        // Triggers
        add_action('gps_ticket_created_notification', [$this, 'trigger'], 10, 2);

        // Call parent constructor
        parent::__construct();
    }

    /**
     * Get email subject
     */
    public function get_default_subject() {
        return __('Your Ticket for {event_name} - Order #{order_number}', 'gps-courses');
    }

    /**
     * Get email heading
     */
    public function get_default_heading() {
        return __('Your Event Booking is Complete', 'gps-courses');
    }

    /**
     * Trigger email
     */
    public function trigger($ticket_id, $order_id) {
        $this->setup_locale();

        global $wpdb;

        // Get ticket
        $this->ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gps_tickets WHERE id = %d",
            $ticket_id
        ));

        if (!$this->ticket) {
            $this->restore_locale();
            return;
        }

        // Get order
        $this->object = wc_get_order($order_id);

        if (!$this->object) {
            $this->restore_locale();
            return;
        }

        // Get event
        $this->event = get_post($this->ticket->event_id);

        // Set recipient
        $this->recipient = $this->ticket->attendee_email ?: $this->object->get_billing_email();

        // Replace placeholders
        $this->placeholders['{event_name}'] = $this->event->post_title;
        $this->placeholders['{order_number}'] = $this->object->get_order_number();

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }

        $this->restore_locale();
    }

    /**
     * Get email content HTML
     */
    public function get_content_html() {
        // Get all necessary data for the template
        $event_start = get_post_meta($this->ticket->event_id, '_gps_start_date', true);
        $event_end = get_post_meta($this->ticket->event_id, '_gps_end_date', true);
        $event_venue = get_post_meta($this->ticket->event_id, '_gps_venue', true);
        $ce_credits = (int) get_post_meta($this->ticket->event_id, '_gps_ce_credits', true);

        // Get ticket type
        $ticket_type = get_post($this->ticket->ticket_type_id);
        $ticket_type_name = $ticket_type ? $ticket_type->post_title : '';
        $ticket_price = get_post_meta($this->ticket->ticket_type_id, '_gps_ticket_price', true);

        // Get QR code
        $qr_code_path = $this->ticket->qr_code_path;
        $qr_code_url = $qr_code_path ? site_url($qr_code_path) : '';

        return wc_get_template_html(
            $this->template_html,
            [
                'ticket'             => $this->ticket,
                'event'              => $this->event,
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                // Additional variables needed by template
                'event_start'        => $event_start,
                'event_end'          => $event_end,
                'event_venue'        => $event_venue,
                'ce_credits'         => $ce_credits,
                'ticket_type_name'   => $ticket_type_name,
                'ticket_price'       => $ticket_price,
                'qr_code_url'        => $qr_code_url,
                'qr_code_path'       => $qr_code_path,
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
                'ticket'         => $this->ticket,
                'event'          => $this->event,
                'order'          => $this->object,
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
