<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Countdown Timer Widget
 */
class Countdown_Timer_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-countdown-timer';
    }

    public function get_title() {
        return __('Countdown Timer', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-countdown';
    }

    public function get_script_depends() {
        return ['gps-courses-countdown'];
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
            'countdown_type',
            [
                'label' => __('Countdown Type', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'event',
                'options' => [
                    'event' => __('Event Start Date', 'gps-courses'),
                    'custom' => __('Custom Date', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'event_id',
            [
                'label' => __('Select Event', 'gps-courses'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_events_list(),
                'default' => '',
                'description' => __('Leave empty to use current event', 'gps-courses'),
                'condition' => [
                    'countdown_type' => 'event',
                ],
            ]
        );

        $this->add_control(
            'custom_date',
            [
                'label' => __('Custom Date', 'gps-courses'),
                'type' => Controls_Manager::DATE_TIME,
                'default' => date('Y-m-d H:i', strtotime('+7 days')),
                'condition' => [
                    'countdown_type' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'layout_style',
            [
                'label' => __('Layout Style', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'block',
                'options' => [
                    'block' => __('Block (Vertical)', 'gps-courses'),
                    'inline' => __('Inline (Horizontal)', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'show_separator',
            [
                'label' => __('Show Separator', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
                'description' => __('Show colon (:) separator between items', 'gps-courses'),
            ]
        );

        $this->add_control(
            'separator_type',
            [
                'label' => __('Separator Type', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'colon',
                'options' => [
                    'colon' => __('Colon (:)', 'gps-courses'),
                    'dot' => __('Dot (•)', 'gps-courses'),
                    'line' => __('Line (|)', 'gps-courses'),
                    'slash' => __('Slash (/)', 'gps-courses'),
                ],
                'condition' => [
                    'show_separator' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_labels',
            [
                'label' => __('Show Labels', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'label_position',
            [
                'label' => __('Label Position', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'below',
                'options' => [
                    'below' => __('Below Number', 'gps-courses'),
                    'above' => __('Above Number', 'gps-courses'),
                ],
                'condition' => [
                    'show_labels' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_days',
            [
                'label' => __('Show Days', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_hours',
            [
                'label' => __('Show Hours', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_minutes',
            [
                'label' => __('Show Minutes', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_seconds',
            [
                'label' => __('Show Seconds', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'hide_seconds_mobile',
            [
                'label' => __('Hide Seconds on Mobile', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
                'description' => __('Hide seconds on mobile devices (768px and below) to save space', 'gps-courses'),
                'condition' => [
                    'show_seconds' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'digits_format',
            [
                'label' => __('Digits Format', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('2 Digits', 'gps-courses'),
                'label_off' => __('Auto', 'gps-courses'),
                'default' => 'yes',
                'description' => __('Show numbers with leading zero (e.g., 05 instead of 5)', 'gps-courses'),
            ]
        );

        $this->add_control(
            'expired_message',
            [
                'label' => __('Expired Message', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Event has started!', 'gps-courses'),
            ]
        );

        $this->end_controls_section();

        // Style Section - Container
        $this->start_controls_section(
            'section_container_style',
            [
                'label' => __('Container', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'container_alignment',
            [
                'label' => __('Alignment', 'gps-courses'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => __('Left', 'gps-courses'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'gps-courses'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'flex-end' => [
                        'title' => __('Right', 'gps-courses'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'items_spacing',
            [
                'label' => __('Items Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Box
        $this->start_controls_section(
            'section_box_style',
            [
                'label' => __('Box Style', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'box_style',
            [
                'label' => __('Box Style', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'filled',
                'options' => [
                    'filled' => __('Filled', 'gps-courses'),
                    'outlined' => __('Outlined', 'gps-courses'),
                    'minimal' => __('Minimal (No Background)', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'box_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2c4266',
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-item' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'box_style' => 'filled',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'box_border',
                'selector' => '{{WRAPPER}} .gps-countdown-item',
                'condition' => [
                    'box_style' => 'outlined',
                ],
            ]
        );

        $this->add_responsive_control(
            'box_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'default' => [
                    'top' => 20,
                    'right' => 20,
                    'bottom' => 20,
                    'left' => 20,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'box_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'box_shadow',
                'selector' => '{{WRAPPER}} .gps-countdown-item',
            ]
        );

        $this->add_responsive_control(
            'box_min_width',
            [
                'label' => __('Min Width', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 300,
                    ],
                ],
                'default' => [
                    'size' => 100,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-item' => 'min-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Numbers
        $this->start_controls_section(
            'section_number_style',
            [
                'label' => __('Numbers', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'number_typography',
                'selector' => '{{WRAPPER}} .gps-countdown-value',
            ]
        );

        $this->add_control(
            'number_color',
            [
                'label' => __('Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#d4af37',
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-value' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'number_spacing',
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
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-value' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'show_labels' => 'yes',
                    'label_position' => 'below',
                ],
            ]
        );

        $this->add_responsive_control(
            'number_spacing_above',
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
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-value' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'show_labels' => 'yes',
                    'label_position' => 'above',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Labels
        $this->start_controls_section(
            'section_label_style',
            [
                'label' => __('Labels', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_labels' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .gps-countdown-label',
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'label_text_transform',
            [
                'label' => __('Text Transform', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'capitalize',
                'options' => [
                    'none' => __('None', 'gps-courses'),
                    'uppercase' => __('Uppercase', 'gps-courses'),
                    'lowercase' => __('Lowercase', 'gps-courses'),
                    'capitalize' => __('Capitalize', 'gps-courses'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-label' => 'text-transform: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Separator
        $this->start_controls_section(
            'section_separator_style',
            [
                'label' => __('Separator', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_separator' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'separator_typography',
                'selector' => '{{WRAPPER}} .gps-countdown-separator',
            ]
        );

        $this->add_control(
            'separator_color',
            [
                'label' => __('Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#d4af37',
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-separator' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Expired Message
        $this->start_controls_section(
            'section_expired_style',
            [
                'label' => __('Expired Message', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'expired_typography',
                'selector' => '{{WRAPPER}} .gps-countdown-expired',
            ]
        );

        $this->add_control(
            'expired_color',
            [
                'label' => __('Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-countdown-expired' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Get target date
        $target_date = '';

        if ($settings['countdown_type'] === 'event') {
            $event_id = !empty($settings['event_id']) ? (int) $settings['event_id'] : get_the_ID();

            if ($event_id && get_post_type($event_id) === 'gps_event') {
                $target_date = get_post_meta($event_id, '_gps_start_date', true);
            }
        } else {
            $target_date = $settings['custom_date'];
        }

        if (empty($target_date)) {
            echo '<p>' . __('Please configure countdown date.', 'gps-courses') . '</p>';
            return;
        }

        $countdown_id = 'gps-countdown-' . $this->get_id();
        $layout_class = 'gps-countdown-' . $settings['layout_style'];
        $box_style_class = 'gps-countdown-style-' . $settings['box_style'];
        $label_position_class = $settings['show_labels'] === 'yes' ? 'label-' . $settings['label_position'] : '';
        // Default to 'yes' for backwards compatibility with existing widgets
        $hide_seconds_mobile = isset($settings['hide_seconds_mobile']) ? $settings['hide_seconds_mobile'] : 'yes';
        $hide_seconds_mobile_class = $hide_seconds_mobile === 'yes' ? 'gps-hide-seconds-mobile' : '';

        $separator_symbol = ':';
        if ($settings['show_separator'] === 'yes') {
            switch ($settings['separator_type']) {
                case 'dot':
                    $separator_symbol = '•';
                    break;
                case 'line':
                    $separator_symbol = '|';
                    break;
                case 'slash':
                    $separator_symbol = '/';
                    break;
                default:
                    $separator_symbol = ':';
            }
        }

        ?>
        <div class="gps-countdown-wrapper <?php echo esc_attr($layout_class); ?> <?php echo esc_attr($box_style_class); ?> <?php echo esc_attr($label_position_class); ?> <?php echo esc_attr($hide_seconds_mobile_class); ?>"
             id="<?php echo esc_attr($countdown_id); ?>"
             data-date="<?php echo esc_attr($target_date); ?>"
             data-expired="<?php echo esc_attr($settings['expired_message']); ?>"
             data-format="<?php echo esc_attr($settings['digits_format']); ?>">
            <div class="gps-countdown">

                <?php if ($settings['show_days'] === 'yes'): ?>
                <div class="gps-countdown-item gps-countdown-days">
                    <?php if ($settings['show_labels'] === 'yes' && $settings['label_position'] === 'above'): ?>
                    <span class="gps-countdown-label"><?php _e('Days', 'gps-courses'); ?></span>
                    <?php endif; ?>
                    <span class="gps-countdown-value" data-days>0</span>
                    <?php if ($settings['show_labels'] === 'yes' && $settings['label_position'] === 'below'): ?>
                    <span class="gps-countdown-label"><?php _e('Days', 'gps-courses'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($settings['show_separator'] === 'yes' && ($settings['show_hours'] === 'yes' || $settings['show_minutes'] === 'yes' || $settings['show_seconds'] === 'yes')): ?>
                <span class="gps-countdown-separator"><?php echo esc_html($separator_symbol); ?></span>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($settings['show_hours'] === 'yes'): ?>
                <div class="gps-countdown-item gps-countdown-hours">
                    <?php if ($settings['show_labels'] === 'yes' && $settings['label_position'] === 'above'): ?>
                    <span class="gps-countdown-label"><?php _e('Hours', 'gps-courses'); ?></span>
                    <?php endif; ?>
                    <span class="gps-countdown-value" data-hours>0</span>
                    <?php if ($settings['show_labels'] === 'yes' && $settings['label_position'] === 'below'): ?>
                    <span class="gps-countdown-label"><?php _e('Hours', 'gps-courses'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($settings['show_separator'] === 'yes' && ($settings['show_minutes'] === 'yes' || $settings['show_seconds'] === 'yes')): ?>
                <span class="gps-countdown-separator"><?php echo esc_html($separator_symbol); ?></span>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($settings['show_minutes'] === 'yes'): ?>
                <div class="gps-countdown-item gps-countdown-minutes">
                    <?php if ($settings['show_labels'] === 'yes' && $settings['label_position'] === 'above'): ?>
                    <span class="gps-countdown-label"><?php _e('Minutes', 'gps-courses'); ?></span>
                    <?php endif; ?>
                    <span class="gps-countdown-value" data-minutes>0</span>
                    <?php if ($settings['show_labels'] === 'yes' && $settings['label_position'] === 'below'): ?>
                    <span class="gps-countdown-label"><?php _e('Minutes', 'gps-courses'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($settings['show_separator'] === 'yes' && $settings['show_seconds'] === 'yes'): ?>
                <span class="gps-countdown-separator"><?php echo esc_html($separator_symbol); ?></span>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($settings['show_seconds'] === 'yes'): ?>
                <div class="gps-countdown-item gps-countdown-seconds">
                    <?php if ($settings['show_labels'] === 'yes' && $settings['label_position'] === 'above'): ?>
                    <span class="gps-countdown-label"><?php _e('Seconds', 'gps-courses'); ?></span>
                    <?php endif; ?>
                    <span class="gps-countdown-value" data-seconds>0</span>
                    <?php if ($settings['show_labels'] === 'yes' && $settings['label_position'] === 'below'): ?>
                    <span class="gps-countdown-label"><?php _e('Seconds', 'gps-courses'); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var countdownEl = $('#<?php echo esc_js($countdown_id); ?>');
            var targetDate = new Date('<?php echo esc_js($target_date); ?>').getTime();
            var expiredMessage = countdownEl.data('expired');
            var useLeadingZero = countdownEl.data('format') === 'yes';

            function pad(num) {
                return useLeadingZero && num < 10 ? '0' + num : num;
            }

            function updateCountdown() {
                var now = new Date().getTime();
                var distance = targetDate - now;

                if (distance < 0) {
                    countdownEl.find('.gps-countdown').html('<div class="gps-countdown-expired">' + expiredMessage + '</div>');
                    return;
                }

                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                countdownEl.find('[data-days]').text(pad(days));
                countdownEl.find('[data-hours]').text(pad(hours));
                countdownEl.find('[data-minutes]').text(pad(minutes));
                countdownEl.find('[data-seconds]').text(pad(seconds));
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        });
        </script>
        <?php
    }

    private function get_events_list() {
        $events = get_posts([
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_gps_start_date',
            'order' => 'ASC',
        ]);

        $options = ['' => __('Current Event', 'gps-courses')];

        foreach ($events as $event) {
            $start_date = get_post_meta($event->ID, '_gps_start_date', true);
            $date_label = $start_date ? ' (' . date_i18n('M j, Y', strtotime($start_date)) . ')' : '';
            $options[$event->ID] = $event->post_title . $date_label;
        }

        return $options;
    }
}
