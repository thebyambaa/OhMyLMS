<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

defined( 'ABSPATH' ) || exit;


/**
 * Plugin cart session implementation.
 */
class CartSession {

	/**
	 * Reference to cart object.
	 *
	 * @since 1.0.0
	 * @var Cart
	 */
	protected $cart;


	/**
	 * Construct.
	 *
	 * @param mixed $cart Cart.
	 * @throws \Exception When the operation cannot be completed.*/
	public function __construct( $cart ) {
		if ( ! is_a( $cart, 'OhMyLMS\Commerce\Cart' ) ) {
			throw new \Exception( 'A valid Cart object is required' );
		}

		$this->set_cart( $cart );
	}


	/**
	 * Sets the cart object
	 *
	 * @param Cart $cart Cart.
	 * @return void
	 */
	public function set_cart( Cart $cart ) {
		$this->cart = $cart;
	}


	/**
	 * Init for Cart session.
	 */
	public function init() {

		add_action( 'wp_loaded', array( $this, 'get_cart_from_session' ) );
		add_action( 'ohmylms_cart_emptied', array( $this, 'destroy_cart_session' ) );
		add_action( 'ohmylms_cart_loaded_from_session', array( $this, 'set_session' ) );
		add_action( 'ohmylms_after_calculate_totals', array( $this, 'set_session' ) );
		add_action( 'ohmylms_removed_coupon', array( $this, 'set_session' ) );

		// Persistent cart stored to usermeta.
		add_action( 'ohmylms_add_to_cart', array( $this, 'persistent_cart_update' ) );

		// Cookie events - cart cookies need to be set before headers are sent.
		add_action( 'ohmylms_add_to_cart', array( $this, 'maybe_set_cart_cookies' ) );
		add_action( 'wp', array( $this, 'maybe_set_cart_cookies' ), 99 );
		add_action( 'shutdown', array( $this, 'maybe_set_cart_cookies' ), 0 );
	}


	/**
	 * Get cart from session
	 *
	 * @return mixed
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_cart_from_session() {
		do_action( 'ohmylms_load_cart_from_session' );

		$this->cart->set_totals( ecommerce()->session->get( 'cart_totals', null ) );
		$this->cart->set_applied_coupons( ecommerce()->session->get( 'applied_coupons', array() ) );
		$this->cart->set_coupon_discount_totals( ecommerce()->session->get( 'coupon_discount_totals', array() ) );
		$cart                = ecommerce()->session->get( 'cart', null );
		$update_cart_session = false;
		if ( is_null( $cart ) && ! apply_filters( 'ohmylms_persistent_cart_enabled', false ) ) {
			$saved_cart          = $this->get_saved_cart();
			$cart                = is_null( $cart ) ? array() : $cart;
			$cart                = array_merge( $saved_cart, $cart );
			$update_cart_session = true;
		}

		$_cart_contents = array();

		foreach ( $cart as $key => $values ) {
			$post_type = get_post_type( $values['course_id'] );
			if ( OHMYLMS_COURSE_CPT === $post_type ) {
				$course = ohmylms_get_course( $values['course_id'] );

			} elseif ( OHMYLMS_MEMBERSHIP_CPT === $post_type && ohmylms_is_pro() ) {
				$course = ohmylms_get_membership( $values['course_id'] );
			}

			if ( empty( $course ) || ! $course->exists() ) {
				continue;
			}

			$session_data           = array_merge(
				$values,
				array(
					'data' => $course,
				)
			);
			$_cart_contents[ $key ] = $session_data;
			$this->cart->set_cart_contents( $_cart_contents );
		}

		// If it's not empty, it's been already populated by the loop above.
		if ( ! empty( $_cart_contents ) ) {
			$this->cart->set_cart_contents( $_cart_contents );
		}

		do_action( 'ohmylms_cart_loaded_from_session', $this->cart );

		$merge_saved_cart = (bool) get_user_meta( get_current_user_id(), '_ohmylms_load_saved_cart_after_login', true );
		if ( is_null( $cart ) || $merge_saved_cart ) {
			delete_user_meta( get_current_user_id(), '_ohmylms_load_saved_cart_after_login' );
		}

		if ( $update_cart_session || is_null( ecommerce()->session->get( 'cart_totals', null ) ) ) {
			ecommerce()->session->set( 'cart', $this->get_cart_for_session() );
			$this->cart->calculate_totals();

			if ( $merge_saved_cart ) {
				$this->persistent_cart_update();
			}
		}
	}


	/**
	 * Sets up the session for cart
	 *
	 * @return void
	 */
	public function set_session() {
		ecommerce()->session->set( 'cart', $this->get_cart_for_session() );
		ecommerce()->session->set( 'cart_totals', $this->cart->get_totals() );
		ecommerce()->session->set( 'applied_coupons', $this->cart->get_applied_coupons() );
		ecommerce()->session->set( 'coupon_discount_totals', $this->cart->get_coupon_discount_totals() );
	}


