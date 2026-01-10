<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use GPSC\Seminars;

/**
 * Seminar Registration Widget
 * Displays available seminars with registration buttons
 */
class Seminar_Registration_Widget extends Base_Widget {

    public function get_name() {
        return 'seminar-registration';
    }

    public function get_title() {
        return __('Seminar Registration', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-calendar';
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

        // Select specific seminar or show all
        $this->add_control(
            'seminar_id',
            [
                'label' => __('Select Seminar', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_seminars_list(),
                'default' => '0',
                'description' => __('Leave as "All Seminars" to show all active seminars', 'gps-courses'),
            ]
        );

        $this->add_control(
            'show_capacity',
            [
                'label' => __('Show Capacity', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'gps-courses'),
                'label_off' => __('Hide', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_sessions',
            [
                'label' => __('Show Session Count', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'gps-courses'),
                'label_off' => __('Hide', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Show Price', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'gps-courses'),
                'label_off' => __('Hide', 'gps-courses'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Register Now', 'gps-courses'),
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
            'card_bg_color',
            [
                'label' => __('Card Background', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-seminar-card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => __('Title Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .gps-seminar-title',
            ]
        );

        $this->add_control(
            'button_bg_color',
            [
                'label' => __('Button Background', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-register-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Button Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-register-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $seminar_id = $settings['seminar_id'];

        // Get seminars
        if ($seminar_id && $seminar_id != '0') {
            $seminars = [get_post($seminar_id)];
        } else {
            $seminars = get_posts([
                'post_type' => 'gps_seminar',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'meta_value_num',
                'meta_key' => '_gps_seminar_year',
                'order' => 'DESC',
            ]);
        }

        if (empty($seminars)) {
            echo '<p>' . __('No seminars available at this time.', 'gps-courses') . '</p>';
            return;
        }

        ?>
        <div class="gps-seminar-registration-widget">
            <?php foreach ($seminars as $seminar):
                $year = get_post_meta($seminar->ID, '_gps_seminar_year', true);
                $capacity = (int) get_post_meta($seminar->ID, '_gps_seminar_capacity', true) ?: 50;
                $enrolled = Seminars::get_enrollment_count($seminar->ID);
                $available = $capacity - $enrolled;
                $product_id = get_post_meta($seminar->ID, '_gps_seminar_product_id', true);
                $sessions = Seminars::get_sessions($seminar->ID);
                $is_full = $available <= 0;
            ?>
                <div class="gps-seminar-card">
                    <div class="gps-seminar-header">
                        <h3 class="gps-seminar-title"><?php echo esc_html($seminar->post_title); ?></h3>
                        <div class="gps-seminar-year"><?php echo esc_html($year); ?></div>
                    </div>

                    <div class="gps-seminar-content">
                        <?php if ($seminar->post_content): ?>
                            <div class="gps-seminar-description">
                                <?php echo wpautop(wp_trim_words($seminar->post_content, 30)); ?>
                            </div>
                        <?php endif; ?>

                        <div class="gps-seminar-meta">
                            <?php if ($settings['show_sessions'] === 'yes'): ?>
                                <div class="gps-seminar-sessions">
                                    <span class="label"><?php _e('Sessions:', 'gps-courses'); ?></span>
                                    <span class="value"><?php echo count($sessions); ?> sessions</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($settings['show_capacity'] === 'yes'): ?>
                                <div class="gps-seminar-capacity">
                                    <span class="label"><?php _e('Availability:', 'gps-courses'); ?></span>
                                    <span class="value <?php echo $is_full ? 'full' : ''; ?>">
                                        <?php
                                        if ($is_full) {
                                            _e('Full', 'gps-courses');
                                        } else {
                                            echo sprintf(__('%d spots remaining', 'gps-courses'), $available);
                                        }
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($settings['show_price'] === 'yes' && $product_id):
                                $product = wc_get_product($product_id);
                                if ($product):
                            ?>
                                <div class="gps-seminar-price">
                                    <span class="label"><?php _e('Price:', 'gps-courses'); ?></span>
                                    <span class="value"><?php echo $product->get_price_html(); ?></span>
                                </div>
                            <?php endif; endif; ?>
                        </div>
                    </div>

                    <div class="gps-seminar-footer">
                        <?php if ($product_id && !$is_full): ?>
                            <a href="<?php echo esc_url(get_permalink($product_id)); ?>" class="gps-register-btn">
                                <?php echo esc_html($settings['button_text']); ?>
                            </a>
                        <?php elseif ($is_full): ?>
                            <button class="gps-register-btn disabled" disabled>
                                <?php _e('Full - Join Waitlist', 'gps-courses'); ?>
                            </button>
                        <?php else: ?>
                            <button class="gps-register-btn disabled" disabled>
                                <?php _e('Registration Unavailable', 'gps-courses'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            .gps-seminar-registration-widget {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 30px;
            }

            .gps-seminar-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 12px;
                overflow: hidden;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
            }

            .gps-seminar-card:hover {
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
                transform: translateY(-4px);
            }

            .gps-seminar-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 25px;
            }

            .gps-seminar-title {
                margin: 0 0 8px 0;
                font-size: 22px;
                font-weight: 700;
                color: #fff;
            }

            .gps-seminar-year {
                font-size: 16px;
                opacity: 0.9;
            }

            .gps-seminar-content {
                padding: 25px;
                flex: 1;
            }

            .gps-seminar-description {
                color: #666;
                line-height: 1.6;
                margin-bottom: 20px;
            }

            .gps-seminar-meta {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .gps-seminar-meta > div {
                display: flex;
                justify-content: space-between;
                padding-bottom: 12px;
                border-bottom: 1px solid #f0f0f0;
            }

            .gps-seminar-meta .label {
                font-weight: 600;
                color: #333;
            }

            .gps-seminar-meta .value {
                color: #666;
            }

            .gps-seminar-meta .value.full {
                color: #dc3232;
                font-weight: 600;
            }

            .gps-seminar-price .value {
                font-size: 20px;
                font-weight: 700;
                color: #2271b1;
            }

            .gps-seminar-footer {
                padding: 0 25px 25px 25px;
            }

            .gps-register-btn {
                display: block;
                width: 100%;
                padding: 15px 30px;
                background: #2271b1;
                color: #fff;
                text-align: center;
                text-decoration: none;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .gps-register-btn:hover {
                background: #135e96;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3);
            }

            .gps-register-btn.disabled {
                background: #ccc;
                cursor: not-allowed;
                opacity: 0.6;
            }

            .gps-register-btn.disabled:hover {
                transform: none;
                box-shadow: none;
            }

            @media (max-width: 768px) {
                .gps-seminar-registration-widget {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    private function get_seminars_list() {
        $options = [
            '0' => __('All Seminars', 'gps-courses'),
        ];

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
