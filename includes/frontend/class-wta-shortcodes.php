<?php
/**
 * Shortcodes for frontend.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

class WTA_Shortcodes {

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
		add_shortcode( 'wta_global_time_comparison', array( $this, 'global_time_comparison_shortcode' ) );
		add_shortcode( 'wta_continents_overview', array( $this, 'continents_overview_shortcode' ) );
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
		$atts = shortcode_atts( array(
			'count' => 12,
		), $atts );
		
		// Get current post ID
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '<!-- Major Cities: No post ID -->';
		}
		
		// Get location type
		$type = get_post_meta( $post_id, 'wta_type', true );
		
		if ( empty( $type ) || ! in_array( $type, array( 'continent', 'country' ) ) ) {
			return '<!-- Major Cities: Not a continent or country (type: ' . esc_html( $type ) . ') -->';
		}
		
		// Get child posts (countries or cities)
		if ( $type === 'continent' ) {
			// For continent: get all child countries first
			$children = get_posts( array(
				'post_type'      => WTA_POST_TYPE,
				'post_parent'    => $post_id,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			) );
			
			if ( empty( $children ) ) {
				return '<!-- Major Cities: No child countries found for continent (ID: ' . $post_id . ') -->';
			}
			
			$child_ids = wp_list_pluck( $children, 'ID' );
			
			// Then find major cities across all countries
			$major_cities = get_posts( array(
				'post_type'      => WTA_POST_TYPE,
				'posts_per_page' => intval( $atts['count'] ),
				'post_parent__in' => $child_ids,
				'orderby'        => 'meta_value_num',
				'meta_key'       => 'wta_population',
				'order'          => 'DESC',
				'post_status'    => 'publish',
			) );
			
			// Debug info
			if ( empty( $major_cities ) ) {
				return '<!-- Major Cities: No cities found. Countries: ' . count( $children ) . ' (IDs: ' . implode( ', ', $child_ids ) . ') -->';
			}
		} else {
			// For country: get direct child cities
			$major_cities = get_posts( array(
				'post_type'      => WTA_POST_TYPE,
				'posts_per_page' => intval( $atts['count'] ),
				'post_parent'    => $post_id,
				'orderby'        => 'meta_value_num',
				'meta_key'       => 'wta_population',
				'order'          => 'DESC',
				'post_status'    => 'publish',
			) );
			
			if ( empty( $major_cities ) ) {
				return '<!-- Major Cities: No cities found for country (ID: ' . $post_id . ') -->';
			}
		}
		
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
				$diff_text = sprintf( '%s timer foran %s', $hours_formatted, $base_country );
			} elseif ( $hours_diff < 0 ) {
				$diff_text = sprintf( '%s timer bagud for %s', $hours_formatted, $base_country );
			} else {
				$diff_text = sprintf( 'Samme tid som %s', $base_country );
			}
				
				// Initial time with seconds
				$initial_time = $now->format( 'H:i:s' );
				
				// Build clock HTML
				$output .= sprintf(
					'<div class="wta-live-city-clock" data-timezone="%s" data-base-offset="%.1f">
						<div class="wta-city-name">%s</div>
						<div class="wta-time">%s</div>
						<div class="wta-time-diff">%s</div>
					</div>' . "\n",
					esc_attr( $timezone ),
					$hours_diff,
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
		$schema_name = sprintf( 'Største byer i %s', $parent_name );
		$schema_description = sprintf( 'Se hvad klokken er i de største byer i %s', $parent_name );
		$output .= $this->generate_item_list_schema( $major_cities, $schema_name, $schema_description );
		
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
				$diff_text = sprintf( '%s timer foran %s', $hours_formatted, $base_country );
			} elseif ( $hours_diff < 0 ) {
				$diff_text = sprintf( '%s timer bagud for %s', $hours_formatted, $base_country );
			} else {
				$diff_text = sprintf( 'Samme tid som %s', $base_country );
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
		
		$atts = shortcode_atts( array(
			'orderby' => 'title',
			'order'   => 'ASC',
			'limit'   => 100,
		), $atts );
		
		// Get child locations
		$children = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $post->ID,
			'posts_per_page' => (int) $atts['limit'],
			'orderby'        => $atts['orderby'],
			'order'          => $atts['order'],
			'post_status'    => 'publish',
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
			$output .= '<h2>Oversigt over ' . esc_html( $child_type_plural ) . ' i ' . esc_html( $parent_name ) . '</h2>' . "\n";
		} elseif ( 'country' === $parent_type ) {
			$output .= '<h2>Oversigt over ' . esc_html( $child_type_plural ) . ' i ' . esc_html( $parent_name ) . '</h2>' . "\n";
		} else {
			$output .= '<h2>' . ucfirst( $child_type_plural ) . ' i ' . esc_html( $parent_name ) . '</h2>' . "\n";
		}
		
		// Intro text
		if ( 'continent' === $parent_type ) {
			$intro = sprintf(
				'I %s er der %d %s og %s tidszoner. Klik på et land for at se aktuel tid og tidszoner.',
				$parent_name,
				$count,
				$child_type_plural,
				$timezone_count
			);
		} elseif ( 'country' === $parent_type ) {
			$intro = sprintf(
				'I %s kan du se hvad klokken er i følgende %d %s:',
				$parent_name,
				$count,
				$child_type_plural
			);
		} else {
			$intro = sprintf(
				'%s har %d %s. Klik for at se mere information.',
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
			
			$output .= '<li class="wta-grid-item"><a class="wta-location-link" href="' . esc_url( get_permalink( $child->ID ) ) . '">';
			$output .= esc_html( $simple_title );
			$output .= '</a></li>' . "\n";
		}
		
		$output .= '</ul>' . "\n";
		$output .= '</div>' . "\n";
		
		// Add ItemList schema
		$schema_name = '';
		$schema_description = '';
		
		if ( 'continent' === $parent_type ) {
			$schema_name = sprintf( 'Lande i %s', $parent_name );
			$schema_description = sprintf( 'Se hvad klokken er i forskellige lande i %s', $parent_name );
		} elseif ( 'country' === $parent_type ) {
			$schema_name = sprintf( 'Steder i %s', $parent_name );
			$schema_description = sprintf( 'Se hvad klokken er i forskellige steder i %s', $parent_name );
		}
		
		$output .= $this->generate_item_list_schema( $children, $schema_name, $schema_description );
		
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
		$atts = shortcode_atts( array(
			'count' => 5,
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
		
		// Find nearby cities
		$nearby_cities = $this->find_nearby_cities( $post_id, $parent_country_id, $latitude, $longitude, intval( $atts['count'] ) );
		
		if ( empty( $nearby_cities ) ) {
			return '<p class="wta-no-nearby">Der er ingen andre byer i databasen endnu.</p>';
		}
		
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
				$description = number_format( $population, 0, ',', '.' ) . ' indbyggere';
			} elseif ( $distance < 50 ) {
				$description = 'Tæt på';
			} else {
				$description = 'By i regionen';
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
		$schema_name = sprintf( 'Byer i nærheden af %s', $city_name );
		
		// Convert nearby cities array to WP_Post objects for schema
		$nearby_posts = array();
		foreach ( $nearby_cities as $city_data ) {
			$nearby_posts[] = get_post( $city_data['id'] );
		}
		
		$output .= $this->generate_item_list_schema( $nearby_posts, $schema_name );
		
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
		$atts = shortcode_atts( array(
			'count' => 5,
		), $atts );
		
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		
		$type = get_post_meta( $post_id, 'wta_type', true );
		if ( 'city' !== $type ) {
			return '';
		}
		
		// Get parent country and continent
		$parent_country_id = wp_get_post_parent_id( $post_id );
		if ( ! $parent_country_id ) {
			return '';
		}
		
		$parent_continent_id = wp_get_post_parent_id( $parent_country_id );
		if ( ! $parent_continent_id ) {
			return '';
		}
		
		// Find nearby countries
		$nearby_countries = $this->find_nearby_countries( $parent_continent_id, $parent_country_id, intval( $atts['count'] ) );
		
		if ( empty( $nearby_countries ) ) {
			return '<p class="wta-no-nearby">Der er ingen andre lande i databasen endnu.</p>';
		}
		
		// Build output
		$output = '<div class="wta-nearby-list wta-nearby-countries-list">' . "\n";
		
		foreach ( $nearby_countries as $country_id ) {
			$country_name = get_post_field( 'post_title', $country_id );
			$country_link = get_permalink( $country_id );
			
			// Count cities in country
			$cities_count = count( get_posts( array(
				'post_type'      => WTA_POST_TYPE,
				'post_parent'    => $country_id,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			) ) );
			
			$description = $cities_count > 0 ? $cities_count . ' steder i databasen' : 'Udforsk landet';
			
			$output .= '<div class="wta-nearby-item">' . "\n";
			$output .= '  <div class="wta-nearby-icon">🌍</div>' . "\n";
			$output .= '  <div class="wta-nearby-content">' . "\n";
			$output .= '    <a href="' . esc_url( $country_link ) . '" class="wta-nearby-title">' . esc_html( $country_name ) . '</a>' . "\n";
			$output .= '    <div class="wta-nearby-meta">' . esc_html( $description ) . '</div>' . "\n";
			$output .= '  </div>' . "\n";
			$output .= '</div>' . "\n";
		}
		
		$output .= '</div>' . "\n";
		
		// Add ItemList schema
		$city_name = get_post_field( 'post_title', $post_id );
		$schema_name = sprintf( 'Lande i nærheden af %s', $city_name );
		
		// Convert country IDs to WP_Post objects for schema
		$nearby_posts = array();
		foreach ( $nearby_countries as $country_id ) {
			$nearby_posts[] = get_post( $country_id );
		}
		
		$output .= $this->generate_item_list_schema( $nearby_posts, $schema_name );
		
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
	private function find_nearby_cities( $current_city_id, $country_id, $lat, $lon, $count = 5 ) {
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
		
		$cities_with_distance = array();
		
		foreach ( $cities as $city ) {
			$city_lat = get_post_meta( $city->ID, 'wta_latitude', true );
			$city_lon = get_post_meta( $city->ID, 'wta_longitude', true );
			
			if ( empty( $city_lat ) || empty( $city_lon ) ) {
				continue;
			}
			
			$distance = $this->calculate_distance( $lat, $lon, $city_lat, $city_lon );
			
			// Only include cities within 500km
			if ( $distance <= 500 ) {
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
	 * Find nearby countries in same continent.
	 *
	 * @since    2.20.0
	 * @param    int $continent_id       Continent post ID.
	 * @param    int $current_country_id Current country ID to exclude.
	 * @param    int $count              Number of countries to return.
	 * @return   array                   Array of country IDs.
	 */
	private function find_nearby_countries( $continent_id, $current_country_id, $count = 5 ) {
		$countries = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $continent_id,
			'posts_per_page' => $count + 1,
			'post__not_in'   => array( $current_country_id ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );
		
		return array_slice( $countries, 0, $count );
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
		
		// Get globally distributed cities (cached for 24 hours)
		$cache_key = 'wta_global_cities_' . $post_id;
		$comparison_cities = get_transient( $cache_key );
		
		if ( false === $comparison_cities ) {
			$comparison_cities = $this->select_global_cities( $post_id, $current_timezone );
			set_transient( $cache_key, $comparison_cities, DAY_IN_SECONDS );
		}
		
		if ( empty( $comparison_cities ) ) {
			return '';
		}
		
		// Get AI-generated intro text (cached for 1 month)
		$intro_cache_key = 'wta_comparison_intro_' . $post_id;
		$intro_text = get_transient( $intro_cache_key );
		
		if ( false === $intro_text ) {
			$intro_text = $this->generate_comparison_intro( $current_city_name );
			if ( ! empty( $intro_text ) ) {
				set_transient( $intro_cache_key, $intro_text, MONTH_IN_SECONDS );
			}
		}
		
		// Build output
		$output = '<div id="global-time-comparison" class="wta-comparison-section">' . "\n";
		$output .= sprintf( '<h2>Tidsforskel: Sammenlign %s med verdensur i andre byer</h2>' . "\n", esc_html( $current_city_name ) );
		
		if ( ! empty( $intro_text ) ) {
			$output .= '<p class="wta-comparison-intro">' . esc_html( $intro_text ) . '</p>' . "\n";
		}
		
		// Build table
		$output .= '<div class="wta-table-wrapper">' . "\n";
		$output .= '<table class="wta-time-comparison-table">' . "\n";
		$output .= '<thead><tr>' . "\n";
		$output .= '<th>By</th><th>Land</th><th>Tidsforskel</th><th>Lokal tid</th>' . "\n";
		$output .= '</tr></thead>' . "\n";
		$output .= '<tbody>' . "\n";
		
		foreach ( $comparison_cities as $city ) {
			$city_name = get_post_field( 'post_title', $city->ID );
			$city_timezone = get_post_meta( $city->ID, 'wta_timezone', true );
			
			// Get country name
			$parent_id = wp_get_post_parent_id( $city->ID );
			$country_name = $parent_id ? get_post_field( 'post_title', $parent_id ) : '';
			
			// Calculate time difference
			$time_diff = $this->calculate_time_difference( $current_timezone, $city_timezone );
			$local_time = $this->get_local_time( $city_timezone );
			
			$output .= '<tr>' . "\n";
			$output .= sprintf( '<td><a href="%s">%s</a></td>' . "\n", esc_url( get_permalink( $city->ID ) ), esc_html( $city_name ) );
			$output .= sprintf( '<td>%s</td>' . "\n", esc_html( $country_name ) );
			$output .= sprintf( '<td class="wta-time-diff">%s</td>' . "\n", esc_html( $time_diff ) );
			$output .= sprintf( '<td><span class="wta-live-comparison-time" data-timezone="%s">%s</span></td>' . "\n", esc_attr( $city_timezone ), esc_html( $local_time ) );
			$output .= '</tr>' . "\n";
		}
		
		$output .= '</tbody>' . "\n";
		$output .= '</table>' . "\n";
		$output .= '</div>' . "\n";
		
		// Add ItemList schema (direct injection like existing schemas)
		$schema_name = sprintf( 'Tidsforskel mellem %s og andre byer', $current_city_name );
		$schema_description = sprintf( 'Sammenlign lokal tid i %s med 24 internationale byer', $current_city_name );
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
		
		// 1. Always add base country city (København)
		$base_city = $wpdb->get_row( $wpdb->prepare( "
			SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_cc ON p.ID = pm_cc.post_id AND pm_cc.meta_key = 'wta_country_code'
			LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND pm_type.meta_value = 'city'
			AND pm_cc.meta_value = 'DK'
			ORDER BY p.post_title ASC
			LIMIT 1
		", WTA_POST_TYPE ) );
		
		if ( $base_city && $base_city->ID != $current_post_id ) {
			$selected_cities[] = get_post( $base_city->ID );
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
		
		return array_slice( $unique_cities, 0, 24 );
	}
	
	/**
	 * Get cities for a specific continent.
	 *
	 * @since    2.26.0
	 * @param    string $continent_code  Continent code (EU, AS, NA, SA, AF, OC).
	 * @param    string $current_tz      Current timezone to exclude same timezone.
	 * @param    int    $current_post_id Current post ID to exclude.
	 * @param    int    $count           Number of cities to fetch.
	 * @return   array                   Array of WP_Post objects.
	 */
	private function get_cities_for_continent( $continent_code, $current_tz, $current_post_id, $count ) {
		global $wpdb;
		
		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'wta_type'
			LEFT JOIN {$wpdb->postmeta} pm_cont ON p.ID = pm_cont.post_id AND pm_cont.meta_key = 'wta_continent_code'
			LEFT JOIN {$wpdb->postmeta} pm_tz ON p.ID = pm_tz.post_id AND pm_tz.meta_key = 'wta_timezone'
			LEFT JOIN {$wpdb->postmeta} pm_pop ON p.ID = pm_pop.post_id AND pm_pop.meta_key = 'wta_population'
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND p.ID != %d
			AND pm_type.meta_value = 'city'
			AND pm_cont.meta_value = %s
			AND pm_tz.meta_value IS NOT NULL
			AND pm_tz.meta_value != 'multiple'
			AND pm_tz.meta_value != %s
			ORDER BY CAST(pm_pop.meta_value AS UNSIGNED) DESC
			LIMIT %d
		", WTA_POST_TYPE, $current_post_id, $continent_code, $current_tz, $count ) );
		
		$cities = array();
		foreach ( $results as $result ) {
			$post = get_post( $result->ID );
			if ( $post ) {
				$cities[] = $post;
			}
		}
		
		return $cities;
	}
	
	/**
	 * Calculate time difference between two timezones.
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
			
			if ( $hours_diff > 0 ) {
				return '+' . $hours_formatted . ' timer';
			} elseif ( $hours_diff < 0 ) {
				return '-' . $hours_formatted . ' timer';
			} else {
				return 'Samme tid';
			}
		} catch ( Exception $e ) {
			return '';
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
		
		$system = 'Du er SEO-ekspert. Skriv KUN teksten, ingen citationstegn, ingen ekstra forklaringer.';
		$user = sprintf(
			'Skriv præcis 40-50 ord om hvorfor et verdensur er nyttigt til at sammenligne tidsforskelle mellem %s og andre internationale byer. Inkludér nøgleordene "tidsforskel", "tidsforskelle" og "verdensur". Fokusér på rejseplanlægning og internationale møder. KUN teksten.',
			$city_name
		);
		
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
				
			// Get country ISO code for flag emoji
			$iso_code = get_post_meta( $country->ID, 'wta_country_code', true );
			$flag_emoji = '';
			
			if ( ! empty( $iso_code ) && strlen( $iso_code ) === 2 ) {
				// Hardcoded ISO to flag emoji mapping (most reliable method)
				$flags = array(
					'AD' => '🇦🇩', 'AE' => '🇦🇪', 'AF' => '🇦🇫', 'AG' => '🇦🇬', 'AI' => '🇦🇮', 'AL' => '🇦🇱',
					'AM' => '🇦🇲', 'AO' => '🇦🇴', 'AQ' => '🇦🇶', 'AR' => '🇦🇷', 'AS' => '🇦🇸', 'AT' => '🇦🇹',
					'AU' => '🇦🇺', 'AW' => '🇦🇼', 'AX' => '🇦🇽', 'AZ' => '🇦🇿', 'BA' => '🇧🇦', 'BB' => '🇧🇧',
					'BD' => '🇧🇩', 'BE' => '🇧🇪', 'BF' => '🇧🇫', 'BG' => '🇧🇬', 'BH' => '🇧🇭', 'BI' => '🇧🇮',
					'BJ' => '🇧🇯', 'BL' => '🇧🇱', 'BM' => '🇧🇲', 'BN' => '🇧🇳', 'BO' => '🇧🇴', 'BQ' => '🇧🇶',
					'BR' => '🇧🇷', 'BS' => '🇧🇸', 'BT' => '🇧🇹', 'BV' => '🇧🇻', 'BW' => '🇧🇼', 'BY' => '🇧🇾',
					'BZ' => '🇧🇿', 'CA' => '🇨🇦', 'CC' => '🇨🇨', 'CD' => '🇨🇩', 'CF' => '🇨🇫', 'CG' => '🇨🇬',
					'CH' => '🇨🇭', 'CI' => '🇨🇮', 'CK' => '🇨🇰', 'CL' => '🇨🇱', 'CM' => '🇨🇲', 'CN' => '🇨🇳',
					'CO' => '🇨🇴', 'CR' => '🇨🇷', 'CU' => '🇨🇺', 'CV' => '🇨🇻', 'CW' => '🇨🇼', 'CX' => '🇨🇽',
					'CY' => '🇨🇾', 'CZ' => '🇨🇿', 'DE' => '🇩🇪', 'DJ' => '🇩🇯', 'DK' => '🇩🇰', 'DM' => '🇩🇲',
					'DO' => '🇩🇴', 'DZ' => '🇩🇿', 'EC' => '🇪🇨', 'EE' => '🇪🇪', 'EG' => '🇪🇬', 'EH' => '🇪🇭',
					'ER' => '🇪🇷', 'ES' => '🇪🇸', 'ET' => '🇪🇹', 'FI' => '🇫🇮', 'FJ' => '🇫🇯', 'FK' => '🇫🇰',
					'FM' => '🇫🇲', 'FO' => '🇫🇴', 'FR' => '🇫🇷', 'GA' => '🇬🇦', 'GB' => '🇬🇧', 'GD' => '🇬🇩',
					'GE' => '🇬🇪', 'GF' => '🇬🇫', 'GG' => '🇬🇬', 'GH' => '🇬🇭', 'GI' => '🇬🇮', 'GL' => '🇬🇱',
					'GM' => '🇬🇲', 'GN' => '🇬🇳', 'GP' => '🇬🇵', 'GQ' => '🇬🇶', 'GR' => '🇬🇷', 'GS' => '🇬🇸',
					'GT' => '🇬🇹', 'GU' => '🇬🇺', 'GW' => '🇬🇼', 'GY' => '🇬🇾', 'HK' => '🇭🇰', 'HM' => '🇭🇲',
					'HN' => '🇭🇳', 'HR' => '🇭🇷', 'HT' => '🇭🇹', 'HU' => '🇭🇺', 'ID' => '🇮🇩', 'IE' => '🇮🇪',
					'IL' => '🇮🇱', 'IM' => '🇮🇲', 'IN' => '🇮🇳', 'IO' => '🇮🇴', 'IQ' => '🇮🇶', 'IR' => '🇮🇷',
					'IS' => '🇮🇸', 'IT' => '🇮🇹', 'JE' => '🇯🇪', 'JM' => '🇯🇲', 'JO' => '🇯🇴', 'JP' => '🇯🇵',
					'KE' => '🇰🇪', 'KG' => '🇰🇬', 'KH' => '🇰🇭', 'KI' => '🇰🇮', 'KM' => '🇰🇲', 'KN' => '🇰🇳',
					'KP' => '🇰🇵', 'KR' => '🇰🇷', 'KW' => '🇰🇼', 'KY' => '🇰🇾', 'KZ' => '🇰🇿', 'LA' => '🇱🇦',
					'LB' => '🇱🇧', 'LC' => '🇱🇨', 'LI' => '🇱🇮', 'LK' => '🇱🇰', 'LR' => '🇱🇷', 'LS' => '🇱🇸',
					'LT' => '🇱🇹', 'LU' => '🇱🇺', 'LV' => '🇱🇻', 'LY' => '🇱🇾', 'MA' => '🇲🇦', 'MC' => '🇲🇨',
					'MD' => '🇲🇩', 'ME' => '🇲🇪', 'MF' => '🇲🇫', 'MG' => '🇲🇬', 'MH' => '🇲🇭', 'MK' => '🇲🇰',
					'ML' => '🇲🇱', 'MM' => '🇲🇲', 'MN' => '🇲🇳', 'MO' => '🇲🇴', 'MP' => '🇲🇵', 'MQ' => '🇲🇶',
					'MR' => '🇲🇷', 'MS' => '🇲🇸', 'MT' => '🇲🇹', 'MU' => '🇲🇺', 'MV' => '🇲🇻', 'MW' => '🇲🇼',
					'MX' => '🇲🇽', 'MY' => '🇲🇾', 'MZ' => '🇲🇿', 'NA' => '🇳🇦', 'NC' => '🇳🇨', 'NE' => '🇳🇪',
					'NF' => '🇳🇫', 'NG' => '🇳🇬', 'NI' => '🇳🇮', 'NL' => '🇳🇱', 'NO' => '🇳🇴', 'NP' => '🇳🇵',
					'NR' => '🇳🇷', 'NU' => '🇳🇺', 'NZ' => '🇳🇿', 'OM' => '🇴🇲', 'PA' => '🇵🇦', 'PE' => '🇵🇪',
					'PF' => '🇵🇫', 'PG' => '🇵🇬', 'PH' => '🇵🇭', 'PK' => '🇵🇰', 'PL' => '🇵🇱', 'PM' => '🇵🇲',
					'PN' => '🇵🇳', 'PR' => '🇵🇷', 'PS' => '🇵🇸', 'PT' => '🇵🇹', 'PW' => '🇵🇼', 'PY' => '🇵🇾',
					'QA' => '🇶🇦', 'RE' => '🇷🇪', 'RO' => '🇷🇴', 'RS' => '🇷🇸', 'RU' => '🇷🇺', 'RW' => '🇷🇼',
					'SA' => '🇸🇦', 'SB' => '🇸🇧', 'SC' => '🇸🇨', 'SD' => '🇸🇩', 'SE' => '🇸🇪', 'SG' => '🇸🇬',
					'SH' => '🇸🇭', 'SI' => '🇸🇮', 'SJ' => '🇸🇯', 'SK' => '🇸🇰', 'SL' => '🇸🇱', 'SM' => '🇸🇲',
					'SN' => '🇸🇳', 'SO' => '🇸🇴', 'SR' => '🇸🇷', 'SS' => '🇸🇸', 'ST' => '🇸🇹', 'SV' => '🇸🇻',
					'SX' => '🇸🇽', 'SY' => '🇸🇾', 'SZ' => '🇸🇿', 'TC' => '🇹🇨', 'TD' => '🇹🇩', 'TF' => '🇹🇫',
					'TG' => '🇹🇬', 'TH' => '🇹🇭', 'TJ' => '🇹🇯', 'TK' => '🇹🇰', 'TL' => '🇹🇱', 'TM' => '🇹🇲',
					'TN' => '🇹🇳', 'TO' => '🇹🇴', 'TR' => '🇹🇷', 'TT' => '🇹🇹', 'TV' => '🇹🇻', 'TW' => '🇹🇼',
					'TZ' => '🇹🇿', 'UA' => '🇺🇦', 'UG' => '🇺🇬', 'UM' => '🇺🇲', 'US' => '🇺🇸', 'UY' => '🇺🇾',
					'UZ' => '🇺🇿', 'VA' => '🇻🇦', 'VC' => '🇻🇨', 'VE' => '🇻🇪', 'VG' => '🇻🇬', 'VI' => '🇻🇮',
					'VN' => '🇻🇳', 'VU' => '🇻🇺', 'WF' => '🇼🇫', 'WS' => '🇼🇸', 'XK' => '🇽🇰', 'YE' => '🇾🇪',
					'YT' => '🇾🇹', 'ZA' => '🇿🇦', 'ZM' => '🇿🇲', 'ZW' => '🇿🇼',
				);
				
				$iso_upper = strtoupper( $iso_code );
				if ( isset( $flags[ $iso_upper ] ) ) {
					$flag_emoji = $flags[ $iso_upper ] . ' ';
				}
			}
				
				$output .= sprintf(
					'<li><a href="%s">%s%s</a></li>' . "\n",
					esc_url( $country_url ),
					$flag_emoji,
					esc_html( $country_name )
				);
				
				// Add country to schema
				$list_items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'item'     => array(
						'@type' => 'Country',
						'@id'   => $country_url,
						'name'  => $country_name,
					),
				);
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
}

