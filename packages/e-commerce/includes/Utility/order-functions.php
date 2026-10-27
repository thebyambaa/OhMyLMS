<?php
/**
 * Order functions.
 *
 * @package OhMyLMS
 */


/**
 * Retrieve an order by its ID.
 *
 * @param int $order_id The ID of the order to retrieve.
 * @return \OhMyLMS\Commerce\Data\Order|bool The order object if found, false otherwise.
 */
function ohmylms_ecommerce_get_order( $order_id ) {
	return \OhMyLMS\Commerce\ecommerce()->order_factory->get_order( $order_id );
}


/**
 * Ohmylms ecommerce get refund order for Order-functions.
 *
 * @param mixed $order_id Order identifier.
 */
function ohmylms_ecommerce_get_refund_order( $order_id ) {
	return \OhMyLMS\Commerce\ecommerce()->order_factory->get_refund_order( $order_id );
}


/**
 * Create a refund for an order.
 *
 * @param array $args {
 *     Arguments for creating a refund.
 * .
 *     @type int    $amount         Refund amount.
 *     @type string $reason         Reason for the refund.
 *     @type int    $order_id       Order ID.
 *     @type int    $refund_id      Refund ID.
 *     @type array  $line_items     Line items to refund.
 *     @type bool   $refund_payment Whether to refund the payment.
 *     @type bool   $restock_items  Whether to restock the items.
 * }
 * @return \WC_Order_Refund|\WP_Error The refund object on success, WP_Error on failure.
 * @throws \Exception When the operation cannot be completed.
 */
function ohmylms_ecommerce_create_refund( $args = array() ) {
	$default_args = array(
		'amount'            => 0,
		'reason'            => null,
		'order_id'          => 0,
		'refund_id'         => 0,
		'line_items'        => array(),
		'refund_payment'    => false,
		'restock_items'     => false,
		'cancel_enrollment' => false,
	);

	try {
		$args  = wp_parse_args( $args, $default_args );
		$order = ohmylms_ecommerce_get_order( $args['order_id'] );
		if ( ! $order ) {
			throw new \Exception( __( 'Invalid order ID.', 'ohmylms' ) );
		}

		$remaining_refund_amount    = $order->get_remaining_refund_amount();
		$remaining_refund_items     = $order->get_remaining_refund_items();
		$refund_item_count          = 0;
		$refund                     = new \OhMyLMS\Commerce\Data\OrderRefund( $args['refund_id'] );
		$refunded_order_and_courses = array();

		if ( 0 > $args['amount'] || $args['amount'] > $remaining_refund_amount ) {
			throw new \Exception( __( 'Invalid refund amounts.', 'ohmylms' ) );
		}

		$refund->set_currency( $order->get_currency() );
		$refund->set_amount( $args['amount'] );
		$refund->set_parent_id( absint( $args['order_id'] ) );
		$refund->set_refunded_by( get_current_user_id() ? get_current_user_id() : 1 );
		$refund->set_cancel_enrollment( true == $args['cancel_enrollment'] );

		if ( ! is_null( $args['reason'] ) ) {
			$refund->set_reason( $args['reason'] );
		}

		// Negative line items.
		if ( is_array( $args['line_items'] ) && count( $args['line_items'] ) > 0 ) {
			$items = $order->get_items( array( 'line_item' ) );

			foreach ( $items as $item_id => $item ) {
				if ( ! isset( $args['line_items'][ $item_id ] ) ) {
					continue;
				}

				$qty          = isset( $args['line_items'][ $item_id ]['quantity'] ) ? $args['line_items'][ $item_id ]['quantity'] : 0;
				$refund_total = $args['line_items'][ $item_id ]['refund_total'];

				if ( empty( $qty ) && empty( $refund_total ) && empty( $args['line_items'][ $item_id ]['refund_tax'] ) ) {
					continue;
				}

				if ( $item->is_type( 'line_item' ) ) {
					$refunded_order_and_courses[ $item_id ] = array(
						'order_id'  => $order->get_id(),
						'course_id' => $item->get_course_id(),
					);
				}

				$class         = get_class( $item );
				$refunded_item = new $class( $item );
				$refunded_item->set_id( 0 );
				$refunded_item->add_meta_data( '_refunded_item_id', $item_id, true );
				$refunded_item->set_total( ohmylms_ecommerce_format_refund_total( $refund_total ) );

				if ( is_callable( array( $refunded_item, 'set_subtotal' ) ) ) {
					$refunded_item->set_subtotal( ohmylms_ecommerce_format_refund_total( $refund_total ) );
				}

				if ( is_callable( array( $refunded_item, 'set_quantity' ) ) ) {
					$refunded_item->set_quantity( $qty * -1 );
				}
				$refund->add_item( $refunded_item );
			}
		}

		$refund->calculate_totals();
		$refund->set_total( $args['amount'] * -1 );

		$result = ohmylms_ecommerce_refund_payment( $order, $refund->get_amount(), $refund->get_reason() );
		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		$refund->set_refunded_payment( true );
		$refund->save();

		do_action( 'ohmylms_order_refunded', $refund, $order );

		if ( ( $remaining_refund_amount - $args['amount'] ) > 0 ) {

		} else {
			$order->update_status( 'refunded' );
		}

		$order->set_date_modified( time() );
		$current_user_id = get_current_user_id();
		$display_name    = $current_user_id ? get_user_by( 'id', $current_user_id )->display_name : __( 'System', 'ohmylms' );
		$refund_note     = sprintf(
			/* translators: %s: refund reason */
			__( 'Order is refunded by %1$s.', 'ohmylms' ),
			$display_name
		);
		if ( ! empty( $args['reason'] ) ) {
			$refund_note .= sprintf(
				/* translators: %s: refund reason */
				__( ' Reason: %s', 'ohmylms' ),
				$args['reason']
			);
		}
		$order->add_order_note( $refund_note );
		$order->save();

	} catch ( Exception $e ) {
		return new \WP_Error( 'error', $e->getMessage() );
	}

	return $refund;
}


