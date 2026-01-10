<?php
/**
 * GeoNames translation helper.
 *
 * Parses alternateNamesV2.txt to provide instant translations for locations.
 * Memory-efficient caching system for 745 MB translation file.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 * @since      3.0.0
 */

class WTA_GeoNames_Translator {

	/**
	 * Parse alternateNamesV2.txt for a specific language.
	 *
	 * Extracts preferred names (isPreferredName=1) for target language.
	 * Result is cached in transient for 24 hours.
	 *
	 * alternateNamesV2.txt format (tab-separated):
	 * alternateNameId  geonameid  isolanguage  alternate_name  isPreferredName  isShortName  isColloquial  isHistoric  from  to
	 *
	 * @since    3.0.0
	 * @param    string $lang_code Language code (e.g., 'da-DK', 'en-US').
	 * @return   array             Array of geonameid => translated_name pairs.
	 */
	public static function parse_alternate_names( $lang_code = 'da-DK', $force_reparse = false ) {
		// Extract language prefix (da-DK → da)
		$lang = strtok( $lang_code, '-' );

		// Check cache first (24h) - unless force_reparse is true
		$cache_key = 'wta_geonames_translations_' . $lang;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached && ! $force_reparse ) {
			WTA_Logger::info( 'GeoNames translations loaded from cache', array(
				'language' => $lang,
				'count'    => count( $cached ),
			) );
			return $cached;
		}
		
		// v3.2.32: If force_reparse, explicitly delete old cache first
		if ( $force_reparse ) {
			delete_transient( $cache_key );
			WTA_Logger::info( 'Force re-parsing GeoNames (ignoring cache)', array(
				'language' => $lang,
				'reason' => 'Ensure new v3.2.29+ code is used (no isPreferredName filter)',
			) );
		}

		// Parse file
		$file_path = WTA_GeoNames_Parser::get_data_directory() . '/alternateNamesV2.txt';

		if ( ! file_exists( $file_path ) ) {
			WTA_Logger::error( 'alternateNamesV2.txt not found', array( 'path' => $file_path ) );
			return array();
		}

	WTA_Logger::info( 'Parsing alternateNamesV2.txt (this takes 2-5 minutes)', array(
		'language' => $lang,
		'file_size' => size_format( filesize( $file_path ) ),
		'version' => 'v3.2.42 - TABS VERIFIED',
	) );

		$start_time = microtime( true );
		$translations = array();
		$file = fopen( $file_path, 'r' );

		if ( ! $file ) {
			WTA_Logger::error( 'Failed to open alternateNamesV2.txt' );
			return array();
		}

	$line_count = 0;
	$matched_count = 0;

	// v3.2.36: CRITICAL FIX - while loop was missing one level of indentation!
	while ( ( $line = fgets( $file ) ) !== false ) {
		$line_count++;

		// v3.2.35: CRITICAL FIX - All code below must be INSIDE while loop (3 tabs!)
		// Skip comments
		if ( strpos( $line, '#' ) === 0 ) {
			continue;
		}

		// Parse tab-separated values
		$parts = explode( "\t", trim( $line ) );

		// v3.2.42: Log every 100k translations to prove code is running!
		// Validate minimum required fields
		if ( count( $parts ) < 5 ) {
			continue;
		}

		$geonameid = $parts[1];
		$isolanguage = $parts[2];
		$alternate_name = $parts[3];

		// v3.2.48: CLEAN VERSION - removed ALL debug code
		// Store ALL translations for target language (not just "preferred")
		if ( $isolanguage === $lang ) {
			if ( ! isset( $translations[ $geonameid ] ) ) {
				$translations[ $geonameid ] = $alternate_name;
				$matched_count++;
			}
		}

	// v3.2.47: Log progress every 1M lines showing EXACTLY how many Swedish matches so far!
	if ( $line_count % 1000000 === 0 ) {
		$elapsed = round( microtime( true ) - $start_time, 2 );
		$memory_mb = round( memory_get_usage() / 1024 / 1024, 2 );
		
		WTA_Logger::info( 'v3.2.47 Parsing progress', array(
			'lines_processed' => number_format( $line_count ),
			'translations' => number_format( $matched_count ),
			'expected_at_this_line' => ($line_count == 1000000 ? '~4,400' : ($line_count == 6000000 ? '~65,000' : 'see local test')),
			'elapsed_seconds' => $elapsed,
			'memory_mb'       => $memory_mb,
		) );
		
		// Extend execution time
		set_time_limit( 300 ); // 5 minutes
	}
	} // End while loop

