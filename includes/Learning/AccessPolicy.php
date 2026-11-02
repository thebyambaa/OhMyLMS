<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Domain-owned learner access rules extracted from the shared hook adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Owns learner access rules; hook registration remains in CommonHook for compatibility. */
final class AccessPolicy {

	/**
	 * Restrict post access for not enrolled users
	 */
	public function restrict_access() {
		// A signed learner preview already passed OhMyLMS\Learning\LessonPreview::start(), which applies these gates as a new learner.
		if ( \OhMyLMS\Learning\LessonPreview::active() ) {
			return;
		}
		if ( is_single() ) {
			global $post;

			/**
			 * Post types whose singular view is gated behind course enrollment.
			 *
			 * @since 1.2.19
			 *
			 * @param string[] $restrict_post_types List of restricted content post types.
			 */
			$restrict_post_types = apply_filters(
				'ohmylms_restricted_content_post_types',
				array( 'ohmylms-lesson', 'ohmylms-assignment', 'ohmylms-quiz', 'ohmylms-session' )
			);
			// Check if the current post type is in the restricted list.
			if ( in_array( $post->post_type, $restrict_post_types, true ) ) {

				// Opening a quiz you author is a preview: clear any old recorded attempt or
				// completion of it so the page never opens on a stale "passed".
				if ( 'ohmylms-quiz' === $post->post_type && is_user_logged_in() ) {
					\OhMyLMS\Quiz\Submission::clear_preview_record( $post->ID, get_current_user_id() );
				}

				// Admins and the post's author preview and work through their own content
				// without enrolling. This matches the template-level check in
				// templates/single-lesson.php and OhMyLMS\SequentialMode's bypass.
				if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_options' ) && get_current_user_id() !== (int) $post->post_author ) ) {

					// Block access for logged-in users who haven't verified their email yet.
					if (
						is_user_logged_in() &&
						\OhMyLMS\Services\EmailVerificationService::is_required() &&
						! \OhMyLMS\Services\EmailVerificationService::is_verified( get_current_user_id() )
					) {
						$resend_url = \OhMyLMS\Services\EmailVerificationService::get_resend_url();
						ob_start();
						// wp_die() exits before the footer, so print the shared sheet and tokens here.
						if ( class_exists( \OhMyLMS\Design\Ui::class ) && function_exists( 'wp_print_styles' ) ) {
							\OhMyLMS\Design\Ui::enqueue();
							if ( class_exists( \OhMyLMS\Design\Tokens::class ) ) {
								wp_add_inline_style( \OhMyLMS\Design\Ui::STYLE_HANDLE, \OhMyLMS\Design\Tokens::frontend_css() );
							}
							wp_print_styles( \OhMyLMS\Design\Ui::STYLE_HANDLE );
						}
						?>
						<div class="ohmylms-access-denied-modal">
							<div class="ohmylms-access-denied-inner">
								<div class="ohmylms-access-denied-modal-content">
									<span class="denied-icon">
										<svg class="ohmylms-verification-icon" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
									</span>
									<?php
									$ohmylms_resend_button = \OhMyLMS\Design\Ui::button(
										__( 'Resend verification email', 'ohmylms' ),
										array(
											'variant' => 'primary',
											'href'    => $resend_url,
											'class'   => 'ohmylms-verification-resend',
										)
									);
									$ohmylms_verify_empty  = \OhMyLMS\Design\Ui::empty_state(
										__( 'Please verify your email', 'ohmylms' ),
										array(
											'description'  => __( 'Check your inbox and click the verification link to access course content.', 'ohmylms' ),
											'actions_html' => $ohmylms_resend_button,
											'class'        => 'ohmylms-access-denied-empty',
										)
									);
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui helpers escape text; resend link URL is escaped.
									echo $ohmylms_verify_empty;
									?>
								</div>
							</div>
						</div>
						<?php
						$output = ob_get_clean();
						wp_die( $output, '', array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}

					$student   = new \OhMyLMS\Data\Student( get_current_user_id() );
					$course_id = ohmylms_get_course_id_by_content_id( $post->ID );
					// Sessions (live classes) are not always stored in the content relationship
					// table; fall back to their post parent so enrolled students are not blocked.
					if ( empty( $course_id ) && 'ohmylms-session' === $post->post_type && $post->post_parent ) {
						$course_id = (int) $post->post_parent;
					}
					$preview_mode = get_post_meta( $post->ID, '_preview_enable', true );
					if ( $student && ! $student->maybe_enrolled( $course_id ) && ! $preview_mode ) {
						ob_start();
						?>
						<div class="ohmylms-access-denied-modal">
							<div class="ohmylms-access-denied-inner">
								<div class="ohmylms-access-denied-modal-content">

									<span class="denied-icon">
										<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
									</span>

									<h4 class="ohmylms-access-denied-title">
										<?php esc_html_e( 'Access Denied', 'ohmylms' ); ?>
									</h4>

									<p class="ohmylms-access-denied-description">
										<?php
										printf(
											esc_html__( 'You do not have permission to view this post', 'ohmylms' )
										);
										?>
									</p>
								</div>
							</div>
						</div>

						<style id="access-denied-modal">
							.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
								background-color: #F4F5F7;
								padding: 40px 40px;
								border-radius: 14px;
								box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
								text-align: center;
							}

							.ohmylms-access-denied-modal .denied-icon {
								display: block;
								text-align: center;
								margin-bottom: 20px;
							}

							.ohmylms-access-denied-modal .denied-icon svg {
								display: block;
								margin: 0 auto;
							}

							.ohmylms-access-denied-modal .ohmylms-access-denied-title {
								margin-bottom: 12px;
								font-size: 36px;
								font-weight: 700;
								line-height: 1;
								color: var(--ohmylms-heading-color);
							}

							.ohmylms-access-denied-modal .ohmylms-access-denied-description {
								font-size: 16px !important;
								color: #7A8B9A !important;
								font-weight: 500;
								line-height: 1.5 !important;
								max-width: 380px;
								margin: 0 auto 32px !important;
							}

							.ohmylms-access-denied-modal .ohmylms-access-denied-button {
								background-color: var(--ohmylms-primary-color, #6e42d3);
								color: #fff;
								padding: 13px 20px;
								border-radius: 8px;
								border: none;
								cursor: pointer;
								font-size: 14px;
								font-weight: 500;
								line-height: 1;
								text-decoration: none;
								display: inline-block;
							}

							@media screen and (max-width: 1199px) {
								.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
									font-size: 26px;
								}
								.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
									font-size: 14px;
								}

							}

						</style>
						<?php
						$content = ob_get_clean();

						wp_die( $content, 'Access Denied', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
					}

					if ( $student && $student->maybe_banned() ) {
						ob_start();
						?>
						<div class="ohmylms-access-denied-modal">
							<div class="ohmylms-access-denied-inner">
								<div class="ohmylms-access-denied-modal-content">

									<span class="denied-icon">
										<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
									</span>

									<h4 class="ohmylms-access-denied-title">
										<?php esc_html_e( 'Access Denied', 'ohmylms' ); ?>
									</h4>

									<p class="ohmylms-access-denied-description">
										<?php
										printf(
											esc_html__( 'You do not have permission to view this post', 'ohmylms' )
										);
										?>
									</p>
								</div>
							</div>
						</div>

						<style id="access-denied-modal">
							.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
								background-color: #F4F5F7;
								padding: 40px 40px;
								border-radius: 14px;
								box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
								text-align: center;
							}

							.ohmylms-access-denied-modal .denied-icon {
								display: block;
								text-align: center;
								margin-bottom: 20px;
							}

							.ohmylms-access-denied-modal .denied-icon svg {
								display: block;
								margin: 0 auto;
							}

							.ohmylms-access-denied-modal .ohmylms-access-denied-title {
								margin-bottom: 12px;
								font-size: 36px;
								font-weight: 700;
								line-height: 1;
								color: var(--ohmylms-heading-color);
							}

							.ohmylms-access-denied-modal .ohmylms-access-denied-description {
								font-size: 16px !important;
								color: #7A8B9A !important;
								font-weight: 500;
								line-height: 1.5 !important;
								max-width: 380px;
								margin: 0 auto 32px !important;
							}

							.ohmylms-access-denied-modal .ohmylms-access-denied-button {
								background-color: var(--ohmylms-primary-color, #6e42d3);
								color: #fff;
								padding: 13px 20px;
								border-radius: 8px;
								border: none;
								cursor: pointer;
								font-size: 14px;
								font-weight: 500;
								line-height: 1;
								text-decoration: none;
								display: inline-block;
							}

							@media screen and (max-width: 1199px) {
								.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
									font-size: 26px;
								}
								.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
									font-size: 14px;
								}

							}

						</style>
						<?php
						$content = ob_get_clean();

						wp_die( $content, 'Access Denied', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
					}

					$prerequisites = null;
					if ( 'ohmylms-lesson' === $post->post_type ) {
						$lesson_obj    = ohmylms_get_lesson( $post->ID );
						$prerequisites = method_exists( $lesson_obj, 'get_prerequisites' ) ? $lesson_obj->get_prerequisites() : '';
					}

					if ( ! ohmylms_is_pro() && 'ohmylms-assignment' === $post->post_type ) {

						ob_start();
						?>
							<div class="ohmylms-access-denied-modal">
								<div class="ohmylms-access-denied-inner">
									<div class="ohmylms-access-denied-modal-content">

										<span class="denied-icon">
											<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
										</span>

										<h4 class="ohmylms-access-denied-title">
											<?php esc_html_e( 'Access Denied', 'ohmylms' ); ?>
										</h4>

										<p class="ohmylms-access-denied-description">
											<?php
											printf(
												esc_html__( 'You do not have permission to view this post', 'ohmylms' )
											);
											?>
										</p>
									</div>
								</div>
							</div>

							<style id="access-denied-modal">
								.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
									background-color: #F4F5F7;
									padding: 40px 40px;
									border-radius: 14px;
									box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
									text-align: center;
								}

								.ohmylms-access-denied-modal .denied-icon {
									display: block;
									text-align: center;
									margin-bottom: 20px;
								}

								.ohmylms-access-denied-modal .denied-icon svg {
									display: block;
									margin: 0 auto;
								}

								.ohmylms-access-denied-modal .ohmylms-access-denied-title {
									margin-bottom: 12px;
									font-size: 36px;
									font-weight: 700;
									line-height: 1;
									color: var(--ohmylms-heading-color);
								}

								.ohmylms-access-denied-modal .ohmylms-access-denied-description {
									font-size: 16px !important;
									color: #7A8B9A !important;
									font-weight: 500;
									line-height: 1.5 !important;
									max-width: 380px;
									margin: 0 auto 32px !important;
								}

								.ohmylms-access-denied-modal .ohmylms-access-denied-button {
									background-color: var(--ohmylms-primary-color, #6e42d3);
									color: #fff;
									padding: 13px 20px;
									border-radius: 8px;
									border: none;
									cursor: pointer;
									font-size: 14px;
									font-weight: 500;
									line-height: 1;
									text-decoration: none;
									display: inline-block;
								}

								@media screen and (max-width: 1199px) {
									.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
										font-size: 26px;
									}
									.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
										font-size: 14px;
									}

								}

							</style>
							<?php
							$content = ob_get_clean();

							wp_die( $content, 'Access Denied', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
					}

					// Assignment prerequisites gate submitting, not reading: the assignment page lists them
					// and OhMyLMS\Assignment\Delivery refuses the submission until they are complete.

					// Check prerequisites settings.
					if ( is_array( $prerequisites ) && ! empty( $prerequisites ) && ! empty( $prerequisites['enable'] ) && ! empty( $prerequisites['data'] ) && $prerequisites['enable'] ) {
						$prerequisites_met      = true;
						$incomplete_course_name = '';
						$incomplete_course_link = '';
						foreach ( $prerequisites['data'] as $prerequisite ) {
							if ( ! $student->maybe_completed( $prerequisite['value'] ) ) {
								$prerequisites_met      = false;
								$incomplete_course_name = $prerequisite['label'];
								$incomplete_course_link = get_permalink( $prerequisite['value'] );
								break;
							}
						}

						if ( ! $prerequisites_met ) {
							ob_start();
							?>
							<div class="ohmylms-access-denied-modal">
								<div class="ohmylms-access-denied-inner">
									<div class="ohmylms-access-denied-modal-content">

										<span class="denied-icon">
											<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
										</span>

										<h4 class="ohmylms-access-denied-title">
											<?php esc_html_e( 'Access Denied', 'ohmylms' ); ?>
										</h4>

										<p class="ohmylms-access-denied-description">
											<?php
											printf(
												/* translators: %s: text value. */
												esc_html__( 'You must complete the prerequisite "%s" before viewing this content.', 'ohmylms' ),
												esc_html( $incomplete_course_name )
											);
											?>
										</p>

										<a href="<?php echo esc_url( $incomplete_course_link ); ?>" class="ohmylms-access-denied-button">
											<?php esc_html_e( 'Go to Prerequisite Content', 'ohmylms' ); ?>
										</a>
									</div>
								</div>
							</div>

							<style id="access-denied-modal">
								.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
									background-color: #F4F5F7;
									padding: 40px 40px;
									border-radius: 14px;
									box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
									text-align: center;
								}

								.ohmylms-access-denied-modal .denied-icon {
									display: block;
									text-align: center;
									margin-bottom: 20px;
								}

								.ohmylms-access-denied-modal .denied-icon svg {
									display: block;
									margin: 0 auto;
								}

								.ohmylms-access-denied-modal .ohmylms-access-denied-title {
									margin-bottom: 12px;
									font-size: 36px;
									font-weight: 700;
									line-height: 1;
									color: var(--ohmylms-heading-color);
								}

								.ohmylms-access-denied-modal .ohmylms-access-denied-description {
									font-size: 16px !important;
									color: #7A8B9A !important;
									font-weight: 500;
									line-height: 1.5 !important;
									max-width: 380px;
									margin: 0 auto 32px !important;
								}

								.ohmylms-access-denied-modal .ohmylms-access-denied-button {
									background-color: var(--ohmylms-primary-color, #6e42d3);
									color: #fff;
									padding: 13px 20px;
									border-radius: 8px;
									border: none;
									cursor: pointer;
									font-size: 14px;
									font-weight: 500;
									line-height: 1;
									text-decoration: none;
									display: inline-block;
								}

								@media screen and (max-width: 1199px) {
									.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
										font-size: 26px;
									}
									.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
										font-size: 14px;
									}

								}

							</style>
							<?php
							$content = ob_get_clean();

							wp_die( $content, 'Access Denied', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
						}
					}

