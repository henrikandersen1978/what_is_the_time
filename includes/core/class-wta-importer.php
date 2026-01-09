<?php
/**
 * Importer for preparing queue.
 *
 * Prepares import queue based on user selections.
 * CRITICAL: Correct population filter logic that includes cities with null population.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

class WTA_Importer {

	/**
	 * Prepare import (Pilanto-AI Model).
	 *
	 * Schedules single Action Scheduler actions for each continent/country,
	 * and delegates city scheduling to structure processor.
	 *
	 * @since    3.0.43
	 * @param    array $options Import options.
	 * @return   array          Statistics.
	 */
	public static function prepare_import( $options = array() ) {
		$defaults = array(
			'import_mode'         => 'continents',
			'selected_continents' => array(),
			'selected_countries'  => array(),
			'min_population'      => 0,
			'max_cities_per_country' => 0,
			'clear_queue'         => true,
		);

	$options = wp_parse_args( $options, $defaults );

	// Clear existing Action Scheduler actions if requested
	if ( $options['clear_queue'] ) {
		// Cancel all pending actions for our hooks
		as_unschedule_all_actions( 'wta_create_continent' );
		as_unschedule_all_actions( 'wta_create_country' );
		as_unschedule_all_actions( 'wta_create_city' );
		as_unschedule_all_actions( 'wta_schedule_cities' );
		as_unschedule_all_actions( 'wta_lookup_timezone' );
		as_unschedule_all_actions( 'wta_generate_ai_content' );
		WTA_Logger::info( 'All pending actions cleared before import' );
	}

	// v3.2.20: CRITICAL FIX - Pre-cache GeoNames translations BEFORE import!
	// This prevents timeout issues where first location triggers 2-5 min parsing
	// which may fail, leaving all subsequent locations with English names instead of translated ones.
	// On Danish site, this worked by luck (parsing completed before timeout).
	// On Swedish site, this failed (parsing timeout → no fallback → "copenhagen" instead of "köpenhamn").
	$lang_code = get_option( 'wta_base_language', 'da-DK' );
	WTA_Logger::info( 'Pre-caching GeoNames translations before import (may take 2-5 minutes)...', array(
		'language' => $lang_code,
		'file_size' => '~745 MB alternateNamesV2.txt',
	) );
	
	$prepare_success = WTA_GeoNames_Translator::prepare_for_import( $lang_code );
	
	if ( ! $prepare_success ) {
		// v3.2.23: CRITICAL FIX - ABORT import if GeoNames parsing fails!
		// Without translations, all cities get English names (copenhagen vs köpenhamn)
		WTA_Logger::error( 'FATAL: GeoNames translations failed - ABORTING import to prevent incorrect city names!', array(
			'language' => $lang_code,
			'file' => 'alternateNamesV2.txt',
			'possible_causes' => array(
				'File missing or corrupted',
				'PHP timeout (needs 2-5 minutes)',
				'Memory limit exceeded',
				'Disk space issue',
			),
			'solution' => 'Fix issue, then click "Clear Translation Cache" and retry import',
		) );
		
		return array(
			'continents' => 0,
			'countries' => 0,
			'cities' => 0,
			'error' => 'GeoNames translation parsing failed - import aborted',
		);
	}
	
	WTA_Logger::info( 'GeoNames translations ready for import!', array(
		'language' => $lang_code,
		'cache_key' => 'wta_geonames_translations_' . strtok( $lang_code, '-' ),
		'expires' => '24 hours',
	) );
	
	// v3.2.24: CRITICAL VERIFICATION - Double-check cache is readable!
	// Sometimes set_transient succeeds but get_transient immediately fails (race condition, DB replication lag, etc.)
	$test_geonameid = 2618425; // Copenhagen
	$test_translation = WTA_GeoNames_Translator::get_name( $test_geonameid, $lang_code );
	
	if ( false === $test_translation ) {
		WTA_Logger::error( 'FATAL: GeoNames cache verification FAILED - cache set but not readable!', array(
			'language' => $lang_code,
			'test_geonameid' => $test_geonameid,
			'test_name' => 'Copenhagen',
			'expected_sv' => 'Köpenhamn',
			'actual' => 'false (not found)',
			'possible_causes' => array(
				'Database replication lag',
				'Cache race condition',
				'Transient corruption',
			),
		) );
		
		return array(
			'continents' => 0,
			'countries' => 0,
			'cities' => 0,
			'error' => 'GeoNames cache not readable - import aborted',
		);
	}
	
	WTA_Logger::info( 'GeoNames cache verified working!', array(
		'test_geonameid' => $test_geonameid,
		'test_result' => $test_translation,
		'expected_sv' => 'Köpenhamn',
		'match' => ( $test_translation === 'Köpenhamn' ) ? 'YES ✅' : 'NO ❌',
	) );

	$stats = array(
		'continents' => 0,
		'countries'  => 0,
		'cities'     => 0,
	);

	// Fetch countries data from GeoNames countryInfo.txt
	$countries = WTA_GeoNames_Parser::parse_countryInfo();
		if ( false === $countries ) {
			WTA_Logger::error( 'Failed to parse countryInfo.txt' );
			return $stats;
		}

		// Schedule continents and filter countries
		$continents_scheduled = array();
		$filtered_countries = array();

		foreach ( $countries as $country ) {
			$continent = $country['continent'];
			$continent_code = WTA_Utils::get_continent_code( $continent );

			// Filter based on import mode
			if ( $options['import_mode'] === 'countries' ) {
				if ( ! empty( $options['selected_countries'] ) && ! in_array( $country['iso2'], $options['selected_countries'], true ) ) {
					continue;
				}
			} else {
				if ( ! empty( $options['selected_continents'] ) && ! in_array( $continent_code, $options['selected_continents'], true ) ) {
					continue;
				}
			}

			// Schedule continent (deduplicated)
			if ( ! in_array( $continent, $continents_scheduled, true ) ) {
				$name_local = WTA_AI_Translator::translate( $continent, 'continent' );
				as_schedule_single_action(
					time(),
					'wta_create_continent',
					array( $continent, $name_local ),  // Separate args, NOT nested array
					'wta_structure'
				);
				$continents_scheduled[] = $continent;
				$stats['continents']++;
			}

			// Add to filtered countries
			$filtered_countries[] = $country;
		}

		WTA_Logger::info( 'Continents scheduled', array( 'count' => $stats['continents'] ) );

		// Schedule countries (with small delay to spread load)
		$delay = 0;
		foreach ( $filtered_countries as $country ) {
			$geonameid = $country['geonameid'];
			$name_local = WTA_AI_Translator::translate( 
				$country['name'], 
				'country', 
				null, 
				$geonameid 
			);
			
			as_schedule_single_action(
				time() + $delay,
				'wta_create_country',
				array(  // Separate args, NOT nested array
					$country['name'],     // name
					$name_local,          // name_local
					$country['iso2'],     // country_code
					$country['iso2'],     // country_id
					$country['continent'], // continent
					null,                  // latitude
					null,                  // longitude
					$geonameid            // geonameid
				),
				'wta_structure'
			);
			$stats['countries']++;
			
			// Spread countries over 10 seconds
			$delay = ( $delay + 1 ) % 10;
		}

		WTA_Logger::info( 'Countries scheduled', array( 'count' => $stats['countries'] ) );

		// Schedule cities import job to run after continents/countries
		$cities_file = WTA_GeoNames_Parser::get_cities_file_path();
		if ( false === $cities_file ) {
			WTA_Logger::error( 'cities500.txt not found - please upload to wp-content/uploads/world-time-ai-data/' );
			return $stats;
		}

		$filtered_country_codes = array_column( $filtered_countries, 'iso2' );

	// Schedule a single action to process all cities
	// v3.0.70: Wait 15 minutes (900s) to ensure ALL continents and countries are created
	as_schedule_single_action(
		time() + 900, // Wait 15 minutes for all 244 countries to be created
		'wta_schedule_cities',
		array(
			'file_path'              => $cities_file,
			'min_population'         => $options['min_population'],
			'max_cities_per_country' => $options['max_cities_per_country'],
			'filtered_country_codes' => $filtered_country_codes,
			'line_offset'            => 0,
			'chunk_size'             => 10000,
		),
		'wta_structure'
	);

		$stats['cities'] = 1; // This is the scheduler job count

		WTA_Logger::info( 'Cities scheduler job scheduled' );

	return $stats;
}

