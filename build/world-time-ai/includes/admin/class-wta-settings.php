<?php
/**
 * Settings API registration.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin
 */

/**
 * Settings class.
 *
 * @since 1.0.0
 */
class WTA_Settings {

	/**
	 * Register all settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// GitHub URLs
		register_setting( 'wta_settings_group', 'wta_github_countries_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'wta_settings_group', 'wta_github_states_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'wta_settings_group', 'wta_github_cities_url', array( 'sanitize_callback' => 'esc_url_raw' ) );

		// TimeZoneDB
		register_setting( 'wta_settings_group', 'wta_timezonedb_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_settings_group', 'wta_complex_countries', array( 'sanitize_callback' => array( $this, 'sanitize_complex_countries' ) ) );

		// Base settings
		register_setting( 'wta_settings_group', 'wta_base_country_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_settings_group', 'wta_base_timezone', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_settings_group', 'wta_base_language', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_settings_group', 'wta_base_language_description', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );

		// OpenAI
		register_setting( 'wta_settings_group', 'wta_openai_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_settings_group', 'wta_openai_model', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wta_settings_group', 'wta_openai_temperature', array( 'sanitize_callback' => 'floatval' ) );
		register_setting( 'wta_settings_group', 'wta_openai_max_tokens', array( 'sanitize_callback' => 'intval' ) );

		// Import filters
		register_setting( 'wta_settings_group', 'wta_selected_continents', array( 'sanitize_callback' => array( $this, 'sanitize_array' ) ) );
		register_setting( 'wta_settings_group', 'wta_min_population', array( 'sanitize_callback' => 'intval' ) );
		register_setting( 'wta_settings_group', 'wta_max_cities_per_country', array( 'sanitize_callback' => 'intval' ) );

		// Yoast
		register_setting( 'wta_settings_group', 'wta_yoast_integration_enabled', array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		register_setting( 'wta_settings_group', 'wta_yoast_allow_overwrite', array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );

		// Register prompt settings
		$this->register_prompt_settings();
	}

	/**
	 * Register prompt settings.
	 *
	 * @since 1.0.0
	 */
	private function register_prompt_settings() {
		$prompt_ids = WTA_Prompt_Manager::get_prompt_ids();

		foreach ( $prompt_ids as $prompt_id ) {
			register_setting(
				'wta_prompts_group',
				"wta_prompt_{$prompt_id}_system",
				array( 'sanitize_callback' => 'sanitize_textarea_field' )
			);

			register_setting(
				'wta_prompts_group',
				"wta_prompt_{$prompt_id}_user",
				array( 'sanitize_callback' => 'sanitize_textarea_field' )
			);
		}
	}

	/**
	 * Sanitize array input.
	 *
	 * @since 1.0.0
	 * @param mixed $input Input value.
	 * @return array Sanitized array.
	 */
	public function sanitize_array( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $input );
	}

	/**
	 * Sanitize complex countries list.
	 *
	 * @since 1.0.0
	 * @param mixed $input Input value.
	 * @return array Sanitized array.
	 */
	public function sanitize_complex_countries( $input ) {
		if ( ! is_array( $input ) ) {
			// If it's a string, try to parse as JSON or textarea
			if ( is_string( $input ) ) {
				// Try JSON first
				$decoded = json_decode( $input, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$input = $decoded;
				} else {
					// Try parsing line by line (ISO2:Name format)
					$lines = explode( "\n", $input );
					$result = array();
					foreach ( $lines as $line ) {
						$line = trim( $line );
						if ( empty( $line ) ) {
							continue;
						}
						$parts = explode( ':', $line, 2 );
						if ( count( $parts ) === 2 ) {
							$code = sanitize_text_field( trim( $parts[0] ) );
							$name = sanitize_text_field( trim( $parts[1] ) );
							$result[ $code ] = $name;
						}
					}
					return $result;
				}
			} else {
				return array();
			}
		}

		$result = array();
		foreach ( $input as $code => $name ) {
			$code = sanitize_text_field( $code );
			$name = sanitize_text_field( $name );
			if ( ! empty( $code ) && ! empty( $name ) ) {
				$result[ $code ] = $name;
			}
		}

		return $result;
	}
}






