<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Row-level review of an import job for the new admin: edit, accept, reject and apply.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ExamImport;

defined( 'ABSPATH' ) || exit;

/**
 * Every write runs under the job lock and checks the draft revision, so two tabs cannot overwrite
 * each other. Commit, exam and quiz creation stay in {@see Workflow} and {@see \OhMyLMS\Exams\Library}.
 */
final class Review {

	const ROW_FIELDS = array( 'question', 'page', 'requires_source', 'notes', 'skills', 'parts', 'figures' );

	/**
	 * Private job state of an import record, or null.
	 *
	 * @param int $id Job ID.
	 * @return array|null
	 */
	public static function job( $id ) {
		if ( Jobs::TYPE !== get_post_type( $id ) ) {
			return null;
		}
		clean_post_cache( (int) $id );
		$job = get_post_meta( (int) $id, Jobs::META, true );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Status of one row.
	 *
	 * @param array $job Job state.
	 * @param array $row Draft row.
	 * @return string pending|accepted|rejected
	 */
	private static function row_status( array $job, array $row ) {
		if ( in_array( (string) $row['number'], self::rejected( $job ), true ) ) {
			return 'rejected';
		}
		return ! empty( $row['reviewed'] ) ? 'accepted' : 'pending';
	}

	/**
	 * Rejected question numbers.
	 *
	 * @param array $job Job state.
	 * @return string[]
	 */
	public static function rejected( array $job ) {
		return array_values( array_map( 'strval', (array) ( $job['rejected'] ?? array() ) ) );
	}

	/**
	 * Safe owner view. Rows carry answers and rubrics, so only the job owner receives them.
	 *
	 * @param int   $id     Job ID.
	 * @param array $job    Job state.
	 * @param bool  $detail Include the inventory and rows.
	 * @return array
	 */
	public static function view( $id, array $job, $detail = false ) {
		$rows      = (array) ( $job['draft'] ?? array() );
		$statuses  = array_map(
			static function ( $row ) use ( $job ) {
				return self::row_status( $job, (array) $row );
			},
			$rows
		);
		$inventory = (array) ( $job['draft_inventory'] ?? ( $job['inventory'] ?? array() ) );
		$state     = (string) $job['state'];
		$view      = array(
			'id'               => (int) $id,
			'title'            => (string) ( $inventory['title'] ?? get_the_title( $id ) ),
			'kind'             => '' === (string) ( $job['document']['id'] ?? '' ) ? 'rows' : 'pdf',
			'state'            => $state,
			'target'           => Jobs::target( $job['target'] ?? '' ),
			'attempts'         => (int) ( $job['attempts'] ?? 0 ),
			'error'            => (string) ( $job['error'] ?? '' ),
			'validation_error' => (string) ( $job['validation_error'] ?? '' ),
			'source'           => array(
				'name'  => (string) ( $job['document']['name'] ?? '' ),
				'bytes' => (int) ( $job['document']['bytes'] ?? 0 ),
			),
			'has_mark_scheme'  => ! empty( $job['mark_scheme'] ),
			'revision'         => (int) ( $job['draft_revision'] ?? 0 ),
			'row_count'        => count( $rows ),
			'accepted_count'   => count( array_keys( $statuses, 'accepted', true ) ),
			'rejected_count'   => count( array_keys( $statuses, 'rejected', true ) ),
			'question_ids'     => array_map( 'intval', (array) ( $job['question_ids'] ?? array() ) ),
			'exam_id'          => (int) ( $job['exam_id'] ?? 0 ),
			'quiz_id'          => (int) ( $job['quiz_id'] ?? 0 ),
			'updated_at'       => self::time( (string) ( $job['updated_at'] ?? '' ) ),
			'can_extract'      => in_array( $state, array( 'ready_for_extraction', 'extraction_failed' ), true ) && \OhMyLMS\AI\Settings::configured( 'exam_converter' ),
			'can_apply'        => self::can_apply( $job, $statuses ),
		);
		if ( ! $detail ) {
			return $view;
		}
		$view['inventory']       = array(
			'title'            => (string) ( $inventory['title'] ?? '' ),
			'duration_minutes' => (int) ( $inventory['duration_minutes'] ?? 0 ),
			'total_marks'      => (float) ( $inventory['total_marks'] ?? 0 ),
			'page_count'       => (int) ( $inventory['page_count'] ?? 0 ),
		);
		$view['usage']           = array( 'requests' => (int) ( $job['usage']['requests'] ?? 0 ) );
		$view['can_reconstruct'] = 'ready_for_review' === $state && 'pdf' === $view['kind'] && \OhMyLMS\AI\Settings::configured( 'exam_converter' ) && (int) ( $job['usage']['requests'] ?? 0 ) < Workflow::MAX_REQUESTS;
		$view['rows']            = array();
		foreach ( $rows as $index => $row ) {
			$view['rows'][] = array(
				'index'            => $index,
				'number'           => (string) $row['number'],
				'status'           => $statuses[ $index ],
				'question'         => (string) $row['question'],
				'page'             => (int) $row['page'],
				'requires_source'  => ! empty( $row['requires_source'] ),
				'notes'            => (string) ( $row['notes'] ?? '' ),
				'skills'           => (string) ( $row['skills'] ?? '' ),
				'parts'            => array_values( (array) $row['parts'] ),
				'figures'          => array_values( (array) ( $row['figures'] ?? array() ) ),
				'figures_complete' => ! empty( $row['figures_complete'] ),
				'marks'            => array_sum( array_map( 'floatval', array_column( (array) $row['parts'], 'marks' ) ) ),
			);
		}
		return $view;
	}

	/**
	 * Whether apply can run now.
	 *
	 * @param array    $job      Job state.
	 * @param string[] $statuses Row statuses.
	 * @return bool
	 */
	private static function can_apply( array $job, array $statuses ) {
		if ( 'committed' === $job['state'] ) {
			$target = Jobs::target( $job['target'] ?? '' );
			return ( 'exam' === $target && empty( $job['exam_id'] ) ) || ( 'quiz' === $target && empty( $job['quiz_id'] ) );
		}
		return 'ready_for_review' === $job['state'] && in_array( 'accepted', $statuses, true ) && ! in_array( 'pending', $statuses, true );
	}

	/**
	 * Run a row write under the job lock after the state, revision and index checks. Also used by
	 * {@see Proposals}.
	 *
	 * @param int      $id       Job ID.
	 * @param int      $index    Row index.
	 * @param int      $revision Draft revision the client started from.
	 * @param callable $work     Receives the job (by reference) and the row index; returns true or WP_Error.
	 * @return array|\WP_Error Job detail view.
	 */
	public static function write( $id, $index, $revision, callable $work ) {
		return Jobs::locked(
			$id,
			static function () use ( $id, $index, $revision, $work ) {
				$job = self::job( $id );
				if ( ! $job || 'ready_for_review' !== $job['state'] ) {
					return new \WP_Error( 'ohmylms_exam_import_not_reviewable', __( 'Only an extracted import waiting for review can be edited.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				if ( (int) ( $job['draft_revision'] ?? 0 ) !== (int) $revision ) {
					return self::conflict( $job );
				}
				if ( ! isset( $job['draft'][ $index ] ) ) {
					return new \WP_Error( 'ohmylms_exam_import_row_not_found', __( 'This import has no such row.', 'ohmylms' ), array( 'status' => 404 ) );
				}
				$result = $work( $job, $index );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$job['draft_revision'] = 1 + (int) $job['draft_revision'];
				if ( ! self::save( $id, $job ) ) {
					return new \WP_Error( 'ohmylms_exam_import_storage', __( 'The review could not be saved. Reload and retry.', 'ohmylms' ), array( 'status' => 500 ) );
				}
				return self::view( $id, $job, true );
			}
		);
	}

	/**
	 * Edit one row. Editing returns it to pending, because accepting attests to the exact content.
	 *
	 * @param int   $id       Job ID.
	 * @param int   $index    Row index.
	 * @param array $changes  Fields from {@see self::ROW_FIELDS}.
	 * @param int   $revision Draft revision.
	 * @return array|\WP_Error Job detail view.
	 */
	public static function edit_row( $id, $index, array $changes, $revision ) {
		return self::write(
			$id,
			$index,
			$revision,
			static function ( array &$job, $index ) use ( $id, $changes ) {
				$rows = $job['draft'];
				if ( isset( $changes['figures'] ) && is_array( $changes['figures'] ) ) {
					$changes['figures'] = self::keep_proposals( (array) ( $rows[ $index ]['figures'] ?? array() ), $changes['figures'] );
				}
				$rows[ $index ]             = array_merge( $rows[ $index ], array_intersect_key( $changes, array_flip( self::ROW_FIELDS ) ) );
				$rows[ $index ]['reviewed'] = false;
				$inventory                  = $job['draft_inventory'] ?? $job['inventory'];
				$clean                      = Extraction::questions( $rows, $inventory['question_numbers'], $inventory['page_count'] );
				if ( is_wp_error( $clean ) ) {
					return new \WP_Error(
						'ohmylms_exam_import_invalid_row',
						$clean->get_error_message(),
						array(
							'status' => 422,
							'reason' => $clean->get_error_code(),
						)
					);
				}
				foreach ( $clean[ $index ]['figures'] as $figure ) {
					if ( '' !== $figure['asset_id'] && ! self::owns_asset( $id, $figure['asset_id'] ) ) {
						return new \WP_Error( 'ohmylms_exam_import_figure_forbidden', __( 'A figure belongs to another import.', 'ohmylms' ), array( 'status' => 403 ) );
					}
				}
				$job['draft']    = $clean;
				$job['rejected'] = array_values( array_diff( self::rejected( $job ), array( (string) $clean[ $index ]['number'] ) ) );
				return true;
			}
		);
	}

	/**
	 * The reconstruction a learner sees (`selected`, `spec`) and its `proposal` change only through
	 * the proposal routes. A row edit keeps them from the stored figure with the same ID and crop; a
	 * new figure, or one whose crop changed, starts on the original.
	 *
	 * @param array $stored   Stored placements.
	 * @param array $incoming Placements from the request.
	 * @return array
	 */
	private static function keep_proposals( array $stored, array $incoming ) {
		$known = array();
		foreach ( $stored as $figure ) {
			if ( is_array( $figure ) && isset( $figure['id'] ) ) {
				$known[ (string) $figure['id'] ] = $figure;
			}
		}
		foreach ( $incoming as &$figure ) {
			if ( ! is_array( $figure ) ) {
				continue;
			}
			unset( $figure['spec'], $figure['proposal'] );
			$figure['selected'] = 'original';
			$before             = $known[ (string) ( $figure['id'] ?? '' ) ] ?? null;
			if ( $before && (string) ( $before['asset_id'] ?? '' ) === (string) ( $figure['asset_id'] ?? '' ) ) {
				foreach ( array( 'selected', 'spec', 'proposal' ) as $key ) {
					if ( isset( $before[ $key ] ) ) {
						$figure[ $key ] = $before[ $key ];
					}
				}
			}
		}
		unset( $figure );
		return $incoming;
	}

	/**
	 * Accept or reject one row.
	 *
	 * @param int    $id       Job ID.
	 * @param int    $index    Row index.
	 * @param string $decision accept|reject.
	 * @param int    $revision Draft revision.
	 * @return array|\WP_Error Job detail view.
	 */
	public static function decide( $id, $index, $decision, $revision ) {
		return self::write(
			$id,
			$index,
			$revision,
			static function ( array &$job, $index ) use ( $id, $decision ) {
				$row    = $job['draft'][ $index ];
				$number = (string) $row['number'];
				$others = array_values( array_diff( self::rejected( $job ), array( $number ) ) );
				if ( 'reject' === $decision ) {
					$job['draft'][ $index ]['reviewed'] = false;
					$job['rejected']                    = array_merge( $others, array( $number ) );
					return true;
				}
				$figures = (array) ( $row['figures'] ?? array() );
				if ( ! empty( $row['requires_source'] ) && ! $figures ) {
					return new \WP_Error( 'ohmylms_exam_import_figures_required', __( 'This question needs its diagram. Add a figure crop, or clear "Needs the source figure" if it does not.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				foreach ( $figures as $figure ) {
					$asset = Figures::asset( $figure['asset_id'] ?? '' );
					if ( $asset && ! self::owns_asset( $id, $figure['asset_id'] ) ) {
						return new \WP_Error( 'ohmylms_exam_import_figure_forbidden', __( 'A figure belongs to another import.', 'ohmylms' ), array( 'status' => 403 ) );
					}
					if ( empty( $figure['approved'] ) || '' === trim( (string) ( $figure['alt'] ?? '' ) ) || ! $asset || 'ready' !== $asset['state'] ) {
						return new \WP_Error( 'ohmylms_exam_import_figures_unreviewed', __( 'Approve every figure of this question, with a description, after its crop is ready.', 'ohmylms' ), array( 'status' => 409 ) );
					}
				}
				$job['draft'][ $index ]['reviewed']         = true;
				$job['draft'][ $index ]['figures_complete'] = true;
				$job['rejected']                            = $others;
				return true;
			}
		);
	}

	/**
	 * Whether a figure asset was cropped from this job's source by its current owner.
	 *
	 * @param int    $id   Job ID.
	 * @param string $uuid Asset ID.
	 * @return bool
	 */
	public static function owns_asset( $id, $uuid ) {
		$asset = Figures::asset( $uuid );
		return $asset && (int) $id === (int) $asset['job_id'] && get_current_user_id() === (int) $asset['owner'];
	}

	/**
	 * Commit accepted rows (once) and create what the import targets (once). Repeating it returns the
	 * same questions, exam or quiz.
	 *
	 * @param int   $id       Job ID.
	 * @param int   $revision Draft revision, checked while the job is still under review.
	 * @param array $details  title, duration_minutes and exam header fields.
	 * @return array|\WP_Error Job view with `exam` when an exam exists.
	 */
	public static function apply( $id, $revision, array $details ) {
		$job = self::job( $id );
		if ( ! $job ) {
			return new \WP_Error( 'ohmylms_exam_import_not_found', __( 'Import not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( 'ready_for_review' === $job['state'] ) {
			if ( (int) ( $job['draft_revision'] ?? 0 ) !== (int) $revision ) {
				return self::conflict( $job );
			}
			$statuses = array_map(
				static function ( $row ) use ( $job ) {
					return self::row_status( $job, (array) $row );
				},
				(array) $job['draft']
			);
			if ( in_array( 'pending', $statuses, true ) || ! in_array( 'accepted', $statuses, true ) ) {
				return new \WP_Error( 'ohmylms_exam_import_unreviewed', __( 'Accept or reject every row, and accept at least one, before applying the import.', 'ohmylms' ), array( 'status' => 409 ) );
			}
			$commit = new \WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports/' . (int) $id . '/commit' );
			$commit->set_param( 'id', (int) $id );
			$commit->set_param( 'revision', (int) $revision );
			$result = Workflow::commit( $commit );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$job = self::job( $id );
		}
		if ( 'committed' !== $job['state'] ) {
			return new \WP_Error( 'ohmylms_exam_import_not_ready', __( 'This import is not ready to apply yet.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$target    = Jobs::target( $job['target'] ?? '' );
		$inventory = (array) ( $job['draft_inventory'] ?? ( $job['inventory'] ?? array() ) );
		$title     = isset( $details['title'] ) ? (string) $details['title'] : (string) ( $inventory['title'] ?? '' );
		if ( 'exam' === $target ) {
			$create = new \WP_REST_Request( 'POST', '/ohmylms/v1/exams' );
			$create->set_param( 'job_id', (int) $id );
			$create->set_param( 'title', $title );
			$create->set_param( 'duration_minutes', $details['duration_minutes'] ?? (int) ( $inventory['duration_minutes'] ?? 60 ) );
			foreach ( array( 'board', 'qualification', 'subject_code', 'paper', 'variant', 'session', 'calculator', 'instructions' ) as $field ) {
				if ( isset( $details[ $field ] ) ) {
					$create->set_param( $field, (string) $details[ $field ] );
				}
			}
			$result = \OhMyLMS\Exams\Library::create( $create );
		} elseif ( 'quiz' === $target ) {
			$quiz = new \WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports/' . (int) $id . '/quiz' );
			$quiz->set_param( 'id', (int) $id );
			$quiz->set_param( 'title', $title );
			$result = Workflow::quiz( $quiz );
		} else {
			$result = true;
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::view( $id, (array) self::job( $id ), true );
	}

	/**
	 * Stale-write error with the stored revision.
	 *
	 * @param array $job Job state.
	 * @return \WP_Error 409.
	 */
	private static function conflict( array $job ) {
		return new \WP_Error(
			'ohmylms_exam_import_conflict',
			__( 'This review was changed somewhere else. Reload it before saving.', 'ohmylms' ),
			array(
				'status'   => 409,
				'revision' => (int) ( $job['draft_revision'] ?? 0 ),
			)
		);
	}

	/**
	 * Persist job state; a write that changes nothing is not a failure.
	 *
	 * @param int   $id  Job ID.
	 * @param array $job State.
	 * @return bool
	 */
	private static function save( $id, array $job ) {
		$job['updated_at'] = gmdate( 'c' );
		return false !== update_post_meta( $id, Jobs::META, wp_slash( $job ) ) || get_post_meta( $id, Jobs::META, true ) === $job;
	}

	/**
	 * RFC 3339 UTC time from a stored ISO 8601 value.
	 *
	 * @param string $iso Stored time.
	 * @return string
	 */
	private static function time( $iso ) {
		$time = '' === $iso ? false : strtotime( $iso );
		return $time ? gmdate( 'Y-m-d\TH:i:s\Z', $time ) : '';
	}
}
