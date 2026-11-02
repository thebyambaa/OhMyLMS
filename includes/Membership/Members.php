<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Membership member lists, status transitions and lifecycle storage.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Membership;

defined( 'ABSPATH' ) || exit;

/**
 * Member lists and counts of `ohmylms_user_membership` behind `ohmylms/v1/memberships` (see
 * docs/api/memberships.md), plus status and cleanup operations for cross-domain workflows.
 * Creation and the existing order flow stay with `DataStores\MembershipStore`.
 */
final class Members {

	/**
	 * Delete member rows matching an owning workflow's conditions.
	 *
	 * @param array $where Equality conditions.
	 * @return int|false Deleted rows or failure.
	 */
	public static function delete( array $where ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Membership owner deletes its own rows.
		return $wpdb->delete( self::table(), $where, array_fill( 0, count( $where ), '%d' ) );
	}

	/**
	 * First order for a plan, preserving the legacy lifecycle lookup.
	 *
	 * @param int $membership_id Plan ID.
	 * @return int Order ID or zero.
	 */
	public static function first_order( $membership_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh lifecycle relationship read.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM %i WHERE membership_id = %d', self::table(), $membership_id ) );
	}

	/**
	 * Cancel every membership for a deleted user.
	 *
	 * @param int $user_id User ID.
	 * @return int|false Updated rows or failure.
	 */
	public static function cancel_user( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Membership owner updates its own rows.
		return $wpdb->update( self::table(), array( 'status' => 'cancelled' ), array( 'user_id' => (int) $user_id ) );
	}

	/**
	 * Transition a user's existing rows when the optional legacy table is installed.
	 *
	 * @param int    $user_id User ID.
	 * @param string $from    Previous status.
	 * @param string $to      New status.
	 * @return int|false Updated rows, zero for an absent table, or false on write failure.
	 */
	public static function transition_user_status( $user_id, $from, $to ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ohmylms_user_membership';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Existence probe for an optional legacy table.
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return 0;
		}
		$where = array(
			'user_id' => (int) $user_id,
			'status'  => $from,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Membership owner updates its own rows.
		return $wpdb->update( $table, array( 'status' => $to ), $where, array( '%s' ), array( '%d', '%s' ) );
	}

	/**
	 * Membership table.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_user_membership';
	}

	/**
	 * WHERE clause and arguments of a plan's member list.
	 *
	 * @param int    $membership_id Plan ID.
	 * @param string $status        A member status, or `all`.
	 * @return array{0:string,1:array}
	 */
	private static function where( $membership_id, $status ) {
		$where  = 'WHERE m.membership_id = %d';
		$params = array( (int) $membership_id );
		if ( 'all' !== $status ) {
			$where   .= ' AND m.status = %s';
			$params[] = (string) $status;
		}
		return array( $where, $params );
	}

	/**
	 * Number of a plan's members with a status.
	 *
	 * @param int    $membership_id Plan ID.
	 * @param string $status        A member status, or `all`.
	 * @return int
	 */
	public static function count( $membership_id, $status ) {
		global $wpdb;
		list( $where, $params ) = self::where( $membership_id, $status );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- The clause is built from fixed fragments.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i m {$where}", array_merge( array( self::table() ), $params ) ) );
	}

	/**
	 * One page of a plan's members, newest first, with the user's name and email.
	 *
	 * @param int    $membership_id Plan ID.
	 * @param string $status        A member status, or `all`.
	 * @param int    $page          Page, from 1.
	 * @param int    $per_page      Page size.
	 * @return object[] Rows with id, user_id, status, order_id, start_date, end_date, display_name, user_email.
	 */
	public static function page( $membership_id, $status, $page, $per_page ) {
		global $wpdb;
		list( $where, $params ) = self::where( $membership_id, $status );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- The clause is built from fixed fragments.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.id, m.user_id, m.status, m.order_id, m.start_date, m.end_date, u.display_name, u.user_email
				FROM %i m LEFT JOIN %i u ON u.ID = m.user_id {$where}
				ORDER BY m.id DESC LIMIT %d OFFSET %d",
				array_merge( array( self::table(), $wpdb->users ), $params, array( (int) $per_page, ( (int) $page - 1 ) * (int) $per_page ) )
			)
		);
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * Enrolled members of several plans, with one query.
	 *
	 * @param int[] $membership_ids Plan IDs.
	 * @return array<int,int> Plan ID => enrolled members (0 when none).
	 */
	public static function enrolled_counts( array $membership_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $membership_ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT membership_id, COUNT(*) AS total FROM %i WHERE status = 'enrolled' AND membership_id IN ({$placeholders}) GROUP BY membership_id", array_merge( array( self::table() ), $ids ) ) );
		$counts = array_fill_keys( $ids, 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->membership_id ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Number of a plan's members in either of two statuses.
	 *
	 * @param int      $membership_id Plan ID.
	 * @param string[] $statuses      Two statuses.
	 * @return int
	 */
	public static function count_in( $membership_id, array $statuses ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh count.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE membership_id = %d AND status IN (%s, %s)', self::table(), $membership_id, $statuses[0], $statuses[1] ) );
	}
}
