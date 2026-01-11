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
		
		// Aggressive cleanup for completed actions (v3.0.57)
		add_filter( 'action_scheduler_retention_period', array( $this, 'set_retention_period' ), 10 );
		add_action( 'init', array( $this, 'schedule_cleanup' ) );
		add_action( 'wta_cleanup_completed_actions', array( $this, 'cleanup_completed_actions' ) );
		
		// Monitor stuck timezone lookups (v3.0.58)
		add_action( 'init', array( $this, 'schedule_timezone_monitor' ) );
		add_action( 'wta_monitor_stuck_timezones', array( $this, 'monitor_stuck_timezones' ) );
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

		// Action Scheduler Processors (v3.0.43 - Pilanto-AI Model)
		require_once WTA_PLUGIN_DIR . 'includes/processors/class-wta-single-structure-processor.php';
		require_once WTA_PLUGIN_DIR . 'includes/processors/class-wta-single-timezone-processor.php';
		require_once WTA_PLUGIN_DIR . 'includes/processors/class-wta-single-ai-processor.php';

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

		// CRITICAL: Increase batch size (v3.0.50 - like Action Scheduler High Volume plugin)
		// Default is 25, but with 10 concurrent runners, we need bigger batches!
		$this->loader->add_filter( 'action_scheduler_queue_runner_batch_size', $this, 'increase_batch_size', 10 );

		// Dynamic concurrent batches per action type (v3.0.43 - Pilanto-AI Model)
		// v3.0.51: MAXIMUM priority to ensure our value is used!
		$this->loader->add_filter( 'action_scheduler_queue_runner_concurrent_batches', $this, 'set_concurrent_batches', PHP_INT_MAX );

		// Initiate additional queue runners via loopback requests (v3.0.48)
		// This is the ONLY way to achieve true concurrency when proc_open() is disabled
		$this->loader->add_action( 'action_scheduler_run_queue', $this, 'request_additional_runners', 0 );
		
		// Handle loopback requests to start queue runners (v3.0.48)
		$this->loader->add_action( 'wp_ajax_nopriv_wta_start_queue_runner', $this, 'start_queue_runner', 0 );
		$this->loader->add_action( 'wp_ajax_wta_start_queue_runner', $this, 'start_queue_runner', 0 );
		
		// Debug hooks to monitor Action Scheduler behavior (v3.0.50)
		$this->loader->add_action( 'action_scheduler_before_process_queue', $this, 'debug_before_queue', 10 );
		$this->loader->add_action( 'action_scheduler_after_process_queue', $this, 'debug_after_queue', 10 );

		// Single Structure Processor (v3.0.43 - Pilanto-AI Model)
		$structure_processor = new WTA_Single_Structure_Processor();
		$this->loader->add_action( 'wta_create_continent', $structure_processor, 'create_continent', 10, 2 );  // name, name_local
		$this->loader->add_action( 'wta_create_country', $structure_processor, 'create_country', 10, 8 );     // name, name_local, country_code, country_id, continent, latitude, longitude, geonameid
		$this->loader->add_action( 'wta_create_city', $structure_processor, 'create_city', 10, 7 );           // name, name_local, geonameid, country_code, latitude, longitude, population

	// Cities scheduler (delegates to importer)
	$this->loader->add_action( 'wta_schedule_cities', 'WTA_Importer', 'schedule_cities', 10, 6 ); // v3.0.75: CRITICAL FIX - Accept all 6 parameters!
	
	// Bulk start city processing (v3.0.72, v3.0.78: Added chunking)
	$this->loader->add_action( 'wta_start_waiting_city_processing', 'WTA_Importer', 'start_waiting_city_processing', 10, 1 );

	// Single Timezone Processor (v3.0.43 - Pilanto-AI Model)
		$timezone_processor = new WTA_Single_Timezone_Processor();
		$this->loader->add_action( 'wta_lookup_timezone', $timezone_processor, 'lookup_timezone', 10, 3 );

		// Single AI Processor (v3.0.43 - Pilanto-AI Model)
		$ai_processor = new WTA_Single_AI_Processor();
		$this->loader->add_action( 'wta_generate_ai_content', $ai_processor, 'generate_content', 10, 3 );

		// Log cleanup (v2.35.7) - Runs daily at 04:00
		$this->loader->add_action( 'wta_cleanup_old_logs', 'WTA_Log_Cleaner', 'cleanup_old_logs' );
	}

	/**
	 * Auto-heal: Ensure Action Scheduler actions are scheduled.
	 * 
	 * v3.0.43: Pilanto-AI Model uses single actions scheduled on-demand.
	 * No recurring actions needed. Only ensure log cleanup is scheduled.
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

		// Ensure log cleanup is scheduled (daily at 04:00)
		if ( false === as_next_scheduled_action( 'wta_cleanup_old_logs' ) ) {
			$tomorrow_4am = strtotime( 'tomorrow 04:00:00' );
			as_schedule_recurring_action( $tomorrow_4am, DAY_IN_SECONDS, 'wta_cleanup_old_logs', array(), 'world-time-ai' );
			WTA_Logger::info( 'Auto-scheduled missing action: wta_cleanup_old_logs' );
		}
	}
	
	/**
	 * Reschedule recurring actions when cron interval setting changes.
	 * 
	 * v3.0.43: Pilanto-AI Model - No recurring actions to reschedule.
	 * Settings are applied dynamically via filters.
	 *
	 * @since    2.35.32
	 * @deprecated 3.0.43 No longer needed with Pilanto-AI Model.
	 */
	public function reschedule_recurring_actions() {
		// Pilanto-AI Model: No recurring actions to reschedule
		// Concurrent settings are applied dynamically via set_concurrent_batches() filter
		WTA_Logger::info( 'Cron interval updated (Pilanto-AI Model - no rescheduling needed)' );
		
		// Add admin notice
		add_settings_error(
			'wta_cron_interval',
			'wta_cron_interval_updated',
			sprintf( 
				__( 'Processing frequency updated to %s. Concurrent settings are applied dynamically.', 'world-time-ai' ),
				intval( get_option( 'wta_cron_interval', 60 ) ) === 300 ? '5 minutes' : '1 minute'
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
	 * Increase Action Scheduler batch size.
	 * 
	 * CRITICAL FIX v3.0.50: This was the missing piece!
	 * 
	 * Default batch size is 25 actions. When multiple concurrent runners start,
	 * if there are only 30 pending actions:
	 * - Runner 1 claims 25 actions
	 * - Runner 2 claims 5 actions
	 * - Runners 3-10 find NOTHING to claim!
	 * 
	 * v3.0.52: Reduced from 100 to 25 for better stability.
	 * While 100 gave maximum throughput, it caused backend slowness and
	 * database strain. Batch size 25 (default) is proven to work well and
	 * provides excellent balance between speed and stability.
	 * 
	 * With 10 concurrent runners × 25 = 250 actions in progress (plenty!)
	 *
	 * @since    3.0.50
	 * @param    int $batch_size Current batch size.
	 * @return   int             New batch size.
	 */
	public function increase_batch_size( $batch_size ) {
		$new_size = 25; // Default proven size - balanced for stability
		
		WTA_Logger::debug( 'Batch size filter called', array(
			'default'  => $batch_size,
			'new_size' => $new_size,
		) );
		
		return $new_size;
	}

	/**
	 * Set concurrent batches dynamically (GLOBAL SETTING).
	 * 
	 * v3.2.80: SIMPLIFIED - One global concurrent setting for all processors.
	 * Sequential phases strategy (Structure → Timezone → AI) eliminates need
	 * for per-group limits.
	 * 
	 * With TimezoneDB Premium (10 req/sec), we can run high concurrency!
	 *
	 * @since    3.0.41
	 * @since    3.2.80 Reverted to simple global concurrent (sequential phases)
	 * @param    int $default Default concurrent batches.
	 * @return   int Adjusted concurrent batches.
	 */
	public function set_concurrent_batches( $default ) {
		$test_mode = get_option( 'wta_test_mode', 0 );
		$concurrent = $test_mode 
			? intval( get_option( 'wta_concurrent_test_mode', 10 ) )
			: intval( get_option( 'wta_concurrent_normal_mode', 10 ) );
		
		WTA_Logger::info( '🔧 Concurrent batches', array(
			'concurrent' => $concurrent,
			'test_mode'  => $test_mode ? 'yes' : 'no',
		) );
		
		return $concurrent;
	}

	/**
	 * Initiate additional queue runners via async loopback requests.
	 * 
	 * Since proc_open() is disabled on RunCloud/OpenLiteSpeed, the ONLY way to achieve
	 * true concurrent processing is via async HTTP loopback requests.
	 * 
	 * This function starts (N-1) additional runners, where N is the concurrent setting,
	 * because Action Scheduler already starts 1 runner automatically.
	 * 
	 * Inspired by Action Scheduler High Volume plugin.
	 * 
	 * @since    3.0.48
	 * @link     https://github.com/woocommerce/action-scheduler-high-volume
	 * @link     https://actionscheduler.org/perf/
	 */
	public function request_additional_runners() {
		// v3.2.80: Simple global concurrent setting
		$test_mode = get_option( 'wta_test_mode', 0 );
		$concurrent = $test_mode 
			? intval( get_option( 'wta_concurrent_test_mode', 10 ) )
			: intval( get_option( 'wta_concurrent_normal_mode', 10 ) );
		
		// Number of additional runners (minus 1 because AS already starts one)
		$additional_runners = max( 0, $concurrent - 1 );
		
		if ( $additional_runners < 1 ) {
			return; // No additional runners needed
		}
		
		// Allow self-signed SSL certificates for loopback requests
		add_filter( 'https_local_ssl_verify', '__return_false', 100 );
		
		// Start N additional runners via async loopback requests
		// Using exact parameters from Action Scheduler documentation
		// v3.0.51: Removed nonce (doesn't work for async requests)
		for ( $i = 0; $i < $additional_runners; $i++ ) {
			wp_remote_post( admin_url( 'admin-ajax.php' ), array(
				'method'      => 'POST',
				'timeout'     => 45,         // Long timeout as per AS docs
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => false,      // CRITICAL: Non-blocking = async!
				'sslverify'   => false,
				'headers'     => array(),
				'body'        => array(
					'action'     => 'wta_start_queue_runner',
					'instance'   => $i,
				),
				'cookies'     => array(),
			) );
		}
		
		WTA_Logger::debug( 'Initiated additional queue runners', array(
			'additional_runners' => $additional_runners,
			'total_concurrent'   => $concurrent,
			'test_mode'          => $test_mode ? 'yes' : 'no',
		) );
	}

	/**
	 * Handle loopback requests and start queue runner.
	 * 
	 * This is the callback for async loopback requests initiated by
	 * request_additional_runners(). 
	 * 
	 * v3.0.51: Removed nonce verification - it doesn't work for async requests
	 * because they run in a different session context. Instead, we verify:
	 * 1. Request comes from localhost (same server)
	 * 2. Instance parameter is present
	 * 
	 * @since    3.0.48
	 */
	public function start_queue_runner() {
		// Basic verification
		if ( ! isset( $_POST['instance'] ) || ! isset( $_POST['action'] ) ) {
			WTA_Logger::error( 'Loopback runner: Invalid request (missing parameters)' );
			wp_die( 'Invalid request', 403 );
		}
		
		// Verify it's a loopback request (from same server)
		$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
		$is_local = in_array( $remote_addr, array( '127.0.0.1', '::1', $_SERVER['SERVER_ADDR'] ?? '' ) );
		
		if ( ! $is_local ) {
			WTA_Logger::error( 'Loopback runner: Not from localhost', array(
				'remote_addr' => $remote_addr,
			) );
			wp_die( 'Forbidden', 403 );
		}
		
		$instance = intval( $_POST['instance'] );
		
		WTA_Logger::info( '🔄 Loopback runner received', array(
			'instance' => $instance,
		) );
		
		// Start queue runner
		if ( class_exists( 'ActionScheduler_QueueRunner' ) ) {
			ActionScheduler_QueueRunner::instance()->run( 'WTA Async Runner #' . $instance );
			WTA_Logger::debug( 'Loopback runner completed', array(
				'instance' => $instance,
			) );
		} else {
			WTA_Logger::error( 'ActionScheduler_QueueRunner class not found!' );
		}
		
		wp_die(); // Terminate cleanly
	}

	/**
	 * Debug: Log queue state BEFORE processing.
	 * 
	 * This helps diagnose why concurrent processing might not work.
	 * 
	 * @since    3.0.50
	 */
	public function debug_before_queue() {
		global $wpdb;
		
		// Get pending actions count
		$pending_count = $wpdb->get_var( 
			"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions 
			WHERE status = 'pending' AND hook LIKE 'wta_%'"
		);
		
		// Get claim count (concurrent batches running)
		$claim_count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT claim_id) FROM {$wpdb->prefix}actionscheduler_actions 
			WHERE claim_id != 0 AND status IN ('pending', 'in-progress')"
		);
		
		// IMPORTANT: Get allowed concurrent batches by calling the filter
		// This should trigger our set_concurrent_batches() method
		$allowed_concurrent = apply_filters( 'action_scheduler_queue_runner_concurrent_batches', 1 );
		
		// Also check settings directly
		$test_mode = get_option( 'wta_test_mode', 0 );
		$setting_value = $test_mode 
			? intval( get_option( 'wta_concurrent_test_mode', 10 ) )
			: intval( get_option( 'wta_concurrent_normal_mode', 5 ) );
		
		WTA_Logger::info( '🚀 Queue runner starting', array(
			'pending_wta_actions' => $pending_count,
			'current_claims'      => $claim_count,
			'allowed_concurrent'  => $allowed_concurrent,
			'setting_value'       => $setting_value,
			'test_mode'           => $test_mode ? 'yes' : 'no',
			'has_maximum'         => $claim_count >= $allowed_concurrent ? 'YES (will skip!)' : 'NO (will process)',
		) );
	}

	/**
	 * Debug: Log queue state AFTER processing.
	 * 
	 * @since    3.0.50
	 */
	public function debug_after_queue() {
		global $wpdb;
		
		$in_progress_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions 
			WHERE status = 'in-progress' AND hook LIKE 'wta_%'"
		);
		
		WTA_Logger::info( '✅ Queue runner finished', array(
			'in_progress_actions' => $in_progress_count,
		) );
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
	 * Set aggressive retention period for completed actions.
	 * 
	 * @since    3.0.57
	 * @since    3.0.65  Increased from 1 to 5 minutes for scheduling safety.
	 * @return   int Retention period in seconds (5 minutes).
	 */
	public function set_retention_period() {
		return 5 * MINUTE_IN_SECONDS; // Keep 5 minutes (safety buffer for pending actions)
	}

	/**
	 * Schedule cleanup job to run every minute.
	 * 
	 * @since    3.0.57
	 */
	public function schedule_cleanup() {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}
		
		if ( ! as_next_scheduled_action( 'wta_cleanup_completed_actions' ) ) {
			as_schedule_recurring_action( 
				time(), 
				60, // Every 1 minute
				'wta_cleanup_completed_actions'
			);
		}
	}

	/**
	 * Cleanup completed actions older than 5 minutes.
	 * Runs every 1 minute, but only deletes actions completed 5+ minutes ago.
	 * Deletes up to 250k records per run.
	 * 
	 * @since    3.0.57
	 * @since    3.0.66  Changed from 1 to 5 minutes to prevent race conditions.
	 */
	public function cleanup_completed_actions() {
		global $wpdb;
		
		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->prefix}actionscheduler_actions 
			WHERE status = 'complete' 
			AND scheduled_date_gmt < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
			LIMIT 250000"
		);
		
		if ( $deleted > 0 ) {
			WTA_Logger::debug( "Cleanup: Deleted $deleted completed actions (5min+ old)" );
		}
	}

	/**
	 * Schedule timezone monitoring job (every 30 minutes).
	 * 
	 * @since    3.0.58
	 */
	public function schedule_timezone_monitor() {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}
		
		if ( ! as_next_scheduled_action( 'wta_monitor_stuck_timezones' ) ) {
			as_schedule_recurring_action( 
				time(), 
				30 * MINUTE_IN_SECONDS, // Every 30 minutes
				'wta_monitor_stuck_timezones'
			);
		}
	}

	/**
	 * Monitor cities stuck without timezone data (passive monitoring).
	 * Logs warnings for manual investigation - NO auto-fix.
	 * 
	 * @since    3.0.58
	 */
	public function monitor_stuck_timezones() {
		global $wpdb;
		
		// Find draft cities with has_timezone = 0 for more than 2 hours
		$stuck_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_modified,
				lat.meta_value as latitude,
				lng.meta_value as longitude
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				LEFT JOIN {$wpdb->postmeta} lat ON p.ID = lat.post_id AND lat.meta_key = 'wta_latitude'
				LEFT JOIN {$wpdb->postmeta} lng ON p.ID = lng.post_id AND lng.meta_key = 'wta_longitude'
				WHERE p.post_type = %s
				AND p.post_status = 'draft'
				AND p.post_modified < DATE_SUB(NOW(), INTERVAL 2 HOUR)
				AND pm.meta_key = 'wta_has_timezone'
				AND pm.meta_value = '0'
				LIMIT 100",
				WTA_POST_TYPE
			)
		);
		
		if ( ! empty( $stuck_posts ) ) {
			$post_details = array();
			foreach ( $stuck_posts as $post ) {
				$post_details[] = sprintf(
					'ID: %d, Title: %s, Coords: %s,%s',
					$post->ID,
					$post->post_title,
					$post->latitude,
					$post->longitude
				);
			}
			
			WTA_Logger::error( '⚠️ Cities stuck without timezone for 2+ hours', array(
				'count'            => count( $stuck_posts ),
				'post_ids'         => array_column( $stuck_posts, 'ID' ),
				'details'          => $post_details,
				'action_required'  => 'Manual investigation needed. Check timezone lookup errors in logs.',
				'dashboard_warning' => 'Visible in dashboard',
			) );
		}
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
