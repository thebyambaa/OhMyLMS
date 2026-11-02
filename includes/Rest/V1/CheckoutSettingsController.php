<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * CheckoutSettingsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Settings\CheckoutSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/settings/checkout`: gateway order, guest checkout, checkout login form, content
 * protection and the read-only Stripe status. Requires {@see SettingsController::CAPABILITY}.
 * See docs/api/settings.md#checkout.
 *
 * @since 1.0.0
 */
class CheckoutSettingsController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings/checkout';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Read permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( SettingsController::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_settings_cannot_view', __( 'Sorry, you are not allowed to view settings.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Write permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( SettingsController::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_settings_cannot_edit', __( 'Sorry, you are not allowed to change settings.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Response with the current settings.
	 *
	 * @param string[] $changed Changed field names.
	 * @return WP_REST_Response
	 */
	private function respond( array $changed ) {
		$data            = CheckoutSettings::read();
		$data['changed'] = $changed;
		$response        = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Read the checkout settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return $this->respond( array() );
	}

	/**
	 * Partially update the checkout settings, all or nothing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$plan = CheckoutSettings::validate( $request->get_json_params() );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$changed = CheckoutSettings::write( $plan );
		if ( is_wp_error( $changed ) ) {
			return $changed;
		}
		return $this->respond( $changed );
	}
}
