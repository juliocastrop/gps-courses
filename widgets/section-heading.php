<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use GPSC\Home_Sections;

/**
 * GPS Section Heading: gold eyebrow + bold navy title + optional link,
 * the section header of the home page sections (gps-home.css).
 */
class Section_Heading_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-section-heading';
    }

    public function get_title() {
        return __('GPS Section Heading', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-heading';
    }

    public function get_keywords() {
        return ['gps', 'heading', 'title', 'eyebrow', 'section', 'home'];
    }

    public function get_style_depends() {
        return ['gps-home'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', [
            'label' => __('Heading', 'gps-courses'),
        ]);

        $this->add_control('eyebrow', [
            'label'       => __('Eyebrow', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Hands-on courses · Duluth, GA', 'gps-courses'),
        ]);

        $this->add_control('title', [
            'label'       => __('Title', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Upcoming courses', 'gps-courses'),
        ]);

        $this->add_control('title_tag', [
            'label'   => __('Title tag', 'gps-courses'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'h2',
            'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p'],
        ]);

        $this->add_control('link_label', [
            'label'       => __('Link label', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'placeholder' => __('All courses →', 'gps-courses'),
            'separator'   => 'before',
        ]);

        $this->add_control('link', [
            'label'       => __('Link', 'gps-courses'),
            'type'        => Controls_Manager::URL,
            'placeholder' => '/courses/',
        ]);

        $this->add_control('align', [
            'label'   => __('Alignment', 'gps-courses'),
            'type'    => Controls_Manager::CHOOSE,
            'default' => 'left',
            'options' => [
                'left'   => ['title' => __('Left', 'gps-courses'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Center', 'gps-courses'), 'icon' => 'eicon-text-align-center'],
            ],
            'toggle'  => false,
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $link = Home_Sections::link_value($s['link'] ?? '');
        $html = Home_Sections::head([
            'eyebrow'    => $s['eyebrow'] ?? '',
            'title'      => $s['title'] ?? '',
            'tag'        => $s['title_tag'] ?? 'h2',
            'align'      => $s['align'] ?? 'left',
            'link_label' => $s['link_label'] ?? '',
            'link_url'   => $link['url'],
            'external'   => $link['external'],
            'nofollow'   => $link['nofollow'],
        ]);
        if ('' !== $html) {
            echo '<div class="gps-home gps-home-heading">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Home_Sections::head()
        }
    }
}