		fclose( $file );

		$elapsed = round( microtime( true ) - $start_time, 2 );
		$memory_mb = round( memory_get_usage() / 1024 / 1024, 2 );

	// v3.2.23: Validate parsing results BEFORE caching
	if ( $matched_count === 0 ) {
		WTA_Logger::error( 'FATAL: No translations found in alternateNamesV2.txt!', array(
			'language' => $lang,
			'lines_processed' => number_format( $line_count ),
			'file_size' => size_format( filesize( $file_path ) ),
		) );
		return array(); // Return empty array = parsing failed
	}
	
	if ( $matched_count < 1000 ) {
		WTA_Logger::warning( 'Very few translations found - file may be corrupted', array(
			'language' => $lang,
			'translations' => number_format( $matched_count ),
			'expected' => '> 1000 for major languages',
		) );
	}
	
	// v3.2.45: CRITICAL - Compare matched_count vs actual array count!
	$actual_array_count = count( $translations );
	
	WTA_Logger::info( 'Finished parsing alternateNamesV2.txt', array(
		'language'        => $lang,
		'lines_processed' => number_format( $line_count ),
		'matched_count'   => number_format( $matched_count ),
		'array_count'     => number_format( $actual_array_count ),
		'DISCREPANCY'     => ($matched_count !== $actual_array_count) ? 'YES! ' . number_format($matched_count - $actual_array_count) . ' missing!' : 'NO',
		'elapsed_seconds' => $elapsed,
		'memory_mb'       => $memory_mb,
	) );

	// Cache for 24 hours
	$cache_set = set_transient( $cache_key, $translations, 24 * HOUR_IN_SECONDS );
	
	// v3.2.23: Verify transient was actually set (can fail if DB issue)
	if ( ! $cache_set ) {
		WTA_Logger::error( 'FATAL: Failed to set GeoNames transient cache!', array(
			'cache_key' => $cache_key,
			'translations' => count( $translations ),
			'possible_causes' => array(
				'Database write error',
				'wp_options table locked',
				'Insufficient disk space',
			),
		) );
		return array(); // Return empty array = caching failed
	}
	
	// Double-verify cache was set correctly
	$verify_cache = get_transient( $cache_key );
	if ( false === $verify_cache || count( $verify_cache ) !== count( $translations ) ) {
		WTA_Logger::error( 'FATAL: GeoNames cache verification failed!', array(
			'cache_key' => $cache_key,
			'expected_count' => count( $translations ),
			'actual_count' => $verify_cache ? count( $verify_cache ) : 0,
		) );
		return array(); // Return empty array = verification failed
	}

