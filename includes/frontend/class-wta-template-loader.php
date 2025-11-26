<?php
/**
 * Template loader for frontend.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

class WTA_Template_Loader {

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
		}
	}
}


