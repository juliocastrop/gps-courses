<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Event Calendar Widget
 * Professional calendar view with category filtering and full customization
 */
class Event_Calendar_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-event-calendar';
    }

    public function get_title() {
        return __('GPS Event Calendar', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_script_depends() {
        return ['gps-courses-calendar'];
    }

    public function get_style_depends() {
        return ['gps-courses-calendar'];
    }

    protected function register_controls() {

        // ===== CONTENT SECTION =====
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Calendar Settings', 'gps-courses'),
            ]
        );

        // Category Filter
        $categories = get_terms([
            'taxonomy' => 'gps_event_category',
            'hide_empty' => false,
        ]);

        $category_options = ['all' => __('All Categories', 'gps-courses')];
        if (!empty($categories) && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_options[$category->term_id] = $category->name;
            }
        }

        $this->add_control(
            'show_category_filter',
            [
                'label' => __('Show Category Filter', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'default_categories',
            [
                'label' => __('Default Categories', 'gps-courses'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $category_options,
                'default' => ['all'],
                'condition' => [
                    'show_category_filter' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'first_day',
            [
                'label' => __('First Day of Week', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => '0',
                'options' => [
                    '0' => __('Sunday', 'gps-courses'),
                    '1' => __('Monday', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'show_event_details',
            [
                'label' => __('Show Event Details on Click', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'events_per_day',
            [
                'label' => __('Max Events Per Day Display', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'default' => 3,
                'min' => 1,
                'max' => 10,
            ]
        );

        $this->add_control(
            'show_today_button',
            [
                'label' => __('Show Today Button', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'gps-courses'),
                'label_off' => __('Hide', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'event_type_filter',
            [
                'label' => __('Event Types to Display', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all' => __('All (Courses & Seminars)', 'gps-courses'),
                    'courses' => __('Courses Only', 'gps-courses'),
                    'seminars' => __('Monthly Seminars Only', 'gps-courses'),
                ],
            ]
        );

        $this->end_controls_section();

        // ===== HEADER STYLE SECTION =====
        $this->start_controls_section(
            'section_header_style',
            [
                'label' => __('Header', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'header_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-header' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'header_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => '20',
                    'right' => '20',
                    'bottom' => '20',
                    'left' => '20',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'header_border',
                'selector' => '{{WRAPPER}} .gps-calendar-header',
            ]
        );

        $this->add_control(
            'header_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-header' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // Month/Year Title
        $this->add_control(
            'title_heading',
            [
                'label' => __('Month/Year Title', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#1a1a1a',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .gps-calendar-title',
            ]
        );

        // Navigation Buttons
        $this->add_control(
            'nav_buttons_heading',
            [
                'label' => __('Navigation Buttons', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'nav_button_color',
            [
                'label' => __('Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-nav-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'nav_button_hover_color',
            [
                'label' => __('Hover Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-nav-btn:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'nav_button_size',
            [
                'label' => __('Size', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 24,
                        'max' => 60,
                    ],
                ],
                'default' => [
                    'size' => 40,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-nav-btn' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: calc({{SIZE}}{{UNIT}} * 0.4);',
                    '{{WRAPPER}} .gps-today-btn' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Today Button
        $this->add_control(
            'today_button_heading',
            [
                'label' => __('Today Button', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'show_today_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'today_button_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-today-btn' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'show_today_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'today_button_hover_background',
            [
                'label' => __('Hover Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#135e96',
                'selectors' => [
                    '{{WRAPPER}} .gps-today-btn:hover' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'show_today_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'today_button_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-today-btn' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_today_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'today_button_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => '6',
                    'right' => '6',
                    'bottom' => '6',
                    'left' => '6',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-today-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition' => [
                    'show_today_button' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'today_button_typography',
                'selector' => '{{WRAPPER}} .gps-today-btn',
                'condition' => [
                    'show_today_button' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // ===== CATEGORY FILTER STYLE =====
        $this->start_controls_section(
            'section_category_style',
            [
                'label' => __('Category Filter', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_category_filter' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'filter_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f8f9fa',
                'selectors' => [
                    '{{WRAPPER}} .gps-category-filter' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_text_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#646970',
                'selectors' => [
                    '{{WRAPPER}} .gps-category-filter select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_border_color',
            [
                'label' => __('Border Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#dcdcde',
                'selectors' => [
                    '{{WRAPPER}} .gps-category-filter select' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => '10',
                    'right' => '15',
                    'bottom' => '10',
                    'left' => '15',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-category-filter select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'filter_typography',
                'selector' => '{{WRAPPER}} .gps-category-filter select',
            ]
        );

        $this->end_controls_section();

        // ===== CALENDAR GRID STYLE =====
        $this->start_controls_section(
            'section_grid_style',
            [
                'label' => __('Calendar Grid', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'grid_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-grid' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'grid_border_color',
            [
                'label' => __('Border Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e8e8e8',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-day' => 'border-color: {{VALUE}};',
                    '{{WRAPPER}} .gps-calendar-grid' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'grid_gap',
            [
                'label' => __('Cell Gap', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'size' => 1,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Day Header (Sun, Mon, etc.)
        $this->add_control(
            'day_header_heading',
            [
                'label' => __('Day Headers', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'day_header_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f6f7f7',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-day-header' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'day_header_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2c3338',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-day-header' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'day_header_typography',
                'selector' => '{{WRAPPER}} .gps-calendar-day-header',
            ]
        );

        // Day Numbers
        $this->add_control(
            'day_numbers_heading',
            [
                'label' => __('Day Numbers', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'day_number_color',
            [
                'label' => __('Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2c3338',
                'selectors' => [
                    '{{WRAPPER}} .gps-day-number' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'other_month_color',
            [
                'label' => __('Other Month Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#c0c0c0',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-day.other-month .gps-day-number' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'day_number_typography',
                'selector' => '{{WRAPPER}} .gps-day-number',
            ]
        );

        $this->end_controls_section();

        // ===== TODAY STYLE =====
        $this->start_controls_section(
            'section_today_style',
            [
                'label' => __('Today Highlight', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'today_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#5b6cf9',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-day.today .gps-day-number' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'today_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-day.today .gps-day-number' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'today_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-day.today .gps-day-number' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // ===== EVENT ITEM STYLE =====
        $this->start_controls_section(
            'section_event_style',
            [
                'label' => __('Event Items', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'event_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e0e7ff',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-event' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'event_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#1e40af',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-event' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'event_hover_background',
            [
                'label' => __('Hover Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#c7d2fe',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-event:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'event_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'size' => 4,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-event' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'event_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => '4',
                    'right' => '8',
                    'bottom' => '4',
                    'left' => '8',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-event' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'event_typography',
                'selector' => '{{WRAPPER}} .gps-calendar-event',
            ]
        );

        $this->end_controls_section();

        // ===== EVENT DETAILS SIDEBAR STYLE =====
        $this->start_controls_section(
            'section_sidebar_style',
            [
                'label' => __('Event Details Sidebar', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'sidebar_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-sidebar' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sidebar_width',
            [
                'label' => __('Width', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 250,
                        'max' => 500,
                    ],
                    '%' => [
                        'min' => 20,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 350,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-sidebar' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'sidebar_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => '30',
                    'right' => '30',
                    'bottom' => '30',
                    'left' => '30',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-sidebar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'sidebar_shadow',
                'selector' => '{{WRAPPER}} .gps-calendar-sidebar',
            ]
        );

        // View Details Button
        $this->add_control(
            'sidebar_button_heading',
            [
                'label' => __('View Details Button', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'sidebar_button_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-sidebar-event-link' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sidebar_button_hover_background',
            [
                'label' => __('Hover Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#135e96',
                'selectors' => [
                    '{{WRAPPER}} .gps-sidebar-event-link:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sidebar_button_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-sidebar-event-link' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sidebar_button_hover_color',
            [
                'label' => __('Hover Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-sidebar-event-link:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sidebar_button_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => '6',
                    'right' => '6',
                    'bottom' => '6',
                    'left' => '6',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-sidebar-event-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'sidebar_button_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => '12',
                    'right' => '24',
                    'bottom' => '12',
                    'left' => '24',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-sidebar-event-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'sidebar_button_typography',
                'selector' => '{{WRAPPER}} .gps-sidebar-event-link',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'sidebar_button_border',
                'selector' => '{{WRAPPER}} .gps-sidebar-event-link',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'sidebar_button_shadow',
                'selector' => '{{WRAPPER}} .gps-sidebar-event-link',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $widget_id = $this->get_id();
        $calendar_id = 'gps-calendar-' . $widget_id;

        ?>
        <div class="gps-calendar-wrapper" id="<?php echo esc_attr($calendar_id); ?>" data-widget-id="<?php echo esc_attr($widget_id); ?>">

            <!-- Calendar Header -->
            <div class="gps-calendar-header">
                <div class="gps-calendar-navigation">
                    <button class="gps-calendar-nav-btn gps-prev-month" aria-label="<?php esc_attr_e('Previous month', 'gps-courses'); ?>">
                        &#8249;
                    </button>

                    <div class="gps-calendar-title-wrapper">
                        <select class="gps-month-selector">
                            <option value="0"><?php esc_html_e('January', 'gps-courses'); ?></option>
                            <option value="1"><?php esc_html_e('February', 'gps-courses'); ?></option>
                            <option value="2"><?php esc_html_e('March', 'gps-courses'); ?></option>
                            <option value="3"><?php esc_html_e('April', 'gps-courses'); ?></option>
                            <option value="4"><?php esc_html_e('May', 'gps-courses'); ?></option>
                            <option value="5"><?php esc_html_e('June', 'gps-courses'); ?></option>
                            <option value="6"><?php esc_html_e('July', 'gps-courses'); ?></option>
                            <option value="7"><?php esc_html_e('August', 'gps-courses'); ?></option>
                            <option value="8"><?php esc_html_e('September', 'gps-courses'); ?></option>
                            <option value="9"><?php esc_html_e('October', 'gps-courses'); ?></option>
                            <option value="10"><?php esc_html_e('November', 'gps-courses'); ?></option>
                            <option value="11"><?php esc_html_e('December', 'gps-courses'); ?></option>
                        </select>
                        <select class="gps-year-selector">
                            <?php
                            $current_year = date('Y');
                            for ($year = $current_year - 2; $year <= $current_year + 5; $year++) {
                                echo '<option value="' . $year . '">' . $year . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <button class="gps-calendar-nav-btn gps-next-month" aria-label="<?php esc_attr_e('Next month', 'gps-courses'); ?>">
                        &#8250;
                    </button>
                    <?php if ($settings['show_today_button'] === 'yes'): ?>
                    <button class="gps-today-btn" aria-label="<?php esc_attr_e('Go to today', 'gps-courses'); ?>">
                        <?php esc_html_e('Today', 'gps-courses'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($settings['show_category_filter'] === 'yes'): ?>
                <div class="gps-category-filter">
                    <select class="gps-category-select">
                        <option value="all"><?php esc_html_e('All Categories', 'gps-courses'); ?></option>
                        <?php
                        $categories = get_terms([
                            'taxonomy' => 'gps_event_category',
                            'hide_empty' => false,
                        ]);

                        if (!empty($categories) && !is_wp_error($categories)) {
                            foreach ($categories as $category) {
                                $selected = in_array($category->term_id, (array) $settings['default_categories']) ? 'selected' : '';
                                echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="gps-calendar-content">
                <!-- Calendar Grid -->
                <div class="gps-calendar-body">
                    <div class="gps-calendar-grid">
                        <!-- Day headers -->
                        <div class="gps-calendar-day-header"><?php esc_html_e('Sun', 'gps-courses'); ?></div>
                        <div class="gps-calendar-day-header"><?php esc_html_e('Mon', 'gps-courses'); ?></div>
                        <div class="gps-calendar-day-header"><?php esc_html_e('Tue', 'gps-courses'); ?></div>
                        <div class="gps-calendar-day-header"><?php esc_html_e('Wed', 'gps-courses'); ?></div>
                        <div class="gps-calendar-day-header"><?php esc_html_e('Thu', 'gps-courses'); ?></div>
                        <div class="gps-calendar-day-header"><?php esc_html_e('Fri', 'gps-courses'); ?></div>
                        <div class="gps-calendar-day-header"><?php esc_html_e('Sat', 'gps-courses'); ?></div>

                        <!-- Day cells will be generated by JavaScript -->
                    </div>
                </div>

                <!-- Event Details Sidebar -->
                <?php if ($settings['show_event_details'] === 'yes'): ?>
                <div class="gps-calendar-sidebar">
                    <div class="gps-sidebar-header">
                        <h3 class="gps-sidebar-title"><?php esc_html_e('Upcoming Events', 'gps-courses'); ?></h3>
                        <p class="gps-selected-date"></p>
                    </div>
                    <div class="gps-sidebar-content">
                        <p class="gps-no-events"><?php esc_html_e('No events scheduled.', 'gps-courses'); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Loading Overlay -->
            <div class="gps-calendar-loading" style="display: none;">
                <div class="gps-spinner"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            if (typeof GPSEventCalendar !== 'undefined') {
                new GPSEventCalendar('#<?php echo esc_js($calendar_id); ?>', {
                    firstDay: <?php echo (int) $settings['first_day']; ?>,
                    eventsPerDay: <?php echo (int) $settings['events_per_day']; ?>,
                    showEventDetails: <?php echo $settings['show_event_details'] === 'yes' ? 'true' : 'false'; ?>,
                    ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    nonce: '<?php echo wp_create_nonce('gps_calendar_nonce'); ?>',
                    eventType: '<?php echo esc_js($settings['event_type_filter'] ?? 'all'); ?>'
                });
            }
        });
        </script>
        <?php
    }
}