/**
 * Send chunk progress email notification.
 * 
 * Sends detailed email after each chunk completion to monitor import progress.
 * Critical for verifying offset advancement and detecting infinite loops.
 * 
 * @since 3.0.70
 * @param array $chunk_data Chunk completion data.
 */
private static function send_chunk_notification( $chunk_data ) {
	$admin_email = get_option( 'admin_email' );
	
	$chunk_num = $chunk_data['chunk_number'];
	$prev_offset = $chunk_data['prev_offset'];
	$next_offset = $chunk_data['next_offset'];
	$offset_diff = $next_offset - $prev_offset;
	$scheduled = $chunk_data['scheduled'];
	$skipped = $chunk_data['skipped'];
	$first_city = $chunk_data['first_city'];
	$last_city = $chunk_data['last_city'];
	$progress_pct = $chunk_data['progress_percent'];
	$is_stuck = isset( $chunk_data['is_stuck'] ) ? $chunk_data['is_stuck'] : false;
	
	$subject = $is_stuck 
		? '🚨 CRITICAL: World Time AI Import STUCK!' 
		: "✅ World Time AI - Chunk #{$chunk_num} Complete ({$progress_pct}%)";
	
	$message = "World Time AI Cities Import - Chunk #{$chunk_num} Status\n";
	$message .= "================================================================\n\n";
	
	if ( $is_stuck ) {
		$message .= "🚨 CRITICAL ERROR DETECTED!\n";
		$message .= "Offset is NOT advancing - same cities being scheduled repeatedly!\n";
		$message .= "Import has been AUTOMATICALLY STOPPED to prevent infinite loop.\n\n";
		$message .= "ACTION REQUIRED:\n";
		$message .= "1. Check code for offset calculation bug\n";
		$message .= "2. DO NOT restart import until bug is fixed\n";
		$message .= "3. Review logs at: " . get_site_url() . "/wp-content/uploads/world-time-ai-data/logs/\n\n";
	} else {
		$message .= "✅ Chunk completed successfully!\n\n";
	}
	
	$message .= "OFFSET PROGRESSION (THIS IS KEY!):\n";
	$message .= "  Previous offset: {$prev_offset}\n";
	$message .= "  Next offset:     {$next_offset}\n";
	$message .= "  Difference:      {$offset_diff} (should be ~{$scheduled})\n";
	$message .= "  Status:          " . ( $offset_diff > 0 ? '✅ ADVANCING' : '❌ STUCK' ) . "\n\n";
	
	$message .= "CITIES PROCESSED:\n";
	$message .= "  Scheduled: {$scheduled}\n";
	$message .= "  Skipped:   {$skipped}\n";
	$message .= "  First city in chunk: " . ( $first_city ? $first_city : 'N/A' ) . "\n";
	$message .= "  Last city in chunk:  {$last_city}\n\n";
	
	$message .= "PROGRESS:\n";
	$message .= "  Overall: {$progress_pct}% complete\n";
	$message .= "  Chunk #{$chunk_num} of ~15 total chunks\n";
	$message .= "  Estimated chunks remaining: " . ( 15 - $chunk_num ) . "\n\n";
	
	if ( ! $is_stuck ) {
		$message .= "WHAT TO CHECK:\n";
		$message .= "✅ Offset difference should be ~{$scheduled}\n";
		$message .= "✅ First/Last city should be DIFFERENT from previous chunk\n";
		$message .= "✅ Progress % should increase with each chunk\n\n";
		
		$message .= "If everything looks correct, the import will continue automatically.\n";
		$message .= "Next chunk will start in ~5 seconds.\n\n";
		
		$message .= "TO STOP IMPORT:\n";
		$message .= "Go to: WP Admin > Tools > Action Scheduler\n";
		$message .= "Cancel all pending 'wta_schedule_cities' actions\n\n";
	}
	
	$message .= "================================================================\n";
	$message .= "Timestamp: " . current_time( 'Y-m-d H:i:s' ) . "\n";
	$message .= "Site: " . get_site_url() . "\n";
	$message .= "Admin: " . admin_url( 'tools.php?page=action-scheduler' ) . "\n";
	
	wp_mail( $admin_email, $subject, $message );
	
	WTA_Logger::info( '📧 Chunk notification email sent', array(
		'chunk_number' => $chunk_num,
		'recipient' => $admin_email,
		'is_stuck' => $is_stuck,
	) );
}

