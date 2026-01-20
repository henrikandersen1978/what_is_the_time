<?php
/**
 * EMERGENCY DATABASE CLEANUP
 * 
 * KØRER DETTE SCRIPT NU FOR AT FRIGØRE DISKPLADS!
 * 
 * Upload til: /wp-content/plugins/world-time-ai/
 * Kør via: https://testsite2.pilanto.dk/wp-content/plugins/world-time-ai/emergency-database-cleanup.php
 * 
 * Eller via SSH/Terminal:
 * cd /path/to/wordpress
 * php wp-content/plugins/world-time-ai/emergency-database-cleanup.php
 * 
 * @package WorldTimeAI
 * @version 1.0
 */

// Allow HTTP access
define( 'DOING_WTA_CLEANUP', true );

// Load WordPress
$wp_load_paths = array(
	__DIR__ . '/../../../wp-load.php',
	__DIR__ . '/../../../../wp-load.php',
);

foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once( $path );
		break;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	http_response_code( 500 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	die( 'ERROR: Could not locate wp-load.php' );
}

header( 'Content-Type: text/plain; charset=utf-8' );
echo "═══════════════════════════════════════════════════════════\n";
echo "  EMERGENCY DATABASE CLEANUP - World Time AI\n";
echo "═══════════════════════════════════════════════════════════\n\n";

global $wpdb;
$start_time = microtime( true );

// ============================================================================
// STEP 1: Check disk/database size BEFORE cleanup
// ============================================================================
echo "📊 STEP 1: Checking current database size...\n";
echo "─────────────────────────────────────────────────────────────\n";

$tables_to_check = array(
	$wpdb->prefix . 'actionscheduler_actions',
	$wpdb->prefix . 'actionscheduler_logs',
	$wpdb->prefix . 'options',
	$wpdb->prefix . 'postmeta',
);

$total_size_before = 0;
foreach ( $tables_to_check as $table ) {
	$size = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT 
				table_name,
				ROUND((data_length + index_length)/1024/1024, 2) as size_mb,
				table_rows
			FROM information_schema.tables 
			WHERE table_schema = DATABASE() 
			AND table_name = %s",
			$table
		)
	);
	
	if ( $size ) {
		$total_size_before += $size->size_mb;
		printf(
			"  %-40s: %8s MB (%s rows)\n",
			$table,
			number_format( $size->size_mb, 2 ),
			number_format( $size->table_rows )
		);
	}
}

printf( "\n  TOTAL SIZE BEFORE: %.2f MB\n\n", $total_size_before );

// ============================================================================
// STEP 2: Clean up Action Scheduler logs (BIGGEST SPACE SAVER!)
// ============================================================================
echo "🗑️  STEP 2: Cleaning Action Scheduler logs...\n";
echo "─────────────────────────────────────────────────────────────\n";

// Count logs before
$logs_before = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_logs"
);
echo "  Logs before cleanup: " . number_format( $logs_before ) . "\n";

// Delete logs older than 7 days (keep recent for debugging)
$logs_deleted = $wpdb->query(
	"DELETE FROM {$wpdb->prefix}actionscheduler_logs 
	 WHERE log_date_gmt < DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
echo "  ✓ Deleted logs older than 7 days: " . number_format( $logs_deleted ) . "\n";

// Count logs after
$logs_after = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_logs"
);
echo "  Logs remaining: " . number_format( $logs_after ) . "\n\n";

// ============================================================================
// STEP 3: Clean up completed/failed Action Scheduler actions
// ============================================================================
echo "🗑️  STEP 3: Cleaning completed Action Scheduler actions...\n";
echo "─────────────────────────────────────────────────────────────\n";

// Count actions before
$actions_before = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions"
);
echo "  Actions before cleanup: " . number_format( $actions_before ) . "\n";

// Delete completed actions older than 7 days
$completed_deleted = $wpdb->query(
	"DELETE FROM {$wpdb->prefix}actionscheduler_actions 
	 WHERE status = 'complete' 
	 AND scheduled_date_gmt < DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
echo "  ✓ Deleted completed actions (>7 days): " . number_format( $completed_deleted ) . "\n";

// Delete failed actions older than 30 days (keep failed longer for debugging)
$failed_deleted = $wpdb->query(
	"DELETE FROM {$wpdb->prefix}actionscheduler_actions 
	 WHERE status = 'failed' 
	 AND scheduled_date_gmt < DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
echo "  ✓ Deleted failed actions (>30 days): " . number_format( $failed_deleted ) . "\n";

// Count actions after
$actions_after = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions"
);
echo "  Actions remaining: " . number_format( $actions_after ) . "\n\n";

// ============================================================================
// STEP 4: Clean up WTA transients
// ============================================================================
echo "🗑️  STEP 4: Cleaning WTA transients...\n";
echo "─────────────────────────────────────────────────────────────\n";

// Count transients before
$transients_before = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$wpdb->options} 
	 WHERE option_name LIKE '_transient_wta_%' 
		OR option_name LIKE '_transient_timeout_wta_%'"
);
echo "  Transients before cleanup: " . number_format( $transients_before ) . "\n";

