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
		// Filters registered via loader in class-wta-core.php
	}
	
	/**
	 * Remove post type slug from permalinks.
	 *
	 * @since    2.28.2
	 * @param    string  $post_link Post URL.
	 * @param    WP_Post $post      Post object.
	 * @return   string             Modified URL.
	 */
	public function remove_post_type_slug( $post_link, $post ) {
		// Only for our post type
		if ( WTA_POST_TYPE !== $post->post_type ) {
			return $post_link;
		}
		
		// Remove the post type slug from URL - works for all post statuses
		// This handles: /wta_location/europa/ -> /europa/
		$post_link = str_replace( '/' . WTA_POST_TYPE . '/', '/', $post_link );
		
		return $post_link;
	}
	
	/**
	 * Parse clean URLs and set correct query var.
	 *
	 * @since    2.28.2
	 * @param    WP_Query $query Query object.
	 */
	public function parse_clean_urls( $query ) {
		// Only on main query, not in admin
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}
		
		// Check if this is a 404 that might be our location
		if ( ! isset( $query->query_vars['post_type'] ) && isset( $query->query_vars['pagename'] ) ) {
			$pagename = $query->query_vars['pagename'];
			
			// Try to find a location post with this slug
			$parts = explode( '/', trim( $pagename, '/' ) );
			$slug = end( $parts );
			
			// Check if a location post exists with this slug
			$posts = get_posts( array(
				'name'        => $slug,
				'post_type'   => WTA_POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 1,
			) );
			
			if ( ! empty( $posts ) ) {
				// Set the correct query vars
				$query->set( 'post_type', WTA_POST_TYPE );
				$query->set( 'name', $slug );
				$query->set( WTA_POST_TYPE, $pagename );
				unset( $query->query_vars['pagename'] );
			}
		}
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
			'slug'         => '',
			'with_front'   => false,
			'hierarchical' => true,
		),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => true, // CRITICAL: Enables parent-child relationships
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-clock',
			'supports'           => array( 'title', 'editor', 'author', 'page-attributes' ),
			'show_in_rest'       => true, // Gutenberg support
		);

		$result = register_post_type( WTA_POST_TYPE, $args );
		
		// Log if registration failed
		if ( is_wp_error( $result ) ) {
			WTA_Logger::error( 'Post type registration failed', array(
				'error' => $result->get_error_message(),
			) );
		} else {
			WTA_Logger::info( 'Post type registered successfully', array(
				'post_type' => WTA_POST_TYPE,
				'rest_enabled' => ! empty( $args['show_in_rest'] ),
			) );
		}
		
		// Add custom rewrite rules for hierarchical URLs without post type prefix
		add_rewrite_rule(
			'^([^/]+)/([^/]+)/([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[3]&' . WTA_POST_TYPE . '=$matches[1]/$matches[2]/$matches[3]',
			'top'
		);
		add_rewrite_rule(
			'^([^/]+)/([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[2]&' . WTA_POST_TYPE . '=$matches[1]/$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[1]&' . WTA_POST_TYPE . '=$matches[1]',
			'top'
		);
	}
}


