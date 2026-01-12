<?php
/**
 * Batch processor for sequential phases completion detection.
 *
 * v3.3.0: Implements smart completion detection instead of fixed delays.
 * Handles ANY number of cities (1k to 1M+) by detecting when phases complete.
 * 
 * v3.3.8: Staggered API scheduling to prevent TimezoneDB overload.
 * Spreads API calls over time (10 req/s) instead of bursting all at once.
 * 
 * v3.3.11: SMART COMPLETION DETECTION - No more race conditions!
 * Checks Action Scheduler for pending/in-progress actions to determine true completion.
 * Dynamic recheck intervals based on remaining work (30s-5min).
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/processors
 * @since      3.3.0
 * @since      3.3.8 Added staggered scheduling for timezone API calls
 * @since      3.3.11 Smart completion detection with Action Scheduler state checks
 */

class WTA_Batch_Processor {

	/**
	 * Check if structure phase is complete and trigger timezone batch.
	 *
	 * v3.3.11: SMART COMPLETION DETECTION - No more race conditions!
	 * Checks Action Scheduler for pending/in-progress actions instead of just meta keys.
	 * Dynamically adjusts recheck interval based on remaining work.
	 *
	 * This runs periodically during structure phase to detect completion.
	 * When all cities are created, schedules timezone resolution for all pending cities.
	 *
	 * @since 3.3.0
	 * @since 3.3.11 Smart completion detection (checks Action Scheduler state)
	 */
	public function check_structure_completion() {
		global $wpdb;

		// v3.3.11: PRIMARY CHECK - Action Scheduler pending/in-progress actions
		// This is the DEFINITIVE check because it reflects actual work being done
		$as_table = $wpdb->prefix . 'actionscheduler_actions';
		$pending_actions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$as_table}
				 WHERE hook = %s
				 AND status IN ('pending', 'in-progress')",
				'wta_create_city'
			)
		);

		// v3.3.11: SECONDARY CHECK - Database meta keys (backup check)
		// Count cities without structure_complete flag (still being created)
		$pending_structure = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type' AND pm_type.meta_value = 'city'
				 WHERE p.post_type = %s
				 AND p.post_status IN ('publish', 'draft')
				 AND NOT EXISTS (
					 SELECT 1 FROM {$wpdb->postmeta} pm_tz
					 WHERE pm_tz.post_id = p.ID
					 AND pm_tz.meta_key = 'wta_structure_complete'
				 )",
				WTA_POST_TYPE
			)
		);

		// v3.3.11: Calculate total pending (either check can indicate pending work)
		$total_pending = max( $pending_actions, $pending_structure );

		WTA_Logger::info( '🔍 Structure completion check', array(
			'pending_actions'   => $pending_actions,  // Action Scheduler queue
			'pending_structure' => $pending_structure, // Database meta check
			'total_pending'     => $total_pending,    // Max of both
		) );

		if ( $total_pending > 0 ) {
			// v3.3.11: DYNAMIC RECHECK INTERVAL based on remaining work
			// Close to completion: Check frequently
			// Far from completion: Check less frequently to reduce overhead
			if ( $total_pending < 100 ) {
				$recheck_delay = 30;  // 30 seconds - almost done!
			} elseif ( $total_pending < 1000 ) {
				$recheck_delay = 60;  // 1 minute - moderate amount left
			} else {
				$recheck_delay = 120; // 2 minutes - lots of work remaining
			}

			WTA_Logger::info( '⏳ Structure phase in progress', array(
				'next_check_in' => $recheck_delay . 's',
				'reason'        => $total_pending < 100 ? 'Almost done!' : 
				                   ( $total_pending < 1000 ? 'Moderate work' : 'Large import' ),
			) );

			as_schedule_single_action(
				time() + $recheck_delay,
				'wta_check_structure_completion',
				array(),
				'wta_structure'
			);
			return;
		}

		// v3.3.11: Structure phase complete! Trigger TWO things:
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

		// v3.3.7: Use WTA_POST_TYPE constant (was hardcoded wrong type!)
		$total_cities = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'wta_type' AND pm.meta_value = 'city'
				 WHERE p.post_type = %s AND p.post_status IN ('publish', 'draft')",
				WTA_POST_TYPE
			)
		);
		
		WTA_Logger::debug( 'Total cities in database', array( 'total_cities' => $total_cities ) );

		// Find all cities that need timezone resolution
		// v3.3.7: Use WTA_POST_TYPE constant (was hardcoded wrong type!)
		$cities_needing_timezone = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, pm_lat.meta_value as lat, pm_lng.meta_value as lng, pm_country.meta_value as country_code
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type' AND pm_type.meta_value = 'city'
				 INNER JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id AND pm_lat.meta_key = 'wta_latitude'
				 INNER JOIN {$wpdb->postmeta} pm_lng ON p.ID = pm_lng.post_id AND pm_lng.meta_key = 'wta_longitude'
				 INNER JOIN {$wpdb->postmeta} pm_country ON p.ID = pm_country.post_id AND pm_country.meta_key = 'wta_country_code'
				 LEFT JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id AND pm_tz.meta_key = 'wta_timezone_status'
				 WHERE p.post_type = %s
				 AND p.post_status IN ('publish', 'draft')
				 AND (pm_tz.meta_value IS NULL OR pm_tz.meta_value IN ('pending', 'waiting_for_toggle'))",
				WTA_POST_TYPE
			)
		);
		
		WTA_Logger::debug( 'Cities found by timezone query', array( 
			'found' => count( $cities_needing_timezone ),
			'sample_ids' => array_slice( array_map( function($c) { return $c->ID; }, $cities_needing_timezone ), 0, 5 )
		) );

		$scheduled = 0;
		$delay = 0;
		$api_count = 0;
		
		foreach ( $cities_needing_timezone as $city ) {
			// Check if city is in simple country list
			$timezone = WTA_Timezone_Helper::get_country_timezone( $city->country_code );
			
			if ( $timezone ) {
				// Simple country - set timezone directly
				update_post_meta( $city->ID, 'wta_timezone', $timezone );
				update_post_meta( $city->ID, 'wta_timezone_status', 'resolved' );
				update_post_meta( $city->ID, 'wta_has_timezone', 1 );
			} else {
				// v3.3.8: STAGGERED SCHEDULING to prevent API overload!
				// Spread API calls over time instead of all at once
				as_schedule_single_action(
					time() + $delay,  // Add incremental delay
					'wta_lookup_timezone',
					array( $city->ID, floatval( $city->lat ), floatval( $city->lng ) ),
					'wta_timezone'
				);
				$scheduled++;
				$api_count++;
				
				// Add 1 second delay after every 10 API calls (= 10 req/s for Premium)
				if ( $api_count % 10 == 0 ) {
					$delay += 1;
				}
			}
		}

		WTA_Logger::info( '✅ Timezone batch scheduled', array(
			'total_cities'    => count( $cities_needing_timezone ),
			'api_scheduled'   => $scheduled,
			'simple_resolved' => count( $cities_needing_timezone ) - $scheduled,
			'staggered_over'  => $delay . ' seconds',
			'rate_limit'      => '10 req/s (Premium tier)',
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
	 * v3.3.11: SMART COMPLETION DETECTION - No more race conditions!
	 * Checks Action Scheduler for pending/in-progress actions instead of just meta keys.
	 * Dynamically adjusts recheck interval based on remaining work.
	 *
	 * This runs periodically during timezone phase to detect completion.
	 * When all timezones are resolved, schedules AI generation for all cities.
	 *
	 * @since 3.3.0
	 * @since 3.3.11 Smart completion detection (checks Action Scheduler state)
	 */
	public function check_timezone_completion() {
		global $wpdb;

		// v3.3.11: PRIMARY CHECK - Action Scheduler pending/in-progress actions
		// This is the DEFINITIVE check because it reflects actual work being done
		$as_table = $wpdb->prefix . 'actionscheduler_actions';
		$pending_actions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$as_table}
				 WHERE hook = %s
				 AND status IN ('pending', 'in-progress')",
				'wta_lookup_timezone'
			)
		);

		// v3.3.11: SECONDARY CHECK - Database meta keys (backup check)
		// Count cities without resolved timezone
		$pending_timezone = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type' AND pm_type.meta_value = 'city'
				 LEFT JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id AND pm_tz.meta_key = 'wta_timezone_status'
				 WHERE p.post_type = %s
				 AND p.post_status IN ('publish', 'draft')
				 AND (pm_tz.meta_value IS NULL OR pm_tz.meta_value != 'resolved')",
				WTA_POST_TYPE
			)
		);

		// v3.3.11: Calculate total pending (either check can indicate pending work)
		$total_pending = max( $pending_actions, $pending_timezone );

		WTA_Logger::info( '🔍 Timezone completion check', array(
			'pending_actions'  => $pending_actions,  // Action Scheduler queue
			'pending_timezone' => $pending_timezone, // Database meta check
			'total_pending'    => $total_pending,    // Max of both
		) );

		if ( $total_pending > 0 ) {
			// v3.3.11: DYNAMIC RECHECK INTERVAL based on remaining work
			// Timezone lookups are slower (API calls), so use longer intervals
			if ( $total_pending < 50 ) {
				$recheck_delay = 60;  // 1 minute - almost done!
			} elseif ( $total_pending < 500 ) {
				$recheck_delay = 180; // 3 minutes - moderate amount left
			} else {
				$recheck_delay = 300; // 5 minutes - lots of work remaining
			}

			WTA_Logger::info( '⏳ Timezone phase in progress', array(
				'next_check_in' => $recheck_delay . 's',
				'reason'        => $total_pending < 50 ? 'Almost done!' : 
				                   ( $total_pending < 500 ? 'Moderate work' : 'Large import' ),
			) );

			as_schedule_single_action(
				time() + $recheck_delay,
				'wta_check_timezone_completion',
				array(),
				'wta_timezone'
			);
			return;
		}

		// v3.3.11: Timezone phase complete! Trigger City AI batch
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
		// v3.3.7: Use WTA_POST_TYPE constant (was hardcoded wrong type!)
		$entities_needing_ai = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, pm_type.meta_value as entity_type
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
				 WHERE p.post_type = %s
				 AND p.post_status IN ('publish', 'draft')
				 AND pm_type.meta_value IN ('continent', 'country')
				 AND NOT EXISTS (
					 SELECT 1 FROM {$wpdb->postmeta} pm_ai
					 WHERE pm_ai.post_id = p.ID
					 AND pm_ai.meta_key = 'wta_ai_generated'
					 AND pm_ai.meta_value = '1'
				 )",
				WTA_POST_TYPE
			)
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
		// v3.3.7: Use WTA_POST_TYPE constant (was hardcoded wrong type!)
		$cities_needing_ai = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, pm_type.meta_value as entity_type
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
				 WHERE p.post_type = %s
				 AND p.post_status IN ('publish', 'draft')
				 AND pm_type.meta_value = 'city'
				 AND NOT EXISTS (
					 SELECT 1 FROM {$wpdb->postmeta} pm_ai
					 WHERE pm_ai.post_id = p.ID
					 AND pm_ai.meta_key = 'wta_ai_generated'
					 AND pm_ai.meta_value = '1'
				 )",
				WTA_POST_TYPE
			)
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
