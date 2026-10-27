<?php
/**
 * Single Lesson
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> >
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<?php wp_head(); ?>
	</head>

<body <?php body_class(); ?> >
	<?php
		global $post;
	if ( ! $post ) {
		return;
	}
		$preview_mode = get_post_meta( $post->ID, '_preview_enable', true );

		/*
		 * Access control: lesson/quiz/assignment/session content is for enrolled students
		 * only. This mirrors OhMyLMS\Hooks\CommonHook::restrict_access() and acts as a
		 * template-level safety net for the cases where that template_redirect gate does
		 * not run (theme template overrides, non-single query contexts, builder renders).
		 */
		$ohmylms_has_lesson_access = (bool) $preview_mode;

	if ( ! $ohmylms_has_lesson_access && is_user_logged_in() ) {
		if ( current_user_can( 'manage_options' ) || get_current_user_id() === (int) $post->post_author ) {
			$ohmylms_has_lesson_access = true;
		} else {
			$ohmylms_lesson_course_id = ohmylms_get_course_id_by_content_id( $post->ID );
			if ( empty( $ohmylms_lesson_course_id ) && 'ohmylms-session' === $post->post_type && $post->post_parent ) {
				$ohmylms_lesson_course_id = (int) $post->post_parent;
			}
			$ohmylms_lesson_student = new \OhMyLMS\Data\Student( get_current_user_id() );
			if ( $ohmylms_lesson_student->maybe_enrolled( $ohmylms_lesson_course_id ) ) {
				$ohmylms_has_lesson_access = true;
			}
		}
	}

	if ( ! $ohmylms_has_lesson_access ) {
		if ( ! is_user_logged_in() ) {
			ohmylms_get_template( 'profile/form-login.php' );
		} else {
			echo '<div class="ohmylms-access-denied-modal"><div class="ohmylms-access-denied-inner"><div class="ohmylms-access-denied-modal-content"><h4 class="ohmylms-access-denied-title">' . esc_html__( 'Access Denied', 'ohmylms' ) . '</h4><p class="ohmylms-access-denied-description">' . esc_html__( 'You do not have permission to view this content.', 'ohmylms' ) . '</p></div></div></div>';
		}
		return;
	}

		$attempt           = null;
		$current_post_type = get_post_type();
	if ( 'ohmylms-quiz' === $current_post_type ) {
		$quiz    = ohmylms_get_quiz( get_the_ID() );
		$attempt = $quiz->get_quiz_attempt( get_current_user_id() );
	}

	// One player for every lesson, quiz, assignment and session; the classic header, breadcrumb and progress strip are gone.
	if ( ! \OhMyLMS\Student\PlayerContext::open( ! empty( $attempt ) && 'ohmylms-quiz' === $current_post_type ) ) {
		\OhMyLMS\Student\PlayerContext::open_standalone();
	}

	?>

		<?php if ( ! empty( $attempt ) && 'ohmylms-quiz' === $current_post_type ) : ?>
			<?php ohmylms_get_template( 'single-lesson/quiz-form.php' ); ?>
		<?php else : ?>
			<?php ohmylms_get_template( 'single-lesson/lesson-content.php' ); ?>
		<?php endif; ?>

		<?php
		\OhMyLMS\Student\PlayerContext::close();
		wp_footer();

		?>
</body>
</html>
