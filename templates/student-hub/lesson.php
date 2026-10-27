<?php
/**
 * Enrolled lesson overview, using only the learner's assigned publication.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\PlayerContext;

defined( 'ABSPATH' ) || exit;
?>
<div class="oml-lesson-page">
	<p><a href="<?php echo esc_url( PlayerContext::overview_url( $hub_outline['id'] ) ); ?>">&larr; <?php echo esc_html( $hub_outline['title'] ); ?></a></p>
	<?php if ( is_wp_error( $hub_lesson ) ) : ?>
		<h1><?php esc_html_e( 'Lesson unavailable', 'ohmylms' ); ?></h1>
		<p><?php echo esc_html( $hub_lesson->get_error_message() ); ?></p>
	<?php else : ?>
		<p class="oml-course-unit-label"><?php echo esc_html( $hub_lesson['unit']['title'] ); ?></p>
		<h1><?php echo esc_html( $hub_lesson['title'] ); ?></h1>
		<p><?php /* translators: 1: completed activities, 2: total activities. */ echo esc_html( sprintf( __( '%1$d of %2$d activities complete', 'ohmylms' ), $hub_lesson['activity_done'], $hub_lesson['activity_total'] ) ); ?></p>
		<progress max="100" value="<?php echo esc_attr( $hub_lesson['activity_percent'] ); ?>" aria-label="<?php esc_attr_e( 'Lesson activity completion', 'ohmylms' ); ?>"></progress>
		<?php if ( $hub_lesson['next'] ) : ?>
			<p><a class="oml-hub-button" href="<?php echo esc_url( $hub_lesson['next']['url'] ); ?>"><?php esc_html_e( 'Continue lesson', 'ohmylms' ); ?>: <?php echo esc_html( $hub_lesson['next']['name'] ); ?></a></p>
		<?php endif; ?>
		<h2><?php esc_html_e( 'Lesson activities', 'ohmylms' ); ?></h2>
		<ol class="oml-lesson-activities">
			<?php foreach ( $hub_lesson['items'] as $hub_activity ) : ?>
				<li>
					<?php if ( ! empty( $hub_activity['available'] ) ) : ?>
						<a href="<?php echo esc_url( $hub_activity['url'] ); ?>"><?php echo esc_html( $hub_activity['name'] ); ?></a>
					<?php else : ?>
						<strong><?php echo esc_html( $hub_activity['name'] ); ?></strong>
					<?php endif; ?>
					<span data-practice-skill="<?php echo esc_attr( 'practice' === $hub_activity['kind'] ? $hub_activity['term_id'] : 0 ); ?>" data-practice-course="<?php echo esc_attr( $hub_outline['id'] ); ?>">
					<?php
					echo esc_html(
						! empty( $hub_activity['kind_label'] ) ? $hub_activity['kind_label'] : ( array(
							'practice'   => __( 'Practice', 'ohmylms' ),
							'quiz'       => __( 'Quiz', 'ohmylms' ),
							'assignment' => __( 'Assignment', 'ohmylms' ),
						)[ $hub_activity['kind'] ] ?? __( 'Activity', 'ohmylms' ) )
					);
					?>
							· <?php echo esc_html( empty( $hub_activity['available'] ) ? __( 'Unavailable', 'ohmylms' ) : ( 'practice' === $hub_activity['kind'] ? $hub_activity['label'] : ( $hub_activity['complete'] ? __( 'Complete', 'ohmylms' ) : __( 'Not completed', 'ohmylms' ) ) ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php if ( $hub_lesson['skills'] ) : ?>
			<h2><?php esc_html_e( 'Skill progress', 'ohmylms' ); ?></h2>
			<p><?php esc_html_e( 'Completing an activity does not by itself raise skill mastery. Skill progress comes from evaluated assessment and practice evidence.', 'ohmylms' ); ?></p>
			<?php
			$hub_guidance = \OhMyLMS\Student\PracticeGuidance::get( $hub_outline['id'], $hub_lesson['id'] );
			if ( ! is_wp_error( $hub_guidance ) ) {
				echo \OhMyLMS\Student\PracticeGuidance::render( $hub_guidance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Owned template escapes each field by context.
			}
			?>
		<?php endif; ?>
	<?php endif; ?>
</div>
