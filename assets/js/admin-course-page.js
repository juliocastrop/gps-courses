/**
 * GPS Courses - course editor "Page content" tab and Settings → Course page:
 * instructor picker, gallery (media modal), FAQ repeater, show/hide of the
 * custom format / sponsors / refund fields.
 */
(function ($) {
    'use strict';

    var cfg = window.gpsCoursePageAdmin || {};
    var i18n = cfg.i18n || {};

    $(function () {
        /* ---------- Custom format / sponsors / refund toggles ---------- */
        $('#gps-format').on('change', function () {
            $('#gps-format-custom').toggle($(this).val() === '__custom');
        });
        $('#gps-sponsors-mode').on('change', function () {
            $('#gps-sponsors-custom').toggle($(this).val() === 'custom');
        });
        $('#gps-refund-mode').on('change', function () {
            $('#gps-refund-text').toggle($(this).val() === 'custom');
        });

        /* ---------- Instructors ---------- */
        var $chips = $('#gps-speaker-chips');
        var $search = $('#gps-speaker-search');
        var options = {};
        $('#gps-speaker-options option').each(function () {
            options[$(this).attr('value').toLowerCase()] = { id: $(this).data('id'), label: $(this).attr('value') };
        });

        function addSpeaker() {
            var key = $.trim($search.val()).toLowerCase();
            if (!key) { return; }
            var match = options[key];
            if (!match) {
                // Unique partial match
                var hits = Object.keys(options).filter(function (k) { return k.indexOf(key) !== -1; });
                if (hits.length === 1) { match = options[hits[0]]; }
            }
            if (!match) {
                window.alert(i18n.notFound || 'Speaker not found.');
                return;
            }
            if ($chips.find('input[value="' + match.id + '"]').length) {
                $search.val('');
                return;
            }
            var $chip = $('<span class="gps-pc-chip"></span>').text(match.label);
            $chip.append($('<input type="hidden" name="gps_speaker_ids[]">').val(match.id));
            $chip.append($('<button type="button">&times;</button>').attr('aria-label', (i18n.remove || 'Remove') + ' ' + match.label));
            $chips.append($chip);
            $search.val('');
        }
        $search.on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addSpeaker(); }
        }).on('change', function () {
            // Picking a datalist option fires change with the full label
            if (options[$.trim($search.val()).toLowerCase()]) { addSpeaker(); }
        });
        $chips.on('click', 'button', function () { $(this).closest('.gps-pc-chip').remove(); });
        if ($.fn.sortable) { $chips.sortable({ items: '.gps-pc-chip', tolerance: 'pointer' }); }

        /* ---------- Gallery ---------- */
        var $gIds = $('#gps-gallery-ids');
        var $gList = $('#gps-gallery-list');
        function syncGallery() {
            $gIds.val($gList.children('li').map(function () { return $(this).data('id'); }).get().join(','));
        }
        var frame;
        $('#gps-gallery-add').on('click', function (e) {
            e.preventDefault();
            if (!window.wp || !wp.media) { return; }
            if (!frame) {
                frame = wp.media({
                    title: i18n.gallery || 'Course gallery',
                    button: { text: i18n.addImages || 'Add to gallery' },
                    library: { type: 'image' },
                    multiple: 'add'
                });
                frame.on('select', function () {
                    frame.state().get('selection').each(function (att) {
                        var a = att.toJSON();
                        if ($gList.children('li[data-id="' + a.id + '"]').length) { return; }
                        var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
                        var $li = $('<li></li>').attr('data-id', a.id);
                        $li.append($('<img alt="">').attr('src', src));
                        $li.append($('<button type="button">&times;</button>').attr('aria-label', i18n.remove || 'Remove'));
                        $gList.append($li);
                    });
                    syncGallery();
                });
            }
            frame.open();
        });
        $gList.on('click', 'button', function () { $(this).closest('li').remove(); syncGallery(); });
        if ($.fn.sortable) { $gList.sortable({ update: syncGallery, tolerance: 'pointer' }); }

        /* ---------- FAQ repeaters ---------- */
        $('.gps-pc-faq-add').on('click', function () {
            var id = $(this).data('target');
            var tpl = $('#' + id + '-tpl').html();
            var $row = $($.trim(tpl));
            $('#' + id).append($row);
            $row.find('input').trigger('focus');
        });
        $(document).on('click', '.gps-pc-faq-remove', function () { $(this).closest('.gps-pc-faq').remove(); });
        if ($.fn.sortable) {
            $('.gps-pc-faqs').sortable({ items: '> .gps-pc-faq', handle: '.gps-pc-faq__handle', tolerance: 'pointer' });
        }
    });
})(jQuery);
