<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Legacy exam composer REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Exams\Composer;
use OhMyLMS\Exams\Library;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy `ohmylms/v1/exams/compose` and `exams/<id>/settings|questions`, used by the quiz editor
 * panel. {@see Composer} creates the blank exam and its backing quiz; {@see Library} owns the
 * author-only rule.
 */
class ComposerController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/exams/compose',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'create_item' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/exams/(?P<id>\d+)/settings',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'update_settings' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/exams/(?P<id>\d+)/questions',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'add_questions' ),
			)
		);
	}

	/**
	 * Authoring access, and ownership of an existing exam.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permissions_check( $request ) {
		return Library::permission( $request );
	}

	/**
	 * Create a blank draft exam.
	 *
	 * @param WP_REST_Request $request Title and duration.
	 * @return array|\WP_Error Exam summary.
	 */
	public function create_item( $request ) {
		return Composer::create( $request );
	}

	/**
	 * Save exam-only fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error Exam summary.
	 */
	public function update_settings( $request ) {
		return Composer::settings( $request );
	}

	/**
	 * Append bank questions to a draft exam.
	 *
	 * @param WP_REST_Request $request Exam ID and question IDs.
	 * @return array|\WP_Error Exam summary.
	 */
	public function add_questions( $request ) {
		return Composer::add_questions( $request );
	}
}
