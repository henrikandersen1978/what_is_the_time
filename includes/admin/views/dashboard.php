<?php
/**
 * Dashboard admin page.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get statistics
$posts_count = wp_count_posts( WTA_POST_TYPE );

// v3.0.43+ - Get Action Scheduler stats instead of custom queue
global $wpdb;
$as_table = $wpdb->prefix . 'actionscheduler_actions';

$as_pending = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$as_table} 
	WHERE hook LIKE 'wta_%' 
	AND status = 'pending'" 
);
$as_running = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$as_table} 
	WHERE hook LIKE 'wta_%' 
	AND status IN ('in-progress', 'running')" 
);
$as_complete = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$as_table} 
	WHERE hook LIKE 'wta_%' 
	AND status = 'complete'" 
);
$as_failed = $wpdb->get_var( 
	"SELECT COUNT(*) FROM {$as_table} 
	WHERE hook LIKE 'wta_%' 
	AND status = 'failed'" 
);

// Get counts by type (continent, country, city, timezone, AI)
// v3.0.47: Use WTA_POST_TYPE constant with $wpdb->prepare for security
$continents_pending = $wpdb->get_var( 
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = %s
		AND p.post_status = 'draft'
		AND pm.meta_key = 'wta_type'
		AND pm.meta_value = 'continent'",
		WTA_POST_TYPE
	)
);
$countries_pending = $wpdb->get_var( 
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = %s
		AND p.post_status = 'draft'
		AND pm.meta_key = 'wta_type'
		AND pm.meta_value = 'country'",
		WTA_POST_TYPE
	)
);
$cities_pending = $wpdb->get_var( 
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = %s
		AND p.post_status = 'draft'
		AND pm.meta_key = 'wta_type'
		AND pm.meta_value = 'city'",
		WTA_POST_TYPE
	)
);

$continents_done = $wpdb->get_var( 
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = %s
		AND p.post_status = 'publish'
		AND pm.meta_key = 'wta_type'
		AND pm.meta_value = 'continent'",
		WTA_POST_TYPE
	)
);
$countries_done = $wpdb->get_var( 
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = %s
		AND p.post_status = 'publish'
		AND pm.meta_key = 'wta_type'
		AND pm.meta_value = 'country'",
		WTA_POST_TYPE
	)
);
$cities_done = $wpdb->get_var( 
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
		WHERE p.post_type = %s
		AND p.post_status = 'publish'
		AND pm.meta_key = 'wta_type'
		AND pm.meta_value = 'city'",
		WTA_POST_TYPE
	)
);

// Build stats array compatible with old structure
$queue_stats = array(
	'by_status' => array(
		'pending'    => intval( $as_pending ),
		'processing' => intval( $as_running ),
		'done'       => intval( $as_complete ),
		'error'      => intval( $as_failed ),
	),
	'by_type' => array(
		'continents' => array(
			'pending'    => intval( $continents_pending ),
			'processing' => 0,
			'done'       => intval( $continents_done ),
			'error'      => 0,
			'total'      => intval( $continents_pending + $continents_done ),
		),
		'countries' => array(
			'pending'    => intval( $countries_pending ),
			'processing' => 0,
			'done'       => intval( $countries_done ),
			'error'      => 0,
			'total'      => intval( $countries_pending + $countries_done ),
		),
		'cities' => array(
			'pending'    => intval( $cities_pending ),
			'processing' => 0,
			'done'       => intval( $cities_done ),
			'error'      => 0,
			'total'      => intval( $cities_pending + $cities_done ),
		),
	),
);
?>

<div class="wrap">
	<h1><?php esc_html_e( 'World Time AI Dashboard', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-dashboard">
		<!-- Location Posts -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Location Posts', WTA_TEXT_DOMAIN ); ?></h2>
			<div class="wta-stats-grid">
				<div class="wta-stat">
					<span class="wta-stat-label"><?php esc_html_e( 'Published', WTA_TEXT_DOMAIN ); ?></span>
					<span class="wta-stat-value"><?php echo esc_html( number_format( $posts_count->publish ) ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-label"><?php esc_html_e( 'Draft', WTA_TEXT_DOMAIN ); ?></span>
					<span class="wta-stat-value"><?php echo esc_html( number_format( $posts_count->draft ) ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-label"><?php esc_html_e( 'Total', WTA_TEXT_DOMAIN ); ?></span>
					<span class="wta-stat-value"><?php echo esc_html( number_format( $posts_count->publish + $posts_count->draft ) ); ?></span>
				</div>
			</div>
			<p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WTA_POST_TYPE ) ); ?>" class="button">
					<?php esc_html_e( 'View All Locations', WTA_TEXT_DOMAIN ); ?>
				</a>
			</p>
		</div>

		<!-- Queue Status -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Queue Status', WTA_TEXT_DOMAIN ); ?></h2>
			<div class="wta-stats-grid">
				<div class="wta-stat">
					<span class="wta-stat-label"><?php esc_html_e( 'Pending', WTA_TEXT_DOMAIN ); ?></span>
					<span class="wta-stat-value"><?php echo esc_html( number_format( $queue_stats['by_status']['pending'] ) ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-label"><?php esc_html_e( 'Processing', WTA_TEXT_DOMAIN ); ?></span>
					<span class="wta-stat-value"><?php echo esc_html( number_format( $queue_stats['by_status']['processing'] ) ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-label"><?php esc_html_e( 'Done', WTA_TEXT_DOMAIN ); ?></span>
					<span class="wta-stat-value"><?php echo esc_html( number_format( $queue_stats['by_status']['done'] ) ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-label"><?php esc_html_e( 'Errors', WTA_TEXT_DOMAIN ); ?></span>
					<span class="wta-stat-value wta-stat-error"><?php echo esc_html( number_format( $queue_stats['by_status']['error'] ) ); ?></span>
				</div>
			</div>
		</div>

		<!-- Queue by Type -->
		<?php if ( ! empty( $queue_stats['by_type'] ) ) : ?>
		<div class="wta-card">
			<h2><?php esc_html_e( 'Queue by Type', WTA_TEXT_DOMAIN ); ?></h2>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Pending', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Processing', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Done', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Error', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Total', WTA_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $queue_stats['by_type'] as $type => $counts ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $type ); ?></strong></td>
						<td><?php echo esc_html( number_format( $counts['pending'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $counts['processing'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $counts['done'] ) ); ?></td>
						<td><?php echo esc_html( number_format( $counts['error'] ) ); ?></td>
						<td><strong><?php echo esc_html( number_format( $counts['total'] ) ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<!-- Action Scheduler -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Background Processing', WTA_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'World Time AI uses Action Scheduler for reliable background processing.', WTA_TEXT_DOMAIN ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=action-scheduler' ) ); ?>" class="button">
					<?php esc_html_e( 'View Scheduled Actions', WTA_TEXT_DOMAIN ); ?>
				</a>
			</p>
		</div>

		<!-- Quick Actions -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Quick Actions', WTA_TEXT_DOMAIN ); ?></h2>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wta-data-import' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Import Data', WTA_TEXT_DOMAIN ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wta-tools' ) ); ?>" class="button">
					<?php esc_html_e( 'Tools & Maintenance', WTA_TEXT_DOMAIN ); ?>
				</a>
			</p>
		</div>

		<!-- System Info -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'System Information', WTA_TEXT_DOMAIN ); ?></h2>
			<table class="widefat">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Plugin Version', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( WTA_VERSION ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WordPress Version', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'PHP Version', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( phpversion() ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Memory Limit', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo esc_html( WP_MEMORY_LIMIT ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Action Scheduler', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo function_exists( 'as_schedule_recurring_action' ) ? '✅ Active' : '❌ Not Found'; ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'OpenAI API', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo ! empty( get_option( 'wta_openai_api_key' ) ) ? '✅ Configured' : '❌ Not Configured'; ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'TimeZoneDB API', WTA_TEXT_DOMAIN ); ?></strong></td>
						<td><?php echo ! empty( get_option( 'wta_timezonedb_api_key' ) ) ? '✅ Configured' : '❌ Not Configured'; ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>


