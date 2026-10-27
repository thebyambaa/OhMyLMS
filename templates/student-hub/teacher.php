<?php
/**
 * Teacher classes and distributed work in the shared workspace shell.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Service;
use OhMyLMS\Student\TeacherActions;
use OhMyLMS\Student\TeacherWorkspace;

defined( 'ABSPATH' ) || exit;

if ( ! TeacherWorkspace::available() ) {
	return;
}
$teacher_page    = min( 10000, max( 1, absint( get_query_var( 'current_page', 1 ) ) ) );
$teacher_status  = 'archived' === get_query_var( 'hub_view' ) ? 'archived' : 'active';
$teacher_classes = TeacherWorkspace::classes( $teacher_page, $teacher_status );
$teacher_more    = 12 < count( $teacher_classes );
$teacher_classes = array_slice( $teacher_classes, 0, 12 );
$teacher_class   = absint( get_query_var( 'hub_class', 0 ) );
$teacher_current = $teacher_class ? Access::classroom( $teacher_class ) : null;
$teacher_flash   = TeacherActions::flash();
$teacher_titles  = array(
	'classes'        => __( 'My classes', 'ohmylms' ),
	'work'           => __( 'Assignments', 'ohmylms' ),
	'roster'         => __( 'Roster', 'ohmylms' ),
	'progress'       => __( 'Reports', 'ohmylms' ),
	'class-settings' => __( 'Class settings', 'ohmylms' ),
);
?>
<section class="oml-hub-teacher-header">
	<h1><?php echo esc_html( $teacher_titles[ $section ] ); ?></h1>
	<p class="oml-hub-teacher-description"><?php esc_html_e( 'Keep your classes and assigned learning connected.', 'ohmylms' ); ?></p>
</section>
<?php if ( $teacher_flash ) : ?>
	<div class="oml-hub-card oml-hub-settings-card oml-hub-class-panel" role="<?php echo empty( $teacher_flash['error'] ) ? 'status' : 'alert'; ?>">
		<p><?php echo esc_html( $teacher_flash['message'] ); ?></p>
		<?php if ( ! empty( $teacher_flash['invite_url'] ) ) : ?>
			<label for="teacher-private-link"><?php esc_html_e( 'Private invitation link — copy it now', 'ohmylms' ); ?></label><input class="oml-hub-invite-link" id="teacher-private-link" type="text" readonly value="<?php echo esc_attr( $teacher_flash['invite_url'] ); ?>">
		<?php endif; ?>
	</div>
<?php endif; ?>
<?php if ( $teacher_class && ! $teacher_current ) : ?>
	<div class="oml-hub-empty"><?php esc_html_e( 'This class is not available.', 'ohmylms' ); ?></div>
<?php elseif ( ! $teacher_current ) : ?>
	<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class status', 'ohmylms' ); ?>">
		<a class="oml-hub-pill" href="<?php echo esc_url( TeacherWorkspace::url( $section ) ); ?>" <?php echo 'active' === $teacher_status ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Active classes', 'ohmylms' ); ?></a>
		<a class="oml-hub-pill" href="<?php echo esc_url( TeacherWorkspace::url( $section, 'archived' ) ); ?>" <?php echo 'archived' === $teacher_status ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Archived classes', 'ohmylms' ); ?></a>
	</nav>
	<?php ohmylms_get_template( 'student-hub/teacher-create.php' ); ?>
	<?php ohmylms_get_template( 'student-hub/teacher-school.php' ); ?>
	<div class="oml-hub-commerce-grid">
		<?php foreach ( $teacher_classes as $teacher_row ) : ?>
			<section class="oml-hub-card oml-hub-settings-card oml-hub-class-panel">
				<h2><?php echo esc_html( $teacher_row['name'] ); ?></h2>
				<p><?php echo esc_html( $teacher_row['subject'] ); ?></p>
				<a class="oml-hub-button" href="<?php echo esc_url( add_query_arg( 'hub_class', $teacher_row['id'], TeacherWorkspace::url( $section ) ) ); ?>"><?php esc_html_e( 'Open class', 'ohmylms' ); ?></a>
			</section>
		<?php endforeach; ?>
	</div>
	<?php if ( ! $teacher_classes ) : ?>
		<div class="oml-hub-empty"><h2><?php esc_html_e( 'No classes to show', 'ohmylms' ); ?></h2><p><?php esc_html_e( 'Create a class above, or switch between active and archived classes.', 'ohmylms' ); ?></p></div>
	<?php endif; ?>
	<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class pages', 'ohmylms' ); ?>">
		<?php
		if ( 1 < $teacher_page ) :
			?>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $teacher_page - 1, TeacherWorkspace::url( $section, 'archived' === $teacher_status ? 'archived' : '' ) ) ); ?>"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a><?php endif; ?>
		<?php
		if ( $teacher_more ) :
			?>
			<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $teacher_page + 1, TeacherWorkspace::url( $section, 'archived' === $teacher_status ? 'archived' : '' ) ) ); ?>"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a><?php endif; ?>
	</nav>
<?php else : ?>
	<section class="oml-hub-card oml-hub-settings-card oml-hub-class-panel">
		<a class="oml-hub-pill oml-hub-back" href="<?php echo esc_url( TeacherWorkspace::url( $section, 'archived' === $teacher_current['status'] ? 'archived' : '' ) ); ?>">&larr; <?php esc_html_e( 'All classes', 'ohmylms' ); ?></a>
		<h2><?php echo esc_html( $teacher_current['name'] ); ?></h2>
		<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class views', 'ohmylms' ); ?>">
			<?php foreach ( array( 'work', 'roster', 'progress', 'class-settings' ) as $teacher_tab ) : ?>
				<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'hub_class', $teacher_class, TeacherWorkspace::url( $teacher_tab ) ) ); ?>" <?php echo ( $teacher_tab === $section || ( 'classes' === $section && 'roster' === $teacher_tab ) ) ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $teacher_titles[ $teacher_tab ] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php if ( in_array( $section, array( 'classes', 'roster', 'class-settings' ), true ) ) : ?>
			<?php ohmylms_get_template( 'student-hub/teacher-class.php', compact( 'section', 'teacher_current', 'teacher_class', 'teacher_page' ) ); ?>
		<?php elseif ( 'active' !== $teacher_current['status'] ) : ?>
			<p><?php esc_html_e( 'This class is archived. Restore it in Class settings to resume management.', 'ohmylms' ); ?></p>
		<?php else : ?>
			<?php if ( 'progress' === $section ) : ?>
				<?php ohmylms_get_template( 'student-hub/teacher-report.php', compact( 'teacher_class', 'teacher_page' ) ); ?>
			<?php endif; ?>
			<?php $teacher_work = Service::assignments( $teacher_class, 0, 0, $teacher_page ); ?>
		<p><?php esc_html_e( 'Distributed class work only. Independent learning is not included.', 'ohmylms' ); ?></p>
		<ul class="oml-hub-commerce-list">
			<?php foreach ( $teacher_work as $teacher_assignment ) : ?>
				<li><div><strong><?php echo esc_html( $teacher_assignment['title'] ); ?></strong><p><?php echo esc_html( $teacher_assignment['student_name'] ); ?></p></div><span><?php echo esc_html( $teacher_assignment['completed_at'] ? __( 'Completed', 'ohmylms' ) : __( 'Not completed', 'ohmylms' ) ); ?></span></li>
			<?php endforeach; ?>
		</ul>
			<?php
			if ( ! $teacher_work ) :
				?>
			<p><?php esc_html_e( 'No distributed work on this page.', 'ohmylms' ); ?></p><?php endif; ?>
		<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Assigned work pages', 'ohmylms' ); ?>">
			<?php
			if ( 1 < $teacher_page ) :
				?>
				<a class="oml-hub-pill" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'hub_class'    => $teacher_class,
							'current_page' => $teacher_page - 1,
						),
						TeacherWorkspace::url( $section )
					)
				);
				?>
				"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a><?php endif; ?>
			<?php
			if ( 50 === count( $teacher_work ) ) :
				?>
				<a class="oml-hub-pill" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'hub_class'    => $teacher_class,
							'current_page' => $teacher_page + 1,
						),
						TeacherWorkspace::url( $section )
					)
				);
				?>
				"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a><?php endif; ?>
		</nav>
		<?php endif; ?>
	</section>
<?php endif; ?>
