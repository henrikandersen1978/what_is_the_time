<?php
/**
 * Template loader for frontend.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

class WTA_Template_Loader {

	/**
	 * Cached language templates.
	 *
	 * @since    3.2.1
	 * @var      array|null
	 */
	private static $templates_cache = null;

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
		
		// Append FAQ schema after content (v2.35.40)
		add_filter( 'the_content', array( $this, 'append_faq_schema' ), 20 );
	}

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
	
	// v3.0.61: Auto-populate country GPS and timezone EVERY page view (cache-independent)
	// v3.0.62: Also check for missing timezone_primary (countries may have GPS but no timezone)
	if ( 'country' === $type ) {
		$current_lat = get_post_meta( $post_id, 'wta_latitude', true );
		$current_lon = get_post_meta( $post_id, 'wta_longitude', true );
		$timezone_primary = get_post_meta( $post_id, 'wta_timezone_primary', true );
		
		// Calculate GPS and timezone if EITHER is missing
		if ( empty( $current_lat ) || empty( $current_lon ) || empty( $timezone_primary ) ) {
			$this->populate_country_gps_timezone( $post_id );
		}
	}
	
	// v3.0.59: Use primary timezone if available (from largest city for complex countries)
	$timezone = get_post_meta( $post_id, 'wta_timezone_primary', true );
	if ( empty( $timezone ) ) {
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );
	}
		
		$navigation_html = '';
		
		// Build breadcrumb
		$breadcrumb_items = array();
		$breadcrumb_items[] = array(
			'name' => self::get_template( 'breadcrumb_home' ) ?: 'Forside',
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
			$diff_text = sprintf( self::get_template( 'hours_ahead' ) ?: '%s timer foran %s', $hours_formatted, $base_country );
		} elseif ( $hours_diff < 0 ) {
			$diff_text = sprintf( self::get_template( 'hours_behind' ) ?: '%s timer bagud for %s', $hours_formatted, $base_country );
		} else {
			$diff_text = sprintf( self::get_template( 'same_time_as' ) ?: 'Samme tid som %s', $base_country );
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
				$dst_text = $dst_active ? ( self::get_template( 'dst_active' ) ?: 'Sommertid er aktiv' ) : ( self::get_template( 'standard_time_active' ) ?: 'Vintertid (normaltid) er aktiv' );
				
				// Next transition
				$next_transition = $transitions[1];
				$change_type = $dst_active ? ( self::get_template( 'standard_time_starts' ) ?: 'Vintertid starter' ) : ( self::get_template( 'dst_starts' ) ?: 'Sommertid starter' );
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
		$lng = get_post_meta( $post_id, 'wta_longitude', true );
		$hemisphere_text = '';
		$season_text = '';
		$gps_text = '';
		
		if ( ! empty( $lat ) && ! empty( $lng ) ) {
			$lat = floatval( $lat );
			$lng = floatval( $lng );
			$hemisphere = $lat > 0 ? 'nordlige' : 'sydlige';
			$hemisphere_text = sprintf( '%s ligger på den %s halvkugle', $name_local, $hemisphere );
			
			// Format GPS coordinates (degrees and minutes)
			$lat_abs = abs( $lat );
			$lat_deg = floor( $lat_abs );
			$lat_min = round( ( $lat_abs - $lat_deg ) * 60, 1 );
			$lat_dir = $lat >= 0 ? 'N' : 'S';
			
			$lng_abs = abs( $lng );
			$lng_deg = floor( $lng_abs );
			$lng_min = round( ( $lng_abs - $lng_deg ) * 60, 1 );
			$lng_dir = $lng >= 0 ? 'Ø' : 'V';
			
		$gps_text = sprintf(
			'Den geografiske placering er %d° %.1f\' %s %d° %.1f\' %s',
			$lat_deg,
			$lat_min,
			$lat_dir,
			$lng_deg,
			$lng_min,
			$lng_dir
		);
		
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
	} elseif ( ! empty( $lat ) ) {
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
	
	// Calculate sunrise/sunset using PHP's built-in function
	$sun_text = '';
	if ( ! empty( $lat ) && ! empty( $lng ) ) {
		try {
			$sun_info = date_sun_info( time(), $lat, $lng );
			
			// Check for polar regions (Arctic Circle: 66.56°N, Antarctic Circle: -66.56°S)
			$is_polar_region = ( abs( $lat ) > 66.56 );
			
			if ( $sun_info && isset( $sun_info['sunrise'] ) && isset( $sun_info['sunset'] ) ) {
				// Check for valid sunrise/sunset times (not false or 0)
				$sunrise_valid = ( $sun_info['sunrise'] !== false && $sun_info['sunrise'] > 0 );
				$sunset_valid = ( $sun_info['sunset'] !== false && $sun_info['sunset'] > 0 );
				
				if ( $sunrise_valid && $sunset_valid ) {
					// Format times in the location's timezone
					$sunrise_time = new DateTime( '@' . $sun_info['sunrise'] );
					$sunset_time = new DateTime( '@' . $sun_info['sunset'] );
					$sunrise_time->setTimezone( $city_tz );
					$sunset_time->setTimezone( $city_tz );
					
					// Calculate day length
					$day_length_seconds = $sun_info['sunset'] - $sun_info['sunrise'];
					$hours = floor( $day_length_seconds / 3600 );
					$minutes = floor( ( $day_length_seconds % 3600 ) / 60 );
					
					$sun_text = sprintf(
						'Solopgang: %s, Solnedgang: %s, Dagens længde: %02d:%02d',
						$sunrise_time->format( 'H:i' ),
						$sunset_time->format( 'H:i' ),
						$hours,
						$minutes
					);
				} elseif ( $is_polar_region ) {
					// Polar region with no valid sunrise/sunset
					$month = intval( $now->format( 'n' ) );
					$is_northern = ( $lat > 0 );
					
					// Determine if it's polar night or midnight sun based on hemisphere and season
					if ( $is_northern ) {
						// Northern hemisphere: polar night in winter (Nov-Jan), midnight sun in summer (May-Jul)
						if ( in_array( $month, array( 11, 12, 1 ) ) ) {
							$sun_text = 'Mørketid (polarnatt) - ingen solopgang i denne periode';
						} elseif ( in_array( $month, array( 5, 6, 7 ) ) ) {
							$sun_text = 'Midnatssol - solen går ikke ned i denne periode';
						} else {
							$sun_text = 'Ekstreme lysforhold på grund af polarregion';
						}
					} else {
						// Southern hemisphere: reversed seasons
						if ( in_array( $month, array( 5, 6, 7 ) ) ) {
							$sun_text = 'Mørketid (polarnatt) - ingen solopgang i denne periode';
						} elseif ( in_array( $month, array( 11, 12, 1 ) ) ) {
							$sun_text = 'Midnatssol - solen går ikke ned i denne periode';
						} else {
							$sun_text = 'Ekstreme lysforhold på grund af polarregion';
						}
					}
				}
			} elseif ( $is_polar_region ) {
				// Fallback for polar regions when date_sun_info returns no data
				$sun_text = 'Ekstreme lysforhold på grund af polarregion (over polarcirklen)';
			}
		} catch ( Exception $e ) {
			// Silently handle any errors in sun calculation without breaking the entire display
			if ( abs( $lat ) > 66.56 ) {
				$sun_text = 'Soldata ikke tilgængelig (polarregion)';
			}
		}
	}
	
	// Calculate moon phase using PHP
	$moon_text = '';
	$known_new_moon = strtotime('2024-12-01 06:21:00'); // Known new moon reference
	$current_time = time();
	$days_since = ($current_time - $known_new_moon) / 86400;
	$moon_cycle = 29.530588; // Average lunar cycle in days
	$phase_days = fmod($days_since, $moon_cycle);
	
	// Calculate illumination percentage
	$illumination = (1 - cos($phase_days / $moon_cycle * 2 * M_PI)) / 2 * 100;
	
	// Determine if waxing (tiltagende) or waning (aftagende)
	$is_waxing = $phase_days < ($moon_cycle / 2);
	
	// Determine phase name based on illumination percentage and direction
	$phase_name = '';
	if ($illumination < 5) {
		$phase_name = 'Nymåne';
	} elseif ($illumination < 45) {
		$phase_name = $is_waxing ? 'Tiltagende månesejl' : 'Aftagende månesejl';
	} elseif ($illumination < 55) {
		$phase_name = $is_waxing ? 'Første kvarter' : 'Sidste kvarter';
	} elseif ($illumination < 95) {
		$phase_name = $is_waxing ? 'Tiltagende måne' : 'Aftagende måne';
	} else {
		$phase_name = 'Fuldmåne';
	}
	
	$moon_text = sprintf(
		'Månefase: %.1f%% (%s)',
		$illumination,
		$phase_name
	);
	
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
	if ( ! empty( $gps_text ) ) {
		$navigation_html .= sprintf(
			'<p class="wta-gps-statement">%s</p>',
			esc_html( $gps_text )
		);
	}
	if ( ! empty( $sun_text ) ) {
		$navigation_html .= sprintf(
			'<p class="wta-sun-statement">%s</p>',
			esc_html( $sun_text )
		);
	}
	if ( ! empty( $moon_text ) ) {
		$navigation_html .= sprintf(
			'<p class="wta-moon-statement">%s</p>',
			esc_html( $moon_text )
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
		
	} catch ( Exception $e ) {
		// Silently fail if timezone is invalid
	}
}

	// Add Place/Country/Continent schema as separate JSON-LD (simple and reliable method)
	// NOTE: This is OUTSIDE the timezone check so it works for continents and countries too
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
			$parent_name = get_post_meta( $parent_id, 'wta_name_local', true ); // v3.0.0 - renamed
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
		
	// Add sameAs link for schema (v3.0.0 - prioritize GeoNames over Wikidata)
	$geonames_id = get_post_meta( $post_id, 'wta_geonames_id', true );
	
	if ( ! empty( $geonames_id ) ) {
		$place_schema['sameAs'] = 'https://www.geonames.org/' . $geonames_id;
	} elseif ( ! empty( $wikidata_id ) ) {
		// Fallback to Wikidata (for old data or special cases)
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
		$parent_name = get_post_meta( $parent_id, 'wta_name_local', true ); // v3.0.0 - renamed
			
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
				$diff_text = sprintf( self::get_template( 'hours_ahead' ) ?: '%s timer foran %s', $hours_formatted, $base_country );
			} elseif ( $hours_diff < 0 ) {
				$diff_text = sprintf( self::get_template( 'hours_behind' ) ?: '%s timer bagud for %s', $hours_formatted, $base_country );
			} else {
				$diff_text = sprintf( self::get_template( 'same_time_as' ) ?: 'Samme tid som %s', $base_country );
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
		
		// v3.0.30: For continents/countries, extract ALL intro paragraphs (before first heading/shortcode)
		$intro_html = '';
		$remaining_content = $content;
		
		if ( in_array( $type, array( 'continent', 'country' ) ) ) {
			// Extract ALL intro paragraphs up to first <h2> or [shortcode]
			// This handles both template content (1 paragraph) and AI content (2-3 paragraphs)
			$h2_pos = strpos( $content, '<h2>' );
			$shortcode_pos = strpos( $content, '[wta_' );
			
			// Use whichever comes first (or false if neither exists)
			$split_pos = false;
			if ( false !== $h2_pos && false !== $shortcode_pos ) {
				$split_pos = min( $h2_pos, $shortcode_pos );
			} elseif ( false !== $h2_pos ) {
				$split_pos = $h2_pos;
			} elseif ( false !== $shortcode_pos ) {
				$split_pos = $shortcode_pos;
			}
			
			if ( false !== $split_pos ) {
				// Extract ALL intro content (all paragraphs before first heading/shortcode)
				$intro_html = '<div class="wta-intro-section">' . trim( substr( $content, 0, $split_pos ) ) . '</div>';
				// Remaining content (from heading/shortcode onwards)
				$remaining_content = substr( $content, $split_pos );
			}
		}
		
		// Output quick navigation (after intro for continents/countries)
		if ( $has_children || $has_major_cities || $has_nearby_sections ) {
			$navigation_html .= $intro_html; // Add intro before buttons
			$navigation_html .= '<div class="wta-quick-nav">';
			
			if ( $has_children ) {
				$child_label = ( 'continent' === $type ) ? ( self::get_template( 'btn_see_all_countries' ) ?: '📍 Se alle lande' ) : ( self::get_template( 'btn_see_all_places' ) ?: '📍 Se alle steder' );
				$navigation_html .= '<a href="#child-locations" class="wta-quick-nav-btn wta-smooth-scroll">';
				$navigation_html .= esc_html( $child_label );
				$navigation_html .= '</a>';
			}
			
			if ( $has_major_cities ) {
				$navigation_html .= '<a href="#major-cities" class="wta-quick-nav-btn wta-smooth-scroll">';
				$navigation_html .= esc_html( self::get_template( 'btn_live_times' ) ?: '🕐 Live tidspunkter' );
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
		
		// Build final output: breadcrumb + intro + buttons + remaining content
		return $navigation_html . $remaining_content;
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
	 * @since    2.32.2 Always enqueue on frontend to support shortcodes in widgets/builders
	 * @since    2.33.1 Added flag-icons library for universal flag emoji support
	 */
	public function enqueue_styles() {
		// Always enqueue on frontend (not admin) to support shortcodes anywhere
		// CSS is lightweight (~20KB) and ensures shortcodes work in widgets, builders, etc.
		if ( ! is_admin() ) {
			// Enqueue flag-icons library for country flags (works in all browsers)
			wp_enqueue_style(
				'flag-icons',
				'https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css',
				array(),
				'7.2.3'
			);
			
			wp_enqueue_style(
				'wta-frontend',
				WTA_PLUGIN_URL . 'includes/frontend/assets/css/frontend.css',
				array( 'flag-icons' ),
				WTA_VERSION
			);
		}
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since    2.0.0
	 * @since    2.32.2 Always enqueue on frontend to support shortcodes in widgets/builders
	 */
	public function enqueue_scripts() {
		// Always enqueue on frontend (not admin) to support shortcodes anywhere
		// JS is lightweight (~15KB) and ensures clocks work in widgets, builders, etc.
		if ( ! is_admin() ) {
			wp_enqueue_script(
				'wta-clock',
				WTA_PLUGIN_URL . 'includes/frontend/assets/js/clock.js',
				array(),
				WTA_VERSION,
				true
			);
			
			// FAQ accordion script for city pages (v2.35.0)
			if ( is_singular( WTA_POST_TYPE ) ) {
				$post_id = get_the_ID();
				$type = get_post_meta( $post_id, 'wta_type', true );
				
				if ( 'city' === $type ) {
					wp_enqueue_script(
						'wta-faq-accordion',
						WTA_PLUGIN_URL . 'includes/frontend/assets/js/faq-accordion.js',
						array(),
						WTA_VERSION,
						true
					);
				}
				
				// v3.0.20: JavaScript H1 hack removed
				// H1 now set correctly server-side in template using _pilanto_page_h1 meta
			}
		}
	}
	
	/**
	 * Append FAQ JSON-LD schema to city content.
	 *
	 * Schema is NOT saved in database - generated dynamically on page load.
	 * This prevents WordPress from escaping/stripping <script> tags.
	 * Same pattern as breadcrumb schema in single template.
	 *
	 * @since    2.35.40
	 * @param    string $content Post content.
	 * @return   string          Content with FAQ schema appended.
	 */
	public function append_faq_schema( $content ) {
		// Only on single location pages (simplified check for theme template compatibility)
		// v3.0.27: Removed in_the_loop() and is_main_query() checks for theme template compatibility
		if ( ! is_singular( WTA_POST_TYPE ) ) {
			return $content;
		}
		
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}
		
		$type = get_post_meta( $post_id, 'wta_type', true );
		
		// Only for cities
		if ( 'city' !== $type ) {
			return $content;
		}
		
		// Get FAQ data
		$faq_data = get_post_meta( $post_id, 'wta_faq_data', true );
		
		if ( empty( $faq_data ) || ! isset( $faq_data['faqs'] ) || empty( $faq_data['faqs'] ) ) {
			return $content;
		}
		
		// Generate and append FAQ schema
		// v3.0.20: Use get_post_field() to bypass the_title filter
		// This ensures FAQ schema uses page title (e.g., "København")
		// not H1 title (e.g., "Hvad er klokken i København, Danmark?")
		$city_name = get_post_field( 'post_title', $post_id );
		$content .= WTA_FAQ_Renderer::generate_faq_schema_tag( $faq_data, $city_name );
		
		return $content;
	}

	/**
	 * Auto-populate country GPS and timezone from cities.
	 * 
	 * v3.0.61: Runs on every country page view (cache-independent)
	 *
	 * @since    3.0.61
	 * @param    int $country_id Country post ID.
	 */
	private function populate_country_gps_timezone( $country_id ) {
		global $wpdb;
		
		// Get geographic center (average of all cities)
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
			return; // No cities yet
		}
		
		// Calculate geographic center (simple average)
		$total_lat = 0;
		$total_lon = 0;
		$count = count( $cities );
		
		foreach ( $cities as $city ) {
			$total_lat += floatval( $city->lat );
			$total_lon += floatval( $city->lon );
		}
		
		$center_lat = $total_lat / $count;
		$center_lon = $total_lon / $count;
		
		// Get timezone from largest city
		$largest_city_tz = $wpdb->get_var( $wpdb->prepare(
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
		
		// Cache GPS and timezone
		update_post_meta( $country_id, 'wta_latitude', $center_lat );
		update_post_meta( $country_id, 'wta_longitude', $center_lon );
		
		if ( ! empty( $largest_city_tz ) ) {
			update_post_meta( $country_id, 'wta_timezone_primary', $largest_city_tz );
		}
	}
}


