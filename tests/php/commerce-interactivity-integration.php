<?php
/**
 * Learner commerce on the Interactivity API (`ohmylms/cart`, `ohmylms/checkout`, `ohmylms/stripe`).
 *
 * Checks the directives and state the buy buttons and checkout templates render, then drives the
 * endpoints those modules call: `POST ohmylms-commerce/v1/cart/items` (and its AJAX twin), the
 * states route, the coupon/tax AJAX fragments and `ohmylms_checkout` with the offline gateway in
 * test mode (no money moves). Options are restored and tagged posts, users and orders removed in
 * `finally`. Mail is short-circuited.
 *
 * Usage (set OHMYLMS_COMMERCE_UI_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/commerce-interactivity-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_COMMERCE_UI_TEST' ) ) {
	exit( "Set OHMYLMS_COMMERCE_UI_TEST=1 to run the commerce Interactivity tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb, $course;

$checks   = 0;
$tag      = 'citest' . strtolower( wp_generate_password( 6, false, false ) );
$posts    = array();
$users    = array();
$exit     = 0;
$options  = array( 'ohmylms_allow_purchase_without_login', 'ohmylms_offline_settings', 'ohmylms_checkout_page_id' );
$snapshot = array();
foreach ( $options as $option ) {
	$snapshot[ $option ] = get_option( $option, null );
}
add_filter( 'pre_wp_mail', '__return_true' );

/**
 * Assert.
 *
 * @param mixed  $value   Truthy value.
 * @param string $message Failure message.
 * @throws RuntimeException On failure.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Render a template.
 *
 * @param string $name Template.
 * @param array  $args Arguments.
 * @return string
 */
function ci_template( $name, $args = array() ) {
	ob_start();
	ohmylms_get_template( $name, $args );
	return (string) ob_get_clean();
}

/**
 * Attributes of the first tag with a class.
 *
 * @param string $html       HTML.
 * @param string $class_name Class.
 * @return array<string,string>|null
 */
function ci_tag( $html, $class_name ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		if ( $tags->has_class( $class_name ) ) {
			$attributes = array();
			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$attributes[ $name ] = (string) $tags->get_attribute( $name );
			}
			return $attributes;
		}
	}
	return null;
}

/**
 * REST call.
 *
 * @param string $method Method.
 * @param string $route  Route below ohmylms-commerce/v1.
 * @param array  $body   Body.
 * @return WP_REST_Response
 */
function ci_rest( $method, $route, $body = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms-commerce/v1' . $route );
	if ( $body ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	return rest_do_request( $request );
}

/**
 * Run an AJAX callback with posted fields and return its JSON answer.
 *
 * An Error (not an Exception) stands in for exit, so the checkout's own catch blocks cannot swallow it.
 *
 * @param callable $callback Callback.
 * @param array    $post     Posted fields.
 * @return array
 * @throws Error When the callback fails for another reason.
 */
function ci_ajax( $callback, array $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	add_filter( 'wp_doing_ajax', '__return_true' );
	$die = static function () {
		return static function () {
			throw new Error( 'ci_ajax_answered' );
		};
	};
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		call_user_func( $callback );
	} catch ( Error $answered ) {
		if ( 'ci_ajax_answered' !== $answered->getMessage() ) {
			ob_end_clean();
			throw $answered;
		}
	}
	$output = (string) ob_get_clean();
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST    = array();
	$_REQUEST = array();
	\OhMyLMS\Commerce\ohmylmse_clear_notices();
	$json = json_decode( $output, true );
	return is_array( $json ) ? $json : array( 'raw' => $output );
}

/**
 * Course ids in the cart.
 *
 * @return array<int, array>
 */
function ci_cart() {
	$items = array();
	foreach ( (array) \OhMyLMS\Commerce\ecommerce()->cart->get_cart_contents() as $item ) {
		$items[ (int) ( $item['course_id'] ?? 0 ) ] = $item;
	}
	return $items;
}

/**
 * Orders of a student.
 *
 * @param int $user_id Student.
 * @return int[]
 */
function ci_orders( $user_id ) {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_student_id' AND meta_value = %s", (string) $user_id ) ) );
}

/**
 * Script module ids queued so far.
 *
 * @return string[]
 */
