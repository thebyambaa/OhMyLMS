<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Learner overlays on the skill map.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Dashboard\SkillMapOverlay;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /skill-map/overlay` (a learner, class or school's mastery on a subject's shared skills) and
 * `GET /skill-map/overlay/targets` (who can be laid over the map). Curriculum administrators and
 * dashboard staff may call them; staff only reach the learners of their schools and classes
 * ({@see SkillMapOverlay}). See docs/api/curriculum-workspace.md, "Learner overlays".
 */
class SkillMapOverlayController extends RestController {

	/** Register the routes. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/skill-map/overlay',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'overlay' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'subject' => array(
							'description' => __( 'Subject of the skill map.', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
							'minLength'   => 1,
							'maxLength'   => 190,
						),
						'target'  => array(
							'description' => __( 'Who to show: "learner:<id>", "class:<id>" or "school:<id>".', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
							'pattern'     => SkillMapOverlay::TARGET_PATTERN,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/skill-map/overlay/targets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'targets' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'search' => array(
							'description' => __( 'Part of a class, school or learner name.', 'ohmylms' ),
							'type'        => 'string',
							'default'     => '',
							'maxLength'   => 100,
						),
					),
				),
			)
		);
	}

	/**
	 * Curriculum administrators and users with dashboard access.
	 *
	 * @return true|WP_Error
	 */
	public function permissions_check() {
		$user_id = get_current_user_id();
		return SkillMapOverlay::can_view( $user_id ) ? true : SkillMapOverlay::denied( $user_id );
	}

	/**
	 * Mastery of the target on the subject's skills.
	 *
	 * @param WP_REST_Request $request subject and target.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function overlay( $request ) {
		try {
			$overlay = SkillMapOverlay::overlay( get_current_user_id(), (string) $request['subject'], (string) $request['target'] );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'ohmylms_skill_overlay_failed', __( 'Learner mastery could not be loaded.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return is_wp_error( $overlay ) ? $overlay : $this->private_response( $overlay );
	}

	/**
	 * Groups and learners matching a search.
	 *
	 * @param WP_REST_Request $request search.
	 * @return \WP_REST_Response
	 */
	public function targets( $request ) {
		return $this->private_response( SkillMapOverlay::targets( get_current_user_id(), (string) $request['search'] ) );
	}

	/**
	 * A response proxies must not store.
	 *
	 * @param array $data Data.
	 * @return \WP_REST_Response
	 */
	private function private_response( array $data ) {
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}
}
