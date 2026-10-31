<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Mastery heatmap": class (or course) × skill, from the skill evidence.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\MasteryRepository;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /dashboard/widgets/mastery-heatmap` (docs/api/dashboard.md, "Mastery heatmap").
 *
 * Rows are the scope's classes (at most 20), or its courses with the most learners when it has no classes.
 * Columns are the skills most of those learners have evidence on (at most 40). A cell is the average
 * percentage of the row's learners on the skill (learners without evidence count as 0) and its level
 * ({@see MasteryLevels}); only cells with evidence are listed. Counts only: names are read by the
 * learners route ({@see MasteryController}) when a cell is opened. The period does not apply (levels are
 * current).
 */
final class MasteryHeatmap extends Widget {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return 'mastery-heatmap';
	}

	/**
	 * Rows, skills and cells of a scope.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period (unused: levels are current).
	 * @return array
	 */
	public function compute( Scope $scope, Period $period ) {
		$thresholds = MasteryLevels::thresholds();
		$rows       = MasteryRepository::rows( $scope );
		$learners   = array();
		foreach ( $rows['rows'] as $row ) {
			foreach ( $row['learners'] as $learner ) {
				$learners[ $learner ] = true;
			}
		}
		$skills = MasteryRepository::top_skills( array_keys( $learners ) );
		$ids    = array_map( 'intval', wp_list_pluck( $skills, 'id' ) );
		$cells  = array();
		foreach ( $rows['rows'] as $row_index => $row ) {
			$figures = MasteryRepository::skill_figures( $row['learners'], $ids, $thresholds['familiar'] );
			foreach ( $ids as $skill_index => $skill_id ) {
				if ( empty( $figures[ $skill_id ]['assessed'] ) ) {
					continue;
				}
				$cells[] = self::cell( $row_index, $skill_index, $figures[ $skill_id ], count( $row['learners'] ), $thresholds );
			}
		}
		$subjects = array();
		foreach ( $skills as $skill ) {
			if ( '' !== $skill['subject'] ) {
				$subjects[ $skill['subject'] ] = ( $subjects[ $skill['subject'] ] ?? 0 ) + 1;
			}
		}
		ksort( $subjects, SORT_NATURAL | SORT_FLAG_CASE );
		return array(
			'row_type'   => $rows['type'],
			'rows_total' => $rows['total'],
			'rows'       => array_map(
				static function ( $row ) {
					return array(
						'key'      => $row['key'],
						'id'       => $row['id'],
						'label'    => $row['label'],
						'learners' => $row['count'],
					);
				},
				$rows['rows']
			),
			'skills'     => array_map(
				static function ( $skill ) {
					return array(
						'id'       => $skill['id'],
						'name'     => $skill['name'],
						'code'     => $skill['code'],
						'subject'  => $skill['subject'],
						'assessed' => $skill['assessed'],
					);
				},
				$skills
			),
			'subjects'   => array_map(
				static function ( $name, $count ) {
					return array(
						'name'   => (string) $name,
						'skills' => $count,
					);
				},
				array_keys( $subjects ),
				array_values( $subjects )
			),
			'cells'      => $cells,
			'levels'     => MasteryLevels::legend( $thresholds ),
			'limits'     => array(
				'rows'   => MasteryRepository::MAX_ROWS,
				'skills' => MasteryRepository::MAX_SKILLS,
			),
		);
	}

	/**
	 * One cell.
	 *
	 * @param int   $row        Row index.
	 * @param int   $skill      Skill index.
	 * @param array $figures    assessed, sum, below.
	 * @param int   $learners   Learners read for the row.
	 * @param array $thresholds Level thresholds.
	 * @return array{row:int,skill:int,percent:int,level:string,assessed:int,below:int}
	 */
	public static function cell( $row, $skill, array $figures, $learners, array $thresholds ) {
		$percent = $learners > 0 ? (int) round( $figures['sum'] / $learners ) : 0;
		return array(
			'row'      => (int) $row,
			'skill'    => (int) $skill,
			'percent'  => $percent,
			'level'    => MasteryLevels::level( $percent, $figures['assessed'] > 0, $thresholds ),
			'assessed' => (int) $figures['assessed'],
			'below'    => (int) $figures['below'],
		);
	}

	/**
	 * Per-user view: whether the user may open student reports from the learner list.
	 *
	 * @param array  $data    Cached data.
	 * @param Scope  $scope   Scope.
	 * @param Period $period  Period.
	 * @param int    $user_id User.
	 * @return array
	 */
	public function present( array $data, Scope $scope, Period $period, $user_id ) {
		$data['can_open_reports'] = user_can( (int) $user_id, Access::SITE_CAPABILITY );
		return $data;
	}
}