// Delete WTA transients (batch of 10,000 to avoid timeout)
$transients_deleted = $wpdb->query( 
	"DELETE FROM {$wpdb->options} 
	 WHERE option_name LIKE '_transient_wta_%' 
		OR option_name LIKE '_transient_timeout_wta_%'
	 LIMIT 10000"
);
echo "  ✓ Deleted WTA transients: " . number_format( $transients_deleted ) . "\n";

// Count transients after
$transients_after = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$wpdb->options} 
	 WHERE option_name LIKE '_transient_wta_%' 
		OR option_name LIKE '_transient_timeout_wta_%'"
);
echo "  Transients remaining: " . number_format( $transients_after ) . "\n";

if ( $transients_after > 0 ) {
	echo "  ⚠️  WARNING: " . number_format( $transients_after ) . " transients still remain!\n";
	echo "     Run cleanup-wta-transients.php flere gange for at fjerne resten.\n";
}
echo "\n";

// ============================================================================
// STEP 5: Clean up expired transients (ALL plugins)
// ============================================================================
echo "🗑️  STEP 5: Cleaning expired transients (all plugins)...\n";
echo "─────────────────────────────────────────────────────────────\n";

// Delete expired transients
$expired_deleted = $wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} 
		 WHERE option_name LIKE %s 
		 AND option_value < %d
		 LIMIT 10000",
		'_transient_timeout_%',
		time()
	)
);
echo "  ✓ Deleted expired transient timeouts: " . number_format( $expired_deleted ) . "\n";

// Get the transient names and delete them
if ( $expired_deleted > 0 ) {
	$expired_deleted_2 = $wpdb->query(
		"DELETE FROM {$wpdb->options} 
		 WHERE option_name LIKE '_transient_%'
		 AND option_name NOT IN (
			 SELECT REPLACE(option_name, '_transient_timeout_', '_transient_')
			 FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_timeout_%'
		 )
		 LIMIT 10000"
	);
	echo "  ✓ Deleted orphaned transients: " . number_format( $expired_deleted_2 ) . "\n";
}
echo "\n";

// ============================================================================
// STEP 6: Optimize tables
// ============================================================================
echo "⚡ STEP 6: Optimizing tables (reclaiming disk space)...\n";
echo "─────────────────────────────────────────────────────────────\n";

foreach ( $tables_to_check as $table ) {
	echo "  Optimizing: {$table}... ";
	$wpdb->query( "OPTIMIZE TABLE {$table}" );
	echo "✓\n";
}
echo "\n";

// ============================================================================
// STEP 7: Check disk/database size AFTER cleanup
// ============================================================================
echo "📊 STEP 7: Checking database size after cleanup...\n";
echo "─────────────────────────────────────────────────────────────\n";

$total_size_after = 0;
foreach ( $tables_to_check as $table ) {
	$size = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT 
				table_name,
				ROUND((data_length + index_length)/1024/1024, 2) as size_mb,
				table_rows
			FROM information_schema.tables 
			WHERE table_schema = DATABASE() 
			AND table_name = %s",
			$table
		)
	);
	
	if ( $size ) {
		$total_size_after += $size->size_mb;
		printf(
			"  %-40s: %8s MB (%s rows)\n",
			$table,
			number_format( $size->size_mb, 2 ),
			number_format( $size->table_rows )
		);
	}
}

printf( "\n  TOTAL SIZE AFTER: %.2f MB\n", $total_size_after );

// ============================================================================
// SUMMARY
// ============================================================================
$execution_time = round( microtime( true ) - $start_time, 2 );
$space_freed = $total_size_before - $total_size_after;
$space_freed_percent = $total_size_before > 0 
	? round( ( $space_freed / $total_size_before ) * 100, 2 )
	: 0;

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  CLEANUP SUMMARY\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  🗑️  Action Scheduler logs deleted: " . number_format( $logs_deleted ) . "\n";
echo "  🗑️  Completed actions deleted: " . number_format( $completed_deleted ) . "\n";
echo "  🗑️  Failed actions deleted: " . number_format( $failed_deleted ) . "\n";
echo "  🗑️  WTA transients deleted: " . number_format( $transients_deleted ) . "\n";
echo "  🗑️  Expired transients deleted: " . number_format( $expired_deleted ) . "\n";
echo "\n";
printf( "  💾 Space freed: %.2f MB (%.2f%%)\n", $space_freed, $space_freed_percent );
printf( "  ⏱️  Execution time: %s seconds\n", $execution_time );
echo "\n";

if ( $transients_after > 0 ) {
	echo "  ⚠️  NEXT STEPS:\n";
	echo "  - Kør cleanup-wta-transients.php for at fjerne resterende " . number_format( $transients_after ) . " transients\n";
	echo "  - Setup EasyCron til at køre cleanup hver 1 minut indtil alle er væk\n";
	echo "\n";
}

echo "  ✅ CLEANUP COMPLETE!\n";
echo "═══════════════════════════════════════════════════════════\n";

// Clear WordPress object cache
wp_cache_flush();

exit( 0 );
