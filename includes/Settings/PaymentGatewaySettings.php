<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Payment gateway settings with write-only credentials.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Settings;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and writes the `ohmylms_{gateway}_settings` options of the registered gateways.
 *
 * Field definitions come from each gateway's own `get_settings()` (`settings_fields`), so the gateway
 * registry (`ecommerce()->gateways()`) stays the single source. Credential fields are write-only:
 * responses carry `has_value` only, an empty submitted secret keeps the stored one and `clear_secrets`
 * removes it. Unknown members of the stored option (for example PayPal's `webhook_id`) are preserved.
 *
 * @since 1.0.0
 */
final class PaymentGatewaySettings {

	/**
	 * Field names treated as credentials when the definition does not say (`'secret' => bool`).
	 */
	const SECRET_PATTERN = '/(secret|transaction_key|signature_key|api_login_id|password|private|token)/';

	/**
	 * Switch fields that select test (sandbox) mode.
	 */
	const MODE_FIELDS = array( 'test_mode', 'testmode' );

	/**
	 * Maximum lengths per field type.
	 */
	const MAX_LENGTH = array(
		'text'     => 200,
		'textarea' => 2000,
		'secret'   => 500,
	);

	/**
	 * Top-level members of a PATCH body.
	 */
	const BODY_KEYS = array( 'enabled', 'mode', 'values', 'secrets', 'clear_secrets' );

	/**
	 * Registered gateway objects keyed by id.
	 *
	 * @return array<string, object>
	 */
	public static function gateways() {
		if ( ! function_exists( '\OhMyLMS\Commerce\ecommerce' ) ) {
			return array();
		}
		$gateways = array();
		foreach ( \OhMyLMS\Commerce\ecommerce()->gateways()->get_payment_gateways() as $id => $gateway ) {
			if ( is_string( $id ) && preg_match( '/^[a-z0-9_-]+$/', $id ) && is_object( $gateway ) && method_exists( $gateway, 'get_settings' ) ) {
				$gateways[ $id ] = $gateway;
			}
		}

		return $gateways;
	}

	/**
	 * Option that stores a gateway's settings.
	 *
	 * @param string $id Gateway id.
	 * @return string
	 */
	public static function option_name( $id ) {
		return 'ohmylms_' . $id . '_settings';
	}

	/**
	 * Whether a stored switch value means "on".
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	private static function is_on( $value ) {
		return true === $value || in_array( strtolower( (string) $value ), array( 'yes', 'true', '1', 'on' ), true );
	}

	/**
	 * Plain text of a gateway label or help string (some carry links).
	 *
	 * @param mixed $value Raw string.
	 * @return string
	 */
	private static function plain( $value ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Normalised definition of one gateway: fields by name, mode field and labels.
	 *
	 * @param object $gateway Gateway object.
	 * @return array{id:string,title:string,description:string,subscription_support:bool,mode_field:string|null,mode_default:bool,fields:array<string,array>}
	 */
	public static function definition( $gateway ) {
		$settings = (array) $gateway->get_settings();
		$fields   = array();
		$mode     = null;
		$default  = false;
		foreach ( isset( $settings['settings_fields'] ) && is_array( $settings['settings_fields'] ) ? $settings['settings_fields'] : array() as $field ) {
			$name = is_array( $field ) && isset( $field['option_name'] ) ? (string) $field['option_name'] : '';
			$type = is_array( $field ) && isset( $field['input_type'] ) ? (string) $field['input_type'] : '';
			if ( ! preg_match( '/^[a-z0-9_]+$/', $name ) || 'enabled' === $name ) {
				continue;
			}
			if ( 'switch' === $type && in_array( $name, self::MODE_FIELDS, true ) ) {
				$mode    = $name;
				$default = self::is_on( isset( $field['default_value'] ) ? $field['default_value'] : 'no' );
				continue;
			}
			if ( in_array( $type, array( 'switch', 'checkbox' ), true ) ) {
				$kind = 'toggle';
			} elseif ( 'textarea' === $type ) {
				$kind = 'textarea';
			} elseif ( 'text' === $type ) {
				$secret = isset( $field['secret'] ) ? (bool) $field['secret'] : (bool) preg_match( self::SECRET_PATTERN, $name );
				$kind   = $secret ? 'secret' : 'text';
			} else {
				continue;
			}
			$logic           = isset( $field['conditional_logic'] ) && is_array( $field['conditional_logic'] ) ? $field['conditional_logic'] : array();
			$fields[ $name ] = array(
				'name'       => $name,
				'label'      => self::plain( isset( $field['title'] ) ? $field['title'] : $name ),
				'help'       => self::plain( isset( $field['short_description'] ) ? $field['short_description'] : '' ),
				'type'       => $kind,
				'mode'       => null,
				'depends_on' => isset( $logic['depends_on'] ) ? (string) $logic['depends_on'] : '',
				'show_when'  => isset( $logic['show_when'] ) ? (string) $logic['show_when'] : '',
				'max_length' => 'toggle' === $kind ? null : self::MAX_LENGTH[ $kind ],
				'required'   => 'title' === $name,
				'default'    => 'toggle' === $kind
					? self::is_on( isset( $field['default_value'] ) ? $field['default_value'] : 'no' )
					: ( 'secret' === $kind ? '' : (string) ( isset( $field['default_value'] ) ? $field['default_value'] : '' ) ),
			);
		}
		foreach ( $fields as $name => $field ) {
			if ( null !== $mode && $field['depends_on'] === $mode && in_array( $field['show_when'], array( 'yes', 'no' ), true ) ) {
				$fields[ $name ]['mode'] = 'yes' === $field['show_when'] ? 'test' : 'live';
			}
			unset( $fields[ $name ]['depends_on'], $fields[ $name ]['show_when'] );
		}

		return array(
			'id'                   => (string) $gateway->id,
			'title'                => self::plain( isset( $settings['title'] ) ? $settings['title'] : $gateway->id ),
			'description'          => self::plain( isset( $settings['description'] ) ? $settings['description'] : '' ),
			'subscription_support' => ! empty( $settings['subscription_support'] ),
			'mode_field'           => $mode,
			'mode_default'         => null === $mode ? false : ( isset( $gateway->testmode ) ? self::is_on( $gateway->testmode ) : $default ),
			'fields'               => $fields,
		);
	}

	/**
	 * Stored settings of a gateway.
	 *
	 * @param string $id Gateway id.
	 * @return array
	 */
	private static function stored( $id ) {
		$stored = get_option( self::option_name( $id ), array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Whether a stored credential is present.
	 *
	 * @param array  $stored Stored settings.
	 * @param string $name   Field name.
	 * @return bool
	 */
	private static function has_secret( array $stored, $name ) {
		return isset( $stored[ $name ] ) && is_string( $stored[ $name ] ) && '' !== trim( $stored[ $name ] );
	}

	/**
	 * REST shape of one gateway. Never contains a credential value.
	 *
	 * @param array $definition Output of {@see self::definition()}.
	 * @return array
	 */
	public static function read( array $definition ) {
		$stored  = self::stored( $definition['id'] );
		$values  = array();
		$secrets = array();
		$fields  = array();
		foreach ( $definition['fields'] as $name => $field ) {
			$fields[] = $field;
			if ( 'secret' === $field['type'] ) {
				$secrets[ $name ] = array( 'has_value' => self::has_secret( $stored, $name ) );
			} elseif ( 'toggle' === $field['type'] ) {
				$values[ $name ] = array_key_exists( $name, $stored ) ? self::is_on( $stored[ $name ] ) : $field['default'];
			} else {
				$values[ $name ] = isset( $stored[ $name ] ) && is_string( $stored[ $name ] ) ? $stored[ $name ] : $field['default'];
			}
		}
		$mode = null;
		if ( null !== $definition['mode_field'] ) {
			$test = array_key_exists( $definition['mode_field'], $stored ) ? self::is_on( $stored[ $definition['mode_field'] ] ) : $definition['mode_default'];
			$mode = $test ? 'test' : 'live';
		}

		return array(
			'id'                   => $definition['id'],
			'title'                => $definition['title'],
			'description'          => $definition['description'],
			'subscription_support' => $definition['subscription_support'],
			'enabled'              => self::is_on( isset( $stored['enabled'] ) ? $stored['enabled'] : 'no' ),
			'mode'                 => $mode,
			'fields'               => $fields,
			'values'               => (object) $values,
			'secrets'              => (object) $secrets,
		);
	}

	/**
	 * Error helper in the settings API shape.
	 *
	 * @param string $code     Error code.
	 * @param string $message  Message.
	 * @param int    $status   HTTP status.
	 * @param array  $messages Field => message.
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status, array $messages = array() ) {
		$data = array( 'status' => $status );
		if ( $messages ) {
			$data['params'] = $messages;
		}

		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Whether a value is a JSON object (an associative array or an empty one).
	 *
	 * @param mixed $value Decoded JSON.
	 * @return bool
	 */
	private static function is_object_like( $value ) {
		return is_array( $value ) && ( ! $value || array_values( $value ) !== $value );
	}

	/**
	 * Validate a partial update. Nothing is written here.
	 *
	 * @param array $definition Output of {@see self::definition()}.
	 * @param mixed $body       Decoded JSON body.
	 * @return array|WP_Error Plan: `enabled`, `mode`, `values`, `secrets`, `clear` (only what was sent).
	 */
	public static function validate( array $definition, $body ) {
		if ( ! self::is_object_like( $body ) ) {
			return self::error( 'ohmylms_settings_invalid_body', __( 'Send a JSON object of settings.', 'ohmylms' ), 400 );
		}

		$unknown = array();
		foreach ( array_keys( $body ) as $key ) {
			if ( ! in_array( $key, self::BODY_KEYS, true ) || ( 'mode' === $key && null === $definition['mode_field'] ) ) {
				$unknown[ (string) $key ] = __( 'Unknown setting.', 'ohmylms' );
			}
		}
		foreach ( array( 'values', 'secrets' ) as $member ) {
			if ( isset( $body[ $member ] ) && self::is_object_like( $body[ $member ] ) ) {
				foreach ( array_keys( $body[ $member ] ) as $name ) {
					$field = isset( $definition['fields'][ $name ] ) ? $definition['fields'][ $name ] : null;
					if ( ! $field || ( 'secrets' === $member ) !== ( 'secret' === $field['type'] ) ) {
						$unknown[ $member . '.' . $name ] = 'secrets' === $member || ! $field ? __( 'Unknown setting.', 'ohmylms' ) : __( 'Send credentials under "secrets".', 'ohmylms' );
					}
				}
			}
		}
		if ( isset( $body['clear_secrets'] ) && is_array( $body['clear_secrets'] ) ) {
			foreach ( $body['clear_secrets'] as $name ) {
				if ( ! is_string( $name ) || ! isset( $definition['fields'][ $name ] ) || 'secret' !== $definition['fields'][ $name ]['type'] ) {
					$unknown[ 'clear_secrets.' . ( is_string( $name ) ? $name : '' ) ] = __( 'Unknown credential.', 'ohmylms' );
				}
			}
		}
		if ( $unknown ) {
			return self::error( 'ohmylms_settings_unknown_field', __( 'Unknown setting(s) in the request.', 'ohmylms' ), 400, $unknown );
		}

		$invalid = array();
		$plan    = array();
		if ( array_key_exists( 'enabled', $body ) ) {
			if ( ! is_bool( $body['enabled'] ) ) {
				$invalid['enabled'] = __( 'Enabled must be true or false.', 'ohmylms' );
			}
			$plan['enabled'] = (bool) $body['enabled'];
		}
		if ( array_key_exists( 'mode', $body ) ) {
			if ( ! in_array( $body['mode'], array( 'test', 'live' ), true ) ) {
				$invalid['mode'] = __( 'Mode must be test or live.', 'ohmylms' );
			}
			$plan['mode'] = $body['mode'];
		}
		foreach ( array( 'values', 'secrets' ) as $member ) {
			if ( array_key_exists( $member, $body ) && ! self::is_object_like( $body[ $member ] ) ) {
				$invalid[ $member ] = __( 'Send an object of fields.', 'ohmylms' );
			}
		}
		if ( array_key_exists( 'clear_secrets', $body ) && ( ! is_array( $body['clear_secrets'] ) || ( $body['clear_secrets'] && array_values( $body['clear_secrets'] ) !== $body['clear_secrets'] ) ) ) {
			$invalid['clear_secrets'] = __( 'Send a list of credential names.', 'ohmylms' );
		}
		if ( $invalid ) {
			return self::error( 'rest_invalid_param', __( 'Invalid parameter(s).', 'ohmylms' ), 400, $invalid );
		}

		$problems       = array();
		$plan['values'] = array();
		foreach ( isset( $body['values'] ) ? $body['values'] : array() as $name => $value ) {
			$field = $definition['fields'][ $name ];
			if ( 'toggle' === $field['type'] ) {
				if ( ! is_bool( $value ) ) {
					$invalid[ 'values.' . $name ] = __( 'Choose on or off.', 'ohmylms' );
					continue;
				}
				$plan['values'][ $name ] = $value;
				continue;
			}
			if ( ! is_string( $value ) ) {
				$invalid[ 'values.' . $name ] = __( 'Enter text.', 'ohmylms' );
				continue;
			}
			if ( mb_strlen( $value ) > $field['max_length'] ) {
				/* translators: %d: maximum number of characters. */
				$invalid[ 'values.' . $name ] = sprintf( __( 'Use at most %d characters.', 'ohmylms' ), $field['max_length'] );
				continue;
			}
			$clean = 'textarea' === $field['type'] ? wp_kses_post( trim( $value ) ) : sanitize_text_field( $value );
			if ( $field['required'] && '' === $clean ) {
				$problems[ 'values.' . $name ] = __( 'This field cannot be empty.', 'ohmylms' );
			} elseif ( '' !== $clean && preg_match( '/_email$/', $name ) && ! is_email( $clean ) ) {
				$problems[ 'values.' . $name ] = __( 'Enter a valid email address.', 'ohmylms' );
			}
			$plan['values'][ $name ] = $clean;
		}

		$plan['secrets'] = array();
		foreach ( isset( $body['secrets'] ) ? $body['secrets'] : array() as $name => $value ) {
			if ( ! is_string( $value ) ) {
				$invalid[ 'secrets.' . $name ] = __( 'Enter text.', 'ohmylms' );
				continue;
			}
			if ( '' === $value ) {
				continue;
			}
			if ( strlen( $value ) > self::MAX_LENGTH['secret'] || ! preg_match( '/^[\x21-\x7E]+$/', $value ) ) {
				/* translators: %d: maximum number of characters. */
				$invalid[ 'secrets.' . $name ] = sprintf( __( 'Use up to %d printable characters without spaces.', 'ohmylms' ), self::MAX_LENGTH['secret'] );
				continue;
			}
			$plan['secrets'][ $name ] = $value;
		}

		$plan['clear'] = array();
		foreach ( isset( $body['clear_secrets'] ) ? $body['clear_secrets'] : array() as $name ) {
			if ( isset( $plan['secrets'][ $name ] ) ) {
				$problems[ 'secrets.' . $name ] = __( 'Either enter a new value or remove the saved one, not both.', 'ohmylms' );
			}
			$plan['clear'][] = $name;
		}
		$plan['clear'] = array_values( array_unique( $plan['clear'] ) );

		if ( $invalid ) {
			return self::error( 'rest_invalid_param', __( 'Invalid parameter(s).', 'ohmylms' ), 400, $invalid );
		}
		if ( $problems ) {
			return self::error( 'ohmylms_settings_invalid_value', __( 'Some settings are not valid.', 'ohmylms' ), 422, $problems );
		}

		return $plan;
	}

	/**
	 * Store a validated plan. All fields go in one option write; the write is verified.
	 *
	 * Fires `ohmylms_payment_gateway_settings_updated( $gateway_id, $changed_names )` (names only).
	 *
	 * @param array $definition Output of {@see self::definition()}.
	 * @param array $plan       Output of {@see self::validate()}.
	 * @return string[]|WP_Error Changed names (`enabled`, `mode`, field names).
	 */
	public static function write( array $definition, array $plan ) {
		$id      = $definition['id'];
		$option  = self::option_name( $id );
		$before  = get_option( $option, null );
		$stored  = is_array( $before ) ? $before : array();
		$next    = $stored;
		$changed = array();
		$current = self::read( $definition );

		if ( isset( $plan['enabled'] ) && $plan['enabled'] !== $current['enabled'] ) {
			$next['enabled'] = $plan['enabled'] ? 'yes' : 'no';
			$changed[]       = 'enabled';
		}
		if ( isset( $plan['mode'] ) && $plan['mode'] !== $current['mode'] ) {
			$next[ $definition['mode_field'] ] = 'test' === $plan['mode'] ? 'yes' : 'no';
			$changed[]                         = 'mode';
		}
		$values = (array) $current['values'];
		foreach ( $plan['values'] as $name => $value ) {
			if ( $value !== $values[ $name ] ) {
				$next[ $name ] = 'toggle' === $definition['fields'][ $name ]['type'] ? ( $value ? 'yes' : 'no' ) : $value;
				$changed[]     = $name;
			}
		}
		foreach ( $plan['secrets'] as $name => $value ) {
			if ( ! isset( $stored[ $name ] ) || $stored[ $name ] !== $value ) {
				$next[ $name ] = $value;
				$changed[]     = $name;
			}
		}
		foreach ( $plan['clear'] as $name ) {
			if ( self::has_secret( $stored, $name ) ) {
				$next[ $name ] = '';
				$changed[]     = $name;
			}
		}
		if ( ! $changed ) {
			return array();
		}

		update_option( $option, $next );
		if ( get_option( $option, null ) !== $next ) {
			if ( null === $before ) {
				delete_option( $option );
			} else {
				update_option( $option, $before );
			}
			return self::error( 'ohmylms_settings_save_failed', __( 'The settings could not be saved.', 'ohmylms' ), 500 );
		}

		/**
		 * Fires after a payment gateway's settings changed through the settings API.
		 *
		 * @param string   $id      Gateway id.
		 * @param string[] $changed Changed names (never values).
		 */
		do_action( 'ohmylms_payment_gateway_settings_updated', $id, $changed );

		return $changed;
	}
}
