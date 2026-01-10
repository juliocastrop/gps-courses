/**
 * GPS Courses - Admin Attendance Scanner
 */

(function($) {
    'use strict';

    let html5QrCode = null;
    let scannerActive = false;

    const GPSAttendance = {
        init: function() {
            this.bindEvents();
            this.loadStats();
            this.loadRecentCheckins();

            // Auto-refresh recent check-ins every 30 seconds
            setInterval(() => {
                this.loadRecentCheckins();
                this.loadStats();
            }, 30000);
        },

        bindEvents: function() {
            console.log('GPS Attendance: Binding events');
            console.log('GPS Attendance: Event selector found:', $('#gps-select-event').length);

            // Event selector
            $('#gps-select-event').on('change', this.handleEventSelection.bind(this));

            // Mode switching
            $('.gps-scan-mode-btn').on('click', this.switchMode.bind(this));
            $('.mode-btn').on('click', this.switchScannerMode.bind(this));

            // QR Scanner controls
            $('#gps-start-scanner').on('click', this.startScanner.bind(this));
            $('#gps-stop-scanner').on('click', this.stopScanner.bind(this));

            // Manual check-in
            $('#gps-manual-checkin-form').on('submit', this.handleManualCheckin.bind(this));
            $('#btn-manual-checkin').on('click', this.handleManualCheckinButton.bind(this));

            // Search attendees
            $('#gps-search-attendees-form').on('submit', this.handleSearch.bind(this));
            $('#gps-search-query, #search-attendee').on('input', this.handleSearchInput.bind(this));
            $('#btn-search').on('click', this.handleSearchButton.bind(this));

            // Bulk check-in from search results
            $(document).on('click', '.gps-checkin-attendee', this.checkInFromSearch.bind(this));
        },

        handleEventSelection: function(e) {
            const eventId = $(e.currentTarget).val();
            console.log('GPS Attendance: Event selected:', eventId);

            if (eventId) {
                console.log('GPS Attendance: Showing scanner interface');

                // Show scanner interface and stats
                $('.gps-scanner-body').slideDown();
                $('.gps-stats-summary').slideDown();

                // Store selected event ID
                this.selectedEventId = eventId;

                // Load event-specific stats
                this.loadEventStats(eventId);
            } else {
                console.log('GPS Attendance: Hiding scanner interface');

                // Hide scanner interface
                $('.gps-scanner-body').slideUp();
                $('.gps-stats-summary').slideUp();

                // Stop scanner if active
                if (scannerActive) {
                    this.stopScanner();
                }

                this.selectedEventId = null;
            }
        },

        switchScannerMode: function(e) {
            e.preventDefault();
            const mode = $(e.currentTarget).data('mode');
            console.log('GPS Attendance: Switching to mode:', mode);

            // Update active button
            $('.mode-btn').removeClass('active');
            $(e.currentTarget).addClass('active');

            // Hide all modes first
            $('.scanner-mode').hide();

            // Show selected mode
            $('.scanner-mode-' + mode).show();
            console.log('GPS Attendance: Showing mode element:', $('.scanner-mode-' + mode).length);

            // Stop scanner when switching away from QR mode
            if (mode !== 'qr' && scannerActive) {
                console.log('GPS Attendance: Stopping scanner (switching away from QR)');
                this.stopScanner();
            }

            // Initialize QR scanner if switching to QR mode
            if (mode === 'qr' && !scannerActive) {
                console.log('GPS Attendance: QR mode selected (scanner not active)');
                // Optionally auto-start scanner
                // $('#gps-start-scanner').click();
            }
        },

        handleManualCheckinButton: function(e) {
            e.preventDefault();

            const ticketCode = $('#manual-ticket-code').val().trim();

            if (!ticketCode) {
                alert(gpsAttendance.i18n.enter_ticket_code);
                return;
            }

            this.performManualCheckin(ticketCode);
        },

        performManualCheckin: function(ticketCode) {
            console.log('GPS Attendance: Manual check-in for ticket:', ticketCode);
            console.log('GPS Attendance: Selected event ID:', this.selectedEventId);

            const $result = $('.scanner-mode-manual .scan-result');

            if (!this.selectedEventId) {
                alert('Please select an event first');
                return;
            }

            $result.show();
            $result.html('<div class="gps-processing"><span class="spinner is-active"></span> ' + gpsAttendance.i18n.processing + '</div>');

            $.ajax({
                url: gpsAttendance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_manual_checkin',
                    nonce: gpsAttendance.nonce,
                    ticket_code: ticketCode,
                    event_id: this.selectedEventId
                },
                success: (response) => {
                    console.log('GPS Attendance: Manual check-in response:', response);

                    if (response.success) {
                        this.showManualResult(response.data, $result);
                        $('#manual-ticket-code').val('');
                        this.loadRecentCheckins();
                        this.loadEventStats(this.selectedEventId);
                        this.successFeedback();
                    } else {
                        const message = response.data && response.data.message ? response.data.message : 'Check-in failed';
                        this.showMessage('error', message, $result);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Manual check-in AJAX error:', error);
                    this.showMessage('error', gpsAttendance.i18n.ajax_error, $result);
                }
            });
        },

        handleSearchButton: function(e) {
            e.preventDefault();

            const query = $('#search-attendee').val().trim();

            if (query.length < 3) {
                alert(gpsAttendance.i18n.search_min_chars);
                return;
            }

            this.performSearch(query);
        },

        loadEventStats: function(eventId) {
            if (!eventId) return;

            $.ajax({
                url: gpsAttendance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_get_event_stats',
                    nonce: gpsAttendance.nonce,
                    event_id: eventId
                },
                success: (response) => {
                    if (response.success) {
                        $('#total-tickets').text(response.data.total || 0);
                        $('#checked-in').text(response.data.checked_in || 0);
                        $('#remaining').text(response.data.remaining || 0);
                    }
                }
            });
        },

        switchMode: function(e) {
            e.preventDefault();
            const mode = $(e.currentTarget).data('mode');

            // Update active button
            $('.gps-scan-mode-btn').removeClass('active');
            $(e.currentTarget).addClass('active');

            // Show corresponding content
            $('.gps-scan-mode-content').removeClass('active');
            $('#gps-mode-' + mode).addClass('active');

            // Stop scanner when switching away from QR mode
            if (mode !== 'qr' && scannerActive) {
                this.stopScanner();
            }
        },

        startScanner: function(e) {
            e.preventDefault();
            console.log('GPS Attendance: Starting QR scanner');

            // Check if event is selected
            if (!this.selectedEventId) {
                alert('Please select an event first before starting the scanner');
                return;
            }

            if (scannerActive) {
                console.log('GPS Attendance: Scanner already active');
                return;
            }

            const $button = $(e.currentTarget);
            const $reader = $('#gps-qr-reader');

            $button.prop('disabled', true).text(gpsAttendance.i18n.starting);
            $reader.show(); // Show the reader div

            // Initialize html5-qrcode scanner
            html5QrCode = new Html5Qrcode("gps-qr-reader");

            // Enhanced scanner configuration
            const config = {
                fps: 10, // Frames per second for scanning
                qrbox: { width: 250, height: 250 }, // QR code scanning box size
                aspectRatio: 1.0 // Aspect ratio
            };

            // Try to start with back camera first
            html5QrCode.start(
                { facingMode: "environment" }, // Use back camera
                config,
                this.onScanSuccess.bind(this),
                this.onScanError.bind(this)
            ).then(() => {
                console.log('GPS Attendance: Scanner started successfully');
                scannerActive = true;
                $button.hide();
                $('#gps-stop-scanner').show();
                $reader.addClass('scanning');
                this.showMessage('success', gpsAttendance.i18n.scanner_started);
            }).catch(err => {
                console.error('Scanner start error:', err);

                // Try to get more specific error message
                let errorMsg = gpsAttendance.i18n.camera_error;
                if (err.toString().includes('NotAllowedError') || err.toString().includes('Permission denied')) {
                    errorMsg = 'Camera permission denied. Please allow camera access in your browser settings.';
                } else if (err.toString().includes('NotFoundError')) {
                    errorMsg = 'No camera found. Please connect a camera or try a different device.';
                } else if (err.toString().includes('NotReadableError')) {
                    errorMsg = 'Camera is already in use by another application.';
                } else {
                    errorMsg += ' Error: ' + err;
                }

                this.showMessage('error', errorMsg);
                $button.prop('disabled', false).text(gpsAttendance.i18n.start_scanner);
                $reader.hide();
            });
        },

        stopScanner: function(e) {
            if (e) e.preventDefault();

            if (!scannerActive || !html5QrCode) {
                return;
            }

            const $startBtn = $('#gps-start-scanner');
            const $stopBtn = $('#gps-stop-scanner');
            const $reader = $('#gps-qr-reader');

            html5QrCode.stop().then(() => {
                scannerActive = false;
                $stopBtn.hide();
                $startBtn.show().prop('disabled', false).text(gpsAttendance.i18n.start_scanner);
                $reader.removeClass('scanning');
                this.showMessage('info', gpsAttendance.i18n.scanner_stopped);
            }).catch(err => {
                console.error('Scanner stop error:', err);
            });
        },

        onScanSuccess: function(decodedText, decodedResult) {
            // Temporarily stop scanner while processing
            if (html5QrCode) {
                html5QrCode.pause();
            }

            this.processScan(decodedText);
        },

        onScanError: function(errorMessage) {
            // Ignore scan errors (they happen frequently during scanning)
            // Only log critical errors
            if (errorMessage.includes('NotFoundException')) {
                return;
            }
            console.warn('Scan error:', errorMessage);
        },

        processScan: function(qrData) {
            const $scanResult = $('#gps-scan-result');

            // Check if event is selected
            if (!this.selectedEventId) {
                this.showMessage('error', 'Please select an event first', $scanResult);
                // Resume scanner
                setTimeout(() => {
                    if (scannerActive && html5QrCode) {
                        html5QrCode.resume();
                    }
                }, 2000);
                return;
            }

            console.log('GPS Attendance: Processing scan with event ID:', this.selectedEventId);
            console.log('GPS Attendance: QR data:', qrData);

            // Show processing state
            $scanResult.html('<div class="gps-processing"><span class="spinner is-active"></span> ' + gpsAttendance.i18n.processing + '</div>');

            $.ajax({
                url: gpsAttendance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_scan_ticket',
                    nonce: gpsAttendance.nonce,
                    qr_data: qrData,
                    event_id: this.selectedEventId
                },
                success: (response) => {
                    if (response.success) {
                        this.showScanResult(response.data);
                        this.loadRecentCheckins();
                        this.loadStats();

                        // Play success sound/vibration
                        this.successFeedback();

                        // Resume scanner after 2 seconds
                        setTimeout(() => {
                            if (scannerActive && html5QrCode) {
                                html5QrCode.resume();
                            }
                        }, 2000);
                    } else {
                        this.showMessage('error', response.data.message);

                        // Resume scanner after 3 seconds on error
                        setTimeout(() => {
                            if (scannerActive && html5QrCode) {
                                html5QrCode.resume();
                            }
                        }, 3000);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Scan AJAX error:', error);
                    this.showMessage('error', gpsAttendance.i18n.ajax_error);

                    setTimeout(() => {
                        if (scannerActive && html5QrCode) {
                            html5QrCode.resume();
                        }
                    }, 3000);
                }
            });
        },

        showScanResult: function(data) {
            const $result = $('#gps-scan-result');

            // Get attendee name and event title
            const attendeeName = data.ticket && data.ticket.attendee_name ? data.ticket.attendee_name : (data.attendee_name || 'Unknown');
            const eventTitle = data.event && data.event.post_title ? data.event.post_title : (data.event_title || 'Event');
            const ticketCode = data.ticket && data.ticket.ticket_code ? data.ticket.ticket_code : (data.ticket_code || 'N/A');
            const checkedInAt = data.checked_in_at || new Date().toLocaleString();

            let html = '<div class="gps-scan-success notice notice-success" style="padding: 20px; margin: 20px 0;">';
            html += '<h3 style="margin-top: 0;"><span class="dashicons dashicons-yes-alt"></span> ' + gpsAttendance.i18n.check_in_success + '</h3>';
            html += '<div class="gps-scan-details" style="font-size: 15px;">';
            html += '<p style="margin: 10px 0;"><strong>' + gpsAttendance.i18n.attendee + ':</strong> ' + attendeeName + '</p>';
            html += '<p style="margin: 10px 0;"><strong>' + gpsAttendance.i18n.event + ':</strong> ' + eventTitle + '</p>';
            html += '<p style="margin: 10px 0;"><strong>' + gpsAttendance.i18n.ticket + ':</strong> <code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">' + ticketCode + '</code></p>';
            html += '<p style="margin: 10px 0;"><strong>' + gpsAttendance.i18n.time + ':</strong> ' + checkedInAt + '</p>';

            if (data.credits_awarded && data.credits_awarded > 0) {
                html += '<p class="gps-credits-awarded" style="margin: 15px 0; padding: 10px; background: #d4edda; border-radius: 4px;"><span class="dashicons dashicons-awards"></span> <strong style="color: #155724;">' + data.credits_awarded + ' ' + gpsAttendance.i18n.credits_awarded + '</strong></p>';
            }

            html += '</div></div>';

            $result.html(html);
        },

        handleManualCheckin: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $ticketCode = $('#gps-manual-ticket-code');
            const $notes = $('#gps-manual-notes');
            const $button = $form.find('button[type="submit"]');
            const $result = $('#gps-manual-result');

            const ticketCode = $ticketCode.val().trim();

            if (!ticketCode) {
                this.showMessage('error', gpsAttendance.i18n.enter_ticket_code, $result);
                return;
            }

            // Show processing
            $button.prop('disabled', true);
            $result.html('<div class="gps-processing"><span class="spinner is-active"></span> ' + gpsAttendance.i18n.processing + '</div>');

            $.ajax({
                url: gpsAttendance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_manual_checkin',
                    nonce: gpsAttendance.nonce,
                    ticket_code: ticketCode,
                    notes: $notes.val()
                },
                success: (response) => {
                    $button.prop('disabled', false);

                    if (response.success) {
                        this.showManualResult(response.data, $result);
                        $ticketCode.val('');
                        $notes.val('');
                        this.loadRecentCheckins();
                        this.loadStats();
                        this.successFeedback();
                    } else {
                        this.showMessage('error', response.data.message, $result);
                    }
                },
                error: (xhr, status, error) => {
                    $button.prop('disabled', false);
                    console.error('Manual check-in error:', error);
                    this.showMessage('error', gpsAttendance.i18n.ajax_error, $result);
                }
            });
        },

        showManualResult: function(data, $container) {
            let html = '<div class="gps-scan-success notice notice-success">';
            html += '<h3><span class="dashicons dashicons-yes-alt"></span> ' + gpsAttendance.i18n.check_in_success + '</h3>';
            html += '<div class="gps-scan-details">';
            html += '<p><strong>' + gpsAttendance.i18n.attendee + ':</strong> ' + data.attendee_name + '</p>';
            html += '<p><strong>' + gpsAttendance.i18n.event + ':</strong> ' + data.event_title + '</p>';
            html += '<p><strong>' + gpsAttendance.i18n.ticket + ':</strong> ' + data.ticket_code + '</p>';

            if (data.credits_awarded > 0) {
                html += '<p class="gps-credits-awarded"><span class="dashicons dashicons-awards"></span> <strong>' + data.credits_awarded + ' ' + gpsAttendance.i18n.credits_awarded + '</strong></p>';
            }

            html += '</div></div>';

            $container.html(html);
        },

        handleSearchInput: function(e) {
            const query = $(e.currentTarget).val().trim();

            // Only auto-search if query is 3+ characters
            if (query.length >= 3) {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.performSearch(query);
                }, 500);
            }
        },

        handleSearch: function(e) {
            e.preventDefault();

            const query = $('#gps-search-query').val().trim();

            if (query.length < 3) {
                this.showMessage('error', gpsAttendance.i18n.search_min_chars);
                return;
            }

            this.performSearch(query);
        },

        performSearch: function(query) {
            console.log('GPS Attendance: Searching for:', query);
            console.log('GPS Attendance: Selected event ID:', this.selectedEventId);

            const $results = $('#search-results');

            if (!this.selectedEventId) {
                alert('Please select an event first');
                return;
            }

            $results.html('<div class="gps-processing"><span class="spinner is-active"></span> ' + gpsAttendance.i18n.searching + '</div>');

            $.ajax({
                url: gpsAttendance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_search_attendees',
                    nonce: gpsAttendance.nonce,
                    query: query,
                    event_id: this.selectedEventId
                },
                success: (response) => {
                    console.log('GPS Attendance: Search response:', response);

                    if (response.success) {
                        this.showSearchResults(response.data);
                    } else {
                        const message = response.data && response.data.message ? response.data.message : 'Search failed';
                        this.showMessage('error', message, $results);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Search AJAX error:', error);
                    this.showMessage('error', gpsAttendance.i18n.ajax_error, $results);
                }
            });
        },

        showSearchResults: function(results) {
            const $results = $('#search-results');

            if (results.length === 0) {
                $results.html('<div class="notice notice-info"><p>' + gpsAttendance.i18n.no_results + '</p></div>');
                return;
            }

            let html = '<div class="gps-search-results-list">';
            html += '<h3>' + results.length + ' ' + gpsAttendance.i18n.attendees_found + '</h3>';
            html += '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th>' + gpsAttendance.i18n.attendee + '</th>';
            html += '<th>' + gpsAttendance.i18n.event + '</th>';
            html += '<th>' + gpsAttendance.i18n.ticket + '</th>';
            html += '<th>' + gpsAttendance.i18n.status + '</th>';
            html += '<th>' + gpsAttendance.i18n.action + '</th>';
            html += '</tr></thead><tbody>';

            results.forEach(result => {
                html += '<tr>';
                html += '<td>' + result.attendee_name + '<br><small>' + result.attendee_email + '</small></td>';
                html += '<td>' + result.event_title + '</td>';
                html += '<td><code>' + result.ticket_code + '</code></td>';

                if (result.checked_in) {
                    html += '<td><span class="gps-status-badge checked-in">' + gpsAttendance.i18n.checked_in + '</span><br><small>' + result.checked_in_at + '</small></td>';
                    html += '<td>—</td>';
                } else {
                    html += '<td><span class="gps-status-badge pending">' + gpsAttendance.i18n.not_checked_in + '</span></td>';
                    html += '<td><button class="button button-primary gps-checkin-attendee" data-ticket-id="' + result.ticket_id + '">' + gpsAttendance.i18n.check_in + '</button></td>';
                }

                html += '</tr>';
            });

            html += '</tbody></table></div>';

            $results.html(html);
        },

        checkInFromSearch: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const ticketId = $button.data('ticket-id');

            $button.prop('disabled', true).text(gpsAttendance.i18n.processing);

            $.ajax({
                url: gpsAttendance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_manual_checkin',
                    nonce: gpsAttendance.nonce,
                    ticket_id: ticketId,
                    notes: 'Checked in from search'
                },
                success: (response) => {
                    if (response.success) {
                        $button.closest('tr').find('.gps-status-badge')
                            .removeClass('pending')
                            .addClass('checked-in')
                            .text(gpsAttendance.i18n.checked_in);
                        $button.closest('td').html('—');

                        this.loadRecentCheckins();
                        this.loadStats();
                        this.successFeedback();
                        this.showMessage('success', gpsAttendance.i18n.check_in_success);
                    } else {
                        $button.prop('disabled', false).text(gpsAttendance.i18n.check_in);
                        this.showMessage('error', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Check-in error:', error);
                    $button.prop('disabled', false).text(gpsAttendance.i18n.check_in);
                    this.showMessage('error', gpsAttendance.i18n.ajax_error);
                }
            });
        },

        loadRecentCheckins: function() {
            const $container = $('#gps-recent-checkins');

            if (!$container.length) return;

            $.ajax({
                url: gpsAttendance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_get_recent_checkins',
                    nonce: gpsAttendance.nonce
                },
                success: (response) => {
                    if (response.success && response.data.length > 0) {
                        let html = '<table class="wp-list-table widefat fixed striped">';
                        html += '<thead><tr>';
                        html += '<th>' + gpsAttendance.i18n.time + '</th>';
                        html += '<th>' + gpsAttendance.i18n.attendee + '</th>';
                        html += '<th>' + gpsAttendance.i18n.event + '</th>';
                        html += '<th>' + gpsAttendance.i18n.method + '</th>';
                        html += '</tr></thead><tbody>';

                        response.data.forEach(checkin => {
                            html += '<tr>';
                            html += '<td>' + checkin.time_ago + '</td>';
                            html += '<td>' + checkin.attendee_name + '</td>';
                            html += '<td>' + checkin.event_title + '</td>';
                            html += '<td><span class="gps-method-badge ' + checkin.method + '">' + checkin.method_label + '</span></td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table>';
                        $container.html(html);
                    }
                }
            });
        },

        loadStats: function() {
            $.ajax({
                url: gpsAttendance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_get_attendance_stats',
                    nonce: gpsAttendance.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#gps-stat-today').text(response.data.today);
                        $('#gps-stat-total').text(response.data.total);
                        $('#gps-stat-credits').text(response.data.credits_awarded);
                    }
                }
            });
        },

        showMessage: function(type, message, $container) {
            const typeClass = type === 'error' ? 'notice-error' : (type === 'success' ? 'notice-success' : 'notice-info');
            const html = '<div class="notice ' + typeClass + '"><p>' + message + '</p></div>';

            if ($container) {
                $container.html(html);
            } else {
                $('.gps-scanner-messages').html(html);
            }

            // Auto-hide after 5 seconds
            setTimeout(() => {
                if ($container) {
                    $container.find('.notice').fadeOut();
                } else {
                    $('.gps-scanner-messages .notice').fadeOut();
                }
            }, 5000);
        },

        successFeedback: function() {
            // Visual feedback
            $('body').addClass('gps-scan-success-flash');
            setTimeout(() => {
                $('body').removeClass('gps-scan-success-flash');
            }, 300);

            // Vibration feedback (if supported)
            if ('vibrate' in navigator) {
                navigator.vibrate(200);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        console.log('GPS Attendance: DOM Ready');
        console.log('GPS Attendance Scanner element found:', $('.gps-attendance-scanner').length);

        if ($('.gps-attendance-scanner').length) {
            console.log('GPS Attendance: Initializing...');
            GPSAttendance.init();
            console.log('GPS Attendance: Initialized');
        } else {
            console.log('GPS Attendance: Scanner element not found');
        }
    });

})(jQuery);
