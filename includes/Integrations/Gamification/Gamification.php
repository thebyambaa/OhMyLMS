<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Integrations\Gamification;

use OhMyLMS\Integrations\Gamification\Includes\Hooks;

/**
 * Plugin gamification implementation.
 */
class Gamification {

	/**
	 * Integration name.
	 *
	 * @var mixed
	 */
	public $integration_name = 'Gamification';

	/**
	 * Initialize Gamification.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_classes();
	}

	/**
	 * Define constants related to Cohorts integration.
	 *
	 * @since 1.0.0
	 */
	public function define_constants() {
	}

	/**
	 * Initialize classes related to Cohorts integration.
	 *
	 * @since 1.0.0
	 */
	public function init_classes() {
		new Hooks();
	}
}
