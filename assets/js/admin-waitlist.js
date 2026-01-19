/**
 * GPS Courses - Waitlist Admin JavaScript
 */
(function($) {
    'use strict';

    const GPSWaitlistAdmin = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            // Select all checkbox
            $('#gps-waitlist-select-all').on('change', this.selectAll.bind(this));

            // Individual checkboxes
            $(document).on('change', '.gps-waitlist-checkbox', this.updateSelectAll.bind(this));

            // Remove entry
            $(document).on('click', '.gps-waitlist-remove', this.removeEntry.bind(this));

            // Notify entry
            $(document).on('click', '.gps-waitlist-notify', this.notifyEntry.bind(this));

            // Mark as converted
            $(document).on('click', '.gps-waitlist-converted', this.markConverted.bind(this));

            // Bulk actions
            $('#gps-waitlist-bulk-apply').on('click', this.bulkAction.bind(this));
        },

        selectAll(e) {
            const isChecked = $(e.currentTarget).is(':checked');
            $('.gps-waitlist-checkbox').prop('checked', isChecked);
        },

        updateSelectAll() {
            const total = $('.gps-waitlist-checkbox').length;
            const checked = $('.gps-waitlist-checkbox:checked').length;
            $('#gps-waitlist-select-all').prop('checked', total === checked && total > 0);
        },

        removeEntry(e) {
            e.preventDefault();

            if (!confirm(gpsWaitlistAdmin.i18n.confirmRemove)) {
                return;
            }

            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const waitlistId = $btn.data('id');

            $btn.prop('disabled', true).text(gpsWaitlistAdmin.i18n.removing);
            $row.addClass('gps-loading');

            $.ajax({
                url: gpsWaitlistAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_admin_remove_waitlist',
                    waitlist_id: waitlistId,
                    nonce: gpsWaitlistAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            GPSWaitlistAdmin.updateStats();
                        });
                        GPSWaitlistAdmin.showNotice('success', response.data.message);
                    } else {
                        GPSWaitlistAdmin.showNotice('error', response.data.message);
                        $btn.prop('disabled', false).text('Remove');
                        $row.removeClass('gps-loading');
                    }
                },
                error: () => {
                    GPSWaitlistAdmin.showNotice('error', 'An error occurred. Please try again.');
                    $btn.prop('disabled', false).text('Remove');
                    $row.removeClass('gps-loading');
                }
            });
        },

        notifyEntry(e) {
            e.preventDefault();

            if (!confirm(gpsWaitlistAdmin.i18n.confirmNotify)) {
                return;
            }

            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const waitlistId = $btn.data('id');

            $btn.prop('disabled', true).text(gpsWaitlistAdmin.i18n.sending);
            $row.addClass('gps-loading');

            $.ajax({
                url: gpsWaitlistAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_admin_notify_waitlist',
                    waitlist_id: waitlistId,
                    nonce: gpsWaitlistAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Reload page to show updated status
                        location.reload();
                    } else {
                        GPSWaitlistAdmin.showNotice('error', response.data.message);
                        $btn.prop('disabled', false).text('Notify');
                        $row.removeClass('gps-loading');
                    }
                },
                error: () => {
                    GPSWaitlistAdmin.showNotice('error', 'An error occurred. Please try again.');
                    $btn.prop('disabled', false).text('Notify');
                    $row.removeClass('gps-loading');
                }
            });
        },

        markConverted(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const waitlistId = $btn.data('id');

            $btn.prop('disabled', true);
            $row.addClass('gps-loading');

            $.ajax({
                url: gpsWaitlistAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_admin_mark_converted',
                    waitlist_id: waitlistId,
                    nonce: gpsWaitlistAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Reload page to show updated status
                        location.reload();
                    } else {
                        GPSWaitlistAdmin.showNotice('error', response.data.message);
                        $btn.prop('disabled', false);
                        $row.removeClass('gps-loading');
                    }
                },
                error: () => {
                    GPSWaitlistAdmin.showNotice('error', 'An error occurred. Please try again.');
                    $btn.prop('disabled', false);
                    $row.removeClass('gps-loading');
                }
            });
        },

        bulkAction(e) {
            e.preventDefault();

            const action = $('#gps-waitlist-bulk-action').val();
            const ids = [];

            $('.gps-waitlist-checkbox:checked').each(function() {
                ids.push($(this).val());
            });

            if (!action) {
                alert('Please select a bulk action.');
                return;
            }

            if (ids.length === 0) {
                alert('Please select at least one entry.');
                return;
            }

            if (action === 'remove' && !confirm(gpsWaitlistAdmin.i18n.confirmBulkRemove)) {
                return;
            }

            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: gpsWaitlistAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gps_waitlist_bulk_action',
                    bulk_action: action,
                    ids: ids,
                    nonce: gpsWaitlistAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        GPSWaitlistAdmin.showNotice('error', response.data.message);
                        $btn.prop('disabled', false).text('Apply');
                    }
                },
                error: () => {
                    GPSWaitlistAdmin.showNotice('error', 'An error occurred. Please try again.');
                    $btn.prop('disabled', false).text('Apply');
                }
            });
        },

        showNotice(type, message) {
            // Remove existing notices
            $('.gps-waitlist-page .notice').remove();

            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            $('.gps-waitlist-page h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        updateStats() {
            // Simple stat update - just decrement waiting count
            const $waitingCard = $('.gps-stat-card:first-child .stat-value');
            const currentCount = parseInt($waitingCard.text(), 10);
            if (currentCount > 0) {
                $waitingCard.text(currentCount - 1);
            }
        }
    };

    $(document).ready(() => GPSWaitlistAdmin.init());
})(jQuery);
