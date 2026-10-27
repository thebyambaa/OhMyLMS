<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class-only skill levels reconstructed from attributed assessments.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Family\Schema as FamilySchema;
use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\ClassManagement;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Performance\Levels;

defined( 'ABSPATH' ) || exit;

/** Never reads a learner's global skill state or unclassified attempts. */
final class TeacherReports {

	/**
	 * Read one roster page and replay class assessment outcomes in the selected period.
	 *
	 * @param int $class_id Class ID.
	 * @param int $days Allowlisted lookback window.
	 * @param int $page Roster page.
	 * @return array
	 */
	public static function read( $class_id, $days = 30, $page = 1 ) {
		$class = Access::classroom( absint( $class_id ), true );
		Access::require_access( $class && TeacherWorkspace::available() );
		$days     = in_array( (int) $days, array( 7, 30, 90 ), true ) ? (int) $days : 30;
		$page     = min( 10000, max( 1, absint( $page ) ) );
		$students = ClassManagement::students( $class_id, '', $page );
		$more     = count( $students ) > 20;
		$students = array_slice( $students, 0, 20 );
		$enabled  = \OhMyLMS\Assessment\Schema::ready() && FamilySchema::enabled();
		$since    = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		global $wpdb;
		foreach ( $students as &$student ) {
			$student['skills']   = array();
			$student['overflow'] = false;
			if ( ! $enabled || ! (int) $class['school_id'] ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh class/recipient authorization; at most 1001 events for each of 20 roster rows.
			$events = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT k.term_id,k.detail FROM %i k JOIN %i p ON p.source_kind='quiz' AND p.source_id=k.source_id AND p.learner_user_id=k.student_id JOIN %i a ON a.id=p.assignment_id AND a.class_id=p.class_id AND a.school_id=p.school_id AND a.status='active' JOIN %i r ON r.assignment_id=a.id AND r.student_user_id=k.student_id WHERE k.source_type='quiz' AND k.student_id=%d AND p.origin='school' AND p.class_id=%d AND p.school_id=%d AND p.completed_at>=%s ORDER BY k.id LIMIT 1001",
					\OhMyLMS\Assessment\Schema::table( 'skill_level_events' ),
					FamilySchema::table( 'activity' ),
					Schema::table( 'learning_assignments' ),
					Schema::table( 'assignment_recipients' ),
					(int) $student['user_id'],
					(int) $class_id,
					(int) $class['school_id'],
					$since
				),
				ARRAY_A
			);
			if ( count( $events ) > 1000 ) {
				$student['overflow'] = true;
				continue;
			}
			foreach ( $events as $event ) {
				$detail = json_decode( $event['detail'], true );
				if ( ! is_array( $detail ) || ! isset( $detail['right'], $detail['wrong'] ) || (int) $detail['right'] + (int) $detail['wrong'] < 1 ) {
					continue;
				}
				$term_id = (int) $event['term_id'];
				$term    = get_term( $term_id, \OhMyLMS\Skills\Taxonomy::NAME );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$prior                         = $student['skills'][ $term_id ]['level'] ?? 'not-started';
				$level                         = Levels::assessment( $prior, 0 === (int) $detail['wrong'] );
				$student['skills'][ $term_id ] = array(
					'name'        => $term->name,
					'level'       => $level,
					'label'       => Levels::label( $level ),
					'assessments' => ( $student['skills'][ $term_id ]['assessments'] ?? 0 ) + 1,
				);
			}
		}
		unset( $student );
		return compact( 'students', 'more', 'days', 'page', 'enabled' );
	}
}
