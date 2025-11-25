<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file runs when the user clicks the "Delete" button in wp-admin/plugins.php
 * 
 * - Delete all custom post types
 * - Delete all post meta
 * - Delete queue table
 * - Delete all options
 * - Delete transients
 * - Unschedule Action Scheduler actions
 * - Clear data directory (optional - keep for now)
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Delete all location posts and their meta
 */
$post_type = 'world_time_location';

// Get all posts
$posts = get_posts( array(
	'post_type'   => $post_type,
	'numberposts' => -1,
	'post_status' => 'any',
) );

// Delete all posts and their meta
foreach ( $posts as $post ) {
	wp_delete_post( $post->ID, true );
}

/**
 * Delete queue table
 */
$queue_table = $wpdb->prefix . 'world_time_queue';
$wpdb->query( "DROP TABLE IF EXISTS $queue_table" );

/**
 * Delete all plugin options
 */
$options = array(
	'wta_plugin_version',
	'wta_base_country_name',
	'wta_base_timezone',
	'wta_base_language',
	'wta_base_language_description',
	'wta_complex_countries',
	'wta_github_countries_url',
	'wta_github_states_url',
	'wta_github_cities_url',
	'wta_timezonedb_api_key',
	'wta_openai_api_key',
	'wta_openai_model',
	'wta_openai_temperature',
	'wta_openai_max_tokens',
	'wta_selected_continents',
	'wta_min_population',
	'wta_max_cities_per_country',
	// AI prompts
	'wta_prompt_translate_name_system',
	'wta_prompt_translate_name_user',
	'wta_prompt_city_title_system',
	'wta_prompt_city_title_user',
	'wta_prompt_city_content_system',
	'wta_prompt_city_content_user',
	'wta_prompt_country_title_system',
	'wta_prompt_country_title_user',
	'wta_prompt_country_content_system',
	'wta_prompt_country_content_user',
	'wta_prompt_continent_title_system',
	'wta_prompt_continent_title_user',
	'wta_prompt_yoast_title_system',
	'wta_prompt_yoast_title_user',
	'wta_prompt_yoast_desc_system',
	'wta_prompt_yoast_desc_user',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Delete all transients
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wta_%' OR option_name LIKE '_transient_timeout_wta_%'" );

/**
 * Unschedule Action Scheduler actions
 */
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wta_process_structure' );
	as_unschedule_all_actions( 'wta_process_timezone' );
	as_unschedule_all_actions( 'wta_process_ai_content' );
}

/**
 * Note: We keep the data directory (wp-content/uploads/world-time-ai-data/)
 * as it contains user-uploaded JSON files. If you want to delete it:
 * 
 * $upload_dir = wp_upload_dir();
 * $data_dir = $upload_dir['basedir'] . '/world-time-ai-data';
 * if ( file_exists( $data_dir ) ) {
 *     // Recursively delete directory
 *     array_map( 'unlink', glob( "$data_dir/*.*" ) );
 *     rmdir( $data_dir );
 * }
 */


