<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course categories were replaced by the curriculum. The admin app still reads this endpoint to fill
 * its course-list filter, so GET returns curriculum items in the shape of the former category terms
 * (the item ID is `term_id`). Nothing can be written here any more: structure is managed with
 * /curriculum/items and a course's placement with /courses/{id}/organization.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Placement;

/**
 * Course categories were replaced by the curriculum. The admin app still reads this endpoint to fill
 * its course-list filter, so GET returns curriculum items in the shape of the former category terms
 * (the item ID is `term_id`). Nothing can be written here any more: structure is managed with
 * /curriculum/items and a course's placement with /courses/{id}/organization.
 */
class CategoryController extends RestController {

	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'categories';

	/**
	 * Get items permissions check for Category controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register routes for Category controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'replaced' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'replaced' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'replaced' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/bulk',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'replaced' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get items for Category controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_items( $request ) {
		return rest_ensure_response( Placement::term_items() );
	}

	/**
	 * Replaced for Category controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function replaced( $request ) {
		return new \WP_Error(
			'ohmylms_categories_replaced',
			__( 'Course categories were replaced by the curriculum. Manage them under OhMyLMS > Curriculum.', 'ohmylms' ),
			array( 'status' => 410 )
		);
	}
}
