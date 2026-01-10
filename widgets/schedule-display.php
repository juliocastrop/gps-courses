<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;

/**
 * Schedule Display Widget
 */
class Schedule_Display_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-schedule-display';
    }

    public function get_title() {
        return __('Event Schedule', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-time-line';
    }

    public function get_script_depends() {
        return ['gps-courses-schedule-display'];
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
            'event_id',
            [
                'label' => __('Select Event', 'gps-courses'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_events_list(),
                'default' => '',
                'description' => __('Leave empty to use current event', 'gps-courses'),
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Layout Style', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'timeline',
                'options' => [
                    'timeline' => __('Timeline', 'gps-courses'),
                    'tabs' => __('Tabs (by date)', 'gps-courses'),
                    'accordion' => __('Accordion', 'gps-courses'),
                    'list' => __('Simple List', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'show_speakers',
            [
                'label' => __('Show Speakers', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_location',
            [
                'label' => __('Show Location', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __('Show Description', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
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

        $this->add_control(
            'container_bg',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-display' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-display' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Topic Item
        $this->start_controls_section(
            'section_topic_style',
            [
                'label' => __('Topic Item', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'topic_title_typography',
                'label' => __('Title Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .schedule-topic-title',
            ]
        );

        $this->add_control(
            'topic_title_color',
            [
                'label' => __('Title Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .schedule-topic-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'topic_bg',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .schedule-topic' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'topic_border_color',
            [
                'label' => __('Border Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .schedule-topic' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Time
        $this->start_controls_section(
            'section_time_style',
            [
                'label' => __('Time Display', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'time_typography',
                'selector' => '{{WRAPPER}} .schedule-time',
            ]
        );

        $this->add_control(
            'time_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .schedule-time' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'time_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .schedule-time' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'time_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default' => [
                    'top' => 15,
                    'right' => 15,
                    'bottom' => 15,
                    'left' => 15,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .schedule-time' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'time_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 6,
                    'right' => 6,
                    'bottom' => 6,
                    'left' => 6,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .schedule-time' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Tabs
        $this->start_controls_section(
            'section_tabs_style',
            [
                'label' => __('Tabs', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'layout' => 'tabs',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'tab_typography',
                'label' => __('Typography', 'gps-courses'),
                'selector' => '{{WRAPPER}} .gps-schedule-tab',
            ]
        );

        $this->add_responsive_control(
            'tabs_alignment',
            [
                'label' => __('Tabs Alignment', 'gps-courses'),
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
                'default' => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}} .schedule-tabs-nav' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->start_controls_tabs('tabs_styles');

        $this->start_controls_tab(
            'tab_normal',
            [
                'label' => __('Normal', 'gps-courses'),
            ]
        );

        $this->add_control(
            'tab_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#646970',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tab_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_hover',
            [
                'label' => __('Hover', 'gps-courses'),
            ]
        );

        $this->add_control(
            'tab_hover_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tab_hover_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f9f9f9',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_active',
            [
                'label' => __('Active', 'gps-courses'),
            ]
        );

        $this->add_control(
            'tab_active_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab.active' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tab_active_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f0f6fc',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab.active' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'tab_active_border_color',
            [
                'label' => __('Border Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab.active' => 'border-bottom-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'tab_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'separator' => 'before',
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'tab_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-schedule-tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'tab_spacing',
            [
                'label' => __('Gap Between Tabs', 'gps-courses'),
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
                    '{{WRAPPER}} .schedule-tabs-nav' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Speakers/Location
        $this->start_controls_section(
            'section_meta_style',
            [
                'label' => __('Speakers & Location', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'meta_typography',
                'selector' => '{{WRAPPER}} .schedule-speakers, {{WRAPPER}} .schedule-location',
            ]
        );

        $this->add_control(
            'meta_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#646970',
                'selectors' => [
                    '{{WRAPPER}} .schedule-speakers' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .schedule-location' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'meta_icon_color',
            [
                'label' => __('Icon Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#2271b1',
                'selectors' => [
                    '{{WRAPPER}} .schedule-speakers .dashicons' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .schedule-location .dashicons' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Description
        $this->start_controls_section(
            'section_description_style',
            [
                'label' => __('Description', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .schedule-description',
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'default' => '#646970',
                'selectors' => [
                    '{{WRAPPER}} .schedule-description' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Get event ID
        $event_id = !empty($settings['event_id']) ? (int) $settings['event_id'] : get_the_ID();

        if (!$event_id || get_post_type($event_id) !== 'gps_event') {
            echo '<p>' . __('Please select a valid event.', 'gps-courses') . '</p>';
            return;
        }

        // Get schedules for this event
        $schedules = \GPSC\Schedules::get_event_schedules($event_id);

        if (empty($schedules)) {
            echo '<p>' . __('No schedules available for this event.', 'gps-courses') . '</p>';
            return;
        }

        $layout = $settings['layout'];

        ?>
        <div class="gps-schedule-display gps-schedule-<?php echo esc_attr($layout); ?>">

            <?php if ($layout === 'tabs'): ?>
                <?php $this->render_tabs_layout($schedules, $settings); ?>

            <?php elseif ($layout === 'accordion'): ?>
                <?php $this->render_accordion_layout($schedules, $settings); ?>

            <?php elseif ($layout === 'list'): ?>
                <?php $this->render_list_layout($schedules, $settings); ?>

            <?php else: // timeline ?>
                <?php $this->render_timeline_layout($schedules, $settings); ?>

            <?php endif; ?>

        </div>

        <?php if ($layout === 'tabs'): ?>
        <script>
        jQuery(document).ready(function($) {
            $('.gps-schedule-tab').on('click', function() {
                const target = $(this).data('tab');

                $('.gps-schedule-tab').removeClass('active');
                $(this).addClass('active');

                $('.gps-schedule-tab-content').removeClass('active');
                $('#' + target).addClass('active');
            });
        });
        </script>
        <?php endif; ?>

        <?php if ($layout === 'accordion'): ?>
        <script>
        jQuery(document).ready(function($) {
            $('.schedule-accordion-header').on('click', function() {
                const $item = $(this).closest('.schedule-accordion-item');
                const $content = $item.find('.schedule-accordion-content');

                if ($item.hasClass('active')) {
                    $item.removeClass('active');
                    $content.slideUp(300);
                } else {
                    $('.schedule-accordion-item').removeClass('active');
                    $('.schedule-accordion-content').slideUp(300);
                    $item.addClass('active');
                    $content.slideDown(300);
                }
            });

            // Open first item by default
            $('.schedule-accordion-item:first-child .schedule-accordion-header').click();
        });
        </script>
        <?php endif; ?>
        <?php
    }

    private function render_timeline_layout($schedules, $settings) {
        ?>
        <div class="schedule-timeline">
            <?php foreach ($schedules as $schedule): ?>
                <?php
                $date = get_post_meta($schedule->ID, '_gps_schedule_date', true);
                $topics = \GPSC\Schedules::get_schedule_topics($schedule->ID);
                ?>
                <div class="schedule-day">
                    <div class="schedule-day-header">
                        <h3 class="schedule-day-title"><?php echo esc_html($schedule->post_title); ?></h3>
                        <?php if ($date): ?>
                            <div class="schedule-day-date">
                                <?php echo date_i18n(get_option('date_format'), strtotime($date)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($topics)): ?>
                        <div class="schedule-topics">
                            <?php foreach ($topics as $topic): ?>
                                <?php $this->render_topic_item($topic, $settings); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_tabs_layout($schedules, $settings) {
        ?>
        <div class="schedule-tabs">
            <div class="schedule-tabs-nav">
                <?php foreach ($schedules as $index => $schedule): ?>
                    <?php
                    $date = get_post_meta($schedule->ID, '_gps_schedule_date', true);
                    $tab_label = get_post_meta($schedule->ID, '_gps_tab_label', true);
                    $display_title = !empty($tab_label) ? $tab_label : $schedule->post_title;
                    ?>
                    <button class="gps-schedule-tab <?php echo $index === 0 ? 'active' : ''; ?>"
                            data-tab="schedule-<?php echo (int) $schedule->ID; ?>">
                        <span class="tab-title"><?php echo esc_html($display_title); ?></span>
                        <?php if ($date): ?>
                            <span class="tab-date"><?php echo date_i18n('M j', strtotime($date)); ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="schedule-tabs-content">
                <?php foreach ($schedules as $index => $schedule): ?>
                    <?php $topics = \GPSC\Schedules::get_schedule_topics($schedule->ID); ?>
                    <div id="schedule-<?php echo (int) $schedule->ID; ?>"
                         class="gps-schedule-tab-content <?php echo $index === 0 ? 'active' : ''; ?>">
                        <?php if (!empty($topics)): ?>
                            <div class="schedule-topics">
                                <?php foreach ($topics as $topic): ?>
                                    <?php $this->render_topic_item($topic, $settings); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function render_accordion_layout($schedules, $settings) {
        ?>
        <div class="schedule-accordion">
            <?php foreach ($schedules as $schedule): ?>
                <?php
                $date = get_post_meta($schedule->ID, '_gps_schedule_date', true);
                $topics = \GPSC\Schedules::get_schedule_topics($schedule->ID);
                ?>
                <div class="schedule-accordion-item">
                    <div class="schedule-accordion-header">
                        <h3 class="accordion-title"><?php echo esc_html($schedule->post_title); ?></h3>
                        <?php if ($date): ?>
                            <span class="accordion-date"><?php echo date_i18n(get_option('date_format'), strtotime($date)); ?></span>
                        <?php endif; ?>
                        <span class="accordion-icon dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    <div class="schedule-accordion-content">
                        <?php if (!empty($topics)): ?>
                            <div class="schedule-topics">
                                <?php foreach ($topics as $topic): ?>
                                    <?php $this->render_topic_item($topic, $settings); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_list_layout($schedules, $settings) {
        ?>
        <div class="schedule-list">
            <?php foreach ($schedules as $schedule): ?>
                <?php
                $date = get_post_meta($schedule->ID, '_gps_schedule_date', true);
                $topics = \GPSC\Schedules::get_schedule_topics($schedule->ID);
                ?>
                <div class="schedule-day">
                    <div class="schedule-day-header">
                        <h3 class="schedule-day-title"><?php echo esc_html($schedule->post_title); ?></h3>
                        <?php if ($date): ?>
                            <div class="schedule-day-date">
                                <?php echo date_i18n(get_option('date_format'), strtotime($date)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($topics)): ?>
                        <div class="schedule-topics">
                            <?php foreach ($topics as $topic): ?>
                                <?php $this->render_topic_item($topic, $settings, true); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_topic_item($topic, $settings, $simple = false) {
        $name = $topic['name'] ?? '';
        $start_time = $topic['start_time'] ?? '';
        $end_time = $topic['end_time'] ?? '';
        $speaker_ids = $topic['speakers'] ?? [];
        $location = $topic['location'] ?? '';
        $description = $topic['description'] ?? '';

        ?>
        <div class="schedule-topic">
            <?php if ($start_time || $end_time): ?>
                <div class="schedule-time">
                    <?php if ($start_time): ?>
                        <span class="time-start"><?php echo esc_html(date_i18n('g:i A', strtotime($start_time))); ?></span>
                    <?php endif; ?>
                    <?php if ($end_time): ?>
                        <span class="time-separator">-</span>
                        <span class="time-end"><?php echo esc_html(date_i18n('g:i A', strtotime($end_time))); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="schedule-topic-content">
                <?php if ($name): ?>
                    <h4 class="schedule-topic-title"><?php echo esc_html($name); ?></h4>
                <?php endif; ?>

                <?php if ($settings['show_speakers'] === 'yes' && !empty($speaker_ids)): ?>
                    <div class="schedule-speakers">
                        <span class="dashicons dashicons-businessperson"></span>
                        <?php
                        $speaker_names = [];
                        foreach ($speaker_ids as $speaker_id) {
                            $speaker = get_post($speaker_id);
                            if ($speaker) {
                                $speaker_names[] = $speaker->post_title;
                            }
                        }
                        echo esc_html(implode(', ', $speaker_names));
                        ?>
                    </div>
                <?php endif; ?>

                <?php if ($settings['show_location'] === 'yes' && $location): ?>
                    <div class="schedule-location">
                        <span class="dashicons dashicons-location"></span>
                        <?php echo esc_html($location); ?>
                    </div>
                <?php endif; ?>

                <?php if ($settings['show_description'] === 'yes' && $description): ?>
                    <div class="schedule-description">
                        <?php
                        // If content already has HTML tags, don't use wpautop
                        if (strpos($description, '<') !== false) {
                            echo wp_kses_post($description);
                        } else {
                            echo wp_kses_post(wpautop($description));
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function get_events_list() {
        $events = get_posts([
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $options = ['' => __('Current Event', 'gps-courses')];

        foreach ($events as $event) {
            $options[$event->ID] = $event->post_title;
        }

        return $options;
    }
}
