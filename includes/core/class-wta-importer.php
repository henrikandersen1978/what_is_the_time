<?php
/**
 * Main import orchestrator.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

/**
 * Import orchestrator class.
 *
 * @since 1.0.0
 */
class WTA_Importer {

	/**
	 * Prepare import queue.
	 *
	 * @since 1.0.0
	 * @param array $options Import options.
	 * @return array|WP_Error Result with stats or error.
	 */
	public static function prepare_import( $options = array() ) {
		$defaults = array(
			'selected_continents'     => array(),
			'min_population'          => 0,
			'max_cities_per_country'  => 0,
			'clear_existing'          => false,
		);

		$options = wp_parse_args( $options, $defaults );

		WTA_Logger::info( 'Starting import preparation', $options );

		// Clear existing queue if requested
		if ( $options['clear_existing'] ) {
			WTA_Queue::clear_all();
			WTA_Logger::info( 'Cleared existing queue' );
		}

		// Fetch data from GitHub
		$countries = WTA_Github_Fetcher::fetch_countries();
		$states = WTA_Github_Fetcher::fetch_states();
		$cities = WTA_Github_Fetcher::fetch_cities();

		// Check for errors
		if ( is_wp_error( $countries ) ) {
			return $countries;
		}
		if ( is_wp_error( $states ) ) {
			return $states;
		}
		if ( is_wp_error( $cities ) ) {
			return $cities;
		}

		// Process data
		$stats = array(
			'continents' => 0,
			'countries'  => 0,
			'cities'     => 0,
		);

		// Extract continents from countries
		$continents = self::extract_continents( $countries );
		$stats['continents'] = self::queue_continents( $continents, $options );

		// Queue countries
		$stats['countries'] = self::queue_countries( $countries, $options );

		// Queue cities
		$stats['cities'] = self::queue_cities( $cities, $countries, $options );

		WTA_Logger::info( 'Import preparation complete', $stats );

		return $stats;
	}

	/**
	 * Extract unique continents from countries data.
	 *
	 * @since 1.0.0
	 * @param array $countries Countries data.
	 * @return array Unique continents.
	 */
	private static function extract_continents( $countries ) {
		$continents = array();

		foreach ( $countries as $country ) {
			$region = isset( $country['region'] ) ? $country['region'] : '';
			
			if ( empty( $region ) || isset( $continents[ $region ] ) ) {
				continue;
			}

			$continents[ $region ] = array(
				'name' => $region,
				'code' => WTA_Utils::get_continent_code( $region ),
			);
		}

		return array_values( $continents );
	}

	/**
	 * Queue continents.
	 *
	 * @since 1.0.0
	 * @param array $continents Continents data.
	 * @param array $options    Import options.
	 * @return int Number of queued items.
	 */
	private static function queue_continents( $continents, $options ) {
		$count = 0;
		$selected = $options['selected_continents'];

		foreach ( $continents as $continent ) {
			// Filter by selected continents
			if ( ! empty( $selected ) && ! in_array( $continent['code'], $selected, true ) ) {
				continue;
			}

			// Check if already queued
			$existing = WTA_Queue::get_items(
				array(
					'type'   => 'continent',
					'status' => 'done',
					'limit'  => 1,
				)
			);

			$already_queued = false;
			foreach ( $existing as $item ) {
				if ( isset( $item['payload']['code'] ) && $item['payload']['code'] === $continent['code'] ) {
					$already_queued = true;
					break;
				}
			}

			if ( $already_queued ) {
				continue;
			}

			WTA_Queue::insert( 'continent', null, $continent );
			$count++;
		}

		return $count;
	}

