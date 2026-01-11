<?php
/**
 * Single timezone processor for Action Scheduler (Pilanto-AI Model).
 *
 * Processes ONE timezone lookup per action.
 * v3.2.83: Premium tier support!
 * - FREE tier: Rate limited to 1 req/s (enforced)
 * - Premium tier: No rate limiting, 10 req/s via concurrent
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/processors
 * @since      3.0.43
 * @since      3.2.83 Added Premium tier support
 */

class WTA_Single_Timezone_Processor {

	/**
	 * Lookup timezone for a single post.
	 *
	 * Action Scheduler unpacks args, so this receives separate parameters.
	 *
	 * @since    3.0.43
	 * @since    3.0.54  Added execution time logging.
	 * @param    int   $post_id Post ID.
	 * @param    float $lat     Latitude.
	 * @param    float $lng     Longitude.
	 */
	public function lookup_timezone( $post_id, $lat, $lng ) {
		$start_time = microtime( true );
		
		// Arguments already unpacked by Action Scheduler - no changes needed
		try {
			// v3.2.83: Premium tier detection
			$is_premium = get_option( 'wta_timezonedb_premium', false );
			$wait_time_applied = 0;
			
			// RATE LIMITING: Only apply for FREE tier
			if ( ! $is_premium ) {
				// FREE tier: 1 request/second (1.5s safety margin)
				$last_api_call = get_transient( 'wta_timezone_api_last_call' );
				
				if ( false !== $last_api_call ) {
					$time_since_last_call = microtime( true ) - $last_api_call;
					if ( $time_since_last_call < 1.5 ) {
						// Too soon! Wait and reschedule
						$wait_time = ceil( 2.0 - $time_since_last_call );
						$wait_time_applied = $wait_time;
						
						WTA_Logger::debug( 'Timezone API rate limit (FREE tier) - rescheduling', array(
							'post_id'   => $post_id,
							'wait_time' => $wait_time . ' seconds',
						) );
						
						as_schedule_single_action(
							time() + $wait_time,
							'wta_lookup_timezone',
							array( $post_id, $lat, $lng ),
							'wta_timezone'
						);
						return;
					}
				}
			}
			// Premium tier: No rate limiting! Concurrent 10 handles throughput
			
			// Set timestamp BEFORE API call (pessimistic locking)
			$api_start = microtime( true );
			set_transient( 'wta_timezone_api_last_call', microtime( true ), 5 );
			
			// Validate post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				WTA_Logger::warning( 'Post not found for timezone resolution', array(
					'post_id' => $post_id,
				) );
				return;
			}

			// Check if already resolved
			$timezone_status = get_post_meta( $post_id, 'wta_timezone_status', true );
			if ( 'resolved' === $timezone_status ) {
				WTA_Logger::info( 'Timezone already resolved', array( 'post_id' => $post_id ) );
				return;
			}

			// Resolve timezone via API
			$timezone = WTA_Timezone_Helper::resolve_timezone_api( $lat, $lng );

			if ( false === $timezone ) {
				// API call failed - implement retry logic
				$retry_count = intval( get_post_meta( $post_id, '_wta_timezone_retry_count', true ) );

				if ( $retry_count < 3 ) {
					// Increment retry counter
					$retry_count++;
					update_post_meta( $post_id, '_wta_timezone_retry_count', $retry_count );

					$next_delay = 5 * $retry_count; // 5s, 10s, 15s

					WTA_Logger::warning( 'Timezone API call failed, will retry', array(
						'post_id'      => $post_id,
						'retry_count'  => $retry_count,
						'next_delay'   => $next_delay . ' seconds',
					) );

				// Reschedule with exponential backoff
				as_schedule_single_action(
					time() + $next_delay,
					'wta_lookup_timezone',
					array( $post_id, $lat, $lng ),
					'wta_timezone'
				);
				} else {
					// Max retries reached
					WTA_Logger::error( 'Timezone resolution failed after 3 retries', array(
						'post_id' => $post_id,
						'lat'     => $lat,
						'lng'     => $lng,
					) );

					update_post_meta( $post_id, 'wta_timezone_status', 'failed' );
					delete_post_meta( $post_id, '_wta_timezone_retry_count' );
				}
				return;
			}

		// Success - save timezone
		delete_post_meta( $post_id, '_wta_timezone_retry_count' );
		update_post_meta( $post_id, 'wta_timezone', $timezone );
		update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
		update_post_meta( $post_id, 'wta_has_timezone', 1 ); // v3.0.58: Set flag for AI queue

		$api_time = round( microtime( true ) - $api_start, 3 );
		$execution_time = round( microtime( true ) - $start_time, 3 );
		
		WTA_Logger::info( '🌍 Timezone resolved', array(
			'post_id'        => $post_id,
			'timezone'       => $timezone,
			'api_time'       => $api_time . 's',
			'execution_time' => $execution_time . 's',
			'tier'           => $is_premium ? 'Premium (10 req/s)' : 'FREE (1 req/s)',
		) );

		// v3.3.0: NO AI scheduling here!
		// AI will be batch-scheduled after ALL timezones are resolved

		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to lookup timezone', array(
				'post_id' => $post_id,
				'error'   => $e->getMessage(),
			) );
		}
	}
}

