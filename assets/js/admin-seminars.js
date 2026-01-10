/**
 * GPS Courses - Monthly Seminars Admin JavaScript
 *
 * Handles interactive functionality for seminar management dashboard
 */

(function($) {
    'use strict';

    /**
     * Main Seminar Management Object
     */
    const GPS_Seminars = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initDatepickers();
            this.initSessionManagement();
            this.initQRScanner();
            this.initRegistrationManagement();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Modal triggers
            $(document).on('click', '[data-modal]', this.openModal);
            $(document).on('click', '.gps-modal-close, .gps-modal-overlay', this.closeModal);
            $(document).on('click', '.gps-modal', function(e) {
                e.stopPropagation();
            });

            // Form submissions
            $(document).on('submit', '#create-session-form', this.handleCreateSession);
            $(document).on('submit', '#edit-session-form', this.handleUpdateSession);
            $(document).on('click', '.delete-session-btn', this.handleDeleteSession);

            // Stats refresh
            $(document).on('click', '.refresh-stats-btn', this.refreshStats);

            // Export buttons
            $(document).on('click', '.export-registrants-btn', this.exportRegistrants);
            $(document).on('click', '.export-attendance-btn', this.exportAttendance);
        },

        /**
         * Initialize datepickers
         */
        initDatepickers: function() {
            if ($.fn.datepicker) {
                $('.gps-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0,
                    changeMonth: true,
                    changeYear: true
                });
            }
        },

        /**
         * Initialize session management
         */
        initSessionManagement: function() {
            const self = this;

            // Edit session button
            $(document).on('click', '.edit-session-btn', function() {
                const sessionId = $(this).data('session-id');
                self.loadSessionData(sessionId);
            });

            // Bulk actions
            $(document).on('change', '#bulk-action-selector', function() {
                const action = $(this).val();
                const $selectedSessions = $('.session-checkbox:checked');

                if (action && $selectedSessions.length > 0) {
                    self.handleBulkAction(action, $selectedSessions);
                }
            });
        },

        /**
         * Initialize QR code scanner
         */
        initQRScanner: function() {
            const self = this;
            const $qrInput = $('#qr-code-input');
            const $checkInBtn = $('#check-in-btn');
            const $result = $('#check-in-result');

            // Scanner tabs
            $('.scanner-tab').on('click', function() {
                const tab = $(this).data('tab');
                $('.scanner-tab').removeClass('active');
                $(this).addClass('active');
                $('.scanner-tab-content').removeClass('active');
                $('#' + tab + '-tab').addClass('active');

                // Stop camera when switching away from camera tab
                if (tab !== 'camera' && self.html5QrCode) {
                    self.stopCameraScanner();
                }

                // Focus input when switching to manual tab
                if (tab === 'manual') {
                    setTimeout(() => $qrInput.focus(), 100);
                }
            });

            // Camera scanner buttons
            $('#start-camera-btn').on('click', function() {
                self.startCameraScanner();
            });

            $('#stop-camera-btn').on('click', function() {
                self.stopCameraScanner();
            });

            // Handle enter key in QR input
            $qrInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $checkInBtn.trigger('click');
                }
            });

            // Auto-focus QR input
            if ($qrInput.length) {
                $qrInput.focus();
            }

            // Check-in button click
            $checkInBtn.on('click', function() {
                const qrCode = $qrInput.val().trim();
                const sessionId = $('#current-session-id').val();

                if (!qrCode) {
                    self.showResult('error', 'Please enter or scan a QR code');
                    return;
                }

                if (!sessionId) {
                    self.showResult('error', 'No session selected');
                    return;
                }

                self.processQRCheckIn(qrCode, sessionId);
            });

            // Manual check-in buttons
            $(document).on('click', '.manual-checkin-btn', function() {
                const registrationId = $(this).data('registration-id');
                const sessionId = $(this).data('session-id');
                const name = $(this).data('name');
                const isMakeup = $(this).data('is-makeup') || false;

                if (confirm('Check in ' + name + '?')) {
                    self.processManualCheckIn(registrationId, sessionId, isMakeup);
                }
            });
        },

        /**
         * Camera scanner instance
         */
        html5QrCode: null,

        /**
         * Start camera scanner
         */
        startCameraScanner: function() {
            const self = this;
            const $startBtn = $('#start-camera-btn');
            const $stopBtn = $('#stop-camera-btn');
            const $reader = $('#camera-reader');
            const $status = $('#camera-status');

            // Clear any previous status
            $status.attr('class', 'scanner-status').empty();

            // Show loading state on button
            $startBtn.prop('disabled', true).text(gpsSeminars.i18n.starting);
            $reader.show(); // Show the reader div BEFORE initializing scanner

            // Initialize scanner if not already done
            if (!self.html5QrCode && typeof Html5Qrcode !== 'undefined') {
                self.html5QrCode = new Html5Qrcode("camera-reader");
            }

            if (!self.html5QrCode) {
                $status.attr('class', 'scanner-status error')
                    .html('<strong>' + gpsSeminars.i18n.camera_error + ':</strong> Library not loaded')
                    .show();
                $startBtn.prop('disabled', false).text(gpsSeminars.i18n.start_camera);
                $reader.hide();
                return;
            }

            // Configure scanner
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            };

            // Start scanning
            self.html5QrCode.start(
                { facingMode: "environment" },
                config,
                (decodedText, decodedResult) => {
                    // QR code detected
                    const sessionId = $('#current-session-id').val();
                    if (sessionId) {
                        self.processQRCheckIn(decodedText, sessionId);
                        self.stopCameraScanner();
                    }
                },
                (errorMessage) => {
                    // Scanning error (can be ignored for most cases)
                }
            ).then(() => {
                $startBtn.hide();
                $stopBtn.show();
                $status.attr('class', 'scanner-status info')
                    .html('📷 <strong>' + gpsSeminars.i18n.scanner_started + '</strong>')
                    .show();
            }).catch((err) => {
                $status.attr('class', 'scanner-status error')
                    .html('<strong>' + gpsSeminars.i18n.camera_error + ':</strong> ' + err +
                          '<br><small>Please allow camera access in your browser settings.</small>')
                    .show();
                $startBtn.prop('disabled', false).text(gpsSeminars.i18n.start_camera);
                $reader.hide();
                console.error('Camera start error:', err);
            });
        },

        /**
         * Stop camera scanner
         */
        stopCameraScanner: function() {
            const self = this;
            const $startBtn = $('#start-camera-btn');
            const $stopBtn = $('#stop-camera-btn');
            const $reader = $('#camera-reader');
            const $status = $('#camera-status');

            if (self.html5QrCode && self.html5QrCode.isScanning) {
                self.html5QrCode.stop().then(() => {
                    $startBtn.show().prop('disabled', false);
                    $stopBtn.hide();
                    $reader.hide(); // Use simple hide() method to match working pattern
                    $status.attr('class', 'scanner-status info')
                        .html('⏸️ <strong>' + gpsSeminars.i18n.scanner_stopped + '</strong>')
                        .show()
                        .delay(3000)
                        .fadeOut();
                }).catch((err) => {
                    console.error('Error stopping scanner:', err);
                    $status.attr('class', 'scanner-status error')
                        .html('<strong>Error:</strong> Failed to stop scanner')
                        .show();
                });
            }
        },

        /**
         * Process QR code check-in
         */
        processQRCheckIn: function(qrCode, sessionId) {
            const self = this;
            const $btn = $('#check-in-btn');
            const $input = $('#qr-code-input');

            $btn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: {
                    action: 'gps_scan_seminar_qr',
                    nonce: gpsSeminars.nonce,
                    qr_code: qrCode,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success) {
                        self.showResult('success', response.data.message + ' - ' + response.data.data.user_name);
                        $input.val('');

                        // Play success sound (if available)
                        self.playSound('success');

                        // Refresh page after 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        self.showResult('error', response.data.message || 'Check-in failed');
                        self.playSound('error');
                    }
                },
                error: function() {
                    self.showResult('error', 'Connection error. Please try again.');
                    self.playSound('error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Check In');
                    $input.focus();
                }
            });
        },

        /**
         * Process manual check-in
         */
        processManualCheckIn: function(registrationId, sessionId, isMakeup) {
            const self = this;

            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: {
                    action: 'gps_manual_seminar_checkin',
                    nonce: gpsSeminars.nonce,
                    registration_id: registrationId,
                    session_id: sessionId,
                    is_makeup: isMakeup
                },
                success: function(response) {
                    if (response.success) {
                        alert('Check-in successful!');
                        location.reload();
                    } else {
                        alert(response.data.message || 'Check-in failed');
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                }
            });
        },

        /**
         * Show check-in result message
         */
        showResult: function(type, message) {
            const $result = $('#check-in-result');
            const icon = type === 'success' ? '✅' : '❌';
            const formattedMessage = '<span style="font-size: 18px;">' + icon + '</span> ' + message;

            $result.removeClass('success error')
                   .addClass(type)
                   .html(formattedMessage)
                   .show();

            setTimeout(function() {
                $result.fadeOut();
            }, 5000);
        },

        /**
         * Play sound feedback
         */
        playSound: function(type) {
            // Simple beep feedback using Web Audio API
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                if (type === 'success') {
                    oscillator.frequency.value = 800;
                    gainNode.gain.value = 0.3;
                    oscillator.start();
                    oscillator.stop(audioContext.currentTime + 0.1);
                } else if (type === 'error') {
                    oscillator.frequency.value = 300;
                    gainNode.gain.value = 0.3;
                    oscillator.start();
                    oscillator.stop(audioContext.currentTime + 0.2);
                }
            } catch(e) {
                // Silent fail if audio not supported
            }
        },

        /**
         * Initialize registration management
         */
        initRegistrationManagement: function() {
            const self = this;

            // View registration details
            $(document).on('click', '.view-registration-btn', function() {
                const registrationId = $(this).data('registration-id');
                self.loadRegistrationDetails(registrationId);
            });

            // Cancel registration
            $(document).on('click', '.cancel-registration-btn', function() {
                const registrationId = $(this).data('registration-id');
                const name = $(this).data('name');

                if (confirm('Are you sure you want to cancel the registration for ' + name + '?')) {
                    self.cancelRegistration(registrationId);
                }
            });

            // Resend confirmation email
            $(document).on('click', '.resend-confirmation-btn', function() {
                const registrationId = $(this).data('registration-id');
                self.resendConfirmation(registrationId);
            });
        },

        /**
         * Load registration details
         */
        loadRegistrationDetails: function(registrationId) {
            const self = this;

            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: {
                    action: 'gps_get_user_progress',
                    nonce: gpsSeminars.nonce,
                    registration_id: registrationId
                },
                success: function(response) {
                    if (response.success) {
                        self.displayRegistrationDetails(response.data);
                    } else {
                        alert('Failed to load registration details');
                    }
                },
                error: function() {
                    alert('Connection error');
                }
            });
        },

        /**
         * Display registration details in modal
         */
        displayRegistrationDetails: function(data) {
            let html = '<div class="registration-details">';
            html += '<h3>' + data.registration.display_name + '</h3>';
            html += '<p><strong>QR Code:</strong> <code>' + data.registration.qr_code + '</code></p>';
            html += '<p><strong>Sessions Completed:</strong> ' + data.registration.sessions_completed + ' / 10</p>';
            html += '<p><strong>Total CE Credits:</strong> ' + data.total_credits + '</p>';
            html += '<p><strong>Progress:</strong> ' + data.completion_percentage + '%</p>';

            if (data.attendance && data.attendance.length > 0) {
                html += '<h4>Attendance History:</h4>';
                html += '<ul>';
                data.attendance.forEach(function(att) {
                    html += '<li>Session ' + att.session_number + ' - ' + att.session_date + ' (' + att.credits_awarded + ' CE)</li>';
                });
                html += '</ul>';
            }

            html += '</div>';

            $('#registration-details-content').html(html);
            this.openModal('#registration-details-modal');
        },

        /**
         * Cancel registration
         */
        cancelRegistration: function(registrationId) {
            const reason = prompt('Reason for cancellation (optional):');

            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: {
                    action: 'gps_cancel_registration',
                    nonce: gpsSeminars.nonce,
                    registration_id: registrationId,
                    reason: reason || ''
                },
                success: function(response) {
                    if (response.success) {
                        alert('Registration cancelled successfully');
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to cancel registration');
                    }
                },
                error: function() {
                    alert('Connection error');
                }
            });
        },

        /**
         * Resend confirmation email
         */
        resendConfirmation: function(registrationId) {
            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: {
                    action: 'gps_resend_confirmation',
                    nonce: gpsSeminars.nonce,
                    registration_id: registrationId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Confirmation email sent successfully');
                    } else {
                        alert(response.data.message || 'Failed to send email');
                    }
                },
                error: function() {
                    alert('Connection error');
                }
            });
        },

        /**
         * Create new session
         */
        handleCreateSession: function(e) {
            e.preventDefault();
            const $form = $(this);
            const formData = $form.serialize();

            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: formData + '&action=gps_create_seminar_session&nonce=' + gpsSeminars.nonce,
                success: function(response) {
                    if (response.success) {
                        alert('Session created successfully');
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to create session');
                    }
                },
                error: function() {
                    alert('Connection error');
                }
            });
        },

        /**
         * Update session
         */
        handleUpdateSession: function(e) {
            e.preventDefault();
            const $form = $(this);
            const formData = $form.serialize();

            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: formData + '&action=gps_update_seminar_session&nonce=' + gpsSeminars.nonce,
                success: function(response) {
                    if (response.success) {
                        alert('Session updated successfully');
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to update session');
                    }
                },
                error: function() {
                    alert('Connection error');
                }
            });
        },

        /**
         * Delete session
         */
        handleDeleteSession: function(e) {
            e.preventDefault();
            const sessionId = $(this).data('session-id');
            const sessionNumber = $(this).data('session-number');

            if (!confirm('Are you sure you want to delete Session #' + sessionNumber + '?')) {
                return;
            }

            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: {
                    action: 'gps_delete_seminar_session',
                    nonce: gpsSeminars.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Session deleted successfully');
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to delete session');
                    }
                },
                error: function() {
                    alert('Connection error');
                }
            });
        },

        /**
         * Load session data for editing
         */
        loadSessionData: function(sessionId) {
            // This would load session data via AJAX and populate edit form
            // Implementation depends on specific form structure
            console.log('Loading session data for ID:', sessionId);
        },

        /**
         * Handle bulk actions
         */
        handleBulkAction: function(action, $selectedSessions) {
            const sessionIds = [];
            $selectedSessions.each(function() {
                sessionIds.push($(this).val());
            });

            console.log('Bulk action:', action, 'for sessions:', sessionIds);
            // Implement bulk action logic here
        },

        /**
         * Refresh statistics
         */
        refreshStats: function(e) {
            e.preventDefault();
            const seminarId = $(this).data('seminar-id');

            $.ajax({
                url: gpsSeminars.ajax_url,
                method: 'POST',
                data: {
                    action: 'gps_get_seminar_stats',
                    nonce: gpsSeminars.nonce,
                    seminar_id: seminarId
                },
                success: function(response) {
                    if (response.success) {
                        // Update stats display
                        $('.stat-enrolled').text(response.data.enrolled);
                        $('.stat-capacity').text(response.data.capacity);
                        $('.stat-available').text(response.data.available);
                        $('.stat-sessions').text(response.data.sessions_count);
                    }
                },
                error: function() {
                    alert('Failed to refresh statistics');
                }
            });
        },

        /**
         * Export registrants
         */
        exportRegistrants: function(e) {
            e.preventDefault();
            const seminarId = $(this).data('seminar-id');

            window.location.href = gpsSeminars.ajax_url + '?action=gps_export_registrants&nonce=' +
                                   gpsSeminars.nonce + '&seminar_id=' + seminarId;
        },

        /**
         * Export attendance
         */
        exportAttendance: function(e) {
            e.preventDefault();
            const sessionId = $(this).data('session-id');

            window.location.href = gpsSeminars.ajax_url + '?action=gps_export_attendance&nonce=' +
                                   gpsSeminars.nonce + '&session_id=' + sessionId;
        },

        /**
         * Open modal
         */
        openModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            const modalId = $(this).data('modal');
            $(modalId).addClass('active');
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            $('.gps-modal-overlay').removeClass('active');
        }
    };

    /**
     * Reports and Analytics
     */
    const GPS_Reports = {

        /**
         * Initialize
         */
        init: function() {
            this.initCharts();
            this.initFilters();
        },

        /**
         * Initialize charts
         */
        initCharts: function() {
            // Initialize Chart.js charts if available
            if (typeof Chart !== 'undefined') {
                this.renderAttendanceChart();
                this.renderCompletionChart();
                this.renderRevenueChart();
            }
        },

        /**
         * Render attendance chart
         */
        renderAttendanceChart: function() {
            const ctx = document.getElementById('attendance-chart');
            if (!ctx) return;

            // Example chart - would be populated with actual data
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Session 1', 'Session 2', 'Session 3', 'Session 4', 'Session 5'],
                    datasets: [{
                        label: 'Attendance Rate',
                        data: [95, 92, 88, 90, 87],
                        borderColor: 'rgb(34, 113, 177)',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        },

        /**
         * Render completion chart
         */
        renderCompletionChart: function() {
            const ctx = document.getElementById('completion-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'In Progress', 'Cancelled'],
                    datasets: [{
                        data: [45, 32, 8],
                        backgroundColor: [
                            'rgb(70, 180, 80)',
                            'rgb(0, 160, 210)',
                            'rgb(220, 50, 50)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        },

        /**
         * Render revenue chart
         */
        renderRevenueChart: function() {
            const ctx = document.getElementById('revenue-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenue',
                        data: [15000, 22500, 18750, 26250, 20000, 23750],
                        backgroundColor: 'rgb(34, 113, 177)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Initialize report filters
         */
        initFilters: function() {
            $('#report-date-range').on('change', function() {
                // Reload report data with new date range
                console.log('Date range changed:', $(this).val());
            });

            $('#report-seminar-filter').on('change', function() {
                // Reload report data for specific seminar
                console.log('Seminar filter changed:', $(this).val());
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        GPS_Seminars.init();
        GPS_Reports.init();
    });

})(jQuery);
