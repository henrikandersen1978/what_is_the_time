<?php
/**
 * Logging functionality.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

class WTA_Logger {

	/**
	 * Log directory.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $log_dir    Path to log directory.
	 */
	private static $log_dir = null;

	/**
	 * Get log directory path.
	 *
	 * @since    2.0.0
	 * @return   string Log directory path.
	 */
	private static function get_log_dir() {
		if ( null === self::$log_dir ) {
			$upload_dir = wp_upload_dir();
			self::$log_dir = $upload_dir['basedir'] . '/world-time-ai-data/logs';
		}

		return self::$log_dir;
	}

	/**
	 * Get log file path for today.
	 *
	 * @since    2.0.0
	 * @return   string Log file path.
	 */
	private static function get_log_file() {
		$log_dir = self::get_log_dir();
		$date = date( 'Y-m-d' );
		return $log_dir . '/' . $date . '-log.txt';
	}

	/**
	 * Write log entry.
	 *
	 * @since    2.0.0
	 * @param    string $level   Log level (ERROR, WARNING, INFO, DEBUG).
	 * @param    string $message Log message.
	 * @param    array  $context Additional context data.
	 */
	private static function log( $level, $message, $context = array() ) {
		$log_file = self::get_log_file();
		$timestamp = current_time( 'Y-m-d H:i:s' );

		$log_entry = sprintf(
			"[%s] %s: %s\n",
			$timestamp,
			$level,
			$message
		);

		if ( ! empty( $context ) ) {
			$log_entry .= 'Context: ' . wp_json_encode( $context, JSON_PRETTY_PRINT ) . "\n";
		}

		$log_entry .= "\n";

		// Ensure log directory exists
		$log_dir = self::get_log_dir();
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Write to file
		file_put_contents( $log_file, $log_entry, FILE_APPEND );
	}

	/**
	 * Log error message.
	 *
	 * @since    2.0.0
	 * @param    string $message Log message.
	 * @param    array  $context Additional context data.
	 */
	public static function error( $message, $context = array() ) {
		self::log( 'ERROR', $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @since    2.0.0
	 * @param    string $message Log message.
	 * @param    array  $context Additional context data.
	 */
	public static function warning( $message, $context = array() ) {
		self::log( 'WARNING', $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @since    2.0.0
	 * @param    string $message Log message.
	 * @param    array  $context Additional context data.
	 */
	public static function info( $message, $context = array() ) {
		self::log( 'INFO', $message, $context );
	}

	/**
	 * Log debug message.
	 *
	 * @since    2.0.0
	 * @param    string $message Log message.
	 * @param    array  $context Additional context data.
	 */
	public static function debug( $message, $context = array() ) {
		self::log( 'DEBUG', $message, $context );
	}

	/**
	 * Get recent log entries.
	 *
	 * @since    2.0.0
	 * @param    int $limit Maximum number of entries to return.
	 * @return   array      Array of log entries.
	 */
	public static function get_recent_logs( $limit = 100 ) {
		$log_file = self::get_log_file();

		if ( ! file_exists( $log_file ) ) {
			return array();
		}

		$lines = file( $log_file, FILE_IGNORE_NEW_LINES );
		$lines = array_reverse( $lines );
		$lines = array_slice( $lines, 0, $limit );

		return $lines;
	}

	/**
	 * Clear old log files.
	 *
	 * @since    2.0.0
	 * @param    int $days Keep logs from last X days.
	 * @return   int       Number of files deleted.
	 */
	public static function clear_old_logs( $days = 7 ) {
		$log_dir = self::get_log_dir();
		$threshold = strtotime( "-$days days" );
		$deleted = 0;

		if ( ! is_dir( $log_dir ) ) {
			return 0;
		}

		$files = glob( $log_dir . '/*.txt' );

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $threshold ) {
				unlink( $file );
				$deleted++;
			}
		}

		return $deleted;
	}
}


