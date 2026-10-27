<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Class OrderItem.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Data;

use OhMyLMS\Commerce\Abstracts\Data;
use OhMyLMS\Commerce\DataStores;

/**
 * Class OrderItem
 *
 * Represents an order item in the e-commerce system.
 *
 * @package OhMyLMS\Commerce\Data
 * @since 1.0.0
 */
class OrderItem extends Data {
	/**
	 * Class OrderItemCourse
	 *
	 * Represents an order item for a course.
	 *
	 * @package OhMyLMS\Commerce\Data
	 */
	protected array $data = array(
		'order_id'  => 0,
		'name'      => '',
		'course_id' => 0,
		'quantity'  => 1,
	);

	/**
	 * The type of the object, in this case, 'order_item'.
	 *
	 * @var string The type of the object, in this case, 'order_item'.
	 */
	protected string $object_type = 'order_item';

	/**
	 * OrderItemCourse constructor.
	 *
	 * Initializes the OrderItemCourse object. If an item is provided, it sets the ID and reads the data store.
	 *
	 * @param int|\WC_Order_Item $item The item to initialize. Default is 0.
	 * .
	 * @since 1.0.0
	 */
	public function __construct( $item = 0 ) {
		parent::__construct( $item );
		if ( $item instanceof OrderItem ) {
			$this->set_id( $item->get_id() );
		} elseif ( is_numeric( $item ) && $item > 0 ) {
			$this->set_id( $item );
		} else {
			$this->set_object_read( true );
		}

		$type             = 'line_item' === $this->get_type() ? 'course' : $this->get_type();
		$this->data_store = DataStores::load( 'order-item-' . $type );
		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Set the order ID for the order item.
	 *
	 * @param int $value The order ID to set.
	 * @since 1.0.0
	 */
	public function set_order_id( $value ) {
		$this->set_prop( 'order_id', absint( $value ) );
	}

	/**
	 * Get order item type. Overridden by child classes.
	 *
	 * @return string
	 */
	public function get_type() {
		return '';
	}

	/**
	 * Get order id for Order item.
	 *
	 * @param string $context Context.
	 */
	public function get_order_id( $context = 'view' ) {
		return $this->get_prop( 'order_id', $context );
	}

	/**
	 * Get the quantity of the order item.
	 *
	 * @return int The quantity of the order item.
	 * @since 1.0.0
	 */
	public function get_quantity() {
		return 1;
	}

	/**
	 * Set the course ID of the order item.
	 *
	 * @param int $value The course ID to set.
	 * @since 1.0.0
	 */
	public function set_course_id( $value ) {
		$this->set_prop( 'course_id', absint( $value ) );
	}

	/**
	 * Get the course ID of the order item.
	 *
	 * @param string $context The context for the course ID retrieval. Default is 'view'.
	 * @return int The course ID of the order item.
	 *
	 * @since 1.0.0
	 */
	public function get_course_id( $context = 'view' ) {
		return $this->get_prop( 'course_id', $context );
	}

	/**
	 * Set name for Order item.
	 *
	 * @param mixed $value Value to process.
	 */
	public function set_name( $value ) {
		$this->set_prop( 'name', $value );
	}

	/**
	 * Set quantity for Order item.
	 *
	 * @param mixed $value Value to process.
	 */
	public function set_quantity( $value ) {
		$this->set_prop( 'quantity', absint( $value ) );
	}
}