	return $translations;
	}

	/**
	 * Get translated name for a GeoNames ID.
	 *
	 * Performs instant lookup from cached translations array.
	 *
	 * @since    3.0.0
	 * @param    int    $geonameid   GeoNames ID.
	 * @param    string $lang_code   Language code (e.g., 'da-DK').
	 * @return   string|false        Translated name or false if not found.
	 */
	public static function get_name( $geonameid, $lang_code = 'da-DK' ) {
		// Extract language prefix
		$lang = strtok( $lang_code, '-' );

		// Get cached translations
		$cache_key = 'wta_geonames_translations_' . $lang;
		$translations = get_transient( $cache_key );

		// If cache is empty, try to parse (but this should be done beforehand)
		if ( false === $translations ) {
			WTA_Logger::warning( 'GeoNames translations not cached, parsing now...', array(
				'geonameid' => $geonameid,
			) );
			$translations = self::parse_alternate_names( $lang_code );
		}

		// Lookup translation
		$geonameid_str = strval( $geonameid );
		
		if ( isset( $translations[ $geonameid_str ] ) ) {
			return $translations[ $geonameid_str ];
		}

		return false;
	}

	/**
	 * Clear translation cache.
	 *
	 * Useful when changing language or forcing re-parse.
	 *
	 * @since    3.0.0
	 * @param    string $lang_code Optional. Language code to clear. If empty, clears all.
	 * @return   bool              Success status.
	 */
	public static function clear_cache( $lang_code = '' ) {
		if ( empty( $lang_code ) ) {
			// Clear all languages (pattern matching not supported by transients, so we clear common ones)
			$common_langs = array( 'da', 'en', 'de', 'fr', 'es', 'it', 'nl', 'sv', 'no', 'fi' );
			
			foreach ( $common_langs as $lang ) {
				$cache_key = 'wta_geonames_translations_' . $lang;
				delete_transient( $cache_key );
			}
			
			WTA_Logger::info( 'Cleared all GeoNames translation caches' );
		} else {
			$lang = strtok( $lang_code, '-' );
			$cache_key = 'wta_geonames_translations_' . $lang;
			delete_transient( $cache_key );
			
			WTA_Logger::info( 'Cleared GeoNames translation cache', array( 'language' => $lang ) );
		}

		return true;
	}

	/**
	 * Get cache status.
	 *
	 * Returns information about cached translations.
	 *
	 * @since    3.0.0
	 * @param    string $lang_code Language code.
	 * @return   array             Cache status information.
	 */
	public static function get_cache_status( $lang_code = 'da-DK' ) {
		$lang = strtok( $lang_code, '-' );
		$cache_key = 'wta_geonames_translations_' . $lang;
		$translations = get_transient( $cache_key );

		if ( false === $translations ) {
			return array(
				'cached'       => false,
				'language'     => $lang,
				'count'        => 0,
				'cache_key'    => $cache_key,
			);
		}

		return array(
			'cached'       => true,
			'language'     => $lang,
			'count'        => count( $translations ),
			'memory_mb'    => round( strlen( serialize( $translations ) ) / 1024 / 1024, 2 ),
			'cache_key'    => $cache_key,
		);
	}

	/**
	 * Pre-cache translations for import.
	 *
	 * Should be called before starting city import.
	 * Ensures translations are ready for instant lookup.
	 *
	 * @since    3.0.0
	 * @param    string $lang_code Language code.
	 * @return   bool              Success status.
	 */
	public static function prepare_for_import( $lang_code = null, $force_reparse = true ) {
		if ( null === $lang_code ) {
			$lang_code = get_option( 'wta_base_language', 'da-DK' );
		}

	WTA_Logger::info( 'Preparing GeoNames translations for import (may take 2-5 minutes)...', array(
		'language' => $lang_code,
		'file' => 'alternateNamesV2.txt (~745 MB)',
		'force_reparse' => $force_reparse ? 'yes (ignore cache)' : 'no (use cache if available)',
	) );

	// v3.2.32: Default to force_reparse = true to ensure fresh data on imports
	// This prevents issues with OpCache serving old cached translations
	$translations = self::parse_alternate_names( $lang_code, $force_reparse );

	// v3.2.23: Strict validation - empty array means parsing/caching FAILED
	if ( empty( $translations ) ) {
		WTA_Logger::error( 'FATAL: Failed to prepare GeoNames translations - import CANNOT proceed!', array(
			'language' => $lang_code,
			'result' => 'empty array',
		) );
		return false;
	}
	
	if ( count( $translations ) < 1000 ) {
		WTA_Logger::warning( 'GeoNames translations seems incomplete', array(
			'language' => $lang_code,
			'translations' => count( $translations ),
			'expected' => '> 1000 for major languages',
		) );
	}

	WTA_Logger::info( 'GeoNames translations ready for import!', array(
		'language'     => $lang_code,
		'translations' => number_format( count( $translations ) ),
		'cache_key'    => 'wta_geonames_translations_' . strtok( $lang_code, '-' ),
		'expires'      => '24 hours',
	) );

	return true;
	}
}

