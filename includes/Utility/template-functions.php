<?php
/**
 * Template functions.
 *
 * @package OhMyLMS
 */


use OhMyLMS\Commerce\Checkout;
use function OhMyLMS\Commerce\ecommerce;
/**
 * Add custom body classes for OhMyLMS pages.
 *
 * @param array $classes An array of body class names.
 * @return array The modified array of body class names.
 */
function ohmylms_body_class( $classes ) {
	if ( ohmylms_is_courses_page() || ohmylms_is_course_list_shortcode() ) {
		$classes[] = 'ohmylms-course-archive';
	}

	if ( is_ohmylms() ) {
		$classes[] = 'ohmylms-page';

	} elseif ( is_ohmylms_checkout() ) {
		$classes[] = 'ohmylms-checkout';
		$classes[] = 'ohmylms-page';
	}

	if ( ohmylms_is_course_list_shortcode() ) {
		$classes[] = 'ohmylms-course-list-shortcode';
	}

	return array_unique( $classes );
}

/**
 * Add theme-specific body class.
 *
 * @param array|string $classes An array or string of body class names.
 * @return array|string The modified array or string of body class names.
 *
 * @since 1.0.0
 */
function ohmylms_add_theme_body_class( $classes ) {
	$class = 'theme-' . get_template();
	if ( is_array( $classes ) ) {
		$classes[] = $class;
	} else {
		$classes .= ' ' . $class . ' ';
	}
	return $classes;
}


/**
 * Handle redirects before content is output - hooked into template_redirect so is_page works.
 */
