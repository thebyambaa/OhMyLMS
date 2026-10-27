<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Integrations\GoogleMeet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OhMyLMS\Integrations\GoogleMeet\Includes\Hooks;
use OhMyLMS\Integrations\GoogleMeet\Includes\SessionReminderScheduler;

/**
 * Plugin google meet implementation.
 */
class GoogleMeet {

	/**
	 * Integration name.
	 *
	 * @var mixed
	 */
	public $integration_name = 'GoogleMeet';

	const Version = '1.0.0';

	/**
	 * Initialize Google meet.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_classes();
	}

	/**
	 * Init classes for Google meet.
	 */
	private function init_classes() {
		// Register REST API routes for Google Meet settings.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		\add_action(
			'rest_api_init',
			array( \OhMyLMS\Integrations\GoogleMeet\Includes\Rest\GoogleMeetSettingsController::instance(), 'register_routes' )
		);

		new Hooks();
		new Includes\Ajax();
		SessionReminderScheduler::init();
	}

	/**
	 * Define constants for Google meet.
	 */
	private function define_constants() {
		define( 'OHMYLMS_GOOGLEMEET_INTEGRATION_URL', plugins_url( '', OHMYLMS_PRO_FILE ) );
	}
}