	/**
	 * Get cart for session
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_cart_for_session() {
		$cart_session = array();
		foreach ( $this->cart->get_cart() as $key => $values ) {
			$cart_session[ $key ] = $values;
			unset( $cart_session[ $key ]['data'] ); // Unset product object.
		}

		return $cart_session;
	}


	/**
	 * Set cart cookies
	 *
	 * @since 1.0.0
	 */
	public function maybe_set_cart_cookies() {
		// if ( headers_sent() || ! did_action( 'wp_loaded' ) ) {
		// return;
		// }.

		if ( ! $this->cart->is_empty() ) {
			$this->set_cart_cookies( true );
		} elseif ( isset( $_COOKIE['ohmylms_items_in_cart'] ) ) { // WPCS: input var ok.
			$this->set_cart_cookies( false );
		}

		// $this->remove_duplicate_cookies();
	}



	/**
	 * Get saved cart data from user meta
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_saved_cart() {
		$saved_cart      = array();
		$saved_cart_meta = get_user_meta( get_current_user_id(), '_ohmylms_persistent_cart_' . get_current_blog_id(), true );
		if ( isset( $saved_cart_meta['cart'] ) ) {
			$saved_cart = array_filter( (array) $saved_cart_meta['cart'] );
		}
		return $saved_cart;
	}



	/**
	 * Save the cart data on user meta if persistent cart is true
	 *
	 * @since 1.0.0
	 */
	public function persistent_cart_update() {
		if ( get_current_user_id() && ! apply_filters( 'ohmylms_persistent_cart_enabled', false ) ) {
			update_user_meta(
				get_current_user_id(),
				'_ohmylms_persistent_cart_' . get_current_blog_id(),
				array(
					'cart' => $this->get_cart_for_session(),
				)
			);
		}
	}


	/**
	 * Set cart cookies data
	 *
	 * @param bool $set Set.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function set_cart_cookies( $set = true ) {
		$setcookies = array(
			'ohmylms_items_in_cart' => '1',
			'ohmylms_cart_hash'     => $this->cart->get_cart_hash(),
		);

		foreach ( $setcookies as $name => $value ) {
			if ( ! isset( $_COOKIE[ $name ] ) || $_COOKIE[ $name ] !== $value ) {
				ohmylms_setcookie( $name, $value );
				$_COOKIE[ $name ] = $value;
			}
		}
		do_action( 'ohmylms_set_cart_cookies', $set );
	}


	/**
	 * Remove if there is any duplicate cookie
	 *
	 * @since 1.0.0
	 */
	private function remove_duplicate_cookies() {
		$all_cookies    = array_filter(
			headers_list(),
			function ( $header ) {
				return stripos( $header, 'Set-Cookie:' ) !== false;
			}
		);
		$final_cookies  = array();
		$update_cookies = false;

		foreach ( $all_cookies as $cookie ) {

			list(, $cookie_value)             = explode( ':', $cookie, 2 );
			list($cookie_name, $cookie_value) = explode( '=', trim( $cookie_value ), 2 );

			if ( stripos( $cookie_name, 'ohmylms_' ) !== false ) {
				$key = $this->find_cookie_by_name( $cookie_name, $final_cookies );
				if ( false !== $key ) {
					$update_cookies = true;
					unset( $final_cookies[ $key ] );
				}
			}
			$final_cookies[] = $cookie;
		}

		if ( $update_cookies ) {
			header_remove( 'Set-Cookie' );
			foreach ( $final_cookies as $cookie ) {
				// Using header here preserves previous cookie args.
				header( $cookie, false );
			}
		}
	}


	/**
	 * Find cookie by name
	 *
	 * @param mixed $cookie_name Cookie name.
	 * @param mixed $cookies Cookies.
	 * @return bool|int|string
	 *
	 * @since 1.0.0
	 */
	private function find_cookie_by_name( $cookie_name, $cookies ) {
		foreach ( $cookies as $key => $cookie ) {
			if ( strpos( $cookie, $cookie_name ) !== false ) {
				return $key;
			}
		}
		return false;
	}


	/**
	 * Delete the persistent cart permanently.
	 */
	public function persistent_cart_destroy() {
		if ( get_current_user_id() && ! apply_filters( 'ohmylms_persistent_cart_enabled', false ) ) {
			delete_user_meta( get_current_user_id(), '_ohmylms_persistent_cart_' . get_current_blog_id() );
		}
	}

	/**
	 * Destroy cart session
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function destroy_cart_session(): void {
		ecommerce()->session->set( 'cart', null );
	}
}
