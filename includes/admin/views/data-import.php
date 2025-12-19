<?php
/**
 * Data & Import view
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$continents = WTA_Utils::get_available_continents();
$selected_continents = get_option( 'wta_selected_continents', array() );
$min_population = get_option( 'wta_min_population', 0 );
$max_cities = get_option( 'wta_max_cities_per_country', 0 );
?>

<div class="wrap wta-admin-wrap">
	<h1><?php esc_html_e( 'Data & Import', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-admin-grid">
	<!-- GeoNames Data Files (v3.0.0) -->
	<div class="wta-card wta-card-wide">
		<h2><?php esc_html_e( 'GeoNames Data Files', WTA_TEXT_DOMAIN ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Upload GeoNames files to wp-content/uploads/world-time-ai-data/', WTA_TEXT_DOMAIN ); ?>
		</p>
		
		<?php
		$upload_dir = wp_upload_dir();
		$data_dir = $upload_dir['basedir'] . '/world-time-ai-data/';
		
		$files = array(
			'cities500.txt' => array('required' => true, 'expected_size' => '~37 MB', 'description' => 'Cities with population > 500'),
			'countryInfo.txt' => array('required' => true, 'expected_size' => '~31 KB', 'description' => 'Country information'),
			'alternateNamesV2.txt' => array('required' => true, 'expected_size' => '~745 MB', 'description' => 'Multi-language city names'),
			'iso-languagecodes.txt' => array('required' => false, 'expected_size' => '~135 KB', 'description' => 'Language codes (optional)'),
		);
		?>
		
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'File', WTA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Status', WTA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Size', WTA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Last Modified', WTA_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($files as $filename => $info): ?>
					<?php
					$filepath = $data_dir . $filename;
					$exists = file_exists($filepath);
					$size = $exists ? size_format(filesize($filepath), 2) : '-';
					$modified = $exists ? date_i18n('Y-m-d H:i', filemtime($filepath)) : '-';
					$required = $info['required'] ? '(Required)' : '(Optional)';
					?>
					<tr>
						<td>
							<strong><?php echo esc_html($filename); ?></strong>
							<br><span class="description"><?php echo esc_html($info['description'] . ' ' . $required); ?></span>
						</td>
						<td>
							<?php if ($exists): ?>
								<span style="color: #46b450; font-weight: bold;">✅ Found</span>
							<?php else: ?>
								<span style="color: #dc3232; font-weight: bold;">❌ Missing</span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html($size); ?>
							<br><span class="description">Expected: <?php echo esc_html($info['expected_size']); ?></span>
						</td>
						<td><?php echo esc_html($modified); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<p class="description" style="margin-top: 15px;">
			<strong><?php esc_html_e( 'Download from GeoNames:', WTA_TEXT_DOMAIN ); ?></strong><br>
			• <a href="https://download.geonames.org/export/dump/cities500.zip" target="_blank">cities500.zip</a> (unzip to get cities500.txt)<br>
			• <a href="https://download.geonames.org/export/dump/countryInfo.txt" target="_blank">countryInfo.txt</a><br>
			• <a href="https://download.geonames.org/export/dump/alternateNamesV2.zip" target="_blank">alternateNamesV2.zip</a> (unzip to get alternateNamesV2.txt)<br>
			• <a href="https://download.geonames.org/export/dump/iso-languagecodes.txt" target="_blank">iso-languagecodes.txt</a> (optional)
		</p>
		
		<p class="description" style="margin-top: 10px; padding: 10px; background: #f0f6fc; border-left: 4px solid #0073aa;">
			<strong><?php esc_html_e( 'Note:', WTA_TEXT_DOMAIN ); ?></strong>
			<?php esc_html_e( 'After uploading files, the translation cache will be built automatically when you click "Prepare Import Queue". This takes 2-5 minutes for alternateNamesV2.txt.', WTA_TEXT_DOMAIN ); ?>
		</p>
	</div>

		<!-- Background Processing Settings -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Background Processing Settings', WTA_TEXT_DOMAIN ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'wta_data_import_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Processing Frequency', WTA_TEXT_DOMAIN ); ?>
						</th>
						<td>
							<?php $cron_interval = intval( get_option( 'wta_cron_interval', 60 ) ); ?>
							<fieldset>
								<label>
									<input type="radio" name="wta_cron_interval" value="60" <?php checked( $cron_interval, 60 ); ?>>
									<strong><?php esc_html_e( '1 minute', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'Quick feedback, smaller batches', WTA_TEXT_DOMAIN ); ?>
								</label>
								<br>
								<label>
									<input type="radio" name="wta_cron_interval" value="300" <?php checked( $cron_interval, 300 ); ?>>
									<strong><?php esc_html_e( '5 minutes', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'Larger batches, better for big imports (recommended)', WTA_TEXT_DOMAIN ); ?>
								</label>
							</fieldset>
							<p class="description">
								<strong><?php esc_html_e( 'Current:', WTA_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( $cron_interval === 300 ? '5 minutes' : '1 minute' ); ?>
								<br>
								<strong><?php esc_html_e( 'Batch sizes adjust automatically:', WTA_TEXT_DOMAIN ); ?></strong>
								<br>• 1 min: AI = 3 cities (45s), Structure = 10 cities
								<br>• 5 min: AI = 15 cities (225s), Structure = 30 cities
								<br>
								<br>⚠️ <strong><?php esc_html_e( 'Important:', WTA_TEXT_DOMAIN ); ?></strong> 
								<?php esc_html_e( 'If using server cron, update your crontab:', WTA_TEXT_DOMAIN ); ?>
								<br><code>*<?php echo $cron_interval === 300 ? '/5' : ''; ?> * * * * wget -q -O - <?php echo esc_url( site_url( 'wp-cron.php' ) ); ?>?doing_wp_cron</code>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Processing Settings', WTA_TEXT_DOMAIN ) ); ?>
			</form>
			
			<!-- Force Reschedule Actions -->
			<div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
				<h3 style="margin-top: 0;"><?php esc_html_e( 'Troubleshooting', WTA_TEXT_DOMAIN ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'If recurring actions are not using the correct interval after changing settings, click this button to force reschedule all actions:', WTA_TEXT_DOMAIN ); ?>
				</p>
				<button type="button" id="wta-force-reschedule" class="button button-secondary">
					<?php esc_html_e( '🔄 Force Reschedule Actions Now', WTA_TEXT_DOMAIN ); ?>
				</button>
				<span id="wta-reschedule-spinner" class="spinner" style="float: none; margin: 0 10px;"></span>
				<div id="wta-reschedule-result" style="margin-top: 10px;"></div>
			</div>
		</div>

		<!-- Performance Information -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Performance Information', WTA_TEXT_DOMAIN ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Current Action Scheduler optimization settings:', WTA_TEXT_DOMAIN ); ?>
			</p>
			
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Concurrent Batches (Test Mode)', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<input type="number" 
						       name="wta_concurrent_batches_test" 
						       value="<?php echo esc_attr( get_option( 'wta_concurrent_batches_test', 12 ) ); ?>" 
						       min="1" 
						       max="20" 
						       class="small-text">
						<p class="description">
							<strong>Recommended: 10-15 for 16 CPU server</strong><br>
							<?php esc_html_e( 'High parallelization (no API limits in test mode). Optimizes structure creation and template generation.', WTA_TEXT_DOMAIN ); ?><br>
							⚠️ <?php esc_html_e( 'Timezone processor always runs single-threaded (TimeZoneDB FREE tier rate limit).', WTA_TEXT_DOMAIN ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Concurrent Batches (Normal Mode)', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<input type="number" 
						       name="wta_concurrent_batches_normal" 
						       value="<?php echo esc_attr( get_option( 'wta_concurrent_batches_normal', 6 ) ); ?>" 
						       min="1" 
						       max="15" 
						       class="small-text">
						<p class="description">
							<strong>Recommended: 5-8 for OpenAI Tier 5</strong><br>
							<?php esc_html_e( 'Moderate parallelization (respects OpenAI API rate limits). Balances speed and API quotas.', WTA_TEXT_DOMAIN ); ?><br>
							ℹ️ <?php esc_html_e( 'OpenAI Tier 5: 10,000 RPM limit. 6 concurrent = ~80 API calls/min (safe).', WTA_TEXT_DOMAIN ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Current Active Setting', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<?php
						$test_mode = get_option( 'wta_test_mode', 0 );
						$active_concurrent = $test_mode 
							? get_option( 'wta_concurrent_batches_test', 12 )
							: get_option( 'wta_concurrent_batches_normal', 6 );
						?>
						<strong><?php echo intval( $active_concurrent ); ?> concurrent batches</strong>
						<span style="color: <?php echo $test_mode ? '#2271b1' : '#d63638'; ?>;">
							(<?php echo $test_mode ? 'Test Mode' : 'Normal Mode'; ?>)
						</span>
						<p class="description">
							<?php esc_html_e( 'Dynamically switches based on Test Mode setting in AI Settings.', WTA_TEXT_DOMAIN ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Time Limit', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<strong>180 seconds (3 minutes) per runner</strong>
						<p class="description">
							<?php esc_html_e( 'Enough time to process large batches while respecting API rate limits', WTA_TEXT_DOMAIN ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'API Rate Limits', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<strong><?php esc_html_e( 'Respected:', WTA_TEXT_DOMAIN ); ?></strong><br>
						• <?php esc_html_e( 'Wikidata: ~5 req/s per processor (200 req/s limit)', WTA_TEXT_DOMAIN ); ?><br>
						• <?php esc_html_e( 'TimeZoneDB: ~0.4 req/s per processor (1 req/s FREE limit)', WTA_TEXT_DOMAIN ); ?><br>
						• <?php esc_html_e( 'OpenAI: Test mode = 0 requests, AI mode = monitored', WTA_TEXT_DOMAIN ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Expected Performance', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<strong><?php esc_html_e( 'Test Mode:', WTA_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( '~600 cities per minute', WTA_TEXT_DOMAIN ); ?><br>
						<strong><?php esc_html_e( 'AI Mode:', WTA_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( '~200-400 cities per minute (depends on OpenAI)', WTA_TEXT_DOMAIN ); ?>
					</td>
				</tr>
			</table>
		</div>

		<!-- Import Configuration -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Import Configuration', WTA_TEXT_DOMAIN ); ?></h2>
			
			<div id="wta-import-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Import Mode', WTA_TEXT_DOMAIN ); ?>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text">
									<span><?php esc_html_e( 'Import Mode', WTA_TEXT_DOMAIN ); ?></span>
								</legend>
								<label style="margin-bottom: 10px; display: block;">
									<input type="radio" name="import_mode" value="continents" id="mode_continents" checked />
									<strong><?php esc_html_e( 'Import by Continents', WTA_TEXT_DOMAIN ); ?></strong>
								</label>
								<div id="continent_selector" style="margin-left: 25px; margin-bottom: 15px;">
								<?php foreach ( $continents as $code => $name ) : ?>
								<label>
									<input type="checkbox" name="continents[]" value="<?php echo esc_attr( $code ); ?>" 
										<?php checked( in_array( $code, $selected_continents, true ) ); ?> />
									<?php echo esc_html( $name ); ?>
								</label><br />
								<?php endforeach; ?>
								<p class="description">
									<?php esc_html_e( 'Leave all unchecked to import all continents', WTA_TEXT_DOMAIN ); ?>
								</p>
								</div>
								
								<label style="margin-bottom: 10px; display: block;">
									<input type="radio" name="import_mode" value="countries" id="mode_countries" />
									<strong><?php esc_html_e( '🚀 Quick Test: Select Specific Countries', WTA_TEXT_DOMAIN ); ?></strong>
								</label>
								<div id="country_selector" style="margin-left: 25px; display: none;">
									<p class="description" style="margin-bottom: 10px;">
										<?php esc_html_e( 'Perfect for testing! Select only specific countries (e.g., Denmark).', WTA_TEXT_DOMAIN ); ?>
									</p>
								<select id="country_select" name="countries[]" multiple style="width: 100%; height: 150px;">
									<?php
									$countries = WTA_GeoNames_Parser::parse_countryInfo(); // v3.0.0 - using GeoNames
									if ( $countries ) {
										// Group by continent for better UX
											$by_continent = array();
											foreach ( $countries as $country ) {
												// v3.0.15: Fixed - GeoNames returns 'continent' not 'region'
												$continent = isset( $country['continent'] ) ? $country['continent'] : 'Other';
												if ( ! isset( $by_continent[ $continent ] ) ) {
													$by_continent[ $continent ] = array();
												}
												$by_continent[ $continent ][] = $country;
											}
											ksort( $by_continent );
											
											foreach ( $by_continent as $continent => $continent_countries ) {
												echo '<optgroup label="' . esc_attr( $continent ) . '">';
												foreach ( $continent_countries as $country ) {
													printf(
														'<option value="%s">%s</option>',
														esc_attr( $country['iso2'] ),
														esc_html( $country['name'] )
													);
												}
												echo '</optgroup>';
											}
										}
										?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple countries.', WTA_TEXT_DOMAIN ); ?>
									</p>
								</div>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="min_population">
								<?php esc_html_e( 'Minimum Population', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="number" id="min_population" name="min_population" 
								value="<?php echo esc_attr( $min_population ); ?>" min="0" step="1000" />
							<p class="description">
								<?php esc_html_e( 'Filter cities by minimum population (0 = no filter). Note: Not all cities have population data.', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="max_cities">
								<?php esc_html_e( 'Max Cities per Country', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="number" id="max_cities" name="max_cities" 
								value="<?php echo esc_attr( $max_cities ); ?>" min="0" step="1" />
							<p class="description">
								<?php esc_html_e( 'Limit number of cities per country (0 = no limit)', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Clear Existing Data', WTA_TEXT_DOMAIN ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" id="clear_existing" name="clear_existing" value="1" />
								<?php esc_html_e( 'Clear queue before import', WTA_TEXT_DOMAIN ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Check this to clear the existing queue. Already imported posts will be skipped automatically.', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" id="wta-prepare-import" class="button button-primary button-large">
						<?php esc_html_e( 'Prepare Import Queue', WTA_TEXT_DOMAIN ); ?>
					</button>
				</p>

				<div id="wta-import-result" style="display: none;"></div>
			</div>
		</div>

		<!-- Import Instructions -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Import Process', WTA_TEXT_DOMAIN ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Configure your data sources and import filters above', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Click "Prepare Import Queue" to fetch data and create queue items', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Cron jobs will automatically process the queue in the background', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Monitor progress on the Dashboard', WTA_TEXT_DOMAIN ); ?></li>
			</ol>
			<p>
				<strong><?php esc_html_e( 'Processing order:', WTA_TEXT_DOMAIN ); ?></strong>
			</p>
			<ol>
				<li><?php esc_html_e( 'Structure import (continents → countries → cities)', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Timezone resolution (for complex countries)', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'AI content generation', WTA_TEXT_DOMAIN ); ?></li>
			</ol>
			<p class="description">
				<?php esc_html_e( 'Each cron job runs every 5 minutes and processes items in batches to avoid timeouts.', WTA_TEXT_DOMAIN ); ?>
			</p>
		</div>
	</div>
</div>





