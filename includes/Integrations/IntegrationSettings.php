<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Provider credential settings for the Add-ons screen.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Integrations;

use OhMyLMS\Services\GoogleAuthService;
use OhMyLMS\Services\MicrosoftAuthService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes, reads and writes the credentials of the integrations that need them.
 *
 * Secrets are write-only: {@see self::describe()} never returns a stored secret, only whether one is
 * configured and a short hint. Storage locations are unchanged (Zoom keeps its per-user credentials),
 * so the integrations that consume them keep working. See docs/api/integrations.md.
 *
 * @since 1.0.0
 */
class IntegrationSettings {

	/**
	 * Option that stores the Zoom webhook secret token (site wide).
	 */
	const ZOOM_WEBHOOK_OPTION = 'ohmylms_zoom_webhook_secret_token';

	/**
	 * User meta that stores the Zoom OAuth credentials of one administrator.
	 */
	const ZOOM_CREDENTIALS_META = 'ohmylms_zoom_api_credentials';

	/**
	 * Field definitions per integration id.
	 *
	 * @return array<string, array{fields: array, readonly: array}>
	 */
	private static function definitions() {
		return array(
			'zoom'             => array(
				'fields'   => array(
					self::text_field( 'account_id', __( 'Account ID', 'ohmylms' ), __( 'From your Zoom Server-to-Server OAuth app.', 'ohmylms' ), true, 128 ),
					self::text_field( 'client_id', __( 'Client ID', 'ohmylms' ), '', true, 128 ),
					self::secret_field( 'client_secret', __( 'Client secret', 'ohmylms' ), '', true, 256 ),
					self::secret_field( 'webhook_secret_token', __( 'Webhook secret token', 'ohmylms' ), __( 'From the app’s Event Subscriptions page. Optional until you subscribe to events.', 'ohmylms' ), false, 256 ),
				),
				'readonly' => array(
					array(
						'name'  => 'webhook_url',
						'label' => __( 'Event notification endpoint URL', 'ohmylms' ),
						'help'  => __( 'Paste this into the Zoom app’s Event Subscriptions page.', 'ohmylms' ),
						'value' => rest_url( 'ohmylms/v1/zoom/webhook' ),
					),
				),
			),
			'google_signin'    => array(
				'fields'   => array(
					self::text_field( 'client_id', __( 'Client ID', 'ohmylms' ), __( 'From a Google Cloud OAuth web application.', 'ohmylms' ), false, 255 ),
					self::secret_field( 'client_secret', __( 'Client secret', 'ohmylms' ), '', false, 255 ),
				),
				'readonly' => array(
					array(
						'name'  => 'redirect_uri',
						'label' => __( 'Authorized redirect URI', 'ohmylms' ),
						'help'  => __( 'Add this exact URI to the Google OAuth client.', 'ohmylms' ),
						'value' => GoogleAuthService::get_redirect_uri(),
					),
				),
			),
			'microsoft_signin' => array(
				'fields'   => array(
					self::text_field( 'client_id', __( 'Application (client) ID', 'ohmylms' ), __( 'From your Microsoft Entra app registration.', 'ohmylms' ), false, 255 ),
					self::text_field( 'tenant', __( 'Tenant', 'ohmylms' ), __( 'A tenant ID or domain, or common, organizations or consumers.', 'ohmylms' ), false, 253, MicrosoftAuthService::DEFAULT_TENANT ),
					self::secret_field( 'client_secret', __( 'Client secret value', 'ohmylms' ), '', false, 255 ),
				),
				'readonly' => array(
					array(
						'name'  => 'redirect_uri',
						'label' => __( 'Redirect URI', 'ohmylms' ),
						'help'  => __( 'Add this exact URI as a Web redirect URI in the app registration.', 'ohmylms' ),
						'value' => MicrosoftAuthService::get_redirect_uri(),
					),
				),
			),
		);
	}

	/**
	 * Build a text field definition.
	 *
	 * @param string $name     Field name.
	 * @param string $label    Label.
	 * @param string $help     Help text.
	 * @param bool   $required Whether a value is needed.
	 * @param int    $max      Maximum length.
	 * @param string $fallback Value used when nothing is stored.
	 * @return array
	 */
	private static function text_field( $name, $label, $help, $required, $max, $fallback = '' ) {
		return array(
			'name'       => $name,
			'label'      => $label,
			'help'       => $help,
			'kind'       => 'text',
			'required'   => $required,
			'max_length' => $max,
			'fallback'   => $fallback,
		);
	}

