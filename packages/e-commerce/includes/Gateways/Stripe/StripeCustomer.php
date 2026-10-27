<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Gateways\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin stripe customer implementation.
 */
class StripeCustomer {

	/**
	 * The Stripe customer ID.
	 *
	 * @var string
	 */
	private $id = '';


	/**
	 * The user ID, WordPress user ID.
	 *
	 * @var int
	 */
	private $user_id = 0;

	/**
	 * The customer data.
	 *
	 * @var array
	 */
	private $customer_data = array();

	/**
	 * Constructor for the StripeCustomer class.
	 *
	 * @param int    $user_id The WordPress user ID.
	 * @param string $stripe_customer_id Optional. The Stripe customer ID. Default is an empty string.
	 */
	public function __construct( $user_id, $stripe_customer_id = '' ) {
		$this->set_user_id( $user_id );
		$this->set_id( $stripe_customer_id );
	}

	/**
	 * Get the Stripe customer ID.
	 *
	 * @return string The Stripe customer ID.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the user ID.
	 *
	 * @return int The WordPress user ID.
	 */
	public function get_user_id() {
		return $this->user_id;
	}

	/**
	 * Retrieves the user object based on the user ID.
	 *
	 * @return \WP_User|false The user object if found, false otherwise.
	 *
	 * @since 1.0.0
	 */
	protected function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Get the customer data.
	 *
	 * @return array The customer data.
	 */
	public function get_customer_data() {
		return $this->customer_data;
	}

	/**
	 * Set user id for Stripe customer.
	 *
	 * @param mixed $user_id User identifier.
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = $user_id;
	}

	/**
	 * Set id for Stripe customer.
	 *
	 * @param mixed $id Id.
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * Set the customer data.
	 *
	 * @param array $data The customer data.
	 */
	public function set_customer_data( $data ) {
		$this->customer_data = $data;
	}

	/**
	 * Updates an existing Stripe customer or creates a new one if the customer ID is not set.
	 *
	 * @param array $args Optional. Additional arguments for updating the customer.
	 * @return string The Stripe customer ID.
	 *
	 * @since 1.0.0
	 */
	public function update_or_create_customer( $args = array() ) {
		if ( empty( $this->get_id() ) ) {
			return $this->create_customer( $args );
		} else {
			return $this->update_customer( $args, true );
		}
	}


	/**
	 * Creates a new Stripe customer.
	 *
	 * @param array $args Optional. Additional arguments for creating the customer.
	 * @return string The Stripe customer ID.
	 *
	 * @throws \Exception If there is an error creating the customer.
	 *
	 * @since 1.0.0
	 */
	public function create_customer( $args ) {
		$args     = $this->generate_customer_request( $args );
		$response = StripeApi::request( $args, 'customers' );

		if ( ! empty( $response->error ) ) {
			throw new \Exception( print_r( $response, true ), $response->error->message );
		}

		$this->set_id( $response->id );
		$this->set_customer_data( $response );

		if ( $this->get_user_id() ) {
			update_user_option( $this->get_user_id(), '_ohmylms_stripe_customer_id', $response->id, false );
		}

		return $response->id;
	}

	/**
	 * Generates the customer request array for creating or updating a Stripe customer.
	 *
	 * @param array $args Optional. Additional arguments for the customer request.
	 * @return array The customer request array.
	 *
	 * @since 1.0.0
	 */
	protected function generate_customer_request( $args = array() ) {
		$billing_email  = isset( $_POST['billing_email'] ) ? filter_var( wp_unslash( $_POST['billing_email'] ), FILTER_SANITIZE_EMAIL ) : '';
		$user           = $this->get_user();
		$address_fields = array(
			'line1'       => 'billing_address_1',
			'line2'       => 'billing_address_2',
			'postal_code' => 'billing_postcode',
			'city'        => 'billing_city',
			'state'       => 'billing_state',
			'country'     => 'billing_country',
		);

		if ( $user ) {
			$billing_first_name = get_user_meta( $user->ID, 'billing_first_name', true );
			$billing_last_name  = get_user_meta( $user->ID, 'billing_last_name', true );

			// If billing first name does not exists try the user first name.
			if ( empty( $billing_first_name ) ) {
				$billing_first_name = get_user_meta( $user->ID, 'first_name', true );
			}

			// If billing last name does not exists try the user last name.
			if ( empty( $billing_last_name ) ) {
				$billing_last_name = get_user_meta( $user->ID, 'last_name', true );
			}

			// translators: %1$s First name, %2$s Second name, %3$s Username.
			$description = sprintf( __( 'Name: %1$s %2$s, Username: %3$s', 'ohmylms' ), $billing_first_name, $billing_last_name, $user->user_login );

			$defaults = array(
				'email'       => $user->user_email,
				'description' => $description,
			);

			$billing_full_name = trim( $billing_first_name . ' ' . $billing_last_name );
			if ( ! empty( $billing_full_name ) ) {
				$defaults['name'] = $billing_full_name;
			}
		} else {
			$billing_first_name = isset( $_POST['billing_first_name'] ) ? filter_var( wp_unslash( $_POST['billing_first_name'] ), FILTER_SANITIZE_STRING ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$billing_last_name  = isset( $_POST['billing_last_name'] ) ? filter_var( wp_unslash( $_POST['billing_last_name'] ), FILTER_SANITIZE_STRING ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			// translators: %1$s First name, %2$s Second name.
			$description = sprintf( __( 'Name: %1$s %2$s, Guest', 'ohmylms' ), $billing_first_name, $billing_last_name );

			$defaults = array(
				'email'       => $billing_email,
				'description' => $description,
			);

			$billing_full_name = trim( $billing_first_name . ' ' . $billing_last_name );
			if ( ! empty( $billing_full_name ) ) {
				$defaults['name'] = $billing_full_name;
			}
		}

		// Add customer address default values.
		foreach ( $address_fields as $key => $field ) {
			if ( $user ) {
				$defaults['address'][ $key ] = get_user_meta( $user->ID, $field, true );
			} else {
				$defaults['address'][ $key ] = isset( $_POST[ $field ] ) ? filter_var( wp_unslash( $_POST[ $field ] ), FILTER_SANITIZE_STRING ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			}
		}

		return wp_parse_args( $args, $defaults );
	}


	/**
	 * Update customer for Stripe customer.
	 */
	public function update_customer() {
	}
}
