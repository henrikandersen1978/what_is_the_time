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
			$seo_h1 = sprintf( 'Aktuel tid i lande og byer i %s', $data['name_local'] );
			update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
			
			$yoast_title = sprintf( 'Hvad er klokken i %s? Tidszoner og aktuel tid', $data['name_local'] );
			update_post_meta( $post_id, '_yoast_wpseo_title', $yoast_title );

			// Schedule AI content generation
			as_schedule_single_action(
				time(),
				'wta_generate_ai_content',
				array( $post_id, 'continent', false ),  // post_id, type, force_ai
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
							array(
								'post_id' => $post_id,
								'lat'     => $data['latitude'],
								'lng'     => $data['longitude'],
							)
						);
					}
				}
			}

			// SEO metadata
			$seo_h1 = sprintf( 'Aktuel tid i byer i %s', $data['name_local'] );
			update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
			
			$yoast_title = sprintf( 'Hvad er klokken i %s?', $data['name_local'] );
			update_post_meta( $post_id, '_yoast_wpseo_title', $yoast_title );

			// Schedule AI content generation
			as_schedule_single_action(
				time(),
				'wta_generate_ai_content',
				array( $post_id, 'country', false ),  // post_id, type, force_ai
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
			
			if ( isset( $data['population'] ) && $data['population'] > 0 ) {
				update_post_meta( $post_id, 'wta_population', intval( $data['population'] ) );
			}
			
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

			// Handle timezone
			if ( WTA_Timezone_Helper::is_complex_country( $country_code ) ) {
				// Complex country - need API lookup
				if ( $final_lat !== null && $final_lon !== null ) {
					update_post_meta( $post_id, 'wta_timezone_status', 'pending' );

					// Schedule timezone lookup (with delay to spread load)
					as_schedule_single_action(
						time() + wp_rand( 1, 10 ),
						'wta_lookup_timezone',
						array(
							'post_id' => $post_id,
							'lat'     => $final_lat,
							'lng'     => $final_lon,
						)
					);
				}
			} else {
				// Simple country - try hardcoded list
				$timezone = WTA_Timezone_Helper::get_country_timezone( $country_code );
				if ( $timezone ) {
					update_post_meta( $post_id, 'wta_timezone', $timezone );
					update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
					
				// Schedule AI content immediately for simple countries
				as_schedule_single_action(
					time(),
					'wta_generate_ai_content',
					array( $post_id, 'city', false ),  // post_id, type, force_ai
					'wta_ai_content'
				);
				} else {
					// Country not in list - use API
					if ( $final_lat !== null && $final_lon !== null ) {
						update_post_meta( $post_id, 'wta_timezone_status', 'pending' );
						
						as_schedule_single_action(
							time() + wp_rand( 1, 10 ),
							'wta_lookup_timezone',
							array(
								'post_id' => $post_id,
								'lat'     => $final_lat,
								'lng'     => $final_lon,
							)
						);
					}
				}
			}

			// SEO metadata
			$parent_country_name = get_post_field( 'post_title', $parent_id );
			$seo_h1 = sprintf( 'Aktuel tid i %s, %s', $data['name_local'], $parent_country_name );
			update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
			
			$seo_title = sprintf( 'Hvad er klokken i %s, %s?', $data['name_local'], $parent_country_name );
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

