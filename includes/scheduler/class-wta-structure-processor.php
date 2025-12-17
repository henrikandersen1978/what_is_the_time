<?php
/**
 * Structure processor for Action Scheduler.
 *
 * Processes continents, countries, and cities to create CPT posts.
 * CRITICAL: Translates BEFORE post creation to ensure Danish URLs!
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/scheduler
 */

class WTA_Structure_Processor {

	/**
	 * Cached admin user ID for post authorship.
	 *
	 * @since    2.34.10
	 * @var      int|null
	 */
	private static $admin_user_id = null;

	/**
	 * Process batch.
	 *
	 * Called by Action Scheduler every minute.
	 * CRITICAL: Process in order - continents first, then countries, then cities!
	 *
	 * @since    2.0.0
	 */
	public function process_batch() {
		// Reset any stuck items first
		WTA_Queue::reset_stuck();

		// CRITICAL: Process in hierarchical order!
		// 1. First process ALL pending continents
		$continents = WTA_Queue::get_pending( 'continent', 50 );
		if ( ! empty( $continents ) ) {
			WTA_Logger::info( 'Processing continents', array( 'count' => count( $continents ) ) );
			foreach ( $continents as $item ) {
				$this->process_item( $item );
			}
			// Don't continue to countries until all continents are done
			return;
		}

		// 2. Then process ALL pending countries (only after continents are done)
		$countries = WTA_Queue::get_pending( 'country', 50 );
		if ( ! empty( $countries ) ) {
			WTA_Logger::info( 'Processing countries', array( 'count' => count( $countries ) ) );
			foreach ( $countries as $item ) {
				$this->process_item( $item );
			}
			// Don't continue until all countries are done
			return;
		}

		// 3. Process cities_import batch job (creates individual city jobs)
		$cities_import = WTA_Queue::get_pending( 'cities_import', 10 );
		if ( ! empty( $cities_import ) ) {
			WTA_Logger::info( 'Processing cities_import batch', array( 'count' => count( $cities_import ) ) );
			foreach ( $cities_import as $item ) {
				$this->process_item( $item );
			}
			// Don't continue to individual cities until batch is done
			return;
		}

	// 4. Finally process individual cities (only after cities_import is done)
	// Dynamic batch size based on cron interval and test mode
	// Each city: ~0.5s (Wikidata + GPS lookup)
	$cron_interval = intval( get_option( 'wta_cron_interval', 60 ) );
	$test_mode = get_option( 'wta_test_mode', 0 );
	
	if ( $test_mode ) {
		// Test mode: Faster processing
		// 1-min: 40 cities (~20s), 5-min: 200 cities (~100s)
		$batch_size = ( $cron_interval >= 300 ) ? 200 : 40;
	} else {
		// Normal mode: Conservative for API limits
		// 1-min: 10 cities (~5s), 5-min: 50 cities (~25s)
		$batch_size = ( $cron_interval >= 300 ) ? 50 : 10;
	}
	$cities = WTA_Queue::get_pending( 'city', $batch_size );
	if ( ! empty( $cities ) ) {
		WTA_Logger::info( 'Processing cities', array( 'count' => count( $cities ) ) );
		foreach ( $cities as $item ) {
			$this->process_item( $item );
		}
	}
}

