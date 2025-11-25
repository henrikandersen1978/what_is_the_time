<?php
/**
 * Utility functions for the plugin.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

/**
 * Utility class with common helper functions.
 *
 * @since 1.0.0
 */
class WTA_Utils {

	/**
	 * Sanitize and validate latitude.
	 *
	 * @since 1.0.0
	 * @param mixed $lat Latitude value.
	 * @return float|null Sanitized latitude or null if invalid.
	 */
	public static function sanitize_latitude( $lat ) {
		$lat = floatval( $lat );
		if ( $lat >= -90 && $lat <= 90 ) {
			return $lat;
		}
		return null;
	}

	/**
	 * Sanitize and validate longitude.
	 *
	 * @since 1.0.0
	 * @param mixed $lng Longitude value.
	 * @return float|null Sanitized longitude or null if invalid.
	 */
	public static function sanitize_longitude( $lng ) {
		$lng = floatval( $lng );
		if ( $lng >= -180 && $lng <= 180 ) {
			return $lng;
		}
		return null;
	}

	/**
	 * Sanitize timezone string.
	 *
	 * @since 1.0.0
	 * @param string $timezone Timezone identifier.
	 * @return string|null Valid timezone or null.
	 */
	public static function sanitize_timezone( $timezone ) {
		$timezone = sanitize_text_field( $timezone );
		$valid_timezones = timezone_identifiers_list();
		if ( in_array( $timezone, $valid_timezones, true ) ) {
			return $timezone;
		}
		return null;
	}

	/**
	 * Generate a safe slug from a string.
	 *
	 * @since 1.0.0
	 * @param string $text Text to convert to slug.
	 * @param string $language Language code for transliteration.
	 * @return string Safe slug.
	 */
	public static function generate_slug( $text, $language = 'en' ) {
		// Remove accents and special characters
		$text = remove_accents( $text );
		
		// Convert to lowercase
		$text = strtolower( $text );
		
		// Replace spaces and underscores with hyphens
		$text = preg_replace( '/[\s_]+/', '-', $text );
		
		// Remove any characters that aren't alphanumeric or hyphens
		$text = preg_replace( '/[^a-z0-9\-]/', '', $text );
		
		// Remove multiple consecutive hyphens
		$text = preg_replace( '/-+/', '-', $text );
		
		// Trim hyphens from ends
		$text = trim( $text, '-' );
		
		return $text;
	}

