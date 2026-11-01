<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Assets;

use OhMyLMS\Abstracts\Assets;

defined( 'ABSPATH' ) || exit();


/**
 * Plugin frontend assets implementation.
 */
class FrontendAssets extends Assets {

	/**
	 * Authored learner scripts, by handle. Empty since L7: the last file, `ohmylms.js`, was deleted
	 * with the video scripts. The `ohmylms-frontend` handle stays, with no source, in {@see self::get_scripts()}.
	 *
	 * @var array<string, string>
	 */
	const LEARNER_SCRIPTS = array();

	/**
	 * Version of an authored learner script; changes whenever the file does.
	 * A handle with no file (every handle, since L7) is the plugin version.
	 *
	 * @param string $handle Handle in {@see self::LEARNER_SCRIPTS}.
	 * @return string
	 */
	public static function learner_script_version( $handle ) {
		$path = self::LEARNER_SCRIPTS[ $handle ] ?? '';
		if ( '' === $path || ! is_file( OHMYLMS_DIR . '/' . $path ) ) {
			return OHMYLMS_VERSION;
		}
		return OHMYLMS_VERSION . '-' . (string) filemtime( OHMYLMS_DIR . '/' . $path );
	}

	/**
	 * Init for Frontend assets.
	 */
	public function init() {

		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'wp_print_scripts', array( $this, 'localize_printed_scripts' ), 5 );
	}

	/**
	 * Get the scripts to be enqueued.
	 *
	 * @return array Filtered array of scripts to be enqueued.
	 * @since 1.0.0
	 */
	public function get_scripts() {
		$scripts = array(
			// Script-less since L7: `ohmylms.js` was deleted. Elementor, Bricks, blocks, previews, the hub
			// and the exam player enqueue this handle to mark a learner page for Chrome::footer(), and
			// scripts such as elementor-widgets.js depend on it. It loads no script and no jQuery and
			// carries only `ohmylms_frontend_params.ajax_url`.
			'ohmylms-frontend' => array(
				'src'       => false,
				'deps'      => array(),
				'in_footer' => true,
				'version'   => OHMYLMS_VERSION,
			),
			// Script-less compatibility handle: classic gateway scripts (QPay, add-ons) depend on it to
			// call window.registerPaymentGatewayHandler() before the ohmylms/checkout module runs.
			'ohmylms-checkout' => array(
				'src'       => false,
				'deps'      => array(),
				'in_footer' => true,
				'version'   => OHMYLMS_VERSION,
			),
		);
		return is_array( $scripts ) ? array_filter( $scripts ) : array();
	}

	/**
	 * Get the styles to be enqueued.
	 *
	 * @return array Filtered array of styles to be enqueued.
	 * @since 1.0.0
	 */
	public function get_styles() {
		$styles = apply_filters(
			'ohmylms_enqueue_styles',
			array(
				// Keeps quiz answer inputs keyboard- and screen-reader-accessible.
				'ohmylms-quiz-a11y' => array(
					'src'     => self::get_asset_url( 'assets/css/quiz-a11y.css' ),
					'deps'    => array(),
					'version' => OHMYLMS_VERSION,
					'media'   => 'all',
					'has_rtl' => false,
				),
				'ohmylms-frontend'  => array(
					'src'     => false,
					'deps'    => array( 'ohmylms-quiz-a11y' ),
					'version' => OHMYLMS_VERSION,
					'media'   => 'all',
					'has_rtl' => false,
				),
			)
		);
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}

	/**
	 * Get script data for Frontend assets.
	 *
	 * @param mixed $handle Handle.
	 */
	public function get_script_data( $handle ) {
		switch ( $handle ) {
			case 'ohmylms-frontend':
				// Page nonces and settings live in each store's wp_interactivity_state() (lesson, video, account, ...).
				$localized_data = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				);
				break;
			default:
				$localized_data = false;
		}

		return apply_filters( 'ohmylms_get_script_data', $localized_data, $handle );
	}

	/**
	 * Load and enqueue scripts and styles.
	 *
	 * @since 1.0.0
	 */
	public function load_scripts() {
		$this->register_scripts();
		$this->register_styles();
		\OhMyLMS\Design\FrontendStyles::register();

		// Course carousels load the ohmylms/carousel module while rendering (OhMyLMS\Design\Carousel).
		if ( ohmylms_is_course_list_shortcode() ) {
			wp_enqueue_script( 'ohmylms-frontend' );
		}

		if ( is_ohmylms_buy_now() || is_ohmylms_archive() || ohmylms_is_course_list_shortcode() || ohmylms_is_membership_page() || ohmylms_is_membership_plan_shortcode() || is_ohmylms_dashboard() || is_ohmylms_profile_shortcode() || is_ohmylms_my_courses_shortcode() ) {
			wp_enqueue_script( 'ohmylms-frontend' );
		}

		// Buy buttons and the checkout load ohmylms/cart and ohmylms/checkout while rendering
		// (OhMyLMS\Extensions\Commerce); this registry runs only for gateway scripts that depend on it.
		wp_add_inline_script( 'ohmylms-checkout', 'window.PaymentGatewayHandlers=window.PaymentGatewayHandlers||{};window.registerPaymentGatewayHandler=window.registerPaymentGatewayHandler||function(id,handler){window.PaymentGatewayHandlers[id]=handler;};' );

		if ( is_ohmylms_checkout() ) {
			wp_enqueue_script( 'ohmylms-frontend' );

			wp_enqueue_style( 'ohmylms-frontend' );
			wp_enqueue_style( 'ohmylms-general' );
		}

		if ( is_ohmylms() ) {
			wp_enqueue_script( 'ohmylms-frontend' );
			wp_enqueue_style( 'ohmylms-frontend' );
			wp_enqueue_style( 'ohmylms-general' );
		}
	}

	/**
	 * Check if a script should be enqueued.
	 *
	 * @param array  $script Script.
	 * @param string $screen_id Screen identifier.
	 * @return bool
	 * @since 1.0.0
	 */
	public function should_enqueue( $script, $screen_id ): bool {
		return true;
	}
}

( new FrontendAssets() )->init();
