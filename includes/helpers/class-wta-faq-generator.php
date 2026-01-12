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
	
	// v3.2.13: Use language-aware prompts from JSON (loaded via "Load Default Prompts")
	$system = get_option( 'wta_prompt_faq_intro_system', 'Du skriver korte, hjælpsomme introduktioner til FAQ sektioner på dansk. Ingen placeholders.' );
	$user = get_option( 'wta_prompt_faq_intro_user', 'Skriv 2-3 korte sætninger der introducerer FAQ-sektionen om tid i {city_name}. Forklar kort hvad brugere kan finde svar på (tidszone, tidsforskel, praktiske tips). Tone: Hjælpsom og direkte. Max 50 ord. INGEN placeholders.' );
	
	// Replace {city_name} placeholder
	$user = str_replace( '{city_name}', $city_name, $user );
	
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
	 * @since    3.2.0  Updated to use language-aware templates.
	 * @param    string $city_name City name.
	 * @return   string            Intro text.
	 */
	private static function generate_template_intro( $city_name ) {
		return self::get_faq_text( 'intro_template', array( 'city_name' => $city_name ) );
	}

	/**
	 * Get language template string.
	 *
	 * @since    3.2.0
	 * @param    string $key Template key (e.g., 'faq_intro')
	 * @return   string Template string with %s placeholders
	 */
	private static function get_template( $key ) {
		static $templates_cache = null;
		
		// Load templates once
		if ( $templates_cache === null ) {
			// Try to get from WordPress options (loaded via "Load Default Prompts")
			$templates = get_option( 'wta_templates', array() );
			
			if ( ! empty( $templates ) && is_array( $templates ) ) {
				$templates_cache = $templates;
			} else {
				// Fallback to empty array
				$templates_cache = array();
			}
		}
		
		return isset( $templates_cache[ $key ] ) ? $templates_cache[ $key ] : '';
	}

	/**
	 * FAQ 1: Current time (template).
	 *
	 * @since    2.35.0
	 */
	private static function generate_current_time_faq( $city_name, $timezone ) {
		// Get current time - server-rendered for SEO, then updated live by JavaScript
		// v3.3.14: Hybrid approach for best UX + SEO
		$current_time = WTA_Timezone_Helper::get_current_time_in_timezone( $timezone, 'H:i:s' );
		
		// Get UTC offset
		try {
			$dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
			$utc_offset = $dt->format( 'P' );
		} catch ( Exception $e ) {
			$utc_offset = '';
		}
		
	// Format UTC offset for answer
	$utc_offset_formatted = ! empty( $utc_offset ) ? " (UTC{$utc_offset})" : '';
	
	// v3.3.14: Wrap time in span for JavaScript live updates
	// Server renders valid time (good for SEO/crawlers), JavaScript updates it live (good for users)
	$current_time_html = '<span class="wta-live-faq-time" data-timezone="' . esc_attr( $timezone ) . '">' . 
	                     esc_html( $current_time ) . '</span>';
	
	$question = self::get_faq_text( 'faq1_question', array( 'city_name' => $city_name ) );
	$answer = self::get_faq_text( 'faq1_answer', array(
		'city_name' => $city_name,
		'current_time' => $current_time_html,
		'timezone' => $timezone,
		'utc_offset' => $utc_offset_formatted
	) );
	
	return array(
		'question' => $question,
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
		
	$question = self::get_faq_text( 'faq2_question', array( 'city_name' => $city_name ) );
	$answer = self::get_faq_text( 'faq2_answer', array(
		'city_name' => $city_name,
		'timezone' => $timezone,
		'utc_offset' => $utc_offset,
		'abbr' => $abbr
	) );
	
	return array(
		'question' => $question,
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
	$question = self::get_faq_text( 'faq3_question', array( 'city_name' => $city_name ) );
	
	if ( empty( $latitude ) || empty( $longitude ) ) {
		$answer = self::get_faq_text( 'faq3_answer_fallback', array( 'city_name' => $city_name ) );
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
			
			$answer = self::get_faq_text( 'faq3_answer', array(
				'sunrise' => $sunrise,
				'sunset' => $sunset,
				'city_name' => $city_name,
				'day_length' => $day_length
			) );
		} else {
			$answer = self::get_faq_text( 'faq3_answer_fallback', array( 'city_name' => $city_name ) );
		}
	}
	
	return array(
		'question' => $question,
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
	
	$question = self::get_faq_text( 'faq4_question', array( 'city_name' => $city_name ) );
	$answer = self::get_faq_text( 'faq4_answer', array(
		'city_name' => $city_name,
		'moon_percentage' => $moon_data['percentage'],
		'moon_phase' => $moon_data['phase_name']
	) );
	
	return array(
		'question' => $question,
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
	
	$question = self::get_faq_text( 'faq5_question', array( 'city_name' => $city_name ) );
	
	$faq_key = ! empty( $country_name ) ? 'faq5_answer' : 'faq5_answer_no_country';
	$answer = self::get_faq_text( $faq_key, array(
		'city_name' => $city_name,
		'latitude' => number_format( abs( $latitude ), 4 ),
		'lat_dir' => $lat_dir,
		'longitude' => number_format( abs( $longitude ), 4 ),
		'lon_dir' => $lon_dir,
		'country_name' => $country_name,
		'hemisphere' => $hemisphere
	) );
	
	return array(
		'question' => $question,
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
	// v3.2.15: Calculate time difference to base timezone (from settings, not hardcoded)
	$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
	$diff_hours = self::calculate_time_difference( $timezone, $base_timezone );
		$example_time = self::format_time_with_offset( 12, 0, $diff_hours );
		
		// Get question and answer from language pack
		$question = self::get_faq_text( 'faq6_question', array( 'city_name' => $city_name ) );
		$answer = self::get_faq_text( 'faq6_answer', array(
			'city_name' => $city_name,
			'diff_hours' => $diff_hours,
			'example_time' => $example_time
		) );
		
		// Optionally add AI sentence for variation (only in normal mode)
		if ( ! $test_mode ) {
			$api_key = get_option( 'wta_openai_api_key', '' );
			if ( ! empty( $api_key ) ) {
				$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
				$site_lang = get_option( 'wta_site_language', 'da' );
				$lang_desc = get_option( 'wta_base_language_description', 'Skriv på flydende dansk til danske brugere' );
				
				$system = "Skriv 1 praktisk sætning på {$site_lang}. Ingen placeholders.";
				$user = "{$lang_desc}. Skriv 1 praktisk eksempel på tidsforskel mellem {$city_name} og brugerens land (forskel: {$diff_hours} timer). F.eks. 'når klokken er 12:00...'. Max 25 ord. INGEN placeholders.";
				
				$ai_sentence = self::call_openai_simple( $api_key, $model, $system, $user, 40 );
				if ( false !== $ai_sentence && ! empty( $ai_sentence ) ) {
					$answer .= ' ' . $ai_sentence;
				}
			}
		}
		
		return array(
			'question' => $question,
			'answer'   => $answer,
			'icon'     => '⏰',
		);
	}

	/**
	 * FAQ 6 template version.
	 *
	 * @since    2.35.0
	 */
private static function generate_time_difference_faq_template( $city_name, $timezone ) {
	// v3.2.15: Use base timezone from settings (not hardcoded)
	$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
	$diff_hours = self::calculate_time_difference( $timezone, $base_timezone );
		$example_time = self::format_time_with_offset( 12, 0, $diff_hours );
		
		$question = self::get_faq_text( 'faq6_question', array( 'city_name' => $city_name ) );
		$answer = self::get_faq_text( 'faq6_answer', array(
			'city_name' => $city_name,
			'diff_hours' => $diff_hours,
			'example_time' => $example_time
		) );
		
		return array(
			'question' => $question,
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
		
		// Get hemisphere from templates
		$templates = get_option( 'wta_templates', array() );
		$hemisphere = ( $latitude >= 0 ) 
			? ( isset( $templates['northern_hemisphere'] ) ? $templates['northern_hemisphere'] : 'nordlige' )
			: ( isset( $templates['southern_hemisphere'] ) ? $templates['southern_hemisphere'] : 'sydlige' );
		
		// Get question and answer from language pack
		$question = self::get_faq_text( 'faq7_question', array( 'city_name' => $city_name ) );
		$answer = self::get_faq_text( 'faq7_answer', array(
			'season' => $season,
			'city_name' => $city_name,
			'hemisphere' => $hemisphere
		) );
		
		// Optionally add AI sentence for variation (only in normal mode)
		if ( ! $test_mode ) {
			$api_key = get_option( 'wta_openai_api_key', '' );
			if ( ! empty( $api_key ) ) {
				$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
				$site_lang = get_option( 'wta_site_language', 'da' );
				$lang_desc = get_option( 'wta_base_language_description', 'Skriv på flydende dansk til danske brugere' );
				
				$system = "Skriv 1 sætning på {$site_lang} om vejr eller dagslængde. Ingen placeholders.";
				$user = "{$lang_desc}. Skriv 1 sætning om hvordan {$season} er i {$city_name} (lat: {$latitude}). Nævn vejr eller dagslængde. Max 25 ord. INGEN placeholders.";
				
				$ai_sentence = self::call_openai_simple( $api_key, $model, $system, $user, 40 );
				if ( false !== $ai_sentence && ! empty( $ai_sentence ) ) {
					$answer .= ' ' . $ai_sentence;
				}
			}
		}
		
		return array(
			'question' => $question,
			'answer'   => $answer,
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
		
		// Get hemisphere from templates
		$templates = get_option( 'wta_templates', array() );
		$hemisphere = ( $latitude >= 0 ) 
			? ( isset( $templates['northern_hemisphere'] ) ? $templates['northern_hemisphere'] : 'nordlige' )
			: ( isset( $templates['southern_hemisphere'] ) ? $templates['southern_hemisphere'] : 'sydlige' );
		
		$question = self::get_faq_text( 'faq7_question', array( 'city_name' => $city_name ) );
		$answer = self::get_faq_text( 'faq7_answer', array(
			'season' => $season,
			'city_name' => $city_name,
			'hemisphere' => $hemisphere
		) );
	
	return array(
		'question' => $question,
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
		
		// Get question and answer from language pack
		$question = self::get_faq_text( 'faq8_question', array( 'city_name' => $city_name ) );
		
		if ( $uses_dst ) {
			$answer = self::get_faq_text( 'faq8_answer_yes', array( 'city_name' => $city_name ) );
		} else {
			$answer = self::get_faq_text( 'faq8_answer_no', array( 'city_name' => $city_name ) );
		}
		
		// Optionally add AI sentence for variation (only in normal mode and if DST is used)
		if ( ! $test_mode && $uses_dst ) {
			$api_key = get_option( 'wta_openai_api_key', '' );
			if ( ! empty( $api_key ) ) {
				$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
				$site_lang = get_option( 'wta_site_language', 'da' );
				$lang_desc = get_option( 'wta_base_language_description', 'Skriv på flydende dansk til danske brugere' );
				
				$system = "Skriv 1 sætning på {$site_lang}. Ingen placeholders.";
				$user = "{$lang_desc}. Skriv 1 sætning om hvordan sommertid påvirker tid i {$city_name}. Max 20 ord. INGEN placeholders.";
				
				$ai_sentence = self::call_openai_simple( $api_key, $model, $system, $user, 35 );
				if ( false !== $ai_sentence && ! empty( $ai_sentence ) ) {
					$answer .= ' ' . $ai_sentence;
				}
			}
		}
		
		return array(
			'question' => $question,
			'answer'   => $answer,
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
		
		$question = self::get_faq_text( 'faq8_question', array( 'city_name' => $city_name ) );
		
		if ( $uses_dst ) {
			$answer = self::get_faq_text( 'faq8_answer_yes', array( 'city_name' => $city_name ) );
		} else {
			$answer = self::get_faq_text( 'faq8_answer_no', array( 'city_name' => $city_name ) );
		}
		
		return array(
			'question' => $question,
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

// v3.2.15: Calculate time difference to base timezone (from settings, not hardcoded)
$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
$diff_hours = self::calculate_time_difference( $timezone, $base_timezone );

// v3.2.14: Use language-aware prompts from JSON (loaded via "Load Default Prompts")
	$system = get_option( 'wta_prompt_faq_ai_batch_system', 'Du er ekspert i at skrive FAQ svar på dansk. Svar skal være praktiske og hjælpsomme. INGEN placeholders. Return ONLY pure JSON, no markdown code blocks.' );
	$user = get_option( 'wta_prompt_faq_ai_batch_user', 'Skriv FAQ svar for {city_name}, {country_name}...' );
	
	// Replace placeholders
	$user = str_replace( '{city_name}', $city_name, $user );
	$user = str_replace( '{country_name}', $country_name, $user );
	$user = str_replace( '{diff_hours}', $diff_hours, $user );
	
	$response = self::call_openai_simple( $api_key, $model, $system, $user, 500 );
	
	if ( false === $response || empty( $response ) ) {
		WTA_Logger::warning( 'Failed to generate AI FAQ batch', array( 'city' => $city_name ) );
		return array();
	}
	
	// v3.0.68: Robust JSON parsing with multiple strategies
	$json_data = self::parse_json_robust( $response );
	
	if ( ! is_array( $json_data ) || ! isset( $json_data['faq1'] ) ) {
		WTA_Logger::warning( 'Invalid AI FAQ JSON response', array( 
			'response' => $response,
			'city' => $city_name 
		) );
		return array();
	}
		
	// v3.2.14: Use language-aware questions from FAQ strings
	return array(
		array(
			'question' => self::get_faq_text( 'faq9_question', array( 'city_name' => $city_name ) ),
			'answer'   => $json_data['faq1'],
			'icon'     => '📞',
		),
		array(
			'question' => self::get_faq_text( 'faq10_question', array( 'city_name' => $city_name ) ),
			'answer'   => $json_data['faq2'],
			'icon'     => '🕐',
		),
		array(
			'question' => self::get_faq_text( 'faq11_question', array( 'city_name' => $city_name ) ),
			'answer'   => $json_data['faq3'],
			'icon'     => '🌐',
		),
		array(
			'question' => self::get_faq_text( 'faq12_question', array( 'city_name' => $city_name ) ),
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
	// v3.2.15: Use base timezone from settings (not hardcoded)
	$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
	$diff_hours = self::calculate_time_difference( $timezone, $base_timezone );
	
	$question = self::get_faq_text( 'faq9_question', array( 'city_name' => $city_name ) );
		$answer = self::get_faq_text( 'faq9_answer_template', array(
			'city_name' => $city_name,
			'diff_hours' => $diff_hours
		) );
		
		return array(
			'question' => $question,
			'answer'   => $answer,
			'icon'     => '📞',
		);
	}

private static function generate_jetlag_faq_template( $city_name, $timezone ) {
	// v3.2.15: Use base timezone from settings (not hardcoded)
	$base_timezone = get_option( 'wta_base_timezone', 'Europe/Copenhagen' );
	$diff_hours = self::calculate_time_difference( $timezone, $base_timezone );
	
	$question = self::get_faq_text( 'faq11_question', array( 'city_name' => $city_name ) );
		$answer = self::get_faq_text( 'faq11_answer_template', array(
			'city_name' => $city_name,
			'diff_hours' => $diff_hours
		) );
		
		return array(
			'question' => $question,
			'answer'   => $answer,
			'icon'     => '🌐',
		);
	}

	private static function generate_culture_faq_template( $city_name, $country_name ) {
		$question = self::get_faq_text( 'faq10_question', array( 'city_name' => $city_name ) );
		$answer = self::get_faq_text( 'faq10_answer_template', array( 'city_name' => $city_name ) );
		
		return array(
			'question' => $question,
			'answer'   => $answer,
			'icon'     => '🕐',
		);
	}

	private static function generate_travel_time_faq_template( $city_name, $latitude ) {
		// Get hemisphere from templates
		$templates = get_option( 'wta_templates', array() );
		$hemisphere = ( $latitude >= 0 ) 
			? ( isset( $templates['northern_hemisphere'] ) ? $templates['northern_hemisphere'] : 'nordlige' )
			: ( isset( $templates['southern_hemisphere'] ) ? $templates['southern_hemisphere'] : 'sydlige' );
		
		$question = self::get_faq_text( 'faq12_question', array( 'city_name' => $city_name ) );
		$answer = self::get_faq_text( 'faq12_answer_template', array(
			'city_name' => $city_name,
			'hemisphere' => $hemisphere
		) );
		
		return array(
			'question' => $question,
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
		
		// Get season templates from language pack
		$templates = get_option( 'wta_templates', array() );
		$spring = isset( $templates['season_spring'] ) ? $templates['season_spring'] : 'forår';
		$summer = isset( $templates['season_summer'] ) ? $templates['season_summer'] : 'sommer';
		$autumn = isset( $templates['season_autumn'] ) ? $templates['season_autumn'] : 'efterår';
		$winter = isset( $templates['season_winter'] ) ? $templates['season_winter'] : 'vinter';
		
		// Northern hemisphere
		if ( $is_northern ) {
			if ( $month >= 3 && $month <= 5 ) {
				return $spring;
			} elseif ( $month >= 6 && $month <= 8 ) {
				return $summer;
			} elseif ( $month >= 9 && $month <= 11 ) {
				return $autumn;
			} else {
				return $winter;
			}
		} else {
			// Southern hemisphere (reversed)
			if ( $month >= 3 && $month <= 5 ) {
				return $autumn;
			} elseif ( $month >= 6 && $month <= 8 ) {
				return $winter;
			} elseif ( $month >= 9 && $month <= 11 ) {
				return $spring;
			} else {
				return $summer;
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
		
		// Determine phase name key (use translation system)
		if ( $phase_days < 1.84566 ) {
			$phase_key = 'moon_new_moon';
		} elseif ( $phase_days < 5.53699 ) {
			$phase_key = 'moon_waxing_crescent';
		} elseif ( $phase_days < 9.22831 ) {
			$phase_key = 'moon_first_quarter';
		} elseif ( $phase_days < 12.91963 ) {
			$phase_key = 'moon_waxing_gibbous';
		} elseif ( $phase_days < 16.61096 ) {
			$phase_key = 'moon_full_moon';
		} elseif ( $phase_days < 20.30228 ) {
			$phase_key = 'moon_waning_gibbous';
		} elseif ( $phase_days < 23.99361 ) {
			$phase_key = 'moon_last_quarter';
		} else {
			$phase_key = 'moon_waning_crescent';
		}
		
		// Translate phase name to current language (load from JSON file)
		$lang_code = get_option( 'wta_site_language', 'da' );
		
		// Build path to JSON file relative to this file
		// This file is in: includes/helpers/class-wta-faq-generator.php
		// JSON files are in: includes/languages/*.json
		$json_file = dirname( dirname( __FILE__ ) ) . '/languages/' . $lang_code . '.json';
		$phase_name = $phase_key; // Fallback to key if translation not found
		
		if ( file_exists( $json_file ) ) {
			$json_content = file_get_contents( $json_file );
			$translations = json_decode( $json_content, true );
			
			// Moon phase translations are in the 'templates' section
			if ( is_array( $translations ) && isset( $translations['templates'][ $phase_key ] ) ) {
				$phase_name = $translations['templates'][ $phase_key ];
			}
		}
		
		return array(
			'percentage'  => number_format( $percentage, 1 ),
			'phase_name'  => $phase_name,
		);
	}

	/**
	 * Robust JSON parsing with multiple strategies.
	 * Handles markdown code blocks, whitespace, BOM, and other AI formatting issues.
	 *
	 * @since    3.0.68
	 * @param    string $response Raw AI response.
	 * @return   array|null       Parsed JSON data or null on failure.
	 */
	private static function parse_json_robust( $response ) {
		// Strategy 1: Direct JSON decode (fastest)
		$json_data = json_decode( $response, true );
		if ( is_array( $json_data ) ) {
			return $json_data;
		}
		
		// Strategy 2: Strip markdown code blocks
		// Remove ```json and ``` wrappers
		$cleaned = preg_replace( '/^```(?:json)?\s*\n?/m', '', $response );
		$cleaned = preg_replace( '/\n?```\s*$/m', '', $cleaned );
		$cleaned = trim( $cleaned );
		
		$json_data = json_decode( $cleaned, true );
		if ( is_array( $json_data ) ) {
			WTA_Logger::debug( 'FAQ JSON parsed after stripping markdown blocks' );
			return $json_data;
		}
		
		// Strategy 3: Remove BOM and control characters
		$cleaned = preg_replace( '/[\x00-\x1F\x7F]/u', '', $cleaned );
		$cleaned = trim( $cleaned, "\xEF\xBB\xBF" ); // UTF-8 BOM
		
		$json_data = json_decode( $cleaned, true );
		if ( is_array( $json_data ) ) {
			WTA_Logger::debug( 'FAQ JSON parsed after removing control characters' );
			return $json_data;
		}
		
		// Strategy 4: Extract JSON object via regex
		if ( preg_match( '/\{[^{}]*(?:"faq[1-4]"[^{}]*){4}[^{}]*\}/s', $cleaned, $matches ) ) {
			$json_data = json_decode( $matches[0], true );
			if ( is_array( $json_data ) ) {
				WTA_Logger::debug( 'FAQ JSON extracted via regex pattern matching' );
				return $json_data;
			}
		}
		
		// All strategies failed
		return null;
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
	
	/**
	 * Get FAQ text from language pack with variable replacement.
	 *
	 * Retrieves translated FAQ strings from wta_faq_strings option (loaded from JSON)
	 * and replaces placeholders with actual values.
	 *
	 * @since    3.2.6
	 * @param    string  $key   FAQ text key (e.g. 'faq1_question').
	 * @param    array   $vars  Associative array of variables to replace (e.g. ['city_name' => 'Stockholm']).
	 * @return   string         Translated text with variables replaced, or Danish fallback.
	 */
	private static function get_faq_text( $key, $vars = array() ) {
		// Get FAQ strings from language pack
		$faq_strings = get_option( 'wta_faq_strings', array() );
		
		// Fallback to hardcoded Danish if option is empty or key not found
		$fallback_strings = array(
			'faq1_question' => 'Hvad er klokken i {city_name} lige nu?',
			'faq1_answer' => 'Klokken i {city_name} er {current_time}. Byen ligger i tidszonen {timezone}{utc_offset}.',
			'faq2_question' => 'Hvad er tidszonen i {city_name}?',
			'faq2_answer' => '{city_name} bruger tidszonen <strong>{timezone}</strong> med UTC offset på <strong>{utc_offset}</strong> ({abbr}).',
			'faq3_question' => 'Hvornår går solen op og ned i {city_name}?',
			'faq3_answer' => 'I dag går solen op kl. <strong>{sunrise}</strong> og ned kl. <strong>{sunset}</strong> i {city_name}. Dagens længde er <strong>{day_length}</strong> timer.',
			'faq3_answer_fallback' => 'Solopgang og solnedgangstider varierer dagligt i {city_name} baseret på årstiden.',
			'faq4_question' => 'Hvad er månefasen i {city_name}?',
			'faq4_answer' => 'Månefasen i {city_name} er aktuelt <strong>{moon_percentage}%</strong> ({moon_phase}).',
			'faq5_question' => 'Hvor ligger {city_name} geografisk?',
			'faq5_answer' => '{city_name} ligger på koordinaterne <strong>{latitude}° {lat_dir}, {longitude}° {lon_dir}</strong> i {country_name}. Byen befinder sig på den {hemisphere} halvkugle.',
			'faq5_answer_no_country' => '{city_name} ligger på koordinaterne <strong>{latitude}° {lat_dir}, {longitude}° {lon_dir}</strong>. Byen befinder sig på den {hemisphere} halvkugle.',
			'intro_template' => 'Her finder du svar på de mest almindelige spørgsmål om tid i {city_name}.'
		);
		
		// Get text from option or fallback
		$text = isset( $faq_strings[ $key ] ) ? $faq_strings[ $key ] : ( isset( $fallback_strings[ $key ] ) ? $fallback_strings[ $key ] : '' );
		
		// Replace variables in text
		foreach ( $vars as $var_name => $var_value ) {
			$text = str_replace( '{' . $var_name . '}', $var_value, $text );
		}
		
		return $text;
	}
}

