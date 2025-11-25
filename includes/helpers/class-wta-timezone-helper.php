<?php
/**
 * Timezone helper functions.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

class WTA_Timezone_Helper {

	/**
	 * Country timezone mapping.
	 *
	 * Simple countries with single timezone.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      array    $country_timezones    Country timezone map.
	 */
	private static $country_timezones = array(
		'DK' => 'Europe/Copenhagen',
		'SE' => 'Europe/Stockholm',
		'NO' => 'Europe/Oslo',
		'FI' => 'Europe/Helsinki',
		'DE' => 'Europe/Berlin',
		'FR' => 'Europe/Paris',
		'ES' => 'Europe/Madrid',
		'IT' => 'Europe/Rome',
		'GB' => 'Europe/London',
		'IE' => 'Europe/Dublin',
		'NL' => 'Europe/Amsterdam',
		'BE' => 'Europe/Brussels',
		'CH' => 'Europe/Zurich',
		'AT' => 'Europe/Vienna',
		'PL' => 'Europe/Warsaw',
		'CZ' => 'Europe/Prague',
		'GR' => 'Europe/Athens',
		'PT' => 'Europe/Lisbon',
		'HU' => 'Europe/Budapest',
		'RO' => 'Europe/Bucharest',
		'HR' => 'Europe/Zagreb',
		'IS' => 'Atlantic/Reykjavik',
		'JP' => 'Asia/Tokyo',
		'KR' => 'Asia/Seoul',
		'SG' => 'Asia/Singapore',
		'TH' => 'Asia/Bangkok',
		'VN' => 'Asia/Ho_Chi_Minh',
		'MY' => 'Asia/Kuala_Lumpur',
		'PH' => 'Asia/Manila',
		'IL' => 'Asia/Jerusalem',
		'TR' => 'Europe/Istanbul',
		'EG' => 'Africa/Cairo',
		'ZA' => 'Africa/Johannesburg',
		'KE' => 'Africa/Nairobi',
		'NG' => 'Africa/Lagos',
		'MA' => 'Africa/Casablanca',
		'NZ' => 'Pacific/Auckland',
		'CL' => 'America/Santiago',
		'AR' => 'America/Argentina/Buenos_Aires',
		'CO' => 'America/Bogota',
		'PE' => 'America/Lima',
		'VE' => 'America/Caracas',
	);

	/**
	 * Check if country has multiple timezones.
	 *
	 * @since    2.0.0
	 * @param    string $country_code ISO2 country code.
	 * @return   bool                 True if complex.
	 */
	public static function is_complex_country( $country_code ) {
		$complex_countries = get_option( 'wta_complex_countries', 'US,CA,BR,RU,AU,MX,ID,CN,KZ,AR,GL,CD,SA' );
		$complex_countries = explode( ',', $complex_countries );
		$complex_countries = array_map( 'trim', $complex_countries );

		return in_array( $country_code, $complex_countries, true );
	}

	/**
	 * Get timezone for country.
	 *
	 * For simple countries only.
	 *
	 * @since    2.0.0
	 * @param    string $country_code ISO2 country code.
	 * @return   string|null          IANA timezone or null if complex.
	 */
	public static function get_country_timezone( $country_code ) {
		if ( self::is_complex_country( $country_code ) ) {
			return null;
		}

		return isset( self::$country_timezones[ $country_code ] ) 
			? self::$country_timezones[ $country_code ] 
			: null;
	}

	/**
	 * Resolve timezone using TimeZoneDB API.
	 *
	 * @since    2.0.0
	 * @param    float $lat Latitude.
	 * @param    float $lng Longitude.
	 * @return   string|false IANA timezone or false on failure.
	 */
	public static function resolve_timezone_api( $lat, $lng ) {
		$api_key = get_option( 'wta_timezonedb_api_key', '' );

		if ( empty( $api_key ) ) {
			WTA_Logger::error( 'TimeZoneDB API key not configured' );
			return false;
		}

		$url = sprintf(
			'http://api.timezonedb.com/v2.1/get-time-zone?key=%s&format=json&by=position&lat=%s&lng=%s',
			$api_key,
			$lat,
			$lng
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			WTA_Logger::error( 'TimeZoneDB API request failed', array(
				'error' => $response->get_error_message(),
				'lat'   => $lat,
				'lng'   => $lng,
			) );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['status'] ) || 'OK' !== $data['status'] ) {
			WTA_Logger::error( 'TimeZoneDB API returned error', array(
				'data' => $data,
				'lat'  => $lat,
				'lng'  => $lng,
			) );
			return false;
		}

		if ( ! isset( $data['zoneName'] ) ) {
			return false;
		}

		return $data['zoneName'];
	}

	/**
	 * Get all timezone identifiers.
	 *
	 * @since    2.0.0
	 * @return   array List of IANA timezone identifiers.
	 */
	public static function get_all_timezones() {
		return DateTimeZone::listIdentifiers();
	}

	/**
	 * Validate timezone.
	 *
	 * @since    2.0.0
	 * @param    string $timezone IANA timezone.
	 * @return   bool             True if valid.
	 */
	public static function is_valid_timezone( $timezone ) {
		return in_array( $timezone, self::get_all_timezones(), true );
	}

	/**
	 * Get current time in timezone.
	 *
	 * @since    2.0.0
	 * @param    string $timezone IANA timezone.
	 * @param    string $format   Date format.
	 * @return   string           Formatted time.
	 */
	public static function get_current_time_in_timezone( $timezone, $format = 'Y-m-d H:i:s' ) {
		try {
			$dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
			return $dt->format( $format );
		} catch ( Exception $e ) {
			WTA_Logger::error( 'Invalid timezone', array(
				'timezone' => $timezone,
				'error'    => $e->getMessage(),
			) );
			return '';
		}
	}
}


