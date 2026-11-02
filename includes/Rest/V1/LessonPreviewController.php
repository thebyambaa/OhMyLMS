<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Signed "Preview as learner" links for lesson editors.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Learning\LessonPreview;

defined( 'ABSPATH' ) || exit;

/** Mint short-lived learner preview links for people who can edit the lesson. */
class LessonPreviewController extends \WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'lessons';

	/** Register `POST /ohmylms/v1/lessons/:id/learner-preview`. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/learner-preview',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Only people who can edit the lesson.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( LessonPreview::can_preview( (int) $request['id'] ) ) {
			return true;
		}
		return new \WP_Error( 'ohmylms_lesson_preview_forbidden', __( 'You cannot preview this lesson.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Links with and without the lock bypass, valid for the same period.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_item( $request ) {
		$id       = (int) $request['id'];
		$expires  = time() + LessonPreview::lifetime();
		$response = rest_ensure_response(
			array(
				'id'         => $id,
				'url'        => LessonPreview::url( $id, false, $expires ),
				'bypass_url' => LessonPreview::url( $id, true, $expires ),
				'expires'    => $expires,
				'status'     => (string) get_post_status( $id ),
			)
		);
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}
}
