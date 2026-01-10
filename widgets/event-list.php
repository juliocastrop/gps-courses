<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Event List Widget
 */
class Event_List_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-event-list';
    }

    public function get_title() {
        return __('Event List', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-posts-ticker';
    }

    protected function register_controls() {
        // Query Controls
        $this->register_query_controls();

        // Layout Section
        $this->start_controls_section(
            'section_layout',
            [
                'label' => __('Layout', 'gps-courses'),
            ]
        );

        $this->add_control(
            'layout_style',
            [
                'label' => __('Layout Style', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'default',
                'options' => [
                    'default' => __('Default', 'gps-courses'),
                    'compact' => __('Compact', 'gps-courses'),
                    'detailed' => __('Detailed', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'show_image',
            [
                'label' => __('Show Image', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'image_position',
            [
                'label' => __('Image Position', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'left',
                'options' => [
                    'left' => __('Left', 'gps-courses'),
                    'top' => __('Top', 'gps-courses'),
                ],
                'condition' => [
                    'show_image' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_excerpt',
            [
                'label' => __('Show Excerpt', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'excerpt_length',
            [
                'label' => __('Excerpt Length', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'default' => 30,
                'condition' => [
                    'show_excerpt' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label' => __('Show Date', 'gps-courses'),
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
            'show_status',
            [
                'label' => __('Show Status Badge', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_button',
            [
                'label' => __('Show Button', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Learn More', 'gps-courses'),
                'condition' => [
                    'show_button' => 'yes',
                ],
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

        $this->register_style_controls_common();

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $query = $this->get_events_query($settings);

        if (!$query->have_posts()) {
            echo '<p>' . __('No events found.', 'gps-courses') . '</p>';
            return;
        }

        $layout_class = 'gps-event-list-' . esc_attr($settings['layout_style']);
        $image_position = $settings['show_image'] === 'yes' ? 'image-' . $settings['image_position'] : '';

        echo '<div class="gps-event-list ' . esc_attr($layout_class) . ' ' . esc_attr($image_position) . '">';

        while ($query->have_posts()) {
            $query->the_post();
            $event_id = get_the_ID();

            echo '<div class="gps-event-item">';

            // Image
            if ($settings['show_image'] === 'yes') {
                echo '<div class="gps-event-image">';
                echo '<a href="' . esc_url(get_permalink()) . '">';
                $this->render_event_thumbnail($event_id, 'medium');
                echo '</a>';
                echo '</div>';
            }

            echo '<div class="gps-event-content">';

            // Status badge
            if ($settings['show_status'] === 'yes') {
                $this->render_event_status($event_id);
            }

            // Title
            echo '<h3 class="gps-event-title">';
            echo '<a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a>';
            echo '</h3>';

            // Meta
            $this->render_event_meta(
                $event_id,
                $settings['show_date'] === 'yes',
                $settings['show_venue'] === 'yes',
                $settings['show_credits'] === 'yes'
            );

            // Excerpt
            if ($settings['show_excerpt'] === 'yes') {
                $excerpt_length = $settings['excerpt_length'] ?? 30;
                $excerpt = wp_trim_words(get_the_excerpt(), $excerpt_length, '...');
                echo '<div class="gps-event-excerpt">' . esc_html($excerpt) . '</div>';
            }

            // Button
            if ($settings['show_button'] === 'yes') {
                echo '<a href="' . esc_url(get_permalink()) . '" class="gps-event-button">';
                echo esc_html($settings['button_text']);
                echo '</a>';
            }

            echo '</div>'; // .gps-event-content

            echo '</div>'; // .gps-event-item
        }

        echo '</div>'; // .gps-event-list

        wp_reset_postdata();
    }
}
