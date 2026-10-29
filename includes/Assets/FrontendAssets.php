<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Assets;

use OhMyLMS\Abstracts\Assets;

defined( 'ABSPATH' ) || exit();


/**
 * Plugin frontend assets implementation.
 */
class FrontendAssets extends Assets {

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
		$suffix  = '';
		$scripts = array(
			'ohmylms-slick'                  => array(
				'src'       => self::get_asset_url( 'assets/dist/frontend/slick' . $suffix . '.js' ),
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
				'version'   => '1.8.1',
			),
			'ohmylms-frontend'               => array(
				'src'       => self::get_asset_url( 'assets/dist/frontend/ohmylms' . $suffix . '.js' ),
				'deps'      => array( 'wp-i18n', 'jquery' ),
				'in_footer' => true,
				'version'   => OHMYLMS_VERSION,
			),
			'ohmylms-video-player'           => array(
				'src'       => self::get_asset_url( 'assets/dist/frontend/video-player' . $suffix . '.js' ),
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
				'version'   => OHMYLMS_VERSION,
			),
			'ohmylms-video-progress-tracker' => array(
				'src'       => self::get_asset_url( 'assets/dist/frontend/video-progress-tracker' . $suffix . '.js' ),
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
				'version'   => OHMYLMS_VERSION,
			),
			'ohmylms-add-to-cart'            => array(
				'src'       => self::get_asset_url( 'assets/dist/frontend/add-to-cart' . $suffix . '.js' ),
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
				'version'   => OHMYLMS_VERSION,
			),
			'ohmylms-checkout'               => array(
				'src'       => self::get_asset_url( 'assets/dist/frontend/checkout' . $suffix . '.js' ),
				'deps'      => array( 'wp-i18n', 'jquery' ),
				'in_footer' => true,
				'version'   => OHMYLMS_VERSION . '-' . filemtime( OHMYLMS_DIR . '/assets/dist/frontend/checkout.js' ),
			),
			'ohmylms-tax-calculation'        => array(
				'src'       => self::get_asset_url( 'assets/dist/frontend/tax-calculation' . $suffix . '.js' ),
				'deps'      => array( 'jquery' ),
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
				$localized_data = array(
					'ajax_url'                    => admin_url( 'admin-ajax.php' ),
					'current_student_id'          => get_current_user_id(),
					'nonce'                       => wp_create_nonce( 'ohmylms' ),
					'load_more_nonce'             => wp_create_nonce( 'load_more_nonce' ),
					'search_filter_nonce'         => wp_create_nonce( 'search_filter_nonce' ),
					'video_completion_threshold'  => max( 1, min( 100, (int) get_option( 'ohmylms_video_completion_threshold', 90 ) ) ),
					'is_creator_page'             => ohmylms_is_content_page(),
					'content_protection'          => get_option( 'ohmylms_content_protection', 'no' ),
					'email_verification_required' => \OhMyLMS\Services\EmailVerificationService::is_required(),
				);

				if ( ohmylms_is_content_page() ) {
					$localized_data['lesson_completed_nonce']      = wp_create_nonce( 'lesson_completed_nonce' );
					$localized_data['assignment_submission_nonce'] = wp_create_nonce( 'assignment_submission_nonce' );
					$localized_data['save_remaining_time']         = wp_create_nonce( 'save_remaining_time' );
					$localized_data['lesson_access_nonce']         = wp_create_nonce( 'lesson_access_nonce' );
					$localized_data['quiz_exit_submission']        = wp_create_nonce( 'quiz_exit_submission' );
					$localized_data['video_progress_nonce']        = wp_create_nonce( 'video_progress_nonce' );
				}

				if ( ohmylms_is_single_course_page() || is_ohmylms_dashboard() || is_ohmylms_my_courses_shortcode() || \OhMyLMS\Student\Hub::is_page() ) {
					$localized_data['course_drop_nonce']          = wp_create_nonce( 'course_drop_nonce' );
					$localized_data['download_certificate_nonce'] = wp_create_nonce( 'download_certificate_nonce' );
				}

				if ( is_ohmylms_profile_shortcode() || \OhMyLMS\Student\Hub::is_page() ) {
					$localized_data['student_profile_cover_upload_nonce'] = wp_create_nonce( 'student_profile_cover_image' );
					$localized_data['student_profile_cover_delete_nonce'] = wp_create_nonce( 'student_profile_cover_image_delete' );
					$localized_data['student_profile_image_upload_nonce'] = wp_create_nonce( 'student_profile_image_upload' );
				}

				if ( ohmylms_is_membership_page() || ohmylms_is_membership_plan_shortcode() || is_ohmylms_dashboard() || \OhMyLMS\Student\Hub::is_page() ) {
					$localized_data['cancel_membership_nonce'] = wp_create_nonce( 'cancel_membership_nonce' );
				}
				break;
			case 'ohmylms-add-to-cart':
				$localized_data = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'add-to-cart' ),
				);
				break;
			case 'ohmylms-checkout':
				$localized_data = array(
					'ajax_url'                => admin_url( 'admin-ajax.php' ),
					'nonce'                   => wp_create_nonce( 'add-to-cart' ),
					'apply_coupon_nonce'      => wp_create_nonce( 'apply-coupon' ),
					'remove_coupon_nonce'     => wp_create_nonce( 'remove-coupon' ),
					'gateway_required_fields' => self::get_gateway_required_checkout_fields(),
				);
				break;
			case 'ohmylms-tax-calculation':
				$localized_data = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ohmylms_calculate_tax' ),
				);
				break;
			default:
				$localized_data = false;
		}

		return apply_filters( 'ohmylms_get_script_data', $localized_data, $handle );
	}

	/**
	 * Map of payment gateway id => checkout field keys that gateway requires.
	 *
	 * Feeds the checkout script so it can toggle the "required" state of fields
	 * (e.g. phone) live when the buyer switches payment method. Server-side
	 * validation in Checkout::validate_gateway_required_fields() is authoritative.
	 *
	 * @since 1.2.19
	 * @return array<string, string[]>
	 */
	private static function get_gateway_required_checkout_fields() {
		$map = array();

		if ( ! function_exists( 'OhMyLMS\Commerce\ecommerce' ) ) {
			return $map;
		}

		$gateways = \OhMyLMS\Commerce\ecommerce()->gateways();
		if ( ! $gateways || ! method_exists( $gateways, 'get_available_payment_gateways' ) ) {
			return $map;
		}

		foreach ( (array) $gateways->get_available_payment_gateways() as $gateway ) {
			if ( ! is_object( $gateway ) || ! isset( $gateway->id ) || ! method_exists( $gateway, 'get_required_checkout_fields' ) ) {
				continue;
			}
			$map[ $gateway->id ] = $gateway->get_required_checkout_fields();
		}

		return $map;
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

		$layout       = get_option( 'ohmylms_archive_page_layout', 'grid' );
		$layout_style = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );

		if ( ohmylms_is_single_course_page() ||
			( is_ohmylms_archive() || ohmylms_is_course_list_shortcode() &&
			'grid' === $layout &&
			( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style ) )
		) {
			wp_enqueue_script( 'ohmylms-slick' );
		}

		if ( ohmylms_is_course_list_shortcode() ) {
			wp_enqueue_script( 'ohmylms-slick' );
			wp_enqueue_script( 'ohmylms-frontend' );
		}

		if ( isset( $_GET['ohmylms-certificate-data'] ) ) {
			wp_enqueue_script(
				'html2pdf',
				plugin_dir_url( __FILE__ ) . '/packages/html2pdf.bundle.min.js',
				array(),
				'0.10.1',
				true
			);
		}

		if ( is_ohmylms_buy_now() || is_ohmylms_archive() || ohmylms_is_course_list_shortcode() || ohmylms_is_membership_page() || ohmylms_is_membership_plan_shortcode() || is_ohmylms_dashboard() || is_ohmylms_profile_shortcode() || is_ohmylms_my_courses_shortcode() || ( isset( $_GET['ohmylms-certificate-data'] ) ) ) {
			wp_enqueue_script( 'ohmylms-frontend' );
			wp_enqueue_script( 'ohmylms-add-to-cart' );
		}

		if ( ohmylms_is_single_course_page() ) {
			wp_enqueue_script( 'ohmylms-add-to-cart' );
		}

		if ( is_ohmylms_checkout() ) {
			wp_enqueue_script( 'ohmylms-checkout' );
			wp_enqueue_script( 'ohmylms-tax-calculation' );
			wp_enqueue_script( 'ohmylms-frontend' );

			wp_enqueue_style( 'ohmylms-frontend' );
			wp_enqueue_style( 'ohmylms-general' );
		}

		if ( is_ohmylms() ) {
			wp_enqueue_script( 'ohmylms-frontend' );
			wp_enqueue_style( 'ohmylms-frontend' );
			wp_enqueue_style( 'ohmylms-general' );
		}

		if ( ohmylms_is_content_page() ) {
			wp_enqueue_script( 'ohmylms-video-player' );
			wp_enqueue_script( 'ohmylms-video-progress-tracker' );
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
