<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Commerce\DataStore;

use OhMyLMS\Commerce\Abstracts\DataStore;

/**
 * Plugin subscription store implementation.
 */
class SubscriptionStore extends DataStore {
	/**
	 * Create for Subscription store.
	 *
	 * @param mixed $subscription Subscription.
	 */
	public function create( &$subscription ) {
		// Implement create logic.
	}

	/**
	 * Read for Subscription store.
	 *
	 * @param mixed $subscription Subscription.
	 */
	public function read( &$subscription ) {
		$subscription_id = $subscription->get_id();
		$student_id      = get_post_meta( $subscription_id, '_student_id', true );
		$student         = \ohmylms_get_student( $student_id );
		$post_object     = get_post( $subscription->get_id() );
		$subscription->set_props(
			array(
				'student_id'                 => $student_id,
				'student_name'               => $student->get_name(),
				'student_email'              => $student->get_email(),
				'student_profile'            => admin_url( 'admin.php?page=ohmylms#/students/' . $student_id . '/report' ),
				'membership_id'              => get_post_meta( $subscription_id, '_membership_id', true ),
				'original_order_id'          => get_post_meta( $subscription_id, '_original_order_id', true ),
				'payment_gateway_id'         => get_post_meta( $subscription_id, '_payment_gateway_id', true ),
				'schedule_start_date'        => get_post_meta( $subscription_id, '_schedule_start_date', true ),
				'schedule_end_date'          => get_post_meta( $subscription_id, '_schedule_end_date', true ),
				'schedule_next_payment_date' => get_post_meta( $subscription_id, '_schedule_next_payment_date', true ),
				'billing_period'             => get_post_meta( $subscription_id, '_billing_period', true ),
				'products'                   => get_post_meta( $subscription_id, '_products', true ),
				'order_total'                => get_post_meta( $subscription_id, '_order_total', true ),
				'recurring_amount'           => get_post_meta( $subscription_id, '_recurring_amount', true ),
				'trial_end_date'             => get_post_meta( $subscription_id, '_trial_end_date', true ),
				'last_payment_date'          => get_post_meta( $subscription_id, '_last_payment_date', true ),
				'status'                     => $post_object->post_status,
			)
		);
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
	 * Update for Subscription store.
	 *
	 * @param mixed $subscription Subscription.
	 */
	public function update( &$subscription ) {
		if ( null === $subscription->get_start_date( 'edit' ) ) {
			$subscription->set_start_date( time() );
		}

		$post_data = array(
			'post_status'       => $this->get_post_status( $subscription ),
			'post_parent'       => $subscription->get_original_order_id(),
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);

		$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, $post_data, array( 'ID' => $subscription->get_id() ) );
		clean_post_cache( $subscription->get_id() );

		$this->update_post_meta( $subscription );
	}

	/**
	 * Update subscription post meta data.
	 *
	 * @param \OhMyLMS\Commerce\Data\Subscription $subscription The subscription object to update.
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$subscription ) {
		$meta_key_to_props = array(
			'_student_id'            => 'student_id',
			'_membership_id'         => 'membership_id',
			'_original_order_id'     => 'original_order_id',
			'_payment_gateway_id'    => 'payment_gateway_id',
			'_schedule_start_date'   => 'schedule_start_date',
			'_schedule_end_date'     => 'schedule_end_date',
			'_schedule_payment_date' => 'schedule_next_payment_date',
			'_billing_period'        => 'billing_period',
			'_products'              => 'products',
			'_recurring_amount'      => 'recurring_amount',
			'_trial_end_date'        => 'trial_end_date',
			'_last_payment_date'     => 'last_payment_date',
		);

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$value = $subscription->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			$this->update_or_delete_post_meta( $subscription, $meta_key, $value );
		}
	}

	/**
	 * Delete for Subscription store.
	 *
	 * @param mixed $subscription Subscription.
	 * @param array $args Operation arguments.
	 */
	public function delete( &$subscription, $args = array() ) {
		// Implement delete logic.
	}

	/**
	 * Get related orders for a subscription.
	 *
	 * This method retrieves all related orders for a given subscription, including renewal orders and the parent order.
	 * It returns an array of related orders with their IDs, statuses, totals, relationships, and dates.
	 * * @param \OhMyLMS\Commerce\Data\Subscription|int $subscription The subscription object or ID to get related orders for.
	 * * @return array An array of related orders, each containing 'id', 'status', 'total', 'relationship', and 'date'.
	 * * @since 1.0.0
	 *
	 * @param mixed $subscription Subscription.
	 */
	public function get_related_orders( $subscription ) {
		global $wpdb;

		// Accept either a Subscription object or an ID.
		$subscription_id = is_object( $subscription ) && method_exists( $subscription, 'get_id' )
			? $subscription->get_id()
			: (int) $subscription;

		$related = array();

		$renewal_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_date FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
            WHERE m.meta_key = %s AND m.meta_value = %d AND p.post_type = %s
            ORDER BY p.post_date DESC",
				'_subscription_renewal_id',
				$subscription_id,
				'ohmylms-order'
			)
		);

		if ( $renewal_orders ) {
			foreach ( $renewal_orders as $renewal ) {
				$order = function_exists( 'ohmylms_ecommerce_get_order' ) ? ohmylms_ecommerce_get_order( $renewal->ID ) : null;
				if ( $order ) {
					$related[] = array(
						'id'           => (int) $renewal->ID,
						'status'       => ucfirst( $order->get_status() ),
						'total'        => $order->get_order_total(),
						'relationship' => 'Renewal Order',
						'date'         => $renewal->post_date,
					);
				}
			}
		}

		$parent_order_id = (int) get_post_field( 'post_parent', $subscription_id );
		if ( $parent_order_id ) {
			$order = function_exists( 'ohmylms_ecommerce_get_order' ) ? ohmylms_ecommerce_get_order( $parent_order_id ) : null;
			if ( $order ) {
				$related[] = array(
					'id'           => $parent_order_id,
					'status'       => ucfirst( $order->get_status() ),
					'total'        => $order->get_order_total(),
					'relationship' => 'Parent',
					'date'         => get_post_field( 'post_date', $parent_order_id ),
				);
			}
		}

		return $related;
	}
}
