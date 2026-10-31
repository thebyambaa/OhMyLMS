<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Legacy exam import figure REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\ExamImport\Figures;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\ExamImport\PageRenderer;
use OhMyLMS\ExamImport\Reconstruction;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy `ohmylms/v1/exam-imports/<id>/reconstruct`, `exam-imports/<id>/figures` and
 * `exam-import-renderer`. Crops are private assets of the job owner ({@see Figures}); a
 * reconstruction is an unapproved proposal ({@see Reconstruction}).
 */
class FiguresController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/exam-imports/(?P<id>\d+)/reconstruct',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'reconstruct' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/exam-imports/(?P<id>\d+)/figures',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, 'get_items' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, 'create_item' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/exam-import-renderer',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'get_renderer' ),
			)
		);
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
	 * Ask the exam converter to reconstruct one crop.
	 *
	 * @param WP_REST_Request $request Authorized owner request.
	 * @return array|\WP_Error Validated proposal.
	 */
	public function reconstruct( $request ) {
		return Reconstruction::generate( $request );
	}

	/**
	 * The job's retained crops.
	 *
	 * @param WP_REST_Request $request Author request.
	 * @return array|\WP_Error
	 */
	public function get_items( $request ) {
		return Figures::listing( $request );
	}

	/**
	 * Queue a manual crop.
	 *
	 * @param WP_REST_Request $request Job, page, normalized bounds.
	 * @return array|\WP_Error
	 */
	public function create_item( $request ) {
		return Figures::create( $request );
	}

	/**
	 * Whether this server can render PDF pages.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function get_renderer( $request ) {
		return PageRenderer::capability();
	}
}
