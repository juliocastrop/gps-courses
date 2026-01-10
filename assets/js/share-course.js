/**
 * GPS Share Course
 * Professional modal-based social sharing
 */
(function($) {
    'use strict';

    class GPSShareCourse {
        constructor(wrapper) {
            this.$wrapper = $(wrapper);
            this.$button = this.$wrapper.find('.gps-share-button');
            this.$modal = this.$wrapper.find('.gps-share-modal');
            this.$modalClose = this.$wrapper.find('.gps-share-modal-close');
            this.$copyButton = this.$wrapper.find('.gps-copy-url-button');

            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Open modal
            this.$button.on('click', (e) => {
                e.preventDefault();
                this.openModal();
            });

            // Close modal
            this.$modalClose.on('click', (e) => {
                e.preventDefault();
                this.closeModal();
            });

            // Close on overlay click
            this.$modal.on('click', (e) => {
                if ($(e.target).hasClass('gps-share-modal')) {
                    this.closeModal();
                }
            });

            // Close on ESC key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.$modal.is(':visible')) {
                    this.closeModal();
                }
            });

            // Copy URL
            this.$copyButton.on('click', (e) => {
                e.preventDefault();
                this.copyUrl();
            });

            // Track share clicks
            this.$wrapper.find('.gps-share-icon').on('click', (e) => {
                const platform = $(e.currentTarget).data('platform');
                this.trackShare(platform);
            });
        }

        openModal() {
            this.$modal.fadeIn(300);
            $('body').addClass('gps-modal-open');
        }

        closeModal() {
            this.$modal.fadeOut(300);
            $('body').removeClass('gps-modal-open');
        }

        copyUrl() {
            const url = this.$copyButton.data('url');
            const $input = this.$wrapper.find('.gps-share-url-input');

            // Select and copy
            $input.select();
            $input[0].setSelectionRange(0, 99999); // For mobile devices

            try {
                // Modern clipboard API
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(() => {
                        this.showCopyFeedback();
                    });
                } else {
                    // Fallback for older browsers
                    document.execCommand('copy');
                    this.showCopyFeedback();
                }
            } catch (err) {
                console.error('GPS Share: Copy failed', err);
                alert('Could not copy URL. Please copy manually.');
            }
        }

        showCopyFeedback() {
            const originalText = this.$copyButton.text();
            this.$copyButton
                .text('Copied!')
                .addClass('gps-copied');

            setTimeout(() => {
                this.$copyButton
                    .text(originalText)
                    .removeClass('gps-copied');
            }, 2000);
        }

        trackShare(platform) {
            // Track share event (can be extended with analytics)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'share', {
                    'method': platform,
                    'content_type': 'course',
                    'item_id': this.$button.data('post-id')
                });
            }

            console.log('GPS Share: Shared on ' + platform);
        }
    }

    // Expose to global scope
    window.GPSShareCourse = GPSShareCourse;

    // Auto-initialize on DOM ready
    $(document).ready(function() {
        $('.gps-share-course-wrapper').each(function() {
            new GPSShareCourse(this);
        });
    });

})(jQuery);
