<?php
/**
 * AI Settings view
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/admin/views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$openai_key = get_option( 'wta_openai_api_key' );
$has_key = ! empty( $openai_key );
?>

<div class="wrap wta-admin-wrap">
	<h1><?php esc_html_e( 'AI Settings', WTA_TEXT_DOMAIN ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'wta_settings_group' ); ?>
		
		<div class="wta-admin-grid">
			<!-- OpenAI API Configuration -->
			<div class="wta-card wta-card-wide">
				<h2><?php esc_html_e( 'OpenAI API Configuration', WTA_TEXT_DOMAIN ); ?></h2>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wta_openai_api_key">
								<?php esc_html_e( 'API Key', WTA_TEXT_DOMAIN ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<input type="password" id="wta_openai_api_key" name="wta_openai_api_key" 
								value="<?php echo esc_attr( $openai_key ); ?>" 
								class="large-text" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: OpenAI API URL */
									esc_html__( 'Get your API key from %s', WTA_TEXT_DOMAIN ),
									'<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>'
								);
								?>
							</p>
							<?php if ( $has_key ) : ?>
							<p>
								<button type="button" id="wta-test-openai-api" class="button">
									<?php esc_html_e( 'Test API Connection', WTA_TEXT_DOMAIN ); ?>
								</button>
								<span id="wta-test-openai-result"></span>
							</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_openai_model">
								<?php esc_html_e( 'Model', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<select id="wta_openai_model" name="wta_openai_model">
								<option value="gpt-4" <?php selected( get_option( 'wta_openai_model' ), 'gpt-4' ); ?>>
									GPT-4
								</option>
								<option value="gpt-4-turbo-preview" <?php selected( get_option( 'wta_openai_model' ), 'gpt-4-turbo-preview' ); ?>>
									GPT-4 Turbo
								</option>
								<option value="gpt-3.5-turbo" <?php selected( get_option( 'wta_openai_model' ), 'gpt-3.5-turbo' ); ?>>
									GPT-3.5 Turbo
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Choose the OpenAI model to use for content generation', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_openai_temperature">
								<?php esc_html_e( 'Temperature', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="number" id="wta_openai_temperature" name="wta_openai_temperature" 
								value="<?php echo esc_attr( get_option( 'wta_openai_temperature', 0.7 ) ); ?>" 
								min="0" max="2" step="0.1" style="width: 100px;" />
							<p class="description">
								<?php esc_html_e( 'Controls randomness (0.0 = focused, 2.0 = creative). Recommended: 0.7', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wta_openai_max_tokens">
								<?php esc_html_e( 'Max Tokens', WTA_TEXT_DOMAIN ); ?>
							</label>
						</th>
						<td>
							<input type="number" id="wta_openai_max_tokens" name="wta_openai_max_tokens" 
								value="<?php echo esc_attr( get_option( 'wta_openai_max_tokens', 1000 ) ); ?>" 
								min="100" max="4000" step="100" style="width: 100px;" />
							<p class="description">
								<?php esc_html_e( 'Maximum tokens for content generation. Recommended: 1000', WTA_TEXT_DOMAIN ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Yoast SEO Integration -->
			<div class="wta-card">
				<h2><?php esc_html_e( 'Yoast SEO Integration', WTA_TEXT_DOMAIN ); ?></h2>
				
				<?php if ( WTA_Utils::is_yoast_active() ) : ?>
					<p class="wta-notice wta-notice-success">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Yoast SEO is active', WTA_TEXT_DOMAIN ); ?>
					</p>
				<?php else : ?>
					<p class="wta-notice wta-notice-warning">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Yoast SEO is not active. SEO data will be stored in custom fields.', WTA_TEXT_DOMAIN ); ?>
					</p>
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Integration Enabled', WTA_TEXT_DOMAIN ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="wta_yoast_integration_enabled" value="1" 
									<?php checked( get_option( 'wta_yoast_integration_enabled', true ) ); ?> />
								<?php esc_html_e( 'Write SEO data to Yoast fields when available', WTA_TEXT_DOMAIN ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Allow Overwrite', WTA_TEXT_DOMAIN ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="wta_yoast_allow_overwrite" value="1" 
									<?php checked( get_option( 'wta_yoast_allow_overwrite', true ) ); ?> />
								<?php esc_html_e( 'Allow plugin to overwrite existing Yoast SEO data', WTA_TEXT_DOMAIN ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Save AI Settings', WTA_TEXT_DOMAIN ), 'primary', 'submit', true, array( 'style' => 'margin-top: 20px;' ) ); ?>
	</form>
</div>






