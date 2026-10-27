<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Order Refund Controller class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Rest\V1;

use OhMyLMS\Commerce\Abstracts\RestController;
use OhMyLMS\Commerce\Data\OrderRefund;

/**
 * Order Refund Controller class.
 *
 * @since 1.0.0
 */
class OrderRefundController extends RestController {

	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'orders/(?P<order_id>[\d]+)/refunds';

	/**
	 * Create item permissions check for Order refund controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register routes for Order refund controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				'args'   => array(
					'order_id' => array(
						'description' => __( 'The order ID.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}


	/**
	 * Create a refund item.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error The response object or WP_Error on failure.
	 *
	 * @since 1.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new \WP_Error( 'ohmylms_rest_ohmylms_order_refund_exists', /* translators: %s: text value. */
			__( 'Cannot create existing %s.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$order_data = get_post( (int) $request['order_id'] );
		$response   = array();
		if ( empty( $order_data ) ) {
			$response['message'] = __( 'Invalid order ID.', 'ohmylms' );
			$response['success'] = false;
			return rest_ensure_response( $response );
		}

		if ( 0 > $request['amount'] ) {
			$response['message'] = __( 'Refund amount must be greater than zero.', 'ohmylms' );
			$response['success'] = false;
			return rest_ensure_response( $response );
		}

		if ( empty( $request['reason'] ) ) {
			$response['message'] = __( 'Refund reason is required.', 'ohmylms' );
			$response['success'] = false;
			return rest_ensure_response( $response );
		}

		// Create the refund.
		$refund = ohmylms_ecommerce_create_refund(
			array(
				'order_id'          => $order_data->ID,
				'amount'            => $request['amount'],
				'reason'            => empty( $request['reason'] ) ? null : $request['reason'],
				'refund_payment'    => is_bool( $request['api_refund'] ) ? $request['api_refund'] : true,
				'restock_items'     => true,
				'cancel_enrollment' => empty( $request['cancel_enrollment'] ) ? null : $request['cancel_enrollment'],
			)
		);

		if ( is_wp_error( $refund ) ) {
			$response['message'] = $refund->get_error_message();
			$response['success'] = false;
			return rest_ensure_response( $response );
		}

		if ( ! $refund ) {
			$response['message'] = __( 'Cannot create order refund, please try again.', 'ohmylms' );
			$response['success'] = false;
			return rest_ensure_response( $response );
		}

		$post = get_post( $refund->get_id() );
		$this->update_additional_fields_for_object( $post, $request );

		/**
		 * Fires after a single item is created or updated via the REST API.
		 *
		 * @param /WP_Post         $post      Post object.
		 * @param /WP_REST_Request $request   Request object.
		 * @param boolean         $creating  True when creating item, false when updating.
		 */
		do_action( 'ohmylms_rest_insert_ohmylms_order_refund', $post, $request, true );

		$response = $this->prepare_item_for_response( $post, $request );
		return rest_ensure_response( $response );
	}

	/**
	 * Prepare a single refund item for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error The response object or WP_Error on failure.
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {
		$order = ohmylms_ecommerce_get_order( (int) $request['order_id'] );

		if ( ! $order ) {
			return new \WP_Error( 'ohmylms_rest_invalid_order_id', __( 'Invalid order ID.', 'ohmylms' ), 404 );
		}

		$refund = new OrderRefund( $post->ID );

		if ( ! $refund || $refund->get_parent_id() !== $order->get_id() ) {
			return new \WP_Error( 'ohmylms_rest_invalid_order_refund_id', __( 'Invalid order refund ID.', 'ohmylms' ), 404 );
		}

		$data = array(
			'id'           => $refund->get_id(),
			'date_created' => ohmylms_ecommerce_rest_prepare_date_response( $refund->get_date_created() ),
			'amount'       => ohmylms_format_decimal( $refund->get_amount(), ohmylms_get_price_decimals() ),
			'reason'       => $refund->get_reason(),
		);

		$data = $this->add_additional_fields_to_object( $data, $request );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $refund, $request ) );

		return $response;
	}

	/**
	 * Prepare links for the refund object.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderRefund $refund The refund object.
	 * @param \WP_REST_Request                   $request The request object.
	 * @return array Links for the given refund.
	 */
	protected function prepare_links( $refund, $request ) {
		$order_id = $refund->get_parent_id();
		$base     = str_replace( '(?P<order_id>[\d]+)', $order_id, $this->base );
		$links    = array(
			'self'       => array(
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $base, $refund->get_id() ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $base ) ),
			),
			'up'         => array(
				'href' => rest_url( sprintf( '/%s/orders/%d', $this->namespace, $order_id ) ),
			),
		);

		return $links;
	}
}
