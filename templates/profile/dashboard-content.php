<?php
/** Student dashboard. Override in yourtheme/ohmylms/profile/dashboard-content.php.
 *
 * @package OhMyLMS
 */
defined( 'ABSPATH' ) || exit;
ohmylms_get_template( 'profile/student-dashboard.php', array( 'student' => $student ) );
