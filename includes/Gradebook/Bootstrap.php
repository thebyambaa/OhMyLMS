<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Hooks of the gradebook.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the capability, the REST routes, the cache-version hooks and the user clean-up.
 */
final class Bootstrap {

	/**
	 * Writes after which cached grids are recomputed (the version is raised once per request).
	 */
	const INVALIDATING_ACTIONS = array(
		'ohmylms_attempt_submitted',
		'ohmylms_attempt_graded',
		'ohmylms_after_assignment_submitted',
		'ohmylms_assignment_submitted',
		'ohmylms_after_assignment_review',
		'ohmylms_manual_student_enrollment',
		'ohmylms_student_unenrolled',
		'ohmylms_quiz_revision_published',
		'ohmylms_exam_results_changed',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'map_meta_cap', array( Access::class, 'map_meta_cap' ), 10, 3 );
		add_filter( 'ohmylms_app_capabilities', array( Access::class, 'app_capabilities' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		foreach ( self::INVALIDATING_ACTIONS as $action ) {
			add_action( $action, array( Version::class, 'touch' ), 99, 0 );
		}
		add_action( 'deleted_user', array( Overrides::class, 'forget_user' ) );
	}

	/**
	 * Register the REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		( new Rest\GradebookController() )->register_routes();
	}
}
