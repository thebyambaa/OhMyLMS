<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Comments
 *
 * Handle comments (reviews and order notes).
 *
 * @package  OhMyLMS\Hooks
 *
 * @version 1.0.0
 */

namespace OhMyLMS;

use OhMyLMS\Data\Course;

defined( 'ABSPATH' ) || exit;

/**
 * Comments class.
 */
class CourseComment {

	/**
	 * Hook in methods.
	 */
	public static function init() {
		// Rating posts.
		add_filter( 'comments_open', array( __CLASS__, 'comments_open' ), 10, 2 );
		add_action( 'preprocess_comment', array( __CLASS__, 'update_comment_type' ), 1 );
		add_action( 'comment_post', array( __CLASS__, 'add_comment_rating' ), 1 );
		add_action( 'comment_moderation_recipients', array( __CLASS__, 'comment_moderation_recipients' ), 10, 2 );
		// Clear transients.
		add_action( 'wp_update_comment_count', array( __CLASS__, 'clear_transients' ) );
		add_filter( 'wp_count_comments', array( __CLASS__, 'wp_count_comments' ), 10, 2 );
		add_action( 'wp_insert_comment', array( __CLASS__, 'delete_comments_count_cache' ) );
		add_action( 'wp_set_comment_status', array( __CLASS__, 'delete_comments_count_cache' ) );
	}

	/**
	 * See if comments are open.
	 *
	 * @since  3.1.0
	 * @param bool $open Whether the current post is open for comments.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public static function comments_open( $open, $post_id ) {
		if ( OHMYLMS_COURSE_CPT === get_post_type( $post_id ) && ! post_type_supports( OHMYLMS_COURSE_CPT, 'comments' ) ) {
			$open = false;
		}
		return $open;
	}

	/**
	 * Rating field for comments.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public static function add_comment_rating( $comment_id ) {
		// phpcs:disable
		if ( isset( $_POST['rating'], $_POST['comment_post_ID'] ) && OHMYLMS_COURSE_CPT === get_post_type( absint( $_POST['comment_post_ID'] ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $_POST['rating'] || $_POST['rating'] > 5 || $_POST['rating'] < 0 ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return;
			}
			add_comment_meta( $comment_id, 'rating', intval( $_POST['rating'] ), true ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$post_id = isset( $_POST['comment_post_ID'] ) ? absint( $_POST['comment_post_ID'] ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $post_id ) {
				self::clear_transients( $post_id );
			}
		}
		// phpcs:enable
	}

	/**
	 * Modify recipient of review email.
	 *
	 * @param array $emails Emails.
	 * @param int   $comment_id Comment ID.
	 * @return array
	 */
	public static function comment_moderation_recipients( $emails, $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( $comment && OHMYLMS_COURSE_CPT === get_post_type( $comment->comment_post_ID ) ) {
			$emails = array( get_option( 'admin_email' ) );
		}

		return $emails;
	}

	/**
	 * Ensure product average rating and review count is kept up to date.
	 *
	 * @param int $post_id Post ID.
	 * @throws \Exception When the operation cannot be completed.*/
	public static function clear_transients( $post_id ) {
		if ( OHMYLMS_COURSE_CPT === get_post_type( $post_id ) ) {
			$course = ohmylms_get_course( $post_id );
			$course->set_rating_counts( self::get_rating_counts_for_product( $course ) );
			$course->set_average_rating( self::get_average_rating_for_product( $course ) );
			$course->set_review_count( self::get_review_count_for_product( $course ) );
			$course->save();
		}
	}

	/**
	 * Delete comments count cache whenever there is
	 * new comment or the status of a comment changes. Cache
	 * will be regenerated next time WC_Comments::wp_count_comments()
	 * is called.
	 */
	public static function delete_comments_count_cache() {
		delete_transient( 'ohmylms_count_comments' );
	}

