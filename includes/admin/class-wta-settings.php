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
		
		// Background processing interval
		register_setting( 'wta_data_import_settings_group', 'wta_cron_interval', array( 
			'sanitize_callback' => 'intval',
			'default' => 60
		) );

		// Concurrent processing settings (v3.0.41)
		register_setting( 'wta_data_import_settings_group', 'wta_concurrent_test_mode', array( 
			'sanitize_callback' => 'intval',
			'default' => 10
		) );
		register_setting( 'wta_data_import_settings_group', 'wta_concurrent_normal_mode', array( 
			'sanitize_callback' => 'intval',
			'default' => 5
		) );
	register_setting( 'wta_data_import_settings_group', 'wta_concurrent_structure', array( 
		'sanitize_callback' => 'intval',
		'default' => 2
	) );

	// City processing toggle (v3.0.72)
	register_setting( 'wta_data_import_settings_group', 'wta_enable_city_processing', array( 
		'sanitize_callback' => array( $this, 'sanitize_city_processing_toggle' ),
		'default' => '0'
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

	/**
	 * Sanitize city processing toggle and trigger bulk processing if enabled.
	 *
	 * @since 3.0.72
	 * @param string $value The toggle value ('1' or '0').
	 * @return string Sanitized value.
	 */
	public function sanitize_city_processing_toggle( $value ) {
		$old_value = get_option( 'wta_enable_city_processing', '0' );
		$new_value = $value === '1' ? '1' : '0';
		
		// If toggle was just enabled (0 → 1), start processing waiting cities
		if ( $old_value === '0' && $new_value === '1' ) {
			// Schedule bulk processing job (runs immediately)
			as_schedule_single_action(
				time(),
				'wta_start_waiting_city_processing',
				array(),
				'wta_coordinator'
			);
			
			WTA_Logger::info( '🚀 City processing toggle enabled - bulk processing scheduled' );
			
			// Add admin notice
			add_settings_error(
				'wta_enable_city_processing',
				'city_processing_started',
				__( '✅ City processing enabled! Waiting cities will start processing immediately. Check Action Scheduler to monitor progress.', 'world-time-ai' ),
				'success'
			);
		} elseif ( $old_value === '1' && $new_value === '0' ) {
			// Toggle disabled
			WTA_Logger::info( '⛔ City processing toggle disabled - new cities will wait' );
			
			add_settings_error(
				'wta_enable_city_processing',
				'city_processing_disabled',
				__( '⛔ City processing disabled. New cities will be created but NOT processed until you re-enable this toggle.', 'world-time-ai' ),
				'warning'
			);
		}
		
		return $new_value;
	}
}


