<?php
/**
 * Prompts admin page.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission
if ( isset( $_POST['submit'] ) && check_admin_referer( 'wta_prompts' ) ) {
	$prompt_types = array( 'translate_name', 'city_title', 'city_content', 'country_title', 'country_content', 'continent_title', 'continent_content', 'yoast_title', 'yoast_desc' );
	
	foreach ( $prompt_types as $type ) {
		update_option( "wta_prompt_{$type}_system", sanitize_textarea_field( $_POST["wta_prompt_{$type}_system"] ) );
		update_option( "wta_prompt_{$type}_user", sanitize_textarea_field( $_POST["wta_prompt_{$type}_user"] ) );
	}
	
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Prompts saved.', WTA_TEXT_DOMAIN ) . '</p></div>';
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'AI Prompts', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-admin-page">
		<div class="wta-card">
			<p><?php esc_html_e( 'Customize AI prompts for content generation. Available variables:', WTA_TEXT_DOMAIN ); ?></p>
			<ul>
				<li><code>{location_name}</code> - <?php esc_html_e( 'Original English name', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{location_name_local}</code> - <?php esc_html_e( 'Danish translated name', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{location_type}</code> - <?php esc_html_e( 'Type: city, country, or continent', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{timezone}</code> - <?php esc_html_e( 'IANA timezone', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{base_language}</code> - <?php esc_html_e( 'Target language (da-DK)', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{base_language_description}</code> - <?php esc_html_e( 'Language instruction', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{country_name}</code> - <?php esc_html_e( 'Parent country name', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{continent_name}</code> - <?php esc_html_e( 'Parent continent name', WTA_TEXT_DOMAIN ); ?></li>
			</ul>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'wta_prompts' ); ?>

			<!-- City Content -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'City Page Content', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wta_prompt_city_content_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_city_content_system" name="wta_prompt_city_content_system" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_city_content_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_city_content_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_city_content_user" name="wta_prompt_city_content_user" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_city_content_user' ) ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>

			<!-- Country Content -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Country Page Content', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wta_prompt_country_content_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_country_content_system" name="wta_prompt_country_content_system" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_country_content_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_country_content_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_country_content_user" name="wta_prompt_country_content_user" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_country_content_user' ) ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>

			<!-- Continent Content -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Continent Page Content', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wta_prompt_continent_content_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_continent_content_system" name="wta_prompt_continent_content_system" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_content_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_continent_content_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_continent_content_user" name="wta_prompt_continent_content_user" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_content_user' ) ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>

			<!-- Yoast SEO -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Yoast SEO Meta', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wta_prompt_yoast_title_system"><?php esc_html_e( 'Title System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_yoast_title_system" name="wta_prompt_yoast_title_system" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_yoast_title_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_yoast_title_user"><?php esc_html_e( 'Title User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_yoast_title_user" name="wta_prompt_yoast_title_user" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_yoast_title_user' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_yoast_desc_system"><?php esc_html_e( 'Description System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_yoast_desc_system" name="wta_prompt_yoast_desc_system" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_yoast_desc_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_yoast_desc_user"><?php esc_html_e( 'Description User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_yoast_desc_user" name="wta_prompt_yoast_desc_user" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_yoast_desc_user' ) ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save All Prompts', WTA_TEXT_DOMAIN ); ?>">
			</p>
		</form>
	</div>
</div>


