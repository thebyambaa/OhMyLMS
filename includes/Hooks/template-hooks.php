<?php
/**
 * Template hooks.
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'body_class', 'ohmylms_body_class' );
add_action( 'body_class', 'ohmylms_add_theme_body_class' );
add_action( 'admin_body_class', 'ohmylms_add_theme_body_class' );

/**
 * Content Wrappers.
 *
 * @see ohmylms_show_toast_notices()
 * @see ohmylms_output_content_wrapper_start()
 * @see ohmylms_breadcrumb()
 */
add_action( 'ohmylms_before_main_content', 'ohmylms_show_toast_notices', 5 );
add_action( 'ohmylms_before_main_content', 'ohmylms_output_content_wrapper_start', 10 );
add_action( 'ohmylms_before_main_content', 'ohmylms_breadcrumb', 15 );

add_action( 'ohmylms_after_main_content', 'ohmylms_output_content_wrapper_end', 5 );
add_action( 'ohmylms_after_main_content', 'ohmylms_scroll_to_top', 10 );

add_action( 'ohmylms_membership_before_main_content', 'ohmylms_show_toast_notices', 5 );
add_action( 'ohmylms_membership_before_main_content', 'ohmylms_output_content_wrapper_start', 10 );
add_action( 'ohmylms_membership_before_main_content', 'ohmylms_breadcrumb', 15 );

add_action( 'ohmylms_membership_after_main_content', 'ohmylms_output_content_wrapper_end', 5 );
add_action( 'ohmylms_membership_after_main_content', 'ohmylms_scroll_to_top', 10 );

/**
 * Course loop
 *
 * @see ohmylms_no_products_found()
 * @see ohmylms_course_header()
 */
add_action( 'ohmylms_no_products_found', 'ohmylms_no_products_found', 5 );

add_action( 'ohmylms_course_loop_header', 'ohmylms_course_header', 10 );

/**
 * Course loop items
 *
 * @see ohmylms_template_loop_product_link_open()
 * @see ohmylms_template_loop_product_link_close()
 * @see ohmylms_template_loop_course_thumbnail()
 * @see ohmylms_loop_course_title()
 * @see ohmylms_loop_course_meta()
 * @see ohmylms_loop_course_price()
 * @see ohmylms_loop_course_add_to_cart()
*/

add_action( 'ohmylms_before_courses_loop_item', 'ohmylms_template_loop_product_link_open', 5, 2 );
add_action( 'ohmylms_after_courses_loop_item', 'ohmylms_template_loop_product_link_close', 5, 2 );

add_action( 'ohmylms_before_courses_loop_item_content', 'ohmylms_template_loop_course_thumbnail', 5 );

add_action( 'ohmylms_courses_loop_item_title', 'ohmylms_loop_course_title', 5, 2 );

add_action( 'ohmylms_courses_loop_item_description', 'ohmylms_loop_course_description', 5 );


// add_action( 'ohmylms_courses_loop_item_description', 'ohmylms_loop_course_cohort', 10, 2 );.


add_action( 'ohmylms_courses_loop_item_meta', 'ohmylms_loop_course_meta', 5 );
add_action( 'ohmylms_courses_loop_item_meta', 'ohmylms_loop_course_cohort', 10, 2 );

add_action( 'ohmylms_courses_loop_item_price', 'ohmylms_loop_course_price', 5 );

add_action( 'ohmylms_courses_loop_item_add_to_cart', 'ohmylms_loop_course_add_to_cart', 5 );

add_action( 'ohmylms_courses_loop_item_add_to_cart', 'ohmylms_loop_course_after_add_to_cart', 10 );

add_action( 'ohmylms_courses_loop_item_certified_tag', 'ohmylms_loop_course_certified_tag', 5 );

add_action( 'ohmylms_courses_loop_item_author', 'ohmylms_loop_course_author', 5 );


/**
 * Checkout form
 *
 * @see ohmylms_checkout_login_form()
 * @see ohmylms_order_review()
 * @see ohmylms_checkout_payment()
 * @see ohmylms_checkout_order_summary()
 *
 * @since 1.0.0
 */
add_action( 'ohmylms_before_checkout_form_start', 'ohmylms_show_all_notices', 5 );

add_action( 'ohmylms_checkout_before_billing', 'ohmylms_checkout_form_title', 10 );
add_action( 'ohmylms_before_checkout_contact_form', 'ohmylms_checkout_form_contact_title', 10 );
add_action( 'ohmylms_before_checkout_form', 'ohmylms_checkout_login_form', 15 );
add_action( 'ohmylms_before_checkout_form', 'ohmylms_checkout_signup_form', 20 );

add_action( 'ohmylms_checkout_after_billing', 'ohmylms_checkout_payment', 20 );
add_action( 'ohmylms_checkout_after_billing', 'ohmylms_mobile_place_order', 30 );
add_action( 'ohmylms_checkout_order_review', 'ohmylms_order_review', 10 );
add_action( 'ohmylms_checkout_order_summary', 'ohmylms_checkout_order_summary' );


/**
 * Guest checkout
 *
 * @see ohmylms_checkout_authentication
 *
 * @since 1.0.0
 */
add_action( 'ohmylms_after_guest_checkout_email_field', 'ohmylms_checkout_authentication' );


add_filter( 'ohmylms_course_tabs', 'ohmylms_default_course_tabs', 10, 1 );


add_action( 'ohmylms_review_rating_area', 'ohmylms_review_rating_area', 5 );

/**
 * My profile
 *
 * @since 1.0.0
 */

add_action( 'ohmylms_account_navigation', 'ohmylms_account_navigation' );
add_action( 'ohmylms_account_header', 'ohmylms_account_student_dashboard_header' );
add_action( 'ohmylms_account_content', 'ohmylms_account_content' );
add_action( 'ohmylms_account_my-courses_endpoint', 'ohmylms_lms_student_profile_my_course_content' );

