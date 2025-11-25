<?php
/**
 * Quick translation helper for location names.
 * 
 * Provides instant translation during post creation to ensure Danish URLs.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

/**
 * Quick translation class.
 *
 * @since 1.0.0
 */
class WTA_Quick_Translate {

	/**
	 * Static translation map for common location names.
	 * This ensures instant translation without API calls during post creation.
	 *
	 * @var array
	 */
	private static $translation_map = array(
		// Continents
		'Europe'        => 'Europa',
		'Asia'          => 'Asien',
		'Africa'        => 'Afrika',
		'North America' => 'Nordamerika',
		'South America' => 'Sydamerika',
		'Oceania'       => 'Oceanien',
		'Antarctica'    => 'Antarktis',
		
		// Common countries
		'Denmark'       => 'Danmark',
		'Germany'       => 'Tyskland',
		'Sweden'        => 'Sverige',
		'Norway'        => 'Norge',
		'Finland'       => 'Finland',
		'Iceland'       => 'Island',
		'France'        => 'Frankrig',
		'Spain'         => 'Spanien',
		'Italy'         => 'Italien',
		'Greece'        => 'Grækenland',
		'Netherlands'   => 'Holland',
		'Belgium'       => 'Belgien',
		'Austria'       => 'Østrig',
		'Switzerland'   => 'Schweiz',
		'Poland'        => 'Polen',
		'Czech Republic' => 'Tjekkiet',
		'Slovakia'      => 'Slovakiet',
		'Hungary'       => 'Ungarn',
		'Romania'       => 'Rumænien',
		'Bulgaria'      => 'Bulgarien',
		'Croatia'       => 'Kroatien',
		'Slovenia'      => 'Slovenien',
		'Serbia'        => 'Serbien',
		'Portugal'      => 'Portugal',
		'Ireland'       => 'Irland',
		'United Kingdom' => 'Storbritannien',
		'Russia'        => 'Rusland',
		'Ukraine'       => 'Ukraine',
		'Turkey'        => 'Tyrkiet',
		'United States' => 'USA',
		'Canada'        => 'Canada',
		'Mexico'        => 'Mexico',
		'Brazil'        => 'Brasilien',
		'Argentina'     => 'Argentina',
		'China'         => 'Kina',
		'Japan'         => 'Japan',
		'India'         => 'Indien',
		'Australia'     => 'Australien',
		'New Zealand'   => 'New Zealand',
		'Egypt'         => 'Egypten',
		'South Africa'  => 'Sydafrika',
	);

	/**
	 * Get translated name for a location.
	 * 
	 * Uses static map for instant translation, or returns original if not found.
	 * AI will provide better translation later for full content.
	 *
	 * @since 1.0.0
	 * @param string $english_name English location name.
	 * @return string Danish translated name or original name.
	 */
	public static function get_danish_name( $english_name ) {
		if ( isset( self::$translation_map[ $english_name ] ) ) {
			return self::$translation_map[ $english_name ];
		}
		
		// Return original name if no translation found
		// AI will translate it properly later
		return $english_name;
	}

	/**
	 * Check if a translation exists.
	 *
	 * @since 1.0.0
	 * @param string $english_name English location name.
	 * @return bool True if translation exists.
	 */
	public static function has_translation( $english_name ) {
		return isset( self::$translation_map[ $english_name ] );
	}

	/**
	 * Add a custom translation to the map.
	 *
	 * @since 1.0.0
	 * @param string $english English name.
	 * @param string $danish Danish name.
	 */
	public static function add_translation( $english, $danish ) {
		self::$translation_map[ $english ] = $danish;
	}

	/**
	 * Get all translations.
	 *
	 * @since 1.0.0
	 * @return array Translation map.
	 */
	public static function get_all_translations() {
		return self::$translation_map;
	}
}

