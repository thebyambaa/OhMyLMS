<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class file; see docs/CODING-STANDARDS.md.
/**
 * Learner commerce on the Interactivity API: buy buttons (`ohmylms/cart`), the checkout page
 * (`ohmylms/checkout`) and their server-rendered state. Prices, tax and coupons stay server-side.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions;

use OhMyLMS\Commerce\Includes\Tax\TaxService;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/** Decorates trusted commerce templates with directives and supplies module state. */
final class Commerce {

	/**
	 * Register the template decoration boundary.
	 */
	public static function init() {
		add_filter( 'ohmylms_template_html', array( __CLASS__, 'template' ), 20, 2 );
	}

	/**
	 * Decorate a rendered template.
	 *
	 * @param string $html     Template HTML.
	 * @param string $template Template name.
	 * @return string
	 */
	public static function template( $html, $template ) {
		if ( ! is_string( $html ) || 0 === strpos( (string) $template, 'emails/' ) ) {
			return $html;
		}
		if ( 'checkout/form-checkout.php' === $template ) {
			$html = self::checkout( $html );
		} elseif ( 'order/order-details.php' === $template ) {
			$html = self::order_details( $html );
		}
		return self::cart_buttons( $html );
	}

	/**
	 * Add `ohmylms/cart` directives to every buy, enroll and points button in an HTML fragment.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function cart_buttons( $html ) {
		if ( false === strpos( $html, 'add_to_cart_button' ) && false === strpos( $html, 'add-to-cart-using-point-button' ) ) {
			return $html;
		}
		$tags  = new \WP_HTML_Tag_Processor( $html );
		$found = false;
		while ( $tags->next_tag() ) {
			if ( ! in_array( $tags->get_tag(), array( 'A', 'BUTTON' ), true ) || null !== $tags->get_attribute( 'data-wp-interactive' ) ) {
				continue;
			}
			$point = (bool) $tags->has_class( 'add-to-cart-using-point-button' );
			if ( ! $point && ! $tags->has_class( 'add_to_cart_button' ) ) {
				continue;
			}
			$tags->set_attribute( 'data-wp-interactive', 'ohmylms/cart' );
			// Router region swaps keep an element's client context; a key per item stops a reused button buying the previous card's course.
			$tags->set_attribute(
				'data-wp-key',
				sprintf(
					'cart-%s-%d-%d',
					$point ? 'point' : 'buy',
					absint( $tags->get_attribute( 'data-course_id' ) ),
					absint( $tags->get_attribute( 'data-membership_id' ) )
				)
			);
			$tags->set_attribute(
				'data-wp-context',
				wp_json_encode(
					array(
						'courseId'     => absint( $tags->get_attribute( 'data-course_id' ) ),
						'membershipId' => absint( $tags->get_attribute( 'data-membership_id' ) ),
						'quantity'     => max( 1, absint( $tags->get_attribute( 'data-quantity' ) ) ),
						'purchaseBy'   => $point ? 'point' : '',
						'purchased'    => '1' === (string) $tags->get_attribute( 'is_already_purchased' ),
						'loading'      => false,
						'added'        => false,
					)
				)
			);
			$tags->set_attribute( 'data-wp-on--click', 'actions.add' );
			$tags->set_attribute( 'data-wp-class--loading', 'context.loading' );
			$tags->set_attribute( 'data-wp-class--added', 'context.added' );
			$tags->set_attribute( 'data-wp-bind--aria-busy', 'context.loading' );
			$found = true;
		}
		if ( $found ) {
			self::enqueue_cart();
		}
		return $tags->get_updated_html();
	}

	/**
	 * Enqueue `ohmylms/cart` with its state.
	 */
	public static function enqueue_cart() {
		Interactivity::enqueue( 'ohmylms/cart' );
		if ( ! Interactivity::once( 'commerce-cart-state' ) ) {
			return;
		}
		wp_interactivity_state(
			'ohmylms/cart',
			array(
				'restRoot' => esc_url_raw( rest_url() ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'messages' => array(
					'error' => __( 'This item could not be added. Please refresh the page and try again.', 'ohmylms' ),
				),
			)
		);
	}

	/**
	 * Add `ohmylms/checkout` directives to the checkout form template.
	 *
	 * @param string $html Rendered `checkout/form-checkout.php`.
	 * @return string
	 */
	public static function checkout( $html ) {
		if ( false === strpos( $html, 'ohmylms-checkout-form-outer' ) || false !== strpos( $html, 'ohmylms-checkout-login-required' ) ) {
			return $html;
		}
		$state   = self::checkout_state();
		$tags    = new \WP_HTML_Tag_Processor( $html );
		$root    = false;
		$row     = false;
		$button  = false;
		$handled = array(
			'submit'   => 'actions.submit',
			'click'    => 'actions.click',
			'change'   => 'actions.change',
			'input'    => 'actions.input',
			'focusin'  => 'actions.focusin',
			'focusout' => 'actions.focusout',
		);
		while ( $tags->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $tags->get_tag();
			if ( $tags->is_tag_closer() ) {
				if ( 'BUTTON' === $tag ) {
					$button = false;
				}
				continue;
			}
			if ( ! $root && $tags->has_class( 'ohmylms-checkout-form-outer' ) ) {
				$root = true;
				$tags->set_attribute( 'data-wp-interactive', 'ohmylms/checkout' );
				$tags->set_attribute( 'data-wp-init', 'callbacks.init' );
				$tags->set_attribute( 'data-wp-watch', 'callbacks.syncRequired' );
				foreach ( $handled as $event => $action ) {
					$tags->set_attribute( 'data-wp-on--' . $event, $action );
				}
				continue;
			}
			if ( $tags->has_class( 'ohmylms-form-row' ) ) {
				$tags->set_bookmark( 'row' );
				$row = true;
				if ( $tags->has_class( 'vat-number-field' ) ) {
					$tags->set_attribute( 'data-wp-style--display', 'state.vatDisplay' );
					$tags->set_attribute( 'data-wp-class--validate-required', 'state.vatVisible' );
					if ( ! in_array( $state['country'], self::eu_countries(), true ) ) {
						$tags->set_attribute( 'style', 'display:none' );
					}
				}
			} elseif ( $row && in_array( $tag, array( 'INPUT', 'SELECT' ), true ) && $tags->has_class( 'ohmylms-input-text' ) ) {
				// Floating labels start folded on filled fields (previously an inline jQuery script).
				$row = false;
				if ( 'SELECT' === $tag || '' !== trim( (string) $tags->get_attribute( 'value' ) ) ) {
					$tags->set_bookmark( 'field' );
					$tags->seek( 'row' );
					$tags->add_class( 'ohmylms-folded' );
					$tags->seek( 'field' );
				}
			} elseif ( 'LI' === $tag && $tags->has_class( 'ohmylms-single-payment' ) ) {
				$gateway = '';
				foreach ( $tags->class_list() as $class ) {
					if ( 0 === strpos( $class, 'payment_method_' ) ) {
						$gateway = substr( $class, strlen( 'payment_method_' ) );
					}
				}
				$tags->set_attribute( 'data-wp-context', wp_json_encode( array( 'gateway' => $gateway ) ) );
				$tags->set_attribute( 'data-wp-class--selected', 'state.isGatewaySelected' );
			} elseif ( $tags->has_class( 'payment_box' ) ) {
				$tags->set_attribute( 'data-wp-style--display', 'state.paymentBoxDisplay' );
			} elseif ( 'BUTTON' === $tag && $tags->has_class( 'ohmylms-place-order-button' ) ) {
				$button = true;
				$tags->set_attribute( 'data-wp-bind--disabled', 'state.busy' );
				$tags->set_attribute( 'data-wp-bind--aria-busy', 'state.busy' );
			} elseif ( $button && $tags->has_class( 'ohmylms-loader' ) ) {
				$tags->set_attribute( 'data-wp-style--display', 'state.loaderDisplay' );
			} elseif ( 'A' === $tag && $tags->has_class( 'showlogin' ) ) {
				$tags->set_attribute( 'aria-expanded', 'false' );
				$tags->set_attribute( 'data-wp-bind--aria-expanded', 'state.loginOpen' );
			}
		}
		if ( ! $root ) {
			return $html;
		}
		self::enqueue_checkout( $state );
		return $tags->get_updated_html();
	}

	/**
	 * Thank-you page: copy the order number.
	 *
	 * @param string $html Rendered `order/order-details.php`.
	 * @return string
	 */
	public static function order_details( $html ) {
		if ( false === strpos( $html, 'thankyou-copy-order-id' ) ) {
			return $html;
		}
		$tags  = new \WP_HTML_Tag_Processor( $html );
		$found = false;
		while ( $tags->next_tag( 'BUTTON' ) ) {
			if ( $tags->has_class( 'thankyou-copy-order-id' ) && null === $tags->get_attribute( 'data-wp-interactive' ) ) {
				$tags->set_attribute( 'data-wp-interactive', 'ohmylms/checkout' );
				$tags->set_attribute( 'data-wp-on--click', 'actions.copyOrderId' );
				$tags->set_attribute( 'aria-label', __( 'Copy order number', 'ohmylms' ) );
				$found = true;
			}
		}
		if ( $found ) {
			Interactivity::enqueue( 'ohmylms/checkout' );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Initial `ohmylms/checkout` state.
	 *
	 * @return array
	 */
	public static function checkout_state() {
		$fields   = ecommerce()->checkout()->get_checkout_fields();
		$required = array();
		foreach ( array( 'contact', 'billing' ) as $fieldset ) {
			foreach ( (array) ( $fields[ $fieldset ] ?? array() ) as $key => $field ) {
				if ( ! empty( $field['required'] ) ) {
					$required[] = (string) $key;
				}
			}
		}
		$country = (string) ( $fields['billing']['country']['default'] ?? '' );
		if ( ! empty( $_POST['country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mirrors Checkout::get_billing_fields(); display only.
			$country = sanitize_text_field( wp_unslash( $_POST['country'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		return array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'restRoot'       => esc_url_raw( rest_url() ),
			'restNonce'      => wp_create_nonce( 'wp_rest' ),
			'nonces'         => array(
				'applyCoupon'  => wp_create_nonce( 'apply-coupon' ),
				'removeCoupon' => wp_create_nonce( 'remove-coupon' ),
				'tax'          => wp_create_nonce( 'ohmylms_calculate_tax' ),
			),
			'requiredFields' => (object) self::gateway_required_fields(),
			'serverRequired' => $required,
			'taxEnabled'     => class_exists( TaxService::class ) && TaxService::get_instance()->is_tax_enabled(),
			'country'        => strtoupper( $country ),
			'gateway'        => '',
			'busy'           => false,
			'loginOpen'      => false,
			'messages'       => array(
				'error'    => __( 'Something went wrong. Please try again.', 'ohmylms' ),
				'required' => __( 'required', 'ohmylms' ),
			),
		);
	}

	/**
	 * Enqueue `ohmylms/checkout` with its state.
	 *
	 * @param array|null $state State, or null to build it.
	 */
	public static function enqueue_checkout( $state = null ) {
		Interactivity::enqueue( 'ohmylms/checkout' );
		wp_interactivity_state( 'ohmylms/checkout', null === $state ? self::checkout_state() : $state );
	}

	/**
	 * Map of payment gateway id => checkout field keys that gateway requires, so the checkout can
	 * toggle the "required" state live. Checkout::validate_gateway_required_fields() is authoritative.
	 *
	 * @return array<string, string[]>
	 */
	public static function gateway_required_fields() {
		$map = array();
		if ( ! function_exists( 'OhMyLMS\Commerce\ecommerce' ) ) {
			return $map;
		}
		$gateways = ecommerce()->gateways();
		if ( ! $gateways || ! method_exists( $gateways, 'get_available_payment_gateways' ) ) {
			return $map;
		}
		foreach ( (array) $gateways->get_available_payment_gateways() as $gateway ) {
			if ( ! is_object( $gateway ) || ! isset( $gateway->id ) || ! method_exists( $gateway, 'get_required_checkout_fields' ) ) {
				continue;
			}
			$map[ $gateway->id ] = array_values( (array) $gateway->get_required_checkout_fields() );
		}
		return $map;
	}

	/**
	 * EU member states that show the VAT number field (mirrors `EU_COUNTRIES` in commerce-logic.mjs).
	 *
	 * @return string[]
	 */
	public static function eu_countries() {
		return array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE' );
	}
}
