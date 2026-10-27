<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * One AI provider's wire format. Adapters only build a request and read a response, so they
 * can be tested without a network and replaced through the ohmylms_ai_providers filter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * One AI provider's wire format. Adapters only build a request and read a response, so they
 * can be tested without a network and replaced through the ohmylms_ai_providers filter.
 *
 * A request is [ 'system' => string, 'user' => string, 'max_tokens' => int ].
 */
interface Provider {
	/**
	 * Request for Provider.
	 *
	 * @return array{url:string,headers:array,body:array}
	 * @param array $settings Configuration settings.
	 * @param mixed $key Lookup key.
	 * @param array $request REST request.
	 */
	public function request( array $settings, $key, array $request );

	/**
	 * Read a decoded JSON response.
	 *
	 * @return array{text:string,input_tokens:int,output_tokens:int}|\WP_Error
	 * @param array $decoded Decoded.
	 * @param mixed $status Status.
	 */
	public function parse( array $decoded, $status );
}
