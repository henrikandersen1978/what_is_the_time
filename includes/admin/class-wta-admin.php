<?php
/**
 * Admin functionality.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin
 */

class WTA_Admin {

	/**
	 * Add plugin admin menu.
	 *
	 * @since    2.0.0
	 */
	public function add_plugin_menu() {
		add_menu_page(
			__( 'World Time AI', WTA_TEXT_DOMAIN ),
			__( 'World Time AI', WTA_TEXT_DOMAIN ),
			'manage_options',
			'world-time-ai',
			array( $this, 'display_dashboard_page' ),
			'dashicons-clock',
			30
		);

		add_submenu_page(
			'world-time-ai',
			__( 'Dashboard', WTA_TEXT_DOMAIN ),
			__( 'Dashboard', WTA_TEXT_DOMAIN ),
			'manage_options',
			'world-time-ai',
			array( $this, 'display_dashboard_page' )
		);

		add_submenu_page(
			'world-time-ai',
			__( 'Data & Import', WTA_TEXT_DOMAIN ),
			__( 'Data & Import', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-data-import',
			array( $this, 'display_data_import_page' )
		);

		add_submenu_page(
			'world-time-ai',
			__( 'AI Settings', WTA_TEXT_DOMAIN ),
			__( 'AI Settings', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-ai-settings',
			array( $this, 'display_ai_settings_page' )
		);

		add_submenu_page(
			'world-time-ai',
			__( 'Prompts', WTA_TEXT_DOMAIN ),
			__( 'Prompts', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-prompts',
			array( $this, 'display_prompts_page' )
		);

		add_submenu_page(
			'world-time-ai',
			__( 'Timezone & Language', WTA_TEXT_DOMAIN ),
			__( 'Timezone & Language', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-timezone-language',
			array( $this, 'display_timezone_language_page' )
		);

		add_submenu_page(
			'world-time-ai',
			__( 'Tools', WTA_TEXT_DOMAIN ),
			__( 'Tools', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-tools',
			array( $this, 'display_tools_page' )
		);

		add_submenu_page(
			'world-time-ai',
			__( 'Debug Info', WTA_TEXT_DOMAIN ),
			__( 'Debug Info', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-debug',
			array( $this, 'display_debug_page' )
		);

		// Add submenu for All Locations (CPT)
		add_submenu_page(
			'world-time-ai',
			__( 'All Locations', WTA_TEXT_DOMAIN ),
			__( 'All Locations', WTA_TEXT_DOMAIN ),
			'manage_options',
			'edit.php?post_type=' . WTA_POST_TYPE
		);
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( strpos( $screen->id, 'world-time-ai' ) !== false || strpos( $screen->id, 'wta-' ) !== false ) {
			wp_enqueue_style(
				'wta-admin',
				WTA_PLUGIN_URL . 'includes/admin/assets/css/admin.css',
				array(),
				WTA_VERSION
			);
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( strpos( $screen->id, 'world-time-ai' ) !== false || strpos( $screen->id, 'wta-' ) !== false ) {
			wp_enqueue_script(
				'wta-admin',
				WTA_PLUGIN_URL . 'includes/admin/assets/js/admin.js',
				array( 'jquery' ),
				WTA_VERSION,
				true
			);

			wp_localize_script( 'wta-admin', 'wtaAdmin', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wta-admin-nonce' ),
			) );
		}
	}

	/**
	 * Display admin notices.
	 *
	 * @since    2.0.0
	 */
	public function show_admin_notices() {
		// Show upgrade notice
		$upgrade_notice = get_transient( 'wta_upgraded_notice' );
		if ( $upgrade_notice ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'World Time AI', WTA_TEXT_DOMAIN ); ?></strong>
					<?php
					printf(
						/* translators: 1: old version, 2: new version */
						esc_html__( 'has been upgraded from version %1$s to %2$s', WTA_TEXT_DOMAIN ),
						esc_html( $upgrade_notice['from'] ),
						esc_html( $upgrade_notice['to'] )
					);
					?>
				</p>
			</div>
			<?php
			delete_transient( 'wta_upgraded_notice' );
		}

		// Show API key warnings
		$screen = get_current_screen();
		if ( strpos( $screen->id, 'world-time-ai' ) !== false || strpos( $screen->id, 'wta-' ) !== false ) {
			$openai_key = get_option( 'wta_openai_api_key', '' );
			$timezonedb_key = get_option( 'wta_timezonedb_api_key', '' );

			if ( empty( $openai_key ) ) {
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'OpenAI API key not configured.', WTA_TEXT_DOMAIN ); ?></strong>
						<?php esc_html_e( 'AI content generation will not work until you configure your API key.', WTA_TEXT_DOMAIN ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wta-ai-settings' ) ); ?>">
							<?php esc_html_e( 'Configure now', WTA_TEXT_DOMAIN ); ?>
						</a>
					</p>
				</div>
				<?php
			}

			if ( empty( $timezonedb_key ) ) {
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'TimeZoneDB API key not configured.', WTA_TEXT_DOMAIN ); ?></strong>
						<?php esc_html_e( 'Timezone resolution for complex countries will not work.', WTA_TEXT_DOMAIN ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wta-timezone-language' ) ); ?>">
							<?php esc_html_e( 'Configure now', WTA_TEXT_DOMAIN ); ?>
						</a>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Display dashboard page.
	 *
	 * @since    2.0.0
	 */
	public function display_dashboard_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
	}

	/**
	 * Display data & import page.
	 *
	 * @since    2.0.0
	 */
	public function display_data_import_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/data-import.php';
	}

	/**
	 * Display AI settings page.
	 *
	 * @since    2.0.0
	 */
	public function display_ai_settings_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/ai-settings.php';
	}

	/**
	 * Display prompts page.
	 *
	 * @since    2.0.0
	 */
	public function display_prompts_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/prompts.php';
	}

	/**
	 * Display timezone & language page.
	 *
	 * @since    2.0.0
	 */
	public function display_timezone_language_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/timezone-language.php';
	}

	/**
	 * Display tools page.
	 *
	 * @since    2.0.0
	 */
	public function display_tools_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/tools.php';
	}

	/**
	 * Display debug page.
	 *
	 * @since    2.0.0
	 */
	public function display_debug_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/debug.php';
	}

	/**
	 * AJAX: Prepare import queue.
	 *
	 * @since    2.0.0
	 */
	public function ajax_prepare_import() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$import_mode = isset( $_POST['import_mode'] ) ? sanitize_text_field( $_POST['import_mode'] ) : 'continents';
		$selected_continents = isset( $_POST['selected_continents'] ) ? array_map( 'sanitize_text_field', $_POST['selected_continents'] ) : array();
		$selected_countries = isset( $_POST['selected_countries'] ) ? array_map( 'sanitize_text_field', $_POST['selected_countries'] ) : array();
		$min_population = isset( $_POST['min_population'] ) ? intval( $_POST['min_population'] ) : 0;
		$max_cities_per_country = isset( $_POST['max_cities_per_country'] ) ? intval( $_POST['max_cities_per_country'] ) : 0;
		$clear_queue = isset( $_POST['clear_queue'] ) && 'yes' === $_POST['clear_queue'];

		$stats = WTA_Importer::prepare_import( array(
			'import_mode'            => $import_mode,
			'selected_continents'    => $selected_continents,
			'selected_countries'     => $selected_countries,
			'min_population'         => $min_population,
			'max_cities_per_country' => $max_cities_per_country,
			'clear_queue'            => $clear_queue,
		) );

		wp_send_json_success( array(
			'message' => 'Import queue prepared successfully!',
			'stats'   => $stats,
		) );
	}