	/**
	 * Build a write-only secret field definition.
	 *
	 * @param string $name     Field name.
	 * @param string $label    Label.
	 * @param string $help     Help text.
	 * @param bool   $required Whether a value is needed.
	 * @param int    $max      Maximum length.
	 * @return array
	 */
	private static function secret_field( $name, $label, $help, $required, $max ) {
		return array(
			'name'       => $name,
			'label'      => $label,
			'help'       => $help,
			'kind'       => 'secret',
			'required'   => $required,
			'max_length' => $max,
			'fallback'   => '',
		);
	}

	/**
	 * Whether the integration has credentials managed through the REST API.
	 *
	 * @param string $id Integration id.
	 * @return bool
	 */
	public static function supports( $id ) {
		return array_key_exists( $id, self::definitions() );
	}

	/**
	 * Ids of the integrations with managed credentials.
	 *
	 * @return string[]
	 */
	public static function supported_ids() {
		return array_keys( self::definitions() );
	}

	/**
	 * Read the stored values (including secrets) of an integration. Never send these to a client.
	 *
	 * @param string $id Integration id.
	 * @return array<string, string>
	 */
	private static function read_stored( $id ) {
		if ( 'zoom' === $id ) {
			$stored = get_user_meta( get_current_user_id(), self::ZOOM_CREDENTIALS_META, true );
			$stored = is_array( $stored ) ? $stored : array();
			$token  = get_option( self::ZOOM_WEBHOOK_OPTION, '' );

			return array(
				'account_id'           => isset( $stored['account_id'] ) ? (string) $stored['account_id'] : '',
				'client_id'            => isset( $stored['client_id'] ) ? (string) $stored['client_id'] : '',
				'client_secret'        => isset( $stored['client_secret'] ) ? (string) $stored['client_secret'] : '',
				'webhook_secret_token' => is_string( $token ) ? $token : '',
			);
		}

		$key    = 'google_signin' === $id ? GoogleAuthService::OPTION_KEY : MicrosoftAuthService::OPTION_KEY;
		$stored = get_option( $key, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array();
		foreach ( self::definitions()[ $id ]['fields'] as $field ) {
			$values[ $field['name'] ] = isset( $stored[ $field['name'] ] ) && is_string( $stored[ $field['name'] ] ) ? $stored[ $field['name'] ] : '';
		}

		return $values;
	}

	/**
	 * Persist the complete value set of an integration.
	 *
	 * @param string                $id     Integration id.
	 * @param array<string, string> $values Values keyed by field name.
	 * @return void
	 */
	private static function write_stored( $id, array $values ) {
		if ( 'zoom' === $id ) {
			update_user_meta(
				get_current_user_id(),
				self::ZOOM_CREDENTIALS_META,
				array(
					'account_id'    => $values['account_id'],
					'client_id'     => $values['client_id'],
					'client_secret' => $values['client_secret'],
				)
			);
			update_option( self::ZOOM_WEBHOOK_OPTION, $values['webhook_secret_token'] );
			return;
		}

		$key = 'google_signin' === $id ? GoogleAuthService::OPTION_KEY : MicrosoftAuthService::OPTION_KEY;
		update_option( $key, $values );
	}

	/**
	 * Short, non-reversible description of a stored secret.
	 *
	 * @param string $secret Stored secret.
	 * @return string
	 */
	private static function hint( $secret ) {
		return strlen( $secret ) >= 12 ? '****' . substr( $secret, -4 ) : '****';
	}

	/**
	 * Describe an integration's settings for a client. Secrets are reduced to `configured` and `hint`.
	 *
	 * @param string $id Integration id (must satisfy {@see self::supports()}).
	 * @return array
	 */
	public static function describe( $id ) {
		$definition = self::definitions()[ $id ];
		$stored     = self::read_stored( $id );
		$fields     = array();
		$configured = true;

		foreach ( $definition['fields'] as $field ) {
			$current = $stored[ $field['name'] ];
			$row     = array(
				'name'       => $field['name'],
				'label'      => $field['label'],
				'help'       => $field['help'],
				'kind'       => $field['kind'],
				'required'   => $field['required'],
				'max_length' => $field['max_length'],
			);
			if ( 'secret' === $field['kind'] ) {
				$row['configured'] = '' !== $current;
				$row['hint']       = '' !== $current ? self::hint( $current ) : '';
			} else {
				$row['value'] = '' !== $current ? $current : $field['fallback'];
			}
			if ( $field['required'] && '' === $current ) {
				$configured = false;
			}
			$fields[] = $row;
		}

		return array(
			'integration' => $id,
			'configured'  => $configured,
			'fields'      => $fields,
			'readonly'    => $definition['readonly'],
		);
	}

	/**
	 * Validate and save settings. Nothing is written unless every submitted field is valid.
	 *
	 * Omitted fields are kept. A secret is replaced by a non-empty string, or cleared with `null` when
	 * it is optional. Text fields are trimmed and sanitised.
	 *
	 * @param string $id    Integration id (must satisfy {@see self::supports()}).
	 * @param array  $input Submitted fields.
	 * @return array|WP_Error The names of the changed fields, or an error (400 unknown field, 422 invalid values).
	 */
	public static function update( $id, array $input ) {
		$fields = array();
		foreach ( self::definitions()[ $id ]['fields'] as $field ) {
			$fields[ $field['name'] ] = $field;
		}

		$unknown = array_diff( array_map( 'strval', array_keys( $input ) ), array_keys( $fields ) );
		if ( $unknown ) {
			return new WP_Error(
				'ohmylms_integration_unknown_setting',
				__( 'Unknown setting.', 'ohmylms' ),
				array(
					'status' => 400,
					'params' => array_values( $unknown ),
				)
			);
		}

		$stored   = self::read_stored( $id );
		$next     = $stored;
		$problems = array();

		foreach ( $input as $name => $raw ) {
			$field = $fields[ $name ];
			$error = self::apply_value( $field, $raw, $next );
			if ( null !== $error ) {
				$problems[ $name ] = $error;
			}
		}

		foreach ( $fields as $name => $field ) {
			if ( $field['required'] && '' === $next[ $name ] && ! isset( $problems[ $name ] ) ) {
				$problems[ $name ] = __( 'This field is required.', 'ohmylms' );
			}
		}

		if ( $problems ) {
			return new WP_Error(
				'ohmylms_integration_invalid_settings',
				__( 'Some settings are not valid.', 'ohmylms' ),
				array(
					'status' => 422,
					'params' => $problems,
				)
			);
		}

		$changed = array();
		foreach ( $next as $name => $value ) {
			if ( $value !== $stored[ $name ] ) {
				$changed[] = $name;
			}
		}
		if ( $changed ) {
			self::write_stored( $id, $next );
		}

		return $changed;
	}

	/**
	 * Validate one submitted value and store it in `$next`.
	 *
	 * @param array $field Field definition.
	 * @param mixed $raw   Submitted value.
	 * @param array $next  Values being built (by reference).
	 * @return string|null Error message, or null when the value is valid.
	 */
	private static function apply_value( array $field, $raw, array &$next ) {
		$name = $field['name'];

		if ( null === $raw ) {
			if ( $field['required'] ) {
				return __( 'This field is required.', 'ohmylms' );
			}
			$next[ $name ] = '';
			return null;
		}
		if ( ! is_string( $raw ) ) {
			return __( 'Enter text.', 'ohmylms' );
		}

		if ( 'secret' === $field['kind'] ) {
			// Secrets are stored byte for byte, so anything sanitising would change is rejected instead.
			if ( '' === $raw ) {
				return __( 'Enter a value, or send null to clear an optional secret.', 'ohmylms' );
			}
			if ( strlen( $raw ) > $field['max_length'] ) {
				/* translators: %d: maximum number of characters. */
				return sprintf( __( 'Use at most %d characters.', 'ohmylms' ), $field['max_length'] );
			}
			if ( ! preg_match( '/^[\x21-\x7E]+$/', $raw ) ) {
				return __( 'Use printable characters without spaces.', 'ohmylms' );
			}
			$next[ $name ] = $raw;
			return null;
		}

		$value = sanitize_text_field( $raw );
		if ( mb_strlen( $value ) > $field['max_length'] ) {
			/* translators: %d: maximum number of characters. */
			return sprintf( __( 'Use at most %d characters.', 'ohmylms' ), $field['max_length'] );
		}
		if ( 'tenant' === $name && '' !== $value && MicrosoftAuthService::sanitize_tenant( $value ) !== $value ) {
			return __( 'Use a tenant ID, a domain name, or common, organizations or consumers.', 'ohmylms' );
		}
		if ( 'tenant' === $name && '' === $value ) {
			$value = $field['fallback'];
		}
		$next[ $name ] = $value;

		return null;
	}
}
