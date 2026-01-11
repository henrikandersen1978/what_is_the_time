<?php
/**
 * Single structure processor for Action Scheduler (Pilanto-AI Model).
 *
 * Processes ONE continent/country/city per action, allowing Action Scheduler
 * to parallelize via async HTTP requests. This replaces batch processing.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/processors
 * @since      3.0.43
 */

class WTA_Single_Structure_Processor {

	/**
	 * Cached admin user ID for post authorship.
	 *
	 * @since    3.0.43
	 * @var      int|null
	 */
	private static $admin_user_id = null;

	/**
	 * Cached language templates.
	 *
	 * @since    3.2.0
	 * @var      array|null
	 */
	private static $templates_cache = null;

	/**
	 * Get language template string.
	 *
	 * @since    3.2.0
	 * @param    string $key Template key (e.g., 'continent_h1', 'city_title')
	 * @return   string Template string with %s placeholders
	 */
	private static function get_template( $key ) {
		// Load templates once
		if ( self::$templates_cache === null ) {
			// Try to get from WordPress options (loaded via "Load Default Prompts")
			$templates = get_option( 'wta_templates', array() );
			
			if ( ! empty( $templates ) && is_array( $templates ) ) {
				self::$templates_cache = $templates;
			} else {
				// Fallback to Danish templates if not loaded
				self::$templates_cache = array(
					'continent_h1'    => 'Aktuel tid i lande og byer i %s',
					'continent_title' => 'Hvad er klokken i %s? Tidszoner og aktuel tid',
					'country_h1'      => 'Aktuel tid i byer i %s',
					'country_title'   => 'Hvad er klokken i %s?',
					'city_h1'         => 'Aktuel tid i %s, %s',
					'city_title'      => 'Hvad er klokken i %s, %s?',
					'faq_intro'       => 'Her finder du svar på de mest almindelige spørgsmål om tid i %s.',
				);
			}
		}
		
		return isset( self::$templates_cache[ $key ] ) ? self::$templates_cache[ $key ] : '';
	}

