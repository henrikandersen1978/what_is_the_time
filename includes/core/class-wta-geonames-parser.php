<?php
/**
 * GeoNames data parser.
 *
 * Parses GeoNames data files (countryInfo.txt, cities500.txt) to replace JSON-based import.
 * Provides memory-efficient streaming for large files.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 * @since      3.0.0
 */

class WTA_GeoNames_Parser {

	/**
	 * Get GeoNames data directory.
	 *
	 * @since    3.0.0
	 * @return   string Data directory path.
	 */
	public static function get_data_directory() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/world-time-ai-data';
	}

	/**
	 * Get path to cities500.txt file.
	 *
	 * @since    3.0.0
	 * @return   string|false File path or false if not found.
	 */
	public static function get_cities_file_path() {
		$file_path = self::get_data_directory() . '/cities500.txt';
		
		if ( ! file_exists( $file_path ) ) {
			WTA_Logger::error( 'cities500.txt not found', array( 'path' => $file_path ) );
			return false;
		}
		
		return $file_path;
	}

	/**
	 * Parse countryInfo.txt to get country data.
	 *
	 * GeoNames countryInfo.txt format:
	 * ISO  ISO3  ISO-Numeric  fips  Country  Capital  Area(km²)  Population  Continent  tld  CurrencyCode  CurrencyName  Phone  Postal Code Format  Postal Code Regex  Languages  geonameid  neighbours  EquivalentFipsCode
	 *
	 * @since    3.0.0
	 * @return   array|false Array of countries or false on failure.
	 */
	public static function parse_countryInfo() {
		$file_path = self::get_data_directory() . '/countryInfo.txt';
		
		if ( ! file_exists( $file_path ) ) {
			WTA_Logger::error( 'countryInfo.txt not found', array( 'path' => $file_path ) );
			return false;
		}

		$countries = array();
		$file = fopen( $file_path, 'r' );

		if ( ! $file ) {
			WTA_Logger::error( 'Failed to open countryInfo.txt' );
			return false;
		}

		$line_count = 0;
		while ( ( $line = fgets( $file ) ) !== false ) {
			$line_count++;
			
			// Skip comments and empty lines
			if ( strpos( $line, '#' ) === 0 || trim( $line ) === '' ) {
				continue;
			}

			// Parse tab-separated values
			$parts = explode( "\t", trim( $line ) );

			// Validate minimum required fields (geonameid is column 16, 0-indexed)
			// Note: Many countries have empty neighbours/EquivalentFipsCode columns at the end
			if ( count( $parts ) < 17 ) {
				WTA_Logger::debug( 'Skipping invalid line in countryInfo.txt', array( 'line' => $line_count, 'columns' => count( $parts ) ) );
				continue;
			}

			// Extract fields
			$iso2 = $parts[0];           // ISO 2-letter code
			$iso3 = $parts[1];           // ISO 3-letter code
			$name = $parts[4];           // Country name (English)
			$capital = $parts[5];        // Capital city
			$area = $parts[6];           // Area in km²
			$population = $parts[7];     // Population
			$continent = $parts[8];      // Continent code (EU, AS, AF, etc.)
			$languages = $parts[15];     // Language codes (comma-separated)
			$geonameid = $parts[16];     // GeoNames ID
			
			// Map continent codes to full names
			$continent_map = array(
				'AF' => 'Africa',
				'AS' => 'Asia',
				'EU' => 'Europe',
				'NA' => 'North America',
				'OC' => 'Oceania',
				'SA' => 'South America',
				'AN' => 'Antarctica',
			);
			
			$continent_name = isset( $continent_map[ $continent ] ) ? $continent_map[ $continent ] : 'Unknown';

			$countries[] = array(
				'name'          => $name,
				'iso2'          => $iso2,
				'iso3'          => $iso3,
				'geonameid'     => intval( $geonameid ),
				'capital'       => $capital,
				'area'          => floatval( $area ),
				'population'    => intval( $population ),
				'continent'     => $continent_name,
				'continent_code' => $continent,
				'languages'     => $languages,
			);
		}

		fclose( $file );

		WTA_Logger::info( 'Parsed countryInfo.txt', array(
			'countries' => count( $countries ),
			'lines'     => $line_count,
		) );

		return $countries;
	}

	/**
	 * Stream-parse cities500.txt and queue individual cities.
	 *
	 * Memory-efficient streaming parser for large files (37 MB).
	 * Processes cities in batches to avoid memory issues.
	 *
	 * GeoNames cities500.txt format (tab-separated):
	 * geonameid  name  asciiname  alternatenames  latitude  longitude  feature_class  feature_code  country_code  cc2  admin1_code  admin2_code  admin3_code  admin4_code  population  elevation  dem  timezone  modification_date
	 *
	 * @since    3.0.0
	 * @param    array $options Import options.
	 * @return   int            Number of cities queued.
	 */
	public static function stream_parse_cities( $options = array() ) {
		$file_path = self::get_cities_file_path();
		
		if ( false === $file_path ) {
			return 0;
		}

		$min_population = isset( $options['min_population'] ) ? intval( $options['min_population'] ) : 0;
		$max_cities_per_country = isset( $options['max_cities_per_country'] ) ? intval( $options['max_cities_per_country'] ) : 0;
		$filtered_country_codes = isset( $options['filtered_country_codes'] ) ? $options['filtered_country_codes'] : array();

		$file = fopen( $file_path, 'r' );
		if ( ! $file ) {
			WTA_Logger::error( 'Failed to open cities500.txt' );
			return 0;
		}

		$queued = 0;
		$per_country = array();
		$line_count = 0;

		while ( ( $line = fgets( $file ) ) !== false ) {
			$line_count++;

			// Parse tab-separated values
			$parts = explode( "\t", trim( $line ) );

			// Validate minimum required fields
			if ( count( $parts ) < 19 ) {
				continue;
			}

			// Extract fields
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

			// Filter by country
			if ( ! empty( $filtered_country_codes ) && ! in_array( $country_code, $filtered_country_codes, true ) ) {
				continue;
			}

			// Only include populated places (P class)
			if ( $feature_class !== 'P' ) {
				continue;
			}

			// Population filter (same logic as old system)
			if ( $min_population > 0 ) {
				$pop = intval( $population );
				if ( $pop > 0 && $pop < $min_population ) {
					continue;
				}
			}

			// Max cities per country
			if ( $max_cities_per_country > 0 ) {
				if ( ! isset( $per_country[ $country_code ] ) ) {
					$per_country[ $country_code ] = 0;
				}

				if ( $per_country[ $country_code ] >= $max_cities_per_country ) {
					continue;
				}

				$per_country[ $country_code ]++;
			}

			// Queue city for import
			$city_data = array(
				'name'         => $name,
				'name_ascii'   => $asciiname,
				'geonameid'    => intval( $geonameid ),
				'country_code' => $country_code,
				'latitude'     => floatval( $latitude ),
				'longitude'    => floatval( $longitude ),
				'population'   => intval( $population ),
				'timezone'     => $timezone,
				'feature_code' => $feature_code,
			);

			// Queue will be handled by importer
			// For now, just return the data structure
			$queued++;

			// Prevent timeout
			if ( $queued % 1000 === 0 ) {
				set_time_limit( 30 );
			}
		}

		fclose( $file );

		WTA_Logger::info( 'Parsed cities500.txt', array(
			'total_lines' => $line_count,
			'queued'      => $queued,
		) );

		return $queued;
	}

	/**
	 * Get cities as array (for batch processing).
	 *
	 * Similar to stream_parse_cities but returns array instead of queuing.
	 * Used by importer to process cities in batches.
	 *
	 * @since    3.0.0
	 * @param    array $options Import options.
	 * @return   array          Array of city data.
	 */
	public static function get_cities_array( $options = array() ) {
		$file_path = self::get_cities_file_path();
		
		if ( false === $file_path ) {
			return array();
		}

		$min_population = isset( $options['min_population'] ) ? intval( $options['min_population'] ) : 0;
		$max_cities_per_country = isset( $options['max_cities_per_country'] ) ? intval( $options['max_cities_per_country'] ) : 0;
		$filtered_country_codes = isset( $options['filtered_country_codes'] ) ? $options['filtered_country_codes'] : array();

		$file = fopen( $file_path, 'r' );
		if ( ! $file ) {
			return array();
		}

		$cities = array();
		$per_country = array();

		while ( ( $line = fgets( $file ) ) !== false ) {
			$parts = explode( "\t", trim( $line ) );

			if ( count( $parts ) < 19 ) {
				continue;
			}

			$geonameid = $parts[0];
			$name = $parts[1];
			$asciiname = $parts[2];
			$latitude = $parts[4];
			$longitude = $parts[5];
			$feature_class = $parts[6];
			$feature_code = $parts[7];
			$country_code = $parts[8];
			$population = $parts[14];
			$timezone = $parts[17];

			// Filter by country
			if ( ! empty( $filtered_country_codes ) && ! in_array( $country_code, $filtered_country_codes, true ) ) {
				continue;
			}

			// Only populated places
			if ( $feature_class !== 'P' ) {
				continue;
			}

			// Population filter
			if ( $min_population > 0 ) {
				$pop = intval( $population );
				if ( $pop > 0 && $pop < $min_population ) {
					continue;
				}
			}

			// Max cities per country
			if ( $max_cities_per_country > 0 ) {
				if ( ! isset( $per_country[ $country_code ] ) ) {
					$per_country[ $country_code ] = 0;
				}

				if ( $per_country[ $country_code ] >= $max_cities_per_country ) {
					continue;
				}

				$per_country[ $country_code ]++;
			}

			$cities[] = array(
				'name'         => $name,
				'name_ascii'   => $asciiname,
				'geonameid'    => intval( $geonameid ),
				'country_code' => $country_code,
				'latitude'     => floatval( $latitude ),
				'longitude'    => floatval( $longitude ),
				'population'   => intval( $population ),
				'timezone'     => $timezone,
				'feature_code' => $feature_code,
			);
		}

		fclose( $file );

		return $cities;
	}
}

