<?php
/**
 * Paginated class/course assessment matrix and full CSV download.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;
use OhMyLMS\Student\SchoolGradebook;
use OhMyLMS\Student\SchoolWorkspace;

defined( 'ABSPATH' ) || exit;

$matrix_teacher = ! empty( $matrix_teacher );
$matrix_role    = $matrix_teacher ? 'teacher' : 'school';
$matrix_section = $matrix_teacher ? 'progress' : 'school-gradebook';
$matrix_action  = $matrix_teacher ? SchoolGradebook::TEACHER_EXPORT_ACTION : SchoolGradebook::EXPORT_ACTION;
$matrix_base    = $matrix_teacher ? \OhMyLMS\Student\TeacherWorkspace::url( 'progress' ) : SchoolWorkspace::url( 'school-gradebook', '', $school_id );
try {
	$matrix_courses = SchoolGradebook::courses( $school_id, $class_id, $matrix_teacher );
} catch ( \RuntimeException $matrix_error ) {
	echo '<p role="alert">' . esc_html__( 'This class gradebook is not available.', 'ohmylms' ) . '</p>';
	return;
}
if ( ! $matrix_courses ) {
	echo '<p>' . esc_html__( 'Assign a course or activity to this class to start its gradebook.', 'ohmylms' ) . '</p>';
	return;
}
$matrix_course = absint( get_query_var( 'hub_course', 0 ) );
$matrix_course = $matrix_course ? $matrix_course : (int) $matrix_courses[0]['id'];
try {
	$matrix = SchoolGradebook::read( $school_id, $class_id, $matrix_course, $page, $matrix_teacher );
} catch ( \RuntimeException $matrix_error ) {
	echo '<p role="alert">' . esc_html__( 'This course is not available in this class.', 'ohmylms' ) . '</p>';
	return;
}
$matrix_url = add_query_arg(
	array(
		'hub_class'  => $class_id,
		'hub_course' => $matrix_course,
	),
	$matrix_base
);
?>
<p class="oml-hub-family-meta"><?php esc_html_e( 'Only assigned work for current class students is shown. Latest graded attempts count toward totals; missing work and pending retakes do not replace a grade. Totals use earned points divided by possible points for graded work. Manual course gradebook overrides are separate.', 'ohmylms' ); ?></p>
<form method="get" action="<?php echo esc_url( Hub::base_url() ); ?>">
	<?php
	if ( ! get_option( 'permalink_structure' ) ) :
		?>
		<input type="hidden" name="page_id" value="<?php echo esc_attr( Hub::settings()['page_id'] ); ?>"><?php endif; ?>
	<input type="hidden" name="hub_role" value="<?php echo esc_attr( $matrix_role ); ?>"><input type="hidden" name="hub_section" value="<?php echo esc_attr( $matrix_section ); ?>"><input type="hidden" name="hub_school" value="<?php echo esc_attr( $school_id ); ?>"><input type="hidden" name="hub_class" value="<?php echo esc_attr( $class_id ); ?>">
	<div class="oml-hub-field"><label for="matrix-course"><?php esc_html_e( 'Course', 'ohmylms' ); ?></label><select id="matrix-course" name="hub_course">
		<?php
		foreach ( $matrix_courses as $matrix_option ) :
			?>
			<option value="<?php echo esc_attr( $matrix_option['id'] ); ?>" <?php selected( $matrix_course, (int) $matrix_option['id'] ); ?>><?php echo esc_html( $matrix_option['title'] ); ?></option><?php endforeach; ?>
	</select></div>
	<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Show course', 'ohmylms' ); ?></button></p>
</form>
<?php if ( ! $matrix['students'] || ! $matrix['items'] ) : ?>
	<p><?php esc_html_e( 'No distributed assessments or current students on this page.', 'ohmylms' ); ?></p>
<?php else : ?>
	<div class="oml-hub-gradebook-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Scrollable gradebook', 'ohmylms' ); ?>">
		<table class="oml-hub-gradebook-table">
			<caption><?php esc_html_e( 'Class assessment grades', 'ohmylms' ); ?></caption>
			<thead><tr><th scope="col"><?php esc_html_e( 'Student', 'ohmylms' ); ?></th>
			<?php
			foreach ( $matrix['items'] as $matrix_item ) :
				?>
				<th scope="col"><?php echo esc_html( $matrix_item['title'] ); ?></th><?php endforeach; ?><th scope="col"><?php esc_html_e( 'Total', 'ohmylms' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $matrix['students'] as $matrix_student ) : ?>
					<tr><th scope="row"><?php echo esc_html( $matrix_student['name'] ); ?></th>
						<?php foreach ( $matrix['items'] as $matrix_item ) : ?>
							<?php $matrix_cell = $matrix_student['cells'][ $matrix_item['id'] ]; ?>
							<td><?php echo esc_html( null === $matrix_cell['score'] ? SchoolGradebook::status( $matrix_cell['status'] ) : $matrix_cell['score'] . ' / ' . $matrix_cell['max'] ); ?></td>
						<?php endforeach; ?>
						<td><?php echo esc_html( null === $matrix_student['total']['percent'] ? __( 'Not graded', 'ohmylms' ) : $matrix_student['total']['score'] . ' / ' . $matrix_student['total']['max'] . ' (' . $matrix_student['total']['percent'] . '%)' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Gradebook pages', 'ohmylms' ); ?>">
	<?php
	if ( $page > 1 ) :
		?>
		<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $page - 1, $matrix_url ) ); ?>"><?php esc_html_e( 'Previous students', 'ohmylms' ); ?></a><?php endif; ?>
	<?php
	if ( $matrix['more'] ) :
		?>
		<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $page + 1, $matrix_url ) ); ?>"><?php esc_html_e( 'Next students', 'ohmylms' ); ?></a><?php endif; ?>
</nav>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( $matrix_action ); ?>"><input type="hidden" name="school_id" value="<?php echo esc_attr( $school_id ); ?>"><input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>"><input type="hidden" name="course_id" value="<?php echo esc_attr( $matrix_course ); ?>">
	<?php Hub::nonce_field( $matrix_action ); ?>
	<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Download full class CSV', 'ohmylms' ); ?></button></p>
</form>
