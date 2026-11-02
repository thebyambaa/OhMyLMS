<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * CartController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Ajax;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Learner storefront routes used by the `ohmylms/cart` and `ohmylms/checkout` modules: a thin REST
 * wrapper over the add-to-cart service the `ohmylms_add_to_cart` AJAX action uses, and the states
 * list of a country. Open to visitors like the AJAX actions; cookie auth verifies X-WP-Nonce.
 *
 * @since 1.2.21
 */
class CartController extends RestController {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms-commerce/v1';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$id = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		register_rest_route(
			$this->namespace,
			'/cart/items',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'course_id'     => $id,
						'membership_id' => $id,
						'quantity'      => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 1,
						),
						'purchase_by'   => array(
							'type'    => 'string',
							'enum'    => array( '', 'point' ),
							'default' => '',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/countries/(?P<country>[A-Za-z]{2})/states',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'states' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Add a course or membership plan to the visitor's cart.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_item( $request ) {
		nocache_headers();
		$quantity = (int) $request['quantity'];
		if ( ! empty( $request['membership_id'] ) ) {
			$result = Ajax::add_membership_to_cart( (int) $request['membership_id'], $quantity );
		} elseif ( ! empty( $request['course_id'] ) ) {
			$result = Ajax::add_course_to_cart( (int) $request['course_id'], $quantity, (string) $request['purchase_by'] );
		} else {
			return new WP_Error( 'ohmylms_cart_missing_item', __( 'Choose a course or membership plan.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( $result['body'], $result['status'] );
	}

	/**
	 * States/provinces of a country (`[{code, title}]`, empty when the country has none).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function states( $request ) {
		$states = function_exists( 'ohmylms_get_states' ) ? ohmylms_get_states( strtoupper( (string) $request['country'] ) ) : array();
		return new WP_REST_Response( array_values( is_array( $states ) ? $states : array() ) );
	}
}
