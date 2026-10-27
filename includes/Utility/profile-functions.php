<?php
/**
 * OhMyLMS Account Functions
 *
 * Functions for account specific things.
 *
 * @version 2.6.0
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the url to the lost password endpoint url.
 *
 * @param string $default_url Default lost password URL.
 * @return string
 */
function ohmylms_lostpassword_url( $default_url = '' ) {
	// Avoid loading too early.
	if ( ! did_action( 'init' ) ) {
		return $default_url;
	}

	// Don't change the admin form.
	if ( did_action( 'login_form_login' ) ) {
		return $default_url;
	}

	// Don't redirect to the ohmylms endpoint on global network admin lost passwords.
	if ( is_multisite() && isset( $_GET['redirect_to'] ) && false !== strpos( wp_unslash( $_GET['redirect_to'] ), network_admin_url() ) ) { // WPCS: input var ok, sanitization ok, CSRF ok.
		return $default_url;
	}

	$ohmylms_account_page_url    = ohmylms_get_page_permalink( 'student_profile' );
	$ohmylms_account_page_exists = ohmylms_get_page_id( 'student_profile' ) > 0;
	$lost_password_endpoint      = get_option( 'ohmylms_myaccount_lost_password_endpoint' );

	if ( $ohmylms_account_page_exists && ! empty( $lost_password_endpoint ) ) {
		return ohmylms_get_endpoint_url( $lost_password_endpoint, '', $ohmylms_account_page_url );
	} else {
		return $default_url;
	}
}

add_filter( 'lostpassword_url', 'ohmylms_lostpassword_url', 10, 1 );

/**
 * Get the link to the edit account details page.
 *
 * @return string
 */
function ohmylms_customer_edit_account_url() {
	$edit_account_url = ohmylms_get_endpoint_url( 'edit-profile', '', ohmylms_get_page_permalink( 'student_profile' ) );

	return apply_filters( 'ohmylms_customer_edit_account_url', $edit_account_url );
}

/**
 * Get the edit address slug translation.
 *
 * @param string $id Address ID.
 * @param bool   $flip Flip the array to make it possible to retrieve the values ​​from both sides.
 *
 * @return string       Address slug i18n.
 */
function ohmylms_edit_address_i18n( $id, $flip = false ) {
	$slugs = apply_filters(
		'ohmylms_edit_address_slugs',
		array(
			'billing'  => sanitize_title( _x( 'billing', 'edit-address-slug', 'ohmylms' ) ),
			'shipping' => sanitize_title( _x( 'shipping', 'edit-address-slug', 'ohmylms' ) ),
		)
	);

	if ( $flip ) {
		$slugs = array_flip( $slugs );
	}

	if ( ! isset( $slugs[ $id ] ) ) {
		return $id;
	}

	return $slugs[ $id ];
}

/**
 * Get My Account menu items.
 *
 * @since 2.6.0
 * @return array
 */
