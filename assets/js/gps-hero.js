/**
 * GPS Hero Slider: switches between server-rendered slides.
 * No dependencies. Autoplay is driven by the active tab's progress
 * animation (CSS), so pausing freezes the bar and the timer together.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
    var instances = [];

    // Images of slides 2+ wait in data-src/data-srcset/data-sizes until needed
    function loadImage(slide) {
        var img = slide && slide.querySelector('img[data-src]');
        if (!img) {
            return;
        }
        if (img.getAttribute('data-sizes')) {
            img.setAttribute('sizes', img.getAttribute('data-sizes'));
        }
        if (img.getAttribute('data-srcset')) {
            img.setAttribute('srcset', img.getAttribute('data-srcset'));
        }
        img.setAttribute('src', img.getAttribute('data-src'));
        img.removeAttribute('data-src');
        img.removeAttribute('data-srcset');
        img.removeAttribute('data-sizes');
    }

    function Hero(root) {
        this.root = root;
        this.slides = Array.prototype.slice.call(root.querySelectorAll('.gps-hero__slide'));
        this.tabs = Array.prototype.slice.call(root.querySelectorAll('.gps-hero__tab'));
        this.live = root.querySelector('.gps-hero__slides');
        this.countNow = root.querySelector('.gps-hero__count-now');
        this.toggle = root.querySelector('.gps-hero__ctl--toggle');
        this.index = 0;
        this.seconds = parseInt(root.getAttribute('data-autoplay'), 10) || 0;
        this.autoplay = this.seconds > 0 && this.slides.length > 1;
        this.userPaused = !!(reduceMotion && reduceMotion.matches); // reduced motion: off until the visitor presses play
        this.hover = false;
        this.focus = false;
        this.pointer = false;
        this.timer = null;
        this.remaining = this.seconds * 1000;
        this.startedAt = 0;
        instances.push(this);
        this.init();
    }

    Hero.prototype.init = function () {
        var self = this;
        var root = this.root;

        this.nav = root.querySelector('.gps-hero__nav');
        if (this.slides.length < 2) {
            root.classList.add('is-ready');
            return;
        }

        if (this.autoplay) {
            root.classList.add('is-autoplay');
            root.style.setProperty('--gps-hero-dur', this.seconds + 's');
        } else if (this.toggle) {
            this.toggle.hidden = true;
        }

        this.tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () {
                self.go(i);
            });
            var fill = tab.querySelector('.gps-hero__fill');
            if (fill) {
                fill.addEventListener('animationend', function () {
                    if (i === self.index) {
                        self.go(self.index + 1);
                    }
                });
            }
        });

        var prev = root.querySelector('.gps-hero__ctl--prev');
        var next = root.querySelector('.gps-hero__ctl--next');
        if (prev) {
            prev.addEventListener('click', function () { self.go(self.index - 1); });
        }
        if (next) {
            next.addEventListener('click', function () { self.go(self.index + 1); });
        }
        if (this.toggle) {
            this.toggle.addEventListener('click', function () {
                self.userPaused = !self.userPaused;
                self.update();
            });
        }

        // Pause on mouse hover and while focus is inside
        root.addEventListener('pointerenter', function (e) {
            if (e.pointerType === 'mouse') {
                self.hover = true;
                self.update();
            }
        });
        root.addEventListener('pointerleave', function (e) {
            if (e.pointerType === 'mouse') {
                self.hover = false;
                self.update();
            }
        });
        // Focus from a click/tap (buttons get focus in Chrome) must not pause for good
        root.addEventListener('pointerdown', function () {
            self.pointer = true;
        });
        root.addEventListener('pointerup', function () {
            setTimeout(function () { self.pointer = false; }, 0);
        });
        root.addEventListener('focusin', function (e) {
            var keyboard;
            try {
                keyboard = e.target.matches(':focus-visible');
            } catch (err) {
                keyboard = !self.pointer; // browsers without :focus-visible
            }
            if (!keyboard) {
                return;
            }
            self.focus = true;
            self.update();
        });
        root.addEventListener('focusout', function (e) {
            if (!e.relatedTarget || !root.contains(e.relatedTarget)) {
                self.focus = false;
                self.update();
            }
        });

        // Arrow keys only while focus is inside the carousel
        root.addEventListener('keydown', function (e) {
            self.pointer = false;
            if (e.altKey || e.ctrlKey || e.metaKey) {
                return;
            }
            var tag = e.target && e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                return;
            }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                e.preventDefault();
                var onTab = document.activeElement && document.activeElement.classList.contains('gps-hero__tab');
                self.go(self.index + (e.key === 'ArrowRight' ? 1 : -1));
                if (onTab && self.tabs[self.index]) {
                    self.tabs[self.index].focus();
                }
            }
        });

        // Swipe on the slides (phones)
        var startX = null;
        var startY = 0;
        this.live.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 1) {
                startX = null;
                return;
            }
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });
        this.live.addEventListener('touchend', function (e) {
            if (startX === null || !e.changedTouches.length) {
                return;
            }
            var dx = e.changedTouches[0].clientX - startX;
            var dy = e.changedTouches[0].clientY - startY;
            startX = null;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) {
                self.go(self.index + (dx < 0 ? 1 : -1));
            }
        }, { passive: true });

        if (reduceMotion && reduceMotion.addEventListener) {
            reduceMotion.addEventListener('change', function (e) {
                if (e.matches) {
                    self.userPaused = true;
                    self.update();
                }
            });
        }

        root.classList.add('is-ready');
        if (this.nav) {
            this.nav.style.visibility = '';
        }
        this.update();

        // Fetch slide 2's image once the page has loaded
        var preload = function () { loadImage(self.slides[1]); };
        if (document.readyState === 'complete') {
            setTimeout(preload, 1500);
        } else {
            window.addEventListener('load', function () { setTimeout(preload, 1500); });
        }
    };

    Hero.prototype.go = function (i) {
        var n = this.slides.length;
        i = ((i % n) + n) % n;
        if (i === this.index) {
            return;
        }
        var prev = this.slides[this.index];
        var next = this.slides[i];
        // Focus inside the slide that is about to become inert moves to its tab
        var hadFocus = prev.contains(document.activeElement);
        loadImage(next);

        prev.classList.remove('is-active');
        prev.setAttribute('aria-hidden', 'true');
        prev.inert = true;
        prev.setAttribute('inert', '');

        next.classList.add('is-active');
        next.removeAttribute('aria-hidden');
        next.inert = false;
        next.removeAttribute('inert');

        var oldTab = this.tabs[this.index];
        var newTab = this.tabs[i];
        if (oldTab) {
            oldTab.classList.remove('is-active');
            oldTab.removeAttribute('aria-current');
        }
        if (newTab) {
            newTab.classList.add('is-active');
            newTab.setAttribute('aria-current', 'true');
            this.restartFill(newTab);
        }
        if (this.countNow) {
            this.countNow.textContent = String(i + 1);
        }
        this.index = i;
        if (hadFocus && newTab) {
            newTab.focus();
        }

        // Preload the one after so autoplay never shows an empty image
        loadImage(this.slides[(i + 1) % n]);

        // New slide, full interval for the fallback timer
        this.stopTimer();
        this.remaining = this.seconds * 1000;
        this.update();
    };

    /*
     * Fallback timer: normally the progress animation's animationend moves the
     * slide. If the animation never runs (e.g. an optimizer stripped the CSS),
     * this advances a little after the interval. go() resets it, so the two
     * never advance twice; it only runs while playing, like the animation.
     */
    Hero.prototype.startTimer = function () {
        if (this.timer || !this.autoplay) {
            return;
        }
        var self = this;
        var index = this.index;
        this.startedAt = Date.now();
        this.timer = setTimeout(function () {
            self.timer = null;
            self.remaining = self.seconds * 1000;
            if (self.index === index && self.root.isConnected) {
                self.go(index + 1);
            }
        }, Math.max(0, this.remaining) + 400);
    };

    Hero.prototype.stopTimer = function () {
        if (!this.timer) {
            return;
        }
        clearTimeout(this.timer);
        this.timer = null;
        this.remaining -= Date.now() - this.startedAt;
    };

    Hero.prototype.restartFill = function (tab) {
        var fill = tab.querySelector('.gps-hero__fill');
        if (!fill) {
            return;
        }
        fill.style.animation = 'none';
        void fill.offsetWidth; // reflow so the animation starts over
        fill.style.animation = '';
    };

    Hero.prototype.update = function () {
        var playing = this.autoplay && !this.userPaused && !this.hover && !this.focus && !document.hidden;
        this.root.classList.toggle('is-playing', playing);
        this.root.classList.toggle('is-paused', this.autoplay && this.userPaused);
        // Announce slide changes only when they are not automatic
        this.live.setAttribute('aria-live', playing ? 'off' : 'polite');
        if (playing) {
            this.startTimer();
        } else {
            this.stopTimer();
        }
        if (this.toggle) {
            var label = this.toggle.getAttribute(this.userPaused ? 'data-label-play' : 'data-label-pause');
            if (label) {
                this.toggle.setAttribute('aria-label', label);
            }
        }
    };

    // One listener for every carousel; drop the ones the editor removed
    document.addEventListener('visibilitychange', function () {
        instances = instances.filter(function (hero) {
            return hero.root.isConnected;
        });
        instances.forEach(function (hero) {
            hero.update();
        });
    });

    function initAll(scope) {
        var base = scope && scope.querySelectorAll ? scope : document;
        var roots = base.querySelectorAll('.gps-hero');
        Array.prototype.forEach.call(roots, function (root) {
            if (!root.gpsHero) {
                root.gpsHero = new Hero(root);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(document); });
    } else {
        initAll(document);
    }

    // Elementor editor/preview: the widget is re-rendered on every change
    function elementorHooks() {
        if (!window.elementorFrontend || !window.elementorFrontend.hooks || elementorHooks.done) {
            return;
        }
        elementorHooks.done = true;
        window.elementorFrontend.hooks.addAction('frontend/element_ready/gps-hero-slider.default', function ($scope) {
            initAll($scope && $scope[0] ? $scope[0] : document);
        });
    }
    elementorHooks();
    if (window.jQuery) {
        window.jQuery(window).on('elementor/frontend/init', elementorHooks);
    }
})();
