<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WPBakery Manager
 *
 * Manages all WPBakery Page Builder elements for OhMyLMS
 *
 * @package OhMyLMS\WPBakery
 * @since 1.0.0
 */

namespace OhMyLMS\WPBakery;

defined( 'ABSPATH' ) || exit;

/**
 * WPBakeryManager class
 */
class WPBakeryManager {

	/**
	 * Instance of this class
	 *
	 * @var WPBakeryManager
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return WPBakeryManager
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Use multiple hooks to ensure elements are registered.
		add_action( 'vc_before_init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'init' ), 20 );
		add_filter( 'is_ohmylms_page', array( $this, 'filter_is_ohmylms_page' ) );
		add_filter( 'is_ohmylms_checkout_page', array( $this, 'filter_is_ohmylms_page' ) );

		// Add WPBakery preview mode filter.
		add_filter( 'ohmylms_wpbakery_preview_mode', array( $this, 'is_wpbakery_preview_mode' ) );

		// Add script to handle real-time preview updates in WPBakery backend editor.
		add_action( 'admin_footer', array( $this, 'add_backend_editor_refresh_script' ) );
	}

	/**
	 * Check if we're in WPBakery preview mode
	 *
	 * @return bool
	 */
	public function is_wpbakery_preview_mode() {
		// Check if WPBakery inline editor is active.
		if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
			return true;
		}

		// Check if in WPBakery frontend editor.
		if ( isset( $_GET['vc_editable'] ) && 'true' === $_GET['vc_editable'] ) {
			return true;
		}

		// Check if in WPBakery backend editor preview.
		if ( isset( $_GET['vc_action'] ) && 'vc_inline' === $_GET['vc_action'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Initialize WPBakery integration
	 *
	 * @return void
	 */
	public function init() {
		// Check if WPBakery Page Builder (Visual Composer) is installed and activated.
		if ( ! defined( 'WPB_VC_VERSION' ) ) {
			return;
		}

		// Prevent double initialization.
		static $initialized = false;
		if ( $initialized ) {
			return;
		}
		$initialized = true;

		// Register elements.
		$this->register_elements();
	}

	/**
	 * Register WPBakery elements
	 *
	 * @return void
	 */
	private function register_elements() {
		// Check if vc_map function exists.
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		// Define elements to register.
		$elements = array(
			array(
				'file'  => OHMYLMS_PATH . '/includes/WPBakery/Elements/CourseListElement.php',
				'class' => '\OhMyLMS\WPBakery\Elements\CourseListElement',
			),
			array(
				'file'  => OHMYLMS_PATH . '/includes/WPBakery/Elements/CheckoutElement.php',
				'class' => '\OhMyLMS\WPBakery\Elements\CheckoutElement',
			),
		);

		// Buy now and offer button elements.
			$elements[] = array(
				'file'  => OHMYLMS_PATH . '/includes/WPBakery/Elements/BuyNowElement.php',
				'class' => '\OhMyLMS\WPBakery\Elements\BuyNowElement',
			);
			$elements[] = array(
				'file'  => OHMYLMS_PATH . '/includes/WPBakery/Elements/OfferButtonElement.php',
				'class' => '\OhMyLMS\WPBakery\Elements\OfferButtonElement',
			);

			// Allow filtering of elements.
			$elements = apply_filters( 'ohmylms/wpbakery_elements', $elements );

			// Include and register elements.
			foreach ( $elements as $element ) {
				if ( file_exists( $element['file'] ) ) {
					require_once $element['file'];

					// Initialize the element class (which will call vc_map internally).
					if ( class_exists( $element['class'] ) ) {
						$element_instance = new $element['class']();
					}
				}
			}
	}

	/**
	 * Filter to modify is_ohmylms_page check
	 *
	 * @param bool $is_ohmylms_page Current value.
	 * @return bool Modified value.
	 */
	public function filter_is_ohmylms_page( $is_ohmylms_page ) {
		if ( defined( 'WPB_VC_VERSION' ) ) {
			return true;
		}
		// Check if we're in WPBakery backend editor.
		if ( ! function_exists( 'get_current_screen' ) ) {
			// Check if we're in WPBakery frontend inline editor.
			if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
				return true;
			} else {
				return $is_ohmylms_page;
			}
		}

		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'page', 'post' ) ) && defined( 'WPB_VC_VERSION' ) ) {
			// Check if WPBakery is active on this post/page.
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'ohmylms_course_list' ) ) {
				return true;
			}
			if ( $post && has_shortcode( $post->post_content, 'ohmylms_checkout' ) ) {
				return true;
			}
			if ( $post && has_shortcode( $post->post_content, 'ohmylms_buy_now' ) ) {
				return true;
			}
			if ( $post && has_shortcode( $post->post_content, 'ohmylms_offer_button' ) ) {
				return true;
			}
		}

		return $is_ohmylms_page;
	}

	/**
	 * Add JavaScript to refresh shortcode preview when parameters change
	 *
	 * @return void
	 */
	public function add_backend_editor_refresh_script() {
		// Only load on WPBakery editor pages.
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'page', 'post' ) ) || ! defined( 'WPB_VC_VERSION' ) ) {
			return;
		}
		?>
		<script type="text/javascript">
		(function($) {
			// Function to initialize folded state for checkout input fields
			if (typeof window.vc !== 'undefined') {
				// Listen for parameter changes in WPBakery editor
				window.vc.events.on('shortcodeView:updated', function(view) {
					if (view && view.model) {
						var shortcode = view.model.get('shortcode');
						// Handle Course List updates
						if (shortcode === 'ohmylms_course_list') {
							setTimeout(function() {
								view.renderContent();
							}, 100);
						}
						// Handle Checkout updates
						if (shortcode === 'ohmylms_checkout') {
							setTimeout(function() {
								view.renderContent();
								
							}, 100);
						}
						// Handle Offer Button updates
						if (shortcode === 'ohmylms_offer_button') {
							setTimeout(function() {
								view.renderContent();
							}, 100);
						}
					}
				});
				
				// Also handle when shortcode is first added
				window.vc.events.on('shortcodeView:ready', function(view) {
					if (view && view.model) {
						var shortcode = view.model.get('shortcode');
						// Handle Course List ready
						if (shortcode === 'ohmylms_course_list') {
							setTimeout(function() {
								view.renderContent();
							}, 100);
						}
						// Handle Checkout ready
						if (shortcode === 'ohmylms_checkout') {
							setTimeout(function() {
								view.renderContent();
							}, 100);
						}
						// Handle Offer Button ready
						if (shortcode === 'ohmylms_offer_button') {
							setTimeout(function() {
								view.renderContent();
							}, 100);
						}
					}
				});
			}
		})(jQuery);
		</script>
		<?php
	}
}

// Initialize the manager.
WPBakeryManager::instance();
