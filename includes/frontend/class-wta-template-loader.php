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
			// Try new template name first (wta_location)
			$new_template = WTA_PLUGIN_DIR . 'includes/frontend/templates/single-wta_location.php';
			if ( file_exists( $new_template ) ) {
				return $new_template;
			}
			
			// Fallback to old template name for backwards compatibility
			$old_template = WTA_PLUGIN_DIR . 'includes/frontend/templates/single-world_time_location.php';
			if ( file_exists( $old_template ) ) {
				return $old_template;
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


