/**
 * GPS Courses - AI Search (editor box "Check as AI crawlers", report row
 * checks and "Check all"). Vanilla JS.
 */
(function () {
    'use strict';

    var cfg = window.gpscAis || {};
    var i18n = cfg.i18n || {};

    function check(postId) {
        var data = new FormData();
        data.append('action', 'gpsc_ais_check');
        data.append('nonce', cfg.nonce || '');
        data.append('post_id', postId);
        return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) { throw new Error(json && json.data && json.data.message ? json.data.message : 'error'); }
                return json.data;
            });
    }

    // Editor box (re-bound after its HTML is replaced)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.gpsc-ais__check');
        if (!btn) { return; }
        var box = btn.closest('.gpsc-ais');
        var inner = box.querySelector('.gpsc-ais__inner');
        // The live region stays outside the part that is replaced, so it is announced
        var status = box.querySelector('.gpsc-ais > .gpsc-ais__status');
        btn.disabled = true;
        status.textContent = i18n.checking || 'Checking…';
        check(box.getAttribute('data-id')).then(function (d) {
            inner.innerHTML = d.html;
            status.textContent = (i18n.done || 'Checked.') + ' ' + d.score + '/100';
            var again = inner.querySelector('.gpsc-ais__check');
            if (again) { again.focus(); }
        }).catch(function () {
            btn.disabled = false;
            status.textContent = i18n.error || 'The check failed. Try again.';
        });
    });

    // Report rows
    function updateRow(row, d) {
        var badge = row.querySelector('.gpsc-ais-badge');
        badge.className = 'gpsc-ais-badge gpsc-ais-badge--' + d.tone;
        badge.textContent = d.score;
        row.querySelector('.gpsc-ais__issues').textContent = d.row || '—';
        row.querySelector('.gpsc-ais__when').textContent = d.when;
    }

    function checkRow(row) {
        var btn = row.querySelector('.gpsc-ais__row-check');
        btn.disabled = true;
        btn.textContent = i18n.checking || 'Checking…';
        return check(row.getAttribute('data-id')).then(function (d) {
            updateRow(row, d);
        }).catch(function () {
            row.querySelector('.gpsc-ais__issues').textContent = i18n.error || 'The check failed. Try again.';
        }).then(function () {
            btn.disabled = false;
            btn.textContent = btn.getAttribute('data-label') || 'Check';
        });
    }

    var table = document.querySelector('.gpsc-ais-table');
    if (!table) { return; }
    Array.prototype.forEach.call(table.querySelectorAll('.gpsc-ais__row-check'), function (b) {
        b.setAttribute('data-label', b.textContent);
        b.addEventListener('click', function () { checkRow(b.closest('tr')); });
    });

    var all = document.querySelector('.gpsc-ais__check-all');
    var allStatus = document.querySelector('.gpsc-ais-tools .gpsc-ais__status');
    if (all) {
        all.addEventListener('click', function () {
            var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-id]'));
            var n = 0;
            all.disabled = true;
            function next() {
                if (n >= rows.length) {
                    all.disabled = false;
                    allStatus.textContent = i18n.allDone || 'All pages checked.';
                    return;
                }
                allStatus.textContent = (i18n.all || 'Checking %1$d of %2$d…').replace('%1$d', n + 1).replace('%2$d', rows.length);
                checkRow(rows[n++]).then(next);
            }
            next();
        });
    }
})();
