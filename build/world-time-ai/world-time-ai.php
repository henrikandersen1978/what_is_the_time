<?php
/**
 * World Time AI
 *
 * @package           WorldTimeAI
 * @author            World Time AI Team
 * @copyright         2025 World Time AI
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       World Time AI
 * Plugin URI:        https://example.com/world-time-ai
 * Description:       Import and display current local time for cities worldwide with AI-generated content. Hierarchical location pages with timezone support.
 * Version:           0.3.15
 * Requires at least: 6.8
 * Requires PHP:      8.4
 * Author:            World Time AI Team
 * Author URI:        https://example.com
 * Text Domain:       world-time-ai
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'WTA_VERSION', '0.3.15' );

/**
 * GitHub repository for updates.
 */
define( 'WTA_GITHUB_REPO', 'henrikandersen1978/what_is_the_time' );

/**
 * Plugin directory path.
 */
define( 'WTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'WTA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'WTA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin text domain.
 */
define( 'WTA_TEXT_DOMAIN', 'world-time-ai' );

/**
 * Custom post type name.
 */
define( 'WTA_POST_TYPE', 'world_time_location' );

/**
 * Queue table name (without prefix).
 */
define( 'WTA_QUEUE_TABLE', 'world_time_queue' );

/**
 * The code that runs during plugin activation.
 */
function activate_world_time_ai() {
	require_once WTA_PLUGIN_DIR . 'includes/class-wta-activator.php';
	WTA_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_world_time_ai() {
	require_once WTA_PLUGIN_DIR . 'includes/class-wta-deactivator.php';
	WTA_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_world_time_ai' );
register_deactivation_hook( __FILE__, 'deactivate_world_time_ai' );

/**
 * The core plugin class.
 */
require_once WTA_PLUGIN_DIR . 'includes/class-wta-core.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function run_world_time_ai() {
	$plugin = new WTA_Core();
	$plugin->run();
}

run_world_time_ai();

/**
 * Initialize Plugin Update Checker for GitHub updates.
 */
add_action( 'plugins_loaded', function() {
	$puc_file = WTA_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
	if ( file_exists( $puc_file ) ) {
		require $puc_file;
		
		$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/' . WTA_GITHUB_REPO,
			__FILE__,
			'world-time-ai'
		);
		
		// Enable release assets - this will look for a zip file in the GitHub release
		$updateChecker->getVcsApi()->enableReleaseAssets('world-time-ai.zip');
	}
}, 20 );

/**
 * Check for plugin upgrades and handle version transitions.
 * This runs on every load to ensure settings are preserved during updates.
 */
add_action( 'plugins_loaded', function() {
	$current_version = get_option( 'wta_plugin_version', '0.0.0' );
	
	// If version is different, we're upgrading (or fresh install)
	if ( version_compare( $current_version, WTA_VERSION, '<' ) ) {
		
		// Check if this is an upgrade (not fresh install)
		$is_upgrade = ( $current_version !== '0.0.0' );
		
		// Ensure critical settings exist (without overwriting)
		require_once WTA_PLUGIN_DIR . 'includes/class-wta-activator.php';
		
		// Run activation to ensure tables and missing options are created
		// Note: add_option() won't overwrite existing values
		WTA_Activator::activate();
		
		// Update the stored version
		update_option( 'wta_plugin_version', WTA_VERSION );
		
		// Set a transient to show upgrade notice to admin
		if ( $is_upgrade ) {
			set_transient( 'wta_upgraded_notice', array(
				'from' => $current_version,
				'to' => WTA_VERSION,
			), 3600 ); // Show for 1 hour
		}
		
		// Log the upgrade
		if ( class_exists( 'WTA_Logger' ) ) {
			WTA_Logger::info( "Plugin upgraded from {$current_version} to " . WTA_VERSION );
		}
	}
}, 30 );

/**
 * Show admin notice after successful upgrade.
 */
add_action( 'admin_notices', function() {
	$upgrade_info = get_transient( 'wta_upgraded_notice' );
	
	if ( $upgrade_info && current_user_can( 'manage_options' ) ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong>World Time AI</strong> blev opdateret fra version <?php echo esc_html( $upgrade_info['from'] ); ?> 
				til <?php echo esc_html( $upgrade_info['to'] ); ?>. 
				✅ Alle dine indstillinger er bevaret!
			</p>
		</div>
		<?php
		// Delete transient after showing once
		delete_transient( 'wta_upgraded_notice' );
	}
} );
