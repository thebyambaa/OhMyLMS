<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Which dashboard scopes a user may pick.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Scopes per user, from current Account Hub rows (never from roles alone):
 *
 * - site administrators (`manage_options`): `all`, every active school and every active class (school
 *   classes and independent ones);
 * - school administrators: their active schools and those schools' active classes;
 * - teachers: the active classes they teach;
 * - anyone else: none, so no dashboard access.
 *
 * Lists are memoised per user for the request; call {@see ScopeResolver::reset()} after changing rows.
 */
final class ScopeResolver {

	/**
	 * Scopes per user id.
	 *
	 * @var array<int,Scope[]>
	 */
	private static $memo = array();

	/**
	 * Forget memoised lists (tests, or after membership changes in the same request).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$memo = array();
	}

	/**
	 * Scopes a user may pick, in picker order: all, then each school followed by its classes, then
	 * classes whose school is not in the list, then independent classes.
	 *
	 * @param int $user_id User.
	 * @return Scope[]
	 */
	public static function available( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return array();
		}
		if ( isset( self::$memo[ $user_id ] ) ) {
			return self::$memo[ $user_id ];
		}

		if ( Access::is_site_admin( $user_id ) ) {
			$schools = Directory::all_schools();
			$classes = Directory::classes( null, true );
			$scopes  = array_merge( array( Scope::all() ), self::arrange( $schools, $classes ) );
		} else {
			$schools = Directory::administered_schools( $user_id );
			$classes = $schools ? Directory::classes( array_map( 'intval', wp_list_pluck( $schools, 'id' ) ), false ) : array();
			$known   = array_map( 'intval', wp_list_pluck( $classes, 'id' ) );
			foreach ( Directory::taught_classes( $user_id ) as $class ) {
				if ( ! in_array( (int) $class['id'], $known, true ) ) {
					$classes[] = $class;
					$known[]   = (int) $class['id'];
				}
			}
			$scopes = self::arrange( $schools, $classes );
		}

		/**
		 * Filters the scopes a user may pick on the dashboard. Only remove scopes here: every scope must
		 * still be one the user is entitled to by the Account Hub rules.
		 *
		 * @param Scope[] $scopes  Scopes in picker order.
		 * @param int     $user_id User.
		 */
		$filtered = apply_filters( 'ohmylms_dashboard_scopes', $scopes, $user_id );
		$filtered = array_values(
			array_filter(
				is_array( $filtered ) ? $filtered : array(),
				static function ( $scope ) use ( $scopes ) {
					return $scope instanceof Scope && in_array( $scope, $scopes, true );
				}
			)
		);

		self::$memo[ $user_id ] = $filtered;
		return $filtered;
	}

	/**
	 * Each school followed by its classes, then the remaining classes.
	 *
	 * @param array[] $schools Rows with id and name.
	 * @param array[] $classes Rows with id, name, school_id and school_name.
	 * @return Scope[]
	 */
	private static function arrange( array $schools, array $classes ) {
		$scopes = array();
		$placed = array();
		foreach ( $schools as $school ) {
			$scopes[] = Scope::school( (int) $school['id'], (string) $school['name'] );
			foreach ( $classes as $index => $class ) {
				if ( (int) $class['school_id'] === (int) $school['id'] ) {
					$scopes[]         = self::class_scope( $class );
					$placed[ $index ] = true;
				}
			}
		}
		foreach ( $classes as $index => $class ) {
			if ( empty( $placed[ $index ] ) ) {
				$scopes[] = self::class_scope( $class );
			}
		}
		return $scopes;
	}

	/**
	 * Class row to scope.
	 *
	 * @param array $row Row with id, name, school_id and school_name.
	 * @return Scope
	 */
	private static function class_scope( array $row ) {
		return Scope::classroom( (int) $row['id'], (string) $row['name'], (int) $row['school_id'], (string) $row['school_name'] );
	}

	/**
	 * Resolve a scope id for a user.
	 *
	 * @param string|null $value   Scope id; null for the user's first scope.
	 * @param int         $user_id User.
	 * @return Scope|WP_Error 401/403 without dashboard access, 400 for a malformed id, 403 for a scope that is
	 *                        not the user's, 404 (site administrators only) for an unknown or inactive one.
	 */
	public static function resolve( $value, $user_id ) {
		$user_id = (int) $user_id;
		$scopes  = self::available( $user_id );
		if ( ! $scopes ) {
			return Access::denied( $user_id );
		}
		if ( null === $value ) {
			return $scopes[0];
		}
		$parsed = Scope::parse( $value );
		if ( null === $parsed ) {
			return new WP_Error( 'rest_invalid_param', __( 'Invalid scope.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		foreach ( $scopes as $scope ) {
			if ( $scope->key() === $value ) {
				return $scope;
			}
		}
		// Staff never learn whether a school or class they cannot use exists.
		return Access::is_site_admin( $user_id ) && Scope::ALL !== $parsed[0] ? Scope::not_found() : Scope::forbidden();
	}
}
