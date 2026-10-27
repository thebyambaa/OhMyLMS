<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Every resource permission is resolved from current database relationships.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/** Every resource permission is resolved from current database relationships. */
final class Access {
	/**
	 * Platform for Access.
	 */
	public static function platform() {
		return current_user_can( 'manage_options' ); }
	/**
	 * School for Access.
	 *
	 * @param mixed $school School.
	 * @param array $roles Roles.
	 * @param int   $user User.
	 */
	public static function school( $school, $roles = array( 'school_admin' ), $user = 0 ) {
		global $wpdb;
		$explicit_user = (bool) $user;
		$user          = $user ? $user : get_current_user_id();
		if ( ! $user || ! $school ) {
			return false; }
		$schools = Schema::table( 'schools' );
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id=%d AND status=\'active\'', $schools, $school ) ) ) {
			return false; }
		if ( ! $explicit_user && self::platform() ) {
			return true; }
		$table        = Schema::table( 'school_memberships' );
		$placeholders = implode( ',', array_fill( 0, count( $roles ), '%s' ) );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE school_id=%d AND user_id=%d AND status='active' AND role IN ($placeholders)", array_merge( array( $school, $user ), $roles ) ) );
	}
	/**
	 * Teacher for Access.
	 */
	public static function teacher() {
		global $wpdb;
		if ( self::platform() || in_array( 'ohmylms_teacher', wp_get_current_user()->roles, true ) ) {
			return true; }
		$m = Schema::table( 'school_memberships' );
		$s = Schema::table( 'schools' );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT m.id FROM %i m JOIN %i s ON s.id=m.school_id WHERE m.user_id=%d AND m.role=\'teacher\' AND m.status=\'active\' AND s.status=\'active\' LIMIT 1', $m, $s, get_current_user_id() ) );
	}
	/**
	 * Classroom for Access.
	 *
	 * @param mixed $id Id.
	 * @param bool  $write Write.
	 */
	public static function classroom( $id, $write = false ) {
		global $wpdb;
		$classes = Schema::table( 'classes' );
		$class   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $classes, $id ), ARRAY_A );
		if ( ! $class || ( $write && 'active' !== $class['status'] ) ) {
			return false; }
		if ( ! (int) $class['school_id'] ) {
			if ( self::platform() ) {
				return $class; }
			$members = Schema::table( 'class_memberships' );
			return self::teacher() && $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE class_id=%d AND user_id=%d AND role=\'teacher\' AND status=\'active\'', $members, $id, get_current_user_id() ) ) ? $class : false;
		}
		if ( self::school( $class['school_id'] ) ) {
			return $class; }
		if ( ! self::school( $class['school_id'], array( 'teacher' ) ) ) {
			return false; }
		$members = Schema::table( 'class_memberships' );
		return $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE class_id=%d AND user_id=%d AND role=\'teacher\' AND status=\'active\'', $members, $id, get_current_user_id() ) ) ? $class : false;
	}
	/**
	 * Student for Access.
	 *
	 * @param mixed $school School.
	 * @param mixed $student Student.
	 */
	public static function student( $school, $student ) {
		global $wpdb;
		$table = Schema::table( 'school_memberships' );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d AND user_id=%d AND role=\'student\' AND status=\'active\'', $table, $school, $student ) );
	}
	/**
	 * Guardian for Access.
	 *
	 * @param mixed $school School.
	 * @param mixed $student Student.
	 */
	public static function guardian( $school, $student ) {
		global $wpdb;
		$links = Schema::table( 'guardian_links' );
		return self::student( $school, $student ) && (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d AND student_user_id=%d AND guardian_user_id=%d AND status=\'active\'', $links, $school, $student, get_current_user_id() ) );
	}
	/**
	 * Require access for Access.
	 *
	 * @param mixed $allowed Allowed.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function require_access( $allowed ) {
		if ( ! $allowed ) {
			throw new \RuntimeException( 'You do not have access to this resource.', 403 ); }
	}
}
