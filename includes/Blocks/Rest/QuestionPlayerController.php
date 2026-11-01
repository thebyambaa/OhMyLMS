<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Question player block REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Blocks\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Blocks\QuestionPlayerBlock;
use OhMyLMS\QuestionBank\AccessPolicy;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/question-player/choices` (author picker metadata) and
 * `ohmylms/v1/question-player/<post_id>/<key>` (learner operations on a saved placement).
 * {@see QuestionPlayerBlock} resolves the placement from the saved post; client flags are never trusted.
 */
class QuestionPlayerController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/question-player/choices',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'kind'    => array(
						'type'    => 'string',
						'enum'    => array( 'questions', 'skills' ),
						'default' => 'questions',
					),
					'search'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'include' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/question-player/(?P<post_id>\d+)/(?P<key>[a-f0-9]{32})',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'operate' ),
				'permission_callback' => array( $this, 'operate_permissions_check' ),
				'args'                => array(
					'operation' => array(
						'type'     => 'string',
						'enum'     => array( 'start', 'resume', 'submit', 'study' ),
						'required' => true,
					),
					'session'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * The picker is for content authors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {
		return AccessPolicy::can_author();
	}

	/**
	 * Question or skill choices, without answers.
	 *
	 * @param WP_REST_Request $request Author search request.
	 * @return array|\WP_Error
	 */
	public function get_items( $request ) {
		return QuestionPlayerBlock::choices( $request );
	}

	/**
	 * Any signed-in user; the placement then rechecks access to its post and course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function operate_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Start, resume, submit or study.
	 *
	 * @param WP_REST_Request $request Learner request.
	 * @return array|\WP_Error Player state.
	 */
	public function operate( $request ) {
		return QuestionPlayerBlock::operate( $request );
	}
}
