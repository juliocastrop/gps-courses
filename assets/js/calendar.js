/**
 * GPS Event Calendar JavaScript
 * Professional calendar functionality with AJAX-based event loading
 */
(function($) {
    'use strict';

    class GPSEventCalendar {
        constructor(selector, options = {}) {
            this.$wrapper = $(selector);
            if (!this.$wrapper.length) {
                console.error('GPS Calendar: Wrapper element not found');
                return;
            }

            this.options = $.extend({
                firstDay: 0, // 0 = Sunday, 1 = Monday
                eventsPerDay: 3,
                showEventDetails: true,
                ajaxUrl: '',
                nonce: '',
                eventType: 'all' // 'all', 'courses', or 'seminars'
            }, options);

            this.currentDate = new Date();
            this.selectedDate = null;
            this.events = [];
            this.selectedCategory = 'all';
            this.selectedEventType = this.options.eventType;
            this.eventsCache = {}; // Cache for loaded events
            this.loadingTimeout = null; // For debouncing
            this.currentAjaxRequest = null; // Track current AJAX request

            this.init();
        }

        init() {
            this.cacheElements();
            this.bindEvents();
            this.loadEvents();
        }

        cacheElements() {
            this.$prevBtn = this.$wrapper.find('.gps-prev-month');
            this.$nextBtn = this.$wrapper.find('.gps-next-month');
            this.$monthName = this.$wrapper.find('.gps-month-name');
            this.$yearName = this.$wrapper.find('.gps-year-name');
            this.$monthSelector = this.$wrapper.find('.gps-month-selector');
            this.$yearSelector = this.$wrapper.find('.gps-year-selector');
            this.$grid = this.$wrapper.find('.gps-calendar-grid');
            this.$sidebar = this.$wrapper.find('.gps-calendar-sidebar');
            this.$sidebarContent = this.$wrapper.find('.gps-sidebar-content');
            this.$sidebarTitle = this.$wrapper.find('.gps-sidebar-title');
            this.$selectedDateEl = this.$wrapper.find('.gps-selected-date');
            this.$categorySelect = this.$wrapper.find('.gps-category-select');
            this.$loading = this.$wrapper.find('.gps-calendar-loading');
        }

        bindEvents() {
            // Navigation
            this.$prevBtn.on('click', () => this.previousMonth());
            this.$nextBtn.on('click', () => this.nextMonth());

            // Today button
            this.$wrapper.on('click', '.gps-today-btn', () => this.goToToday());

            // Month/Year dropdowns
            this.$monthSelector.on('change', (e) => {
                const month = parseInt($(e.target).val(), 10);
                this.currentDate.setMonth(month);
                this.selectedDate = null; // Reset to monthly view
                this.loadEvents();
            });

            this.$yearSelector.on('change', (e) => {
                const year = parseInt($(e.target).val(), 10);
                this.currentDate.setFullYear(year);
                this.selectedDate = null; // Reset to monthly view
                this.loadEvents();
            });

            // Category filter
            this.$categorySelect.on('change', (e) => {
                this.selectedCategory = $(e.target).val();
                this.selectedDate = null; // Reset to monthly view
                this.loadEvents();
            });

            // Day click - delegate to handle dynamically added days
            this.$grid.on('click', '.gps-calendar-day', (e) => {
                const $day = $(e.currentTarget);
                if (!$day.hasClass('other-month')) {
                    const dateStr = $day.data('date');
                    if (dateStr) {
                        this.selectDate(this.parseDate(dateStr));
                    }
                }
            });

            // Event click
            this.$grid.on('click', '.gps-calendar-event', (e) => {
                e.stopPropagation();
                const eventId = $(e.currentTarget).data('event-id');
                const event = this.events.find(ev => ev.id === eventId);
                if (event && event.url) {
                    window.location.href = event.url;
                }
            });

            // More events click
            this.$grid.on('click', '.gps-more-events', (e) => {
                e.stopPropagation();
                const $day = $(e.currentTarget).closest('.gps-calendar-day');
                const dateStr = $day.data('date');
                if (dateStr) {
                    this.selectDate(this.parseDate(dateStr));
                }
            });

            // Tooltip functionality
            this.$grid.on('mouseenter', '.gps-calendar-event[data-tooltip]', (e) => {
                this.showTooltip(e);
            });

            this.$grid.on('mouseleave', '.gps-calendar-event[data-tooltip]', () => {
                this.hideTooltip();
            });

            this.$grid.on('mousemove', '.gps-calendar-event[data-tooltip]', (e) => {
                this.updateTooltipPosition(e);
            });
        }

        previousMonth() {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.selectedDate = null; // Reset to monthly view
            this.loadEvents();
        }

        nextMonth() {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.selectedDate = null; // Reset to monthly view
            this.loadEvents();
        }

        goToToday() {
            this.currentDate = new Date();
            this.selectedDate = null; // Reset to monthly view
            this.loadEvents();
        }

        selectDate(date) {
            this.selectedDate = date;

            // Highlight selected day (instant, no render needed)
            this.$grid.find('.gps-calendar-day').removeClass('selected');
            const dateStr = this.formatDate(date);
            this.$grid.find(`.gps-calendar-day[data-date="${dateStr}"]`).addClass('selected');

            // Update sidebar (instant, uses existing events data)
            this.renderSidebar();

            // Scroll sidebar into view on mobile
            if (window.innerWidth <= 768 && this.$sidebar.length) {
                $('html, body').animate({
                    scrollTop: this.$sidebar.offset().top - 20
                }, 300);
            }
        }

        clearDateSelection() {
            this.selectedDate = null;
            this.$grid.find('.gps-calendar-day').removeClass('selected');
            this.renderSidebar();
        }

        parseDate(dateStr) {
            // Parse YYYY-MM-DD format to avoid timezone issues
            const parts = dateStr.split('-');
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1; // Month is 0-indexed
            const day = parseInt(parts[2], 10);
            return new Date(year, month, day);
        }

        loadEvents() {
            // Cancel any pending timeout
            if (this.loadingTimeout) {
                clearTimeout(this.loadingTimeout);
            }

            // Debounce rapid requests
            this.loadingTimeout = setTimeout(() => {
                this._performLoadEvents();
            }, 150);
        }

        _performLoadEvents() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth() + 1;
            const cacheKey = `${year}-${month}-${this.selectedCategory}-${this.selectedEventType}`;

            // Check cache first
            if (this.eventsCache[cacheKey]) {
                this.events = this.eventsCache[cacheKey];
                this.render();
                // Preload adjacent months in background
                this.preloadAdjacentMonths();
                return;
            }

            // Cancel any ongoing request
            if (this.currentAjaxRequest) {
                this.currentAjaxRequest.abort();
            }

            this.showLoading();

            this.currentAjaxRequest = $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gps_get_calendar_events',
                    nonce: this.options.nonce,
                    year: year,
                    month: month,
                    category: this.selectedCategory,
                    event_type: this.selectedEventType
                },
                success: (response) => {
                    if (response.success) {
                        this.events = response.data.events || [];
                        // Store in cache
                        this.eventsCache[cacheKey] = this.events;
                        this.render();
                        // Preload adjacent months in background
                        this.preloadAdjacentMonths();
                    } else {
                        console.error('GPS Calendar: Failed to load events', response);
                        this.events = [];
                        this.render();
                    }
                    this.hideLoading();
                    this.currentAjaxRequest = null;
                },
                error: (xhr, status, error) => {
                    if (status === 'abort') {
                        return; // Request was cancelled, do nothing
                    }
                    console.error('GPS Calendar: AJAX error', error);
                    this.events = [];
                    this.render();
                    this.hideLoading();
                    this.currentAjaxRequest = null;
                }
            });
        }

        preloadAdjacentMonths() {
            // Preload previous and next months silently in the background
            const currentYear = this.currentDate.getFullYear();
            const currentMonth = this.currentDate.getMonth() + 1;

            // Previous month
            const prevDate = new Date(currentYear, currentMonth - 2, 1);
            const prevYear = prevDate.getFullYear();
            const prevMonth = prevDate.getMonth() + 1;
            const prevCacheKey = `${prevYear}-${prevMonth}-${this.selectedCategory}-${this.selectedEventType}`;

            // Next month
            const nextDate = new Date(currentYear, currentMonth, 1);
            const nextYear = nextDate.getFullYear();
            const nextMonth = nextDate.getMonth() + 1;
            const nextCacheKey = `${nextYear}-${nextMonth}-${this.selectedCategory}-${this.selectedEventType}`;

            // Load previous month if not cached
            if (!this.eventsCache[prevCacheKey]) {
                this.loadMonthInBackground(prevYear, prevMonth, prevCacheKey);
            }

            // Load next month if not cached
            if (!this.eventsCache[nextCacheKey]) {
                this.loadMonthInBackground(nextYear, nextMonth, nextCacheKey);
            }
        }

        loadMonthInBackground(year, month, cacheKey) {
            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'gps_get_calendar_events',
                    nonce: this.options.nonce,
                    year: year,
                    month: month,
                    category: this.selectedCategory,
                    event_type: this.selectedEventType
                },
                success: (response) => {
                    if (response.success) {
                        this.eventsCache[cacheKey] = response.data.events || [];
                    }
                }
                // Silent fail for background loads
            });
        }

        render() {
            this.renderHeader();
            this.renderGrid();
            this.renderSidebar();
        }

        renderHeader() {
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            const currentMonth = this.currentDate.getMonth();
            const currentYear = this.currentDate.getFullYear();

            // Update text displays
            this.$monthName.text(monthNames[currentMonth]);
            this.$yearName.text(currentYear);

            // Update dropdown selections
            this.$monthSelector.val(currentMonth);
            this.$yearSelector.val(currentYear);
        }

        renderGrid() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();

            // Get first day of month
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);

            // Calculate starting day (considering first day of week setting)
            let startingDayOfWeek = firstDay.getDay() - this.options.firstDay;
            if (startingDayOfWeek < 0) {
                startingDayOfWeek += 7;
            }

            // Get days from previous month
            const prevMonthLastDay = new Date(year, month, 0).getDate();
            const prevMonthStart = prevMonthLastDay - startingDayOfWeek + 1;

            // Clear existing day cells (keep headers)
            this.$grid.find('.gps-calendar-day').remove();

            // Today's date for comparison
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            let dayCount = 1;
            let nextMonthDay = 1;

            // Render 6 weeks (42 days) to ensure consistent grid
            for (let i = 0; i < 42; i++) {
                let currentDate, isCurrentMonth, isPrevMonth, isNextMonth;

                if (i < startingDayOfWeek) {
                    // Previous month
                    currentDate = new Date(year, month - 1, prevMonthStart + i);
                    isPrevMonth = true;
                    isCurrentMonth = false;
                    isNextMonth = false;
                } else if (dayCount <= lastDay.getDate()) {
                    // Current month
                    currentDate = new Date(year, month, dayCount);
                    isCurrentMonth = true;
                    isPrevMonth = false;
                    isNextMonth = false;
                    dayCount++;
                } else {
                    // Next month
                    currentDate = new Date(year, month + 1, nextMonthDay);
                    isNextMonth = true;
                    isCurrentMonth = false;
                    isPrevMonth = false;
                    nextMonthDay++;
                }

                this.renderDay(currentDate, isCurrentMonth, isPrevMonth, isNextMonth, today);
            }
        }

        renderDay(date, isCurrentMonth, isPrevMonth, isNextMonth, today) {
            const dateStr = this.formatDate(date);
            const dayEvents = this.getEventsForDate(date);
            const isToday = this.isSameDate(date, today);

            let classes = ['gps-calendar-day'];
            if (isToday) classes.push('today');
            if (!isCurrentMonth) classes.push('other-month');
            if (dayEvents.length > 0) classes.push('has-events');
            if (this.selectedDate && this.isSameDate(date, this.selectedDate)) {
                classes.push('selected');
            }

            const $day = $('<div>', {
                class: classes.join(' '),
                'data-date': dateStr
            });

            // Day number
            const $dayNumber = $('<div>', {
                class: 'gps-day-number',
                text: date.getDate()
            });
            $day.append($dayNumber);

            // Events
            if (dayEvents.length > 0) {
                const $eventsContainer = $('<div>', { class: 'gps-day-events' });

                const maxEvents = this.options.eventsPerDay;
                const visibleEvents = dayEvents.slice(0, maxEvents);
                const remainingCount = dayEvents.length - maxEvents;

                visibleEvents.forEach(event => {
                    // Build tooltip content
                    let tooltipContent = event.title;
                    if (event.start_time) {
                        tooltipContent = `${event.start_time} - ${tooltipContent}`;
                    }

                    // Add event type class for styling (course or seminar-session)
                    const eventType = event.type || 'course';
                    const $event = $('<div>', {
                        class: `gps-calendar-event ${eventType}`,
                        'data-event-id': event.id,
                        'data-event-type': eventType,
                        'data-tooltip': tooltipContent
                    });

                    let eventHtml = '';
                    if (event.start_time) {
                        eventHtml += `<span class="gps-event-time">${event.start_time}</span>`;
                    }
                    eventHtml += `<span class="gps-event-title">${this.truncateText(event.title, 25)}</span>`;

                    $event.html(eventHtml);
                    $eventsContainer.append($event);
                });

                if (remainingCount > 0) {
                    const $moreEvents = $('<div>', {
                        class: 'gps-more-events',
                        text: `+${remainingCount} more`
                    });
                    $eventsContainer.append($moreEvents);
                }

                $day.append($eventsContainer);
            }

            this.$grid.append($day);
        }

        renderSidebar() {
            if (!this.options.showEventDetails || !this.$sidebar.length) {
                return;
            }

            // If a specific date is selected, show events for that day
            if (this.selectedDate) {
                // Update title for daily view
                this.$sidebarTitle.text('Events for');
                const dateStr = this.formatDateLong(this.selectedDate);
                this.$selectedDateEl.text(dateStr);

                const dayEvents = this.getEventsForDate(this.selectedDate);

                if (dayEvents.length === 0) {
                    this.$sidebarContent.html('<p class="gps-no-events">No events scheduled for this date.</p>');
                    return;
                }

                let html = '';
                dayEvents.forEach(event => {
                    html += this.renderSidebarEvent(event);
                });

                this.$sidebarContent.html(html);
            } else {
                // No date selected - show all events for the current month
                this.renderMonthlySidebar();
            }
        }

        renderMonthlySidebar() {
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            const currentMonth = this.currentDate.getMonth();
            const currentYear = this.currentDate.getFullYear();

            // Update title for monthly view
            this.$sidebarTitle.text('Upcoming Events');
            this.$selectedDateEl.text(`${monthNames[currentMonth]} ${currentYear}`);

            // Get all events for the current month
            const monthEvents = this.getEventsForMonth(currentMonth, currentYear);

            if (monthEvents.length === 0) {
                this.$sidebarContent.html('<p class="gps-no-events">No events scheduled for this month.</p>');
                return;
            }

            // Sort events by start_date
            monthEvents.sort((a, b) => {
                if (a.start_date === b.start_date) {
                    return (a.start_time || '').localeCompare(b.start_time || '');
                }
                return a.start_date.localeCompare(b.start_date);
            });

            let html = '';
            monthEvents.forEach(event => {
                html += this.renderSidebarEvent(event, true); // true = show date
            });

            this.$sidebarContent.html(html);
        }

        getEventsForMonth(month, year) {
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);

            return this.events.filter(event => {
                const eventStart = new Date(event.start_date);
                const eventEnd = event.end_date ? new Date(event.end_date) : eventStart;

                // Check if event overlaps with the month
                return (eventStart <= lastDay && eventEnd >= firstDay);
            });
        }

        renderSidebarEvent(event, showDate = false) {
            const eventType = event.type || 'course';
            let html = `<div class="gps-sidebar-event ${eventType}">`;

            // Title
            html += `<h4 class="gps-sidebar-event-title">`;
            if (event.url) {
                html += `<a href="${event.url}">${event.title}</a>`;
            } else {
                html += event.title;
            }
            html += `</h4>`;

            // Meta information
            html += '<div class="gps-sidebar-event-meta">';

            // Show date if in monthly view
            if (showDate && event.start_date) {
                const eventDate = new Date(event.start_date + 'T00:00:00');
                const dateStr = this.formatDateShort(eventDate);
                html += `<div class="gps-sidebar-event-meta-item">
                    <i class="dashicons dashicons-calendar-alt"></i>
                    <span>${dateStr}</span>
                </div>`;
            }

            if (event.start_time) {
                html += `<div class="gps-sidebar-event-meta-item">
                    <i class="dashicons dashicons-clock"></i>
                    <span>${event.start_time}${event.end_time ? ' - ' + event.end_time : ''}</span>
                </div>`;
            }

            if (event.location) {
                html += `<div class="gps-sidebar-event-meta-item">
                    <i class="dashicons dashicons-location"></i>
                    <span>${this.escapeHtml(event.location)}</span>
                </div>`;
            }

            if (event.credits) {
                html += `<div class="gps-sidebar-event-meta-item">
                    <i class="dashicons dashicons-awards"></i>
                    <span>${event.credits} CE Credits</span>
                </div>`;
            }

            html += '</div>'; // End meta

            // Link - different text for seminars vs courses
            if (event.url) {
                const linkText = eventType === 'seminar-session' ? 'Register for Seminars' : 'View Details';
                html += `<a href="${event.url}" class="gps-sidebar-event-link">
                    ${linkText}
                    <i class="dashicons dashicons-arrow-right-alt"></i>
                </a>`;
            }

            html += '</div>';

            return html;
        }

        formatDateShort(date) {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            return `${dayNames[date.getDay()]}, ${monthNames[date.getMonth()]} ${date.getDate()}`;
        }

        getEventsForDate(date) {
            const dateStr = this.formatDate(date);
            return this.events.filter(event => {
                // Check if event starts on this date
                if (event.start_date === dateStr) {
                    return true;
                }

                // Check if event spans multiple days and includes this date
                if (event.end_date && event.end_date !== event.start_date) {
                    const eventStart = new Date(event.start_date);
                    const eventEnd = new Date(event.end_date);
                    return date >= eventStart && date <= eventEnd;
                }

                return false;
            });
        }

        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        formatDateLong(date) {
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            return `${monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        }

        isSameDate(date1, date2) {
            return date1.getFullYear() === date2.getFullYear() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getDate() === date2.getDate();
        }

        truncateText(text, maxLength) {
            if (text.length <= maxLength) {
                return text;
            }
            return text.substring(0, maxLength) + '...';
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        showLoading() {
            // Only show loading if request takes more than 200ms
            this.loadingShowTimeout = setTimeout(() => {
                this.$loading.addClass('visible');
            }, 200);
        }

        hideLoading() {
            clearTimeout(this.loadingShowTimeout);
            this.$loading.removeClass('visible');
        }

        showTooltip(e) {
            const tooltipText = $(e.currentTarget).attr('data-tooltip');
            if (!tooltipText) return;

            // Create tooltip if it doesn't exist
            if (!this.$tooltip || !this.$tooltip.length) {
                this.$tooltip = $('<div>', {
                    class: 'gps-calendar-tooltip'
                });
                $('body').append(this.$tooltip);
            }

            this.$tooltip.text(tooltipText).fadeIn(200);
            this.updateTooltipPosition(e);
        }

        hideTooltip() {
            if (this.$tooltip && this.$tooltip.length) {
                this.$tooltip.fadeOut(200);
            }
        }

        updateTooltipPosition(e) {
            if (!this.$tooltip || !this.$tooltip.length || !this.$tooltip.is(':visible')) {
                return;
            }

            const tooltipWidth = this.$tooltip.outerWidth();
            const tooltipHeight = this.$tooltip.outerHeight();
            const offset = 10;

            let left = e.pageX + offset;
            let top = e.pageY + offset;

            // Keep tooltip within viewport
            if (left + tooltipWidth > $(window).width()) {
                left = e.pageX - tooltipWidth - offset;
            }

            if (top + tooltipHeight > $(window).scrollTop() + $(window).height()) {
                top = e.pageY - tooltipHeight - offset;
            }

            this.$tooltip.css({
                left: left + 'px',
                top: top + 'px'
            });
        }
    }

    // Expose to global scope
    window.GPSEventCalendar = GPSEventCalendar;

})(jQuery);
