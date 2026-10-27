<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Blocks Manager
 *
 * Manages all Gutenberg blocks for OhMyLMS
 *
 * @package OhMyLMS\Blocks
 * @since 1.0.0
 */

namespace OhMyLMS\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * BlocksManager class
 */
class BlocksManager {

	/**
	 * Instance of this class
	 *
	 * @var BlocksManager
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return BlocksManager
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
		$this->init();
	}

	/**
	 * Initialize the Blocks integration
	 *
	 * @return void
	 */
	private function init() {
		// Hook into init to register blocks.
		add_action( 'init', array( $this, 'register_blocks' ) );

		// Enqueue block editor assets.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

		// Enqueue frontend block assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Register all blocks
	 *
	 * @return void
	 */
	public function register_blocks() {
		// Include block files.
		$this->include_blocks();

		// Register block category.
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );

		// Register individual blocks.
			new CheckoutBlock();
			new DashboardGutenbergBlock();
			new ProfileGutenbergBlock();
			\OhMyLMS\Blocks\Blocks\BuyNowBlock::register();
		if ( class_exists( '\OhMyLMS\Blocks\Blocks\OfferButtonBlock' ) ) {
			\OhMyLMS\Blocks\Blocks\OfferButtonBlock::register();
		}

			new \OhMyLMS\Blocks\Blocks\MembershipListBlock();
	}

	/**
	 * Include block files
	 *
	 * @return void
	 */
	private function include_blocks() {
		require_once OHMYLMS_PATH . '/includes/Blocks/CheckoutBlock.php';
		require_once OHMYLMS_PATH . '/includes/Blocks/Blocks/BuyNowBlock.php';
		require_once OHMYLMS_PATH . '/includes/Blocks/Blocks/OfferButtonBlock.php';
		require_once OHMYLMS_PATH . '/includes/Blocks/DashboardGutenbergBlock.php';
		require_once OHMYLMS_PATH . '/includes/Blocks/ProfileGutenbergBlock.php';
		require_once OHMYLMS_PATH . '/includes/Blocks/Blocks/MembershipListBlock.php';
	}

	/**
	 * Register OhMyLMS block category
	 *
	 * @param array $categories Existing block categories.
	 * @return array Updated block categories
	 */
	public function register_block_category( $categories ) {
		// Add OhMyLMS category at the beginning.
		array_unshift(
			$categories,
			array(
				'slug'  => 'ohmylms',
				'title' => esc_html__( 'OhMyLMS', 'ohmylms' ),
				'icon'  => 'graduation-cap',
			)
		);

		return $categories;
	}

	/**
	 * Enqueue block editor assets
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		// Enqueue main blocks script.
		wp_enqueue_script(
			'ohmylms-blocks-editor',
			OHMYLMS_URL . '/assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
			OHMYLMS_VERSION,
			true
		);

		// Enqueue individual block scripts.
		$this->enqueue_individual_block_scripts();

		// Enqueue slick.js for carousel functionality in editor.
		wp_enqueue_script(
			'ohmylms-slick-editor',
			OHMYLMS_URL . '/assets/dist/frontend/slick.js',
			array( 'jquery' ),
			'1.8.1',
			true
		);

		// Enqueue frontend script for slick initialization in editor.
		wp_enqueue_script(
			'ohmylms-frontend-editor',
			OHMYLMS_URL . '/assets/dist/frontend/ohmylms.js',
			array( 'jquery', 'ohmylms-slick-editor' ),
			OHMYLMS_VERSION,
			true
		);
		// The editor uses a separate handle, so the frontend print hook does not localize it.
		$frontend_assets = new \OhMyLMS\Assets\FrontendAssets();
		wp_localize_script( 'ohmylms-frontend-editor', 'ohmylms_frontend_params', $frontend_assets->get_script_data( 'ohmylms-frontend' ) );

		// Enqueue block editor styles.
		wp_enqueue_style(
			'ohmylms-blocks-editor',
			OHMYLMS_URL . '/assets/blocks/css/blocks-editor.css',
			array( 'wp-edit-blocks' ),
			OHMYLMS_VERSION
		);

		// Enqueue frontend styles.
		wp_enqueue_style(
			'ohmylms-blocks-frontend',
			OHMYLMS_URL . '/assets/blocks/css/blocks-frontend.css',
			array(),
			OHMYLMS_VERSION
		);

		// Enqueue main OhMyLMS styles in editor for ServerSideRender checkout styling.
		wp_enqueue_style(
			'ohmylms-main-editor',
			OHMYLMS_URL . '/assets/css/style.css',
			array(),
			OHMYLMS_VERSION
		);
	}

	/**
	 * Enqueue individual block scripts
	 *
	 * @return void
	 */
	private function enqueue_individual_block_scripts() {
		$suffix = $this->is_development_mode() ? '' : '.min';

		// Checkout block.
		wp_enqueue_script(
			'ohmylms-checkout-block',
			OHMYLMS_URL . "/assets/blocks/js/checkout{$suffix}.js",
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
			OHMYLMS_VERSION,
			true
		);

		// Dashboard block.
		wp_enqueue_script(
			'ohmylms-dashboard-block',
			OHMYLMS_URL . "/assets/blocks/js/dashboard{$suffix}.js",
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			OHMYLMS_VERSION,
			true
		);

		// Profile block.
		wp_enqueue_script(
			'ohmylms-profile-block',
			OHMYLMS_URL . "/assets/blocks/js/profile{$suffix}.js",
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			OHMYLMS_VERSION,
			true
		);

		// Buy Now block.
		wp_enqueue_script(
			'ohmylms-buy-now-block',
			OHMYLMS_URL . "/assets/blocks/js/buy-now{$suffix}.js",
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
			OHMYLMS_VERSION,
			true
		);

		// Offer Button block.
		wp_enqueue_script(
			'ohmylms-offer-button-block',
			OHMYLMS_URL . "/assets/blocks/js/offer-button{$suffix}.js",
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
			OHMYLMS_VERSION,
			true
		);

		// Membership List block.
		wp_enqueue_script(
			'ohmylms-membership-list-block',
			OHMYLMS_URL . "/assets/blocks/js/membership-list{$suffix}.js",
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			OHMYLMS_VERSION,
			true
		);

		// Add more blocks here as needed.
	}

	/**
	 * Check if development mode is enabled
	 *
	 * @return bool
	 */
	private function is_development_mode() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	}

	/**
	 * Enqueue frontend block assets
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$ohmylms_blocks = array(
			'ohmylms/dashboard',
			'ohmylms/checkout',
			'ohmylms/profile',
			'ohmylms/buy-now',
			'ohmylms/offer-button',
			'ohmylms/membership-list',
		);

		$has_ohmylms_block = false;
		foreach ( $ohmylms_blocks as $block_name ) {
			if ( has_block( $block_name ) ) {
				$has_ohmylms_block = true;
				break;
			}
		}

		if ( ! $has_ohmylms_block ) {
			return;
		}

		// Enqueue frontend styles.
		wp_enqueue_style(
			'ohmylms-blocks-frontend',
			OHMYLMS_URL . '/assets/blocks/css/blocks-frontend.css',
			array(),
			OHMYLMS_VERSION
		);

		// Enqueue frontend script.
		wp_enqueue_script(
			'ohmylms-blocks-frontend',
			OHMYLMS_URL . '/assets/blocks/js/blocks-frontend.js',
			array( 'jquery' ),
			OHMYLMS_VERSION,
			true
		);
	}
}
