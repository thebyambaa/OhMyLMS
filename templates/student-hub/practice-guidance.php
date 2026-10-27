<?php
/**
 * Safe lesson-scoped skill practice recommendations.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
$guidance_endpoint = add_query_arg(
	array(
		'lesson' => $guidance['lesson_id'],
		'skill'  => $guidance['skill_id'],
	),
	rest_url( 'ohmylms/v1/courses/' . $guidance['course_id'] . '/learning/practice-guidance' )
);
?>
<section class="oml-practice-guidance" data-guidance-course="<?php echo esc_attr( $guidance['course_id'] ); ?>" data-guidance-endpoint="<?php echo esc_url( $guidance_endpoint ); ?>" data-guidance-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-guidance-error="<?php esc_attr_e( 'Could not refresh skill progress. Try again.', 'ohmylms' ); ?>" aria-label="<?php esc_attr_e( 'Lesson practice guidance', 'ohmylms' ); ?>">
	<h3><?php esc_html_e( 'Suggested next step', 'ohmylms' ); ?></h3>
	<?php if ( $guidance['pending'] ) : ?>
		<p><?php esc_html_e( 'Your latest evidence is still being evaluated. Refresh skill progress shortly.', 'ohmylms' ); ?></p>
	<?php elseif ( $guidance['recommended'] ) : ?>
		<p><a class="oml-hub-button" href="<?php echo esc_url( $guidance['recommended']['practice_url'] ); ?>"><?php /* translators: %s: recommended skill name. */ echo esc_html( sprintf( 'review' === $guidance['recommended']['need'] ? __( 'Review %s', 'ohmylms' ) : __( 'Practise %s', 'ohmylms' ), $guidance['recommended']['name'] ) ); ?></a></p>
	<?php elseif ( $guidance['next'] ) : ?>
		<p><a class="oml-hub-button" href="<?php echo esc_url( $guidance['next']['url'] ); ?>"><?php echo esc_html( $guidance['next']['label'] ); ?></a></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Review your lesson progress and choose what to work on next.', 'ohmylms' ); ?></p>
	<?php endif; ?>
	<ul class="oml-lesson-skills">
		<?php foreach ( $guidance['skills'] as $guidance_skill ) : ?>
			<li data-guidance-skill="<?php echo esc_attr( $guidance_skill['term_id'] ); ?>">
				<strong><?php echo esc_html( $guidance_skill['name'] ); ?></strong>
				<p><?php echo esc_html( $guidance_skill['label'] ); ?> · <?php /* translators: %s: required skill level. */ echo esc_html( sprintf( __( 'Target: %s', 'ohmylms' ), $guidance_skill['target'] ) ); ?></p>
				<p><?php echo esc_html( 'gap' === $guidance_skill['need'] ? __( 'Needs practice', 'ohmylms' ) : ( 'review' === $guidance_skill['need'] ? __( 'Review due', 'ohmylms' ) : __( 'Target reached', 'ohmylms' ) ) ); ?></p>
				<?php if ( $guidance_skill['session_limit'] ) : ?>
					<a href="<?php echo esc_url( $guidance_skill['practice_url'] ); ?>"><?php esc_html_e( 'Practice this skill', 'ohmylms' ); ?></a>
					<p><?php /* translators: %d: session question limit. */ echo esc_html( sprintf( _n( 'Up to %d approved question per session.', 'Up to %d approved questions per session.', $guidance_skill['session_limit'], 'ohmylms' ), $guidance_skill['session_limit'] ) ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'No approved practice questions are available for this skill in your course yet.', 'ohmylms' ); ?></p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p><a href="<?php echo esc_url( $guidance['lesson_url'] ); ?>"><?php esc_html_e( 'Back to lesson progress', 'ohmylms' ); ?></a></p>
	<button type="button" class="ohmylms-button" data-guidance-refresh><?php esc_html_e( 'Refresh skill progress', 'ohmylms' ); ?></button>
	<p data-guidance-message role="status"></p>
</section>
