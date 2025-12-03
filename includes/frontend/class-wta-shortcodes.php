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
					$diff_text = sprintf( '%s timer efter %s', $hours_formatted, $base_country );
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
				$diff_text = sprintf( '%s timer efter %s', $hours_formatted, $base_country );
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
}

