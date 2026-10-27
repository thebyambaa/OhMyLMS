<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping.
/**
 * Private, administrator-owned chat uploads. No public media-library copy.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

defined( 'ABSPATH' ) || exit;

/** File IDs supplied by clients are opaque local handles, never provider IDs. */
final class AssistantAttachments {
	const MIMES = array(
		'pdf'        => 'application/pdf',
		'png'        => 'image/png',
		'jpg|jpeg'   => 'image/jpeg',
		'webp'       => 'image/webp',
		'txt|md|csv' => 'text/plain',
		'json'       => 'application/json',
		'docx'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xlsx'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'pptx'       => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	);

	/**
	 * Describe file limits to the composer.
	 *
	 * @return array Limits.
	 */
	public static function limits() {
		return array(
			'max_files'  => 3,
			'max_bytes'  => min( 5 * MB_IN_BYTES, wp_max_upload_size() ),
			'extensions' => explode( '|', implode( '|', array_keys( self::MIMES ) ) ),
		);
	}

	/**
	 * Validate an actual HTTP upload before reading or transmitting it.
	 *
	 * @param array $file PHP uploaded file.
	 * @return array|\WP_Error Validated file metadata.
	 */
	public static function validate( array $file ) {
		if ( UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) || ! is_string( $file['name'] ?? null ) ) {
			return new \WP_Error( 'attachment_upload', __( 'The upload failed. Check the file and your server upload limit.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$name = mb_strcut( sanitize_file_name( $file['name'] ), 0, 200, 'UTF-8' );
		$size = filesize( $file['tmp_name'] );
		$type = wp_check_filetype_and_ext( $file['tmp_name'], $name, self::MIMES );
		if ( ! $size || $size > self::limits()['max_bytes'] || ! $type['ext'] || ! $type['type'] ) {
			return new \WP_Error( 'attachment_type', __( 'Choose a supported file within the upload size limit.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return array(
			'name'  => $name,
			'bytes' => $size,
			'mime'  => $type['type'],
			'ext'   => $type['ext'],
		);
	}

	/**
	 * Upload directly from PHP's temporary file to the configured OpenAI account.
	 *
	 * @param \WP_REST_Request $request Multipart request.
	 * @return array|\WP_Error Public metadata.
	 */
	public static function upload( $request ) {
		if ( ! current_user_can( 'upload_files' ) || ! AssistantSettings::summary()['configured'] ) {
			return new \WP_Error( 'attachment_unavailable', __( 'Enable the OpenAI connection and sign in with upload access first.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$files = $request->get_file_params();
		$file  = $files['file'] ?? array();
		$meta  = is_array( $file ) ? self::validate( $file ) : new \WP_Error( 'attachment_upload', __( 'Choose one file per upload.', 'ohmylms' ), array( 'status' => 400 ) );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}
		// This is a verified PHP upload temporary path, never a URL or a client-selected server file.
		$bytes = file_get_contents( $file['tmp_name'] );
		if ( false === $bytes || ( 'pdf' === $meta['ext'] && 0 !== strpos( $bytes, '%PDF-' ) ) || ( in_array( $meta['ext'], array( 'txt', 'md', 'csv', 'json' ), true ) && ( false !== strpos( $bytes, "\0" ) || 1 !== preg_match( '//u', $bytes ) ) ) || ( in_array( $meta['ext'], array( 'docx', 'xlsx', 'pptx' ), true ) && 0 !== strpos( $bytes, "PK\x03\x04" ) ) || ( str_starts_with( $meta['mime'], 'image/' ) && wp_get_image_mime( $file['tmp_name'] ) !== $meta['mime'] ) ) {
			return new \WP_Error( 'attachment_content', __( 'The file content does not match a supported document.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$boundary = 'oml' . wp_generate_password( 32, false );
		$purpose  = str_starts_with( $meta['mime'], 'image/' ) ? 'vision' : 'user_data';
		$body     = '';
		foreach ( array(
			'purpose'                => $purpose,
			'expires_after[anchor]'  => 'created_at',
			'expires_after[seconds]' => '3600',
		) as $name => $value ) {
			$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
		}
		$filename = str_replace( array( '"', "\r", "\n", '\\' ), '_', $meta['name'] );
		$body    .= "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\nContent-Type: " . $meta['mime'] . "\r\n\r\n" . $bytes . "\r\n--$boundary--\r\n";
		$reply    = wp_remote_post(
			'https://api.openai.com/v1/files',
			array(
				'timeout'             => 45,
				'redirection'         => 0,
				'limit_response_size' => 16384,
				'headers'             => array(
					'Authorization' => 'Bearer ' . AssistantSettings::key(),
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'                => $body,
			)
		);
		$decoded  = is_wp_error( $reply ) ? null : json_decode( wp_remote_retrieve_body( $reply ), true );
		if ( is_wp_error( $reply ) || wp_remote_retrieve_response_code( $reply ) >= 400 || ! is_string( $decoded['id'] ?? null ) || ! preg_match( '/^file-[a-zA-Z0-9_-]+$/', $decoded['id'] ) ) {
			return new \WP_Error( 'attachment_provider', __( 'OpenAI could not accept this upload. Check the connection and try again.', 'ohmylms' ), array( 'status' => 502 ) );
		}
		$id              = wp_generate_uuid4();
		$meta['id']      = $id;
		$meta['file_id'] = $decoded['id'];
		$meta['expires'] = time() + HOUR_IN_SECONDS;
		set_transient( 'oml_file_' . get_current_user_id() . '_' . $id, $meta, HOUR_IN_SECONDS );
		return array(
			'id'    => $id,
			'name'  => $meta['name'],
			'bytes' => $meta['bytes'],
		);
	}

	/**
	 * Resolve only attachments uploaded by this administrator.
	 *
	 * @param mixed $ids Opaque local upload handles.
	 * @return array|\WP_Error Private file metadata.
	 */
	public static function resolve( $ids ) {
		if ( ! is_array( $ids ) || count( $ids ) > 3 || count( array_filter( $ids, 'is_string' ) ) !== count( $ids ) || count( array_unique( $ids ) ) !== count( $ids ) ) {
			return new \WP_Error( 'attachment_count', __( 'Attach up to three files per message.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$files = array();
		foreach ( $ids as $id ) {
			$file = wp_is_uuid( $id ) ? get_transient( 'oml_file_' . get_current_user_id() . '_' . $id ) : false;
			if ( ! $file || $file['expires'] <= time() ) {
				return new \WP_Error( 'attachment_expired', __( 'An attachment expired or is unavailable. Remove it and attach the file again.', 'ohmylms' ), array( 'status' => 410 ) );
			}
			$files[] = $file;
		}
		return $files;
	}

	/**
	 * Remove an upload that has not been submitted with a conversation.
	 *
	 * @param \WP_REST_Request $request Deletion request.
	 * @return array|\WP_Error Result.
	 */
	public static function remove( $request ) {
		$files = self::resolve( array( $request['id'] ) );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$file = $files[0];
		if ( ! empty( $file['used'] ) ) {
			return new \WP_Error( 'attachment_in_use', __( 'This attachment is already part of a conversation.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$reply = wp_remote_request(
			'https://api.openai.com/v1/files/' . rawurlencode( $file['file_id'] ),
			array(
				'method'      => 'DELETE',
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array( 'Authorization' => 'Bearer ' . AssistantSettings::key() ),
			)
		);
		if ( is_wp_error( $reply ) || wp_remote_retrieve_response_code( $reply ) >= 400 ) {
			return new \WP_Error( 'attachment_remove', __( 'The upload could not be removed from OpenAI. It will expire automatically within one hour.', 'ohmylms' ), array( 'status' => 502 ) );
		}
		delete_transient( 'oml_file_' . get_current_user_id() . '_' . $file['id'] );
		return array( 'removed' => true );
	}
}
