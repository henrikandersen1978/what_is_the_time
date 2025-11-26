<?php
/**
 * Quick translation helper for location names.
 *
 * Provides a static translation map for common locations.
 * Used as a fast fallback by WTA_AI_Translator before calling OpenAI API.
 *
 * NOTE: This is now a fallback. WTA_AI_Translator provides comprehensive
 * AI-powered translations for all locations.
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
					'Albania'         => 'Albanien',
					'Andorra'         => 'Andorra',
					'Austria'         => 'Østrig',
					'Belarus'         => 'Hviderusland',
					'Belgium'         => 'Belgien',
					'Bosnia and Herzegovina' => 'Bosnien-Hercegovina',
					'Bulgaria'        => 'Bulgarien',
					'Croatia'         => 'Kroatien',
					'Cyprus'          => 'Cypern',
					'Czech Republic'  => 'Tjekkiet',
					'Denmark'         => 'Danmark',
					'Estonia'         => 'Estland',
					'Finland'         => 'Finland',
					'France'          => 'Frankrig',
					'Germany'         => 'Tyskland',
					'Greece'          => 'Grækenland',
					'Hungary'         => 'Ungarn',
					'Iceland'         => 'Island',
					'Ireland'         => 'Irland',
					'Italy'           => 'Italien',
					'Kosovo'          => 'Kosovo',
					'Latvia'          => 'Letland',
					'Liechtenstein'   => 'Liechtenstein',
					'Lithuania'       => 'Litauen',
					'Luxembourg'      => 'Luxembourg',
					'Malta'           => 'Malta',
					'Moldova'         => 'Moldova',
					'Monaco'          => 'Monaco',
					'Montenegro'      => 'Montenegro',
					'Netherlands'     => 'Holland',
					'North Macedonia' => 'Nordmakedonien',
					'Norway'          => 'Norge',
					'Poland'          => 'Polen',
					'Portugal'        => 'Portugal',
					'Romania'         => 'Rumænien',
					'Russia'          => 'Rusland',
					'San Marino'      => 'San Marino',
					'Serbia'          => 'Serbien',
					'Slovakia'        => 'Slovakiet',
					'Slovenia'        => 'Slovenien',
					'Spain'           => 'Spanien',
					'Sweden'          => 'Sverige',
					'Switzerland'     => 'Schweiz',
					'Turkey'          => 'Tyrkiet',
					'Ukraine'         => 'Ukraine',
					'United Kingdom'  => 'Storbritannien',
					'Vatican City'    => 'Vatikanstaten',
					// Americas - North America
					'Antigua and Barbuda' => 'Antigua og Barbuda',
					'Bahamas'         => 'Bahamas',
					'Barbados'        => 'Barbados',
					'Belize'          => 'Belize',
					'Canada'          => 'Canada',
					'Costa Rica'      => 'Costa Rica',
					'Cuba'            => 'Cuba',
					'Dominica'        => 'Dominica',
					'Dominican Republic' => 'Den Dominikanske Republik',
					'El Salvador'     => 'El Salvador',
					'Grenada'         => 'Grenada',
					'Guatemala'       => 'Guatemala',
					'Haiti'           => 'Haiti',
					'Honduras'        => 'Honduras',
					'Jamaica'         => 'Jamaica',
					'Mexico'          => 'Mexico',
					'Nicaragua'       => 'Nicaragua',
					'Panama'          => 'Panama',
					'Saint Kitts and Nevis' => 'Saint Kitts og Nevis',
					'Saint Lucia'     => 'Saint Lucia',
					'Saint Vincent and the Grenadines' => 'Saint Vincent og Grenadinerne',
					'Trinidad and Tobago' => 'Trinidad og Tobago',
					'United States'   => 'USA',
					// Americas - South America
					'Argentina'       => 'Argentina',
					'Bolivia'         => 'Bolivia',
					'Brazil'          => 'Brasilien',
					'Chile'           => 'Chile',
					'Colombia'        => 'Colombia',
					'Ecuador'         => 'Ecuador',
					'Guyana'          => 'Guyana',
					'Paraguay'        => 'Paraguay',
					'Peru'            => 'Peru',
					'Suriname'        => 'Surinam',
					'Uruguay'         => 'Uruguay',
					'Venezuela'       => 'Venezuela',
					// Asia - Middle East
					'Afghanistan'     => 'Afghanistan',
					'Armenia'         => 'Armenien',
					'Azerbaijan'      => 'Aserbajdsjan',
					'Bahrain'         => 'Bahrain',
					'Georgia'         => 'Georgien',
					'Iran'            => 'Iran',
					'Iraq'            => 'Irak',
					'Israel'          => 'Israel',
					'Jordan'          => 'Jordan',
					'Kuwait'          => 'Kuwait',
					'Lebanon'         => 'Libanon',
					'Oman'            => 'Oman',
					'Palestine'       => 'Palæstina',
					'Qatar'           => 'Qatar',
					'Saudi Arabia'    => 'Saudi-Arabien',
					'Syria'           => 'Syrien',
					'United Arab Emirates' => 'De Forenede Arabiske Emirater',
					'Yemen'           => 'Yemen',
					// Asia - Central Asia
					'Kazakhstan'      => 'Kasakhstan',
					'Kyrgyzstan'      => 'Kirgisistan',
					'Tajikistan'      => 'Tadsjikistan',
					'Turkmenistan'    => 'Turkmenistan',
					'Uzbekistan'      => 'Usbekistan',
					// Asia - South Asia
					'Bangladesh'      => 'Bangladesh',
					'Bhutan'          => 'Bhutan',
					'India'           => 'Indien',
					'Maldives'        => 'Maldiverne',
					'Nepal'           => 'Nepal',
					'Pakistan'        => 'Pakistan',
					'Sri Lanka'       => 'Sri Lanka',
					// Asia - Southeast Asia
					'Brunei'          => 'Brunei',
					'Cambodia'        => 'Cambodja',
					'East Timor'      => 'Østtimor',
					'Indonesia'       => 'Indonesien',
					'Laos'            => 'Laos',
					'Malaysia'        => 'Malaysia',
					'Myanmar'         => 'Myanmar',
					'Philippines'     => 'Filippinerne',
					'Singapore'       => 'Singapore',
					'Thailand'        => 'Thailand',
					'Vietnam'         => 'Vietnam',
					// Asia - East Asia
					'China'           => 'Kina',
					'Hong Kong'       => 'Hong Kong',
					'Japan'           => 'Japan',
					'Macau'           => 'Macau',
					'Mongolia'        => 'Mongoliet',
					'North Korea'     => 'Nordkorea',
					'South Korea'     => 'Sydkorea',
					'Taiwan'          => 'Taiwan',
					// Oceania
					'Australia'       => 'Australien',
					'Fiji'            => 'Fiji',
					'Kiribati'        => 'Kiribati',
					'Marshall Islands' => 'Marshalløerne',
					'Micronesia'      => 'Mikronesien',
					'Nauru'           => 'Nauru',
					'New Zealand'     => 'New Zealand',
					'Palau'           => 'Palau',
					'Papua New Guinea' => 'Papua Ny Guinea',
					'Samoa'           => 'Samoa',
					'Solomon Islands' => 'Salomonøerne',
					'Tonga'           => 'Tonga',
					'Tuvalu'          => 'Tuvalu',
					'Vanuatu'         => 'Vanuatu',
					// Africa - North Africa
					'Algeria'         => 'Algeriet',
					'Egypt'           => 'Egypten',
					'Libya'           => 'Libyen',
					'Morocco'         => 'Marokko',
					'Sudan'           => 'Sudan',
					'Tunisia'         => 'Tunesien',
					// Africa - West Africa
					'Benin'           => 'Benin',
					'Burkina Faso'    => 'Burkina Faso',
					'Cape Verde'      => 'Kap Verde',
					'Ivory Coast'     => 'Elfenbenskysten',
					'Gambia'          => 'Gambia',
					'Ghana'           => 'Ghana',
					'Guinea'          => 'Guinea',
					'Guinea-Bissau'   => 'Guinea-Bissau',
					'Liberia'         => 'Liberia',
					'Mali'            => 'Mali',
					'Mauritania'      => 'Mauretanien',
					'Niger'           => 'Niger',
					'Nigeria'         => 'Nigeria',
					'Senegal'         => 'Senegal',
					'Sierra Leone'    => 'Sierra Leone',
					'Togo'            => 'Togo',
					// Africa - Central Africa
					'Cameroon'        => 'Cameroun',
					'Central African Republic' => 'Centralafrikanske Republik',
					'Chad'            => 'Tchad',
					'Congo'           => 'Congo',
					'Democratic Republic of the Congo' => 'Den Demokratiske Republik Congo',
					'Equatorial Guinea' => 'Ækvatorialguinea',
					'Gabon'           => 'Gabon',
					'São Tomé and Príncipe' => 'São Tomé og Príncipe',
					// Africa - East Africa
					'Burundi'         => 'Burundi',
					'Comoros'         => 'Comorerne',
					'Djibouti'        => 'Djibouti',
					'Eritrea'         => 'Eritrea',
					'Ethiopia'        => 'Etiopien',
					'Kenya'           => 'Kenya',
					'Madagascar'      => 'Madagaskar',
					'Malawi'          => 'Malawi',
					'Mauritius'       => 'Mauritius',
					'Mozambique'      => 'Mozambique',
					'Rwanda'          => 'Rwanda',
					'Seychelles'      => 'Seychellerne',
					'Somalia'         => 'Somalia',
					'South Sudan'     => 'Sydsudan',
					'Tanzania'        => 'Tanzania',
					'Uganda'          => 'Uganda',
					'Zambia'          => 'Zambia',
					// Africa - Southern Africa
					'Angola'          => 'Angola',
					'Botswana'        => 'Botswana',
					'Eswatini'        => 'Eswatini',
					'Lesotho'         => 'Lesotho',
					'Namibia'         => 'Namibia',
					'South Africa'    => 'Sydafrika',
					'Zimbabwe'        => 'Zimbabwe',
				),
				'city' => array(
					// Denmark
					'Copenhagen'      => 'København',
					'Aarhus'          => 'Århus',
					'Odense'          => 'Odense',
					'Aalborg'         => 'Ålborg',
					'Esbjerg'         => 'Esbjerg',
					'Randers'         => 'Randers',
					'Kolding'         => 'Kolding',
					'Horsens'         => 'Horsens',
					'Vejle'           => 'Vejle',
					'Roskilde'        => 'Roskilde',
					'Herning'         => 'Herning',
					'Silkeborg'       => 'Silkeborg',
					'Næstved'         => 'Næstved',
					'Fredericia'      => 'Fredericia',
					'Viborg'          => 'Viborg',
					'Køge'            => 'Køge',
					'Holstebro'       => 'Holstebro',
					'Taastrup'        => 'Taastrup',
					'Slagelse'        => 'Slagelse',
					'Hillerød'        => 'Hillerød',
					// Germany
					'Berlin'          => 'Berlin',
					'Hamburg'         => 'Hamburg',
					'Munich'          => 'München',
					'Cologne'         => 'Köln',
					'Frankfurt'       => 'Frankfurt',
					'Stuttgart'       => 'Stuttgart',
					'Düsseldorf'      => 'Düsseldorf',
					'Dortmund'        => 'Dortmund',
					'Essen'           => 'Essen',
					'Leipzig'         => 'Leipzig',
					'Bremen'          => 'Bremen',
					'Dresden'         => 'Dresden',
					'Hannover'        => 'Hannover',
					'Nuremberg'       => 'Nürnberg',
					// United Kingdom
					'London'          => 'London',
					'Edinburgh'       => 'Edinburgh',
					'Manchester'      => 'Manchester',
					'Birmingham'      => 'Birmingham',
					'Glasgow'         => 'Glasgow',
					'Liverpool'       => 'Liverpool',
					'Bristol'         => 'Bristol',
					'Leeds'           => 'Leeds',
					'Sheffield'       => 'Sheffield',
					'Cardiff'         => 'Cardiff',
					'Belfast'         => 'Belfast',
					// France
					'Paris'           => 'Paris',
					'Marseille'       => 'Marseille',
					'Lyon'            => 'Lyon',
					'Toulouse'        => 'Toulouse',
					'Nice'            => 'Nice',
					'Nantes'          => 'Nantes',
					'Strasbourg'      => 'Strasbourg',
					'Montpellier'     => 'Montpellier',
					'Bordeaux'        => 'Bordeaux',
					// Italy
					'Rome'            => 'Rom',
					'Milan'           => 'Milano',
					'Naples'          => 'Napoli',
					'Turin'           => 'Torino',
					'Venice'          => 'Venedig',
					'Florence'        => 'Firenze',
					'Bologna'         => 'Bologna',
					'Genoa'           => 'Genova',
					// Spain
					'Madrid'          => 'Madrid',
					'Barcelona'       => 'Barcelona',
					'Valencia'        => 'Valencia',
					'Seville'         => 'Sevilla',
					'Zaragoza'        => 'Zaragoza',
					'Málaga'          => 'Málaga',
					'Murcia'          => 'Murcia',
					'Palma'           => 'Palma',
					'Bilbao'          => 'Bilbao',
					// Other Europe
					'Vienna'          => 'Wien',
					'Geneva'          => 'Genève',
					'Zurich'          => 'Zürich',
					'Brussels'        => 'Bruxelles',
					'Prague'          => 'Prag',
					'Athens'          => 'Athen',
					'Moscow'          => 'Moskva',
					'Saint Petersburg' => 'Sankt Petersborg',
					'Warsaw'          => 'Warszawa',
					'Lisbon'          => 'Lissabon',
					'Bucharest'       => 'Bukarest',
					'Budapest'        => 'Budapest',
					'Stockholm'       => 'Stockholm',
					'Oslo'            => 'Oslo',
					'Helsinki'        => 'Helsinki',
					'Amsterdam'       => 'Amsterdam',
					'Rotterdam'       => 'Rotterdam',
					'The Hague'       => 'Haag',
					'Dublin'          => 'Dublin',
					'Zagreb'          => 'Zagreb',
					'Sofia'           => 'Sofia',
					'Belgrade'        => 'Beograd',
					// Americas - USA
					'New York'        => 'New York',
					'Los Angeles'     => 'Los Angeles',
					'Chicago'         => 'Chicago',
					'Houston'         => 'Houston',
					'Phoenix'         => 'Phoenix',
					'Philadelphia'    => 'Philadelphia',
					'San Antonio'     => 'San Antonio',
					'San Diego'       => 'San Diego',
					'Dallas'          => 'Dallas',
					'San Jose'        => 'San Jose',
					'Austin'          => 'Austin',
					'Jacksonville'    => 'Jacksonville',
					'Fort Worth'      => 'Fort Worth',
					'Columbus'        => 'Columbus',
					'Charlotte'       => 'Charlotte',
					'San Francisco'   => 'San Francisco',
					'Indianapolis'    => 'Indianapolis',
					'Seattle'         => 'Seattle',
					'Denver'          => 'Denver',
					'Washington'      => 'Washington',
					'Boston'          => 'Boston',
					'El Paso'         => 'El Paso',
					'Nashville'       => 'Nashville',
					'Detroit'         => 'Detroit',
					'Oklahoma City'   => 'Oklahoma City',
					'Portland'        => 'Portland',
					'Las Vegas'       => 'Las Vegas',
					'Memphis'         => 'Memphis',
					'Louisville'      => 'Louisville',
					'Baltimore'       => 'Baltimore',
					'Milwaukee'       => 'Milwaukee',
					'Albuquerque'     => 'Albuquerque',
					'Tucson'          => 'Tucson',
					'Fresno'          => 'Fresno',
					'Sacramento'      => 'Sacramento',
					'Mesa'            => 'Mesa',
					'Kansas City'     => 'Kansas City',
					'Atlanta'         => 'Atlanta',
					'Miami'           => 'Miami',
					'Colorado Springs' => 'Colorado Springs',
					'Raleigh'         => 'Raleigh',
					'Omaha'           => 'Omaha',
					'Long Beach'      => 'Long Beach',
					'Virginia Beach'  => 'Virginia Beach',
					// Canada
					'Toronto'         => 'Toronto',
					'Montreal'        => 'Montreal',
					'Vancouver'       => 'Vancouver',
					'Calgary'         => 'Calgary',
					'Edmonton'        => 'Edmonton',
					'Ottawa'          => 'Ottawa',
					'Winnipeg'        => 'Winnipeg',
					'Quebec City'     => 'Quebec City',
					'Hamilton'        => 'Hamilton',
					// Latin America
					'Mexico City'     => 'Mexico By',
					'Guadalajara'     => 'Guadalajara',
					'Monterrey'       => 'Monterrey',
					'São Paulo'       => 'São Paulo',
					'Rio de Janeiro'  => 'Rio de Janeiro',
					'Brasília'        => 'Brasília',
					'Salvador'        => 'Salvador',
					'Buenos Aires'    => 'Buenos Aires',
					'Santiago'        => 'Santiago',
					'Lima'            => 'Lima',
					'Bogotá'          => 'Bogotá',
					'Caracas'         => 'Caracas',
					// Asia - China & Japan
					'Beijing'         => 'Beijing',
					'Shanghai'        => 'Shanghai',
					'Guangzhou'       => 'Guangzhou',
					'Shenzhen'        => 'Shenzhen',
					'Chengdu'         => 'Chengdu',
					'Wuhan'           => 'Wuhan',
					'Hangzhou'        => 'Hangzhou',
					'Xi\'an'          => 'Xi\'an',
					'Tokyo'           => 'Tokyo',
					'Yokohama'        => 'Yokohama',
					'Osaka'           => 'Osaka',
					'Nagoya'          => 'Nagoya',
					'Sapporo'         => 'Sapporo',
					'Fukuoka'         => 'Fukuoka',
					'Kobe'            => 'Kobe',
					'Kyoto'           => 'Kyoto',
					'Hong Kong'       => 'Hong Kong',
					// Asia - Other
					'Seoul'           => 'Seoul',
					'Busan'           => 'Busan',
					'Incheon'         => 'Incheon',
					'Bangkok'         => 'Bangkok',
					'Manila'          => 'Manila',
					'Jakarta'         => 'Jakarta',
					'Singapore'       => 'Singapore',
					'Kuala Lumpur'    => 'Kuala Lumpur',
					'Ho Chi Minh City' => 'Ho Chi Minh City',
					'Hanoi'           => 'Hanoi',
					'New Delhi'       => 'New Delhi',
					'Mumbai'          => 'Mumbai',
					'Bangalore'       => 'Bangalore',
					'Kolkata'         => 'Kolkata',
					'Chennai'         => 'Chennai',
					'Hyderabad'       => 'Hyderabad',
					'Dubai'           => 'Dubai',
					'Abu Dhabi'       => 'Abu Dhabi',
					'Tel Aviv'        => 'Tel Aviv',
					'Jerusalem'       => 'Jerusalem',
					'Riyadh'          => 'Riyadh',
					'Tehran'          => 'Teheran',
					'Baghdad'         => 'Bagdad',
					'Istanbul'        => 'Istanbul',
					'Ankara'          => 'Ankara',
					// Oceania
					'Sydney'          => 'Sydney',
					'Melbourne'       => 'Melbourne',
					'Brisbane'        => 'Brisbane',
					'Perth'           => 'Perth',
					'Adelaide'        => 'Adelaide',
					'Auckland'        => 'Auckland',
					'Wellington'      => 'Wellington',
					'Christchurch'    => 'Christchurch',
					// Africa
					'Cairo'           => 'Cairo',
					'Alexandria'      => 'Alexandria',
					'Johannesburg'    => 'Johannesburg',
					'Cape Town'       => 'Kapstaden',
					'Durban'          => 'Durban',
					'Pretoria'        => 'Pretoria',
					'Nairobi'         => 'Nairobi',
					'Lagos'           => 'Lagos',
					'Kinshasa'        => 'Kinshasa',
					'Casablanca'      => 'Casablanca',
					'Rabat'           => 'Rabat',
					'Algiers'         => 'Algier',
					'Tunis'           => 'Tunis',
					'Addis Ababa'     => 'Addis Abeba',
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
