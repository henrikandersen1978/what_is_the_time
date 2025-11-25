<?php
/**
 * Data & Import admin page.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get file info
$countries_info = WTA_Github_Fetcher::get_file_info( 'countries.json' );
$states_info = WTA_Github_Fetcher::get_file_info( 'states.json' );
$cities_info = WTA_Github_Fetcher::get_file_info( 'cities.json' );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Data & Import', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-admin-page">
		<!-- Data Files Status -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Data Files Status', WTA_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'JSON data files are stored in:', WTA_TEXT_DOMAIN ); ?> <code><?php echo esc_html( WTA_Github_Fetcher::get_data_directory() ); ?></code></p>
			
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'File', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Status', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Size', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Last Modified', WTA_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>countries.json</strong></td>
						<td><?php echo $countries_info ? '✅ Found' : '❌ Not Found'; ?></td>
						<td><?php echo $countries_info ? esc_html( $countries_info['size_formatted'] ) : '-'; ?></td>
						<td><?php echo $countries_info ? esc_html( $countries_info['modified_formatted'] ) : '-'; ?></td>
					</tr>
					<tr>
						<td><strong>states.json</strong></td>
						<td><?php echo $states_info ? '✅ Found' : '❌ Not Found'; ?></td>
						<td><?php echo $states_info ? esc_html( $states_info['size_formatted'] ) : '-'; ?></td>
						<td><?php echo $states_info ? esc_html( $states_info['modified_formatted'] ) : '-'; ?></td>
					</tr>
					<tr>
						<td><strong>cities.json</strong></td>
						<td><?php echo $cities_info ? '✅ Found' : '❌ Not Found'; ?></td>
						<td><?php echo $cities_info ? esc_html( $cities_info['size_formatted'] ) : '-'; ?></td>
						<td><?php echo $cities_info ? esc_html( $cities_info['modified_formatted'] ) : '-'; ?></td>
					</tr>
				</tbody>
			</table>

			<p class="description">
				<?php
				printf(
					/* translators: %s: data directory path */
					esc_html__( 'Place your JSON files in %s or they will be fetched from GitHub on first import.', WTA_TEXT_DOMAIN ),
					'<code>' . esc_html( WTA_Github_Fetcher::get_data_directory() ) . '</code>'
				);
				?>
			</p>
		</div>

		<!-- Import Configuration -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Import Configuration', WTA_TEXT_DOMAIN ); ?></h2>
			
			<form id="wta-import-form">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Select Continents', WTA_TEXT_DOMAIN ); ?></th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="continents[]" value="Europe" checked> <?php esc_html_e( 'Europe', WTA_TEXT_DOMAIN ); ?></label><br>
								<label><input type="checkbox" name="continents[]" value="Asia" checked> <?php esc_html_e( 'Asia', WTA_TEXT_DOMAIN ); ?></label><br>
								<label><input type="checkbox" name="continents[]" value="Africa"> <?php esc_html_e( 'Africa', WTA_TEXT_DOMAIN ); ?></label><br>
								<label><input type="checkbox" name="continents[]" value="North America"> <?php esc_html_e( 'North America', WTA_TEXT_DOMAIN ); ?></label><br>
								<label><input type="checkbox" name="continents[]" value="South America"> <?php esc_html_e( 'South America', WTA_TEXT_DOMAIN ); ?></label><br>
								<label><input type="checkbox" name="continents[]" value="Oceania"> <?php esc_html_e( 'Oceania', WTA_TEXT_DOMAIN ); ?></label><br>
								<label><input type="checkbox" name="continents[]" value="Antarctica"> <?php esc_html_e( 'Antarctica', WTA_TEXT_DOMAIN ); ?></label>
								<p class="description"><?php esc_html_e( 'Leave all unchecked to import all continents.', WTA_TEXT_DOMAIN ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="min_population"><?php esc_html_e( 'Minimum Population', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="min_population" name="min_population" value="0" min="0" class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Filter cities by minimum population (0 = no filter). Note: Cities without population data (null) will NOT be filtered out - only cities with known population below this threshold will be excluded.', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="max_cities"><?php esc_html_e( 'Max Cities per Country', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="max_cities" name="max_cities" value="0" min="0" class="regular-text">
							<p class="description"><?php esc_html_e( '0 = unlimited. Useful for testing with smaller datasets.', WTA_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clear Existing Queue', WTA_TEXT_DOMAIN ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="clear_queue" value="yes" checked>
								<?php esc_html_e( 'Clear existing queue before import', WTA_TEXT_DOMAIN ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary" id="wta-prepare-import">
						<?php esc_html_e( 'Prepare Import Queue', WTA_TEXT_DOMAIN ); ?>
					</button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="wta-import-result"></div>
		</div>

		<!-- Import Instructions -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Import Instructions', WTA_TEXT_DOMAIN ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Configure import settings above', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Click "Prepare Import Queue" to create queue items', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Action Scheduler will automatically process the queue in the background', WTA_TEXT_DOMAIN ); ?></li>
				<li>
					<?php
					printf(
						/* translators: %s: link to Action Scheduler */
						esc_html__( 'Monitor progress in %s', WTA_TEXT_DOMAIN ),
						'<a href="' . esc_url( admin_url( 'tools.php?page=action-scheduler' ) ) . '">' . esc_html__( 'Scheduled Actions', WTA_TEXT_DOMAIN ) . '</a>'
					);
					?>
				</li>
			</ol>

			<h3><?php esc_html_e( 'Processing Order', WTA_TEXT_DOMAIN ); ?></h3>
			<ol>
				<li><strong><?php esc_html_e( 'Structure', WTA_TEXT_DOMAIN ); ?>:</strong> <?php esc_html_e( 'Creates continent, country, and city posts with Danish names', WTA_TEXT_DOMAIN ); ?></li>
				<li><strong><?php esc_html_e( 'Timezone', WTA_TEXT_DOMAIN ); ?>:</strong> <?php esc_html_e( 'Resolves timezones for cities in complex countries via TimeZoneDB API', WTA_TEXT_DOMAIN ); ?></li>
				<li><strong><?php esc_html_e( 'AI Content', WTA_TEXT_DOMAIN ); ?>:</strong> <?php esc_html_e( 'Generates Danish content and publishes posts', WTA_TEXT_DOMAIN ); ?></li>
			</ol>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#wta-import-form').on('submit', function(e) {
		e.preventDefault();
		
		var $button = $('#wta-prepare-import');
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-import-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		var continents = [];
		$('input[name="continents[]"]:checked').each(function() {
			continents.push($(this).val());
		});
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_prepare_import',
				nonce: wtaAdmin.nonce,
				selected_continents: continents,
				min_population: $('#min_population').val(),
				max_cities_per_country: $('#max_cities').val(),
				clear_queue: $('input[name="clear_queue"]').is(':checked') ? 'yes' : 'no'
			},
			success: function(response) {
				if (response.success) {
					var html = '<div class="notice notice-success"><p>' + response.data.message + '</p>';
					html += '<p><strong>Queued items:</strong></p><ul>';
					html += '<li>Continents: ' + response.data.stats.continents + '</li>';
					html += '<li>Countries: ' + response.data.stats.countries + '</li>';
					html += '<li>Cities: ' + response.data.stats.cities + ' <em>(batch job - actual cities will be queued by cron)</em></li>';
					html += '</ul></div>';
					$result.html(html);
				} else {
					$result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>AJAX request failed</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});
});
</script>
