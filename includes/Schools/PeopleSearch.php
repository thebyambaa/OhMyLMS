<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Searches for the people and classes of a school for the front-end dashboard.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only lookups over the school membership, invitation and class tables.
 *
 * The dashboard controller works out which schools the user may see and shapes the response; this class only
 * runs the queries, so they live in one place and the controller stays free of `$wpdb`.
 */
final class PeopleSearch {

	/**
	 * Most rows one search returns.
	 */
	const LIMIT = 50;

	/**
	 * People of the given schools: active members with a role, or pending guardian invitations.
	 *
	 * @param int[]  $school_ids Schools the user may see; must not be empty.
	 * @param string $role       Membership role, or `guardian` for pending invitations.
	 * @param string $search     Text to look for in names and email addresses.
	 * @return array[] Rows with `id`, `name`, `email` and `school`.
	 */
	public static function find( array $school_ids, $role, $search ) {
		global $wpdb;
		$ids  = array_values( array_map( 'intval', $school_ids ) );
		$in   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		if ( 'guardian' === $role ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.id,i.email AS name,i.email,'' AS school FROM %i i WHERE i.role='guardian' AND i.consumed_at IS NULL AND i.revoked_at IS NULL AND i.school_id IN ($in) AND i.email LIKE %s ORDER BY i.id DESC LIMIT 50", array_merge( array( Schema::table( 'school_invitations' ) ), $ids, array( $like ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The IN() placeholders are built from the school list, which the sniff cannot count.
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID AS id,u.display_name AS name,u.user_email AS email,s.name AS school FROM %i m JOIN {$wpdb->users} u ON u.ID=m.user_id JOIN %i s ON s.id=m.school_id WHERE m.status='active' AND m.role=%s AND m.school_id IN ($in) AND (u.display_name LIKE %s OR u.user_email LIKE %s) ORDER BY u.display_name LIMIT 50", array_merge( array( Schema::table( 'school_memberships' ), Schema::table( 'schools' ), $role ), $ids, array( $like, $like ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The IN() placeholders are built from the school list, which the sniff cannot count.
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * The active class of a school with the given name.
	 *
	 * @param int    $school School ID.
	 * @param string $name   Class name.
	 * @return int Class ID, or 0 when there is none.
	 */
	public static function class_id_by_name( $school, $name ) {
		global $wpdb;
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d AND name=%s AND status=%s LIMIT 1', Schema::table( 'classes' ), $school, $name, 'active' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
