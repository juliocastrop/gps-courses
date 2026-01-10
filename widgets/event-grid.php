<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Event Grid Widget
 */
class Event_Grid_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-event-grid';
    }

    public function get_title() {
        return __('Event Grid', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-posts-grid';
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

        $this->add_responsive_control(
            'columns',
            [
                'label' => __('Columns', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );

        $this->add_responsive_control(
            'column_gap',
            [
                'label' => __('Column Gap', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 30,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-grid' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'row_gap',
            [
                'label' => __('Row Gap', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 30,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-grid' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
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
                'default' => 20,
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
                'default' => __('View Details', 'gps-courses'),
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

        // Title Style
        $this->start_controls_section(
            'section_title_style',
            [
                'label' => __('Title', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .gps-event-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-event-title a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_hover_color',
            [
                'label' => __('Hover Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-event-title a:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $query = $this->get_events_query($settings);

        if (!$query->have_posts()) {
            echo '<p>' . __('No events found.', 'gps-courses') . '</p>';
            return;
        }

        echo '<div class="gps-event-grid gps-event-grid-' . esc_attr($settings['columns']) . '">';

        while ($query->have_posts()) {
            $query->the_post();
            $event_id = get_the_ID();

            echo '<div class="gps-event-item">';

            // Image
            if ($settings['show_image'] === 'yes') {
                echo '<a href="' . esc_url(get_permalink()) . '" class="gps-event-image-link">';
                $this->render_event_thumbnail($event_id);
                echo '</a>';
            }

            echo '<div class="gps-event-content">';

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
                $excerpt_length = $settings['excerpt_length'] ?? 20;
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

        echo '</div>'; // .gps-event-grid

        wp_reset_postdata();
    }
}
