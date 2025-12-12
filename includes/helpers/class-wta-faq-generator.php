<?php
/**
 * FAQ Generator for City Pages.
 *
 * Generates FAQ content using a hybrid approach:
 * - Template-based FAQ (data-driven, no AI cost)
 * - Light AI FAQ (1 sentence variation)
 * - Full AI FAQ (contextual, unique content)
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 * @since      2.35.0
 */

class WTA_FAQ_Generator {

	/**
	 * Generate complete FAQ for a city.
	 *
	 * Returns 12 FAQ items with intro text.
	 *
	 * @since    2.35.0
	 * @param    int   $post_id  City post ID.
	 * @param    bool  $test_mode Whether in test mode (no AI calls).
	 * @return   array|false     FAQ data or false on failure.
	 */
	public static function generate_city_faq( $post_id, $test_mode = false ) {
		// Get city data
		$city_name = get_the_title( $post_id );
		$timezone = get_post_meta( $post_id, 'wta_timezone', true );
		$latitude = get_post_meta( $post_id, 'wta_latitude', true );
		$longitude = get_post_meta( $post_id, 'wta_longitude', true );
		
		// Get parent country
		$parent_country_id = wp_get_post_parent_id( $post_id );
		$country_name = $parent_country_id ? get_the_title( $parent_country_id ) : '';
		
		if ( empty( $city_name ) || empty( $timezone ) ) {
			WTA_Logger::warning( 'Missing required data for FAQ generation', array( 'post_id' => $post_id ) );
			return false;
		}
		
		// Generate FAQ intro (AI or template)
		$intro = $test_mode 
			? self::generate_template_intro( $city_name )
			: self::generate_ai_intro( $city_name );
		
		// Generate FAQ items
		$faqs = array();
		
		// TIER 1: Template-based FAQ (5 items)
		$faqs[] = self::generate_current_time_faq( $city_name, $timezone );
		$faqs[] = self::generate_timezone_faq( $city_name, $timezone );
		$faqs[] = self::generate_sun_times_faq( $city_name, $latitude, $longitude );
		$faqs[] = self::generate_moon_phase_faq( $city_name );
		$faqs[] = self::generate_geography_faq( $city_name, $latitude, $longitude, $country_name );
		
		// TIER 2: Light AI FAQ (3 items) - only if not test mode
		if ( ! $test_mode ) {
			$faqs[] = self::generate_time_difference_faq( $city_name, $timezone, $test_mode );
			$faqs[] = self::generate_season_faq( $city_name, $latitude, $test_mode );
			$faqs[] = self::generate_dst_faq( $city_name, $timezone, $test_mode );
		} else {
			// Template fallbacks for test mode
			$faqs[] = self::generate_time_difference_faq_template( $city_name, $timezone );
			$faqs[] = self::generate_season_faq_template( $city_name, $latitude );
			$faqs[] = self::generate_dst_faq_template( $city_name, $timezone );
		}
		
		// TIER 3: Full AI FAQ (4 items) - batched in single call
		if ( ! $test_mode ) {
			$ai_faqs = self::generate_ai_faqs_batch( $city_name, $country_name, $timezone );
			if ( ! empty( $ai_faqs ) ) {
				$faqs = array_merge( $faqs, $ai_faqs );
			} else {
				// Fallback to template if AI fails
				$faqs[] = self::generate_calling_hours_faq_template( $city_name, $timezone );
				$faqs[] = self::generate_jetlag_faq_template( $city_name, $timezone );
				$faqs[] = self::generate_culture_faq_template( $city_name, $country_name );
				$faqs[] = self::generate_travel_time_faq_template( $city_name, $latitude );
			}
		} else {
			// Test mode: template versions
			$faqs[] = self::generate_calling_hours_faq_template( $city_name, $timezone );
			$faqs[] = self::generate_jetlag_faq_template( $city_name, $timezone );
			$faqs[] = self::generate_culture_faq_template( $city_name, $country_name );
			$faqs[] = self::generate_travel_time_faq_template( $city_name, $latitude );
		}
		
		return array(
			'intro' => $intro,
			'faqs'  => $faqs,
		);
	}

