/**
 * GPS Courses - Email Template Manager
 */
(function($) {
    'use strict';

    let editor;
    let previewTimeout;

    $(document).ready(function() {
        initCodeEditor();
        initPreview();
        initEventHandlers();
        initVariableCopy();
    });

    /**
     * Initialize CodeMirror editor
     */
    function initCodeEditor() {
        const textarea = document.getElementById('gps-template-editor');
        if (!textarea) return;

        const editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
        editorSettings.codemirror = _.extend(
            {},
            editorSettings.codemirror,
            {
                mode: 'htmlmixed',
                lineNumbers: true,
                lineWrapping: true,
                styleActiveLine: true,
                matchBrackets: true,
                autoCloseTags: true,
                theme: 'default'
            }
        );

        editor = wp.codeEditor.initialize(textarea, editorSettings);

        // Auto-preview on change (debounced)
        editor.codemirror.on('change', function() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(function() {
                refreshPreview();
            }, 1000);
        });
    }

    /**
     * Initialize preview iframe
     */
    function initPreview() {
        refreshPreview();
    }

    /**
     * Refresh preview iframe
     */
    function refreshPreview() {
        const content = editor ? editor.codemirror.getValue() : $('#gps-template-editor').val();
        const template = $('#gps-template-editor').data('template');

        $('.gps-preview-loading').show();

        $.ajax({
            url: gpsEmailTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'gps_preview_email_template',
                nonce: gpsEmailTemplates.nonce,
                template: template,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    const iframe = document.getElementById('gps-preview-frame');
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

                    iframeDoc.open();
                    iframeDoc.write(response.data.html);
                    iframeDoc.close();

                    $('.gps-preview-loading').fadeOut();
                } else {
                    showNotice('error', response.data.message || 'Preview failed');
                    $('.gps-preview-loading').hide();
                }
            },
            error: function() {
                showNotice('error', 'Failed to load preview');
                $('.gps-preview-loading').hide();
            }
        });
    }

    /**
     * Initialize event handlers
     */
    function initEventHandlers() {
        // Save template
        $('#gps-save-template').on('click', function() {
            saveTemplate();
        });

        // Reset template
        $('#gps-reset-template').on('click', function() {
            if (confirm('Are you sure you want to reset this template to default? This cannot be undone.')) {
                resetTemplate();
            }
        });

        // Refresh preview
        $('#gps-refresh-preview').on('click', function() {
            refreshPreview();
        });

        // Send test email
        $('#gps-send-test-email').on('click', function() {
            $('#gps-test-email-modal').fadeIn();
        });

        // Confirm send test
        $('#gps-confirm-send-test').on('click', function() {
            sendTestEmail();
        });

        // Close modal
        $('.gps-modal-close').on('click', function() {
            $(this).closest('.gps-modal').fadeOut();
        });

        // Close modal on outside click
        $('.gps-modal').on('click', function(e) {
            if ($(e.target).hasClass('gps-modal')) {
                $(this).fadeOut();
            }
        });

        // Device preview tabs
        $('.gps-device-tab').on('click', function() {
            const device = $(this).data('device');

            $('.gps-device-tab').removeClass('active');
            $(this).addClass('active');

            $('.gps-preview-frame-wrapper')
                .removeClass('desktop mobile')
                .addClass(device);
        });

        // Editor mode switch
        $('input[name="editor_mode"]').on('change', function() {
            const mode = $(this).val();
            if (mode === 'code') {
                $('.CodeMirror').show();
                editor.codemirror.refresh();
            } else {
                // Visual mode would go here (future enhancement)
                $('.CodeMirror').show();
            }
        });
    }

    /**
     * Initialize variable copy to clipboard
     */
    function initVariableCopy() {
        $('.gps-variable-code').on('click', function() {
            const text = $(this).text().trim();
            copyToClipboard(text);

            // Visual feedback
            const $this = $(this);
            $this.addClass('copied');
            setTimeout(function() {
                $this.removeClass('copied');
            }, 1000);

            showNotice('success', 'Variable copied to clipboard: ' + text);
        });
    }

    /**
     * Save template
     */
    function saveTemplate() {
        const content = editor ? editor.codemirror.getValue() : $('#gps-template-editor').val();
        const template = $('#gps-template-editor').data('template');
        const $button = $('#gps-save-template');

        $button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: gpsEmailTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'gps_save_email_template',
                nonce: gpsEmailTemplates.nonce,
                template: template,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message || 'Save failed');
                }
            },
            error: function() {
                showNotice('error', 'Failed to save template');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save Template');
            }
        });
    }

    /**
     * Reset template to default
     */
    function resetTemplate() {
        const template = $('#gps-template-editor').data('template');
        const $button = $('#gps-reset-template');

        $button.prop('disabled', true).text('Resetting...');

        $.ajax({
            url: gpsEmailTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'gps_reset_email_template',
                nonce: gpsEmailTemplates.nonce,
                template: template
            },
            success: function(response) {
                if (response.success) {
                    // Update editor with default content
                    if (editor) {
                        editor.codemirror.setValue(response.data.content);
                    } else {
                        $('#gps-template-editor').val(response.data.content);
                    }

                    refreshPreview();
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message || 'Reset failed');
                }
            },
            error: function() {
                showNotice('error', 'Failed to reset template');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-image-rotate"></span> Reset to Default');
            }
        });
    }

    /**
     * Send test email
     */
    function sendTestEmail() {
        const content = editor ? editor.codemirror.getValue() : $('#gps-template-editor').val();
        const template = $('#gps-template-editor').data('template');
        const email = $('#gps-test-email-address').val();
        const $button = $('#gps-confirm-send-test');

        if (!email || !isValidEmail(email)) {
            showNotice('error', 'Please enter a valid email address');
            return;
        }

        $button.prop('disabled', true).text('Sending...');

        $.ajax({
            url: gpsEmailTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'gps_send_test_email',
                nonce: gpsEmailTemplates.nonce,
                template: template,
                content: content,
                email: email
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    $('#gps-test-email-modal').fadeOut();
                } else {
                    showNotice('error', response.data.message || 'Send failed');
                }
            },
            error: function() {
                showNotice('error', 'Failed to send test email');
            },
            complete: function() {
                $button.prop('disabled', false).text('Send Test Email');
            }
        });
    }

    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
    }

    /**
     * Validate email
     */
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

        $('.wrap h1').after($notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Make dismissible
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }

})(jQuery);
