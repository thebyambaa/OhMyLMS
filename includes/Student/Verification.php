<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Feature-owned hook operations, with registration kept in the compatibility adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/** Owns the Student operations delegated by CommonHook. */
final class Verification {

	/**
	 * Output verification notices on the student dashboard.
	 * Handles URL params from verification redirects and shows a persistent
	 * "please verify" banner for logged-in but unverified users.
	 */
	public function output_email_verification_notices(): void {
		if ( ! \OhMyLMS\Services\EmailVerificationService::is_required() ) {
			return;
		}

		$user_id = get_current_user_id();

		// URL-param-based one-time notices (from redirects).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['ohmylms_email_verified'] ) ) {
			$this->render_verification_notice(
				'success',
				__( 'Email verified! Your account is now fully active.', 'ohmylms' )
			);
			return;
		}

		if ( ! empty( $_GET['ohmylms_verify_sent'] ) ) {
			$this->render_verification_notice(
				'info',
				__( 'Verification email sent. Check your inbox and click the link to verify.', 'ohmylms' )
			);
			return;
		}

		if ( ! empty( $_GET['ohmylms_verify_error'] ) ) {
			$error = sanitize_key( wp_unslash( $_GET['ohmylms_verify_error'] ) );
			if ( 'expired' === $error ) {
				$msg = __( 'The verification link has expired.', 'ohmylms' );
			} else {
				$msg = __( 'The verification link is invalid.', 'ohmylms' );
			}
			$resend_url = \OhMyLMS\Services\EmailVerificationService::get_resend_url();
			$msg       .= ' <a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend verification email', 'ohmylms' ) . '</a>';
			$this->render_verification_notice( 'warning', $msg, false );
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Persistent banner for unverified logged-in users.
		if ( $user_id && ! \OhMyLMS\Services\EmailVerificationService::is_verified( $user_id ) ) {
			$resend_url = \OhMyLMS\Services\EmailVerificationService::get_resend_url();
			$msg        = __( 'Your email address is not yet verified. Please check your inbox.', 'ohmylms' );
			$msg       .= ' <a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend verification email', 'ohmylms' ) . '</a>';
			$this->render_verification_notice( 'warning', $msg, false );
		}
	}

	/**
	 * Render a verification notice. Colours and spacing come from the shared stylesheet.
	 *
	 * @param string $type 'success' | 'info' | 'warning'.
	 * @param string $message Already-escaped or safe HTML message.
	 * @param bool   $escape  Whether to run esc_html on $message (default true).
	 */
	public function render_verification_notice( string $type, string $message, bool $escape = true ): void {
		if ( ! in_array( $type, array( 'success', 'info', 'warning' ), true ) ) {
			$type = 'info';
		}
		$tone = 'info' === $type ? 'info' : $type;
		if ( class_exists( \OhMyLMS\Design\Ui::class ) ) {
			\OhMyLMS\Design\Ui::enqueue();
		}
		$classes = class_exists( \OhMyLMS\Design\Ui::class )
			? \OhMyLMS\Design\Ui::alert_class(
				array(
					'tone'  => $tone,
					'class' => 'ohmylms-verification-notice ohmylms-verification-notice-' . $type . ' ohmylms-verification-notice--' . $type,
				)
			)
			: 'ohmylms-verification-notice ohmylms-verification-notice-' . $type . ' ohmylms-verification-notice--' . $type;
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" role="status">
			<div class="oml-alert-content">
			<?php
			if ( $escape ) {
				echo esc_html( $message );
			} else {
				echo wp_kses( $message, array( 'a' => array( 'href' => array() ) ) );
			}
			?>
			</div>
		</div>
		<?php
	}

	/**
	 * Show an email verification notice on the order thank-you page.
	 * Only shown when verification is required and the buyer's account is not yet verified.
	 *
	 * @param int $order_id Order identifier.
	 */
	public function output_thankyou_verification_notice( int $order_id ): void {
		if ( ! \OhMyLMS\Services\EmailVerificationService::is_required() ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id || \OhMyLMS\Services\EmailVerificationService::is_verified( $user_id ) ) {
			return;
		}
		$resend_url = \OhMyLMS\Services\EmailVerificationService::get_resend_url();
		if ( class_exists( \OhMyLMS\Design\Ui::class ) ) {
			\OhMyLMS\Design\Ui::enqueue();
		}
		?>
		<div class="ohmylms-verification-notice ohmylms-verification-notice-info ohmylms-verification-notice-thankyou ohmylms-verification-notice--info">
			<strong><?php esc_html_e( 'One more step — verify your email!', 'ohmylms' ); ?></strong><br>
			<?php esc_html_e( 'We sent a verification link to your inbox. Click it to unlock your course content.', 'ohmylms' ); ?>
			<br><br>
			<a class="ohmylms-verification-resend-button" href="<?php echo esc_url( $resend_url ); ?>">
				<?php esc_html_e( 'Resend verification email', 'ohmylms' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Trigger email verification after a new student account is created via ohmylms_create_new_student().
	 *
	 * @param int $student_id Student identifier.
	 */
	public function maybe_send_email_verification( int $student_id ): void {
		if ( ! \OhMyLMS\Services\EmailVerificationService::is_required() ) {
			return;
		}
		\OhMyLMS\Services\EmailVerificationService::generate_and_send( $student_id );
	}

	/**
	 * Handle the email verification link (/?ohmylms_verify_email=TOKEN).
	 * Runs early in template_redirect (priority 5) before restrict_access.
	 */
	public function handle_email_verification_link(): void {
		if ( empty( $_GET['ohmylms_verify_email'] ) ) {
			return;
		}

		$token  = sanitize_text_field( wp_unslash( $_GET['ohmylms_verify_email'] ) );
		$result = \OhMyLMS\Services\EmailVerificationService::verify_token_detailed( $token );

		$dashboard_url = ohmylms_get_dashboard_url();
		if ( ! $dashboard_url ) {
			$dashboard_url = home_url( '/' );
		}

		if ( 'verified' === $result['status'] ) {
			// Auto-login the user if not already logged in.
			if ( ! is_user_logged_in() && $result['user_id'] ) {
				wp_set_current_user( $result['user_id'] );
				wp_set_auth_cookie( $result['user_id'], false );
			}

			// Check for a pending post-verification redirect (e.g., checkout after registration).
			$verified_user_id = (int) ( $result['user_id'] ?? get_current_user_id() );
			$pending_redirect = $verified_user_id ? get_user_meta( $verified_user_id, '_ohmylms_post_verification_redirect', true ) : '';
			if ( $pending_redirect ) {
				delete_user_meta( $verified_user_id, '_ohmylms_post_verification_redirect' );
				wp_safe_redirect( $pending_redirect );
				exit;
			}

			wp_safe_redirect( add_query_arg( 'ohmylms_email_verified', '1', $dashboard_url ) );
			exit;
		}

		if ( 'expired' === $result['status'] ) {
			wp_safe_redirect( add_query_arg( 'ohmylms_verify_error', 'expired', $dashboard_url ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'ohmylms_verify_error', 'invalid', $dashboard_url ) );
		exit;
	}

	/**
	 * Lock lessons for logged-in users who have not yet verified their email.
	 * Hooks into ohmylms_is_lesson_locked filter (priority 5, runs before drip/sequential).
	 *
	 * @param bool $is_locked Is locked.
	 * @param int  $lesson_id Lesson identifier.
	 * @param int  $course_id Course identifier.
	 * @param int  $user_id User identifier.
	 * @return bool
	 */
	public function lock_lesson_for_unverified_user( bool $is_locked, int $lesson_id, int $course_id, int $user_id ): bool {
		if ( $is_locked ) {
			return $is_locked;
		}
		if ( ! $user_id || ! is_user_logged_in() ) {
			return $is_locked;
		}
		if ( ! \OhMyLMS\Services\EmailVerificationService::is_required() ) {
			return $is_locked;
		}
		if ( \OhMyLMS\Services\EmailVerificationService::is_verified( $user_id ) ) {
			return $is_locked;
		}
		return true;
	}

	/**
	 * Block adding an unverified user to a community space.
	 * Hooks into ohmylms_allow_add_user_to_space filter (community plugin).
	 *
	 * @param bool $allow Allow.
	 * @param int  $user_id User identifier.
	 * @return bool
	 */
	public function maybe_block_unverified_community_join( bool $allow, int $user_id ): bool {
		if ( ! $allow ) {
			return false;
		}
		if ( ! \OhMyLMS\Services\EmailVerificationService::is_required() ) {
			return true;
		}
		return \OhMyLMS\Services\EmailVerificationService::is_verified( $user_id );
	}

	/**
	 * Block course review submission for users with unverified emails.
	 *
	 * @param array $comment_data Comment data.
	 * @return array
	 */
	public function block_review_for_unverified_user( array $comment_data ): array {
		if ( is_admin() ) {
			return $comment_data;
		}

		if ( ! \OhMyLMS\Services\EmailVerificationService::is_required() ) {
			return $comment_data;
		}

		$post_id = isset( $comment_data['comment_post_ID'] ) ? (int) $comment_data['comment_post_ID'] : 0;
		if ( ! $post_id || OHMYLMS_COURSE_CPT !== get_post_type( $post_id ) ) {
			return $comment_data;
		}

		$user_id = get_current_user_id();
		if ( $user_id && ! \OhMyLMS\Services\EmailVerificationService::is_verified( $user_id ) ) {
			wp_die(
				esc_html__( 'You must verify your email address before submitting a review.', 'ohmylms' ),
				esc_html__( 'Email verification required', 'ohmylms' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		return $comment_data;
	}
}
