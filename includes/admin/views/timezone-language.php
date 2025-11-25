<?php
/**
 * Timezone & Language Settings view
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$timezonedb_key = get_option( 'wta_timezonedb_api_key' );
$has_tz_key = ! empty( $timezonedb_key );
$complex_countries = get_option( 'wta_complex_countries', array() );
?>

<div class="wrap wta-admin-wrap">
	<h1><?php esc_html_e( 'Timezone & Language Settings', WTA_TEXT_DOMAIN ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'wta_settings_group' ); ?>
		
		<div class="wta-admin-grid">
			<!-- Base Country & Language -->
			<div class="wta-card wta-card-wide">
				<h2><?php esc_html_e( 'Base Country & Language', WTA_TEXT_DOMAIN ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'All generated content will be in this language, and time differences will be calculated relative to this timezone.', WTA_TEXT_DOMAIN ); ?>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_base_country_name">
								<?php esc_html_e( 'Base Country Name', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="text" id="wta_base_country_name" name="wta_base_country_name" 
								value="<?php echo esc_attr( get_option( 'wta_base_country_name' ) ); ?>" 
								class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Example: Denmark, United States, etc.', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_base_timezone">
								<?php esc_html_e( 'Base Timezone', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="text" id="wta_base_timezone" name="wta_base_timezone" 
								value="<?php echo esc_attr( get_option( 'wta_base_timezone' ) ); ?>" 
								class="regular-text" 
								placeholder="Europe/Copenhagen" />
							<p class="description">
								<?php esc_html_e( 'IANA timezone identifier (e.g., Europe/Copenhagen, America/New_York)', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_base_language">
								<?php esc_html_e( 'Base Language', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="text" id="wta_base_language" name="wta_base_language" 
								value="<?php echo esc_attr( get_option( 'wta_base_language' ) ); ?>" 
								class="regular-text" 
								placeholder="da-DK" />
							<p class="description">
								<?php esc_html_e( 'Language code (e.g., da-DK, en-US, de-DE)', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_base_language_description">
								<?php esc_html_e( 'Language Style Description', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<textarea id="wta_base_language_description" name="wta_base_language_description" 
								class="large-text" rows="3"><?php echo esc_textarea( get_option( 'wta_base_language_description' ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Instructions for AI about writing style (e.g., "Write in fluent Danish for Danish users")', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- TimeZoneDB API -->
			<div class="wta-card wta-card-wide">
				<h2><?php esc_html_e( 'TimeZoneDB API', WTA_TEXT_DOMAIN ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Required for resolving timezones in complex countries (USA, Canada, Brazil, etc.)', WTA_TEXT_DOMAIN ); ?>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_timezonedb_api_key">
								<?php esc_html_e( 'API Key', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="text" id="wta_timezonedb_api_key" name="wta_timezonedb_api_key" 
								value="<?php echo esc_attr( $timezonedb_key ); ?>" 
								class="regular-text" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: TimeZoneDB URL */
									esc_html__( 'Get a free API key from %s', WTA_TEXT_DOMAIN ),
									'<a href="https://timezonedb.com/api" target="_blank">TimeZoneDB</a>'
								);
								?>
							</p>
							<?php if ( $has_tz_key ) : ?>
							<p>
								<button type="button" id="wta-test-timezonedb-api" class="button">
									<?php esc_html_e( 'Test API Connection', WTA_TEXT_DOMAIN ); ?>
								</button>
								<span id="wta-test-timezonedb-result"></span>
							</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Complex Countries -->
			<div class="wta-card wta-card-wide">
				<h2><?php esc_html_e( 'Complex Countries', WTA_TEXT_DOMAIN ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Countries that require individual city timezone lookups via TimeZoneDB API. Other countries will use a default timezone.', WTA_TEXT_DOMAIN ); ?>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_complex_countries_text">
								<?php esc_html_e( 'Country List', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<textarea id="wta_complex_countries_text" name="wta_complex_countries" 
								class="large-text code" rows="10"><?php
								foreach ( $complex_countries as $code => $name ) {
									echo esc_html( $code . ':' . $name ) . "\n";
								}
							?></textarea>
							<p class="description">
								<?php esc_html_e( 'Format: ISO2:Country Name (one per line). Example: US:United States', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Save Timezone & Language Settings', WTA_TEXT_DOMAIN ), 'primary', 'submit', true, array( 'style' => 'margin-top: 20px;' ) ); ?>
	</form>
</div>





