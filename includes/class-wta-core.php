<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes
 */

class WTA_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      WTA_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Initialize the core plugin functionality.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_permalink_hooks(); // MUST run on both admin and frontend
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_action_scheduler_hooks();
		
		// FAQ schema via direct JSON-LD injection (v2.35.30)
		// Yoast SEO 26.5+ doesn't pass @graph to wpseo_schema_graph filter
		// Instead, FAQ schema added directly in render_faq_section() as <script> tag
		// Same pattern as ItemList - proven stable
		// add_action( 'wp', array( $this, 'register_faq_schema' ) ); // DISABLED v2.35.30
		
		// Check for updates after WordPress is fully loaded
		add_action( 'init', array( $this, 'check_plugin_update' ), 5 );
		
		// Auto-heal: Ensure Action Scheduler actions are scheduled
		add_action( 'admin_init', array( $this, 'ensure_actions_scheduled' ) );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		// Loader
		require_once WTA_PLUGIN_DIR . 'includes/class-wta-loader.php';

	// Core
	require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-post-type.php';
	require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-queue.php';
	require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-geonames-parser.php'; // v3.0.0
	require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-importer.php';

	// Helpers
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-logger.php';
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-utils.php';
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-timezone-helper.php';
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-quick-translate.php';
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-geonames-translator.php'; // v3.0.0
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-wikidata-translator.php'; // v3.0.0 - kept as fallback
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-ai-translator.php';
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-faq-generator.php'; // v2.35.0
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-faq-renderer.php'; // v2.35.0
	require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-log-cleaner.php'; // v2.35.7
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-country-gps-migration.php'; // v2.35.73

		// Action Scheduler Processors
		require_once WTA_PLUGIN_DIR . 'includes/scheduler/class-wta-structure-processor.php';
		require_once WTA_PLUGIN_DIR . 'includes/scheduler/class-wta-timezone-processor.php';
		require_once WTA_PLUGIN_DIR . 'includes/scheduler/class-wta-ai-processor.php';

		// Admin
		if ( is_admin() ) {
			require_once WTA_PLUGIN_DIR . 'includes/admin/class-wta-admin.php';
			require_once WTA_PLUGIN_DIR . 'includes/admin/class-wta-settings.php';
		}

	// Frontend
	require_once WTA_PLUGIN_DIR . 'includes/frontend/class-wta-template-loader.php';
	require_once WTA_PLUGIN_DIR . 'includes/frontend/class-wta-shortcodes.php';

	// Load Action Scheduler if not already loaded by another plugin
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			$action_scheduler_path = WTA_PLUGIN_DIR . 'includes/action-scheduler/action-scheduler.php';
			if ( file_exists( $action_scheduler_path ) ) {
				require_once $action_scheduler_path;
			} else {
				// Show admin notice if Action Scheduler is missing
				add_action( 'admin_notices', array( $this, 'action_scheduler_missing_notice' ) );
			}
		}

		$this->loader = new WTA_Loader();
	}

	/**
	 * Check for plugin updates and run upgrade routines if needed.
	 *
	 * @since    2.0.0
	 * @access   public
	 */
	public function check_plugin_update() {
		$current_version = get_option( 'wta_plugin_version', '0.0.0' );

		if ( version_compare( $current_version, WTA_VERSION, '<' ) ) {
			// Plugin has been upgraded
			WTA_Logger::info( 'Plugin upgraded', array(
				'from' => $current_version,
				'to'   => WTA_VERSION,
			) );

			// Run activation routines to ensure DB tables and options are up to date
			require_once WTA_PLUGIN_DIR . 'includes/class-wta-activator.php';
			WTA_Activator::activate();

			// Set transient to show upgrade notice
			set_transient( 'wta_upgraded_notice', array(
				'from' => $current_version,
				'to'   => WTA_VERSION,
			), HOUR_IN_SECONDS );
		}
	}

	/**
	 * Register permalink and post type hooks.
	 * 
	 * Uses dynamic continent-based rewrite rules + defensive pre_get_posts.
	 * This hybrid approach provides maximum compatibility.
	 *
	 * @since    2.32.0
	 * @access   private
	 */
	private function define_permalink_hooks() {
		// Register custom post type (includes dynamic rewrite rules)
		$post_type = new WTA_Post_Type();
		$this->loader->add_action( 'init', $post_type, 'register_post_type' );
		
		// Remove slug from permalinks
		$this->loader->add_filter( 'post_type_link', $post_type, 'remove_post_type_slug', 10, 3 );
		
		// Defensive pre_get_posts as backup for edge cases
		$this->loader->add_action( 'pre_get_posts', $post_type, 'parse_request_for_locations', 10 );
		
		// Redirect old URLs with slugs to clean URLs
		$this->loader->add_action( 'template_redirect', $post_type, 'redirect_old_urls', 10 );
	}

	/**
	 * Register all admin-related hooks.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		// Admin class
		$admin = new WTA_Admin();
		$this->loader->add_action( 'admin_menu', $admin, 'add_plugin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_notices', $admin, 'show_admin_notices' );
		
		// Reschedule recurring actions when cron interval changes (v2.35.32)
		$this->loader->add_action( 'update_option_wta_cron_interval', $this, 'reschedule_recurring_actions' );

		// Admin columns and bulk actions (Content Health Check)
		$this->loader->add_filter( 'manage_' . WTA_POST_TYPE . '_posts_columns', $admin, 'add_content_status_column' );
		$this->loader->add_action( 'manage_' . WTA_POST_TYPE . '_posts_custom_column', $admin, 'display_content_status_column', 10, 2 );
		$this->loader->add_filter( 'bulk_actions-edit-' . WTA_POST_TYPE, $admin, 'add_regenerate_bulk_action' );
		$this->loader->add_filter( 'handle_bulk_actions-edit-' . WTA_POST_TYPE, $admin, 'handle_regenerate_bulk_action', 10, 3 );
		$this->loader->add_action( 'admin_notices', $admin, 'display_regenerate_admin_notice' );
		
		// Content status filter dropdown
		$this->loader->add_action( 'restrict_manage_posts', $admin, 'add_content_filter_dropdown' );
		$this->loader->add_action( 'pre_get_posts', $admin, 'filter_posts_by_content_status' );

		// AJAX handlers
		$this->loader->add_action( 'wp_ajax_wta_prepare_import', $admin, 'ajax_prepare_import' );
		$this->loader->add_action( 'wp_ajax_wta_get_queue_stats', $admin, 'ajax_get_queue_stats' );
		$this->loader->add_action( 'wp_ajax_wta_test_openai_connection', $admin, 'ajax_test_openai_connection' );
		$this->loader->add_action( 'wp_ajax_wta_test_timezonedb_connection', $admin, 'ajax_test_timezonedb_connection' );
		$this->loader->add_action( 'wp_ajax_wta_reset_all_data', $admin, 'ajax_reset_all_data' );
		$this->loader->add_action( 'wp_ajax_wta_view_queue_details', $admin, 'ajax_view_queue_details' );
		$this->loader->add_action( 'wp_ajax_wta_reset_stuck_items', $admin, 'ajax_reset_stuck_items' );
		$this->loader->add_action( 'wp_ajax_wta_retry_failed_items', $admin, 'ajax_retry_failed_items' );
		$this->loader->add_action( 'wp_ajax_wta_regenerate_all_ai', $admin, 'ajax_regenerate_all_ai' ); // v2.34.20
		$this->loader->add_action( 'wp_ajax_wta_get_logs', $admin, 'ajax_get_logs' );
		$this->loader->add_action( 'wp_ajax_wta_clear_translation_cache', $admin, 'ajax_clear_translation_cache' );
		$this->loader->add_action( 'wp_ajax_wta_clear_shortcode_cache', $admin, 'ajax_clear_shortcode_cache' ); // v2.35.51
		$this->loader->add_action( 'wp_ajax_wta_regenerate_permalinks', $admin, 'ajax_regenerate_permalinks' );
		$this->loader->add_action( 'wp_ajax_wta_force_reschedule', $admin, 'ajax_force_reschedule' ); // v2.35.33
		// v3.0.19: Country GPS migration removed - no longer needed with GeoNames

		// Settings
		$settings = new WTA_Settings();
		$this->loader->add_action( 'admin_init', $settings, 'register_settings' );
	}

	/**
	 * Register all public-facing hooks.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		// Template loader
		$template_loader = new WTA_Template_Loader();
		$this->loader->add_filter( 'template_include', $template_loader, 'load_template' );

		// Enqueue frontend assets
		$this->loader->add_action( 'wp_enqueue_scripts', $template_loader, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $template_loader, 'enqueue_scripts' );
		
		// Register shortcodes
		$shortcodes = new WTA_Shortcodes();
		$this->loader->add_action( 'init', $shortcodes, 'register_shortcodes' );

		// Disable wpautop for our post type (v2.35.20)
		// Prevents WordPress from auto-adding <br> and <p> tags to our structured content
		add_filter( 'the_content', function( $content ) {
			if ( WTA_POST_TYPE === get_post_type() ) {
				remove_filter( 'the_content', 'wpautop' );
			}
			return $content;
		}, 0 ); // Priority 0 = run before wpautop (priority 10)
	}
	
	/**
	 * Register FAQ schema integration.
	 * 
	 * Adds FAQ schema to Yoast SEO graph using array type ['WebPage', 'FAQPage'].
	 * Best practice: Preserves all Yoast properties, just adds FAQPage to @type array.
	 * 
	 * @since    2.35.15
	 * @access   public
	 */
	public function register_faq_schema() {
		// FAQ Schema integration with Yoast (v2.35.29 - debug mode)
		// Priority 999 = Run AFTER Yoast builds complete @graph (Yoast uses priority 10-100)
		// This ensures @graph exists before we modify it
		if ( function_exists( 'YoastSEO' ) || class_exists( 'WPSEO_Options' ) ) {
			// Debug: Log when filter is registered
			WTA_Logger::info( '=== FAQ FILTER REGISTRATION ===', array(
				'priority'   => 999,
				'yoast_seo'  => function_exists( 'YoastSEO' ) ? 'exists' : 'not found',
				'timestamp'  => current_time( 'mysql' )
			) );
			
			add_filter( 'wpseo_schema_graph', array( 'WTA_FAQ_Renderer', 'inject_faq_schema' ), 999, 2 );
			
			// Verify filter was added
			global $wp_filter;
			if ( isset( $wp_filter['wpseo_schema_graph'] ) ) {
				WTA_Logger::info( '=== FILTER CONFIRMED ADDED ===', array(
					'priorities' => array_keys( $wp_filter['wpseo_schema_graph']->callbacks )
				) );
			}
		} else {
			WTA_Logger::warning( '=== YOAST NOT FOUND - FAQ FILTER NOT REGISTERED ===', array(
				'yoast_function' => function_exists( 'YoastSEO' ),
				'yoast_class'    => class_exists( 'WPSEO_Options' )
			) );
		}
}

	/**
	 * Register Action Scheduler hooks.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_action_scheduler_hooks() {
		// Increase Action Scheduler time limit for API-heavy operations
		$this->loader->add_filter( 'action_scheduler_queue_runner_time_limit', $this, 'increase_time_limit' );
		
		// v3.0.36: Set concurrent batches dynamically based on test mode
		$this->loader->add_filter( 'action_scheduler_queue_runner_concurrent_batches', $this, 'set_concurrent_batches' );

		// Structure processor
		$structure_processor = new WTA_Structure_Processor();
		$this->loader->add_action( 'wta_process_structure', $structure_processor, 'process_batch' );

		// Timezone processor
		$timezone_processor = new WTA_Timezone_Processor();
		$this->loader->add_action( 'wta_process_timezone', $timezone_processor, 'process_batch' );

	// AI processor
	$ai_processor = new WTA_AI_Processor();
	$this->loader->add_action( 'wta_process_ai_content', $ai_processor, 'process_batch' );
	
	// v3.0.37: Initiate additional runners for concurrent processing
	$this->loader->add_action( 'action_scheduler_run_queue', $this, 'initiate_additional_runners', 0 );
	
	// AJAX handlers for additional runners (no auth required - nonce validated)
	$this->loader->add_action( 'wp_ajax_nopriv_wta_run_additional_queue', $this, 'handle_additional_runner_request' );
	$this->loader->add_action( 'wp_ajax_wta_run_additional_queue', $this, 'handle_additional_runner_request' );

	// Log cleanup (v2.35.7) - Runs daily at 04:00
	$this->loader->add_action( 'wta_cleanup_old_logs', 'WTA_Log_Cleaner', 'cleanup_old_logs' );
	}

	/**
	 * Auto-heal: Ensure Action Scheduler actions are scheduled.
	 * 
	 * Runs on admin_init to automatically schedule missing recurring actions.
	 * This prevents issues where actions get unscheduled or were never scheduled.
	 *
	 * @since    2.0.0
	 */
	public function ensure_actions_scheduled() {
		// Only if Action Scheduler is available
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		// Check once per hour to avoid overhead
		$last_check = get_transient( 'wta_actions_checked' );
		if ( false !== $last_check ) {
			return;
		}

		// Set transient for 1 hour
		set_transient( 'wta_actions_checked', time(), HOUR_IN_SECONDS );

		// Define required actions
		$required_actions = array(
			'wta_process_structure',
			'wta_process_timezone',
			'wta_process_ai_content',
		);

		// Get current cron interval setting
		$interval = intval( get_option( 'wta_cron_interval', 60 ) );

		// Check and schedule missing actions
		foreach ( $required_actions as $action ) {
			if ( false === as_next_scheduled_action( $action ) ) {
				as_schedule_recurring_action( time(), $interval, $action, array(), 'world-time-ai' );
				
				WTA_Logger::info( "Auto-scheduled missing action: $action", array( 'interval' => $interval . 's' ) );
			}
		}
	}
	
	/**
	 * Reschedule recurring actions when cron interval setting changes.
	 * 
	 * Unschedules all existing actions and reschedules with new interval.
	 * This ensures batch sizes and time limits match the new interval.
	 *
	 * @since    2.35.32
	 */
	public function reschedule_recurring_actions() {
		// Only if Action Scheduler is available
		if ( ! function_exists( 'as_unschedule_all_actions' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		$actions = array(
			'wta_process_structure',
			'wta_process_timezone',
			'wta_process_ai_content',
		);

		$interval = intval( get_option( 'wta_cron_interval', 60 ) );

		foreach ( $actions as $action ) {
			// Unschedule all instances of this action
			as_unschedule_all_actions( $action, array(), 'world-time-ai' );
			
			// Reschedule with new interval
			as_schedule_recurring_action( time(), $interval, $action, array(), 'world-time-ai' );
			
			WTA_Logger::info( "Rescheduled recurring action", array(
				'action'   => $action,
				'interval' => $interval . 's',
			) );
		}
		
		// Clear the auto-schedule check so it re-validates immediately
		delete_transient( 'wta_actions_checked' );
		
		// Add admin notice
		add_settings_error(
			'wta_cron_interval',
			'wta_cron_interval_updated',
			sprintf( 
				__( 'Background processing interval updated to %s. All recurring actions have been rescheduled. Batch sizes will automatically adjust.', 'world-time-ai' ),
				$interval === 300 ? '5 minutes' : '1 minute'
			),
			'success'
		);
	}

	/**
	 * Increase Action Scheduler time limit.
	 *
	 * Default is 30 seconds, but timezone API calls need more time.
	 * With batch size of 25 items × 1.1 seconds = 27.5 seconds needed,
	 * we set 60 seconds to allow buffer for network delays.
	 *
	 * @since    2.21.3
	 * @param    int $time_limit Current time limit in seconds.
	 * @return   int             New time limit in seconds.
	 */
	public function increase_time_limit( $time_limit ) {
		return 60; // 60 seconds to safely process timezone lookups
	}
	
	/**
	 * Set concurrent batches dynamically based on test mode.
	 * 
	 * Test mode: Higher concurrency (no API limits, only templates)
	 * Normal mode: Moderate concurrency (respects OpenAI Tier 5 limits)
	 * 
	 * Note: Timezone processor has its own lock to run single-threaded
	 * (TimeZoneDB FREE tier rate limit: 1 req/s)
	 *
	 * @since    3.0.36
	 * @param    int $default Default concurrent batches (usually 1).
	 * @return   int          Number of concurrent batches allowed.
	 */
	public function set_concurrent_batches( $default ) {
		$test_mode = get_option( 'wta_test_mode', 0 );
		
		if ( $test_mode ) {
			// Test mode: High parallelization (no API limits)
			// Default: 12 concurrent batches
			// Optimizes structure creation + template generation
			return intval( get_option( 'wta_concurrent_batches_test', 12 ) );
		} else {
			// Normal mode: Moderate parallelization (respects OpenAI API limits)
			// Default: 6 concurrent batches
			// OpenAI Tier 5: 10,000 RPM - 6 concurrent = ~80 API calls/min (safe)
			return intval( get_option( 'wta_concurrent_batches_normal', 6 ) );
		}
	}
	
	/**
	 * Initiate additional queue runners for concurrent processing.
	 * 
	 * Triggers multiple async loopback requests to start additional runners.
	 * Based on Action Scheduler performance recommendations.
	 * 
	 * Only runs if concurrent_batches > 1. For concurrent=12, this initiates 11 
	 * additional runners via loopback requests (WP-Cron already started 1).
	 * 
	 * @since    3.0.37
	 * @link     https://actionscheduler.org/perf/
	 */
	public function initiate_additional_runners() {
		// Get current concurrent batches setting
		$concurrent = $this->set_concurrent_batches( 1 );
		
		// Only initiate additional runners if concurrent > 1
		if ( $concurrent <= 1 ) {
			return;
		}
		
		// Allow self-signed SSL certificates
		add_filter( 'https_local_ssl_verify', '__return_false', 100 );
		
		// Start (concurrent - 1) additional runners
		// -1 because WP-Cron already started 1 runner
		for ( $i = 1; $i < $concurrent; $i++ ) {
			wp_remote_post( admin_url( 'admin-ajax.php' ), array(
				'method'      => 'POST',
				'timeout'     => 0.01, // Very short timeout (non-blocking)
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => false, // Non-blocking request
				'sslverify'   => false,
				'headers'     => array(),
				'body'        => array(
					'action'   => 'wta_run_additional_queue',
					'instance' => $i,
					'nonce'    => wp_create_nonce( 'wta_runner_' . $i ),
				),
			) );
		}
		
		WTA_Logger::debug( 'Initiated additional queue runners', array(
			'concurrent' => $concurrent,
			'additional_runners' => $concurrent - 1,
		) );
	}
	
	/**
	 * Handle additional queue runner requests.
	 * 
	 * Validates nonce and starts ActionScheduler queue runner.
	 * Called via AJAX from initiate_additional_runners().
	 * 
	 * @since    3.0.37
	 */
	public function handle_additional_runner_request() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! isset( $_POST['instance'] ) ) {
			wp_die( 'Invalid request', 403 );
		}
		
		$instance = absint( $_POST['instance'] );
		
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wta_runner_' . $instance ) ) {
			wp_die( 'Invalid nonce', 403 );
		}
		
		// Start ActionScheduler queue runner
		if ( class_exists( 'ActionScheduler_QueueRunner' ) ) {
			WTA_Logger::debug( 'Additional queue runner started', array(
				'instance' => $instance,
			) );
			
			ActionScheduler_QueueRunner::instance()->run();
		}
		
		wp_die(); // End request
	}

	/**
	 * Display admin notice if Action Scheduler is missing.
	 *
	 * @since    2.0.0
	 */
	public function action_scheduler_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'World Time AI Error:', 'world-time-ai' ); ?></strong>
				<?php
				printf(
					/* translators: %s: URL to setup instructions */
					esc_html__( 'Action Scheduler library not found. Please see %s for installation instructions.', 'world-time-ai' ),
					'<a href="https://github.com/henrikandersen1978/what_is_the_time/blob/main/EXTERNAL-LIBRARIES.md" target="_blank">EXTERNAL-LIBRARIES.md</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    2.0.0
	 */
	public function run() {
		$this->loader->run();
	}
}
