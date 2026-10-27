<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * FunnelManager Class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Integrations\Funnel\Includes;

/**
 * FunnelManager Class
 *
 * Handles one-click offer funnel processing after successful payments.
 *
 * @since 1.0.0
 */
class FunnelManager {

	/**
	 * Check if funnel processing is needed and handle it.
	 *
	 * @param int   $order_id The order ID.
	 * @param array $posted_data The posted checkout data.
	 * @param array $payment_result The payment processing result.
	 * @return array|null Funnel redirect result or null if no funnel needed.
	 * @since 1.0.0
	 */
	public function maybe_process_funnel( $order_id, $posted_data, $payment_result ) {

		// Check if the order is with supported payment methods.
		if ( ! $this->is_supported_payment_method( $order_id ) ) {
			return false;
		}

		// Only process funnel for successful payments.
		if ( ! $this->is_payment_successful( $payment_result ) ) {
			return false;
		}

		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$payment_method = $order->get_payment_method();
		if ( 'paypal' === $payment_method ) {
			return $payment_result;
		}

		/**
		 * Allow other plugins to hook into funnel processing.
		 *
		 * @param int $order_id The order ID.
		 * @param array $posted_data The posted checkout data.
		 * @param array $payment_result The payment processing result.
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_process_funnel', $order_id, $posted_data, $payment_result );

		// Check if we have funnel steps configured for this order.
		$funnel_course = $this->find_funnel_course( $order_id );
		if ( ! $funnel_course ) {
			return $payment_result;
		}

		// Get the first funnel step.
		$first_funnel_step = $this->get_first_funnel_step( $funnel_course );

		if ( ! $first_funnel_step ) {
			return $payment_result;
		}

		// Store funnel session data.
		$funnel_session_data = array(
			'order_id'     => $order_id,
			'course_id'    => $funnel_course,
			'current_step' => $first_funnel_step['step_id'],
			'step_data'    => $first_funnel_step,
			'created_at'   => time(),
		);
		$this->store_funnel_session( $funnel_session_data );

		// Build funnel step URL.
		$funnel_url = $this->build_funnel_step_url( $order_id, $first_funnel_step );

		// Return redirect to funnel step instead of thank you page.
		return array(
			'result'   => 'success',
			'redirect' => $funnel_url,
			'order_id' => $order_id,
			'success'  => true,
			'funnel'   => true,
		);
	}

	/**
	 * Check if payment was successful.
	 *
	 * @param array $payment_result The payment processing result.
	 * @return bool True if payment was successful.
	 * @since 1.0.0
	 */
	private function is_payment_successful( $payment_result ) {
		return ( isset( $payment_result['result'] ) && 'success' === $payment_result['result'] ) ||
				( isset( $payment_result['success'] ) && true === $payment_result['success'] );
	}

	/**
	 * Check if the order is with supported payment methods.
	 *
	 * @param int $order_id The order ID.
	 * @return bool True if the order has a supported payment method.
	 * @since 1.0.0
	 */
	private function is_supported_payment_method( $order_id ) {
		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$payment_method    = $order->get_payment_method();
		$supported_methods = array( 'paypal', 'stripe' );

		return in_array( $payment_method, $supported_methods, true );
	}

	/**
	 * Find a course that has funnel steps configured from the order.
	 *
	 * @param int $order_id The order ID.
	 * @return int|null Course ID with funnel or null.
	 * @since 1.0.0
	 */
	private function find_funnel_course( $order_id ) {
		$course_ids = $this->get_order_courses( $order_id );
		foreach ( $course_ids as $course_id ) {
			$funnel_steps = get_post_meta( $course_id, '_funnel_steps', true );

			if ( ! empty( $funnel_steps ) && is_array( $funnel_steps ) ) {
				return $course_id;
			}
		}

		return null;
	}

	/**
	 * Get courses from order.
	 *
	 * @param int $order_id The order ID.
	 * @return array Array of course IDs.
	 * @since 1.0.0
	 */
	private function get_order_courses( $order_id ) {
		if ( ! function_exists( 'ohmylms_ecommerce_get_order' ) ) {
			return array();
		}

		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $order ) {
			return array();
		}

		$courses     = array();
		$order_items = $order->get_items();

		foreach ( $order_items as $item ) {
			if ( method_exists( $item, 'get_course_id' ) ) {
				$course_id = $item->get_course_id();
				if ( $course_id ) {
					$courses[] = $course_id;
				}
			}
		}

		return $courses;
	}

	/**
	 * Get the first funnel step for a course.
	 *
	 * @param int $course_id The course ID.
	 * @return array|null First funnel step or null.
	 * @since 1.0.0
	 */
	private function get_first_funnel_step( $course_id ) {
		$funnel_steps = get_post_meta( $course_id, '_funnel_steps', true );

		if ( empty( $funnel_steps ) || ! is_array( $funnel_steps ) ) {
			return null;
		}

		// Return the first step.
		return reset( $funnel_steps );
	}

	/**
	 * Store funnel session data.
	 *
	 * @param array $data Funnel data to store.
	 * @return void
	 * @since 1.0.0
	 */
	private function store_funnel_session( $data ) {
		if ( ! function_exists( '\OhMyLMS\Commerce\ecommerce' ) ) {
			return;
		}

		\OhMyLMS\Commerce\ecommerce()->session->set( 'funnel_data', $data );
		\OhMyLMS\Commerce\ecommerce()->session->save_data();
	}

	/**
	 * Build funnel step URL.
	 *
	 * @param int   $order_id The order ID.
	 * @param array $funnel_step The funnel step data.
	 * @return string The funnel step URL.
	 * @since 1.0.0
	 */
	public function build_funnel_step_url( $order_id, $funnel_step ) {
		// Try pretty URLs first, fallback to query parameters.
		$step_number = $funnel_step['step_id'] ?? 'step_1';

		// Remove 'step_' prefix if it exists to get just the number.
		if ( strpos( $step_number, 'step_' ) === 0 ) {
			$step_number = substr( $step_number, 5 );
		}

		// Try pretty URL format first.
		return home_url( sprintf( '/post-checkout-funnel/order/%d/step/%s', $order_id, $step_number ) );
	}

	/**
	 * Get funnel session data.
	 *
	 * @return array|null Funnel session data or null.
	 * @since 1.0.0
	 */
	public static function get_funnel_session() {
		if ( ! function_exists( '\OhMyLMS\Commerce\ecommerce' ) ) {
			return null;
		}

		$session_data = \OhMyLMS\Commerce\ecommerce()->session->get( 'funnel_data' );
		return $session_data;
	}

	/**
	 * Clear funnel session data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function clear_funnel_session() {
		if ( ! function_exists( '\OhMyLMS\Commerce\ecommerce' ) ) {
			return;
		}

		\OhMyLMS\Commerce\ecommerce()->session->set( 'funnel_data', null );
		\OhMyLMS\Commerce\ecommerce()->session->save_data();
	}

	/**
	 * Process order funnel (for compatibility with ohmylms-pro).
	 *
	 * @param int   $order_id The order ID.
	 * @param array $posted_data The posted checkout data.
	 * @return array|null Funnel redirect result or null if no funnel needed.
	 * @since 1.0.0
	 */
	public function process_order_funnel( $order_id, $posted_data ) {
		// This method is for compatibility with ohmylms-pro funnel system
		// We need to create a dummy payment result for the maybe_process_funnel method.
		$payment_result = array(
			'result'  => 'success',
			'success' => true,
		);

		return $this->maybe_process_funnel( $order_id, $posted_data, $payment_result );
	}
}
