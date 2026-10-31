<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * The at-risk widget as the "learners at high risk" source of Needs attention.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\AtRiskProvider;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;

defined( 'ABSPATH' ) || exit;

/**
 * Registered on `ohmylms_dashboard_at_risk_provider`: counts the `high` learners of the at-risk widget's
 * (cached) data for the same scope and period, and links to the dashboard in that scope.
 */
final class AtRiskCount implements AtRiskProvider {

	/**
	 * Filter callback: this provider unless another one was given.
	 *
	 * @param AtRiskProvider|null $provider Provider so far.
	 * @return AtRiskProvider
	 */
	public static function provide( $provider ) {
		return $provider instanceof AtRiskProvider ? $provider : new self();
	}

	/**
	 * Learners of the scope at high risk.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return int
	 */
	public function high_risk_count( Scope $scope, Period $period ) {
		$data = AtRisk::cached( $scope, $period );
		return (int) ( $data['counts']['high'] ?? 0 );
	}

	/**
	 * The dashboard in that scope, where the at-risk widget lists them.
	 *
	 * @param Scope $scope Scope.
	 * @return string
	 */
	public function route( Scope $scope ) {
		return '/dashboard?scope=' . $scope->key();
	}
}
