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

		// Create post with Danish name and slug
		$post_id = wp_insert_post( array(
			'post_title'   => $data['name_local'],
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
		$country_post_id = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'post_status' => array( 'publish', 'draft' ), // IMPORTANT: Include draft!
			'meta_key'    => 'wta_country_id',
			'meta_value'  => $data['country_id'],
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
			WTA_Logger::info( 'Starting cities_import batch (STREAMING)', array(
				'file' => basename( $file_path ),
				'size_mb' => round( $file_size / 1024 / 1024, 2 ),
			) );

		// Stream JSON file line-by-line to avoid memory issues
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			throw new Exception( 'Could not open cities.json for reading' );
		}

		$min_population = isset( $options['min_population'] ) ? $options['min_population'] : 0;
		$base_language = get_option( 'wta_base_country_name', 'en' );
		
		$queued = 0;
		$skipped = 0;
		$line_number = 0;
		$batch = array();
		$batch_size = 100; // Process 100 cities at a time

		while ( ! feof( $handle ) ) {
			$line = fgets( $handle );
			$line_number++;
			
			if ( empty( $line ) || $line_number === 1 ) {
				continue; // Skip opening bracket
			}

			// Remove trailing comma and whitespace
			$line = trim( $line );
			if ( $line === '[' || $line === ']' ) {
				continue;
			}
			$line = rtrim( $line, ',' );

			// Decode single city JSON
			$city = json_decode( $line, true );
			if ( null === $city ) {
				continue; // Skip invalid JSON lines
			}

			// Apply population filter
			if ( isset( $city['population'] ) && $city['population'] < $min_population ) {
				$skipped++;
				continue;
			}

			// Add to batch
			$batch[] = $city;

			// Process batch when it reaches batch_size
			if ( count( $batch ) >= $batch_size ) {
				$queued += $this->queue_cities_batch( $batch, $options );
				$batch = array(); // Reset batch
				
				// Log progress every 1000 cities
				if ( $queued % 1000 === 0 ) {
					WTA_Logger::info( 'Cities streaming progress', array(
						'queued' => $queued,
						'skipped' => $skipped,
					) );
				}
			}
		}

		// Process remaining cities in batch
		if ( ! empty( $batch ) ) {
			$queued += $this->queue_cities_batch( $batch, $options );
		}

		fclose( $handle );

			$debug_file = WP_CONTENT_DIR . '/uploads/wta-cities-import-debug.log';
			$summary = "COMPLETED: Queued=$queued, Skipped=$skipped, Min_pop=$min_population\n";
			file_put_contents( $debug_file, $summary, FILE_APPEND );
			
			WTA_Logger::info( 'Cities import batch completed', array(
				'cities_queued' => $queued,
				'cities_skipped' => $skipped,
				'min_population' => $min_population,
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


