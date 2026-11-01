<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * View-as REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools\Rest;

use OhMyLMS\Schools\ViewAs;
use WP_Error;
use WP_REST_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/school/view-as/{options,default,start,return}`: an administrator previews the site as another
 * person and returns to their own session ({@see ViewAs}). Every route needs the REST nonce; `return` needs a
 * view-as session, the others `manage_options`. Responses are `private, no-store`.
 */
class ViewAsController extends WP_REST_Controller {

	/**
	 * Actions and their methods. Each action is the {@see ViewAs} method of the same name.
	 */
	const ACTIONS = array(
		'options' => 'GET',
		'default' => 'GET',
		'start'   => 'POST',
		'return'  => 'POST',
	);

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'school/view-as';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		foreach ( self::ACTIONS as $action => $method ) {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/' . $action,
				array(
					'methods'             => $method,
					'permission_callback' => function ( $request ) use ( $action ) {
						return $this->permissions_check( $request, $action );
					},
					'callback'            => function ( $request ) use ( $action ) {
						return $this->run( $request, $action );
					},
				)
			);
		}
	}

	/**
	 * Signed in with a valid REST nonce; then a view-as session to return, or an administrator otherwise.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $action  Action.
	 * @return true|WP_Error
	 */
	public function permissions_check( $request, $action ) {
		if ( ! is_user_logged_in() || ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'view_as_auth', 'Refresh the page and sign in again.', array( 'status' => 403 ) );
		}
		return ( 'return' === $action ? (bool) ViewAs::session() : current_user_can( 'manage_options' ) ) ? true : new WP_Error( 'view_as_forbidden', 'Only an administrator can switch accounts.', array( 'status' => 403 ) );
	}

	/**
	 * Run the action and map its refusals to 400 or 403.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $action  Action.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function run( $request, $action ) {
		try {
			$response = rest_ensure_response( call_user_func( array( ViewAs::class, $action ), $request ) );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		} catch ( \Throwable $e ) {
			return new WP_Error( 'view_as_error', $e->getMessage(), array( 'status' => in_array( $e->getCode(), array( 400, 403 ), true ) ? $e->getCode() : 400 ) );
		}
	}
}
