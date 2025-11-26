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
		
		// Handle URL parsing
		add_action( 'pre_get_posts', array( $this, 'parse_location_url' ), 1 );
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
	 * Parse location URLs and handle 404s.
	 * 
	 * This allows clean URLs without /location/ prefix while not breaking other pages.
	 *
	 * @since    2.1.10
	 * @param    WP_Query $query Main query.
	 */
	public function parse_location_url( $query ) {
		// Only on main query, frontend, and when it's a 404
		if ( ! $query->is_main_query() || is_admin() || ! $query->is_404() ) {
			return;
		}

		// Get the request path
		$request = trim( $_SERVER['REQUEST_URI'], '/' );
		$request = strtok( $request, '?' ); // Remove query string
		$request = trim( $request, '/' );
		
		if ( empty( $request ) ) {
			return;
		}

		// Split path into parts
		$parts = explode( '/', $request );
		
		// Try to find a location post by the path
		$slug = end( $parts );
		
		// Look for a published location with this slug
		$args = array(
			'name'        => sanitize_title( $slug ),
			'post_type'   => WTA_POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => 1,
		);
		
		$posts = get_posts( $args );
		
		// If we found a location post, redirect to its proper URL
		if ( ! empty( $posts ) ) {
			$post = $posts[0];
			
			// Set query to show this post
			$query->set( 'post_type', WTA_POST_TYPE );
			$query->set( 'name', $slug );
			$query->set( 'p', $post->ID );
			$query->is_404 = false;
			$query->is_single = true;
			$query->is_singular = true;
		}
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


