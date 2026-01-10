<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use GPSC\Seminar_Registrations;

/**
 * Seminar Progress Widget
 * Displays user's progress in their seminar program
 */
class Seminar_Progress_Widget extends Base_Widget {

    public function get_name() {
        return 'seminar-progress';
    }

    public function get_title() {
        return __('Seminar Progress', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-progress-tracker';
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
            'show_qr_code',
            [
                'label' => __('Show QR Code', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'gps-courses'),
                'label_off' => __('Hide', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_attendance_history',
            [
                'label' => __('Show Attendance History', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'gps-courses'),
                'label_off' => __('Hide', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_next_session',
            [
                'label' => __('Show Next Session', 'gps-courses'),
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
            'progress_bar_color',
            [
                'label' => __('Progress Bar Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-progress-fill' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'card_bg_color',
            [
                'label' => __('Card Background', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-progress-card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Check if user is logged in
        if (!is_user_logged_in()) {
            echo '<p>' . __('Please log in to view your seminar progress.', 'gps-courses') . '</p>';
            return;
        }

        $user_id = get_current_user_id();

        // Get user's registrations
        $registrations = Seminar_Registrations::get_user_registrations($user_id);

        if (empty($registrations)) {
            echo '<p>' . __('You are not currently enrolled in any seminars.', 'gps-courses') . '</p>';
            return;
        }

        ?>
        <div class="gps-seminar-progress-widget">
            <?php foreach ($registrations as $registration):
                $progress = Seminar_Registrations::get_user_progress($registration->id);
                $seminar = get_post($registration->seminar_id);
                $percentage = $progress['completion_percentage'];
            ?>
                <div class="gps-progress-card">
                    <div class="gps-progress-header">
                        <h3><?php echo esc_html($seminar->post_title); ?></h3>
                        <span class="gps-progress-status status-<?php echo esc_attr($registration->status); ?>">
                            <?php echo ucfirst($registration->status); ?>
                        </span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="gps-progress-stats">
                        <div class="gps-stat-item">
                            <span class="gps-stat-label"><?php _e('Sessions Completed', 'gps-courses'); ?></span>
                            <span class="gps-stat-value"><?php echo $registration->sessions_completed; ?> / 10</span>
                        </div>
                        <div class="gps-progress-bar">
                            <div class="gps-progress-fill" style="width: <?php echo $percentage; ?>%">
                                <span class="gps-progress-text"><?php echo round($percentage); ?>%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="gps-stats-grid">
                        <div class="gps-stat-box">
                            <div class="gps-stat-number"><?php echo $registration->sessions_remaining; ?></div>
                            <div class="gps-stat-label-small"><?php _e('Remaining', 'gps-courses'); ?></div>
                        </div>
                        <div class="gps-stat-box">
                            <div class="gps-stat-number"><?php echo $progress['total_credits']; ?></div>
                            <div class="gps-stat-label-small"><?php _e('CE Credits', 'gps-courses'); ?></div>
                        </div>
                        <div class="gps-stat-box">
                            <div class="gps-stat-number"><?php echo $registration->makeup_used ? __('Used', 'gps-courses') : __('Available', 'gps-courses'); ?></div>
                            <div class="gps-stat-label-small"><?php _e('Makeup Session', 'gps-courses'); ?></div>
                        </div>
                    </div>

                    <!-- QR Code -->
                    <?php if ($settings['show_qr_code'] === 'yes' && $registration->qr_code_path): ?>
                        <div class="gps-qr-section">
                            <h4><?php _e('Your QR Code', 'gps-courses'); ?></h4>
                            <p class="gps-qr-instructions"><?php _e('Present this QR code at each session for check-in', 'gps-courses'); ?></p>
                            <?php
                            $upload_dir = wp_upload_dir();
                            $qr_url = $upload_dir['baseurl'] . '/' . $registration->qr_code_path;
                            ?>
                            <div class="gps-qr-code">
                                <img src="<?php echo esc_url($qr_url); ?>" alt="QR Code">
                            </div>
                            <p class="gps-qr-code-text"><?php echo esc_html($registration->qr_code); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Next Session -->
                    <?php if ($settings['show_next_session'] === 'yes' && $progress['next_session']): ?>
                        <div class="gps-next-session">
                            <h4><?php _e('Next Session', 'gps-courses'); ?></h4>
                            <div class="gps-session-info">
                                <div class="gps-session-number">Session <?php echo $progress['next_session']->session_number; ?></div>
                                <div class="gps-session-details">
                                    <div class="gps-session-topic"><?php echo esc_html($progress['next_session']->topic); ?></div>
                                    <div class="gps-session-date">
                                        <?php echo date('F j, Y', strtotime($progress['next_session']->session_date)); ?>
                                        at <?php echo date('g:i A', strtotime($progress['next_session']->session_time_start)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Attendance History -->
                    <?php if ($settings['show_attendance_history'] === 'yes' && !empty($progress['attendance'])): ?>
                        <div class="gps-attendance-history">
                            <h4><?php _e('Attendance History', 'gps-courses'); ?></h4>
                            <div class="gps-attendance-list">
                                <?php foreach ($progress['attendance'] as $attendance): ?>
                                    <div class="gps-attendance-item">
                                        <div class="gps-attendance-session">
                                            <span class="gps-session-badge">Session <?php echo $attendance->session_number; ?></span>
                                            <span class="gps-session-topic-small"><?php echo esc_html($attendance->topic); ?></span>
                                        </div>
                                        <div class="gps-attendance-details">
                                            <span class="gps-attendance-date"><?php echo date('M j, Y', strtotime($attendance->session_date)); ?></span>
                                            <span class="gps-attendance-credits"><?php echo $attendance->credits_awarded; ?> CE</span>
                                            <?php if ($attendance->is_makeup): ?>
                                                <span class="gps-makeup-badge"><?php _e('Makeup', 'gps-courses'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            .gps-seminar-progress-widget {
                max-width: 800px;
                margin: 0 auto;
            }

            .gps-progress-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 12px;
                padding: 30px;
                margin-bottom: 30px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }

            .gps-progress-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 2px solid #f0f0f0;
            }

            .gps-progress-header h3 {
                margin: 0;
                font-size: 24px;
                color: #333;
            }

            .gps-progress-status {
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 600;
            }

            .gps-progress-status.status-active {
                background: #d4edda;
                color: #155724;
            }

            .gps-progress-status.status-completed {
                background: #e5f5fa;
                color: #00527c;
            }

            .gps-progress-stats {
                margin-bottom: 30px;
            }

            .gps-stat-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 12px;
            }

            .gps-stat-label {
                font-weight: 600;
                color: #666;
            }

            .gps-stat-value {
                font-size: 18px;
                font-weight: 700;
                color: #2271b1;
            }

            .gps-progress-bar {
                height: 30px;
                background: #f0f0f0;
                border-radius: 15px;
                overflow: hidden;
                position: relative;
            }

            .gps-progress-fill {
                height: 100%;
                background: #2271b1;
                border-radius: 15px;
                transition: width 0.5s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .gps-progress-text {
                color: #fff;
                font-weight: 700;
                font-size: 14px;
            }

            .gps-stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 30px;
            }

            .gps-stat-box {
                text-align: center;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
            }

            .gps-stat-number {
                font-size: 32px;
                font-weight: 700;
                color: #2271b1;
                margin-bottom: 8px;
            }

            .gps-stat-label-small {
                font-size: 13px;
                color: #666;
                font-weight: 600;
            }

            .gps-qr-section,
            .gps-next-session,
            .gps-attendance-history {
                margin-top: 30px;
                padding-top: 30px;
                border-top: 1px solid #e0e0e0;
            }

            .gps-qr-section h4,
            .gps-next-session h4,
            .gps-attendance-history h4 {
                margin: 0 0 15px 0;
                font-size: 18px;
                color: #333;
            }

            .gps-qr-code {
                text-align: center;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
                margin: 15px 0;
            }

            .gps-qr-code img {
                max-width: 250px;
                height: auto;
            }

            .gps-qr-instructions,
            .gps-qr-code-text {
                text-align: center;
                color: #666;
                font-size: 14px;
            }

            .gps-session-info {
                display: flex;
                gap: 20px;
                padding: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 8px;
                color: #fff;
            }

            .gps-session-number {
                font-size: 24px;
                font-weight: 700;
                min-width: 100px;
            }

            .gps-session-topic {
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 8px;
            }

            .gps-session-date {
                opacity: 0.9;
                font-size: 14px;
            }

            .gps-attendance-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .gps-attendance-item {
                display: flex;
                justify-content: space-between;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #2271b1;
            }

            .gps-session-badge {
                display: inline-block;
                padding: 4px 10px;
                background: #2271b1;
                color: #fff;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                margin-right: 10px;
            }

            .gps-session-topic-small {
                color: #666;
                font-size: 14px;
            }

            .gps-attendance-details {
                display: flex;
                gap: 15px;
                align-items: center;
            }

            .gps-attendance-credits {
                font-weight: 600;
                color: #2271b1;
            }

            .gps-makeup-badge {
                padding: 4px 10px;
                background: #fcf3cd;
                color: #886300;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
            }

            @media (max-width: 768px) {
                .gps-stats-grid {
                    grid-template-columns: 1fr;
                }

                .gps-progress-header {
                    flex-direction: column;
                    gap: 15px;
                    align-items: flex-start;
                }

                .gps-attendance-item {
                    flex-direction: column;
                    gap: 10px;
                }
            }
        </style>
        <?php
    }
}
