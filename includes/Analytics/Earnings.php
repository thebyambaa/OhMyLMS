<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Legacy earnings ledger writes.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Analytics;

defined( 'ABSPATH' ) || exit;

/** Owns writes to the earnings ledger used by the compatibility hook. */
final class Earnings {
	/**
	 * Record an order's current status and initial totals using the established ledger behavior.
	 *
	 * @param object $order Order object.
	 * @param array  $data  Membership context.
	 */
	public static function record_order( $order, $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_earning';
		// Execute the query and return the result.
		$result        = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id FROM %i WHERE order_id = %d', $table_name, $order->get_id() ), ARRAY_A );
		$membership_id = null;
		$course_id     = null;
		$status        = $order->get_status();
		$total         = $order->get_total();
		if ( ! empty( $data['membership_id'] ) ) {
			$membership_id = $data['membership_id'];
		} else {
			$items = $order->get_items();
			foreach ( $items as $item ) {
				if ( isset( $item['course_id'] ) ) {
					$course_id = $item['course_id'];
				}
			}
		}
		if ( ! empty( $result ) ) {
			// Update the membership status in the database.
			$result = $wpdb->update(
				$table_name,
				array( 'status' => $order->get_status() ), // Data to update.
				array(
					'order_id' => $order->get_id(),
				), // Where conditions.
				array( '%s' ), // Format for the updated data.
				array( '%d' ) // Format for the where conditions.
			);
		} else {
			// Insert a new record.
			$insert_result = $wpdb->insert(
				$table_name,
				array(
					'order_id'      => $order->get_id(),
					'membership_id' => $membership_id,
					'course_id'     => $course_id,
					'status'        => $status,
					'total'         => $total,
					'created_at'    => current_time( 'mysql' ), // Optional timestamp.
				),
				array( '%d', '%d', '%d', '%s', '%f', '%s' ) // Format for the inserted data.
			);
		}
	}
}
