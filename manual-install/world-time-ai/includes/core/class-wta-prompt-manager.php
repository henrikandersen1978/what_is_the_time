<?php
/**
 * Prompt template management.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

/**
 * Prompt manager class.
 *
 * @since 1.0.0
 */
class WTA_Prompt_Manager {

	/**
	 * Available prompt IDs.
	 *
	 * @var array
	 */
	private static $prompt_ids = array(
		'translate_location_name',
		'city_page_title',
		'city_page_content',
		'country_page_title',
		'country_page_content',
		'continent_page_title',
		'continent_page_content',
		'yoast_seo_title',
		'yoast_meta_description',
	);

	/**
	 * Get a prompt template.
	 *
	 * @since 1.0.0
	 * @param string $prompt_id Prompt identifier.
	 * @param string $type      Prompt type: 'system' or 'user'.
	 * @param array  $variables Variables to replace in the prompt.
	 * @return string|false Processed prompt or false if not found.
	 */
	public static function get_prompt( $prompt_id, $type = 'user', $variables = array() ) {
		if ( ! in_array( $prompt_id, self::$prompt_ids, true ) ) {
			WTA_Logger::error( 'Invalid prompt ID', array( 'prompt_id' => $prompt_id ) );
			return false;
		}

		if ( ! in_array( $type, array( 'system', 'user' ), true ) ) {
			WTA_Logger::error( 'Invalid prompt type', array( 'type' => $type ) );
			return false;
		}

		$option_name = "wta_prompt_{$prompt_id}_{$type}";
		$prompt = get_option( $option_name );

		if ( empty( $prompt ) ) {
			WTA_Logger::error( 'Prompt not found', array( 'option' => $option_name ) );
			return false;
		}

		// Replace variables
		if ( ! empty( $variables ) ) {
			$prompt = self::replace_variables( $prompt, $variables );
		}

		return $prompt;
	}

	/**
	 * Replace variables in prompt template.
	 *
	 * @since 1.0.0
	 * @param string $prompt    Prompt template.
	 * @param array  $variables Variables to replace.
	 * @return string Processed prompt.
	 */
	private static function replace_variables( $prompt, $variables ) {
		foreach ( $variables as $key => $value ) {
			$placeholder = '{' . $key . '}';
			$prompt = str_replace( $placeholder, $value, $prompt );
		}

		return $prompt;
	}

	/**
	 * Get all available prompt IDs.
	 *
	 * @since 1.0.0
	 * @return array Array of prompt IDs.
	 */
	public static function get_prompt_ids() {
		return self::$prompt_ids;
	}

	/**
	 * Get prompt variables for a specific type.
	 *
	 * @since 1.0.0
	 * @param string $location_type Location type: 'continent', 'country', 'city'.
	 * @return array Available variables.
	 */
	public static function get_available_variables( $location_type = 'city' ) {
		$base_variables = array(
			'base_language'             => get_option( 'wta_base_language', 'en-US' ),
			'base_language_description' => get_option( 'wta_base_language_description', '' ),
			'base_country_name'         => get_option( 'wta_base_country_name', '' ),
		);

		$location_variables = array(
			'location_type' => $location_type,
		);

		switch ( $location_type ) {
			case 'continent':
				$location_variables = array_merge( $location_variables, array(
					'location_name'       => '',
					'location_name_local' => '',
				) );
				break;

			case 'country':
				$location_variables = array_merge( $location_variables, array(
					'location_name'       => '',
					'location_name_local' => '',
					'continent_name'      => '',
				) );
				break;

			case 'city':
				$location_variables = array_merge( $location_variables, array(
					'location_name'       => '',
					'location_name_local' => '',
					'country_name'        => '',
					'continent_name'      => '',
					'timezone'            => '',
				) );
				break;
		}

		return array_merge( $base_variables, $location_variables );
	}

	/**
	 * Prepare variables for a post.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return array Variables array.
	 */
	public static function prepare_variables_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$type = get_post_meta( $post_id, 'wta_type', true );
		$original_name = get_post_meta( $post_id, 'wta_name_original', true );
		$local_name = get_post_meta( $post_id, 'wta_name_local', true );
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );

		$variables = array(
			'location_type'             => $type,
			'location_name'             => $original_name ? $original_name : $post->post_title,
			'location_name_local'       => $local_name ? $local_name : $post->post_title,
			'base_language'             => get_option( 'wta_base_language', 'en-US' ),
			'base_language_description' => get_option( 'wta_base_language_description', '' ),
			'base_country_name'         => get_option( 'wta_base_country_name', '' ),
		);

		// Add timezone for cities
		if ( $type === 'city' && $timezone ) {
			$variables['timezone'] = $timezone;
		}

		// Get parent information
		if ( $post->post_parent ) {
			$parent_type = get_post_meta( $post->post_parent, 'wta_type', true );
			$parent_name = get_post_meta( $post->post_parent, 'wta_name_local', true );
			if ( ! $parent_name ) {
				$parent_name = get_the_title( $post->post_parent );
			}

			if ( $parent_type === 'country' ) {
				$variables['country_name'] = $parent_name;
				
				// Get grandparent continent
				$grandparent_post = get_post( $post->post_parent );
				if ( $grandparent_post && $grandparent_post->post_parent ) {
					$continent_name = get_post_meta( $grandparent_post->post_parent, 'wta_name_local', true );
					if ( ! $continent_name ) {
						$continent_name = get_the_title( $grandparent_post->post_parent );
					}
					$variables['continent_name'] = $continent_name;
				}
			} elseif ( $parent_type === 'continent' ) {
				$variables['continent_name'] = $parent_name;
			}
		}

		return $variables;
	}

	/**
	 * Validate prompt template.
	 *
	 * @since 1.0.0
	 * @param string $prompt    Prompt template.
	 * @param array  $required_vars Required variables.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_prompt( $prompt, $required_vars = array() ) {
		if ( empty( $prompt ) ) {
			return new WP_Error( 'empty_prompt', __( 'Prompt cannot be empty.', WTA_TEXT_DOMAIN ) );
		}

		// Check for required variables
		foreach ( $required_vars as $var ) {
			$placeholder = '{' . $var . '}';
			if ( strpos( $prompt, $placeholder ) === false ) {
				return new WP_Error(
					'missing_variable',
					sprintf(
						/* translators: %s: variable name */
						__( 'Required variable missing: %s', WTA_TEXT_DOMAIN ),
						$placeholder
					)
				);
			}
		}

		return true;
	}
}






