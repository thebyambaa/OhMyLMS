<?php
/**
 * Conditional functions.
 *
 * @package OhMyLMS
 */


/**
 * Is ohmylms for Conditional-functions.
 */
function is_ohmylms() {
	// add a apply filters here if needed in future.
	$maybe_yes = ohmylms_is_courses_page() || ohmylms_is_single_course_page() || ohmylms_is_course_taxonomy() || ohmylms_is_course_category() || is_ohmylms_profile() || ohmylms_is_content_page() || ohmylms_is_membership_page() || ohmylms_is_membership_plan_shortcode() || ohmylms_is_course_list_shortcode() || is_ohmylms_buy_now() || is_ohmylms_dashboard() || is_ohmylms_profile_shortcode() || is_ohmylms_my_courses_shortcode();
	return apply_filters( 'is_ohmylms_page', $maybe_yes );
}

/**
 * Check if the current page is the profile page.
 *
 * @return bool
 * @since 1.0.0
 */
function is_ohmylms_profile() {
	return is_page( ohmylms_get_page_id( 'student_profile' ) );
}

/**
 * Check if the current page is the checkout page.
 *
 * @return bool True if the current page is the checkout page, false otherwise.
 * @since 1.0.0
 */
function is_ohmylms_checkout() {

	$page_id = get_option( 'ohmylms_checkout_page_id' );

	$is_checkout = false;
	if ( ! $page_id ) {
		$is_checkout = false;
	}

	// Alternative 1: Using get_queried_object_id().
	$current_page_id = get_queried_object_id();

	// Alternative 2: Fallback to get_the_ID() if queried object ID is empty.
	if ( ! $current_page_id ) {
		$current_page_id = get_the_ID();
	}

	// Alternative 3: Use global $post as final fallback.
	if ( ! $current_page_id ) {
		global $post;
		$current_page_id = isset( $post->ID ) ? $post->ID : 0;
	}

	if ( $current_page_id != $page_id ) {
		// Check for shortcode, Elementor widget, Gutenberg block, Bricks element, or WPBakery element.
		$is_checkout = ohmylms_post_content_has_shortcode( 'ohmylms_checkout' ) ||
				ohmylms_page_has_checkout_widget( $current_page_id ) ||
				ohmylms_page_has_checkout_block( $current_page_id ) ||
				ohmylms_page_has_checkout_bricks_element( $current_page_id ) ||
				ohmylms_page_has_checkout_wpbakery_element( $current_page_id );
	}

	$is_checkout = $current_page_id == $page_id || $is_checkout;

	return apply_filters( 'is_ohmylms_checkout_page', $is_checkout );
}


/**
 * Is ohmylms buy now for Conditional-functions.
 */
function is_ohmylms_buy_now() {
	return ohmylms_post_content_has_shortcode( 'ohmylms_buy_now' ) ||
			ohmylms_page_has_buy_now_widget() ||
			ohmylms_page_has_buy_now_block() ||
			ohmylms_page_has_buy_now_bricks_element() ||
			ohmylms_page_has_buy_now_wpbakery_element();
}

/**
 * Check if the current page has the dashboard shortcode.
 *
 * @return bool True if the current page has the dashboard shortcode, false otherwise.
 * @since 1.0.0
 */
function is_ohmylms_dashboard() {
	return ohmylms_post_content_has_shortcode( 'ohmylms_dashboard' ) || ohmylms_page_has_dashboard_block();
}

/**
 * Check if the current page has the profile shortcode.
 *
 * @return bool True if the current page has the profile shortcode, false otherwise.
 * @since 1.0.0
 */
function is_ohmylms_profile_shortcode() {
	return ohmylms_post_content_has_shortcode( 'ohmylms_profile' ) || ohmylms_page_has_profile_block();
}

/**
 * Check if the current page has the my courses shortcode.
 *
 * @return bool True if the current page has the my courses shortcode, false otherwise.
 * @since 1.0.0
 */