function ohmylms_template_redirect() {
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
add_action( 'template_redirect', 'ohmylms_template_redirect' );


/**
 * Ohmylms get logout redirect url for Template-functions.
 */
function ohmylms_get_logout_redirect_url() {
	/**
	 * Filters the logout redirect URL.
	 *
	 * @since 2.6.9
	 * @param string $logout_url Logout URL.
	 * @return string
	 */
	return apply_filters( 'ohmylms_logout_default_redirect_url', ohmylms_get_page_permalink( 'student_dashboard' ) );
}

/**
 * Get logout link.
 *
 * @since  2.6.9
 * @param string $redirect Redirect URL.
 * @return string
 */
function ohmylms_logout_url( $redirect = '' ) {
	return wp_logout_url( $redirect ? $redirect : wc_get_logout_redirect_url() );
}
/**
 * Render registration fields
 *
 * @since 1.0.0
 */
function ohmylms_student_details() {
	$checkout = ohmylms()->checkout();
	ohmylms_get_template( 'checkout/register.php', array( 'checkout' => $checkout ) );
}


/**
 * Payment method of the course
 *
 * @see 1.0.0
 */
function ohmylms_checkout_payment() {
	if ( \OhMyLMS\Commerce\ecommerce()->cart->needs_payment() ) {
		$available_gateways = \OhMyLMS\Commerce\ecommerce()->gateways()->get_available_payment_gateways();
		\OhMyLMS\Commerce\ecommerce()->gateways()->set_current_gateway( $available_gateways );
	} else {
		$available_gateways = array();
	}
	ohmylms_get_template(
		'checkout/payment.php',
		array(
			'checkout'           => \OhMyLMS\Commerce\ecommerce()->checkout(),
			'available_gateways' => $available_gateways,
			'order_button_text'  => apply_filters( 'ohmylms_order_button_text', __( 'Complete Checkout', 'ohmylms' ) ),
		)
	);
}


/**
 * place order button for mobile
 *
 * @since 1.0.0
 */
function ohmylms_mobile_place_order() {
	ohmylms_get_template(
		'checkout/mobile-place-order-button.php',
		array(
			'checkout'          => \OhMyLMS\Commerce\ecommerce()->checkout(),
			'order_button_text' => apply_filters( 'ohmylms_order_button_text', __( 'Complete Checkout', 'ohmylms' ) ),
		)
	);
}

/**
 * Put $course as global variable when the the_post data is set
 *
 * @param mixed $post Post.
 * @return \OhMyLMS\Course|void
 *
 * @since 1.0.0
 */
function ohmylms_setup_course_data( $post ) {

	unset( $GLOBALS['course'] );

	if ( is_int( $post ) ) {
		$post = get_post( $post );
	}

	if ( empty( $post->post_type ) ) {
		return;
	}

	$GLOBALS['course'] = ohmylms_get_course( $post );
	return $GLOBALS['course'];
}
add_action( 'the_post', 'ohmylms_setup_course_data' );


/**
 * Put $category as global variable
 *
 * @return void
 *
 * @since 1.0.0
 */
function ohmylms_setup_category_data() {
	unset( $GLOBALS['category'] );
	$GLOBALS['category'] = null;
	return $GLOBALS['category'];
}
add_action( 'wp', 'ohmylms_setup_category_data' );

/**
 * Put $category as global variable
 *
 * @return void
 *
 * @since 1.0.0
 */
function ohmylms_setup_num_of_courses_data() {
	unset( $GLOBALS['courses_count'] );
	$GLOBALS['courses_count'] = 10;
	return $GLOBALS['courses_count'];
}
add_action( 'wp', 'ohmylms_setup_num_of_courses_data' );

/**
 * Put $course as global variable when the the_post data is set
 *
 * @param mixed $post Post.
 * @return \OhMyLMS\Course|void
 *
 * @since 1.0.0
 */
function ohmylms_setup_membership_data( $post ) {
	unset( $GLOBALS['membership'] );

	if ( is_int( $post ) ) {
		$post = get_post( $post );
	}

	if ( empty( $post->post_type ) || 'ohmylms-membership' !== $post->post_type ) {
		return;
	}

	$GLOBALS['membership'] = ohmylms_is_pro() ? ohmylms_get_membership( $post ) : null;

	return $GLOBALS['membership'];
}
add_action( 'the_post', 'ohmylms_setup_membership_data' );

/**
 * Open course link
 *
 * @since 1.0.0
 * @param mixed $layout Layout.
 * @param mixed $layout_style Layout style.
 */
function ohmylms_template_loop_product_link_open( $layout, $layout_style ): void {
	if (
		'grid' === $layout &&
		in_array( $layout_style, array( 'grid-style2', 'grid-style3', 'grid-style4' ), true )
	) {
		$link = apply_filters( 'ohmylms_loop_product_link', get_the_permalink() );
		echo '<a href="' . esc_url( $link ) . '" class="ohmylms-course-card-link">';
	}
}


/**
 * Close course link
 *
 * @since 1.0.0
 * @param mixed $layout Layout.
 * @param mixed $layout_style Layout style.
 */
function ohmylms_template_loop_product_link_close( $layout, $layout_style ): void {
	if (
		'grid' === $layout &&
		in_array( $layout_style, array( 'grid-style2', 'grid-style3', 'grid-style4' ), true )
	) {
		echo '</a>';
	}
}

/**
 * Course title
 *
 * @since 1.0.0
 * @param mixed $layout Layout.
 * @param mixed $layout_style Layout style.
 */
function ohmylms_loop_course_title( $layout, $layout_style ) {
	$link = apply_filters( 'ohmylms_loop_product_link', get_the_permalink() );
	if ( 'grid' === $layout ) {
		if ( 'grid-style1' == $layout_style ) {
			?>
			<a href="<?php echo esc_url( $link ); ?>" class="ohmylms-loop-course-link">
				<h2 class="ohmylms-loop-course-title">
					<?php echo esc_html( get_the_title() ); ?>
				</h2>
			</a>
			<?php
		} else {
			?>
			<h2 class="ohmylms-loop-course-title">
				<?php echo esc_html( get_the_title() ); ?>
			</h2>
			<?php
		}
	}
	if ( 'list' === $layout ) {
		?>
		<div class="title-content">
			<a href="<?php echo esc_url( $link ); ?>" class="ohmylms-loop-course-link">
				<h2 class="ohmylms-loop-course-title">
					<?php echo esc_html( get_the_title() ); ?>
				</h2>
			</a>
		<?php
	}
}


/**
 * Course title
 *
 * @since 1.0.0
 */
function ohmylms_loop_course_popup_title() {
	$link         = apply_filters( 'ohmylms_loop_product_link', get_the_permalink() );
	$layout       = get_option( 'ohmylms_archive_page_layout', 'grid' );
	$layout_style = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
	if ( 'grid' === $layout ) {
		if ( 'grid-style1' == $layout_style ) {
			?>
			<a href="<?php echo esc_url( $link ); ?>" class="ohmylms-loop-course-link">
				<h2 class="ohmylms-loop-course-title">
					<?php echo esc_html( get_the_title() ); ?>
				</h2>
			</a>
			<?php
		} else {
			?>
			<h2 class="ohmylms-loop-course-title">
				<?php echo esc_html( get_the_title() ); ?>
			</h2>
			<?php
		}
	}

	if ( 'list' === $layout ) {
		?>
		<div class="title-content">
		<!-- This is start div element. Its closed in ohmylms_template_loop_price() -->
			<a href="<?php echo esc_url( $link ); ?>" class="ohmylms-loop-course-link">
				<h2 class="ohmylms-loop-course-title">
					<?php echo esc_html( get_the_title() ); ?>
				</h2>
			</a>
		<?php
	}
}


if ( ! function_exists( 'ohmylms_loop_course_description' ) ) {
	/**
	 * Load course loop description
	 *
	 * @since 1.0.0
	 */
	function ohmylms_loop_course_description() {

		if ( ! empty( get_the_content() ) ) {
			?>
			<div class="ohmylms-loop-course-description">
				<?php echo get_the_content(); ?>
			</div>
			<?php
		}
	}
}


if ( ! function_exists( 'ohmylms_loop_course_update' ) ) {
	/**
	 * Load course updated date.
	 *
	 * @since 1.0.0
	 * @param mixed $course_id Course identifier.
	 */
	function ohmylms_loop_course_update( $course_id ) {
		$course = ohmylms_get_course( $course_id );
		$date   = '';
		if ( $course ) {
			$date = date( 'M d, Y', $course->get_date_modified( 'edit' )->getTimestamp() );
		}

		?>
		<div class="course-update-wrapper">
			<span class="calendar-icon" style="display: none;">
				<svg width="20" height="20" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill="#7A8B9A" fill-rule="evenodd" d="M5.834 1.666c.46 0 .833.373.833.833v.834h6.667v-.834A.833.833 0 1115 2.5v.834h.834a2.5 2.5 0 012.5 2.5v10a2.5 2.5 0 01-2.5 2.5H4.167a2.5 2.5 0 01-2.5-2.5v-10a2.5 2.5 0 012.5-2.5H5V2.5c0-.46.373-.833.834-.833zm7.5 3.333v.834a.833.833 0 101.666 0v-.834h.834c.46 0 .833.373.833.834v2.5H3.334v-2.5c0-.46.373-.834.833-.834H5v.834a.833.833 0 001.667 0v-.834h6.667zm-10 5v5.834c0 .46.373.833.833.833h11.667c.46 0 .833-.373.833-.833V9.999H3.334z" clip-rule="evenodd"/></svg>
			</span>

			<time datetime="<?php echo esc_attr( $date ); ?>" class="updated-date">
				<?php echo esc_html__( 'Last Updated', 'ohmylms' ); ?>
				<strong><?php echo esc_html( $date ); ?></strong>
			</time>
		</div>
		<?php
	}
}


/**
 * No course found
 *
 * @since 1.0.0
 */
function ohmylms_no_products_found(): void {
	ohmylms_get_template( 'loop/no-course-found.php' );
}


/**
 * Add to cart button
 *
 * @since 1.0.0
 * @param array $args Operation arguments.
 */
function ohmylms_loop_course_add_to_cart( $args = array() ): void {
	global $course;
	if ( ! $course ) {
		return;
	}
	$current_student_id = get_current_user_id();
	$student            = new \OhMyLMS\Data\Student( $current_student_id );
	$maybe_enrolled     = $student->maybe_enrolled( $course->get_id() );
	if ( $course ) {
		$defaults = array(
			'quantity'   => 1,
			'class'      => implode(
				' ',
				array_filter(
					array(
						$course->is_purchasable() && $course->is_in_stock() ? 'add_to_cart_button enroll-button ohmylms-button' : 'ohmylms-button enroll-button',
					)
				)
			),
			'attributes' => array(
				'data-course_id' => $course->get_id(),
				'rel'            => 'nofollow',
			),
		);
		$args     = apply_filters( 'ohmylms_loop_add_to_cart_args', wp_parse_args( $args, $defaults ), $course );
		if ( $maybe_enrolled ) {
			ohmylms_get_template( 'loop/continue-course.php' );
		} elseif ( $course->get_type() === 'cohort-based' ) {
				$cohorts               = $course->get_cohort();
				$has_capacity          = false;
				$has_active_enrollment = false;
				$all_expired           = true;
				$current_time          = current_time( 'timestamp' );
			foreach ( $cohorts as $cohort ) {
				if ( empty( $cohort['has_capacity'] ) || ( ! empty( $cohort['has_capacity'] ) && $cohort['has_capacity'] && ! empty( $cohort['capacity'] ) && (int) ( $cohort['capacity'] ) > $course->get_total_enrolled_users() ) ) {
					if ( 'password_protected' === $course->get_access_type() ) {
						$has_capacity = true;
						ohmylms_get_template( 'loop/password-protected-course.php', $args );
					} else {
						$has_capacity = true;
						ohmylms_get_template( 'loop/add-to-cart.php', $args );
					}
				}

				if ( ! empty( $cohort['enrollment_deadline'] ) ) {
					$enrollment_end = strtotime( $cohort['enrollment_deadline'] );

					if ( $enrollment_end > $current_time ) {
						$has_active_enrollment = true;
					}
				} else {
					// If enrollment deadline is not set, consider it as active enrollment.
					$has_active_enrollment = true;
				}

				// Check if any cohort is not expired.
				if ( ! empty( $cohort['end_date'] ) ) {
					if ( strtotime( $cohort['end_date'] ) >= $current_time ) {
						$all_expired = false;
					}
				} else {
					// If no end date, consider not expired.
					$all_expired = false;
				}
			}

			if ( ! $has_active_enrollment || $all_expired ) {
				ohmylms_get_template( 'loop/exceed-deadline.php', $args );
			} elseif ( ! $has_capacity ) {
				ohmylms_get_template( 'loop/exceed-capacity.php', $args );
			}
		} elseif ( ! $course->get_has_capacity() || ( $course->get_has_capacity() && $course->get_capacity() > $course->get_total_enrolled_users() ) ) {
			if ( 'password_protected' === $course->get_access_type() ) {
				ohmylms_get_template( 'loop/password-protected-course.php', $args );
			} else {
				ohmylms_get_template( 'loop/add-to-cart.php', $args );
			}
		} else {
			ohmylms_get_template( 'loop/exceed-capacity.php', $args );
		}
	}
}


/**
 * Add to cart button
 *
 * @since 1.0.0
 * @param array $args Operation arguments.
 */
function ohmylms_loop_course_after_add_to_cart( $args = array() ): void {
	if ( ! is_user_logged_in() ) {
		return;
	}

	global $course;
	if ( ! $course ) {
		return;
	}
	$current_student_id = get_current_user_id();
	$student            = new \OhMyLMS\Data\Student( $current_student_id );
	$maybe_enrolled     = $student->maybe_enrolled( $course->get_id() );

	if ( $maybe_enrolled ) {
		return;
	}

	if ( ohmylms_is_pro() ) {
		$integrations = get_option( 'ohmylms_integrations', array() );
		if ( isset( $integrations['gamification']['is_enable'] ) && $integrations['gamification']['is_enable'] && \OhMyLMS\Engagement\Reward::maybe_met_rules( 'purchase_course' ) && $course->get_reward_disabled() !== 'yes' ) {
			if ( $course ) {
				if ( ! $course->get_purchase_point() ) {
					return;
				}
				$defaults = array(
					'quantity'   => 1,
					'class'      => implode(
						' ',
						array_filter(
							array(
								$course->is_purchasable() && $course->is_in_stock() ? 'add-to-cart-using-point-button enroll-button ohmylms-button' : 'ohmylms-button enroll-button',
							)
						)
					),
					'attributes' => array(
						'data-course_id' => $course->get_id(),
						'rel'            => 'nofollow',
					),
				);
				$args     = apply_filters( 'ohmylms_loop_add_to_cart_args', wp_parse_args( $args, $defaults ), $course );
				if ( $maybe_enrolled ) {
					ohmylms_get_template( 'loop/continue-course.php' );
				} elseif ( ! $course->get_has_capacity() || ( $course->get_has_capacity() && $course->get_capacity() > $course->get_total_enrolled_users() ) ) {
					ohmylms_get_template( 'loop/add-to-cart-using-point.php', $args );
				} else {
					ohmylms_get_template( 'loop/exceed-capacity.php', $args );
				}
			}
		}
	}
}

/**
 * Load difficulty level for loop item
 *
 * @return void
 * @since 1.0.0
 */
function ohmylms_loop_difficulty_level(): void {
	ohmylms_get_template( 'loop/difficulty-level.php' );
}

/**
 * Load duration for loop item
 *
 * @return void
 * @since 1.0.0
 */
function ohmylms_loop_duration(): void {
	ohmylms_get_template( 'loop/duration.php' );
}


/**
 * Load course meta(e.g author name and enrolled user) for loop
 *
 * @return void
 * @since 1.0.0
 */
function ohmylms_loop_course_meta(): void {
	ohmylms_get_template( 'loop/course-meta.php' );
}

/**
 * Load course cohort for loop
 *
 * @return void
 * @since 1.0.0
 * @param mixed $layout Layout.
 * @param mixed $layout_style Layout style.
 */
function ohmylms_loop_course_cohort( $layout, $layout_style ): void {
	if ( 'grid' === $layout ) {
		ohmylms_get_template( 'loop/course-cohort.php' );
	}
}


/**
 * Load price template for loop
 *
 * @return void
 * @since 1.0.0
 * @param mixed $layout Layout.
 */
function ohmylms_loop_course_price( $layout ): void {
	ohmylms_get_template( 'loop/price.php' );

	if ( 'list' === $layout ) {
		echo '</div>';
		// --- This div element end of .title-content form ohmylms_loop_course_title()---
	}
}


if ( ! function_exists( 'ohmylms_loop_course_author' ) ) {
	/**
	 * Load author name for loop
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function ohmylms_loop_course_author(): void {
		echo '<p class="course-author">by ' . get_the_author() . '</p>';
	}
}


if ( ! function_exists( 'ohmylms_loop_course_certified_tag' ) ) {
	/**
	 * Load certified tag for loop
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function ohmylms_loop_course_certified_tag(): void {
		global $course;
		if ( $course ) {
			if ( ! $course->get_certificate() ) {
				return;
			}
		}
		?>
		<div class="certified-tag">
			<?php
				include OHMYLMS_DIR . '/assets/images/icon/certificate-icon.php';
				echo wp_kses_post( __( 'With <strong>Certified</strong>', 'ohmylms' ) );
			?>
		</div>
		<?php
	}
}


/**
 * Load login and registration field
 *
 * @param mixed $checkout Checkout.
 * @since 1.0.0
 */
function ohmylms_checkout_authentication( $checkout ) {
	ohmylms_get_template(
		'checkout/student-login-signup-options.php',
		array(
			'checkout' => $checkout,
		)
	);
}


/**
 * Billing form
 *
 * @return void
 * @since 1.0.0
 */
function ohmylms_checkout_billing_form() {
	ohmylms_get_template( 'checkout/billing-form' );
}

/**
 * Order summary
 *
 * @return void
 * @since 1.0.0
 */
function ohmylms_checkout_order_summary() {
	ohmylms_get_template( 'checkout/order-summary' );
}


if ( ! function_exists( 'ohmylms_order_review' ) ) {
	/**
	 * Display the order review table on the checkout page.
	 *
	 * @param mixed $checkout Checkout object.
	 * @since 1.0.0
	 */
	function ohmylms_order_review( $checkout ) {
		ohmylms_get_template(
			'checkout/review-order.php',
			array(
				'checkout' => $checkout,
			)
		);
	}
}


/**
 * Load pagination.
 *
 * @return void
 * @since 1.0.0
 */
function ohmylms_pagination_after_course() {
	ohmylms_get_template( 'pagination.php' );
}


if ( ! function_exists( 'ohmylms_course_skeleton' ) ) {

	/**
	 * Ohmylms course skeleton for Template-functions.
	 *
	 * @param bool $echo Echo.
	 */
	function ohmylms_course_skeleton( $echo = true ) {
		ob_start();

		ohmylms_get_template( 'loop/skeleton.php' );

		$loop_start = ob_get_clean();

		if ( $echo ) {
			echo $loop_start;
		} else {
			return $loop_start;
		}
	}
}


if ( ! function_exists( 'ohmylms_course_loop_start' ) ) {

	/**
	 * Start the course loop.
	 *
	 * This function outputs the opening HTML for the course loop.
	 *
	 * @param bool  $echo Whether to echo the output or return it. Default true.
	 * @param mixed $attrs Attrs.
	 * @return string|null The opening HTML for the course loop if $echo is false, null otherwise.
	 * @since 1.0.0
	 */
	function ohmylms_course_loop_start( $echo = true, $attrs = null ) {
		ob_start();

		ohmylms_get_template( 'loop/loop-start.php', array( 'atts' => $attrs ) );

		$loop_start = ob_get_clean();

		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $loop_start;
		} else {
			return $loop_start;
		}
	}
}

if ( ! function_exists( 'ohmylms_course_loop_end' ) ) {

	/**
	 * End the course loop.
	 *
	 * This function outputs the closing HTML for the course loop.
	 *
	 * @param bool  $echo Whether to echo the output or return it. Default true.
	 * @param mixed $attrs Attrs.
	 * @return string|null The closing HTML for the course loop if $echo is false, null otherwise.
	 * @since 1.0.0
	 */
	function ohmylms_course_loop_end( $echo = true, $attrs = null ) {
		ob_start();

		ohmylms_get_template( 'loop/loop-end.php', array( 'atts' => $attrs ) );

		$loop_end = ob_get_clean();

		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $loop_end;
		} else {
			return $loop_end;
		}
	}
}