	/**
	 * Process single queue item.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_item( $item ) {
		// Mark as processing
		WTA_Queue::mark_processing( $item['id'] );

		try {
			switch ( $item['type'] ) {
				case 'continent':
					$this->process_continent( $item );
					break;
				case 'country':
					$this->process_country( $item );
					break;
				case 'city':
					$this->process_city( $item );
					break;
				case 'cities_import':
					$this->process_cities_import( $item );
					break;
				default:
					WTA_Logger::warning( 'Unknown queue item type', array( 'type' => $item['type'] ) );
					WTA_Queue::mark_done( $item['id'] );
			}
		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to process queue item', array(
				'id'    => $item['id'],
				'type'  => $item['type'],
				'error' => $e->getMessage(),
			) );
			WTA_Queue::mark_failed( $item['id'], $e->getMessage() );
		}
	}

	/**
	 * Process continent.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_continent( $item ) {
		$data = $item['payload'];

		// Check if post already exists (check both draft and published)
		$existing = get_posts( array(
			'name'        => sanitize_title( $data['name_local'] ),
			'post_type'   => WTA_POST_TYPE,
			'post_status' => array( 'publish', 'draft' ),
			'numberposts' => 1,
		) );
		
		if ( ! empty( $existing ) ) {
			WTA_Logger::info( 'Continent post already exists', array( 'name' => $data['name_local'] ) );
			WTA_Queue::mark_done( $item['id'] );
			return;
		}

		// Create post with continent name as title (used in H2s and navigation)
		$post_id = wp_insert_post( array(
			'post_title'   => $data['name_local'], // Just "Europa", "Asien", etc.
			'post_name'    => sanitize_title( $data['name_local'] ),
			'post_type'    => WTA_POST_TYPE,
			'post_status'  => 'draft', // Will be published after AI content
			'post_parent'  => 0,
			'post_author'  => $this->get_admin_user_id(),
		) );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Save meta
	update_post_meta( $post_id, 'wta_type', 'continent' );
	update_post_meta( $post_id, 'wta_name_original', $data['name'] );
	update_post_meta( $post_id, 'wta_name_local', $data['name_local'] ); // v3.0.0 - renamed from wta_name_danish
	update_post_meta( $post_id, 'wta_continent', $data['name'] ); // v3.0.4 - Store continent name for consistency
	update_post_meta( $post_id, 'wta_continent_code', WTA_Utils::get_continent_code( $data['name'] ) );
	update_post_meta( $post_id, 'wta_ai_status', 'pending' );
		
	// Save SEO-friendly H1 title for theme to display
	$seo_h1 = sprintf( 'Hvad er klokken i %s? Tidszoner og aktuel tid', $data['name_local'] );
	update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
	
	// Update Yoast SEO title for proper schema integration
	update_post_meta( $post_id, '_yoast_wpseo_title', $seo_h1 );

		// Queue AI content generation
		WTA_Queue::add( 'ai_content', array(
			'post_id' => $post_id,
			'type'    => 'continent',
		), 'ai_continent_' . $post_id );

		WTA_Logger::info( 'Continent post created', array(
			'post_id' => $post_id,
			'name'    => $data['name_local'],
		) );

		WTA_Queue::mark_done( $item['id'] );
	}

	/**
	 * Process country.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_country( $item ) {
		$data = $item['payload'];

		// Find parent continent post (include draft posts!)
		$continent_name_local = WTA_AI_Translator::translate( $data['continent'], 'continent' );
		
		$parent_posts = get_posts( array(
			'name'        => sanitize_title( $continent_name_local ),
			'post_type'   => WTA_POST_TYPE,
			'post_status' => array( 'publish', 'draft' ), // IMPORTANT: Include draft!
			'numberposts' => 1,
		) );

		if ( empty( $parent_posts ) ) {
			WTA_Logger::warning( 'Parent continent not found', array(
				'continent' => $data['continent'],
				'country'   => $data['name'],
			) );
			// Requeue for later
			WTA_Queue::mark_failed( $item['id'], 'Parent continent not found' );
			return;
		}

		$parent = $parent_posts[0];

		// Check if post already exists (check both draft and published)
		$existing = get_posts( array(
			'name'        => sanitize_title( $data['name_local'] ),
			'post_type'   => WTA_POST_TYPE,
			'post_parent' => $parent->ID,
			'post_status' => array( 'publish', 'draft' ),
			'numberposts' => 1,
		) );

		if ( ! empty( $existing ) ) {
			WTA_Logger::info( 'Country post already exists', array( 'name' => $data['name_local'] ) );
			WTA_Queue::mark_done( $item['id'] );
			return;
		}

		// Create post with Danish name and slug
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

		// Save meta
	update_post_meta( $post_id, 'wta_type', 'country' );
	update_post_meta( $post_id, 'wta_name_original', $data['name'] );
	update_post_meta( $post_id, 'wta_name_local', $data['name_local'] ); // v3.0.0 - renamed from wta_name_danish
	update_post_meta( $post_id, 'wta_continent', $data['continent'] ); // v3.0.4 - CRITICAL FIX: Store continent name
	update_post_meta( $post_id, 'wta_continent_code', WTA_Utils::get_continent_code( $data['continent'] ) );
	update_post_meta( $post_id, 'wta_country_code', $data['country_code'] );
		update_post_meta( $post_id, 'wta_country_id', $data['country_id'] );
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );
	
	// Save Wikidata ID if available
	if ( isset( $data['wikidata_id'] ) && ! empty( $data['wikidata_id'] ) ) {
		update_post_meta( $post_id, 'wta_wikidata_id', $data['wikidata_id'] );
	}
	
	// Save GPS coordinates
	if ( isset( $data['latitude'] ) && ! empty( $data['latitude'] ) ) {
		update_post_meta( $post_id, 'wta_latitude', floatval( $data['latitude'] ) );
	}
	if ( isset( $data['longitude'] ) && ! empty( $data['longitude'] ) ) {
		update_post_meta( $post_id, 'wta_longitude', floatval( $data['longitude'] ) );
	}

		// Determine timezone
		if ( WTA_Timezone_Helper::is_complex_country( $data['country_code'] ) ) {
			// Will need API lookup (per city)
			update_post_meta( $post_id, 'wta_timezone', 'multiple' );
			update_post_meta( $post_id, 'wta_timezone_status', 'multiple' );
		} else {
			// Simple country - try hardcoded list first
			$timezone = WTA_Timezone_Helper::get_country_timezone( $data['country_code'] );
			if ( $timezone ) {
				// Found in hardcoded list
				update_post_meta( $post_id, 'wta_timezone', $timezone );
				update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
			} else {
				// Country not in list - fallback to API lookup using capital city coordinates
				if ( isset( $data['latitude'] ) && isset( $data['longitude'] ) ) {
					update_post_meta( $post_id, 'wta_timezone_status', 'pending' );
					
					WTA_Queue::add( 'timezone', array(
						'post_id' => $post_id,
						'lat'     => $data['latitude'],
						'lng'     => $data['longitude'],
					), 'timezone_country_' . $post_id );
					
					WTA_Logger::info( 'Country timezone not in hardcoded list, using API fallback', array(
						'country_code' => $data['country_code'],
						'country'      => $data['name'],
					) );
				} else {
					WTA_Logger::error( 'No timezone data available for country', array(
						'country_code' => $data['country_code'],
						'country'      => $data['name'],
					) );
				}
			}
		}

	// Save SEO-friendly H1 title matching search intent
	$seo_h1 = sprintf( 'Hvad er klokken i %s?', $data['name_local'] );
	update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
	
	// Update Yoast SEO title for proper schema integration
	update_post_meta( $post_id, '_yoast_wpseo_title', $seo_h1 );

	// Queue AI content generation
		WTA_Queue::add( 'ai_content', array(
			'post_id' => $post_id,
			'type'    => 'country',
		), 'ai_country_' . $post_id );

		WTA_Logger::info( 'Country post created', array(
			'post_id' => $post_id,
			'name'    => $data['name_local'],
		) );

		WTA_Queue::mark_done( $item['id'] );
	}

	/**
	 * Process city.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_city( $item ) {
		$data = $item['payload'];

		// Find parent country post (include draft posts!)
		// CRITICAL: Use meta_query to filter by BOTH country_id AND type='country'
		$country_post_id = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'post_status' => array( 'publish', 'draft' ), // IMPORTANT: Include draft!
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => 'wta_country_id',
					'value' => $data['country_id'],
				),
				array(
					'key'   => 'wta_type',
					'value' => 'country',
				),
			),
			'numberposts' => 1,
			'fields'      => 'ids',
		) );

		if ( empty( $country_post_id ) ) {
			WTA_Logger::warning( 'Parent country not found', array(
				'country_id' => $data['country_id'],
				'city'       => $data['name'],
			) );
			WTA_Queue::mark_failed( $item['id'], 'Parent country not found' );
			return;
		}

		$parent_id = $country_post_id[0];

		// Check if post already exists (check both draft and published)
		$existing = get_posts( array(
			'name'        => sanitize_title( $data['name_local'] ),
			'post_type'   => WTA_POST_TYPE,
			'post_parent' => $parent_id,
			'post_status' => array( 'publish', 'draft' ),
			'numberposts' => 1,
		) );

		if ( ! empty( $existing ) ) {
			WTA_Logger::info( 'City post already exists', array( 'name' => $data['name_local'] ) );
			WTA_Queue::mark_done( $item['id'] );
			return;
		}

		// Create post with Danish name and slug
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

	// Save meta (v3.0.0 - using wta_name_local and GeoNames)
	update_post_meta( $post_id, 'wta_type', 'city' );
	update_post_meta( $post_id, 'wta_name_original', $data['name'] );
	update_post_meta( $post_id, 'wta_name_local', $data['name_local'] ); // v3.0.0 - renamed from wta_name_danish
	update_post_meta( $post_id, 'wta_continent_code', $continent_code );
	update_post_meta( $post_id, 'wta_country_code', $country_code );
	update_post_meta( $post_id, 'wta_ai_status', 'pending' );
	
	// Save GeoNames ID (v3.0.0 - primary identifier)
	if ( isset( $data['geonameid'] ) && ! empty( $data['geonameid'] ) ) {
		update_post_meta( $post_id, 'wta_geonames_id', intval( $data['geonameid'] ) );
	}
	
	// Save population (CRITICAL for major cities shortcode!)
	if ( isset( $data['population'] ) && $data['population'] > 0 ) {
		update_post_meta( $post_id, 'wta_population', intval( $data['population'] ) );
	}
	
	// Save Wikidata ID if available (kept for backward compatibility)
	if ( isset( $data['wikidata_id'] ) && ! empty( $data['wikidata_id'] ) ) {
		update_post_meta( $post_id, 'wta_wikidata_id', $data['wikidata_id'] );
	}

	// ==========================================
	// GEONAMES-FIRST GPS STRATEGY (v3.0.0)
	// Priority: GeoNames → Wikidata fallback
	// GeoNames provides accurate GPS for 99%+ of cities
	// ==========================================
	
	$final_lat = null;
	$final_lon = null;
	$gps_source = 'unknown';
	
	// STEP 1: Use GeoNames GPS (primary source, most accurate)
	if ( isset( $data['latitude'] ) && isset( $data['longitude'] ) ) {
		$final_lat = floatval( $data['latitude'] );
		$final_lon = floatval( $data['longitude'] );
		$gps_source = 'geonames';
	} else {
		// STEP 2: Fallback to Wikidata GPS (rare case where GeoNames is missing)
		if ( isset( $data['wikidata_id'] ) && ! empty( $data['wikidata_id'] ) ) {
			$wikidata_coords = $this->fetch_coordinates_from_wikidata( $data['wikidata_id'] );
			
			if ( $wikidata_coords !== false ) {
				$final_lat = $wikidata_coords['lat'];
				$final_lon = $wikidata_coords['lon'];
				$gps_source = 'wikidata';
				
				WTA_Logger::info( 'Wikidata GPS fallback used', array(
					'city'         => $data['name'],
					'wikidata_gps' => "$final_lat, $final_lon",
				) );
			}
		}
		
		// No GPS found from either source
		$final_lat = isset( $data['latitude'] ) ? $data['latitude'] : null;
		$final_lon = isset( $data['longitude'] ) ? $data['longitude'] : null;
		$gps_source = 'cities_json';
	}
	
	// STEP 2: Validate GPS after Wikidata-first correction
	// This catches truly corrupt data that Wikidata couldn't fix
	if ( $final_lat !== null && $final_lon !== null ) {
		// Sanity check: Mathematically impossible coordinates
		if ( abs( $final_lat ) > 90 || abs( $final_lon ) > 180 ) {
			WTA_Logger::error( sprintf(
				'City creation ABORTED - invalid GPS range: %s (%s) - GPS: %s,%s',
				$data['name'],
				$data['country_code'],
				$final_lat,
				$final_lon
			) );
			WTA_Queue::mark_failed( $item['id'], 'Invalid GPS coordinates (out of range)' );
			return;
		}
		
		// Continent consistency check (after Wikidata correction)
		// Only skip if STILL mismatched after Wikidata tried to fix it
		// Use parent country's continent to validate GPS
		if ( isset( $parent_id ) && $parent_id > 0 ) {
			$parent_continent_code = get_post_meta( $parent_id, 'wta_continent_code', true );
			if ( $parent_continent_code ) {
				// Map continent code to name for comparison
				$continent_names = array(
					'AF' => 'Africa', 'AN' => 'Antarctica', 'AS' => 'Asia',
					'EU' => 'Europe', 'NA' => 'North America', 'OC' => 'Oceania', 'SA' => 'South America'
				);
				$country_continent = isset( $continent_names[ $parent_continent_code ] ) ? $continent_names[ $parent_continent_code ] : 'Unknown';
				$gps_continent = $this->get_continent_from_gps( $final_lat, $final_lon );
				
				if ( $country_continent !== 'Unknown' && $gps_continent !== 'Unknown' && $country_continent !== $gps_continent ) {
					// SMART ERROR HANDLING (v2.34.19):
					// This is BAD DATA (corrupt GPS), not a retriable error.
					// Mark as complete (not failed) so it doesn't show as error in dashboard
					// and won't be retried via "Retry Failed Items" button.
					WTA_Logger::info( sprintf(
						'SKIPPED (corrupt GPS - continent mismatch): %s (%s) - Country=%s, GPS=%s (lat=%s, lon=%s, source=%s)',
						$data['name'],
						$data['country_code'],
						$country_continent,
						$gps_continent,
						$final_lat,
						$final_lon,
						$gps_source
					) );
					WTA_Queue::mark_done( $item['id'] );
					return;
				}
			}
		}
		
		// GPS is valid! Save it.
		update_post_meta( $post_id, 'wta_latitude', floatval( $final_lat ) );
		update_post_meta( $post_id, 'wta_longitude', floatval( $final_lon ) );
		update_post_meta( $post_id, 'wta_gps_source', $gps_source );
		
		WTA_Logger::debug( sprintf(
			'GPS saved for %s: %s,%s (source: %s)',
			$data['name'],
			$final_lat,
			$final_lon,
			$gps_source
		) );
	} else {
		// SMART ERROR HANDLING (v2.34.19):
		// No GPS available = BAD DATA (not a retriable error)
		// Mark as complete so it doesn't show as error in dashboard
		WTA_Logger::info( sprintf(
			'SKIPPED (no GPS available): %s (%s)',
			$data['name'],
			$data['country_code']
		) );
		WTA_Queue::mark_done( $item['id'] );
		return;
	}

		// Handle timezone (use final GPS coordinates from Wikidata-first strategy)
		$needs_timezone_api = false;

		if ( WTA_Timezone_Helper::is_complex_country( $country_code ) ) {
			// Complex country - need API lookup if we have lat/lng
			if ( $final_lat !== null && $final_lon !== null ) {
				$needs_timezone_api = true;
				update_post_meta( $post_id, 'wta_timezone_status', 'pending' );

				// Queue timezone resolution
				WTA_Queue::add( 'timezone', array(
					'post_id' => $post_id,
					'lat'     => $final_lat,
					'lng'     => $final_lon,
				), 'timezone_' . $post_id );
			} else {
				// No lat/lng - use country default as fallback
				$timezone = WTA_Timezone_Helper::get_country_timezone( $country_code );
				if ( $timezone ) {
					update_post_meta( $post_id, 'wta_timezone', $timezone );
					update_post_meta( $post_id, 'wta_timezone_status', 'fallback' );
				}
			}
		} else {
			// Simple country - try to get from hardcoded list first
			$timezone = WTA_Timezone_Helper::get_country_timezone( $country_code );
			if ( $timezone ) {
				// Found in hardcoded list
				update_post_meta( $post_id, 'wta_timezone', $timezone );
				update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
			} else {
				// Country not in list - fallback to API lookup
				if ( $final_lat !== null && $final_lon !== null ) {
					$needs_timezone_api = true;
					update_post_meta( $post_id, 'wta_timezone_status', 'pending' );
					
					WTA_Queue::add( 'timezone', array(
						'post_id' => $post_id,
						'lat'     => $final_lat,
						'lng'     => $final_lon,
					), 'timezone_' . $post_id );
					
					WTA_Logger::info( 'Country timezone not in list, using API fallback', array(
						'country_code' => $country_code,
						'city'         => $data['name'],
					) );
				} else {
					WTA_Logger::warning( 'No timezone data available', array(
						'country_code' => $country_code,
						'city'         => $data['name'],
					) );
				}
			}
		}

	// Get parent country name for SEO H1
	$parent_country_name = get_post_field( 'post_title', $parent_id );
	
	// Save SEO-friendly H1 title matching search intent
	$seo_h1 = sprintf( 'Hvad er klokken i %s, %s?', $data['name_local'], $parent_country_name );
	update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
	
	// Update Yoast SEO title for proper schema integration
	update_post_meta( $post_id, '_yoast_wpseo_title', $seo_h1 );

	// ==========================================
	// SMART AI QUEUEING STRATEGY (v2.35.8)
	// - Simple countries: Queue AI immediately (timezone already correct)
	// - Complex countries: Wait for timezone processor to queue AI after resolution
	// This ensures quality (correct timezone in AI content) while maximizing speed
	// ==========================================
	
	if ( ! WTA_Timezone_Helper::is_complex_country( $country_code ) ) {
		// Simple country: Timezone is already correct from hardcoded list
		// Queue AI content immediately for fast processing!
		WTA_Queue::add( 'ai_content', array(
			'post_id' => $post_id,
			'type'    => 'city',
		), 'ai_city_' . $post_id );
		
		WTA_Logger::info( 'AI content queued immediately (simple country)', array(
			'post_id' => $post_id,
			'country_code' => $country_code,
		) );
	}
	// Complex countries: AI will be queued by timezone processor after resolution

	WTA_Logger::info( 'City post created', array(
		'post_id' => $post_id,
		'name'    => $data['name_local'],
	) );

	WTA_Queue::mark_done( $item['id'] );
}

	/**
	 * Process cities import batch job.
	 *
	 * Streams cities.json and queues individual cities.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	/**
	 * Process cities import batch using GeoNames cities500.txt.
	 *
	 * MAJOR REFACTOR (v3.0.3): Complete rewrite to use GeoNames format instead of JSON.
	 * - Uses streaming tab-separated parsing (37 MB file)
	 * - GeoNames data: geonameid, name, GPS, population, timezone
	 * - Translation via alternateNamesV2.txt lookup
	 * - Chunked processing (1000 cities per chunk) for performance
	 *
	 * @since    2.3.8
	 * @since    3.0.3  Rewritten for GeoNames format
	 * @param    array $item Queue item.
	 */
	private function process_cities_import( $item ) {
		// Debug logging
		$debug_file = WP_CONTENT_DIR . '/uploads/wta-cities-import-debug.log';
		$log_msg = "\n\n=== CITIES_IMPORT DEBUG (GeoNames v3.0.3) " . date('Y-m-d H:i:s') . " ===\n";
		file_put_contents( $debug_file, $log_msg, FILE_APPEND );
		
		WTA_Logger::info( '=== CITIES_IMPORT STARTED (GeoNames) ===' );
		
		try {
			// Validate payload
			if ( ! isset( $item['payload'] ) ) {
				$error = 'Missing payload in cities_import item';
				file_put_contents( $debug_file, "ERROR: $error\n", FILE_APPEND );
				throw new Exception( $error );
			}
			
			$options = $item['payload'];
			file_put_contents( $debug_file, 'Payload keys: ' . implode( ', ', array_keys( $options ) ) . "\n", FILE_APPEND );
			
			if ( ! isset( $options['file_path'] ) ) {
				$error = 'Missing file_path in payload';
				file_put_contents( $debug_file, "ERROR: $error\n", FILE_APPEND );
				throw new Exception( $error );
			}
			
			$file_path = $options['file_path'];
			file_put_contents( $debug_file, "File path: $file_path\n", FILE_APPEND );
			
			if ( ! file_exists( $file_path ) ) {
				throw new Exception( 'cities500.txt not found at: ' . $file_path );
			}

			// Increase time limit for processing
			set_time_limit( 300 ); // 5 minutes

			$file_size = filesize( $file_path );
			file_put_contents( $debug_file, "File size: " . round( $file_size / 1024 / 1024, 2 ) . " MB\n", FILE_APPEND );
			WTA_Logger::info( 'Starting cities_import batch (GeoNames TAB-SEPARATED)', array(
				'file' => basename( $file_path ),
				'size_mb' => round( $file_size / 1024 / 1024, 2 ),
			) );

		file_put_contents( $debug_file, "Streaming parse cities500.txt (GeoNames format)...\n", FILE_APPEND );
		
	// Extract options
	$min_population = isset( $options['min_population'] ) ? intval( $options['min_population'] ) : 0;
	$filtered_country_codes = isset( $options['filtered_country_codes'] ) ? $options['filtered_country_codes'] : array();
	$max_cities_per_country = isset( $options['max_cities_per_country'] ) ? intval( $options['max_cities_per_country'] ) : 0;
	$selected_continents = isset( $options['selected_continents'] ) ? $options['selected_continents'] : array();
	$offset = isset( $options['offset'] ) ? intval( $options['offset'] ) : 0;
	$chunk_size = 1000; // 1k cities per chunk
	
	// Smart logging detection
	$is_full_import = false;
	if ( empty( $filtered_country_codes ) || count( $filtered_country_codes ) > 50 ) {
		$is_full_import = true;
	}
	if ( empty( $selected_continents ) || count( $selected_continents ) >= 4 ) {
		$is_full_import = true;
	}
	if ( $min_population === 0 && empty( $filtered_country_codes ) ) {
		$is_full_import = true;
	}
	$enable_detailed_logging = ! $is_full_import;
	
	// Log settings
	file_put_contents( $debug_file, "Min population: $min_population\n", FILE_APPEND );
	file_put_contents( $debug_file, "Max cities per country: $max_cities_per_country\n", FILE_APPEND );
	file_put_contents( $debug_file, "Filtered countries: " . count( $filtered_country_codes ) . "\n", FILE_APPEND );
	file_put_contents( $debug_file, "Chunk: $offset (size: $chunk_size)\n", FILE_APPEND );
	file_put_contents( $debug_file, sprintf(
		"Import scale: %s (detailed logging: %s)\n",
		$is_full_import ? 'FULL IMPORT' : 'TARGETED',
		$enable_detailed_logging ? 'ENABLED' : 'DISABLED'
	), FILE_APPEND );
		
	// Statistics
	$queued = 0;
	$skipped_country = 0;
	$skipped_population = 0;
	$skipped_max_reached = 0;
	$skipped_gps_invalid = 0;
	$skipped_feature_class = 0;
	$skipped_duplicate = 0;
	$per_country = array();
	$total_read = 0;
	$seen_cities = array(); // For duplicate detection
	
	// Load country → continent mapping
	file_put_contents( $debug_file, "Loading GeoNames countryInfo.txt...\n", FILE_APPEND );
	$countries = WTA_GeoNames_Parser::parse_countryInfo();
	$country_to_continent = array();
		
	if ( $countries && is_array( $countries ) ) {
		foreach ( $countries as $country ) {
			if ( isset( $country['iso2'] ) && isset( $country['continent'] ) ) {
				$country_to_continent[ $country['iso2'] ] = $country['continent'];
			}
		}
		file_put_contents( $debug_file, sprintf(
			"Loaded %d country→continent mappings\n",
			count( $country_to_continent )
		), FILE_APPEND );
	}
		
	// ==========================================
	// STREAM PARSE GeoNames cities500.txt
	// ==========================================
	// Instead of loading entire file, we stream it line-by-line
	// and collect cities into memory only for the current chunk.
	
	$file = fopen( $file_path, 'r' );
	if ( ! $file ) {
		throw new Exception( 'Failed to open cities500.txt' );
	}
	
	// STEP 1: Read all lines and collect into temporary array
	// (We need total count for chunking logic)
	file_put_contents( $debug_file, "Reading all lines from cities500.txt...\n", FILE_APPEND );
	$all_cities = array();
	$line_count = 0;
	
	while ( ( $line = fgets( $file ) ) !== false ) {
		$line_count++;
		
		// Parse tab-separated values
		$parts = explode( "\t", trim( $line ) );
		
		// Validate minimum required fields (19 columns in GeoNames)
		if ( count( $parts ) < 19 ) {
			continue;
		}
		
		// Extract GeoNames fields
		$geonameid = $parts[0];
		$name = $parts[1];           // Name (UTF-8)
		$asciiname = $parts[2];      // ASCII name (for URL slugs)
		$latitude = $parts[4];
		$longitude = $parts[5];
		$feature_class = $parts[6];
		$feature_code = $parts[7];
		$country_code = $parts[8];   // ISO2 country code
		$population = $parts[14];
		$timezone = $parts[17];
		
		// Only include populated places (P class) - CRITICAL FILTER
		if ( $feature_class !== 'P' ) {
			continue;
		}
		
		// Convert to standardized city array format
		$city = array(
			'geonameid'    => intval( $geonameid ),
			'name'         => $name,
			'name_ascii'   => $asciiname,
			'country_code' => strtoupper( $country_code ),
			'latitude'     => floatval( $latitude ),
			'longitude'    => floatval( $longitude ),
			'population'   => intval( $population ),
			'timezone'     => $timezone,
			'feature_code' => $feature_code,
		);
		
		$all_cities[] = $city;
	}
	
	fclose( $file );
	
	$total_cities = count( $all_cities );
	file_put_contents( $debug_file, sprintf(
		"Parsed %d cities from %d lines. Starting chunked processing...\n",
		$total_cities,
		$line_count
	), FILE_APPEND );
	
	// STEP 2: Extract current chunk
	$cities_chunk = array_slice( $all_cities, $offset, $chunk_size );
	$chunk_end = $offset + count( $cities_chunk );
	
	file_put_contents( $debug_file, sprintf(
		"CHUNK INFO: Processing cities %d-%d of %d total\n",
		$offset,
		$chunk_end - 1,
		$total_cities
	), FILE_APPEND );
	
	WTA_Logger::info( sprintf(
		'Processing chunk: %d-%d of %d cities (GeoNames)',
		$offset,
		$chunk_end - 1,
		$total_cities
	) );
	
	// Free memory - we only need the chunk
	unset( $all_cities );
	
	// STEP 3: Process each city in this chunk
	foreach ( $cities_chunk as $index => $city ) {
		$total_read++;
		
		// Log progress every 500 cities (only if detailed logging enabled)
		if ( $enable_detailed_logging && $total_read % 500 === 0 ) {
			$memory_now = round( memory_get_usage( true ) / 1024 / 1024, 2 );
			file_put_contents( $debug_file, sprintf(
				"[PROGRESS] Processed %d cities in chunk | Memory: %s MB\n",
				$total_read,
				$memory_now
			), FILE_APPEND );
		}
		
		// Log first city
		if ( $total_read === 1 ) {
			file_put_contents( $debug_file, "First city: " . $city['name'] . " (GID:" . $city['geonameid'] . ", " . $city['country_code'] . ")\n", FILE_APPEND );
		}
			
		// Filter by country_code
		if ( ! empty( $filtered_country_codes ) && ! in_array( $city['country_code'], $filtered_country_codes, true ) ) {
			$skipped_country++;
			continue;
		}
			
		// Population filter
		if ( $min_population > 0 && $city['population'] < $min_population ) {
			$skipped_population++;
			continue;
		}
		
		// GPS validation (GeoNames data is reliable, just basic sanity checks)
		$lat = $city['latitude'];
		$lon = $city['longitude'];
		
		// Basic sanity checks
		if ( $lat == 0 && $lon == 0 ) {
			$skipped_gps_invalid++;
			continue;
		}
		
		if ( abs( $lat ) > 90 || abs( $lon ) > 180 ) {
			$skipped_gps_invalid++;
			continue;
		}
		
	// Duplicate detection (GeoNames should have unique entries, but check anyway)
	$duplicate_key = $city['country_code'] . '_' . $this->normalize_city_name( $city['name'] );
	
	if ( isset( $seen_cities[ $duplicate_key ] ) ) {
		// Calculate scores to pick best entry
		$new_score = $this->calculate_score( $city );
		$old_score = $seen_cities[ $duplicate_key ]['score'];
		
		if ( $new_score <= $old_score ) {
			$skipped_duplicate++;
			continue;
		}
		// If new score is better, we'll use this entry instead
	}
	
	// Remember this city
	$seen_cities[ $duplicate_key ] = array(
		'city'  => $city,
		'score' => isset( $new_score ) ? $new_score : $this->calculate_score( $city ),
	);
		
	// Administrative terms filter (lightweight check)
	// GeoNames feature_class='P' already filters out most admin divisions,
	// but we keep a basic check for safety
	$name_lower = mb_strtolower( $city['name'], 'UTF-8' );
	$admin_keywords = array( 'municipality', 'commune', 'district', 'province', 'county', 'governorate' );
	
	foreach ( $admin_keywords as $keyword ) {
		if ( strpos( $name_lower, $keyword ) !== false ) {
			$skipped_country++;
			continue 2;
		}
	}
	
	// Max cities per country check
	if ( $max_cities_per_country > 0 ) {
		if ( ! isset( $per_country[ $city['country_code'] ] ) ) {
			$per_country[ $city['country_code'] ] = 0;
		}
		
		if ( $per_country[ $city['country_code'] ] >= $max_cities_per_country ) {
			$skipped_max_reached++;
			continue;
		}
		
		$per_country[ $city['country_code'] ]++;
	}
					
	// Queue city for import
	$queued += $this->queue_cities_batch( array( $city ), $options );
				
	// Log progress every 100 queued cities
	if ( $queued % 100 === 0 ) {
		file_put_contents( $debug_file, "Progress: Queued $queued cities...\n", FILE_APPEND );
	}
	} // Close foreach loop
	
	// Log completion stats
	$memory_peak = round( memory_get_peak_usage( true ) / 1024 / 1024, 2 );
	file_put_contents( $debug_file, sprintf(
		"Chunk complete. Cities in chunk: %d | Queued: %d | Peak memory: %s MB\n",
		count( $cities_chunk ),
		$queued,
		$memory_peak
	), FILE_APPEND );

	$summary = sprintf(
		"CHUNK STATS: Queued=%d, Skipped_country=%d, Skipped_population=%d, Skipped_GPS=%d, Skipped_duplicate=%d, Skipped_max=%d\n",
		$queued,
		$skipped_country,
		$skipped_population,
		$skipped_gps_invalid,
		$skipped_duplicate,
		$skipped_max_reached
	);
	file_put_contents( $debug_file, $summary, FILE_APPEND );

	WTA_Logger::info( 'Cities import chunk completed (GeoNames)', array(
		'chunk_start'         => $offset,
		'chunk_end'           => $chunk_end - 1,
		'cities_queued'       => $queued,
		'skipped_country'     => $skipped_country,
		'skipped_population'  => $skipped_population,
		'skipped_gps_invalid' => $skipped_gps_invalid,
		'skipped_duplicate'   => $skipped_duplicate,
		'skipped_max_reached' => $skipped_max_reached,
	) );

	// ==========================================
	// CHUNK CONTINUATION (v3.0.3 - GeoNames)
	// ==========================================
	$next_offset = $offset + $chunk_size;
	$current_chunk_number = ( $offset / $chunk_size ) + 1;
	$max_chunks = 250; // Safety limit: 250 chunks × 1k = 250k cities max (210k + buffer)
	
	// Safety check 1: No cities queued in this chunk
	if ( $queued === 0 ) {
		file_put_contents( $debug_file, "\n⚠️ CHUNK STOP: No cities queued (all filtered)\n", FILE_APPEND );
		WTA_Logger::info( 'Chunking stopped: No cities queued in chunk' );
	}
	// Safety check 2: Max chunks reached
	elseif ( $current_chunk_number >= $max_chunks ) {
		file_put_contents( $debug_file, "\n⚠️ CHUNK STOP: Max chunks limit reached ($max_chunks)\n", FILE_APPEND );
		WTA_Logger::warning( "Chunking stopped: Max chunks limit ($max_chunks)" );
	}
	// Safety check 3: More cities to process?
	elseif ( $next_offset < $total_cities ) {
		// Queue next chunk
		$next_chunk_end = min( $next_offset + $chunk_size, $total_cities );
		
		file_put_contents( $debug_file, sprintf(
			"\n✅ CHUNK %d DONE: %d-%d, Queued %d. Next chunk: %d-%d\n",
			$current_chunk_number,
			$offset,
			$chunk_end - 1,
			$queued,
			$next_offset,
			$next_chunk_end - 1
		), FILE_APPEND );
		
		WTA_Logger::info( sprintf(
			'Chunk %d (%d-%d) complete, queued %d. Queuing chunk %d (%d-%d)',
			$current_chunk_number,
			$offset,
			$chunk_end - 1,
			$queued,
			$current_chunk_number + 1,
			$next_offset,
			$next_chunk_end - 1
		) );
		
		$next_options = $options;
		$next_options['offset'] = $next_offset;
		
		WTA_Queue::add(
			'cities_import',
			$next_options,
			'cities_import_chunk_' . $next_offset
		);
	} else {
		// All chunks complete!
		file_put_contents( $debug_file, sprintf(
			"\n🎉 ALL CHUNKS COMPLETE! Total: %d cities, Chunks: %d\n",
			$total_cities,
			$current_chunk_number
		), FILE_APPEND );
		
		WTA_Logger::info( sprintf(
			'All GeoNames chunks complete! Chunks: %d, Total cities: %d',
			$current_chunk_number,
			$total_cities
		) );
	}

	WTA_Queue::mark_done( $item['id'] );
			
		} catch ( Exception $e ) {
			$error_msg = sprintf(
				"EXCEPTION: %s in %s:%d\n",
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			);
			file_put_contents( $debug_file, $error_msg, FILE_APPEND );
			
			WTA_Logger::error( 'CRITICAL: GeoNames cities_import failed', array(
				'error' => $e->getMessage(),
				'file'  => $e->getFile(),
				'line'  => $e->getLine(),
			) );
			
			WTA_Queue::mark_failed( $item['id'], $e->getMessage() );
		}
	}

