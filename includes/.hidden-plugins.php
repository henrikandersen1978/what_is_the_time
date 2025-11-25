<?php
/**
 * Hide bundled libraries from WordPress plugin list
 *
 * This prevents Action Scheduler and Plugin Update Checker from appearing
 * as separate plugins in WordPress admin.
 *
 * @package WorldTimeAI
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Hide bundled libraries from plugin list.
 *
 * @param array $plugins Array of plugins.
 * @return array Filtered array of plugins.
 */
function wta_hide_bundled_libraries( $plugins ) {
	// Try different variations of the plugin basename
	$possible_basenames = array(
		'world-time-ai',
		'world-time-ai-2',
		'world-time-ai-3',
	);

	foreach ( $possible_basenames as $basename ) {
		$libraries_to_hide = array(
			$basename . '/includes/action-scheduler/action-scheduler.php',
			$basename . '/includes/plugin-update-checker/plugin-update-checker.php',
		);

		foreach ( $libraries_to_hide as $library ) {
			if ( isset( $plugins[ $library ] ) ) {
				unset( $plugins[ $library ] );
			}
		}
	}

	return $plugins;
}
add_filter( 'all_plugins', 'wta_hide_bundled_libraries', 99 );

/**
 * Prevent direct activation of bundled libraries.
 *
 * @param string $plugin Plugin basename.
 */
function wta_prevent_library_activation( $plugin ) {
	// Check if this is an attempt to activate a bundled library
	if ( strpos( $plugin, '/includes/action-scheduler/action-scheduler.php' ) !== false ||
		 strpos( $plugin, '/includes/plugin-update-checker/plugin-update-checker.php' ) !== false ) {
		wp_die(
			esc_html__( 'This library is bundled with World Time AI and should not be activated separately. Please activate "World Time AI" instead.', 'world-time-ai' ),
			esc_html__( 'Plugin Activation Error', 'world-time-ai' ),
			array( 'back_link' => true )
		);
	}
}
add_action( 'activate_plugin', 'wta_prevent_library_activation', 1 );

