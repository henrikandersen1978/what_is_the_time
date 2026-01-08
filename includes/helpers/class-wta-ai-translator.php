<?php
/**
 * AI-powered translation helper for location names.
 *
 * Uses OpenAI to translate location names on-the-fly before post creation
 * to ensure localized URLs from the start.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

class WTA_AI_Translator {

	/**
	 * Translate a location name with intelligent fallback system.
	 *
	 * Translation priority (v3.0.0):
	 * 1. GeoNames (if geonameid provided) - fast, accurate, multi-language
	 * 2. Wikidata (if wikidata_id provided) - fallback for missing GeoNames
	 * 3. Static Quick_Translate table - manually curated translations
	 * 4. OpenAI API - AI-powered translation (continents/countries only)
	 * 5. Original name - fallback for small towns (correct behavior)
	 *
	 * @since    2.0.0
	 * @since    2.11.0  Added Wikidata support with wikidata_id parameter.
	 * @since    3.0.0   Added GeoNames support as primary translation source.
	 * @param    string $name        Original name.
	 * @param    string $type        Location type (continent, country, city).
	 * @param    string $target_lang Target language code (e.g., 'da-DK').
	 * @param    mixed  $geonameid   Optional. GeoNames ID (int) or Wikidata Q-ID (string for BC).
	 * @return   string              Translated name or original if translation fails.
	 */
	public static function translate( $name, $type, $target_lang = null, $geonameid = null ) {
		// Get target language from settings if not provided
		if ( null === $target_lang ) {
			$target_lang = get_option( 'wta_base_language', 'da-DK' );
		}

	// Extract language prefix (da-DK → da)
	$lang = strtok( $target_lang, '-' );

	// Generate cache key (include geonameid if provided)
	$cache_suffix = ! empty( $geonameid ) ? $geonameid : $name;
		$cache_key = 'wta_trans_' . md5( $cache_suffix . '_' . $type . '_' . $target_lang );

		// Check cache first (v3.0.80: Transient caching disabled to reduce database bloat)
		// Transients are not needed for one-time imports
		// $cached = get_transient( $cache_key );
		// if ( false !== $cached ) {
		// 	return $cached;
		// }

	// 1. Try GeoNames first (fastest + most accurate! v3.0.0)
	if ( ! empty( $geonameid ) && is_numeric( $geonameid ) ) {
		$geonames_translation = WTA_GeoNames_Translator::get_name( intval( $geonameid ), $target_lang );
		if ( false !== $geonames_translation ) {
			// GeoNames translation found - return directly (v3.0.80: no caching)
			return $geonames_translation;
		}
	}

	// 2. Try Wikidata as fallback (for backward compatibility or missing GeoNames)
	// Note: geonameid might be a Wikidata Q-ID string from old system
	if ( ! empty( $geonameid ) && is_string( $geonameid ) && strpos( $geonameid, 'Q' ) === 0 ) {
		$wikidata_translation = WTA_Wikidata_Translator::get_label( $geonameid, $lang );
			if ( false !== $wikidata_translation ) {
				// Wikidata translation found - return directly (v3.0.80: no caching)
				return $wikidata_translation;
			}
		}

		// 3. Try static translation (fast and free)
		$static_translation = WTA_Quick_Translate::translate( $name, $type, $target_lang );
		if ( $static_translation !== $name ) {
			// Static translation found - return directly (v3.0.80: no caching)
			return $static_translation;
		}

		// 4. Use AI as last resort - BUT NOT FOR CITIES!
		// Cities should keep their original names unless found in Wikidata or Quick_Translate
		// This prevents AI from translating city names like "Ojo de Agua" to "Øje de Agua"
		if ( 'city' === $type ) {
			// Return original city name (v3.0.80: no caching)
			return $name;
		}
		
		$ai_translation = self::translate_with_ai( $name, $type, $target_lang );
		
		if ( false !== $ai_translation && ! empty( $ai_translation ) ) {
			// AI translation successful - return directly (v3.0.80: no caching)
			return $ai_translation;
		}

		// 5. Return original name (correct for small towns that don't have translations!)
		// (v3.0.80: no caching to prevent database bloat)
		return $name;
	}

	/**
	 * Translate using OpenAI API.
	 *
	 * @since    2.0.0
	 * @param    string $name        Original name.
	 * @param    string $type        Location type.
	 * @param    string $target_lang Target language code.
	 * @return   string|false        Translated name or false on failure.
	 */
	private static function translate_with_ai( $name, $type, $target_lang ) {
		// Get OpenAI settings
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return false;
		}

		// Get language description
		$lang_description = get_option( 'wta_base_language_description', 'Skriv på flydende dansk til danske brugere' );
		$country_name = get_option( 'wta_base_country_name', 'Danmark' );

		// Map language codes to language names
		$lang_map = array(
			'da-DK' => 'dansk',
			'en-US' => 'engelsk',
			'sv-SE' => 'svensk',
			'no-NO' => 'norsk',
			'de-DE' => 'tysk',
			'fr-FR' => 'fransk',
			'es-ES' => 'spansk',
			'it-IT' => 'italiensk',
		);

		$target_language_name = isset( $lang_map[ $target_lang ] ) ? $lang_map[ $target_lang ] : 'dansk';

		// Type-specific context
		$type_context = array(
			'continent' => 'kontinentnavn',
			'country'   => 'landenavn',
			'city'      => 'bynavn',
		);
		$type_name = isset( $type_context[ $type ] ) ? $type_context[ $type ] : 'stedsnavn';

		// Build prompts
		$system_prompt = "Du er en professionel oversætter specialiseret i geografiske stednavne. Du oversætter til {$target_language_name} som det bruges i {$country_name}.";
		
		$user_prompt = "Oversæt dette {$type_name} til {$target_language_name}: \"{$name}\"\n\n";
		$user_prompt .= "Regler:\n";
		$user_prompt .= "1. Svar KUN med det oversatte navn, intet andet\n";
		$user_prompt .= "2. Brug den officielle {$target_language_name}e stavemåde\n";
		$user_prompt .= "3. Hvis navnet normalt ikke oversættes (f.eks. London, Paris), behold det originale navn\n";
		$user_prompt .= "4. Undgå anførselstegn i svaret\n";
		$user_prompt .= "5. For byer: brug den form som lokale indbyggere ville bruge\n\n";
		$user_prompt .= "Oversæt: {$name}";

		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );

		// Call OpenAI API
		$url = 'https://api.openai.com/v1/chat/completions';

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
			'temperature' => 0.3, // Low temperature for consistent translations
			'max_tokens'  => 50,  // Short response expected
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
			WTA_Logger::warning( 'AI translation failed', array(
				'name'  => $name,
				'type'  => $type,
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			WTA_Logger::warning( 'AI translation returned unexpected response', array(
				'name'     => $name,
				'response' => $data,
			) );
			return false;
		}

		$translated = trim( $data['choices'][0]['message']['content'] );
		
		// Clean up the translation
		$translated = self::clean_translation( $translated );

		// Validate translation
		if ( empty( $translated ) || strlen( $translated ) > 200 ) {
			WTA_Logger::warning( 'AI translation validation failed', array(
				'name'       => $name,
				'translated' => $translated,
			) );
			return false;
		}

		WTA_Logger::info( 'AI translation successful', array(
			'original'   => $name,
			'translated' => $translated,
			'type'       => $type,
		) );

		return $translated;
	}

	/**
	 * Clean translated text.
	 *
	 * Removes quotes, extra whitespace, and common artifacts.
	 *
	 * @since    2.0.0
	 * @param    string $text Text to clean.
	 * @return   string       Cleaned text.
	 */
	private static function clean_translation( $text ) {
		$text = trim( $text );

		// Remove surrounding quotes
		if ( ( str_starts_with( $text, '"' ) && str_ends_with( $text, '"' ) ) ||
		     ( str_starts_with( $text, "'" ) && str_ends_with( $text, "'" ) ) ||
		     ( str_starts_with( $text, '«' ) && str_ends_with( $text, '»' ) ) ) {
			$text = substr( $text, 1, -1 );
		}

		// Remove any remaining quotes
		$quotes_to_remove = array( '"', "'", '«', '»' );
		$text = str_replace( $quotes_to_remove, '', $text );
		
		// Remove smart quotes using preg_replace
		$text = preg_replace( '/[\x{201C}\x{201D}\x{2018}\x{2019}]/u', '', $text );

		// Remove common prefixes that might appear
		$text = preg_replace( '/^(Oversættelse:|Translation:|Svar:|Answer:)\s*/i', '', $text );

		// Remove excessive whitespace
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Batch translate multiple locations.
	 *
	 * Optimized for translating multiple items with delay between API calls.
	 *
	 * @since    2.0.0
	 * @param    array  $items       Array of items with 'name' and 'type'.
	 * @param    string $target_lang Target language code.
	 * @return   array               Array of translations keyed by original name.
	 */
	public static function batch_translate( $items, $target_lang = null ) {
		$translations = array();

		foreach ( $items as $item ) {
			$name = $item['name'];
			$type = $item['type'];

			$translated = self::translate( $name, $type, $target_lang );
			$translations[ $name ] = $translated;

			// Small delay between API calls to respect rate limits
			if ( $translated !== $name ) {
				usleep( 100000 ); // 100ms
			}
		}

		return $translations;
	}

	/**
	 * Clear translation cache.
	 *
	 * Useful when changing target language or forcing re-translation.
	 *
	 * @since    2.0.0
	 * @param    string $name        Optional. Clear cache for specific name.
	 * @param    string $type        Optional. Location type.
	 * @param    string $target_lang Optional. Target language.
	 * @return   bool                True if cache was cleared.
	 */
	public static function clear_cache( $name = null, $type = null, $target_lang = null ) {
		global $wpdb;

		if ( null !== $name && null !== $type ) {
			// Clear specific translation
			if ( null === $target_lang ) {
				$target_lang = get_option( 'wta_base_language', 'da-DK' );
			}
			$cache_key = 'wta_trans_' . md5( $name . '_' . $type . '_' . $target_lang );
			return delete_transient( $cache_key );
		}

		// Clear all AI translation transients
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_wta_trans_%' 
			OR option_name LIKE '_transient_timeout_wta_trans_%'"
		);

		// Also clear Wikidata translation cache
		WTA_Wikidata_Translator::clear_cache();

		WTA_Logger::info( 'Translation cache cleared' );
		return true;
	}
}