function ohmylms_get_account_menu_items() {
	$endpoints = array(
		'profile'              => get_option( 'ohmylms_myprofile_profile_endpoint', 'profile' ),
		'profile-edit'         => get_option( 'ohmylms_myprofile_profile_edit_endpoint', 'profile-edit' ),
		'my-courses'           => get_option( 'ohmylms_myprofile_course_endpoint', 'my-courses' ),
		'settings'             => get_option( 'ohmylms_myprofile_settings_endpoint', 'settings' ),
		'notification'         => get_option( 'ohmylms_myprofile_notification_endpoint', 'notification' ),
		'transactions-history' => get_option( 'ohmylms_myprofile_transactions_history_endpoint', 'transactions-history' ),
		'membership'           => get_option( 'ohmylms_myprofile_membership_endpoint', 'membership' ),
		'invoice-details'      => get_option( 'ohmylms_myprofile_invoice_details_endpoint', 'invoice-details' ),
		'billing-information'  => get_option( 'ohmylms_myprofile_edit_account_endpoint', 'billing-information' ),
		'customer-logout'      => get_option( 'ohmylms_logout_endpoint', 'customer-logout' ),
	);

	$items = array(
		'dashboard'            => __( 'Dashboard', 'ohmylms' ),
		'my-courses'           => __( 'My Course', 'ohmylms' ),
		'profile'              => __( 'My Profile', 'ohmylms' ),
		'notification'         => __( 'Notification', 'ohmylms' ),
		'settings'             => __( 'Settings', 'ohmylms' ),
		'transactions-history' => __( 'Transactions History', 'ohmylms' ),
		'membership'           => __( 'Membership', 'ohmylms' ),
		'invoice-details'      => __( 'Invoice Details', 'ohmylms' ),
		'billing-information'  => __( 'Billing-Information', 'ohmylms' ),
		'customer-logout'      => __( 'Log out', 'ohmylms' ),
	);

	// Remove missing endpoints.
	// foreach ( $endpoints as $endpoint_id => $endpoint ) {
	// if ( empty( $endpoint ) ) {
	// unset( $items[ $endpoint_id ] );
	// }
	// }.

	// Check if payment gateways support add new payment methods.
	// if ( isset( $items['payment-methods'] ) ) {
	// $support_payment_methods = false;
	// foreach ( \OhMyLMS\Commerce\ecommerce()->payment_gateways->get_available_payment_gateways() as $gateway ) {
	// if ( $gateway->supports( 'add_payment_method' ) || $gateway->supports( 'tokenization' ) ) {
	// $support_payment_methods = true;
	// break;
	// }
	// }
	//
	// if ( ! $support_payment_methods ) {
	// unset( $items['payment-methods'] );
	// }
	// }.

	return apply_filters( 'ohmylms_account_menu_items', $items, $endpoints );
}

/**
 * Find current item in account menu.
 *
 * @since 9.3.0
 * @param string $endpoint Endpoint.
 * @return bool
 */
function ohmylms_is_current_account_menu_item( $endpoint ) {
	global $wp;

	$current = isset( $wp->query_vars[ $endpoint ] );
	if ( 'dashboard' === $endpoint && ( isset( $wp->query_vars['page'] ) || empty( $wp->query_vars ) ) ) {
		$current = true; // Dashboard is not an endpoint, so needs a custom check.
	} elseif ( 'orders' === $endpoint && isset( $wp->query_vars['view-order'] ) ) {
		$current = true; // When looking at individual order, highlight Orders list item (to signify where in the menu the user currently is).
	} elseif ( 'settings' === $endpoint && isset( $wp->query_vars['settings'] ) ) {
		$current = true; // When looking at individual order, highlight Orders list item (to signify where in the menu the user currently is).
	} elseif ( 'payment-methods' === $endpoint && isset( $wp->query_vars['add-payment-method'] ) ) {
		$current = true;
	}
	return $current;
}

/**
 * Get account menu item classes.
 *
 * @since 2.6.0
 * @param string $endpoint Endpoint.
 * @return string
 */
function ohmylms_get_account_menu_item_classes( $endpoint ) {
	$classes = array(
		'ohmylms-profile--' . $endpoint,
	);

	if ( ohmylms_is_current_account_menu_item( $endpoint ) ) {
		$classes[] = ' active ';
	}

	$classes = apply_filters( 'ohmylms_account_menu_item_classes', $classes, $endpoint );

	return implode( ' ', array_map( 'sanitize_html_class', $classes ) );
}

/**
 * Get account endpoint URL.
 *
 * @since 2.6.0
 * @param string $endpoint Endpoint.
 * @return string
 * @param array  $query_params Query params.
 */
function ohmylms_get_account_endpoint_url( $endpoint, $query_params = array() ) {
	$hub_url = \OhMyLMS\Student\Hub::endpoint_url( $endpoint, $query_params );
	if ( $hub_url ) {
		return $hub_url;
	}

	if ( 'dashboard' === $endpoint ) {
		return ohmylms_get_page_permalink( 'student_dashboard' );
	}

	if ( 'profile' === $endpoint ) {
		return ohmylms_get_page_permalink( 'student_profile' );
	}

	if ( 'my-courses' === $endpoint ) {
		return ohmylms_get_page_permalink( 'student_courses' );
	}

	$url = ohmylms_get_endpoint_url( $endpoint, '', ohmylms_get_page_permalink( 'student_profile' ) );

	$url = add_query_arg( $query_params, $url );

	if ( 'customer-logout' === $endpoint ) {
		return wp_nonce_url( $url, 'customer-logout' );
	}
	return $url;
}

