<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping.
/**
 * Private PDF storage for membership downloads.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Downloads;

defined( 'ABSPATH' ) || exit;

/** Keep protected files outside WordPress and the web server document root. */
final class PrivateFiles {

	/**
	 * Resolve the configured storage directory.
	 *
	 * @return string|null Absolute directory, if configured safely.
	 */
	public static function directory(): ?string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only used for a filesystem boundary check with realpath().
		$web_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : false;
		$wp_root  = realpath( ABSPATH );
		$path     = defined( 'OHMYLMS_PRIVATE_DOWNLOAD_DIR' ) ? realpath( OHMYLMS_PRIVATE_DOWNLOAD_DIR ) : false;
		if ( ! $web_root || ! $wp_root || ! $path || ! is_dir( $path ) ) {
			return null;
		}

		$directory = trailingslashit( wp_normalize_path( $path ) );
		foreach ( array( $web_root, $wp_root ) as $root ) {
			$root = trailingslashit( wp_normalize_path( $root ) );
			if ( 0 === strncasecmp( $directory, $root, strlen( $root ) ) || 0 === strncasecmp( $root, $directory, strlen( $directory ) ) ) {
				return null;
			}
		}

		return $directory;
	}

	/**
	 * Store a validated PDF uploaded by an administrator.
	 *
	 * @param array $upload PHP upload entry.
	 * @return array|\WP_Error Private file key and original filename, or a validation error.
	 */
	public static function store( array $upload ) {
		$directory = self::directory();
		if ( ! $directory || ! is_writable( $directory ) ) {
			return new \WP_Error( 'private_directory', __( 'Configure a writable private downloads folder outside the web root first.', 'ohmylms' ) );
		}

		$tmp  = $upload['tmp_name'] ?? '';
		$name = isset( $upload['name'] ) && is_string( $upload['name'] ) ? sanitize_file_name( $upload['name'] ) : '';
		if ( UPLOAD_ERR_OK !== ( $upload['error'] ?? null ) || ! is_string( $tmp ) || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'upload_failed', __( 'The upload failed. Check the server upload limit and try again.', 'ohmylms' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read exactly five bytes from a verified PHP upload.
		$signature = file_get_contents( $tmp, false, null, 0, 5 );
		if ( 'pdf' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || '%PDF-' !== $signature ) {
			return new \WP_Error( 'invalid_pdf', __( 'Choose a valid PDF file.', 'ohmylms' ) );
		}

		$key = wp_generate_uuid4() . '.pdf';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- PHP verifies the provenance of the file while moving it outside the web root.
		if ( ! move_uploaded_file( $tmp, $directory . $key ) ) {
			return new \WP_Error( 'move_failed', __( 'WordPress could not save the PDF to the private folder.', 'ohmylms' ) );
		}

		return array(
			'key'  => $key,
			'name' => $name,
		);
	}

	/**
	 * Resolve a stored key to an existing local file.
	 *
	 * @param string $key Stored file key.
	 * @return string|null Absolute file path.
	 */
	public static function path( string $key ): ?string {
		$directory = self::directory();
		if ( ! $directory || ! preg_match( '/^[a-f0-9-]{36}\.pdf$/D', $key ) ) {
			return null;
		}

		$path = $directory . $key;
		return ! is_link( $path ) && is_file( $path ) ? $path : null;
	}

	/**
	 * Remove an unreferenced file.
	 *
	 * @param string $key Stored file key.
	 */
	public static function delete( string $key ): void {
		$path = self::path( $key );
		if ( $path ) {
			wp_delete_file( $path );
		}
	}
}
