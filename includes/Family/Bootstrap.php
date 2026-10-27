<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Family feature wiring.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Family;

defined( 'ABSPATH' ) || exit;

/** Rollback: define OHMYLMS_FAMILY_ENABLED as false; data and audit rows are kept. */
final class Bootstrap {

	/** Install tables when the feature is enabled. */
	public static function init() {
		if ( defined( 'OHMYLMS_FAMILY_ENABLED' ) && ! OHMYLMS_FAMILY_ENABLED ) {
			return;
		}
		add_action( 'init', array( Schema::class, 'install' ), 7 );
		Provenance::init();
	}
}
