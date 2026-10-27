<?php

/**
 * OhMyLMS admin functions
 *
 * @package OhMyLMS
 */

/**
 * Check if current page is OhMyLMS admin page
 *
 * @return bool
 */
function ohmylms_is_admin_page(): bool {
	return is_admin() && isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'ohmylms-settings', 'ohmylms-tools' ), true );
}
