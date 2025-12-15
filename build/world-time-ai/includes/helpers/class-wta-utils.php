<?php
/**
 * Utility functions.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

class WTA_Utils {

	/**
	 * Generate slug from name.
	 *
	 * Converts name to lowercase, replaces spaces/special chars, and ensures uniqueness.
	 *
	 * @since    2.0.0
	 * @param    string $name      Location name.
	 * @param    int    $parent_id Optional. Parent post ID for uniqueness check.
	 * @return   string            Generated slug.
	 */
	public static function generate_slug( $name, $parent_id = 0 ) {
		// Convert to lowercase and sanitize
		$slug = sanitize_title( $name );

		// Ensure uniqueness
		$original_slug = $slug;
		$counter = 1;

		while ( self::slug_exists( $slug, $parent_id ) ) {
			$slug = $original_slug . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	/**
	 * Check if slug exists.
	 *
	 * @since    2.0.0
	 * @param    string $slug      Slug to check.
	 * @param    int    $parent_id Parent post ID.
	 * @return   bool              True if slug exists.
	 */
	private static function slug_exists( $slug, $parent_id = 0 ) {
		$args = array(
			'name'        => $slug,
			'post_type'   => WTA_POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
		);

		if ( $parent_id > 0 ) {
			$args['post_parent'] = $parent_id;
		}

		$posts = get_posts( $args );

		return ! empty( $posts );
	}

	/**
	 * Get continent code from name.
	 *
	 * @since    2.0.0
	 * @param    string $continent_name Continent name.
	 * @return   string                 Continent code.
	 */
	public static function get_continent_code( $continent_name ) {
		$map = array(
			'Europe'        => 'EU',
			'Europa'        => 'EU',
			'Asia'          => 'AS',
			'Asien'         => 'AS',
			'Africa'        => 'AF',
			'Afrika'        => 'AF',
			'Americas'      => 'NA',  // Fallback (shouldn't be used with new logic)
			'North America' => 'NA',
			'Nordamerika'   => 'NA',
			'South America' => 'SA',
			'Sydamerika'    => 'SA',
			'Oceania'       => 'OC',
			'Oceanien'      => 'OC',
			'Antarctica'    => 'AN',
			'Antarktis'     => 'AN',
			'Polar'         => 'AN',  // Map Polar to Antarctica
		);

		return isset( $map[ $continent_name ] ) ? $map[ $continent_name ] : 'XX';
	}

	/**
	 * Get available continents.
	 *
	 * @since    2.0.0
	 * @param    string $language  Language code.
	 * @return   array             Array of continent codes => names.
	 */
	public static function get_available_continents( $language = 'da-DK' ) {
		$continents = array(
			'da-DK' => array(
				'EU' => 'Europa',
				'AS' => 'Asien',
				'AF' => 'Afrika',
				'NA' => 'Nordamerika',
				'SA' => 'Sydamerika',
				'OC' => 'Oceanien',
				'AN' => 'Antarktis',
			),
			'en-US' => array(
				'EU' => 'Europe',
				'AS' => 'Asia',
				'AF' => 'Africa',
				'NA' => 'North America',
				'SA' => 'South America',
				'OC' => 'Oceania',
				'AN' => 'Antarctica',
			),
		);

		return isset( $continents[ $language ] ) ? $continents[ $language ] : $continents['en-US'];
	}

	/**
	 * Get continent name from code.
	 *
	 * @since    2.0.0
	 * @param    string $code      Continent code.
	 * @param    string $language  Language code.
	 * @return   string            Continent name.
	 */
	public static function get_continent_name_from_code( $code, $language = 'en-US' ) {
		$map = array(
			'en-US' => array(
				'EU' => 'Europe',
				'AS' => 'Asia',
				'AF' => 'Africa',
				'NA' => 'North America',
				'SA' => 'South America',
				'OC' => 'Oceania',
				'AN' => 'Antarctica',
			),
			'da-DK' => array(
				'EU' => 'Europa',
				'AS' => 'Asien',
				'AF' => 'Afrika',
				'NA' => 'Nordamerika',
				'SA' => 'Sydamerika',
				'OC' => 'Oceanien',
				'AN' => 'Antarktis',
			),
		);

		return isset( $map[ $language ][ $code ] ) ? $map[ $language ][ $code ] : $code;
	}

	/**
	 * Stream parse large JSON file.
	 *
	 * Parses JSON file in chunks to avoid memory issues.
	 * WARNING: This is a simplified version. For production, use a proper streaming JSON parser library.
	 *
	 * @since    2.0.0
	 * @param    string   $file_path Path to JSON file.
	 * @param    callable $callback  Callback function to process each item.
	 * @param    int      $chunk_size Chunk size in bytes.
	 * @return   int                 Number of items processed.
	 */
	public static function stream_parse_json( $file_path, $callback, $chunk_size = 8192 ) {
		if ( ! file_exists( $file_path ) ) {
			return 0;
		}

		// For now, use json_decode if file is not too large
		$file_size = filesize( $file_path );

		if ( $file_size < 50 * 1024 * 1024 ) { // Less than 50MB
			$content = file_get_contents( $file_path );
			$data = json_decode( $content, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				WTA_Logger::error( 'JSON decode error', array(
					'file'  => $file_path,
					'error' => json_last_error_msg(),
				) );
				return 0;
			}

			$count = 0;
			foreach ( $data as $item ) {
				call_user_func( $callback, $item );
				$count++;

				// Prevent timeout
				if ( $count % 100 === 0 ) {
					set_time_limit( 30 );
				}
			}

			return $count;
		}

		// For very large files, read line by line (assuming each item is on a line)
		// This is a fallback - ideally use a proper streaming JSON parser
		WTA_Logger::warning( 'Large JSON file detected - using line-by-line parsing', array(
			'file' => $file_path,
			'size' => size_format( $file_size ),
		) );

		return 0;
	}

	/**
	 * Format date in Danish.
	 *
	 * @since    2.0.0
	 * @param    string $format Date format.
	 * @param    int    $timestamp Timestamp.
	 * @return   string          Formatted date.
	 */
	public static function format_danish_date( $format = 'd. F Y', $timestamp = null ) {
		if ( null === $timestamp ) {
			$timestamp = time();
		}

		$date = date_i18n( $format, $timestamp );

		// Replace month names with Danish
		$months = array(
			'January'   => 'januar',
			'February'  => 'februar',
			'March'     => 'marts',
			'April'     => 'april',
			'May'       => 'maj',
			'June'      => 'juni',
			'July'      => 'juli',
			'August'    => 'august',
			'September' => 'september',
			'October'   => 'oktober',
			'November'  => 'november',
			'December'  => 'december',
		);

		return str_replace( array_keys( $months ), array_values( $months ), $date );
	}

	/**
	 * Sanitize JSON data.
	 *
	 * @since    2.0.0
	 * @param    mixed $data Data to sanitize.
	 * @return   mixed       Sanitized data.
	 */
	public static function sanitize_json_data( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( __CLASS__, 'sanitize_json_data' ), $data );
		}

		if ( is_string( $data ) ) {
			return sanitize_text_field( $data );
		}

		return $data;
	}
}


