<?php
/**
 * Subscription functions.
 *
 * @package OhMyLMS
 */


/**
 * Get subscription.
 *
 * @param int $subscription_id Subscription ID.
 * @return \OhMyLMS\Commerce\Data\Subscription Subscription object.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_get_subscription( $subscription_id ) {
	return \OhMyLMS\Commerce\ecommerce()->subscription_factory->get_subscription( $subscription_id );
}

/**
 * Get subscription notes.
 *
 * @param array $args Query arguments.
 * @return array Subscription notes.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_get_subscription_notes( $args ) {
	$key_mapping = array(
		'limit'           => 'number',
		'subscription_id' => 'post_id',
		'order__in'       => 'post__in',
		'order__not_in'   => 'post__not_in',
	);

	foreach ( $key_mapping as $query_key => $db_key ) {
		if ( isset( $args[ $query_key ] ) ) {
			$args[ $db_key ] = $args[ $query_key ];
			unset( $args[ $query_key ] );
		}
	}

	// Define orderby.
	$orderby_mapping = array(
		'date_created'     => 'comment_date',
		'date_created_gmt' => 'comment_date_gmt',
		'id'               => 'comment_ID',
	);

	$args['orderby'] = ! empty( $args['orderby'] ) && in_array( $args['orderby'], array( 'date_created', 'date_created_gmt', 'id' ), true ) ? $orderby_mapping[ $args['orderby'] ] : 'comment_ID';

	if ( isset( $args['type'] ) && 'customer' === $args['type'] ) {
		$args['meta_query'] = array( // WPCS: slow query ok.
			array(
				'key'     => 'is_customer_note',
				'value'   => 1,
				'compare' => '=',
			),
		);
	} elseif ( isset( $args['type'] ) && 'internal' === $args['type'] ) {
		$args['meta_query'] = array( // WPCS: slow query ok.
			array(
				'key'     => 'is_customer_note',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	// Set correct comment type.
	$args['type'] = 'subscription_note';

	// Always approved.
	$args['status'] = 'approve';

	// Does not support 'count' or 'fields'.
	unset( $args['count'], $args['fields'] );

	remove_filter( 'comments_clauses', array( 'OhMyLMS\\Commerce\\Comments', 'exclude_order_comments' ), 10, 1 );

	remove_filter( 'comments_clauses', array( 'OhMyLMS\\Commerce\\Comments', 'exclude_subscription_comments' ), 10, 1 );

	$notes = get_comments( $args );

	add_filter( 'comments_clauses', array( 'OhMyLMS\\Commerce\\Comments', 'exclude_subscription_comments' ), 10, 1 );
	return array_filter( array_map( 'ohmylms_ecommerce_get_subscription_note', $notes ) );
}

/**
 * Get a subscription note object.
 *
 * @param int|WP_Comment $data Comment ID or WP_Comment object.
 * @return object|null Subscription note object or null if not found.
 */
function ohmylms_ecommerce_get_subscription_note( $data ) {
	if ( is_numeric( $data ) ) {
		$data = get_comment( $data );
	}

	if ( ! is_a( $data, 'WP_Comment' ) ) {
		return null;
	}

	return (object) array(
		'id'            => (int) $data->comment_ID,
		'date_created'  => ohmylms_ecommerce_string_to_datetime( $data->comment_date ),
		'content'       => $data->comment_content,
		'customer_note' => (bool) get_comment_meta( $data->comment_ID, 'is_customer_note', true ),
		'added_by'      => __( 'OhMyLMS', 'ohmylms' ) === $data->comment_author ? 'system' : $data->comment_author,
	);
}

/**
 * Get subscription info.
 *
 * @param int $membership_id Membership ID.
 * @return array Subscription info.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_get_subscription_info( $membership_id ) {
	$subscription_length = get_post_meta( $membership_id, '_subscription_length', true );
	if ( ! is_array( $subscription_length ) || empty( $subscription_length['period'] ) ) {
		return array(
			'period'   => 'unknown',
			'duration' => 1,
		);
	}

	$period_map = array(
		'day'   => __( 'daily', 'ohmylms' ),
		'month' => __( 'monthly', 'ohmylms' ),
		'year'  => __( 'yearly', 'ohmylms' ),
	);

	$_period  = strtolower( $subscription_length['period'] );
	$period   = isset( $period_map[ $_period ] ) ? $period_map[ $_period ] : __( 'Unknown', 'ohmylms' );
	$duration = 1;
	if ( ! empty( $subscription_length['every'] ) ) {
		if ( strtolower( $subscription_length['every'] ) !== 'every' ) {
			$duration = intval( $subscription_length['every'] );
		}
	}

	return array(
		'period'     => $period,
		'duration'   => $duration,
		'period_raw' => $_period,
	);
}
