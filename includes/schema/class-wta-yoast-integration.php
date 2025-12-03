<?php
/**
 * Yoast SEO Schema Integration
 *
 * Integrates WTA location data into Yoast's Schema @graph
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/schema
 */

class WTA_Yoast_Integration {

	/**
	 * Initialize integration
	 *
	 * @since    2.23.1
	 */
	public static function init() {
		// Only if Yoast SEO is active
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		add_filter( 'wpseo_schema_graph_pieces', array( __CLASS__, 'add_place_schema' ), 11, 2 );
	}

	/**
	 * Add Place schema to Yoast's @graph
	 *
	 * @since    2.23.1
	 * @param    array                  $pieces  Schema pieces
	 * @param    WPSEO_Schema_Context   $context Schema context
	 * @return   array                           Modified schema pieces
	 */
	public static function add_place_schema( $pieces, $context ) {
		// Only for location posts
		if ( ! is_singular( WTA_POST_TYPE ) ) {
			return $pieces;
		}

		$post_id = get_the_ID();
		$lat = get_post_meta( $post_id, 'wta_latitude', true );
		$lng = get_post_meta( $post_id, 'wta_longitude', true );
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );
		$country_code = get_post_meta( $post_id, 'wta_country_code', true );

		WTA_Logger::debug( 'Yoast Place schema filter called', array(
			'post_id'      => $post_id,
			'has_lat'      => ! empty( $lat ),
			'has_lng'      => ! empty( $lng ),
			'timezone'     => $timezone,
			'country_code' => $country_code,
		) );

		// Only add if we have GPS data
		if ( empty( $lat ) || empty( $lng ) ) {
			WTA_Logger::warning( 'Place schema skipped - missing GPS data', array(
				'post_id' => $post_id,
			) );
			return $pieces;
		}

		// Create Place piece
		$pieces[] = new WTA_Schema_Place_Piece( $context, array(
			'lat'          => floatval( $lat ),
			'lng'          => floatval( $lng ),
			'timezone'     => $timezone,
			'country_code' => $country_code,
		) );

		WTA_Logger::info( 'Place schema added to Yoast @graph', array(
			'post_id'  => $post_id,
			'location' => get_the_title(),
		) );

		return $pieces;
	}
}

/**
 * Place Schema Piece for Yoast @graph
 *
 * @since    2.23.1
 */
class WTA_Schema_Place_Piece extends WPSEO_Graph_Piece {

	/**
	 * Location data
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor
	 *
	 * @since    2.23.1
	 * @param    WPSEO_Schema_Context $context Schema context
	 * @param    array                $data    Location data
	 */
	public function __construct( $context, $data ) {
		parent::__construct( $context );
		$this->data = $data;
	}

	/**
	 * Is this piece needed?
	 *
	 * @since    2.23.1
	 * @return   bool
	 */
	public function is_needed() {
		return true;
	}

	/**
	 * Generate Place schema
	 *
	 * @since    2.23.1
	 * @return   array Schema data
	 */
	public function generate() {
		$schema = array(
			'@type' => 'Place',
			'@id'   => $this->context->canonical . '#place',
			'name'  => get_the_title(),
			'geo'   => array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $this->data['lat'],
				'longitude' => $this->data['lng'],
			),
		);

		// Add timezone if available and not multiple
		if ( ! empty( $this->data['timezone'] ) && 'multiple' !== $this->data['timezone'] ) {
			$schema['timeZone'] = $this->data['timezone'];
		}

		// Add address with country if available
		if ( ! empty( $this->data['country_code'] ) ) {
			$schema['address'] = array(
				'@type'          => 'PostalAddress',
				'addressCountry' => strtoupper( $this->data['country_code'] ),
			);
		}

		return $schema;
	}
}

