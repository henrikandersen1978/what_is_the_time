<?php
/**
 * Error logging utility for the plugin.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

/**
 * Logger class for error tracking.
 *
 * @since 1.0.0
 */
class WTA_Logger {

	/**
	 * Log levels.
	 */
	const LEVEL_ERROR   = 'error';
	const LEVEL_WARNING = 'warning';
	const LEVEL_INFO    = 'info';
	const LEVEL_DEBUG   = 'debug';

	/**
	 * Log an error message.
	 *
	 * @since 1.0.0
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 */
	public static function error( $message, $context = array() ) {
		self::log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @since 1.0.0
	 * @param string $message Warning message.
	 * @param array  $context Additional context data.
	 */
	public static function warning( $message, $context = array() ) {
		self::log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @since 1.0.0
	 * @param string $message Info message.
	 * @param array  $context Additional context data.
	 */
	public static function info( $message, $context = array() ) {
		self::log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @since 1.0.0
	 * @param string $message Debug message.
	 * @param array  $context Additional context data.
	 */
	public static function debug( $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::log( self::LEVEL_DEBUG, $message, $context );
		}
	}

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @param string $level   Log level.
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 */
	private static function log( $level, $message, $context = array() ) {
		// Format the log entry
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$level_upper = strtoupper( $level );
		
		$log_message = sprintf(
			'[%s] [%s] %s',
			$timestamp,
			$level_upper,
			$message
		);

		// Add context if provided
		if ( ! empty( $context ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $context );
		}

		// Write to WordPress debug log if WP_DEBUG_LOG is enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[World Time AI] ' . $log_message );
		}

		// Store in transient for admin display (last 100 messages)
		self::store_in_transient( $level, $message, $context );
	}

	/**
	 * Store log in transient for admin display.
	 *
	 * @since 1.0.0
	 * @param string $level   Log level.
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 */
	private static function store_in_transient( $level, $message, $context ) {
		$logs = get_transient( 'wta_logs' );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'timestamp' => current_time( 'timestamp' ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
		);

		// Keep only last 100 entries
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		set_transient( 'wta_logs', $logs, WEEK_IN_SECONDS );
	}

	/**
	 * Get recent logs.
	 *
	 * @since 1.0.0
	 * @param int    $limit Maximum number of logs to return.
	 * @param string $level Filter by level (optional).
	 * @return array Array of log entries.
	 */
	public static function get_logs( $limit = 50, $level = '' ) {
		$logs = get_transient( 'wta_logs' );
		if ( ! is_array( $logs ) ) {
			return array();
		}

		// Filter by level if specified
		if ( ! empty( $level ) ) {
			$logs = array_filter(
				$logs,
				function( $log ) use ( $level ) {
					return $log['level'] === $level;
				}
			);
		}

		// Sort by timestamp descending (newest first)
		usort(
			$logs,
			function( $a, $b ) {
				return $b['timestamp'] - $a['timestamp'];
			}
		);

		// Limit results
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Clear all logs.
	 *
	 * @since 1.0.0
	 */
	public static function clear_logs() {
		delete_transient( 'wta_logs' );
	}

	/**
	 * Get log count by level.
	 *
	 * @since 1.0.0
	 * @return array Array of counts by level.
	 */
	public static function get_log_counts() {
		$logs = get_transient( 'wta_logs' );
		if ( ! is_array( $logs ) ) {
			return array(
				'error'   => 0,
				'warning' => 0,
				'info'    => 0,
				'debug'   => 0,
			);
		}

		$counts = array(
			'error'   => 0,
			'warning' => 0,
			'info'    => 0,
			'debug'   => 0,
		);

		foreach ( $logs as $log ) {
			if ( isset( $counts[ $log['level'] ] ) ) {
				$counts[ $log['level'] ]++;
			}
		}

		return $counts;
	}
}




