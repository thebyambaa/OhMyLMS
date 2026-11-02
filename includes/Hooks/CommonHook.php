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
		( new \OhMyLMS\Admin\HookPresentation() )->ohmylms_remove_emoji();
	}

	/**
	 * Add Edit Course menu on admin bar for admin users
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar.
	 * @since 1.0.0
	 */
	public function add_edit_course_menu( \WP_Admin_Bar $admin_bar ) {
		return ( new \OhMyLMS\Admin\HookPresentation() )->add_edit_course_menu( $admin_bar );
	}

	/**
	 * Modifies the number of posts per page for the course archive.
	 *
	 * @param WP_Query $query The WP_Query object.
	 */
	public function modify_posts_per_page_for_course_archive( $query ) {
		return ( new \OhMyLMS\Course\ArchiveQuery() )->modify_posts_per_page_for_course_archive( $query );
	}

	/**
	 * Restrict post access for not enrolled users
	 */
	public function restrict_access() {
		return ( new \OhMyLMS\Learning\AccessPolicy() )->restrict_access();
	}

	/**
	 * Delete enrollment
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public function delete_enrollment( $course_id ) {
		\OhMyLMS\Student\Lifecycle::delete_course( $course_id );
	}

	/**
	 * Delete enrollment
	 *
	 * @param mixed $order_id Order identifier.
	 */
	public function delete_enrollment_after_delete_order( $order_id ) {
		\OhMyLMS\Student\Lifecycle::delete_order( $order_id );
	}

	/**
	 * Delete membership
	 *
	 * @param mixed $membership_id Membership identifier.
	 */
	public function delete_membership( $membership_id ) {
		\OhMyLMS\Student\Lifecycle::delete_plan( $membership_id );
	}

	/**
	 * Delete membership and enrollment for Common hook.
	 *
	 * @param mixed $id Id.
	 */
	public function delete_membership_and_enrollment( $id ) {
		\OhMyLMS\Student\Lifecycle::delete_user( $id );
	}

	/**
	 * Load custom template
	 *
	 * @param string $template Template.
	 * @return string
	 */
	public function load_custom_template( $template ) {
		return ( new \OhMyLMS\ThemeSupport\PageTemplates() )->load_custom_template( $template );
	}

	/**
	 * Maybe bricks theme for Common hook.
	 */
	public function maybe_bricks_theme() {
		return ( new \OhMyLMS\ThemeSupport\PageTemplates() )->maybe_bricks_theme();
	}

	/**
	 * Register custom template
	 *
	 * @param array $templates Templates.
	 * @return array
	 */
	public function register_custom_template( $templates ) {
		return ( new \OhMyLMS\ThemeSupport\PageTemplates() )->register_custom_template( $templates );
	}

	/**
	 * Add display post states for Common hook.
	 *
	 * @param mixed $post_states Post states.
	 * @param mixed $post Post.
	 */
	public function add_display_post_states( $post_states, $post ) {
		return ( new \OhMyLMS\ThemeSupport\PageTemplates() )->add_display_post_states( $post_states, $post );
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
		return ( new \OhMyLMS\ThemeSupport\PageTemplates() )->ohmylms_remove_title( $title, $id );
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
		return ( new \OhMyLMS\ThemeSupport\PageTemplates() )->fix_archive_page_document_title( $title_parts );
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
		( new \OhMyLMS\ThemeSupport\PageTemplates() )->fix_archive_page_query_context();
	}

	/**
	 * Update earning
	 *
	 * @param mixed $order Order.
	 * @param mixed $data Input data.
	 */
	public function update_earning( $order, $data ) {
		\OhMyLMS\Analytics\Earnings::record_order( $order, $data );
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
		return ( new \OhMyLMS\Integrations\MailMint\HookSupport() )->maybe_allowed_webhook( $is_allowed );
	}

	/**
	 * Create contact on mm from appsero optin for Common hook.
	 *
	 * @param mixed $data Input data.
	 */
	public function create_contact_on_mm_from_appsero_optin( $data ) {
		return ( new \OhMyLMS\Integrations\MailMint\HookSupport() )->create_contact_on_mm_from_appsero_optin( $data );
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
		return ( new \OhMyLMS\ThemeSupport\PageTemplates() )->ohmylms_add_internal_styles();
	}

	/**
	 * Set users last login
	 *
	 * @param mixed  $user_login User login.
	 * @param object $user User object.
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function set_users_last_login( $user_login, $user ) {
		( new \OhMyLMS\Student\Activity() )->set_users_last_login( $user_login, $user );
	}

	/**
	 * Modify comments query to exclude WooCommerce order notes
	 *
	 * @param WP_Comment_Query $query The comment query object.
	 * @return void
	 */
	public function modify_comments_query( $query ) {
		( new \OhMyLMS\Admin\HookPresentation() )->modify_comments_query( $query );
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
		\OhMyLMS\Student\Lifecycle::cancel_user( $user_id );
	}

	/**
	 * Add OhMyLMS specific body classes for admin pages
	 *
	 * @param string $classes Existing admin body classes.
	 * @return string Modified admin body classes
	 * @since 1.0.0
	 */
	public function add_ohmylms_admin_body_class( $classes ) {
		return ( new \OhMyLMS\Admin\HookPresentation() )->add_ohmylms_admin_body_class( $classes );
	}

	/**
	 * Customize admin footer text on OhMyLMS admin pages
	 *
	 * @param mixed $text Text.
	 * @return string The modified footer text.
	 */
	public function review_text_in_footer( $text ) {
		return ( new \OhMyLMS\Admin\HookPresentation() )->review_text_in_footer( $text );
	}

	/**
	 * Is ohmylms page for Common hook.
	 */
	private function is_ohmylms_page() {
		return ( new \OhMyLMS\Admin\HookPresentation() )->is_ohmylms_page();
	}

	/**
	 * Add new row on plugins page for OhMyLMS
	 *
	 * @param mixed $links Links.
	 * @param mixed $file File.
	 */
	public function add_row_meta( $links, $file ) {
		return ( new \OhMyLMS\Admin\HookPresentation() )->add_row_meta( $links, $file );
	}

	/**
	 * Output verification notices on the student dashboard.
	 * Handles URL params from verification redirects and shows a persistent
	 * "please verify" banner for logged-in but unverified users.
	 */
	public function output_email_verification_notices(): void {
		( new \OhMyLMS\Student\Verification() )->output_email_verification_notices();
	}

	/**
	 * Render a verification notice. Colours and spacing come from the shared stylesheet.
	 *
	 * @param string $type 'success' | 'info' | 'warning'.
	 * @param string $message Already-escaped or safe HTML message.
	 * @param bool   $escape  Whether to run esc_html on $message (default true).
	 */
	private function render_verification_notice( string $type, string $message, bool $escape = true ): void {
		( new \OhMyLMS\Student\Verification() )->render_verification_notice( $type, $message, $escape );
	}

	/**
	 * Show an email verification notice on the order thank-you page.
	 * Only shown when verification is required and the buyer's account is not yet verified.
	 *
	 * @param int $order_id Order identifier.
	 */
	public function output_thankyou_verification_notice( int $order_id ): void {
		( new \OhMyLMS\Student\Verification() )->output_thankyou_verification_notice( $order_id );
	}

	/**
	 * Trigger email verification after a new student account is created via ohmylms_create_new_student().
	 *
	 * @param int $student_id Student identifier.
	 */
	public function maybe_send_email_verification( int $student_id ): void {
		( new \OhMyLMS\Student\Verification() )->maybe_send_email_verification( $student_id );
	}

	/**
	 * Handle the email verification link (/?ohmylms_verify_email=TOKEN).
	 * Runs early in template_redirect (priority 5) before restrict_access.
	 */
	public function handle_email_verification_link(): void {
		( new \OhMyLMS\Student\Verification() )->handle_email_verification_link();
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
		return ( new \OhMyLMS\Student\Verification() )->lock_lesson_for_unverified_user( $is_locked, $lesson_id, $course_id, $user_id );
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
		return ( new \OhMyLMS\Student\Verification() )->maybe_block_unverified_community_join( $allow, $user_id );
	}

	/**
	 * Block course review submission for users with unverified emails.
	 *
	 * @param array $comment_data Comment data.
	 * @return array
	 */
	public function block_review_for_unverified_user( array $comment_data ): array {
		return ( new \OhMyLMS\Student\Verification() )->block_review_for_unverified_user( $comment_data );
	}

	/**
	 * If guest checkout is disabled, redirect non-logged-in users away from checkout to the login page.
	 */
	public function enforce_checkout_login_gate(): void {
		( new \OhMyLMS\Commerce\CheckoutAccess() )->enforce_checkout_login_gate();
	}

	/**
	 * When a logged-in user lands on the checkout page with ?ohmylms_add_to_cart=COURSE_ID,
	 * add that course to cart and strip the param from the URL.
	 * Used for the guest-checkout-disabled + login redirect flow.
	 */
	public function handle_add_to_cart_after_login(): void {
		( new \OhMyLMS\Commerce\CheckoutAccess() )->handle_add_to_cart_after_login();
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
		return ( new \OhMyLMS\Learning\AccessPolicy() )->is_lesson_sequentially_locked( $locked, $content_id, $course_id, $student_id );
	}

	/**
	 * Pro data stores for Common hook.
	 *
	 * @param mixed $stores Stores.
	 */
	public function pro_data_stores( $stores ) {
		return ( new \OhMyLMS\Extensions\LegacyRegistry() )->pro_data_stores( $stores );
	}

	/**
	 * Ohmylms is pro for Common hook.
	 *
	 * @param mixed $is_pro Is pro.
	 */
	public function ohmylms_is_pro( $is_pro ) {
		return ( new \OhMyLMS\Extensions\LegacyRegistry() )->ohmylms_is_pro( $is_pro );
	}

	/**
	 * Pro modules for Common hook.
	 *
	 * @param mixed $modules Modules.
	 */
	public function pro_modules( $modules ) {
		return ( new \OhMyLMS\Extensions\LegacyRegistry() )->pro_modules( $modules );
	}

	/**
	 * Restrict session access for Common hook.
	 */
	public function restrict_session_access() {
		return ( new \OhMyLMS\Learning\AccessPolicy() )->restrict_session_access();
	}

	/**
	 * Session single url for Common hook.
	 *
	 * @param mixed $url Url.
	 * @param mixed $id Id.
	 */
	public function session_single_url( $url, $id ) {
		return ( new \OhMyLMS\ThemeSupport\PageTemplates() )->session_single_url( $url, $id );
	}
}
