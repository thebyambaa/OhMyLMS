<?php
/**
 * Learners x skills matrix, skills grouped under unit headers, with a course and unit filter.
 *
 * Expects $matrix (ReportData::matrix plus 'courses'), $matrix_url (base URL), $matrix_course (selected course ID).
 *
 * @package OhMyLMS
 */

use OhMyLMS\Skills\UnitReport;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'ohmylms-skill-matrix', plugins_url( 'assets/css/skill-matrix.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/skill-matrix.css' ) );
$matrix_skills = array();
foreach ( $matrix['skills'] as $matrix_skill ) {
	$matrix_skills[ $matrix_skill['id'] ] = $matrix_skill;
}
$matrix_unit_url = remove_query_arg( 'hub_skill_unit', $matrix_url );
?>
<div class="ohmylms-mx-wrap">
	<?php if ( count( $matrix['courses'] ) > 1 ) : ?>
		<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Course', 'ohmylms' ); ?>">
			<a class="oml-hub-pill" href="<?php echo esc_url( remove_query_arg( array( 'hub_skill_course', 'hub_skill_unit' ), $matrix_url ) ); ?>" <?php echo $matrix_course ? '' : 'aria-current="page"'; ?>><?php esc_html_e( 'All courses', 'ohmylms' ); ?></a>
			<?php foreach ( $matrix['courses'] as $matrix_option ) : ?>
				<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_skill_course', $matrix_option['id'], remove_query_arg( 'hub_skill_unit', $matrix_url ) ) ); ?>" <?php echo (int) $matrix_option['id'] === (int) $matrix_course ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $matrix_option['title'] ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
	<?php if ( $matrix['units'] ) : ?>
		<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Unit', 'ohmylms' ); ?>">
			<a class="oml-hub-pill" href="<?php echo esc_url( $matrix_unit_url ); ?>" <?php echo $matrix['unit'] ? '' : 'aria-current="page"'; ?>><?php esc_html_e( 'All units', 'ohmylms' ); ?></a>
			<?php foreach ( $matrix['units'] as $matrix_unit ) : ?>
				<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_skill_unit', $matrix_unit['id'], $matrix_unit_url ) ); ?>" <?php echo (int) $matrix_unit['id'] === (int) $matrix['unit'] ? 'aria-current="page"' : ''; ?>><?php echo esc_html( sprintf( /* translators: 1: unit name, 2: number of skills. */ _n( '%1$s (%2$d skill)', '%1$s (%2$d skills)', (int) $matrix_unit['skills'], 'ohmylms' ), $matrix_unit['name'], (int) $matrix_unit['skills'] ) ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
	<?php if ( ! $matrix['skills'] || ! $matrix['students'] ) : ?>
		<p><?php esc_html_e( 'No skills to show for this selection yet.', 'ohmylms' ); ?></p>
	<?php else : ?>
		<?php ohmylms_get_template( 'student-hub/skill-legend.php' ); ?>
		<div class="ohmylms-mx-scroll">
			<table class="ohmylms-mx">
				<caption class="ohmylms-mx-sr"><?php esc_html_e( 'Skill levels by student, grouped by unit', 'ohmylms' ); ?></caption>
				<thead>
					<tr>
						<th scope="col" rowspan="2"><?php esc_html_e( 'Student', 'ohmylms' ); ?></th>
						<?php foreach ( $matrix['groups'] as $matrix_group ) : ?>
							<th scope="colgroup" class="ohmylms-mx-unit" colspan="<?php echo esc_attr( count( $matrix_group['skills'] ) ); ?>"><?php echo esc_html( $matrix_group['name'] ); ?></th>
						<?php endforeach; ?>
					</tr>
					<tr>
						<?php foreach ( $matrix['groups'] as $matrix_group ) : ?>
							<?php foreach ( $matrix_group['skills'] as $matrix_term ) : ?>
								<th scope="col" title="<?php echo esc_attr( $matrix_skills[ $matrix_term ]['name'] ); ?>"><?php echo esc_html( $matrix_skills[ $matrix_term ]['code'] ? $matrix_skills[ $matrix_term ]['code'] . ' ' . $matrix_skills[ $matrix_term ]['name'] : $matrix_skills[ $matrix_term ]['name'] ); ?></th>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $matrix['students'] as $matrix_student ) : ?>
						<?php $matrix_cells = (array) $matrix_student['skills']; ?>
						<tr>
							<th scope="row"><?php echo esc_html( $matrix_student['name'] ); ?></th>
							<?php foreach ( $matrix['groups'] as $matrix_group ) : ?>
								<?php foreach ( $matrix_group['skills'] as $matrix_term ) : ?>
									<?php
									$matrix_cell = $matrix_cells[ $matrix_term ] ?? null;
									ohmylms_get_template(
										'student-hub/skill-cell.php',
										array(
											'cell_key'    => $matrix_cell['display'] ?? 'not-started',
											'cell_review' => ! empty( $matrix_cell['review_due'] ),
											'cell_title'  => $matrix_cell ? sprintf( /* translators: 1: level, 2: evidence count. */ __( '%1$s · %2$d pieces of evidence', 'ohmylms' ), $matrix_cell['label'], (int) $matrix_cell['evidence'] ) : '',
										)
									);
									?>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					<tr class="ohmylms-mx-average">
						<th scope="row"><?php esc_html_e( 'Class average', 'ohmylms' ); ?></th>
						<?php foreach ( $matrix['groups'] as $matrix_group ) : ?>
							<?php foreach ( $matrix_group['skills'] as $matrix_term ) : ?>
								<?php
								$matrix_average = ( (array) $matrix['average'] )[ $matrix_term ] ?? null;
								if ( $matrix_average ) {
									ohmylms_get_template(
										'student-hub/skill-cell.php',
										array(
											'cell_key'   => $matrix_average['key'],
											'cell_title' => sprintf( /* translators: %d: percentage of learners. */ __( '%d%% Proficient or above', 'ohmylms' ), (int) $matrix_average['proficient_share'] ),
										)
									);
								} else {
									echo '<td class="ohmylms-mx-cell ohmylms-mx-not-started" title="' . esc_attr( sprintf( /* translators: %d: smallest group size. */ __( 'Shown for groups of at least %d', 'ohmylms' ), UnitReport::MIN_COHORT ) ) . '">–</td>';
								}
								?>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</tr>
				</tbody>
			</table>
		</div>
		<p class="ohmylms-mx-note"><?php esc_html_e( 'Levels use the Khan mastery names. They come from question-level evidence and are separate from grades.', 'ohmylms' ); ?></p>
	<?php endif; ?>
</div>
