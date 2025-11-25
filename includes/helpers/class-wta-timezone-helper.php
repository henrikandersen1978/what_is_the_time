<?php
/**
 * Timezone helper functions.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

/**
 * Helper class for timezone operations.
 *
 * @since 1.0.0
 */
class WTA_Timezone_Helper {

	/**
	 * Get time difference between two timezones in hours.
	 *
	 * @since 1.0.0
	 * @param string $timezone1 First timezone.
	 * @param string $timezone2 Second timezone.
	 * @return float Time difference in hours.
	 */
	public static function get_time_difference( $timezone1, $timezone2 ) {
		try {
			$tz1 = new DateTimeZone( $timezone1 );
			$tz2 = new DateTimeZone( $timezone2 );
			
			$dt1 = new DateTime( 'now', $tz1 );
			$dt2 = new DateTime( 'now', $tz2 );
			
			$offset1 = $tz1->getOffset( $dt1 );
			$offset2 = $tz2->getOffset( $dt2 );
			
			$difference = ( $offset1 - $offset2 ) / 3600; // Convert to hours
			
			return $difference;
		} catch ( Exception $e ) {
			WTA_Logger::error( 'Timezone difference calculation failed', array(
				'timezone1' => $timezone1,
				'timezone2' => $timezone2,
				'error'     => $e->getMessage(),
			) );
			return 0;
		}
	}

	/**
	 * Get formatted time difference string.
	 *
	 * @since 1.0.0
	 * @param string $timezone1 First timezone.
	 * @param string $timezone2 Second timezone.
	 * @param string $format Format: 'short' or 'long'.
	 * @return string Formatted time difference.
	 */
	public static function get_formatted_difference( $timezone1, $timezone2, $format = 'short' ) {
		$difference = self::get_time_difference( $timezone1, $timezone2 );
		
		if ( $difference == 0 ) {
			return $format === 'short' ? '0h' : __( 'Same time', WTA_TEXT_DOMAIN );
		}

		$hours = abs( $difference );
		$sign = $difference > 0 ? '+' : '-';
		
		// Calculate hours and minutes
		$h = floor( $hours );
		$m = round( ( $hours - $h ) * 60 );

		if ( $format === 'short' ) {
			if ( $m > 0 ) {
				return sprintf( '%s%dh %dm', $sign, $h, $m );
			} else {
				return sprintf( '%s%dh', $sign, $h );
			}
		} else {
			if ( $m > 0 ) {
				/* translators: %1$s: sign (+/-), %2$d: hours, %3$d: minutes */
				return sprintf( __( '%1$s%2$d hours %3$d minutes', WTA_TEXT_DOMAIN ), $sign, $h, $m );
			} else {
				if ( $h == 1 ) {
					/* translators: %1$s: sign (+/-) */
					return sprintf( __( '%1$s1 hour', WTA_TEXT_DOMAIN ), $sign );
				} else {
					/* translators: %1$s: sign (+/-), %2$d: hours */
					return sprintf( __( '%1$s%2$d hours', WTA_TEXT_DOMAIN ), $sign, $h );
				}
			}
		}
	}

