<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Core modules always load, bundled modules use Add-ons switches; project modules use trusted configuration.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/** Core modules always load, bundled modules use Add-ons switches; project modules use trusted configuration. */
final class Modules {
	/**
	 * Load for Modules.
	 */
	public static function load() {
		$enabled = defined( 'OHMYLMS_ENABLED_MODULES' ) ? OHMYLMS_ENABLED_MODULES : array();
		$enabled = apply_filters( 'ohmylms_enabled_modules', $enabled );
		$bundled = Addons::IDS;
		$enabled = array_diff( (array) $enabled, array_merge( $bundled, Addons::CORE, array( 'question_bank' ) ) );
		foreach ( Addons::CORE as $id ) {
			$enabled[] = $id; }
		foreach ( $bundled as $id ) {
			if ( Addons::enabled( $id ) ) {
				$enabled[] = $id; }
		}
		foreach ( array_unique( (array) $enabled ) as $id ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-z][a-z0-9_-]*$/D', $id ) ) {
				continue; }
			$root = realpath( OHMYLMS_DIR . '/modules' );
			$file = realpath( OHMYLMS_DIR . '/modules/' . $id . '/module.php' );
			if ( ! $root || ! $file || strpos( $file, $root . DIRECTORY_SEPARATOR ) !== 0 ) {
				continue; }
			require_once $file;
		}
	}
}
