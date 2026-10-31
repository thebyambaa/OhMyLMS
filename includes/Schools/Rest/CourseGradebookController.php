<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course gradebook REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools\Rest;

use OhMyLMS\Schools\Gradebook;
use OhMyLMS\Schools\Service;
use WP_Error;
use WP_REST_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/courses/<course_id>/gradebook[/classes[/<class_id>]|/grades]`: the class gradebook of a course
 * ({@see Gradebook}). Administrators only, with the REST nonce; responses are `private, no-store`.
 */
class CourseGradebookController extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Route base, after the course.
	 *
	 * @var string
	 */
	protected $rest_base = 'gradebook';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		foreach ( array(
			''                           => 'GET',
			'/classes'                   => 'GET,POST',
			'/classes/(?P<class_id>\d+)' => 'POST,DELETE',
			'/grades'                    => 'POST,DELETE',
		) as $suffix => $methods ) {
			register_rest_route(
				$this->namespace,
				'/courses/(?P<course_id>\d+)/' . $this->rest_base . $suffix,
				array(
					'methods'             => $methods,
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, 'dispatch' ),
				)
			);
		}
	}

	/**
	 * Administrators with a valid REST nonce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' ) && wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ? true : new WP_Error( 'gradebook_forbidden', 'Only an administrator can manage this gradebook.', array( 'status' => 403 ) );
	}

	/**
	 * Read the gradebook, list, attach or detach classes, or save or reset grades.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function dispatch( $request ) {
		try {
			$course = absint( $request['course_id'] );
			$post   = get_post( $course );
			Service::need( $post && OHMYLMS_COURSE_CPT === $post->post_type && 'trash' !== $post->post_status, 'Course not found.' );
			$route  = $request->get_route();
			$method = $request->get_method();
			if ( substr( $route, -7 ) === '/grades' ) {
				$data = Gradebook::grade( $course, $request, 'DELETE' === $method );
			} elseif ( $request['class_id'] ) {
				$data = 'DELETE' === $method ? Gradebook::detach( $course, absint( $request['class_id'] ) ) : Gradebook::attach( $course, absint( $request['class_id'] ), rest_sanitize_boolean( $request['enroll'] ) );
			} elseif ( substr( $route, -8 ) === '/classes' ) {
				$data = 'GET' === $method ? Gradebook::classes( $course ) : Gradebook::attach( $course, absint( $request['class'] ), rest_sanitize_boolean( $request['enroll'] ) );
			} else {
				$data = Gradebook::read( $course );
			}
			$response = rest_ensure_response( $data );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		} catch ( \Throwable $e ) {
			$status = in_array( $e->getCode(), array( 400, 403, 404, 409 ), true ) ? $e->getCode() : 500;
			if ( 500 === $status ) {
				error_log( '[OhMyLMS gradebook] ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- unexpected failures are logged; the learner-facing message stays generic.
			}
			return new WP_Error( 'gradebook_error', 500 === $status ? 'Unable to update the gradebook. Please try again.' : $e->getMessage(), array( 'status' => $status ) );
		}
	}
}