/**
 * Get My Account > Orders columns.
 *
 * @since 2.6.0
 * @return array
 */
function ohmylms_get_account_orders_columns() {
	/**
	 * Filters the array of My Account > Orders columns.
	 *
	 * @since 2.6.0
	 * @param array $columns Array of column labels keyed by column IDs.
	 */
	return apply_filters(
		'ohmylms_account_orders_columns',
		array(
			'order-number'  => __( 'Order', 'ohmylms' ),
			'order-date'    => __( 'Date', 'ohmylms' ),
			'order-status'  => __( 'Status', 'ohmylms' ),
			'order-total'   => __( 'Total', 'ohmylms' ),
			'order-actions' => __( 'Actions', 'ohmylms' ),
		)
	);
}

/**
 * Get My Account > Downloads columns.
 *
 * @since 2.6.0
 * @return array
 */
function ohmylms_get_account_downloads_columns() {
	$columns = apply_filters(
		'ohmylms_account_downloads_columns',
		array(
			'download-product'   => __( 'Product', 'ohmylms' ),
			'download-remaining' => __( 'Downloads remaining', 'ohmylms' ),
			'download-expires'   => __( 'Expires', 'ohmylms' ),
			'download-file'      => __( 'Download', 'ohmylms' ),
			'download-actions'   => '&nbsp;',
		)
	);

	if ( ! has_filter( 'ohmylms_account_download_actions' ) ) {
		unset( $columns['download-actions'] );
	}

	return $columns;
}

/**
 * Get My Account > Payment methods columns.
 *
 * @since 2.6.0
 * @return array
 */
function ohmylms_get_account_payment_methods_columns() {
	return apply_filters(
		'ohmylms_account_payment_methods_columns',
		array(
			'method'  => __( 'Method', 'ohmylms' ),
			'expires' => __( 'Expires', 'ohmylms' ),
			'actions' => '&nbsp;',
		)
	);
}

/**
 * Get My Account > Payment methods types
 *
 * @since 2.6.0
 * @return array
 */
function ohmylms_get_account_payment_methods_types() {
	return apply_filters(
		'ohmylms_payment_methods_types',
		array(
			'cc'     => __( 'Credit card', 'ohmylms' ),
			'echeck' => __( 'eCheck', 'ohmylms' ),
		)
	);
}

/**
 * Get the initials from the first and last name.
 *
 * @param string $firstName The first name.
 * @param string $lastName The last name.
 * @return string The initials.
 *
 * @since 1.0.0
 */
function ohmylms_get_initials( $firstName = '', $lastName = '' ) {
	$firstInitial  = ! empty( $firstName ) ? strtoupper( $firstName[0] ) : '';
	$secondInitial = ! empty( $lastName ) ? strtoupper( $lastName[0] ) : '';

	// If both are missing, return a default placeholder, e.g., "NN" for "No Name".
	if ( empty( $firstInitial ) && empty( $secondInitial ) ) {
		return 'NN';
	}

	return $firstInitial . $secondInitial;
}

/**
 * Get account orders actions.
 *
 * @since  3.2.0
 * @param int|WC_Order $order Order instance or ID.
 * @return array
 */
function ohmylms_get_account_orders_actions( $order ) {
	if ( ! is_object( $order ) ) {
		$order_id = absint( $order );
		$order    = ohmylms_get_order( $order_id );
	}

	$actions = array(
		'pay'    => array(
			'url'  => $order->get_checkout_payment_url(),
			'name' => __( 'Pay', 'ohmylms' ),
		),
		'view'   => array(
			'url'  => $order->get_view_order_url(),
			'name' => __( 'View', 'ohmylms' ),
		),
		'cancel' => array(
			'url'  => $order->get_cancel_order_url( ohmylms_get_page_permalink( 'myaccount' ) ),
			'name' => __( 'Cancel', 'ohmylms' ),
		),
	);

	if ( ! $order->needs_payment() ) {
		unset( $actions['pay'] );
	}

	if ( ! in_array( $order->get_status(), apply_filters( 'ohmylms_valid_order_statuses_for_cancel', array( 'pending', 'failed' ), $order ), true ) ) {
		unset( $actions['cancel'] );
	}

	return apply_filters( 'ohmylms_my_account_my_orders_actions', $actions, $order );
}
