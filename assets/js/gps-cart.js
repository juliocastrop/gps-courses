/**
 * GPS cart: accessibility and small touches for the Elementor Pro widgets.
 *
 * Header mini-cart ("Menu Cart"): keeps aria-expanded / aria-label of the
 * toggle in sync, makes the close "X" a real button, returns focus to the
 * toggle on close, adds a panel title and keeps the panel inside the screen.
 *
 * Cart page ("Cart"): - / + buttons around each quantity field and a label
 * for the coupon field. Everything works without this file (plain inputs).
 */
(function () {
    'use strict';

    var t = window.gpsCart || {};
    var txt = function (k, d) { return t[k] || d; };

    /* ============================================================
     * Mini-cart
     * ============================================================ */

    function count(widget) {
        var q = widget.querySelector('.elementor-button-icon-qty[data-counter]');
        return q ? parseInt(q.getAttribute('data-counter'), 10) || 0 : 0;
    }

    function label(widget, toggle) {
        var n = count(widget);
        var items = 1 === n ? txt('item', '1 item') : txt('items', '%d items').replace('%d', n);
        toggle.setAttribute('aria-label', txt('cart', 'Cart') + ', ' + items);
    }

    // Keep the panel inside the viewport: it hangs from the icon's right edge,
    // so when the icon is close to the left side move it right (never past
    // the right margin).
    function place(widget, toggle) {
        var vw = document.documentElement.clientWidth;
        var r = toggle.getBoundingClientRect();
        var gap = vw < 768 ? 12 : 16;
        var width = Math.min(380, vw - gap * 2);
        var shift = Math.max(0, Math.min(gap - (r.right - width), vw - gap - r.right));
        widget.style.setProperty('--gps-mc-shift', Math.round(shift) + 'px');
    }

    // Elementor re-renders parts of the widget on cart fragment refreshes,
    // so elements are looked up every time and decorating is idempotent.
    function decorate(widget) {
        var toggle = widget.querySelector('.elementor-menu-cart__toggle_button');
        var main = widget.querySelector('.elementor-menu-cart__main');
        var close = widget.querySelector('.elementor-menu-cart__close-button');
        if (!toggle || !main) {
            return null;
        }
        if (!main.id) {
            main.id = 'gps-mini-cart-' + Math.random().toString(36).slice(2, 8);
        }
        toggle.setAttribute('aria-controls', main.id);
        toggle.setAttribute('aria-haspopup', 'true');
        toggle.setAttribute('aria-expanded', widget.classList.contains('elementor-menu-cart--shown') ? 'true' : 'false');
        label(widget, toggle);
        main.setAttribute('role', 'region');
        main.setAttribute('aria-label', txt('cart', 'Cart'));

        if (!main.querySelector('.gps-mc-title')) {
            var title = document.createElement('p');
            title.className = 'gps-mc-title';
            title.textContent = txt('title', 'Your cart');
            main.insertBefore(title, main.firstChild);
        }

        if (close && !close.getAttribute('data-gps-cart')) {
            close.setAttribute('data-gps-cart', '1');
            close.setAttribute('role', 'button');
            close.setAttribute('tabindex', '0');
            close.setAttribute('aria-label', txt('close', 'Close cart'));
            close.addEventListener('keydown', function (e) {
                if ('Enter' === e.key || ' ' === e.key) {
                    e.preventDefault();
                    close.click();
                }
            });
        }
        return { toggle: toggle, main: main };
    }

    function setupMiniCart(widget) {
        if (widget.getAttribute('data-gps-cart')) {
            return;
        }
        widget.setAttribute('data-gps-cart', '1');
        if (!decorate(widget)) {
            return;
        }

        var wasOpen = false;
        var onClass = function () {
            var open = widget.classList.contains('elementor-menu-cart--shown');
            var el = decorate(widget);
            if (!el) {
                return;
            }
            if (open && !wasOpen) {
                place(widget, el.toggle);
            }
            if (!open && wasOpen && el.main.contains(document.activeElement)) {
                el.toggle.focus();
            }
            wasOpen = open;
        };

        new MutationObserver(onClass).observe(widget, { attributes: true, attributeFilter: ['class'] });
        new MutationObserver(function () { decorate(widget); }).observe(widget, { childList: true, subtree: true });
        window.addEventListener('resize', function () {
            if (widget.classList.contains('elementor-menu-cart--shown')) {
                var el = decorate(widget);
                if (el) {
                    place(widget, el.toggle);
                }
            }
        });
    }

    /* ============================================================
     * Cart page
     * ============================================================ */

    function fire(input) {
        // jQuery listeners (WooCommerce / Elementor auto-update) need a jQuery event.
        if (window.jQuery) {
            window.jQuery(input).trigger('input').trigger('change');
        } else {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function stepper(input) {
        var wrap = input.parentNode;
        if (!wrap || wrap.classList.contains('gps-qty') || 'hidden' === input.type) {
            return;
        }
        wrap.classList.add('gps-qty');
        var make = function (dir, text, sign) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'gps-qty__btn gps-qty__btn--' + dir;
            b.setAttribute('aria-label', text);
            b.textContent = sign;
            b.addEventListener('click', function () {
                var step = parseFloat(input.step) || 1;
                var min = '' === input.min ? 0 : parseFloat(input.min);
                var max = '' === input.max ? Infinity : parseFloat(input.max);
                var v = (parseFloat(input.value) || 0) + ('more' === dir ? step : -step);
                // Never drop to 0 from the stepper: removing is the "remove" button's job.
                v = Math.min(max, Math.max(Math.max(min, 1), v));
                if (String(v) !== input.value) {
                    input.value = v;
                    fire(input);
                }
            });
            return b;
        };
        wrap.insertBefore(make('less', txt('less', 'Decrease quantity'), '−'), input);
        wrap.appendChild(make('more', txt('more', 'Increase quantity'), '+'));
    }

    function setupCart(root) {
        root.querySelectorAll('.woocommerce-cart-form .quantity input.qty').forEach(stepper);
        var coupon = root.querySelector('#coupon_code');
        if (coupon && !coupon.getAttribute('aria-label') && !root.querySelector('label[for="coupon_code"]')) {
            coupon.setAttribute('aria-label', txt('coupon', 'Coupon code'));
        }
    }

    /* ============================================================
     * Boot (and again after WooCommerce re-renders the cart)
     * ============================================================ */

    // The cart heading sits outside the part WooCommerce re-renders: keep its count current
    function updateHeadCount() {
        var el = document.querySelector('.gps-cart-head__count');
        var qtys = document.querySelectorAll('.woocommerce-cart-form input.qty');
        if (!el || !qtys.length) {
            return;
        }
        var n = 0;
        qtys.forEach(function (q) { n += parseInt(q.value, 10) || 0; });
        el.textContent = 1 === n ? gpsCart.item : gpsCart.items.replace('%d', n);
    }

    function boot() {
        document.querySelectorAll('.elementor-widget-woocommerce-menu-cart').forEach(setupMiniCart);
        document.querySelectorAll('.elementor-widget-woocommerce-cart').forEach(setupCart);
        updateHeadCount();
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    if (window.jQuery) {
        window.jQuery(document.body).on('updated_wc_div updated_cart_totals wc_fragments_refreshed wc_fragments_loaded', boot);
    }
})();
