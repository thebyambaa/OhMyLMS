<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Internal REST routes behind the skill assessment MCP tools.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\SkillAssessment;
use OhMyLMS\MCP\Server;
use OhMyLMS\MCP\SkillAssessmentTools;

defined( 'ABSPATH' ) || exit;

/** One validated contract for the REST routes and the native abilities. */
class SkillAssessmentToolsController extends RestController {

	/** Register each concrete operation without exposing arbitrary dispatch. */
	public function register_routes() {
		foreach ( SkillAssessmentTools::definitions() as $name => $tool ) {
			$args = $tool['properties'];
			foreach ( $args as $key => &$schema ) {
				$schema['required'] = in_array( $key, $tool['required'], true );
			}
			unset( $schema );
			register_rest_route(
				$this->namespace,
				$tool['route'],
				array(
					'methods'             => $tool['method'],
					'args'                => $args,
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => function ( $request ) use ( $name ) {
						return $this->dispatch( $name, $request );
					},
				)
			);
		}
	}

	/**
	 * Administrator access, as for the other MCP authoring routes.
	 *
	 * @return true|\WP_Error
	 */
	public function permission() {
		return Server::can_manage() ? true : new \WP_Error( 'ohmylms_forbidden', __( 'Administrator access is required.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Run one known operation.
	 *
	 * @param string           $name    Tool name.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function dispatch( $name, $request ) {
		if ( 'list_unit_skills' === $name ) {
			return rest_ensure_response( SkillAssessment::unit_skills( absint( $request->get_param( 'chapter_id' ) ) ) );
		}
		$result = SkillAssessment::create(
			array(
				'title'      => (string) $request->get_param( 'title' ),
				'style'      => (string) $request->get_param( 'style' ),
				'skills'     => (array) $request->get_param( 'skills' ),
				'per_skill'  => (int) $request->get_param( 'per_skill' ),
				'chapter_id' => absint( $request->get_param( 'chapter_id' ) ),
				'time_limit' => absint( $request->get_param( 'time_limit' ) ),
				// Created by a tool: always a draft for a person to review and publish.
				'publish'    => false,
			)
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
