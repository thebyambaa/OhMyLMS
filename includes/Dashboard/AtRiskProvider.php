<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Contract of the at-risk learner source used by "Needs attention".
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Return an implementation from the `ohmylms_dashboard_at_risk_provider` filter to show the "learners at
 * high risk" item. Without one, the item is not shown at all.
 */
interface AtRiskProvider {

	/**
	 * Learners of the scope at high risk now. Must apply the same scope limits as every widget.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return int
	 */
	public function high_risk_count( Scope $scope, Period $period );

	/**
	 * Internal app route that lists them (for example the At-risk widget's screen), or null.
	 *
	 * @param Scope $scope Scope.
	 * @return string|null
	 */
	public function route( Scope $scope );
}
