<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Base controller for the OhMyLMS commerce API.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Abstracts;

use WP_REST_Controller;

/**
 * Rest Controller base class.
 *
 * @since 0.3.0
 */
abstract class RestController extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms-commerce/v1';

	/**
	 * Register the previous commerce routes on installations without CreatorLMS.
	 *
	 * The caller uses a separate controller so callbacks retain their namespace.
	 *
	 * @return void
	 */
	public function register_legacy_routes(): void {
		$this->namespace = 'cx-ecommerce/v1';
		$this->register_routes();
	}

	/**
	 * Check default permission for rest routes.
	 *
	 * @since 0.3.0
	 *
	 * @TODO: manage permissions from capabilities.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return true;
	}
}
