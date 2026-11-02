<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Checkout settings: gateway order, guest checkout, login form, content protection.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Settings;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and writes the checkout options the payment gateways tab edits.
 *
 * Storage is unchanged: `ohmylms_gateway_order` (gateway id => position, read by
 * `Commerce\Gateways\Gateways::init()`), `ohmylms_allow_purchase_without_login` and
 * `ohmylms_enable_login` (read by `Commerce\Checkout`), `ohmylms_content_protection` (read by
 * `FrontendAssets`, mirrored into the Content protection integration switch). The Stripe webhook
 * status is derived and read-only. See docs/api/settings.md#checkout.
 *
 * @since 1.0.0
 */
final class CheckoutSettings {

	const GATEWAY_ORDER = 'ohmylms_gateway_order';
	const GUEST         = 'ohmylms_allow_purchase_without_login';
	const LOGIN         = 'ohmylms_enable_login';
	const PROTECTION    = 'ohmylms_content_protection';
	const INTEGRATIONS  = 'ohmylms_integrations';

	/**
	 * Writable fields and their option (toggles) or `null` (gateway order).
	 */
	const FIELDS = array(
		'gateway_order'      => null,
		'guest_checkout'     => self::GUEST,
		'login_form'         => self::LOGIN,
		'content_protection' => self::PROTECTION,
	);

	/**
	 * Defaults of the toggle options, as their readers use them.
	 */
	const DEFAULTS = array(
		self::GUEST      => 'yes',
		self::LOGIN      => 'yes',
		self::PROTECTION => 'no',
	);

	/**
	 * Registered gateways keyed by id, in registration order.
	 *
	 * @return array<string, object>
	 */
	private static function registered() {
		return PaymentGatewaySettings::gateways();
	}

	/**
	 * Gateway ids in checkout order: stored positions first (ascending), then gateways without one
	 * in registration order. Unknown stored ids are ignored.
	 *
	 * @return string[]
	 */
	public static function gateway_order() {
		$ids    = array_keys( self::registered() );
		$stored = get_option( self::GATEWAY_ORDER, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$ranked = array();
		$rest   = array();
		foreach ( $ids as $index => $id ) {
			if ( isset( $stored[ $id ] ) && is_numeric( $stored[ $id ] ) ) {
				$ranked[] = array( (int) $stored[ $id ], $index, $id );
			} else {
				$rest[] = $id;
			}
		}
		usort(
			$ranked,
			static function ( $a, $b ) {
				return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0];
			}
		);
		return array_merge( array_column( $ranked, 2 ), $rest );
	}

	/**
	 * Whether a stored toggle is on.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private static function flag( $option ) {
		return 'yes' === get_option( $option, self::DEFAULTS[ $option ] );
	}

	/**
	 * Revision of the stored checkout options (16 hex characters).
	 *
	 * @return string
	 */
	public static function revision() {
		$state = array( self::gateway_order() );
		foreach ( self::DEFAULTS as $option => $default ) {
			$state[] = get_option( $option, $default );
		}
		return substr( hash( 'sha256', (string) wp_json_encode( $state ) ), 0, 16 );
	}

	/**
	 * Current settings plus the read-only Stripe status.
	 *
	 * @return array
	 */
	public static function read() {
		$gateways = array();
		foreach ( self::gateway_order() as $id ) {
			$gateway    = self::registered()[ $id ];
			$settings   = get_option( PaymentGatewaySettings::option_name( $id ), array() );
			$gateways[] = array(
				'id'      => $id,
				'title'   => wp_strip_all_tags( (string) ( $gateway->get_settings()['title'] ?? $id ) ),
				'enabled' => is_array( $settings ) && self::is_on( $settings['enabled'] ?? 'no' ),
			);
		}

		return array(
			'gateway_order'      => self::gateway_order(),
			'gateways'           => $gateways,
			'guest_checkout'     => self::flag( self::GUEST ),
			'login_form'         => self::flag( self::LOGIN ),
			'content_protection' => self::flag( self::PROTECTION ),
			'stripe_webhook'     => self::stripe_status(),
			'revision'           => self::revision(),
		);
	}

