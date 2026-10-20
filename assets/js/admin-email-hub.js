/**
 * GPS Courses › Emails hub: live preview while editing texts or Brand,
 * placeholder chips, desktop / mobile preview width, logo picker.
 */
(function ($) {
    'use strict';

    if (typeof gpsEmailHub === 'undefined') {
        return;
    }

    var $stage = $('.gps-email-preview__stage');
    var $frame = $('.gps-email-preview__frame');
    var timer = null;
    var request = null;

    function render(data) {
        if (!$frame.length) {
            return;
        }
        data.action = 'gps_email_preview';
        data.nonce = gpsEmailHub.nonce;
        if (request) {
            request.abort();
        }
        $stage.addClass('is-loading');
        request = $.post(gpsEmailHub.ajax, data).done(function (res) {
            if (res && res.success) {
                $frame.attr('srcdoc', res.data.html);
                $('.gps-email-preview__subject').text(res.data.subject);
            }
        }).always(function () {
            $stage.removeClass('is-loading');
        });
    }

    function later(fn) {
        clearTimeout(timer);
        timer = setTimeout(fn, 400);
    }

    /* ---------- One email: texts ---------- */
    var $texts = $('.gps-email-detail__texts');
    var lastField = null;

    function textData() {
        var data = { email: $texts.data('email'), texts: {} };
        $texts.find('.gps-email-input').each(function () {
            var m = this.name.match(/texts\[(\w+)\]/);
            if (m) {
                data.texts[m[1]] = this.value;
            }
        });
        return data;
    }

    if ($texts.length) {
        $texts.on('input', '.gps-email-input', function () {
            later(function () { render(textData()); });
        });
        $texts.on('focus', '.gps-email-input', function () {
            lastField = this;
        });
        $texts.on('click', '.gps-chip', function () {
            var field = lastField || $texts.find('.gps-email-input').get(0);
            if (!field) {
                return;
            }
            var text = $(this).data('insert');
            var start = field.selectionStart != null ? field.selectionStart : field.value.length;
            var end = field.selectionEnd != null ? field.selectionEnd : field.value.length;
            field.value = field.value.slice(0, start) + text + field.value.slice(end);
            field.focus();
            field.selectionStart = field.selectionEnd = start + text.length;
            $(field).trigger('input');
        });
    }

    /* ---------- Preview width ---------- */
    $('.gps-segmented').on('click', 'button', function () {
        var $b = $(this);
        $b.addClass('is-on').attr('aria-pressed', 'true').siblings().removeClass('is-on').attr('aria-pressed', 'false');
        $frame.css('width', $b.data('width') + 'px');
    });

    /* ---------- Brand ---------- */
    var $brand = $('#gps-email-brand');

    function brandData() {
        var data = { email: $('#gps-brand-preview-email').val(), brand: {} };
        $brand.find('[data-brand]').each(function () {
            data.brand[this.name] = $(this).val();
        });
        return data;
    }

    if ($brand.length) {
        var refresh = function () {
            later(function () { render(brandData()); });
        };
        if ($.fn.wpColorPicker) {
            $brand.find('.gps-color').wpColorPicker({ change: refresh, clear: refresh });
        }
        $brand.on('input change', 'input[data-brand]', refresh);
        $('#gps-brand-preview-email').on('change', function () {
            render(brandData());
        });

        var picker = null;
        $brand.on('click', '.gps-logo-pick', function (e) {
            e.preventDefault();
            if (!picker) {
                picker = wp.media({
                    title: gpsEmailHub.i18n.chooseLogo,
                    button: { text: gpsEmailHub.i18n.useLogo },
                    library: { type: 'image' },
                    multiple: false
                });
                picker.on('select', function () {
                    var file = picker.state().get('selection').first().toJSON();
                    var isSvg = /\.svg(\?|$)/i.test(file.url);
                    $('#gps-brand-gps_email_logo').val(file.url).trigger('change');
                    if (!isSvg) {
                        $('.gps-logo-field__swatch img').attr('src', file.url);
                    }
                    $('.gps-logo-note').text(isSvg ? gpsEmailHub.i18n.svgWarn : '');
                });
            }
            picker.open();
        });
    }
})(jQuery);
