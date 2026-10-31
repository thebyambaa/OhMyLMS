<?php
/**
 * Template for displaying my courses of student profile
 *
 * The tabs are driven by the `ohmylms/tabs` store (arrow keys, Home and End move between tabs).
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/my-courses.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 */

defined( 'ABSPATH' ) || exit();
$my_course_tab = apply_filters( 'ohmylms_my_course_tabs', array() );
$active_tab    = isset( $my_course_tab['enrolled-courses'] ) ? 'enrolled-courses' : (string) array_key_first( (array) $my_course_tab );
?>
<div class="ohmylms-dashboard-wrapper">
	<h1 class="student-name"><?php echo esc_html__( 'My Courses', 'ohmylms' ); ?></h1>
	<div class="ohmylms-my-courses-tab-nav" data-wp-interactive="ohmylms/tabs" <?php echo wp_interactivity_data_wp_context( array( 'activeTab' => 'course-' . $active_tab ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper escapes the attribute. ?>>
		<div class="ohmylms-my-courses-tab">
			<ul role="tablist" id="my-courses-tab">
				<?php foreach ( $my_course_tab as $key => $course_tab ) : ?>
					<?php $is_active = $active_tab === $key; ?>
					<li class="<?php echo esc_attr( $is_active ? 'active' : '' ); ?>" role="presentation" <?php echo wp_interactivity_data_wp_context( array( 'tabKey' => 'course-' . $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper escapes the attribute. ?> data-wp-class--active="state.selected">
						<button type="button" role="tab" id="<?php echo esc_attr( 'tab-course-' . $key ); ?>" aria-controls="course-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo esc_attr( $is_active ? 'true' : 'false' ); ?>" tabindex="<?php echo esc_attr( $is_active ? '0' : '-1' ); ?>" data-target="#course-<?php echo esc_attr( $key ); ?>"
							data-wp-on--click="actions.select" data-wp-on--keydown="actions.key" data-wp-bind--aria-selected="state.selected" data-wp-bind--tabindex="state.tabIndex">
							<?php echo wp_kses_post( $course_tab['title'] ); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="ohmylms-my-courses-tab-content" id="my-courses-tab-content">
			<?php
			foreach ( $my_course_tab as $key => $course_tab ) :
				$course_tab_class = 'ohmylms-single-tab-content course-' . $key;
				if ( $active_tab === $key ) {
					$course_tab_class .= ' active';
				}
				?>

				<div class="<?php echo esc_attr( $course_tab_class ); ?>" id="<?php echo esc_attr( 'course-' . $key ); ?>" aria-labelledby="<?php echo esc_attr( 'tab-course-' . $key ); ?>" role="tabpanel" tabindex="0" <?php echo wp_interactivity_data_wp_context( array( 'tabKey' => 'course-' . $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper escapes the attribute. ?> data-wp-class--active="state.selected">
					<?php
					if ( isset( $course_tab['callback'] ) ) {
						call_user_func( $course_tab['callback'], $key, $course_tab );
					}
					?>
				</div>
			<?php endforeach; ?>
		</div>

	</div>

</div>