add_action( 'ohmylms_account_settings_endpoint', 'ohmylms_profile_layout' );
add_action( 'ohmylms_account_profile_endpoint', 'ohmylms_profile_layout' );
add_action( 'ohmylms_account_notification_endpoint', 'ohmylms_profile_layout' );
add_action( 'ohmylms_account_transactions-history_endpoint', 'ohmylms_profile_layout' );
add_action( 'ohmylms_account_membership_endpoint', 'ohmylms_profile_layout' );
add_action( 'ohmylms_account_invoice-details_endpoint', 'ohmylms_profile_layout' );
add_action( 'ohmylms_account_billing-information_endpoint', 'ohmylms_profile_layout' );
add_action( 'ohmylms_account_profile-edit_endpoint', 'ohmylms_profile_layout' );


add_action( 'ohmylms_profile_layout_content', 'ohmylms_profile_layout_content' );

add_action( 'ohmylms_layout_settings_content', 'ohmylms_account_settings_content' );
add_action( 'ohmylms_layout_profile_content', 'ohmylms_account_profile_content' );
add_action( 'ohmylms_layout_notification_content', 'ohmylms_account_notification_content' );
add_action( 'ohmylms_layout_transactions-history_content', 'ohmylms_account_transactions_history_content' );
add_action( 'ohmylms_layout_membership_content', 'ohmylms_account_membership_content' );
add_action( 'ohmylms_layout_invoice-details_content', 'ohmylms_account_invoice_details_content' );
add_action( 'ohmylms_layout_billing-information_content', 'ohmylms_account_billing_information_content' );
add_action( 'ohmylms_layout_profile-edit_content', 'ohmylms_account_profile_edit_content' );


add_action( 'ohmylms_lms_student_profile_before_dashboard_content', 'ohmylms_lms_student_profile_name' );
add_action( 'ohmylms_lms_student_profile_dashboard_content', 'ohmylms_lms_student_profile_dashboard_content' );

/**
 * Dashboard my courses tabs
 *
 * @since 1.0.0
 */
add_filter( 'ohmylms_my_course_tabs', 'ohmylms_default_my_course_tabs', 10, 1 );

/**
 * Notices
 */
add_action( 'ohmylms_account_content', 'ohmylms_show_all_notices', 5 );


/**
 * Membership Hook
 */
add_action( 'ohmylms_membership_section_header', 'ohmylms_membership_header', 5 );

add_action( 'ohmylms_membership_table_header', 'ohmylms_membership_title', 5 );
add_action( 'ohmylms_membership_table_header', 'ohmylms_membership_price', 10 );
add_action( 'ohmylms_membership_table_header', 'ohmylms_membership_description', 15 );

add_action( 'ohmylms_membership_table_body', 'ohmylms_membership_product_list', 5 );

add_action( 'ohmylms_membership_table_footer', 'ohmylms_membership_add_to_cart', 5 );

add_action( 'ohmylms_no_membership', 'ohmylms_no_membership_found', 5 );



/**
 * Lesson page Hook
 */
add_action( 'ohmylms_before_lesson_main_content', 'ohmylms_show_toast_notices', 5 );


/**
 * Email Hook
 */
add_action( 'ohmylms_email_header', 'ohmylms_email_header', 10, 2 );
add_action( 'ohmylms_email_footer', 'ohmylms_email_footer', 10, 2 );
add_action( 'ohmylms_email_order_details', 'ohmylms_email_order_details', 10, 2 );
add_action( 'ohmylms_email_order_items', 'ohmylms_email_order_items', 10, 2 );



/**
 * Course archive page hooks
 */
add_action( 'ohmylms_course_filter', 'ohmylms_course_filter_header', 5 );
add_action( 'ohmylms_course_filter', 'ohmylms_course_filters', 10 );


/**
 * Checks if the category filter is enabled for layout 'grid-style3' and 'grid-style4' adds an action to apply the category filter before the course loop.
 *
 * @global string $layout_style The current layout style of the course archive page.
*/
add_action( 'ohmylms_before_course_loop', 'ohmylms_course_loop_before_category_filter', 5 );


/**
 * Checks if course layout is 'grid-style1' and 'grid-style1' adds an action to apply the category filter before the course loop.
 *
 * @global string $layout_style The current layout style of the course archive page.
*/
add_action( 'ohmylms_before_course_loop', 'ohmylms_course_loop_before_filter', 10 );


/**
 * Checks if course layout is 'grid-style3' adds an action to apply course item hover popup before the course loop.
 *
 * @global string $layout_style The current layout style of the course archive page.
*/

add_action( 'ohmylms_after_course_loop', 'ohmylms_course_carousel_item_hover', 5 );

// ---course card popup actions----
add_action( 'ohmylms_course_card_popup', 'ohmylms_loop_course_popup_title', 5 );
add_action( 'ohmylms_course_card_popup', 'ohmylms_loop_course_update', 10 );
add_action( 'ohmylms_course_card_popup', 'ohmylms_loop_course_meta', 15 );
add_action( 'ohmylms_course_card_popup', 'ohmylms_loop_course_description', 20 );
add_action( 'ohmylms_course_card_popup', 'ohmylms_loop_course_add_to_cart', 25 );


// ---login page actions----
add_action( 'ohmylms_login_form_start', 'ohmylms_login_header', 5 );
add_action( 'ohmylms_login_form_start', 'ohmylms_login_signup_form_title', 15 );

// ---signup page actions----
add_action( 'ohmylms_signup_form_start', 'ohmylms_signup_header', 5 );
add_action( 'ohmylms_signup_form_start', 'ohmylms_login_signup_form_title', 15 );
