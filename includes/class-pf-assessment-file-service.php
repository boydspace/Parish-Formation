<?php
/** Secure handling for learner assessment file submissions. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parish_Formation_Assessment_File_Service {
	public const PRIVATE_META = '_pf_private_assessment_file';
	public const PATH_META = '_pf_private_assessment_file_path';
	public const OWNER_META = '_pf_assessment_file_owner';
	public const QUESTION_META = '_pf_assessment_file_question';
	public const ENROLLMENT_META = '_pf_assessment_file_enrollment';

	public static function register_rest_route() {
		register_rest_route( 'parish-formation/v1', '/assessment-files', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( self::class, 'upload_rest' ),
			'permission_callback' => static function () { return is_user_logged_in(); },
		) );
	}

	public static function upload_rest( WP_REST_Request $request ) {
		$enrollment_id = absint( $request->get_param( 'enrollment_id' ) );
		$course_id = absint( $request->get_param( 'course_id' ) );
		$assessment_id = absint( $request->get_param( 'assessment_id' ) );
		$question_id = absint( $request->get_param( 'question_id' ) );
		$enrollment = Parish_Formation_Assessment_Actions::validate_submission( $enrollment_id, $course_id, $assessment_id );
		if ( is_wp_error( $enrollment ) ) { return $enrollment; }
		if ( $assessment_id !== absint( get_post_meta( $question_id, '_pf_assessment_id', true ) ) || 'file_upload' !== Parish_Formation_Question_Config::get( $question_id )['type'] ) {
			return new WP_Error( 'invalid_question', __( 'This upload question is not part of the assessment.', 'parish-formation' ), array( 'status' => 400 ) );
		}
		$files = $request->get_file_params();
		$file = $files['file'] ?? null;
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new WP_Error( 'upload_failed', __( 'The selected file could not be uploaded.', 'parish-formation' ), array( 'status' => 400 ) );
		}
		$config = Parish_Formation_Question_Config::get( $question_id )['type_config'];
		if ( (int) $file['size'] > (int) $config['max_file_size'] ) { return new WP_Error( 'file_too_large', __( 'The selected file is too large.', 'parish-formation' ), array( 'status' => 400 ) ); }
		$mimes = self::allowed_mimes( $config );
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) { return new WP_Error( 'invalid_file_type', __( 'This file type is not permitted.', 'parish-formation' ), array( 'status' => 400 ) ); }

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$uploaded = wp_handle_sideload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
		if ( isset( $uploaded['error'] ) ) { return new WP_Error( 'upload_failed', sanitize_text_field( $uploaded['error'] ), array( 'status' => 400 ) ); }
		$private = self::private_directory();
		if ( is_wp_error( $private ) ) { wp_delete_file( $uploaded['file'] ); return $private; }
		$filename = wp_unique_filename( $private, sanitize_file_name( wp_basename( $uploaded['file'] ) ) );
		$destination = trailingslashit( $private ) . $filename;
		if ( ! @rename( $uploaded['file'], $destination ) ) { wp_delete_file( $uploaded['file'] ); return new WP_Error( 'private_storage_failed', __( 'The file could not be moved into protected storage.', 'parish-formation' ), array( 'status' => 500 ) ); }
		$attachment_id = wp_insert_attachment( array( 'post_title' => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ), 'post_status' => 'private', 'post_mime_type' => $checked['type'], 'guid' => '' ), $destination );
		if ( is_wp_error( $attachment_id ) ) { wp_delete_file( $destination ); return $attachment_id; }
		update_attached_file( $attachment_id, $destination );
		// WordPress assumes attachment paths live below the public uploads directory.
		// Keep the protected absolute path separately so Windows drive separators and
		// out-of-tree storage are not rewritten as an uploads-relative path.
		update_post_meta( $attachment_id, self::PATH_META, wp_normalize_path( $destination ) );
		update_post_meta( $attachment_id, self::PRIVATE_META, 1 );
		update_post_meta( $attachment_id, self::OWNER_META, get_current_user_id() );
		update_post_meta( $attachment_id, self::QUESTION_META, $question_id );
		update_post_meta( $attachment_id, self::ENROLLMENT_META, $enrollment_id );
		return rest_ensure_response( array( 'attachmentId' => $attachment_id, 'name' => sanitize_file_name( $file['name'] ) ) );
	}

	public static function download() {
		self::stream( false );
	}

	/** Display an authorized protected raster image inline. */
	public static function preview() {
		self::stream( true );
	}

	private static function stream( $inline ) {
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		$nonce_action = $inline ? 'pf_preview_assessment_file_' : 'pf_download_assessment_file_';
		check_admin_referer( $nonce_action . $attachment_id );
		$owner_id = absint( get_post_meta( $attachment_id, self::OWNER_META, true ) );
		if ( ! is_user_logged_in() || ( get_current_user_id() !== $owner_id && ! current_user_can( 'pf_grade_assessments' ) ) ) { wp_die( esc_html__( 'You cannot access this file.', 'parish-formation' ), '', array( 'response' => 403 ) ); }
		$file = self::resolve_file_path( $attachment_id );
		if ( ! get_post_meta( $attachment_id, self::PRIVATE_META, true ) || ! $file || ! is_file( $file ) ) { wp_die( esc_html__( 'The file is unavailable.', 'parish-formation' ), '', array( 'response' => 404 ) ); }
		if ( $inline && ! self::is_previewable_image( $attachment_id ) ) { wp_die( esc_html__( 'This file cannot be previewed safely.', 'parish-formation' ), '', array( 'response' => 415 ) ); }
		nocache_headers();
		header( 'Content-Type: ' . ( get_post_mime_type( $attachment_id ) ?: 'application/octet-stream' ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . rawurlencode( wp_basename( $file ) ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	public static function download_url( $attachment_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=pf_download_assessment_file&attachment_id=' . absint( $attachment_id ) ), 'pf_download_assessment_file_' . absint( $attachment_id ) );
	}

	public static function preview_url( $attachment_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=pf_preview_assessment_file&attachment_id=' . absint( $attachment_id ) ), 'pf_preview_assessment_file_' . absint( $attachment_id ) );
	}

	public static function is_previewable_image( $attachment_id ) {
		return in_array( (string) get_post_mime_type( absint( $attachment_id ) ), array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ), true );
	}

	/** Resolve and validate a protected attachment path, including legacy uploads. */
	public static function resolve_file_path( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! get_post_meta( $attachment_id, self::PRIVATE_META, true ) ) { return ''; }

		$candidates = array(
			(string) get_post_meta( $attachment_id, self::PATH_META, true ),
			(string) get_attached_file( $attachment_id ),
			(string) get_post_meta( $attachment_id, '_wp_attached_file', true ),
		);
		$private = self::private_directory();
		if ( ! is_wp_error( $private ) ) {
			$stored = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
			if ( '' !== $stored ) { $candidates[] = trailingslashit( $private ) . wp_basename( str_replace( '\\', '/', $stored ) ); }
		}

		foreach ( array_unique( array_filter( $candidates ) ) as $candidate ) {
			$resolved = realpath( $candidate );
			if ( false === $resolved || ! is_file( $resolved ) || ! self::is_protected_path( $resolved, $private ) ) { continue; }
			$resolved = wp_normalize_path( $resolved );
			if ( get_post_meta( $attachment_id, self::PATH_META, true ) !== $resolved ) {
				update_post_meta( $attachment_id, self::PATH_META, $resolved );
			}
			return $resolved;
		}
		return '';
	}

	private static function is_protected_path( $path, $private ) {
		if ( is_wp_error( $private ) ) { return false; }
		$root = realpath( $private );
		$file = realpath( $path );
		if ( false === $root || false === $file ) { return false; }
		$root = trailingslashit( wp_normalize_path( $root ) );
		$file = wp_normalize_path( $file );
		return 0 === strpos( $file, $root );
	}

	private static function allowed_mimes( $config ) {
		$extensions = array_flip( $config['allowed_extensions'] ?? array() );
		$mime_types = $config['allowed_mime_types'] ?? array();
		$safe = array();
		foreach ( get_allowed_mime_types() as $extensions_pattern => $mime ) {
			foreach ( explode( '|', $extensions_pattern ) as $extension ) {
				if ( isset( $extensions[ $extension ] ) && ( ! $mime_types || in_array( $mime, $mime_types, true ) ) ) { $safe[ $extension ] = $mime; }
			}
		}
		return $safe;
	}

	private static function private_directory() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) { return new WP_Error( 'upload_directory_error', $uploads['error'], array( 'status' => 500 ) ); }
		$outside_root = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'parish-formation-private-' . substr( wp_hash( home_url( '/' ) . AUTH_SALT ), 0, 12 );
		$directory = apply_filters( 'parish_formation_private_upload_directory', $outside_root );
		if ( ! wp_mkdir_p( $directory ) ) {
			$directory = trailingslashit( $uploads['basedir'] ) . 'parish-formation-private-' . substr( wp_hash( home_url( '/' ) . AUTH_SALT ), 0, 12 );
			if ( ! wp_mkdir_p( $directory ) ) { return new WP_Error( 'private_storage_failed', __( 'Protected upload storage is unavailable.', 'parish-formation' ), array( 'status' => 500 ) ); }
		}
		if ( ! file_exists( $directory . '/.htaccess' ) ) { file_put_contents( $directory . '/.htaccess', "Require all denied\nDeny from all\n" ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( ! file_exists( $directory . '/web.config' ) ) { file_put_contents( $directory . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( ! file_exists( $directory . '/index.php' ) ) { file_put_contents( $directory . '/index.php', "<?php\nhttp_response_code( 404 );\nexit;\n" ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $directory;
	}
}
