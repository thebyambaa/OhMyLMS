<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Loads the options every request reads in one query.
 *
 * WordPress loads autoloaded options together, but an option stored with autoload off, or one that
 * was never saved, costs a query of its own each time a request first reads it. The plugin reads
 * about thirty of those while it starts. Priming them answers all of them with a single query and
 * changes neither their values nor how they are stored.
 *
 * `tests/php/startup-options-integration.php` fails when startup reads an option that is not listed.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Utility;

defined( 'ABSPATH' ) || exit;

/** Primes the option cache for the plugin's startup reads. */
final class StartupOptions {

	/**
	 * Options read on every request that are not autoloaded or may not exist.
	 */
	const NAMES = array(
		// Schema and migration markers.
		'ohmylms_exam_results_db',
		'ohmylms_school_schema',
		'ohmylms_assessment_schema',
		'ohmylms_xapi_schema',
		'ohmylms_learning_schema',
		'ohmylms_curriculum_schema',
		'ohmylms_family_schema',
		'ohmylms_assessment_migration',
		'ohmylms_skill_provenance_backfill',
		// Settings.
		'ohmylms_terminology',
		'ohmylms_student_hub',
		'ohmylms_xapi_settings',
		'ohmylms_streak_settings',
		'ohmylms_xp_settings',
		'ohmylms_score_settings',
		'ohmylms_currency',
		'ohmylms_gateway_order',
		'ohmylms_paypal_settings',
		// Account and checkout endpoints.
		'ohmylms_myprofile_profile_endpoint',
		'ohmylms_myprofile_profile_edit_endpoint',
		'ohmylms_myprofile_course_endpoint',
		'ohmylms_myprofile_settings_endpoint',
		'ohmylms_myprofile_notification_endpoint',
		'ohmylms_myprofile_transactions_history_endpoint',
		'ohmylms_myprofile_membership_endpoint',
		'ohmylms_myprofile_invoice_details_endpoint',
		'ohmylms_myprofile_edit_account_endpoint',
		'ohmylms_logout_endpoint',
		'ohmylms_checkout_order_received_endpoint',
	);

	/**
	 * Load the listed options that are not cached yet with one query.
	 */
	public static function prime() {
		if ( function_exists( 'wp_prime_option_caches' ) ) {
			wp_prime_option_caches( self::NAMES );
		}
	}
}
