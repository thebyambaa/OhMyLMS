<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hook for Membership
 *
 * @package    OhMyLMSPro
 * @subpackage OhMyLMSPro/includes
 */
namespace OhMyLMS\Hooks;

use OhMyLMS\Data\Membership;
use function OhMyLMS\Commerce\ecommerce;
/**
 * Plugin membership hook implementation.
 */
class MembershipHook {

	/**
	 * Register hooks for Membership hook.
	 */
	public function register_hooks() {
		\OhMyLMS\Membership\CourseSelection::init();
		// add_action('ohmylms_after_review_order', array( __CLASS__, 'display_recurring_totals' ), 10, 2 );.
		add_action( 'ohmylms_after_order_details', array( __CLASS__, 'display_order_details' ), 10, 1 );
		add_action( 'ohmylms_recurring_subscription_totals', array( __CLASS__, 'get_recurring_subscription_totals' ) );
		add_action( 'ohmylms_after_thankyou_table', array( $this, 'show_subscription_info' ), 10, 2 );
		add_filter( 'ohmylms_thankyou_text', array( $this, 'membership_thankyou_text' ), 10, 3 );

		add_action( 'ohmylms_rest_after_adding_products_on_membership', array( $this, 'enroll_courses' ), 10, 2 );
		add_filter( 'ohmylms_checkout_course_price_html', array( $this, 'membership_price_html' ), 10, 3 );
		add_filter( 'ohmylms_cart_item_subtotal', array( $this, 'membership_cart_subtaotal' ), 10, 4 );
		add_filter( 'ohmylms_cart_item_price', array( $this, 'membership_cart_item_price' ), 10, 4 );
		add_filter( 'ohmylms_cart_item_line_total', array( $this, 'membership_discounted_prices' ), 10, 4 );
		add_filter( 'ohmylms_cart_discounted_price', array( $this, 'membership_discounted_prices' ), 10, 4 );
		add_filter( 'ohmylms_order_item_subtotal', array( $this, 'membership_order_item_subtotal' ), 10, 4 );
	}


	/**
	 * Display recurring totals
	 *
	 * @param mixed $membership Membership.
	 * @return void
	 * @since 1.0.0
	 */
	public static function display_recurring_totals( $membership ) {

		if ( is_null( $membership ) || ! is_object( $membership ) || ! ( $membership instanceof Membership ) ) {
			return;
		}
		if ( ! $membership->get_first_renewal_payment_date() ) {
			return;
		}

		ohmylms_get_template(
			'templates/checkout/recurring-totals',
			array(
				'membership' => $membership,
			)
		);
	}

