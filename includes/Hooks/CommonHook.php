<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Hooks;

use OhMyLMS\Abstracts\HookHandler;

/**
 * Plugin common hook implementation.
 */
class CommonHook extends HookHandler {

	/**
	 * Register hooks.
	 *
	 * This method should be implemented by subclasses to register their specific hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'theme_page_templates', array( $this, 'register_custom_template' ) );
		add_filter( 'template_include', array( $this, 'load_custom_template' ) );
		add_filter( 'display_post_states', array( $this, 'add_display_post_states' ), 10, 2 );
		add_filter( 'the_title', array( $this, 'ohmylms_remove_title' ), 10, 2 );
		add_filter( 'document_title_parts', array( $this, 'fix_archive_page_document_title' ), 10, 1 );
		add_action( 'wp', array( $this, 'fix_archive_page_query_context' ), 1 );
		add_action( 'ohmylms_rest_delete_course', array( $this, 'delete_enrollment' ), 10, 1 );
		add_action( 'ohmylms_rest_before_delete_order', array( $this, 'delete_enrollment_after_delete_order' ), 10, 1 );
		add_action( 'ohmylms_rest_delete_membership', array( $this, 'delete_membership' ), 10, 1 );
		add_action( 'deleted_user', array( $this, 'delete_membership_and_enrollment' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'restrict_access' ), 10 );
		add_action( 'pre_get_posts', array( $this, 'modify_posts_per_page_for_course_archive' ), 10 );
		add_filter( 'mint_webhook_allowed', array( $this, 'maybe_allowed_webhook' ), 10 );

		add_filter( 'wp_head', array( $this, 'ohmylms_add_internal_styles' ), 10 );
		add_action( 'admin_init', array( $this, 'ohmylms_remove_emoji' ), 10 );
		add_action( 'admin_bar_menu', array( $this, 'add_edit_course_menu' ), 999 );
		add_action( 'wp_login', array( $this, 'set_users_last_login' ), 10, 2 );
		add_action( 'pre_get_comments', array( $this, 'modify_comments_query' ), 999 );
		add_action( 'delete_user', array( $this, 'after_delete_wp_user' ), 10 );
		add_filter( 'admin_body_class', array( $this, 'add_ohmylms_admin_body_class' ), 10, 1 );
		// if ( is_ohmylms() ) {
		// add_action( 'the_password_form', array( $this, 'ohmylms_password_protected_form_class' ));
		// }.

		add_filter( 'admin_footer_text', array( $this, 'review_text_in_footer' ), 1 );

		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );

		// Email verification hooks.
		add_action( 'ohmylms_created_customer', array( $this, 'maybe_send_email_verification' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'handle_email_verification_link' ), 5 );
		add_filter( 'ohmylms_is_lesson_locked', array( $this, 'lock_lesson_for_unverified_user' ), 5, 4 );
		add_action( 'ohmylms_lms_student_profile_before_dashboard_content', array( $this, 'output_email_verification_notices' ) );
		add_action( 'ohmylms_before_thankyou', array( $this, 'output_thankyou_verification_notice' ), 10, 1 );
		add_filter( 'ohmylms_allow_add_user_to_space', array( $this, 'maybe_block_unverified_community_join' ), 10, 2 );
		add_filter( 'preprocess_comment', array( $this, 'block_review_for_unverified_user' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle_add_to_cart_after_login' ), 9 );
		add_action( 'template_redirect', array( $this, 'enforce_checkout_login_gate' ), 8 );

		add_filter( 'ohmylms_data_stores', array( $this, 'pro_data_stores' ), 10 );
		add_filter( 'ohmylms_modules', array( $this, 'pro_modules' ), 10 );
		add_filter( 'ohmylms_is_pro', array( $this, 'ohmylms_is_pro' ), 10 );
		add_action( 'template_redirect', array( $this, 'restrict_session_access' ), 10 );
		add_filter( 'post_type_link', array( $this, 'session_single_url' ), 1, 2 );
		add_filter( 'ohmylms_is_lesson_sequentially_locked', array( $this, 'is_lesson_sequentially_locked' ), 10, 4 );
		add_filter( 'ohmylms_can_save_sequential_mode', '__return_true' );
	}

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
	 * Modifies the number of posts per page for the course archive.
	 *
	 * @param WP_Query $query The WP_Query object.
	 */
	public function modify_posts_per_page_for_course_archive( $query ) {
		// Ensure we're modifying the main query and not in the admin area.
		if ( ! is_admin() && $query->is_main_query() ) {
			// Check if the post type is 'ohmylms-course'.
			if ( $query->is_post_type_archive( OHMYLMS_COURSE_CPT ) ) {
				// Modify the number of posts per page.
				$query->set( 'posts_per_page', get_option( 'ohmylms_courses_per_page', 10 ) ); // Set to your desired number.
			}
		}
	}