					// Sequential mode gate — blocks direct URL access when course has sequential mode on.
					if ( apply_filters( 'ohmylms_is_lesson_sequentially_locked', false, $post->ID, $course_id, get_current_user_id() ) ) {
						ob_start();
						?>
						<div class="ohmylms-access-denied-modal">
							<div class="ohmylms-access-denied-inner">
								<div class="ohmylms-access-denied-modal-content">

									<span class="denied-icon">
										<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
									</span>

									<h4 class="ohmylms-access-denied-title">
										<?php esc_html_e( 'Content Locked', 'ohmylms' ); ?>
									</h4>

									<p class="ohmylms-access-denied-description">
										<?php esc_html_e( 'You must complete the previous lesson before accessing this content.', 'ohmylms' ); ?>
									</p>
								</div>
							</div>
						</div>

						<style id="access-denied-modal">
							.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
								background-color: #F4F5F7;
								padding: 40px 40px;
								border-radius: 14px;
								box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
								text-align: center;
							}

							.ohmylms-access-denied-modal .denied-icon {
								display: block;
								text-align: center;
								margin-bottom: 20px;
							}

							.ohmylms-access-denied-modal .denied-icon svg {
								display: block;
								margin: 0 auto;
							}

							.ohmylms-access-denied-modal .ohmylms-access-denied-title {
								margin-bottom: 12px;
								font-size: 36px;
								font-weight: 700;
								line-height: 1;
								color: var(--ohmylms-heading-color);
							}

