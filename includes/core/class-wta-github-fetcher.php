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
	 * @since 1.0.0
	 * @return array|WP_Error Countries data or error.
	 */
	public static function fetch_countries() {
		$url = get_option( 'wta_github_countries_url' );
		return self::fetch_json( $url, 'countries' );
	}

	/**
	 * Fetch states data.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error States data or error.
	 */
	public static function fetch_states() {
		$url = get_option( 'wta_github_states_url' );
		return self::fetch_json( $url, 'states' );
	}

	/**
	 * Fetch cities data.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Cities data or error.
	 */
	public static function fetch_cities() {
		$url = get_option( 'wta_github_cities_url' );
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
	 * Clear all GitHub data cache.
	 *
	 * @since 1.0.0
	 */
	public static function clear_cache() {
		delete_transient( 'wta_github_countries' );
		delete_transient( 'wta_github_states' );
		delete_transient( 'wta_github_cities' );
		WTA_Logger::info( 'GitHub data cache cleared' );
	}
}





