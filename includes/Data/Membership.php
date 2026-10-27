<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Membership.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Data;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Class Membership
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Membership extends Data {

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'membership';


	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'membership';


	/**
	 * Subscription data.
	 *
	 * @var mixed|null Subscription data
	 */
	public $subscription = null;

	/**
	 * Order data.
	 *
	 * @var mixed|null Order data
	 */
	public $order = null;

	/**
	 * Membership data array
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'                         => '',
		'description'                  => '',
		'slug'                         => '',
		'status'                       => 'publish',
		'visibility_status'            => 'public',
		'post_date'                    => null,
		'price'                        => '',
		'regular_price'                => 0,
		'sale_price'                   => '',
		'sale_price_dates_from'        => null,
		'sale_price_dates_to'          => null,
		'date_created'                 => null,
		'date_modified'                => null,
		'sign_up_fee'                  => '',
		'subscription_period'          => 'year',
		'subscription_period_interval' => 0,
		'subscription_length'          => 0,
		'subscription_trial_length'    => 0,
		'products'                     => array(),
		'course_categories'            => array(),
		'course_tags'                  => array(),
		'course_curriculum'            => array(),
		'course_bundles'               => array(),
		'excluded_courses'             => array(),
	);


	/**
	 * Membership constructor.
	 *
	 * @param string $membership Membership.
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function __construct( $membership = '' ) {
		if ( is_numeric( $membership ) && $membership > 0 ) {
			$this->set_id( $membership );
		} elseif ( $membership instanceof self ) {
			$this->set_id( absint( $membership->get_id() ) );
		} elseif ( ! empty( $membership->ID ) ) {
			$this->set_id( absint( $membership->ID ) );
		}

		// load the data store.
		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Get thumbnail url for Membership.
	 */
	public function get_thumbnail_url() {
		return '';
	}


	/**
	 * Get name of the object
	 *
	 * @return mixed
	 */
	public function get_name() {
		return $this->get_prop( 'name' ) ?? '';
	}


	/**
	 * Get name
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_slug() {
		return $this->get_prop( 'slug' ) ?? '';
	}

	/**
	 * Get description
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_description() {
		return $this->get_prop( 'description' ) ?? '';
	}


	/**
	 * Get short description
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_short_description() {
		return $this->get_prop( 'short_description' ) ?? '';
	}

	/**
	 * Get status of the membership
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_status() {
		return $this->get_prop( 'status' ) ?? 'draft';
	}

	/**
	 * Get status of the membership
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_password_protected() {
		return $this->get_prop( 'password_protected' ) ?? '';
	}

	/**
	 * Get status of the membership
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_post_date() {
		return $this->get_prop( 'post_date' ) ?? '';
	}


	/**
	 * Get permalink of the membership
	 *
	 * @return false|string
	 * @since 1.0.0
	 */
	public function get_permalink(): string {
		return get_permalink( $this->get_id() ) ?? '';
	}


	/**
	 * Get price of the membership
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_price( string $context = 'view' ) {
		return $this->get_prop( 'price', $context ) ?? '';
	}


	/**
	 * Get regular price of the membership
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_regular_price( string $context = 'view' ) {
		return $this->get_prop( 'regular_price', $context ) ?? '';
	}

	/**
	 * Get sale price of the membership
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_sale_price( string $context = 'view' ) {
		return $this->get_prop( 'sale_price', $context ) ?? '';
	}


	/**
	 * Is free for Membership.
	 */
	public function is_free() {
		return 'free' === $this->get_price_type();
	}

	/**
	 * Get price html
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_price_html() {
		$price_html = '';

		if ( $this->is_free() ) {
			$price_html .= apply_filters(
				'ohmylms_free_course_price_html',
				sprintf( '<span class="free">%s</span>', esc_html__( 'Free', 'ohmylms' ) )
			);
		} elseif ( $this->is_on_sale() ) {
			$price_html .= apply_filters(
				'ohmylms_course_sale_price_html',
				sprintf(
					'<ins><bdi>%s</bdi></ins> <del><bdi>%s</bdi></del>',
					ohmylms_price( $this->get_sale_price() ),
					ohmylms_price( $this->get_regular_price() )
				)
			);
		} else {
			$price_html .= apply_filters(
				'ohmylms_course_price_html',
				ohmylms_price( $this->get_price() )
			);
		}

		return apply_filters( 'ohmylms_get_price_html', $price_html, $this );
	}


	/**
	 * Get membership created date.
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 *
	 * @since 1.0.0
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->get_prop( 'date_created', $context ) ?? '';
	}


	/**
	 * Get membership created date.
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 *
	 * @since 1.0.0
	 */
	public function get_date_modified( $context = 'view' ) {
		return $this->get_prop( 'date_modified', $context ) ?? '';
	}


	/**
	 * Get membership featured image id
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_thumbnail_id( $context = 'view' ) {
		return $this->get_prop( 'thumbnail_id', $context ) ?? '';
	}

	/**
	 * Get date on sale from.
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_sale_price_dates_from( $context = 'view' ) {
		return $this->get_prop( 'sale_price_dates_from', $context ) ?? '';
	}

	/**
	 * Get date on sale to.
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_sale_price_dates_to( $context = 'view' ) {
		return $this->get_prop( 'sale_price_dates_to', $context ) ?? '';
	}

	/**
	 * Get the add to url used mainly in loops.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function add_to_cart_url(): string {
		$profile_page_id = get_option( 'ohmylms_profile_page_id' );
		$profile_link    = get_page_link( $profile_page_id );

		if ( $this->is_already_purchased() ) {
			return apply_filters( 'ohmylms_membership_add_to_cart_url', $profile_link, $this );
		}
		return apply_filters( 'ohmylms_membership_add_to_cart_url', $this->get_permalink(), $this );
	}

	/**
	 * Get membership add to cart text
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function add_to_cart_text() {
		if ( $this->is_purchasable() && $this->is_in_stock() ) {
			if ( ! $this->is_already_purchased() ) {
				$price      = $this->validate_on_sale() ? $this->get_price() : $this->get_regular_price();
				$signup_fee = $this->get_sign_up_fee();
				if ( ! empty( $signup_fee ) ) {
					$price += floatval( $signup_fee );
				}
				$text = sprintf(
					// Translators: %s is replaced with the plan being purchased.
					__( 'Choose Plan %s', 'ohmylms' ),
					wp_kses_post( ohmylms_price( $price ) )
				);
			} else {
				$text = __( 'See Membership', 'ohmylms' );
			}
		} else {
			$text = __( 'This membership cannot be purchased.', 'ohmylms' );
		}

		return apply_filters( 'ohmylms_membership_add_to_cart_text', $text );
	}
	/**
	 * Get the price type of the membership.
	 *
	 * @param string $context The context for getting the price type. Default is 'view'.
	 * @return mixed|null The price type of the membership.
	 * @since 1.0.0
	 */
	public function get_price_type( $context = 'view' ) {
		return $this->get_prop( 'price_type', $context ) ?? '';
	}

	/**
	 * Check if membership is exists otr not
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function exists(): bool {
		return true;
	}


	/**
	 * Check if membership is purchasable
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_purchasable(): bool {
		return apply_filters( 'ohmylms_membership_is_purchasable', $this->exists() && ( 'publish' === $this->get_status() || current_user_can( 'edit_post', $this->get_id() ) ) && '' !== $this->get_price(), $this );
	}


	/**
	 * Check if membership is available for enrolment
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_in_stock(): bool {
		return true;
	}

	/**
	 * Check if membership is on sale
	 *
	 * @param string $context Context.
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_on_sale( $context = 'view' ) {
		$on_sale = false;
		if ( '' !== (string) $this->get_sale_price( $context ) && 0 < $this->get_sale_price( $context ) && $this->get_regular_price( $context ) > $this->get_sale_price( $context ) ) {
			$from_date = $this->get_sale_price_dates_from();
			$to_date   = $this->get_sale_price_dates_to();

			if ( empty( $from_date ) && empty( $to_date ) ) {
				$on_sale = true;
			} else {
				$now_date = current_time( 'Y-m-d' );
				if ( $from_date instanceof \DateTime && $to_date instanceof \DateTime ) {
					// Extract only the date (YYYY-MM-DD) for comparison.
					$start_date = $from_date->format( 'Y-m-d' );
					$end_date   = $to_date->format( 'Y-m-d' );

					// Validate if today's date is within the sale period (ignoring time).
					if ( $now_date >= $start_date && $now_date <= $end_date ) {
						return true;
					} else {
						return false;
					}
				}
			}
		}

		return 'view' === $context ? apply_filters( 'ohmylms_membership_is_on_sale', $on_sale, $this ) : $on_sale;
	}


	/**
	 * Validate on sale for Membership.
	 */
	public function validate_on_sale() {
		$start    = $this->get_sale_price_dates_from();
		$to       = $this->get_sale_price_dates_to();
		$now_date = current_time( 'Y-m-d' );
		if ( ! $start || ! $to ) {
			return true;
		}
		// Convert to DateTime if necessary.
		if ( $start instanceof \DateTime && $to instanceof \DateTime ) {
			// Extract only the date (YYYY-MM-DD) for comparison.
			$start_date = $start->format( 'Y-m-d' );
			$end_date   = $to->format( 'Y-m-d' );

			// Validate if today's date is within the sale period (ignoring time).
			if ( $now_date >= $start_date && $now_date <= $end_date ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}


	/**
	 * Get sign up fee
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_sign_up_fee() {
		return $this->get_prop( 'sign_up_fee' ) ?? '';
	}

	/**
	 * Get free trial
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_free_trial() {
		return $this->get_prop( 'free_trial' ) ?? array();
	}

	/**
	 * Get free trial length
	 *
	 * @return array|mixed
	 * @since 1.0.0
	 */
	public function get_subscription_trial_length() {
		return $this->get_prop( 'subscription_trial_length' );
	}

	/**
	 * Get stop renew
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_stop_renew() {
		return $this->get_prop( 'stop_renew' ) ?? array();
	}

	/**
	 * Get subscription length
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_subscription_length() {
		return $this->get_prop( 'subscription_length' ) ?? array();
	}

	/**
	 * Get products
	 *
	 * @return array
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_products( $context = 'view' ) {
		$products = $this->get_prop( 'products', $context ) ?? array();
		return 'edit' === $context ? $products : \OhMyLMS\Membership\CourseSelection::resolve( $products, $this->get_course_categories(), $this->get_course_tags(), $this->get_excluded_courses(), $this->get_course_curriculum(), $this->get_course_bundles() );
	}
	/**
	 * Get course categories for Membership.
	 *
	 * @param string $context Context.
	 */
	public function get_course_categories( $context = 'view' ) {
		return $this->get_prop( 'course_categories', $context ) ?? array(); }
	/**
	 * Get course tags for Membership.
	 *
	 * @param string $context Context.
	 */
	public function get_course_tags( $context = 'view' ) {
		return $this->get_prop( 'course_tags', $context ) ?? array(); }
	/**
	 * Get course curriculum for Membership.
	 *
	 * @param string $context Context.
	 */
	public function get_course_curriculum( $context = 'view' ) {
		return $this->get_prop( 'course_curriculum', $context ) ?? array(); }
	/**
	 * Get course bundles for Membership.
	 *
	 * @param string $context Context.
	 */
	public function get_course_bundles( $context = 'view' ) {
		return $this->get_prop( 'course_bundles', $context ) ?? array(); }
	/**
	 * Get excluded courses for Membership.
	 *
	 * @param string $context Context.
	 */
	public function get_excluded_courses( $context = 'view' ) {
		return $this->get_prop( 'excluded_courses', $context ) ?? array(); }
	/**
	 * Set course categories for Membership.
	 *
	 * @param mixed $ids Ids.
	 */
	public function set_course_categories( $ids ) {
		$this->set_prop( 'course_categories', \OhMyLMS\Membership\CourseSelection::ids( $ids ) ); }
	/**
	 * Set course tags for Membership.
	 *
	 * @param mixed $ids Ids.
	 */
	public function set_course_tags( $ids ) {
		$this->set_prop( 'course_tags', \OhMyLMS\Membership\CourseSelection::ids( $ids ) ); }
	/**
	 * Set course curriculum for Membership.
	 *
	 * @param mixed $ids Ids.
	 */
	public function set_course_curriculum( $ids ) {
		$this->set_prop( 'course_curriculum', \OhMyLMS\Membership\CourseSelection::ids( $ids ) ); }
	/**
	 * Set course bundles for Membership.
	 *
	 * @param mixed $ids Ids.
	 */
	public function set_course_bundles( $ids ) {
		$this->set_prop( 'course_bundles', \OhMyLMS\Membership\CourseSelection::ids( $ids ) ); }
	/**
	 * Set excluded courses for Membership.
	 *
	 * @param mixed $ids Ids.
	 */
	public function set_excluded_courses( $ids ) {
		$this->set_prop( 'excluded_courses', \OhMyLMS\Membership\CourseSelection::ids( $ids ) ); }

	/**
	 * Get subscription duration for Membership.
	 */
	public function get_subscription_duration() {
		$subscription_length = $this->get_subscription_length();
		return $subscription_length['duration'] ?? '';
	}

	/**
	 * Get subscription period for Membership.
	 */
	public function get_subscription_period() {
		return $this->get_prop( 'subscription_period' );
	}

	/**
	 * Get billing period for Membership.
	 */
	public function get_billing_period() {
		return $this->data_store->get_billing_period( $this );
	}

	/**
	 * Get billing interval for Membership.
	 */
	public function get_billing_interval() {
		return $this->data_store->get_billing_interval( $this );
	}


	/*
	 * ************************************
	 * Setters
	 * ************************************
	 */


	/**
	 * Set status
	 *
	 * @param mixed $status Status.
	 * @since 1.0.0
	 */
	public function set_status( $status ) {
		$this->set_prop( 'status', $status );
	}

	/**
	 * Set status
	 *
	 * @param mixed $status Status.
	 * @since 1.0.0
	 */
	public function set_get_visibility_status( $status ) {
		$this->set_prop( 'visibility_status', $status );
	}

	/**
	 * Set status
	 *
	 * @param mixed $password Password.
	 * @since 1.0.0
	 */
	public function set_password_protected( $password ) {
		$this->set_prop( 'password_protected', $password );
	}
	/**
	 * Set post_date
	 *
	 * @param mixed $data Input data.
	 * @since 1.0.0
	 */
	public function set_post_date( $data ) {
		$this->set_date_prop( 'post_date', $data );
	}


	/**
	 * Set name
	 *
	 * @param mixed $name Name.
	 * @since 1.0.0
	 */
	public function set_name( $name ) {
		$this->set_prop( 'name', $name );
	}
	/**
	 * Set short description
	 *
	 * @param mixed $short_description Short description.
	 * @since 1.0.0
	 */
	public function set_short_description( $short_description ) {
		$this->set_prop( 'short_description', $short_description );
	}

	/**
	 * Set description
	 *
	 * @param mixed $description Description.
	 * @since 1.0.0
	 */
	public function set_description( $description ) {
		$this->set_prop( 'description', $description );
	}

	/**
	 * Set slug
	 *
	 * @param mixed $slug Slug.
	 * @since 1.0.0
	 */
	public function set_slug( $slug ) {
		$this->set_prop( 'slug', $slug );
	}


	/**
	 * Set price of the membership
	 *
	 * @param mixed $price Price.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_price( $price ) {
		$this->set_prop( 'price', $price );
	}


	/**
	 * Set regular price of the membership
	 *
	 * @param mixed $regular_price Regular price.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_regular_price( $regular_price ) {
		$this->set_prop( 'regular_price', $regular_price );
	}


	/**
	 * Set sale price of the membership
	 *
	 * @param mixed $sale_price Sale price.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_sale_price( $sale_price ) {
		$this->set_prop( 'sale_price', $sale_price );
	}

	/**
	 * Set the date when the sale price starts.
	 *
	 * @param string|null $date The date when the sale price starts.
	 * @since 1.0.0
	 */
	public function set_sale_price_dates_from( $date = null ) {
		$this->set_date_prop( 'sale_price_dates_from', $date );
	}

	/**
	 * Set the date when the sale price ends.
	 *
	 * @param string|null $date The date when the sale price ends.
	 * @since 1.0.0
	 */
	public function set_sale_price_dates_to( $date = null ) {
		$this->set_date_prop( 'sale_price_dates_to', $date );
	}


	/**
	 * Set the date when the membership was created.
	 *
	 * @param string|null $date The date when the membership was created.
	 * @since 1.0.0
	 */
	public function set_date_created( $date = null ) {
		$this->set_date_prop( 'date_created', $date );
	}

	/**
	 * Set the date when the membership was created.
	 *
	 * @param string|null $date The date when the membership was created.
	 * @since 1.0.0
	 */
	public function set_date_modified( $date = null ) {
		$this->set_date_prop( 'date_modified', $date );
	}
	/**
	 * Set the accessibility date of the membership.
	 *
	 * @param mixed $accessibility The limit date of the membership.
	 * .
	 * @since 1.0.0
	 */
	public function set_access_type( $accessibility ) {
		$this->set_prop( 'access_type', $accessibility );
	}

	/**
	 * Set the price type of the membership.
	 *
	 * @param mixed $value The price type of the membership.
	 * @since 1.0.0
	 */
	public function set_price_type( $value ) {
		$this->set_prop( 'price_type', $value );
	}

	/**
	 * Save or update the membership object in DB
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function save() {
		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		/**
		 * Fires before saving the membership object.
		 *
		 * This action allows developers to perform custom actions before the membership object is saved.
		 *
		 * @param Membership $this The membership object being saved.
		 * @param DataStores $data_store The data store object handling the membership data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_' . $this->object_type . '_object_save', $this, $this->data_store );

		if ( $this->get_id() ) {
			$this->data_store->update( $this );
		} else {
			$this->data_store->create( $this );
		}

		/**
		 * Fires after saving the membership object.
		 *
		 * This action allows developers to perform custom actions after the membership object is saved.
		 *
		 * @param Membership $this The membership object being saved.
		 * @param DataStores $data_store The data store object handling the membership data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_' . $this->object_type . '_object_save', $this, $this->data_store );

		return $this->get_id();
	}

	/**
	 * Delete membership data
	 *
	 * @param array $args Operation arguments.
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		$this->data_store->delete( $this, $args );
	}
	/**
	 * Set sign up fee
	 *
	 * @param mixed $sign_up_fee Sign up fee.
	 * @since 1.0.0
	 */
	public function set_sign_up_fee( $sign_up_fee ) {
		$this->set_prop( 'sign_up_fee', $sign_up_fee );
	}

	/**
	 * Set free trial
	 *
	 * @param array $free_trial Free trial.
	 * @since 1.0.0
	 */
	public function set_free_trial( array $free_trial ) {
		$this->set_prop( 'free_trial', $free_trial );
	}

	/**
	 * Set stop renew
	 *
	 * @param array $stop_renew Stop renew.
	 * @since 1.0.0
	 */
	public function set_stop_renew( array $stop_renew ) {
		$this->set_prop( 'stop_renew', $stop_renew );
	}

	/**
	 * Set subscription length
	 *
	 * @param array $subscription_length Subscription length.
	 * @since 1.0.0
	 */
	public function set_subscription_length( $subscription_length ) {
		$this->set_prop( 'subscription_length', $subscription_length );
	}

	/**
	 * Set products
	 *
	 * @param array $products Products.
	 * @since 1.0.0
	 */
	public function set_products( array $products ) {
		$this->set_prop( 'products', $products );
	}


	/**
	 * Is already purchased for Membership.
	 */
	public function is_already_purchased() {
		return $this->data_store->is_already_purchased( $this );
	}

	/**
	 * Count membership members for Membership.
	 */
	public function count_membership_members() {
		return $this->data_store->count_membership_members( $this );
	}

	/**
	 * Update membership status for Membership.
	 *
	 * @param mixed $new_status New status.
	 */
	public function update_membership_status( $new_status ) {
		return $this->data_store->update_membership_status( $this, $new_status );
	}

	/**
	 * Cancel enrollment for Membership.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $order_id Order identifier.
	 */
	public function cancel_enrollment( $student_id, $order_id ) {
		return $this->data_store->cancel_enrollment( $this, $student_id, $order_id );
	}

	/**
	 * Get subscription period interval
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function get_subscription_period_interval() {
		return $this->get_prop( 'subscription_period_interval' ) ?? 0;
	}

	/**
	 * Set subscription period interval
	 *
	 * @param int $interval Interval.
	 * @since 1.0.0
	 */
	public function set_subscription_period_interval( $interval ) {
		$this->set_prop( 'subscription_period_interval', $interval );
	}

	/**
	 * Set subscription period interval
	 *
	 * @param mixed $period Period.
	 * @since 1.0.0
	 */
	public function set_subscription_period( $period ) {
		$this->set_prop( 'subscription_period', $period );
	}

	/**
	 * Set trial length
	 *
	 * @param mixed $trial_length Trial length.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_subscription_trial_length( $trial_length ) {
		$this->set_prop( 'subscription_trial_length', $trial_length );
	}

	/**
	 * Get first renewal payment date
	 *
	 * @param string $from_date From date.
	 * @param string $timezone Timezone.
	 * @return mixed|null
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_first_renewal_payment_date( $from_date = '', $timezone = 'gmt' ) {
		$first_renewal_timestamp = $this->get_first_renewal_payment_time( $from_date, $timezone );
		if ( $first_renewal_timestamp > 0 ) {
			$first_renewal_date = gmdate( 'Y-m-d H:i:s', $first_renewal_timestamp );
		} else {
			$first_renewal_date = 0;
		}
		return $first_renewal_date;
	}


	/**
	 * Get first renewal payment time
	 *
	 * @param string $from_date From date.
	 * @param string $timezone Timezone.
	 * @return false|int|mixed
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_first_renewal_payment_time( $from_date = '', $timezone = 'gmt' ) {
		$subscription_period_interval = $this->get_subscription_period_interval();
		$subscription_length          = $this->get_subscription_length();

		if ( $subscription_period_interval !== $subscription_length ) {
			if ( empty( $from_date ) ) {
				$from_date = gmdate( 'Y-m-d H:i:s' );
			}
			$site_time_offset        = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			$first_renewal_timestamp = ohmylms_add_time( $subscription_period_interval, $this->get_subscription_period(), ohmylms_date_to_time( $from_date ) + $site_time_offset );
			if ( 'site' !== $timezone ) {
				$first_renewal_timestamp -= $site_time_offset;
			}
		} else {
			$first_renewal_timestamp = 0;
		}

		return $first_renewal_timestamp;
	}
}
