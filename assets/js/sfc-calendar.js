(function() {
    'use strict';

    class SFCCalendar {
        constructor(wrapper) {
            this.wrapper = wrapper;
            this.currentMonth = parseInt(wrapper.dataset.month);
            this.currentYear = parseInt(wrapper.dataset.year);

            this.init();
        }

        init() {
            // Navigation buttons
            this.wrapper.querySelector('.sfc-prev-month').addEventListener('click', () => {
                this.navigateMonth(-1);
            });

            this.wrapper.querySelector('.sfc-next-month').addEventListener('click', () => {
                this.navigateMonth(1);
            });

            // Refresh button
            const refreshBtn = this.wrapper.querySelector('.sfc-refresh-button');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    this.refresh();
                });
            }

            // Event tooltips and click handlers
            this.initEventHandlers();
        }

        navigateMonth(direction) {
            this.currentMonth += direction;

            if (this.currentMonth > 12) {
                this.currentMonth = 1;
                this.currentYear++;
            } else if (this.currentMonth < 1) {
                this.currentMonth = 12;
                this.currentYear--;
            }

            this.loadMonth();
        }

        loadMonth() {
            const content = this.wrapper.querySelector('.sfc-calendar-content');
            const grid = content.querySelector('.sfc-calendar-grid');
            content.style.opacity = '0.5';

            const formData = new FormData();
            formData.append('action', 'sfc_navigate_month');
            formData.append('nonce', sfc_ajax.nonce);
            formData.append('month', this.currentMonth);
            formData.append('year', this.currentYear);

            fetch(sfc_ajax.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update only the calendar grid content
                    if (grid) {
                        grid.innerHTML = data.data.html;
                    } else {
                        content.innerHTML = data.data.html;
                    }

                    // Update the header
                    this.updateHeader(data.data.month_name, data.data.year);

                    // Update data attributes
                    this.wrapper.dataset.month = this.currentMonth;
                    this.wrapper.dataset.year = this.currentYear;

                    // Reinitialize event handlers
                    this.initEventHandlers();
                } else {
                    console.error('Calendar update failed:', data);
                }
            })
            .catch(error => {
                console.error('Calendar navigation error:', error);
            })
            .finally(() => {
                content.style.opacity = '1';
            });
        }

        updateHeader(monthName, year) {
            const monthElement = this.wrapper.querySelector('.sfc-month-name');
            const yearElement = this.wrapper.querySelector('.sfc-year');

            if (monthElement) monthElement.textContent = monthName;
            if (yearElement) yearElement.textContent = year;
        }

        refresh() {
            const button = this.wrapper.querySelector('.sfc-refresh-button');
            const content = this.wrapper.querySelector('.sfc-calendar-content');
            const grid = content.querySelector('.sfc-calendar-grid');

            button.classList.add('sfc-spinning');
            content.style.opacity = '0.5';

            const formData = new FormData();
            formData.append('action', 'sfc_refresh_calendar');
            formData.append('nonce', sfc_ajax.nonce);
            formData.append('month', this.currentMonth);
            formData.append('year', this.currentYear);

            fetch(sfc_ajax.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update only the calendar grid content
                    if (grid) {
                        grid.innerHTML = data.data.html;
                    } else {
                        content.innerHTML = data.data.html;
                    }

                    // Reinitialize event handlers
                    this.initEventHandlers();
                } else {
                    console.error('Calendar refresh failed:', data);
                }
            })
            .catch(error => {
                console.error('Calendar refresh error:', error);
            })
            .finally(() => {
                button.classList.remove('sfc-spinning');
                content.style.opacity = '1';
            });
        }

        initEventHandlers() {
            // Tooltips
            const events = this.wrapper.querySelectorAll('.sfc-event');

            events.forEach(event => {
                event.addEventListener('mouseenter', (e) => {
                    // Remove any existing tooltips first
                    const existingTooltip = document.querySelector('.sfc-tooltip');
                    if (existingTooltip) {
                        existingTooltip.remove();
                    }

                    const title = e.currentTarget.dataset.eventTitle;
                    const time = e.currentTarget.dataset.eventTime;
                    const location = e.currentTarget.dataset.eventLocation;

                    const tooltip = document.createElement('div');
                    tooltip.className = 'sfc-tooltip';

                    let content = `<strong>${title}</strong>`;
                    if (time && time !== '') {
                        content += `<br>${time}`;
                    }
                    if (location && location !== '') {
                        content += `<br><em>${location}</em>`;
                    }

                    tooltip.innerHTML = content;
                    document.body.appendChild(tooltip);

                    const rect = e.currentTarget.getBoundingClientRect();
                    const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

                    tooltip.style.left = (rect.left + scrollLeft) + 'px';
                    tooltip.style.top = (rect.bottom + scrollTop + 5) + 'px';

                    // Adjust if tooltip goes off screen
                    const tooltipRect = tooltip.getBoundingClientRect();
                    if (tooltipRect.right > window.innerWidth) {
                        tooltip.style.left = (window.innerWidth - tooltipRect.width - 10) + 'px';
                    }

                    e.currentTarget._tooltip = tooltip;
                });

                event.addEventListener('mouseleave', (e) => {
                    if (e.currentTarget._tooltip) {
                        e.currentTarget._tooltip.remove();
                        delete e.currentTarget._tooltip;
                    }
                });
            });

            // "More events" click handlers
            const moreButtons = this.wrapper.querySelectorAll('.sfc-more-events');
            moreButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const dayElement = e.currentTarget.closest('.sfc-day');
                    this.toggleDayEvents(dayElement);
                });
            });
        }

        toggleDayEvents(dayElement) {
            const eventsContainer = dayElement.querySelector('.sfc-day-events');
            const hiddenEvents = eventsContainer.querySelectorAll('.sfc-event-hidden');
            const moreButton = eventsContainer.querySelector('.sfc-more-events');
            const moreText = moreButton.querySelector('.sfc-more-text');
            const lessText = moreButton.querySelector('.sfc-less-text');

            if (dayElement.classList.contains('sfc-expanded')) {
                // Collapse: hide extra events
                hiddenEvents.forEach(event => {
                    event.style.display = 'none';
                });

                // Update button text
                moreText.style.display = 'inline';
                lessText.style.display = 'none';

                // Remove expanded class
                dayElement.classList.remove('sfc-expanded');

                // Remove click outside listener
                if (this.handleClickOutside) {
                    document.removeEventListener('click', this.handleClickOutside);
                }
            } else {
                // Close any other expanded days first
                this.wrapper.querySelectorAll('.sfc-day.sfc-expanded').forEach(day => {
                    if (day !== dayElement) {
                        this.toggleDayEvents(day);
                    }
                });

                // Expand: show all events
                hiddenEvents.forEach(event => {
                    event.style.display = 'block';
                });

                // Update button text
                moreText.style.display = 'none';
                lessText.style.display = 'inline';

                // Add expanded class
                dayElement.classList.add('sfc-expanded');

                // Add click outside listener
                this.handleClickOutside = (e) => {
                    if (!dayElement.contains(e.target)) {
                        this.toggleDayEvents(dayElement);
                    }
                };

                // Delay to prevent immediate closing
                setTimeout(() => {
                    document.addEventListener('click', this.handleClickOutside);
                }, 100);
            }
        }
    }

    // Initialize all calendars on page load
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.sfc-calendar-wrapper').forEach(wrapper => {
            new SFCCalendar(wrapper);
        });
    });

    // Add spinning animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes sfc-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .sfc-spinning .dashicons-update {
            animation: sfc-spin 1s linear infinite;
        }
    `;
    document.head.appendChild(style);
})();
