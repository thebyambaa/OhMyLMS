<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Curriculum administration: the item tree, moves, deletion and links to courses, skills,
 * question banks and quizzes/exams. Every route needs an administrator; the server validates
 * every parent, type and target again regardless of what the editor sends.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\CourseLinks;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Curriculum\SyllabusCourse;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Curriculum administration: the item tree, moves, deletion and links to courses, skills,
 * question banks and quizzes/exams. Every route needs an administrator; the server validates
 * every parent, type and target again regardless of what the editor sends.
 */
class CurriculumController extends RestController {
	/**
     * Register routes for Curriculum controller.
   */
    public function register_routes() {
		$admin = array( Access::class, 'admin' );
		register_rest_route(
			$this->namespace,
			'/curriculum/tree',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'tree' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/curriculum/items',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/curriculum/items/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $admin,
					'args'                => array(
						'children'          => array(
							'type'    => 'string',
							'enum'    => array( '', 'promote', 'delete' ),
							'default' => '',
						),
						'confirm'           => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'expected_revision' => array(
							'type'      => 'string',
							'minLength' => 64,
							'maxLength' => 64,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/curriculum/items/(?P<id>\d+)/move',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'move' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/curriculum/items/(?P<id>\d+)/links',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_link' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_link' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/curriculum/link-targets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'link_targets' ),
					'permission_callback' => $admin,
				),
			)
		);
		// Advisory links between courses (sub-courses, prerequisites, "get ready for"). Anyone who can edit the course may manage them.
		$course_editor = function ( WP_REST_Request $request ) {
			return $this->check_object_permission( $request, 'edit', array( OHMYLMS_COURSE_CPT ) );
		};
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/links',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'course_links' ),
					'permission_callback' => $course_editor,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'replace_course_links' ),
					'permission_callback' => $course_editor,
				),
			)
		);
	}

	/**
     * Course links for Curriculum controller.
  *
  * @param WP_REST_Request $request REST request.
    */
    public function course_links( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'links' => $this->titled( CourseLinks::get( (int) $request['id'] ) ) ) );
	}

	/**
     * Add the linked course's title so the editor can show what it links to.
   *
  * @param array[] $links Links from CourseLinks.
    * @return array[]
  */
	private function titled( array $links ) {
		foreach ( $links as &$link ) {
			$link['title']  = html_entity_decode( (string) get_the_title( $link['course_id'] ), ENT_QUOTES, 'UTF-8' );
			$link['status'] = (string) get_post_status( $link['course_id'] );
		}
		return $links;
	}

	/**
     * Replace course links for Curriculum controller.
  *
  * @param WP_REST_Request $request REST request.
    */
    public function replace_course_links( WP_REST_Request $request ) {
		$result = CourseLinks::replace( (int) $request['id'], $request['links'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'links' => $this->titled( $result ) ) );
	}

	/**
     * Payload for Curriculum controller.
   *
  * @param array $extra Extra.
   */
    private function payload( array $extra = array() ) {
		return rest_ensure_response( $extra + array( 'items' => Items::tree() ) );
	}

	/**
     * Tree for Curriculum controller.
  */
    public function tree() {
		return rest_ensure_response(
			array(
				'items'      => Items::tree(),
				'types'      => Items::types(),
				'link_types' => Links::TYPES,
				'icons'      => \OhMyLMS\Curriculum\Icons::options(),
				'limits'     => array(
					'max_depth' => Items::MAX_DEPTH,
					'max_items' => Items::MAX_ITEMS,
				),
			)
		);
	}

	/**
     * Create for Curriculum controller.
    *
  * @param WP_REST_Request $request REST request.
    */
    public function create( WP_REST_Request $request ) {
		$item = Items::create( $request->get_params() );
		if ( is_wp_error( $item ) ) {
			return $item; }
		// An item typed "syllabus" is a syllabus from the start, and a syllabus is also a course.
		if ( ! empty( $item['is_syllabus'] ) ) {
			$this->make_course( (int) $item['id'] ); }
		$response = $this->payload( array( 'item' => Items::describe( Items::get( (int) $item['id'] ) ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/** Give a syllabus its course and bring it up to date. The change that asked for it stands even if this fails.
     * @param mixed $syllabus_id Syllabus identifier.
   */
	private function make_course( $syllabus_id ) {
		$course = SyllabusCourse::ensure( $syllabus_id );
		return is_wp_error( $course ) ? $course : SyllabusCourse::sync_quietly( $syllabus_id );
	}

	/**
     * Show for Curriculum controller.
  *
  * @param WP_REST_Request $request REST request.
    */
    public function show( WP_REST_Request $request ) {
		$item = Items::get( (int) $request['id'] );
		if ( ! $item ) {
			return Items::missing(); }
		$links    = Links::for_item( (int) $item['id'] );
		$mappings = array();
		foreach ( $links['skill'] as $skill ) {
			$mappings[ $skill['id'] ] = SkillMappings::for_skill( $skill['id'] ); }
		return rest_ensure_response(
			array(
				'item'            => Items::describe( $item ),
				'path'            => Items::path_names( (int) $item['id'], false ),
				'links'           => $links,
				'skill_mappings'  => (object) $mappings,
				'dependents'      => Items::dependents( (int) $item['id'] ),
				'delete_revision' => Items::delete_revision( (int) $item['id'] ),
			)
		);
	}

	/**
     * Update for Curriculum controller.
    *
  * @param WP_REST_Request $request REST request.
    */
    public function update( WP_REST_Request $request ) {
		$params = $request->get_params();
		$item   = Items::update( (int) $request['id'], array_intersect_key( $params, array_flip( array( 'name', 'item_type', 'description', 'code', 'version', 'is_syllabus', 'icon' ) ) ), $params['expected_updated_at'] ?? null );
		if ( is_wp_error( $item ) ) {
			return $item; }
		// Turning an item into a syllabus gives it a course; renaming a syllabus renames its course.
		if ( ! empty( $item['is_syllabus'] ) ) {
			if ( ! empty( $params['is_syllabus'] ) && rest_sanitize_boolean( $params['is_syllabus'] ) ) {
				$this->make_course( (int) $item['id'] ); } else {
				SyllabusCourse::sync_quietly( (int) $item['id'] ); }
				$item = Items::get( (int) $item['id'] );
		}
		return $this->payload( array( 'item' => Items::describe( $item ) ) );
	}

	/**
     * Move for Curriculum controller.
  *
  * @param WP_REST_Request $request REST request.
    */
    public function move( WP_REST_Request $request ) {
		$params = $request->get_params();
		$before = Syllabus::owner( (int) $request['id'] );
		$item   = Items::move( (int) $request['id'], (int) ( $params['parent_id'] ?? 0 ), isset( $params['position'] ) && '' !== $params['position'] ? (int) $params['position'] : null );
		if ( is_wp_error( $item ) ) {
			return $item; }
		// Moving an item can move the skill groups under it between syllabuses, or change the order of the chapters.
		SyllabusCourse::after_items_changed( array( $before, Syllabus::owner( (int) $item['id'] ) ) );
		return $this->payload( array( 'item' => Items::describe( $item ) ) );
	}

	/**
     * Delete for Curriculum controller.
    *
  * @param WP_REST_Request $request REST request.
    */
    public function delete( WP_REST_Request $request ) {
		$owner  = Syllabus::owner( (int) $request['id'] );
		$result = Items::delete( (int) $request['id'], (string) $request->get_param( 'children' ), rest_sanitize_boolean( $request->get_param( 'confirm' ) ), $request->has_param( 'expected_revision' ) ? (string) $request['expected_revision'] : null );
		if ( is_wp_error( $result ) ) {
			return $result; }
		// The skill groups under a deleted item go with it, and so do their chapters in the syllabus's course.
		if ( $owner && ! in_array( $owner, array_map( 'intval', (array) ( $result['deleted'] ?? array() ) ), true ) ) {
			SyllabusCourse::after_items_changed( array( $owner ) ); }
		return $this->payload( $result );
	}

	/**
     * Add link for Curriculum controller.
  *
  * @param WP_REST_Request $request REST request.
    */
    public function add_link( WP_REST_Request $request ) {
		$result = Links::add( (int) $request['id'], (string) $request->get_param( 'object_type' ), (int) $request->get_param( 'object_id' ) );
		return is_wp_error( $result ) ? $result : $this->links_payload( (int) $request['id'] );
	}

	/**
     * Remove link for Curriculum controller.
   *
  * @param WP_REST_Request $request REST request.
    */
    public function remove_link( WP_REST_Request $request ) {
		$result = Links::remove( (int) $request['id'], (string) $request->get_param( 'object_type' ), (int) $request->get_param( 'object_id' ) );
		return is_wp_error( $result ) ? $result : $this->links_payload( (int) $request['id'] );
	}

	/**
     * Links payload for Curriculum controller.
     *
  * @param mixed $item_id Item identifier.
   */
    private function links_payload( $item_id ) {
		$links    = Links::for_item( $item_id );
		$mappings = array();
		foreach ( $links['skill'] as $skill ) {
			$mappings[ $skill['id'] ] = SkillMappings::for_skill( $skill['id'] ); }
		return $this->payload(
			array(
				'links'          => $links,
				'skill_mappings' => (object) $mappings,
			)
		);
	}

	/**
     * Link targets for Curriculum controller.
  *
  * @param WP_REST_Request $request REST request.
    */
    public function link_targets( WP_REST_Request $request ) {
		$type = (string) $request->get_param( 'type' );
		if ( ! Links::valid_type( $type ) ) {
			return Access::error( 'ohmylms_link_invalid', __( 'Choose course, skill, question bank or quiz/exam content.', 'ohmylms' ) ); }
		return rest_ensure_response( Links::targets( $type, (string) $request->get_param( 'search' ), array_filter( array_map( 'intval', explode( ',', (string) $request->get_param( 'include' ) ) ) ) ) );
	}
}
