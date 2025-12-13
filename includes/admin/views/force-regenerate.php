<?php
/**
 * Force Regenerate - Manual AI content generation
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/admin/views
 * @since      2.35.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission
if ( isset( $_POST['wta_force_regenerate'] ) && check_admin_referer( 'wta_force_regenerate_action', 'wta_force_regenerate_nonce' ) ) {
	$post_id = absint( $_POST['post_id'] );
	
	if ( $post_id > 0 && get_post_type( $post_id ) === WTA_POST_TYPE ) {
		$start_time = microtime( true );
		
		try {
			// Load all required dependencies (v2.35.26)
			if ( ! class_exists( 'WTA_Logger' ) ) {
				require_once WTA_PLUGIN_DIR . '/includes/helpers/class-wta-logger.php';
			}
			if ( ! class_exists( 'WTA_FAQ_Generator' ) ) {
				require_once WTA_PLUGIN_DIR . '/includes/helpers/class-wta-faq-generator.php';
			}
			if ( ! class_exists( 'WTA_FAQ_Renderer' ) ) {
				require_once WTA_PLUGIN_DIR . '/includes/helpers/class-wta-faq-renderer.php';
			}
			if ( ! class_exists( 'WTA_AI_Processor' ) ) {
				require_once WTA_PLUGIN_DIR . '/includes/scheduler/class-wta-ai-processor.php';
			}
			
			// Force regenerate single post (NO queue involvement)
			$processor = new WTA_AI_Processor();
			$success = $processor->force_regenerate_single( $post_id );
			
			$elapsed = round( microtime( true ) - $start_time, 2 );
			$post_title = get_the_title( $post_id );
			
			if ( $success ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo '<strong>✅ Success!</strong> "' . esc_html( $post_title ) . '" (ID: ' . $post_id . ') regenerated in ' . $elapsed . ' seconds.';
				echo ' <a href="' . get_permalink( $post_id ) . '" target="_blank">View page</a>';
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>';
				echo '<strong>❌ Error:</strong> Failed to regenerate "' . esc_html( $post_title ) . '". Check logs for details.';
				echo '</p></div>';
			}
			
		} catch ( Exception $e ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>❌ Error:</strong> ' . esc_html( $e->getMessage() );
			echo '</p></div>';
		}
		
	} else {
		echo '<div class="notice notice-error"><p><strong>Error:</strong> Invalid post ID or not a location post.</p></div>';
	}
}
?>

<div class="wrap">
	<h1>🚀 Force Regenerate AI Content</h1>
	
	<div class="card" style="max-width: 600px;">
		<h2>Manual AI Content Generation</h2>
		<p>Generate AI content immediately for a specific post, bypassing the queue system.</p>
		<p><strong>⚠️ Use for testing only.</strong> This runs synchronously and may take 30-60 seconds.</p>
		
		<form method="post" action="">
			<?php wp_nonce_field( 'wta_force_regenerate_action', 'wta_force_regenerate_nonce' ); ?>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="post_id">Post ID</label>
					</th>
					<td>
						<input type="number" 
						       id="post_id" 
						       name="post_id" 
						       class="regular-text" 
						       min="1" 
						       required
						       placeholder="Enter post ID (e.g., 12345)">
						<p class="description">
							Find post ID in the URL when editing a location:<br>
							<code>post.php?post=<strong>12345</strong>&action=edit</code>
						</p>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<button type="submit" 
				        name="wta_force_regenerate" 
				        class="button button-primary button-hero"
				        onclick="return confirm('Regenerate AI content for this post? This will overwrite existing content.');">
					🚀 Regenerate Now
				</button>
			</p>
		</form>
	</div>
	
	<div class="card" style="max-width: 600px; margin-top: 20px;">
		<h3>📋 Quick Links</h3>
		<p>Recent locations you might want to regenerate:</p>
		
		<?php
		$recent_posts = get_posts( array(
			'post_type'      => WTA_POST_TYPE,
			'posts_per_page' => 10,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		
		if ( ! empty( $recent_posts ) ) {
			echo '<ul>';
			foreach ( $recent_posts as $post ) {
				$type = get_post_meta( $post->ID, 'wta_type', true );
				$ai_status = get_post_meta( $post->ID, 'wta_ai_status', true );
				
				echo '<li>';
				echo '<strong>' . esc_html( $post->post_title ) . '</strong> ';
				echo '(ID: <code>' . $post->ID . '</code>, ';
				echo 'Type: ' . esc_html( $type ) . ', ';
				echo 'AI: ' . ( $ai_status === 'done' ? '✅' : '⏳' ) . ')';
				echo ' <a href="' . get_edit_post_link( $post->ID ) . '">Edit</a>';
				echo '</li>';
			}
			echo '</ul>';
		}
		?>
	</div>
</div>

