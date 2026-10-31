<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

use OhMyLMS\OhMyLmsEndpoint;
use OhMyLMS\Hooks\AssignmentHookHandler;
use OhMyLMS\Hooks\CommonHook;
use OhMyLMS\Hooks\CourseHookHandler;
use OhMyLMS\Hooks\ChapterHookHandler;
use OhMyLMS\Hooks\LessonHookHandler;
use OhMyLMS\Hooks\OrderHooks;
use OhMyLMS\Hooks\QuestionHookHandler;
use OhMyLMS\Hooks\QuizHookHandler;
use OhMyLMS\Elementor\ElementorManager;
use OhMyLMS\Blocks\BlocksManager;
use OhMyLMS\WPBakery\WPBakeryManager;

/**
 * Plugin oh my lms implementation.
 */
final class OhMyLMS {
	/**
	 * Session factory.
	 *
	 * @var mixed
	 */
	public $session_factory;

	/**
	 * OhMyLMS\Rest\Api.
	 *
	 * @var $rest_api OhMyLMS\Rest\Api
	 */
	public $rest_api;


	/**
	 * OhMyLMS\Admin\Admin.
	 *
	 * @var $admin OhMyLMS\Admin\Admin
	 */
	public \OhMyLMS\Admin\Admin $admin;

	/**
	 * \OhMyLMS\Admin\AdminNotices.
	 *
	 * @var $admin_notices \OhMyLMS\Admin\AdminNotices
	 */
	public $admin_notices;

	/**
	 * Initialize Gutenberg blocks
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_blocks() {
		// Include the Blocks Manager file.
		if ( file_exists( OHMYLMS_PATH . '/includes/Blocks/BlocksManager.php' ) ) {
			require_once OHMYLMS_PATH . '/includes/Blocks/BlocksManager.php';
			BlocksManager::instance();
		}
	}

	/**
	 * \OhMyLMS\Admin\Menu.
	 *
	 * @var $admin_menu \OhMyLMS\Admin\Menu
	 */
	public $admin_menu;


	/**
	 * \OhMyLMS\Order\OrderLoader.
	 *
	 * @var $order_loader \OhMyLMS\Order\OrderLoader
	 */
	public $order_loader;


	/**
	 * \OhMyLMS\Order\SessionHandler.
	 *
	 * @var $session \OhMyLMS\Order\SessionHandler
	 */
	public $session;


	/**
	 * Course factory.
	 *
	 * @var \OhMyLMS\Factory\CourseFactory
	 */
	public $course_factory;

	/**
	 * Chapter factory.
	 *
	 * @var \OhMyLMS\Factory\ChapterFactory
	 */
	public $chapter_factory;

	/**
	 * Student factory.
	 *
	 * @var \OhMyLMS\Factory\StudentFactory
	 */
	public $student_factory;


	/**
	 * Lesson factory.
	 *
	 * @var \OhMyLMS\Factory\LessonFactory
	 */
	public $lesson_factory;


	/**
	 * Question factory.
	 *
	 * @var \OhMyLMS\Factory\LessonFactory
	 */
	public $question_factory;


	/**
	 * Quiz factory.
	 *
	 * @var \OhMyLMS\Factory\QuizFactory
	 */
	public $quiz_factory;

	/**
	 * Membership factory.
	 *
	 * @var \OhMyLMS\Factory\MembershipFactory
	 */
	public $membership_factory;

	/**
	 * Assignment factory.
	 *
	 * @var \OhMyLMS\Factory\AssignmentFactory
	 */
	public $assignment_factory;

	/**
	 * Certificate factory.
	 *
	 * @var \OhMyLMS\Factory\CertificateFactory
	 */
	public $certificate_factory;

	/**
	 * Attempt factory.
	 *
	 * @var \OhMyLMS\Factory\AttemptFactory
	 */
	public $attempt_factory;


	/**
	 * Shortcode.
	 *
	 * @var \OhMyLMS\Shortcodes\Shortcodes
	 */
	public $shortcode;


	/**
	 * Admin ajax.
	 *
	 * @var \OhMyLMS\Admin\Ajax
	 */
	public $admin_ajax;


