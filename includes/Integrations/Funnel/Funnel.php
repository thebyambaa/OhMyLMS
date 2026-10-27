<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Integrations\Funnel;

use OhMyLMS\Integrations\Funnel\Includes\Hooks;
use OhMyLMS\Integrations\Funnel\Includes\FunnelManager;
use OhMyLMS\Integrations\Funnel\Includes\FunnelEndpoint;

/**
 * Plugin funnel implementation.
 */
class Funnel {

	/**
	 * Integration name.
	 *
	 * @var mixed
	 */
	public $integration_name = 'Funnel';

	/**
	 * Initialize Funnel.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_classes();
	}

	/**
	 * Define constants related to Funnel integration.
	 *
	 * @since 1.0.0
	 */
	public function define_constants() {
		// Define any constants needed for the Funnel integration.
	}

	/**
	 * Initialize classes related to Funnel integration.
	 *
	 * @since 1.0.0
	 */
	public function init_classes() {
		new Hooks();
		new FunnelEndpoint();
	}
}
