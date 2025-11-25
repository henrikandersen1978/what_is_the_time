<?php
/**
 * Dashboard view
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats = WTA_Queue::get_stats();
$cron_status = WTA_Cron_Manager::get_cron_status();
$post_counts = wp_count_posts( WTA_POST_TYPE );
?>

<div class="wrap wta-admin-wrap">
	<h1><?php esc_html_e( 'World Time AI - Dashboard', WTA_TEXT_DOMAIN ); ?></h1>
	<p style="color: #666; font-size: 14px;">
		<?php printf( esc_html__( 'Version: %s', WTA_TEXT_DOMAIN ), WTA_VERSION ); ?>
	</p>

	<div class="wta-dashboard-grid">
		<!-- Overview Cards -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Location Posts', WTA_TEXT_DOMAIN ); ?></h2>
			<div class="wta-stats-grid">
				<div class="wta-stat">
					<span class="wta-stat-value"><?php echo esc_html( $post_counts->publish ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Published', WTA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-value"><?php echo esc_html( $post_counts->draft ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Draft', WTA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-value"><?php echo esc_html( $post_counts->publish + $post_counts->draft ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Total', WTA_TEXT_DOMAIN ); ?></span>
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
					<span class="wta-stat-value wta-stat-warning"><?php echo esc_html( $stats['by_status']['pending'] ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Pending', WTA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-value wta-stat-info"><?php echo esc_html( $stats['by_status']['processing'] ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Processing', WTA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-value wta-stat-success"><?php echo esc_html( $stats['by_status']['done'] ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Done', WTA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-value wta-stat-error"><?php echo esc_html( $stats['by_status']['error'] ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Errors', WTA_TEXT_DOMAIN ); ?></span>
				</div>
			</div>
			<p>
				<button type="button" class="button" id="wta-refresh-stats">
					<?php esc_html_e( 'Refresh Stats', WTA_TEXT_DOMAIN ); ?>
				</button>
			</p>
		</div>

		<!-- Queue Breakdown -->
		<?php if ( ! empty( $stats['by_type'] ) ) : ?>
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Queue by Type', WTA_TEXT_DOMAIN ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Pending', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Processing', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Done', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Errors', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Total', WTA_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stats['by_type'] as $type => $type_stats ) : ?>
					<tr>
						<td><strong><?php echo esc_html( ucfirst( $type ) ); ?></strong></td>
						<td><?php echo esc_html( $type_stats['pending'] ); ?></td>
						<td><?php echo esc_html( $type_stats['processing'] ); ?></td>
						<td><?php echo esc_html( $type_stats['done'] ); ?></td>
						<td><?php echo esc_html( $type_stats['error'] ); ?></td>
						<td><?php echo esc_html( $type_stats['total'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<!-- Cron Status -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Cron Jobs Status', WTA_TEXT_DOMAIN ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Job', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Status', WTA_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Next Run', WTA_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cron_status as $hook => $status ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $status['label'] ); ?></strong></td>
						<td>
							<?php if ( $status['enabled'] ) : ?>
								<span class="wta-status-badge wta-status-active"><?php esc_html_e( 'Active', WTA_TEXT_DOMAIN ); ?></span>
							<?php else : ?>
								<span class="wta-status-badge wta-status-inactive"><?php esc_html_e( 'Inactive', WTA_TEXT_DOMAIN ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( $status['next_run'] ) {
								echo esc_html( human_time_diff( $status['next_run'] ) );
								echo ' ';
								esc_html_e( '(from now)', WTA_TEXT_DOMAIN );
							} else {
								esc_html_e( 'Not scheduled', WTA_TEXT_DOMAIN );
							}
							?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Quick Actions -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Quick Actions', WTA_TEXT_DOMAIN ); ?></h2>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wta-import' ) ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'Start New Import', WTA_TEXT_DOMAIN ); ?>
				</a>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wta-tools' ) ); ?>" class="button button-large">
					<?php esc_html_e( 'View Logs & Tools', WTA_TEXT_DOMAIN ); ?>
				</a>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WTA_POST_TYPE ) ); ?>" class="button button-large">
					<?php esc_html_e( 'Manage Locations', WTA_TEXT_DOMAIN ); ?>
				</a>
			</p>
		</div>

		<!-- System Info -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'System Information', WTA_TEXT_DOMAIN ); ?></h2>
			<table class="wta-info-table">
				<tr>
					<td><?php esc_html_e( 'Plugin Version:', WTA_TEXT_DOMAIN ); ?></td>
					<td><strong><?php echo esc_html( WTA_VERSION ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'WordPress Version:', WTA_TEXT_DOMAIN ); ?></td>
					<td><strong><?php echo esc_html( get_bloginfo( 'version' ) ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'PHP Version:', WTA_TEXT_DOMAIN ); ?></td>
					<td><strong><?php echo esc_html( PHP_VERSION ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Yoast SEO:', WTA_TEXT_DOMAIN ); ?></td>
					<td>
						<?php if ( WTA_Utils::is_yoast_active() ) : ?>
							<span class="wta-status-badge wta-status-active"><?php esc_html_e( 'Active', WTA_TEXT_DOMAIN ); ?></span>
						<?php else : ?>
							<span class="wta-status-badge wta-status-inactive"><?php esc_html_e( 'Not Active', WTA_TEXT_DOMAIN ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Memory Usage:', WTA_TEXT_DOMAIN ); ?></td>
					<td><strong><?php echo esc_html( WTA_Utils::get_memory_usage() ); ?></strong></td>
				</tr>
			</table>
		</div>
	</div>
</div>





