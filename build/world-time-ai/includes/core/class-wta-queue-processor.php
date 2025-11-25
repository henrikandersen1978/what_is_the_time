<?php
/**
 * Process queue items and create CPT posts.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

/**
 * Queue processor class.
 *
 * @since 1.0.0
 */
class WTA_Queue_Processor {

	/**
	 * Process a single queue item.
	 *
	 * @since 1.0.0
	 * @param array $item Queue item.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function process_item( $item ) {
		if ( empty( $item ) || empty( $item['type'] ) ) {
			return new WP_Error( 'invalid_item', __( 'Invalid queue item.', WTA_TEXT_DOMAIN ) );
		}

		$type = $item['type'];
		$payload = isset( $item['payload'] ) ? $item['payload'] : array();

		WTA_Logger::debug( "Processing {$type} queue item", array( 'id' => $item['id'] ) );

		switch ( $type ) {
			case 'continent':
				return self::process_continent( $item['id'], $payload );
			case 'country':
				return self::process_country( $item['id'], $payload );
			case 'city':
				return self::process_city( $item['id'], $payload );
			default:
				return new WP_Error( 'unknown_type', sprintf( __( 'Unknown queue type: %s', WTA_TEXT_DOMAIN ), $type ) );
		}
	}

	/**
	 * Process continent queue item.
	 *
	 * @since 1.0.0
	 * @param int   $queue_id Queue item ID.
	 * @param array $data     Continent data.
	 * @return bool|WP_Error True on success.
	 */
	private static function process_continent( $queue_id, $data ) {
		if ( empty( $data['name'] ) || empty( $data['code'] ) ) {
			return new WP_Error( 'missing_data', __( 'Missing continent name or code.', WTA_TEXT_DOMAIN ) );
		}

		// Check if continent already exists
		$existing_id = WTA_Utils::post_exists_by_meta( 'wta_continent_code', $data['code'] );
		if ( $existing_id ) {
			WTA_Logger::info( 'Continent already exists', array( 'code' => $data['code'], 'post_id' => $existing_id ) );
			WTA_Queue::update_status( $queue_id, 'done' );
			return true;
		}

		// Get Danish name immediately to ensure Danish URL
		$english_name = $data['name'];
		$danish_name = WTA_Quick_Translate::get_danish_name( $english_name );
		$slug = WTA_Utils::generate_slug( $danish_name );

		// Create post with Danish name
		$post_data = array(
			'post_title'   => $danish_name,
			'post_name'    => $slug,
			'post_type'    => WTA_POST_TYPE,
			'post_status'  => 'draft', // Will be published after AI content generation
			'post_parent'  => 0,
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$error_msg = $post_id->get_error_message();
			WTA_Queue::update_status( $queue_id, 'error', $error_msg );
			WTA_Logger::error( 'Failed to create continent post', array( 'error' => $error_msg ) );
			return $post_id;
		}

		// Add meta data
		update_post_meta( $post_id, 'wta_type', 'continent' );
		update_post_meta( $post_id, 'wta_continent_code', $data['code'] );
		update_post_meta( $post_id, 'wta_name_original', $english_name );
		update_post_meta( $post_id, 'wta_name_danish', $danish_name );
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );

		// Queue AI content generation
		WTA_Queue::insert( 'ai_content', $post_id, array( 'post_id' => $post_id, 'type' => 'continent' ) );

		WTA_Queue::update_status( $queue_id, 'done' );
		WTA_Logger::info( 'Created continent', array( 'code' => $data['code'], 'post_id' => $post_id ) );

