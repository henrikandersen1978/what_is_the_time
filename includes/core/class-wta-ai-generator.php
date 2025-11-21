<?php
/**
 * OpenAI API integration for content generation.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

/**
 * AI content generator using OpenAI API.
 *
 * @since 1.0.0
 */
class WTA_AI_Generator {

	/**
	 * OpenAI API base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Generate AI content for a post.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function generate_content_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', WTA_TEXT_DOMAIN ) );
		}

		$type = get_post_meta( $post_id, 'wta_type', true );
		if ( empty( $type ) ) {
			return new WP_Error( 'missing_type', __( 'Post type meta not found.', WTA_TEXT_DOMAIN ) );
		}

		WTA_Logger::info( "Generating AI content for {$type}", array( 'post_id' => $post_id ) );

		// Prepare variables
		$variables = WTA_Prompt_Manager::prepare_variables_for_post( $post_id );

		// 1. Translate location name
		$translated_name = self::translate_location_name( $variables );
		if ( is_wp_error( $translated_name ) ) {
			update_post_meta( $post_id, 'wta_ai_status', 'error' );
			return $translated_name;
		}

		// Update post with translated name
		update_post_meta( $post_id, 'wta_name_local', $translated_name );
		$variables['location_name_local'] = $translated_name;

		// 2. Generate page title
		$page_title = self::generate_page_title( $type, $variables );
		if ( is_wp_error( $page_title ) ) {
			update_post_meta( $post_id, 'wta_ai_status', 'error' );
			return $page_title;
		}

		// 3. Generate page content
		$page_content = self::generate_page_content( $type, $variables );
		if ( is_wp_error( $page_content ) ) {
			update_post_meta( $post_id, 'wta_ai_status', 'error' );
			return $page_content;
		}

		// 4. Generate SEO title
		$seo_title = self::generate_seo_title( $type, $variables );
		if ( is_wp_error( $seo_title ) ) {
			update_post_meta( $post_id, 'wta_ai_status', 'error' );
			return $seo_title;
		}

		// 5. Generate SEO meta description
		$seo_description = self::generate_seo_description( $type, $variables );
		if ( is_wp_error( $seo_description ) ) {
			update_post_meta( $post_id, 'wta_ai_status', 'error' );
			return $seo_description;
		}

		// Update post
		$slug = WTA_Utils::generate_slug( $translated_name );
		wp_update_post( array(
			'ID'           => $post_id,
			'post_title'   => $page_title,
			'post_name'    => $slug,
			'post_content' => $page_content,
			'post_status'  => 'publish', // Publish after AI generation
		) );

		// Store SEO data
		self::store_seo_data( $post_id, $seo_title, $seo_description );

		// Mark as done
		update_post_meta( $post_id, 'wta_ai_status', 'done' );

		WTA_Logger::info( "AI content generated successfully for {$type}", array( 'post_id' => $post_id ) );

		return true;
	}

	/**
	 * Translate location name.
	 *
	 * @since 1.0.0
	 * @param array $variables Prompt variables.
	 * @return string|WP_Error Translated name or error.
	 */
	private static function translate_location_name( $variables ) {
		$system_prompt = WTA_Prompt_Manager::get_prompt( 'translate_location_name', 'system', $variables );
		$user_prompt = WTA_Prompt_Manager::get_prompt( 'translate_location_name', 'user', $variables );

		if ( ! $system_prompt || ! $user_prompt ) {
			return new WP_Error( 'missing_prompt', __( 'Translation prompt not found.', WTA_TEXT_DOMAIN ) );
		}

		$result = self::call_openai_api( $system_prompt, $user_prompt, 100 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return trim( $result );
	}

	/**
	 * Generate page title.
	 *
	 * @since 1.0.0
	 * @param string $type      Location type.
	 * @param array  $variables Prompt variables.
	 * @return string|WP_Error Page title or error.
	 */
	private static function generate_page_title( $type, $variables ) {
		$prompt_id = $type . '_page_title';
		$system_prompt = WTA_Prompt_Manager::get_prompt( $prompt_id, 'system', $variables );
		$user_prompt = WTA_Prompt_Manager::get_prompt( $prompt_id, 'user', $variables );

		if ( ! $system_prompt || ! $user_prompt ) {
			return new WP_Error( 'missing_prompt', __( 'Page title prompt not found.', WTA_TEXT_DOMAIN ) );
		}

		$result = self::call_openai_api( $system_prompt, $user_prompt, 150 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return trim( $result );
	}

	/**
	 * Generate page content.
	 *
	 * @since 1.0.0
	 * @param string $type      Location type.
	 * @param array  $variables Prompt variables.
	 * @return string|WP_Error Page content or error.
	 */
	private static function generate_page_content( $type, $variables ) {
		$prompt_id = $type . '_page_content';
		$system_prompt = WTA_Prompt_Manager::get_prompt( $prompt_id, 'system', $variables );
		$user_prompt = WTA_Prompt_Manager::get_prompt( $prompt_id, 'user', $variables );

		if ( ! $system_prompt || ! $user_prompt ) {
			return new WP_Error( 'missing_prompt', __( 'Page content prompt not found.', WTA_TEXT_DOMAIN ) );
		}

		$max_tokens = get_option( 'wta_openai_max_tokens', 1000 );
		$result = self::call_openai_api( $system_prompt, $user_prompt, $max_tokens );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return trim( $result );
	}

	/**
	 * Generate SEO title.
	 *
	 * @since 1.0.0
	 * @param string $type      Location type.
	 * @param array  $variables Prompt variables.
	 * @return string|WP_Error SEO title or error.
	 */
	private static function generate_seo_title( $type, $variables ) {
		$system_prompt = WTA_Prompt_Manager::get_prompt( 'yoast_seo_title', 'system', $variables );
		$user_prompt = WTA_Prompt_Manager::get_prompt( 'yoast_seo_title', 'user', $variables );

		if ( ! $system_prompt || ! $user_prompt ) {
			return new WP_Error( 'missing_prompt', __( 'SEO title prompt not found.', WTA_TEXT_DOMAIN ) );
		}

		$result = self::call_openai_api( $system_prompt, $user_prompt, 100 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return trim( $result );
	}

	/**
	 * Generate SEO meta description.
	 *
	 * @since 1.0.0
	 * @param string $type      Location type.
	 * @param array  $variables Prompt variables.
	 * @return string|WP_Error SEO description or error.
	 */
	private static function generate_seo_description( $type, $variables ) {
		$system_prompt = WTA_Prompt_Manager::get_prompt( 'yoast_meta_description', 'system', $variables );
		$user_prompt = WTA_Prompt_Manager::get_prompt( 'yoast_meta_description', 'user', $variables );

		if ( ! $system_prompt || ! $user_prompt ) {
			return new WP_Error( 'missing_prompt', __( 'SEO description prompt not found.', WTA_TEXT_DOMAIN ) );
		}

		$result = self::call_openai_api( $system_prompt, $user_prompt, 200 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return trim( $result );
	}

	/**
	 * Call OpenAI API.
	 *
	 * @since 1.0.0
	 * @param string $system_prompt System message.
	 * @param string $user_prompt   User message.
	 * @param int    $max_tokens    Maximum tokens to generate.
	 * @return string|WP_Error Generated text or error.
	 */
	private static function call_openai_api( $system_prompt, $user_prompt, $max_tokens = 500 ) {
		$api_key = get_option( 'wta_openai_api_key' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API key is not configured.', WTA_TEXT_DOMAIN ) );
		}

		$model = get_option( 'wta_openai_model', 'gpt-4' );
		$temperature = floatval( get_option( 'wta_openai_temperature', 0.7 ) );

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
		);

		$response = wp_remote_post(
			self::API_BASE_URL,
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			WTA_Logger::error( 'OpenAI API request failed', array(
				'error' => $response->get_error_message(),
			) );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$error_data = json_decode( $body, true );
			$error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown error';
			
			WTA_Logger::error( 'OpenAI API error', array(
				'status'  => $response_code,
				'message' => $error_message,
			) );
			
			return new WP_Error( 'api_error', $error_message );
		}

		// Parse response
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = sprintf(
				/* translators: %s: JSON error */
				__( 'Failed to parse OpenAI response: %s', WTA_TEXT_DOMAIN ),
				json_last_error_msg()
			);
			WTA_Logger::error( $error_message );
			return new WP_Error( 'json_error', $error_message );
		}

		// Extract generated text
		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			$error_message = __( 'No content in OpenAI response.', WTA_TEXT_DOMAIN );
			WTA_Logger::error( $error_message );
			return new WP_Error( 'empty_response', $error_message );
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Store SEO data (Yoast or custom meta).
	 *
	 * @since 1.0.0
	 * @param int    $post_id         Post ID.
	 * @param string $seo_title       SEO title.
	 * @param string $seo_description SEO description.
	 */
	private static function store_seo_data( $post_id, $seo_title, $seo_description ) {
		$yoast_enabled = get_option( 'wta_yoast_integration_enabled', true );
		$yoast_overwrite = get_option( 'wta_yoast_allow_overwrite', true );

		// Check if Yoast is active
		if ( $yoast_enabled && $yoast_overwrite && WTA_Utils::is_yoast_active() ) {
			// Store in Yoast meta fields
			update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $seo_description );
			WTA_Logger::debug( 'Stored SEO data in Yoast fields', array( 'post_id' => $post_id ) );
		} else {
			// Store in custom meta fields
			update_post_meta( $post_id, 'wta_seo_title', $seo_title );
			update_post_meta( $post_id, 'wta_seo_description', $seo_description );
			WTA_Logger::debug( 'Stored SEO data in custom fields', array( 'post_id' => $post_id ) );
		}
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function test_api() {
		$result = self::call_openai_api(
			'You are a helpful assistant.',
			'Say "Hello, World!" in exactly those words.',
			50
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( stripos( $result, 'Hello' ) !== false ) {
			return true;
		}

		return new WP_Error( 'unexpected_result', sprintf(
			/* translators: %s: API response */
			__( 'Unexpected API response: %s', WTA_TEXT_DOMAIN ),
			$result
		) );
	}
}






