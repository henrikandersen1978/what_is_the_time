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
 * Version:           2.35.12
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
define( 'WTA_VERSION', '2.35.12' );

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
	// ASYNC MODE DISABLED (v2.35.12)
	// Conflicts with our manual loopback implementation
	// Action Scheduler's async mode doesn't trigger 'action_scheduler_run_queue' hook
	// So our loopback code never runs. We use manual loopback instead.
	// ==========================================
	
	// DISABLED: Action Scheduler's own async mode
	// add_filter( 'action_scheduler_allow_async_request_runner', '__return_true', 999 );
	
	// DISABLED: Async sleep time (not needed with manual loopback)
	// add_filter( 'action_scheduler_async_request_sleep_seconds', function() {
	// 	return 1;
	// }, 999 );
	
	// ==========================================
	// CONFIGURABLE CONCURRENT BATCHES (v2.35.10)
	// Simple setting-based approach - admin can control via backend
	// ==========================================
	
	add_filter( 'action_scheduler_queue_runner_concurrent_batches', function( $batches ) {
		// Get admin-configurable concurrent batches setting
		$concurrent = get_option( 'wta_concurrent_batches', 10 );
		
		// Validate range (1-20)
		$concurrent = max( 1, min( 20, intval( $concurrent ) ) );
		
		return $concurrent;
	}, 999 );
	
	// Increase batch size: 25 → 150
	// Process more actions per batch to reduce overhead
	add_filter( 'action_scheduler_queue_runner_batch_size', function( $size ) {
		return 150; // 6× default
	}, 999 );
	
	// Increase time limit: 30s → 120s
	// Allow longer processing time for complex operations
	add_filter( 'action_scheduler_queue_runner_time_limit', function( $limit ) {
		return 120; // 2 minutes per queue
	}, 999 );
	
	// Increase timeout period: 5 min → 15 min
	// Prevent premature action reset for longer-running tasks
	add_filter( 'action_scheduler_timeout_period', function( $timeout ) {
		return 900; // 15 minutes
	}, 999 );
	
	// Increase failure period: 5 min → 15 min
	add_filter( 'action_scheduler_failure_period', function( $timeout ) {
		return 900; // 15 minutes
	}, 999 );
	
	// ==========================================
	// MULTIPLE QUEUE RUNNERS (v2.35.11)
	// Start additional loopback requests to achieve true concurrency
	// Based on Action Scheduler docs: https://actionscheduler.org/perf/
	// ==========================================
	
	/**
	 * Trigger additional loopback requests based on concurrent_batches setting.
	 * WP-Cron starts 1 runner, this creates (concurrent_batches - 1) additional runners.
	 */
	add_action( 'action_scheduler_run_queue', function() {
		// Get the concurrent batches setting
		$concurrent = get_option( 'wta_concurrent_batches', 10 );
		$concurrent = max( 1, min( 20, intval( $concurrent ) ) );
		
		// WP-Cron already starts 1 runner, so we need (concurrent - 1) additional
		$additional_runners = $concurrent - 1;
		
		// DEBUG: Log that hook was triggered
		WTA_Logger::info( 'action_scheduler_run_queue triggered', array(
			'concurrent_setting' => $concurrent,
			'additional_runners' => $additional_runners,
		) );
		
		if ( $additional_runners < 1 ) {
			WTA_Logger::info( 'No additional runners needed (concurrent_batches = 1)' );
			return; // No additional runners needed
		}
		
		// Allow self-signed SSL certificates for local/dev environments
		add_filter( 'https_local_ssl_verify', '__return_false', 100 );
		
		// Create additional loopback requests
		for ( $i = 0; $i < $additional_runners; $i++ ) {
			$ajax_url = admin_url( 'admin-ajax.php' );
			
			WTA_Logger::info( 'Sending loopback request', array(
				'instance' => $i,
				'url'      => $ajax_url,
			) );
			
			$response = wp_remote_post( $ajax_url, array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => false, // Non-blocking = concurrent!
				'headers'     => array(),
				'body'        => array(
					'action'     => 'wta_create_additional_runner',
					'instance'   => $i,
					'wta_nonce'  => wp_create_nonce( 'wta_runner_' . $i ),
				),
				'cookies'     => array(),
			) );
			
			// Check if request failed
			if ( is_wp_error( $response ) ) {
				WTA_Logger::error( 'Loopback request failed', array(
					'instance' => $i,
					'error'    => $response->get_error_message(),
				) );
			}
		}
		
		WTA_Logger::info( 'All loopback requests sent', array(
			'count' => $additional_runners,
		) );
	}, 0 );
	
	/**
	 * Handle loopback requests and start additional queue runners.
	 * This is called via AJAX (nopriv) from the loopback requests above.
	 */
	add_action( 'wp_ajax_nopriv_wta_create_additional_runner', function() {
		WTA_Logger::info( 'AJAX handler called', array(
			'has_nonce'    => isset( $_POST['wta_nonce'] ),
			'has_instance' => isset( $_POST['instance'] ),
			'instance'     => isset( $_POST['instance'] ) ? $_POST['instance'] : 'N/A',
		) );
		
		if ( isset( $_POST['wta_nonce'] ) && isset( $_POST['instance'] ) && 
		     wp_verify_nonce( $_POST['wta_nonce'], 'wta_runner_' . $_POST['instance'] ) ) {
			
			WTA_Logger::info( 'Starting additional queue runner', array(
				'instance' => $_POST['instance'],
			) );
			
			ActionScheduler_QueueRunner::instance()->run();
			
			WTA_Logger::info( 'Additional queue runner completed', array(
				'instance' => $_POST['instance'],
			) );
		} else {
			WTA_Logger::error( 'Nonce verification failed for additional runner', array(
				'instance' => isset( $_POST['instance'] ) ? $_POST['instance'] : 'N/A',
			) );
		}
		wp_die();
	}, 0 );
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
