<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Read-only commerce data for the learner workspace.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/** Commerce ownership is checked independently of navigation and post authorship. */
final class MembershipView {

	/**
	 * Query a bounded page of the current customer's records.
	 *
	 * @param string $type Commerce post type.
	 * @param int    $page Page number.
	 * @return \WP_Query
	 */
	public static function owned( $type, $page = 1 ) {
		return new \WP_Query(
			array(
				'post_type'      => in_array( $type, array( 'ohmylms-order', 'ohmylms-subscription' ), true ) ? $type : 'ohmylms-order',
				'post_status'    => 'any',
				'posts_per_page' => 12,
				'paged'          => max( 1, absint( $page ) ),
				'meta_key'       => '_student_id',
				'meta_value'     => is_user_logged_in() ? (string) get_current_user_id() : '-1',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Resolve one invoice without exposing another customer's record.
	 *
	 * @param int $order_id Order ID.
	 * @return object|false
	 */
	public static function invoice( $order_id ) {
		if ( ! is_user_logged_in() || 'ohmylms-order' !== get_post_type( $order_id ) || ! function_exists( 'ohmylms_ecommerce_get_order' ) ) {
			return false;
		}
		$order = ohmylms_ecommerce_get_order( $order_id );
		return $order && get_current_user_id() === (int) $order->get_user_id() ? $order : false;
	}

	/**
	 * Format only an actual stored date, never calculate a renewal date.
	 *
	 * @param mixed $value Stored UTC date or date object.
	 * @return string
	 */
	public static function date( $value ) {
		if ( empty( $value ) ) {
			return __( 'Not scheduled', 'ohmylms' );
		}
		$timestamp = $value instanceof \DateTimeInterface ? $value->getTimestamp() : strtotime( (string) $value . ' UTC' );
		return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : __( 'Not scheduled', 'ohmylms' );
	}
}
