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
		
		// English
		' municipality',
		' city',
		' county',
		' district',
		' province',
		' state',
		' borough',
		' township',
		
		// Spanish/Portuguese
		' municipio',
		' município',
		' departamento',
		' provincia',
		
		// German
		' gemeinde',
		' landkreis',
		' kreis',
		
		// French
		' commune',
		' arrondissement',
		' canton',
		
		// Other
		' prefecture',
		' governorate',
		' oblast',
		' rayon',
	);
	
	// Case-insensitive removal of suffixes
	$label_lower = mb_strtolower( $label, 'UTF-8' );
	$suffix_removed = false;
	$removed_suffix = '';
	
	foreach ( $admin_suffixes as $suffix ) {
		if ( mb_substr( $label_lower, -mb_strlen( $suffix, 'UTF-8' ), null, 'UTF-8' ) === $suffix ) {
			// Remove the suffix (preserve original case for the city name part)
			$label = mb_substr( $label, 0, mb_strlen( $label, 'UTF-8' ) - mb_strlen( $suffix, 'UTF-8' ), 'UTF-8' );
			$label = trim( $label );
			$suffix_removed = true;
			$removed_suffix = $suffix;
			break; // Only remove one suffix
		}
	}
	
	$label = trim( $label );
	
	// DEBUG: Log suffix removal for Norwegian cities
	if ( $suffix_removed ) {
		WTA_Logger::info( 'Wikidata suffix removed', array(
			'wikidata_id' => $wikidata_id,
			'original'    => $original_label,
			'cleaned'     => $label,
			'suffix'      => $removed_suffix,
			'lang'        => $target_lang,
		) );
	} elseif ( strpos( mb_strtolower( $original_label, 'UTF-8' ), 'kommune' ) !== false ) {
		// Log if "kommune" is present but NOT removed
		WTA_Logger::warning( 'Wikidata suffix NOT removed', array(
			'wikidata_id'  => $wikidata_id,
			'label'        => $original_label,
			'label_lower'  => $label_lower,
			'lang'         => $target_lang,
		) );
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



