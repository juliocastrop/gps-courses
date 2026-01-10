/**
 * GPS Courses - Admin Reports
 */

(function($) {
    'use strict';

    const GPSReports = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Export buttons
            $('.gps-export-btn').on('click', this.handleExport.bind(this));

            // Email blast form
            $('#gps-email-blast-form').on('submit', this.handleEmailBlast.bind(this));

            // Bulk operations form
            $('#gps-bulk-operations-form').on('submit', this.handleBulkOperation.bind(this));
        },

        handleExport: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const type = $button.data('type');
            const eventId = $('#export-event').val();

            $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + gpsReports.i18n.exporting);

            $.ajax({
                url: gpsReports.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_export_' + type,
                    nonce: gpsReports.nonce,
                    event_id: eventId
                },
                success: (response) => {
                    $button.prop('disabled', false).html($button.data('original-text') || $button.text().replace(gpsReports.i18n.exporting, ''));

                    if (response.success) {
                        // Create download link
                        const csvContent = atob(response.data.data);
                        const blob = new Blob([csvContent], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);

                        this.showNotice('success', sprintf('%d records exported successfully.', response.data.count));
                    } else {
                        this.showNotice('error', response.data.message || gpsReports.i18n.error);
                    }
                },
                error: (xhr, status, error) => {
                    $button.prop('disabled', false).html($button.data('original-text') || $button.text().replace(gpsReports.i18n.exporting, ''));
                    console.error('Export error:', error);
                    this.showNotice('error', gpsReports.i18n.error);
                }
            });
        },

        handleEmailBlast: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $button = $form.find('button[type="submit"]');
            const $result = $('#email-blast-result');

            const formData = {
                action: 'gps_send_email_blast',
                nonce: gpsReports.nonce,
                event_id: $('#email-event').val(),
                recipients: $('#email-recipients').val(),
                subject: $('#email-subject').val(),
                message: tinymce.get('email-message') ? tinymce.get('email-message').getContent() : $('#email-message').val()
            };

            if (!formData.event_id || !formData.subject || !formData.message) {
                this.showNotice('error', 'Please fill all required fields.', $result);
                return;
            }

            // Confirm
            const recipientText = $('#email-recipients option:selected').text();
            if (!confirm('Send email to ' + recipientText + ' for this event?')) {
                return;
            }

            $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + gpsReports.i18n.sending);
            $result.html('');

            $.ajax({
                url: gpsReports.ajaxurl,
                type: 'POST',
                data: formData,
                success: (response) => {
                    $button.prop('disabled', false).html($button.data('original-text') || $button.text().replace(gpsReports.i18n.sending, ''));

                    if (response.success) {
                        this.showNotice('success', response.data.message, $result);
                        $form[0].reset();
                    } else {
                        this.showNotice('error', response.data.message || gpsReports.i18n.error, $result);
                    }
                },
                error: (xhr, status, error) => {
                    $button.prop('disabled', false).html($button.data('original-text') || $button.text().replace(gpsReports.i18n.sending, ''));
                    console.error('Email blast error:', error);
                    this.showNotice('error', gpsReports.i18n.error, $result);
                }
            });
        },

        handleBulkOperation: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $button = $form.find('button[type="submit"]');
            const $result = $('#bulk-operation-result');

            const eventId = $('#bulk-event').val();
            const operation = $('#bulk-operation').val();

            if (!eventId || !operation) {
                this.showNotice('error', 'Please select event and operation.', $result);
                return;
            }

            // Confirm
            if (!confirm(gpsReports.i18n.confirm_bulk)) {
                return;
            }

            $button.prop('disabled', true).html('<span class="spinner is-active"></span> Processing...');
            $result.html('');

            $.ajax({
                url: gpsReports.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_bulk_award_credits',
                    nonce: gpsReports.nonce,
                    event_id: eventId,
                    operation: operation
                },
                success: (response) => {
                    $button.prop('disabled', false).html($button.data('original-text') || 'Execute Bulk Operation');

                    if (response.success) {
                        this.showNotice('success', response.data.message, $result);
                        $form[0].reset();

                        // Reload page after 2 seconds to show updated stats
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showNotice('error', response.data.message || gpsReports.i18n.error, $result);
                    }
                },
                error: (xhr, status, error) => {
                    $button.prop('disabled', false).html($button.data('original-text') || 'Execute Bulk Operation');
                    console.error('Bulk operation error:', error);
                    this.showNotice('error', gpsReports.i18n.error, $result);
                }
            });
        },

        showNotice: function(type, message, $container) {
            const typeClass = type === 'error' ? 'notice-error' : (type === 'success' ? 'notice-success' : 'notice-info');
            const html = '<div class="notice ' + typeClass + ' is-dismissible"><p>' + message + '</p></div>';

            if ($container) {
                $container.html(html);
            } else {
                $('.gps-reports-page h1').after(html);
            }

            // Auto-hide after 5 seconds
            setTimeout(() => {
                if ($container) {
                    $container.find('.notice').fadeOut();
                } else {
                    $('.gps-reports-page .notice').fadeOut();
                }
            }, 5000);
        }
    };

    // Helper function for string formatting
    function sprintf(str, ...args) {
        return str.replace(/%d/g, () => args.shift());
    }

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.gps-reports-page').length) {
            GPSReports.init();
        }
    });

})(jQuery);
