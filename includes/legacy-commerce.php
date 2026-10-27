<?php
/**
 * Deprecated PHP compatibility API for sites without CreatorLMS installed.
 *
 * Loaded only by ohmylms_load_legacy_commerce_api(); never load this directly.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

define( 'ECOMMERCE_MODULE_PREFIX', OHMYLMS_ECOMMERCE_MODULE_PREFIX );

// Resolve previous class names lazily, preserving normal Composer loading.
spl_autoload_register(
	static function ( $class_name ) {
		$class_aliases = array(
			'Package'              => 'OhMyLMS_Commerce_Package',
			'GatewayOffline'       => 'OhMyLMS_Gateway_Offline',
			'GatewayStripeIntents' => 'OhMyLMS_Gateway_Stripe_Intents',
			'TaxCalculator'        => 'OhMyLMS_Tax_Calculator',
			'EuVatApi'             => 'OhMyLMS_EU_VAT_API',
			'EuVatApiResponse'     => 'OhMyLMS_EU_VAT_API_Response',
		);
		$class_aliases = array_change_key_case( $class_aliases, CASE_LOWER );
		if ( 0 === stripos( $class_name, 'CodeRex\\Ecommerce\\' ) ) {
			$target_class = 'OhMyLMS\\Commerce\\' . substr( $class_name, strlen( 'CodeRex\\Ecommerce\\' ) );
		} else {
			$target_class = $class_aliases[ strtolower( $class_name ) ] ?? null;
		}
		if ( $target_class && class_exists( $target_class ) ) {
			class_alias( $target_class, $class_name );
		}
	}
);

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_is_admin_page().
 * @return mixed The result from the OhMyLMS implementation.
 */
function is_crlm_admin_page(): bool {
	return ohmylms_is_admin_page();
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_get_question_ans_by_question_id().
 * @param mixed $question_id Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function crator_lms_get_question_ans_by_question_id( $question_id ) {
	return ohmylms_get_question_ans_by_question_id( $question_id );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_if_theme_support_available().
 * @return mixed The result from the OhMyLMS implementation.
 */
function if_theme_support_available() {
	return ohmylms_if_theme_support_available();
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_get_best_selling_course_ids().
 * @param mixed $category Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function get_best_selling_course_ids( $category = null ) {
	return ohmylms_get_best_selling_course_ids( $category );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_get_top_rated_course_ids().
 * @param mixed $category Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function get_top_rated_course_ids( $category = null ) {
	return ohmylms_get_top_rated_course_ids( $category );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_get_top_reviewed_course_ids().
 * @param mixed $category Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function get_top_reviewed_course_ids( $category = null ) {
	return ohmylms_get_top_reviewed_course_ids( $category );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_get_free_course_ids().
 * @param mixed $category Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function get_free_course_ids( $category = null ) {
	return ohmylms_get_free_course_ids( $category );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_get_paid_course_ids().
 * @param mixed $category Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function get_paid_course_ids( $category = null ) {
	return ohmylms_get_paid_course_ids( $category );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_get_recent_course_ids().
 * @param mixed $category Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function get_recent_course_ids( $category = null ) {
	return ohmylms_get_recent_course_ids( $category );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_coupon_id_by_code().
 * @param mixed $code Original argument.
 * @param mixed $exclude Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_coupon_id_by_code( $code, $exclude = 0 ) {
	return ohmylms_ecommerce_get_coupon_id_by_code( $code, $exclude );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_string_to_timestamp().
 * @param mixed $time_string Original argument.
 * @param mixed $from_timestamp Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_string_to_timestamp( $time_string, $from_timestamp = null ) {
	return ohmylms_ecommerce_string_to_timestamp( $time_string, $from_timestamp );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_timezone_offset().
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_timezone_offset() {
	return ohmylms_ecommerce_timezone_offset();
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_timezone_string().
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_timezone_string() {
	return ohmylms_ecommerce_timezone_string();
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_format_refund_total().
 * @param mixed $amount Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_format_refund_total( $amount ) {
	return ohmylms_ecommerce_format_refund_total( $amount );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_format_coupon_code().
 * @param mixed $code Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_format_coupon_code( $code ) {
	return ohmylms_ecommerce_format_coupon_code( $code );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_string_to_datetime().
 * @param mixed $time_string Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_string_to_datetime( $time_string ) {
	return ohmylms_ecommerce_string_to_datetime( $time_string );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_membership().
 * @param mixed $membership_id Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_membership( $membership_id ) {
	return ohmylms_ecommerce_get_membership( $membership_id );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_order().
 * @param mixed $order_id Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_order( $order_id ) {
	return ohmylms_ecommerce_get_order( $order_id );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_refund_order().
 * @param mixed $order_id Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_refund_order( $order_id ) {
	return ohmylms_ecommerce_get_refund_order( $order_id );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_create_refund().
 * @param mixed $args Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_create_refund( $args = array() ) {
	return ohmylms_ecommerce_create_refund( $args );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_refund_payment().
 * @param mixed $order Original argument.
 * @param mixed $amount Original argument.
 * @param mixed $reason Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_refund_payment( $order, $amount, $reason = '' ) {
	return ohmylms_ecommerce_refund_payment( $order, $amount, $reason );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_order_statuses().
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_order_statuses() {
	return ohmylms_ecommerce_get_order_statuses();
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_order_status_name().
 * @param mixed $status Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_order_status_name( $status ) {
	return ohmylms_ecommerce_get_order_status_name( $status );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_order_notes().
 * @param mixed $args Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_order_notes( $args ) {
	return ohmylms_ecommerce_get_order_notes( $args );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_order_note().
 * @param mixed $data Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_order_note( $data ) {
	return ohmylms_ecommerce_get_order_note( $data );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_page_endpoint_title().
 * @param mixed $title Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_page_endpoint_title( $title ) {
	return ohmylms_ecommerce_page_endpoint_title( $title );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_endpoint_url().
 * @param mixed $endpoint Original argument.
 * @param mixed $value Original argument.
 * @param mixed $permalink Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {
	return ohmylms_ecommerce_get_endpoint_url( $endpoint, $value, $permalink );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_rest_prepare_date_response().
 * @param mixed $date Original argument.
 * @param mixed $utc Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_rest_prepare_date_response( $date, $utc = true ) {
	return ohmylms_ecommerce_rest_prepare_date_response( $date, $utc );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_create_new_student_username().
 * @param mixed $email Original argument.
 * @param mixed $new_user_args Original argument.
 * @param mixed $suffix Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function creatot_lms_create_new_student_username( $email, $new_user_args = array(), $suffix = '' ) {
	return ohmylms_create_new_student_username( $email, $new_user_args, $suffix );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_subscription().
 * @param mixed $subscription_id Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_subscription( $subscription_id ) {
	return ohmylms_ecommerce_get_subscription( $subscription_id );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_subscription_notes().
 * @param mixed $args Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_subscription_notes( $args ) {
	return ohmylms_ecommerce_get_subscription_notes( $args );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_subscription_note().
 * @param mixed $data Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_subscription_note( $data ) {
	return ohmylms_ecommerce_get_subscription_note( $data );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use ohmylms_ecommerce_get_subscription_info().
 * @param mixed $membership_id Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce_get_subscription_info( $membership_id ) {
	return ohmylms_ecommerce_get_subscription_info( $membership_id );
}

require_once __DIR__ . '/legacy-commerce-functions.php';
