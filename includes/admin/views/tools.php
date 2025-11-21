<?php
/**
 * Tools & Logs view
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logs = WTA_Logger::get_logs( 50 );
$log_counts = WTA_Logger::get_log_counts();
?>

<div class="wrap wta-admin-wrap">
	<h1><?php esc_html_e( 'Tools & Logs', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-admin-grid">
		<!-- Tools -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Data Management Tools', WTA_TEXT_DOMAIN ); ?></h2>
			
			<div class="wta-tool-group">
				<h3><?php esc_html_e( 'Reset Failed Items', WTA_TEXT_DOMAIN ); ?></h3>
				<p><?php esc_html_e( 'Reset all failed queue items back to pending status for re-processing.', WTA_TEXT_DOMAIN ); ?></p>
				<p>
					<button type="button" id="wta-retry-failed" class="button">
						<?php esc_html_e( 'Retry Failed Items', WTA_TEXT_DOMAIN ); ?>
					</button>
				</p>
			</div>

			<div class="wta-tool-group">
				<h3><?php esc_html_e( 'Reset Stuck Items', WTA_TEXT_DOMAIN ); ?></h3>
				<p><?php esc_html_e( 'Reset items stuck in "processing" status (older than 5 minutes).', WTA_TEXT_DOMAIN ); ?></p>
				<p>
					<button type="button" id="wta-reset-stuck" class="button">
						<?php esc_html_e( 'Reset Stuck Items', WTA_TEXT_DOMAIN ); ?>
					</button>
				</p>
			</div>

			<div class="wta-tool-group wta-tool-danger">
				<h3><?php esc_html_e( 'Full Data Reset', WTA_TEXT_DOMAIN ); ?></h3>
				<p class="description">
					<strong><?php esc_html_e( 'Warning:', WTA_TEXT_DOMAIN ); ?></strong>
					<?php esc_html_e( 'This will permanently delete ALL imported location posts and clear the entire queue.', WTA_TEXT_DOMAIN ); ?>
				</p>
				<p>
					<button type="button" id="wta-reset-all" class="button button-danger">
						<?php esc_html_e( 'Reset All Data', WTA_TEXT_DOMAIN ); ?>
					</button>
				</p>
			</div>

			<div class="wta-tool-group">
				<h3><?php esc_html_e( 'Clear Cache', WTA_TEXT_DOMAIN ); ?></h3>
				<p><?php esc_html_e( 'Clear GitHub data cache and force fresh data fetch on next import.', WTA_TEXT_DOMAIN ); ?></p>
				<p>
					<button type="button" id="wta-clear-cache" class="button">
						<?php esc_html_e( 'Clear Cache', WTA_TEXT_DOMAIN ); ?>
					</button>
				</p>
			</div>
		</div>

		<!-- Log Summary -->
		<div class="wta-card">
			<h2><?php esc_html_e( 'Log Summary', WTA_TEXT_DOMAIN ); ?></h2>
			<div class="wta-stats-grid">
				<div class="wta-stat">
					<span class="wta-stat-value wta-stat-error"><?php echo esc_html( $log_counts['error'] ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Errors', WTA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-value wta-stat-warning"><?php echo esc_html( $log_counts['warning'] ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Warnings', WTA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-value wta-stat-info"><?php echo esc_html( $log_counts['info'] ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Info', WTA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="wta-stat">
					<span class="wta-stat-value"><?php echo esc_html( $log_counts['debug'] ); ?></span>
					<span class="wta-stat-label"><?php esc_html_e( 'Debug', WTA_TEXT_DOMAIN ); ?></span>
				</div>
			</div>
			<p>
				<button type="button" id="wta-clear-logs" class="button">
					<?php esc_html_e( 'Clear All Logs', WTA_TEXT_DOMAIN ); ?>
				</button>
			</p>
		</div>

		<!-- Recent Logs -->
		<div class="wta-card wta-card-wide">
			<h2><?php esc_html_e( 'Recent Logs', WTA_TEXT_DOMAIN ); ?></h2>
			
			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No logs found.', WTA_TEXT_DOMAIN ); ?></p>
			<?php else : ?>
				<div class="wta-logs-container">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 140px;"><?php esc_html_e( 'Time', WTA_TEXT_DOMAIN ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Level', WTA_TEXT_DOMAIN ); ?></th>
								<th><?php esc_html_e( 'Message', WTA_TEXT_DOMAIN ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
							<tr class="wta-log-<?php echo esc_attr( $log['level'] ); ?>">
								<td><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $log['timestamp'] ) ); ?></td>
								<td>
									<span class="wta-log-badge wta-log-badge-<?php echo esc_attr( $log['level'] ); ?>">
										<?php echo esc_html( strtoupper( $log['level'] ) ); ?>
									</span>
								</td>
								<td>
									<div class="wta-log-message"><?php echo esc_html( $log['message'] ); ?></div>
									<?php if ( ! empty( $log['context'] ) ) : ?>
									<details class="wta-log-context">
										<summary><?php esc_html_e( 'Context', WTA_TEXT_DOMAIN ); ?></summary>
										<pre><?php echo esc_html( print_r( $log['context'], true ) ); ?></pre>
									</details>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>




