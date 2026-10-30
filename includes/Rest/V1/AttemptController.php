<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner endpoints for an in-progress versioned attempt: resume state and per-item autosave.
 * Only the attempt's own learner can use them; nothing here reveals correctness.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Deadlines;
use OhMyLMS\Assessment\Grader;
use OhMyLMS\Assessment\Responses;
use OhMyLMS\Quiz\Submission;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Learner endpoints for an in-progress versioned attempt: resume state and per-item autosave.
 * Only the attempt's own learner can use them; nothing here reveals correctness.
 */
class AttemptController extends RestController {
	/**
	 * Register routes for Attempt controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/attempts/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'resume' ),
					'permission_callback' => array( $this, 'owner_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/attempts/(?P<id>[\d]+)/responses',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'owner_permission' ),
					'args'                => array(
						'question_id' => array(
							'required' => true,
							'type'     => 'integer',
							'minimum'  => 1,
						),
						'sequence'    => array(
							'type'    => 'integer',
							'default' => 0,
							'minimum' => 0,
							'maximum' => 2147483647,
						),
						'response'    => array(
							'description'       => 'The answer: text, a number, a boolean, a list or an object; at most 64 KB when encoded.',
							'type'              => array( 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' ),
							'validate_callback' => array( $this, 'validate_response_size' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Reject an oversized answer before it is sanitised or stored.
	 *
	 * @param mixed $value Submitted answer.
	 * @return true|WP_Error
	 */
	public function validate_response_size( $value ) {
		if ( strlen( (string) wp_json_encode( $value ) ) > 65535 ) {
			return new WP_Error( 'quiz_answer', __( 'This answer is too long.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/** The current user must own an in-progress versioned attempt they can still access.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function owner_permission( WP_REST_Request $request ) {
		global $wpdb;
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ohmylms_login_required', __( 'Please log in.', 'ohmylms' ), array( 'status' => 401 ) ); }
		$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", (int) $request['id'] ), ARRAY_A );
		if ( ! $attempt || get_current_user_id() !== (int) $attempt['student_id'] ) {
			return new WP_Error( 'ohmylms_attempt_missing', __( 'Attempt not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$access = Submission::access( (int) $attempt['quiz_id'], get_current_user_id() );
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! AttemptItems::is_versioned( (int) $attempt['id'] ) ) {
			return new WP_Error( 'ohmylms_attempt_legacy', __( 'This attempt does not support autosave.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		return true;
	}

	/**
	 * Attempt for Attempt controller.
	 *
	 * @param mixed $id Id.
	 */
	private function attempt( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", (int) $id ), ARRAY_A );
	}

	/**
	 * Resume for Attempt controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function resume( WP_REST_Request $request ) {
		$attempt_id = (int) $request['id'];
		$attempt    = $this->attempt( $attempt_id );
		if ( ! $attempt ) {
			return new WP_Error( 'ohmylms_attempt_missing', __( 'Attempt not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$context   = AttemptItems::context( $attempt_id );
		$saved     = Responses::for_resume( $attempt_id );
		$responses = array();
		foreach ( AttemptItems::items( $attempt_id ) as $item ) {
			if ( ! isset( $saved[ (int) $item['id'] ] ) ) {
				continue; }
			$responses[ (int) $item['question_id'] ] = array(
				'response' => AttemptItems::tokenize( $attempt_id, $item, $saved[ (int) $item['id'] ]['response'] ),
				'sequence' => $saved[ (int) $item['id'] ]['sequence'],
			);
		}
		return rest_ensure_response(
			array(
				'attempt_id'        => $attempt_id,
				'status'            => $attempt['status'],
				'deadline_at'       => $context['deadline_at'] ? gmdate( 'c', strtotime( $context['deadline_at'] . ' UTC' ) ) : null,
				'remaining_seconds' => Deadlines::remaining( $context ),
				'server_time'       => gmdate( 'c' ),
				'responses'         => (object) $responses,
			)
		);
	}

	/**
	 * Save for Attempt controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function save( WP_REST_Request $request ) {
		$attempt_id = (int) $request['id'];
		$attempt    = $this->attempt( $attempt_id );
		if ( ! $attempt ) {
			return new WP_Error( 'ohmylms_attempt_missing', __( 'Attempt not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( 'in-progress' !== $attempt['status'] ) {
			return new WP_Error( 'quiz_attempt', __( 'This attempt has already been submitted.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$context = AttemptItems::context( $attempt_id );
		if ( Deadlines::closed( $context ) ) {
			return new WP_Error( 'quiz_deadline_passed', __( 'Time is up. Answers received after the deadline are not saved.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$item = null;
		foreach ( AttemptItems::items( $attempt_id ) as $candidate ) {
			if ( (int) $candidate['question_id'] === (int) $request['question_id'] ) {
				$item = $candidate;
				break; }
		}
		if ( ! $item ) {
			return new WP_Error( 'quiz_question', __( 'That question is not part of this attempt.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$response = $request['response'];
		if ( null === $response ) {
			$response = array(); }
		if ( ! is_array( $response ) && ! is_scalar( $response ) ) {
			return new WP_Error( 'quiz_answer', __( 'Invalid answer format.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$answer = Grader::sanitize( AttemptItems::untokenize( $attempt_id, $item, is_array( $response ) ? $response : array( $response ) ) );
		if ( strlen( wp_json_encode( $answer ) ) > 65535 ) {
			return new WP_Error( 'quiz_answer', __( 'This answer is too long.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$saved = Responses::save( $attempt_id, $item, $answer, (int) $request['sequence'] );
		if ( is_wp_error( $saved ) ) {
			return $saved; }
		$saved['remaining_seconds'] = Deadlines::remaining( $context );
		return rest_ensure_response( $saved );
	}
}