if ( ! function_exists( 'ohmylms_template_loop_course_thumbnail' ) ) {
	/**
	 * Display the course thumbnail in the loop.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_template_loop_course_thumbnail() {
		echo ohmylms_get_product_thumbnail( 'ohmylms_thumbnail' );
	}
}

if ( ! function_exists( 'ohmylms_get_product_thumbnail' ) ) {
	/**
	 * Get the product thumbnail for the course.
	 *
	 * @param string $size The size of the thumbnail. Default is 'ohmylms_thumbnail'.
	 * @param array  $attr Attributes for the image tag. Default is an empty array.
	 * @param bool   $placeholder Whether to show a placeholder if no thumbnail is found. Default is true.
	 * @return string|void        The image tag for the thumbnail or void if no course is found.
	 */
	function ohmylms_get_product_thumbnail( $size = 'ohmylms_thumbnail', $attr = array(), $placeholder = true ) {
		global $course;

		$course = ohmylms_get_course( $course );

		if ( ! $course ) {
			return;
		}

		$thumbnail_id = $course->get_thumbnail_id();

		$link         = apply_filters( 'ohmylms_loop_product_link', get_the_permalink() );
		$layout_style = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );

		if ( ! $thumbnail_id && $placeholder ) {
			$image_src = ohmylms_placeholder_img();
			ob_start();

			if ( is_single() ) {
				echo '<figure class="ohmylms-loop-course-thumbnail-link">';
					echo '<img src="' . esc_url( $image_src ) . '" alt="' . esc_attr( $course->get_name() ) . '" class="ohmylms-img-fluid" />';
				echo '</figure>';
			} elseif ( 'grid-style1' === $layout_style ) {
					echo '<a href="' . esc_url( $link ) . '" class="ohmylms-loop-course-thumbnail-link">';
						echo '<figure>';
							echo '<img src="' . esc_url( $image_src ) . '" alt="' . esc_attr( $course->get_name() ) . '" class="ohmylms-img-fluid" />';
				if ( $course->get_duration() ) {
					echo '<span class="ohmylms-course-time-label">';
					include OHMYLMS_DIR . '/assets/images/icon/clock-icon-white.php';
					echo esc_html( ohmylms_format_duration( $course->get_duration() ) );
					echo '</span>';
				}
						echo '</figure>';
					echo '</a>';
			} else {
				echo '<div class="ohmylms-loop-course-thumbnail-link">';
					echo '<figure>';
						echo '<img src="' . esc_url( $image_src ) . '" alt="' . esc_attr( $course->get_name() ) . '" class="ohmylms-img-fluid" />';
				if ( $course->get_duration() ) {
					echo '<span class="ohmylms-course-time-label">';
					include OHMYLMS_DIR . '/assets/images/icon/clock-icon-white.php';
					echo esc_html( ohmylms_format_duration( $course->get_duration() ) );
					echo '</span>';
				}
					echo '</figure>';
				echo '</div>';
			}
			return ob_get_clean();
		}

		if ( $thumbnail_id ) {
			ob_start();

			if ( is_single() ) {
				echo '<figure>';
					echo wp_get_attachment_image( $thumbnail_id, $size, false, $attr );
				echo '</figure>';

			} elseif ( 'grid-style1' === $layout_style ) {
					echo '<a href="' . esc_url( $link ) . '" class="ohmylms-loop-course-thumbnail-link">';
						echo '<figure>';
							echo wp_get_attachment_image( $thumbnail_id, $size, false, $attr );
						echo '</figure>';
					echo '</a>';

			} else {
				echo '<div class="ohmylms-loop-course-thumbnail-link">';
					echo '<figure>';
						echo wp_get_attachment_image( $thumbnail_id, $size, false, $attr );
					echo '</figure>';
				echo '</div>';
			}

			return ob_get_clean();
		}
	}
}


