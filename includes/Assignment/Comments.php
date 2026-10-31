<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Comment threads between instructors and the learner on one assignment submission.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assignment;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * One thread per submission (`ohmylms_assignment_attempts` row), stored in
 * `{prefix}ohmylms_assignment_comments`. Only the submission's learner and people who can edit the
 * assignment read or write it; comments are plain text. Posting needs the assignment's comments switch
 * (`_enable_comments`); existing threads stay readable when it is off. Every new comment fires
 * `ohmylms_assignment_comment_added`, which sends the comment emails.
 */
final class Comments {

	const VERSION    = '1';
	const OPTION     = 'ohmylms_assignment_comments_schema';
	const MAX_LENGTH = 5000;

	/** Seconds a person waits between two comments on one submission. */
	const INTERVAL = 5;

	/**
	 * Register the learner form handler.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_ohmylms_assignment_comment', array( __CLASS__, 'handle_form' ) );
	}

	/**
	 * Comment table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_assignment_comments';
	}

	/**
	 * Create the table on first use.
	 *
	 * @return bool Whether the table exists.
	 */
	public static function install() {
		if ( get_option( self::OPTION ) === self::VERSION ) {
			return true;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta(
			"CREATE TABLE $table (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 submission_id bigint unsigned NOT NULL,
 assignment_id bigint unsigned NOT NULL,
 user_id bigint unsigned NOT NULL,
 role varchar(20) NOT NULL,
 body text NOT NULL,
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 KEY submission (submission_id,id),
 KEY assignment (assignment_id)
) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';'
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check right after dbDelta.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
			return false;
		}
		update_option( self::OPTION, self::VERSION, false );
		return true;
	}

	/**
	 * One submission row of an assignment.
	 *
	 * @param int $assignment_id Assignment ID.
	 * @param int $submission_id Submission ID.
	 * @return object|null
	 */
	public static function submission( $assignment_id, $submission_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One indexed row.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, user_id, course_id, assignment_id FROM %i WHERE id = %d AND assignment_id = %d', $wpdb->prefix . 'ohmylms_assignment_attempts', (int) $submission_id, (int) $assignment_id ) );
		return $row ? $row : null;
	}

	/**
	 * The role of a user in a submission's thread, or null when they may not see it.
	 *
	 * @param object $submission Submission row.
	 * @param int    $user_id    User.
	 * @return string|null `instructor` or `learner`.
	 */
	public static function role( $submission, $user_id ) {
		if ( $user_id < 1 ) {
			return null;
		}
		if ( user_can( $user_id, 'edit_post', (int) $submission->assignment_id ) ) {
			return 'instructor';
		}
		return (int) $submission->user_id === (int) $user_id ? 'learner' : null;
	}

	/**
	 * Whether posting is switched on for an assignment.
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return bool
	 */
	public static function enabled( $assignment_id ) {
		return rest_sanitize_boolean( get_post_meta( (int) $assignment_id, '_enable_comments', true ) );
	}

