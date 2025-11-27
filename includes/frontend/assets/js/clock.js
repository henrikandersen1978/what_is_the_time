/**
 * World Time AI - Live Clock JavaScript
 */

(function() {
	'use strict';

	// Update all clocks on page
	function updateClocks() {
		// Main location clocks
		const mainClocks = document.querySelectorAll('.wta-clock[data-timezone]');
		mainClocks.forEach(function(clock) {
			updateMainClock(clock);
		});

		// Widget clocks (shortcode)
		const widgetClocks = document.querySelectorAll('.wta-clock-widget[data-timezone]');
		widgetClocks.forEach(function(clock) {
			updateWidgetClock(clock);
		});
		
		// City time clocks (live grid)
		const cityClocks = document.querySelectorAll('.wta-live-city-clock[data-timezone]');
		cityClocks.forEach(function(clock) {
			updateCityClock(clock);
		});
	}

	// Update main clock (single location page)
	function updateMainClock(clock) {
		const timezone = clock.getAttribute('data-timezone');
		
		try {
			const now = new Date();
			
			// Format time
			const timeFormatter = new Intl.DateTimeFormat('da-DK', {
				timeZone: timezone,
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			});
			const timeString = timeFormatter.format(now);
			
			// Format date
			const dateFormatter = new Intl.DateTimeFormat('da-DK', {
				timeZone: timezone,
				weekday: 'long',
				year: 'numeric',
				month: 'long',
				day: 'numeric'
			});
			const dateString = dateFormatter.format(now);
			
			// Update DOM
			const timeEl = clock.querySelector('.wta-clock-time');
			const dateEl = clock.querySelector('.wta-clock-date');
			
			if (timeEl) timeEl.textContent = timeString;
			if (dateEl) dateEl.textContent = dateString;
			
		} catch (error) {
			console.error('Error updating clock:', error);
			const timeEl = clock.querySelector('.wta-clock-time');
			if (timeEl) timeEl.textContent = 'Error';
		}
	}

	// Update widget clock (shortcode)
	function updateWidgetClock(clock) {
		const timezone = clock.getAttribute('data-timezone');
		const format = clock.getAttribute('data-format') || 'long';
		
		try {
			const now = new Date();
			
			// Format time
			const timeFormatter = new Intl.DateTimeFormat('da-DK', {
				timeZone: timezone,
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			});
			const timeString = timeFormatter.format(now);
			
			// Format date based on format option
			let dateFormatter;
			if (format === 'short') {
				dateFormatter = new Intl.DateTimeFormat('da-DK', {
					timeZone: timezone,
					day: 'numeric',
					month: 'short',
					year: 'numeric'
				});
			} else if (format === 'time-only') {
				dateFormatter = null;
			} else {
				dateFormatter = new Intl.DateTimeFormat('da-DK', {
					timeZone: timezone,
					weekday: 'long',
					day: 'numeric',
					month: 'long',
					year: 'numeric'
				});
			}
			
			const dateString = dateFormatter ? dateFormatter.format(now) : '';
			
			// Update DOM
			const timeEl = clock.querySelector('.wta-time');
			const dateEl = clock.querySelector('.wta-date');
			
			if (timeEl) timeEl.textContent = timeString;
			if (dateEl) {
				if (format === 'time-only') {
					dateEl.style.display = 'none';
				} else {
					dateEl.textContent = dateString;
					dateEl.style.display = 'block';
				}
			}
			
		} catch (error) {
			console.error('Error updating widget clock:', error);
			const timeEl = clock.querySelector('.wta-time');
			if (timeEl) timeEl.textContent = 'Error';
		}
	}

	// Update city clock (live grid display)
	function updateCityClock(clock) {
		const timezone = clock.getAttribute('data-timezone');
		
		try {
			const now = new Date();
			
			// Format time with seconds
			const timeFormatter = new Intl.DateTimeFormat('da-DK', {
				timeZone: timezone,
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			});
			const timeString = timeFormatter.format(now);
			
			// Update DOM
			const timeEl = clock.querySelector('.wta-time');
			if (timeEl) {
				timeEl.textContent = timeString;
			}
			
		} catch (error) {
			console.error('Error updating city clock:', error);
			const timeEl = clock.querySelector('.wta-time');
			if (timeEl) timeEl.textContent = '--:--:--';
		}
	}

	// Initialize
	function init() {
		// Update immediately
		updateClocks();
		
		// Update every second
		setInterval(updateClocks, 1000);
	}

	// Start when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();


