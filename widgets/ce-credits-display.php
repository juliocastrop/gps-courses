<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * CE Credits Display Widget
 */
class Ce_Credits_Display_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-ce-credits-display';
    }

    public function get_title() {
        return __('CE Credits Display', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-trophy';
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
            'display_type',
            [
                'label' => __('Display Type', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'total',
                'options' => [
                    'total' => __('Total Credits', 'gps-courses'),
                    'event' => __('Event Credits', 'gps-courses'),
                    'ledger' => __('Full Ledger', 'gps-courses'),
                ],
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
                    'value' => 'fas fa-certificate',
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
                'default' => __('CE Credits:', 'gps-courses'),
                'condition' => [
                    'display_type' => 'total',
                ],
            ]
        );

        $this->add_control(
            'show_progress_bar',
            [
                'label' => __('Show Progress Bar', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
                'condition' => [
                    'display_type' => 'total',
                ],
            ]
        );

        $this->add_control(
            'goal_credits',
            [
                'label' => __('Goal (Credits)', 'gps-courses'),
                'type' => Controls_Manager::NUMBER,
                'default' => 100,
                'condition' => [
                    'show_progress_bar' => 'yes',
                    'display_type' => 'total',
                ],
            ]
        );

        $this->add_control(
            'require_login',
            [
                'label' => __('Require Login', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'login_message',
            [
                'label' => __('Login Message', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Please login to view your CE credits.', 'gps-courses'),
                'condition' => [
                    'require_login' => 'yes',
                ],
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
                    '{{WRAPPER}} .gps-ce-credits-box' => 'display: flex; justify-content: {{VALUE}}; align-items: center; flex-wrap: wrap;',
                    '{{WRAPPER}} .gps-credits-main' => 'display: flex; align-items: center; flex-wrap: nowrap; gap: 10px;',
                    '{{WRAPPER}} .gps-credits-icon' => 'display: inline-flex; flex-shrink: 0;',
                    '{{WRAPPER}} .gps-credits-content' => 'display: inline-flex; align-items: baseline; gap: 5px; white-space: nowrap;',
                    '{{WRAPPER}} .gps-credits-progress' => 'width: 100%; margin-top: 10px;',
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
                    '{{WRAPPER}} .gps-ce-credits-box' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'number_typography',
                'label' => __('Number Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .gps-credits-number',
            ]
        );

        $this->add_control(
            'number_color',
            [
                'label' => __('Number Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-credits-number' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'number_spacing',
            [
                'label' => __('Number Spacing', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .gps-credits-number' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'label' => __('Label Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .gps-credits-label',
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Label Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-credits-label' => 'color: {{VALUE}};',
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
                    '{{WRAPPER}} .gps-ce-credits-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-ce-credits-box' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .gps-credits-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gps-credits-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gps-credits-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label' => __('Icon Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#d4af37',
                'selectors' => [
                    '{{WRAPPER}} .gps-credits-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .gps-credits-icon i' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .gps-credits-icon svg' => 'fill: {{VALUE}};',
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
                    '{{WRAPPER}} .gps-credits-icon' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Check if user is logged in
        if ($settings['require_login'] === 'yes' && !is_user_logged_in()) {
            echo '<div class="gps-ce-credits-login-message">';
            echo '<p>' . esc_html($settings['login_message']) . '</p>';
            echo '</div>';
            return;
        }

        $user_id = get_current_user_id();

        if ($settings['display_type'] === 'total') {
            $this->render_total_credits($user_id, $settings);
        } elseif ($settings['display_type'] === 'event') {
            $this->render_event_credits($settings);
        } else {
            $this->render_ledger($user_id);
        }
    }

    /**
     * Render total credits
     */
    private function render_total_credits($user_id, $settings) {
        $total_credits = \GPSC\Credits::user_total($user_id);

        ?>
        <div class="gps-ce-credits-box gps-credits-total">
            <div class="gps-credits-main">
                <?php if ($settings['show_icon'] === 'yes' && !empty($settings['icon']['value'])): ?>
                <div class="gps-credits-icon">
                    <?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']); ?>
                </div>
                <?php endif; ?>

                <div class="gps-credits-content">
                    <?php if (!empty($settings['label_text'])): ?>
                    <div class="gps-credits-label"><?php echo esc_html($settings['label_text']); ?></div>
                    <?php endif; ?>

                    <div class="gps-credits-number"><?php echo (int) $total_credits; ?></div>
                </div>
            </div>

            <?php if ($settings['show_progress_bar'] === 'yes'): ?>
            <?php
                $goal = (int) $settings['goal_credits'];
                $percentage = $goal > 0 ? min(($total_credits / $goal) * 100, 100) : 0;
            ?>
            <div class="gps-credits-progress">
                <div class="gps-progress-bar">
                    <div class="gps-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                </div>
                <div class="gps-progress-text">
                    <?php echo (int) $total_credits; ?> / <?php echo (int) $goal; ?> <?php _e('Credits', 'gps-courses'); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render event credits
     */
    private function render_event_credits($settings) {
        $event_id = get_the_ID();

        if (!$event_id || get_post_type($event_id) !== 'gps_event') {
            echo '<p>' . __('This widget must be used on an event page.', 'gps-courses') . '</p>';
            return;
        }

        $credits = (int) get_post_meta($event_id, '_gps_ce_credits', true);

        ?>
        <div class="gps-ce-credits-box gps-credits-event">
            <div class="gps-credits-main">
                <?php if ($settings['show_icon'] === 'yes' && !empty($settings['icon']['value'])): ?>
                <div class="gps-credits-icon">
                    <?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']); ?>
                </div>
                <?php endif; ?>

                <div class="gps-credits-content">
                    <div class="gps-credits-label"><?php _e('CE Credits:', 'gps-courses'); ?></div>
                    <div class="gps-credits-number"><?php echo (int) $credits; ?></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render full ledger
     */
    private function render_ledger($user_id) {
        $ledger = \GPSC\Credits::user_ledger($user_id);

        if (empty($ledger)) {
            echo '<div class="gps-ce-credits-box gps-credits-ledger">';
            echo '<p>' . __('No CE credits earned yet.', 'gps-courses') . '</p>';
            echo '</div>';
            return;
        }

        ?>
        <div class="gps-ce-credits-box gps-credits-ledger">
            <h3><?php _e('CE Credits History', 'gps-courses'); ?></h3>

            <table class="gps-ledger-table">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'gps-courses'); ?></th>
                        <th><?php _e('Event', 'gps-courses'); ?></th>
                        <th><?php _e('Credits', 'gps-courses'); ?></th>
                        <th><?php _e('Source', 'gps-courses'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ledger as $entry): ?>
                    <tr>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($entry->awarded_at)); ?></td>
                        <td>
                            <?php
                            $event = get_post($entry->event_id);
                            echo $event ? esc_html($event->post_title) : __('Unknown Event', 'gps-courses');
                            ?>
                        </td>
                        <td><strong><?php echo (int) $entry->credits; ?></strong></td>
                        <td><span class="gps-source-badge"><?php echo esc_html(ucfirst($entry->source)); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="gps-ledger-total">
                <?php _e('Total Credits:', 'gps-courses'); ?>
                <strong><?php echo (int) array_sum(array_column($ledger, 'credits')); ?></strong>
            </div>
        </div>
        <?php
    }
}
