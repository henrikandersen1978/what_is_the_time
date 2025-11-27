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
		
		// Build output
		$output = '';
		
		// Heading
		$output .= '<h2>' . ucfirst( $child_type_plural ) . ' i ' . esc_html( $parent_name ) . '</h2>' . "\n";
		
		// Intro text
		$intro = sprintf(
			'%s består af %d %s med forskellige tidszoner. Klik på %s %s for at se den nøjagtige tid og tidszone information.',
			$parent_name,
			$count,
			$child_type_plural,
			$count === 1 ? $child_type : 'et ' . $child_type,
			$count === 1 ? '' : 'eller by'
		);
		
		// Simplify intro based on type
		if ( 'continent' === $parent_type ) {
			$intro = sprintf(
				'%s består af %d %s med forskellige tidszoner. Klik på et land for at se aktuel tid og tidszoner.',
				$parent_name,
				$count,
				$child_type_plural
			);
		} elseif ( 'country' === $parent_type ) {
			$intro = sprintf(
				'Se hvad klokken er i de største byer i %s. Klik på en by for at få aktuel tid og detaljeret information.',
				$parent_name
			);
		}
		
		$output .= '<p>' . esc_html( $intro ) . '</p>' . "\n";
		
		// Locations grid
		$output .= '<div class="wta-locations-grid">' . "\n";
		$output .= '<ul>' . "\n";
		
		foreach ( $children as $child ) {
			$output .= '<li><a href="' . esc_url( get_permalink( $child->ID ) ) . '">';
			$output .= esc_html( get_the_title( $child->ID ) );
			$output .= '</a></li>' . "\n";
		}
		
		$output .= '</ul>' . "\n";
		$output .= '</div>' . "\n";
		
		return $output;
	}
}

