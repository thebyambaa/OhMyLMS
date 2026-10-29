<?php
/**
 * One student workspace. Override in yourtheme/ohmylms/student-hub/workspace.php.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;
use OhMyLMS\Student\ParentWorkspace;
use OhMyLMS\Student\SchoolWorkspace;
use OhMyLMS\Student\TeacherWorkspace;
use OhMyLMS\Practice\Dashboard;
use OhMyLMS\Practice\Frontend;

defined( 'ABSPATH' ) || exit;

$user        = wp_get_current_user();
$parent_mode = ! empty( $parent_mode );
$school_mode = ! empty( $school_mode );
// A school can override the site accent and logo for everyone working in that school.
$hub_school_id = $school_mode && SchoolWorkspace::current() ? (int) SchoolWorkspace::current()['id'] : \OhMyLMS\Schools\Settings::school_for_user();
// Following the brand sets no inline accent, so the stylesheet's token (and its dark variant) applies.
$hub_style = $config['accent_brand'] ? '' : \OhMyLMS\Schools\Settings::style( $config['accent'] );
if ( $hub_school_id ) {
	$hub_own_accent = \OhMyLMS\Schools\Settings::overrides( $hub_school_id )['accent'] ?? '';
	$hub_style      = '' !== $hub_own_accent ? \OhMyLMS\Schools\Settings::style( $hub_own_accent ) : $hub_style;
}
$hub_role = $teacher_mode ? 'teacher' : ( $parent_mode ? 'parent' : ( $school_mode ? 'school' : 'learner' ) );
$hub_url  = $teacher_mode ? array( TeacherWorkspace::class, 'url' ) : ( $parent_mode ? array( ParentWorkspace::class, 'url' ) : ( $school_mode ? array( SchoolWorkspace::class, 'url' ) : array( Hub::class, 'url' ) ) );
?>
<?php
$hub_icons = array(
	'home'             => 'M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
	'classes'          => 'M3 5h18v12H3zM8 21h8M12 17v4',
	'achievements'     => 'M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3',
	'history'          => 'M3 12a9 9 0 1 0 3-6.7M3 4v5h5M12 7v5l3 2',
	'library'          => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM8 7h8M8 11h6',
	'learn'            => 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM8 7h7',
	'work'             => 'M9 11l3 3 8-8M4 5h10M4 12h3M4 19h13',
	'roster'           => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 4a4 4 0 0 1 0 7M22 21v-2a4 4 0 0 0-3-4',
	'class-settings'   => 'M4 7h16M4 17h16M8 4v6M16 14v6',
	'school-overview'  => 'M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
	'school-classes'   => 'M3 5h18v12H3zM8 21h8M12 17v4',
	'school-reports'   => 'M4 20h16M7 16V9M12 16V4M17 16v-5',
	'school-gradebook' => 'M4 3h16v18H4zM8 7h8M8 11h8M8 15h4',
	'school-people'    => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 4a4 4 0 0 1 0 7M22 21v-2a4 4 0 0 0-3-4',
	'school-settings'  => 'M4 7h16M4 17h16M8 4v6M16 14v6',
	'school-invites'   => 'M4 4h16v16H4zM4 8l8 6 8-6',
	'family'           => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 4a4 4 0 0 1 0 7M22 21v-2a4 4 0 0 0-3-4',
	'progress'         => 'M4 20V10M10 20V4M16 20v-8M22 20H2',
	'account'          => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12l2-1-2-4-2 1-2-1-1-2h-4l-1 2-2 1-2-1-2 4 2 1v2l-2 1 2 4 2-1 2 1 1 2h4l1-2 2-1 2 1 2-4-2-1z',
	'member'           => 'M4 7h16v12H4zM4 7l3-4h10l3 4M9 13h6',
	'membership'       => 'M4 7h16v12H4zM4 7l3-4h10l3 4M9 13h6',
);
$hub_icon  = static function ( $key ) use ( $hub_icons ) {
	return '<svg class="oml-hub-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . esc_attr( $hub_icons[ $key ] ?? $hub_icons['home'] ) . '"/></svg>';
};
$hub_main  = array_values( array_diff( $config['tabs'], array( 'account', 'home' ) ) );
?>
<div class="ohmylms ohmylms-page">
	<section class="ohmylms-student-hub" data-layout="<?php echo esc_attr( $config['layout'] ); ?>" data-role="<?php echo esc_attr( $hub_role ); ?>" data-section="<?php echo esc_attr( $section ); ?>" data-view="<?php echo esc_attr( $view ); ?>" data-theme="light" style="<?php echo esc_attr( $hub_style ); ?>">
		<?php
		// Apply the saved theme before first paint so navigating does not flash the light default.
		wp_print_inline_script_tag( "try{var t=localStorage.getItem('ohmylms-hub-theme');if(t==='system'||t==='dark'||t==='light'){document.currentScript.parentElement.dataset.theme=t;}}catch(e){}" );
		?>
		<aside class="oml-hub-rail" id="oml-hub-rail">
			<div class="oml-hub-rail-head">
				<a class="oml-hub-brand" href="<?php echo esc_url( $hub_url( $teacher_mode ? 'classes' : ( $parent_mode ? 'family' : ( $school_mode ? 'school-overview' : '' ) ) ) ); ?>">
				<?php
				$hub_logo = \OhMyLMS\Design\HubIcons::logo_markup( $school_mode ? 'school' : ( $teacher_mode ? 'teacher' : ( $parent_mode ? 'parent' : 'learner' ) ) );
				$hub_own  = $hub_school_id ? \OhMyLMS\Schools\Settings::overrides( $hub_school_id ) : array();
				if ( ! empty( $hub_own['logo'] ) ) {
					$hub_logo = '<img class="oml-hub-logo oml-hub-logo--custom" src="' . esc_url( (string) wp_get_attachment_image_url( $hub_own['logo'], 'thumbnail' ) ) . '" alt="" width="28" height="28" decoding="async">';
				}
				echo '' !== $hub_logo ? $hub_logo : '<span class="oml-hub-logo" aria-hidden="true">✦</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in HubIcons::logo_markup().
				?>
				<span><?php echo esc_html( $config['title'] ); ?></span></a>
				<button type="button" class="oml-hub-rail-close" data-hub-rail-close aria-label="<?php esc_attr_e( 'Close menu', 'ohmylms' ); ?>">&times;</button>
			</div>
			<nav class="oml-hub-nav" aria-label="<?php echo esc_attr( $teacher_mode ? __( 'Teacher workspace', 'ohmylms' ) : ( $parent_mode ? __( 'Family workspace', 'ohmylms' ) : ( $school_mode ? __( 'School workspace', 'ohmylms' ) : __( 'Student workspace', 'ohmylms' ) ) ) ); ?>">
				<?php foreach ( $hub_main as $hub_tab ) : ?>
					<a href="<?php echo esc_url( $teacher_mode && absint( get_query_var( 'hub_class', 0 ) ) ? add_query_arg( 'hub_class', absint( get_query_var( 'hub_class' ) ), $hub_url( $hub_tab ) ) : $hub_url( $hub_tab ) ); ?>" <?php echo $hub_tab === $section ? 'aria-current="page"' : ''; ?>><?php echo $hub_icon( $hub_tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG built from an allowlist. ?><span><?php echo esc_html( $config['labels'][ $hub_tab ] ); ?></span></a>
				<?php endforeach; ?>
			</nav>
			<div class="oml-hub-rail-foot">
				<a href="<?php echo esc_url( $hub_url( 'account', 'account' ) ); ?>" <?php echo 'account' === $section ? 'aria-current="page"' : ''; ?>><?php echo $hub_icon( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG built from an allowlist. ?><span><?php esc_html_e( 'Settings', 'ohmylms' ); ?></span></a>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( Hub::admin_home_url() ); ?>"><?php echo $hub_icon( 'progress' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG built from an allowlist. ?><span><?php esc_html_e( 'Customize', 'ohmylms' ); ?></span></a>
				<?php endif; ?>
				<div class="oml-hub-theme" role="group" aria-label="<?php esc_attr_e( 'Appearance on this device', 'ohmylms' ); ?>">
					<button type="button" data-hub-theme-set="system"><?php esc_html_e( 'Auto', 'ohmylms' ); ?></button>
					<button type="button" data-hub-theme-set="light"><?php esc_html_e( 'Light', 'ohmylms' ); ?></button>
					<button type="button" data-hub-theme-set="dark"><?php esc_html_e( 'Dark', 'ohmylms' ); ?></button>
				</div>
				<div class="oml-hub-account">
					<?php echo get_avatar( $user->ID, 32, '', '', array( 'class' => 'oml-hub-avatar' ) ); ?>
					<span class="oml-hub-account-name"><?php echo esc_html( $user->display_name ); ?></span>
					<a class="oml-hub-signout" href="<?php echo esc_url( wp_logout_url( Hub::url() ) ); ?>" aria-label="<?php esc_attr_e( 'Sign out', 'ohmylms' ); ?>"><svg class="oml-hub-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></a>
				</div>
			</div>
		</aside>
		<div class="oml-hub-main">
			<header class="oml-hub-topbar">
				<button type="button" class="oml-hub-menu" data-hub-rail-open aria-controls="oml-hub-rail" aria-expanded="false"><svg class="oml-hub-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'ohmylms' ); ?></span></button>
				<p class="oml-hub-topbar-title"><?php echo esc_html( $config['labels'][ $section ] ); ?></p>
				<?php $hub_teacher = TeacherWorkspace::available(); ?>
				<?php $hub_parent = ParentWorkspace::available(); ?>
				<?php $hub_school = SchoolWorkspace::available(); ?>
				<?php if ( $hub_teacher || $hub_parent || $hub_school ) : ?>
					<details class="oml-hub-role-switch">
						<summary><?php echo esc_html( $teacher_mode ? __( 'Teacher', 'ohmylms' ) : ( $parent_mode ? __( 'Parent', 'ohmylms' ) : ( $school_mode ? __( 'School', 'ohmylms' ) : __( 'Learner', 'ohmylms' ) ) ) ); ?> · <?php esc_html_e( 'Switch role', 'ohmylms' ); ?></summary>
						<div class="oml-hub-role-options">
							<a href="<?php echo esc_url( Hub::url( 'learn' ) ); ?>"><?php esc_html_e( 'Learner', 'ohmylms' ); ?></a>
							<?php if ( $hub_teacher ) : ?>
								<a href="<?php echo esc_url( TeacherWorkspace::url() ); ?>"><?php esc_html_e( 'Teacher', 'ohmylms' ); ?></a>
							<?php endif; ?>
							<?php if ( $hub_parent ) : ?>
								<a href="<?php echo esc_url( ParentWorkspace::url() ); ?>"><?php esc_html_e( 'Parent', 'ohmylms' ); ?></a>
							<?php endif; ?>
							<?php if ( $hub_school ) : ?>
								<a href="<?php echo esc_url( SchoolWorkspace::url() ); ?>"><?php esc_html_e( 'School', 'ohmylms' ); ?></a>
							<?php endif; ?>
						</div>
					</details>
				<?php endif; ?>
			</header>
			<div class="oml-hub-content ohmylms-dashboard" id="student-home-content" <?php echo Hub::uses_workspace_landmark() ? 'role="main" tabindex="-1"' : ''; ?>>
				<?php
				if ( function_exists( 'ohmylms_show_all_notices' ) ) {
					ohmylms_show_all_notices(); }
				?>
				<?php if ( $views && 'classes' !== $section ) : ?>
					<nav class="oml-hub-subnav" aria-label="<?php echo esc_attr( $config['labels'][ $section ] ); ?>">
						<?php foreach ( $views as $key => $label ) : ?>
							<?php
							if ( 'invoice' === $key && 'invoice' !== $view ) {
								continue; }
							?>
							<a class="oml-hub-pill" href="<?php echo esc_url( 'classes' === $section ? add_query_arg( 'hub_class', absint( get_query_var( 'hub_class', 0 ) ), $hub_url( $section, $key ) ) : $hub_url( $section, $key ) ); ?>" <?php echo $key === $view ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
				<?php
				if ( $teacher_mode && in_array( $section, array( 'classes', 'work', 'roster', 'progress', 'class-settings' ), true ) ) {
					ohmylms_get_template( 'student-hub/teacher.php', array( 'section' => $section ) );
				} elseif ( $school_mode && 0 === strpos( $section, 'school-' ) ) {
					ohmylms_get_template( 'student-hub/school.php', array( 'section' => $section ) );
				} elseif ( $parent_mode && 'family' === $section ) {
					ohmylms_get_template( 'student-hub/parent.php' );
				} elseif ( 'home' === $section ) {
					$dashboard = Dashboard::data( $user->ID );
					ohmylms_get_template( 'student-hub/home.php', compact( 'config', 'student', 'dashboard', 'user' ) );
					do_action( 'ohmylms_lms_student_dashboard_sections' );
				} elseif ( 'history' === $section ) {
					ohmylms_get_template( 'student-hub/history.php' );
				} elseif ( 'achievements' === $section ) {
					ohmylms_get_template( 'student-hub/achievements.php', array( 'student' => $student ) );
				} elseif ( 'library' === $section && absint( get_query_var( 'hub_course', 0 ) ) ) {
					ohmylms_get_template( 'student-hub/course.php' );
				} elseif ( 'library' === $section ) {
					ohmylms_get_template( 'student-hub/library.php', array( 'config' => $config ) );
				} elseif ( 'classes' === $section ) {
					ohmylms_get_template( 'student-hub/classes.php', array( 'view' => $view ) );
				} elseif ( 'membership' === $section ) {
					ohmylms_get_template( 'student-hub/membership.php', array( 'view' => $view ) );
				} elseif ( 'account' === $section ) {
					if ( in_array( $view, array( 'account', 'appearance', 'notifications', 'classes' ), true ) ) {
						ohmylms_get_template( 'student-hub/settings.php', compact( 'view', 'teacher_mode', 'parent_mode', 'school_mode' ) );
					} else {
						echo '<div class="ohmylms-student-profile oml-hub-legacy">';
						Hub::account( $view );
						echo '</div>';
					}
				} elseif ( 'learn' === $section ) {
					ohmylms_get_template( 'student-hub/my-learning.php' );
				} elseif ( 'progress' === $section && 'achievements' === $view ) {
					ohmylms_get_template( 'student-hub/achievements.php', array( 'student' => $student ) );
				} elseif ( 'progress' === $section && 'skills' === $view ) {
					// The existing renderer escapes its own complete HTML fragment.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted shortcode renderer with context-specific escaping.
					echo '<div class="oml-hub-legacy">' . Frontend::skills() . '</div>';
				} else {
					ohmylms_get_template( 'student-hub/learning.php', compact( 'section', 'view', 'student' ) );
				}
				?>
			</div>
		</div>
	</section>
</div>
