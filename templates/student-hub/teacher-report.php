<?php
/**
 * Teacher class assessment report, scoped to recorded classroom learning.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\TeacherWorkspace;

defined( 'ABSPATH' ) || exit;

$gradebook_class = \OhMyLMS\Schools\Access::classroom( $teacher_class, true );
if ( ! $gradebook_class || ! TeacherWorkspace::available() ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui::alert() escapes the message.
	echo \OhMyLMS\Design\Ui::alert( __( 'This class gradebook is not available.', 'ohmylms' ), array( 'tone' => 'danger' ) );
	return;
}
echo '<h3>' . esc_html__( 'Class gradebook', 'ohmylms' ) . '</h3>';
ohmylms_get_template(
	'student-hub/school-gradebook.php',
	array(
		'school_id'      => (int) $gradebook_class['school_id'],
		'class_id'       => $teacher_class,
		'page'           => $teacher_page,
		'matrix_teacher' => true,
	)
);
// Class skill levels have their own tab.
printf(
	'<p><a class="oml-hub-pill" href="%1$s">%2$s</a></p>',
	esc_url( add_query_arg( 'hub_class', $teacher_class, TeacherWorkspace::url( 'skills' ) ) ),
	esc_html__( 'See class skill levels', 'ohmylms' )
);
