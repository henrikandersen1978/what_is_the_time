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
	}

	/**
	 * Shortcode to display current time in a city.
	 *
	 * Usage: [wta_city_time city="London"]
	 *
	 * @since    2.9.2
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
				$diff_text = sprintf( '%+.1f timer foran %s', $hours_diff, $base_country );
			} elseif ( $hours_diff < 0 ) {
				$diff_text = sprintf( '%.1f timer efter %s', abs( $hours_diff ), $base_country );
			} else {
				$diff_text = sprintf( 'Samme tid som %s', $base_country );
			}
			
			// Format time
			$time_format = $now->format( 'H:i' );
			
			$output = sprintf(
				'<span class="wta-inline-city-time"><strong>%s:</strong> %s (%s)</span>',
				esc_html( $atts['city'] ),
				esc_html( $time_format ),
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
		$parent_name = get_the_title( $post->ID );
		
		// Determine child type
		$child_type = '';
		$child_type_plural = '';
		
		if ( 'continent' === $parent_type ) {
			$child_type = 'land';
			$child_type_plural = 'lande';
		} elseif ( 'country' === $parent_type ) {
			$child_type = 'by';
			$child_type_plural = 'byer';
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
				'I %s finder du %d store %s. Klik på en by for at se aktuel tid og tidszoner.',
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
		$output .= '<div class="wta-plugin-locations-grid wta-child-list">' . "\n";
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

