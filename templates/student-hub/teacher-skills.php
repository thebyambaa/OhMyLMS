<?php
/**
 * A teacher's class skill levels: students by skills, from recorded class assessments only.
 *
 * Expects $teacher_class (class ID) and $teacher_page (roster page).
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\TeacherReports;
use OhMyLMS\Student\TeacherWorkspace;

defined( 'ABSPATH' ) || exit;

try {
	$class_report = TeacherReports::read( $teacher_class, absint( get_query_var( 'hub_period', 30 ) ), $teacher_page );
} catch ( RuntimeException $class_report_error ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui::alert() escapes the message.
	echo \OhMyLMS\Design\Ui::alert( __( 'This class report is no longer available. Return to your classes and try again.', 'ohmylms' ), array( 'tone' => 'danger' ) );
	return;
}
wp_enqueue_style( 'ohmylms-skill-matrix', plugins_url( 'assets/css/skill-matrix.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/skill-matrix.css' ) );
$class_report_url = add_query_arg( 'hub_class', $teacher_class, TeacherWorkspace::url( 'skills' ) );
// Every skill any student on this page was assessed on, by name.
$class_skills = array();
foreach ( $class_report['students'] as $class_report_student ) {
	foreach ( $class_report_student['skills'] as $class_term => $class_skill ) {
		$class_skills[ $class_term ] = $class_skill['name'];
	}
}
asort( $class_skills, SORT_NATURAL | SORT_FLAG_CASE );
?>
<h3><?php esc_html_e( 'Class skill levels', 'ohmylms' ); ?></h3>
<p><?php esc_html_e( 'Levels start at Not started for the selected period and follow the Khan rules using recorded class assessments only. Independent learning, home learning and overall skill levels are not included.', 'ohmylms' ); ?></p>
<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class report period', 'ohmylms' ); ?>">
	<?php foreach ( array( 7, 30, 90 ) as $class_report_days ) : ?>
		<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_period', $class_report_days, $class_report_url ) ); ?>" <?php echo $class_report_days === $class_report['days'] ? 'aria-current="page"' : ''; ?>>
			<?php
			/* translators: %d: number of days. */
			echo esc_html( sprintf( __( 'Last %d days', 'ohmylms' ), $class_report_days ) );
			?>
		</a>
	<?php endforeach; ?>
</nav>
<?php if ( ! $class_report['enabled'] ) : ?>
	<p><?php esc_html_e( 'Class skill reporting requires the Khan mastery model and recorded learning context to be enabled.', 'ohmylms' ); ?></p>
<?php elseif ( ! $class_report['students'] ) : ?>
	<p><?php esc_html_e( 'No students on this roster page.', 'ohmylms' ); ?></p>
<?php elseif ( ! $class_skills ) : ?>
	<p><?php esc_html_e( 'No recorded class skill assessments in this period. Levels appear here once students complete quizzes assigned to this class whose questions or quiz have skills.', 'ohmylms' ); ?></p>
<?php else : ?>
	<div class="ohmylms-mx-wrap">
		<?php ohmylms_get_template( 'student-hub/skill-legend.php' ); ?>
		<div class="ohmylms-mx-scroll">
			<table class="ohmylms-mx">
				<caption class="ohmylms-mx-sr"><?php esc_html_e( 'Class skill levels by student', 'ohmylms' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Student', 'ohmylms' ); ?></th>
						<?php foreach ( $class_skills as $class_skill_name ) : ?>
							<th scope="col" title="<?php echo esc_attr( $class_skill_name ); ?>"><?php echo esc_html( $class_skill_name ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $class_report['students'] as $class_report_student ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $class_report_student['display_name'] ); ?></th>
							<?php if ( $class_report_student['overflow'] ) : ?>
								<td class="ohmylms-mx-cell" colspan="<?php echo esc_attr( count( $class_skills ) ); ?>"><?php esc_html_e( 'Too many results to summarize. Choose a shorter period.', 'ohmylms' ); ?></td>
							<?php else : ?>
								<?php foreach ( array_keys( $class_skills ) as $class_term ) : ?>
									<?php
									$class_cell = $class_report_student['skills'][ $class_term ] ?? null;
									ohmylms_get_template(
										'student-hub/skill-cell.php',
										array(
											'cell_key'   => $class_cell ? $class_cell['level'] : 'not-started',
											'cell_title' => $class_cell
												/* translators: 1: level, 2: recorded assessment count. */
												? sprintf( _n( '%1$s · %2$d assessment', '%1$s · %2$d assessments', (int) $class_cell['assessments'], 'ohmylms' ), $class_cell['label'], (int) $class_cell['assessments'] )
												: __( 'Not started', 'ohmylms' ),
										)
									);
									?>
								<?php endforeach; ?>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="ohmylms-mx-note"><?php esc_html_e( 'Levels come from quizzes assigned to this class. They are separate from grades.', 'ohmylms' ); ?></p>
	</div>
<?php endif; ?>
<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class report roster pages', 'ohmylms' ); ?>">
	<?php
	foreach ( array(
		-1 => __( 'Previous', 'ohmylms' ),
		1  => __( 'Next', 'ohmylms' ),
	) as $class_report_step => $class_report_label ) :
		if ( ( -1 === $class_report_step && $class_report['page'] > 1 ) || ( 1 === $class_report_step && $class_report['more'] ) ) :
			$ohmylms_href = add_query_arg(
				array(
					'hub_period'   => $class_report['days'],
					'current_page' => $class_report['page'] + $class_report_step,
				),
				$class_report_url
			);
			?>
			<a class="oml-hub-pill" href="<?php echo esc_url( $ohmylms_href ); ?>"><?php echo esc_html( $class_report_label ); ?></a>
			<?php
		endif;
	endforeach;
	?>
</nav>
