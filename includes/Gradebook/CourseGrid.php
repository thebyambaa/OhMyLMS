<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Course × Students: every listed learner of a course against its graded items.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\Directory;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;

defined( 'ABSPATH' ) || exit;

/**
 * {@see CourseGrid::compute()} reads everything for a course and a learner set in a bounded number of
 * queries and returns a compact, user-independent grid; {@see CourseGrid::for_scope()} caches it per course
 * and scope (Dashboard\Cache, keyed with the gradebook data version) and {@see CourseGrid::present()}
 * filters, sorts and pages it per request.
 */
final class CourseGrid {

	/**
	 * Cache id (Dashboard\Cache "widget").
	 */
	const CACHE_ID = 'gradebook-course';

	/**
	 * Most learners and cells in one grid.
	 */
	const MAX_LEARNERS = 3000;
	const MAX_CELLS    = 150000;

	/**
	 * Most rows per page and in an export.
	 */
	const MAX_PER_PAGE = 200;
	const MAX_EXPORT   = 5000;

	/**
	 * Row filters on cell states and enrolment statuses.
	 */
	const STATUS_FILTERS = array( 'missing', 'pending', 'overridden', 'excused', 'active', 'completed', 'suspended' );

	/**
	 * The grid of a course for a scope, cached.
	 *
	 * @param int   $course_id Course.
	 * @param Scope $scope     Scope (already checked for the user).
	 * @return array
	 */
	public static function for_scope( $course_id, Scope $scope ) {
		return Cache::remember(
			self::CACHE_ID,
			$scope,
			new Period( Period::DEFAULT_DAYS ),
			static function () use ( $course_id, $scope ) {
				return self::compute( $course_id, $scope->learner_ids(), self::visible_classes( $scope ) );
			},
			array(
				'course' => (int) $course_id,
				'v'      => Version::current(),
			)
		);
	}

	/**
	 * Classes whose names a scope may show: every class for `all`, a school's classes, or the class.
	 *
	 * @param Scope $scope Scope.
	 * @return int[]|null Null for every class.
	 */
	public static function visible_classes( Scope $scope ) {
		if ( $scope->is_all() ) {
			return null;
		}
		if ( Scope::CLASSROOM === $scope->type() ) {
			return array( $scope->id() );
		}
		return array_map( 'intval', wp_list_pluck( Directory::classes( array( $scope->id() ), false ), 'id' ) );
	}

