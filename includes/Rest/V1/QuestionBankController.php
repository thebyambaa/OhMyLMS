<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Question banks: the banks a user can see, bank creation and settings, and sharing grants.
 *
 * Questions themselves are served by `ohmylms/v1/questions` ({@see QuestionsController}) and their
 * placement in quizzes by `ohmylms/v1/quizzes/{id}/questions` ({@see QuizQuestionsController}).
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\Banks;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Question banks and their sharing grants.
 */
class QuestionBankController extends RestController {
	/**
	 * Register routes for Question bank controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/question-banks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'banks' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_bank' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/question-banks/(?P<bank_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_bank' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/question-banks/(?P<bank_id>[\d]+)/grants',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'grants' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'grant' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
	}

	/**
	 * Author permission for Question bank controller.
	 */
	public function author_permission() {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_bank_unavailable', __( 'The question bank is not installed yet.', 'ohmylms' ), array( 'status' => 503 ) ); }
		return AccessPolicy::check( AccessPolicy::can_author() );
	}

	/**
	 * Banks for Question bank controller.
	 */
	public function banks() {
		return rest_ensure_response( array( 'banks' => Banks::for_user() ) ); }

	/**
	 * Create bank for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function create_bank( WP_REST_Request $request ) {
		$bank = Banks::create( $request['name'], (int) $request['course_id'], (string) ( $request['visibility'] ? $request['visibility'] : 'private' ) );
		if ( is_wp_error( $bank ) ) {
			return $bank; }
		$response = rest_ensure_response( $bank );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Update bank for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function update_bank( WP_REST_Request $request ) {
		$bank = Banks::update( (int) $request['bank_id'], $request->get_params() );
		return is_wp_error( $bank ) ? $bank : rest_ensure_response( $bank );
	}

	/**
	 * Grants for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function grants( WP_REST_Request $request ) {
		$bank = Banks::get( (int) $request['bank_id'] );
		if ( ! $bank ) {
			return new WP_Error( 'ohmylms_bank_missing', __( 'Question bank not found.', 'ohmylms' ), array( 'status' => 404 ) ); }
		if ( ! Banks::manages( $bank ) ) {
			return AccessPolicy::denied(); }
		return rest_ensure_response( array( 'grants' => Banks::grants( (int) $request['bank_id'] ) ) );
	}

	/**
	 * Grant for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function grant( WP_REST_Request $request ) {
		$result = Banks::grant( (int) $request['bank_id'], (int) $request['user_id'], (string) $request['permission'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'grants' => $result ) );
	}

	/**
	 * Revoke for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function revoke( WP_REST_Request $request ) {
		$result = Banks::revoke( (int) $request['bank_id'], (int) $request['user_id'], (string) $request['permission'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'grants' => $result ) );
	}
}
