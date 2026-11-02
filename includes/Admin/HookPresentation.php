<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Feature-owned hook operations, with registration kept in the compatibility adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Admin;

defined( 'ABSPATH' ) || exit;

/** Owns the Admin operations delegated by CommonHook. */
final class HookPresentation {

	/**
	 * Remove emoji scripts and styles from admin
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ohmylms_remove_emoji() {
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Add Edit Course menu on admin bar for admin users
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar.
	 * @since 1.0.0
	 */
	public function add_edit_course_menu( \WP_Admin_Bar $admin_bar ) {
		if ( current_user_can( 'edit_posts' ) ) {
			// Modify the "New Course" link in the admin bar.
			$node = $admin_bar->get_node( 'new-ohmylms-course' ); // This targets the "New Course" button in the admin bar.
			if ( $node ) {
				// Update the URL with your custom link (e.g., redirecting to a different page).
				$new_url = \OhMyLMS\Admin\LegacyRoutes::app_url( '/courses' );
				// Add the updated node back to the admin bar with the new URL.
				$admin_bar->add_node(
					array(
						'id'    => 'new-ohmylms-course',  // Keep the same ID to modify the existing node.
						'title' => $node->title,        // Keep the same title.
						'href'  => $new_url,            // New URL.
						'meta'  => array( 'class' => 'custom-class' ), // Optional: custom class.
					)
				);
			}

			if ( ohmylms_is_single_course_page() ) {

				// Get the ID of the current course.
				global $post;
				if ( $post && 'ohmylms-course' === $post->post_type ) {
					// Find the "Edit" menu item
					// Modify the URL of the "Edit Course" link.
					$new_url = \OhMyLMS\Admin\LegacyRoutes::app_url( '/courses/' . $post->ID );

					// Update the existing "Edit" node with the new URL.
					$admin_bar->add_node(
						array(
							'id'    => 'edit-course', // Use the same ID to modify the existing one.
							'title' => '<span class="ab-icon dashicons dashicons-edit"></span>' . esc_html__( 'Edit Course', 'ohmylms' ),
							'href'  => $new_url, // New link for the "Edit Course".
						)
					);
				}
			}
		}
	}

	/**
	 * Modify comments query to exclude WooCommerce order notes
	 *
	 * @param WP_Comment_Query $query The comment query object.
	 * @return void
	 */
	public function modify_comments_query( $query ) {
		global $pagenow;

		// Only run in admin "Comments" screen.
		if ( is_admin() && 'edit-comments.php' === $pagenow ) {
			// Exclude WooCommerce order notes from the list.
			$query->query_vars['type__not_in'] = array( 'order_note', 'subscription_note' );
		}
	}

	/**
	 * Add OhMyLMS specific body classes for admin pages
	 *
	 * @param string $classes Existing admin body classes.
	 * @return string Modified admin body classes
	 * @since 1.0.0
	 */
	public function add_ohmylms_admin_body_class( $classes ) {
		// Check for UI Express plugin.
		if ( defined( 'uixpress_plugin_version' ) ) {
			$classes .= ' ohmylms-ui-express-active';
		}
		return $classes;
	}

	/**
	 * Customize admin footer text on OhMyLMS admin pages
	 *
	 * @param mixed $text Text.
	 * @return string The modified footer text.
	 */
	public function review_text_in_footer( $text ) {
		global $pagenow;
		// if ( apply_filters( 'ohmylms_display_admin_footer_text', ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'ohmylms' === $_GET['page'] ) ) ) {.
		if ( apply_filters( 'ohmylms_display_admin_footer_text', $this->is_ohmylms_page() ) ) {
			$text = sprintf(
				/* translators: %s: plugin name */
				__( 'Enjoying OhMyLMS? Your 5-Star review will help us grow and offer you even better features!', 'ohmylms' ),
				sprintf( '<strong>%s</strong>', esc_html__( 'OhMyLMS', 'ohmylms' ) )
			);

			$text .= sprintf(
				' <a href="%1$s" target="_blank" class="ohmylms-rating-link">%2$s</a>',
				esc_url( 'https://wordpress.org/support/plugin/ohmylms/reviews?rate=5#new-post' ),
				'Support Us With &#9733;&#9733;&#9733;&#9733;&#9733;'
			);
		}
		return $text;
	}

	/**
	 * Is ohmylms page for Common hook.
	 */
	public function is_ohmylms_page() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		// Check if we're on any OhMyLMS admin page.
		$ohmylms_pages = array(
			'ohmylms_page_ohmylms-courses',
			'ohmylms_page_ohmylms-lessons',
			'ohmylms_page_ohmylms-quiz',
			'ohmylms_page_ohmylms-questions',
			'ohmylms_page_ohmylms-students',
			'ohmylms_page_ohmylms-settings',
		);

		// Also check if screen ID starts with 'ohmylms'.
		if ( in_array( $screen->id, $ohmylms_pages, true ) || strpos( $screen->id, 'ohmylms' ) === 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Add new row on plugins page for OhMyLMS
	 *
	 * @param mixed $links Links.
	 * @param mixed $file File.
	 */
	public function add_row_meta( $links, $file ) {
		if ( OHMYLMS_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$new_links = array(
			'<a href="https://wordpress.org/support/plugin/ohmylms/reviews?rate=5#new-post" target="_blank" aria-label="' . esc_attr__( 'Support Us With ★★★★★', 'ohmylms' ) . '">' . esc_html__( 'Support Us With ★★★★★', 'ohmylms' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );

		return $links;
	}
}
