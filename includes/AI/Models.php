<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Administrator-only discovery through the selected provider's Models API.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

defined( 'ABSPATH' ) || exit;

/** Discover model identifiers without exposing credentials or making generation requests. */
final class Models {
	/**
	 * Load one bounded catalogue page using saved credentials, independently of tool switches.
	 *
	 * @return array|\WP_Error Sanitized identifiers and pagination status.
	 */
	public static function listing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'ai_models_forbidden', __( 'You cannot manage AI connections.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$settings    = Settings::get();
		$key         = Settings::api_key();
		$connections = array(
			'openai'    => array( 'https://api.openai.com/v1/models', array( 'Authorization' => 'Bearer ' . $key ) ),
			'anthropic' => array(
				'https://api.anthropic.com/v1/models?limit=1000',
				array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
				),
			),
			'gemini'    => array( 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1000', array( 'x-goog-api-key' => $key ) ),
		);
		$provider    = $settings['provider'];
		if ( '' === $key || ! isset( $connections[ $provider ] ) ) {
			return new \WP_Error( 'ai_models_key', __( 'Save your provider and API key first.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$response = wp_remote_get(
			$connections[ $provider ][0],
			array(
				'headers'             => $connections[ $provider ][1],
				'timeout'             => 20,
				'redirection'         => 0,
				'limit_response_size' => 2097152,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ai_models_network', __( 'Could not load models from the provider. Your saved settings were kept.', 'ohmylms' ), array( 'status' => 502 ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new \WP_Error( 'ai_models_response', __( 'The provider could not list models. Check your key permissions, provider and account limits.', 'ohmylms' ), array( 'status' => 502 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$rows = $data[ 'gemini' === $provider ? 'models' : 'data' ] ?? null;
		if ( ! is_array( $rows ) ) {
			return new \WP_Error( 'ai_models_format', __( 'The provider returned an unreadable model list.', 'ohmylms' ), array( 'status' => 502 ) );
		}
		$models = array();
		foreach ( array_slice( $rows, 0, 1000 ) as $row ) {
			if ( ! is_array( $row ) || 'retired' === ( $row['lifecycle'] ?? '' ) || ( 'gemini' === $provider && ! in_array( 'generateContent', (array) ( $row['supportedGenerationMethods'] ?? array() ), true ) ) ) {
				continue;
			}
			$raw_id = $row[ 'gemini' === $provider ? 'name' : 'id' ] ?? null;
			$id     = is_string( $raw_id ) ? Settings::clean_model( $raw_id ) : '';
			if ( '' !== $id ) {
				$models[ $id ] = $id;
			}
		}
		sort( $models, SORT_NATURAL | SORT_FLAG_CASE );
		return array(
			'provider'  => $provider,
			'models'    => array_values( $models ),
			'truncated' => ! empty( $data['has_more'] ) || ! empty( $data['nextPageToken'] ) || count( $rows ) > 1000,
		);
	}
}