	/**
	 * Remove order notes, webhook delivery logs, and product reviews from wp_count_comments().
	 *
	 * @since  2.2
	 * @param object $stats Comment stats.
	 * @param int    $post_id Post ID.
	 * @return object
	 */
	public static function wp_count_comments( $stats, $post_id ) {
		global $wpdb;

		if ( 0 === $post_id ) {
			$stats = get_transient( 'ohmylms_count_comments' );

			if ( ! $stats ) {
				$stats = array(
					'total_comments' => 0,
					'all'            => 0,
				);

				$count = $wpdb->get_results(
					"
					SELECT comment_approved, COUNT(*) AS num_comments
					FROM {$wpdb->comments}
					LEFT JOIN {$wpdb->posts} ON comment_post_ID = {$wpdb->posts}.ID
					WHERE comment_type NOT IN ('action_log', 'order_note', 'webhook_delivery') AND {$wpdb->posts}.post_type NOT IN ('ohmylms-course')
					GROUP BY comment_approved
					",
					ARRAY_A
				);

				$approved = array(
					'0'            => 'moderated',
					'1'            => 'approved',
					'spam'         => 'spam',
					'trash'        => 'trash',
					'post-trashed' => 'post-trashed',
				);

				foreach ( (array) $count as $row ) {
					// Don't count post-trashed toward totals.
					if ( ! in_array( $row['comment_approved'], array( 'post-trashed', 'trash', 'spam' ), true ) ) {
						$stats['all']            += $row['num_comments'];
						$stats['total_comments'] += $row['num_comments'];
					} elseif ( ! in_array( $row['comment_approved'], array( 'post-trashed', 'trash' ), true ) ) {
						$stats['total_comments'] += $row['num_comments'];
					}
					if ( isset( $approved[ $row['comment_approved'] ] ) ) {
						$stats[ $approved[ $row['comment_approved'] ] ] = $row['num_comments'];
					}
				}

				foreach ( $approved as $key ) {
					if ( empty( $stats[ $key ] ) ) {
						$stats[ $key ] = 0;
					}
				}

				$stats = (object) $stats;
				set_transient( 'ohmylms_count_comments', $stats );
			}
		}

		return $stats;
	}

	/**
	 * Make sure WP displays avatars for comments with the `review` type.
	 *
	 * @since  2.3
	 * @param array $comment_types Comment types.
	 * @return array
	 */
	public static function add_avatar_for_review_comment_type( $comment_types ) {
		return array_merge( $comment_types, array( 'review' ) );
	}

	/**
	 * Add Product Reviews filter for `review` comment type.
	 *
	 * @since 6.0.0
	 *
	 * @param array $comment_types Array of comment type labels keyed by their name.
	 *
	 * @return array
	 */
	public static function add_review_comment_filter( array $comment_types ): array {
		$comment_types['review'] = __( 'Course Reviews', 'ohmylms' );
		return $comment_types;
	}

	/**
	 * Determine if a review is from a verified owner at submission.
	 *
	 * @param int $comment_id Comment ID.
	 * @return bool
	 */
	public static function add_comment_purchase_verification( $comment_id ) {
		$comment  = get_comment( $comment_id );
		$verified = false;
		if ( OHMYLMS_COURSE_CPT === get_post_type( $comment->comment_post_ID ) ) {
			$verified = wc_customer_bought_product( $comment->comment_author_email, $comment->user_id, $comment->comment_post_ID );
			add_comment_meta( $comment_id, 'verified', (int) $verified, true );
		}
		return $verified;
	}

	/**
	 * Get product rating for a product. Please note this is not cached.
	 *
	 * @since 3.0.0
	 * @return float
	 * @param mixed $course Course.
	 */
	public static function get_average_rating_for_product( &$course ) {
		global $wpdb;

		$count = $course->get_rating_count();

		if ( $count ) {
			$ratings = $wpdb->get_var(
				$wpdb->prepare(
					"
				SELECT SUM(meta_value) FROM $wpdb->commentmeta
				LEFT JOIN $wpdb->comments ON $wpdb->commentmeta.comment_id = $wpdb->comments.comment_ID
				WHERE meta_key = 'rating'
				AND comment_post_ID = %d
				AND comment_approved = '1'
				AND meta_value > 0
					",
					$course->get_id()
				)
			);
			$average = number_format( $ratings / $count, 2, '.', '' );
		} else {
			$average = 0;
		}

		return $average;
	}

