<?php
/**
 * Settings registration.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin
 */

class WTA_Settings {

	/**
	 * Register settings.
	 *
	 * @since    2.0.0
	 */
	public function register_settings() {
		// Data Import Settings
		register_setting( 'wta_data_import_settings_group', 'wta_github_countries_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'wta_data_import_settings_group', 'wta_github_states_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'wta_data_import_settings_group', 'wta_github_cities_url', array( 'sanitize_callback' => 'esc_url_raw' ) );

		// AI Settings
		register_setting( 'wta_ai_settings', 'wta_openai_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_ai_settings', 'wta_openai_model', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_ai_settings', 'wta_openai_temperature', array( 'sanitize_callback' => 'floatval' ) );
		register_setting( 'wta_ai_settings', 'wta_openai_max_tokens', array( 'sanitize_callback' => 'intval' ) );

		// Timezone & Language Settings
		register_setting( 'wta_timezone_language', 'wta_timezonedb_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_timezone_language', 'wta_base_country_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_timezone_language', 'wta_base_timezone', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_timezone_language', 'wta_base_language', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_timezone_language', 'wta_base_language_description', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'wta_timezone_language', 'wta_complex_countries', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// AI Prompts (9 prompts × 2 fields = 18 settings)
		$prompt_types = array( 'translate_name', 'city_title', 'city_content', 'country_title', 'country_content', 'continent_title', 'continent_content', 'yoast_title', 'yoast_desc' );
		foreach ( $prompt_types as $type ) {
			register_setting( 'wta_prompts', "wta_prompt_{$type}_system", array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
			register_setting( 'wta_prompts', "wta_prompt_{$type}_user", array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		}
	}
}


