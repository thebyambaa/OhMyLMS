<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Stores learners' recorded audio and video responses as files, not in the database.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * An audio or video response is uploaded to the uploads folder and only its URL is kept in the answer.
 *
 * Files get an unguessable name inside uploads/ohmylms-responses/, so a recording is not
 * listed in the media library and cannot be found without its link.
 */
final class ResponseMedia {
	const MAX_AUDIO  = 8388608;
	const MAX_VIDEO  = 67108864;
	const PER_HOUR   = 30;
	const SUBFOLDER  = 'ohmylms-responses';
	const EXTENSIONS = array(
		'audio' => array( 'webm', 'ogg', 'oga', 'mp3', 'm4a', 'mp4', 'wav' ),
		'video' => array( 'webm', 'ogg', 'ogv', 'mp4' ),
	);

	/** Register the upload route. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/** Route: POST /ohmylms/v1/response-media (multipart: file, kind). */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/response-media',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'upload' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * Script data for the response widgets.
	 *
	 * @return array
	 */
	public static function script_data() {
		return array(
			'url'        => is_user_logged_in() ? esc_url_raw( rest_url( 'ohmylms/v1/response-media' ) ) : '',
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'audioBytes' => self::limit( 'audio' ),
			'videoBytes' => self::limit( 'video' ),
		);
	}

	/**
	 * Largest accepted file: the type's own cap, never above what the server allows.
	 *
	 * @param string $kind audio or video.
	 * @return int Bytes.
	 */
	public static function limit( $kind ) {
		$cap = 'video' === $kind ? self::MAX_VIDEO : self::MAX_AUDIO;
		return min( $cap, (int) wp_max_upload_size() );
	}

	/**
	 * Does the file start like the audio or video container its extension claims?
	 *
	 * @param string $head      First bytes of the file.
	 * @param string $extension Lower-case extension.
	 * @return bool
	 */
	public static function looks_like( $head, $extension ) {
		switch ( $extension ) {
			case 'webm':
				return 0 === strpos( $head, "\x1A\x45\xDF\xA3" );
			case 'ogg':
			case 'oga':
			case 'ogv':
				return 0 === strpos( $head, 'OggS' );
			case 'wav':
				return 0 === strpos( $head, 'RIFF' ) && 'WAVE' === substr( $head, 8, 4 );
			case 'mp3':
				return 0 === strpos( $head, 'ID3' ) || ( 2 <= strlen( $head ) && "\xFF" === $head[0] && 0xE0 === ( ord( $head[1] ) & 0xE0 ) );
			case 'm4a':
			case 'mp4':
				return 'ftyp' === substr( $head, 4, 4 );
		}
		return false;
	}

	/**
	 * Save an uploaded recording.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function upload( $request ) {
		$kind = 'video' === $request->get_param( 'kind' ) ? 'video' : 'audio';
		$file = $request->get_file_params()['file'] ?? null;
		if ( ! is_array( $file ) || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'ohmylms_media_missing', __( 'No recording was received.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( self::limit( $kind ) < (int) $file['size'] || 0 === (int) $file['size'] ) {
			return new \WP_Error( 'ohmylms_media_size', __( 'This recording is too large.', 'ohmylms' ), array( 'status' => 413 ) );
		}
		$extension = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		$handle    = fopen( $file['tmp_name'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads the first bytes of an uploaded temp file.
		$head      = $handle ? (string) fread( $handle, 16 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( $handle ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		if ( ! in_array( $extension, self::EXTENSIONS[ $kind ], true ) || ! self::looks_like( $head, $extension ) ) {
			return new \WP_Error( 'ohmylms_media_type', __( 'This file type is not supported.', 'ohmylms' ), array( 'status' => 415 ) );
		}
		$key   = 'ohmylms_media_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( self::PER_HOUR <= $count ) {
			return new \WP_Error( 'ohmylms_media_rate', __( 'Too many recordings. Please try again later.', 'ohmylms' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$to_folder = static function ( $dirs ) {
			$dirs['subdir'] = '/' . self::SUBFOLDER . $dirs['subdir'];
			$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
			$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
			return $dirs;
		};
		$mimes     = array();
		foreach ( self::EXTENSIONS[ $kind ] as $allowed ) {
			$mimes[ $allowed ] = $kind . '/' . ( 'mp3' === $allowed ? 'mpeg' : $allowed );
		}
		$name = bin2hex( random_bytes( 16 ) ) . '.' . $extension;
		add_filter( 'upload_dir', $to_folder );
		$stored = wp_handle_upload(
			array_merge( $file, array( 'name' => $name ) ),
			array(
				'test_form' => false,
				'test_type' => false,
				'mimes'     => $mimes,
			)
		);
		remove_filter( 'upload_dir', $to_folder );
		if ( ! empty( $stored['error'] ) || empty( $stored['url'] ) ) {
			return new \WP_Error( 'ohmylms_media_store', __( 'The recording could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'url' => $stored['url'] ) );
	}
}