							.ohmylms-access-denied-modal .ohmylms-access-denied-description {
								font-size: 16px !important;
								color: #7A8B9A !important;
								font-weight: 500;
								line-height: 1.5 !important;
								max-width: 380px;
								margin: 0 auto 32px !important;
							}

							@media screen and (max-width: 1199px) {
								.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
									font-size: 26px;
								}
								.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
									font-size: 14px;
								}
							}
						</style>
						<?php
						$content = ob_get_clean();
						wp_die( $content, 'Content Locked', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
					}
				}
			}
		}
	}

	/**
	 * Restrict session access for Common hook.
	 */
	public function restrict_session_access() {
		if ( is_single() ) {
			global $post;

			$restrict_post_types = apply_filters(
				'ohmylms_restricted_content_post_types',
				array( 'ohmylms-lesson', 'ohmylms-assignment', 'ohmylms-quiz', 'ohmylms-session' )
			);
			// Check if the current post type is in the restricted list.
			if ( in_array( $post->post_type, $restrict_post_types, true ) ) {
				// Implement drip functionality.
				if ( 'ohmylms-lesson' === $post->post_type ) {
					$student    = new \OhMyLMS\Data\Student( get_current_user_id() );
					$lesson_obj = ohmylms_get_lesson( $post->ID );
					if ( $lesson_obj ) {
						$drip_feed = method_exists( $lesson_obj, 'get_drip_feed' ) ? $lesson_obj->get_drip_feed() : '';
						$drip_feed = ! $drip_feed && method_exists( $lesson_obj, 'get_drip_settings' ) ? $lesson_obj->get_drip_settings() : '';
						// Check drip feed settings.
						if ( is_array( $drip_feed ) && ! empty( $drip_feed['enable'] ) ) {
							if ( 'specific-date' === $drip_feed['type'] ) {

								// Get current WordPress date and time in UTC.
								$current_time      = new \DateTime( 'now', new \DateTimeZone( wp_timezone_string() ) );
								$current_date      = $current_time->format( 'Y-m-d' );
								$current_time_only = $current_time->format( 'H:i:s' );

								// Parse drip feed date and time.
								$drip_date = ( new \DateTime( $drip_feed['date'], new \DateTimeZone( wp_timezone_string() ) ) )->format( 'Y-m-d' );
								$drip_time = ( new \DateTime( $drip_feed['time'], new \DateTimeZone( wp_timezone_string() ) ) )->format( 'H:i:s' );

								// Compare date.
								if ( $current_date === $drip_date ) {
									// If date matches, compare time.
									if ( $current_time_only < $drip_time ) {

										ob_start();
										?>
										<div class="ohmylms-access-denied-modal">
											<div class="ohmylms-access-denied-inner">
												<div class="ohmylms-access-denied-modal-content">

													<span class="denied-icon">
														<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
													</span>

													<h4 class="ohmylms-access-denied-title">
														<?php esc_html_e( 'Access Denied', 'ohmylms' ); ?>
													</h4>

													<p class="ohmylms-access-denied-description">
														<?php
														printf(
															/* translators: %s: text value. */
															esc_html__( 'This content will be available after "%s"', 'ohmylms' ),
															esc_html( $drip_time . ' ' . wp_timezone_string() )
														);
														?>
													</p>
												</div>
											</div>
										</div>

										<style id="access-denied-modal">
											.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
												background-color: #F4F5F7;
												padding: 40px 40px;
												border-radius: 14px;
												box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
												text-align: center;
											}

											.ohmylms-access-denied-modal .denied-icon {
												display: block;
												text-align: center;
												margin-bottom: 20px;
											}

											.ohmylms-access-denied-modal .denied-icon svg {
												display: block;
												margin: 0 auto;
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-title {
												margin-bottom: 12px;
												font-size: 36px;
												font-weight: 700;
												line-height: 1;
												color: var(--ohmylms-heading-color);
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-description {
												font-size: 16px !important;
												color: #7A8B9A !important;
												font-weight: 500;
												line-height: 1.5 !important;
												max-width: 380px;
												margin: 0 auto 32px !important;
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-button {
												background-color: #1356F0;
												color: #fff;
												padding: 13px 20px;
												border-radius: 8px;
												border: none;
												cursor: pointer;
												font-size: 14px;
												font-weight: 500;
												line-height: 1;
												text-decoration: none;
												display: inline-block;
											}

											@media screen and (max-width: 1199px) {
												.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
													font-size: 26px;
												}
												.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
													font-size: 14px;
												}

											}

										</style>
										<?php
										$content = ob_get_clean();

										wp_die( $content, 'Access Denied', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
									}
								} elseif ( $current_date < $drip_date ) {
									// If current date is earlier than the drip feed date.

									ob_start();
									?>
										<div class="ohmylms-access-denied-modal">
											<div class="ohmylms-access-denied-inner">
												<div class="ohmylms-access-denied-modal-content">

													<span class="denied-icon">
														<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
													</span>

													<h4 class="ohmylms-access-denied-title">
														<?php esc_html_e( 'Access Denied', 'ohmylms' ); ?>
													</h4>

													<p class="ohmylms-access-denied-description">
														<?php
														printf(
															/* translators: %s: text value. */
															esc_html__( 'This content will be available on "%s"', 'ohmylms' ),
															esc_html( $drip_date . ' ' . wp_timezone_string() )
														);
														?>
													</p>
												</div>
											</div>
										</div>

										<style id="access-denied-modal">
											.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
												background-color: #F4F5F7;
												padding: 40px 40px;
												border-radius: 14px;
												box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
												text-align: center;
											}

											.ohmylms-access-denied-modal .denied-icon {
												display: block;
												text-align: center;
												margin-bottom: 20px;
											}

											.ohmylms-access-denied-modal .denied-icon svg {
												display: block;
												margin: 0 auto;
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-title {
												margin-bottom: 12px;
												font-size: 36px;
												font-weight: 700;
												line-height: 1;
												color: var(--ohmylms-heading-color);
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-description {
												font-size: 16px !important;
												color: #7A8B9A !important;
												font-weight: 500;
												line-height: 1.5 !important;
												max-width: 380px;
												margin: 0 auto 32px !important;
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-button {
												background-color: #1356F0;
												color: #fff;
												padding: 13px 20px;
												border-radius: 8px;
												border: none;
												cursor: pointer;
												font-size: 14px;
												font-weight: 500;
												line-height: 1;
												text-decoration: none;
												display: inline-block;
											}

											@media screen and (max-width: 1199px) {
												.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
													font-size: 26px;
												}
												.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
													font-size: 14px;
												}

											}

										</style>
										<?php
										$content = ob_get_clean();

										wp_die( $content, 'Access Denied', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
								}
							} elseif ( 'enrollment-from-x-days' === $drip_feed['type'] ) {
								$days = ! empty( $drip_feed['enrollment_from_x_days'] ) ? $drip_feed['enrollment_from_x_days'] : 0;
								global $wpdb;
								$user_id    = get_current_user_id(); // Change this as needed.
								$course_id  = $post->post_parent;
								$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
								$start_date = $wpdb->get_var(
									$wpdb->prepare( 'SELECT start_date FROM %i WHERE user_id = %d AND course_id = %d', $table_name, $user_id, $course_id )
								);

								if ( $start_date ) {
									$start_date_time   = new \DateTime( $start_date );
									$current_date_time = new \DateTime();

									$interval        = $start_date_time->diff( $current_date_time );
									$days_difference = $interval->days;
									if ( (int) $days > (int) $days_difference ) {
										// Calculate the exact date when content will be available.
										$available_date = clone $start_date_time;
										$available_date->modify( "+$days days" );

										ob_start();
										?>
										<div class="ohmylms-access-denied-modal">
											<div class="ohmylms-access-denied-inner">
												<div class="ohmylms-access-denied-modal-content">

													<span class="denied-icon">
														<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
													</span>

													<h4 class="ohmylms-access-denied-title">
														<?php esc_html_e( 'Access Denied', 'ohmylms' ); ?>
													</h4>

													<p class="ohmylms-access-denied-description">
														<?php
														printf(
															/* translators: %s: text value. */
															esc_html__( 'This content will be available on "%s"', 'ohmylms' ),
															esc_html( $available_date->format( 'F j, Y' ) )
														);
														?>
													</p>
												</div>
											</div>
										</div>

										<style id="access-denied-modal">
											.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
												background-color: #F4F5F7;
												padding: 40px 40px;
												border-radius: 14px;
												box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
												text-align: center;
											}

											.ohmylms-access-denied-modal .denied-icon {
												display: block;
												text-align: center;
												margin-bottom: 20px;
											}

											.ohmylms-access-denied-modal .denied-icon svg {
												display: block;
												margin: 0 auto;
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-title {
												margin-bottom: 12px;
												font-size: 36px;
												font-weight: 700;
												line-height: 1;
												color: var(--ohmylms-heading-color);
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-description {
												font-size: 16px !important;
												color: #7A8B9A !important;
												font-weight: 500;
												line-height: 1.5 !important;
												max-width: 380px;
												margin: 0 auto 32px !important;
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-button {
												background-color: #1356F0;
												color: #fff;
												padding: 13px 20px;
												border-radius: 8px;
												border: none;
												cursor: pointer;
												font-size: 14px;
												font-weight: 500;
												line-height: 1;
												text-decoration: none;
												display: inline-block;
											}

											@media screen and (max-width: 1199px) {
												.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
													font-size: 26px;
												}
												.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
													font-size: 14px;
												}

											}

										</style>
										<?php
										$content = ob_get_clean();

										wp_die( $content, 'Access Denied', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
									}
								}
							} elseif ( 'course-content-sequentially' === $drip_feed['type'] ) {
								$prev_content = ohmylms_get_prev_content( $post->ID );
								if ( $prev_content && isset( $prev_content['id'] ) ) {
									if ( ! $student->maybe_completed( $prev_content['id'] ) ) {
										// Get the previous content title for better user experience.
										$prev_content_title     = get_the_title( $prev_content['id'] );
										$incomplete_course_link = get_permalink( $prev_content['id'] );

										ob_start();
										?>
										<div class="ohmylms-access-denied-modal">
											<div class="ohmylms-access-denied-inner">
												<div class="ohmylms-access-denied-modal-content">

													<span class="denied-icon">
														<svg width="134" height="134" fill="none" viewBox="0 0 134 134" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.049 49.476c9.21 31.863-2.566 44.707 6.956 61.118 4.992 8.602 15.525 14.47 27.765 17.526 11.108 2.774 20.138-5.306 31.19-7.223 13.027-2.26 21.105 3.572 33.892-4.208 12.788-7.779 9.802-14.285 15.984-23.715 12.688-19.356 2.225-49.373-8.03-51.999-13.526-3.464-16.578 23.457-29.127 0C78.242 25.204 97.5 2.73 49.142 5.793 22.918 7.453 1.136 25.565 8.05 49.476z"/><g clip-path="url(#clip0_2404_836)"><path fill="#000D25" d="M30.534 102.826c.751 2.037 4.148.167 7.495-2.362 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154-14.194 2.257-37.995.306-40.048-5.607z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.047" d="M30.418 102.356c.406 2.758 4.037.808 7.611-1.892 3.262-2.465 4.893-3.697 5.774-3.635.88.062 2.338 1.524 5.254 4.45 8.995 9.024 21.525 7.154 21.525 7.154m-40.164-6.077L20.935 42.7c-.348-2.193-.522-3.29.05-4.08.573-.789 1.67-.963 3.863-1.312l62.282-9.9m-56.712 74.948c.925 6.279 25.6 8.392 40.164 6.077m0 0l33.579-5.337c2.193-.349 3.29-.523 3.863-1.313.573-.79.399-1.886.05-4.08l-9.943-62.55m-78.658 76.624h3.533m-1.767 1.767v-3.533"/><path fill="#000D25" d="M108.381 48.694c-.827-.425-1.24-.637-1.24-.867 0-.23.413-.442 1.24-.867.591-.303.96-.672 1.264-1.264.424-.827.637-1.24.867-1.24.23 0 .442.413.866 1.24.304.592.673.96 1.265 1.264.827.425 1.24.637 1.24.867 0 .23-.413.443-1.24.867-.592.304-.961.673-1.265 1.264-.424.827-.636 1.24-.866 1.24-.23 0-.443-.413-.867-1.24-.304-.591-.673-.96-1.264-1.264zm6.72 5.741c-.309-.31-.464-.464-.489-.507-.116-.202-.116-.175 0-.377.025-.043.18-.198.489-.507.31-.31.465-.464.508-.49.201-.115.175-.115.376 0 .043.026.198.18.507.49.31.31.465.464.489.507.116.202.116.175 0 .377-.024.043-.179.198-.489.507-.309.31-.464.464-.507.49-.201.115-.175.115-.376 0-.043-.026-.198-.18-.508-.49z"/><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.178" d="M22.01 26.858l-5.015-6.013m7.495 3.23l-1.579-2.928"/><path fill="#000D25" d="M28.223 30.511c.59-1.023 2.021-.943 1.4.131-.62 1.075 3.691-.063 2.448 2.09-.183.319 2.458-1.367 1.545.215L21.75 53.5c-.606 1.05-1.38-1.456-2.27.088-.892 1.543-2.514-.6-2.83-3.032l11.573-20.044zm79.802 58.433c.711-.741 2.023-.261 1.276.518-.747.778 2.306.28.808 1.841-.221.23 1.33 2.502.23 3.648l-14.601 15.21c-.73.76.132-2.87-.942-1.751-.533.556-1.533-.653-2.747-.023-1.214.629-1.686-1.173-1.09-1.771 0 0 16.354-16.93 17.066-17.672z"/></g><path stroke="#000D25" stroke-linecap="round" stroke-linejoin="round" d="M65 82c5.523 0 10-4.477 10-10s-4.477-10-10-10-10 4.477-10 10 4.477 10 10 10zm-7.07-17.07l14.14 14.14"/><defs><clipPath id="clip0_2404_836"><path fill="#fff" d="M0 0h113.062v113.062H0z" transform="translate(10.469 10.469)"/></clipPath></defs></svg>
													</span>

													<h4 class="ohmylms-access-denied-title">
														<?php esc_html_e( 'Access Denied', 'ohmylms' ); ?>
													</h4>

													<p class="ohmylms-access-denied-description">
														<?php
														printf(
															/* translators: %s: text value. */
															esc_html__( 'You must complete "%s" before viewing this content. Please complete it and try again.', 'ohmylms' ),
															esc_html( $prev_content_title )
														);
														?>
													</p>

													<a href="<?php echo esc_url( $incomplete_course_link ); ?>" class="ohmylms-access-denied-button">
														<?php esc_html_e( 'Go to Prerequisite Content', 'ohmylms' ); ?>
													</a>
												</div>
											</div>
										</div>

										<style id="access-denied-modal">
											.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content {
												background-color: #F4F5F7;
												padding: 40px 40px;
												border-radius: 14px;
												box-shadow: -2px 3px 6px rgba(186, 176, 210, 0.10);
												text-align: center;
											}

											.ohmylms-access-denied-modal .denied-icon {
												display: block;
												text-align: center;
												margin-bottom: 20px;
											}

											.ohmylms-access-denied-modal .denied-icon svg {
												display: block;
												margin: 0 auto;
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-title {
												margin-bottom: 12px;
												font-size: 36px;
												font-weight: 700;
												line-height: 1;
												color: var(--ohmylms-heading-color);
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-description {
												font-size: 16px !important;
												color: #7A8B9A !important;
												font-weight: 500;
												line-height: 1.5 !important;
												max-width: 380px;
												margin: 0 auto 32px !important;
											}

											.ohmylms-access-denied-modal .ohmylms-access-denied-button {
												background-color: #1356F0;
												color: #fff;
												padding: 13px 20px;
												border-radius: 8px;
												border: none;
												cursor: pointer;
												font-size: 14px;
												font-weight: 500;
												line-height: 1;
												text-decoration: none;
												display: inline-block;
											}

											@media screen and (max-width: 1199px) {
												.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-title {
													font-size: 26px;
												}
												.ohmylms-access-denied-modal .ohmylms-access-denied-modal-content .ohmylms-access-denied-description {
													font-size: 14px;
												}

											}

										</style>
										<?php
										$content = ob_get_clean();

										wp_die( $content, 'Access Denied', array( 'response' => 403 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template markup buffered above; its dynamic values are escaped there.
									}
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Is lesson sequentially locked for Common hook.
	 *
	 * @param mixed $locked Locked.
	 * @param mixed $content_id Content identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $student_id Student identifier.
	 */
	public function is_lesson_sequentially_locked( $locked, $content_id, $course_id, $student_id ) {
		if ( $locked ) {
			return true;
		}
		return \OhMyLMS\SequentialMode::is_sequentially_locked( $content_id, $course_id, $student_id );
	}
}
