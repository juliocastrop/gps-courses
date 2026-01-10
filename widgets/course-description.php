<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Course Description Widget
 */
class Course_Description_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-course-description';
    }

    public function get_title() {
        return __('Course Description', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-text-area';
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Settings', 'gps-courses'),
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
            'title',
            [
                'label' => __('Title', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('About This Course', 'gps-courses'),
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'title_tag',
            [
                'label' => __('Title HTML Tag', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                    'div' => 'div',
                ],
                'default' => 'h2',
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_underline',
            [
                'label' => __('Show Title Underline', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Container
        $this->start_controls_section(
            'section_container_style',
            [
                'label' => __('Container', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_bg',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-description-container' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-description-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-description-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Title
        $this->start_controls_section(
            'section_title_style',
            [
                'label' => __('Title', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_title' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .gps-description-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2c4266',
                'selectors' => [
                    '{{WRAPPER}} .gps-description-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_spacing',
            [
                'label' => __('Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-description-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'underline_color',
            [
                'label' => __('Underline Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#6eb8ed',
                'selectors' => [
                    '{{WRAPPER}} .gps-description-title-underline' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'show_underline' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'underline_width',
            [
                'label' => __('Underline Width', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 500,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 80,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-description-title-underline' => 'width: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'show_underline' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'underline_height',
            [
                'label' => __('Underline Height', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 10,
                    ],
                ],
                'default' => [
                    'size' => 3,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-description-title-underline' => 'height: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'show_underline' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Description Text
        $this->start_controls_section(
            'section_description_style',
            [
                'label' => __('Description Text', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .gps-description-text',
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#646970',
                'selectors' => [
                    '{{WRAPPER}} .gps-description-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'description_spacing',
            [
                'label' => __('Line Height', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 3,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'size' => 1.7,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-description-text' => 'line-height: {{SIZE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Get event ID
        $event_id = !empty($settings['event_id']) ? $settings['event_id'] : get_the_ID();

        if (!$event_id || get_post_type($event_id) !== 'gps_event') {
            echo '<p>' . __('This widget must be used on an event page or have an event selected.', 'gps-courses') . '</p>';
            return;
        }

        // Get course description
        $description = get_post_meta($event_id, '_gps_course_description', true);

        if (empty($description)) {
            echo '<p>' . __('No course description available.', 'gps-courses') . '</p>';
            return;
        }

        $title_tag = $settings['title_tag'];

        ?>
        <div class="gps-description-container">
            <?php if ($settings['show_title'] === 'yes' && !empty($settings['title'])): ?>
            <<?php echo esc_attr($title_tag); ?> class="gps-description-title">
                <?php echo esc_html($settings['title']); ?>
            </<?php echo esc_attr($title_tag); ?>>
            <?php if ($settings['show_underline'] === 'yes'): ?>
            <div class="gps-description-title-underline"></div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="gps-description-text">
                <?php echo wpautop(esc_html($description)); ?>
            </div>
        </div>
        <?php
    }

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
