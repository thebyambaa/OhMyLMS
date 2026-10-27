<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Administrator endpoint for the backend design system.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Design\DesignSystem;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, saves and resets the backend design system (colors, button roundness, density).
 */
class DesignSystemController extends RestController {

	/** Register the routes. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/design-system',
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
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'reset' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Only administrators change the look of every admin screen. Cookie requests also need the REST nonce.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The current settings, the presets and the contrast verdict for the primary color.
	 *
	 * @param array|null $settings Settings to describe; the saved ones when null.
	 * @return \WP_REST_Response
	 */
	private function snapshot( $settings = null ) {
		$settings = $settings ?? DesignSystem::get();
		$primary  = $settings['colors']['primary'];
		return rest_ensure_response(
			array_merge(
				$settings,
				array(
					'on_primary'     => DesignSystem::text_on( $primary ),
					'primary_passes' => DesignSystem::passes_aa( $primary ),
					'white_passes'   => DesignSystem::white_passes_aa( $primary ),
					'presets'        => array_map(
						static function ( $name ) {
							return array_merge( DesignSystem::preset_values( $name ), array( 'label' => DesignSystem::PRESETS[ $name ]['label'] ) );
						},
						array_keys( DesignSystem::PRESETS )
					),
				)
			)
		);
	}

	/**
	 * Read the settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function read() {
		return $this->snapshot();
	}

	/**
	 * Save the settings; invalid fields are reported by name and nothing is stored.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( $request ) {
		$input  = $request->get_json_params();
		$input  = is_array( $input ) ? $input : $request->get_body_params();
		$errors = DesignSystem::validate( $input );
		if ( $errors ) {
			return new \WP_Error(
				'ohmylms_invalid_design',
				__( 'Some design settings are not valid.', 'ohmylms' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}
		return $this->snapshot( DesignSystem::save( $input ) );
	}

	/**
	 * Go back to the Khan Academy defaults.
	 *
	 * @return \WP_REST_Response
	 */
	public function reset() {
		return $this->snapshot( DesignSystem::save( DesignSystem::preset_values( DesignSystem::DEFAULT_PRESET ) ) );
	}
}
