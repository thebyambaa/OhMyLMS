<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Course.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Data;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Class Course
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Course extends Data {

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'course';


	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'course';


	/**
	 * Course data array
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'                  => '',
		'description'           => '',
		'thumbnail_id'          => '',
		'slug'                  => '',
		'status'                => 'draft',
		'visibility_status'     => 'public',
		'password_protected'    => '',
		'post_date'             => null,
		'featured'              => false,
		'price_type'            => 'free',
		'price'                 => '',
		'regular_price'         => 0,
		'sale_price'            => '',
		'sale_price_dates_from' => null,
		'sale_price_dates_to'   => null,
		'date_created'          => null,
		'date_modified'         => null,
		'level'                 => 'beginner',
		'availability'          => false,
		'available_date'        => null,
		'access_type'           => 'public',
		'has_capacity'          => false,
		'capacity'              => 0,
		'video_id'              => '',
		'enable_reviews'        => true,
		'review_count'          => 0,
		'rating_counts'         => array(),
		'average_rating'        => 0,
		'duration'              => null,
		'benefit_description'   => '',
		'benefiter_description' => '',
		'requirement'           => '',
		'download_resource'     => '',
		'type'                  => 'self-paced',
		'creation_method'       => 'scratch',
		'leaderboard_disabled'  => 'no',
		'has_community'         => 'no',
		'sequential_mode'       => 'no',
		'funnel_steps'          => array(),
		'point_disabled'        => 'no',
		'reward_disabled'       => 'no',
		'space_title'           => '',
		'space_description'     => '',
	);


	/**
	 * Course constructor.
	 *
	 * @param string $course Course.
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function __construct( $course = '' ) {
		if ( is_numeric( $course ) && $course > 0 ) {
			$this->set_id( $course );
		} elseif ( $course instanceof self ) {
			$this->set_id( absint( $course->get_id() ) );
		} elseif ( ! empty( $course->ID ) ) {
			$this->set_id( absint( $course->ID ) );
		}

		// load the data store.
		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
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
	 * Get short description
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_benefit_description() {
		return $this->get_prop( 'benefit_description' ) ?? '';
	}

	/**
	 * Get short description
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_benefiter_description() {
		return $this->get_prop( 'benefiter_description' ) ?? '';
	}

	/**
	 * Get short description
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_requirement() {
		return $this->get_prop( 'requirement' ) ?? '';
	}


	/**
	 * Get status of the course
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_status() {
		return $this->get_prop( 'status' ) ?? 'draft';
	}

	/**
	 * Get status of the course
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_password_protected() {
		return $this->get_prop( 'password_protected' ) ?? '';
	}

	/**
	 * Get status of the course
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_post_date() {
		return $this->get_prop( 'post_date' ) ?? '';
	}

	/**
	 * Get status of the course
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_enable_reviews() {
		return $this->get_prop( 'enable_reviews' ) ?? '';
	}


	/**
	 * Get permalink of the course
	 *
	 * @return false|string
	 * @since 1.0.0
	 */
	public function get_permalink(): string {
		return get_permalink( $this->get_id() ) ?? '';
	}


	/**
	 * Get price of the course
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_price( string $context = 'view' ) {
		return $this->get_prop( 'price', $context ) ?? '';
	}


	/**
	 * Get regular price of the course
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_regular_price( string $context = 'view' ) {
		return $this->get_prop( 'regular_price', $context ) ?? '';
	}




	/**
	 * Get sale price of the course
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_sale_price( string $context = 'view' ) {
		return $this->get_prop( 'sale_price', $context ) ?? '';
	}

	/**
	 * Is free for Course.
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
			if ( $this->validate_on_sale() ) {
				$discount_percentage = $this->get_sale_price() ? round( ( ( $this->get_regular_price() - $this->get_sale_price() ) / $this->get_regular_price() ) * 100 ) : 0;
				$price_html         .= apply_filters(
					'ohmylms_course_sale_price_html',
					sprintf(
						'<ins><bdi>%s</bdi></ins><span class="discount-percentage">%s</span> <del><bdi>%s</bdi></del>',
						ohmylms_price( $this->get_sale_price() ),
						$discount_percentage . '% off',
						ohmylms_price( $this->get_regular_price() )
					)
				);
			} else {
				$price_html .= apply_filters(
					'ohmylms_course_price_html',
					ohmylms_price( $this->get_regular_price() )
				);
			}
		} else {
			$price_html .= apply_filters(
				'ohmylms_course_price_html',
				ohmylms_price( $this->get_price() )
			);
		}

		return apply_filters( 'ohmylms_get_price_html', $price_html, $this );
	}


	/**
	 * Get course created date.
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
	 * Get course created date.
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
	 * Get course featured image id
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_thumbnail_id( $context = 'view' ) {
		return $this->get_prop( 'thumbnail_id', $context ) ?? '';
	}

	/**
	 * Get the URL of the first lesson in the course.
	 *
	 * @return string The URL of the first lesson.
	 */
	public function get_course_first_lesson_url() {
		return ohmylms_get_course_first_lesson_url( $this->get_id() );
	}


	/**
	 * Get content link for Course.
	 *
	 * @param mixed $content_id Content identifier.
	 */
	public function get_content_link( $content_id ) {
		$item_type             = get_post_type( $content_id );
		$permalink_structure   = ohmylms_get_permalink_structure();
		$course_base_permalink = $permalink_structure['course_base'];
		$lesson_base_permalink = $permalink_structure['lesson_base'];
		$quiz_base_permalink   = $permalink_structure['quiz_base'];
		$course_permalink      = trailingslashit( $this->get_permalink() );
		$item_slug             = get_post_field( 'post_name', $content_id );

		$slugs       = array(
			'ohmylms-course'     => $course_base_permalink,
			'ohmylms-lesson'     => $lesson_base_permalink,
			'ohmylms-quiz'       => $quiz_base_permalink,
			'ohmylms-assignment' => 'assignments',
		);
		$slug_prefix = trailingslashit( $slugs[ $item_type ] ?? '' );
		return trailingslashit( $course_permalink . $slug_prefix . $item_slug );
	}


	/**
	 * Get thumbnail url for Course.
	 *
	 * @param string $size Size.
	 */
	public function get_thumbnail_url( $size = 'thumbnail' ) {
		$thumbnail_id = $this->get_thumbnail_id();
		return $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, $size ) : OHMYLMS_URL . '/assets/images/course-placeholder-image.jpg';
	}

	/**
	 * Get thumbnail url without placeholder for Course.
	 *
	 * @param string $size Size.
	 */
	public function get_thumbnail_url_without_placeholder( $size = 'thumbnail' ) {
		$thumbnail_id = $this->get_thumbnail_id();
		return $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, $size ) : '';
	}

	/**
	 * Get course featured image id
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_video_id( $context = 'view' ) {
		return $this->get_prop( 'video_id', $context ) ?? 0;
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
	 * Get average rating
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_average_rating( $context = 'view' ) {
		$average_rating = $this->get_prop( 'average_rating', $context ) ?? 0;
		return number_format( (float) $average_rating, 1, '.', '' );
	}

	/**
	 * Get the rating count for the course.
	 *
	 * @param int|null $value The specific rating value to get the count for, or null to get the total count.
	 * @return int The rating count.
	 * @since 1.0.0
	 */
	public function get_rating_count( $value = null ) {
		$counts = $this->get_rating_counts();

		if ( is_null( $value ) ) {
			return array_sum( $counts );
		} elseif ( isset( $counts[ $value ] ) ) {
			return absint( $counts[ $value ] );
		} else {
			return 0;
		}
	}


	/**
	 * Get review count
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_review_count( $context = 'view' ) {
		return $this->get_prop( 'review_count', $context ) ?? '';
	}

	/**
	 * Get rating counts
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_rating_counts( $context = 'view' ) {
		return $this->get_prop( 'rating_counts', $context ) ?? '';
	}


	/**
	 * Get the add to url used mainly in loops.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function add_to_cart_url(): string {
		return apply_filters( 'ohmylms_course_add_to_cart_url', $this->get_permalink(), $this );
	}

	/**
	 * Get course add to cart text
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function add_to_cart_text() {
		$price_type = $this->get_price_type();

		if ( 'free' === $price_type ) {
			$text = apply_filters( 'ohmylms_free_course_add_to_cart_text', __( 'Enroll Now', 'ohmylms' ) );
		} elseif ( $this->is_purchasable() && $this->is_in_stock() ) {
			if ( $this->validate_on_sale() && $this->is_on_sale() ) {
				$text = sprintf(
					// Translators: %s is replaced with the item being purchased.
					__( 'Buy %s', 'ohmylms' ),
					wp_kses_post( ohmylms_price( $this->get_price() ) )
				);
			} else {
				$text = sprintf(
					// Translators: %s is replaced with the item being purchased.
					__( 'Buy %s', 'ohmylms' ),
					wp_kses_post( ohmylms_price( $this->get_regular_price() ) )
				);
			}
		} else {
			$text = __( 'This course cannot be purchased.', 'ohmylms' );
		}

		return apply_filters( 'ohmylms_course_add_to_cart_text', $text );
	}



	/**
	 * Get course add to cart using point text
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function add_to_cart_using_point_text() {
		$price_type = $this->get_price_type();
		if ( $this->is_purchasable() && $this->is_in_stock() ) {
			$text = sprintf(
				// Translators: %s is replaced with the item being purchased.
				__( 'Buy using points (%s pts)', 'ohmylms' ),
				wp_kses_post( $this->get_purchase_point() )
			);
		}
		return apply_filters( 'ohmylms_course_add_to_cart_text', $text );
	}

	/**
	 * Get the level of the course.
	 *
	 * @param string $context The context for getting the level. Default is 'view'.
	 * @return mixed|null The level of the course.
	 *
	 * @since 1.0.0
	 */
	public function get_level( $context = 'view' ) {
		return $this->get_prop( 'level', $context ) ?? '';
	}


	/**
	 * Get the availability of the course.
	 *
	 * @param bool $context The context for getting the availability. Default is false.
	 * @return mixed|null The availability of the course.
	 *
	 * @since 1.0.0
	 */
	public function get_availability( $context = 'view' ) {
		return $this->get_prop( 'availability', $context ) ?? '';
	}

	/**
	 * Get the available date of the course.
	 *
	 * @param string $context The context for getting the available date. Default is ''.
	 * @return mixed|null The available date of the course.
	 *
	 * @since 1.0.0
	 */
	public function get_available_date( $context = 'view' ) {
		return $this->get_prop( 'available_date', $context ) ?? '';
	}

	/**
	 * Get the accessibility of the course.
	 *
	 * @param string $context The context for getting the accessibility. Default is ''.
	 * @return mixed|null The accessibility of the course.
	 *
	 * @since 1.0.0
	 */
	public function get_access_type( $context = 'view' ) {
		return $this->get_prop( 'access_type', $context ) ?? '';
	}

	/**
	 * Get the capacity of the course.
	 *
	 * @param bool $context The context for getting the capacity. Default is false.
	 * @return mixed|null The capacity of the course.
	 *
	 * @since 1.0.0
	 */
	public function get_has_capacity( $context = 'view' ) {
		return $this->get_prop( 'has_capacity', $context ) ?? '';
	}

	/**
	 * Get the limit of the course.
	 *
	 * @param int $context The context for getting the limit. Default is false.
	 * @return mixed|null The limit of the course.
	 *
	 * @since 1.0.0
	 */
	public function get_capacity( $context = 'view' ) {
		return $this->get_prop( 'capacity', $context ) ?? '';
	}

	/**
	 * Get the chapters of the course.
	 *
	 * @return array The chapters of the course.
	 * @since 1.0.0
	 * @param string $return_type Return type.
	 */
	public function get_chapters( $return_type = 'array' ) {
		return $this->data_store->get_chapters( $this, $return_type ) ?? array();
	}

	/**
	 * Get the chapters of the course.
	 *
	 * @return array The chapters of the course.
	 * @since 1.0.0
	 */
	public function has_access() {
		return $this->data_store->check_course_access( $this );
	}


	/**
	 * Get the price type of the course.
	 *
	 * @param string $context The context for getting the price type. Default is 'view'.
	 * @return mixed|null The price type of the course.
	 * @since 1.0.0
	 */
	public function get_price_type( $context = 'view' ) {
		return $this->get_prop( 'price_type', $context ) ?? '';
	}

	/**
	 * Get the number of enrollments for the course.
	 *
	 * @return int The number of enrollments.
	 * @since 1.0.0
	 */
	public function get_total_enrolled_users() {
		return $this->data_store->get_total_enrolled_users( $this ) ?? 0;
	}


	/**
	 * Get the number of in-progress users for the course.
	 *
	 * @return int The number of in-progress users.
	 * @since 1.0.0
	 */
	public function get_total_in_progress_users() {
		return $this->data_store->get_total_in_progress_users( $this ) ?? 0;
	}


	/**
	 * Get the number of completed for the course.
	 *
	 * @return int The number of completed users.
	 * @since 1.0.0
	 */
	public function get_total_completed_users() {
		return $this->data_store->get_total_completed_users( $this ) ?? 0;
	}

	/**
	 * Get the duration of the course.
	 *
	 * @return mixed|null The duration of the course.
	 * @since 1.0.0
	 */
	public function get_duration() {
		return $this->get_prop( 'duration' ) ?? '';
	}

	/**
	 * Get the count of lessons in the course.
	 *
	 * @return int The number of lessons.
	 * @since 1.0.0
	 */
	public function get_lessons_count() {
		return $this->data_store->get_lessons_count( $this ) ?? 0;
	}
	/**
	 * Get the count of lessons in the course.
	 *
	 * @return int The number of lessons.
	 * @since 1.0.0
	 */
	public function get_quiz_count() {
		return $this->data_store->get_quiz_count( $this ) ?? 0;
	}

	/**
	 * Get the quiz ids of the course.
	 *
	 * @return array The list of quiz ids in the course.
	 * @since 1.0.0
	 */
	public function get_quiz_ids() {
		return $this->data_store->get_quiz_ids( $this ) ?? array();
	}

	/**
	 * Get the count of assignment in the course.
	 *
	 * @return int The number of lessons.
	 * @since 1.0.0
	 */
	public function get_assignment_count() {
		return $this->data_store->get_assignment_count( $this ) ?? 0;
	}
	/**
	 * Get all lessons in the course.
	 *
	 * @return array The list of lessons in the course.
	 * @since 1.0.0
	 * @param string $return_type Return type.
	 */
	public function get_lessons( $return_type = 'array' ) {
		$lessons = array();
		foreach ( $this->get_chapters( $return_type ) as $single_chapter ) {
			is_object( $single_chapter ) ? $chapter = $single_chapter :
			$chapter                                = new Chapter( $single_chapter['id'] );
			$lessons                                = array_merge( $lessons, $chapter->get_lessons( $return_type ) );
		}
		return $lessons;
	}

	/**
	 * Set certificate for given course
	 *
	 * @param mixed $certificate_id Certificate identifier.
	 * @return Certificate
	 * @throws \LogicException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function set_certificate( $certificate_id ) {
		return $this->data_store->set_certificate( $this, $certificate_id );
	}


	/**
	 * Get certificate for given course
	 *
	 * @return Certificate
	 * @throws \LogicException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_certificate() {
		return $this->data_store->get_certificate( $this );
	}

	/**
	 * Get course progress for given user
	 *
	 * @param int $user_id User identifier.
	 * @return CourseProgress
	 * @throws \LogicException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_course_progress( int $user_id ): CourseProgress {
		return CourseProgress::get_by_user_id( $user_id, $this->get_id() );
	}

	/**
	 * Get course progress for given user
	 *
	 * @param int $user_id
	 * @return CourseProgress
	 * @throws \LogicException
	 * @since 1.0.0
	 */


	/**
	 * Get all lessons in the course.
	 *
	 * @return array The list of lessons in the course.
	 * @since 1.0.0
	 */
	public function get_all_content_count() {

		return $this->data_store->get_all_content_count( $this ) ?? 0;
	}
	/**
	 * Check if course is exists otr not
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function exists(): bool {
		return true;
	}


	/**
	 * Check if course is purchasable
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_purchasable(): bool {
		return apply_filters( 'ohmylms_course_is_purchasable', $this->exists() && ( 'publish' === $this->get_status() || current_user_can( 'edit_post', $this->get_id() ) ) && '' !== $this->get_price(), $this );
	}


	/**
	 * Check if course is available for enrolment
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_in_stock(): bool {
		return true;
	}

	/**
	 * Check if course is on sale
	 *
	 * @param string $context Context.
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_on_sale( $context = 'view' ) {
		if ( '' !== (string) $this->get_sale_price( $context ) && $this->get_regular_price( $context ) > $this->get_sale_price( $context ) ) {
			$on_sale = true;
		} else {
			$on_sale = false;
		}
		return 'view' === $context ? apply_filters( 'ohmylms_product_is_on_sale', $on_sale, $this ) : $on_sale;
	}

	/**
	 * Validate on sale for Course.
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
	 * Set prerequisites of the Lesson
	 *
	 * @param mixed $resource Resource.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_download_resource( $resource ) {
		$this->set_prop( 'download_resource', $resource );
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
	 * Set name
	 *
	 * @param mixed $enable_reviews Enable reviews.
	 * @since 1.0.0
	 */
	public function set_enable_reviews( $enable_reviews ) {
		$this->set_prop( 'enable_reviews', $enable_reviews );
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
	 * Set description
	 *
	 * @param mixed $description Description.
	 * @since 1.0.0
	 */
	public function set_benefit_description( $description ) {
		$this->set_prop( 'benefit_description', $description );
	}
	/**
	 * Set description
	 *
	 * @param mixed $description Description.
	 * @since 1.0.0
	 */
	public function set_benefiter_description( $description ) {
		$this->set_prop( 'benefiter_description', $description );
	}
	/**
	 * Set description
	 *
	 * @param mixed $requirement Requirement.
	 * @since 1.0.0
	 */
	public function set_requirement( $requirement ) {
		$this->set_prop( 'requirement', $requirement );
	}


	/**
	 * Set featured image id
	 *
	 * @param mixed $image_id Image identifier.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_thumbnail_id( $image_id ) {
		$this->set_prop( 'thumbnail_id', $image_id );
	}
	/**
	 * Set featured image id
	 *
	 * @param mixed $image_id Image identifier.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_video_id( $image_id ) {
		$this->set_prop( 'video_id', $image_id );
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
	 * Set price of the course
	 *
	 * @param mixed $price Price.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_price( $price ) {
		$this->set_prop( 'price', $price );
	}


	/**
	 * Set regular price of the course
	 *
	 * @param mixed $regular_price Regular price.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_regular_price( $regular_price ) {
		$this->set_prop( 'regular_price', $regular_price );
	}


	/**
	 * Set sale price of the course
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
	 * Set the date when the course was created.
	 *
	 * @param string|null $date The date when the course was created.
	 * @since 1.0.0
	 */
	public function set_date_created( $date = null ) {
		$this->set_date_prop( 'date_created', $date );
	}

	/**
	 * Set the date when the course was created.
	 *
	 * @param string|null $date The date when the course was created.
	 * @since 1.0.0
	 */
	public function set_date_modified( $date = null ) {
		$this->set_date_prop( 'date_modified', $date );
	}

	/**
	 * Set the level of the course.
	 *
	 * @param mixed $level The level of the course.
	 * .
	 * @since 1.0.0
	 */
	public function set_level( $level ) {
		$this->set_prop( 'level', $level );
	}


	/**
	 * Set the availability of the course.
	 *
	 * @param mixed $availability The availability of the course.
	 * .
	 * @since 1.0.0
	 */
	public function set_availability( $availability ) {
		$this->set_prop( 'availability', $availability );
	}

	/**
	 * Set the available date of the course.
	 *
	 * @param mixed $available_date The available date of the course.
	 * .
	 * @since 1.0.0
	 */
	public function set_available_date( $available_date ) {
		$this->set_prop( 'available_date', $available_date );
	}

	/**
	 * Set the capacity date of the course.
	 *
	 * @param mixed $capacity The capacity date of the course.
	 * .
	 * @since 1.0.0
	 */
	public function set_has_capacity( $capacity ) {
		$this->set_prop( 'has_capacity', $capacity );
	}

	/**
	 * Set the limit date of the course.
	 *
	 * @param mixed $limit The limit date of the course.
	 * .
	 * @since 1.0.0
	 */
	public function set_capacity( $limit ) {
		$this->set_prop( 'capacity', $limit );
	}

	/**
	 * Set the accessibility date of the course.
	 *
	 * @param mixed $accessibility The limit date of the course.
	 * .
	 * @since 1.0.0
	 */
	public function set_access_type( $accessibility ) {
		$this->set_prop( 'access_type', $accessibility );
	}


	/**
	 * Set the chapters of the course.
	 *
	 * @param array $chapters The chapters of the course.
	 * .
	 * @since 1.0.0
	 */
	public function set_chapters( $chapters ) {
		return $this->data_store->set_chapters( $this, $chapters );
	}

	/**
	 * Set the price type of the course.
	 *
	 * @param mixed $value The price type of the course.
	 * @since 1.0.0
	 */
	public function set_price_type( $value ) {
		$this->set_prop( 'price_type', $value );
	}

	/**
	 * Set the duration of the course.
	 *
	 * @param mixed $value The duration of the course.
	 * @since 1.0.0
	 */
	public function set_duration( $value ) {
		$this->set_prop( 'duration', $value );
	}

	/**
	 * Set the rating with count.
	 *
	 * @param mixed $value The rating with count.
	 * @since 1.0.0
	 */
	public function set_rating_with_count( $value ) {
		$this->set_prop( 'rating_with_count', $value );
	}

	/**
	 * Set the student count.
	 *
	 * @param mixed $value The student count.
	 * @since 1.0.0
	 */
	public function set_student_count( $value ) {
		$this->set_prop( 'student_count', $value );
	}

	/**
	 * Set rating counts. Read only.
	 *
	 * @param array $counts Product rating counts.
	 */
	public function set_rating_counts( $counts ) {
		$this->set_prop( 'rating_counts', array_filter( array_map( 'absint', (array) $counts ) ) );
	}

	/**
	 * Set average rating. Read only.
	 *
	 * @param float $average Product average rating.
	 */
	public function set_average_rating( $average ) {
		$this->set_prop( 'average_rating', ohmylms_format_decimal( $average ) );
	}

	/**
	 * Set review count. Read only.
	 *
	 * @param int $count Product review count.
	 */
	public function set_review_count( $count ) {
		$this->set_prop( 'review_count', absint( $count ) );
	}

	/**
	 * Is enrolled for Course.
	 */
	public function is_enrolled() {
		$this->data_store->is_enrolled( $this, $args );
	}


	/**
	 * Save or update the course object in DB
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function save() {
		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		/**
		 * Fires before saving the course object.
		 *
		 * This action allows developers to perform custom actions before the course object is saved.
		 *
		 * @param Course $this The course object being saved.
		 * @param DataStores $data_store The data store object handling the course data.
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
		 * Fires after saving the course object.
		 *
		 * This action allows developers to perform custom actions after the course object is saved.
		 *
		 * @param Course $this The course object being saved.
		 * @param DataStores $data_store The data store object handling the course data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_' . $this->object_type . '_object_save', $this, $this->data_store );

		return $this->get_id();
	}

	/**
	 * Delete course data
	 *
	 * @param array $args Operation arguments.
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		$this->data_store->delete( $this, $args );
	}


	/**
	 * Count students
	 */
	public function get_students_count() {
		$this->data_store->get_students_count( $this );
	}


	/**
	 * Get all students
	 *
	 * @param string $filter Filter.
	 * @param mixed  $sort_by Sort by.
	 * @param mixed  $start_date Start date.
	 * @param mixed  $end_date End date.
	 * @param mixed  $search Search.
	 * @param mixed  $only_completed Only completed.
	 */
	public function get_students( $filter = 'all', $sort_by = null, $start_date = null, $end_date = null, $search = null, $only_completed = null ) {
		return $this->data_store->get_students( $this, $filter, $sort_by, $start_date, $end_date, $search, $only_completed );
	}


	/**
	 * Search lessons within the course based on a search term.
	 *
	 * @param string $term The search term to filter lessons by.
	 * @return array An array of lessons that match the search term.
	 *
	 * @since 1.0.0
	 */
	public function search_lessons_in_course( $term ) {
		return $this->data_store->search_lessons_in_course( $this, $term );
	}

	/**
	 * Get the type of the course (self-paced or cohort-based)
	 *
	 * @param string $context Context.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_type( $context = 'view' ) {
		return $this->get_prop( 'type', $context ) ?? 'self-paced';
	}

	/**
	 * Set the type of the course (self-paced or cohort-based)
	 *
	 * @param string $type Type.
	 * @since 1.0.0
	 */
	public function set_type( $type ) {
		$this->set_prop( 'type', $type );
	}

	/**
	 * Get the creation method of the course (scratch, template, or ai)
	 *
	 * @param string $context Context.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_creation_method( $context = 'view' ) {
		return $this->get_prop( 'creation_method', $context ) ?? 'scratch';
	}

	/**
	 * Set the creation method of the course (scratch, template, or ai)
	 *
	 * @param string $creation_method Creation method.
	 * @since 1.0.0
	 */
	public function set_creation_method( $creation_method ) {
		$this->set_prop( 'creation_method', $creation_method );
	}

	/**
	 * Get whether the course has community features enabled
	 *
	 * @param string $context Context.
	 * @return bool
	 * @since 1.0.0
	 */
	public function get_has_community( $context = 'view' ) {
		return $this->get_prop( 'has_community', $context ) ?? false;
	}

	/**
	 * Get the title of the course space.
	 *
	 * @param string $context Context.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_space_title( $context = 'view' ) {
		return $this->get_prop( 'space_title', $context ) ?? '';
	}

	/**
	 * Get the description of the course space.
	 *
	 * @param string $context Context.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_space_description( $context = 'view' ) {
		return $this->get_prop( 'space_description', $context ) ?? '';
	}

	/**
	 * Get the URL of the course space.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_space_url() {
		return $this->data_store->get_space_url( $this );
	}

	/**
	 * Set whether the course has community features enabled
	 *
	 * @param bool $has_community Has community.
	 * @since 1.0.0
	 */
	public function set_has_community( $has_community ) {
		$this->set_prop( 'has_community', $has_community );
	}

	/**
	 * Get whether sequential lesson access mode is enabled
	 *
	 * @param string $context Context.
	 * @return string 'yes' or 'no'
	 * @since 1.0.0
	 */
	public function get_sequential_mode( $context = 'view' ) {
		return $this->get_prop( 'sequential_mode', $context ) ?? 'no';
	}

	/**
	 * Set sequential lesson access mode
	 *
	 * @param string $sequential_mode 'yes' or 'no'.
	 * @since 1.0.0
	 */
	public function set_sequential_mode( $sequential_mode ) {
		$this->set_prop( 'sequential_mode', $sequential_mode );
	}

	/**
	 * Set the title of the course space.
	 *
	 * @param string $title Title.
	 * @since 1.0.0
	 */
	public function set_space_title( $title ) {
		$this->set_prop( 'space_title', $title );
	}

	/**
	 * Set the description of the course space.
	 *
	 * @param string $description Description.
	 * @since 1.0.0
	 */
	public function set_space_description( $description ) {
		$this->set_prop( 'space_description', $description );
	}

	/**
	 * Set the URL of the course space.
	 *
	 * @param string $url Url.
	 * @since 1.0.0
	 */
	public function set_space_url( $url ) {
		$this->set_prop( 'space_url', $url );
	}

	/**
	 * Get the cohort associated with the course.
	 *
	 * @return Cohort|null The cohort object if it exists, otherwise null.
	 * @since 1.0.0
	 */
	public function get_cohort() {
		return $this->data_store->get_cohort( $this );
	}

	/**
	 * Get additional resource count for Course.
	 */
	public function get_additional_resource_count() {
		$download_resource = $this->get_download_resource();
		return isset( $download_resource['file'] ) ? count( $download_resource['file'] ) : 0;
	}

	/**
	 * Get download resource for Course.
	 *
	 * @param string $context Context.
	 */
	public function get_download_resource( $context = 'view' ) {
		return $this->get_prop( 'download_resource', $context ) ?? array();
	}

	/**
	 * Get purchase point for Course.
	 */
	public function get_purchase_point() {
		return $this->data_store->get_purchase_point( $this );
	}

	/**
	 * Set purchase point for Course.
	 *
	 * @param mixed $point Point.
	 */
	public function set_purchase_point( $point ) {
		update_post_meta( $this->get_id(), '_purchase_point', $point );
	}

	/**
	 * Get resources for Course.
	 */
	public function get_resources() {
		return $this->data_store->get_resources( $this );
	}

	/**
	 * Get leaderboard disabled for Course.
	 */
	public function get_leaderboard_disabled() {
		return $this->data_store->get_leaderboard_disabled( $this );
	}

	/**
	 * Set leaderboard disabled for Course.
	 *
	 * @param mixed $disabled Disabled.
	 */
	public function set_leaderboard_disabled( $disabled ) {
		update_post_meta( $this->get_id(), '_leaderboard_disabled', $disabled );
	}

	/**
	 * Get funnel steps for Course.
	 */
	public function get_funnel_steps() {
		return $this->data_store->get_funnel_steps( $this );
	}

	/**
	 * Set funnel steps for Course.
	 *
	 * @param mixed $steps Steps.
	 */
	public function set_funnel_steps( $steps ) {
		update_post_meta( $this->get_id(), '_funnel_steps', $steps );
	}

	/**
	 * Get point disabled for Course.
	 */
	public function get_point_disabled() {
		return $this->data_store->get_point_disabled( $this );
	}

	/**
	 * Set point disabled for Course.
	 *
	 * @param mixed $disabled Disabled.
	 */
	public function set_point_disabled( $disabled ) {
		update_post_meta( $this->get_id(), '_point_disabled', $disabled );
	}

	/**
	 * Get reward disabled for Course.
	 */
	public function get_reward_disabled() {
		return $this->data_store->get_reward_disabled( $this );
	}

	/**
	 * Set reward disabled for Course.
	 *
	 * @param mixed $disabled Disabled.
	 */
	public function set_reward_disabled( $disabled ) {
		update_post_meta( $this->get_id(), '_reward_disabled', $disabled );
	}
}
