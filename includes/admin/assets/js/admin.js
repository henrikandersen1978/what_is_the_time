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

	// Prepare Import Queue button handler
	$('#wta-prepare-import').on('click', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var $resultDiv = $('#wta-import-result');
		
		// Disable button
		$button.prop('disabled', true).text('Processing...');
		$resultDiv.hide().html('');
		
		// Collect form data
		var continents = [];
		$('input[name="continents[]"]:checked').each(function() {
			continents.push($(this).val());
		});
		
		var data = {
			action: 'wta_prepare_import',
			nonce: wtaAdmin.nonce,
			selected_continents: continents,
			min_population: $('#min_population').val() || 0,
			max_cities_per_country: $('#max_cities').val() || 0,
			clear_queue: $('#clear_existing').is(':checked') ? 'yes' : 'no'
		};
		
		// Make AJAX request
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: data,
			success: function(response) {
				$button.prop('disabled', false).text('Prepare Import Queue');
				
				if (response.success) {
					var stats = response.data.stats || {};
					var message = '<strong>Import queue prepared successfully!</strong><br><br>';
					message += 'Queued items:<br>';
					if (stats.continents) message += '• Continents: ' + stats.continents + '<br>';
					if (stats.countries) message += '• Countries: ' + stats.countries + '<br>';
					if (stats.cities) message += '• Cities: ' + stats.cities + '<br>';
					
					$resultDiv.html(
						'<div class="notice notice-success"><p>' + message + '</p></div>'
					).show();
				} else {
					$resultDiv.html(
						'<div class="notice notice-error"><p>' +
						'<strong>Error:</strong> ' + (response.data.message || 'Unknown error') +
						'</p></div>'
					).show();
				}
			},
			error: function(xhr, status, error) {
				$button.prop('disabled', false).text('Prepare Import Queue');
				$resultDiv.html(
					'<div class="notice notice-error"><p>' +
					'<strong>AJAX Error:</strong> ' + error +
					'</p></div>'
				).show();
			}
		});
	});

})(jQuery);
