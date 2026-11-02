<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MCP connection settings REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\MCP\Server;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/mcp/settings` and `mcp/settings/<provider>` (generate or revoke a provider token).
 * The MCP endpoint itself (`ohmylms/v1/mcp`) is registered by the WordPress MCP Adapter;
 * {@see Server} owns the tokens and the readiness report.
 */
class ServerController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/mcp/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/mcp/settings/(?P<provider>openai|anthropic|gemini)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'action' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'generate', 'revoke' ),
					),
				),
			)
		);
	}

	/**
	 * Administrators who can also edit content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permissions_check( $request ) {
		return Server::can_manage();
	}

	/**
	 * Connection details, provider token state and readiness.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		return Server::settings();
	}

	/**
	 * Generate or revoke one provider's token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		return Server::update_settings( $request );
	}
}
