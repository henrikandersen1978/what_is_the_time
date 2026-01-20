<?php
/**
 * Comparison Intro processor for Action Scheduler.
 *
 * Generates AI intro text for global_time_comparison shortcode in background.
 * This prevents 1.9-second API calls on first page load.
 *
 * v3.5.25: Batch size 10, conservative rate limiting for Tier 5 OpenAI API.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/scheduler
 * @since      3.5.25
 */

class WTA_Comparison_Intro_Processor {

	/**
	 * Batch size for processing.
	 * Conservative: 10 cities per batch to avoid conflicts with main AI import.
	 */
	const BATCH_SIZE = 10;

	/**
	 * Delay between API calls (in seconds).
	 * 0.1 second delay = 10 req/sec (well within Tier 5 limits).
	 */
	const DELAY_SECONDS = 0.1;

	/**
	 * Process batch.
	 *
	 * Called by Action Scheduler.
	 * Finds cities without comparison intro and generates it.
	 *
	 * @since    3.5.25
	 */
	public function process_batch() {
		$start_time = microtime( true );

		// Get cities without comparison intro
		$cities = $this->get_cities_without_intro( self::BATCH_SIZE );

		if ( empty( $cities ) ) {
			WTA_Logger::info( 'Comparison intro processor: No cities pending', array(
				'batch_size' => self::BATCH_SIZE,
			) );
			return array(
				'completed' => true,
				'processed' => 0,
			);
		}

		WTA_Logger::info( 'Comparison intro processor started', array(
			'cities_found' => count( $cities ),
			'batch_size' => self::BATCH_SIZE,
		) );

		$processed = 0;
		$failed = 0;

		foreach ( $cities as $city ) {
			$success = $this->process_city( $city->ID, $city->post_title );

			if ( $success ) {
				$processed++;
			} else {
				$failed++;
			}

			// Rate limit delay (except after last item)
			if ( ( $processed + $failed ) < count( $cities ) ) {
				usleep( self::DELAY_SECONDS * 1000000 );
			}
		}

		$duration = round( microtime( true ) - $start_time, 2 );

		WTA_Logger::info( 'Comparison intro processor completed', array(
			'processed' => $processed,
			'failed' => $failed,
			'duration_seconds' => $duration,
			'avg_per_item' => round( $duration / max( $processed + $failed, 1 ), 2 ),
		) );

		// Schedule next batch if there are more cities
		if ( count( $cities ) === self::BATCH_SIZE ) {
			// More cities to process - schedule next batch
			$next_run = time() + 60; // 1 minute delay between batches
			
			as_schedule_single_action(
				$next_run,
				'wta_process_comparison_intros',
				array(),
				'world-time-ai'
			);

			WTA_Logger::info( 'Comparison intro: Next batch scheduled', array(
				'next_run' => date( 'Y-m-d H:i:s', $next_run ),
			) );
		} else {
			// All done!
			WTA_Logger::info( 'Comparison intro: All cities completed! 🎉', array(
				'total_processed' => $processed,
			) );
		}

		return array(
			'completed' => count( $cities ) < self::BATCH_SIZE,
			'processed' => $processed,
			'failed' => $failed,
		);
	}

