<?php
/**
 * Debug Info Page
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="wta-card">
		<h2>Location Structure</h2>
		
		<?php
		// Get Europa
		$europa = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'draft' ),
			'meta_query'     => array(
				array(
					'key'     => 'wta_type',
					'value'   => 'continent',
					'compare' => '='
				)
			)
		) );
		
		if ( ! empty( $europa ) ) {
			$continent = $europa[0];
			echo '<h3>Continent: ' . esc_html( $continent->post_title ) . ' (ID: ' . $continent->ID . ')</h3>';
			
			// Get children (countries)
			$countries = get_posts( array(
				'post_type'      => WTA_POST_TYPE,
				'post_parent'    => $continent->ID,
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
			) );
			
			echo '<p><strong>Child Countries:</strong> ' . count( $countries ) . '</p>';
			
			if ( ! empty( $countries ) ) {
				echo '<table class="widefat">';
				echo '<thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Cities</th></tr></thead>';
				echo '<tbody>';
				
				foreach ( $countries as $country ) {
					$country_type = get_post_meta( $country->ID, 'wta_type', true );
					
					// Get cities for this country
					$cities = get_posts( array(
						'post_type'      => WTA_POST_TYPE,
						'post_parent'    => $country->ID,
						'posts_per_page' => -1,
						'post_status'    => array( 'publish', 'draft' ),
					) );
					
					echo '<tr>';
					echo '<td>' . $country->ID . '</td>';
					echo '<td>' . esc_html( $country->post_title ) . '</td>';
					echo '<td>' . esc_html( $country_type ) . '</td>';
					echo '<td>' . count( $cities ) . '</td>';
					echo '</tr>';
				}
				
				echo '</tbody></table>';
			}
		} else {
			echo '<p>No continent found.</p>';
		}
		?>
		
		<hr>
		
		<h2>All Cities</h2>
		
		<?php
		// Get all cities
		$all_cities = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => 20,
			'post_status'    => array( 'publish', 'draft' ),
			'meta_query'     => array(
				array(
					'key'     => 'wta_type',
					'value'   => 'city',
					'compare' => '='
				)
			)
		) );
		
		if ( ! empty( $all_cities ) ) {
			echo '<p><strong>Total Cities:</strong> ' . count( $all_cities ) . '</p>';
			echo '<table class="widefat">';
			echo '<thead><tr><th>ID</th><th>Title</th><th>Parent ID</th><th>Parent Title</th><th>Population</th><th>Timezone</th></tr></thead>';
			echo '<tbody>';
			
			foreach ( $all_cities as $city ) {
				$parent_id = $city->post_parent;
				$parent_title = $parent_id ? get_the_title( $parent_id ) : 'NONE';
				$population = get_post_meta( $city->ID, 'wta_population', true );
				$timezone = get_post_meta( $city->ID, 'wta_timezone', true );
				
				echo '<tr>';
				echo '<td>' . $city->ID . '</td>';
				echo '<td>' . esc_html( $city->post_title ) . '</td>';
				echo '<td>' . ( $parent_id ? $parent_id : '<strong style="color:red;">0</strong>' ) . '</td>';
				echo '<td>' . esc_html( $parent_title ) . '</td>';
				echo '<td>' . ( $population ? number_format( $population ) : '<strong style="color:red;">NONE</strong>' ) . '</td>';
				echo '<td>' . esc_html( $timezone ) . '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<p><strong style="color:red;">No cities found!</strong></p>';
		}
		?>
		
		<hr>
		
		<h2>Test Query (Same as shortcode)</h2>
		
		<?php
		if ( ! empty( $europa ) ) {
			$continent_id = $europa[0]->ID;
			
			// Same query as shortcode
			$children = get_posts( array(
				'post_type'      => WTA_POST_TYPE,
				'post_parent'    => $continent_id,
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
			) );
			
			echo '<p><strong>Children found:</strong> ' . count( $children ) . '</p>';
			
			if ( ! empty( $children ) ) {
				$child_ids = wp_list_pluck( $children, 'ID' );
				echo '<p><strong>Child IDs:</strong> ' . implode( ', ', $child_ids ) . '</p>';
				
				// Query for cities
				$major_cities = get_posts( array(
					'post_type'      => WTA_POST_TYPE,
					'posts_per_page' => 12,
					'post_parent__in' => $child_ids,
					'orderby'        => 'meta_value_num',
					'meta_key'       => 'wta_population',
					'order'          => 'DESC',
					'post_status'    => array( 'publish', 'draft' ),
				) );
				
				echo '<p><strong>Cities found:</strong> ' . count( $major_cities ) . '</p>';
				
				if ( ! empty( $major_cities ) ) {
					echo '<ul>';
					foreach ( $major_cities as $city ) {
						$pop = get_post_meta( $city->ID, 'wta_population', true );
						echo '<li>' . esc_html( $city->post_title ) . ' (Pop: ' . number_format( $pop ) . ', Parent: ' . $city->post_parent . ')</li>';
					}
					echo '</ul>';
				} else {
					echo '<p style="color:red;"><strong>NO CITIES MATCHED THE QUERY!</strong></p>';
					
					// Try without meta_key requirement
					$test_cities = get_posts( array(
						'post_type'      => WTA_POST_TYPE,
						'posts_per_page' => 12,
						'post_parent__in' => $child_ids,
						'post_status'    => array( 'publish', 'draft' ),
					) );
					
					echo '<p><strong>Cities WITHOUT population filter:</strong> ' . count( $test_cities ) . '</p>';
					
					if ( ! empty( $test_cities ) ) {
						echo '<p style="color:orange;">→ Cities exist but don\'t have wta_population meta!</p>';
						echo '<ul>';
						foreach ( $test_cities as $city ) {
							$pop = get_post_meta( $city->ID, 'wta_population', true );
							$type = get_post_meta( $city->ID, 'wta_type', true );
							echo '<li>' . esc_html( $city->post_title ) . ' (Type: ' . $type . ', Pop: ' . ( $pop ? number_format( $pop ) : 'MISSING' ) . ')</li>';
						}
						echo '</ul>';
					}
				}
			}
		}
		?>
		
	</div>
</div>

<style>
.wta-card {
	background: #fff;
	padding: 20px;
	margin: 20px 0;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>

