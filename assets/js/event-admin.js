/**
 * GPS Courses - Event Admin Scripts
 * Handles tabbed interface for event editing
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize tabs
        if ($('.gps-event-tabs-wrapper').length) {
            $('.gps-event-tabs-wrapper').tabs({
                active: 0,
                heightStyle: 'content',
                activate: function(event, ui) {
                    // Trigger resize for editors in the activated tab
                    if (typeof tinymce !== 'undefined') {
                        tinymce.editors.forEach(function(editor) {
                            editor.fire('wp-autoresize');
                        });
                    }
                }
            });

            // Hide the postbox container that would normally show metaboxes
            $('#postbox-container-1, #postbox-container-2').hide();

            // Move the submitdiv (publish box) above the tabs
            if ($('#submitdiv').length) {
                $('#submitdiv').prependTo('#post-body');
                $('#submitdiv').addClass('gps-publish-box');
            }
        }

        // Handle category dropdown styling
        if ($.fn.select2 && $('#gps_category').length) {
            $('#gps_category').select2({
                placeholder: 'Select Category',
                allowClear: true,
                width: '100%'
            });
        }

        // Date validation
        $('#gps_start_date, #gps_end_date').on('change', function() {
            var startDate = $('#gps_start_date').val();
            var endDate = $('#gps_end_date').val();

            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                alert('End date must be after start date');
                $('#gps_end_date').val('');
            }
        });

        // Time validation
        $('#gps_start_time, #gps_end_time').on('change', function() {
            var startDate = $('#gps_start_date').val();
            var endDate = $('#gps_end_date').val();
            var startTime = $('#gps_start_time').val();
            var endTime = $('#gps_end_time').val();

            if (startDate === endDate && startTime && endTime && startTime >= endTime) {
                alert('End time must be after start time on the same day');
                $('#gps_end_time').val('');
            }
        });

        // Featured Image handling
        var featuredImageFrame;

        $('#gps-set-featured-image').on('click', function(e) {
            e.preventDefault();

            // Check if we have a post ID
            var postId = $('#post_ID').val();
            if (!postId || postId === '0') {
                alert('Please save the course first before adding an image.');
                return;
            }

            // Check if we have a nonce
            if (!gpsEventAdmin.thumbnailNonce) {
                alert('Please refresh the page and try again.');
                return;
            }

            if (featuredImageFrame) {
                featuredImageFrame.open();
                return;
            }

            featuredImageFrame = wp.media({
                title: 'Set Course Image',
                button: { text: 'Set Image' },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            featuredImageFrame.on('select', function() {
                var attachment = featuredImageFrame.state().get('selection').first().toJSON();
                var postId = $('#post_ID').val();

                console.log('GPS Courses: Setting thumbnail', {
                    postId: postId,
                    attachmentId: attachment.id,
                    hasNonce: !!gpsEventAdmin.thumbnailNonce,
                    ajaxurl: ajaxurl
                });

                // Set the featured image via AJAX
                $.post(ajaxurl, {
                    action: 'gps_set_course_thumbnail',
                    post_id: postId,
                    thumbnail_id: attachment.id,
                    nonce: gpsEventAdmin.thumbnailNonce
                }, function(response) {
                    console.log('GPS Courses: Thumbnail AJAX response', response);

                    // Check if response is successful
                    if (!response.success) {
                        var errorMsg = response.data || 'Unknown error occurred';
                        alert('Error: ' + errorMsg);
                        console.error('GPS Courses: Thumbnail AJAX failed', response);
                        return;
                    }

                    // Update the preview
                    $('#gps-featured-image-container').html(
                        '<img src="' + attachment.url + '" alt="" id="gps-featured-image-preview" style="max-width: 100%; height: auto; border-radius: 6px;">'
                    );

                    // Update button text and show remove button
                    $('#gps-set-featured-image').text('Change Image');

                    if (!$('#gps-remove-featured-image').length) {
                        $('#gps-set-featured-image').after(
                            '<button type="button" class="button" id="gps-remove-featured-image">Remove Image</button>'
                        );
                    }

                    console.log('GPS Courses: Thumbnail set successfully');
                }, 'json').fail(function(xhr, status, error) {
                    console.error('GPS Courses: Thumbnail AJAX error', {
                        status: status,
                        error: error,
                        statusCode: xhr.status,
                        responseText: xhr.responseText
                    });

                    var errorMsg = 'Error: Unable to set course image. ';
                    if (xhr.status === 0) {
                        errorMsg += 'Network connection error.';
                    } else if (xhr.status === 403) {
                        errorMsg += 'Permission denied. Please refresh and try again.';
                    } else if (xhr.status === 500) {
                        errorMsg += 'Server error. Please contact support.';
                    } else {
                        errorMsg += 'Please check your connection and try again.';
                    }

                    alert(errorMsg);
                });
            });

            featuredImageFrame.open();
        });

        // Remove featured image
        $(document).on('click', '#gps-remove-featured-image', function(e) {
            e.preventDefault();

            var postId = $('#post_ID').val();

            $.post(ajaxurl, {
                action: 'gps_remove_course_thumbnail',
                post_id: postId,
                nonce: gpsEventAdmin.thumbnailNonce
            }, function(response) {
                console.log('GPS Courses: Remove thumbnail response', response);

                // Check if response is successful
                if (!response.success) {
                    var errorMsg = response.data || 'Unknown error occurred';
                    alert('Error: ' + errorMsg);
                    console.error('GPS Courses: Remove thumbnail AJAX failed', response);
                    return;
                }

                // Replace with placeholder
                $('#gps-featured-image-container').html(
                    '<div id="gps-featured-image-placeholder" style="background: #f0f0f1; padding: 60px 20px; text-align: center; border: 2px dashed #dcdcde; border-radius: 6px;">' +
                    '<span class="dashicons dashicons-format-image" style="font-size: 48px; color: #c0c0c0;"></span><br>' +
                    '<p style="color: #646970; margin: 10px 0 0 0;">No image selected</p>' +
                    '</div>'
                );

                // Update button text and remove remove button
                $('#gps-set-featured-image').text('Set Course Image');
                $('#gps-remove-featured-image').remove();

                console.log('GPS Courses: Thumbnail removed successfully');
            }, 'json').fail(function(xhr, status, error) {
                console.error('GPS Courses: Remove thumbnail AJAX error', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    responseText: xhr.responseText
                });
                alert('Error: Unable to remove course image. Please check your connection.');
            });
        });

        // Add New Category functionality (using event delegation)
        $(document).on('click', '#gps-add-new-category', function(e) {
            e.preventDefault();
            $('#gps-new-category-form').slideDown();
            $('#gps_new_category_name').focus();
        });

        $(document).on('click', '#gps-cancel-new-category', function(e) {
            e.preventDefault();
            $('#gps-new-category-form').slideUp();
            $('#gps_new_category_name').val('');
        });

        $(document).on('click', '#gps-save-new-category', function(e) {
            e.preventDefault();
            var categoryName = $('#gps_new_category_name').val().trim();

            if (!categoryName) {
                alert('Please enter a category name');
                return;
            }

            var $button = $(this);
            var $spinner = $('#gps-new-category-form .spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.post(ajaxurl, {
                action: 'gps_add_category',
                name: categoryName,
                nonce: $('#gps_event_meta_nonce').val()
            }, function(response) {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    // Add new option to dropdown
                    var newOption = $('<option>', {
                        value: response.data.term_id,
                        text: response.data.name,
                        selected: true
                    });
                    $('#gps_category').append(newOption);

                    // Refresh select2 if active
                    if ($.fn.select2 && $('#gps_category').hasClass('select2-hidden-accessible')) {
                        $('#gps_category').select2('destroy').select2({
                            placeholder: 'Select Category',
                            allowClear: true,
                            width: '100%'
                        });
                    }

                    // Clear and hide form
                    $('#gps_new_category_name').val('');
                    $('#gps-new-category-form').slideUp();

                    alert('Category added successfully!');
                } else {
                    alert('Error: ' + (response.data || 'Could not add category'));
                }
            }).fail(function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                alert('Error: Could not connect to server');
            });
        });

        // Add New Tag functionality (using event delegation)
        $(document).on('click', '#gps-add-new-tag', function(e) {
            e.preventDefault();
            $('#gps-new-tag-form').slideDown();
            $('#gps_new_tag_name').focus();
        });

        $(document).on('click', '#gps-cancel-new-tag', function(e) {
            e.preventDefault();
            $('#gps-new-tag-form').slideUp();
            $('#gps_new_tag_name').val('');
        });

        $(document).on('click', '#gps-save-new-tag', function(e) {
            e.preventDefault();
            var tagName = $('#gps_new_tag_name').val().trim();

            if (!tagName) {
                alert('Please enter a tag name');
                return;
            }

            var $button = $(this);
            var $spinner = $('#gps-new-tag-form .spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.post(ajaxurl, {
                action: 'gps_add_tag',
                name: tagName,
                nonce: $('#gps_event_meta_nonce').val()
            }, function(response) {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    // Add new option to tags select (already selected)
                    var newOption = $('<option>', {
                        value: response.data.term_id,
                        text: response.data.name,
                        selected: true
                    });
                    $('#gps_tags').append(newOption);

                    // Clear and hide form
                    $('#gps_new_tag_name').val('');
                    $('#gps-new-tag-form').slideUp();

                    alert('Tag added successfully!');
                } else {
                    alert('Error: ' + (response.data || 'Could not add tag'));
                }
            }).fail(function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                alert('Error: Could not connect to server');
            });
        });
    });

})(jQuery);
