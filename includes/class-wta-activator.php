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
			claim_id VARCHAR(32) DEFAULT NULL,
			last_error TEXT,
			attempts INT DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			INDEX idx_type_status (type, status),
			INDEX idx_status (status),
			INDEX idx_created (created_at),
			INDEX idx_claim_id (claim_id)
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

		// Install performance indices for postmeta queries (v2.35.46)
		self::install_performance_indices();

		// Add claim_id column to queue table (v3.0.41)
		self::add_claim_id_column();

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

		// Country page content (OLD - kept for backward compatibility)
		add_option( 'wta_prompt_country_content_system', '{base_language_description}. Du skriver naturligt, autentisk og informativt indhold om lande. Undgå klichéer, generiske indledninger og kunstige vendinger.' );
		add_option( 'wta_prompt_country_content_user', 'Skriv 300-400 ord om {location_name_local} i {continent_name}. Inkluder konkrete fakta om tidszoner, geografi og kultur. Undgå fraser som "velkommen til", "lad os udforske", "i denne artikel" og lignende. Skriv direkte og naturligt.' );

		// === COUNTRY PAGE TEMPLATE (Multi-section) ===
		
		// Country page - Section 1: Introduction
		add_option( 'wta_prompt_country_intro_system', 'Du er en SEO-ekspert der skriver naturligt dansk indhold om lande og tidszoner. Skriv informativt og direkte til danske brugere. VIGTIG: Skriv KUN ren tekst uden overskrifter, uden markdown, uden ChatGPT-fraser. Alle sætninger SKAL afsluttes korrekt. Brug KORTE, varierede sætninger for god læsbarhed. Teksten opdeles automatisk i paragraffer. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_country_intro_user', 'Skriv 2-3 korte afsnit (max 150 ord) der introducerer {location_name_local} i {continent_name}. Fokuser på tidszone, geografisk placering og hvad klokken er i landet lige nu. Nævn tidsforskel til {base_country_name} hvis relevant. Skriv direkte og konkret.

VIGTIGT: Skriv IKKE "velkommen til", "lad os udforske" eller lignende intro-fraser.' );

		// Country page - Section 2: Timezones
		add_option( 'wta_prompt_country_timezone_system', 'Du er ekspert i internationale tidszoner og tidsberegning. Skriv præcist og faktabaseret om tidszoner til danske brugere. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. Brug varierede sætningslængder for god læsbarhed. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_country_timezone_user', 'Forklar tidszoner i {location_name_local}. Landet bruger timezone: {timezone}. Skriv 2-3 afsnit der forklarer:
- Om landet har én eller flere tidszoner
- Om der bruges sommertid/vintertid
- Tidsforskel til {base_country_name}
- Konkrete eksempler på hvornår det er hvilken tid

VIGTIGT: Vær præcis og faktuel. Inkluder kun information du er sikker på.' );

		// Country page - Section 3: Major Cities
		add_option( 'wta_prompt_country_cities_system', 'Du er ekspert i byer og deres betydning for lande. Skriv engagerende om byer og deres rolle. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown, ingen lister. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_country_cities_user', 'Skriv 2 afsnit om de største byer i {location_name_local} og deres betydning: {cities_list}. 

Forklar hvordan byerne spiller forskellige roller:
- Hovedstad og administration
- Økonomiske centre og erhvervsliv
- Kulturelle og historiske betydning
- Befolkningsfordeling

VIGTIGT: Skriv IKKE "her er en liste" eller lignende. Gå direkte til indholdet.

Efter din tekst vil der automatisk blive indsat en dynamisk boks der viser live tid for de 12 største byer i landet med befolkningstal og tidsforskel.' );

		// Country page - Section 4: Weather & Climate
		add_option( 'wta_prompt_country_weather_system', 'Du er klima-ekspert der forklarer sammenhænge mellem vejr, klima og tid. Skriv engagerende og præcist. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_country_weather_user', 'Skriv 2 afsnit om vejr og klima i {location_name_local}, med fokus på hvordan det påvirker tid og dagligdag:
- Dagslængde gennem året (lange sommerdage, korte vinterdage)
- Solop og solned tider
- Særlige klimatiske forhold (midnatssol, mørketid, osv.)
- Hvordan klimaet påvirker hverdagen og aktiviteter

Gør det interessant og relevant for rejsende.' );

		// Country page - Section 5: Culture & Time
		add_option( 'wta_prompt_country_culture_system', 'Du er kultur-ekspert der beskriver hverdagsliv og sociale normer omkring tid. Skriv engagerende og indsigtsfuldt. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_country_culture_user', 'Skriv 2 afsnit om tidskultur og dagligdag i {location_name_local}:
- Typiske arbejdstider og arbejdskultur
- Måltider og spisetider (morgenmad, frokost, middag)
- Siesta eller andre særlige tidsvaner
- Butikkers åbningstider og hverdagsrytme
- Sammenlign kort med {base_country_name} hvor relevant

Gør det praktisk og kulturelt interessant.' );

		// Country page - Section 6: Travel Info
		add_option( 'wta_prompt_country_travel_system', 'Du er rejse-ekspert der giver praktiske og konkrete tips til rejsende. Skriv hjælpsomt og direkte. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown, ingen punktlister. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_country_travel_user', 'Skriv 2 afsnit med praktisk rejseinformation for danskere der rejser til {location_name_local}:
- Tidsforskel fra {base_country_name} og jetlag-tips
- Transport og rejsetider inden for landet
- Åbningstider for attraktioner og seværdigheder
- Bedste tidspunkt på dagen for forskellige aktiviteter
- Praktiske tips relateret til tid (booking, transport, osv.)

Vær konkret og brugbar - fokuser på ting rejsende faktisk har brug for at vide.' );

		// === CITY PAGE TEMPLATE (6 sections) ===
		
		// City page - Section 1: Intro
		add_option( 'wta_prompt_city_intro_system', 'Du er en faktuel rejseekspert. KRITISK: Skriv KUN om den SPECIFIKKE by der er angivet. Verificer ALTID byens placering i det angivne land med GPS-koordinater. Nævn ALDRIG andre byer med samme navn. Hvis du er usikker på facts, skriv IKKE om det. Fokuser på objektive, verificerbare facts. Undgå spekulationer og generaliseringer. Skriv kort, præcist og faktabaseret. KUN ren tekst, ingen overskrifter, ingen markdown. Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_city_intro_user', 'VERIFICÉR FØRST: Byen ligger i {country_name}, {continent_name}. Koordinater: {latitude}, {longitude}. Tidszone: {timezone}.

Skriv 2-3 korte afsnit om {location_name_local} i {country_name}.

Fokuser på:
- Byens beliggenhed i {country_name}
- Hvad byen er kendt for
- Tidszonen ({timezone})
- Eventuel regional betydning

Undgå:
- At nævne andre byer med samme navn
- At gætte på facts du ikke er sikker på
- Historiske facts uden relevans for tiden

Max 150 ord. Skriv KUN om DENNE specifikke by.' );

		// City page - Section 2: Timezone
		add_option( 'wta_prompt_city_timezone_system', 'Du er tidszone-ekspert der forklarer præcist og praktisk. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_city_timezone_user', 'Skriv 2-3 afsnit om tidszonen i {location_name_local}.

Forklar:
- Tidszonen ({timezone}) - hvad er UTC-offset
- Sommertid/vintertid og hvornår skiftet sker
- Forskel til {base_country_name} og praktiske implikationer
- Bedste tid at kontakte nogen i {location_name_local}
- Sammenligning med andre store byer i regionen

Max 200 ord. Vær praktisk og brugbar.' );

		// City page - Section 3: Attractions
		add_option( 'wta_prompt_city_attractions_system', 'Du er faktuel rejseguide der ALDRIG spekulerer. KRITISK: Hvis du ikke kender specifikke seværdigheder, fokuser på bytype og regional karakter. Undgå påstande du ikke kan verificere. Skriv om regionen hvis bydata mangler. KUN ren tekst, ingen overskrifter, ingen markdown. Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_city_attractions_user', 'VERIFY: {location_name_local} i {country_name}, koordinater {latitude},{longitude}.

Skriv 2-3 korte afsnit om seværdigheder i {location_name_local}.

Hvis byen er kendt: Nævn specifikke attraktioner, kultur, events
Hvis byen er mindre kendt: Fokuser på regional karakter og generel information

Fokuser på:
- Hvad området er kendt for
- Kulturelle eller naturlige highlights
- Regional betydning

Max 200 ord. KUN om denne by i {country_name}.' );

		// City page - Section 4: Practical
		add_option( 'wta_prompt_city_practical_system', 'Du giver praktiske, verificerbare rejsetips. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_city_practical_user', 'Skriv 2-3 afsnit med praktisk info om at besøge {location_name_local}.

Inkluder:
- Transport (lufthavn, tog, bus)
- Vejr og klima generelt
- Bedste rejsetid
- Praktiske tips for besøgende

Fokuser på generel regional info hvis specifics mangler. Max 200 ord.' );

		// City page - Section 5: Nearby Cities
		add_option( 'wta_prompt_city_nearby_cities_system', 'Du skriver generelle, inspirerende introduktionstekster om at udforske en regions byer. VIGTIG: Nævn INGEN specifikke bynavne - de vises automatisk nedenfor. Fokuser på generelle fordele ved at besøge nærliggende byer. KUN ren tekst, ingen overskrifter. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_city_nearby_cities_user', 'Skriv 2-3 korte sætninger der inspirerer til at udforske andre byer omkring {location_name_local} i {country_name}.

VIGTIG: Nævn INGEN specifikke bynavne! Fokuser på:
- Hvad gør området interessant at udforske
- Fordele ved at besøge flere byer i regionen
- Generel opfordring til at udforske

Eksempel tone:
"Når du er i området, er der mange spændende byer værd at udforske. Regionen byder på varieret kultur og historie, og afstandene er nemme at rejse."

Max 40-50 ord. Generisk og inspirerende.' );

		// City page - Section 6: Nearby Countries
		add_option( 'wta_prompt_city_nearby_countries_system', 'Du skriver generelle, inspirerende introduktionstekster om at udforske en regions lande. VIGTIG: Nævn INGEN specifikke landenavne - de vises automatisk nedenfor. Fokuser på regionale fordele. KUN ren tekst, ingen overskrifter. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_city_nearby_countries_user', 'Skriv 2-3 korte sætninger der inspirerer til at udforske andre lande når man besøger {country_name}.

VIGTIG: Nævn INGEN specifikke landenavne! Fokuser på:
- Hvad gør {continent_name} interessant at udforske
- Fordele ved at kombinere flere lande på rejsen
- Generel opfordring til regional udforskning

Eksempel:
"Når du er i regionen, ligger flere spændende lande inden for kort afstand. {continent_name} byder på enorm variation i kultur, natur og oplevelser."

Max 40-50 ord. Generisk og inspirerende.' );

		// Continent page - Section 1: Introduction
		add_option( 'wta_prompt_continent_intro_system', 'Du er en SEO-ekspert der skriver naturligt dansk indhold om tidszoner og geografi. Skriv informativt og direkte til danske brugere. VIGTIG: Skriv KUN ren tekst uden overskrifter, uden markdown, uden ChatGPT-fraser som "velkommen til" eller "lad os udforske". Alle sætninger SKAL afsluttes korrekt - ingen afskæring midt i sætning. Brug KORTE, varierede sætninger for god læsbarhed. Teksten opdeles automatisk i paragraffer. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land], [sted] etc. i din tekst. Brug ALTID de faktiske stednavne der gives i prompten.' );
		add_option( 'wta_prompt_continent_intro_user', 'Skriv en SEO-optimeret introduktion på 120-150 ord om hvad klokken er i {location_name_local}. FOKUS: Besvaring af søgeintentionen "hvad er klokken i {location_name_local}". Inkluder naturligt disse SEO-søgeord: "hvad er klokken i {location_name_local}", "tidszoner i {location_name_local}", "aktuel tid i {location_name_local}". Start direkte med information (ikke "velkommen" eller lignende). Nævn kort: antal tidszoner, geografisk udstrækning, største lande. Skriv med korte, varierede sætninger for god læsbarhed. Veksl mellem korte (5-10 ord) og lidt længere sætninger (15-20 ord). Afslut ALLE sætninger korrekt. KUN ren tekst - ingen overskrifter, ingen markdown.' );
		
		// Continent page - Section 2: Timezones
		add_option( 'wta_prompt_continent_timezone_system', 'Du er ekspert i internationale tidszoner og tidsberegning. Skriv præcist og faktabaseret om tidszoner til danske brugere. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. Brug varierede sætningslængder for god læsbarhed. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_continent_timezone_user', 'Skriv et detaljeret afsnit på 150-200 ord om tidszonerne i {location_name_local}. Indhold der SKAL inkluderes: 1) Liste de vigtigste tidszoner (fx CET, EET, GMT+X), 2) Forklar tidsforskelle i forhold til dansk tid (CET/CEST), 3) Nævn om sommertid/vintertid anvendes, 4) Eventuelle særlige tidszoner (halve timer, etc.). SEO-fokus på: "tidszoner i {location_name_local}" og "tidsforskelle {location_name_local}". Skriv med varierede sætningslængder - både korte (5-10 ord) og lidt længere (15-20 ord). Afslut ALLE sætninger korrekt - ingen afskæring. KUN ren tekst - ingen overskrifter.' );
		
		// Continent page - Section 3: Major Cities
		add_option( 'wta_prompt_continent_cities_system', 'Du er ekspert i verdens storbyer og deres tidszoner. Skriv engagerende om byer og deres lokale tid. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown, ingen lister. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_continent_cities_user', 'Skriv et afsnit på 100-120 ord om de største byer i {location_name_local} og deres tidszoner. Beskriv kort: Hvilke tidszoner de ligger i, Hvordan tiden adskiller sig mellem byerne (hvis relevant), Interessante tidszone-aspekter for rejsende. Byerne inkluderer: {cities_list}. Skriv naturligt og rejsevejleder-agtigt. Afslut ALLE sætninger korrekt. KUN ren tekst - ingen overskrifter, ingen punktopstillinger. (Note: Aktuel tid for hver by vises automatisk efter afsnittet)' );
		
		// Continent page - Section 4: Geography
		add_option( 'wta_prompt_continent_geography_system', 'Du er geografi-ekspert med fokus på hvordan geografi påvirker tid. Skriv kortfattet og faktuelt. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location] etc. Brug ALTID de faktiske stednavne direkte.' );
		add_option( 'wta_prompt_continent_geography_user', 'Skriv et kort afsnit på 80-100 ord om geografien i {location_name_local} og hvordan det påvirker tidszoner. Inkluder: Geografisk udstrækning (øst-vest især relevant for tidszoner), Størrelse i km² eller sammenligning, Hvorfor geografien giver X antal tidszoner. Skriv faktabaseret men tilgængeligt. Afslut ALLE sætninger korrekt. KUN ren tekst - ingen overskrifter.' );
		
		// Continent page - Section 5: Facts
		add_option( 'wta_prompt_continent_facts_system', 'Du er ekspert i kultur, historie og interessante fakta om tid og tidszoner. Skriv engagerende og lærerigt om tidszoner-relaterede fakta. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location] etc. Brug ALTID de faktiske stednavne direkte.' );
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

		// STAGGERED CONCURRENT SCHEDULING (v2.35.4)
		// Spread processors across time to enable true parallel processing
		// This prevents WP-Cron bottleneck where only 1 action runs per request
		
		// Process structure queue (continents, countries, cities) - Every 1 minute (PRIMARY)
		// Starts immediately, handles chunks (now 2k for faster completion <30s)
		if ( false === as_next_scheduled_action( 'wta_process_structure' ) ) {
			as_schedule_recurring_action( time(), MINUTE_IN_SECONDS, 'wta_process_structure', array(), 'world-time-ai' );
		}

		// Process timezone resolution - Every 30 seconds (offset +20s)
		// Runs between structure chunks to maximize throughput
		if ( false === as_next_scheduled_action( 'wta_process_timezone' ) ) {
			as_schedule_recurring_action( time() + 20, 30, 'wta_process_timezone', array(), 'world-time-ai' );
		}

	// Process AI content generation - Every 30 seconds (offset +40s)
	// Runs between structure chunks and timezone to maximize throughput
		if ( false === as_next_scheduled_action( 'wta_process_ai_content' ) ) {
		as_schedule_recurring_action( time() + 40, 30, 'wta_process_ai_content', array(), 'world-time-ai' );
	}

	// Cleanup old log files - Daily at 04:00 (v2.35.7)
	// Deletes all logs except today's to prevent disk space issues
	if ( false === as_next_scheduled_action( 'wta_cleanup_old_logs' ) ) {
		$tomorrow_4am = strtotime( 'tomorrow 04:00:00' );
		as_schedule_recurring_action( $tomorrow_4am, DAY_IN_SECONDS, 'wta_cleanup_old_logs', array(), 'world-time-ai' );
		}
	}

	/**
	 * Install performance indices for wp_postmeta.
	 * 
	 * These indices dramatically improve query performance for postmeta lookups.
	 * Reduces query time from 2-3 seconds to <0.1 seconds per query.
	 * 
	 * Safe to run multiple times - uses IF NOT EXISTS to prevent duplicates.
	 *
	 * @since 2.35.46
	 */
	private static function install_performance_indices() {
		global $wpdb;

		// Suppress errors temporarily (indices may already exist)
		$wpdb->suppress_errors();
		
		// Index 1: meta_key + meta_value lookups (used in WHERE clauses)
		// Speeds up: WHERE pm.meta_key = 'wta_type' AND pm.meta_value = 'city'
		$wpdb->query( "
			CREATE INDEX IF NOT EXISTS idx_wta_meta_key_value 
			ON {$wpdb->postmeta}(meta_key, meta_value(50))
		" );

		// Index 2: post_id + meta_key lookups (used in JOINs)
		// Speeds up: LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'wta_type'
		$wpdb->query( "
			CREATE INDEX IF NOT EXISTS idx_wta_post_meta 
			ON {$wpdb->postmeta}(post_id, meta_key)
		" );

		// Index 3: meta_key alone (fallback for general meta queries)
		// Speeds up: WHERE pm.meta_key = 'wta_population'
		$wpdb->query( "
			CREATE INDEX IF NOT EXISTS idx_wta_meta_key 
			ON {$wpdb->postmeta}(meta_key)
		" );

		// Re-enable error reporting
		$wpdb->suppress_errors( false );

		// Log success (optional - for debugging)
		if ( function_exists( 'error_log' ) ) {
			error_log( 'World Time AI: Performance indices installed/verified' );
		}
	}

	/**
	 * Add claim_id column to queue table for atomic claiming.
	 * 
	 * Enables concurrent queue processing by allowing multiple processors
	 * to atomically claim different batches of items without race conditions.
	 * 
	 * Safe to run multiple times - checks if column exists before adding.
	 *
	 * @since 3.0.41
	 */
	private static function add_claim_id_column() {
		global $wpdb;
		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		// Check if column already exists
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s 
				AND TABLE_NAME = %s 
				AND COLUMN_NAME = 'claim_id'",
				DB_NAME,
				$table_name
			)
		);

		if ( empty( $column_exists ) ) {
			// Add claim_id column after status
			$wpdb->query(
				"ALTER TABLE $table_name 
				ADD COLUMN claim_id VARCHAR(32) DEFAULT NULL AFTER status,
				ADD INDEX idx_claim_id (claim_id)"
			);

			// Log success
			if ( function_exists( 'error_log' ) ) {
				error_log( 'World Time AI: claim_id column added to queue table for concurrent processing' );
			}
		}
	}
}

