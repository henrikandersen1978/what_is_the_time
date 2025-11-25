<?php
/**
 * Fetch JSON data from GitHub repository.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

/**
 * GitHub data fetcher.
 *
 * @since 1.0.0
 */
class WTA_Github_Fetcher {

	/**
	 * Fetch countries data.
	 * 
	 * Checks for local file first for better performance.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Countries data or error.
	 */
	public static function fetch_countries() {
		$local_file = WP_CONTENT_DIR . '/plugins/world-time-ai/json/countries.json';
		if ( file_exists( $local_file ) ) {
			WTA_Logger::info( 'Using local countries.json file' );
			return self::parse_large_json_file( $local_file, 'countries' );
		}
		
		$url = get_option( 'wta_github_countries_url' );
		if ( empty( $url ) ) {
			return new WP_Error( 
				'missing_data_source', 
				__( 'No data source configured. Please either place countries.json in /wp-content/plugins/world-time-ai/json/ or configure GitHub URL in Data & Import settings.', WTA_TEXT_DOMAIN )
			);
		}
		
		return self::fetch_json( $url, 'countries' );
	}

	/**
	 * Fetch states data.
	 * 
	 * Checks for local file first for better performance.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error States data or error.
	 */
	public static function fetch_states() {
		$local_file = WP_CONTENT_DIR . '/plugins/world-time-ai/json/states.json';
		if ( file_exists( $local_file ) ) {
			WTA_Logger::info( 'Using local states.json file' );
			return self::parse_large_json_file( $local_file, 'states' );
		}
		
		$url = get_option( 'wta_github_states_url' );
		if ( empty( $url ) ) {
			return new WP_Error( 
				'missing_data_source', 
				__( 'No data source configured. Please either place states.json in /wp-content/plugins/world-time-ai/json/ or configure GitHub URL in Data & Import settings.', WTA_TEXT_DOMAIN )
			);
		}
		
		return self::fetch_json( $url, 'states' );
	}

	/**
	 * Fetch cities data.
	 * 
	 * Uses streaming parser for large files to avoid memory issues.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Cities data or error.
	 */
	public static function fetch_cities() {
		// First check for local JSON file to avoid memory issues with huge GitHub downloads
		$local_file = WP_CONTENT_DIR . '/plugins/world-time-ai/json/cities.json';
		if ( file_exists( $local_file ) ) {
			WTA_Logger::info( 'Using local cities.json file' );
			return self::parse_large_json_file( $local_file, 'cities' );
		}
		
		$url = get_option( 'wta_github_cities_url' );
		if ( empty( $url ) ) {
			return new WP_Error( 
				'missing_data_source', 
				__( 'No data source configured. Please either place cities.json in /wp-content/plugins/world-time-ai/json/ or configure GitHub URL in Data & Import settings.', WTA_TEXT_DOMAIN )
			);
		}
		
		return self::fetch_json( $url, 'cities' );
	}

	/**
	 * Fetch and parse JSON from URL.
	 *
	 * @since 1.0.0
	 * @param string $url  URL to fetch.
	 * @param string $type Data type for caching.
	 * @return array|WP_Error Parsed data or error.
	 */
	private static function fetch_json( $url, $type ) {
		if ( empty( $url ) ) {
			return new WP_Error( 'missing_url', __( 'URL is not configured.', WTA_TEXT_DOMAIN ) );
		}

		// Check cache first
		$transient_key = 'wta_github_' . $type;
		$cached_data = get_transient( $transient_key );
		
		if ( $cached_data !== false ) {
			WTA_Logger::debug( "Using cached data for {$type}" );
			return $cached_data;
		}

		WTA_Logger::info( "Fetching {$type} from GitHub", array( 'url' => $url ) );

		// Fetch data using WordPress HTTP API
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			WTA_Logger::error( "Failed to fetch {$type}", array(
				'error' => $response->get_error_message(),
			) );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$error_message = sprintf(
				/* translators: %1$s: data type, %2$d: response code */
				__( 'Failed to fetch %1$s. HTTP status: %2$d', WTA_TEXT_DOMAIN ),
				$type,
				$response_code
			);
			WTA_Logger::error( $error_message );
			return new WP_Error( 'http_error', $error_message );
		}

