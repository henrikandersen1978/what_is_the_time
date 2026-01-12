<?php
/**
 * Single AI processor for Action Scheduler (Pilanto-AI Model).
 *
 * Processes ONE AI content generation per action, allowing Action Scheduler
 * to parallelize. Includes force_regenerate_single() for manual regeneration.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/processors
 * @since      3.0.43
 */

// We need to load the old processor temporarily to access its methods
require_once WTA_PLUGIN_DIR . 'includes/scheduler/class-wta-ai-processor.php';

class WTA_Single_AI_Processor extends WTA_AI_Processor {

	/**
	 * Cached language templates.
	 *
	 * @since    3.2.0
	 * @var      array|null
	 */
	private static $templates_cache = null;

	/**
	 * Get language template string.
	 *
	 * @since    3.2.0
	 * @param    string $key Template key (e.g., 'continent_h1', 'city_title')
	 * @return   string Template string with %s placeholders
	 */
	private static function get_template( $key ) {
		// Load templates once
		if ( self::$templates_cache === null ) {
			// Try to get from WordPress options (loaded via "Load Default Prompts")
			$templates = get_option( 'wta_templates', array() );
			
			if ( ! empty( $templates ) && is_array( $templates ) ) {
				self::$templates_cache = $templates;
			} else {
				// Fallback to Danish templates if not loaded
				self::$templates_cache = array(
					'continent_h1'    => 'Aktuel tid i lande og byer i %s',
					'continent_title' => 'Hvad er klokken i %s? Tidszoner og aktuel tid',
					'country_h1'      => 'Aktuel tid i byer i %s',
					'country_title'   => 'Hvad er klokken i %s?',
					'city_h1'         => 'Aktuel tid i %s, %s',
					'city_title'      => 'Hvad er klokken i %s, %s?',
					'faq_intro'       => 'Her finder du svar på de mest almindelige spørgsmål om tid i %s.',
				);
			}
		}
		
		return isset( self::$templates_cache[ $key ] ) ? self::$templates_cache[ $key ] : '';
	}

