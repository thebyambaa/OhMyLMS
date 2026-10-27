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
	'redirect_to' => isset( $_GET['redirect_to'] ) ? $_GET['redirect_to'] : '',
);

?>

<div class="ohmylms-user-login-wrapper">

	<?php
		ohmylms_login_form( $args );
		ohmylms_signup_form( $args );
	?>
</div>
