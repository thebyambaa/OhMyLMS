<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Source asset overrides and the SDK used by the shipped admin application.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/** Source asset overrides and the SDK used by the shipped admin application. */
final class SourceAssets {
	/** Register asset hooks for the LMS application. */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 11 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 11 );
		if ( ! defined( 'OHMYLMS_SOURCE_ASSETS' ) || ! OHMYLMS_SOURCE_ASSETS ) {
			return; }
		add_filter( 'script_loader_src', array( __CLASS__, 'url' ), 20 );
		add_filter( 'style_loader_src', array( __CLASS__, 'url' ), 20 );
	}
	/**
	 * Resolve source assets when the source build is enabled.
	 *
	 * @param string $url Published asset URL.
	 * @return string Resolved URL.
	 */
	public static function url( $url ) {
		$base = plugins_url( '/', OHMYLMS_FILE );
		if ( strpos( $url, $base . 'assets/' ) !== 0 ) {
			return $url; }
		$relative = strtok( substr( $url, strlen( $base ) ), '?' );
		$file     = OHMYLMS_DIR . '/build/' . $relative;
		if ( ! is_file( $file ) ) {
			return $url; }
		return $base . 'build/' . $relative . '?ver=' . substr( hash_file( 'sha256', $file ), 0, 12 );
	}
	/** Load the application SDK; native screens use their dedicated editor bundle. */
	public static function enqueue() {
		$screen = is_admin() ? get_current_screen() : null;
		// The authored preview owns its bootstrap. Keep the recovered SDK on existing screens
		// until the new app implements the documented extension surfaces before cutover.
		if ( $screen && \OhMyLMS\Admin\AppPage::is_app_screen( $screen->id ) ) {
			return;
		}
		if ( $screen && in_array( $screen->post_type, array( 'ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-question' ), true ) && $screen->is_block_editor() ) {
			return;
		}
		$directory  = defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ? 'build/sdk' : 'assets/dist/admin';
		$asset_file = OHMYLMS_DIR . '/' . $directory . '/extensions.asset.php';
		if ( ! is_file( $asset_file ) ) {
			return; }
		$asset = require $asset_file;
		wp_enqueue_script( 'ohmylms-extension-sdk', plugins_url( $directory . '/extensions.js', OHMYLMS_FILE ), $asset['dependencies'], $asset['version'], true );
		wp_localize_script( 'ohmylms-extension-sdk', 'ohmylmsTabPreferences', TabPreferences::read() );
		wp_localize_script( 'ohmylms-extension-sdk', 'ohmylmsPlayerTemplates', \OhMyLMS\Quiz\PlayerTemplates::choices() );
		wp_localize_script( 'ohmylms-extension-sdk', 'ohmylmsExtensionManifest', array_merge( Registry::manifest(), array( 'settings' => Settings::manifest() ) ) );
		wp_localize_script(
			'ohmylms-extension-sdk',
			'ohmylmsAssessment',
			array(
				'versioned'     => \OhMyLMS\Assessment\Engine::versioned(),
				'bankUi'        => \OhMyLMS\Assessment\Engine::bank_ui(),
				'skills'        => true,
				'practice'      => \OhMyLMS\Assessment\Engine::practice(),
				'formAuthoring' => defined( 'OHMYLMS_FORM_AUTHORING' ) && OHMYLMS_FORM_AUTHORING,
				'isAdmin'       => current_user_can( 'manage_options' ),
			)
		);
		$scripts = wp_scripts();
		if ( isset( $scripts->registered['ohmylms-vendor'] ) ) {
			$scripts->registered['ohmylms-vendor']->deps = array_values( array_unique( array_merge( $scripts->registered['ohmylms-vendor']->deps, array( 'wp-preferences', 'wp-keyboard-shortcuts' ) ) ) );
		}
		$before = $scripts->queue;
		do_action( 'ohmylms_enqueue_extension_scripts', 'ohmylms-extension-sdk' );
		if ( isset( $scripts->registered['ohmylms'] ) ) {
			$scripts->registered['ohmylms']->deps = array_values( array_unique( array_merge( $scripts->registered['ohmylms']->deps, array( 'ohmylms-extension-sdk' ), array_diff( $scripts->queue, $before ) ) ) );
		}
	}
}
