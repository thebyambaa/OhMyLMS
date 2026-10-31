<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * The other gradebook views: Roster × Assignments, Student transcript, Skill mastery and Org roll-up.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\Directory;
use OhMyLMS\Dashboard\MasteryRepository;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widgets\MasteryLevels;

defined( 'ABSPATH' ) || exit;

/**
 * Each view is built from cached course grids ({@see CourseGrid::for_scope()}) or cached itself with the
 * gradebook data version, and is bounded (constants below).
 */
final class Views {

	const MAX_ROSTER_COURSES   = 12;
	const MAX_ROSTER_COLUMNS   = 120;
	const MAX_ROSTER_LEARNERS  = 500;
	const MAX_TRANSCRIPT       = 20;
	const MAX_SKILLS           = 20;
	const MAX_MASTERY_LEARNERS = 1000;
	const MAX_ROLLUP_CLASSES   = 60;
	const MAX_ROLLUP_COURSES   = 12;
	const MAX_INDIVIDUAL_COURSE = 10;

	/**
	 * Roster × Assignments: one class's learners against the items of every course linked to the class.
	 *
	 * @param int $class_id Class (already checked against the scope).
	 * @return array
	 */
	public static function roster( $class_id ) {
		$class   = Repository::classes( array( (int) $class_id ) )[ (int) $class_id ];
		$scope   = Scope::classroom( $class['id'], $class['name'], $class['school_id'], $class['school_name'] );
		$members = (array) $scope->learner_ids();
		$names   = Repository::names( $members );
		$linked  = Directory::existing_courses( Directory::class_linked_courses( (int) $class_id ) );
		$courses = array();
		$columns = 0;
		$rows    = array();
		foreach ( array_slice( $linked, 0, self::MAX_ROSTER_COURSES ) as $course_id ) {
			$grid  = CourseGrid::for_scope( $course_id, $scope );
			$items = array_slice( $grid['items'], 0, max( 0, self::MAX_ROSTER_COLUMNS - $columns ) );
			if ( ! $items && $columns >= self::MAX_ROSTER_COLUMNS ) {
				break;
			}
			$columns  += count( $items );
			$courses[] = array(
				'id'      => (int) $course_id,
				'title'   => $grid['course']['title'],
				'items'   => $items,
				'average' => GradeMath::average( wp_list_pluck( wp_list_pluck( $grid['rows'], 'total' ), 'p' ) ),
			);
			foreach ( $grid['rows'] as $row ) {
				$rows[ $row['id'] ][ $course_id ] = array(
					'row'   => $row,
					'items' => $items,
				);
			}
		}
		$learners = array();
		foreach ( array_slice( $members, 0, self::MAX_ROSTER_LEARNERS ) as $user ) {
			$cells  = array();
			$totals = array();
			foreach ( $courses as $course ) {
				$entry = $rows[ $user ][ $course['id'] ] ?? null;
				if ( ! $entry ) {
					$totals[ (string) $course['id'] ] = null;
					continue;
				}
				$totals[ (string) $course['id'] ] = array(
					'percent' => $entry['row']['total']['p'],
					'letter'  => $entry['row']['total']['l'],
					'status'  => $entry['row']['status'],
					'source'  => $entry['row']['source'],
				);
				foreach ( $entry['items'] as $item ) {
					if ( isset( $entry['row']['cells'][ $item['id'] ] ) ) {
						$cells[ $course['id'] . ':' . $item['id'] ] = CourseGrid::expand_cell( $entry['row']['cells'][ $item['id'] ] );
					}
				}
			}
			$learners[] = array(
				'id'     => (int) $user,
				'name'   => $names[ $user ] ?? '',
				'totals' => (object) $totals,
				'cells'  => (object) $cells,
			);
		}
		usort(
			$learners,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] ) ?: $a['id'] <=> $b['id'];
			}
		);
		return array(
			'class'     => array(
				'id'          => $class['id'],
				'name'        => $class['name'],
				'school_id'   => $class['school_id'],
				'school_name' => $class['school_name'],
			),
			'courses'   => $courses,
			'learners'  => $learners,
			'truncated' => count( $linked ) > count( $courses ) || count( $members ) > self::MAX_ROSTER_LEARNERS,
			'scale'     => ScaleSettings::scale(),
		);
	}

	/**
	 * Student transcript: every enrolment of a learner in the scope's courses with totals and cells, and
	 * the learner's skill mastery. Cached per scope and learner.
	 *
	 * @param Scope $scope   Scope (already checked to contain the learner).
	 * @param int   $user_id Learner.
	 * @return array
	 */
	public static function transcript( Scope $scope, $user_id ) {
		return Cache::remember(
			'gradebook-transcript',
			$scope,
			new Period( Period::DEFAULT_DAYS ),
			static function () use ( $scope, $user_id ) {
				$allowed = $scope->course_ids();
				$courses = array_values(
					array_filter(
						Repository::learner_courses( $user_id ),
						static function ( $course ) use ( $allowed ) {
							return null === $allowed || in_array( $course, $allowed, true );
						}
					)
				);
				$visible    = CourseGrid::visible_classes( $scope );
				$enrolments = array();
				$classes    = array();
				foreach ( array_slice( $courses, 0, self::MAX_TRANSCRIPT ) as $course_id ) {
					$grid = CourseGrid::compute( $course_id, array( (int) $user_id ), $visible );
					$row  = CourseGrid::row( $grid, $user_id );
					if ( ! $row ) {
						continue;
					}
					foreach ( $grid['classes'] as $class ) {
						$classes[ (int) $class['id'] ] = $class;
					}
					$weights = $grid['weights']['course']['weights'];
					foreach ( $grid['weights']['overrides'] as $override ) {
						if ( $override['class_id'] === $row['total']['w'] ) {
							$weights = $override['weights'];
						}
					}
					$enrolments[] = array(
						'course'  => $grid['course'],
						'items'   => $grid['items'],
						'weights' => $weights,
						'row'     => CourseGrid::expand_row( $row, $grid['items'], $classes ),
					);
				}
				$names = Repository::names( array( (int) $user_id ) );
				return array(
					'learner'    => array(
						'id'   => (int) $user_id,
						'name' => $names[ (int) $user_id ] ?? '',
					),
					'enrolments' => $enrolments,
					'truncated'  => count( $courses ) > self::MAX_TRANSCRIPT,
					'mastery'    => self::learner_mastery( $user_id ),
					'scale'      => ScaleSettings::scale(),
				);
			},
			array(
				'learner' => (int) $user_id,
				'v'       => Version::current(),
			)
		);
	}

	/**
	 * A learner's skills with evidence, most recent first (at most 40).
	 *
	 * @param int $user_id Learner.
	 * @return array{skills:array,levels:array}
	 */
	private static function learner_mastery( $user_id ) {
		global $wpdb;
		$thresholds = MasteryLevels::thresholds();
		$skills     = array();
		if ( class_exists( AssessmentSchema::class ) && AssessmentSchema::ready() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded read, cached with the transcript.
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT term_id, mastery, evidence_count, last_evidence_at FROM %i WHERE student_id = %d AND evidence_count > 0 ORDER BY last_evidence_at DESC, term_id LIMIT 40', $wpdb->prefix . 'ohmylms_student_skill_state', (int) $user_id ), ARRAY_A );
			$rows  = is_array( $rows ) ? $rows : array();
			$terms = MasteryRepository::terms( array_map( 'intval', wp_list_pluck( $rows, 'term_id' ) ) );
			foreach ( $rows as $row ) {
				$term = $terms[ (int) $row['term_id'] ] ?? null;
				if ( ! $term ) {
					continue;
				}
				$percent  = max( 0.0, min( 100.0, (float) $row['mastery'] ) );
				$skills[] = $term + array(
					'percent' => round( $percent, 1 ),
					'level'   => MasteryLevels::level( $percent, true, $thresholds ),
				);
			}
		}
		return array(
			'skills' => $skills,
			'levels' => MasteryLevels::legend( $thresholds ),
		);
	}

	/**
	 * Skill mastery: learners × skills. Learners are a course's (audience filtered) or, without a course,
	 * the scope's learners (a school or class scope only).
	 *
	 * @param Scope $scope Scope.
	 * @param array $args  `course`, `audience`, `search`, `page`, `per_page`.
	 * @return array|\WP_Error
	 */
	public static function mastery( Scope $scope, array $args ) {
		global $wpdb;
		$course_id = (int) ( $args['course'] ?? 0 );
		$people    = array();
		if ( $course_id ) {
			$grid      = CourseGrid::for_scope( $course_id, $scope );
			$presented = CourseGrid::present(
				$grid,
				array(
					'audience' => $args['audience'] ?? 'all',
					'search'   => $args['search'] ?? '',
					'export'   => true,
				)
			);
			foreach ( $presented['rows'] as $row ) {
				$people[] = array(
					'id'      => $row['id'],
					'name'    => $row['name'],
					'source'  => $row['source'],
					'rosters' => $row['rosters'],
				);
			}
		} elseif ( $scope->is_all() ) {
			return new \WP_Error( 'ohmylms_gradebook_course_required', __( 'Choose a course, or a school or class, to compare skills.', 'ohmylms' ), array( 'status' => 400 ) );
		} else {
			$ids    = (array) $scope->learner_ids();
			$names  = Repository::names( $ids );
			$search = trim( (string) ( $args['search'] ?? '' ) );
			foreach ( $ids as $id ) {
				$name = $names[ $id ] ?? '';
				if ( '' === $search || false !== mb_stripos( $name, $search ) ) {
					$people[] = array(
						'id'      => $id,
						'name'    => $name,
						'source'  => '',
						'rosters' => array(),
					);
				}
			}
			usort(
				$people,
				static function ( $a, $b ) {
					return strnatcasecmp( $a['name'], $b['name'] ) ?: $a['id'] <=> $b['id'];
				}
			);
		}
		$total    = count( $people );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 50 ) ) );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$slice    = array_slice( $people, ( $page - 1 ) * $per_page, $per_page );

		$thresholds = MasteryLevels::thresholds();
		$skills     = MasteryRepository::top_skills( array_slice( wp_list_pluck( $people, 'id' ), 0, self::MAX_MASTERY_LEARNERS ), self::MAX_SKILLS );
		$states     = array();
		if ( $slice && $skills && AssessmentSchema::ready() ) {
			$query_args = array( $wpdb->prefix . 'ohmylms_student_skill_state' );
			$learner    = Scope::in_condition( 'student_id', wp_list_pluck( $slice, 'id' ), $query_args );
			$skill      = Scope::in_condition( 'term_id', wp_list_pluck( $skills, 'id' ), $query_args );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fragments use placeholders; one page of learners.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT student_id, term_id, mastery, evidence_count FROM %i WHERE $learner AND $skill", $query_args ), ARRAY_A );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$assessed = (int) $row['evidence_count'] > 0;
				$percent  = max( 0.0, min( 100.0, (float) $row['mastery'] ) );
				$states[ (int) $row['student_id'] ][ (string) $row['term_id'] ] = array(
					'percent' => $assessed ? round( $percent, 1 ) : null,
					'level'   => MasteryLevels::level( $percent, $assessed, $thresholds ),
				);
			}
		}
		foreach ( $slice as &$person ) {
			$person['cells'] = (object) ( $states[ $person['id'] ] ?? array() );
		}
		unset( $person );
		return array(
			'skills'      => $skills,
			'learners'    => $slice,
			'levels'      => MasteryLevels::legend( $thresholds ),
			'total'       => $total,
			'total_pages' => $pages,
			'page'        => $page,
		);
	}

	/**
	 * Org roll-up: schools, their classes and (for all schools) individual learners, with learner and
	 * enrolment counts, the average weighted total and learners with missing work. Cached per scope.
	 *
	 * @param Scope $scope Scope.
	 * @return array
	 */
	public static function rollup( Scope $scope ) {
		return Cache::remember(
			'gradebook-rollup',
			$scope,
			new Period( Period::DEFAULT_DAYS ),
			static function () use ( $scope ) {
				return self::compute_rollup( $scope );
			},
			array( 'v' => Version::current() )
		);
	}

	/**
	 * Build the roll-up.
	 *
	 * @param Scope $scope Scope.
	 * @return array
	 */
	private static function compute_rollup( Scope $scope ) {
		$used  = 0;
		$nodes = array();
		if ( Scope::CLASSROOM === $scope->type() ) {
			$class   = Repository::classes( array( $scope->id() ) )[ $scope->id() ] ?? null;
			$nodes[] = self::class_node( $class, $used );
			return array(
				'nodes'     => array_values( array_filter( $nodes ) ),
				'truncated' => false,
			);
		}
		$schools = $scope->is_all() ? Directory::all_schools() : array(
			array(
				'id'   => $scope->id(),
				'name' => $scope->label(),
			),
		);
		$truncated = false;
		foreach ( $schools as $school ) {
			$classes  = Directory::classes( array( (int) $school['id'] ), false );
			$children = array();
			foreach ( $classes as $class ) {
				if ( $used >= self::MAX_ROLLUP_CLASSES ) {
					$truncated = true;
					break;
				}
				$children[] = self::class_node( Repository::classes( array( (int) $class['id'] ) )[ (int) $class['id'] ] ?? null, $used );
			}
			$children = array_values( array_filter( $children ) );
			$nodes[]  = self::merge(
				array(
					'type'     => 'school',
					'id'       => (int) $school['id'],
					'name'     => (string) $school['name'],
					'learners' => count( Directory::school_learners( (int) $school['id'] ) ),
				),
				$children
			);
		}
		if ( $scope->is_all() ) {
			$independent = array();
			foreach ( Directory::classes( array(), true ) as $class ) {
				if ( $used >= self::MAX_ROLLUP_CLASSES ) {
					$truncated = true;
					break;
				}
				$independent[] = self::class_node( Repository::classes( array( (int) $class['id'] ) )[ (int) $class['id'] ] ?? null, $used );
			}
			$independent = array_values( array_filter( $independent ) );
			if ( $independent ) {
				$nodes[] = self::merge(
					array(
						'type' => 'independent',
						'id'   => 0,
						'name' => __( 'Independent classes', 'ohmylms' ),
					),
					$independent
				);
			}
			$nodes[] = self::individuals();
		}
		return array(
			'nodes'     => $nodes,
			'truncated' => $truncated,
		);
	}

	/**
	 * Summary of one class over its linked courses.
	 *
	 * @param array|null $class Class row.
	 * @param int        $used  Classes used so far (raised).
	 * @return array|null
	 */
	private static function class_node( $class, &$used ) {
		if ( ! $class || 'active' !== $class['status'] ) {
			return null;
		}
		++$used;
		$scope   = Scope::classroom( $class['id'], $class['name'], $class['school_id'], $class['school_name'] );
		$entries = array();
		foreach ( array_slice( Directory::existing_courses( Directory::class_linked_courses( $class['id'] ) ), 0, self::MAX_ROLLUP_COURSES ) as $course_id ) {
			foreach ( CourseGrid::for_scope( $course_id, $scope )['rows'] as $row ) {
				$entries[ $row['id'] . ':' . $course_id ] = array(
					'user'    => $row['id'],
					'total'   => $row['total']['p'],
					'missing' => $row['counts']['missing'],
					'pending' => $row['counts']['pending'],
				);
			}
		}
		return self::summary(
			array(
				'type'     => 'class',
				'id'       => $class['id'],
				'name'     => $class['name'],
				'learners' => count( (array) $scope->learner_ids() ),
			),
			$entries
		);
	}

	/**
	 * Learners in no class, over the courses most of them take.
	 *
	 * @return array
	 */
	private static function individuals() {
		$entries = array();
		$all     = Scope::all();
		foreach ( Repository::courses_with_individuals( self::MAX_INDIVIDUAL_COURSE ) as $course_id ) {
			$rows    = CourseGrid::for_scope( $course_id, $all )['rows'];
			$members = Repository::class_memberships( wp_list_pluck( $rows, 'id' ) );
			foreach ( $rows as $row ) {
				if ( $row['in_any'] || ! empty( $members[ $row['id'] ] ) ) {
					continue;
				}
				$entries[ $row['id'] . ':' . $course_id ] = array(
					'user'    => $row['id'],
					'total'   => $row['total']['p'],
					'missing' => $row['counts']['missing'],
					'pending' => $row['counts']['pending'],
				);
			}
		}
		$node             = self::summary(
			array(
				'type' => 'individuals',
				'id'   => 0,
				'name' => __( 'Individual learners (no class)', 'ohmylms' ),
			),
			$entries
		);
		$node['learners'] = count( array_unique( wp_list_pluck( $entries, 'user' ) ) );
		return $node;
	}

	/**
	 * Figures of a set of enrolment entries.
	 *
	 * @param array $node    Node (type, id, name, learners).
	 * @param array $entries `user:course` => user, total, missing, pending.
	 * @return array
	 */
	private static function summary( array $node, array $entries ) {
		$missing = array();
		$pending = 0;
		foreach ( $entries as $entry ) {
			if ( $entry['missing'] ) {
				$missing[ $entry['user'] ] = true;
			}
			$pending += $entry['pending'];
		}
		return $node + array(
			'enrolments'       => count( $entries ),
			'average'          => GradeMath::average( wp_list_pluck( $entries, 'total' ) ),
			'missing_learners' => count( $missing ),
			'pending'          => $pending,
			'entries'          => $entries,
			'children'         => array(),
		);
	}

	/**
	 * A parent node from its children (a learner in two classes counts once; an enrolment once).
	 *
	 * @param array $node     Node (type, id, name, optional learners).
	 * @param array $children Child nodes.
	 * @return array
	 */
	private static function merge( array $node, array $children ) {
		$entries = array();
		foreach ( $children as $child ) {
			$entries += $child['entries'];
		}
		$merged = self::summary( $node, $entries );
		if ( ! isset( $node['learners'] ) ) {
			$merged['learners'] = count( array_unique( wp_list_pluck( $entries, 'user' ) ) );
		}
		$merged['children'] = array_map(
			static function ( $child ) {
				unset( $child['entries'] );
				return $child;
			},
			$children
		);
		return $merged;
	}

	/**
	 * Drop the internal entries from roll-up nodes (recursive).
	 *
	 * @param array $nodes Nodes.
	 * @return array
	 */
	public static function public_nodes( array $nodes ) {
		return array_map(
			static function ( $node ) {
				unset( $node['entries'] );
				$node['children'] = self::public_nodes( $node['children'] ?? array() );
				return $node;
			},
			$nodes
		);
	}
}
