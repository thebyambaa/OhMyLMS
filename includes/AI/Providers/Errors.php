<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Provider failures in words an administrator can act on. The provider's own message is kept
 * (shortened) because it usually names the real problem, such as an unknown model; the API key
 * is never part of any message.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Provider failures in words an administrator can act on. The provider's own message is kept
 * (shortened) because it usually names the real problem, such as an unknown model; the API key
 * is never part of any message.
 */
final class Errors {
	/**
	 * From status for Errors.
	 *
	 * @param mixed $status Status.
	 * @param mixed $message Message.
	 */
	public static function from_status( $status, $message ) {
		$detail = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $message ) ) );
		$detail = mb_strlen( $detail ) > 160 ? mb_substr( $detail, 0, 160 ) . '…' : $detail;
		$status = (int) $status;
		if ( in_array( $status, array( 401, 403 ), true ) ) {
			$code = 'ohmylms_ai_auth';
			$text = __( 'The AI provider rejected the API key.', 'ohmylms' );
		} elseif ( 404 === $status ) {
			$code = 'ohmylms_ai_model';
			$text = __( 'The AI provider does not know this model. Check the model name.', 'ohmylms' );
		} elseif ( 429 === $status ) {
			$code = 'ohmylms_ai_rate';
			$text = __( 'The AI provider is rate limiting this account or has no quota left.', 'ohmylms' );
		} elseif ( $status >= 500 ) {
			$code = 'ohmylms_ai_unavailable';
			$text = __( 'The AI provider is unavailable right now.', 'ohmylms' );
		} else {
			$code = 'ohmylms_ai_request';
			$text = __( 'The AI provider could not process the request.', 'ohmylms' );
		}
		return new \WP_Error( $code, '' !== $detail ? $text . ' (' . $detail . ')' : $text, array( 'status' => 502 ) );
	}

	/** A reply with no text: usually the token budget was used up before any text was written.
	 *
	 * @param mixed $ran_out Ran out.
	 */
	public static function empty_reply( $ran_out ) {
		return new \WP_Error(
			'ohmylms_ai_empty',
			$ran_out
				? __( 'The model used its whole token budget before answering. Raise "Maximum reply length" or choose a model that does not think at length.', 'ohmylms' )
				: __( 'The AI provider returned no text.', 'ohmylms' ),
			array( 'status' => 502 )
		);
	}
}
