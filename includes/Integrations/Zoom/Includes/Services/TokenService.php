<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * TokenService class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Services;

use OhMyLMS\Integrations\Zoom\Includes\Helpers\ZoomApiHelper;

/**
 * Class TokenService
 *
 * @package OhMyLMS\Integrations\Zoom\Services
 * @since 1.0.0
 */
class TokenService {
	/**
	 * The single instance of the class.
	 *
	 * @var TokenService
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Get instance
	 *
	 * @since 1.0.0
	 *
	 * @return TokenService
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get a valid access token.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The access token or false on failure.
	 */
	public function get_valid_access_token() {
		$user_id      = get_current_user_id();
		$access_token = get_user_meta( $user_id, 'clms_zoom_access_token', true );
		$expires_at   = get_user_meta( $user_id, 'clms_zoom_token_expires', true );

		if ( $access_token && $expires_at && time() < $expires_at ) {
			return $access_token;
		}

		// Token is expired or doesn't exist, get a new one.
		return $this->get_new_access_token();
	}

	/**
	 * Get a new access token from Zoom.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The new access token or false on failure.
	 */
	private function get_new_access_token() {
		$account_id    = ZoomApiHelper::get_account_id();
		$client_id     = ZoomApiHelper::get_client_id();
		$client_secret = ZoomApiHelper::get_client_secret();
		$response      = wp_remote_post(
			'https://zoom.us/oauth/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'account_credentials',
					'account_id' => $account_id,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( isset( $data['access_token'] ) ) {
			$this->store_token_data( $data );
			return $data['access_token'];
		}

		return false;
	}

	/**
	 * Store the token data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The token data.
	 */
	private function store_token_data( $data ) {
		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'clms_zoom_access_token', $data['access_token'] );
		update_user_meta( $user_id, 'clms_zoom_token_expires', time() + $data['expires_in'] );
	}
}