/**
 * Process a refund payment.
 *
 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
 * @param float                        $amount The amount to refund.
 * @param string                       $reason The reason for the refund.
 * @return bool|\WP_Error True on success, WP_Error on failure.
 *
 * @since 1.0.0
 * @throws \Exception When the operation cannot be completed.
 */
function ohmylms_ecommerce_refund_payment( $order, $amount, $reason = '' ) {
	try {
		$gateway_controller = \OhMyLMS\Commerce\Gateways\Gateways::instance();
		$gateways           = $gateway_controller->get_payment_gateways();
		$payment_method     = $order->get_payment_method();
		$gateway            = isset( $gateways[ $payment_method ] ) ? $gateways[ $payment_method ] : false;
		if ( ! $gateway ) {
			throw new \Exception( __( 'The payment gateway for this order does not exist.', 'ohmylms' ) );
		}

		if ( 'offline' !== $gateway->id ) {
			$result = $gateway->process_refund( $order->get_id(), $amount, $reason );
			if ( is_array( $result ) && isset( $result['result'] ) && 'failure' === $result['result'] ) {
				throw new \Exception( $result['message'] ? $result['message'] : __( 'An error occurred while attempting to create the refund using the payment gateway API.', 'ohmylms' ) );
			}
			if ( false === $result ) {
				throw new \Exception( __( 'An error occurred while attempting to create the refund using the payment gateway API.', 'ohmylms' ) );
			}
		} else {
			// Add order note about the refund.
			$order->add_order_note(
				sprintf(
					/* translators: %s: text value. */
					__( 'Refund processed.Reason: %s', 'ohmylms' ),
					$reason ? $reason : __( 'No reason provided', 'ohmylms' )
				)
			);
			$order->save();
		}
		return true;

	} catch ( Exception $e ) {
		return new WP_Error( 'error', $e->getMessage() );
	}
}

