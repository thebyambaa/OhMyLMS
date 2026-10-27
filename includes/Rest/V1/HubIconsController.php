<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Administrator endpoint for the learner hub's activity icons.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Design\HubIcons;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and saves the custom icon for each activity kind.
 */
class HubIconsController extends RestController {

	/** Register the routes. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/hub-icons',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'read' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * The hub is shared by every learner, so only administrators change its icons.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Describe the kinds and their current icons.
	 *
	 * @param array<string,int>|null $icons Attachment IDs to describe; the saved ones when null.
	 * @return \WP_REST_Response
	 */
	private function snapshot( $icons = null ) {
		$icons = $icons ?? HubIcons::get();
		$kinds = array();
		$logos = HubIcons::logo_kinds();
		foreach ( HubIcons::all_kinds() as $key => $label ) {
			$id      = $icons[ $key ] ?? 0;
			$kinds[] = array(
				'key'   => $key,
				'group' => isset( $logos[ $key ] ) ? 'logo' : 'activity',
				'label' => $label,
				'id'    => $id,
				'url'   => $id ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '',
			);
		}
		return rest_ensure_response( array( 'kinds' => $kinds ) );
	}

	/**
	 * Read the icons.
	 *
	 * @return \WP_REST_Response
	 */
	public function read() {
		return $this->snapshot();
	}

	/**
	 * Save the icons; a kind that is missing, zero or not an image uses the built-in icon.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save( $request ) {
		$input = $request->get_json_params();
		$input = is_array( $input ) && isset( $input['icons'] ) ? $input['icons'] : array();
		return $this->snapshot( HubIcons::save( $input ) );
	}
}