/**
 * Schedule cities from GeoNames file (Pilanto-AI Model).
	 *
	 * Reads cities500.txt and schedules ONE Action Scheduler action per city.
	 * This allows Action Scheduler to parallelize processing.
	 *
	 * v3.0.69: Chunked processing to prevent timeout on large imports.
	 * Processes cities in chunks of 10,000, self-rescheduling for next chunk.
	 *
	 * @since    3.0.43
	 * @since    3.0.69 Added chunk processing parameters.
	 * @param    string $file_path              Path to cities500.txt.
	 * @param    int    $min_population         Minimum population filter.
	 * @param    int    $max_cities_per_country Max cities per country.
	 * @param    array  $filtered_country_codes Country codes to include.
	 * @param    int    $line_offset            Line number to start from (for chunking).
	 * @param    int    $chunk_size             Maximum cities to schedule per chunk.
	 */
	public static function schedule_cities( $file_path, $min_population, $max_cities_per_country, $filtered_country_codes, $line_offset = 0, $chunk_size = 10000 ) {
		set_time_limit( 300 ); // 5 minutes per chunk

	WTA_Logger::info( 'Starting cities scheduling', array(
		'file'                   => basename( $file_path ),
		'min_population'         => $min_population,
		'max_cities_per_country' => $max_cities_per_country,
		'filtered_countries'     => count( $filtered_country_codes ),
		'chunk_offset'           => $line_offset,
		'chunk_size'             => $chunk_size,
	) );

	$file = fopen( $file_path, 'r' );
	if ( ! $file ) {
		WTA_Logger::error( 'Failed to open cities500.txt' );
		return;
	}

	$scheduled = 0;
	$skipped = 0;
	$per_country = array();
	$delay = 0;
	$file_line = 0; // v3.0.74: Start from 0 to properly track file position
	$first_city_in_chunk = null; // v3.0.70: Track first city for email notification

	// v3.0.74: CRITICAL FIX - Skip lines to reach offset BEFORE processing
	// Without this, we always read from line 1, causing infinite loop on same cities
	if ( $line_offset > 0 ) {
		WTA_Logger::debug( 'Skipping to offset', array(
			'target_offset' => $line_offset,
		) );
		
		while ( $file_line < $line_offset && fgets( $file ) !== false ) {
			$file_line++;
		}
		
		WTA_Logger::debug( 'Reached offset', array(
			'file_position' => $file_line,
			'target_offset' => $line_offset,
		) );
	}

	// v3.0.74: Now process lines from offset onwards
	while ( ( $line = fgets( $file ) ) !== false ) {
		$file_line++; // Increment for each line read
		
		$parts = explode( "\t", trim( $line ) );
			
			if ( count( $parts ) < 19 ) {
				continue;
			}

			$geonameid = $parts[0];
			$name = $parts[1];
			$latitude = $parts[4];
			$longitude = $parts[5];
			$feature_class = $parts[6];
			$country_code = $parts[8];
			$population = $parts[14];

			// Only populated places
			if ( $feature_class !== 'P' ) {
				continue;
			}

			// Filter by country
			if ( ! empty( $filtered_country_codes ) && ! in_array( strtoupper( $country_code ), $filtered_country_codes, true ) ) {
				$skipped++;
				continue;
			}

			// Population filter
			if ( $min_population > 0 ) {
				$pop = intval( $population );
				if ( $pop > 0 && $pop < $min_population ) {
					$skipped++;
					continue;
				}
			}

			// Max cities per country
			if ( $max_cities_per_country > 0 ) {
				if ( ! isset( $per_country[ $country_code ] ) ) {
					$per_country[ $country_code ] = 0;
				}

				if ( $per_country[ $country_code ] >= $max_cities_per_country ) {
					$skipped++;
					continue;
				}

				$per_country[ $country_code ]++;
			}

		// Translate city name
		$name_local = WTA_AI_Translator::translate( $name, 'city', null, intval( $geonameid ) );

	// v3.0.70: Track first city for email notification
	if ( $scheduled === 0 ) {
		$first_city_in_chunk = $name;
	}

	// v3.0.72: Schedule cities IMMEDIATELY (no delay)
	// Processing controlled by 'wta_enable_city_processing' toggle in admin
	// This allows chunking to complete fast (~30-45 min) before processing starts
	
	// Schedule city creation
		as_schedule_single_action(
			time() + $delay,  // Immediate + spread (1 per second)
			'wta_create_city',
			array(  // Separate args, NOT nested array
				$name,                         // name
				$name_local,                   // name_local
				intval( $geonameid ),          // geonameid
				strtoupper( $country_code ),   // country_code
				floatval( $latitude ),         // latitude
				floatval( $longitude ),        // longitude
				intval( $population )          // population
			),
			'wta_structure'
		);

		$scheduled++;

		// Spread cities over time (1 per second)
		$delay++;

		// Log progress every 1000 cities
		if ( $scheduled % 1000 === 0 ) {
			WTA_Logger::info( 'Cities scheduling progress', array(
				'scheduled' => $scheduled,
				'skipped'   => $skipped,
			) );
		}

		// Prevent timeout
		if ( $scheduled % 500 === 0 ) {
			set_time_limit( 60 );
		}
		
	// v3.0.69: Stop after chunk_size cities scheduled
	if ( $scheduled >= $chunk_size ) {
		WTA_Logger::info( 'Chunk limit reached, preparing next chunk', array(
			'scheduled_in_chunk' => $scheduled,
			'current_line' => $file_line, // v3.0.70: Fixed - use $file_line
		) );
		break;
	}
	}

	$reached_eof = feof( $file );
	fclose( $file );

	// v3.0.70: Calculate chunk number and progress
	$chunk_number = ( $line_offset === 0 ) ? 1 : ( floor( $line_offset / $chunk_size ) + 1 );
	$progress_percent = round( ( $file_line / 150000 ) * 100, 1 ); // Approximate total lines

	WTA_Logger::info( 'Cities scheduling chunk complete', array(
		'chunk_number'   => $chunk_number,
		'scheduled'      => $scheduled,
		'skipped'        => $skipped,
		'prev_offset'    => $line_offset,
		'next_offset'    => $file_line, // v3.0.70: Fixed - use $file_line
		'offset_diff'    => $file_line - $line_offset,
		'reached_eof'    => $reached_eof,
		'first_city'     => $first_city_in_chunk,
		'last_city'      => isset( $name ) ? $name : 'unknown',
		'progress_pct'   => $progress_percent,
	) );

	// v3.0.70: CRITICAL SAFETY CHECK - Detect stuck offset
	$offset_is_stuck = ( $line_offset > 0 && $file_line <= $line_offset );

	if ( $offset_is_stuck ) {
		WTA_Logger::error( '🚨 CRITICAL: Offset not advancing! Import STOPPED.', array(
			'previous_offset' => $line_offset,
			'current_offset'  => $file_line,
			'scheduled'       => $scheduled,
		) );
		
		// Send emergency email
		self::send_chunk_notification( array(
			'chunk_number'      => $chunk_number,
			'prev_offset'       => $line_offset,
			'next_offset'       => $file_line,
			'scheduled'         => $scheduled,
			'skipped'           => $skipped,
			'first_city'        => $first_city_in_chunk,
			'last_city'         => isset( $name ) ? $name : 'unknown',
			'progress_percent'  => $progress_percent,
			'is_stuck'          => true,
		) );
		
		// DO NOT schedule next chunk - STOP HERE
		return;
	}

	// v3.0.70: Send email notification after EVERY chunk
	self::send_chunk_notification( array(
		'chunk_number'      => $chunk_number,
		'prev_offset'       => $line_offset,
		'next_offset'       => $file_line,
		'scheduled'         => $scheduled,
		'skipped'           => $skipped,
		'first_city'        => $first_city_in_chunk,
		'last_city'         => isset( $name ) ? $name : 'unknown',
		'progress_percent'  => $progress_percent,
		'is_stuck'          => false,
	) );
	
	// v3.0.69: If more cities remain, schedule next chunk
	if ( ! $reached_eof && $scheduled >= $chunk_size ) {
		WTA_Logger::info( 'Scheduling next chunk', array(
			'next_offset' => $file_line, // v3.0.70: Fixed - use $file_line
		) );
		
		as_schedule_single_action(
			time() + 5, // Wait 5 seconds before next chunk
			'wta_schedule_cities',
			array(
				'file_path'              => $file_path,
				'min_population'         => $min_population,
				'max_cities_per_country' => $max_cities_per_country,
				'filtered_country_codes' => $filtered_country_codes,
				'line_offset'            => $file_line, // v3.0.70: Fixed - use $file_line
				'chunk_size'             => $chunk_size,
			),
			'wta_structure'
		);
	} else {
		WTA_Logger::info( 'All cities scheduled - import complete', array(
			'total_scheduled' => $scheduled,
			'total_skipped'   => $skipped,
		) );
	}
}