	/**
	 * Utility function for getting review counts for multiple products in one query. This is not cached.
	 *
	 * @since 5.0.0
	 *
	 * @param array $course_ids Array of product IDs.
	 *
	 * @return array
	 */
	public static function get_review_counts_for_product_ids( $course_ids ) {
		global $wpdb;

		if ( empty( $course_ids ) ) {
			return array();
		}

		$course_id_string_placeholder = substr( str_repeat( ',%s', count( $course_ids ) ), 1 );

		$review_counts = $wpdb->get_results(
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Ignored for allowing interpolation in IN query.
			$wpdb->prepare(
				"
					SELECT comment_post_ID as product_id, COUNT( comment_post_ID ) as review_count
					FROM $wpdb->comments
					WHERE
						comment_parent = 0
						AND comment_post_ID IN ( $course_id_string_placeholder )
						AND comment_approved = '1'
						AND comment_type in ( 'review', '', 'comment' )
					GROUP BY product_id
				",
				$course_ids
			),
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared.
			ARRAY_A
		);

		// Convert to key value pairs.
		$counts = array_replace( array_fill_keys( $course_ids, 0 ), array_column( $review_counts, 'review_count', 'product_id' ) );

		return $counts;
	}

	/**
	 * Get product review count for a product (not replies). Please note this is not cached.
	 *
	 * @since 3.0.0
	 * @param Course $course Product instance.
	 * @return int
	 */
	public static function get_review_count_for_product( &$course ) {
		$counts = self::get_review_counts_for_product_ids( array( $course->get_id() ) );

		return $counts[ $course->get_id() ];
	}

	/**
	 * Get product rating count for a product. Please note this is not cached.
	 *
	 * @since 3.0.0
	 * @param Course $course Product instance.
	 * @return int[]
	 */
	public static function get_rating_counts_for_product( &$course ) {
		global $wpdb;

		$counts     = array();
		$raw_counts = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT meta_value, COUNT( * ) as meta_value_count FROM $wpdb->commentmeta
			LEFT JOIN $wpdb->comments ON $wpdb->commentmeta.comment_id = $wpdb->comments.comment_ID
			WHERE meta_key = 'rating'
			AND comment_post_ID = %d
			AND comment_approved = '1'
			AND meta_value > 0
			GROUP BY meta_value
				",
				$course->get_id()
			)
		);

		foreach ( $raw_counts as $count ) {
			$counts[ $count->meta_value ] = absint( $count->meta_value_count ); // WPCS: slow query ok.
		}

		return $counts;
	}

	/**
	 * Update comment type of product reviews.
	 *
	 * @since 3.5.0
	 * @param array $comment_data Comment data.
	 * @return array
	 */
	public static function update_comment_type( $comment_data ) {
		// phpcs:disable
		if ( ! is_admin() && isset( $_POST['comment_post_ID'], $comment_data['comment_type'] ) && self::is_default_comment_type( $comment_data['comment_type'] ) && OHMYLMS_COURSE_CPT === get_post_type( absint( $_POST['comment_post_ID'] ) ) ) { // WPCS: input var ok, CSRF ok.
			$comment_data['comment_type']     = 'review';
			$comment_data['comment_approved'] = 0;
		}
		return $comment_data;
		// phpcs:enable
	}
	/**
	 * Determines if a comment is of the default type.
	 *
	 * Prior to WordPress 5.5, '' was the default comment type.
	 * As of 5.5, the default type is 'comment'.
	 *
	 * @since 4.3.0
	 * @param string $comment_type Comment type.
	 * @return bool
	 */
	private static function is_default_comment_type( $comment_type ) {
		return ( '' === $comment_type || 'comment' === $comment_type );
	}
}

CourseComment::init();
