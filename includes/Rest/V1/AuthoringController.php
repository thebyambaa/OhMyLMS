<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bounded curriculum authoring services for administrator MCP clients.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\LessonObjectives;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\MCP\AuthoringTools;
use OhMyLMS\MCP\ContentAuthoring;
use OhMyLMS\MCP\Server;

defined( 'ABSPATH' ) || exit;

/** One validated contract for REST and native WordPress abilities. */
class AuthoringController extends RestController {
	/** Register each concrete operation without exposing arbitrary dispatch. */
	public function register_routes() {
		foreach ( AuthoringTools::definitions() as $name => $tool ) {
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
					'callback'            => function ( $request ) use ( $name, $tool ) {
						return $this->dispatch( $name, $tool, $request ); },
				)
			);
		}
	}

	/**
	 * Reuse MCP administrator access; per-object services check ownership too.
	 *
	 * @return true|\WP_Error
	 */
	public function permission() {
		if ( ! Server::can_manage() ) {
			return Catalog::error( __( 'Administrator authoring access is required.', 'ohmylms' ), rest_authorization_required_code() );
		}
		return Schema::ready() ? true : Catalog::error( __( 'Curriculum storage is unavailable.', 'ohmylms' ), 503 );
	}

	/**
	 * Execute a known operation after schema validation and request bounds.
	 *
	 * @param string           $name Tool name.
	 * @param array            $tool Registered definition.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function dispatch( $name, array $tool, $request ) {
		$data = $request->get_json_params() ?? array();
		if ( ! is_array( $data ) || strlen( wp_json_encode( $data ) ) > 131072 ) {
			return Catalog::error( __( 'Use a JSON object of at most 128 KiB.', 'ohmylms' ) );
		}
		foreach ( array_keys( $data ) as $key ) {
			if ( ! isset( $tool['properties'][ $key ] ) ) {
				return Catalog::error( __( 'Unknown authoring field.', 'ohmylms' ) );
			}
		}
		$id = (int) $request['id'];
		switch ( $name ) {
			case 'get_lesson_for_edit':
				$result = ContentAuthoring::read( 'lesson', $id );
				break;
			case 'get_question_for_edit':
				$result = ContentAuthoring::read( 'question', $id );
				break;
			case 'save_lesson':
			case 'save_question':
				$result = ContentAuthoring::save( 'save_lesson' === $name ? 'lesson' : 'question', $data );
				break;
			case 'set_lesson_objectives':
				$result = LessonObjectives::save( $id, $data );
				break;
			case 'save_lesson_alignment_review':
				$result = LessonObjectives::review( $id, $data );
				break;
			default:
				$result = LessonObjectives::read( $id );
		}
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
