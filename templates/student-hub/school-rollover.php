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
	<p><?php esc_html_e( 'Copy class names, subjects and grades into another active year with empty rosters. The source year and its active classes will be archived. Students, assignments and learning records stay in the source classes.', 'ohmylms' ); ?></p>
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
			<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Preview rollover', 'ohmylms' ); ?></button></p>
		</form>
	<?php endif; ?>
	<?php if ( ! empty( $school_flash['rollover'] ) ) : ?>
		<?php $rollover = $school_flash['rollover']; ?>
		<h3><?php echo esc_html( $rollover['from']['label'] . ' → ' . $rollover['to']['label'] ); ?></h3>
		<p><?php echo esc_html( sprintf( /* translators: %d: class count. */ _n( '%d new empty class will be created. Review and confirm within five minutes.', '%d new empty classes will be created. Review and confirm within five minutes.', count( $rollover['classes'] ), 'ohmylms' ), count( $rollover['classes'] ) ) ); ?></p>
		<ul>
		<?php
		foreach ( $rollover['classes'] as $rollover_class ) :
			?>
			<li><?php echo esc_html( $rollover_class['name'] ); ?></li><?php endforeach; ?></ul>
		<form method="post" action="<?php echo esc_url( $school_post ); ?>">
			<?php SchoolActions::fields( 'rollover_commit', $school_id, 'school-classes' ); ?>
			<input type="hidden" name="from_year" value="<?php echo esc_attr( $rollover['from']['id'] ); ?>"><input type="hidden" name="to_year" value="<?php echo esc_attr( $rollover['to']['id'] ); ?>">
			<label class="oml-hub-check"><input type="checkbox" name="confirm" value="yes" required><span><?php esc_html_e( 'Archive the source year and classes and create the empty destination classes.', 'ohmylms' ); ?></span></label>
			<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Confirm rollover', 'ohmylms' ); ?></button></p>
		</form>
	<?php endif; ?>
</section>
