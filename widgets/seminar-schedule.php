<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use GPSC\Seminars;

/**
 * Seminar Schedule Widget
 * Displays the 10-session schedule for a seminar
 */
class Seminar_Schedule_Widget extends Base_Widget {

    public function get_name() {
        return 'seminar-schedule';
    }

    public function get_title() {
        return __('Seminar Schedule', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-table-of-contents';
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'gps-courses'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'seminar_id',
            [
                'label' => __('Select Seminar', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_seminars_list(),
                'default' => '',
                'description' => __('Select which seminar schedule to display', 'gps-courses'),
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Layout', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'timeline' => __('Timeline', 'gps-courses'),
                    'list' => __('List', 'gps-courses'),
                    'cards' => __('Cards', 'gps-courses'),
                ],
                'default' => 'timeline',
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __('Show Description', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'gps-courses'),
                'label_off' => __('Hide', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_time',
            [
                'label' => __('Show Time', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'gps-courses'),
                'label_off' => __('Hide', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'timeline_color',
            [
                'label' => __('Timeline Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-timeline::before' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .gps-schedule-item::before' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'layout' => 'timeline',
                ],
            ]
        );

        $this->add_control(
            'completed_color',
            [
                'label' => __('Completed Session Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#46b450',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-item.completed .gps-session-number' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'upcoming_color',
            [
                'label' => __('Upcoming Session Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-item.upcoming .gps-session-number' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $seminar_id = $settings['seminar_id'];

        if (!$seminar_id) {
            echo '<p>' . __('Please select a seminar in the widget settings.', 'gps-courses') . '</p>';
            return;
        }

        $seminar = get_post($seminar_id);
        if (!$seminar) {
            echo '<p>' . __('Seminar not found.', 'gps-courses') . '</p>';
            return;
        }

        $sessions = Seminars::get_sessions($seminar_id);

        if (empty($sessions)) {
            echo '<p>' . __('No sessions scheduled yet.', 'gps-courses') . '</p>';
            return;
        }

        $layout = $settings['layout'];
        $today = current_time('Y-m-d');

        ?>
        <div class="gps-seminar-schedule-widget layout-<?php echo esc_attr($layout); ?>">
            <div class="gps-schedule-header">
                <h3><?php echo esc_html($seminar->post_title); ?></h3>
                <p class="gps-schedule-subtitle"><?php printf(__('%d Sessions | 2 CE Credits per Session', 'gps-courses'), count($sessions)); ?></p>
            </div>

            <div class="gps-schedule-<?php echo esc_attr($layout); ?>">
                <?php foreach ($sessions as $session):
                    $session_date = $session->session_date;
                    $is_completed = strtotime($session_date) < strtotime($today);
                    $is_upcoming = strtotime($session_date) >= strtotime($today);
                    $status_class = $is_completed ? 'completed' : 'upcoming';
                ?>
                    <div class="gps-schedule-item <?php echo $status_class; ?>">
                        <div class="gps-session-number">
                            <?php if ($is_completed): ?>
                                <span class="gps-check-icon">✓</span>
                            <?php endif; ?>
                            <span class="gps-number"><?php echo $session->session_number; ?></span>
                        </div>

                        <div class="gps-session-content">
                            <div class="gps-session-header">
                                <h4 class="gps-session-title"><?php echo esc_html($session->topic); ?></h4>
                                <?php if ($is_completed): ?>
                                    <span class="gps-status-badge completed"><?php _e('Completed', 'gps-courses'); ?></span>
                                <?php elseif ($is_upcoming): ?>
                                    <span class="gps-status-badge upcoming"><?php _e('Upcoming', 'gps-courses'); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="gps-session-meta">
                                <span class="gps-session-date">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M11 2h1a2 2 0 012 2v9a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2h1V1h2v1h4V1h2v1zM4 4v9h8V4H4z"/>
                                    </svg>
                                    <?php echo date('F j, Y', strtotime($session_date)); ?>
                                </span>

                                <?php if ($settings['show_time'] === 'yes'): ?>
                                    <span class="gps-session-time">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                            <path d="M8 1a7 7 0 110 14A7 7 0 018 1zm0 2a5 5 0 100 10A5 5 0 008 3zm0 1v4l3 2-1 1-3-3V4h1z"/>
                                        </svg>
                                        <?php echo date('g:i A', strtotime($session->session_time_start)); ?>
                                        - <?php echo date('g:i A', strtotime($session->session_time_end)); ?>
                                    </span>
                                <?php endif; ?>

                                <span class="gps-session-credits">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M8 1l2 4 4 .5-3 3 1 4-4-2-4 2 1-4-3-3 4-.5 2-4z"/>
                                    </svg>
                                    <?php _e('2 CE Credits', 'gps-courses'); ?>
                                </span>
                            </div>

                            <?php if ($settings['show_description'] === 'yes' && $session->description): ?>
                                <div class="gps-session-description">
                                    <?php echo wpautop(esc_html($session->description)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .gps-seminar-schedule-widget {
                max-width: 900px;
                margin: 0 auto;
            }

            .gps-schedule-header {
                text-align: center;
                margin-bottom: 40px;
            }

            .gps-schedule-header h3 {
                margin: 0 0 10px 0;
                font-size: 32px;
                color: #333;
            }

            .gps-schedule-subtitle {
                color: #666;
                font-size: 16px;
                margin: 0;
            }

            /* Timeline Layout */
            .gps-schedule-timeline {
                position: relative;
                padding-left: 60px;
            }

            .gps-schedule-timeline::before {
                content: '';
                position: absolute;
                left: 25px;
                top: 0;
                bottom: 0;
                width: 3px;
                background: #e0e0e0;
            }

            .layout-timeline .gps-schedule-item {
                position: relative;
                margin-bottom: 40px;
            }

            .layout-timeline .gps-schedule-item::before {
                content: '';
                position: absolute;
                left: -47px;
                top: 12px;
                width: 15px;
                height: 15px;
                border-radius: 50%;
                background: #2271b1;
                border: 3px solid #fff;
                box-shadow: 0 0 0 3px #e0e0e0;
            }

            .layout-timeline .gps-schedule-item.completed::before {
                background: #46b450;
            }

            /* List Layout */
            .gps-schedule-list {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            /* Cards Layout */
            .gps-schedule-cards {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 25px;
            }

            .layout-cards .gps-schedule-item {
                border: 1px solid #e0e0e0;
                border-radius: 12px;
                overflow: hidden;
                transition: all 0.3s ease;
            }

            .layout-cards .gps-schedule-item:hover {
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
                transform: translateY(-4px);
            }

            /* Common Styles */
            .gps-schedule-item {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 25px;
                display: flex;
                gap: 20px;
            }

            .gps-session-number {
                flex-shrink: 0;
                width: 60px;
                height: 60px;
                background: #2271b1;
                color: #fff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                font-weight: 700;
                position: relative;
            }

            .gps-schedule-item.completed .gps-session-number {
                background: #46b450;
            }

            .gps-check-icon {
                position: absolute;
                top: -5px;
                right: -5px;
                background: #fff;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
                color: #46b450;
                font-weight: 700;
            }

            .gps-session-content {
                flex: 1;
            }

            .gps-session-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 12px;
            }

            .gps-session-title {
                margin: 0;
                font-size: 20px;
                color: #333;
                font-weight: 600;
            }

            .gps-status-badge {
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }

            .gps-status-badge.completed {
                background: #d4edda;
                color: #155724;
            }

            .gps-status-badge.upcoming {
                background: #e5f5fa;
                color: #00527c;
            }

            .gps-session-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 15px;
                color: #666;
                font-size: 14px;
            }

            .gps-session-meta > span {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .gps-session-meta svg {
                flex-shrink: 0;
                opacity: 0.7;
            }

            .gps-session-description {
                color: #666;
                line-height: 1.6;
                font-size: 14px;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
            }

            @media (max-width: 768px) {
                .gps-schedule-timeline {
                    padding-left: 40px;
                }

                .layout-timeline .gps-schedule-item::before {
                    left: -35px;
                }

                .gps-schedule-cards {
                    grid-template-columns: 1fr;
                }

                .gps-schedule-item {
                    flex-direction: column;
                }

                .gps-session-header {
                    flex-direction: column;
                    gap: 10px;
                }

                .gps-session-meta {
                    flex-direction: column;
                    gap: 8px;
                }
            }
        </style>
        <?php
    }

    private function get_seminars_list() {
        $options = [];

        $seminars = get_posts([
            'post_type' => 'gps_seminar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        foreach ($seminars as $seminar) {
            $year = get_post_meta($seminar->ID, '_gps_seminar_year', true);
            $options[$seminar->ID] = $seminar->post_title . ' (' . $year . ')';
        }

        return $options;
    }
}
