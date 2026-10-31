<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Guardians of a school's learners: active guardian links and open guardian invitations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/**
 * Read model of the guardian list (docs/api/account-hub.md, "Guardians"). Callers check scope; this class
 * only reads. Links and invitations are created and revoked through `Service::invite()`,
 * `Service::revoke_guardian()` and `Service::revoke_invite()`, which keep the rule that a guardian link
 * becomes active only when the invited, signed-in parent accepts it.
 */
final class Guardians {

	/** List filters. */
	const STATUSES = array( 'all', 'active', 'pending' );

	/**
	 * The UNION of links and open invitations, as prepared SQL.
	 *
	 * @param int    $school_id School.
	 * @param string $status    `all`, `active` or `pending`.
	 * @param int    $class_id  Only students of this class (0 for every student).
	 * @param string $like      LIKE pattern on student and parent names.
	 * @return string
	 */
	private static function union( $school_id, $status, $class_id, $like ) {
		global $wpdb;
		$class = $class_id ? $wpdb->prepare( " AND EXISTS (SELECT 1 FROM %i cm WHERE cm.class_id=%d AND cm.user_id=su.ID AND cm.role='student' AND cm.status='active')", Schema::table( 'class_memberships' ), $class_id ) : '';
		$parts = array();
		if ( 'pending' !== $status ) {
			$parts[] = $wpdb->prepare(
				"SELECT 'link' AS kind, g.id, g.guardian_user_id AS parent_id, pu.display_name AS parent_name, su.ID AS student_id, su.display_name AS student_name, g.approved_at AS at, '' AS email
				FROM %i g JOIN %i su ON su.ID=g.student_user_id JOIN %i pu ON pu.ID=g.guardian_user_id
				WHERE g.school_id=%d AND g.status='active' AND (su.display_name LIKE %s OR pu.display_name LIKE %s)",
				Schema::table( 'guardian_links' ),
				$wpdb->users,
				$wpdb->users,
				$school_id,
				$like,
				$like
			) . $class;
		}
		if ( 'active' !== $status ) {
			$parts[] = $wpdb->prepare(
				"SELECT 'invitation' AS kind, i.id, 0 AS parent_id, '' AS parent_name, su.ID AS student_id, su.display_name AS student_name, i.expires_at AS at, i.email
				FROM %i i JOIN %i su ON su.ID=i.student_user_id
				WHERE i.school_id=%d AND i.role='guardian' AND i.consumed_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>%s AND su.display_name LIKE %s",
				Schema::table( 'school_invitations' ),
				$wpdb->users,
				$school_id,
				Service::now(),
				$like
			) . $class;
		}

		return implode( ' UNION ALL ', $parts );
	}

	/**
	 * Number of rows for a filter.
	 *
	 * @param int    $school_id School.
	 * @param string $status    Filter.
	 * @param int    $class_id  Class or 0.
	 * @param string $like      LIKE pattern.
	 * @return int
	 */
	public static function count( $school_id, $status, $class_id, $like ) {
		global $wpdb;
		$union = self::union( (int) $school_id, $status, (int) $class_id, $like );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Assembled from prepared parts.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ( $union ) x" );
	}

	/**
	 * One page of rows, by student then kind.
	 *
	 * @param int    $school_id School.
	 * @param string $status    Filter.
	 * @param int    $class_id  Class or 0.
	 * @param string $like      LIKE pattern.
	 * @param int    $limit     Rows.
	 * @param int    $offset    Offset.
	 * @return array[] Raw rows: kind, id, parent_id, parent_name, student_id, student_name, at, email.
	 */
	public static function page( $school_id, $status, $class_id, $like, $limit, $offset ) {
		global $wpdb;
		$union = self::union( (int) $school_id, $status, (int) $class_id, $like );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Assembled from prepared parts.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ( $union ) x ORDER BY x.student_name, x.student_id, x.kind DESC, x.id LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		foreach ( $rows as &$row ) {
			if ( 'invitation' === $row['kind'] && '' !== $row['email'] ) {
				$user = get_user_by( 'email', $row['email'] );
				if ( $user ) {
					$row['parent_id']   = (int) $user->ID;
					$row['parent_name'] = (string) $user->display_name;
				}
			}
		}

		return $rows;
	}

	/**
	 * Active link of a school, or null.
	 *
	 * @param int $school_id School.
	 * @param int $link_id   Link.
	 * @return array|null
	 */
	public static function link( $school_id, $link_id ) {
		$link = Service::row( 'guardian_links', (int) $link_id );

		return $link && (int) $link['school_id'] === (int) $school_id && 'active' === $link['status'] ? $link : null;
	}

	/**
	 * Open guardian invitation of a school, or null.
	 *
	 * @param int $school_id     School.
	 * @param int $invitation_id Invitation.
	 * @return array|null
	 */
	public static function invitation( $school_id, $invitation_id ) {
		$invite = Service::row( 'school_invitations', (int) $invitation_id );
		$open   = $invite && 'guardian' === $invite['role'] && (int) $invite['school_id'] === (int) $school_id
			&& ! $invite['consumed_at'] && ! $invite['revoked_at'] && $invite['expires_at'] > Service::now();

		return $open ? $invite : null;
	}

	/**
	 * Id of an open guardian invitation for an email and student, or 0.
	 *
	 * @param int    $school_id  School.
	 * @param int    $student_id Student.
	 * @param string $email      Email.
	 * @return int
	 */
	public static function pending( $school_id, $student_id, $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current invitation state.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE school_id=%d AND student_user_id=%d AND role='guardian' AND email=%s AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s ORDER BY id DESC LIMIT 1", Schema::table( 'school_invitations' ), $school_id, $student_id, $email, Service::now() ) );
	}

	/**
	 * Whether an account is already an active guardian of a student in a school.
	 *
	 * @param int $school_id  School.
	 * @param int $parent_id  Parent account.
	 * @param int $student_id Student.
	 * @return bool
	 */
	public static function linked( $school_id, $parent_id, $student_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current link state.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE school_id=%d AND guardian_user_id=%d AND student_user_id=%d AND status='active'", Schema::table( 'guardian_links' ), $school_id, $parent_id, $student_id ) );
	}
}
