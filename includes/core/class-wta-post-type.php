<?php
/**
 * Register custom post type for locations.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

class WTA_Post_Type {

	/**
	 * Constructor.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		// Filter the permalink structure
		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 2 );
		
		// Handle URL parsing - intercept requests
		add_filter( 'request', array( $this, 'parse_location_request' ), 10, 1 );
	}

	/**
	 * Register the custom post type.
	 *
	 * Creates a hierarchical custom post type for locations (continents, countries, cities).
	 *
	 * @since    2.0.0
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Locations', 'Post type general name', WTA_TEXT_DOMAIN ),
			'singular_name'         => _x( 'Location', 'Post type singular name', WTA_TEXT_DOMAIN ),
			'menu_name'             => _x( 'World Time', 'Admin Menu text', WTA_TEXT_DOMAIN ),
			'name_admin_bar'        => _x( 'Location', 'Add New on Toolbar', WTA_TEXT_DOMAIN ),
			'add_new'               => __( 'Add New', WTA_TEXT_DOMAIN ),
			'add_new_item'          => __( 'Add New Location', WTA_TEXT_DOMAIN ),
			'new_item'              => __( 'New Location', WTA_TEXT_DOMAIN ),
			'edit_item'             => __( 'Edit Location', WTA_TEXT_DOMAIN ),
			'view_item'             => __( 'View Location', WTA_TEXT_DOMAIN ),
			'all_items'             => __( 'All Locations', WTA_TEXT_DOMAIN ),
			'search_items'          => __( 'Search Locations', WTA_TEXT_DOMAIN ),
			'parent_item_colon'     => __( 'Parent Location:', WTA_TEXT_DOMAIN ),
			'not_found'             => __( 'No locations found.', WTA_TEXT_DOMAIN ),
			'not_found_in_trash'    => __( 'No locations found in Trash.', WTA_TEXT_DOMAIN ),
			'featured_image'        => _x( 'Location Image', 'Overrides the "Featured Image" phrase', WTA_TEXT_DOMAIN ),
			'set_featured_image'    => _x( 'Set location image', 'Overrides the "Set featured image" phrase', WTA_TEXT_DOMAIN ),
			'remove_featured_image' => _x( 'Remove location image', 'Overrides the "Remove featured image" phrase', WTA_TEXT_DOMAIN ),
			'use_featured_image'    => _x( 'Use as location image', 'Overrides the "Use as featured image" phrase', WTA_TEXT_DOMAIN ),
			'archives'              => _x( 'Location archives', 'The post type archive label used in nav menus', WTA_TEXT_DOMAIN ),
			'insert_into_item'      => _x( 'Insert into location', 'Overrides the "Insert into post" phrase', WTA_TEXT_DOMAIN ),
			'uploaded_to_this_item' => _x( 'Uploaded to this location', 'Overrides the "Uploaded to this post" phrase', WTA_TEXT_DOMAIN ),
			'filter_items_list'     => _x( 'Filter locations list', 'Screen reader text for the filter links', WTA_TEXT_DOMAIN ),
			'items_list_navigation' => _x( 'Locations list navigation', 'Screen reader text for the pagination', WTA_TEXT_DOMAIN ),
			'items_list'            => _x( 'Locations list', 'Screen reader text for the items list', WTA_TEXT_DOMAIN ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false, // We add custom menu via admin class
			'query_var'          => true,
			'rewrite'            => array(
				'slug'         => 'location',
				'with_front'   => false,
				'hierarchical' => true,
			),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => true, // CRITICAL: Enables parent-child relationships
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-clock',
			'supports'           => array( 'title', 'editor', 'page-attributes' ),
			'show_in_rest'       => true, // Gutenberg support
		);

		register_post_type( WTA_POST_TYPE, $args );
	}

	/**
	 * Parse location URLs and handle clean URLs without /location/ prefix.
	 * 
	 * This intercepts requests that would be 404s and checks if they're location posts.
	 *
	 * @since    2.2.1
	 * @param    array $query_vars Query vars from WordPress.
	 * @return   array Modified query vars.
	 */
	public function parse_location_request( $query_vars ) {
		global $wpdb;
		
		// Skip if we already have a clear query (not a potential 404)
		if ( ! empty( $query_vars['p'] ) || ! empty( $query_vars['page_id'] ) || ! empty( $query_vars['attachment'] ) ) {
			return $query_vars;
		}

		// Get the request path
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$home_path = parse_url( home_url(), PHP_URL_PATH );
		
		// Remove home path from request
		if ( $home_path && $home_path !== '/' ) {
			$request_uri = str_replace( $home_path, '', $request_uri );
		}
		
		$request = trim( $request_uri, '/' );
		$request = strtok( $request, '?' ); // Remove query string
		
		if ( empty( $request ) ) {
			return $query_vars;
		}

		// Split path into parts
		$parts = explode( '/', $request );
		
		// Get the last part as slug
		// For /europa/ → 'europa'
		// For /europa/albanien/ → 'albanien' 
		// For /europa/albanien/tirana/ → 'tirana'
		
		$slug = sanitize_title( end( $parts ) );
		
		// Direct database query to find published location with this slug
		$post_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_name = %s 
			AND post_type = %s 
			AND post_status = 'publish' 
			LIMIT 1",
			$slug,
			WTA_POST_TYPE
		) );
		
		// If found, set query vars to load this post
		if ( $post_id ) {
			// Return ONLY these query vars (clear everything else)
			return array(
				WTA_POST_TYPE => $slug,
				'post_type'    => WTA_POST_TYPE,
				'name'         => $slug,
			);
		}
		
		// Not a location, return original query vars
		return $query_vars;
	}

	/**
	 * Filter the post type link to generate clean URLs (remove /location/ prefix).
	 *
	 * @since    2.0.0
	 * @param    string  $post_link The post's permalink.
	 * @param    WP_Post $post      The post object.
	 * @return   string             The filtered permalink.
	 */
	public function filter_post_type_link( $post_link, $post ) {
		if ( WTA_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return $post_link;
		}

		// Remove the /location/ prefix from the URL
		$post_link = str_replace( '/location/', '/', $post_link );

		return $post_link;
	}
}


