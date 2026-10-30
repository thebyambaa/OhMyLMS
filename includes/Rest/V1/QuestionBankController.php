<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Question bank: search, approval, duplication, archive/restore, version history,
 * bank sharing, and placing existing questions into quizzes by reference.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\Approval;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\Duplicator;
use OhMyLMS\QuestionBank\RowExporter;
use OhMyLMS\QuestionBank\Search;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Utility\Transaction;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Question bank: search, approval, duplication, archive/restore, version history,
 * bank sharing, and placing existing questions into quizzes by reference.
 */
class QuestionBankController extends RestController {
	/**
	 * Register routes for Question bank controller.
	 */
	public function register_routes() {
		$id = '(?P<id>[\d]+)';
		register_rest_route(
			$this->namespace,
			'/question-bank',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/question-bank/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_rows' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			"/question-bank/$id",
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'detail' ),
					'permission_callback' => array( $this, 'use_permission' ),
				),
			)
		);
		foreach ( array(
			'approve'   => 'approve_permission',
			'archive'   => 'edit_permission',
			'restore'   => 'edit_permission',
			'duplicate' => 'use_permission',
		) as $action => $permission ) {
			register_rest_route(
				$this->namespace,
				"/question-bank/$id/$action",
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $action ),
						'permission_callback' => array( $this, $permission ),
					),
				)
			);
		}
		register_rest_route(
			$this->namespace,
			'/question-bank/versions/(?P<version_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'version' ),
					'permission_callback' => array( $this, 'version_permission' ),
				),
			)
		);
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
		register_rest_route(
			$this->namespace,
			'/quiz/(?P<quiz_id>[\d]+)/questions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_to_quiz' ),
					'permission_callback' => array( $this, 'quiz_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/quiz/(?P<quiz_id>[\d]+)/questions/(?P<question_id>[\d]+)/pin',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'pin' ),
					'permission_callback' => array( $this, 'quiz_permission' ),
				),
			)
		);
	}

	// ---- Permissions ----

	/**
	 * Author permission for Question bank controller.
	 */
	public function author_permission() {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_bank_unavailable', __( 'The question bank is not installed yet.', 'ohmylms' ), array( 'status' => 503 ) ); }
		return AccessPolicy::check( AccessPolicy::can_author() );
	}
	/**
	 * Question exists for Question bank controller.
	 *
	 * @param mixed $request REST request.
	 */
	private function question_exists( $request ) {
		return get_post_type( (int) $request['id'] ) === OHMYLMS_QUESTION_CPT ? true : new WP_Error( 'ohmylms_rest_invalid_question_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
	}
	/**
	 * Use permission for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function use_permission( WP_REST_Request $request ) {
		$exists = $this->question_exists( $request );
		return true !== $exists ? $exists : AccessPolicy::check( AccessPolicy::can_use_question( (int) $request['id'] ) );
	}
	/**
	 * Edit permission for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function edit_permission( WP_REST_Request $request ) {
		$exists = $this->question_exists( $request );
		return true !== $exists ? $exists : AccessPolicy::check( AccessPolicy::can_edit_question( (int) $request['id'] ) );
	}
	/**
	 * Approve permission for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function approve_permission( WP_REST_Request $request ) {
		$exists = $this->question_exists( $request );
		return true !== $exists ? $exists : AccessPolicy::check( AccessPolicy::can_approve_question( (int) $request['id'] ), __( 'You cannot approve versions of this question.', 'ohmylms' ) );
	}
	/**
	 * Version permission for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function version_permission( WP_REST_Request $request ) {
		$version = VersionPublisher::version( (int) $request['version_id'] );
		if ( ! $version ) {
			return new WP_Error( 'ohmylms_version_missing', __( 'Version not found.', 'ohmylms' ), array( 'status' => 404 ) ); }
		return AccessPolicy::check( AccessPolicy::can_use_question( (int) $version['question_id'] ) );
	}
	/**
	 * Quiz permission for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function quiz_permission( WP_REST_Request $request ) {
		$quiz_id = (int) $request['quiz_id'];
		if ( get_post_type( $quiz_id ) !== OHMYLMS_QUIZ_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) ); }
		return AccessPolicy::check( AccessPolicy::can_edit_quiz( $quiz_id ) );
	}

	// ---- Search ----

	/**
	 * Search for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function search( WP_REST_Request $request ) {
		$per_page = max( 1, min( 100, (int) ( $request['per_page'] ? $request['per_page'] : 20 ) ) );
		$page     = max( 1, (int) ( $request['page'] ? $request['page'] : 1 ) );
		$result   = Search::query( $request->get_params(), $page, $per_page );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$total    = $result['total'];
		$response = rest_ensure_response(
			array(
				'items'    => array_map( array( Search::class, 'summary' ), $result['ids'] ),
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );
		return $response;
	}

	/**
	 * Summary for Question bank controller.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	private function summary( $question_id ) {
		return Search::summary( (int) $question_id );
	}

	/**
	 * Detail for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function detail( WP_REST_Request $request ) {
		$question_id = (int) $request['id'];
		VersionPublisher::identity( $question_id );
		$question = ohmylms_get_question( $question_id );
		$data     = $this->summary( $question_id ) + array(
			'description' => $question->get_description(),
			'settings'    => $question->get_settings(),
			'options'     => $question->get_questions(),
			'skill_map'   => SkillMap::current( $question_id ),
			'versions'    => VersionPublisher::history( $question_id ),
			'quizzes'     => array_map(
				static function ( $quiz_id ) {
					return array(
						'id'       => $quiz_id,
						'name'     => get_the_title( $quiz_id ),
						'can_edit' => AccessPolicy::can_edit_quiz( $quiz_id ),
					); },
				Usage::quizzes( $question_id )
			),
		);
		return rest_ensure_response( $data );
	}

	/**
	 * Version for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function version( WP_REST_Request $request ) {
		$snapshot       = VersionPublisher::snapshot( (int) $request['version_id'] );
		$data           = $snapshot->to_array();
		$data['skills'] = SkillMap::for_version( $snapshot->get_version_id() );
		unset( $data['content_hash'] );
		return rest_ensure_response( $data );
	}

	// ---- Lifecycle ----

	/** Approve a version for shared use and practice (default: the current version). */
	/**
	 * Export questions as import rows (CSV or JSON), chosen by ID or as the questions of one quiz.
	 *
	 * @param WP_REST_Request $request ids (list or comma-separated) or quiz_id, format (json or csv), label.
	 * @return array|WP_Error filename, mime, content, count and warnings.
	 */
	public function export_rows( WP_REST_Request $request ) {
		$quiz_id  = absint( $request['quiz_id'] );
		$sections = array();
		$label    = is_string( $request['label'] ) ? sanitize_text_field( $request['label'] ) : '';
		if ( $quiz_id ) {
			if ( OHMYLMS_QUIZ_CPT !== get_post_type( $quiz_id ) || ! AccessPolicy::can_edit_quiz( $quiz_id ) ) {
				return AccessPolicy::denied( __( 'You are not allowed to export this quiz.', 'ohmylms' ) );
			}
			$ids   = array_map( 'intval', array_column( ohmylms_get_quiz( $quiz_id )->get_questions(), 'id' ) );
			$label = '' !== $label ? $label : get_the_title( $quiz_id );
			foreach ( (array) \OhMyLMS\Assessment\AssessmentSettings::get( $quiz_id )['sections'] as $section ) {
				foreach ( (array) ( $section['questions'] ?? array() ) as $question_id ) {
					$sections[ (int) $question_id ] = (string) ( $section['title'] ?? '' );
				}
			}
		} else {
			$ids = $request['ids'];
			$ids = is_array( $ids ) ? $ids : preg_split( '/[\s,]+/', (string) $ids );
		}
		return RowExporter::export( array_map( 'absint', (array) $ids ), 'csv' === $request['format'] ? 'csv' : 'json', $label, $sections );
	}

	/**
	 * Approve for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function approve( WP_REST_Request $request ) {
		$question_id = (int) $request['id'];
		$approved    = Approval::approve( $question_id, $request['version_id'] );
		if ( is_wp_error( $approved ) ) {
			return $approved;
		}
		return rest_ensure_response( $this->summary( $question_id ) );
	}

	/**
	 * Archive for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function archive( WP_REST_Request $request ) {
		$result = Usage::archive( (int) $request['id'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $this->summary( (int) $request['id'] ) );
	}

	/**
	 * Restore for Question bank controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function restore( WP_REST_Request $request ) {
		$result = Usage::restore( (int) $request['id'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $this->summary( (int) $request['id'] ) );
	}

	/**
	 * Duplicate as a new question (new UUID, same family). With quiz_id, the copy replaces the
	 * original in that quiz — the way to edit a shared, read-only question.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function duplicate( WP_REST_Request $request ) {
		$source_id = (int) $request['id'];
		$saved     = Duplicator::duplicate( $source_id, (int) $request['quiz_id'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$response = rest_ensure_response( $this->summary( $saved['id'] ) + array( 'duplicated_from' => $source_id ) );
		$response->set_status( 201 );
		return $response;
	}

	// ---- Quiz references ----

	/** Place existing bank questions into a quiz by reference (never copies or edits them).
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function add_to_quiz( WP_REST_Request $request ) {
		$quiz_id = (int) $request['quiz_id'];
		$ids     = array_values( array_unique( array_map( 'intval', (array) $request['question_ids'] ) ) );
		if ( ! $ids ) {
			return new WP_Error( 'ohmylms_question_ids_required', __( 'Choose at least one question.', 'ohmylms' ), array( 'status' => 400 ) ); }
		foreach ( $ids as $question_id ) {
			if ( ! AccessPolicy::can_use_question( $question_id ) ) {
				return AccessPolicy::denied( __( 'You cannot use one of these questions.', 'ohmylms' ) ); }
			if ( ! AccessPolicy::can_edit_question( $question_id ) ) {
				$identity = VersionPublisher::identity( $question_id );
				if ( ! $identity || ! (int) $identity['approved_version_id'] ) {
					return new WP_Error( 'quiz_question_unapproved', sprintf( /* translators: %s: text value. */
					__( '"%s" has no approved version yet.', 'ohmylms' ), get_the_title( $question_id ) ), array( 'status' => 409 ) );
				}
			}
		}
		try {
			Transaction::run(
				static function () use ( $ids, $quiz_id, $request ) {
					$pins = (array) get_post_meta( $quiz_id, '_ohmylms_version_pins', true );
					foreach ( $ids as $question_id ) {
						DraftWriter::link( $quiz_id, $question_id );
						if ( rest_sanitize_boolean( $request['pin'] ) ) {
							$identity = VersionPublisher::identity( $question_id );
							$version  = AccessPolicy::can_edit_question( $question_id ) ? VersionPublisher::capture( $question_id ) : VersionPublisher::version( (int) $identity['approved_version_id'] );
							if ( $version ) {
								$pins[ $question_id ] = (int) $version['id']; }
						}
					}
					update_post_meta( $quiz_id, '_ohmylms_version_pins', array_filter( $pins ) );
				}
			);
		} catch ( \Throwable $error ) {
			return new WP_Error( 'ohmylms_quiz_storage', __( 'Could not add the questions.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response(
			array(
				'quiz_id' => $quiz_id,
				'added'   => $ids,
				'content' => ohmylms_get_quiz( $quiz_id )->get_questions(),
			)
		);
	}

	/** Pin a question in this quiz to a specific version, or unpin (version_id null).
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function pin( WP_REST_Request $request ) {
		$quiz_id     = (int) $request['quiz_id'];
		$question_id = (int) $request['question_id'];
		if ( ! in_array( $quiz_id, Usage::quizzes( $question_id ), true ) ) {
			return new WP_Error( 'ohmylms_question_not_in_quiz', __( 'The question is not part of this quiz.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$pins       = (array) get_post_meta( $quiz_id, '_ohmylms_version_pins', true );
		$version_id = (int) $request['version_id'];
		if ( $version_id < 0 || 'current' === $request['version_id'] ) {
			// Pin whatever this quiz would use now: the current version, or the approved one for shared use.
			$current = \OhMyLMS\Assessment\RevisionPublisher::slot_version( $quiz_id, $question_id );
			if ( is_wp_error( $current ) ) {
				return $current; }
			$version_id = (int) $current['id'];
		}
		if ( $version_id ) {
			$version = VersionPublisher::version( $version_id );
			if ( ! $version || (int) $version['question_id'] !== $question_id ) {
				return new WP_Error( 'ohmylms_version_missing', __( 'Version not found for this question.', 'ohmylms' ), array( 'status' => 404 ) ); }
			$pins[ $question_id ] = $version_id;
		} else {
			unset( $pins[ $question_id ] );
		}
		update_post_meta( $quiz_id, '_ohmylms_version_pins', array_filter( $pins ) );
		return rest_ensure_response(
			array(
				'quiz_id'     => $quiz_id,
				'question_id' => $question_id,
				'version_id'  => $version_id ? $version_id : null,
			)
		);
	}

	// ---- Banks and sharing ----

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
