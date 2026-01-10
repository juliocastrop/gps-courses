(function($) {
    'use strict';

    const SeminarCertificates = {
        seminarId: null,
        period: 'second_half',

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;

            // Seminar selector
            $('#seminar-selector').on('change', function() {
                self.seminarId = $(this).val();
                self.loadRegistrations();
            });

            // Period selector
            $('#period-selector').on('change', function() {
                self.period = $(this).val();
                if (self.seminarId) {
                    self.loadRegistrations();
                }
            });

            // Select all checkbox
            $('#select-all-header, #select-all-registrations').on('change', function() {
                const isChecked = $(this).prop('checked');
                $('.registration-checkbox').prop('checked', isChecked);
                self.updateBulkActions();
            });

            // Individual checkboxes
            $(document).on('change', '.registration-checkbox', function() {
                self.updateBulkActions();
            });

            // Generate certificate button
            $(document).on('click', '.generate-certificate-btn', function(e) {
                e.preventDefault();
                const registrationId = $(this).data('registration-id');
                self.generateCertificate(registrationId);
            });

            // Regenerate certificate button
            $(document).on('click', '.regenerate-certificate-btn', function(e) {
                e.preventDefault();
                const registrationId = $(this).data('registration-id');
                self.regenerateCertificate(registrationId);
            });

            // Send certificate button
            $(document).on('click', '.send-certificate-btn', function(e) {
                e.preventDefault();
                const registrationId = $(this).data('registration-id');
                self.sendCertificate(registrationId);
            });

            // Download certificate button
            $(document).on('click', '.download-certificate-btn', function(e) {
                e.preventDefault();
                const certificateUrl = $(this).data('certificate-url');
                window.open(certificateUrl, '_blank');
            });

            // Bulk actions
            $('#bulk-send-certificates').on('click', function() {
                self.bulkSendCertificates();
            });

            $('#bulk-regenerate-certificates').on('click', function() {
                self.bulkRegenerateCertificates();
            });

            // Generate all missing certificates
            $('#generate-all-certificates').on('click', function() {
                self.generateAllMissingCertificates();
            });
        },

        loadRegistrations: function() {
            const self = this;

            if (!self.seminarId) {
                return;
            }

            $('#loading-indicator').show();
            $('#registrations-table tbody').html('<tr><td colspan="7" class="loading">Loading...</td></tr>');

            $.ajax({
                url: gpsSeminarCertificates.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_get_seminar_registrations',
                    nonce: gpsSeminarCertificates.nonce,
                    seminar_id: self.seminarId,
                    period: self.period
                },
                success: function(response) {
                    if (response.success) {
                        self.renderRegistrations(response.data.registrations);
                        self.updateStatistics(response.data.registrations);
                        $('#bulk-actions').show();
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError(gpsSeminarCertificates.i18n.error);
                },
                complete: function() {
                    $('#loading-indicator').hide();
                }
            });
        },

        renderRegistrations: function(registrations) {
            const self = this;
            const $tbody = $('#registrations-table tbody');

            if (!registrations || registrations.length === 0) {
                $tbody.html('<tr><td colspan="7" class="no-registrations">No registrations found for this seminar and period.</td></tr>');
                $('#generate-all-certificates').hide();
                return;
            }

            let html = '';
            let hasEligibleWithoutCertificate = false;

            registrations.forEach(function(reg) {
                const statusClass = reg.has_certificate ? 'has-certificate' : (reg.eligible_for_certificate ? 'eligible' : 'not-eligible');
                const statusText = reg.has_certificate
                    ? '✅ Generated'
                    : (reg.eligible_for_certificate ? '⚠️ Eligible' : '❌ Not Eligible');

                if (reg.eligible_for_certificate && !reg.has_certificate) {
                    hasEligibleWithoutCertificate = true;
                }

                html += '<tr class="registration-row ' + statusClass + '">';
                html += '<td class="check-column"><input type="checkbox" class="registration-checkbox" value="' + reg.registration_id + '" ' + (reg.eligible_for_certificate ? '' : 'disabled') + '></td>';
                html += '<td><strong>' + self.escapeHtml(reg.user_name) + '</strong></td>';
                html += '<td>' + self.escapeHtml(reg.user_email) + '</td>';
                html += '<td class="text-center">' + reg.sessions_attended + '</td>';
                html += '<td class="text-center"><strong>' + reg.credits_earned + '</strong></td>';
                html += '<td class="status-cell"><span class="status-badge ' + statusClass + '">' + statusText + '</span></td>';
                html += '<td class="actions-cell">';

                if (reg.eligible_for_certificate) {
                    if (!reg.has_certificate) {
                        html += '<button type="button" class="button button-primary button-small generate-certificate-btn" data-registration-id="' + reg.registration_id + '">📜 Generate</button> ';
                    } else {
                        html += '<button type="button" class="button button-small regenerate-certificate-btn" data-registration-id="' + reg.registration_id + '">🔄 Regenerate</button> ';
                        html += '<button type="button" class="button button-small download-certificate-btn" data-certificate-url="' + reg.certificate_url + '">⬇️ Download</button> ';
                    }
                    html += '<button type="button" class="button button-primary button-small send-certificate-btn" data-registration-id="' + reg.registration_id + '">📧 Send</button>';
                } else {
                    html += '<span class="description">No credits earned in this period</span>';
                }

                html += '</td>';
                html += '</tr>';
            });

            $tbody.html(html);

            // Show/hide generate all button
            if (hasEligibleWithoutCertificate) {
                $('#generate-all-certificates').show();
            } else {
                $('#generate-all-certificates').hide();
            }
        },

        updateStatistics: function(registrations) {
            let total = registrations.length;
            let eligible = 0;
            let generated = 0;
            let sent = 0; // Note: We're counting generated as "sent" for now

            registrations.forEach(function(reg) {
                if (reg.eligible_for_certificate) {
                    eligible++;
                }
                if (reg.has_certificate) {
                    generated++;
                    sent++; // Assuming generated certificates were sent
                }
            });

            $('#stat-total').text(total);
            $('#stat-eligible').text(eligible);
            $('#stat-generated').text(generated);
            $('#stat-sent').text(sent);
        },

        updateBulkActions: function() {
            const checkedCount = $('.registration-checkbox:checked').length;
            $('#bulk-send-certificates, #bulk-regenerate-certificates').prop('disabled', checkedCount === 0);
        },

        generateCertificate: function(registrationId) {
            const self = this;
            const $btn = $('button[data-registration-id="' + registrationId + '"]');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Generating...');

            $.ajax({
                url: gpsSeminarCertificates.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_generate_seminar_certificate',
                    nonce: gpsSeminarCertificates.nonce,
                    registration_id: registrationId,
                    period: self.period
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Certificate generated successfully!');
                        self.loadRegistrations(); // Reload to update status
                    } else {
                        self.showError(response.data.message);
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    self.showError(gpsSeminarCertificates.i18n.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        regenerateCertificate: function(registrationId) {
            const self = this;

            if (!confirm('Are you sure you want to regenerate this certificate? This will overwrite the existing certificate.')) {
                return;
            }

            const $btn = $('.regenerate-certificate-btn[data-registration-id="' + registrationId + '"]');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Regenerating...');

            $.ajax({
                url: gpsSeminarCertificates.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_regenerate_seminar_certificate',
                    nonce: gpsSeminarCertificates.nonce,
                    registration_id: registrationId,
                    period: self.period
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.loadRegistrations();
                    } else {
                        self.showError(response.data.message);
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    self.showError(gpsSeminarCertificates.i18n.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        sendCertificate: function(registrationId) {
            const self = this;
            const $btn = $('.send-certificate-btn[data-registration-id="' + registrationId + '"]');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: gpsSeminarCertificates.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_send_seminar_certificate',
                    nonce: gpsSeminarCertificates.nonce,
                    registration_id: registrationId,
                    period: self.period
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        $btn.prop('disabled', false).text(originalText);
                    } else {
                        self.showError(response.data.message);
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    self.showError(gpsSeminarCertificates.i18n.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        bulkSendCertificates: function() {
            const self = this;
            const registrationIds = $('.registration-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (registrationIds.length === 0) {
                alert('Please select at least one registration');
                return;
            }

            const message = gpsSeminarCertificates.i18n.confirm_bulk.replace('{count}', registrationIds.length);
            if (!confirm(message)) {
                return;
            }

            const $btn = $('#bulk-send-certificates');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: gpsSeminarCertificates.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_bulk_send_seminar_certificates',
                    nonce: gpsSeminarCertificates.nonce,
                    registration_ids: registrationIds,
                    period: self.period
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        $('.registration-checkbox').prop('checked', false);
                        self.updateBulkActions();
                        self.loadRegistrations();
                    } else {
                        self.showError(response.data.message);
                    }
                    $btn.prop('disabled', false).text(originalText);
                },
                error: function() {
                    self.showError(gpsSeminarCertificates.i18n.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        bulkRegenerateCertificates: function() {
            const self = this;
            const registrationIds = $('.registration-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (registrationIds.length === 0) {
                alert('Please select at least one registration');
                return;
            }

            if (!confirm('Are you sure you want to regenerate ' + registrationIds.length + ' certificates? This will overwrite existing certificates.')) {
                return;
            }

            const $btn = $('#bulk-regenerate-certificates');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Regenerating...');

            $.ajax({
                url: gpsSeminarCertificates.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_bulk_regenerate_seminar_certificates',
                    nonce: gpsSeminarCertificates.nonce,
                    registration_ids: registrationIds,
                    period: self.period
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        $('.registration-checkbox').prop('checked', false);
                        self.updateBulkActions();
                        self.loadRegistrations();
                    } else {
                        self.showError(response.data.message);
                    }
                    $btn.prop('disabled', false).text(originalText);
                },
                error: function() {
                    self.showError(gpsSeminarCertificates.i18n.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        generateAllMissingCertificates: function() {
            const self = this;

            // Get all eligible registrations without certificates
            const eligibleIds = [];
            $('.registration-row.eligible').each(function() {
                const $checkbox = $(this).find('.registration-checkbox');
                if (!$checkbox.prop('disabled')) {
                    eligibleIds.push($checkbox.val());
                }
            });

            if (eligibleIds.length === 0) {
                alert('No eligible registrations without certificates found');
                return;
            }

            if (!confirm('Generate certificates for ' + eligibleIds.length + ' participants?')) {
                return;
            }

            const $btn = $('#generate-all-certificates');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Generating...');

            $.ajax({
                url: gpsSeminarCertificates.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_bulk_regenerate_seminar_certificates',
                    nonce: gpsSeminarCertificates.nonce,
                    registration_ids: eligibleIds,
                    period: self.period
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        self.loadRegistrations();
                    } else {
                        self.showError(response.data.message);
                    }
                    $btn.prop('disabled', false).text(originalText);
                },
                error: function() {
                    self.showError(gpsSeminarCertificates.i18n.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        showError: function(message) {
            this.showNotice(message, 'error');
        },

        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SeminarCertificates.init();
    });

})(jQuery);
