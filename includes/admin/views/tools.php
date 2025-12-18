<?php
/**
 * Tools admin page.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Tools & Maintenance', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-admin-page">
		<!-- Logs -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Recent Logs', WTA_TEXT_DOMAIN ); ?></h2>
			<p>
				<button type="button" class="button" id="wta-load-logs"><?php esc_html_e( 'Load Recent Logs', WTA_TEXT_DOMAIN ); ?></button>
				<span class="spinner"></span>
			</p>
			<div id="wta-logs-container"></div>
		</div>

		<!-- Queue Management -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Queue Management', WTA_TEXT_DOMAIN ); ?></h2>
			<p>
				<button type="button" class="button" id="wta-reset-stuck"><?php esc_html_e( 'Reset Stuck Jobs', WTA_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="wta-retry-failed"><?php esc_html_e( 'Retry Failed Items', WTA_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="wta-view-queue-details"><?php esc_html_e( 'View Queue Details', WTA_TEXT_DOMAIN ); ?></button>
				<span class="spinner"></span>
			</p>
			<p class="description"><?php esc_html_e( 'Reset stuck/processing jobs (5+ min) or retry failed items (max 3 attempts).', WTA_TEXT_DOMAIN ); ?></p>
			<div id="wta-retry-result"></div>
			<div id="wta-queue-details"></div>
		</div>

		<!-- Regenerate ALL AI Content (v2.34.20) -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Regenerate ALL AI Content', WTA_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Queue AI content generation for ALL location posts (continents, countries, cities).', WTA_TEXT_DOMAIN ); ?></p>
			<p class="description">
				<strong><?php esc_html_e( 'Use this when:', WTA_TEXT_DOMAIN ); ?></strong><br>
				• <?php esc_html_e( 'Switching from Test Mode to Normal Mode', WTA_TEXT_DOMAIN ); ?><br>
				• <?php esc_html_e( 'After changing AI prompts', WTA_TEXT_DOMAIN ); ?><br>
				• <?php esc_html_e( 'After a full import in test mode (template content)', WTA_TEXT_DOMAIN ); ?>
			</p>
			<?php
			$total_posts = wp_count_posts( WTA_POST_TYPE );
			$published = isset( $total_posts->publish ) ? $total_posts->publish : 0;
			$estimated_cost = round( $published * 8 * 0.00017, 2 ); // 8 API calls per post × $0.00017/1k tokens
			$estimated_days = max( 1, round( $published / 10 / 60 / 24 ) ); // 10 posts/min × 60 min × 24 hours
			?>
			<p>
				<strong><?php esc_html_e( 'Current Stats:', WTA_TEXT_DOMAIN ); ?></strong><br>
				• <?php printf( esc_html__( 'Published posts: %s', WTA_TEXT_DOMAIN ), number_format( $published ) ); ?><br>
				• <?php printf( esc_html__( 'Estimated cost: ~$%s (gpt-4o-mini)', WTA_TEXT_DOMAIN ), $estimated_cost ); ?><br>
				• <?php printf( esc_html__( 'Estimated time: ~%d days', WTA_TEXT_DOMAIN ), $estimated_days ); ?>
			</p>
			<p class="description">
				<strong style="color: #d63638;"><?php esc_html_e( '⚠️ Warning:', WTA_TEXT_DOMAIN ); ?></strong>
				<?php esc_html_e( 'This will cost real money in OpenAI API calls! Make sure Test Mode is disabled and you want to generate AI content for all posts.', WTA_TEXT_DOMAIN ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="wta-regenerate-all-ai"><?php esc_html_e( 'Regenerate ALL AI Content', WTA_TEXT_DOMAIN ); ?></button>
				<span class="spinner"></span>
			</p>
			<div id="wta-regenerate-ai-result"></div>
		</div>

		<!-- Action Scheduler -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Scheduled Actions', WTA_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'View and manage Action Scheduler jobs.', WTA_TEXT_DOMAIN ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=action-scheduler' ) ); ?>" class="button">
					<?php esc_html_e( 'View Scheduled Actions', WTA_TEXT_DOMAIN ); ?>
				</a>
			</p>
		</div>

		<!-- Translation Cache -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Translation Cache', WTA_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Clear cached AI translations. Use this when you change the base language or want to force fresh translations.', WTA_TEXT_DOMAIN ); ?></p>
			<p>
				<button type="button" class="button" id="wta-clear-translation-cache"><?php esc_html_e( 'Clear Translation Cache', WTA_TEXT_DOMAIN ); ?></button>
				<span class="spinner"></span>
			</p>
			<div id="wta-translation-cache-result"></div>
		</div>

		<!-- Shortcode Cache -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Shortcode Cache', WTA_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Clear cached shortcode data (child locations, nearby cities, major cities, etc.). Use this after updating the plugin or if shortcodes show old data.', WTA_TEXT_DOMAIN ); ?></p>
			<p>
				<button type="button" class="button" id="wta-clear-shortcode-cache"><?php esc_html_e( 'Clear Shortcode Cache', WTA_TEXT_DOMAIN ); ?></button>
				<span class="spinner"></span>
			</p>
			<div id="wta-shortcode-cache-result"></div>
		</div>

		<!-- v3.0.19: Country GPS Migration removed - no longer needed -->
		<!-- GeoNames migration uses post_parent hierarchy, shortcodes work directly with city GPS -->

		<!-- Permalink Regeneration -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Regenerate Permalinks', WTA_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Regenerate all location permalinks to remove cached /location/ prefix from URLs. This updates internal links, schema markup, and Yoast SEO data.', WTA_TEXT_DOMAIN ); ?></p>
			<p class="description"><strong><?php esc_html_e( 'Use this after URL structure changes or if internal links still show old URLs.', WTA_TEXT_DOMAIN ); ?></strong></p>
			<p>
				<button type="button" class="button button-primary" id="wta-regenerate-permalinks"><?php esc_html_e( 'Regenerate All Permalinks', WTA_TEXT_DOMAIN ); ?></button>
				<span class="spinner"></span>
			</p>
			<div id="wta-permalink-result"></div>
		</div>

		<!-- Reset Data -->
		<div class="wta-card wta-card-warning">
			<h2><?php esc_html_e( 'Reset All Data', WTA_TEXT_DOMAIN ); ?></h2>
			<p><strong><?php esc_html_e( 'Warning:', WTA_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'This will delete all location posts and clear the queue. This action cannot be undone!', WTA_TEXT_DOMAIN ); ?></p>
			<p>
				<button type="button" class="button button-secondary" id="wta-reset-data"><?php esc_html_e( 'Reset All Data', WTA_TEXT_DOMAIN ); ?></button>
				<span class="spinner"></span>
			</p>
			<div id="wta-reset-result"></div>
		</div>

	<!-- Data Files (v3.0.0 - GeoNames) -->
	<div class="wta-card">
		<h2><?php esc_html_e( 'GeoNames Data Files', WTA_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'Data files location:', WTA_TEXT_DOMAIN ); ?> <code><?php echo esc_html( WTA_GeoNames_Parser::get_data_directory() ); ?></code></p>
		<p class="description"><?php esc_html_e( 'Files in this directory persist across plugin updates.', WTA_TEXT_DOMAIN ); ?></p>
		<?php
		$data_dir = WTA_GeoNames_Parser::get_data_directory();
		$cities_file = $data_dir . '/cities500.txt';
		$countries_file = $data_dir . '/countryInfo.txt';
		$alt_names_file = $data_dir . '/alternateNamesV2.txt';
		?>
		<ul>
			<li>
				<strong>cities500.txt:</strong>
				<?php echo file_exists( $cities_file ) ? '✅ ' . size_format( filesize( $cities_file ) ) : '❌ Not found'; ?>
			</li>
			<li>
				<strong>countryInfo.txt:</strong>
				<?php echo file_exists( $countries_file ) ? '✅ ' . size_format( filesize( $countries_file ) ) : '❌ Not found'; ?>
			</li>
			<li>
				<strong>alternateNamesV2.txt:</strong>
				<?php echo file_exists( $alt_names_file ) ? '✅ ' . size_format( filesize( $alt_names_file ) ) : '❌ Not found'; ?>
			</li>
		</ul>
	</div>

		<!-- System Info -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'System Information', WTA_TEXT_DOMAIN ); ?></h2>
			<table class="widefat">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Plugin Version', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( WTA_VERSION ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WordPress Version', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'PHP Version', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( phpversion() ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'MySQL Version', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php global $wpdb; echo esc_html( $wpdb->db_version() ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Memory Limit', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( WP_MEMORY_LIMIT ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Max Execution Time', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?> seconds</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Upload Max Size', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( ini_get( 'upload_max_filesize' ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Load logs
	$('#wta-load-logs').on('click', function() {
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $container = $('#wta-logs-container');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_get_logs',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					var logs = response.data.logs;
					if (logs.length > 0) {
						var html = '<pre style="background: #f1f1f1; padding: 10px; max-height: 400px; overflow-y: scroll;">';
						logs.forEach(function(log) {
							html += log + '\n';
						});
						html += '</pre>';
						$container.html(html);
					} else {
						$container.html('<p>No logs found.</p>');
					}
				}
			},
			error: function() {
				$container.html('<div class="notice notice-error"><p>Failed to load logs</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Clear translation cache
	$('#wta-clear-translation-cache').on('click', function() {
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-translation-cache-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_clear_translation_cache',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ Request failed</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Clear shortcode cache
	$('#wta-clear-shortcode-cache').on('click', function() {
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-shortcode-cache-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_clear_shortcode_cache',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ Request failed</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Migrate country GPS (v2.35.73)
	$('#wta-migrate-country-gps').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'This will calculate GPS coordinates for all countries based on their largest city. This takes about 5-10 seconds. Continue?', WTA_TEXT_DOMAIN ) ); ?>')) {
			return;
		}
		
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-country-gps-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('<div class="notice notice-info"><p>⏳ Calculating GPS coordinates for all countries... This takes about 5-10 seconds.</p></div>');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_migrate_country_gps',
				nonce: wtaAdmin.nonce
			},
			timeout: 60000, // 60 seconds timeout
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
					// Reload page after 3 seconds to show updated stats
					setTimeout(function() {
						location.reload();
					}, 3000);
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ Request failed or timed out.</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Regenerate permalinks
	$('#wta-regenerate-permalinks').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'This will regenerate permalinks for all location posts. This may take a few moments. Continue?', WTA_TEXT_DOMAIN ) ); ?>')) {
			return;
		}
		
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-permalink-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('<div class="notice notice-info"><p>⏳ Regenerating permalinks... This may take a minute.</p></div>');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_regenerate_permalinks',
				nonce: wtaAdmin.nonce
			},
			timeout: 120000, // 2 minutes timeout
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '<br><strong>Updated:</strong> ' + response.data.updated + ' posts</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ Request failed or timed out. The process might still be running in the background.</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Reset stuck jobs
	$('#wta-reset-stuck').on('click', function() {
		var $button = $(this);
		var $spinner = $button.siblings('.spinner');
		var $result = $('#wta-retry-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_reset_stuck_items',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ Request failed</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// View queue details
	$('#wta-view-queue-details').on('click', function() {
		var $button = $(this);
		var $spinner = $button.siblings('.spinner');
		var $result = $('#wta-queue-details');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_view_queue_details',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html(response.data.html);
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ Request failed</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Retry failed
	$('#wta-retry-failed').on('click', function() {
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-retry-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_retry_failed_items',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ Request failed</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Regenerate ALL AI Content (v2.34.20)
	$('#wta-regenerate-all-ai').on('click', function() {
		var $button = $(this);
		var $result = $('#wta-regenerate-ai-result');
		
		// Get estimated cost from page
		var costText = $button.closest('.wta-card').find('p:contains("Estimated cost")').text();
		var costMatch = costText.match(/\$(\d+\.?\d*)/);
		var estimatedCost = costMatch ? costMatch[1] : 'unknown';
		
		// Confirm with user
		if (!confirm('⚠️ WARNING: This will cost approximately $' + estimatedCost + ' in OpenAI API calls!\n\nAre you absolutely sure you want to regenerate AI content for ALL posts?\n\nThis action will:\n- Queue AI content generation for all location posts\n- Cost real money in API calls\n- Take several days to complete\n\nClick OK to continue or Cancel to abort.')) {
			return;
		}
		
		// Double confirmation
		if (!confirm('FINAL CONFIRMATION:\n\nYou are about to spend ~$' + estimatedCost + ' on AI content generation.\n\nMake sure:\n✅ Test Mode is DISABLED (otherwise no cost but template content only)\n✅ You have sufficient OpenAI credits\n✅ You really want to regenerate ALL posts\n\nProceed?')) {
			return;
		}
		
		var $spinner = $button.next('.spinner');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('<div class="notice notice-info"><p>⏳ Queuing all posts for AI content generation... This may take a minute.</p></div>');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_regenerate_all_ai',
				nonce: wtaAdmin.nonce
			},
			timeout: 180000, // 3 minutes timeout
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
					
					// Reload page after 2 seconds to show updated queue stats
					setTimeout(function() {
						window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=world-time-ai' ) ); ?>';
					}, 2000);
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function(xhr, status, error) {
				if (status === 'timeout') {
					$result.html('<div class="notice notice-warning"><p>⚠️ Request timed out, but the queuing process might still be running in the background. Check the queue status in a moment.</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ Request failed: ' + error + '</p></div>');
				}
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});

	// Reset data
	$('#wta-reset-data').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete all location posts and clear the queue? This cannot be undone!', WTA_TEXT_DOMAIN ) ); ?>')) {
			return;
		}
		
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-reset-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_reset_all_data',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + ' (' + response.data.deleted + ' posts deleted)</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ Request failed</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});
});
</script>


