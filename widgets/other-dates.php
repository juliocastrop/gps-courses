<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Course Other Dates: upcoming editions of the same course (same
 * gps_course_program), with their status. Renders nothing when there are none.
 */
class Other_Dates_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-other-dates';
    }

    public function get_title() {
        return __('Course Other Dates', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_keywords() {
        return ['gps', 'course', 'dates', 'editions', 'program'];
    }

    public function get_style_depends() {
        return ['gps-course-stage'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', [
            'label' => __('Settings', 'gps-courses'),
        ]);

        $this->add_control('event_id', [
            'label'       => __('Course', 'gps-courses'),
            'type'        => Controls_Manager::SELECT2,
            'options'     => $this->get_events_list(),
            'default'     => '',
            'description' => __('Leave empty to use the current course.', 'gps-courses'),
        ]);

        $this->add_control('title', [
            'label'   => __('Title', 'gps-courses'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Other dates for this course', 'gps-courses'),
        ]);

        $this->add_control('title_tag', [
            'label'   => __('Title tag', 'gps-courses'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'h3',
            'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'p' => 'p'],
        ]);

        $this->add_control('note', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => esc_html__('Lists upcoming editions in the same Course program (course editor → Interest list tab). Nothing is shown when there are none.', 'gps-courses'),
            'content_classes' => 'elementor-descriptor',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Style', 'gps-courses'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'title_typography',
            'label'    => __('Title typography', 'gps-courses'),
            'selector' => '{{WRAPPER}} .gps-other-dates__title',
        ]);

        $this->add_control('title_color', [
            'label'     => __('Title color', 'gps-courses'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gps-other-dates__title' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('date_color', [
            'label'     => __('Date color', 'gps-courses'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gps-other-dates__when' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('list_bg', [
            'label'     => __('List background', 'gps-courses'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .gps-other-dates__list' => 'background-color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $event_id = \GPSC\resolve_widget_event_id($settings['event_id'] ?? '', $this->get_id());
        $editor   = class_exists('\GPSC\Elementor_Integration') && \Elementor\Plugin::$instance && \GPSC\Elementor_Integration::is_elementor_editor();

        if (!$event_id || 'gps_event' !== get_post_type($event_id) || !class_exists('\GPSC\Course_Stage')) {
            if ($editor) {
                echo '<p>' . esc_html__('Select a course (or use this widget on a course page).', 'gps-courses') . '</p>';
            }
            return;
        }

        $html = \GPSC\Course_Stage::render_other_dates($event_id, [
            'title'       => isset($settings['title']) ? (string) $settings['title'] : '',
            'heading_tag' => isset($settings['title_tag']) ? (string) $settings['title_tag'] : 'h3',
        ]);

        if ('' === $html) {
            if ($editor) {
                echo '<p style="padding:12px;border:1px dashed #c3c4c7;color:#646970;font-size:13px;">'
                    . esc_html__('No other upcoming dates for this course right now, so nothing is shown on the live page.', 'gps-courses')
                    . '</p>';
            }
            return;
        }
        echo $html; // escaped in Course_Stage::render_other_dates()
    }

    private function get_events_list() {
        // Editions listed as "Course — Edition 2026 (date)"; a course with editions is not
        return ['' => __('Current course', 'gps-courses')] + \GPSC\Editions::admin_options(0, [
            'query' => [
                'post_type'      => 'gps_event',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'meta_value',
                'meta_key'       => '_gps_start_date',
                'order'          => 'DESC',
            ],
            'no_title' => '',
            'suffix'   => function ($event) {
                $start = get_post_meta($event->ID, '_gps_start_date', true);
                return $start ? ' (' . date_i18n('M j, Y', strtotime($start)) . ')' : '';
            },
        ]);
    }
}
