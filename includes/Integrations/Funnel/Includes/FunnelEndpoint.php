<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * FunnelEndpoint Class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Integrations\Funnel\Includes;

use OhMyLMS\Commerce\SubscriptionManager;

use function OhMyLMS\Commerce\ecommerce;

/**
 * FunnelEndpoint Class
 *
 * Handles funnel step endpoints and URL processing.
 *
 * @since 1.0.0
 */
class FunnelEndpoint {

	/**
	 * New tax amount after upsell/downsell purchase.
	 *
	 * @var float
	 * @since 1.0.0
	 */
	private $new_tax_amount;


	/**
	 * New discount amount after upsell/downsell purchase.
	 *
	 * @var float
	 * @since 1.0.0
	 */
	private $new_discount_amount;

	/**
	 * Initialize the funnel endpoint.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_funnel_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_funnel_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	/**
	 * Add funnel endpoint rewrite rules.
	 *
	 * @since 1.0.0
	 */
	public function add_funnel_endpoint() {
		// Add rewrite rule for new URL format: /post-checkout-funnel/order/{order_id}/step/{step_number}.
		add_rewrite_rule(
			'^post-checkout-funnel/order/([0-9]+)/step/([^/]+)/?$',
			'index.php?funnel_step=1&order_id=$matches[1]&step=$matches[2]',
			'top'
		);

		// Add rewrite rule for funnel actions: /post-checkout-funnel/order/{order_id}/step/{step_number}/action/{action}.
		add_rewrite_rule(
			'^post-checkout-funnel/order/([0-9]+)/step/([^/]+)/action/([^/]+)/?$',
			'index.php?funnel_step=1&order_id=$matches[1]&step=$matches[2]&action=$matches[3]',
			'top'
		);
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Current query vars.
	 * @return array Modified query vars.
	 * @since 1.0.0
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'funnel_step';
		$vars[] = 'order_id';
		$vars[] = 'step';
		$vars[] = 'action';
		return $vars;
	}

	/**
	 * Handle funnel endpoint requests.
	 *
	 * @since 1.0.0
	 */
	public function handle_funnel_endpoint() {
		global $wp_query;
		// Check if this is a funnel step request.
		if ( ! isset( $wp_query->query_vars['funnel_step'] ) && ! get_query_var( 'funnel-step' ) ) {
			return;
		}

		// Get query parameters.
		$order_id = get_query_var( 'order_id' );
		$step     = get_query_var( 'step' );
		$action   = get_query_var( 'action' );

		// Validate required parameters.
		if ( empty( $order_id ) || empty( $step ) ) {
			wp_die( esc_html__( 'Invalid funnel request.', 'ohmylms' ), esc_html__( 'Error', 'ohmylms' ), array( 'response' => 400 ) );
		}

		// Handle funnel actions (accept/decline).
		if ( ! empty( $action ) ) {
			$this->handle_funnel_action( $order_id, $step, $action );
			return;
		}
		// Display funnel step.
		$this->display_funnel_step( $order_id, $step );
	}

