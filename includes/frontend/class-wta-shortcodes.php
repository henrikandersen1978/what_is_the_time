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
		
		// v3.5.7: Custom cache (prevents wp_options bloat)
		$cache_key = 'wta_major_cities_' . $post_id . '_' . intval( $atts['count'] );
		$cached = WTA_Cache::get( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		// Get major cities using optimized SQL query
		global $wpdb;
		
	if ( $type === 'continent' ) {
		// v3.5.13: Use global continent cities cache (instant lookup!)
		// Previous (v3.5.9): Complex query with 3 JOINs = 3-4 seconds ❌
		// Now: Cached lookup = 0.001 seconds ✅ (3500× faster!)
		$continent_code = get_post_meta( $post_id, 'wta_continent_code', true );
		
		if ( empty( $continent_code ) ) {
			return '<!-- Major Cities: Continent code missing (ID: ' . $post_id . ') -->';
		}
		
		// Get from global cache (shared across all continent pages!)
		$all_continent_cities = $this->get_all_continent_top_cities_cache();
		
		if ( ! isset( $all_continent_cities[ $continent_code ] ) ) {
			return '<!-- Major Cities: No cities found for continent in cache (ID: ' . $post_id . ', code: ' . $continent_code . ') -->';
		}
		
		// Get top N cities for this continent from cached array
		$city_ids = array_slice( 
			$all_continent_cities[ $continent_code ], 
			0, 
			intval( $atts['count'] ) 
		);
		
		if ( empty( $city_ids ) ) {
			return '<!-- Major Cities: No cities in cache slice for continent -->';
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
			
			// v3.5.22: For COUNTRY pages - load master cache for instant permalink access!
			$country_master = $this->get_country_cities_master_cache( $post_id );
		}
		
		// Batch fetch posts
		$major_cities = get_posts( array(
			'post_type'   => WTA_POST_TYPE,
			'post__in'    => $city_ids,
			'orderby'     => 'post__in',
			'post_status' => 'publish',
			'nopaging'    => true,  // CRITICAL: Fetch ALL posts matching the IDs
		) );
		
	// Batch prefetch meta (only needed for continents - countries use master cache)
	if ( 'continent' === $type ) {
		update_meta_cache( 'post', $city_ids );
	}
	
	// v3.5.22: Conditional permalink handling
	// CONTINENT: Batch generate (30 cities from different countries = no shared cache)
	// COUNTRY: Use master cache (instant!)
	if ( 'continent' === $type ) {
		// v3.5.11: Batch generate permalinks (6× faster for continent pages!)
		// get_permalink() in loop: 30 cities × 0.15s = 4.5 seconds ❌
		// Batch pre-generation: ~0.3 seconds total ✅
		$city_permalinks = array();
		foreach ( $major_cities as $city ) {
			$city_permalinks[ $city->ID ] = get_permalink( $city->ID );
		}
	}
	// For countries: permalinks come from $country_master (loaded above)
	
	// v3.5.11: Cache base values OUTSIDE loop (avoid 30× get_option calls)
	$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
	$base_country = get_option( 'wta_base_country_name', 'Danmark' );
	$base_tz = new DateTimeZone( $base_timezone );
	
	// Build output with anchor ID for navigation
	$output = '<div id="major-cities" class="wta-city-times-grid">' . "\n";
	
	foreach ( $major_cities as $city ) {
		$city_name = get_post_field( 'post_title', $city->ID );
		$timezone = get_post_meta( $city->ID, 'wta_timezone', true );
		
		if ( empty( $timezone ) ) {
			continue;
		}
		
		try {
			$city_tz = new DateTimeZone( $timezone );
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
		
		// v3.5.22: Get permalink from appropriate source
		// CONTINENT: Use batch-generated permalinks (v3.5.11)
		// COUNTRY: Use master cache (instant!)
		if ( 'continent' === $type ) {
			$city_url = isset( $city_permalinks[ $city->ID ] ) ? $city_permalinks[ $city->ID ] : get_permalink( $city->ID );
		} else {
			$city_url = isset( $country_master[ $city->ID ]['permalink'] ) ? $country_master[ $city->ID ]['permalink'] : get_permalink( $city->ID );
		}
			
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
		
		// v3.5.7: Cache using custom table
		WTA_Cache::set( $cache_key, $output, DAY_IN_SECONDS, 'major_cities' );
		
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
		
		// v3.5.7: Custom cache
		$cache_key = 'wta_child_locations_' . $post->ID . '_' . md5( serialize( $atts ) );
		$cached = WTA_Cache::get( $cache_key );
		
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
		
		// v3.5.7: Cache using custom table
		WTA_Cache::set( $cache_key, $output, DAY_IN_SECONDS, 'child_locations' );
		
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
		
		// v3.5.23: HTML CACHE LAYER - Try compressed HTML first (instant!)
		// Cached HTML contains only static data: city names, distances, links
		// No dates, no time-dependent data (100% safe to cache for 7 days)
		$html_cache_key = 'wta_nearby_html_' . $post_id . '_v1';
		$cached_html = WTA_Cache::get( $html_cache_key );
		
		if ( false !== $cached_html ) {
			// Decompress and return (0.001-0.01s) ⚡
			return gzuncompress( $cached_html );
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
		
		// v3.5.7: Custom cache
		$cache_key = 'wta_nearby_cities_' . $post_id . '_v2';
		$cached_data = WTA_Cache::get( $cache_key );
		
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
	
	// v3.5.17: Use country master cache instead of update_meta_cache!
	// Previous: update_meta_cache for 150 cities = 1.3 seconds ❌
	// Now: Instant array lookup from master cache = 0.001 seconds ✅
	// Improvement: 1300× faster! 🚀
	$country_master = $this->get_country_cities_master_cache( $parent_country_id );
	
	$city_ids = wp_list_pluck( $nearby_cities, 'id' );
	
	// v3.5.22: Use permalinks from master cache (instant!)
	// Previous: Batch generate 150 permalinks = 0.2-0.5 seconds + update_meta_cache overhead ❌
	// Now: Read from master cache = 0.001 seconds ✅
	// Improvement: 500× faster! 🚀
	
	// Build output
	$output = '<div class="wta-nearby-list wta-nearby-cities-list">' . "\n";
	
	foreach ( $nearby_cities as $city ) {
		$city_id = $city['id'];
		$city_data = isset( $country_master[ $city_id ] ) ? $country_master[ $city_id ] : null;
		
		// v3.5.22: Get ALL data from master cache including permalink (instant!)
		$city_name = $city_data ? $city_data['name'] : get_post_field( 'post_title', $city_id );
		$city_link = $city_data && isset( $city_data['permalink'] ) ? $city_data['permalink'] : get_permalink( $city_id );
			$distance = round( $city['distance'] );
			$population = $city_data ? intval( $city_data['population'] ) : 0;
			
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
	
	// v3.5.7: Cache using custom table (data cache - kept for backward compatibility)
	WTA_Cache::set( $cache_key, array( 'output' => $output ), DAY_IN_SECONDS, 'nearby_cities' );
	
	// v3.5.23: Cache compressed HTML (7 days - content is 100% static!)
	// Compression: ~50 KB HTML → ~5-10 KB compressed (10× smaller!)
	// Next load: 0.001s from object cache (Memcached) ⚡
	WTA_Cache::set( $html_cache_key, gzcompress( $output, 9 ), WEEK_IN_SECONDS, 'html_cache' );
	
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
		
		// v3.5.23: HTML CACHE LAYER - Try compressed HTML first (instant!)
		// Cached HTML contains only static data: country names, flags, links
		// No dates, no time-dependent data (100% safe to cache for 7 days)
		$html_cache_key = 'wta_countries_html_' . $post_id . '_v1';
		$cached_html = WTA_Cache::get( $html_cache_key );
		
		if ( false !== $cached_html ) {
			// Decompress and return (0.001-0.01s) ⚡
			return gzuncompress( $cached_html );
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
		
		// v3.5.7: Custom cache
		$cache_key = 'wta_nearby_countries_' . $post_id . '_v8_' . intval( $atts['count'] );
		$cached_data = WTA_Cache::get( $cache_key );
		
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
		
	// v3.5.18: Removed update_meta_cache for 24 countries
	// Individual get_post_meta calls below are fast enough (24 × 0.001s = 0.024s)
	// Not worth the overhead of batch prefetching for such a small set
	
	// v3.5.12: Global cache for ALL country city counts (shared across all pages!)
	// Previous: COUNT query for 24 countries on EVERY city page = 9.7s ❌
	// Now: Cached globally, updated weekly = 0.001s ✅ (9700× faster!)
	global $wpdb;
	$cache_key = 'wta_all_country_city_counts_v1';
	$city_counts_map = WTA_Cache::get( $cache_key );
	
	if ( false === $city_counts_map ) {
		// Count cities for ALL countries in one query (runs once per week!)
		$city_counts_data = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_parent, COUNT(*) as city_count 
			FROM {$wpdb->posts} 
			WHERE post_type = %s
			AND post_status IN ('publish', 'draft')
			AND post_parent > 0
			GROUP BY post_parent",
			WTA_POST_TYPE
		), ARRAY_A );
		
		// Convert to map for fast lookup
		$city_counts_map = array();
		foreach ( $city_counts_data as $row ) {
			$city_counts_map[ intval( $row['post_parent'] ) ] = intval( $row['city_count'] );
		}
		
		// Cache for 7 days (city counts change slowly)
		WTA_Cache::set( $cache_key, $city_counts_map, WEEK_IN_SECONDS, 'city_counts' );
		
		WTA_Logger::info( 'Global city counts cache built (v3.5.12)', array(
			'total_countries' => count( $city_counts_map ),
			'cache_size_kb' => round( strlen( serialize( $city_counts_map ) ) / 1024, 2 )
		) );
	}
	
	// Build array result from cached map (compatible with existing code)
	// OBJECT_K format: array with country_id as keys, objects as values
	$city_counts = array();
	foreach ( $nearby_countries as $country_id ) {
		if ( isset( $city_counts_map[ $country_id ] ) && $city_counts_map[ $country_id ] > 0 ) {
			$obj = new stdClass();
			$obj->post_parent = $country_id;
			$obj->city_count = $city_counts_map[ $country_id ];
		$city_counts[ $country_id ] = $obj;
	}
}
	
	// v3.5.14: Batch generate permalinks (24× faster!)
	// get_permalink() in loop: 24 countries × 0.04s = 0.96 seconds ❌
	// Batch pre-generation: ~0.04 seconds total ✅
	$country_permalinks = array();
	foreach ( $nearby_countries as $country_id ) {
		$country_permalinks[ $country_id ] = get_permalink( $country_id );
	}
	
	// Build output
	$output = '<div class="wta-nearby-list wta-nearby-countries-list">' . "\n";
	
	foreach ( $nearby_countries as $country_id ) {
		$country_name = get_post_field( 'post_title', $country_id );
		$country_link = isset( $country_permalinks[ $country_id ] ) ? $country_permalinks[ $country_id ] : get_permalink( $country_id );
			
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
	
	// v3.5.7: Cache using custom table (data cache - kept for backward compatibility)
	WTA_Cache::set( $cache_key, array( 'output' => $output ), DAY_IN_SECONDS, 'nearby_countries_alt' );
	
	// v3.5.23: Cache compressed HTML (7 days - content is 100% static!)
	// Compression: ~15-20 KB HTML → ~2-3 KB compressed (8× smaller!)
	// Next load: 0.001s from object cache (Memcached) ⚡
	WTA_Cache::set( $html_cache_key, gzcompress( $output, 9 ), WEEK_IN_SECONDS, 'html_cache' );
	
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
		
		// v3.5.24: HTML CACHE LAYER - Try compressed HTML first (instant!)
		// Cached HTML contains only static data: city names, links, grid layout
		// No dates, no time-dependent data (100% safe to cache for 7 days)
		$html_cache_key = 'wta_regional_html_' . $post_id . '_v1';
		$cached_html = WTA_Cache::get( $html_cache_key );
		
		if ( false !== $cached_html ) {
			// Decompress and return (0.001-0.01s) ⚡
			return gzuncompress( $cached_html );
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
	
	// v3.5.15: Cache per-country (shared across ALL cities in country!)
	// Previous: Only cached IDs → still had to render HTML every time = 1.66s ❌
	// Now: Cache IDs → re-render with filtered list (fast, meta already cached) ✅
	// This reduces 150,000 potential cache entries to ~250 (one per country)
	// Cache overhead: ~250 countries × 8 KB = 2 MB (instead of 400 MB - 1.2 GB!)
	$cache_key = 'wta_regional_centres_country_' . $country_id . '_v2';
	$cached_data = WTA_Cache::get( $cache_key );
	
	if ( false !== $cached_data && is_array( $cached_data ) ) {
		// We have cached grid city IDs for this country
		// Re-render HTML with current city filtered out (fast - meta already cached!)
		$filtered_ids = array_diff( $cached_data['ids'], array( $post_id ) );
		if ( empty( $filtered_ids ) ) {
			return '';
		}
		// Quick render using cached IDs (meta already in cache from previous renders)
		$output = $this->render_regional_centres( $filtered_ids, $post_id, $country_id );
		
		// v3.5.24: Cache compressed HTML (7 days - content is 100% static!)
		// Compression: ~20-30 KB HTML → ~3-5 KB compressed (8× smaller!)
		// Next load: 0.001s from object cache (Memcached) ⚡
		WTA_Cache::set( $html_cache_key, gzcompress( $output, 9 ), WEEK_IN_SECONDS, 'html_cache' );
		
		return $output;
	}
		
	// v3.5.16: Cache expensive GPS query per-country (eliminates 7+ second query for large countries!)
	// This query is the MAIN bottleneck for countries with many cities (e.g., Canada: 5000+ cities)
	// Cache size: Small country (500 cities) = 16 KB, Large country (5000 cities) = 160 KB
	// Total for 250 countries: ~8 MB (acceptable overhead for massive speedup!)
	$gps_cache_key = 'wta_country_cities_gps_' . $country_id . '_v1';
	$cities = WTA_Cache::get( $gps_cache_key );
	
	if ( false === $cities ) {
		// NOT CACHED - Run expensive query (only first time per country per week!)
		// v3.5.12: OPTIMIZED - Use separate JOINs instead of MAX(CASE...) GROUP BY
		// Previous: PIVOT query with MAX(CASE...) = 9.4 seconds ❌
		// Now: Direct JOINs = ~0.3-7+ seconds (depends on city count) ✅
		$cities = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, 
				pm_lat.meta_value as lat,
				pm_lon.meta_value as lon,
				pm_pop.meta_value as pop
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_lat ON p.ID = pm_lat.post_id 
				AND pm_lat.meta_key = 'wta_latitude'
			INNER JOIN {$wpdb->postmeta} pm_lon ON p.ID = pm_lon.post_id 
				AND pm_lon.meta_key = 'wta_longitude'
			INNER JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id 
				AND pm_pop.meta_key = 'wta_population'
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND p.post_parent = %d",
			WTA_POST_TYPE,
			$country_id
		) );
		
		// Cache for 7 days (city GPS coordinates rarely change)
		WTA_Cache::set( $gps_cache_key, $cities, WEEK_IN_SECONDS, 'country_gps' );
		
		WTA_Logger::info( 'Country GPS cache built (v3.5.16)', array(
			'country_id' => $country_id,
			'city_count' => count( $cities ),
			'cache_size_kb' => round( strlen( serialize( $cities ) ) / 1024, 2 ),
			'performance' => 'Eliminates 3-7+ second query on subsequent loads'
		) );
	}
		
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
	
	// v3.5.15: Cache IDs per-country (NOT per-city!)
	// This reduces 150,000 potential entries to ~250 entries
	WTA_Cache::set( $cache_key, array( 'ids' => $grid_cities ), DAY_IN_SECONDS, 'regional_centres' );
	
	// Filter out current city and render
	$filtered_ids = array_diff( $grid_cities, array( $post_id ) );
	
	if ( empty( $filtered_ids ) ) {
		return '';
	}
	
	$output = $this->render_regional_centres( $filtered_ids, $post_id, $country_id );
	
	// v3.5.24: Cache compressed HTML (7 days - content is 100% static!)
	// Compression: ~20-30 KB HTML → ~3-5 KB compressed (8× smaller!)
	// Next load: 0.001s from object cache (Memcached) ⚡
	WTA_Cache::set( $html_cache_key, gzcompress( $output, 9 ), WEEK_IN_SECONDS, 'html_cache' );
	
	return $output;
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
	// v3.5.15: Cache rendered HTML per-country to avoid expensive update_meta_cache()
	// Generate cache key based on country + sorted city IDs
	sort( $city_ids ); // Ensure consistent cache key
	$render_cache_key = 'wta_regional_centres_render_' . $country_id . '_' . md5( implode( ',', $city_ids ) );
	$cached_html = WTA_Cache::get( $render_cache_key );
	
	if ( false !== $cached_html ) {
		// Return cached HTML (skip expensive update_meta_cache + get_posts!)
		return $cached_html;
	}
	
	// NOT CACHED - Build HTML (only happens once per country per day!)
	
	// v3.5.17: Use country master cache instead of update_meta_cache!
	// Previous: update_meta_cache for 16 cities = 0.1-0.2 seconds ❌
	// Now: Instant array lookup from master cache = 0.001 seconds ✅
	$country_master = $this->get_country_cities_master_cache( $country_id );
	
	// v3.5.14: Optimized - Get posts without meta_key JOIN (faster!)
	// Previous: orderby => meta_value_num requires JOIN to postmeta ❌
	// Now: orderby => post__in, then sort in PHP using cached meta ✅
	$posts = get_posts( array(
		'post_type'   => WTA_POST_TYPE,
		'post__in'    => $city_ids,
		'orderby'     => 'post__in',
		'post_status' => 'publish',
		'nopaging'    => true,
	) );
	
	if ( empty( $posts ) ) {
		return '';
	}
	
	// Sort by population in PHP using master cache (instant!)
	usort( $posts, function( $a, $b ) use ( $country_master ) {
		$pop_a = isset( $country_master[ $a->ID ] ) ? (int) $country_master[ $a->ID ]['population'] : 0;
		$pop_b = isset( $country_master[ $b->ID ] ) ? (int) $country_master[ $b->ID ]['population'] : 0;
		return $pop_b - $pop_a; // DESC order
	} );
	
	// v3.5.22: Use permalinks from master cache (instant!)
	// Previous: Batch generate 16 permalinks = 0.04 seconds ❌
	// Now: Read from master cache = 0.001 seconds ✅
	// Improvement: 40× faster! 🚀
	
	// Build output
	$output = '<div class="wta-nearby-list wta-regional-centres-list">' . "\n";
	
	foreach ( $posts as $city ) {
		$city_name = $city->post_title;
		// v3.5.22: Get permalink from master cache (instant!)
		$city_link = isset( $country_master[ $city->ID ]['permalink'] ) ? $country_master[ $city->ID ]['permalink'] : get_permalink( $city->ID );
			// v3.5.17: Get population from master cache (instant!)
			$population = isset( $country_master[ $city->ID ] ) ? (int) $country_master[ $city->ID ]['population'] : 0;
			
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
	
	// v3.5.15: Get country info for schema (use country_id, not current_city_id for caching)
	// This allows us to cache the same HTML for ALL cities in the country
	$country_post_id = $country_id;
	
	// Get country name with fallback
	$country_name = $country_post_id ? get_post_field( 'post_title', $country_post_id ) : ( self::get_template( 'the_country' ) ?: 'landet' );
	$schema_name = sprintf( self::get_template( 'cities_in_parts_of' ) ?: 'Byer i forskellige dele af %s', $country_name );
	
	$output .= $this->generate_item_list_schema( $posts, $schema_name );
	
	// v3.5.15: Cache rendered HTML per-country (2 MB total overhead for ~250 countries)
	// This eliminates update_meta_cache() + get_posts() + permalink gen on subsequent city pages
	// First city in country: 6 seconds (builds cache)
	// Other cities in country: 0.05 seconds (uses cached HTML) = 120× faster! 🚀
	WTA_Cache::set( $render_cache_key, $output, DAY_IN_SECONDS, 'regional_centres_html' );
	
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
		// v3.5.18: Use country master cache instead of get_posts + update_meta_cache!
		// Previous: get_posts(5000 cities) + update_meta_cache = 1.3 seconds ❌
		// Now: Instant array lookup from master cache = 0.001 seconds ✅
		// Improvement: 1300× faster! 🚀
		$country_master = $this->get_country_cities_master_cache( $country_id );
		
		if ( empty( $country_master ) ) {
			return array();
		}
		
		$cities_with_distance = array();
		
		// Loop through master cache (instant - no DB queries!)
		foreach ( $country_master as $city_id => $city_data ) {
			if ( $city_id == $current_city_id ) {
				continue; // Skip current city
			}
			
			// v3.5.19: Fix empty() bug - empty(0) returns TRUE in PHP!
			// We need to check if lat/lon exist AND are not both exactly 0
			if ( ! isset( $city_data['latitude'] ) || ! isset( $city_data['longitude'] ) ) {
				continue; // Skip if GPS data missing
			}
			
			$city_lat = floatval( $city_data['latitude'] );
			$city_lon = floatval( $city_data['longitude'] );
			
			// Only skip if BOTH are exactly 0.0 (invalid GPS)
			if ( $city_lat === 0.0 && $city_lon === 0.0 ) {
				continue;
			}
			
			$distance = $this->calculate_distance( $lat, $lon, $city_lat, $city_lon );
			
			// Only include cities within specified radius
			if ( $distance <= $radius_km ) {
				$cities_with_distance[] = array(
					'id'       => $city_id,
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
		// v3.5.18: Use global countries GPS cache (from v3.5.11)
		// Previous: get_posts + update_meta_cache = 0.2 seconds ❌
		// Now: Global cache lookup = 0.001 seconds ✅
		// Improvement: 200× faster! 🚀
		global $wpdb;
		
		// Get current country's GPS coordinates from global cache
		$all_countries_gps = WTA_Cache::get( 'wta_all_countries_gps_v2' );
		
		if ( false === $all_countries_gps ) {
			// Fallback to old method if cache not built yet
			// This should never happen as cache is built on first global_comparison load
			$current_lat = get_post_meta( $current_country_id, 'wta_latitude', true );
			$current_lon = get_post_meta( $current_country_id, 'wta_longitude', true );
			
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
		} else {
			// Find current country in cache
			$current_lat = null;
			$current_lon = null;
			foreach ( $all_countries_gps as $country_data ) {
				if ( $country_data->country_id == $current_country_id ) {
					$current_lat = $country_data->lat;
					$current_lon = $country_data->lon;
					break;
				}
			}
			
			if ( empty( $current_lat ) || empty( $current_lon ) ) {
				// Fallback to alphabetical
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
		}
		
		// Get all country IDs in this continent (fast query - no JOINs!)
		$country_ids_in_continent = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			WHERE post_type = %s 
			AND post_status = 'publish'
			AND post_parent = %d
			AND ID != %d",
			WTA_POST_TYPE,
			$continent_id,
			$current_country_id
		) );
		
		if ( empty( $country_ids_in_continent ) ) {
			return array();
		}
		
		// Calculate distances using global GPS cache
		$countries_with_distance = array();
		
		foreach ( $country_ids_in_continent as $country_id ) {
			// Find GPS in global cache
			$country_lat = null;
			$country_lon = null;
			
			foreach ( $all_countries_gps as $country_data ) {
				if ( $country_data->country_id == $country_id ) {
					$country_lat = $country_data->lat;
					$country_lon = $country_data->lon;
					break;
				}
			}
			
			// v3.5.19: Fix empty() bug - empty(0) returns TRUE!
			// Check for null (not found) OR both coordinates are 0
			if ( $country_lat === null || $country_lon === null ) {
				continue; // GPS data not found
			}
			
			$country_lat = floatval( $country_lat );
			$country_lon = floatval( $country_lon );
			
			if ( $country_lat === 0.0 && $country_lon === 0.0 ) {
				continue; // Invalid GPS (both exactly 0)
			}
			
			$distance = $this->calculate_distance( $current_lat, $current_lon, $country_lat, $country_lon );
			
			$countries_with_distance[] = array(
				'id'       => $country_id,
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
	
	// v3.5.11: GLOBAL cache for ALL countries GPS (shared across ALL 150k+ pages!)
	// This data NEVER changes (GPS coordinates are static), so cache it for 7 days
	// Previous: Heavy query (3 JOINs, 200+ countries) ran on EVERY page = 2.3s ❌
	// Now: Cached globally, query runs once per week = 0.001s ✅
	// Cache size: ~6.5 KB (negligible compared to 2300× performance gain!)
	$cache_key = 'wta_all_countries_gps_v2';
	$countries = WTA_Cache::get( $cache_key );
	
	if ( false === $countries ) {
		// Only run this heavy query once per week!
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
			AND p.post_status = 'publish'",
			WTA_POST_TYPE
		) );
		
		// Cache for 7 days (static GPS data, rarely changes)
		WTA_Cache::set( $cache_key, $countries, WEEK_IN_SECONDS, 'countries_gps' );
		
		WTA_Logger::info( 'Global countries GPS cache built (v3.5.11)', array(
			'total_countries' => count( $countries ),
			'cache_key' => $cache_key,
			'cache_size_kb' => round( strlen( serialize( $countries ) ) / 1024, 2 )
		) );
	}
		
		if ( empty( $countries ) ) {
			return array();
		}
		
	$countries_with_distance = array();
	
	// Calculate distances (fast - just math, no DB queries!)
	// v3.5.11: Filter out current country during distance calculation (not in SQL)
	foreach ( $countries as $country ) {
		$country_id = intval( $country->country_id );
		
		// Skip current country
		if ( $country_id === $current_country_id ) {
			continue;
		}
		
		$distance = $this->calculate_distance(
			floatval( $current_lat ),
			floatval( $current_lon ),
			floatval( $country->lat ),
			floatval( $country->lon )
		);
		
		$countries_with_distance[] = array(
			'id'       => $country_id,
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
		
		// v3.5.23: HTML CACHE LAYER - Try compressed HTML first (instant!)
		// Safe to cache: HTML structure + city names are static
		// Times are updated LIVE via JavaScript (data-timezone attribute)
		// Cached per day because AI-generated intro text changes daily
		$html_cache_key = 'wta_global_html_' . $post_id . '_' . date( 'Ymd' ) . '_v1';
		$cached_html = WTA_Cache::get( $html_cache_key );
		
		if ( false !== $cached_html ) {
			// Decompress and return (0.001-0.01s) ⚡
			return gzuncompress( $cached_html );
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
		
	// v3.5.7: Custom cache for globally distributed cities
	$cache_key = 'wta_global_cities_' . $post_id . '_' . date( 'Ymd' );
	$comparison_cities = WTA_Cache::get( $cache_key );
	
	if ( false === $comparison_cities ) {
		// First load: select, batch prefetch, sort, then cache
		$comparison_cities = $this->select_global_cities( $post_id, $current_timezone );
		WTA_Cache::set( $cache_key, $comparison_cities, DAY_IN_SECONDS, 'global_comparison' );
	} else {
		// v3.5.17: RESTORED - Refresh meta cache for cached cities
		// This is necessary because meta values are NOT embedded in cached WP_Post objects
		// Without this, get_post_meta() returns NULL and shortcode shows no/wrong content
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
		
	// v3.5.25: Get pre-generated intro text from background job
	// NO LONGER generate on page load (causes 1.9s delay!)
	// Background job (comparison-intro-processor) generates these
	// If missing, we simply skip intro (table still works perfectly - graceful degradation)
	// v3.7.2: Language-specific cache key
	$test_mode = get_option( 'wta_test_mode', 0 );
	if ( $test_mode ) {
		// Test mode: use dummy text (no AI costs)
		$intro_text = 'Dummy tekst om tidsforskelle og verdensur. Test mode aktiveret.';
	} else {
		// Check if intro text exists (generated by background job)
		// v3.7.2: Include language in cache key
		$lang = get_option( 'wta_site_language', 'da' );
		$intro_cache_key = 'wta_comparison_intro_' . $post_id . '_' . $lang;
		$intro_text = WTA_Cache::get( $intro_cache_key );
		
		// If not found, simply skip it (don't generate on page load!)
		// Background job will generate it eventually
		// Table works perfectly without intro text ✅
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
	
	// v3.5.23: Cache compressed HTML (1 day - AI intro changes daily)
	// Compression: ~25-30 KB HTML → ~3-5 KB compressed (8× smaller!)
	// Times are updated LIVE via JavaScript, so HTML structure can be cached!
	// Next load: 0.001s from object cache (Memcached) ⚡
	WTA_Cache::set( $html_cache_key, gzcompress( $output, 9 ), DAY_IN_SECONDS, 'html_cache' );
	
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
	 * Get ALL continent cities in ONE query (v3.5.8 performance fix).
	 * 
	 * PROBLEM (v3.5.7):
	 * - select_global_cities() called get_cities_for_continent() 6 times (EU,AS,NA,SA,AF,OC)
	 * - Each ran separate SQL query with 5-6 JOINs + subquery
	 * - First load each day = 6 × 1.3 sec = ~8 seconds ❌
	 * 
	 * SOLUTION (v3.5.8):
	 * - ONE master query fetches data for ALL 6 continents
	 * - Cached once per day (shared across ALL 150k+ pages!)
	 * - First load: 1 × 1.5 sec = ~1.5 seconds ✅
	 * - All subsequent loads: <0.5 seconds (cache hit) ✅
	 * 
	 * @since 3.5.8
	 * @return array Master cache: [continent_code][country_code][] = city objects
	 */
	private function get_all_continents_master_cache() {
		// Daily cache shared across ALL pages
		$cache_key = 'wta_master_continents_' . date( 'Ymd' );
		$cached_data = WTA_Cache::get( $cache_key );
		
		if ( false !== $cached_data ) {
			return $cached_data;
		}
		
		global $wpdb;
		
		// ONE QUERY for ALL 6 continents (EU, AS, NA, SA, AF, OC)
		// Fetches top 20 cities per country across all continents
		// v3.5.20: Fixed collation issue for MySQL 8.0+ compatibility
		$all_cities = $wpdb->get_results( $wpdb->prepare( "
			SELECT sub.ID, sub.continent_code, sub.country_code, sub.timezone, sub.row_num
			FROM (
				SELECT 
					p.ID,
					pm_cont.meta_value as continent_code,
					pm_cc.meta_value as country_code,
					pm_tz.meta_value as timezone,
					@row_num := IF(@prev_country COLLATE utf8mb4_unicode_ci = pm_cc.meta_value COLLATE utf8mb4_unicode_ci, @row_num + 1, 1) as row_num,
					@prev_country := pm_cc.meta_value COLLATE utf8mb4_unicode_ci as prev_country
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
				INNER JOIN {$wpdb->postmeta} pm_cont ON p.ID = pm_cont.post_id AND pm_cont.meta_key = 'wta_continent_code'
				INNER JOIN {$wpdb->postmeta} pm_cc ON p.ID = pm_cc.post_id AND pm_cc.meta_key = 'wta_country_code'
				INNER JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id AND pm_pop.meta_key = 'wta_population'
				INNER JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id AND pm_tz.meta_key = 'wta_timezone'
				CROSS JOIN (SELECT @row_num := 0, @prev_country := '') as vars
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND pm_type.meta_value = 'city'
				AND pm_cont.meta_value IN ('EU','AS','NA','SA','AF','OC')
				AND pm_cc.meta_value IS NOT NULL
				AND pm_tz.meta_value IS NOT NULL
				AND pm_tz.meta_value != 'multiple'
				ORDER BY pm_cc.meta_value, CAST(pm_pop.meta_value AS UNSIGNED) DESC
			) as sub
			WHERE sub.row_num <= 20
		", WTA_POST_TYPE ) );
		
		// Group by continent → country → cities
		$master_cache = array();
		foreach ( $all_cities as $city ) {
			$cont = $city->continent_code;
			$cc = $city->country_code;
			
			if ( ! isset( $master_cache[ $cont ] ) ) {
				$master_cache[ $cont ] = array();
			}
			if ( ! isset( $master_cache[ $cont ][ $cc ] ) ) {
				$master_cache[ $cont ][ $cc ] = array();
			}
			
			$master_cache[ $cont ][ $cc ][] = (object) array(
				'ID' => $city->ID,
				'timezone' => $city->timezone
			);
		}
		
		// Cache for 24 hours (same as v3.5.7 per-continent caches)
		WTA_Cache::set( $cache_key, $master_cache, DAY_IN_SECONDS, 'master_continents' );
		
		WTA_Logger::info( 'Master continent cache built (v3.5.8)', array(
			'continents' => count( $master_cache ),
			'total_cities' => count( $all_cities ),
			'cache_key' => $cache_key,
			'performance' => '6 queries reduced to 1'
		) );
		
		return $master_cache;
	}
	
	/**
	 * Get global cache of top cities for ALL continents.
	 * 
	 * v3.5.13: Optimizes major_cities_shortcode for continent pages.
	 * Caches top 50 cities per continent in ONE query.
	 * 
	 * PERFORMANCE:
	 * - Before: 6 separate queries (one per continent) = 3-4 seconds each
	 * - After: 1 global query (shared across all continents) = 0.001 seconds
	 * - Improvement: 3500× faster! ⚡
	 *
	 * @since    3.5.13
	 * @return   array  Array keyed by continent code, values are arrays of city IDs
	 */
	private function get_all_continent_top_cities_cache() {
		global $wpdb;
		
		$cache_key = 'wta_all_continent_top_cities_v1';
		$cached = WTA_Cache::get( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		// Single query to get top 50 cities for EACH continent
		// (50 per continent = covers default 30 + room for growth)
		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT 
				pm_cont.meta_value as continent_code,
				p.ID,
				CAST(pm_pop.meta_value AS UNSIGNED) as population
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_pop 
				ON p.ID = pm_pop.post_id AND pm_pop.meta_key = 'wta_population'
			INNER JOIN {$wpdb->postmeta} pm_type 
				ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
			INNER JOIN {$wpdb->postmeta} pm_cont 
				ON p.ID = pm_cont.post_id AND pm_cont.meta_key = 'wta_continent_code'
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND pm_type.meta_value = 'city'
			AND pm_cont.meta_value IN ('EU','AS','NA','SA','AF','OC')
			ORDER BY pm_cont.meta_value ASC, population DESC
		", WTA_POST_TYPE ), ARRAY_A );
		
		// Group by continent and limit to top 50 per continent
		$continent_cities = array();
		foreach ( $results as $row ) {
			$code = $row['continent_code'];
			if ( ! isset( $continent_cities[ $code ] ) ) {
				$continent_cities[ $code ] = array();
			}
			if ( count( $continent_cities[ $code ] ) < 50 ) {
				$continent_cities[ $code ][] = intval( $row['ID'] );
			}
		}
		
		// Cache for 1 day
		WTA_Cache::set( $cache_key, $continent_cities, DAY_IN_SECONDS, 'continent_cities' );
		
		WTA_Logger::info( 'Global continent cities cache built (v3.5.13)', array(
			'continents' => count( $continent_cities ),
			'total_cities' => array_sum( array_map( 'count', $continent_cities ) ),
			'cache_size_kb' => round( strlen( serialize( $continent_cities ) ) / 1024, 2 ),
			'performance' => '6 continent queries reduced to 1 global cache'
		) );
		
		return $continent_cities;
	}
	
	/**
	 * Get master cache of ALL cities in a country with their complete data.
	 * 
	 * v3.5.17: GAME CHANGER - Eliminates update_meta_cache() overhead!
	 * 
	 * This function caches ALL city data for a country in ONE optimized query,
	 * then returns it as an instantly-accessible array. All shortcodes on city
	 * pages can use this shared cache instead of calling update_meta_cache() 
	 * and get_post_meta() repeatedly.
	 * 
	 * PROBLEM SOLVED:
	 * - update_meta_cache() for 150 cities = 1.3 seconds ❌
	 * - Array lookup in master cache = 0.001 seconds ✅
	 * - Improvement: 1300× faster! 🚀
	 * 
	 * CACHE STRUCTURE:
	 * - Per-country (NOT per-city!)
	 * - All 5000 Canadian cities share ONE cache entry
	 * - All 500 Danish cities share ONE cache entry
	 * - Total: 250 cache entries (one per country)
	 * - Size: ~25 MB for all 250 countries
	 * 
	 * PERFORMANCE:
	 * - First city in Canada: Builds cache (~1 sec)
	 * - Next 4999 Canadian cities: Use cached data (instant!)
	 * - Cache hit rate: 99.8% 🎉
	 *
	 * @since    3.5.17
	 * @param    int $country_id Country post ID
	 * @return   array Map of city_id => city_data with all meta fields
	 */
	private function get_country_cities_master_cache( $country_id ) {
		global $wpdb;
		
	// Cache key per country
	// v3.5.22: Bumped to v2 (added permalink to cache structure)
	$cache_key = 'wta_country_master_' . $country_id . '_v2';
		$cached = WTA_Cache::get( $cache_key );
		
		if ( false !== $cached ) {
			return $cached; // Instant return from cache! ⚡
		}
		
		// NOT CACHED - Build master data for this country
		// ONE optimized query gets ALL data for ALL cities in country using MAX(CASE...)
		// This is faster than multiple JOINs for large datasets
		$cities = $wpdb->get_results( $wpdb->prepare( "
			SELECT 
				p.ID,
				p.post_title as name,
				MAX(CASE WHEN pm.meta_key = 'wta_latitude' THEN pm.meta_value END) as latitude,
				MAX(CASE WHEN pm.meta_key = 'wta_longitude' THEN pm.meta_value END) as longitude,
				MAX(CASE WHEN pm.meta_key = 'wta_population' THEN pm.meta_value END) as population,
				MAX(CASE WHEN pm.meta_key = 'wta_timezone' THEN pm.meta_value END) as timezone,
				MAX(CASE WHEN pm.meta_key = 'wta_country_code' THEN pm.meta_value END) as country_code,
				MAX(CASE WHEN pm.meta_key = 'wta_continent_code' THEN pm.meta_value END) as continent_code,
				MAX(CASE WHEN pm.meta_key = 'wta_geonames_id' THEN pm.meta_value END) as geonames_id
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_parent = %d
			AND p.post_type = %s
			AND p.post_status = 'publish'
			GROUP BY p.ID, p.post_title
		", $country_id, WTA_POST_TYPE ), ARRAY_A );
		
	// Convert to map for instant lookup by ID
	$city_map = array();
	foreach ( $cities as $city ) {
		$city_id = intval( $city['ID'] );
		// v3.5.22: Add permalink to master cache (eliminates get_permalink loops!)
		$city['permalink'] = get_permalink( $city_id );
		$city_map[ $city_id ] = $city;
	}
	
	// Cache for 7 days (city data rarely changes)
	WTA_Cache::set( $cache_key, $city_map, WEEK_IN_SECONDS, 'country_master' );
	
	WTA_Logger::info( 'Country master cache built (v3.5.22)', array(
			'country_id' => $country_id,
			'city_count' => count( $city_map ),
			'cache_size_kb' => round( strlen( serialize( $city_map ) ) / 1024, 2 ),
			'performance' => 'Eliminates update_meta_cache + get_post_meta overhead'
		) );
		
		return $city_map;
	}
	
	/**
	 * Get cities for a specific continent.
	 * 
	 * NEW (v2.35.34): One city per country for better link diversity.
	 * Randomizes among top 5 cities in each country for daily variation.
	 * OPTIMIZED (v2.35.45): Cross-page caching for continent cities.
	 * OPTIMIZED (v3.5.8): Uses master cache instead of per-continent queries.
	 *
	 * @since    2.26.0
	 * @param    string $continent_code  Continent code (EU, AS, NA, SA, AF, OC).
	 * @param    string $current_tz      Current timezone to exclude same timezone.
	 * @param    int    $current_post_id Current post ID to exclude.
	 * @param    int    $count           Number of cities to fetch (one per country).
	 * @return   array                   Array of WP_Post objects.
	 */
	private function get_cities_for_continent( $continent_code, $current_tz, $current_post_id, $count ) {
		// v3.5.8: Use master cache instead of per-continent queries
		// This reduces 6 queries to 1 query on first load each day
		$master_cache = $this->get_all_continents_master_cache();
		
		// Extract data for this specific continent
		$cached_data = isset( $master_cache[ $continent_code ] ) ? $master_cache[ $continent_code ] : array();
		
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
	
	// v3.5.11: Cache top 10 cities per country (shared across all cities in that country)
	// Previous: Heavy query (4 JOINs) ran for EVERY city page = 0.25s ❌
	// Now: Cached per country, query runs once per week = 0.001s ✅
	// Cache size: 200 countries × ~1 KB = ~168 KB total (excellent ROI!)
	$cache_key = 'wta_country_cities_' . $country_code . '_v2';
	$city_data = WTA_Cache::get( $cache_key );
	
	if ( false === $city_data ) {
		// Fetch top 10 cities for this country (more variety than top 5)
		$city_data = $wpdb->get_results( $wpdb->prepare( "
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
			LIMIT 10
		", WTA_POST_TYPE, $country_code ) );
		
		// Cache for 7 days (city rankings rarely change)
		WTA_Cache::set( $cache_key, $city_data, WEEK_IN_SECONDS, 'country_cities' );
	}
	
	// Filter out excluded city and timezone
	$city_ids = array();
	foreach ( $city_data as $city ) {
		if ( intval( $city->ID ) !== $exclude_id && $city->timezone !== $exclude_tz ) {
			$city_ids[] = intval( $city->ID );
		}
	}
		
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