	/**
	 * Check if a post with specific meta exists.
	 *
	 * @since 1.0.0
	 * @param string $meta_key Meta key to check.
	 * @param mixed  $meta_value Meta value to check.
	 * @param string $post_type Post type.
	 * @return int|false Post ID if exists, false otherwise.
	 */
	public static function post_exists_by_meta( $meta_key, $meta_value, $post_type = WTA_POST_TYPE ) {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => $meta_key,
					'value' => $meta_value,
				),
			),
		);

		$query = new WP_Query( $args );
		
		if ( $query->have_posts() ) {
			return $query->posts[0];
		}
		
		return false;
	}

	/**
	 * Get post ID by multiple meta criteria.
	 *
	 * @since 1.0.0
	 * @param array $meta_queries Array of meta query arrays.
	 * @param string $post_type Post type.
	 * @return int|false Post ID if exists, false otherwise.
	 */
	public static function get_post_by_meta( $meta_queries, $post_type = WTA_POST_TYPE ) {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => $meta_queries,
		);

		$query = new WP_Query( $args );
		
		if ( $query->have_posts() ) {
			return $query->posts[0];
		}
		
		return false;
	}

	/**
	 * Format time for display.
	 *
	 * @since 1.0.0
	 * @param string $timezone Timezone identifier.
	 * @param string $format Time format (default: H:i:s).
	 * @return string Formatted time.
	 */
	public static function get_time_in_timezone( $timezone, $format = 'H:i:s' ) {
		try {
			$tz = new DateTimeZone( $timezone );
			$dt = new DateTime( 'now', $tz );
			return $dt->format( $format );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Get ISO 8601 formatted time in timezone.
	 *
	 * @since 1.0.0
	 * @param string $timezone Timezone identifier.
	 * @return string ISO 8601 formatted time.
	 */
	public static function get_iso_time_in_timezone( $timezone ) {
		try {
			$tz = new DateTimeZone( $timezone );
			$dt = new DateTime( 'now', $tz );
			return $dt->format( 'c' ); // ISO 8601
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Check if Yoast SEO is active.
	 *
	 * @since 1.0.0
	 * @return bool True if Yoast is active.
	 */
	public static function is_yoast_active() {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Check if ACF Pro is active.
	 *
	 * @since 1.0.0
	 * @return bool True if ACF Pro is active.
	 */
	public static function is_acf_active() {
		return class_exists( 'ACF' );
	}

	/**
	 * Get continent name from region.
	 *
	 * @since 1.0.0
	 * @param string $region Region name from database.
	 * @return string Continent code.
	 */
	public static function get_continent_code( $region ) {
		$continent_map = array(
			'Africa'     => 'AF',
			'Americas'   => 'AM',
			'Asia'       => 'AS',
			'Europe'     => 'EU',
			'Oceania'    => 'OC',
			'Antarctica' => 'AN',
		);

		return isset( $continent_map[ $region ] ) ? $continent_map[ $region ] : 'XX';
	}

	/**
	 * Get all available continents.
	 *
	 * @since 1.0.0
	 * @return array Array of continent codes and names.
	 */
	public static function get_available_continents() {
		return array(
			'AF' => __( 'Africa', WTA_TEXT_DOMAIN ),
			'AM' => __( 'Americas', WTA_TEXT_DOMAIN ),
			'AS' => __( 'Asia', WTA_TEXT_DOMAIN ),
			'EU' => __( 'Europe', WTA_TEXT_DOMAIN ),
			'OC' => __( 'Oceania', WTA_TEXT_DOMAIN ),
			'AN' => __( 'Antarctica', WTA_TEXT_DOMAIN ),
		);
	}

	/**
	 * Sanitize JSON data.
	 *
	 * @since 1.0.0
	 * @param mixed $data Data to encode.
	 * @return string JSON string.
	 */
	public static function sanitize_json( $data ) {
		return wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Parse JSON safely.
	 *
	 * @since 1.0.0
	 * @param string $json JSON string.
	 * @return mixed|null Parsed data or null on error.
	 */
	public static function parse_json( $json ) {
		$data = json_decode( $json, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $data;
		}
		return null;
	}

	/**
	 * Get memory usage in readable format.
	 *
	 * @since 1.0.0
	 * @return string Memory usage.
	 */
	public static function get_memory_usage() {
		$memory = memory_get_usage( true );
		$unit = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
		return @round( $memory / pow( 1024, ( $i = floor( log( $memory, 1024 ) ) ) ), 2 ) . ' ' . $unit[ $i ];
	}

	/**
	 * Check if we're close to max execution time.
	 *
	 * @since 1.0.0
	 * @param int $buffer Buffer time in seconds (default: 5).
	 * @return bool True if close to timeout.
	 */
	public static function is_approaching_timeout( $buffer = 5 ) {
		$max_execution = ini_get( 'max_execution_time' );
		if ( $max_execution == 0 ) {
			return false; // Unlimited
		}
		
		$start_time = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? $_SERVER['REQUEST_TIME_FLOAT'] : $_SERVER['REQUEST_TIME'];
		$elapsed = microtime( true ) - $start_time;
		
		return ( $elapsed + $buffer ) >= $max_execution;
	}

	/**
	 * Validate API key format.
	 *
	 * @since 1.0.0
	 * @param string $key API key.
	 * @return bool True if valid format.
	 */
	public static function is_valid_api_key( $key ) {
		$key = trim( $key );
		return ! empty( $key ) && strlen( $key ) > 10;
	}

	/**
	 * Truncate string to specified length.
	 *
	 * @since 1.0.0
	 * @param string $text Text to truncate.
	 * @param int    $length Maximum length.
	 * @param string $suffix Suffix to append (default: '...').
	 * @return string Truncated text.
	 */
	public static function truncate( $text, $length, $suffix = '...' ) {
		if ( mb_strlen( $text ) > $length ) {
			return mb_substr( $text, 0, $length ) . $suffix;
		}
		return $text;
	}
}






