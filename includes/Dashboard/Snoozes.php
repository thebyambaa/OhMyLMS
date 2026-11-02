<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Per-user snoozes of dashboard items.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Snoozed "needs attention" items, kept in one user meta entry (`ohmylms_dashboard_snoozes`) as
 * `"<scope>|<item key>" => expiry (Unix time)`. A snooze lasts {@see Snoozes::DAYS} days; expired entries
 * are dropped on the next write, and at most {@see Snoozes::LIMIT} are kept (the oldest go first).
 */
final class Snoozes {

	const META_KEY = 'ohmylms_dashboard_snoozes';

	/**
	 * Days a snooze lasts.
	 */
	const DAYS = 7;

	/**
	 * Most snoozes kept per user.
	 */
	const LIMIT = 200;

	/**
	 * REST argument pattern of an item key, such as `grading` or `stalling_course:12`.
	 */
	const KEY_PATTERN = '^[a-z][a-z0-9_]{0,40}(:[0-9]{1,19})?$';

	/**
	 * Active snoozes of a user.
	 *
	 * @param int      $user_id User.
	 * @param int|null $now     Current Unix time (tests).
	 * @return array<string,int> `"<scope>|<key>"` => expiry.
	 */
	public static function active( $user_id, $now = null ) {
		$now    = null === $now ? time() : (int) $now;
		$stored = get_user_meta( (int) $user_id, self::META_KEY, true );
		$active = array();
		foreach ( is_array( $stored ) ? $stored : array() as $id => $until ) {
			if ( is_string( $id ) && (int) $until > $now ) {
				$active[ $id ] = (int) $until;
			}
		}
		return $active;
	}

	/**
	 * Expiry of one item's snooze, or null.
	 *
	 * @param array<string,int> $active From {@see Snoozes::active()}.
	 * @param Scope             $scope  Scope.
	 * @param string            $key    Item key.
	 * @return int|null
	 */
	public static function until( array $active, Scope $scope, $key ) {
		return $active[ $scope->key() . '|' . $key ] ?? null;
	}

	/**
	 * Snooze an item for {@see Snoozes::DAYS} days.
	 *
	 * @param int      $user_id User.
	 * @param Scope    $scope   Scope the item was shown in.
	 * @param string   $key     Item key.
	 * @param int|null $now     Current Unix time (tests).
	 * @return int Expiry.
	 */
	public static function snooze( $user_id, Scope $scope, $key, $now = null ) {
		$now                                  = null === $now ? time() : (int) $now;
		$active                               = self::active( $user_id, $now );
		$until                                = $now + self::DAYS * DAY_IN_SECONDS;
		$active[ $scope->key() . '|' . $key ] = $until;
		asort( $active );
		$active = array_slice( $active, -self::LIMIT, null, true );
		update_user_meta( (int) $user_id, self::META_KEY, $active );
		return $until;
	}

	/**
	 * Remove an item's snooze.
	 *
	 * @param int    $user_id User.
	 * @param Scope  $scope   Scope.
	 * @param string $key     Item key.
	 * @return bool Whether a snooze was removed.
	 */
	public static function wake( $user_id, Scope $scope, $key ) {
		$active = self::active( $user_id );
		$id     = $scope->key() . '|' . $key;
		if ( ! isset( $active[ $id ] ) ) {
			return false;
		}
		unset( $active[ $id ] );
		if ( $active ) {
			update_user_meta( (int) $user_id, self::META_KEY, $active );
		} else {
			delete_user_meta( (int) $user_id, self::META_KEY );
		}
		return true;
	}
}
