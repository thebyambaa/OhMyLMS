<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Read-only lookups over plan subscriptions and their renewal orders.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Membership;

defined( 'ABSPATH' ) || exit;

/**
 * Searches behind `ohmylms/v1/subscriptions` (see docs/api/commerce.md) that WP_Query cannot express:
 * subscriptions by customer, plan or order number, and the renewal orders of one subscription. Status
 * changes go through `Commerce\SubscriptionManager` (packages/e-commerce).
 */
final class Subscriptions {

	/**
	 * Subscription post type.
	 */
	const POST_TYPE = 'ohmylms-subscription';

	/**
	 * Subscription IDs matching a search text: subscription or original order number, customer name or
	 * email, or plan name.
	 *
	 * @param string $term Search text.
	 * @return int[] At most 1000 IDs.
	 */
	public static function search_ids( $term ) {
		global $wpdb;

		$like   = '%' . $wpdb->esc_like( $term ) . '%';
		$number = ctype_digit( $term ) ? ' OR p.ID = %d OR o.meta_value = %s' : '';
		$params = array( $wpdb->posts, $wpdb->postmeta, $wpdb->users, $wpdb->postmeta, $wpdb->posts, $wpdb->postmeta, self::POST_TYPE, $like, $like, $like );
		if ( $number ) {
			$params[] = (int) $term;
			$params[] = $term;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Prepared; $number is one of two fixed fragments whose placeholders are added to $params.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM %i p
				LEFT JOIN %i s ON s.post_id = p.ID AND s.meta_key = '_student_id'
				LEFT JOIN %i u ON u.ID = s.meta_value
				LEFT JOIN %i m ON m.post_id = p.ID AND m.meta_key = '_membership_id'
				LEFT JOIN %i plan ON plan.ID = m.meta_value
				LEFT JOIN %i o ON o.post_id = p.ID AND o.meta_key = '_original_order_id'
				WHERE p.post_type = %s AND ( u.display_name LIKE %s OR u.user_email LIKE %s OR plan.post_title LIKE %s{$number} )
				LIMIT 1000",
				$params
			)
		);
		// phpcs:enable

		return array_values( array_unique( array_map( 'intval', (array) $ids ) ) );
	}

	/**
	 * Renewal order IDs of a subscription, newest first.
	 *
	 * @param int $subscription_id Subscription post ID.
	 * @param int $limit           Most rows.
	 * @return string[]
	 */
	public static function renewal_order_ids( $subscription_id, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Prepared and bounded.
		return (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM %i p INNER JOIN %i m ON m.post_id = p.ID AND m.meta_key = '_subscription_renewal_id' WHERE m.meta_value = %s AND p.post_type = %s ORDER BY p.post_date DESC, p.ID DESC LIMIT %d", $wpdb->posts, $wpdb->postmeta, (string) $subscription_id, 'ohmylms-order', $limit ) );
	}
}