if ( ! function_exists( 'ohmylms_show_toast_notices' ) ) {
	/**
	 * Output the toast notice.
	 *
	 * This function includes the template for the toast notice.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_show_toast_notices() {
		ohmylms_get_template( 'global/toast.php' );
	}
}


if ( ! function_exists( 'ohmylms_output_content_wrapper_start' ) ) {
	/**
	 * Output the start of the content wrapper.
	 *
	 * This function includes the template for the start of the content wrapper.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_output_content_wrapper_start() {
		ohmylms_get_template( 'global/wrapper-start.php' );
	}
}


if ( ! function_exists( 'ohmylms_output_content_wrapper_end' ) ) {
	/**
	 * Output the end of the content wrapper.
	 *
	 * This function includes the template for the end of the content wrapper.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_output_content_wrapper_end() {
		ohmylms_get_template( 'global/wrapper-end.php' );
	}
}

if ( ! function_exists( 'ohmylms_scroll_to_top' ) ) {
	/**
	 * scroll to top.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_scroll_to_top() {
		ohmylms_get_template( 'global/scrollto-top.php' );
	}
}


if ( ! function_exists( 'ohmylms_breadcrumb' ) ) {

	/**
	 * Display the breadcrumb for a single course.
	 *
	 * @param array $args Optional. Arguments to customize the breadcrumb. Default empty array.
	 * .
	 * @since 1.0.0
	 */
	function ohmylms_breadcrumb( $args = array() ) {

		if ( ! is_single() ) {
			return;
		}

		global $post;

		$permalinks     = ohmylms_get_permalink_structure();
		$course_page_id = ohmylms_get_page_id( 'course' );
		$course_page    = get_post( $course_page_id );
		$crumbs         = array();
		$permalink      = get_permalink( $post );

		$args = wp_parse_args(
			$args,
			array(
				'delimiter'   => '&nbsp;&#47;&nbsp;',
				'wrap_before' => '<nav class="ohmylms-breadcrumb" aria-label="Breadcrumb">',
				'wrap_after'  => '</nav>',
				'before'      => '',
				'after'       => '',
				'home'        => _x( 'All Courses', 'breadcrumb', 'ohmylms' ),
			)
		);

		if ( ! empty( $args['home'] ) ) {
			$crumbs[] = array(
				$args['home'],
				get_post_type_archive_link( 'ohmylms-course' ),
			);
		}

		if ( $course_page_id && $course_page && isset( $permalinks['course_base'] ) && strstr( $permalinks['course_base'], '/' . $course_page->post_name ) && intval( get_option( 'page_on_front' ) ) !== $course_page_id ) {
			$crumbs[] = array(
				wp_strip_all_tags( get_the_title( $course_page ) ),
				$permalink,
			);
		}

		if ( 'ohmylms-course' === get_post_type( $post ) ) {
			$terms = ohmylms_get_course_terms(
				$post->ID,
				'course_cat',
				array(
					'orderby' => 'parent',
					'order'   => 'DESC',
				)
			);
			if ( $terms ) {
				$main_term = $terms[0];

				$ancestors = get_ancestors( $main_term->term_id, 'course_cat' );
				$ancestors = array_reverse( $ancestors );

				foreach ( $ancestors as $ancestor ) {
					$ancestor = get_term( $ancestor, 'course_cat' );

					if ( ! is_wp_error( $ancestor ) && $ancestor ) {
						$crumbs[] = array(
							wp_strip_all_tags( $ancestor->name ),
							get_term_link( $ancestor ),
						);
					}
				}

				$crumbs[] = array(
					wp_strip_all_tags( $main_term->name ),
					get_term_link( $main_term ),
				);
			}
		}

		$crumbs[] = array(
			wp_strip_all_tags( get_the_title( $post ) ),
			$permalink,
		);

		$args = array(
			'delimiter'   => '&nbsp;&#47;&nbsp;',
			'wrap_before' => '<nav class="ohmylms-breadcrumb"><div class="ohmylms-container"><ul>',
			'wrap_after'  => '</ul></div></nav>',
			'before'      => '<li>',
			'after'       => '</li>',
			'home'        => _x( 'All Courses', 'breadcrumb', 'ohmylms' ),
			'breadcrumb'  => $crumbs,
		);

		ohmylms_get_template( 'global/breadcrumb.php', $args );
	}
}


if ( ! function_exists( 'ohmylms_course_header' ) ) {
	/**
	 * Display the course header in the loop.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_course_header() {
		ohmylms_get_template( 'loop/header.php' );
	}
}


if ( ! function_exists( 'ohmylms_login_form' ) ) {
	/**
	 * Display the login form.
	 *
	 * This function outputs the login form template with the provided arguments.
	 *
	 * @param array $args {
	 *     Optional. Arguments to customize the login form.
	 * .
	 *     @type string $message  Optional. Message to display above the form. Default empty.
	 *     @type string $redirect Optional. URL to redirect to after login. Default empty.
	 *     @type bool   $hidden   Optional. Whether to hide the form initially. Default false.
	 * }
	 * @since 1.0.0
	 */
	function ohmylms_login_form( $args ) {
		$defaults = array(
			'message'     => '',
			'redirect_to' => '',
			'hidden'      => false,
		);

		$args = wp_parse_args( $args, $defaults );

		ohmylms_get_template( 'global/form-login.php', $args );
	}
}


