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
	 * Remove dummy slug from permalinks to create clean URLs.
	 *
	 * WordPress generates: /l/europa/danmark/
	 * This filter removes '/l/' → /europa/danmark/
	 *
	 * @since    2.28.2
	 * @param    string       $post_link Post URL.
	 * @param    WP_Post|int  $post      Post object or ID.
	 * @return   string                  Modified URL.
	 */
	public function remove_post_type_slug( $post_link, $post ) {
		// Handle both WP_Post object and post ID
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		
		// Only for our post type
		if ( ! $post || WTA_POST_TYPE !== $post->post_type ) {
			return $post_link;
		}
		
		// Remove dummy slug '/l/' from URL
		// WordPress generates: /l/europa/ → We return: /europa/
		$post_link = str_replace( '/l/', '/', $post_link );
		
		return $post_link;
	}
	
	/**
	 * Check and ensure rewrite rules are properly set.
	 *
	 * @since    2.28.6
	 */
	public function ensure_rewrite_rules() {
		// Check if rewrite rules exist
		$rules = get_option( 'rewrite_rules' );
		
		// If rules don't exist OR don't contain our custom rules, flush them
		if ( ! $rules || ! $this->check_custom_rules_exist( $rules ) ) {
			flush_rewrite_rules( false );
			
			WTA_Logger::info( 'Rewrite rules flushed', array(
				'reason' => empty( $rules ) ? 'rules_missing' : 'custom_rules_missing',
			) );
		}
		
		// Clear object cache for permalinks
		wp_cache_delete( 'alloptions', 'options' );
		
		// Clear Yoast SEO permalink cache if it exists
		if ( function_exists( 'YoastSEO' ) ) {
			delete_transient( 'wpseo_sitemap_cache_validator' );
		}
	}
	
	/**
	 * Check if our custom rewrite rules exist in the rules array.
	 *
	 * @since    2.28.6
	 * @param    array $rules Rewrite rules array.
	 * @return   bool         True if custom rules exist.
	 */
	private function check_custom_rules_exist( $rules ) {
		// Get continent slugs to build expected patterns
		$continent_slugs = $this->get_continent_slugs();
		
		if ( empty( $continent_slugs ) ) {
			return false;
		}
		
		// Build the continent pattern (same as in register_post_type)
		$escaped_slugs = array_map( 'preg_quote', $continent_slugs );
		$continent_pattern = implode( '|', $escaped_slugs );
		
		// Check if any of our dynamic custom rules exist
		$custom_patterns = array(
			'^(' . $continent_pattern . ')/([^/]+)/([^/]+)/?$',
			'^(' . $continent_pattern . ')/([^/]+)/?$',
			'^(' . $continent_pattern . ')/?$',
		);
		
		foreach ( $custom_patterns as $pattern ) {
			if ( isset( $rules[ $pattern ] ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Clear permalink cache for a single post when it's saved.
	 *
	 * @since    2.28.4
	 * @param    int $post_id Post ID.
	 */
	public function clear_single_permalink_cache( $post_id ) {
		// Clear permalink cache for this specific post
		clean_post_cache( $post_id );
		
		// Clear object cache
		wp_cache_delete( $post_id, 'posts' );
		wp_cache_delete( $post_id, 'post_meta' );
	}
	
	/**
	 * Disable canonical redirect for our clean URLs.
	 *
	 * WordPress tries to "fix" our clean URLs by redirecting to /location/ prefix.
	 * This filter prevents that redirect.
	 *
	 * @since    2.28.5
	 * @param    string $redirect_url  Redirect URL.
	 * @param    string $requested_url Requested URL.
	 * @return   string|false          False to disable redirect.
	 */
	public function disable_canonical_redirect( $redirect_url, $requested_url ) {
		global $wp_query;
		
		// If this is a location post, disable canonical redirect
		if ( isset( $wp_query->query_vars['post_type'] ) && WTA_POST_TYPE === $wp_query->query_vars['post_type'] ) {
			return false;
		}
		
		// Check if requested URL looks like our clean URL pattern
		$path = parse_url( $requested_url, PHP_URL_PATH );
		$path = trim( $path, '/' );
		$parts = explode( '/', $path );
		
		// If it's 1-3 level path (continent, continent/country, continent/country/city)
		// and doesn't contain 'location', disable redirect
		if ( count( $parts ) >= 1 && count( $parts ) <= 3 && strpos( $path, 'location' ) === false ) {
			// Check if redirect URL contains 'location' - if so, cancel the redirect!
			if ( strpos( $redirect_url, '/location/' ) !== false || strpos( $redirect_url, '/' . WTA_POST_TYPE . '/' ) !== false ) {
				return false;
			}
		}
		
		return $redirect_url;
	}
	
	/**
	 * Disable WordPress guess redirect for our URLs.
	 *
	 * Prevents WordPress from "guessing" that our clean URLs are wrong.
	 *
	 * @since    2.28.5
	 * @param    bool $do_redirect Whether to redirect.
	 * @return   bool              False to disable.
	 */
	public function disable_guess_redirect( $do_redirect ) {
		// Check if current request matches our clean URL pattern
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$path = parse_url( $request_uri, PHP_URL_PATH );
		$path = trim( $path, '/' );
		$parts = explode( '/', $path );
		
		// If it's 1-3 level path without 'location', disable guess redirect
		if ( count( $parts ) >= 1 && count( $parts ) <= 3 && strpos( $path, 'location' ) === false ) {
			return false;
		}
		
		return $do_redirect;
	}
	
	/**
	 * Get continent slugs from database for dynamic rewrite rules.
	 * 
	 * Fetches all published continents and returns their slugs.
	 * Used to create specific rewrite rules that only match actual continents.
	 *
	 * @since    2.30.3
	 * @return   array Array of continent slugs.
	 */
	private function get_continent_slugs() {
		global $wpdb;
		
		// Query for all continent slugs directly from database
		// This is more efficient than get_posts() and works even during early init
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
		// This ensures rules work even before first import
		if ( empty( $slugs ) ) {
			$slugs = array(
				'europa', 'asien', 'afrika', 'nordamerika', 'sydamerika', 'oceanien', 'antarktis', // Danish
				'europe', 'asia', 'africa', 'north-america', 'south-america', 'oceania', 'antarctica', // English
			);
		}
		
		return $slugs;
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
			'slug'         => 'l',
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
		
		// Add dynamic rewrite rules that ONLY match actual continent slugs
		// This prevents hijacking WordPress pages while allowing clean location URLs
		$continent_slugs = $this->get_continent_slugs();
		
		if ( ! empty( $continent_slugs ) ) {
			// Escape each slug for regex and create pattern
			$escaped_slugs = array_map( 'preg_quote', $continent_slugs );
			$continent_pattern = implode( '|', $escaped_slugs );
			
			// 3-level: /europa/danmark/kobenhavn/
			add_rewrite_rule(
				'^(' . $continent_pattern . ')/([^/]+)/([^/]+)/?$',
				'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[3]',
				'top'
			);
			
			// 2-level: /europa/danmark/
			add_rewrite_rule(
				'^(' . $continent_pattern . ')/([^/]+)/?$',
				'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[2]',
				'top'
			);
			
			// 1-level: /europa/
			add_rewrite_rule(
				'^(' . $continent_pattern . ')/?$',
				'index.php?post_type=' . WTA_POST_TYPE . '&name=$matches[1]',
				'top'
			);
			
			WTA_Logger::debug( 'Dynamic rewrite rules registered', array(
				'continent_count' => count( $continent_slugs ),
				'continents' => implode( ', ', $continent_slugs ),
			) );
		}
	}
}


