<?php // phpcs:ignore WordPress.Files.FileName -- Existing PSR-4 class/file mapping.
/**
 * Same-process MCP transport for the signed-in administrator.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

use OhMyLMS\MCP\Server;

defined( 'ABSPATH' ) || exit;

/** No public tunnel, provider bearer token or alternate tool executor is needed. */
final class AssistantMcpClient {
	/**
	 * Exact internally dispatched request.
	 *
	 * @var \WP_REST_Request|null
	 */
	private static $active_request;
	/**
	 * Adapter session for this PHP request.
	 *
	 * @var string
	 */
	private static $session = '';

	/**
	 * Only the exact PHP-created request may use administrator cookie identity.
	 *
	 * @param \WP_REST_Request $request MCP request.
	 * @return bool
	 */
	public static function is_local_request( $request ) {
		return self::$active_request === $request && Server::can_manage();
	}

	/**
	 * Dispatch a JSON-RPC envelope through the actual registered MCP transport.
	 *
	 * @param string $method JSON-RPC method.
	 * @param array  $params Method parameters.
	 * @return array|\WP_Error
	 */
	private static function request( $method, array $params ) {
		$request = new \WP_REST_Request( 'POST', Server::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Accept', 'application/json, text/event-stream' );
		$request->set_header( 'mcp-protocol-version', '2025-11-25' );
		if ( self::$session ) {
			$request->set_header( 'mcp-session-id', self::$session );
		}
		$request->set_body(
			wp_json_encode(
				array(
					'jsonrpc' => '2.0',
					'id'      => wp_generate_uuid4(),
					'method'  => $method,
					'params'  => (object) $params,
				)
			)
		);
		self::$active_request = $request;
		try {
			$reply = rest_do_request( $request );
			$reply = apply_filters( 'rest_post_dispatch', $reply, rest_get_server(), $request );
		} finally {
			self::$active_request = null;
		}
		foreach ( $reply->get_headers() as $name => $value ) {
			if ( 'mcp-session-id' === strtolower( $name ) ) {
				self::$session = $value;
			}
		}
		$data = json_decode( wp_json_encode( $reply->get_data() ), true );
		if ( $reply->get_status() >= 400 || ! is_array( $data ) || isset( $data['error'] ) || ! isset( $data['result'] ) ) {
			return new \WP_Error( 'ohmylms_assistant_mcp', __( 'The LMS tool could not complete. Check MCP settings and your access.', 'ohmylms' ), array( 'status' => 502 ) );
		}
		return $data['result'];
	}

	/**
	 * Execute an allowlisted call using the existing MCP permission/schema checks.
	 *
	 * @param string $name Tool name.
	 * @param array  $arguments Validated arguments.
	 * @return array|\WP_Error
	 */
	public static function call( $name, array $arguments ) {
		if ( ! Server::can_manage() || ! Server::readiness()['ready'] ) {
			return new \WP_Error( 'ohmylms_assistant_mcp_unavailable', __( 'Enable the MCP add-on and check its readiness in Add-ons.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		$valid = AssistantTools::validate( $name, $arguments );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! self::$session ) {
			$hello = self::request(
				'initialize',
				array(
					'protocolVersion' => '2025-11-25',
					'capabilities'    => (object) array(),
					'clientInfo'      => array(
						'name'    => 'OhMyLMS admin assistant',
						'version' => OHMYLMS_VERSION,
					),
				)
			);
			if ( is_wp_error( $hello ) ) {
				return $hello;
			}
		}
		return self::request(
			'tools/call',
			array(
				'name'      => $name,
				'arguments' => (object) $arguments,
			)
		);
	}
}
