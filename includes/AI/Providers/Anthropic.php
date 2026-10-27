<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Claude Messages API (platform.claude.com).
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\AI\Providers;

defined( 'ABSPATH' ) || exit;

/** Claude Messages API (platform.claude.com). */
final class Anthropic implements Provider {
	/**
	 * Request for Anthropic.
	 *
	 * @param array $settings Configuration settings.
	 * @param mixed $key Lookup key.
	 * @param array $request REST request.
	 */
	public function request( array $settings, $key, array $request ) {
		return array(
			'url'     => 'https://api.anthropic.com/v1/messages',
			'headers' => array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => array(
				'model'      => $settings['model'],
				'max_tokens' => (int) $request['max_tokens'],
				'system'     => $request['system'],
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $request['user'],
					),
				),
			),
		);
	}

	/**
	 * Parse for Anthropic.
	 *
	 * @param array $decoded Decoded.
	 * @param mixed $status Status.
	 */
	public function parse( array $decoded, $status ) {
		if ( $status >= 400 || ( $decoded['type'] ?? '' ) === 'error' ) {
			return Errors::from_status( $status, (string) ( $decoded['error']['message'] ?? '' ) ); }
		$text = '';
		foreach ( (array) ( $decoded['content'] ?? array() ) as $block ) {
			if ( ( $block['type'] ?? '' ) === 'text' ) {
				$text .= (string) ( $block['text'] ?? '' ); }
		}
		if ( trim( $text ) === '' ) {
			return Errors::empty_reply( (string) ( $decoded['stop_reason'] ?? '' ) === 'max_tokens' ); }
		return array(
			'text'          => $text,
			'input_tokens'  => (int) ( $decoded['usage']['input_tokens'] ?? 0 ),
			'output_tokens' => (int) ( $decoded['usage']['output_tokens'] ?? 0 ),
		);
	}
}
