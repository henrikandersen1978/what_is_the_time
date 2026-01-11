<?php
/**
 * Batch processor for sequential phases completion detection.
 *
 * v3.3.0: Implements smart completion detection instead of fixed delays.
 * Handles ANY number of cities (1k to 1M+) by detecting when phases complete.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/processors
 * @since      3.3.0
 */

class WTA_Batch_Processor {

	/**
	 * Check if structure phase is complete and trigger timezone batch.
	 *
	 * This runs periodically (every 2 min) during structure phase to detect completion.
	 * When all cities are created, schedules timezone resolution for all pending cities.
	 *
	 * @since 3.3.0
	 */
	public function check_structure_completion() {
		global $wpdb;

		// Count cities without timezone status (still being created or waiting)
		$pending_structure = $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type' AND pm_type.meta_value = 'city'
			 WHERE p.post_type = 'world_time_location'
			 AND p.post_status = 'publish'
			 AND NOT EXISTS (
				 SELECT 1 FROM {$wpdb->postmeta} pm_tz
				 WHERE pm_tz.post_id = p.ID
				 AND pm_tz.meta_key = 'wta_structure_complete'
			 )"
		);

		WTA_Logger::info( '🔍 Structure completion check', array(
			'pending_structure' => $pending_structure,
		) );

		if ( $pending_structure > 0 ) {
			// Still creating cities - schedule next check in 2 minutes
			as_schedule_single_action(
				time() + 120,
				'wta_check_structure_completion',
				array(),
				'wta_structure'
			);
			return;
		}

		// Structure phase complete! Trigger TWO things:
		WTA_Logger::info( '✅ Structure phase COMPLETE!' );
		
		// 1. AI for continents + countries (no timezone needed!)
		WTA_Logger::info( '→ Triggering Continent + Country AI batch...' );
		as_schedule_single_action(
			time() + 30,
			'wta_batch_schedule_ai_non_cities',
			array(),
			'wta_ai_content'
		);
		