function ci_modules() {
	$modules = wp_script_modules();
	foreach ( array( 'queue', 'enqueued' ) as $property ) {
		if ( property_exists( $modules, $property ) ) {
			$reflection = new ReflectionProperty( $modules, $property );
			$reflection->setAccessible( true );
			return array_values( array_map( 'strval', (array) $reflection->getValue( $modules ) ) );
		}
	}
	return array();
}

try {
	$users['buyer'] = wp_insert_user(
		array(
			'user_login' => $tag . '_buyer',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '_buyer@example.invalid',
			'role'       => 'subscriber',
		)
	);
	$course_id      = (int) wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'publish',
			'post_title'  => $tag . ' course',
		)
	);
	$posts[]        = $course_id;
	update_post_meta( $course_id, '_price_type', 'paid' );
	update_post_meta( $course_id, '_regular_price', '10' );
	update_post_meta( $course_id, '_price', '10' );
	update_option(
		'ohmylms_offline_settings',
		array_merge(
			(array) $snapshot['ohmylms_offline_settings'],
			array(
				'enabled'   => 'yes',
				'test_mode' => 'yes',
			)
		)
	);
	\OhMyLMS\Commerce\ecommerce()->gateways()->init();
	if ( ! ohmylms_get_checkout_url() ) {
		// The configured checkout page may be missing on a scratch site; use any checkout page for the run.
		$page = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[ohmylms_checkout]%' ORDER BY ID LIMIT 1" );
		ok( $page > 0, 'the local site has a checkout page' );
		update_option( 'ohmylms_checkout_page_id', $page );
	}
	$checkout_url = ohmylms_get_checkout_url();
	ok( '' !== (string) $checkout_url, 'a checkout URL is configured' );

	// Old handles are gone; only the script-less gateway registry handle remains.
	do_action( 'wp_enqueue_scripts' );
	foreach ( array( 'ohmylms-add-to-cart', 'ohmylms-tax-calculation', 'ohmylms-stripe-intents' ) as $handle ) {
		ok( ! wp_script_is( $handle, 'registered' ), "$handle is no longer registered" );
	}
	ok( false === wp_scripts()->registered['ohmylms-checkout']->src && array() === wp_scripts()->registered['ohmylms-checkout']->deps, 'ohmylms-checkout is a script-less registry handle without jQuery' );
	ok( false !== strpos( implode( '', (array) wp_scripts()->get_data( 'ohmylms-checkout', 'after' ) ), 'registerPaymentGatewayHandler' ), 'the registry shim defines registerPaymentGatewayHandler' );
	$registered = wp_script_modules();
	$reflection = new ReflectionProperty( $registered, 'registered' );
	$reflection->setAccessible( true );
	$modules = $reflection->getValue( $registered );
	foreach ( array( 'ohmylms/cart', 'ohmylms/checkout', 'ohmylms/stripe' ) as $module ) {
		ok( isset( $modules[ $module ] ), "$module is registered" );
	}
	$stripe_deps = array_column( (array) ( $modules['ohmylms/stripe']['dependencies'] ?? array() ), 'id' );
	ok( in_array( 'ohmylms/checkout', $stripe_deps, true ), 'ohmylms/stripe imports ohmylms/checkout: ' . implode( ', ', $stripe_deps ) );

	// Buy buttons: single course template and the buy-now shortcode.
	wp_set_current_user( $users['buyer'] );
	$course = ohmylms_get_course( $course_id );
	$html   = ci_template( 'single-course/add-to-cart.php' );
	$button = ci_tag( $html, 'add_to_cart_button' );
	ok( $button && 'ohmylms/cart' === $button['data-wp-interactive'] && 'actions.add' === $button['data-wp-on--click'], 'course button is an ohmylms/cart island: ' . $html );
	$context = json_decode( $button['data-wp-context'], true );
	ok( $course_id === $context['courseId'] && 1 === $context['quantity'] && '' === $context['purchaseBy'] && false === $context['purchased'], 'button context names the course: ' . $button['data-wp-context'] );
	ok( 'context.loading' === $button['data-wp-class--loading'] && 'context.added' === $button['data-wp-class--added'], 'loading/added classes are bound' );
	ok( false !== strpos( $button['href'], 'http' ), 'the link still works without JavaScript' );
	ob_start();
	\OhMyLMS\Shortcodes\ShortCodeBuyNow::output( array( 'course_id' => $course_id ) );
	$buy_now = ci_tag( (string) ob_get_clean(), 'add_to_cart_button' );
	$bought  = $buy_now ? json_decode( $buy_now['data-wp-context'], true ) : array( 'courseId' => 0 );
	ok( $buy_now && 'ohmylms/cart' === $buy_now['data-wp-interactive'] && $course_id === $bought['courseId'], 'buy-now shortcode (block, Elementor, Bricks) is decorated' );
	ob_start();
	\OhMyLMS\Shortcodes\ShortCodeBuyNow::output( array( 'course_id' => '' ) );
	$buy_error = (string) ob_get_clean();
	ok( false !== strpos( $buy_error, 'ohmylms-buy-now-error' ) && false === strpos( $buy_error, 'color:red' ) && false !== strpos( $buy_error, 'Course ID is required.' ), 'buy-now errors use the danger class: ' . $buy_error );
	$point = \OhMyLMS\Extensions\Commerce::cart_buttons( '<a href="#" class="add-to-cart-using-point-button" data-course_id="' . $course_id . '">Points</a><a class="ohmylms-button add_to_cart_button" data-membership_id="7" is_already_purchased="1" href="/plan">Plan</a>' );
	ok( false !== strpos( $point, '&quot;purchaseBy&quot;:&quot;point&quot;' ) && false !== strpos( $point, '&quot;membershipId&quot;:7' ) && false !== strpos( $point, '&quot;purchased&quot;:true' ), 'points and membership buttons carry their context: ' . $point );
	ok( \OhMyLMS\Extensions\Commerce::cart_buttons( $point ) === $point, 'decoration is idempotent for nested templates' );
	$cart_state = wp_interactivity_state( 'ohmylms/cart' );
	ok( rest_url() === $cart_state['restRoot'] && wp_verify_nonce( $cart_state['nonce'], 'wp_rest' ) && '' !== $cart_state['messages']['error'], 'cart state carries the REST root and nonce' );
	ok( in_array( 'ohmylms/cart', ci_modules(), true ), 'rendering a button enqueues ohmylms/cart' );

	// Cart endpoint the module calls.
	\OhMyLMS\Commerce\ecommerce()->cart->empty_cart();
	$added = ci_rest( 'POST', '/cart/items', array( 'course_id' => $course_id ) );
	$body  = $added->get_data();
	ok( 200 === $added->get_status() && 'success' === $body['status'] && 0 === strpos( $body['redirect_url'], $checkout_url ) && false !== strpos( $body['redirect_url'], 'nocache=' ), 'REST add-to-cart answers with the checkout redirect: ' . wp_json_encode( $body ) );
	ok( isset( ci_cart()[ $course_id ] ), 'the course is in the buyer cart' );
	\OhMyLMS\Commerce\ecommerce()->cart->empty_cart();
	$points = ci_rest(
		'POST',
		'/cart/items',
		array(
			'course_id'   => $course_id,
			'purchase_by' => 'point',
		)
	)->get_data();
	ok( 'success' === $points['status'] && 'point' === ( ci_cart()[ $course_id ]['purchase_by'] ?? '' ), 'buy-with-points marks the cart item: ' . wp_json_encode( $points ) );
	$missing = ci_rest( 'POST', '/cart/items', array() );
	ok( 400 === $missing->get_status() && 'ohmylms_cart_missing_item' === $missing->get_data()['code'], 'nothing to buy is a 400' );
	$bad = ci_rest(
		'POST',
		'/cart/items',
		array(
			'course_id'   => $course_id,
			'purchase_by' => 'cash',
		)
	);
	ok( 400 === $bad->get_status() && 'rest_invalid_param' === $bad->get_data()['code'], 'unknown purchase type refused' );
	$unknown = ci_rest( 'POST', '/cart/items', array( 'course_id' => 999999999 ) )->get_data();
	ok( 'error' === $unknown['status'] && empty( $unknown['redirect_url'] ), 'an unknown course is not added: ' . wp_json_encode( $unknown ) );

	// The AJAX action shares the service.
	\OhMyLMS\Commerce\ecommerce()->cart->empty_cart();
	$ajax = ci_ajax(
		array( \OhMyLMS\Ajax::class, 'add_to_cart' ),
		array(
			'nonce'     => wp_create_nonce( 'add-to-cart' ),
			'course_id' => $course_id,
		)
	);
	ok( 'success' === ( $ajax['status'] ?? '' ) && 0 === strpos( $ajax['redirect_url'], $checkout_url ), 'AJAX add-to-cart unchanged: ' . wp_json_encode( $ajax ) );
	$ajax = ci_ajax( array( \OhMyLMS\Ajax::class, 'add_to_cart' ), array( 'course_id' => $course_id ) );
	ok( false === ( $ajax['success'] ?? null ), 'AJAX add-to-cart still checks its nonce' );

	// Guests with guest checkout off are sent to the checkout log-in.
	update_option( 'ohmylms_allow_purchase_without_login', 'no' );
	wp_set_current_user( 0 );
	$guest = ci_rest( 'POST', '/cart/items', array( 'course_id' => $course_id ) )->get_data();
	ok( 'success' === $guest['status'] && $checkout_url === $guest['redirect_url'] && false !== stripos( $guest['message'], 'log in' ), 'guest is sent to log in: ' . wp_json_encode( $guest ) );
	update_option( 'ohmylms_allow_purchase_without_login', 'yes' );

	// States route used when the country changes.
	$states = ci_rest( 'GET', '/countries/us/states' );
	$list   = $states->get_data();
	ok( 200 === $states->get_status() && array_values( $list ) === $list, 'states answer a list' );
	if ( $list ) {
		ok( isset( $list[0]['code'], $list[0]['title'] ), 'states carry code and title' );
	}
	ok( 404 === ci_rest( 'GET', '/countries/usa/states' )->get_status(), 'country codes are two letters' );

	// Checkout markup and state.
	wp_set_current_user( $users['buyer'] );
	$cart = \OhMyLMS\Commerce\ecommerce()->cart;
	$cart->empty_cart();
	$cart->add_to_cart( $course_id );
	$cart->calculate_totals();
	$checkout = \OhMyLMS\Commerce\Checkout::instance();
	$html     = ci_template( 'checkout/form-checkout.php', array( 'checkout' => $checkout ) );
	$root     = ci_tag( $html, 'ohmylms-checkout-form-outer' );
	ok( $root && 'ohmylms/checkout' === $root['data-wp-interactive'] && 'actions.submit' === $root['data-wp-on--submit'] && 'actions.change' === $root['data-wp-on--change'] && 'callbacks.init' === $root['data-wp-init'] && 'callbacks.syncRequired' === $root['data-wp-watch'], 'checkout root carries the delegated directives' );
	$offline = ci_tag( $html, 'payment_method_offline' );
	ok( $offline && '{"gateway":"offline"}' === $offline['data-wp-context'] && 'state.isGatewaySelected' === $offline['data-wp-class--selected'], 'gateway rows carry their id: ' . wp_json_encode( $offline ) );
	$place = ci_tag( $html, 'ohmylms-place-order-button' );
	ok( $place && 'state.busy' === $place['data-wp-bind--disabled'], 'place-order button is bound to the busy state' );
	ok( false !== strpos( $html, 'data-wp-style--display="state.loaderDisplay"' ), 'the spinner follows the busy state' );
	$country = ci_tag( $html, 'ohmylms-form-row' );
	$rows    = new WP_HTML_Tag_Processor( $html );
	$folded  = false;
	while ( $rows->next_tag( 'P' ) ) {
		if ( 'country_field' === $rows->get_attribute( 'id' ) ) {
			$folded = (bool) $rows->has_class( 'ohmylms-folded' );
		}
	}
	ok( $country && $folded, 'filled fields render folded without the inline jQuery script' );
	ok( false === stripos( $html, 'jQuery' ), 'no jQuery in the checkout markup: ' . substr( $html, (int) stripos( $html, 'jquery' ) - 300, 600 ) );
	$state = wp_interactivity_state( 'ohmylms/checkout' );
	ok( admin_url( 'admin-ajax.php' ) === $state['ajaxUrl'] && rest_url() === $state['restRoot'] && wp_verify_nonce( $state['restNonce'], 'wp_rest' ), 'checkout state has the AJAX and REST endpoints' );
	ok( wp_verify_nonce( $state['nonces']['applyCoupon'], 'apply-coupon' ) && wp_verify_nonce( $state['nonces']['removeCoupon'], 'remove-coupon' ) && wp_verify_nonce( $state['nonces']['tax'], 'ohmylms_calculate_tax' ), 'checkout state has valid action nonces' );
	ok( in_array( 'email', $state['serverRequired'], true ) && in_array( 'first_name', $state['serverRequired'], true ) && ! in_array( 'city', $state['serverRequired'], true ), 'server-required fields listed: ' . wp_json_encode( $state['serverRequired'] ) );
	ok( is_object( $state['requiredFields'] ) && preg_match( '/^[A-Z]{2}$/', $state['country'] ) && '' === $state['gateway'] && false === $state['busy'], 'gateway map, country and initial UI state' );
	ok( in_array( 'ohmylms/checkout', ci_modules(), true ), 'rendering the checkout enqueues ohmylms/checkout' );
	$copy = \OhMyLMS\Extensions\Commerce::order_details( '<td><span class="thankyou-order-id-text">#5<button type="button" class="thankyou-copy-order-id"></button></span></td>' );
	ok( false !== strpos( $copy, 'data-wp-on--click="actions.copyOrderId"' ), 'thank-you copy button is decorated' );

	// Coupon and tax fragments the module swaps in.
	$coupon = ci_ajax(
		array( \OhMyLMS\Ajax::class, 'apply_coupon' ),
		array(
			'security'    => $state['nonces']['applyCoupon'],
			'coupon_code' => $tag . '-missing',
		)
	);
	ok( false === $coupon['success'] && is_string( $coupon['data']['message'] ?? null ), 'an unknown coupon answers with a notice: ' . wp_json_encode( $coupon ) );
	$removed = ci_ajax(
		array( \OhMyLMS\Ajax::class, 'remove_coupon' ),
		array(
			'security'    => $state['nonces']['removeCoupon'],
			'coupon_code' => $tag . '-missing',
		)
	);
	ok( true === $removed['success'] && false !== strpos( (string) $removed['data']['fragments']['.ohmylms-checkout-review-order'], 'order-total' ), 'coupon removal re-renders the review on the server' );
	$tax = ci_ajax(
		array( \OhMyLMS\Ajax::class, 'calculate_tax' ),
		array(
			'nonce'   => $state['nonces']['tax'],
			'country' => 'DE',
		)
	);
	ok( $state['taxEnabled'] ? ( true === $tax['success'] && isset( $tax['data']['fragments']['.ohmylms-checkout-review-order'] ) ) : false === $tax['success'], 'tax recalculation follows the tax setting: ' . substr( wp_json_encode( $tax ), 0, 200 ) );

	// Order submission exactly as the module posts it (form fields + nonce + action).
	$fields = array(
		'action'                         => 'ohmylms_checkout',
		'payment_method'                 => 'offline',
		'email'                          => $tag . '_buyer@example.invalid',
		'first_name'                     => 'Test',
		'last_name'                      => 'Buyer',
		'address'                        => '1 Test Street',
		'country'                        => 'US',
		'city'                           => 'Test',
		'postcode'                       => '10000',
		'student_id'                     => (string) $users['buyer'],
		'ohmylms-process-checkout-nonce' => wp_create_nonce( 'ohmylms-process_checkout' ),
	);
	$answer = ci_ajax( array( \OhMyLMS\Ajax::class, 'checkout' ), $fields );
	ok( 'success' === ( $answer['result'] ?? '' ) && ! empty( $answer['redirect'] ) && 1 === count( ci_orders( $users['buyer'] ) ), 'checkout submit redirects to the order: ' . wp_json_encode( $answer ) );

	echo "Commerce Interactivity integration: {$checks} checks passed.\n";
} catch ( Throwable $e ) {
	echo $e->getMessage(), "\n", $e->getTraceAsString(), "\n";
	$exit = 1;
} finally {
	foreach ( $snapshot as $option => $value ) {
		if ( null === $value ) {
			delete_option( $option );
		} else {
			update_option( $option, $value );
		}
	}
	\OhMyLMS\Commerce\ecommerce()->cart->empty_cart();
	foreach ( $users as $user_id ) {
		foreach ( ci_orders( $user_id ) as $order_id ) {
			do_action( 'ohmylms_rest_delete_order', $order_id );
			$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $order_id ) );
			wp_delete_post( $order_id, true );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ) );
		if ( \OhMyLMS\Student\EnrolmentSchema::exists() ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_enrolments', array( 'user_id' => $user_id ) );
		}
	}
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user_id ) {
		wp_delete_user( $user_id );
	}
	exit( $exit );
}
