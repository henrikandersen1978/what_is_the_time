<?php
/**
 * AI content processor for Action Scheduler.
 *
 * Generates AI content using OpenAI and publishes posts.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/scheduler
 */

class WTA_AI_Processor {

	/**
	 * Process batch.
	 *
	 * Called by Action Scheduler every 5 minutes.
	 *
	 * @since    2.0.0
	 */
	public function process_batch() {
		// Get pending AI content items
		$items = WTA_Queue::get_pending( 'ai_content', 10 );

		if ( empty( $items ) ) {
			return;
		}

		WTA_Logger::info( 'AI processor started', array(
			'items' => count( $items ),
		) );

		foreach ( $items as $item ) {
			$this->process_item( $item );

			// Small delay between API calls
			usleep( 100000 ); // 100ms
		}

		WTA_Logger::info( 'AI processor completed', array(
			'processed' => count( $items ),
		) );
	}

	/**
	 * Process single AI content generation.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_item( $item ) {
		WTA_Queue::mark_processing( $item['id'] );

		try {
			$data = $item['payload'];
			$post_id = $data['post_id'];
			$type = $data['type'];

			// Validate post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				WTA_Logger::warning( 'Post not found for AI generation', array(
					'post_id' => $post_id,
				) );
				WTA_Queue::mark_done( $item['id'] );
				return;
			}

			// Check if already processed
			$ai_status = get_post_meta( $post_id, 'wta_ai_status', true );
			if ( 'done' === $ai_status ) {
				WTA_Logger::info( 'AI content already generated', array( 'post_id' => $post_id ) );
				WTA_Queue::mark_done( $item['id'] );
				return;
			}

			// Generate content
			$result = $this->generate_ai_content( $post_id, $type );

			if ( false === $result ) {
				WTA_Queue::mark_failed( $item['id'], 'AI content generation failed' );
				return;
			}

			// Update post
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $result['content'],
				'post_status'  => 'publish', // PUBLISH the post!
			) );

			// Update Yoast SEO meta if available
			if ( isset( $result['yoast_title'] ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', $result['yoast_title'] );
			}
			if ( isset( $result['yoast_desc'] ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $result['yoast_desc'] );
			}

			// Mark as done
			update_post_meta( $post_id, 'wta_ai_status', 'done' );

			WTA_Logger::info( 'AI content generated and post published', array(
				'post_id' => $post_id,
				'type'    => $type,
			) );

			WTA_Queue::mark_done( $item['id'] );

		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to process AI item', array(
				'id'    => $item['id'],
				'error' => $e->getMessage(),
			) );
			WTA_Queue::mark_failed( $item['id'], $e->getMessage() );
		}
	}

	/**
	 * Generate AI content for post.
	 *
	 * @since    2.0.0
	 * @param    int    $post_id Post ID.
	 * @param    string $type    Location type.
	 * @return   array|false     Generated content or false on failure.
	 */
	private function generate_ai_content( $post_id, $type ) {
		// Get OpenAI settings
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			WTA_Logger::error( 'OpenAI API key not configured' );
			return false;
		}

		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		$temperature = (float) get_option( 'wta_openai_temperature', 0.7 );
		$max_tokens = (int) get_option( 'wta_openai_max_tokens', 2000 );

		// Get post data
		$name_local = get_the_title( $post_id );
		$name_original = get_post_meta( $post_id, 'wta_name_original', true );
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );
		$country_code = get_post_meta( $post_id, 'wta_country_code', true );
		$continent_code = get_post_meta( $post_id, 'wta_continent_code', true );

		// Build variables for prompts
		$variables = array(
			'{location_name}'                  => $name_original,
			'{location_name_local}'            => $name_local,
			'{location_type}'                  => $type,
			'{timezone}'                       => $timezone,
			'{base_language}'                  => get_option( 'wta_base_language', 'da-DK' ),
			'{base_language_description}'      => get_option( 'wta_base_language_description', 'Skriv på flydende dansk til danske brugere' ),
			'{base_country_name}'              => get_option( 'wta_base_country_name', 'Danmark' ),
		);

		// Get parent names
		$parent_id = wp_get_post_parent_id( $post_id );
		if ( $parent_id ) {
			$parent_type = get_post_meta( $parent_id, 'wta_type', true );
			if ( 'continent' === $parent_type ) {
				$variables['{continent_name}'] = get_the_title( $parent_id );
			} elseif ( 'country' === $parent_type ) {
				$variables['{country_name}'] = get_the_title( $parent_id );
				$grandparent_id = wp_get_post_parent_id( $parent_id );
				if ( $grandparent_id ) {
					$variables['{continent_name}'] = get_the_title( $grandparent_id );
				}
			}
		}

		// Generate content
		$content_system = get_option( "wta_prompt_{$type}_content_system", '' );
		$content_user = get_option( "wta_prompt_{$type}_content_user", '' );

		$content_system = str_replace( array_keys( $variables ), array_values( $variables ), $content_system );
		$content_user = str_replace( array_keys( $variables ), array_values( $variables ), $content_user );

		$content = $this->call_openai_api( $api_key, $model, $temperature, $max_tokens, $content_system, $content_user );
		if ( false === $content ) {
			return false;
		}

		// Generate Yoast SEO title
		$yoast_title_system = get_option( 'wta_prompt_yoast_title_system', '' );
		$yoast_title_user = get_option( 'wta_prompt_yoast_title_user', '' );
		$yoast_title_system = str_replace( array_keys( $variables ), array_values( $variables ), $yoast_title_system );
		$yoast_title_user = str_replace( array_keys( $variables ), array_values( $variables ), $yoast_title_user );
		$yoast_title = $this->call_openai_api( $api_key, $model, $temperature, 100, $yoast_title_system, $yoast_title_user );

		// Generate Yoast meta description
		$yoast_desc_system = get_option( 'wta_prompt_yoast_desc_system', '' );
		$yoast_desc_user = get_option( 'wta_prompt_yoast_desc_user', '' );
		$yoast_desc_system = str_replace( array_keys( $variables ), array_values( $variables ), $yoast_desc_system );
		$yoast_desc_user = str_replace( array_keys( $variables ), array_values( $variables ), $yoast_desc_user );
		$yoast_desc = $this->call_openai_api( $api_key, $model, $temperature, 200, $yoast_desc_system, $yoast_desc_user );

		return array(
			'content'     => $content,
			'yoast_title' => $yoast_title,
			'yoast_desc'  => $yoast_desc,
		);
	}

	/**
	 * Call OpenAI API.
	 *
	 * @since    2.0.0
	 * @param    string $api_key     API key.
	 * @param    string $model       Model name.
	 * @param    float  $temperature Temperature.
	 * @param    int    $max_tokens  Max tokens.
	 * @param    string $system      System prompt.
	 * @param    string $user        User prompt.
	 * @return   string|false        Generated text or false on failure.
	 */
	private function call_openai_api( $api_key, $model, $temperature, $max_tokens, $system, $user ) {
		$url = 'https://api.openai.com/v1/chat/completions';

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
		);

		$response = wp_remote_post( $url, array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			WTA_Logger::error( 'OpenAI API request failed', array(
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			WTA_Logger::error( 'OpenAI API returned unexpected response', array(
				'response' => $data,
			) );
			return false;
		}

		return $data['choices'][0]['message']['content'];
	}
}


