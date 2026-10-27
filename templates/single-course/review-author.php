<?php
/**
 * Template for displaying the review author in a single course.
 *
 * This template can be overridden by copying it to yourtheme/single-course/review-author.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 * @global \OhMyLMS\Data\Student $student
 */
defined( 'ABSPATH' ) || exit;

global $comment;

?>

<figure class="author-image">
	<?php
	$student_profile_photo = $student->get_profile_image();
	if ( $student_profile_photo ) {
		echo '<img class="student-profile-photo" src="' . esc_url( $student_profile_photo ) . '" alt="Student Profile Photo" id="student-profile-photo">';
	} else {
		echo esc_html( ohmylms_get_initials( $student->get_first_name(), $student->get_last_name() ) );
	}
	?>
</figure>
