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
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_action_scheduler_hooks();
		
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
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-github-fetcher.php';
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-importer.php';

		// Helpers
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-logger.php';
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-utils.php';
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-timezone-helper.php';
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-quick-translate.php';
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-ai-translator.php';

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
	 * Register all admin-related hooks.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		// Register custom post type
		$post_type = new WTA_Post_Type();
		$this->loader->add_action( 'init', $post_type, 'register_post_type' );

		// Admin class
		$admin = new WTA_Admin();
		$this->loader->add_action( 'admin_menu', $admin, 'add_plugin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_notices', $admin, 'show_admin_notices' );

		// AJAX handlers
		$this->loader->add_action( 'wp_ajax_wta_prepare_import', $admin, 'ajax_prepare_import' );
		$this->loader->add_action( 'wp_ajax_wta_get_queue_stats', $admin, 'ajax_get_queue_stats' );
		$this->loader->add_action( 'wp_ajax_wta_test_openai_connection', $admin, 'ajax_test_openai_connection' );
		$this->loader->add_action( 'wp_ajax_wta_test_timezonedb_connection', $admin, 'ajax_test_timezonedb_connection' );
		$this->loader->add_action( 'wp_ajax_wta_reset_all_data', $admin, 'ajax_reset_all_data' );
		$this->loader->add_action( 'wp_ajax_wta_view_queue_details', $admin, 'ajax_view_queue_details' );
		$this->loader->add_action( 'wp_ajax_wta_reset_stuck_items', $admin, 'ajax_reset_stuck_items' );
		$this->loader->add_action( 'wp_ajax_wta_retry_failed_items', $admin, 'ajax_retry_failed_items' );
		$this->loader->add_action( 'wp_ajax_wta_get_logs', $admin, 'ajax_get_logs' );
		$this->loader->add_action( 'wp_ajax_wta_clear_translation_cache', $admin, 'ajax_clear_translation_cache' );

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

		// Shortcodes
		$shortcodes = new WTA_Shortcodes();
		$this->loader->add_action( 'init', $shortcodes, 'register_shortcodes' );
	}

	/**
	 * Register Action Scheduler hooks.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_action_scheduler_hooks() {
		// Structure processor
		$structure_processor = new WTA_Structure_Processor();
		$this->loader->add_action( 'wta_process_structure', $structure_processor, 'process_batch' );

		// Timezone processor
		$timezone_processor = new WTA_Timezone_Processor();
		$this->loader->add_action( 'wta_process_timezone', $timezone_processor, 'process_batch' );

		// AI processor
		$ai_processor = new WTA_AI_Processor();
		$this->loader->add_action( 'wta_process_ai_content', $ai_processor, 'process_batch' );
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

		// Check and schedule missing actions
		foreach ( $required_actions as $action ) {
			if ( false === as_next_scheduled_action( $action ) ) {
				as_schedule_recurring_action( time(), MINUTE_IN_SECONDS, $action, array(), 'world-time-ai' );
				
				WTA_Logger::info( "Auto-scheduled missing action: $action" );
			}
		}
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
