<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Locally packaged math presentation and optional rollout overrides.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/** Keep content and grading independent of presentation availability. */
final class MathLive {
	/** Stored equation marker syntax, independent of whether presentation is enabled. */
	const MARKER = '\[\[ohmylms-math:latex:(?:inline|display)\]\][\s\S]{1,2000}?\[\[/ohmylms-math\]\]';
	/** Register asset hooks without changing assessment contracts. */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'configure' ), 12 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'configure' ), 12 );
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'content' ) );
	}

	/**
	 * Enable the installed renderer unless the site explicitly disables it.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$enabled = ! defined( 'OHMYLMS_MATHLIVE_ENABLED' ) || OHMYLMS_MATHLIVE_ENABLED;
		return (bool) apply_filters( 'ohmylms_mathlive_enabled', $enabled );
	}

	/** Register a lazy runtime and make its local URLs available to editors. */
	public static function configure() {
		$config = array(
			'enabled' => self::enabled(),
			'runtime' => plugins_url( 'assets/dist/admin/math-runtime.js', OHMYLMS_FILE ),
			'fonts'   => plugins_url( 'assets/dist/mathlive/fonts', OHMYLMS_FILE ),
			'style'   => plugins_url( 'assets/css/mathlive.css', OHMYLMS_FILE ),
		);
		wp_register_script( 'ohmylms-mathlive', $config['runtime'], array(), '0.111.1', true );
		wp_register_script( 'ohmylms-mathlive-loader', plugins_url( 'assets/interactivity/math-loader.js', OHMYLMS_FILE ), array(), '0.111.1', true );
		wp_localize_script( 'ohmylms-mathlive-loader', 'ohmylmsMath', $config );
		wp_add_inline_script( 'ohmylms-mathlive', 'window.ohmylmsMath = ' . wp_json_encode( $config ) . ';', 'before' );
		wp_localize_script( 'ohmylms-extension-sdk', 'ohmylmsMath', $config );
	}

	/** Enqueue on question/practice pages only; editor fields load on demand. */
	public static function enqueue() {
		if ( ! self::enabled() ) {
			return;
		}
		wp_enqueue_script( 'ohmylms-mathlive' );
		wp_enqueue_style( 'ohmylms-mathlive', plugins_url( 'assets/css/mathlive.css', OHMYLMS_FILE ), array(), '0.111.1' );
	}

	/** Detect math in dynamically fetched practice questions before loading the runtime. */
	public static function enqueue_loader() {
		if ( self::enabled() ) {
			wp_enqueue_script( 'ohmylms-mathlive-loader' );
		}
	}

	/**
	 * Load readonly rendering for explicit markers in previews and reviews.
	 *
	 * @param string $html Existing sanitized template output.
	 * @return string Unchanged template output.
	 */
	public static function content( $html ) {
		if ( false !== strpos( $html, '[[ohmylms-math:latex:' ) || preg_match( '/\$\S|\\\\[\[(]/', $html ) ) {
			self::enqueue();
		}
		return $html;
	}
}