if ( ! function_exists( 'ohmylms_signup_form' ) ) {
	/**
	 * Ohmylms signup form for Template-functions.
	 *
	 * @param mixed $args Operation arguments.
	 */
	function ohmylms_signup_form( $args ) {
		$defaults = array(
			'message'     => '',
			'redirect_to' => '',
			'hidden'      => false,
		);

		$args = wp_parse_args( $args, $defaults );

		ohmylms_get_template( 'global/form-signup.php', $args );
	}
}


if ( ! function_exists( 'ohmylms_checkout_login_form' ) ) {
	/**
	 * Display the checkout login form.
	 *
	 * This function outputs the login form template for the checkout page.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_checkout_login_form() {
		ohmylms_get_template(
			'checkout/form-login.php',
			array(
				'checkout' => \OhMyLMS\Commerce\ecommerce()->checkout(),
			)
		);
	}
}


if ( ! function_exists( 'ohmylms_checkout_signup_form' ) ) {
	/**
	 * Display the checkout signup form.
	 *
	 * This function outputs the signup form template for the checkout page.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_checkout_signup_form() {
		ohmylms_get_template(
			'checkout/form-signup.php',
			array(
				'checkout' => \OhMyLMS\Commerce\ecommerce()->checkout(),
			)
		);
	}
}


if ( ! function_exists( 'ohmylms_checkout_form_title' ) ) {
	/**
	 * Display the checkout form title.
	 *
	 * This function outputs the title for the checkout form.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_checkout_form_title() {
		echo '<div class="customer-info-title-wrapper">';
			echo '<h2 class="ohmylms-checkout-title customer-info-title">' . esc_html__( 'Customer Information', 'ohmylms' ) . '</h2>';
		echo '</div>';
	}
}

if ( ! function_exists( 'ohmylms_checkout_form_contact_title' ) ) {
	/**
	 * Display the checkout form contact title.
	 *
	 * This function outputs the title for the checkout form contact section.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_checkout_form_contact_title() {
		echo '<div class="ohmylms-customer-contact-wrapper">';
			echo '<h2 class="ohmylms-checkout-title customer-info-title">';
				echo esc_html__( 'Contact', 'ohmylms' );
			echo '</h2>';

		if ( ! is_user_logged_in() && \OhMyLMS\Commerce\ecommerce()->checkout()->is_login_enabled() ) {
			?>
					<div class="ohmylms-form-login-toggle">
					<?php echo esc_html__( 'Already have an account?', 'ohmylms' ); ?>

						<a href="#" class="showlogin">
						<?php echo esc_html__( 'Log In', 'ohmylms' ); ?>
						</a>
					</div>
				<?php
		}
		echo '</div>';
	}
}

if ( ! function_exists( 'ohmylms_review_order_mobile' ) ) {
	/**
	 * Display the order review in mobile device.
	 *
	 * This function outputs the order review for the checkout form in mobile.
	 *
	 * @since 1.0.0
	 * @param mixed $checkout Checkout.
	 */
	function ohmylms_review_order_mobile( $checkout ) {
		ohmylms_get_template(
			'checkout/review-order-mobile.php',
			array(
				'checkout' => $checkout,
			)
		);
	}
}


if ( ! function_exists( 'ohmylms_circular_progressbar' ) ) {

	/**
	 * Outputs a circular progress bar.
	 *
	 * @param int    $sqSize Sqsize.
	 * @param int    $progress Progress to display as a percentage. Default 0.
	 * @param int    $thikness Thickness of the progress bar. Default 5.
	 * @param string $backgroundColor Background color of the progress bar. Default #EAEDF4.
	 * @param string $forgroundColor Foreground color of the progress bar. Default #5B65F5.
	 * .
	 * @return string The HTML for the circular progress bar.
	 */
	function ohmylms_circular_progressbar( $sqSize = 110, $progress = 0, $thikness = 5, $backgroundColor = '#EAEDF4', $forgroundColor = '#5B65F5' ) {
		ob_start();

		$progressPercent = $progress;
		$radius          = ( $sqSize - 6 ) / 2;
		$dashArray       = $radius * M_PI * 2;
		$dashOffset      = $dashArray - $dashArray * $progressPercent / 100;
		?>
		<svg class="circle-progress-svg" width="<?php echo esc_attr( $sqSize ); ?>" height="<?php echo esc_attr( $sqSize ); ?>" viewBox="0 0 <?php echo esc_attr( $sqSize ); ?> <?php echo esc_attr( $sqSize ); ?>">
			<circle
				class="circle-background"
				cx=<?php echo $sqSize / 2; ?>
				cy=<?php echo $sqSize / 2; ?>
				r=<?php echo $radius; ?>
				stroke-width="<?php echo esc_attr( $thikness ); ?>px"
				stroke="<?php echo esc_attr( $backgroundColor ); ?>"
			>
			</circle>

			<circle
			class="circle-progress"
			cy=<?php echo $sqSize / 2; ?>
			cx=<?php echo $sqSize / 2; ?>
			r=<?php echo $radius; ?>
			stroke-width="<?php echo esc_attr( $thikness ); ?>px"
			stroke="<?php echo esc_attr( $forgroundColor ); ?>"
			transform="rotate(-90 <?php echo esc_attr( $sqSize / 2 ); ?> <?php echo esc_attr( $sqSize / 2 ); ?> )"
			style="stroke-dasharray: <?php echo esc_attr( $dashArray ); ?>; stroke-dashoffset: <?php echo esc_attr( $dashOffset ); ?>"
			>
			</circle>

		</svg>
		<?php
		return ob_get_clean();
	}
}