	/**
	 * Restrict post access for not enrolled users
	 */
	public function restrict_access() {
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
						?>
						<div class="ohmylms-access-denied-modal">
							<div class="ohmylms-access-denied-inner">
								<div class="ohmylms-access-denied-modal-content">
									<span class="denied-icon">
										<svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#6E42D3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
									</span>
									<h4 class="ohmylms-access-denied-title">
										<?php esc_html_e( 'Please verify your email', 'ohmylms' ); ?>
									</h4>
									<p class="ohmylms-access-denied-description">
										<?php esc_html_e( 'Check your inbox and click the verification link to access course content.', 'ohmylms' ); ?>
									</p>
									<p style="margin-top:16px;">
										<a href="<?php echo esc_url( $resend_url ); ?>" style="color:#6E42D3;text-decoration:underline;">
											<?php esc_html_e( 'Resend verification email', 'ohmylms' ); ?>
										</a>
									</p>
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

						wp_die( $content, 'Access Denied', array( 'response' => 403 ) );
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

						wp_die( $content, 'Access Denied', array( 'response' => 403 ) );
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

							wp_die( $content, 'Access Denied', array( 'response' => 403 ) );
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

							wp_die( $content, 'Access Denied', array( 'response' => 403 ) );
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
						wp_die( $content, 'Content Locked', array( 'response' => 403 ) );
					}
				}
			}
		}
	}


	/**
	 * Delete enrollment
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public function delete_enrollment( $course_id ) {
		global $wpdb;
		// Ensure course_id is sanitized.
		$course_id = intval( $course_id );
		// Delete rows where course_id matches.
		$deleted = \OhMyLMS\Student\Enrollments::delete( array( 'course_id' => $course_id ), array( '%d' ) );
	}


	/**
	 * Delete enrollment
	 *
	 * @param mixed $order_id Order identifier.
	 */
	public function delete_enrollment_after_delete_order( $order_id ) {
		global $wpdb;
		// Ensure order_id is sanitized.
		$order_id        = intval( $order_id );
		$parent_order_id = wp_get_post_parent_id( $order_id );
		if ( $parent_order_id ) {
			$order_id = $parent_order_id;
		}
		// Delete rows where order_id matches.
		$deleted = \OhMyLMS\Student\Enrollments::delete( array( 'order_id' => $order_id ), array( '%d' ) );
		if ( ohmylms_is_pro() ) {
			// Delete rows where order_id matches.
			$deleted = $wpdb->delete(
				"{$wpdb->prefix}ohmylms_user_membership", // Table name.
				array( 'order_id' => $order_id ),           // Where condition.
				array( '%d' )                                 // Data type for the condition.
			);
		}
	}

	/**
	 * Delete membership
	 *
	 * @param mixed $membership_id Membership identifier.
	 */
	public function delete_membership( $membership_id ) {
		global $wpdb;
		// Ensure course_id is sanitized.
		$membership_id = intval( $membership_id );
		$order_id      = null;
		if ( ohmylms_is_pro() ) {
			$order_id = $wpdb->get_var(
				$wpdb->prepare( 'SELECT order_id FROM %i WHERE membership_id = %d', $wpdb->prefix . 'ohmylms_user_membership', $membership_id )
			);
		}

		// Check if an order_id was found.
		if ( $order_id ) {
			// Delete rows from ohmylms_user_enrollment where order_id matches.
			\OhMyLMS\Student\Enrollments::delete( array( 'order_id' => (int) $order_id ), array( '%d' ) );
		}
		if ( ohmylms_is_pro() ) {
			// Delete rows where course_id matches.
			$deleted = $wpdb->delete(
				"{$wpdb->prefix}ohmylms_user_membership", // Table name.
				array( 'membership_id' => $membership_id ),           // Where condition.
				array( '%d' )                                 // Data type for the condition.
			);
		}
	}


	/**
	 * Delete membership and enrollment for Common hook.
	 *
	 * @param mixed $id Id.
	 */
	public function delete_membership_and_enrollment( $id ) {
		global $wpdb;

		if ( ohmylms_is_pro() ) {
			$wpdb->delete(
				"{$wpdb->prefix}ohmylms_user_membership", // Table name.
				array( 'user_id' => $id ),           // Where condition.
				array( '%d' )                                 // Data type for the condition.
			);
		}

		\OhMyLMS\Student\Enrollments::delete( array( 'user_id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Load custom template
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public function load_custom_template( $template ) {

		if ( isset( $_GET['ohmylms-certificate-data'] ) ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-certificate.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		if ( is_ohmylms_profile() && get_page_template_slug() === 'ohmylms-profile' ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-profile.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		// Dashboard page template.
		if ( get_page_template_slug() === 'ohmylms-dashboard' ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-dashboard.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		// My Courses page template.
		if ( get_page_template_slug() === 'ohmylms-my-courses' ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-my-courses.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		if ( $this->maybe_bricks_theme() ) {
			return $template;
		}

		// Thank you page without header.
		if ( is_ohmylms_order_received_page() ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-thankyou.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		// Checkout page layout type.
		$checkout_page_layout_type = get_option( 'ohmylms_checkout_page_layout_type' );

		// Check if current post has a checkout block with layoutType attribute.
		if ( is_ohmylms_checkout() ) {
			$post = get_post();
			if ( $post ) {
				// First check for Elementor widgets.
				$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
				if ( ! empty( $elementor_data ) ) {
					$layout_type_from_elementor = $this->find_layout_type_in_elementor_data( $elementor_data, $checkout_page_layout_type );
					if ( $layout_type_from_elementor !== $checkout_page_layout_type ) {
						$checkout_page_layout_type = $layout_type_from_elementor;
					}
				}

				// Then check for Bricks elements.
				$bricks_data = get_post_meta( $post->ID, '_bricks_page_content_2', true );
				if ( ! empty( $bricks_data ) ) {
					$layout_type_from_bricks = $this->find_layout_type_in_bricks_data( $bricks_data, $checkout_page_layout_type );
					if ( $layout_type_from_bricks !== $checkout_page_layout_type ) {
						$checkout_page_layout_type = $layout_type_from_bricks;
					}
				}

				// Then check for Gutenberg blocks.
				if ( $post->post_content && has_blocks( $post->post_content ) ) {
					$blocks                    = parse_blocks( $post->post_content );
					$checkout_page_layout_type = $this->find_layout_type_in_blocks( $blocks, $checkout_page_layout_type );
				}

				// Finally check for shortcode (for other page builders).
				if ( $post->post_content && has_shortcode( $post->post_content, 'ohmylms_checkout' ) ) {
					preg_match( '/\[ohmylms_checkout[^\]]*layout_type=["\']?([^"\'\s\]]+)["\']?[^\]]*\]/', $post->post_content, $matches );
					if ( ! empty( $matches[1] ) ) {
						$checkout_page_layout_type = $matches[1];
					}
				}
			}
		}

		if ( is_ohmylms_checkout() && 'canvas' === $checkout_page_layout_type ) {
			$plugin_template = OHMYLMS_PATH . '/templates/page-template/ohmylms-checkout.php';

			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Recursively search for layoutType in blocks (handles nested blocks)
	 *
	 * @param array  $blocks Array of blocks to search.
	 * @param string $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_blocks( $blocks, $default_value ) {
		foreach ( $blocks as $block ) {
			// Check if this is the checkout block.
			if ( 'ohmylms/checkout' === $block['blockName'] ) {
				if ( ! empty( $block['attrs']['layoutType'] ) ) {
					return $block['attrs']['layoutType'];
				}
			}

			// Recursively check inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->find_layout_type_in_blocks( $block['innerBlocks'], $default_value );
				if ( $found !== $default_value ) {
					return $found;
				}
			}
		}

		return $default_value;
	}

	/**
	 * Find layoutType in Elementor widget data
	 *
	 * @param string|array $elementor_data JSON string or already decoded array of Elementor data.
	 * @param string       $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_elementor_data( $elementor_data, $default_value ) {
		// Decode JSON data — the meta comes back already decoded when it was saved serialized.
		$data = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;

		if ( ! is_array( $data ) ) {
			return $default_value;
		}

		return $this->find_layout_type_in_elementor_elements( $data, $default_value );
	}

	/**
	 * Recursively search for layoutType in Elementor elements
	 *
	 * @param array  $elements Array of Elementor elements.
	 * @param string $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_elementor_elements( $elements, $default_value ) {
		foreach ( $elements as $element ) {
			// Check if this is the checkout widget.
			if ( isset( $element['widgetType'] ) && 'ohmylms-checkout' === $element['widgetType'] ) {
				if ( ! empty( $element['settings']['layout_type'] ) ) {
					return $element['settings']['layout_type'];
				}
			}

			// Recursively check child elements.
			if ( ! empty( $element['elements'] ) ) {
				$found = $this->find_layout_type_in_elementor_elements( $element['elements'], $default_value );
				if ( $found !== $default_value ) {
					return $found;
				}
			}
		}

		return $default_value;
	}

	/**
	 * Find layoutType in Bricks element data
	 *
	 * @param array  $bricks_data Array of Bricks elements.
	 * @param string $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_bricks_data( $bricks_data, $default_value ) {
		if ( ! is_array( $bricks_data ) ) {
			return $default_value;
		}

		return $this->find_layout_type_in_bricks_elements( $bricks_data, $default_value );
	}

	/**
	 * Recursively search for layoutType in Bricks elements
	 *
	 * @param array  $elements Array of Bricks elements.
	 * @param string $default_value Default value to return if not found.
	 * @return string The found layoutType or default value
	 */
	private function find_layout_type_in_bricks_elements( $elements, $default_value ) {
		foreach ( $elements as $element ) {
			// Check if this is the checkout element.
			if ( isset( $element['name'] ) && 'ohmylms-checkout-2' === $element['name'] ) {
				if ( ! empty( $element['settings']['layout_type'] ) ) {
					return $element['settings']['layout_type'];
				}
			}

			// Recursively check child elements.
			if ( ! empty( $element['elements'] ) ) {
				$found = $this->find_layout_type_in_bricks_elements( $element['elements'], $default_value );
				if ( $found !== $default_value ) {
					return $found;
				}
			}
		}

		return $default_value;
	}


	/**
	 * Maybe bricks theme for Common hook.
	 */
	public function maybe_bricks_theme() {
		$current_theme = wp_get_theme();
		$parent_theme  = $current_theme->parent();

		if ( 'Bricks' === $current_theme->get( 'Name' ) ) {
			return true;
		}

		if ( $parent_theme && 'Bricks' === $parent_theme->get( 'Name' ) ) {
			return true;
		}

		return false;
	}


	/**
	 * Register custom template
	 *
	 * @param array $templates Templates.
	 * @return array
	 */
	public function register_custom_template( $templates ) {

		$templates['ohmylms-profile'] = 'OhMyLMS My Profile'; // Template name as it appears in the editor.

		$templates['ohmylms-checkout'] = 'OhMyLMS Checkout'; // Template name as it appears in the editor.

		$templates['ohmylms-thankyou'] = 'OhMyLMS Thank You'; // Template name as it appears in the editor.

		$templates['ohmylms-certificate'] = 'Certificate template';

		$templates['ohmylms-dashboard'] = 'OhMyLMS Dashboard'; // Template name as it appears in the editor.

		$templates['ohmylms-my-courses'] = 'OhMyLMS My Courses'; // Template name as it appears in the editor.

		return $templates;
	}

	/**
	 * Add display post states for Common hook.
	 *
	 * @param mixed $post_states Post states.
	 * @param mixed $post Post.
	 */
	public function add_display_post_states( $post_states, $post ) {
		$state = __( 'OhMyLMS Page', 'ohmylms' );
		if ( ohmylms_get_page_id( 'checkout' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_checkout'] = $state . ' - Checkout';
		}
		if ( ohmylms_get_page_id( 'course' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_course'] = $state . ' - All Course';
		}
		if ( ohmylms_get_page_id( 'membership' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_membership'] = $state . ' - All Membership';
		}
		if ( ohmylms_get_page_id( 'student_dashboard' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_student_dashboard'] = $state . ' - Student Dashboard';
		}
		if ( ohmylms_get_page_id( 'student_profile' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_student_profile'] = $state . ' - Student My Profile';
		}
		if ( ohmylms_get_page_id( 'student_courses' ) === get_post_field( 'ID', $post->ID ) ) {
			$post_states['ohmylms_student_courses'] = $state . ' - Student My Courses';
		}
		return $post_states;
	}

	/**
	 * Remove page title form OhMyLMS pages
	 *
	 * Only strips the title where the theme prints the page's own heading inside the
	 * main loop. Every other consumer of `the_title` — nav menus, breadcrumbs, widgets,
	 * search results — must keep the real title, otherwise WordPress substitutes its
	 * "#123 (no title)" placeholder for these pages.
	 *
	 * @param string  $title Page title.
	 * @param int int $id Post id
	 * .
	 * @return string $title
	 * @since 1.0.0
	 */
	public function ohmylms_remove_title( $title, $id = 0 ) {

		if ( is_admin() ) {
			return $title;
		}

		// Not the page heading being rendered — leave the title untouched.
		if ( ! in_the_loop() || ! is_main_query() || ! is_singular() ) {
			return $title;
		}

		// Only the post actually being viewed, never another post referenced on the page.
		if ( ! $id || get_queried_object_id() !== (int) $id ) {
			return $title;
		}

		$post = get_post( $id );
		if ( ! $post ) {
			return $title;
		}
		$slugs = array( 'ohmylms-all-courses', 'ohmylms-all-membership', 'ohmylms-checkout', 'ohmylms-checkout-2', 'ohmylms-all-courses', 'ohmylms-all-membership', 'ohmylms-checkout', 'my-profile' );
		if ( isset( $post->post_name ) && in_array( $post->post_name, $slugs, true ) ) {
			$title = '';
		}
		return $title;
	}

	/**
	 * Fix the browser tab title on the default Course/Membership pages.
	 *
	 * TemplateLoader::pre_get_posts() rewrites the main query for these pages
	 * into a CPT-archive query so it can loop over courses/memberships. That
	 * makes WordPress build document_title from the CPT's archive label
	 * instead of the page's own post_title. Restore the real page title here.
	 *
	 * @param array $title_parts Title parts.
	 * @return array
	 * @since 1.0.0
	 */
	public function fix_archive_page_document_title( $title_parts ) {
		if ( is_admin() ) {
			return $title_parts;
		}

		$page_id = 0;
		if ( is_page( ohmylms_get_page_id( 'course' ) ) || is_post_type_archive( OHMYLMS_COURSE_CPT ) ) {
			$page_id = ohmylms_get_page_id( 'course' );
		} elseif ( is_page( ohmylms_get_page_id( 'membership' ) ) || is_post_type_archive( OHMYLMS_MEMBERSHIP_CPT ) ) {
			$page_id = ohmylms_get_page_id( 'membership' );
		}

		if ( $page_id && get_post( $page_id ) ) {
			$title_parts['title'] = get_the_title( $page_id );
		}

		return $title_parts;
	}

	/**
	 * SEO plugins (RankMath, Yoast) build the tab title, meta description,
	 * canonical URL and schema from $wp_query's public flags and queried
	 * object — not from document_title_parts/pre_get_document_title, which
	 * fix_archive_page_document_title() above targets. Since
	 * TemplateLoader::pre_get_posts() rewrote the main query into a
	 * CPT-archive query, is_post_type_archive() reads true and is_page()
	 * reads false, so every SEO plugin treats the request as the CPT's
	 * archive (using its archive label/template) instead of the real page.
	 *
	 * Runs once, right after the main query resolves and before wp_head /
	 * any template output, and restores the query's identity to the real
	 * page: is_page()/is_singular() true, is_post_type_archive() false,
	 * queried object set to the real page. The Loop's already-fetched
	 * posts/pagination are untouched (only flags/queried object change), so
	 * the course/membership grid still renders exactly as before — only
	 * what SEO plugins and the theme see as "what page is this" changes,
	 * to match wp-admin's own page title/meta the user actually edits.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function fix_archive_page_query_context() {
		global $wp_query, $post;

		if ( is_admin() ) {
			return;
		}

		$page_id = 0;
		if ( is_post_type_archive( OHMYLMS_COURSE_CPT ) ) {
			$page_id = ohmylms_get_page_id( 'course' );
		} elseif ( is_post_type_archive( OHMYLMS_MEMBERSHIP_CPT ) ) {
			$page_id = ohmylms_get_page_id( 'membership' );
		}

		$page = $page_id ? get_post( $page_id ) : null;
		if ( ! $page ) {
			return;
		}

		$wp_query->is_post_type_archive = false;
		$wp_query->is_archive           = false;
		$wp_query->is_singular          = true;
		$wp_query->is_page              = true;
		$wp_query->queried_object       = $page;
		$wp_query->queried_object_id    = $page_id;

		$post = $page;
		setup_postdata( $post );
	}

	/**
	 * Update earning
	 *
	 * @param mixed $order Order.
	 * @param mixed $data Input data.
	 */
	public function update_earning( $order, $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_earning';
		$query      = $wpdb->prepare( 'SELECT order_id FROM %i WHERE order_id = %d', $table_name, $order->get_id() );
		// Execute the query and return the result.
		$result        = $wpdb->get_row( $query, ARRAY_A );
		$membership_id = null;
		$course_id     = null;
		$status        = $order->get_status();
		$total         = $order->get_total();
		if ( ! empty( $data['membership_id'] ) ) {
			$membership_id = $data['membership_id'];
		} else {
			$items = $order->get_items();
			foreach ( $items as $item ) {
				if ( isset( $item['course_id'] ) ) {
					$course_id = $item['course_id'];
				}
			}
		}
		if ( ! empty( $result ) ) {
			// Update the membership status in the database.
			$result = $wpdb->update(
				$table_name,
				array( 'status' => $order->get_status() ), // Data to update.
				array(
					'order_id' => $order->get_id(),
				), // Where conditions.
				array( '%s' ), // Format for the updated data.
				array( '%d' ) // Format for the where conditions.
			);
		} else {
			// Insert a new record.
			$insert_result = $wpdb->insert(
				$table_name,
				array(
					'order_id'      => $order->get_id(),
					'membership_id' => $membership_id,
					'course_id'     => $course_id,
					'status'        => $status,
					'total'         => $total,
					'created_at'    => current_time( 'mysql' ), // Optional timestamp.
				),
				array( '%d', '%d', '%d', '%s', '%f', '%s' ) // Format for the inserted data.
			);
		}
	}

	/**
	 * Allow webhook url mail mint to create contact from setup wizard
	 *
	 * @param bool $is_allowed Is allowed.
	 * .
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function maybe_allowed_webhook( $is_allowed ) {
		$queryString     = 'mailmint=1&route=webhook&topic=contact&hash=f9fcd11c-fde8-4c7f-9810-0ebb02d2740e';
		$userQueryString = $_SERVER['QUERY_STRING'] ?? '';

		if ( $queryString !== $userQueryString ) {
			return true;
		}

		$userAgent         = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$allowedUserAgents = array(
			'Mozilla', // Covers Firefox.
			'Chrome',  // Covers Chrome.
			'Safari',  // Covers Safari.
			'Opera',   // Covers Opera.
			'Edge',    // Covers Edge.
			'WordPress',
		);

		foreach ( $allowedUserAgents as $allowedUserAgent ) {
			if ( stripos( $userAgent, $allowedUserAgent ) !== false ) {
					return true;
			}
		}
		return false;
	}


	/**
	 * Create contact on mm from appsero optin for Common hook.
	 *
	 * @param mixed $data Input data.
	 */
	function create_contact_on_mm_from_appsero_optin( $data ) {
		if ( isset( $data['admin_email'], $data['first_name'], $data['last_name'] ) ) {
			$json_body_data = wp_json_encode(
				array(
					'email'      => $data['admin_email'],
					'first_name' => $data['first_name'],
					'last_name'  => $data['last_name'],
				)
			);

			$webHookUrl = array(
				'https://staging-useraccount.kinsta.cloud/?mailmint=1&route=webhook&topic=contact&hash=b258136d-6759-4e91-bbab-0b7397af6dc7',
			);

			try {
				if ( ! empty( $webHookUrl ) ) {
					foreach ( $webHookUrl as $url ) {
						wp_remote_request(
							$url,
							array(
								'method'  => 'POST',
								'headers' => array(
									'Content-Type' => 'application/json',
								),
								'body'    => $json_body_data,
							)
						);
					}
				}
			} catch ( \Exception $e ) {
				// Optional opt-in delivery must not interrupt the originating admin action.
				return;
			}
		}
	}


	/**
	 * Outputs CSS variables for use in the plugin's internal styles.
	 *
	 * This function is attached to the `wp_print_styles` action hook, which is
	 * called by WordPress when printing styles in the page header.
	 *
	 * The styles are output inside a `<style>` block with the class
	 * `ohmylms-internal-styles`.
	 *
	 * @since 1.0.0
	 */
	public function ohmylms_add_internal_styles() {
		// Course pages, lesson checks, practice and the school portal all read these variables.
		echo '<style class="ohmylms-internal-styles">' . \OhMyLMS\Design\Tokens::frontend_css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- values are validated hex colors and an allow-listed font.
	}

	/**
	 * Set users last login
	 *
	 * @param mixed  $user_login User login.
	 * @param object $user
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function set_users_last_login( $user_login, $user ) {
		update_user_meta( $user->ID, '_ohmylms_last_login', current_time( 'mysql' ) );
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
	 * After delete WP user
	 * Delete enrollment and membership of the user if exists
	 *
	 * @param int $user_id User ID.
	 * @return void
	 * @since 1.0.0
	 */
	public function after_delete_wp_user( $user_id ) {
		global $wpdb;
		\OhMyLMS\Student\Enrollments::update(
			array(
				'status' => 'cancelled',
			),
			array(
				'user_id' => (int) $user_id,
			)
		);
		if ( ohmylms_is_pro() ) {
			$table_name = $wpdb->prefix . 'ohmylms_user_membership';
			$wpdb->update(
				$table_name,
				array(
					'status' => 'cancelled',
				),
				array(
					'user_id' => $user_id,
				)
			);
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
	private function is_ohmylms_page() {
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
	 * Render a styled inline notice.
	 *
	 * @param string $type 'success' | 'info' | 'warning'.
	 * @param string $message Already-escaped or safe HTML message.
	 * @param bool   $escape  Whether to run esc_html on $message (default true).
	 */
	private function render_verification_notice( string $type, string $message, bool $escape = true ): void {
		$brand = get_option( 'ohmylms_notification_color', get_option( 'ohmylms_email_base_color', '#6E42D3' ) );
		if ( empty( $brand ) ) {
			$brand = '#6E42D3';
		}
		$colors = array(
			'success' => array(
				'bg'     => '#f0fdf4',
				'border' => '#16a34a',
				'text'   => '#15803d',
			),
			'info'    => array(
				'bg'     => '#eff6ff',
				'border' => $brand,
				'text'   => $brand,
			),
			'warning' => array(
				'bg'     => '#fffbeb',
				'border' => '#d97706',
				'text'   => '#92400e',
			),
		);
		$c      = $colors[ $type ] ?? $colors['info'];
		?>
		<div class="ohmylms-verification-notice ohmylms-verification-notice--<?php echo esc_attr( $type ); ?>" style="background:<?php echo esc_attr( $c['bg'] ); ?>;border-left:4px solid <?php echo esc_attr( $c['border'] ); ?>;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:14px;color:<?php echo esc_attr( $c['text'] ); ?>;line-height:1.6;">
			<?php
			if ( $escape ) {
				echo esc_html( $message );
			} else {
				echo wp_kses( $message, array( 'a' => array( 'href' => array() ) ) );
			}
			?>
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
		$resend_url  = \OhMyLMS\Services\EmailVerificationService::get_resend_url();
		$brand_color = get_option( 'ohmylms_notification_color', get_option( 'ohmylms_email_base_color', '#6E42D3' ) ) ?: '#6E42D3';
		?>
		<div class="ohmylms-verification-notice ohmylms-verification-notice--info" style="background:#f5f0ff;border:1px solid <?php echo esc_attr( $brand_color ); ?>;border-radius:10px;padding:18px 22px;margin:24px 0;font-size:15px;color:<?php echo esc_attr( $brand_color ); ?>;line-height:1.7;text-align:center;">
			<strong><?php esc_html_e( 'One more step — verify your email!', 'ohmylms' ); ?></strong><br>
			<?php esc_html_e( 'We sent a verification link to your inbox. Click it to unlock your course content.', 'ohmylms' ); ?>
			<br><br>
			<a href="<?php echo esc_url( $resend_url ); ?>" style="display:inline-block;background:<?php echo esc_attr( $brand_color ); ?>;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:600;">
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

		$dashboard_url = ohmylms_get_dashboard_url() ?: home_url( '/' );

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

	/**
	 * Pro data stores for Common hook.
	 *
	 * @param mixed $stores Stores.
	 */
	public function pro_data_stores( $stores ) {
		$stores['membership-pro'] = 'OhMyLMS\DataStores\MembershipStore';
		$stores['assignment-pro'] = 'OhMyLMS\DataStores\AssignmentStore';
		$stores['course-pro']     = 'OhMyLMS\DataStores\CourseStore';
		$stores['lesson-pro']     = 'OhMyLMS\DataStores\LessonStore';
		$stores['session-pro']    = 'OhMyLMS\DataStores\SessionStore';
		return $stores;
	}

	/**
	 * Ohmylms is pro for Common hook.
	 *
	 * @param mixed $is_pro Is pro.
	 */
	public function ohmylms_is_pro( $is_pro ) {
		return true;
	}

	/**
	 * Pro modules for Common hook.
	 *
	 * @param mixed $modules Modules.
	 */
	public function pro_modules( $modules ) {
		array_push( $modules, 'membership', 'assignment' );
		return $modules;
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

										wp_die( $content, 'Access Denied', array( 'response' => 403 ) );
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

										wp_die( $content, 'Access Denied', array( 'response' => 403 ) );
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

										wp_die( $content, 'Access Denied', array( 'response' => 403 ) );
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

										wp_die( $content, 'Access Denied', array( 'response' => 403 ) );
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
	 * Session single url for Common hook.
	 *
	 * @param mixed $url Url.
	 * @param mixed $id Id.
	 */
	public function session_single_url( $url, $id ) {
		$post = get_post( $id );
		if ( is_object( $post ) && 'ohmylms-session' === $post->post_type ) {
			$course_id          = $post->post_parent;
			$course             = get_post( $course_id );
			$permalink_settings = ohmylms_get_permalink_structure();
			$course_base        = $permalink_settings['course_base'];
			if ( is_object( $course ) ) {
				return home_url( "/{$course_base}/{$course->post_name}/sessions/" . $post->post_name . '/' );
			} else {
				return home_url( "/{$course_base}/sample-course/sessions/" . $post->post_name . '/' );
			}
		}

		return $url;
	}
}
