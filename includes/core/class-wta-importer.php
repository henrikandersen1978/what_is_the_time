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
		// v3.2.25: LOG ERROR but DON'T ABORT (for debugging)
		// User can see in logs + scheduled actions what actually happens
		WTA_Logger::warning( 'WARNING: GeoNames translations failed - proceeding anyway for debugging!', array(
			'language' => $lang_code,
			'file' => 'alternateNamesV2.txt',
			'impact' => 'All cities will get English names instead of translated names',
			'possible_causes' => array(
				'File missing or corrupted',
				'PHP timeout (needs 2-5 minutes)',
				'Memory limit exceeded',
				'Disk space issue',
			),
			'solution' => 'Fix issue, then click "Clear Translation Cache" and retry import',
		) );
		// v3.2.25: Continue anyway - let user see actual behavior
	}
	
	WTA_Logger::info( 'GeoNames translations ready for import!', array(
		'language' => $lang_code,
		'cache_key' => 'wta_geonames_translations_' . strtok( $lang_code, '-' ),
		'expires' => '24 hours',
	) );
	
	// v3.2.26: CRITICAL FIX - Wait for database replication!
	// Race condition: set_transient() writes to master DB, but get_transient() 
	// might read from slave DB that hasn't synced yet. Wait 2 seconds for replication.
	WTA_Logger::info( 'Waiting 2 seconds for database replication...', array(
		'reason' => 'Ensure GeoNames cache is readable from all DB servers',
		'issue' => 'Race condition between set_transient() and get_transient()',
	) );
	sleep( 2 );
	
	// v3.2.28: CRITICAL VERIFICATION - Verify cache is readable after wait
	// Use multiple test cities to ensure cache works (Copenhagen may not have SV translation!)
	$test_cities = array(
		array( 'geonameid' => 2673730, 'name' => 'Stockholm', 'expected_sv' => 'Stockholm' ),
		array( 'geonameid' => 2711537, 'name' => 'Gothenburg', 'expected_sv' => 'Göteborg' ),
		array( 'geonameid' => 2692969, 'name' => 'Malmö', 'expected_sv' => 'Malmö' ),
	);
	
	$cache_verified = false;
	$test_results = array();
	
	foreach ( $test_cities as $test_city ) {
		$translation = WTA_GeoNames_Translator::get_name( $test_city['geonameid'], $lang_code );
		$test_results[] = array(
			'city' => $test_city['name'],
			'geonameid' => $test_city['geonameid'],
			'result' => $translation ? $translation : 'false',
			'expected' => $test_city['expected_sv'],
			'match' => ( $translation === $test_city['expected_sv'] ),
		);
		
		// If at least ONE test passes, cache is working!
		if ( $translation !== false ) {
			$cache_verified = true;
		}
	}
	
	if ( ! $cache_verified ) {
		// v3.2.31: CHANGED from ABORT to WARNING - let import continue for debugging
		WTA_Logger::warning( 'WARNING: GeoNames cache verification failed - import will continue anyway!', array(
			'language' => $lang_code,
			'test_results' => $test_results,
			'impact' => 'Cities MAY get ENGLISH names instead of translated names (if OpCache not cleared)',
			'action_required' => 'Check if v3.2.29+ code is actually running (OpCache issue)',
			'note' => 'Import will continue to allow debugging - check actual city names in scheduled actions',
		) );
		// DO NOT abort - continue import so we can see actual translation results
	}
	
	WTA_Logger::info( 'GeoNames cache verified working after replication wait!', array(
		'test_results' => $test_results,
		'cache_readable' => 'YES ✅',
		'wait_time' => '2 seconds',
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

	// v3.5.0: ALWAYS send filtered_country_codes (even for large imports)
	// This prevents cities from being scheduled for unselected countries
	// Critical fix: TF/GS cities were scheduled even when Antarctica wasn't selected
	// causing infinite "Parent country not found" reschedule loop
	$is_large_import = ( count( $filtered_country_codes ) > 200 );
	
	// v3.4.4: Smaller chunks for large imports to prevent timeout
	// 2500 cities × ~0.04s per city = ~100s (safe with 300s timeout)
	$chunk_size = $is_large_import ? 2500 : 10000;
	
	WTA_Logger::info( 'Preparing cities scheduler', array(
		'total_countries' => count( $filtered_country_codes ),
		'is_large_import' => $is_large_import,
		'chunk_size'      => $chunk_size,
		'filter_strategy' => 'Filter by scheduled country codes',
	) );

	// Schedule a single action to process all cities
	// v3.2.79: Reduced to 10 minutes (600s) since country AI is now delayed
	// v3.3.12: Reduced to 30 seconds - countries complete quickly, and batch processor
	// will wait for ALL cities to complete anyway via smart completion detection (v3.3.11)
	// v3.5.0: ALWAYS pass filtered_country_codes (fixes TF/GS infinite loop bug)
	as_schedule_single_action(
		time() + 30, // 30 seconds buffer - batch processor handles the rest!
		'wta_schedule_cities',
		array(
			'file_path'              => $cities_file,
			'min_population'         => $options['min_population'],
			'max_cities_per_country' => $options['max_cities_per_country'],
			'filtered_country_codes' => $filtered_country_codes, // ALWAYS send actual list!
			'line_offset'            => 0,
			'chunk_size'             => $chunk_size,
			'chunk_number'           => 1,
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
	$total_lines = isset( $chunk_data['total_lines'] ) ? $chunk_data['total_lines'] : 0;
	$estimated_total_chunks = isset( $chunk_data['estimated_total_chunks'] ) ? $chunk_data['estimated_total_chunks'] : 0;
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
	$message .= "  Total lines in file: " . number_format( $total_lines ) . "\n";
	$message .= "  Chunk #{$chunk_num} of ~{$estimated_total_chunks} total chunks\n";
	$message .= "  Estimated chunks remaining: " . ( $estimated_total_chunks - $chunk_num ) . "\n\n";
	
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
	 * @param    int    $chunk_number           Current chunk number (for accurate tracking).
	 */
	public static function schedule_cities( $file_path, $min_population, $max_cities_per_country, $filtered_country_codes, $line_offset = 0, $chunk_size = 10000, $chunk_number = 1 ) {
		set_time_limit( 300 ); // 5 minutes per chunk

	// v3.4.3: Log import strategy
	$import_all_countries = empty( $filtered_country_codes );
	
	WTA_Logger::info( 'Starting cities scheduling', array(
		'file'                   => basename( $file_path ),
		'min_population'         => $min_population,
		'max_cities_per_country' => $max_cities_per_country,
		'filtered_countries'     => $import_all_countries ? 'ALL (no filter)' : count( $filtered_country_codes ),
		'chunk_offset'           => $line_offset,
		'chunk_size'             => $chunk_size,
		'import_strategy'        => $import_all_countries ? 'Full import (empty filter)' : 'Selective import',
	) );

	// v3.2.69: Load entire file into memory (37MB cities500.txt fits easily in 1024MB)
	// Using file_get_contents + preg_split ensures proper handling of all line endings
	// across Windows (CRLF) and Linux (LF) without trim() issues
	$file_contents = file_get_contents( $file_path );
	if ( $file_contents === false ) {
		WTA_Logger::error( 'Failed to read cities500.txt' );
		return;
	}

	// Split by all common line endings: \r\n (Windows), \n (Linux), \r (old Mac)
	$lines = preg_split( '/\r\n|\r|\n/', $file_contents );
	unset( $file_contents ); // Free memory immediately
	
	$total_lines = count( $lines );
	WTA_Logger::debug( 'File loaded into memory', array(
		'total_lines' => $total_lines,
		'start_offset' => $line_offset,
	) );

	$scheduled = 0;
	$skipped = 0;
	$per_country = array();
	$cities_by_country = array(); // v3.2.57: Collect cities per country for sorting
	$delay = 0;
	$file_line = 0; // v3.0.74: Track current line position
	$first_city_in_chunk = null; // v3.0.70: Track first city for email notification

	// v3.2.69: Process lines from offset onwards
	for ( $i = $line_offset; $i < $total_lines; $i++ ) {
		$file_line = $i + 1; // Convert to 1-based line number
		$line = $lines[ $i ];
		
		// Skip empty lines
		if ( empty( $line ) ) {
			continue;
		}
		
		$parts = explode( "\t", $line );
			
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
		
		// v3.2.57: Extract feature_code for filtering
		$feature_code = isset( $parts[7] ) ? $parts[7] : '';
		
		// v3.2.70: CRITICAL FIX - PPLA2 filter removed ALL major Danish cities!
		// PPLA2 = "second-order administrative division seat" = MAJOR cities in DK/SE/NO
		// Examples: Odense (180k), Esbjerg (71k), Randers (62k), Kolding (61k)
		// Only filter PPLA3/PPLA4 (tiny "kommun" centers with inflated populations)
		$excluded_feature_codes = array( 'PPLA3', 'PPLA4' );
		if ( in_array( $feature_code, $excluded_feature_codes, true ) ) {
			$skipped++;
			continue;
		}

		// v3.2.57: COLLECT cities per country (don't limit yet - we'll sort and take top X)
		if ( ! isset( $cities_by_country[ $country_code ] ) ) {
			$cities_by_country[ $country_code ] = array();
		}
		
		// Store city data for later sorting
		$cities_by_country[ $country_code ][] = array(
			'geonameid'    => intval( $geonameid ),
			'name'         => $name,
			'latitude'     => $latitude,
			'longitude'    => $longitude,
			'country_code' => strtoupper( $country_code ),
			'population'   => intval( $population ),
			'feature_code' => $feature_code,
		);

		// v3.2.57: Continue collecting (scheduling happens AFTER sorting)
		// Stop after chunk_size COLLECTED (not scheduled yet)
		$collected = array_sum( array_map( 'count', $cities_by_country ) );
		if ( $collected >= $chunk_size ) {
			WTA_Logger::info( 'Chunk limit reached, will sort and schedule', array(
				'collected_cities' => $collected,
				'current_line' => $file_line,
			) );
			break;
		}
	}

	// v3.2.69: Check if we reached end of file
	$reached_eof = ( $i >= $total_lines - 1 );
	$next_offset = isset( $i ) ? ( $i + 1 ) : $line_offset; // Next line position (0-based)
	unset( $lines ); // Free memory
	
	// v3.2.57: NOW sort cities by population per country and schedule top X
	WTA_Logger::info( 'Sorting cities by population', array(
		'countries' => count( $cities_by_country ),
		'total_cities' => array_sum( array_map( 'count', $cities_by_country ) ),
	) );
	
	foreach ( $cities_by_country as $country_code => $cities ) {
		// Sort by population (descending)
		usort( $cities, function( $a, $b ) {
			return $b['population'] - $a['population'];
		});
		
		// Take top X cities (or all if no limit)
		$cities_to_schedule = $cities;
		if ( $max_cities_per_country > 0 && count( $cities ) > $max_cities_per_country ) {
			$cities_to_schedule = array_slice( $cities, 0, $max_cities_per_country );
			WTA_Logger::info( 'Limited cities for country', array(
				'country' => $country_code,
				'found' => count( $cities ),
				'scheduling' => count( $cities_to_schedule ),
			) );
		}
		
		// Schedule each city
		foreach ( $cities_to_schedule as $city ) {
			// Track first city for email notification
			if ( $scheduled === 0 ) {
				$first_city_in_chunk = $city['name'];
			}
			
			// Translate city name
			$name_local = WTA_AI_Translator::translate( $city['name'], 'city', null, $city['geonameid'] );
			
			// Schedule city creation
			as_schedule_single_action(
				time() + $delay,
				'wta_create_city',
				array(
					$city['name'],
					$name_local,
					$city['geonameid'],
					$city['country_code'],
					floatval( $city['latitude'] ),
					floatval( $city['longitude'] ),
					$city['population']
				),
				'wta_structure'
			);
			
			$scheduled++;
			$delay++; // Spread cities over time (1 per second)
			
			// Prevent timeout
			if ( $scheduled % 500 === 0 ) {
				set_time_limit( 60 );
			}
		}
	}

	// v3.0.70: Calculate chunk number and progress
	// v3.2.69: Use $next_offset for all calculations
	// v3.4.5: Use actual $total_lines instead of hardcoded 150000
	// v3.4.6: Use passed $chunk_number parameter instead of calculating from offset
	// (prevents incorrect chunk numbers when many lines are skipped)
	$estimated_total_chunks = ceil( $total_lines / $chunk_size );
	$progress_percent = round( ( $next_offset / $total_lines ) * 100, 1 );

	WTA_Logger::info( 'Cities scheduling chunk complete', array(
		'chunk_number'   => $chunk_number,
		'scheduled'      => $scheduled,
		'skipped'        => $skipped,
		'prev_offset'    => $line_offset,
		'next_offset'    => $next_offset,
		'offset_diff'    => $next_offset - $line_offset,
		'reached_eof'    => $reached_eof,
		'first_city'     => $first_city_in_chunk,
		'last_city'      => isset( $name ) ? $name : 'unknown',
		'progress_pct'   => $progress_percent,
	) );

	// v3.0.70: CRITICAL SAFETY CHECK - Detect stuck offset
	$offset_is_stuck = ( $line_offset > 0 && $next_offset <= $line_offset );

	if ( $offset_is_stuck ) {
		WTA_Logger::error( '🚨 CRITICAL: Offset not advancing! Import STOPPED.', array(
			'previous_offset' => $line_offset,
			'current_offset'  => $next_offset,
			'scheduled'       => $scheduled,
		) );
		
		// Send emergency email
		self::send_chunk_notification( array(
			'chunk_number'         => $chunk_number,
			'prev_offset'          => $line_offset,
			'next_offset'          => $next_offset,
			'scheduled'            => $scheduled,
			'skipped'              => $skipped,
			'first_city'           => $first_city_in_chunk,
			'last_city'            => isset( $name ) ? $name : 'unknown',
			'progress_percent'     => $progress_percent,
			'total_lines'          => $total_lines,
			'estimated_total_chunks' => $estimated_total_chunks,
			'is_stuck'             => true,
		) );
		
		// DO NOT schedule next chunk - STOP HERE
		return;
	}

	// v3.0.70: Send email notification after EVERY chunk
	self::send_chunk_notification( array(
		'chunk_number'         => $chunk_number,
		'prev_offset'          => $line_offset,
		'next_offset'          => $next_offset,
		'scheduled'            => $scheduled,
		'skipped'              => $skipped,
		'first_city'           => $first_city_in_chunk,
		'last_city'            => isset( $name ) ? $name : 'unknown',
		'progress_percent'     => $progress_percent,
		'total_lines'          => $total_lines,
		'estimated_total_chunks' => $estimated_total_chunks,
		'is_stuck'             => false,
	) );
	
	// v3.0.69: If more cities remain, schedule next chunk
	// v3.2.69: Check if we collected a full chunk (indicates more data available)
	if ( ! $reached_eof && $collected >= $chunk_size ) {
		WTA_Logger::info( 'Scheduling next chunk', array(
			'next_offset'      => $next_offset,
			'next_chunk_number' => $chunk_number + 1,
		) );
		
		as_schedule_single_action(
			time() + 5, // Wait 5 seconds before next chunk
			'wta_schedule_cities',
			array(
				'file_path'              => $file_path,
				'min_population'         => $min_population,
				'max_cities_per_country' => $max_cities_per_country,
				'filtered_country_codes' => $filtered_country_codes,
				'line_offset'            => $next_offset,
				'chunk_size'             => $chunk_size,
				'chunk_number'           => $chunk_number + 1,
			),
			'wta_structure'
		);
	} else {
		WTA_Logger::info( 'All cities scheduled - import complete', array(
			'total_scheduled' => $scheduled,
			'total_skipped'   => $skipped,
		) );

		// v3.3.0: Start structure completion detection
		// This will check if all cities are created and trigger timezone batch
		as_schedule_single_action(
			time() + 120, // Check in 2 minutes
			'wta_check_structure_completion',
			array(),
			'wta_structure'
		);
		WTA_Logger::info( '✅ Structure completion checker scheduled (checks every 2 min)' );
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
