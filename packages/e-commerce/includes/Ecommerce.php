<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Class CLMS_Order_Loader
 * Handles loading and instantiation of order-related classes.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce;

use OhMyLMS\Commerce\Factory\OrderFactory;
use OhMyLMS\Commerce\Factory\SubscriptionFactory;
use OhMyLMS\Commerce\Gateways\Gateways;
use OhMyLMS\Commerce\Rest\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Class CLMS_Order_Loader
 * Handles loading and instantiation of order-related classes.
 */
final class Ecommerce {

	/**
	 * Post types.
	 *
	 * @var PostTypes
	 */
	public $post_types;


	/**
	 * Session.
	 *
	 * @var SessionHandler
	 */
	public $session;


	/**
	 * Cart.
	 *
	 * @var $cart Cart
	 */
	public $cart;

	/**
	 * Cart.
	 *
	 * @var $payment Cart
	 */
	public $payment;

	/**
	 * Holds the singleton instance of this class.
	 *
	 * @var Ecommerce
	 */
	private static $instance;

	/**
	 * Order factory.
	 *
	 * @var OrderFactory
	 */
	public $order_factory;


	/**
	 * Subscription factory.
	 *
	 * @var mixed
	 */
	public $subscription_factory;

	/**
	 * Query.
	 *
	 * @var CustomEndpoints
	 */
	public $query;

	/**
	 * Subscription manager.
	 *
	 * @var SubscriptionManager
	 */
	public $subscription_manager;


	/**
	 * Prefix.
	 *
	 * @var mixed
	 */
	public $prefix = 'ohmylms';

	/**
	 * Instance.
	 *
	 * @var Api
	 */
	public $rest_api;

	/**
	 * Instance.
	 *
	 * @var Gateways
	 */
	public $gateways;


	/**
	 * Singleton instance.
	 *
	 * @return OhMyLMS
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}


	/**
	 * Init for Ecommerce.
	 */
	public function init() {
		$this->register_services();
		$this->init_post_types();
	}


	/**
	 * Register order-related services in the container.
	 *
	 * @since 1.0.0
	 */
	private function register_services() {
		$this->post_types = new PostTypes();
		$this->session    = new SessionHandler();
		$this->session->init();

		$this->cart                 = new Cart();
		$this->order_factory        = new OrderFactory();
		$this->subscription_factory = new SubscriptionFactory();
		$this->query                = new CustomEndpoints();
		$this->rest_api             = new Api();
		$this->gateways             = $this->gateways();
		$this->subscription_manager = new SubscriptionManager();
		$this->subscription_manager::init_hooks();
		\OhMyLMS\Cohorts\CohortHooks::init();
		\OhMyLMS\Automation\RuleRunner::init();

		$ajax = new Ajax();
		$ajax::init();
	}


	/**
	 * Initialize the post types.
	 *
	 * @since 1.0.0
	 */
	public function init_post_types() {
		$this->post_types->register_post_types();
		$this->post_types->register_post_status();
	}


	/**
	 * Get Checkout Class.
	 *
	 * @return Checkout|null
	 */
	public function checkout() {
		return Checkout::instance();
	}

	/**
	 * Membership instance declared
	 *
	 * @return Membership|null
	 * @since 1.0.0
	 */
	public function membership() {
		return Membership::instance();
	}

	/**
	 * Get gateways class.
	 *
	 * @return Gateways
	 */
	public function gateways() {
		return Gateways::instance();
	}

	/**
	 * Order object
	 *
	 * @param mixed $order_id Order identifier.
	 * @return Order
	 * @sincee 1.0.0
	 */
	public function order( $order_id = null ): Order {
		if ( $order_id ) {
			return new Order( $order_id );
		}
		return new Order();
	}
}

/**
 * Ecommerce for Ecommerce.
 */
function ecommerce() {
	return Ecommerce::instance();
}

ecommerce(); // phpcs:ignore




