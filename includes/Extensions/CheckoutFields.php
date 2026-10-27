<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Additional checkout input; never changes prices or payment authorization.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/** Additional checkout input; never changes prices or payment authorization. */
final class CheckoutFields {
	/**
	 * Fields.
	 *
	 * @var mixed
	 */
	private static $fields = array();
	/**
	 * Pending.
	 *
	 * @var mixed
	 */
	private static $pending = array();
	/**
	 * Register for Checkout fields.
	 *
	 * @param mixed $id Id.
	 * @param array $field Field.
	 * @throws \InvalidArgumentException When the operation cannot be completed.
	 */
	public static function register( $id, array $field ) {
		if ( ! preg_match( '/^[a-z][a-z0-9_-]*$/D', $id ) || isset( self::$fields[ $id ] ) || empty( $field['label'] ) || ! in_array( $field['type'] ?? 'text', array( 'text', 'textarea', 'email', 'number', 'select' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid or duplicate checkout extension field.' );
		}
		if ( empty( $field['schema'] ) || ! in_array( $field['schema']['type'] ?? '', array( 'string', 'number', 'integer' ), true ) ) {
			throw new \InvalidArgumentException( 'Checkout fields require a scalar JSON schema.' );
		}
		self::$fields[ $id ] = $field;
	}
	/**
	 * Init for Checkout fields.
	 */
	public static function init() {
		add_filter(
			'ohmylms_checkout_fields',
			static function ( $fields ) {
				foreach ( self::$fields as $id => $field ) {
					unset( $field['schema'] );
					$fields['billing'][ 'ohmylms_extension_' . $id ] = $field;
				}
				return $fields;
			}
		);
		add_action( 'ohmylms_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		// Orders have no ID at create_order; persist metadata only after save().
		add_action(
			'ohmylms_checkout_create_order',
			static function ( $order, $data ) {
				self::$pending[ spl_object_hash( $order ) ] = $data;
			},
			10,
			2
		);
		add_action(
			'ohmylms_checkout_order_created',
			static function ( $order ) {
				$key = spl_object_hash( $order );
				if ( isset( self::$pending[ $key ] ) ) {
					$data = self::$pending[ $key ];
					unset( self::$pending[ $key ] );
					self::save( $order, $data );
				}
			}
		);
	}
	/**
	 * Validate for Checkout fields.
	 *
	 * @param mixed $data Input data.
	 * @param mixed $errors Errors.
	 */
	public static function validate( $data, $errors ) {
		foreach ( self::$fields as $id => $field ) {
			$value = $data[ 'ohmylms_extension_' . $id ] ?? '';
			if ( '' === $value && empty( $field['required'] ) ) {
				continue; }
			$valid = rest_validate_value_from_schema( $value, $field['schema'], $id );
			if ( ( '' === $value && ! empty( $field['required'] ) ) || is_wp_error( $valid ) ) {
				$errors->add( 'ohmylms_extension_' . $id, sprintf( /* translators: %s: text value. */
				__( 'Please check %s.', 'ohmylms' ), $field['label'] ) );
			}
		}
	}
	/**
	 * Save for Checkout fields.
	 *
	 * @param mixed $order Order.
	 * @param mixed $data Input data.
	 */
	public static function save( $order, $data ) {
		$values = array();
		foreach ( self::$fields as $id => $field ) {
			$key = 'ohmylms_extension_' . $id;
			if ( ! isset( $data[ $key ] ) ) {
				continue; }
			$valid = rest_validate_value_from_schema( $data[ $key ], $field['schema'], $id );
			if ( is_wp_error( $valid ) ) {
				continue; }
			$values[ $id ] = rest_sanitize_value_from_schema( $data[ $key ], $field['schema'], $id );
		}
		if ( $values ) {
			$order->update_meta_data( '_ohmylms_extension_fields', $values ); }
	}
}