	/**
	 * Generate FAQ intro text (AI).
	 *
	 * @since    2.35.0
	 * @param    string $city_name City name.
	 * @return   string            Intro text.
	 */
	private static function generate_ai_intro( $city_name ) {
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return self::generate_template_intro( $city_name );
		}
		
		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		$system = 'Du skriver korte, hjælpsomme introduktioner til FAQ sektioner på dansk. Ingen placeholders.';
		$user = "Skriv 2-3 korte sætninger der introducerer FAQ-sektionen om tid i {$city_name}. Forklar kort hvad brugere kan finde svar på (tidszone, tidsforskel, praktiske tips). Tone: Hjælpsom og direkte. Max 50 ord. INGEN placeholders.";
		
		$intro = self::call_openai_simple( $api_key, $model, $system, $user, 80 );
		
		// Fallback to template if AI fails
		if ( false === $intro || empty( $intro ) ) {
			return self::generate_template_intro( $city_name );
		}
		
		return $intro;
	}

	/**
	 * Generate template intro (fallback).
	 *
	 * @since    2.35.0
	 * @param    string $city_name City name.
	 * @return   string            Intro text.
	 */
	private static function generate_template_intro( $city_name ) {
		return "Her finder du svar på de mest almindelige spørgsmål om tid i {$city_name}. Vi dækker alt fra aktuel tid og tidszone til praktiske rejsetips og tidsforskel til Danmark. Scroll gennem spørgsmålene nedenfor for at finde præcis det du søger.";
	}

	/**
	 * FAQ 1: Current time (template).
	 *
	 * @since    2.35.0
	 */
	private static function generate_current_time_faq( $city_name, $timezone ) {
		// Get current time (will be dynamic on page load via JavaScript)
		$current_time = WTA_Timezone_Helper::get_current_time_in_timezone( $timezone, 'H:i:s' );
		
		// Get UTC offset
		try {
			$dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
			$utc_offset = $dt->format( 'P' );
		} catch ( Exception $e ) {
			$utc_offset = '';
		}
		
		$answer = "Klokken i {$city_name} er <strong id=\"faq-live-time\">{$current_time}</strong>. Byen ligger i tidszonen {$timezone}" . 
		          ( ! empty( $utc_offset ) ? " (UTC{$utc_offset})" : '' ) . 
		          ". Tiden opdateres automatisk, så du altid ser den aktuelle tid.";
		
		return array(
			'question' => "Hvad er klokken i {$city_name} lige nu?",
			'answer'   => $answer,
			'icon'     => '⏰',
		);
	}

	/**
	 * FAQ 2: Timezone info (template).
	 *
	 * @since    2.35.0
	 */
	private static function generate_timezone_faq( $city_name, $timezone ) {
		try {
			$dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
			$utc_offset = $dt->format( 'P' );
			$abbr = $dt->format( 'T' );
		} catch ( Exception $e ) {
			$utc_offset = '';
			$abbr = '';
		}
		
		$answer = "{$city_name} bruger tidszonen <strong>{$timezone}</strong>";
		if ( ! empty( $utc_offset ) ) {
			$answer .= " med UTC offset på <strong>{$utc_offset}</strong>";
		}
		if ( ! empty( $abbr ) ) {
			$answer .= " ({$abbr})";
		}
		$answer .= ". Dette er den officielle IANA tidszone identifier for byen.";
		
		return array(
			'question' => "Hvad er tidszonen i {$city_name}?",
			'answer'   => $answer,
			'icon'     => '🌍',
		);
	}

	/**
	 * FAQ 3: Sun times (template with live data).
	 *
	 * @since    2.35.0
	 */
	private static function generate_sun_times_faq( $city_name, $latitude, $longitude ) {
		if ( empty( $latitude ) || empty( $longitude ) ) {
			$answer = "Solopgang og solnedgangstider varierer dagligt i {$city_name} baseret på årstiden og byens geografiske placering.";
		} else {
			// Calculate today's sun times
			$sunrise = date_sunrise( time(), SUNFUNCS_RET_STRING, $latitude, $longitude );
			$sunset = date_sunset( time(), SUNFUNCS_RET_STRING, $latitude, $longitude );
			
			if ( $sunrise && $sunset ) {
				// Calculate day length
				$sunrise_ts = strtotime( $sunrise );
				$sunset_ts = strtotime( $sunset );
				$day_length_seconds = $sunset_ts - $sunrise_ts;
				$hours = floor( $day_length_seconds / 3600 );
				$minutes = floor( ( $day_length_seconds % 3600 ) / 60 );
				$day_length = sprintf( '%02d:%02d', $hours, $minutes );
				
				$answer = "I dag går solen op kl. <strong>{$sunrise}</strong> og ned kl. <strong>{$sunset}</strong> i {$city_name}. Dagens længde er <strong>{$day_length}</strong> timer. Disse tider ændrer sig dagligt baseret på årstiden.";
			} else {
				$answer = "Solopgang og solnedgangstider varierer dagligt i {$city_name} baseret på årstiden og byens geografiske placering.";
			}
		}
		
		return array(
			'question' => "Hvornår går solen op og ned i {$city_name}?",
			'answer'   => $answer,
			'icon'     => '🌅',
		);
	}

	/**
	 * FAQ 4: Moon phase (template with calculation).
	 *
	 * @since    2.35.0
	 */
	private static function generate_moon_phase_faq( $city_name ) {
		// Calculate moon phase (simple algorithm)
		$moon_data = self::calculate_moon_phase();
		
		$answer = "Månefasen i {$city_name} er aktuelt <strong>{$moon_data['percentage']}%</strong> ({$moon_data['phase_name']}). Månen er synlig i nattehimlen over byen, og fasen ændrer sig dagligt i løbet af månens 29,5-dages cyklus.";
		
		return array(
			'question' => "Hvad er månefasen i {$city_name}?",
			'answer'   => $answer,
			'icon'     => '🌙',
		);
	}

	/**
	 * FAQ 5: Geography (template).
	 *
	 * @since    2.35.0
	 */
	private static function generate_geography_faq( $city_name, $latitude, $longitude, $country_name ) {
		$lat_dir = ( $latitude >= 0 ) ? 'N' : 'S';
		$lon_dir = ( $longitude >= 0 ) ? 'Ø' : 'V';
		$hemisphere = ( $latitude >= 0 ) ? 'nordlige' : 'sydlige';
		
		$answer = "{$city_name} ligger på koordinaterne <strong>" . number_format( abs( $latitude ), 4 ) . "° {$lat_dir}, " . 
		          number_format( abs( $longitude ), 4 ) . "° {$lon_dir}</strong>";
		if ( ! empty( $country_name ) ) {
			$answer .= " i {$country_name}";
		}
		$answer .= ". Byen befinder sig på den {$hemisphere} halvkugle.";
		
		return array(
			'question' => "Hvor ligger {$city_name} geografisk?",
			'answer'   => $answer,
			'icon'     => '📍',
		);
	}

	/**
	 * FAQ 6: Time difference (light AI + template).
	 *
	 * @since    2.35.0
	 */
	private static function generate_time_difference_faq( $city_name, $timezone, $test_mode = false ) {
		// Calculate time difference to Denmark
		$diff_hours = self::calculate_time_difference( $timezone, 'Europe/Copenhagen' );
		
		$base_answer = "Tidsforskellen mellem {$city_name} og Danmark er <strong>{$diff_hours} timer</strong>.";
		
		if ( $test_mode ) {
			$base_answer .= " Dette betyder at når klokken er 12:00 i Danmark, er klokken " . 
			                self::format_time_with_offset( 12, 0, $diff_hours ) . " i {$city_name}.";
		} else {
			// Add 1 AI sentence for variation
			$api_key = get_option( 'wta_openai_api_key', '' );
			if ( ! empty( $api_key ) ) {
				$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
				$system = 'Skriv 1 praktisk sætning på dansk. Ingen placeholders.';
				$user = "Skriv 1 praktisk eksempel på tidsforskel mellem {$city_name} og Danmark (forskel: {$diff_hours} timer). F.eks. 'når klokken er 12:00 i Danmark...'. Max 25 ord. INGEN placeholders.";
				
				$ai_sentence = self::call_openai_simple( $api_key, $model, $system, $user, 40 );
				if ( false !== $ai_sentence && ! empty( $ai_sentence ) ) {
					$base_answer .= ' ' . $ai_sentence;
				}
			}
		}
		
		return array(
			'question' => "Hvad er tidsforskellen mellem {$city_name} og Danmark?",
			'answer'   => $base_answer,
			'icon'     => '⏰',
		);
	}

	/**
	 * FAQ 6 template version.
	 *
	 * @since    2.35.0
	 */
	private static function generate_time_difference_faq_template( $city_name, $timezone ) {
		$diff_hours = self::calculate_time_difference( $timezone, 'Europe/Copenhagen' );
		
		$answer = "Tidsforskellen mellem {$city_name} og Danmark er <strong>{$diff_hours} timer</strong>. Dette betyder at når klokken er 12:00 i Danmark, er klokken " . 
		          self::format_time_with_offset( 12, 0, $diff_hours ) . " i {$city_name}.";
		
		return array(
			'question' => "Hvad er tidsforskellen mellem {$city_name} og Danmark?",
			'answer'   => $answer,
			'icon'     => '⏰',
		);
	}

	/**
	 * FAQ 7: Season (light AI + template).
	 *
	 * @since    2.35.0
	 */
	private static function generate_season_faq( $city_name, $latitude, $test_mode = false ) {
		$season = self::get_current_season( $latitude );
		$hemisphere = ( $latitude >= 0 ) ? 'nordlige' : 'sydlige';
		
		$base_answer = "Det er aktuelt <strong>{$season}</strong> i {$city_name}. Byen ligger på den {$hemisphere} halvkugle, hvilket påvirker sæsonerne og dagslængden.";
		
		if ( ! $test_mode ) {
			// Add 1 AI sentence about season context
			$api_key = get_option( 'wta_openai_api_key', '' );
			if ( ! empty( $api_key ) ) {
				$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
				$system = 'Skriv 1 sætning på dansk om vejr eller dagslængde. Ingen placeholders.';
				$user = "Skriv 1 sætning om hvordan {$season} er i {$city_name} (lat: {$latitude}). Nævn vejr eller dagslængde. Max 25 ord. INGEN placeholders.";
				
				$ai_sentence = self::call_openai_simple( $api_key, $model, $system, $user, 40 );
				if ( false !== $ai_sentence && ! empty( $ai_sentence ) ) {
					$base_answer .= ' ' . $ai_sentence;
				}
			}
		}
		
		return array(
			'question' => "Hvilken sæson er det i {$city_name}?",
			'answer'   => $base_answer,
			'icon'     => '🍂',
		);
	}

	/**
	 * FAQ 7 template version.
	 *
	 * @since    2.35.0
	 */
	private static function generate_season_faq_template( $city_name, $latitude ) {
		$season = self::get_current_season( $latitude );
		$hemisphere = ( $latitude >= 0 ) ? 'nordlige' : 'sydlige';
		
		$answer = "Det er aktuelt <strong>{$season}</strong> i {$city_name}. Byen ligger på den {$hemisphere} halvkugle, hvilket påvirker sæsonerne og dagslængden.";
		
		return array(
			'question' => "Hvilken sæson er det i {$city_name}?",
			'answer'   => $answer,
			'icon'     => '🍂',
		);
	}

	/**
	 * FAQ 8: DST info (light AI + template).
	 *
	 * @since    2.35.0
	 */
	private static function generate_dst_faq( $city_name, $timezone, $test_mode = false ) {
		// Check if timezone uses DST
		$uses_dst = self::timezone_uses_dst( $timezone );
		
		if ( $uses_dst ) {
			$base_answer = "{$city_name} <strong>bruger sommertid</strong>. Uret stilles frem om foråret og tilbage om efteråret for at udnytte dagslyset bedre.";
		} else {
			$base_answer = "{$city_name} <strong>bruger ikke sommertid</strong>. Tiden forbliver den samme året rundt.";
		}
		
		if ( ! $test_mode && $uses_dst ) {
			// Add 1 AI sentence about DST impact
			$api_key = get_option( 'wta_openai_api_key', '' );
			if ( ! empty( $api_key ) ) {
				$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
				$system = 'Skriv 1 sætning på dansk. Ingen placeholders.';
				$user = "Skriv 1 sætning om hvordan sommertid påvirker tid i {$city_name}. Max 20 ord. INGEN placeholders.";
				
				$ai_sentence = self::call_openai_simple( $api_key, $model, $system, $user, 35 );
				if ( false !== $ai_sentence && ! empty( $ai_sentence ) ) {
					$base_answer .= ' ' . $ai_sentence;
				}
			}
		}
		
		return array(
			'question' => "Bruger {$city_name} sommertid?",
			'answer'   => $base_answer,
			'icon'     => '☀️',
		);
	}

	/**
	 * FAQ 8 template version.
	 *
	 * @since    2.35.0
	 */
	private static function generate_dst_faq_template( $city_name, $timezone ) {
		$uses_dst = self::timezone_uses_dst( $timezone );
		
		if ( $uses_dst ) {
			$answer = "{$city_name} <strong>bruger sommertid</strong>. Uret stilles frem om foråret og tilbage om efteråret for at udnytte dagslyset bedre.";
		} else {
			$answer = "{$city_name} <strong>bruger ikke sommertid</strong>. Tiden forbliver den samme året rundt.";
		}
		
		return array(
			'question' => "Bruger {$city_name} sommertid?",
			'answer'   => $answer,
			'icon'     => '☀️',
		);
	}

	/**
	 * Generate 4 full AI FAQ in single batched call.
	 *
	 * @since    2.35.0
	 */
	private static function generate_ai_faqs_batch( $city_name, $country_name, $timezone ) {
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return array();
		}
		
		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
		
		// Calculate time difference for context
		$diff_hours = self::calculate_time_difference( $timezone, 'Europe/Copenhagen' );
		
		// Batched prompt for all 4 AI FAQ
		$system = 'Du er ekspert i at skrive FAQ svar på dansk. Svar skal være praktiske og hjælpsomme. INGEN placeholders.';
		$user = "Skriv FAQ svar for {$city_name}, {$country_name}.

