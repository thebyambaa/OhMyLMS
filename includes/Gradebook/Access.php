<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Who may see and change which gradebook data.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use OhMyLMS\Dashboard\Access as DashboardAccess;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\ScopeResolver;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Scoped exactly like the dashboard (docs/api/dashboard.md, "Who sees what"): site administrators see
 * every learner; school administrators the active students of their schools; teachers the rosters of their
 * classes. Every request resolves its `scope` again with `Dashboard\ScopeResolver`.
 *
 * - Reading and changing cells (override, excuse, revert): anyone whose scope contains the learner and the
 *   course.
 * - Course weights and item settings: site administrators and users who may edit the course post.
 * - Class weight overrides: users with a scope that contains the class (site administrators, the class's
 *   school administrators, its teachers).
 */
final class Access {

	/**
	 * Meta capability of the Gradebook tab and its endpoints.
	 */
	const CAPABILITY = 'ohmylms_view_gradebook';

	/**
	 * Map the meta capability (`map_meta_cap`).
	 *
	 * @param string[] $caps    Primitive capabilities.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User.
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id ) {
		if ( self::CAPABILITY !== $cap ) {
			return $caps;
		}
		if ( DashboardAccess::is_site_admin( (int) $user_id ) ) {
			return array( DashboardAccess::SITE_CAPABILITY );
		}
		return ScopeResolver::available( (int) $user_id ) ? array( 'read' ) : array( 'do_not_allow' );
	}

	/**
	 * Expose the capability to the admin app (`ohmylms_app_capabilities`).
	 *
	 * @param string[] $names Capability names.
	 * @return string[]
	 */
	public static function app_capabilities( $names ) {
		$names   = (array) $names;
		$names[] = self::CAPABILITY;
		return array_values( array_unique( $names ) );
	}

	/**
	 * Permission callback: signed in with at least one scope.
	 *
	 * @return true|WP_Error
	 */
	public static function check() {
		$user_id = get_current_user_id();
		if ( $user_id > 0 && user_can( $user_id, self::CAPABILITY ) ) {
			return true;
		}
		return new WP_Error( 'ohmylms_gradebook_cannot_view', __( 'Sorry, you are not allowed to view the gradebook.', 'ohmylms' ), array( 'status' => $user_id > 0 ? 403 : 401 ) );
	}

	/**
	 * Whether a scope contains a course.
	 *
	 * @param Scope $scope     Scope.
	 * @param int   $course_id Course.
	 * @return bool
	 */
	public static function has_course( Scope $scope, $course_id ) {
		$post = get_post( (int) $course_id );
		if ( ! $post || OHMYLMS_COURSE_CPT !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return false;
		}
		$courses = $scope->course_ids();
		return null === $courses || in_array( (int) $course_id, $courses, true );
	}

	/**
	 * Whether a scope contains a learner.
	 *
	 * @param Scope $scope   Scope.
	 * @param int   $user_id Learner.
	 * @return bool
	 */
	public static function has_learner( Scope $scope, $user_id ) {
		$learners = $scope->learner_ids();
		return null === $learners ? (bool) get_userdata( (int) $user_id ) : in_array( (int) $user_id, $learners, true );
	}

	/**
	 * Whether a scope contains an active class.
	 *
	 * @param Scope $scope    Scope.
	 * @param int   $class_id Class.
	 * @return bool
	 */
	public static function has_class( Scope $scope, $class_id ) {
		$class = Repository::classes( array( (int) $class_id ) )[ (int) $class_id ] ?? null;
		if ( ! $class || 'active' !== $class['status'] ) {
			return false;
		}
		if ( $scope->is_all() ) {
			return true;
		}
		if ( Scope::CLASSROOM === $scope->type() ) {
			return $scope->id() === (int) $class_id;
		}
		return $class['school_id'] === $scope->id();
	}

	/**
	 * Whether a user may change a course's weights and item settings.
	 *
	 * @param int $user_id   User.
	 * @param int $course_id Course.
	 * @return bool
	 */
	public static function can_manage_course( $user_id, $course_id ) {
		return DashboardAccess::is_site_admin( (int) $user_id ) || user_can( (int) $user_id, 'edit_post', (int) $course_id );
	}

	/**
	 * Whether a user may set a class's weight override: one of their scopes contains the class.
	 *
	 * @param int $user_id  User.
	 * @param int $class_id Class.
	 * @return bool
	 */
	public static function can_weight_class( $user_id, $class_id ) {
		foreach ( ScopeResolver::available( (int) $user_id ) as $scope ) {
			if ( self::has_class( $scope, $class_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * 404 for a course outside the scope (staff never learn whether it exists).
	 *
	 * @return WP_Error
	 */
	public static function course_not_found() {
		return new WP_Error( 'ohmylms_gradebook_course_not_found', __( 'This course is not in the selected school or class.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * 404 for a learner outside the scope.
	 *
	 * @return WP_Error
	 */
	public static function learner_not_found() {
		return new WP_Error( 'ohmylms_gradebook_learner_not_found', __( 'This learner is not in the selected school or class.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * 404 for a class outside the scope.
	 *
	 * @return WP_Error
	 */
	public static function class_not_found() {
		return new WP_Error( 'ohmylms_gradebook_class_not_found', __( 'This class is not in the selected school or class.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * 403 for a change the user may not make.
	 *
	 * @return WP_Error
	 */
	public static function forbidden() {
		return new WP_Error( 'ohmylms_gradebook_forbidden', __( 'Sorry, you are not allowed to change this.', 'ohmylms' ), array( 'status' => 403 ) );
	}
}
