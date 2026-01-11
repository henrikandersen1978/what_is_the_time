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

					<!-- Concurrent Processing Settings (v3.2.80 - Sequential Phases) -->
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Concurrent Processing', WTA_TEXT_DOMAIN ); ?>
						</th>
						<td>
							<table class="widefat" style="max-width: 700px;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Mode', WTA_TEXT_DOMAIN ); ?></th>
										<th><?php esc_html_e( 'Concurrent Queues', WTA_TEXT_DOMAIN ); ?></th>
										<th><?php esc_html_e( 'Recommended', WTA_TEXT_DOMAIN ); ?></th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>
											<strong><?php esc_html_e( 'Test Mode', WTA_TEXT_DOMAIN ); ?></strong>
											<br><span class="description"><?php esc_html_e( 'Template generation only (no API calls)', WTA_TEXT_DOMAIN ); ?></span>
										</td>
										<td>
											<input type="number" 
												name="wta_concurrent_test_mode" 
												value="<?php echo esc_attr( get_option( 'wta_concurrent_test_mode', 10 ) ); ?>" 
												min="1" 
												max="20" 
												class="small-text"
											/>
										</td>
										<td>
											<span style="color: #46b450;">✓ 10</span>
											<br><span class="description"><?php esc_html_e( 'High throughput', WTA_TEXT_DOMAIN ); ?></span>
										</td>
									</tr>
									<tr>
										<td>
											<strong><?php esc_html_e( 'Normal Mode', WTA_TEXT_DOMAIN ); ?></strong>
											<br><span class="description"><?php esc_html_e( 'Full import with API calls', WTA_TEXT_DOMAIN ); ?></span>
										</td>
										<td>
											<input type="number" 
												name="wta_concurrent_normal_mode" 
												value="<?php echo esc_attr( get_option( 'wta_concurrent_normal_mode', 10 ) ); ?>" 
												min="1" 
												max="20" 
												class="small-text"
											/>
										</td>
										<td>
											<span style="color: #46b450;">✓ 10</span>
											<br><span class="description"><?php esc_html_e( 'With TimezoneDB Premium (10 req/s)', WTA_TEXT_DOMAIN ); ?></span>
										</td>
									</tr>
								</tbody>
							</table>
							<p class="description" style="margin-top: 10px;">
								<strong><?php esc_html_e( 'Sequential Phases (v3.2.80):', WTA_TEXT_DOMAIN ); ?></strong>
								<?php esc_html_e( 'Import runs in 3 phases: Structure (continents/countries/cities) → Timezone (API lookups) → AI Content (OpenAI generation). This prevents different processor types from blocking each other.', WTA_TEXT_DOMAIN ); ?>
							</p>
							<p class="description">
								<strong><?php esc_html_e( 'TimezoneDB Premium recommended:', WTA_TEXT_DOMAIN ); ?></strong>
								<?php esc_html_e( 'Upgrade to Premium ($9.99/month) for 10 req/s (10x faster timezone resolution). FREE tier: 1 req/s.', WTA_TEXT_DOMAIN ); ?>
							</p>
							<p class="description" style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
								<strong>⚠️ <?php esc_html_e( 'Important:', WTA_TEXT_DOMAIN ); ?></strong>
								<?php esc_html_e( 'Higher concurrency requires more server resources (CPU, memory, database connections). Start conservative and increase gradually while monitoring performance.', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>

					<!-- TimezoneDB Premium Setting (v3.2.83) -->
					<tr>
						<th scope="row">
							<?php esc_html_e( 'TimezoneDB API Tier', WTA_TEXT_DOMAIN ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									name="wta_timezonedb_premium" 
									value="1" 
									<?php checked( get_option( 'wta_timezonedb_premium', false ), true ); ?>
								/>
								<?php esc_html_e( 'I have TimezoneDB Premium ($9.99/month)', WTA_TEXT_DOMAIN ); ?>
							</label>
							<p class="description">
								<strong><?php esc_html_e( 'FREE tier:', WTA_TEXT_DOMAIN ); ?></strong> 1 request/second (rate limiting enforced)<br>
								<strong><?php esc_html_e( 'Premium tier:', WTA_TEXT_DOMAIN ); ?></strong> 10 requests/second (no rate limiting, 10x faster!)<br>
								<br>
								<?php esc_html_e( 'Check this box if you have upgraded to Premium. This disables rate limiting and allows concurrent timezone resolution.', WTA_TEXT_DOMAIN ); ?>
								<br>
								<a href="https://timezonedb.com/pricing" target="_blank"><?php esc_html_e( 'Upgrade to Premium →', WTA_TEXT_DOMAIN ); ?></a>
							</p>
							<?php if ( get_option( 'wta_timezonedb_premium', false ) ): ?>
								<p style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;">
									<strong>✅ <?php esc_html_e( 'Premium Active!', WTA_TEXT_DOMAIN ); ?></strong><br>
									<?php esc_html_e( 'Rate limiting disabled. Timezone resolution will use full concurrent capacity (10 requests/second).', WTA_TEXT_DOMAIN ); ?>
								</p>
							<?php else: ?>
								<p style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
									<strong>⚠️ <?php esc_html_e( 'FREE tier active', WTA_TEXT_DOMAIN ); ?></strong><br>
									<?php esc_html_e( 'Rate limiting enforced (1 request/second). For 10,000 cities, timezone resolution will take ~3 hours. With Premium: ~17 minutes!', WTA_TEXT_DOMAIN ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<!-- City Processing Toggle (v3.0.72) -->
					<tr>
						<th scope="row">
							<?php esc_html_e( 'City Processing Control', WTA_TEXT_DOMAIN ); ?>
					</th>
					<td>
						<?php 
						global $wpdb;
						$processing_enabled = get_option( 'wta_enable_city_processing', '0' );
						$waiting_count = $wpdb->get_var(
								"SELECT COUNT(*) 
								 FROM {$wpdb->posts} p
								 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
								 WHERE p.post_type = 'world_time_location'
								 AND pm.meta_key = 'wta_timezone_status' 
								 AND pm.meta_value = 'waiting_for_toggle'"
							);
							?>
							<label>
								<input type="checkbox" 
									   name="wta_enable_city_processing" 
									   value="1" 
									   <?php checked( $processing_enabled, '1' ); ?> />
								<strong><?php esc_html_e( 'Enable City Processing (Timezone + AI Content)', WTA_TEXT_DOMAIN ); ?></strong>
							</label>
							
							<?php if ( $waiting_count > 0 ): ?>
							<p style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; margin-top: 10px;">
								<strong>⚠️ <?php echo number_format_i18n( $waiting_count ); ?> cities waiting for processing</strong>
								<br><?php esc_html_e( 'When you check this box and save, these cities will start processing (timezone lookups + AI content generation).', WTA_TEXT_DOMAIN ); ?>
							</p>
							<?php endif; ?>
							
							<p class="description" style="margin-top: 10px;">
								<strong><?php esc_html_e( 'How it works:', WTA_TEXT_DOMAIN ); ?></strong>
							</p>
							<ol class="description">
								<li><strong><?php esc_html_e( 'Import with toggle OFF:', WTA_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'City chunks schedule fast (~30-45 min for 150k cities)', WTA_TEXT_DOMAIN ); ?></li>
								<li><strong><?php esc_html_e( 'Cities created:', WTA_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'Draft posts created, but marked as "waiting_for_toggle"', WTA_TEXT_DOMAIN ); ?></li>
								<li><strong><?php esc_html_e( 'Check Action Scheduler:', WTA_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'When all chunks done (0 pending wta_schedule_cities)', WTA_TEXT_DOMAIN ); ?></li>
								<li><strong><?php esc_html_e( 'Enable toggle & save:', WTA_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'Waiting cities start processing immediately', WTA_TEXT_DOMAIN ); ?></li>
							</ol>
							
							<p class="description" style="padding: 10px; background: #d1ecf1; border-left: 4px solid #0073aa; margin-top: 10px;">
								<strong>💡 <?php esc_html_e( 'Why use this?', WTA_TEXT_DOMAIN ); ?></strong>
								<br><?php esc_html_e( 'Separates scheduling from processing. Test that chunking works correctly without wasting API credits or server resources. You have full control over when processing starts.', WTA_TEXT_DOMAIN ); ?>
							</p>
							
							<?php if ( $processing_enabled === '1' ): ?>
							<p style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; margin-top: 10px;">
								<strong>✅ <?php esc_html_e( 'City processing is currently ENABLED', WTA_TEXT_DOMAIN ); ?></strong>
								<br><?php esc_html_e( 'New cities will start processing immediately after creation.', WTA_TEXT_DOMAIN ); ?>
							</p>
							<?php else: ?>
							<p style="padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; margin-top: 10px;">
								<strong>⛔ <?php esc_html_e( 'City processing is currently DISABLED', WTA_TEXT_DOMAIN ); ?></strong>
								<br><?php esc_html_e( 'New cities will be created but NOT processed until you enable this toggle.', WTA_TEXT_DOMAIN ); ?>
							</p>
							<?php endif; ?>
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
				<th scope="row"><?php esc_html_e( 'Queue Runner Time Limit', WTA_TEXT_DOMAIN ); ?></th>
				<td>
					<strong>60 seconds per batch</strong>
					<p class="description">
						<?php esc_html_e( 'Time allocated for each Action Scheduler queue runner to process items.', WTA_TEXT_DOMAIN ); ?>
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