	/**
	 * Settings pages.
	 *
	 * @var \OhMyLMS\Admin\Pages\AdminSettings
	 */
	public $settings_pages;


	/**
	 * Holds the singleton instance of this class.
	 *
	 * @var OhMyLMS
	 */
	private static $instance;

	/**
	 * Course hook handler.
	 *
	 * @var \OhMyLMS\Hooks\CourseHookHandler
	 */
	private $course_hook_handler;


	/**
	 * Chapter hook handler.
	 *
	 * @var \OhMyLMS\Hooks\ChapterHookHandler
	 */
	private $chapter_hook_handler;

	/**
	 * Lesson hook handler.
	 *
	 * @var \OhMyLMS\Hooks\LessonHookHandler
	 */
	private $lesson_hook_handler;

	/**
	 * Order hook handler.
	 *
	 * @var \OhMyLMS\Hooks\OrderHooks
	 */
	private $order_hook_handler;

	/**
	 * Question hook handler.
	 *
	 * @var \OhMyLMS\Hooks\QuestionHookHandler
	 */
	private $question_hook_handler;

	/**
	 * Quiz hook handler.
	 *
	 * @var \OhMyLMS\Hooks\QuizHookHandler
	 */
	private $quiz_hook_handler;

	/**
	 * Ohmylms endpoint.
	 *
	 * @var \OhMyLMS\OhMyLmsEndpoint
	 */
	public $ohmylms_endpoint;

	/**
	 * Course comment.
	 *
	 * @var \OhMyLMS\CourseComment
	 */
	private $course_comment;
	/**
	 * Common hook.
	 *
	 * @var \OhMyLMS\Hooks\CommonHook
	 */
	private $common_hook;
	/**
	 * Assignment hook handler.
	 *
	 * @var \OhMyLMS\Hooks\AssignmentHookHandler
	 */
	private $assignment_hook_handler;

	/**
	 * Rewrite rules.
	 *
	 * @var \OhMyLMS\RewriteRules
	 */
	private $rewrite_rules;


	/**
	 * Emails.
	 *
	 * @var \OhMyLMS\Emails\Emails
	 */
	public $emails;

	/**
	 * Drip content.
	 *
	 * @var \OhMyLMS\DripContent
	 */
	public $drip_content;

	/**
	 * Webhook manager.
	 *
	 * @var \OhMyLMS\WebhookManager
	 */
	public $webhook_manager;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.2.21';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	const SLUG = 'ohmylms';



	/**
	 * Singleton instance.
	 *
	 * @return OhMyLMS
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}



	/**
	 * Constructor for the PluginName class.
	 *
	 * Sets up all the appropriate hooks and actions within our plugin.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->includes();
		$this->init_plugin();
		$this->register_bundled_features();
	}

	/**
	 * Maybe run setup wizard for Oh my lms.
	 */
	public function maybe_run_setup_wizard() {
		if ( get_transient( '_ohmylms_activation_redirect' ) ) {
			delete_transient( '_ohmylms_activation_redirect' );
		}
	}


	/**
	 * Clear legacy activation redirects without opening onboarding.
	 */
	public function admin_redirects() {
		$this->maybe_run_setup_wizard();
	}


