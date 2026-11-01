<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Legacy exam import review REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\ExamImport\Workflow;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy `ohmylms/v1/exam-import-skills`, `exam-imports/rows` and
 * `exam-imports/<id>/extract|review|commit|quiz`. {@see Workflow} owns extraction, review and the
 * explicit draft commits; {@see Jobs} owns the owner-only rule.
 */
class WorkflowController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/exam-import-skills',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'get_skills' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/exam-imports/rows',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'import_rows' ),
			)
		);
		foreach ( array( 'extract', 'review', 'commit', 'quiz' ) as $operation ) {
			register_rest_route(
				$this->namespace,
				'/exam-imports/(?P<id>\d+)/' . $operation,
				array(
					'methods'             => 'POST',
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, $operation . '_item' ),
				)
			);
		}
	}

	/**
	 * Authoring and upload capabilities, and ownership of an existing job.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permissions_check( $request ) {
		return Jobs::permission( $request );
	}

	/**
	 * Search the skill catalogue during a review.
	 *
	 * @param WP_REST_Request $request Author search.
	 * @return array|\WP_Error
	 */
	public function get_skills( $request ) {
		return Workflow::skills( $request );
	}

	/**
	 * Import CSV or JSON rows.
	 *
	 * @param WP_REST_Request $request Rows request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_rows( $request ) {
		return Workflow::rows( $request );
	}

	/**
	 * Send the uploaded documents to the configured AI provider.
	 *
	 * @param WP_REST_Request $request Owner request.
	 * @return array|\WP_Error
	 */
	public function extract_item( $request ) {
		return Workflow::extract( $request );
	}

	/**
	 * Save the teacher's review.
	 *
	 * @param WP_REST_Request $request Owner request.
	 * @return array|\WP_Error
	 */
	public function review_item( $request ) {
		return Workflow::review( $request );
	}

	/**
	 * Commit reviewed rows as draft questions.
	 *
	 * @param WP_REST_Request $request Owner request.
	 * @return array|\WP_Error
	 */
	public function commit_item( $request ) {
		return Workflow::commit( $request );
	}

	/**
	 * Create a draft quiz from committed questions.
	 *
	 * @param WP_REST_Request $request Owner request.
	 * @return array|\WP_Error
	 */
	public function quiz_item( $request ) {
		return Workflow::quiz( $request );
	}
}
