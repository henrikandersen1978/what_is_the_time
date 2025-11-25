<?php
/**
 * Fired during plugin activation.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes
 */

class WTA_Activator {

	/**
	 * Activate the plugin.
	 *
	 * - Create queue database table
	 * - Create persistent data directory
	 * - Set default options (use add_option to preserve existing)
	 * - Schedule Action Scheduler recurring actions
	 *
	 * @since 2.0.0
	 */
	public static function activate() {
		global $wpdb;

		// Create queue table
		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			type VARCHAR(50) NOT NULL,
			source_id VARCHAR(100),
			payload LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			last_error TEXT,
			attempts INT DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			INDEX idx_type_status (type, status),
			INDEX idx_status (status),
			INDEX idx_created (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Create persistent data directory
		self::create_data_directory();

		// Set default options - CRITICAL: Use add_option() to preserve existing settings
		self::set_default_options();

		// Register post type before flushing rewrite rules
		require_once WTA_PLUGIN_DIR . 'includes/core/class-wta-post-type.php';
		$post_type = new WTA_Post_Type();
		$post_type->register_post_type();

		// Flush rewrite rules for custom post type
		flush_rewrite_rules();

		// Schedule recurring Action Scheduler actions (after everything else)
		self::schedule_actions();

		// Update plugin version
		update_option( 'wta_plugin_version', WTA_VERSION );
	}

	/**
	 * Create persistent data directory with .htaccess protection.
	 *
	 * @since 2.0.0
	 */
	private static function create_data_directory() {
		$upload_dir = wp_upload_dir();
		$data_dir = $upload_dir['basedir'] . '/world-time-ai-data';

		// Create directory if it doesn't exist
		if ( ! file_exists( $data_dir ) ) {
			wp_mkdir_p( $data_dir );
		}

		// Create logs subdirectory
		$logs_dir = $data_dir . '/logs';
		if ( ! file_exists( $logs_dir ) ) {
			wp_mkdir_p( $logs_dir );
		}

		// Create .htaccess to protect data files
		$htaccess_file = $data_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# Protect World Time AI data files\n";
			$htaccess_content .= "<Files ~ \"\\.(json|log|txt)$\">\n";
			$htaccess_content .= "    Order allow,deny\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</Files>\n";
			file_put_contents( $htaccess_file, $htaccess_content );
		}
	}

