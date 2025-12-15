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
		
		// Performance Settings
		register_setting( 'wta_data_import_settings_group', 'wta_concurrent_batches', array( 
			'sanitize_callback' => 'absint',
			'default' => 10
		) );

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
		
		// Background processing interval
		register_setting( 'wta_data_import_settings_group', 'wta_cron_interval', array( 
			'sanitize_callback' => 'intval',
			'default' => 60
		) );

		// AI Prompts
		$prompt_types = array(
			'translate_name', 'city_title', 'city_content', 'country_title', 'country_content',
			// Country page template (6 sections)
			'country_intro', 'country_timezone', 'country_cities', 'country_weather', 'country_culture', 'country_travel',
			// Continent page template (5 sections)
			'continent_intro', 'continent_timezone', 'continent_cities', 'continent_geography', 'continent_facts',
			// City page template (6 sections)
			'city_intro', 'city_timezone', 'city_attractions', 'city_practical', 'city_nearby_cities', 'city_nearby_countries',
			'yoast_title', 'yoast_desc'
		);
		foreach ( $prompt_types as $type ) {
			register_setting( 'wta_prompts', "wta_prompt_{$type}_system", array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
			register_setting( 'wta_prompts', "wta_prompt_{$type}_user", array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		}
	}
}


