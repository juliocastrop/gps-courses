/**
 * GPS Courses - "Notify me about the next dates" form (Interest_List).
 *
 * Pages are served from the FlyingPress cache, so the nonce printed in the
 * HTML may have expired: ask admin-ajax for a fresh one right before sending.
 * Also exposes window.gpsFreshNonce() for the sold-out waitlist form.
 */
(function () {
    'use strict';

    var cfg = window.gpsInterest || {};
    var i18n = cfg.i18n || {};

    function ajaxUrl(form) {
        return cfg.ajaxUrl || (form && form.getAttribute('action')) || '/wp-admin/admin-ajax.php';
    }

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        }).then(function (r) { return r.json(); });
    }

    /** Resolves to a fresh nonce for 'gpsc_interest' / 'gps_waitlist', or null. */
    function freshNonce(forAction, url) {
        var data = new FormData();
        data.append('action', 'gpsc_form_nonce');
        data.append('for', forAction);
        return post(url || ajaxUrl(null), data)
            .then(function (json) { return json && json.success && json.data ? json.data.nonce : null; })
            .catch(function () { return null; });
    }
    window.gpsFreshNonce = freshNonce;

    function show(form, ok, message) {
        var box = form.querySelector('.gps-waitlist-response');
        if (!box) { return; }
        box.textContent = message;
        box.classList.remove('success', 'error');
        box.classList.add(ok ? 'success' : 'error');
        box.hidden = false;
        box.style.display = '';
    }

    function setBusy(form, busy) {
        var btn = form.querySelector('[type="submit"]');
        if (!btn) { return; }
        if (busy) {
            btn.dataset.label = btn.dataset.label || btn.textContent;
            btn.textContent = i18n.sending || 'Sending…';
        } else if (btn.dataset.label) {
            btn.textContent = btn.dataset.label;
        }
        btn.disabled = busy;
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function send(form, nonce, retried) {
        var data = new FormData(form);
        data.set('ajax', '1');
        if (nonce) { data.set('nonce', nonce); }
        return post(ajaxUrl(form), data).then(function (json) {
            // An expired nonce despite the refresh: one more try with a new one
            if (!json.success && json.data && json.data.code === 'bad_nonce' && !retried) {
                return freshNonce('gpsc_interest', ajaxUrl(form)).then(function (n) { return send(form, n, true); });
            }
            return json;
        });
    }

    function submit(form) {
        var email = form.querySelector('input[name="email"]');
        if (email && (!email.value.trim() || (email.checkValidity && !email.checkValidity()))) {
            email.setAttribute('aria-invalid', 'true');
            show(form, false, i18n.email || 'Please enter a valid email address.');
            email.focus();
            return;
        }
        if (email) { email.removeAttribute('aria-invalid'); }

        setBusy(form, true);
        freshNonce('gpsc_interest', ajaxUrl(form))
            .then(function (nonce) { return send(form, nonce, false); })
            .then(function (json) {
                var msg = json && json.data && json.data.message ? json.data.message : (i18n.error || 'Something went wrong. Please try again.');
                if (json && json.success) {
                    var fields = form.querySelector('.gps-waitlist-fields');
                    if (fields) { fields.hidden = true; }
                    form.classList.add('is-done');
                    show(form, true, msg);
                    var box = form.querySelector('.gps-waitlist-response');
                    if (box) { box.setAttribute('tabindex', '-1'); box.focus(); }
                } else {
                    show(form, false, msg);
                    setBusy(form, false);
                }
            })
            .catch(function () {
                show(form, false, i18n.error || 'Something went wrong. Please try again.');
                setBusy(form, false);
            });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList || !form.classList.contains('gps-interest-form')) { return; }
        if (!window.fetch || !window.FormData) { return; } // plain POST fallback
        e.preventDefault();
        submit(form);
    });
})();
