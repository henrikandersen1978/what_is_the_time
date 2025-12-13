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
		<!-- Data Sources -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Data Sources', WTA_TEXT_DOMAIN ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure GitHub URLs for JSON data files, or leave empty if using local files.', WTA_TEXT_DOMAIN ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wta_data_import_settings_group' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_github_countries_url">
								<?php esc_html_e( 'Countries URL', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="url" id="wta_github_countries_url" name="wta_github_countries_url" 
								value="<?php echo esc_attr( get_option( 'wta_github_countries_url' ) ); ?>" 
								class="large-text" />
			<p class="description">
				<?php 
				$upload_dir = wp_upload_dir();
				$countries_file = $upload_dir['basedir'] . '/world-time-ai-data/countries.json';
				if ( file_exists( $countries_file ) ) {
					$size = size_format( filesize( $countries_file ) );
					echo '✅ ' . sprintf( esc_html__( 'Local file exists (%s) - URL not needed', WTA_TEXT_DOMAIN ), $size );
				} else {
					esc_html_e( 'URL to countries.json file (optional if local file exists in wp-content/uploads/world-time-ai-data/)', WTA_TEXT_DOMAIN );
				}
				?>
			</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_github_states_url">
								<?php esc_html_e( 'States URL', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="url" id="wta_github_states_url" name="wta_github_states_url" 
								value="<?php echo esc_attr( get_option( 'wta_github_states_url' ) ); ?>" 
								class="large-text" />
			<p class="description">
				<?php 
				$upload_dir = wp_upload_dir();
				$states_file = $upload_dir['basedir'] . '/world-time-ai-data/states.json';
				if ( file_exists( $states_file ) ) {
					$size = size_format( filesize( $states_file ) );
					echo '✅ ' . sprintf( esc_html__( 'Local file exists (%s) - URL not needed', WTA_TEXT_DOMAIN ), $size );
				} else {
					esc_html_e( 'URL to states.json file (optional if local file exists in wp-content/uploads/world-time-ai-data/)', WTA_TEXT_DOMAIN );
				}
				?>
			</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_github_cities_url">
								<?php esc_html_e( 'Cities URL', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="url" id="wta_github_cities_url" name="wta_github_cities_url" 
								value="<?php echo esc_attr( get_option( 'wta_github_cities_url' ) ); ?>" 
								class="large-text" />
			<p class="description">
				<?php 
				$upload_dir = wp_upload_dir();
				$cities_file = $upload_dir['basedir'] . '/world-time-ai-data/cities.json';
				if ( file_exists( $cities_file ) ) {
					$size = size_format( filesize( $cities_file ) );
					echo '✅ ' . sprintf( esc_html__( 'Local file exists (%s) - URL not needed', WTA_TEXT_DOMAIN ), $size );
				} else {
					esc_html_e( 'URL to cities.json file (Note: cities.json is 185MB, local placement in wp-content/uploads/world-time-ai-data/ recommended)', WTA_TEXT_DOMAIN );
				}
				?>
			</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Data Sources', WTA_TEXT_DOMAIN ) ); ?>
			</form>
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
					<th scope="row"><?php esc_html_e( 'Concurrent Runners', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<strong>2</strong>
						<p class="description">
							<?php esc_html_e( 'Fixed at 2 concurrent runners (WP-Cron + occasional async).', WTA_TEXT_DOMAIN ); ?><br>
							<?php esc_html_e( 'Testing showed Action Scheduler\'s concurrent_batches is a GLOBAL limit, not per-runner.', WTA_TEXT_DOMAIN ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Batch Size', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<strong>300 actions per batch</strong>
						<p class="description">
							<?php esc_html_e( '2 runners × 300 batch = 600 actions per cycle (every minute)', WTA_TEXT_DOMAIN ); ?>
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
										$countries = WTA_Github_Fetcher::fetch_countries();
										if ( $countries ) {
											// Group by continent for better UX
											$by_continent = array();
											foreach ( $countries as $country ) {
												$continent = isset( $country['region'] ) ? $country['region'] : 'Other';
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





