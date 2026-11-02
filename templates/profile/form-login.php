<?php
/**
 * Profile form login template.
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$args = array(
	'redirect_to' => isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '',
);

?>

<div class="ohmylms-user-login-wrapper">

	<?php
		ohmylms_login_form( $args );
		ohmylms_signup_form( $args );
	?>
</div>
