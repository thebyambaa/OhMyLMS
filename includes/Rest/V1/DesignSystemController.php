<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Administrator endpoint for the backend design system.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Design\DesignSystem;
use OhMyLMS\Design\Mascot;
use OhMyLMS\Design\Tokens;

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
		register_rest_route(
			$this->namespace,
			'/design-system/schools/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_school' ),
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
	 * The current settings, the presets, the contrast verdict for the primary color and the colors admin
	 * screens use under the WordPress preset (the requesting user's admin colour scheme).
	 *
	 * @param array|null $settings Settings to describe; the saved ones when null.
	 * @return \WP_REST_Response
	 */
	private function snapshot( $settings = null ) {
		$settings = $settings ?? DesignSystem::get();
		$primary  = $settings['colors']['primary'];
		$scheme   = DesignSystem::admin_scheme();
		$mascot   = Mascot::get();
		return rest_ensure_response(
			array_merge(
				$settings,
				array(
					'on_primary'     => DesignSystem::text_on( $primary ),
					'primary_passes' => DesignSystem::passes_aa( $primary ),
					'white_passes'   => DesignSystem::white_passes_aa( $primary ),
					'admin_scheme'   => $scheme,
					'admin_colors'   => DesignSystem::admin_colors( $scheme ),
					'font'           => Tokens::font( 'ohmylms_font_family' ),
					'fonts'          => Tokens::font_choices( 'ohmylms_font_family' ),
					'mascot'         => $this->mascot_payload( $mascot ),
					'mascots'        => Mascot::catalog(),
					'schools'        => $this->schools(),
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
	 * Mascot plus the uploaded image URL, for the settings screen.
	 *
	 * @param array $mascot Clean mascot.
	 * @return array
	 */
	private function mascot_payload( array $mascot ) {
		return $mascot + array( 'attachment_url' => Mascot::image_url( $mascot ) );
	}

	/**
	 * Schools an administrator can brand, with the overrides already stored.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function schools() {
		if ( \OhMyLMS\Schools\Schema::VERSION !== get_option( 'ohmylms_school_schema' ) ) {
			return array();
		}
		try {
			$rows = \OhMyLMS\Schools\Service::schools( 1 );
		} catch ( \Throwable $error ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A missing school table leaves the branding screen usable.
			return array();
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			$id = absint( $row['id'] ?? 0 );
			if ( ! $id ) {
				continue;
			}
			$own    = \OhMyLMS\Schools\Settings::overrides( $id );
			$mascot = isset( $own['mascot'] ) && is_array( $own['mascot'] ) ? $this->mascot_payload( $own['mascot'] ) : null;
			$out[]  = array(
				'id'       => $id,
				'name'     => isset( $row['name'] ) ? (string) $row['name'] : '',
				'accent'   => $own['accent'] ?? '',
				'logo_id'  => isset( $own['logo'] ) ? (int) $own['logo'] : 0,
				'logo_url' => isset( $own['logo'] ) ? (string) wp_get_attachment_image_url( (int) $own['logo'], 'thumbnail' ) : '',
				'mascot'   => $mascot,
			);
		}
		return $out;
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
		if ( isset( $input['font'] ) && ( ! is_string( $input['font'] ) || ! in_array( $input['font'], Tokens::font_choices( 'ohmylms_font_family' ), true ) ) ) {
			$errors['font'] = __( 'Choose a learner font from the list.', 'ohmylms' );
		}
		if ( isset( $input['mascot'] ) ) {
			$errors = array_merge( $errors, Mascot::validate( $input['mascot'] ) );
		}
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
		$saved = DesignSystem::save( $input );
		if ( isset( $input['font'] ) ) {
			update_option( 'ohmylms_font_family', $input['font'] );
		}
		if ( isset( $input['mascot'] ) ) {
			Mascot::save( $input['mascot'] );
		}
		return $this->snapshot( $saved );
	}

	/**
	 * Save one school's accent, logo and mascot override.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_school( $request ) {
		$id    = absint( $request['id'] );
		$input = $request->get_json_params();
		$input = is_array( $input ) ? $input : array();
		if ( isset( $input['mascot'] ) && empty( $input['reset_mascot'] ) ) {
			$errors = Mascot::validate( $input['mascot'] );
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
		}
		try {
			\OhMyLMS\Schools\Settings::save( $id, $input );
		} catch ( \RuntimeException $error ) {
			return new \WP_Error(
				'ohmylms_invalid_design',
				$error->getMessage(),
				array( 'status' => 400 )
			);
		}
		return $this->snapshot();
	}

	/**
	 * Go back to the default (WordPress) preset.
	 *
	 * @return \WP_REST_Response
	 */
	public function reset() {
		return $this->snapshot( DesignSystem::save( DesignSystem::preset_values( DesignSystem::DEFAULT_PRESET ) ) );
	}
}
