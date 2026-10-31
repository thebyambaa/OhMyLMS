<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Question bank organisation REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\QuestionBank\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\Organisation;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `GET ohmylms/v1/question-bank-organisation`: the curriculum, roadmap, course and skill views the
 * Question Bank filters by. {@see Organisation} builds them; they never carry question content.
 */
class OrganisationController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/question-bank-organisation',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'callback'            => array( $this, 'get_items' ),
			)
		);
	}

	/**
	 * Content authors only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {
		return AccessPolicy::can_author();
	}

	/**
	 * Hierarchical organisation views.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array View definitions.
	 */
	public function get_items( $request ) {
		return Organisation::views();
	}
}
