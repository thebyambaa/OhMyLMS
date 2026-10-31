<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Site health": set-up checks for site administrators.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\SiteHealthChecks;
use OhMyLMS\Dashboard\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * `data: { checks[], passed, total }` (docs/api/dashboard.md, "Site health"). Only site administrators
 * in the `all` scope; everyone else gets 403 `ohmylms_dashboard_widget_unavailable`. Not cached: the
 * checks read a few options, and a fix should show at once.
 */
final class SiteHealth extends Widget {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return 'site-health';
	}

	/**
	 * Site administrators in the all-schools scope.
	 *
	 * @param Scope $scope   Scope.
	 * @param int   $user_id User.
	 * @return bool
	 */
	public function available( Scope $scope, $user_id ) {
		return $scope->is_all() && Access::is_site_admin( (int) $user_id );
	}

	/**
	 * The checks.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return array
	 */
	public function compute( Scope $scope, Period $period ) {
		$checks = SiteHealthChecks::all();
		return array(
			'checks' => $checks,
			'passed' => count(
				array_filter(
					$checks,
					static function ( $check ) {
						return 'ok' === $check['status'];
					}
				)
			),
			'total'  => count( $checks ),
		);
	}

	/**
	 * No caching for this widget (`ohmylms_dashboard_cache_ttl`).
	 *
	 * @param int    $ttl    Seconds.
	 * @param string $widget Widget id.
	 * @return int
	 */
	public static function ttl( $ttl, $widget ) {
		return 'site-health' === $widget ? 0 : $ttl;
	}
}
