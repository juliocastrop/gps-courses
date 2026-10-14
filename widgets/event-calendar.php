<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Event Calendar Widget
 *
 * Month calendar of courses and monthly seminar sessions plus the next
 * upcoming events. The markup comes from \GPSC\Event_Calendar (shared with the
 * gps_get_calendar_events AJAX action), so the current month is complete
 * without JavaScript; assets/js/calendar.js adds in-place month navigation,
 * filters and the day panel.
 *
 * The style controls of the first version (header, grid, day numbers, today,
 * event pills, sidebar, buttons) targeted the old JS-built markup and were
 * replaced by the "Colors" section below: the calendar now follows the GPS
 * design tokens (gps-forms.css) and only exposes the colours that carry
 * meaning (course / seminar / accent).
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

    public function get_keywords() {
        return ['calendar', 'events', 'courses', 'seminars', 'gps'];
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
                'description' => __('"All" also shows the All / Courses / Seminars filter chips.', 'gps-courses'),
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
                'description' => __('Course category select (only shown when categories exist).', 'gps-courses'),
            ]
        );

        $this->add_control(
            'default_categories',
            [
                'label' => __('Default Category', 'gps-courses'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $category_options,
                'default' => ['all'],
                'description' => __('The first category selected is preselected.', 'gps-courses'),
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
            'show_event_details',
            [
                'label' => __('Show Upcoming Events Panel', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
                'description' => __('Next events from today; clicking a day shows that day\'s events.', 'gps-courses'),
            ]
        );

        $this->add_control(
            'upcoming_count',
            [
                'label' => __('Upcoming Events to Show', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'default' => 5,
                'min' => 1,
                'max' => 20,
                'condition' => [
                    'show_event_details' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'upcoming_title',
            [
                'label' => __('Upcoming Panel Title', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Upcoming events', 'gps-courses'),
                'condition' => [
                    'show_event_details' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // ===== COLORS =====
        $this->start_controls_section(
            'section_cal_colors',
            [
                'label' => __('Colors', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'cal_colors_note',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => esc_html__('Leave empty to use the GPS design colours (navy, course blue #0B52AC, seminar gold #DDC89D).', 'gps-courses'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->add_control(
            'cal_course_color',
            [
                'label' => __('Course Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gpsc-cal' => '--gpsc-course: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'cal_seminar_color',
            [
                'label' => __('Seminar Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gpsc-cal' => '--gpsc-seminar: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'cal_accent_color',
            [
                'label' => __('Accent Color (title, buttons, today)', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gpsc-cal' => '--gpsc-navy: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'cal_card_background',
            [
                'label' => __('Card Background', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gpsc-cal' => '--gpsc-paper: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'cal_title_typography',
                'label' => __('Month Title Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .gpsc-cal__title',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!class_exists('\GPSC\Event_Calendar')) {
            return;
        }
        $settings = $this->get_settings_for_display();

        $show_category = isset($settings['show_category_filter']) && 'yes' === $settings['show_category_filter'];
        $category = 'all';
        if ($show_category && !empty($settings['default_categories'])) {
            foreach ((array) $settings['default_categories'] as $cat) {
                if ('all' !== (string) $cat && '' !== (string) $cat) {
                    $category = (string) $cat;
                    break;
                }
            }
        }
        $upcoming_title = isset($settings['upcoming_title']) ? (string) $settings['upcoming_title'] : '';

        // Escaped inside Event_Calendar::render()
        echo \GPSC\Event_Calendar::render([
            'id'             => $this->get_id(),
            'base_type'      => isset($settings['event_type_filter']) ? $settings['event_type_filter'] : 'all',
            'category'       => $category,
            'first_day'      => isset($settings['first_day']) ? (int) $settings['first_day'] : 0,
            'per_day'        => isset($settings['events_per_day']) ? (int) $settings['events_per_day'] : 3,
            'upcoming'       => isset($settings['upcoming_count']) && '' !== (string) $settings['upcoming_count'] ? (int) $settings['upcoming_count'] : 5,
            'show_upcoming'  => !isset($settings['show_event_details']) || 'yes' === $settings['show_event_details'],
            'show_today'     => !isset($settings['show_today_button']) || 'yes' === $settings['show_today_button'],
            'show_category'  => $show_category,
            'upcoming_title' => '' !== trim($upcoming_title) ? $upcoming_title : __('Upcoming events', 'gps-courses'),
        ]);
    }
}
