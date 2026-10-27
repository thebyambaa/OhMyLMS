<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared AI connection and independent tool policies (option "ohmylms_ai").
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Shared AI connection and independent tool policies (option "ohmylms_ai").
 *
 * AI only explains and hints: it never grades, scores or changes mastery. When it is off,
 * unconfigured or failing, practice works exactly as before with the authored hint and
 * explanation.
 *
 * wp-config.php may define OHMYLMS_AI_API_KEY (or the same environment variable),
 * OHMYLMS_AI_PROVIDER and OHMYLMS_AI_MODEL; those win over the stored values.
 */
final class Settings {
	const OPTION    = 'ohmylms_ai';
	const PROVIDERS = array(
		'anthropic' => 'Anthropic (Claude)',
		'openai'    => 'OpenAI',
		'gemini'    => 'Google (Gemini)',
	);

	/** Defaults keep both tools off until an administrator enables them. */
	public static function defaults() {
		return array(
			'enabled'                => false,
			'exam_converter_enabled' => false,
			'provider'               => 'anthropic',
			'model'                  => '',
			'key'                    => '',
			'hints'                  => true,
			'explanations'           => true,
			'guests'                 => false,
			'daily_limit'            => 30,
			'max_tokens'             => 400,
			'timeout'                => 20,
		);
	}

	/** Stored settings over the defaults, then constants over both. */
	public static function get() {
		$stored   = get_option( self::OPTION, array() );
		$settings = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		if ( defined( 'OHMYLMS_AI_PROVIDER' ) && isset( self::PROVIDERS[ OHMYLMS_AI_PROVIDER ] ) ) {
			$settings['provider'] = OHMYLMS_AI_PROVIDER; }
		if ( defined( 'OHMYLMS_AI_MODEL' ) ) {
			$settings['model'] = self::clean_model( OHMYLMS_AI_MODEL ); }
		return $settings;
	}

	/**
	 * Normalize plain model identifiers, dropping Gemini's models/ prefix.
	 *
	 * @param string $model Provider model identifier.
	 * @return string Valid identifier or an empty string.
	 */
	public static function clean_model( $model ) {
		$model = preg_replace( '#^models/#', '', trim( (string) $model ) );
		return preg_match( '/^[A-Za-z0-9._:\/-]{1,100}$/', $model ) ? $model : '';
	}

	/** Where the key comes from: constant, environment, stored or none. */
	public static function key_source() {
		if ( defined( 'OHMYLMS_AI_API_KEY' ) && OHMYLMS_AI_API_KEY !== '' ) {
			return 'constant'; }
		if ( getenv( 'OHMYLMS_AI_API_KEY' ) ) {
			return 'environment'; }
		return self::api_key() !== '' ? 'stored' : 'none';
	}

	/** The usable API key. Never put this in a response, a log or a page. */
	public static function api_key() {
		if ( defined( 'OHMYLMS_AI_API_KEY' ) && OHMYLMS_AI_API_KEY !== '' ) {
			return (string) OHMYLMS_AI_API_KEY; }
		$environment = getenv( 'OHMYLMS_AI_API_KEY' );
		if ( $environment ) {
			return (string) $environment; }
		$stored = get_option( self::OPTION, array() );
		return Secrets::open( is_array( $stored ) ? (string) ( $stored['key'] ?? '' ) : '' );
	}

	/**
	 * Whether the shared provider connection has usable credentials.
	 *
	 * @return bool Connection is configured, independently of tool switches.
	 */
	public static function connection_configured() {
		$settings = self::get();
		return isset( self::PROVIDERS[ $settings['provider'] ] ) && '' !== $settings['model'] && '' !== self::api_key();
	}

	/**
	 * Whether a particular tool may send requests through the shared connection.
	 *
	 * Keep the no-argument tutor contract and its established enabled REST field.
	 *
	 * @param string $tool Tutor or exam_converter.
	 * @return bool Tool is enabled and configured.
	 */
	public static function configured( $tool = 'tutor' ) {
		$fields = array(
			'tutor'          => 'enabled',
			'exam_converter' => 'exam_converter_enabled',
		);
		return isset( $fields[ $tool ] ) && ! empty( self::get()[ $fields[ $tool ] ] ) && self::connection_configured();
	}

	/**
	 * Save admin input. A blank key keeps the stored one; $clear_key removes it.
	 *
	 * @param array $input Submitted settings.
	 * @param bool  $clear_key Remove the stored key.
	 * @return array the sanitized settings that were stored
	 */
	public static function update( array $input, $clear_key = false ) {
		$current  = self::get();
		$stored   = get_option( self::OPTION, array() );
		$provider = (string) ( $input['provider'] ?? $current['provider'] );
		$next     = array(
			'enabled'                => ! empty( $input['enabled'] ),
			'exam_converter_enabled' => array_key_exists( 'exam_converter_enabled', $input ) ? ! empty( $input['exam_converter_enabled'] ) : ! empty( $current['exam_converter_enabled'] ),
			'provider'               => isset( self::PROVIDERS[ $provider ] ) ? $provider : 'anthropic',
			'model'                  => self::clean_model( $input['model'] ?? '' ),
			'hints'                  => ! empty( $input['hints'] ),
			'explanations'           => ! empty( $input['explanations'] ),
			'guests'                 => ! empty( $input['guests'] ),
			'daily_limit'            => max( 1, min( 500, (int) ( $input['daily_limit'] ?? 30 ) ) ),
			'max_tokens'             => max( 100, min( 1200, (int) ( $input['max_tokens'] ?? 400 ) ) ),
			'timeout'                => max( 5, min( 60, (int) ( $input['timeout'] ?? 20 ) ) ),
			'key'                    => is_array( $stored ) ? (string) ( $stored['key'] ?? '' ) : '',
		);
		$new_key  = trim( (string) ( $input['key'] ?? '' ) );
		if ( $clear_key ) {
			$next['key'] = ''; } elseif ( '' !== $new_key ) {
			$next['key'] = Secrets::seal( $new_key ); }
			update_option( self::OPTION, $next, false );
			return $next;
	}

	/** Safe to send to the admin page: no key, only whether and where one exists. */
	public static function summary() {
		$settings = self::get();
		unset( $settings['key'] );
		$key                   = self::api_key();
		$settings['has_key']   = '' !== $key;
		$settings['key_hint']  = Secrets::hint( $key );
		$settings['key_from']  = self::key_source();
		$settings['providers'] = self::PROVIDERS;
		return $settings;
	}

	/** Running totals shown on the settings page. */
	public static function stats() {
		return array_merge(
			array(
				'requests'      => 0,
				'failures'      => 0,
				'cached'        => 0,
				'input_tokens'  => 0,
				'output_tokens' => 0,
				'last_error'    => '',
				'last_ok'       => '',
			),
			(array) get_option( 'ohmylms_ai_stats', array() )
		);
	}

	/**
	 * Accumulate provider usage without storing prompts or credentials.
	 *
	 * @param array $delta Usage counters and safe error codes.
	 */
	public static function record( array $delta ) {
		$stats = self::stats();
		foreach ( array( 'requests', 'failures', 'cached', 'input_tokens', 'output_tokens' ) as $key ) {
			$stats[ $key ] += (int) ( $delta[ $key ] ?? 0 );
		}
		foreach ( array( 'last_error', 'last_ok' ) as $key ) {
			if ( isset( $delta[ $key ] ) ) {
				$stats[ $key ] = mb_substr( (string) $delta[ $key ], 0, 200 ); }
		}
		update_option( 'ohmylms_ai_stats', $stats, false );
	}
}
