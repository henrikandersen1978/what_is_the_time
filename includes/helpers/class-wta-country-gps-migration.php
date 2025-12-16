<?php
/**
 * Country GPS Migration - Calculates and stores GPS coordinates for all countries.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 * @since      2.35.73
 */

class WTA_Country_GPS_Migration {

	/**
	 * Run the migration to calculate GPS for all countries.
	 *
	 * @since 2.35.73
	 * @return array Migration results with stats
	 */
	public static function run_migration() {
		global $wpdb;
		
		$start_time = microtime( true );
		$stats = array(
			'total' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors' => array(),
		);
		
		// Get all countries
		$countries = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'wta_type',
					'value' => 'country',
				),
			),
		) );
		
		$stats['total'] = count( $countries );
		
		foreach ( $countries as $country ) {
			$country_id = $country->ID;
			
			// Find largest city in this country (using post_parent hierarchy)
			$largest_city = $wpdb->get_row( $wpdb->prepare(
				"SELECT 
					p.ID,
					pm_lat.meta_value as lat,
					pm_lon.meta_value as lon,
					pm_pop.meta_value as pop
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id 
					AND pm_lat.meta_key = 'wta_latitude'
				INNER JOIN {$wpdb->postmeta} pm_lon ON p.ID = pm_lon.post_id 
					AND pm_lon.meta_key = 'wta_longitude'
				LEFT JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id 
					AND pm_pop.meta_key = 'wta_population'
				WHERE p.post_parent = %d
				AND p.post_type = %s
				AND p.post_status = 'publish'
				ORDER BY CAST(COALESCE(pm_pop.meta_value, 0) AS UNSIGNED) DESC
				LIMIT 1",
				$country_id,
				WTA_POST_TYPE
			) );
			
			if ( ! $largest_city || empty( $largest_city->lat ) || empty( $largest_city->lon ) ) {
				$stats['skipped']++;
				$stats['errors'][] = sprintf(
					'%s (ID: %d) - No cities with GPS coordinates',
					$country->post_title,
					$country_id
				);
				continue;
			}
			
			// Store GPS on country
			update_post_meta( $country_id, 'wta_latitude', $largest_city->lat );
			update_post_meta( $country_id, 'wta_longitude', $largest_city->lon );
			update_post_meta( $country_id, 'wta_gps_source_city_id', $largest_city->ID );
			update_post_meta( $country_id, 'wta_gps_updated', current_time( 'mysql' ) );
			
			$stats['updated']++;
		}
		
		$stats['duration'] = round( microtime( true ) - $start_time, 2 );
		
		// Store migration result
		update_option( 'wta_country_gps_migration_last_run', array(
			'timestamp' => current_time( 'mysql' ),
			'stats'     => $stats,
		) );
		
		return $stats;
	}
	
	/**
	 * Update a single country's GPS when a new city is added/updated.
	 *
	 * @since 2.35.73
	 * @param int $city_id City post ID
	 */
	public static function maybe_update_country_gps( $city_id ) {
		// Get city's country
		$country_id = wp_get_post_parent_id( $city_id );
		if ( ! $country_id ) {
			return;
		}
		
		// Check if city has GPS
		$city_lat = get_post_meta( $city_id, 'wta_latitude', true );
		$city_lon = get_post_meta( $city_id, 'wta_longitude', true );
		$city_pop = get_post_meta( $city_id, 'wta_population', true );
		
		if ( empty( $city_lat ) || empty( $city_lon ) ) {
			return;
		}
		
		// Get current GPS source city for country
		$current_source_city_id = get_post_meta( $country_id, 'wta_gps_source_city_id', true );
		
		if ( ! $current_source_city_id ) {
			// No GPS set yet - use this city
			update_post_meta( $country_id, 'wta_latitude', $city_lat );
			update_post_meta( $country_id, 'wta_longitude', $city_lon );
			update_post_meta( $country_id, 'wta_gps_source_city_id', $city_id );
			update_post_meta( $country_id, 'wta_gps_updated', current_time( 'mysql' ) );
			return;
		}
		
		// Compare population - update if this city is larger
		$current_source_pop = get_post_meta( $current_source_city_id, 'wta_population', true );
		
		if ( intval( $city_pop ) > intval( $current_source_pop ) ) {
			update_post_meta( $country_id, 'wta_latitude', $city_lat );
			update_post_meta( $country_id, 'wta_longitude', $city_lon );
			update_post_meta( $country_id, 'wta_gps_source_city_id', $city_id );
			update_post_meta( $country_id, 'wta_gps_updated', current_time( 'mysql' ) );
		}
	}
}

