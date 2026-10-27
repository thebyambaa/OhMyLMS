<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Student Dashboard Shortcode
 *
 * @package OhMyLMS\Shortcodes
 * @since 1.0.0
 */

namespace OhMyLMS\Shortcodes;

use OhMyLMS\Commerce\DataStores;
use OhMyLMS\Data\Student;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin short code dashboard implementation.
 */
class ShortCodeDashboard {

	/**
	 * Render the dashboard
	 *
	 * @param array $atts Shortcode attributes.
	 * @since 1.0.0
	 */
	public static function output( $atts ) {
		// Check cart class is loaded or abort.
		if ( is_null( ecommerce()->cart ) ) {
			return;
		}

		// Show login form if not logged in.
		if ( ! is_user_logged_in() ) {
			ohmylms_get_template( 'global/toast.php' );
			ohmylms_get_template( 'profile/form-login.php' );
			return;
		}

		// Output the dashboard.
		self::dashboard( $atts ? $atts : array() );
	}

	/**
	 * Display dashboard content
	 *
	 * @param array $atts Shortcode attributes.
	 * @since 1.0.0
	 */
	private static function dashboard( $atts ) {
		$args = shortcode_atts(
			array(
				// Header visibility and configuration.
				'show_header'                    => 'yes',
				// Header styling.
				'header_bg_color'                => '#000D2C',
				// User menu styling.
				'user_menu_color'                => '#7A8B9A',
				'user_menu_bg_color'             => '#FFFFFF',
				'user_menu_font_size'            => 14,
				'user_menu_font_weight'          => 400,
				// User menu hover styling.
				'user_menu_hover_color'          => '#000D25',
				'user_menu_hover_bg_color'       => '#f5f5f5',
				// User menu icon styling.
				'user_menu_icon_color'           => '#7A8B9A',
				'user_menu_icon_hover_color'     => '#4361EE',
				// Dashboard background.
				'dashboard_bg_color'             => '#F9FAFD',
				// Course card button styling.
				'course_button_text_color'       => '#FFFFFF',
				'course_button_bg_color'         => '#4361EE',
				'course_button_font_size'        => 15,
				'course_button_font_weight'      => 500,
				'course_button_border_radius'    => 10,
				'course_button_hover_text_color' => '#ffffff',
				'course_button_hover_bg_color'   => 'transparent',
				// Card styling.
				'card_bg_color'                  => '#FFFFFF',
				'card_text_color'                => '#52525B',
				'card_text_font_size'            => 14,
				'card_text_font_weight'          => 400,
				'card_number_color'              => '#1E1E1E',
				'card_number_font_size'          => 30,
				'card_number_font_weight'        => 700,
				// Title styling.
				'title_color'                    => '#1E1E1E',
				'title_font_size'                => 22,
				'title_font_weight'              => 700,
			),
			$atts,
			'ohmylms_dashboard'
		);

		$student = new Student( get_current_user_id() );

		// Generate custom styles.
		self::generate_custom_styles( $args );

		// Display header with navigation if enabled.
		$show_header = filter_var( $args['show_header'], FILTER_VALIDATE_BOOLEAN );
		if ( $show_header ) {
			ohmylms_get_template(
				'global/main-header.php',
				array(
					'student' => $student,
				)
			);
		}

		// Display dashboard content.
		echo '<section class="ohmylms-dashboard">';
		echo '<div class="ohmylms-container">';

		// Show notices.
		if ( function_exists( 'ohmylms_show_all_notices' ) ) {
			ohmylms_show_all_notices();
		}

		do_action( 'ohmylms_lms_student_profile_before_dashboard_content' );

		ohmylms_get_template(
			'profile/dashboard-content.php',
			array(
				'student' => $student,
			)
		);

		/**
		 * Add-ons such as Course Bundles render their own learner sections below the standard content.
		 *
		 * @since NEXT
		 */
		do_action( 'ohmylms_lms_student_dashboard_sections' );

		echo '</div>';
		echo '</section>';

		// Add JavaScript to prevent navigation redirects.
		self::add_navigation_handler( $args );
	}

