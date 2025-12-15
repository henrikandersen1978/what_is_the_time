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
	 * Prepare import queue.
	 *
	 * @since    2.0.0
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

		// Clear existing queue if requested
		if ( $options['clear_queue'] ) {
			WTA_Queue::clear();
			WTA_Logger::info( 'Queue cleared before import' );
		}

		$stats = array(
			'continents' => 0,
			'countries'  => 0,
			'cities'     => 0,
		);

		// Fetch countries data
		$countries = WTA_Github_Fetcher::fetch_countries();
		if ( false === $countries ) {
			WTA_Logger::error( 'Failed to fetch countries data' );
			return $stats;
		}

		// Queue continents and filter countries
		$continents_queued = array();
		$filtered_countries = array();

		foreach ( $countries as $country ) {
			// Determine continent from subregion (for Americas) or region (for others)
			if ( isset( $country['subregion'] ) && ! empty( $country['subregion'] ) ) {
				$subregion = $country['subregion'];
				
				// Map Americas subregions to North/South America
				if ( in_array( $subregion, array( 'Northern America', 'Central America', 'Caribbean' ), true ) ) {
					$continent = 'North America';
				} elseif ( $subregion === 'South America' ) {
					$continent = 'South America';
				} else {
					// For other continents (Europe, Asia, Africa, Oceania), use main region
					$continent = isset( $country['region'] ) ? $country['region'] : 'Unknown';
				}
			} else {
				$continent = isset( $country['region'] ) ? $country['region'] : 'Unknown';
			}
			
			// Handle special case: Polar -> Antarctica
			if ( $continent === 'Polar' ) {
				$continent = 'Antarctica';
			}
			
			$continent_code = WTA_Utils::get_continent_code( $continent );

			// Filter based on import mode
			if ( $options['import_mode'] === 'countries' ) {
				// Filter by selected countries (by ISO2 code)
				if ( ! empty( $options['selected_countries'] ) && ! in_array( $country['iso2'], $options['selected_countries'], true ) ) {
					continue;
				}
			} else {
			// Filter by selected continents (by code)
			if ( ! empty( $options['selected_continents'] ) && ! in_array( $continent_code, $options['selected_continents'], true ) ) {
				continue;
				}
			}

			// Queue continent (deduplicated)
			if ( ! in_array( $continent, $continents_queued, true ) ) {
WTA_Queue::add(
					'continent',
					array(
						'name'     => $continent,
						'name_local' => WTA_AI_Translator::translate( $continent, 'continent' ),
					),
					'continent_' . sanitize_title( $continent )
				);
				$continents_queued[] = $continent;
				$stats['continents']++;
			}

			// Add to filtered countries
			$filtered_countries[] = $country;
		}

		WTA_Logger::info( 'Continents queued', array( 'count' => $stats['continents'] ) );

		// Queue countries
		foreach ( $filtered_countries as $country ) {
			// Use Wikidata for translation if available
			$wikidata_id = isset( $country['wikiDataId'] ) ? $country['wikiDataId'] : null;
			$name_local = WTA_AI_Translator::translate( 
				$country['name'], 
				'country', 
				null, 
				$wikidata_id 
			);
			
			// Calculate continent for this country (reuse logic from above)
			if ( isset( $country['subregion'] ) && ! empty( $country['subregion'] ) ) {
				$subregion = $country['subregion'];
				if ( in_array( $subregion, array( 'Northern America', 'Central America', 'Caribbean' ), true ) ) {
					$country_continent = 'North America';
				} elseif ( $subregion === 'South America' ) {
					$country_continent = 'South America';
				} else {
					$country_continent = isset( $country['region'] ) ? $country['region'] : 'Unknown';
				}
			} else {
				$country_continent = isset( $country['region'] ) ? $country['region'] : 'Unknown';
			}
			
			if ( $country_continent === 'Polar' ) {
				$country_continent = 'Antarctica';
			}
			
			WTA_Queue::add(
				'country',
				array(
					'name'         => $country['name'],
					'name_local'   => $name_local,
					'country_code' => $country['iso2'],
					'country_id'   => $country['id'],
					'continent'    => $country_continent,
					'latitude'     => isset( $country['latitude'] ) ? $country['latitude'] : null,
					'longitude'    => isset( $country['longitude'] ) ? $country['longitude'] : null,
					'wikidata_id'  => $wikidata_id,
				),
				'country_' . $country['id']
			);
			$stats['countries']++;
		}

		WTA_Logger::info( 'Countries queued', array( 'count' => $stats['countries'] ) );

		// Queue cities batch job (ONE item)
		// This will be processed by Action Scheduler which will stream-parse cities.json
		$cities_file = WTA_Github_Fetcher::get_cities_file_path();
		if ( false === $cities_file ) {
			WTA_Logger::error( 'cities.json not found' );
			return $stats;
		}

		// CRITICAL: Use country_code (iso2) instead of id for matching cities
		$filtered_country_codes = array_column( $filtered_countries, 'iso2' );

		WTA_Queue::add(
			'cities_import',
			array(
				'file_path'       => $cities_file,
				'min_population'  => $options['min_population'],
				'max_cities_per_country' => $options['max_cities_per_country'],
				'selected_continents' => $options['selected_continents'],
				'filtered_country_codes' => $filtered_country_codes,
			),
			'cities_import_' . time()
		);

		$stats['cities'] = 1; // This is the batch job count, not actual cities

		WTA_Logger::info( 'Cities import batch job queued', $options );

		return $stats;
	}

	/**
	 * Queue cities from array.
	 *
	 * Called by Action Scheduler processor to queue individual cities.
	 * CRITICAL: Correct population filter - include cities with null population!
	 *
	 * @since    2.0.0
	 * @param    array $cities  Cities data.
	 * @param    array $options Import options.
	 * @return   int            Number of cities queued.
	 */
	public static function queue_cities_from_array( $cities, $options = array() ) {
		$min_population = isset( $options['min_population'] ) ? (int) $options['min_population'] : 0;
		$max_cities_per_country = isset( $options['max_cities_per_country'] ) ? (int) $options['max_cities_per_country'] : 0;
		$filtered_country_codes = isset( $options['filtered_country_codes'] ) ? $options['filtered_country_codes'] : array();

		$queued = 0;
		$per_country = array();

		foreach ( $cities as $city ) {
			// Filter by country_code (iso2)
			if ( ! empty( $filtered_country_codes ) && ! in_array( $city['country_code'], $filtered_country_codes, true ) ) {
				continue;
			}

			// CRITICAL: Population filter logic
			// Only filter if:
			// 1. min_population > 0 (filter is active)
			// 2. population is explicitly set (not null)
			// 3. population is less than min_population
			if ( $min_population > 0 ) {
				if ( isset( $city['population'] ) && null !== $city['population'] ) {
					$population = (int) $city['population'];
					if ( $population > 0 && $population < $min_population ) {
						continue; // Skip this city
					}
				}
				// If population is null or not set, INCLUDE the city
			}

			// Max cities per country
			if ( $max_cities_per_country > 0 ) {
				$country_id = $city['country_id'];
				if ( ! isset( $per_country[ $country_id ] ) ) {
					$per_country[ $country_id ] = 0;
				}

				if ( $per_country[ $country_id ] >= $max_cities_per_country ) {
					continue;
				}

				$per_country[ $country_id ]++;
			}

		// Queue city with Wikidata support
		$wikidata_id = isset( $city['wikiDataId'] ) ? $city['wikiDataId'] : null;
		$name_local = WTA_AI_Translator::translate( 
			$city['name'], 
			'city', 
			null, 
			$wikidata_id 
		);
		
			$city_payload = array(
				'name'         => $city['name'],
			'name_local'   => $name_local,
				'city_id'      => $city['id'],
				'country_id'   => $city['country_id'],
				'state_id'     => isset( $city['state_id'] ) ? $city['state_id'] : null,
				'latitude'     => isset( $city['latitude'] ) ? $city['latitude'] : null,
				'longitude'    => isset( $city['longitude'] ) ? $city['longitude'] : null,
				'population'   => isset( $city['population'] ) ? $city['population'] : null,
			'wikidata_id'  => $wikidata_id,
			);

			WTA_Queue::add( 'city', $city_payload, 'city_' . $city['id'] );
			$queued++;

			// Prevent timeout
			if ( $queued % 100 === 0 ) {
				set_time_limit( 30 );
			}
		}

		return $queued;
	}
}
