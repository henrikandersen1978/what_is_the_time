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
	 * Called by Action Scheduler every minute.
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
				// Also save as custom H1 field for Pilanto theme
				update_post_meta( $post_id, '_pilanto_page_h1', $result['yoast_title'] );
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
	 * Generate AI content for post with structured sections.
	 *
	 * @since    2.3.6
	 * @param    int    $post_id Post ID.
	 * @param    string $type    Location type.
	 * @return   array|false     Generated content or false on failure.
	 */
	private function generate_ai_content( $post_id, $type ) {
		// Use multi-section generation for continents
		if ( 'continent' === $type ) {
			return $this->generate_continent_content( $post_id );
		}
		
		// Use standard generation for countries and cities (for now)
		return $this->generate_standard_content( $post_id, $type );
	}

	/**
	 * Generate structured content for continents with multiple sections.
	 *
	 * Uses customizable prompts from admin settings for each section.
	 * No token limits - OpenAI controls length based on prompt instructions.
	 *
	 * @since    2.8.0
	 * @param    int $post_id Post ID.
	 * @return   array|false  Generated content or false on failure.
	 */
	private function generate_continent_content( $post_id ) {
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return false;
		}

		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		$temperature = (float) get_option( 'wta_openai_temperature', 0.7 );
		
		$name_local = get_the_title( $post_id );
		$name_original = get_post_meta( $post_id, 'wta_name_original', true );
		
		// Build variables for prompts
		$variables = array(
			'{location_name}'       => $name_original,
			'{location_name_local}' => $name_local,
		);
		
		// Use shortcode for dynamic child locations list
		// This shortcode will display children (countries/cities) with heading and intro
		$country_list = '[wta_child_locations]' . "\n\n";
		
		// Get major cities for context (for AI prompt)
		$children = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $post_id,
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'draft' ),
		) );
		
		$major_cities = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => 12, // 3x4 grid = 12 cities
			'post_parent__in' => wp_list_pluck( $children, 'ID' ),
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'wta_population',
			'order'          => 'DESC',
			'post_status'    => array( 'publish', 'draft' ), // Include drafts!
		) );
		$cities_list = '';
		if ( ! empty( $major_cities ) ) {
			// Get simple post titles (not SEO H1)
			$city_names = array();
			foreach ( $major_cities as $city ) {
				$city_names[] = get_post_field( 'post_title', $city->ID );
			}
			$cities_list = implode( ', ', $city_names );
		}
		
		// === 1. INTRO ===
		$intro_system = get_option( 'wta_prompt_continent_intro_system', '' );
		$intro_user = get_option( 'wta_prompt_continent_intro_user', '' );
		$intro_system = str_replace( array_keys( $variables ), array_values( $variables ), $intro_system );
		$intro_user = str_replace( array_keys( $variables ), array_values( $variables ), $intro_user );
		$intro = $this->call_openai_api( $api_key, $model, $temperature, 500, $intro_system, $intro_user );
		
		// === 2. COUNTRY LIST (auto-generated above) ===
		
		// === 3. TIMEZONE ===
		$tz_system = get_option( 'wta_prompt_continent_timezone_system', '' );
		$tz_user = get_option( 'wta_prompt_continent_timezone_user', '' );
		$tz_system = str_replace( array_keys( $variables ), array_values( $variables ), $tz_system );
		$tz_user = str_replace( array_keys( $variables ), array_values( $variables ), $tz_user );
		$timezone_content = $this->call_openai_api( $api_key, $model, $temperature, 600, $tz_system, $tz_user );
		
		// === 4. MAJOR CITIES ===
		$cities_system = get_option( 'wta_prompt_continent_cities_system', '' );
		$cities_user = get_option( 'wta_prompt_continent_cities_user', '' );
		// Add cities list to variables for this section
		$cities_variables = array_merge( $variables, array( '{cities_list}' => $cities_list ) );
		$cities_system = str_replace( array_keys( $cities_variables ), array_values( $cities_variables ), $cities_system );
		$cities_user = str_replace( array_keys( $cities_variables ), array_values( $cities_variables ), $cities_user );
		$cities_content = $this->call_openai_api( $api_key, $model, $temperature, 500, $cities_system, $cities_user );
		
		// Add dynamic shortcode to display major cities with live clocks
		// This shortcode queries the database when the page loads, so no timing issues!
		$cities_content .= "\n\n" . '[wta_major_cities count="12"]';
		
		// === 5. GEOGRAPHY ===
		$geo_system = get_option( 'wta_prompt_continent_geography_system', '' );
		$geo_user = get_option( 'wta_prompt_continent_geography_user', '' );
		$geo_system = str_replace( array_keys( $variables ), array_values( $variables ), $geo_system );
		$geo_user = str_replace( array_keys( $variables ), array_values( $variables ), $geo_user );
		$geography_content = $this->call_openai_api( $api_key, $model, $temperature, 400, $geo_system, $geo_user );
		
		// === 6. FACTS ===
		$facts_system = get_option( 'wta_prompt_continent_facts_system', '' );
		$facts_user = get_option( 'wta_prompt_continent_facts_user', '' );
		$facts_system = str_replace( array_keys( $variables ), array_values( $variables ), $facts_system );
		$facts_user = str_replace( array_keys( $variables ), array_values( $variables ), $facts_user );
		$facts_content = $this->call_openai_api( $api_key, $model, $temperature, 500, $facts_system, $facts_user );
		
		// === COMBINE ALL SECTIONS ===
		// Add paragraph breaks to make content more readable
		$intro = $this->add_paragraph_breaks( $intro );
		$timezone_content = $this->add_paragraph_breaks( $timezone_content );
		$cities_content = $this->add_paragraph_breaks( $cities_content );
		$geography_content = $this->add_paragraph_breaks( $geography_content );
		$facts_content = $this->add_paragraph_breaks( $facts_content );
		
		$full_content = $intro . "\n\n";
		$full_content .= $country_list;
		$full_content .= '<h2>Tidszoner i ' . esc_html( $name_local ) . '</h2>' . "\n" . $timezone_content . "\n\n";
		$full_content .= '<h2>Hvad er klokken i de største byer i ' . esc_html( $name_local ) . '?</h2>' . "\n" . $cities_content . "\n\n";
		$full_content .= '<h2>Geografi og beliggenhed</h2>' . "\n" . $geography_content . "\n\n";
		$full_content .= '<h2>Interessante fakta om ' . esc_html( $name_local ) . '</h2>' . "\n" . $facts_content;
		
		// Generate Yoast SEO meta
		$yoast_title = $this->generate_yoast_title( $post_id, $name_local, 'continent' );
		$yoast_desc = $this->generate_yoast_description( $post_id, $name_local, 'continent' );
		
		return array(
			'content'     => $full_content,
			'yoast_title' => $yoast_title,
			'yoast_desc'  => $yoast_desc,
		);
	}

	/**
	 * Generate standard content (for countries and cities).
	 *
	 * @since    2.3.6
	 * @param    int    $post_id Post ID.
	 * @param    string $type    Location type.
	 * @return   array|false     Generated content or false on failure.
	 */
	private function generate_standard_content( $post_id, $type ) {
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
	 * Generate Yoast SEO title.
	 *
	 * For continents, creates SEO-friendly H1 like "Hvad er klokken i Europa? Tidszoner og aktuel tid"
	 * For other types, uses AI generation.
	 *
	 * @since    2.8.2
	 * @param    int    $post_id Post ID.
	 * @param    string $name    Location name.
	 * @param    string $type    Location type.
	 * @return   string|false    Generated title or false.
	 */
	private function generate_yoast_title( $post_id, $name, $type ) {
		// For continents, use SEO-friendly template
		if ( 'continent' === $type ) {
			return sprintf( 'Hvad er klokken i %s? Tidszoner og aktuel tid', $name );
		}
		
		// For other types, use AI generation
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return false;
		}

		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		$system = 'Du er SEO ekspert. Skriv KUN titlen, ingen citationstegn, ingen ekstra tekst.';
		$user = sprintf(
			'Skriv en SEO meta title (50-60 tegn) for en side om hvad klokken er i %s. Inkluder "Hvad er klokken" eller "Tidszoner". KUN titlen.',
			$name
		);
		
		return $this->call_openai_api( $api_key, $model, 0.7, 100, $system, $user );
	}

	/**
	 * Generate Yoast SEO description.
	 *
	 * @since    2.3.6
	 * @param    int    $post_id Post ID.
	 * @param    string $name    Location name.
	 * @param    string $type    Location type.
	 * @return   string|false    Generated description or false.
	 */
	private function generate_yoast_description( $post_id, $name, $type ) {
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return false;
		}

		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		$system = 'Du er SEO ekspert. Skriv KUN beskrivelsen, ingen citationstegn, ingen ekstra tekst.';
		$user = sprintf(
			'Skriv en SEO meta description (140-160 tegn) om hvad klokken er i %s og tidszoner. KUN beskrivelsen.',
			$name
		);
		
		return $this->call_openai_api( $api_key, $model, 0.7, 200, $system, $user );
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

		$content = $data['choices'][0]['message']['content'];
		
		// Clean up the content
		return $this->clean_ai_content( $content );
	}

	/**
	 * Add paragraph breaks to AI content for better readability.
	 *
	 * Converts long text blocks into properly formatted paragraphs.
	 *
	 * @since    2.8.1
	 * @param    string $content The AI-generated content.
	 * @return   string          Content with paragraph breaks.
	 */
	private function add_paragraph_breaks( $content ) {
		// If content already has paragraph tags, return as is
		if ( strpos( $content, '<p>' ) !== false ) {
			return $content;
		}
		
		// Split by sentence boundaries (. followed by space and capital letter)
		// Group sentences into paragraphs of 2-4 sentences
		$sentences = preg_split( '/(?<=[.!?])\s+(?=[A-ZÆØÅ])/', $content );
		
		if ( count( $sentences ) <= 2 ) {
			// Short content, wrap in single paragraph
			return '<p>' . trim( $content ) . '</p>';
		}
		
		$paragraphs = array();
		$current_paragraph = array();
		$sentence_count = 0;
		
		foreach ( $sentences as $sentence ) {
			$current_paragraph[] = $sentence;
			$sentence_count++;
			
			// Create a new paragraph every 2-3 sentences
			if ( $sentence_count >= 2 && ( $sentence_count >= 3 || count( $sentences ) - count( $current_paragraph ) <= 2 ) ) {
				$paragraphs[] = '<p>' . trim( implode( ' ', $current_paragraph ) ) . '</p>';
				$current_paragraph = array();
				$sentence_count = 0;
			}
		}
		
		// Add any remaining sentences
		if ( ! empty( $current_paragraph ) ) {
			$paragraphs[] = '<p>' . trim( implode( ' ', $current_paragraph ) ) . '</p>';
		}
		
		return implode( "\n\n", $paragraphs );
	}

	/**
	 * Clean AI-generated content.
	 *
	 * Removes common AI artifacts and unwanted phrases.
	 *
	 * @since    2.0.0
	 * @param    string $content The AI-generated content.
	 * @return   string          Cleaned content.
	 */
	private function clean_ai_content( $content ) {
		// Remove surrounding quotes if present
		$content = trim( $content );
		if ( ( str_starts_with( $content, '"' ) && str_ends_with( $content, '"' ) ) ||
		     ( str_starts_with( $content, "'" ) && str_ends_with( $content, "'" ) ) ) {
			$content = substr( $content, 1, -1 );
		}

		// Remove common ChatGPT artifacts (Danish versions)
		$artifacts = array(
			'/^Velkommen til\s+/i',
			'/^Lad os udforske\s+/i',
			'/^I denne artikel\s+/i',
			'/^Her er\s+/i',
			'/^Dette er\s+/i',
			'/\s+Håber dette hjælper!?$/i',
			'/\s+God fornøjelse!?$/i',
			'/\s+Rigtig god fornøjelse!?$/i',
			'/\s+Enjoy!?$/i',
			'/\s+Happy travels!?$/i',
			// English versions (in case they slip through)
			'/^Welcome to\s+/i',
			'/^Let\'s explore\s+/i',
			'/^In this article\s+/i',
			'/^Here is\s+/i',
			'/^This is\s+/i',
			'/\s+Hope this helps!?$/i',
		);

		foreach ( $artifacts as $pattern ) {
			$content = preg_replace( $pattern, '', $content );
		}

		// Remove excessive whitespace
		$content = preg_replace( '/\s+/', ' ', $content );
		
		return trim( $content );
	}
}


