<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Feature-owned hook operations, with registration kept in the compatibility adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Commerce;

defined( 'ABSPATH' ) || exit;

/** Owns the Commerce operations delegated by CommonHook. */
final class CheckoutAccess {

	/**
	 * If guest checkout is disabled, redirect non-logged-in users away from checkout to the login page.
	 */
	public function enforce_checkout_login_gate(): void {
		if ( ! is_ohmylms_checkout() || is_user_logged_in() || ohmylms_is_guest_purchase_enabled() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$current_url = home_url( $request_uri );

		// Prefer the student dashboard page (shows LMS login/register form for guests).
		$dashboard_id = ohmylms_get_page_id( 'student_dashboard' );
		if ( 0 < $dashboard_id ) {
			$dashboard_url = get_permalink( $dashboard_id );
			if ( $dashboard_url ) {
				$gate_redirect = add_query_arg( 'redirect_to', $current_url, $dashboard_url );
				wp_safe_redirect( $gate_redirect );
				exit;
			}
		}

		// Fallback to WordPress login page.
		wp_safe_redirect( wp_login_url( $current_url ) );
		exit;
	}

	/**
	 * When a logged-in user lands on the checkout page with ?ohmylms_add_to_cart=COURSE_ID,
	 * add that course to cart and strip the param from the URL.
	 * Used for the guest-checkout-disabled + login redirect flow.
	 */
	public function handle_add_to_cart_after_login(): void {
		if ( ! is_user_logged_in() || ! is_ohmylms_checkout() ) {
			return;
		}

		$course_id      = 0;
		$from_url_param = false;

		if ( ! empty( $_GET['ohmylms_add_to_cart'] ) ) {
			$course_id      = absint( $_GET['ohmylms_add_to_cart'] );
			$from_url_param = true;
		} elseif ( ! empty( $_COOKIE['ohmylms_pending_course'] ) ) {
			$course_id = absint( $_COOKIE['ohmylms_pending_course'] );
			// Clear cookie immediately.
			setcookie( 'ohmylms_pending_course', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
		}

		if ( ! $course_id ) {
			return;
		}

		// If already enrolled, send to the course directly — no point going through checkout.
		$course = ohmylms_get_course( $course_id );
		if ( $course && $course->has_access() ) {
			$course_url = $course->get_permalink();
			if ( $course_url ) {
				wp_safe_redirect( $course_url );
				exit;
			}
		}

		\OhMyLMS\Commerce\ecommerce()->cart->add_to_cart( $course_id, 1 );

		if ( $from_url_param ) {
			wp_safe_redirect( remove_query_arg( 'ohmylms_add_to_cart' ) );
			exit;
		}
	}
}
