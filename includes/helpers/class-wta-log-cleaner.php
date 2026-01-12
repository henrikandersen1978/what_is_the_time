<?php
/**
 * Log Cleaner - Automatic cleanup of old log files.
 *
 * Runs daily at 04:00 to delete logs older than today.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 * @since      2.35.7
 */

class WTA_Log_Cleaner {

	/**
	 * Clean up old log files.
	 *
	 * Deletes log files older than 5 days.
	 * Runs daily at 04:00 via Action Scheduler.
	 *
	 * @since    2.35.7
	 * @since    3.4.9 Changed from deleting all old logs to keeping last 5 days
	 * @return   array  Cleanup statistics.
	 */
	public static function cleanup_old_logs() {
		$upload_dir = wp_upload_dir();
		$logs_dir = $upload_dir['basedir'] . '/world-time-ai-data/logs';

		// Check if logs directory exists
		if ( ! file_exists( $logs_dir ) || ! is_dir( $logs_dir ) ) {
			WTA_Logger::warning( 'Logs directory not found for cleanup', array(
				'path' => $logs_dir,
			) );
			return array(
				'deleted' => 0,
				'kept'    => 0,
				'errors'  => 0,
			);
		}

		// Calculate cutoff date (5 days ago)
		$cutoff_timestamp = strtotime( '-5 days', current_time( 'timestamp' ) );
		$cutoff_date = date( 'Y-m-d', $cutoff_timestamp );

		$deleted = 0;
		$kept = 0;
		$errors = 0;

		// Scan logs directory
		$files = scandir( $logs_dir );
		
		foreach ( $files as $file ) {
			// Skip . and .. and .htaccess
			if ( $file === '.' || $file === '..' || $file === '.htaccess' ) {
				continue;
			}

			$file_path = $logs_dir . '/' . $file;

			// Only process log files
			if ( ! is_file( $file_path ) || ! preg_match( '/^(\d{4}-\d{2}-\d{2})-log\.txt$/', $file, $matches ) ) {
				continue;
			}

			// Extract date from filename
			$file_date = $matches[1];

			// Keep files from the last 5 days
			if ( $file_date >= $cutoff_date ) {
				$kept++;
				continue;
			}

			// Delete old log (older than 5 days)
			if ( unlink( $file_path ) ) {
				$deleted++;
				WTA_Logger::debug( 'Old log file deleted', array(
					'file' => $file,
					'file_date' => $file_date,
					'cutoff_date' => $cutoff_date,
				) );
			} else {
				$errors++;
				WTA_Logger::warning( 'Failed to delete old log file', array(
					'file' => $file,
					'path' => $file_path,
				) );
			}
		}

		WTA_Logger::info( 'Log cleanup completed', array(
			'deleted' => $deleted,
			'kept'    => $kept,
			'errors'  => $errors,
			'retention_days' => 5,
			'cutoff_date' => $cutoff_date,
		) );

		return array(
			'deleted' => $deleted,
			'kept'    => $kept,
			'errors'  => $errors,
		);
	}
}
