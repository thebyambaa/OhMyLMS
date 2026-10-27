<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Gemini generateContent API (ai.google.dev). The key travels in a header, not the URL, so it
 * never appears in access logs. Thinking models share the token budget with the visible reply.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Gemini generateContent API (ai.google.dev). The key travels in a header, not the URL, so it
 * never appears in access logs. Thinking models share the token budget with the visible reply.
 */
final class Gemini implements Provider {
	/**
	 * Request for Gemini.
	 *
	 * @param array $settings Configuration settings.
	 * @param mixed $key Lookup key.
	 * @param array $request REST request.
	 */
	public function request( array $settings, $key, array $request ) {
		return array(
			'url'     => 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $settings['model'] ) . ':generateContent',
			'headers' => array(
				'x-goog-api-key' => $key,
				'content-type'   => 'application/json',
			),
			'body'    => array(
				'systemInstruction' => array( 'parts' => array( array( 'text' => $request['system'] ) ) ),
				'contents'          => array(
					array(
						'role'  => 'user',
						'parts' => array( array( 'text' => $request['user'] ) ),
					),
				),
				'generationConfig'  => array( 'maxOutputTokens' => (int) $request['max_tokens'] ),
			),
		);
	}

	/**
	 * Parse for Gemini.
	 *
	 * @param array $decoded Decoded.
	 * @param mixed $status Status.
	 */
	public function parse( array $decoded, $status ) {
		if ( $status >= 400 || ! empty( $decoded['error'] ) ) {
			return Errors::from_status( $status, (string) ( $decoded['error']['message'] ?? '' ) ); }
		if ( ! empty( $decoded['promptFeedback']['blockReason'] ) ) {
			return new \WP_Error( 'ohmylms_ai_blocked', __( 'The AI provider declined to answer this question.', 'ohmylms' ), array( 'status' => 502 ) ); }
		$text = '';
		foreach ( (array) ( $decoded['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			$text .= (string) ( $part['text'] ?? '' ); }
		if ( trim( $text ) === '' ) {
			return Errors::empty_reply( ( $decoded['candidates'][0]['finishReason'] ?? '' ) === 'MAX_TOKENS' ); }
		return array(
			'text'          => $text,
			'input_tokens'  => (int) ( $decoded['usageMetadata']['promptTokenCount'] ?? 0 ),
			'output_tokens' => (int) ( $decoded['usageMetadata']['candidatesTokenCount'] ?? 0 ),
		);
	}
}
