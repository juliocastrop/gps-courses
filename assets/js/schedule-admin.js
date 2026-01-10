/**
 * GPS Schedule Builder Admin JavaScript
 */
(function($) {
    'use strict';

    let topicIndex = 0;

    $(document).ready(function() {
        // Wait for TinyMCE to be fully loaded before initializing
        if (typeof tinymce !== 'undefined' && tinymce.dom && tinymce.dom.Event) {
            initScheduleBuilder();
        } else {
            // If TinyMCE isn't ready yet, wait a bit
            setTimeout(function() {
                initScheduleBuilder();
            }, 500);
        }
    });

    function initScheduleBuilder() {
        // Set initial index based on existing topics
        topicIndex = $('.gps-topic-item').length;

        // Initialize sortable
        initSortable();

        // Add topic button
        $('#gps-add-topic').on('click', addTopic);

        // Topic toggle
        $(document).on('click', '.topic-header', function(e) {
            if ($(e.target).hasClass('drag-handle') || $(e.target).closest('.drag-handle').length) {
                return;
            }
            toggleTopic($(this).closest('.gps-topic-item'));
        });

        // Remove topic
        $(document).on('click', '.remove-topic', function(e) {
            e.stopPropagation();
            removeTopic($(this).closest('.gps-topic-item'));
        });

        // Update topic title on name change
        $(document).on('input', '.topic-name-input', function() {
            const title = $(this).val() || gpsSchedule.topicName;
            $(this).closest('.gps-topic-item').find('.topic-title').text(title);
        });

        // Show tooltip on hover
        $(document).on('mouseenter', '.tooltip', function() {
            const title = $(this).attr('title');
            if (title) {
                $(this).data('tipText', title).removeAttr('title');
                $('<p class="gps-tooltip"></p>')
                    .text(title)
                    .appendTo('body')
                    .fadeIn('fast');
            }
        }).on('mousemove', '.tooltip', function(e) {
            $('.gps-tooltip').css({
                top: e.pageY + 10,
                left: e.pageX + 10
            });
        }).on('mouseleave', '.tooltip', function() {
            $(this).attr('title', $(this).data('tipText'));
            $('.gps-tooltip').remove();
        });

        // Add tooltip styles
        if (!$('#gps-tooltip-styles').length) {
            $('<style id="gps-tooltip-styles">')
                .text('.gps-tooltip{position:absolute;background:#1d2327;color:#fff;padding:8px 12px;border-radius:4px;font-size:12px;max-width:250px;z-index:10000;box-shadow:0 2px 8px rgba(0,0,0,0.2);}')
                .appendTo('head');
        }
    }

    function initSortable() {
        $('#gps-topics-container').sortable({
            handle: '.drag-handle',
            placeholder: 'ui-sortable-placeholder',
            cursor: 'grabbing',
            opacity: 0.9,
            tolerance: 'pointer',
            update: function() {
                updateTopicIndexes();
            }
        });
    }

    function addTopic() {
        const template = $('#gps-topic-template').html();
        const html = template.replace(/\{\{INDEX\}\}/g, topicIndex);

        const $topic = $(html);
        $('#gps-topics-container').append($topic);

        // Initialize TinyMCE editor for the new topic
        const editorId = 'gps_topic_description_' + topicIndex;
        if (typeof wp !== 'undefined' && typeof wp.editor !== 'undefined') {
            setTimeout(() => {
                try {
                    wp.editor.initialize(editorId, {
                        tinymce: {
                            wpautop: true,
                            plugins: 'lists,link,paste',
                            toolbar1: 'bold,italic,bullist,numlist,link,unlink',
                            toolbar2: '',
                            forced_root_block: 'p',
                            force_p_newlines: true,
                            force_br_newlines: false,
                            convert_newlines_to_brs: false,
                            remove_linebreaks: false,
                            keep_styles: true,
                            valid_elements: '*[*]',
                            extended_valid_elements: 'ul[*],ol[*],li[*],p[*],strong,em,a[href|target],br'
                        },
                        quicktags: true,
                        mediaButtons: false
                    });
                } catch (error) {
                    console.error('GPS Schedule: Error initializing editor ' + editorId, error);
                }
            }, 100);
        }

        // Scroll to new topic
        $('html, body').animate({
            scrollTop: $topic.offset().top - 100
        }, 500);

        // Focus on name input
        setTimeout(() => {
            $topic.find('.topic-name-input').focus();
        }, 600);

        topicIndex++;
        updateEmptyState();
    }

    function removeTopic($topic) {
        if (!confirm(gpsSchedule.confirmDelete)) {
            return;
        }

        // Remove TinyMCE editor instance if exists
        const $editor = $topic.find('.wp-editor-area');
        if ($editor.length && typeof wp !== 'undefined' && typeof wp.editor !== 'undefined') {
            try {
                const editorId = $editor.attr('id');
                wp.editor.remove(editorId);
            } catch (error) {
                console.error('GPS Schedule: Error removing editor', error);
            }
        }

        $topic.fadeOut(300, function() {
            $(this).remove();
            updateTopicIndexes();
            updateEmptyState();
        });
    }

    function toggleTopic($topic) {
        $topic.toggleClass('collapsed');
    }

    function updateTopicIndexes() {
        $('#gps-topics-container .gps-topic-item').each(function(index) {
            $(this).attr('data-index', index);

            // Update editor ID and name
            const $editor = $(this).find('.wp-editor-area');
            if ($editor.length) {
                const oldEditorId = $editor.attr('id');
                const newEditorId = 'gps_topic_description_' + index;

                // Only update if ID changed
                if (oldEditorId !== newEditorId && typeof wp !== 'undefined' && typeof wp.editor !== 'undefined') {
                    try {
                        // Check if editor exists before trying to get content
                        let content = '';
                        const editor = tinymce && tinymce.get(oldEditorId);

                        if (editor) {
                            // Get content directly from TinyMCE editor
                            content = editor.getContent();
                            // Remove the editor instance directly through TinyMCE
                            // Don't use wp.editor.remove() as it causes getEditorType errors
                            tinymce.remove('#' + oldEditorId);
                        } else {
                            // Editor doesn't exist yet, just get textarea value
                            content = $editor.val();
                        }

                        $editor.attr('id', newEditorId);

                        setTimeout(() => {
                            try {
                                wp.editor.initialize(newEditorId, {
                                    tinymce: {
                                        wpautop: true,
                                        plugins: 'lists,link,paste',
                                        toolbar1: 'bold,italic,bullist,numlist,link,unlink',
                                        toolbar2: '',
                                        forced_root_block: 'p',
                                        force_p_newlines: true,
                                        force_br_newlines: false,
                                        convert_newlines_to_brs: false,
                                        remove_linebreaks: false,
                                        keep_styles: true,
                                        valid_elements: '*[*]',
                                        extended_valid_elements: 'ul[*],ol[*],li[*],p[*],strong,em,a[href|target],br'
                                    },
                                    quicktags: true,
                                    mediaButtons: false
                                });

                                setTimeout(() => {
                                    try {
                                        const newEditor = tinymce.get(newEditorId);
                                        if (newEditor) {
                                            newEditor.setContent(content);
                                        } else {
                                            $editor.val(content);
                                        }
                                    } catch (error) {
                                        console.error('GPS Schedule: Error setting content for ' + newEditorId, error);
                                        $editor.val(content);
                                    }
                                }, 100);
                            } catch (error) {
                                console.error('GPS Schedule: Error reinitializing editor ' + newEditorId, error);
                            }
                        }, 100);
                    } catch (error) {
                        console.error('GPS Schedule: Error getting content from ' + oldEditorId, error);
                    }
                }
            }

            // Update all name attributes
            $(this).find('[name^="gps_topics"]').each(function() {
                const name = $(this).attr('name');
                const newName = name.replace(/gps_topics\[\d+\]/, 'gps_topics[' + index + ']');
                $(this).attr('name', newName);
            });
        });
    }

    function updateEmptyState() {
        const $container = $('#gps-topics-container');
        const hasTopics = $container.find('.gps-topic-item').length > 0;

        if (!hasTopics && !$container.find('.gps-topics-empty').length) {
            $container.append(
                '<div class="gps-topics-empty">' +
                '<span class="dashicons dashicons-calendar-alt"></span>' +
                '<p>' + gpsSchedule.addTopic + '</p>' +
                '</div>'
            );
        } else if (hasTopics) {
            $container.find('.gps-topics-empty').remove();
        }
    }

    // Initialize empty state on load
    updateEmptyState();

    // Prevent form submission on Enter key in text inputs (except textarea)
    $(document).on('keypress', '.gps-schedule-builder input[type="text"]', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            return false;
        }
    });

    // Auto-save notification (optional enhancement)
    let saveTimeout;
    $(document).on('change input', '.gps-schedule-builder input, .gps-schedule-builder select, .gps-schedule-builder textarea', function() {
        clearTimeout(saveTimeout);

        // Add "unsaved changes" indicator
        if (!$('.gps-unsaved-indicator').length) {
            $('#publish').before('<span class="gps-unsaved-indicator" style="color:#d63638;margin-right:10px;">●</span>');
        }

        saveTimeout = setTimeout(function() {
            $('.gps-unsaved-indicator').remove();
        }, 3000);
    });

    // Remove unsaved indicator on publish
    $('#publish, #save-post').on('click', function() {
        $('.gps-unsaved-indicator').remove();
    });

    // CRITICAL: Sync all TinyMCE editors before form submission
    // This prevents data loss when editors haven't auto-saved their content
    let formSubmitting = false;
    $('#post').on('submit', function(e) {
        if (formSubmitting) {
            return true; // Allow submission to proceed
        }

        e.preventDefault();
        const $form = $(this);

        console.log('GPS Schedule: Syncing TinyMCE editors before submission...');

        try {
            // Sync all TinyMCE editors to their textareas
            if (typeof tinymce !== 'undefined' && tinymce.editors) {
                $('.gps-topic-item .wp-editor-area').each(function() {
                    const editorId = $(this).attr('id');

                    if (!editorId) {
                        console.log('GPS Schedule: Skipping editor with no ID');
                        return;
                    }

                    try {
                        const editor = tinymce.get(editorId);

                        if (editor && typeof editor.save === 'function') {
                            try {
                                console.log('GPS Schedule: Syncing editor ' + editorId);
                                editor.save();

                                // Also manually set textarea value to ensure it's there
                                const content = editor.getContent();
                                $(this).val(content);
                                console.log('GPS Schedule: Editor ' + editorId + ' content length: ' + content.length);
                            } catch (error) {
                                console.error('GPS Schedule: Error syncing editor ' + editorId, error);
                                // Try to at least preserve textarea content if editor fails
                                const textContent = $(this).val();
                                console.log('GPS Schedule: Fallback - textarea has ' + textContent.length + ' chars');
                            }
                        } else {
                            // No editor found, just ensure textarea value is preserved
                            console.log('GPS Schedule: No editor for ' + editorId + ', using textarea value');
                        }
                    } catch (error) {
                        console.error('GPS Schedule: Error accessing editor ' + editorId, error);
                    }
                });
            }

            // Also ensure text editor tabs content is preserved (if in text mode)
            $('.gps-topic-item .wp-editor-area').each(function() {
                if ($(this).is(':visible')) {
                    const content = $(this).val();
                    console.log('GPS Schedule: Text area visible with ' + content.length + ' chars');
                }
            });
        } catch (error) {
            console.error('GPS Schedule: Error during editor sync', error);
        }

        console.log('GPS Schedule: All editors synced, submitting form...');

        // Small delay to ensure DOM updates complete
        setTimeout(function() {
            formSubmitting = true;
            $form.off('submit').submit();
        }, 100);

        return false;
    });

    // Also sync editors when WordPress autosave runs
    $(document).on('autosave-enable-buttons', function() {
        if (typeof tinymce !== 'undefined' && tinymce.editors) {
            $('.gps-topic-item .wp-editor-area').each(function() {
                try {
                    const editorId = $(this).attr('id');
                    const editor = tinymce.get(editorId);
                    if (editor && typeof editor.save === 'function') {
                        editor.save();
                    }
                } catch (error) {
                    console.error('GPS Schedule: Error syncing editor during autosave', error);
                }
            });
        }
    });

})(jQuery);
