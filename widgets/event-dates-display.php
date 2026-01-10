<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * Event Dates Display Widget
 */
class Event_Dates_Display_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-event-dates-display';
    }

    public function get_title() {
        return __('Event Dates Display', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_style_depends() {
        return ['elementor-icons-fa-solid', 'elementor-icons-fa-regular', 'elementor-icons-fa-brands'];
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Settings', 'gps-courses'),
            ]
        );

        $this->add_control(
            'show_icon',
            [
                'label' => __('Show Icon', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'icon',
            [
                'label' => __('Icon', 'gps-courses'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-calendar-alt',
                    'library' => 'solid',
                ],
                'condition' => [
                    'show_icon' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'label_text',
            [
                'label' => __('Label Text', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Event Date:', 'gps-courses'),
            ]
        );

        $this->add_control(
            'date_format',
            [
                'label' => __('Date Format', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'M d, Y',
                'options' => [
                    'M d, Y' => __('Nov 03, 2025', 'gps-courses'),
                    'F j, Y' => __('November 3, 2025', 'gps-courses'),
                    'd/m/Y' => __('03/11/2025', 'gps-courses'),
                    'm/d/Y' => __('11/03/2025', 'gps-courses'),
                    'Y-m-d' => __('2025-11-03', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'show_time',
            [
                'label' => __('Show Time', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
            ]
        );

        $this->add_responsive_control(
            'alignment',
            [
                'label' => __('Alignment', 'gps-courses'),
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
                'selectors_dictionary' => [
                    'left' => 'flex-start',
                    'center' => 'center',
                    'right' => 'flex-end',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-dates-box' => 'display: flex; justify-content: {{VALUE}}; align-items: center; flex-wrap: nowrap; gap: 10px;',
                    '{{WRAPPER}} .gps-dates-icon' => 'display: inline-flex; flex-shrink: 0;',
                    '{{WRAPPER}} .gps-dates-content' => 'display: inline-flex; align-items: baseline; gap: 5px; white-space: nowrap;',
                    '{{WRAPPER}} .gps-dates-label' => 'white-space: nowrap;',
                    '{{WRAPPER}} .gps-dates-text' => 'white-space: nowrap;',
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

        $this->add_control(
            'box_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-event-dates-box' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'date_typography',
                'label' => __('Date Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .gps-dates-text',
            ]
        );

        $this->add_control(
            'date_color',
            [
                'label' => __('Date Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-dates-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'date_spacing',
            [
                'label' => __('Date Spacing', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .gps-dates-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'label' => __('Label Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .gps-dates-label',
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Label Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-dates-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'box_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-dates-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'box_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-dates-box' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Icon
        $this->start_controls_section(
            'section_icon_style',
            [
                'label' => __('Icon', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_icon' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'icon_size',
            [
                'label' => __('Icon Size', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 200,
                    ],
                ],
                'default' => [
                    'size' => 48,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-dates-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gps-dates-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gps-dates-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label' => __('Icon Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-dates-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .gps-dates-icon i' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .gps-dates-icon svg' => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'icon_spacing',
            [
                'label' => __('Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-dates-icon' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $event_id = get_the_ID();

        if (!$event_id || get_post_type($event_id) !== 'gps_event') {
            echo '<p>' . __('This widget must be used on an event page.', 'gps-courses') . '</p>';
            return;
        }

        // Get event dates
        $start_date = get_post_meta($event_id, '_gps_start_date', true);
        $end_date = get_post_meta($event_id, '_gps_end_date', true);
        $start_time = get_post_meta($event_id, '_gps_start_time', true);
        $end_time = get_post_meta($event_id, '_gps_end_time', true);

        if (empty($start_date)) {
            echo '<p>' . __('No event date set.', 'gps-courses') . '</p>';
            return;
        }

        // Format dates
        $date_format = $settings['date_format'];
        $formatted_date = $this->format_event_dates($start_date, $end_date, $date_format);

        // Add time if requested
        if ($settings['show_time'] === 'yes' && !empty($start_time)) {
            $formatted_date .= ' ' . date('g:i A', strtotime($start_time));
            if (!empty($end_time)) {
                $formatted_date .= ' - ' . date('g:i A', strtotime($end_time));
            }
        }

        ?>
        <div class="gps-event-dates-box">
            <?php if ($settings['show_icon'] === 'yes' && !empty($settings['icon']['value'])): ?>
            <div class="gps-dates-icon">
                <?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']); ?>
            </div>
            <?php endif; ?>

            <div class="gps-dates-content">
                <?php if (!empty($settings['label_text'])): ?>
                <div class="gps-dates-label"><?php echo esc_html($settings['label_text']); ?></div>
                <?php endif; ?>

                <div class="gps-dates-text"><?php echo esc_html($formatted_date); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Format event dates
     * Returns formatted string like "Nov 03, 2025" or "Nov 03 - 05, 2025"
     */
    private function format_event_dates($start_date, $end_date, $format) {
        $start_timestamp = strtotime($start_date);

        // If no end date or same as start date, return single date
        if (empty($end_date) || $start_date === $end_date) {
            return date($format, $start_timestamp);
        }

        $end_timestamp = strtotime($end_date);

        // Check if dates are in the same month and year
        $start_month = date('m', $start_timestamp);
        $start_year = date('Y', $start_timestamp);
        $end_month = date('m', $end_timestamp);
        $end_year = date('Y', $end_timestamp);

        // Same month and year: "Nov 03 - 05, 2025"
        if ($start_month === $end_month && $start_year === $end_year) {
            switch ($format) {
                case 'M d, Y':
                    return date('M d', $start_timestamp) . ' - ' . date('d, Y', $end_timestamp);
                case 'F j, Y':
                    return date('F j', $start_timestamp) . ' - ' . date('j, Y', $end_timestamp);
                case 'd/m/Y':
                    return date('d', $start_timestamp) . '-' . date('d/m/Y', $end_timestamp);
                case 'm/d/Y':
                    return date('m/d', $start_timestamp) . ' - ' . date('d/Y', $end_timestamp);
                case 'Y-m-d':
                    return date('Y-m-d', $start_timestamp) . ' to ' . date('d', $end_timestamp);
                default:
                    return date($format, $start_timestamp) . ' - ' . date($format, $end_timestamp);
            }
        }

        // Different months or years: show full dates
        return date($format, $start_timestamp) . ' - ' . date($format, $end_timestamp);
    }
}
