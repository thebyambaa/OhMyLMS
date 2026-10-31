<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Elementor Manager
 *
 * Manages all Elementor widgets for OhMyLMS
 *
 * @package OhMyLMS\Elementor
 * @since 1.0.0
 */

namespace OhMyLMS\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * ElementorManager class
 */
class ElementorManager {

	/**
	 * Instance of this class
	 *
	 * @var ElementorManager
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return ElementorManager
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
	 * Initialize the Elementor integration
	 *
	 * @return void
	 */
	private function init() {
		// Check if Elementor is installed and activated.
		if ( ! did_action( 'elementor/loaded' ) ) {
			// add_action( 'admin_notices', array( $this, 'admin_notice_missing_elementor' ) );.
			return;
		}

		// Check for required Elementor version.
		if ( ! version_compare( ELEMENTOR_VERSION, '3.0.0', '>=' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_elementor_version' ) );
			return;
		}

		// Tell FrontendAssets this is a OhMyLMS page when an Elementor page
		// contains our widget — so ohmylms-frontend gets enqueued via the normal path.
		add_filter( 'is_ohmylms_page', array( $this, 'is_ohmylms_page_for_elementor' ) );

		// Add ohmylms-page body class on Elementor pages so all scoped CSS rules apply.
		add_filter( 'body_class', array( $this, 'add_ohmylms_body_class' ) );

		// Initialize when Elementor is ready.
		add_action( 'elementor/init', array( $this, 'elementor_init' ) );
	}

	/**
	 * Return true when the current page is an Elementor page that contains
	 * the My Courses widget, so FrontendAssets enqueues ohmylms-frontend.
	 *
	 * @param bool $is_ohmylms_page Is ohmylms page.
	 * @return bool
	 */
	public function is_ohmylms_page_for_elementor( $is_ohmylms_page ) {
		if ( $is_ohmylms_page ) {
			return true;
		}
		// If Elementor is active on this request, treat it as a ohmylms page
		// so the main CSS/JS bundle is always enqueued.
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return true;
		}
		// On the published frontend, treat any singular page as a ohmylms page when Elementor is active.
		if ( defined( 'ELEMENTOR_VERSION' ) && is_singular() ) {
			return true;
		}
		return $is_ohmylms_page;
	}

