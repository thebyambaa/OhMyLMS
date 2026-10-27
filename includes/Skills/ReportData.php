<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Unit-aware skill matrix data, shared by the REST report and the hub class view.
 *
 * Callers authorize the scope and pass in the learners; this class only reads. Nothing is written.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Performance\Levels;
use OhMyLMS\Performance\Snapshot;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/** Builds the learners x skills matrix, grouped by unit, with Khan display levels. */
final class ReportData {

	/**
	 * Published courses the given learners are enrolled in.
	 *
	 * @param int[] $students Learner IDs.
	 * @return int[]
	 */
	public static function enrolled_courses( array $students ) {
		global $wpdb;
		$students = array_values( array_filter( array_map( 'intval', $students ) ) );
		if ( ! $students ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $students ), '%d' ) );
		$ids     = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT course_id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE status='enrolled' AND user_id IN ($holders)", $students ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The IN list is only %d placeholders; fresh enrolment read, never cached.
		return array_values(
			array_filter(
				array_map( 'intval', $ids ),
				static function ( $id ) {
					return OHMYLMS_COURSE_CPT === get_post_type( $id ) && 'publish' === get_post_status( $id );
				}
			)
		);
	}

	/**
	 * Units and the skill => unit map for some courses.
	 *
	 * With more than one course the unit names carry the course title.
	 *
	 * @param int[] $course_ids Course IDs.
	 * @return array{units:array,map:array<int,int>,terms:int[]} terms are every skill the programs name.
	 */
	public static function context( array $course_ids ) {
		$units = array();
		$map   = array();
		$terms = array();
		$multi = count( $course_ids ) > 1;
		foreach ( $course_ids as $course ) {
			$title = wp_strip_all_tags( get_the_title( $course ) );
			foreach ( Catalog::chapters( $course ) as $chapter ) {
				$units[] = array(
					'id'        => (int) $chapter['id'],
					'name'      => $multi ? $title . ' · ' . $chapter['name'] : $chapter['name'],
					'course_id' => (int) $course,
				);
			}
			$program = CourseProgram::current( $course );
			foreach ( UnitReport::unit_map( $program ) as $term => $chapter ) {
				if ( ! isset( $map[ $term ] ) ) {
					$map[ $term ] = $chapter;
				}
			}
			foreach ( (array) ( $program['outcomes'] ?? array() ) as $outcome ) {
				$terms[] = (int) ( $outcome['term_id'] ?? 0 );
			}
		}
		$unit_ids = array_column( $units, 'id' );
		$map      = array_filter(
			$map,
			static function ( $chapter ) use ( $unit_ids ) {
				return in_array( (int) $chapter, $unit_ids, true );
			}
		);
		return array(
			'units' => $units,
			'map'   => $map,
			'terms' => array_values( array_unique( array_filter( $terms ) ) ),
		);
	}

	/**
	 * The matrix for some learners.
	 *
	 * @param int[] $students   Learner IDs, already authorized by the caller.
	 * @param int[] $course_ids Courses whose units and skills apply (empty for none).
	 * @param int   $chapter    Unit to keep, or 0 for all.
	 * @param int[] $extra      Skills to include even without evidence (for example the course's tagged skills).
	 * @return array|WP_Error
	 */
	public static function matrix( array $students, array $course_ids, $chapter = 0, array $extra = array() ) {
		global $wpdb;
		$chapter = (int) $chapter;
		$context = self::context( $course_ids );
		if ( $chapter && ! in_array( $chapter, array_column( $context['units'], 'id' ), true ) ) {
			return new WP_Error( 'ohmylms_unit_unknown', __( 'This unit does not belong to the chosen course.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$skills = array();
		$rows   = array();
		if ( $students && Schema::ready() ) {
			$holders = implode( ',', array_fill( 0, count( $students ), '%d' ) );
			$states  = $wpdb->get_results( $wpdb->prepare( 'SELECT student_id, term_id, level, review_due, evidence_count, independent_correct, families FROM ' . Schema::table( 'student_skill_state' ) . " WHERE student_id IN ($holders)", $students ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The IN list is only %d placeholders; fresh report read, never cached.
			foreach ( $states as $state ) {
				$skills[ (int) $state['term_id'] ]                             = true;
				$rows[ (int) $state['student_id'] ][ (int) $state['term_id'] ] = array(
					'level'               => $state['level'],
					'review_due'          => (bool) $state['review_due'],
					'evidence'            => (int) $state['evidence_count'],
					'independent_correct' => (int) $state['independent_correct'],
					'families'            => (int) $state['families'],
				);
			}
		}
		foreach ( array_merge( $extra, $context['terms'] ) as $term ) {
			$skills[ (int) $term ] = true;
		}
		$described = array_values( array_filter( array_map( array( Taxonomy::class, 'describe' ), array_keys( $skills ) ) ) );
		usort(
			$described,
			static function ( $left, $right ) {
				return strcmp( $left['name'], $right['name'] );
			}
		);
		$by_id = array();
		foreach ( $described as $skill ) {
			$by_id[ $skill['id'] ] = $skill;
		}
		$term_ids = UnitReport::filter( array_keys( $by_id ), $context['map'], $chapter );
		$groups   = UnitReport::group( $term_ids, $context['map'], $context['units'], __( 'Other skills', 'ohmylms' ) );
		$learners = array();
		$levels   = array();
		$displays = Snapshot::students( array_map( 'intval', $students ), $term_ids );
		foreach ( $students as $id ) {
			$user    = get_userdata( $id );
			$display = $displays[ (int) $id ] ?? array();
			$cells   = array();
			foreach ( $term_ids as $term ) {
				$key               = $display[ $term ]['key'] ?? 'not-started';
				$levels[ $term ][] = $key;
				if ( isset( $rows[ $id ][ $term ] ) || 'not-started' !== $key ) {
					$cells[ $term ] = ( $rows[ $id ][ $term ] ?? array(
						'level'               => 'not-started',
						'review_due'          => false,
						'evidence'            => 0,
						'independent_correct' => 0,
						'families'            => 0,
					) ) + array(
						'display' => $key,
						'label'   => Levels::label( $key ),
					);
				}
			}
			$learners[] = array(
				'id'     => (int) $id,
				'name'   => $user ? $user->display_name : '#' . $id,
				'skills' => (object) $cells,
			);
		}
		usort(
			$learners,
			static function ( $left, $right ) {
				return strcmp( $left['name'], $right['name'] );
			}
		);
		$average = array();
		foreach ( $term_ids as $term ) {
			$average[ $term ] = UnitReport::average( $levels[ $term ] ?? array() );
		}
		$display_levels = array();
		foreach ( Levels::SCALE as $key ) {
			$display_levels[ $key ] = Levels::label( $key );
		}
		return array(
			'skills'         => array_map(
				static function ( $term ) use ( $by_id, $context ) {
					return array(
						'id'      => $term,
						'name'    => $by_id[ $term ]['name'],
						'code'    => $by_id[ $term ]['code'],
						'unit_id' => (int) ( $context['map'][ $term ] ?? 0 ),
					);
				},
				$term_ids
			),
			'students'       => $learners,
			'levels'         => array_map( array( Mastery::class, 'label' ), array_combine( Mastery::LEVELS, Mastery::LEVELS ) ),
			'display_levels' => $display_levels,
			'units'          => UnitReport::with_counts( $context['units'], $context['map'] ),
			'unit'           => $chapter,
			'groups'         => $groups,
			'average'        => (object) $average,
		);
	}
}
