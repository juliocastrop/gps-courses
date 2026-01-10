/**
 * GPS Courses - Certificate Management
 * Handles certificate generation and sending for event attendees
 */

(function($) {
    'use strict';

    const CertificateManager = {
        selectedEventId: null,
        selectedAttendees: [],
        isProcessing: false,

        /**
         * Initialize the certificate manager
         */
        init: function() {
            this.bindEvents();
            console.log('Certificate Manager initialized');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Event selection
            $('#event-selector').on('change', this.onEventChange.bind(this));

            // Select all checkbox
            $('#select-all-attendees').on('change', this.onSelectAllChange.bind(this));

            // Individual attendee checkboxes
            $(document).on('change', '.attendee-checkbox', this.onAttendeeCheckboxChange.bind(this));

            // Bulk send button
            $('#bulk-send-certificates').on('click', this.onBulkSendClick.bind(this));

            // Individual send buttons
            $(document).on('click', '.send-certificate-btn', this.onIndividualSendClick.bind(this));

            // Individual generate buttons
            $(document).on('click', '.generate-certificate-btn', this.onGenerateCertificateClick.bind(this));

            // Regenerate certificate buttons
            $(document).on('click', '.regenerate-certificate-btn', this.onRegenerateCertificateClick.bind(this));

            // Bulk regenerate button
            $('#bulk-regenerate-certificates').on('click', this.onBulkRegenerateClick.bind(this));

            // Download certificate buttons
            $(document).on('click', '.download-certificate-btn', this.onDownloadCertificateClick.bind(this));
        },

        /**
         * Handle event selection change
         */
        onEventChange: function(e) {
            const eventId = $(e.target).val();

            if (!eventId) {
                this.clearAttendeeList();
                return;
            }

            this.selectedEventId = eventId;
            this.selectedAttendees = [];
            this.loadEventAttendees(eventId);
        },

        /**
         * Load attendees for selected event
         */
        loadEventAttendees: function(eventId) {
            this.showLoading(true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_get_event_attendees',
                    nonce: gpsCertificates.nonce,
                    event_id: eventId
                },
                success: this.onAttendeesLoaded.bind(this),
                error: this.onAttendeesLoadError.bind(this)
            });
        },

        /**
         * Handle successful attendee load
         */
        onAttendeesLoaded: function(response) {
            this.showLoading(false);

            if (!response.success) {
                this.showMessage('error', response.data || 'Failed to load attendees');
                this.clearAttendeeList();
                return;
            }

            this.renderAttendeeList(response.data);
            this.updateStatistics(response.data);
        },

        /**
         * Handle attendee load error
         */
        onAttendeesLoadError: function(xhr, status, error) {
            this.showLoading(false);
            this.showMessage('error', 'Failed to load attendees: ' + error);
            this.clearAttendeeList();
        },

        /**
         * Render attendee list
         */
        renderAttendeeList: function(attendees) {
            const $tbody = $('#attendees-table tbody');
            $tbody.empty();

            if (attendees.length === 0) {
                $tbody.append(
                    '<tr><td colspan="6" class="no-attendees">No checked-in attendees found for this event.</td></tr>'
                );
                $('#bulk-actions').hide();
                return;
            }

            attendees.forEach(attendee => {
                const row = this.createAttendeeRow(attendee);
                $tbody.append(row);
            });

            $('#bulk-actions').show();
            $('#select-all-attendees').prop('checked', false);
        },

        /**
         * Create attendee table row
         */
        createAttendeeRow: function(attendee) {
            const certificateStatus = attendee.certificate_sent_at ? 'sent' : 'pending';
            const statusBadge = certificateStatus === 'sent'
                ? '<span class="status-badge status-sent">Sent</span>'
                : '<span class="status-badge status-pending">Pending</span>';

            const sentDate = attendee.certificate_sent_at
                ? new Date(attendee.certificate_sent_at).toLocaleString()
                : 'Not sent';

            const actions = this.createActionButtons(attendee, certificateStatus);

            return `
                <tr data-ticket-id="${attendee.ticket_id}">
                    <td>
                        <input type="checkbox"
                               class="attendee-checkbox"
                               value="${attendee.ticket_id}"
                               data-user-id="${attendee.user_id}"
                               ${certificateStatus === 'sent' ? '' : ''}>
                    </td>
                    <td><strong>${this.escapeHtml(attendee.user_name)}</strong></td>
                    <td>${this.escapeHtml(attendee.user_email)}</td>
                    <td>${this.escapeHtml(attendee.ticket_code)}</td>
                    <td>${statusBadge}<br><small>${sentDate}</small></td>
                    <td class="actions-column">${actions}</td>
                </tr>
            `;
        },

        /**
         * Create action buttons for attendee row
         */
        createActionButtons: function(attendee, status) {
            let buttons = '';

            if (!attendee.certificate_path) {
                // No certificate generated yet
                buttons += `
                    <button type="button"
                            class="button button-small generate-certificate-btn"
                            data-ticket-id="${attendee.ticket_id}">
                        Generate
                    </button>
                `;
            } else {
                // Certificate exists
                buttons += `
                    <button type="button"
                            class="button button-small button-primary send-certificate-btn"
                            data-ticket-id="${attendee.ticket_id}"
                            data-user-email="${this.escapeHtml(attendee.user_email)}">
                        ${status === 'sent' ? 'Resend' : 'Send'}
                    </button>
                    <button type="button"
                            class="button button-small regenerate-certificate-btn"
                            data-ticket-id="${attendee.ticket_id}">
                        Regenerate
                    </button>
                    <button type="button"
                            class="button button-small download-certificate-btn"
                            data-certificate-url="${attendee.certificate_url}">
                        Download
                    </button>
                `;
            }

            return buttons;
        },

        /**
         * Handle select all checkbox change
         */
        onSelectAllChange: function(e) {
            const isChecked = $(e.target).prop('checked');
            $('.attendee-checkbox').prop('checked', isChecked);
            this.updateSelectedAttendees();
        },

        /**
         * Handle individual checkbox change
         */
        onAttendeeCheckboxChange: function(e) {
            this.updateSelectedAttendees();

            // Update select all checkbox state
            const totalCheckboxes = $('.attendee-checkbox').length;
            const checkedCheckboxes = $('.attendee-checkbox:checked').length;
            $('#select-all-attendees').prop('checked', totalCheckboxes === checkedCheckboxes);
        },

        /**
         * Update selected attendees array
         */
        updateSelectedAttendees: function() {
            this.selectedAttendees = [];
            $('.attendee-checkbox:checked').each((i, checkbox) => {
                this.selectedAttendees.push({
                    ticket_id: $(checkbox).val(),
                    user_id: $(checkbox).data('user-id')
                });
            });

            // Update bulk action buttons state
            const hasSelection = this.selectedAttendees.length > 0;
            $('#bulk-send-certificates').prop('disabled', !hasSelection);
            $('#bulk-regenerate-certificates').prop('disabled', !hasSelection);
        },

        /**
         * Handle bulk send button click
         */
        onBulkSendClick: function(e) {
            e.preventDefault();

            if (this.selectedAttendees.length === 0) {
                this.showMessage('error', 'Please select at least one attendee');
                return;
            }

            // Show confirmation dialog
            const count = this.selectedAttendees.length;
            const message = `Are you sure you want to send certificates to ${count} attendee${count > 1 ? 's' : ''}?\n\nThis will:\n- Generate certificates if not already generated\n- Send emails with certificate attachments\n- Update certificate status`;

            if (!confirm(message)) {
                return;
            }

            this.bulkSendCertificates();
        },

        /**
         * Send certificates in bulk
         */
        bulkSendCertificates: function() {
            if (this.isProcessing) {
                return;
            }

            this.isProcessing = true;
            const ticketIds = this.selectedAttendees.map(a => a.ticket_id);

            // Show progress
            this.showProgress('Sending certificates...', 0);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_bulk_send_certificates',
                    nonce: gpsCertificates.nonce,
                    ticket_ids: ticketIds
                },
                success: this.onBulkSendComplete.bind(this),
                error: this.onBulkSendError.bind(this),
                complete: () => {
                    this.isProcessing = false;
                    this.hideProgress();
                }
            });
        },

        /**
         * Handle bulk send completion
         */
        onBulkSendComplete: function(response) {
            if (!response.success) {
                this.showMessage('error', response.data || 'Failed to send certificates');
                return;
            }

            const results = response.data;
            const successCount = results.success.length;
            const errorCount = results.errors.length;

            let message = `Successfully sent ${successCount} certificate${successCount !== 1 ? 's' : ''}`;

            if (errorCount > 0) {
                message += `\n${errorCount} failed:\n`;
                results.errors.forEach(error => {
                    message += `- ${error}\n`;
                });
                this.showMessage('warning', message);
            } else {
                this.showMessage('success', message);
            }

            // Reload attendee list
            this.loadEventAttendees(this.selectedEventId);
        },

        /**
         * Handle bulk send error
         */
        onBulkSendError: function(xhr, status, error) {
            this.showMessage('error', 'Failed to send certificates: ' + error);
        },

        /**
         * Handle individual send button click
         */
        onIndividualSendClick: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const ticketId = $btn.data('ticket-id');
            const userEmail = $btn.data('user-email');

            if (!confirm(`Send certificate to ${userEmail}?`)) {
                return;
            }

            this.sendIndividualCertificate(ticketId, $btn);
        },

        /**
         * Send individual certificate
         */
        sendIndividualCertificate: function(ticketId, $btn) {
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_send_certificate',
                    nonce: gpsCertificates.nonce,
                    ticket_id: ticketId
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', 'Certificate sent successfully');
                        // Reload attendee list to update status
                        this.loadEventAttendees(this.selectedEventId);
                    } else {
                        this.showMessage('error', response.data || 'Failed to send certificate');
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('error', 'Failed to send certificate: ' + error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle generate certificate button click
         */
        onGenerateCertificateClick: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const ticketId = $btn.data('ticket-id');

            this.generateCertificate(ticketId, $btn);
        },

        /**
         * Generate individual certificate
         */
        generateCertificate: function(ticketId, $btn) {
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Generating...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_generate_certificate',
                    nonce: gpsCertificates.nonce,
                    ticket_id: ticketId
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', 'Certificate generated successfully');
                        // Reload attendee list to show new buttons
                        this.loadEventAttendees(this.selectedEventId);
                    } else {
                        this.showMessage('error', response.data || 'Failed to generate certificate');
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('error', 'Failed to generate certificate: ' + error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle regenerate certificate button click
         */
        onRegenerateCertificateClick: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const ticketId = $btn.data('ticket-id');

            if (!confirm('Are you sure you want to regenerate this certificate? This will replace the existing certificate with a new one using the current settings.')) {
                return;
            }

            this.regenerateCertificate(ticketId, $btn);
        },

        /**
         * Regenerate individual certificate
         */
        regenerateCertificate: function(ticketId, $btn) {
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Regenerating...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_regenerate_certificate',
                    nonce: gpsCertificates.nonce,
                    ticket_id: ticketId
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('success', 'Certificate regenerated successfully');
                        // Reload attendee list to update
                        this.loadEventAttendees(this.selectedEventId);
                    } else {
                        this.showMessage('error', response.data || 'Failed to regenerate certificate');
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('error', 'Failed to regenerate certificate: ' + error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle bulk regenerate button click
         */
        onBulkRegenerateClick: function(e) {
            e.preventDefault();

            if (this.selectedAttendees.length === 0) {
                this.showMessage('error', 'Please select at least one attendee');
                return;
            }

            // Show confirmation dialog
            const count = this.selectedAttendees.length;
            const message = `Are you sure you want to regenerate certificates for ${count} attendee${count > 1 ? 's' : ''}?\n\nThis will:\n- Replace existing certificates with new ones\n- Use current certificate settings\n- Not send emails (use "Send Selected" to send after)`;

            if (!confirm(message)) {
                return;
            }

            this.bulkRegenerateCertificates();
        },

        /**
         * Regenerate certificates in bulk
         */
        bulkRegenerateCertificates: function() {
            if (this.isProcessing) {
                return;
            }

            this.isProcessing = true;
            const ticketIds = this.selectedAttendees.map(a => a.ticket_id);

            // Show progress
            this.showProgress('Regenerating certificates...', 0);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_bulk_regenerate_certificates',
                    nonce: gpsCertificates.nonce,
                    ticket_ids: ticketIds
                },
                success: this.onBulkRegenerateComplete.bind(this),
                error: this.onBulkRegenerateError.bind(this),
                complete: () => {
                    this.isProcessing = false;
                    this.hideProgress();
                }
            });
        },

        /**
         * Handle bulk regenerate completion
         */
        onBulkRegenerateComplete: function(response) {
            if (!response.success) {
                this.showMessage('error', response.data || 'Failed to regenerate certificates');
                return;
            }

            const results = response.data;
            const successCount = results.success.length;
            const errorCount = results.errors.length;

            let message = `Successfully regenerated ${successCount} certificate${successCount !== 1 ? 's' : ''}`;

            if (errorCount > 0) {
                message += `\n${errorCount} failed:\n`;
                results.errors.forEach(error => {
                    message += `- ${error}\n`;
                });
                this.showMessage('warning', message);
            } else {
                this.showMessage('success', message);
            }

            // Reload attendee list
            this.loadEventAttendees(this.selectedEventId);
        },

        /**
         * Handle bulk regenerate error
         */
        onBulkRegenerateError: function(xhr, status, error) {
            this.showMessage('error', 'Failed to regenerate certificates: ' + error);
        },

        /**
         * Handle download certificate button click
         */
        onDownloadCertificateClick: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const certificateUrl = $btn.data('certificate-url');

            if (certificateUrl) {
                window.open(certificateUrl, '_blank');
            } else {
                this.showMessage('error', 'Certificate URL not found');
            }
        },

        /**
         * Update statistics display
         */
        updateStatistics: function(attendees) {
            const total = attendees.length;
            const sent = attendees.filter(a => a.certificate_sent_at).length;
            const pending = total - sent;

            $('#stat-total').text(total);
            $('#stat-sent').text(sent);
            $('#stat-pending').text(pending);
        },

        /**
         * Clear attendee list
         */
        clearAttendeeList: function() {
            $('#attendees-table tbody').empty();
            $('#bulk-actions').hide();
            $('#stat-total, #stat-sent, #stat-pending').text('0');
        },

        /**
         * Show loading indicator
         */
        showLoading: function(show) {
            if (show) {
                $('#loading-indicator').show();
                $('#attendees-table').css('opacity', '0.5');
            } else {
                $('#loading-indicator').hide();
                $('#attendees-table').css('opacity', '1');
            }
        },

        /**
         * Show progress message
         */
        showProgress: function(message, percent) {
            let $progress = $('#bulk-progress');
            if ($progress.length === 0) {
                $progress = $('<div id="bulk-progress" class="notice notice-info"><p></p></div>');
                $('#attendees-table').before($progress);
            }
            $progress.find('p').text(message);
            $progress.show();
        },

        /**
         * Hide progress message
         */
        hideProgress: function() {
            $('#bulk-progress').remove();
        },

        /**
         * Show message to user
         */
        showMessage: function(type, message) {
            const noticeClass = type === 'error' ? 'notice-error' :
                              type === 'warning' ? 'notice-warning' :
                              'notice-success';

            const $notice = $(`
                <div class="notice ${noticeClass} is-dismissible">
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `);

            // Remove existing notices
            $('.wrap .notice').remove();

            // Add new notice
            $('.wrap h1').after($notice);

            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.remove();
            });

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);

            // Scroll to top to show message
            $('html, body').animate({ scrollTop: 0 }, 300);
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#gps-certificates-page').length > 0) {
            CertificateManager.init();
        }
    });

})(jQuery);
