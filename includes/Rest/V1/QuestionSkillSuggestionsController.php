<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * AI suggestions of the skill each question assesses.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\Skills\QuestionSkillMatcher;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /question-skill-suggestions/curricula` lists the curriculum items to match against;
 * `POST /question-skill-suggestions` suggests a primary skill for up to ten questions. Saving an
 * accepted suggestion goes through `PATCH /questions/<id>` like any other skill change.
 */
class QuestionSkillSuggestionsController extends RestController {

	/** Register the routes. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/question-skill-suggestions/curricula',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'permission' ),
				'callback'            => static function () {
					return rest_ensure_response( QuestionSkillMatcher::curricula() );
				},
			)
		);
		register_rest_route(
			$this->namespace,
			'/question-skill-suggestions',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'question_ids'   => array(
						'type'     => 'array',
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'minItems' => 1,
						'maxItems' => QuestionSkillMatcher::MAX_QUESTIONS,
						'required' => true,
					),
					'item_id'        => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
					'include_mapped' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'callback'            => static function ( \WP_REST_Request $request ) {
					$result = QuestionSkillMatcher::suggest( (array) $request['question_ids'], (int) $request['item_id'], (bool) $request['include_mapped'] );
					return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
				},
			)
		);
	}

	/**
	 * Question authors only; each question is checked again before it is read.
	 *
	 * @return true|\WP_Error
	 */
	public function permission() {
		return AccessPolicy::can_edit_question( 0 ) ? true : new \WP_Error( 'ohmylms_forbidden', __( 'You cannot edit questions.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}
}