		// 2. Timezone for cities
		WTA_Logger::info( '→ Triggering Timezone batch for cities...' );
		as_schedule_single_action(
			time() + 60, // Small buffer
			'wta_batch_schedule_timezone',
			array(),
			'wta_timezone'
		);
	}

	/**
	 * Batch schedule timezone resolution for all cities.
	 *
	 * Finds all cities that need timezone resolution and schedules them.
	 * Then starts checking for timezone completion.
	 *
	 * @since 3.3.0
	 */
	public function batch_schedule_timezone() {
		global $wpdb;

		WTA_Logger::info( '🌍 Starting batch timezone scheduling...' );

		// v3.3.5: DEBUG - Count total cities first
		$total_cities = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'wta_type' AND pm.meta_value = 'city'
			 WHERE p.post_type = 'world_time_location' AND p.post_status = 'publish'"
		);
		
		WTA_Logger::debug( 'Total cities in database', array( 'total_cities' => $total_cities ) );

		// Find all cities that need timezone resolution
		// v3.3.2: Include cities with 'pending' or 'waiting_for_toggle' status
		$cities_needing_timezone = $wpdb->get_results(
			"SELECT p.ID, pm_lat.meta_value as lat, pm_lng.meta_value as lng, pm_country.meta_value as country_code
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type' AND pm_type.meta_value = 'city'
			 INNER JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id AND pm_lat.meta_key = 'wta_latitude'
			 INNER JOIN {$wpdb->postmeta} pm_lng ON p.ID = pm_lng.post_id AND pm_lng.meta_key = 'wta_longitude'
			 INNER JOIN {$wpdb->postmeta} pm_country ON p.ID = pm_country.post_id AND pm_country.meta_key = 'wta_country_code'
			 LEFT JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id AND pm_tz.meta_key = 'wta_timezone_status'
			 WHERE p.post_type = 'world_time_location'
			 AND p.post_status = 'publish'
			 AND (pm_tz.meta_value IS NULL OR pm_tz.meta_value IN ('pending', 'waiting_for_toggle'))"
		);
		
		WTA_Logger::debug( 'Cities found by timezone query', array( 
			'found' => count( $cities_needing_timezone ),
			'sample_ids' => array_slice( array_map( function($c) { return $c->ID; }, $cities_needing_timezone ), 0, 5 )
		) );

		$scheduled = 0;
		foreach ( $cities_needing_timezone as $city ) {
			// Check if city is in simple country list
			$timezone = WTA_Timezone_Helper::get_country_timezone( $city->country_code );
			
			if ( $timezone ) {
				// Simple country - set timezone directly
				update_post_meta( $city->ID, 'wta_timezone', $timezone );
				update_post_meta( $city->ID, 'wta_timezone_status', 'resolved' );
				update_post_meta( $city->ID, 'wta_has_timezone', 1 );
			} else {
				// Needs API lookup
				as_schedule_single_action(
					time(),
					'wta_lookup_timezone',
					array( $city->ID, floatval( $city->lat ), floatval( $city->lng ) ),
					'wta_timezone'
				);
				$scheduled++;
			}
		}

		WTA_Logger::info( '✅ Timezone batch scheduled', array(
			'total_cities'    => count( $cities_needing_timezone ),
			'api_scheduled'   => $scheduled,
			'simple_resolved' => count( $cities_needing_timezone ) - $scheduled,
		) );

		// Start checking for timezone completion
		as_schedule_single_action(
			time() + 300, // Check in 5 minutes
			'wta_check_timezone_completion',
			array(),
			'wta_timezone'
		);
	}

	/**
	 * Check if timezone phase is complete and trigger AI batch.
	 *
	 * This runs periodically (every 5 min) during timezone phase to detect completion.
	 * When all timezones are resolved, schedules AI generation for all cities.
	 *
	 * @since 3.3.0
	 */
	public function check_timezone_completion() {
		global $wpdb;

		// Count cities without resolved timezone
		// v3.3.2: Only count cities (type = 'city'), not continents/countries
		$pending_timezone = $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type' AND pm_type.meta_value = 'city'
			 LEFT JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id AND pm_tz.meta_key = 'wta_timezone_status'
			 WHERE p.post_type = 'world_time_location'
			 AND p.post_status = 'publish'
			 AND (pm_tz.meta_value IS NULL OR pm_tz.meta_value != 'resolved')"
		);

		WTA_Logger::info( '🔍 Timezone completion check', array(
			'pending_timezone' => $pending_timezone,
		) );

		if ( $pending_timezone > 0 ) {
			// Still resolving timezones - schedule next check in 5 minutes
			as_schedule_single_action(
				time() + 300,
				'wta_check_timezone_completion',
				array(),
				'wta_timezone'
			);
			return;
		}

		// Timezone phase complete! Trigger City AI batch
		WTA_Logger::info( '✅ Timezone phase COMPLETE! Triggering City AI batch...' );

		as_schedule_single_action(
			time() + 30, // Small buffer
			'wta_batch_schedule_ai_cities',
			array(),
			'wta_ai_content'
		);
	}

	/**
	 * Batch schedule AI for continents and countries (no timezone needed).
	 *
	 * Runs immediately after structure phase completes.
	 * These entities don't need timezone data for AI generation.
	 *
	 * @since 3.3.1
	 */
	public function batch_schedule_ai_non_cities() {
		global $wpdb;

		WTA_Logger::info( '🤖 Starting Continent + Country AI batch...' );

		// Find continents and countries that need AI
		$entities_needing_ai = $wpdb->get_results(
			"SELECT p.ID, pm_type.meta_value as entity_type
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
			 WHERE p.post_type = 'world_time_location'
			 AND p.post_status = 'publish'
			 AND pm_type.meta_value IN ('continent', 'country')
			 AND NOT EXISTS (
				 SELECT 1 FROM {$wpdb->postmeta} pm_ai
				 WHERE pm_ai.post_id = p.ID
				 AND pm_ai.meta_key = 'wta_ai_generated'
				 AND pm_ai.meta_value = '1'
			 )"
		);

		$scheduled = 0;
		foreach ( $entities_needing_ai as $entity ) {
			as_schedule_single_action(
				time(),
				'wta_generate_ai_content',
				array( $entity->ID, $entity->entity_type, false ),
				'wta_ai_content'
			);
			$scheduled++;
		}

		WTA_Logger::info( '✅ Continent + Country AI batch scheduled', array(
			'total_entities' => $scheduled,
		) );
	}

	/**
	 * Batch schedule AI for cities (after timezone resolution).
	 *
	 * Runs after timezone phase completes.
	 * Cities need timezone data for accurate AI content.
	 *
	 * @since 3.3.1
	 */
	public function batch_schedule_ai_cities() {
		global $wpdb;

		WTA_Logger::info( '🤖 Starting City AI batch...' );

		// Find cities that need AI
		$cities_needing_ai = $wpdb->get_results(
			"SELECT p.ID, pm_type.meta_value as entity_type
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
			 WHERE p.post_type = 'world_time_location'
			 AND p.post_status = 'publish'
			 AND pm_type.meta_value = 'city'
			 AND NOT EXISTS (
				 SELECT 1 FROM {$wpdb->postmeta} pm_ai
				 WHERE pm_ai.post_id = p.ID
				 AND pm_ai.meta_key = 'wta_ai_generated'
				 AND pm_ai.meta_value = '1'
			 )"
		);

		$scheduled = 0;
		foreach ( $cities_needing_ai as $city ) {
			as_schedule_single_action(
				time(),
				'wta_generate_ai_content',
				array( $city->ID, $city->entity_type, false ),
				'wta_ai_content'
			);
			$scheduled++;
		}

		WTA_Logger::info( '✅ City AI batch scheduled', array(
			'total_cities' => $scheduled,
		) );
	}
}