/**
 * Retrieve all order statuses.
 *
 * @return array An associative array of order statuses with keys as status codes and values as status labels.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_get_order_statuses() {
	$order_statuses = array(
		'ohmylms-pending'    => _x( 'Pending', 'Order status', 'ohmylms' ),
		'ohmylms-processing' => _x( 'Processing', 'Order status', 'ohmylms' ),
		'ohmylms-on-hold'    => _x( 'On hold', 'Order status', 'ohmylms' ),
		'ohmylms-completed'  => _x( 'Completed', 'Order status', 'ohmylms' ),
		'ohmylms-cancelled'  => _x( 'Cancelled', 'Order status', 'ohmylms' ),
		'ohmylms-refunded'   => _x( 'Refunded', 'Order status', 'ohmylms' ),
		'ohmylms-failed'     => _x( 'Failed', 'Order status', 'ohmylms' ),
	);
	return $order_statuses;
}

/**
 * Retrieve the name of an order status.
 *
 * @param string $status The status code.
 * @return string The status label.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_get_order_status_name( $status ) {
	$statuses = ohmylms_ecommerce_get_order_statuses();
	if ( strpos( $status, 'ohmylms-' ) === 0 ) {
		$status = substr( $status, 6 );
	}
	return $statuses[ 'ohmylms-' . $status ] ?? $status;
}


/**
 * Ohmylms ecommerce get order notes for Order-functions.
 *
 * @param mixed $args Operation arguments.
 */
function ohmylms_ecommerce_get_order_notes( $args ) {
	$key_mapping = array(
		'limit'         => 'number',
		'order_id'      => 'post_id',
		'order__in'     => 'post__in',
		'order__not_in' => 'post__not_in',
	);

	foreach ( $key_mapping as $query_key => $db_key ) {
		if ( isset( $args[ $query_key ] ) ) {
			$args[ $db_key ] = $args[ $query_key ];
			unset( $args[ $query_key ] );
		}
	}

	// Define orderby.
	$orderby_mapping = array(
		'date_created'     => 'comment_date',
		'date_created_gmt' => 'comment_date_gmt',
		'id'               => 'comment_ID',
	);

	$args['orderby'] = ! empty( $args['orderby'] ) && in_array( $args['orderby'], array( 'date_created', 'date_created_gmt', 'id' ), true ) ? $orderby_mapping[ $args['orderby'] ] : 'comment_ID';

	if ( isset( $args['type'] ) && 'customer' === $args['type'] ) {
		$args['meta_query'] = array( // WPCS: slow query ok.
			array(
				'key'     => 'is_customer_note',
				'value'   => 1,
				'compare' => '=',
			),
		);
	} elseif ( isset( $args['type'] ) && 'internal' === $args['type'] ) {
		$args['meta_query'] = array( // WPCS: slow query ok.
			array(
				'key'     => 'is_customer_note',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	// Set correct comment type.
	$args['type'] = 'order_note';

	// Always approved.
	$args['status'] = 'approve';

	// Does not support 'count' or 'fields'.
	unset( $args['count'], $args['fields'] );

	remove_filter( 'comments_clauses', array( 'OhMyLMS\Commerce\Comments', 'exclude_order_comments' ), 10, 1 );
	$notes = get_comments( $args );
	add_filter( 'comments_clauses', array( 'OhMyLMS\Commerce\Comments', 'exclude_order_comments' ), 10, 1 );

	return array_filter( array_map( 'ohmylms_ecommerce_get_order_note', $notes ) );
}


/**
 * Retrieve a single order note by ID or convert comment data to order note object.
 *
 * @param int|WP_Comment $data Comment ID or WP_Comment object.
 * @return object|null Order note object or null if not found/invalid.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_get_order_note( $data ) {
	if ( is_numeric( $data ) ) {
		$data = get_comment( $data );
	}

	if ( ! is_a( $data, 'WP_Comment' ) ) {
		return null;
	}

	// Ensure this is actually an order note.
	if ( 'order_note' !== $data->comment_type ) {
		return null;
	}

	return (object) array(
		'id'            => (int) $data->comment_ID,
		'date_created'  => ohmylms_ecommerce_string_to_datetime( $data->comment_date ),
		'content'       => $data->comment_content,
		'customer_note' => (bool) get_comment_meta( $data->comment_ID, 'is_customer_note', true ),
		'added_by'      => __( 'OhMyLMS', 'ohmylms' ) === $data->comment_author ? 'system' : $data->comment_author,
	);
}
