/**
 * World Time AI - Live Clock JavaScript
 */

(function() {
	'use strict';

	// Update all clocks on page
	function updateClocks() {
		// SEO Direct Answer elements
		updateDirectAnswer();
		
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
		
		// Global comparison times
		updateComparisonTimes();
	}
	
	// Update SEO Direct Answer section
	function updateDirectAnswer() {
		const timeEl = document.querySelector('.wta-live-time[data-timezone]');
		const dateEl = document.querySelector('.wta-live-date[data-timezone]');
		
		if (!timeEl && !dateEl) return;
		
		const timezone = timeEl ? timeEl.getAttribute('data-timezone') : dateEl.getAttribute('data-timezone');
		if (!timezone) return;
		
		try {
			const now = new Date();
			
			if (timeEl) {
				const timeFormatter = new Intl.DateTimeFormat('da-DK', {
					timeZone: timezone,
					hour: '2-digit',
					minute: '2-digit',
					second: '2-digit',
					hour12: false
				});
				timeEl.textContent = timeFormatter.format(now);
			}
			
			if (dateEl) {
				const dateFormatter = new Intl.DateTimeFormat('da-DK', {
					timeZone: timezone,
					weekday: 'long',
					day: 'numeric',
					month: 'long',
					year: 'numeric'
				});
				dateEl.textContent = dateFormatter.format(now);
			}
		} catch (error) {
			console.error('Error updating direct answer:', error);
		}
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
	
	// Update global comparison times
	function updateComparisonTimes() {
		const comparisonTimes = document.querySelectorAll('.wta-live-comparison-time[data-timezone]');
		
		comparisonTimes.forEach(function(timeEl) {
			const timezone = timeEl.getAttribute('data-timezone');
			
			try {
				const now = new Date();
				const formatter = new Intl.DateTimeFormat('da-DK', {
					timeZone: timezone,
					hour: '2-digit',
					minute: '2-digit',
					second: '2-digit',
					hour12: false
				});
				
				timeEl.textContent = formatter.format(now);
			} catch (error) {
				console.error('Error updating comparison time:', error);
				timeEl.textContent = '--:--:--';
			}
		});
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

/**
 * Smooth scroll for quick navigation buttons
 */
(function() {
	'use strict';

	function initSmoothScroll() {
		// Find all smooth scroll links
		const smoothScrollLinks = document.querySelectorAll('.wta-smooth-scroll');
		
		if (smoothScrollLinks.length === 0) {
			return;
		}

		smoothScrollLinks.forEach(function(link) {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				
				const targetId = this.getAttribute('href');
				const targetElement = document.querySelector(targetId);
				
				if (targetElement) {
					// Calculate offset for fixed headers (if any)
					const offset = 80; // Adjust based on your theme's header height
					const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;
					
					window.scrollTo({
						top: targetPosition,
						behavior: 'smooth'
					});
				}
			});
		});
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSmoothScroll);
	} else {
		initSmoothScroll();
	}

})();

/**
 * Convert ISO country codes to flag emojis
 * Uses Regional Indicator Symbols (U+1F1E6-1F1FF)
 */
(function() {
	'use strict';

	function isoToFlag(countryCode) {
		if (!countryCode || countryCode.length !== 2) {
			return '';
		}
		
		// Convert to uppercase
		countryCode = countryCode.toUpperCase();
		
		// Regional Indicator Symbol Letter A starts at U+1F1E6 (127462 in decimal)
		// A = 65 in ASCII, so offset = 127462 - 65 = 127397
		const codePoints = countryCode
			.split('')
			.map(function(char) {
				return 127397 + char.charCodeAt();
			});
		
		return String.fromCodePoint.apply(String, codePoints);
	}

	function convertFlagEmojis() {
		const flagElements = document.querySelectorAll('.wta-flag-emoji[data-country-code]');
		
		flagElements.forEach(function(element) {
			const countryCode = element.getAttribute('data-country-code');
			const flag = isoToFlag(countryCode);
			
			if (flag) {
				element.textContent = flag + ' ';
			}
		});
	}

	// Start when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', convertFlagEmojis);
	} else {
		convertFlagEmojis();
	}

})();


