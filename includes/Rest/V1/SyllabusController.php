<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * A syllabus's skill groups and skills, and importing them from a CSV the browser has already read
 * and mapped. Administrators only, like the rest of curriculum administration. Every write answers
 * with the syllabus's fresh outline and the item tree (for the counts shown on the rows).
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Curriculum\SyllabusCourse;
use OhMyLMS\Curriculum\SyllabusSettings;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * A syllabus's skill groups and skills, and importing them from a CSV the browser has already read
 * and mapped. Administrators only, like the rest of curriculum administration. Every write answers
 * with the syllabus's fresh outline and the item tree (for the counts shown on the rows).
 */
class SyllabusController extends RestController {
	/**
	 * Register routes for Syllabus controller.
	 */
	public function register_routes() {
		$admin = array( Access::class, 'admin' );
		$base  = '/curriculum/items/(?P<id>\d+)/syllabus';
		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/settings',
			array(
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'settings' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/publish',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/course',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'course' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/course/skills',
			array(
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'course_skills' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/groups',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_group' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/groups/(?P<group>\d+)',
			array(
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_group' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_group' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/groups/(?P<group>\d+)/move',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'move_group' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/groups/(?P<group>\d+)/skills',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_skill' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/groups/(?P<group>\d+)/skills/(?P<term>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_skill' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/groups/(?P<group>\d+)/skills/(?P<term>\d+)/move',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'move_skill' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/skills/(?P<term>\d+)',
			array(
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_skill' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/**
	 * The fresh outline plus the tree, merged with anything the operation wants to report. A syllabus is also a
	 * course, so a change first brings the course up to date; if the course cannot follow, the change still stands
	 * and the response says so in `course_error`.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param array $extra Extra.
	 */
	private function outline( $syllabus_id, array $extra = array() ) {
		$synced  = SyllabusCourse::sync_quietly( $syllabus_id );
		$outline = Syllabus::outline( $syllabus_id );
		if ( is_wp_error( $outline ) ) {
			return $outline; }
		$extra += array(
			'course'   => SyllabusCourse::summary( $syllabus_id ),
			'settings' => SyllabusSettings::get( $syllabus_id ),
		);
		if ( is_wp_error( $synced ) ) {
			$extra['course_error'] = $synced->get_error_message(); }
		return rest_ensure_response( $extra + SyllabusCourse::decorate( $outline, $syllabus_id ) + array( 'items' => Items::tree() ) );
	}

	/**
	 * Show for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function show( WP_REST_Request $request ) {
		$outline = Syllabus::outline( (int) $request['id'] );
		return is_wp_error( $outline ) ? $outline : rest_ensure_response(
			SyllabusCourse::decorate( $outline, (int) $request['id'] ) + array(
				'course'   => SyllabusCourse::summary( (int) $request['id'] ),
				'settings' => SyllabusSettings::get( (int) $request['id'] ),
			)
		);
	}

	/**
	 * Settings for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function settings( WP_REST_Request $request ) {
		$result = SyllabusSettings::save( (int) $request['id'], (array) $request->get_json_params() );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'] );
	}

	/**
	 * Publish for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function publish( WP_REST_Request $request ) {
		$result = SyllabusSettings::publish( (int) $request['id'] );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'] );
	}

	/** Give the syllabus its course if it has none, and make the course match the syllabus.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function course( WP_REST_Request $request ) {
		$syllabus = (int) $request['id'];
		$course   = SyllabusCourse::ensure( $syllabus );
		return is_wp_error( $course ) ? $course : $this->outline( $syllabus );
	}

	/** Which skills the course requires, and at what target: `skills` is `[{term_id, required, target}]`.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function course_skills( WP_REST_Request $request ) {
		$skills = $request->get_param( 'skills' );
		if ( ! is_array( $skills ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'Send the skills to change.', 'ohmylms' ), 400 ); }
		$changes = array_map(
			static function ( $skill ) {
				if ( ! is_array( $skill ) ) {
					return $skill; }
				$change = array( 'term_id' => (int) ( $skill['term_id'] ?? 0 ) );
				if ( array_key_exists( 'required', $skill ) ) {
					$change['required'] = rest_sanitize_boolean( $skill['required'] ); }
				if ( isset( $skill['target'] ) ) {
					$change['target'] = (string) $skill['target']; }
				return $change;
			},
			$skills
		);
		$result  = SyllabusCourse::set_requirements( (int) $request['id'], $changes );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'] );
	}

	/**
	 * Import for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function import( WP_REST_Request $request ) {
		$rows = $request->get_param( 'rows' );
		if ( ! is_array( $rows ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'Send the rows to import.', 'ohmylms' ) ); }
		$report = Syllabus::import( (int) $request['id'], $rows, rest_sanitize_boolean( $request->get_param( 'dry_run' ) ) );
		if ( is_wp_error( $report ) ) {
			return $report; }
		// A dry run changes nothing, so only an applied import needs the new outline.
		return $report['applied'] ? $this->outline( (int) $request['id'], array( 'report' => $report ) ) : rest_ensure_response( array( 'report' => $report ) );
	}

	/**
	 * Add group for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function add_group( WP_REST_Request $request ) {
		$result = Syllabus::add_group( (int) $request['id'], $request->get_params() );
		return is_wp_error( $result ) ? $result : $this->created( $this->outline( (int) $request['id'], array( 'group_id' => (int) $result ) ) );
	}

	/**
	 * Update group for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function update_group( WP_REST_Request $request ) {
		$result = Syllabus::update_group( (int) $request['id'], (int) $request['group'], $request->get_params() );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'] );
	}

	/**
	 * Move group for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function move_group( WP_REST_Request $request ) {
		$result = Syllabus::move_group( (int) $request['id'], (int) $request['group'], (int) $request->get_param( 'item_id' ), $request->get_param( 'position' ) );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'] );
	}

	/**
	 * Delete group for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function delete_group( WP_REST_Request $request ) {
		$result = Syllabus::delete_group( (int) $request['id'], (int) $request['group'], rest_sanitize_boolean( $request->get_param( 'confirm' ) ) );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'], $result );
	}

	/**
	 * Add skill for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function add_skill( WP_REST_Request $request ) {
		$syllabus = (int) $request['id'];
		$group    = (int) $request['group'];
		if ( (int) $request->get_param( 'term_id' ) > 0 ) {
			$result = Syllabus::add_skill( $syllabus, $group, (int) $request->get_param( 'term_id' ) );
			return is_wp_error( $result ) ? $result : $this->created( $this->outline( $syllabus ) );
		}
		$result = Syllabus::create_skill( $syllabus, $group, $request->get_params() );
		return is_wp_error( $result ) ? $result : $this->created( $this->outline( $syllabus, array( 'skill' => $result ) ) );
	}

	/**
	 * Update skill for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function update_skill( WP_REST_Request $request ) {
		$result = Syllabus::update_skill( (int) $request['id'], (int) $request['term'], $request->get_params() );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'] );
	}

	/**
	 * Remove skill for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function remove_skill( WP_REST_Request $request ) {
		$result = Syllabus::remove_skill( (int) $request['id'], (int) $request['group'], (int) $request['term'] );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'] );
	}

	/**
	 * Move skill for Syllabus controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function move_skill( WP_REST_Request $request ) {
		$result = Syllabus::move_skill( (int) $request['id'], (int) $request['group'], (int) $request['term'], (int) $request->get_param( 'group_id' ), $request->get_param( 'position' ) );
		return is_wp_error( $result ) ? $result : $this->outline( (int) $request['id'] );
	}

	/**
	 * Created for Syllabus controller.
	 *
	 * @param mixed $response Response data.
	 */
	private function created( $response ) {
		if ( ! is_wp_error( $response ) ) {
			$response->set_status( 201 ); }
		return $response;
	}
}
