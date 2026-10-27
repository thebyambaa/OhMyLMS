<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Integrations\Zoom;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OhMyLMS\Integrations\Zoom\Includes\Hooks;
use OhMyLMS\Integrations\Zoom\Includes\SessionReminderScheduler;

/**
 * Plugin zoom implementation.
 */
class Zoom {

	/**
	 * Integration name.
	 *
	 * @var mixed
	 */
	public $integration_name = 'Zoom';

	const Version = '1.0.0';


	/**
	 * Initialize Zoom.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_classes();
	}

	/**
	 * Init classes for Zoom.
	 */
	private function init_classes() {
		// Register REST API routes for Zoom settings.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		\add_action(
			'rest_api_init',
			array( \OhMyLMS\Integrations\Zoom\Includes\Rest\ZoomSettingsController::instance(), 'register_routes' )
		);

		// Register the recording.completed webhook receiver.
		\add_action(
			'rest_api_init',
			array( \OhMyLMS\Integrations\Zoom\Includes\Rest\ZoomWebhookController::instance(), 'register_routes' )
		);

		new Hooks();
		SessionReminderScheduler::init();
	}

	/**
	 * Define constants for Zoom.
	 */
	private function define_constants() {
		define( 'OHMYLMS_ZOOM_INTEGRATION_URL', plugins_url( '', OHMYLMS_PRO_FILE ) );
	}
}
