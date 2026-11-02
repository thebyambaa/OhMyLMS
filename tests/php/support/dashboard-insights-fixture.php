<?php
/**
 * Extra tagged fixtures for the course health, insights, activity and site health widgets, on top of
 * tests/php/support/dashboard-fixture.php (call dashfx_create() first):
 *
 * - course reviews on Course One: S1 rates 5, S2 rates 4, S7 (school B, not enrolled) rates 1, and an
 *   unapproved 1 from S3 that never counts;
 * - a skill `<tag> Fractions`: S1-S5 (class A1) at Attempted, S6 (class A2) at Mastered;
 * - two paid orders: 100 two days ago, 50 thirty-five days ago.
 *
 * dashinsfx_delete() removes what dashfx_delete() does not (skill states and the skill term); reviews and
 * orders go with the tagged posts.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Skills\Taxonomy;

/**
 * Add the extra fixtures.
 *
 * @param array $fx Fixtures of dashfx_create().
 * @return array The fixtures with `reviews`, `skill` and `orders_paid`.
 * @throws RuntimeException When a row cannot be written.
 */
function dashinsfx_extend( array $fx ) {
	global $wpdb;
	$tag = $fx['tag'];
	$s   = $fx['students'];
	$ago = static function ( $days ) {
		return wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	};

	$fx['reviews'] = array();
	foreach ( array(
		array( 1, 5, '1' ),
		array( 2, 4, '1' ),
		array( 7, 1, '1' ),
		array( 3, 1, '0' ),
	) as $review ) {
		$id = wp_insert_comment(
			array(
				'comment_post_ID'  => $fx['course_1'],
				'user_id'          => $s[ $review[0] ],
				'comment_author'   => $tag . ' s' . $review[0],
				'comment_content'  => $tag . ' review',
				'comment_type'     => 'review',
				'comment_approved' => $review[2],
			)
		);
		if ( ! $id ) {
			throw new RuntimeException( 'Could not create a review.' );
		}
		add_comment_meta( $id, 'rating', $review[1], true );
		$fx['reviews'][] = (int) $id;
	}

	$fx['skill'] = 0;
	if ( AssessmentSchema::ready() && taxonomy_exists( Taxonomy::NAME ) ) {
		$term = wp_insert_term( $tag . ' Fractions', Taxonomy::NAME );
		if ( is_wp_error( $term ) ) {
			throw new RuntimeException( 'Could not create the skill: ' . esc_html( $term->get_error_message() ) );
		}
		$fx['skill'] = (int) $term['term_id'];
		foreach ( array( 1, 2, 3, 4, 5, 6 ) as $i ) {
			$row = array(
				'student_id' => $s[ $i ],
				'term_id'    => $fx['skill'],
				'level'      => 6 === $i ? 'mastered' : 'attempted',
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			);
			if ( false === $wpdb->insert( AssessmentSchema::table( 'student_skill_state' ), $row ) ) {
				throw new RuntimeException( 'Could not write a skill state: ' . esc_html( $wpdb->last_error ) );
			}
		}
	}

	$fx['orders_paid'] = array(
		dashfx_post( $tag, 'ohmylms-order', 'paid order now', 'ohmylms-completed', $ago( 2 ) ),
		dashfx_post( $tag, 'ohmylms-order', 'paid order before', 'ohmylms-completed', $ago( 35 ) ),
	);
	update_post_meta( $fx['orders_paid'][0], '_order_total', '100' );
	update_post_meta( $fx['orders_paid'][1], '_order_total', '50' );
	return $fx;
}

/**
 * Remove the extra fixtures of a tag (before dashfx_delete(), which removes the users).
 *
 * @param string $tag Tag.
 * @return void
 */
function dashinsfx_delete( $tag ) {
	global $wpdb;
	$like  = $wpdb->esc_like( $tag ) . '%';
	$users = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE user_login LIKE %s', $wpdb->users, $like ) ) );
	if ( $users && AssessmentSchema::ready() ) {
		$wpdb->query( 'DELETE FROM ' . AssessmentSchema::table( 'student_skill_state' ) . ' WHERE student_id IN (' . implode( ',', $users ) . ')' );
	}
	if ( taxonomy_exists( Taxonomy::NAME ) ) {
		foreach ( (array) get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'name__like' => $tag,
				'fields'     => 'ids',
			)
		) as $term ) {
			wp_delete_term( (int) $term, Taxonomy::NAME );
		}
	}
	$posts = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_title LIKE %s', $wpdb->posts, $like ) ) );
	foreach ( $posts as $post ) {
		foreach ( get_comments(
			array(
				'post_id' => $post,
				'status'  => 'all',
				'fields'  => 'ids',
			)
		) as $comment ) {
			wp_delete_comment( (int) $comment, true );
		}
	}
}
