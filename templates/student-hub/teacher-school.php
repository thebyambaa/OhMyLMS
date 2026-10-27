<?php
/**
 * Teacher entry to school administration: create your school, or open it.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\Access;
use OhMyLMS\Student\SchoolActions;
use OhMyLMS\Student\SchoolWorkspace;

defined( 'ABSPATH' ) || exit;

if ( SchoolWorkspace::available() ) {
	echo '<p><a class="oml-hub-pill" href="' . esc_url( SchoolWorkspace::url() ) . '">' . esc_html__( 'Open school workspace', 'ohmylms' ) . '</a></p>';
	return;
}
if ( ! Access::teacher() ) {
	return;
}
$school_timezones = timezone_identifiers_list();
$school_default   = wp_timezone_string();
?>
<details class="oml-hub-card oml-hub-settings-card oml-hub-class-panel">
	<summary><?php esc_html_e( 'Create your school', 'ohmylms' ); ?></summary>
	<p class="oml-hub-family-meta"><?php esc_html_e( 'You become the school’s administrator. You can then invite other teachers, add students and link parents. Your independent classes stay as they are.', 'ohmylms' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
		<?php SchoolActions::fields( 'create_school', 0, 'school-overview' ); ?>
		<div class="oml-hub-field"><label for="cs-name"><?php esc_html_e( 'School name', 'ohmylms' ); ?></label><input id="cs-name" name="name" type="text" required maxlength="190"></div>
		<div class="oml-hub-field"><label for="cs-zone"><?php esc_html_e( 'Time zone', 'ohmylms' ); ?></label>
			<select id="cs-zone" name="timezone">
				<?php foreach ( $school_timezones as $school_zone ) : ?>
					<option value="<?php echo esc_attr( $school_zone ); ?>" <?php selected( in_array( $school_default, $school_timezones, true ) ? $school_default : 'UTC', $school_zone ); ?>><?php echo esc_html( $school_zone ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Create school', 'ohmylms' ); ?></button></p>
	</form>
</details>
