<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Ajax class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS;

use OhMyLMS\Commerce\Checkout;
use OhMyLMS\Commerce\Includes\Tax\TaxService;
use OhMyLMS\Data\Student;
use OhMyLMS\User\UserHelper;
use OhMyLMS\User\UserValidator;
use function OhMyLMS\Commerce\ohmylmse_print_notices;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit();

/**
 * Ajax class
 *
 * @package OhMyLMS
 * @since 1.0.0
 */
class Ajax {

	/**
	 * Init
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		self::add_ajax_actions();
	}


	/**
	 * Add ajax actions
	 *
	 * @since 1.0.0
	 */
	public static function add_ajax_actions(): void {
		$ajax_events_nopriv = array(
			'add_to_cart',
			'login',
			'signup',
			'checkout',
			'purchase_membership',
			'loadmore',
			'lesson_access_nonce',
			'search_filter',
			'apply_coupon',
			'remove_coupon',
			'calculate_tax',
			'resend_verification',
		);

		foreach ( $ajax_events_nopriv as $ajax_event ) {
			add_action( 'wp_ajax_ohmylms_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			add_action( 'wp_ajax_nopriv_ohmylms_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}

		$ajax_events = array(
			'student_profile_image_upload',
			'student_cover_image_upload',
			'student_cover_image_delete',
			'lesson_completed',
			'save_assignment_submission_file',
			'drop_course',
			'save_remaining_time',
			'quiz_exit_submission',
			'cancel_membership',
			'search_pages',
			'save_video_progress',
			'get_video_progress',
			'clear_video_progress',
		);
		foreach ( $ajax_events as $ajax_event ) {
			add_action( 'wp_ajax_ohmylms_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}
	}

	/**
	 * Search pages with ajax request
	 *
	 * @since 1.0.0
	 */
	public static function search_pages(): void {
		ob_start();

		check_ajax_referer( 'search-pages', 'security' );

		if ( ! current_user_can( 'manage_ohmylms' ) ) { // @codingStandardsIgnoreLine
			wp_die( -1 );
		}

		$search_text = isset( $_GET['term'] ) ? ohmylms_clean( wp_unslash( $_GET['term'] ) ) : '';
		$limit       = isset( $_GET['limit'] ) ? absint( wp_unslash( $_GET['limit'] ) ) : -1;
		$exclude_ids = ! empty( $_GET['exclude'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['exclude'] ) ) : array();

		$args                 = array(
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'posts_per_page'         => $limit,
			'post_type'              => 'page',
			'post_status'            => array( 'publish', 'private', 'draft' ),
			's'                      => $search_text,
			'post__not_in'           => $exclude_ids,
		);
		$search_results_query = new \WP_Query( $args );

		$pages_results = array();
		foreach ( $search_results_query->get_posts() as $post ) {
			$pages_results[ $post->ID ] = sprintf(
			/* translators: 1: page name 2: page ID */
				__( '%1$s (ID: %2$s)', 'ohmylms' ),
				get_the_title( $post ),
				$post->ID
			);
		}

		wp_send_json( $pages_results );
	}


	/**
	 * Purchase membership for Ajax.
	 */
	public static function purchase_membership() {
		// Prevent caching of cart operations in this AJAX response.
		nocache_headers();
		if ( ! check_ajax_referer( 'add-to-cart', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request. Please refresh the page and try again.', 'ohmylms' ) ) );
		}

		$membership_id = ohmylms_clean( wp_unslash( $_POST['membership_id'] ?? '' ) );
		if ( \OhMyLMS\Membership\FreeSale::active( ohmylms_get_membership( absint( $membership_id ) ) ) ) {
			wp_send_json( array( 'status' => 'success', 'redirect_url' => \OhMyLMS\Student\Hub::url( 'membership', 'plans' ) ) );
		}
		ecommerce()->membership()->request_membership( $membership_id );
		die();
	}


	/**
	 * Add to cart course actions
	 *
	 * @since 1.0.0
	 */
	public static function add_to_cart(): void {
		// Prevent caching of cart operations.
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! check_ajax_referer( 'add-to-cart', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request. Please refresh the page and try again.', 'ohmylms' ) ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$quantity = empty( $_POST['quantity'] ) ? 1 : absint( $_POST['quantity'] );
		if ( isset( $_POST['membership_id'] ) ) {
			$result = self::add_membership_to_cart( absint( $_POST['membership_id'] ), $quantity );
			wp_send_json( $result['body'], $result['status'] );
		}
		if ( ! isset( $_POST['course_id'] ) ) {
			return;
		}
		$result = self::add_course_to_cart(
			absint( $_POST['course_id'] ?? 0 ),
			$quantity,
			isset( $_POST['purchase_by'] ) ? ohmylms_clean( wp_unslash( $_POST['purchase_by'] ) ) : ''
		);
		// phpcs:enable
		wp_send_json( $result['body'], $result['status'] );
	}

	/**
	 * Cart answer shared by the AJAX action and `POST ohmylms-commerce/v1/cart/items`.
	 *
	 * @param array $body   Response body (`status`, `message`, `redirect_url`).
	 * @param int   $status HTTP status.
	 * @return array{status:int, body:array}
	 */
	private static function cart_answer( array $body, $status = 200 ): array {
		return array(
			'status' => $status,
			'body'   => $body,
		);
	}

	/**
	 * Add a course to the cart. Callers verify their own nonce.
	 *
	 * @param int    $course_id   Course.
	 * @param int    $quantity    Quantity.
	 * @param string $purchase_by `point` for a bonus-points purchase.
	 * @return array{status:int, body:array}
	 */
	public static function add_course_to_cart( $course_id, $quantity = 1, $purchase_by = '' ): array {
		$course_status = get_post_status( $course_id );

		$course = ohmylms_get_course( $course_id );
		if ( ! $course || ! $course->is_purchasable() ) {
			return self::cart_answer(
				array(
					'status'  => 'error',
					'message' => __( 'Sorry! This course is not purchasable.', 'ohmylms' ),
				)
			);
		}

		if ( ! $course->is_in_stock() ) {
			return self::cart_answer(
				array(
					'status'  => 'error',
					'message' => __( 'Sorry! The number of enrolled students has reached its limit.', 'ohmylms' ),
				)
			);
		}

		$checkout_url = ohmylms_get_checkout_url();
		if ( ! $checkout_url ) {
			return self::cart_answer(
				array(
					'status'  => 'error',
					'message' => __( 'Enrollment is temporarily unavailable. Please contact the site administrator.', 'ohmylms' ),
				),
				503
			);
		}

		// Not logged in + guest checkout disabled → redirect to checkout which has its own login form.
		if ( ! is_user_logged_in() && ! ohmylms_is_guest_purchase_enabled() ) {
			// Store course in a short-lived cookie so it can be added to cart after login/verification.
			if ( ! headers_sent() ) {
				setcookie( 'ohmylms_pending_course', (string) $course_id, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
			}
			return self::cart_answer(
				array(
					'status'       => 'success',
					'redirect_url' => $checkout_url,
					'message'      => __( 'Please log in to complete your purchase.', 'ohmylms' ),
				)
			);
		}

		$redirect_url = add_query_arg( 'nocache', time(), $checkout_url );
		$cart_meta    = array();

		if ( 'point' === $purchase_by ) {
			$cart_meta['purchase_by'] = 'point';
		}

		if ( false !== ecommerce()->cart->add_to_cart( $course_id, $quantity, $cart_meta ) && 'publish' === $course_status ) {

			do_action( 'ohmylms_ajax_added_to_cart', $course_id );

			return self::cart_answer(
				array(
					'status'       => 'success',
					'redirect_url' => $redirect_url,
					'message'      => __( 'Successfully added to cart.', 'ohmylms' ),
				)
			);
		}

		return self::cart_answer(
			array(
				'status'  => 'error',
				'message' => __( 'Add to cart failed.', 'ohmylms' ),
			)
		);
	}


	/**
	 * Process the checkout action via AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function checkout(): void {
		// Prevent caching of checkout operations.
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! check_ajax_referer( 'ohmylms-process_checkout', 'ohmylms-process-checkout-nonce', false ) ) {
			wp_send_json(
				array(
					'result'  => 'failure',
					'message' => esc_html__( 'Your checkout session expired. Reload the page and try again.', 'ohmylms' ),
					'reload'  => true,
				)
			);
		}

		ecommerce()->checkout()->process_checkout();
		die();
	}

	/**
	 * Handle the signup action via AJAX.
	 *
	 * @since 1.0.0
	 * @throws \Exception When the operation cannot be completed.
	 */
	public static function signup() {
		$nonce_value = isset($_REQUEST['ohmylms-signup-nonce']) ? ohmylms_clean( wp_unslash( $_REQUEST['ohmylms-signup-nonce'] )) : ''; // @codingStandardsIgnoreLine.
		$valid_nonce = wp_verify_nonce( $nonce_value, 'ohmylms-signup' );
		if ( isset( $_POST['email'], $_POST['password'] ) && $valid_nonce ) {
			try {
				$validation_error = new \WP_Error();

				if ( $validation_error->get_error_code() ) {
					\OhMyLMS\Commerce\ohmylmse_add_notice( $validation_error->get_error_message(), 'error', array() );
					\OhMyLMS\Commerce\ohmylmse_print_notices( true );
					throw new \Exception( '<strong>' . __( 'Error:', 'ohmylms' ) . '</strong> ' . $validation_error->get_error_message() );
				}

				$email        = sanitize_email( wp_unslash( $_POST['email'] ) );
				$password     = ohmylms_clean( wp_unslash( $_POST['password'] ) );
				$first_name   = ! empty( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
				$last_name    = ! empty( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
				$display_name = trim( $first_name . ' ' . $last_name );

				$student_role = function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber';

				$user_data = array(
					'user_login'   => $email,
					'user_email'   => $email,
					'user_pass'    => $password,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'display_name' => $display_name ? $display_name : '',
					'role'         => $student_role,
					'user_status'  => 1,
				);

				$user_id = wp_insert_user( $user_data );

				if ( is_wp_error( $user_id ) ) {
					\OhMyLMS\Commerce\ohmylmse_add_notice( $user_id->get_error_message(), 'error', array() );
					\OhMyLMS\Commerce\ohmylmse_print_notices( true );
					throw new \Exception( $user_id->get_error_message() );
				}

				$user = new \WP_User( $user_id );
				$user->set_role( $student_role );

				$phone   = ! empty( $_POST['phone'] ) ? ohmylms_clean( wp_unslash( $_POST['phone'] ) ) : '';
				$country = ! empty( $_POST['country'] ) ? ohmylms_clean( wp_unslash( $_POST['country'] ) ) : '';
				if ( $phone || $country ) {
					$student = new \OhMyLMS\Data\Student( $user_id );
					if ( $phone ) {
						$student->set_phone( $phone );
					}
					if ( $country ) {
						$student->set_country( $country );
					}
					$student->save();
				}

				$pending_redirect = isset( $_POST['redirect_to'] ) && ! empty( $_POST['redirect_to'] )
					? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) )
					: '';

				if ( \OhMyLMS\Services\EmailVerificationService::is_required() ) {
					\OhMyLMS\Services\EmailVerificationService::generate_and_send( $user_id );
					if ( $pending_redirect ) {
						update_user_meta( $user_id, '_ohmylms_post_verification_redirect', $pending_redirect );
					}
				}

				$creds = array(
					'user_login'    => $email,
					'user_password' => $password,
					'remember'      => true,
				);

				$user = wp_signon( $creds, is_ssl() );

				$redirect_url = $pending_redirect ? $pending_redirect : ohmylms_get_dashboard_url();

				if ( is_wp_error( $user ) ) {
					\OhMyLMS\Commerce\ohmylmse_add_notice( $user->get_error_message(), 'error', array() );
				} else {
					if ( \OhMyLMS\Services\EmailVerificationService::is_required() ) {
						$response = array(
							'status'  => 'pending_verification',
							'message' => __( 'Account created! Please check your inbox and verify your email before accessing your courses.', 'ohmylms' ),
						);
					} else {
						$response = array(
							'status'       => 'success',
							'redirect_url' => $redirect_url,
							'message'      => __( 'Successfully logged in.', 'ohmylms' ),
						);
					}

					wp_send_json( $response );
				}
			} catch ( \Exception $e ) {
				$response = array(
					'status'  => 'error',
					'message' => $e->getMessage(),
				);
				do_action( 'ohmylms_signup_failed' );
				wp_send_json( $response );
			}
		}
	}


	/**
	 * Handle the login action via AJAX.
	 *
	 * @since 1.0.0
	 * @throws \Exception When the operation cannot be completed.
	 */
	public static function login() {
		$nonce_value = isset($_REQUEST['ohmylms-login-nonce']) ? ohmylms_clean( wp_unslash( $_REQUEST['ohmylms-login-nonce'] )) : ''; // @codingStandardsIgnoreLine.
		$valid_nonce = wp_verify_nonce( $nonce_value, 'ohmylms-login' );
		if ( isset( $_POST['username'], $_POST['password'] ) && $valid_nonce ) {
			try {
				$creds = array(
					'user_login'    => trim( wp_unslash( $_POST['username'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'user_password' => ohmylms_clean( wp_unslash( $_POST['password'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
					'remember'      => isset( $_POST['rememberme'] ) ? ohmylms_clean( wp_unslash( $_POST['rememberme'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				);

				$validation_error = new \WP_Error();

				if ( $validation_error->get_error_code() ) {
					\OhMyLMS\Commerce\ohmylmse_add_notice( $validation_error->get_error_message(), 'error', array() );
					\OhMyLMS\Commerce\ohmylmse_print_notices( true );
					throw new \Exception( '<strong>' . __( 'Error:', 'ohmylms' ) . '</strong> ' . $validation_error->get_error_message() );
				}

				if ( empty( $creds['user_login'] ) ) {
					$message = __( 'Username is required.', 'ohmylms' );
					\OhMyLMS\Commerce\ohmylmse_add_notice( $message, 'error', array() );
					\OhMyLMS\Commerce\ohmylmse_print_notices( true );
					throw new \Exception( '<strong>' . __( 'Error:', 'ohmylms' ) . '</strong> ' . __( 'Username is required.', 'ohmylms' ) );
				}

				// On multisite, ensure user exists on current site, if not add them before allowing login.
				if ( is_multisite() ) {
					$user_data = get_user_by( is_email( $creds['user_login'] ) ? 'email' : 'login', $creds['user_login'] );

					if ( $user_data && ! is_user_member_of_blog( $user_data->ID, get_current_blog_id() ) ) {
						add_user_to_blog( get_current_blog_id(), $user_data->ID, function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber' );
					}
				}

				// Peform the login.
				$user = wp_signon( $creds, is_ssl() );

				$redirect_url = isset( $_POST['redirect_to'] ) && ! empty( $_POST['redirect_to'] )
					? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) )
					: ohmylms_get_dashboard_url();

				if ( ! empty( $_POST['isCheckoutLogin'] ) ) {
					$redirect_url = ohmylms_get_checkout_url();
				}

				// If a course purchase was initiated before login, always redirect to checkout.
				if ( ! empty( $_COOKIE['ohmylms_pending_course'] ) ) {
					$redirect_url = ohmylms_get_checkout_url();
				}

				if ( ! $redirect_url || ! is_string( $redirect_url ) ) {
					$redirect_url = ohmylms_get_page_permalink( 'student_profile' );
				}

				if ( is_wp_error( $user ) ) {
					\OhMyLMS\Commerce\ohmylmse_add_notice( $user->get_error_message(), 'error', array() );
					\OhMyLMS\Commerce\ohmylmse_print_notices( true );
					throw new \Exception( $user->get_error_message() );
				} else {
					$response = array(
						'status'       => 'success',
						'message'      => __( 'Successfully logged in.', 'ohmylms' ),
						'redirect_url' => $redirect_url,
					);

					wp_send_json( $response );
				}
			} catch ( \Exception $e ) {
				\OhMyLMS\Commerce\ohmylmse_add_notice( wp_strip_all_tags( $e->getMessage() ), 'error', array() );
				do_action( 'ohmylms_login_failed' );
				$messages = \OhMyLMS\Commerce\ohmylmse_print_notices( true );
				$response = array(
					'status'  => 'error',
					'message' => isset( $messages ) ? $messages : '',
				);
				wp_send_json( $response );
			}
		}
	}


	/**
	 * Handle the load more action via AJAX.
	 *
	 * @since 1.0.0
	 */
	public static function loadmore() {
		check_ajax_referer( 'load_more_nonce', 'nonce' );

		if ( isset( $_POST['page'] ) ) {
			$paged = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

			if ( isset( $_POST['posts_per_page'] ) ) {
				$posts_per_page = absint( $_POST['posts_per_page'] );
			} else {
				$posts_per_page = get_option( 'ohmylms_courses_per_page', 10 );
			}
			// Collect filter parameters if present.
			$args = array(
				'post_type'      => OHMYLMS_COURSE_CPT,
				'paged'          => $paged,
				'posts_per_page' => $posts_per_page,
				'post_status'    => 'publish',
			);
			// The category and tag filters now filter by curriculum item and course bundle.
			$item_ids   = array();
			$bundle_ids = array();

			// Check for filter parameters.
			if ( ! empty( $_POST['filter_type'] ) ) {
				$filter_type = sanitize_text_field( wp_unslash( $_POST['filter_type'] ) );

				if ( isset( $_POST['category_slug'] ) && is_array( $_POST['category_slug'] ) ) {
					$category_slugs = array_map( 'sanitize_text_field', wp_unslash( $_POST['category_slug'] ) );
					$item_ids       = \OhMyLMS\Curriculum\Placement::ids_from_slugs( $category_slugs, 'item' );
				}
				if ( isset( $_POST['price_slug'] ) && is_array( $_POST['price_slug'] ) ) {
					$price_slugs          = array_map( 'sanitize_text_field', wp_unslash( $_POST['price_slug'] ) );
					$args['meta_query'][] = array(
						'key'     => '_price_type',
						'value'   => $price_slugs,
						'compare' => 'IN',
					);
				}

				if ( isset( $_POST['level_slug'] ) && is_array( $_POST['level_slug'] ) ) {
					$level_slugs          = array_map( 'sanitize_text_field', wp_unslash( $_POST['level_slug'] ) );
					$args['meta_query'][] = array(
						'key'     => '_level',
						'value'   => $level_slugs,
						'compare' => 'IN',
					);
				}
				if ( isset( $_POST['tag_slug'] ) && is_array( $_POST['tag_slug'] ) ) {
					$tag_slugs  = array_map( 'sanitize_text_field', wp_unslash( $_POST['tag_slug'] ) );
					$bundle_ids = \OhMyLMS\Curriculum\Placement::ids_from_slugs( $tag_slugs, 'bundle' );
				}
				if ( ! empty( $_POST['search_term'] ) ) {
					$args['s'] = sanitize_text_field( wp_unslash( $_POST['search_term'] ) );
				}
				if ( ! empty( $_POST['sort_by'] ) ) {
					$sort_by = sanitize_text_field( wp_unslash( $_POST['sort_by'] ) );
					if ( 'max_price' === $sort_by ) {
						$args['meta_key'] = '_price';
						$args['orderby']  = 'meta_value_num';
						$args['order']    = 'DESC';
					} elseif ( 'min_price' === $sort_by ) {
						$args['meta_key'] = '_price';
						$args['orderby']  = 'meta_value_num';
						$args['order']    = 'ASC';
					} elseif ( 'rating' === $sort_by ) {
						$args['meta_key'] = '_average_rating';
						$args['orderby']  = 'meta_value_num';
						$args['order']    = 'DESC';
					} elseif ( 'review' === $sort_by ) {
						$args['meta_key'] = '_review_count';
						$args['orderby']  = 'meta_value_num';
						$args['order']    = 'DESC';
					} elseif ( 'date' === $sort_by ) {
						$args['orderby'] = 'date';
						$args['order']   = 'DESC';
					}
				}
			}

			$args  = \OhMyLMS\Curriculum\Placement::narrow_query( $args, $item_ids, $bundle_ids );
			$query = new \WP_Query( $args );

			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					$query->the_post();
					ohmylms_get_template( 'content-course' ); // Load the post template.
				endwhile;
			endif;

			wp_reset_postdata();
		}

		die(); // Always die after handling the request.
	}

	/**
	 * Search filter for Ajax.
	 */
	public static function search_filter() {
		check_ajax_referer( 'search_filter_nonce', 'nonce' );

		if ( ! isset( $_POST['page'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ohmylms' ) ) );
			return;
		}

		$paged       = intval( $_POST['page'] );
		$filter_type = isset( $_POST['filter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_type'] ) ) : '';

		$price_slugs    = array();
		$level_slugs    = array();
		$tag_slugs      = array();
		$category_slugs = array();
		$search_term    = '';
		$sort_by        = '';

		// Collect filter parameters.
		if ( isset( $_POST['category_slug'] ) && is_array( $_POST['category_slug'] ) ) {
			$category_slugs = array_map( 'sanitize_text_field', wp_unslash( $_POST['category_slug'] ) );
		}

		if ( isset( $_POST['price_slug'] ) && is_array( $_POST['price_slug'] ) ) {
			$price_slugs = array_map( 'sanitize_text_field', wp_unslash( $_POST['price_slug'] ) );
		}

		if ( isset( $_POST['level_slug'] ) && is_array( $_POST['level_slug'] ) ) {
			$level_slugs = array_map( 'sanitize_text_field', wp_unslash( $_POST['level_slug'] ) );
		}

		if ( $level_slugs && is_array( $level_slugs ) && in_array( 'all', $level_slugs, true ) ) {
			$level_slugs = array();
		}

		if ( isset( $_POST['tag_slug'] ) && is_array( $_POST['tag_slug'] ) ) {
			$tag_slugs = array_map( 'sanitize_text_field', wp_unslash( $_POST['tag_slug'] ) );
		}

		if ( ! empty( $_POST['search_term'] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_POST['search_term'] ) );
		}

		if ( ! empty( $_POST['sort_by'] ) ) {
			$sort_by = sanitize_text_field( wp_unslash( $_POST['sort_by'] ) );
		}

		// Base query arguments.
		$args = array(
			'post_type'      => OHMYLMS_COURSE_CPT,
			'paged'          => $paged,
			'posts_per_page' => get_option( 'ohmylms_courses_per_page', 10 ),
			'post_status'    => 'publish',
		);

		// Add search term.
		if ( $search_term ) {
			$args['s'] = $search_term;
		}

		// Initialize meta_query and tax_query arrays.
		$meta_query = array();
		$tax_query  = array();

		// Add meta queries for filters.
		if ( ! empty( $price_slugs ) ) {
			$meta_query[] = array(
				'key'     => '_price_type',
				'value'   => $price_slugs,
				'compare' => 'IN',
			);
		}

		if ( ! empty( $level_slugs ) ) {
			$meta_query[] = array(
				'key'     => '_level',
				'value'   => $level_slugs,
				'compare' => 'IN',
			);
		}

		// Category and tag filters are curriculum item and course bundle filters; they are applied
		// below, once the remaining query arguments are built.
		$item_ids   = \OhMyLMS\Curriculum\Placement::ids_from_slugs( $category_slugs, 'item' );
		$bundle_ids = \OhMyLMS\Curriculum\Placement::ids_from_slugs( $tag_slugs, 'bundle' );

		// Set relations for multiple queries.
		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		// Add queries to args if they exist.
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		// Apply sorting logic.
		if ( 'max_price' === $sort_by ) {
			$args['meta_key'] = '_price';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'DESC';
		} elseif ( 'min_price' === $sort_by ) {
			$args['meta_key'] = '_price';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'ASC';
		} elseif ( 'rating' === $sort_by ) {
			$args['meta_key'] = '_average_rating';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'DESC';
		} elseif ( 'review' === $sort_by ) {
			$args['meta_key'] = '_review_count';
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'DESC';
		} elseif ( 'date' === $sort_by ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		$args = \OhMyLMS\Curriculum\Placement::narrow_query( $args, $item_ids, $bundle_ids );

		// Handle different filter types.
		if ( 'category' === $filter_type ) {
			$GLOBALS['category'] = isset( $category_slugs[0] ) ? $category_slugs[0] : null;

			$query         = new \WP_Query( $args );
			$courses_count = $query->found_posts;

			if ( $query->have_posts() ) {
				ohmylms_get_template_part( 'content', 'course-carousel' );
			}
			wp_reset_postdata();
		} else {
			// Normal filtering.
			$query                    = new \WP_Query( $args );
			$courses_count            = $query->found_posts;
			$GLOBALS['courses_count'] = $courses_count;

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) :
					$query->the_post();
					ohmylms_get_template( 'content-course' );
				endwhile;
			} else {
				// Show empty state.
				echo '<div class="no-courses-found">';
				echo '</div>';
			}
			wp_reset_postdata();
		}

		die();
	}


	/**
	 * Filter for Ajax.
	 *
	 * @param mixed $slug Slug.
	 */
	private function filter( $slug ) {
	}



	/**
	 * Upload an image via AJAX.
	 *
	 * @param string $nonce The nonce value for security.
	 * @param string $nonce_action The action name for nonce verification.
	 * @param string $image_type The type of image being uploaded (profile or cover).
	 * .
	 * @since 1.0.0
	 */
	private static function upload_image( $nonce, $nonce_action, $image_type ) {
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
			return;
		}

		// Check if a file is uploaded.
		if ( isset( $_FILES['file']['error'] ) && UPLOAD_ERR_OK === absint( $_FILES['file']['error'] ) ) {
			// Process the file upload.
			$uploaded_file = wp_handle_upload( $_FILES['file'], array( 'test_form' => false ) );

			if ( isset( $uploaded_file['url'] ) ) {
				$student_id = get_current_user_id();
				$student    = new Student( $student_id );

				// Set the correct image type.
				if ( 'profile' === $image_type ) {
					$student->set_profile_image( $uploaded_file['url'] );
				} elseif ( 'cover' === $image_type ) {
					$student->set_cover_image( $uploaded_file['url'] );
				}

				$student->save();
				wp_send_json_success( array( 'url' => $uploaded_file['url'] ) );
			} else {
				wp_send_json_error( array( 'message' => 'Upload failed' ) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'No file uploaded' ) );
		}
	}

	/**
	 * Handle the student cover image upload via AJAX.
	 *
	 * @since 1.0.0
	 */
	public static function student_profile_image_upload() {
		check_ajax_referer( 'student_profile_image_upload', 'nonce' );
		self::upload_image( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'student_profile_image_upload', 'profile' );
	}

	/**
	 * Handle the student cover image upload via AJAX.
	 *
	 * @since 1.0.0
	 */
	public static function student_cover_image_upload() {
		check_ajax_referer( 'student_profile_cover_image', 'nonce' );
		self::upload_image( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'student_profile_cover_image', 'cover' );
	}


	/**
	 * Handle the student cover image delete via AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function student_cover_image_delete() {
		if ( ! check_ajax_referer( 'student_profile_cover_image_delete', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
			return;
		}

		$student_id = get_current_user_id();
		$student    = new Student( $student_id );
		$student->set_cover_image( '' );
		$student->save();
		wp_send_json_success( array( 'url' => $student->get_cover_image() ) );
		wp_send_json_success();
	}

	/**
	 * Apply a coupon to the cart via AJAX.
	 *
	 * @since 1.0.0
	 */
	public static function apply_coupon() {
		// Prevent caching of cart operations.
		nocache_headers();

		check_ajax_referer( 'apply-coupon', 'security' );
		$coupon_code   = ohmylms_clean( wp_unslash( $_POST['coupon_code'] ?? '' ) );
		$cart          = ecommerce()->cart;
		$maybe_applied = $cart->apply_coupon( ohmylms_ecommerce_format_coupon_code( $coupon_code ) );
		if ( ! $maybe_applied ) {
			wp_send_json_error(
				array(
					'status'  => 'error',
					'message' => ohmylmse_print_notices( true ),
				)
			);
		}

		ob_start();
		$checkout = new Checkout();
		ohmylms_get_template(
			'checkout/review-order.php',
			array(
				'checkout' => $checkout,
			)
		);
		$html = ob_get_clean();
		wp_send_json_success(
			array(
				'fragments' => array(
					'.ohmylms-checkout-review-order' => $html,
				),
			)
		);
		die();
	}

	/**
	 * Remove a coupon from the cart via AJAX.
	 *
	 * @since 1.0.0
	 */
	public static function remove_coupon() {
		// Prevent caching of cart operations.
		nocache_headers();

		check_ajax_referer( 'remove-coupon', 'security' );
		$coupon_code = ohmylms_clean( wp_unslash( $_POST['coupon_code'] ?? '' ) );
		$cart        = ecommerce()->cart;
		$cart->remove_coupon( ohmylms_ecommerce_format_coupon_code( $coupon_code ) );
		ob_start();
		$checkout = new Checkout();
		ohmylms_get_template(
			'checkout/review-order.php',
			array(
				'checkout' => $checkout,
			)
		);
		$html = ob_get_clean();
		wp_send_json_success(
			array(
				'fragments' => array(
					'.ohmylms-checkout-review-order' => $html,
				),
			)
		);
		die();
	}

	/**
	 * Handle the lesson completed action via AJAX.
	 *
	 * @since 1.0.0
	 */
	public static function lesson_completed() {
		check_ajax_referer( 'lesson_completed_nonce', 'nonce' );
		$lesson_id             = ohmylms_clean( wp_unslash( $_POST['lesson_id'] ?? '' ) );
		$course_id             = ohmylms_get_course_by_content_id( $lesson_id );
		$student_id            = get_current_user_id();
		$student               = new Student( $student_id );
		if ( ! \OhMyLMS\Learning\VideoCompletion::satisfied( $student_id, (int) $lesson_id ) ) {
			wp_send_json_error(
				array(
					'status'  => 'error',
					'code'    => 'ohmylms_lesson_video_not_watched',
					/* translators: %d: percentage. */
					'message' => sprintf( __( 'Watch at least %d%% of the video to complete this lesson.', 'ohmylms' ), \OhMyLMS\Learning\VideoCompletion::rule( (int) $lesson_id )['percent'] ),
				)
			);
		}
		$progress_content_id   = $student->complete_lesson( $lesson_id, $course_id );
		$get_next_content_link = ohmylms_get_next_content_permalink( $lesson_id );
		if ( $progress_content_id ) {
			/**
			 * Lesson completed action.
			 * Fires after a lesson is completed.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_after_lesson_completed', $lesson_id, $course_id, $student_id );

			$completion_rate = $student->get_over_all_completion_rate( $course_id );
			if ( (int) ( $completion_rate ) === 100 && ! \OhMyLMS\Learning\CourseProgram::managed( $student_id, $course_id ) ) {
				global $wpdb;
				$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
				$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d', $table_name, $student_id, $course_id ), ARRAY_A );
				if ( isset( $enroll_data['order_id'] ) ) {
					// Get current date with WordPress timezone.
					$current_date = current_time( 'mysql' ); // Format: Y-m-d H:i:s
					// Update end_date in the table.
					\OhMyLMS\Student\Enrollments::update(
						array( 'end_date' => $current_date ),
						array(
							'user_id'   => $student_id,
							'course_id' => $course_id,
						)
					);

					do_action( 'ohmylms_course_completed', $student_id, $course_id, $enroll_data['order_id'] );
				}
			}
			do_action( 'ohmylms_course_completion_rate', $student_id, $course_id, intval( $completion_rate ) );

			do_action( 'ohmylms_lesson_completed', $lesson_id, $course_id, $student_id );

			wp_send_json_success(
				array(
					'status'            => 'success',
					'next_content_link' => $get_next_content_link,
					'message'           => __( 'Lesson completed successfully.', 'ohmylms' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'status'  => 'error',
					'message' => __( 'Lesson completion failed.', 'ohmylms' ),
				)
			);
		}
	}


	/**
	 * Handle the lesson completed action via AJAX.
	 *
	 * @since 1.0.0
	 */
	public static function drop_course() {
		check_ajax_referer( 'course_drop_nonce', 'nonce' );
		$course_id  = ohmylms_clean( wp_unslash( $_POST['course_id'] ?? '' ) );
		$student_id = get_current_user_id();

		$enrollment_data = array(
			'status'     => 'dropped',
			'progress'   => 'stop',
			'start_date' => current_time( 'mysql' ),
		);

		$where = array(
			'course_id' => $course_id,
			'user_id'   => $student_id,
		);

		$student = new \OhMyLMS\Data\Student( $student_id );
		if ( $student && $student->maybe_enrolled( $course_id ) ) {
			$updated = \OhMyLMS\Student\Enrollments::update( $enrollment_data, $where );

			if ( false !== $updated ) {
				wp_send_json_success(
					array(
						'status'  => 'success',
						'message' => __( 'Course dropped successfully.', 'ohmylms' ),
					)
				);
			} else {
				wp_send_json_error(
					array(
						'status'  => 'error',
						'message' => __( 'Failed to drop the course.', 'ohmylms' ),
					)
				);
			}
		}

		wp_send_json_error(
			array(
				'status'  => 'error',
				'message' => __( 'Course drop failed.', 'ohmylms' ),
			)
		);
	}


	/**
	 * Save assignment submission file for Ajax.
	 */
	public static function save_assignment_submission_file() {
		if ( ! ohmylms_is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'This feature is only available in OhMyLMS.', 'ohmylms' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Verify nonce.
		$nonce_value = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce_value, 'assignment_submission_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request. Please refresh the page and try again.', 'ohmylms' ) ) );
		}

		// Collect and sanitize input.
		$assignment_id = ! empty( $_POST['assignment_id'] ) ? ohmylms_clean( wp_unslash( $_POST['assignment_id'] ) ) : '';
		$course_id     = ! empty( $_POST['course_id'] ) ? ohmylms_clean( wp_unslash( $_POST['course_id'] ?? '' ) ) : '';
		$content       = ! empty( $_POST['submission-body'] ) ? ohmylms_clean( sanitize_text_field( wp_unslash( $_POST['submission-body'] ) ) ) : '';

		// Get student.
		$student = new Student( get_current_user_id() );

		if ( ! $student->get_id() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to submit the assignment.', 'ohmylms' ) ) );
		}

		// Get assignment.
		$assignment = new \OhMyLMS\Data\Assignment( $assignment_id );

		if ( ! $assignment->get_id() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid assignment.', 'ohmylms' ) ) );
		}

		// Enrolment, the drip release and the prerequisites are checked here, before any upload is stored.
		$allowed = \OhMyLMS\Assignment\Delivery::check_submission( $assignment->get_id(), $student->get_id() );
		if ( is_wp_error( $allowed ) ) {
			wp_send_json_error(
				array(
					'message' => $allowed->get_error_message(),
					'code'    => $allowed->get_error_code(),
				)
			);
		}
		$resolved_course = (int) ohmylms_get_course_by_content_id( $assignment->get_id(), $student->get_id() );
		$course_id       = $resolved_course ? $resolved_course : absint( $course_id );

		if ( ! $assignment->get_allow_upload_files() ) {
			wp_send_json_error( array( 'message' => __( 'File uploads are not allowed for this assignment.', 'ohmylms' ) ) );
		}

		if ( ! isset( $_FILES['ohmylms-submission-file']['error'] ) || UPLOAD_ERR_NO_FILE === absint( $_FILES['ohmylms-submission-file']['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'ohmylms' ) ) );
		}

		$allowed_mimes = apply_filters(
			'ohmylms_assignment_allowed_mimes',
			array(
				'pdf'          => 'application/pdf',
				'doc'          => 'application/msword',
				'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'txt'          => 'text/plain',
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
				'zip'          => 'application/zip',
				'ppt'          => 'application/vnd.ms-powerpoint',
				'pptx'         => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'xls'          => 'application/vnd.ms-excel',
				'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'odt'          => 'application/vnd.oasis.opendocument.text',
				'csv'          => 'text/csv',
				'mp3|m4a|m4b'  => 'audio/mpeg',
				'mp4|m4v'      => 'video/mp4',
			)
		);

		$uploaded_file = wp_handle_upload(
			$_FILES['ohmylms-submission-file'],
			array(
				'test_form' => false,
				'mimes'     => $allowed_mimes,
			)
		);

		if ( ! $uploaded_file || isset( $uploaded_file['error'] ) ) {
			$error_message = isset( $uploaded_file['error'] ) ? $uploaded_file['error'] : __( 'File upload failed.', 'ohmylms' );
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		$file_size_bytes = filesize( $uploaded_file['file'] );
		$file_size_mb    = $file_size_bytes / ( 1024 * 1024 );

		if ( $assignment->get_enable_file_size_limit() && $assignment->get_max_file_size_limit() < $file_size_mb ) {
			wp_send_json_error( array( 'message' => __( 'File size is too large.', 'ohmylms' ) ) );
		}

		if ( $assignment->get_number_of_files() <= count( $assignment->get_submission( $student->get_id() ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You have reached the maximum number of files allowed.', 'ohmylms' ) ) );
		}

		$submit_data = array(
			'content' => $content,
			'files'   => $uploaded_file,
		);

		$assignment->submit_file_submission( $student->get_id(), $course_id, $submit_data );

		/**
		 * Assignment submitted action.
		 * Fires after an assignment is submitted.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_assignment_submitted', $assignment->get_id(), $course_id, $student->get_id() );

		wp_send_json_success(
			array(
				'message'      => __( 'Assignment submitted successfully.', 'ohmylms' ),
				'redirect_url' => get_permalink( $assignment->get_id() ),
			)
		);
	}


	/**
	 * Add a membership plan to the cart. Callers verify their own nonce.
	 *
	 * @param int $membership_id Membership.
	 * @param int $quantity      Quantity.
	 * @return array{status:int, body:array}
	 */
	public static function add_membership_to_cart( $membership_id, $quantity = 1 ): array {
		if ( ! ohmylms_is_pro() ) {
			return self::cart_answer(
				array(
					'status'  => 'error',
					'message' => __( 'Sorry! This membership is only available in OhMyLMS.', 'ohmylms' ),
				)
			);
		}

		$membership_status = get_post_status( $membership_id );
		if ( \OhMyLMS\Membership\FreeSale::active( ohmylms_get_membership( $membership_id ) ) ) {
			return self::cart_answer(
				array(
					'status'       => 'success',
					'redirect_url' => \OhMyLMS\Student\Hub::url( 'membership', 'plans' ),
				)
			);
		}

		$membership = ohmylms_get_membership( $membership_id );
		if ( ! $membership || ! $membership->is_purchasable() ) {
			return self::cart_answer(
				array(
					'status'  => 'error',
					'message' => __( 'Sorry! This membership is not purchasable.', 'ohmylms' ),
				)
			);
		}

		if ( false !== ecommerce()->cart->add_to_cart( $membership_id, $quantity ) && 'publish' === $membership_status ) {
			do_action( 'ohmylms_ajax_added_to_cart', $membership_id );

			// Add cache-busting parameter to redirect URL.
			$checkout_url = get_permalink( ohmylms_get_page_id( 'checkout' ) );

			return self::cart_answer(
				array(
					'status'       => 'success',
					'redirect_url' => add_query_arg( 'nocache', time(), $checkout_url ),
					'message'      => __( 'Successfully added to cart.', 'ohmylms' ),
				)
			);
		}

		return self::cart_answer(
			array(
				'status'  => 'error',
				'message' => __( 'Add to cart failed.', 'ohmylms' ),
			)
		);
	}


	/**
	 * Save remaining time for Ajax.
	 */
	public static function save_remaining_time() {
		if ( ! ohmylms_is_pro() ) {
			$response = array(
				'status'  => 'error',
				'message' => __( 'Sorry! Timer is only available in OhMyLMS.', 'ohmylms' ),
			);
			wp_send_json( $response );
		}

		check_ajax_referer( 'save_remaining_time', 'nonce' );

		$content_id = ohmylms_clean( wp_unslash( $_POST['content_id'] ?? '' ) );
		$student_id = get_current_user_id();
		$assignment = ohmylms_get_assignment( $content_id );
		if ( ! $assignment ) {
			return;
		}
		$is_enabled_time_limit = $assignment->get_enable_time_limit();
		if ( ! $is_enabled_time_limit ) {
			return;
		}

		$time_limit        = $assignment->get_time_limit();
		$time_limit_type   = strtolower( $assignment->get_time_limit_type() );
		$key               = '_ohmylms_deadline_' . $student_id . '_' . $content_id . '_';
		$existing_deadline = get_option( $key );

		if ( ! $existing_deadline ) {
			// No deadline is set, so calculate it from the current time.
			$current_datetime = new \DateTime( 'now', new \DateTimeZone( wp_timezone_string() ) ); // Use WordPress timezone.
			$interval_spec    = "P{$time_limit}" . strtoupper( substr( $time_limit_type, 0, 1 ) ); // Example: P2D (2 days), P2M (2 months), P2W (2 weeks).

			try {
				$current_datetime->add( new \DateInterval( $interval_spec ) );
				$new_deadline = $current_datetime->format( 'Y-m-d H:i:s' ); // Format for storage.
				update_option( "_ohmylms_deadline_{$student_id}_{$content_id}_", $new_deadline );
			} catch ( \Exception $e ) {
				wp_send_json_error( array( 'message' => __( 'Error calculating deadline.', 'ohmylms' ) ) );
			}
		}
		wp_send_json_success( array( 'message' => __( 'Deadline saved successfully.', 'ohmylms' ) ) );
	}


	/**
	 * Quiz exit submission for Ajax.
	 */
	public static function quiz_exit_submission() {
		check_ajax_referer( 'quiz_exit_submission', 'nonce' );
		$quiz_id    = absint( $_POST['content_id'] ?? 0 );
		$attempt_id = absint( $_POST['attempt_id'] ?? 0 );
		$answers    = wp_unslash( $_POST['attempt'][ $attempt_id ]['quiz_question'] ?? array() );
		if ( ! is_array( $answers ) ) {
			wp_send_json_error( array( 'message' => 'Invalid answers' ), 400 );
		}
		$result = \OhMyLMS\Quiz\Submission::submit( $quiz_id, $attempt_id, get_current_user_id(), $answers, 'timeout' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'url' => get_permalink( $quiz_id ) ) );
	}

	/**
	 * Cancel membership for Ajax.
	 */
	public static function cancel_membership() {
		check_ajax_referer( 'cancel_membership_nonce', 'nonce' );

		if ( ! ohmylms_is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'This feature is only available in OhMyLMS.', 'ohmylms' ) ) );
		}

		$current_user_id = get_current_user_id();
		$subscription_id = absint( $_POST['subscription_id'] ?? 0 );
		$subscription    = $current_user_id && $subscription_id ? ohmylms_ecommerce_get_subscription( $subscription_id ) : null;
		// The student, membership and order come from the subscription record, never from the request.
		$student_id    = $subscription ? absint( $subscription->get_student_id() ) : 0;
		$membership_id = $subscription ? absint( $subscription->get_membership_id() ) : 0;
		$order_id      = $subscription ? absint( $subscription->get_original_order_id() ) : 0;
		$posted_order  = absint( $_POST['order_id'] ?? 0 );
		if ( $posted_order && $subscription_id === absint( get_post_meta( $posted_order, '_subscription_id', true ) ) ) {
			$order_id = $posted_order;
		}
		if ( ! $student_id || ( $student_id !== $current_user_id && ! current_user_can( 'manage_options' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot cancel this membership.', 'ohmylms' ) ), 403 );
		}
		$membership = ohmylms_get_membership( $membership_id );
		if ( ! $membership ) {
			wp_send_json_error( array( 'message' => __( 'Membership not found.', 'ohmylms' ) ), 404 );
		}
		$membership->cancel_enrollment( $student_id, $order_id );
		$subscription->set_status( 'cancelled' );
		$subscription->set_schedule_next_payment_date( '' );
		$subscription->set_schedule_end_date( '' );
		$subscription->save();
		$user_id = $current_user_id;
		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user && $user->exists() ) {
				\OhMyLMS\Commerce\SubscriptionManager::add_subscription_note(
					$subscription_id,
					sprintf( /* translators: %s: text value. */
						__( 'Subscription cancelled by %s.', 'ohmylms' ),
						$user->display_name
					)
				);
			} else {
				\OhMyLMS\Commerce\SubscriptionManager::add_subscription_note( $subscription_id, __( 'Subscription status changed to Cancelled.', 'ohmylms' ) );
			}
		} else {
			\OhMyLMS\Commerce\SubscriptionManager::add_subscription_note( $subscription_id, __( 'Subscription status changed to Cancelled.', 'ohmylms' ) );
		}
		wp_send_json_success( array( 'message' => __( 'Cancel membership successfully.', 'ohmylms' ) ) );
	}


	/**
	 * Check lesson access via AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function lesson_access_nonce() {
		check_ajax_referer( 'lesson_access_nonce', 'nonce' );
		$lesson_id = absint( $_GET['lesson_id'] ?? 0 );
		$student   = new Student( get_current_user_id() );
		$post      = get_post( $lesson_id );

		// Assignment prerequisites gate submitting, not reading (OhMyLMS\Assignment\Delivery).
		$prerequisites = null;
		if ( $post && 'ohmylms-lesson' === $post->post_type ) {
			$lesson_obj    = ohmylms_get_lesson( $post->ID );
			$prerequisites = method_exists( $lesson_obj, 'get_prerequisites' ) ? $lesson_obj->get_prerequisites() : '';
		}

		// Check prerequisites settings.
		if ( is_array( $prerequisites ) && ! empty( $prerequisites ) && ! empty( $prerequisites['enable'] ) && ! empty( $prerequisites['data'] ) && $prerequisites['enable'] ) {
			$prerequisites_met      = true;
			$incomplete_course_name = '';
			foreach ( $prerequisites['data'] as $prerequisite ) {
				if ( ! $student->maybe_completed( $prerequisite['value'] ) ) {
					$prerequisites_met      = false;
					$incomplete_course_name = $prerequisite['label'];
					break;
				}
			}

			if ( ! $prerequisites_met ) {
				wp_send_json_success(
					array(
						'permission'             => false,
						'incomplete_course_name' => $incomplete_course_name,
						'message'                => __( 'You can access this lesson.', 'ohmylms' ),
					)
				);
			}
		}

		// Sequential mode gate for the React lesson player.
		$seq_course_id = ohmylms_get_course_id_by_content_id( $lesson_id );
		if ( $seq_course_id && apply_filters( 'ohmylms_is_lesson_sequentially_locked', false, $lesson_id, $seq_course_id, get_current_user_id() ) ) {
			wp_send_json_success(
				array(
					'permission' => false,
					'message'    => __( 'You must complete the previous lesson before accessing this content.', 'ohmylms' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'permission' => true,
				'message'    => __( 'You can access this lesson.', 'ohmylms' ),
			)
		);
	}

	/**
	 * Calculate tax for Ajax.
	 */
	public static function calculate_tax() {
		// Prevent caching of cart operations.
		nocache_headers();

		check_ajax_referer( 'ohmylms_calculate_tax', 'nonce' );

		if ( ! TaxService::get_instance()->is_tax_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Tax calculation is not enabled.', 'ohmylms' ) ) );
		}

		$country    = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$state      = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$vat_number = isset( $_POST['vat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['vat_number'] ) ) : '';
		$cart       = ecommerce()->cart;
		$tax_rate   = $cart->get_country_tax_rate( $country, $state, $vat_number );
		ob_start();
		$checkout = new Checkout();
		ohmylms_get_template(
			'checkout/review-order.php',
			array(
				'checkout' => $checkout,
			)
		);
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'fragments' => array(
					'.ohmylms-checkout-review-order' => $html,
				),
			)
		);
		die();
	}

	/**
	 * Save video progress via AJAX
	 *
	 * @since 1.1.0
	 */
	public static function save_video_progress() {
		check_ajax_referer( 'video_progress_nonce', 'nonce' );

		// Get current user.
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'User not logged in.', 'ohmylms' ) ) );
		}

		// Get and sanitize input.
		$lesson_id        = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ?? 0 ) : 0;
		$watched_duration = isset( $_POST['watched_duration'] ) ? floatval( $_POST['watched_duration'] ) : 0;
		$total_duration   = isset( $_POST['total_duration'] ) ? floatval( $_POST['total_duration'] ) : 0;
		$last_position    = isset( $_POST['last_position'] ) ? floatval( $_POST['last_position'] ) : 0;

		if ( ! $lesson_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing lesson ID.', 'ohmylms' ) ) );
		}

		// SECURITY: Derive course_id from lesson_id server-side to prevent manipulation
		// This prevents users from pairing arbitrary lesson IDs with courses they're enrolled in.
		$course_id = ohmylms_get_course_id_by_content_id( $lesson_id );
		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid lesson or lesson not associated with any course.', 'ohmylms' ) ) );
		}

		// Validate duration values to prevent manipulation.
		if ( 0 > $watched_duration || 0 > $total_duration || 0 > $last_position ) {
			wp_send_json_error( array( 'message' => __( 'Invalid duration values.', 'ohmylms' ) ) );
		}

		// Prevent watched_duration from exceeding total_duration by more than a small margin
		// Allow small tolerance (5%) for timing variations.
		if ( $watched_duration > ( $total_duration * 1.05 ) ) {
			$watched_duration = $total_duration;
		}

		// Initialize video progress tracker.
		$tracker = new \OhMyLMS\VideoProgress\VideoProgressTracker();

		// Check if user is enrolled in the course that actually contains this lesson.
		if ( ! $tracker->is_user_enrolled( $user_id, $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'User not enrolled in this course.', 'ohmylms' ) ) );
		}

		// Save progress.
		$result = $tracker->save_progress( $user_id, $lesson_id, $course_id, $watched_duration, $total_duration, $last_position );

		if ( $result ) {
			$progress = $tracker->get_progress( $user_id, $lesson_id );

			// Auto-complete lesson if threshold reached.
			if ( $progress && $progress->is_completed ) {
				$tracker->mark_lesson_complete( $user_id, $lesson_id, $course_id );
			}

			wp_send_json_success(
				array(
					'message'  => __( 'Progress saved successfully.', 'ohmylms' ),
					'progress' => array(
						'watched_duration' => $progress->watched_duration,
						'watch_percentage' => $progress->watch_percentage,
						'is_completed'     => (bool) $progress->is_completed,
					),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save progress.', 'ohmylms' ) ) );
		}
	}

	/**
	 * Get video progress via AJAX
	 *
	 * @since 1.1.0
	 */
	public static function get_video_progress() {
		check_ajax_referer( 'video_progress_nonce', 'nonce' );

		// Get current user.
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'User not logged in.', 'ohmylms' ) ) );
		}

		// Get and sanitize input.
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ?? 0 ) : 0;

		// Validate required fields.
		if ( ! $lesson_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing lesson ID.', 'ohmylms' ) ) );
		}

		// Initialize video progress tracker.
		$tracker = new \OhMyLMS\VideoProgress\VideoProgressTracker();

		// Get progress.
		$progress = $tracker->get_progress( $user_id, $lesson_id );

		if ( $progress ) {
			wp_send_json_success(
				array(
					'progress' => array(
						'watched_duration' => floatval( $progress->watched_duration ),
						'total_duration'   => floatval( $progress->total_duration ),
						'watch_percentage' => floatval( $progress->watch_percentage ),
						'last_position'    => floatval( $progress->last_position ),
						'is_completed'     => (bool) $progress->is_completed,
					),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'progress' => array(
						'watched_duration' => 0,
						'total_duration'   => 0,
						'watch_percentage' => 0,
						'last_position'    => 0,
						'is_completed'     => false,
					),
				)
			);
		}
	}

	/**
	 * Clear video progress via AJAX
	 *
	 * @since 1.2.0
	 */
	public static function clear_video_progress() {
		check_ajax_referer( 'video_progress_nonce', 'nonce' );

		// Get current user.
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'User not logged in.', 'ohmylms' ) ) );
		}

		// Get and sanitize input.
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ?? 0 ) : 0;

		// Validate required fields.
		if ( ! $lesson_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing lesson ID.', 'ohmylms' ) ) );
		}

		// Initialize video progress tracker.
		$tracker = new \OhMyLMS\VideoProgress\VideoProgressTracker();

		// Clear progress.
		$result = $tracker->reset_progress( $user_id, $lesson_id );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Video progress cleared successfully.', 'ohmylms' ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message' => __( 'No progress to clear.', 'ohmylms' ),
				)
			);
		}
	}

	/**
	 * Handle resend-verification-email request.
	 * Accessible to both logged-in users (resend for self) and guests (resend by email).
	 */
	public static function resend_verification(): void {
		$redirect_base = ohmylms_get_dashboard_url() ?: home_url( '/' );
		$is_json       = isset( $_REQUEST['format'] ) && 'json' === $_REQUEST['format'];

		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ohmylms_resend_verification' ) ) {
			if ( $is_json ) {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => __( 'Invalid request. Please try again.', 'ohmylms' ),
					)
				);
			}
			wp_safe_redirect( add_query_arg( 'ohmylms_verify_error', 'invalid', $redirect_base ) );
			exit;
		}

		if ( ! \OhMyLMS\Services\EmailVerificationService::is_required() ) {
			if ( $is_json ) {
				wp_send_json(
					array(
						'status'  => 'success',
						'message' => __( 'Email verification is not required.', 'ohmylms' ),
					)
				);
			}
			wp_safe_redirect( $redirect_base );
			exit;
		}

		$user_id        = get_current_user_id();
		$from_email_arg = false;

		if ( ! $user_id && ! empty( $_REQUEST['email'] ) ) {
			$email = sanitize_email( wp_unslash( $_REQUEST['email'] ) );
			$user  = get_user_by( 'email', $email );
			if ( $user ) {
				$user_id = $user->ID;
			}
			$from_email_arg = true;
		}

		// When the user was looked up by email (unauthenticated request), always return the
		// same neutral response regardless of whether the account exists — prevents enumeration.
		if ( $from_email_arg ) {
			if ( $user_id && ! \OhMyLMS\Services\EmailVerificationService::is_verified( $user_id ) ) {
				\OhMyLMS\Services\EmailVerificationService::generate_and_send( $user_id );
			}
			$neutral = __( 'If an account exists for that email address, a verification link has been sent.', 'ohmylms' );
			if ( $is_json ) {
				wp_send_json(
					array(
						'status'  => 'success',
						'message' => $neutral,
					)
				);
			}
			wp_safe_redirect( add_query_arg( 'ohmylms_verify_sent', '1', $redirect_base ) );
			exit;
		}

		if ( ! $user_id ) {
			if ( $is_json ) {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => __( 'Invalid request. Please log in and try again.', 'ohmylms' ),
					)
				);
			}
			wp_safe_redirect( add_query_arg( 'ohmylms_verify_error', 'invalid', $redirect_base ) );
			exit;
		}

		if ( \OhMyLMS\Services\EmailVerificationService::is_verified( $user_id ) ) {
			if ( $is_json ) {
				wp_send_json(
					array(
						'status'  => 'success',
						'message' => __( 'Your email is already verified.', 'ohmylms' ),
					)
				);
			}
			wp_safe_redirect( add_query_arg( 'ohmylms_email_verified', '1', $redirect_base ) );
			exit;
		}

		\OhMyLMS\Services\EmailVerificationService::generate_and_send( $user_id );

		if ( $is_json ) {
			wp_send_json(
				array(
					'status'  => 'success',
					'message' => __( 'Verification email sent. Check your inbox!', 'ohmylms' ),
				)
			);
		}
		wp_safe_redirect( add_query_arg( 'ohmylms_verify_sent', '1', $redirect_base ) );
		exit;
	}
}

Ajax::init();
