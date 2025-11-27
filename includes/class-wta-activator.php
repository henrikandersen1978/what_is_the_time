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
		// NOTE: Translation is now handled by WTA_AI_Translator before post creation
		// These old translate prompts are kept for backward compatibility but no longer used
		add_option( 'wta_prompt_translate_name_system', 'Du er en professionel oversætter der oversætter stednavne til dansk.' );
		add_option( 'wta_prompt_translate_name_user', 'Oversæt "{location_name}" til dansk. Svar kun med det oversatte navn, ingen forklaring.' );

		// City page title
		add_option( 'wta_prompt_city_title_system', 'Du er en SEO ekspert der skriver fængende sider på dansk.' );
		add_option( 'wta_prompt_city_title_user', 'Skriv en fængende H1 titel for en side om hvad klokken er i {location_name_local}. Brug formatet "Hvad er klokken i [by]?"' );

		// City page content
		add_option( 'wta_prompt_city_content_system', '{base_language_description}. Du skriver naturligt, autentisk og informativt indhold om byer. Undgå klichéer, generiske indledninger og kunstige vendinger.' );
		add_option( 'wta_prompt_city_content_user', 'Skriv 200-300 ord om {location_name_local} i {country_name}. Tidszonen er {timezone}. Inkluder konkrete, interessante fakta om byen. Undgå fraser som "velkommen til", "lad os udforske", "i denne artikel" og lignende. Skriv direkte og naturligt.' );

		// Country page title
		add_option( 'wta_prompt_country_title_system', 'Du er en SEO ekspert der skriver fængende sider på dansk.' );
		add_option( 'wta_prompt_country_title_user', 'Skriv en fængende H1 titel for en side om hvad klokken er i {location_name_local}.' );

		// Country page content
		add_option( 'wta_prompt_country_content_system', '{base_language_description}. Du skriver naturligt, autentisk og informativt indhold om lande. Undgå klichéer, generiske indledninger og kunstige vendinger.' );
		add_option( 'wta_prompt_country_content_user', 'Skriv 300-400 ord om {location_name_local} i {continent_name}. Inkluder konkrete fakta om tidszoner, geografi og kultur. Undgå fraser som "velkommen til", "lad os udforske", "i denne artikel" og lignende. Skriv direkte og naturligt.' );

		// Continent page - Section 1: Introduction
		add_option( 'wta_prompt_continent_intro_system', 'Du er en SEO-ekspert der skriver naturligt dansk indhold om tidszoner og geografi. Skriv informativt og direkte til danske brugere. VIGTIG: Skriv KUN ren tekst uden overskrifter, uden markdown, uden ChatGPT-fraser som "velkommen til" eller "lad os udforske". Alle sætninger SKAL afsluttes korrekt - ingen afskæring midt i sætning. Brug KORTE, varierede sætninger for god læsbarhed. Teksten opdeles automatisk i paragraffer.' );
		add_option( 'wta_prompt_continent_intro_user', 'Skriv en SEO-optimeret introduktion på 120-150 ord om hvad klokken er i {location_name_local}. FOKUS: Besvaring af søgeintentionen "hvad er klokken i {location_name_local}". Inkluder naturligt disse SEO-søgeord: "hvad er klokken i {location_name_local}", "tidszoner i {location_name_local}", "aktuel tid i {location_name_local}". Start direkte med information (ikke "velkommen" eller lignende). Nævn kort: antal tidszoner, geografisk udstrækning, største lande. Skriv med korte, varierede sætninger for god læsbarhed. Veksl mellem korte (5-10 ord) og lidt længere sætninger (15-20 ord). Afslut ALLE sætninger korrekt. KUN ren tekst - ingen overskrifter, ingen markdown.' );
		
		// Continent page - Section 2: Timezones
		add_option( 'wta_prompt_continent_timezone_system', 'Du er ekspert i internationale tidszoner og tidsberegning. Skriv præcist og faktabaseret om tidszoner til danske brugere. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. Brug varierede sætningslængder for god læsbarhed.' );
		add_option( 'wta_prompt_continent_timezone_user', 'Skriv et detaljeret afsnit på 150-200 ord om tidszonerne i {location_name_local}. Indhold der SKAL inkluderes: 1) Liste de vigtigste tidszoner (fx CET, EET, GMT+X), 2) Forklar tidsforskelle i forhold til dansk tid (CET/CEST), 3) Nævn om sommertid/vintertid anvendes, 4) Eventuelle særlige tidszoner (halve timer, etc.). SEO-fokus på: "tidszoner i {location_name_local}" og "tidsforskelle {location_name_local}". Skriv med varierede sætningslængder - både korte (5-10 ord) og lidt længere (15-20 ord). Afslut ALLE sætninger korrekt - ingen afskæring. KUN ren tekst - ingen overskrifter.' );
		
		// Continent page - Section 3: Major Cities
		add_option( 'wta_prompt_continent_cities_system', 'Du er ekspert i verdens storbyer og deres tidszoner. Skriv engagerende om byer og deres lokale tid. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown, ingen lister. Alle sætninger SKAL afsluttes korrekt.' );
		add_option( 'wta_prompt_continent_cities_user', 'Skriv et afsnit på 100-120 ord om de største byer i {location_name_local} og deres tidszoner. Du får automatisk liste over byerne - beskriv kort: Hvilke tidszoner de ligger i, Hvordan tiden adskiller sig mellem byerne (hvis relevant), Eventuelle interessante tidszone-aspekter for rejsende. SEO-fokus på: "hvad er klokken i [by-navn]". Skriv naturligt og rejsevejleder-agtigt. Afslut ALLE sætninger korrekt. KUN ren tekst - ingen overskrifter, ingen punktopstillinger. Kontekst: {cities_list}' );
		
		// Continent page - Section 4: Geography
		add_option( 'wta_prompt_continent_geography_system', 'Du er geografi-ekspert med fokus på hvordan geografi påvirker tid. Skriv kortfattet og faktuelt. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt.' );
		add_option( 'wta_prompt_continent_geography_user', 'Skriv et kort afsnit på 80-100 ord om geografien i {location_name_local} og hvordan det påvirker tidszoner. Inkluder: Geografisk udstrækning (øst-vest især relevant for tidszoner), Størrelse i km² eller sammenligning, Hvorfor geografien giver X antal tidszoner. Skriv faktabaseret men tilgængeligt. Afslut ALLE sætninger korrekt. KUN ren tekst - ingen overskrifter.' );
		
		// Continent page - Section 5: Facts
		add_option( 'wta_prompt_continent_facts_system', 'Du er ekspert i kultur, historie og interessante fakta om tid og tidszoner. Skriv engagerende og lærerigt om tidszoner-relaterede fakta. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt.' );
		add_option( 'wta_prompt_continent_facts_user', 'Skriv et afsnit på 100-120 ord med interessante fakta SPECIFIKT om tidszoner og tid i {location_name_local}. Fokuser på: Særlige tidszoner (fx halve eller kvart timers forskelle), Historiske ændringer i tidszoner, Kulturelle aspekter af tid (fx siesta, arbejdstider), Fun facts om tid og tidszoner. UNDGÅ generiske fakta om kultur/historie der ikke relaterer til tid. Skriv engagerende og informativt. Afslut ALLE sætninger korrekt. KUN ren tekst - ingen overskrifter.' );

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

		// Process structure queue (continents, countries, cities) - Every 1 minute
		if ( false === as_next_scheduled_action( 'wta_process_structure' ) ) {
			as_schedule_recurring_action( time(), MINUTE_IN_SECONDS, 'wta_process_structure', array(), 'world-time-ai' );
		}

		// Process timezone resolution - Every 1 minute
		if ( false === as_next_scheduled_action( 'wta_process_timezone' ) ) {
			as_schedule_recurring_action( time(), MINUTE_IN_SECONDS, 'wta_process_timezone', array(), 'world-time-ai' );
		}

		// Process AI content generation - Every 1 minute
		if ( false === as_next_scheduled_action( 'wta_process_ai_content' ) ) {
			as_schedule_recurring_action( time(), MINUTE_IN_SECONDS, 'wta_process_ai_content', array(), 'world-time-ai' );
		}
	}
}

