<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bricks Manager
 *
 * Manages all Bricks elements for OhMyLMS
 *
 * @package OhMyLMS\Bricks
 * @since 1.0.0
 */

namespace OhMyLMS\Bricks;

defined( 'ABSPATH' ) || exit;

/**
 * BricksManager class
 */
class BricksManager {

	/**
	 * Instance of this class
	 *
	 * @var BricksManager
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return BricksManager
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
	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 11 );
		add_filter( 'bricks/elements/categories', array( $this, 'add_element_category' ) );
	}

	/**
	 * Initialize and register OhMyLMS elements for Bricks
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function init() {

		// Define elements to register.
		$elements = array(
			array(
				'file'  => OHMYLMS_PATH . '/includes/Bricks/Elements/CourseListElement.php',
				'class' => '\OhMyLMS\Bricks\Elements\CourseListElement',
			),
		);

		// Buy now and offer button elements.
			$elements[] = array(
				'file'  => OHMYLMS_PATH . '/includes/Bricks/Elements/BuyNowElement.php',
				'class' => '\OhMyLMS\Bricks\Elements\BuyNowElement',
			);
			$elements[] = array(
				'file'  => OHMYLMS_PATH . '/includes/Bricks/Elements/OfferButtonElement.php',
				'class' => '\OhMyLMS\Bricks\Elements\OfferButtonElement',
			);

			$elements[] = array(
				'file'  => OHMYLMS_PATH . '/includes/Bricks/Elements/CheckoutElement.php',
				'class' => '\OhMyLMS\Bricks\Elements\CheckoutElement',
			);

			$elements[] = array(
				'file'  => OHMYLMS_PATH . '/includes/Bricks/Elements/MyCoursesElement.php',
				'class' => '\OhMyLMS\Bricks\Elements\MyCoursesElement',
			);

			$elements[] = array(
				'file'  => OHMYLMS_PATH . '/includes/Bricks/Elements/DashboardElement.php',
				'class' => '\OhMyLMS\Bricks\Elements\DashboardElement',
			);

			$elements[] = array(
				'file'  => OHMYLMS_PATH . '/includes/Bricks/Elements/ProfileElement.php',
				'class' => '\OhMyLMS\Bricks\Elements\ProfileElement',
			);

			// Allow filtering of elements.
			$elements = apply_filters( 'ohmylms/bricks_elements', $elements );
			// Register elements with Bricks.
			if ( class_exists( '\Bricks\Elements' ) ) {
				foreach ( $elements as $element ) {
					if ( file_exists( $element['file'] ) ) {
						\Bricks\Elements::register_element( $element['file'] );
					}
				}
			}

			// Enqueue frontend assets.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Add OhMyLMS category to Bricks elements
	 *
	 * @param array $categories Existing categories.
	 * @return array Modified categories
	 */
	public function add_element_category( $categories ) {
		$categories['ohmylms'] = array(
			'title' => esc_html__( 'OhMyLMS', 'ohmylms' ),
			'icon'  => 'ti-crown',
		);

		return $categories;
	}

	/**
	 * Enqueue frontend styles and scripts
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		// wp_enqueue_style(
		// 'ohmylms-bricks-elements',
		// OHMYLMS_URL . '/assets/css/bricks-elements.css',
		// array(),
		// OHMYLMS_VERSION
		// );.

		// wp_enqueue_script(
		// 'ohmylms-bricks-elements',
		// OHMYLMS_URL . '/assets/js/bricks-elements.js',
		// array( 'jquery' ),
		// OHMYLMS_VERSION,
		// true
		// );.

		// Localize script for AJAX.
		wp_localize_script(
			'ohmylms-bricks-elements',
			'ohMyLmsBricks',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ohmylms_bricks_nonce' ),
			)
		);
	}
}
BricksManager::instance();
