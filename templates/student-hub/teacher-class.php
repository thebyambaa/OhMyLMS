<?php
/**
 * Teacher class settings and student roster management.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\ClassManagement;
use OhMyLMS\Student\Hub;
use OhMyLMS\Student\TeacherActions;
use OhMyLMS\Student\TeacherWorkspace;

defined( 'ABSPATH' ) || exit;

$teacher_active = 'active' === $teacher_current['status'];
$teacher_search = get_query_var( 'hub_q' );
$teacher_search = is_string( $teacher_search ) ? sanitize_text_field( $teacher_search ) : '';
?>
<?php if ( 'class-settings' === $section ) : ?>
	<?php if ( $teacher_active ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php TeacherActions::fields( 'edit', $teacher_class ); ?>
			<div class="oml-hub-field"><label for="teacher-edit-name"><?php esc_html_e( 'Class name', 'ohmylms' ); ?></label><input id="teacher-edit-name" name="name" maxlength="190" value="<?php echo esc_attr( $teacher_current['name'] ); ?>" required></div>
			<div class="oml-hub-field"><label for="teacher-edit-subject"><?php esc_html_e( 'Subject', 'ohmylms' ); ?></label><input id="teacher-edit-subject" name="subject" maxlength="100" value="<?php echo esc_attr( $teacher_current['subject'] ); ?>"></div>
			<div class="oml-hub-field"><label for="teacher-edit-grade"><?php esc_html_e( 'Grade', 'ohmylms' ); ?></label><input id="teacher-edit-grade" name="grade" maxlength="50" value="<?php echo esc_attr( $teacher_current['grade'] ); ?>"></div>
			<button class="oml-hub-button" type="submit"><?php esc_html_e( 'Save class', 'ohmylms' ); ?></button>
		</form>
	<?php else : ?>
		<p><?php esc_html_e( 'This class is archived. Its roster and learning records have been kept.', 'ohmylms' ); ?></p>
	<?php endif; ?>
	<?php if ( ClassManagement::can_archive( $teacher_current ) ) : ?>
		<form class="oml-hub-class-lifecycle" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php TeacherActions::fields( $teacher_active ? 'archive' : 'restore', $teacher_class ); ?>
			<?php if ( $teacher_active ) : ?>
				<h3><?php esc_html_e( 'Archive this class', 'ohmylms' ); ?></h3>
				<p><?php esc_html_e( 'Archiving hides the class from active lists. You can restore it later.', 'ohmylms' ); ?></p>
				<label class="oml-hub-check"><input type="checkbox" name="confirm" value="yes" required><span><?php esc_html_e( 'I want to archive this class.', 'ohmylms' ); ?></span></label>
			<?php endif; ?>
			<button class="oml-hub-button" type="submit"><?php echo esc_html( $teacher_active ? __( 'Archive class', 'ohmylms' ) : __( 'Restore class', 'ohmylms' ) ); ?></button>
		</form>
	<?php else : ?>
		<p><?php esc_html_e( 'A school administrator manages archiving for this class.', 'ohmylms' ); ?></p>
	<?php endif; ?>
<?php else : ?>
	<form class="oml-hub-roster-search" method="get" action="<?php echo esc_url( Hub::base_url() ); ?>">
		<?php
		if ( ! get_option( 'permalink_structure' ) ) :
			?>
			<input type="hidden" name="page_id" value="<?php echo esc_attr( Hub::settings()['page_id'] ); ?>"><?php endif; ?>
		<input type="hidden" name="hub_role" value="teacher"><input type="hidden" name="hub_section" value="roster"><input type="hidden" name="hub_class" value="<?php echo esc_attr( $teacher_class ); ?>">
		<label for="teacher-roster-search"><?php esc_html_e( 'Search students by name', 'ohmylms' ); ?></label><input type="search" id="teacher-roster-search" name="hub_q" value="<?php echo esc_attr( $teacher_search ); ?>" maxlength="190">
		<button class="oml-hub-pill" type="submit"><?php esc_html_e( 'Search', 'ohmylms' ); ?></button>
	</form>
	<?php
	$teacher_students      = ClassManagement::students( $teacher_class, $teacher_search, $teacher_page );
	$teacher_more_students = 20 < count( $teacher_students );
	$teacher_students      = array_slice( $teacher_students, 0, 20 );
	?>
	<h3><?php esc_html_e( 'Students', 'ohmylms' ); ?></h3>
	<ul class="oml-hub-commerce-list">
		<?php foreach ( $teacher_students as $teacher_student ) : ?>
			<li><strong><?php echo esc_html( $teacher_student['display_name'] ); ?></strong>
				<?php if ( $teacher_active ) : ?>
					<details class="oml-hub-roster-remove"><summary><?php esc_html_e( 'Remove', 'ohmylms' ); ?></summary>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php TeacherActions::fields( 'remove', $teacher_class ); ?>
							<input type="hidden" name="student_id" value="<?php echo esc_attr( $teacher_student['user_id'] ); ?>">
							<p><?php esc_html_e( 'Remove this student from this roster? Their account and learning history will stay intact.', 'ohmylms' ); ?></p>
							<label class="oml-hub-check"><input type="checkbox" name="confirm" value="yes" required><span><?php esc_html_e( 'Confirm removal', 'ohmylms' ); ?></span></label>
							<button class="oml-hub-pill" type="submit"><?php esc_html_e( 'Remove from class', 'ohmylms' ); ?></button>
						</form>
					</details>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
	if ( ! $teacher_students ) :
		?>
		<p><?php esc_html_e( 'No students match this view.', 'ohmylms' ); ?></p><?php endif; ?>
	<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Roster pages', 'ohmylms' ); ?>">
		<?php
		if ( 1 < $teacher_page ) :
			?>
			<a class="oml-hub-pill" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'hub_class'    => $teacher_class,
						'hub_q'        => $teacher_search,
						'current_page' => $teacher_page - 1,
					),
					TeacherWorkspace::url( 'roster' )
				)
			);
			?>
			"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a><?php endif; ?>
		<?php
		if ( $teacher_more_students ) :
			?>
			<a class="oml-hub-pill" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'hub_class'    => $teacher_class,
						'hub_q'        => $teacher_search,
						'current_page' => $teacher_page + 1,
					),
					TeacherWorkspace::url( 'roster' )
				)
			);
			?>
			"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a><?php endif; ?>
	</nav>
	<?php if ( $teacher_active && (int) $teacher_current['school_id'] ) : ?>
		<details class="oml-hub-class-add" <?php echo 'add' === get_query_var( 'hub_view' ) ? 'open' : ''; ?>><summary><?php esc_html_e( 'Add a school student', 'ohmylms' ); ?></summary>
			<p><?php esc_html_e( 'Search above to find eligible students on this school’s approved roster.', 'ohmylms' ); ?></p>
			<?php
			$teacher_candidate_page = min( 10000, max( 1, absint( get_query_var( 'hub_candidate_page', 1 ) ) ) );
			$teacher_candidates     = ClassManagement::students( $teacher_class, $teacher_search, $teacher_candidate_page, true );
			?>
			<?php if ( $teacher_candidates ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php TeacherActions::fields( 'add', $teacher_class ); ?>
					<div class="oml-hub-field"><label for="teacher-add-student"><?php esc_html_e( 'Student', 'ohmylms' ); ?></label><select name="student_id" id="teacher-add-student" required><option value=""><?php esc_html_e( 'Choose a student', 'ohmylms' ); ?></option>
						<?php
						foreach ( array_slice( $teacher_candidates, 0, 20 ) as $teacher_candidate ) :
							?>
							<option value="<?php echo esc_attr( $teacher_candidate['user_id'] ); ?>"><?php echo esc_html( $teacher_candidate['display_name'] ); ?></option><?php endforeach; ?>
					</select></div><button class="oml-hub-button" type="submit"><?php esc_html_e( 'Add student', 'ohmylms' ); ?></button>
				</form>
				<p><?php esc_html_e( 'Showing up to 20 eligible students. Refine the search to find another student.', 'ohmylms' ); ?></p>
				<?php
			else :
				?>
				<p><?php esc_html_e( 'No eligible school students match this view.', 'ohmylms' ); ?></p><?php endif; ?>
			<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Eligible student pages', 'ohmylms' ); ?>">
				<?php
				if ( 1 < $teacher_candidate_page ) :
					?>
					<a class="oml-hub-pill" href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'hub_class'          => $teacher_class,
								'hub_q'              => $teacher_search,
								'hub_candidate_page' => $teacher_candidate_page - 1,
							),
							TeacherWorkspace::url( 'roster', 'add' )
						)
					);
					?>
					"><?php esc_html_e( 'Previous eligible students', 'ohmylms' ); ?></a><?php endif; ?>
				<?php
				if ( 20 < count( $teacher_candidates ) ) :
					?>
					<a class="oml-hub-pill" href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'hub_class'          => $teacher_class,
								'hub_q'              => $teacher_search,
								'hub_candidate_page' => $teacher_candidate_page + 1,
							),
							TeacherWorkspace::url( 'roster', 'add' )
						)
					);
					?>
					"><?php esc_html_e( 'Next eligible students', 'ohmylms' ); ?></a><?php endif; ?>
			</nav>
		</details>
	<?php elseif ( $teacher_active ) : ?>
		<details class="oml-hub-class-add"><summary><?php esc_html_e( 'Invite a student', 'ohmylms' ); ?></summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php TeacherActions::fields( 'invite', $teacher_class ); ?>
				<div class="oml-hub-field"><label for="teacher-invite-email"><?php esc_html_e( 'Student email', 'ohmylms' ); ?></label><input id="teacher-invite-email" name="email" type="email" required></div>
				<p><?php esc_html_e( 'Create a private, single-use link for this email address. The student signs in and accepts it. No email is sent automatically.', 'ohmylms' ); ?></p>
				<button class="oml-hub-button" type="submit"><?php esc_html_e( 'Create invitation link', 'ohmylms' ); ?></button>
			</form>
		</details>
		<?php $teacher_invitations = ClassManagement::invitations( $teacher_class ); ?>
		<?php if ( $teacher_invitations ) : ?>
			<h3><?php esc_html_e( 'Pending invitations', 'ohmylms' ); ?></h3>
			<ul class="oml-hub-commerce-list">
				<?php foreach ( $teacher_invitations as $teacher_invitation ) : ?>
					<li><span><?php echo esc_html( $teacher_invitation['email'] ); ?></span><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php TeacherActions::fields( 'revoke', $teacher_class ); ?><input type="hidden" name="invitation_id" value="<?php echo esc_attr( $teacher_invitation['id'] ); ?>"><button class="oml-hub-pill" type="submit"><?php esc_html_e( 'Revoke invitation', 'ohmylms' ); ?></button></form></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>