	/**
	 * Create continent post.
	 *
	 * @since    3.0.43
	 * @since    3.0.54  Added execution time logging.
	 * @param    string $name       Original continent name.
	 * @param    string $name_local Local (Danish) continent name.
	 */
	public function create_continent( $name, $name_local ) {
		$start_time = microtime( true );
		
		// Rebuild data array from unpacked arguments
		$data = array(
			'name'       => $name,
			'name_local' => $name_local,
		);
		try {
			// Check if post already exists
			$existing = get_posts( array(
				'name'                   => sanitize_title( $data['name_local'] ),
				'post_type'              => WTA_POST_TYPE,
				'post_status'            => array( 'publish', 'draft' ),
				'numberposts'            => 1,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );
			
			if ( ! empty( $existing ) ) {
				WTA_Logger::info( 'Continent post already exists', array( 'name' => $data['name_local'] ) );
				return;
			}

			// Create post
			$post_id = wp_insert_post( array(
				'post_title'   => $data['name_local'],
				'post_name'    => sanitize_title( $data['name_local'] ),
				'post_type'    => WTA_POST_TYPE,
				'post_status'  => 'draft',
				'post_parent'  => 0,
				'post_author'  => $this->get_admin_user_id(),
			) );

			if ( is_wp_error( $post_id ) ) {
				throw new Exception( $post_id->get_error_message() );
			}

			// Save metadata
			update_post_meta( $post_id, 'wta_type', 'continent' );
			update_post_meta( $post_id, 'wta_name_original', $data['name'] );
			update_post_meta( $post_id, 'wta_name_local', $data['name_local'] );
			update_post_meta( $post_id, 'wta_continent', $data['name'] );
			update_post_meta( $post_id, 'wta_continent_code', WTA_Utils::get_continent_code( $data['name'] ) );
			update_post_meta( $post_id, 'wta_ai_status', 'pending' );
			
			// SEO metadata
			$seo_h1 = sprintf( self::get_template( 'continent_h1' ), $data['name_local'] );
			update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
			
			$yoast_title = sprintf( self::get_template( 'continent_title' ), $data['name_local'] );
			update_post_meta( $post_id, '_yoast_wpseo_title', $yoast_title );

			// v3.2.81: Schedule AI with delay to allow structure phase to complete
			// Continents don't need timezone, so AI can start after structure
			as_schedule_single_action(
				time() + 1800, // 30 min delay (after structure phase)
				'wta_generate_ai_content',
				array( $post_id, 'continent', false ),
				'wta_ai_content'
			);

			$execution_time = round( microtime( true ) - $start_time, 3 );
			
			WTA_Logger::info( '🌍 Continent post created', array(
				'post_id'        => $post_id,
				'name'           => $data['name_local'],
				'execution_time' => $execution_time . 's',
			) );

		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to create continent', array(
				'name'  => isset( $data['name'] ) ? $data['name'] : 'unknown',
				'error' => $e->getMessage(),
			) );
		}
	}

	/**
	 * Create country post.
	 *
	 * @since    3.0.43
	 * @since    3.0.54  Added execution time logging.
	 * @param    string      $name         Original country name.
	 * @param    string      $name_local   Local (Danish) country name.
	 * @param    string      $country_code ISO2 country code.
	 * @param    string      $country_id   Country ID.
	 * @param    string      $continent    Continent name.
	 * @param    float|null  $latitude     Latitude (optional).
	 * @param    float|null  $longitude    Longitude (optional).
	 * @param    int         $geonameid    GeoNames ID.
	 */
	public function create_country( $name, $name_local, $country_code, $country_id, $continent, $latitude = null, $longitude = null, $geonameid = 0 ) {
		$start_time = microtime( true );
		
		// Rebuild data array from unpacked arguments
		$data = array(
			'name'         => $name,
			'name_local'   => $name_local,
			'country_code' => $country_code,
			'country_id'   => $country_id,
			'continent'    => $continent,
			'latitude'     => $latitude,
			'longitude'    => $longitude,
			'geonameid'    => $geonameid,
		);
		try {
			// Find parent continent post
			$continent_name_local = WTA_AI_Translator::translate( $data['continent'], 'continent' );
			
			$parent_posts = get_posts( array(
				'name'                   => sanitize_title( $continent_name_local ),
				'post_type'              => WTA_POST_TYPE,
				'post_status'            => array( 'publish', 'draft' ),
				'numberposts'            => 1,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( empty( $parent_posts ) ) {
				// Parent not ready yet - reschedule for 5 seconds later
				as_schedule_single_action(
					time() + 5,
					'wta_create_country',
					array( $name, $name_local, $country_code, $country_id, $continent, $latitude, $longitude, $geonameid )
				);
				WTA_Logger::debug( 'Parent continent not found, rescheduling country', array(
					'continent' => $continent,
					'country'   => $name,
				) );
				return;
			}

			$parent = $parent_posts[0];

			// Check if post already exists
			$existing = get_posts( array(
				'name'                   => sanitize_title( $data['name_local'] ),
				'post_type'              => WTA_POST_TYPE,
				'post_parent'            => $parent->ID,
				'post_status'            => array( 'publish', 'draft' ),
				'numberposts'            => 1,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( ! empty( $existing ) ) {
				WTA_Logger::info( 'Country post already exists', array( 'name' => $data['name_local'] ) );
				return;
			}

			// Create post
			$post_id = wp_insert_post( array(
				'post_title'   => $data['name_local'],
				'post_name'    => sanitize_title( $data['name_local'] ),
				'post_type'    => WTA_POST_TYPE,
				'post_status'  => 'draft',
				'post_parent'  => $parent->ID,
				'post_author'  => $this->get_admin_user_id(),
			) );

			if ( is_wp_error( $post_id ) ) {
				throw new Exception( $post_id->get_error_message() );
			}

			// Save metadata
			update_post_meta( $post_id, 'wta_type', 'country' );
			update_post_meta( $post_id, 'wta_name_original', $data['name'] );
			update_post_meta( $post_id, 'wta_name_local', $data['name_local'] );
			update_post_meta( $post_id, 'wta_continent', $data['continent'] );
			update_post_meta( $post_id, 'wta_continent_code', WTA_Utils::get_continent_code( $data['continent'] ) );
			update_post_meta( $post_id, 'wta_country_code', $data['country_code'] );
			update_post_meta( $post_id, 'wta_country_id', $data['country_id'] );
			update_post_meta( $post_id, 'wta_ai_status', 'pending' );
			
			if ( isset( $data['wikidata_id'] ) && ! empty( $data['wikidata_id'] ) ) {
				update_post_meta( $post_id, 'wta_wikidata_id', $data['wikidata_id'] );
			}
			
			if ( isset( $data['latitude'] ) && ! empty( $data['latitude'] ) ) {
				update_post_meta( $post_id, 'wta_latitude', floatval( $data['latitude'] ) );
			}
			if ( isset( $data['longitude'] ) && ! empty( $data['longitude'] ) ) {
				update_post_meta( $post_id, 'wta_longitude', floatval( $data['longitude'] ) );
			}

			// Determine timezone
			if ( WTA_Timezone_Helper::is_complex_country( $data['country_code'] ) ) {
				update_post_meta( $post_id, 'wta_timezone', 'multiple' );
				update_post_meta( $post_id, 'wta_timezone_status', 'multiple' );
			} else {
				$timezone = WTA_Timezone_Helper::get_country_timezone( $data['country_code'] );
				if ( $timezone ) {
					update_post_meta( $post_id, 'wta_timezone', $timezone );
					update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
				} else {
					if ( isset( $data['latitude'] ) && isset( $data['longitude'] ) ) {
						update_post_meta( $post_id, 'wta_timezone_status', 'pending' );
						
						as_schedule_single_action(
							time(),
							'wta_lookup_timezone',
							array( $post_id, $data['latitude'], $data['longitude'] ),
							'wta_timezone'
						);
					}
				}
			}

			// SEO metadata
			$seo_h1 = sprintf( self::get_template( 'country_h1' ), $data['name_local'] );
			update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
			
			$yoast_title = sprintf( self::get_template( 'country_title' ), $data['name_local'] );
			update_post_meta( $post_id, '_yoast_wpseo_title', $yoast_title );

			// v3.2.81: Schedule AI with delay to allow structure phase to complete
			// Countries don't need timezone, so AI can start after structure
			as_schedule_single_action(
				time() + 1800, // 30 min delay (after structure phase)
				'wta_generate_ai_content',
				array( $post_id, 'country', false ),
				'wta_ai_content'
			);

			$execution_time = round( microtime( true ) - $start_time, 3 );
			
			WTA_Logger::info( '🌎 Country post created', array(
				'post_id'        => $post_id,
				'name'           => $data['name_local'],
				'execution_time' => $execution_time . 's',
			) );

		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to create country', array(
				'name'  => isset( $data['name'] ) ? $data['name'] : 'unknown',
				'error' => $e->getMessage(),
			) );
		}
	}

	/**
	 * Create city post.
	 *
	 * @since    3.0.43
	 * @param    string      $name         Original city name.
	 * @param    string      $name_local   Local (Danish) city name.
	 * @param    int         $geonameid    GeoNames ID.
	 * @param    string      $country_code ISO2 country code.
	 * @param    float       $latitude     Latitude.
	 * @param    float       $longitude    Longitude.
	 * @param    int         $population   Population.
	 */
	public function create_city( $name, $name_local, $geonameid, $country_code, $latitude, $longitude, $population ) {
		$start_time = microtime( true );
		
		// Rebuild data array from unpacked arguments
		$data = array(
			'name'         => $name,
			'name_local'   => $name_local,
			'geonameid'    => $geonameid,
			'country_code' => $country_code,
			'latitude'     => $latitude,
			'longitude'    => $longitude,
			'population'   => $population,
		);
		try {
			// Find parent country post
			$country_post_id = get_posts( array(
				'post_type'              => WTA_POST_TYPE,
				'post_status'            => array( 'publish', 'draft' ),
				'meta_query'             => array(
					'relation' => 'AND',
					array(
						'key'   => 'wta_country_code',
						'value' => $data['country_code'],
					),
					array(
						'key'   => 'wta_type',
						'value' => 'country',
					),
				),
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( empty( $country_post_id ) ) {
				// Parent not ready yet - reschedule for 5 seconds later
				as_schedule_single_action(
					time() + 5,
					'wta_create_city',
					array( $name, $name_local, $geonameid, $country_code, $latitude, $longitude, $population )
				);
				WTA_Logger::debug( 'Parent country not found, rescheduling city', array(
					'city'         => $name,
					'country_code' => $country_code,
				) );
				return;
			}

			$parent_id = $country_post_id[0];

			// Check if post already exists
			$existing = get_posts( array(
				'name'                   => sanitize_title( $data['name_local'] ),
				'post_type'              => WTA_POST_TYPE,
				'post_parent'            => $parent_id,
				'post_status'            => array( 'publish', 'draft' ),
				'numberposts'            => 1,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( ! empty( $existing ) ) {
				WTA_Logger::info( 'City post already exists', array( 'name' => $data['name_local'] ) );
				return;
			}

			// Create post
			$post_id = wp_insert_post( array(
				'post_title'   => $data['name_local'],
				'post_name'    => sanitize_title( $data['name_local'] ),
				'post_type'    => WTA_POST_TYPE,
				'post_status'  => 'draft',
				'post_parent'  => $parent_id,
				'post_author'  => $this->get_admin_user_id(),
			) );

			if ( is_wp_error( $post_id ) ) {
				throw new Exception( $post_id->get_error_message() );
			}

			// Get country meta
			$country_code = get_post_meta( $parent_id, 'wta_country_code', true );
			$continent_code = get_post_meta( $parent_id, 'wta_continent_code', true );

			// Save metadata
			update_post_meta( $post_id, 'wta_type', 'city' );
			update_post_meta( $post_id, 'wta_name_original', $data['name'] );
			update_post_meta( $post_id, 'wta_name_local', $data['name_local'] );
			update_post_meta( $post_id, 'wta_continent_code', $continent_code );
			update_post_meta( $post_id, 'wta_country_code', $country_code );
			update_post_meta( $post_id, 'wta_ai_status', 'pending' );
			
		if ( isset( $data['geonameid'] ) && ! empty( $data['geonameid'] ) ) {
			update_post_meta( $post_id, 'wta_geonames_id', intval( $data['geonameid'] ) );
		}
		
		// v3.0.64: Always save population (default to 1 if NULL/0 for sorting)
		// Small villages without population data still need a value for shortcode sorting
		$population = isset( $data['population'] ) && $data['population'] > 0 
			? intval( $data['population'] ) 
			: 1; // Default for small villages/towns without population data
		update_post_meta( $post_id, 'wta_population', $population );
			
			if ( isset( $data['wikidata_id'] ) && ! empty( $data['wikidata_id'] ) ) {
				update_post_meta( $post_id, 'wta_wikidata_id', $data['wikidata_id'] );
			}

			// GPS coordinates (with validation)
			$final_lat = isset( $data['latitude'] ) ? floatval( $data['latitude'] ) : null;
			$final_lon = isset( $data['longitude'] ) ? floatval( $data['longitude'] ) : null;
			$gps_source = isset( $data['gps_source'] ) ? $data['gps_source'] : 'geonames';

			if ( $final_lat !== null && $final_lon !== null ) {
				// Validate GPS
				if ( abs( $final_lat ) > 90 || abs( $final_lon ) > 180 ) {
					WTA_Logger::warning( 'City creation skipped - invalid GPS range', array(
						'city'      => $data['name'],
						'latitude'  => $final_lat,
						'longitude' => $final_lon,
					) );
					wp_delete_post( $post_id, true );
					return;
				}

				update_post_meta( $post_id, 'wta_latitude', $final_lat );
				update_post_meta( $post_id, 'wta_longitude', $final_lon );
				update_post_meta( $post_id, 'wta_gps_source', $gps_source );
			} else {
				WTA_Logger::warning( 'City creation skipped - no GPS available', array(
					'city' => $data['name'],
				) );
				wp_delete_post( $post_id, true );
			return;
		}

	// v3.0.72: Check if city processing is enabled
	$processing_enabled = get_option( 'wta_enable_city_processing', '0' );
	
	if ( $processing_enabled !== '1' ) {
		// Processing disabled - mark city as waiting for manual toggle
		update_post_meta( $post_id, 'wta_timezone_status', 'waiting_for_toggle' );
		update_post_meta( $post_id, 'wta_has_timezone', 0 );
		
		WTA_Logger::debug( 'City created but processing disabled (waiting for toggle)', array(
			'city' => $data['name_local'],
			'post_id' => $post_id,
		) );
		
		// SEO metadata (still add this even when waiting)
		$parent_country_name = get_post_field( 'post_title', $parent_id );
		$seo_h1 = sprintf( self::get_template( 'city_h1' ), $data['name_local'], $parent_country_name );
		update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
		
		$seo_title = sprintf( self::get_template( 'city_title' ), $data['name_local'], $parent_country_name );
		update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
		
		$execution_time = round( microtime( true ) - $start_time, 3 );
		
		WTA_Logger::info( '🏙️ City post created (waiting for processing toggle)', array(
			'post_id'        => $post_id,
			'name'           => $data['name_local'],
			'population'     => $data['population'],
			'execution_time' => $execution_time . 's',
		) );
		
		return; // Exit early - don't schedule timezone/AI yet
	}

	// Handle timezone
	if ( WTA_Timezone_Helper::is_complex_country( $country_code ) ) {
			// Complex country - need API lookup
			if ( $final_lat !== null && $final_lon !== null ) {
				update_post_meta( $post_id, 'wta_timezone_status', 'pending' );
				update_post_meta( $post_id, 'wta_has_timezone', 0 ); // v3.0.58: Flag for AI queue

			// v3.0.65: Schedule immediately (no delay to prevent cleanup race condition)
			as_schedule_single_action(
				time(),
				'wta_lookup_timezone',
				array( $post_id, $final_lat, $final_lon ),
				'wta_timezone'
			);
			}
		} else {
			// Simple country - try hardcoded list
			$timezone = WTA_Timezone_Helper::get_country_timezone( $country_code );
			if ( $timezone ) {
				update_post_meta( $post_id, 'wta_timezone', $timezone );
				update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
				update_post_meta( $post_id, 'wta_has_timezone', 1 ); // v3.0.58: Flag for AI queue
				
				// v3.2.82: TRUE Sequential Phases - Delay AI for simple countries!
				// These cities get timezone from country list (no API call needed)
				// But AI is delayed 30 min to allow structure phase to complete first
				as_schedule_single_action(
					time() + 1800, // 30 min delay (same as continents/countries)
					'wta_generate_ai_content',
					array( $post_id, 'city', false ),
					'wta_ai_content'
				);
			} else {
				// Country not in list - use API
				if ( $final_lat !== null && $final_lon !== null ) {
					update_post_meta( $post_id, 'wta_timezone_status', 'pending' );
					update_post_meta( $post_id, 'wta_has_timezone', 0 ); // v3.0.58: Flag for AI queue
				
				// v3.0.65: Schedule immediately (no delay to prevent cleanup race condition)
				as_schedule_single_action(
					time(),
					'wta_lookup_timezone',
					array( $post_id, $final_lat, $final_lon ),
					'wta_timezone'
				);
				}
			}
		}

			// SEO metadata
			$parent_country_name = get_post_field( 'post_title', $parent_id );
			$seo_h1 = sprintf( self::get_template( 'city_h1' ), $data['name_local'], $parent_country_name );
			update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
			
			$seo_title = sprintf( self::get_template( 'city_title' ), $data['name_local'], $parent_country_name );
			update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );

			$execution_time = round( microtime( true ) - $start_time, 3 );
			
			WTA_Logger::info( '🏙️ City post created', array(
				'post_id'        => $post_id,
				'name'           => $data['name_local'],
				'population'     => $data['population'],
				'execution_time' => $execution_time . 's',
			) );

		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to create city', array(
				'name'  => isset( $data['name'] ) ? $data['name'] : 'unknown',
				'error' => $e->getMessage(),
			) );
		}
	}

	/**
	 * Get admin user ID for post authorship (cached).
	 *
	 * @since    3.0.43
	 * @return   int Admin user ID.
	 */
	private function get_admin_user_id() {
		if ( null === self::$admin_user_id ) {
			$admin_users = get_users( array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
			) );
			self::$admin_user_id = ! empty( $admin_users ) ? $admin_users[0]->ID : 1;
		}
		return self::$admin_user_id;
	}
}

