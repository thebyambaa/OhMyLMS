<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * "This week" summary on the parent's home (My children) in the hub.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/**
 * Figures for children a school currently links to the signed-in parent. Work is read through
 * {@see ParentWorkspace::work()}, which asks the school service to recheck the guardian link for every
 * child, so nothing here widens what the parent may see. Only school-assigned work is counted.
 */
final class ParentHome {

	/**
	 * Days ahead that count as "due soon".
	 */
	const SOON_DAYS = 7;

	/**
	 * Figures for one child from their assigned work.
	 *
	 * @param array[] $work Rows with `title`, `due_at` (UTC, or empty) and `completed_at`.
	 * @param int     $now  Current time, Unix seconds.
	 * @return array{assigned: int, completed: int, overdue: int, due_soon: int, next: ?array{title: string, due: int}}
	 */
	public static function figures( array $work, $now ) {
		$out = array(
			'assigned'  => count( $work ),
			'completed' => 0,
			'overdue'   => 0,
			'due_soon'  => 0,
			'next'      => null,
		);
		$end = $now + self::SOON_DAYS * DAY_IN_SECONDS;
		foreach ( $work as $row ) {
			if ( ! empty( $row['completed_at'] ) ) {
				++$out['completed'];
				continue;
			}
			$due = empty( $row['due_at'] ) ? 0 : (int) strtotime( $row['due_at'] . ' UTC' );
			if ( $due <= 0 ) {
				continue;
			}
			if ( $due < $now ) {
				++$out['overdue'];
			} elseif ( $due < $end ) {
				++$out['due_soon'];
			}
			if ( $due >= $now && ( null === $out['next'] || $due < $out['next']['due'] ) ) {
				$out['next'] = array(
					'title' => (string) $row['title'],
					'due'   => $due,
				);
			}
		}
		return $out;
	}

	/**
	 * Per-child figures and the children who need a look first.
	 *
	 * @param array[] $children Rows from {@see ParentWorkspace::children()}.
	 * @param int     $now      Current time, Unix seconds.
	 * @return array{totals: array<string,int>, rows: array<string,array>, attention: array[]}
	 */
	public static function summary( array $children, $now ) {
		$out = array(
			'totals'    => array(
				'children' => count( $children ),
				'overdue'  => 0,
				'due_soon' => 0,
			),
			'rows'      => array(),
			'attention' => array(),
		);
		foreach ( $children as $child ) {
			$key                       = (int) $child['student_user_id'] . ':' . (int) $child['school_id'];
			$figures                   = self::figures( ParentWorkspace::work( $child ), $now );
			$out['rows'][ $key ]       = $figures;
			$out['totals']['overdue']  += $figures['overdue'];
			$out['totals']['due_soon'] += $figures['due_soon'];
			if ( $figures['overdue'] > 0 ) {
				$out['attention'][] = array(
					'child'  => $child,
					'weight' => $figures['overdue'],
					'text'   => sprintf( /* translators: %d: number of overdue assignments. */ _n( '%d assignment is overdue', '%d assignments are overdue', $figures['overdue'], 'ohmylms' ), $figures['overdue'] ),
				);
			} elseif ( $figures['due_soon'] > 0 ) {
				$out['attention'][] = array(
					'child'  => $child,
					'weight' => 0,
					'text'   => sprintf( /* translators: %d: number of assignments due within a week. */ _n( '%d assignment is due this week', '%d assignments are due this week', $figures['due_soon'], 'ohmylms' ), $figures['due_soon'] ),
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
