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
								// Remove <br> tags and use CSS spacing instead
								$answer_clean = str_replace( array( '<br>', '<br/>', '<br />' ), ' ', $faq['answer'] );
								echo wp_kses_post( $answer_clean ); 
								?>
							</div>
						</div>
					</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
		
		<?php
		return ob_get_clean();
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
	 * Inject FAQ schema into Yoast SEO graph.
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
		if ( empty( $faq_data ) ) {
			return $data;
		}
		
		// Generate main entity (Questions)
		$faqs = isset( $faq_data['faqs'] ) ? $faq_data['faqs'] : array();
		$main_entity = array();
		
		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}
			
			// Strip HTML tags and <br> from answer for schema
			$answer_text = wp_strip_all_tags( $faq['answer'] );
			$answer_text = str_replace( array( '<br>', '<br/>', '<br />' ), ' ', $answer_text );
			
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
		if ( ! isset( $data['@graph'] ) ) {
			$data['@graph'] = array();
		}
		
		// Find existing WebPage node and convert to FAQPage (Yoast pattern)
		$webpage_index = null;
		foreach ( $data['@graph'] as $index => $node ) {
			if ( isset( $node['@type'] ) && 'WebPage' === $node['@type'] ) {
				$webpage_index = $index;
				break;
			}
		}
		
		if ( null !== $webpage_index ) {
			// Convert WebPage to FAQPage and add mainEntity
			$data['@graph'][$webpage_index]['@type'] = 'FAQPage';
			$data['@graph'][$webpage_index]['mainEntity'] = $main_entity;
		} else {
			// Fallback: Add as separate FAQPage node if no WebPage found
			$data['@graph'][] = array(
				'@type'      => 'FAQPage',
				'@id'        => get_permalink( $post_id ) . '#faq',
				'mainEntity' => $main_entity,
			);
		}
		
		return $data;
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

