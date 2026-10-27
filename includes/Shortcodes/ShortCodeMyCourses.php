<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Student My Courses Shortcode
 *
 * @package OhMyLMS\Shortcodes
 * @since 1.0.0
 */

namespace OhMyLMS\Shortcodes;

use OhMyLMS\Data\Student;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin short code my courses implementation.
 */
class ShortCodeMyCourses {

	/**
	 * Render the my courses page
	 *
	 * @param array $atts Shortcode attributes.
	 * @since 1.0.0
	 */
	public static function output( $atts ) {
		// Check if we're in any page-builder preview mode before the cart null guard,
		// so that editor previews work even when the cart session isn't initialised.
		$is_preview_mode = apply_filters( 'ohmylms_gutenberg_preview_mode', false )
			|| apply_filters( 'ohmylms_elementor_preview_mode', false )
			|| apply_filters( 'ohmylms_bricks_preview_mode', false )
			|| apply_filters( 'ohmylms_wpbakery_preview_mode', false );

		// Check cart class is loaded or abort (skip in preview mode).
		if ( ! $is_preview_mode && is_null( ecommerce()->cart ) ) {
			return;
		}

		// Show login form if not logged in and not in preview mode.
		if ( ! is_user_logged_in() && ! $is_preview_mode ) {
			ohmylms_get_template( 'global/toast.php' );
			ohmylms_get_template( 'profile/form-login.php' );
			return;
		}

		// Output the my courses page.
		self::my_courses( $atts ? $atts : array() );
	}

	/**
	 * Display my courses content
	 *
	 * @param array $atts Shortcode attributes.
	 * @since 1.0.0
	 */
	private static function my_courses( $atts ) {
		$args = shortcode_atts(
			array(
				// Header visibility and configuration.
				'show_header'                => 'yes',
				// Header styling.
				'header_bg_color'            => '#000D2C',
				// User menu styling.
				'user_menu_color'            => '#7A8B9A',
				'user_menu_bg_color'         => '#FFFFFF',
				'user_menu_font_size'        => 14,
				'user_menu_font_weight'      => 400,
				// User menu hover styling.
				'user_menu_hover_color'      => '#000D25',
				'user_menu_hover_bg_color'   => '#f5f5f5',
				// User menu icon styling.
				'user_menu_icon_color'       => '#7A8B9A',
				'user_menu_icon_hover_color' => '#4361EE',
				// Section background color.
				'section_bg_color'           => '#F9FAFD',
				// Wrapper styling.
				'wrapper_bg_color'           => '#FFFFFF',
				'wrapper_padding'            => 30,
				// Title typography.
				'title_color'                => '#1E1E1E',
				'title_font_size'            => 24,
				'title_font_weight'          => 600,
				// Text typography.
				'text_color'                 => '#52525B',
				'text_font_size'             => 14,
				'text_font_weight'           => 400,
				// Button styling.
				'button_text_color'          => '#FFFFFF',
				'button_bg_color'            => '#6E42D3',
				'button_font_size'           => 14,
				'button_font_weight'         => 600,
				'button_border_width'        => 0,
				'button_border_color'        => '#6E42D3',
				'button_border_radius'       => 6,
				'button_padding'             => 12,
				'button_hover_bg_color'      => '#5a32c2',
				'button_hover_text_color'    => '#FFFFFF',
				// Course card styling.
				'card_bg_color'              => '#FFFFFF',
				'card_padding'               => 20,
				// Progress bar colors.
				'progress_bar_bg_color'      => '#E5E7EB',
				'progress_bar_fill_color'    => '#6E42D3',
				// Tab styling.
				'tab_normal_color'           => '#666666',
				'tab_active_color'           => '#6E42D3',
				// No course data card styling.
				'no_course_card_bg_color'    => '#F9FAFB',
				'no_course_card_padding'     => 40,
			),
			$atts,
			'ohmylms_my_courses'
		);

		$student = new Student( get_current_user_id() );

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

		// Generate unique ID for this instance.
		$unique_id = 'ohmylms-my-courses-' . uniqid();

		// Output custom styles.
		self::output_custom_styles( $unique_id, $args );

		// Display my courses content.
		echo '<section class="ohmylms-dashboard ' . esc_attr( $unique_id ) . '">';
		echo '<div class="ohmylms-container">';

		// Show notices.
		if ( function_exists( 'ohmylms_show_all_notices' ) ) {
			ohmylms_show_all_notices();
		}

		// My courses content.
		ohmylms_get_template(
			'profile/my-courses.php',
			array(
				'user' => get_user_by( 'id', get_current_user_id() ),
			)
		);

		echo '</div>'; // .ohmylms-container
		echo '</section>'; // .ohmylms-dashboard

		// Add JavaScript to prevent navigation redirects.
		self::add_navigation_handler( $args );
	}