if ( ! function_exists( 'ohmylms_show_all_notices' ) ) {
	/**
	 * Display all notices.
	 *
	 * This function is used to display all notices on the checkout page.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_show_all_notices() {
		echo '<div class="ohmylms-notices-wrapper">';
		\OhMyLMS\Commerce\ohmylmse_print_notices();
		echo '</div>';
	}
}


if ( ! function_exists( 'ohmylms_get_header' ) ) {
	/**
	 * Get the header template for OhMyLMS.
	 *
	 * This function checks the WordPress version and theme type to determine
	 * whether to use the block template part or the classic header template.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_get_header() {
		global $wp_version, $ohmylms_block_template_parts;

		if ( version_compare( $wp_version, '5.9', '>=' ) && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			// Discover block styles/module dependencies before wp_head prints the import map.
			$theme_slug                   = wp_get_theme()->get( 'TextDomain' );
			$ohmylms_block_template_parts = array();
			foreach ( array( 'header', 'footer' ) as $part ) {
				$attributes                            = wp_json_encode(
					array(
						'slug'      => $part,
						'theme'     => $theme_slug,
						'tagName'   => $part,
						'className' => 'site-' . $part,
						'layout'    => array( 'inherit' => true ),
					)
				);
				$ohmylms_block_template_parts[ $part ] = do_blocks( '<!-- wp:template-part ' . $attributes . ' /-->' );
			}
			?>
			<!doctype html>
		<html <?php language_attributes(); ?>>
			<head>
				<meta charset="<?php bloginfo( 'charset' ); ?>">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<?php wp_head(); ?>
			</head>

			<body <?php body_class(); ?>>
				<?php wp_body_open(); ?>
				<div class="wp-site-blocks">
					<?php
					// Get the current theme's slug to reference the template part correctly.
					$theme      = wp_get_theme();
					$theme_slug = $theme->get( 'TextDomain' );

					// Output the header block template part.
					echo $ohmylms_block_template_parts['header'];
					?>
				<?php
		} else {
			// Fallback for classic themes.
			get_header( 'course' );
		}
	}
}


if ( ! function_exists( 'ohmylms_get_footer' ) ) {
	/**
	 * Get the footer template for OhMyLMS.
	 *
	 * This function checks the WordPress version and theme type to determine
	 * whether to use the block template part or the classic footer template.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_get_footer() {
		global $wp_version, $ohmylms_block_template_parts;

		if ( version_compare( $wp_version, '5.9', '>=' ) && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			?>
			</div> <!-- Close wp-site-blocks -->
			<?php
			$theme      = wp_get_theme();
			$theme_slug = $theme->get( 'TextDomain' );

			// Output the footer block template part.
			echo isset( $ohmylms_block_template_parts['footer'] ) ? $ohmylms_block_template_parts['footer'] : do_blocks( '<!-- wp:template-part {"slug":"footer","theme":"' . $theme_slug . '","tagName":"footer","className":"site-footer","layout":{"inherit":true}} /-->' );
			?>
			<?php wp_footer(); ?>
			</body>
			</html>
			<?php
		} else {
			// Fallback for classic themes.
			get_footer( 'course' );
		}
	}
}


/**
 * Get the HTML for the rating.
 *
 * @param float $rating The rating value.
 * @param int   $count The number of ratings.
 * .
 * @return string The HTML for the rating.
 */
function ohmylms_get_rating_html( $rating, $count = 0 ) {
	$html = '';
	if ( 0 < $rating ) {
		ob_start();
		echo '(' . $count . ( 1 == $count ? esc_html__( ' Rating', 'ohmylms' ) : esc_html__( ' Ratings', 'ohmylms' ) ) . ')';
		$html = ob_get_clean();
	}
	return apply_filters( 'ohmylms_get_rating_html', $html, $rating, $count );
}


if ( ! function_exists( 'ohmylms_comments' ) ) {

	/**
	 * Output the Review comments template.
	 *
	 * @param WP_Comment $comment Comment object.
	 */
	function ohmylms_comments( $comment ) {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['comment'] = $comment;
		ohmylms_get_template(
			'single-course/review.php',
			array(
				'comment' => $comment,
			)
		);
	}
}

if ( ! function_exists( 'ohmylms_review_display_gravatar' ) ) {
	/**
	 * Display the review authors gravatar
	 *
	 * @param array $comment WP_Comment.
	 * @return void
	 */
	function ohmylms_review_display_gravatar( $comment ) {
		echo get_avatar( $comment, apply_filters( 'ohmylms_review_gravatar_size', '60' ), '' );
	}
}

if ( ! function_exists( 'ohmylms_review_author' ) ) {
	/**
	 * Display the reviewers star rating
	 *
	 * @return void
	 */
	function ohmylms_review_author() {
		if ( post_type_supports( 'ohmylms-course', 'comments' ) ) {
			$student = new \OhMyLMS\Data\Student( get_current_user_id() );
			ohmylms_get_template( 'single-course/review-author.php', array( 'student' => $student ) );
		}
	}
}

if ( ! function_exists( 'ohmylms_review_meta' ) ) {
	/**
	 * Display the review authors meta (name, verified owner, review date)
	 *
	 * @return void
	 */
	function ohmylms_review_meta() {
		ohmylms_get_template( 'single-course/review-meta.php' );
	}
}

if ( ! function_exists( 'ohmylms_review_rating_area' ) ) {
	/**
	 * Display the review authors meta (name, verified owner, review date)
	 *
	 * @return void
	 */
	function ohmylms_review_rating_area() {
		ohmylms_get_template( 'single-course/review-rating.php' );
	}
}

if ( ! function_exists( 'ohmylms_review_display_comment_text' ) ) {

	/**
	 * Display the review content.
	 */
	function ohmylms_review_display_comment_text() {
		echo '<div class="description">';
		comment_text();
		echo '</div>';
	}
}

/**
 * Get HTML for ratings.
 *
 * @since  3.0.0
 * @param float $rating Rating being shown.
 * @param int   $count Total number of ratings.
 * @return string
 */
function ohmylms_get_rating_stars_html( $rating, $count = 0 ) {
	$html = '';

	if ( 0 < $rating ) {
		/* translators: %s: rating */
		$label             = sprintf( __( 'Rated %s out of 5', 'ohmylms' ), $rating );
		$rating_percentage = ( $rating / 5 ) * 100; // Calculate percentage based on rating.
		$html              = '<span class="course-review-rating" role="img" aria-label="' . esc_attr( $label ) . '">';
		$html             .= ohmylms_get_star_rating_html( $rating, $rating_percentage, $count );
		$html             .= '</span>';
	}

	return apply_filters( 'ohmylms_course_get_rating_html', $html, $rating, $count );
}

/**
 *
 * Get HTML for star rating.
 *
 * @param mixed $rating Rating._percentage.
 * @param mixed $rating_percentage Rating percentage.
 * @param int   $count Count.
 * @return mixed|null
 */
function ohmylms_get_star_rating_html( $rating, $rating_percentage, $count = 0 ) {
	$html = '<span class="given-rate" style="width: ' . esc_attr( $rating_percentage ) . '%;">';

	$html .= '</span>';

	return apply_filters( 'ohmylms_get_star_rating_html', $html, $rating, $count );
}

/**
 * Ohmylms get review date html for Template-functions.
 *
 * @param mixed $comment_time Comment time.
 */
function ohmylms_get_review_date_html( $comment_time ) {
	// Calculate the human-readable time difference.
	$time_diff = human_time_diff( strtotime( $comment_time ), current_time( 'timestamp' ) ) . ' ago';

	// Generate the HTML markup for the time element.
	$html = '<time class="review-date">' . esc_html( $time_diff ) . '</time>';

	// Apply a filter for further customization if needed.
	return apply_filters( 'ohmylms_get_review_date_html', $html, $comment_time );
}

if ( ! function_exists( 'ohmylms_default_my_course_tabs' ) ) {

	/**
	 * Get the default dashboard's my-courses tabs.
	 *
	 * This function returns an array of default tabs for dashboard page's my-courses, including
	 * Enrolled courses, In-Progress Courses, Completed Courses.
	 *
	 * @return array The array of default course tabs.
	 * @since 1.0.0
	 * @param mixed $tabs Tabs.
	 */
	function ohmylms_default_my_course_tabs( $tabs ) {

		$tabs['enrolled-courses'] = array(
			'title'    => __( 'Enrolled Courses', 'ohmylms' ),
			'priority' => 5,
			'callback' => 'ohmylms_enrolled_courses_tab_content',
		);

		$tabs['inprogress-courses'] = array(
			'title'    => __( 'In-Progress Courses', 'ohmylms' ),
			'priority' => 10,
			'callback' => 'ohmylms_inprogress_courses_tab_content',
		);

		$tabs['completed-courses'] = array(
			'title'    => __( 'Completed Courses', 'ohmylms' ),
			'priority' => 15,
			'callback' => 'ohmylms_completed_courses_tab_content',
		);

		return $tabs;
	}
}

if ( ! function_exists( 'ohmylms_enrolled_courses_tab_content' ) ) {

	/**
	 * Output the Enrolled courses tab content.
	 */
	function ohmylms_enrolled_courses_tab_content() {
		ohmylms_get_template(
			'profile/tabs/enrolled-course.php',
			array(
				'student' => new \OhMyLMS\Data\Student( get_current_user_id() ),
			)
		);
	}
}

if ( ! function_exists( 'ohmylms_inprogress_courses_tab_content' ) ) {

	/**
	 * Output the In progress tab content.
	 */
	function ohmylms_inprogress_courses_tab_content() {
		ohmylms_get_template(
			'profile/tabs/progress-course.php',
			array(
				'student' => new \OhMyLMS\Data\Student( get_current_user_id() ),
			)
		);
	}
}

if ( ! function_exists( 'ohmylms_completed_courses_tab_content' ) ) {

	/**
	 * Output the In progress tab content.
	 */
	function ohmylms_completed_courses_tab_content() {
		ohmylms_get_template(
			'profile/tabs/completed-course.php',
			array(
				'student' => new \OhMyLMS\Data\Student( get_current_user_id() ),
			)
		);
	}
}


if ( ! function_exists( 'ohmylms_account_student_dashboard_header' ) ) {
	/**
	 * this function should be removed after studendt dashboard is ready.
	 */
	function ohmylms_account_student_dashboard_header() {
		$current_user_id = get_current_user_id();
		$student         = new \OhMyLMS\Data\Student( $current_user_id );

		ohmylms_get_template(
			'global/main-header.php',
			array(
				'student' => $student,
			)
		);
	}
}


if ( ! function_exists( 'ohmylms_account_content' ) ) {

	/**
	 * My Account content output.
	 */
	function ohmylms_account_content() {
		global $wp;
		if ( ! empty( $wp->query_vars ) ) {
			foreach ( $wp->query_vars as $key => $value ) {
				// Ignore pagename param.
				if ( 'pagename' === $key ) {
					continue;
				}
				if ( has_action( 'ohmylms_account_' . $key . '_endpoint' ) ) {
					do_action( 'ohmylms_account_' . $key . '_endpoint', $value );
					return;
				}
			}
		}

		// No endpoint found? Default to dashboard.
		ohmylms_get_template(
			'profile/dashboard.php',
			array(
				'current_user' => get_user_by( 'id', get_current_user_id() ),
			)
		);
	}
}

if ( ! function_exists( 'ohmylms_profile_layout_content' ) ) {

	/**
	 * My Account content output.
	 */
	function ohmylms_profile_layout_content() {
		global $wp;
		if ( ! empty( $wp->query_vars ) ) {
			foreach ( $wp->query_vars as $key => $value ) {
				// Ignore pagename param.
				if ( 'pagename' === $key ) {
					continue;
				}
				if ( has_action( 'ohmylms_layout_' . $key . '_content' ) ) {
					do_action( 'ohmylms_layout_' . $key . '_content', $value );
					return;
				}
			}
		}

		// No endpoint found? Default to dashboard.
		// ohmylms_get_template(
		// 'profile/dashboard.php',
		// array(
		// 'current_user' => get_user_by( 'id', get_current_user_id() ),
		// )
		// );.
	}
}

if ( ! function_exists( 'ohmylms_account_navigation' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_account_navigation() {
		ohmylms_get_template( 'profile/navigation.php' );
	}
}


if ( ! function_exists( 'ohmylms_account_settings_content' ) ) {

	/**
	 * My Account navigation template.
	 *
	 * @param mixed $current_page Current page.
	 */
	function ohmylms_account_settings_content( $current_page ) {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::settings();
	}
}


if ( ! function_exists( 'ohmylms_lms_student_profile_name' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_lms_student_profile_name() {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::name();
	}
}


if ( ! function_exists( 'ohmylms_lms_student_profile_dashboard_content' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_lms_student_profile_dashboard_content() {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::main_content();
	}
}


if ( ! function_exists( 'ohmylms_account_profile_content' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_account_profile_content() {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::profile();
	}
}


if ( ! function_exists( 'ohmylms_account_notification_content' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_account_notification_content() {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::notifications();
	}
}


if ( ! function_exists( 'ohmylms_lms_student_profile_my_course_content' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_lms_student_profile_my_course_content() {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::my_courses();
	}
}

if ( ! function_exists( 'ohmylms_profile_layout' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_profile_layout() {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::profile_layout();
	}
}

if ( ! function_exists( 'ohmylms_account_transactions_history_content' ) ) {

	/**
	 * My Account navigation template.
	 *
	 * @param mixed $current_page Current page.
	 */
	function ohmylms_account_transactions_history_content( $current_page ) {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::transactions_history( $current_page );
	}
}

if ( ! function_exists( 'ohmylms_account_membership_content' ) ) {

	/**
	 * My Account navigation template.
	 *
	 * @param mixed $current_page Current page.
	 */
	function ohmylms_account_membership_content( $current_page ) {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::membership( $current_page );
	}
}

if ( ! function_exists( 'ohmylms_account_invoice_details_content' ) ) {

	/**
	 * My Account navigation template.
	 *
	 * @param mixed $current_page Current page.
	 */
	function ohmylms_account_invoice_details_content( $current_page ) {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::invoice_details( $current_page );
	}
}


if ( ! function_exists( 'ohmylms_account_billing_information_content' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_account_billing_information_content() {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::billing_information();
	}
}


if ( ! function_exists( 'ohmylms_account_profile_edit_content' ) ) {

	/**
	 * My Account navigation template.
	 */
	function ohmylms_account_profile_edit_content() {
		\OhMyLMS\Shortcodes\ShortCodeMyProfile::profile_edit();
	}
}


if ( ! function_exists( 'ohmylms_membership_loop_start' ) ) {

	/**
	 * Start the membership loop.
	 *
	 * This function outputs the opening HTML for the membership loop.
	 *
	 * @param bool $echo Whether to echo the output or return it. Default true.
	 * @return string|null The opening HTML for the membership loop if $echo is false, null otherwise.
	 * @since 1.0.0
	 */
	function ohmylms_membership_loop_start( $echo = true ) {
		ob_start();

		ohmylms_get_template( 'membership-loop/loop-start.php' );

		$loop_start = ob_get_clean();

		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $loop_start;
		} else {
			return $loop_start;
		}
	}
}


if ( ! function_exists( 'ohmylms_membership_loop_end' ) ) {

	/**
	 * End the membership loop.
	 *
	 * This function outputs the closing HTML for the membership loop.
	 *
	 * @param bool $echo Whether to echo the output or return it. Default true.
	 * @return string|null The closing HTML for the membership loop if $echo is false, null otherwise.
	 * @since 1.0.0
	 */
	function ohmylms_membership_loop_end( $echo = true ) {
		ob_start();

		ohmylms_get_template( 'membership-loop/loop-end.php' );

		$loop_end = ob_get_clean();

		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $loop_end;
		} else {
			return $loop_end;
		}
	}
}

if ( ! function_exists( 'ohmylms_membership_header' ) ) {
	/**
	 * Display the course header in the loop.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_membership_header() {
		ohmylms_get_template( 'membership-loop/header.php' );
	}
}


/**
 * No Membership found
 *
 * @since 1.0.0
 */
function ohmylms_no_membership_found(): void {
	ohmylms_get_template( 'membership-loop/no-membership-found.php' );
}

/**
 * Outputs the membership product list template.
 *
 * @since 1.0.0
 */
function ohmylms_membership_product_list() {
	ohmylms_get_template( 'membership-loop/products-list.php' );
}

/**
 * Load the membership title template.
 *
 * This function loads the membership title template for displaying
 * the membership title in the loop.
 *
 * @since 1.0.0
 */
function ohmylms_membership_title() {
	ohmylms_get_template( 'membership-loop/title.php' );
}
/**
 * Ohmylms membership price for Template-functions.
 */
function ohmylms_membership_price() {
	ohmylms_get_template( 'membership-loop/price.php' );
}
/**
 * Ohmylms membership description for Template-functions.
 */
function ohmylms_membership_description() {
	ohmylms_get_template( 'membership-loop/descriptions.php' );
}
/**
 * Ohmylms membership add to cart for Template-functions.
 */
function ohmylms_membership_add_to_cart() {
	ohmylms_get_template( 'membership-loop/add-to-cart.php' );
}


/**
 * Email template functions
 */
if ( ! function_exists( 'ohmylms_email_header' ) ) {
	/**
	 * Ohmylms email header for Template-functions.
	 *
	 * @param mixed $header_title Header title.
	 * @param mixed $email_settings Email settings.
	 */
	function ohmylms_email_header( $header_title, $email_settings ) {
		ohmylms_get_template(
			'emails/email-header.php',
			array(
				'header_title'   => $header_title,
				'email_settings' => $email_settings,
			)
		);
	}
}

if ( ! function_exists( 'ohmylms_email_footer' ) ) {
	/**
	 * Ohmylms email footer for Template-functions.
	 *
	 * @param mixed $settings Configuration settings.
	 * @param mixed $email_settings Email settings.
	 */
	function ohmylms_email_footer( $settings, $email_settings ) {
		ohmylms_get_template(
			'emails/email-footer.php',
			array(
				'settings'       => $settings,
				'email_settings' => $email_settings,
			)
		);
	}
}

if ( ! function_exists( 'ohmylms_email_order_details' ) ) {
	/**
	 * Ohmylms email order details for Template-functions.
	 *
	 * @param mixed $order Order.
	 * @param mixed $email_settings Email settings.
	 */
	function ohmylms_email_order_details( $order, $email_settings ) {
		ohmylms_get_template(
			'emails/email-order-details.php',
			array(
				'order'          => $order,
				'email_settings' => $email_settings,
			)
		);
	}
}

if ( ! function_exists( 'ohmylms_email_order_items' ) ) {
	/**
	 * Ohmylms email order items for Template-functions.
	 *
	 * @param mixed $order Order.
	 * @param mixed $email_settings Email settings.
	 */
	function ohmylms_email_order_items( $order, $email_settings ) {
		ohmylms_get_template(
			'emails/email-order-items.php',
			array(
				'order'          => $order,
				'email_settings' => $email_settings,
			)
		);
	}
}


if ( ! function_exists( 'ohmylms_course_filter_header' ) ) {
	/**
	 * Display the course filter header.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_course_filter_header() {
		ohmylms_get_template( 'filters/filter-header.php' );
	}
}

if ( ! function_exists( 'ohmylms_course_filters' ) ) {
	/**
	 * Display the course filters.
	 *
	 * @since 1.0.0
	 * @param array $atts Atts.
	 */
	function ohmylms_course_filters( $atts = array() ) {

		ohmylms_get_template(
			'filters/filters.php',
			array(
				'atts' => $atts,
			)
		);
	}
}


if ( ! function_exists( 'ohmylms_course_loop_before_category_filter' ) ) {
	/**
	 * Display the course category filter for layout style3 and style4.
	 *
	 * @since 1.0.0
	 * @param mixed $atts Atts.
	 */
	function ohmylms_course_loop_before_category_filter( $atts ) {
		$is_enable_category = isset( $atts ) && is_array( $atts ) && isset( $atts['is_enable_category'] ) ? $atts['is_enable_category'] : get_option( 'ohmylms_archive_page_category_is_enabled', 'no' );
		$layout_style       = isset( $atts ) && is_array( $atts ) && isset( $atts['layout_style'] ) ? $atts['layout_style'] : get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
		$layout             = isset( $atts ) && is_array( $atts ) && isset( $atts['layout'] ) ? $atts['layout'] : get_option( 'ohmylms_archive_page_layout', 'grid' );
		if ( ( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style ) &&
			'grid' === $layout &&
			'yes' === $is_enable_category
		) {
			ohmylms_get_template(
				'filters/category-type-button.php',
				array(
					'is_enable_category' => $is_enable_category,
					'layout_style'       => $layout_style,
				)
			);
		}
	}
}


if ( ! function_exists( 'ohmylms_course_loop_before_filter' ) ) {
	/**
	 * Display the course search bar and sort.
	 *
	 * @since 1.0.0
	 * @param mixed $atts Atts.
	 */
	function ohmylms_course_loop_before_filter( $atts ) {
		$layout_style = isset( $atts ) && is_array( $atts ) && isset( $atts['layout_style'] ) ? $atts['layout_style'] : get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
		$layout       = isset( $atts ) && is_array( $atts ) && isset( $atts['layout'] ) ? $atts['layout'] : get_option( 'ohmylms_archive_page_layout', 'grid' );
		if ( 'grid' === $layout &&
			( 'grid-style1' === $layout_style || 'grid-style2' === $layout_style )
		) {
			ohmylms_get_template(
				'filters/search-sort.php',
				array(
					'atts' => $atts, // Pass shortcode attributes to template.
				)
			);
		}
	}
}

if ( ! function_exists( 'ohmylms_course_carousel_item_hover' ) ) {
	/**
	 * Display the courses carousel item's hover popup.
	 *
	 * @since 1.0.0
	 * @param mixed $atts Atts.
	 */
	function ohmylms_course_carousel_item_hover( $atts ) {
		$layout_style = isset( $atts ) && is_array( $atts ) && isset( $atts['layout_style'] ) ? $atts['layout_style'] : get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
		$layout       = isset( $atts ) && is_array( $atts ) && isset( $atts['layout'] ) ? $atts['layout'] : get_option( 'ohmylms_archive_page_layout', 'grid' );

		if ( 'grid' === $layout && 'grid-style3' === $layout_style ) {
			ohmylms_get_template_part( 'content', 'course-popup', $atts );
		}
	}
}

if ( ! function_exists( 'ohmylms_get_number_suffix' ) ) {
	/**
	 * Returns the ordinal suffix for a given number.
	 *
	 * This function computes the appropriate ordinal suffix ('st', 'nd', 'rd', 'th')
	 * for a given integer. Special cases are considered for numbers ending in 11, 12,
	 * and 13, for which the suffix 'th' is returned.
	 *
	 * @param int $number The number for which to find the ordinal suffix.
	 * @return string The ordinal suffix of the number.
	 */
	function ohmylms_get_number_suffix( $number ) {
		if ( in_array( $number % 100, array( 11, 12, 13 ) ) ) {
			return 'th'; // Special case for 11th, 12th, 13th.
		}

		switch ( $number % 10 ) {
			case 1:
				return 'st';
			case 2:
				return 'nd';
			case 3:
				return 'rd';
			default:
				return 'th';
		}
	}
}


if ( ! function_exists( 'ohmylms_login_header' ) ) {

	/**
	 * Outputs the header for the login pages.
	 *
	 * This function displays a title and description at the top of the login pages.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_login_header() {

		if ( is_ohmylms_checkout() ) {
			return;
		}
		?>
			<div class="ohmylms-login-signup-header">
				<h1>
					<?php echo esc_html__( 'Log in to your account.', 'ohmylms' ); ?>
				</h1>
				<p>
					<?php echo esc_html__( 'Build skills for today, tomorrow, and beyond. Education to future-proof your career.', 'ohmylms' ); ?>
				</p>
			</div>
		<?php
	}
}

if ( ! function_exists( 'ohmylms_signup_header' ) ) {

	/**
	 * Outputs the header for the signup pages.
	 *
	 * This function displays a title and description at the top of the signup pages.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_signup_header() {
		?>
			<div class="ohmylms-login-signup-header">
				<h1>
					<?php echo esc_html__( 'Create your account.', 'ohmylms' ); ?>
				</h1>
				<p>
					<?php echo esc_html__( 'Build skills for today, tomorrow, and beyond. Education to future-proof your career.', 'ohmylms' ); ?>
				</p>
			</div>
		<?php
	}
}

if ( ! function_exists( 'ohmylms_login_signup_form_title' ) ) {

	/**
	 * Outputs the title for the login and signup forms.
	 *
	 * This function displays a title at the top of the login and signup forms.
	 *
	 * @since 1.0.0
	 */
	function ohmylms_login_signup_form_title() {
		?>
			<span class="account-details-title">
				<?php echo is_ohmylms_checkout() ? esc_html__( 'Log in', 'ohmylms' ) : esc_html__( 'Account details', 'ohmylms' ); ?>
			</span>
		<?php
	}
}
