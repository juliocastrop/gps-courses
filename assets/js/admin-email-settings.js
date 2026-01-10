console.log('=== GPS EMAIL SETTINGS JS FILE LOADED ===');

jQuery(document).ready(function($) {
    'use strict';

    console.log('=== GPS Email Settings - jQuery Ready ===');
    console.log('jQuery version:', $.fn.jquery);
    console.log('wp object:', typeof wp);
    console.log('wp.media available:', typeof wp !== 'undefined' && typeof wp.media !== 'undefined');

    // Initialize color pickers
    $('.gps-color-picker').wpColorPicker();
    console.log('Color pickers initialized:', $('.gps-color-picker').length);

    // Debug: Check if tab elements exist
    console.log('Tab navigation elements found:', $('.gps-nav-tab').length);
    console.log('Tab content sections found:', $('.gps-tab-content').length);

    // Tab Navigation
    $('.gps-nav-tab').on('click', function(e) {
        e.preventDefault();

        var tab = $(this).data('tab');
        console.log('Tab clicked:', tab);
        console.log('Target tab element exists:', $('#tab-' + tab).length > 0);

        // Update active tab
        $('.gps-nav-tab').removeClass('active');
        $(this).addClass('active');

        // Hide all tab content
        $('.gps-tab-content').removeClass('active');

        // Show target tab
        $('#tab-' + tab).addClass('active');

        // Update URL hash
        window.location.hash = tab;
    });

    // Handle hash on page load
    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        $('.gps-nav-tab[data-tab="' + hash + '"]').trigger('click');
    }

    // Logo upload
    var logoUploader;

    $(document).on('click', '.gps-upload-logo-button', function(e) {
        e.preventDefault();
        console.log('Logo upload button clicked');
        console.log('wp.media check:', typeof wp, typeof wp.media);

        // If the uploader object has already been created, reopen the dialog
        if (logoUploader) {
            logoUploader.open();
            return;
        }

        // Create the media uploader
        logoUploader = wp.media({
            title: 'Select Logo',
            button: {
                text: 'Use this logo'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        // When an image is selected, run a callback
        logoUploader.on('select', function() {
            var attachment = logoUploader.state().get('selection').first().toJSON();

            // Update hidden input
            $('#gps_email_logo').val(attachment.url);

            // Update preview
            var previewHtml = '<img src="' + attachment.url + '" style="max-width: 200px; height: auto; display: block; margin-bottom: 10px;">';
            $('.gps-logo-preview').html(previewHtml);

            // Show remove button if not already visible
            if ($('.gps-remove-logo-button').length === 0) {
                $('.gps-upload-logo-button').after(' <button type="button" class="button gps-remove-logo-button">Remove Logo</button>');
            }
            $('.gps-remove-logo-button').show();
        });

        // Open the uploader dialog
        logoUploader.open();
    });

    // Remove logo
    $(document).on('click', '.gps-remove-logo-button', function(e) {
        e.preventDefault();

        // Clear hidden input
        $('#gps_email_logo').val('');

        // Clear preview
        $('.gps-logo-preview').html('');

        // Hide the remove button
        $(this).hide();
    });

    // Send test email
    $(document).on('click', '#gps_send_test_email', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#gps_test_email_result');
        var email = $('#gps_test_email_address').val();
        var template = $('#gps_test_email_template').val() || 'ticket';

        if (!email) {
            $result.removeClass('success').addClass('error')
                .html('Please enter an email address.')
                .show();
            return;
        }

        // Check if gpsEmailSettings is defined
        var ajaxUrl = typeof gpsEmailSettings !== 'undefined' ? gpsEmailSettings.ajaxUrl : ajaxurl;
        var nonce = typeof gpsEmailSettings !== 'undefined' ? gpsEmailSettings.nonce : '';

        // Get template name for display
        var templateName = $('#gps_test_email_template option:selected').text();

        // Disable button and show loading
        $button.prop('disabled', true).text('Sending...');
        $result.hide();

        // Send AJAX request
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'gps_send_test_email',
                nonce: nonce,
                test_email: email,
                template: template
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success')
                        .html('✓ ' + templateName + ' sent successfully! ' + response.data.message)
                        .show();
                } else {
                    $result.removeClass('success').addClass('error')
                        .html('✗ ' + response.data.message)
                        .show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error')
                    .html('✗ An error occurred. Please try again.')
                    .show();
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false).text('Send Test Email');
            }
        });
    });

    // Auto-hide success messages after 5 seconds
    $(document).on('DOMSubtreeModified', '#gps-test-email-result.success', function() {
        setTimeout(function() {
            $('#gps-test-email-result.success').fadeOut();
        }, 5000);
    });

    // Email Template Preview Selector
    function loadEmailPreview(template) {
        var $container = $('#gps-preview-container');
        var ajaxUrl = typeof gpsEmailSettings !== 'undefined' ? gpsEmailSettings.ajaxUrl : ajaxurl;
        var nonce = typeof gpsEmailSettings !== 'undefined' ? gpsEmailSettings.nonce : '';

        // Show loading state
        $container.html('<div style="text-align: center; padding: 40px; color: #64748b;"><div style="font-size: 48px; margin-bottom: 16px;">⏳</div><p>Loading preview...</p></div>');

        // Map template to action
        var actionMap = {
            'ticket': 'gps_preview_ticket_email',
            'seminar_welcome': 'gps_preview_seminar_welcome',
            'ce_credits': 'gps_preview_ce_credits',
            'session_reminder': 'gps_preview_session_reminder',
            'missed_session': 'gps_preview_missed_session'
        };

        var action = actionMap[template] || 'gps_preview_ticket_email';

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                nonce: nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    $container.html(response.data.html);
                } else {
                    $container.html('<div style="text-align: center; padding: 40px; color: #ef4444;"><div style="font-size: 48px; margin-bottom: 16px;">❌</div><p>Failed to load preview</p></div>');
                }
            },
            error: function() {
                $container.html('<div style="text-align: center; padding: 40px; color: #ef4444;"><div style="font-size: 48px; margin-bottom: 16px;">❌</div><p>Error loading preview</p></div>');
            }
        });
    }

    // Handle template selection change
    $('#gps_email_template_selector').on('change', function() {
        var selectedTemplate = $(this).val();
        console.log('Template selected:', selectedTemplate);
        loadEmailPreview(selectedTemplate);
    });

    // Load initial preview when Test Email tab is activated
    $('.gps-nav-tab[data-tab="test"]').on('click', function() {
        setTimeout(function() {
            var selectedTemplate = $('#gps_email_template_selector').val() || 'ticket';
            loadEmailPreview(selectedTemplate);
        }, 100);
    });

    // Load preview on page load if on test tab
    if ($('.gps-nav-tab[data-tab="test"]').hasClass('active')) {
        var selectedTemplate = $('#gps_email_template_selector').val() || 'ticket';
        loadEmailPreview(selectedTemplate);
    }
});
