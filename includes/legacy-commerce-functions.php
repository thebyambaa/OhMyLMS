<?php
/**
 * Deprecated commerce functions for installations without CreatorLMS.
 *
 * Parameter names are retained for existing named-argument callers.
 *
 * @package OhMyLMS
 */

namespace CodeRex\Ecommerce;

defined( 'ABSPATH' ) || exit;


/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ecommerce().
 * @return mixed The result from the OhMyLMS implementation.
 */
function ecommerce() {
	return \OhMyLMS\Commerce\ecommerce();
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_add_notice().
 * @param mixed $message Original argument.
 * @param mixed $notice_type Original argument.
 * @param mixed $data Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_add_notice( $message, $notice_type = 'success', $data = array() ) {
	return \OhMyLMS\Commerce\ohmylmse_add_notice( $message, $notice_type, $data );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_notice_count().
 * @param mixed $notice_type Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_notice_count( $notice_type = 'error' ) {
	return \OhMyLMS\Commerce\ohmylmse_notice_count( $notice_type );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_has_notice().
 * @param mixed $message Original argument.
 * @param mixed $notice_type Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_has_notice( $message, $notice_type = 'success' ) {
	return \OhMyLMS\Commerce\ohmylmse_has_notice( $message, $notice_type );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_set_notices().
 * @param mixed $notices Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_set_notices( $notices ) {
	return \OhMyLMS\Commerce\ohmylmse_set_notices( $notices );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_clear_notices().
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_clear_notices() {
	return \OhMyLMS\Commerce\ohmylmse_clear_notices();
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_print_notices().
 * @param mixed $return Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_print_notices( $return = false ) {
	return \OhMyLMS\Commerce\ohmylmse_print_notices( $return );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylms_print_notice().
 * @param mixed $message Original argument.
 * @param mixed $notice_type Original argument.
 * @param mixed $data Original argument.
 * @param mixed $return Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylms_print_notice( $message, $notice_type = 'success', $data = array(), $return = false ) {
	return \OhMyLMS\Commerce\ohmylms_print_notice( $message, $notice_type, $data, $return );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylms_get_notices().
 * @param mixed $notice_type Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylms_get_notices( $notice_type = '' ) {
	return \OhMyLMS\Commerce\ohmylms_get_notices( $notice_type );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_add_wp_error_notices().
 * @param mixed $errors Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_add_wp_error_notices( $errors ) {
	return \OhMyLMS\Commerce\ohmylmse_add_wp_error_notices( $errors );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_kses_notice().
 * @param mixed $message Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_kses_notice( $message ) {
	return \OhMyLMS\Commerce\ohmylmse_kses_notice( $message );
}

/**
 * Forward a deprecated standalone API call.
 *
 * @deprecated 1.2.20 Use \OhMyLMS\Commerce\ohmylmse_get_notice_data_attr().
 * @param mixed $notice Original argument.
 * @return mixed The result from the OhMyLMS implementation.
 */
function ohmylmse_get_notice_data_attr( $notice ) {
	return \OhMyLMS\Commerce\ohmylmse_get_notice_data_attr( $notice );
}
