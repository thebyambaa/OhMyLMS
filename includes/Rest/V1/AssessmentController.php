<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Versioned assessment endpoints: revisions/publishing and the migration status.
 * Learner attempt endpoints (autosave, resume) are registered by AttemptController.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\Migration;
use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\Utility\Transaction;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Versioned assessment endpoints: revisions/publishing and the migration status.
 * Learner attempt endpoints (autosave, resume) are registered by AttemptController.
 */
class AssessmentController extends RestController {
	/**
	 * Register routes for Assessment controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/assessment/migration',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'migration_status' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'migration_run' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/quiz/(?P<id>[\d]+)/assessment-settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'settings' ),
					'permission_callback' => array( $this, 'quiz_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'quiz_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/quiz/(?P<id>[\d]+)/pools',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'pools' ),
					'permission_callback' => array( $this, 'quiz_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_pools' ),
					'permission_callback' => array( $this, 'quiz_permission' ),
				),
			)
		);
		// A quiz, unit test or course challenge that draws questions for a list of skills.
		register_rest_route(
			$this->namespace,
			'/skill-assessments',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_skill_assessment' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/skill-assessments/course',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_course_assessments' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/quiz/(?P<id>[\d]+)/revisions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'revisions' ),
					'permission_callback' => array( $this, 'quiz_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish' ),
					'permission_callback' => array( $this, 'quiz_permission' ),
				),
			)
		);
	}

	/**
	 * Admin permission for Assessment controller.
	 */
	public function admin_permission() {
		return AccessPolicy::check( current_user_can( 'manage_options' ) );
	}

	/**
	 * Author permission for Assessment controller.
	 */
	public function author_permission() {
		return AccessPolicy::check( current_user_can( 'edit_posts' ) );
	}

	/**
	 * Create course assessments for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function create_course_assessments( WP_REST_Request $request ) {
		$body   = (array) $request->get_json_params();
		$result = \OhMyLMS\Assessment\SkillAssessment::create_for_course( absint( $body['course_id'] ?? 0 ), $body );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Create skill assessment for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function create_skill_assessment( WP_REST_Request $request ) {
		$result = \OhMyLMS\Assessment\SkillAssessment::create( (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result; }
		$response = rest_ensure_response( $result );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Quiz permission for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function quiz_permission( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( get_post_type( $id ) !== OHMYLMS_QUIZ_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) ); }
		return AccessPolicy::check( AccessPolicy::can_edit_quiz( $id ) );
	}

	/**
	 * Settings for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function settings( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'settings' => \OhMyLMS\Assessment\AssessmentSettings::get( (int) $request['id'] ) ) );
	}

	/**
	 * Save settings for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function save_settings( WP_REST_Request $request ) {
		$saved = \OhMyLMS\Assessment\AssessmentSettings::save( (int) $request['id'], (array) $request->get_json_params() );
		return is_wp_error( $saved ) ? $saved : rest_ensure_response( array( 'settings' => $saved ) );
	}

	/**
	 * Pools for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function pools( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'pools' => \OhMyLMS\Assessment\Pools::rules( (int) $request['id'] ) ) );
	}

	/**
	 * Save pools for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function save_pools( WP_REST_Request $request ) {
		$saved = \OhMyLMS\Assessment\Pools::save( (int) $request['id'], (array) ( $request['pools'] ?? array() ) );
		return is_wp_error( $saved ) ? $saved : rest_ensure_response( array( 'pools' => $saved ) );
	}

	/**
	 * Migration status for Assessment controller.
	 */
	public function migration_status() {
		return rest_ensure_response(
			array(
				'schema'    => get_option( Schema::OPTION ),
				'state'     => Migration::state(),
				'inventory' => Migration::inventory(),
			)
		);
	}

	/**
	 * Migration run for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function migration_run( WP_REST_Request $request ) {
		$state = $request['retry'] ? Migration::retry_failed() : Migration::run_all( (int) ( $request['batches'] ?: 20 ) );
		return rest_ensure_response(
			array(
				'state'     => $state,
				'inventory' => Migration::inventory(),
			)
		);
	}

	/**
	 * Revisions for Assessment controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function revisions( WP_REST_Request $request ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, revision_no, kind, total_marks, status, created_by, created_at FROM %i WHERE quiz_id=%d ORDER BY revision_no DESC', Schema::table( 'quiz_revisions' ), (int) $request['id'] ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['attempts'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE revision_id=%d', Schema::table( 'attempt_context' ), (int) $row['id'] ) );
		}
		return rest_ensure_response( array( 'revisions' => $rows ) );
	}

	/** Validate the quiz and publish its current content as a revision.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function publish( WP_REST_Request $request ) {
		try {
			$revision = Transaction::run(
				static function () use ( $request ) {
					$revision = RevisionPublisher::publish( (int) $request['id'] );
					if ( is_wp_error( $revision ) ) {
						throw new \OhMyLMS\Assessment\ErrorException( $revision ); }
					return $revision;
				}
			);
		} catch ( \OhMyLMS\Assessment\ErrorException $error ) {
			return $error->error;
		} catch ( \Throwable $error ) {
			return new WP_Error( 'ohmylms_quiz_storage', __( 'The quiz could not be published.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'revision' => $revision ) );
	}
}
