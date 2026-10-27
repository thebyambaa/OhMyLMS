<?php
/**
 * Enrolled learning paths, work, and course progress.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\CompletionPolicy;
use OhMyLMS\Learning\Frontend;

defined( 'ABSPATH' ) || exit;

$courses = $student->get_enrolled_courses();
$shown   = 0;
?>
<h1><?php echo esc_html( 'work' === $section ? __( 'My work', 'ohmylms' ) : ( 'progress' === $section ? __( 'Course progress', 'ohmylms' ) : __( 'Learning paths', 'ohmylms' ) ) ); ?></h1>
<div class="oml-hub-cards">
	<?php foreach ( $courses as $learning_course ) : ?>
		<?php
		$course_id = $learning_course->get_id();
		$state     = \OhMyLMS\Learning\Schema::ready() ? CompletionPolicy::status( get_current_user_id(), $course_id ) : null;
		$program   = $state && ! is_wp_error( $state ) ? $state['program'] : null;
		if ( 'learn' === $section && ! $program ) {
			continue;
		}
		$items = array();
		if ( 'work' === $section ) {
			$curriculum = $program ? $program : CourseProgram::defaults( $course_id );
			foreach ( $curriculum['items'] as $item ) {
				if ( ! in_array( $item['type'], array( 'quiz', 'assignment' ), true ) || 'publish' !== get_post_status( $item['content_id'] ) ) {
					continue;
				}
				$completed = (bool) $student->maybe_completed( $item['content_id'] );
				if ( $program ) {
					$completed = ! empty( $item['complete'] );
				}
				if ( ( 'completed' === $view ) === $completed ) {
					$items[] = $item;
				}
			}
			if ( ! $items ) {
				continue;
			}
		}
		++$shown;
		?>
		<article class="oml-hub-card">
			<h2><?php echo esc_html( $learning_course->get_name() ); ?></h2>
			<?php if ( 'work' === $section ) : ?>
				<?php foreach ( $items as $item ) : ?>
					<div class="oml-hub-list-item"><p><?php echo esc_html( 'quiz' === $item['type'] ? __( 'Quiz', 'ohmylms' ) : __( 'Assignment', 'ohmylms' ) ); ?></p><h3><?php echo esc_html( get_the_title( $item['content_id'] ) ); ?></h3><a class="oml-hub-button" href="<?php echo esc_url( $program ? $item['url'] : $learning_course->get_content_link( $item['content_id'] ) ); ?>"><?php esc_html_e( 'Open activity', 'ohmylms' ); ?></a></div>
				<?php endforeach; ?>
			<?php else : ?>
				<?php $percent = max( 0, min( 100, (float) $student->get_course_progress_percentage( $course_id ) ) ); ?>
				<progress max="100" value="<?php echo esc_attr( $percent ); ?>" aria-label="<?php echo esc_attr( $learning_course->get_name() ); ?>"></progress>
				<p><?php /* translators: %s: completion percentage. */ echo esc_html( sprintf( __( '%s%% complete', 'ohmylms' ), $percent ) ); ?></p>
				<a class="oml-hub-button" href="<?php echo esc_url( $program ? Frontend::url( $course_id ) : $student->get_course_resume_url( $course_id ) ); ?>"><?php echo esc_html( 'learn' === $section ? __( 'Open learning path', 'ohmylms' ) : __( 'Continue learning', 'ohmylms' ) ); ?></a>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
<?php if ( ! $shown ) : ?>
	<div class="oml-hub-empty"><h2><?php esc_html_e( 'You’re all caught up', 'ohmylms' ); ?></h2><p><?php echo esc_html( 'work' === $section ? __( 'No activities in this view. Your enrolled course quizzes and assignments appear here.', 'ohmylms' ) : __( 'Your enrolled learning content will appear here when available.', 'ohmylms' ) ); ?></p></div>
<?php endif; ?>
