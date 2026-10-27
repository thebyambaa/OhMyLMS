<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Subscription Manager.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce;

use OhMyLMS\Commerce\Data\OrderItem;
use OhMyLMS\Commerce\Data\OrderItemCoupon;
use OhMyLMS\Commerce\Data\OrderItemCourse;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * OhMyLMS Subscription Manager.
 *
 * Handles creation, status updates, scheduling, and processing for site-managed subscriptions.
 * All date/time operations are intended to be stored and calculated in GMT.
 *
 * @since 1.0.0
 */
class SubscriptionManager {

	/**
	 * Post type slug for subscriptions.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ohmylms-subscription';

	/**
	 * Action Scheduler hook for processing renewals.
	 *
	 * @var string
	 */
	const RENEWAL_ACTION_HOOK = 'ohmylms_process_subscription_renewal';

	/**
	 * Create a new subscription linked to an order and membership.
	 *
	 * @since 1.0.0
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object for which the subscription is created.
	 * @param int                          $membership_id The ID of the membership plan post.
	 * @param array                        $gateway_meta Optional. Metadata from the payment gateway.
	 *                                                                                                 Expected keys: 'customer_id', 'payment_method_token'.
	 * @return int|\WP_Error The new subscription post ID on success, or \WP_Error on failure.
	 */
	public static function create_subscription( $order, $membership_id, $gateway_meta = array() ) {
		if ( ! $order instanceof \OhMyLMS\Commerce\Data\Order ) {
			return new \WP_Error( 'invalid_order_object', __( 'Invalid order object provided for subscription creation.', 'ohmylms' ) );
		}
		$membership_id = absint( $membership_id );
		if ( empty( $membership_id ) ) { // Simplified check for now.
			return new \WP_Error( 'invalid_membership_id', __( 'Invalid membership ID provided for subscription creation.', 'ohmylms' ) );
		}

		$student_id = $order->get_student_id();
		if ( empty( $student_id ) ) {
			return new \WP_Error( 'missing_student_id', __( 'Order is missing a student ID, cannot create subscription.', 'ohmylms' ) );
		}

		$dates             = self::calculate_subscription_dates( $order, $membership_id );
		$subscription_data = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => sprintf(
				/* translators: %1$s: text value; %2$s: text value. */
				__( 'Subscription for Order #%1$s - Membership: %2$s', 'ohmylms' ),
				$order->get_id(),
				get_the_title( $membership_id )
			),
			'post_status' => 'ohmylms-pending',
			'post_parent' => $order->get_id(),
			'post_author' => $student_id,
		);

		$subscription_id = wp_insert_post( $subscription_data, true ); // true for WP_Error return on failure.

		if ( is_wp_error( $subscription_id ) ) {
			// translators: %s Error message from wp_insert_post.
			self::add_subscription_note( 0, sprintf( __( 'Failed to create subscription database entry. Error: %s', 'ohmylms' ), $subscription_id->get_error_message() ) );
			return $subscription_id;
		}

		// Store essential meta data.
		update_post_meta( $subscription_id, '_student_id', absint( $student_id ) );
		update_post_meta( $subscription_id, '_membership_id', absint( $membership_id ) );
		update_post_meta( $subscription_id, '_original_order_id', absint( $order->get_id() ) );
		update_post_meta( $subscription_id, '_payment_gateway_id', sanitize_text_field( $order->get_payment_method() ) );

		// Store sanitized gateway-specific metadata for recurring payments.
		if ( ! empty( $gateway_meta['customer_id'] ) ) {
			update_post_meta( $subscription_id, '_gateway_customer_id', sanitize_text_field( $gateway_meta['customer_id'] ) );
		}
		if ( ! empty( $gateway_meta['payment_method_token'] ) ) {
			update_post_meta( $subscription_id, '_gateway_payment_method_token', sanitize_text_field( $gateway_meta['payment_method_token'] ) );
		}

		// Store calculated dates. These are expected to be in GMT 'Y-m-d H:i:s' format.
		update_post_meta( $subscription_id, '_schedule_start_date', $dates['start_date'] );
		update_post_meta( $subscription_id, '_schedule_next_payment_date', $dates['next_payment_date'] );
		if ( $dates['end_date'] ) {
			update_post_meta( $subscription_id, '_schedule_end_date', $dates['end_date'] );
		}
		$tax_rate = $order->get_tax_rate();
		// Store the recurring amount (from membership plan).
		$recurring_amount = (float) get_post_meta( $membership_id, '_regular_price', true );
		if ( $recurring_amount > 0 ) {
			update_post_meta( $subscription_id, '_recurring_amount', $recurring_amount );
		}
		$subscription_period          = get_post_meta( $membership_id, '_subscription_period', true );
		$subscription_period_interval = (int) get_post_meta( $membership_id, '_subscription_period_interval', true );
		update_post_meta( $subscription_id, '_billing_interval', $subscription_period_interval );
		update_post_meta( $subscription_id, '_billing_period', $subscription_period );

