<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Data;

use OhMyLMS\Commerce\Abstracts\Data;
use OhMyLMS\Commerce\DataStores;

/**
 * Plugin coupon implementation.
 */
class Coupon extends Data {

	/**
	 * Data store name.
	 *
	 * @var mixed
	 */
	protected string $data_store_name = 'coupon';

	/**
	 * Object type.
	 *
	 * @var mixed
	 */
	protected string $object_type = 'coupon';

	/**
	 * Data.
	 *
	 * @var mixed
	 */
	protected array $data = array(
		'title'                => '',
		'code'                 => '',
		'amount'               => 0,
		'status'               => null,
		'date_created'         => null,
		'date_modified'        => null,
		'date_start'           => null,
		'date_expires'         => null,
		'discount_type'        => 'fixed_cart',
		'description'          => '',
		'usage_count'          => 0,
		'individual_use'       => 'no',
		'course_id_type'       => 'all',
		'course_ids'           => array(),
		'usage_limit'          => 0,
		'usage_limit_per_user' => 0,
		'exclude_sale_items'   => 'no',
		'minimum_amount'       => '',
		'maximum_amount'       => '',
		'used_by'              => array(),
	);

	/**
	 * Initialize Coupon.
	 *
	 * @param int $data Input data.
	 */
	public function __construct( $data = 0 ) {
		parent::__construct( $data );
		if ( is_int( $data ) ) {
			$this->set_id( $data );
		} elseif ( is_string( $data ) ) {
			$id = ohmylms_ecommerce_get_coupon_id_by_code( $data );
			if ( ! $id ) {
				$this->set_id( $data );
			} else {
				$this->set_id( $id );
				$this->set_code( $data );
			}
		} else {
			$this->set_object_read( true );
		}

		// load the data store.
		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}


	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	|
	| Methods for getting data from the coupon object.
	|
	*/

	/**
	 * Get the coupon title.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_title( $context = 'view' ) {
		return $this->get_prop( 'title', $context );
	}

	/**
	 * Set title
	 *
	 * @param mixed $name Name.
	 * @since 1.0.0
	 */
	public function set_title( $name ) {
		$this->set_prop( 'title', $name );
	}

	/**
	 * Get the coupon code.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_code( $context = 'view' ) {
		return $this->get_prop( 'code', $context );
	}

	/**
	 * Get the discount amount.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return float
	 * @since 1.0.0
	 */
	public function get_amount( $context = 'view' ) {
		return $this->get_prop( 'amount', $context );
	}

	/**
	 * Get the status of the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string|null
	 * @since 1.0.0
	 */
	public function get_status( $context = 'view' ) {
		return $this->get_prop( 'status', $context );
	}

	/**
	 * Get the creation date of the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string|null
	 * @since 1.0.0
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->get_prop( 'date_created', $context );
	}

	/**
	 * Get the modification date of the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string|null
	 * @since 1.0.0
	 */
	public function get_date_modified( $context = 'view' ) {
		return $this->get_prop( 'date_modified', $context );
	}

	/**
	 * Get the expiration date of the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string|null
	 * @since 1.0.0
	 */
	public function get_date_expires( $context = 'view' ) {
		return $this->get_prop( 'date_expires', $context );
	}

	/**
	 * Get the start date of the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string|null
	 * @since 1.0.0
	 */
	public function get_date_start( $context = 'view' ) {
		return $this->get_prop( 'date_start', $context );
	}

	/**
	 * Get the discount type (fixed_cart or percentage).
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_discount_type( $context = 'view' ) {
		return $this->get_prop( 'discount_type', $context );
	}

	/**
	 * Get the description of the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_description( $context = 'view' ) {
		return $this->get_prop( 'description', $context );
	}

	/**
	 * Get the usage count of the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return int
	 * @since 1.0.0
	 */
	public function get_usage_count( $context = 'view' ) {
		return $this->get_prop( 'usage_count', $context );
	}

