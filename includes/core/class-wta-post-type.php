<?php
/**
 * Register custom post type for locations.
 *
 * Uses dynamic continent-based rewrite rules for clean URLs.
 * Combines custom rewrite with defensive pre_get_posts for maximum compatibility.
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
	 * Generates clean URLs: /europa/danmark/ instead of /l/europa/danmark/
	 *
	 * @since    2.32.0
	 * @param    string  $post_link Post URL.
	 * @param    WP_Post $post      Post object.
	 * @param    bool    $leavename Whether to keep post name.
	 * @return   string             Modified URL.
	 */
	public function remove_post_type_slug( $post_link, $post, $leavename ) {
		// Only for our post type and published posts
		if ( ! in_array( $post->post_type, array( WTA_POST_TYPE ), true ) || 'publish' !== $post->post_status ) {
			return $post_link;
		}
		
		// Get the post type rewrite slug
		$slug = $this->get_post_type_slug( $post->post_type );
		
		if ( $slug ) {
			// Remove the slug from the URL
			$post_link = str_replace( "/{$slug}/", '/', $post_link );
		}
		
		return $post_link;
	}
	
	/**
	 * Defensive pre_get_posts to handle edge cases.
	 * 
	 * Only modifies queries for hierarchical paths starting with continents.
	 * Exits early for single-level paths like /om/, /blog/, etc.
	 *
	 * @since    2.32.0
	 * @param    WP_Query $query Query object.
	 */
	public function parse_request_for_locations( $query ) {
		// Only main query, not admin
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}
		
		// Look for hierarchical pagename structure
		$potential_path = null;
		
		if ( isset( $query->query['pagename'] ) && ! empty( $query->query['pagename'] ) ) {
			$potential_path = $query->query['pagename'];
		}
		
		// If we found a potential path, check if it's a location URL
		if ( $potential_path ) {
			$parts = explode( '/', trim( $potential_path, '/' ) );
			
			// CRITICAL: Single-level paths are normal pages - DON'T TOUCH
			// This prevents interference with /om/, /blog/, etc.
			if ( count( $parts ) === 1 ) {
				return;
			}
			
			// Only proceed for multi-level hierarchical paths
			$first_part = $parts[0];
			
			// Get known continent slugs
			$continent_slugs = $this->get_continent_slugs();
			
			// If first part is a continent, this might be our URL
			if ( in_array( $first_part, $continent_slugs, true ) ) {
				// Allow our post type to be queried alongside pages/posts
				$query->set( 'post_type', array_merge( array( 'post', 'page' ), array( WTA_POST_TYPE ) ) );
			}
		}
	}
	
	/**
	 * Redirect old URLs with slugs to clean URLs.
	 *
	 * @since    2.32.0
	 */
	public function redirect_old_urls() {
		// Only for singular location posts, not admin/preview
		if ( ! is_singular( WTA_POST_TYPE ) || is_admin() || is_preview() ) {
			return;
		}
		
		$slug = $this->get_post_type_slug( get_post_type() );
		$current_url = trailingslashit( $this->get_current_url() );
		
		// If URL contains the slug, redirect to clean version
		if ( $slug && str_contains( $current_url, "/{$slug}" ) ) {
			wp_safe_redirect( esc_url( str_replace( "/{$slug}", '', $current_url ) ), 301 );
			exit;
		}
	}
	
	/**
	 * Get the current URL.
	 *
	 * @since    2.32.0
	 * @return   string Current URL.
	 */
	private function get_current_url() {
		global $wp;
		return isset( $wp->request ) ? home_url( add_query_arg( array(), $wp->request ) ) : '';
	}
	
	/**
	 * Get post type slug from rewrite settings.
	 *
	 * @since    2.32.0
	 * @param    string $type Post type name.
	 * @return   string       Post type slug.
	 */
	private function get_post_type_slug( $type ) {
		$obj = get_post_type_object( $type );
		return $obj->rewrite['slug'] ?? $obj->name ?? $type;
	}
	
	/**
	 * Get known continent slugs for URL validation and rewrite rules.
	 * 
	 * Cached for 24 hours to avoid repeated DB queries.
	 *
	 * @since    2.32.0
	 * @return   array Array of continent slugs.
	 */
	private function get_continent_slugs() {
		// Check cache first (24 hour cache)
		$cache_key = 'wta_continent_slugs';
		$cached_slugs = get_transient( $cache_key );
		
		if ( false !== $cached_slugs && is_array( $cached_slugs ) ) {
			return $cached_slugs;
		}
		
		global $wpdb;
		
		// Query for all continent slugs directly from database
		$query = $wpdb->prepare(
			"SELECT p.post_name 
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND pm.meta_key = 'wta_type'
			AND pm.meta_value = 'continent'",
			WTA_POST_TYPE
		);
		
		$slugs = $wpdb->get_col( $query );
		
		// Fallback: If no continents in DB yet, use common translated continent slugs
		if ( empty( $slugs ) ) {
			$slugs = array(
				'europa', 'asien', 'afrika', 'nordamerika', 'sydamerika', 'oceanien', 'antarktis', // Danish
				'europe', 'asia', 'africa', 'north-america', 'south-america', 'oceania', 'antarctica', // English
			);
		}
		
		// Cache for 24 hours
		set_transient( $cache_key, $slugs, DAY_IN_SECONDS );
		
		return $slugs;
	}

	/**
	 * Register the custom post type.
	 *
	 * Creates a hierarchical custom post type for locations with dynamic rewrite rules.
	 *
	 * @since    2.0.0
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Locations', 'Post type general name', WTA_TEXT_DOMAIN ),
			'singular_name'         => _x( 'Location', 'Post type singular name', WTA_TEXT_DOMAIN ),
			'menu_name'             => _x( 'Locations', 'Admin Menu text', WTA_TEXT_DOMAIN ),
			'name_admin_bar'        => _x( 'Location', 'Add New on Toolbar', WTA_TEXT_DOMAIN ),
			'add_new'               => __( 'Add New', WTA_TEXT_DOMAIN ),
			'add_new_item'          => __( 'Add New Location', WTA_TEXT_DOMAIN ),
			'new_item'              => __( 'New Location', WTA_TEXT_DOMAIN ),
			'edit_item'             => __( 'Edit Location', WTA_TEXT_DOMAIN ),
			'view_item'             => __( 'View Location', WTA_TEXT_DOMAIN ),
			'all_items'             => __( 'All Locations', WTA_TEXT_DOMAIN ),
			'search_items'          => __( 'Search Locations', WTA_TEXT_DOMAIN ),
			'parent_item_colon'     => __( 'Parent Locations:', WTA_TEXT_DOMAIN ),
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
			'description'        => __( 'Locations (continents, countries, cities) with timezone information', WTA_TEXT_DOMAIN ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'         => 'l', // Dummy slug - removed by permalink filter
				'with_front'   => false,
				'hierarchical' => true,
			),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => true,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-location-alt',
			'supports'           => array( 'title', 'editor', 'author', 'revisions', 'page-attributes' ),
			'show_in_rest'       => true,
		);

		register_post_type( WTA_POST_TYPE, $args );
		
		// Add dynamic rewrite rules for continents
		$this->add_dynamic_rewrite_rules();
		
		// Clear continent slugs cache
		delete_transient( 'wta_continent_slugs' );
	}
	
	/**
	 * Add dynamic rewrite rules based on existing continent slugs.
	 * 
	 * Creates specific rules only for known continents to avoid conflicts.
	 *
	 * @since    2.32.0
	 */
	private function add_dynamic_rewrite_rules() {
		// Get continent slugs
		$continent_slugs = $this->get_continent_slugs();
		
		if ( empty( $continent_slugs ) ) {
			return;
		}
		
		// Escape slugs for regex
		$escaped_slugs = array_map( 'preg_quote', $continent_slugs );
		$continent_pattern = implode( '|', $escaped_slugs );
		
		// Add rewrite rules for hierarchical location URLs
		// Matches: /europa/danmark/copenhagen/
		add_rewrite_rule(
			'^(' . $continent_pattern . ')/([^/]+)/([^/]+)/?$',
			'index.php?wta_location=$matches[1]/$matches[2]/$matches[3]&post_type=' . WTA_POST_TYPE . '&name=$matches[3]',
			'top'
		);
		
		// Matches: /europa/danmark/
		add_rewrite_rule(
			'^(' . $continent_pattern . ')/([^/]+)/?$',
			'index.php?wta_location=$matches[1]/$matches[2]&post_type=' . WTA_POST_TYPE . '&name=$matches[2]',
			'top'
		);
		
		// Matches: /europa/
		add_rewrite_rule(
			'^(' . $continent_pattern . ')/?$',
			'index.php?wta_location=$matches[1]&post_type=' . WTA_POST_TYPE . '&name=$matches[1]',
			'top'
		);
		}
	}
