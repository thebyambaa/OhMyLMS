<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

use OhMyLMS\Commerce\Data\OrderItemCoupon;
use OhMyLMS\Commerce\Data\OrderItemCourse;
use OhMyLMS\Integrations\Funnel\Includes\FunnelManager;
use OhMyLMS\Data\Student;
use OhMyLMS\Membership\MembershipHelper;
use OhMyLMS\user\UserHelper;
use OhMyLMS\user\UserRepository;
use OhMyLMS\Commerce\Data\Order;
use OhMyLMS\Commerce\SubscriptionManager;

/**
 * Plugin checkout implementation.
 */
class Checkout {

	/**
	 * The single instance of the class.
	 *
	 * @var Checkout|null
	 */
	protected static $instance = null;

	/**
	 * Checkout fields are stored here.
	 *
	 * @var array|null
	 */
	protected $fields = null;

	/**
	 * Gets the main Checkout instance
	 *
	 * @return Checkout|null
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			add_action( 'ohmylms_checkout_contact', array( self::$instance, 'checkout_form_contact' ) );
			add_action( 'ohmylms_checkout_billing', array( self::$instance, 'checkout_form_billing' ) );

			/**
			* Trigger when OhMyLMS checkout is first initiated
			*
			* @since 1.0.0
			*/
			do_action( 'ohmylms_checkout_init', self::$instance );
		}
		return self::$instance;
	}

	/**
	 * Get checkout fields
	 *
	 * @param string $fieldset Fieldset.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_checkout_fields( $fieldset = '' ) {

		if ( ! is_null( $this->fields ) ) {
			return $fieldset ? $this->fields[ $fieldset ] : $this->fields;
		}

		$this->fields = array(
			'contact' => $this->get_contact_fields(),
			'billing' => $this->get_billing_fields(),
			'account' => $this->get_account_fields(),
		);

		$this->fields = apply_filters( 'ohmylms_checkout_fields', $this->fields );

		return $this->fields;
	}

	/**
	 * Check if the student has access to purchase a course without logging in.
	 * This function checks if the site allows guest purchases.
	 *
	 * @return bool True if guest purchases are allowed, false otherwise.
	 * @since 1.0.0
	 */
	public function is_allow_purchase_without_login() {
		$option  = get_option( 'ohmylms_allow_purchase_without_login', 'yes' );
		$default = 'yes' === $option;
		return apply_filters( 'ohmylms_allow_purchase_without_login', $default );
	}

	/**
	 * Check if registration is required for course checkout
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function is_registration_enabled() {
		return apply_filters( 'ohmylms_checkout_registration_required', 'yes' === get_option( 'ohmylms_guest_checkout' ) );
	}

	/**
	 * Check if login is required for course checkout
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function is_login_enabled() {
		return apply_filters( 'ohmylms_checkout_login_required', 'yes' === get_option( 'ohmylms_enable_login' ) );
	}

	/**
	 * Output the billing form.
	 */
	public function checkout_form_billing() {
		ohmylms_get_template( 'checkout/form-billing.php', array( 'checkout' => $this ) );
	}

	/**
	 * Output the contact form.
	 */
	public function checkout_form_contact() {
		ohmylms_get_template( 'checkout/form-contact.php', array( 'checkout' => $this ) );
	}

	/**
	 * Get contact fields for the checkout form.
	 *
	 * @return array An array of contact fields.
	 * @since 1.0.0
	 */
	public function get_contact_fields() {
		$current_user = wp_get_current_user();
		$user_email   = is_user_logged_in() ? $current_user->user_email : '';

		$fields = array(
			'email' => array(
				'type'         => 'email',
				'label'        => __( 'Email', 'ohmylms' ),
				'placeholder'  => __( 'Email', 'ohmylms' ),
				'required'     => true,
				'autocomplete' => 'email',
				'default'      => $user_email,
				'autofocus'    => 'autofocus',
			),
		);
		return $fields;
	}

	/**
	 * Get billing fields for the checkout form.
	 *
	 * @return array An array of billing fields.
	 * @since 1.0.0
	 */
	public function get_billing_fields() {
		// Get default country based on site language/locale.
		$site_locale = get_locale();
		// e.g., 'en_US', 'es_ES', 'de_DE'.
		$locale_parts    = explode( '_', $site_locale );
		$default_country = isset( $locale_parts[1] ) ? $locale_parts[1] : 'US';

		$country = ! empty( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : $default_country;
		$states  = ohmylms_get_states( $country );

		$fields = array(
			'first_name' => array(
				'type'        => 'text',
				'label'       => __( 'First Name', 'ohmylms' ),
				'placeholder' => __( 'First Name', 'ohmylms' ),
				'required'    => true,
			),
			'last_name'  => array(
				'type'        => 'text',
				'label'       => __( 'Last Name', 'ohmylms' ),
				'placeholder' => __( 'Last Name', 'ohmylms' ),
				'required'    => true,
			),
			'address'    => array(
				'type'        => 'text',
				'label'       => __( 'Address', 'ohmylms' ),
				'placeholder' => __( 'Address', 'ohmylms' ),
				'required'    => true,
			),
			'country'    => array(
				'type'        => 'select',
				'label'       => __( 'Country', 'ohmylms' ),
				'placeholder' => __( 'Country', 'ohmylms' ),
				'required'    => true,
				'options'     => ohmylms_get_countries(),
				'default'     => $default_country,
			),
			'state'      => array(
				'type'        => empty( $states ) ? 'text' : 'select',
				'label'       => __( 'State / Province', 'ohmylms' ),
				'placeholder' => __( 'State / Province', 'ohmylms' ),
				'required'    => false,
				'options'     => $states,
			),
			'city'       => array(
				'type'        => 'text',
				'label'       => __( 'City', 'ohmylms' ),
				'placeholder' => __( 'City', 'ohmylms' ),
				'required'    => false,
			),
			'postcode'   => array(
				'type'        => 'text',
				'label'       => __( 'Postcode', 'ohmylms' ),
				'placeholder' => __( 'Postcode', 'ohmylms' ),
				'required'    => false,
			),
			'phone'      => array(
				'type'         => 'tel',
				'label'        => __( 'Phone', 'ohmylms' ),
				'placeholder'  => __( 'Phone', 'ohmylms' ),
				'required'     => false,
				'validate'     => array( 'phone' ),
				'autocomplete' => 'tel',
				'class'        => array( 'form-row-wide' ),
			),
		);

		$fields['phone'] = array(
			'type'        => 'tel',
			'label'       => __( 'Phone Number', 'ohmylms' ),
			'placeholder' => __( 'Phone Number', 'ohmylms' ),
			'required'    => false,
		);

		/*
		 * Global admin control for the phone field:
		 *   'optional' (default) - shown, not required; a gateway can still escalate it.
		 *   'required'           - always required, regardless of gateway.
		 *   'hidden'             - removed from checkout entirely (a gateway that needs
		 *                          phone then simply cannot enforce it - see validate_checkout()).
		 */
		$phone_field_mode = get_option( 'ohmylms_checkout_phone_field', 'optional' );
		if ( 'hidden' === $phone_field_mode ) {
			unset( $fields['phone'] );
		} elseif ( 'required' === $phone_field_mode && isset( $fields['phone'] ) ) {
			$fields['phone']['required'] = true;
		}

		// Only add VAT number field if tax is enabled.
		if ( \OhMyLMS\Commerce\Includes\Tax\TaxService::get_instance()->is_tax_enabled() ) {
			$vat_label = get_option( 'ohmylms_vat_number_label', __( 'VAT Number', 'ohmylms' ) );

			$fields['vat_number'] = array(
				'type'              => 'text',
				'label'             => $vat_label,
				'placeholder'       => $vat_label,
				'required'          => false,
				'class'             => array( 'form-row-wide', 'vat-number-field' ),
				'custom_attributes' => array(
					'data-conditional' => 'true',
					'data-show-for-eu' => 'true',
				),
			);
		}

		return $fields;
	}

	/**
	 * Get account fields for the checkout form.
	 *
	 * @return array An array of account fields.
	 * @since 1.0.0
	 */
	public function get_account_fields() {
		$fields = array(
			'account_username' => array(
				'type'         => 'text',
				'label'        => __( 'Account username', 'ohmylms' ),
				'required'     => true,
				'placeholder'  => esc_attr__( 'Username', 'ohmylms' ),
				'autocomplete' => 'username',
			),
			'account_password' => array(
				'type'         => 'password',
				'label'        => __( 'Create account password', 'ohmylms' ),
				'required'     => true,
				'placeholder'  => esc_attr__( 'Password', 'ohmylms' ),
				'autocomplete' => 'new-password',
			),
		);
		return $fields;
	}

	/**
	 * Get user IP address.
	 *
	 * @return string
	 */
	public function get_ip_address() {
		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return (string) rest_is_ip_address( trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) ) );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '';
	}

	/**
	 * Get user agent.
	 *
	 * @return string
	 */
	public function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	/**
	 * Process the checkout.
	 *
	 * This function handles the checkout process, including validating the posted data,
	 * creating the order, processing the payment, and enrolling the user in the selected courses.
	 *
	 * @throws \Exception If there is an error during the checkout process.
	 *
	 * @since 1.0.0
	 */
	public function process_checkout() {
		try {
			$errors = new \WP_Error();

			// Get user IP and user agent.
			$user_ip    = $this->get_ip_address();
			$user_agent = $this->get_user_agent();

			// Check if there is an active logged-in user.
			if ( ! is_user_logged_in() && ! $this->is_allow_purchase_without_login() ) {
				$message = __( 'Please log in to purchase.', 'ohmylms' );
				ohmylmse_add_notice( $message, 'error', array() );
				$this->send_ajax_failure_response();
			}

			do_action( 'ohmylms_before_checkout_process' );
			$posted_data = $this->get_posted_data();

			$user_id = null;
			if ( ! is_user_logged_in() ) {
				$user = get_user_by( 'email', $posted_data['email'] );
				if ( $user ) {
					$user_id = $user->ID;
				}
			} else {
				$user_id = get_current_user_id();
			}

			if ( null !== $user_id ) {
				$student = new Student( $user_id );
				if ( $student->maybe_banned() ) {
					$message = __( 'Your account has been restricted and is not eligible to make purchases.', 'ohmylms' );
					ohmylmse_add_notice( $message, 'error', array() );
					$this->send_ajax_failure_response();
				}
			}

			// Update session for customer and totals.
			$this->update_session( $posted_data );

			$cart_data = ecommerce()->cart->get_cart_contents();
			$membership_ids = array( absint( $posted_data['membership_id'] ?? 0 ) );
			foreach ( $cart_data as $cart_entry ) {
				$membership_ids[] = absint( $cart_entry['course_id'] ?? 0 );
			}
			foreach ( array_unique( array_filter( $membership_ids ) ) as $membership_id ) {
				if ( 'ohmylms-membership' === get_post_type( $membership_id ) && \OhMyLMS\Membership\FreeSale::active( ohmylms_get_membership( $membership_id ) ) ) {
					wp_send_json( array( 'status' => 'error', 'message' => __( 'Use Start free access on the Membership plans page. No payment is needed.', 'ohmylms' ) ) );
				}
			}

			if ( empty( $cart_data ) && empty( $posted_data['membership_id'] ) ) {
				$response = array(
					'status'  => 'error',
					'message' => __( 'No cart item found.', 'ohmylms' ),
				);
				wp_send_json( $response );
			}

			$maybe_by_point = $this->maybe_purchase_by_point( $cart_data );
			if ( $maybe_by_point ) {
				// Cash items and memberships cannot be made free by one point-priced cart item.
				$valid_point_cart = \OhMyLMS\Engagement\Reward::valid_point_cart( $cart_data, $posted_data['membership_id'] ?? 0 );
				if ( ! $valid_point_cart ) {
					ohmylmse_add_notice( __( 'Point checkout requires only eligible point-priced courses. Purchase other items separately.', 'ohmylms' ), 'error', array() );
					$this->send_ajax_failure_response();
				}
			}

			$this->validate_checkout( $posted_data, $errors, $maybe_by_point );

			foreach ( $errors->errors as $code => $messages ) {
				$data = $errors->get_error_data( $code );
				foreach ( $messages as $message ) {
					ohmylmse_add_notice( $message, 'error', $data );
				}
			}
			if ( 0 !== ohmylmse_notice_count( 'error' ) ) {
				$this->send_ajax_failure_response();
			}

			$student_id = $this->process_student( $posted_data );

			if ( $maybe_by_point ) {
				$this->process_point_checkout( $student_id, $cart_data );
			}

			if ( 0 === ohmylmse_notice_count( 'error' ) ) {
				$order_id = $this->create_order( $posted_data );
				$order    = ohmylms_ecommerce_get_order( $order_id );
				if ( ! $order_id ) {
					$message = __( 'Failed to create order.', 'ohmylms' );
					ohmylmse_add_notice( $message, 'error', array() );
					$this->send_ajax_failure_response();
				}

				if ( $order->needs_payment() ) {
					$result = $this->process_order_payment( $order_id, $posted_data );
					$order  = ohmylms_ecommerce_get_order( $order_id );
				} else {
					$result = $this->process_order_without_payment( $order );
				}

				// Handle WP_Error result
				// QPay errors must retain the pending order: a timed-out invoice request
				// may already exist remotely. Keep its browser capability for safe resume.
				if ( 'qpay' === ( $posted_data['payment_method'] ?? '' ) && is_wp_error( $result ) ) {
					$token = get_post_meta( $order_id, '_qpay_browser_token', true );
					if ( ! $token ) {
						$token = wp_generate_password( 48, false, false );
						update_post_meta( $order_id, '_qpay_browser_token', $token );
					}
					$result = array(
						'result'         => 'success',
						'payment_status' => 'pending',
						'payment_method' => 'qpay',
						'order_id'       => $order_id,
						'payment_token'  => $token,
						'payment_error'  => $result->get_error_message(),
					);
				}
				if ( is_wp_error( $result ) ) {
					$message = __( 'Failed to process order due to payment.', 'ohmylms' );
					ohmylmse_add_notice( $result->get_error_message() ?: $message, 'error', array() );
					wp_trash_post( $order_id );
					// Clean up failed order.
					$this->send_ajax_failure_response();
				}

				if ( is_array( $result ) && isset( $result['result'] ) && 'failure' === $result['result'] ) {
					$message = __( 'Failed to process order due to payment.', 'ohmylms' );
					ohmylmse_add_notice( $result['message'] ?? $message, 'error', array() );
					wp_trash_post( $order_id );
					// Clean up failed order.
					$this->send_ajax_failure_response();
				}

				// If we have a valid result with redirect, proceed regardless of error notices
				// This ensures payment gateway redirects work even if other plugins add notices.
				$has_valid_result = is_array( $result ) && (
					( isset( $result['result'] ) && 'success' === $result['result'] ) ||
					( isset( $result['success'] ) && true === $result['success'] ) ||
					! empty( $result['redirect'] )
				);

				$payment_pending = is_array( $result ) && 'pending' === ( $result['payment_status'] ?? '' );
				if ( ! $payment_pending ) {
					do_action( 'ecommerce_after_payment_completed', $order, $result );
				}

				// Only trigger order creation hook after confirmed payment or for free orders
				// Check if payment requires external redirect ( like PayPal, etc. ).
				$has_redirect = is_array( $result ) && ! empty( $result['redirect'] );

				// Payment is confirmed if:
				// 1. Order doesn't need payment (free order)
				// 2. Order status is completed or processing (immediate payment confirmed)
				// 3. Result indicates success without redirect (direct payment).
				$order_status      = $order->get_status();
				$payment_confirmed = ! $order->needs_payment()
					|| in_array( $order_status, array( 'completed', 'processing' ), true )
					|| ( ! $has_redirect && (
						( is_array( $result ) && isset( $result['result'] ) && 'success' === $result['result'] )
						|| ( isset( $result['success'] ) && true === $result['success'] )
					) );

				if ( $payment_pending ) {
					$payment_confirmed = false;
				}

				if ( $payment_confirmed ) {
					do_action( 'ohmylms_checkout_after_create_order', $order, $posted_data );
				}

				// If payment was successful, and it's a membership, create the subscription
				// Only for confirmed payments, not for pending/awaiting redirects.
				if ( $payment_confirmed ) {
					if ( ! empty( $posted_data['membership_id'] ) && class_exists( 'OhMyLMS\Commerce\SubscriptionManager' ) ) {
						$membership = ohmylms_get_membership( $posted_data['membership_id'] );
						if ( $membership && 'one_time' !== $membership->get_subscription_period() ) {
							$gateway_meta_data = isset( $result['gateway_meta'] ) && is_array( $result['gateway_meta'] ) ? $result['gateway_meta'] : array();
							$subscription_id   = SubscriptionManager::create_subscription( $order, $posted_data['membership_id'], $gateway_meta_data );
							if ( is_wp_error( $subscription_id ) ) {
								$order->add_order_note( sprintf( /* translators: %s: text value. */
								__( 'Automated subscription creation failed. Error: %s', 'ohmylms' ), $subscription_id->get_error_message() ) );
							} elseif ( $subscription_id ) {
								SubscriptionManager::mark_subscription_active( $subscription_id );
								$order->add_order_note( sprintf( /* translators: %1$d: number; %2$s: text value. */
								__( 'Subscription #%1$d created and activated for membership: %2$s.', 'ohmylms' ), $subscription_id, get_the_title( $posted_data['membership_id'] ) ) );
							}
						}
					}
				}
				$this->set_student_to_order( $order, $student_id );
				$this->process_enrollment( $order_id, $posted_data );
				if ( ! $payment_pending ) {
					do_action( 'ohmylms_after_checkout_process', $order );
				}
				update_post_meta( $order_id, '_student_ip_address', $user_ip );
				update_post_meta( $order_id, '_student_user_agent', $user_agent );

				if ( $payment_pending && 'qpay' === ( $result['payment_method'] ?? '' ) ) {
					update_post_meta( $order_id, '_qpay_checkout_ready', 1 );
					// Finish an early verified callback even if the buyer closes the browser.
					// This reads local verification only and never polls QPay's API.
					\OhMyLMS\Commerce\Gateways\QPay\PaymentService::settle(
						$order_id,
						ecommerce()->gateways()->get_payment_gateways()['qpay']
					);
				}

				// Check for funnel processing after successful payment.
				$funnel_result = $payment_pending ? array() : $this->maybe_process_funnel( $order_id, $posted_data, $result );

				if ( isset( $funnel_result['result'] ) && 'success' === $funnel_result['result'] ) {
					$funnel_result['order_id'] = $order_id;
					$funnel_result['success']  = true;
					$result                    = $funnel_result;

				} elseif ( isset( $funnel_result['success'] ) && true === $funnel_result['success'] ) {
					$result = $funnel_result;
				}

				$result = apply_filters( 'ohmylms_checkout_process_result', $result, $order_id, $posted_data );
				if ( ! $payment_pending ) {
					\ohmylms_empty_cart();
				}

				// Send JSON response if we have a valid result ( success or redirect )
				// This ensures payment gateway redirects work even if error notices were added.
				if ( $has_valid_result ) {
					wp_send_json( $result );
				}
			}

			// Only show failure response if we don't have a valid result.
			if ( 0 !== ohmylmse_notice_count( 'error' ) || ! isset( $has_valid_result ) || ! $has_valid_result ) {
				$this->send_ajax_failure_response();
			}
		} catch ( \Exception $e ) {
			ohmylmse_add_notice( $e->getMessage(), 'error' );
			$this->send_ajax_failure_response();
		}
	}

	/**
	 * Buy a point-priced cart through {@see \OhMyLMS\Engagement\PointPurchase} (one transaction:
	 * balance check, points order, debit and enrolments) and answer the checkout request.
	 *
	 * @param int   $student_id Buyer.
	 * @param array $cart_data  Cart contents (already checked by `Reward::valid_point_cart()`).
	 * @return void
	 */
	protected function process_point_checkout( $student_id, $cart_data ) {
		$course_ids = array();
		foreach ( $cart_data as $cart_item ) {
			$course_ids[] = (int) ( $cart_item['course_id'] ?? $cart_item['id'] ?? 0 );
		}
		$bought = \OhMyLMS\Engagement\PointPurchase::purchase( $student_id, $course_ids );
		if ( is_wp_error( $bought ) ) {
			ohmylmse_add_notice( $bought->get_error_message(), 'error', array() );
			$this->send_ajax_failure_response();
			return;
		}
		$order = ohmylms_ecommerce_get_order( $bought['order_id'] );
		do_action( 'ohmylms_checkout_after_create_order', $order, array( 'payment_method' => \OhMyLMS\Engagement\PointPurchase::PAYMENT_METHOD ) );
		do_action( 'ohmylms_after_checkout_process', $order );
		\ohmylms_empty_cart();
		wp_send_json(
			array(
				'result'   => 'success',
				'order_id' => $bought['order_id'],
				'redirect' => $order->get_checkout_redirect_url(),
			)
		);
	}

	/**
	 * Maybe purchase by point.
	 * This function checks if the course is being purchased using points.
	 *
	 * @param mixed $cart_items Cart items.
	 * @return bool True if the course is purchased by points, false otherwise.
	 *
	 * @since 1.0.0
	 */
	protected function maybe_purchase_by_point( $cart_items ) {
		if ( empty( $cart_items ) || ! is_array( $cart_items ) ) {
			return false;
		}
		foreach ( $cart_items as $cart_item ) {
			if ( isset( $cart_item['purchase_by'] ) && 'point' === $cart_item['purchase_by'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get total points from the cart items.
	 *
	 * @param array $cart_items The cart items to check.
	 * @return int The total points from the cart items.
	 *
	 * @since 1.0.0
	 */
	protected function get_total_points( $cart_items ) {
		$total_points = 0;
		if ( empty( $cart_items ) || ! is_array( $cart_items ) ) {
			return $total_points;
		}
		foreach ( $cart_items as $cart_item ) {
			if ( isset( $cart_item['purchase_by'] ) && 'point' === $cart_item['purchase_by'] ) {
				if ( isset( $cart_item['course_id'] ) ) {
					$course = ohmylms_get_course( $cart_item['course_id'] );
					if ( $course && $course->get_purchase_point() ) {
						$total_points += $course->get_purchase_point();
					}
				}
			}
		}
		return $total_points;
	}

	/**
	 * Send an AJAX failure response.
	 *
	 * This function sends a JSON response indicating a failure, including any notices and session data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function send_ajax_failure_response() {
		if ( wp_doing_ajax() ) {
			$messages = ohmylmse_print_notices( true );
			$response = array(
				'result'  => 'failure',
				'message' => isset( $messages ) ? $messages : '',
				'refresh' => isset( ecommerce()->session->refresh_totals ),
				'reload'  => isset( ecommerce()->session->reload_checkout ),
			);
			wp_send_json( $response );
		}
	}

	/**
	 * Check if funnel processing is needed and handle it.
	 *
	 * @param int   $order_id The order ID.
	 * @param array $posted_data The posted checkout data.
	 * @param array $payment_result The payment processing result.
	 * @return array|null Funnel redirect result or null if no funnel needed.
	 * @since 1.0.0
	 */
	private function maybe_process_funnel( $order_id, $posted_data, $payment_result ) {
		// Use our FunnelManager class if it exists.
		if ( class_exists( '\OhMyLMS\Integrations\Funnel\Includes\FunnelManager' ) ) {
			$funnel_manager = new \OhMyLMS\Integrations\Funnel\Includes\FunnelManager();
			return $funnel_manager->maybe_process_funnel( $order_id, $posted_data, $payment_result );
		}
		return $payment_result;
	}

	/**
	 * Checks if the admin is allowed to enroll in courses.
	 *
	 * @since 1.0.0
	 */
	public function is_admin_allow_to_enroll() {
		/**
		 * Retrieves the value of the 'ohmylms_allow_admin_to_enroll' option and applies the 'ohmylms_allow_admin_to_enroll' filter.
		 *
		 * @return bool Returns true if the 'ohmylms_allow_admin_to_enroll' option is set to 'yes', false otherwise.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_allow_admin_to_enroll', 'yes' === get_option( 'ohmylms_allow_admin_to_enroll' ) );
	}

	/**
	 * Validate enrollment
	 *
	 * @param mixed $cart_items Cart items.
	 * @return array
	 * @since 1.0.0
	 */
	public static function validate_enrollment( $cart_items ): array {
		foreach ( $cart_items as $cart_item ) {
			if ( isset( $cart_item['course_id'] ) ) {
				$course_id    = (int) $cart_item['course_id'];
				$user_id      = get_current_user_id();
				$enrolled     = UserHelper::is_user_enrolled( $course_id, $user_id );
				$course_title = esc_html( get_the_title( $course_id ) );
				if ( $enrolled ) {
					return array(
						'status'  => 'error',
						// Translators: %s is replaced with the course title.
						'message' => sprintf( __( 'Already enrolled in %s', 'ohmylms' ), $course_title ),
					);
				}
			}
		}

		return array(
			'status'  => 'success',
			'message' => __( 'Enrollment allowed.', 'ohmylms' ),
		);
	}


	/**
	 * Handle enrollment after payment
	 *
	 * @param mixed $order_id Order identifier.
	 * @param mixed $order_items Order items.
	 * @return array|null
	 * @since 1.0.0
	 */
	public static function handle_enrollment_after_payment( $order_id, $order_items ) {
		$response = null;
		foreach ( $order_items as $order_item ) {
			if ( $order_item['course_id'] || isset( $order_item['id'] ) !== null ) {
				if ( ! empty( $order_item['course_id'] ) ) {
					$course_id = (int) $order_item['course_id'];
				} else {
					$course_id = (int) $order_item['id'];
				}

				$response = self::attempt_course_enrollment( $course_id, $order_id );

				if ( 'error' == $response['status'] ) {
					ohmylms_add_notice( $response['message'], $response['status'] );
					return $response;
				}
			}
		}

		return $response;
	}

	/**
	 * Attempt course enrollment
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $order_id Order identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function attempt_course_enrollment( $course_id, $order_id ): array {

		$user_id = get_current_user_id();

		$enrolled = UserHelper::is_user_enrolled( $course_id, $user_id );

		if ( $enrolled ) {
			return array(
				'status'  => 'success',
				'message' => __( 'Already enrolled.', 'ohmylms' ),
			);
		}

		$data = array(
			'course_id'      => $course_id,
			'user_id'        => $user_id,
			'created_at'     => current_time( 'mysql' ),
			'created_at_gmt' => current_time( 'mysql', 1 ),
			'updated_at'     => current_time( 'mysql' ),
			'updated_at_gmt' => current_time( 'mysql', 1 ),
			'created_by'     => $user_id,
			'updated_by'     => $user_id,
		);

		return UserRepository::insert_new_enrollment( $data );
	}

	/**
	 * Validate the checkout data.
	 *
	 * This function validates the posted checkout data and checks the cart items.
	 * It also ensures that the terms and conditions are accepted if required.
	 *
	 * @param array     $data The posted checkout data.
	 * @param \WP_Error $errors The error object to store validation errors.
	 * @param bool      $maybe_by_point Maybe by point.
	 * @return void
	 * @since 1.0.0
	 */
	protected function validate_checkout( &$data, &$errors, $maybe_by_point = false ) {
		$this->validate_posted_data( $data, $errors );
		$this->check_cart_items();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $data['terms'] ) && ! empty( $data['terms-field'] ) ) {
			$errors->add( 'terms', __( 'Please read and accept the terms and conditions to proceed with your order.', 'ohmylms' ) );
		}

		if ( ! $maybe_by_point && ecommerce()->cart->needs_payment() ) {
			$available_gateways = ecommerce()->gateways()->get_available_payment_gateways();

			if ( ! isset( $available_gateways[ $data['payment_method'] ] ) ) {
				$errors->add( 'payment', __( 'Invalid payment method.', 'ohmylms' ) );
			} else {
				$chosen_gateway = $available_gateways[ $data['payment_method'] ];
				$chosen_gateway->validate_fields();
				$this->validate_gateway_required_fields( $chosen_gateway, $data, $errors );
			}
		}

		/**
		 * Fires after the checkout validation process.
		 *
		 * @param array    $data   The posted checkout data.
		 * @param \WP_Error $errors The error object containing validation errors.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_checkout_validation', $data, $errors );
	}


	/**
	 * Validate the checkout fields the selected gateway marks as required.
	 *
	 * Lets each gateway (core, pro, or a third-party custom gateway) declare that a
	 * normally-optional field - phone being the common case - must be filled in when
	 * that gateway is chosen. A field the admin has hidden cannot be enforced.
	 *
	 * @param \OhMyLMS\Commerce\Abstracts\PaymentGateway $gateway The chosen gateway.
	 * @param array                                      $data Posted checkout data.
	 * @param \WP_Error                                  $errors Error bag.
	 * @return void
	 * @since 1.0.0
	 */
	protected function validate_gateway_required_fields( $gateway, $data, &$errors ) {
		if ( ! is_object( $gateway ) || ! method_exists( $gateway, 'get_required_checkout_fields' ) ) {
			return;
		}

		$required_keys = $gateway->get_required_checkout_fields();
		if ( empty( $required_keys ) ) {
			return;
		}

		$checkout_fields = $this->get_checkout_fields();

		foreach ( $required_keys as $required_key ) {
			// Find the field's label across all fieldsets. If it isn't on the form
			// (e.g. admin hid the phone field) there is nothing to enforce.
			$field_label = null;
			foreach ( $checkout_fields as $fieldset ) {
				if ( isset( $fieldset[ $required_key ] ) ) {
					$field_label = ! empty( $fieldset[ $required_key ]['label'] ) ? $fieldset[ $required_key ]['label'] : $required_key;
					break;
				}
			}

			if ( null === $field_label ) {
				continue;
			}

			if ( isset( $data[ $required_key ] ) && '' !== trim( (string) $data[ $required_key ] ) ) {
				continue;
			}

			$errors->add(
				$required_key . '_required',
				sprintf(
					/* translators: %s: field label */
					__( '%s is a required field for the selected payment method.', 'ohmylms' ),
					'<strong>' . esc_html( $field_label ) . '</strong>'
				),
				array( 'id' => $required_key )
			);
		}
	}


	/**
	 * Check the items in the cart.
	 *
	 * This function triggers the 'ohmylms_check_cart_items' action to allow
	 * custom validation or processing of cart items.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function check_cart_items() {
		do_action( 'ohmylms_check_cart_items' );
	}


	/**
	 * Validate the posted data.
	 *
	 * This function validates the posted data from the checkout form and adds any errors to the provided WP_Error object.
	 *
	 * @param array     $data The posted data from the checkout form.
	 * @param \WP_Error $errors The error object to store validation errors.
	 * @return void
	 * @since 1.0.0
	 */
	protected function validate_posted_data( &$data, &$errors ) {
		foreach ( $this->get_checkout_fields() as $fieldset_key => $fieldset ) {
			$validate_fieldset = true;
			foreach ( $fieldset as $key => $field ) {
				if ( ! isset( $data[ $key ] ) ) {
					continue;
				}
				$required    = ! empty( $field['required'] );
				$format      = array_filter( isset( $field['validate'] ) ? (array) $field['validate'] : array() );
				$field_label = isset( $field['label'] ) ? $field['label'] : '';

				switch ( $fieldset_key ) {
					case 'billing':
						/* translators: %s: field name */
						$field_label = sprintf( _x( 'Billing %s', 'checkout-validation', 'ohmylms' ), $field_label );
						break;
				}

				if ( in_array( 'email', $format, true ) && '' !== $data[ $key ] ) {
					$email_is_valid = is_email( $data[ $key ] );
					$data[ $key ]   = sanitize_email( $data[ $key ] );

					if ( $validate_fieldset && ! $email_is_valid ) {
						/* translators: %s: email address */
						$errors->add( $key . '_validation', sprintf( __( '%s is not a valid email address.', 'ohmylms' ), '<strong>' . esc_html( $field_label ) . '</strong>' ), array( 'id' => $key ) );
						continue;
					}
				}

				if ( $validate_fieldset && $required && '' === $data[ $key ] ) {
					/* translators: %s: field name */
					$errors->add( $key . '_required', sprintf( __( '%s is a required field.', 'ohmylms' ), '<strong>' . esc_html( $field_label ) . '</strong>', $field_label, $key ), array( 'id' => $key ) );
				}
			}
		}
	}


	/**
	 * Retrieve posted data from the checkout form.
	 *
	 * This function iterates over the checkout fields and collects the data
	 * submitted via the POST request.
	 *
	 * @return array An associative array of sanitized posted data.
	 * @since 1.0.0
	 */
	public function get_posted_data() {
		$data = array(
			'payment_method' => isset( $_POST['payment_method'] ) ? wp_unslash( $_POST['payment_method'] ) : '',
			'createaccount'  => (int) (bool) $this->is_registration_enabled(),
		);

		if ( ! empty( $_POST['membership_id'] ) ) {
			$data['membership_id'] = ohmylms_clean( wp_unslash( $_POST['membership_id'] ) );
		}

		foreach ( $this->get_checkout_fields() as $fieldset_key => $fieldset ) {
			foreach ( $fieldset as $field_key => $field ) {
				if ( ! isset( $_POST[ $field_key ] ) ) {
					continue;
				}
				$data[ $field_key ] = ohmylms_clean( wp_unslash( $_POST[ $field_key ] ) );
			}
		}
		return $data;
	}


	/**
	 * Update session data
	 *
	 * @param mixed $posted_data Posted data.
	 * @return void
	 * @since 1.0.0
	 */
	public function update_session( $posted_data ): void {
		// Update payment method.
		if ( isset( $posted_data['payment_method'] ) ) {
			ecommerce()->session->set( 'payment_method', $posted_data['payment_method'] );
		}
	}

	/**
	 * Validate checkout
	 *
	 * @param mixed $posted_data Posted data.
	 * @param mixed $errors Errors.
	 * @return void
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function validate_checkout_data( $posted_data, $errors ): void {
		// Validate payment method.
		if ( empty( $posted_data['payment_method'] ) ) {
			$errors->add( 'payment_method', __( 'Please select a payment method.', 'ohmylms' ) );
		}

		if ( $errors->has_errors() ) {
			throw new \Exception( $errors->get_error_message() );
		}
	}

	/**
	 * Process the student data during checkout.
	 *
	 * This function handles the creation of a new student account if the user is not logged in
	 * and registration is required or the user has opted to create an account.
	 *
	 * @param array $data The posted data from the checkout form.
	 * @throws \Exception If there is an error during student account creation.
	 *
	 * @since 1.0.0
	 */
	protected function process_student( $data ) {
		$student_id = get_current_user_id();

		if ( ! is_user_logged_in() && $this->is_allow_purchase_without_login() ) {
			$username     = ! empty( $data['email'] ) ? $data['email'] : '';
			$password     = wp_generate_password( 16, true, true );
			$display_name = '';
			if ( ! empty( $data['first_name'] ) ) {
				$display_name .= trim( $data['first_name'] ) . ' ';
			}
			if ( ! empty( $data['last_name'] ) ) {
				$display_name .= trim( $data['last_name'] );
			}
			$args       = array(
				'first_name'   => ! empty( $data['first_name'] ) ? $data['first_name'] . ' ' : '',
				'last_name'    => ! empty( $data['last_name'] ) ? $data['last_name'] : '',
				'display_name' => $display_name,
			);
			$student_id = ohmylms_create_new_student(
				$data['email'],
				$username,
				$password,
				$args
			);

			if ( is_wp_error( $student_id ) ) {
				throw new \Exception( $student_id->get_error_message() );
			}

			wp_set_current_user( $student_id );
			wp_set_auth_cookie( $student_id, true );

			// Log the user in.
			$user = get_user_by( 'ID', $student_id );

			// Manually trigger WordPress login hooks.
			do_action( 'wp_login', $user->user_login, $user );
			update_user_meta( $user->ID, '_ohmylms_last_login', current_time( 'mysql' ) );

			// Update session.
			ecommerce()->session->init_session_cookie();

			// Email the new student a link to choose a password; the generated one is never sent.
			$to           = $data['email'];
			$subject      = __( 'Your account has been created', 'ohmylms' );
			$profile_url  = ohmylms_get_account_endpoint_url( 'dashboard' );
			$student_name = '';
			if ( $user ) {
				$student_name = trim( $user->first_name . ' ' . $user->last_name );
				if ( empty( $student_name ) ) {
					$student_name = $user->display_name;
				}
			}
			$reset_key = $user ? get_password_reset_key( $user ) : '';
			$reset_url = $user && ! is_wp_error( $reset_key ) ? network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $reset_key ) . '&login=' . rawurlencode( $user->user_login ), 'login' ) : wp_lostpassword_url();

			$message = sprintf(
				/* translators: %1$s: name; %2$s: site name; %3$s: username; %4$s: set-password URL; %5$s: account URL; %6$s: account URL text; %7$s: site name. */
				__( 'Hello %1$s,<br><br>Your account has been created on %2$s.<br><br>Username: %3$s<br><br>Choose your password here: <a href="%4$s">set your password</a><br><br>You can access your courses from here: <a href="%5$s">%6$s</a><br><br>Thank you<br>%7$s', 'ohmylms' ),
				esc_html( $student_name ),
				esc_html( get_bloginfo( 'name' ) ),
				esc_html( $user ? $user->user_login : $username ),
				esc_url( $reset_url ),
				esc_url( $profile_url ),
				esc_html( $profile_url ),
				esc_html( get_bloginfo( 'name' ) )
			);

			$headers = array(
				'MIME-Version: 1.0',
				'Content-Type: text/html;
            charset = UTF-8',
			);
			wp_mail( $to, $subject, $message, $headers );
		}

		if ( $student_id && is_multisite() && is_user_logged_in() && ! is_user_member_of_blog() ) {
			add_user_to_blog( get_current_blog_id(), $student_id, function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber' );
		}

		if ( $student_id ) {
			$student = new Student( $student_id );
			if ( ! empty( $data['first_name'] ) ) {
				$student->set_first_name( $data['first_name'] );
			}
			if ( ! empty( $data['last_name'] ) ) {
				$student->set_last_name( $data['last_name'] );
			}
			if ( ! empty( $data['address'] ) ) {
				$student->set_address( $data['address'] );
			}

			if ( ! empty( $data['country'] ) ) {
				$student->set_country( $data['country'] );
			}

			if ( ! empty( $data['city'] ) ) {
				$student->set_city( $data['city'] );
			}

			if ( ! empty( $data['postcode'] ) ) {
				$student->set_postcode( $data['postcode'] );
			}

			if ( ! empty( $data['state'] ) ) {
				$student->set_state( $data['state'] );
			}

			if ( ! empty( $data['phone'] ) && method_exists( $student, 'set_phone' ) ) {
				$student->set_phone( $data['phone'] );
			}

			$student->save();

			update_user_meta( $student_id, '_is_ohmylms_student', 'yes' );
		}
		return $student_id;
	}


	/**
	 * Set student to order
	 *
	 * @param mixed $order Order.
	 * @param mixed $student_id Student identifier.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_student_to_order( $order, $student_id ) {
		$order->set_student_id( $student_id );
	}

	/**
	 * Create a new order.
	 *
	 * @param array $data The data for creating the order.
	 * @return int|\WP_Error The order ID on success, or a WP_Error on failure.
	 *
	 * @since 1.0.0
	 * @throws \Exception When the operation cannot be completed.
	 */
	public function create_order( $data ) {
		try {
			$order              = new Order();
			$cart_hash          = ecommerce()->cart->get_cart_hash();
			$available_gateways = ecommerce()->gateways()->get_available_payment_gateways();

			foreach ( $data as $key => $value ) {
				if ( is_callable( array( $order, "set_{$key}" ) ) ) {
					$order->{"set_{$key}"}( $value );
				}
			}

			$order->set_total( ecommerce()->cart->get_total( 'edit' ) );
			$order->set_currency( get_ohmylms_currency() );
			$order->set_payment_method( isset( $available_gateways[ $data['payment_method'] ] ) ? $available_gateways[ $data['payment_method'] ] : $data['payment_method'] );
			$order->set_cart_hash( $cart_hash );
			$order->set_student_id( get_current_user_id() );
			$order->set_order_version( OHMYLMS_VERSION );

			/**
			 * Fires before the order is created during checkout.
			 *
			 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
			 * @param array $data The posted checkout data.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_checkout_create_order', $order, $data );
			$this->set_data_from_cart( $order );
			$order_id = $order->save();

			// Store membership ID if this is a membership purchase.
			if ( ! empty( $data['membership_id'] ) ) {
				update_post_meta( $order_id, '_membership_id', absint( $data['membership_id'] ) );
			}

			/**
			 * Fires after the order is created during checkout.
			 *
			 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
			 * @param array $data The posted checkout data.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_checkout_order_created', $order );

			return $order_id;
		} catch ( \Exception $e ) {
			throw new \Exception( $e->getMessage() );
		}
	}

	/**
	 * Process enrollment for the given order.
	 *
	 * This function inserts enrollment records into the database for each course in the cart.
	 *
	 * @param int   $order_id The ID of the order.
	 * @param array $posted_data The posted data from the checkout form.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function process_enrollment( $order_id, $posted_data ) {

		global $wpdb;
		$enrollment_table  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$membership_table  = $wpdb->prefix . 'ohmylms_user_membership';
		$student_id        = get_current_user_id();
		$cart_items        = ecommerce()->cart->get_cart_contents();
		$order             = ohmylms_ecommerce_get_order( $order_id );
		$enrollment_status = 'pending' === $order->get_status() || 'processing' === $order->get_status() ? 'pending' : 'enrolled';
		$item_id           = 0;

		if ( ! empty( $posted_data['membership_id'] ) ) {
			$item_id            = $posted_data['membership_id'];
			$membership_details = ohmylms_get_membership( $posted_data['membership_id'] )->get_products();
			$cart_items         = $membership_details;
		}

		if ( empty( $cart_items ) ) {
			if ( ! empty( $posted_data['membership_id'] ) ) {
				$enrollment_data = array(
					'membership_id' => $posted_data['membership_id'],
					'user_id'       => $student_id,
					'order_id'      => $order_id,
					'status'        => $enrollment_status,
					'progress'      => 'running',
					'start_date'    => current_time( 'mysql' ),
				);
				if ( ! empty( $posted_data['membership_id'] ) ) {
					$subscription_id                    = get_post_meta( $order_id, '_subscription_id', true );
					$enrollment_data['subscription_id'] = $subscription_id;
				}

				if ( ohmylms_is_pro() ) {
					// Check if the record exists.
					$existing_record = $wpdb->get_var(
						$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND membership_id = %d', $membership_table, $student_id, $posted_data['membership_id'] )
					);

					if ( $existing_record ) {
						$wpdb->update(
							$membership_table,
							array(
								'order_id'   => $order_id,
								'status'     => $enrollment_status,
								'progress'   => 'running',
								'start_date' => current_time( 'mysql' ),
							),
							array(
								'user_id'       => $student_id,
								'membership_id' => $posted_data['membership_id'],
							)
						);
					} else {
						$wpdb->insert(
							$membership_table,
							$enrollment_data
						);
					}
				}
			}
		}
		if ( is_array( $cart_items ) ) {
			foreach ( $cart_items as $cart_item ) {
				if ( ! isset( $cart_item['course_id'] ) ) {
					if ( ! isset( $cart_item['id'] ) ) {
						continue;
					}
				}
				$course_id = isset( $cart_item['course_id'] ) ? (int) $cart_item['course_id'] : (int) $cart_item['id'];
				$item_id   = $course_id;

				$enrollment_data = array(
					'course_id'     => $course_id,
					'user_id'       => $student_id,
					'order_id'      => $order_id,
					'membership_id' => ! empty( $posted_data['membership_id'] ) ? $posted_data['membership_id'] : null,
					'status'        => $enrollment_status,
					'progress'      => 'running',
					'start_date'    => current_time( 'mysql' ),
				);

				// Cohort membership is kept in ohmylms_cohort_members (OhMyLMS\Cohorts), not on the enrolment row.
				$already_enrolled = (bool) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $enrollment_table WHERE user_id=%d AND course_id=%d AND status='enrolled' AND " . ( ! empty( $posted_data['membership_id'] ) ? 'membership_id=%d' : '(membership_id IS NULL OR membership_id=0)' ),
						! empty( $posted_data['membership_id'] ) ? array( $student_id, $course_id, (int) $posted_data['membership_id'] ) : array( $student_id, $course_id )
					)
				);

				if ( $course_id && ! $already_enrolled ) {
					$existing_where = array(
						'user_id'   => $student_id,
						'course_id' => $course_id,
						'order_id'  => $order_id,
					);

					$existing_record = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM $enrollment_table WHERE " . implode(
								' AND ',
								array_map(
									function ( $col ) {
										return "$col = %d";
									},
									array_keys( $existing_where )
								)
							),
							array_values( $existing_where )
						)
					);

					$origin = \OhMyLMS\Student\EnrolmentMirror::order_origin( $order_id, (int) $enrollment_data['membership_id'] );
					if ( $existing_record ) {
						// Update the existing record.
						\OhMyLMS\Student\Enrollments::update(
							array(
								'order_id'      => $order_id,
								'membership_id' => $enrollment_data['membership_id'],
								'status'        => $enrollment_status,
								'progress'      => 'running',
								'start_date'    => current_time( 'mysql' ),
							),
							$existing_where,
							$origin
						);
					} else {
						\OhMyLMS\Student\Enrollments::create( $enrollment_data, $origin );
					}

					if ( 'enrolled' === $enrollment_status ) {
						do_action( 'ohmylms_after_enrolled_student', $order_id );
					}
				}

				if ( ! empty( $posted_data['membership_id'] ) ) {
					$membership = ohmylms_get_membership( $posted_data['membership_id'] );
					if ( ! $membership->is_already_purchased() && ohmylms_is_pro() ) {
						$enrollment_data['membership_id']   = $posted_data['membership_id'];
						$enrollment_data['subscription_id'] = get_post_meta( $order_id, '_subscription_id', true );
						unset( $enrollment_data['course_id'] );

						// Check if the record exists.
						$existing_record = $wpdb->get_var(
							$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND membership_id = %d', $membership_table, $student_id, $posted_data['membership_id'] )
						);

						if ( $existing_record ) {
							$wpdb->update(
								$membership_table,
								array(
									'order_id'   => $order_id,
									'status'     => $enrollment_status,
									'progress'   => 'running',
									'start_date' => current_time( 'mysql' ),
								),
								array(
									'user_id'       => $student_id,
									'membership_id' => $posted_data['membership_id'],
								)
							);
						} else {
							$wpdb->insert(
								$membership_table,
								$enrollment_data
							);
						}
					}
				}
			}
		}

		do_action( 'ohmylms_after_enrollment_processed', $student_id, $order, $item_id );
	}


	/**
	 * Set data from the cart to the order.
	 *
	 * This function sets various data from the cart to the order object.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function set_data_from_cart( &$order ) {
		$order->set_cart_discount( ecommerce()->cart->get_totals_by_key( 'discounts_total' ) );
		$order->set_total( ecommerce()->cart->get_total( 'edit' ) );
		$order->set_tax_amount( ecommerce()->cart->get_totals_by_key( 'tax_amount' ) );
		$order->set_tax_rate( ecommerce()->cart->get_totals_by_key( 'tax_rate' ) );
		$this->create_order_coupon_lines( $order, ecommerce()->cart );
		$this->create_order_line_items( $order, ecommerce()->cart );
		$this->set_coupon_meta( $order, ecommerce()->cart );
	}


	/**
	 * Create order line items from the cart.
	 *
	 * This function iterates over the cart items and creates corresponding line items in the order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @param \OhMyLMS\Commerce\Cart       $cart The cart object.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function create_order_line_items( &$order, $cart ) {
		foreach ( $cart->get_cart() as $cart_item_key => $values ) {
			$item   = new OrderItemCourse();
			$course = $values['data'];
			$item->set_props(
				array(
					'quantity' => $values['quantity'],
					'total'    => $values['line_total'],
				)
			);

			if ( $course ) {
				$item->set_props(
					array(
						'name'      => $course->get_name(),
						'course_id' => $course->get_id(),
						'subtotal'  => apply_filters(
							'ohmylms_order_item_subtotal',
							$course->is_on_sale() && $course->validate_on_sale() ? $course->get_price( 'edit' ) : $course->get_regular_price( 'edit' ),
							$course,
							$order
						),
					)
				);
			}
			$order->add_item( $item );
		}
	}

	/**
	 * Create membership order
	 *
	 * @param mixed $posted_data Posted data.
	 * @return \WP_Error|int
	 * @since 1.0.0
	 */
	public static function create_membership_order( $posted_data ) {
		$membership_details = ohmylms_get_membership( $posted_data['membership_id'] )->get_products();
		return ecommerce()->order()->create_order( $membership_details, $posted_data );
	}

	/**
	 * Process the payment for an order.
	 *
	 * @param int   $order_id The ID of the order.
	 * @param mixed $posted_data Posted data.
	 * @return array The result of the payment process.
	 *
	 * @since 1.0.0
	 */
	public static function process_order_payment( $order_id, $posted_data ) {

		$payment_method     = $posted_data['payment_method'];
		$available_gateways = ecommerce()->gateways()->get_available_payment_gateways();

		if ( ! isset( $available_gateways[ $payment_method ] ) ) {
			return;
		}

		ecommerce()->session->set( 'order_awaiting_payment', $order_id );

		ecommerce()->session->save_data();

		$is_subscription = false;
		$membership_id   = isset( $posted_data['membership_id'] ) ? $posted_data['membership_id'] : 0;
		if ( $membership_id ) {
			$membership = ohmylms_get_membership( $membership_id );
			if ( $membership && 'one_time' !== $membership->get_subscription_period() ) {
				$is_subscription = true;
			}
		}
		$result = $available_gateways[ $payment_method ]->process_payment( $order_id, $is_subscription );

		/**
		 * Fires after the order payment is processed.
		 *
		 * @param int $order_id The ID of the order.
		 * @param array $posted_data The posted data from the checkout form.
		 * @param array $result The result of the payment process.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_order_payment', $order_id, $posted_data, $result );

		return $result;
	}


	/**
	 * Handle offline payment
	 *
	 * @param mixed $order_id Order identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function handle_offline_payment( $order_id ): array {
		$order = ecommerce()->order( $order_id );
		$order->update_order_meta( 'ohmylms_order_payment_method', 'offline_payment' );
		$order->update_order_meta( 'ohmylms_order_status', 'pending' );

		return array(
			'status'  => 'success',
			'order'   => $order_id,
			'message' => __( 'Payment confirmed.', 'ohmylms' ),
		);
	}

	/**
	 * Process an order that does not require payment.
	 *
	 * This function marks the order as complete and empties the cart.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return void
	 * @since 1.0.0
	 */
	public static function process_order_without_payment( $order ) {
		$order->payment_complete();

		return array(
			'success'  => 'success',
			'redirect' => $order->get_checkout_redirect_url(),
		);
	}


	/**
	 * Retrieve the value of a specified input field.
	 *
	 * This function checks if the input field is set in the `$_POST` array and sanitizes it.
	 * If the user is logged in and the input field is not set in `$_POST`, it retrieves the value from the current user's data.
	 *
	 * @param string $input The name of the input field to retrieve.
	 * @return string The sanitized value of the input field.
	 *
	 * @since 1.0.0
	 */
	public function get_value( $input ) {
		$value = '';
		if ( isset( $_POST[ $input ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $input ] ) );
		} elseif ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$value = $user->$input;
		}
		return $value;
	}

			/**
			 * Create order coupon lines from the cart.
			 *
			 * This function iterates over the cart coupons and creates corresponding coupon lines in the order.
			 *
			 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
			 * @param \OhMyLMS\Commerce\Cart       $cart The cart object.
			 * @return void
			 *
			 * @since 1.0.0
			 */
	private function create_order_coupon_lines( $order, $cart ) {
		foreach ( $cart->get_coupons() as $code => $coupon ) {
			$item = new OrderItemCoupon();
			$item->set_props(
				array(
					'code'     => $code,
					'discount' => $cart->get_coupon_discount_amount( $code ),
					'name'     => $coupon->get_code( 'edit' ),
				)
			);
			$order->add_item( $item );
		}
	}

	/**
	 * Set coupon meta for Checkout.
	 *
	 * @param mixed $order Order.
	 * @param mixed $cart Cart.
	 */
	private function set_coupon_meta( $order, $cart ) {
		foreach ( $cart->get_coupons() as $code => $coupon ) {
			$current_uses = get_post_meta( $coupon->get_id(), 'usage_count', true );
			$current_uses = $current_uses ? $current_uses + 1 : 1;
			update_post_meta( $coupon->get_id(), 'usage_count', $current_uses );
			$current_user_id = get_current_user_id();
			if ( $current_user_id ) {
				$current_uses_per_user = get_post_meta( $coupon->get_id(), 'usage_count_' . $current_user_id, true );
				$current_uses_per_user = $current_uses_per_user ? $current_uses_per_user + 1 : 1;
				update_post_meta( $coupon->get_id(), 'usage_count_' . $current_user_id, $current_uses_per_user );
			}
		}

		ecommerce()->session->set( 'applied_coupons', array() );
	}
}
