<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Front-end assets for versioned quiz delivery (autosave and resume).
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/** Front-end assets for versioned quiz delivery (autosave and resume). */
final class Delivery {
	/**
	 * Needed.
	 *
	 * @var mixed
	 */
	private static $needed = false;

	/** Called by the quiz template when it renders a versioned attempt. */
	public static function require_script() {
		self::$needed = true;
		self::register();
		wp_enqueue_script( 'ohmylms-quiz-autosave' );
		wp_enqueue_style( 'ohmylms-practice', plugins_url( 'assets/css/practice.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
	}

	/**
	 * Enqueue for Delivery.
	 */
	public static function enqueue() {
		if ( self::$needed ) {
			self::register();
			wp_enqueue_script( 'ohmylms-quiz-autosave' ); }
	}

	/**
	 * Register for Delivery.
	 */
	private static function register() {
		if ( wp_script_is( 'ohmylms-quiz-autosave', 'registered' ) ) {
			return; }
		wp_register_script( 'ohmylms-quiz-autosave', plugins_url( 'assets/js/quiz-autosave.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION, true );
		wp_localize_script(
			'ohmylms-quiz-autosave',
			'ohmylmsQuizAutosave',
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'saving'  => __( 'Saving…', 'ohmylms' ),
					'saved'   => __( 'All answers saved', 'ohmylms' ),
					'offline' => __( 'Not saved yet — will retry', 'ohmylms' ),
					'closed'  => __( 'Time is up. Later changes are not saved.', 'ohmylms' ),
				),
			)
		);
	}
}
