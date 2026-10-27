<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Explicit, non-recording preview for authors: shows the quiz as learners would get it
 * (current revision, learner-safe views) and grades answers without creating attempts,
 * grade events or evidence. Unlike "view as", it never acts as another account.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Grader;
use OhMyLMS\Assessment\Pools;
use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Practice\Inline;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Utility\Transaction;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Explicit, non-recording preview for authors: shows the quiz as learners would get it
 * (current revision, learner-safe views) and grades answers without creating attempts,
 * grade events or evidence. Unlike "view as", it never acts as another account.
 */
class PreviewController extends RestController {
	/**
	 * Register routes for Preview controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/quiz/(?P<id>[\d]+)/preview',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'preview' ),
					'permission_callback' => array( $this, 'permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/quiz/(?P<id>[\d]+)/preview/grade',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'grade' ),
					'permission_callback' => array( $this, 'permission' ),
				),
			)
		);
	}

	/**
	 * Permission for Preview controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function permission( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( get_post_type( $id ) !== OHMYLMS_QUIZ_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) ); }
		return AccessPolicy::check( AccessPolicy::can_edit_quiz( $id ) );
	}

	/**
	 * Preview for Preview controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function preview( WP_REST_Request $request ) {
		$quiz_id = (int) $request['id'];
		try {
			$revision = Transaction::run(
				static function () use ( $quiz_id ) {
					$revision = RevisionPublisher::publish( $quiz_id );
					if ( is_wp_error( $revision ) ) {
						throw new \OhMyLMS\Assessment\ErrorException( $revision ); }
					return $revision;
				}
			);
		} catch ( \OhMyLMS\Assessment\ErrorException $error ) {
			return $error->error;
		}
		$seed      = random_int( 1, PHP_INT_MAX );
		$used      = array_filter( array_map( 'intval', array_column( $revision['slots'], 'question_id' ) ) );
		$questions = array();
		foreach ( $revision['slots'] as $slot ) {
			$version_id = (int) $slot['version_id'] ?: Pools::draw( $slot['pool'], $seed, $used, 0 );
			$snapshot   = $version_id ? VersionPublisher::snapshot( $version_id ) : null;
			if ( ! $snapshot ) {
				continue; }
			$used[]                  = $snapshot->get_id();
			$token                   = Inline::sign( $version_id, $quiz_id );
			$snapshot                = $snapshot->instantiate( Inline::seed( $token ) );
			$scope                   = Inline::scope( $token );
			$ids                     = array_map(
				static function ( $option ) {
					return (int) $option['id'];
				},
				$snapshot->get_questions()
			);
			$view                    = $snapshot->student_view( $ids, AttemptItems::tokens( $scope, $ids ), array( 'definitions' => $ids ), AttemptItems::tokens( $scope, $ids, 'd' ), $scope );
			$view['token']           = $token;
			$view['marks']           = (float) $slot['marks'];
			$view['section']         = (string) $slot['section'];
			$view['page']            = (int) $slot['page'];
			$view['drawn_from_pool'] = ! (int) $slot['version_id'];
			$questions[]             = $view;
		}
		return rest_ensure_response(
			array(
				'revision'  => array(
					'id'          => (int) $revision['id'],
					'number'      => (int) $revision['revision_no'],
					'total_marks' => (float) $revision['total_marks'],
				),
				'preview'   => true,
				'questions' => $questions,
			)
		);
	}

	/** Grade one previewed answer; nothing is stored.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function grade( WP_REST_Request $request ) {
		$claims = Inline::verify( (string) $request['token'] );
		if ( ! $claims || $claims['lesson_id'] !== (int) $request['id'] ) {
			return new WP_Error( 'ohmylms_preview_token', __( 'This preview has expired. Reload it.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$snapshot = VersionPublisher::snapshot( $claims['version_id'] );
		$snapshot = $snapshot ? $snapshot->instantiate( Inline::seed( (string) $request['token'] ) ) : null;
		if ( ! $snapshot ) {
			return new WP_Error( 'ohmylms_preview_missing', __( 'Question unavailable.', 'ohmylms' ), array( 'status' => 410 ) ); }
		$item     = array(
			'id'         => 0,
			'version_id' => $claims['version_id'],
		);
		$response = $request['response'] ?? array();
		$grade    = Grader::grade( $snapshot, AttemptItems::untokenize( Inline::scope( (string) $request['token'] ), $item, is_array( $response ) ? $response : array( $response ) ) );
		if ( is_wp_error( $grade ) ) {
			return $grade; }
		return rest_ensure_response(
			array(
				'correct'        => $grade['correct'],
				'fraction'       => $grade['fraction'],
				'pending_review' => $grade['pending'],
				'parts'          => $grade['parts'],
				'recorded'       => false,
			)
		);
	}
}
