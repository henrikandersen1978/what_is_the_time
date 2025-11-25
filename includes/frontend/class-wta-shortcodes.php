<?php
/**
 * Shortcodes for frontend.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

class WTA_Shortcodes {

	/**
	 * Register shortcodes.
	 *
	 * @since    2.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'world_time_clock', array( $this, 'clock_shortcode' ) );
	}

	/**
	 * Clock shortcode.
	 *
	 * Usage: [world_time_clock timezone="Europe/Copenhagen" format="long"]
	 *
	 * @since    2.0.0
	 * @param    array $atts Shortcode attributes.
	 * @return   string      HTML output.
	 */
	public function clock_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'timezone' => 'Europe/Copenhagen',
			'format'   => 'long', // long, short, time-only
			'title'    => '',
		), $atts );

		$timezone = sanitize_text_field( $atts['timezone'] );
		$format = sanitize_text_field( $atts['format'] );
		$title = sanitize_text_field( $atts['title'] );

		// Validate timezone
		if ( ! WTA_Timezone_Helper::is_valid_timezone( $timezone ) ) {
			return '<p class="wta-error">Invalid timezone</p>';
		}

		ob_start();
		?>
		<div class="wta-clock-widget" data-timezone="<?php echo esc_attr( $timezone ); ?>" data-format="<?php echo esc_attr( $format ); ?>">
			<?php if ( ! empty( $title ) ) : ?>
				<h3 class="wta-clock-title"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>
			<div class="wta-clock-display">
				<div class="wta-time">--:--:--</div>
				<div class="wta-date">-</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}


