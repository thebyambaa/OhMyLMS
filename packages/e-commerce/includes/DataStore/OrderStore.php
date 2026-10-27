<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\DataStore;

use OhMyLMS\Commerce\Abstracts\DataStore;
use OhMyLMS\Commerce\Data\OrderItemCoupon;
use OhMyLMS\Commerce\Data\OrderItemCourse;

use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;


/**
 * Plugin order store implementation.
 */
class OrderStore extends DataStore {

	/**
	 * Create a new order.
	 *
	 * This function generates a new order key if it is not already set.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object to create.
	 * @since 1.0.0
	 */
	public function create( &$order ) {
		// Hook before order creation.
		do_action( 'ohmylms_before_order_create', $order );

		if ( '' === $order->get_order_key() ) {
			$order->set_order_key( wp_generate_password( 13, false ) );
		}
		$order->set_currency( $order->get_currency() ? $order->get_currency() : get_ohmylms_currency() );
		if ( ! $order->get_date_created( 'edit' ) ) {
			$order->set_date_created( time() );
		}
		$id = wp_insert_post(
			apply_filters(
				'ohmylms_new_order_data',
				array(
					// 'post_date'     => date( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getOffsetTimestamp() ),
					// 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getTimestamp() ),
					'post_type'     => $order->get_post_type(),
					'post_status'   => $this->get_post_status( $order ),
					'ping_status'   => 'closed',
					'post_author'   => get_current_user_id(),
					'post_parent'   => $order->get_parent_id( 'edit' ),
					'post_title'    => $this->get_post_title(),
					'post_password' => wp_generate_password( 13, false ),
				)
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$order->set_id( $id );
			$this->update_post_meta( $order );
		}

		// Hook after order creation.
		do_action( 'ohmylms_after_order_create', $order->get_id(), $order );
		do_action( 'ohmylms_new_order', $order->get_id(), $order );
	}

	/**
	 * Read an order from the database.
	 *
	 * This function retrieves the order data from the database and sets it to the order object.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object to read data into.
	 * @since 1.0.0
	 */
	public function read( &$order ) {
		// Hook before order read.
		do_action( 'ohmylms_before_order_read', $order );

		$post_object = get_post( $order->get_id() );
		if ( ! $order->get_id() || ! $post_object ) {
			return;
		}
		$order->set_props(
			array(
				'parent_id'     => $post_object->post_parent,
				'date_created'  => $post_object->post_date,
				'date_modified' => $post_object->post_modified_gmt,
				'status'        => $post_object->post_status,
			)
		);
		$this->read_order_data( $order, $post_object );

		// Hook after order read.
		do_action( 'ohmylms_after_order_read', $order );
	}

	/**
	 * Update an order in the database.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object to update.
	 * .
	 * @since 1.0.0
	 */
	public function update( &$order ) {
		$old_status = $order->get_status( 'edit' );

		// Hook before order update.
		do_action( 'ohmylms_before_order_update', $order, $old_status );

		if ( null === $order->get_date_created( 'edit' ) ) {
			$order->set_date_created( time() );
		}

		$post_data = array(
			// 'post_date'         => gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getOffsetTimestamp() ),
			// 'post_date_gmt'     => gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getTimestamp() ),
			'post_status'       => $this->get_post_status( $order ),
			'post_parent'       => $order->get_parent_id(),
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);

		$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, $post_data, array( 'ID' => $order->get_id() ) );
		clean_post_cache( $order->get_id() );

		$this->update_post_meta( $order );

		// Hook after order update.
		do_action( 'ohmylms_after_order_update', $order, $old_status );
	}

	/**
	 * Delete.
	 *
	 * @param mixed $data Input data.
	 * @param array $args Operation arguments.
	 * @inheritDoc
	 */
	public function delete( &$data, $args = array() ) {
		// Hook before order deletion.
		do_action( 'ohmylms_before_order_delete', $data, $args );

		// Perform the actual deletion logic here
		// This is a placeholder for the actual deletion implementation.

		// Hook after order deletion.
		do_action( 'ohmylms_after_order_delete', $data, $args );
	}

