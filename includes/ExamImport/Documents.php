<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Private source PDFs. Documents never become public media attachments.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport;

defined( 'ABSPATH' ) || exit;

/** Only opaque, server-generated document IDs can be resolved. */
final class Documents {

	const MAX_BYTES = 10485760;

	/**
	 * Resolve the explicitly configured, existing directory outside served roots.
	 *
	 * @internal Shared by private import derivatives; never expose through REST.
	 * @return string|\WP_Error Canonical private directory.
	 */
	public static function root() {
		$root = defined( 'OHMYLMS_IMPORT_PRIVATE_DIR' ) ? realpath( OHMYLMS_IMPORT_PRIVATE_DIR ) : false;
		if ( ! $root || ! is_dir( $root ) || ! is_writable( $root ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Check the explicitly configured local storage before exclusive creation.
			return new \WP_Error( 'exam_storage', __( 'Configure a writable private import directory outside the web root.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		$roots = array( ABSPATH, WP_CONTENT_DIR, isset( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '' );
		foreach ( $roots as $public ) {
			$public = $public ? realpath( $public ) : false;
			if ( $public && 0 === stripos( wp_normalize_path( $root ) . '/', trailingslashit( wp_normalize_path( $public ) ) ) ) {
				return new \WP_Error( 'exam_storage', __( 'Import storage must be outside all served directories.', 'ohmylms' ), array( 'status' => 503 ) );
			}
		}
		return $root;
	}

	/**
	 * Validate and store an actual multipart HTTP PDF upload.
	 *
	 * @param array $file PHP upload record.
	 * @return array|\WP_Error Private metadata.
	 */
	public static function upload( array $file ) {
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			return new \WP_Error( 'exam_forbidden', __( 'You cannot upload exam sources.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		if ( UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) || ! is_string( $file['name'] ?? null ) ) {
			return new \WP_Error( 'exam_upload', __( 'Choose a PDF within the upload limit.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$size = filesize( $file['tmp_name'] );
		$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'pdf' => 'application/pdf' ) );
		if ( ! $size || $size > min( self::MAX_BYTES, wp_max_upload_size() ) || 'application/pdf' !== $type['type'] ) {
			return new \WP_Error( 'exam_upload', __( 'Choose a PDF within the upload limit.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$bytes = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Verified local upload, never a URL.
		if ( ! is_string( $bytes ) || 0 !== strpos( $bytes, '%PDF-' ) ) {
			return new \WP_Error( 'exam_pdf', __( 'The source is not a PDF document.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$root = self::root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$id   = wp_generate_uuid4();
		$path = $root . DIRECTORY_SEPARATOR . $id . '.pdf';
		// Exclusive creation prevents overwriting an existing file or following a link.
		$handle = fopen( $path, 'x+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive local creation is required.
		if ( false === $handle ) {
			return new \WP_Error( 'exam_storage', __( 'Could not store the source PDF.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		chmod( $path, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Private local source permissions.
		$written = fwrite( $handle, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Exclusive local file handle.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close exclusive local handle.
		if ( strlen( $bytes ) !== $written ) {
			wp_delete_file( $path );
			return new \WP_Error( 'exam_storage', __( 'Could not store the complete source PDF.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		return array(
			'id'     => $id,
			'name'   => mb_substr( sanitize_file_name( $file['name'] ), 0, 180 ),
			'bytes'  => $written,
			'sha256' => hash( 'sha256', $bytes ),
		);
	}

	/**
	 * Resolve a source by ID; reject symlinks and files outside private storage.
	 *
	 * @param string $id Opaque source ID.
	 * @return string|\WP_Error Canonical local path, never returned through REST.
	 */
	public static function path( $id ) {
		$root = self::root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		if ( ! is_string( $id ) || ! wp_is_uuid( $id ) ) {
			return new \WP_Error( 'exam_document', __( 'Source document unavailable.', 'ohmylms' ) );
		}
		$path = $root . DIRECTORY_SEPARATOR . $id . '.pdf';
		if ( is_link( $path ) || ! is_file( $path ) || realpath( dirname( $path ) ) !== $root || ! is_readable( $path ) ) {
			return new \WP_Error( 'exam_document', __( 'Source document unavailable.', 'ohmylms' ) );
		}
		return $path;
	}

	/**
	 * Remove one stored document after job creation fails or its job is deleted.
	 *
	 * @param string $id Server-generated document ID.
	 */
	public static function remove( $id ) {
		$path = self::path( $id );
		if ( ! is_wp_error( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