	/**
	 * Read-only Stripe status. Payments are confirmed on the checkout page and on the return URL
	 * (`GatewayStripeIntents::handle_stripe_return()`); the plugin registers no webhook endpoint, so
	 * there is no endpoint URL or signing secret to show.
	 *
	 * @return array|null `null` when Stripe is not registered.
	 */
	public static function stripe_status() {
		if ( ! isset( self::registered()['stripe'] ) ) {
			return null;
		}
		$settings = get_option( PaymentGatewaySettings::option_name( 'stripe' ), array() );
		$settings = is_array( $settings ) ? $settings : array();
		$mode     = 'yes' === ( $settings['test_mode'] ?? 'no' ) ? 'test' : 'live';
		$keys     = 'test' === $mode ? array( 'sandbox_publishable_key', 'sandbox_secret_key' ) : array( 'publishable_key', 'secret_key' );
		$ready    = true;
		foreach ( $keys as $key ) {
			$ready = $ready && '' !== trim( (string) ( $settings[ $key ] ?? '' ) );
		}

		$last  = null;
		$query = new \WP_Query(
			array(
				'post_type'              => 'ohmylms-order',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One bounded row, indexed meta key.
				'meta_query'             => array(
					array(
						'key'     => '_stripe_payment_intent_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		if ( $query->posts ) {
			$order_id = (int) $query->posts[0];
			$paid     = (string) get_post_meta( $order_id, '_date_paid', true );
			$last     = array(
				'order_id' => $order_id,
				'date'     => $paid ? str_replace( ' ', 'T', $paid ) : str_replace( ' ', 'T', (string) get_post_field( 'post_date', $order_id ) ),
			);
		}

		return array(
			'enabled'              => self::is_on( $settings['enabled'] ?? 'no' ),
			'mode'                 => $mode,
			'keys_configured'      => $ready,
			'webhook_endpoint'     => null,
			'confirmation'         => 'return_url',
			'last_confirmed_order' => $last,
		);
	}

	/**
	 * Whether a stored gateway switch means "on".
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	private static function is_on( $value ) {
		return true === $value || in_array( strtolower( (string) $value ), array( 'yes', 'true', '1', 'on' ), true );
	}

	/**
	 * Validation error.
	 *
	 * @param string $code     Code.
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
	 * Validate a partial update. Everything is checked before anything is written.
	 *
	 * @param mixed $body JSON body.
	 * @return array|WP_Error Option name => stored value.
	 */
	public static function validate( $body ) {
		if ( ! is_array( $body ) || ( $body && array_keys( $body ) === range( 0, count( $body ) - 1 ) ) ) {
			return self::error( 'rest_invalid_param', __( 'Send the settings as an object.', 'ohmylms' ), 400 );
		}
		$revision = $body['revision'] ?? null;
		unset( $body['revision'] );
		$unknown = array_diff( array_keys( $body ), array_keys( self::FIELDS ) );
		if ( $unknown ) {
			$messages = array();
			foreach ( $unknown as $key ) {
				$messages[ (string) $key ] = in_array( $key, array( 'stripe_webhook', 'gateways' ), true ) ? __( 'This field is read-only.', 'ohmylms' ) : __( 'Unknown field.', 'ohmylms' );
			}
			return self::error( 'ohmylms_settings_unknown_field', __( 'The request contains fields that cannot be changed.', 'ohmylms' ), 400, $messages );
		}
		if ( null !== $revision && ( ! is_string( $revision ) || ! hash_equals( self::revision(), $revision ) ) ) {
			return self::error( 'ohmylms_settings_stale', __( 'The checkout settings changed since they were loaded. Reload them and try again.', 'ohmylms' ), 409 );
		}

		$plan  = array();
		$types = array();
		$rules = array();
		foreach ( self::FIELDS as $field => $option ) {
			if ( ! array_key_exists( $field, $body ) ) {
				continue;
			}
			$value = $body[ $field ];
			if ( null !== $option ) {
				if ( ! is_bool( $value ) ) {
					$types[ $field ] = __( 'Use true or false.', 'ohmylms' );
					continue;
				}
				$plan[ $option ] = $value ? 'yes' : 'no';
				continue;
			}
			if ( ! is_array( $value ) || array_values( $value ) !== $value || count( array_filter( $value, 'is_string' ) ) !== count( $value ) ) {
				$types[ $field ] = __( 'Send the gateway ids as a list of strings.', 'ohmylms' );
				continue;
			}
			$sent = $value;
			$ids  = array_keys( self::registered() );
			sort( $sent );
			sort( $ids );
			if ( $sent !== $ids ) {
				$rules[ $field ] = __( 'List every registered gateway exactly once.', 'ohmylms' );
				continue;
			}
			$plan[ self::GATEWAY_ORDER ] = array_flip( $value );
		}
		if ( $types ) {
			return self::error( 'rest_invalid_param', __( 'Some checkout settings have the wrong type.', 'ohmylms' ), 400, $types );
		}
		if ( $rules ) {
			return self::error( 'ohmylms_settings_invalid_value', __( 'Some checkout settings are not valid.', 'ohmylms' ), 422, $rules );
		}

		return $plan;
	}

	/**
	 * Write a validated plan. A failed write restores every option written before it.
	 *
	 * @param array $plan Option name => value.
	 * @return string[]|WP_Error Changed field names.
	 */
	public static function write( array $plan ) {
		$before  = array();
		$changed = array();
		foreach ( $plan as $option => $value ) {
			$previous = get_option( $option, self::DEFAULTS[ $option ] ?? array() );
			if ( $previous === $value ) {
				continue;
			}
			$before[ $option ] = $previous;
			update_option( $option, $value );
			if ( get_option( $option ) !== $value ) {
				foreach ( $before as $restore => $old ) {
					update_option( $restore, $old );
				}
				return self::error( 'ohmylms_settings_save_failed', __( 'The checkout settings could not be saved.', 'ohmylms' ), 500 );
			}
			$changed[] = (string) array_search( self::GATEWAY_ORDER === $option ? null : $option, self::FIELDS, true );
		}
		if ( isset( $before[ self::PROTECTION ] ) ) {
			self::mirror_protection( 'yes' === $plan[ self::PROTECTION ] );
		}
		if ( $changed ) {
			/**
			 * Fires after checkout settings were saved.
			 *
			 * @param string[] $changed Changed field names.
			 */
			do_action( 'ohmylms_checkout_settings_updated', $changed );
		}

		return $changed;
	}

	/**
	 * Keep the Content protection integration switch in step with the option, as the Integrations
	 * screen does the other way round.
	 *
	 * @param bool $enabled Whether protection is on.
	 * @return void
	 */
	private static function mirror_protection( $enabled ) {
		$integrations = get_option( self::INTEGRATIONS, array() );
		$integrations = is_array( $integrations ) ? $integrations : array();
		$entry        = isset( $integrations['content_protection'] ) && is_array( $integrations['content_protection'] ) ? $integrations['content_protection'] : array();

		$entry['is_enable']                 = $enabled ? 1 : 0;
		$integrations['content_protection'] = $entry;
		update_option( self::INTEGRATIONS, $integrations );
	}
}
