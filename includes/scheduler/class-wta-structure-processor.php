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
		$cities = WTA_Queue::get_pending( 'city', 50 );
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
		) );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Save meta
		update_post_meta( $post_id, 'wta_type', 'continent' );
		update_post_meta( $post_id, 'wta_name_original', $data['name'] );
		update_post_meta( $post_id, 'wta_name_danish', $data['name_local'] );
		update_post_meta( $post_id, 'wta_continent_code', WTA_Utils::get_continent_code( $data['name'] ) );
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );
		
		// Save SEO-friendly H1 title for theme to display
		$seo_h1 = sprintf( 'Hvad er klokken i %s? Tidszoner og aktuel tid', $data['name_local'] );
		update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );

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
		) );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

	// Save meta
	update_post_meta( $post_id, 'wta_type', 'country' );
	update_post_meta( $post_id, 'wta_name_original', $data['name'] );
	update_post_meta( $post_id, 'wta_name_danish', $data['name_local'] );
	update_post_meta( $post_id, 'wta_continent_code', WTA_Utils::get_continent_code( $data['continent'] ) );
	update_post_meta( $post_id, 'wta_country_code', $data['country_code'] );
	update_post_meta( $post_id, 'wta_country_id', $data['country_id'] );
	update_post_meta( $post_id, 'wta_ai_status', 'pending' );
	
	// Save Wikidata ID if available
	if ( isset( $data['wikidata_id'] ) && ! empty( $data['wikidata_id'] ) ) {
		update_post_meta( $post_id, 'wta_wikidata_id', $data['wikidata_id'] );
	}

		// Determine timezone
		if ( WTA_Timezone_Helper::is_complex_country( $data['country_code'] ) ) {
			// Will need API lookup (per city)
			update_post_meta( $post_id, 'wta_timezone', 'multiple' );
			update_post_meta( $post_id, 'wta_timezone_status', 'multiple' );
		} else {
			// Simple country - get default timezone
			$timezone = WTA_Timezone_Helper::get_country_timezone( $data['country_code'] );
			if ( $timezone ) {
				update_post_meta( $post_id, 'wta_timezone', $timezone );
				update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
			}
		}

		// Save SEO-friendly H1 title for theme to display
		$seo_h1 = sprintf( 'Hvad er klokken i %s? Aktuel tid og tidszoner', $data['name_local'] );
		update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );

		// Queue AI content generation
		WTA_Queue::add( 'ai_content', array(
			'post_id' => $post_id,
			'type'    => 'country',
		), 'ai_country_' . $post_id );
		
		// Check if parent continent needs content regeneration
		// (to include this new country in the country list)
		$this->maybe_regenerate_parent_content( $parent->ID );

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
		) );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Get country meta
		$country_code = get_post_meta( $parent_id, 'wta_country_code', true );
		$continent_code = get_post_meta( $parent_id, 'wta_continent_code', true );

	// Save meta
	update_post_meta( $post_id, 'wta_type', 'city' );
	update_post_meta( $post_id, 'wta_name_original', $data['name'] );
	update_post_meta( $post_id, 'wta_name_danish', $data['name_local'] );
	update_post_meta( $post_id, 'wta_continent_code', $continent_code );
	update_post_meta( $post_id, 'wta_country_code', $country_code );
	update_post_meta( $post_id, 'wta_country_id', $data['country_id'] );
	update_post_meta( $post_id, 'wta_city_id', $data['city_id'] );
	update_post_meta( $post_id, 'wta_ai_status', 'pending' );
	
	// Save population (CRITICAL for major cities shortcode!)
	if ( isset( $data['population'] ) && $data['population'] > 0 ) {
		update_post_meta( $post_id, 'wta_population', intval( $data['population'] ) );
	}
	
	// Save Wikidata ID if available
	if ( isset( $data['wikidata_id'] ) && ! empty( $data['wikidata_id'] ) ) {
		update_post_meta( $post_id, 'wta_wikidata_id', $data['wikidata_id'] );
	}

		if ( isset( $data['latitude'] ) ) {
			update_post_meta( $post_id, 'wta_lat', $data['latitude'] );
		}
		if ( isset( $data['longitude'] ) ) {
			update_post_meta( $post_id, 'wta_lng', $data['longitude'] );
		}

		// Handle timezone
		$needs_timezone_api = false;

		if ( WTA_Timezone_Helper::is_complex_country( $country_code ) ) {
			// Complex country - need API lookup if we have lat/lng
			if ( isset( $data['latitude'] ) && isset( $data['longitude'] ) ) {
				$needs_timezone_api = true;
				update_post_meta( $post_id, 'wta_timezone_status', 'pending' );

				// Queue timezone resolution
				WTA_Queue::add( 'timezone', array(
					'post_id' => $post_id,
					'lat'     => $data['latitude'],
					'lng'     => $data['longitude'],
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
			// Simple country
			$timezone = WTA_Timezone_Helper::get_country_timezone( $country_code );
			if ( $timezone ) {
				update_post_meta( $post_id, 'wta_timezone', $timezone );
				update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
			}
		}

		// Queue AI content generation (only if timezone is resolved or not needed)
		if ( ! $needs_timezone_api ) {
			WTA_Queue::add( 'ai_content', array(
				'post_id' => $post_id,
				'type'    => 'city',
			), 'ai_city_' . $post_id );
		}

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
	private function process_cities_import( $item ) {
		// CRITICAL: Write to separate debug file so logs don't get lost
		$debug_file = WP_CONTENT_DIR . '/uploads/wta-cities-import-debug.log';
		$log_msg = "\n\n=== CITIES_IMPORT DEBUG " . date('Y-m-d H:i:s') . " ===\n";
		file_put_contents( $debug_file, $log_msg, FILE_APPEND );
		
		WTA_Logger::info( '=== CITIES_IMPORT STARTED ===' );
		
		try {
			if ( ! isset( $item['payload'] ) ) {
				$error = 'Missing payload in cities_import item';
				file_put_contents( $debug_file, "ERROR: $error\n", FILE_APPEND );
				throw new Exception( $error );
			}
			
			$options = $item['payload'];
			$msg = 'Payload keys: ' . implode( ', ', array_keys( $options ) );
			file_put_contents( $debug_file, "$msg\n", FILE_APPEND );
			WTA_Logger::info( 'Payload received', array( 'keys' => array_keys( $options ) ) );
			
			if ( ! isset( $options['file_path'] ) ) {
				$error = 'Missing file_path in payload';
				file_put_contents( $debug_file, "ERROR: $error\n", FILE_APPEND );
				throw new Exception( $error );
			}
			
			$file_path = $options['file_path'];
			file_put_contents( $debug_file, "File path: $file_path\n", FILE_APPEND );
			WTA_Logger::info( 'File path extracted', array( 'path' => $file_path ) );

			if ( ! file_exists( $file_path ) ) {
				throw new Exception( 'cities.json not found at: ' . $file_path );
			}

			// Increase time limit for streaming large file
			set_time_limit( 300 ); // 5 minutes

			$file_size = filesize( $file_path );
			file_put_contents( $debug_file, "File size: " . round( $file_size / 1024 / 1024, 2 ) . " MB\n", FILE_APPEND );
			WTA_Logger::info( 'Starting cities_import batch (CHUNK STREAMING)', array(
				'file' => basename( $file_path ),
				'size_mb' => round( $file_size / 1024 / 1024, 2 ),
			) );

		// Stream JSON file chunk-by-chunk (reading one JSON object at a time)
		file_put_contents( $debug_file, "Opening file for streaming...\n", FILE_APPEND );
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			throw new Exception( 'Could not open cities.json for reading' );
		}
		
		file_put_contents( $debug_file, "Starting chunk-based streaming...\n", FILE_APPEND );

		$min_population = isset( $options['min_population'] ) ? $options['min_population'] : 0;
		$filtered_country_codes = isset( $options['filtered_country_codes'] ) ? $options['filtered_country_codes'] : array();
		$max_cities_per_country = isset( $options['max_cities_per_country'] ) ? $options['max_cities_per_country'] : 0;
		
		file_put_contents( $debug_file, "Min population: $min_population\n", FILE_APPEND );
		file_put_contents( $debug_file, "Max cities per country: $max_cities_per_country\n", FILE_APPEND );
		file_put_contents( $debug_file, "Filtered country codes: " . implode( ', ', $filtered_country_codes ) . "\n", FILE_APPEND );
		
		$queued = 0;
		$skipped_country = 0;
		$skipped_population = 0;
		$skipped_max_reached = 0;
		$per_country = array();
		$total_read = 0;
		
		$json_buffer = '';
		$in_object = false;
		$brace_count = 0;
		$first_city_logged = false;
		
		// Read file line by line and build complete JSON objects
		while ( ! feof( $handle ) ) {
			$line = fgets( $handle );
			
			// Skip opening array bracket
			if ( trim( $line ) === '[' ) {
				continue;
			}
			
			// Stop at closing array bracket
			if ( trim( $line ) === ']' ) {
				break;
			}
			
			// Track braces to know when we have a complete object
			$brace_count += substr_count( $line, '{' ) - substr_count( $line, '}' );
			
			if ( strpos( $line, '{' ) !== false ) {
				$in_object = true;
			}
			
			if ( $in_object ) {
				$json_buffer .= $line;
			}
			
			// When we have a complete object (brace_count returns to 0)
			if ( $in_object && $brace_count === 0 ) {
				$total_read++;
				
				// Remove trailing comma
				$json_buffer = rtrim( $json_buffer );
				$json_buffer = rtrim( $json_buffer, ',' );
				
				// Parse this single city object
				$city = json_decode( $json_buffer, true );
				
				if ( null !== $city && is_array( $city ) ) {
					// Log first city for debugging
					if ( ! $first_city_logged ) {
						file_put_contents( $debug_file, "First city: " . $city['name'] . " (" . $city['country_code'] . ")\n", FILE_APPEND );
						$first_city_logged = true;
					}
					
					// Filter by country_code (iso2)
					if ( ! empty( $filtered_country_codes ) && ! in_array( $city['country_code'], $filtered_country_codes, true ) ) {
						$skipped_country++;
					} elseif ( $min_population > 0 ) {
						// Apply population filter - SKIP cities with null or zero population OR below threshold
						if ( ! isset( $city['population'] ) || $city['population'] === null || $city['population'] < $min_population ) {
							$skipped_population++;
							continue; // Skip to next iteration
						}
					}
					
					// Filter out municipalities, communes, and administrative divisions
					if ( isset( $city['name'] ) ) {
						$name_lower = strtolower( $city['name'] );
						// Skip if name contains municipality/commune keywords
						if ( strpos( $name_lower, 'kommune' ) !== false ||
						     strpos( $name_lower, 'municipality' ) !== false ||
						     strpos( $name_lower, 'commune' ) !== false ||
						     strpos( $name_lower, 'district' ) !== false ||
						     strpos( $name_lower, 'province' ) !== false ) {
							$skipped_country++; // Use same counter for simplicity
							continue;
						}
					}
					
					// Filter by type field if present
					if ( isset( $city['type'] ) && $city['type'] !== null && $city['type'] !== '' ) {
						// Skip non-city types
						if ( in_array( strtolower( $city['type'] ), array( 'municipality', 'commune', 'district', 'province', 'county' ) ) ) {
							$skipped_country++;
							continue;
						}
					}
					
					// If we reach here, city passed all filters
					// Max cities per country
					$should_queue = true;
					if ( $max_cities_per_country > 0 ) {
						$country_code = $city['country_code'];
						if ( ! isset( $per_country[ $country_code ] ) ) {
							$per_country[ $country_code ] = 0;
						}

						if ( $per_country[ $country_code ] >= $max_cities_per_country ) {
							$should_queue = false;
							$skipped_max_reached++;
						} else {
							$per_country[ $country_code ]++;
						}
					}
					
					if ( $should_queue ) {
						// Queue city using the helper method
						$queued += $this->queue_cities_batch( array( $city ), $options );
						
						// Log progress every 100 cities
						if ( $queued % 100 === 0 ) {
							file_put_contents( $debug_file, "Progress: Queued $queued cities (read $total_read total)...\n", FILE_APPEND );
						}
					}
				}
				
				// Reset for next object
				$json_buffer = '';
				$in_object = false;
			}
		}
		
		fclose( $handle );
		file_put_contents( $debug_file, "Streaming complete. Total objects read: $total_read\n", FILE_APPEND );

			$debug_file = WP_CONTENT_DIR . '/uploads/wta-cities-import-debug.log';
			$summary = sprintf(
				"COMPLETED: Queued=%d, Skipped_country=%d, Skipped_population=%d, Skipped_max=%d, Total_read=%d\n",
				$queued,
				$skipped_country,
				$skipped_population,
				$skipped_max_reached,
				$total_read
			);
			file_put_contents( $debug_file, $summary, FILE_APPEND );
			
			WTA_Logger::info( 'Cities import batch completed', array(
				'cities_queued' => $queued,
				'skipped_country' => $skipped_country,
				'skipped_population' => $skipped_population,
				'skipped_max_reached' => $skipped_max_reached,
				'total_read' => $total_read,
			) );

			WTA_Queue::mark_done( $item['id'] );
			
		} catch ( Exception $e ) {
			$debug_file = WP_CONTENT_DIR . '/uploads/wta-cities-import-debug.log';
			$error_msg = sprintf(
				"EXCEPTION: %s in %s:%d\n",
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			);
			file_put_contents( $debug_file, $error_msg, FILE_APPEND );
			
			WTA_Logger::error( 'CRITICAL: cities_import failed', array(
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			) );
			throw $e; // Re-throw to mark as failed
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
}


