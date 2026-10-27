<?php // phpcs:ignore WordPress.Files.FileName -- Existing PSR-4 class/file mapping.
/**
 * Separate administrator assistant connection, with optional OpenAI key reuse.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

defined( 'ABSPATH' ) || exit;

/** Does not enable or change learner AI feedback settings. */
final class AssistantSettings {
	const OPTION = 'ohmylms_admin_assistant';

	/**
	 * Read stored settings.
	 *
	 * @return array Stored settings.
	 */
	public static function get() {
		return array_merge(
			array(
				'enabled' => false,
				'model'   => '',
				'key'     => '',
			),
			(array) get_option( self::OPTION, array() )
		);
	}

	/**
	 * Resolve the server credential.
	 *
	 * @return string Server-only credential.
	 */
	public static function key() {
		if ( defined( 'OHMYLMS_ASSISTANT_API_KEY' ) && OHMYLMS_ASSISTANT_API_KEY ) {
			return (string) OHMYLMS_ASSISTANT_API_KEY;
		}
		$key = getenv( 'OHMYLMS_ASSISTANT_API_KEY' );
		if ( $key ) {
			return $key;
		}
		$key = Secrets::open( self::get()['key'] );
		if ( $key ) {
			return $key;
		}
		return 'openai' === Settings::get()['provider'] ? Settings::api_key() : '';
	}

	/**
	 * Resolve the explicit or existing OpenAI model.
	 *
	 * @return string Model ID.
	 */
	public static function model() {
		if ( defined( 'OHMYLMS_ASSISTANT_MODEL' ) ) {
			return Settings::clean_model( OHMYLMS_ASSISTANT_MODEL );
		}
		$settings = self::get();
		if ( $settings['model'] ) {
			return $settings['model'];
		}
		return 'openai' === Settings::get()['provider'] ? Settings::get()['model'] : '';
	}

	/**
	 * Describe connection readiness without secrets.
	 *
	 * @return array Connection state.
	 */
	public static function summary() {
		return array(
			'enabled'    => (bool) self::get()['enabled'],
			'model'      => self::model(),
			'has_key'    => '' !== self::key(),
			'configured' => (bool) self::get()['enabled'] && '' !== self::key() && '' !== self::model(),
		);
	}

	/**
	 * Accept a key only through the nonce-protected administrator settings route.
	 *
	 * @param array $data Settings input.
	 * @return array|\WP_Error
	 */
	public static function save( array $data ) {
		if ( ! is_bool( $data['enabled'] ?? null ) || ! is_string( $data['model'] ?? null ) || ! is_string( $data['key'] ?? '' ) || ! is_bool( $data['clear_key'] ?? false ) ) {
			return new \WP_Error( 'ohmylms_assistant_settings', __( 'Enter a valid model and connection settings.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$model = Settings::clean_model( $data['model'] );
		if ( '' !== $data['model'] && '' === $model ) {
			return new \WP_Error( 'ohmylms_assistant_model', __( 'Enter a valid OpenAI model ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$settings            = self::get();
		$settings['enabled'] = $data['enabled'];
		$settings['model']   = $model;
		if ( ! empty( $data['clear_key'] ) ) {
			$settings['key'] = '';
		}
		$key = trim( $data['key'] ?? '' );
		if ( '' !== $key ) {
			if ( strlen( $key ) > 512 || preg_match( '/\s/', $key ) ) {
				return new \WP_Error( 'ohmylms_assistant_key', __( 'Enter a valid API key.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			try {
				$settings['key'] = Secrets::seal( $key );
			} catch ( \Throwable $error ) {
				$settings['key'] = '';
			}
			if ( ! $settings['key'] ) {
				return new \WP_Error( 'ohmylms_assistant_key_storage', __( 'Secure key storage is unavailable. Configure the server environment variable instead.', 'ohmylms' ), array( 'status' => 503 ) );
			}
		}
		update_option( self::OPTION, $settings, false );
		return self::summary();
	}
}