	/**
	 * Display order details
	 *
	 * @param mixed $order Order.
	 * @return void
	 * @since 1.0.0
	 */
	public static function display_order_details( $order ) {
		if ( is_null( $order ) || ! is_object( $order ) ) {
			return;
		}
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			$id         = $item->get_course_id();
			$membership = ohmylms_get_membership( $id );
			if ( ! $membership ) {
				continue;
			}
			if ( $membership && $membership instanceof Membership ) {
				$billing_period = $membership->get_subscription_period();

				if ( 'one_time' === $billing_period ) {
					continue;
				}
				ohmylms_get_template(
					'templates/checkout/recurring-totals',
					array(
						'membership' => $membership,
					)
				);
			}
		}
	}

	/**
	 * Get recurring subscription totals
	 *
	 * @param mixed $membership Membership.
	 * @return void
	 * @since 1.0.0
	 */
	public static function get_recurring_subscription_totals( $membership ) {
		if ( is_null( $membership ) || ! is_object( $membership ) || ! ( $membership instanceof Membership ) ) {
			return;
		}

		$price          = $membership->get_regular_price();
		$billing_period = $membership->get_subscription_period();

		if ( 'one_time' === $billing_period ) {
			return;
		}

		$billing_interval = $membership->get_subscription_period_interval();

		$stored_totals = ecommerce()->session->get( 'cart_totals' );
		if ( ! empty( $stored_totals ) && isset( $stored_totals['tax_rate'] ) && $stored_totals['tax_rate'] > 0 ) {
			$tax_rate = $stored_totals['tax_rate'];
			// Now calculate tax based on the discounted total.
			$tax_data = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax(
				$tax_rate,
				array(
					'total' => $price,
				)
			);
			$price    = isset( $tax_data['total_with_tax'] ) ? $tax_data['total_with_tax'] : $price;
		}
		$price_html = ohmylms_price( $price ) . ' / ' . $billing_period;
		$now        = current_time( 'timestamp' );

		switch ( $billing_period ) {
			case 'month':
				$renewal = strtotime( "+$billing_interval month", $now );
				break;
			case 'week':
				$renewal = strtotime( "+$billing_interval week", $now );
				break;
			case 'day':
				$renewal = strtotime( "+$billing_interval day", $now );
				break;
			case 'year':
			default:
				$renewal = strtotime( "+$billing_interval year", $now );
				break;
		}
		$renewal_date = date_i18n( get_option( 'date_format' ), $renewal );

		ohmylms_get_template(
			'templates/checkout/recurring-subscription-totals',
			array(
				'membership'       => $membership,
				'renewal_date'     => $renewal_date,
				'billing_period'   => $billing_period,
				'billing_interval' => $billing_interval,
				'price_html'       => $price_html,
			)
		);
	}


	/**
	 * Change the thankyou text for memberships
	 *
	 * @param mixed $thankyou_text Thankyou text.
	 * @param mixed $order Order.
	 * @param mixed $is_course Is course.
	 * @return mixed|string
	 * @since 1.0.0
	 */
	public function membership_thankyou_text( $thankyou_text, $order, $is_course ) {
		if ( $is_course ) {
			return $thankyou_text;
		}
		$order_id       = $order->get_id();
		$subscripton_id = get_post_meta( $order_id, '_subscription_id', true );
		$subscription   = ohmylms_ecommerce_get_subscription( $subscripton_id );
		if ( ! $subscription ) {
			return $thankyou_text;
		}
		$membership_id = $subscription->get_membership_id();
		$membership    = function_exists( 'ohmylms_get_membership' ) ? ohmylms_get_membership( $membership_id ) : null;
		if ( ! $membership ) {
			return;
		}
		$billing_period = $membership->get_subscription_period();

		if ( 'one_time' === $billing_period ) {
			return;
		}

		$thankyou_text = sprintf(
			/* translators: %1$s: text value; %2$s: text value. */
			__( '<strong>Your subscription is %1$s!</strong> A confirmation email has been sent to <br/> <span>%2$s</span>.', 'ohmylms' ),
			$subscription->get_status(),
			$order->get_email()
		);
		return $thankyou_text;
	}


	/**
	 * Show subscription info for Membership hook.
	 *
	 * @param mixed $order Order.
	 * @param mixed $is_course Is course.
	 */
	public function show_subscription_info( $order, $is_course ) {
		if ( $is_course ) {
			return;
		}
		$order_id       = $order->get_id();
		$subscripton_id = get_post_meta( $order_id, '_subscription_id', true );
		$subscription   = ohmylms_ecommerce_get_subscription( $subscripton_id );
		if ( ! $subscription ) {
			return;
		}
		$membership_id = $subscription->get_membership_id();
		$membership    = function_exists( 'ohmylms_get_membership' ) ? ohmylms_get_membership( $membership_id ) : null;
		if ( ! $membership ) {
			return;
		}
		$billing_period = $membership->get_subscription_period();

		if ( 'one_time' === $billing_period ) {
			return;
		}

		ohmylms_get_template(
			'templates/checkout/subscription-info',
			array(
				'subscription' => $subscription,
				'order_id'     => $order_id,
			)
		);
	}


	/**
	 * Enroll students in courses associated with a membership
	 *
	 * @param array $products The products to enroll students in.
	 * @param int   $membership_id The ID of the membership.
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function enroll_courses( $products, $membership_id ) {
		\OhMyLMS\Membership\CourseSelection::sync( $membership_id );
	}


	/**
	 * Format the course price HTML for memberships
	 *
	 * @param string $price_html The HTML for the course price.
	 * @param float  $course_price The price of the course.
	 * @param object $membership The membership object.
	 * .
	 * @return string The formatted price HTML.
	 *
	 * @since 1.0.0
	 */
	public function membership_price_html( $price_html, $course_price, $membership ) {
		if ( ! is_object( $membership ) || ! ( $membership instanceof \OhMyLMS\Data\Membership ) ) {
			return $price_html;
		}
		// Get the signup fee from the membership object.
		$signup_fee = method_exists( $membership, 'get_sign_up_fee' ) ? floatval( $membership->get_sign_up_fee() ) : 0;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		$price_html = ohmylms_price( $course_price );
		if ( $signup_fee > 0 ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
			$price_html .= ' <small style="font-weight: normal;">and a ' . ohmylms_price( $signup_fee ) . ' sign-up fee</small>';
		}
		return $price_html;
	}


	/**
	 * Format the cart subtotal price HTML for memberships
	 *
	 * @param mixed  $cart_subtotal_html Cart subtotal html.
	 * @param string $cart_subtotal The cart subtotal price.
	 * @param object $membership The membership object.
	 * .
	 * @return string The formatted cart subtotal price HTML.
	 * @since 1.0.0
	 */
	public function membership_subtotal_price_html( $cart_subtotal_html, $cart_subtotal, $membership ) {
		if ( ! is_object( $membership ) || ! ( $membership instanceof \OhMyLMS\Data\Membership ) ) {
			return $price_html;
		}
		$signup_fee = method_exists( $membership, 'get_sign_up_fee' ) ? floatval( $membership->get_sign_up_fee() ) : 0;
		if ( $signup_fee > 0 ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
			$new_subtotal = floatval( $cart_subtotal ) + $signup_fee;
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
			return ohmylms_price( $new_subtotal );
		}
		return $cart_subtotal;
	}


	/**
	 * Calculate the discounted price for a membership item in the cart
	 *
	 * @param float  $discounted_price The original discounted price.
	 * @param array  $item The cart item data.
	 * @param string $item_key The cart item key.
	 * @param object $cart The cart object.
	 * .
	 * @return float The final discounted price after applying any coupon discounts.
	 * @since 1.0.0
	 */
	public function membership_discounted_prices( $discounted_price, $item, $item_key, $cart ) {
		// $item['data'] is the membership object
		$object      = $item->object;
		$membership  = $object['data'];
		$signup_fee  = method_exists( $membership, 'get_sign_up_fee' ) ? floatval( $membership->get_sign_up_fee() ) : 0;
		$total_price = $discounted_price + $signup_fee;
		return $total_price;
	}


	/**
	 * Calculate the cart subtotal for a membership item
	 *
	 * @param float  $subtotal The original subtotal price.
	 * @param array  $item The cart item data.
	 * @param string $item_key The cart item key.
	 * @param object $cart The cart object.
	 * .
	 * @return float The final subtotal price after adding the signup fee.
	 * @since 1.0.0
	 */
	public function membership_cart_subtaotal( $subtotal, $item, $item_key, $cart ) {
		// $item['data'] is the membership object
		if ( is_array( $item ) && isset( $item['data'] ) ) {
			$membership = $item['data'];
		} elseif ( is_object( $item ) && isset( $item->object['data'] ) ) {
			$membership = $item->object['data'];
		} else {
			$membership = null;
		}
		$signup_fee  = method_exists( $membership, 'get_sign_up_fee' ) ? floatval( $membership->get_sign_up_fee() ) : 0;
		$total_price = $subtotal + $signup_fee;
		return $total_price;
	}


	/**
	 * Calculate the price for a membership item in the cart
	 *
	 * @param float  $price The original price of the item.
	 * @param array  $item The cart item data.
	 * @param mixed  $cart_item Cart item.
	 * @param object $cart The cart object.
	 * .
	 * @return float The final price after adding the signup fee.
	 * @since 1.0.0
	 */
	public function membership_cart_item_price( $price, $item, $cart_item, $cart ) {
		if ( is_array( $item ) && isset( $item['data'] ) ) {
			$membership = $item['data'];
		} elseif ( is_object( $item ) && isset( $item->object['data'] ) ) {
			$membership = $item->object['data'];
		} else {
			$membership = null;
		}
		$signup_fee  = method_exists( $membership, 'get_sign_up_fee' ) ? floatval( $membership->get_sign_up_fee() ) : 0;
		$total_price = floatval( $price ) + $signup_fee;
		return $total_price;
	}


	/**
	 * Calculate the order item subtotal for a membership
	 *
	 * @param float  $subtotal The original subtotal price.
	 * @param object $membership The membership object.
	 * @param object $order The order object.
	 * .
	 * @return float The final subtotal price after adding the signup fee.
	 * @since 1.0.0
	 */
	public function membership_order_item_subtotal( $subtotal, $membership, $order ) {
		$signup_fee = method_exists( $membership, 'get_sign_up_fee' ) ? floatval( $membership->get_sign_up_fee() ) : 0;

		if ( $signup_fee > 0 ) {
			$subtotal += $signup_fee;
		}

		return $subtotal;
	}
}
