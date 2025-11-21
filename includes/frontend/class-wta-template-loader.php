<?php
/**
 * Custom template loader for location pages.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

/**
 * Template loader class.
 *
 * @since 1.0.0
 */
class WTA_Template_Loader {

	/**
	 * Load custom template for location pages.
	 *
	 * @since 1.0.0
	 * @param string $template Template path.
	 * @return string Modified template path.
	 */
	public function load_template( $template ) {
		if ( ! is_singular( WTA_POST_TYPE ) ) {
			return $template;
		}

		// Look for template in theme first
		$theme_template = locate_template( array( 'single-world_time_location.php' ) );
		
		if ( $theme_template ) {
			return $theme_template;
		}

		// Use plugin template
		$plugin_template = WTA_PLUGIN_DIR . 'includes/frontend/templates/single-world_time_location.php';
		
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		if ( ! is_singular( WTA_POST_TYPE ) ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style(
			'wta-frontend',
			WTA_PLUGIN_URL . 'includes/frontend/assets/css/frontend.css',
			array(),
			WTA_VERSION
		);

		// Enqueue JS
		wp_enqueue_script(
			'wta-clock',
			WTA_PLUGIN_URL . 'includes/frontend/assets/js/clock.js',
			array(),
			WTA_VERSION,
			true
		);
	}
}




