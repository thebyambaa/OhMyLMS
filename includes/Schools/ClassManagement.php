<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Class edits and scoped roster operations shared by the teacher workspace.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/** Mutations never grant course access or change a learner's account role. */
final class ClassManagement {

	/**
	 * Save editable class details without moving school or academic year.
	 *
	 * @param int   $class_id Class ID.
	 * @param array $data Submitted details.
	 */
	public static function edit( $class_id, array $data ) {
		$class = Access::classroom( $class_id, true );
		Access::require_access( $class );
		$name = Service::text( $data['name'] ?? '' );
		Service::need( '' !== $name, __( 'A class name is required.', 'ohmylms' ) );
		Service::update(
			'classes',
			array(
				'name'    => $name,
				'subject' => Service::text( $data['subject'] ?? '', 100 ),
				'grade'   => Service::text( $data['grade'] ?? '', 50 ),
			),
			array( 'id' => $class_id )
		);
		Service::audit( $class['school_id'], 'class_updated', $class_id );
	}

	/**
	 * Whether the current actor may archive or restore this class.
	 *
	 * @param array $class Class record.
	 * @return bool
	 */
	public static function can_archive( array $class ) {
		return (bool) Access::classroom( $class['id'] ) && ( ! (int) $class['school_id'] || Access::school( $class['school_id'] ) );
	}

	/**
	 * Restore an archived class using the same authority required for archiving.
	 *
	 * @param int $class_id Class ID.
	 */
	public static function restore( $class_id ) {
		$class = Access::classroom( $class_id );
		Access::require_access( $class && self::can_archive( $class ) );
		Service::need( 'archived' === $class['status'], __( 'This class is not archived.', 'ohmylms' ) );
		if ( (int) $class['school_id'] ) {
			$year = Service::row( 'academic_years', $class['academic_year_id'] );
			Service::need( $year && 'active' === $year['status'], __( 'The academic year must be active before restoring this class.', 'ohmylms' ) );
		}
		Service::update( 'classes', array( 'status' => 'active' ), array( 'id' => $class_id ) );
		Service::audit( $class['school_id'], 'class_restored', $class_id );
	}

	/**
	 * Search a class's students or eligible school roster, with bounded pagination.
	 *
	 * @param int    $class_id Class ID.
	 * @param string $search Display-name search.
	 * @param int    $page Page number.
	 * @param bool   $candidates Return school students outside this class.
	 * @return array
	 */
	public static function students( $class_id, $search = '', $page = 1, $candidates = false ) {
		$class = Access::classroom( $class_id, $candidates );
		Access::require_access( $class );
		if ( $candidates && ! (int) $class['school_id'] ) {
			return array();
		}
		global $wpdb;
		$members = Schema::table( 'class_memberships' );
		$school  = Schema::table( 'school_memberships' );
		$offset  = ( min( 10000, max( 1, absint( $page ) ) ) - 1 ) * 20;
		$like    = '%' . $wpdb->esc_like( Service::text( $search ) ) . '%';
		if ( $candidates ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT u.ID AS user_id,u.display_name FROM %i u JOIN %i sm ON sm.user_id=u.ID AND sm.school_id=%d AND sm.role='student' AND sm.status='active' WHERE u.display_name LIKE %s AND NOT EXISTS (SELECT 1 FROM %i cm WHERE cm.class_id=%d AND cm.user_id=u.ID AND cm.role='student' AND cm.status='active') ORDER BY u.display_name,u.ID LIMIT 21 OFFSET %d",
					$wpdb->users,
					$school,
					$class['school_id'],
					$like,
					$members,
					$class_id,
					$offset
				),
				ARRAY_A
			);
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID AS user_id,u.display_name FROM %i u JOIN %i cm ON cm.user_id=u.ID AND cm.class_id=%d AND cm.role='student' AND cm.status='active' WHERE u.display_name LIKE %s AND (%d=0 OR EXISTS (SELECT 1 FROM %i sm WHERE sm.user_id=u.ID AND sm.school_id=%d AND sm.role='student' AND sm.status='active')) ORDER BY u.display_name,u.ID LIMIT 21 OFFSET %d",
				$wpdb->users,
				$members,
				$class_id,
				$like,
				$class['school_id'],
				$school,
				$class['school_id'],
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Remove only an existing student membership in the authorized class.
	 *
	 * @param int $class_id Class ID.
	 * @param int $student_id Learner ID.
	 */
	public static function remove_student( $class_id, $student_id ) {
		$class = Access::classroom( $class_id, true );
		Access::require_access( $class );
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE class_id=%d AND user_id=%d AND role='student' AND status='active'", Schema::table( 'class_memberships' ), $class_id, $student_id ) );
		Service::need( $id, __( 'This student is not on the active roster.', 'ohmylms' ) );
		Service::update( 'class_memberships', array( 'status' => 'inactive' ), array( 'id' => $id ) );
		Service::audit( $class['school_id'], 'class_member_removed', $student_id );
	}

