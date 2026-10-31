<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Base class of a dashboard widget's server side.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * One widget endpoint: `GET /ohmylms/v1/dashboard/widgets/<id>?scope=&period=` (docs/api/dashboard.md).
 *
 * The controller checks dashboard access, resolves the scope for the current user and the period, then:
 *
 * 1. {@see Widget::available()} — false answers 403 `ohmylms_dashboard_widget_unavailable`;
 * 2. {@see Widget::compute()} through {@see Cache::remember()} — figures for the scope and period, the same
 *    for everyone who may see that scope (never read the current user here);
 * 3. {@see Widget::present()} — per-user, uncached adjustments (snoozes, links the user may open).
 *
 * Keep personal data out: counts, titles of courses and content, and internal routes only.
 */
abstract class Widget {

	/**
	 * Widget id: lowercase letters, digits and dashes (`needs-attention`).
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Figures for a scope and period. Cached per widget, scope and period.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return array JSON-serialisable data.
	 * @throws \RuntimeException On a database error (answers 500 `ohmylms_dashboard_query_failed`).
	 */
	abstract public function compute( Scope $scope, Period $period );

	/**
	 * Whether the widget applies to a scope for a user.
	 *
	 * @param Scope $scope   Resolved scope.
	 * @param int   $user_id User.
	 * @return bool
	 */
	public function available( Scope $scope, $user_id ) {
		return true;
	}

	/**
	 * Per-user view of cached data.
	 *
	 * @param array  $data    Data from {@see Widget::compute()}.
	 * @param Scope  $scope   Scope.
	 * @param Period $period  Period.
	 * @param int    $user_id User.
	 * @return array
	 */
	public function present( array $data, Scope $scope, Period $period, $user_id ) {
		return $data;
	}
}
