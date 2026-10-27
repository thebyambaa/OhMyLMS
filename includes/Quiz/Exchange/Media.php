<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Copies the media a quiz file points at into this site's media library.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz\Exchange;

defined( 'ABSPATH' ) || exit;

/**
 * One import's media work.
 *
 * Question images, videos and option images are attachment IDs in the database, so the file's
 * addresses are copied into the library. Addresses inside question text and settings that point
 * at the source site's uploads folder are copied and rewritten too, so the quiz does not keep
 * depending on the old site. Anything that cannot be copied is reported, never silently dropped.
 */
final class Media {

	const MAX_COPIES = 200;

	/**
	 * Uploads address of the site that made the file.
	 *
	 * @var string
	 */
	private $source_base;

	/**
	 * Whether files may be downloaded at all.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Address => attachment ID (0 when it could not be copied).
	 *
	 * @var int[]
	 */
	private $cache = array();

	/**
	 * Attachments this import created, so a failed import can remove them.
	 *
	 * @var int[]
	 */
	private $created = array();

	/**
	 * Warnings: question number and message.
	 *
	 * @var array[]
	 */
	private $warnings = array();

	/**
	 * Constructor.
	 *
	 * @param string $source_base Uploads address of the source site ('' when unknown).
	 * @param bool   $enabled     Copy files into the library.
	 */
	public function __construct( $source_base, $enabled = true ) {
		$this->source_base = (string) $source_base;
		$this->enabled     = (bool) $enabled;
	}

	/**
	 * Attachment ID for a media address, copying the file when this site does not have it.
	 *
	 * @param string $url    Address.
	 * @param int    $number Question number for messages (0 for the quiz).
	 * @param string $label What the file is, for messages.
	 * @return int Attachment ID, or 0 when it could not be provided.
	 */
	public function attachment( $url, $number, $label ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return 0;
		}
		if ( isset( $this->cache[ $url ] ) ) {
			return $this->cache[ $url ];
		}
		$id = (int) attachment_url_to_postid( $url );
		if ( $id ) {
			return $this->remember( $url, $id );
		}
		if ( ! $this->enabled ) {
			return $this->fail( $url, $number, $label, __( 'copying media was switched off', 'ohmylms' ) );
		}
		if ( count( $this->created ) >= self::MAX_COPIES ) {
			/* translators: %d: copy limit */
			return $this->fail( $url, $number, $label, sprintf( __( 'an import copies at most %d files', 'ohmylms' ), self::MAX_COPIES ) );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return $this->fail( $url, $number, $label, __( 'you cannot upload files', 'ohmylms' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$temporary = download_url( $url, 30 );
		if ( is_wp_error( $temporary ) ) {
			return $this->fail( $url, $number, $label, $temporary->get_error_message() );
		}
		$name = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		$id   = media_handle_sideload(
			array(
				'name'     => '' !== $name ? $name : 'media',
				'tmp_name' => $temporary,
			),
			0
		);
		if ( is_wp_error( $id ) ) {
			if ( file_exists( $temporary ) ) {
				wp_delete_file( $temporary );
			}
			return $this->fail( $url, $number, $label, $id->get_error_message() );
		}
		$this->created[] = (int) $id;
		return $this->remember( $url, (int) $id );
	}

	/**
	 * Copy and rewrite source-site upload addresses inside text, recursively through arrays.
	 *
	 * @param mixed $value  Text or a structure of text.
	 * @param int   $number Question number for messages.
	 * @return mixed Same shape, with local addresses.
	 */
	public function rewrite( $value, $number ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->rewrite( $item, $number );
			}
			return $value;
		}
		if ( ! $this->enabled || ! is_string( $value ) || '' === $this->source_base || false === strpos( $value, $this->source_base . '/' ) || $this->is_local( $this->source_base ) ) {
			return $value;
		}
		return preg_replace_callback(
			'#' . preg_quote( $this->source_base, '#' ) . '/[^\s"\'<>()\[\]\\\\]+#',
			function ( $found ) use ( $number ) {
				$id = $this->attachment( $found[0], $number, __( 'a file used in the question', 'ohmylms' ) );
				return $id ? (string) wp_get_attachment_url( $id ) : $found[0];
			},
			$value
		);
	}

	/**
	 * Remove the attachments this import created (after a failed import).
	 */
	public function discard() {
		foreach ( $this->created as $id ) {
			wp_delete_attachment( $id, true );
		}
		$this->created = array();
	}

	/**
	 * How many files were copied into the library.
	 *
	 * @return int
	 */
	public function copied() {
		return count( $this->created );
	}

	/**
	 * Warnings collected so far.
	 *
	 * @return array[]
	 */
	public function warnings() {
		return $this->warnings;
	}

	/**
	 * Is this uploads address this site's own?
	 *
	 * @param string $base Address.
	 * @return bool
	 */
	private function is_local( $base ) {
		$uploads = wp_get_upload_dir();
		return rtrim( (string) ( $uploads['baseurl'] ?? '' ), '/' ) === rtrim( $base, '/' );
	}

	/**
	 * Cache and return an ID.
	 *
	 * @param string $url Address.
	 * @param int    $id  Attachment ID.
	 * @return int
	 */
	private function remember( $url, $id ) {
		$this->cache[ $url ] = $id;
		return $id;
	}

	/**
	 * Record why a file could not be provided.
	 *
	 * @param string $url    Address.
	 * @param int    $number Question number.
	 * @param string $label  What the file is.
	 * @param string $reason Reason.
	 * @return int Always 0.
	 */
	private function fail( $url, $number, $label, $reason ) {
		$this->cache[ $url ] = 0;
		$this->warnings[]    = Document::problem(
			$number,
			/* translators: 1: what the file is, 2: address, 3: reason */
			sprintf( __( 'Could not copy %1$s (%2$s): %3$s', 'ohmylms' ), $label, $url, $reason )
		);
		return 0;
	}
}
