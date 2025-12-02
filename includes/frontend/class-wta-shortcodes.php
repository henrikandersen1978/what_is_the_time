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
				'post_status'    => array( 'publish', 'draft' ),
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
				'post_status'    => array( 'publish', 'draft' ),
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
				'post_status'    => array( 'publish', 'draft' ),
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
			
			try {
				$city_tz = new DateTimeZone( $timezone );
				$base_tz = new DateTimeZone( $base_timezone );
				$now = new DateTime( 'now', $city_tz );
				$base_time = new DateTime( 'now', $base_tz );
				
				$offset = $city_tz->getOffset( $now ) - $base_tz->getOffset( $base_time );
				$hours_diff = $offset / 3600;
				
				$diff_text = '';
				if ( $hours_diff > 0 ) {
					$diff_text = sprintf( '+%.1f timer foran', abs( $hours_diff ) );
				} elseif ( $hours_diff < 0 ) {
					$diff_text = sprintf( '%.1f timer efter', abs( $hours_diff ) );
				} else {
					$diff_text = 'Samme tid';
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
			'post_status'    => array( 'publish', 'draft' ),
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
			
			$diff_text = '';
			if ( $hours_diff > 0 ) {
				$diff_text = sprintf( '+%.1f timer foran', abs( $hours_diff ) );
			} elseif ( $hours_diff < 0 ) {
				$diff_text = sprintf( '%.1f timer efter', abs( $hours_diff ) );
			} else {
				$diff_text = 'Samme tid';
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
			'post_status'    => array( 'publish', 'draft' ),
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
}

