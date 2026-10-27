<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Parent dashboard skill comparison: children side by side, and one child against the class.
 *
 * Callers pass only children the guardian is currently approved for (ParentWorkspace::children()).
 * Only display levels and class aggregates are returned: no answer logs, and no learner other than
 * the guardian's own children is ever named or identifiable. A class aggregate needs at least
 * UnitReport::MIN_PEERS classmates besides the child, is shown only as a coarse band, and can be
 * switched off with a filter.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Performance\Snapshot;

use OhMyLMS\Schools\Schema as SchoolSchema;

defined( 'ABSPATH' ) || exit;

/** Read-only comparison data for the parent dashboard. */
final class FamilyComparison {

	/** Most classmates read for one average, to keep a page cheap. */
	const COHORT_LIMIT = 100;

	/**
	 * Comparison for approved children.
	 *
	 * @param array $children  Rows with student_user_id, display_name and school_id.
	 * @param int   $school_id When exactly one child is compared, the school whose class to average (0 for none).
	 * @return array{children:array,skills:array<int,string>,rows:array,class:bool}
	 */
	public static function build( array $children, $school_id = 0 ) {
		$people = array();
		foreach ( $children as $child ) {
			$id = (int) $child['student_user_id'];
			if ( $id && ! isset( $people[ $id ] ) ) {
				$people[ $id ] = array(
					'id'   => $id,
					'name' => (string) $child['display_name'],
				);
			}
		}
		$levels = array();
		$names  = array();
		foreach ( $people as $id => $person ) {
			$terms = Snapshot::started_terms( $id );
			foreach ( Snapshot::student( $id, $terms ) as $term => $entry ) {
				$levels[ $id ][ $term ] = $entry['key'];
				if ( ! isset( $names[ $term ] ) ) {
					$skill = Taxonomy::describe( $term );
					if ( $skill ) {
						$names[ $term ] = $skill['name'];
					}
				}
			}
		}
		$skills  = UnitReport::started_skills( $levels, $names );
		$cohorts = array();
		if ( 1 === count( $people ) && (int) $school_id ) {
			$id     = (int) key( $people );
			$cohort = self::cohort( $id, (int) $school_id, $skills );
			if ( $cohort ) {
				$cohorts[ $id ] = $cohort;
			}
		}
		return array(
			'children' => array_values( $people ),
			'skills'   => array_intersect_key( $names, array_flip( $skills ) ),
			'rows'     => UnitReport::compare( $skills, $levels, $cohorts ),
			'class'    => (bool) $cohorts,
		);
	}

	/**
	 * Display levels per skill for the child's active classmates in one school.
	 *
	 * @param int   $child_id  Approved child.
	 * @param int   $school_id School the guardian link belongs to.
	 * @param int[] $skills    Skills to read.
	 * @return array<int,string[]> Skill => levels of every classmate except the child. Empty when not permitted or too small.
	 */
	private static function cohort( $child_id, $school_id, array $skills ) {
		global $wpdb;
		/**
		 * Whether a guardian may see an anonymous class average beside their child.
		 *
		 * @param bool $allowed   Default true.
		 * @param int  $school_id School ID.
		 * @param int  $child_id  Child user ID.
		 */
		if ( ! $skills || ! apply_filters( 'ohmylms_guardian_class_comparison', true, $school_id, $child_id ) ) {
			return array();
		}
		$members = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh roster-scoped read; never cached.
			$wpdb->prepare(
				'SELECT DISTINCT cm.user_id FROM %i cm
				JOIN %i mine ON mine.class_id=cm.class_id AND mine.user_id=%d AND mine.role=\'student\' AND mine.status=\'active\'
				JOIN %i c ON c.id=cm.class_id AND c.school_id=%d AND c.status=\'active\'
				JOIN %i sm ON sm.school_id=c.school_id AND sm.user_id=cm.user_id AND sm.role=\'student\' AND sm.status=\'active\'
				WHERE cm.role=\'student\' AND cm.status=\'active\' LIMIT %d',
				SchoolSchema::table( 'class_memberships' ),
				SchoolSchema::table( 'class_memberships' ),
				$child_id,
				SchoolSchema::table( 'classes' ),
				$school_id,
				SchoolSchema::table( 'school_memberships' ),
				self::COHORT_LIMIT
			)
		);
		// Classmates only: the child is left out so the average never contains (or is shifted by) their own level.
		$members = array_values( array_diff( array_map( 'intval', $members ), array( (int) $child_id ) ) );
		if ( count( $members ) < UnitReport::MIN_PEERS ) {
			return array();
		}
		$cohort  = array_fill_keys( $skills, array() );
		$display = Snapshot::students( $members, $skills );
		foreach ( $members as $member ) {
			foreach ( $skills as $skill ) {
				$cohort[ $skill ][] = $display[ $member ][ $skill ]['key'] ?? 'not-started';
			}
		}
		return $cohort;
	}
}
