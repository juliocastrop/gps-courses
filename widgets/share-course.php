<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Share Course Widget
 * Professional social sharing with modal popup
 */
class Share_Course_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-share-course';
    }

    public function get_title() {
        return __('GPS Share Course', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-share';
    }

    public function get_script_depends() {
        return ['gps-courses-share'];
    }

    public function get_style_depends() {
        return ['gps-courses-share'];
    }

    protected function register_controls() {

        // ===== CONTENT SECTION =====
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Share Settings', 'gps-courses'),
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Share This Course', 'gps-courses'),
            ]
        );

        $this->add_control(
            'button_icon',
            [
                'label' => __('Button Icon', 'gps-courses'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-share-alt',
                    'library' => 'fa-solid',
                ],
                'recommended' => [
                    'fa-solid' => [
                        'share-alt',
                        'share',
                        'share-nodes',
                        'share-square',
                        'external-link-alt',
                        'link',
                    ],
                    'fa-regular' => [
                        'share-from-square',
                    ],
                ],
            ]
        );

        $this->add_control(
            'show_button_icon',
            [
                'label' => __('Show Icon', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'modal_title',
            [
                'label' => __('Modal Title', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Share This Course', 'gps-courses'),
            ]
        );

        $this->add_control(
            'share_platforms',
            [
                'label' => __('Share Platforms', 'gps-courses'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'facebook' => __('Facebook', 'gps-courses'),
                    'twitter' => __('Twitter (X)', 'gps-courses'),
                    'linkedin' => __('LinkedIn', 'gps-courses'),
                    'whatsapp' => __('WhatsApp', 'gps-courses'),
                    'email' => __('Email', 'gps-courses'),
                    'copy' => __('Copy Link', 'gps-courses'),
                ],
                'default' => ['facebook', 'twitter', 'linkedin', 'whatsapp', 'email', 'copy'],
            ]
        );

        $this->add_control(
            'button_alignment',
            [
                'label' => __('Button Alignment', 'gps-courses'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'gps-courses'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'gps-courses'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'gps-courses'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-course-wrapper' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // ===== BUTTON STYLE =====
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => __('Button', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background',
            [
                'label' => __('Hover Background', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#135e96',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_color',
            [
                'label' => __('Hover Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .gps-share-button',
            ]
        );

        // Button Icon
        $this->add_control(
            'button_icon_heading',
            [
                'label' => __('Button Icon', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'button_icon_size',
            [
                'label' => __('Icon Size', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'size' => 18,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-share-button-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gps-share-button-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'button_icon_spacing',
            [
                'label' => __('Icon Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'size' => 8,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-share-button' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'button_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => '12',
                    'right' => '24',
                    'bottom' => '12',
                    'left' => '24',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-share-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'button_border_radius',
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
                    '{{WRAPPER}} .gps-share-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_shadow',
                'selector' => '{{WRAPPER}} .gps-share-button',
            ]
        );

        $this->end_controls_section();

        // ===== MODAL STYLE =====
        $this->start_controls_section(
            'section_modal_style',
            [
                'label' => __('Modal', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'modal_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-modal-content' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'modal_overlay_color',
            [
                'label' => __('Overlay Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => 'rgba(0, 0, 0, 0.75)',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-modal' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'modal_width',
            [
                'label' => __('Width', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 300,
                        'max' => 800,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 500,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-share-modal-content' => 'max-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'modal_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => '12',
                    'right' => '12',
                    'bottom' => '12',
                    'left' => '12',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-share-modal-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'modal_shadow',
                'selector' => '{{WRAPPER}} .gps-share-modal-content',
            ]
        );

        $this->end_controls_section();

        // ===== SHARE ICONS STYLE =====
        $this->start_controls_section(
            'section_icons_style',
            [
                'label' => __('Share Icons', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'icon_size',
            [
                'label' => __('Icon Size', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 60,
                    ],
                ],
                'default' => [
                    'size' => 40,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-share-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: calc({{SIZE}}{{UNIT}} * 0.5);',
                ],
            ]
        );

        $this->add_control(
            'icon_spacing',
            [
                'label' => __('Icon Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 5,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-share-icons' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'icon_border_radius',
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
                    '{{WRAPPER}} .gps-share-icon' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Custom Icon Colors
        $this->add_control(
            'custom_icon_colors_heading',
            [
                'label' => __('Custom Icon Colors', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'enable_custom_colors',
            [
                'label' => __('Enable Custom Colors', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Enable to override default platform colors', 'gps-courses'),
            ]
        );

        $this->add_control(
            'facebook_color',
            [
                'label' => __('Facebook Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#1877f2',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-facebook' => 'background-color: {{VALUE}} !important;',
                ],
                'condition' => [
                    'enable_custom_colors' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'twitter_color',
            [
                'label' => __('Twitter Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#1da1f2',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-twitter' => 'background-color: {{VALUE}} !important;',
                ],
                'condition' => [
                    'enable_custom_colors' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'linkedin_color',
            [
                'label' => __('LinkedIn Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#0a66c2',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-linkedin' => 'background-color: {{VALUE}} !important;',
                ],
                'condition' => [
                    'enable_custom_colors' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'whatsapp_color',
            [
                'label' => __('WhatsApp Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#25d366',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-whatsapp' => 'background-color: {{VALUE}} !important;',
                ],
                'condition' => [
                    'enable_custom_colors' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'email_color',
            [
                'label' => __('Email Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-email' => 'background-color: {{VALUE}} !important;',
                ],
                'condition' => [
                    'enable_custom_colors' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'icon_text_color',
            [
                'label' => __('Icon Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-share-icon' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        if (!$post_id) {
            return;
        }

        $post_title = get_the_title($post_id);
        $post_url = get_permalink($post_id);
        $post_excerpt = wp_trim_words(get_the_excerpt($post_id), 20);

        ?>
        <div class="gps-share-course-wrapper">
            <button class="gps-share-button" data-post-id="<?php echo esc_attr($post_id); ?>">
                <?php if ($settings['show_button_icon'] === 'yes' && !empty($settings['button_icon']['value'])): ?>
                    <span class="gps-share-button-icon">
                        <?php \Elementor\Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']); ?>
                    </span>
                <?php endif; ?>
                <span class="gps-share-button-text"><?php echo esc_html($settings['button_text']); ?></span>
            </button>

            <!-- Share Modal -->
            <div class="gps-share-modal" style="display: none;">
                <div class="gps-share-modal-content">
                    <button class="gps-share-modal-close" aria-label="<?php esc_attr_e('Close', 'gps-courses'); ?>">
                        <span>&times;</span>
                    </button>

                    <div class="gps-share-modal-header">
                        <h3><?php echo esc_html($settings['modal_title']); ?></h3>
                        <p class="gps-share-course-title"><?php echo esc_html($post_title); ?></p>
                    </div>

                    <div class="gps-share-modal-body">
                        <div class="gps-share-icons">
                            <?php
                            $platforms = $settings['share_platforms'];
                            $share_data = [
                                'url' => $post_url,
                                'title' => $post_title,
                                'excerpt' => $post_excerpt,
                            ];

                            foreach ($platforms as $platform) {
                                $this->render_share_icon($platform, $share_data);
                            }
                            ?>
                        </div>

                        <?php if (in_array('copy', $platforms)): ?>
                        <div class="gps-share-url-copy">
                            <input type="text" readonly value="<?php echo esc_url($post_url); ?>" class="gps-share-url-input">
                            <button class="gps-copy-url-button" data-url="<?php echo esc_url($post_url); ?>">
                                <?php esc_html_e('Copy', 'gps-courses'); ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_share_icon($platform, $data) {
        if ($platform === 'copy') {
            return; // Handled separately in the copy URL section
        }

        $icons = [
            'facebook' => 'fab fa-facebook-f',
            'twitter' => 'fab fa-twitter',
            'linkedin' => 'fab fa-linkedin-in',
            'whatsapp' => 'fab fa-whatsapp',
            'email' => 'fas fa-envelope',
        ];

        $labels = [
            'facebook' => __('Facebook', 'gps-courses'),
            'twitter' => __('Twitter', 'gps-courses'),
            'linkedin' => __('LinkedIn', 'gps-courses'),
            'whatsapp' => __('WhatsApp', 'gps-courses'),
            'email' => __('Email', 'gps-courses'),
        ];

        $url = $data['url'];
        $title = rawurlencode($data['title']);
        $excerpt = rawurlencode($data['excerpt']);

        $share_urls = [
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($url),
            'twitter' => "https://twitter.com/intent/tweet?url=" . urlencode($url) . "&text=" . $title,
            'linkedin' => "https://www.linkedin.com/shareArticle?mini=true&url=" . urlencode($url) . "&title=" . $title,
            'whatsapp' => "https://wa.me/?text=" . $title . "%20" . urlencode($url),
            'email' => "mailto:?subject=" . $title . "&body=" . $excerpt . "%0A%0A" . urlencode($url),
        ];

        ?>
        <a href="<?php echo esc_url($share_urls[$platform]); ?>"
           class="gps-share-icon gps-share-<?php echo esc_attr($platform); ?>"
           data-platform="<?php echo esc_attr($platform); ?>"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="<?php echo esc_attr(sprintf(__('Share on %s', 'gps-courses'), $labels[$platform])); ?>">
            <i class="<?php echo esc_attr($icons[$platform]); ?>"></i>
        </a>
        <?php
    }
}
