<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Student Profile Shortcode
 *
 * @package OhMyLMS\Shortcodes
 * @since 1.0.0
 */

namespace OhMyLMS\Shortcodes;

use OhMyLMS\Data\Student;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin short code profile implementation.
 */
class ShortCodeProfile {

	/**
	 * Render the profile
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

		// Output the profile.
		self::profile( $atts ? $atts : array() );
	}

	/**
	 * Display profile content
	 *
	 * @param array $atts Shortcode attributes.
	 * @since 1.0.0
	 */
	private static function profile( $atts ) {
		$args = shortcode_atts(
			array(
				// Header visibility and configuration.
				'show_header'                       => 'yes',
				// Header styling.
				'header_bg_color'                   => '#000D2C',
				// User menu styling.
				'user_menu_color'                   => '#7A8B9A',
				'user_menu_bg_color'                => '#FFFFFF',
				'user_menu_font_size'               => 14,
				'user_menu_font_weight'             => 400,
				// User menu hover styling.
				'user_menu_hover_color'             => '#000D25',
				'user_menu_hover_bg_color'          => '#f5f5f5',
				// User menu icon styling.
				'user_menu_icon_color'              => '#7A8B9A',
				'user_menu_icon_hover_color'        => '#4361EE',
				// Section background.
				'section_bg_color'                  => '#F9FAFD',
				// Profile wrapper styling.
				'wrapper_border'                    => '#EBECEF',
				'wrapper_bg_color'                  => '#F8F8F8',
				'wrapper_shadow'                    => '0px 2px 8px 0px #ECECEC',
				// Sidebar items styling.
				'sidebar_item_color'                => '#1E1E1E',
				'sidebar_item_font_size'            => 14,
				'sidebar_item_font_weight'          => 400,
				'sidebar_item_bg_color'             => 'transparent',
				'sidebar_item_active_color'         => '#4361EE',
				'sidebar_item_active_font_size'     => 14,
				'sidebar_item_active_font_weight'   => 400,
				'sidebar_item_active_bg_color'      => '#FFFFFF',
				// Sidebar content styling.
				'sidebar_content_bg_color'          => '#FFFFFF',
				'sidebar_content_shadow'            => '0px 1px 2px 0px #DBDDE1',
				'sidebar_content_padding'           => 40,
				'sidebar_content_title_color'       => '#1E1E1E',
				'sidebar_content_title_font_size'   => 24,
				'sidebar_content_title_font_weight' => 600,
				// Profile info styling.
				'profile_name_color'                => '#1E1E1E',
				'profile_name_font_size'            => 20,
				'profile_name_font_weight'          => 700,
				'profile_bio_color'                 => '#52525B',
				'profile_bio_font_size'             => 15,
				'profile_bio_font_weight'           => 400,
				// Profile edit button styling.
				'profile_edit_color'                => '#1E1E1E',
				'profile_edit_bg_color'             => '#FFFFFF',
				'profile_edit_border'               => '#EBEBEF',
				'profile_edit_font_size'            => 14,
				'profile_edit_font_weight'          => 500,
				'profile_edit_hover_color'          => '#1E1E1E',
				'profile_edit_hover_bg_color'       => '#f6f6f6',
				// Basic info section styling.
				'basic_info_bg_color'               => '#FFFFFF',
				'basic_info_shadow'                 => '0px 1px 4px 0px #D3D6DD',
				'basic_info_padding'                => 21,
				'basic_info_title_color'            => '#1E1E1E',
				'basic_info_title_font_size'        => 18,
				'basic_info_title_font_weight'      => 600,
				// Input styling.
				'input_bg_color'                    => '#FFFFFF',
				'input_border'                      => '#c8d2e980',
				'input_text_color'                  => '#52525B',
				'input_placeholder_color'           => '#7A8B9A',
				'input_font_size'                   => 14,
				'input_padding'                     => 13,
				'input_border_radius'               => 8,
				// Label styling.
				'label_color'                       => '#1E1E1E',
				'label_font_size'                   => 14,
				'label_font_weight'                 => 500,
				// Button styling.
				'button_bg_color'                   => '#4361EE',
				'button_text_color'                 => '#FFFFFF',
				'button_font_size'                  => 15,
				'button_font_weight'                => 500,
				'button_border_radius'              => 8,
				'button_hover_bg_color'             => 'transparent',
				'button_hover_text_color'           => '#4361EE',
				'button_hover_border'               => '#4361EE',
			),
			$atts,
			'ohmylms_profile'
		);

		$student = new Student( get_current_user_id() );

		// Check current view mode.
		$current_view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] )) : 'profile';
		$is_edit_mode = isset( $_GET['edit'] ) && 'true' === $_GET['edit'];

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

		// Display profile content with sidebar layout.
		echo '<section class="ohmylms-dashboard">';
		echo '<div class="ohmylms-container">';

		// Show notices.
		if ( function_exists( 'ohmylms_show_all_notices' ) ) {
			ohmylms_show_all_notices();
		}

		// Profile layout with sidebar; the drawer is driven by the ohmylms/ui store.
		\OhMyLMS\Extensions\Chrome::enqueue_ui();
		echo '<div class="ohmylms-student-profile" data-wp-interactive="ohmylms/ui"';
		echo ' ' . wp_interactivity_data_wp_context( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper escapes the attribute.
			array(
				'drawerOpen'   => false,
				'drawerPanel'  => '.ohmylms-student-profile-sidebar',
				'drawerInside' => '.ohmylms-student-profile-sidebar,.ohmylms-hamburger',
			)
		);
		echo ' data-wp-class--open-sidebar="context.drawerOpen" data-wp-on-document--click="actions.drawerOutside" data-wp-on-document--keydown="actions.drawerEscape">';
		echo '<span class="ohmylms-hamburger" aria-label="' . esc_attr__( 'Menu', 'ohmylms' ) . '" role="button" tabindex="0" aria-controls="' . esc_attr( \OhMyLMS\Extensions\Chrome::PROFILE_PANEL_ID ) . '" aria-expanded="false"';
		echo ' data-wp-bind--aria-expanded="context.drawerOpen" data-wp-on--click="actions.drawerToggle" data-wp-on--keydown="actions.drawerKey">';
		echo '<svg width="14" height="11" fill="none" viewBox="0 0 14 11" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" stroke="#21212F" stroke-width=".1" d="M13.125 1.872H.875C.411 1.872.05 1.482.05.96.05.439.411.05.875.05h12.25c.464 0 .825.39.825.91 0 .522-.361.912-.825.912zm0 4.484H.875c-.464 0-.825-.39-.825-.91 0-.522.361-.912.825-.912h12.25c.464 0 .825.39.825.911s-.361.91-.825.91zm0 4.483H.875c-.464 0-.825-.39-.825-.911 0-.522.361-.911.825-.911h12.25c.464 0 .825.39.825.91 0 .522-.361.912-.825.912z"/></svg>';
		echo '</span>';

		echo '<div class="ohmylms-student-profile-wrapper">';

		// Custom sidebar navigation for shortcode.
		self::render_navigation( $current_view );

		echo '<div class="ohmylms-student-profile-sidebar-content">';

		// Display content based on view and mode.
		if ( 'transactions-history' === $current_view ) {
			self::render_transactions_history();
		} elseif ( 'membership' === $current_view && ohmylms_is_pro() ) {
			self::render_membership();
		} elseif ( 'invoice-details' === $current_view ) {
			self::render_invoice_details();
		} elseif ( $is_edit_mode ) {
			// Profile edit content.
			ohmylms_get_template(
				'profile/profile-edit.php',
				array(
					'student' => $student,
				)
			);
		} else {
			// Profile view content.
			ohmylms_get_template(
				'profile/profile.php',
				array(
					'student' => $student,
				)
			);
		}

		echo '</div>'; // .ohmylms-student-profile-sidebar-content
		echo '</div>'; // .ohmylms-student-profile-wrapper
		echo '</div>'; // .ohmylms-student-profile

		echo '</div>'; // .ohmylms-container
		echo '</section>'; // .ohmylms-dashboard

		// Add JavaScript to handle navigation and edit button.
		self::add_navigation_handler();
	}

	/**
	 * Render custom navigation for shortcode
	 *
	 * @param string $current_view Current view.
	 * @since 1.0.0
	 */
	private static function render_navigation( $current_view ) {
		$current_url = remove_query_arg( array( 'view', 'edit' ) );
		?>
		<aside class="ohmylms-student-profile-sidebar" id="<?php echo esc_attr( \OhMyLMS\Extensions\Chrome::PROFILE_PANEL_ID ); ?>">
			<ul class="ohmylms-student-profile-tab">
				<li class="item-profile <?php echo ( 'profile' === $current_view ) ? 'active' : ''; ?>">
					<a href="<?php echo esc_url( add_query_arg( 'view', 'profile', $current_url ) ); ?>" data-view="profile">
						<span class="icon icon-regular">
							<?php include OHMYLMS_DIR . '/assets/images/icon/profile-icon.php'; ?>
						</span>
						<span class="icon icon-active">
							<?php include OHMYLMS_DIR . '/assets/images/icon/profile-active-icon.php'; ?>
						</span>
						<?php echo esc_html__( 'Profile', 'ohmylms' ); ?>
					</a>
				</li>

				<li class="item-transaction-history <?php echo ( 'transactions-history' === $current_view ) ? 'active' : ''; ?>">
					<a href="<?php echo esc_url( add_query_arg( 'view', 'transactions-history', $current_url ) ); ?>" data-view="transactions-history">
						<span class="icon icon-regular">
							<?php include OHMYLMS_DIR . '/assets/images/icon/cart-icon.php'; ?>
						</span>
						<span class="icon icon-active">
							<?php include OHMYLMS_DIR . '/assets/images/icon/cart-active-icon.php'; ?>
						</span>
						<?php echo esc_html__( 'Transaction History', 'ohmylms' ); ?>
					</a>
				</li>

				<?php if ( ohmylms_is_pro() ) : ?>
					<li class="item-membership <?php echo ( 'membership' === $current_view ) ? 'active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'membership', $current_url ) ); ?>" data-view="membership">
							<span class="icon icon-regular">
								<?php include OHMYLMS_DIR . '/assets/images/icon/membership-icon.php'; ?>
							</span>
							<span class="icon icon-active">
								<?php include OHMYLMS_DIR . '/assets/images/icon/membership-active-icon.php'; ?>
							</span>
							<?php echo esc_html__( 'Membership', 'ohmylms' ); ?>
						</a>
					</li>
				<?php endif; ?>
			</ul>
		</aside>
		<?php
	}

	/**
	 * Render transactions history content
	 *
	 * @since 1.0.0
	 */
	private static function render_transactions_history() {
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::transactions_history( $current_page );
	}

	/**
	 * Render membership content
	 *
	 * @since 1.0.0
	 */
	private static function render_membership() {
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::membership( $current_page );
	}

	/**
	 * Render invoice details content
	 *
	 * @since 1.0.0
	 */
	private static function render_invoice_details() {
		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::invoice_details( $current_page );
	}

	/**
	 * Add JavaScript to handle navigation and edit button clicks
	 *
	 * @since 1.0.0
	 */
	private static function add_navigation_handler() {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Handle edit button click
			const editButton = document.querySelector('.ohmylms-profile-actions .profile-edit');
			if (editButton) {
				editButton.addEventListener('click', function(e) {
					e.preventDefault();
					const currentUrl = new URL(window.location.href);
					currentUrl.searchParams.set('edit', 'true');
					currentUrl.searchParams.delete('view'); // Remove view param when editing
					window.location.href = currentUrl.toString();
				});
			}

			// Handle form submission to return to view mode
			const profileForm = document.querySelector('.student-profile-edit form');
			if (profileForm) {
				profileForm.addEventListener('submit', function(e) {
					// Let the form submit normally, but add a redirect parameter
					const currentUrl = new URL(window.location.href);
					currentUrl.searchParams.delete('edit');
					currentUrl.searchParams.set('view', 'profile');
					
					// Store the return URL for after form submission
					const returnUrlInput = document.createElement('input');
					returnUrlInput.type = 'hidden';
					returnUrlInput.name = 'redirect_to';
					returnUrlInput.value = currentUrl.toString();
					profileForm.appendChild(returnUrlInput);
				});
			}

			// Handle navigation menu clicks in header dropdown
			const headerProfileLink = document.querySelector('.ohmylms-user-dropdown .my-profile-link');
			if (headerProfileLink) {
				headerProfileLink.addEventListener('click', function(e) {
					e.preventDefault();
					const currentUrl = new URL(window.location.href);
					currentUrl.searchParams.set('view', 'profile');
					currentUrl.searchParams.delete('edit');
					window.location.href = currentUrl.toString();
				});
			}

			// Handle Dashboard link
			const dashboardLink = document.querySelector('.ohmylms-user-dropdown .dashboard-link');
			if (dashboardLink) {
				dashboardLink.addEventListener('click', function(e) {
					e.preventDefault();
					// Redirect to Dashboard page
					window.location.href = '<?php echo esc_url( ohmylms_get_page_permalink( 'student_dashboard' ) ); ?>';
				});
			}

			// Handle My Courses link
			const myCoursesLink = document.querySelector('.ohmylms-user-dropdown .my-course-link');
			if (myCoursesLink) {
				myCoursesLink.addEventListener('click', function(e) {
					e.preventDefault();
					// Redirect to My Courses page
					window.location.href = '<?php echo esc_url( ohmylms_get_page_permalink( 'student_courses' ) ); ?>';
				});
			}

			// Handle invoice details links in membership page
			document.addEventListener('click', function(e) {
				const invoiceLink = e.target.closest('a[href*="invoice-details"]');
				if (invoiceLink) {
					e.preventDefault();
					const href = invoiceLink.getAttribute('href');
					
					// Extract order ID from the URL
					const urlParams = new URLSearchParams(href.split('?')[1] || '');
					const orderId = urlParams.get('id') || href.match(/id[=\/](\d+)/)?.[1];
					
					if (orderId) {
						const currentUrl = new URL(window.location.href);
						// Remove all query params except keep the base URL
						const baseUrl = currentUrl.origin + currentUrl.pathname;
						const newUrl = new URL(baseUrl);
						newUrl.searchParams.set('view', 'invoice-details');
						newUrl.searchParams.set('id', orderId);
						window.location.href = newUrl.toString();
					}
				}
			});

			// Handle breadcrumb "Invoice" link in invoice details page
			const breadcrumbInvoiceLink = document.querySelector('.invoice-breadcrumb a[href*="membership"]');
			if (breadcrumbInvoiceLink) {
				breadcrumbInvoiceLink.addEventListener('click', function(e) {
					e.preventDefault();
					const currentUrl = new URL(window.location.href);
					const baseUrl = currentUrl.origin + currentUrl.pathname;
					const newUrl = new URL(baseUrl);
					newUrl.searchParams.set('view', 'membership');
					window.location.href = newUrl.toString();
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
	 * @since 1.2.5
	 */
	private static function generate_custom_styles( $args ) {
		// Sanitize values.
		$header_bg_color                   = sanitize_hex_color( $args['header_bg_color'] );
		$user_menu_color                   = sanitize_hex_color( $args['user_menu_color'] );
		$user_menu_bg_color                = sanitize_hex_color( $args['user_menu_bg_color'] );
		$user_menu_font_size               = absint( $args['user_menu_font_size'] );
		$user_menu_font_weight             = absint( $args['user_menu_font_weight'] );
		$user_menu_hover_color             = sanitize_hex_color( $args['user_menu_hover_color'] );
		$user_menu_hover_bg_color          = sanitize_hex_color( $args['user_menu_hover_bg_color'] );
		$user_menu_icon_color              = sanitize_hex_color( $args['user_menu_icon_color'] );
		$user_menu_icon_hover_color        = sanitize_hex_color( $args['user_menu_icon_hover_color'] );
		$section_bg_color                  = sanitize_hex_color( $args['section_bg_color'] );
		$wrapper_border                    = sanitize_text_field( $args['wrapper_border'] );
		$wrapper_bg_color                  = sanitize_hex_color( $args['wrapper_bg_color'] );
		$wrapper_shadow                    = sanitize_text_field( $args['wrapper_shadow'] );
		$sidebar_item_color                = sanitize_hex_color( $args['sidebar_item_color'] );
		$sidebar_item_font_size            = absint( $args['sidebar_item_font_size'] );
		$sidebar_item_font_weight          = absint( $args['sidebar_item_font_weight'] );
		$sidebar_item_bg_color             = sanitize_text_field( $args['sidebar_item_bg_color'] );
		$sidebar_item_active_color         = sanitize_hex_color( $args['sidebar_item_active_color'] );
		$sidebar_item_active_font_size     = absint( $args['sidebar_item_active_font_size'] );
		$sidebar_item_active_font_weight   = absint( $args['sidebar_item_active_font_weight'] );
		$sidebar_item_active_bg_color      = sanitize_hex_color( $args['sidebar_item_active_bg_color'] );
		$sidebar_content_bg_color          = sanitize_hex_color( $args['sidebar_content_bg_color'] );
		$sidebar_content_shadow            = sanitize_text_field( $args['sidebar_content_shadow'] );
		$sidebar_content_padding           = absint( $args['sidebar_content_padding'] );
		$sidebar_content_title_color       = sanitize_hex_color( $args['sidebar_content_title_color'] );
		$sidebar_content_title_font_size   = absint( $args['sidebar_content_title_font_size'] );
		$sidebar_content_title_font_weight = absint( $args['sidebar_content_title_font_weight'] );
		$profile_name_color                = sanitize_hex_color( $args['profile_name_color'] );
		$profile_name_font_size            = absint( $args['profile_name_font_size'] );
		$profile_name_font_weight          = absint( $args['profile_name_font_weight'] );
		$profile_bio_color                 = sanitize_hex_color( $args['profile_bio_color'] );
		$profile_bio_font_size             = absint( $args['profile_bio_font_size'] );
		$profile_bio_font_weight           = absint( $args['profile_bio_font_weight'] );
		$profile_edit_color                = sanitize_hex_color( $args['profile_edit_color'] );
		$profile_edit_bg_color             = sanitize_hex_color( $args['profile_edit_bg_color'] );
		$profile_edit_border               = sanitize_text_field( $args['profile_edit_border'] );
		$profile_edit_font_size            = absint( $args['profile_edit_font_size'] );
		$profile_edit_font_weight          = absint( $args['profile_edit_font_weight'] );
		$profile_edit_hover_color          = sanitize_hex_color( $args['profile_edit_hover_color'] );
		$profile_edit_hover_bg_color       = sanitize_hex_color( $args['profile_edit_hover_bg_color'] );
		$basic_info_bg_color               = sanitize_hex_color( $args['basic_info_bg_color'] );
		$basic_info_shadow                 = sanitize_text_field( $args['basic_info_shadow'] );
		$basic_info_padding                = absint( $args['basic_info_padding'] );
		$basic_info_title_color            = sanitize_hex_color( $args['basic_info_title_color'] );
		$basic_info_title_font_size        = absint( $args['basic_info_title_font_size'] );
		$basic_info_title_font_weight      = absint( $args['basic_info_title_font_weight'] );
		$input_bg_color                    = sanitize_hex_color( $args['input_bg_color'] );
		$input_border                      = sanitize_text_field( $args['input_border'] );
		$input_text_color                  = sanitize_hex_color( $args['input_text_color'] );
		$input_placeholder_color           = sanitize_hex_color( $args['input_placeholder_color'] );
		$input_font_size                   = absint( $args['input_font_size'] );
		$input_padding                     = absint( $args['input_padding'] );
		$input_border_radius               = absint( $args['input_border_radius'] );
		$label_color                       = sanitize_hex_color( $args['label_color'] );
		$label_font_size                   = absint( $args['label_font_size'] );
		$label_font_weight                 = absint( $args['label_font_weight'] );
		$button_bg_color                   = sanitize_hex_color( $args['button_bg_color'] );
		$button_text_color                 = sanitize_hex_color( $args['button_text_color'] );
		$button_font_size                  = absint( $args['button_font_size'] );
		$button_font_weight                = absint( $args['button_font_weight'] );
		$button_border_radius              = absint( $args['button_border_radius'] );
		$button_hover_bg_color             = sanitize_text_field( $args['button_hover_bg_color'] );
		$button_hover_text_color           = sanitize_hex_color( $args['button_hover_text_color'] );
		$button_hover_border               = sanitize_text_field( $args['button_hover_border'] );

		// Generate CSS.
		?>
		<style id="ohmylms-profile-custom-styles">
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

			/* Section Background */
			<?php if ( $section_bg_color ) : ?>
			.ohmylms-page .ohmylms-dashboard {
				background-color: <?php echo esc_attr( $section_bg_color ); ?> !important;
			}
			<?php endif; ?>

			/* Profile Wrapper */
			<?php if ( $wrapper_border || $wrapper_bg_color || $wrapper_shadow ) : ?>
			.ohmylms-page .ohmylms-student-profile .ohmylms-student-profile-wrapper {
				<?php if ( $wrapper_border ) : ?>
				border-color: <?php echo esc_attr( $wrapper_border ); ?> !important;
				<?php endif; ?>
				<?php if ( $wrapper_bg_color ) : ?>
				background-color: <?php echo esc_attr( $wrapper_bg_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $wrapper_shadow ) : ?>
				box-shadow: <?php echo esc_attr( $wrapper_shadow ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Sidebar Items */
			<?php if ( $sidebar_item_color || $sidebar_item_font_size || $sidebar_item_font_weight || $sidebar_item_bg_color ) : ?>
			.ohmylms-page .ohmylms-student-profile .ohmylms-student-profile-tab li a {
				<?php if ( $sidebar_item_color ) : ?>
				color: <?php echo esc_attr( $sidebar_item_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $sidebar_item_font_size ) : ?>
				font-size: <?php echo esc_attr( $sidebar_item_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $sidebar_item_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $sidebar_item_font_weight ); ?> !important;
				<?php endif; ?>
				<?php if ( $sidebar_item_bg_color ) : ?>
				background-color: <?php echo esc_attr( $sidebar_item_bg_color ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Sidebar Items Active/Hover */
			<?php if ( $sidebar_item_active_color || $sidebar_item_active_font_size || $sidebar_item_active_font_weight || $sidebar_item_active_bg_color ) : ?>
			.ohmylms-page .ohmylms-student-profile .ohmylms-student-profile-tab li a:hover,
			.ohmylms-page .ohmylms-student-profile .ohmylms-student-profile-tab li.active a {
				<?php if ( $sidebar_item_active_color ) : ?>
				color: <?php echo esc_attr( $sidebar_item_active_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $sidebar_item_active_font_size ) : ?>
				font-size: <?php echo esc_attr( $sidebar_item_active_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $sidebar_item_active_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $sidebar_item_active_font_weight ); ?> !important;
				<?php endif; ?>
				<?php if ( $sidebar_item_active_bg_color ) : ?>
				background-color: <?php echo esc_attr( $sidebar_item_active_bg_color ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Sidebar Content */
			<?php if ( $sidebar_content_bg_color || $sidebar_content_shadow ) : ?>
			.ohmylms-page .ohmylms-student-profile .ohmylms-student-profile-sidebar-content {
				<?php if ( $sidebar_content_bg_color ) : ?>
				background-color: <?php echo esc_attr( $sidebar_content_bg_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $sidebar_content_shadow ) : ?>
				box-shadow: <?php echo esc_attr( $sidebar_content_shadow ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			<?php if ( $sidebar_content_padding ) : ?>
			.ohmylms-page .ohmylms-student-profile .ohmylms-student-profile-tab-content {
				padding: <?php echo esc_attr( $sidebar_content_padding ); ?>px !important;
			}
			<?php endif; ?>

			/* Sidebar Content Title */
			<?php if ( $sidebar_content_title_color || $sidebar_content_title_font_size || $sidebar_content_title_font_weight ) : ?>
			.ohmylms-page .ohmylms-student-profile .ohmylms-student-profile-tab-content .profile-tab-title {
				<?php if ( $sidebar_content_title_color ) : ?>
				color: <?php echo esc_attr( $sidebar_content_title_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $sidebar_content_title_font_size ) : ?>
				font-size: <?php echo esc_attr( $sidebar_content_title_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $sidebar_content_title_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $sidebar_content_title_font_weight ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Profile Name */
			<?php if ( $profile_name_color || $profile_name_font_size || $profile_name_font_weight ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile .ohmylms-profile-name {
				<?php if ( $profile_name_color ) : ?>
				color: <?php echo esc_attr( $profile_name_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $profile_name_font_size ) : ?>
				font-size: <?php echo esc_attr( $profile_name_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $profile_name_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $profile_name_font_weight ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Profile Bio */
			<?php if ( $profile_bio_color || $profile_bio_font_size || $profile_bio_font_weight ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile .profile-bio {
				<?php if ( $profile_bio_color ) : ?>
				color: <?php echo esc_attr( $profile_bio_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $profile_bio_font_size ) : ?>
				font-size: <?php echo esc_attr( $profile_bio_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $profile_bio_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $profile_bio_font_weight ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Profile Edit Button */
			<?php if ( $profile_edit_color || $profile_edit_bg_color || $profile_edit_border || $profile_edit_font_size || $profile_edit_font_weight ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile .ohmylms-profile-actions .profile-edit {
				<?php if ( $profile_edit_color ) : ?>
				color: <?php echo esc_attr( $profile_edit_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $profile_edit_bg_color ) : ?>
				background-color: <?php echo esc_attr( $profile_edit_bg_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $profile_edit_border ) : ?>
				border-color: <?php echo esc_attr( $profile_edit_border ); ?> !important;
				<?php endif; ?>
				<?php if ( $profile_edit_font_size ) : ?>
				font-size: <?php echo esc_attr( $profile_edit_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $profile_edit_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $profile_edit_font_weight ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Profile Edit Button Hover */
			<?php if ( $profile_edit_hover_color || $profile_edit_hover_bg_color ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile .ohmylms-profile-actions .profile-edit:hover {
				<?php if ( $profile_edit_hover_color ) : ?>
				color: <?php echo esc_attr( $profile_edit_hover_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $profile_edit_hover_bg_color ) : ?>
				background-color: <?php echo esc_attr( $profile_edit_hover_bg_color ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Basic Info Section */
			<?php if ( $basic_info_bg_color || $basic_info_shadow || $basic_info_padding ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile-edit .ohmylms-student-basic-info,
			.ohmylms-page .ohmylms-student-profile .student-profile-edit .ohmylms-student-account {
				<?php if ( $basic_info_bg_color ) : ?>
				background-color: <?php echo esc_attr( $basic_info_bg_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $basic_info_shadow ) : ?>
				box-shadow: <?php echo esc_attr( $basic_info_shadow ); ?> !important;
				<?php endif; ?>
				<?php if ( $basic_info_padding ) : ?>
				padding: <?php echo esc_attr( $basic_info_padding ); ?>px !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Basic Info Title */
			<?php if ( $basic_info_title_color || $basic_info_title_font_size || $basic_info_title_font_weight ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile-edit .account-title {
				<?php if ( $basic_info_title_color ) : ?>
				color: <?php echo esc_attr( $basic_info_title_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $basic_info_title_font_size ) : ?>
				font-size: <?php echo esc_attr( $basic_info_title_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $basic_info_title_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $basic_info_title_font_weight ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Input Fields */
			<?php if ( $input_bg_color || $input_border || $input_text_color || $input_font_size || $input_padding || $input_border_radius ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile-edit input[type="text"],
			.ohmylms-page .ohmylms-student-profile .student-profile-edit input[type="email"],
			.ohmylms-page .ohmylms-student-profile .student-profile-edit input[type="password"],
			.ohmylms-page .ohmylms-student-profile .student-profile-edit input[type="url"],
			.ohmylms-page .ohmylms-student-profile .student-profile-edit input[type="date"],
			.ohmylms-page .ohmylms-student-profile .student-profile-edit select,
			.ohmylms-page .ohmylms-student-profile .student-profile-edit textarea {
				<?php if ( $input_bg_color ) : ?>
				background-color: <?php echo esc_attr( $input_bg_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $input_border ) : ?>
				border-color: <?php echo esc_attr( $input_border ); ?> !important;
				<?php endif; ?>
				<?php if ( $input_text_color ) : ?>
				color: <?php echo esc_attr( $input_text_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $input_font_size ) : ?>
				font-size: <?php echo esc_attr( $input_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $input_padding ) : ?>
				padding: <?php echo esc_attr( $input_padding ); ?>px !important;
				<?php endif; ?>
				<?php if ( $input_border_radius ) : ?>
				border-radius: <?php echo esc_attr( $input_border_radius ); ?>px !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Input Placeholder */
			<?php if ( $input_placeholder_color ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile-edit input::placeholder,
			.ohmylms-page .ohmylms-student-profile .student-profile-edit textarea::placeholder {
				color: <?php echo esc_attr( $input_placeholder_color ); ?> !important;
			}
			<?php endif; ?>

			/* Labels */
			<?php if ( $label_color || $label_font_size || $label_font_weight ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile-edit .ohmylms-form-group > label {
				<?php if ( $label_color ) : ?>
				color: <?php echo esc_attr( $label_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $label_font_size ) : ?>
				font-size: <?php echo esc_attr( $label_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $label_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $label_font_weight ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Buttons */
			<?php if ( $button_bg_color || $button_text_color || $button_font_size || $button_font_weight || $button_border_radius ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile-edit .ohmylms-button {
				<?php if ( $button_bg_color ) : ?>
				background-color: <?php echo esc_attr( $button_bg_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $button_text_color ) : ?>
				color: <?php echo esc_attr( $button_text_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $button_font_size ) : ?>
				font-size: <?php echo esc_attr( $button_font_size ); ?>px !important;
				<?php endif; ?>
				<?php if ( $button_font_weight ) : ?>
				font-weight: <?php echo esc_attr( $button_font_weight ); ?> !important;
				<?php endif; ?>
				<?php if ( $button_border_radius ) : ?>
				border-radius: <?php echo esc_attr( $button_border_radius ); ?>px !important;
				<?php endif; ?>
			}
			<?php endif; ?>

			/* Button Hover */
			<?php if ( $button_hover_bg_color || $button_hover_text_color || $button_hover_border ) : ?>
			.ohmylms-page .ohmylms-student-profile .student-profile-edit .ohmylms-button:hover {
				<?php if ( $button_hover_bg_color ) : ?>
				background-color: <?php echo esc_attr( $button_hover_bg_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $button_hover_text_color ) : ?>
				color: <?php echo esc_attr( $button_hover_text_color ); ?> !important;
				<?php endif; ?>
				<?php if ( $button_hover_border ) : ?>
				border-color: <?php echo esc_attr( $button_hover_border ); ?> !important;
				<?php endif; ?>
			}
			<?php endif; ?>
		</style>
		<?php
	}
}
