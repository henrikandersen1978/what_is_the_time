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

	$type = get_post_meta( get_the_ID(), 'wta_type', true );
	$timezone = get_post_meta( get_the_ID(), 'wta_timezone', true );
	$name_local = get_the_title();
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'wta-location-single' ); ?>>
		<header class="wta-location-header">
			<?php
			if ( 'city' === $type ) {
				printf( '<h1 class="wta-location-title">%s</h1>', esc_html( sprintf( __( 'Hvad er klokken i %s?', 'world-time-ai' ), $name_local ) ) );
			} else {
				printf( '<h1 class="wta-location-title">%s</h1>', esc_html( $name_local ) );
			}
			?>
		</header>

		<?php if ( 'city' === $type && ! empty( $timezone ) && 'multiple' !== $timezone ) : ?>
		<div class="wta-clock-container">
			<div class="wta-clock" data-timezone="<?php echo esc_attr( $timezone ); ?>">
				<div class="wta-clock-time">--:--:--</div>
				<div class="wta-clock-date">-</div>
				<div class="wta-clock-timezone"><?php echo esc_html( $timezone ); ?></div>
			</div>
		</div>
		<?php endif; ?>

		<div class="wta-location-content">
			<?php the_content(); ?>
		</div>

		<?php
		// Show child locations
		$children = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => get_the_ID(),
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		if ( ! empty( $children ) ) :
			$child_type = ( 'continent' === $type ) ? __( 'Countries', 'world-time-ai' ) : __( 'Cities', 'world-time-ai' );
			?>
			<div class="wta-children-list">
				<h2><?php echo esc_html( $child_type ); ?></h2>
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

	<?php
endwhile;

get_footer();

