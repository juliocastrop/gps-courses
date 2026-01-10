/**
 * GPS Courses - Ticket Selector
 */

(function($) {
    'use strict';

    class GPSTicketSelector {
        constructor(element) {
            this.$element = $(element);
            this.eventId = this.$element.data('event-id');

            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Quantity controls
            this.$element.on('click', '.gps-qty-minus', this.decreaseQuantity.bind(this));
            this.$element.on('click', '.gps-qty-plus', this.increaseQuantity.bind(this));
            this.$element.on('change', '.gps-qty-input', this.validateQuantity.bind(this));

            // Add to cart
            this.$element.on('click', '.gps-add-to-cart-btn', this.addToCart.bind(this));

            // Ticket type selection
            this.$element.on('change', 'input[name="ticket_type"]', this.updateSelection.bind(this));
        }

        decreaseQuantity(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const $input = $button.siblings('.gps-qty-input');
            let value = parseInt($input.val()) || 0;

            if (value > 0) {
                value--;
                $input.val(value).trigger('change');
            }
        }

        increaseQuantity(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const $input = $button.siblings('.gps-qty-input');
            const max = parseInt($input.attr('max')) || 999;
            let value = parseInt($input.val()) || 0;

            if (value < max) {
                value++;
                $input.val(value).trigger('change');
            }
        }

        validateQuantity(e) {
            const $input = $(e.currentTarget);
            const min = parseInt($input.attr('min')) || 0;
            const max = parseInt($input.attr('max')) || 999;
            let value = parseInt($input.val()) || 0;

            if (value < min) value = min;
            if (value > max) value = max;

            $input.val(value);
            this.updateTotal();
        }

        updateSelection() {
            const $selected = this.$element.find('input[name="ticket_type"]:checked');
            const ticketId = $selected.val();

            // Highlight selected ticket
            this.$element.find('.gps-ticket-item').removeClass('selected');
            $selected.closest('.gps-ticket-item').addClass('selected');

            this.updateTotal();
        }

        updateTotal() {
            let total = 0;
            let totalQuantity = 0;

            this.$element.find('.gps-ticket-item').each(function() {
                const $item = $(this);
                const quantity = parseInt($item.find('.gps-qty-input').val()) || 0;
                const price = parseFloat($item.data('price')) || 0;

                total += quantity * price;
                totalQuantity += quantity;
            });

            // Update display
            this.$element.find('.gps-total-amount').text(this.formatPrice(total));
            this.$element.find('.gps-total-quantity').text(totalQuantity);

            // Enable/disable add to cart button
            const $addButton = this.$element.find('.gps-add-to-cart-btn');
            if (totalQuantity > 0) {
                $addButton.prop('disabled', false);
            } else {
                $addButton.prop('disabled', true);
            }
        }

        addToCart(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            if ($button.prop('disabled')) {
                return;
            }

            // Collect selected tickets
            const tickets = [];
            this.$element.find('.gps-ticket-item').each(function() {
                const $item = $(this);
                const quantity = parseInt($item.find('.gps-qty-input').val()) || 0;

                if (quantity > 0) {
                    tickets.push({
                        ticket_id: $item.data('ticket-id'),
                        product_id: $item.data('product-id'),
                        quantity: quantity
                    });
                }
            });

            if (tickets.length === 0) {
                this.showMessage('error', 'Please select at least one ticket.');
                return;
            }

            // Show loading
            const originalText = $button.html();
            $button.prop('disabled', true).html('<span class="gps-spinner"></span> Adding to cart...');

            // Add to cart via AJAX
            $.ajax({
                url: gpsTicketSelector.ajaxurl || wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'gps_add_tickets_to_cart',
                    event_id: this.eventId,
                    tickets: tickets,
                    nonce: gpsTicketSelector.nonce
                },
                success: (response) => {
                    $button.prop('disabled', false).html(originalText);

                    if (response.success) {
                        this.showMessage('success', response.data.message || 'Tickets added to cart!');

                        // Update cart count
                        if (response.data.cart_count) {
                            $('.cart-count, .cart-contents-count').text(response.data.cart_count);
                        }

                        // Trigger WooCommerce cart update
                        $(document.body).trigger('wc_fragment_refresh');
                        $(document.body).trigger('added_to_cart', [response.data.fragments, response.data.cart_hash]);

                        // Reset quantities
                        this.$element.find('.gps-qty-input').val(0);
                        this.updateTotal();

                        // Optionally redirect to cart
                        if (gpsTicketSelector.redirect_to_cart) {
                            setTimeout(() => {
                                window.location.href = gpsTicketSelector.cart_url;
                            }, 1500);
                        }
                    } else {
                        this.showMessage('error', response.data.message || 'Error adding tickets to cart.');
                    }
                },
                error: (xhr, status, error) => {
                    $button.prop('disabled', false).html(originalText);
                    console.error('Add to cart error:', error);
                    this.showMessage('error', 'An error occurred. Please try again.');
                }
            });
        }

        showMessage(type, message) {
            const $messages = this.$element.find('.gps-ticket-messages');

            if (!$messages.length) {
                this.$element.prepend('<div class="gps-ticket-messages"></div>');
            }

            const typeClass = type === 'error' ? 'gps-message-error' : 'gps-message-success';
            const html = '<div class="gps-message ' + typeClass + '">' + message + '</div>';

            this.$element.find('.gps-ticket-messages').html(html);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                this.$element.find('.gps-ticket-messages').fadeOut(() => {
                    $(this).remove();
                });
            }, 5000);

            // Scroll to message
            $('html, body').animate({
                scrollTop: this.$element.offset().top - 100
            }, 300);
        }

        formatPrice(amount) {
            // Use WooCommerce currency format if available
            if (typeof accounting !== 'undefined' && typeof woocommerce_params !== 'undefined') {
                return accounting.formatMoney(amount, {
                    symbol: woocommerce_params.currency_format_symbol,
                    decimal: woocommerce_params.currency_format_decimal_sep,
                    thousand: woocommerce_params.currency_format_thousand_sep,
                    precision: woocommerce_params.currency_format_num_decimals,
                    format: woocommerce_params.currency_format
                });
            }

            // Fallback format
            return '$' + amount.toFixed(2);
        }
    }

    // Initialize ticket selectors
    function initTicketSelectors() {
        $('.gps-ticket-selector-widget, .gps-ticket-selector-shortcode').each(function() {
            if (!$(this).data('gps-ticket-selector-initialized')) {
                new GPSTicketSelector(this);
                $(this).data('gps-ticket-selector-initialized', true);
            }
        });
    }

    $(document).ready(function() {
        initTicketSelectors();
    });

    // Expose to Elementor frontend
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/gps-ticket-selector.default', function($scope) {
            new GPSTicketSelector($scope.find('.gps-ticket-selector-widget'));
        });
    });

    // Re-initialize on AJAX content load
    $(document).on('gps-content-loaded', function() {
        initTicketSelectors();
    });

})(jQuery);
