<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Legacy exam import job REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\ExamImport\Jobs;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy `ohmylms/v1/exam-imports` upload and listing, and `exam-imports/<id>` status (GET), retry
 * (POST) and removal (DELETE). The typed admin uses `exam-imports/jobs` instead
 * ({@see \OhMyLMS\Rest\V1\ExamImportsController}). Jobs are visible to their owner only, including to
 * administrators; {@see Jobs} owns the rule and the job pipeline.
 */
class JobsController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/exam-imports',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'create_item' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/exam-imports',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'get_items' ),
			)
		);
		foreach ( array(
			'GET'    => 'get_item',
			'POST'   => 'retry_item',
			'DELETE' => 'delete_item',
		) as $method => $callback ) {
			register_rest_route(
				$this->namespace,
				'/exam-imports/(?P<id>\d+)',
				array(
					'methods'             => $method,
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, $callback ),
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
	 * Store an uploaded source and queue validation.
	 *
	 * @param WP_REST_Request $request Multipart upload request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		return Jobs::create( $request );
	}

	/**
	 * The current owner's jobs.
	 *
	 * @param WP_REST_Request $request Paginated request.
	 * @return array|\WP_Error
	 */
	public function get_items( $request ) {
		return Jobs::listing( $request );
	}

	/**
	 * One job's status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function get_item( $request ) {
		return Jobs::status( $request );
	}

	/**
	 * Retry validation of a failed job.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function retry_item( $request ) {
		return Jobs::retry( $request );
	}

	/**
	 * Remove a job and its source files.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function delete_item( $request ) {
		return Jobs::delete( $request );
	}
}
