<?php
/**
 * School administration workspace: overview, classes, people and invitations.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\Service;
use OhMyLMS\Schools\Lifecycle;
use OhMyLMS\Schools\Settings;
use OhMyLMS\Student\Hub;
use OhMyLMS\Student\SchoolActions;
use OhMyLMS\Student\SchoolWorkspace;

defined( 'ABSPATH' ) || exit;

$school_all   = SchoolWorkspace::schools();
$school       = SchoolWorkspace::current();
$school_flash = SchoolActions::flash();
if ( ! $school ) {
	echo '<div class="oml-hub-empty"><h2>' . esc_html__( 'No school to administer', 'ohmylms' ) . '</h2><p>' . esc_html__( 'You are not an administrator of an active school.', 'ohmylms' ) . '</p></div>';
	return;
}
$school_id   = (int) $school['id'];
$school_post = admin_url( 'admin-post.php' );
$school_page = max( 1, absint( get_query_var( 'current_page', 1 ) ) );
?>
<div class="oml-hub-family">
	<section class="oml-hub-banner">
		<h1><?php echo esc_html( $school['name'] ); ?></h1>
		<?php if ( count( $school_all ) > 1 ) : ?>
			<form method="get" action="<?php echo esc_url( Hub::base_url() ? Hub::base_url() : home_url( '/' ) ); ?>">
				<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
					<input type="hidden" name="page_id" value="<?php echo esc_attr( Hub::settings()['page_id'] ); ?>">
				<?php endif; ?>
				<input type="hidden" name="hub_role" value="school">
				<input type="hidden" name="hub_section" value="<?php echo esc_attr( $section ); ?>">
				<label class="screen-reader-text" for="school-switch"><?php esc_html_e( 'School', 'ohmylms' ); ?></label>
				<select id="school-switch" name="hub_school">
					<?php foreach ( $school_all as $school_option ) : ?>
						<option value="<?php echo esc_attr( $school_option['id'] ); ?>" <?php selected( $school_id, (int) $school_option['id'] ); ?>><?php echo esc_html( $school_option['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="oml-hub-pill"><?php esc_html_e( 'Switch school', 'ohmylms' ); ?></button>
			</form>
		<?php endif; ?>
	</section>
	<?php if ( $school_flash && ! empty( $school_flash['error'] ) ) : ?>
		<p class="oml-hub-notice is-error" role="alert"><?php echo esc_html( $school_flash['error'] ); ?></p>
	<?php elseif ( $school_flash ) : ?>
		<div class="oml-hub-notice oml-hub-family-flash" role="status">
			<p class="oml-hub-family-flash-text"><strong><?php echo esc_html( $school_flash['message'] ); ?></strong></p>
			<?php if ( ! empty( $school_flash['invite_url'] ) ) : ?>
				<label class="oml-hub-family-label" for="school-invite-url"><?php esc_html_e( 'Invitation link (shown once, valid for 48 hours)', 'ohmylms' ); ?></label>
				<input id="school-invite-url" type="text" readonly value="<?php echo esc_attr( $school_flash['invite_url'] ); ?>">
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( 'school-overview' === $section ) : ?>
		<?php $school_counts = SchoolWorkspace::counts( $school_id ); ?>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'Overview', 'ohmylms' ); ?></h2>
			<ul class="oml-hub-commerce-list">
				<li><div><strong><?php esc_html_e( 'Students', 'ohmylms' ); ?></strong></div><span><?php echo esc_html( $school_counts['student'] ); ?></span></li>
				<li><div><strong><?php esc_html_e( 'Teachers', 'ohmylms' ); ?></strong></div><span><?php echo esc_html( $school_counts['teacher'] ); ?></span></li>
				<li><div><strong><?php esc_html_e( 'School administrators', 'ohmylms' ); ?></strong></div><span><?php echo esc_html( $school_counts['school_admin'] ); ?></span></li>
				<li><div><strong><?php esc_html_e( 'Active classes', 'ohmylms' ); ?></strong></div><span><?php echo esc_html( $school_counts['classes'] ); ?></span></li>
				<li><div><strong><?php esc_html_e( 'Pending invitations', 'ohmylms' ); ?></strong></div><span><?php echo esc_html( $school_counts['invitations'] ); ?></span></li>
			</ul>
			<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'School tasks', 'ohmylms' ); ?>">
				<a class="oml-hub-pill" href="<?php echo esc_url( SchoolWorkspace::url( 'school-classes', '', $school_id ) ); ?>"><?php esc_html_e( 'Manage classes', 'ohmylms' ); ?></a>
				<a class="oml-hub-pill" href="<?php echo esc_url( SchoolWorkspace::url( 'school-people', '', $school_id ) ); ?>"><?php esc_html_e( 'Manage people', 'ohmylms' ); ?></a>
				<a class="oml-hub-pill" href="<?php echo esc_url( SchoolWorkspace::url( 'school-invites', '', $school_id ) ); ?>"><?php esc_html_e( 'Invite someone', 'ohmylms' ); ?></a>
			</nav>
		</section>

	<?php elseif ( in_array( $section, array( 'school-reports', 'school-gradebook' ), true ) ) : ?>
		<?php ohmylms_get_template( 'student-hub/school-reports.php', compact( 'school', 'school_id', 'school_post', 'school_page', 'section' ) ); ?>
	<?php elseif ( 'school-classes' === $section ) : ?>
		<?php
		$school_years   = Service::years( $school_id );
		$school_active  = array_values(
			array_filter(
				$school_years,
				static function ( $school_year_row ) {
					return 'active' === $school_year_row['status'];
				}
			)
		);
		$school_classes = Service::classes( $school_id, $school_page );
		?>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'Classes', 'ohmylms' ); ?></h2>
			<?php if ( ! $school_classes ) : ?>
				<p class="oml-hub-family-meta"><?php esc_html_e( 'No classes yet.', 'ohmylms' ); ?></p>
			<?php else : ?>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $school_classes as $school_class ) : ?>
						<li>
							<div><strong><?php echo esc_html( $school_class['name'] ); ?></strong><p class="oml-hub-family-meta"><?php echo esc_html( trim( $school_class['subject'] . ' ' . $school_class['grade'] ) ); ?> · <?php echo esc_html( 'active' === $school_class['status'] ? __( 'Active', 'ohmylms' ) : __( 'Archived', 'ohmylms' ) ); ?></p></div>
							<?php if ( 'active' === $school_class['status'] ) : ?>
								<form method="post" action="<?php echo esc_url( $school_post ); ?>" class="oml-hub-family-actions">
									<?php SchoolActions::fields( 'archive_class', $school_id, 'school-classes' ); ?>
									<input type="hidden" name="class_id" value="<?php echo esc_attr( $school_class['id'] ); ?>">
									<label class="oml-hub-family-confirm"><input type="checkbox" name="confirm" value="yes" required> <?php esc_html_e( 'Confirm archiving', 'ohmylms' ); ?></label>
									<button type="submit" class="oml-hub-pill oml-hub-pill-danger"><?php esc_html_e( 'Archive', 'ohmylms' ); ?></button>
								</form>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Class pages', 'ohmylms' ); ?>">
				<?php if ( $school_page > 1 ) : ?>
					<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $school_page - 1, SchoolWorkspace::url( 'school-classes', '', $school_id ) ) ); ?>"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a>
				<?php endif; ?>
				<?php if ( count( $school_classes ) >= 50 ) : ?>
					<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $school_page + 1, SchoolWorkspace::url( 'school-classes', '', $school_id ) ) ); ?>"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a>
				<?php endif; ?>
			</nav>
			<details>
				<summary><?php esc_html_e( 'Create a class', 'ohmylms' ); ?></summary>
				<?php if ( ! $school_active ) : ?>
					<p class="oml-hub-family-meta"><?php esc_html_e( 'Create an academic year first.', 'ohmylms' ); ?></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $school_post ); ?>" autocomplete="off">
						<?php SchoolActions::fields( 'create_class', $school_id, 'school-classes' ); ?>
						<div class="oml-hub-field"><label for="sc-name"><?php esc_html_e( 'Class name', 'ohmylms' ); ?></label><input id="sc-name" name="name" type="text" required maxlength="190"></div>
						<div class="oml-hub-field"><label for="sc-subject"><?php esc_html_e( 'Subject', 'ohmylms' ); ?></label><input id="sc-subject" name="subject" type="text" maxlength="100"></div>
						<div class="oml-hub-field"><label for="sc-grade"><?php esc_html_e( 'Grade', 'ohmylms' ); ?></label><input id="sc-grade" name="grade" type="text" maxlength="50"></div>
						<div class="oml-hub-field"><label for="sc-year"><?php esc_html_e( 'Academic year', 'ohmylms' ); ?></label><select id="sc-year" name="academic_year_id">
						<?php
						foreach ( $school_active as $school_year_opt ) :
							?>
							<option value="<?php echo esc_attr( $school_year_opt['id'] ); ?>"><?php echo esc_html( $school_year_opt['label'] ); ?></option><?php endforeach; ?></select></div>
						<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Create class', 'ohmylms' ); ?></button></p>
					</form>
				<?php endif; ?>
			</details>
			<details>
				<summary><?php esc_html_e( 'Add an academic year', 'ohmylms' ); ?></summary>
				<form method="post" action="<?php echo esc_url( $school_post ); ?>" autocomplete="off">
					<?php SchoolActions::fields( 'create_year', $school_id, 'school-classes' ); ?>
					<div class="oml-hub-field"><label for="sy-label"><?php esc_html_e( 'Label (for example 2026–27)', 'ohmylms' ); ?></label><input id="sy-label" name="label" type="text" required maxlength="100"></div>
					<div class="oml-hub-field"><label for="sy-start"><?php esc_html_e( 'Starts', 'ohmylms' ); ?></label><input id="sy-start" name="starts_at" type="date" required></div>
					<div class="oml-hub-field"><label for="sy-end"><?php esc_html_e( 'Ends', 'ohmylms' ); ?></label><input id="sy-end" name="ends_at" type="date" required></div>
					<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Add year', 'ohmylms' ); ?></button></p>
				</form>
			</details>
		</section>

		<?php ohmylms_get_template( 'student-hub/school-rollover.php', compact( 'school_id', 'school_post', 'school_active', 'school_flash' ) ); ?>
	<?php elseif ( 'school-people' === $section ) : ?>
		<?php
		$school_search  = sanitize_text_field( (string) get_query_var( 'hub_q', '' ) );
		$school_roster  = Service::roster( $school_id, 0, $school_page, $school_search );
		$school_parents = Service::guardians( $school_id );
		$school_labels  = array(
			'student'      => __( 'Student', 'ohmylms' ),
			'teacher'      => __( 'Teacher', 'ohmylms' ),
			'school_admin' => __( 'School administrator', 'ohmylms' ),
		);
		?>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'People', 'ohmylms' ); ?></h2>
			<form class="oml-hub-people-search" method="get" action="<?php echo esc_url( Hub::base_url() ? Hub::base_url() : home_url( '/' ) ); ?>">
				<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
					<input type="hidden" name="page_id" value="<?php echo esc_attr( Hub::settings()['page_id'] ); ?>">
				<?php endif; ?>
				<input type="hidden" name="hub_role" value="school"><input type="hidden" name="hub_section" value="school-people"><input type="hidden" name="hub_school" value="<?php echo esc_attr( $school_id ); ?>">
				<label for="school-search"><?php esc_html_e( 'Search by name or student ID', 'ohmylms' ); ?></label>
				<input id="school-search" name="hub_q" type="search" value="<?php echo esc_attr( $school_search ); ?>">
				<button type="submit" class="oml-hub-pill"><?php esc_html_e( 'Search', 'ohmylms' ); ?></button>
			</form>
			<?php if ( ! $school_roster ) : ?>
				<p class="oml-hub-family-meta"><?php esc_html_e( 'No people match this view.', 'ohmylms' ); ?></p>
			<?php else : ?>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $school_roster as $school_person ) : ?>
						<li><div><strong><?php echo esc_html( $school_person['display_name'] ); ?></strong><p class="oml-hub-family-meta"><span class="oml-hub-chip"><?php echo esc_html( $school_labels[ $school_person['role'] ] ?? $school_person['role'] ); ?></span><?php echo ! empty( $school_person['external_student_id'] ) ? ' ' . esc_html( $school_person['external_student_id'] ) : ''; ?></p></div>
							<?php if ( 'student' === $school_person['role'] && Lifecycle::managed_student( $school_id, (int) $school_person['user_id'] ) ) : ?>
								<form method="post" action="<?php echo esc_url( $school_post ); ?>" class="oml-hub-family-actions">
									<?php SchoolActions::fields( 'student_recovery', $school_id, 'school-people' ); ?>
									<input type="hidden" name="student_user_id" value="<?php echo esc_attr( $school_person['user_id'] ); ?>">
									<label class="oml-hub-family-confirm"><input type="checkbox" name="confirm" value="yes" required> <?php esc_html_e( 'Replace unused recovery links', 'ohmylms' ); ?></label>
									<button type="submit" class="oml-hub-pill"><?php esc_html_e( 'Create password reset link', 'ohmylms' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( get_current_user_id() !== (int) $school_person['user_id'] ) : ?>
								<form method="post" action="<?php echo esc_url( $school_post ); ?>" class="oml-hub-family-actions">
									<?php SchoolActions::fields( 'remove_member', $school_id, 'school-people' ); ?>
									<input type="hidden" name="member_id" value="<?php echo esc_attr( $school_person['id'] ); ?>">
									<label class="oml-hub-family-confirm"><input type="checkbox" name="confirm" value="yes" required> <?php esc_html_e( 'Confirm removal', 'ohmylms' ); ?></label>
									<button type="submit" class="oml-hub-pill oml-hub-pill-danger"><?php esc_html_e( 'Remove', 'ohmylms' ); ?></button>
								</form>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'People pages', 'ohmylms' ); ?>">
				<?php if ( $school_page > 1 ) : ?>
					<a class="oml-hub-pill" href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'current_page' => $school_page - 1,
								'hub_q'        => $school_search,
							),
							SchoolWorkspace::url( 'school-people', '', $school_id )
						)
					);
					?>
													"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a>
				<?php endif; ?>
				<?php if ( count( $school_roster ) >= 50 ) : ?>
					<a class="oml-hub-pill" href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'current_page' => $school_page + 1,
								'hub_q'        => $school_search,
							),
							SchoolWorkspace::url( 'school-people', '', $school_id )
						)
					);
					?>
													"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a>
				<?php endif; ?>
			</nav>
			<?php if ( $school_flash && ! empty( $school_flash['import_rows'] ) ) : ?>
				<h3><?php esc_html_e( 'Import preview', 'ohmylms' ); ?></h3>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $school_flash['import_rows'] as $import_row ) : ?>
						<li><div><strong><?php echo esc_html( $import_row['name'] ?? ( '#' . $import_row['line'] ) ); ?></strong><p class="oml-hub-family-meta"><?php echo esc_html( sprintf( /* translators: 1: line number, 2: school student ID. */ __( 'Line %1$d · %2$s', 'ohmylms' ), $import_row['line'], $import_row['external_student_id'] ?? '' ) ); ?><?php echo ! empty( $import_row['error'] ) ? ' · ' . esc_html( $import_row['error'] ) : ''; ?></p></div><span class="oml-hub-chip"><?php echo esc_html( $import_row['status'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( ! empty( $school_flash['can_commit'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( $school_post ); ?>">
						<?php SchoolActions::fields( 'import_commit', $school_id, 'school-people' ); ?>
						<textarea name="csv" hidden><?php echo esc_textarea( $school_flash['csv'] ); ?></textarea>
						<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Import the new students', 'ohmylms' ); ?></button></p>
					</form>
				<?php endif; ?>
			<?php endif; ?>
			<details>
				<summary><?php esc_html_e( 'Import students from CSV', 'ohmylms' ); ?></summary>
				<form method="post" action="<?php echo esc_url( $school_post ); ?>">
					<?php SchoolActions::fields( 'import_preview', $school_id, 'school-people' ); ?>
					<div class="oml-hub-field"><label for="si-csv"><?php esc_html_e( 'Paste up to 200 students (columns: name, external_student_id)', 'ohmylms' ); ?></label><textarea id="si-csv" name="csv" rows="7" required><?php echo esc_textarea( "name,external_student_id\n" ); ?></textarea></div>
					<p class="oml-hub-family-meta"><?php esc_html_e( 'You see a preview first. Existing school IDs are skipped.', 'ohmylms' ); ?></p>
					<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Preview import', 'ohmylms' ); ?></button></p>
				</form>
			</details>
			<details>
				<summary><?php esc_html_e( 'Add a student', 'ohmylms' ); ?></summary>
				<form method="post" action="<?php echo esc_url( $school_post ); ?>" autocomplete="off">
					<?php SchoolActions::fields( 'create_student', $school_id, 'school-people' ); ?>
					<div class="oml-hub-field"><label for="ss-name"><?php esc_html_e( 'Student name', 'ohmylms' ); ?></label><input id="ss-name" name="name" type="text" required maxlength="190"></div>
					<div class="oml-hub-field"><label for="ss-id"><?php esc_html_e( 'School student ID', 'ohmylms' ); ?></label><input id="ss-id" name="external_student_id" type="text" required maxlength="100"></div>
					<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Add student', 'ohmylms' ); ?></button></p>
				</form>
			</details>
		</section>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'Parent links', 'ohmylms' ); ?></h2>
			<?php if ( ! $school_parents ) : ?>
				<p class="oml-hub-family-meta"><?php esc_html_e( 'No parents are linked yet. Invite a parent for a student from Invitations.', 'ohmylms' ); ?></p>
			<?php else : ?>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $school_parents as $school_link ) : ?>
						<li>
							<div><strong><?php echo esc_html( $school_link['parent_name'] ); ?></strong><p class="oml-hub-family-meta"><?php echo esc_html( sprintf( /* translators: %s: student name. */ __( 'Parent of %s', 'ohmylms' ), $school_link['student_name'] ) ); ?> · <?php echo esc_html( $school_link['status'] ); ?></p></div>
							<?php if ( 'active' === $school_link['status'] ) : ?>
								<form method="post" action="<?php echo esc_url( $school_post ); ?>" class="oml-hub-family-actions">
									<?php SchoolActions::fields( 'revoke_guardian', $school_id, 'school-people' ); ?>
									<input type="hidden" name="guardian_link_id" value="<?php echo esc_attr( $school_link['id'] ); ?>">
									<label class="oml-hub-family-confirm"><input type="checkbox" name="confirm" value="yes" required> <?php esc_html_e( 'Confirm removal', 'ohmylms' ); ?></label>
									<button type="submit" class="oml-hub-pill oml-hub-pill-danger"><?php esc_html_e( 'Remove link', 'ohmylms' ); ?></button>
								</form>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

	<?php elseif ( 'school-invites' === $section ) : ?>
		<?php
		$school_students = array_values(
			array_filter(
				Service::roster( $school_id, 0, 1, '' ),
				static function ( $person ) {
					return 'student' === $person['role'];
				}
			)
		);
		$school_invites  = array_values(
			array_filter(
				Service::invitations( $school_id ),
				static function ( $row ) {
					return ! $row['consumed_at'] && ! $row['revoked_at'] && $row['expires_at'] > gmdate( 'Y-m-d H:i:s' );
				}
			)
		);
		$school_roles    = array(
			'teacher'      => __( 'Teacher', 'ohmylms' ),
			'school_admin' => __( 'School administrator', 'ohmylms' ),
			'student'      => __( 'Student (by email)', 'ohmylms' ),
			'guardian'     => __( 'Parent of a student', 'ohmylms' ),
			'activation'   => __( 'Password setup for a student you created', 'ohmylms' ),
		);
		?>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'Invitations', 'ohmylms' ); ?></h2>
			<p class="oml-hub-family-meta"><?php esc_html_e( 'Links are private, single use and expire after 48 hours. No email is sent; share the link yourself.', 'ohmylms' ); ?></p>
			<form method="post" action="<?php echo esc_url( $school_post ); ?>" autocomplete="off">
				<?php SchoolActions::fields( 'invite', $school_id, 'school-invites' ); ?>
				<div class="oml-hub-field"><label for="si-role"><?php esc_html_e( 'Invite as', 'ohmylms' ); ?></label><select id="si-role" name="role">
				<?php
				foreach ( $school_roles as $role_key => $role_label ) :
					?>
					<option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_label ); ?></option><?php endforeach; ?></select></div>
				<div class="oml-hub-field"><label for="si-email"><?php esc_html_e( 'Recipient email (not needed for password setup)', 'ohmylms' ); ?></label><input id="si-email" name="email" type="email" maxlength="190"></div>
				<div class="oml-hub-field"><label for="si-student"><?php esc_html_e( 'Student (for parent and password setup)', 'ohmylms' ); ?></label><select id="si-student" name="student_user_id"><option value=""><?php esc_html_e( 'None', 'ohmylms' ); ?></option>
				<?php
				foreach ( $school_students as $student_option ) :
					?>
					<option value="<?php echo esc_attr( $student_option['user_id'] ); ?>"><?php echo esc_html( $student_option['display_name'] ); ?></option><?php endforeach; ?></select></div>
				<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Create invitation link', 'ohmylms' ); ?></button></p>
			</form>
			<?php if ( $school_invites ) : ?>
				<h3><?php esc_html_e( 'Pending invitations', 'ohmylms' ); ?></h3>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $school_invites as $school_invite ) : ?>
						<li>
							<div><strong><?php echo esc_html( $school_roles[ $school_invite['role'] ] ?? $school_invite['role'] ); ?></strong><p class="oml-hub-family-meta"><?php echo esc_html( $school_invite['email'] ? $school_invite['email'] : __( 'Password setup', 'ohmylms' ) ); ?> · <?php echo esc_html( sprintf( /* translators: %s: expiry date and time. */ __( 'Expires %s', 'ohmylms' ), get_date_from_gmt( $school_invite['expires_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ); ?></p></div>
							<form method="post" action="<?php echo esc_url( $school_post ); ?>">
								<?php SchoolActions::fields( 'revoke_invite', $school_id, 'school-invites' ); ?>
								<input type="hidden" name="invitation_id" value="<?php echo esc_attr( $school_invite['id'] ); ?>">
								<button type="submit" class="oml-hub-pill"><?php esc_html_e( 'Revoke', 'ohmylms' ); ?></button>
							</form>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	<?php elseif ( 'school-settings' === $section ) : ?>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'School settings', 'ohmylms' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $school_post ); ?>" autocomplete="off">
				<?php SchoolActions::fields( 'update_school', $school_id, 'school-settings' ); ?>
				<div class="oml-hub-field"><label for="ssn-name"><?php esc_html_e( 'School name', 'ohmylms' ); ?></label><input id="ssn-name" name="name" type="text" required maxlength="190" value="<?php echo esc_attr( $school['name'] ); ?>"></div>
				<div class="oml-hub-field"><label for="ssn-zone"><?php esc_html_e( 'Time zone', 'ohmylms' ); ?></label>
					<select id="ssn-zone" name="timezone">
						<?php foreach ( timezone_identifiers_list() as $school_zone ) : ?>
							<option value="<?php echo esc_attr( $school_zone ); ?>" <?php selected( $school['timezone'], $school_zone ); ?>><?php echo esc_html( $school_zone ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Save changes', 'ohmylms' ); ?></button></p>
			</form>
		</section>
		<?php
		$school_own    = Settings::overrides( $school_id );
		$school_accent = Settings::get( 'accent', $school_id );
		$school_logo   = Settings::get( 'logo', $school_id, 'school' );
		?>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'School design', 'ohmylms' ); ?></h2>
			<p class="oml-hub-muted"><?php esc_html_e( 'Choose your own colour and logo for this school. Anything you leave unchanged uses the site default.', 'ohmylms' ); ?></p>
			<form method="post" action="<?php echo esc_url( $school_post ); ?>" enctype="multipart/form-data" autocomplete="off">
				<?php SchoolActions::fields( 'update_design', $school_id, 'school-settings' ); ?>
				<div class="oml-hub-field">
					<label for="ssd-accent"><?php esc_html_e( 'Accent colour', 'ohmylms' ); ?></label>
					<input id="ssd-accent" name="accent" type="color" value="<?php echo esc_attr( $school_accent ); ?>">
					<small><?php echo isset( $school_own['accent'] ) ? esc_html__( 'Custom colour for this school.', 'ohmylms' ) : esc_html__( 'Using the site default.', 'ohmylms' ); ?></small>
				</div>
				<?php if ( isset( $school_own['accent'] ) ) : ?>
					<div class="oml-hub-field"><label><input type="checkbox" name="reset_accent" value="1"> <?php esc_html_e( 'Go back to the site colour', 'ohmylms' ); ?></label></div>
				<?php endif; ?>
				<div class="oml-hub-field">
					<label for="ssd-logo"><?php esc_html_e( 'Logo (JPG, PNG or WebP, up to 1 MB)', 'ohmylms' ); ?></label>
					<?php if ( '' !== $school_logo ) : ?>
						<img src="<?php echo esc_url( $school_logo ); ?>" alt="" width="48" height="48" decoding="async">
					<?php endif; ?>
					<input id="ssd-logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
				</div>
				<?php if ( isset( $school_own['logo'] ) ) : ?>
					<div class="oml-hub-field"><label><input type="checkbox" name="remove_logo" value="1"> <?php esc_html_e( 'Remove our logo and use the site logo', 'ohmylms' ); ?></label></div>
				<?php endif; ?>
				<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Save design', 'ohmylms' ); ?></button></p>
			</form>
		</section>
	<?php endif; ?>
</div>
