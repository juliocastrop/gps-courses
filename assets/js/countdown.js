/**
 * GPS Courses - Countdown Timer
 */

(function($) {
    'use strict';

    class GPSCountdown {
        constructor(element) {
            this.$element = $(element);
            this.targetDate = this.$element.data('date');
            this.interval = null;

            if (this.targetDate) {
                this.init();
            }
        }

        init() {
            this.startCountdown();
        }

        startCountdown() {
            // Initial update
            this.updateCountdown();

            // Update every second
            this.interval = setInterval(() => {
                this.updateCountdown();
            }, 1000);
        }

        updateCountdown() {
            const now = new Date().getTime();
            const target = new Date(this.targetDate).getTime();
            const distance = target - now;

            if (distance < 0) {
                this.handleExpired();
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            this.$element.find('.gps-days').text(this.padZero(days));
            this.$element.find('.gps-hours').text(this.padZero(hours));
            this.$element.find('.gps-minutes').text(this.padZero(minutes));
            this.$element.find('.gps-seconds').text(this.padZero(seconds));

            // Add animation class
            this.$element.find('.gps-seconds').closest('.gps-countdown-item').addClass('tick');
            setTimeout(() => {
                this.$element.find('.gps-seconds').closest('.gps-countdown-item').removeClass('tick');
            }, 500);
        }

        handleExpired() {
            clearInterval(this.interval);

            // Show expired message
            const expiredMessage = this.$element.data('expired-message') || 'Event has started!';
            this.$element.find('.gps-countdown-timer').html(
                '<div class="gps-countdown-expired">' + expiredMessage + '</div>'
            );

            this.$element.addClass('expired');

            // Trigger custom event
            this.$element.trigger('gps-countdown-expired');
        }

        padZero(num) {
            return num < 10 ? '0' + num : num;
        }

        destroy() {
            if (this.interval) {
                clearInterval(this.interval);
            }
        }
    }

    // Initialize countdowns
    function initCountdowns() {
        $('.gps-countdown-shortcode, .gps-countdown-widget').each(function() {
            if (!$(this).data('gps-countdown-initialized')) {
                new GPSCountdown(this);
                $(this).data('gps-countdown-initialized', true);
            }
        });
    }

    $(document).ready(function() {
        initCountdowns();
    });

    // Expose to Elementor frontend
    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/gps-countdown-timer.default', function($scope) {
            new GPSCountdown($scope.find('.gps-countdown-widget'));
        });
    });

    // Re-initialize on AJAX content load
    $(document).on('gps-content-loaded', function() {
        initCountdowns();
    });

})(jQuery);
