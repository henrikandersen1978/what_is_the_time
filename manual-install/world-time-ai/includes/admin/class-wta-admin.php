<?php
/**
 * Admin interface registration and handlers.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin
 */

/**
 * Admin class.
 *
 * @since 1.0.0
 */
class WTA_Admin {

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'World Time AI', WTA_TEXT_DOMAIN ),
			__( 'World Time AI', WTA_TEXT_DOMAIN ),
			'manage_options',
			'world-time-ai',
			array( $this, 'render_dashboard' ),
			'dashicons-clock',
			20
		);

		// Dashboard (same as main menu)
		add_submenu_page(
			'world-time-ai',
			__( 'Dashboard', WTA_TEXT_DOMAIN ),
			__( 'Dashboard', WTA_TEXT_DOMAIN ),
			'manage_options',
			'world-time-ai',
			array( $this, 'render_dashboard' )
		);

		// Data & Import
		add_submenu_page(
			'world-time-ai',
			__( 'Data & Import', WTA_TEXT_DOMAIN ),
			__( 'Data & Import', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-import',
			array( $this, 'render_import' )
		);

		// AI Settings
		add_submenu_page(
			'world-time-ai',
			__( 'AI Settings', WTA_TEXT_DOMAIN ),
			__( 'AI Settings', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-ai-settings',
			array( $this, 'render_ai_settings' )
		);

		// Prompts
		add_submenu_page(
			'world-time-ai',
			__( 'Prompts', WTA_TEXT_DOMAIN ),
			__( 'Prompts', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-prompts',
			array( $this, 'render_prompts' )
		);

		// Timezone & Language
		add_submenu_page(
			'world-time-ai',
			__( 'Timezone & Language', WTA_TEXT_DOMAIN ),
			__( 'Timezone & Language', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-timezone-language',
			array( $this, 'render_timezone_language' )
		);

		// Tools & Logs
		add_submenu_page(
			'world-time-ai',
			__( 'Tools & Logs', WTA_TEXT_DOMAIN ),
			__( 'Tools & Logs', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-tools',
			array( $this, 'render_tools' )
		);

		// Add locations submenu (links to CPT)
		add_submenu_page(
			'world-time-ai',
			__( 'All Locations', WTA_TEXT_DOMAIN ),
			__( 'All Locations', WTA_TEXT_DOMAIN ),
			'edit_pages',
			'edit.php?post_type=' . WTA_POST_TYPE
		);
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		if ( strpos( $hook, 'world-time-ai' ) === false && strpos( $hook, 'wta-' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'wta-admin',
			WTA_PLUGIN_URL . 'includes/admin/assets/css/admin.css',
			array(),
			WTA_VERSION
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'world-time-ai' ) === false && strpos( $hook, 'wta-' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'wta-admin',
			WTA_PLUGIN_URL . 'includes/admin/assets/js/admin.js',
			array( 'jquery' ),
			WTA_VERSION,
			true
		);

		wp_localize_script(
			'wta-admin',
			'wtaAdmin',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wta_admin_nonce' ),
				'strings' => array(
					'confirm_reset' => __( 'Are you sure you want to reset all data? This will delete all imported locations and clear the queue.', WTA_TEXT_DOMAIN ),
					'processing'    => __( 'Processing...', WTA_TEXT_DOMAIN ),
					'success'       => __( 'Success!', WTA_TEXT_DOMAIN ),
					'error'         => __( 'Error:', WTA_TEXT_DOMAIN ),
				),
			)
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard() {
		require_once WTA_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
	}

	/**
	 * Render import page.
	 *
	 * @since 1.0.0
	 */
	public function render_import() {
		require_once WTA_PLUGIN_DIR . 'includes/admin/views/data-import.php';
	}

	/**
	 * Render AI settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_ai_settings() {
		require_once WTA_PLUGIN_DIR . 'includes/admin/views/ai-settings.php';
	}

	/**
	 * Render prompts page.
	 *
	 * @since 1.0.0
	 */
	public function render_prompts() {
		require_once WTA_PLUGIN_DIR . 'includes/admin/views/prompts.php';
	}

	/**
	 * Render timezone & language page.
	 *
	 * @since 1.0.0
	 */
	public function render_timezone_language() {
		require_once WTA_PLUGIN_DIR . 'includes/admin/views/timezone-language.php';
	}

	/**
	 * Render tools page.
	 *
	 * @since 1.0.0
	 */
	public function render_tools() {
		require_once WTA_PLUGIN_DIR . 'includes/admin/views/tools.php';
	}

	/**
	 * AJAX: Prepare import.
	 *
	 * @since 1.0.0
	 */
	public function ajax_prepare_import() {
		check_ajax_referer( 'wta_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', WTA_TEXT_DOMAIN ) ) );
		}

		$selected_continents = isset( $_POST['continents'] ) ? array_map( 'sanitize_text_field', $_POST['continents'] ) : array();
		$min_population = isset( $_POST['min_population'] ) ? intval( $_POST['min_population'] ) : 0;
		$max_cities = isset( $_POST['max_cities'] ) ? intval( $_POST['max_cities'] ) : 0;
		$clear_existing = isset( $_POST['clear_existing'] ) && $_POST['clear_existing'] === 'true';

		$result = WTA_Importer::prepare_import( array(
			'selected_continents'    => $selected_continents,
			'min_population'         => $min_population,
			'max_cities_per_country' => $max_cities,
			'clear_existing'         => $clear_existing,
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Import queue prepared successfully!', WTA_TEXT_DOMAIN ),
			'stats'   => $result,
		) );
	}

	/**
	 * AJAX: Get queue stats.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_queue_stats() {
		check_ajax_referer( 'wta_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', WTA_TEXT_DOMAIN ) ) );
		}

		$stats = WTA_Queue::get_stats();
		wp_send_json_success( array( 'stats' => $stats ) );
	}

	/**
	 * AJAX: Reset all data.
	 *
	 * @since 1.0.0
	 */
	public function ajax_reset_all_data() {
		check_ajax_referer( 'wta_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', WTA_TEXT_DOMAIN ) ) );
		}

		// Delete all location posts
		$posts = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		// Clear queue
		WTA_Queue::clear_all();

		// Clear cache
		WTA_Github_Fetcher::clear_cache();

		wp_send_json_success( array(
			'message' => __( 'All data has been reset.', WTA_TEXT_DOMAIN ),
			'deleted' => count( $posts ),
		) );
	}

	/**
	 * AJAX: Retry failed items.
	 *
	 * @since 1.0.0
	 */
	public function ajax_retry_failed() {
		check_ajax_referer( 'wta_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', WTA_TEXT_DOMAIN ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . WTA_QUEUE_TABLE;
		
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET status = 'pending', last_error = NULL, updated_at = %s WHERE status = 'error'",
				current_time( 'mysql' )
			)
		);

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of items */
				__( 'Reset %d failed items to pending.', WTA_TEXT_DOMAIN ),
				$updated
			),
			'count'   => $updated,
		) );
	}

	/**
	 * AJAX: Test API connection.
	 *
	 * @since 1.0.0
	 */
	public function ajax_test_api() {
		check_ajax_referer( 'wta_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', WTA_TEXT_DOMAIN ) ) );
		}

		$api_type = isset( $_POST['api_type'] ) ? sanitize_text_field( $_POST['api_type'] ) : '';

		if ( $api_type === 'openai' ) {
			$result = WTA_AI_Generator::test_api();
		} elseif ( $api_type === 'timezonedb' ) {
			$result = WTA_Timezone_Resolver::test_api();
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid API type.', WTA_TEXT_DOMAIN ) ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'API connection successful!', WTA_TEXT_DOMAIN ) ) );
	}

	/**
	 * AJAX handler to clear GitHub update cache.
	 *
	 * @since 1.0.0
	 */
	public function ajax_clear_update_cache() {
		check_ajax_referer( 'wta_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', WTA_TEXT_DOMAIN ) ) );
		}

		// Clear the GitHub updater cache
		$cache_key = 'wta_github_release_' . md5( WTA_GITHUB_REPO );
		$result    = delete_transient( $cache_key );

		if ( $result ) {
			error_log( '[WTA GitHub Updater] Cache manually cleared via admin tools' );
			wp_send_json_success( array(
				'message' => __( 'Update cache cleared successfully! Visit the Plugins page to check for updates.', WTA_TEXT_DOMAIN ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Failed to clear cache or cache was already empty.', WTA_TEXT_DOMAIN ),
			) );
		}
	}
}






