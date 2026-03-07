(function($) {
    'use strict';

    var GPSTicketsAdmin = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.gps-assign-attendee-btn', this.openModal.bind(this));
            $(document).on('click', '.gps-modal-close, .gps-modal-close-btn, .gps-modal-overlay', this.closeModal.bind(this));
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') GPSTicketsAdmin.closeModal();
            });
            $('#gps-save-attendee').on('click', this.saveAttendee.bind(this));
            $('#gps-clear-attendee').on('click', this.clearAttendee.bind(this));
        },

        openModal: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);

            var ticketId = $btn.data('ticket-id');
            var buyerName = $btn.data('buyer-name');
            var buyerEmail = $btn.data('buyer-email');
            var designatedName = $btn.data('designated-name') || '';
            var designatedEmail = $btn.data('designated-email') || '';
            var hasDesignated = $btn.data('has-designated') === 1 || $btn.data('has-designated') === '1';

            $('#assign-ticket-id').val(ticketId);
            $('#assign-buyer-info').html('<strong>' + this.escHtml(buyerName) + '</strong> &mdash; ' + this.escHtml(buyerEmail));
            $('#assign-attendee-name').val(designatedName);
            $('#assign-attendee-email').val(designatedEmail);
            $('#assign-regenerate-qr').prop('checked', true);
            $('#assign-send-email').prop('checked', true);

            // Show/hide Remove Attendee button
            if (hasDesignated) {
                $('#gps-clear-attendee').show();
            } else {
                $('#gps-clear-attendee').hide();
            }

            $('#gps-assign-modal').show();
            $('#assign-attendee-name').focus();
        },

        closeModal: function() {
            $('#gps-assign-modal').hide();
        },

        saveAttendee: function(e) {
            e.preventDefault();

            var ticketId = $('#assign-ticket-id').val();
            var name = $('#assign-attendee-name').val().trim();
            var email = $('#assign-attendee-email').val().trim();

            if (!name || !email) {
                alert('Please fill in both name and email.');
                return;
            }

            if (!confirm('Assign "' + name + '" (' + email + ') as the attendee for this ticket?')) {
                return;
            }

            var $btn = $('#gps-save-attendee');
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: gpsTicketsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_assign_attendee',
                    nonce: gpsTicketsAdmin.nonce,
                    ticket_id: ticketId,
                    attendee_name: name,
                    attendee_email: email,
                    regenerate_qr: $('#assign-regenerate-qr').is(':checked') ? '1' : '0',
                    send_email: $('#assign-send-email').is(':checked') ? '1' : '0',
                },
                success: function(response) {
                    if (response.success) {
                        GPSTicketsAdmin.updateRow(ticketId, response.data);
                        GPSTicketsAdmin.closeModal();

                        var msg = response.data.message;
                        if (response.data.warnings && response.data.warnings.length > 0) {
                            msg += '\n\nNote: ' + response.data.warnings.join('\n');
                        }
                        alert(msg);
                    } else {
                        alert(response.data.message || 'An error occurred.');
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Attendee');
                }
            });
        },

        clearAttendee: function(e) {
            e.preventDefault();

            if (!confirm('Remove the designated attendee? The buyer will be used as the attendee.')) {
                return;
            }

            var ticketId = $('#assign-ticket-id').val();
            var $btn = $('#gps-clear-attendee');
            $btn.prop('disabled', true).text('Removing...');

            $.ajax({
                url: gpsTicketsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_assign_attendee',
                    nonce: gpsTicketsAdmin.nonce,
                    ticket_id: ticketId,
                    clear_attendee: '1',
                },
                success: function(response) {
                    if (response.success) {
                        GPSTicketsAdmin.updateRow(ticketId, response.data);
                        GPSTicketsAdmin.closeModal();
                        alert(response.data.message);
                    } else {
                        alert(response.data.message || 'An error occurred.');
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Remove Attendee');
                }
            });
        },

        updateRow: function(ticketId, data) {
            var $row = $('tr[data-ticket-id="' + ticketId + '"]');
            if (!$row.length) return;

            var $cell = $row.find('.gps-attendee-cell');
            var $btn = $row.find('.gps-assign-attendee-btn');

            if (data.has_designated) {
                $cell.html(
                    '<span class="gps-designated-badge">Designated</span><br>' +
                    '<span class="gps-attendee-name">' + this.escHtml(data.designated_name) + '</span>' +
                    '<br><small class="gps-attendee-email" style="color: #666;">' + this.escHtml(data.designated_email) + '</small>'
                );
                $btn.data('designated-name', data.designated_name);
                $btn.data('designated-email', data.designated_email);
                $btn.data('has-designated', '1');
                $btn.find('.dashicons').nextAll().remove();
                $btn.append(' Edit Attendee');
            } else {
                $cell.html('<span class="gps-same-as-buyer">= Buyer</span>');
                $btn.data('designated-name', '');
                $btn.data('designated-email', '');
                $btn.data('has-designated', '0');
                $btn.find('.dashicons').nextAll().remove();
                $btn.append(' Assign Attendee');
            }
        },

        escHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        GPSTicketsAdmin.init();
    });

})(jQuery);
