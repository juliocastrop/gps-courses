/**
 * GPS Event Calendar (Elementor widget "GPS Event Calendar").
 *
 * The month, its agenda and the upcoming events are rendered in PHP
 * (GPSC\Event_Calendar), so the calendar is complete without JavaScript and
 * every control is a plain link / GET form. This script only enhances it:
 * month navigation, filters and the month/year selects swap in the markup of
 * the gps_get_calendar_events AJAX action (same PHP renderer, no nonce),
 * day numbers and "+N more" become buttons that list a day's events in the
 * day panel, and extra events per day collapse behind "+N more".
 *
 * Robust against late loading (FlyingPress "delay JS"): no jQuery, initialises
 * on DOMContentLoaded or immediately, and on Elementor's element_ready hook in
 * the editor. A page served stale from the page cache (another day, or older
 * than 30 minutes) is refreshed quietly, unless the visitor already interacted.
 * If a request fails the current month stays and a retry message is shown.
 */
(function () {
    'use strict';

    var SEL = '[data-gpsc-cal]';
    var PARAMS = ['gpsc_cal', 'gpsc_cal_y', 'gpsc_cal_m', 'gpsc_cal_type', 'gpsc_cal_cat'];
    var STALE_SECONDS = 30 * 60;

    if (!window.fetch || !window.FormData || !document.querySelector || !Element.prototype.matches) {
        return; // links and the GET form keep working without the enhancement
    }

    function q(root, sel) { return root.querySelector(sel); }
    function qa(root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }
    function closest(el, sel) {
        while (el && el.nodeType === 1) {
            if (el.matches(sel)) { return el; }
            el = el.parentNode;
        }
        return null;
    }
    function fmt(str, value) { return String(str || '').replace('%s', value); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function Calendar(root) {
        var self = this;
        var d = root.dataset;

        this.root = root;
        this.ajax = d.ajax || '/wp-admin/admin-ajax.php';
        this.month = d.month || '';
        this.explicit = d.explicit === '1';
        this.type = d.type || 'all';
        this.baseType = d.baseType || 'all';
        this.category = d.category || 'all';
        this.upcoming = parseInt(d.upcoming, 10) || 0;
        this.seq = 0;
        this.cache = {};
        this.touched = false;
        this.lastTrigger = null;
        try { this.i18n = JSON.parse(d.i18n || '{}'); } catch (e) { this.i18n = {}; }

        this.main = q(root, '[data-gpsc-main]');
        this.title = q(root, '[data-gpsc-title]');
        this.status = q(root, '[data-gpsc-status]');
        this.live = q(root, '[data-gpsc-live]');
        this.monthSelect = q(root, '[data-gpsc-month-select]');
        this.yearSelect = q(root, '[data-gpsc-year-select]');
        this.catSelect = q(root, '[data-gpsc-cat-select]');
        this.upcomingBox = q(root, '[data-gpsc-upcoming]');
        this.dayPanel = q(root, '[data-gpsc-daypanel]');
        this.dayTitle = q(root, '[data-gpsc-daytitle]');
        this.dayList = q(root, '[data-gpsc-daylist]');

        if (!this.main) {
            return;
        }
        root.classList.add('is-enhanced');
        this.enhance(this.main);

        // Any interaction means a quiet refresh must not replace what the visitor sees
        var touch = function () { self.touched = true; };
        root.addEventListener('focusin', touch);
        root.addEventListener('pointerdown', touch);
        root.addEventListener('keydown', touch);

        root.addEventListener('click', function (e) { self.onClick(e); });
        root.addEventListener('change', function (e) { self.touched = true; self.onChange(e); });
        root.addEventListener('submit', function (e) {
            if (closest(e.target, '[data-gpsc-jump]')) {
                e.preventDefault();
                self.load(self.selectedMonth(), { announce: true });
            }
        });
        root.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.key === 'Esc') && self.dayPanel && !self.dayPanel.hidden) {
                self.closeDay(true);
            }
        });

        // Selects changed before this script ran (FlyingPress delay): show what was chosen
        var chosen = this.selectedMonth();
        var chosenCat = this.catSelect ? (this.catSelect.value || 'all') : this.category;
        if (chosen !== this.month || chosenCat !== this.category) {
            this.touched = true;
            this.load(chosen, { upcoming: chosenCat !== this.category, category: chosenCat, announce: true });
            return;
        }

        if (this.isStale()) {
            var later = window.requestIdleCallback || function (fn) { return setTimeout(fn, 1); };
            later(function () {
                if (!self.touched) {
                    self.load(self.explicit ? self.month : '', { upcoming: true, silent: true });
                }
            });
        }
    }

    /** Page rendered on another site day, or more than 30 minutes ago (page cache). */
    Calendar.prototype.isStale = function () {
        var d = this.root.dataset;
        var rendered = parseInt(d.rendered, 10) || 0;
        var offset = parseInt(d.tzOffset, 10) || 0;
        var now = Math.floor(Date.now() / 1000);
        var local = new Date((now + offset) * 1000);
        var siteToday = local.getUTCFullYear() + '-' + pad(local.getUTCMonth() + 1) + '-' + pad(local.getUTCDate());
        return !rendered || siteToday !== d.today || now - rendered > STALE_SECONDS;
    };

    /** Day numbers and "+N more" are plain text without JS: make them buttons. */
    Calendar.prototype.enhance = function (scope) {
        qa(scope, 'span[data-gpsc-btn]').forEach(function (span) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = span.className;
            btn.setAttribute('data-gpsc-day', span.getAttribute('data-gpsc-day'));
            btn.setAttribute('aria-label', span.getAttribute('data-label') || '');
            btn.textContent = span.getAttribute('data-num') || span.textContent;
            span.parentNode.replaceChild(btn, span);
        });
    };

    Calendar.prototype.onClick = function (e) {
        var el;
        if ((el = closest(e.target, 'a[data-gpsc-nav]'))) {
            e.preventDefault();
            if (el.getAttribute('aria-disabled') === 'true') { return; }
            var dir = el.getAttribute('data-gpsc-nav');
            if (dir === 'today') {
                this.load('', { announce: true });
            } else if (el.getAttribute('data-month')) {
                this.load(el.getAttribute('data-month'), { announce: true });
            }
            return;
        }
        if ((el = closest(e.target, 'a[data-gpsc-type]'))) {
            e.preventDefault();
            var type = el.getAttribute('data-gpsc-type');
            if (type !== this.type) {
                this.load(this.month, { upcoming: true, type: type, announce: true });
            }
            return;
        }
        if ((el = closest(e.target, 'button[data-gpsc-day]'))) {
            e.preventDefault();
            this.showDay(el.getAttribute('data-gpsc-day'), el);
            return;
        }
        if ((el = closest(e.target, '[data-gpsc-back]'))) {
            e.preventDefault();
            this.closeDay(true);
            return;
        }
        if ((el = closest(e.target, '[data-gpsc-retry]'))) {
            e.preventDefault();
            if (this.retry) { this.retry(); }
        }
    };

    Calendar.prototype.onChange = function (e) {
        var t = e.target;
        if (t === this.monthSelect || t === this.yearSelect) {
            this.load(this.selectedMonth(), { announce: true });
        } else if (t === this.catSelect) {
            this.load(this.month, { upcoming: true, category: t.value || 'all', announce: true });
        }
    };

    Calendar.prototype.selectedMonth = function () {
        if (!this.monthSelect || !this.yearSelect) { return this.month; }
        return this.yearSelect.value + '-' + pad(parseInt(this.monthSelect.value, 10));
    };

    Calendar.prototype.monthName = function (ym) {
        if (!ym) { return this.title ? this.title.textContent : ''; }
        var parts = ym.split('-');
        var opt = this.monthSelect && this.monthSelect.options[parseInt(parts[1], 10) - 1];
        return (opt ? opt.text : parts[1]) + ' ' + parts[0];
    };

    /**
     * @param {string} month Y-m, '' = the current month (site time)
     * @param {object} opts  upcoming (also refresh the upcoming panel), silent, announce, type, category
     */
    Calendar.prototype.load = function (month, opts) {
        var self = this;
        opts = opts || {};
        if (!opts.silent) { this.touched = true; }
        var type = opts.type || this.type;
        var category = opts.category || this.category;
        var withUpcoming = !!(opts.upcoming && this.upcoming);
        var key = [month || 'now', type, category].join('|');
        var seq = ++this.seq;

        var cached = month && this.cache[key];
        if (cached && !withUpcoming) {
            this.apply(cached, type, category, opts);
            return;
        }

        var fd = new FormData();
        fd.append('action', 'gps_get_calendar_events');
        fd.append('ym', month || '');
        fd.append('event_type', type);
        fd.append('base_type', this.baseType);
        fd.append('category', category);
        fd.append('first_day', this.root.dataset.firstDay || '0');
        fd.append('per_day', this.root.dataset.perDay || '3');
        fd.append('upcoming', String(this.upcoming || 5));
        fd.append('with_upcoming', withUpcoming ? '1' : '0');
        fd.append('id', this.root.dataset.id || '');
        var upTitle = this.upcomingBox && q(this.upcomingBox, '.gpsc-cal__paneltitle');
        if (upTitle) { fd.append('upcoming_title', upTitle.textContent); }

        if (!opts.silent) { this.busy(true); }

        fetch(this.ajax, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.json();
        }).then(function (json) {
            var data = json && json.success && json.data;
            if (!data || typeof data.month_html !== 'string') { throw new Error('Bad response'); }
            if (seq !== self.seq) { return; } // a newer request won
            if (opts.silent && self.touched) { return; } // never replace what the visitor is using
            self.cache[[data.month, type, category].join('|')] = data;
            self.apply(data, type, category, opts);
        }).catch(function () {
            if (seq !== self.seq) { return; }
            self.busy(false);
            if (!opts.silent) {
                self.fail(month, opts);
            }
        });
    };

    Calendar.prototype.apply = function (data, type, category, opts) {
        var root = this.root;
        opts = opts || {};
        this.closeDay(false);

        this.month = data.month;
        this.type = type;
        this.category = category;
        root.dataset.month = data.month;
        root.dataset.type = type;
        root.dataset.category = category;
        if (data.today) { root.dataset.today = data.today; }
        if (data.rendered) { root.dataset.rendered = String(data.rendered); }

        this.main.innerHTML = data.month_html;
        this.enhance(this.main);
        if (this.title && this.title.textContent !== data.title) { this.title.textContent = data.title; }
        if (typeof data.upcoming_html === 'string' && this.upcomingBox) {
            this.upcomingBox.innerHTML = data.upcoming_html;
        }

        var parts = data.month.split('-');
        if (this.monthSelect) { this.monthSelect.value = String(parseInt(parts[1], 10)); }
        if (this.yearSelect) { this.yearSelect.value = parts[0]; }
        if (this.catSelect) { this.catSelect.value = category; }

        this.updateNav('prev', data.prev);
        this.updateNav('next', data.next);
        var self = this;
        qa(root, 'a[data-gpsc-type]').forEach(function (a) {
            var on = a.getAttribute('data-gpsc-type') === type;
            a.classList.toggle('is-active', on);
            if (on) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); }
            a.href = self.viewUrl(data.month, a.getAttribute('data-gpsc-type'), category);
        });
        var today = q(root, 'a[data-gpsc-nav="today"]');
        if (today) { today.href = this.viewUrl('', type, category); }

        this.clearStatus();
        if (opts.announce && this.live && data.showing) {
            this.live.textContent = data.showing;
        }
        this.busy(false);
    };

    /** Prev / next stay in place; at the bounds they are disabled (no href, not focusable). */
    Calendar.prototype.updateNav = function (dir, info) {
        var el = q(this.root, 'a[data-gpsc-nav="' + dir + '"]');
        if (!el) { return; }
        var label = (dir === 'prev' ? this.i18n.prev : this.i18n.next) || '';
        if (!info) {
            var hadFocus = document.activeElement === el;
            el.removeAttribute('href');
            el.removeAttribute('data-month');
            el.setAttribute('aria-disabled', 'true');
            el.setAttribute('role', 'link');
            el.setAttribute('tabindex', '-1');
            el.setAttribute('aria-label', label);
            el.classList.add('is-disabled');
            if (hadFocus) {
                var target = q(this.root, 'a[data-gpsc-nav="today"]') ||
                    q(this.root, 'a[data-gpsc-nav="' + (dir === 'prev' ? 'next' : 'prev') + '"]:not([aria-disabled])') ||
                    this.title;
                if (target) { target.focus(); }
            }
            return;
        }
        el.classList.remove('is-disabled');
        el.removeAttribute('aria-disabled');
        el.removeAttribute('role');
        el.removeAttribute('tabindex');
        el.setAttribute('rel', 'nofollow');
        el.setAttribute('data-month', info.month);
        el.setAttribute('aria-label', label + ': ' + info.title);
        el.href = this.viewUrl(info.month, this.type, this.category);
    };

    /** Same URL the server builds for the no-JS links. */
    Calendar.prototype.viewUrl = function (month, type, category) {
        try {
            var u = new URL(window.location.href);
            PARAMS.forEach(function (k) { u.searchParams.delete(k); });
            if (month) { u.searchParams.set('gpsc_cal', month); }
            if (this.baseType === 'all' && type && type !== 'all') { u.searchParams.set('gpsc_cal_type', type); }
            if (this.catSelect && category && category !== 'all') { u.searchParams.set('gpsc_cal_cat', category); }
            u.hash = '';
            return u.toString();
        } catch (e) {
            return '#';
        }
    };

    Calendar.prototype.busy = function (on) {
        this.root.classList.toggle('is-loading', on);
        this.main.setAttribute('aria-busy', on ? 'true' : 'false');
    };

    Calendar.prototype.clearStatus = function () {
        this.retry = null;
        if (this.status) {
            this.status.textContent = '';
            this.status.classList.remove('is-error');
        }
    };

    Calendar.prototype.fail = function (month, opts) {
        var self = this;
        // Put the selects back on the month that is still shown
        var parts = this.month.split('-');
        if (this.monthSelect) { this.monthSelect.value = String(parseInt(parts[1], 10)); }
        if (this.yearSelect) { this.yearSelect.value = parts[0]; }
        if (this.catSelect) { this.catSelect.value = this.category; }
        if (!this.status) { return; }

        this.retry = function () { self.load(month, opts); };
        this.status.textContent = '';
        var msg = document.createElement('span');
        msg.textContent = fmt(this.i18n.error || 'We couldn’t load %s.', this.monthName(month));
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'gpsc-cal__retry';
        btn.setAttribute('data-gpsc-retry', '');
        btn.textContent = this.i18n.retry || 'Try again';
        this.status.appendChild(msg);
        this.status.appendChild(document.createTextNode(' '));
        this.status.appendChild(btn);
        this.status.classList.add('is-error');
    };

    /** List a day's events in the day panel (cards come from the month's agenda). */
    Calendar.prototype.showDay = function (date, trigger) {
        if (!this.dayPanel || !this.dayList) { return; }
        var day = q(this.main, '[data-gpsc-agenda="' + date + '"]');
        var cards = day && q(day, '.gpsc-cal__cards');
        if (!cards) { return; }

        qa(this.main, '.gpsc-cal__cell.is-selected').forEach(function (td) { td.classList.remove('is-selected'); });
        var cell = q(this.main, '.gpsc-cal__cell[data-date="' + date + '"]');
        if (cell) { cell.classList.add('is-selected'); }

        var heading = q(day, '.gpsc-cal__adate time');
        this.dayTitle.textContent = heading ? heading.textContent : date;
        this.dayList.innerHTML = '';
        this.dayList.appendChild(cards.cloneNode(true));
        if (this.upcomingBox) { this.upcomingBox.hidden = true; }
        this.dayPanel.hidden = false;
        this.lastTrigger = trigger || null;
        this.dayTitle.focus({ preventScroll: true });

        var rect = this.dayPanel.getBoundingClientRect();
        if (rect.top < 0 || rect.top > window.innerHeight - 80) {
            this.dayPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    Calendar.prototype.closeDay = function (restoreFocus) {
        if (!this.dayPanel || this.dayPanel.hidden) { return; }
        this.dayPanel.hidden = true;
        if (this.upcomingBox) { this.upcomingBox.hidden = false; }
        qa(this.main, '.gpsc-cal__cell.is-selected').forEach(function (td) { td.classList.remove('is-selected'); });
        if (restoreFocus && this.lastTrigger && document.body.contains(this.lastTrigger)) {
            this.lastTrigger.focus();
        }
        this.lastTrigger = null;
    };

    /* ---------- Boot ---------- */

    function init(el) {
        if (el && !el.gpscCalendar) {
            el.gpscCalendar = new Calendar(el);
        }
        return el ? el.gpscCalendar : null;
    }

    function boot(scope) {
        qa(scope || document, SEL).forEach(init);
    }

    // Backwards compatible entry point (old inline initialisers in cached pages)
    window.GPSEventCalendar = function (selector) {
        var el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (el && el.jquery) { el = el[0]; }
        if (el && !el.matches(SEL)) { el = q(el, SEL); }
        return init(el);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(); });
    } else {
        boot();
    }

    // Elementor editor / preview: widgets are (re)rendered after load
    var hooked = false;
    function hookElementor() {
        var ef = window.elementorFrontend;
        if (hooked || !ef || !ef.hooks) { return; }
        hooked = true;
        ef.hooks.addAction('frontend/element_ready/gps-event-calendar.default', function ($scope) {
            boot($scope && $scope[0] ? $scope[0] : document);
        });
    }
    hookElementor();
    if (!hooked) {
        if (window.jQuery) { window.jQuery(window).on('elementor/frontend/init', hookElementor); }
        window.addEventListener('elementor/frontend/init', hookElementor);
    }
})();
