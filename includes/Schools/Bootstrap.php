<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap implementation.
 */
final class Bootstrap {
	/**
	 * Init for Bootstrap.
	 */
	public static function init() {
		// Internal rollback switch; all features ship in the same OhMyLMS product.
		if ( defined( 'OHMYLMS_SCHOOLS_ENABLED' ) && ! OHMYLMS_SCHOOLS_ENABLED ) {
			return; }
		add_action( 'init', array( Schema::class, 'install' ), 6 );
		add_action( 'rest_api_init', array( Controller::class, 'register' ) );
		add_action( 'rest_api_init', array( ViewAs::class, 'register' ) );
		add_action( 'rest_api_init', array( SamplePreview::class, 'register' ) );
		add_action( 'rest_api_init', array( Gradebook::class, 'register' ) );
		add_action( 'init', array( ViewAs::class, 'protect_session' ), 1 );
		add_action( 'wp_footer', array( ViewAs::class, 'banner' ) );
		add_action( 'admin_footer', array( ViewAs::class, 'banner' ) );
		add_action( 'init', array( Views::class, 'blocks' ), 20 );
		add_action( 'admin_menu', array( Views::class, 'menu' ), 30 );
		add_action( 'admin_init', array( Views::class, 'redirect_management' ) );
		add_action( 'ohmylms_course_completed', array( Service::class, 'course_completed' ), 20, 2 );
		add_action( 'ohmylms_lesson_completed', array( Service::class, 'content_completed' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( Views::class, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( Views::class, 'assets' ) );
		add_action( 'ohmylms_lms_student_profile_after_dashboard_content', array( Views::class, 'dashboard_link' ) );
	}
}
