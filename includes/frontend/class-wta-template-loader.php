<?php
/**
 * Template loader for frontend.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

class WTA_Template_Loader {

	/**
	 * Constructor.
	 *
	 * @since    2.8.4
	 */
	public function __construct() {
		// Filter title for location posts to show custom H1
		add_filter( 'the_title', array( $this, 'filter_location_title' ), 10, 2 );
		
		// Inject breadcrumb and quick nav before content
		add_filter( 'the_content', array( $this, 'inject_navigation' ), 5 );
	}

	/**
	 * Filter post title to show custom H1 for location posts.
	 *
	 * Only affects wta_location posts when displayed as H1 in single post view.
	 *
	 * @since    2.8.4
	 * @param    string $title   Post title.
	 * @param    int    $post_id Post ID.
	 * @return   string          Modified title.
	 */
	public function filter_location_title( $title, $post_id ) {
		// Only for location posts
		if ( get_post_type( $post_id ) !== WTA_POST_TYPE ) {
			return $title;
		}
		
		// Only in singular view (not in loops, menus, etc.)
		if ( ! is_singular( WTA_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}
		
		// Get custom H1 if it exists
		$custom_h1 = get_post_meta( $post_id, '_pilanto_page_h1', true );
		
		if ( ! empty( $custom_h1 ) ) {
			return $custom_h1;
		}
		
		return $title;
	}

	/**
	 * Inject breadcrumb navigation and quick nav before content.
	 *
	 * @since    2.18.1
	 * @param    string $content Post content.
	 * @return   string          Modified content with navigation.
	 */
	public function inject_navigation( $content ) {
		// Only for location posts in singular view
		if ( ! is_singular( WTA_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		
	$post_id = get_the_ID();
	$type = get_post_meta( $post_id, 'wta_type', true );
	$name_local = get_post_field( 'post_title', $post_id ); // Get original title, not SEO H1
	$timezone = get_post_meta( $post_id, 'wta_timezone', true );
		
		$navigation_html = '';
		
		// Build breadcrumb
		$breadcrumb_items = array();
		$breadcrumb_items[] = array(
			'name' => 'Forside',
			'url'  => home_url( '/' ),
		);
		
		// Add parent hierarchy
		$ancestors = array();
		$parent_id = wp_get_post_parent_id( $post_id );
		while ( $parent_id ) {
			$ancestors[] = $parent_id;
			$parent_id = wp_get_post_parent_id( $parent_id );
		}
		$ancestors = array_reverse( $ancestors );
		
		foreach ( $ancestors as $ancestor_id ) {
			$breadcrumb_items[] = array(
				'name' => get_post_field( 'post_title', $ancestor_id ), // Use simple title, not SEO H1
				'url'  => get_permalink( $ancestor_id ),
			);
		}
		
		// Add current page (use simple title, not SEO H1)
		$breadcrumb_items[] = array(
			'name' => get_post_field( 'post_title', $post_id ),
			'url'  => get_permalink( $post_id ),
		);
		
		// Output breadcrumb
		if ( count( $breadcrumb_items ) > 1 ) {
			$navigation_html .= '<nav class="wta-breadcrumb" aria-label="Breadcrumb">';
			$navigation_html .= '<ol class="wta-breadcrumb-list">';
			
			foreach ( $breadcrumb_items as $index => $item ) {
				if ( $index === count( $breadcrumb_items ) - 1 ) {
					$navigation_html .= '<li class="wta-breadcrumb-item wta-breadcrumb-current" aria-current="page">';
					$navigation_html .= '<span>' . esc_html( $item['name'] ) . '</span>';
					$navigation_html .= '</li>';
				} else {
					$navigation_html .= '<li class="wta-breadcrumb-item">';
					$navigation_html .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
					$navigation_html .= '</li>';
				}
			}
			
		$navigation_html .= '</ol>';
		$navigation_html .= '</nav>';
		
		// Note: BreadcrumbList schema is handled by Yoast SEO for better integration
	}
	
	// Add Direct Answer section for SEO (Featured Snippet optimization)
	if ( ! empty( $timezone ) && 'multiple' !== $timezone ) {
		// Calculate time difference to base country
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
		
		// Get UTC offset for display
		$utc_offset_seconds = $city_tz->getOffset( $now );
		$utc_offset_hours = $utc_offset_seconds / 3600;
		$offset_formatted = sprintf( 'UTC%+d', $utc_offset_hours );
		
		// Determine DST status and next change
		$dst_active = false;
		$dst_text = '';
		$next_dst_text = '';
		try {
			$transitions = $city_tz->getTransitions( time(), time() + ( 180 * 86400 ) ); // Next 180 days
			if ( count( $transitions ) > 1 ) {
				$dst_active = $transitions[0]['isdst'];
				$dst_text = $dst_active ? 'Sommertid er aktiv' : 'Vintertid (normaltid) er aktiv';
				
				// Next transition
				$next_transition = $transitions[1];
				$change_type = $dst_active ? 'Vintertid starter' : 'Sommertid starter';
				$next_dst_text = sprintf(
					'%s: %s',
					$change_type,
					date_i18n( 'l j. F Y \k\l. H:i', $next_transition['ts'] )
				);
			}
		} catch ( Exception $e ) {
			// Ignore if DST info not available
		}
		
		// Determine hemisphere and season
		$lat = get_post_meta( $post_id, 'wta_latitude', true );
		$hemisphere_text = '';
		$season_text = '';
		if ( ! empty( $lat ) ) {
			$lat = floatval( $lat );
			$hemisphere = $lat > 0 ? 'nordlige' : 'sydlige';
			$hemisphere_text = sprintf( '%s ligger på den %s halvkugle', $name_local, $hemisphere );
			
			// Determine season based on month and hemisphere
			$month = intval( $now->format( 'n' ) );
			if ( $lat > 0 ) {
				// Northern hemisphere
				if ( in_array( $month, array( 12, 1, 2 ) ) ) {
					$season = 'vinter';
				} elseif ( in_array( $month, array( 3, 4, 5 ) ) ) {
					$season = 'forår';
				} elseif ( in_array( $month, array( 6, 7, 8 ) ) ) {
					$season = 'sommer';
				} else {
					$season = 'efterår';
				}
			} else {
				// Southern hemisphere (seasons reversed)
				if ( in_array( $month, array( 12, 1, 2 ) ) ) {
					$season = 'sommer';
				} elseif ( in_array( $month, array( 3, 4, 5 ) ) ) {
					$season = 'efterår';
				} elseif ( in_array( $month, array( 6, 7, 8 ) ) ) {
					$season = 'vinter';
				} else {
					$season = 'forår';
				}
			}
			$season_text = 'Nuværende sæson: ' . ucfirst( $season );
		}
		
		// Build Direct Answer HTML
		$navigation_html .= '<div class="wta-seo-direct-answer">';
		$navigation_html .= sprintf(
			'<p class="wta-current-time-statement"><strong>Den aktuelle tid i %s er <span class="wta-live-time" data-timezone="%s">%s</span></strong></p>',
			esc_html( $name_local ),
			esc_attr( $timezone ),
			$now->format( 'H:i:s' )
		);
		$navigation_html .= sprintf(
			'<p class="wta-current-date-statement">Datoen er <span class="wta-live-date" data-timezone="%s">%s</span></p>',
			esc_attr( $timezone ),
			$now->format( 'l j. F Y' )
		);
		$navigation_html .= sprintf(
			'<p class="wta-timezone-statement">Tidszone: <span class="wta-timezone-name">%s (%s)</span></p>',
			esc_html( $timezone ),
			esc_html( $offset_formatted )
		);
		if ( ! empty( $diff_text ) ) {
			$navigation_html .= sprintf(
				'<p class="wta-time-diff-statement">%s</p>',
				esc_html( $diff_text )
			);
		}
		if ( ! empty( $dst_text ) ) {
			$navigation_html .= sprintf(
				'<p class="wta-dst-statement">%s</p>',
				esc_html( $dst_text )
			);
		}
		if ( ! empty( $next_dst_text ) ) {
			$navigation_html .= sprintf(
				'<p class="wta-dst-change">%s</p>',
				esc_html( $next_dst_text )
			);
		}
		if ( ! empty( $hemisphere_text ) ) {
			$navigation_html .= sprintf(
				'<p class="wta-hemisphere-statement">%s</p>',
				esc_html( $hemisphere_text )
			);
		}
		if ( ! empty( $season_text ) ) {
			$navigation_html .= sprintf(
				'<p class="wta-season-statement">%s</p>',
				esc_html( $season_text )
			);
		}
		$navigation_html .= '</div>';
		
		// Add Place/Country/Continent schema as separate JSON-LD (simple and reliable method)
		$lat = get_post_meta( $post_id, 'wta_latitude', true );
		$lng = get_post_meta( $post_id, 'wta_longitude', true );
		$country_code = get_post_meta( $post_id, 'wta_country_code', true );
		$wikidata_id = get_post_meta( $post_id, 'wta_wikidata_id', true );
		
		// Determine if we should add schema (all types get schema, but GPS only for cities)
		$add_schema = true;
		
		if ( $add_schema ) {
			// Determine schema type based on location type
			$schema_type = 'Place'; // Default
			if ( 'country' === $type ) {
				$schema_type = 'Country';
			} elseif ( 'continent' === $type ) {
				$schema_type = 'Continent';
			}
			
			$place_schema = array(
				'@context' => 'https://schema.org',
				'@type'    => $schema_type,
				'name'     => $name_local,
				'url'      => get_permalink( $post_id ),
			);
			
			// Add GPS coordinates if available (typically only cities)
			if ( ! empty( $lat ) && ! empty( $lng ) ) {
				$place_schema['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => floatval( $lat ),
					'longitude' => floatval( $lng ),
				);
			}
			
			// Add description based on type
			$description = sprintf( 
				'Aktuel tid og tidszone for %s',
				$name_local
			);
			if ( 'city' === $type && ! empty( $country_code ) ) {
				// Add country name for cities
				$parent_id = wp_get_post_parent_id( $post_id );
				if ( $parent_id ) {
					$parent_name = get_post_meta( $parent_id, 'wta_name_danish', true );
					if ( ! empty( $parent_name ) ) {
						$description = sprintf( 
							'Aktuel tid og tidszone for %s, %s',
							$name_local,
							$parent_name
						);
					}
				}
			} elseif ( 'continent' === $type ) {
				$description = sprintf( 
					'Tidszoner og aktuel tid i %s',
					$name_local
				);
			}
			$place_schema['description'] = $description;
			
			// Add Wikidata link if available
			if ( ! empty( $wikidata_id ) ) {
				$place_schema['sameAs'] = 'https://www.wikidata.org/wiki/' . $wikidata_id;
			}
			
			// Note: timeZone is not a valid property for Place schema according to schema.org
			// Timezone info is displayed in the UI instead
			
			// Add address with country if available (for cities and countries)
			if ( ! empty( $country_code ) ) {
				$place_schema['address'] = array(
					'@type'          => 'PostalAddress',
					'addressCountry' => strtoupper( $country_code ),
				);
			}
			
			// Add containedInPlace for hierarchical structure
			$parent_id = wp_get_post_parent_id( $post_id );
			if ( $parent_id ) {
				$parent_type = get_post_meta( $parent_id, 'wta_type', true );
				$parent_name = get_post_meta( $parent_id, 'wta_name_danish', true );
				
				// Determine parent schema type
				$parent_schema_type = 'Place';
				if ( 'country' === $parent_type ) {
					$parent_schema_type = 'Country';
				} elseif ( 'continent' === $parent_type ) {
					$parent_schema_type = 'Continent';
				}
				
				$place_schema['containedInPlace'] = array(
					'@type' => $parent_schema_type,
					'@id'   => get_permalink( $parent_id ) . '#place',
					'name'  => ! empty( $parent_name ) ? $parent_name : get_the_title( $parent_id ),
				);
			}
			
			$navigation_html .= '<script type="application/ld+json">';
			$navigation_html .= wp_json_encode( $place_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
			$navigation_html .= '</script>';
		}
		
	} catch ( Exception $e ) {
		// Silently fail if timezone is invalid
	}
}

// Note: Large purple clock removed - all info now in Direct Answer box
	
	// Check if page has child locations or major cities (skip old clock logic)
	if ( false && 'city' === $type && ! empty( $timezone ) && 'multiple' !== $timezone ) {
			// Calculate time difference to base country
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
			} catch ( Exception $e ) {
				$diff_text = '';
				$hours_diff = 0;
			}
			
			$navigation_html .= '<div class="wta-clock-container">';
			$navigation_html .= '<div class="wta-clock" data-timezone="' . esc_attr( $timezone ) . '" data-base-offset="' . esc_attr( $hours_diff ) . '">';
			$navigation_html .= '<div class="wta-clock-time">--:--:--</div>';
			$navigation_html .= '<div class="wta-clock-date">-</div>';
			$navigation_html .= '<div class="wta-clock-timezone">' . esc_html( $timezone ) . '</div>';
			if ( ! empty( $diff_text ) ) {
				$navigation_html .= '<div class="wta-time-diff">' . esc_html( $diff_text ) . '</div>';
			}
			$navigation_html .= '</div>';
			$navigation_html .= '</div>';
		}
		
		// Check if page has child locations or major cities
		$has_children = false;
		$has_major_cities = false;
		$has_nearby_sections = false;
		
		if ( in_array( $type, array( 'continent', 'country' ) ) ) {
			$children = get_posts( array(
				'post_type'      => WTA_POST_TYPE,
				'post_parent'    => $post_id,
				'posts_per_page' => 1,
				'post_status'    => array( 'publish', 'draft' ),
			) );
			$has_children = ! empty( $children );
			$has_major_cities = true; // Continents and countries always show major cities
		} elseif ( 'city' === $type ) {
			// Cities have nearby sections
			$has_nearby_sections = true;
		}
		
		// Output quick navigation
		if ( $has_children || $has_major_cities || $has_nearby_sections ) {
			$navigation_html .= '<div class="wta-quick-nav">';
			
			if ( $has_children ) {
				$child_label = ( 'continent' === $type ) ? '📍 Se alle lande' : '📍 Se alle steder';
				$navigation_html .= '<a href="#child-locations" class="wta-quick-nav-btn wta-smooth-scroll">';
				$navigation_html .= esc_html( $child_label );
				$navigation_html .= '</a>';
			}
			
			if ( $has_major_cities ) {
				$navigation_html .= '<a href="#major-cities" class="wta-quick-nav-btn wta-smooth-scroll">';
				$navigation_html .= '🕐 Live tidspunkter';
				$navigation_html .= '</a>';
			}
			
			if ( $has_nearby_sections ) {
				$navigation_html .= '<a href="#nearby-cities" class="wta-quick-nav-btn wta-smooth-scroll">';
				$navigation_html .= '🏙️ Nærliggende byer';
				$navigation_html .= '</a>';
				$navigation_html .= '<a href="#nearby-countries" class="wta-quick-nav-btn wta-smooth-scroll">';
				$navigation_html .= '🌍 Nærliggende lande';
				$navigation_html .= '</a>';
			}
			
			$navigation_html .= '</div>';
		}
		
		// Prepend navigation to content
		return $navigation_html . $content;
	}

	/**
	 * Load custom template for location posts.
	 *
	 * @since    2.0.0
	 * @param    string $template Template path.
	 * @return   string           Modified template path.
	 */
	public function load_template( $template ) {
		if ( is_singular( WTA_POST_TYPE ) ) {
			// Use theme's page template instead of custom template
			// This ensures perfect theme compatibility
			$page_template = get_page_template();
			if ( $page_template && file_exists( $page_template ) ) {
				return $page_template;
			}
			
			// Fallback: use theme's single template
			$single_template = get_single_template();
			if ( $single_template && file_exists( $single_template ) ) {
				return $single_template;
			}
		}

		return $template;
	}

	/**
	 * Enqueue frontend styles.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_styles() {
		if ( is_singular( WTA_POST_TYPE ) || has_shortcode( get_the_content(), 'world_time_clock' ) ) {
			wp_enqueue_style(
				'wta-frontend',
				WTA_PLUGIN_URL . 'includes/frontend/assets/css/frontend.css',
				array(),
				WTA_VERSION
			);
		}
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_scripts() {
		if ( is_singular( WTA_POST_TYPE ) || has_shortcode( get_the_content(), 'world_time_clock' ) ) {
			wp_enqueue_script(
				'wta-clock',
				WTA_PLUGIN_URL . 'includes/frontend/assets/js/clock.js',
				array(),
				WTA_VERSION,
				true
			);
			
			// Add custom H1 handler for location posts
			if ( is_singular( WTA_POST_TYPE ) ) {
				$this->inject_h1_script();
			}
		}
	}
	
	/**
	 * Inject JavaScript to replace H1 with custom title.
	 *
	 * Fallback solution if theme doesn't use the_title() for H1.
	 *
	 * @since    2.8.4
	 */
	private function inject_h1_script() {
		global $post;
		
		$custom_h1 = get_post_meta( $post->ID, '_pilanto_page_h1', true );
		
		if ( empty( $custom_h1 ) ) {
			return;
		}
		
		// Escape for JavaScript
		$custom_h1_escaped = esc_js( $custom_h1 );
		$post_title_escaped = esc_js( get_the_title() );
		
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			// Find H1 that contains the post title
			var h1Elements = document.querySelectorAll('h1');
			var postTitle = <?php echo wp_json_encode( $post_title_escaped ); ?>;
			var customH1 = <?php echo wp_json_encode( $custom_h1_escaped ); ?>;
			
			h1Elements.forEach(function(h1) {
				// Check if H1 contains the post title (case-insensitive)
				if (h1.textContent.trim().toLowerCase().indexOf(postTitle.toLowerCase()) !== -1) {
					// Replace with custom H1
					h1.textContent = customH1;
				}
			});
		});
		</script>
		<?php
	}
}


