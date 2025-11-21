<?php
/**
 * Cron job for resolving timezones via TimeZoneDB API.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/cron
 */

/**
 * Timezone resolution cron class.
 *
 * @since 1.0.0
 */
class WTA_Cron_Timezone {

	/**
	 * Batch size for processing (small due to API rate limit).
	 *
	 * @var int
	 */
	const BATCH_SIZE = 5;

	/**
	 * Lock transient key.
	 *
	 * @var string
	 */
	const LOCK_KEY = 'wta_cron_timezone_lock';

	/**
	 * Process timezone queue items.
	 *
	 * @since 1.0.0
	 */
	public function process() {
		// Check for lock to prevent overlap
		if ( get_transient( self::LOCK_KEY ) ) {
			WTA_Logger::debug( 'Timezone cron already running, skipping' );
			return;
		}

		// Set lock for 5 minutes
		set_transient( self::LOCK_KEY, true, 300 );

		WTA_Logger::info( 'Starting timezone cron' );

		$processed = 0;
		$errors = 0;

		try {
			$items = WTA_Queue::get_items( array(
				'type'     => 'timezone',
				'status'   => 'pending',
				'limit'    => self::BATCH_SIZE,
				'order_by' => 'created_at',
				'order'    => 'ASC',
			) );

			if ( empty( $items ) ) {
				WTA_Logger::debug( 'No timezone items to process' );
				delete_transient( self::LOCK_KEY );
				return;
			}

			foreach ( $items as $item ) {
				// Check if approaching timeout
				if ( WTA_Utils::is_approaching_timeout( 10 ) ) {
					WTA_Logger::warning( 'Approaching timeout, stopping batch' );
					break;
				}

				// Mark as processing
				WTA_Queue::update_status( $item['id'], 'processing' );

				// Process item
				$result = $this->process_timezone_item( $item );

				if ( is_wp_error( $result ) ) {
					$error_msg = $result->get_error_message();
					WTA_Queue::update_status( $item['id'], 'error', $error_msg );
					WTA_Logger::error( 'Failed to resolve timezone', array(
						'id'    => $item['id'],
						'error' => $error_msg,
					) );
					$errors++;
				} else {
					WTA_Queue::update_status( $item['id'], 'done' );
					$processed++;
				}

				// Rate limiting: sleep 200-300ms between requests
				WTA_Timezone_Resolver::rate_limit_sleep();
			}

			WTA_Logger::info( 'Timezone cron completed', array(
				'processed' => $processed,
				'errors'    => $errors,
			) );
		} catch ( Exception $e ) {
			WTA_Logger::error( 'Timezone cron failed', array(
				'error' => $e->getMessage(),
			) );
		} finally {
			// Release lock
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Process a timezone queue item.
	 *
	 * @since 1.0.0
	 * @param array $item Queue item.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function process_timezone_item( $item ) {
		$payload = $item['payload'];
		
		if ( empty( $payload['post_id'] ) || empty( $payload['lat'] ) || empty( $payload['lng'] ) ) {
			return new WP_Error( 'invalid_payload', __( 'Invalid timezone queue payload.', WTA_TEXT_DOMAIN ) );
		}

		$post_id = $payload['post_id'];
		$lat = $payload['lat'];
		$lng = $payload['lng'];

		// Resolve timezone
		$timezone = WTA_Timezone_Resolver::resolve_timezone( $lat, $lng );

		if ( is_wp_error( $timezone ) ) {
			return $timezone;
		}

		// Update post meta
		update_post_meta( $post_id, 'wta_timezone', $timezone );
		update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );

		WTA_Logger::info( 'Timezone resolved', array(
			'post_id'  => $post_id,
			'timezone' => $timezone,
		) );

		// Queue AI content generation now that timezone is resolved
		$ai_status = get_post_meta( $post_id, 'wta_ai_status', true );
		if ( $ai_status === 'pending' ) {
			$type = get_post_meta( $post_id, 'wta_type', true );
			WTA_Queue::insert( 'ai_content', $post_id, array(
				'post_id' => $post_id,
				'type'    => $type,
			) );
		}

		return true;
	}
}