	/**
	 * AJAX: Get queue statistics.
	 *
	 * @since    2.0.0
	 */
	public function ajax_get_queue_stats() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$stats = WTA_Queue::get_stats();

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Test OpenAI connection.
	 *
	 * @since    2.0.0
	 */
	public function ajax_test_openai_connection() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'API key not configured' ) );
		}

		// Simple test request
		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => 'gpt-4o-mini',
				'messages'   => array(
					array( 'role' => 'user', 'content' => 'Say "Hello"' ),
				),
				'max_tokens' => 10,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			wp_send_json_success( array( 'message' => 'Connection successful!' ) );
		} else {
			wp_send_json_error( array( 'message' => 'API returned unexpected response' ) );
		}
	}

	/**
	 * AJAX: Test TimeZoneDB connection.
	 *
	 * @since    2.0.0
	 */
	public function ajax_test_timezonedb_connection() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$api_key = get_option( 'wta_timezonedb_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'API key not configured' ) );
		}

		// Test with Copenhagen coordinates
		$timezone = WTA_Timezone_Helper::resolve_timezone_api( 55.6761, 12.5683 );

		if ( false === $timezone ) {
			wp_send_json_error( array( 'message' => 'API call failed' ) );
		}

		wp_send_json_success( array(
			'message'  => 'Connection successful!',
			'timezone' => $timezone,
		) );
	}

	/**
	 * AJAX: Reset all data.
	 *
	 * @since    2.0.0
	 */
	public function ajax_reset_all_data() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		// Delete all location posts
		$posts = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'numberposts' => -1,
			'post_status' => 'any',
		) );

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		// Clear queue
		WTA_Queue::clear();

		WTA_Logger::info( 'All data reset by user', array(
			'posts_deleted' => count( $posts ),
		) );

		wp_send_json_success( array(
			'message' => 'All data has been reset',
			'deleted' => count( $posts ),
		) );
	}

	/**
	 * AJAX: View queue details.
	 *
	 * @since    2.4.2
	 */
	public function ajax_view_queue_details() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		// Get last 10 cities_import jobs
		$cities_import_jobs = $wpdb->get_results(
			"SELECT * FROM $table_name 
			WHERE type = 'cities_import' 
			ORDER BY id DESC 
			LIMIT 5",
			ARRAY_A
		);

		// Get queue summary
		$summary = $wpdb->get_results(
			"SELECT type, status, COUNT(*) as count 
			FROM $table_name 
			GROUP BY type, status 
			ORDER BY type, status",
			ARRAY_A
		);

		$html = '<h3>Queue Summary</h3>';
		$html .= '<table class="widefat">';
		$html .= '<thead><tr><th>Type</th><th>Status</th><th>Count</th></tr></thead><tbody>';
		foreach ( $summary as $row ) {
			$html .= sprintf(
				'<tr><td>%s</td><td>%s</td><td>%d</td></tr>',
				esc_html( $row['type'] ),
				esc_html( $row['status'] ),
				intval( $row['count'] )
			);
		}
		$html .= '</tbody></table>';

		$html .= '<h3 style="margin-top: 20px;">Last 5 cities_import Jobs</h3>';
		if ( empty( $cities_import_jobs ) ) {
			$html .= '<p>No cities_import jobs found in queue.</p>';
		} else {
			$html .= '<table class="widefat">';
			$html .= '<thead><tr><th>ID</th><th>Status</th><th>Attempts</th><th>Error</th><th>Created</th><th>Payload</th></tr></thead><tbody>';
			foreach ( $cities_import_jobs as $job ) {
				$payload = json_decode( $job['payload'], true );
				$html .= sprintf(
					'<tr><td>%d</td><td><strong>%s</strong></td><td>%d</td><td style="color:red;">%s</td><td>%s</td><td><pre style="font-size:10px;">%s</pre></td></tr>',
					intval( $job['id'] ),
					esc_html( $job['status'] ),
					intval( $job['attempts'] ),
					esc_html( $job['error_message'] ? $job['error_message'] : '-' ),
					esc_html( $job['created_at'] ),
					esc_html( print_r( $payload, true ) )
				);
			}
			$html .= '</tbody></table>';
		}

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX: Reset stuck queue items.
	 *
	 * @since    2.3.9
	 */
	public function ajax_reset_stuck_items() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$count = WTA_Queue::reset_stuck();

		wp_send_json_success( array(
			'message' => "Reset $count stuck jobs to pending",
			'count'   => $count,
		) );
	}

	/**
	 * AJAX: Retry failed queue items.
	 *
	 * @since    2.0.0
	 */
	public function ajax_retry_failed_items() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$count = WTA_Queue::retry_failed();

		wp_send_json_success( array(
			'message' => "Reset $count failed items to pending",
			'count'   => $count,
		) );
	}

	/**
	 * AJAX: Get recent logs.
	 *
	 * @since    2.0.0
	 */
	public function ajax_get_logs() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$logs = WTA_Logger::get_recent_logs( 100 );

		wp_send_json_success( array( 'logs' => $logs ) );
	}

	/**
	 * AJAX: Clear translation cache.
	 *
	 * @since    2.0.0
	 */
	public function ajax_clear_translation_cache() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		WTA_AI_Translator::clear_cache();

		WTA_Logger::info( 'Translation cache cleared by user' );

		wp_send_json_success( array(
			'message' => 'Translation cache has been cleared. New imports will use fresh translations.',
		) );
	}

	/**
	 * AJAX: Regenerate all permalinks for location posts.
	 *
	 * @since    2.28.7
	 */
	public function ajax_regenerate_permalinks() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		// Increase time limit for large datasets
		set_time_limit( 300 ); // 5 minutes

		$updated = 0;

		// Get all location posts
		$args = array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$post_ids = get_posts( $args );

		WTA_Logger::info( 'Starting permalink regeneration', array(
			'total_posts' => count( $post_ids ),
		) );

	foreach ( $post_ids as $post_id ) {
		// Clear post cache
		clean_post_cache( $post_id );

		// Clear permalink cache
		delete_post_meta( $post_id, '_wp_old_slug' );

		// Clear ALL Yoast SEO caches if exists
		if ( class_exists( 'WPSEO_Options' ) ) {
			// Clear canonical meta
			delete_post_meta( $post_id, '_yoast_wpseo_canonical' );
			
			// Clear ALL Yoast post meta that might contain URLs
			delete_post_meta( $post_id, '_yoast_wpseo_opengraph-title' );
			delete_post_meta( $post_id, '_yoast_wpseo_opengraph-description' );
			delete_post_meta( $post_id, '_yoast_wpseo_twitter-title' );
			delete_post_meta( $post_id, '_yoast_wpseo_twitter-description' );
			
			// Clear Yoast indexables (they cache permalinks in separate table)
			if ( class_exists( 'Yoast\WP\SEO\Repositories\Indexable_Repository' ) ) {
				try {
					$indexable_repository = YoastSEO()->classes->get( 'Yoast\WP\SEO\Repositories\Indexable_Repository' );
					$indexable = $indexable_repository->find_by_id_and_type( $post_id, 'post' );
					if ( $indexable ) {
						// Force indexable to rebuild by deleting it
						$indexable_repository->delete( $indexable );
					}
				} catch ( Exception $e ) {
					WTA_Logger::warning( 'Failed to clear Yoast indexable', array(
						'post_id' => $post_id,
						'error' => $e->getMessage(),
					) );
				}
			}
			
			// Clear sitemap cache
			delete_transient( 'wpseo_sitemap_cache_' . $post_id );
		}

		// Force WordPress to regenerate permalink
		$post = get_post( $post_id );
		if ( $post ) {
			// Get fresh permalink (our filter will apply)
			$new_permalink = get_permalink( $post_id );

			WTA_Logger::debug( 'Permalink regenerated', array(
				'post_id'   => $post_id,
				'title'     => $post->post_title,
				'permalink' => $new_permalink,
			) );

			$updated++;
		}
	}

	// Clear object cache
	wp_cache_flush();

	// Clear ALL Yoast SEO caches globally
	if ( function_exists( 'YoastSEO' ) ) {
		// Clear sitemap cache
		delete_transient( 'wpseo_sitemap_cache_validator' );
		
		// Force Yoast to rebuild all indexables with new URLs
		if ( class_exists( 'Yoast\WP\SEO\Commands\Index_Command' ) ) {
			// Trigger indexables rebuild action
			do_action( 'wpseo_permalink_change' );
		}
		
		// Clear Yoast's internal caches
		wp_cache_delete( 'wpseo_', 'options' );
		
	// Clear all Yoast transients
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpseo_%' OR option_name LIKE '_transient_timeout_wpseo_%'" );
	
	WTA_Logger::info( 'Cleared all Yoast SEO caches and indexables' );
}

	WTA_Logger::info( 'Permalink regeneration completed', array(
		'updated_posts' => $updated,
	) );

	wp_send_json_success( array(
		'message' => 'Permalinks have been regenerated successfully! All internal links, schema, and Yoast data should now use clean URLs.',
		'updated' => $updated,
	) );
}


