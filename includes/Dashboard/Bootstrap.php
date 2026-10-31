<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Hooks of the scoped dashboard.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Rest\V1\DashboardAtRiskController;
use OhMyLMS\Rest\V1\DashboardScopesController;
use OhMyLMS\Rest\V1\DashboardWidgetsController;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dashboard capability, the scope and widget routes and the cache invalidation hooks.
 */
final class Bootstrap {

	/**
	 * Writes after which cached dashboard data is dropped (cheap: one option update per request).
	 */
	const INVALIDATING_ACTIONS = array(
		'ohmylms_after_assignment_review',
		'ohmylms_order_status_changed',
		'ohmylms_manual_student_enrollment',
		'ohmylms_student_unenrolled',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'map_meta_cap', array( Access::class, 'map_meta_cap' ), 10, 3 );
		add_filter( 'ohmylms_app_capabilities', array( Access::class, 'app_capabilities' ) );
		add_filter( 'ohmylms_hide_admin_for_user', array( Access::class, 'keep_dashboard_open' ), 20, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		foreach ( self::INVALIDATING_ACTIONS as $action ) {
			add_action( $action, array( Cache::class, 'invalidate' ), 99, 0 );
		}
		add_filter( 'ohmylms_dashboard_at_risk_provider', array( Widgets\AtRiskCount::class, 'provide' ) );
		add_action( 'deleted_user', array( Widgets\AtRiskNudges::class, 'forget_user' ) );
		add_filter( 'ohmylms_dashboard_cache_ttl', array( Widgets\Activity::class, 'ttl' ), 10, 2 );
		add_filter( 'ohmylms_dashboard_cache_ttl', array( Widgets\SiteHealth::class, 'ttl' ), 10, 2 );
		\OhMyLMS\Gradebook\Bootstrap::init();
	}

	/**
	 * Register the REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		( new DashboardScopesController() )->register_routes();
		( new DashboardWidgetsController() )->register_routes();
		( new Widgets\MasteryController() )->register_routes();
		( new Widgets\ReviewSetsController() )->register_routes();
		( new DashboardAtRiskController() )->register_routes();
	}
}
