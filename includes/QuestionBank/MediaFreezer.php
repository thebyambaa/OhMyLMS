<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Frozen copies of question media.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

defined( 'ABSPATH' ) || exit;

/**
 * Frozen copies of question media.
 *
 * When a version is captured, every local upload it references (question image and video,
 * option and matching images, images embedded in the question text) is copied to
 * uploads/ohmylms-frozen/ under its SHA-256, so identical files are stored once. The version
 * keeps a map from each reference to its frozen copy, and snapshots serve those copies:
 * replacing or deleting a file in the media library later does not change what earlier
 * attempts showed. A replaced file is detected on the next capture (size, time, then
 * checksum) and produces a new version. Files outside uploads, missing files and files over
 * MAX_BYTES are not copied and keep their live URL.
 */
final class MediaFreezer {
	const DIR       = 'ohmylms-frozen';
	const MAX_BYTES = 26214400;
	const MAX_FILES = 40;

	/**
	 * Local files referenced by version content.
	 *
	 * @return array<string,array{url:string,path:string}> reference key => source
	 * @param array $content Content.
	 */
	public static function sources( array $content ) {
		$sources = array();
		$media   = (array) ( $content['media'] ?? array() );
		self::add( $sources, 'image', (string) ( $media['image_url'] ?? '' ), (int) ( $media['image_id'] ?? 0 ) );
		self::add( $sources, 'video', (string) ( $media['video_url'] ?? '' ), (int) ( $media['video_id'] ?? 0 ) );
		foreach ( array( 'image_url', 'video_url' ) as $setting ) {
			self::add( $sources, 'settings:' . $setting, (string) ( $content['settings'][ $setting ] ?? '' ), 0 );
		}
		foreach ( (array) ( $content['options'] ?? array() ) as $option ) {
			$id = (int) ( $option['id'] ?? 0 );
			self::add( $sources, 'option:' . $id, (string) ( $option['image_url'] ?? '' ), (int) ( $option['thumbnail_id'] ?? 0 ) );
			self::add( $sources, 'match:' . $id, (string) ( $option['matching_data']['image_url'] ?? '' ), 0 );
		}
		foreach ( (array) ( $content['settings']['parts'] ?? array() ) as $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}
			$child_settings = (array) ( $part['question']['settings'] ?? array() );
			// Structured children are leaves; do not recurse into unvalidated author input.
			unset( $child_settings['parts'] );
			$child = array(
				'body'     => (string) ( $part['prompt'] ?? '' ),
				'settings' => $child_settings,
				'options'  => (array) ( $part['question']['questions'] ?? array() ),
			);
			foreach ( self::sources( $child ) as $key => $source ) {
				$sources[ 'part:' . ( $part['id'] ?? '' ) . ':' . $key ] = $source;
			}
		}
		if ( preg_match_all( '/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', (string) ( $content['body'] ?? '' ), $matches ) ) {
			foreach ( array_unique( $matches[1] ) as $url ) {
				self::add( $sources, 'body:' . md5( html_entity_decode( $url ) ), html_entity_decode( $url ), 0 ); }
		}
		return array_slice( $sources, 0, self::MAX_FILES, true );
	}

	/**
	 * Add for Media freezer.
	 *
	 * @param array $sources Sources.
	 * @param mixed $key Lookup key.
	 * @param mixed $url Url.
	 * @param mixed $attachment_id Attachment identifier.
	 */
	private static function add( array &$sources, $key, $url, $attachment_id ) {
		// Prefer the file actually shown (e.g. the "large" size), else the attachment's original.
		$path = self::path_for_url( $url );
		if ( ! $path && $attachment_id ) {
			$path = self::inside_uploads( (string) get_attached_file( $attachment_id ) ); }
		if ( ! $path ) {
			return; }
		$sources[ $key ] = array(
			'url'  => '' !== $url ? $url : (string) wp_get_attachment_url( $attachment_id ),
			'path' => $path,
		);
	}

	/** Uploads path for a URL under the uploads base URL, or null.
	 *
	 * @param mixed $url Url.
	 */
	public static function path_for_url( $url ) {
		$url = strtok( (string) $url, '?#' );
		if ( '' === $url || false === $url ) {
			return null; }
		$uploads = wp_upload_dir( null, false );
		$base    = set_url_scheme( $uploads['baseurl'], 'http' );
		$url     = set_url_scheme( $url, 'http' );
		if ( strpos( $url, trailingslashit( $base ) ) !== 0 ) {
			return null; }
		$relative = rawurldecode( substr( $url, strlen( trailingslashit( $base ) ) ) );
		if ( strpos( $relative, self::DIR . '/' ) === 0 ) {
			return null; }
		return self::inside_uploads( trailingslashit( $uploads['basedir'] ) . $relative );
	}

	/**
	 * Inside uploads for Media freezer.
	 *
	 * @param mixed $path Path.
	 */
	private static function inside_uploads( $path ) {
		$real = realpath( (string) $path );
		$root = realpath( wp_upload_dir( null, false )['basedir'] );
		if ( ! $real || ! $root || ! is_file( $real ) ) {
			return null; }
		$real = wp_normalize_path( $real );
		return strpos( $real, trailingslashit( wp_normalize_path( $root ) ) ) === 0 ? $real : null;
	}

	/**
	 * Copy each referenced file into frozen storage.
	 *
	 * @return array<string,array> reference key => frozen record (url, source, sha256, size, mtime)
	 * @param array $content Content.
	 */
	public static function freeze( array $content ) {
		$frozen  = array();
		$uploads = wp_upload_dir( null, false );
		foreach ( self::sources( $content ) as $key => $source ) {
			$size = (int) @filesize( $source['path'] );
			if ( $size <= 0 || $size > self::MAX_BYTES ) {
				continue; }
			$sha       = hash_file( 'sha256', $source['path'] );
			$extension = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) pathinfo( $source['path'], PATHINFO_EXTENSION ) ) );
			$relative  = self::DIR . '/' . substr( $sha, 0, 2 ) . '/' . $sha . ( '' !== $extension ? '.' . $extension : '' );
			$target    = trailingslashit( $uploads['basedir'] ) . $relative;
			if ( ! is_file( $target ) ) {
				if ( ! wp_mkdir_p( dirname( $target ) ) ) {
					continue; }
				self::protect_directory( trailingslashit( $uploads['basedir'] ) . self::DIR );
				$temporary = $target . '.' . wp_generate_password( 6, false, false ) . '.tmp';
				if ( ! @copy( $source['path'], $temporary ) || hash_file( 'sha256', $temporary ) !== $sha || ! @rename( $temporary, $target ) ) {
					@unlink( $temporary );
					continue;
				}
			}
			$frozen[ $key ] = array(
				'url'    => trailingslashit( $uploads['baseurl'] ) . $relative,
				'source' => $source['url'],
				'sha256' => $sha,
				'size'   => $size,
				'mtime'  => (int) @filemtime( $source['path'] ),
			);
		}
		return $frozen;
	}

	/** No directory listing for frozen storage.
	 *
	 * @param mixed $directory Directory.
	 */
	private static function protect_directory( $directory ) {
		if ( is_dir( $directory ) && ! is_file( $directory . '/index.php' ) ) {
			@file_put_contents( $directory . '/index.php', "<?php\n// Silence is golden.\n" ); }
	}

	/** Has a file the version froze been replaced in place since?
	 *
	 * @param array $frozen Frozen.
	 * @param array $content Content.
	 */
	public static function changed( array $frozen, array $content ) {
		foreach ( self::sources( $content ) as $key => $source ) {
			if ( ! isset( $frozen[ $key ] ) ) {
				continue; }
			$record = $frozen[ $key ];
			clearstatcache( true, $source['path'] );
			$size = (int) @filesize( $source['path'] );
			if ( $size === (int) $record['size'] && (int) @filemtime( $source['path'] ) === (int) $record['mtime'] ) {
				continue; }
			if ( $size > self::MAX_BYTES || hash_file( 'sha256', $source['path'] ) !== $record['sha256'] ) {
				return true; }
		}
		return false;
	}

	/** Replace frozen references in question text.
	 *
	 * @param mixed $body Body.
	 * @param array $frozen Frozen.
	 */
	public static function body( $body, array $frozen ) {
		foreach ( $frozen as $key => $record ) {
			if ( strpos( $key, 'body:' ) !== 0 ) {
				continue; }
			$body = str_replace( array( $record['source'], esc_attr( $record['source'] ) ), $record['url'], $body );
		}
		return $body;
	}
}
