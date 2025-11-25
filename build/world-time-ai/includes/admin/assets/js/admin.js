/**
 * Admin JavaScript for World Time AI
 *
 * @package WorldTimeAI
 */

(function($) {
	'use strict';

	/**
	 * Main admin object
	 */
	var WTA_Admin = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Import
			$('#wta-prepare-import').on('click', this.prepareImport.bind(this));
			
			// Stats refresh
			$('#wta-refresh-stats').on('click', this.refreshStats.bind(this));
			
			// API tests
			$('#wta-test-openai-api').on('click', this.testOpenAI.bind(this));
			$('#wta-test-timezonedb-api').on('click', this.testTimeZoneDB.bind(this));
			
			// Tools
			$('#wta-retry-failed').on('click', this.retryFailed.bind(this));
			$('#wta-reset-stuck').on('click', this.resetStuck.bind(this));
			$('#wta-reset-all').on('click', this.resetAll.bind(this));
			$('#wta-clear-cache').on('click', this.clearCache.bind(this));
			$('#wta-clear-logs').on('click', this.clearLogs.bind(this));
		},

		/**
		 * Prepare import
		 */
		prepareImport: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var $result = $('#wta-import-result');

			// Get form data
			var continents = [];
			$('input[name="continents[]"]:checked').each(function() {
				continents.push($(this).val());
			});

			var data = {
				action: 'wta_prepare_import',
				nonce: wtaAdmin.nonce,
				continents: continents,
				min_population: $('#min_population').val(),
				max_cities: $('#max_cities').val(),
				clear_existing: $('#clear_existing').is(':checked')
			};

			// Show loading
			$button.prop('disabled', true).text(wtaAdmin.strings.processing);
			$result.hide().removeClass('success error');

			// Make AJAX request
			$.post(wtaAdmin.ajaxurl, data, function(response) {
				if (response.success) {
					var stats = response.data.stats;
					var message = response.data.message + '<br><br>';
					message += '<strong>Queued items:</strong><br>';
					message += 'Continents: ' + stats.continents + '<br>';
					message += 'Countries: ' + stats.countries + '<br>';
					message += 'Cities: ' + stats.cities + ' <em>(batch job - actual cities will be queued by cron)</em>';
					
					$result.addClass('success').html(message).show();
				} else {
					$result.addClass('error').text(wtaAdmin.strings.error + ' ' + response.data.message).show();
				}
			}).fail(function() {
				$result.addClass('error').text(wtaAdmin.strings.error + ' Connection failed').show();
			}).always(function() {
				$button.prop('disabled', false).text('Prepare Import Queue');
			});
		},

		/**
		 * Refresh queue stats
		 */
		refreshStats: function(e) {
			e.preventDefault();
			location.reload();
		},

		/**
		 * Test OpenAI API
		 */
		testOpenAI: function(e) {
			e.preventDefault();
			this.testAPI('openai', $('#wta-test-openai-api'), $('#wta-test-openai-result'));
		},

		/**
		 * Test TimeZoneDB API
		 */
		testTimeZoneDB: function(e) {
			e.preventDefault();
			this.testAPI('timezonedb', $('#wta-test-timezonedb-api'), $('#wta-test-timezonedb-result'));
		},

		/**
		 * Test API connection
		 */
		testAPI: function(apiType, $button, $result) {
			var data = {
				action: 'wta_test_api',
				nonce: wtaAdmin.nonce,
				api_type: apiType
			};

			// Show loading
			$button.prop('disabled', true);
			$result.removeClass('success error').html('<span class="wta-spinner"></span>');

			// Make AJAX request
			$.post(wtaAdmin.ajaxurl, data, function(response) {
				if (response.success) {
					$result.addClass('success').html('✓ ' + response.data.message);
				} else {
					$result.addClass('error').html('✗ ' + response.data.message);
				}
			}).fail(function() {
				$result.addClass('error').html('✗ Connection failed');
			}).always(function() {
				$button.prop('disabled', false);
			});
		},

		/**
		 * Retry failed items
		 */
		retryFailed: function(e) {
			e.preventDefault();

			if (!confirm('Reset all failed items to pending?')) {
				return;
			}

			var $button = $(e.currentTarget);

			var data = {
				action: 'wta_retry_failed',
				nonce: wtaAdmin.nonce
			};

			$button.prop('disabled', true).text(wtaAdmin.strings.processing);

			$.post(wtaAdmin.ajaxurl, data, function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(wtaAdmin.strings.error + ' ' + response.data.message);
				}
			}).fail(function() {
				alert(wtaAdmin.strings.error + ' Connection failed');
			}).always(function() {
				$button.prop('disabled', false).text('Retry Failed Items');
			});
		},

		/**
		 * Reset stuck items
		 */
		resetStuck: function(e) {
			e.preventDefault();

			var data = {
				action: 'wta_retry_failed',
				nonce: wtaAdmin.nonce
			};

			$.post(wtaAdmin.ajaxurl, data, function(response) {
				if (response.success) {
					alert('Stuck items have been reset.');
					location.reload();
				} else {
					alert(wtaAdmin.strings.error + ' ' + response.data.message);
				}
			});
		},

		/**
		 * Reset all data
		 */
		resetAll: function(e) {
			e.preventDefault();

			if (!confirm(wtaAdmin.strings.confirm_reset)) {
				return;
			}

			var $button = $(e.currentTarget);

			var data = {
				action: 'wta_reset_all_data',
				nonce: wtaAdmin.nonce
			};

			$button.prop('disabled', true).text(wtaAdmin.strings.processing);

			$.post(wtaAdmin.ajaxurl, data, function(response) {
				if (response.success) {
					alert(response.data.message + '\nDeleted posts: ' + response.data.deleted);
					location.reload();
				} else {
					alert(wtaAdmin.strings.error + ' ' + response.data.message);
				}
			}).fail(function() {
				alert(wtaAdmin.strings.error + ' Connection failed');
			}).always(function() {
				$button.prop('disabled', false).text('Reset All Data');
			});
		},

		/**
		 * Clear cache
		 */
		clearCache: function(e) {
			e.preventDefault();
			// Since we don't have a specific endpoint, just refresh
			alert('Please save settings to clear cache, or use the import page.');
		},

		/**
		 * Clear logs
		 */
		clearLogs: function(e) {
			e.preventDefault();

			if (!confirm('Clear all logs?')) {
				return;
			}

			// Note: You'd need to add an AJAX endpoint for this
			alert('Log clearing functionality will be implemented.');
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		WTA_Admin.init();
	});

})(jQuery);




