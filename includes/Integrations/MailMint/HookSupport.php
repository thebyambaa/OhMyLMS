<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Feature-owned hook operations, with registration kept in the compatibility adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Integrations\MailMint;

defined( 'ABSPATH' ) || exit;

/** Owns the Integrations MailMint operations delegated by CommonHook. */
final class HookSupport {

	/**
	 * Allow webhook url mail mint to create contact from setup wizard
	 *
	 * @param bool $is_allowed Is allowed.
	 * .
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function maybe_allowed_webhook( $is_allowed ) {
		$query_string      = 'mailmint=1&route=webhook&topic=contact&hash=f9fcd11c-fde8-4c7f-9810-0ebb02d2740e';
		$user_query_string = isset( $_SERVER['QUERY_STRING'] ) ? wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared for exact equality with the constant above.

		if ( $query_string !== $user_query_string ) {
			return true;
		}

		$user_agent          = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$allowed_user_agents = array(
			'Mozilla', // Covers Firefox.
			'Chrome',  // Covers Chrome.
			'Safari',  // Covers Safari.
			'Opera',   // Covers Opera.
			'Edge',    // Covers Edge.
			'WordPress',
		);

		foreach ( $allowed_user_agents as $allowed_user_agent ) {
			if ( stripos( $user_agent, $allowed_user_agent ) !== false ) {
					return true;
			}
		}
		return false;
	}

	/**
	 * Create contact on mm from appsero optin for Common hook.
	 *
	 * @param mixed $data Input data.
	 */
	public function create_contact_on_mm_from_appsero_optin( $data ) {
		if ( isset( $data['admin_email'], $data['first_name'], $data['last_name'] ) ) {
			$json_body_data = wp_json_encode(
				array(
					'email'      => $data['admin_email'],
					'first_name' => $data['first_name'],
					'last_name'  => $data['last_name'],
				)
			);

			$web_hook_url = array(
				'https://staging-useraccount.kinsta.cloud/?mailmint=1&route=webhook&topic=contact&hash=b258136d-6759-4e91-bbab-0b7397af6dc7',
			);

			try {
				if ( ! empty( $web_hook_url ) ) {
					foreach ( $web_hook_url as $url ) {
						wp_remote_request(
							$url,
							array(
								'method'  => 'POST',
								'headers' => array(
									'Content-Type' => 'application/json',
								),
								'body'    => $json_body_data,
							)
						);
					}
				}
			} catch ( \Exception $e ) {
				// Optional opt-in delivery must not interrupt the originating admin action.
				return;
			}
		}
	}
}