	/**
	 * Create a private standalone-class invitation; never send mail implicitly.
	 *
	 * @param int    $class_id Class ID.
	 * @param string $email Intended learner email.
	 * @return array Invitation ID and one-time link.
	 */
	public static function invite( $class_id, $email ) {
		$class = Access::classroom( $class_id, true );
		Access::require_access( $class && 0 === (int) $class['school_id'] );
		$email = sanitize_email( $email );
		Service::need( is_email( $email ), __( 'Enter a valid student email address.', 'ohmylms' ) );
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE class_id=%d AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s', Schema::table( 'school_invitations' ), $class_id, Service::now() ) );
		Service::need( 50 > (int) $count, __( 'Revoke unused invitations before creating more.', 'ohmylms' ) );
		$token = bin2hex( random_bytes( 32 ) );
		$id    = Service::insert(
			'school_invitations',
			array(
				'token_hash' => hash( 'sha256', $token ),
				'school_id'  => 0,
				'class_id'   => $class_id,
				'role'       => 'student',
				'email'      => $email,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ),
				'invited_by' => get_current_user_id(),
				'created_at' => Service::now(),
			)
		);
		Service::audit( 0, 'class_invitation_created', $id );
		return array(
			'id'  => $id,
			'url' => add_query_arg( 'ohmylms_invite', $token, \OhMyLMS\Student\Hub::url( 'classes', 'join' ) ),
		);
	}

	/**
	 * Read pending invitations without exposing token hashes.
	 *
	 * @param int $class_id Class ID.
	 * @return array
	 */
	public static function invitations( $class_id ) {
		Access::require_access( Access::classroom( $class_id, true ) );
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT id,email,expires_at FROM %i WHERE class_id=%d AND school_id=0 AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s ORDER BY id DESC LIMIT 50', Schema::table( 'school_invitations' ), $class_id, Service::now() ), ARRAY_A );
	}

	/**
	 * Revoke an invitation only within its authorized standalone class.
	 *
	 * @param int $class_id Class ID.
	 * @param int $invite_id Invitation ID.
	 */
	public static function revoke( $class_id, $invite_id ) {
		Access::require_access( Access::classroom( $class_id, true ) );
		$invite = Service::row( 'school_invitations', $invite_id );
		Access::require_access( $invite && (int) $invite['class_id'] === $class_id && 0 === (int) $invite['school_id'] && 'student' === $invite['role'] );
		Service::update( 'school_invitations', array( 'revoked_at' => Service::now() ), array( 'id' => $invite_id ) );
		Service::audit( 0, 'class_invitation_revoked', $invite_id );
	}

	/**
	 * Accept a locked, validated standalone invitation inside Service::accept().
	 *
	 * @param array $invite Invitation row locked by the caller's transaction.
	 * @return array
	 */
	public static function accept( array $invite ) {
		$class  = Service::row( 'classes', $invite['class_id'] );
		$issuer = get_userdata( $invite['invited_by'] );
		$user   = wp_get_current_user();
		Access::require_access( $class && 'active' === $class['status'] && 0 === (int) $class['school_id'] && 0 === (int) $invite['school_id'] && 'student' === $invite['role'] && ! (int) $invite['student_user_id'] );
		Access::require_access( $user->ID );
		Service::need( 0 === strcasecmp( $user->user_email, $invite['email'] ), 'This invitation was sent to a different email address. Sign in with the account it was sent to.' );
		global $wpdb;
		$member         = $issuer && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE class_id=%d AND user_id=%d AND role='teacher' AND status='active'", Schema::table( 'class_memberships' ), $class['id'], $issuer->ID ) );
		$school_teacher = $issuer && $wpdb->get_var( $wpdb->prepare( "SELECT m.id FROM %i m JOIN %i s ON s.id=m.school_id AND s.status='active' WHERE m.user_id=%d AND m.role='teacher' AND m.status='active' LIMIT 1", Schema::table( 'school_memberships' ), Schema::table( 'schools' ), $issuer->ID ) );
		Access::require_access( $issuer && ( user_can( $issuer, 'manage_options' ) || ( $member && ( in_array( 'ohmylms_teacher', $issuer->roles, true ) || $school_teacher ) ) ) );
		Service::membership(
			'class_memberships',
			array(
				'class_id' => $class['id'],
				'user_id'  => $user->ID,
				'role'     => 'student',
			)
		);
		Service::update( 'school_invitations', array( 'consumed_at' => Service::now() ), array( 'id' => $invite['id'] ) );
		Service::audit( 0, 'class_invitation_accepted', $invite['id'] );
		return array(
			'success' => true,
			'message' => __( 'Invitation accepted. Open My classes to see your class.', 'ohmylms' ),
		);
	}
}