	/**
	 * Queue a batch of cities.
	 *
	 * @since    2.3.8
	 * @param    array $cities   Array of city data.
	 * @param    array $options  Import options.
	 * @return   int             Number of cities queued.
	 */
	private function queue_cities_batch( $cities, $options ) {
		return WTA_Importer::queue_cities_from_array( $cities, $options );
	}

	/**
	 * Get continent from GPS coordinates using rough geographical bounds.
	 * 
	 * This is a fallback validation that works GLOBALLY for all 195+ countries.
	 * Rough bounds are sufficient to catch major mismatches (e.g., Europe vs North America).
	 *
	 * @since    2.33.7
	 * @param    float $lat Latitude
	 * @param    float $lon Longitude
	 * @return   string     Continent name or 'Unknown'
	 */
	private function get_continent_from_gps( $lat, $lon ) {
		// Rough continent bounds (covering main landmasses)
		// These are intentionally generous to avoid false positives
		$continent_bounds = array(
			'Africa' => array(
				'lat_min' => -35.0, 'lat_max' => 37.5,
				'lon_min' => -18.0, 'lon_max' => 52.0
			),
			'Asia' => array(
				'lat_min' => -10.0, 'lat_max' => 80.0,
				'lon_min' => 25.0, 'lon_max' => 180.0
			),
			'Europe' => array(
				'lat_min' => 36.0, 'lat_max' => 71.5,
				'lon_min' => -10.0, 'lon_max' => 40.0
			),
			'North America' => array(
				'lat_min' => 15.0, 'lat_max' => 85.0,
				'lon_min' => -170.0, 'lon_max' => -50.0
			),
			'South America' => array(
				'lat_min' => -56.0, 'lat_max' => 13.0,
				'lon_min' => -82.0, 'lon_max' => -35.0
			),
			'Oceania' => array(
				'lat_min' => -47.0, 'lat_max' => 1.0,
				'lon_min' => 110.0, 'lon_max' => 180.0
			),
			'Antarctica' => array(
				'lat_min' => -90.0, 'lat_max' => -60.0,
				'lon_min' => -180.0, 'lon_max' => 180.0
			),
		);

		foreach ( $continent_bounds as $continent => $bounds ) {
			if ( $lat >= $bounds['lat_min'] && $lat <= $bounds['lat_max'] &&
			     $lon >= $bounds['lon_min'] && $lon <= $bounds['lon_max'] ) {
				return $continent;
			}
		}

		return 'Unknown';
	}