	/**
	 * Get current time in a timezone.
	 *
	 * @since 1.0.0
	 * @param string $timezone Timezone identifier.
	 * @param string $format   Time format (default: 'Y-m-d H:i:s').
	 * @return string|false Formatted time or false on error.
	 */
	public static function get_current_time( $timezone, $format = 'Y-m-d H:i:s' ) {
		try {
			$tz = new DateTimeZone( $timezone );
			$dt = new DateTime( 'now', $tz );
			return $dt->format( $format );
		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to get current time', array(
				'timezone' => $timezone,
				'error'    => $e->getMessage(),
			) );
			return false;
		}
	}

	/**
	 * Check if a timezone is valid.
	 *
	 * @since 1.0.0
	 * @param string $timezone Timezone identifier.
	 * @return bool True if valid.
	 */
	public static function is_valid_timezone( $timezone ) {
		try {
			new DateTimeZone( $timezone );
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Get timezone from coordinates using a simple mapping.
	 * This is a fallback for countries that don't need TimeZoneDB API.
	 *
	 * @since 1.0.0
	 * @param string $country_code ISO2 country code.
	 * @return string|null Default timezone for country or null.
	 */
	public static function get_default_timezone_for_country( $country_code ) {
		// Map of common countries to their primary timezone
		$timezone_map = array(
			'DK' => 'Europe/Copenhagen',
			'DE' => 'Europe/Berlin',
			'FR' => 'Europe/Paris',
			'GB' => 'Europe/London',
			'ES' => 'Europe/Madrid',
			'IT' => 'Europe/Rome',
			'NL' => 'Europe/Amsterdam',
			'BE' => 'Europe/Brussels',
			'SE' => 'Europe/Stockholm',
			'NO' => 'Europe/Oslo',
			'FI' => 'Europe/Helsinki',
			'PL' => 'Europe/Warsaw',
			'CZ' => 'Europe/Prague',
			'AT' => 'Europe/Vienna',
			'CH' => 'Europe/Zurich',
			'PT' => 'Europe/Lisbon',
			'GR' => 'Europe/Athens',
			'HU' => 'Europe/Budapest',
			'RO' => 'Europe/Bucharest',
			'BG' => 'Europe/Sofia',
			'IE' => 'Europe/Dublin',
			'HR' => 'Europe/Zagreb',
			'SK' => 'Europe/Bratislava',
			'SI' => 'Europe/Ljubljana',
			'JP' => 'Asia/Tokyo',
			'KR' => 'Asia/Seoul',
			'IN' => 'Asia/Kolkata',
			'TH' => 'Asia/Bangkok',
			'VN' => 'Asia/Ho_Chi_Minh',
			'PH' => 'Asia/Manila',
			'MY' => 'Asia/Kuala_Lumpur',
			'SG' => 'Asia/Singapore',
			'HK' => 'Asia/Hong_Kong',
			'TW' => 'Asia/Taipei',
			'NZ' => 'Pacific/Auckland',
			'AR' => 'America/Argentina/Buenos_Aires',
			'CL' => 'America/Santiago',
			'CO' => 'America/Bogota',
			'PE' => 'America/Lima',
			'VE' => 'America/Caracas',
			'UY' => 'America/Montevideo',
			'PY' => 'America/Asuncion',
			'BO' => 'America/La_Paz',
			'EC' => 'America/Guayaquil',
			'ZA' => 'Africa/Johannesburg',
			'EG' => 'Africa/Cairo',
			'NG' => 'Africa/Lagos',
			'KE' => 'Africa/Nairobi',
			'GH' => 'Africa/Accra',
			'ET' => 'Africa/Addis_Ababa',
			'TZ' => 'Africa/Dar_es_Salaam',
			'UG' => 'Africa/Kampala',
			'DZ' => 'Africa/Algiers',
			'MA' => 'Africa/Casablanca',
			'TN' => 'Africa/Tunis',
			'TR' => 'Europe/Istanbul',
			'SA' => 'Asia/Riyadh',
			'AE' => 'Asia/Dubai',
			'IL' => 'Asia/Jerusalem',
			'IQ' => 'Asia/Baghdad',
			'IR' => 'Asia/Tehran',
			'PK' => 'Asia/Karachi',
			'BD' => 'Asia/Dhaka',
			'LK' => 'Asia/Colombo',
			'MM' => 'Asia/Yangon',
			'KH' => 'Asia/Phnom_Penh',
			'LA' => 'Asia/Vientiane',
			'NP' => 'Asia/Kathmandu',
			'AF' => 'Asia/Kabul',
		);

		return isset( $timezone_map[ $country_code ] ) ? $timezone_map[ $country_code ] : null;
	}

	/**
	 * Check if a country requires individual city timezone lookups.
	 *
	 * @since 1.0.0
	 * @param string $country_code ISO2 country code.
	 * @return bool True if complex country.
	 */
	public static function is_complex_country( $country_code ) {
		$complex_countries = get_option( 'wta_complex_countries', array() );
		return isset( $complex_countries[ $country_code ] );
	}

	/**
	 * Get timezone abbreviation.
	 *
	 * @since 1.0.0
	 * @param string $timezone Timezone identifier.
	 * @return string Timezone abbreviation (e.g., 'CET', 'EST').
	 */
	public static function get_timezone_abbreviation( $timezone ) {
		try {
			$tz = new DateTimeZone( $timezone );
			$dt = new DateTime( 'now', $tz );
			return $dt->format( 'T' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Get UTC offset for a timezone.
	 *
	 * @since 1.0.0
	 * @param string $timezone Timezone identifier.
	 * @return string UTC offset (e.g., '+01:00').
	 */
	public static function get_utc_offset( $timezone ) {
		try {
			$tz = new DateTimeZone( $timezone );
			$dt = new DateTime( 'now', $tz );
			return $dt->format( 'P' );
		} catch ( Exception $e ) {
			return '+00:00';
		}
	}
}





