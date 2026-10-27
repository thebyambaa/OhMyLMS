<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ZoomApiHelper class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Helpers;

/**
 * Class ZoomApiHelper
 *
 * @package OhMyLMS\Integrations\Zoom\Helpers
 * @since 1.0.0
 */
class ZoomApiHelper {
	/**
	 * Get the Zoom Account ID from user meta.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_account_id() {
		$user_id  = \get_current_user_id();
		$settings = \get_user_meta( $user_id, 'ohmylms_zoom_api_credentials', true );
		if ( is_array( $settings ) && isset( $settings['account_id'] ) ) {
			return $settings['account_id'];
		}
		// Fallback for backward compatibility.
		return \get_user_meta( $user_id, 'clms_zoom_account_id', true );
	}

	/**
	 * Get the Zoom Client ID from user meta.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_client_id() {
		$user_id  = \get_current_user_id();
		$settings = \get_user_meta( $user_id, 'ohmylms_zoom_api_credentials', true );
		if ( is_array( $settings ) && isset( $settings['client_id'] ) ) {
			return $settings['client_id'];
		}
		// Fallback for backward compatibility.
		return \get_user_meta( $user_id, 'clms_zoom_client_id', true );
	}

	/**
	 * Get the Zoom Client Secret from user meta.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_client_secret() {
		$user_id  = \get_current_user_id();
		$settings = \get_user_meta( $user_id, 'ohmylms_zoom_api_credentials', true );
		if ( is_array( $settings ) && isset( $settings['client_secret'] ) ) {
			return $settings['client_secret'];
		}
		// Fallback for backward compatibility.
		return \get_user_meta( $user_id, 'clms_zoom_client_secret', true );
	}

	/**
	 * Get the Zoom webhook secret token.
	 *
	 * Site-wide (not per-user): the webhook receiver has no logged-in user
	 * context when Zoom calls it, so this can't live in user meta like the
	 * OAuth credentials above.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_webhook_secret_token() {
		return \get_option( 'ohmylms_zoom_webhook_secret_token', '' );
	}
}
