<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;

/**
 * Ticket Selector Widget
 */
class Ticket_Selector_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-ticket-selector';
    }

    public function get_title() {
        return __('Ticket Selector', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-product-add-to-cart';
    }

    public function get_script_depends() {
        return ['gps-courses-ticket-selector'];
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
            'ticket_selection_note',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => '<div style="padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; margin: 10px 0 15px 0; border-radius: 4px;">
                    <strong style="color: #856404;">⚠️ Ticket Selection Guide:</strong>
                    <ul style="margin: 8px 0 0 20px; padding: 0; color: #856404; font-size: 12px;">
                        <li><strong>Leave empty</strong> to show ALL tickets for selected event (recommended)</li>
                        <li>Format: <code>Event Name → Ticket Name</code></li>
                        <li style="margin-top: 5px;"><strong style="color: #d63638;">⚠️ ONLY select tickets matching your event above!</strong></li>
                        <li>Wrong selections will be automatically filtered out</li>
                    </ul>
                </div>',
                'content_classes' => 'elementor-panel-alert',
            ]
        );

        $this->add_control(
            'ticket_types',
            [
                'label' => __('Select Ticket Types (Optional)', 'gps-courses'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_ticket_types_list(),
                'default' => [],
                'label_block' => true,
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Layout', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => __('Grid', 'gps-courses'),
                    'list' => __('List', 'gps-courses'),
                    'table' => __('Table', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'show_quantity',
            [
                'label' => __('Show Quantity Selector', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __('Show Ticket Description', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_features',
            [
                'label' => __('Show Ticket Features', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'feature_icon',
            [
                'label' => __('Feature Icon', 'gps-courses'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-check',
                    'library' => 'solid',
                ],
                'condition' => [
                    'show_features' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Add to Cart', 'gps-courses'),
            ]
        );

        $this->end_controls_section();

        // Style Section - Ticket Container
        $this->start_controls_section(
            'section_ticket_style',
            [
                'label' => __('Ticket Container', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'ticket_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'ticket_border_color',
            [
                'label' => __('Border Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-item' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'ticket_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'ticket_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Title
        $this->start_controls_section(
            'section_title_style',
            [
                'label' => __('Ticket Title', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .gps-ticket-title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_spacing',
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
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Badge
        $this->start_controls_section(
            'section_badge_style',
            [
                'label' => __('Ticket Type Badge', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'badge_typography',
                'selector' => '{{WRAPPER}} .gps-ticket-badge',
            ]
        );

        $this->add_control(
            'badge_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-badge' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'badge_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-badge' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'badge_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'badge_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-badge' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Price
        $this->start_controls_section(
            'section_price_style',
            [
                'label' => __('Price', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .gps-ticket-price',
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-price' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Features
        $this->start_controls_section(
            'section_features_style',
            [
                'label' => __('Features', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_features' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'features_typography',
                'selector' => '{{WRAPPER}} .gps-ticket-features .gps-feature-item',
            ]
        );

        $this->add_control(
            'features_text_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-features .gps-feature-item' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'features_icon_color',
            [
                'label' => __('Icon Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-features .gps-feature-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .gps-ticket-features .gps-feature-icon svg' => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'features_icon_size',
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
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-features .gps-feature-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gps-ticket-features .gps-feature-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'features_spacing',
            [
                'label' => __('Item Spacing', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-ticket-features .gps-feature-item' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Button
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => __('Button', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .gps-add-to-cart-btn',
            ]
        );

        $this->add_control(
            'button_bg_color',
            [
                'label' => __('Background Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-add-to-cart-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Text Color', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-add-to-cart-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_bg_color',
            [
                'label' => __('Hover Background', 'gps-courses'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .gps-add-to-cart-btn:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'gps-courses'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .gps-add-to-cart-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-add-to-cart-btn' => 'border-radius: {{SIZE}}{{UNIT}};',
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

        // Get the event object
        $event = get_post($event_id);
        if (!$event) {
            echo '<p>' . __('Event not found.', 'gps-courses') . '</p>';
            return;
        }

        // Get active tickets for this event
        $tickets = \GPSC\Tickets::get_active_tickets($event_id);

        // Filter tickets if specific types are selected
        $selected_ticket_types = !empty($settings['ticket_types']) ? $settings['ticket_types'] : [];
        if (!empty($selected_ticket_types)) {
            $tickets = array_filter($tickets, function($ticket) use ($selected_ticket_types, $event_id) {
                // Only show tickets that:
                // 1. Are in the selected list
                // 2. AND belong to the current event (safety check)
                $ticket_event_id = get_post_meta($ticket->ID, '_gps_event_id', true);
                return in_array($ticket->ID, $selected_ticket_types) && $ticket_event_id == $event_id;
            });
        }

        if (empty($tickets)) {
            echo '<p>' . __('No tickets available for this event.', 'gps-courses') . '</p>';
            return;
        }

        $layout_class = 'gps-tickets-' . $settings['layout'];

        ?>
        <div class="gps-ticket-selector <?php echo esc_attr($layout_class); ?>">

            <?php if ($settings['layout'] === 'table'): ?>
            <table class="gps-tickets-table">
                <thead>
                    <tr>
                        <th><?php _e('Ticket Type', 'gps-courses'); ?></th>
                        <th><?php _e('Price', 'gps-courses'); ?></th>
                        <?php if ($settings['show_quantity'] === 'yes'): ?>
                        <th><?php _e('Quantity', 'gps-courses'); ?></th>
                        <?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
            <?php endif; ?>

            <?php foreach ($tickets as $ticket):
                $price = get_post_meta($ticket->ID, '_gps_ticket_price', true);
                $type = get_post_meta($ticket->ID, '_gps_ticket_type', true);
                $product_id = get_post_meta($ticket->ID, '_gps_wc_product_id', true);

                // Get accurate stock information
                $stock_info = \GPSC\Tickets::get_ticket_stock($ticket->ID);
                $quantity_available = $stock_info['available'];
                $total_quantity = $stock_info['total'];
                $sold_count = $stock_info['sold'];
                $is_unlimited = $stock_info['unlimited'];

                // Check if sold out (uses new centralized method that includes manual override)
                $is_sold_out = \GPSC\Tickets::is_sold_out($ticket->ID);
                $is_manually_sold_out = \GPSC\Tickets::is_manually_sold_out($ticket->ID);

                if ($settings['layout'] === 'table'): ?>
                    <tr class="gps-ticket-row <?php echo $is_sold_out ? 'gps-sold-out-row' : ''; ?>" data-ticket-id="<?php echo (int) $ticket->ID; ?>">
                        <td>
                            <strong><?php echo esc_html($ticket->post_title); ?></strong>
                            <span class="gps-ticket-badge <?php echo esc_attr($type); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $type))); ?>
                            </span>
                            <?php if ($is_sold_out): ?>
                                <span class="gps-sold-out-badge-small"><?php _e('Sold Out', 'gps-courses'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo wc_price($price); ?></strong></td>
                        <?php if ($settings['show_quantity'] === 'yes'): ?>
                        <td>
                            <?php if (!$is_sold_out): ?>
                                <input type="number" class="gps-ticket-quantity" min="1" max="10" value="1">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php if (!$is_sold_out && $product_id): ?>
                                <button class="gps-add-to-cart-btn" data-product-id="<?php echo (int) $product_id; ?>">
                                    <?php echo esc_html($settings['button_text']); ?>
                                </button>
                            <?php else: ?>
                                <button class="gps-waitlist-btn-table" data-ticket-id="<?php echo (int) $ticket->ID; ?>" data-event-id="<?php echo (int) $event->ID; ?>">
                                    <?php _e('Join Waitlist', 'gps-courses'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <div class="gps-ticket-item" data-ticket-id="<?php echo (int) $ticket->ID; ?>">
                        <div class="gps-ticket-header">
                            <h3 class="gps-ticket-title"><?php echo esc_html($ticket->post_title); ?></h3>
                            <span class="gps-ticket-badge <?php echo esc_attr($type); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $type))); ?>
                            </span>
                        </div>

                        <div class="gps-ticket-price">
                            <?php echo wc_price($price); ?>
                        </div>

                        <?php if ($settings['show_description'] === 'yes' && $ticket->post_content): ?>
                        <div class="gps-ticket-description">
                            <?php echo wpautop($ticket->post_content); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($settings['show_features'] === 'yes'):
                            $features = get_post_meta($ticket->ID, '_gps_ticket_features', true);
                            if (!empty($features)):
                                $features_array = array_filter(array_map('trim', explode("\n", $features)));
                                if (!empty($features_array)):
                        ?>
                        <div class="gps-ticket-features">
                            <?php foreach ($features_array as $feature): ?>
                            <div class="gps-feature-item">
                                <span class="gps-feature-icon">
                                    <?php \Elementor\Icons_Manager::render_icon($settings['feature_icon'], ['aria-hidden' => 'true']); ?>
                                </span>
                                <span class="gps-feature-text"><?php echo esc_html($feature); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                                endif;
                            endif;
                        endif; ?>

                        <?php if (!$is_sold_out): ?>
                            <!-- Tickets Available -->
                            <div class="gps-ticket-actions">
                                <?php if ($settings['show_quantity'] === 'yes'): ?>
                                <div class="gps-quantity-selector">
                                    <label><?php _e('Qty:', 'gps-courses'); ?></label>
                                    <input type="number" class="gps-ticket-quantity" min="1" max="10" value="1">
                                </div>
                                <?php endif; ?>

                                <?php if ($product_id): ?>
                                <button class="gps-add-to-cart-btn" data-product-id="<?php echo (int) $product_id; ?>">
                                    <?php echo esc_html($settings['button_text']); ?>
                                </button>
                                <?php endif; ?>
                            </div>

                            <?php if (!$is_unlimited && $total_quantity > 0): ?>
                            <div class="gps-ticket-availability">
                                <?php printf(__('%d tickets remaining', 'gps-courses'), (int) $quantity_available); ?>
                            </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- Sold Out State -->
                            <div class="gps-ticket-sold-out">
                                <div class="gps-sold-out-badge">
                                    <span class="gps-sold-out-icon">🎫</span>
                                    <span class="gps-sold-out-text"><?php _e('Sold Out', 'gps-courses'); ?></span>
                                </div>

                                <div class="gps-sold-out-message">
                                    <p><?php _e('Thank you for your interest! This course is currently sold out.', 'gps-courses'); ?></p>
                                    <p class="gps-waitlist-cta"><?php _e('Join our waitlist to be notified if spots become available:', 'gps-courses'); ?></p>
                                </div>

                                <form class="gps-waitlist-form" data-ticket-id="<?php echo (int) $ticket->ID; ?>" data-event-id="<?php echo (int) $event->ID; ?>">
                                    <div class="gps-waitlist-fields">
                                        <div class="gps-waitlist-row">
                                            <input type="text"
                                                   name="waitlist_first_name"
                                                   placeholder="<?php esc_attr_e('First Name', 'gps-courses'); ?>"
                                                   class="gps-waitlist-input"
                                                   value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->first_name) : ''; ?>">
                                            <input type="text"
                                                   name="waitlist_last_name"
                                                   placeholder="<?php esc_attr_e('Last Name', 'gps-courses'); ?>"
                                                   class="gps-waitlist-input"
                                                   value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->last_name) : ''; ?>">
                                        </div>
                                        <input type="email"
                                               name="waitlist_email"
                                               placeholder="<?php esc_attr_e('Email Address *', 'gps-courses'); ?>"
                                               required
                                               class="gps-waitlist-input"
                                               value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>">
                                        <input type="tel"
                                               name="waitlist_phone"
                                               placeholder="<?php esc_attr_e('Phone (optional)', 'gps-courses'); ?>"
                                               class="gps-waitlist-input">
                                        <button type="submit" class="gps-waitlist-submit">
                                            <?php _e('Join Waitlist', 'gps-courses'); ?>
                                        </button>
                                    </div>
                                    <div class="gps-waitlist-response" style="display: none;"></div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endforeach; ?>

            <?php if ($settings['layout'] === 'table'): ?>
                </tbody>
            </table>
            <?php endif; ?>

        </div>

        <style>
        /* Sold Out Card Styling */
        .gps-ticket-sold-out {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-top: 20px;
        }

        .gps-sold-out-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff3cd;
            color: #856404;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #ffc107;
        }

        .gps-sold-out-icon {
            font-size: 20px;
        }

        .gps-sold-out-message {
            margin: 20px 0;
        }

        .gps-sold-out-message p {
            margin: 10px 0;
            color: #495057;
            line-height: 1.6;
        }

        .gps-waitlist-cta {
            font-weight: 600;
            color: #212529;
        }

        .gps-waitlist-form {
            margin-top: 20px;
        }

        .gps-waitlist-fields {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
            margin: 0 auto;
        }

        .gps-waitlist-row {
            display: flex;
            gap: 10px;
            width: 100%;
        }

        .gps-waitlist-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #ced4da;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            width: 100%;
            box-sizing: border-box;
            min-width: 0;
        }

        .gps-waitlist-row .gps-waitlist-input {
            flex: 1;
            width: auto;
        }

        .gps-waitlist-input:focus {
            outline: none;
            border-color: #0d6efd;
        }

        .gps-waitlist-submit {
            padding: 12px 24px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            white-space: nowrap;
            width: 100%;
        }

        .gps-waitlist-submit:hover {
            background: #0b5ed7;
        }

        .gps-waitlist-submit:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .gps-waitlist-response {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
        }

        .gps-waitlist-response.success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .gps-waitlist-response.error {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        /* Table Sold Out Styling */
        .gps-sold-out-badge-small {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
            border: 1px solid #ffc107;
        }

        .gps-sold-out-row {
            opacity: 0.7;
        }

        .gps-waitlist-btn-table {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .gps-waitlist-btn-table:hover {
            background: #5c636a;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Add to Cart
            $('.gps-add-to-cart-btn').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var productId = $btn.data('product-id');
                var $quantityInput = $btn.closest('.gps-ticket-item, .gps-ticket-row').find('.gps-ticket-quantity');
                var quantity = $quantityInput.length ? $quantityInput.val() : 1;

                $btn.prop('disabled', true).text('<?php _e('Adding...', 'gps-courses'); ?>');

                $.ajax({
                    url: '<?php echo esc_url(wc_get_cart_url()); ?>',
                    type: 'POST',
                    data: {
                        'add-to-cart': productId,
                        'quantity': quantity
                    },
                    success: function() {
                        $btn.text('<?php _e('Added!', 'gps-courses'); ?>');
                        setTimeout(function() {
                            window.location.href = '<?php echo esc_url(wc_get_cart_url()); ?>';
                        }, 500);
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js($settings['button_text']); ?>');
                        alert('<?php _e('Error adding to cart. Please try again.', 'gps-courses'); ?>');
                    }
                });
            });

            // Waitlist Form Submission
            $('.gps-waitlist-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $btn = $form.find('.gps-waitlist-submit');
                var $response = $form.find('.gps-waitlist-response');
                var email = $form.find('[name="waitlist_email"]').val();
                var firstName = $form.find('[name="waitlist_first_name"]').val() || '';
                var lastName = $form.find('[name="waitlist_last_name"]').val() || '';
                var phone = $form.find('[name="waitlist_phone"]').val() || '';
                var ticketId = $form.data('ticket-id');
                var eventId = $form.data('event-id');

                $btn.prop('disabled', true).text('<?php _e('Joining...', 'gps-courses'); ?>');
                $response.hide().removeClass('success error');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'gps_join_waitlist',
                        email: email,
                        first_name: firstName,
                        last_name: lastName,
                        phone: phone,
                        ticket_id: ticketId,
                        event_id: eventId,
                        nonce: '<?php echo wp_create_nonce('gps_waitlist'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $response.addClass('success').html(response.data.message).show();
                            $form.find('input[type="email"], input[type="text"], input[type="tel"]').val('');
                            $btn.text('<?php _e('Joined!', 'gps-courses'); ?>');
                        } else {
                            $response.addClass('error').html(response.data.message).show();
                            $btn.prop('disabled', false).text('<?php _e('Join Waitlist', 'gps-courses'); ?>');
                        }
                    },
                    error: function() {
                        $response.addClass('error').html('<?php _e('Error joining waitlist. Please try again.', 'gps-courses'); ?>').show();
                        $btn.prop('disabled', false).text('<?php _e('Join Waitlist', 'gps-courses'); ?>');
                    }
                });
            });

            // Waitlist Button (Table Layout)
            $('.gps-waitlist-btn-table').on('click', function(e) {
                e.preventDefault();
                var ticketId = $(this).data('ticket-id');
                var eventId = $(this).data('event-id');
                var email = prompt('<?php _e('Enter your email to join the waitlist:', 'gps-courses'); ?>');

                if (email) {
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('<?php _e('Joining...', 'gps-courses'); ?>');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'gps_join_waitlist',
                            email: email,
                            ticket_id: ticketId,
                            event_id: eventId,
                            nonce: '<?php echo wp_create_nonce('gps_waitlist'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                $btn.text('<?php _e('Joined!', 'gps-courses'); ?>');
                            } else {
                                alert(response.data.message);
                                $btn.prop('disabled', false).text('<?php _e('Join Waitlist', 'gps-courses'); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php _e('Error joining waitlist. Please try again.', 'gps-courses'); ?>');
                            $btn.prop('disabled', false).text('<?php _e('Join Waitlist', 'gps-courses'); ?>');
                        }
                    });
                }
            });
        });
        </script>
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

    private function get_ticket_types_list() {
        try {
            global $wpdb;

            // Get all tickets with their event names
            $results = $wpdb->get_results("
                SELECT p.ID, p.post_title, pm.meta_value as event_id
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_gps_event_id'
                WHERE p.post_type = 'gps_ticket'
                AND p.post_status = 'publish'
                ORDER BY pm.meta_value, p.post_title
            ");

            $options = [];

            if (empty($results)) {
                return $options;
            }

            $events_cache = [];

            foreach ($results as $ticket) {
                if (!isset($ticket->ID) || !isset($ticket->post_title)) {
                    continue;
                }

                $event_name = 'No Event';

                if (!empty($ticket->event_id)) {
                    if (!isset($events_cache[$ticket->event_id])) {
                        $event = get_post($ticket->event_id);
                        $events_cache[$ticket->event_id] = $event ? $event->post_title : 'Unknown Event';
                    }
                    $event_name = $events_cache[$ticket->event_id];
                }

                // Format: "Event Name → Ticket Name"
                $options[$ticket->ID] = $event_name . ' → ' . $ticket->post_title;
            }

            return $options;
        } catch (\Exception $e) {
            // Fallback to simple list if query fails
            $tickets = get_posts([
                'post_type' => 'gps_ticket',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            ]);

            $options = [];
            foreach ($tickets as $ticket) {
                $options[$ticket->ID] = $ticket->post_title;
            }

            return $options;
        }
    }

    /**
     * Get ticket types filtered by event ID
     */
    private function get_ticket_types_for_event($event_id) {
        if (empty($event_id)) {
            return $this->get_ticket_types_list();
        }

        global $wpdb;

        // Get tickets for specific event
        $ticket_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = '_gps_event_id'
            AND meta_value = %d",
            $event_id
        ));

        if (empty($ticket_ids)) {
            return [];
        }

        $tickets = get_posts([
            'post_type' => 'gps_ticket',
            'post_status' => 'publish',
            'post__in' => $ticket_ids,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $options = [];
        foreach ($tickets as $ticket) {
            $options[$ticket->ID] = $ticket->post_title;
        }

        return $options;
    }
}
