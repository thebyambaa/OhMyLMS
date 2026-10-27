<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * HTTP client for an xAPI Learning Record Store.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * Sends statements to the configured LRS using Basic authentication and xAPI 1.0.3.
 *
 * Every response is reduced to one of four classes so the processor never has to know about
 * HTTP: ok (delivered), retry (try again later, counts against the row), hold (the connection
 * is wrong, so pause the whole queue without using up attempts) and reject (the LRS refused
 * this content, so retrying cannot help).
 */
final class LrsClient {

	const VERSION = '1.0.3';

	/**
	 * Resource URL under the endpoint.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @param string $path     Resource path, for example "statements".
	 * @return string
	 */
	public static function url( $endpoint, $path ) {
		return untrailingslashit( $endpoint ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Request headers.
	 *
	 * @param string $key    LRS key (username).
	 * @param string $secret LRS secret (password).
	 * @return array
	 */
	public static function headers( $key, $secret ) {
		return array(
			'Authorization'            => 'Basic ' . base64_encode( $key . ':' . $secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication is defined as base64 of "user:password".
			'X-Experience-API-Version' => self::VERSION,
			'Content-Type'             => 'application/json; charset=utf-8',
		);
	}

	/**
	 * Classify an HTTP outcome.
	 *
	 * @param int    $code  HTTP status, or 0 when the request never completed.
	 * @param string $error Transport error or response excerpt, used in the message.
	 * @return array class (ok|retry|hold|reject), status, message.
	 */
	public static function interpret( $code, $error = '' ) {
		$code = (int) $code;
		// 409: the LRS already has a statement with this ID, which is what a replay produces.
		if ( ( $code >= 200 && $code < 300 ) || 409 === $code ) {
			$class = 'ok';
		} elseif ( 0 === $code || 408 === $code || 429 === $code || $code >= 500 ) {
			$class = 'retry';
		} elseif ( in_array( $code, array( 401, 403, 404, 405 ), true ) || ( $code >= 300 && $code < 400 ) ) {
			$class = 'hold';
		} else {
			$class = 'reject';
		}
		$messages = array(
			401 => __( 'The LRS rejected the key and secret.', 'ohmylms' ),
			403 => __( 'The LRS refused access for this key.', 'ohmylms' ),
			404 => __( 'The LRS endpoint was not found. Check the endpoint URL.', 'ohmylms' ),
			405 => __( 'The LRS endpoint does not accept statements. Check the endpoint URL.', 'ohmylms' ),
		);
		if ( isset( $messages[ $code ] ) ) {
			$message = $messages[ $code ];
		} elseif ( $code >= 300 && $code < 400 ) {
			$message = __( 'The LRS endpoint redirected the request. Use the final endpoint URL (often https).', 'ohmylms' );
		} elseif ( 0 === $code ) {
			$message = '' !== $error ? $error : __( 'The LRS could not be reached.', 'ohmylms' );
		} elseif ( 'ok' === $class ) {
			$message = '';
		} else {
			/* translators: 1: HTTP status, 2: LRS response excerpt. */
			$message = sprintf( __( 'LRS responded %1$d. %2$s', 'ohmylms' ), $code, $error );
		}

		return array(
			'class'   => $class,
			'status'  => $code,
			'message' => trim( $message ),
		);
	}

	/**
	 * Send statements in one request.
	 *
	 * A success also carries "stored": the statement IDs the LRS says it stored, or null when
	 * it did not say. A 200 alone does not prove delivery: a real LRS was seen to store the valid
	 * statements of a batch, drop the invalid one, and answer 200 with only the stored IDs.
	 *
	 * @param array[] $statements Statements.
	 * @return array See interpret(), plus stored (string[]|null) on success.
	 */
	public static function post( array $statements ) {
		$request = self::request( 'POST', 'statements', wp_json_encode( array_values( $statements ), JSON_UNESCAPED_UNICODE ) );
		if ( isset( $request['result'] ) ) {
			return $request['result'];
		}
		$result = self::interpret( $request['code'], $request['body'] );
		if ( 'ok' === $result['class'] ) {
			// A 409 means "already stored", which says nothing about the other statements, so it is never trusted as a list.
			$result['stored'] = 409 === $result['status'] ? null : self::stored_ids( $request['raw'] );
		}

		return $result;
	}

	/**
	 * The statement IDs an LRS reports having stored, from the body of a successful POST.
	 *
	 * @param string $body Response body.
	 * @return string[]|null Lower-case IDs, or null when the body is not a list of IDs (for
	 *                       example an empty 204), in which case delivery cannot be verified.
	 */
	public static function stored_ids( $body ) {
		$ids = json_decode( (string) $body, true );
		if ( ! is_array( $ids ) ) {
			return null;
		}
		$stored = array();
		foreach ( $ids as $id ) {
			if ( ! Uuid::is_valid( $id ) ) {
				return null;
			}
			$stored[] = strtolower( $id );
		}

		return $stored;
	}

	/**
	 * Whether the LRS has a statement.
	 *
	 * @param string $statement_id Statement UUID.
	 * @return bool|null True if stored, false if the LRS says it has no such statement, null when
	 *                   the answer is unclear (network trouble, an unexpected status).
	 */
	public static function exists( $statement_id ) {
		$request = self::request( 'GET', 'statements?statementId=' . rawurlencode( $statement_id ) );
		if ( isset( $request['result'] ) || 0 === $request['code'] ) {
			return null;
		}
		if ( 200 === $request['code'] ) {
			return true;
		}

		return 404 === $request['code'] ? false : null;
	}

	/**
	 * Check the connection and the credentials.
	 *
	 * The "about" resource only proves the address is right: LRSs commonly answer it without
	 * checking the key, so it cannot be the whole test. The credentials are checked by posting an
	 * empty list of statements, which needs the same write permission as sending and stores nothing.
	 *
	 * @return array ok (bool), message.
	 */
	public static function test() {
		$about = self::request( 'GET', 'about' );
		if ( isset( $about['result'] ) ) {
			return array(
				'ok'      => false,
				'message' => $about['result']['message'],
			);
		}
		$outcome = self::interpret( $about['code'], $about['body'] );
		if ( 'ok' !== $outcome['class'] ) {
			return array(
				'ok'      => false,
				'message' => $outcome['message'],
			);
		}
		$info     = json_decode( $about['body'], true );
		$versions = is_array( $info ) && ! empty( $info['version'] ) ? implode( ', ', (array) $info['version'] ) : '';
		/* translators: %s: xAPI versions the LRS reports, or empty. */
		$supports = '' !== $versions ? ' ' . sprintf( __( 'The LRS supports xAPI %s.', 'ohmylms' ), $versions ) : '';

		$check = self::post( array() );
		if ( 'ok' === $check['class'] ) {
			return array(
				'ok'      => true,
				'message' => __( 'Connected, and the key is accepted for sending.', 'ohmylms' ) . $supports,
			);
		}
		if ( 'reject' === $check['class'] ) {
			// The LRS refused an empty request for a reason other than the credentials, so they are unproven, not wrong.
			return array(
				'ok'      => true,
				'message' => __( 'Connected. This LRS does not accept an empty test request, so the key could not be checked; it will be checked when the first statement is sent.', 'ohmylms' ) . $supports,
			);
		}

		return array(
			'ok'      => false,
			'message' => $check['message'],
		);
	}

	/**
	 * Perform a request against the configured LRS.
	 *
	 * @param string      $method   HTTP method.
	 * @param string      $path     Resource path.
	 * @param string|null $body     Request body.
	 * @return array Either code and body, or a ready-made result when the request cannot be made.
	 */
	private static function request( $method, $path, $body = null ) {
		$settings = Settings::all();
		$endpoint = Settings::normalize_endpoint( $settings['endpoint'] );
		if ( '' === $endpoint || '' === $settings['key'] ) {
			return array(
				'result' => array(
					'class'   => 'hold',
					'status'  => 0,
					'message' => __( 'The LRS endpoint and key are not configured.', 'ohmylms' ),
				),
			);
		}
		$args = array(
			'method'      => $method,
			'timeout'     => 15,
			// Never follow redirects: the Authorization header must not travel to another host.
			'redirection' => 0,
			'headers'     => self::headers( $settings['key'], $settings['secret'] ),
		);
		if ( null !== $body ) {
			$args['body'] = $body;
		}
		$args     = apply_filters( 'ohmylms_xapi_request_args', $args, $method, $path );
		$response = wp_remote_request( self::url( $endpoint, $path ), $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'code' => 0,
				'body' => $response->get_error_message(),
				'raw'  => '',
			);
		}
		$text = wp_remote_retrieve_body( $response );

		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			// A short plain-text excerpt for messages, and the untouched body for parsing.
			'body' => 'GET' === $method ? $text : substr( trim( wp_strip_all_tags( $text ) ), 0, 300 ),
			'raw'  => $text,
		);
	}
}
