<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * School administration workspace inside the hub (not the WordPress dashboard).
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;

defined( 'ABSPATH' ) || exit;

/** Navigation only; every read and write is authorized again by the school services. */
final class SchoolWorkspace {

	/**
	 * Whether schools are installed and enabled.
	 *
	 * @return bool
	 */
	private static function ready() {
		return is_user_logged_in() && Schema::VERSION === get_option( 'ohmylms_school_schema' ) && ( ! defined( 'OHMYLMS_SCHOOLS_ENABLED' ) || OHMYLMS_SCHOOLS_ENABLED );
	}

	/**
	 * Active schools the signed-in user administers (platform administrators see all active schools).
	 *
	 * @return array
	 */
	public static function schools() {
		if ( ! self::ready() ) {
			return array();
		}
		return array_values(
			array_filter(
				Service::schools(),
				static function ( $school ) {
					return 'active' === $school['status'] && Access::school( (int) $school['id'] );
				}
			)
		);
	}

	/**
	 * Whether the user may open the school workspace.
	 *
	 * @return bool
	 */
	public static function available() {
		return self::ready() && (bool) self::schools();
	}

	/**
	 * Bookmarkable school workspace destination.
	 *
	 * @param string $section Workspace section.
	 * @param string $view Secondary view.
	 * @param int    $school_id Optional school to keep selected.
	 * @return string
	 */
	public static function url( $section = 'school-overview', $view = '', $school_id = 0 ) {
		$school_id = $school_id ? absint( $school_id ) : absint( get_query_var( 'hub_school', 0 ) );
		$url       = add_query_arg( 'hub_role', 'school', Hub::url( $section, $view ) );
		return $school_id ? add_query_arg( 'hub_school', absint( $school_id ), $url ) : $url;
	}

	/**
	 * The selected school: the requested one when currently administered, else the first.
	 *
	 * @return array|null
	 */
	public static function current() {
		$schools   = self::schools();
		$requested = absint( get_query_var( 'hub_school', 0 ) );
		foreach ( $schools as $school ) {
			if ( (int) $school['id'] === $requested ) {
				return $school;
			}
		}
		return $schools ? $schools[0] : null;
	}

	/**
	 * Headline numbers for one administered school.
	 *
	 * @param int $school_id School ID.
	 * @return array
	 */
	public static function counts( $school_id ) {
		Access::require_access( Access::school( (int) $school_id ) );
		global $wpdb;
		$m   = Schema::table( 'school_memberships' );
		$c   = Schema::table( 'classes' );
		$i   = Schema::table( 'school_invitations' );
		$row = array();
		foreach ( array( 'student', 'teacher', 'school_admin' ) as $role ) {
			$row[ $role ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE school_id=%d AND role=%s AND status='active'", $m, $school_id, $role ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh authorization-scoped count.
		}
		$row['classes']     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE school_id=%d AND status='active'", $c, $school_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh authorization-scoped count.
		$row['invitations'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE school_id=%d AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s', $i, $school_id, Service::now() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh authorization-scoped count.
		return $row;
	}
}