		// Get body and parse JSON
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = sprintf(
				/* translators: %1$s: data type, %2$s: JSON error */
				__( 'Failed to parse %1$s JSON: %2$s', WTA_TEXT_DOMAIN ),
				$type,
				json_last_error_msg()
			);
			WTA_Logger::error( $error_message );
			return new WP_Error( 'json_error', $error_message );
		}

		if ( ! is_array( $data ) ) {
			$error_message = sprintf(
				/* translators: %s: data type */
				__( 'Invalid %s data format.', WTA_TEXT_DOMAIN ),
				$type
			);
			WTA_Logger::error( $error_message );
			return new WP_Error( 'invalid_data', $error_message );
		}

		WTA_Logger::info( "Successfully fetched {$type}", array( 'count' => count( $data ) ) );

		// Cache for 1 hour
		set_transient( $transient_key, $data, HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Parse large JSON file in chunks to avoid memory issues.
	 * 
	 * For files > 50MB, this reads and parses in batches.
	 *
	 * @since 1.0.0
	 * @param string $file_path Path to JSON file.
	 * @param string $type Data type for caching.
	 * @return array|WP_Error Parsed data or error.
	 */
	private static function parse_large_json_file( $file_path, $type ) {
		// Check cache first
		$transient_key = 'wta_local_' . $type;
		$cached_data = get_transient( $transient_key );
		
		if ( $cached_data !== false ) {
			WTA_Logger::debug( "Using cached local data for {$type}" );
			return $cached_data;
		}

		$file_size = filesize( $file_path );
		$file_size_mb = round( $file_size / 1024 / 1024, 2 );
		
		WTA_Logger::info( "Parsing local {$type} file", array( 
			'size' => $file_size_mb . ' MB',
			'path' => $file_path 
		) );

		// For files over 50MB, use chunked parsing
		if ( $file_size > 50 * 1024 * 1024 ) {
			return self::parse_json_chunked( $file_path, $type, $transient_key );
		}

		// For smaller files, use standard parsing
		$json_content = file_get_contents( $file_path );
		if ( $json_content === false ) {
			return new WP_Error( 'file_read_error', __( 'Could not read JSON file.', WTA_TEXT_DOMAIN ) );
		}

		$data = json_decode( $json_content, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = sprintf(
				__( 'Failed to parse %s JSON: %s', WTA_TEXT_DOMAIN ),
				$type,
				json_last_error_msg()
			);
			WTA_Logger::error( $error_message );
			return new WP_Error( 'json_error', $error_message );
		}

		// Cache for 1 day (local files don't change often)
		set_transient( $transient_key, $data, DAY_IN_SECONDS );
		
		WTA_Logger::info( "Successfully parsed local {$type}", array( 'count' => count( $data ) ) );
		
		return $data;
	}

	/**
	 * Parse large JSON file in chunks using streaming approach.
	 * 
	 * This reads the file line by line and parses JSON objects individually
	 * to avoid loading the entire file into memory.
	 *
	 * @since 1.0.0
	 * @param string $file_path Path to JSON file.
	 * @param string $type Data type for logging.
	 * @param string $transient_key Cache key.
	 * @return array|WP_Error Parsed data or error.
	 */
	private static function parse_json_chunked( $file_path, $type, $transient_key ) {
		$data = array();
		$handle = fopen( $file_path, 'r' );
		
		if ( ! $handle ) {
			return new WP_Error( 'file_open_error', __( 'Could not open JSON file.', WTA_TEXT_DOMAIN ) );
		}

		$buffer = '';
		$in_array = false;
		$bracket_count = 0;
		$object_buffer = '';
		$object_count = 0;
		$chunk_size = 8192; // 8KB chunks

		WTA_Logger::info( "Starting chunked parsing of {$type}" );

		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, $chunk_size );
			$buffer .= $chunk;

			// Process character by character
			$buffer_len = strlen( $buffer );
			$processed = 0;

			for ( $i = 0; $i < $buffer_len; $i++ ) {
				$char = $buffer[ $i ];

				// Track if we're inside the main array
				if ( $char === '[' && $bracket_count === 0 ) {
					$in_array = true;
					$processed = $i + 1;
					continue;
				}

				if ( ! $in_array ) {
					continue;
				}

				// Track nested brackets in objects
				if ( $char === '{' ) {
					$bracket_count++;
				} elseif ( $char === '}' ) {
					$bracket_count--;
				}

				$object_buffer .= $char;

				// When we close an object at root level
				if ( $bracket_count === 0 && $char === '}' ) {
					// Parse this single object
					$obj = json_decode( $object_buffer, true );
					
					if ( $obj !== null ) {
						$data[] = $obj;
						$object_count++;
						
						// Log progress every 10000 objects
						if ( $object_count % 10000 === 0 ) {
							WTA_Logger::debug( "{$type}: Parsed {$object_count} objects" );
							
							// Prevent timeout on long operations
							if ( function_exists( 'set_time_limit' ) ) {
								set_time_limit( 300 );
							}
						}
					}
					
					$object_buffer = '';
					$processed = $i + 1;
				}
			}

			// Keep unprocessed part in buffer
			$buffer = substr( $buffer, $processed );

			// Clear output buffer to prevent memory buildup
			if ( ob_get_level() > 0 ) {
				ob_flush();
			}
		}

		fclose( $handle );

		WTA_Logger::info( "Completed chunked parsing of {$type}", array( 'total_objects' => $object_count ) );

		// Cache for 1 day
		set_transient( $transient_key, $data, DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Clear all GitHub data cache.
	 *
	 * @since 1.0.0
	 */
	public static function clear_cache() {
		delete_transient( 'wta_github_countries' );
		delete_transient( 'wta_github_states' );
		delete_transient( 'wta_github_cities' );
		delete_transient( 'wta_local_countries' );
		delete_transient( 'wta_local_states' );
		delete_transient( 'wta_local_cities' );
		WTA_Logger::info( 'GitHub data cache cleared' );
	}
}





