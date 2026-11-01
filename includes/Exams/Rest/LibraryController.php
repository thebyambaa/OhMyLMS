<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Legacy exam library REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Exams\Library;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy `ohmylms/v1/exams` (list, create) and `exams/<id>/publish|release`. The typed admin uses
 * `exams/library` ({@see \OhMyLMS\Rest\V1\ExamsController}). Exams are visible to their author only,
 * including to administrators; {@see Library} owns the rule, publication and release.
 */
class LibraryController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		foreach ( array(
			'GET'  => 'get_items',
			'POST' => 'create_item',
		) as $method => $callback ) {
			register_rest_route(
				$this->namespace,
				'/exams',
				array(
					'methods'             => $method,
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, $callback ),
				)
			);
		}
		foreach ( array( 'publish', 'release' ) as $operation ) {
			register_rest_route(
				$this->namespace,
				'/exams/(?P<id>\d+)/' . $operation,
				array(
					'methods'             => 'POST',
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, $operation . '_item' ),
				)
			);
		}
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
	 * The author's exams.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function get_items( $request ) {
		return Library::listing( $request );
	}

	/**
	 * Create a draft exam from an import job's committed questions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error Exam summary.
	 */
	public function create_item( $request ) {
		return Library::create( $request );
	}

	/**
	 * Publish an exam with approved, pinned question versions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error Exam summary.
	 */
	public function publish_item( $request ) {
		return Library::publish( $request );
	}

	/**
	 * Release the results of every pending attempt.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error Exam summary.
	 */
	public function release_item( $request ) {
		return Library::release( $request );
	}
}
