<?php
/**
 * FAQ Renderer - Generates HTML and Schema for FAQ sections.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 * @since      2.35.0
 */

class WTA_FAQ_Renderer {

	/**
	 * Render FAQ section HTML.
	 *
	 * @since    2.35.0
	 * @param    array  $faq_data FAQ data with 'intro' and 'faqs' keys.
	 * @param    string $city_name City name for heading.
	 * @return   string            FAQ HTML.
	 */
	public static function render_faq_section( $faq_data, $city_name = '' ) {
		if ( empty( $faq_data ) || ! isset( $faq_data['faqs'] ) || empty( $faq_data['faqs'] ) ) {
			return '';
		}
		
		$intro = isset( $faq_data['intro'] ) ? $faq_data['intro'] : '';
		$faqs = $faq_data['faqs'];
		
		// Use city name from first FAQ question if not provided
		if ( empty( $city_name ) && ! empty( $faqs[0]['question'] ) ) {
			// Extract city name from question like "Hvad er klokken i København?"
			preg_match( '/i ([^?]+)\?/', $faqs[0]['question'], $matches );
			$city_name = isset( $matches[1] ) ? $matches[1] : '';
		}
		
		ob_start();
		?>
		
		<!-- FAQ Section (v2.35.0) -->
		<section class="wta-faq-section" id="faq">
			<h2 class="wta-faq-heading">Ofte stillede spørgsmål om tid<?php echo ! empty( $city_name ) ? ' i ' . esc_html( $city_name ) : ''; ?></h2>
			
			<?php if ( ! empty( $intro ) ) : ?>
			<div class="wta-faq-intro">
				<p><?php echo esc_html( $intro ); ?></p>
			</div>
			<?php endif; ?>
			
			<div class="wta-faq-container">
				<?php foreach ( $faqs as $index => $faq ) : ?>
					<?php if ( ! empty( $faq['question'] ) && ! empty( $faq['answer'] ) ) : ?>
					<div class="wta-faq-item" data-faq-index="<?php echo esc_attr( $index ); ?>">
						<button class="wta-faq-question" aria-expanded="false" aria-controls="faq-answer-<?php echo esc_attr( $index ); ?>">
							<?php if ( ! empty( $faq['icon'] ) ) : ?>
								<span class="wta-faq-icon-emoji"><?php echo $faq['icon']; ?></span>
							<?php endif; ?>
							<span class="wta-faq-question-text">
								<?php echo esc_html( $faq['question'] ); ?>
							</span>
							<span class="wta-faq-toggle-icon" aria-hidden="true">▼</span>
						</button>
						<div class="wta-faq-answer" id="faq-answer-<?php echo esc_attr( $index ); ?>" hidden>
							<div class="wta-faq-answer-content">
								<?php 
								// Clean answer text: strip ALL HTML including <br>
								// Then allow only specific tags back (strong, em, a)
								$answer = $faq['answer'];
								
								// First strip all <br> variants
								$answer = str_replace( array( '<br>', '<br/>', '<br />', '<br/ >' ), ' ', $answer );
								
								// Allow only safe HTML tags
								$allowed_html = array(
									'strong' => array(),
									'b'      => array(),
									'em'     => array(),
									'i'      => array(),
									'a'      => array( 'href' => array(), 'title' => array() ),
								);
								
								echo wp_kses( $answer, $allowed_html ); 
								?>
							</div>
						</div>
					</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
		
		<?php
		$output = ob_get_clean();
		
		// FAQ schema now injected via Yoast filter using array type (v2.35.21)
		// No longer adding as separate JSON-LD script tag
		// BEST PRACTICE: @type = ['WebPage', 'FAQPage'] preserves all Yoast properties
		
		return $output;
	}

	/**
	 * Generate FAQPage schema markup for Yoast.
	 *
	 * @since    2.35.0
	 * @param    array  $faq_data  FAQ data with 'faqs' array.
	 * @param    string $permalink Page URL.
	 * @return   array             Schema.org FAQPage structure.
	 */
	public static function generate_faq_schema( $faq_data, $permalink = '' ) {
		if ( empty( $faq_data ) || ! isset( $faq_data['faqs'] ) || empty( $faq_data['faqs'] ) ) {
			return array();
		}
		
		$faqs = $faq_data['faqs'];
		$main_entity = array();
		
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}
			
