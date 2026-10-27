<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course tags were replaced by Course Bundles. GET returns bundles in the shape of the former tag terms
 * (the bundle ID is `term_id`) for older clients; nothing can be written here any more. Bundles are
 * managed with /bundles and a course's bundles with /courses/{id}/organization.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Placement;

/**
 * Course tags were replaced by Course Bundles. GET returns bundles in the shape of the former tag terms
 * (the bundle ID is `term_id`) for older clients; nothing can be written here any more. Bundles are
 * managed with /bundles and a course's bundles with /courses/{id}/organization.
 */
class TagController extends RestController {

	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'tags';

	/**
	 * Get items permissions check for Tag controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register routes for Tag controller.
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
	 * Get items for Tag controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_items( $request ) {
		return rest_ensure_response( Placement::term_bundles( (string) $request['search'] ) );
	}

	/**
	 * Replaced for Tag controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function replaced( $request ) {
		return new \WP_Error(
			'ohmylms_tags_replaced',
			__( 'Course tags were replaced by Course Bundles. Manage them under OhMyLMS > Course Bundles.', 'ohmylms' ),
			array( 'status' => 410 )
		);
	}
}
