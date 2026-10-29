<?php
/**
 * Teacher class assessment report, scoped to recorded classroom learning.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\TeacherReports;
use OhMyLMS\Student\TeacherWorkspace;

defined( 'ABSPATH' ) || exit;

$gradebook_class = \OhMyLMS\Schools\Access::classroom( $teacher_class, true );
if ( ! $gradebook_class || ! TeacherWorkspace::available() ) {
	echo '<p role="alert">' . esc_html__( 'This class gradebook is not available.', 'ohmylms' ) . '</p>';
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
echo '<details class="oml-hub-skill-report"><summary>' . esc_html__( 'Skill progress', 'ohmylms' ) . '</summary>';

try {
	$class_report = TeacherReports::read( $teacher_class, absint( get_query_var( 'hub_period', 30 ) ), $teacher_page );
} catch ( RuntimeException $class_report_error ) {
	echo '<p role="alert">' . esc_html__( 'This class report is no longer available. Return to your classes and try again.', 'ohmylms' ) . '</p>';
	echo '</details>';
	return;
}
$class_report_url = add_query_arg( 'hub_class', $teacher_class, TeacherWorkspace::url( 'progress' ) );
?>
<h3><?php esc_html_e( 'Class skill levels', 'ohmylms' ); ?></h3>
<p><?php esc_html_e( 'Levels start at Not started for the selected period and follow the Khan rules using recorded class assessments only. Independent learning, home learning and overall skill levels are not included.', 'ohmylms' ); ?></p>
<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class report period', 'ohmylms' ); ?>">
	<?php foreach ( array( 7, 30, 90 ) as $class_report_days ) : ?>
		<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_period', $class_report_days, $class_report_url ) ); ?>" <?php echo $class_report_days === $class_report['days'] ? 'aria-current="page"' : ''; ?>><?php echo esc_html( sprintf( /* translators: %d: lookback days. */ __( 'Last %d days', 'ohmylms' ), $class_report_days ) ); ?></a>
	<?php endforeach; ?>
</nav>
<?php if ( ! $class_report['enabled'] ) : ?>
	<p><?php esc_html_e( 'Class skill reporting requires the Khan mastery model and recorded learning context to be enabled.', 'ohmylms' ); ?></p>
<?php elseif ( ! $class_report['students'] ) : ?>
	<p><?php esc_html_e( 'No students on this roster page.', 'ohmylms' ); ?></p>
<?php else : ?>
	<?php foreach ( $class_report['students'] as $class_report_student ) : ?>
		<details>
			<summary><?php echo esc_html( $class_report_student['display_name'] ); ?></summary>
			<?php if ( $class_report_student['overflow'] ) : ?>
				<p><?php esc_html_e( 'This period has too many assessment results to summarize. Choose a shorter period.', 'ohmylms' ); ?></p>
			<?php elseif ( ! $class_report_student['skills'] ) : ?>
				<p><?php esc_html_e( 'No recorded class skill assessments in this period.', 'ohmylms' ); ?></p>
			<?php else : ?>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $class_report_student['skills'] as $class_report_skill ) : ?>
						<li><div><strong><?php echo esc_html( $class_report_skill['name'] ); ?></strong><p><?php echo esc_html( sprintf( /* translators: %d: recorded assessment count. */ _n( '%d assessment', '%d assessments', $class_report_skill['assessments'], 'ohmylms' ), $class_report_skill['assessments'] ) ); ?></p></div><span class="oml-hub-chip"><?php echo esc_html( $class_report_skill['label'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</details>
	<?php endforeach; ?>
<?php endif; ?>
<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class report roster pages', 'ohmylms' ); ?>">
	<?php
	foreach ( array(
		-1 => __( 'Previous', 'ohmylms' ),
		1  => __( 'Next', 'ohmylms' ),
	) as $class_report_step => $class_report_label ) :
		?>
											<?php if ( ( -1 === $class_report_step && $class_report['page'] > 1 ) || ( 1 === $class_report_step && $class_report['more'] ) ) : ?>
			<a class="oml-hub-pill" href="
												<?php
												echo esc_url(
													add_query_arg(
														array(
															'hub_period'   => $class_report['days'],
															'current_page' => $class_report['page'] + $class_report_step,
														),
														$class_report_url
													)
												);
												?>
											"><?php echo esc_html( $class_report_label ); ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>

</details>
