<?php
/**
 * Subject index and ordered published course cards.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\CourseOutline;
use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;
?>
<section class="oml-subject-browser" aria-label="<?php esc_attr_e( 'Subjects and courses', 'ohmylms' ); ?>">
	<?php if ( is_wp_error( $subject ) ) : ?>
		<h1><?php esc_html_e( 'Subject not available', 'ohmylms' ); ?></h1>
		<a href="<?php echo esc_url( $subject_index_url ); ?>"><?php esc_html_e( 'Browse subjects', 'ohmylms' ); ?></a>
	<?php elseif ( $subject ) : ?>
		<nav aria-label="<?php esc_attr_e( 'Breadcrumb', 'ohmylms' ); ?>"><a href="<?php echo esc_url( $subject_index_url ); ?>"><?php esc_html_e( 'All subjects', 'ohmylms' ); ?></a> / <span aria-current="page"><?php echo esc_html( $subject['name'] ); ?></span></nav>
		<h1><?php echo esc_html( $subject['name'] ); ?></h1>
		<?php
		if ( $subject['description'] ) :
			?>
			<p><?php echo esc_html( $subject['description'] ); ?></p><?php endif; ?>
		<ol class="oml-subject-courses">
			<?php foreach ( $subject['course_ids'] as $subject_course_id ) : ?>
				<?php
				$subject_outline = CourseOutline::get( $subject_course_id, get_current_user_id() );
				if ( is_wp_error( $subject_outline ) ) {
					continue;
				}
				$subject_overview = is_user_logged_in() && Hub::base_url() && in_array( 'library', Hub::settings()['tabs'], true ) ? Hub::course_url( $subject_course_id ) : get_permalink( $subject_course_id );
				$subject_overview = add_query_arg( 'hub_subject', $subject['id'], $subject_overview );
				$subject_action   = $subject_outline['continue'];
				if ( ! $subject_outline['enrolled'] ) {
					$subject_action['url'] = $subject_overview . '#oml-course-enroll';
				}
				// A visitor is sent to register (or sign in when registration is closed); browsing creates no account.
				$subject_visitor = ! is_user_logged_in();
				if ( $subject_visitor ) {
					$subject_action['url'] = apply_filters( 'ohmylms_subject_signup_url', get_option( 'users_can_register' ) ? wp_registration_url() : wp_login_url( $subject_overview ), $subject_course_id );
				}
				?>
				<li class="oml-subject-course">
					<article>
						<h2><a href="<?php echo esc_url( $subject_overview ); ?>"><?php echo esc_html( $subject_outline['title'] ); ?></a></h2>
						<p><?php echo esc_html( $subject_outline['description'] ); ?></p>
						<?php if ( ! $subject_outline['locked'] ) : ?>
							<p><?php /* translators: %d: number of units. */ echo esc_html( sprintf( _n( '%d unit', '%d units', count( $subject_outline['units'] ), 'ohmylms' ), count( $subject_outline['units'] ) ) ); ?></p>
							<?php if ( $subject_outline['units'] ) : ?>
								<details><summary><?php esc_html_e( 'View published outline', 'ohmylms' ); ?></summary><ol>
								<?php
								foreach ( $subject_outline['units'] as $subject_unit ) :
									?>
									<li><?php echo esc_html( $subject_unit['title'] ); ?></li><?php endforeach; ?></ol></details>
							<?php endif; ?>
						<?php endif; ?>
						<a class="oml-subject-action" href="<?php echo esc_url( $subject_action['url'] ); ?>"><?php echo esc_html( $subject_outline['enrolled'] ? $subject_action['label'] : ( $subject_visitor ? __( 'Sign up to enroll', 'ohmylms' ) : __( 'Start course', 'ohmylms' ) ) ); ?></a>
						<?php if ( $subject_visitor ) : ?>
							<a class="oml-subject-secondary" href="<?php echo esc_url( $subject_overview ); ?>"><?php esc_html_e( 'View course', 'ohmylms' ); ?></a>
						<?php endif; ?>
					</article>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<h1><?php esc_html_e( 'Browse subjects', 'ohmylms' ); ?></h1>
		<p><?php esc_html_e( 'Choose a subject, then a course to begin learning.', 'ohmylms' ); ?></p>
		<?php
		if ( ! $subjects ) :
			?>
			<p><?php esc_html_e( 'No subjects yet. Subjects appear when courses are published and assigned to them.', 'ohmylms' ); ?></p><?php endif; ?>
		<ul class="oml-subject-courses">
			<?php foreach ( $subjects as $subject_node ) : ?>
				<li class="oml-subject-course"><h2><a href="<?php echo esc_url( $subject_link( $subject_node['id'] ) ); ?>"><?php echo esc_html( $subject_node['name'] ); ?></a></h2><p><?php /* translators: %d: number of courses. */ echo esc_html( sprintf( _n( '%d course', '%d courses', $subject_node['total'], 'ohmylms' ), $subject_node['total'] ) ); ?></p></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
