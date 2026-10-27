<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OpenAI Responses API (platform.openai.com). Reasoning models spend part of the token
 * budget thinking; if a reply comes back empty for that reason the error says so.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI Responses API (platform.openai.com). Reasoning models spend part of the token
 * budget thinking; if a reply comes back empty for that reason the error says so.
 */
final class OpenAI implements Provider {
	/**
	 * Request for Open ai.
	 *
	 * @param array $settings Configuration settings.
	 * @param mixed $key Lookup key.
	 * @param array $request REST request.
	 */
	public function request( array $settings, $key, array $request ) {
		return array(
			'url'     => 'https://api.openai.com/v1/responses',
			'headers' => array(
				'authorization' => 'Bearer ' . $key,
				'content-type'  => 'application/json',
			),
			'body'    => array(
				'model'             => $settings['model'],
				'instructions'      => $request['system'],
				'input'             => $request['user'],
				'max_output_tokens' => (int) $request['max_tokens'],
				'store'             => false,
			),
		);
	}

	/**
	 * Parse for Open ai.
	 *
	 * @param array $decoded Decoded.
	 * @param mixed $status Status.
	 */
	public function parse( array $decoded, $status ) {
		if ( $status >= 400 || ! empty( $decoded['error'] ) ) {
			return Errors::from_status( $status, (string) ( $decoded['error']['message'] ?? '' ) ); }
		$text = '';
		foreach ( (array) ( $decoded['output'] ?? array() ) as $item ) {
			if ( ( $item['type'] ?? '' ) !== 'message' ) {
				continue; }
			foreach ( (array) ( $item['content'] ?? array() ) as $part ) {
				if ( ( $part['type'] ?? '' ) === 'output_text' ) {
					$text .= (string) ( $part['text'] ?? '' ); }
			}
		}
		if ( trim( $text ) === '' ) {
			return Errors::empty_reply( ( $decoded['status'] ?? '' ) === 'incomplete' ); }
		return array(
			'text'          => $text,
			'input_tokens'  => (int) ( $decoded['usage']['input_tokens'] ?? 0 ),
			'output_tokens' => (int) ( $decoded['usage']['output_tokens'] ?? 0 ),
		);
	}
}