			// Strip HTML tags from answer for schema (plain text)
			$answer_text = wp_strip_all_tags( $faq['answer'] );
			
			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer_text,
				),
			);
		}
		
		if ( empty( $main_entity ) ) {
			return array();
		}
		
		$schema = array(
			'@type'      => 'FAQPage',
			'@id'        => ! empty( $permalink ) ? $permalink . '#faq' : '#faq',
			'mainEntity' => $main_entity,
		);
		
		return $schema;
	}

	/**
	 * Inject FAQ schema into Yoast SEO graph (BEST PRACTICE v2.35.21).
	 * 
	 * Converts WebPage to array type ['WebPage', 'FAQPage'] and adds mainEntity.
	 * Preserves ALL existing WebPage properties (breadcrumb, organization, etc.).
	 * Only runs for wta_location post type with FAQ data.
	 *
	 * @since    2.35.0
	 * @param    array  $data    Yoast schema graph data.
	 * @param    object $context Meta tags context.
	 * @return   array           Modified schema graph.
	 */
	public static function inject_faq_schema( $data, $context ) {
		// Only for single location pages
		if ( ! is_singular( WTA_POST_TYPE ) ) {
			return $data;
		}
		
		$post_id = get_the_ID();
		$type = get_post_meta( $post_id, 'wta_type', true );
		
		// Only for city pages
		if ( 'city' !== $type ) {
			return $data;
		}
		
		// Get FAQ data
		$faq_data = get_post_meta( $post_id, 'wta_faq_data', true );
		if ( empty( $faq_data ) || ! isset( $faq_data['faqs'] ) || empty( $faq_data['faqs'] ) ) {
			return $data;
		}
		
		// Build mainEntity array
		$faqs = $faq_data['faqs'];
		$main_entity = array();
		
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}
			
			// Clean answer for schema
			$answer_text = wp_strip_all_tags( $faq['answer'] );
			$answer_text = str_replace( array( '<br>', '<br/>', '<br />', '<br/ >' ), ' ', $answer_text );
			$answer_text = preg_replace( '/\s+/', ' ', $answer_text );
			$answer_text = trim( $answer_text );
			
			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer_text,
				),
			);
		}
		
		if ( empty( $main_entity ) ) {
			return $data;
		}
		
		// Initialize @graph if needed
		if ( ! isset( $data['@graph'] ) || ! is_array( $data['@graph'] ) ) {
			$data['@graph'] = array();
		}
		
		// Find WebPage node
		$webpage_index = null;
		foreach ( $data['@graph'] as $index => $node ) {
			if ( ! isset( $node['@type'] ) ) {
				continue;
			}
			
			// Check both string and array @type
			$node_types = is_array( $node['@type'] ) ? $node['@type'] : array( $node['@type'] );
			
			if ( in_array( 'WebPage', $node_types, true ) ) {
				$webpage_index = $index;
				break;
			}
		}
		
		// If WebPage found: Add FAQPage to @type array and add mainEntity
		if ( null !== $webpage_index ) {
			$existing_types = is_array( $data['@graph'][$webpage_index]['@type'] ) 
				? $data['@graph'][$webpage_index]['@type'] 
				: array( $data['@graph'][$webpage_index]['@type'] );
			
			// Add FAQPage if not already present (BEST PRACTICE: array type)
			if ( ! in_array( 'FAQPage', $existing_types, true ) ) {
				$existing_types[] = 'FAQPage';
			}
			
			// Update @type to array - preserves WebPage, adds FAQPage
			$data['@graph'][$webpage_index]['@type'] = $existing_types;
			
			// Add FAQ mainEntity (preserves all other Yoast properties!)
			$data['@graph'][$webpage_index]['mainEntity'] = $main_entity;
			
		} else {
			// Fallback: No WebPage found, add standalone FAQPage
			// This should rarely happen, but ensures FAQ schema is always present
			$data['@graph'][] = array(
				'@type'      => 'FAQPage',
				'@id'        => get_permalink( $post_id ) . '#faq',
				'mainEntity' => $main_entity,
			);
		}
		
		return $data;
	}
	
	/**
	 * Generate standalone FAQ schema as JSON-LD script tag.
	 * 
	 * FALLBACK ONLY: Not currently used (v2.35.21).
	 * FAQ schema now injected via Yoast filter using array type.
	 * Kept for potential future use if Yoast is not active.
	 * 
	 * @since    2.35.20
	 * @deprecated 2.35.21 Use Yoast filter integration with array type instead
	 * @param    array  $faq_data  FAQ data with 'faqs' array.
	 * @param    string $city_name City name for schema title.
	 * @return   string            JSON-LD script tag with FAQPage schema.
	 */
	private static function generate_faq_schema_tag( $faq_data, $city_name = '' ) {
		if ( empty( $faq_data ) || ! isset( $faq_data['faqs'] ) || empty( $faq_data['faqs'] ) ) {
			return '';
		}
		
		$faqs = $faq_data['faqs'];
		$main_entity = array();
		
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}
			
			// Clean answer for schema: strip ALL HTML and normalize whitespace
			$answer_text = wp_strip_all_tags( $faq['answer'] );
			// Remove all <br> variants that might have been in original
			$answer_text = str_replace( array( '<br>', '<br/>', '<br />', '<br/ >' ), ' ', $answer_text );
			// Multiple spaces → single space
			$answer_text = preg_replace( '/\s+/', ' ', $answer_text );
			$answer_text = trim( $answer_text );
			
			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => $faq['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer_text,
				),
			);
		}
		
		if ( empty( $main_entity ) ) {
			return '';
		}
		
		$schema_name = ! empty( $city_name ) 
			? sprintf( 'Ofte stillede spørgsmål om tid i %s', $city_name )
			: 'Ofte stillede spørgsmål om tid';
		
		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'name'       => $schema_name,
			'mainEntity' => $main_entity,
		);
		
		$output = "\n" . '<script type="application/ld+json">' . "\n";
		$output .= wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		$output .= "\n" . '</script>' . "\n";
		
		return $output;
	}
}

/**
 * Global helper function for rendering FAQ.
 *
 * @since    2.35.0
 * @param    array  $faq_data FAQ data.
 * @param    string $city_name Optional city name.
 * @return   string            FAQ HTML.
 */
function wta_render_faq_section( $faq_data, $city_name = '' ) {
	return WTA_FAQ_Renderer::render_faq_section( $faq_data, $city_name );
}

