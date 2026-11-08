<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * How a course is organized: the curriculum items it is linked to. This replaces course categories
 * and tags in the course editor.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\QuestionBank\AccessPolicy;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * How a course is organized: the curriculum items it is linked to. This replaces course categories
 * and tags in the course editor.
 *
 *  - Anyone who may edit the course can read the outline and change its curriculum links.
 *  - Creating or restructuring curriculum items stays on the Curriculum page.
 */
class CourseOrganizationController extends RestController {
	/**
	 * Register routes for Course organization controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/organization',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'course_permission' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'course_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/curriculum/outline',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'curriculum_outline' ),
					'permission_callback' => array( $this, 'author_permission' ),
				),
			)
		);
	}

	/**
	 * Author permission for Course organization controller.
	 */
	public function author_permission() {
		if ( ! Schema::ready() ) {
			return new \WP_Error( 'ohmylms_curriculum_unavailable', __( 'Curriculum storage is not installed yet.', 'ohmylms' ), array( 'status' => 503 ) ); }
		return AccessPolicy::check( current_user_can( 'edit_posts' ) );
	}

	/**
	 * Course permission for Course organization controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function course_permission( WP_REST_Request $request ) {
		$ready = $this->author_permission();
		return is_wp_error( $ready ) ? $ready : $this->check_object_permission( $request, 'edit', OHMYLMS_COURSE_CPT );
	}

	/**
	 * Describe for Course organization controller.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	private function describe( $course_id ) {
		return rest_ensure_response(
			array(
				'curriculum'    => Placement::items( $course_id ),
				'level_summary' => \OhMyLMS\Skills\CourseLevels::get( $course_id ),
			)
		);
	}

	/**
	 * Show for Course organization controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function show( WP_REST_Request $request ) {
		return $this->describe( (int) $request['id'] );
	}

	/** Replace a course's curriculum links. Send only what changes.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function update( WP_REST_Request $request ) {
		$course_id  = (int) $request['id'];
		$curriculum = $request->get_param( 'curriculum_ids' );
		if ( null !== $curriculum && ! is_array( $curriculum ) ) {
			return Access::error( 'ohmylms_organization_invalid', __( 'Send the curriculum items as a list.', 'ohmylms' ) ); }
		if ( is_array( $curriculum ) ) {
			$result = Placement::set_items( $course_id, $curriculum );
			if ( is_wp_error( $result ) ) {
				return $result; }
		}
		return $this->describe( $course_id );
	}

	/** The whole structure, names only, for pickers. With `courses=1` each item also lists its published courses.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function curriculum_outline( WP_REST_Request $request ) {
		$courses = $request->get_param( 'courses' ) ? Placement::published_courses_by_item() : null;
		return rest_ensure_response(
			array(
				'items' => array_map(
					static function ( $row ) use ( $courses ) {
						$item = array(
							'id'        => (int) $row['id'],
							'parent_id' => (int) $row['parent_id'],
							'position'  => (int) $row['position'],
							'name'      => $row['name'],
							'item_type' => $row['item_type'],
							'code'      => $row['code'],
							'version'   => $row['version'],
						);
						if ( null !== $courses ) {
							$item['courses'] = $courses[ (int) $row['id'] ] ?? array(); }
						return $item;
					},
					Items::all()
				),
			)
		);
	}

}
