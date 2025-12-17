<?php
/**
 * Timezone processor for Action Scheduler.
 *
 * Resolves timezones using TimeZoneDB API.
 * Conservative rate limiting: 1.5 second base delay with exponential backoff for retries.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/scheduler
 */

class WTA_Timezone_Processor {

	/**
	 * Process batch.
	 *
	 * Called by Action Scheduler every 5 minutes.
	 * Processes up to 8 items with exponential backoff based on retry count.
	 * Stops processing at 55 seconds to respect 60-second time limit.
	 *
	 * @since    2.0.0
	 */
	public function process_batch() {
		// Dynamic batch size based on cron interval
		// v3.0.7: Reduced batch size + increased delay for FREE tier rate limit safety
		// Each item: ~2.0s (API call + 2.0s delay)
		$cron_interval = intval( get_option( 'wta_cron_interval', 60 ) );
		
		// 1-min: 5 items (~10s), 5-min: 15 items (~30s)
		// Conservative for TimeZoneDB FREE tier (1 req/s limit)
		$batch_size = ( $cron_interval >= 300 ) ? 15 : 5;
		
		$items = WTA_Queue::get_pending( 'timezone', $batch_size );

		if ( empty( $items ) ) {
			return;
		}

		$start_time = microtime( true );

		WTA_Logger::info( 'Timezone processor started', array(
			'items' => count( $items ),
		) );

		$processed = 0;
		foreach ( $items as $item ) {
			// Get retry count for exponential backoff calculation
			$data = $item['payload'];
			$post_id = isset( $data['post_id'] ) ? $data['post_id'] : 0;
			$retry_count = $post_id ? intval( get_post_meta( $post_id, '_wta_timezone_retry_count', true ) ) : 0;

			// Process item
			$this->process_item( $item );
			$processed++;

			// Apply exponential backoff delay between requests
			// v3.0.7: Increased from 1.5s to 2.0s for FREE tier rate limit safety
			if ( $processed < count( $items ) ) {
				$base_delay = 2000000; // 2.0 seconds in microseconds (0.5 req/s)
				$multiplier = 1 + ( $retry_count * 0.5 ); // 1x, 1.5x, 2x, 2.5x
				$actual_delay = intval( $base_delay * $multiplier );

				usleep( $actual_delay );

				WTA_Logger::debug( 'Rate limit delay applied', array(
					'retry_count' => $retry_count,
					'delay_seconds' => round( $actual_delay / 1000000, 2 ),
				) );
			}

			// Safety check: Stop early to respect time limit
			$elapsed = microtime( true ) - $start_time;
			$time_limit = ( $cron_interval >= 300 ) ? 260 : 55;
			
			if ( $elapsed > $time_limit ) {
				WTA_Logger::warning( 'Timezone batch stopped early to respect time limit', array(
					'processed' => $processed,
					'remaining' => count( $items ) - $processed,
					'elapsed_seconds' => round( $elapsed, 2 ),
					'time_limit' => $time_limit,
				) );
				break;
			}
		}

		$duration = round( microtime( true ) - $start_time, 2 );

		WTA_Logger::info( 'Timezone processor completed', array(
			'processed' => $processed,
			'duration_seconds' => $duration,
			'avg_per_item' => round( $duration / max( $processed, 1 ), 2 ),
		) );
	}

	/**
	 * Process single timezone resolution.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_item( $item ) {
		WTA_Queue::mark_processing( $item['id'] );

		try {
			$data = $item['payload'];
			$post_id = $data['post_id'];
			$lat = $data['lat'];
			$lng = $data['lng'];

			// Validate post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				WTA_Logger::warning( 'Post not found for timezone resolution', array(
					'post_id' => $post_id,
				) );
				WTA_Queue::mark_done( $item['id'] );
				return;
			}

			// Check if already resolved
			$timezone_status = get_post_meta( $post_id, 'wta_timezone_status', true );
			if ( 'resolved' === $timezone_status ) {
				WTA_Logger::info( 'Timezone already resolved', array( 'post_id' => $post_id ) );
				WTA_Queue::mark_done( $item['id'] );
				return;
			}

		// Resolve timezone via API
		$timezone = WTA_Timezone_Helper::resolve_timezone_api( $lat, $lng );

		if ( false === $timezone ) {
			// API call failed - implement retry logic with exponential backoff
			$retry_count = intval( get_post_meta( $post_id, '_wta_timezone_retry_count', true ) );

			if ( $retry_count < 3 ) {
				// Increment retry counter
				$retry_count++;
				update_post_meta( $post_id, '_wta_timezone_retry_count', $retry_count );

				$next_delay = 1.5 * ( 1 + ( $retry_count * 0.5 ) );

				WTA_Logger::warning( 'Timezone API call failed, will retry with backoff', array(
					'post_id'      => $post_id,
					'lat'          => $lat,
					'lng'          => $lng,
					'retry_count'  => $retry_count,
					'next_delay'   => $next_delay . ' seconds',
				) );

				WTA_Queue::mark_failed( $item['id'], 'API call failed (retry ' . $retry_count . '/3, next delay: ' . $next_delay . 's)' );
			} else {
				// Max retries reached - mark as permanently failed
				WTA_Logger::error( 'Timezone resolution failed after 3 retries', array(
					'post_id' => $post_id,
					'lat'     => $lat,
					'lng'     => $lng,
				) );

				update_post_meta( $post_id, 'wta_timezone_status', 'failed' );
				delete_post_meta( $post_id, '_wta_timezone_retry_count' );

				WTA_Queue::mark_failed( $item['id'], 'TimeZoneDB API failed after 3 retries' );
			}
			return;
		}

		// Success - clear retry count and save timezone
		delete_post_meta( $post_id, '_wta_timezone_retry_count' );
		update_post_meta( $post_id, 'wta_timezone', $timezone );
		update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );

		WTA_Logger::info( 'Timezone resolved', array(
			'post_id'  => $post_id,
			'timezone' => $timezone,
		) );

		// ==========================================
		// QUEUE AI CONTENT AFTER TIMEZONE RESOLVED (v2.35.8)
		// For complex countries (US/RU/CA/etc), we wait for accurate timezone
		// before generating AI content to ensure quality
		// Simple countries skip this and queue AI immediately during city creation
		// ==========================================
		WTA_Queue::add( 'ai_content', array(
			'post_id' => $post_id,
			'type'    => get_post_meta( $post_id, 'wta_type', true ),
		), 'ai_' . get_post_meta( $post_id, 'wta_type', true ) . '_' . $post_id );

			WTA_Queue::mark_done( $item['id'] );

		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to process timezone item', array(
				'id'    => $item['id'],
				'error' => $e->getMessage(),
			) );
			WTA_Queue::mark_failed( $item['id'], $e->getMessage() );
		}
	}
}


