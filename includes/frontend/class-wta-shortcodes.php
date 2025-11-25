<?php
/**
 * Shortcode registration and handlers.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend
 */

/**
 * Shortcodes class.
 *
 * @since 1.0.0
 */
class WTA_Shortcodes {

	/**
	 * Register shortcodes.
	 *
	 * @since 1.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'wta_clock', array( $this, 'clock_shortcode' ) );
		add_shortcode( 'wta_time_difference', array( $this, 'time_difference_shortcode' ) );
	}

	/**
	 * Clock shortcode handler.
	 *
	 * Usage: [wta_clock city_id="123"]
	 * or: [wta_clock timezone="Europe/Copenhagen"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Clock HTML.
	 */
	public function clock_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'city_id'  => '',
				'timezone' => '',
				'format'   => 'H:i:s',
			),
			$atts,
			'wta_clock'
		);

		$timezone = $atts['timezone'];

		// Get timezone from city_id if provided
		if ( ! empty( $atts['city_id'] ) ) {
			$city_id = intval( $atts['city_id'] );
			$timezone = get_post_meta( $city_id, 'wta_timezone', true );
		}

		if ( empty( $timezone ) || ! WTA_Timezone_Helper::is_valid_timezone( $timezone ) ) {
			return '<p>' . esc_html__( 'Invalid timezone.', WTA_TEXT_DOMAIN ) . '</p>';
		}

		// Get current time in timezone
		$current_time = WTA_Utils::get_time_in_timezone( $timezone, $atts['format'] );
		$iso_time = WTA_Utils::get_iso_time_in_timezone( $timezone );

		// Enqueue clock script
		wp_enqueue_script( 'wta-clock' );

		return sprintf(
			'<div class="wta-clock" data-timezone="%s" data-base-time="%s">%s</div>',
			esc_attr( $timezone ),
			esc_attr( $iso_time ),
			esc_html( $current_time )
		);
	}

	/**
	 * Time difference shortcode handler.
	 *
	 * Usage: [wta_time_difference city_id="123"]
	 * or: [wta_time_difference timezone="Europe/Copenhagen"]
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Time difference HTML.
	 */
	public function time_difference_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'city_id'  => '',
				'timezone' => '',
				'format'   => 'long',
			),
			$atts,
			'wta_time_difference'
		);

		$timezone = $atts['timezone'];

		// Get timezone from city_id if provided
		if ( ! empty( $atts['city_id'] ) ) {
			$city_id = intval( $atts['city_id'] );
			$timezone = get_post_meta( $city_id, 'wta_timezone', true );
		}

		if ( empty( $timezone ) || ! WTA_Timezone_Helper::is_valid_timezone( $timezone ) ) {
			return '<p>' . esc_html__( 'Invalid timezone.', WTA_TEXT_DOMAIN ) . '</p>';
		}

		// Get base timezone
		$base_timezone = get_option( 'wta_base_timezone', 'UTC' );
		$base_country = get_option( 'wta_base_country_name', '' );

		// Calculate difference
		$difference = WTA_Timezone_Helper::get_formatted_difference(
			$timezone,
			$base_timezone,
			$atts['format']
		);

		if ( ! empty( $base_country ) ) {
			return sprintf(
				'<span class="wta-time-difference">%s %s %s</span>',
				esc_html( $difference ),
				esc_html__( 'from', WTA_TEXT_DOMAIN ),
				esc_html( $base_country )
			);
		}

		return sprintf(
			'<span class="wta-time-difference">%s</span>',
			esc_html( $difference )
		);
	}
}





