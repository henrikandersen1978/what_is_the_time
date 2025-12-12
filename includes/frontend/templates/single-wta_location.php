<?php
/**
 * Single location template - Simple theme-compatible version.
 *
 * This template uses minimal markup and lets the theme control everything.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/frontend/templates
 */

// Let's try to mimic a normal page/post as much as possible
get_header(); ?>

<?php
while ( have_posts() ) :
	the_post();

	$type = get_post_meta( get_the_ID(), 'wta_type', true );
	$timezone = get_post_meta( get_the_ID(), 'wta_timezone', true );
	
	// Get child locations
	$children = get_posts( array(
		'post_type'      => WTA_POST_TYPE,
		'post_parent'    => get_the_ID(),
		'posts_per_page' => 100,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'post_status'    => 'publish',
	) );
	
	// Add child list to content if exists
	$child_list_html = '';
	if ( ! empty( $children ) ) {
		$child_heading = ( 'continent' === $type ) ? __( 'Lande', 'world-time-ai' ) : __( 'Byer', 'world-time-ai' );
		
		$child_list_html = '<div class="wta-children-list"><h2>' . esc_html( $child_heading ) . '</h2><ul class="wta-locations-grid">';
		foreach ( $children as $child ) {
			$child_list_html .= sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( get_permalink( $child->ID ) ),
				esc_html( get_the_title( $child->ID ) )
			);
		}
		$child_list_html .= '</ul></div>';
	}
	
	// Generate FAQ section for cities (v2.35.0)
	$faq_html = '';
	if ( 'city' === $type ) {
		$faq_data = get_post_meta( get_the_ID(), 'wta_faq_data', true );
		if ( ! empty( $faq_data ) && is_array( $faq_data ) ) {
			$faq_html = wta_render_faq_section( $faq_data );
		}
	}
	
	// Add filter to append child list and FAQ to content
	add_filter( 'the_content', function( $content ) use ( $child_list_html, $faq_html ) {
		return $content . $child_list_html . $faq_html;
	}, 20 );
	
	// Use theme's template for single posts/pages
	// This is the magic - let theme handle the layout completely
	if ( function_exists( 'get_template_part' ) ) {
		// Try to use theme's single template
		get_template_part( 'template-parts/content', 'single' );
		if ( ! did_action( 'loop_start' ) ) {
			get_template_part( 'content', 'single' );
		}
		if ( ! did_action( 'loop_start' ) ) {
			// Fallback: just output content the normal way
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				</header>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		}
	}

endwhile;

get_sidebar();
get_footer();

