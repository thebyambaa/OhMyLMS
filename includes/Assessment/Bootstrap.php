<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Wires the question bank, versioned assessment, skills and practice services.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Wires the question bank, versioned assessment, skills and practice services. */
final class Bootstrap {
	/**
	 * Init for Bootstrap.
	 */
	public static function init() {
		PreviewPlayer::init();
		ResponseMedia::init();
		add_action( 'init', array( Taxonomy::class, 'register' ), 4 );
		\OhMyLMS\Skills\Provenance::init();
		add_action( 'init', array( Usage::class, 'register_status' ), 4 );
		add_action( 'before_delete_post', array( Usage::class, 'on_deleted_post' ) );
		add_action( 'init', array( Schema::class, 'install' ), 6 );
		add_action( 'init', array( Migration::class, 'maybe_schedule' ), 30 );
		add_action( Migration::HOOK, array( Migration::class, 'run_scheduled' ) );
		add_filter( 'cron_schedules', array( Deadlines::class, 'schedules' ) );
		add_action( 'init', array( Deadlines::class, 'schedule' ), 31 );
		add_action( 'ohmylms_finalize_attempts', array( Deadlines::class, 'finalize_due' ) );
		add_filter( 'ohmylms_attempt_extra_seconds', array( AssessmentSettings::class, 'extra_seconds' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( Delivery::class, 'enqueue' ) );
		// Skill evidence: processed at the end of grading requests, with WP-Cron as the durable fallback.
		add_filter( 'cron_schedules', array( \OhMyLMS\Skills\Evidence::class, 'schedules' ) );
		add_action( 'init', array( \OhMyLMS\Skills\Evidence::class, 'schedule' ), 31 );
		add_action(
			'ohmylms_process_evidence',
			static function () {
				\OhMyLMS\Skills\Evidence::process( 500 );
				\OhMyLMS\Skills\Mastery::refresh_reviews();
			}
		);
		foreach ( array( 'ohmylms_attempt_submitted', 'ohmylms_attempt_graded', 'ohmylms_answer_regraded' ) as $hook ) {
			add_action( $hook, array( \OhMyLMS\Skills\Evidence::class, 'soon' ) );
		}
		// Guest practice and inline checks.
		add_action(
			'init',
			static function () {
				if ( ! wp_next_scheduled( 'ohmylms_guest_cleanup' ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ohmylms_guest_cleanup' );
				} },
			31
		);
		add_action( 'ohmylms_guest_cleanup', array( \OhMyLMS\Practice\Guests::class, 'cleanup' ) );
		add_action( 'ohmylms_register_extensions', array( \OhMyLMS\Practice\Inline::class, 'register' ) );
		\OhMyLMS\Practice\Frontend::init();
		do_action( 'ohmylms_assessment_loaded' );
	}
}
