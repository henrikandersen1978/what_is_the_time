<?php
/**
 * Single location template.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend/templates
 */

get_header();

while ( have_posts() ) :
	the_post();

	$post_id = get_the_ID();
	$type = get_post_meta( $post_id, 'wta_type', true );
	$timezone = get_post_meta( $post_id, 'wta_timezone', true );
	$local_name = get_post_meta( $post_id, 'wta_name_local', true );
	$display_name = $local_name ? $local_name : get_the_title();

	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<div class="entry-content">
			
			<?php if ( $type === 'city' && $timezone ) : ?>
				<!-- City Page -->
				<header class="entry-header">
					<h1 class="entry-title">
						<?php
						/* translators: %s: city name */
						printf( esc_html__( 'What time is it in %s?', WTA_TEXT_DOMAIN ), esc_html( $display_name ) );
						?>
					</h1>
				</header>

				<div class="wta-time-display">
					<?php
					$current_time = WTA_Utils::get_time_in_timezone( $timezone, 'H:i:s' );
					$iso_time = WTA_Utils::get_iso_time_in_timezone( $timezone );
					$tz_abbr = WTA_Timezone_Helper::get_timezone_abbreviation( $timezone );
					?>
					
					<div class="wta-clock-container">
						<div class="wta-clock" data-timezone="<?php echo esc_attr( $timezone ); ?>" data-base-time="<?php echo esc_attr( $iso_time ); ?>">
							<span class="wta-time"><?php echo esc_html( $current_time ); ?></span>
						</div>
						<div class="wta-timezone-info">
							<span class="wta-timezone-abbr"><?php echo esc_html( $tz_abbr ); ?></span>
							<span class="wta-timezone-name"><?php echo esc_html( $timezone ); ?></span>
						</div>
					</div>

					<?php
					// Show time difference to base country
					$base_timezone = get_option( 'wta_base_timezone', 'UTC' );
					$base_country = get_option( 'wta_base_country_name', '' );
					
					if ( $base_timezone !== $timezone && ! empty( $base_country ) ) :
						$difference = WTA_Timezone_Helper::get_formatted_difference( $timezone, $base_timezone, 'long' );
						?>
						<div class="wta-time-difference">
							<p>
								<?php
								/* translators: 1: time difference, 2: base country name */
								printf( esc_html__( 'Time difference: %1$s from %2$s', WTA_TEXT_DOMAIN ), esc_html( $difference ), esc_html( $base_country ) );
								?>
							</p>
						</div>
					<?php endif; ?>
				</div>

				<div class="wta-content">
					<?php the_content(); ?>
				</div>

			<?php elseif ( $type === 'country' ) : ?>
				<!-- Country Page -->
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
				</header>

				<div class="wta-content">
					<?php the_content(); ?>
				</div>

				<?php
				// List cities in this country
				$cities = get_posts( array(
					'post_type'      => WTA_POST_TYPE,
					'post_parent'    => $post_id,
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				) );

				if ( $cities ) :
					?>
					<div class="wta-children-list">
						<h2><?php esc_html_e( 'Cities', WTA_TEXT_DOMAIN ); ?></h2>
						<ul class="wta-cities-list">
							<?php foreach ( $cities as $city ) : ?>
								<li>
									<a href="<?php echo esc_url( get_permalink( $city->ID ) ); ?>">
										<?php echo esc_html( $city->post_title ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

			<?php elseif ( $type === 'continent' ) : ?>
				<!-- Continent Page -->
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
				</header>

				<div class="wta-content">
					<?php the_content(); ?>
				</div>

				<?php
				// List countries in this continent
				$countries = get_posts( array(
					'post_type'      => WTA_POST_TYPE,
					'post_parent'    => $post_id,
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				) );

				if ( $countries ) :
					?>
					<div class="wta-children-list">
						<h2><?php esc_html_e( 'Countries', WTA_TEXT_DOMAIN ); ?></h2>
						<ul class="wta-countries-list">
							<?php foreach ( $countries as $country ) : ?>
								<li>
									<a href="<?php echo esc_url( get_permalink( $country->ID ) ); ?>">
										<?php echo esc_html( $country->post_title ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<!-- Default fallback -->
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
				</header>

				<div class="wta-content">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

		</div>
	</article>

	<?php
endwhile;

get_footer();