	/**
	 * Calculate quality score for a city entry.
	 * 
	 * Used to prioritize best data when duplicates are found.
	 * Works even if population is null!
	 *
	 * @since    2.33.7
	 * @param    array $city City data
	 * @return   float       Quality score (higher = better)
	 */
	/**
	 * Calculate quality score for duplicate detection (OPTIMIZED v2.34.20).
	 * 
	 * Ultra-fast version focusing on what matters most:
	 * 1. Wikidata ID (can be corrected via Wikidata-first strategy)
	 * 2. Population data (metadata quality indicator)
	 * 
	 * Previous version did GPS precision analysis (string operations) which
	 * was slow for 150k cities. This version is 10x+ faster while preserving
	 * the essential quality selection logic.
	 *
	 * @since    2.34.20
	 * @param    array $city City data
	 * @return   int         Quality score
	 */
	private function calculate_score( $city ) {
		$score = 0;

		// 1. Wikidata ID = authoritative source (MOST IMPORTANT!)
		//    Cities with wikiDataId can be corrected via Wikidata-first GPS strategy
		//    Example: København with corrupt GPS but wikiDataId Q1748 → Wikidata fixes it ✅
		if ( isset( $city['wikiDataId'] ) && ! empty( $city['wikiDataId'] ) ) {
			$score += 100; // Winner! Can be fixed by Wikidata
		}

		// 2. Population (if available) = data completeness indicator
		//    Higher population = better data quality (usually)
		if ( isset( $city['population'] ) && $city['population'] > 0 ) {
			$score += 10; // Nice to have
		}

		return $score;
	}

