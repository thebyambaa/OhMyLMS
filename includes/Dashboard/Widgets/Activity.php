<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Happening now": the latest learner events of the scope.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\ActivityFeed;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * `data: { events[], limit }`, newest first, each `{ id, type, at, text }` (docs/api/dashboard.md,
 * "Happening now"). Events of the period only; cached for 60 seconds (Bootstrap).
 */
final class Activity extends Widget {

	/**
	 * Cache lifetime in seconds.
	 */
	const TTL = 60;

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return 'activity';
	}

	/**
	 * Events of a scope and period.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return array
	 */
	public function compute( Scope $scope, Period $period ) {
		$range = $period->range();
		$limit = ActivityFeed::limit();
		return array(
			'events' => ActivityFeed::recent( $scope, $range->start_sql(), $range->end_sql(), $limit ),
			'limit'  => $limit,
		);
	}

	/**
	 * Shorter cache lifetime for this widget (`ohmylms_dashboard_cache_ttl`).
	 *
	 * @param int    $ttl    Seconds.
	 * @param string $widget Widget id.
	 * @return int
	 */
	public static function ttl( $ttl, $widget ) {
		return 'activity' === $widget ? min( (int) $ttl, self::TTL ) : $ttl;
	}
}