	/**
	 * Generate AI content for a single post.
	 *
	 * Action Scheduler unpacks args, so this receives separate parameters.
	 *
	 * @since    3.0.43
	 * @since    3.0.54  Added execution time logging.
	 * @param    int    $post_id  Post ID.
	 * @param    string $type     Location type.
	 * @param    bool   $force_ai Force AI generation (ignore test mode).
	 */
	public function generate_content( $post_id, $type, $force_ai = false ) {
		$start_time = microtime( true );
		
		// Arguments already unpacked by Action Scheduler - no changes needed
		try {
			// Validate post exists
			$post = get_post( $post_id );
			if ( ! $post ) {
				WTA_Logger::warning( 'Post not found for AI generation', array(
					'post_id' => $post_id,
				) );
				return;
			}

			// Check if already processed (skip check if force_ai is true)
			if ( ! $force_ai ) {
				$ai_status = get_post_meta( $post_id, 'wta_ai_status', true );
				if ( 'done' === $ai_status ) {
					// Special case for cities: Check if FAQ exists
					if ( 'city' === $type ) {
						$faq_data = get_post_meta( $post_id, 'wta_faq_data', true );
						if ( empty( $faq_data ) ) {
							// Try to generate FAQ and append to existing content
							$test_mode = get_option( 'wta_test_mode', 0 );
							$faq_data = WTA_FAQ_Generator::generate_city_faq( $post_id, $test_mode );
							
							if ( false !== $faq_data && ! empty( $faq_data ) ) {
								// v3.4.0: Save static FAQ data
								update_post_meta( $post_id, 'wta_faq_data', $faq_data );
								
								$city_name = get_the_title( $post_id );
								$faq_html = WTA_FAQ_Renderer::render_faq_section( $faq_data, $city_name, $post_id );
								
								if ( ! empty( $faq_html ) ) {
									$existing_content = get_post_field( 'post_content', $post_id );
									wp_update_post( array(
										'ID'           => $post_id,
										'post_content' => $existing_content . "\n\n" . $faq_html,
									) );
									
									WTA_Logger::info( 'FAQ generated and appended to existing content', array( 
										'post_id'   => $post_id,
										'faq_count' => count( $faq_data['faqs'] ),
									) );
								}
							}
						}
					}
					
					WTA_Logger::info( 'AI content already generated', array( 'post_id' => $post_id ) );
					return;
				}
			}

			// Generate content using parent class method
			$result = $this->generate_ai_content( $post_id, $type, $force_ai );

			if ( false === $result ) {
				WTA_Logger::error( 'AI content generation failed', array(
					'post_id' => $post_id,
					'type'    => $type,
				) );
				return;
			}

			// Generate FAQ for cities
			if ( 'city' === $type ) {
				$test_mode = get_option( 'wta_test_mode', 0 );
				$use_test_mode = $test_mode && ! $force_ai;
				$faq_data = WTA_FAQ_Generator::generate_city_faq( $post_id, $use_test_mode );
				
				if ( false !== $faq_data && ! empty( $faq_data ) ) {
					// v3.4.0: Save static FAQ data
					update_post_meta( $post_id, 'wta_faq_data', $faq_data );
					
					$city_name = get_the_title( $post_id );
					$faq_html = WTA_FAQ_Renderer::render_faq_section( $faq_data, $city_name, $post_id );
					
					if ( ! empty( $faq_html ) ) {
						$result['content'] .= "\n\n" . $faq_html;
						WTA_Logger::info( 'FAQ generated and appended to content', array( 
							'post_id'   => $post_id, 
							'force_ai'  => $force_ai,
							'faq_count' => count( $faq_data['faqs'] ),
						) );
					}
				} else {
					WTA_Logger::warning( 'Failed to generate FAQ', array( 'post_id' => $post_id ) );
				}
			}

			// Update post with content
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $result['content'],
				'post_status'  => 'publish',
			) );

			// Update Yoast SEO meta if available
			if ( isset( $result['yoast_title'] ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', $result['yoast_title'] );
				
				// Generate answer-based H1 (separate from title tag)
				if ( 'city' === $type ) {
					$parent_id = wp_get_post_parent_id( $post_id );
					if ( $parent_id ) {
						$country_name = get_post_field( 'post_title', $parent_id );
						$city_name = get_the_title( $post_id );
						$seo_h1 = sprintf( self::get_template( 'city_h1' ), $city_name, $country_name );
						update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
					}
				} elseif ( 'country' === $type ) {
					$country_name = get_the_title( $post_id );
					$seo_h1 = sprintf( self::get_template( 'country_h1' ), $country_name );
					update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
				} elseif ( 'continent' === $type ) {
					$continent_name = get_the_title( $post_id );
					$seo_h1 = sprintf( self::get_template( 'continent_h1' ), $continent_name );
					update_post_meta( $post_id, '_pilanto_page_h1', $seo_h1 );
				}
			}
			
			if ( isset( $result['yoast_desc'] ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $result['yoast_desc'] );
			}

			// Mark as done
			update_post_meta( $post_id, 'wta_ai_status', 'done' );

			$execution_time = round( microtime( true ) - $start_time, 3 );
			$test_mode = get_option( 'wta_test_mode', 0 );
			$used_ai = ! $test_mode || $force_ai;
			
			WTA_Logger::info( '🤖 AI content generated and post published', array(
				'post_id'        => $post_id,
				'type'           => $type,
				'used_ai'        => $used_ai ? 'yes' : 'no (template)',
				'execution_time' => $execution_time . 's',
			) );

		} catch ( Exception $e ) {
			WTA_Logger::error( 'Failed to generate AI content', array(
				'post_id' => $post_id,
				'type'    => $type,
				'error'   => $e->getMessage(),
			) );
		}
	}
}

