<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin data.
 */
function wta_uninstall() {
	global $wpdb;

	// Delete all posts of our custom post type
	$posts = get_posts(
		array(
			'post_type'      => 'world_time_location',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		)
	);

	foreach ( $posts as $post ) {
		wp_delete_post( $post->ID, true );
	}

	// Drop custom tables
	$table_name = $wpdb->prefix . 'world_time_queue';
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

	// Delete all plugin options
	$options = array(
		// GitHub URLs
		'wta_github_countries_url',
		'wta_github_states_url',
		'wta_github_cities_url',
		// TimeZoneDB
		'wta_timezonedb_api_key',
		'wta_complex_countries',
		// Base settings
		'wta_base_country_name',
		'wta_base_timezone',
		'wta_base_language',
		'wta_base_language_description',
		// OpenAI
		'wta_openai_api_key',
		'wta_openai_model',
		'wta_openai_temperature',
		'wta_openai_max_tokens',
		// Import filters
		'wta_selected_continents',
		'wta_min_population',
		'wta_max_cities_per_country',
		// Yoast
		'wta_yoast_integration_enabled',
		'wta_yoast_allow_overwrite',
		// DB version
		'wta_db_version',
	);

	// Delete prompt options (9 prompts × 2 types)
	$prompt_ids = array(
		'translate_location_name',
		'city_page_title',
		'city_page_content',
		'country_page_title',
		'country_page_content',
		'continent_page_title',
		'continent_page_content',
		'yoast_seo_title',
		'yoast_meta_description',
	);

	foreach ( $prompt_ids as $prompt_id ) {
		$options[] = "wta_prompt_{$prompt_id}_system";
		$options[] = "wta_prompt_{$prompt_id}_user";
	}

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear any transients
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wta_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wta_%'" );

	// Clear scheduled cron events
	wp_clear_scheduled_hook( 'world_time_import_structure' );
	wp_clear_scheduled_hook( 'world_time_resolve_timezones' );
	wp_clear_scheduled_hook( 'world_time_generate_ai_content' );

	// Flush rewrite rules
	flush_rewrite_rules();
}

wta_uninstall();