	/**
	 * Set default plugin options.
	 * 
	 * CRITICAL: Use add_option() NOT update_option() to preserve user settings across updates!
	 *
	 * @since 2.0.0
	 */
	private static function set_default_options() {
		// Base settings
		add_option( 'wta_base_country_name', 'Danmark' );
		add_option( 'wta_base_timezone', 'Europe/Copenhagen' );
		add_option( 'wta_base_language', 'da-DK' );
		add_option( 'wta_base_language_description', 'Skriv på flydende dansk til danske brugere' );

		// Complex countries requiring timezone API lookup
		add_option( 'wta_complex_countries', 'US,CA,BR,RU,AU,MX,ID,CN,KZ,AR,GL,CD,SA,CL' );

		// GitHub data source URLs (optional if local files exist)
		add_option( 'wta_github_countries_url', 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/countries.json' );
		add_option( 'wta_github_states_url', 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/states.json' );
		add_option( 'wta_github_cities_url', 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/cities.json' );

		// TimeZoneDB API
		add_option( 'wta_timezonedb_api_key', '' );

		// OpenAI settings
		add_option( 'wta_openai_api_key', '' );
		add_option( 'wta_openai_model', 'gpt-4o-mini' );
		add_option( 'wta_openai_temperature', 0.7 );
		add_option( 'wta_openai_max_tokens', 2000 );

		// Import settings
		add_option( 'wta_selected_continents', array() );
		add_option( 'wta_min_population', 0 );
		add_option( 'wta_max_cities_per_country', 0 );

		// Default AI prompts
		self::set_default_prompts();
	}

	/**
	 * Set default AI prompts.
	 *
	 * @since 2.0.0
	 */
	private static function set_default_prompts() {
		// Translate location name
		add_option( 'wta_prompt_translate_name_system', 'Du er en professionel oversætter der oversætter stednavne til dansk.' );
		add_option( 'wta_prompt_translate_name_user', 'Oversæt "{location_name}" til dansk. Svar kun med det oversatte navn, ingen forklaring.' );

		// City page title
		add_option( 'wta_prompt_city_title_system', 'Du er en SEO ekspert der skriver fængende sider på dansk.' );
		add_option( 'wta_prompt_city_title_user', 'Skriv en fængende H1 titel for en side om hvad klokken er i {location_name_local}. Brug formatet "Hvad er klokken i [by]?"' );

		// City page content
		add_option( 'wta_prompt_city_content_system', '{base_language_description}. Du skriver informativt og SEO-venligt indhold om byer.' );
		add_option( 'wta_prompt_city_content_user', 'Skriv 200-300 ord om {location_name_local} i {country_name}. Inkluder tidszonen ({timezone}) og interessante fakta om byen.' );

		// Country page title
		add_option( 'wta_prompt_country_title_system', 'Du er en SEO ekspert der skriver fængende sider på dansk.' );
		add_option( 'wta_prompt_country_title_user', 'Skriv en fængende H1 titel for en side om hvad klokken er i {location_name_local}.' );

		// Country page content
		add_option( 'wta_prompt_country_content_system', '{base_language_description}. Du skriver informativt og SEO-venligt indhold om lande.' );
		add_option( 'wta_prompt_country_content_user', 'Skriv 300-400 ord om {location_name_local} i {continent_name}. Inkluder tidszoner og interessante fakta om landet.' );

		// Continent page title
		add_option( 'wta_prompt_continent_title_system', 'Du er en SEO ekspert der skriver fængende sider på dansk.' );
		add_option( 'wta_prompt_continent_title_user', 'Skriv en fængende H1 titel for en side om hvad klokken er i {location_name_local}.' );

		// Continent page content
		add_option( 'wta_prompt_continent_content_system', '{base_language_description}. Du skriver informativt og SEO-venligt indhold om kontinenter.' );
		add_option( 'wta_prompt_continent_content_user', 'Skriv 400-500 ord om {location_name_local}. Inkluder tidszoner og interessante fakta om kontinentet.' );

		// Yoast SEO title
		add_option( 'wta_prompt_yoast_title_system', 'Du er en SEO ekspert der skriver meta titles på dansk.' );
		add_option( 'wta_prompt_yoast_title_user', 'Skriv en SEO meta title (50-60 tegn) for en side om hvad klokken er i {location_name_local}.' );

		// Yoast meta description
		add_option( 'wta_prompt_yoast_desc_system', 'Du er en SEO ekspert der skriver meta descriptions på dansk.' );
		add_option( 'wta_prompt_yoast_desc_user', 'Skriv en SEO meta description (140-160 tegn) for en side om hvad klokken er i {location_name_local}.' );
	}

	/**
	 * Schedule Action Scheduler recurring actions.
	 *
	 * @since 2.0.0
	 */
	private static function schedule_actions() {
		// Only schedule if Action Scheduler is available
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		// Process structure queue (continents, countries, cities) - Every 5 minutes
		if ( false === as_next_scheduled_action( 'wta_process_structure' ) ) {
			as_schedule_recurring_action( time(), 5 * MINUTE_IN_SECONDS, 'wta_process_structure', array(), 'world-time-ai' );
		}

		// Process timezone resolution - Every 5 minutes
		if ( false === as_next_scheduled_action( 'wta_process_timezone' ) ) {
			as_schedule_recurring_action( time(), 5 * MINUTE_IN_SECONDS, 'wta_process_timezone', array(), 'world-time-ai' );
		}

		// Process AI content generation - Every 5 minutes
		if ( false === as_next_scheduled_action( 'wta_process_ai_content' ) ) {
			as_schedule_recurring_action( time(), 5 * MINUTE_IN_SECONDS, 'wta_process_ai_content', array(), 'world-time-ai' );
		}
	}
}

