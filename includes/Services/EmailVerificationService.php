<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Email Verification Service
 *
 * @package OhMyLMS\Services
 * @since   1.0.0
 */

namespace OhMyLMS\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles token generation, validation, and status for core email verification.
 *
 * Meta keys are intentionally separate from the Community plugin's keys so the
 * two systems don't collide. The Community plugin checks these core keys and
 * skips its own verification when core verification is already complete.
 */
class EmailVerificationService {

	const META_VERIFIED = '_ohmylms_email_verified';
	const META_TOKEN    = '_ohmylms_email_verify_token';
	const META_EXPIRES  = '_ohmylms_email_verify_token_expires';
	const OPTION_KEY    = 'ohmylms_require_email_verification';
	const TOKEN_TTL     = DAY_IN_SECONDS; // 24 hours

	/**
	 * Whether email verification is required site-wide.
	 */
	public static function is_required(): bool {
		return 'yes' === get_option( self::OPTION_KEY, 'no' );
	}

	/**
	 * Whether a given user's email is verified.
	 *
	 * Users whose meta was never set (accounts created before this feature)
	 * are treated as verified to avoid locking out existing students.
	 *
	 * @param int $user_id User identifier.
	 */
	public static function is_verified( int $user_id ): bool {
		if ( ! self::is_required() ) {
			return true;
		}
		$value = get_user_meta( $user_id, self::META_VERIFIED, true );
		if ( 'no' === $value ) {
			return false;
		}
		// Empty string = meta never set → backwards-compatible treat as verified.
		return true;
	}

	/**
	 * Generate a verification token, store it in user meta, and return it.
	 *
	 * @param int $user_id User identifier.
	 */
	public static function generate_token( int $user_id ): string {
		$token = bin2hex( random_bytes( 32 ) );
		update_user_meta( $user_id, self::META_VERIFIED, 'no' );
		update_user_meta( $user_id, self::META_TOKEN, $token );
		update_user_meta( $user_id, self::META_EXPIRES, time() + self::TOKEN_TTL );
		return $token;
	}

	/**
	 * Validate a token and mark the user as verified on success.
	 *
	 * @return int|false User ID on success, false on invalid or expired token.
	 * @param string $token Token.
	 */
	public static function verify_token( string $token ) {
		$result = self::verify_token_detailed( $token );
		return 'verified' === $result['status'] ? $result['user_id'] : false;
	}

	/**
	 * Validate a token and return a detailed result.
	 *
	 * @return array{status: 'verified'|'expired'|'invalid', user_id: int}
	 * @param string $token Token.
	 */
	public static function verify_token_detailed( string $token ): array {

		$invalid = array(
			'status'  => 'invalid',
			'user_id' => 0,
		);

		if ( empty( $token ) ) {
			return $invalid;
		}

		$user_ids = get_users(
			array(
				'meta_key'    => self::META_TOKEN,
				'meta_value'  => $token,
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => false,
			)
		);
		$user_id  = $user_ids ? (int) $user_ids[0] : 0;

		if ( ! $user_id ) {
			return $invalid;
		}

		$expires = (int) get_user_meta( $user_id, self::META_EXPIRES, true );
		if ( time() > $expires ) {
			delete_user_meta( $user_id, self::META_TOKEN );
			delete_user_meta( $user_id, self::META_EXPIRES );
			return array(
				'status'  => 'expired',
				'user_id' => $user_id,
			);
		}

		update_user_meta( $user_id, self::META_VERIFIED, 'yes' );
		delete_user_meta( $user_id, self::META_TOKEN );
		delete_user_meta( $user_id, self::META_EXPIRES );

		return array(
			'status'  => 'verified',
			'user_id' => $user_id,
		);
	}

	/**
	 * Generate a token and fire the action that dispatches the verification email.
	 *
	 * Returns false if the user is already verified or a send was throttled within
	 * the last 2 minutes (prevents inbox flooding via rapid resend clicks).
	 *
	 * @param int $user_id User identifier.
	 */
	public static function generate_and_send( int $user_id ): bool {
		$value = get_user_meta( $user_id, self::META_VERIFIED, true );
		if ( 'yes' === $value ) {
			return false;
		}

		$cooldown_key = 'ohmylms_verify_cooldown_' . $user_id;
		if ( get_transient( $cooldown_key ) ) {
			return false;
		}
		set_transient( $cooldown_key, 1, 2 * MINUTE_IN_SECONDS );

		$token = self::generate_token( $user_id );
		do_action( 'ohmylms_send_email_verification', $user_id, $token );
		return true;
	}

	/**
	 * Build the verification URL for a given token.
	 * Handled via template_redirect in CommonHook.
	 *
	 * @param string $token Token.
	 */
	public static function get_verification_url( string $token ): string {
		return add_query_arg(
			array(
				'ohmylms_verify_email' => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Build the resend-verification AJAX URL.
	 */
	public static function get_resend_url(): string {
		return add_query_arg(
			array(
				'action' => 'ohmylms_resend_verification',
				'nonce'  => wp_create_nonce( 'ohmylms_resend_verification' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}
}
