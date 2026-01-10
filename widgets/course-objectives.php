<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

/**
 * Course Objectives Widget
 */
class Course_Objectives_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-course-objectives';
    }

    public function get_title() {
        return __('Course Objectives', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-editor-list-ul';
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
            'title',
            [
                'label' => __('Title', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Course Objectives', 'gps-courses'),
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
            ]
        );

        $this->add_control(
            'icon',
            [
                'label' => __('Icon', 'gps-courses'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-plus-circle',
                    'library' => 'solid',
                ],
            ]
        );

        $this->add_control(
            'show_underline',
            [
                'label' => __('Show Title Underline', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
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
                    '{{WRAPPER}} .gps-objectives-container' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .gps-objectives-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-objectives-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .gps-objectives-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2c4266',
                'selectors' => [
                    '{{WRAPPER}} .gps-objectives-title' => 'color: {{VALUE}};',
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
                    'size' => 30,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-objectives-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-objectives-title-underline' => 'background-color: {{VALUE}};',
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
                        'max' => 300,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 150,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-objectives-title-underline' => 'width: {{SIZE}}{{UNIT}};',
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
                    'size' => 4,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-objectives-title-underline' => 'height: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'show_underline' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Objectives
        $this->start_controls_section(
            'section_objectives_style',
            [
                'label' => __('Objectives', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'objective_typography',
                'selector' => '{{WRAPPER}} .gps-objective-item',
            ]
        );

        $this->add_control(
            'objective_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#646970',
                'selectors' => [
                    '{{WRAPPER}} .gps-objective-item' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'objective_spacing',
            [
                'label' => __('Item Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-objectives-list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Icon
        $this->start_controls_section(
            'section_icon_style',
            [
                'label' => __('Icon', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'icon_size',
            [
                'label' => __('Icon Size', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 24,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-objective-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gps-objective-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label' => __('Icon Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#6eb8ed',
                'selectors' => [
                    '{{WRAPPER}} .gps-objective-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .gps-objective-icon svg' => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'icon_spacing',
            [
                'label' => __('Icon Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-objective-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
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

        // Get objectives
        $objectives = get_post_meta($event_id, '_gps_objectives', true);

        if (empty($objectives)) {
            echo '<p>' . __('No objectives defined for this course.', 'gps-courses') . '</p>';
            return;
        }

        $objectives_array = array_filter(array_map('trim', explode("\n", $objectives)));

        if (empty($objectives_array)) {
            echo '<p>' . __('No objectives defined for this course.', 'gps-courses') . '</p>';
            return;
        }

        $title_tag = $settings['title_tag'];
        ?>
        <div class="gps-objectives-container">
            <?php if (!empty($settings['title'])): ?>
            <<?php echo esc_attr($title_tag); ?> class="gps-objectives-title">
                <?php echo esc_html($settings['title']); ?>
            </<?php echo esc_attr($title_tag); ?>>
            <?php if ($settings['show_underline'] === 'yes'): ?>
            <div class="gps-objectives-title-underline"></div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="gps-objectives-list">
                <?php foreach ($objectives_array as $objective): ?>
                <div class="gps-objective-item">
                    <span class="gps-objective-icon">
                        <?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']); ?>
                    </span>
                    <span class="gps-objective-text"><?php echo esc_html($objective); ?></span>
                </div>
                <?php endforeach; ?>
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