	/**
	 * Read additional order data from the database and set it to the order object.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object to read data into.
	 * @param mixed                        $post_object Post object.
	 * @since 1.0.0
	 */
	protected function read_order_data( &$order, $post_object ) {
		$id                   = $order->get_id();
		$payment_method_title = get_post_meta( $id, '_payment_method_title', true );
		$order->set_props(
			array(
				'currency'             => get_post_meta( $id, '_order_currency', true ),
				'total'                => get_post_meta( $id, '_order_total', true ),
				'order_key'            => get_post_meta( $id, '_order_key', true ),
				'student_id'           => get_post_meta( $id, '_student_id', true ),
				'payment_method_title' => $payment_method_title ? $payment_method_title : 'N/A',
				'payment_method'       => get_post_meta( $id, '_payment_method', true ),
				'cart_hash'            => get_post_meta( $id, '_cart_hash', true ),
				'first_name'           => get_post_meta( $id, '_first_name', true ),
				'last_name'            => get_post_meta( $id, '_last_name', true ),
				'email'                => get_post_meta( $id, '_email', true ),
				'address'              => get_post_meta( $id, '_address', true ),
				'country'              => get_post_meta( $id, '_country', true ),
				'city'                 => get_post_meta( $id, '_city', true ),
				'postcode'             => get_post_meta( $id, '_postcode', true ),
				'state'                => get_post_meta( $id, '_state', true ),
				'phone'                => get_post_meta( $id, '_phone', true ),
				'vat_number'           => get_post_meta( $id, '_vat_number', true ),
				'cart_discount'        => get_post_meta( $id, '_cart_discount', true ),
				'transaction_id'       => get_post_meta( $id, '_transaction_id', true ),
				'tax_amount'           => get_post_meta( $id, '_tax_amount', true ),
				'tax_rate'             => get_post_meta( $id, '_tax_rate', true ),
			)
		);
	}

	/**
	 * Update order post meta data.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object to update.
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$order ) {
		// Hook before post meta update.
		do_action( 'ohmylms_before_order_meta_update', $order );

		$updated_props     = array();
		$meta_key_to_props = array(
			'_order_currency'       => 'currency',
			'_order_total'          => 'total',
			'_order_key'            => 'order_key',
			'_payment_method'       => 'payment_method',
			'_payment_method_title' => 'payment_method_title',
			'_date_completed'       => 'date_completed',
			'_date_paid'            => 'date_paid',
			'_cart_hash'            => 'cart_hash',
			'_student_id'           => 'student_id',
			'_transaction_id'       => 'transaction_id',
			'_email'                => 'email',
			'_first_name'           => 'first_name',
			'_last_name'            => 'last_name',
			'_address'              => 'address',
			'_country'              => 'country',
			'_city'                 => 'city',
			'_postcode'             => 'postcode',
			'_state'                => 'state',
			'_phone'                => 'phone',
			'_vat_number'           => 'vat_number',
			'_cart_discount'        => 'cart_discount',
			'_tax_amount'           => 'tax_amount',
			'_tax_rate'             => 'tax_rate',
		);

		$props_to_update = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $order->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			switch ( $prop ) {
				case 'date_paid':
				case 'date_completed':
					$value = ! is_null( $value ) ? $value->getTimestamp() : '';
					break;
			}
			$updated = $this->update_or_delete_post_meta( $order, $meta_key, $value );
			if ( $updated ) {
				$updated_props[] = $prop;
			}
		}

		// Hook after post meta update.
		do_action( 'ohmylms_after_order_meta_update', $order, $updated_props );
	}

	/**
	 * Get the post status for the order.
	 *
	 * This method retrieves the status of the order and ensures it is in the correct format.
	 * If the status is not set, it applies a default status.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return string The post status for the order.
	 *
	 * @since 1.0.0
	 */
	protected function get_post_status( $order ) {
		$order_status = $order->get_status( 'edit' );

		if ( ! $order_status ) {
			$order_status = 'pending';
		}

		$post_status    = $order_status;
		$valid_statuses = get_post_stati();

		if ( ! in_array( $post_status, array( 'auto-draft', 'draft', 'trash' ), true ) && in_array( 'ohmylms-' . $post_status, $valid_statuses, true ) ) {
			$post_status = 'ohmylms-' . $post_status;
		}

		return $post_status;
	}


