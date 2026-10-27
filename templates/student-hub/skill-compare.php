<?php
/**
 * Skills of the guardian's own children side by side, or one child against the class average.
 *
 * Expects $compare (FamilyComparison::build). Only levels and an anonymous class aggregate are shown.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Skills\UnitReport;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'ohmylms-skill-matrix', plugins_url( 'assets/css/skill-matrix.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/skill-matrix.css' ) );
?>
<?php if ( ! $compare['rows'] ) : ?>
	<p><?php esc_html_e( 'No skills practised yet.', 'ohmylms' ); ?></p>
<?php else : ?>
	<?php ohmylms_get_template( 'student-hub/skill-legend.php' ); ?>
	<div class="ohmylms-mx-scroll">
		<table class="ohmylms-mx">
			<caption class="ohmylms-mx-sr"><?php echo esc_html( $compare['class'] ? __( 'Skill levels for your child and the class average', 'ohmylms' ) : __( 'Skill levels for your children', 'ohmylms' ) ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Skill', 'ohmylms' ); ?></th>
					<?php foreach ( $compare['children'] as $compare_child ) : ?>
						<th scope="col"><?php echo esc_html( $compare_child['name'] ); ?></th>
						<?php if ( $compare['class'] ) : ?>
							<th scope="col"><?php esc_html_e( 'Class average', 'ohmylms' ); ?></th>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $compare['rows'] as $compare_row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $compare['skills'][ $compare_row['skill'] ] ); ?></th>
						<?php foreach ( $compare['children'] as $compare_child ) : ?>
							<?php
							ohmylms_get_template( 'student-hub/skill-cell.php', array( 'cell_key' => $compare_row['cells'][ $compare_child['id'] ] ) );
							if ( $compare['class'] ) {
								$compare_class = $compare_row['class'][ $compare_child['id'] ] ?? null;
								if ( $compare_class ) {
									ohmylms_get_template(
										'student-hub/skill-cell.php',
										array(
											'cell_key'   => $compare_class['key'],
											'cell_title' => array(
												'most' => __( 'Most of the class is Proficient or above', 'ohmylms' ),
												'half' => __( 'About half of the class is Proficient or above', 'ohmylms' ),
												'few'  => __( 'Few of the class are Proficient or above', 'ohmylms' ),
											)[ $compare_class['band'] ] ?? '',
										)
									);
								} else {
									echo '<td class="ohmylms-mx-cell ohmylms-mx-not-started">–</td>';
								}
							}
							?>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="ohmylms-mx-note"><?php echo esc_html( $compare['class'] ? sprintf( /* translators: %d: smallest number of classmates. */ __( 'The class average leaves out your child, is anonymous and only shown for classes with at least %d other learners. It never names other learners.', 'ohmylms' ), UnitReport::MIN_PEERS ) : __( 'Levels use the Khan mastery names and are separate from grades.', 'ohmylms' ) ); ?></p>
<?php endif; ?>
