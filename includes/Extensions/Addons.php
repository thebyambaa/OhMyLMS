<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bundled modules share the existing Add-ons settings and stay opt-in. Skills (like the Question Bank)
 * is part of the core product: it is always on, has no Add-ons card and cannot be switched off.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/**
 * Bundled modules share the existing Add-ons settings and stay opt-in. Skills (like the Question Bank)
 * is part of the core product: it is always on, has no Add-ons card and cannot be switched off.
 */
final class Addons {
	/** Former add-ons that are now always on. */
	const CORE = array( 'skills' );
	/** Bundled modules that still have an Add-ons switch. */
	const IDS = array();

	/**
	 * Definitions for Addons.
	 */
	public static function definitions() {
		return array();
	}

	/**
	 * Enabled for Addons.
	 *
	 * @param mixed $id Id.
	 */
	public static function enabled( $id ) {
		if ( in_array( $id, self::CORE, true ) ) {
			return true; }
		$settings = get_option( 'ohmylms_integrations', array() );
		return is_array( $settings ) && 1 === (int) ( $settings[ $id ]['is_enable'] ?? 0 );
	}

	/**
	 * Init for Addons.
	 */
	public static function init() {
		add_filter( 'ohmylms_integrations', array( __CLASS__, 'manifest' ) );
	}

	/**
	 * Manifest for Addons.
	 *
	 * @param mixed $manifest Manifest.
	 */
	public static function manifest( $manifest ) {
		unset( $manifest['question_bank'], $manifest['skills'] );
		foreach ( self::definitions() as $id => $definition ) {
			$manifest[ $id ] = array_merge(
				$definition,
				array(
					'categories'  => array( 'course-enhancements' ),
					'hasSettings' => false,
					'class'       => '',
					'is_valid'    => true,
					'is_enable'   => self::enabled( $id ) ? 1 : 0,
				)
			);
		}
		return $manifest;
	}
}
