<?php
/**
 * Template loader for frontend.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

class WTA_Template_Loader {

	/**
	 * Constructor.
	 *
	 * @since    2.8.4
	 */
	public function __construct() {
		// Filter title for location posts to show custom H1
		add_filter( 'the_title', array( $this, 'filter_location_title' ), 10, 2 );
	}

	/**
	 * Filter post title to show custom H1 for location posts.
	 *
	 * Only affects wta_location posts when displayed as H1 in single post view.
	 *
	 * @since    2.8.4
	 * @param    string $title   Post title.
	 * @param    int    $post_id Post ID.
	 * @return   string          Modified title.
	 */
	public function filter_location_title( $title, $post_id ) {
		// Only for location posts
		if ( get_post_type( $post_id ) !== WTA_POST_TYPE ) {
			return $title;
		}
		
		// Only in singular view (not in loops, menus, etc.)
		if ( ! is_singular( WTA_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}
		
		// Get custom H1 if it exists
		$custom_h1 = get_post_meta( $post_id, '_pilanto_page_h1', true );
		
		if ( ! empty( $custom_h1 ) ) {
			return $custom_h1;
		}
		
		return $title;
	}

	/**
	 * Load custom template for location posts.
	 *
	 * @since    2.0.0
	 * @param    string $template Template path.
	 * @return   string           Modified template path.
	 */
	public function load_template( $template ) {
		if ( is_singular( WTA_POST_TYPE ) ) {
			// Use theme's page template instead of custom template
			// This ensures perfect theme compatibility
			$page_template = get_page_template();
			if ( $page_template && file_exists( $page_template ) ) {
				return $page_template;
			}
			
			// Fallback: use theme's single template
			$single_template = get_single_template();
			if ( $single_template && file_exists( $single_template ) ) {
				return $single_template;
			}
		}

		return $template;
	}

	/**
	 * Enqueue frontend styles.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_styles() {
		if ( is_singular( WTA_POST_TYPE ) || has_shortcode( get_the_content(), 'world_time_clock' ) ) {
			wp_enqueue_style(
				'wta-frontend',
				WTA_PLUGIN_URL . 'includes/frontend/assets/css/frontend.css',
				array(),
				WTA_VERSION
			);
		}
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_scripts() {
		if ( is_singular( WTA_POST_TYPE ) || has_shortcode( get_the_content(), 'world_time_clock' ) ) {
			wp_enqueue_script(
				'wta-clock',
				WTA_PLUGIN_URL . 'includes/frontend/assets/js/clock.js',
				array(),
				WTA_VERSION,
				true
			);
			
			// Add custom H1 handler for location posts
			if ( is_singular( WTA_POST_TYPE ) ) {
				$this->inject_h1_script();
			}
		}
	}
	
	/**
	 * Inject JavaScript to replace H1 with custom title.
	 *
	 * Fallback solution if theme doesn't use the_title() for H1.
	 *
	 * @since    2.8.4
	 */
	private function inject_h1_script() {
		global $post;
		
		$custom_h1 = get_post_meta( $post->ID, '_pilanto_page_h1', true );
		
		if ( empty( $custom_h1 ) ) {
			return;
		}
		
		// Escape for JavaScript
		$custom_h1_escaped = esc_js( $custom_h1 );
		$post_title_escaped = esc_js( get_the_title() );
		
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			// Find H1 that contains the post title
			var h1Elements = document.querySelectorAll('h1');
			var postTitle = <?php echo wp_json_encode( $post_title_escaped ); ?>;
			var customH1 = <?php echo wp_json_encode( $custom_h1_escaped ); ?>;
			
			h1Elements.forEach(function(h1) {
				// Check if H1 contains the post title (case-insensitive)
				if (h1.textContent.trim().toLowerCase().indexOf(postTitle.toLowerCase()) !== -1) {
					// Replace with custom H1
					h1.textContent = customH1;
				}
			});
		});
		</script>
		<?php
	}
}


