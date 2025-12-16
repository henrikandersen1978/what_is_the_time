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
	 * Force regenerate single post immediately (no queue).
	 * 
	 * Dedicated method for manual regeneration that bypasses queue system entirely.
	 * Copies logic from process_item() but without queue dependencies.
	 *
	 * @since    2.35.26
	 * @param    int  $post_id  Post ID to regenerate.
	 * @return   bool           Success or failure.
	 */
	public function force_regenerate_single( $post_id ) {
		try {
			$type = get_post_meta( $post_id, 'wta_type', true );
			$force_ai = true; // Always use real AI for manual regeneration
			
			// Validate post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				WTA_Logger::warning( 'Post not found for AI generation', array( 'post_id' => $post_id ) );
				return false;
			}
			
			// Generate content
			$result = $this->generate_ai_content( $post_id, $type, $force_ai );
			
			if ( false === $result ) {
				WTA_Logger::error( 'AI content generation failed', array( 'post_id' => $post_id ) );
				return false;
			}
			
			// Generate FAQ for cities
			if ( 'city' === $type ) {
				$test_mode = get_option( 'wta_test_mode', 0 );
				$use_test_mode = $test_mode && ! $force_ai;
				$faq_data = WTA_FAQ_Generator::generate_city_faq( $post_id, $use_test_mode );
				
				if ( false !== $faq_data && ! empty( $faq_data ) ) {
					// Save FAQ data for schema
					update_post_meta( $post_id, 'wta_faq_data', $faq_data );
					
					// Render FAQ HTML and append to post content
					$city_name = get_the_title( $post_id );
					$faq_html = WTA_FAQ_Renderer::render_faq_section( $faq_data, $city_name );
					
					if ( ! empty( $faq_html ) ) {
						$result['content'] .= "\n\n" . $faq_html;
						WTA_Logger::info( 'FAQ generated and appended to content', array( 
							'post_id'   => $post_id,
							'force_ai'  => $force_ai,
							'faq_count' => count( $faq_data['faqs'] )
						) );
					}
				} else {
					WTA_Logger::warning( 'Failed to generate FAQ', array( 'post_id' => $post_id ) );
				}
			}
			
			// Update post with content (including FAQ HTML if city)
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $result['content'],
				'post_status'  => 'publish',
			) );
			
			// Update Yoast SEO meta if available
			if ( isset( $result['yoast_title'] ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', $result['yoast_title'] );
				// Only update H1 for continents and countries, NOT cities
				if ( 'city' !== $type ) {
					update_post_meta( $post_id, '_pilanto_page_h1', $result['yoast_title'] );
				}
			}
			if ( isset( $result['yoast_desc'] ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $result['yoast_desc'] );
			}
			
			// Mark as done
			update_post_meta( $post_id, 'wta_ai_status', 'done' );
			
			WTA_Logger::info( 'AI content generated and post published (force regenerate)', array(
				'post_id' => $post_id,
				'type'    => $type,
			) );
			
			return true;
			
		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to force regenerate post', array(
				'post_id' => $post_id,
				'error'   => $e->getMessage(),
			) );
			return false;
		}
	}

	/**
	 * Process batch.
	 *
	 * Called by Action Scheduler every minute.
	 *
	 * @since    2.0.0
	 */
	public function process_batch() {
		// Dynamic batch size based on cron interval and test mode
		$cron_interval = intval( get_option( 'wta_cron_interval', 60 ) );
		$test_mode = get_option( 'wta_test_mode', 0 );
		
	if ( $test_mode ) {
		// Test mode: Fast template generation (~0.8s per city after DB optimization)
		// 1-min: 55 cities (~44s - safe under 50s limit)
		// 5-min: 280 cities (~224s - safe under 270s limit, well under 10min timeout)
		$batch_size = ( $cron_interval >= 300 ) ? 280 : 55;
	} else {
		// AI mode with Tier 5 + DB optimization: ~12-13s per city
		// 1-min interval: 3 cities (39s - safe buffer under 50s limit)
		// 5-min interval: 18 cities (234s - safe under 270s limit, well under 10min timeout)
		// Conservative to avoid timeouts while maximizing throughput
		$batch_size = ( $cron_interval >= 300 ) ? 18 : 3;
	}
		
		// Get pending AI content items
		$items = WTA_Queue::get_pending( 'ai_content', $batch_size );

		if ( empty( $items ) ) {
			return;
		}

		$start_time = microtime( true );

		WTA_Logger::info( 'AI processor started', array(
			'items' => count( $items ),
			'test_mode' => $test_mode ? 'yes' : 'no',
		) );

		$processed = 0;
		foreach ( $items as $item ) {
			$this->process_item( $item );
			$processed++;

		// No delay needed with OpenAI Tier 5 (15,000 RPM = 250 RPS)
		// Our usage: 16 cities × 8 API calls = 128 calls per 5-min = 0.4 RPS
		// = Only 0.16% of Tier 5 capacity - no rate limiting risk!

			// Safety check: Stop early to respect time limit
			$elapsed = microtime( true ) - $start_time;
			$time_limit = ( $cron_interval >= 300 ) ? 260 : 45;
			
			if ( $elapsed > $time_limit ) {
				WTA_Logger::warning( 'AI batch stopped early to respect time limit', array(
					'processed' => $processed,
					'remaining' => count( $items ) - $processed,
					'elapsed_seconds' => round( $elapsed, 2 ),
					'time_limit' => $time_limit,
				) );
				break;
			}
		}

		$duration = round( microtime( true ) - $start_time, 2 );

		WTA_Logger::info( 'AI processor completed', array(
			'processed' => $processed,
			'duration_seconds' => $duration,
			'avg_per_item' => $processed > 0 ? round( $duration / $processed, 2 ) : 0,
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
			$force_ai = isset( $data['force_ai'] ) ? $data['force_ai'] : false;

			// Validate post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				WTA_Logger::warning( 'Post not found for AI generation', array(
					'post_id' => $post_id,
				) );
				WTA_Queue::mark_done( $item['id'] );
				return;
			}

			// Check if already processed (skip check if force_ai is true)
			if ( ! $force_ai ) {
				$ai_status = get_post_meta( $post_id, 'wta_ai_status', true );
				if ( 'done' === $ai_status ) {
					WTA_Logger::info( 'AI content already generated', array( 'post_id' => $post_id ) );
					WTA_Queue::mark_done( $item['id'] );
					return;
				}
			}

		// Generate content
		$result = $this->generate_ai_content( $post_id, $type, $force_ai );

		if ( false === $result ) {
			WTA_Queue::mark_failed( $item['id'], 'AI content generation failed' );
			return;
		}

		// Generate FAQ for cities BEFORE saving content (v2.35.0)
		if ( 'city' === $type ) {
			// Use test mode unless force_ai is true
			$test_mode = get_option( 'wta_test_mode', 0 );
			$use_test_mode = $test_mode && ! $force_ai;
			$faq_data = WTA_FAQ_Generator::generate_city_faq( $post_id, $use_test_mode );
			
			if ( false !== $faq_data && ! empty( $faq_data ) ) {
				// Save FAQ data for schema
				update_post_meta( $post_id, 'wta_faq_data', $faq_data );
				
				// Render FAQ HTML and append to post content
				$city_name = get_the_title( $post_id );
				$faq_html = WTA_FAQ_Renderer::render_faq_section( $faq_data, $city_name );
				
				if ( ! empty( $faq_html ) ) {
					$result['content'] .= "\n\n" . $faq_html;
					WTA_Logger::info( 'FAQ generated and appended to content', array( 
						'post_id' => $post_id, 
						'force_ai' => $force_ai,
						'faq_count' => count( $faq_data['faqs'] )
					) );
				}
			} else {
				WTA_Logger::warning( 'Failed to generate FAQ', array( 'post_id' => $post_id ) );
			}
		}

		// Update post with content (including FAQ HTML if city)
		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $result['content'],
			'post_status'  => 'publish', // PUBLISH the post!
		) );

	// Update Yoast SEO meta if available
	if ( isset( $result['yoast_title'] ) ) {
		update_post_meta( $post_id, '_yoast_wpseo_title', $result['yoast_title'] );
		// Only update H1 for continents and countries, NOT cities (cities keep their structured H1)
		$type = get_post_meta( $post_id, 'wta_type', true );
		if ( 'city' !== $type ) {
			update_post_meta( $post_id, '_pilanto_page_h1', $result['yoast_title'] );
		}
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
	 * @param    int    $post_id  Post ID.
	 * @param    string $type     Location type.
	 * @param    bool   $force_ai Force AI generation (ignore test mode).
	 * @return   array|false      Generated content or false on failure.
	 */
	private function generate_ai_content( $post_id, $type, $force_ai = false ) {
		// Use multi-section generation for continents, countries, and cities
		if ( 'continent' === $type ) {
			return $this->generate_continent_content( $post_id, $force_ai );
		} elseif ( 'country' === $type ) {
			return $this->generate_country_content( $post_id, $force_ai );
		} elseif ( 'city' === $type ) {
			return $this->generate_city_content( $post_id, $force_ai );
		}
		
		// Use standard generation for cities only (legacy fallback)
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
	private function generate_continent_content( $post_id, $force_ai = false ) {
		// Check if test mode is enabled (template content instead of AI)
		// Skip test mode if force_ai is true (manual single-post regeneration)
		$test_mode = get_option( 'wta_test_mode', 0 );
		if ( $test_mode && ! $force_ai ) {
			WTA_Logger::info( 'Test mode enabled - using template content (no AI costs)', array( 'post_id' => $post_id ) );
			return $this->generate_template_continent_content( $post_id );
		}
		
		if ( $force_ai ) {
			WTA_Logger::info( 'Force AI enabled - using real AI (ignore test mode)', array( 'post_id' => $post_id ) );
		}
		
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
	$intro = $this->call_openai_api( $api_key, $model, $temperature, 800, $intro_system, $intro_user );
		
		// === 2. COUNTRY LIST (auto-generated above) ===
		
	// === 3. TIMEZONE ===
	$tz_system = get_option( 'wta_prompt_continent_timezone_system', '' );
	$tz_user = get_option( 'wta_prompt_continent_timezone_user', '' );
	$tz_system = str_replace( array_keys( $variables ), array_values( $variables ), $tz_system );
	$tz_user = str_replace( array_keys( $variables ), array_values( $variables ), $tz_user );
	$timezone_content = $this->call_openai_api( $api_key, $model, $temperature, 1000, $tz_system, $tz_user );
		
	// === 4. MAJOR CITIES ===
	$cities_system = get_option( 'wta_prompt_continent_cities_system', '' );
	$cities_user = get_option( 'wta_prompt_continent_cities_user', '' );
	// Add cities list to variables for this section
	$cities_variables = array_merge( $variables, array( '{cities_list}' => $cities_list ) );
	$cities_system = str_replace( array_keys( $cities_variables ), array_values( $cities_variables ), $cities_system );
	$cities_user = str_replace( array_keys( $cities_variables ), array_values( $cities_variables ), $cities_user );
	$cities_content = $this->call_openai_api( $api_key, $model, $temperature, 800, $cities_system, $cities_user );
		
		// Add dynamic shortcode to display major cities with live clocks
		// This shortcode queries the database when the page loads, so no timing issues!
		$cities_content .= "\n\n" . '[wta_major_cities]';
		
	// === 5. GEOGRAPHY ===
	$geo_system = get_option( 'wta_prompt_continent_geography_system', '' );
	$geo_user = get_option( 'wta_prompt_continent_geography_user', '' );
	$geo_system = str_replace( array_keys( $variables ), array_values( $variables ), $geo_system );
	$geo_user = str_replace( array_keys( $variables ), array_values( $variables ), $geo_user );
	$geography_content = $this->call_openai_api( $api_key, $model, $temperature, 700, $geo_system, $geo_user );
		
	// === 6. FACTS ===
	$facts_system = get_option( 'wta_prompt_continent_facts_system', '' );
	$facts_user = get_option( 'wta_prompt_continent_facts_user', '' );
	$facts_system = str_replace( array_keys( $variables ), array_values( $variables ), $facts_system );
	$facts_user = str_replace( array_keys( $variables ), array_values( $variables ), $facts_user );
	$facts_content = $this->call_openai_api( $api_key, $model, $temperature, 800, $facts_system, $facts_user );
		
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
	 * Generate structured content for countries with multiple sections.
	 *
	 * Uses customizable prompts from admin settings for each section.
	 * Parallel structure to continent pages but tailored for country-specific content.
	 *
	 * @since    2.10.0
	 * @param    int $post_id Post ID.
	 * @return   array|false  Generated content or false on failure.
	 */
	private function generate_country_content( $post_id, $force_ai = false ) {
		// Check if test mode is enabled (template content instead of AI)
		// Skip test mode if force_ai is true (manual single-post regeneration)
		$test_mode = get_option( 'wta_test_mode', 0 );
		if ( $test_mode && ! $force_ai ) {
			WTA_Logger::info( 'Test mode enabled - using template content (no AI costs)', array( 'post_id' => $post_id ) );
			return $this->generate_template_country_content( $post_id );
		}
		
		if ( $force_ai ) {
			WTA_Logger::info( 'Force AI enabled - using real AI (ignore test mode)', array( 'post_id' => $post_id ) );
		}
		
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return false;
		}

		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		$temperature = (float) get_option( 'wta_openai_temperature', 0.7 );
		
		$name_local = get_the_title( $post_id );
		$name_original = get_post_meta( $post_id, 'wta_name_original', true );
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );
		
		// Get parent continent name
		$parent_id = wp_get_post_parent_id( $post_id );
		$continent_name = $parent_id ? get_the_title( $parent_id ) : '';
		
		// Build variables for prompts
		$variables = array(
			'{location_name}'       => $name_original,
			'{location_name_local}' => $name_local,
			'{continent_name}'      => $continent_name,
			'{timezone}'            => $timezone,
			'{base_country_name}'   => get_option( 'wta_base_country_name', 'Danmark' ),
		);
		
		// Use shortcode for dynamic child locations list (cities)
		$city_list = '[wta_child_locations]' . "\n\n";
		
		// Get major cities for AI prompt context
		$major_cities = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => 12,
			'post_parent'    => $post_id,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'wta_population',
			'order'          => 'DESC',
			'post_status'    => array( 'publish', 'draft' ),
		) );
		
		$cities_list = '';
		if ( ! empty( $major_cities ) ) {
			$city_names = array();
			foreach ( $major_cities as $city ) {
				$city_names[] = get_post_field( 'post_title', $city->ID );
			}
			$cities_list = implode( ', ', $city_names );
		}
		
		// ==========================================
		// PARALLEL API CALLS (v2.35.31)
		// Execute all 7 API calls simultaneously for 3x speed boost!
		// ==========================================
		
		// Prepare all requests
		$batch_requests = array();
		
		// 1. INTRO
		$intro_system = get_option( 'wta_prompt_country_intro_system', '' );
		$intro_user = get_option( 'wta_prompt_country_intro_user', '' );
		$batch_requests['intro'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $intro_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $intro_user ),
			'temperature' => $temperature,
			'max_tokens'  => 600,
		);
		
		// 2. TIMEZONE
		$tz_system = get_option( 'wta_prompt_country_timezone_system', '' );
		$tz_user = get_option( 'wta_prompt_country_timezone_user', '' );
		$batch_requests['timezone'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $tz_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $tz_user ),
			'temperature' => $temperature,
			'max_tokens'  => 800,
		);
		
		// 3. MAJOR CITIES
		$cities_system = get_option( 'wta_prompt_country_cities_system', '' );
		$cities_user = get_option( 'wta_prompt_country_cities_user', '' );
		$cities_variables = array_merge( $variables, array( '{cities_list}' => $cities_list ) );
		$batch_requests['cities'] = array(
			'system'      => str_replace( array_keys( $cities_variables ), array_values( $cities_variables ), $cities_system ),
			'user'        => str_replace( array_keys( $cities_variables ), array_values( $cities_variables ), $cities_user ),
			'temperature' => $temperature,
			'max_tokens'  => 700,
		);
		
		// 4. WEATHER & CLIMATE
		$weather_system = get_option( 'wta_prompt_country_weather_system', '' );
		$weather_user = get_option( 'wta_prompt_country_weather_user', '' );
		$batch_requests['weather'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $weather_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $weather_user ),
			'temperature' => $temperature,
			'max_tokens'  => 700,
		);
		
		// 5. CULTURE & TIME
		$culture_system = get_option( 'wta_prompt_country_culture_system', '' );
		$culture_user = get_option( 'wta_prompt_country_culture_user', '' );
		$batch_requests['culture'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $culture_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $culture_user ),
			'temperature' => $temperature,
			'max_tokens'  => 700,
		);
		
		// 6. TRAVEL INFO
		$travel_system = get_option( 'wta_prompt_country_travel_system', '' );
		$travel_user = get_option( 'wta_prompt_country_travel_user', '' );
		$batch_requests['travel'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $travel_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $travel_user ),
			'temperature' => $temperature,
			'max_tokens'  => 800,
		);
		
		// 7. YOAST TITLE
		$yoast_title_system = 'Du er SEO ekspert. Skriv KUN titlen, ingen citationstegn, ingen ekstra tekst.';
		$yoast_title_user = sprintf(
			'Skriv en SEO meta title (50-60 tegn) for en side om hvad klokken er i %s. Inkluder "Hvad er klokken" eller "Tidszoner". KUN titlen.',
			$name_local
		);
		$batch_requests['yoast_title'] = array(
			'system'      => $yoast_title_system,
			'user'        => $yoast_title_user,
			'temperature' => 0.7,
			'max_tokens'  => 100,
		);
		
		// 8. YOAST DESCRIPTION
		$yoast_desc_system = 'Du er SEO ekspert. Skriv KUN beskrivelsen, ingen citationstegn, ingen ekstra tekst.';
		$yoast_desc_user = sprintf(
			'Skriv en SEO meta description (140-160 tegn) om hvad klokken er i %s og tidszoner. KUN beskrivelsen.',
			$name_local
		);
		$batch_requests['yoast_desc'] = array(
			'system'      => $yoast_desc_system,
			'user'        => $yoast_desc_user,
			'temperature' => 0.7,
			'max_tokens'  => 200,
		);
		
		// Execute all requests in parallel
		$results = $this->call_openai_api_batch( $api_key, $model, $batch_requests );
		
		if ( false === $results ) {
			WTA_Logger::error( 'All parallel API calls failed for country', array( 'post_id' => $post_id ) );
			return false;
		}
		
		// Extract results (with fallbacks for failed individual requests)
		$intro = ! empty( $results['intro'] ) ? $results['intro'] : '';
		$timezone_content = ! empty( $results['timezone'] ) ? $results['timezone'] : '';
		$cities_content = ! empty( $results['cities'] ) ? $results['cities'] : '';
		$weather_content = ! empty( $results['weather'] ) ? $results['weather'] : '';
		$culture_content = ! empty( $results['culture'] ) ? $results['culture'] : '';
		$travel_content = ! empty( $results['travel'] ) ? $results['travel'] : '';
		$yoast_title = ! empty( $results['yoast_title'] ) ? $results['yoast_title'] : '';
		$yoast_desc = ! empty( $results['yoast_desc'] ) ? $results['yoast_desc'] : '';
		
		// Add dynamic shortcode for live city clocks (uses default count)
		$cities_content .= "\n\n" . '[wta_major_cities]';
		
		// === COMBINE ALL SECTIONS ===
		// Add paragraph breaks to make content more readable
		$intro = $this->add_paragraph_breaks( $intro );
		$timezone_content = $this->add_paragraph_breaks( $timezone_content );
		$cities_content = $this->add_paragraph_breaks( $cities_content );
		$weather_content = $this->add_paragraph_breaks( $weather_content );
		$culture_content = $this->add_paragraph_breaks( $culture_content );
		$travel_content = $this->add_paragraph_breaks( $travel_content );
		
		$full_content = $intro . "\n\n";
		$full_content .= $city_list;
		$full_content .= '<h2>Tidszoner i ' . esc_html( $name_local ) . '</h2>' . "\n" . $timezone_content . "\n\n";
		$full_content .= '<h2>Hvad er klokken i de største byer i ' . esc_html( $name_local ) . '?</h2>' . "\n" . $cities_content . "\n\n";
		$full_content .= '<h2>Vejr og klima i ' . esc_html( $name_local ) . '</h2>' . "\n" . $weather_content . "\n\n";
		$full_content .= '<h2>Tidskultur og dagligdag i ' . esc_html( $name_local ) . '</h2>' . "\n" . $culture_content . "\n\n";
		$full_content .= '<h2>Hvad du skal vide om tid når du rejser til ' . esc_html( $name_local ) . '</h2>' . "\n" . $travel_content;
		
		return array(
			'content'     => $full_content,
			'yoast_title' => $yoast_title,
			'yoast_desc'  => $yoast_desc,
		);
	}

	/**
	 * Generate city content (6 sections with auto-linked recommendations).
	 *
	 * @since    2.19.0
	 * @param    int $post_id Post ID.
	 * @return   array|false  Generated content or false on failure.
	 */
	private function generate_city_content( $post_id, $force_ai = false ) {
		// Check if test mode is enabled (template content instead of AI)
		// Skip test mode if force_ai is true (manual single-post regeneration)
		$test_mode = get_option( 'wta_test_mode', 0 );
		if ( $test_mode && ! $force_ai ) {
			WTA_Logger::info( 'Test mode enabled - using template content (no AI costs)', array( 'post_id' => $post_id ) );
			return $this->generate_template_city_content( $post_id );
		}
		
		if ( $force_ai ) {
			WTA_Logger::info( 'Force AI enabled - using real AI (ignore test mode)', array( 'post_id' => $post_id ) );
		}
		
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return false;
		}

		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		$temperature = (float) get_option( 'wta_openai_temperature', 0.7 );
		
		$name_local = get_the_title( $post_id );
		$name_original = get_post_meta( $post_id, 'wta_name_original', true );
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );
		$latitude = get_post_meta( $post_id, 'wta_latitude', true );
		$longitude = get_post_meta( $post_id, 'wta_longitude', true );
		
		// Get parent country and continent names
		$parent_country_id = wp_get_post_parent_id( $post_id );
		$country_name = $parent_country_id ? get_the_title( $parent_country_id ) : '';
		$parent_continent_id = $parent_country_id ? wp_get_post_parent_id( $parent_country_id ) : 0;
		$continent_name = $parent_continent_id ? get_the_title( $parent_continent_id ) : '';
		
		// Build variables for prompts (no longer need specific city/country lists)
		$variables = array(
			'{location_name}'       => $name_original,
			'{location_name_local}' => $name_local,
			'{country_name}'        => $country_name,
			'{continent_name}'      => $continent_name,
			'{timezone}'            => $timezone,
			'{latitude}'            => $latitude,
			'{longitude}'           => $longitude,
			'{base_country_name}'   => get_option( 'wta_base_country_name', 'Danmark' ),
		);
		
		// ==========================================
		// PARALLEL API CALLS (v2.35.31)
		// Execute all 8 API calls simultaneously for 3x speed boost!
		// ==========================================
		
		// Prepare all requests
		$batch_requests = array();
		
		// 1. INTRO
		$intro_system = get_option( 'wta_prompt_city_intro_system', '' );
		$intro_user = get_option( 'wta_prompt_city_intro_user', '' );
		$batch_requests['intro'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $intro_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $intro_user ),
			'temperature' => $temperature,
			'max_tokens'  => 600,
		);
		
		// 2. TIMEZONE
		$tz_system = get_option( 'wta_prompt_city_timezone_system', '' );
		$tz_user = get_option( 'wta_prompt_city_timezone_user', '' );
		$batch_requests['timezone'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $tz_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $tz_user ),
			'temperature' => $temperature,
			'max_tokens'  => 700,
		);
		
		// 3. ATTRACTIONS
		$attr_system = get_option( 'wta_prompt_city_attractions_system', '' );
		$attr_user = get_option( 'wta_prompt_city_attractions_user', '' );
		$batch_requests['attractions'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $attr_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $attr_user ),
			'temperature' => $temperature,
			'max_tokens'  => 700,
		);
		
		// 4. PRACTICAL
		$pract_system = get_option( 'wta_prompt_city_practical_system', '' );
		$pract_user = get_option( 'wta_prompt_city_practical_user', '' );
		$batch_requests['practical'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $pract_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $pract_user ),
			'temperature' => $temperature,
			'max_tokens'  => 700,
		);
		
		// 5. NEARBY CITIES
		$near_cities_system = get_option( 'wta_prompt_city_nearby_cities_system', '' );
		$near_cities_user = get_option( 'wta_prompt_city_nearby_cities_user', '' );
		$batch_requests['nearby_cities'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $near_cities_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $near_cities_user ),
			'temperature' => $temperature,
			'max_tokens'  => 150,
		);
		
		// 6. NEARBY COUNTRIES
		$near_countries_system = get_option( 'wta_prompt_city_nearby_countries_system', '' );
		$near_countries_user = get_option( 'wta_prompt_city_nearby_countries_user', '' );
		$batch_requests['nearby_countries'] = array(
			'system'      => str_replace( array_keys( $variables ), array_values( $variables ), $near_countries_system ),
			'user'        => str_replace( array_keys( $variables ), array_values( $variables ), $near_countries_user ),
			'temperature' => $temperature,
			'max_tokens'  => 150,
		);
		
		// 7. YOAST TITLE
		$yoast_title_system = 'Du er SEO ekspert. Skriv KUN titlen, ingen citationstegn, ingen ekstra tekst.';
		$yoast_title_user = sprintf(
			'Skriv en SEO meta title (50-60 tegn) for en side om hvad klokken er i %s. Inkluder "Hvad er klokken" eller "Tidszoner". KUN titlen.',
			$name_local
		);
		$batch_requests['yoast_title'] = array(
			'system'      => $yoast_title_system,
			'user'        => $yoast_title_user,
			'temperature' => 0.7,
			'max_tokens'  => 100,
		);
		
		// 8. YOAST DESCRIPTION
		$yoast_desc_system = 'Du er SEO ekspert. Skriv KUN beskrivelsen, ingen citationstegn, ingen ekstra tekst.';
		$yoast_desc_user = sprintf(
			'Skriv en SEO meta description (140-160 tegn) om hvad klokken er i %s og tidszoner. KUN beskrivelsen.',
			$name_local
		);
		$batch_requests['yoast_desc'] = array(
			'system'      => $yoast_desc_system,
			'user'        => $yoast_desc_user,
			'temperature' => 0.7,
			'max_tokens'  => 200,
		);
		
		// Execute all requests in parallel
		$results = $this->call_openai_api_batch( $api_key, $model, $batch_requests );
		
		if ( false === $results ) {
			WTA_Logger::error( 'All parallel API calls failed for city', array( 'post_id' => $post_id ) );
			return false;
		}
		
		// Extract results (with fallbacks for failed individual requests)
		$intro = ! empty( $results['intro'] ) ? $results['intro'] : '';
		$timezone_content = ! empty( $results['timezone'] ) ? $results['timezone'] : '';
		$attractions_content = ! empty( $results['attractions'] ) ? $results['attractions'] : '';
		$practical_content = ! empty( $results['practical'] ) ? $results['practical'] : '';
		$nearby_cities_intro = ! empty( $results['nearby_cities'] ) ? $results['nearby_cities'] : '';
		$nearby_countries_intro = ! empty( $results['nearby_countries'] ) ? $results['nearby_countries'] : '';
		$yoast_title = ! empty( $results['yoast_title'] ) ? $results['yoast_title'] : '';
		$yoast_desc = ! empty( $results['yoast_desc'] ) ? $results['yoast_desc'] : '';
		
		// === COMBINE ALL SECTIONS ===
		$intro = $this->add_paragraph_breaks( $intro );
		$timezone_content = $this->add_paragraph_breaks( $timezone_content );
		$attractions_content = $this->add_paragraph_breaks( $attractions_content );
		$practical_content = $this->add_paragraph_breaks( $practical_content );
		
		$full_content = $intro . "\n\n";
		$full_content .= '<h2>Tidszone i ' . esc_html( $name_local ) . '</h2>' . "\n" . $timezone_content . "\n\n";
		$full_content .= '<h2>Seværdigheder og aktiviteter i ' . esc_html( $name_local ) . '</h2>' . "\n" . $attractions_content . "\n\n";
		$full_content .= '<h2>Praktisk information for besøgende</h2>' . "\n" . $practical_content . "\n\n";
		
		// Nearby cities section with dynamic shortcode
		$full_content .= '<div id="nearby-cities"><h2>Nærliggende byer værd at besøge</h2>' . "\n";
		if ( ! empty( $nearby_cities_intro ) ) {
			$full_content .= '<p>' . $nearby_cities_intro . "</p>\n";
		}
		$full_content .= '[wta_nearby_cities]' . "\n</div>\n\n";  // Uses default count=60
		
		// Regional centres section (v2.35.64) - Dynamic country name
		$full_content .= '<div id="regional-centres"><h2>Byer i forskellige dele af ' . esc_html( $country_name ) . '</h2>' . "\n";
		$full_content .= '<p>Udforsk større byer spredt over hele ' . esc_html( $country_name ) . '.</p>' . "\n";
		$full_content .= '[wta_regional_centres]' . "\n</div>\n\n";
		
		// Nearby countries section with dynamic shortcode
		$full_content .= '<div id="nearby-countries"><h2>Udforsk nærliggende lande</h2>' . "\n";
		if ( ! empty( $nearby_countries_intro ) ) {
			$full_content .= '<p>' . $nearby_countries_intro . "</p>\n";
		}
		$full_content .= '[wta_nearby_countries]' . "\n</div>\n\n";  // Uses default count=18
		
		// Global time comparison section
		$full_content .= '[wta_global_time_comparison]';
		
		return array(
			'content'     => $full_content,
			'yoast_title' => $yoast_title,
			'yoast_desc'  => $yoast_desc,
		);
	}

	/**
	 * Find nearby cities within distance threshold.
	 *
	 * @since    2.19.0
	 * @param    int    $current_city_id Current city ID.
	 * @param    float  $lat             Current latitude.
	 * @param    float  $lon             Current longitude.
	 * @param    int    $count           Number of cities to return.
	 * @return   array                   Array of city data with id and distance.
	 */
	private function get_nearby_cities( $current_city_id, $lat, $lon, $count = 4 ) {
		if ( empty( $lat ) || empty( $lon ) ) {
			return array();
		}
		
		$parent_country_id = wp_get_post_parent_id( $current_city_id );
		if ( ! $parent_country_id ) {
			return array();
		}
		
		// Get all cities in same country
		$cities = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $parent_country_id,
			'posts_per_page' => -1,
			'post__not_in'   => array( $current_city_id ),
			'post_status'    => array( 'publish', 'draft' ),
			'meta_query'     => array(
				array(
					'key'     => 'wta_latitude',
					'compare' => 'EXISTS'
				),
				array(
					'key'     => 'wta_longitude',
					'compare' => 'EXISTS'
				)
			)
		) );
		
		$cities_with_distance = array();
		foreach ( $cities as $city ) {
			$city_lat = get_post_meta( $city->ID, 'wta_latitude', true );
			$city_lon = get_post_meta( $city->ID, 'wta_longitude', true );
			
			if ( empty( $city_lat ) || empty( $city_lon ) ) {
				continue;
			}
			
			$distance = $this->calculate_distance( $lat, $lon, $city_lat, $city_lon );
			
			// Only include cities within 500km
			if ( $distance <= 500 ) {
				$cities_with_distance[] = array(
					'id'       => $city->ID,
					'distance' => $distance
				);
			}
		}
		
		// Sort by distance
		usort( $cities_with_distance, function( $a, $b ) {
			return $a['distance'] <=> $b['distance'];
		});
		
		return array_slice( $cities_with_distance, 0, $count );
	}

	/**
	 * Calculate distance between two GPS coordinates (Haversine formula).
	 *
	 * @since    2.19.0
	 * @param    float $lat1 Latitude 1.
	 * @param    float $lon1 Longitude 1.
	 * @param    float $lat2 Latitude 2.
	 * @param    float $lon2 Longitude 2.
	 * @return   float       Distance in kilometers.
	 */
	private function calculate_distance( $lat1, $lon1, $lat2, $lon2 ) {
		$earth_radius = 6371; // km
		
		$dLat = deg2rad( $lat2 - $lat1 );
		$dLon = deg2rad( $lon2 - $lon1 );
		
		$a = sin( $dLat / 2 ) * sin( $dLat / 2 ) +
			 cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
			 sin( $dLon / 2 ) * sin( $dLon / 2 );
		
		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
		
		return $earth_radius * $c;
	}

	/**
	 * Find nearby countries in same continent.
	 *
	 * @since    2.19.0
	 * @param    int $continent_id      Continent ID.
	 * @param    int $current_country_id Current country ID to exclude.
	 * @param    int $count              Number of countries to return.
	 * @return   array                   Array of country IDs.
	 */
	private function get_nearby_countries( $continent_id, $current_country_id, $count = 5 ) {
		if ( ! $continent_id ) {
			return array();
		}
		
		$countries = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $continent_id,
			'posts_per_page' => $count + 1,
			'post__not_in'   => array( $current_country_id ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'draft' ),
		) );
		
		$country_ids = array();
		foreach ( $countries as $country ) {
			$country_ids[] = $country->ID;
			if ( count( $country_ids ) >= $count ) {
				break;
			}
		}
		
		return $country_ids;
	}

	/**
	 * Auto-link location names in content.
	 *
	 * @since    2.19.0
	 * @param    string $content      Content to process.
	 * @param    array  $locations    Array of location data (city: id/distance, country: just IDs).
	 * @param    string $type         Type: 'city' or 'country'.
	 * @return   string               Content with auto-linked location names.
	 */
	private function auto_link_locations( $content, $locations, $type = 'city' ) {
		foreach ( $locations as $location ) {
			$location_id = ( $type === 'city' ) ? $location['id'] : $location;
			$location_name = get_post_field( 'post_title', $location_id );
			$location_url = get_permalink( $location_id );
			
			if ( empty( $location_name ) || empty( $location_url ) ) {
				continue;
			}
			
			// Use word boundaries to avoid partial matches
			// Only link first occurrence to avoid over-linking
			$pattern = '/(?<!<a[^>]*>)(?<!href=")(?<!title=")\b' . preg_quote( $location_name, '/' ) . '\b(?![^<]*<\/a>)/u';
			$replacement = '<a href="' . esc_url( $location_url ) . '" class="wta-auto-link">' . esc_html( $location_name ) . '</a>';
			
			$content = preg_replace( $pattern, $replacement, $content, 1 );
		}
		
		return $content;
	}

	/**
	 * Generate standard content (for cities only).
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
		
		// For cities, include country name in prompt
		if ( 'city' === $type ) {
			$parent_id = wp_get_post_parent_id( $post_id );
			if ( $parent_id ) {
				$country_name = get_post_field( 'post_title', $parent_id );
				$user = sprintf(
					'Skriv en SEO meta title (max 60 tegn) der SKAL starte med "Hvad er klokken i %s, %s" og fortsæt med relevant SEO tekst der passer inden for 60 tegn. KUN titlen.',
					$name,
					$country_name
				);
			} else {
				$user = sprintf(
					'Skriv en SEO meta title (50-60 tegn) for en side om hvad klokken er i %s. Start med "Hvad er klokken i %s". KUN titlen.',
					$name,
					$name
				);
			}
		} else {
			// For countries
			$user = sprintf(
				'Skriv en SEO meta title (50-60 tegn) for en side om hvad klokken er i %s. Inkluder "Hvad er klokken" eller "Tidszoner". KUN titlen.',
				$name
			);
		}
		
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
	 * Call OpenAI API with parallel requests using cURL multi-handles.
	 *
	 * Executes multiple OpenAI API requests simultaneously for massive speed boost.
	 * Reduces city generation time from ~45s to ~15s (3x faster)!
	 *
	 * @since    2.35.31
	 * @param    string $api_key  API key.
	 * @param    string $model    Model name.
	 * @param    array  $requests Array of requests with format:
	 *                            array(
	 *                              'key1' => array( 'system' => '...', 'user' => '...', 'temperature' => 0.7, 'max_tokens' => 600 ),
	 *                              'key2' => array( ... )
	 *                            )
	 * @return   array            Array of results with same keys, or false on failure.
	 */
	private function call_openai_api_batch( $api_key, $model, $requests ) {
		$start_time = microtime( true );
		$url = 'https://api.openai.com/v1/chat/completions';
		
		// Initialize cURL multi-handle
		$mh = curl_multi_init();
		$handles = array();
		
		// Create all cURL handles
		foreach ( $requests as $key => $req ) {
			$body = array(
				'model'       => $model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => $req['system'],
					),
					array(
						'role'    => 'user',
						'content' => $req['user'],
					),
				),
				'temperature' => isset( $req['temperature'] ) ? $req['temperature'] : 0.7,
				'max_tokens'  => isset( $req['max_tokens'] ) ? $req['max_tokens'] : 600,
			);
			
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer ' . $api_key,
				'Content-Type: application/json',
			) );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );
			
			curl_multi_add_handle( $mh, $ch );
			$handles[ $key ] = $ch;
		}
		
		// Execute all queries simultaneously
		$running = null;
		do {
			curl_multi_exec( $mh, $running );
			curl_multi_select( $mh, 0.1 );
		} while ( $running > 0 );
		
		// Collect results
		$results = array();
		$failed = 0;
		
		foreach ( $handles as $key => $ch ) {
			$content = curl_multi_getcontent( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			
			if ( $http_code === 200 && ! empty( $content ) ) {
				$data = json_decode( $content, true );
				
				if ( isset( $data['choices'][0]['message']['content'] ) ) {
					$results[ $key ] = $this->clean_ai_content( $data['choices'][0]['message']['content'] );
				} else {
					WTA_Logger::warning( 'Parallel API call missing content', array( 'key' => $key, 'response' => $data ) );
					$results[ $key ] = false;
					$failed++;
				}
			} else {
				$error = curl_error( $ch );
				WTA_Logger::error( 'Parallel API call failed', array(
					'key'       => $key,
					'http_code' => $http_code,
					'error'     => $error,
				) );
				$results[ $key ] = false;
				$failed++;
			}
			
			curl_multi_remove_handle( $mh, $ch );
			curl_close( $ch );
		}
		
		curl_multi_close( $mh );
		
		$elapsed = round( microtime( true ) - $start_time, 2 );
		
		WTA_Logger::info( 'Parallel API batch complete', array(
			'requests' => count( $requests ),
			'failed'   => $failed,
			'elapsed'  => $elapsed . 's',
		) );
		
		// Return false if ALL requests failed
		if ( $failed === count( $requests ) ) {
			return false;
		}
		
		return $results;
	}

	/**
	 * Call OpenAI API (single request - kept for backwards compatibility).
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

	/**
	 * Generate template-based content for cities (test mode, no AI costs).
	 *
	 * @since    2.34.2
	 * @param    int $post_id Post ID.
	 * @return   array        Generated content array.
	 */
	private function generate_template_city_content( $post_id ) {
		$name_local = get_the_title( $post_id );
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );
		
		// Get parent names
		$parent_country_id = wp_get_post_parent_id( $post_id );
		$country_name = $parent_country_id ? get_the_title( $parent_country_id ) : 'landet';
		$parent_continent_id = $parent_country_id ? wp_get_post_parent_id( $parent_country_id ) : 0;
		$continent_name = $parent_continent_id ? get_the_title( $parent_continent_id ) : 'kontinentet';
		
		// Build simple template content (test mode - no AI)
		$content = '';
		
		// Intro
		$content .= "<p>Dette er testindhold for {$name_local}. Byen ligger i {$country_name}, {$continent_name}. Tidszonen er {$timezone}. Dette indhold genereres uden brug af AI for at spare omkostninger under test.</p>\n\n";
		
		// Timezone section
		$content .= "<h2>Tidszone i {$name_local}</h2>\n";
		$content .= "<p>Dummy tekst om tidszoner. {$name_local} følger {$timezone}. Dette er testindhold uden AI-generering.</p>\n\n";
		
		// Attractions section
		$content .= "<h2>Seværdigheder og aktiviteter i {$name_local}</h2>\n";
		$content .= "<p>Dummy tekst om seværdigheder. Dette er generisk testindhold.</p>\n\n";
		
		// Practical info
		$content .= "<h2>Praktisk information for besøgende</h2>\n";
		$content .= "<p>Dummy tekst med praktisk information. Test mode aktiveret.</p>\n\n";
		
		// Nearby cities (with shortcode)
		$content .= "<div id=\"nearby-cities\"><h2>Nærliggende byer værd at besøge</h2>\n";
		$content .= "<p>Dummy intro tekst om nærliggende byer.</p>\n";
		$content .= "[wta_nearby_cities]\n</div>\n\n";  // Uses default count=60
		
		// Regional centres (v2.35.64) - Dynamic country name
		$content .= "<div id=\"regional-centres\"><h2>Byer i forskellige dele af " . esc_html( $country_name ) . "</h2>\n";
		$content .= "<p>Udforsk større byer spredt over hele " . esc_html( $country_name ) . ".</p>\n";
		$content .= "[wta_regional_centres]\n</div>\n\n";
		
		// Nearby countries (with shortcode)
		$content .= "<div id=\"nearby-countries\"><h2>Udforsk nærliggende lande</h2>\n";
		$content .= "<p>Dummy intro tekst om nærliggende lande.</p>\n";
		$content .= "[wta_nearby_countries]\n</div>\n\n";  // Uses default count=18
		
		// Global time comparison
		$content .= "<div id=\"global-time\"><h2>Sammenlign med storbyer rundt om i verden</h2>\n";
		$content .= "<p>Dummy tekst om global tidssammenligning.</p>\n";
		$content .= "[wta_global_time_comparison]\n</div>";
		
		return array(
			'content' => $content,
			'yoast_title' => "Hvad er klokken i {$name_local}? Test mode",
			'yoast_desc' => "Test mode indhold for {$name_local}, {$country_name}. Tidszone {$timezone}."
		);
	}

	/**
	 * Generate template-based content for countries (test mode, no AI costs).
	 *
	 * @since    2.34.2
	 * @param    int $post_id Post ID.
	 * @return   array        Generated content array.
	 */
	private function generate_template_country_content( $post_id ) {
		$name_local = get_the_title( $post_id );
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );
		
		// Get parent continent
		$parent_id = wp_get_post_parent_id( $post_id );
		$continent_name = $parent_id ? get_the_title( $parent_id ) : 'kontinentet';
		
		// Build simple template content (test mode - no AI)
		$content = '';
		
		// Intro
		$content .= "<p>Dette er testindhold for {$name_local}. Landet ligger i {$continent_name}. Tidszonen er {$timezone}. Dette indhold genereres uden brug af AI for at spare omkostninger under test.</p>\n\n";
		
		// Timezone section
		$content .= "<h2>Tidszoner i {$name_local}</h2>\n";
		$content .= "<p>Dummy tekst om tidszoner i {$name_local}. Test mode aktiveret.</p>\n\n";
		
		// Major cities section
		$content .= "<h2>Hvad er klokken i de største byer i {$name_local}?</h2>\n";
		$content .= "<p>Dummy tekst om største byer.</p>\n";
		$content .= "[wta_major_cities]\n\n";
		
		// Weather section
		$content .= "<h2>Vejr og klima i {$name_local}</h2>\n";
		$content .= "<p>Dummy tekst om vejr og klima. Test mode.</p>\n\n";
		
		// Culture section
		$content .= "<h2>Tidskultur og dagligdag i {$name_local}</h2>\n";
		$content .= "<p>Dummy tekst om kultur. Test mode.</p>\n\n";
		
		// Travel section
		$content .= "<h2>Hvad du skal vide om tid når du rejser til {$name_local}</h2>\n";
		$content .= "<p>Dummy tekst om rejseinformation. Test mode.</p>\n\n";
		
		// Child locations section
		$content .= "<div id=\"child-locations\"><h2>Udforsk byer i {$name_local}</h2>\n";
		$content .= "<p>Dummy intro tekst om byer.</p>\n";
		$content .= "[wta_child_locations]\n</div>";
		
		return array(
			'content' => $content,
			'yoast_title' => "Hvad er klokken i {$name_local}? Test mode",
			'yoast_desc' => "Test mode indhold for {$name_local}, {$continent_name}."
		);
	}

	/**
	 * Generate template-based content for continents (test mode, no AI costs).
	 *
	 * @since    2.34.2
	 * @param    int $post_id Post ID.
	 * @return   array        Generated content array.
	 */
	private function generate_template_continent_content( $post_id ) {
		$name_local = get_the_title( $post_id );
		
		// Build simple template content (test mode - no AI)
		$content = '';
		
		// Intro
		$content .= "<p>Dette er testindhold for {$name_local}. Dette indhold genereres uden brug af AI for at spare omkostninger under test.</p>\n\n";
		
		// Timezone overview
		$content .= "<h2>Tidszoner i {$name_local}</h2>\n";
		$content .= "<p>Dummy tekst om tidszoner på kontinentet. Test mode aktiveret.</p>\n\n";
		
		// Major cities section
		$content .= "<h2>Hvad er klokken i de største byer i {$name_local}?</h2>\n";
		$content .= "<p>Dummy tekst om største byer.</p>\n";
		$content .= "[wta_major_cities]\n\n";
		
		// Geography section
		$content .= "<h2>Geografi og beliggenhed</h2>\n";
		$content .= "<p>Dummy tekst om geografi. Test mode.</p>\n\n";
		
		// Facts section
		$content .= "<h2>Interessante fakta om {$name_local}</h2>\n";
		$content .= "<p>Dummy tekst med fakta. Test mode.</p>\n\n";
		
		// Child locations section
		$content .= "<div id=\"child-locations\"><h2>Lande i {$name_local}</h2>\n";
		$content .= "<p>Dummy intro tekst om lande.</p>\n";
		$content .= "[wta_child_locations]\n</div>";
		
		return array(
			'content' => $content,
			'yoast_title' => "Hvad er klokken i {$name_local}? Test mode",
			'yoast_desc' => "Test mode indhold for {$name_local}. Ingen AI-omkostninger."
		);
	}
}


