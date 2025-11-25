/**
 * World Time AI - Admin JavaScript
 */

(function($) {
	'use strict';

	// Auto-refresh queue stats on dashboard
	if ($('.wta-dashboard').length) {
		setInterval(function() {
			refreshQueueStats();
		}, 30000); // Every 30 seconds
	}

	function refreshQueueStats() {
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_get_queue_stats',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					updateQueueDisplay(response.data);
				}
			}
		});
	}

	function updateQueueDisplay(stats) {
		// Update stats in dashboard if elements exist
		$('[data-stat="pending"]').text(numberFormat(stats.by_status.pending));
		$('[data-stat="processing"]').text(numberFormat(stats.by_status.processing));
		$('[data-stat="done"]').text(numberFormat(stats.by_status.done));
		$('[data-stat="error"]').text(numberFormat(stats.by_status.error));
	}

	function numberFormat(num) {
		return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
	}

})(jQuery);