	/**
	 * Compute the grid.
	 *
	 * @param int        $course_id Course.
	 * @param int[]|null $learners  Learners to include (null: everyone listed in the course).
	 * @param int[]|null $visible   Classes whose names may be shown (null: all).
	 * @return array
	 * @throws \DomainException When the grid would exceed the bounds (code 422).
	 */
	public static function compute( $course_id, $learners, $visible ) {
		$course_id = (int) $course_id;
		$items     = Items::for_course( $course_id );
		$enrolled  = Repository::learners( Repository::enrolments( $course_id, $learners ) );
		if ( count( $enrolled ) > self::MAX_LEARNERS || count( $enrolled ) * max( 1, count( $items ) ) > self::MAX_CELLS ) {
			throw new \DomainException( 'too_large', 422 );
		}
		$users   = array_keys( $enrolled );
		$rosters = Repository::course_rosters( $course_id );
		$names   = Repository::names( $users );
		$quizzes = array();
		$written = array();
		foreach ( $items as $item ) {
			if ( 'assignment' === $item['kind'] ) {
				$written[] = $item['id'];
			} else {
				$quizzes[] = $item['id'];
			}
		}
		$attempts = self::attempts(
			Repository::quiz_attempts( $course_id, $quizzes, $users ),
			Repository::assignment_attempts( $course_id, $written, $users )
		);
		$due      = Repository::class_due_dates( $course_id, $users );
		$manual   = Repository::cells( $course_id, $users );
		$weights  = Repository::weights( $course_id );
		$scale    = ScaleSettings::scale();
		$zero     = ScaleSettings::missing_as_zero();
		$now      = gmdate( 'Y-m-d H:i:s' );

		$class_ids = array_keys( $weights );
		foreach ( $enrolled as $user => $learner ) {
			$class_ids = array_merge( $class_ids, $rosters[ $user ] ?? array(), $learner['via'] ? array( $learner['via'] ) : array() );
		}
		$class_ids = array_merge( $class_ids, Repository::linked_classes( $course_id ) );
		foreach ( $items as $item ) {
			$class_ids = array_merge( $class_ids, $item['class_ids'] );
		}
		$classes = Repository::classes( array_values( array_unique( array_filter( $class_ids ) ) ) );
		$shown   = static function ( $id ) use ( $visible, $classes ) {
			return isset( $classes[ $id ] ) && ( null === $visible || in_array( (int) $id, $visible, true ) );
		};

		$rows = array();
		foreach ( $enrolled as $user => $learner ) {
			$mine = array_values( array_unique( array_merge( $rosters[ $user ] ?? array(), $learner['via'] ? array( (int) $learner['via'] ) : array() ) ) );
			sort( $mine );
			$applied = Weights::for_learner( $weights, $mine, (int) $learner['via'] );
			$cells   = array();
			$effect  = array();
			$compute = array();
			$counts  = array(
				'missing'    => 0,
				'pending'    => 0,
				'excused'    => 0,
				'overridden' => 0,
			);
			foreach ( $items as $item ) {
				$override = $manual[ $user ][ $item['id'] ] ?? null;
				$cell     = GradeMath::cell(
					array(
						'assigned' => Items::assigned( $item, $mine ),
						'graded'   => $attempts[ $user ][ $item['id'] ]['graded'] ?? null,
						'pending'  => ! empty( $attempts[ $user ][ $item['id'] ]['pending'] ),
						'due'      => $item['due_at'] ?? ( $due[ $user ][ $item['id'] ] ?? ( $due[ $user ][0] ?? null ) ),
						'now'      => $now,
						'max'      => $item['max'],
						'override' => $override && in_array( $override['kind'], array( GradeMath::SCORE, GradeMath::EXCUSED ), true ) ? $override : null,
					)
				);
				$cells[ $item['id'] ] = array(
					's'  => $cell['state'],
					'v'  => $cell['score'],
					'm'  => $cell['max'],
					'o'  => $cell['overridden'] ? 1 : 0,
					'cs' => $cell['computed']['state'],
					'cv' => $cell['computed']['score'],
					'cm' => $cell['computed']['max'],
					'n'  => $override ? (int) $override['version'] : 0,
				);
				$effect[]             = array(
					'category' => $item['category'],
					'state'    => $cell['state'],
					'score'    => $cell['score'],
					'max'      => $cell['max'],
				);
				$compute[]            = array(
					'category' => $item['category'],
					'state'    => $cell['computed']['state'],
					'score'    => $cell['computed']['score'],
					'max'      => $cell['computed']['max'],
				);
				if ( isset( $counts[ $cell['state'] ] ) ) {
					++$counts[ $cell['state'] ];
				}
				if ( $cell['overridden'] ) {
					++$counts['overridden'];
				}
			}
			$total    = GradeMath::total( $effect, $applied['weights'], $zero );
			$computed = GradeMath::total( $compute, $applied['weights'], $zero );
			$rows[]   = array(
				'id'      => (int) $user,
				'name'    => $names[ $user ] ?? '',
				'status'  => $learner['status'],
				'source'  => $learner['source'],
				'sources' => $learner['sources'],
				'via'     => $learner['via'] && $shown( (int) $learner['via'] ) ? (int) $learner['via'] : 0,
				'rosters' => array_values( array_filter( $mine, $shown ) ),
				'in_any'  => $mine ? 1 : 0,
				'cells'   => $cells,
				'total'   => array(
					'p'    => $total['percent'],
					'l'    => GradeMath::letter( $total['percent'], $scale ),
					'cp'   => $computed['percent'],
					'mode' => $total['mode'],
					'w'    => $applied['class_id'] && $shown( $applied['class_id'] ) ? $applied['class_id'] : ( $applied['class_id'] ? -1 : 0 ),
				),
				'counts'  => $counts,
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] ) ?: $a['id'] <=> $b['id'];
			}
		);

		$class_list = array();
		foreach ( $classes as $id => $class ) {
			if ( $shown( $id ) ) {
				$class_list[] = array(
					'id'          => $id,
					'name'        => $class['name'],
					'school_name' => $class['school_name'],
				);
			}
		}
		$overrides = array();
		foreach ( $weights as $class_id => $row ) {
			if ( $class_id && $shown( $class_id ) ) {
				$overrides[] = array(
					'class_id' => (int) $class_id,
					'weights'  => (object) $row['weights'],
					'version'  => $row['version'],
				);
			}
		}
		$post = get_post( $course_id );
		return array(
			'course'          => array(
				'id'    => $course_id,
				'title' => $post ? get_the_title( $post ) : '',
			),
			'items'           => array_map(
				static function ( $item ) {
					$item['due_at'] = Items::iso( $item['due_at'] );
					return $item;
				},
				$items
			),
			'rows'            => $rows,
			'classes'         => $class_list,
			'weights'         => array(
				'course'    => array(
					'weights' => (object) ( $weights[0]['weights'] ?? array() ),
					'version' => (int) ( $weights[0]['version'] ?? 0 ),
				),
				'overrides' => $overrides,
			),
			'scale'           => $scale,
			'missing_as_zero' => $zero,
			'generated_at'    => gmdate( DATE_ATOM ),
		);
	}

	/**
	 * Latest graded attempt and a pending flag per learner and item.
	 *
	 * Quizzes: `completed` (and legacy `passed`/`failed`) is graded, `in-review` waits for grading,
	 * `in-progress` is not a submission. Assignments: `submitted` waits for grading, any other status is
	 * graded. A newer pending retake never hides a graded attempt.
	 *
	 * @param array[] $quizzes Quiz attempts, newest first.
	 * @param array[] $written Assignment submissions, newest first.
	 * @return array<int,array<int,array{graded:array|null,pending:bool}>>
	 */
	public static function attempts( array $quizzes, array $written ) {
		$out = array();
		foreach ( $quizzes as $row ) {
			$user = (int) $row['user_id'];
			$item = (int) $row['content_id'];
			$slot = $out[ $user ][ $item ] ?? array(
				'graded'  => null,
				'pending' => false,
			);
			if ( in_array( $row['status'], array( 'completed', 'passed', 'failed' ), true ) ) {
				if ( null === $slot['graded'] ) {
					$slot['graded'] = array(
						'score' => (float) $row['score'],
						'max'   => (float) $row['max'],
					);
				}
			} elseif ( 'in-review' === $row['status'] ) {
				$slot['pending'] = true;
			}
			$out[ $user ][ $item ] = $slot;
		}
		foreach ( $written as $row ) {
			$user = (int) $row['user_id'];
			$item = (int) $row['content_id'];
			$slot = $out[ $user ][ $item ] ?? array(
				'graded'  => null,
				'pending' => false,
			);
			if ( 'submitted' === $row['status'] ) {
				$slot['pending'] = true;
			} elseif ( '' !== (string) $row['status'] && null === $slot['graded'] ) {
				$slot['graded'] = array(
					'score' => (float) $row['score'],
					'max'   => 0.0,
				);
			}
			$out[ $user ][ $item ] = $slot;
		}
		return $out;
	}

	/**
	 * A compact cell as the API shows it.
	 *
	 * @param array $cell Compact cell.
	 * @return array
	 */
	public static function expand_cell( array $cell ) {
		return array(
			'state'      => $cell['s'],
			'score'      => $cell['v'],
			'max'        => $cell['m'],
			'percent'    => GradeMath::percent( $cell['v'], $cell['m'] ),
			'overridden' => (bool) $cell['o'],
			'version'    => (int) $cell['n'],
			'computed'   => array(
				'state'   => $cell['cs'],
				'score'   => $cell['cv'],
				'max'     => $cell['cm'],
				'percent' => GradeMath::percent( $cell['cv'], $cell['cm'] ),
			),
		);
	}

	/**
	 * A compact row as the API shows it (cells of the given items only).
	 *
	 * @param array $row     Compact row.
	 * @param array $columns Items to include.
	 * @param array $classes Class id => class.
	 * @return array
	 */
	public static function expand_row( array $row, array $columns, array $classes ) {
		$cells = array();
		foreach ( $columns as $item ) {
			if ( isset( $row['cells'][ $item['id'] ] ) ) {
				$cells[ (string) $item['id'] ] = self::expand_cell( $row['cells'][ $item['id'] ] );
			}
		}
		$class = static function ( $id ) use ( $classes ) {
			return isset( $classes[ $id ] ) ? array(
				'id'   => (int) $id,
				'name' => $classes[ $id ]['name'],
			) : null;
		};
		return array(
			'id'            => $row['id'],
			'name'          => $row['name'],
			'status'        => $row['status'],
			'source'        => $row['source'],
			'sources'       => $row['sources'],
			'via'           => $row['via'] ? $class( $row['via'] ) : null,
			'rosters'       => array_values( array_filter( array_map( $class, $row['rosters'] ) ) ),
			'total'         => array(
				'percent'          => $row['total']['p'],
				'letter'           => $row['total']['l'],
				'computed_percent' => $row['total']['cp'],
				'mode'             => $row['total']['mode'],
				'weights_class'    => $row['total']['w'],
			),
			'counts'        => $row['counts'],
			'cells'         => (object) $cells,
		);
	}

	/**
	 * Filter, sort and page a grid for a request.
	 *
	 * @param array $grid Output of {@see CourseGrid::compute()}.
	 * @param array $args `audience` (all|individuals|class:N), `search`, `status`, `type` (category),
	 *                    `module` (module id), `orderby` (name|total|source), `order`, `page`, `per_page`,
	 *                    `export` (all matching rows, at most 5000).
	 * @return array
	 */
	public static function present( array $grid, array $args ) {
		$classes = array();
		foreach ( $grid['classes'] as $class ) {
			$classes[ (int) $class['id'] ] = $class;
		}
		$type    = (string) ( $args['type'] ?? '' );
		$module  = (int) ( $args['module'] ?? 0 );
		$columns = array_values(
			array_filter(
				$grid['items'],
				static function ( $item ) use ( $type, $module ) {
					return ( '' === $type || $item['category'] === $type ) && ( ! $module || $item['module_id'] === $module );
				}
			)
		);
		$audience = (string) ( $args['audience'] ?? 'all' );
		$class_id = 0 === strpos( $audience, 'class:' ) ? (int) substr( $audience, 6 ) : 0;
		$search   = trim( (string) ( $args['search'] ?? '' ) );
		$status   = (string) ( $args['status'] ?? '' );
		$rows     = array_values(
			array_filter(
				$grid['rows'],
				static function ( $row ) use ( $audience, $class_id, $search, $status, $columns ) {
					if ( 'individuals' === $audience && $row['in_any'] ) {
						return false;
					}
					if ( $class_id && ! in_array( $class_id, $row['rosters'], true ) ) {
						return false;
					}
					if ( '' !== $search && false === mb_stripos( $row['name'], $search ) ) {
						return false;
					}
					if ( in_array( $status, array( 'active', 'completed', 'suspended' ), true ) ) {
						return $row['status'] === $status;
					}
					if ( '' !== $status ) {
						foreach ( $columns as $item ) {
							$cell = $row['cells'][ $item['id'] ] ?? null;
							if ( $cell && ( 'overridden' === $status ? $cell['o'] : $cell['s'] === $status ) ) {
								return true;
							}
						}
						return false;
					}
					return true;
				}
			)
		);
		$orderby = in_array( $args['orderby'] ?? 'name', array( 'name', 'total', 'source' ), true ) ? (string) ( $args['orderby'] ?? 'name' ) : 'name';
		$desc    = 'desc' === ( $args['order'] ?? 'asc' );
		usort(
			$rows,
			static function ( $a, $b ) use ( $orderby, $desc ) {
				if ( 'total' === $orderby ) {
					$x = $a['total']['p'];
					$y = $b['total']['p'];
					if ( null === $x || null === $y ) {
						$diff = ( null === $x ) <=> ( null === $y );
						if ( $diff ) {
							return $diff;
						}
					} else {
						$diff = $desc ? $y <=> $x : $x <=> $y;
						if ( $diff ) {
							return $diff;
						}
					}
				} elseif ( 'source' === $orderby ) {
					$diff = $desc ? strcmp( $b['source'], $a['source'] ) : strcmp( $a['source'], $b['source'] );
					if ( $diff ) {
						return $diff;
					}
				} elseif ( $desc ) {
					return strnatcasecmp( $b['name'], $a['name'] ) ?: $b['id'] <=> $a['id'];
				}
				return strnatcasecmp( $a['name'], $b['name'] ) ?: $a['id'] <=> $b['id'];
			}
		);
		$total    = count( $rows );
		$export   = ! empty( $args['export'] );
		$per_page = $export ? self::MAX_EXPORT : max( 1, min( self::MAX_PER_PAGE, (int) ( $args['per_page'] ?? 50 ) ) );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = $export ? 1 : max( 1, (int) ( $args['page'] ?? 1 ) );
		$slice    = array_slice( $rows, ( $page - 1 ) * $per_page, $per_page );

		$summary = array();
		foreach ( $columns as $item ) {
			$percents = array();
			$states   = array();
			foreach ( $rows as $row ) {
				$cell = $row['cells'][ $item['id'] ] ?? null;
				if ( ! $cell ) {
					continue;
				}
				$states[ $cell['s'] ] = ( $states[ $cell['s'] ] ?? 0 ) + 1;
				if ( GradeMath::SCORE === $cell['s'] ) {
					$percents[] = GradeMath::percent( $cell['v'], $cell['m'] );
				}
			}
			$summary[ (string) $item['id'] ] = array(
				'average' => GradeMath::average( $percents ),
				'states'  => (object) $states,
			);
		}
		$modules = array();
		foreach ( $grid['items'] as $item ) {
			if ( $item['module_id'] && ! isset( $modules[ $item['module_id'] ] ) ) {
				$modules[ $item['module_id'] ] = array(
					'id'    => $item['module_id'],
					'title' => $item['module'],
				);
			}
		}
		$categories = array_keys( (array) $grid['weights']['course']['weights'] );
		foreach ( $grid['items'] as $item ) {
			$categories[] = $item['category'];
		}

		return array(
			'course'          => $grid['course'],
			'items'           => $columns,
			'all_items'       => count( $grid['items'] ),
			'modules'         => array_values( $modules ),
			'categories'      => array_values( array_unique( $categories ) ),
			'classes'         => $grid['classes'],
			'weights'         => $grid['weights'],
			'scale'           => $grid['scale'],
			'missing_as_zero' => $grid['missing_as_zero'],
			'rows'            => array_map(
				static function ( $row ) use ( $columns, $classes ) {
					return self::expand_row( $row, $columns, $classes );
				},
				$slice
			),
			'summary'         => array(
				'learners' => $total,
				'average'  => GradeMath::average( wp_list_pluck( wp_list_pluck( $rows, 'total' ), 'p' ) ),
				'items'    => (object) $summary,
			),
			'total'           => $total,
			'total_pages'     => $pages,
			'page'            => $page,
			'per_page'        => $per_page,
			'generated_at'    => $grid['generated_at'],
		);
	}

	/**
	 * One learner's compact row in a grid, or null.
	 *
	 * @param array $grid    Grid.
	 * @param int   $user_id Learner.
	 * @return array|null
	 */
	public static function row( array $grid, $user_id ) {
		foreach ( $grid['rows'] as $row ) {
			if ( (int) $user_id === $row['id'] ) {
				return $row;
			}
		}
		return null;
	}
}
