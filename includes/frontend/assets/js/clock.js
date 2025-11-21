/**
 * Live clock functionality using timezone data
 *
 * @package WorldTimeAI
 */

(function() {
	'use strict';

	/**
	 * Initialize all clocks on the page
	 */
	function initClocks() {
		const clocks = document.querySelectorAll('.wta-clock');
		
		clocks.forEach(function(clock) {
			const timezone = clock.getAttribute('data-timezone');
			const baseTime = clock.getAttribute('data-base-time');
			
			if (!timezone || !baseTime) {
				return;
			}

			// Parse base time
			const baseDate = new Date(baseTime);
			const baseTimestamp = baseDate.getTime();
			
			// Store in element
			clock.wtaData = {
				timezone: timezone,
				baseTimestamp: baseTimestamp,
				serverTime: Date.now()
			};

			// Update immediately
			updateClock(clock);

			// Update every second
			setInterval(function() {
				updateClock(clock);
			}, 1000);
		});
	}

	/**
	 * Update a single clock
	 *
	 * @param {HTMLElement} clock Clock element
	 */
	function updateClock(clock) {
		if (!clock.wtaData) {
			return;
		}

		// Calculate elapsed time since server time
		const now = Date.now();
		const elapsed = now - clock.wtaData.serverTime;
		
		// Calculate current time in timezone
		const currentTimestamp = clock.wtaData.baseTimestamp + elapsed;
		const currentDate = new Date(currentTimestamp);

		// Format time
		const timeString = formatTime(currentDate, clock.wtaData.timezone);
		
		// Update display
		const timeElement = clock.querySelector('.wta-time');
		if (timeElement) {
			timeElement.textContent = timeString;
		} else {
			clock.textContent = timeString;
		}
	}

	/**
	 * Format time in HH:MM:SS format
	 *
	 * @param {Date} date Date object
	 * @param {string} timezone Timezone identifier
	 * @return {string} Formatted time
	 */
	function formatTime(date, timezone) {
		try {
			// Try using Intl.DateTimeFormat for proper timezone formatting
			const formatter = new Intl.DateTimeFormat('en-US', {
				timeZone: timezone,
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			});
			
			return formatter.format(date);
		} catch (e) {
			// Fallback to simple formatting
			const hours = String(date.getHours()).padStart(2, '0');
			const minutes = String(date.getMinutes()).padStart(2, '0');
			const seconds = String(date.getSeconds()).padStart(2, '0');
			
			return hours + ':' + minutes + ':' + seconds;
		}
	}

	/**
	 * Initialize on DOM ready
	 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initClocks);
	} else {
		initClocks();
	}

	/**
	 * Re-initialize if content is dynamically loaded
	 */
	if (typeof MutationObserver !== 'undefined') {
		const observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				if (mutation.addedNodes.length) {
					initClocks();
				}
			});
		});

		if (document.body) {
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		}
	}
})();




