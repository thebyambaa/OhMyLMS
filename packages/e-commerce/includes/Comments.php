<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

/**
 * Plugin comments implementation.
 */
class Comments {

	/**
	 * Initialize Comments.
	 */
	public function __construct() {
		add_filter( 'comments_clauses', array( __CLASS__, 'exclude_order_comments' ), 10, 1 );
		add_filter( 'comments_clauses', array( __CLASS__, 'exclude_subscription_comments' ), 10, 1 );
	}

	/**
	 * Exclude order comments for Comments.
	 *
	 * @param mixed $clauses Clauses.
	 */
	public static function exclude_order_comments( $clauses ) {
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type != 'order_note' ";
		return $clauses;
	}

	/**
	 * Exclude subscription comments for Comments.
	 *
	 * @param mixed $clauses Clauses.
	 */
	public static function exclude_subscription_comments( $clauses ) {
		$clauses['where'] .= ( $clauses['where'] ? ' AND ' : '' ) . " comment_type != 'subscription_note' ";
		return $clauses;
	}
}
