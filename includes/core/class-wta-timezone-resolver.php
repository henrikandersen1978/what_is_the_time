<?php
/**
 * TimeZoneDB API integration.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

/**
 * Timezone resolver using TimeZoneDB API.
 *
 * @since 1.0.0
 */
class WTA_Timezone_Resolver {

	/**
	 * TimeZoneDB API base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'http://api.timezonedb.com/v2.1/get-time-zone';

	/**
	 * Resolve timezone for coordinates.
	 *
	 * @since 1.0.0
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return string|WP_Error IANA timezone identifier or error.
	 */
	public static function resolve_timezone( $lat, $lng ) {
		$api_key = get_option( 'wta_timezonedb_api_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'TimeZoneDB API key is not configured.', WTA_TEXT_DOMAIN ) );
		}

		$lat = WTA_Utils::sanitize_latitude( $lat );
		$lng = WTA_Utils::sanitize_longitude( $lng );

		if ( $lat === null || $lng === null ) {
			return new WP_Error( 'invalid_coordinates', __( 'Invalid coordinates.', WTA_TEXT_DOMAIN ) );
		}

		// Build API URL
		$url = add_query_arg(
			array(
				'key'    => $api_key,
				'format' => 'json',
				'by'     => 'position',
				'lat'    => $lat,
				'lng'    => $lng,
			),
			self::API_BASE_URL
		);

		WTA_Logger::debug( 'Calling TimeZoneDB API', array( 'lat' => $lat, 'lng' => $lng ) );

		// Make API request
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			WTA_Logger::error( 'TimeZoneDB API request failed', array(
				'error' => $response->get_error_message(),
				'lat'   => $lat,
				'lng'   => $lng,
			) );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$error_message = sprintf(
				/* translators: %d: HTTP response code */
				__( 'TimeZoneDB API returned HTTP status: %d', WTA_TEXT_DOMAIN ),
				$response_code
			);
			WTA_Logger::error( $error_message );
			return new WP_Error( 'http_error', $error_message );
		}

		// Parse response
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = sprintf(
				/* translators: %s: JSON error message */
				__( 'Failed to parse TimeZoneDB response: %s', WTA_TEXT_DOMAIN ),
				json_last_error_msg()
			);
			WTA_Logger::error( $error_message );
			return new WP_Error( 'json_error', $error_message );
		}

		// Check API response status
		if ( empty( $data['status'] ) || $data['status'] !== 'OK' ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown API error', WTA_TEXT_DOMAIN );
			WTA_Logger::error( 'TimeZoneDB API error', array( 'message' => $error_message ) );
			return new WP_Error( 'api_error', $error_message );
		}

		// Extract timezone
		if ( empty( $data['zoneName'] ) ) {
			$error_message = __( 'Timezone not found in API response.', WTA_TEXT_DOMAIN );
			WTA_Logger::error( $error_message );
			return new WP_Error( 'missing_timezone', $error_message );
		}

		$timezone = $data['zoneName'];

		// Validate timezone
		if ( ! WTA_Timezone_Helper::is_valid_timezone( $timezone ) ) {
			$error_message = sprintf(
				/* translators: %s: timezone identifier */
				__( 'Invalid timezone returned: %s', WTA_TEXT_DOMAIN ),
				$timezone
			);
			WTA_Logger::error( $error_message );
			return new WP_Error( 'invalid_timezone', $error_message );
		}

		WTA_Logger::info( 'Timezone resolved', array(
			'lat'      => $lat,
			'lng'      => $lng,
			'timezone' => $timezone,
		) );

		return $timezone;
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function test_api() {
		// Test with Copenhagen coordinates
		$result = self::resolve_timezone( 55.6761, 12.5683 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $result === 'Europe/Copenhagen' ) {
			return true;
		}

		return new WP_Error( 'unexpected_result', sprintf(
			/* translators: %s: timezone identifier */
			__( 'Unexpected timezone result: %s', WTA_TEXT_DOMAIN ),
			$result
		) );
	}

	/**
	 * Rate limiting sleep.
	 * TimeZoneDB free tier allows 1 request per second.
	 *
	 * @since 1.0.0
	 */
	public static function rate_limit_sleep() {
		// Sleep for 250-300ms to ensure we don't exceed 1 req/sec
		usleep( mt_rand( 250000, 300000 ) );
	}
}





