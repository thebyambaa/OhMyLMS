<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hooks class.
 *
 * Registers the Google Sign-In addon card on the Addons (Integrations) page
 * and exposes the addon's enabled/disabled state to the rest of the plugin.
 *
 * @package OhMyLMS\Integrations\GoogleSignIn
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleSignIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Hooks
 */
class Hooks {

	const INTEGRATION_KEY = 'google_signin';

	/**
	 * Hooks constructor.
	 */
	public function __construct() {
		\add_filter( 'ohmylms_integrations', array( $this, 'register_integration' ), 10, 1 );
	}

	/**
	 * Register Google Sign-In in the Addons list.
	 *
	 * @param array $integrations The existing integrations.
	 * @return array
	 */
	public function register_integration( $integrations ) {
		$integrations[ self::INTEGRATION_KEY ] = array(
			'label'         => __( 'Google Sign-In', 'ohmylms' ),
			'description'   => __( 'Let students sign up and log in with their Google account.', 'ohmylms' ),
			'icon'          => plugins_url( 'includes/Integrations/GoogleSignIn/Assets/google-icon.svg', OHMYLMS_FILE ),
			'categories'    => array( 'course-engagement' ),
			'hasSettings'   => true,
			'class'         => 'OhMyLMS\\Integrations\\GoogleSignIn\\GoogleSignIn',
			'is_valid'      => true,
			'required_plan' => null,
		);

		return $integrations;
	}

	/**
	 * Whether the Google Sign-In addon is toggled on from the Addons page.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$integrations = get_option( 'ohmylms_integrations', array() );

		return ! empty( $integrations[ self::INTEGRATION_KEY ]['is_enable'] );
	}
}
