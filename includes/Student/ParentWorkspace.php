<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Parent context for the hub, limited to approved school-linked children.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;

defined( 'ABSPATH' ) || exit;

/** A parent role label grants no child access; links are read fresh on every request. */
final class ParentWorkspace {

	const LIMIT = 100;

	/**
	 * Whether this user has the parent role or an active guardian link.
	 *
	 * @return bool
	 */
	public static function available() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$school_ready = Schema::VERSION === get_option( 'ohmylms_school_schema' ) && ( ! defined( 'OHMYLMS_SCHOOLS_ENABLED' ) || OHMYLMS_SCHOOLS_ENABLED );
		$family_ready = \OhMyLMS\Family\Schema::enabled();
		return ( $school_ready || $family_ready ) && ( in_array( 'ohmylms_parent', wp_get_current_user()->roles, true ) || ( $school_ready && (bool) self::children() ) || ( $family_ready && (bool) \OhMyLMS\Family\Service::children() ) );
	}

	/**
	 * Whether this user is a parent only, with no learner or teacher entry points.
	 *
	 * @return bool
	 */
	public static function parent_only() {
		$user = wp_get_current_user();
		return in_array( 'ohmylms_parent', $user->roles, true ) && 1 === count( $user->roles ) && ! TeacherWorkspace::available();
	}

	/**
	 * Bookmarkable parent destination.
	 *
	 * @param string $section Workspace section.
	 * @param string $view Secondary view.
	 * @return string
	 */
	public static function url( $section = 'family', $view = '' ) {
		return add_query_arg( 'hub_role', 'parent', Hub::url( $section, $view ) );
	}

	/**
	 * Approved children in active schools; the school service authorizes each row.
	 *
	 * @return array
	 */
	public static function children() {
		if ( ! is_user_logged_in() || Schema::VERSION !== get_option( 'ohmylms_school_schema' ) || ( defined( 'OHMYLMS_SCHOOLS_ENABLED' ) && ! OHMYLMS_SCHOOLS_ENABLED ) ) {
			return array();
		}
		return Service::children();
	}

	/**
	 * Overview link for one approved child within one school scope.
	 *
	 * @param int $child_id Child user ID.
	 * @param int $school_id School ID.
	 * @return string
	 */
	public static function child_url( $child_id, $school_id ) {
		return add_query_arg(
			array(
				'hub_child'  => absint( $child_id ),
				'hub_school' => absint( $school_id ),
			),
			self::url()
		);
	}

	/**
	 * A requested child/school pair only if the actor is currently approved for exactly that scope.
	 * The request values select a candidate; they never grant access.
	 *
	 * @param int $child_id Child user ID.
	 * @param int $school_id School ID.
	 * @return array|null
	 */
	public static function child( $child_id, $school_id ) {
		foreach ( self::children() as $row ) {
			if ( (int) $row['student_user_id'] === (int) $child_id && (int) $row['school_id'] === (int) $school_id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Distributed work for one authorized child; the school service rechecks access.
	 *
	 * @param array $child Row returned by child().
	 * @param int   $page Page number.
	 * @return array
	 */
	public static function work( array $child, $page = 1 ) {
		try {
			return Service::assignments( 0, (int) $child['student_user_id'], (int) $child['school_id'], max( 1, (int) $page ) );
		} catch ( \RuntimeException $error ) {
			return array();
		}
	}
}
