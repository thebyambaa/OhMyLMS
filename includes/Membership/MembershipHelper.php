<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Responsible to handle all course related calculations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Membership;

/**
 * Responsible to handle all course related calculations
 *
 * @since 1.0.0
 */
class MembershipHelper {

	/**
	 * Save membership plan details
	 *
	 * @param mixed $membership_id Membership identifier.
	 * @param mixed $membership_plans Membership plans.
	 * @return array
	 * @since 1.0.0
	 */
	public static function save_membership_details( $membership_id, $membership_plans ): array {

		/**
		 * Fires before membership details save
		 *
		 * @param number $membership_id membership id
		 * @param array $membership_plans membership plans
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_save_membership_details', $membership_id, $membership_plans );

		$membership_id = (int) sanitize_text_field( $membership_id );

		if ( is_array( $membership_plans ) ) {
			foreach ( $membership_plans as &$plan ) {

				$plan['plan_id'] = null != $plan['plan_id'] ? sanitize_text_field( $plan['plan_id'] ) : uniqid( 'plan-' );
				$plan['title']   = sanitize_text_field( $plan['title'] );
				$plan['price']   = sanitize_text_field( $plan['price'] );
			}
		}

		update_post_meta( $membership_id, 'ohmylms_membership_plans', $membership_plans );

		/**
		 * Fires after membership details save
		 *
		 * @param number $membership_id membership id
		 * @param array $membership_plans membership plans
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_save_membership_details', $membership_id, $membership_plans );

		return array(
			'status'  => 'success',
			'message' => __( 'Successfully saved membership details', 'ohmylms' ),
		);
	}

	/**
	 * Get courses for membership plan
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_courses_for_membership_plans(): array {
		$courses = get_posts(
			array(
				'post_type'   => 'ohmylms-course',
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);

		$courses_data = array();
		foreach ( $courses as $course ) {
			$courses_data[] = array(
				'id'    => $course->ID,
				'title' => $course->post_title,
			);
		}

		return $courses_data;
	}

	/**
	 * Subscription options
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function subscription_options() {
		return array(
			'monthly'  => __( 'Monthly', 'ohmylms' ),
			'yearly'   => __( 'yearly', 'ohmylms' ),
			'lifetime' => __( 'Lifetime', 'ohmylms' ),
		);
	}

	/**
	 * Get subscription option by key
	 *
	 * @param mixed $key Lookup key.
	 * @return string|null
	 * @since 1.0.0
	 */
	public static function get_subscription_options_by_key( $key ) {
		$options = self::subscription_options();
		if ( array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}
		return null;
	}

	/**
	 * Get plan details
	 *
	 * @param mixed $membership_id Membership identifier.
	 * @param mixed $plan_id Plan identifier.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public static function get_plan_details( $membership_id, $plan_id ) {
		$membership_details = get_post_meta( $membership_id, 'ohmylms_membership_plans', true );
		foreach ( $membership_details as $index => $plan_details ) {
			if ( $plan_details['plan_id'] == $plan_id ) {
				return $plan_details;
			}
		}

		return null;
	}

	/**
	 * Get plan price
	 *
	 * @param mixed $membership_id Membership identifier.
	 * @return float|null
	 * @since 1.0.0
	 */
	public static function get_plan_price( $membership_id ): ?float {
		$price = get_post_meta( $membership_id, 'ohmylms_membership_regular_price', true );

		if ( $price ) {
			return number_format( $price, 2 );
		}
		return null;
	}

	/**
	 * Billing interval constants
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	public static function billing_interval_constants(): array {
		return array(
			'monthly' => 'MONTH',
			'weekly'  => 'WEEK',
			'yearly'  => 'YEAR',
		);
	}

	/**
	 * Get interval unit by frequency
	 *
	 * @param mixed $frequency Frequency.
	 * @return string|null
	 * @since 1.0.0
	 */
	public static function get_interval_unit_by_frequency( $frequency ): ?string {
		$billing_intervals = self::billing_interval_constants();
		if ( array_key_exists( $frequency, $billing_intervals ) ) {
			return $billing_intervals[ $frequency ];
		}
		return null;
	}

	/**
	 * Generate billing cycles
	 *
	 * @param mixed $order Order.
	 * @return array[]
	 * @since 1.0.0
	 */
	public static function generate_billing_cycles( $order ): array {
		$billing_period = get_post_meta( $order->id, 'ohmylms_membership_billing_period', true );
		$interval_unit  = self::get_interval_unit_by_frequency( $billing_period );

		return array(
			array(
				'frequency'      => array(
					'interval_unit'  => $interval_unit,
					'interval_count' => 1,
				),
				'tenure_type'    => 'REGULAR',
				'sequence'       => 1,
				'total_cycles'   => 0, // 0 means infinite cycles
				'pricing_scheme' => array(
					'fixed_price' => array(
						'value'         => number_format( $order->amount, 2 ),
						'currency_code' => get_ohmylms_currency(),
					),
				),
			),
		);
	}

	/**
	 * Get payment preference
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_payment_preference(): array {
		return array(
			'auto_bill_outstanding'     => true,
			'setup_fee'                 => array(
				'value'         => '0',
				'currency_code' => get_ohmylms_currency(),
			),
			'setup_fee_failure_action'  => 'CANCEL',
			'payment_failure_threshold' => 3,
		);
	}
}
