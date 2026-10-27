<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The teacher's "who needs help now" view: stuck learners grouped by what to reteach.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

defined( 'ABSPATH' ) || exit;

/**
 * A learner is stuck on a skill when their recent answers on it had errors (a `help` step in guidance). The
 * cause is often a weaker prerequisite, so learners are grouped by the prerequisite to reteach: one mini-lesson
 * then helps several of them. A stuck skill with no weak prerequisite is its own group (reteach the skill).
 *
 * `group()` is pure and unit tested (tests/php/performance-unit.php); `for_students()` reads guidance.
 */
final class HelpNow {

	/** Most learners read for one view. */
	const MAX_STUDENTS = 200;

	/**
	 * Group stuck learners by the prerequisite to reteach.
	 *
	 * A skill with several weak prerequisites goes to the one that the most learners share, so a single
	 * mini-lesson covers as many as possible (ties: lowest ID).
	 *
	 * @param array[] $learners Each: id, name, exam (name, days_left or null), help (list of skill {id, name}, prerequisites [{id, name}]).
	 * @return array[] Groups, biggest first: reteach {id, name}, kind (prerequisite or skill), skills [{id, name}], students [{id, name, exam}].
	 */
	public static function group( array $learners ) {
		// How many learners each candidate prerequisite would help.
		$reach = array();
		foreach ( $learners as $learner ) {
			$seen = array();
			foreach ( (array) ( $learner['help'] ?? array() ) as $help ) {
				foreach ( (array) ( $help['prerequisites'] ?? array() ) as $prerequisite ) {
					$seen[ (int) $prerequisite['id'] ] = true;
				}
			}
			foreach ( array_keys( $seen ) as $id ) {
				$reach[ $id ] = ( $reach[ $id ] ?? 0 ) + 1;
			}
		}
		$groups = array();
		foreach ( $learners as $learner ) {
			foreach ( (array) ( $learner['help'] ?? array() ) as $help ) {
				$target = $help['skill'];
				$kind   = 'skill';
				$best   = null;
				foreach ( (array) ( $help['prerequisites'] ?? array() ) as $prerequisite ) {
					$id = (int) $prerequisite['id'];
					if ( null === $best || array( -$reach[ $id ], $id ) < array( -$reach[ (int) $best['id'] ], (int) $best['id'] ) ) {
						$best = $prerequisite;
					}
				}
				if ( $best ) {
					$target = $best;
					$kind   = 'prerequisite';
				}
				$key = $kind . ':' . (int) $target['id'];
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'reteach'  => array(
							'id'   => (int) $target['id'],
							'name' => (string) $target['name'],
						),
						'kind'     => $kind,
						'skills'   => array(),
						'students' => array(),
					);
				}
				$groups[ $key ]['skills'][ (int) $help['skill']['id'] ] = array(
					'id'   => (int) $help['skill']['id'],
					'name' => (string) $help['skill']['name'],
				);
				$groups[ $key ]['students'][ (int) $learner['id'] ]     = array(
					'id'   => (int) $learner['id'],
					'name' => (string) $learner['name'],
					'exam' => $learner['exam'] ?? null,
				);
			}
		}
		foreach ( $groups as &$group ) {
			$group['skills']   = array_values( $group['skills'] );
			$group['students'] = array_values( $group['students'] );
			usort(
				$group['students'],
				static function ( $left, $right ) {
					return array( $left['exam']['days_left'] ?? PHP_INT_MAX, $left['name'], $left['id'] ) <=> array( $right['exam']['days_left'] ?? PHP_INT_MAX, $right['name'], $right['id'] );
				}
			);
		}
		unset( $group );
		$groups = array_values( $groups );
		usort(
			$groups,
			static function ( $left, $right ) {
				$soon = static function ( $group ) {
					return min(
						array_map(
							static function ( $student ) {
								return $student['exam']['days_left'] ?? PHP_INT_MAX;
							},
							$group['students']
						)
					);
				};
				return array( -count( $left['students'] ), $soon( $left ), $left['reteach']['id'] ) <=> array( -count( $right['students'] ), $soon( $right ), $right['reteach']['id'] );
			}
		);
		return $groups;
	}

	/**
	 * Stuck learners among these, grouped. The caller has already checked the viewer may see them.
	 *
	 * @param int[] $student_ids Authorized learner IDs.
	 * @return array students (how many were read), stuck (how many are stuck), groups.
	 */
	public static function for_students( array $student_ids ) {
		$ids      = array_slice( array_values( array_unique( array_filter( array_map( 'intval', $student_ids ) ) ) ), 0, self::MAX_STUDENTS );
		$learners = array();
		foreach ( $ids as $id ) {
			$guidance = Guidance::for_student( $id, 20, '' );
			$help     = array();
			foreach ( $guidance['stuck'] as $step ) {
				if ( 'help' === $step['type'] ) {
					$help[] = array(
						'skill'         => array(
							'id'   => (int) $step['skill']['id'],
							'name' => (string) $step['skill']['name'],
						),
						'prerequisites' => array_map(
							static function ( $prerequisite ) {
								return array(
									'id'   => (int) $prerequisite['id'],
									'name' => (string) $prerequisite['name'],
								);
							},
							$step['prerequisites']
						),
					);
				}
			}
			if ( ! $help ) {
				continue;
			}
			$user       = get_userdata( $id );
			$learners[] = array(
				'id'   => $id,
				'name' => $user ? $user->display_name : '#' . $id,
				'exam' => $guidance['exam'] ? array(
					'name'      => $guidance['exam']['syllabus']['name'],
					'days_left' => (int) $guidance['exam']['days_left'],
				) : null,
				'help' => $help,
			);
		}
		return array(
			'students' => count( $ids ),
			'stuck'    => count( $learners ),
			'groups'   => self::group( $learners ),
		);
	}
}
