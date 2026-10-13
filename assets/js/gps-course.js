/**
 * GPS Courses - single course page (templates/single-gps_event.php).
 *
 * Sticky "On this page" nav, agenda day tabs, countdown, quantity stepper,
 * sold-out waitlist, share / calendar menus, overview "Read more", gallery
 * lightbox and the phone register bar. Vanilla, loaded with defer; the page
 * works without it (forms post, menus are <details>).
 */
(function () {
    'use strict';

    var root = document.querySelector('.gps-cp');
    if (!root) { return; }

    var cfg = window.gpsCourse || {};
    var i18n = cfg.i18n || {};
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    /* ---------- Hero under the transparent site header ---------- */

    // CSS assumes 100/85/65px; use the header's real height when it sits in the flow
    function fitHeader() {
        var h = document.querySelector('[data-elementor-type="header"], .elementor-location-header');
        if (!h) { return; }
        var pos = getComputedStyle(h).position;
        if (pos === 'static' || pos === 'relative') {
            root.style.setProperty('--gps-cp-header-h', Math.round(h.getBoundingClientRect().height) + 'px');
        }
    }
    fitHeader();
    window.addEventListener('resize', fitHeader);

    /* ---------- Sticky offsets (fixed / sticky site header) ---------- */

    var nav = root.querySelector('.gps-cp-nav');

    function headerOffset() {
        var h = document.querySelector('.elementor-location-header, header.site-header, #masthead');
        if (!h) { return 0; }
        var el = null;
        // The sticky element may be the header itself or one of its sections
        var candidates = [h].concat($all('.elementor-sticky, .elementor-sticky--active, [data-settings*="sticky"]', h));
        for (var i = 0; i < candidates.length; i++) {
            var pos = getComputedStyle(candidates[i]).position;
            if (pos === 'fixed' || pos === 'sticky') { el = candidates[i]; break; }
        }
        if (!el) { return 0; }
        var rect = el.getBoundingClientRect();
        return rect.bottom > 0 && rect.top <= 1 ? Math.round(rect.bottom) : 0;
    }

    var book = document.getElementById('registration');

    function setOffsets() {
        var top = headerOffset();
        root.style.setProperty('--gps-cp-top', top + 'px');
        if (nav) { root.style.setProperty('--gps-cp-nav-h', nav.offsetHeight + 'px'); }
        // A card taller than the screen can't stick (its end would be unreachable)
        if (book) {
            book.classList.toggle('is-tall', book.offsetHeight + top + (nav ? nav.offsetHeight : 0) + 40 > window.innerHeight);
        }
    }
    setOffsets();
    window.addEventListener('resize', setOffsets, { passive: true });
    window.addEventListener('scroll', function () {
        // Elementor sticky headers switch on scroll: re-measure, cheaply
        if (!setOffsets.t) {
            setOffsets.t = window.setTimeout(function () { setOffsets.t = 0; setOffsets(); }, 150);
        }
    }, { passive: true });

    /* ---------- "On this page": active section ---------- */

    if (nav) {
        var links = $all('.gps-cp-nav__link', nav);
        var sections = links.map(function (a) { return document.getElementById(a.getAttribute('href').slice(1)); });
        var active = -1;

        function setActive(n) {
            if (n === active) { return; }
            active = n;
            links.forEach(function (a, i) {
                if (i !== n) { a.removeAttribute('aria-current'); return; }
                a.setAttribute('aria-current', 'location');
                // keep the active link in view on phones (horizontal scroll)
                var box = a.parentNode;
                if (box.scrollWidth > box.clientWidth) {
                    if (a.offsetLeft < box.scrollLeft || a.offsetLeft + a.offsetWidth > box.scrollLeft + box.clientWidth) {
                        box.scrollTo({ left: a.offsetLeft - 16, behavior: reduceMotion ? 'auto' : 'smooth' });
                    }
                }
            });
        }

        // The active section is the last one whose top has passed a line a
        // third of the way down the screen (the first one before that).
        function onScroll() {
            onScroll.queued = false;
            var line = nav.getBoundingClientRect().bottom + window.innerHeight * 0.3;
            var n = 0;
            sections.forEach(function (s, i) {
                if (s && s.getBoundingClientRect().top <= line) { n = i; }
            });
            // Bottom of the page: the last section, even when it is short
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
                var last = sections.length - 1;
                if (sections[last] && sections[last].getBoundingClientRect().top < window.innerHeight) { n = last; }
            }
            setActive(n);
        }
        window.addEventListener('scroll', function () {
            if (!onScroll.queued) { onScroll.queued = true; window.requestAnimationFrame(onScroll); }
        }, { passive: true });
        onScroll();
    }

    /* ---------- Agenda day tabs ---------- */

    $all('.gps-cp-tabs[role="tablist"]').forEach(function (list) {
        var tabs = $all('[role="tab"]', list);
        function select(tab, focus) {
            tabs.forEach(function (t) {
                var on = t === tab;
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                var panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) { panel.hidden = !on; }
            });
            if (focus) { tab.focus(); }
        }
        // Without JS (or while FlyingPress delays it) every day is listed with its
        // own label; with JS the labels give way to the tabs.
        list.hidden = false;
        tabs.forEach(function (t) {
            var panel = document.getElementById(t.getAttribute('aria-controls'));
            var label = panel && panel.querySelector('.gps-cp-day__label');
            if (label) { label.className = 'gps-sr'; }
        });
        select(tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0] || tabs[0], false);
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { select(tab, false); });
            tab.addEventListener('keydown', function (e) {
                var next = null;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { next = tabs[(i + 1) % tabs.length]; }
                if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { next = tabs[(i - 1 + tabs.length) % tabs.length]; }
                if (e.key === 'Home') { next = tabs[0]; }
                if (e.key === 'End') { next = tabs[tabs.length - 1]; }
                if (next) { e.preventDefault(); select(next, true); }
            });
        });
    });

    /* ---------- Countdown (days / hours / minutes) ---------- */

    $all('.gps-cp-countdown[data-target]').forEach(function (box) {
        var target = Date.parse(box.getAttribute('data-target'));
        if (isNaN(target)) { return; }
        var cells = {};
        $all('[data-unit]', box).forEach(function (el) { cells[el.getAttribute('data-unit')] = el; });
        function tick() {
            var left = Math.max(0, Math.floor((target - Date.now()) / 1000));
            if (!left) {
                box.hidden = true;
                var line = box.parentNode.querySelector('.gps-cp-countdown__line');
                if (line) { line.hidden = true; }
                return false;
            }
            var v = { days: Math.floor(left / 86400), hours: Math.floor(left % 86400 / 3600), minutes: Math.floor(left % 3600 / 60) };
            Object.keys(cells).forEach(function (k) {
                if (cells[k].textContent !== String(v[k])) { cells[k].textContent = v[k]; }
            });
            return true;
        }
        if (tick()) {
            var timer = window.setInterval(function () { if (!tick()) { window.clearInterval(timer); } }, 30000);
        }
    });

    /* ---------- Quantity stepper + ticket choice ---------- */

    $all('.gps-cp-buy').forEach(function (form) {
        var input = form.querySelector('.gps-cp-qty__input');
        var btns = $all('.gps-cp-qty__btn', form);

        function clamp() {
            if (!input) { return; }
            var min = parseInt(input.min, 10) || 1;
            var max = parseInt(input.max, 10) || 10;
            var v = parseInt(input.value, 10);
            v = isNaN(v) ? min : Math.min(max, Math.max(min, v));
            input.value = v;
            btns.forEach(function (b) {
                var step = parseInt(b.getAttribute('data-step'), 10);
                b.disabled = (step < 0 && v <= min) || (step > 0 && v >= max);
            });
        }
        btns.forEach(function (b) {
            b.addEventListener('click', function () {
                input.value = (parseInt(input.value, 10) || 1) + parseInt(b.getAttribute('data-step'), 10);
                clamp();
            });
        });
        if (input) { input.addEventListener('change', clamp); }
        $all('input[type="radio"][name="add-to-cart"]', form).forEach(function (radio) {
            radio.addEventListener('change', function () {
                var ticket = radio.closest('.gps-cp-ticket');
                if (ticket && input) { input.max = ticket.getAttribute('data-max') || 10; clamp(); }
            });
        });
        clamp();

        form.addEventListener('submit', function () {
            var submit = form.querySelector('.gps-cp-buy__submit');
            if (submit) {
                submit.dataset.label = submit.textContent;
                submit.textContent = i18n.adding || 'Adding…';
                window.setTimeout(function () { submit.disabled = true; }, 0);
                // Back/forward cache: restore the button
                window.addEventListener('pageshow', function () { submit.disabled = false; submit.textContent = submit.dataset.label; }, { once: true });
            }
        });
    });

    /* ---------- Sold-out waitlist (gps_join_waitlist, as the Ticket Selector) ---------- */

    function post(data) {
        return fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        }).then(function (r) { return r.json(); });
    }

    $all('.gps-cp-waitlist').forEach(function (form) {
        var btn = form.querySelector('.gps-waitlist-submit');
        var box = form.querySelector('.gps-waitlist-response');
        function show(ok, msg) {
            box.textContent = msg;
            box.classList.remove('success', 'error');
            box.classList.add(ok ? 'success' : 'error');
            box.hidden = false;
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var email = form.querySelector('[name="email"]');
            if (!email.value || !email.checkValidity()) {
                email.setAttribute('aria-invalid', 'true');
                email.focus();
                show(false, (window.gpsInterest && gpsInterest.i18n && gpsInterest.i18n.email) || 'Please enter a valid email address.');
                return;
            }
            email.removeAttribute('aria-invalid');
            btn.disabled = true;
            btn.textContent = i18n.joining || 'Joining…';
            var getNonce = typeof window.gpsFreshNonce === 'function'
                ? window.gpsFreshNonce('gps_waitlist', cfg.ajaxUrl)
                : Promise.resolve(null);
            getNonce.then(function (nonce) {
                var data = new FormData();
                data.append('action', 'gps_join_waitlist');
                data.append('nonce', nonce || form.getAttribute('data-nonce'));
                data.append('ticket_id', form.getAttribute('data-ticket-id'));
                data.append('event_id', form.getAttribute('data-event-id'));
                ['first_name', 'last_name', 'email', 'phone'].forEach(function (n) {
                    var f = form.querySelector('[name="' + n + '"]');
                    data.append(n, f ? f.value : '');
                });
                return post(data);
            }).then(function (json) {
                var msg = json && json.data && json.data.message ? json.data.message : (i18n.error || 'Something went wrong. Please try again.');
                show(!!(json && json.success), msg);
                if (json && json.success) {
                    btn.textContent = i18n.joined || 'Joined!';
                    $all('.gps-waitlist-fields input', form).forEach(function (f) { f.disabled = true; });
                } else {
                    btn.disabled = false;
                    btn.textContent = i18n.join || 'Join the waitlist';
                }
            }).catch(function () {
                show(false, i18n.error || 'Something went wrong. Please try again.');
                btn.disabled = false;
                btn.textContent = i18n.join || 'Join the waitlist';
            });
        });
    });

    /* ---------- Menus (Add to calendar / Share) ---------- */

    var menus = $all('.gps-cp-menu');
    menus.forEach(function (menu) {
        menu.addEventListener('toggle', function () {
            if (menu.open) {
                menus.forEach(function (m) { if (m !== menu) { m.open = false; } });
            }
        });
    });
    document.addEventListener('click', function (e) {
        menus.forEach(function (m) { if (m.open && !m.contains(e.target)) { m.open = false; } });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        menus.forEach(function (m) {
            if (m.open) { m.open = false; m.querySelector('summary').focus(); }
        });
    });

    // Native share sheet where available (phones); the menu stays as fallback
    $all('.gps-cp-share').forEach(function (menu) {
        var summary = menu.querySelector('summary');
        // Phones only: desktop browsers with Web Share keep the full menu
        var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
        if (!navigator.share || !summary || !coarse) { return; }
        summary.addEventListener('click', function (e) {
            e.preventDefault();
            navigator.share({ title: menu.getAttribute('data-title'), url: menu.getAttribute('data-url') }).catch(function () {});
        });
    });

    $all('.gps-cp-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url');
            var done = function () {
                var label = btn.textContent;
                btn.textContent = i18n.copied || 'Link copied';
                window.setTimeout(function () { btn.textContent = label; }, 2000);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); });
            } else {
                window.prompt('', url);
            }
        });
    });

    /* ---------- Overview: "Read more" on phones ---------- */

    var clampBox = root.querySelector('.gps-cp-clamp');
    if (clampBox && window.matchMedia('(max-width: 767px)').matches) {
        clampBox.classList.add('is-clamped');
        if (clampBox.scrollHeight > clampBox.clientHeight + 8) {
            var more = document.createElement('button');
            more.type = 'button';
            more.className = 'gps-cp-more';
            more.setAttribute('aria-expanded', 'false');
            more.setAttribute('aria-controls', clampBox.id);
            more.textContent = i18n.more || 'Read more';
            clampBox.parentNode.insertBefore(more, clampBox.nextSibling);
            more.addEventListener('click', function () {
                var open = clampBox.classList.toggle('is-clamped') === false;
                more.setAttribute('aria-expanded', open ? 'true' : 'false');
                more.textContent = open ? (i18n.less || 'Show less') : (i18n.more || 'Read more');
            });
        } else {
            clampBox.classList.remove('is-clamped');
        }
    }

    /* ---------- Gallery lightbox ---------- */

    var gLinks = $all('.gps-cp-gallery__link');
    if (gLinks.length && typeof HTMLDialogElement === 'function') {
        var dlg = document.createElement('dialog');
        dlg.className = 'gps-cp-lightbox';
        dlg.innerHTML = '<img alt="">' +
            '<button type="button" class="gps-cp-lightbox__btn gps-cp-lightbox__close"></button>' +
            (gLinks.length > 1 ? '<button type="button" class="gps-cp-lightbox__btn gps-cp-lightbox__prev">‹</button><button type="button" class="gps-cp-lightbox__btn gps-cp-lightbox__next">›</button>' : '');
        document.body.appendChild(dlg);
        var img = dlg.querySelector('img');
        var close = dlg.querySelector('.gps-cp-lightbox__close');
        close.textContent = '×';
        close.setAttribute('aria-label', i18n.close || 'Close');
        var current = 0;
        function open(n) {
            current = (n + gLinks.length) % gLinks.length;
            var a = gLinks[current];
            var thumb = a.querySelector('img');
            img.src = a.getAttribute('href');
            img.alt = thumb ? thumb.alt : '';
            if (!dlg.open) { dlg.showModal(); }
        }
        gLinks.forEach(function (a, n) {
            a.addEventListener('click', function (e) { e.preventDefault(); open(n); });
        });
        close.addEventListener('click', function () { dlg.close(); });
        var prev = dlg.querySelector('.gps-cp-lightbox__prev');
        var next = dlg.querySelector('.gps-cp-lightbox__next');
        if (prev) {
            prev.setAttribute('aria-label', i18n.prev || 'Previous image');
            next.setAttribute('aria-label', i18n.next || 'Next image');
            prev.addEventListener('click', function () { open(current - 1); });
            next.addEventListener('click', function () { open(current + 1); });
            dlg.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') { open(current - 1); }
                if (e.key === 'ArrowRight') { open(current + 1); }
            });
        }
        dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
        dlg.addEventListener('close', function () {
            var a = gLinks[current];
            if (a) { a.focus(); }
        });
    }

    /* ---------- Phone register bar ---------- */

    var bar = root.querySelector('.gps-cp-bar');
    var card = book;
    if (bar && card && 'IntersectionObserver' in window) {
        bar.classList.add('is-off');
        bar.hidden = false;
        var band = root.querySelector('.gps-cp-band');
        var cardVisible = true, bandVisible = false, cardAbove = false;
        function update() {
            var show = !cardVisible && cardAbove && !bandVisible;
            bar.classList.toggle('is-off', !show);
            bar.setAttribute('aria-hidden', show ? 'false' : 'true');
            $all('a', bar).forEach(function (a) { a.tabIndex = show ? 0 : -1; });
        }
        new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.target === card) {
                    cardVisible = e.isIntersecting;
                    cardAbove = e.boundingClientRect.bottom < 0;
                } else {
                    bandVisible = e.isIntersecting;
                }
            });
            update();
        }).observe(card);
        if (band) {
            new IntersectionObserver(function (entries) {
                bandVisible = entries[0].isIntersecting;
                update();
            }).observe(band);
        }
        update();
    }
})();
