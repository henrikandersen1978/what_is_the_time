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
			as_unschedule_all_actions( 'wta_lookup_timezone' );
			as_unschedule_all_actions( 'wta_generate_ai_content' );
			WTA_Logger::info( 'All pending actions cleared before import' );
		}

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
		as_schedule_single_action(
			time() + 15, // Wait 15 seconds for continents/countries to start
			'wta_schedule_cities',
			array(
				'file_path'              => $cities_file,
				'min_population'         => $options['min_population'],
				'max_cities_per_country' => $options['max_cities_per_country'],
				'filtered_country_codes' => $filtered_country_codes,
			),
			'wta_structure'
		);

		$stats['cities'] = 1; // This is the scheduler job count

		WTA_Logger::info( 'Cities scheduler job scheduled' );

		return $stats;
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
	$current_line = 0;

	while ( ( $line = fgets( $file ) ) !== false ) {
		$current_line++;
		
		// Skip lines until we reach our offset
		if ( $current_line <= $line_offset ) {
			continue;
		}
		
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

			// Schedule city creation
			as_schedule_single_action(
				time() + $delay,
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
				'current_line' => $current_line,
			) );
			break;
		}
	}

	$reached_eof = feof( $file );
	fclose( $file );

	WTA_Logger::info( 'Cities scheduling chunk complete', array(
		'scheduled' => $scheduled,
		'skipped'   => $skipped,
		'next_offset' => $current_line,
		'reached_eof' => $reached_eof,
	) );
	
	// v3.0.69: If more cities remain, schedule next chunk
	if ( ! $reached_eof && $scheduled >= $chunk_size ) {
		WTA_Logger::info( 'Scheduling next chunk', array(
			'next_offset' => $current_line,
		) );
		
		as_schedule_single_action(
			time() + 5, // Wait 5 seconds before next chunk
			'wta_schedule_cities',
			array(
				'file_path'              => $file_path,
				'min_population'         => $min_population,
				'max_cities_per_country' => $max_cities_per_country,
				'filtered_country_codes' => $filtered_country_codes,
				'line_offset'            => $current_line,
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