	/**
	 * Add JavaScript to handle navigation links
	 *
	 * @param array $args Shortcode attributes.
	 * @since 1.0.0
	 */
	private static function add_navigation_handler( $args = array() ) {
		// Get URLs from global settings.
		$my_profile_url = ohmylms_get_nav_link_url( 'profile' );
		$my_courses_url = ohmylms_get_nav_link_url( 'courses' );
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Prevent "My Courses" link in header from redirecting
			const myCoursesLink = document.querySelector('.ohmylms-user-dropdown .my-course-link');
			if (myCoursesLink) {
				myCoursesLink.addEventListener('click', function(e) {
					e.preventDefault();
					// Redirect to My Courses page
					window.location.href = '<?php echo $my_courses_url; ?>';
				});
			}

			// Prevent "Dashboard" link from redirecting (keep on same page)
			const dashboardLink = document.querySelector('.ohmylms-user-dropdown .dashboard-link');
			if (dashboardLink) {
				dashboardLink.addEventListener('click', function(e) {
					e.preventDefault();
					// Reload current page to stay on dashboard
					window.location.reload();
				});
			}

			// Handle "My Profile" link
			const myProfileLink = document.querySelector('.ohmylms-user-dropdown .my-profile-link');
			if (myProfileLink) {
				myProfileLink.addEventListener('click', function(e) {
					e.preventDefault();
					// Redirect to My Profile page
					window.location.href = '<?php echo $my_profile_url; ?>';
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Generate custom styles based on shortcode attributes
	 *
	 * @param array $args Shortcode attributes.
	 * @since 1.0.0
	 */
	private static function generate_custom_styles( $args ) {
		// Sanitize values.
		$header_bg_color                = sanitize_hex_color( $args['header_bg_color'] );
		$user_menu_color                = sanitize_hex_color( $args['user_menu_color'] );
		$user_menu_bg_color             = sanitize_hex_color( $args['user_menu_bg_color'] );
		$user_menu_font_size            = absint( $args['user_menu_font_size'] );
		$user_menu_font_weight          = absint( $args['user_menu_font_weight'] );
		$user_menu_hover_color          = sanitize_hex_color( $args['user_menu_hover_color'] );
		$user_menu_hover_bg_color       = sanitize_hex_color( $args['user_menu_hover_bg_color'] );
		$user_menu_icon_color           = sanitize_hex_color( $args['user_menu_icon_color'] );
		$user_menu_icon_hover_color     = sanitize_hex_color( $args['user_menu_icon_hover_color'] );
		$dashboard_bg_color             = sanitize_hex_color( $args['dashboard_bg_color'] );
		$course_button_text_color       = sanitize_hex_color( $args['course_button_text_color'] );
		$course_button_bg_color         = sanitize_hex_color( $args['course_button_bg_color'] );
		$course_button_font_size        = absint( $args['course_button_font_size'] );
		$course_button_font_weight      = absint( $args['course_button_font_weight'] );
		$course_button_border_radius    = absint( $args['course_button_border_radius'] );
		$course_button_hover_text_color = sanitize_hex_color( $args['course_button_hover_text_color'] );
		$course_button_hover_bg_color   = sanitize_hex_color( $args['course_button_hover_bg_color'] );

		// Generate CSS.
		?>
		<style id="ohmylms-dashboard-custom-styles">
			/* Header Background */
			<?php if ( $header_bg_color ) : ?>
			.ohmylms-page .ohmylms-header {
				background-color: <?php echo esc_attr( $header_bg_color ); ?> !important;
			}
			<?php endif; ?>

			/* User Menu Dropdown */
			<?php if ( $user_menu_bg_color ) : ?>
			.ohmylms-page .ohmylms-header-right .ohmylms-user .ohmylms-user-dropdown {
				background-color: <?php echo esc_attr( $user_menu_bg_color ); ?> !important;
			}
			<?php endif; ?>

			<?php if ( $user_menu_color || $user_menu_font_size || $user_menu_font_weight ) : ?>
			.ohmylms-page .ohmylms-header-right .ohmylms-user .ohmylms-user-dropdown li a {
				<?php if ( $user_menu_color ) : ?>
				color: <?php echo esc_attr( $user_menu_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $user_menu_font_size ) : ?>
				font-size: <?php echo esc_attr( $user_menu_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $user_menu_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $user_menu_font_weight ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* User Menu Hover */
			<?php if ( $user_menu_hover_color || $user_menu_hover_bg_color ) : ?>
			.ohmylms-page .ohmylms-header-right .ohmylms-user .ohmylms-user-dropdown li a:hover {
				<?php if ( $user_menu_hover_color ) : ?>
				color: <?php echo esc_attr( $user_menu_hover_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $user_menu_hover_bg_color ) : ?>
				background-color: <?php echo esc_attr( $user_menu_hover_bg_color ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* User Menu Icons */
			<?php if ( $user_menu_icon_color ) : ?>
			.ohmylms-page .ohmylms-header-right .ohmylms-user .ohmylms-user-dropdown li a svg {
				color: <?php echo esc_attr( $user_menu_icon_color ); ?> !important;
			}
			.ohmylms-page .ohmylms-header-right .ohmylms-user .ohmylms-user-dropdown li a svg path {
				fill: <?php echo esc_attr( $user_menu_icon_color ); ?> !important;
			}
			<?php endif; ?>

			/* User Menu Icons Hover */
			<?php if ( $user_menu_icon_hover_color ) : ?>
			.ohmylms-page .ohmylms-header-right .ohmylms-user .ohmylms-user-dropdown li a:hover svg {
				color: <?php echo esc_attr( $user_menu_icon_hover_color ); ?> !important;
			}
			.ohmylms-page .ohmylms-header-right .ohmylms-user .ohmylms-user-dropdown li a:hover svg path {
				fill: <?php echo esc_attr( $user_menu_icon_hover_color ); ?> !important;
			}
			<?php endif; ?>

			/* Dashboard Background */
			<?php if ( $dashboard_bg_color ) : ?>
			.ohmylms-page .ohmylms-dashboard {
				background-color: <?php echo esc_attr( $dashboard_bg_color ); ?> !important;
			}
			<?php endif; ?>

			/* Course Card Buttons */
			<?php if ( $course_button_text_color || $course_button_bg_color || $course_button_font_size || $course_button_font_weight || $course_button_border_radius ) : ?>
			.ohmylms-page .ohmylms-dashboard-single-course .ohmylms-btn-area .ohmylms-button {
				<?php if ( $course_button_text_color ) : ?>
				color: <?php echo esc_attr( $course_button_text_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $course_button_bg_color ) : ?>
				background-color: <?php echo esc_attr( $course_button_bg_color ); ?> !important;
				border-color: <?php echo esc_attr( $course_button_bg_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $course_button_font_size ) : ?>
				font-size: <?php echo esc_attr( $course_button_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $course_button_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $course_button_font_weight ); ?> !important;
				<?php endif; ?>
				<?php if ( $course_button_border_radius ) : ?>
				border-radius: <?php echo esc_attr( $course_button_border_radius ); ?>px !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Course Card Buttons Hover */
			<?php if ( $course_button_hover_text_color || $course_button_hover_bg_color ) : ?>
			.ohmylms-page .ohmylms-dashboard-single-course .ohmylms-btn-area .ohmylms-button:hover {
				<?php if ( $course_button_hover_text_color ) : ?>
				color: <?php echo esc_attr( $course_button_hover_text_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $course_button_hover_bg_color ) : ?>
				background-color: <?php echo esc_attr( $course_button_hover_bg_color ); ?> !important;
				border-color: <?php echo esc_attr( $course_button_hover_bg_color ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

		</style>
		<?php
	}
}