function is_ohmylms_my_courses_shortcode() {
	return ohmylms_post_content_has_shortcode( 'ohmylms_my_courses' );
}

/**
 * Check if the current page is the order received page.
 *
 * @return bool True if the current page is the order received page, false otherwise.
 * @since 1.0.0
 */
function is_ohmylms_order_received_page() {
	if ( is_ohmylms_checkout() ) {
		global $wp;
		if ( isset( $wp->query_vars['ohmylms-order-received'] ) && ! empty( $wp->query_vars['ohmylms-order-received'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Check if the current page is a OhMyLMS endpoint URL.
 *
 * @param string $endpoint The endpoint to check. Default is an empty string.
 * @return bool True if the current page is a OhMyLMS endpoint URL, false otherwise.
 * @since 1.0.0
 */
function is_ohmylms_endpoint_url( $endpoint = '' ) {
	$endpoints = ohmylms()->ohmylms_endpoint->get_query_vars();
	if ( ! empty( $endpoints[ $endpoint ] ) ) {
		return isset( $endpoints[ $endpoint ] );
	}
	return false;
}


/**
 * Check if the post content has a specific shortcode.
 *
 * @param string $tag The shortcode tag to check for. Default is an empty string.
 * @return bool True if the post content has the shortcode, false otherwise.
 */
function ohmylms_post_content_has_shortcode( $tag = '' ) {
	global $post;
	return is_singular() && is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, $tag ) || str_contains( $post->post_content, 'ohmylms_checkout' ) );
}

/**
 * Check if a page has the OhMyLMS checkout Elementor widget.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the checkout widget, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_checkout_widget( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if Elementor is active.
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return false;
	}

	// Get Elementor data for this page.
	$elementor_data = get_post_meta( $page_id, '_elementor_data', true );

	if ( empty( $elementor_data ) ) {
		return false;
	}

	// Decode JSON data.
	$data = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;

	if ( ! is_array( $data ) ) {
		return false;
	}

	// Recursively search for the checkout widget.
	return ohmylms_search_elementor_data_for_widget( $data, 'ohmylms-checkout' );
}



/**
 * Check if a page has the OhMyLMS checkout Elementor widget.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the checkout widget, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_buy_now_widget( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if Elementor is active.
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return false;
	}

	// Get Elementor data for this page.
	$elementor_data = get_post_meta( $page_id, '_elementor_data', true );

	if ( empty( $elementor_data ) ) {
		return false;
	}

	// Decode JSON data.
	$data = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;

	if ( ! is_array( $data ) ) {
		return false;
	}

	// Recursively search for the buy now widget.
	return ohmylms_search_elementor_data_for_widget( $data, 'ohmylms-buy-now' );
}

/**
 * Recursively search Elementor data for a specific widget type.
 *
 * @param array  $data Elementor data array.
 * @param string $widget_type Widget type to search for.
 * @return bool True if widget is found, false otherwise.
 * @since 1.0.0
 */
function ohmylms_search_elementor_data_for_widget( $data, $widget_type ) {
	if ( ! is_array( $data ) ) {
		return false;
	}

	foreach ( $data as $element ) {
		// Check if this element is the widget we're looking for.
		if ( isset( $element['widgetType'] ) && $element['widgetType'] === $widget_type ) {
			return true;
		}

		// Check nested elements (sections, columns, etc.).
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			if ( ohmylms_search_elementor_data_for_widget( $element['elements'], $widget_type ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Check if a page has the OhMyLMS checkout Gutenberg block.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the checkout block, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_checkout_block( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if block editor functions are available.
	if ( ! function_exists( 'has_block' ) ) {
		return false;
	}

	// Check if the page has the checkout block.
	return has_block( 'ohmylms/checkout', $page_id );
}



/**
 * Check if a page has the OhMyLMS buy now Gutenberg block.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the buy now block, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_buy_now_block( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if block editor functions are available.
	if ( ! function_exists( 'has_block' ) ) {
		return false;
	}

	// Check if the page has the buy now block.
	return has_block( 'ohmylms/buy-now', $page_id );
}

/**
 * Check if a page has the OhMyLMS course list Elementor widget.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the course list widget, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_course_list_widget( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if Elementor is active.
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return false;
	}

	// Get Elementor data for this page.
	$elementor_data = get_post_meta( $page_id, '_elementor_data', true );
	if ( empty( $elementor_data ) ) {
		return false;
	}

	// Parse the JSON data.
	$data = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
	if ( ! is_array( $data ) ) {
		return false;
	}

	// Recursively search for the course list widget.
	return ohmylms_search_elementor_data_for_widget( $data, 'ohmylms-course-list' );
}

/**
 * Compatibility helper for the removed Course List Gutenberg block.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool Always false; the block is no longer registered.
 * @since 1.0.0
 */
function ohmylms_page_has_course_list_block( $page_id = 0 ) {
	// Compatibility for add-ons calling this public helper after the block's removal.
	return false;
}

/**
 * Check if a page has the OhMyLMS dashboard Gutenberg block.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the dashboard block, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_dashboard_block( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if block editor functions are available.
	if ( ! function_exists( 'has_block' ) ) {
		return false;
	}

	// Check if the page has the dashboard block.
	return has_block( 'ohmylms/dashboard', $page_id );
}

/**
 * Check if a page has the OhMyLMS profile Gutenberg block.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the profile block, false otherwise.
 * @since 1.2.5
 */
function ohmylms_page_has_profile_block( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if block editor functions are available.
	if ( ! function_exists( 'has_block' ) ) {
		return false;
	}

	// Check if the page has the profile block.
	return has_block( 'ohmylms/profile', $page_id );
}

/**
 * Compatibility helper for the removed My Courses Gutenberg block.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool Always false; the block is no longer registered.
 * @since 1.2.5
 */
function ohmylms_page_has_my_courses_block( $page_id = 0 ) {
	// Compatibility for add-ons calling this public helper after the block's removal.
	return false;
}

// === BRICKS ELEMENT DETECTION FUNCTIONS ===

/**
 * Check if a page has the OhMyLMS checkout Bricks element.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the checkout element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_checkout_bricks_element( $page_id = 0 ) {
	return ohmylms_page_has_bricks_element( 'ohmylms-checkout', $page_id );
}

/**
 * Check if a page has the OhMyLMS buy now Bricks element.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the buy now element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_buy_now_bricks_element( $page_id = 0 ) {
	return ohmylms_page_has_bricks_element( 'ohmylms-buy-now', $page_id );
}

/**
 * Check if a page has the OhMyLMS course list Bricks element.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the course list element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_course_list_bricks_element( $page_id = 0 ) {
	return ohmylms_page_has_bricks_element( 'ohmylms-course-list', $page_id );
}

/**
 * Check if a page has the OhMyLMS offer button Bricks element.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the offer button element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_offer_button_bricks_element( $page_id = 0 ) {
	return ohmylms_page_has_bricks_element( 'ohmylms-offer-button', $page_id );
}

/**
 * Generic function to check if a page has a specific Bricks element.
 *
 * @param string $element_name The Bricks element name to search for.
 * @param int    $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_bricks_element( $element_name, $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if Bricks is active.
	if ( ! class_exists( '\Bricks\Database' ) ) {
		return false;
	}

	// Get Bricks data for this page.
	$bricks_data = get_post_meta( $page_id, BRICKS_DB_PAGE_CONTENT, true );

	if ( empty( $bricks_data ) ) {
		return false;
	}

	// Bricks stores data as an array of elements.
	if ( ! is_array( $bricks_data ) ) {
		return false;
	}

	// Recursively search for the element.
	return ohmylms_search_bricks_data_for_element( $bricks_data, $element_name );
}

/**
 * Recursively search Bricks data for a specific element type.
 *
 * @param array  $data Bricks data array.
 * @param string $element_name Element name to search for.
 * @return bool True if element is found, false otherwise.
 * @since 1.0.0
 */
function ohmylms_search_bricks_data_for_element( $data, $element_name ) {
	if ( ! is_array( $data ) ) {
		return false;
	}

	foreach ( $data as $element ) {
		// Check if this element is the one we're looking for.
		if ( isset( $element['name'] ) && $element['name'] === $element_name ) {
			return true;
		}

		// Check nested elements (containers, sections, etc.).
		if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
			if ( ohmylms_search_bricks_data_for_element( $element['children'], $element_name ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Ohmylms is courses page for Conditional-functions.
 */
function ohmylms_is_courses_page() {
	return ( is_post_type_archive( OHMYLMS_COURSE_CPT ) || is_page( ohmylms_get_page_id( 'course' ) ) );
}

/**
 * Ohmylms is course list shortcode for Conditional-functions.
 */
function ohmylms_is_course_list_shortcode() {
	// Check for shortcode, Elementor widget, Bricks element, or WPBakery element.
	return ohmylms_post_content_has_shortcode( 'ohmylms_course_list' ) ||
			ohmylms_page_has_course_list_widget() ||
			ohmylms_page_has_course_list_bricks_element() ||
			ohmylms_page_has_course_list_wpbakery_element();
}

/**
 * Ohmylms is membership page for Conditional-functions.
 */
function ohmylms_is_membership_page() {
	return ( is_post_type_archive( OHMYLMS_MEMBERSHIP_CPT ) || is_page( ohmylms_get_page_id( 'membership' ) ) );
}

/**
 * Ohmylms is membership plan shortcode for Conditional-functions.
 */
function ohmylms_is_membership_plan_shortcode() {
	return ohmylms_post_content_has_shortcode( 'ohmylms_membership_plan' ) ||
			ohmylms_page_has_membership_list_block();
}

/**
 * Check if a page has the OhMyLMS membership list Gutenberg block.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the membership list block, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_membership_list_block( $page_id = 0 ) {
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	if ( ! function_exists( 'has_block' ) ) {
		return false;
	}

	return has_block( 'ohmylms/membership-list', $page_id );
}

/**
 * Ohmylms is single course page for Conditional-functions.
 */
function ohmylms_is_single_course_page() {
	return is_singular( OHMYLMS_COURSE_CPT );
}

/**
 * Check if the current page is a single lesson page.
 *
 * @return bool True if the current page is a single lesson page, false otherwise.
 */
function ohmylms_is_single_lesson_page() {
	return is_singular( OHMYLMS_LESSON_CPT );
}

/**
 * Check if the current page is a single lesson page.
 *
 * @return bool True if the current page is a single lesson page, false otherwise.
 */
function ohmylms_is_single_assignment_page() {
	return is_singular( OHMYLMS_ASSIGNMENT_CPT );
}

/**
 * Ohmylms is single quiz page for Conditional-functions.
 */
function ohmylms_is_single_quiz_page() {
	return is_singular( OHMYLMS_QUIZ_CPT );
}
/**
 * Ohmylms is single session for Conditional-functions.
 */
function ohmylms_is_single_session() {
	return is_singular( 'ohmylms-session' );
}
/**
 * Ohmylms is content page for Conditional-functions.
 */
function ohmylms_is_content_page() {
	return ohmylms_is_single_lesson_page() || ohmylms_is_single_assignment_page() || ohmylms_is_single_quiz_page() || ohmylms_is_single_session();
}


/**
 * Ohmylms is course taxonomy for Conditional-functions.
 */
function ohmylms_is_course_taxonomy() {
	return is_tax( get_object_taxonomies( OHMYLMS_COURSE_CPT ) );
}


/**
 * Ohmylms is course category for Conditional-functions.
 *
 * @param string $term Term.
 */
function ohmylms_is_course_category( $term = '' ) {
	return is_tax( 'course_category', $term );
}

/**
 * Check if the current page is a OhMyLMS archive page.
 *
 * @return bool True if the current page is a OhMyLMS archive page, false otherwise.
 * @since 1.0.0
 */
function is_ohmylms_archive() {
	return ( is_post_type_archive( OHMYLMS_COURSE_CPT ) || is_page( ohmylms_get_page_id( 'course' ) ) || ohmylms_is_course_list_shortcode() );
}

/**
 * Check if the current page has any OhMyLMS offer button.
 *
 * @return bool True if the page has an offer button, false otherwise.
 * @since 1.0.0
 */
function is_ohmylms_offer_button() {
	return ohmylms_post_content_has_shortcode( 'ohmylms_offer_button' ) ||
			ohmylms_page_has_offer_button_widget() ||
			ohmylms_page_has_offer_button_block() ||
			ohmylms_page_has_offer_button_bricks_element() ||
			ohmylms_page_has_offer_button_wpbakery_element();
}

/**
 * Check if a page has the OhMyLMS offer button Elementor widget.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the offer button widget, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_offer_button_widget( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if Elementor is active.
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return false;
	}

	// Get Elementor data for this page.
	$elementor_data = get_post_meta( $page_id, '_elementor_data', true );

	if ( empty( $elementor_data ) ) {
		return false;
	}

	// Decode JSON data.
	$data = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;

	if ( ! is_array( $data ) ) {
		return false;
	}

	// Recursively search for the offer button widget.
	return ohmylms_search_elementor_data_for_widget( $data, 'ohmylms-offer-button' );
}

/**
 * Check if a page has the OhMyLMS offer button Gutenberg block.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the offer button block, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_offer_button_block( $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if block editor functions are available.
	if ( ! function_exists( 'has_block' ) ) {
		return false;
	}

	// Check if the page has the offer button block.
	return has_block( 'ohmylms/offer-button', $page_id );
}

// === WPBAKERY ELEMENT DETECTION FUNCTIONS ===

/**
 * Check if a page has the OhMyLMS checkout WPBakery element.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the checkout element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_checkout_wpbakery_element( $page_id = 0 ) {
	return ohmylms_page_has_wpbakery_element( 'ohmylms_checkout', $page_id );
}

/**
 * Check if a page has the OhMyLMS buy now WPBakery element.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the buy now element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_buy_now_wpbakery_element( $page_id = 0 ) {
	return ohmylms_page_has_wpbakery_element( 'ohmylms_buy_now', $page_id );
}

/**
 * Check if a page has the OhMyLMS course list WPBakery element.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the course list element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_course_list_wpbakery_element( $page_id = 0 ) {
	return ohmylms_page_has_wpbakery_element( 'ohmylms_course_list', $page_id );
}

/**
 * Check if a page has the OhMyLMS offer button WPBakery element.
 *
 * @param int $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the offer button element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_offer_button_wpbakery_element( $page_id = 0 ) {
	return ohmylms_page_has_wpbakery_element( 'ohmylms_offer_button', $page_id );
}

/**
 * Generic function to check if a page has a specific WPBakery element.
 * WPBakery stores content as shortcodes in post_content.
 *
 * @param string $shortcode_tag The shortcode tag to search for (e.g., 'ohmylms_checkout').
 * @param int    $page_id The page ID to check. If not provided, uses current page.
 * @return bool True if the page has the element, false otherwise.
 * @since 1.0.0
 */
function ohmylms_page_has_wpbakery_element( $shortcode_tag, $page_id = 0 ) {
	// If no page ID provided, get current page ID.
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}

	// Check if WPBakery is active.
	if ( ! class_exists( 'Vc_Manager' ) ) {
		return false;
	}

	// Get the post content.
	$post = get_post( $page_id );

	if ( ! $post || empty( $post->post_content ) ) {
		return false;
	}

	// Check if the shortcode exists in the post content.
	return has_shortcode( $post->post_content, $shortcode_tag );
}
