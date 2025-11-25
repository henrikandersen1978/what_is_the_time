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

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 */
class WTA_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    WTA_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->register_upload_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the core plugin.
		 */
		require_once WTA_PLUGIN_DIR . 'includes/class-wta-loader.php';

		/**
		 * Helper classes
		 */
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-utils.php';
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-logger.php';
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-timezone-helper.php';
		require_once WTA_PLUGIN_DIR . 'includes/helpers/class-wta-file-uploader.php';

		/**
		 * Core classes
		 */
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-post-type.php';
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-queue.php';
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-github-fetcher.php';
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-importer.php';
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-queue-processor.php';
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-timezone-resolver.php';
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-ai-generator.php';
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-prompt-manager.php';

		/**
		 * Admin classes
		 */
		require_once WTA_PLUGIN_DIR . 'includes/admin/class-wta-admin.php';
		require_once WTA_PLUGIN_DIR . 'includes/admin/class-wta-settings.php';

		/**
		 * Cron classes
		 */
		require_once WTA_PLUGIN_DIR . 'includes/cron/class-wta-cron-manager.php';
		require_once WTA_PLUGIN_DIR . 'includes/cron/class-wta-cron-structure.php';
		require_once WTA_PLUGIN_DIR . 'includes/cron/class-wta-cron-timezone.php';
		require_once WTA_PLUGIN_DIR . 'includes/cron/class-wta-cron-ai.php';

		/**
		 * Frontend classes
		 */
		require_once WTA_PLUGIN_DIR . 'includes/frontend/class-wta-template-loader.php';
		require_once WTA_PLUGIN_DIR . 'includes/frontend/class-wta-shortcodes.php';

		$this->loader = new WTA_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			WTA_TEXT_DOMAIN,
			false,
			dirname( WTA_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_admin_hooks() {
		// Register custom post type
		$post_type = new WTA_Post_Type();
		$this->loader->add_action( 'init', $post_type, 'register_post_type' );

		// Admin interface
		$admin = new WTA_Admin();
		$this->loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

		// Settings
		$settings = new WTA_Settings();
		$this->loader->add_action( 'admin_init', $settings, 'register_settings' );

		// AJAX handlers
		$this->loader->add_action( 'wp_ajax_wta_prepare_import', $admin, 'ajax_prepare_import' );
		$this->loader->add_action( 'wp_ajax_wta_get_queue_stats', $admin, 'ajax_get_queue_stats' );
		$this->loader->add_action( 'wp_ajax_wta_reset_all_data', $admin, 'ajax_reset_all_data' );
		$this->loader->add_action( 'wp_ajax_wta_retry_failed', $admin, 'ajax_retry_failed' );
		$this->loader->add_action( 'wp_ajax_wta_test_api', $admin, 'ajax_test_api' );
	}

	/**
	 * Register file upload hooks directly (static methods).
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function register_upload_hooks() {
		add_action( 'wp_ajax_wta_upload_json', array( 'WTA_File_Uploader', 'handle_simple_upload' ) );
		add_action( 'wp_ajax_wta_upload_json_chunk', array( 'WTA_File_Uploader', 'handle_chunked_upload' ) );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_public_hooks() {
		// Template loader
		$template_loader = new WTA_Template_Loader();
		$this->loader->add_filter( 'template_include', $template_loader, 'load_template' );
		$this->loader->add_action( 'wp_enqueue_scripts', $template_loader, 'enqueue_assets' );

		// Shortcodes
		$shortcodes = new WTA_Shortcodes();
		$this->loader->add_action( 'init', $shortcodes, 'register_shortcodes' );
	}

	/**
	 * Register all of the hooks related to cron functionality.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function define_cron_hooks() {
		// Cron manager
		$cron_manager = new WTA_Cron_Manager();
		$this->loader->add_filter( 'cron_schedules', $cron_manager, 'add_custom_schedules' );

		// Structure import cron
		$cron_structure = new WTA_Cron_Structure();
		$this->loader->add_action( 'world_time_import_structure', $cron_structure, 'process' );

		// Timezone resolution cron
		$cron_timezone = new WTA_Cron_Timezone();
		$this->loader->add_action( 'world_time_resolve_timezones', $cron_timezone, 'process' );

		// AI content generation cron
		$cron_ai = new WTA_Cron_AI();
		$this->loader->add_action( 'world_time_generate_ai_content', $cron_ai, 'process' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since  1.0.0
	 * @return WTA_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}
}




