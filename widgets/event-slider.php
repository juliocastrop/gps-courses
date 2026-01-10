<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Event Slider Widget
 */
class Event_Slider_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-event-slider';
    }

    public function get_title() {
        return __('Event Slider', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-slider-push';
    }

    public function get_script_depends() {
        return ['swiper', 'gps-courses-widgets'];
    }

    public function get_style_depends() {
        return ['swiper'];
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
            'slides_to_show',
            [
                'label' => __('Slides to Show', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 6,
                'default' => 3,
                'tablet_default' => 2,
                'mobile_default' => 1,
            ]
        );

        $this->add_responsive_control(
            'slides_to_scroll',
            [
                'label' => __('Slides to Scroll', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 6,
                'default' => 1,
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label' => __('Autoplay', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'autoplay_speed',
            [
                'label' => __('Autoplay Speed (ms)', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'default' => 3000,
                'condition' => [
                    'autoplay' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'loop',
            [
                'label' => __('Loop', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_navigation',
            [
                'label' => __('Show Navigation', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_pagination',
            [
                'label' => __('Show Pagination', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_responsive_control(
            'space_between',
            [
                'label' => __('Space Between', 'gps-courses'),
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
                    'unit' => 'px',
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
                'default' => 15,
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
                'default' => 'no',
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
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $query = $this->get_events_query($settings);

        if (!$query->have_posts()) {
            echo '<p>' . __('No events found.', 'gps-courses') . '</p>';
            return;
        }

        $slider_id = 'gps-slider-' . $this->get_id();

        // Slider settings
        $slider_settings = [
            'slidesPerView' => $settings['slides_to_show_mobile'] ?? 1,
            'spaceBetween' => $settings['space_between']['size'] ?? 30,
            'loop' => $settings['loop'] === 'yes',
            'autoplay' => $settings['autoplay'] === 'yes' ? [
                'delay' => $settings['autoplay_speed'] ?? 3000,
                'disableOnInteraction' => false,
            ] : false,
            'navigation' => $settings['show_navigation'] === 'yes' ? [
                'nextEl' => '.swiper-button-next',
                'prevEl' => '.swiper-button-prev',
            ] : false,
            'pagination' => $settings['show_pagination'] === 'yes' ? [
                'el' => '.swiper-pagination',
                'clickable' => true,
            ] : false,
            'breakpoints' => [
                768 => [
                    'slidesPerView' => $settings['slides_to_show_tablet'] ?? 2,
                ],
                1024 => [
                    'slidesPerView' => $settings['slides_to_show'] ?? 3,
                ],
            ],
        ];

        echo '<div class="gps-event-slider-wrapper">';
        echo '<div class="swiper ' . esc_attr($slider_id) . '" data-settings="' . esc_attr(wp_json_encode($slider_settings)) . '">';
        echo '<div class="swiper-wrapper">';

        while ($query->have_posts()) {
            $query->the_post();
            $event_id = get_the_ID();

            echo '<div class="swiper-slide">';
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
                $excerpt_length = $settings['excerpt_length'] ?? 15;
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
            echo '</div>'; // .swiper-slide
        }

        echo '</div>'; // .swiper-wrapper

        // Navigation
        if ($settings['show_navigation'] === 'yes') {
            echo '<div class="swiper-button-prev"></div>';
            echo '<div class="swiper-button-next"></div>';
        }

        // Pagination
        if ($settings['show_pagination'] === 'yes') {
            echo '<div class="swiper-pagination"></div>';
        }

        echo '</div>'; // .swiper
        echo '</div>'; // .gps-event-slider-wrapper

        wp_reset_postdata();

        // Initialize Swiper
        ?>
        <script>
        jQuery(document).ready(function($) {
            if (typeof Swiper !== 'undefined') {
                var settings = <?php echo wp_json_encode($slider_settings); ?>;
                new Swiper('.<?php echo esc_js($slider_id); ?>', settings);
            }
        });
        </script>
        <?php
    }
}
