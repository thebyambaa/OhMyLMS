<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Hooks for skill tracking.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

defined( 'ABSPATH' ) || exit;

/** Registers the background rebuild that follows a change of policy, and keeps guidance current. See docs/PERFORMANCE.md. */
final class Bootstrap {

	/** Hook the rebuild. */
	public static function init() {
		add_action( 'ohmylms_performance_rebuild', array( Engine::class, 'rebuild_step' ), 10, 2 );
		add_action( 'ohmylms_skill_state_updated', array( Guidance::class, 'flush' ), 10, 0 );
	}
}
