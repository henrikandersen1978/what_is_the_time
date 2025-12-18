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
	$post_id = get_the_ID();
	
	// Build breadcrumb
	$breadcrumb_items = array();
	$breadcrumb_items[] = array(
		'name' => 'Forside',
		'url'  => home_url( '/' ),
	);
	
	// Add parent hierarchy
	$ancestors = array();
	$parent_id = wp_get_post_parent_id( $post_id );
	while ( $parent_id ) {
		$ancestors[] = $parent_id;
		$parent_id = wp_get_post_parent_id( $parent_id );
	}
	$ancestors = array_reverse( $ancestors );
	
	foreach ( $ancestors as $ancestor_id ) {
		$breadcrumb_items[] = array(
			'name' => get_the_title( $ancestor_id ),
			'url'  => get_permalink( $ancestor_id ),
		);
	}
	
	// Add current page
	$breadcrumb_items[] = array(
		'name' => $name_local,
		'url'  => get_permalink( $post_id ),
	);
	
	// Check if page has child locations or major cities
	$has_children = false;
	$has_major_cities = false;
	
	if ( in_array( $type, array( 'continent', 'country' ) ) ) {
		$children = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => $post_id,
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'draft' ),
		) );
		$has_children = ! empty( $children );
		$has_major_cities = true; // Continents and countries always show major cities
	}
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'wta-location-single' ); ?>>
		
		<?php if ( count( $breadcrumb_items ) > 1 ) : ?>
		<!-- Breadcrumb Navigation -->
		<nav class="wta-breadcrumb" aria-label="Breadcrumb">
			<ol class="wta-breadcrumb-list">
				<?php foreach ( $breadcrumb_items as $index => $item ) : ?>
					<?php if ( $index === count( $breadcrumb_items ) - 1 ) : ?>
						<li class="wta-breadcrumb-item wta-breadcrumb-current" aria-current="page">
							<span><?php echo esc_html( $item['name'] ); ?></span>
						</li>
					<?php else : ?>
						<li class="wta-breadcrumb-item">
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php endif; ?>
		
		<header class="wta-location-header">
			<?php
			// v3.0.20: Use SEO-optimized H1 from meta (includes country name for cities)
			// This ensures H1 is correct from server-side render (no JavaScript needed)
			$seo_h1 = get_post_meta( get_the_ID(), '_pilanto_page_h1', true );
			
			if ( ! empty( $seo_h1 ) ) {
				// Use pre-generated SEO H1 (e.g., "Hvad er klokken i København, Danmark?")
				printf( '<h1 class="wta-location-title">%s</h1>', esc_html( $seo_h1 ) );
			} elseif ( 'city' === $type ) {
				// Fallback for cities without meta
				printf( '<h1 class="wta-location-title">%s</h1>', esc_html( sprintf( __( 'Hvad er klokken i %s?', 'world-time-ai' ), $name_local ) ) );
			} else {
				// Fallback for continents/countries
				printf( '<h1 class="wta-location-title">%s</h1>', esc_html( $name_local ) );
			}
			?>
		</header>
		
		<?php
		// v3.0.25: Extract intro paragraph for continents/countries to show before navigation buttons
		// CRITICAL FIX: Content from test mode already contains <p> tags, so split by </p> tag
		$intro_paragraph = '';
		$remaining_content = get_the_content();
		
		if ( in_array( $type, array( 'continent', 'country' ) ) && ! empty( $remaining_content ) ) {
			// Content already has HTML tags: "<p>Intro...</p>\n\n[shortcode]..."
			// Split at first </p> tag to extract intro
			$pos = strpos( $remaining_content, '</p>' );
			
			if ( false !== $pos ) {
				// Extract intro (including </p> tag)
				$intro_raw = substr( $remaining_content, 0, $pos + 4 );
				// Get remaining content (after </p> and whitespace)
				$remaining_raw = trim( substr( $remaining_content, $pos + 4 ) );
				
				// Apply filters to intro (for consistency, though already has <p> tags)
				$intro_paragraph = apply_filters( 'the_content', $intro_raw );
				// Remaining content will be filtered later
				$remaining_content = $remaining_raw;
			} else {
				// Fallback: No </p> tag found, show all normally
				$remaining_content = get_the_content();
			}
		}
		
		// Show intro paragraph before navigation buttons (continent/country only)
		if ( ! empty( $intro_paragraph ) ) {
			echo '<div class="wta-intro-section">' . "\n";
			echo $intro_paragraph . "\n";
			echo '</div>' . "\n";
		}
		?>
		
		<?php if ( $has_children || $has_major_cities ) : ?>
		<!-- Quick Navigation -->
		<div class="wta-quick-nav">
			<?php if ( $has_children ) : ?>
				<?php 
				$child_label = ( 'continent' === $type ) ? '📍 Se alle lande' : '📍 Se alle steder';
				?>
				<a href="#child-locations" class="wta-quick-nav-btn wta-smooth-scroll">
					<?php echo esc_html( $child_label ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $has_major_cities ) : ?>
				<a href="#major-cities" class="wta-quick-nav-btn wta-smooth-scroll">
					🕐 Live tidspunkter
				</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>

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
			<?php 
			// v3.0.24: For continents/countries, show remaining content (intro already shown above)
			if ( in_array( $type, array( 'continent', 'country' ) ) && ! empty( $remaining_content ) ) {
				// Apply filters to remaining content (shortcodes, wpautop, etc.)
				echo apply_filters( 'the_content', $remaining_content );
			} else {
				// For cities, show all content normally
				the_content();
			}
			?>
		</div>

		<?php
		// Show child locations
		$children = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'post_parent'    => get_the_ID(),
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'draft' ),
		) );

		if ( ! empty( $children ) ) :
			$child_type = ( 'continent' === $type ) ? __( 'Countries', 'world-time-ai' ) : __( 'Cities', 'world-time-ai' );
			?>
			<div id="child-locations" class="wta-children-list">
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
	// Output Schema.org JSON-LD breadcrumb
	if ( count( $breadcrumb_items ) > 1 ) :
		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(),
		);
		
		foreach ( $breadcrumb_items as $index => $item ) {
			$schema['itemListElement'][] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $item['name'],
				'item'     => $item['url'],
			);
		}
		?>
		<script type="application/ld+json">
		<?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); ?>
		</script>
	<?php endif; ?>

	<?php
endwhile;

get_footer();

