<?php
/**
 * Prompts admin page.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission
if ( isset( $_POST['submit'] ) && check_admin_referer( 'wta_prompts' ) ) {
	$prompt_types = array( 
		'translate_name', 'city_title', 'city_content', 'country_title', 'country_content', 
		'continent_intro', 'continent_timezone', 'continent_cities', 'continent_geography', 'continent_facts',
		'country_intro', 'country_timezone', 'country_cities', 'country_weather', 'country_culture', 'country_travel',
		'city_intro', 'city_timezone', 'city_attractions', 'city_practical', 'city_nearby_cities', 'city_nearby_countries',
		'yoast_title', 'yoast_desc' 
	);
	
	foreach ( $prompt_types as $type ) {
		if ( isset( $_POST["wta_prompt_{$type}_system"] ) ) {
			update_option( "wta_prompt_{$type}_system", sanitize_textarea_field( $_POST["wta_prompt_{$type}_system"] ) );
		}
		if ( isset( $_POST["wta_prompt_{$type}_user"] ) ) {
			update_option( "wta_prompt_{$type}_user", sanitize_textarea_field( $_POST["wta_prompt_{$type}_user"] ) );
		}
	}
	
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Prompts saved.', WTA_TEXT_DOMAIN ) . '</p></div>';
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'AI Prompts', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-admin-page">
		<div class="wta-card">
			<p><?php esc_html_e( 'Customize AI prompts for content generation. Available variables:', WTA_TEXT_DOMAIN ); ?></p>
			<ul>
				<li><code>{location_name}</code> - <?php esc_html_e( 'Original English name', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{location_name_local}</code> - <?php esc_html_e( 'Danish translated name', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{location_type}</code> - <?php esc_html_e( 'Type: city, country, or continent', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{timezone}</code> - <?php esc_html_e( 'IANA timezone', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{base_language}</code> - <?php esc_html_e( 'Target language (da-DK)', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{base_language_description}</code> - <?php esc_html_e( 'Language instruction', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{country_name}</code> - <?php esc_html_e( 'Parent country name', WTA_TEXT_DOMAIN ); ?></li>
				<li><code>{continent_name}</code> - <?php esc_html_e( 'Parent continent name', WTA_TEXT_DOMAIN ); ?></li>
			</ul>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'wta_prompts' ); ?>

			<!-- City Content -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'City Page Content', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wta_prompt_city_content_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_city_content_system" name="wta_prompt_city_content_system" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_city_content_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_city_content_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_city_content_user" name="wta_prompt_city_content_user" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_city_content_user' ) ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>

			<!-- Country Content -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Country Page Content', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wta_prompt_country_content_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_country_content_system" name="wta_prompt_country_content_system" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_country_content_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_country_content_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_country_content_user" name="wta_prompt_country_content_user" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_country_content_user' ) ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>

		<!-- Country Content Template -->
		<div class="wta-card">
			<h2>🗺️ <?php esc_html_e( 'Country Page Template (6 Sections)', WTA_TEXT_DOMAIN ); ?></h2>
			
			<div class="notice notice-info inline" style="margin: 15px 0;">
				<p><strong>📌 <?php esc_html_e( 'Content Structure:', WTA_TEXT_DOMAIN ); ?></strong></p>
				<ol>
					<li><strong><?php esc_html_e( 'Intro', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'City List', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'Auto-inserted [wta_child_locations] shortcode', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Tidszoner', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Major Cities', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI text + auto-inserted [wta_major_cities count="12"] shortcode', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Weather & Climate', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Culture & Time', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Travel Info', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
				</ol>
			</div>

			<!-- Section 1: Intro -->
			<h3>📝 1. <?php esc_html_e( 'Introduction (2-3 short paragraphs)', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_intro_system" rows="3" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_intro_system' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_intro_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_intro_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Variables: {location_name_local}, {continent_name}, {base_country_name}', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- Section 2: Timezone -->
			<h3>🕒 2. <?php esc_html_e( 'Tidszoner (H2: "Tidszoner i [land]")', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_timezone_system" rows="3" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_timezone_system' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_timezone_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_timezone_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Variables: {location_name_local}, {timezone}, {base_country_name}', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- Section 3: Major Cities -->
			<h3>🏙️ 3. <?php esc_html_e( 'Store byer (H2: "Hvad er klokken i de største byer i [land]?")', WTA_TEXT_DOMAIN ); ?></h3>
			<p class="description" style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
				<strong>ℹ️ <?php esc_html_e( 'Note:', WTA_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( '[wta_major_cities count="12"] shortcode indsættes automatisk efter AI-tekst for at vise 12 live klokker i 3x4 grid.', WTA_TEXT_DOMAIN ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_cities_system" rows="3" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_cities_system' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_cities_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_cities_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Variables: {location_name_local}, {cities_list}', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- Section 4: Weather & Climate -->
			<h3>☀️ 4. <?php esc_html_e( 'Vejr og klima (H2: "Vejr og klima i [land]")', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_weather_system" rows="3" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_weather_system' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_weather_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_weather_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Variables: {location_name_local}', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- Section 5: Culture & Time -->
			<h3>🎭 5. <?php esc_html_e( 'Tidskultur (H2: "Tidskultur og dagligdag i [land]")', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_culture_system" rows="3" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_culture_system' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_culture_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_culture_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Variables: {location_name_local}, {base_country_name}', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- Section 6: Travel Info -->
			<h3>✈️ 6. <?php esc_html_e( 'Rejseinformation (H2: "Hvad du skal vide om tid når du rejser til [land]")', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_travel_system" rows="3" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_travel_system' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_country_travel_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_country_travel_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Variables: {location_name_local}, {base_country_name}', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Continent Content Template -->
		<div class="wta-card">
			<h2>🌍 <?php esc_html_e( 'Continent Page Template', WTA_TEXT_DOMAIN ); ?></h2>
			
			<div class="notice notice-info inline" style="margin: 15px 0;">
				<p><strong>📌 <?php esc_html_e( 'Content Structure:', WTA_TEXT_DOMAIN ); ?></strong></p>
				<ol>
					<li><strong><?php esc_html_e( 'Intro', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Links to Countries', WTA_TEXT_DOMAIN ); ?></strong> - ✅ <?php esc_html_e( 'Auto-generated list (no prompt needed)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Timezones', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Major Cities', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Geography', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Facts', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'AI generated (configure below)', WTA_TEXT_DOMAIN ); ?></li>
				</ol>
				<p>⚠️ <strong><?php esc_html_e( 'The country list is automatically generated from child pages. No token limits - OpenAI controls length based on your instructions.', WTA_TEXT_DOMAIN ); ?></strong></p>
			</div>
			
			<p class="description">
				<?php esc_html_e( 'Configure 5 AI-generated sections below. Variables: {location_name_local}, {location_name}', WTA_TEXT_DOMAIN ); ?>
			</p>
			
			<!-- 1. INTRO -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">📝 <?php esc_html_e( '1. Introduction (Shown first)', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wta_prompt_continent_intro_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_intro_system" name="wta_prompt_continent_intro_system" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_intro_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'AI role and writing style instructions.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wta_prompt_continent_intro_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_intro_user" name="wta_prompt_continent_intro_user" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_intro_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Specific task. SEO focus: "hvad er klokken i X", "tidszoner i X"', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>
			
			<!-- NOTE ABOUT COUNTRY LIST -->
			<div style="background: #f0f0f1; padding: 15px; margin: 20px 0; border-left: 4px solid #2271b1;">
				<h3 style="margin-top: 0;">📍 2. <?php esc_html_e( 'Links to Countries (Auto-Generated)', WTA_TEXT_DOMAIN ); ?></h3>
				<p><strong><?php esc_html_e( 'This section is generated automatically from published country pages.', WTA_TEXT_DOMAIN ); ?></strong></p>
				<p><?php esc_html_e( 'Format: Grid of links with heading "Lande i [Continent Name]"', WTA_TEXT_DOMAIN ); ?></p>
				<p>✅ <?php esc_html_e( 'No configuration needed - works automatically!', WTA_TEXT_DOMAIN ); ?></p>
			</div>
			
			<!-- 3. TIMEZONE -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">🕐 <?php esc_html_e( '3. Timezones (After country list)', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wta_prompt_continent_timezone_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_timezone_system" name="wta_prompt_continent_timezone_system" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_timezone_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Timezone expert instructions.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wta_prompt_continent_timezone_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_timezone_user" name="wta_prompt_continent_timezone_user" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_timezone_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Details about timezones, time differences, DST.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>
			
			<!-- 4. MAJOR CITIES -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">🏙️ <?php esc_html_e( '4. Major Cities', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wta_prompt_continent_cities_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_cities_system" name="wta_prompt_continent_cities_system" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_cities_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Expert in major cities and their timezones.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wta_prompt_continent_cities_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_cities_user" name="wta_prompt_continent_cities_user" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_cities_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'AI gets list of major cities automatically. Focus on their timezones.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>
			
			<!-- 5. GEOGRAPHY -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">🗺️ <?php esc_html_e( '5. Geography and Location', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wta_prompt_continent_geography_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_geography_system" name="wta_prompt_continent_geography_system" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_geography_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Geography expert instructions.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wta_prompt_continent_geography_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_geography_user" name="wta_prompt_continent_geography_user" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_geography_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Geographic extent and how it affects timezones.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>
			
			<!-- 6. FACTS -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">💡 <?php esc_html_e( '6. Interesting Facts about Timezones', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wta_prompt_continent_facts_system"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_facts_system" name="wta_prompt_continent_facts_system" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_facts_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Expert in culture, history, and timezone facts.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wta_prompt_continent_facts_user"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
					<td>
						<textarea id="wta_prompt_continent_facts_user" name="wta_prompt_continent_facts_user" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_continent_facts_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Interesting facts specifically about timezones and time.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- CITY PAGE TEMPLATE -->
		<div class="wta-card">
			<h2>🏙️ <?php esc_html_e( 'City Page Template (6 Sections)', WTA_TEXT_DOMAIN ); ?></h2>
			
			<div class="notice notice-info inline" style="margin: 15px 0;">
				<p><strong>📌 <?php esc_html_e( 'Content Structure:', WTA_TEXT_DOMAIN ); ?></strong></p>
				<ol>
					<li><strong><?php esc_html_e( 'Intro', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'Basic info about city and timezone', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Timezone Details', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'DST, time differences, practical info', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Attractions', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'Sightseeing and activities', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Practical Info', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'Travel, transport, climate', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Nearby Cities', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'PHP finds + AI writes + auto-linked', WTA_TEXT_DOMAIN ); ?></li>
					<li><strong><?php esc_html_e( 'Nearby Countries', WTA_TEXT_DOMAIN ); ?></strong> - <?php esc_html_e( 'PHP finds + AI writes + auto-linked', WTA_TEXT_DOMAIN ); ?></li>
				</ol>
				<p>⚠️ <strong><?php esc_html_e( 'City/country names in sections 5-6 are automatically linked to their pages.', WTA_TEXT_DOMAIN ); ?></strong></p>
			</div>
			
			<p class="description">
				<?php esc_html_e( 'Variables: {location_name_local}, {location_name}, {country_name}, {continent_name}, {timezone}, {latitude}, {longitude}, {nearby_cities_list}, {nearby_countries_list}', WTA_TEXT_DOMAIN ); ?>
			</p>

			<!-- 1. Intro -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">📝 <?php esc_html_e( '1. Intro (2-3 korte afsnit)', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_intro_system" rows="4" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_intro_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Factual expert, never speculates, uses GPS coordinates for verification', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_intro_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_intro_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Basic city info, location, timezone. Max 150 words.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- 2. Timezone -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">🕐 <?php esc_html_e( '2. Tidszone og praktisk info', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_timezone_system" rows="4" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_timezone_system' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_timezone_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_timezone_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Timezone details, DST, time differences. Max 200 words.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- 3. Attractions -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">🎭 <?php esc_html_e( '3. Seværdigheder og aktiviteter', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_attractions_system" rows="4" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_attractions_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'NEVER speculates, focuses on regional info if specifics unknown', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_attractions_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_attractions_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Tourist attractions, culture, events. Max 200 words.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- 4. Practical -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">✈️ <?php esc_html_e( '4. Praktisk rejseinformation', WTA_TEXT_DOMAIN ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_practical_system" rows="4" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_practical_system' ) ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_practical_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_practical_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Transport, weather, travel tips. Max 200 words.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- 5. Nearby Cities -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">🏙️ <?php esc_html_e( '5. Nærliggende byer (Auto-linked)', WTA_TEXT_DOMAIN ); ?></h3>
			<div style="background: #f0f0f1; padding: 15px; margin: 10px 0; border-left: 4px solid #2271b1;">
				<p><strong>⚠️ <?php esc_html_e( 'CRITICAL: PHP finds nearby cities (<500km), AI mentions ONLY those cities, PHP auto-links them.', WTA_TEXT_DOMAIN ); ?></strong></p>
				<p><?php esc_html_e( 'Variable {nearby_cities_list} contains comma-separated city names that AI MUST use.', WTA_TEXT_DOMAIN ); ?></p>
			</div>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_nearby_cities_system" rows="4" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_nearby_cities_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'MUST ONLY mention cities in {nearby_cities_list} - NO others!', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_nearby_cities_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_nearby_cities_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Recommend visiting nearby cities. Use EXACT names from {nearby_cities_list}. Max 60 words.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>

			<!-- 6. Nearby Countries -->
			<h3 style="margin-top: 25px; border-top: 2px solid #ddd; padding-top: 20px;">🌍 <?php esc_html_e( '6. Nærliggende lande (Auto-linked)', WTA_TEXT_DOMAIN ); ?></h3>
			<div style="background: #f0f0f1; padding: 15px; margin: 10px 0; border-left: 4px solid #2271b1;">
				<p><strong>⚠️ <?php esc_html_e( 'CRITICAL: PHP finds nearby countries (same continent), AI mentions ONLY those countries, PHP auto-links them.', WTA_TEXT_DOMAIN ); ?></strong></p>
				<p><?php esc_html_e( 'Variable {nearby_countries_list} contains comma-separated country names that AI MUST use.', WTA_TEXT_DOMAIN ); ?></p>
			</div>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'System Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_nearby_countries_system" rows="4" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_nearby_countries_system' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'MUST ONLY mention countries in {nearby_countries_list} - NO others!', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User Prompt', WTA_TEXT_DOMAIN ); ?></th>
					<td>
						<textarea name="wta_prompt_city_nearby_countries_user" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'wta_prompt_city_nearby_countries_user' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Recommend exploring nearby countries. Use EXACT names from {nearby_countries_list}. Max 60 words.', WTA_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</table>
		</div>

			<!-- Yoast SEO -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Yoast SEO Meta', WTA_TEXT_DOMAIN ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wta_prompt_yoast_title_system"><?php esc_html_e( 'Title System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_yoast_title_system" name="wta_prompt_yoast_title_system" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_yoast_title_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_yoast_title_user"><?php esc_html_e( 'Title User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_yoast_title_user" name="wta_prompt_yoast_title_user" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_yoast_title_user' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_yoast_desc_system"><?php esc_html_e( 'Description System Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_yoast_desc_system" name="wta_prompt_yoast_desc_system" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_yoast_desc_system' ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wta_prompt_yoast_desc_user"><?php esc_html_e( 'Description User Prompt', WTA_TEXT_DOMAIN ); ?></label></th>
						<td>
							<textarea id="wta_prompt_yoast_desc_user" name="wta_prompt_yoast_desc_user" rows="2" class="large-text"><?php echo esc_textarea( get_option( 'wta_prompt_yoast_desc_user' ) ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save All Prompts', WTA_TEXT_DOMAIN ); ?>">
			</p>
		</form>
	</div>
</div>


