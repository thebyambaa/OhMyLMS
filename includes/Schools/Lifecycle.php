<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class mapping.
/**
 * School recovery, voluntary departure and academic-year previews.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/** Lifecycle operations always authorize current database relationships. */
final class Lifecycle {

	/**
	 * Whether this school still manages an ordinary student account.
	 *
	 * @param int $school School ID.
	 * @param int $student Student ID.
	 * @return bool
	 */
	public static function managed_student( $school, $student ) {
		$user = get_userdata( $student );
		return $user && Access::student( $school, $student ) && (int) get_user_meta( $student, '_ohmylms_managed_school', true ) === (int) $school
			&& ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'edit_users' ) && ! user_can( $user, 'edit_posts' )
			&& ! array_intersect( array( 'administrator', 'ohmylms_school_admin', 'ohmylms_teacher', 'ohmylms_parent' ), $user->roles );
	}

	/**
	 * Replace unused password-setup links for an account managed by this school.
	 *
	 * @param int $school School ID.
	 * @param int $student Student ID.
	 * @return array Private invitation result.
	 */
	public static function recovery( $school, $student ) {
		Access::require_access( Access::school( $school ) );
		return Service::transaction(
			static function () use ( $school, $student ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialize replacement links for this account.
				$person = $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID=%d FOR UPDATE', $wpdb->users, $student ) );
				Access::require_access( $person && Access::school( $school ) && self::managed_student( $school, $student ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Revoke only outstanding setup links owned by this school.
				$changed = $wpdb->query( $wpdb->prepare( "UPDATE %i SET revoked_at=%s WHERE school_id=%d AND student_user_id=%d AND role='activation' AND consumed_at IS NULL AND revoked_at IS NULL", Schema::table( 'school_invitations' ), Service::now(), $school, $student ) );
				Service::need( false !== $changed, 'Unable to replace the recovery link.' );
				$result = Service::invite(
					$school,
					array(
						'role'            => 'activation',
						'student_user_id' => $student,
					)
				);
				Service::audit( $school, 'student_recovery_created', $student );
				return $result;
			}
		);
	}

	/**
	 * Schools the signed-in person has actually joined, not platform-visible schools.
	 *
	 * @return array
	 */
	public static function memberships() {
		if ( ! is_user_logged_in() || Schema::VERSION !== get_option( 'ohmylms_school_schema' ) || ( defined( 'OHMYLMS_SCHOOLS_ENABLED' ) && ! OHMYLMS_SCHOOLS_ENABLED ) ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current actor's own active relationships.
		return $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT s.id,s.name FROM %i s JOIN %i m ON m.school_id=s.id WHERE s.status='active' AND m.user_id=%d AND m.status='active' ORDER BY s.name,s.id", Schema::table( 'schools' ), Schema::table( 'school_memberships' ), get_current_user_id() ), ARRAY_A );
	}

	/**
	 * Leave all of the actor's memberships in one school, preserving accounts and history.
	 *
	 * @param int $school School ID.
	 * @return void
	 */
	public static function leave( $school ) {
		$actor = get_current_user_id();
		Access::require_access( Schema::VERSION === get_option( 'ohmylms_school_schema' ) && ( ! defined( 'OHMYLMS_SCHOOLS_ENABLED' ) || OHMYLMS_SCHOOLS_ENABLED ) );
		Access::require_access( $actor && Access::school( $school, array( 'school_admin', 'teacher', 'student' ), $actor ) );
		Service::need( (int) get_user_meta( $actor, '_ohmylms_managed_school', true ) !== (int) $school, 'Ask your school administrator to remove a school-managed account.' );
		Service::transaction(
			static function () use ( $school, $actor ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialize departures so two administrators cannot both leave the school without an administrator.
				$wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'schools' ), $school ) );
				Access::require_access( Access::school( $school, array( 'school_admin', 'teacher', 'student' ), $actor ) );
				if ( Access::school( $school, array( 'school_admin' ), $actor ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Require a different, existing active administrator.
					$other = $wpdb->get_var( $wpdb->prepare( "SELECT m.id FROM %i m JOIN %i u ON u.ID=m.user_id WHERE m.school_id=%d AND m.role='school_admin' AND m.status='active' AND m.user_id<>%d LIMIT 1", Schema::table( 'school_memberships' ), $wpdb->users, $school, $actor ) );
					Service::need( $other, 'Invite another school administrator before leaving.' );
				}
				Service::update(
					'school_memberships',
					array( 'status' => 'inactive' ),
					array(
						'school_id' => $school,
						'user_id'   => $actor,
					)
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Remove this actor's class access in this school only.
				$changed = $wpdb->query( $wpdb->prepare( "UPDATE %i m JOIN %i c ON c.id=m.class_id SET m.status='inactive' WHERE c.school_id=%d AND m.user_id=%d", Schema::table( 'class_memberships' ), Schema::table( 'classes' ), $school, $actor ) );
				Service::need( false !== $changed, 'Unable to leave the school.' );
				foreach ( array( 'guardian_user_id', 'student_user_id' ) as $column ) {
					Service::update(
						'guardian_links',
						array( 'status' => 'inactive' ),
						array(
							'school_id' => $school,
							$column     => $actor,
						)
					);
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Outstanding links issued by the departing member must not remain active.
				$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET revoked_at=%s WHERE school_id=%d AND invited_by=%d AND consumed_at IS NULL AND revoked_at IS NULL', Schema::table( 'school_invitations' ), Service::now(), $school, $actor ) );
				Service::need( false !== $changed, 'Unable to revoke pending invitations.' );
				Service::audit( $school, 'member_left_school', $actor );
			}
		);
	}

	/**
	 * Bind a rollover confirmation to its current source classes and year records.
	 *
	 * @param array $from Source year.
	 * @param array $to Target year.
	 * @param array $classes Source classes.
	 * @return string
	 */
	public static function signature( $from, $to, $classes ) {
		return hash( 'sha256', wp_json_encode( array( $from, $to, $classes ) ) );
	}

	/**
	 * Preview a rollover without changing any learning or roster data.
	 *
	 * @param int $school School ID.
	 * @param int $from_id Source year ID.
	 * @param int $to_id Target year ID.
	 * @return array
	 */
	public static function preview( $school, $from_id, $to_id ) {
		Access::require_access( Access::school( $school ) );
		$from = Service::row( 'academic_years', $from_id );
		$to   = Service::row( 'academic_years', $to_id );
		Service::need( $from && $to && $from_id !== $to_id && (int) $from['school_id'] === (int) $school && (int) $to['school_id'] === (int) $school && 'active' === $from['status'] && 'active' === $to['status'], 'Select two different active academic years in this school.' );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact source list used by the confirmation signature.
		$classes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE academic_year_id=%d AND school_id=%d AND status='active' ORDER BY id", Schema::table( 'classes' ), $from_id, $school ), ARRAY_A );
		set_transient( self::preview_key( $school, $from_id, $to_id ), self::signature( $from, $to, $classes ), 5 * MINUTE_IN_SECONDS );
		return array(
			'from'    => $from,
			'to'      => $to,
			'classes' => $classes,
		);
	}

	/**
	 * Per-actor confirmation storage key.
	 *
	 * @param int $school School ID.
	 * @param int $from Source year.
	 * @param int $to Target year.
	 * @return string
	 */
	private static function preview_key( $school, $from, $to ) {
		return 'ohmylms_rollover_' . get_current_user_id() . '_' . $school . '_' . $from . '_' . $to;
	}

	/**
	 * Apply only a recently previewed rollover. The service rechecks its signature under lock.
	 *
	 * @param int $school School ID.
	 * @param int $from Source year.
	 * @param int $to Target year.
	 * @return array
	 */
	public static function rollover( $school, $from, $to ) {
		Access::require_access( Access::school( $school ) );
		$key       = self::preview_key( $school, $from, $to );
		$signature = get_transient( $key );
		Service::need( is_string( $signature ) && strlen( $signature ) === 64, 'Preview this rollover before confirming it.' );
		$result = Service::rollover(
			$school,
			array(
				'from_year'    => $from,
				'to_year'      => $to,
				'preview_hash' => $signature,
			)
		);
		delete_transient( $key );
		return $result;
	}
}
