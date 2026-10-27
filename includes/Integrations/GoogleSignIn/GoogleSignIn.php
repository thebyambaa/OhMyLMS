<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Google Sign-In addon bootstrap.
 *
 * @package OhMyLMS\Integrations\GoogleSignIn
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleSignIn;

use OhMyLMS\Rest\V1\AuthController;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GoogleSignIn
 */
class GoogleSignIn {

	/**
	 * Constructor.
	 */
	public function __construct() {
		new Hooks();

		\add_action( 'rest_api_init', array( new AuthController(), 'register_routes' ) );
	}
}
