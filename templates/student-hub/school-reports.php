<?php
/**
 * School completion, skills and submission grading inside the shared hub shell.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\SchoolActions;
use OhMyLMS\Student\SchoolReports;
use OhMyLMS\Student\SchoolWorkspace;

defined( 'ABSPATH' ) || exit;

$report_class_id = absint( get_query_var( 'hub_class', 0 ) );
$report_url      = SchoolWorkspace::url( $section, '', $school_id );
$report_grading  = 'school-gradebook' === $section;
$report_class    = null;
try {
	if ( $report_class_id ) {
		$report_class = SchoolReports::classroom( $school_id, $report_class_id );
	}
} catch ( \RuntimeException $report_error ) {
	echo '<p class="oml-hub-notice is-error" role="alert">' . esc_html__( 'This class is not available in this school.', 'ohmylms' ) . '</p>';
	return;
}
?>
<section class="oml-hub-card oml-hub-settings-card oml-hub-school-reports">
	<h2><?php echo esc_html( $report_grading ? __( 'Gradebook', 'ohmylms' ) : __( 'School reports', 'ohmylms' ) ); ?></h2>
	<?php if ( ! $report_class ) : ?>
		<?php
		$report_rows = SchoolReports::overview( $school_id, $school_page );
		$report_more = count( $report_rows ) > 20;
		$report_rows = array_slice( $report_rows, 0, 20 );
		?>
		<p class="oml-hub-family-meta"><?php esc_html_e( 'Choose a class to see assigned work or review submissions. Counts include active assignments for current school and class students.', 'ohmylms' ); ?></p>
		<?php if ( ! $report_rows ) : ?>
			<p><?php esc_html_e( 'No classes yet. Create a class to start reporting.', 'ohmylms' ); ?></p>
		<?php else : ?>
			<ul class="oml-hub-commerce-list">
				<?php foreach ( $report_rows as $report_row ) : ?>
					<li><div>
						<strong><a href="<?php echo esc_url( add_query_arg( 'hub_class', $report_row['id'], $report_url ) ); ?>"><?php echo esc_html( $report_row['name'] ); ?></a></strong>
						<p class="oml-hub-family-meta"><?php echo esc_html( 'active' === $report_row['status'] ? __( 'Active', 'ohmylms' ) : __( 'Archived', 'ohmylms' ) ); ?></p>
						<p><?php echo esc_html( sprintf( /* translators: 1: completed count, 2: assigned count, 3: overdue count. */ __( '%1$d of %2$d completed · %3$d overdue', 'ohmylms' ), $report_row['completed'], $report_row['assigned'], $report_row['overdue'] ) ); ?></p>
					</div><span class="oml-hub-chip"><?php echo esc_html( $report_row['assigned'] ? round( 100 * $report_row['completed'] / $report_row['assigned'] ) . '%' : __( 'No assigned work', 'ohmylms' ) ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php else : ?>
		<?php
		$report_url = add_query_arg( 'hub_class', $report_class_id, $report_url );
		$report_tab = 'skills' === get_query_var( 'hub_type', '' ) && ! $report_grading ? 'skills' : 'work';
		?>
		<h3><?php echo esc_html( $report_class['name'] ); ?></h3>
		<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class reports', 'ohmylms' ); ?>">
			<a class="oml-hub-pill" href="<?php echo esc_url( SchoolWorkspace::url( $section, '', $school_id ) ); ?>"><?php esc_html_e( 'All classes', 'ohmylms' ); ?></a>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_class', $report_class_id, SchoolWorkspace::url( 'school-reports', '', $school_id ) ) ); ?>"><?php esc_html_e( 'Assigned work', 'ohmylms' ); ?></a>
			<a class="oml-hub-pill" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'hub_class' => $report_class_id,
						'hub_type'  => 'skills',
					),
					SchoolWorkspace::url( 'school-reports', '', $school_id )
				)
			);
			?>
											"><?php esc_html_e( 'Skills', 'ohmylms' ); ?></a>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_class', $report_class_id, SchoolWorkspace::url( 'school-gradebook', '', $school_id ) ) ); ?>"><?php esc_html_e( 'Gradebook', 'ohmylms' ); ?></a>
		</nav>
		<?php if ( $report_grading ) : ?>
			<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Gradebook views', 'ohmylms' ); ?>">
				<a class="oml-hub-pill" href="<?php echo esc_url( $report_url ); ?>"><?php esc_html_e( 'Course matrix', 'ohmylms' ); ?></a>
				<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_type', 'submissions', $report_url ) ); ?>"><?php esc_html_e( 'Review submissions', 'ohmylms' ); ?></a>
			</nav>
			<?php if ( 'submissions' !== get_query_var( 'hub_type', '' ) ) : ?>
				<?php
				$report_more = false;
				ohmylms_get_template(
					'student-hub/school-gradebook.php',
					array(
						'school_id' => $school_id,
						'class_id'  => $report_class_id,
						'page'      => $school_page,
					)
				);
				?>
			<?php else : ?>
				<?php
				$report_url  = add_query_arg( 'hub_type', 'submissions', $report_url );
				$report_data = SchoolReports::submissions( $school_id, $report_class_id, $school_page );
				$report_rows = $report_data['rows'];
				$report_more = $report_data['more'];
				?>
			<p class="oml-hub-family-meta"><?php esc_html_e( 'Review submissions for activities assigned to this class. Each attempt is listed separately. Saving a grade updates the existing assessment and completion records.', 'ohmylms' ); ?></p>
				<?php if ( 'active' !== $report_class['status'] ) : ?>
				<p><?php esc_html_e( 'This class is archived. Assigned-work reports remain available; grading is closed.', 'ohmylms' ); ?></p>
			<?php elseif ( ! $report_rows ) : ?>
				<p><?php esc_html_e( 'No reviewable submissions on this page.', 'ohmylms' ); ?></p>
			<?php endif; ?>
				<?php foreach ( $report_rows as $submission ) : ?>
				<details>
					<summary><?php echo esc_html( sprintf( /* translators: 1: student, 2: assigned work, 3: attempt ID. */ __( '%1$s · %2$s · Attempt #%3$d', 'ohmylms' ), $submission['student_name'], $submission['title'], $submission['id'] ) ); ?><span class="oml-hub-family-meta"><?php echo esc_html( sprintf( /* translators: 1: score, 2: maximum points. */ __( 'Score: %1$s / %2$d', 'ohmylms' ), $submission['score'], $submission['total_points'] ) ); ?></span></summary>
					<p><?php echo esc_html( sprintf( /* translators: 1: score, 2: maximum, 3: submission status. */ __( 'Score: %1$s / %2$d · %3$s', 'ohmylms' ), $submission['score'], $submission['total_points'], $submission['status'] ) ); ?></p>
					<p><?php echo nl2br( esc_html( $submission['content'] ) ); ?></p>
					<?php if ( $submission['file_url'] ) : ?>
						<p><a href="<?php echo esc_url( $submission['file_url'] ); ?>"><?php esc_html_e( 'Download submitted file', 'ohmylms' ); ?></a></p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( $school_post ); ?>">
						<?php SchoolActions::fields( 'grade_submission', $school_id, 'school-gradebook' ); ?>
						<input type="hidden" name="class_id" value="<?php echo esc_attr( $report_class_id ); ?>">
						<input type="hidden" name="learning_id" value="<?php echo esc_attr( $submission['learning_id'] ); ?>">
						<input type="hidden" name="attempt_id" value="<?php echo esc_attr( $submission['id'] ); ?>">
						<div class="oml-hub-field"><label for="grade-<?php echo esc_attr( $submission['learning_id'] . '-' . $submission['id'] ); ?>"><?php esc_html_e( 'Score', 'ohmylms' ); ?></label><input id="grade-<?php echo esc_attr( $submission['learning_id'] . '-' . $submission['id'] ); ?>" name="score" type="number" min="0" max="<?php echo esc_attr( $submission['total_points'] ); ?>" step="1" required value="<?php echo esc_attr( $submission['score'] ); ?>"></div>
						<div class="oml-hub-field"><label for="note-<?php echo esc_attr( $submission['learning_id'] . '-' . $submission['id'] ); ?>"><?php esc_html_e( 'Feedback', 'ohmylms' ); ?></label><textarea id="note-<?php echo esc_attr( $submission['learning_id'] . '-' . $submission['id'] ); ?>" name="note" rows="3" maxlength="2000"><?php echo esc_textarea( $submission['note'] ); ?></textarea></div>
						<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Save grade', 'ohmylms' ); ?></button></p>
					</form>
				</details>
			<?php endforeach; ?>
			<?php endif; ?>
		<?php elseif ( 'skills' === $report_tab ) : ?>
			<?php
			$report_rows = SchoolReports::skills( $school_id, $report_class_id, $school_page );
			$report_more = count( $report_rows ) >= 50;
			$report_url  = add_query_arg( 'hub_type', 'skills', $report_url );
			?>
			<p class="oml-hub-family-meta"><?php esc_html_e( 'Current students’ stored skill levels across their learning. These levels are separate from grades and are not a measure of work done in this school.', 'ohmylms' ); ?></p>
			<?php if ( ! $report_rows ) : ?>
				<p><?php esc_html_e( 'No skill report is available. Skills may be disabled or this roster page is empty.', 'ohmylms' ); ?></p>
			<?php else : ?>
				<?php
				$report_course = absint( get_query_var( 'hub_skill_course', 0 ) );
				$report_matrix = SchoolReports::matrix( $school_id, $report_class_id, $school_page, $report_course, absint( get_query_var( 'hub_skill_unit', 0 ) ) );
				if ( $report_matrix ) {
					ohmylms_get_template(
						'student-hub/skill-matrix.php',
						array(
							'matrix'        => $report_matrix,
							'matrix_url'    => $report_url,
							'matrix_course' => $report_course,
						)
					);
				}
				?>
			<?php endif; ?>
			<?php foreach ( $report_rows as $skill_student ) : ?>
				<?php
				if ( 'student' !== $skill_student['role'] ) {
					continue; }
				?>
				<details>
					<summary><?php echo esc_html( $skill_student['display_name'] ); ?></summary>
					<?php if ( ! $skill_student['skills'] ) : ?>
						<p><?php esc_html_e( 'No skill evidence yet.', 'ohmylms' ); ?></p>
					<?php else : ?>
						<ul class="oml-hub-commerce-list">
							<?php foreach ( $skill_student['skills'] as $student_skill ) : ?>
								<li><div><strong><?php echo esc_html( $student_skill['name'] ); ?></strong>
								<?php
								if ( $student_skill['review_due'] ) :
									?>
									<p><?php esc_html_e( 'Review due', 'ohmylms' ); ?></p><?php endif; ?></div><span class="oml-hub-chip"><?php echo esc_html( $student_skill['level_label'] ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</details>
			<?php endforeach; ?>
		<?php else : ?>
			<?php
			$report_rows = SchoolReports::work( $school_id, $report_class_id, $school_page );
			$report_more = count( $report_rows ) >= 50;
			?>
			<?php if ( ! $report_rows ) : ?>
				<p><?php esc_html_e( 'No assigned work for current students on this page.', 'ohmylms' ); ?></p>
			<?php else : ?>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $report_rows as $work_row ) : ?>
						<li><div><strong><?php echo esc_html( $work_row['student_name'] ); ?></strong><p><?php echo esc_html( $work_row['title'] ); ?></p>
							<?php
							if ( $work_row['due_at'] ) :
								?>
								<p class="oml-hub-family-meta"><?php echo esc_html( sprintf( /* translators: %s: due date in the school time zone. */ __( 'Due %s', 'ohmylms' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $work_row['due_at'] . ' UTC' ), new \DateTimeZone( $school['timezone'] ) ) ) ); ?></p><?php endif; ?>
						</div><span class="oml-hub-chip"><?php echo esc_html( $work_row['completed_at'] ? __( 'Completed', 'ohmylms' ) : ( $work_row['due_at'] && $work_row['due_at'] < \OhMyLMS\Schools\Service::now() ? __( 'Overdue', 'ohmylms' ) : __( 'Not completed', 'ohmylms' ) ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( ! $report_grading || ! $report_class || 'submissions' === get_query_var( 'hub_type', '' ) ) : ?>
	<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Report pages', 'ohmylms' ); ?>">
		<?php
		if ( $school_page > 1 ) :
			?>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $school_page - 1, $report_url ) ); ?>"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a><?php endif; ?>
		<?php
		if ( $report_more ) :
			?>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $school_page + 1, $report_url ) ); ?>"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a><?php endif; ?>
	</nav>
	<?php endif; ?>
</section>
