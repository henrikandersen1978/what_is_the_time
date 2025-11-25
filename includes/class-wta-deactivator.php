<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 */
class WTA_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clears scheduled cron events but keeps data intact.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		self::clear_scheduled_events();
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Clear all scheduled cron events.
	 *
	 * @since 1.0.0
	 */
	private static function clear_scheduled_events() {
		$events = array(
			'world_time_import_structure',
			'world_time_resolve_timezones',
			'world_time_generate_ai_content',
		);

		foreach ( $events as $event ) {
			$timestamp = wp_next_scheduled( $event );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $event );
			}
		}

		// Clear all instances of our custom cron schedule
		wp_clear_scheduled_hook( 'world_time_import_structure' );
		wp_clear_scheduled_hook( 'world_time_resolve_timezones' );
		wp_clear_scheduled_hook( 'world_time_generate_ai_content' );
	}
}





