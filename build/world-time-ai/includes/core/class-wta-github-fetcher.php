<?php
/**
 * GitHub data fetcher with persistent storage.
 *
 * Manages fetching JSON data from GitHub or local files.
 * CRITICAL: Uses persistent storage in wp-content/uploads/ to survive plugin updates.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

class WTA_Github_Fetcher {

	/**
	 * Get persistent data directory.
	 *
	 * @since    2.0.0
	 * @return   string Data directory path.
	 */
	public static function get_data_directory() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/world-time-ai-data';
	}

	/**
	 * Ensure data directory exists.
	 *
	 * @since    2.0.0
	 * @return   bool Success status.
	 */
	private static function ensure_data_directory() {
		$data_dir = self::get_data_directory();

		if ( ! file_exists( $data_dir ) ) {
			return wp_mkdir_p( $data_dir );
		}

		return true;
	}

	/**
	 * Get JSON file path.
	 *
	 * Priority:
	 * 1. Persistent location (wp-content/uploads/world-time-ai-data/)
	 * 2. Old plugin location (for backward compatibility)
	 * 3. null if not found
	 *
	 * @since    2.0.0
	 * @param    string $filename JSON filename.
	 * @return   string|null      File path or null if not found.
	 */
	private static function get_json_file_path( $filename ) {
		// Priority 1: Persistent location
		$persistent_path = self::get_data_directory() . '/' . $filename;
		if ( file_exists( $persistent_path ) ) {
			return $persistent_path;
		}

		// Priority 2: Old plugin location (for migration)
		$old_path = WTA_PLUGIN_DIR . 'json/' . $filename;
		if ( file_exists( $old_path ) ) {
			// Auto-migrate to persistent location
			self::migrate_json_file( $old_path, $persistent_path, $filename );
			return $persistent_path;
		}

		return null;
	}

	/**
	 * Migrate JSON file to persistent storage.
	 *
	 * @since    2.0.0
	 * @param    string $from_path Source file path.
	 * @param    string $to_path   Destination file path.
	 * @param    string $filename  Filename for logging.
	 * @return   bool              Success status.
	 */
	private static function migrate_json_file( $from_path, $to_path, $filename ) {
		self::ensure_data_directory();

		$result = copy( $from_path, $to_path );

		if ( $result ) {
			WTA_Logger::info( "Migrated $filename to persistent storage", array(
				'from' => $from_path,
				'to'   => $to_path,
			) );
		} else {
			WTA_Logger::error( "Failed to migrate $filename", array(
				'from' => $from_path,
				'to'   => $to_path,
			) );
		}

		return $result;
	}

	/**
	 * Fetch countries data.
	 *
	 * @since    2.0.0
	 * @return   array|false Countries data or false on failure.
	 */
	public static function fetch_countries() {
		// Check local file first
		$local_path = self::get_json_file_path( 'countries.json' );
		if ( $local_path ) {
			WTA_Logger::info( 'Using local countries.json', array( 'path' => $local_path ) );
			return self::load_json_file( $local_path );
		}

		// Fallback to GitHub
		$url = get_option( 'wta_github_countries_url', '' );
		if ( empty( $url ) ) {
			WTA_Logger::error( 'No local countries.json and no GitHub URL configured' );
			return false;
		}

		return self::fetch_from_github( $url, 'countries.json' );
	}

	/**
	 * Fetch states data.
	 *
	 * @since    2.0.0
	 * @return   array|false States data or false on failure.
	 */
	public static function fetch_states() {
		// Check local file first
		$local_path = self::get_json_file_path( 'states.json' );
		if ( $local_path ) {
			WTA_Logger::info( 'Using local states.json', array( 'path' => $local_path ) );
			return self::load_json_file( $local_path );
		}

		// Fallback to GitHub
		$url = get_option( 'wta_github_states_url', '' );
		if ( empty( $url ) ) {
			WTA_Logger::error( 'No local states.json and no GitHub URL configured' );
			return false;
		}

		return self::fetch_from_github( $url, 'states.json' );
	}

	/**
	 * Get cities file path.
	 *
	 * Cities file is too large to load into memory - return path for streaming.
	 *
	 * @since    2.0.0
	 * @return   string|false File path or false if not found.
	 */
	public static function get_cities_file_path() {
		$local_path = self::get_json_file_path( 'cities.json' );

		if ( ! $local_path ) {
			WTA_Logger::error( 'cities.json not found in persistent storage' );
			return false;
		}

		return $local_path;
	}

	/**
	 * Load JSON file.
	 *
	 * @since    2.0.0
	 * @param    string $file_path File path.
	 * @return   array|false       Decoded JSON data or false on failure.
	 */
	private static function load_json_file( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			WTA_Logger::error( 'Failed to read file', array( 'path' => $file_path ) );
			return false;
		}

		$data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			WTA_Logger::error( 'JSON decode error', array(
				'path'  => $file_path,
				'error' => json_last_error_msg(),
			) );
			return false;
		}

		return $data;
	}

	/**
	 * Fetch JSON from GitHub and save locally.
	 *
	 * @since    2.0.0
	 * @param    string $url      GitHub URL.
	 * @param    string $filename Filename to save as.
	 * @return   array|false      Decoded JSON data or false on failure.
	 */
	private static function fetch_from_github( $url, $filename ) {
		WTA_Logger::info( "Fetching $filename from GitHub", array( 'url' => $url ) );

		$response = wp_remote_get( $url, array(
			'timeout' => 120, // Large files need more time
		) );

		if ( is_wp_error( $response ) ) {
			WTA_Logger::error( "Failed to fetch $filename from GitHub", array(
				'url'   => $url,
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			WTA_Logger::error( "JSON decode error for $filename", array(
				'url'   => $url,
				'error' => json_last_error_msg(),
			) );
			return false;
		}

		// Save to persistent storage
		self::ensure_data_directory();
		$save_path = self::get_data_directory() . '/' . $filename;
		file_put_contents( $save_path, $body );

		WTA_Logger::info( "Saved $filename to persistent storage", array(
			'path' => $save_path,
			'size' => size_format( strlen( $body ) ),
		) );

		return $data;
	}

	/**
	 * Get file info.
	 *
	 * @since    2.0.0
	 * @param    string $filename JSON filename.
	 * @return   array|false      File info or false if not found.
	 */
	public static function get_file_info( $filename ) {
		$path = self::get_json_file_path( $filename );

		if ( ! $path ) {
			return false;
		}

		return array(
			'path'     => $path,
			'size'     => filesize( $path ),
			'size_formatted' => size_format( filesize( $path ) ),
			'modified' => filemtime( $path ),
			'modified_formatted' => date( 'Y-m-d H:i:s', filemtime( $path ) ),
		);
	}
}
