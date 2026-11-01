<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Recorded response upload REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\ResponseMedia;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `POST ohmylms/v1/response-media` (multipart: file, kind): a learner's audio or video answer.
 * {@see ResponseMedia} validates, rate-limits and stores the file.
 */
class ResponseMediaController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/response-media',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			)
		);
	}

	/**
	 * Any signed-in user may upload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Save an uploaded recording.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		return ResponseMedia::upload( $request );
	}
}
