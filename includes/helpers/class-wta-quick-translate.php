<?php
/**
 * Quick translation helper for location names.
 *
 * CRITICAL: This provides instant Danish translation BEFORE post creation
 * to ensure Danish URLs from the start (WordPress doesn't allow slug changes after creation).
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

class WTA_Quick_Translate {

	/**
	 * Translation map.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      array    $translations    Static translation map.
	 */
	private static $translations = null;

	/**
	 * Get translation map.
	 *
	 * @since    2.0.0
	 * @return   array Translation map.
	 */
	private static function get_translations() {
		if ( null !== self::$translations ) {
			return self::$translations;
		}

		self::$translations = array(
			'da-DK' => array(
				'continent' => array(
					'Europe'        => 'Europa',
					'Asia'          => 'Asien',
					'Africa'        => 'Afrika',
					'North America' => 'Nordamerika',
					'South America' => 'Sydamerika',
					'Oceania'       => 'Oceanien',
					'Antarctica'    => 'Antarktis',
				),
				'country' => array(
					// Europe
					'Denmark'         => 'Danmark',
					'Germany'         => 'Tyskland',
					'Sweden'          => 'Sverige',
					'Norway'          => 'Norge',
					'Finland'         => 'Finland',
					'United Kingdom'  => 'Storbritannien',
					'France'          => 'Frankrig',
					'Spain'           => 'Spanien',
					'Italy'           => 'Italien',
					'Netherlands'     => 'Holland',
					'Belgium'         => 'Belgien',
					'Switzerland'     => 'Schweiz',
					'Austria'         => 'Østrig',
					'Poland'          => 'Polen',
					'Czech Republic'  => 'Tjekkiet',
					'Greece'          => 'Grækenland',
					'Portugal'        => 'Portugal',
					'Hungary'         => 'Ungarn',
					'Romania'         => 'Rumænien',
					'Ireland'         => 'Irland',
					'Croatia'         => 'Kroatien',
					'Iceland'         => 'Island',
					'Luxembourg'      => 'Luxembourg',
					'Russia'          => 'Rusland',
					'Ukraine'         => 'Ukraine',
					'Turkey'          => 'Tyrkiet',
					// Americas
					'United States'   => 'USA',
					'Canada'          => 'Canada',
					'Mexico'          => 'Mexico',
					'Brazil'          => 'Brasilien',
					'Argentina'       => 'Argentina',
					'Chile'           => 'Chile',
					'Colombia'        => 'Colombia',
					'Peru'            => 'Peru',
					'Venezuela'       => 'Venezuela',
					// Asia
					'China'           => 'Kina',
					'Japan'           => 'Japan',
					'India'           => 'Indien',
					'South Korea'     => 'Sydkorea',
					'Thailand'        => 'Thailand',
					'Vietnam'         => 'Vietnam',
					'Indonesia'       => 'Indonesien',
					'Philippines'     => 'Filippinerne',
					'Malaysia'        => 'Malaysia',
					'Singapore'       => 'Singapore',
					'Israel'          => 'Israel',
					'Saudi Arabia'    => 'Saudi-Arabien',
					'United Arab Emirates' => 'De Forenede Arabiske Emirater',
					// Oceania
					'Australia'       => 'Australien',
					'New Zealand'     => 'New Zealand',
					// Africa
					'South Africa'    => 'Sydafrika',
					'Egypt'           => 'Egypten',
					'Morocco'         => 'Marokko',
					'Kenya'           => 'Kenya',
					'Nigeria'         => 'Nigeria',
				),
				'city' => array(
					// Denmark
					'Copenhagen'      => 'København',
					'Aarhus'          => 'Århus',
					'Odense'          => 'Odense',
					'Aalborg'         => 'Ålborg',
					// Germany
					'Munich'          => 'München',
					'Cologne'         => 'Köln',
					'Frankfurt'       => 'Frankfurt',
					'Berlin'          => 'Berlin',
					'Hamburg'         => 'Hamburg',
					// Rest of Europe
					'Vienna'          => 'Wien',
					'Geneva'          => 'Genève',
					'Zurich'          => 'Zürich',
					'Brussels'        => 'Bruxelles',
					'Prague'          => 'Prag',
					'Athens'          => 'Athen',
					'Rome'            => 'Rom',
					'Venice'          => 'Venedig',
					'Florence'        => 'Firenze',
					'Moscow'          => 'Moskva',
					'Saint Petersburg' => 'Sankt Petersborg',
					'Warsaw'          => 'Warszawa',
					'Lisbon'          => 'Lissabon',
					// Americas
					'New York'        => 'New York',
					'Los Angeles'     => 'Los Angeles',
					'Chicago'         => 'Chicago',
					'Miami'           => 'Miami',
					'San Francisco'   => 'San Francisco',
					'Mexico City'     => 'Mexico By',
					// Asia
					'Tokyo'           => 'Tokyo',
					'Beijing'         => 'Beijing',
					'Shanghai'        => 'Shanghai',
					'Hong Kong'       => 'Hong Kong',
					'Seoul'           => 'Seoul',
					'Bangkok'         => 'Bangkok',
					'Dubai'           => 'Dubai',
					'Singapore'       => 'Singapore',
					// Oceania
					'Sydney'          => 'Sydney',
					'Melbourne'       => 'Melbourne',
					'Auckland'        => 'Auckland',
					// Africa
					'Cairo'           => 'Cairo',
					'Cape Town'       => 'Kapstaden',
					'Johannesburg'    => 'Johannesburg',
				),
			),
		);

		return self::$translations;
	}

	/**
	 * Translate a location name.
	 *
	 * @since    2.0.0
	 * @param    string $name        Original English name.
	 * @param    string $type        Location type (continent, country, city).
	 * @param    string $target_lang Target language code (e.g., 'da-DK').
	 * @return   string              Translated name or original if no translation found.
	 */
	public static function translate( $name, $type, $target_lang = 'da-DK' ) {
		$translations = self::get_translations();

		if ( ! isset( $translations[ $target_lang ][ $type ][ $name ] ) ) {
			// No translation found - return original
			return $name;
		}

		return $translations[ $target_lang ][ $type ][ $name ];
	}

	/**
	 * Check if a translation exists for a location.
	 *
	 * @since    2.0.0
	 * @param    string $name        Original English name.
	 * @param    string $type        Location type (continent, country, city).
	 * @param    string $target_lang Target language code.
	 * @return   bool                True if translation exists.
	 */
	public static function has_translation( $name, $type, $target_lang = 'da-DK' ) {
		$translations = self::get_translations();
		return isset( $translations[ $target_lang ][ $type ][ $name ] );
	}

	/**
	 * Get all translations for a type.
	 *
	 * @since    2.0.0
	 * @param    string $type        Location type (continent, country, city).
	 * @param    string $target_lang Target language code.
	 * @return   array               Array of translations.
	 */
	public static function get_all_for_type( $type, $target_lang = 'da-DK' ) {
		$translations = self::get_translations();

		if ( ! isset( $translations[ $target_lang ][ $type ] ) ) {
			return array();
		}

		return $translations[ $target_lang ][ $type ];
	}
}