	/**
	 * Output custom styles for the My Courses shortcode.
	 *
	 * @param string $unique_id Unique identifier for this instance.
	 * @param array  $args Shortcode attributes.
	 * @since 1.2.5
	 */
	private static function output_custom_styles( $unique_id, $args ) {
		?>
		<style>
			/* Header styling */
			.ohmylms-header {
				background-color: <?php echo esc_attr( $args['header_bg_color'] ); ?> !important;
			}

			/* User menu styling */
			.ohmylms-user-dropdown a,
			.ohmylms-user-dropdown button,
			.ohmylms-user-dropdown span {
				color: <?php echo esc_attr( $args['user_menu_color'] ); ?> !important;
				font-size: <?php echo esc_attr( $args['user_menu_font_size'] ); ?>px !important;
				font-weight: <?php echo esc_attr( $args['user_menu_font_weight'] ); ?> !important;
			}

			.ohmylms-user-dropdown {
				background-color: <?php echo esc_attr( $args['user_menu_bg_color'] ); ?> !important;
			}

			/* User Menu Hover */
			.ohmylms-user-dropdown li a:hover {
				color: <?php echo esc_attr( $args['user_menu_hover_color'] ); ?> !important;
				background-color: <?php echo esc_attr( $args['user_menu_hover_bg_color'] ); ?> !important;
			}

			/* User Menu Icons */
			.ohmylms-user-dropdown li a svg {
				color: <?php echo esc_attr( $args['user_menu_icon_color'] ); ?> !important;
			}
			.ohmylms-user-dropdown li a svg path {
				fill: <?php echo esc_attr( $args['user_menu_icon_color'] ); ?> !important;
			}

			/* User Menu Icons Hover */
			.ohmylms-user-dropdown li a:hover svg {
				color: <?php echo esc_attr( $args['user_menu_icon_hover_color'] ); ?> !important;
			}
			.ohmylms-user-dropdown li a:hover svg path {
				fill: <?php echo esc_attr( $args['user_menu_icon_hover_color'] ); ?> !important;
			}

			/* Section background */
			.<?php echo esc_attr( $unique_id ); ?> {
				background-color: <?php echo esc_attr( $args['section_bg_color'] ); ?> !important;
			}

			/* Wrapper styling */
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-dashboard-wrapper {
				background-color: <?php echo esc_attr( $args['wrapper_bg_color'] ); ?> !important;
				padding: <?php echo esc_attr( $args['wrapper_padding'] ); ?>px !important;
			}

			/* Title typography */
			.<?php echo esc_attr( $unique_id ); ?> .student-name,
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-dashboard-wrapper h1 {
				color: <?php echo esc_attr( $args['title_color'] ); ?> !important;
				font-size: <?php echo esc_attr( $args['title_font_size'] ); ?>px !important;
				font-weight: <?php echo esc_attr( $args['title_font_weight'] ); ?> !important;
			}

			/* Text typography */
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-dashboard-wrapper,
			.<?php echo esc_attr( $unique_id ); ?> .progressbar-title,
			.<?php echo esc_attr( $unique_id ); ?> .completed-date,
			.<?php echo esc_attr( $unique_id ); ?> .no-course-data p {
				color: <?php echo esc_attr( $args['text_color'] ); ?> !important;
				font-size: <?php echo esc_attr( $args['text_font_size'] ); ?>px !important;
				font-weight: <?php echo esc_attr( $args['text_font_weight'] ); ?> !important;
			}

			/* Button styling - Normal state */
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-button {
				color: <?php echo esc_attr( $args['button_text_color'] ); ?> !important;
				background-color: <?php echo esc_attr( $args['button_bg_color'] ); ?> !important;
				font-size: <?php echo esc_attr( $args['button_font_size'] ); ?>px !important;
				font-weight: <?php echo esc_attr( $args['button_font_weight'] ); ?> !important;
				border: <?php echo esc_attr( $args['button_border_width'] ); ?>px solid <?php echo esc_attr( $args['button_border_color'] ); ?> !important;
				border-radius: <?php echo esc_attr( $args['button_border_radius'] ); ?>px !important;
				padding: <?php echo esc_attr( $args['button_padding'] ); ?>px <?php echo esc_attr( $args['button_padding'] * 2 ); ?>px !important;
			}

			/* Button styling - Hover & Active state */
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-button:hover,
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-button:active,
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-button:focus {
				color: <?php echo esc_attr( $args['button_hover_text_color'] ); ?> !important;
				background-color: <?php echo esc_attr( $args['button_hover_bg_color'] ); ?> !important;
			}

			/* Course card styling */
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-dashboard-single-course {
				background-color: <?php echo esc_attr( $args['card_bg_color'] ); ?> !important;
				padding: <?php echo esc_attr( $args['card_padding'] ); ?>px !important;
			}

			/* Progress bar colors */
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-progressbar-outer {
				background-color: <?php echo esc_attr( $args['progress_bar_bg_color'] ); ?> !important;
			}

			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-progressbar-inner {
				background-color: <?php echo esc_attr( $args['progress_bar_fill_color'] ); ?> !important;
			}

			/* Tab styling - Normal state */
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-my-courses-tab button {
				color: <?php echo esc_attr( $args['tab_normal_color'] ); ?> !important;
			}

			/* Tab styling - Active state */
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-my-courses-tab li.active button,
			.<?php echo esc_attr( $unique_id ); ?> .ohmylms-my-courses-tab button[aria-selected="true"] {
				color: <?php echo esc_attr( $args['tab_active_color'] ); ?> !important;
				border-bottom-color: <?php echo esc_attr( $args['tab_active_color'] ); ?> !important;
			}

			/* No course data card styling */
			.<?php echo esc_attr( $unique_id ); ?> .no-course-data {
				background-color: <?php echo esc_attr( $args['no_course_card_bg_color'] ); ?> !important;
				padding: <?php echo esc_attr( $args['no_course_card_padding'] ); ?>px !important;
			}
		</style>
		<?php
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
		$dashboard_url  = ohmylms_get_page_permalink( 'student_dashboard' );
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Prevent "Dashboard" link in header from redirecting
			const dashboardLink = document.querySelector('.ohmylms-user-dropdown .dashboard-link');
			if (dashboardLink) {
				dashboardLink.addEventListener('click', function(e) {
					e.preventDefault();
					// Redirect to Dashboard page
					window.location.href = '<?php echo esc_url( $dashboard_url ); ?>';
				});
			}

			// Prevent "My Courses" link from redirecting (keep on same page)
			const myCoursesLink = document.querySelector('.ohmylms-user-dropdown .my-course-link');
			if (myCoursesLink) {
				myCoursesLink.addEventListener('click', function(e) {
					e.preventDefault();
					// Reload current page to stay on my courses
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
}
