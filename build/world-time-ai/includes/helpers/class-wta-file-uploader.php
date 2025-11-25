<?php
/**
 * File uploader for JSON data files.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/helpers
 */

/**
 * File uploader class.
 *
 * @since 1.0.0
 */
class WTA_File_Uploader {

	/**
	 * Target directory for uploads.
	 *
	 * @var string
	 */
	private static $upload_dir = null;

	/**
	 * Get upload directory path.
	 *
	 * @return string
	 */
	private static function get_upload_dir() {
		if ( self::$upload_dir === null ) {
			self::$upload_dir = WP_CONTENT_DIR . '/plugins/world-time-ai/json';
		}
		return self::$upload_dir;
	}

	/**
	 * Ensure upload directory exists.
	 *
	 * @return bool|WP_Error
	 */
	private static function ensure_upload_dir() {
		$dir = self::get_upload_dir();
		
		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error( 'mkdir_failed', __( 'Could not create upload directory.', WTA_TEXT_DOMAIN ) );
			}
		}

		// Ensure directory is writable
		if ( ! is_writable( $dir ) ) {
			return new WP_Error( 'not_writable', __( 'Upload directory is not writable.', WTA_TEXT_DOMAIN ) );
		}

		return true;
	}

	/**
	 * Handle simple file upload (for smaller files).
	 */
	public static function handle_simple_upload() {
		// Verify nonce
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', WTA_TEXT_DOMAIN ) ) );
		}

		// Ensure directory exists
		$dir_check = self::ensure_upload_dir();
		if ( is_wp_error( $dir_check ) ) {
			wp_send_json_error( array( 'message' => $dir_check->get_error_message() ) );
		}

		// Get file type
		$file_type = isset( $_POST['file_type'] ) ? sanitize_text_field( $_POST['file_type'] ) : '';
		if ( ! in_array( $file_type, array( 'countries', 'states', 'cities' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type.', WTA_TEXT_DOMAIN ) ) );
		}

		// Check if file was uploaded
		if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => __( 'File upload failed.', WTA_TEXT_DOMAIN ) ) );
		}

		$file = $_FILES['file'];

		// Validate file type
		if ( ! self::validate_json_file( $file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON file.', WTA_TEXT_DOMAIN ) ) );
		}

		// Move file to destination
		$target_file = self::get_upload_dir() . '/' . $file_type . '.json';
		if ( ! move_uploaded_file( $file['tmp_name'], $target_file ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save file.', WTA_TEXT_DOMAIN ) ) );
		}

		WTA_Logger::info( "File uploaded successfully: {$file_type}.json", array(
			'size' => filesize( $target_file ),
		) );

		wp_send_json_success( array(
			'message' => sprintf( __( '%s.json uploaded successfully!', WTA_TEXT_DOMAIN ), ucfirst( $file_type ) ),
			'size' => size_format( filesize( $target_file ) ),
		) );
	}

	/**
	 * Handle chunked file upload (for large files).
	 */
	public static function handle_chunked_upload() {
		// Verify nonce
		check_ajax_referer( 'wta-admin-nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', WTA_TEXT_DOMAIN ) ) );
		}

		// Ensure directory exists
		$dir_check = self::ensure_upload_dir();
		if ( is_wp_error( $dir_check ) ) {
			wp_send_json_error( array( 'message' => $dir_check->get_error_message() ) );
		}

		// Get parameters
		$file_type = isset( $_POST['file_type'] ) ? sanitize_text_field( $_POST['file_type'] ) : '';
		$chunk_index = isset( $_POST['chunk_index'] ) ? intval( $_POST['chunk_index'] ) : 0;
		$total_chunks = isset( $_POST['total_chunks'] ) ? intval( $_POST['total_chunks'] ) : 0;
		$upload_id = isset( $_POST['upload_id'] ) ? sanitize_text_field( $_POST['upload_id'] ) : '';
		$file_name = isset( $_POST['file_name'] ) ? sanitize_file_name( $_POST['file_name'] ) : '';

		// Validate
		if ( ! in_array( $file_type, array( 'countries', 'states', 'cities' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type.', WTA_TEXT_DOMAIN ) ) );
		}

		if ( empty( $upload_id ) || $total_chunks < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload parameters.', WTA_TEXT_DOMAIN ) ) );
		}

		// Check if chunk was uploaded
		if ( ! isset( $_FILES['chunk'] ) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => __( 'Chunk upload failed.', WTA_TEXT_DOMAIN ) ) );
		}

		$chunk = $_FILES['chunk'];

		// Create temp directory for chunks
		$temp_dir = self::get_upload_dir() . '/temp';
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		// Save chunk
		$chunk_file = $temp_dir . '/' . $upload_id . '_' . $chunk_index;
		if ( ! move_uploaded_file( $chunk['tmp_name'], $chunk_file ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save chunk.', WTA_TEXT_DOMAIN ) ) );
		}

		// If this is the last chunk, combine all chunks
		if ( $chunk_index === $total_chunks - 1 ) {
			$target_file = self::get_upload_dir() . '/' . $file_type . '.json';
			$target_handle = fopen( $target_file, 'wb' );

			if ( ! $target_handle ) {
				wp_send_json_error( array( 'message' => __( 'Failed to create target file.', WTA_TEXT_DOMAIN ) ) );
			}

			// Combine all chunks
			for ( $i = 0; $i < $total_chunks; $i++ ) {
				$chunk_path = $temp_dir . '/' . $upload_id . '_' . $i;
				
				if ( ! file_exists( $chunk_path ) ) {
					fclose( $target_handle );
					wp_send_json_error( array( 'message' => sprintf( __( 'Chunk %d is missing.', WTA_TEXT_DOMAIN ), $i ) ) );
				}

				$chunk_data = file_get_contents( $chunk_path );
				fwrite( $target_handle, $chunk_data );
				unlink( $chunk_path ); // Delete chunk after combining
			}

			fclose( $target_handle );

			// Validate combined file
			if ( ! self::validate_json_file( $target_file ) ) {
				unlink( $target_file );
				wp_send_json_error( array( 'message' => __( 'Combined file is not valid JSON.', WTA_TEXT_DOMAIN ) ) );
			}

			WTA_Logger::info( "Chunked upload completed: {$file_type}.json", array(
				'size' => filesize( $target_file ),
				'chunks' => $total_chunks,
			) );

			wp_send_json_success( array(
				'message' => sprintf( __( '%s.json uploaded successfully!', WTA_TEXT_DOMAIN ), ucfirst( $file_type ) ),
				'size' => size_format( filesize( $target_file ) ),
			) );
		} else {
			// More chunks to come
			wp_send_json_success( array(
				'message' => sprintf( __( 'Chunk %d of %d uploaded.', WTA_TEXT_DOMAIN ), $chunk_index + 1, $total_chunks ),
			) );
		}
	}

	/**
	 * Validate that file is valid JSON.
	 *
	 * @param string $file_path Path to file.
	 * @return bool
	 */
	private static function validate_json_file( $file_path ) {
		// Read first 1KB to validate JSON structure
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return false;
		}

		$sample = fread( $handle, 1024 );
		fclose( $handle );

		// Check if it looks like JSON
		$sample = trim( $sample );
		if ( empty( $sample ) || ( $sample[0] !== '[' && $sample[0] !== '{' ) ) {
			return false;
		}

		return true;
	}
}

