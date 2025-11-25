<?php
/**
 * Cron manager for scheduling and managing cron jobs.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/cron
 */

/**
 * Cron manager class.
 *
 * @since 1.0.0
 */
class WTA_Cron_Manager {

	/**
	 * Add custom cron schedules.
	 *
	 * @since 1.0.0
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_custom_schedules( $schedules ) {
		// Add 5-minute schedule
		$schedules['wta_five_minutes'] = array(
			'interval' => 300, // 5 minutes in seconds
			'display'  => __( 'Every 5 Minutes', WTA_TEXT_DOMAIN ),
		);

		return $schedules;
	}

	/**
	 * Get all cron jobs status.
	 *
	 * @since 1.0.0
	 * @return array Array of cron job statuses.
	 */
	public static function get_cron_status() {
		$events = array(
			'world_time_import_structure'      => __( 'Import Structure', WTA_TEXT_DOMAIN ),
			'world_time_resolve_timezones'     => __( 'Resolve Timezones', WTA_TEXT_DOMAIN ),
			'world_time_generate_ai_content'   => __( 'Generate AI Content', WTA_TEXT_DOMAIN ),
		);

		$status = array();

		foreach ( $events as $hook => $label ) {
			$next_run = wp_next_scheduled( $hook );
			
			$status[ $hook ] = array(
				'label'    => $label,
				'enabled'  => $next_run !== false,
				'next_run' => $next_run,
			);
		}

		return $status;
	}

	/**
	 * Manually trigger a cron job.
	 *
	 * @since 1.0.0
	 * @param string $hook Cron hook name.
	 * @return bool True on success.
	 */
	public static function trigger_cron( $hook ) {
		$valid_hooks = array(
			'world_time_import_structure',
			'world_time_resolve_timezones',
			'world_time_generate_ai_content',
		);

		if ( ! in_array( $hook, $valid_hooks, true ) ) {
			return false;
		}

		do_action( $hook );
		return true;
	}

	/**
	 * Pause a cron job.
	 *
	 * @since 1.0.0
	 * @param string $hook Cron hook name.
	 * @return bool True on success.
	 */
	public static function pause_cron( $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
			return true;
		}
		return false;
	}

	/**
	 * Resume a cron job.
	 *
	 * @since 1.0.0
	 * @param string $hook Cron hook name.
	 * @return bool True on success.
	 */
	public static function resume_cron( $hook ) {
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), 'wta_five_minutes', $hook );
			return true;
		}
		return false;
	}
}





