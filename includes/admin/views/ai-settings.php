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
	update_option( 'wta_openai_api_key', sanitize_text_field( $_POST['wta_openai_api_key'] ) );
	update_option( 'wta_openai_model', sanitize_text_field( $_POST['wta_openai_model'] ) );
	update_option( 'wta_openai_temperature', floatval( $_POST['wta_openai_temperature'] ) );
	update_option( 'wta_openai_max_tokens', intval( $_POST['wta_openai_max_tokens'] ) );
	
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', WTA_TEXT_DOMAIN ) . '</p></div>';
}

$api_key = get_option( 'wta_openai_api_key', '' );
$model = get_option( 'wta_openai_model', 'gpt-4o-mini' );
$temperature = get_option( 'wta_openai_temperature', 0.7 );
$max_tokens = get_option( 'wta_openai_max_tokens', 2000 );
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
});
</script>