	/**
	 * Handle funnel step actions (accept/decline).
	 *
	 * @param int    $order_id The order ID.
	 * @param string $step The current step.
	 * @param string $action The action (accept/decline).
	 * @since 1.0.0
	 */
	private function handle_funnel_action( $order_id, $step, $action ) {
		// Verify nonce for security.
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'funnel_action_' . $order_id . '_' . $step ) ) {
			wp_die( esc_html__( 'Security check failed.', 'ohmylms' ), esc_html__( 'Error', 'ohmylms' ), array( 'response' => 403 ) );
		}

		// Get funnel session data.
		$funnel_data = FunnelManager::get_funnel_session();

		if ( ! $funnel_data || $funnel_data['order_id'] != $order_id ) {
			wp_die( esc_html__( 'Invalid funnel session.', 'ohmylms' ), esc_html__( 'Error', 'ohmylms' ), array( 'response' => 400 ) );
		}

		// Process the action.
		if ( 'accept' === $action ) {
			$this->process_funnel_accept( $order_id, $step, $funnel_data );
		} elseif ( 'decline' === $action ) {
			$this->process_funnel_decline( $order_id, $step, $funnel_data );
		} else {
			wp_die( esc_html__( 'Invalid action.', 'ohmylms' ), esc_html__( 'Error', 'ohmylms' ), array( 'response' => 400 ) );
		}
	}

	/**
	 * Process funnel offer acceptance.
	 *
	 * @param int    $order_id The order ID.
	 * @param string $step The current step.
	 * @param array  $funnel_data The funnel session data.
	 * @since 1.0.0
	 */
	private function process_funnel_accept( $order_id, $step, $funnel_data ) {
		// Get the current funnel step data.
		$course_id    = $funnel_data['course_id'];
		$funnel_steps = get_post_meta( $course_id, '_funnel_steps', true );

		$current_step_data = null;
		foreach ( $funnel_steps as $funnel_step ) {
			if ( $funnel_step['step_id'] === 'step_' . $step ) {
				$current_step_data = $funnel_step;
				break;
			}
		}

		if ( ! $current_step_data ) {
			wp_die( esc_html__( 'Invalid funnel step.', 'ohmylms' ), esc_html__( 'Error', 'ohmylms' ), array( 'response' => 400 ) );
		}

		// Process the upsell/downsell purchase first.
		$purchase_result = $this->process_upsell_downsell_purchase( $order_id, $current_step_data );

		// Check if purchase failed.
		if ( is_wp_error( $purchase_result ) ) {
			wp_die( $purchase_result->get_error_message(), esc_html__( 'Payment Error', 'ohmylms' ), array( 'response' => 400 ) );
		}

		// Check accepted action from condition configuration.
		$accepted_action = $current_step_data['condition']['accepted']['action'] ?? 'next_step';

		if ( 'thank_you_page' === $accepted_action ) {
			// Redirect directly to thank you page using payment gateway's return URL.
			$thank_you_url = $this->get_gateway_return_url( $order_id );

			// Clear funnel session since we're ending the funnel.
			FunnelManager::clear_funnel_session();
			wp_redirect( $thank_you_url );
		} elseif ( 'next_step' === $accepted_action ) {
			// Continue to next step.
			$next_step = $this->get_next_funnel_step( $funnel_steps, $step );

			if ( $next_step ) {
				// Redirect to next step using new URL format.
				$next_step_number = $next_step['step_id'];
				if ( strpos( $next_step_number, 'step_' ) === 0 ) {
					$next_step_number = substr( $next_step_number, 5 );
				}
				$next_url = home_url( sprintf( '/post-checkout-funnel/order/%d/step/%s', $order_id, $next_step_number ) );
				wp_redirect( $next_url );
			} else {
				// No more steps, redirect to thank you page.
				$thank_you_url = $this->get_gateway_return_url( $order_id );

				// Clear funnel session.
				FunnelManager::clear_funnel_session();

				wp_redirect( $thank_you_url );
			}
		} else {
			// Default fallback - go to thank you page.
			$thank_you_url = $this->get_gateway_return_url( $order_id );

			// Clear funnel session.
			FunnelManager::clear_funnel_session();

			wp_redirect( $thank_you_url );
		}
		exit;
	}

	/**
	 * Process funnel offer decline.
	 *
	 * @param int    $order_id The order ID.
	 * @param string $step The current step.
	 * @param array  $funnel_data The funnel session data.
	 * @since 1.0.0
	 */
	private function process_funnel_decline( $order_id, $step, $funnel_data ) {
		// Get the current funnel step data to check decline action.
		$course_id    = $funnel_data['course_id'];
		$funnel_steps = get_post_meta( $course_id, '_funnel_steps', true );

		$current_step_data = null;
		foreach ( $funnel_steps as $funnel_step ) {
			if ( $funnel_step['step_id'] === 'step_' . $step ) {
				$current_step_data = $funnel_step;
				break;
			}
		}

		if ( ! $current_step_data ) {
			wp_die( esc_html__( 'Invalid funnel step.', 'ohmylms' ), esc_html__( 'Error', 'ohmylms' ), array( 'response' => 400 ) );
		}

		// Check decline action - for now, just go to next step or thank you page.
		$decline_action = $current_step_data['condition']['declined']['action'] ?? 'next_step';

		if ( 'next_step' === $decline_action ) {
			$next_step = $this->get_next_funnel_step( $funnel_steps, $step );

			if ( $next_step ) {
				// Redirect to next step using new URL format.
				$next_step_number = $next_step['step_id'];
				if ( strpos( $next_step_number, 'step_' ) === 0 ) {
					$next_step_number = substr( $next_step_number, 5 );
				}

				$next_url = home_url( sprintf( '/post-checkout-funnel/order/%d/step/%s', $order_id, $next_step_number ) );
				wp_redirect( $next_url );
			} else {
				// No more steps, redirect to thank you page.
				$thank_you_url = $this->get_gateway_return_url( $order_id );

				// Clear funnel session.
				FunnelManager::clear_funnel_session();

				wp_redirect( $thank_you_url );
			}
		} else {
			// End funnel, redirect to thank you page.
			$thank_you_url = $this->get_gateway_return_url( $order_id );

			// Clear funnel session.
			FunnelManager::clear_funnel_session();

			wp_redirect( $thank_you_url );
		}
		exit;
	}

	/**
	 * Get the next funnel step after the current one.
	 *
	 * @param array  $funnel_steps All funnel steps.
	 * @param string $current_step_id Current step ID.
	 * @return array|null Next step data or null if no next step.
	 * @since 1.0.0
	 */
	private function get_next_funnel_step( $funnel_steps, $current_step_id ) {
		$found_current = false;

		foreach ( $funnel_steps as $step ) {
			if ( $found_current ) {
				return $step;
			}

			if ( $step['step_id'] === 'step_' . $current_step_id ) {
				$found_current = true;
			}
		}

		return null;
	}

	/**
	 * Display funnel step page.
	 *
	 * @param int    $order_id The order ID.
	 * @param string $step The step to display.
	 * @since 1.0.0
	 */
	private function display_funnel_step( $order_id, $step ) {
		// Get funnel session data.
		$funnel_data = FunnelManager::get_funnel_session();
		if ( ! $funnel_data || $funnel_data['order_id'] != $order_id ) {
			wp_die( esc_html__( 'Invalid funnel session.', 'ohmylms' ), esc_html__( 'Error', 'ohmylms' ), array( 'response' => 400 ) );
		}

		// Get the funnel step data.
		$course_id    = $funnel_data['course_id'];
		$funnel_steps = get_post_meta( $course_id, '_funnel_steps', true );

		$step_data = null;
		foreach ( $funnel_steps as $funnel_step ) {
			if ( $funnel_step['step_id'] === 'step_' . $step ) {
				$step_data = $funnel_step;
				break;
			}
		}

		if ( ! $step_data ) {
			wp_die( esc_html__( 'Invalid funnel step.', 'ohmylms' ), esc_html__( 'Error', 'ohmylms' ), array( 'response' => 404 ) );
		}

		// Check if this step has an offer page ID - if so, redirect to that page.
		if ( ! empty( $step_data['offer_page_id'] ) ) {
			$offer_page_url = get_permalink( $step_data['offer_page_id'] );

			if ( $offer_page_url ) {
				$order          = ohmylms_ecommerce_get_order( $order_id );
				$payment_method = $order->get_payment_method();

				// Initialize query arguments.
				$query_args = array(
					'order_id'     => $order_id,
					'step'         => $step,
					'funnel_nonce' => wp_create_nonce( 'funnel_action_' . $order_id . '_' . $step ),
				);

				// Add token if payment method is PayPal.
				if ( 'paypal' === $payment_method ) {
					$query_args['token'] = get_post_meta( $order_id, '_paypal_order_id', true );
				}

				// Add funnel parameters to the offer page URL.
				$offer_page_url = add_query_arg( $query_args, $offer_page_url );

				wp_redirect( $offer_page_url );
				exit;
			}
		}
	}

	/**
	 * Process upsell/downsell purchase when user accepts the offer.
	 *
	 * @param int   $order_id The original order ID.
	 * @param array $step_data The current funnel step data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	private function process_upsell_downsell_purchase( $order_id, $step_data ) {
		// Get the original order.
		$original_order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $original_order ) {
			return new \WP_Error( 'invalid_order', __( 'Original order not found.', 'ohmylms' ) );
		}

		// Determine offer type and get the appropriate product.
		$offer_type = $step_data['offer_type'] ?? 'course';
		$product    = null;
		$product_id = null;

		if ( 'course' === $offer_type ) {
			$product_id = $step_data['course_id'];
			if ( empty( $product_id ) ) {
				return new \WP_Error( 'invalid_course', __( 'Course not specified for upsell/downsell.', 'ohmylms' ) );
			}
			$product = ohmylms_get_course( $product_id );
			if ( ! $product ) {
				return new \WP_Error( 'course_not_found', __( 'Course not found.', 'ohmylms' ) );
			}
		} elseif ( 'membership' === $offer_type ) {
			$product_id = $step_data['membership_id'];
			if ( empty( $product_id ) ) {
				return new \WP_Error( 'invalid_membership', __( 'Membership not specified for upsell/downsell.', 'ohmylms' ) );
			}
			$product = ohmylms_get_membership( $product_id );
			if ( ! $product ) {
				return new \WP_Error( 'membership_not_found', __( 'Membership not found.', 'ohmylms' ) );
			}
		} else {
			return new \WP_Error( 'invalid_offer_type', __( 'Invalid offer type specified.', 'ohmylms' ) );
		}

		// Calculate the final price based on discount configuration.
		$final_price = $this->calculate_upsell_price( $product, $step_data );

		$this->calculate_discount_amount( $product, $step_data );
		// Apply tax to the final price and update order tax amount.
		$final_price = $this->apply_tax_to_upsell_price( $final_price, $order_id );

		// Get payment method from original order.
		$payment_method     = $original_order->get_payment_method();
		$available_gateways = ecommerce()->gateways()->get_available_payment_gateways();
		if ( ! isset( $available_gateways[ $payment_method ] ) ) {
			return new \WP_Error( 'unsupported_gateway', __( 'Upsell/downsell currently only supported for Stripe and Paypal payments.', 'ohmylms' ) );
		}

		$payment_result = $available_gateways[ $payment_method ]->process_offer_payment( $original_order, $product, $final_price, $step_data );

		if ( is_wp_error( $payment_result ) ) {
			return $payment_result;
		}

		// Add the product to the original order.
		$this->add_product_to_order( $original_order, $product, $final_price, $step_data, $offer_type );

		return true;
	}

	/**
	 * Calculate the final price for upsell/downsell based on discount configuration.
	 *
	 * @param object $product The product object (course or membership).
	 * @param array  $step_data The funnel step data.
	 * @return float The calculated final price.
	 * @since 1.0.0
	 */
	private function calculate_upsell_price( $product, $step_data ) {
		// Get the original product price.
		$original_price  = is_callable( array( $product, 'get_price' ) ) ? $product->get_price() : $product->price;
		$signup_fee      = method_exists( $product, 'get_sign_up_fee' ) ? floatval( $product->get_sign_up_fee() ) : 0;
		$original_price += $signup_fee;

		// Get discount configuration.
		$discount_type  = $step_data['discount_type'] ?? 'no_discount';
		$discount_value = floatval( $step_data['discount_value'] ?? 0 );

		$final_price = $original_price;

		switch ( $discount_type ) {
			case 'percentage':
				// Apply percentage discount.
				$discount_amount = ( $original_price * $discount_value ) / 100;
				$final_price     = $original_price - $discount_amount;
				break;

			case 'amount':
				// Apply fixed amount discount.
				$final_price = $original_price - $discount_value;
				break;

			case 'no_discount':
			default:
				// No discount, use original price.
				$final_price = $original_price;
				break;
		}

		// Ensure final price is not negative.
		$final_price = max( 0, $final_price );

		return $final_price;
	}


	/**
	 * Calculate discount amount for Funnel endpoint.
	 *
	 * @param mixed $product Product.
	 * @param mixed $step_data Step data.
	 */
	private function calculate_discount_amount( $product, $step_data ) {
		// Get discount configuration.
		$original_price  = is_callable( array( $product, 'get_price' ) ) ? $product->get_price() : $product->price;
		$signup_fee      = method_exists( $product, 'get_sign_up_fee' ) ? floatval( $product->get_sign_up_fee() ) : 0;
		$original_price += $signup_fee;
		$discount_type   = $step_data['discount_type'] ?? 'no_discount';
		$discount_value  = floatval( $step_data['discount_value'] ?? 0 );

		$this->new_discount_amount = 0;
		switch ( $discount_type ) {
			case 'percentage':
				// Apply percentage discount.
				$this->new_discount_amount = ( $original_price * $discount_value ) / 100;
				break;

			case 'amount':
				// Apply fixed amount discount.
				$this->new_discount_amount = $discount_value;
				break;

			case 'no_discount':
			default:
				// No discount, use original price.
				$this->new_discount_amount = 0;
				break;
		}
	}

	/**
	 * Apply tax to upsell price and update order tax amount.
	 *
	 * @param float $price The price before tax.
	 * @param int   $order_id The order ID.
	 * @return float The price including tax.
	 * @since 1.0.0
	 */
	private function apply_tax_to_upsell_price( $price, $order_id ) {
		// Check if tax is enabled globally (you may need to adjust this based on your tax settings).
		$tax_enabled = get_option( 'ohmylms_tax_enabled', 'no' ) === 'yes';
		if ( ! $tax_enabled ) {
			return $price;
		}

		// Get tax rate from order meta.
		$tax_rate = get_post_meta( $order_id, '_tax_rate', true );
		if ( empty( $tax_rate ) || ! is_numeric( $tax_rate ) ) {
			return $price;
		}

		// Convert tax rate to decimal.
		$tax_rate = floatval( $tax_rate ) / 100;

		// Calculate tax amount for the upsell.
		$tax_amount = $price * $tax_rate;

		// Get current tax amount from order.
		$current_tax_amount = get_post_meta( $order_id, '_tax_amount', true );
		$current_tax_amount = is_numeric( $current_tax_amount ) ? floatval( $current_tax_amount ) : 0;

		// Add the new tax amount to the existing tax amount.
		$this->new_tax_amount = $current_tax_amount + $tax_amount;

		if ( ! \OhMyLMS\Commerce\Includes\Tax\TaxService::get_instance()->prices_include_tax() ) {
			// If prices include tax, add tax to the price.
			$price += $tax_amount;
		}

		// Return price including tax.
		return $price;
	}

	/**
	 * Add the upsell/downsell product to the original order.
	 *
	 * @param object $original_order The original order.
	 * @param object $product The product to add (course or membership).
	 * @param float  $final_price The final price after discount.
	 * @param array  $step_data The funnel step data.
	 * @param string $offer_type The offer type ('course' or 'membership').
	 * @return void
	 * @since 1.0.0
	 */
	private function add_product_to_order( $original_order, $product, $final_price, $step_data, $offer_type ) {
		// Create appropriate order item based on offer type.
		$signup_fee = method_exists( $product, 'get_sign_up_fee' ) ? floatval( $product->get_sign_up_fee() ) : 0;
		if ( 'course' === $offer_type ) {
			$item = new \OhMyLMS\Commerce\Data\OrderItemCourse();

			$item->set_props(
				array(
					'name'      => $product->get_name(),
					'course_id' => $product->get_id(),
					'quantity'  => 1,
					'subtotal'  => $product->get_price() + $signup_fee, // Original price.
					'total'     => $product->get_price() + $signup_fee, // Price after discount.
				)
			);
		} elseif ( 'membership' === $offer_type ) {
			// Check if OrderItemMembership class exists, otherwise use generic order item.
			if ( class_exists( '\OhMyLMS\Commerce\Data\OrderItemMembership' ) ) {
				$item = new \OhMyLMS\Commerce\Data\OrderItemMembership();
				$item->set_props(
					array(
						'name'      => $product->get_name(),
						'course_id' => $product->get_id(),
						'quantity'  => 1,
						'subtotal'  => $product->get_price() + $signup_fee, // Original price.
						'total'     => $product->get_price() + $signup_fee, // Price after discount.
					)
				);
			} else {
				$item = new \OhMyLMS\Commerce\Data\OrderItemMembership();
				// Fallback to generic order item.
				$item = new \OhMyLMS\Commerce\Data\OrderItem();
				$item->set_props(
					array(
						'name'     => $product->get_name(),
						'quantity' => 1,
						'subtotal' => $product->get_price() + $signup_fee, // Original price.
						'total'    => $product->get_price() + $signup_fee, // Price after discount.
					)
				);
				$item->update_meta_data( 'membership_id', $product->get_id() );
			}
		}

		// Set the order ID on the item BEFORE saving.
		$item->set_order_id( $original_order->get_id() );

		// Add meta data for tracking.
		$item->update_meta_data( 'funnel_step', $step_data['step_id'] );
		$item->update_meta_data( 'step_type', $step_data['step_type'] );
		$item->update_meta_data( 'offer_type', $offer_type );
		$item->update_meta_data( 'discount_type', $step_data['discount_type'] ?? 'no_discount' );
		$item->update_meta_data( 'discount_value', $step_data['discount_value'] ?? 0 );
		$item->update_meta_data( 'original_price', $product->get_price() + $signup_fee ); // Store original price.

		// Add item to order first.
		$original_order->add_item( $item );
		// Save the item to ensure meta data is stored with correct order_id.
		$item->save();

		// Update order totals.
		$current_total = $original_order->get_total();
		$new_total     = $current_total + $final_price;
		$discount      = $original_order->get_cart_discount();
		$discount     += $this->new_discount_amount;
		$original_order->set_cart_discount( $discount );
		$original_order->set_total( $new_total );
		$original_order->set_tax_amount( $this->new_tax_amount );

		// Add order note.
		$note = sprintf(
			/* translators: %1$s: text value; %2$s: text value; %3$s: text value; %4$s: text value; %5$s: text value. */
			__( '%1$s (%2$s) added via funnel step %3$s. Original price: %4$s, Final price: %5$s', 'ohmylms' ),
			$product->get_name(),
			$offer_type,
			$step_data['step_id'],
			ohmylms_price( $product->get_price() ),
			ohmylms_price( $final_price )
		);
		$original_order->add_order_note( $note );

		// Save the order.
		$original_order->save();
		// Process enrollment for the new product.
		if ( 'course' === $offer_type ) {
			$this->process_upsell_enrollment( $original_order->get_student_id(), $product->get_id(), $original_order );
		} elseif ( 'membership' === $offer_type ) {
			$this->process_upsell_membership_enrollment( $original_order->get_student_id(), $product->get_id(), $original_order );
			$membership = ohmylms_get_membership( $product->get_id() );
			if ( $membership && 'one_time' !== $membership->get_subscription_period() ) {
				$subscription_id = SubscriptionManager::create_subscription( $original_order, $product->get_id() );
				if ( is_wp_error( $subscription_id ) ) {
					$original_order->add_order_note( sprintf( /* translators: %s: text value. */
					__( 'Automated subscription creation failed. Error: %s', 'ohmylms' ), $subscription_id->get_error_message() ) );
				} elseif ( $subscription_id ) {
					SubscriptionManager::mark_subscription_active( $subscription_id );
					$original_order->add_order_note( sprintf( /* translators: %1$d: number; %2$s: text value. */
					__( 'Subscription #%1$d created and activated for membership: %2$s.', 'ohmylms' ), $subscription_id, get_the_title( $product->get_id() ) ) );
				}
			}
		}
	}

	/**
	 * Process enrollment for upsell/downsell course.
	 *
	 * @param int    $student_id The student ID.
	 * @param int    $course_id The course ID.
	 * @param object $order The order object.
	 * @return void
	 * @since 1.0.0
	 */
	private function process_upsell_enrollment( $student_id, $course_id, $order ) {
		global $wpdb;

		$enrollment_table  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enrollment_status = 'pending' === $order->get_status() || 'processing' === $order->get_status() ? 'pending' : 'enrolled';

		$enrollment_data = array(
			'course_id'  => $course_id,
			'user_id'    => $student_id,
			'order_id'   => $order->get_id(),
			'status'     => $enrollment_status,
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		);

		$student = new \OhMyLMS\Data\Student( get_current_user_id() );
		if ( $course_id && $student && ! $student->maybe_enrolled( $course_id ) ) {
			// Check if the record exists.
			$existing_record = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $enrollment_table, $student_id, $course_id )
			);

			$origin = \OhMyLMS\Student\EnrolmentMirror::order_origin( $order->get_id() );
			if ( $existing_record ) {
				// Update the existing record.
				\OhMyLMS\Student\Enrollments::update(
					array(
						'order_id'   => $order->get_id(),
						'status'     => $enrollment_status,
						'progress'   => 'running',
						'start_date' => current_time( 'mysql' ),
					),
					array(
						'user_id'   => $student_id,
						'course_id' => $course_id,
					),
					$origin
				);
			} else {
				\OhMyLMS\Student\Enrollments::create( $enrollment_data, $origin );
			}

			if ( 'enrolled' === $enrollment_status ) {
				do_action( 'ohmylms_after_enrolled_student', $order->get_id() );
			}
		}
	}

	/**
	 * Process membership enrollment for upsell/downsell membership.
	 *
	 * @param int    $student_id The student ID.
	 * @param int    $membership_id The membership ID.
	 * @param object $order The order object.
	 * @return void
	 * @since 1.0.0
	 */
	private function process_upsell_membership_enrollment( $student_id, $membership_id, $order ) {
		global $wpdb;

		$membership_table  = $wpdb->prefix . 'ohmylms_user_membership';
		$enrollment_status = 'pending' === $order->get_status() || 'processing' === $order->get_status() ? 'pending' : 'enrolled';

		$membership_data = array(
			'membership_id' => $membership_id,
			'user_id'       => $student_id,
			'order_id'      => $order->get_id(),
			'status'        => $enrollment_status,
			'progress'      => 'running',
			'start_date'    => current_time( 'mysql' ),
		);

		// Check if the record exists.
		$existing_record = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND membership_id = %d', $membership_table, $student_id, $membership_id )
		);

		if ( $existing_record ) {
			// Update the existing record.
			$wpdb->update(
				$membership_table,
				array(
					'order_id'   => $order->get_id(),
					'status'     => $enrollment_status,
					'progress'   => 'running',
					'start_date' => current_time( 'mysql' ),
				),
				array(
					'user_id'       => $student_id,
					'membership_id' => $membership_id,
				)
			);
		} else {
			$wpdb->insert(
				$membership_table,
				$membership_data
			);
			do_action( 'ohmylms_after_enrolled_student', $order->get_id() );
		}

		$membership_details = ohmylms_get_membership( $membership_id )->get_products();
		if ( ! empty( $membership_details ) && is_array( $membership_details ) ) {
			foreach ( $membership_details as $membership_detail ) {
				$this->process_upsell_enrollment( $student_id, $membership_detail['id'], $order );
			}
		}
	}

	/**
	 * Get the proper return URL using the order's payment gateway.
	 *
	 * @param int $order_id The order ID.
	 * @return string The return URL from the payment gateway.
	 * @since 1.0.0
	 */
	private function get_gateway_return_url( $order_id ) {
		// Get order object.
		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $order ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			if ( 0 < $order_id ) {
				$order = ohmylms_ecommerce_get_order( $order_id );
			}
		}
		if ( ! $order || ! is_a( $order, '\OhMyLMS\Commerce\Data\Order' ) ) {
			return '';
		}
		return $order->get_checkout_redirect_url();
	}
}