	/**
	 * Normalize city name for duplicate detection.
	 *
	 * @since    2.33.7
	 * @param    string $name City name
	 * @return   string       Normalized name
	 */
	private function normalize_city_name( $name ) {
		// Convert to lowercase
		$name = mb_strtolower( $name, 'UTF-8' );
		
		// Remove common variations
		$name = str_replace( array( 'copenhagen', 'københavn' ), 'kobenhavn', $name );
		$name = str_replace( array( 'saint', 'st.', 'st' ), 'sankt', $name );
		
		// Remove spaces and dashes
		$name = str_replace( array( ' ', '-', '_' ), '', $name );
		
		return $name;
	}

	/**
	 * Get admin user ID for post authorship (cached).
	 *
	 * @since    2.34.10
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
			
			WTA_Logger::debug( 'Admin user ID cached for post authorship', array(
				'user_id' => self::$admin_user_id,
			) );
		}
		return self::$admin_user_id;
	}

	/**
	 * Fetch correct GPS coordinates from Wikidata.
	 * 
	 * Used to rescue cities with corrupt GPS data in cities.json.
	 * Only called when GPS validation fails but city has Wikidata ID.
	 *
	 * @since    2.33.8
	 * @param    string $wikidata_id Wikidata entity ID (e.g., "Q1748")
	 * @return   array|false         Array with 'lat' and 'lon', or false on failure
	 */
	private function fetch_coordinates_from_wikidata( $wikidata_id ) {
		if ( empty( $wikidata_id ) ) {
			return false;
		}
		
		// Dynamic rate limiting optimized for speed and safety:
		// Test mode: 20 requests/second (0.05s = 50ms, 10% of Wikidata capacity)
		// Normal mode: 5 requests/second (0.2s = 200ms, 2.5% of Wikidata capacity)
		// Wikidata official limit: 200 requests/second (we use 2.5-10% max)
		static $last_api_call = 0;
		
		$test_mode = get_option( 'wta_test_mode', 0 );
		$min_interval = $test_mode ? 0.05 : 0.2;  // 50ms vs 200ms
		
		$now = microtime( true );
		$time_since_last_call = $now - $last_api_call;
		
		// If minimum interval hasn't passed since last call, wait
		if ( $time_since_last_call < $min_interval ) {
			$wait_microseconds = (int) ( ( $min_interval - $time_since_last_call ) * 1000000 );
			usleep( $wait_microseconds );
			WTA_Logger::debug( sprintf( 'Rate limit: waited %.3f seconds', $wait_microseconds / 1000000 ) );
		}
		
		// Wikidata Entity Data API (JSON format)
		$url = sprintf(
			'https://www.wikidata.org/wiki/Special:EntityData/%s.json',
			$wikidata_id
		);
		
		WTA_Logger::info( 'Fetching GPS from Wikidata: ' . $wikidata_id . ' - ' . $url );
		
		$response = wp_remote_get( $url, array(
			'timeout' => 5,  // Reduced from 10s for faster failover
			'headers' => array(
				'User-Agent' => 'WorldTimeAI-WordPress-Plugin/2.34.16'
			)
		) );
		
		// Update last call timestamp for rate limiting
		$last_api_call = microtime( true );
		
		if ( is_wp_error( $response ) ) {
			WTA_Logger::warning( 'Wikidata API error: ' . $response->get_error_message() );
			return false;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		// Check if coordinate data exists (P625 = coordinate location property)
		if ( ! isset( $data['entities'][ $wikidata_id ]['claims']['P625'] ) ) {
			WTA_Logger::warning( 'No coordinate data in Wikidata for: ' . $wikidata_id );
			return false;
		}
		
		// Extract coordinates from first claim
		$coord_claim = $data['entities'][ $wikidata_id ]['claims']['P625'][0];
		
		if ( ! isset( $coord_claim['mainsnak']['datavalue']['value'] ) ) {
			WTA_Logger::warning( 'Invalid coordinate structure in Wikidata for: ' . $wikidata_id );
			return false;
		}
		
		$coord_value = $coord_claim['mainsnak']['datavalue']['value'];
		
		if ( ! isset( $coord_value['latitude'] ) || ! isset( $coord_value['longitude'] ) ) {
			WTA_Logger::warning( 'Missing lat/lon in Wikidata for: ' . $wikidata_id );
			return false;
		}
		
		return array(
			'lat' => $coord_value['latitude'],
			'lon' => $coord_value['longitude']
		);
	}
}


