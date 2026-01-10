<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Speaker Grid Widget
 */
class Speaker_Grid_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-speaker-grid';
    }

    public function get_title() {
        return __('Speaker Grid', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    protected function register_controls() {
        // Query Section
        $this->start_controls_section(
            'section_query',
            [
                'label' => __('Query', 'gps-courses'),
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => __('Speakers Per Page', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'min' => 1,
                'max' => 100,
            ]
        );

        $this->add_control(
            'order_by',
            [
                'label' => __('Order By', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'title',
                'options' => [
                    'title' => __('Name', 'gps-courses'),
                    'date' => __('Date Added', 'gps-courses'),
                    'rand' => __('Random', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __('Order', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => [
                    'ASC' => __('Ascending', 'gps-courses'),
                    'DESC' => __('Descending', 'gps-courses'),
                ],
            ]
        );

        $this->end_controls_section();

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
                    '{{WRAPPER}} .gps-speaker-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
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
                    '{{WRAPPER}} .gps-speaker-grid' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-speaker-grid' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'show_photo',
            [
                'label' => __('Show Photo', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_designation',
            [
                'label' => __('Show Designation', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_company',
            [
                'label' => __('Show Company', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_bio',
            [
                'label' => __('Show Bio', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'bio_length',
            [
                'label' => __('Bio Length (words)', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'default' => 20,
                'condition' => [
                    'show_bio' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_social',
            [
                'label' => __('Show Social Links', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
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

        $this->add_responsive_control(
            'item_padding',
            [
                'label' => __('Item Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .gps-speaker-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'item_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-speaker-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'item_border',
                'selector' => '{{WRAPPER}} .gps-speaker-item',
            ]
        );

        $this->add_control(
            'item_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-speaker-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $args = [
            'post_type' => 'gps_speaker',
            'post_status' => 'publish',
            'posts_per_page' => $settings['posts_per_page'],
            'orderby' => $settings['order_by'],
            'order' => $settings['order'],
        ];

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            echo '<p>' . __('No speakers found.', 'gps-courses') . '</p>';
            return;
        }

        echo '<div class="gps-speaker-grid">';

        while ($query->have_posts()) {
            $query->the_post();
            $speaker_id = get_the_ID();

            $designation = get_post_meta($speaker_id, '_gps_designation', true);
            $company = get_post_meta($speaker_id, '_gps_company', true);
            $twitter = get_post_meta($speaker_id, '_gps_social_twitter', true);
            $linkedin = get_post_meta($speaker_id, '_gps_social_linkedin', true);
            $facebook = get_post_meta($speaker_id, '_gps_social_facebook', true);

            echo '<div class="gps-speaker-item">';

            // Photo
            if ($settings['show_photo'] === 'yes') {
                echo '<div class="gps-speaker-photo">';
                if (has_post_thumbnail()) {
                    the_post_thumbnail('medium');
                } else {
                    echo '<div class="gps-no-photo"><i class="fas fa-user"></i></div>';
                }
                echo '</div>';
            }

            echo '<div class="gps-speaker-content">';

            // Name
            echo '<h3 class="gps-speaker-name">' . esc_html(get_the_title()) . '</h3>';

            // Designation
            if ($settings['show_designation'] === 'yes' && $designation) {
                echo '<p class="gps-speaker-designation">' . esc_html($designation) . '</p>';
            }

            // Company
            if ($settings['show_company'] === 'yes' && $company) {
                echo '<p class="gps-speaker-company">' . esc_html($company) . '</p>';
            }

            // Bio
            if ($settings['show_bio'] === 'yes') {
                $bio_length = $settings['bio_length'] ?? 20;
                $bio = wp_trim_words(get_the_content(), $bio_length, '...');
                if ($bio) {
                    echo '<div class="gps-speaker-bio">' . wp_kses_post($bio) . '</div>';
                }
            }

            // Social Links
            if ($settings['show_social'] === 'yes') {
                $has_social = $twitter || $linkedin || $facebook;

                if ($has_social) {
                    echo '<div class="gps-speaker-social">';

                    if ($twitter) {
                        echo '<a href="' . esc_url($twitter) . '" target="_blank" rel="noopener" class="gps-social-link twitter">';
                        echo '<i class="fab fa-twitter"></i>';
                        echo '</a>';
                    }

                    if ($linkedin) {
                        echo '<a href="' . esc_url($linkedin) . '" target="_blank" rel="noopener" class="gps-social-link linkedin">';
                        echo '<i class="fab fa-linkedin"></i>';
                        echo '</a>';
                    }

                    if ($facebook) {
                        echo '<a href="' . esc_url($facebook) . '" target="_blank" rel="noopener" class="gps-social-link facebook">';
                        echo '<i class="fab fa-facebook"></i>';
                        echo '</a>';
                    }

                    echo '</div>';
                }
            }

            echo '</div>'; // .gps-speaker-content
            echo '</div>'; // .gps-speaker-item
        }

        echo '</div>'; // .gps-speaker-grid

        wp_reset_postdata();
    }
}
