<?php
/**
 * Register custom post type for locations.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

/**
 * Custom post type registration.
 *
 * @since 1.0.0
 */
class WTA_Post_Type {

	/**
	 * Register the custom post type.
	 *
	 * @since 1.0.0
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Locations', 'Post Type General Name', WTA_TEXT_DOMAIN ),
			'singular_name'         => _x( 'Location', 'Post Type Singular Name', WTA_TEXT_DOMAIN ),
			'menu_name'             => __( 'Time Locations', WTA_TEXT_DOMAIN ),
			'name_admin_bar'        => __( 'Time Location', WTA_TEXT_DOMAIN ),
			'archives'              => __( 'Location Archives', WTA_TEXT_DOMAIN ),
			'attributes'            => __( 'Location Attributes', WTA_TEXT_DOMAIN ),
			'parent_item_colon'     => __( 'Parent Location:', WTA_TEXT_DOMAIN ),
			'all_items'             => __( 'All Locations', WTA_TEXT_DOMAIN ),
			'add_new_item'          => __( 'Add New Location', WTA_TEXT_DOMAIN ),
			'add_new'               => __( 'Add New', WTA_TEXT_DOMAIN ),
			'new_item'              => __( 'New Location', WTA_TEXT_DOMAIN ),
			'edit_item'             => __( 'Edit Location', WTA_TEXT_DOMAIN ),
			'update_item'           => __( 'Update Location', WTA_TEXT_DOMAIN ),
			'view_item'             => __( 'View Location', WTA_TEXT_DOMAIN ),
			'view_items'            => __( 'View Locations', WTA_TEXT_DOMAIN ),
			'search_items'          => __( 'Search Location', WTA_TEXT_DOMAIN ),
			'not_found'             => __( 'Not found', WTA_TEXT_DOMAIN ),
			'not_found_in_trash'    => __( 'Not found in Trash', WTA_TEXT_DOMAIN ),
			'featured_image'        => __( 'Featured Image', WTA_TEXT_DOMAIN ),
			'set_featured_image'    => __( 'Set featured image', WTA_TEXT_DOMAIN ),
			'remove_featured_image' => __( 'Remove featured image', WTA_TEXT_DOMAIN ),
			'use_featured_image'    => __( 'Use as featured image', WTA_TEXT_DOMAIN ),
			'insert_into_item'      => __( 'Insert into location', WTA_TEXT_DOMAIN ),
			'uploaded_to_this_item' => __( 'Uploaded to this location', WTA_TEXT_DOMAIN ),
			'items_list'            => __( 'Locations list', WTA_TEXT_DOMAIN ),
			'items_list_navigation' => __( 'Locations list navigation', WTA_TEXT_DOMAIN ),
			'filter_items_list'     => __( 'Filter locations list', WTA_TEXT_DOMAIN ),
		);

		$rewrite = array(
			'slug'       => '',  // Empty slug for hierarchical URLs
			'with_front' => false,
			'pages'      => true,
			'feeds'      => true,
			'hierarchical' => true,
		);

		$args = array(
			'label'               => __( 'Location', WTA_TEXT_DOMAIN ),
			'description'         => __( 'World time locations with hierarchical structure', WTA_TEXT_DOMAIN ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'hierarchical'        => true,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // We'll add it to our custom menu
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-clock',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'rewrite'             => $rewrite,
			'capability_type'     => 'page',
			'show_in_rest'        => true,
		);

		register_post_type( WTA_POST_TYPE, $args );

		// Add rewrite rules for hierarchical structure
		$this->add_hierarchical_rewrites();
	}

	/**
	 * Add custom rewrite rules for hierarchical URLs.
	 *
	 * @since 1.0.0
	 */
	private function add_hierarchical_rewrites() {
		// Pattern: /continent/
		add_rewrite_rule(
			'^([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[1]',
			'top'
		);

		// Pattern: /continent/country/
		add_rewrite_rule(
			'^([^/]+)/([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[2]',
			'top'
		);

		// Pattern: /continent/country/city/
		add_rewrite_rule(
			'^([^/]+)/([^/]+)/([^/]+)/?$',
			'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[3]',
			'top'
		);
	}
}




