<?php
/**
 * Notice functions.
 *
 * @package OhMyLMS
 */



/**
 * Add and store a notice
 *
 * @param string $message Message.
 * @param string $notice_type Notice type.
 * @param array  $data Input data.
 * @return void
 *
 * @since 1.0.0
 */
function ohmylms_add_notice( $message, $notice_type = 'success', $data = array() ) {
	if ( ! did_action( 'ohmylms_init' ) ) {
		return;
	}

	$notices = \OhMyLMS\Commerce\ecommerce()->session->get( 'ohmylms_notices', array() );
	if ( ! empty( $message ) ) {
		$notices[ $notice_type ][] = array(
			'notice' => $message,
			'data'   => $data,
		);
	}

	\OhMyLMS\Commerce\ecommerce()->session->set( 'ohmylms_notices', $notices );
}


/**
 * Ohmylms notice count for Notice-functions.
 */
function ohmylms_notice_count() {
}
