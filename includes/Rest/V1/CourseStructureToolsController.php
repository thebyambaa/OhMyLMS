<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Internal REST routes behind the course structure MCP tools.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\Schema;
use OhMyLMS\MCP\CourseStructureTools;
use OhMyLMS\MCP\Server;

defined( 'ABSPATH' ) || exit;

/**
 * One validated contract for the structure tools. Every operation calls the service the Content Hub uses, so
 * its validation and edit-permission rules apply, and nothing here publishes a program.
 */
class CourseStructureToolsController extends RestController {

	/** Register each concrete operation without exposing arbitrary dispatch. */
	public function register_routes() {
		foreach ( CourseStructureTools::definitions() as $name => $tool ) {
			if ( 0 !== strpos( $tool['route'], CourseStructureTools::PREFIX . '/' ) ) {
				continue; // Served by the course editor's own route.
			}
			$args = $tool['properties'];
			foreach ( $args as $key => &$schema ) {
				$schema['required'] = in_array( $key, $tool['required'], true );
			}
			unset( $schema );
			register_rest_route(
				$this->namespace,
				str_replace( '{id}', '(?P<id>\d+)', $tool['route'] ),
				array(
					'methods'             => $tool['method'],
					'args'                => $args,
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => function ( $request ) use ( $name ) {
						return $this->dispatch( $name, $request ); },
				)
			);
		}
	}

	/**
	 * Administrator access plus permission to edit this course, as for the Content Hub.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permission( $request ) {
		if ( ! Server::can_manage() || ! Catalog::can_edit( (int) $request['id'] ) ) {
			return new \WP_Error( 'ohmylms_forbidden', __( 'Administrator access to this course is required.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return Schema::ready() ? true : Catalog::error( __( 'Learning storage is unavailable.', 'ohmylms' ), 503, 'ohmylms_learning_unavailable' );
	}

	/**
	 * Run one known operation.
	 *
	 * @param string           $name Tool name.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function dispatch( $name, $request ) {
		$course = (int) $request['id'];
		$unit   = (int) $request->get_param( 'chapter_id' );
		switch ( $name ) {
			case 'get_course_program':
				return rest_ensure_response( Catalog::program_view( $course ) );
			case 'save_course_program':
				return $this->save_program( $course, $request );
			case 'create_course_unit':
				$created = Catalog::add_chapter( $course, (string) $request->get_param( 'name' ) );
				return is_wp_error( $created ) ? $created : $this->units( $course, (int) $created['id'], 201 );
			case 'rename_course_unit':
				$done = Catalog::update_chapter( $course, $unit, array( 'name' => (string) $request->get_param( 'name' ) ) );
				return is_wp_error( $done ) ? $done : $this->units( $course, $unit );
			case 'set_unit_number':
				$done = Catalog::update_chapter( $course, $unit, array( 'number' => (string) $request->get_param( 'number' ) ) );
				return is_wp_error( $done ) ? $done : $this->units( $course, $unit );
			case 'reorder_course_units':
				$done = Catalog::reorder_chapters( $course, array_map( 'intval', (array) $request->get_param( 'ids' ) ) );
				return is_wp_error( $done ) ? $done : $this->units( $course );
			case 'delete_course_unit':
				if ( true !== $request->get_param( 'confirm' ) ) {
					return Catalog::error( __( 'Deleting a unit is permanent. Ask a person, then call again with confirm=true.', 'ohmylms' ), 400, 'ohmylms_confirm_required' );
				}
				$done = Catalog::delete_chapter( $course, $unit );
				return is_wp_error( $done ) ? $done : rest_ensure_response(
					array(
						'deleted'  => $unit,
						'units'    => Catalog::units( $course ),
						'revision' => Catalog::revision( $course ),
					)
				);
			default:
				return $this->save_unit_groups( $course, $unit, $request );
		}
	}

	/**
	 * Replace parts of the draft program, or only check the change.
	 *
	 * @param int              $course  Course ID.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function save_program( $course, $request ) {
		$data = array();
		foreach ( array( 'mode', 'outcomes', 'attachments', 'groups' ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$data[ $key ] = $request->get_param( $key );
			}
		}
		if ( ! $data ) {
			return Catalog::error( __( 'Send at least one of mode, outcomes, attachments or groups.', 'ohmylms' ) );
		}
		$dry = rest_sanitize_boolean( $request->get_param( 'dry_run' ) );
		return $this->guarded(
			$course,
			$request,
			static function () use ( $course, $data, $dry ) {
				if ( $dry ) {
					$program = Catalog::check_draft( $course, $data );
					return is_wp_error( $program ) ? $program : array(
						'dry_run'     => true,
						'valid'       => true,
						'outcomes'    => count( $program['outcomes'] ),
						'attachments' => count(
							array_filter(
								$program['items'],
								static function ( $item ) {
									return 'practice' !== $item['type'];
								}
							)
						),
						'groups'      => count( $program['groups'] ?? array() ),
					);
				}
				$saved = Catalog::save_draft( $course, $data );
				return is_wp_error( $saved ) ? $saved : array( 'saved' => true ) + Catalog::program_view( $course );
			}
		);
	}

	/**
	 * Replace the lesson groups of one unit, or only check the change.
	 *
	 * @param int              $course  Course ID.
	 * @param int              $unit    Unit (chapter) ID.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function save_unit_groups( $course, $unit, $request ) {
		$spec = (array) $request->get_param( 'groups' );
		$dry  = rest_sanitize_boolean( $request->get_param( 'dry_run' ) );
		return $this->guarded(
			$course,
			$request,
			static function () use ( $course, $unit, $spec, $dry ) {
				$program = Catalog::save_unit_groups( $course, $unit, $spec, $dry );
				if ( is_wp_error( $program ) ) {
					return $program;
				}
				if ( ! $dry ) {
					return array( 'saved' => true ) + Catalog::program_view( $course );
				}
				return array(
					'dry_run' => true,
					'valid'   => true,
					'groups'  => array_values(
						array_filter(
							(array) ( $program['groups'] ?? array() ),
							static function ( $group ) use ( $unit ) {
								return (int) $group['chapter_id'] === $unit;
							}
						)
					),
				);
			}
		);
	}

	/**
	 * Run a draft change under the revision check.
	 *
	 * @param int              $course  Course ID.
	 * @param \WP_REST_Request $request Request.
	 * @param callable         $change  The change.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function guarded( $course, $request, callable $change ) {
		$result = Catalog::with_revision( $course, $request->get_param( 'revision' ), $change );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * The unit list, with the changed unit when there is one.
	 *
	 * @param int $course Course ID.
	 * @param int $unit   Changed unit ID, or 0.
	 * @param int $status HTTP status.
	 * @return \WP_REST_Response
	 */
	private function units( $course, $unit = 0, $status = 200 ) {
		$units = Catalog::units( $course );
		$data  = array( 'units' => $units );
		foreach ( $units as $row ) {
			if ( $unit && $row['id'] === $unit ) {
				$data = array( 'unit' => $row ) + $data;
			}
		}
		$response = rest_ensure_response( $data );
		$response->set_status( $status );
		return $response;
	}
}
