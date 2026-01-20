<?php
/**
 * Database Maintenance - Leverage Action Scheduler's built-in cleanup
 * 
 * Action Scheduler has its own QueueCleaner that deletes:
 * - Complete and canceled actions
 * - Logs for deleted actions (automatically via 'action_scheduler_deleted_action' hook)
 * 
 * We just need to:
 * 1. Set aggressive retention period (1 hour instead of default 31 days)
 * 2. Disable revisions for wta_location posts (prevents GB bloat)
 * 3. OPTIMIZE tables periodically to reclaim disk space
 * 
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 * @since      3.4.8
 */

class WTA_Database_Maintenance {

	/**
	 * Set aggressive retention period for Action Scheduler cleanup.
	 * 
	 * Action Scheduler's built-in QueueCleaner automatically:
	 * - Deletes complete/canceled actions older than retention period
	 * - Deletes logs for those actions (via action_scheduler_deleted_action hook)
	 * 
	 * Default: 31 days (2,678,400 seconds)
	 * Our setting: 10 minutes (600 seconds) for fast 24-hour imports
	 * 
	 * @since    3.4.8
	 * @since    3.4.9  Reduced from 1 hour to 10 minutes for better backend performance.
	 * @param    int $seconds Retention period in seconds.
	 * @return   int          10 minutes in seconds.
	 */
	public static function set_retention_period( $seconds ) {
		// 10 minutes = 600 seconds
		// Actions/logs older than 10 minutes will be deleted automatically
		// Keeps Action Scheduler UI responsive during 24-hour imports
		return 10 * MINUTE_IN_SECONDS;
	}
	
	/**
	 * Increase Action Scheduler cleanup batch size for faster cleanup.
	 * 
	 * Default: 20 actions per cleanup run
	 * Our setting: 500 actions per cleanup run
	 * 
	 * This allows Action Scheduler to delete 500 actions at once instead of 20,
	 * preventing database bloat during high-volume processing.
	 * 
	 * @since    3.4.10
	 * @param    int $batch_size Batch size for cleanup.
	 * @return   int             500 actions per cleanup.
	 */
	public static function set_cleanup_batch_size( $batch_size ) {
		return 500;
	}
	
	/**
	 * Run periodic table optimization.
	 * 
	 * OPTIMIZE reclaims disk space after DELETE operations.
	 * Runs every 6 hours to balance performance vs overhead.
	 * 
	 * Scheduled via Action Scheduler to run every 6 hours.
	 *
	 * @since    3.4.8
	 * @since    3.5.6  Added wp_options optimization (reclaims space from deleted transients).
	 * @return   array  Optimization statistics.
	 */
	public static function run_optimization() {
		global $wpdb;
		
		$stats = array(
			'optimized_tables' => 0,
			'execution_time' => 0,
		);
		
		$start_time = microtime( true );
		
		// OPTIMIZE Action Scheduler tables to reclaim disk space
		$wpdb->query( "OPTIMIZE TABLE {$wpdb->prefix}actionscheduler_logs" );
		$wpdb->query( "OPTIMIZE TABLE {$wpdb->prefix}actionscheduler_actions" );
		
		// v3.5.6: Also optimize wp_options to reclaim space from deleted transients
		// This prevents database bloat after massive transient deletions
		$wpdb->query( "OPTIMIZE TABLE {$wpdb->options}" );
		
		$stats['optimized_tables'] = 3;
		
		$stats['execution_time'] = round( microtime( true ) - $start_time, 3 );
		
		WTA_Logger::info( 'Database optimization completed', $stats );
		
		return $stats;
	}
	
	/**
	 * Disable revisions for wta_location posts.
	 * 
	 * We generate 200k+ posts - revisions would bloat wp_posts to GB.
	 * This filter prevents revision creation entirely for our post type.
	 * 
	 * @since    3.4.8
	 * @param    int     $num  Number of revisions to keep.
	 * @param    WP_Post $post Post object.
	 * @return   int|bool      0 to disable revisions for wta_location.
	 */
	public static function disable_revisions_for_locations( $num, $post ) {
		if ( isset( $post->post_type ) && $post->post_type === 'wta_location' ) {
			return 0; // Disable revisions completely
		}
		return $num;
	}
}