	/**
	 * Comments of a submission, oldest first.
	 *
	 * @param int $submission_id Submission ID.
	 * @param int $page          Page from 1.
	 * @param int $per_page      Page size.
	 * @return array{items:array[],total:int}
	 */
	public static function page( $submission_id, $page = 1, $per_page = 100 ) {
		if ( ! self::install() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Fresh thread read.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE submission_id = %d', self::table(), (int) $submission_id ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE submission_id = %d ORDER BY id ASC LIMIT %d OFFSET %d', self::table(), (int) $submission_id, (int) $per_page, ( max( 1, (int) $page ) - 1 ) * (int) $per_page ), ARRAY_A );
		// phpcs:enable
		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * One comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array|null Raw row.
	 */
	public static function get( $comment_id ) {
		if ( ! self::install() ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One indexed row.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), (int) $comment_id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * A comment as the API and templates show it. Authors are only an ID and a display name.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function shape( array $row ) {
		$author = get_userdata( (int) $row['user_id'] );
		return array(
			'id'            => (int) $row['id'],
			'submission_id' => (int) $row['submission_id'],
			'author'        => array(
				'id'           => (int) $row['user_id'],
				'display_name' => $author ? (string) $author->display_name : __( 'Deleted user', 'ohmylms' ),
			),
			'role'          => 'instructor' === $row['role'] ? 'instructor' : 'learner',
			'body'          => (string) $row['body'],
			'created_at'    => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $row['created_at'] . ' UTC' ) ),
		);
	}

	/**
	 * Whether a user may delete a comment: instructors any, others their own.
	 *
	 * @param array  $row  Raw comment.
	 * @param string $role Role of the user in the thread.
	 * @param int    $user_id User.
	 * @return bool
	 */
	public static function can_delete( array $row, $role, $user_id ) {
		return 'instructor' === $role || (int) $row['user_id'] === (int) $user_id;
	}

	/**
	 * Add a comment after the permission, switch, length and pace checks.
	 *
	 * @param object $submission Submission row.
	 * @param int    $user_id    Author.
	 * @param mixed  $body       Text.
	 * @return array|WP_Error The shaped comment.
	 */
	public static function add( $submission, $user_id, $body ) {
		$role = self::role( $submission, $user_id );
		if ( ! $role ) {
			return new WP_Error( 'ohmylms_assignment_cannot_comment', __( 'Sorry, you are not allowed to comment on this submission.', 'ohmylms' ), array( 'status' => $user_id ? 403 : 401 ) );
		}
		if ( ! self::enabled( (int) $submission->assignment_id ) ) {
			return new WP_Error( 'ohmylms_assignment_comments_disabled', __( 'Comments are switched off for this assignment.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$text = is_string( $body ) ? trim( sanitize_textarea_field( $body ) ) : '';
		if ( '' === $text || mb_strlen( $text ) > self::MAX_LENGTH ) {
			return new WP_Error(
				'ohmylms_assignment_invalid_comment',
				/* translators: %d: maximum length. */
				sprintf( __( 'Write a comment of 1 to %d characters.', 'ohmylms' ), self::MAX_LENGTH ),
				array(
					'status' => 422,
					'field'  => 'body',
				)
			);
		}
		if ( ! self::install() ) {
			return new WP_Error( 'ohmylms_assignment_comment_failed', __( 'The comment could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Pace check on the author's latest comment.
		$last = $wpdb->get_var( $wpdb->prepare( 'SELECT created_at FROM %i WHERE submission_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1', self::table(), (int) $submission->id, (int) $user_id ) );
		if ( $last && time() - strtotime( $last . ' UTC' ) < self::INTERVAL ) {
			return new WP_Error( 'ohmylms_assignment_comment_rate_limited', __( 'Wait a few seconds before posting another comment.', 'ohmylms' ), array( 'status' => 429 ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The thread owner writes its rows.
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'submission_id' => (int) $submission->id,
				'assignment_id' => (int) $submission->assignment_id,
				'user_id'       => (int) $user_id,
				'role'          => $role,
				'body'          => $text,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		$row      = $inserted ? self::get( (int) $wpdb->insert_id ) : null;
		if ( ! $row ) {
			return new WP_Error( 'ohmylms_assignment_comment_failed', __( 'The comment could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$comment = self::shape( $row );
		/**
		 * Fires after a comment was added to a submission thread.
		 *
		 * @param array  $comment       The comment (`id`, `author`, `role`, `body`, `created_at`).
		 * @param object $submission    Submission row (`id`, `user_id`, `course_id`, `assignment_id`).
		 * @param int    $assignment_id Assignment ID.
		 */
		do_action( 'ohmylms_assignment_comment_added', $comment, $submission, (int) $submission->assignment_id );
		return $comment;
	}

	/**
	 * Delete a comment of a thread.
	 *
	 * @param object $submission Submission row.
	 * @param int    $comment_id Comment ID.
	 * @param int    $user_id    User.
	 * @return array|WP_Error The deleted comment.
	 */
	public static function delete( $submission, $comment_id, $user_id ) {
		$role = self::role( $submission, $user_id );
		$row  = self::get( $comment_id );
		if ( ! $row || (int) $row['submission_id'] !== (int) $submission->id ) {
			return new WP_Error( 'ohmylms_assignment_comment_not_found', __( 'Comment not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! $role || ! self::can_delete( $row, $role, $user_id ) ) {
			return new WP_Error( 'ohmylms_assignment_cannot_delete_comment', __( 'Sorry, you are not allowed to delete this comment.', 'ohmylms' ), array( 'status' => $user_id ? 403 : 401 ) );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The thread owner deletes its rows.
		if ( ! $wpdb->delete( self::table(), array( 'id' => (int) $row['id'] ), array( '%d' ) ) ) {
			return new WP_Error( 'ohmylms_assignment_comment_failed', __( 'The comment could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return self::shape( $row );
	}

	/**
	 * Comment counts of several submissions.
	 *
	 * @param int[] $submission_ids Submission IDs.
	 * @return array<int,int>
	 */
	public static function counts( array $submission_ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $submission_ids ) ) ) );
		if ( ! $ids || ! self::install() ) {
			return array();
		}
		global $wpdb;
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders built for the ID list.
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT submission_id, COUNT(*) AS total FROM %i WHERE submission_id IN ({$holders}) GROUP BY submission_id", array_merge( array( self::table() ), $ids ) ) );
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->submission_id ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Remove the threads of submissions (used when submissions are removed).
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return void
	 */
	public static function delete_for_assignment( $assignment_id ) {
		if ( get_option( self::OPTION ) !== self::VERSION ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The thread owner deletes its rows.
		$wpdb->delete( self::table(), array( 'assignment_id' => (int) $assignment_id ), array( '%d' ) );
	}

	/**
	 * Learner comment form on the assignment page (`admin-post.php?action=ohmylms_assignment_comment`).
	 * Goes back to the submission with `ohmylms_comment=added` or the error code.
	 *
	 * @return void
	 */
	public static function handle_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified just below.
		$assignment_id = absint( $_POST['assignment_id'] ?? 0 );
		$submission_id = absint( $_POST['submission_id'] ?? 0 );
		$body          = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
		// phpcs:enable
		$back = (string) ohmylms_get_pretty_content_permalink( $assignment_id );
		$back = $back ? $back : home_url( '/' );
		if ( ! check_admin_referer( 'ohmylms_assignment_comment_' . $submission_id, 'ohmylms_comment_nonce' ) ) {
			return;
		}
		$submission = self::submission( $assignment_id, $submission_id );
		$result     = $submission ? self::add( $submission, get_current_user_id(), $body ) : new WP_Error( 'ohmylms_assignment_submission_not_found' );
		$status     = is_wp_error( $result ) ? $result->get_error_code() : 'added';
		wp_safe_redirect( add_query_arg( 'ohmylms_comment', rawurlencode( $status ), $back ) . '#ohmylms-submission-' . $submission_id );
		exit;
	}
}
