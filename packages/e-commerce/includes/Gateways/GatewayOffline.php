<?php // phpcs:ignore WordPress.Files.FileName -- Retains the established public include path and Composer class mapping.

use OhMyLMS\Commerce\Abstracts\PaymentGateway;

/**
 * Plugin oh my lms_gateway_offline implementation.
 */
class OhMyLMS_Gateway_Offline extends PaymentGateway {

	/**
	 * Testmode.
	 *
	 * @var mixed
	 */
	public $testmode;
	/**
	 * Subscription support.
	 *
	 * @var mixed
	 */
	public $subscription_support;
	/**
	 * Initialize Gateway offline.
	 */
	public function __construct() {
		$this->id                   = 'offline';
		$gateway_settings_key       = 'ohmylms_' . $this->id . '_settings';
		$this->settings             = get_option( $gateway_settings_key, array() );
		$this->title                = $this->get_setting( 'title', __( 'Offline', 'ohmylms' ) );
		$this->description          = $this->get_setting( 'instruction', __( 'Pay with offline payment.', 'ohmylms' ) );
		$this->has_fields           = true;
		$this->subscription_support = false;
		$this->order_button_text    = __( 'Place payment', 'ohmylms' );
		$this->testmode             = 'yes' === $this->get_setting( 'test_mode', 'no' );
		$this->enabled              = $this->get_setting( 'enabled', 'no' );

		// Actions.
		add_action( 'ohmylms_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}


	/**
	 * Output payment fields on the checkout page.
	 * Displays the instruction/description for offline payment.
	 *
	 * @return void
	 */
	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wp_kses_post( $description ) );
		}
	}


	/**
	 * Get payment gateway settings.
	 * This method returns an array of settings for the payment gateway.
	 *
	 * @return array
	 */
	public function get_settings() {
		$fields           = array(
			array(
				'title'             => __( 'Title', 'ohmylms' ),
				'short_description' => __( 'Enter the title that will appear for offline payment during checkout.', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => __( 'Offline payment', 'ohmylms' ),
				'option_name'       => 'title',
				'value'             => $this->title,
			),
			array(
				'title'             => __( 'Instruction', 'ohmylms' ),
				'short_description' => __( 'Provide detailed instructions on how students should complete offline payment', 'ohmylms' ),
				'input_type'        => 'textarea',
				'default_value'     => __( 'Pay with offline payment.', 'ohmylms' ),
				'option_name'       => 'instruction',
				'value'             => $this->description,
			),
			array(
				'title'             => __( 'Test Mode', 'ohmylms' ),
				'short_description' => __( 'Automatically complete orders for testing purposes without actual payment.', 'ohmylms' ),
				'input_type'        => 'switch',
				'default_value'     => 'no',
				'option_name'       => 'test_mode',
				'value'             => $this->testmode,
			),
		);
		$gateway_settings = array(
			'id'                   => $this->id,
			'title'                => $this->title,
			'description'          => $this->description,
			'icon'                 => '<svg width="23" height="18" fill="none" viewBox="0 0 23 18" xmlns="http://www.w3.org/2000/svg" style="display: block;"><path fill="var(--ohmylms-primary-color)" d="M4.231 4.234c-.274-.519-.934-.73-1.473-.477-.704.34-1.386.9-1.902 1.366A1.028 1.028 0 00.79 6.616a1.147 1.147 0 001.561.064c.22-.19.88-.784 1.397-1.027.538-.265.758-.9.494-1.42h-.01zm17.891.699A16.256 16.256 0 006.321 1.936c-.033.01-.066.042-.1.064L4.606.337a1.125 1.125 0 00-1.55-.053A1.037 1.037 0 003 1.777l15.395 15.884a1.121 1.121 0 001.55.053c.44-.402.462-1.07.055-1.493l-8.115-8.376a9.36 9.36 0 016.422 2.806c.22.222.505.328.791.328.275 0 .55-.095.76-.296.439-.402.46-1.07.043-1.493A11.577 11.577 0 0011.6 5.716c-.55 0-1.1.043-1.639.117h-.022L7.86 3.683a13.841 13.841 0 013.739-.519 14.02 14.02 0 019.072 3.347c.209.18.473.264.726.264.308 0 .604-.116.824-.36a1.02 1.02 0 00-.099-1.493v.01zM7.41 7.58a1.121 1.121 0 00-1.508-.38A11.444 11.444 0 003.44 9.04a1.036 1.036 0 000 1.493 1.112 1.112 0 001.55-.01A9.686 9.686 0 016.992 9.02c.527-.296.703-.942.395-1.45l.022.01zm3.364 3.696c-.176-.562-.791-.869-1.374-.7-1.144.34-2.265 1.356-2.826 1.928a1.019 1.019 0 00.044 1.493c.209.19.484.297.758.297.286 0 .583-.106.792-.329.682-.688 1.419-1.217 1.88-1.355a1.05 1.05 0 00.726-1.324v-.01zm.726 6.682c.759 0 1.374-.593 1.374-1.324 0-.731-.615-1.324-1.374-1.324-.76 0-1.375.593-1.375 1.324 0 .731.616 1.324 1.375 1.324z"/></svg>',
			'has_config'           => true,
			'subscription_support' => false,
			'settings_fields'      => $fields,
			'enabled'              => $this->enabled,
		);

		return $gateway_settings;
	}

	/**
	 * Process payment
	 *
	 * @param int  $order_id Order identifier.
	 * @return array
	 * @since 1.0.0
	 * @param bool $is_subscription Is subscription.
	 */
	public function process_payment( $order_id, $is_subscription = false ) {
		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( $this->testmode ) {
			$order->payment_complete();
		} else {
			$order->update_status( 'processing' );
			$order->add_order_note( __( 'Awaiting offline payment.', 'ohmylms' ) );
		}
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
