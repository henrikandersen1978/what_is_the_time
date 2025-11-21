<?php
/**
 * Fired during plugin activation.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 */
class WTA_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables and sets up default options.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::schedule_cron_events();
		
		// Flush rewrite rules to register our custom post type URLs
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 *
	 * @since 1.0.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . WTA_QUEUE_TABLE;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(50) NOT NULL,
			source_id bigint(20) unsigned DEFAULT NULL,
			payload longtext DEFAULT NULL,
			status varchar(20) DEFAULT 'pending',
			last_error text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY type (type),
			KEY created_at (created_at),
			KEY type_status (type, status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store the database version
		update_option( 'wta_db_version', WTA_VERSION );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		// GitHub data source URLs (default to dr5hn/countries-states-cities-database)
		add_option( 'wta_github_countries_url', 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/countries.json' );
		add_option( 'wta_github_states_url', 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/states.json' );
		add_option( 'wta_github_cities_url', 'https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/cities.json' );

		// TimeZoneDB API
		add_option( 'wta_timezonedb_api_key', '' );
		
		// Complex countries requiring individual timezone lookups
		$complex_countries = array(
			'US' => 'United States',
			'CA' => 'Canada',
			'BR' => 'Brazil',
			'AU' => 'Australia',
			'RU' => 'Russia',
			'MX' => 'Mexico',
			'ID' => 'Indonesia',
			'CN' => 'China',
		);
		add_option( 'wta_complex_countries', $complex_countries );

		// Base country and language settings
		add_option( 'wta_base_country_name', 'Denmark' );
		add_option( 'wta_base_timezone', 'Europe/Copenhagen' );
		add_option( 'wta_base_language', 'da-DK' );
		add_option( 'wta_base_language_description', 'Skriv på flydende dansk til danske brugere' );

		// OpenAI settings
		add_option( 'wta_openai_api_key', '' );
		add_option( 'wta_openai_model', 'gpt-4' );
		add_option( 'wta_openai_temperature', 0.7 );
		add_option( 'wta_openai_max_tokens', 1000 );

		// Import filters
		add_option( 'wta_selected_continents', array() );
		add_option( 'wta_min_population', 0 );
		add_option( 'wta_max_cities_per_country', 0 ); // 0 means no limit

		// Yoast SEO integration
		add_option( 'wta_yoast_integration_enabled', true );
		add_option( 'wta_yoast_allow_overwrite', true );

		// Default prompts - all 9 prompts with system and user prompts
		self::set_default_prompts();
	}

	/**
	 * Set default AI prompts.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_prompts() {
		$prompts = array(
			'translate_location_name' => array(
				'system' => 'You are a professional translator specializing in geographical names and locations.',
				'user'   => 'Translate the following location name to {base_language}.

Location name: {location_name}
Location type: {location_type}
Country: {country_name}

Instructions:
- {base_language_description}
- Use the most common and natural translation for this location
- If the location name is already in the target language or is a proper noun that doesn\'t translate, return it unchanged
- Return ONLY the translated name, no explanations

Translated name:',
			),
			'city_page_title' => array(
				'system' => 'You are an SEO copywriter creating engaging page titles for a world time website.',
				'user'   => 'Create a natural, engaging page title for a webpage about the current time in a city.

City: {location_name_local}
Country: {country_name}
Timezone: {timezone}

Instructions:
- {base_language_description}
- The title should be engaging and SEO-friendly
- Target search intent: "what time is it in [city]?"
- Length: 40-60 characters
- Include the city name
- Make it natural and conversational
- Return ONLY the title, no explanations

Page title:',
			),
			'city_page_content' => array(
				'system' => 'You are a professional content writer specializing in travel and world information.',
				'user'   => 'Write engaging, informative content for a webpage showing the current time in a city.

City: {location_name_local}
Country: {country_name}
Continent: {continent_name}
Timezone: {timezone}

Instructions:
- {base_language_description}
- Write 200-300 words
- Include information about:
  * Brief introduction to the city
  * Why people might need to know the time there
  * Timezone information (natural, not technical)
  * Time difference considerations (business hours, calling times, etc.)
- Use HTML formatting: <p>, <h2>, <h3>, <strong>, <em>
- Be informative but conversational
- SEO-optimized but natural
- Do NOT include the current time (that will be displayed separately)
- Return ONLY the HTML content, no explanations

Content:',
			),
			'country_page_title' => array(
				'system' => 'You are an SEO copywriter creating engaging page titles for a world time website.',
				'user'   => 'Create a natural, engaging page title for a webpage about time zones and current time in a country.

Country: {location_name_local}
Continent: {continent_name}

Instructions:
- {base_language_description}
- The title should be engaging and SEO-friendly
- Target search intent: "what time is it in [country]?"
- Length: 40-60 characters
- Include the country name
- Make it natural and conversational
- Return ONLY the title, no explanations

Page title:',
			),
			'country_page_content' => array(
				'system' => 'You are a professional content writer specializing in travel and world information.',
				'user'   => 'Write engaging, informative content for a webpage showing time information for a country.

Country: {location_name_local}
Continent: {continent_name}

Instructions:
- {base_language_description}
- Write 300-400 words
- Include information about:
  * Brief introduction to the country
  * Timezone overview (if multiple timezones, mention that)
  * Why people might need to know the time there
  * Business and communication considerations
  * Cultural aspects related to time (if relevant)
- Use HTML formatting: <p>, <h2>, <h3>, <strong>, <em>
- Be informative but conversational
- SEO-optimized but natural
- Return ONLY the HTML content, no explanations

Content:',
			),
			'continent_page_title' => array(
				'system' => 'You are an SEO copywriter creating engaging page titles for a world time website.',
				'user'   => 'Create a natural, engaging page title for a webpage about time zones and current time across a continent.

Continent: {location_name_local}

Instructions:
- {base_language_description}
- The title should be engaging and SEO-friendly
- Target search intent: "what time is it in [continent]?"
- Length: 40-60 characters
- Include the continent name
- Make it natural and conversational
- Return ONLY the title, no explanations

Page title:',
			),
			'continent_page_content' => array(
				'system' => 'You are a professional content writer specializing in travel and world information.',
				'user'   => 'Write engaging, informative content for a webpage showing time information for a continent.

Continent: {location_name_local}

Instructions:
- {base_language_description}
- Write 400-500 words
- Include information about:
  * Overview of the continent
  * Timezone diversity across the region
  * Major time zones in the continent
  * International business and travel considerations
  * Interesting facts about time zones in this region
- Use HTML formatting: <p>, <h2>, <h3>, <strong>, <em>
- Be informative but conversational
- SEO-optimized but natural
- Return ONLY the HTML content, no explanations

Content:',
			),
			'yoast_seo_title' => array(
				'system' => 'You are an SEO specialist creating optimized meta titles.',
				'user'   => 'Create an SEO-optimized meta title for a webpage about the current time in a location.

Location: {location_name_local}
Location type: {location_type}
Country: {country_name}

Instructions:
- {base_language_description}
- Length: 50-60 characters (strict limit for search engines)
- Include the location name
- Should target "what time is it in [location]" search intent
- Include relevant keywords naturally
- Make it compelling for click-through
- Return ONLY the meta title, no explanations

Meta title:',
			),
			'yoast_meta_description' => array(
				'system' => 'You are an SEO specialist creating optimized meta descriptions.',
				'user'   => 'Create an SEO-optimized meta description for a webpage about the current time in a location.

Location: {location_name_local}
Location type: {location_type}
Country: {country_name}
Continent: {continent_name}

Instructions:
- {base_language_description}
- Length: 140-160 characters (strict limit for search engines)
- Include the location name
- Should target "what time is it in [location]" search intent
- Include a call-to-action or value proposition
- Make it compelling for click-through
- Be specific and informative
- Return ONLY the meta description, no explanations

Meta description:',
			),
		);

		foreach ( $prompts as $prompt_id => $prompt_data ) {
			add_option( "wta_prompt_{$prompt_id}_system", $prompt_data['system'] );
			add_option( "wta_prompt_{$prompt_id}_user", $prompt_data['user'] );
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @since 1.0.0
	 */
	private static function schedule_cron_events() {
		// These will be registered by the cron manager
		// We just ensure they don't exist yet to avoid duplicates
		$events = array(
			'world_time_import_structure',
			'world_time_resolve_timezones',
			'world_time_generate_ai_content',
		);

		foreach ( $events as $event ) {
			if ( ! wp_next_scheduled( $event ) ) {
				wp_schedule_event( time(), 'wta_five_minutes', $event );
			}
		}
	}
}




