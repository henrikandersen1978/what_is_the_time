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
			__( 'Shortcode Settings', WTA_TEXT_DOMAIN ),
			__( 'Shortcode Settings', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-shortcode-settings',
			array( $this, 'display_shortcode_settings_page' )
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

		add_submenu_page(
			'world-time-ai',
			__( 'Force Regenerate', WTA_TEXT_DOMAIN ),
			__( '🚀 Force Regenerate', WTA_TEXT_DOMAIN ),
			'manage_options',
			'wta-force-regenerate',
			array( $this, 'display_force_regenerate_page' )
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
	 * Display shortcode settings page.
	 *
	 * @since    3.0.23
	 */
	public function display_shortcode_settings_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/shortcode-settings.php';
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
	 * Display Force Regenerate page.
	 *
	 * @since    2.35.22
	 */
	public function display_force_regenerate_page() {
		include WTA_PLUGIN_DIR . 'includes/admin/views/force-regenerate.php';
	}

	/**
	 * AJAX: Prepare import queue.
	 *
	 * @since    2.0.0
	 */
	public function ajax_prepare_import() {
		// v3.2.27: Add debug logging to see if AJAX is working
		WTA_Logger::info( 'AJAX prepare_import called!', array(
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
		) );
		
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			WTA_Logger::error( 'AJAX prepare_import UNAUTHORIZED!', array(
				'user_id' => get_current_user_id(),
			) );
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$import_mode = isset( $_POST['import_mode'] ) ? sanitize_text_field( $_POST['import_mode'] ) : 'continents';
		$selected_continents = isset( $_POST['selected_continents'] ) ? array_map( 'sanitize_text_field', $_POST['selected_continents'] ) : array();
		$selected_countries = isset( $_POST['selected_countries'] ) ? array_map( 'sanitize_text_field', $_POST['selected_countries'] ) : array();
		$min_population = isset( $_POST['min_population'] ) ? intval( $_POST['min_population'] ) : 0;
		$max_cities_per_country = isset( $_POST['max_cities_per_country'] ) ? intval( $_POST['max_cities_per_country'] ) : 0;
		$clear_queue = isset( $_POST['clear_queue'] ) && 'yes' === $_POST['clear_queue'];

		WTA_Logger::info( 'Calling WTA_Importer::prepare_import()...', array(
			'import_mode' => $import_mode,
			'selected_countries' => $selected_countries,
			'min_population' => $min_population,
			'max_cities_per_country' => $max_cities_per_country,
		) );
		
		$stats = WTA_Importer::prepare_import( array(
			'import_mode'            => $import_mode,
			'selected_continents'    => $selected_continents,
			'selected_countries'     => $selected_countries,
			'min_population'         => $min_population,
			'max_cities_per_country' => $max_cities_per_country,
			'clear_queue'            => $clear_queue,
		) );

		WTA_Logger::info( 'WTA_Importer::prepare_import() COMPLETED!', array(
			'stats' => $stats,
			'result_type' => gettype( $stats ),
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
	 * @since    3.0.1  Optimized for large datasets (150k+ posts) with direct SQL queries
	 */
	public function ajax_reset_all_data() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		global $wpdb;
		
		// Increase timeout for large datasets
		set_time_limit( 300 ); // 5 minutes
		
		$start_time = microtime( true );
		
		// Count posts before deletion
		$count_query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			WTA_POST_TYPE
		);
		$total_posts = intval( $wpdb->get_var( $count_query ) );
		
		if ( $total_posts > 0 ) {
			// Get all post IDs (lightweight query)
			$ids_query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
				WTA_POST_TYPE
			);
			$post_ids = $wpdb->get_col( $ids_query );
			
			if ( ! empty( $post_ids ) ) {
				$ids_string = implode( ',', array_map( 'intval', $post_ids ) );
				
				// Delete post meta (faster than wp_delete_post loop)
				$wpdb->query(
					"DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($ids_string)"
				);
				
				// Delete posts
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->posts} WHERE post_type = %s",
					WTA_POST_TYPE
				) );
				
				// Clean up relationships
				$wpdb->query(
					"DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($ids_string)"
				);
			}
		}
		
	// Clear queue
	WTA_Queue::clear();
	
	// v3.2.58: CRITICAL - Clear ALL Action Scheduler actions when resetting data
	// Without this, 15K+ pending/in-progress actions continue processing deleted posts!
	$wta_hooks = array(
		'wta_create_continent',
		'wta_create_country',
		'wta_schedule_cities',
		'wta_create_city',
		'wta_lookup_timezone',
		'wta_generate_ai_content',
		'wta_start_waiting_city_processing',
	);
	
	$cleared_actions = 0;
	foreach ( $wta_hooks as $hook ) {
		// Unschedule pending/scheduled actions
		$cleared = as_unschedule_all_actions( $hook );
		if ( $cleared > 0 ) {
			$cleared_actions += $cleared;
		}
	}
	
	// v3.2.60: DEBUG - Check what's actually in the database
	$hooks_string = "'" . implode( "','", array_map( 'esc_sql', $wta_hooks ) ) . "'";
	
	// Check all WTA actions regardless of status
	$all_wta_actions = $wpdb->get_results(
		"SELECT status, COUNT(*) as count 
		 FROM {$wpdb->prefix}actionscheduler_actions 
		 WHERE hook IN ($hooks_string) 
		 GROUP BY status",
		ARRAY_A
	);
	
	WTA_Logger::info( 'DEBUG: All WTA actions by status', array(
		'breakdown' => $all_wta_actions,
		'table' => $wpdb->prefix . 'actionscheduler_actions',
	) );
	
	// v3.2.60: Use Action Scheduler's own API to delete actions
	// SQL queries might not work due to Action Scheduler's internal structure
	$deleted_count = 0;
	
	foreach ( $wta_hooks as $hook ) {
		// Get ALL action IDs for this hook (any status)
		$action_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT action_id FROM {$wpdb->prefix}actionscheduler_actions WHERE hook = %s",
				$hook
			)
		);
		
		if ( ! empty( $action_ids ) ) {
			foreach ( $action_ids as $action_id ) {
				try {
					// Use Action Scheduler's store to delete the action
					ActionScheduler_Store::instance()->delete_action( $action_id );
					$deleted_count++;
				} catch ( Exception $e ) {
					WTA_Logger::warning( 'Failed to delete action', array(
						'action_id' => $action_id,
						'hook' => $hook,
						'error' => $e->getMessage(),
					) );
				}
			}
		}
	}
	
	$cleared_actions += $deleted_count;
	
	WTA_Logger::info( 'Action Scheduler actions cleared via API', array(
		'unscheduled_pending' => 0,
		'deleted_via_api' => $deleted_count,
		'total_cleared' => $cleared_actions,
		'hooks_cleared' => count( $wta_hooks ),
	) );
	
	// v3.2.22: Clear GeoNames translation cache to force fresh re-parsing on next import
	// This ensures that new imports use correct language translations, not stale cache
	$geonames_cache_deleted = $wpdb->query(
		"DELETE FROM {$wpdb->options} 
		 WHERE option_name LIKE '_transient_wta_geonames_translations_%' 
		    OR option_name LIKE '_transient_timeout_wta_geonames_translations_%'"
	);
	
	if ( $geonames_cache_deleted > 0 ) {
		WTA_Logger::info( 'GeoNames translation cache cleared', array(
			'rows_deleted' => $geonames_cache_deleted,
		) );
	}
	
	// Clear WordPress caches
	wp_cache_flush();
		
		$execution_time = round( microtime( true ) - $start_time, 2 );
		
	WTA_Logger::info( 'All data reset by user (SQL optimized)', array(
		'posts_deleted'     => $total_posts,
		'actions_cleared'   => $cleared_actions,
		'execution_time'    => $execution_time . 's',
		'method'            => 'direct_sql',
	) );

	wp_send_json_success( array(
		'message' => sprintf( 
			'All data has been reset successfully (%d posts deleted, %d actions cleared in %s seconds)', 
			$total_posts,
			$cleared_actions,
			$execution_time 
		),
		'deleted' => $total_posts,
		'actions_cleared' => $cleared_actions,
		'time'    => $execution_time,
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
	 * AJAX: Regenerate ALL AI Content (v2.34.20).
	 * 
	 * Queues ai_content jobs for ALL location posts.
	 * Use case: Switching from test mode to normal mode, or after prompt changes.
	 *
	 * @since    2.34.20
	 */
	public function ajax_regenerate_all_ai() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		// Get ALL published location posts
		$posts = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => -1, // ALL posts
			'post_status'    => 'publish',
			'fields'         => 'ids', // Only IDs for performance
		) );

		if ( empty( $posts ) ) {
			wp_send_json_error( array( 
				'message' => 'No published location posts found to regenerate.'
			) );
		}

		$queued = 0;
		$skipped = 0;

		foreach ( $posts as $post_id ) {
			$type = get_post_meta( $post_id, 'wta_type', true );

			if ( ! $type || ! in_array( $type, array( 'continent', 'country', 'city' ), true ) ) {
				$skipped++;
				continue;
			}

			// Queue AI content generation
			$source_id = 'ai_content_' . $type . '_' . $post_id;
			
			WTA_Queue::add(
				'ai_content',
				array(
					'post_id' => $post_id,
					'type'    => $type,
				),
				$source_id
			);

			$queued++;
		}

		$test_mode = get_option( 'wta_test_mode', 0 );
		$mode_warning = $test_mode ? ' ⚠️ Note: Test Mode is ENABLED - template content will be used (no API costs).' : '';

		wp_send_json_success( array(
			'message' => sprintf(
				'Successfully queued %d posts for AI content regeneration! %s%s',
				$queued,
				$skipped > 0 ? " ($skipped posts skipped due to missing type)" : '',
				$mode_warning
			),
			'queued'  => $queued,
			'skipped' => $skipped,
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

		// Clear WordPress transient cache
		WTA_AI_Translator::clear_cache();

		// v3.2.30: CRITICAL - Clear PHP OpCache so new code is used!
		// Without this, updated plugin code won't be used until server restart
		$opcache_cleared = false;
		if ( function_exists( 'opcache_reset' ) ) {
			$opcache_cleared = opcache_reset();
			WTA_Logger::info( 'PHP OpCache cleared (ensures new plugin code is used)', array(
				'success' => $opcache_cleared,
			) );
		}

		WTA_Logger::info( 'Translation cache cleared by user', array(
			'opcache_cleared' => $opcache_cleared ? 'yes' : 'no (function not available)',
		) );

		$message = 'Translation cache has been cleared. New imports will use fresh translations.';
		if ( $opcache_cleared ) {
			$message .= ' PHP OpCache also cleared - new plugin code is now active!';
		}

		wp_send_json_success( array(
			'message' => $message,
		) );
	}

	/**
	 * AJAX: Clear shortcode cache.
	 *
	 * @since    2.35.51
	 */
	public function ajax_clear_shortcode_cache() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

	global $wpdb;
	
	// Delete all WTA shortcode transients
	$deleted = $wpdb->query( "
		DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE '_transient_wta_child_locations_%'
		OR option_name LIKE '_transient_timeout_wta_child_locations_%'
		OR option_name LIKE '_transient_wta_nearby_cities_%'
		OR option_name LIKE '_transient_timeout_wta_nearby_cities_%'
		OR option_name LIKE '_transient_wta_major_cities_%'
		OR option_name LIKE '_transient_timeout_wta_major_cities_%'
		OR option_name LIKE '_transient_wta_global_time_%'
		OR option_name LIKE '_transient_timeout_wta_global_time_%'
		OR option_name LIKE '_transient_wta_global_cities_%'
		OR option_name LIKE '_transient_timeout_wta_global_cities_%'
		OR option_name LIKE '_transient_wta_continent_EU_%'
		OR option_name LIKE '_transient_timeout_wta_continent_EU_%'
		OR option_name LIKE '_transient_wta_continent_AS_%'
		OR option_name LIKE '_transient_timeout_wta_continent_AS_%'
		OR option_name LIKE '_transient_wta_continent_NA_%'
		OR option_name LIKE '_transient_timeout_wta_continent_NA_%'
		OR option_name LIKE '_transient_wta_continent_SA_%'
		OR option_name LIKE '_transient_timeout_wta_continent_SA_%'
		OR option_name LIKE '_transient_wta_continent_AF_%'
		OR option_name LIKE '_transient_timeout_wta_continent_AF_%'
		OR option_name LIKE '_transient_wta_continent_OC_%'
		OR option_name LIKE '_transient_timeout_wta_continent_OC_%'
		OR option_name LIKE '_transient_wta_comparison_intro_%'
		OR option_name LIKE '_transient_timeout_wta_comparison_intro_%'
		OR option_name LIKE '_transient_wta_continent_data_%'
		OR option_name LIKE '_transient_timeout_wta_continent_data_%'
		OR option_name LIKE '_transient_wta_regional_centres_%'
		OR option_name LIKE '_transient_timeout_wta_regional_centres_%'
	" );

		WTA_Logger::info( 'Shortcode cache cleared by user (' . $deleted . ' entries)' );

		wp_send_json_success( array(
			'message' => 'Shortcode cache has been cleared (' . $deleted . ' entries). Pages will regenerate on next visit.',
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
			
			// Get fresh permalink (our filter will apply)
			$new_permalink = get_permalink( $post_id );
			
			WTA_Logger::debug( 'Permalink regenerated', array(
				'post_id'   => $post_id,
				'permalink' => $new_permalink,
			) );
			
			$updated++;
		}

		// Clear object cache
		wp_cache_flush();
		
		// Trigger Yoast SEO reindex (simple way)
		if ( function_exists( 'YoastSEO' ) ) {
			delete_transient( 'wpseo_sitemap_cache_validator' );
		}

		WTA_Logger::info( 'Permalink regeneration completed', array(
			'updated_posts' => $updated,
		) );

		wp_send_json_success( array(
			'message' => 'Permalinks regenerated successfully! ✅ Now go to Yoast SEO → Tools → "Optimize SEO Data" to update Yoast cache.',
			'updated' => $updated,
		) );
	}

	/**
	 * Force reschedule recurring actions (AJAX handler).
	 * 
	 * Manually triggers reschedule of all recurring actions with current interval setting.
	 * Useful when actions are not automatically rescheduled after changing cron interval.
	 *
	 * @since    2.35.33
	 */
	public function ajax_force_reschedule() {
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		// Check if Action Scheduler is available
		if ( ! function_exists( 'as_unschedule_all_actions' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			wp_send_json_error( array( 'message' => 'Action Scheduler not available' ) );
		}

		// v3.0.46: Pilanto-AI Model does not use recurring actions
		// This endpoint is deprecated and should not be used
		WTA_Logger::warning( 'force_reschedule_actions called but deprecated in v3.0.43+' );
		
		wp_send_json_error( array( 
			'message' => '⚠️ This function is deprecated in v3.0.43+. Pilanto-AI model uses single on-demand actions scheduled during import, not recurring actions. Please use "Start Import" instead.' 
		) );
	}

	// v3.0.19: Country GPS migration removed - no longer needed
	// GeoNames migration uses post_parent hierarchy for city-to-country relationships
	// Regional centres shortcode works directly with city GPS data

	/**
	 * Add custom admin columns for content status.
	 *
	 * @since    2.34.8
	 * @param    array $columns Existing columns.
	 * @return   array          Modified columns.
	 */
	public function add_content_status_column( $columns ) {
		// Insert after title column
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['content_status'] = __( 'Content Status', WTA_TEXT_DOMAIN );
			}
		}
		return $new_columns;
	}

	/**
	 * Display content status in admin column.
	 *
	 * @since    2.34.8
	 * @param    string $column  Column name.
	 * @param    int    $post_id Post ID.
	 */
	public function display_content_status_column( $column, $post_id ) {
		if ( 'content_status' !== $column ) {
			return;
		}

		$status = $this->check_content_completeness( $post_id );
		
		if ( $status['complete'] ) {
			echo '<span style="color: #46b450; font-size: 18px;" title="Content is complete">✅</span>';
		} else {
			$issues = implode( ', ', $status['issues'] );
			echo '<span style="color: #dc3232; font-size: 18px;" title="Issues: ' . esc_attr( $issues ) . '">❌</span>';
			echo '<div style="font-size: 11px; color: #666; margin-top: 3px;">' . esc_html( $issues ) . '</div>';
		}
	}

	/**
	 * Check if post content is complete.
	 *
	 * @since    2.34.8
	 * @param    int $post_id Post ID.
	 * @return   array        Status and issues.
	 */
	private function check_content_completeness( $post_id ) {
		$issues = array();
		
		// Check post content
		$content = get_post_field( 'post_content', $post_id );
		if ( empty( $content ) ) {
			$issues[] = 'No content';
		} elseif ( strlen( $content ) < 500 ) {
			$issues[] = 'Short content (' . strlen( $content ) . ' chars)';
		}
		
		// Check Yoast title
		$yoast_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
		if ( empty( $yoast_title ) ) {
			$issues[] = 'No SEO title';
		}
		
		// Check Yoast description
		$yoast_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( empty( $yoast_desc ) ) {
			$issues[] = 'No SEO desc';
		}
		
		return array(
			'complete' => empty( $issues ),
			'issues'   => $issues,
		);
	}

	/**
	 * Add bulk action for regenerating content.
	 *
	 * @since    2.34.8
	 * @param    array $actions Existing bulk actions.
	 * @return   array          Modified bulk actions.
	 */
	public function add_regenerate_bulk_action( $actions ) {
		$actions['regenerate_ai_content'] = __( 'Regenerate AI Content', WTA_TEXT_DOMAIN );
		return $actions;
	}

	/**
	 * Handle regenerate content bulk action.
	 *
	 * @since    2.34.8
	 * @param    string $redirect_to Redirect URL.
	 * @param    string $doaction    Action being taken.
	 * @param    array  $post_ids    Post IDs being acted upon.
	 * @return   string              Modified redirect URL.
	 */
	public function handle_regenerate_bulk_action( $redirect_to, $doaction, $post_ids ) {
		if ( 'regenerate_ai_content' !== $doaction ) {
			return $redirect_to;
		}

		$regenerated = 0;
		
		foreach ( $post_ids as $post_id ) {
			// Verify this is a location post
			if ( WTA_POST_TYPE !== get_post_type( $post_id ) ) {
				continue;
			}
			
			// Get location type
			$type = get_post_meta( $post_id, 'wta_type', true );
			if ( empty( $type ) ) {
				continue;
			}
			
		// Reset AI status to trigger regeneration
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );
		
		// Add to AI content queue with force_ai flag
		// force_ai=true ignores test mode for manual single-post regeneration
		WTA_Queue::add(
			'ai_content',
			array(
				'post_id' => $post_id,
				'type'    => $type,
				'force_ai' => true, // Ignore test mode for manual regeneration
			),
			'regenerate_' . $post_id
		);
		
		$regenerated++;
			
			WTA_Logger::info( 'Post queued for AI content regeneration', array(
				'post_id' => $post_id,
				'type'    => $type,
			) );
		}

		// Add count to redirect URL
		$redirect_to = add_query_arg( 'regenerated', $regenerated, $redirect_to );
		
		return $redirect_to;
	}

	/**
	 * Display admin notice after bulk regeneration.
	 *
	 * @since    2.34.8
	 */
	public function display_regenerate_admin_notice() {
		if ( ! isset( $_GET['regenerated'] ) ) {
			return;
		}

		$count = intval( $_GET['regenerated'] );
		
		if ( $count > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: number of posts queued for regeneration */
					_n(
						'%d post has been queued for AI content regeneration.',
						'%d posts have been queued for AI content regeneration.',
						$count,
						WTA_TEXT_DOMAIN
					),
					$count
				)
			);
		}
	}

	/**
	 * Add content completeness filter dropdown.
	 *
	 * @since    2.34.9
	 */
	public function add_content_filter_dropdown() {
		global $typenow;
		
		if ( WTA_POST_TYPE !== $typenow ) {
			return;
		}

		$current_filter = isset( $_GET['content_status_filter'] ) ? $_GET['content_status_filter'] : '';
		
		?>
		<select name="content_status_filter">
			<option value=""><?php esc_html_e( 'All Content Status', WTA_TEXT_DOMAIN ); ?></option>
			<option value="complete" <?php selected( $current_filter, 'complete' ); ?>>
				✅ <?php esc_html_e( 'Complete', WTA_TEXT_DOMAIN ); ?>
			</option>
			<option value="incomplete" <?php selected( $current_filter, 'incomplete' ); ?>>
				❌ <?php esc_html_e( 'Incomplete', WTA_TEXT_DOMAIN ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Filter posts by content completeness.
	 *
	 * @since    2.34.9
	 * @param    object $query The WP_Query instance.
	 */
	public function filter_posts_by_content_status( $query ) {
		global $pagenow, $typenow;
		
		// Only on admin post list page for our post type
		if ( ! is_admin() || 'edit.php' !== $pagenow || WTA_POST_TYPE !== $typenow || ! $query->is_main_query() ) {
			return;
		}

		if ( ! isset( $_GET['content_status_filter'] ) || empty( $_GET['content_status_filter'] ) ) {
			return;
		}

		$filter = $_GET['content_status_filter'];
		
		if ( 'complete' === $filter ) {
			// Show only posts with complete content
			// We'll use a meta query + post content length check
			add_filter( 'posts_where', array( $this, 'filter_complete_content_where' ), 10, 2 );
			
		} elseif ( 'incomplete' === $filter ) {
			// Show only posts with incomplete content
			add_filter( 'posts_where', array( $this, 'filter_incomplete_content_where' ), 10, 2 );
		}
	}

	/**
	 * Add WHERE clause for complete content filter.
	 *
	 * @since    2.34.9
	 * @param    string $where Current WHERE clause.
	 * @param    object $query The WP_Query instance.
	 * @return   string        Modified WHERE clause.
	 */
	public function filter_complete_content_where( $where, $query ) {
		global $wpdb;
		
		// Only apply once
		remove_filter( 'posts_where', array( $this, 'filter_complete_content_where' ), 10 );
		
		// Posts with content > 500 chars AND have Yoast meta
		$where .= " AND LENGTH({$wpdb->posts}.post_content) >= 500";
		$where .= " AND {$wpdb->posts}.ID IN (
			SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_yoast_wpseo_title' 
			AND meta_value IS NOT NULL 
			AND meta_value != ''
		)";
		$where .= " AND {$wpdb->posts}.ID IN (
			SELECT post_id FROM {$wpdb->postmeta} 
			WHERE meta_key = '_yoast_wpseo_metadesc' 
			AND meta_value IS NOT NULL 
			AND meta_value != ''
		)";
		
		return $where;
	}

	/**
	 * Add WHERE clause for incomplete content filter.
	 *
	 * @since    2.34.9
	 * @param    string $where Current WHERE clause.
	 * @param    object $query The WP_Query instance.
	 * @return   string        Modified WHERE clause.
	 */
	public function filter_incomplete_content_where( $where, $query ) {
		global $wpdb;
		
		// Only apply once
		remove_filter( 'posts_where', array( $this, 'filter_incomplete_content_where' ), 10 );
		
		// Posts with short/no content OR missing Yoast meta
		$where .= " AND (
			LENGTH({$wpdb->posts}.post_content) < 500
			OR {$wpdb->posts}.post_content IS NULL
			OR {$wpdb->posts}.ID NOT IN (
				SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_yoast_wpseo_title' 
				AND meta_value IS NOT NULL 
				AND meta_value != ''
			)
			OR {$wpdb->posts}.ID NOT IN (
				SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_yoast_wpseo_metadesc' 
				AND meta_value IS NOT NULL 
				AND meta_value != ''
			)
		)";
		
		return $where;
	}
}


