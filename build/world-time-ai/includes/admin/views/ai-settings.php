<?php
/**
 * AI Settings admin page.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission
if ( isset( $_POST['submit'] ) && check_admin_referer( 'wta_ai_settings' ) ) {
	// Detect test mode change (v2.34.20)
	$old_test_mode = get_option( 'wta_test_mode', 0 );
	$new_test_mode = isset( $_POST['wta_test_mode'] ) ? 1 : 0;
	$test_mode_disabled = ( $old_test_mode == 1 && $new_test_mode == 0 );
	
	update_option( 'wta_openai_api_key', sanitize_text_field( $_POST['wta_openai_api_key'] ) );
	update_option( 'wta_openai_model', sanitize_text_field( $_POST['wta_openai_model'] ) );
	update_option( 'wta_openai_temperature', floatval( $_POST['wta_openai_temperature'] ) );
	update_option( 'wta_openai_max_tokens', intval( $_POST['wta_openai_max_tokens'] ) );
	update_option( 'wta_test_mode', $new_test_mode );
	
	// Show prompt for AI regeneration if test mode was disabled (v2.34.20)
	if ( $test_mode_disabled ) {
		$post_count = wp_count_posts( WTA_POST_TYPE );
		$published = isset( $post_count->publish ) ? $post_count->publish : 0;
		$estimated_cost = round( $published * 8 * 0.00017, 2 );
		
		echo '<div class="notice notice-warning" id="wta-test-mode-disabled-notice">';
		echo '<h3>' . esc_html__( '✅ Test Mode Disabled', WTA_TEXT_DOMAIN ) . '</h3>';
		echo '<p><strong>' . esc_html__( 'Would you like to generate AI content for all location posts now?', WTA_TEXT_DOMAIN ) . '</strong></p>';
		echo '<p>' . sprintf( esc_html__( 'This will queue %s posts for AI content generation.', WTA_TEXT_DOMAIN ), number_format( $published ) ) . '</p>';
		echo '<p>' . sprintf( esc_html__( 'Estimated cost: ~$%s (gpt-4o-mini)', WTA_TEXT_DOMAIN ), $estimated_cost ) . '</p>';
		echo '<p>';
		echo '<button type="button" class="button button-primary" id="wta-trigger-ai-regeneration">' . esc_html__( 'Yes, Generate AI Content Now', WTA_TEXT_DOMAIN ) . '</button> ';
		echo '<button type="button" class="button" id="wta-dismiss-ai-prompt">' . esc_html__( 'No, I\'ll Do It Later', WTA_TEXT_DOMAIN ) . '</button>';
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'You can also manually trigger this later from Tools → Regenerate ALL AI Content', WTA_TEXT_DOMAIN ) . '</p>';
		echo '</div>';
	} else {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', WTA_TEXT_DOMAIN ) . '</p></div>';
	}
}

$api_key = get_option( 'wta_openai_api_key', '' );
$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
$temperature = get_option( 'wta_openai_temperature', 0.7 );
$max_tokens = get_option( 'wta_openai_max_tokens', 2000 );
$test_mode = get_option( 'wta_test_mode', 0 );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'AI Settings', WTA_TEXT_DOMAIN ); ?></h1>

	<div class="wta-admin-page">
		<div class="wta-card">
			<h2><?php esc_html_e( 'OpenAI API Configuration', WTA_TEXT_DOMAIN ); ?></h2>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'wta_ai_settings' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_openai_api_key"><?php esc_html_e( 'API Key', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="password" id="wta_openai_api_key" name="wta_openai_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
							<p class="description">
								<?php
								printf(
									/* translators: %s: OpenAI URL */
									esc_html__( 'Get your API key from %s', WTA_TEXT_DOMAIN ),
									'<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_openai_model"><?php esc_html_e( 'Model', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<select id="wta_openai_model" name="wta_openai_model">
								<option value="gpt-4o-mini" <?php selected( $model, 'gpt-4o-mini' ); ?>>GPT-4o Mini (Recommended)</option>
								<option value="gpt-4o" <?php selected( $model, 'gpt-4o' ); ?>>GPT-4o</option>
								<option value="gpt-4" <?php selected( $model, 'gpt-4' ); ?>>GPT-4</option>
								<option value="gpt-3.5-turbo" <?php selected( $model, 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo</option>
							</select>
							<p class="description"><?php esc_html_e( 'GPT-4o Mini offers the best balance of quality and cost.', WTA_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_openai_temperature"><?php esc_html_e( 'Temperature', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="wta_openai_temperature" name="wta_openai_temperature" value="<?php echo esc_attr( $temperature ); ?>" min="0" max="1" step="0.1" class="small-text">
							<input type="range" id="wta_temperature_slider" min="0" max="1" step="0.1" value="<?php echo esc_attr( $temperature ); ?>">
							<span id="wta_temperature_display"><?php echo esc_html( $temperature ); ?></span>
							<p class="description"><?php esc_html_e( '0 = more deterministic, 1 = more creative. Recommended: 0.7', WTA_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_openai_max_tokens"><?php esc_html_e( 'Max Tokens', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" id="wta_openai_max_tokens" name="wta_openai_max_tokens" value="<?php echo esc_attr( $max_tokens ); ?>" min="100" max="4000" class="small-text">
							<p class="description"><?php esc_html_e( 'Maximum tokens per request. Recommended: 2000', WTA_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_test_mode"><?php esc_html_e( 'Test Mode', WTA_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<label for="wta_test_mode">
								<input type="checkbox" id="wta_test_mode" name="wta_test_mode" value="1" <?php checked( $test_mode, 1 ); ?>>
								<?php esc_html_e( 'Use template content instead of AI (no OpenAI costs)', WTA_TEXT_DOMAIN ); ?>
							</label>
							<p class="description">
								<strong style="color: #2271b1;">✓ Aktivér for test-imports:</strong> Bruger simple content templates i stedet for OpenAI. 
								Ingen AI-omkostninger. Perfekt til at teste GPS-validering, struktur og performance med fuld global import (152k byer).<br>
								<strong style="color: #d63638;">⚠ Deaktivér for produktion:</strong> Bruger OpenAI til at generere unikt, SEO-optimeret indhold for hver lokation.
								<br><br>
								<em>💰 Cost savings: ~$210 per fuld import når aktiveret.</em>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', WTA_TEXT_DOMAIN ); ?>">
					<button type="button" class="button" id="wta-test-openai"><?php esc_html_e( 'Test Connection', WTA_TEXT_DOMAIN ); ?></button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="wta-test-result"></div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Temperature slider sync
	$('#wta_temperature_slider').on('input', function() {
		var val = $(this).val();
		$('#wta_openai_temperature').val(val);
		$('#wta_temperature_display').text(val);
	});

	$('#wta_openai_temperature').on('input', function() {
		var val = $(this).val();
		$('#wta_temperature_slider').val(val);
		$('#wta_temperature_display').text(val);
	});

	// Test connection
	$('#wta-test-openai').on('click', function() {
		var $button = $(this);
		var $spinner = $button.next('.spinner');
		var $result = $('#wta-test-result');
		
		$button.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.html('');
		
		$.ajax({
			url: wtaAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wta_test_openai_connection',
				nonce: wtaAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
				} else {
					$result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
				}
			},
		error: function() {
			$result.html('<div class="notice notice-error"><p>❌ AJAX request failed</p></div>');
		},
		complete: function() {
			$button.prop('disabled', false);
			$spinner.removeClass('is-active');
		}
	});
});

// Handle test mode disabled prompt - Trigger AI regeneration (v2.34.20)
$('#wta-trigger-ai-regeneration').on('click', function() {
	var $button = $(this);
	var $notice = $('#wta-test-mode-disabled-notice');
	
	$button.prop('disabled', true);
	$notice.html('<p>⏳ Queuing all posts for AI content generation... This may take a minute.</p>');
	
	$.ajax({
		url: wtaAdmin.ajaxUrl,
		type: 'POST',
		data: {
			action: 'wta_regenerate_all_ai',
			nonce: wtaAdmin.nonce
		},
		timeout: 180000, // 3 minutes
		success: function(response) {
			if (response.success) {
				$notice.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p><p>Redirecting to dashboard...</p></div>');
				
				// Redirect to dashboard after 2 seconds
				setTimeout(function() {
					window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=world-time-ai' ) ); ?>';
				}, 2000);
			} else {
				$notice.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
			}
		},
		error: function(xhr, status, error) {
			if (status === 'timeout') {
				$notice.html('<div class="notice notice-warning"><p>⚠️ Request timed out. The queuing may still be running. Check queue status in dashboard.</p></div>');
			} else {
				$notice.html('<div class="notice notice-error"><p>❌ Failed: ' + error + '</p></div>');
			}
		}
	});
});

// Handle test mode disabled prompt - Dismiss
$('#wta-dismiss-ai-prompt').on('click', function() {
	$('#wta-test-mode-disabled-notice').fadeOut();
});
});
</script>


