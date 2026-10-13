<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use GPSC\Hero_Slider;

/**
 * GPS Hero Slider Widget
 *
 * Full-bleed home hero. Manual slides plus automatic slides for upcoming
 * courses and the monthly seminar program (from gpsc_catalog()). Markup and
 * slide data live in GPSC\Hero_Slider; this class holds the controls.
 */
class Hero_Slider_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-hero-slider';
    }

    public function get_title() {
        return __('GPS Hero Slider', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-slider-full-screen';
    }

    public function get_keywords() {
        return ['hero', 'slider', 'banner', 'courses', 'seminars', 'gps'];
    }

    public function get_style_depends() {
        return ['gps-hero'];
    }

    public function get_script_depends() {
        return ['gps-hero'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_slides', [
            'label' => __('Slides', 'gps-courses'),
        ]);

        $repeater = new Repeater();

        $repeater->add_control('type', [
            'label'   => __('Type', 'gps-courses'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'manual',
            'options' => [
                'manual'  => __('Manual slide', 'gps-courses'),
                'courses' => __('Upcoming courses (group)', 'gps-courses'),
                'seminar' => __('Monthly seminar', 'gps-courses'),
            ],
        ]);

        // Manual slide
        $manual = ['type' => 'manual'];
        $repeater->add_control('eyebrow', [
            'label'       => __('Eyebrow', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'condition'   => $manual,
        ]);

        // Headline / highlight / line: manual and seminar
        $repeater->add_control('headline', [
            'label'       => __('Headline', 'gps-courses'),
            'type'        => Controls_Manager::TEXTAREA,
            'rows'        => 2,
            'condition'   => ['type' => ['manual', 'seminar']],
        ]);
        $repeater->add_control('highlight', [
            'label'       => __('Gold highlight', 'gps-courses'),
            'description' => __('Second part of the headline, drawn in gold.', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'condition'   => ['type' => ['manual', 'seminar']],
        ]);
        $repeater->add_control('line', [
            'label'       => __('Short line', 'gps-courses'),
            'type'        => Controls_Manager::TEXTAREA,
            'rows'        => 3,
            'condition'   => ['type' => ['manual', 'seminar']],
        ]);
        $repeater->add_control('location', [
            'label'       => __('Session location', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Duluth, GA',
            'condition'   => ['type' => 'seminar'],
        ]);

        $repeater->add_control('image', [
            'label'       => __('Background image', 'gps-courses'),
            'description' => __('For courses: used only when a course has no hero image and no course image.', 'gps-courses'),
            'type'        => Controls_Manager::MEDIA,
            'default'     => ['url' => '', 'id' => 0],
        ]);

        $repeater->add_control('primary_label', [
            'label'     => __('Primary button', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'separator' => 'before',
            'condition' => $manual,
        ]);
        $repeater->add_control('primary_link', [
            'label'     => __('Primary link', 'gps-courses'),
            'type'      => Controls_Manager::URL,
            'condition' => $manual,
        ]);
        $repeater->add_control('secondary_label', [
            'label'     => __('Secondary button', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'condition' => $manual,
        ]);
        $repeater->add_control('secondary_link', [
            'label'     => __('Secondary link', 'gps-courses'),
            'type'      => Controls_Manager::URL,
            'condition' => $manual,
        ]);
        $repeater->add_control('tab_label', [
            'label'       => __('Tab label', 'gps-courses'),
            'description' => __('Short name in the slide tabs. Empty = the headline.', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'separator'   => 'before',
            'condition'   => $manual,
        ]);
        $repeater->add_control('show_from', [
            'label'       => __('Show from', 'gps-courses'),
            'type'        => Controls_Manager::DATE_TIME,
            'description' => __('Optional. Site time zone. Starts at the beginning of that day.', 'gps-courses'),
            'picker_options' => ['enableTime' => false],
            'condition'   => $manual,
        ]);
        $repeater->add_control('show_until', [
            'label'       => __('Show until', 'gps-courses'),
            'type'        => Controls_Manager::DATE_TIME,
            'description' => __('Optional. Shown until the end of that day.', 'gps-courses'),
            'picker_options' => ['enableTime' => false],
            'condition'   => $manual,
        ]);

        // Courses group
        $repeater->add_control('courses_note', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => esc_html__('One slide per upcoming course: featured first, then by date, low priority last. Each course is managed on its edit screen, "Home hero" tab. Closed courses never show.', 'gps-courses'),
            'content_classes' => 'elementor-descriptor',
            'condition'       => ['type' => 'courses'],
        ]);
        $repeater->add_control('max_courses', [
            'label'     => __('Max courses', 'gps-courses'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 12,
            'default'   => 4,
            'condition' => ['type' => 'courses'],
        ]);
        $repeater->add_control('include_sold_out', [
            'label'     => __('Include sold-out courses', 'gps-courses'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => '',
            'condition' => ['type' => 'courses'],
        ]);

        $this->add_control('slides', [
            'label'       => __('Slides (drag to reorder)', 'gps-courses'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => "{{{ 'courses' === type ? '" . esc_js(__('Upcoming courses', 'gps-courses')) . "' : ( 'seminar' === type ? '" . esc_js(__('Monthly seminar', 'gps-courses')) . "' : ( _.escape( tab_label || headline ) || '" . esc_js(__('Manual slide', 'gps-courses')) . "' ) ) }}}",
            'default'     => [
                [
                    'type'            => 'manual',
                    'eyebrow'         => 'Hands-on continuing education · Duluth, GA',
                    'headline'        => 'Advanced Education',
                    'highlight'       => 'in Surgical, Restorative and Digital Dentistry',
                    'line'            => 'Live-patient, hands-on courses and monthly seminars. Every program awards CE credits from a Nationally Approved PACE Program Provider.',
                    'primary_label'   => 'Browse courses',
                    'primary_link'    => ['url' => (string) get_post_type_archive_link('gps_event') ?: home_url('/courses/')],
                    'secondary_label' => 'Monthly seminars',
                    'secondary_link'  => ['url' => home_url('/monthly-seminars/')],
                    'tab_label'       => 'GPS Dental Training',
                ],
                [
                    'type'        => 'courses',
                    'max_courses' => 4,
                ],
                [
                    'type'      => 'seminar',
                    'headline'  => 'One evening a month.',
                    'highlight' => '20 CE credits a year.',
                    'line'      => 'Ten two-hour sessions with GPS faculty, 2 CE credits each, one makeup session included.',
                    'location'  => 'Duluth, GA',
                ],
            ],
        ]);

        $this->end_controls_section();

        // Settings
        $this->start_controls_section('section_settings', [
            'label' => __('Settings', 'gps-courses'),
        ]);

        $this->add_control('autoplay', [
            'label'       => __('Autoplay (seconds)', 'gps-courses'),
            'description' => __('0 = off. Always off for visitors who prefer reduced motion.', 'gps-courses'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'max'         => 30,
            'default'     => 7,
        ]);

        $this->add_responsive_control('height', [
            'label'      => __('Height', 'gps-courses'),
            'description' => __('Desktop and tablet. On phones the hero stacks image and text.', 'gps-courses'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 420, 'max' => 1000]],
            'default'    => ['size' => 720, 'unit' => 'px'],
            'selectors'  => ['{{WRAPPER}} .gps-hero' => '--gps-hero-h: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('top_offset', [
            'label'       => __('Top offset', 'gps-courses'),
            'description' => __('Space for the site header that overlays the top of the hero.', 'gps-courses'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => ['px'],
            'range'       => ['px' => ['min' => 0, 'max' => 240]],
            'default'     => ['size' => 100, 'unit' => 'px'],
            'selectors'   => ['{{WRAPPER}} .gps-hero' => '--gps-hero-top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('use_h1', [
            'label'       => __('Use H1 for the first slide', 'gps-courses'),
            'description' => __('Turn off if the page already has its own H1.', 'gps-courses'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
        ]);

        $this->add_control('region_label', [
            'label'   => __('Accessible name', 'gps-courses'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Featured courses and programs', 'gps-courses'),
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        if (!class_exists('\\GPSC\\Hero_Slider')) {
            return;
        }
        $settings  = $this->get_settings_for_display();
        $is_editor = class_exists('\\Elementor\\Plugin') && (
            \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode()
        );

        $slides = Hero_Slider::build_slides((array) ($settings['slides'] ?? []), $is_editor);
        if (!$slides) {
            if ($is_editor) {
                echo '<div class="elementor-alert elementor-alert-info">' . esc_html__('GPS Hero Slider: no slide to show right now (check the dates of the manual slides).', 'gps-courses') . '</div>';
            }
            return;
        }

        Hero_Slider::render($slides, [
            'autoplay' => isset($settings['autoplay']) && '' !== $settings['autoplay'] ? (int) $settings['autoplay'] : 7,
            'use_h1'   => 'yes' === ($settings['use_h1'] ?? ''),
            'label'    => (string) ($settings['region_label'] ?? ''),
        ], 'gps-hero-' . $this->get_id());
    }
}
