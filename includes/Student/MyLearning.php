<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * What the learner is studying: their courses with real progress, and the collections they follow.
 *
 * Read-only. Progress comes from the same course outline the course page uses, so the figures
 * on both screens always agree. Roadmap courses, unpublished courses and removed courses are
 * never listed.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;


defined( 'ABSPATH' ) || exit;

/** Courses and followed collections for one learner. */
final class MyLearning {

	/**
	 * One page of the learner's enrolled courses, courses in progress first.
	 *
	 * @param int $student_id Learner ID.
	 * @param int $page 1-based page.
	 * @param int $per_page Page size, 1-24.
	 * @return array{items:array[],total:int,pages:int}
	 */
	public static function courses( $student_id, $page = 1, $per_page = 12 ) {
		global $wpdb;
		$student_id = (int) $student_id;
		$page       = max( 1, (int) $page );
		$per_page   = max( 1, min( 24, (int) $per_page ) );
		if ( ! $student_id ) {
			return array(
				'items' => array(),
				'total' => 0,
				'pages' => 1,
			);
		}
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The learner's own enrolments; not cached so a new enrolment shows at once.
			$wpdb->prepare(
				"SELECT e.course_id, e.progress FROM %i e JOIN %i p ON p.ID=e.course_id AND p.post_type=%s AND p.post_status='publish' WHERE e.user_id=%d AND e.status='enrolled' ORDER BY (e.progress='completed') ASC, e.start_date DESC, e.id DESC",
				$wpdb->prefix . 'ohmylms_user_enrollment',
				$wpdb->posts,
				OHMYLMS_COURSE_CPT,
				$student_id
			),
			ARRAY_A
		);
		$hidden = array_flip( LearnerCatalog::roadmap_course_ids() );
		$order  = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['course_id'];
			if ( ! isset( $order[ $id ] ) && ! isset( $hidden[ $id ] ) ) {
				$order[ $id ] = 'completed' === $row['progress'];
			}
		}
		$total = count( $order );
		$items = array();
		foreach ( array_slice( $order, ( $page - 1 ) * $per_page, $per_page, true ) as $id => $completed ) {
			$outline = CourseOutline::get( $id, $student_id );
			if ( is_wp_error( $outline ) ) {
				continue;
			}
			$items[] = array(
				'id'        => $id,
				'title'     => $outline['title'],
				'overview'  => Hub::course_url( $id ),
				'summary'   => $outline['summary'],
				'continue'  => $outline['continue'],
				'completed' => $completed,
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

}
