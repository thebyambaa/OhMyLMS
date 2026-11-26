<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * "Needs attention" summary on the teacher's home (My classes) in the hub.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Counts for the classes a teacher may already open. The caller passes the rows
 * {@see TeacherWorkspace::classes()} returned, so every class here was authorized by the access service;
 * nothing in this class widens that list.
 */
final class TeacherHome {

	/**
	 * Days ahead that count as "due soon".
	 */
	const SOON_DAYS = 7;

	/**
	 * Per-class figures and the classes that need a look first.
	 *
	 * @param array[] $classes Authorized class rows, each with `id`, `name` and `subject`.
	 * @return array{totals: array<string,int>, rows: array<int,array<string,int>>, attention: array[]}
	 */
	public static function summary( array $classes ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', wp_list_pluck( $classes, 'id' ) ) ) ) );
		$out = array(
			'totals'    => array(
				'classes'  => count( $ids ),
				'students' => 0,
				'overdue'  => 0,
				'due_soon' => 0,
			),
			'rows'      => array(),
			'attention' => array(),
		);
		if ( ! $ids || Schema::VERSION !== get_option( 'ohmylms_school_schema' ) ) {
			return $out;
		}
		foreach ( $ids as $id ) {
			$out['rows'][ $id ] = array(
				'students'    => 0,
				'assignments' => 0,
				'overdue'     => 0,
				'due_soon'    => 0,
			);
		}
		global $wpdb;
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$now = Service::now();
		$end = gmdate( 'Y-m-d H:i:s', time() + self::SOON_DAYS * DAY_IN_SECONDS );

		$students = $wpdb->get_results( $wpdb->prepare( "SELECT class_id, COUNT(*) AS total FROM %i WHERE role='student' AND status='active' AND class_id IN ($in) GROUP BY class_id", array_merge( array( Schema::table( 'class_memberships' ) ), $ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Ids were authorized by TeacherWorkspace::classes().
		foreach ( (array) $students as $row ) {
			$out['rows'][ (int) $row['class_id'] ]['students'] = (int) $row['total'];
		}

		$work = $wpdb->get_results( $wpdb->prepare( "SELECT a.class_id, COUNT(DISTINCT a.id) AS assignments, SUM(a.due_at IS NOT NULL AND a.due_at < %s AND r.assignment_id IS NOT NULL AND r.completed_at IS NULL) AS overdue, SUM(a.due_at IS NOT NULL AND a.due_at >= %s AND a.due_at < %s AND r.assignment_id IS NOT NULL AND r.completed_at IS NULL) AS due_soon FROM %i a LEFT JOIN %i r ON r.assignment_id = a.id WHERE a.status='active' AND a.class_id IN ($in) GROUP BY a.class_id", array_merge( array( $now, $now, $end, Schema::table( 'learning_assignments' ), Schema::table( 'assignment_recipients' ) ), $ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Ids were authorized by TeacherWorkspace::classes().
		foreach ( (array) $work as $row ) {
			$id                                = (int) $row['class_id'];
			$out['rows'][ $id ]['assignments'] = (int) $row['assignments'];
			$out['rows'][ $id ]['overdue']     = (int) $row['overdue'];
			$out['rows'][ $id ]['due_soon']    = (int) $row['due_soon'];
		}

		foreach ( $classes as $class ) {
			$id  = (int) $class['id'];
			$row = $out['rows'][ $id ] ?? null;
			if ( ! $row ) {
				continue;
			}
			$out['totals']['students'] += $row['students'];
			$out['totals']['overdue']  += $row['overdue'];
			$out['totals']['due_soon'] += $row['due_soon'];
			if ( $row['overdue'] > 0 ) {
				$out['attention'][] = array(
					'id'     => $id,
					'name'   => (string) $class['name'],
					'weight' => $row['overdue'],
					'text'   => sprintf( /* translators: %d: number of overdue student assignments. */ _n( '%d overdue assignment to follow up', '%d overdue assignments to follow up', $row['overdue'], 'ohmylms' ), $row['overdue'] ),
				);
			} elseif ( 0 === $row['students'] ) {
				$out['attention'][] = array(
					'id'     => $id,
					'name'   => (string) $class['name'],
					'weight' => 0,
					'text'   => __( 'No students yet. Invite your class.', 'ohmylms' ),
				);
			}
		}
		usort(
			$out['attention'],
			static function ( $a, $b ) {
				return $b['weight'] <=> $a['weight'];
			}
		);
		return $out;
	}
}
