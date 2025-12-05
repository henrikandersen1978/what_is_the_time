<?php
/**
 * Wikidata-based translation helper for location names.
 *
 * Uses Wikidata API to fetch official localized names for cities and countries.
 * Provides 100% accurate translations maintained by Wikipedia community.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

class WTA_Wikidata_Translator {

	/**
	 * Get localized name from Wikidata.
	 *
	 * Uses Wikidata Q-ID to fetch official name in target language.
	 * Results are cached in WordPress transients for 1 year.
	 *
	 * @since    2.11.0
	 * @param    string $wikidata_id Wikidata Q-ID (e.g., "Q1748").
	 * @param    string $target_lang Target language code (e.g., 'da').
	 * @return   string|false        Localized name or false on failure.
	 */
	public static function get_label( $wikidata_id, $target_lang = 'da' ) {
		// Validate Wikidata ID format
		if ( empty( $wikidata_id ) || ! preg_match( '/^Q\d+$/', $wikidata_id ) ) {
			return false;
		}

		// Generate cache key
		$cache_key = 'wta_wikidata_' . $wikidata_id . '_' . $target_lang;

		// Check cache first
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			// Return cached value (can be false if previous attempt failed)
			return $cached === '__NOTFOUND__' ? false : $cached;
		}

		// Call Wikidata API
		$url = sprintf(
			'https://www.wikidata.org/wiki/Special:EntityData/%s.json',
			sanitize_text_field( $wikidata_id )
		);

		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'User-Agent' => 'WorldTimeAI WordPress Plugin/2.11.0',
			),
		) );

		if ( is_wp_error( $response ) ) {
			WTA_Logger::warning( 'Wikidata API request failed', array(
				'wikidata_id' => $wikidata_id,
				'error'       => $response->get_error_message(),
			) );
			// Cache failure for 1 day to avoid repeated failed requests
			set_transient( $cache_key, '__NOTFOUND__', DAY_IN_SECONDS );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			WTA_Logger::warning( 'Wikidata API returned non-200 status', array(
				'wikidata_id' => $wikidata_id,
				'status_code' => $status_code,
			) );
			// Cache failure for 1 day
			set_transient( $cache_key, '__NOTFOUND__', DAY_IN_SECONDS );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['entities'][ $wikidata_id ]['labels'][ $target_lang ]['value'] ) ) {
			// No translation in target language - this is OK for small towns
			WTA_Logger::debug( 'Wikidata: No label in target language', array(
				'wikidata_id'  => $wikidata_id,
				'target_lang'  => $target_lang,
			) );
			// Cache failure for 30 days (longer because missing translations rarely get added)
			set_transient( $cache_key, '__NOTFOUND__', 30 * DAY_IN_SECONDS );
			return false;
		}

	$label = $data['entities'][ $wikidata_id ]['labels'][ $target_lang ]['value'];
	$original_label = $label; // Store for debugging
	
	// DEBUG: Log what Wikidata actually returns (before any cleaning)
	WTA_Logger::debug( 'Wikidata raw label received', array(
		'wikidata_id' => $wikidata_id,
		'raw_label'   => $label,
		'lang'        => $target_lang,
	) );

	// Validate label
	if ( empty( $label ) || strlen( $label ) > 200 ) {
		WTA_Logger::warning( 'Wikidata label validation failed', array(
			'wikidata_id' => $wikidata_id,
			'label'       => $label,
		) );
		// Cache failure for 1 day
		set_transient( $cache_key, '__NOTFOUND__', DAY_IN_SECONDS );
		return false;
	}

	// Clean label (remove unexpected formatting)
	$label = trim( $label );
	
	// Remove administrative suffixes from city names
	// Wikidata often includes "Kommune", "Municipality", etc. in localized labels
	// We want clean city names without these administrative designations
	$admin_suffixes = array(
		// Nordic languages
		' kommune',          // Danish/Norwegian
		' kommun',           // Swedish
		' kunta',            // Finnish
		' kommuna',          // Icelandic
		' kummuna',          // Maltese
		
		// English
		' municipality',
		' city',
		' county',
		' district',
		' province',
		' state',
		' borough',
		' township',
		' region',
		
		// Spanish/Portuguese
		' municipio',
		' município',
		' departamento',
		' provincia',
		' concelho',
		
		// German/Austrian
		' gemeinde',
		' landkreis',
		' kreis',
		' bezirk',
		' stadtkreis',
		
		// French/Belgian
		' commune',
		' arrondissement',
		' canton',
		' département',
		
		// Italian
		' comune',
		' provincia',
		
		// Eastern European
		' oblast',           // Russian/Ukrainian
		' rayon',            // Russian/Azerbaijani
		' gmina',            // Polish
		' powiat',           // Polish
		' voivodeship',      // Polish
		' okrug',            // Russian
		
		// Asian
		' prefecture',       // Japanese
		' governorate',      // Arabic
		' shi',              // Chinese/Japanese (city)
		' gun',              // Korean (county)
		
		// Other patterns
		' urban area',
		' metropolitan area',
		' metro area',
		' area',
	);
	
	// LAG 2: Remove administrative terms from ANYWHERE in Wikidata label
	// More robust than suffix-only removal - handles "Kommune Oslo", "Oslo kommune", etc.
	// Normalize suffixes (remove leading space, convert to array of terms)
	$admin_terms = array();
	foreach ( $admin_suffixes as $suffix ) {
		$admin_terms[] = trim( $suffix ); // Remove leading space
	}
	
	// Sort by length (longest first) to avoid partial matches
	usort( $admin_terms, function( $a, $b ) {
		return mb_strlen( $b, 'UTF-8' ) - mb_strlen( $a, 'UTF-8' );
	});
	
	$suffix_removed = false;
	$removed_terms = array();
	$max_iterations = 3; // Allow removing up to 3 terms
	
	for ( $i = 0; $i < $max_iterations; $i++ ) {
		$found = false;
		$label_lower = mb_strtolower( $label, 'UTF-8' );
		
		foreach ( $admin_terms as $term ) {
			$term_lower = mb_strtolower( $term, 'UTF-8' );
			
			// Search for the term anywhere in the string (case-insensitive)
			$pos = mb_strpos( $label_lower, $term_lower, 0, 'UTF-8' );
			
			if ( $pos !== false ) {
				// Found the term! Remove it from original label (preserve case of city name)
				$before = mb_substr( $label, 0, $pos, 'UTF-8' );
				$after = mb_substr( $label, $pos + mb_strlen( $term, 'UTF-8' ), null, 'UTF-8' );
				
				// Combine and clean up extra spaces
				$label = trim( $before . ' ' . $after );
				$label = preg_replace( '/\s+/', ' ', $label ); // Multiple spaces → single space
				
				$suffix_removed = true;
				$removed_terms[] = $term;
				$found = true;
				break; // Try again with cleaned label
			}
		}
		
		if ( ! $found ) {
			break; // No more terms found
		}
	}
	
	$removed_suffix = implode( ', ', $removed_terms );
	
	$label = trim( $label );
	
	// DEBUG: Enhanced logging for suffix removal
	if ( $suffix_removed ) {
		WTA_Logger::info( 'Wikidata suffix removed', array(
			'wikidata_id' => $wikidata_id,
			'original'    => $original_label,
			'cleaned'     => $label,
			'suffix'      => $removed_suffix,
			'lang'        => $target_lang,
		) );
	}
	
	// Log if "kommune" or other admin terms are still present after cleaning
	$admin_check_terms = array( 'kommune', 'kommun', 'municipality', 'commune', 'municipio', 'município' );
	$label_check = mb_strtolower( $label, 'UTF-8' );
	
	foreach ( $admin_check_terms as $term ) {
		if ( strpos( $label_check, $term ) !== false ) {
			WTA_Logger::warning( 'Wikidata: Admin term still present after cleaning', array(
				'wikidata_id'  => $wikidata_id,
				'original'     => $original_label,
				'cleaned'      => $label,
				'term_found'   => $term,
				'lang'         => $target_lang,
			) );
			break;
		}
	}

		// Cache successful result for 1 year
		set_transient( $cache_key, $label, YEAR_IN_SECONDS );

		WTA_Logger::info( 'Wikidata translation successful', array(
			'wikidata_id' => $wikidata_id,
			'label'       => $label,
			'lang'        => $target_lang,
		) );

		return $label;
	}

	/**
	 * Batch translate multiple locations from Wikidata.
	 *
	 * Optimized for translating multiple items with delay between API calls.
	 *
	 * @since    2.11.0
	 * @param    array  $items       Array of items with 'wikidata_id'.
	 * @param    string $target_lang Target language code.
	 * @return   array               Array of translations keyed by wikidata_id.
	 */
	public static function batch_translate( $items, $target_lang = 'da' ) {
		$translations = array();

		foreach ( $items as $item ) {
			$wikidata_id = $item['wikidata_id'];

			$translated = self::get_label( $wikidata_id, $target_lang );
			$translations[ $wikidata_id ] = $translated;

			// Small delay between API calls to respect rate limits
			if ( false !== $translated ) {
				usleep( 100000 ); // 100ms
			}
		}

		return $translations;
	}

	/**
	 * Clear translation cache.
	 *
	 * Useful when forcing re-translation or changing language.
	 *
	 * @since    2.11.0
	 * @param    string $wikidata_id Optional. Clear cache for specific ID.
	 * @param    string $target_lang Optional. Target language.
	 * @return   bool                True if cache was cleared.
	 */
	public static function clear_cache( $wikidata_id = null, $target_lang = null ) {
		global $wpdb;

		if ( null !== $wikidata_id ) {
			// Clear specific translation
			if ( null === $target_lang ) {
				$target_lang = 'da';
			}
			$cache_key = 'wta_wikidata_' . $wikidata_id . '_' . $target_lang;
			return delete_transient( $cache_key );
		}

		// Clear all Wikidata translation transients
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_wta_wikidata_%' 
			OR option_name LIKE '_transient_timeout_wta_wikidata_%'"
		);

		WTA_Logger::info( 'Wikidata translation cache cleared' );
		return true;
	}

	/**
	 * Get statistics about Wikidata cache.
	 *
	 * @since    2.11.0
	 * @return   array Statistics array.
	 */
	public static function get_cache_stats() {
		global $wpdb;

		$total = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_wta_wikidata_%'"
		);

		$not_found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_value = %s",
				'_transient_wta_wikidata_%',
				'__NOTFOUND__'
			)
		);

		return array(
			'total'     => (int) $total,
			'found'     => (int) $total - (int) $not_found,
			'not_found' => (int) $not_found,
		);
	}
}