	/**
	 * Add the `ohmylms-page` body class on any page built with Elementor.
	 *
	 * All CSS rules in style.css are scoped to `.ohmylms-page`. Without this
	 * class on the <body>, every scoped rule is inactive and the widget renders
	 * completely unstyled. The Gutenberg block works because it adds this class
	 * via a different mechanism; we replicate that here for Elementor.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function add_ohmylms_body_class( array $classes ): array {
		// Already present — nothing to do.
		if ( in_array( 'ohmylms-page', $classes, true ) ) {
			return $classes;
		}

		// Editor preview iframe.
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			$classes[] = 'ohmylms-page';
			return $classes;
		}

		// Published frontend: add on any singular page when Elementor is active.
		if ( defined( 'ELEMENTOR_VERSION' ) && is_singular() ) {
			$classes[] = 'ohmylms-page';
		}

		return $classes;
	}

	/**
	 * Initialize Elementor widgets and controls
	 *
	 * @return void
	 */
	public function elementor_init() {
		// Add new category for OhMyLMS widgets.
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_categories' ) );

		// Register widgets.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Enqueue widget scripts and styles on the published frontend.
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_frontend_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// Enqueue ohmylms-frontend assets inside the Elementor editor preview iframe.
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview_assets' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_preview_assets' ) );
	}

	/**
	 * Register widget categories
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 * @return void
	 */
	public function register_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'ohmylms',
			array(
				'title' => esc_html__( 'OhMyLMS', 'ohmylms' ),
				'icon'  => 'fa fa-graduation-cap',
			)
		);
	}

	/**
	 * Register widgets
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		// Include widget files.
		$this->include_widgets();

		// Register widgets.
		$widgets_manager->register( new Widgets\CheckoutWidget() );
		$widgets_manager->register( new Widgets\CourseListWidget() );
		$widgets_manager->register( new Widgets\MyCoursesWidget() );
		$widgets_manager->register( new Widgets\DashboardWidget() );
		$widgets_manager->register( new Widgets\ProfileWidget() );

			$widgets_manager->register( new Widgets\BuyNowWidget() );
			$widgets_manager->register( new Widgets\OfferButtonWidget() );

		// Also initialize Gutenberg blocks if not already done.
		if ( ! did_action( 'ohmylms_blocks_initialized' ) ) {
			$this->init_blocks();
			do_action( 'ohmylms_blocks_initialized' );
		}
	}

	/**
	 * Include widget files
	 *
	 * @return void
	 */
	private function include_widgets() {
		require_once OHMYLMS_PATH . '/includes/Elementor/Widgets/CheckoutWidget.php';
		require_once OHMYLMS_PATH . '/includes/Elementor/Widgets/CourseListWidget.php';
		require_once OHMYLMS_PATH . '/includes/Elementor/Widgets/BuyNowWidget.php';
		require_once OHMYLMS_PATH . '/includes/Elementor/Widgets/OfferButtonWidget.php';
		require_once OHMYLMS_PATH . '/includes/Elementor/Widgets/MyCoursesWidget.php';
		require_once OHMYLMS_PATH . '/includes/Elementor/Widgets/DashboardWidget.php';
		require_once OHMYLMS_PATH . '/includes/Elementor/Widgets/ProfileWidget.php';
	}

	/**
	 * Initialize Gutenberg blocks
	 *
	 * @return void
	 */
	private function init_blocks() {
		if ( file_exists( OHMYLMS_PATH . '/includes/Blocks/BlocksManager.php' ) ) {
			require_once OHMYLMS_PATH . '/includes/Blocks/BlocksManager.php';
			\OhMyLMS\Blocks\BlocksManager::instance();
		}
	}

	/**
	 * Register and enqueue ohmylms-frontend assets.
	 * Called on both the published frontend and the editor preview iframe.
	 * We register the handles here so we are not dependent on FrontendAssets
	 * having already run (it only enqueues on page-detection conditions that
	 * are never true on a plain Elementor page).
	 *
	 * @return void
	 */
	private function register_and_enqueue_ohmylms_assets() {
		$url = defined( 'OHMYLMS_URL' ) ? OHMYLMS_URL : plugins_url( '', OHMYLMS_FILE );

		// Register if not already registered (FrontendAssets may have done it first).
		if ( ! wp_script_is( 'ohmylms-frontend', 'registered' ) ) {
			wp_register_script(
				'ohmylms-frontend',
				$url . '/' . \OhMyLMS\Assets\FrontendAssets::LEARNER_SCRIPTS['ohmylms-frontend'],
				array( 'wp-i18n', 'jquery' ),
				\OhMyLMS\Assets\FrontendAssets::learner_script_version( 'ohmylms-frontend' ),
				true
			);
		}

		if ( ! wp_style_is( 'ohmylms-frontend', 'registered' ) ) {
			\OhMyLMS\Design\FrontendStyles::register();
		}

		wp_enqueue_script( 'ohmylms-frontend' );
		wp_enqueue_style( 'ohmylms-frontend' );

		// Localize if not already done.
		if ( ! wp_script_is( 'ohmylms-frontend', 'done' ) ) {
			wp_localize_script(
				'ohmylms-frontend',
				'ohmylms_frontend_params',
				array(
					'ajax_url'           => admin_url( 'admin-ajax.php' ),
					'current_student_id' => get_current_user_id(),
					'nonce'              => wp_create_nonce( 'ohmylms' ),
					'is_creator_page'    => true,
				)
			);
		}
	}

	/**
	 * Enqueue ohmylms-frontend assets inside the Elementor editor preview iframe.
	 *
	 * @return void
	 */
	public function enqueue_preview_assets() {
		$this->register_and_enqueue_ohmylms_assets();
	}

	/**
	 * Enqueue frontend styles on published Elementor pages.
	 *
	 * @return void
	 */
	public function enqueue_frontend_styles() {
		$this->register_and_enqueue_ohmylms_assets();

		wp_enqueue_style(
			'ohmylms-elementor-widgets',
			OHMYLMS_URL . '/assets/css/elementor-widgets.css',
			array( 'ohmylms-frontend' ),
			OHMYLMS_VERSION
		);
	}

	/**
	 * Enqueue frontend scripts on published Elementor pages.
	 *
	 * @return void
	 */
	public function enqueue_frontend_scripts() {
		wp_enqueue_script(
			'ohmylms-elementor-widgets',
			OHMYLMS_URL . '/assets/js/elementor-widgets.js',
			array( 'jquery', 'ohmylms-frontend' ),
			OHMYLMS_VERSION,
			true
		);
	}

	/**
	 * Admin notice for missing Elementor
	 *
	 * @return void
	 */
	public function admin_notice_missing_elementor() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor */
			esc_html__( '"%1$s" requires "%2$s" to be installed and activated.', 'ohmylms' ),
			'<strong>' . esc_html__( 'OhMyLMS Elementor Widgets', 'ohmylms' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'ohmylms' ) . '</strong>'
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}

	/**
	 * Admin notice for minimum Elementor version
	 *
	 * @return void
	 */
	public function admin_notice_minimum_elementor_version() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'ohmylms' ),
			'<strong>' . esc_html__( 'OhMyLMS Elementor Widgets', 'ohmylms' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'ohmylms' ) . '</strong>',
			'3.0.0'
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}
}
