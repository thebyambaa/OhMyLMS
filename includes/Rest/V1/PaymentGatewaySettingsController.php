<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * PaymentGatewaySettingsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Settings\PaymentGatewaySettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST API for payment gateway settings under `ohmylms/v1/settings/payment-gateways`.
 *
 * Credentials are write-only (see {@see PaymentGatewaySettings}). Every route requires
 * {@see SettingsController::CAPABILITY}. See docs/api/settings.md.
 *
 * @since 1.0.0
 */
class PaymentGatewaySettingsController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings/payment-gateways';

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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<gateway>[a-z0-9_-]+)',
			array(
				'args' => array(
					'gateway' => array(
						'description' => __( 'Payment gateway id.', 'ohmylms' ),
						'type'        => 'string',
						'pattern'     => '^[a-z0-9_-]+$',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
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
	 * Check that the user may read gateway settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( SettingsController::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_settings_cannot_view', __( 'Sorry, you are not allowed to view settings.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check that the user may change gateway settings.
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
	 * List every registered gateway.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( PaymentGatewaySettings::gateways() as $gateway ) {
			$data[] = PaymentGatewaySettings::read( PaymentGatewaySettings::definition( $gateway ) );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Definition of the requested gateway.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	private function definition( $request ) {
		$gateways = PaymentGatewaySettings::gateways();
		$id       = (string) $request['gateway'];
		if ( ! isset( $gateways[ $id ] ) ) {
			return new WP_Error( 'ohmylms_settings_gateway_not_found', __( 'Payment gateway not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		return PaymentGatewaySettings::definition( $gateways[ $id ] );
	}

	/**
	 * Build the response for one gateway.
	 *
	 * @param array    $definition Gateway definition.
	 * @param string[] $changed    Changed names.
	 * @return WP_REST_Response
	 */
	private function gateway_response( array $definition, array $changed ) {
		$data            = PaymentGatewaySettings::read( $definition );
		$data['changed'] = $changed;
		$response        = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'no-store' );
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $definition['id'] ) );

		return $response;
	}

	/**
	 * Read one gateway.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$definition = $this->definition( $request );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}

		return $this->gateway_response( $definition, array() );
	}

	/**
	 * Partially update one gateway; everything not sent is kept.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$definition = $this->definition( $request );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}
		$plan = PaymentGatewaySettings::validate( $definition, $request->get_json_params() );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$changed = PaymentGatewaySettings::write( $definition, $plan );
		if ( is_wp_error( $changed ) ) {
			return $changed;
		}

		return $this->gateway_response( $definition, $changed );
	}
}
