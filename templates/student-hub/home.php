<?php
/**
 * Configurable student home cards using existing learner data.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;

$courses = $student->get_enrolled_courses();
?>
<header class="oml-hub-greeting">
	<p><?php esc_html_e( 'YOUR LEARNING SPACE', 'ohmylms' ); ?></p>
	<h1><?php /* translators: %s: learner name. */ echo esc_html( sprintf( __( 'Welcome back, %s', 'ohmylms' ), $user->first_name ? $user->first_name : $user->display_name ) ); ?></h1>
	<p><?php esc_html_e( 'Pick up where you left off, or discover your next step.', 'ohmylms' ); ?></p>
</header>
<?php if ( $config['personalize'] ) : ?>
	<details class="oml-hub-personalize"><summary><?php esc_html_e( 'Personalize your space', 'ohmylms' ); ?></summary>
		<div class="oml-student-dashboard" data-dashboard-theme="<?php echo esc_attr( $dashboard['theme'] ); ?>" data-theme-endpoint="<?php echo esc_url( rest_url( 'ohmylms/v1/student/dashboard-theme' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-save-error="<?php esc_attr_e( 'Could not save your theme. Please try again.', 'ohmylms' ); ?>" data-save-success="<?php esc_attr_e( 'Theme saved.', 'ohmylms' ); ?>">
			<?php
			foreach ( array(
				'meadow' => __( 'Meadow', 'ohmylms' ),
				'ocean'  => __( 'Ocean', 'ohmylms' ),
				'sunset' => __( 'Sunset', 'ohmylms' ),
			) as $key => $label ) :
				?>
				<button type="button" data-select-theme="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo $dashboard['theme'] === $key ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
			<p role="status" data-theme-message></p>
		</div>
	</details>
<?php endif; ?>
<div class="oml-hub-cards" data-hub-theme="<?php echo esc_attr( $dashboard['theme'] ); ?>">
	<?php foreach ( $config['cards'] as $card ) : ?>
		<article class="oml-hub-card" data-card="<?php echo esc_attr( $card ); ?>">
			<h2><?php echo esc_html( Hub::cards()[ $card ] ); ?></h2>
			<?php if ( 'weekly' === $card ) : ?>
				<dl class="oml-hub-stats">
					<div><dt><?php esc_html_e( 'Practice answers', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $dashboard['weekly']['answers'] ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Skills practiced', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $dashboard['weekly']['skills'] ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Active days', 'ohmylms' ); ?></dt><dd><?php echo esc_html( $dashboard['weekly']['days'] ); ?></dd></div>
				</dl>
			<?php elseif ( 'recommendations' === $card ) : ?>
				<?php
				$suggestions = array_filter(
					$dashboard['recommendations'],
					static function ( $item ) {
						return ! empty( $item['skill'] );
					}
				);
				?>
				<?php if ( ! $suggestions ) : ?>
					<p><?php esc_html_e( 'Suggestions will appear as you practice and build your skills.', 'ohmylms' ); ?></p>
				<?php endif; ?>
				<?php foreach ( array_slice( $suggestions, 0, 3 ) as $suggestion ) : ?>
					<div class="oml-hub-list-item"><h3><?php echo esc_html( $suggestion['skill']['name'] ); ?></h3><p><?php echo esc_html( $suggestion['message'] ); ?></p><a class="oml-hub-button" href="<?php echo esc_url( add_query_arg( 'ohmylms_practice', absint( $suggestion['skill']['term_id'] ?? $suggestion['skill']['id'] ?? 0 ), home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Practice', 'ohmylms' ); ?></a></div>
				<?php endforeach; ?>
			<?php else : ?>
				<?php $items = 'continue' === $card ? $student->get_progress_course() : $courses; ?>
				<?php if ( ! $items ) : ?>
					<p><?php echo esc_html( 'continue' === $card ? __( 'Your next chapter starts here. Choose a course to begin.', 'ohmylms' ) : __( 'You have not enrolled in any courses yet.', 'ohmylms' ) ); ?></p>
					<a class="oml-hub-button" href="<?php echo esc_url( Hub::catalog_url() ); ?>"><?php esc_html_e( 'Explore courses', 'ohmylms' ); ?></a>
				<?php endif; ?>
				<?php foreach ( array_slice( $items, 0, 3 ) as $learning_course ) : ?>
					<?php $percent = max( 0, min( 100, (float) $student->get_course_progress_percentage( $learning_course->get_id() ) ) ); ?>
					<div class="oml-hub-list-item">
						<h3><?php echo esc_html( $learning_course->get_name() ); ?></h3>
						<progress max="100" value="<?php echo esc_attr( $percent ); ?>" aria-label="<?php echo esc_attr( $learning_course->get_name() ); ?>"></progress>
						<p><?php /* translators: %s: completion percentage. */ echo esc_html( sprintf( __( '%s%% complete', 'ohmylms' ), $percent ) ); ?></p>
						<a class="oml-hub-button" href="<?php echo esc_url( $student->get_course_resume_url( $learning_course->get_id() ) ); ?>"><?php esc_html_e( 'Continue learning', 'ohmylms' ); ?></a>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