	/**
	 * Get whether the coupon is for individual use only.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return bool
	 * @since 1.0.0
	 */
	public function get_individual_use( $context = 'view' ) {
		return $this->get_prop( 'individual_use', $context );
	}

	/**
	 * Get the course IDs associated with the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_course_ids( $context = 'view' ) {
		return $this->get_prop( 'course_ids', $context );
	}

	/**
	 * Get the course IDs associated with the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_course_id_type( $context = 'view' ) {
		return $this->get_prop( 'course_id_type', $context );
	}

	/**
	 * Get the excluded course IDs associated with the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_excluded_course_ids( $context = 'view' ) {
		return $this->get_prop( 'excluded_course_ids', $context );
	}

	/**
	 * Get the usage limit of the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return int
	 * @since 1.0.0
	 */
	public function get_usage_limit( $context = 'view' ) {
		return $this->get_prop( 'usage_limit', $context );
	}

	/**
	 * Get the usage limit per user for the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return int
	 * @since 1.0.0
	 */
	public function get_usage_limit_per_user( $context = 'view' ) {
		return $this->get_prop( 'usage_limit_per_user', $context );
	}

	/**
	 * Get whether sale items are excluded from the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return bool
	 * @since 1.0.0
	 */
	public function get_exclude_sale_items( $context = 'view' ) {
		return $this->get_prop( 'exclude_sale_items', $context );
	}

	/**
	 * Get the minimum amount for the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_minimum_amount( $context = 'view' ) {
		return $this->get_prop( 'minimum_amount', $context );
	}

	/**
	 * Get the maximum amount for the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_maximum_amount( $context = 'view' ) {
		return $this->get_prop( 'maximum_amount', $context );
	}

	/**
	 * Get the list of users who have used the coupon.
	 *
	 * @param string $context Context for the value. Defaults to 'view'.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_used_by( $context = 'view' ) {
		return $this->get_prop( 'used_by', $context );
	}


	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	|
	| Functions for setting coupon data. These should not update anything in the
	| database itself and should only change what is stored in the class
	| object.
	|
	*/

	/**
	 * Set the usage count of the coupon.
	 *
	 * @param int $usage_count The usage count to set.
	 * @since 1.0.0
	 */
	public function set_usage_count( $usage_count ) {
		$this->set_prop( 'usage_count', absint( $usage_count ) );
	}

	/**
	 * Set the coupon code.
	 *
	 * @param string $code The coupon code to set.
	 * @since 1.0.0
	 */
	public function set_code( $code ) {
		$this->set_prop( 'code', sanitize_text_field( $code ) );
	}

	/**
	 * Set the discount amount.
	 *
	 * @param float $amount The amount to set.
	 * @since 1.0.0
	 */
	public function set_amount( $amount ) {
		$this->set_prop( 'amount', floatval( $amount ) );
	}

	/**
	 * Set the status of the coupon.
	 *
	 * @param string|null $status The status to set.
	 * @since 1.0.0
	 */
	public function set_status( $status ) {
		$this->set_prop( 'status', $status );
	}

	/**
	 * Set the creation date of the coupon.
	 *
	 * @param string|null $date_created The creation date to set.
	 * @since 1.0.0
	 */
	public function set_date_created( $date_created ) {
		$this->set_date_prop( 'date_created', $date_created );
	}

	/**
	 * Set the modification date of the coupon.
	 *
	 * @param string|null $date_modified The modification date to set.
	 * @since 1.0.0
	 */
	public function set_date_modified( $date_modified ) {
		$this->set_date_prop( 'date_modified', $date_modified );
	}

	/**
	 * Set the expiration date of the coupon.
	 *
	 * @param string|null $date_expires The expiration date to set.
	 * @since 1.0.0
	 */
	public function set_date_expires( $date_expires ) {
		$this->set_date_prop( 'date_expires', $date_expires );
	}

