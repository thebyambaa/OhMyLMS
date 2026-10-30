<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Order refund REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Rest\V1;

use OhMyLMS\Commerce\Abstracts\RestController;
use OhMyLMS\Commerce\Data\OrderRefund;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Refunds of one order under `ohmylms-commerce/v1/orders/{order_id}/refunds`.
 *
 * Refunds move money, so creating one is guarded: administrator capability, an order that was paid
 * and is not refunded or cancelled, a gateway that is allowed to refund (offline only by default,
 * see the `ohmylms_commerce_refundable_gateways` filter), an amount within the remaining
 * refundable total, a mandatory reason, and a per-order lock against double submission.
 *
 * @since 1.0.0
 */
class OrderRefundController extends RestController {

	/**
	 * Order statuses that can be refunded.
	 */
	const REFUNDABLE_STATUSES = array( 'processing', 'on-hold', 'completed' );

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'orders/(?P<order_id>[\d]+)/refunds';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args'   => array(
					'order_id' => array(
						'description' => __( 'The order ID.', 'ohmylms' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'amount'            => array(
							'description' => __( 'Amount to refund, a positive decimal in the order currency.', 'ohmylms' ),
							'type'        => array( 'string', 'number' ),
							'required'    => true,
						),
						'reason'            => array(
							'description'       => __( 'Why the order is refunded.', 'ohmylms' ),
							'type'              => 'string',
							'required'          => true,
							'minLength'         => 1,
							'maxLength'         => 500,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'cancel_enrollment' => array(
							'description' => __( 'Remove the student enrolment when the order is fully refunded.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Permission: list refunds.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_refund_cannot_view', __( 'Sorry, you are not allowed to view refunds.', 'ohmylms' ) );
	}

	/**
	 * Permission: create a refund.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_refund_cannot_create', __( 'Sorry, you are not allowed to refund orders.', 'ohmylms' ) );
	}

	/**
	 * Load an order.
	 *
	 * @param int $id Order ID.
	 * @return object|WP_Error
	 */
	private function get_order( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || OrdersController::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return new WP_Error( 'ohmylms_order_not_found', __( 'Order not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return ohmylms_ecommerce_get_order( $post->ID );
	}

	/**
	 * List the refunds of an order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$order = $this->get_order( $request['order_id'] );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$items = array();
		foreach ( $order->get_refunds() as $post ) {
			$items[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $post, $request ) );
		}
		return rest_ensure_response( $items );
	}

	/**
	 * Create a refund.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$order = $this->get_order( $request['order_id'] );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$decimals = ohmylms_get_price_decimals();
		$amount   = $request['amount'];
		if ( ! is_numeric( $amount ) || (float) $amount <= 0 ) {
			return new WP_Error(
				'ohmylms_refund_amount_invalid',
				__( 'The refund amount must be greater than zero.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'amount',
				)
			);
		}
		$amount = round( (float) $amount, $decimals );

		$lock = 'refund-' . $order->get_id();
		if ( ! $this->acquire_lock( $lock ) ) {
			return new WP_Error( 'ohmylms_refund_in_progress', __( 'A refund for this order is already being processed.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		try {
			clean_post_cache( $order->get_id() );
			$order = ohmylms_ecommerce_get_order( $order->get_id() );

			if ( ! in_array( $order->get_status( 'edit' ), self::REFUNDABLE_STATUSES, true ) ) {
				return new WP_Error( 'ohmylms_refund_order_status', __( 'Only paid orders that are not refunded or cancelled can be refunded.', 'ohmylms' ), array( 'status' => 409 ) );
			}
			$gateways = (array) apply_filters( 'ohmylms_commerce_refundable_gateways', array( 'offline' ) );
			if ( ! in_array( $order->get_payment_method(), $gateways, true ) ) {
				return new WP_Error( 'ohmylms_refund_gateway_unsupported', __( 'Refunds for this payment method must be made in the payment provider.', 'ohmylms' ), array( 'status' => 409 ) );
			}
			if ( $amount > (float) $order->get_remaining_refund_amount() ) {
				return new WP_Error(
					'ohmylms_refund_exceeds_remaining',
					__( 'The refund is larger than the amount still refundable.', 'ohmylms' ),
					array(
						'status' => 422,
						'field'  => 'amount',
					)
				);
			}

			$refund = ohmylms_ecommerce_create_refund(
				array(
					'order_id'          => $order->get_id(),
					'amount'            => $amount,
					'reason'            => $request['reason'],
					'refund_payment'    => false,
					'restock_items'     => false,
					'cancel_enrollment' => (bool) $request['cancel_enrollment'],
				)
			);
			if ( is_wp_error( $refund ) || ! $refund ) {
				return new WP_Error( 'ohmylms_refund_failed', is_wp_error( $refund ) ? $refund->get_error_message() : __( 'The refund could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
			}

			$post = get_post( $refund->get_id() );
			do_action( 'ohmylms_rest_insert_ohmylms_order_refund', $post, $request, true );

			$response = $this->prepare_item_for_response( $post, $request );
			$response->set_status( 201 );
			return $response;
		} finally {
			$this->release_lock( $lock );
		}
	}

	/**
	 * Prepare a refund for the response.
	 *
	 * @param \WP_Post        $item    Refund post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data = array(
			'id'           => (int) $item->ID,
			'order_id'     => (int) $item->post_parent,
			'amount'       => number_format( (float) get_post_meta( $item->ID, '_refund_amount', true ), ohmylms_get_price_decimals(), '.', '' ),
			'reason'       => (string) get_post_meta( $item->ID, '_refund_reason', true ),
			'date_created' => str_replace( ' ', 'T', $item->post_date ),
		);
		return rest_ensure_response( $data );
	}

	/**
	 * Refund schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->add_additional_fields_schema(
			array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'order_refund',
				'type'       => 'object',
				'properties' => array(
					'id'           => array(
						'type'     => 'integer',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'order_id'     => array(
						'type'     => 'integer',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'amount'       => array(
						'description' => __( 'Refunded amount as a decimal string.', 'ohmylms' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
					'reason'       => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'date_created' => array(
						'type'     => 'string',
						'format'   => 'date-time',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
				),
			)
		);
	}
}
