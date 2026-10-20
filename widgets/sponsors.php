<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use GPSC\Home_Sections;

/**
 * GPS Sponsors: the site's default sponsors (GPS Courses → Settings → Course
 * page, gps_sponsor posts: featured image = logo, Sponsor link = website) as
 * a logo grid under a section header.
 */
class Sponsors_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-sponsors';
    }

    public function get_title() {
        return __('GPS Sponsors', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-logo';
    }

    public function get_keywords() {
        return ['gps', 'sponsors', 'partners', 'logos', 'home'];
    }

    public function get_style_depends() {
        return ['gps-home'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', [
            'label' => __('Sponsors', 'gps-courses'),
        ]);
        $this->add_control('eyebrow', [
            'label'       => __('Eyebrow', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Thank you to our partners', 'gps-courses'),
        ]);
        $this->add_control('title', [
            'label'       => __('Title', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Our sponsors', 'gps-courses'),
        ]);
        $this->add_control('title_tag', [
            'label'   => __('Title tag', 'gps-courses'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'h2',
            'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p'],
        ]);
        $this->add_control('align', [
            'label'   => __('Heading alignment', 'gps-courses'),
            'type'    => Controls_Manager::CHOOSE,
            'default' => 'center',
            'options' => [
                'left'   => ['title' => __('Left', 'gps-courses'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Center', 'gps-courses'), 'icon' => 'eicon-text-align-center'],
            ],
            'toggle'  => false,
        ]);
        $this->add_control('grayscale', [
            'label'        => __('Grayscale logos (color on hover)', 'gps-courses'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);
        $this->add_control('sponsors_note', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => esc_html__('Logos are the default sponsors from GPS Courses → Settings → Course page. Manage them under GPS Courses → Sponsors.', 'gps-courses'),
            'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
        ]);
        $this->end_controls_section();
    }

    protected function render() {
        $s        = $this->get_settings_for_display();
        $sponsors = Home_Sections::sponsors();
        if (!$sponsors) {
            if ($this->is_editor()) {
                echo '<p class="gps-home-empty">' . esc_html__('No default sponsors yet: choose them in GPS Courses → Settings → Course page.', 'gps-courses') . '</p>';
            }
            return;
        }
        $gray = 'yes' === ($s['grayscale'] ?? 'yes');
        echo '<section class="gps-home gps-home-sponsors">';
        echo Home_Sections::head([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Home_Sections::head()
            'eyebrow' => $s['eyebrow'] ?? '',
            'title'   => $s['title'] ?? '',
            'tag'     => $s['title_tag'] ?? 'h2',
            'align'   => $s['align'] ?? 'center',
        ]);
        echo '<ul class="gps-home-logos' . ($gray ? ' gps-home-logos--gray' : '') . '">';
        foreach ($sponsors as $sp) {
            $name = (string) $sp['name'];
            $img  = $sp['logo'] ? wp_get_attachment_image((int) $sp['logo'], 'medium', false, ['alt' => $name, 'loading' => 'lazy', 'class' => 'gps-home-logos__img']) : '';
            $body = $img ?: '<span class="gps-home-logos__name">' . esc_html($name) . '</span>';
            $url  = trim((string) $sp['url']);
            echo '<li class="gps-home-logos__item">';
            if ('' !== $url) {
                echo '<a class="gps-home-logos__link" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $body . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() / esc_html
            } else {
                echo '<span class="gps-home-logos__link">' . $body . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() / esc_html
            }
            echo '</li>';
        }
        echo '</ul></section>';
    }

    protected function is_editor() {
        return class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }
}
