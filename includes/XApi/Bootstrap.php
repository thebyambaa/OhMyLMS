<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bootstrap for the xAPI emitter.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the xAPI emitter: schema, listeners, background delivery and the settings screen.
 */
final class Bootstrap {

	/**
	 * Register everything. When switched off, listeners return immediately and delivery
	 * is unscheduled; retention housekeeping remains available.
	 */
	public static function init() {
		Integration::init();
		add_action( 'rest_api_init', array( SettingsController::class, 'register_routes' ) );
		add_action( 'init', array( Schema::class, 'install' ), 6 );
		add_filter( 'cron_schedules', array( Processor::class, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- a one-minute interval is required for timely delivery.
		add_action( 'init', array( Processor::class, 'schedule' ), 31 );
		add_action( Processor::HOOK, array( Processor::class, 'run_scheduled' ) );
		add_action( Processor::PRUNE_HOOK, array( Processor::class, 'prune' ) );
		Listeners::init();
		if ( is_admin() ) {
			AdminPage::init();
		}
		do_action( 'ohmylms_xapi_loaded' );
	}
}
