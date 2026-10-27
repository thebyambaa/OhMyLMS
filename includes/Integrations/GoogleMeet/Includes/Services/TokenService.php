<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * TokenService class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes\Services;

/**
 * Class TokenService
 *
 * @package OhMyLMS\Integrations\GoogleMeet\Services
 * @since 1.0.0
 */
class TokenService {
	/**
	 * Get valid access token, refreshing if necessary.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The access token or false on failure.
	 */
	public function get_valid_access_token() {
		$user_id      = \get_current_user_id();
		$access_token = \get_user_meta( $user_id, 'ohmylms_googlemeet_access_token', true );
		$expires_at   = \get_user_meta( $user_id, 'ohmylms_googlemeet_token_expires', true );

		// If token exists and not expired, return it.
		if ( $access_token && $expires_at && time() < $expires_at - 300 ) { // 5 min buffer
			return $access_token;
		}

		// Try to refresh the token.
		return $this->refresh_access_token();
	}

	/**
	 * Refresh the access token using the refresh token.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The new access token or false on failure.
	 */
	private function refresh_access_token() {
		$user_id       = \get_current_user_id();
		$refresh_token = \get_user_meta( $user_id, 'ohmylms_googlemeet_refresh_token', true );
		$credentials   = \get_user_meta( $user_id, 'ohmylms_googlemeet_api_credentials', true );

		if ( ! $refresh_token || empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
			return false;
		}

		$token_url = 'https://oauth2.googleapis.com/token';
		$response  = \wp_remote_post(
			$token_url,
			array(
				'body' => array(
					'client_id'     => $credentials['client_id'],
					'client_secret' => $credentials['client_secret'],
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( \is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( \wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$new_access_token = $body['access_token'];
			\update_user_meta( $user_id, 'ohmylms_googlemeet_access_token', $new_access_token );
			\update_user_meta( $user_id, 'ohmylms_googlemeet_token_expires', time() + $body['expires_in'] );

			return $new_access_token;
		}

		return false;
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The authorization URL or false on failure.
	 */
	public function get_authorization_url() {
		$user_id     = \get_current_user_id();
		$credentials = \get_user_meta( $user_id, 'ohmylms_googlemeet_api_credentials', true );

		if ( empty( $credentials['client_id'] ) || empty( $credentials['redirect_uri'] ) ) {
			return false;
		}

		$params = array(
			'client_id'     => $credentials['client_id'],
			'redirect_uri'  => $credentials['redirect_uri'],
			'response_type' => 'code',
			'scope'         => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	/**
	 * Revoke access token.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function revoke_token() {
		$user_id      = \get_current_user_id();
		$access_token = \get_user_meta( $user_id, 'ohmylms_googlemeet_access_token', true );

		if ( ! $access_token ) {
			return false;
		}

		$revoke_url = 'https://oauth2.googleapis.com/revoke';
		$response   = \wp_remote_post(
			$revoke_url,
			array(
				'body' => array(
					'token' => $access_token,
				),
			)
		);

		// Clear stored tokens.
		\delete_user_meta( $user_id, 'ohmylms_googlemeet_access_token' );
		\delete_user_meta( $user_id, 'ohmylms_googlemeet_refresh_token' );
		\delete_user_meta( $user_id, 'ohmylms_googlemeet_token_expires' );

		return ! \is_wp_error( $response );
	}
}
