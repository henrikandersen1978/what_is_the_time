<?php
/**
 * Cron job for generating AI content.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/cron
 */

/**
 * AI content generation cron class.
 *
 * @since 1.0.0
 */
class WTA_Cron_AI {

	/**
	 * Batch size for processing.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 10;

	/**
	 * Lock transient key.
	 *
	 * @var string
	 */
	const LOCK_KEY = 'wta_cron_ai_lock';

	/**
	 * Process AI content generation queue items.
	 *
	 * @since 1.0.0
	 */
	public function process() {
		// Check for lock to prevent overlap
		if ( get_transient( self::LOCK_KEY ) ) {
			WTA_Logger::debug( 'AI cron already running, skipping' );
			return;
		}

		// Set lock for 10 minutes (AI calls can take longer)
		set_transient( self::LOCK_KEY, true, 600 );

		WTA_Logger::info( 'Starting AI content cron' );

		$processed = 0;
		$errors = 0;

		try {
			$items = WTA_Queue::get_items( array(
				'type'     => 'ai_content',
				'status'   => 'pending',
				'limit'    => self::BATCH_SIZE,
				'order_by' => 'created_at',
				'order'    => 'ASC',
			) );

			if ( empty( $items ) ) {
				WTA_Logger::debug( 'No AI content items to process' );
				delete_transient( self::LOCK_KEY );
				return;
			}

			foreach ( $items as $item ) {
				// Check if approaching timeout
				if ( WTA_Utils::is_approaching_timeout( 30 ) ) {
					WTA_Logger::warning( 'Approaching timeout, stopping batch' );
					break;
				}

				// Mark as processing
				WTA_Queue::update_status( $item['id'], 'processing' );

				// Process item
				$result = $this->process_ai_item( $item );

				if ( is_wp_error( $result ) ) {
					$error_msg = $result->get_error_message();
					WTA_Queue::update_status( $item['id'], 'error', $error_msg );
					WTA_Logger::error( 'Failed to generate AI content', array(
						'id'    => $item['id'],
						'error' => $error_msg,
					) );
					$errors++;
				} else {
					WTA_Queue::update_status( $item['id'], 'done' );
					$processed++;
				}

				// Small delay between AI calls
				sleep( 1 );
			}

			WTA_Logger::info( 'AI content cron completed', array(
				'processed' => $processed,
				'errors'    => $errors,
			) );
		} catch ( Exception $e ) {
			WTA_Logger::error( 'AI content cron failed', array(
				'error' => $e->getMessage(),
			) );
		} finally {
			// Release lock
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Process an AI content queue item.
	 *
	 * @since 1.0.0
	 * @param array $item Queue item.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function process_ai_item( $item ) {
		$payload = $item['payload'];
		
		if ( empty( $payload['post_id'] ) ) {
			return new WP_Error( 'invalid_payload', __( 'Invalid AI content queue payload.', WTA_TEXT_DOMAIN ) );
		}

		$post_id = $payload['post_id'];

		// Check if post exists
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', WTA_TEXT_DOMAIN ) );
		}

		// For cities, ensure timezone is resolved first
		$type = get_post_meta( $post_id, 'wta_type', true );
		if ( $type === 'city' ) {
			$timezone_status = get_post_meta( $post_id, 'wta_timezone_status', true );
			if ( $timezone_status !== 'resolved' && $timezone_status !== 'fallback' ) {
				// Re-queue for later
				return new WP_Error( 'timezone_not_ready', __( 'Waiting for timezone resolution.', WTA_TEXT_DOMAIN ) );
			}
		}

		// Generate AI content
		$result = WTA_AI_Generator::generate_content_for_post( $post_id );

		return $result;
	}
}