		return true;
	}

	/**
	 * Process country queue item.
	 *
	 * @since 1.0.0
	 * @param int   $queue_id Queue item ID.
	 * @param array $data     Country data.
	 * @return bool|WP_Error True on success.
	 */
	private static function process_country( $queue_id, $data ) {
		if ( empty( $data['name'] ) || empty( $data['id'] ) ) {
			return new WP_Error( 'missing_data', __( 'Missing country name or ID.', WTA_TEXT_DOMAIN ) );
		}

		// Check if country already exists
		$existing_id = WTA_Utils::post_exists_by_meta( 'wta_country_id', $data['id'] );
		if ( $existing_id ) {
			WTA_Logger::info( 'Country already exists', array( 'id' => $data['id'], 'post_id' => $existing_id ) );
			WTA_Queue::update_status( $queue_id, 'done' );
			return true;
		}

		// Get parent continent
		$region = isset( $data['region'] ) ? $data['region'] : '';
		$continent_code = WTA_Utils::get_continent_code( $region );
		$parent_id = WTA_Utils::post_exists_by_meta( 'wta_continent_code', $continent_code );

		if ( ! $parent_id ) {
			$error_msg = sprintf( __( 'Parent continent not found: %s', WTA_TEXT_DOMAIN ), $region );
			WTA_Queue::update_status( $queue_id, 'error', $error_msg );
			WTA_Logger::error( $error_msg, array( 'country' => $data['name'] ) );
			return new WP_Error( 'parent_not_found', $error_msg );
		}

		// Get Danish name immediately to ensure Danish URL
		$english_name = $data['name'];
		$danish_name = WTA_Quick_Translate::get_danish_name( $english_name );
		$slug = WTA_Utils::generate_slug( $danish_name );
		$country_code = isset( $data['iso2'] ) ? $data['iso2'] : '';
		$lat = isset( $data['latitude'] ) ? WTA_Utils::sanitize_latitude( $data['latitude'] ) : null;
		$lng = isset( $data['longitude'] ) ? WTA_Utils::sanitize_longitude( $data['longitude'] ) : null;

		// Get default timezone for country
		$timezone = WTA_Timezone_Helper::get_default_timezone_for_country( $country_code );
		$timezone_status = $timezone ? 'resolved' : 'pending';

		// Create post with Danish name
		$post_data = array(
			'post_title'   => $danish_name,
			'post_name'    => $slug,
			'post_type'    => WTA_POST_TYPE,
			'post_status'  => 'draft',
			'post_parent'  => $parent_id,
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$error_msg = $post_id->get_error_message();
			WTA_Queue::update_status( $queue_id, 'error', $error_msg );
			WTA_Logger::error( 'Failed to create country post', array( 'error' => $error_msg ) );
			return $post_id;
		}

		// Add meta data
		update_post_meta( $post_id, 'wta_type', 'country' );
		update_post_meta( $post_id, 'wta_continent_code', $continent_code );
		update_post_meta( $post_id, 'wta_country_code', $country_code );
		update_post_meta( $post_id, 'wta_country_id', $data['id'] );
		update_post_meta( $post_id, 'wta_name_original', $english_name );
		update_post_meta( $post_id, 'wta_name_danish', $danish_name );
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );
		
		if ( $lat !== null ) {
			update_post_meta( $post_id, 'wta_lat', $lat );
		}
		if ( $lng !== null ) {
			update_post_meta( $post_id, 'wta_lng', $lng );
		}
		if ( $timezone ) {
			update_post_meta( $post_id, 'wta_timezone', $timezone );
		}
		update_post_meta( $post_id, 'wta_timezone_status', $timezone_status );

		// Queue AI content generation
		WTA_Queue::insert( 'ai_content', $post_id, array( 'post_id' => $post_id, 'type' => 'country' ) );

		WTA_Queue::update_status( $queue_id, 'done' );
		WTA_Logger::info( 'Created country', array( 'id' => $data['id'], 'post_id' => $post_id ) );

		return true;
	}

	/**
	 * Process city queue item.
	 *
	 * @since 1.0.0
	 * @param int   $queue_id Queue item ID.
	 * @param array $data     City data.
	 * @return bool|WP_Error True on success.
	 */
	private static function process_city( $queue_id, $data ) {
		if ( empty( $data['name'] ) || empty( $data['id'] ) || empty( $data['country_id'] ) ) {
			return new WP_Error( 'missing_data', __( 'Missing city name, ID, or country ID.', WTA_TEXT_DOMAIN ) );
		}

		// Check if city already exists
		$existing_id = WTA_Utils::post_exists_by_meta( 'wta_city_id', $data['id'] );
		if ( $existing_id ) {
			WTA_Logger::info( 'City already exists', array( 'id' => $data['id'], 'post_id' => $existing_id ) );
			WTA_Queue::update_status( $queue_id, 'done' );
			return true;
		}

		// Get parent country
		$parent_id = WTA_Utils::post_exists_by_meta( 'wta_country_id', $data['country_id'] );
		if ( ! $parent_id ) {
			$error_msg = sprintf( __( 'Parent country not found for city: %s', WTA_TEXT_DOMAIN ), $data['name'] );
			WTA_Queue::update_status( $queue_id, 'error', $error_msg );
			WTA_Logger::warning( $error_msg, array( 'city' => $data['name'], 'country_id' => $data['country_id'] ) );
			return new WP_Error( 'parent_not_found', $error_msg );
		}

		// Get Danish name immediately to ensure Danish URL
		$english_name = $data['name'];
		$danish_name = WTA_Quick_Translate::get_danish_name( $english_name );
		$slug = WTA_Utils::generate_slug( $danish_name );
		$country_code = isset( $data['country_code'] ) ? $data['country_code'] : '';
		$region = isset( $data['region'] ) ? $data['region'] : '';
		$continent_code = WTA_Utils::get_continent_code( $region );
		$lat = isset( $data['latitude'] ) ? WTA_Utils::sanitize_latitude( $data['latitude'] ) : null;
		$lng = isset( $data['longitude'] ) ? WTA_Utils::sanitize_longitude( $data['longitude'] ) : null;

		// Determine timezone status
		$is_complex = WTA_Timezone_Helper::is_complex_country( $country_code );
		$timezone = null;
		$timezone_status = 'pending';

		if ( ! $is_complex ) {
			// Use country's default timezone
			$timezone = WTA_Timezone_Helper::get_default_timezone_for_country( $country_code );
			if ( ! $timezone ) {
				// Try to get from parent country post
				$parent_timezone = get_post_meta( $parent_id, 'wta_timezone', true );
				if ( $parent_timezone ) {
					$timezone = $parent_timezone;
				}
			}
			if ( $timezone ) {
				$timezone_status = 'resolved';
			} else {
				$timezone_status = 'fallback';
			}
		} else {
			// Complex country - queue for TimeZoneDB lookup
			$timezone_status = 'pending';
		}

		// Create post with Danish name
		$post_data = array(
			'post_title'   => $danish_name,
			'post_name'    => $slug,
			'post_type'    => WTA_POST_TYPE,
			'post_status'  => 'draft',
			'post_parent'  => $parent_id,
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$error_msg = $post_id->get_error_message();
			WTA_Queue::update_status( $queue_id, 'error', $error_msg );
			WTA_Logger::error( 'Failed to create city post', array( 'error' => $error_msg ) );
			return $post_id;
		}

		// Add meta data
		update_post_meta( $post_id, 'wta_type', 'city' );
		update_post_meta( $post_id, 'wta_continent_code', $continent_code );
		update_post_meta( $post_id, 'wta_country_code', $country_code );
		update_post_meta( $post_id, 'wta_country_id', $data['country_id'] );
		update_post_meta( $post_id, 'wta_city_id', $data['id'] );
		update_post_meta( $post_id, 'wta_name_original', $english_name );
		update_post_meta( $post_id, 'wta_name_danish', $danish_name );
		update_post_meta( $post_id, 'wta_ai_status', 'pending' );
		
		if ( $lat !== null ) {
			update_post_meta( $post_id, 'wta_lat', $lat );
		}
		if ( $lng !== null ) {
			update_post_meta( $post_id, 'wta_lng', $lng );
		}
		if ( $timezone ) {
			update_post_meta( $post_id, 'wta_timezone', $timezone );
		}
		update_post_meta( $post_id, 'wta_timezone_status', $timezone_status );

		// Queue timezone lookup for complex countries
		if ( $is_complex && $lat !== null && $lng !== null ) {
			WTA_Queue::insert( 'timezone', $post_id, array(
				'post_id' => $post_id,
				'lat'     => $lat,
				'lng'     => $lng,
			) );
		}

		// Don't queue AI content yet - wait for timezone to be resolved
		if ( $timezone_status === 'resolved' || $timezone_status === 'fallback' ) {
			WTA_Queue::insert( 'ai_content', $post_id, array( 'post_id' => $post_id, 'type' => 'city' ) );
		}

		WTA_Queue::update_status( $queue_id, 'done' );
		WTA_Logger::info( 'Created city', array( 'id' => $data['id'], 'post_id' => $post_id ) );

		return true;
	}
}






