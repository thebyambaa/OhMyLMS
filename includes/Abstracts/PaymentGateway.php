<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Abstracts;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin payment gateway implementation.
 */
abstract class PaymentGateway {

	/**
	 * Define if the gateway is enabled or not
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	public bool $is_enabled = false;


	/**
	 * Title of the gateway
	 *
	 * @var string $title
	 * @since 1.0.0
	 */
	public string $title;


	/**
	 * Description of the payment gateway
	 *
	 * @var string $description
	 * @since 1.0.0
	 */
	public string $description;


	/**
	 * Process Payment
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 * @since 1.0.0
	 */
	public function process_payment( $order_id ) {
		return array();
	}


	/**
	 * Process refund
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public function process_refund( $order_id ) {
		return false;
	}
}
