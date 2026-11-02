<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Page routing shared by the public template redirect callback.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\ThemeSupport;

use OhMyLMS\Commerce\Checkout;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/** Owns page redirect decisions; template functions remain rendering helpers and adapters. */
final class PageRouting {
	/** Apply the established redirects after WordPress resolves the queried page. */
	public static function redirect() {
		global $wp;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// When default permalinks are enabled, redirect shop page to post type archive url.
		if ( ! empty( $_GET['page_id'] ) && '' === get_option( 'permalink_structure' ) && ohmylms_get_page_id( 'course' ) === absint( $_GET['page_id'] ) && get_post_type_archive_link( 'ohmylms-course' ) ) {
			wp_safe_redirect( get_post_type_archive_link( 'ohmylms-course' ) );
			exit;
		}

		if ( ! empty( $_GET['page_id'] ) && '' === get_option( 'permalink_structure' ) && ohmylms_get_page_id( 'membership' ) === absint( $_GET['page_id'] ) && get_post_type_archive_link( 'ohmylms-membership' ) ) {
			wp_safe_redirect( get_post_type_archive_link( 'ohmylms-membership' ) );
			exit;
		}

		// Check if user already has access to items in cart and redirect to profile.
		if ( is_page( ohmylms_get_page_id( 'checkout' ) ) && is_user_logged_in() && ! is_ohmylms_order_received_page() ) {
			$cart_data                  = ecommerce()->cart->get_cart_contents();
			$is_course_already_enrolled = false;
			$url                        = '';

			if ( is_array( $cart_data ) ) {
				foreach ( $cart_data as $key => $data ) {
					if ( isset( $data['course_id'], $data['type'] ) ) {
						$id   = $data['course_id'];
						$type = $data['type'];
						if ( 'ohmylms-course' === $type ) {
							$course                     = ohmylms_get_course( $id );
							$is_course_already_enrolled = $course->has_access();
							$url                        = $course->get_permalink();
						} else {
							$membership                 = ohmylms_get_membership( $id );
							$is_course_already_enrolled = $membership->is_already_purchased();
							if ( $membership ) {
								$url = home_url( '/my-profile/' );
							}
						}

						// Redirect if already enrolled/has access.
						if ( $is_course_already_enrolled ) {
							wp_safe_redirect( $url ? $url : home_url( '/my-profile/' ) );
							exit;
						}
					}
				}
			}
		}

		// Logout endpoint under My Account page. Logging out requires a valid nonce.
		if ( isset( $wp->query_vars['customer-logout'] ) ) {
			if ( ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'customer-logout' ) ) {
				wp_logout();
				wp_safe_redirect( ohmylms_get_logout_redirect_url() );
				exit;
			}
			/* translators: %s: logout url */
			wp_safe_redirect( ohmylms_get_page_permalink( 'student_dashboard' ) );
			exit;
		}
	}
}
