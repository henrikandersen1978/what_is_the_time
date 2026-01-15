<?php
/**
 * Timezone & Language admin page.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WTA_Activator class if not already loaded
if ( ! class_exists( 'WTA_Activator' ) ) {
	require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'class-wta-activator.php';
}

// Handle Load Language Defaults action
if ( isset( $_GET['action'] ) && $_GET['action'] === 'load_language_defaults' && isset( $_GET['lang'] ) && check_admin_referer( 'wta_load_language_' . sanitize_text_field( $_GET['lang'] ) ) ) {
	$lang = sanitize_text_field( $_GET['lang'] );
	WTA_Activator::load_language_defaults( $lang );
	echo '<div class="notice notice-success"><p>✅ ' . sprintf( esc_html__( 'Language defaults loaded for %s', WTA_TEXT_DOMAIN ), $lang ) . '</p></div>';
}

// Handle form submission
if ( isset( $_POST['submit'] ) && check_admin_referer( 'wta_timezone_language' ) ) {
	update_option( 'wta_timezonedb_api_key', sanitize_text_field( $_POST['wta_timezonedb_api_key'] ) );
	update_option( 'wta_site_language', sanitize_text_field( $_POST['wta_site_language'] ) );
	update_option( 'wta_base_country_name', sanitize_text_field( $_POST['wta_base_country_name'] ) );
	update_option( 'wta_base_timezone', sanitize_text_field( $_POST['wta_base_timezone'] ) );
	update_option( 'wta_base_language', sanitize_text_field( $_POST['wta_base_language'] ) );
	update_option( 'wta_base_language_description', sanitize_textarea_field( $_POST['wta_base_language_description'] ) );
	update_option( 'wta_complex_countries', sanitize_text_field( $_POST['wta_complex_countries'] ) );
	
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', WTA_TEXT_DOMAIN ) . '</p></div>';
}

$timezonedb_key = get_option( 'wta_timezonedb_api_key', '' );
$site_language = get_option( 'wta_site_language', 'da' );
$base_country = get_option( 'wta_base_country_name', 'Danmark' );
$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
$base_language = get_option( 'wta_base_language', 'da-DK' );
$base_language_desc = get_option( 'wta_base_language_description', 'Skriv på flydende dansk til danske brugere' );
$complex_countries = get_option( 'wta_complex_countries', 'US,CA,BR,RU,AU,MX,ID,CN,KZ,AR,GL,CD,SA,CL' );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Timezone & Language', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-admin-page">
		<form method="post" action="">
			<?php wp_nonce_field( 'wta_timezone_language' ); ?>

			<!-- TimeZoneDB API -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'TimeZoneDB API', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_timezonedb_api_key"><?php esc_html_e( 'API Key', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="password" id="wta_timezonedb_api_key" name="wta_timezonedb_api_key" value="<?php echo esc_attr( $timezonedb_key ); ?>" class="regular-text">
							<p class="description">
								<?php
								printf(
									/* translators: %s: TimeZoneDB URL */
									esc_html__( 'Get your free API key from %s', WTA_TEXT_DOMAIN ),
									'<a href="https://timezonedb.com/api" target="_blank">TimeZoneDB</a>'
								);
								?>
							</p>
							<p>
								<button type="button" class="button" id="wta-test-timezonedb"><?php esc_html_e( 'Test Connection', WTA_TEXT_DOMAIN ); ?></button>
								<span class="spinner"></span>
							</p>
							<div id="wta-timezone-test-result"></div>
						</td>
					</tr>
				</table>
			</div>

			<!-- Site Language -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Site Language / Webstedssprog', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_site_language"><?php esc_html_e( 'Language', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
						<select id="wta_site_language" name="wta_site_language" class="regular-text">
							<option value="da" <?php selected( $site_language, 'da' ); ?>>🇩🇰 Dansk</option>
							<option value="de" <?php selected( $site_language, 'de' ); ?>>🇩🇪 Deutsch</option>
							<option value="en" <?php selected( $site_language, 'en' ); ?>>🇬🇧 English (UK)</option>
							<option value="us" <?php selected( $site_language, 'us' ); ?>>🇺🇸 English (US)</option>
							<option value="es" <?php selected( $site_language, 'es' ); ?>>🇪🇸 Español</option>
							<option value="fr" <?php selected( $site_language, 'fr' ); ?>>🇫🇷 Français</option>
							<option value="it" <?php selected( $site_language, 'it' ); ?>>🇮🇹 Italiano</option>
							<option value="nl" <?php selected( $site_language, 'nl' ); ?>>🇳🇱 Nederlands</option>
							<option value="pl" <?php selected( $site_language, 'pl' ); ?>>🇵🇱 Polski</option>
							<option value="pt" <?php selected( $site_language, 'pt' ); ?>>🇵🇹 Português</option>
							<option value="sv" <?php selected( $site_language, 'sv' ); ?>>🇸🇪 Svenska</option>
						</select>
							<p class="description"><?php esc_html_e( 'Select your site language for AI prompts and FAQ strings.', WTA_TEXT_DOMAIN ); ?></p>
							<p class="description"><strong><?php echo sprintf( esc_html__( 'Current language: %s', WTA_TEXT_DOMAIN ), $site_language ); ?></strong></p>
							<p>
								<?php
								$load_lang_nonce = wp_create_nonce( 'wta_load_language_' . $site_language );
								$load_lang_url = add_query_arg(
									array(
										'page'   => 'wta-timezone-language',
										'action' => 'load_language_defaults',
										'lang'   => $site_language,
										'_wpnonce' => $load_lang_nonce,
									),
									admin_url( 'admin.php' )
								);
								?>
								<a href="<?php echo esc_url( $load_lang_url ); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js( sprintf( __( 'Load default prompts and FAQ strings for %s? This will overwrite your AI Prompts settings.', WTA_TEXT_DOMAIN ), $site_language ) ); ?>');">
									🔄 <?php echo sprintf( esc_html__( 'Load Default Prompts for %s', WTA_TEXT_DOMAIN ), strtoupper( $site_language ) ); ?>
								</a>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Base Settings -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Base Settings', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_base_country_name"><?php esc_html_e( 'Base Country Name', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" id="wta_base_country_name" name="wta_base_country_name" value="<?php echo esc_attr( $base_country ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Your home country for time difference calculations.', WTA_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_base_timezone"><?php esc_html_e( 'Base Timezone', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<select id="wta_base_timezone" name="wta_base_timezone" class="regular-text">
								<?php
								$timezones = DateTimeZone::listIdentifiers();
								foreach ( $timezones as $tz ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $tz ),
										selected( $base_timezone, $tz, false ),
										esc_html( $tz )
									);
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Your home timezone.', WTA_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_base_language"><?php esc_html_e( 'Base Language', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" id="wta_base_language" name="wta_base_language" value="<?php echo esc_attr( $base_language ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Language code (e.g., da-DK, en-US).', WTA_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_base_language_description"><?php esc_html_e( 'Language Description', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<textarea id="wta_base_language_description" name="wta_base_language_description" rows="3" class="large-text"><?php echo esc_textarea( $base_language_desc ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Instruction for AI on how to write in your language.', WTA_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Complex Countries -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Complex Countries', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_complex_countries"><?php esc_html_e( 'Country Codes', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" id="wta_complex_countries" name="wta_complex_countries" value="<?php echo esc_attr( $complex_countries ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'Comma-separated ISO2 country codes requiring API timezone lookup (have multiple timezones).', WTA_TEXT_DOMAIN ); ?></p>
							<p class="description"><strong><?php esc_html_e( 'Default:', WTA_TEXT_DOMAIN ); ?></strong> US,CA,BR,RU,AU,MX,ID,CN,KZ,AR,GL,CD,SA,CL</p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', WTA_TEXT_DOMAIN ); ?>">
			</p>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#wta-test-timezonedb').on('click', function() {
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-timezone-test-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_test_timezonedb_connection',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + ' (Timezone: ' + response.data.timezone + ')</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
			error: function() {
				$result.html('<div class="notice notice-error"><p>❌ AJAX request failed</p></div>');
			},
			complete: function() {
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	});
});
</script>


