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
		// Add custom rewrite rules
		add_action( 'init', array( $this, 'add_rewrite_rules' ), 20 );
		
		// Filter the permalink structure
		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 2 );
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
			'rewrite'            => false, // We handle rewrite rules manually
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => true, // CRITICAL: Enables parent-child relationships
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-clock',
			'supports'           => array( 'title', 'editor', 'page-attributes' ),
			'show_in_rest'       => true, // Gutenberg support
		);

		register_post_type( WTA_POST_TYPE, $args );
	}

	/**
	 * Add custom rewrite rules for hierarchical URLs without post type slug.
	 *
	 * @since    2.1.8
	 */
	public function add_rewrite_rules() {
		// Match: /continent/country/city/
		add_rewrite_rule(
			'^([^/]+)/([^/]+)/([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[3]&wta_path=$matches[1]/$matches[2]/$matches[3]',
			'top'
		);

		// Match: /continent/country/
		add_rewrite_rule(
			'^([^/]+)/([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[2]&wta_path=$matches[1]/$matches[2]',
			'top'
		);

		// Match: /continent/
		add_rewrite_rule(
			'^([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[1]&wta_path=$matches[1]',
			'top'
		);
	}

	/**
	 * Filter the post type link to generate clean URLs.
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

		// Build hierarchical URL
		$slug_parts = array();
		$current_post = $post;

		// Traverse up the hierarchy
		while ( $current_post ) {
			array_unshift( $slug_parts, $current_post->post_name );
			
			if ( $current_post->post_parent ) {
				$current_post = get_post( $current_post->post_parent );
			} else {
				$current_post = null;
			}
		}

		// Generate URL
		$post_link = home_url( '/' . implode( '/', $slug_parts ) . '/' );

		return $post_link;
	}
}


