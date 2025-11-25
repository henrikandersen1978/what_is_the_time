<?php
/**
 * Cron job for processing structure queue (continents, countries, cities).
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/cron
 */

/**
 * Structure import cron class.
 *
 * @since 1.0.0
 */
class WTA_Cron_Structure {

	/**
	 * Batch size for processing.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * Lock transient key.
	 *
	 * @var string
	 */
	const LOCK_KEY = 'wta_cron_structure_lock';

	/**
	 * Process structure queue items.
	 *
	 * @since 1.0.0
	 */
	public function process() {
		// Check for lock to prevent overlap
		if ( get_transient( self::LOCK_KEY ) ) {
			WTA_Logger::debug( 'Structure cron already running, skipping' );
			return;
		}

		// Set lock for 5 minutes
		set_transient( self::LOCK_KEY, true, 300 );

		WTA_Logger::info( 'Starting structure cron' );

		$processed = 0;
		$errors = 0;

		try {
			// Process cities_import batch jobs first (converts to individual city jobs)
			$processed += $this->process_cities_import_jobs();

			// Process continents first
			$processed += $this->process_type( 'continent' );

			// Then countries
			$processed += $this->process_type( 'country' );

			// Then cities
			$processed += $this->process_type( 'city' );

			WTA_Logger::info( 'Structure cron completed', array(
				'processed' => $processed,
				'errors'    => $errors,
			) );
		} catch ( Exception $e ) {
			WTA_Logger::error( 'Structure cron failed', array(
				'error' => $e->getMessage(),
			) );
		} finally {
			// Release lock
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Process queue items of a specific type.
	 *
	 * @since 1.0.0
	 * @param string $type Queue item type.
	 * @return int Number of processed items.
	 */
	private function process_type( $type ) {
		$items = WTA_Queue::get_items( array(
			'type'     => $type,
			'status'   => 'pending',
			'limit'    => self::BATCH_SIZE,
			'order_by' => 'created_at',
			'order'    => 'ASC',
		) );

		if ( empty( $items ) ) {
			return 0;
		}

		$processed = 0;

		foreach ( $items as $item ) {
			// Check if approaching timeout
			if ( WTA_Utils::is_approaching_timeout( 10 ) ) {
				WTA_Logger::warning( 'Approaching timeout, stopping batch' );
				break;
			}

			// Mark as processing
			WTA_Queue::update_status( $item['id'], 'processing' );

			// Process item
			$result = WTA_Queue_Processor::process_item( $item );

			if ( is_wp_error( $result ) ) {
				$error_msg = $result->get_error_message();
				WTA_Queue::update_status( $item['id'], 'error', $error_msg );
				WTA_Logger::error( "Failed to process {$type}", array(
					'id'    => $item['id'],
					'error' => $error_msg,
				) );
			} else {
				$processed++;
			}
		}

		WTA_Logger::info( "Processed {$processed} {$type} items" );

		return $processed;
	}

	/**
	 * Process cities_import batch jobs.
	 *
	 * These are special jobs that load cities from file in batches
	 * and queue them as individual 'city' jobs.
	 *
	 * @since 1.0.0
	 * @return int Number of jobs processed.
	 */
	private function process_cities_import_jobs() {
		$items = WTA_Queue::get_items( array(
			'type'     => 'cities_import',
			'status'   => 'pending',
			'limit'    => 1, // Process one at a time
			'order_by' => 'created_at',
			'order'    => 'ASC',
		) );

		if ( empty( $items ) ) {
			return 0;
		}

		foreach ( $items as $item ) {
			WTA_Logger::info( 'Processing cities_import job', array( 'job_id' => $item['id'] ) );

			// Mark as processing
			WTA_Queue::update_status( $item['id'], 'processing' );

			$payload = $item['payload'];
			$countries = isset( $payload['countries'] ) ? $payload['countries'] : array();
			$options = array(
				'selected_continents' => isset( $payload['selected_continents'] ) ? $payload['selected_continents'] : array(),
				'min_population' => isset( $payload['min_population'] ) ? $payload['min_population'] : 0,
				'max_cities_per_country' => isset( $payload['max_cities_per_country'] ) ? $payload['max_cities_per_country'] : 0,
			);

			// Fetch cities (with streaming parser for large files)
			$cities = WTA_Github_Fetcher::fetch_cities();

			if ( is_wp_error( $cities ) ) {
				WTA_Queue::update_status( $item['id'], 'error', $cities->get_error_message() );
				WTA_Logger::error( 'Failed to fetch cities for batch job', array(
					'error' => $cities->get_error_message(),
				) );
				continue;
			}

			// Now queue individual cities (reuse the existing queue_cities logic)
			$queued_count = WTA_Importer::queue_cities_from_array( $cities, $countries, $options );

			// Mark job as done
			WTA_Queue::update_status( $item['id'], 'done' );
			WTA_Logger::info( "Cities_import job completed", array(
				'job_id' => $item['id'],
				'cities_queued' => $queued_count,
				'total_cities_in_file' => count( $cities ),
				'min_population_filter' => $options['min_population'],
				'selected_continents' => implode( ', ', $options['selected_continents'] ),
			) );

			return 1; // Processed 1 job
		}

		return 0;
	}
}





