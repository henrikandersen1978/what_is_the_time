<?php
/**
 * Log file cleanup utility.
 *
 * Automatically deletes old log files to prevent disk space issues.
 * Runs daily at 04:00 via Action Scheduler.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 * @since      2.35.7
 */

class WTA_Log_Cleaner {

	/**
	 * Cleanup old log files.
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

		$today = gmdate( 'Y-m-d' );
		$today_log = $today . '-log.txt';
		
		$stats = array(
			'deleted' => 0,
			'kept'    => 0,
			'errors'  => 0,
		);

		// Scan logs directory
		$files = scandir( $logs_dir );
		
		foreach ( $files as $file ) {
			// Skip . and ..
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$file_path = $logs_dir . '/' . $file;

			// Skip non-files
			if ( ! is_file( $file_path ) ) {
				continue;
			}

			// Skip .htaccess
			if ( '.htaccess' === $file ) {
				$stats['kept']++;
				continue;
			}

			// Keep today's log
			if ( $file === $today_log ) {
				$stats['kept']++;
				continue;
			}

			// Delete old log files
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}-log\.txt$/', $file ) ) {
				if ( unlink( $file_path ) ) {
					$stats['deleted']++;
				} else {
					$stats['errors']++;
					WTA_Logger::error( 'Failed to delete old log file', array(
						'file' => $file,
					) );
				}
			}
		}

		WTA_Logger::info( 'Log cleanup completed', $stats );

		return $stats;
	}

	/**
	 * Get logs directory size.
	 *
	 * Returns the total size of all log files in bytes.
	 *
	 * @since    2.35.7
	 * @return   int  Total size in bytes.
	 */
	public static function get_logs_size() {
		$upload_dir = wp_upload_dir();
		$logs_dir = $upload_dir['basedir'] . '/world-time-ai-data/logs';

		if ( ! file_exists( $logs_dir ) || ! is_dir( $logs_dir ) ) {
			return 0;
		}

		$total_size = 0;
		$files = scandir( $logs_dir );

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$file_path = $logs_dir . '/' . $file;
			if ( is_file( $file_path ) ) {
				$total_size += filesize( $file_path );
			}
		}

		return $total_size;
	}

	/**
	 * Get list of log files.
	 *
	 * Returns array of log files with metadata.
	 *
	 * @since    2.35.7
	 * @return   array  Array of log file info.
	 */
	public static function get_log_files() {
		$upload_dir = wp_upload_dir();
		$logs_dir = $upload_dir['basedir'] . '/world-time-ai-data/logs';

		if ( ! file_exists( $logs_dir ) || ! is_dir( $logs_dir ) ) {
			return array();
		}

		$log_files = array();
		$files = scandir( $logs_dir );

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file || '.htaccess' === $file ) {
				continue;
			}

			$file_path = $logs_dir . '/' . $file;
			if ( is_file( $file_path ) && preg_match( '/^\d{4}-\d{2}-\d{2}-log\.txt$/', $file ) ) {
				$log_files[] = array(
					'name'     => $file,
					'size'     => filesize( $file_path ),
					'modified' => filemtime( $file_path ),
				);
			}
		}

		// Sort by date (newest first)
		usort( $log_files, function( $a, $b ) {
			return $b['modified'] - $a['modified'];
		} );

		return $log_files;
	}
}

