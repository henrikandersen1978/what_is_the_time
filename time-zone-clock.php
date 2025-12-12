<?php
/**
 * World Time AI
 *
 * @package           WorldTimeAI
 * @author            Henrik Andersen
 * @copyright         2025 Henrik Andersen
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       World Time AI
 * Plugin URI:        https://github.com/henrikandersen1978/what_is_the_time
 * Description:       Display current local time worldwide with AI-generated Danish content and hierarchical location pages.
 * Version:           2.35.13
 * Requires at least: 6.8
 * Requires PHP:      8.4
 * Author:            Henrik Andersen
 * Author URI:        https://github.com/henrikandersen1978
 * Text Domain:       world-time-ai
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
define( 'WTA_VERSION', '2.35.13' );

/**
 * Plugin directory path.
 */
define( 'WTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'WTA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Custom post type name.
 */
define( 'WTA_POST_TYPE', 'wta_location' );

/**
 * Text domain for translations.
 */
define( 'WTA_TEXT_DOMAIN', 'world-time-ai' );

/**
 * Queue table name (without prefix).
 */
define( 'WTA_QUEUE_TABLE', 'world_time_queue' );

/**
 * The code that runs during plugin activation.
 */
function activate_world_time_ai() {
	// Load Action Scheduler before activation if not already loaded
	if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
		$action_scheduler_path = WTA_PLUGIN_DIR . 'includes/action-scheduler/action-scheduler.php';
		if ( file_exists( $action_scheduler_path ) ) {
			require_once $action_scheduler_path;
		}
	}

	try {
		require_once WTA_PLUGIN_DIR . 'includes/class-wta-activator.php';
		WTA_Activator::activate();
	} catch ( Exception $e ) {
		wp_die(
			'World Time AI Activation Error: ' . esc_html( $e->getMessage() ) . '<br><br>' .
			'File: ' . esc_html( $e->getFile() ) . '<br>' .
			'Line: ' . esc_html( $e->getLine() ) . '<br><br>' .
			'Please check your PHP error log for more details.',
			'Plugin Activation Error',
			array( 'back_link' => true )
		);
	}
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
 * Check for plugin upgrades and flush rewrite rules if needed.
 */
function wta_check_plugin_upgrade() {
	$current_version = get_option( 'wta_plugin_version' );
	
	// If version has changed, flush rewrite rules
	if ( $current_version !== WTA_VERSION ) {
		// Flush rewrite rules to ensure custom rules are registered
		flush_rewrite_rules( false );
		
		// Update version in database
		update_option( 'wta_plugin_version', WTA_VERSION );
		
		// Log the upgrade
		if ( class_exists( 'WTA_Logger' ) ) {
			WTA_Logger::info( 'Plugin upgraded, rewrite rules flushed', array(
				'old_version' => $current_version,
				'new_version' => WTA_VERSION,
			) );
		}
	}
}
add_action( 'admin_init', 'wta_check_plugin_upgrade' );

/**
 * The core plugin class.
 */
require WTA_PLUGIN_DIR . 'includes/class-wta-core.php';

/**
 * High-Resource Server Optimization (v2.34.25)
 * 
 * Optimize Action Scheduler for servers with 16+ CPU cores and 32GB+ RAM.
 * These settings enable faster concurrent processing of large queues.
 * 
 * CRITICAL: Only use on high-resource servers!
 * For shared hosting (2-4 CPU, 4GB RAM), use v2.34.23 with default settings.
 * 
 * @since 2.34.25
 */
function wta_optimize_action_scheduler() {
	// ==========================================
	// OPTIMIZED FOR 2 CONCURRENT RUNNERS (v2.35.13)
	// Action Scheduler's concurrent_batches is GLOBAL across all runners
	// Reality: ~2 runners active (1 from WP-Cron + 1 from occasional async)
	// Strategy: Optimize these 2 runners for maximum throughput within API limits
	// ==========================================
	
	// ==========================================
	// CONCURRENT BATCHES - Reflects Reality
	// ==========================================
	// Testing showed: Only 2 runners ever active, regardless of concurrent_batches setting
	// concurrent_batches is a GLOBAL limit, not per-runner
	// So we set this to 2 to match actual behavior and reduce overhead
	
	add_filter( 'action_scheduler_queue_runner_concurrent_batches', function( $batches ) {
		return 2; // Realistic: WP-Cron + occasional async
	}, 999 );
	
	// ==========================================
	// BATCH SIZE - Maximize throughput per runner
	// ==========================================
	// With 2 runners × 300 batch size = 600 actions per cycle
	// Within 3-minute time limit, respecting API rate limits:
	// - Wikidata: 5 req/s per processor (0.2s delay) = safe under 200 req/s limit
	// - TimeZoneDB: 0.4 req/s per processor (2.5s avg) = safe under 1 req/s limit  
	// - OpenAI: Test mode = 0 requests, AI mode would need monitoring
	
	add_filter( 'action_scheduler_queue_runner_batch_size', function( $size ) {
		return 300; // 12× default, optimized for 3-min time limit
	}, 999 );
	
	// ==========================================
	// TIME LIMIT - Allow longer processing
	// ==========================================
	// 180s (3 minutes) gives adequate time for:
	// - Structure: 300 items × 0.2s = 60s
	// - Timezone: 300 items × 2.5s = 750s (but only 8-50 items per batch typically)
	// - AI: 300 items × 2s = 600s (but test mode is faster)
	
	add_filter( 'action_scheduler_queue_runner_time_limit', function( $limit ) {
		return 180; // 3 minutes per runner
	}, 999 );
	
	// ==========================================
	// TIMEOUT & FAILURE PERIODS
	// ==========================================
	// Longer periods prevent premature action resets during processing
	
	add_filter( 'action_scheduler_timeout_period', function( $timeout ) {
		return 900; // 15 minutes
	}, 999 );
	
	add_filter( 'action_scheduler_failure_period', function( $timeout ) {
		return 900; // 15 minutes
	}, 999 );
}

// Hook early to ensure filters are applied before Action Scheduler runs
add_action( 'plugins_loaded', 'wta_optimize_action_scheduler', 1 );

/**
 * DIRECT post type registration for debugging.
 * Register BEFORE anything else to ensure it works.
 * 
 * STRATEGY: Use a short dummy slug ('l') + hierarchical rewrite
 * WordPress generates: /l/europa/danmark/
 * Our filter removes '/l/' → /europa/danmark/
 * 
 * This is the ONLY reliable way to avoid query string URLs in WordPress.
 */
add_action( 'init', function() {
	register_post_type( 'wta_location', array(
		'labels' => array(
			'name' => 'Locations',
			'singular_name' => 'Location',
		),
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'query_var' => true,
		'rewrite' => array(
			'slug'         => 'l',  // Short dummy slug - will be removed by filter
			'hierarchical' => true,
			'with_front'   => false,
		),
		'has_archive' => false,
		'hierarchical' => true,
		'supports' => array( 'title', 'editor', 'author', 'page-attributes' ),
		'show_in_rest' => true,
	) );
}, 0 );

/**
 * Begins execution of the plugin.
 */
function run_world_time_ai() {
	$plugin = new WTA_Core();
	$plugin->run();
}
run_world_time_ai();

/**
 * Plugin Update Checker
 * Only load if the library exists - automatic updates require this.
 */
$puc_path = WTA_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $puc_path ) ) {
	require $puc_path;

	// Use fully qualified class name to avoid "use" statement in conditional block
	$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/henrikandersen1978/what_is_the_time',
		__FILE__,
		'time-zone-clock'  // Must match GitHub release asset filename (time-zone-clock-X.Y.Z.zip)
	);

	$updateChecker->getVcsApi()->enableReleaseAssets();
} else {
	// Show admin notice if Plugin Update Checker is missing
	add_action( 'admin_notices', function() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'World Time AI Warning:', 'world-time-ai' ); ?></strong>
				<?php
				printf(
					/* translators: %s: URL to setup instructions */
					esc_html__( 'Plugin Update Checker library not found. Automatic updates will not work. See %s for installation instructions.', 'world-time-ai' ),
					'<a href="https://github.com/henrikandersen1978/what_is_the_time/blob/main/EXTERNAL-LIBRARIES.md" target="_blank">EXTERNAL-LIBRARIES.md</a>'
				);
				?>
			</p>
		</div>
		<?php
	});
}
