<?php
/**
 * Shortcode Settings admin page.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission
if ( isset( $_POST['submit'] ) && check_admin_referer( 'wta_shortcode_settings' ) ) {
	update_option( 'wta_major_cities_count_continent', intval( $_POST['wta_major_cities_count_continent'] ) );
	update_option( 'wta_major_cities_count_country', intval( $_POST['wta_major_cities_count_country'] ) );
	update_option( 'wta_child_locations_limit', intval( $_POST['wta_child_locations_limit'] ) );
	update_option( 'wta_nearby_cities_count', intval( $_POST['wta_nearby_cities_count'] ) );
	update_option( 'wta_nearby_countries_count', intval( $_POST['wta_nearby_countries_count'] ) );
	update_option( 'wta_global_comparison_count', intval( $_POST['wta_global_comparison_count'] ) );
	
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Shortcode settings saved. Caches will be refreshed automatically.', WTA_TEXT_DOMAIN ) . '</p></div>';
}

// Get current values (with defaults matching current implementation)
$major_cities_continent = get_option( 'wta_major_cities_count_continent', 30 );
$major_cities_country = get_option( 'wta_major_cities_count_country', 50 );
$child_locations_limit = get_option( 'wta_child_locations_limit', 300 );
$nearby_cities_count = get_option( 'wta_nearby_cities_count', 120 );
$nearby_countries_count = get_option( 'wta_nearby_countries_count', 24 );
$global_comparison_count = get_option( 'wta_global_comparison_count', 24 );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Shortcode Settings', WTA_TEXT_DOMAIN ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Configure default display counts for shortcodes used throughout the site. Changes take effect immediately (caches auto-refresh).', WTA_TEXT_DOMAIN ); ?>
	</p>

	<div class="wta-admin-page">
		<form method="post" action="">
			<?php wp_nonce_field( 'wta_shortcode_settings' ); ?>
			
			<!-- Major Cities -->
			<div class="wta-card">
				<h2>🏙️ <?php esc_html_e( 'Major Cities Shortcode', WTA_TEXT_DOMAIN ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Used on continent and country pages to show live clocks for the largest cities.', WTA_TEXT_DOMAIN ); ?>
					<br><code>[wta_major_cities]</code>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_major_cities_count_continent"><?php esc_html_e( 'Cities on Continents', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="wta_major_cities_count_continent" name="wta_major_cities_count_continent" value="<?php echo esc_attr( $major_cities_continent ); ?>" min="1" max="100" class="small-text">
							<p class="description">
								<?php esc_html_e( 'How many cities to show on continent pages (e.g., Europa). Recommended: 24-30', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_major_cities_count_country"><?php esc_html_e( 'Cities on Countries', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="wta_major_cities_count_country" name="wta_major_cities_count_country" value="<?php echo esc_attr( $major_cities_country ); ?>" min="1" max="200" class="small-text">
							<p class="description">
								<?php esc_html_e( 'How many cities to show on country pages (e.g., Danmark). Recommended: 30-50', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
			
			<!-- Child Locations -->
			<div class="wta-card">
				<h2>📍 <?php esc_html_e( 'Child Locations Shortcode', WTA_TEXT_DOMAIN ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Shows all countries on continents, or top cities on country pages.', WTA_TEXT_DOMAIN ); ?>
					<br><code>[wta_child_locations]</code>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_child_locations_limit"><?php esc_html_e( 'Max Cities per Country', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="wta_child_locations_limit" name="wta_child_locations_limit" value="<?php echo esc_attr( $child_locations_limit ); ?>" min="1" max="1000" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Maximum cities to show on country pages (sorted by population). Countries on continents show ALL. Recommended: 300', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
			
			<!-- City-Specific Shortcodes -->
			<div class="wta-card">
				<h2>🌆 <?php esc_html_e( 'City Page Shortcodes', WTA_TEXT_DOMAIN ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Shortcodes used on individual city pages for related content.', WTA_TEXT_DOMAIN ); ?>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_nearby_cities_count"><?php esc_html_e( 'Nearby Cities', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="wta_nearby_cities_count" name="wta_nearby_cities_count" value="<?php echo esc_attr( $nearby_cities_count ); ?>" min="1" max="300" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Max cities in nearby cities section. Dynamically adjusts based on density. Recommended: 120', WTA_TEXT_DOMAIN ); ?>
								<br><code>[wta_nearby_cities]</code>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_nearby_countries_count"><?php esc_html_e( 'Nearby Countries', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="wta_nearby_countries_count" name="wta_nearby_countries_count" value="<?php echo esc_attr( $nearby_countries_count ); ?>" min="1" max="50" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Number of nearby countries to show (GPS-based distance). Recommended: 24', WTA_TEXT_DOMAIN ); ?>
								<br><code>[wta_nearby_countries]</code>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_global_comparison_count"><?php esc_html_e( 'Global Time Comparison', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="wta_global_comparison_count" name="wta_global_comparison_count" value="<?php echo esc_attr( $global_comparison_count ); ?>" min="1" max="50" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Number of global cities to show in time comparison table. Recommended: 24', WTA_TEXT_DOMAIN ); ?>
								<br><code>[wta_global_time_comparison]</code>
							</p>
						</td>
					</tr>
				</table>
			</div>
			
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', WTA_TEXT_DOMAIN ); ?>">
			</p>
		</form>
		
		<!-- Info Box -->
		<div class="wta-card" style="background-color: #f0f6fc; border-left: 4px solid #0073aa;">
			<h3>ℹ️ <?php esc_html_e( 'About These Settings', WTA_TEXT_DOMAIN ); ?></h3>
			<ul style="margin-left: 20px;">
				<li><?php esc_html_e( 'All counts can be overridden per-shortcode using attributes (e.g., [wta_major_cities count="20"])', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Changes apply immediately - cached content refreshes automatically within 24 hours', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Higher counts = more internal links (better SEO) but slower page load', WTA_TEXT_DOMAIN ); ?></li>
				<li><?php esc_html_e( 'Some shortcodes use dynamic counts (e.g., nearby cities adjusts based on city density)', WTA_TEXT_DOMAIN ); ?></li>
			</ul>
		</div>
	</div>
</div>

