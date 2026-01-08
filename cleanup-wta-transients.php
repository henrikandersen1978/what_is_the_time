<?php
/**
 * WTA Transient Cleanup - Chunked Batch Deletion
 * 
 * Run via cron every minute:
 * * * * * * php /path/to/wp-content/plugins/world-time-ai/cleanup-wta-transients.php
 * 
 * Deletes WTA transients in safe batches to avoid locking wp_options table.
 * Automatically stops when no more transients found.
 * 
 * @package WorldTimeAI
 * @version 1.0
 * @since   3.0.80
 */

// Prevent direct browser access
if ( php_sapi_name() !== 'cli' && ! defined( 'DOING_CRON' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	die( 'This script can only be run via CLI or cron.' );
}

// Load WordPress
$wp_load_paths = array(
	__DIR__ . '/../../../wp-load.php',  // Standard plugin location
	__DIR__ . '/../../../../wp-load.php', // Alternative
);

foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once( $path );
		break;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Could not locate wp-load.php. Please adjust the path in this script.' );
}

// Configuration
$batch_size = 2000;  // Rows to delete per run (safe for every minute execution)
$log_file = __DIR__ . '/wta-cleanup.log';

// Start cleanup
$start_time = microtime( true );
global $wpdb;

// Count total before deletion
$total_before = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$wpdb->options} 
	 WHERE option_name LIKE '_transient_wta_%' 
		OR option_name LIKE '_transient_timeout_wta_%'"
);

if ( $total_before == 0 ) {
	// Nothing to delete - cleanup complete!
	log_message( $log_file, "✓ Cleanup complete - no more WTA transients found." );
	exit( 0 );
}

// Delete batch
$deleted = $wpdb->query( 
	$wpdb->prepare( 
		"DELETE FROM {$wpdb->options} 
		 WHERE option_name LIKE %s 
			OR option_name LIKE %s
		 LIMIT %d",
		'_transient_wta_%',
		'_transient_timeout_wta_%',
		$batch_size
	)
);

// Count remaining
$total_after = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$wpdb->options} 
	 WHERE option_name LIKE '_transient_wta_%' 
		OR option_name LIKE '_transient_timeout_wta_%'"
);

// Calculate progress
$execution_time = round( microtime( true ) - $start_time, 3 );
$total_deleted = $total_before - $total_after;
$progress_percent = $total_before > 0 ? round( ( $total_deleted / $total_before ) * 100, 2 ) : 100;

// Estimate completion time
$batches_remaining = ceil( $total_after / $batch_size );
$minutes_remaining = $batches_remaining; // Since cron runs every minute

// Log results
$message = sprintf(
	"[%s] Deleted: %d rows | Remaining: %s | Progress: %s%% | Time: %ss | ETA: ~%d min",
	date( 'Y-m-d H:i:s' ),
	$deleted,
	number_format( $total_after ),
	$progress_percent,
	$execution_time,
	$minutes_remaining
);

log_message( $log_file, $message );

// Also output to CLI
if ( php_sapi_name() === 'cli' ) {
	echo $message . "\n";
}

// Check database size (optional - every 10th run to avoid overhead)
if ( rand( 1, 10 ) === 1 ) {
	$table_size = $wpdb->get_var(
		"SELECT ROUND((data_length + index_length)/1024/1024, 2) 
		 FROM information_schema.tables 
		 WHERE table_schema = DATABASE() 
		   AND table_name = '{$wpdb->options}'"
	);
	
	if ( $table_size ) {
		log_message( $log_file, "  └─ wp_options table size: {$table_size} MB" );
	}
}

// Clear WordPress object cache to free memory
wp_cache_flush();

exit( 0 );

/**
 * Log message to file
 * 
 * @param string $file    Log file path.
 * @param string $message Message to log.
 */
function log_message( $file, $message ) {
	file_put_contents( $file, $message . "\n", FILE_APPEND );
}

