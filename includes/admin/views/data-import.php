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
		<!-- Upload JSON Files -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Upload JSON Files', WTA_TEXT_DOMAIN ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload JSON data files directly from your computer. Large files (185MB cities.json) are uploaded in chunks automatically.', WTA_TEXT_DOMAIN ); ?>
			</p>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wta_upload_countries">
							<?php esc_html_e( 'Countries File', WTA_TEXT_DOMAIN ); ?>
						</label>
					</th>
					<td>
						<input type="file" id="wta_upload_countries" accept=".json" />
						<button type="button" class="button wta-upload-btn" data-type="countries">
							<?php esc_html_e( 'Upload', WTA_TEXT_DOMAIN ); ?>
						</button>
						<span class="wta-upload-status" id="wta-countries-status"></span>
						<p class="description">
							<?php 
							$countries_file = WP_CONTENT_DIR . '/plugins/world-time-ai/json/countries.json';
							if ( file_exists( $countries_file ) ) {
								$size = size_format( filesize( $countries_file ) );
								echo '✅ ' . sprintf( esc_html__( 'File exists (%s)', WTA_TEXT_DOMAIN ), $size );
							} else {
								echo '❌ ' . esc_html__( 'Not uploaded yet', WTA_TEXT_DOMAIN );
							}
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wta_upload_states">
							<?php esc_html_e( 'States File', WTA_TEXT_DOMAIN ); ?>
						</label>
					</th>
					<td>
						<input type="file" id="wta_upload_states" accept=".json" />
						<button type="button" class="button wta-upload-btn" data-type="states">
							<?php esc_html_e( 'Upload', WTA_TEXT_DOMAIN ); ?>
						</button>
						<span class="wta-upload-status" id="wta-states-status"></span>
						<p class="description">
							<?php 
							$states_file = WP_CONTENT_DIR . '/plugins/world-time-ai/json/states.json';
							if ( file_exists( $states_file ) ) {
								$size = size_format( filesize( $states_file ) );
								echo '✅ ' . sprintf( esc_html__( 'File exists (%s)', WTA_TEXT_DOMAIN ), $size );
							} else {
								echo '❌ ' . esc_html__( 'Not uploaded yet', WTA_TEXT_DOMAIN );
							}
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wta_upload_cities">
							<?php esc_html_e( 'Cities File', WTA_TEXT_DOMAIN ); ?>
						</label>
					</th>
					<td>
						<input type="file" id="wta_upload_cities" accept=".json" />
						<button type="button" class="button wta-upload-btn" data-type="cities">
							<?php esc_html_e( 'Upload (Chunked)', WTA_TEXT_DOMAIN ); ?>
						</button>
						<span class="wta-upload-status" id="wta-cities-status"></span>
						<div class="wta-upload-progress" id="wta-cities-progress" style="display:none;">
							<div class="progress-bar" style="width: 0%"></div>
							<span class="progress-text">0%</span>
						</div>
						<p class="description">
							<?php 
							$cities_file = WP_CONTENT_DIR . '/plugins/world-time-ai/json/cities.json';
							if ( file_exists( $cities_file ) ) {
								$size = size_format( filesize( $cities_file ) );
								echo '✅ ' . sprintf( esc_html__( 'File exists (%s)', WTA_TEXT_DOMAIN ), $size );
							} else {
								echo '❌ ' . esc_html__( 'Not uploaded yet (will use chunked upload for large files)', WTA_TEXT_DOMAIN );
							}
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Data Sources (Optional) -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Data Sources (GitHub URLs - Optional)', WTA_TEXT_DOMAIN ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'These URLs are only used if you have not uploaded the JSON files above.', WTA_TEXT_DOMAIN ); ?>
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
								<?php esc_html_e( 'URL to countries.json file', WTA_TEXT_DOMAIN ); ?>
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
								<?php esc_html_e( 'URL to states.json file', WTA_TEXT_DOMAIN ); ?>
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
								<?php esc_html_e( 'URL to cities.json file (Note: cities.json is 185MB, upload recommended)', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Data Sources', WTA_TEXT_DOMAIN ) ); ?>
			</form>
		</div>

		<!-- Import Configuration -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Import Configuration', WTA_TEXT_DOMAIN ); ?></h2>
			
			<div id="wta-import-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Select Continents', WTA_TEXT_DOMAIN ); ?>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text">
									<span><?php esc_html_e( 'Select Continents', WTA_TEXT_DOMAIN ); ?></span>
								</legend>
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