		// Save order key from order object.
		if ( method_exists( $order, 'get_order_key' ) ) {
			$order_key = $order->get_order_key();
			if ( $order_key ) {
				update_post_meta( $subscription_id, '_order_key', $order_key );
			}
		}

		// Save all meta from order to subscription.
		self::copy_order_meta_to_subscription( $order->get_id(), $subscription_id );

		// Save _order_version.
		if ( defined( 'OHMYLMS_VERSION' ) ) {
			update_post_meta( $subscription_id, '_order_version', OHMYLMS_VERSION );
		}

		update_post_meta( $subscription_id, '_order_id', $order->get_id() );
		update_post_meta( $subscription_id, '_created_via', 'checkout' );
		update_post_meta( $subscription_id, '_last_payment_date', current_time( 'mysql' ) );
		update_post_meta( $order->get_id(), '_subscription_id', $subscription_id );
		self::add_subscription_note( $subscription_id, sprintf( /* translators: %1$d: number; %2$d: number. */
		__( 'Subscription record created. Related Order ID: #%1$d, Membership ID: #%2$d.', 'ohmylms' ), $order->get_id(), $membership_id ) );
		return $subscription_id;
	}

	/**
	 * Add a note to a subscription. Uses WordPress comment functionality.
	 *
	 * @since 1.0.0
	 *
	 * @param int|\WP_Post $subscription_ref Subscription post ID or object. Can be 0 if post not yet created (note will not be saved).
	 * @param string       $note The note content.
	 * @param bool         $is_customer_note Optional. Whether this is a customer-visible note. Default false.
	 *                                                         (Currently not implemented with specific visibility for subscriptions).
	 * @return int|false Comment ID on success, false on failure or if $subscription_ref is invalid/0.
	 */
	public static function add_subscription_note( $subscription_ref, $note, $is_customer_note = false ) {
		$post_id = 0;
		if ( is_numeric( $subscription_ref ) && $subscription_ref > 0 ) {
			$post_id = absint( $subscription_ref );
		} elseif ( $subscription_ref instanceof \WP_Post ) {
			$post_id = absint( $subscription_ref->ID );
		}

		if ( 0 === $post_id ) {
			return false; // Cannot add a comment to a non-existent post.
		}

		$subscription_post = get_post( $post_id );
		if ( ! $subscription_post || self::POST_TYPE !== $subscription_post->post_type ) {
			return false;
		}

		// Determine comment author.
		$comment_author = __( 'OhMyLMS System', 'ohmylms' );
		// Generate a unique system email, e.g., system@mydomain.com (removes www.).
		$comment_author_email = 'system@' . preg_replace( '#^www\.#', '', strtolower( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) );
		$comment_author_url   = '';
		$user_id              = 0; // System comment by default.

		// If a logged-in user is performing an action that generates a note.
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			// Attribute the note only to a user who can edit this subscription.
			if ( current_user_can( 'edit_post', $post_id ) ) {
				$comment_author       = $current_user->display_name;
				$comment_author_email = $current_user->user_email;
				$comment_author_url   = $current_user->user_url;
				$user_id              = $current_user->ID;
			}
		}

		$commentdata = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $comment_author,
			'comment_author_email' => $comment_author_email,
			'comment_author_url'   => $comment_author_url,
			'comment_content'      => wp_kses_post( $note ), // Sanitize note content for display.
			'comment_type'         => 'subscription_note',
			'comment_parent'       => 0,
			'comment_approved'     => 1,
			'comment_agent'        => 'OhMyLMS/' . ( defined( 'OHMYLMS_VERSION' ) ? OHMYLMS_VERSION : '1.0.0' ),
			'comment_date'         => current_time( 'mysql' ),       // Local time for display.
			'comment_date_gmt'     => current_time( 'mysql', true ), // GMT for storage consistency.
			'user_id'              => $user_id,
		);

		$comment_id = wp_insert_comment( $commentdata );

		// Future: if $is_customer_note functionality is added for subscriptions.
		// if ( $comment_id && $is_customer_note ) {
		// add_comment_meta( $comment_id, '_is_customer_note', 1 );
		// }.

		return $comment_id;
	}

	/**
	 * Mark a subscription as active.
	 * Schedules renewal if a future next payment date is set and not past a fixed end date.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return bool True if status updated successfully, false otherwise.
	 */
	public static function mark_subscription_active( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		$updated         = self::update_subscription_status( $subscription_id, 'active' );
		if ( ! $updated ) {
			return false;
		}
		self::add_subscription_note( $subscription_id, __( 'Subscription status changed to Active.', 'ohmylms' ) );
		$next_payment_date_gmt = get_post_meta( $subscription_id, '_schedule_next_payment_date', true );
		$end_date_gmt          = get_post_meta( $subscription_id, '_schedule_end_date', true );

		if ( empty( $end_date_gmt ) || strtotime( $end_date_gmt ) > current_time( 'timestamp' ) ) {

			if ( ! empty( $next_payment_date_gmt ) ) {
				$next_payment_timestamp_gmt = strtotime( $next_payment_date_gmt );
				if ( $next_payment_timestamp_gmt > current_time( 'timestamp' ) && ( empty( $end_date_gmt ) || $next_payment_timestamp_gmt <= strtotime( $end_date_gmt ) ) ) {
					self::clear_scheduled_renewal_actions( $subscription_id );
					self::schedule_renewal_payment( $subscription_id, $next_payment_timestamp_gmt );
				}
			} else {
				$last_payment_date = get_post_meta( $subscription_id, '_last_payment_date', true );
				$membership_id     = get_post_meta( $subscription_id, '_membership_id', true );
				$period            = get_post_meta( $membership_id, '_subscription_period', true );
				$interval          = (int) get_post_meta( $membership_id, '_subscription_period_interval', true );

				$base_ts = $last_payment_date ? strtotime( $last_payment_date ) : current_time( 'timestamp' );

				try {
					$next_payment_dt = ( new \DateTime( "@$base_ts" ) )->setTimezone( new \DateTimeZone( 'GMT' ) );
					$next_payment_dt->modify( "+{$interval} {$period}" );
					$next_payment_timestamp_gmt = $next_payment_dt->getTimestamp();
					// Only schedule if before end date.
					if ( empty( $end_date_gmt ) || $next_payment_timestamp_gmt <= strtotime( $end_date_gmt ) ) {
						self::clear_scheduled_renewal_actions( $subscription_id );
						self::schedule_renewal_payment( $subscription_id, $next_payment_timestamp_gmt );
					}
				} catch ( \Exception $e ) {
					self::add_subscription_note( $subscription_id, __( 'Error calculating next payment date for scheduling.', 'ohmylms' ) );
				}
			}
		}
		return $updated;
	}

	/**
	 * Mark a subscription as on-hold.
	 * Clears any scheduled renewal payments.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return bool True if status updated successfully, false otherwise.
	 */
	public static function mark_subscription_on_hold( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		$updated         = self::update_subscription_status( $subscription_id, 'on-hold' );

		if ( $updated ) {
			self::add_subscription_note( $subscription_id, __( 'Subscription status changed to On-Hold.', 'ohmylms' ) );
			self::clear_scheduled_renewal_actions( $subscription_id ); // Stop scheduled payments.
			do_action( 'ohmylms_subscription_on_hold', $subscription_id );
		}
		return $updated;
	}

	/**
	 * Mark a subscription as pending cancellation.
	 * The subscription will be cancelled at the end of the current paid term.
	 * Scheduled renewals are typically left in place, as the renewal job will handle the final cancellation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return bool True if status updated successfully, false otherwise.
	 */
	public static function mark_subscription_pending_cancellation( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		$updated         = self::update_subscription_status( $subscription_id, 'pending-cancel' );

		if ( $updated ) {
			self::add_subscription_note( $subscription_id, __( 'Subscription marked for cancellation. It will be cancelled at the end of the current term.', 'ohmylms' ) );
			do_action( 'ohmylms_subscription_pending_cancellation', $subscription_id );
		}
		return $updated;
	}

	/**
	 * Mark a subscription as cancelled.
	 * Clears scheduled renewals and sets an end date to the current time (GMT).
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id The subscription ID.
	 * @param int $user_id User identifier.
	 * @return bool True if status updated successfully, false otherwise.
	 */
	public static function mark_subscription_cancelled( $subscription_id, $user_id = 0 ) {
		$subscription_id = absint( $subscription_id );
		$updated         = self::update_subscription_status( $subscription_id, 'cancelled' );
		if ( $updated ) {
			$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
			if ( $user_id ) {
				$user = get_user_by( 'id', $user_id );
				if ( $user && $user->exists() ) {
					self::add_subscription_note( $subscription_id, sprintf( /* translators: %s: text value. */
					__( 'Subscription cancelled by user: %s.', 'ohmylms' ), $user->display_name ) );
				} else {
					self::add_subscription_note( $subscription_id, __( 'Subscription status changed to Cancelled.', 'ohmylms' ) );
				}
			} else {
				self::add_subscription_note( $subscription_id, __( 'Subscription status changed to Cancelled.', 'ohmylms' ) );
			}
			self::clear_scheduled_renewal_actions( $subscription_id );
			update_post_meta( $subscription_id, '_end_date', current_time( 'mysql' ) ); // Record cancellation date as end date (GMT).
			do_action( 'ohmylms_subscription_cancelled', $subscription_id );
		}
		return $updated;
	}

	/**
	 * Mark a subscription as expired.
	 * Clears scheduled renewals. Typically called when a fixed-term subscription ends.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return bool True if status updated successfully, false otherwise.
	 */
	public static function mark_subscription_expired( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		$updated         = self::update_subscription_status( $subscription_id, 'expired' );
		update_post_meta( $subscription_id, '_schedule_next_payment_date', 0 );

		if ( $updated ) {
			self::add_subscription_note( $subscription_id, __( 'Subscription status changed to Expired.', 'ohmylms' ) );
			self::clear_scheduled_renewal_actions( $subscription_id );
			do_action( 'ohmylms_subscription_expired', $subscription_id );
		}
		return $updated;
	}

	/**
	 * Generic public method to update a subscription's post_status and a corresponding '_status' meta field.
	 * Validates the status slug before updating.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $subscription_id The subscription ID.
	 * @param string $new_status_slug The new status slug (e.g., 'active', 'on-hold', without 'ohmylms' prefix).
	 * @return bool True on success, false on failure.
	 */
	public static function update_subscription_status( $subscription_id, $new_status_slug ) {
		$subscription_id = absint( $subscription_id );
		if ( empty( $subscription_id ) ) {
			return false; // Cannot update status for an invalid ID.
		}

		$new_status_slug_clean = sanitize_key( $new_status_slug );
		$full_status           = 'ohmylms-' . $new_status_slug_clean;

		// Verify if it's a valid registered post status.
		// get_post_stati() returns registered status objects.
		$registered_statuses = get_post_stati( array(), 'objects' );
		if ( ! array_key_exists( $full_status, $registered_statuses ) ) {
			// translators: %s: Invalid status slug.
			self::add_subscription_note( $subscription_id, sprintf( __( 'Attempted to set invalid subscription status: %s', 'ohmylms' ), esc_html( $full_status ) ) );
			return false;
		}

		$update_args = array(
			'ID'          => $subscription_id,
			'post_status' => $full_status,
		);
		// wp_update_post returns post ID on success, 0 or WP_Error on failure.
		$updated_post_id_or_error = wp_update_post( $update_args, true ); // true for WP_Error return on failure.

		if ( ! is_wp_error( $updated_post_id_or_error ) && $updated_post_id_or_error > 0 ) {
			update_post_meta( $subscription_id, '_status', $new_status_slug_clean ); // Store the clean slug for easier checks.
			// Action hook for specific status change, e.g., ohmylms_subscription_status_active.
			do_action( 'ohmylms_subscription_status_' . $new_status_slug_clean, $subscription_id );
			// Generic status change hook.
			do_action( 'ohmylms_subscription_status_changed', $subscription_id, $new_status_slug_clean, $full_status );
			return true;
		} else {
			// Log error if wp_update_post failed.
			$error_message = is_wp_error( $updated_post_id_or_error ) ? $updated_post_id_or_error->get_error_message() : __( 'Unknown error updating post.', 'ohmylms' );
			// translators: %1$s Target status, %2$s Error message.
			self::add_subscription_note( $subscription_id, sprintf( __( 'Failed to update subscription status to %1$s. Error: %2$s', 'ohmylms' ), esc_html( $full_status ), esc_html( $error_message ) ) );
			return false;
		}
	}

	/**
	 * Calculate subscription dates based on current time and membership plan settings.
	 * Returns an array with start date, trial end date, next payment date, and end date.
	 *
	 * @since 1.0.0
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object for which the subscription is created.
	 * @param mixed                        $membership_id Membership identifier.
	 * @return array An associative array with 'start_date', 'trial_end_date', 'next_payment_date', and 'end_date'.
	 */
	private static function calculate_subscription_dates( $order, $membership_id ) {
		$subscription_length          = get_post_meta( $membership_id, '_subscription_length', true );
		$subscription_period          = get_post_meta( $membership_id, '_subscription_period', true );
		$subscription_period_interval = get_post_meta( $membership_id, '_subscription_period_interval', true );

		$timezone              = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( ohmylms_timezone_string() );
		$now                   = ( new \DateTime( 'now', $timezone ) )->format( 'Y-m-d H:i:s' );
		$start_date_obj        = new \DateTime( $now, $timezone );
		$next_payment_date_obj = '';
		$end_date_obj          = '';
		$next_payment_date     = '';
		$end_date              = '';

		// Map period to DateInterval code.
		$interval_map = array(
			'day'   => 'D',
			'week'  => 'W',
			'month' => 'M',
			'year'  => 'Y',
		);
		$period_code  = isset( $interval_map[ $subscription_period ] ) ? $interval_map[ $subscription_period ] : 'M';
		$interval     = max( 1, $subscription_period_interval );
		$length       = max( 1, $subscription_length );

		$date_format = 'Y-m-d H:i:s';
		if ( '0' == $subscription_length ) {
			$end_date = '';

			$next_payment_date_obj = clone $start_date_obj;
			$next_payment_date_obj->add( new \DateInterval( 'P' . $interval . $period_code ) );
			$next_payment_date = $next_payment_date_obj->format( $date_format );
		} elseif ( '1' === $subscription_length ) {
			$next_payment_date = '';

			$end_date_obj = clone $start_date_obj;
			$end_date_obj->add( new \DateInterval( 'P' . ( $interval * $length ) . $period_code ) );
			$end_date = $end_date_obj->format( $date_format );
		} else {
			$end_date_obj = clone $start_date_obj;
			$end_date_obj->add( new \DateInterval( 'P' . ( $interval * $length ) . $period_code ) );
			$end_date = $end_date_obj->format( $date_format );

			$next_payment_date_obj = clone $start_date_obj;
			$next_payment_date_obj->add( new \DateInterval( 'P' . $interval . $period_code ) );
			$next_payment_date = $next_payment_date_obj->format( $date_format );
		}
		return array(
			'start_date'        => $start_date_obj->format( $date_format ),
			'next_payment_date' => $next_payment_date,
			'end_date'          => $end_date,
		);
	}

	/**
	 * Schedule the next renewal payment using Action Scheduler.
	 * Clears any existing identical scheduled actions for this subscription first.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $subscription_id The subscription ID.
	 * @param int  $next_payment_gmt_ts GMT timestamp for the next payment.
	 * @param bool $is_retry Optional. Whether this is a retry attempt (for logging purposes). Default false.
	 */
	public static function schedule_renewal_payment( $subscription_id, $next_payment_gmt_ts, $is_retry = false ) {
		$subscription_id     = absint( $subscription_id );
		$next_payment_gmt_ts = absint( $next_payment_gmt_ts );
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			self::add_subscription_note( $subscription_id, __( 'Action Scheduler is not available. Cannot schedule renewal payment.', 'ohmylms' ) );
			return;
		}
		self::clear_scheduled_renewal_actions( $subscription_id );
		$action_id = as_schedule_single_action(
			$next_payment_gmt_ts,
			self::RENEWAL_ACTION_HOOK,
			array( 'subscription_id' => $subscription_id ),
			'ohmylms-subscription-renewals'
		);
		if ( $action_id ) {
			$date_format     = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i:s' );
			$formatted_gmt   = gmdate( $date_format, $next_payment_gmt_ts );
			$formatted_local = wp_date( $date_format, $next_payment_gmt_ts );
			if ( empty( get_post_meta( $subscription_id, '_schedule_next_payment_date', true ) ) ) {
				self::add_subscription_note(
					$subscription_id,
					sprintf(
						/* translators: %1$s: text value; %2$s: text value; %3$s: text value. */
						__( 'Subscription scheduled to expire on %1$s GMT (%2$s site time). Action ID: %3$s.', 'ohmylms' ),
						$formatted_gmt,
						$formatted_local,
						$action_id
					)
				);
			} else {
				$note = $is_retry
					? sprintf(
						/* translators: %1$s: text value; %2$s: text value; %3$s: text value. */
						__( 'Subscription renewal payment retry scheduled for %1$s GMT (%2$s site time). Action ID: %3$s.', 'ohmylms' ),
						$formatted_gmt,
						$formatted_local,
						$action_id
					)
					: sprintf(
						/* translators: %1$s: text value; %2$s: text value; %3$s: text value. */
						__( 'Next subscription renewal payment scheduled for %1$s GMT (%2$s site time). Action ID: %3$s.', 'ohmylms' ),
						$formatted_gmt,
						$formatted_local,
						$action_id
					);

				self::add_subscription_note( $subscription_id, $note );
			}
			update_post_meta( $subscription_id, '_scheduled_renewal_action_id', $action_id );
		} else {
			self::add_subscription_note( $subscription_id, sprintf( /* translators: %s: text value. */
			__( 'Failed to schedule next subscription renewal payment for %s GMT.', 'ohmylms' ), gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_payment_gmt_ts ) ) );
		}
	}

	/**
	 * Clear any scheduled PENDING renewal actions for a specific subscription.
	 * Uses Action Scheduler's functions to find and unschedule actions.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id The subscription ID.
	 */
	public static function clear_scheduled_renewal_actions( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		if ( ! function_exists( 'as_unschedule_action_by_id' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return; // Action Scheduler functions not available.
		}

		// Query for pending actions matching the hook and arguments.
		$args       = array(
			'hook'   => self::RENEWAL_ACTION_HOOK,
			'args'   => array( 'subscription_id' => $subscription_id ),
			'group'  => 'ohmylms-subscription-renewals',
			'status' => \ActionScheduler_Store::STATUS_PENDING, // Only clear pending actions.
		);
		$action_ids = as_get_scheduled_actions( $args, 'ids' ); // Get an array of action IDs.

		if ( ! empty( $action_ids ) ) {
			foreach ( $action_ids as $action_id_to_cancel ) {
				as_unschedule_action_by_id( $action_id_to_cancel );
			}
		}
		// Clear the stored action ID meta, as it might now be invalid or relate to a completed/failed action.
		// This is important to prevent trying to cancel an already run/failed action by its old ID.
		delete_post_meta( $subscription_id, '_scheduled_renewal_action_id' );
	}

	/**
	 * Process a subscription renewal payment. This method is hooked to Action Scheduler.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id The ID of the subscription to renew.
	 */
	public static function process_subscription_renewal( $subscription_id ) {
		$subscription_id = absint( $subscription_id );
		$subscription    = ohmylms_ecommerce_get_subscription( $subscription_id );
		// Validate subscription existence and post type.
		if ( ! $subscription || self::POST_TYPE !== get_post_field( 'post_type', $subscription_id ) ) {
			return;
		}

		$status = $subscription->get_status();

		// Handle pending cancellation immediately.
		if ( 'pending-cancel' === $status ) {
			self::add_subscription_note( $subscription_id, __( 'Subscription was pending cancellation. Processing final cancellation.', 'ohmylms' ) );
			self::mark_subscription_cancelled( $subscription_id );
			return;
		}

		// Only process if active or on-hold.
		if ( ! in_array( $status, array( 'active', 'on-hold' ), true ) ) {
			self::add_subscription_note( $subscription_id, sprintf( /* translators: %s: text value. */
			__( 'Subscription renewal skipped. Current status is "%s".', 'ohmylms' ), esc_html( $status ) ) );
			return;
		}

		// Check if subscription ended.
		$end_date          = get_post_meta( $subscription_id, '_schedule_end_date', true );
		$next_payment_date = get_post_meta( $subscription_id, '_schedule_next_payment_date', true );

		if ( $end_date && empty( $next_payment_date ) ) {
			if ( time() >= strtotime( $end_date ) ) {
				self::add_subscription_note( $subscription_id, __( 'Subscription has reached its prepaid end date. Marking as expired.', 'ohmylms' ) );
				self::mark_subscription_expired( $subscription_id );
			} else {
				self::add_subscription_note( $subscription_id, __( 'Prepaid subscription active until end date. No renewal scheduled.', 'ohmylms' ) );
			}
			return;
		}
		if ( $end_date && $next_payment_date && strtotime( $next_payment_date ) > strtotime( $end_date ) ) {
			self::add_subscription_note( $subscription_id, __( 'Subscription has reached its end date. Marking as expired instead of renewing.', 'ohmylms' ) );
			self::mark_subscription_expired( $subscription_id );
			return;
		}

		$original_order_id = $subscription->get_original_order_id();
		$student_id        = absint( get_post_meta( $subscription_id, '_student_id', true ) );
		$membership_id     = absint( get_post_meta( $subscription_id, '_membership_id', true ) );
		$gateway_id        = sanitize_text_field( get_post_meta( $subscription_id, '_payment_gateway_id', true ) );

		if ( ! $original_order_id || ! $student_id || ! $membership_id || ! $gateway_id ) {
			self::add_subscription_note( $subscription_id, __( 'Missing critical data (Order ID, Student ID, Membership ID, or Gateway ID). Cannot process renewal.', 'ohmylms' ) );
			self::mark_subscription_on_hold( $subscription_id );
			return;
		}

		$original_order = ohmylms_ecommerce_get_order( $original_order_id );
		if ( ! $original_order ) {
			self::add_subscription_note( $subscription_id, sprintf( /* translators: %d: number. */
			__( 'Original order #%d not found. Cannot process renewal.', 'ohmylms' ), $original_order_id ) );
			self::mark_subscription_on_hold( $subscription_id );
			return;
		}

		// Determine renewal amount.
		$amount_to_charge = (float) get_post_meta( $subscription_id, '_recurring_amount', true );

		$tax_amount       = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $original_order->get_tax_rate(), array( 'total' => $amount_to_charge ) );
		$amount_to_charge = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $amount_to_charge;
		if ( $amount_to_charge <= 0 ) {
			$amount_to_charge = (float) get_post_meta( $membership_id, '_regular_price', true );
		}
		if ( $amount_to_charge <= 0 ) {
			self::add_subscription_note( $subscription_id, __( 'Renewal amount invalid or zero. Cannot process payment.', 'ohmylms' ) );
			self::mark_subscription_on_hold( $subscription_id );
			return;
		}

		// Validate payment gateway.
		$available_gateways = ecommerce()->gateways()->get_payment_gateways();

		$gateway = $available_gateways[ $gateway_id ];
		if ( ! isset( $available_gateways[ $gateway_id ] ) ) {
			self::add_subscription_note( $subscription_id, sprintf( /* translators: %s: text value. */
			__( 'Payment gateway "%s" not found or inactive.', 'ohmylms' ), esc_html( $gateway_id ) ) );
			self::mark_subscription_on_hold( $subscription_id );
			return;
		}

		$gateway = $available_gateways[ $gateway_id ];
		// Create renewal order.
		try {
			$renewal_order = new \OhMyLMS\Commerce\Data\Order();
			$renewal_order->set_tax_amount( $tax_amount['tax_amount'] );
			$renewal_order->set_tax_rate( $original_order->get_tax_rate() );

			$renewal_order->set_total( $amount_to_charge );
			$renewal_order->set_payment_method( $gateway_id );
			$renewal_order->set_payment_method_title( $gateway->get_title() );
			$renewal_order->set_student_id( $student_id );
			$renewal_order->set_email( $original_order->get_email() );
			$renewal_order->set_first_name( $original_order->get_first_name() );
			$renewal_order->set_last_name( $original_order->get_last_name() );
			$renewal_order->set_address( $original_order->get_address() );
			$renewal_order->set_country( $original_order->get_country() );
			$renewal_order->set_parent_id( $original_order_id );
			$renewal_order->set_order_version( OHMYLMS_VERSION );

			foreach ( $original_order->get_items( 'line_item' ) as $item ) {
				$product_id = $item->get_course_id();
				if ( ! $product_id ) {
					continue;
				}
				$membership = get_post( $membership_id );
				if ( ! $membership || 'ohmylms-membership' !== $membership->post_type ) {
					continue;
				}

				if ( ohmylms_is_pro() ) {
					$product = ohmylms_get_membership( $product_id );
				}
				if ( ! $product ) {
					continue;
				}

				$renewal_item = new OrderItemCourse();
				$renewal_item->set_course_id( $item->get_course_id() );
				$renewal_item->set_quantity( $item->get_quantity() );
				$renewal_item->set_name( $item->get_name() );
				$renewal_item->set_total( $product->get_regular_price() );
				$renewal_item->set_subtotal( $product->get_regular_price() );
				$renewal_order->add_item( $renewal_item );
			}

			$renewal_order_id = $renewal_order->save();
			self::add_subscription_note( $subscription_id, sprintf( /* translators: %d: number. */
			__( 'Renewal order #%d created.', 'ohmylms' ), $renewal_order_id ) );
			$renewal_order->add_order_note( __( 'Order created for subscription renewal.', 'ohmylms' ) );
			update_post_meta( $subscription_id, '_last_renewal_order_id', $renewal_order_id );
		} catch ( \Exception $e ) {
			self::add_subscription_note( $subscription_id, sprintf( /* translators: %s: text value. */
			__( 'Failed to create renewal order: %s', 'ohmylms' ), $e->getMessage() ) );
			return;
		}

		// Check if gateway supports recurring payment.
		if ( ! method_exists( $gateway, 'process_recurring_payment' ) ) {
			self::add_subscription_note( $subscription_id, sprintf( /* translators: %s: text value. */
			__( 'Gateway "%s" missing recurring payment method.', 'ohmylms' ), esc_html( $gateway->get_title() ) ) );
			self::mark_subscription_on_hold( $subscription_id );
			return;
		}

		$payment_result = $gateway->process_recurring_payment( $original_order_id, $renewal_order_id, $amount_to_charge, $subscription_id, $student_id );
		if ( is_wp_error( $payment_result ) || ( isset( $payment_result['result'] ) && 'failure' === $payment_result['result'] ) || ( isset( $payment_result['status'] ) && 'failed' === $payment_result['status'] ) ) {
			$error_message = is_wp_error( $payment_result ) ? $payment_result->get_error_message() : ( $payment_result['message'] ?? __( 'Unknown payment error.', 'ohmylms' ) );
			self::add_subscription_note( $subscription_id, sprintf( /* translators: %s: text value. */
			__( 'Renewal payment failed: %s', 'ohmylms' ), esc_html( $error_message ) ) );
			self::mark_subscription_on_hold( $subscription_id );
			$renewal_order->set_status( 'failed' );
			$renewal_order->save();
			do_action( 'ohmylms_subscription_renewal_payment_failed', $subscription_id, $error_message );
			return;
		}

		$transaction_id = isset( $payment_result['transaction_id'] ) ? sanitize_text_field( $payment_result['transaction_id'] ) : __( 'N/A', 'ohmylms' );
		self::add_subscription_note( $subscription_id, sprintf( /* translators: %s: text value. */
		__( 'Renewal payment successful. Transaction ID: %s', 'ohmylms' ), $transaction_id ) );

		if ( ! empty( $payment_result['transaction_id'] ) ) {
			update_post_meta( $subscription_id, '_last_renewal_transaction_id', $transaction_id );
		}

		update_post_meta( $subscription_id, '_last_payment_date', current_time( 'mysql' ) );

		$base_ts  = $next_payment_date ? strtotime( $next_payment_date ) : strtotime( current_time( 'mysql' ) );
		$period   = get_post_meta( $membership_id, '_subscription_period', true );
		$interval = (int) get_post_meta( $membership_id, '_subscription_period_interval', true );

		try {
			$next_payment_dt = ( new \DateTime( "@$base_ts" ) )->setTimezone( new \DateTimeZone( 'GMT' ) );
			$next_payment_dt->modify( "+{$interval} {$period}" );
			$new_next_payment_date = $next_payment_dt->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			self::add_subscription_note( $subscription_id, __( 'Error calculating next payment date. Marking subscription on-hold.', 'ohmylms' ) );
			self::mark_subscription_on_hold( $subscription_id );
			return;
		}

		if ( $end_date ) {
			$end_ts      = strtotime( $end_date );
			$new_next_ts = strtotime( $new_next_payment_date );
			if ( $new_next_ts >= $end_ts ) {
				self::add_subscription_note(
					$subscription_id,
					__( 'Final payment completed. No further payments will be scheduled. Subscription will remain active until the end date.', 'ohmylms' )
				);
				delete_post_meta( $subscription_id, '_schedule_next_payment_date' );
				self::mark_subscription_active( $subscription_id );
				do_action(
					'ohmylms_subscription_renewal_payment_completed',
					$subscription_id,
					$amount_to_charge,
					$transaction_id
				);
				return;
			}
		}

		update_post_meta( $subscription_id, '_schedule_next_payment_date', $new_next_payment_date );
		update_post_meta( $renewal_order_id, '_subscription_renewal_id', $subscription_id );
		self::add_subscription_note(
			$subscription_id,
			sprintf( /* translators: %s: text value. */
			__( 'Next payment date set to %s.', 'ohmylms' ), $new_next_payment_date )
		);

		self::mark_subscription_active( $subscription_id );

		do_action(
			'ohmylms_subscription_renewal_payment_completed',
			$subscription_id,
			$amount_to_charge,
			$transaction_id
		);
	}


	/**
	 * Initialize hooks for the subscription manager.
	 * This method should be called once from the main plugin file or a relevant setup routine
	 * (e.g., on `plugins_loaded` or `init` action) to ensure the Action Scheduler hook is registered.
	 *
	 * @since 1.0.0
	 */
	public static function init_hooks() {
		add_action( self::RENEWAL_ACTION_HOOK, array( __CLASS__, 'process_subscription_renewal' ) );
	}

	/**
	 * Copy all meta from the order post to the subscription post, skipping protected meta keys.
	 *
	 * @param int $order_id Order identifier.
	 * @param int $subscription_id Subscription identifier.
	 */
	private static function copy_order_meta_to_subscription( $order_id, $subscription_id ) {
		$order_id        = absint( $order_id );
		$subscription_id = absint( $subscription_id );
		if ( ! $order_id || ! $subscription_id ) {
			return;
		}
		$order_meta = get_post_meta( $order_id );
		if ( ! empty( $order_meta ) && is_array( $order_meta ) ) {
			foreach ( $order_meta as $meta_key => $meta_values ) {
				if ( strpos( $meta_key, '_subscription' ) === 0 || strpos( $meta_key, '_ohmylms' ) === 0 ) {
					continue;
				}
				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $subscription_id, $meta_key, $meta_value );
				}
			}
		}
	}
}
