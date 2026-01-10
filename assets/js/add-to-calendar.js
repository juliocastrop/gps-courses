/**
 * GPS Add to Calendar
 * Generate calendar links for Google, Yahoo, Outlook, Apple Calendar
 */
(function($) {
    'use strict';

    class GPSAddToCalendar {
        constructor(wrapper) {
            this.$wrapper = $(wrapper);
            this.$button = this.$wrapper.find('.gps-calendar-button');
            this.$modal = this.$wrapper.find('.gps-calendar-modal');
            this.$modalClose = this.$wrapper.find('.gps-calendar-modal-close');
            this.$options = this.$wrapper.find('.gps-calendar-option');

            this.eventData = {
                title: this.$button.data('title'),
                description: this.$button.data('description'),
                location: this.$button.data('location'),
                start: this.$button.data('start'),
                end: this.$button.data('end')
            };

            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Open modal
            this.$button.on('click', (e) => {
                e.preventDefault();
                this.openModal();
            });

            // Close modal
            this.$modalClose.on('click', (e) => {
                e.preventDefault();
                this.closeModal();
            });

            // Close on overlay click
            this.$modal.on('click', (e) => {
                if ($(e.target).hasClass('gps-calendar-modal')) {
                    this.closeModal();
                }
            });

            // Close on ESC key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.$modal.is(':visible')) {
                    this.closeModal();
                }
            });

            // Calendar option clicks
            this.$options.on('click', (e) => {
                e.preventDefault();
                const service = $(e.currentTarget).data('service');
                this.addToCalendar(service);
            });
        }

        openModal() {
            this.$modal.fadeIn(300);
            $('body').addClass('gps-modal-open');
        }

        closeModal() {
            this.$modal.fadeOut(300);
            $('body').removeClass('gps-modal-open');
        }

        addToCalendar(service) {
            const url = this.generateCalendarUrl(service);

            if (url) {
                if (service === 'apple') {
                    // Apple Calendar uses .ics file download
                    this.downloadICS();
                } else {
                    // Open calendar service in new window
                    window.open(url, '_blank', 'width=600,height=600');
                }

                this.trackCalendarAdd(service);
                this.closeModal();
            }
        }

        generateCalendarUrl(service) {
            const { title, description, location, start, end } = this.eventData;

            // Convert to required formats
            const startFormatted = this.formatDate(start);
            const endFormatted = this.formatDate(end);

            switch (service) {
                case 'google':
                    return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&dates=${startFormatted}/${endFormatted}&details=${encodeURIComponent(description)}&location=${encodeURIComponent(location)}`;

                case 'yahoo':
                    const yahooStart = this.formatYahooDate(start);
                    const duration = this.calculateDuration(start, end);
                    return `https://calendar.yahoo.com/?v=60&view=d&type=20&title=${encodeURIComponent(title)}&st=${yahooStart}&dur=${duration}&desc=${encodeURIComponent(description)}&in_loc=${encodeURIComponent(location)}`;

                case 'outlook':
                    return `https://outlook.live.com/calendar/0/deeplink/compose?path=/calendar/action/compose&rru=addevent&startdt=${encodeURIComponent(start)}&enddt=${encodeURIComponent(end)}&subject=${encodeURIComponent(title)}&body=${encodeURIComponent(description)}&location=${encodeURIComponent(location)}`;

                case 'outlookcom':
                    return `https://outlook.office.com/calendar/0/deeplink/compose?path=/calendar/action/compose&rru=addevent&startdt=${encodeURIComponent(start)}&enddt=${encodeURIComponent(end)}&subject=${encodeURIComponent(title)}&body=${encodeURIComponent(description)}&location=${encodeURIComponent(location)}`;

                case 'apple':
                    // Apple uses ICS file, return null to trigger download
                    return null;

                default:
                    return null;
            }
        }

        formatDate(datetime) {
            // Convert YYYY-MM-DDTHH:MM:SS to YYYYMMDDTHHMMSSZ
            if (!datetime) return '';
            return datetime.replace(/[-:]/g, '').replace(/\.\d{3}/, '') + 'Z';
        }

        formatYahooDate(datetime) {
            // Convert YYYY-MM-DDTHH:MM:SS to YYYYMMDDTHHMMSS
            if (!datetime) return '';
            return datetime.replace(/[-:]/g, '').replace(/\.\d{3}/, '');
        }

        calculateDuration(start, end) {
            // Calculate duration in HHMM format
            const startTime = new Date(start);
            const endTime = new Date(end);
            const diffMs = endTime - startTime;
            const hours = Math.floor(diffMs / (1000 * 60 * 60));
            const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            return String(hours).padStart(2, '0') + String(minutes).padStart(2, '0');
        }

        downloadICS() {
            const { title, description, location, start, end } = this.eventData;

            // Generate ICS file content
            const icsContent = [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:-//GPS Courses//Event//EN',
                'BEGIN:VEVENT',
                `DTSTART:${this.formatDate(start)}`,
                `DTEND:${this.formatDate(end)}`,
                `SUMMARY:${this.escapeICS(title)}`,
                `DESCRIPTION:${this.escapeICS(description)}`,
                `LOCATION:${this.escapeICS(location)}`,
                'STATUS:CONFIRMED',
                'END:VEVENT',
                'END:VCALENDAR'
            ].join('\r\n');

            // Create blob and download
            const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${this.slugify(title)}.ics`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }

        escapeICS(str) {
            if (!str) return '';
            return str.replace(/\\/g, '\\\\')
                      .replace(/;/g, '\\;')
                      .replace(/,/g, '\\,')
                      .replace(/\n/g, '\\n');
        }

        slugify(text) {
            return text.toString().toLowerCase()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
        }

        trackCalendarAdd(service) {
            // Track calendar add event (can be extended with analytics)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'add_to_calendar', {
                    'calendar_service': service,
                    'content_type': 'course'
                });
            }

            console.log('GPS Calendar: Added to ' + service);
        }
    }

    // Expose to global scope
    window.GPSAddToCalendar = GPSAddToCalendar;

    // Auto-initialize on DOM ready
    $(document).ready(function() {
        $('.gps-add-calendar-wrapper').each(function() {
            new GPSAddToCalendar(this);
        });
    });

})(jQuery);