	/**
	 * Set the start date of the coupon.
	 *
	 * @param string|null $date_start The start date to set.
	 * @since 1.0.0
	 */
	public function set_date_start( $date_start ) {
		$this->set_date_prop( 'date_start', $date_start );
	}

	/**
	 * Set the discount type (fixed_cart or percentage).
	 *
	 * @param string $discount_type The discount type to set.
	 * @since 1.0.0
	 */
	public function set_discount_type( $discount_type ) {
		$this->set_prop( 'discount_type', $discount_type );
	}

	/**
	 * Set the description of the coupon.
	 *
	 * @param string $description The description to set.
	 * @since 1.0.0
	 */
	public function set_description( $description ) {
		$this->set_prop( 'description', sanitize_text_field( $description ) );
	}

	/**
	 * Set whether the coupon is for individual use only.
	 *
	 * @param bool $individual_use Whether to set individual use.
	 * @since 1.0.0
	 */
	public function set_individual_use( $individual_use ) {
		$this->set_prop( 'individual_use', $individual_use );
	}

	/**
	 * Set the course IDs associated with the coupon.
	 *
	 * @param array $course_ids The course IDs to set.
	 * @since 1.0.0
	 */
	public function set_course_ids( $course_ids ) {
		$this->set_prop( 'course_ids', is_array( $course_ids ) ? $course_ids : array() );
	}

	/**
	 * Set the course IDs associated with the coupon.
	 *
	 * @param mixed $type Type.
	 * @since 1.0.0
	 */
	public function set_course_id_type( $type ) {
		$this->set_prop( 'course_id_type', $type );
	}

	/**
	 * Set the excluded course IDs associated with the coupon.
	 *
	 * @param array $excluded_course_ids The excluded course IDs to set.
	 * @since 1.0.0
	 */
	public function set_excluded_course_ids( $excluded_course_ids ) {
		$this->set_prop( 'excluded_course_ids', is_array( $excluded_course_ids ) ? $excluded_course_ids : array() );
	}

	/**
	 * Set the usage limit of the coupon.
	 *
	 * @param int $usage_limit The usage limit to set.
	 * @since 1.0.0
	 */
	public function set_usage_limit( $usage_limit ) {
		$this->set_prop( 'usage_limit', absint( $usage_limit ) );
	}

	/**
	 * Set the usage limit per user for the coupon.
	 *
	 * @param int $usage_limit_per_user The usage limit per user to set.
	 * @since 1.0.0
	 */
	public function set_usage_limit_per_user( $usage_limit_per_user ) {
		$this->set_prop( 'usage_limit_per_user', absint( $usage_limit_per_user ) );
	}

	/**
	 * Set whether sale items are excluded from the coupon.
	 *
	 * @param bool $exclude_sale_items Whether to exclude sale items.
	 * @since 1.0.0
	 */
	public function set_exclude_sale_items( $exclude_sale_items ) {
		$this->set_prop( 'exclude_sale_items', $exclude_sale_items );
	}

	/**
	 * Set the minimum amount for the coupon.
	 *
	 * @param string $minimum_amount The minimum amount to set.
	 * @since 1.0.0
	 */
	public function set_minimum_amount( $minimum_amount ) {
		$this->set_prop( 'minimum_amount', sanitize_text_field( $minimum_amount ) );
	}

	/**
	 * Set the maximum amount for the coupon.
	 *
	 * @param string $maximum_amount The maximum amount to set.
	 * @since 1.0.0
	 */
	public function set_maximum_amount( $maximum_amount ) {
		$this->set_prop( 'maximum_amount', sanitize_text_field( $maximum_amount ) );
	}

	/**
	 * Set the list of users who have used the coupon.
	 *
	 * @param array $used_by The users who have used the coupon.
	 * @since 1.0.0
	 */
	public function set_used_by( $used_by ) {
		$this->set_prop( 'used_by', is_array( $used_by ) ? $used_by : array() );
	}
}
