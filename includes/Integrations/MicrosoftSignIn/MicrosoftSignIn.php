<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Microsoft Sign-In addon bootstrap.
 *
 * @package OhMyLMS\Integrations\MicrosoftSignIn
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\MicrosoftSignIn;

use OhMyLMS\Rest\V1\MicrosoftAuthController;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class MicrosoftSignIn
 */
class MicrosoftSignIn {

	/**
	 * Constructor.
	 */
	public function __construct() {
		new Hooks();

		\add_action( 'rest_api_init', array( new MicrosoftAuthController(), 'register_routes' ) );
	}
}
