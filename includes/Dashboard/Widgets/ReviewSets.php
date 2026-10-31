<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Recommended next assignments": groups below Familiar on a skill, with a draft review set each.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\MasteryRepository;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /dashboard/widgets/review-sets` (docs/api/dashboard.md, "Recommended next assignments").
 *
 * A group is at least three learners of one class (a heatmap row of the scope) with evidence on a skill and
 * a percentage below Familiar. Largest groups first, at most eight. Each carries the bank's question
 * figures for the skill, so the widget can say "not enough questions" instead of failing. Counts only;
 * "See group" reads names from the heatmap learners route. {@see ReviewSets::present()} adds, per user,
 * whether they may create the draft and the review set that already exists for each group.
 */
final class ReviewSets extends Widget {

	/** Most groups listed. */
	const MAX_GROUPS = 8;

	/** Most skills considered per class. */
	const SKILLS_PER_CLASS = 10;

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return 'review-sets';
	}

	/**
	 * Groups of a scope.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period (unused: levels are current).
	 * @return array
	 */
	public function compute( Scope $scope, Period $period ) {
		$thresholds = MasteryLevels::thresholds();
		$rows       = MasteryRepository::rows( $scope );
		$candidates = array();
		if ( 'class' === $rows['type'] ) {
			foreach ( $rows['rows'] as $row ) {
				foreach ( MasteryRepository::struggling_skills( $row['learners'], $thresholds['familiar'], ReviewSetsService::MIN_GROUP, self::SKILLS_PER_CLASS ) as $skill_id => $count ) {
					$candidates[] = array(
						'row'   => $row,
						'skill' => $skill_id,
						'count' => $count,
					);
				}
			}
		}
		usort(
			$candidates,
			static function ( $a, $b ) {
				return array( $b['count'], $a['row']['label'], $a['skill'] ) <=> array( $a['count'], $b['row']['label'], $b['skill'] );
			}
		);
		$terms  = MasteryRepository::terms( array_values( array_unique( array_column( $candidates, 'skill' ) ) ) );
		$groups = array();
		foreach ( $candidates as $candidate ) {
			$skill = $terms[ $candidate['skill'] ] ?? null;
			if ( ! $skill || count( $groups ) >= self::MAX_GROUPS ) {
				continue;
			}
			$groups[] = array(
				'key'       => ReviewSetsService::key( $candidate['row']['id'], $skill['id'] ),
				'class'     => array(
					'id'       => $candidate['row']['id'],
					'key'      => $candidate['row']['key'],
					'label'    => $candidate['row']['label'],
					'learners' => $candidate['row']['count'],
				),
				'skill'     => array(
					'id'   => $skill['id'],
					'name' => $skill['name'],
					'code' => $skill['code'],
				),
				'learners'  => $candidate['count'],
				'questions' => ReviewSetsService::questions( $skill['id'] ),
			);
		}
		return array(
			'row_type'  => $rows['type'],
			'min_group' => ReviewSetsService::MIN_GROUP,
			'groups'    => $groups,
			'total'     => count( $candidates ),
		);
	}

	/**
	 * Per-user view: whether the user may create review sets, and each group's existing review set.
	 *
	 * @param array  $data    Cached data.
	 * @param Scope  $scope   Scope.
	 * @param Period $period  Period.
	 * @param int    $user_id User.
	 * @return array
	 */
	public function present( array $data, Scope $scope, Period $period, $user_id ) {
		$data['can_assign'] = ReviewSetsService::can_assign( $user_id );
		$existing           = ReviewSetsService::existing( array_column( $data['groups'] ?? array(), 'key' ) );
		foreach ( $data['groups'] as &$group ) {
			$group['review_set'] = isset( $existing[ $group['key'] ] ) ? ReviewSetsService::describe( $existing[ $group['key'] ], $user_id ) : null;
		}
		unset( $group );
		return $data;
	}
}
