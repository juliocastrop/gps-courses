/**
 * GPS account screens: tabs, 3-step registration, code boxes, password meter
 * and inline validation. The forms still post to WooCommerce; this only
 * guides the visitor through them. Config comes from window.gpsAuth.
 */
(function () {
    'use strict';

    var root = document.getElementById('gps-auth');
    if (!root || !window.gpsAuth) {
        return;
    }

    var cfg = window.gpsAuth;
    var t = cfg.i18n || {};
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    function $(sel, ctx) { return (ctx || root).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }

    /* ------------------------------------------------------------------
     * Field errors and alerts
     * ------------------------------------------------------------------ */

    function fieldOf(el) { return el.closest('.gps-field'); }

    function setError(el, message) {
        var field = fieldOf(el);
        if (!field) { return; }
        var slot = $('[data-gps-err]', field);
        field.classList.toggle('is-invalid', !!message);
        if (slot) { slot.textContent = message || ''; }
        if (el.setAttribute) { el.setAttribute('aria-invalid', message ? 'true' : 'false'); }
    }

    function alertIn(scope, message, isOk) {
        var box = $('[data-gps-alert]', scope);
        if (!box) { return; }
        if (!message) { box.hidden = true; box.innerHTML = ''; return; }
        box.classList.toggle('gps-alert--ok', !!isOk);
        if (typeof message === 'string') { box.textContent = message; } else { box.innerHTML = ''; box.appendChild(message); }
        box.hidden = false;
    }

    function passwordOk(value) {
        return value.length >= (cfg.minPass || 8) && /\d/.test(value);
    }

    /** Validate one input; returns true when valid. */
    function check(el) {
        var v = (el.type === 'checkbox') ? el.checked : (el.value || '').trim();
        var msgKey = el.getAttribute('data-gps-msg');
        var msg = '';

        if (el.type === 'checkbox') {
            if (el.required && !v) { msg = t[msgKey] || t.required; }
        } else if (el.required && !v) {
            msg = t[msgKey] || t.required;
        } else if (v && el.type === 'email' && !EMAIL_RE.test(v)) {
            msg = t.email;
        } else if (v && el.hasAttribute('data-gps-meter') && !passwordOk(el.value)) {
            msg = t.password;
        } else if (el.hasAttribute('data-gps-match')) {
            var other = document.getElementById(el.getAttribute('data-gps-match'));
            if (other && el.value !== other.value) { msg = t.passwordMatch; }
        } else if (v && el.type === 'tel') {
            var digits = v.replace(/\D/g, '');
            if (!/^\+?[\d\s().-]+$/.test(v) || digits.length < 7 || digits.length > 15) { msg = t.phone; }
        }

        setError(el, msg);
        return !msg;
    }

    function checkAll(scope) {
        var first = null;
        $$('input, select', scope).forEach(function (el) {
            if (el.type === 'hidden' || el.disabled || el.closest('[data-gps-code]')) { return; }
            if (!el.required && !el.value && el.type !== 'tel') { return; }
            if (!check(el) && !first) { first = el; }
        });
        if (first) { first.focus(); }
        return !first;
    }

    // Clear an error as soon as the visitor fixes the field
    root.addEventListener('input', function (e) {
        var field = e.target.closest && e.target.closest('.gps-field.is-invalid');
        if (field && !e.target.closest('[data-gps-code]')) { check(e.target); }
    });
    root.addEventListener('change', function (e) {
        if (e.target.type === 'checkbox' && e.target.closest('.gps-field.is-invalid')) { check(e.target); }
    });

    /* ------------------------------------------------------------------
     * Tabs
     * ------------------------------------------------------------------ */

    function showTab(name, focus) {
        var tabs = $$('[role="tab"][data-gps-tab]');
        if (!tabs.length) { return; }
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-gps-tab') === name;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            var panel = document.getElementById(tab.getAttribute('aria-controls'));
            if (panel) { panel.hidden = !on; }
        });
        root.setAttribute('data-view', name);
        try {
            var url = new URL(window.location.href);
            if (name === 'register') { url.searchParams.set('action', 'register'); } else { url.searchParams.delete('action'); }
            window.history.replaceState(null, '', url.toString());
        } catch (err) { /* old browser: the links still work */ }
        if (focus) {
            var scope = $('#gps-panel-' + name + ' .gps-step.is-active') || $('#gps-panel-' + name);
            var input = scope && $('input:not([type="hidden"]), select', scope);
            if (input) { input.focus(); }
        }
    }

    root.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('[data-gps-tab]');
        if (!link || !$('[role="tab"]')) { return; }
        e.preventDefault();
        showTab(link.getAttribute('data-gps-tab'), true);
    });

    /* ------------------------------------------------------------------
     * Show / hide password and strength meter
     * ------------------------------------------------------------------ */

    root.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-gps-toggle]');
        if (!btn) { return; }
        var input = document.getElementById(btn.getAttribute('data-gps-toggle'));
        if (!input) { return; }
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? t.hide : t.show;
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    });

    $$('[data-gps-meter]').forEach(function (input) {
        var bar = document.getElementById(input.getAttribute('data-gps-meter'));
        var text = $('[data-gps-meter-text="' + input.getAttribute('data-gps-meter') + '"]');
        var initial = text ? text.textContent : '';
        input.addEventListener('input', function () {
            var v = input.value;
            var score = 0;
            if (v.length >= (cfg.minPass || 8)) { score++; }
            if (/\d/.test(v)) { score++; }
            if (v.length >= 12) { score++; }
            if (/[A-Z]/.test(v) && /[a-z]/.test(v) || /[^A-Za-z0-9]/.test(v)) { score++; }
            if (!passwordOk(v)) { score = Math.min(score, 1); }
            var widths = ['8%', '30%', '62%', '82%', '100%'];
            var colors = ['#b4322a', '#b4322a', '#c47f17', '#1d7a4e', '#1d7a4e'];
            var labels = [t.weak, v.length >= (cfg.minPass || 8) ? t.fair : t.weak, t.good, t.good, t.strong];
            if (bar) {
                bar.style.width = v ? widths[score] : '0';
                bar.style.backgroundColor = colors[score];
            }
            if (text) { text.textContent = v ? labels[score] : initial; }
        });
    });

    /* ------------------------------------------------------------------
     * Simple forms: login, lost password, reset password
     * ------------------------------------------------------------------ */

    $$('form[data-gps-form="login"], form[data-gps-form="lost"], form[data-gps-form="reset"]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!checkAll(form)) { e.preventDefault(); return; }
            var btn = $('.gps-btn', form);
            if (btn) { btn.classList.add('is-busy'); }
        });
    });

    /* ------------------------------------------------------------------
     * Registration: 1 Account -> 2 Verify email -> 3 Your profile
     * ------------------------------------------------------------------ */

    var reg = $('form[data-gps-form="register"]');
    if (!reg) { return; }

    var steps = $$('[data-gps-step]', reg);
    var labels = $$('[data-gps-step-label]');
    var emailInput = $('input[name="email"]', reg);
    var codeHidden = $('#gps_reg_code', reg);
    var boxes = $$('[data-gps-code] input', reg);
    var resendBtn = $('[data-gps-resend]', reg);
    var current = 1;
    var timer = null;

    function stepEl(n) { return $('[data-gps-step="' + n + '"]', reg); }

    function goStep(n) {
        current = n;
        steps.forEach(function (s) { s.classList.toggle('is-active', s.getAttribute('data-gps-step') === String(n)); });
        labels.forEach(function (li) {
            var i = parseInt(li.getAttribute('data-gps-step-label'), 10);
            li.classList.toggle('is-done', i < n);
            li.classList.toggle('is-current', i === n);
            if (i === n) { li.setAttribute('aria-current', 'step'); } else { li.removeAttribute('aria-current'); }
        });
        var focusEl = n === 2 ? boxes[0] : $('input:not([type="hidden"]), select', stepEl(n));
        if (focusEl) { focusEl.focus(); }
        var top = root.getBoundingClientRect().top;
        if (top < 0) { root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    function post(action, data) {
        var body = new FormData();
        body.append('action', action);
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function busy(btn, on, label) {
        if (!btn) { return; }
        if (on) {
            btn.setAttribute('data-label', btn.textContent);
            btn.textContent = label;
            btn.disabled = true;
            btn.classList.add('is-busy');
        } else {
            btn.textContent = btn.getAttribute('data-label') || btn.textContent;
            btn.disabled = false;
            btn.classList.remove('is-busy');
        }
    }

    function startCooldown(seconds) {
        if (!resendBtn) { return; }
        clearInterval(timer);
        var left = seconds;
        resendBtn.disabled = true;
        resendBtn.textContent = (t.resendIn || '%ds').replace('%d', left);
        timer = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(timer);
                resendBtn.disabled = false;
                resendBtn.textContent = t.resend;
                return;
            }
            resendBtn.textContent = (t.resendIn || '%ds').replace('%d', left);
        }, 1000);
    }

    function sendCode() {
        var hp = $('[name="gps_url"]', reg);
        return post('gps_reg_send_code', { email: emailInput.value.trim(), gps_url: hp ? hp.value : '' });
    }

    function loginInstead(message) {
        var wrap = document.createElement('span');
        wrap.appendChild(document.createTextNode(message + ' '));
        var a = document.createElement('a');
        a.href = '#';
        a.textContent = $('#gps-tab-login') ? $('#gps-tab-login').textContent : 'Log in';
        a.addEventListener('click', function (ev) {
            ev.preventDefault();
            var user = $('#gps_login_user');
            if (user) { user.value = emailInput.value.trim(); }
            showTab('login', false);
            var pass = $('#gps_login_pass');
            if (pass) { pass.focus(); }
        });
        wrap.appendChild(a);
        return wrap;
    }

    // Step 1 -> send the code -> step 2
    var nextBtn = $('[data-gps-next]', reg);
    function stepOne() {
        if (nextBtn.disabled) { return; } // a second Enter would email a second code
        var scope = stepEl(1);
        alertIn(scope, '');
        if (!checkAll(scope)) { return; }
        // Back from a failed POST with a verified code for this same email
        if (codeHidden.value && codeHidden.getAttribute('data-email') === emailInput.value.trim().toLowerCase()) {
            goStep(3);
            return;
        }
        codeHidden.value = '';
        busy(nextBtn, true, t.sending);
        sendCode().then(function (res) {
            busy(nextBtn, false);
            var code = res && res.data && res.data.code;
            var msg = res && res.data && res.data.message;
            if (res && res.success) {
                $('[data-gps-sent-to]', reg).textContent = emailInput.value.trim();
                alertIn(stepEl(2), '');
                goStep(2);
                startCooldown(cfg.cooldown || 60);
            } else if (code === 'cooldown') {
                // A code was just sent to this address: let them type it.
                $('[data-gps-sent-to]', reg).textContent = emailInput.value.trim();
                alertIn(stepEl(2), msg, true);
                goStep(2);
                startCooldown(cfg.cooldown || 60);
            } else if (code === 'exists') {
                alertIn(scope, loginInstead(msg));
            } else {
                alertIn(scope, msg || t.error);
            }
        }).catch(function () {
            busy(nextBtn, false);
            alertIn(scope, t.error);
        });
    }
    nextBtn.addEventListener('click', stepOne);

    // Code boxes: one digit each, paste / autofill fills all six
    function fillFrom(index, digits) {
        for (var i = 0; i < digits.length && index + i < boxes.length; i++) {
            boxes[index + i].value = digits.charAt(i);
        }
        var next = Math.min(index + digits.length, boxes.length - 1);
        boxes[next].focus();
    }
    function codeValue() { return boxes.map(function (b) { return b.value; }).join(''); }

    boxes.forEach(function (box, i) {
        box.addEventListener('input', function () {
            var digits = box.value.replace(/\D/g, '');
            if (digits.length > 1) {
                box.value = '';
                fillFrom(i, digits.slice(0, boxes.length - i));
            } else {
                box.value = digits;
                if (digits && i < boxes.length - 1) { boxes[i + 1].focus(); }
            }
            setError(boxes[0], '');
            if (codeValue().length === boxes.length) { verify(); }
        });
        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !box.value && i > 0) { boxes[i - 1].focus(); boxes[i - 1].value = ''; e.preventDefault(); }
            if (e.key === 'ArrowLeft' && i > 0) { boxes[i - 1].focus(); }
            if (e.key === 'ArrowRight' && i < boxes.length - 1) { boxes[i + 1].focus(); }
        });
        box.addEventListener('paste', function (e) {
            var text = (e.clipboardData || window.clipboardData).getData('text') || '';
            var digits = text.replace(/\D/g, '');
            if (!digits) { return; }
            e.preventDefault();
            fillFrom(0, digits.slice(0, boxes.length));
            if (codeValue().length === boxes.length) { verify(); }
        });
        box.addEventListener('focus', function () { box.select(); });
    });

    // Step 2 -> check the code -> step 3
    var verifyBtn = $('[data-gps-verify]', reg);
    var verifying = false;
    function verify() {
        if (verifying) { return; }
        var scope = stepEl(2);
        var code = codeValue();
        alertIn(scope, '');
        if (!/^\d{6}$/.test(code)) {
            setError(boxes[0], t.code);
            boxes[Math.min(code.length, boxes.length - 1)].focus();
            return;
        }
        verifying = true;
        busy(verifyBtn, true, t.checking);
        post('gps_reg_check_code', { email: emailInput.value.trim(), code: code }).then(function (res) {
            verifying = false;
            busy(verifyBtn, false);
            if (res && res.success) {
                codeHidden.value = code;
                codeHidden.setAttribute('data-email', emailInput.value.trim().toLowerCase());
                goStep(3);
                return;
            }
            var reason = res && res.data && res.data.code;
            setError(boxes[0], (res && res.data && res.data.message) || t.error);
            if (reason === 'otp_too_many' || reason === 'otp_expired' || reason === 'otp_missing') {
                boxes.forEach(function (b) { b.value = ''; });
                clearInterval(timer);
                resendBtn.disabled = false;
                resendBtn.textContent = t.resend;
            }
            boxes[0].focus();
        }).catch(function () {
            verifying = false;
            busy(verifyBtn, false);
            alertIn(scope, t.error);
        });
    }
    verifyBtn.addEventListener('click', verify);

    resendBtn.addEventListener('click', function () {
        var scope = stepEl(2);
        resendBtn.disabled = true;
        resendBtn.textContent = t.sending;
        boxes.forEach(function (b) { b.value = ''; });
        setError(boxes[0], '');
        sendCode().then(function (res) {
            var msg = res && res.data && res.data.message;
            alertIn(scope, msg || t.error, !!(res && res.success));
            if (res && (res.success || (res.data && res.data.code === 'cooldown'))) {
                startCooldown(cfg.cooldown || 60);
            } else {
                resendBtn.disabled = false;
                resendBtn.textContent = t.resend;
            }
            boxes[0].focus();
        }).catch(function () {
            alertIn(scope, t.error);
            resendBtn.disabled = false;
            resendBtn.textContent = t.resend;
        });
    });

    $('[data-gps-back]', reg).addEventListener('click', function () {
        boxes.forEach(function (b) { b.value = ''; });
        codeHidden.value = '';
        setError(boxes[0], '');
        goStep(1);
        emailInput.focus();
        emailInput.select();
    });

    // Step 3: "Skip for now" sends the account without the profile
    var skipping = false;
    $('[data-gps-skip]', reg).addEventListener('click', function () {
        skipping = true;
        $$('select, input[type="tel"]', stepEl(3)).forEach(function (el) { el.value = ''; setError(el, ''); });
        $$('.gps-chip input, input[name="gps_marketing"]', stepEl(3)).forEach(function (el) { el.checked = false; });
    });

    // Enter in steps 1-2 moves forward instead of posting a half-filled form
    reg.addEventListener('submit', function (e) {
        if (current === 1) { e.preventDefault(); stepOne(); return; }
        if (current === 2) { e.preventDefault(); verify(); return; }
        if (!codeHidden.value) { e.preventDefault(); goStep(2); return; }
        if (!skipping && !checkAll(stepEl(3))) { e.preventDefault(); return; }
        skipping = false;
        if (reg.getAttribute('data-sending')) { e.preventDefault(); return; }
        reg.setAttribute('data-sending', '1');
        var submit = $('[data-gps-submit]', reg);
        if (submit) { submit.classList.add('is-busy'); submit.textContent = t.creating; }
    });
    // Back/forward cache: allow sending again
    window.addEventListener('pageshow', function () { reg.removeAttribute('data-sending'); });
})();
