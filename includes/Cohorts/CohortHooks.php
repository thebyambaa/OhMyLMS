<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Cohort assignment, checkout enforcement and access.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Cohorts;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks cohorts into enrolment, checkout and lesson access.
 *
 * - A learner who enrols (purchase, points, manual) joins the open cohort of the course; a learner
 *   who buys a plan joins the open cohort of the plan.
 * - Checkout and points purchases of a course with cohorts fail when no cohort is open or every open
 *   one is full (the legacy course page already hid the button in that case).
 * - Members cannot open course content before their cohort starts, nor after it ends when the cohort
 *   closes access at its end. Lessons that drip from the cohort start use the member's own cohort
 *   (the legacy code used the course's first cohort for everyone).
 *
 * @since 1.0.0
 */
final class CohortHooks {

	/**
	 * Lock state before the drip filter ran, keyed by lesson and learner.
	 *
	 * @var array<string, bool>
	 */
	private static $before_drip = array();

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'ohmylms_after_enrolled_student', array( __CLASS__, 'after_order_enrolment' ), 20, 2 );
		add_action( 'ohmylms_manual_student_enrollment', array( __CLASS__, 'after_manual_enrolment' ), 20, 2 );
		add_action( 'ohmylms_after_checkout_validation', array( __CLASS__, 'validate_checkout' ), 10, 2 );
		add_filter( 'ohmylms_point_purchase_course_error', array( __CLASS__, 'point_purchase_error' ), 10, 2 );
		add_filter( 'ohmylms_is_lesson_locked', array( __CLASS__, 'remember_lock' ), 9, 4 );
		add_filter( 'ohmylms_is_lesson_locked', array( __CLASS__, 'lesson_locked' ), 11, 4 );
	}

	/**
	 * Assign cohorts after an order enrolled its buyer.
	 *
	 * @param int $order_id Order.
	 * @param int $user_id  Learner (optional in older callers).
	 * @return void
	 */
	public static function after_order_enrolment( $order_id, $user_id = 0 ) {
		global $wpdb;
		$order_id = is_object( $order_id ) && method_exists( $order_id, 'get_id' ) ? (int) $order_id->get_id() : (int) $order_id;
		if ( ! $order_id ) {
			return;
		}
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			$order   = function_exists( 'ohmylms_ecommerce_get_order' ) ? ohmylms_ecommerce_get_order( $order_id ) : null;
			$user_id = $order ? (int) $order->get_student_id() : 0;
		}
		if ( ! $user_id ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh rows of this order.
		$courses = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT course_id FROM %i WHERE order_id = %d AND user_id = %d AND status = 'enrolled'", $wpdb->prefix . 'ohmylms_user_enrollment', $order_id, $user_id ) );
		foreach ( array_map( 'intval', $courses ) as $course_id ) {
			Cohorts::assign( $user_id, 'course_id', $course_id, 'purchase', $order_id );
		}
		$membership_id = (int) get_post_meta( $order_id, '_membership_id', true );
		if ( $membership_id ) {
			Cohorts::assign( $user_id, 'membership_id', $membership_id, 'membership', $order_id );
		}
	}

	/**
	 * Assign the course cohort after a manual enrolment.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return void
	 */
	public static function after_manual_enrolment( $user_id, $course_id ) {
		Cohorts::assign( (int) $user_id, 'course_id', (int) $course_id, 'enrolment' );
	}

	/**
	 * Error when a course has cohorts and none takes new learners.
	 *
	 * @param int $course_id Course.
	 * @return WP_Error|null
	 */
	public static function closed_error( $course_id ) {
		if ( ! Cohorts::has_cohorts( 'course_id', $course_id ) || Cohorts::open_cohort( 'course_id', $course_id ) ) {
			return null;
		}
		return new WP_Error(
			'ohmylms_cohort_enrolment_closed',
			sprintf(
				/* translators: %s: course title. */
				__( 'Enrolment for %s is closed or every cohort is full.', 'ohmylms' ),
				get_the_title( $course_id )
			),
			array(
				'status'    => 409,
				'course_id' => (int) $course_id,
			)
		);
	}

	/**
	 * Checkout: refuse courses whose cohorts take no new learners.
	 *
	 * @param array     $data   Posted checkout data.
	 * @param \WP_Error $errors Error bag.
	 * @return void
	 */
	public static function validate_checkout( $data, $errors ) {
		if ( ! function_exists( '\OhMyLMS\Commerce\ecommerce' ) || ! $errors instanceof WP_Error ) {
			return;
		}
		foreach ( (array) \OhMyLMS\Commerce\ecommerce()->cart->get_cart_contents() as $item ) {
			$course_id = (int) ( $item['course_id'] ?? $item['id'] ?? 0 );
			if ( OHMYLMS_COURSE_CPT !== get_post_type( $course_id ) ) {
				continue;
			}
			$closed = self::closed_error( $course_id );
			if ( $closed ) {
				$errors->add( $closed->get_error_code(), $closed->get_error_message() );
			}
		}
	}

	/**
	 * Points purchase: same rule as the checkout.
	 *
	 * @param WP_Error|null $error     Error so far.
	 * @param int           $course_id Course.
	 * @return WP_Error|null
	 */
	public static function point_purchase_error( $error, $course_id ) {
		return is_wp_error( $error ) ? $error : self::closed_error( $course_id );
	}

	/**
	 * Remember the lock decision before drip schedules (priority 9).
	 *
	 * @param bool $locked     Locked so far.
	 * @param int  $lesson_id  Lesson, quiz or assignment.
	 * @param int  $course_id  Course.
	 * @param int  $student_id Learner.
	 * @return bool Unchanged.
	 */
	public static function remember_lock( $locked, $lesson_id, $course_id = 0, $student_id = 0 ) {
		self::$before_drip[ (int) $lesson_id . ':' . (int) $student_id ] = (bool) $locked;
		return $locked;
	}

	/**
	 * Cohort of a learner that governs a course: their course cohort, else the cohort of the plan
	 * that grants the course.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return array|null Row.
	 */
	public static function governing_cohort( $user_id, $course_id ) {
		static $cache = array();
		$key = (int) $user_id . ':' . (int) $course_id;
		if ( ! array_key_exists( $key, $cache ) || did_action( 'ohmylms_cohort_member_added' ) || did_action( 'ohmylms_cohort_updated' ) ) {
			$cache[ $key ] = self::find_governing_cohort( $user_id, $course_id );
		}
		return $cache[ $key ];
	}

	/**
	 * Uncached {@see self::governing_cohort()}.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return array|null Row.
	 */
	private static function find_governing_cohort( $user_id, $course_id ) {
		global $wpdb;
		$rows = Cohorts::of_learner( $user_id, 'course_id', $course_id );
		if ( ! $rows ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Plan that grants the course.
			$plans = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT membership_id FROM %i WHERE user_id = %d AND course_id = %d AND status = 'enrolled' AND membership_id > 0", $wpdb->prefix . 'ohmylms_user_enrollment', (int) $user_id, (int) $course_id ) );
			foreach ( array_map( 'intval', $plans ) as $plan ) {
				$rows = array_merge( $rows, Cohorts::of_learner( $user_id, 'membership_id', $plan ) );
			}
		}
		foreach ( $rows as $row ) {
			if ( 'archived' !== $row['status'] ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Apply cohort dates to course content (priority 11, after drip schedules).
	 *
	 * @param bool $locked     Locked so far.
	 * @param int  $lesson_id  Lesson, quiz or assignment.
	 * @param int  $course_id  Course.
	 * @param int  $student_id Learner.
	 * @return bool
	 */
	public static function lesson_locked( $locked, $lesson_id, $course_id = 0, $student_id = 0 ) {
		$student_id = (int) $student_id;
		$course_id  = (int) $course_id;
		if ( ! $student_id || ! $course_id ) {
			return $locked;
		}
		$cohort = self::governing_cohort( $student_id, $course_id );
		if ( ! $cohort || empty( $cohort['start_date'] ) ) {
			return $locked;
		}
		$now  = Cohorts::now();
		$meta = json_decode( (string) $cohort['meta'], true );
		if ( $now < $cohort['start_date'] ) {
			return true;
		}
		if ( ! empty( $meta['close_access_at_end'] ) && ! empty( $cohort['end_date'] ) && $now > $cohort['end_date'] ) {
			return true;
		}

		$post_type = get_post_type( $lesson_id );
		$getter    = array(
			'ohmylms-lesson'     => 'ohmylms_get_lesson',
			'ohmylms-quiz'       => 'ohmylms_get_quiz',
			'ohmylms-assignment' => 'ohmylms_get_assignment',
		)[ $post_type ] ?? null;
		$item      = $getter && function_exists( $getter ) ? $getter( $lesson_id ) : null;
		$drip      = $item && method_exists( $item, 'get_drip_settings' ) ? $item->get_drip_settings( 'edit' ) : array();
		$type      = is_array( $drip ) && ! empty( $drip['enable'] ) ? (string) ( $drip['type'] ?? '' ) : '';
		if ( 'cohort-start' !== $type && 'cohort-from-x-days' !== $type ) {
			return $locked;
		}
		$unlock = strtotime( $cohort['start_date'] );
		if ( 'cohort-from-x-days' === $type ) {
			$unlock += max( 0, (int) ( $drip['days'] ?? 0 ) ) * DAY_IN_SECONDS;
		}
		$before = self::$before_drip[ (int) $lesson_id . ':' . $student_id ] ?? false;
		return $before || strtotime( $now ) < $unlock;
	}
}
