<?php
/**
 * Prompts Editor view
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prompt_ids = WTA_Prompt_Manager::get_prompt_ids();
$prompt_labels = array(
	'translate_location_name' => __( 'Location Name Translation', WTA_TEXT_DOMAIN ),
	'city_page_title'         => __( 'City Page Title', WTA_TEXT_DOMAIN ),
	'city_page_content'       => __( 'City Page Content', WTA_TEXT_DOMAIN ),
	'country_page_title'      => __( 'Country Page Title', WTA_TEXT_DOMAIN ),
	'country_page_content'    => __( 'Country Page Content', WTA_TEXT_DOMAIN ),
	'continent_page_title'    => __( 'Continent Page Title', WTA_TEXT_DOMAIN ),
	'continent_page_content'  => __( 'Continent Page Content', WTA_TEXT_DOMAIN ),
	'yoast_seo_title'         => __( 'Yoast SEO Title', WTA_TEXT_DOMAIN ),
	'yoast_meta_description'  => __( 'Yoast Meta Description', WTA_TEXT_DOMAIN ),
);
?>

<div class="wrap wta-admin-wrap">
	<h1><?php esc_html_e( 'AI Prompts Editor', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-prompts-info">
		<h2><?php esc_html_e( 'Available Variables', WTA_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'You can use these variables in your prompts. They will be automatically replaced with actual values:', WTA_TEXT_DOMAIN ); ?></p>
		<ul class="wta-variables-list">
			<li><code>{location_name}</code> - <?php esc_html_e( 'Original location name from database', WTA_TEXT_DOMAIN ); ?></li>
			<li><code>{location_name_local}</code> - <?php esc_html_e( 'Translated location name', WTA_TEXT_DOMAIN ); ?></li>
			<li><code>{location_type}</code> - <?php esc_html_e( 'Type: continent, country, or city', WTA_TEXT_DOMAIN ); ?></li>
			<li><code>{country_name}</code> - <?php esc_html_e( 'Country name', WTA_TEXT_DOMAIN ); ?></li>
			<li><code>{continent_name}</code> - <?php esc_html_e( 'Continent name', WTA_TEXT_DOMAIN ); ?></li>
			<li><code>{timezone}</code> - <?php esc_html_e( 'IANA timezone identifier', WTA_TEXT_DOMAIN ); ?></li>
			<li><code>{base_language}</code> - <?php esc_html_e( 'Target language code', WTA_TEXT_DOMAIN ); ?></li>
			<li><code>{base_language_description}</code> - <?php esc_html_e( 'Language style instructions', WTA_TEXT_DOMAIN ); ?></li>
			<li><code>{base_country_name}</code> - <?php esc_html_e( 'Base country name', WTA_TEXT_DOMAIN ); ?></li>
		</ul>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'wta_prompts_group' ); ?>
		
		<div class="wta-prompts-container">
			<?php foreach ( $prompt_ids as $prompt_id ) : ?>
			<div class="wta-prompt-card">
				<h2><?php echo esc_html( $prompt_labels[ $prompt_id ] ); ?></h2>
				
				<div class="wta-prompt-section">
					<h3><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Defines the AI\'s role and behavior', WTA_TEXT_DOMAIN ); ?>
					</p>
					<textarea name="wta_prompt_<?php echo esc_attr( $prompt_id ); ?>_system" 
						class="large-text code wta-prompt-textarea" 
						rows="3"><?php echo esc_textarea( get_option( "wta_prompt_{$prompt_id}_system" ) ); ?></textarea>
				</div>

				<div class="wta-prompt-section">
					<h3><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Specific instructions and context for content generation', WTA_TEXT_DOMAIN ); ?>
					</p>
					<textarea name="wta_prompt_<?php echo esc_attr( $prompt_id ); ?>_user" 
						class="large-text code wta-prompt-textarea" 
						rows="12"><?php echo esc_textarea( get_option( "wta_prompt_{$prompt_id}_user" ) ); ?></textarea>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<?php submit_button( __( 'Save All Prompts', WTA_TEXT_DOMAIN ), 'primary', 'submit', true, array( 'style' => 'margin-top: 20px;' ) ); ?>
	</form>
</div>





