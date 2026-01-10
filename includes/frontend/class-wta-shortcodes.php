<?php
/**
 * Shortcodes for frontend.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

class WTA_Shortcodes {

	/**
	 * Cached language templates.
	 *
	 * @since    3.2.1
	 * @var      array|null
	 */
	private static $templates_cache = null;

	/**
	 * Get language template string.
	 *
	 * @since    3.2.1
	 * @param    string $key Template key
	 * @return   string Template string
	 */
	private static function get_template( $key ) {
		if ( self::$templates_cache === null ) {
			$templates = get_option( 'wta_templates', array() );
			self::$templates_cache = is_array( $templates ) ? $templates : array();
		}
		return isset( self::$templates_cache[ $key ] ) ? self::$templates_cache[ $key ] : '';
	}

	/**
	 * Register shortcodes.
	 *
	 * @since    2.9.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'wta_child_locations', array( $this, 'child_locations_shortcode' ) );
		add_shortcode( 'wta_city_time', array( $this, 'city_time_shortcode' ) );
		add_shortcode( 'wta_major_cities', array( $this, 'major_cities_shortcode' ) );
		add_shortcode( 'wta_nearby_cities', array( $this, 'nearby_cities_shortcode' ) );
		add_shortcode( 'wta_nearby_countries', array( $this, 'nearby_countries_shortcode' ) );
		add_shortcode( 'wta_regional_centres', array( $this, 'regional_centres_shortcode' ) ); // v2.35.63
		add_shortcode( 'wta_global_time_comparison', array( $this, 'global_time_comparison_shortcode' ) );
		add_shortcode( 'wta_continents_overview', array( $this, 'continents_overview_shortcode' ) );
		add_shortcode( 'wta_recent_cities', array( $this, 'recent_cities_shortcode' ) ); // v2.35.1
		add_shortcode( 'wta_queue_status', array( $this, 'queue_status_shortcode' ) ); // v2.35.1
	}

	/**
	 * Shortcode to display major cities with live clocks.
	 *
	 * Usage: [wta_major_cities count="12"]
	 *
	 * @since    2.9.7
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function major_cities_shortcode( $atts ) {
		// Get current post ID and type first to set appropriate default
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '<!-- Major Cities: No post ID -->';
		}
		
		// Get location type
		$type = get_post_meta( $post_id, 'wta_type', true );
		
	if ( empty( $type ) || ! in_array( $type, array( 'continent', 'country' ) ) ) {
		return '<!-- Major Cities: Not a continent or country (type: ' . esc_html( $type ) . ') -->';
	}
	
	// v3.0.59: Auto-populate country GPS and timezone on first page view (same as nearby_countries)
	if ( 'country' === $type ) {
		$current_lat = get_post_meta( $post_id, 'wta_latitude', true );
		$current_lon = get_post_meta( $post_id, 'wta_longitude', true );
		
		// Calculate GPS and timezone if missing
		if ( empty( $current_lat ) || empty( $current_lon ) ) {
			$calculated_gps = $this->calculate_country_center( $post_id );
			if ( ! empty( $calculated_gps ) ) {
				// Cache GPS (geographic center)
				update_post_meta( $post_id, 'wta_latitude', $calculated_gps['lat'] );
				update_post_meta( $post_id, 'wta_longitude', $calculated_gps['lon'] );
				
				// Also cache timezone from largest city (for live-time display)
				$largest_city_tz = $this->get_largest_city_timezone( $post_id );
				if ( ! empty( $largest_city_tz ) ) {
					update_post_meta( $post_id, 'wta_timezone_primary', $largest_city_tz );
				}
			}
		}
	}
	
	// v3.0.28: Check backend settings FIRST, then fallback to hardcoded defaults
		// Continents = broader scope → default 30 cities
		// Countries = focused scope → default 50 cities (more detail)
		if ( 'continent' === $type ) {
			$backend_setting = get_option( 'wta_major_cities_count_continent', 0 );
			$default_count = $backend_setting > 0 ? $backend_setting : 30;
		} else {
			$backend_setting = get_option( 'wta_major_cities_count_country', 0 );
			$default_count = $backend_setting > 0 ? $backend_setting : 50;
		}
		
		$atts = shortcode_atts( array(
			'count' => $default_count, // Now respects backend settings!
		), $atts );
		
		// Check cache first (24 hours)
		$cache_key = 'wta_major_cities_' . $post_id . '_' . intval( $atts['count'] );
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		// Get major cities using optimized SQL query
		global $wpdb;
		
		if ( $type === 'continent' ) {
			// For continent: Use optimized SQL to get top cities across all countries
			$city_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id AND pm_pop.meta_key = 'wta_population'
				INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
				INNER JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND pm_type.meta_value = 'city'
				AND parent.post_parent = %d
				ORDER BY CAST(pm_pop.meta_value AS UNSIGNED) DESC
				LIMIT %d
			", WTA_POST_TYPE, $post_id, intval( $atts['count'] ) ) );
			
			if ( empty( $city_ids ) ) {
				return '<!-- Major Cities: No cities found for continent (ID: ' . $post_id . ') -->';
			}
		} else {
			// For country: Use optimized SQL to get top cities
			$city_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id AND pm_pop.meta_key = 'wta_population'
				INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
				WHERE p.post_type = %s
				AND p.post_parent = %d
				AND p.post_status = 'publish'
				AND pm_type.meta_value = 'city'
				ORDER BY CAST(pm_pop.meta_value AS UNSIGNED) DESC
				LIMIT %d
			", WTA_POST_TYPE, $post_id, intval( $atts['count'] ) ) );
			
			if ( empty( $city_ids ) ) {
				return '<!-- Major Cities: No cities found for country (ID: ' . $post_id . ') -->';
			}
		}
		
		// Batch fetch posts
		$major_cities = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'post__in'    => $city_ids,
			'orderby'     => 'post__in',
			'post_status' => 'publish',
			'nopaging'    => true,  // CRITICAL: Fetch ALL posts matching the IDs
		) );
		
		// Batch prefetch meta
		update_meta_cache( 'post', $city_ids );
		
		// Build output with anchor ID for navigation
		$output = '<div id="major-cities" class="wta-city-times-grid">' . "\n";
		
		foreach ( $major_cities as $city ) {
			$city_name = get_post_field( 'post_title', $city->ID );
			$timezone = get_post_meta( $city->ID, 'wta_timezone', true );
			
			if ( empty( $timezone ) ) {
				continue;
			}
			
			// Get base country timezone
			$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
			$base_country = get_option( 'wta_base_country_name', 'Danmark' );
			
			try {
				$city_tz = new DateTimeZone( $timezone );
				$base_tz = new DateTimeZone( $base_timezone );
				$now = new DateTime( 'now', $city_tz );
				$base_time = new DateTime( 'now', $base_tz );
				
				$offset = $city_tz->getOffset( $now ) - $base_tz->getOffset( $base_time );
				$hours_diff = $offset / 3600;
				
			// Format hours: show decimal only if not a whole number
			$hours_abs = abs( $hours_diff );
			$hours_formatted = ( $hours_abs == floor( $hours_abs ) ) 
				? intval( $hours_abs ) 
				: number_format( $hours_abs, 1, ',', '' );
			
			$diff_text = '';
			if ( $hours_diff > 0 ) {
				$diff_text = sprintf( self::get_template( 'hours_ahead' ) ?: '%s timer foran %s', $hours_formatted, $base_country );
			} elseif ( $hours_diff < 0 ) {
				$diff_text = sprintf( self::get_template( 'hours_behind' ) ?: '%s timer bagud for %s', $hours_formatted, $base_country );
			} else {
				$diff_text = sprintf( self::get_template( 'same_time_as' ) ?: 'Samme tid som %s', $base_country );
			}
				
			// Initial time with seconds
			$initial_time = $now->format( 'H:i:s' );
			
			// Get city URL for link
			$city_url = get_permalink( $city->ID );
			
			// Build clock HTML with linked city name
			$output .= sprintf(
				'<div class="wta-live-city-clock" data-timezone="%s" data-base-offset="%.1f">
					<div class="wta-city-name"><a href="%s">%s</a></div>
					<div class="wta-time">%s</div>
					<div class="wta-time-diff">%s</div>
				</div>' . "\n",
				esc_attr( $timezone ),
				$hours_diff,
				esc_url( $city_url ),
				esc_html( $city_name ),
				esc_html( $initial_time ),
				esc_html( $diff_text )
			);
				
			} catch ( Exception $e ) {
				continue;
			}
		}
		
		$output .= '</div>';
		
		// Add ItemList schema
		$parent_name = get_post_field( 'post_title', $post_id );
		$schema_name = sprintf( self::get_template( 'largest_cities_in' ) ?: 'Største byer i %s', $parent_name );
		$schema_description = sprintf( self::get_template( 'see_time_largest_cities' ) ?: 'Se hvad klokken er i de største byer i %s', $parent_name );
		$output .= $this->generate_item_list_schema( $major_cities, $schema_name, $schema_description );
		
		// Cache for 24 hours
		set_transient( $cache_key, $output, DAY_IN_SECONDS );
		
		return $output;
	}

	/**
	 * Shortcode to display live clock for a city.
	 *
	 * Usage: [wta_city_time city="London"]
	 *
	 * @since    2.9.5
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function city_time_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'city' => '',
		), $atts );
		
		if ( empty( $atts['city'] ) ) {
			return '';
		}
		
		// Find city post by name
		$city_posts = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'title'          => $atts['city'],
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		) );
		
		if ( empty( $city_posts ) ) {
			return '';
		}
		
		$city_post = $city_posts[0];
		$timezone = get_post_meta( $city_post->ID, 'wta_timezone', true );
		
		if ( empty( $timezone ) ) {
			return '';
		}
		
		// Get base country timezone
		$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
		$base_country = get_option( 'wta_base_country_name', 'Danmark' );
		
		// Calculate time difference
		try {
			$city_tz = new DateTimeZone( $timezone );
			$base_tz = new DateTimeZone( $base_timezone );
			$now = new DateTime( 'now', $city_tz );
			$base_time = new DateTime( 'now', $base_tz );
			
			$offset = $city_tz->getOffset( $now ) - $base_tz->getOffset( $base_time );
			$hours_diff = $offset / 3600;
			
			// Format hours: show decimal only if not a whole number
			$hours_abs = abs( $hours_diff );
			$hours_formatted = ( $hours_abs == floor( $hours_abs ) ) 
				? intval( $hours_abs ) 
				: number_format( $hours_abs, 1, ',', '' );
			
			$diff_text = '';
			if ( $hours_diff > 0 ) {
				$diff_text = sprintf( self::get_template( 'hours_ahead' ) ?: '%s timer foran %s', $hours_formatted, $base_country );
			} elseif ( $hours_diff < 0 ) {
				$diff_text = sprintf( self::get_template( 'hours_behind' ) ?: '%s timer bagud for %s', $hours_formatted, $base_country );
			} else {
				$diff_text = sprintf( self::get_template( 'same_time_as' ) ?: 'Samme tid som %s', $base_country );
			}
			
			// Initial time with seconds
			$initial_time = $now->format( 'H:i:s' );
			
			// Build live clock HTML
			$output = sprintf(
				'<div class="wta-live-city-clock" data-timezone="%s" data-base-offset="%.1f">
					<div class="wta-city-name">%s</div>
					<div class="wta-time">%s</div>
					<div class="wta-time-diff">%s</div>
				</div>',
				esc_attr( $timezone ),
				$hours_diff,
				esc_html( $atts['city'] ),
				esc_html( $initial_time ),
				esc_html( $diff_text )
			);
			
			return $output;
			
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Shortcode to display child locations (countries under continent, cities under country).
	 *
	 * Usage: [wta_child_locations]
	 *
	 * @since    2.9.0
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function child_locations_shortcode( $atts ) {
		global $post;
		
		// Only works on location posts
		if ( ! is_singular( WTA_POST_TYPE ) ) {
			return '';
		}
		
		// Get parent type to determine appropriate defaults
		$parent_type = get_post_meta( $post->ID, 'wta_type', true );
		
		// Dynamic defaults based on location type
		if ( 'continent' === $parent_type ) {
			// Continents → Countries (no population, alphabetical, show all)
			$default_orderby = 'title';
			$default_meta_key = '';
			$default_order = 'ASC';
			$default_limit = -1;  // Show ALL countries
		} else {
			// Countries → Cities (sort by population, limit for performance)
			$default_orderby = 'meta_value_num';
			$default_meta_key = 'wta_population';
			$default_order = 'DESC';
			// v3.0.23: Configurable limit from settings (fallback: 300)
			$default_limit = get_option( 'wta_child_locations_limit', 300 );
		}
		
		$atts = shortcode_atts( array(
			'orderby'  => $default_orderby,
			'meta_key' => $default_meta_key,
			'order'    => $default_order,
			'limit'    => $default_limit,
		), $atts );
		
		// Check cache first (24 hours)
		$cache_key = 'wta_child_locations_' . $post->ID . '_' . md5( serialize( $atts ) );
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		// Get child locations
		$query_args = array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $post->ID,
			'orderby'        => $atts['orderby'],
			'order'          => $atts['order'],
			'post_status'    => 'publish',
			'fields'         => 'ids',  // Only get IDs for better performance
		);
		
		// For continents: show ALL (no pagination)
		// For countries: limit to specific number
		// CRITICAL: Use 'nopaging' instead of posts_per_page=-1 for proper behavior with custom orderby
		if ( $atts['limit'] === -1 ) {
			$query_args['nopaging'] = true;
		} else {
			$query_args['posts_per_page'] = (int) $atts['limit'];
		}
		
		// Add meta_key if sorting by population
		if ( 'meta_value_num' === $atts['orderby'] && ! empty( $atts['meta_key'] ) ) {
			$query_args['meta_key'] = $atts['meta_key'];
		}
		
		$children_ids = get_posts( $query_args );
		
		if ( empty( $children_ids ) ) {
			return '';
		}
		
		// Batch prefetch post data
		$children = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'post__in'    => $children_ids,
			'orderby'     => 'post__in',
			'post_status' => 'publish',
			'nopaging'    => true,  // CRITICAL: Fetch ALL posts matching the IDs
		) );
		
		if ( empty( $children ) ) {
			return '';
		}
		
		// Get location type and names
		$parent_type = get_post_meta( $post->ID, 'wta_type', true );
		$parent_name = get_post_field( 'post_title', $post->ID ); // Simple title, not SEO H1
		
		// Determine child type
		$child_type = '';
		$child_type_plural = '';
		
		if ( 'continent' === $parent_type ) {
			$child_type = 'land';
			$child_type_plural = 'lande';
		} elseif ( 'country' === $parent_type ) {
			$child_type = 'sted';
			$child_type_plural = 'steder';
		} else {
			$child_type = 'lokation';
			$child_type_plural = 'lokationer';
		}
		
		$count = count( $children );
		
		// Count unique timezones (estimate based on children)
		$timezone_count = 'flere';
		if ( 'continent' === $parent_type ) {
			// Rough estimate: most continents have multiple timezones
			$timezone_estimates = array(
				'Europa'      => '4',
				'Asien'       => '11',
				'Afrika'      => '6',
				'Nordamerika' => '6',
				'Sydamerika'  => '4',
				'Oceanien'    => '3',
			);
			$timezone_count = isset( $timezone_estimates[ $parent_name ] ) ? $timezone_estimates[ $parent_name ] : 'flere';
		}
		
		// Build output
		$output = '';
		
		// Heading
		if ( 'continent' === $parent_type ) {
			$heading_template = self::get_template( 'overview_of' ) ?: 'Oversigt over %s i %s';
			$output .= '<h2>' . esc_html( sprintf( $heading_template, $child_type_plural, $parent_name ) ) . '</h2>' . "\n";
		} elseif ( 'country' === $parent_type ) {
			$heading_template = self::get_template( 'overview_of' ) ?: 'Oversigt over %s i %s';
			$output .= '<h2>' . esc_html( sprintf( $heading_template, $child_type_plural, $parent_name ) ) . '</h2>' . "\n";
		} else {
			$output .= '<h2>' . ucfirst( $child_type_plural ) . ' i ' . esc_html( $parent_name ) . '</h2>' . "\n";
		}
		
	// Intro text
	if ( 'continent' === $parent_type ) {
		$intro_template = self::get_template( 'child_locations_continent_intro' ) ?: 'I %s er der %d %s og %s tidszoner. Klik på et land for at se aktuel tid og tidszoner.';
		$intro = sprintf(
			$intro_template,
			$parent_name,
			$count,
			$child_type_plural,
			$timezone_count
		);
	} elseif ( 'country' === $parent_type ) {
		$intro_template = self::get_template( 'child_locations_country_intro' ) ?: 'I %s kan du se hvad klokken er i følgende %d %s:';
		$intro = sprintf(
			$intro_template,
			$parent_name,
			$count,
			$child_type_plural
		);
	} else {
		$intro_template = self::get_template( 'child_locations_default_intro' ) ?: '%s har %d %s. Klik for at se mere information.';
		$intro = sprintf(
			$intro_template,
			$parent_name,
			$count,
			$child_type_plural
		);
	}
		
		$output .= '<p>' . esc_html( $intro ) . '</p>' . "\n";
		
		// Locations grid (with very specific class to prevent theme override)
		$output .= '<div id="child-locations" class="wta-plugin-locations-grid wta-child-list">' . "\n";
		$output .= '<ul class="wta-grid-list">' . "\n";
		
	foreach ( $children as $child ) {
		// Get simple title (not SEO H1) - use post_title directly
		$simple_title = get_post_field( 'post_title', $child->ID );
		
		// Get ISO code for flag icon (only for countries)
		$child_type = get_post_meta( $child->ID, 'wta_type', true );
		$iso_code = '';
		if ( 'country' === $child_type ) {
			$iso_code = get_post_meta( $child->ID, 'wta_country_code', true );
		}
		
		$output .= '<li class="wta-grid-item"><a class="wta-location-link" href="' . esc_url( get_permalink( $child->ID ) ) . '">';
		
		// Add flag icon for countries
		if ( ! empty( $iso_code ) ) {
			$output .= '<span class="fi fi-' . esc_attr( strtolower( $iso_code ) ) . '"></span> ';
		}
		
		$output .= esc_html( $simple_title );
		$output .= '</a></li>' . "\n";
	}
		
		$output .= '</ul>' . "\n";
		$output .= '</div>' . "\n";
		
		// Add ItemList schema
		$schema_name = '';
		$schema_description = '';
		
		if ( 'continent' === $parent_type ) {
			$schema_name = sprintf( self::get_template( 'countries_in' ) ?: 'Lande i %s', $parent_name );
			$schema_description = sprintf( self::get_template( 'see_time_countries' ) ?: 'Se hvad klokken er i forskellige lande i %s', $parent_name );
		} elseif ( 'country' === $parent_type ) {
			$schema_name = sprintf( self::get_template( 'places_in' ) ?: 'Steder i %s', $parent_name );
			$schema_description = sprintf( self::get_template( 'see_time_places' ) ?: 'Se hvad klokken er i forskellige steder i %s', $parent_name );
		}
		
		$output .= $this->generate_item_list_schema( $children, $schema_name, $schema_description );
		
		// Cache for 24 hours
		set_transient( $cache_key, $output, DAY_IN_SECONDS );
		
		return $output;
	}

	/**
	 * Shortcode to display nearby cities with distance.
	 *
	 * Usage: [wta_nearby_cities count="5"]
	 *
	 * @since    2.20.0
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function nearby_cities_shortcode( $atts ) {
		// v3.0.23: Configurable count from settings (fallback: 120)
		$default_count = get_option( 'wta_nearby_cities_count', 120 );
		
		$atts = shortcode_atts( array(
			'count' => $default_count,
		), $atts );
		
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		
		$type = get_post_meta( $post_id, 'wta_type', true );
		if ( 'city' !== $type ) {
			return '';
		}
		
		$latitude = get_post_meta( $post_id, 'wta_latitude', true );
		$longitude = get_post_meta( $post_id, 'wta_longitude', true );
		
		if ( empty( $latitude ) || empty( $longitude ) ) {
			return '';
		}
		
		// Get parent country
		$parent_country_id = wp_get_post_parent_id( $post_id );
		if ( ! $parent_country_id ) {
			return '';
		}
		
		// Cache nearby cities list (24 hours)
		$cache_key = 'wta_nearby_cities_' . $post_id . '_v2';  // v2 = dynamic density-based
		$cached_data = get_transient( $cache_key );
		
		if ( false !== $cached_data && is_array( $cached_data ) ) {
			return $cached_data['output'];
		}
		
		// Phase 1: Find cities within 500km (no limit)
		$nearby_cities_500km = $this->find_nearby_cities( $post_id, $parent_country_id, $latitude, $longitude, 9999, 500 );
		$count_500km = count( $nearby_cities_500km );
		
		// Phase 2: Determine optimal radius and count based on density
		$radius = 500;
		$nearby_cities = $nearby_cities_500km;
		
		if ( $count_500km < 60 ) {
			// Sparse area: expand to 1000km to find more neighbors
			$nearby_cities = $this->find_nearby_cities( $post_id, $parent_country_id, $latitude, $longitude, 9999, 1000 );
			$radius = 1000;
		}
		
		$found_count = count( $nearby_cities );
		
		// Phase 3: Dynamic limit based on actual density
		if ( $found_count < 60 ) {
			// Very sparse: show all available
			$limit = $found_count;
		} elseif ( $found_count < 120 ) {
			// Normal density: show what we have
			$limit = $found_count;
		} elseif ( $found_count < 300 ) {
			// Dense area: show 120
			$limit = 120;
		} else {
			// Very dense: show 150 (cap to avoid spam)
			$limit = 150;
		}
		
		// Apply the dynamic limit
		$nearby_cities = array_slice( $nearby_cities, 0, $limit );
		
		if ( empty( $nearby_cities ) ) {
			return '<p class="wta-no-nearby">Der er ingen andre byer i databasen endnu.</p>';
		}
		
		// Batch prefetch post meta for all cities (1 query instead of N)
		$city_ids = wp_list_pluck( $nearby_cities, 'id' );
		update_meta_cache( 'post', $city_ids );
		
		// Build output
		$output = '<div class="wta-nearby-list wta-nearby-cities-list">' . "\n";
		
		foreach ( $nearby_cities as $city ) {
			$city_name = get_post_field( 'post_title', $city['id'] );
			$city_link = get_permalink( $city['id'] );
			$distance = round( $city['distance'] );
			$population = get_post_meta( $city['id'], 'wta_population', true );
			
			// Build description
			$description = '';
			if ( $population && $population > 100000 ) {
				$description = number_format( $population, 0, ',', '.' ) . ' ' . ( self::get_template( 'inhabitants' ) ?: 'indbyggere' );
			} elseif ( $distance < 50 ) {
				$description = self::get_template( 'close_by' ) ?: 'Tæt på';
			} else {
				$description = self::get_template( 'city_in_region' ) ?: 'By i regionen';
			}
			
			$output .= '<div class="wta-nearby-item">' . "\n";
			$output .= '  <div class="wta-nearby-icon">🏙️</div>' . "\n";
			$output .= '  <div class="wta-nearby-content">' . "\n";
			$output .= '    <a href="' . esc_url( $city_link ) . '" class="wta-nearby-title">' . esc_html( $city_name ) . '</a>' . "\n";
			$output .= '    <div class="wta-nearby-meta">' . esc_html( $description ) . ' • ' . esc_html( $distance ) . ' km</div>' . "\n";
			$output .= '  </div>' . "\n";
			$output .= '</div>' . "\n";
		}
		
		$output .= '</div>' . "\n";
		
		// Add ItemList schema
		$city_name = get_post_field( 'post_title', $post_id );
		$schema_name = sprintf( self::get_template( 'cities_near' ) ?: 'Byer i nærheden af %s', $city_name );
		
		// Batch fetch city posts for schema (reuse query results)
		$nearby_posts = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'post__in'    => $city_ids,
			'orderby'     => 'post__in',
			'post_status' => 'publish',
			'nopaging'    => true,  // CRITICAL: Fetch ALL posts matching the IDs
		) );
		
		$output .= $this->generate_item_list_schema( $nearby_posts, $schema_name );
		
		// Cache the output (24 hours)
		set_transient( $cache_key, array( 'output' => $output ), DAY_IN_SECONDS );
		
		return $output;
	}

	/**
	 * Shortcode to display nearby countries.
	 *
	 * Usage: [wta_nearby_countries count="5"]
	 *
	 * @since    2.20.0
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function nearby_countries_shortcode( $atts ) {
		// v3.0.23: Configurable count from settings (fallback: 24)
		$default_count = get_option( 'wta_nearby_countries_count', 24 );
		
		$atts = shortcode_atts( array(
			'count' => $default_count,
		), $atts );
		
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		
		$type = get_post_meta( $post_id, 'wta_type', true );
		if ( 'city' !== $type ) {
			return '';
		}
		
		// Get parent country - robust lookup (handles both post ID and country code)
		$country_id_or_code = get_post_meta( $post_id, 'wta_country_id', true );
		
		// Try as post_parent first
		$parent_country_id = wp_get_post_parent_id( $post_id );
		
		// Fallback: Lookup by country code if not found
		if ( ! $parent_country_id && ! empty( $country_id_or_code ) ) {
			global $wpdb;
			$parent_country_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT p.ID 
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key = 'wta_country_code'
				AND pm.meta_value = %s
				LIMIT 1",
				WTA_POST_TYPE,
				$country_id_or_code
			) );
		}
		
		if ( ! $parent_country_id ) {
			return '';
		}
		
		// Get continent for fallback (if needed)
		$parent_continent_id = wp_get_post_parent_id( $parent_country_id );
		
		// Cache nearby countries list (24 hours) - v7 for pre-calculated country GPS
		$cache_key = 'wta_nearby_countries_' . $post_id . '_v7_' . intval( $atts['count'] );
		$cached_data = get_transient( $cache_key );
		
		if ( false !== $cached_data && is_array( $cached_data ) ) {
			return $cached_data['output'];
		}
		
		// Find nearby countries - GLOBAL search (cross-continent)
		$nearby_countries = $this->find_nearby_countries_global( $parent_country_id, intval( $atts['count'] ) );
		
		// Fallback: If too few found, fill up from same continent
		if ( count( $nearby_countries ) < intval( $atts['count'] ) && $parent_continent_id ) {
			$same_continent = $this->find_nearby_countries( $parent_continent_id, $parent_country_id, intval( $atts['count'] ) );
			$nearby_countries = array_unique( array_merge( $nearby_countries, $same_continent ) );
			$nearby_countries = array_slice( $nearby_countries, 0, intval( $atts['count'] ) );
		}
		
	if ( empty( $nearby_countries ) ) {
		return '<p class="wta-no-nearby">' . esc_html( self::get_template( 'nearby_countries_empty' ) ?: 'Der er ingen andre lande i databasen endnu.' ) . '</p>';
	}
		
		// Batch prefetch post meta for all countries (1 query instead of N×2)
		update_meta_cache( 'post', $nearby_countries );
		
		// Batch count cities for all countries (1 query instead of N queries)
		global $wpdb;
		$country_ids_string = implode( ',', array_map( 'intval', $nearby_countries ) );
		
		$city_counts = $wpdb->get_results( 
			$wpdb->prepare(
				"SELECT post_parent, COUNT(*) as city_count 
				FROM {$wpdb->posts} 
				WHERE post_parent IN (%1s)
				AND post_type = %s 
				AND post_status = 'publish'
				GROUP BY post_parent",
				$country_ids_string,
				WTA_POST_TYPE
			),
			OBJECT_K 
		);
		
		// Build output
		$output = '<div class="wta-nearby-list wta-nearby-countries-list">' . "\n";
		
		foreach ( $nearby_countries as $country_id ) {
			$country_name = get_post_field( 'post_title', $country_id );
			$country_link = get_permalink( $country_id );
			
			// Get ISO code for flag icon (now from cache)
			$iso_code = get_post_meta( $country_id, 'wta_country_code', true );
			
			// Get city count from batch query
			$cities_count = isset( $city_counts[ $country_id ] ) ? intval( $city_counts[ $country_id ]->city_count ) : 0;
			
			$description = $cities_count > 0 ? $cities_count . ' ' . ( self::get_template( 'places_in_database' ) ?: 'steder i databasen' ) : ( self::get_template( 'explore_country' ) ?: 'Udforsk landet' );
			
			$output .= '<div class="wta-nearby-item">' . "\n";
			
			// Use flag icon instead of generic globe emoji
			if ( ! empty( $iso_code ) ) {
				$output .= '  <div class="wta-nearby-icon"><span class="fi fi-' . esc_attr( strtolower( $iso_code ) ) . '"></span></div>' . "\n";
			} else {
				$output .= '  <div class="wta-nearby-icon">🌍</div>' . "\n";
			}
			
			$output .= '  <div class="wta-nearby-content">' . "\n";
			$output .= '    <a href="' . esc_url( $country_link ) . '" class="wta-nearby-title">' . esc_html( $country_name ) . '</a>' . "\n";
			$output .= '    <div class="wta-nearby-meta">' . esc_html( $description ) . '</div>' . "\n";
			$output .= '  </div>' . "\n";
			$output .= '</div>' . "\n";
		}
		
		$output .= '</div>' . "\n";
		
		// Add ItemList schema
		$city_name = get_post_field( 'post_title', $post_id );
		$schema_name = sprintf( self::get_template( 'countries_near' ) ?: 'Lande i nærheden af %s', $city_name );
		
		// Batch fetch country posts for schema (reuse query results)
		$nearby_posts = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'post__in'    => $nearby_countries,
			'orderby'     => 'post__in',
			'post_status' => 'publish',
			'nopaging'    => true,  // CRITICAL: Fetch ALL posts matching the IDs
		) );
		
		$output .= $this->generate_item_list_schema( $nearby_posts, $schema_name );
		
		// Cache the output (24 hours)
		set_transient( $cache_key, array( 'output' => $output ), DAY_IN_SECONDS );
		
		return $output;
	}

	/**
	 * Shortcode to display regional centre cities using geographic grid.
	 * Divides country into 4×4 grid and shows largest city from each zone.
	 * Ensures small cities get authority links from major cities.
	 *
	 * @since    2.35.63
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output with regional centres and schema.
	 */
	public function regional_centres_shortcode( $atts ) {
		global $wpdb;
		
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		
		$type = get_post_meta( $post_id, 'wta_type', true );
		if ( 'city' !== $type ) {
			return '';
		}
		
		// v3.0.19: Get parent country from post_parent (not meta)
		// GeoNames migration uses post hierarchy, not meta keys
		$country_id = wp_get_post_parent_id( $post_id );
		if ( ! $country_id ) {
			return '';
		}
		
		// Cache regional centres list (24 hours)
		$cache_key = 'wta_regional_centres_' . $country_id;
		$cached_data = get_transient( $cache_key );
		
		if ( false !== $cached_data && is_array( $cached_data ) ) {
			// Filter out current city
			$filtered_ids = array_diff( $cached_data['ids'], array( $post_id ) );
			if ( empty( $filtered_ids ) ) {
				return '';
			}
			return $this->render_regional_centres( $filtered_ids, $post_id, $country_id );
		}
		
		// v3.0.19: Simplified query using post_parent instead of meta join
		// Get all cities in country with coordinates and population
		$cities = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, 
				MAX(CASE WHEN pm.meta_key = 'wta_latitude' THEN pm.meta_value END) as lat,
				MAX(CASE WHEN pm.meta_key = 'wta_longitude' THEN pm.meta_value END) as lon,
				MAX(CASE WHEN pm.meta_key = 'wta_population' THEN pm.meta_value END) as pop
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND p.post_parent = %d
			AND pm.meta_key IN ('wta_latitude', 'wta_longitude', 'wta_population')
			GROUP BY p.ID
			HAVING lat IS NOT NULL AND lon IS NOT NULL",
			WTA_POST_TYPE,
			$country_id
		) );
		
		if ( count( $cities ) < 2 ) {
			return ''; // Not enough cities for regional grid
		}
		
		// Find lat/lon boundaries
		$lats = array_column( $cities, 'lat' );
		$lons = array_column( $cities, 'lon' );
		$min_lat = min( $lats );
		$max_lat = max( $lats );
		$min_lon = min( $lons );
		$max_lon = max( $lons );
		
		// Avoid division by zero for small countries
		$lat_range = $max_lat - $min_lat;
		$lon_range = $max_lon - $min_lon;
		
		if ( $lat_range < 0.1 || $lon_range < 0.1 ) {
			// Very small country: just get top 16 by population
			usort( $cities, function( $a, $b ) {
				return (int)$b->pop - (int)$a->pop;
			} );
			$grid_cities = array_slice( array_column( $cities, 'ID' ), 0, 16 );
		} else {
			// Divide into 4×4 grid
			$lat_step = $lat_range / 4;
			$lon_step = $lon_range / 4;
			
			$grid_cities = array();
			
			for ( $i = 0; $i < 4; $i++ ) {
				for ( $j = 0; $j < 4; $j++ ) {
					$zone_min_lat = $min_lat + ( $i * $lat_step );
					$zone_max_lat = $min_lat + ( ($i + 1) * $lat_step );
					$zone_min_lon = $min_lon + ( $j * $lon_step );
					$zone_max_lon = $min_lon + ( ($j + 1) * $lon_step );
					
					// Find largest city in this zone
					$zone_cities = array_filter( $cities, function( $city ) use ( $zone_min_lat, $zone_max_lat, $zone_min_lon, $zone_max_lon ) {
						return $city->lat >= $zone_min_lat && $city->lat < $zone_max_lat
							&& $city->lon >= $zone_min_lon && $city->lon < $zone_max_lon;
					} );
					
					if ( ! empty( $zone_cities ) ) {
						// Sort by population and take the largest
						usort( $zone_cities, function( $a, $b ) {
							return (int)$b->pop - (int)$a->pop;
						} );
						$grid_cities[] = $zone_cities[0]->ID;
					}
				}
			}
		}
		
		// Remove duplicates
		$grid_cities = array_unique( $grid_cities );
		
		// Cache the grid cities for this country (shared across all cities)
		set_transient( $cache_key, array( 'ids' => $grid_cities ), DAY_IN_SECONDS );
		
		// Filter out current city
		$grid_cities = array_diff( $grid_cities, array( $post_id ) );
		
		if ( empty( $grid_cities ) ) {
			return '';
		}
		
		return $this->render_regional_centres( $grid_cities, $post_id, $country_id );
	}
	
	/**
	 * Render regional centres HTML output.
	 *
	 * @since    2.35.63
	 * @param    array $city_ids Array of city post IDs.
	 * @param    int   $current_city_id Current city ID for schema.
	 * @param    int   $country_id Country ID for schema label.
	 * @return   string HTML output.
	 */
	private function render_regional_centres( $city_ids, $current_city_id, $country_id ) {
		// Batch prefetch post meta
		update_meta_cache( 'post', $city_ids );
		
		// Get posts
		$posts = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'post__in'    => $city_ids,
			'orderby'     => 'meta_value_num',
			'meta_key'    => 'wta_population',
			'order'       => 'DESC',
			'post_status' => 'publish',
			'nopaging'    => true,
		) );
		
		if ( empty( $posts ) ) {
			return '';
		}
		
		// Build output
		$output = '<div class="wta-nearby-list wta-regional-centres-list">' . "\n";
		
		foreach ( $posts as $city ) {
			$city_name = $city->post_title;
			$city_link = get_permalink( $city->ID );
			$population = get_post_meta( $city->ID, 'wta_population', true );
			
			$description = '';
			if ( $population && $population > 100000 ) {
				$description = number_format( $population, 0, ',', '.' ) . ' ' . ( self::get_template( 'inhabitants' ) ?: 'indbyggere' );
			} elseif ( $population && $population > 50000 ) {
				$description = self::get_template( 'regional_city' ) ?: 'Regional by';
		} else {
			$description = self::get_template( 'smaller_city' ) ?: 'Mindre by';
		}
			
			$output .= '<div class="wta-nearby-item">' . "\n";
			$output .= '  <div class="wta-nearby-icon">🏛️</div>' . "\n";
			$output .= '  <div class="wta-nearby-content">' . "\n";
			$output .= '    <a href="' . esc_url( $city_link ) . '" class="wta-nearby-title">' . esc_html( $city_name ) . '</a>' . "\n";
			$output .= '    <div class="wta-nearby-meta">' . esc_html( $description ) . '</div>' . "\n";
			$output .= '  </div>' . "\n";
			$output .= '</div>' . "\n";
		}
		
		$output .= '</div>' . "\n";
		
		// Add ItemList schema
		$city_name = get_post_field( 'post_title', $current_city_id );
		
		// Robust country name lookup with fallback
		// Try post_parent first (might exist for hierarchy display)
		$country_post_id = wp_get_post_parent_id( $current_city_id );
		
		// Fallback: If country_id is actually a country CODE (like "AR"), lookup the post
		if ( ! $country_post_id && ! empty( $country_id ) ) {
			global $wpdb;
			$country_post_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT p.ID 
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key = 'wta_country_code'
				AND pm.meta_value = %s
				LIMIT 1",
				WTA_POST_TYPE,
				$country_id
			) );
		}
		
		// Get country name with fallback
		$country_name = $country_post_id ? get_post_field( 'post_title', $country_post_id ) : ( self::get_template( 'the_country' ) ?: 'landet' );
		$schema_name = sprintf( self::get_template( 'cities_in_parts_of' ) ?: 'Byer i forskellige dele af %s', $country_name );
		
		$output .= $this->generate_item_list_schema( $posts, $schema_name );
		
		return $output;
	}

	/**
	 * Find nearby cities within same country using GPS distance.
	 *
	 * @since    2.20.0
	 * @param    int    $current_city_id Current city post ID.
	 * @param    int    $country_id      Parent country ID.
	 * @param    float  $lat             Latitude.
	 * @param    float  $lon             Longitude.
	 * @param    int    $count           Number of cities to return.
	 * @return   array                   Array of cities with distance.
	 */
	private function find_nearby_cities( $current_city_id, $country_id, $lat, $lon, $count = 5, $radius_km = 500 ) {
		// Get all cities in same country
		$cities = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $country_id,
			'posts_per_page' => -1,
			'post__not_in'   => array( $current_city_id ),
			'post_status'    => 'publish',
		) );
		
		if ( empty( $cities ) ) {
			return array();
		}
		
		// Batch prefetch ALL meta for all cities (1 query instead of N×2)
		$city_ids = wp_list_pluck( $cities, 'ID' );
		update_meta_cache( 'post', $city_ids );
		
		$cities_with_distance = array();
		
		foreach ( $cities as $city ) {
			// Get meta from cache (no DB hit!)
			$city_lat = get_post_meta( $city->ID, 'wta_latitude', true );
			$city_lon = get_post_meta( $city->ID, 'wta_longitude', true );
			
			if ( empty( $city_lat ) || empty( $city_lon ) ) {
				continue;
			}
			
			$distance = $this->calculate_distance( $lat, $lon, $city_lat, $city_lon );
			
			// Only include cities within specified radius
			if ( $distance <= $radius_km ) {
				$cities_with_distance[] = array(
					'id'       => $city->ID,
					'distance' => $distance,
				);
			}
		}
		
		// Sort by distance
		usort( $cities_with_distance, function( $a, $b ) {
			return $a['distance'] <=> $b['distance'];
		} );
		
		// Return top N
		return array_slice( $cities_with_distance, 0, $count );
	}

	/**
	 * Find nearby countries in same continent by geographic distance.
	 *
	 * @since    2.20.0
	 * @param    int $continent_id       Continent post ID.
	 * @param    int $current_country_id Current country ID to exclude.
	 * @param    int $count              Number of countries to return.
	 * @return   array                   Array of country IDs sorted by distance.
	 */
	private function find_nearby_countries( $continent_id, $current_country_id, $count = 5 ) {
		// Get current country's GPS coordinates
		$current_lat = get_post_meta( $current_country_id, 'wta_latitude', true );
		$current_lon = get_post_meta( $current_country_id, 'wta_longitude', true );
		
		// Fallback to alphabetical if no coordinates
		if ( empty( $current_lat ) || empty( $current_lon ) ) {
			$countries = get_posts( array(
				'post_type'      => WTA_POST_TYPE,
				'post_parent'    => $continent_id,
				'posts_per_page' => $count,
				'post__not_in'   => array( $current_country_id ),
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				'fields'         => 'ids',
			) );
			
			return $countries;
		}
		
		// Get all countries in same continent
		$countries = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $continent_id,
			'posts_per_page' => -1,
			'post__not_in'   => array( $current_country_id ),
			'post_status'    => 'publish',
		) );
		
		if ( empty( $countries ) ) {
			return array();
		}
		
		// Batch prefetch ALL meta for all countries (1 query instead of N×2)
		$country_ids = wp_list_pluck( $countries, 'ID' );
		update_meta_cache( 'post', $country_ids );
		
		$countries_with_distance = array();
		
		foreach ( $countries as $country ) {
			// Get meta from cache (no DB hit!)
			$country_lat = get_post_meta( $country->ID, 'wta_latitude', true );
			$country_lon = get_post_meta( $country->ID, 'wta_longitude', true );
			
			// Skip countries without coordinates
			if ( empty( $country_lat ) || empty( $country_lon ) ) {
				continue;
			}
			
			$distance = $this->calculate_distance( $current_lat, $current_lon, $country_lat, $country_lon );
			
			$countries_with_distance[] = array(
				'id'       => $country->ID,
				'distance' => $distance,
			);
		}
		
		// Sort by distance (closest first)
		usort( $countries_with_distance, function( $a, $b ) {
			return $a['distance'] <=> $b['distance'];
		} );
		
		// Extract IDs and return top N
		$sorted_country_ids = array_map( function( $item ) {
			return $item['id'];
		}, $countries_with_distance );
		
		return array_slice( $sorted_country_ids, 0, $count );
	}

	/**
	 * Find nearby countries GLOBALLY (cross-continent) by GPS distance.
	 * Returns countries sorted by distance regardless of continent.
	 *
	 * @since    2.35.68
	 * @param    int $current_country_id Current country ID to exclude.
	 * @param    int $count              Number of countries to return.
	 * @return   array                   Array of country IDs sorted by distance (closest first).
	 */
	private function find_nearby_countries_global( $current_country_id, $count = 24 ) {
		global $wpdb;
		
		// Get current country's GPS (pre-calculated and stored on country post)
		$current_lat = get_post_meta( $current_country_id, 'wta_latitude', true );
		$current_lon = get_post_meta( $current_country_id, 'wta_longitude', true );
		
		// v3.0.23: Auto-calculate country GPS if missing (countries don't have GPS in GeoNames)
		// v3.0.59: Also cache timezone from largest city for display purposes
		if ( empty( $current_lat ) || empty( $current_lon ) ) {
			$calculated_gps = $this->calculate_country_center( $current_country_id );
			if ( ! empty( $calculated_gps ) ) {
				$current_lat = $calculated_gps['lat'];
				$current_lon = $calculated_gps['lon'];
				// Cache GPS (geographic center)
				update_post_meta( $current_country_id, 'wta_latitude', $current_lat );
				update_post_meta( $current_country_id, 'wta_longitude', $current_lon );
				
				// Also cache timezone from largest city (for live-time display)
				$largest_city_tz = $this->get_largest_city_timezone( $current_country_id );
				if ( ! empty( $largest_city_tz ) ) {
					update_post_meta( $current_country_id, 'wta_timezone_primary', $largest_city_tz );
				}
			} else {
				return array(); // No cities in country yet
			}
		}
		
		// Get ALL countries with pre-calculated GPS coordinates (ONE fast query!)
		$countries = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				p.ID as country_id,
				pm_lat.meta_value as lat,
				pm_lon.meta_value as lon
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id 
				AND pm_type.meta_key = 'wta_type' 
				AND pm_type.meta_value = 'country'
			INNER JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id 
				AND pm_lat.meta_key = 'wta_latitude'
			INNER JOIN {$wpdb->postmeta} pm_lon ON p.ID = pm_lon.post_id 
				AND pm_lon.meta_key = 'wta_longitude'
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND p.ID != %d",
			WTA_POST_TYPE,
			$current_country_id
		) );
		
		if ( empty( $countries ) ) {
			return array();
		}
		
		$countries_with_distance = array();
		
		// Calculate distances (fast - just math, no DB queries!)
		foreach ( $countries as $country ) {
			$distance = $this->calculate_distance(
				floatval( $current_lat ),
				floatval( $current_lon ),
				floatval( $country->lat ),
				floatval( $country->lon )
			);
			
			$countries_with_distance[] = array(
				'id'       => intval( $country->country_id ),
				'distance' => $distance,
			);
		}
		
		// Sort by distance (closest first)
		usort( $countries_with_distance, function( $a, $b ) {
			return $a['distance'] <=> $b['distance'];
		} );
		
		// Extract IDs and return top N
		$sorted_country_ids = array_map( function( $item ) {
			return $item['id'];
		}, $countries_with_distance );
		
		return array_slice( $sorted_country_ids, 0, $count );
	}

	/**
	 * Calculate distance between two GPS coordinates using Haversine formula.
	 *
	 * @since    2.20.0
	 * @param    float $lat1 Latitude 1.
	 * @param    float $lon1 Longitude 1.
	 * @param    float $lat2 Latitude 2.
	 * @param    float $lon2 Longitude 2.
	 * @return   float       Distance in kilometers.
	 */
	private function calculate_distance( $lat1, $lon1, $lat2, $lon2 ) {
		$earth_radius = 6371; // km
		
		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lon = deg2rad( $lon2 - $lon1 );
		
		$a = sin( $d_lat / 2 ) * sin( $d_lat / 2 ) +
			cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
			sin( $d_lon / 2 ) * sin( $d_lon / 2 );
		
		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
		
		return $earth_radius * $c;
	}

	/**
	 * Calculate country's geographic center point based on its cities.
	 * 
	 * v3.0.23: Auto-calculate country GPS on-the-fly (GeoNames doesn't provide country coordinates)
	 * Uses geographic center (average of all city coordinates) - simple and fast.
	 *
	 * @since    3.0.23
	 * @param    int $country_id Country post ID.
	 * @return   array|false     Array with 'lat' and 'lon' keys, or false if no cities.
	 */
	private function calculate_country_center( $country_id ) {
		global $wpdb;
		
		// Get all published cities in this country with GPS coordinates
		$cities = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				pm_lat.meta_value as lat,
				pm_lon.meta_value as lon
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id 
				AND pm_lat.meta_key = 'wta_latitude'
			INNER JOIN {$wpdb->postmeta} pm_lon ON p.ID = pm_lon.post_id 
				AND pm_lon.meta_key = 'wta_longitude'
			WHERE p.post_parent = %d
			AND p.post_type = %s
			AND p.post_status = 'publish'",
			$country_id,
			WTA_POST_TYPE
		) );
		
		if ( empty( $cities ) ) {
			return false; // No cities yet
		}
		
		// Calculate geographic center (simple average)
		$total_lat = 0;
		$total_lon = 0;
		$count = count( $cities );
		
		foreach ( $cities as $city ) {
			$total_lat += floatval( $city->lat );
			$total_lon += floatval( $city->lon );
		}
		
		return array(
			'lat' => $total_lat / $count,
			'lon' => $total_lon / $count,
		);
	}

	/**
	 * Get timezone from largest city in country.
	 * 
	 * Used to provide a representative timezone for complex countries
	 * (e.g., Russia → Moscow timezone, Mexico → Mexico City timezone).
	 *
	 * @since    3.0.59
	 * @param    int $country_id Country post ID.
	 * @return   string|false    Timezone or false if no cities found.
	 */
	private function get_largest_city_timezone( $country_id ) {
		global $wpdb;
		
		$timezone = $wpdb->get_var( $wpdb->prepare(
			"SELECT pm_tz.meta_value
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id 
				AND pm_tz.meta_key = 'wta_timezone'
			LEFT JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id 
				AND pm_pop.meta_key = 'wta_population'
			WHERE p.post_parent = %d
			AND p.post_type = %s
			AND p.post_status = 'publish'
			AND pm_tz.meta_value != ''
			AND pm_tz.meta_value != 'multiple'
			ORDER BY CAST(pm_pop.meta_value AS UNSIGNED) DESC
			LIMIT 1",
			$country_id,
			WTA_POST_TYPE
		) );
		
		return $timezone ? $timezone : false;
	}

	/**
	 * Global time comparison shortcode - shows 24 cities from around the world.
	 *
	 * Usage: [wta_global_time_comparison]
	 *
	 * @since    2.26.0
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function global_time_comparison_shortcode( $atts ) {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		
		$type = get_post_meta( $post_id, 'wta_type', true );
		if ( 'city' !== $type ) {
			return '';
		}
		
		$current_city_name = get_post_field( 'post_title', $post_id );
		$current_timezone = get_post_meta( $post_id, 'wta_timezone', true );
		
		if ( empty( $current_timezone ) || 'multiple' === $current_timezone ) {
			return '';
		}
		
	// Get globally distributed cities (cached daily with date-based key for variation)
	// Daily cache ensures fresh city selection each day while maintaining performance
	$cache_key = 'wta_global_cities_' . $post_id . '_' . date( 'Ymd' );
	$comparison_cities = get_transient( $cache_key );
	
	if ( false === $comparison_cities ) {
		// First load: select, batch prefetch, sort, then cache (all in select_global_cities)
		$comparison_cities = $this->select_global_cities( $post_id, $current_timezone );
		set_transient( $cache_key, $comparison_cities, DAY_IN_SECONDS );
	} else {
		// PERFORMANCE (v2.35.43): Cached hit - refresh meta cache for fast access
		// This prevents WordPress from re-querying meta one-by-one in table loop
		$city_ids = wp_list_pluck( $comparison_cities, 'ID' );
		update_meta_cache( 'post', $city_ids );
		
		// Also refresh parent country meta
		$parent_ids = array();
		foreach ( $comparison_cities as $city ) {
			$parent_id = wp_get_post_parent_id( $city->ID );
			if ( $parent_id ) {
				$parent_ids[] = $parent_id;
			}
		}
		if ( ! empty( $parent_ids ) ) {
			$parent_ids = array_unique( $parent_ids );
			update_meta_cache( 'post', $parent_ids );
		}
	}
	
	if ( empty( $comparison_cities ) ) {
		return '';
	}
		
		// Get intro text (test mode or AI-generated)
		$test_mode = get_option( 'wta_test_mode', 0 );
		if ( $test_mode ) {
			// Test mode: use dummy text (no AI costs)
			$intro_text = 'Dummy tekst om tidsforskelle og verdensur. Test mode aktiveret.';
		} else {
			// Normal mode: AI-generated intro text (cached for 1 month)
			$intro_cache_key = 'wta_comparison_intro_' . $post_id;
			$intro_text = get_transient( $intro_cache_key );
			
			if ( false === $intro_text ) {
				$intro_text = $this->generate_comparison_intro( $current_city_name );
				if ( ! empty( $intro_text ) ) {
					set_transient( $intro_cache_key, $intro_text, MONTH_IN_SECONDS );
				}
			}
		}
		
		// Build output
		$output = '<div id="global-time-comparison" class="wta-comparison-section">' . "\n";
		
		// v3.2.56: Use language-aware heading
		$heading_template = self::get_template( 'comparison_heading' ) ?: 'Tidsforskel: Sammenlign %s med verdensur i andre byer';
		$output .= sprintf( '<h2>' . $heading_template . '</h2>' . "\n", esc_html( $current_city_name ) );
		
		if ( ! empty( $intro_text ) ) {
			$output .= '<p class="wta-comparison-intro">' . esc_html( $intro_text ) . '</p>' . "\n";
		}
		
		// Build table
		$output .= '<div class="wta-table-wrapper">' . "\n";
		$output .= '<table class="wta-time-comparison-table">' . "\n";
		$output .= '<thead><tr>' . "\n";
		
		// v3.2.56: Use language-aware table headers
		$header_city = self::get_template( 'table_header_city' ) ?: 'By';
		$header_country = self::get_template( 'table_header_country' ) ?: 'Land';
		$header_time_diff = self::get_template( 'table_header_time_diff' ) ?: 'Tidsforskel';
		$header_local_time = self::get_template( 'table_header_local_time' ) ?: 'Lokal tid';
		
		$output .= sprintf( '<th>%s</th><th>%s</th><th>%s</th><th>%s</th>' . "\n", 
			esc_html( $header_city ),
			esc_html( $header_country ),
			esc_html( $header_time_diff ),
			esc_html( $header_local_time )
		);
		$output .= '</tr></thead>' . "\n";
		$output .= '<tbody>' . "\n";
		
	foreach ( $comparison_cities as $city ) {
		$city_name = get_post_field( 'post_title', $city->ID );
		$city_timezone = get_post_meta( $city->ID, 'wta_timezone', true );
		
		// Get country name, URL and ISO code for flag
		$parent_id = wp_get_post_parent_id( $city->ID );
		$country_name = $parent_id ? get_post_field( 'post_title', $parent_id ) : '';
		$country_url = $parent_id ? get_permalink( $parent_id ) : '';
		$country_iso = $parent_id ? get_post_meta( $parent_id, 'wta_country_code', true ) : '';
		
		// Calculate time difference
		$time_diff = $this->calculate_time_difference( $current_timezone, $city_timezone );
		$local_time = $this->get_local_time( $city_timezone );
		
		$output .= '<tr>' . "\n";
		
		// City column with internal link
		$output .= sprintf( '<td><a href="%s">%s</a></td>' . "\n", 
			esc_url( get_permalink( $city->ID ) ), 
			esc_html( $city_name ) 
		);
		
		// Country column with flag and internal link
		if ( ! empty( $country_iso ) && ! empty( $country_url ) ) {
			$output .= sprintf( '<td><span class="fi fi-%s"></span> <a href="%s">%s</a></td>' . "\n", 
				esc_attr( strtolower( $country_iso ) ),
				esc_url( $country_url ),
				esc_html( $country_name ) 
			);
		} elseif ( ! empty( $country_iso ) ) {
			$output .= sprintf( '<td><span class="fi fi-%s"></span> %s</td>' . "\n", 
				esc_attr( strtolower( $country_iso ) ), 
				esc_html( $country_name ) 
			);
		} else {
			$output .= sprintf( '<td>%s</td>' . "\n", esc_html( $country_name ) );
		}
		
		$output .= sprintf( '<td class="wta-time-diff">%s</td>' . "\n", esc_html( $time_diff ) );
		$output .= sprintf( '<td><span class="wta-live-comparison-time" data-timezone="%s">%s</span></td>' . "\n", esc_attr( $city_timezone ), esc_html( $local_time ) );
		$output .= '</tr>' . "\n";
	}
		
		$output .= '</tbody>' . "\n";
		$output .= '</table>' . "\n";
		$output .= '</div>' . "\n";
		
		// v3.2.56: Use language-aware schema texts
		$schema_name_template = self::get_template( 'time_difference_between' ) ?: 'Tidsforskel mellem %s og andre byer';
		$schema_desc_template = self::get_template( 'compare_local_time' ) ?: 'Sammenlign lokal tid i %s med 24 internationale byer';
		
		$schema_name = sprintf( $schema_name_template, $current_city_name );
		$schema_description = sprintf( $schema_desc_template, $current_city_name );
		$output .= $this->generate_item_list_schema( $comparison_cities, $schema_name, $schema_description );
		
		$output .= '</div>' . "\n";
		
		return $output;
	}
	
	/**
	 * Select 24 globally distributed cities for time comparison.
	 *
	 * @since    2.26.0
	 * @param    int    $current_post_id Current city post ID.
	 * @param    string $current_timezone Current timezone.
	 * @return   array                   Array of WP_Post objects.
	 */
	private function select_global_cities( $current_post_id, $current_timezone ) {
		global $wpdb;
		
		$selected_cities = array();
		
		// 1. Always add base country city (Denmark - random among top 5 largest)
		$base_city_id = $this->get_random_city_for_country( 'DK', $current_post_id, $current_timezone );
		if ( $base_city_id ) {
			$selected_cities[] = get_post( $base_city_id );
		}
		
		// 2. Get current city's continent
		$current_continent = get_post_meta( $current_post_id, 'wta_continent_code', true );
		
		// 3. Define distribution (including Oceania)
		$distribution = array(
			'EU' => ( 'EU' === $current_continent ) ? 3 : 5,
			'AS' => ( 'AS' === $current_continent ) ? 3 : 5,
			'NA' => ( 'NA' === $current_continent ) ? 3 : 4,
			'SA' => ( 'SA' === $current_continent ) ? 3 : 3,
			'AF' => ( 'AF' === $current_continent ) ? 3 : 3,
			'OC' => ( 'OC' === $current_continent ) ? 2 : 2,
		);
		
		// 4. Fetch cities per continent
		foreach ( $distribution as $continent_code => $count ) {
			$cities = $this->get_cities_for_continent( $continent_code, $current_timezone, $current_post_id, $count );
			$selected_cities = array_merge( $selected_cities, $cities );
		}
		
		// 5. Remove duplicates and limit to 24
		$unique_cities = array();
		$seen_ids = array();
		
		foreach ( $selected_cities as $city ) {
			if ( ! in_array( $city->ID, $seen_ids ) ) {
				$unique_cities[] = $city;
				$seen_ids[] = $city->ID;
			}
		}
		
		$final_cities = array_slice( $unique_cities, 0, 24 );
		
		// PERFORMANCE (v2.35.43): Batch prefetch all meta before sorting
		// This reduces N+1 queries from ~48 to 1 on first load
		$city_ids = wp_list_pluck( $final_cities, 'ID' );
		update_meta_cache( 'post', $city_ids );
		
		// Also prefetch parent country meta (for flags/names in table)
		$parent_ids = array();
		foreach ( $final_cities as $city ) {
			$parent_id = wp_get_post_parent_id( $city->ID );
			if ( $parent_id ) {
				$parent_ids[] = $parent_id;
			}
		}
		if ( ! empty( $parent_ids ) ) {
			$parent_ids = array_unique( $parent_ids );
			update_meta_cache( 'post', $parent_ids );
		}
		
		// Sort cities by time difference for better UX (moved from shortcode to cache sorted result)
		usort( $final_cities, function( $a, $b ) use ( $current_timezone ) {
			$tz_a = get_post_meta( $a->ID, 'wta_timezone', true );
			$tz_b = get_post_meta( $b->ID, 'wta_timezone', true );
			
			$offset_a = $this->get_timezone_offset_hours( $current_timezone, $tz_a );
			$offset_b = $this->get_timezone_offset_hours( $current_timezone, $tz_b );
			
			return $offset_a <=> $offset_b;
		} );
		
		return $final_cities;
	}
	
	/**
	 * Get cities for a specific continent.
	 * 
	 * NEW (v2.35.34): One city per country for better link diversity.
	 * Randomizes among top 5 cities in each country for daily variation.
	 * OPTIMIZED (v2.35.45): Cross-page caching for continent cities.
	 *
	 * @since    2.26.0
	 * @param    string $continent_code  Continent code (EU, AS, NA, SA, AF, OC).
	 * @param    string $current_tz      Current timezone to exclude same timezone.
	 * @param    int    $current_post_id Current post ID to exclude.
	 * @param    int    $count           Number of cities to fetch (one per country).
	 * @return   array                   Array of WP_Post objects.
	 */
	private function get_cities_for_continent( $continent_code, $current_tz, $current_post_id, $count ) {
		// PERFORMANCE (v2.35.45): Cache continent data across ALL pages (not per-page)
		// This means only the FIRST visitor per day hits the database
		// All subsequent page loads get instant cache hits
		$cache_key = 'wta_continent_' . $continent_code . '_' . date( 'Ymd' );
		$cached_data = get_transient( $cache_key );
		
		if ( false === $cached_data ) {
			// First load: Build country->cities map for entire continent
			global $wpdb;
			
			// Get ALL countries in continent
			// v3.0.17: Changed LEFT JOIN to INNER JOIN to ensure valid meta values
			$all_countries = $wpdb->get_col( $wpdb->prepare( "
				SELECT DISTINCT pm_cc.meta_value as country_code
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
				INNER JOIN {$wpdb->postmeta} pm_cont ON p.ID = pm_cont.post_id AND pm_cont.meta_key = 'wta_continent_code'
				INNER JOIN {$wpdb->postmeta} pm_cc ON p.ID = pm_cc.post_id AND pm_cc.meta_key = 'wta_country_code'
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND pm_type.meta_value = 'city'
				AND pm_cont.meta_value = %s
				AND pm_cc.meta_value IS NOT NULL
			", WTA_POST_TYPE, $continent_code ) );
			
			// Build map of country -> [city IDs with timezone]
			$country_cities_map = array();
			
			foreach ( $all_countries as $country_code ) {
				// Get top 20 cities per country (to have selection pool)
				// v3.0.17: Changed LEFT JOIN to INNER JOIN to ensure valid meta values
				$cities = $wpdb->get_results( $wpdb->prepare( "
					SELECT p.ID, pm_tz.meta_value as timezone
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm_cc ON p.ID = pm_cc.post_id AND pm_cc.meta_key = 'wta_country_code'
					INNER JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id AND pm_pop.meta_key = 'wta_population'
					INNER JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id AND pm_tz.meta_key = 'wta_timezone'
					INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
					WHERE p.post_type = %s
					AND p.post_status = 'publish'
					AND pm_type.meta_value = 'city'
					AND pm_cc.meta_value = %s
					AND pm_tz.meta_value IS NOT NULL
					AND pm_tz.meta_value != 'multiple'
					ORDER BY CAST(pm_pop.meta_value AS UNSIGNED) DESC
					LIMIT 20
				", WTA_POST_TYPE, $country_code ) );
				
				if ( ! empty( $cities ) ) {
					$country_cities_map[ $country_code ] = $cities;
				}
			}
			
			// Cache for 24 hours, shared across ALL pages
			set_transient( $cache_key, $country_cities_map, DAY_IN_SECONDS );
			$cached_data = $country_cities_map;
		}
		
		if ( empty( $cached_data ) ) {
			return array();
		}
		
		// Filter out current city's timezone and select cities
		$available_countries = array();
		foreach ( $cached_data as $country_code => $cities ) {
			// Filter cities with different timezone
			$valid_cities = array_filter( $cities, function( $city ) use ( $current_tz, $current_post_id ) {
				return $city->timezone !== $current_tz && intval( $city->ID ) !== $current_post_id;
			} );
			
			if ( ! empty( $valid_cities ) ) {
				$available_countries[ $country_code ] = array_values( $valid_cities );
			}
		}
		
		if ( empty( $available_countries ) ) {
			return array();
		}
		
		// Shuffle countries with daily seed
		$all_countries = array_keys( $available_countries );
		$seed = intval( date( 'Ymd' ) ) + $current_post_id + crc32( $continent_code );
		mt_srand( $seed );
		shuffle( $all_countries );
		
		// Select one random city per country (from top 5)
		$selected_cities = array();
		
		foreach ( $all_countries as $country_code ) {
			$country_cities = $available_countries[ $country_code ];
			
			// Take top 5 (already sorted by population in cache)
			$top_cities = array_slice( $country_cities, 0, 5 );
			
			// Select random from top 5 with daily seed
			$seed = intval( date( 'Ymd' ) ) + crc32( $country_code . $current_post_id );
			mt_srand( $seed );
			$random_index = mt_rand( 0, count( $top_cities ) - 1 );
			$selected_city_id = $top_cities[ $random_index ]->ID;
			
			// Get WP_Post object
			$post = get_post( $selected_city_id );
			if ( $post ) {
				$selected_cities[] = $post;
				
				// Stop when we have enough cities
				if ( count( $selected_cities ) >= $count ) {
					break;
				}
			}
		}
		
		return $selected_cities;
	}
	
	/**
	 * Get one random city from top 5 cities in a country.
	 * 
	 * Uses daily seed for consistent randomness within same day.
	 * Different days = different cities for content freshness!
	 *
	 * @since    2.35.34
	 * @param    string $country_code    Country code (ISO2).
	 * @param    int    $exclude_id      Current city ID to exclude.
	 * @param    string $exclude_tz      Current timezone to exclude.
	 * @return   int|null                City post ID or null.
	 */
	private function get_random_city_for_country( $country_code, $exclude_id, $exclude_tz ) {
		global $wpdb;
		
		// Get top 5 cities in country
		// v3.0.17: Changed LEFT JOIN to INNER JOIN to ensure valid meta values
		$city_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_cc ON p.ID = pm_cc.post_id AND pm_cc.meta_key = 'wta_country_code'
			INNER JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id AND pm_pop.meta_key = 'wta_population'
			INNER JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id AND pm_tz.meta_key = 'wta_timezone'
			INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND p.ID != %d
			AND pm_type.meta_value = 'city'
			AND pm_cc.meta_value = %s
			AND pm_tz.meta_value IS NOT NULL
			AND pm_tz.meta_value != 'multiple'
			AND pm_tz.meta_value != %s
			ORDER BY CAST(pm_pop.meta_value AS UNSIGNED) DESC
			LIMIT 5
		", WTA_POST_TYPE, $exclude_id, $country_code, $exclude_tz ) );
		
		if ( empty( $city_ids ) ) {
			return null;
		}
		
		// Use daily seed + country code for consistent randomness per day per country
		// Same day = same city, next day = different city (content freshness!)
		$seed = intval( date( 'Ymd' ) ) + crc32( $country_code . $exclude_id );
		mt_srand( $seed );
		$random_index = mt_rand( 0, count( $city_ids ) - 1 );
		
		return $city_ids[ $random_index ];
	}
	
	/**
	 * Calculate time difference between two timezones.
	 * 
	 * NEW (v2.35.34): Correct Danish grammar (time/timer).
	 *
	 * @since    2.26.0
	 * @param    string $tz1 Timezone 1.
	 * @param    string $tz2 Timezone 2.
	 * @return   string      Formatted time difference.
	 */
	private function calculate_time_difference( $tz1, $tz2 ) {
		try {
			$timezone1 = new DateTimeZone( $tz1 );
			$timezone2 = new DateTimeZone( $tz2 );
			$now = new DateTime( 'now' );
			
			$offset = $timezone2->getOffset( $now ) - $timezone1->getOffset( $now );
			$hours_diff = $offset / 3600;
			
			// Format
			$hours_abs = abs( $hours_diff );
			$hours_formatted = ( $hours_abs == floor( $hours_abs ) ) 
				? intval( $hours_abs ) 
				: number_format( $hours_abs, 1, ',', '' );
			
			// v3.2.56: Use language-aware hour singular/plural
			$hour_singular = self::get_template( 'hour_singular' ) ?: 'time';
			$hour_plural = self::get_template( 'hour_plural' ) ?: 'timer';
			$plural = ( $hours_abs == 1 ) ? $hour_singular : $hour_plural;
			
			if ( $hours_diff > 0 ) {
				return '+' . $hours_formatted . ' ' . $plural;
			} elseif ( $hours_diff < 0 ) {
				return $hours_formatted . ' ' . $plural; // Negative sign already in number
			} else {
				// v3.2.56: Use language-aware "same time"
				return self::get_template( 'same_time' ) ?: 'Samme tid';
			}
		} catch ( Exception $e ) {
			return '';
		}
	}
	
	/**
	 * Get timezone offset in hours (for sorting).
	 *
	 * @since    2.35.34
	 * @param    string $tz1 Base timezone.
	 * @param    string $tz2 Target timezone.
	 * @return   float       Hour difference.
	 */
	private function get_timezone_offset_hours( $tz1, $tz2 ) {
		try {
			$timezone1 = new DateTimeZone( $tz1 );
			$timezone2 = new DateTimeZone( $tz2 );
			$now = new DateTime( 'now' );
			
			$offset = $timezone2->getOffset( $now ) - $timezone1->getOffset( $now );
			return $offset / 3600;
		} catch ( Exception $e ) {
			return 0;
		}
	}
	
	/**
	 * Get local time for a timezone.
	 *
	 * @since    2.26.0
	 * @param    string $timezone Timezone identifier.
	 * @return   string           Formatted time (HH:MM:SS).
	 */
	private function get_local_time( $timezone ) {
		try {
			$tz = new DateTimeZone( $timezone );
			$now = new DateTime( 'now', $tz );
			return $now->format( 'H:i:s' );
		} catch ( Exception $e ) {
			return '--:--:--';
		}
	}
	
	/**
	 * Generate AI intro text for comparison section.
	 *
	 * @since    2.26.0
	 * @param    string $city_name Current city name.
	 * @return   string            AI-generated intro text.
	 */
	private function generate_comparison_intro( $city_name ) {
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return '';
		}
		
		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		
		// v3.2.56: Use language-aware prompts
		$system = get_option( 'wta_prompt_comparison_intro_system', 'Du er SEO-ekspert. Skriv KUN teksten, ingen citationstegn, ingen ekstra forklaringer.' );
		$user_template = get_option( 'wta_prompt_comparison_intro_user', 'Skriv præcis 40-50 ord om hvorfor et verdensur er nyttigt til at sammenligne tidsforskelle mellem %s og andre internationale byer. Inkludér nøgleordene "tidsforskel", "tidsforskelle" og "verdensur". Fokusér på rejseplanlægning og internationale møder. KUN teksten.' );
		$user = sprintf( $user_template, $city_name );
		
		$url = 'https://api.openai.com/v1/chat/completions';
		
		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			'temperature' => 0.7,
			'max_tokens'  => 100,
		);
		
		$response = wp_remote_post( $url, array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );
		
		if ( is_wp_error( $response ) ) {
			return '';
		}
		
		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );
		
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return '';
		}
		
		$content = trim( $data['choices'][0]['message']['content'] );
		
		// Remove surrounding quotes
		if ( ( str_starts_with( $content, '"' ) && str_ends_with( $content, '"' ) ) ||
		     ( str_starts_with( $content, "'" ) && str_ends_with( $content, "'" ) ) ) {
			$content = substr( $content, 1, -1 );
		}
		
		return $content;
	}

	/**
	 * Generate ItemList schema for a list of locations.
	 *
	 * @since    2.24.0
	 * @param    array  $items       Array of WP_Post objects.
	 * @param    string $list_name   Name of the list.
	 * @param    string $description Description of the list.
	 * @return   string              JSON-LD script tag.
	 */
	private function generate_item_list_schema( $items, $list_name, $description = '' ) {
		if ( empty( $items ) ) {
			return '';
		}

		$item_list_elements = array();
		$position = 1;

		foreach ( $items as $item ) {
			$item_type = get_post_meta( $item->ID, 'wta_type', true );
			
			// Determine schema type
			$schema_type = 'Place';
			if ( 'country' === $item_type ) {
				$schema_type = 'Country';
			} elseif ( 'continent' === $item_type ) {
				$schema_type = 'Continent';
			}

			$item_list_elements[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'item'     => array(
					'@type' => $schema_type,
					'@id'   => get_permalink( $item->ID ),
					'name'  => get_post_field( 'post_title', $item->ID ),
				),
			);
		}

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => $list_name,
			'numberOfItems'   => count( $items ),
			'itemListElement' => $item_list_elements,
		);

		if ( ! empty( $description ) ) {
			$schema['description'] = $description;
		}

		$output = '<script type="application/ld+json">';
		$output .= wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		$output .= '</script>';

		return $output;
	}

	/**
	 * Shortcode to display continents overview with top countries.
	 *
	 * Usage: [wta_continents_overview countries_per_continent="5"]
	 *
	 * @since    2.28.0
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function continents_overview_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'countries_per_continent' => 5,
		), $atts );
		
		// Get all continents
		$continents = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'wta_type',
					'value' => 'continent',
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		
		if ( empty( $continents ) ) {
			return '<!-- No continents found -->';
		}
		
		// Build output with modern grid layout
		$output = '<div class="wta-continents-overview">' . "\n";
		
		// Schema.org ItemList - includes BOTH continents and countries
		$list_items = array();
		$position = 1;
		
		foreach ( $continents as $continent ) {
			$continent_name = get_the_title( $continent->ID );
			$continent_url = get_permalink( $continent->ID );
			
			// Add continent to schema
			$list_items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'item'     => array(
					'@type' => 'Place',
					'@id'   => $continent_url,
					'name'  => $continent_name,
				),
			);
			
		// Get random countries for this continent
		$countries = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_status'    => 'publish',
			'post_parent'    => $continent->ID,
			'posts_per_page' => intval( $atts['countries_per_continent'] ),
			'meta_query'     => array(
				array(
					'key'     => 'wta_type',
					'value'   => 'country',
					'compare' => '=',
				),
			),
			'orderby'        => 'rand',
		) );
		
		// Continent card (no emoji, clean design)
		$output .= '<div class="wta-continent-card">' . "\n";
		$output .= sprintf( 
			'<h3 class="wta-continent-title"><a href="%s">%s</a></h3>' . "\n",
			esc_url( $continent_url ),
			esc_html( $continent_name )
		);
		
		if ( ! empty( $countries ) ) {
			$output .= '<ul class="wta-country-list">' . "\n";
			foreach ( $countries as $country ) {
				$country_name = get_the_title( $country->ID );
				$country_url = get_permalink( $country->ID );
				
				// Get country ISO code for flag-icons CSS classes
				$iso_code = get_post_meta( $country->ID, 'wta_country_code', true );
				
				// Use flag-icons library for universal browser support
				// Format: fi fi-{lowercase-iso-code}
				$output .= sprintf(
					'<li><a href="%s"><span class="fi fi-%s"></span> %s</a></li>' . "\n",
					esc_url( $country_url ),
					esc_attr( strtolower( $iso_code ) ),
					esc_html( $country_name )
				);
				
				// Countries are NOT added to front page schema (only continents in ItemList)
				// Each continent page will have its own ItemList of countries for better SEO structure
			}
			$output .= '</ul>' . "\n";
		} else {
			// Debug: Show why no countries are found
			$output .= '<p class="wta-debug" style="font-size: 0.85em; color: #999;">Ingen lande fundet endnu. Import i gang...</p>' . "\n";
		}
			
			$output .= '</div>' . "\n";
		}
		
		$output .= '</div>' . "\n";
		
		// Add Schema.org markup with both continents and countries
		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => 'Verdenstidszoner efter kontinent',
			'numberOfItems'   => count( $list_items ),
			'itemListElement' => $list_items,
		);
		
		$output .= '<script type="application/ld+json">' . "\n";
		$output .= wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
		$output .= '</script>' . "\n";
		
		return $output;
	}

	/**
	 * Shortcode to display recently published cities (for monitoring during import).
	 *
	 * Usage: [wta_recent_cities count="20"]
	 *
	 * @since    2.35.1
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function recent_cities_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'count' => 20,
		), $atts );
		
		// Get recently published cities
		$recent_cities = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => intval( $atts['count'] ),
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => 'wta_type',
					'value'   => 'city',
					'compare' => '='
				)
			)
		) );
		
		if ( empty( $recent_cities ) ) {
			return '<p>Ingen byer fundet endnu.</p>';
		}
		
		// Build output
		ob_start();
		?>
		
		<div class="wta-recent-cities">
			<h2>🏙️ Seneste <?php echo count( $recent_cities ); ?> Importerede Byer</h2>
			<p class="wta-recent-intro">Disse byer er netop blevet importeret. Tjek om de har FAQ-sektion nederst på siden.</p>
			
			<div class="wta-recent-list">
				<?php foreach ( $recent_cities as $city ) : 
					$city_name = get_the_title( $city->ID );
					$city_url = get_permalink( $city->ID );
					$country_id = wp_get_post_parent_id( $city->ID );
					$country_name = $country_id ? get_the_title( $country_id ) : '';
					$timezone = get_post_meta( $city->ID, 'wta_timezone', true );
					$ai_status = get_post_meta( $city->ID, 'wta_ai_status', true );
					$has_faq = get_post_meta( $city->ID, 'wta_faq_data', true );
					$publish_date = get_the_date( 'j. M Y, H:i', $city->ID );
				?>
				<div class="wta-recent-item">
					<div class="wta-recent-header">
						<h3 class="wta-recent-title">
							<a href="<?php echo esc_url( $city_url ); ?>" target="_blank">
								<?php echo esc_html( $city_name ); ?>
							</a>
						</h3>
						<div class="wta-recent-badges">
							<?php if ( 'done' === $ai_status ) : ?>
								<span class="wta-badge wta-badge-success">✅ AI</span>
							<?php else : ?>
								<span class="wta-badge wta-badge-pending">⏳ Pending</span>
							<?php endif; ?>
							
							<?php if ( ! empty( $has_faq ) ) : ?>
								<span class="wta-badge wta-badge-success">📝 FAQ</span>
							<?php else : ?>
								<span class="wta-badge wta-badge-warning">📝 No FAQ</span>
							<?php endif; ?>
						</div>
					</div>
					
					<div class="wta-recent-meta">
						<?php if ( ! empty( $country_name ) ) : ?>
							<span>🌍 <?php echo esc_html( $country_name ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $timezone ) ) : ?>
							<span>🕐 <?php echo esc_html( $timezone ); ?></span>
						<?php endif; ?>
						<span>📅 <?php echo esc_html( $publish_date ); ?></span>
					</div>
					
					<div class="wta-recent-actions">
						<a href="<?php echo esc_url( $city_url ); ?>" class="wta-btn wta-btn-primary" target="_blank">
							👁️ Se Side
						</a>
						<a href="<?php echo admin_url( 'post.php?post=' . $city->ID . '&action=edit' ); ?>" class="wta-btn wta-btn-secondary" target="_blank">
							✏️ Rediger (ID: <?php echo $city->ID; ?>)
						</a>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		
		<style>
		.wta-recent-cities {
			margin: 2em 0;
			padding: 1.5em;
			background: #f8f9fa;
			border-radius: 8px;
		}
		.wta-recent-cities h2 {
			margin: 0 0 0.5em 0;
			color: #333;
			font-size: 1.5em;
		}
		.wta-recent-intro {
			color: #666;
			margin: 0 0 1.5em 0;
		}
		.wta-recent-list {
			display: flex;
			flex-direction: column;
			gap: 1em;
		}
		.wta-recent-item {
			background: white;
			padding: 1em;
			border-radius: 6px;
			border: 2px solid #e0e0e0;
		}
		.wta-recent-header {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			margin-bottom: 0.75em;
			flex-wrap: wrap;
			gap: 0.5em;
		}
		.wta-recent-title {
			margin: 0;
			font-size: 1.2em;
			flex: 1;
			min-width: 200px;
		}
		.wta-recent-title a {
			color: #667eea;
			text-decoration: none;
		}
		.wta-recent-title a:hover {
			text-decoration: underline;
		}
		.wta-recent-badges {
			display: flex;
			gap: 0.5em;
			flex-wrap: wrap;
		}
		.wta-badge {
			padding: 0.25em 0.6em;
			border-radius: 4px;
			font-size: 0.85em;
			font-weight: 600;
			white-space: nowrap;
		}
		.wta-badge-success {
			background: #d4edda;
			color: #155724;
		}
		.wta-badge-warning {
			background: #fff3cd;
			color: #856404;
		}
		.wta-badge-pending {
			background: #cce5ff;
			color: #004085;
		}
		.wta-recent-meta {
			display: flex;
			gap: 1em;
			flex-wrap: wrap;
			margin-bottom: 0.75em;
			font-size: 0.9em;
			color: #666;
		}
		.wta-recent-actions {
			display: flex;
			gap: 0.5em;
		}
		.wta-btn {
			display: inline-block;
			padding: 0.5em 1em;
			border-radius: 6px;
			text-decoration: none;
			font-weight: 600;
			font-size: 0.9em;
			transition: all 0.2s;
		}
		.wta-btn-primary {
			background: #667eea;
			color: white;
		}
		.wta-btn-primary:hover {
			background: #764ba2;
		}
		.wta-btn-secondary {
			background: #f0f0f0;
			color: #333;
		}
		.wta-btn-secondary:hover {
			background: #e0e0e0;
		}
		
		@media (max-width: 768px) {
			.wta-recent-cities {
				padding: 1em;
			}
			.wta-recent-item {
				padding: 0.75em;
			}
			.wta-recent-header {
				flex-direction: column;
			}
			.wta-recent-title {
				font-size: 1.1em;
			}
		}
		</style>
		
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode to display queue status (for monitoring during import).
	 *
	 * Usage: [wta_queue_status refresh="30"]
	 *
	 * @since    2.35.1
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function queue_status_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'refresh' => 30, // Auto-refresh seconds (0 = disabled)
		), $atts );
		
		// Get queue statistics using same method as backend dashboard
		$stats_data = WTA_Queue::get_stats();
		
		// Extract data
		$totals = $stats_data['by_status'];
		$queue_data = $stats_data['by_type'];
		
		// Get published/draft counts
		$posts_counts = wp_count_posts( WTA_POST_TYPE );
		$published = isset( $posts_counts->publish ) ? $posts_counts->publish : 0;
		$draft = isset( $posts_counts->draft ) ? $posts_counts->draft : 0;
		
		// Add total to totals array
		$totals['total'] = $stats_data['total'];
		
		// Build output
		ob_start();
		?>
		
		<div class="wta-queue-status" id="wta-queue-status-widget">
			<div class="wta-queue-header">
				<h2>📊 Import Status</h2>
				<div class="wta-queue-update">
					<span class="wta-queue-time">Opdateret: <?php echo date( 'H:i:s' ); ?></span>
					<?php if ( intval( $atts['refresh'] ) > 0 ) : ?>
						<span class="wta-queue-refresh">🔄 Auto-refresh: <?php echo intval( $atts['refresh'] ); ?>s</span>
					<?php endif; ?>
				</div>
			</div>
			
			<!-- Overall Stats -->
			<div class="wta-queue-overall">
				<div class="wta-stat-card wta-stat-pending">
					<div class="wta-stat-value"><?php echo number_format( $totals['pending'] ); ?></div>
					<div class="wta-stat-label">⏳ Pending</div>
				</div>
				<div class="wta-stat-card wta-stat-processing">
					<div class="wta-stat-value"><?php echo number_format( $totals['processing'] ); ?></div>
					<div class="wta-stat-label">⚙️ Processing</div>
				</div>
				<div class="wta-stat-card wta-stat-done">
					<div class="wta-stat-value"><?php echo number_format( $totals['done'] ); ?></div>
					<div class="wta-stat-label">✅ Done</div>
				</div>
				<div class="wta-stat-card wta-stat-failed">
					<div class="wta-stat-value"><?php echo number_format( $totals['error'] ); ?></div>
					<div class="wta-stat-label">❌ Failed</div>
				</div>
			</div>
			
			<!-- Published Posts -->
			<div class="wta-queue-posts">
				<div class="wta-post-card">
					<div class="wta-post-value"><?php echo number_format( $published ); ?></div>
					<div class="wta-post-label">📝 Published</div>
				</div>
				<div class="wta-post-card">
					<div class="wta-post-value"><?php echo number_format( $draft ); ?></div>
					<div class="wta-post-label">📄 Draft</div>
				</div>
				<div class="wta-post-card">
					<div class="wta-post-value"><?php echo number_format( $published + $draft ); ?></div>
					<div class="wta-post-label">📦 Total</div>
				</div>
			</div>
			
			<!-- Queue by Type -->
			<?php if ( ! empty( $queue_data ) ) : ?>
			<div class="wta-queue-types">
				<h3>Queue by Type</h3>
				<table class="wta-queue-table">
					<thead>
						<tr>
							<th>Type</th>
							<th>Pending</th>
							<th>Processing</th>
							<th>Done</th>
							<th>Failed</th>
							<th>Total</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $queue_data as $type => $data ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $type ); ?></strong></td>
							<td><?php echo number_format( $data['pending'] ); ?></td>
							<td><?php echo number_format( $data['processing'] ); ?></td>
							<td><?php echo number_format( $data['done'] ); ?></td>
							<td><?php echo number_format( $data['error'] ); ?></td>
							<td><strong><?php echo number_format( $data['total'] ); ?></strong></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
		
		<style>
		.wta-queue-status {
			margin: 2em 0;
			padding: 1.5em;
			background: #f8f9fa;
			border-radius: 8px;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}
		.wta-queue-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 1.5em;
			flex-wrap: wrap;
			gap: 0.5em;
		}
		.wta-queue-header h2 {
			margin: 0;
			color: #333;
			font-size: 1.5em;
		}
		.wta-queue-update {
			display: flex;
			gap: 1em;
			font-size: 0.9em;
			color: #666;
		}
		.wta-queue-refresh {
			color: #667eea;
			font-weight: 600;
		}
		.wta-queue-overall {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 1em;
			margin-bottom: 1.5em;
		}
		.wta-stat-card {
			background: white;
			padding: 1.5em 1em;
			border-radius: 8px;
			text-align: center;
			border: 2px solid #e0e0e0;
		}
		.wta-stat-pending { border-color: #ffc107; }
		.wta-stat-processing { border-color: #2196F3; }
		.wta-stat-done { border-color: #4CAF50; }
		.wta-stat-failed { border-color: #f44336; }
		.wta-stat-value {
			font-size: 2em;
			font-weight: 700;
			color: #333;
			margin-bottom: 0.25em;
		}
		.wta-stat-label {
			font-size: 0.9em;
			color: #666;
			font-weight: 600;
		}
		.wta-queue-posts {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 1em;
			margin-bottom: 1.5em;
		}
		.wta-post-card {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			padding: 1.5em 1em;
			border-radius: 8px;
			text-align: center;
			color: white;
		}
		.wta-post-value {
			font-size: 2em;
			font-weight: 700;
			margin-bottom: 0.25em;
		}
		.wta-post-label {
			font-size: 0.9em;
			opacity: 0.9;
			font-weight: 600;
		}
		.wta-queue-types {
			background: white;
			padding: 1.5em;
			border-radius: 8px;
		}
		.wta-queue-types h3 {
			margin: 0 0 1em 0;
			color: #333;
		}
		.wta-queue-table {
			width: 100%;
			border-collapse: collapse;
		}
		.wta-queue-table th,
		.wta-queue-table td {
			padding: 0.75em;
			text-align: left;
			border-bottom: 1px solid #e0e0e0;
		}
		.wta-queue-table th {
			background: #f8f9fa;
			font-weight: 600;
			color: #333;
		}
		.wta-queue-table tbody tr:hover {
			background: #f8f9fa;
		}
		
		@media (max-width: 768px) {
			.wta-queue-status {
				padding: 1em;
			}
			.wta-queue-header {
				flex-direction: column;
				align-items: flex-start;
			}
			.wta-queue-overall,
			.wta-queue-posts {
				grid-template-columns: repeat(2, 1fr);
			}
			.wta-stat-value,
			.wta-post-value {
				font-size: 1.5em;
			}
			.wta-queue-table {
				font-size: 0.85em;
			}
			.wta-queue-table th,
			.wta-queue-table td {
				padding: 0.5em 0.25em;
			}
		}
		</style>
		
		<?php if ( intval( $atts['refresh'] ) > 0 ) : ?>
		<script>
		(function() {
			var refreshInterval = <?php echo intval( $atts['refresh'] ); ?> * 1000;
			
			setInterval(function() {
				location.reload();
			}, refreshInterval);
		})();
		</script>
		<?php endif; ?>
		
		<?php
		return ob_get_clean();
	}
}