	/**
	 * Get cities without comparison intro.
	 *
	 * @since    3.5.25
	 * @param    int $limit Batch size.
	 * @return   array      Array of post objects (ID, post_title).
	 */
	private function get_cities_without_intro( $limit ) {
		global $wpdb;

		$cities = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, p.post_title
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->prefix}wta_cache c 
				ON c.cache_key = CONCAT('wta_comparison_intro_', p.ID)
				AND c.expires > UNIX_TIMESTAMP()
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND c.cache_key IS NULL
			LIMIT %d",
			WTA_POST_TYPE,
			$limit
		) );

		return $cities;
	}

	/**
	 * Process single city.
	 *
	 * @since    3.5.25
	 * @param    int    $post_id   City post ID.
	 * @param    string $city_name City name.
	 * @return   bool              True on success, false on failure.
	 */
	private function process_city( $post_id, $city_name ) {
		// Check test mode
		$test_mode = get_option( 'wta_test_mode', 0 );
		if ( $test_mode ) {
			// Test mode: use dummy text (no AI costs)
			$intro = 'Dummy tekst om tidsforskelle og verdensur. Test mode aktiveret.';
			
			WTA_Cache::set(
				'wta_comparison_intro_' . $post_id,
				$intro,
				MONTH_IN_SECONDS,
				'comparison_intro'
			);

			WTA_Logger::debug( 'Comparison intro generated (test mode)', array(
				'post_id' => $post_id,
				'city_name' => $city_name,
			) );

			return true;
		}

		// Generate AI intro
		$intro = $this->generate_intro( $city_name );

		if ( empty( $intro ) ) {
			WTA_Logger::warning( 'Comparison intro generation failed', array(
				'post_id' => $post_id,
				'city_name' => $city_name,
			) );
			return false;
		}

		// Cache for 1 month
		WTA_Cache::set(
			'wta_comparison_intro_' . $post_id,
			$intro,
			MONTH_IN_SECONDS,
			'comparison_intro'
		);

		WTA_Logger::debug( 'Comparison intro generated', array(
			'post_id' => $post_id,
			'city_name' => $city_name,
			'intro_length' => strlen( $intro ),
		) );

		return true;
	}

	/**
	 * Generate comparison intro text using OpenAI API.
	 *
	 * @since    3.5.25
	 * @param    string $city_name City name.
	 * @return   string|false      Generated intro text or false on failure.
	 */
	private function generate_intro( $city_name ) {
		$api_key = get_option( 'wta_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return false;
		}

		$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );

		// Get prompts from settings (same as shortcode used)
		$system = get_option( 'wta_prompt_comparison_intro_system', 'Du er SEO-ekspert. Skriv KUN teksten, ingen citationstegn, ingen ekstra forklaringer.' );
		$user_template = get_option( 'wta_prompt_comparison_intro_user', 'Skriv præcis 40-50 ord om hvorfor et verdensur er nyttigt til at sammenligne tidsforskelle mellem %s og andre internationale byer. Inkludér nøgleordene "tidsforskel", "tidsforskelle" og "verdensur". Fokusér på rejseplanlægning og internationale møder. KUN teksten.' );

		// Replace placeholder
		$user = sprintf( $user_template, $city_name );

		// Call OpenAI API
		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type' => 'application/json',
			),
			'body' => json_encode( array(
				'model' => $model,
				'messages' => array(
					array(
						'role' => 'system',
						'content' => $system,
					),
					array(
						'role' => 'user',
						'content' => $user,
					),
				),
				'temperature' => 0.7,
				'max_tokens' => 150,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			WTA_Logger::error( 'OpenAI API error', array(
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			WTA_Logger::error( 'OpenAI API invalid response', array(
				'response_code' => wp_remote_retrieve_response_code( $response ),
				'body' => substr( $body, 0, 200 ),
			) );
			return false;
		}

		$intro = trim( $data['choices'][0]['message']['content'] );

		// Clean up (remove quotes if present)
		$intro = trim( $intro, '"\'""' );

		return $intro;
	}

	/**
	 * Get statistics about comparison intro generation.
	 *
	 * @since    3.5.25
	 * @return   array Statistics.
	 */
	public static function get_stats() {
		global $wpdb;

		$total_cities = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_status = 'publish'",
			WTA_POST_TYPE
		) );

		$cities_with_intro = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT SUBSTRING(c.cache_key, 24))
			FROM {$wpdb->prefix}wta_cache c
			WHERE c.cache_key LIKE %s
			AND c.expires > UNIX_TIMESTAMP()",
			'wta_comparison_intro_%'
		) );

		$cities_pending = $total_cities - $cities_with_intro;
		$percentage = $total_cities > 0 ? round( ( $cities_with_intro / $total_cities ) * 100, 1 ) : 0;

		// Estimate time remaining (10 cities per minute)
		$minutes_remaining = ceil( $cities_pending / 10 );
		$hours_remaining = round( $minutes_remaining / 60, 1 );

		return array(
			'total_cities' => $total_cities,
			'with_intro' => $cities_with_intro,
			'pending' => $cities_pending,
			'percentage' => $percentage,
			'estimated_hours' => $hours_remaining,
		);
	}
}