FAQ 1: Hvornår skal jeg ringe til {$city_name} fra Danmark?
Tidsforskel: {$diff_hours} timer. Skriv 2-3 praktiske sætninger (max 60 ord).

FAQ 2: Hvad skal jeg vide om tidskultur i {$city_name}?
Skriv 2-3 sætninger om arbejdstider, måltider, lokale tidsvaner (max 60 ord).

FAQ 3: Hvordan undgår jeg jetlag til {$city_name}?
Tidsforskel: {$diff_hours} timer. Skriv 2 jetlag-tips (max 50 ord).

FAQ 4: Hvad er bedste rejsetidspunkt til {$city_name}?
Skriv 2 sætninger om vejr og turistsæson (max 50 ord).

Svar i JSON format:
{
  \"faq1\": \"...\",
  \"faq2\": \"...\",
  \"faq3\": \"...\",
  \"faq4\": \"...\"
}

INGEN placeholders. KUN faktisk indhold.";
		
		$response = self::call_openai_simple( $api_key, $model, $system, $user, 500 );
		
		if ( false === $response || empty( $response ) ) {
			WTA_Logger::warning( 'Failed to generate AI FAQ batch', array( 'city' => $city_name ) );
			return array();
		}
		
		// Parse JSON response
		$json_data = json_decode( $response, true );
		
		if ( ! is_array( $json_data ) || ! isset( $json_data['faq1'] ) ) {
			WTA_Logger::warning( 'Invalid AI FAQ JSON response', array( 'response' => $response ) );
			return array();
		}
		
		return array(
			array(
				'question' => "Hvornår skal jeg ringe til {$city_name} fra Danmark?",
				'answer'   => $json_data['faq1'],
				'icon'     => '📞',
			),
			array(
				'question' => "Hvad skal jeg vide om tidskultur i {$city_name}?",
				'answer'   => $json_data['faq2'],
				'icon'     => '🕐',
			),
			array(
				'question' => "Hvordan undgår jeg jetlag til {$city_name}?",
				'answer'   => $json_data['faq3'],
				'icon'     => '🌐',
			),
			array(
				'question' => "Hvad er bedste tidspunkt at besøge {$city_name}?",
				'answer'   => $json_data['faq4'],
				'icon'     => '✈️',
			),
		);
	}

	/**
	 * FAQ 9-12: Template fallbacks.
	 *
	 * @since    2.35.0
	 */
	private static function generate_calling_hours_faq_template( $city_name, $timezone ) {
		$diff_hours = self::calculate_time_difference( $timezone, 'Europe/Copenhagen' );
		
		$answer = "For at ringe til {$city_name} fra Danmark, skal du tage højde for tidsforskellen på {$diff_hours} timer. Bedste tidspunkt er typisk mellem kl. 10:00-17:00 dansk tid, så du rammer arbejdstiden i {$city_name}.";
		
		return array(
			'question' => "Hvornår skal jeg ringe til {$city_name} fra Danmark?",
			'answer'   => $answer,
			'icon'     => '📞',
		);
	}

	private static function generate_jetlag_faq_template( $city_name, $timezone ) {
		$diff_hours = self::calculate_time_difference( $timezone, 'Europe/Copenhagen' );
		
		$answer = "Med en tidsforskel på {$diff_hours} timer til {$city_name}, kan du undgå jetlag ved at tilpasse din søvnrytme gradvist før afrejse og få meget lys de første dage efter ankomst.";
		
		return array(
			'question' => "Hvordan undgår jeg jetlag til {$city_name}?",
			'answer'   => $answer,
			'icon'     => '🌐',
		);
	}

	private static function generate_culture_faq_template( $city_name, $country_name ) {
		$answer = "I {$city_name} følger man lokale tidsvaner og arbejdstider. Det er en god idé at researche lokale skikke vedrørende måltider og arbejdstid før dit besøg i {$country_name}.";
		
		return array(
			'question' => "Hvad skal jeg vide om tidskultur i {$city_name}?",
			'answer'   => $answer,
			'icon'     => '🕐',
		);
	}

	private static function generate_travel_time_faq_template( $city_name, $latitude ) {
		$hemisphere = ( $latitude >= 0 ) ? 'nordlige' : 'sydlige';
		$answer = "{$city_name} ligger på den {$hemisphere} halvkugle. Bedste rejsetidspunkt afhænger af vejret og turistsæsonen, men generelt er forårs- og efterårsmånederne ofte gode valg.";
		
		return array(
			'question' => "Hvad er bedste tidspunkt at besøge {$city_name}?",
			'answer'   => $answer,
			'icon'     => '✈️',
		);
	}

	/**
	 * Helper: Calculate time difference in hours.
	 *
	 * @since    2.35.0
	 */
	private static function calculate_time_difference( $tz1, $tz2 ) {
		try {
			$dt1 = new DateTime( 'now', new DateTimeZone( $tz1 ) );
			$dt2 = new DateTime( 'now', new DateTimeZone( $tz2 ) );
			
			$offset1 = $dt1->getOffset();
			$offset2 = $dt2->getOffset();
			
			$diff_seconds = $offset1 - $offset2;
			$diff_hours = $diff_seconds / 3600;
			
			// Format as +X or -X hours
			if ( $diff_hours > 0 ) {
				return '+' . $diff_hours;
			} elseif ( $diff_hours < 0 ) {
				return $diff_hours;
			} else {
				return '0';
			}
		} catch ( Exception $e ) {
			return '0';
		}
	}

	/**
	 * Helper: Format time with offset.
	 *
	 * @since    2.35.0
	 */
	private static function format_time_with_offset( $hour, $minute, $offset_str ) {
		$offset = floatval( str_replace( '+', '', $offset_str ) );
		$new_hour = $hour + $offset;
		
		// Handle day wraparound
		if ( $new_hour >= 24 ) {
			$new_hour -= 24;
		} elseif ( $new_hour < 0 ) {
			$new_hour += 24;
		}
		
		return sprintf( '%02d:%02d', $new_hour, $minute );
	}

	/**
	 * Helper: Get current season based on latitude.
	 *
	 * @since    2.35.0
	 */
	private static function get_current_season( $latitude ) {
		$month = (int) date( 'n' );
		$is_northern = $latitude >= 0;
		
		// Northern hemisphere
		if ( $is_northern ) {
			if ( $month >= 3 && $month <= 5 ) {
				return 'forår';
			} elseif ( $month >= 6 && $month <= 8 ) {
				return 'sommer';
			} elseif ( $month >= 9 && $month <= 11 ) {
				return 'efterår';
			} else {
				return 'vinter';
			}
		} else {
			// Southern hemisphere (reversed)
			if ( $month >= 3 && $month <= 5 ) {
				return 'efterår';
			} elseif ( $month >= 6 && $month <= 8 ) {
				return 'vinter';
			} elseif ( $month >= 9 && $month <= 11 ) {
				return 'forår';
			} else {
				return 'sommer';
			}
		}
	}

	/**
	 * Helper: Check if timezone uses DST.
	 *
	 * @since    2.35.0
	 */
	private static function timezone_uses_dst( $timezone ) {
		try {
			$tz = new DateTimeZone( $timezone );
			$transitions = $tz->getTransitions( time(), time() + 31536000 ); // Check next 12 months
			
			// If more than 1 transition, timezone uses DST
			return count( $transitions ) > 1;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Helper: Calculate moon phase.
	 *
	 * @since    2.35.0
	 * @return   array Moon data with percentage and phase name.
	 */
	private static function calculate_moon_phase() {
		// Known new moon: 2000-01-06 18:14 UTC
		$known_new_moon = 947182440;
		$synodic_month = 29.53058867; // days
		
		$current_time = time();
		$age_days = ( $current_time - $known_new_moon ) / 86400;
		$phase_days = fmod( $age_days, $synodic_month );
		
		$percentage = ( $phase_days / $synodic_month ) * 100;
		
		// Determine phase name
		if ( $phase_days < 1.84566 ) {
			$phase_name = 'Nymåne';
		} elseif ( $phase_days < 5.53699 ) {
			$phase_name = 'Voksende halvmåne';
		} elseif ( $phase_days < 9.22831 ) {
			$phase_name = 'Første kvartal';
		} elseif ( $phase_days < 12.91963 ) {
			$phase_name = 'Voksende måne';
		} elseif ( $phase_days < 16.61096 ) {
			$phase_name = 'Fuldmåne';
		} elseif ( $phase_days < 20.30228 ) {
			$phase_name = 'Aftagende måne';
		} elseif ( $phase_days < 23.99361 ) {
			$phase_name = 'Sidste kvartal';
		} else {
			$phase_name = 'Aftagende halvmåne';
		}
		
		return array(
			'percentage'  => number_format( $percentage, 1 ),
			'phase_name'  => $phase_name,
		);
	}

	/**
	 * Simple OpenAI API call.
	 *
	 * @since    2.35.0
	 */
	private static function call_openai_simple( $api_key, $model, $system, $user, $max_tokens = 100 ) {
		$url = 'https://api.openai.com/v1/chat/completions';
		
		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			'temperature' => 0.7,
			'max_tokens'  => $max_tokens,
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
			WTA_Logger::error( 'OpenAI FAQ API request failed', array(
				'error' => $response->get_error_message(),
			) );
			return false;
		}
		
		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );
		
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			WTA_Logger::error( 'OpenAI FAQ API returned unexpected response', array(
				'response' => $data,
			) );
			return false;
		}
		
		$content = trim( $data['choices'][0]['message']['content'] );
		
		// Remove surrounding quotes if present
		if ( ( str_starts_with( $content, '"' ) && str_ends_with( $content, '"' ) ) ||
		     ( str_starts_with( $content, "'" ) && str_ends_with( $content, "'" ) ) ) {
			$content = substr( $content, 1, -1 );
		}
		
		return $content;
	}
}

