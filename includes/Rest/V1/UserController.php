<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling user REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Course;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Controller for handling user REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for User-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class UserController extends RestController {

	/**
	 * The base route for user base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'users';

	/**
	 * Check user permission for User controller.
	 */
	public function check_user_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Registers REST API routes for user operations.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_user_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}


	/**
	 * Retrieves all users with their enrollment data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$search = $request->get_param( 'search' ); // Get the search parameter from the request.

		// Prepare query arguments.
		$query_args = array(
			'number' => -1, // Limit the number of users to return.
		);

		if ( $search ) {
			$query_args['search']         = '*' . esc_attr( $search ) . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		// Query the users.
		$user_query = new \WP_User_Query( $query_args );
		$users      = $user_query->get_results();

		// If no users are found, return an empty response.
		if ( empty( $users ) ) {
			return new WP_REST_Response( array(), 200 );
		}

		// Prepare the user data for the response.
		$data = array();
		foreach ( $users as $user ) {
			$data[] = array(
				'id'           => $user->ID,
				'username'     => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
			);
		}

		// Return the response.
		return new WP_REST_Response( $data, 200 );
	}
}
