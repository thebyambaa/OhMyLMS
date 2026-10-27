<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the plugin's Composer PSR-4 class/file mapping.
/**
 * Read-only course level information from curriculum placement and saved skills.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Learning\CourseProgram;

defined( 'ABSPATH' ) || exit;

/** Keep subject level scales separate and never infer a level from a course title. */
final class CourseLevels {

	/**
	 * Read curriculum grades/levels and the course's saved skill bands.
	 *
	 * @param int $course_id Course ID.
	 * @return array Curriculum labels, subject skill ranges and unplaced skill count.
	 */
	public static function get( $course_id ) {
		$curriculum = array();
		foreach ( Placement::item_ids( $course_id ) as $item_id ) {
			$item = Items::get( $item_id );
			foreach ( array_merge( Items::path( $item_id ), $item ? array( $item ) : array() ) as $node ) {
				if ( in_array( $node['item_type'], array( 'grade', 'level' ), true ) ) {
					$curriculum[ (int) $node['id'] ] = $node['name'];
				}
			}
		}
		$program  = CourseProgram::editor( $course_id )['draft'];
		$term_ids = array_values( array_unique( array_map( 'intval', array_column( $program['outcomes'] ?? array(), 'term_id' ) ) ) );
		update_termmeta_cache( $term_ids );
		$skills = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, Taxonomy::NAME );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$subject  = (string) get_term_meta( $term_id, Comparison::SUBJECT_META, true );
			$profile  = SubjectProfiles::get( $subject );
			$skills[] = array(
				'subject' => '' !== $subject ? $subject : $profile['label'],
				'profile' => $profile['key'],
				'levels'  => $profile['levels'],
				'from'    => (int) get_term_meta( $term_id, Comparison::GRADE_FROM_META, true ),
				'to'      => (int) get_term_meta( $term_id, Comparison::GRADE_TO_META, true ),
			);
		}
		return self::summarize( $skills, array_values( $curriculum ) );
	}

	/**
	 * Summarize validated skill bands without combining unrelated subject scales.
	 *
	 * @param array[]  $skills Subject, profile key, ordered level labels, from and to ordinals.
	 * @param string[] $curriculum Curriculum grade/level labels.
	 * @return array Read-only summary; unknown or invalid bands remain undetermined.
	 */
	public static function summarize( array $skills, array $curriculum = array() ) {
		$ranges   = array();
		$unplaced = 0;
		foreach ( $skills as $skill ) {
			$from = (int) $skill['from'];
			$to   = (int) $skill['to'];
			if ( 0 === $to ) {
				$to = $from;
			}
			if ( 1 > $from || $to < $from || $to > count( $skill['levels'] ) ) {
				++$unplaced;
				continue;
			}
			$key = $skill['profile'] . ':' . $skill['subject'];
			if ( ! isset( $ranges[ $key ] ) ) {
				$ranges[ $key ] = array(
					'subject' => $skill['subject'],
					'from'    => $from,
					'to'      => $to,
					'levels'  => $skill['levels'],
				);
			} else {
				$ranges[ $key ]['from'] = min( $ranges[ $key ]['from'], $from );
				$ranges[ $key ]['to']   = max( $ranges[ $key ]['to'], $to );
			}
		}
		foreach ( $ranges as &$range ) {
			$first          = $range['levels'][ $range['from'] - 1 ];
			$last           = $range['levels'][ $range['to'] - 1 ];
			$range['label'] = $first === $last ? $first : $first . ' – ' . $last;
			unset( $range['levels'] );
		}
		unset( $range );
		return array(
			'curriculum'      => array_values( array_unique( $curriculum ) ),
			'skills'          => array_values( $ranges ),
			'unplaced_skills' => $unplaced,
		);
	}
}
