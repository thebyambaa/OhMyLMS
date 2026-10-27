<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Explicit curriculum-skill to shared-skill mappings. Administrators only: a mapping changes
 * what every learner's combined skill view shows.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\SkillMappings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Explicit curriculum-skill to shared-skill mappings. Administrators only: a mapping changes
 * what every learner's combined skill view shows.
 */
class SkillMappingController extends RestController {
	/**
	 * Register routes for Skill mapping controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/skill-mappings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( Access::class, 'admin' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( Access::class, 'admin' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove' ),
					'permission_callback' => array( Access::class, 'admin' ),
				),
			)
		);
	}

	/**
	 * Index for Skill mapping controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function index( WP_REST_Request $request ) {
		$skill = (int) $request->get_param( 'skill_id' );
		if ( $skill ) {
			return rest_ensure_response( SkillMappings::for_skill( $skill ) ); }
		return rest_ensure_response(
			array(
				'mappings' => array_map(
					static function ( $row ) {
						return array(
							'specific_id' => (int) $row['specific_term_id'],
							'shared_id'   => (int) $row['shared_term_id'],
							'relation'    => $row['relation'],
							'note'        => (string) $row['note'],
						);
					},
					SkillMappings::all()
				),
			)
		);
	}

	/**
	 * Save for Skill mapping controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function save( WP_REST_Request $request ) {
		$mapping = SkillMappings::save( (int) $request->get_param( 'specific_id' ), (int) $request->get_param( 'shared_id' ), (string) ( $request->get_param( 'relation' ) ?: 'equivalent' ), (string) $request->get_param( 'note' ) );
		return is_wp_error( $mapping ) ? $mapping : rest_ensure_response(
			array(
				'mapping'        => $mapping,
				'skill_mappings' => SkillMappings::for_skill( (int) $request->get_param( 'specific_id' ) ),
			)
		);
	}

	/**
	 * Remove for Skill mapping controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function remove( WP_REST_Request $request ) {
		SkillMappings::remove( (int) $request->get_param( 'specific_id' ), (int) $request->get_param( 'shared_id' ) );
		return rest_ensure_response(
			array(
				'removed'        => true,
				'skill_mappings' => SkillMappings::for_skill( (int) $request->get_param( 'specific_id' ) ),
			)
		);
	}
}
