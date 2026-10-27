<?php
/**
 * Template for displaying name of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/name.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();

?>


<h1 class="student-name">
	<?php printf( /* translators: %s: text value. */
	__( 'Welcome, %s', 'ohmylms' ), $user->display_name ); ?>
</h1>
