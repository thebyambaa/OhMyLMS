<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Read-only teacher workspace over existing school permissions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Schema;

defined( 'ABSPATH' ) || exit;

/** Role switching changes navigation only; access always comes from school services. */
final class TeacherWorkspace {

	/**
	 * Active academic years in schools this actor administers.
	 *
	 * @return array
	 */
	public static function contexts() {
		if ( ! self::available() ) {
			return array();
		}
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT y.id,y.label,s.name FROM %i y JOIN %i s ON s.id=y.school_id AND s.status='active' WHERE y.status='active' AND (%d=1 OR EXISTS (SELECT 1 FROM %i m WHERE m.school_id=s.id AND m.user_id=%d AND m.role='school_admin' AND m.status='active')) ORDER BY s.name,y.starts_at DESC,y.id LIMIT 100",
				Schema::table( 'academic_years' ),
				Schema::table( 'schools' ),
				Access::platform() ? 1 : 0,
				Schema::table( 'school_memberships' ),
				get_current_user_id()
			),
			ARRAY_A
		);
	}

	/**
	 * Whether this user has an existing teacher role or active relationship.
	 *
	 * @return bool
	 */
	public static function available() {
		return is_user_logged_in() && Schema::VERSION === get_option( 'ohmylms_school_schema' ) && ( ! defined( 'OHMYLMS_SCHOOLS_ENABLED' ) || OHMYLMS_SCHOOLS_ENABLED ) && Access::teacher();
	}

	/**
	 * Build a bookmarkable teacher destination without changing a user's role.
	 *
	 * @param string $section Workspace section.
	 * @param string $view Secondary view.
	 * @return string
	 */
	public static function url( $section = 'classes', $view = '' ) {
		return add_query_arg( 'hub_role', 'teacher', Hub::url( $section, $view ) );
	}

	/**
	 * One page of active classes, rechecked by the authoritative access service.
	 *
	 * @param int    $page Page number.
	 * @param string $status Active or archived classes.
	 * @return array
	 */
	public static function classes( $page = 1, $status = 'active' ) {
		if ( ! self::available() ) {
			return array();
		}
		global $wpdb;
		$status  = 'archived' === $status ? 'archived' : 'active';
		$classes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.* FROM %i c WHERE c.status=%s AND (%d=1 OR EXISTS (SELECT 1 FROM %i m WHERE m.class_id=c.id AND m.user_id=%d AND m.role='teacher' AND m.status='active') OR EXISTS (SELECT 1 FROM %i sm WHERE sm.school_id=c.school_id AND sm.user_id=%d AND sm.role='school_admin' AND sm.status='active')) ORDER BY c.name,c.id LIMIT 13 OFFSET %d",
				Schema::table( 'classes' ),
				$status,
				Access::platform() ? 1 : 0,
				Schema::table( 'class_memberships' ),
				get_current_user_id(),
				Schema::table( 'school_memberships' ),
				get_current_user_id(),
				( min( 10000, max( 1, absint( $page ) ) ) - 1 ) * 12
			),
			ARRAY_A
		);
		return array_values(
			array_filter(
				$classes,
				static function ( $class_row ) {
					return (bool) Access::classroom( $class_row['id'] );
				}
			)
		);
	}
}
