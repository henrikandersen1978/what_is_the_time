<?php
/**
 * Timezone processor for Action Scheduler.
 *
 * Resolves timezones using TimeZoneDB API.
 * Respects rate limit: 1 request/second.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/scheduler
 */

class WTA_Timezone_Processor {

	/**
	 * Process batch.
	 *
	 * Called by Action Scheduler every 5 minutes.
	 * Processes 5 items with 200ms delay between each (rate limiting).
	 *
	 * @since    2.0.0
	 */
	public function process_batch() {
		// Get pending timezone items (batch size optimized for 60-second time limit)
		$items = WTA_Queue::get_pending( 'timezone', 25 );

		if ( empty( $items ) ) {
			return;
		}

		$start_time = microtime( true );

		WTA_Logger::info( 'Timezone processor started', array(
			'items' => count( $items ),
		) );

		$processed = 0;
		foreach ( $items as $item ) {
			$this->process_item( $item );
			$processed++;

			// Rate limiting: Wait 1.1 seconds between requests (respects 1 req/sec limit)
			if ( $processed < count( $items ) ) {
				usleep( 1100000 ); // 1.1 seconds in microseconds
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
				// API call failed - retry later
				WTA_Logger::warning( 'Timezone API call failed', array(
					'post_id' => $post_id,
					'lat'     => $lat,
					'lng'     => $lng,
				) );
				WTA_Queue::mark_failed( $item['id'], 'TimeZoneDB API call failed' );
				return;
			}

			// Save timezone
			update_post_meta( $post_id, 'wta_timezone', $timezone );
			update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );

			WTA_Logger::info( 'Timezone resolved', array(
				'post_id'  => $post_id,
				'timezone' => $timezone,
			) );

			// Queue AI content generation now that timezone is resolved
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


