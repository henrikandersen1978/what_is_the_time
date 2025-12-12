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
	 * Deletes all log files except today's log.
	 * Runs daily at 04:00 via Action Scheduler.
	 *
	 * @since    2.35.7
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

		// Get today's date for comparison
		$today = date( 'Y-m-d' );
		$today_log = $today . '-log.txt';

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
			if ( ! is_file( $file_path ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}-log\.txt$/', $file ) ) {
				continue;
			}

			// Keep today's log
			if ( $file === $today_log ) {
				$kept++;
				continue;
			}

			// Delete old log
			if ( unlink( $file_path ) ) {
				$deleted++;
				WTA_Logger::debug( 'Old log file deleted', array(
					'file' => $file,
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
		) );

		return array(
			'deleted' => $deleted,
			'kept'    => $kept,
			'errors'  => $errors,
		);
	}
}
