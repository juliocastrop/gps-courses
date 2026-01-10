<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Add to Calendar Widget
 * Allows users to add events to Google, Yahoo, Outlook, Apple Calendar
 */
class Add_To_Calendar_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-add-to-calendar';
    }

    public function get_title() {
        return __('GPS Add to Calendar', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_script_depends() {
        return ['gps-courses-add-calendar'];
    }

    public function get_style_depends() {
        return ['gps-courses-add-calendar'];
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
            'button_text',
            [
                'label' => __('Button Text', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Add to Calendar', 'gps-courses'),
            ]
        );

        $this->add_control(
            'button_icon',
            [
                'label' => __('Button Icon', 'gps-courses'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'far fa-calendar-plus',
                    'library' => 'fa-regular',
                ],
                'recommended' => [
                    'fa-solid' => [
                        'calendar-plus',
                        'calendar',
                        'calendar-day',
                        'calendar-check',
                        'calendar-alt',
                    ],
                    'fa-regular' => [
                        'calendar-plus',
                        'calendar',
                        'calendar-check',
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
                'default' => __('Add to Your Calendar', 'gps-courses'),
            ]
        );

        $this->add_control(
            'calendar_services',
            [
                'label' => __('Calendar Services', 'gps-courses'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'google' => __('Google Calendar', 'gps-courses'),
                    'yahoo' => __('Yahoo Calendar', 'gps-courses'),
                    'outlook' => __('Outlook', 'gps-courses'),
                    'outlookcom' => __('Outlook.com', 'gps-courses'),
                    'apple' => __('Apple Calendar', 'gps-courses'),
                ],
                'default' => ['google', 'yahoo', 'outlook', 'outlookcom', 'apple'],
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
                    '{{WRAPPER}} .gps-add-calendar-wrapper' => 'text-align: {{VALUE}};',
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
                'default' => '#5b6cf9',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background',
            [
                'label' => __('Hover Background', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#4a5bd9',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-button:hover' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .gps-calendar-button' => 'color: {{VALUE}};',
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
                    '{{WRAPPER}} .gps-calendar-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .gps-calendar-button',
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
                    '{{WRAPPER}} .gps-calendar-button-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gps-calendar-button-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-calendar-button' => 'gap: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-calendar-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-calendar-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_shadow',
                'selector' => '{{WRAPPER}} .gps-calendar-button',
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
                    '{{WRAPPER}} .gps-calendar-modal-content' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .gps-calendar-modal' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .gps-calendar-modal-content' => 'max-width: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-calendar-modal-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'modal_shadow',
                'selector' => '{{WRAPPER}} .gps-calendar-modal-content',
            ]
        );

        $this->end_controls_section();

        // ===== CALENDAR OPTIONS STYLE =====
        $this->start_controls_section(
            'section_options_style',
            [
                'label' => __('Calendar Options', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'option_background',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f9fafb',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-option' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'option_hover_background',
            [
                'label' => __('Hover Background', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e0f2ff',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-option:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'option_text_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#1a1a1a',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-option' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'option_typography',
                'selector' => '{{WRAPPER}} .gps-calendar-option',
            ]
        );

        $this->add_control(
            'option_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'default' => [
                    'top' => '8',
                    'right' => '8',
                    'bottom' => '8',
                    'left' => '8',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-option' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // Option Icons
        $this->add_control(
            'option_icon_heading',
            [
                'label' => __('Option Icons', 'gps-courses'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'option_icon_size',
            [
                'label' => __('Icon Size', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 16,
                        'max' => 40,
                    ],
                ],
                'default' => [
                    'size' => 24,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-option i' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'option_icon_color',
            [
                'label' => __('Icon Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#5b6cf9',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-option i' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'option_hover_icon_color',
            [
                'label' => __('Hover Icon Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#4a5bd9',
                'selectors' => [
                    '{{WRAPPER}} .gps-calendar-option:hover i' => 'color: {{VALUE}};',
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

        // Get event data
        $title = get_the_title($post_id);
        $description = wp_trim_words(get_the_excerpt($post_id), 30);
        $location = get_post_meta($post_id, '_gps_venue', true) ?: get_post_meta($post_id, '_gps_location', true);
        $start_date = get_post_meta($post_id, '_gps_start_date', true);
        $end_date = get_post_meta($post_id, '_gps_end_date', true);
        $start_time = get_post_meta($post_id, '_gps_start_time', true);
        $end_time = get_post_meta($post_id, '_gps_end_time', true);

        // Format dates for calendar
        $start_datetime = $this->format_datetime($start_date, $start_time);
        $end_datetime = $this->format_datetime($end_date ?: $start_date, $end_time ?: $start_time);

        ?>
        <div class="gps-add-calendar-wrapper">
            <button class="gps-calendar-button"
                    data-title="<?php echo esc_attr($title); ?>"
                    data-description="<?php echo esc_attr($description); ?>"
                    data-location="<?php echo esc_attr($location); ?>"
                    data-start="<?php echo esc_attr($start_datetime); ?>"
                    data-end="<?php echo esc_attr($end_datetime); ?>">
                <?php if ($settings['show_button_icon'] === 'yes' && !empty($settings['button_icon']['value'])): ?>
                    <span class="gps-calendar-button-icon">
                        <?php \Elementor\Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']); ?>
                    </span>
                <?php endif; ?>
                <span class="gps-calendar-button-text"><?php echo esc_html($settings['button_text']); ?></span>
            </button>

            <!-- Calendar Modal -->
            <div class="gps-calendar-modal" style="display: none;">
                <div class="gps-calendar-modal-content">
                    <button class="gps-calendar-modal-close" aria-label="<?php esc_attr_e('Close', 'gps-courses'); ?>">
                        <span>&times;</span>
                    </button>

                    <div class="gps-calendar-modal-header">
                        <h3><?php echo esc_html($settings['modal_title']); ?></h3>
                        <p class="gps-calendar-event-title"><?php echo esc_html($title); ?></p>
                    </div>

                    <div class="gps-calendar-modal-body">
                        <div class="gps-calendar-options">
                            <?php
                            $services = $settings['calendar_services'];
                            foreach ($services as $service) {
                                $this->render_calendar_option($service);
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_calendar_option($service) {
        $icons = [
            'google' => 'fab fa-google',
            'yahoo' => 'fab fa-yahoo',
            'outlook' => 'fab fa-microsoft',
            'outlookcom' => 'fab fa-microsoft',
            'apple' => 'fab fa-apple',
        ];

        $labels = [
            'google' => __('Google Calendar', 'gps-courses'),
            'yahoo' => __('Yahoo Calendar', 'gps-courses'),
            'outlook' => __('Outlook', 'gps-courses'),
            'outlookcom' => __('Outlook.com', 'gps-courses'),
            'apple' => __('Apple Calendar', 'gps-courses'),
        ];

        ?>
        <button class="gps-calendar-option" data-service="<?php echo esc_attr($service); ?>">
            <i class="<?php echo esc_attr($icons[$service]); ?>"></i>
            <span><?php echo esc_html($labels[$service]); ?></span>
        </button>
        <?php
    }

    private function format_datetime($date, $time) {
        if (empty($date)) {
            return '';
        }

        $datetime = $date;
        if (!empty($time)) {
            $datetime .= ' ' . $time;
        }

        return date('Y-m-d\TH:i:s', strtotime($datetime));
    }
}