	/**
	 * Auto-load in-accessible properties on demand.
	 *
	 * @param mixed $key Key name.
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( in_array( $key, array( 'payment_gateways' ), true ) ) {
			return \OhMyLMS\Commerce\ecommerce()->$key();
			// return $this->$key();.
		}
	}


	/**
	 * Include required files.
	 *
	 * @since 1.0.0
	 */
	private function includes(): void {

		if ( $this->is_request( 'admin' ) ) {
			include_once plugin_dir_path( __FILE__ ) . 'Admin/ohmylms-admin-functions.php';
		}

		/**
		 * Utility functions
		 */
		include_once plugin_dir_path( __FILE__ ) . 'Hooks/template-hooks.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/core-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/course-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/lesson-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/quiz-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/chapter-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/notice-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/template-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/page-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/formatting-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/conditional-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/question-function.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/term-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/profile-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/content-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/certificate-function.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/attempt-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/student-function.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/time-functions.php';
		include_once plugin_dir_path( __FILE__ ) . 'Utility/update-functions.php';

		/**
		 * Core classes.
		 */
		include_once plugin_dir_path( __FILE__ ) . 'Ajax.php';
		include_once plugin_dir_path( __FILE__ ) . 'TemplateLoader.php';
		include_once plugin_dir_path( __FILE__ ) . 'Assets/FrontendAssets.php';
		include_once plugin_dir_path( __FILE__ ) . 'Assets/AdminAssets.php';

		/**
		 * CPT registration
		 */
		require_once plugin_dir_path( __FILE__ ) . 'PostTypes/CoursePostType.php';
		require_once plugin_dir_path( __FILE__ ) . 'PostTypes/MembershipPostType.php';

		$this->include_theme_support();
	}


