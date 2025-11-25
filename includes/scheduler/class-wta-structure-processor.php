<?php
/**
 * Structure processor for Action Scheduler.
 *
 * Processes continents, countries, and cities to create CPT posts.
 * CRITICAL: Translates BEFORE post creation to ensure Danish URLs!
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/scheduler
 */

class WTA_Structure_Processor {

	/**
	 * Process batch.
	 *
	 * Called by Action Scheduler every minute.
	 *
	 * @since    2.0.0
	 */
	public function process_batch() {
		// Reset any stuck items first
		WTA_Queue::reset_stuck();

		// Get pending items (batch of 50)
		$items = WTA_Queue::get_pending( null, 50 );

		if ( empty( $items ) ) {
			return;
		}

		WTA_Logger::info( 'Structure processor started', array(
			'items' => count( $items ),
		) );

		foreach ( $items as $item ) {
			$this->process_item( $item );
		}

		WTA_Logger::info( 'Structure processor completed', array(
			'processed' => count( $items ),
		) );
	}

	/**
	 * Process single queue item.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_item( $item ) {
		// Mark as processing
		WTA_Queue::mark_processing( $item['id'] );

		try {
			switch ( $item['type'] ) {
				case 'continent':
					$this->process_continent( $item );
					break;
				case 'country':
					$this->process_country( $item );
					break;
				case 'city':
					$this->process_city( $item );
					break;
				case 'cities_import':
					$this->process_cities_import( $item );
					break;
				default:
					WTA_Logger::warning( 'Unknown queue item type', array( 'type' => $item['type'] ) );
					WTA_Queue::mark_done( $item['id'] );
			}
		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to process queue item', array(
				'id'    => $item['id'],
				'type'  => $item['type'],
				'error' => $e->getMessage(),
			) );
			WTA_Queue::mark_failed( $item['id'], $e->getMessage() );
		}
	}

	/**
	 * Process continent.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_continent( $item ) {
		$data = $item['payload'];

		// Check if post already exists
		$existing = get_page_by_path( sanitize_title( $data['name_local'] ), OBJECT, WTA_POST_TYPE );
		if ( $existing ) {
			WTA_Logger::info( 'Continent post already exists', array( 'name' => $data['name_local'] ) );
			WTA_Queue::mark_done( $item['id'] );
			return;
		}

		// Create post with Danish name and slug
		$post_id = wp_insert_post( array(
			'post_title'   => $data['name_local'],
			'post_name'    => sanitize_title( $data['name_local'] ),
			'post_type'    => WTA_POST_TYPE,
			'post_status'  => 'draft', // Will be published after AI content
			'post_parent'  => 0,
		) );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Save meta
		update_post_meta( $post_id, 'wta_type', 'continent' );
		update_post_meta( $post_id, 'wta_name_original', $data['name'] );
		update_post_meta( $post_id, 'wta_name_danish', $data['name_local'] );
		update_post_meta( $post_id, 'wta_continent_code', WTA_Utils::get_continent_code( $data['name'] ) );
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );

		// Queue AI content generation
		WTA_Queue::add( 'ai_content', array(
			'post_id' => $post_id,
			'type'    => 'continent',
		), 'ai_continent_' . $post_id );

		WTA_Logger::info( 'Continent post created', array(
			'post_id' => $post_id,
			'name'    => $data['name_local'],
		) );

		WTA_Queue::mark_done( $item['id'] );
	}

	/**
	 * Process country.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_country( $item ) {
		$data = $item['payload'];

		// Find parent continent post
		$continent_name_local = WTA_AI_Translator::translate( $data['continent'], 'continent' );
		$parent = get_page_by_path( sanitize_title( $continent_name_local ), OBJECT, WTA_POST_TYPE );

		if ( ! $parent ) {
			WTA_Logger::warning( 'Parent continent not found', array(
				'continent' => $data['continent'],
				'country'   => $data['name'],
			) );
			// Requeue for later
			WTA_Queue::mark_failed( $item['id'], 'Parent continent not found' );
			return;
		}

		// Check if post already exists
		$existing = get_posts( array(
			'name'        => sanitize_title( $data['name_local'] ),
			'post_type'   => WTA_POST_TYPE,
			'post_parent' => $parent->ID,
			'numberposts' => 1,
		) );

		if ( ! empty( $existing ) ) {
			WTA_Logger::info( 'Country post already exists', array( 'name' => $data['name_local'] ) );
			WTA_Queue::mark_done( $item['id'] );
			return;
		}

		// Create post with Danish name and slug
		$post_id = wp_insert_post( array(
			'post_title'   => $data['name_local'],
			'post_name'    => sanitize_title( $data['name_local'] ),
			'post_type'    => WTA_POST_TYPE,
			'post_status'  => 'draft',
			'post_parent'  => $parent->ID,
		) );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Save meta
		update_post_meta( $post_id, 'wta_type', 'country' );
		update_post_meta( $post_id, 'wta_name_original', $data['name'] );
		update_post_meta( $post_id, 'wta_name_danish', $data['name_local'] );
		update_post_meta( $post_id, 'wta_continent_code', WTA_Utils::get_continent_code( $data['continent'] ) );
		update_post_meta( $post_id, 'wta_country_code', $data['country_code'] );
		update_post_meta( $post_id, 'wta_country_id', $data['country_id'] );
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );

		// Determine timezone
		if ( WTA_Timezone_Helper::is_complex_country( $data['country_code'] ) ) {
			// Will need API lookup (per city)
			update_post_meta( $post_id, 'wta_timezone', 'multiple' );
			update_post_meta( $post_id, 'wta_timezone_status', 'multiple' );
		} else {
			// Simple country - get default timezone
			$timezone = WTA_Timezone_Helper::get_country_timezone( $data['country_code'] );
			if ( $timezone ) {
				update_post_meta( $post_id, 'wta_timezone', $timezone );
				update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
			}
		}

		// Queue AI content generation
		WTA_Queue::add( 'ai_content', array(
			'post_id' => $post_id,
			'type'    => 'country',
		), 'ai_country_' . $post_id );

		WTA_Logger::info( 'Country post created', array(
			'post_id' => $post_id,
			'name'    => $data['name_local'],
		) );

		WTA_Queue::mark_done( $item['id'] );
	}

	/**
	 * Process city.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_city( $item ) {
		$data = $item['payload'];

		// Find parent country post
		$country_post_id = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'meta_key'    => 'wta_country_id',
			'meta_value'  => $data['country_id'],
			'numberposts' => 1,
			'fields'      => 'ids',
		) );

		if ( empty( $country_post_id ) ) {
			WTA_Logger::warning( 'Parent country not found', array(
				'country_id' => $data['country_id'],
				'city'       => $data['name'],
			) );
			WTA_Queue::mark_failed( $item['id'], 'Parent country not found' );
			return;
		}

		$parent_id = $country_post_id[0];

		// Check if post already exists
		$existing = get_posts( array(
			'name'        => sanitize_title( $data['name_local'] ),
			'post_type'   => WTA_POST_TYPE,
			'post_parent' => $parent_id,
			'numberposts' => 1,
		) );

		if ( ! empty( $existing ) ) {
			WTA_Logger::info( 'City post already exists', array( 'name' => $data['name_local'] ) );
			WTA_Queue::mark_done( $item['id'] );
			return;
		}

		// Create post with Danish name and slug
		$post_id = wp_insert_post( array(
			'post_title'   => $data['name_local'],
			'post_name'    => sanitize_title( $data['name_local'] ),
			'post_type'    => WTA_POST_TYPE,
			'post_status'  => 'draft',
			'post_parent'  => $parent_id,
		) );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Get country meta
		$country_code = get_post_meta( $parent_id, 'wta_country_code', true );
		$continent_code = get_post_meta( $parent_id, 'wta_continent_code', true );

		// Save meta
		update_post_meta( $post_id, 'wta_type', 'city' );
		update_post_meta( $post_id, 'wta_name_original', $data['name'] );
		update_post_meta( $post_id, 'wta_name_danish', $data['name_local'] );
		update_post_meta( $post_id, 'wta_continent_code', $continent_code );
		update_post_meta( $post_id, 'wta_country_code', $country_code );
		update_post_meta( $post_id, 'wta_country_id', $data['country_id'] );
		update_post_meta( $post_id, 'wta_city_id', $data['city_id'] );
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );

		if ( isset( $data['latitude'] ) ) {
			update_post_meta( $post_id, 'wta_lat', $data['latitude'] );
		}
		if ( isset( $data['longitude'] ) ) {
			update_post_meta( $post_id, 'wta_lng', $data['longitude'] );
		}

		// Handle timezone
		$needs_timezone_api = false;

		if ( WTA_Timezone_Helper::is_complex_country( $country_code ) ) {
			// Complex country - need API lookup if we have lat/lng
			if ( isset( $data['latitude'] ) && isset( $data['longitude'] ) ) {
				$needs_timezone_api = true;
				update_post_meta( $post_id, 'wta_timezone_status', 'pending' );

				// Queue timezone resolution
				WTA_Queue::add( 'timezone', array(
					'post_id' => $post_id,
					'lat'     => $data['latitude'],
					'lng'     => $data['longitude'],
				), 'timezone_' . $post_id );
			} else {
				// No lat/lng - use country default as fallback
				$timezone = WTA_Timezone_Helper::get_country_timezone( $country_code );
				if ( $timezone ) {
					update_post_meta( $post_id, 'wta_timezone', $timezone );
					update_post_meta( $post_id, 'wta_timezone_status', 'fallback' );
				}
			}
		} else {
			// Simple country
			$timezone = WTA_Timezone_Helper::get_country_timezone( $country_code );
			if ( $timezone ) {
				update_post_meta( $post_id, 'wta_timezone', $timezone );
				update_post_meta( $post_id, 'wta_timezone_status', 'resolved' );
			}
		}

		// Queue AI content generation (only if timezone is resolved or not needed)
		if ( ! $needs_timezone_api ) {
			WTA_Queue::add( 'ai_content', array(
				'post_id' => $post_id,
				'type'    => 'city',
			), 'ai_city_' . $post_id );
		}

		WTA_Logger::info( 'City post created', array(
			'post_id' => $post_id,
			'name'    => $data['name_local'],
		) );

		WTA_Queue::mark_done( $item['id'] );
	}

	/**
	 * Process cities import batch job.
	 *
	 * Streams cities.json and queues individual cities.
	 *
	 * @since    2.0.0
	 * @param    array $item Queue item.
	 */
	private function process_cities_import( $item ) {
		$options = $item['payload'];
		$file_path = $options['file_path'];

		if ( ! file_exists( $file_path ) ) {
			throw new Exception( 'cities.json not found' );
		}

		// Load cities
		$content = file_get_contents( $file_path );
		$cities = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'JSON decode error: ' . json_last_error_msg() );
		}

		// Queue individual cities
		$queued = WTA_Importer::queue_cities_from_array( $cities, $options );

		WTA_Logger::info( 'Cities import batch completed', array(
			'cities_queued' => $queued,
			'total_cities'  => count( $cities ),
			'min_population' => $options['min_population'],
		) );

		WTA_Queue::mark_done( $item['id'] );
	}
}