	/**
	 * Get the total refunded amount for an order.
	 *
	 * This function calculates the total amount refunded for a given order by summing up the refund amounts
	 * from the database.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return float The total refunded amount.
	 *
	 * @since 1.0.0
	 */
	public function get_total_refunded( $order ) {
		global $wpdb;

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM( postmeta.meta_value )
				FROM $wpdb->postmeta AS postmeta
				INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'ohmylms_order_refund' AND posts.post_parent = %d )
				WHERE postmeta.meta_key = '_refund_amount'
				AND postmeta.post_id = posts.ID",
				$order->get_id()
			)
		);

		return floatval( $total );
	}


	/**
	 * Read items of a specific type from the order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object to read items from.
	 * @param string                       $type The type of items to read.
	 * @return array The items of the specified type.
	 *
	 * @since 1.0.0
	 */
	public function read_items( &$order, $type ) {
		global $wpdb;
		$items = $wpdb->get_results(
			$wpdb->prepare( "SELECT order_item_type, order_item_id, order_id, order_item_name FROM {$wpdb->prefix}ohmylms_order_items WHERE order_id = %d ORDER BY order_item_id;", $order->get_id() )
		);

		$items          = wp_list_filter( $items, array( 'order_item_type' => $type ) );
		$filtered_items = array();
		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$order_item_id = $item->order_item_id;

				switch ( $type ) {
					case 'line_item':
						$filtered_items[] = new OrderItemCourse( $order_item_id );
						break;
					case 'coupon':
						$filtered_items[] = new OrderItemCoupon( $order_item_id );
						break;
				}
			}
		} else {
			$filtered_items = array();
		}
		return $filtered_items;
	}

	/**
	 * Query orders based on the provided arguments.
	 *
	 * @param array $args The query arguments.
	 * @return object The query result containing orders, total count, and max number of pages.
	 *
	 * @since 1.0.0
	 */
	public function query( $args ) {
		$query = new \WP_Query( $args );
		update_post_caches( $query->posts );
		$order_ids = wp_list_pluck( $query->posts, 'ID' );
		$orders    = $this->compile_orders( $order_ids, $query );

		return (object) array(
			'orders'        => $orders,
			'total'         => $query->found_posts,
			'max_num_pages' => $query->max_num_pages,
		);
	}


	/**
	 * Compile order response and set caches as needed for order ids.
	 *
	 * @param array    $order_ids List of order IDs to compile.
	 * @param WP_Query $query      Query object.
	 * .
	 * @return array Orders.
	 *
	 * @since 1.0.0
	 */
	private function compile_orders( $order_ids, $query ) {
		if ( empty( $order_ids ) ) {
			return array();
		}
		$orders = array();
		foreach ( $query->posts as $post ) {
			$order = ohmylms_ecommerce_get_order( $post );
			if ( false === $order ) {
				continue;
			}
			$orders[] = $order;
		}
		return $orders;
	}

	/**
	 * Get the post title for the order.
	 *
	 * This method generates a formatted title for the order post.
	 *
	 * @return string The formatted post title.
	 *
	 * @since 1.0.0
	 */
	protected function get_post_title() {
		return sprintf( /* translators: %s: text value. */
			__( 'Order &ndash; %s', 'ohmylms' ),
			( new \DateTime( 'now' ) )->format( _x( 'M d, Y @ h:i A', 'Order date', 'ohmylms' ) )
		);
	}


	/**
	 * Get related orders for a given order.
	 *
	 * This function retrieves child orders and renewal orders related to the specified order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order|int $order The order object or ID to get related orders for.
	 * @return array An array of related orders, each containing ID, type, date, status, total, and relationship.
	 * @since 1.0.0
	 */
	public function get_related_orders( $order ) {
		global $wpdb;
		$order_id = is_object( $order ) ? $order->get_id() : (int) $order;
		$related  = array();

		// 1. Get the subscription for this order (assuming only one subscription per order)
		$_subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_type, post_date, post_status FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s LIMIT 1",
				$order_id,
				'ohmylms-subscription'
			)
		);
		if ( $_subscription ) {
			$subscription_id = (int) $_subscription->ID;
			$subscription    = ohmylms_ecommerce_get_subscription( $subscription_id );
			$renewal_orders  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_type, p.post_date, p.post_status FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
				WHERE m.meta_key = %s AND m.meta_value = %d
				ORDER BY p.post_date DESC",
					'_subscription_renewal_id',
					$subscription_id
				)
			);

			foreach ( $renewal_orders as $renewal ) {
				$order     = ohmylms_ecommerce_get_order( $renewal->ID );
				$related[] = array(
					'id'           => (int) $renewal->ID,
					'status'       => ucfirst( $order->get_status() ),
					'total'        => $order->get_order_total(),
					'relationship' => __( 'Renewal Order', 'ohmylms' ),
					'date'         => $renewal->post_date,
				);
			}
			$related[] = array(
				'id'           => $subscription_id,
				'status'       => ucfirst( $subscription->get_status() ),
				'total'        => $subscription->get_order_total(),
				'relationship' => __( 'Subscription', 'ohmylms' ),
				'date'         => $_subscription->post_date,
			);
		}
		return $related;
	}


	/**
	 * Check if the given order is a renewal order.
	 *
	 * This function checks if the order has a related subscription renewal ID.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order|int $order The order object or ID to check.
	 * @return bool True if the order is a renewal order, false otherwise.
	 * @since 1.0.0
	 */
	public function is_renewal_order( $order ) {
		if ( ! is_a( $order, 'OhMyLMS\Commerce\Data\Order' ) ) {
			$order = ohmylms_ecommerce_get_order( $order );
		}
		$order_id                = $order->get_id();
		$related_subscription_id = get_post_meta( $order_id, '_subscription_renewal_id', true );
		if ( $related_subscription_id ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if the given order is a parent order.
	 *
	 * This function checks if the order has child subscriptions.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order|int $order The order object or ID to check.
	 * @return bool True if the order is a parent order, false otherwise.
	 * @since 1.0.0
	 */
	public function is_parent_order( $order ) {
		if ( ! is_a( $order, 'OhMyLMS\Commerce\Data\Order' ) ) {
			$order = ohmylms_ecommerce_get_order( $order );
		}
		$is_parent_order = new \WP_Query(
			array(
				'post_type'   => 'ohmylms-subscription',
				'parent_id'   => $order->get_id(),
				'post_status' => 'any',
				'fields'      => 'ids',
			)
		);

		return ! empty( $is_parent_order->posts ) ? true : false;
	}

	/**
	 * Update order status with hooks.
	 *
	 * This method provides a dedicated way to update order status with proper hooks.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order|int $order The order object or ID.
	 * @param string                           $new_status The new status to set.
	 * @param string                           $note Optional note for the status change.
	 * @since 1.0.0
	 */
	public function update_order_status( $order, $new_status, $note = '' ) {
		if ( ! is_a( $order, 'OhMyLMS\Commerce\Data\Order' ) ) {
			$order = ohmylms_ecommerce_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		$old_status = $order->get_status();

		// Hook before status change.
		do_action( 'ohmylms_before_order_status_change', $order->get_id(), $old_status, $new_status, $order );

		// Update the status.
		$order->set_status( $new_status );

		// Hook after status change.
		do_action( 'ohmylms_after_order_status_change', $order->get_id(), $old_status, $new_status, $order );
		do_action( 'ohmylms_order_status_changed', $order->get_id(), $old_status, $new_status, $order );

		// Save the order.
		$order->save();

		return true;
	}
}
