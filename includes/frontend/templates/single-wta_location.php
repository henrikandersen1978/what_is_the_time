<?php
/**
 * Single location template - Theme-compatible version.
 *
 * This template respects your theme's layout and styling.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend/templates
 */

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php
		while ( have_posts() ) :
			the_post();

			$type = get_post_meta( get_the_ID(), 'wta_type', true );
			$timezone = get_post_meta( get_the_ID(), 'wta_timezone', true );
			$name_local = get_the_title();
			?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				
				<header class="entry-header">
					<?php
					// Title with optional prefix for cities
					if ( 'city' === $type ) {
						the_title( '<h1 class="entry-title">', '</h1>' );
					} else {
						the_title( '<h1 class="entry-title">', '</h1>' );
					}
					?>
				</header>

				<?php 
				// Show clock for cities with timezone
				if ( 'city' === $type && ! empty( $timezone ) && 'multiple' !== $timezone ) : 
				?>
				<div class="wta-clock-container">
					<div class="wta-clock" data-timezone="<?php echo esc_attr( $timezone ); ?>">
						<div class="wta-clock-time">--:--:--</div>
						<div class="wta-clock-date">-</div>
						<div class="wta-clock-timezone"><?php echo esc_html( $timezone ); ?></div>
					</div>
				</div>
				<?php endif; ?>

				<div class="entry-content">
					<?php the_content(); ?>
				</div>

				<?php
				// Show child locations (countries or cities)
				$children = get_posts( array(
					'post_type'      => WTA_POST_TYPE,
					'post_parent'    => get_the_ID(),
					'posts_per_page' => 100,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'post_status'    => 'publish',
				) );

				if ( ! empty( $children ) ) :
					// Determine child type based on parent type
					if ( 'continent' === $type ) {
						$child_heading = __( 'Lande', 'world-time-ai' );
					} elseif ( 'country' === $type ) {
						$child_heading = __( 'Byer', 'world-time-ai' );
					} else {
						$child_heading = __( 'Locations', 'world-time-ai' );
					}
					?>
					<div class="wta-children-list">
						<h2><?php echo esc_html( $child_heading ); ?></h2>
						<ul class="wta-locations-grid">
							<?php foreach ( $children as $child ) : ?>
							<li>
								<a href="<?php echo esc_url( get_permalink( $child->ID ) ); ?>">
									<?php echo esc_html( get_the_title( $child->ID ) ); ?>
								</a>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

			</article>

		<?php endwhile; ?>

	</main>
</div>

<?php
// Show sidebar if theme supports it
get_sidebar();
get_footer();

