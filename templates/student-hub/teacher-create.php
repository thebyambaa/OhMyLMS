<?php
/**
 * Native class creation, scoped to independent teaching or administered school years.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\TeacherActions;
use OhMyLMS\Student\TeacherWorkspace;

defined( 'ABSPATH' ) || exit;
?>
<details class="oml-hub-card oml-hub-settings-card oml-hub-class-create oml-hub-class-panel">
	<summary><?php esc_html_e( 'Create a class', 'ohmylms' ); ?></summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php TeacherActions::fields( 'create' ); ?>
		<div class="oml-hub-field"><label for="teacher-class-name"><?php esc_html_e( 'Class name', 'ohmylms' ); ?></label><input id="teacher-class-name" name="name" maxlength="190" required></div>
		<div class="oml-hub-field"><label for="teacher-class-subject"><?php esc_html_e( 'Subject', 'ohmylms' ); ?></label><input id="teacher-class-subject" name="subject" maxlength="100"></div>
		<div class="oml-hub-field"><label for="teacher-class-grade"><?php esc_html_e( 'Grade', 'ohmylms' ); ?></label><input id="teacher-class-grade" name="grade" maxlength="50"></div>
		<div class="oml-hub-field"><label for="teacher-class-year"><?php esc_html_e( 'Class context', 'ohmylms' ); ?></label><select id="teacher-class-year" name="academic_year_id"><option value="0"><?php esc_html_e( 'Independent class', 'ohmylms' ); ?></option>
			<?php foreach ( TeacherWorkspace::contexts() as $teacher_context ) : ?>
				<option value="<?php echo esc_attr( $teacher_context['id'] ); ?>"><?php echo esc_html( $teacher_context['name'] . ' · ' . $teacher_context['label'] ); ?></option>
			<?php endforeach; ?>
		</select></div>
		<p><?php esc_html_e( 'A class is a student roster. Students keep their own course access and learning records.', 'ohmylms' ); ?></p>
		<button class="oml-hub-button" type="submit"><?php esc_html_e( 'Create class', 'ohmylms' ); ?></button>
	</form>
</details>