	/**
	 * Activating the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activate() {
		\OhMyLMS\Install::install();
	}

	/**
	 * Handles plugin deactivation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function deactivate() {
		/**
		 * Fires when the plugin is deactivated.
		 *
		 * @since 1.1.10
		 */
		do_action( 'ohmylms_plugin_deactivated' );
	}


	/**
	 * Load the plugin after all plugins are loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_plugin() {
		$this->define_constants();

		\OhMyLMS\Install::init();

		add_action( 'init', array( $this, 'add_image_sizes' ) );

		// Localize our plugin.
		add_action( 'init', array( $this, 'localization_setup' ) );

		// Add the plugin page links.
		add_filter( 'plugin_action_links_' . plugin_basename( OHMYLMS_FILE ), array( $this, 'plugin_action_links' ) );

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'init', array( $this, 'init' ) );

		add_action( 'init', array( '\OhMyLMS\Shortcodes\Shortcodes', 'init' ) );

		// Initialize Elementor widgets.
		add_action( 'plugins_loaded', array( $this, 'init_elementor' ) );

		// Initialize Bricks elements.
		add_action( 'plugins_loaded', array( $this, 'init_bricks' ) );

		// Initialize WPBakery Page Builder elements.
		add_action( 'plugins_loaded', array( $this, 'init_wpbakery' ) );

		// Initialize Gutenberg blocks.
		add_action( 'plugins_loaded', array( $this, 'init_blocks' ) );

		// === register post types ===//
		$sectionCPT  = new OhMyLMS\PostTypes\SectionPostType();
		$lessonCPT   = new OhMyLMS\PostTypes\LessonPostType();
		$lessonCPT   = new OhMyLMS\PostTypes\ChapterPostType();
		$quizCPT     = new OhMyLMS\PostTypes\QuizPostType();
		$questionCPT = new OhMyLMS\PostTypes\QuestionPostType();
		$assignment  = new OhMyLMS\PostTypes\AssignmentPostType();
		$certificate = new OhMyLMS\PostTypes\CertificatePostType();

		// load the helper packages.
		\OhMyLMS\Packages::init();

		// load form handler.
		\OhMyLMS\FormHandler::init();

		/**
		 * Fires after the plugin is loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_init' );
	}

	/**
	 * Define the constants.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function define_constants() {
		defined( 'OHMYLMS_SLUG' ) || define( 'OHMYLMS_SLUG', self::SLUG );
		defined( 'OHMYLMS_FILE' ) || define( 'OHMYLMS_FILE', OHMYLMS_FILE );
		defined( 'OHMYLMS_PATH' ) || define( 'OHMYLMS_PATH', dirname( OHMYLMS_FILE ) );
		defined( 'OHMYLMS_INCLUDES' ) || define( 'OHMYLMS_INCLUDES', OHMYLMS_PATH . '/includes' );
		defined( 'OHMYLMS_TEMPLATE_PATH' ) || define( 'OHMYLMS_TEMPLATE_PATH', OHMYLMS_PATH . '/views' );
		defined( 'OHMYLMS_URL' ) || define( 'OHMYLMS_URL', plugins_url( '', OHMYLMS_FILE ) );
		defined( 'OHMYLMS_ASSETS_URL' ) || define( 'OHMYLMS_ASSETS_URL', OHMYLMS_URL . '/assets' );
		defined( 'OHMYLMS_ASSETS_DIR' ) || define( 'OHMYLMS_ASSETS_DIR', OHMYLMS_DIR . '/assets' );
		defined( 'OHMYLMS_PRODUCTION' ) || define( 'OHMYLMS_PRODUCTION', 'yes' );
		defined( 'OHMYLMS_SESSION_CACHE_GROUP' ) || define( 'OHMYLMS_SESSION_CACHE_GROUP', 'ohmylms_session_id' );
		defined( 'OHMYLMS_COURSE_CPT' ) || define( 'OHMYLMS_COURSE_CPT', 'ohmylms-course' );
		defined( 'OHMYLMS_CHAPTER_CPT' ) || define( 'OHMYLMS_CHAPTER_CPT', 'ohmylms-chapter' );
		defined( 'OHMYLMS_CERTIFICATE_CPT' ) || define( 'OHMYLMS_CERTIFICATE_CPT', 'ohmylms-certificate' );
		defined( 'OHMYLMS_LESSON_CPT' ) || define( 'OHMYLMS_LESSON_CPT', 'ohmylms-lesson' );
		defined( 'OHMYLMS_QUIZ_CPT' ) || define( 'OHMYLMS_QUIZ_CPT', 'ohmylms-quiz' );
		defined( 'OHMYLMS_QUESTION_CPT' ) || define( 'OHMYLMS_QUESTION_CPT', 'ohmylms-question' );
		defined( 'OHMYLMS_MEMBERSHIP_CPT' ) || define( 'OHMYLMS_MEMBERSHIP_CPT', 'ohmylms-membership' );
		defined( 'OHMYLMS_ASSIGNMENT_CPT' ) || define( 'OHMYLMS_ASSIGNMENT_CPT', 'ohmylms-assignment' );
		defined( 'OHMYLMS_CHAPTER_RELATIONSHIP' ) || define( 'OHMYLMS_CHAPTER_RELATIONSHIP', 'ohmylms_chapter_relationship' );
		defined( 'OHMYLMS_CONTENT_RELATIONSHIP' ) || define( 'OHMYLMS_CONTENT_RELATIONSHIP', 'ohmylms_content_relationship' );

		defined( 'OHMYLMS_QUIZ_QUESTION_RELATIONSHIP' ) || define( 'OHMYLMS_QUIZ_QUESTION_RELATIONSHIP', 'ohmylms_quiz_questions_relationship' );
	}

	/**
	 * Init OhMyLMS when WordPress is loaded
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {

		/**
		 * Action triggered before OhMyLMS initialization begins.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_init' );

		if ( $this->is_request( 'admin' ) ) {
			$this->admin          = new \OhMyLMS\Admin\Admin();
			$this->admin_notices  = new \OhMyLMS\Admin\AdminNotices();
			$this->settings_pages = new \OhMyLMS\Admin\Pages\AdminSettings();
			$this->settings_pages->init_settings_pages();
			$this->admin_menu = new OhMyLMS\Admin\Menu();
			$this->admin_ajax = new \OhMyLMS\Admin\Ajax();

			// Initialize promotional banner (customize dates for your promotional period)
			// Vendor promotions are not part of this distribution.
		}

		$this->rest_api = new OhMyLMS\Rest\Api();
		new \OhMyLMS\Integrations\GoogleSignIn\GoogleSignIn();
		new \OhMyLMS\Integrations\MicrosoftSignIn\MicrosoftSignIn();
		$this->course_factory      = new \OhMyLMS\Factory\CourseFactory();
		$this->lesson_factory      = new \OhMyLMS\Factory\LessonFactory();
		$this->chapter_factory     = new \OhMyLMS\Factory\ChapterFactory();
		$this->quiz_factory        = new \OhMyLMS\Factory\QuizFactory();
		$this->question_factory    = new \OhMyLMS\Factory\QuestionFactory();
		$this->certificate_factory = new \OhMyLMS\Factory\CertificateFactory();
		$this->student_factory     = new \OhMyLMS\Factory\StudentFactory();
		$this->attempt_factory     = new \OhMyLMS\Factory\AttemptFactory();
		$this->membership_factory  = new \OhMyLMS\Factory\MembershipFactory();
		$this->assignment_factory  = new \OhMyLMS\Factory\AssignmentFactory();
		$this->session_factory     = new \OhMyLMS\Factory\SessionFactory();
		$this->rewrite_rules       = new \OhMyLMS\RewriteRules();
		$this->shortcode           = new OhMyLMS\Shortcodes\Shortcodes();
		$this->ohmylms_endpoint    = new OhMyLmsEndpoint();
		$this->course_comment      = new \OhMyLMS\CourseComment();
		$this->emails              = new \OhMyLMS\Emails\Emails();
		$this->drip_content        = new \OhMyLMS\DripContent();

		// Initialize webhook manager only if webhooks integration is enabled.
		if ( apply_filters( 'ohmylms_should_enable_webhooks', false ) ) {
			$this->webhook_manager = new \OhMyLMS\WebhookManager();
		}

		$this->set_custom_image_size();
		$this->emails->register_email();
		/**
		 * Action triggered after OhMyLMS initialize.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_init' );

		$this->maybe_run_setup_wizard();
	}


	/**
	 * On plugins loaded for Oh my lms.
	 */
	public function on_plugins_loaded() {

		$this->course_hook_handler = new CourseHookHandler();
		$this->course_hook_handler->register_hooks();

		$this->chapter_hook_handler = new ChapterHookHandler();
		$this->chapter_hook_handler->register_hooks();

		$this->lesson_hook_handler = new LessonHookHandler();
		$this->lesson_hook_handler->register_hooks();

		$this->order_hook_handler = new OrderHooks();
		$this->order_hook_handler->register_hooks();

		$this->question_hook_handler = new QuestionHookHandler();
		$this->question_hook_handler->register_hooks();

		$this->quiz_hook_handler = new QuizHookHandler();
		$this->quiz_hook_handler->register_hooks();

		$this->assignment_hook_handler = new AssignmentHookHandler();
		$this->assignment_hook_handler->register_hooks();

		$this->common_hook = new CommonHook();
		$this->common_hook->register_hooks();
		/**
		 * Signal that OhMyLMS is loaded
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_loaded' );
	}



	/**
	 * Initialize plugin for localization.
	 *
	 * @uses load_plugin_textdomain()
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function localization_setup() {
		// The plugin's own languages/ folder (this file lives in includes/).
		load_plugin_textdomain( 'ohmylms', false, dirname( plugin_basename( OHMYLMS_FILE ) ) . '/languages/' );

		// Load the React-pages translations.
		if ( is_admin() ) {
			wp_set_script_translations( 'ohmylms-app', 'ohmylms', OHMYLMS_DIR . '/languages' );
		}
	}

	/**
	 * What type of request is this.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type admin, ajax, cron or frontend
	 * .
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();

			case 'ajax':
				return defined( 'DOING_AJAX' );

			case 'rest':
				return defined( 'REST_REQUEST' );

			case 'cron':
				return defined( 'DOING_CRON' );

			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	/**
	 * Plugin action links
	 *
	 * @param array $links Links.
	 * .
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$links[] = '<a href="' . esc_url( \OhMyLMS\Admin\LegacyRoutes::app_url( '/settings?tab=general' ) ) . '">' . esc_html__( 'Settings', 'ohmylms' ) . '</a>';

		return $links;
	}


	/**
	 * Init order module
	 *
	 * @since 1.0.0
	 */
	public function initialize_order_module() {
		if ( is_null( $this->order_loader ) || ! $this->order_loader instanceof OhMyLMS\Order\OrderLoader ) {
			$this->order_loader = new OhMyLMS\Order\OrderLoader();
		}
	}

	/**
	 * Set custom image for creator LMS.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function set_custom_image_size() {
		if ( function_exists( 'add_image_size' ) ) {
			add_image_size( 'course-listing-thumb', 130, 80 );
		}
	}

	/**
	 * Add custom image sizes for OhMyLMS.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_image_sizes() {
		add_image_size( 'ohmylms_thumbnail', 282, 160, true );
		add_image_size( 'ohmylms_single', 720, 405, true );
	}

	/**
	 * Initialize Elementor integration
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_elementor() {
		// Include the Elementor Manager file.
		if ( file_exists( OHMYLMS_PATH . '/includes/Elementor/ElementorManager.php' ) ) {
			require_once OHMYLMS_PATH . '/includes/Elementor/ElementorManager.php';
			ElementorManager::instance();
		}
	}

	/**
	 * Initialize Bricks integration
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_bricks() {
		// Include the Bricks Manager file.
		if ( file_exists( OHMYLMS_PATH . '/includes/Bricks/BricksManager.php' ) ) {
			require_once OHMYLMS_PATH . '/includes/Bricks/BricksManager.php';
			\OhMyLMS\Bricks\BricksManager::instance();
		}
	}

	/**
	 * Initialize WPBakery Page Builder integration
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_wpbakery() {
		// Include the WPBakery Manager file.
		if ( defined( 'WPB_VC_VERSION' ) && file_exists( OHMYLMS_PATH . '/includes/WPBakery/WPBakeryManager.php' ) ) {
			require_once OHMYLMS_PATH . '/includes/WPBakery/WPBakeryManager.php';
			\OhMyLMS\WPBakery\WPBakeryManager::instance();
		}
	}

	/**
	 * Include theme support if available.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function include_theme_support() {
		if ( ohmylms_if_theme_support_available() ) {
			switch ( get_template() ) {
				case 'twentytwentyone':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentyTwentyOne();
					$theme_support->init();
					break;
				case 'twentytwentytwo':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentyTwentyTwo();
					$theme_support->init();
					break;
				case 'twentytwentythree':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentyTwentyThree();
					$theme_support->init();
					break;
				case 'twentytwentyfour':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentyTwentyFour();
					$theme_support->init();
					break;
				case 'twentytwentyfive':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentyTwentyFive();
					$theme_support->init();
					break;
				case 'astra':
					$theme_support = new \OhMyLMS\ThemeSupport\Astra();
					$theme_support->init();
					break;
				case 'Avada':
					$theme_support = new \OhMyLMS\ThemeSupport\Avada();
					$theme_support->init();
					break;
				case 'blocksy':
					$theme_support = new \OhMyLMS\ThemeSupport\Blocksy();
					$theme_support->init();
					break;
				case 'bricks':
					$theme_support = new \OhMyLMS\ThemeSupport\Bricks();
					$theme_support->init();
					break;
				case 'colibri-wp':
					$theme_support = new \OhMyLMS\ThemeSupport\ColibriWP();
					$theme_support->init();
					break;
				case 'Divi':
					$theme_support = new \OhMyLMS\ThemeSupport\Divi();
					$theme_support->init();
					break;
				case 'generatepress':
					$theme_support = new \OhMyLMS\ThemeSupport\GeneratePress();
					$theme_support->init();
					break;
				case 'kadence':
					$theme_support = new \OhMyLMS\ThemeSupport\Kadence();
					$theme_support->init();
					break;
				case 'oceanwp':
					$theme_support = new \OhMyLMS\ThemeSupport\OceanWP();
					$theme_support->init();
					break;
				case 'storefront':
					$theme_support = new \OhMyLMS\ThemeSupport\Storefront();
					$theme_support->init();
					break;
				case 'twentynineteen':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentyNineteen();
					$theme_support->init();
					break;
				case 'twentyseventeen':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentySeventeen();
					$theme_support->init();
					break;
				case 'twentysixteen':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentySixteen();
					$theme_support->init();
					break;
				case 'twentytwenty':
					$theme_support = new \OhMyLMS\ThemeSupport\TwentyTwenty();
					$theme_support->init();
					break;
				case 'helloelementor':
					$theme_support = new \OhMyLMS\ThemeSupport\HelloElementor();
					$theme_support->init();
					break;
				case 'hestia':
					$theme_support = new \OhMyLMS\ThemeSupport\Hestia();
					$theme_support->init();
					break;
				case 'thrive-theme':
					$theme_support = new \OhMyLMS\ThemeSupport\ThriveTheme();
					$theme_support->init();
					break;
				case 'woostify':
					$theme_support = new \OhMyLMS\ThemeSupport\Woostify();
					$theme_support->init();
					break;
				case 'betheme':
					$theme_support = new \OhMyLMS\ThemeSupport\BeTheme();
					$theme_support->init();
					break;
				case 'flatsome':
					$theme_support = new \OhMyLMS\ThemeSupport\Flatsome();
					$theme_support->init();
					break;
				case 'woodmart':
					$theme_support = new \OhMyLMS\ThemeSupport\Woodmart();
					$theme_support->init();
					break;
				case 'dt-the7':
					$theme_support = new \OhMyLMS\ThemeSupport\The7();
					$theme_support->init();
					break;
			}
		}
	}


	/**
	 * Register bundled features for Oh my lms.
	 */
	private function register_bundled_features() {
		foreach ( array( 'membership-function', 'assignment-function', 'session-function' ) as $helper ) {
			require_once OHMYLMS_DIR . '/includes/Utility/' . $helper . '.php';
		}
		add_action( 'plugins_loaded', array( $this, 'load_bundled_features' ), 15 );
		add_action(
			'plugins_loaded',
			function () {
				( new \OhMyLMS\Gateways\GatewayAutoloader() )->load_gateways();
			},
			25
		);
		add_action( 'init', array( $this, 'register_sessions_post_type' ) );
	}
	/**
	 * Load bundled features for Oh my lms.
	 */
	public function load_bundled_features() {
		new \OhMyLMS\SequentialMode();
		foreach ( array( 'CourseHook', 'ChapterHook', 'LessonHook', 'QuizHook', 'MembershipHook', 'SettingsHook', 'ApiHook', 'AutomationHook', 'LeaderboardHook', 'EngagementHook' ) as $name ) {
			$class = 'OhMyLMS\\Hooks\\' . $name;
			( new $class() )->register_hooks();
		}
		foreach ( array( 'Cohorts', 'Zoom', 'Gamification', 'Funnel', 'ContentProtection', 'Webhooks', 'GoogleMeet', 'WPFusion' ) as $name ) {
			$class = 'OhMyLMS\\Integrations\\' . $name . '\\' . $name;
			new $class();
		}
	}


	/**
	 * Register sessions post type for Oh my lms.
	 */
	public function register_sessions_post_type() {
		$labels = array(
			'name'               => \_x( 'Sessions', 'Post type general name', 'ohmylms' ),
			'singular_name'      => \_x( 'Session', 'Post type singular name', 'ohmylms' ),
			'menu_name'          => \_x( 'Sessions', 'Admin Menu text', 'ohmylms' ),
			'name_admin_bar'     => \_x( 'Session', 'Add New on Toolbar', 'ohmylms' ),
			'add_new'            => \__( 'Add New', 'ohmylms' ),
			'add_new_item'       => \__( 'Add New Session', 'ohmylms' ),
			'new_item'           => \__( 'New Session', 'ohmylms' ),
			'edit_item'          => \__( 'Edit Session', 'ohmylms' ),
			'view_item'          => \__( 'View Session', 'ohmylms' ),
			'all_items'          => \__( 'All Sessions', 'ohmylms' ),
			'search_items'       => \__( 'Search Sessions', 'ohmylms' ),
			'parent_item_colon'  => \__( 'Parent Sessions:', 'ohmylms' ),
			'not_found'          => \__( 'No sessions found.', 'ohmylms' ),
			'not_found_in_trash' => \__( 'No sessions found in Trash.', 'ohmylms' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'query_var'           => true,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'exclude_from_search' => true,
			'rewrite'             => array( 'slug' => 'ohmylms-session' ),
		);

		\register_post_type( 'ohmylms-session', $args );
	}
}
