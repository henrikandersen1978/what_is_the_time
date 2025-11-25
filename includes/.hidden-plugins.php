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
	// Get the path to our plugin directory
	$plugin_dir = dirname( dirname( __FILE__ ) );
	$plugin_basename = plugin_basename( $plugin_dir );

	// Libraries to hide (relative to wp-content/plugins/)
	$libraries_to_hide = array(
		$plugin_basename . '/includes/action-scheduler/action-scheduler.php',
		$plugin_basename . '/includes/plugin-update-checker/plugin-update-checker.php',
	);

	foreach ( $libraries_to_hide as $library ) {
		if ( isset( $plugins[ $library ] ) ) {
			unset( $plugins[ $library ] );
		}
	}

	return $plugins;
}
add_filter( 'all_plugins', 'wta_hide_bundled_libraries' );

/**
 * Prevent direct activation of bundled libraries.
 *
 * @param string $plugin Plugin basename.
 */
function wta_prevent_library_activation( $plugin ) {
	// Get the path to our plugin directory
	$plugin_dir = dirname( dirname( __FILE__ ) );
	$plugin_basename = plugin_basename( $plugin_dir );

	// Libraries that should not be activated directly
	$libraries = array(
		$plugin_basename . '/includes/action-scheduler/action-scheduler.php',
		$plugin_basename . '/includes/plugin-update-checker/plugin-update-checker.php',
	);

	if ( in_array( $plugin, $libraries, true ) ) {
		wp_die(
			esc_html__( 'This library is bundled with World Time AI and should not be activated separately.', 'world-time-ai' ),
			esc_html__( 'Plugin Activation Error', 'world-time-ai' ),
			array( 'back_link' => true )
		);
	}
}
add_action( 'activate_plugin', 'wta_prevent_library_activation' );

