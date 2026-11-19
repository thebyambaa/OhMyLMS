<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping.
/**
 * Private file storage for membership downloads.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Downloads;

defined( 'ABSPATH' ) || exit;

/** Keep protected files outside WordPress and the web server document root. */
final class PrivateFiles {

	/** Supported extensions and their download content types. */
	const MIME_TYPES = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'  => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'ppt'  => 'application/vnd.ms-powerpoint',
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'odt'  => 'application/vnd.oasis.opendocument.text',
		'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
		'odp'  => 'application/vnd.oasis.opendocument.presentation',
		'rtf'  => 'application/rtf',
		'txt'  => 'text/plain',
		'csv'  => 'text/csv',
		'zip'  => 'application/zip',
	);

	/**
	 * Resolve the configured storage directory or use a dedicated folder under private import storage.
	 *
	 * @param bool $create Whether to create the dedicated fallback folder.
	 * @return string|null Absolute directory, if available safely.
	 */
	public static function directory( bool $create = false ): ?string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only used for a filesystem boundary check with realpath().
		$web_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : false;
		$wp_root  = realpath( ABSPATH );
		if ( ! $web_root || ! $wp_root ) {
			return null;
		}

		$roots = array( $web_root, $wp_root );
		if ( defined( 'OHMYLMS_PRIVATE_DOWNLOAD_DIR' ) ) {
			return self::resolve_directory( OHMYLMS_PRIVATE_DOWNLOAD_DIR, $roots );
		}

		$parent = defined( 'OHMYLMS_IMPORT_PRIVATE_DIR' ) ? self::resolve_directory( OHMYLMS_IMPORT_PRIVATE_DIR, $roots ) : null;
		if ( ! $parent ) {
			return null;
		}

		$folder = $parent . 'protected-downloads';
		if ( $create && ! is_link( $folder ) && ! is_dir( $folder ) ) {
			wp_mkdir_p( $folder );
		}

		$directory = self::resolve_directory( $folder, $roots );
		return trailingslashit( $folder ) === $directory ? $directory : null;
	}

	/**
	 * Resolve a directory that is disjoint from all served roots.
	 *
	 * @param string   $path  Candidate filesystem path.
	 * @param string[] $roots Canonical WordPress and web server roots.
	 * @return string|null Canonical directory with a trailing slash.
	 */
	private static function resolve_directory( string $path, array $roots ): ?string {
		$resolved = realpath( $path );
		if ( ! $resolved || ! is_dir( $resolved ) ) {
			return null;
		}

		$directory = trailingslashit( wp_normalize_path( $resolved ) );
		foreach ( $roots as $root ) {
			$root = trailingslashit( wp_normalize_path( $root ) );
			if ( 0 === strncasecmp( $directory, $root, strlen( $root ) ) || 0 === strncasecmp( $root, $directory, strlen( $directory ) ) ) {
				return null;
			}
		}

		return $directory;
	}

	/**
	 * Store a validated file uploaded by an administrator.
	 *
	 * @param array $upload PHP upload entry.
	 * @return array|\WP_Error Private file key and original filename, or a validation error.
	 */
	public static function store( array $upload ) {
		$directory = self::directory( true );
		if ( ! $directory ) {
			return new \WP_Error( 'private_directory', __( 'Configure a writable private downloads folder outside the web root first.', 'ohmylms' ) );
		}

		$tmp  = $upload['tmp_name'] ?? '';
		$name = isset( $upload['name'] ) && is_string( $upload['name'] ) ? sanitize_file_name( $upload['name'] ) : '';
		if ( UPLOAD_ERR_OK !== ( $upload['error'] ?? null ) || ! is_string( $tmp ) || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'upload_failed', __( 'The upload failed. Check the server upload limit and try again.', 'ohmylms' ) );
		}

		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$file_type = wp_check_filetype_and_ext( $tmp, $name, self::MIME_TYPES );
		if ( ! isset( self::MIME_TYPES[ $extension ] ) || 0 !== strcasecmp( $extension, (string) $file_type['ext'] ) || self::MIME_TYPES[ $extension ] !== $file_type['type'] ) {
			return new \WP_Error( 'invalid_file', __( 'Choose a supported document or ZIP file whose contents match its extension.', 'ohmylms' ) );
		}

		if ( in_array( $extension, array( 'pdf', 'zip' ), true ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the header of a verified PHP upload.
			$signature = file_get_contents( $tmp, false, null, 0, 5 );
			if ( 'pdf' === $extension && '%PDF-' !== $signature ) {
				return new \WP_Error( 'invalid_pdf', __( 'Choose a valid PDF file.', 'ohmylms' ) );
			}
			if ( 'zip' === $extension && ( false === $signature || ! in_array( substr( $signature, 0, 4 ), array( "PK\x03\x04", "PK\x05\x06" ), true ) ) ) {
				return new \WP_Error( 'invalid_zip', __( 'Choose a valid ZIP file.', 'ohmylms' ) );
			}
		}

		$key = wp_generate_uuid4() . '.' . $extension;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- PHP verifies the provenance of the file while moving it outside the web root.
		if ( ! move_uploaded_file( $tmp, $directory . $key ) ) {
			return new \WP_Error( 'move_failed', __( 'WordPress could not save the file to the private folder.', 'ohmylms' ) );
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
		if ( ! $directory || ! preg_match( '/^[a-f0-9-]{36}\.([a-z0-9]+)$/D', $key, $matches ) || ! isset( self::MIME_TYPES[ $matches[1] ] ) ) {
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
