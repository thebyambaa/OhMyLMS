<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Quiz player block REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Blocks\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Blocks\QuizPlayerBlock;
use OhMyLMS\QuestionBank\AccessPolicy;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/quiz-player/quizzes` (author picker metadata) and
 * `ohmylms/v1/quiz-player/quizzes/<id>/attempt` (inline start and submit). {@see QuizPlayerBlock}
 * delegates attempts to the locked, server-authoritative quiz engine.
 */
class QuizPlayerController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/quiz-player/quizzes/(?P<id>\d+)/attempt',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'attempt_permissions_check' ),
				'callback'            => array( $this, 'attempt' ),
				'args'                => array(
					'id'              => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'operation'       => array(
						'type'     => 'string',
						'enum'     => array( 'start', 'submit', 'exit', 'timeout' ),
						'required' => true,
					),
					'quiz_attempt_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/quiz-player/quizzes',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'callback'            => array( $this, 'get_items' ),
				'args'                => array(
					'search'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'include' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);
	}

	/**
	 * Any signed-in user; the quiz engine then checks access to the quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function attempt_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Start, submit, exit or time out an inline attempt.
	 *
	 * @param WP_REST_Request $request Authenticated request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function attempt( $request ) {
		return QuizPlayerBlock::mutate( $request );
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
	 * Quiz titles and author preview links, without question content.
	 *
	 * @param WP_REST_Request $request Picker request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		return QuizPlayerBlock::choices( $request );
	}
}