/**
 * Start processing for cities waiting for toggle (CHUNKED).
 * 
 * v3.0.78: CRITICAL FIX - Process in chunks to avoid timeout.
 * Processes 5000 cities per chunk and schedules next chunk automatically.
 * 
 * Called when admin enables 'wta_enable_city_processing' option.
 * Schedules timezone lookup and AI content generation for cities
 * that were created but marked as 'waiting_for_toggle'.
 * 
 * @since 3.0.72
 * @since 3.0.78 Added chunking to prevent timeout with large imports.
 * @param int $offset Starting offset for this chunk (default 0).
 * @return int Number of cities queued for processing in this chunk.
 */
public static function start_waiting_city_processing( $offset = 0 ) {
	global $wpdb;
	
	set_time_limit( 120 ); // 2 minutes per chunk
	
	$chunk_size = 5000; // Process 5000 cities per chunk
	
	// v3.0.78: First chunk only - count total waiting cities
	if ( $offset === 0 ) {
		$total_waiting = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
				 WHERE p.post_type = %s
				 AND p.post_status IN ('draft', 'publish')
				 AND pm.meta_key = 'wta_timezone_status' 
				 AND pm.meta_value = 'waiting_for_toggle'",
				WTA_POST_TYPE
			)
		);
		
		WTA_Logger::info( '🚀 Starting CHUNKED processing for waiting cities', array(
			'total_waiting' => $total_waiting,
			'chunk_size' => $chunk_size,
			'estimated_chunks' => ceil( $total_waiting / $chunk_size ),
		) );
	}
	
	// Find cities for THIS chunk (with LIMIT and OFFSET)
	$waiting_cities = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID, pm1.meta_value as latitude, pm2.meta_value as longitude, pm3.meta_value as country_code
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'wta_timezone_status' AND pm.meta_value = 'waiting_for_toggle'
			 INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'wta_latitude'
			 INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'wta_longitude'
			 INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'wta_country_code'
			 WHERE p.post_type = %s
			 AND p.post_status IN ('draft', 'publish')
			 ORDER BY p.ID ASC
			 LIMIT %d OFFSET %d",
			WTA_POST_TYPE,
			$chunk_size,
			$offset
		)
	);
	
	if ( empty( $waiting_cities ) ) {
		WTA_Logger::info( '✅ All waiting cities processed - COMPLETE!', array(
			'final_offset' => $offset,
		) );
		return 0;
	}
	
	$scheduled = 0;
	$delay = 0;
	
	foreach ( $waiting_cities as $city ) {
		$country_code = $city->country_code;
		$post_id = intval( $city->ID );
		$latitude = floatval( $city->latitude );
		$longitude = floatval( $city->longitude );
		
		// Check if complex country (needs API lookup)
		if ( WTA_Timezone_Helper::is_complex_country( $country_code ) ) {
			// Schedule timezone lookup
			as_schedule_single_action(
				time() + $delay,
				'wta_lookup_timezone',
				array( $post_id, $latitude, $longitude ),
				'wta_timezone'
			);
			
			update_post_meta( $post_id, 'wta_timezone_status', 'pending' );
		} else {
			// Simple country - check hardcoded list
			$timezone = WTA_Timezone_Helper::get_country_timezone( $country_code );
			
			if ( $timezone ) {
				// Use hardcoded timezone
				update_post_meta( $post_id, 'wta_timezone', $timezone );
				update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
				update_post_meta( $post_id, 'wta_has_timezone', 1 );
				
				// Schedule AI content immediately
				as_schedule_single_action(
					time() + $delay,
					'wta_generate_ai_content',
					array( $post_id, 'city', false ),
					'wta_ai_content'
				);
			} else {
				// Not in list - use API
				as_schedule_single_action(
					time() + $delay,
					'wta_lookup_timezone',
					array( $post_id, $latitude, $longitude ),
					'wta_timezone'
				);
				
				update_post_meta( $post_id, 'wta_timezone_status', 'pending' );
			}
		}
		
		$scheduled++;
		
		// Spread over time (1 per second)
		$delay++;
	}
	
	$chunk_number = floor( $offset / $chunk_size ) + 1;
	$next_offset = $offset + $scheduled;
	
	WTA_Logger::info( '✅ Chunk processing complete', array(
		'chunk_number' => $chunk_number,
		'scheduled_in_chunk' => $scheduled,
		'current_offset' => $offset,
		'next_offset' => $next_offset,
	) );
	
	// v3.0.79: Schedule next chunk if we GOT a full batch from DB
	// Check count($waiting_cities), NOT $scheduled (which can be less if cities skip)
	if ( count( $waiting_cities ) >= $chunk_size ) {
		WTA_Logger::info( '📦 Scheduling next chunk', array(
			'next_offset' => $next_offset,
		) );
		
		as_schedule_single_action(
			time() + 5, // Wait 5 seconds before next chunk
			'wta_start_waiting_city_processing',
			array( $next_offset ),
			'wta_coordinator'
		);
	} else {
		// Last chunk - send completion notification
		WTA_Logger::info( '🎉 ALL CHUNKS COMPLETE!', array(
			'total_processed' => $next_offset,
			'final_chunk' => $chunk_number,
		) );
	}
	
	return $scheduled;
}

	/**
	 * Legacy method kept for backward compatibility.
	 * Now delegates to schedule_cities for single actions.
	 *
	 * @deprecated 3.0.43 Use schedule_cities() instead.
	 * @param    array $cities  Cities data.
	 * @param    array $options Import options.
	 * @return   int            Number of cities queued.
	 */
	public static function queue_cities_from_array( $cities, $options = array() ) {
		$min_population = isset( $options['min_population'] ) ? (int) $options['min_population'] : 0;
		$max_cities_per_country = isset( $options['max_cities_per_country'] ) ? (int) $options['max_cities_per_country'] : 0;
		$filtered_country_codes = isset( $options['filtered_country_codes'] ) ? $options['filtered_country_codes'] : array();

		$scheduled = 0;
		$per_country = array();
		$delay = 0;

		foreach ( $cities as $city ) {
			// Filter by country_code
			if ( ! empty( $filtered_country_codes ) && ! in_array( $city['country_code'], $filtered_country_codes, true ) ) {
				continue;
			}

			// Population filter
			if ( $min_population > 0 ) {
				if ( isset( $city['population'] ) && null !== $city['population'] ) {
					$population = (int) $city['population'];
					if ( $population > 0 && $population < $min_population ) {
						continue;
					}
				}
			}

			// Max cities per country
			if ( $max_cities_per_country > 0 ) {
				$country_id = $city['country_code'];
				if ( ! isset( $per_country[ $country_id ] ) ) {
					$per_country[ $country_id ] = 0;
				}

				if ( $per_country[ $country_id ] >= $max_cities_per_country ) {
					continue;
				}

				$per_country[ $country_id ]++;
			}

			// Schedule city with Action Scheduler
			$geonameid = $city['geonameid'];
			$name_local = WTA_AI_Translator::translate( 
				$city['name'], 
				'city', 
				null, 
				$geonameid 
			);
			
			as_schedule_single_action(
				time() + $delay,
				'wta_create_city',
				array(  // Separate args, NOT nested array
					$city['name'],                                           // name
					$name_local,                                             // name_local
					$geonameid,                                              // geonameid
					$city['country_code'],                                   // country_code
					isset( $city['latitude'] ) ? $city['latitude'] : 0.0,   // latitude
					isset( $city['longitude'] ) ? $city['longitude'] : 0.0, // longitude
					isset( $city['population'] ) ? $city['population'] : 0  // population
				),
				'wta_structure'
			);

			$scheduled++;
			$delay++;

			// Prevent timeout
			if ( $scheduled % 100 === 0 ) {
				set_time_limit( 30 );
			}
		}

		return $scheduled;
	}
}
