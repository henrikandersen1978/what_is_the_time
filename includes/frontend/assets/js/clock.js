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
		
		// FAQ time elements (v3.3.14)
		updateFaqTimes();
	}
	
	// Update SEO Direct Answer section (TIME ONLY - date is static from PHP!)
	// v3.2.19: Date removed - it only changes at midnight, no need for JS updates every second!
	function updateDirectAnswer() {
		const timeEl = document.querySelector('.wta-live-time[data-timezone]');
		if (!timeEl) return;
		
		const timezone = timeEl.getAttribute('data-timezone');
		if (!timezone) return;
		
		try {
			const now = new Date();
			
			// Use dynamic locale from PHP (window.wtaLocale) or fallback to da-DK
			const locale = window.wtaLocale || 'da-DK';
			
			const timeFormatter = new Intl.DateTimeFormat(locale, {
				timeZone: timezone,
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			});
			timeEl.textContent = timeFormatter.format(now);
		} catch (error) {
			console.error('Error updating time:', error);
		}
	}

	// Update main clock (single location page)
	function updateMainClock(clock) {
		const timezone = clock.getAttribute('data-timezone');
		
		try {
			const now = new Date();
			
			// v3.2.19: Use dynamic locale from PHP (window.wtaLocale) or fallback to da-DK
			const locale = window.wtaLocale || 'da-DK';
			
			// Format time
			const timeFormatter = new Intl.DateTimeFormat(locale, {
				timeZone: timezone,
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			});
			const timeString = timeFormatter.format(now);
			
			// Format date
			const dateFormatter = new Intl.DateTimeFormat(locale, {
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
			
			// v3.2.19: Use dynamic locale from PHP (window.wtaLocale) or fallback to da-DK
			const locale = window.wtaLocale || 'da-DK';
			
			// Format time
			const timeFormatter = new Intl.DateTimeFormat(locale, {
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
				dateFormatter = new Intl.DateTimeFormat(locale, {
					timeZone: timezone,
					day: 'numeric',
					month: 'short',
					year: 'numeric'
				});
			} else if (format === 'time-only') {
				dateFormatter = null;
			} else {
				dateFormatter = new Intl.DateTimeFormat(locale, {
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
			
			// v3.2.19: Use dynamic locale from PHP (window.wtaLocale) or fallback to da-DK
			const locale = window.wtaLocale || 'da-DK';
			
			// Format time with seconds
			const timeFormatter = new Intl.DateTimeFormat(locale, {
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
		
		// v3.2.19: Use dynamic locale from PHP (window.wtaLocale) or fallback to da-DK
		const locale = window.wtaLocale || 'da-DK';
		
		comparisonTimes.forEach(function(timeEl) {
			const timezone = timeEl.getAttribute('data-timezone');
			
			try {
				const now = new Date();
				const formatter = new Intl.DateTimeFormat(locale, {
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
	
	// Update FAQ time elements (v3.3.14)
	// Provides live time updates in FAQ answers while maintaining SEO-friendly server-rendered times
	function updateFaqTimes() {
		const faqTimes = document.querySelectorAll('.wta-live-faq-time[data-timezone]');
		
		// Use dynamic locale from PHP (window.wtaLocale) or fallback to da-DK
		const locale = window.wtaLocale || 'da-DK';
		
		faqTimes.forEach(function(timeEl) {
			const timezone = timeEl.getAttribute('data-timezone');
			
			try {
				const now = new Date();
				const formatter = new Intl.DateTimeFormat(locale, {
					timeZone: timezone,
					hour: '2-digit',
					minute: '2-digit',
					second: '2-digit',
					hour12: false
				});
				
				timeEl.textContent = formatter.format(now);
			} catch (error) {
				console.error('Error updating FAQ time:', error);
				// Keep server-rendered time on error (graceful degradation)
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
 * Flag emojis are now handled by flag-icons CSS library
 * This provides universal browser support (including Chrome on Windows)
 * See: https://github.com/lipis/flag-icons
 * 
 * Previous approach using Regional Indicator Symbols worked on Safari/macOS
 * but not on Chrome/Windows, which doesn't support native flag emojis.
 */


