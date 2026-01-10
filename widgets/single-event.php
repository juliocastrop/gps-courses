<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Single Event Widget
 * Display full event details
 */
class Single_Event_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-single-event';
    }

    public function get_title() {
        return __('Single Event', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-single-post';
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'gps-courses'),
            ]
        );

        $this->add_control(
            'event_id',
            [
                'label' => __('Select Event', 'gps-courses'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_events_list(),
                'default' => '',
                'description' => __('Leave empty to use current event', 'gps-courses'),
            ]
        );

        $this->add_control(
            'show_image',
            [
                'label' => __('Show Featured Image', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Show Title', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_content',
            [
                'label' => __('Show Content', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label' => __('Show Date & Time', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_venue',
            [
                'label' => __('Show Venue', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_credits',
            [
                'label' => __('Show CE Credits', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_speakers',
            [
                'label' => __('Show Speakers', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_agenda',
            [
                'label' => __('Show Agenda/Sessions', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_tickets',
            [
                'label' => __('Show Ticket Selector', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => __('Title Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .gps-single-event-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-single-event-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Get event ID
        $event_id = !empty($settings['event_id']) ? (int) $settings['event_id'] : get_the_ID();

        if (!$event_id || get_post_type($event_id) !== 'gps_event') {
            echo '<p>' . __('Please select a valid event.', 'gps-courses') . '</p>';
            return;
        }

        $event = get_post($event_id);
        $start = get_post_meta($event_id, '_gps_date_start', true);
        $end = get_post_meta($event_id, '_gps_date_end', true);
        $venue = get_post_meta($event_id, '_gps_venue', true);
        $credits = (int) get_post_meta($event_id, '_gps_ce_credits', true);

        ?>
        <div class="gps-single-event">

            <?php if ($settings['show_image'] === 'yes' && has_post_thumbnail($event_id)): ?>
            <div class="gps-single-event-image">
                <?php echo get_the_post_thumbnail($event_id, 'large'); ?>
            </div>
            <?php endif; ?>

            <div class="gps-single-event-content">

                <?php if ($settings['show_title'] === 'yes'): ?>
                <h1 class="gps-single-event-title"><?php echo esc_html($event->post_title); ?></h1>
                <?php endif; ?>

                <div class="gps-single-event-meta">
                    <?php if ($settings['show_date'] === 'yes' && $start): ?>
                    <div class="gps-meta-item gps-meta-date">
                        <i class="far fa-calendar"></i>
                        <strong><?php _e('Date & Time:', 'gps-courses'); ?></strong>
                        <span><?php echo esc_html($this->format_event_date($start, $end)); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($settings['show_venue'] === 'yes' && $venue): ?>
                    <div class="gps-meta-item gps-meta-venue">
                        <i class="far fa-map-marker-alt"></i>
                        <strong><?php _e('Venue:', 'gps-courses'); ?></strong>
                        <span><?php echo esc_html($venue); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($settings['show_credits'] === 'yes' && $credits > 0): ?>
                    <div class="gps-meta-item gps-meta-credits">
                        <i class="far fa-certificate"></i>
                        <strong><?php _e('CE Credits:', 'gps-courses'); ?></strong>
                        <span><?php echo (int) $credits; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($settings['show_content'] === 'yes'): ?>
                <div class="gps-single-event-description">
                    <?php echo apply_filters('the_content', $event->post_content); ?>
                </div>
                <?php endif; ?>

                <?php if ($settings['show_speakers'] === 'yes'): ?>
                <?php $this->render_speakers($event_id); ?>
                <?php endif; ?>

                <?php if ($settings['show_agenda'] === 'yes'): ?>
                <?php $this->render_agenda($event_id); ?>
                <?php endif; ?>

                <?php if ($settings['show_tickets'] === 'yes'): ?>
                <?php $this->render_tickets($event_id); ?>
                <?php endif; ?>

            </div>

        </div>
        <?php
    }

    /**
     * Render speakers section
     */
    private function render_speakers($event_id) {
        $speaker_ids = get_post_meta($event_id, '_gps_speaker_ids', true);

        if (empty($speaker_ids) || !is_array($speaker_ids)) {
            return;
        }

        echo '<div class="gps-event-speakers">';
        echo '<h3>' . __('Speakers', 'gps-courses') . '</h3>';
        echo '<div class="gps-speakers-grid">';

        foreach ($speaker_ids as $speaker_id) {
            $speaker = get_post($speaker_id);
            if (!$speaker) continue;

            $designation = get_post_meta($speaker_id, '_gps_designation', true);
            $company = get_post_meta($speaker_id, '_gps_company', true);

            echo '<div class="gps-speaker-item">';
            if (has_post_thumbnail($speaker_id)) {
                echo get_the_post_thumbnail($speaker_id, 'thumbnail', ['class' => 'gps-speaker-photo']);
            }
            echo '<h4>' . esc_html($speaker->post_title) . '</h4>';
            if ($designation) {
                echo '<p class="gps-speaker-designation">' . esc_html($designation) . '</p>';
            }
            if ($company) {
                echo '<p class="gps-speaker-company">' . esc_html($company) . '</p>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render agenda/sessions section
     */
    private function render_agenda($event_id) {
        $sessions = get_posts([
            'post_type' => 'gps_session',
            'meta_key' => '_gps_event_id',
            'meta_value' => $event_id,
            'orderby' => 'meta_value',
            'meta_key' => '_gps_start',
            'order' => 'ASC',
            'posts_per_page' => -1,
        ]);

        if (empty($sessions)) {
            return;
        }

        echo '<div class="gps-event-agenda">';
        echo '<h3>' . __('Agenda', 'gps-courses') . '</h3>';
        echo '<div class="gps-agenda-list">';

        foreach ($sessions as $session) {
            $start = get_post_meta($session->ID, '_gps_start', true);
            $end = get_post_meta($session->ID, '_gps_end', true);

            echo '<div class="gps-agenda-item">';
            echo '<div class="gps-agenda-time">';
            if ($start) {
                echo date_i18n('g:i A', strtotime($start));
                if ($end) {
                    echo ' - ' . date_i18n('g:i A', strtotime($end));
                }
            }
            echo '</div>';
            echo '<div class="gps-agenda-content">';
            echo '<h4>' . esc_html($session->post_title) . '</h4>';
            if ($session->post_content) {
                echo '<p>' . esc_html(wp_trim_words($session->post_content, 20)) . '</p>';
            }
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render tickets section
     */
    private function render_tickets($event_id) {
        $tickets = \GPSC\Tickets::get_active_tickets($event_id);

        if (empty($tickets)) {
            return;
        }

        echo '<div class="gps-event-tickets">';
        echo '<h3>' . __('Tickets', 'gps-courses') . '</h3>';
        echo '<div class="gps-tickets-list">';

        foreach ($tickets as $ticket) {
            $price = get_post_meta($ticket->ID, '_gps_ticket_price', true);
            $type = get_post_meta($ticket->ID, '_gps_ticket_type', true);
            $product_id = get_post_meta($ticket->ID, '_gps_wc_product_id', true);

            echo '<div class="gps-ticket-item">';
            echo '<div class="gps-ticket-info">';
            echo '<h4>' . esc_html($ticket->post_title) . '</h4>';
            echo '<span class="gps-ticket-type">' . esc_html(ucwords(str_replace('_', ' ', $type))) . '</span>';
            echo '</div>';
            echo '<div class="gps-ticket-price">';
            echo wc_price($price);
            echo '</div>';
            if ($product_id) {
                $product_url = get_permalink($product_id);
                echo '<a href="' . esc_url($product_url) . '" class="gps-ticket-button">' . __('Buy Now', 'gps-courses') . '</a>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Get list of events for select control
     */
    private function get_events_list() {
        $events = get_posts([
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $options = ['' => __('Current Event', 'gps-courses')];

        foreach ($events as $event) {
            $options[$event->ID] = $event->post_title;
        }

        return $options;
    }
}
