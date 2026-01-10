<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

/**
 * Base Widget Class
 * Extended by all GPS Courses widgets
 */
abstract class Base_Widget extends Widget_Base {

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['gps-courses'];
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-posts-grid';
    }

    /**
     * Register common style controls
     */
    protected function register_style_controls_common() {
        // Spacing
        $this->add_responsive_control(
            'item_spacing',
            [
                'label' => __('Item Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-item' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Padding
        $this->add_responsive_control(
            'item_padding',
            [
                'label' => __('Item Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // Border
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'item_border',
                'selector' => '{{WRAPPER}} .gps-event-item',
            ]
        );

        // Border Radius
        $this->add_control(
            'item_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-event-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // Box Shadow
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'item_box_shadow',
                'selector' => '{{WRAPPER}} .gps-event-item',
            ]
        );
    }

    /**
     * Register common query controls
     */
    protected function register_query_controls() {
        $this->start_controls_section(
            'section_query',
            [
                'label' => __('Query', 'gps-courses'),
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => __('Events Per Page', 'gps-courses'),
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
                'default' => 'date',
                'options' => [
                    'date' => __('Event Date', 'gps-courses'),
                    'title' => __('Title', 'gps-courses'),
                    'menu_order' => __('Menu Order', 'gps-courses'),
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

        $this->add_control(
            'event_filter',
            [
                'label' => __('Filter', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all' => __('All Events', 'gps-courses'),
                    'upcoming' => __('Upcoming Events', 'gps-courses'),
                    'past' => __('Past Events', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'exclude_ids',
            [
                'label' => __('Exclude Events', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Enter event IDs separated by commas', 'gps-courses'),
                'description' => __('Example: 123, 456, 789', 'gps-courses'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Get events query
     */
    protected function get_events_query($settings) {
        $args = [
            'post_type' => 'gps_event',
            'post_status' => 'publish',
            'posts_per_page' => $settings['posts_per_page'] ?? 6,
            'orderby' => $settings['order_by'] ?? 'date',
            'order' => $settings['order'] ?? 'ASC',
        ];

        // Event filter (upcoming/past)
        $event_filter = $settings['event_filter'] ?? 'all';
        if ($event_filter !== 'all') {
            $current_date = current_time('Y-m-d H:i:s');

            $args['meta_query'] = [
                [
                    'key' => '_gps_date_start',
                    'value' => $current_date,
                    'compare' => $event_filter === 'upcoming' ? '>=' : '<',
                    'type' => 'DATETIME',
                ],
            ];
        }

        // Exclude events
        if (!empty($settings['exclude_ids'])) {
            $exclude_ids = array_map('trim', explode(',', $settings['exclude_ids']));
            $exclude_ids = array_filter($exclude_ids, 'is_numeric');
            if (!empty($exclude_ids)) {
                $args['post__not_in'] = $exclude_ids;
            }
        }

        // Order by event date
        if ($args['orderby'] === 'date') {
            $args['orderby'] = 'meta_value';
            $args['meta_key'] = '_gps_date_start';
            $args['meta_type'] = 'DATETIME';
        }

        return new \WP_Query($args);
    }

    /**
     * Format event date
     */
    protected function format_event_date($start, $end) {
        if (empty($start)) {
            return '';
        }

        $start_date = strtotime($start);
        $end_date = !empty($end) ? strtotime($end) : $start_date;

        // Same day
        if (date('Y-m-d', $start_date) === date('Y-m-d', $end_date)) {
            return date_i18n('F j, Y', $start_date) . ' ' . date_i18n('g:i a', $start_date);
        }

        // Multi-day
        return date_i18n('F j', $start_date) . ' - ' . date_i18n('F j, Y', $end_date);
    }

    /**
     * Get event status
     */
    protected function get_event_status($start_date) {
        if (empty($start_date)) {
            return 'unknown';
        }

        $start = strtotime($start_date);
        $now = current_time('timestamp');

        if ($start > $now) {
            return 'upcoming';
        } elseif ($start < $now) {
            return 'past';
        } else {
            return 'ongoing';
        }
    }

    /**
     * Render event thumbnail
     */
    protected function render_event_thumbnail($event_id, $size = 'medium_large') {
        if (!has_post_thumbnail($event_id)) {
            echo '<div class="gps-event-thumbnail gps-no-image">';
            echo '<span class="dashicons dashicons-calendar-alt"></span>';
            echo '</div>';
            return;
        }

        echo '<div class="gps-event-thumbnail">';
        echo get_the_post_thumbnail($event_id, $size);
        echo '</div>';
    }

    /**
     * Render event meta
     */
    protected function render_event_meta($event_id, $show_date = true, $show_venue = true, $show_credits = false) {
        echo '<div class="gps-event-meta">';

        if ($show_date) {
            $start = get_post_meta($event_id, '_gps_date_start', true);
            $end = get_post_meta($event_id, '_gps_date_end', true);

            if ($start) {
                echo '<span class="gps-meta-item gps-meta-date">';
                echo '<i class="far fa-calendar"></i> ';
                echo esc_html($this->format_event_date($start, $end));
                echo '</span>';
            }
        }

        if ($show_venue) {
            $venue = get_post_meta($event_id, '_gps_venue', true);
            if ($venue) {
                echo '<span class="gps-meta-item gps-meta-venue">';
                echo '<i class="far fa-map-marker-alt"></i> ';
                echo esc_html($venue);
                echo '</span>';
            }
        }

        if ($show_credits) {
            $credits = (int) get_post_meta($event_id, '_gps_ce_credits', true);
            if ($credits > 0) {
                echo '<span class="gps-meta-item gps-meta-credits">';
                echo '<i class="far fa-certificate"></i> ';
                echo sprintf(_n('%d CE Credit', '%d CE Credits', $credits, 'gps-courses'), $credits);
                echo '</span>';
            }
        }

        echo '</div>';
    }

    /**
     * Render event status badge
     */
    protected function render_event_status($event_id) {
        $start_date = get_post_meta($event_id, '_gps_date_start', true);
        $status = $this->get_event_status($start_date);

        $class = 'gps-event-status gps-status-' . $status;

        $labels = [
            'upcoming' => __('Upcoming', 'gps-courses'),
            'ongoing' => __('Happening Now', 'gps-courses'),
            'past' => __('Past', 'gps-courses'),
        ];

        echo '<span class="' . esc_attr($class) . '">';
        echo esc_html($labels[$status] ?? '');
        echo '</span>';
    }
}
