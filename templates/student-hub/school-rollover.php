<?php
/**
 * Explicit, previewed academic year rollover.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\SchoolActions;

defined( 'ABSPATH' ) || exit;
?>
<section class="oml-hub-card oml-hub-settings-card">
	<h2><?php esc_html_e( 'Academic year rollover', 'ohmylms' ); ?></h2>
	<p><?php esc_html_e( 'Move the active classes of one year into another active year. Each class with active students gets a class of the same name and grade in the new year; its students, course links and roster course access move with it. Classes without active students are archived without a new class unless you include them. The source year and its classes are archived; assignments and learning records stay in them.', 'ohmylms' ); ?></p>
	<?php if ( count( $school_active ) < 2 ) : ?>
		<p><?php esc_html_e( 'Create a second active academic year before rolling over.', 'ohmylms' ); ?></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( $school_post ); ?>">
			<?php SchoolActions::fields( 'rollover_preview', $school_id, 'school-classes' ); ?>
			<?php
			foreach ( array(
				'from_year' => __( 'Source year to archive', 'ohmylms' ),
				'to_year'   => __( 'Destination year', 'ohmylms' ),
			) as $year_key => $year_label ) :
				?>
				<div class="oml-hub-field"><label for="rollover-<?php echo esc_attr( $year_key ); ?>"><?php echo esc_html( $year_label ); ?></label><select id="rollover-<?php echo esc_attr( $year_key ); ?>" name="<?php echo esc_attr( $year_key ); ?>" required>
					<option value=""><?php esc_html_e( 'Choose an academic year', 'ohmylms' ); ?></option>
					<?php
					foreach ( $school_active as $year_option ) :
						?>
						<option value="<?php echo esc_attr( $year_option['id'] ); ?>"><?php echo esc_html( $year_option['label'] ); ?></option><?php endforeach; ?>
				</select></div>
			<?php endforeach; ?>
			<label class="oml-hub-check"><input type="checkbox" name="include_empty" value="1"><span><?php esc_html_e( 'Also create new classes for classes without active students', 'ohmylms' ); ?></span></label>
			<label class="oml-hub-check"><input type="checkbox" name="copy_teachers" value="1"><span><?php esc_html_e( 'Teachers join the new classes too', 'ohmylms' ); ?></span></label>
			<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Preview rollover', 'ohmylms' ); ?></button></p>
		</form>
	<?php endif; ?>
	<?php if ( ! empty( $school_flash['rollover']['totals'] ) ) : ?>
		<?php
		$rollover        = $school_flash['rollover'];
		$rollover_totals = $rollover['totals'];
		?>
		<h3><?php echo esc_html( $rollover['from']['label'] . ' → ' . $rollover['to']['label'] ); ?></h3>
		<p><?php echo esc_html( sprintf( /* translators: 1: classes created, 2: students moving, 3: classes archived without a new class. */ __( '%1$d new classes will be created and %2$d students move with their classes; %3$d classes without active students will only be archived. Review and confirm within five minutes.', 'ohmylms' ), $rollover_totals['classes_created'], $rollover_totals['students_promoted'], $rollover_totals['classes_skipped'] ) ); ?></p>
		<ul>
		<?php
		foreach ( $rollover['classes'] as $rollover_class ) :
			if ( 'skip' === $rollover_class['action'] ) {
				$rollover_line = __( 'archived; no new class (no active students)', 'ohmylms' );
			} elseif ( 'leave' === $rollover_class['action'] ) {
				$rollover_line = __( 'archived; its students leave the class', 'ohmylms' );
			} else {
				/* translators: 1: new class name, 2: students moving. */
				$rollover_line = sprintf( _n( '→ %1$s with %2$d student', '→ %1$s with %2$d students', $rollover_class['students'], 'ohmylms' ), $rollover_class['target']['name'], $rollover_class['students'] );
			}
			?>
			<li><?php echo esc_html( $rollover_class['name'] . ': ' . $rollover_line ); ?></li><?php endforeach; ?></ul>
		<form method="post" action="<?php echo esc_url( $school_post ); ?>">
			<?php SchoolActions::fields( 'rollover_commit', $school_id, 'school-classes' ); ?>
			<input type="hidden" name="from_year" value="<?php echo esc_attr( $rollover['from']['id'] ); ?>"><input type="hidden" name="to_year" value="<?php echo esc_attr( $rollover['to']['id'] ); ?>">
			<label class="oml-hub-check"><input type="checkbox" name="confirm" value="yes" required><span><?php esc_html_e( 'Archive the source year and its classes and move the classes listed above into the destination year.', 'ohmylms' ); ?></span></label>
			<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Confirm rollover', 'ohmylms' ); ?></button></p>
		</form>
	<?php endif; ?>
</section>
