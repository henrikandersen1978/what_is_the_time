<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes
 */

class WTA_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * - Unschedule Action Scheduler recurring actions
	 * - Clear any transients
	 *
	 * Note: We do NOT delete data or tables on deactivation.
	 * That only happens on uninstall (uninstall.php).
	 *
	 * @since 2.0.0
	 */
	public static function deactivate() {
		// Unschedule all Action Scheduler actions
		self::unschedule_actions();

		// Clear transients
		self::clear_transients();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Unschedule all Action Scheduler actions.
	 *
	 * @since 2.0.0
	 */
	private static function unschedule_actions() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( 'wta_process_structure', array(), 'world-time-ai' );
		as_unschedule_all_actions( 'wta_process_timezone', array(), 'world-time-ai' );
		as_unschedule_all_actions( 'wta_process_ai_content', array(), 'world-time-ai' );
	}

	/**
	 * Clear all plugin transients.
	 *
	 * @since 2.0.0
	 */
	private static function clear_transients() {
		global $wpdb;

		// Delete all transients starting with 'wta_'
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wta_%' OR option_name LIKE '_transient_timeout_wta_%'" );
	}
}

