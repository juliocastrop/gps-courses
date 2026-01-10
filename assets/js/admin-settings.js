/**
 * GPS Courses - Admin Settings
 */

(function($) {
    'use strict';

    const GPSSettings = {
        init: function() {
            this.initColorPicker();
            this.initMediaUploader();
        },

        initColorPicker: function() {
            $('.gps-color-picker').wpColorPicker();
        },

        initMediaUploader: function() {
            $('.gps-upload-button').on('click', function(e) {
                e.preventDefault();

                const button = $(this);
                const targetInput = $('#' + button.data('target'));

                const mediaUploader = wp.media({
                    title: gpsSettings.media_title,
                    button: {
                        text: gpsSettings.media_button
                    },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    targetInput.val(attachment.url);

                    // Update preview if exists
                    const preview = targetInput.closest('td').find('.gps-image-preview');
                    if (preview.length) {
                        preview.html('<img src="' + attachment.url + '" style="max-width: 300px; margin-top: 10px;">');
                    } else {
                        targetInput.after('<div class="gps-image-preview"><img src="' + attachment.url + '" style="max-width: 300px; margin-top: 10px;"></div>');
                    }
                });

                mediaUploader.open();
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.gps-settings-page').length) {
            GPSSettings.init();
        }
    });

})(jQuery);
