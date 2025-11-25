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
			
			// File uploads
			$('.wta-upload-btn').on('click', this.handleFileUpload.bind(this));
			
			// Tools
			$('#wta-retry-failed').on('click', this.retryFailed.bind(this));
			$('#wta-reset-stuck').on('click', this.resetStuck.bind(this));
			$('#wta-reset-all').on('click', this.resetAll.bind(this));
			$('#wta-clear-cache').on('click', this.clearCache.bind(this));
			$('#wta-clear-logs').on('click', this.clearLogs.bind(this));
		},

		/**
		 * Handle file upload
		 */
		handleFileUpload: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var fileType = $button.data('type');
			var $fileInput = $('#wta_upload_' + fileType);
			var $status = $('#wta-' + fileType + '-status');
			var $progress = $('#wta-' + fileType + '-progress');
			
			var file = $fileInput[0].files[0];
			
			if (!file) {
				alert('Please select a file first');
				return;
			}
			
			if (!file.name.endsWith('.json')) {
				alert('Please select a JSON file');
				return;
			}
			
			// Check file size - use chunked upload for files > 10MB
			var chunkSize = 5 * 1024 * 1024; // 5MB chunks
			var useChunkedUpload = file.size > 10 * 1024 * 1024;
			
			if (useChunkedUpload) {
				this.chunkedUpload(file, fileType, $button, $status, $progress, chunkSize);
			} else {
				this.simpleUpload(file, fileType, $button, $status);
			}
		},

		/**
		 * Simple upload for small files
		 */
		simpleUpload: function(file, fileType, $button, $status) {
			var formData = new FormData();
			formData.append('action', 'wta_upload_json');
			formData.append('nonce', wtaAdmin.nonce);
			formData.append('file_type', fileType);
			formData.append('file', file);
			
			$button.prop('disabled', true);
			$status.removeClass('success error').addClass('uploading').text('Uploading...');
			
			$.ajax({
				url: wtaAdmin.ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						$status.removeClass('uploading').addClass('success').text('✓ ' + response.data.message);
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						$status.removeClass('uploading').addClass('error').text('✗ ' + response.data.message);
					}
				},
				error: function() {
					$status.removeClass('uploading').addClass('error').text('✗ Upload failed');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Chunked upload for large files
		 */
		chunkedUpload: function(file, fileType, $button, $status, $progress, chunkSize) {
			var totalChunks = Math.ceil(file.size / chunkSize);
			var currentChunk = 0;
			var uploadId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
			
			$button.prop('disabled', true);
			$status.removeClass('success error').addClass('uploading').text('Uploading in chunks...');
			$progress.show();
			
			var uploadChunk = function() {
				var start = currentChunk * chunkSize;
				var end = Math.min(start + chunkSize, file.size);
				var chunk = file.slice(start, end);
				
				var formData = new FormData();
				formData.append('action', 'wta_upload_json_chunk');
				formData.append('nonce', wtaAdmin.nonce);
				formData.append('file_type', fileType);
				formData.append('chunk', chunk);
				formData.append('chunk_index', currentChunk);
				formData.append('total_chunks', totalChunks);
				formData.append('upload_id', uploadId);
				formData.append('file_name', file.name);
				
				$.ajax({
					url: wtaAdmin.ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if (response.success) {
							currentChunk++;
							var progress = Math.round((currentChunk / totalChunks) * 100);
							$progress.find('.progress-bar').css('width', progress + '%');
							$progress.find('.progress-text').text(progress + '%');
							
							if (currentChunk < totalChunks) {
								// Upload next chunk
								uploadChunk();
							} else {
								// All chunks uploaded
								$status.removeClass('uploading').addClass('success').text('✓ Upload complete!');
								$progress.hide();
								setTimeout(function() {
									location.reload();
								}, 1500);
							}
						} else {
							$status.removeClass('uploading').addClass('error').text('✗ ' + response.data.message);
							$progress.hide();
							$button.prop('disabled', false);
						}
					},
					error: function() {
						$status.removeClass('uploading').addClass('error').text('✗ Upload failed at chunk ' + (currentChunk + 1));
						$progress.hide();
						$button.prop('disabled', false);
					}
				});
			};
			
			// Start uploading
			uploadChunk();
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
					message += 'Cities: ' + stats.cities;
					
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