	/**
	 * Queue countries.
	 *
	 * @since 1.0.0
	 * @param array $countries Countries data.
	 * @param array $options   Import options.
	 * @return int Number of queued items.
	 */
	private static function queue_countries( $countries, $options ) {
		$count = 0;
		$selected = $options['selected_continents'];

		foreach ( $countries as $country ) {
			// Filter by selected continents
			if ( ! empty( $selected ) ) {
				$region = isset( $country['region'] ) ? $country['region'] : '';
				$continent_code = WTA_Utils::get_continent_code( $region );
				
				if ( ! in_array( $continent_code, $selected, true ) ) {
					continue;
				}
			}

			// Validate required fields
			if ( empty( $country['id'] ) || empty( $country['name'] ) ) {
				continue;
			}

			// Check if already queued
			$existing = WTA_Queue::get_items(
				array(
					'type'   => 'country',
					'status' => 'done',
					'limit'  => 1,
				)
			);

			$already_queued = false;
			foreach ( $existing as $item ) {
				if ( isset( $item['payload']['id'] ) && $item['payload']['id'] == $country['id'] ) {
					$already_queued = true;
					break;
				}
			}

			if ( $already_queued ) {
				continue;
			}

			WTA_Queue::insert( 'country', $country['id'], $country );
			$count++;
		}

		return $count;
	}

	/**
	 * Queue cities.
	 *
	 * @since 1.0.0
	 * @param array $cities    Cities data.
	 * @param array $countries Countries data for reference.
	 * @param array $options   Import options.
	 * @return int Number of queued items.
	 */
	private static function queue_cities( $cities, $countries, $options ) {
		$count = 0;
		$selected = $options['selected_continents'];
		$min_population = $options['min_population'];
		$max_cities = $options['max_cities_per_country'];

		// Index countries by ID for quick lookup
		$country_index = array();
		foreach ( $countries as $country ) {
			if ( ! empty( $country['id'] ) ) {
				$country_index[ $country['id'] ] = $country;
			}
		}

		// Count cities per country for limit enforcement
		$city_counts = array();

		foreach ( $cities as $city ) {
			// Validate required fields
			if ( empty( $city['id'] ) || empty( $city['name'] ) || empty( $city['country_id'] ) ) {
				continue;
			}

			$country_id = $city['country_id'];

			// Check if country exists
			if ( ! isset( $country_index[ $country_id ] ) ) {
				continue;
			}

			$country = $country_index[ $country_id ];

			// Filter by selected continents
			if ( ! empty( $selected ) ) {
				$region = isset( $country['region'] ) ? $country['region'] : '';
				$continent_code = WTA_Utils::get_continent_code( $region );
				
				if ( ! in_array( $continent_code, $selected, true ) ) {
					continue;
				}
			}

			// Filter by population
			if ( $min_population > 0 ) {
				$population = isset( $city['population'] ) ? intval( $city['population'] ) : 0;
				if ( $population < $min_population ) {
					continue;
				}
			}

			// Check max cities per country limit
			if ( $max_cities > 0 ) {
				if ( ! isset( $city_counts[ $country_id ] ) ) {
					$city_counts[ $country_id ] = 0;
				}
				
				if ( $city_counts[ $country_id ] >= $max_cities ) {
					continue;
				}
			}

			// Check if already queued
			$existing = WTA_Queue::get_items(
				array(
					'type'   => 'city',
					'status' => 'done',
					'limit'  => 1,
				)
			);

			$already_queued = false;
			foreach ( $existing as $item ) {
				if ( isset( $item['payload']['id'] ) && $item['payload']['id'] == $city['id'] ) {
					$already_queued = true;
					break;
				}
			}

			if ( $already_queued ) {
				continue;
			}

			// Add country info to city payload
			$city['country_code'] = isset( $country['iso2'] ) ? $country['iso2'] : '';
			$city['country_name'] = $country['name'];
			$city['region'] = isset( $country['region'] ) ? $country['region'] : '';

			WTA_Queue::insert( 'city', $city['id'], $city );
			$count++;

			if ( $max_cities > 0 ) {
				$city_counts[ $country_id ]++;
			}
		}

		return $count;
	}

	/**
	 * Get import progress.
	 *
	 * @since 1.0.0
	 * @return array Progress statistics.
	 */
	public static function get_progress() {
		return WTA_Queue::get_stats();
	}
}






