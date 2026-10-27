<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Data;

/**
 * Plugin order item course implementation.
 */
class OrderItemCourse extends OrderItem {

	/**
	 * Order Data array. This is the core order data exposed in APIs since 3.0.0.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	protected array $extra_data = array(
		'course_id' => 0,
		'quantity'  => 1,
		'subtotal'  => 0,
		'total'     => 0,
	);


	/**
	 * Get the type of the order item.
	 *
	 * @return string The type of the order item.
	 * @since 1.0.0
	 */
	public function get_type() {
		return 'line_item';
	}

	/**
	 * Get the quantity of the order item.
	 *
	 * @return int The quantity of the order item.
	 * @since 1.0.0
	 */
	public function get_quantity() {
		return $this->get_prop( 'quantity' );
	}

	/**
	 * Set the quantity of the order item.
	 *
	 * @param int $quantity The quantity to set.
	 * @since 1.0.0
	 */
	public function set_quantity( $quantity ) {
		$this->set_prop( 'quantity', absint( $quantity ) );
	}

	/**
	 * Get the subtotal of the order item.
	 *
	 * @return float The subtotal of the order item.
	 * @since 1.0.0
	 */
	public function get_subtotal() {
		return $this->get_prop( 'subtotal' );
	}

	/**
	 * Set the subtotal of the order item.
	 *
	 * @param float $subtotal The subtotal to set.
	 * @since 1.0.0
	 */
	public function set_subtotal( $subtotal ) {
		$this->set_prop( 'subtotal', floatval( $subtotal ) );
	}

	/**
	 * Get the total of the order item.
	 *
	 * @return float The total of the order item.
	 * @since 1.0.0
	 */
	public function get_total() {
		return $this->get_prop( 'total' );
	}

	/**
	 * Set the total of the order item.
	 *
	 * @param float $total The total to set.
	 * @since 1.0.0
	 */
	public function set_total( $total ) {
		$this->set_prop( 'total', floatval( $total ) );
	}

	/**
	 * Get the name of the order item.
	 *
	 * @return string The name of the order item.
	 * @since 1.0.0
	 */
	public function get_name() {
		return $this->get_prop( 'name' );
	}

	/**
	 * Get the course associated with the order item.
	 *
	 * @return \Course The course object.
	 * @since 1.0.0
	 */
	public function get_course() {
		return ohmylms_get_course( $this->get_course_id() );
	}

	/**
	 * Get the course associated with the order item.
	 *
	 * @return \Course The course object.
	 * @since 1.0.0
	 */
	public function get_membership() {
		return ohmylms_get_membership( $this->get_course_id() );
	}


	/**
	 * Set the name of the order item.
	 *
	 * @param string $name The name to set.
	 * @since 1.0.0
	 */
	public function set_name( $name ) {
		$this->set_prop( 'name', sanitize_text_field( $name ) );
	}

	/**
	 * Get the course ID of the order item.
	 *
	 * @return int The course ID of the order item.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_course_id( $context = 'view' ) {
		return $this->get_prop( 'course_id', $context );
	}

	/**
	 * Set the course ID of the order item.
	 *
	 * @param int $course_id The course ID to set.
	 * @since 1.0.0
	 */
	public function set_course_id( $course_id ) {
		$this->set_prop( 'course_id', absint( $course_id ) );
	}
}
