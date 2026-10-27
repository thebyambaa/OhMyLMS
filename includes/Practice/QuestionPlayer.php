<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Frozen bank-question runs with optional grading and skill evidence.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\DeliveryPolicy;
use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Assessment\Grader;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Assessment\Structured;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Runs use practice storage and immutable question versions, independently of course completion. */
final class QuestionPlayer {
	/**
	 * Create a REST error.
	 *
	 * @param string $message Public message.
	 * @param int    $status HTTP status.
	 * @return \WP_Error Player error.
	 */
	public static function error( $message, $status = 400 ) {
		return new \WP_Error( 'ohmylms_question_player', $message, array( 'status' => $status ) );
	}

	/**
	 * Approved, non-exam, automatically marked versions usable by the placement author.
	 *
	 * @param int      $question_id Question ID.
	 * @param int      $author_id Placement author.
	 * @param int|null $student_id Optional learner for course-bank access checks.
	 * @return \OhMyLMS\Assessment\QuestionSnapshot|null Eligible immutable snapshot.
	 */
	public static function eligible( $question_id, $author_id, $student_id = null ) {
		$snapshot = DeliveryPolicy::snapshot( $question_id, DeliveryPolicy::PRACTICE );
		if ( ! $snapshot ) {
			return null;
		}
		$identity = VersionPublisher::identity( $question_id, false );
		$bank_id  = (int) ( $identity['bank_id'] ?? 0 );
		if ( ! user_can( $author_id, 'edit_post', $question_id ) && ( ! $bank_id || ! Banks::can( $bank_id, 'use', $author_id ) ) ) {
			return null;
		}
		$bank = $bank_id ? Banks::get( $bank_id ) : null;
		if ( null !== $student_id && ! empty( $bank['course_id'] ) && ! user_can( $student_id, 'edit_post', (int) $bank['course_id'] ) && ! \OhMyLMS\Learning\CourseProgram::enrollment( $student_id, (int) $bank['course_id'] ) ) {
			return null;
		}
		return $snapshot;
	}

	/**
	 * Freeze an ordered selection or random skill pool when the learner starts.
	 *
	 * @param array $placement Authorized saved placement.
	 * @return array|\WP_Error Learner state.
	 */
	public static function start( array $placement ) {
		global $wpdb;
		$settings      = $placement['settings'];
		$mode          = $settings['activityMode'] ?? 'standard';
		$pair_count    = 0;
		$recall_labels = array();
		$game_count    = 0;
		$game_backs    = array();
		$game_fronts   = array();
		$author_id     = (int) $placement['post']->post_author;
		$student       = get_current_user_id();
		$ids           = $settings['questionIds'];
		if ( 'random' === $settings['source'] ) {
			$ids = array();
			foreach ( $settings['skillIds'] as $skill_id ) {
				if ( ! term_exists( $skill_id, \OhMyLMS\Skills\Taxonomy::NAME ) ) {
					continue;
				}
				foreach ( Selector::pool( $skill_id ) as $candidate ) {
					$ids[ (int) $candidate['question_id'] ] = (int) $candidate['question_id'];
				}
			}
			$ids = array_values( $ids );
			shuffle( $ids );
		}
		$versions = array();
		foreach ( $ids as $id ) {
			$snapshot = self::eligible( $id, $author_id, $student );
			if ( ! $snapshot ) {
				if ( 'selected' === $settings['source'] ) {
					return self::error( __( 'A selected question is unavailable or needs teacher marking. Ask the author to update this player.', 'ohmylms' ), 409 );
				}
				continue;
			}
			if ( ActivityModes::family( $mode ) ) {
				if ( ! ActivityModes::compatible( $mode, $snapshot ) ) {
					if ( 'random' === $settings['source'] ) {
						continue;
					}
					return self::error( __( 'This question does not support the chosen activity. Use the compatible content described in the player editor.', 'ohmylms' ) );
				}
				$family     = ActivityModes::family( $mode );
				$additional = in_array( $family, array( 'deck', 'match' ), true ) && 'matching' === $snapshot->get_type() ? count( $snapshot->get_questions() ) : ( 'sort' === $family ? count( $snapshot->get_settings()['items'] ) : ( 'magnets' === $family ? count( $snapshot->get_settings()['tiles'] ) : 1 ) );
				$fronts     = $game_fronts;
				$backs      = $game_backs;
				if ( 'match' === $family ) {
					foreach ( $snapshot->get_questions() as $option ) {
						$fronts[] = RecallActivity::label( $option['answer'] );
						$backs[]  = RecallActivity::label( $option['matching_data']['label'] );
					}
				}
				if ( $game_count + $additional > 100 || count( array_unique( $backs ) ) !== count( $backs ) || count( array_unique( $fronts ) ) !== count( $fronts ) ) {
					if ( 'random' === $settings['source'] ) {
						continue;
					}
					return self::error( __( 'Activities support at most 100 items. Matching games need distinct prompts and answer labels across the selected questions.', 'ohmylms' ) );
				}
				$game_count += $additional;
				$game_fronts = $fronts;
				$game_backs  = $backs;
			} elseif ( in_array( $mode, array( 'hangman', 'spell-word' ), true ) ) {
				if ( ! WordActivity::answers( $snapshot ) ) {
					if ( 'random' === $settings['source'] ) {
						continue;
					}
					return self::error( __( 'Word games need automatic text answers or letter tiles, containing letters and at most 64 characters per answer.', 'ohmylms' ) );
				}
			} elseif ( 'image-quiz' === $mode ) {
				if ( ! ImageActivity::compatible( $snapshot ) ) {
					if ( 'random' === $settings['source'] ) {
						continue;
					}
					return self::error( __( 'Image quiz needs single-choice questions with a main image, 2–6 choices and exactly one correct answer. Variable questions are not supported.', 'ohmylms' ) );
				}
			} elseif ( 'standard' !== $mode ) {
				if ( 'matching' !== $snapshot->get_type() ) {
					if ( 'random' === $settings['source'] ) {
						continue; }
					return self::error( __( 'Study modes require matching questions. Choose matching questions or use the standard mode.', 'ohmylms' ) );
				}
				foreach ( $snapshot->get_questions() as $option ) {
					$recall_labels[] = mb_strtolower( RecallActivity::label( (string) $option['answer'] ), 'UTF-8' );
					if ( '' === trim( (string) $option['answer'] ) || '' === trim( (string) ( $option['matching_data']['label'] ?? '' ) ) ) {
						return self::error( __( 'Study cards need text on both sides of each pair.', 'ohmylms' ) );
					}
				}
				$pair_count += count( $snapshot->get_questions() );
				if ( $pair_count > 24 ) {
					return self::error( __( 'Study activities support at most 24 pairs. Select fewer matching questions.', 'ohmylms' ) );
				}
			}
			$versions[] = $snapshot->get_version_id();
			if ( 'random' === $settings['source'] && count( $versions ) >= $settings['count'] ) {
				break;
			}
		}
		if ( ! $versions ) {
			return self::error( __( 'There are no approved questions available for this player.', 'ohmylms' ), 409 );
		}
		if ( 'memory-pairs' === $mode && $pair_count < 2 ) {
			return self::error( __( 'Matching pairs needs at least two pairs.', 'ohmylms' ) );
		}
		if ( 'memory-recall' === $mode && ( count( $recall_labels ) < 3 || count( array_unique( $recall_labels ) ) !== count( $recall_labels ) || in_array( '', $recall_labels, true ) || array_filter(
			$recall_labels,
			static function ( $label ) {
				return mb_strlen( $label ) > 200; }
		) || ( $settings['rememberCount'] ?? 2 ) >= count( $recall_labels ) ) ) {
			return self::error( __( 'Memory recall needs at least three distinct text items (up to 200 characters each) and more items than the number to remember.', 'ohmylms' ) );
		}
		$policy = array(
			'post_id'        => (int) $placement['post']->ID,
			'key'            => $placement['key'],
			'graded'         => 'standard' === $mode && $settings['graded'],
			'affects_skills' => 'standard' === $mode && $settings['affectsSkills'] && ! $placement['preview'],
			'preview'        => $placement['preview'],
			'activity_mode'  => $mode,
		);
		if ( 'memory-recall' === $mode ) {
			$policy['remember_count'] = $settings['rememberCount'];
			$policy['study_seconds']  = $settings['studySeconds'];
		}
		if ( 'image-quiz' === $mode ) {
			$policy['reveal_seconds'] = $settings['revealSeconds'];
		}
		if ( ActivityModes::family( $mode ) ) {
			$policy['round_seconds'] = $settings['roundSeconds'];
		}
		$uuid = wp_generate_uuid4();
		try {
			Transaction::run(
				static function () use ( $wpdb, $uuid, $student, $policy, $versions ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic session creation uses the existing private assessment tables.
					$inserted = $wpdb->insert(
						Schema::table( 'practice_sessions' ),
						array(
							'uuid'       => $uuid,
							'student_id' => $student,
							'mode'       => 'question-player',
							'lesson_id'  => $policy['post_id'],
							'policy'     => wp_json_encode( $policy ),
							'status'     => 'active',
							'item_limit' => count( $versions ),
							'started_at' => current_time( 'mysql', true ),
						)
					);
					if ( ! $inserted ) {
						throw new \RuntimeException( 'Session write failed.' );
					}
					$session_id = (int) $wpdb->insert_id;
					foreach ( $versions as $position => $version_id ) {
						if ( ! Sessions::add_item( $session_id, $version_id, $position + 1 ) ) {
							throw new \RuntimeException( 'Question write failed.' );
						}
					}
				}
			);
		} catch ( \Throwable $error ) {
			return self::error( __( 'Could not start these questions. Please retry.', 'ohmylms' ), 500 );
		}
		return self::view( Sessions::get( $uuid ) );
	}

	/**
	 * Resume or atomically submit an owned run. A repeated submission returns its saved result.
	 *
	 * @param array      $placement Authorized placement.
	 * @param string     $uuid Session UUID.
	 * @param array|null $attempt Posted answer tree, or null for resume.
	 * @param array|null $activity Study reveal action, or null for standard delivery.
	 * @return array|\WP_Error Learner state.
	 */
	public static function resume( array $placement, $uuid, $attempt = null, $activity = null ) {
		global $wpdb;
		$session = Sessions::get( $uuid );
		if ( ! $session || 'question-player' !== $session['mode'] || ! Sessions::owns( $session, array( 'student_id' => get_current_user_id() ) ) || (int) $session['lesson_id'] !== (int) $placement['post']->ID || ( $session['policy']['key'] ?? '' ) !== $placement['key'] ) {
			return self::error( __( 'Question session not found. Start again.', 'ohmylms' ), 404 );
		}
		$items = Sessions::items( $session['id'] );
		foreach ( $items as $item ) {
			if ( ! self::eligible( (int) $item['question_id'], (int) $placement['post']->post_author, get_current_user_id() ) ) {
				return self::error( __( 'A question is no longer available. Ask the author to update this player.', 'ohmylms' ), 403 );
			}
		}
		if ( ActivityModes::family( $session['policy']['activity_mode'] ?? '' ) ) {
			return null !== $activity ? GameActivity::act( $session, $items, $activity ) : GameActivity::view( $session, $items );
		}
		if ( 'image-quiz' === ( $session['policy']['activity_mode'] ?? '' ) ) {
			return null !== $activity ? ImageActivity::act( $session, $items, $activity ) : ImageActivity::view( $session, $items );
		}
		if ( 'memory-recall' === ( $session['policy']['activity_mode'] ?? '' ) ) {
			return null !== $activity ? RecallActivity::act( $session, $items, $activity ) : RecallActivity::view( $session, $items );
		}
		if ( 'standard' !== ( $session['policy']['activity_mode'] ?? 'standard' ) ) {
			$handler = in_array( $session['policy']['activity_mode'], array( 'hangman', 'spell-word' ), true ) ? WordActivity::class : StudyActivity::class;
			return null !== $activity ? $handler::act( $session, $items, $activity ) : $handler::view( $session, $items );
		}
		if ( null !== $activity ) {
			return self::error( __( 'This player does not use study cards.', 'ohmylms' ) );
		}
		if ( null === $attempt || 'complete' === $session['status'] ) {
			return self::view( $session );
		}
		if ( ! is_array( $attempt ) ) {
			return self::error( __( 'Invalid answer format.', 'ohmylms' ) );
		}
		$submitted = $attempt[ (int) $session['id'] ]['quiz_question'] ?? array();
		if ( ! is_array( $submitted ) ) {
			return self::error( __( 'Invalid answer format.', 'ohmylms' ) );
		}
		$updates = array();
		foreach ( $items as $item ) {
			$snapshot = AttemptItems::snapshot( $item );
			if ( ! $snapshot ) {
				return self::error( __( 'A question version is unavailable.', 'ohmylms' ), 410 );
			}
			$response = $submitted[ (int) $item['question_id'] ] ?? array();
			$answer   = AttemptItems::untokenize( 'p' . (int) $item['id'], $item, is_array( $response ) ? $response : array( $response ) );
			$grade    = $session['policy']['graded'] ? Grader::grade( $snapshot, $answer ) : array(
				'answer'   => Grader::sanitize( $answer ),
				'present'  => Grader::present( $answer ),
				'fraction' => null,
				'correct'  => null,
				'pending'  => false,
				'parts'    => null,
			);
			if ( is_wp_error( $grade ) ) {
				return $grade;
			}
			if ( $grade['pending'] ) {
				return self::error( __( 'This question requires teacher marking and cannot be used in this player.', 'ohmylms' ), 409 );
			}
			if ( ! empty( $snapshot->get_settings()['required'] ) && ! $grade['present'] ) {
				return self::error( __( 'Answer every required question before submitting.', 'ohmylms' ) );
			}
			$updates[] = array(
				'item'     => $item,
				'snapshot' => $snapshot,
				'grade'    => $grade,
			);
		}
		try {
			Transaction::run(
				static function () use ( $wpdb, $session, $updates ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A row lock must read current session state for idempotent submission.
					$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'practice_sessions' ), (int) $session['id'] ) );
					if ( 'complete' === $status ) {
						return;
					}
					if ( 'active' !== $status ) {
						throw new \RuntimeException( 'Session unavailable.' );
					}
					foreach ( $updates as $update ) {
						$item  = $update['item'];
						$grade = $update['grade'];
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit private responses inside the locked session transaction.
						$saved = $wpdb->update(
							Schema::table( 'practice_items' ),
							array(
								'response'    => wp_json_encode( $grade['answer'] ),
								'fraction'    => $grade['fraction'],
								'correct'     => null === $grade['correct'] ? null : (int) $grade['correct'],
								'tries'       => 1,
								'answered_at' => current_time( 'mysql', true ),
							),
							array( 'id' => (int) $item['id'] )
						);
						if ( false === $saved ) {
							throw new \RuntimeException( 'Response write failed.' );
						}
						if ( $session['policy']['graded'] && empty( $session['policy']['preview'] ) ) {
							self::record( $session, $update );
						}
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Completion is written atomically with the item grades.
					if ( false === $wpdb->update(
						Schema::table( 'practice_sessions' ),
						array(
							'status'       => 'complete',
							'completed_at' => current_time( 'mysql', true ),
						),
						array( 'id' => (int) $session['id'] )
					) ) {
						throw new \RuntimeException( 'Completion write failed.' );
					}
				}
			);
		} catch ( \Throwable $error ) {
			return self::error( __( 'Could not save your responses. Your answers remain here; please retry.', 'ohmylms' ), 500 );
		}
		if ( ! empty( $session['policy']['affects_skills'] ) ) {
			\OhMyLMS\Skills\Evidence::soon();
			do_action( 'ohmylms_question_player_completed', $uuid );
		}
		return self::view( Sessions::get( $uuid ) );
	}

	/**
	 * Record whole-question or structured-part grades with an explicit evidence policy.
	 *
	 * @param array $session Frozen session policy.
	 * @param array $update Graded item and snapshot.
	 */
	private static function record( array $session, array $update ) {
		$item     = $update['item'];
		$grade    = $update['grade'];
		$snapshot = $update['snapshot'];
		$marks    = max( 0, (float) ( $snapshot->get_settings()['score']['value'] ?? 1 ) );
		$event    = array(
			'source_type'     => 'question-player',
			'source_id'       => (int) $session['id'],
			'item_id'         => (int) $item['id'],
			'student_id'      => (int) $session['student_id'],
			'question_id'     => (int) $item['question_id'],
			'version_id'      => (int) $item['version_id'],
			'awarded'         => $marks * $grade['fraction'],
			'max_marks'       => $marks,
			'fraction'        => $grade['fraction'],
			'correct'         => $grade['correct'],
			'reason'          => $grade['present'] ? 'answered' : 'unanswered',
			'record_evidence' => ! empty( $session['policy']['affects_skills'] ),
		);
		if ( in_array( $snapshot->get_type(), array( 'structured', 'passage' ), true ) && is_array( $grade['parts'] ) && $grade['present'] ) {
			$weights = Structured::weights( $snapshot->get_settings() );
			foreach ( $grade['parts'] as $part_id => $fraction ) {
				$part_max = $marks * ( $weights[ $part_id ] ?? 0 );
				GradeEvents::record(
					array_merge(
						$event,
						array(
							'part_id'   => (string) $part_id,
							'awarded'   => $part_max * $fraction,
							'max_marks' => $part_max,
							'fraction'  => $fraction,
							'correct'   => $fraction >= 1,
						)
					)
				);
			}
			return;
		}
		GradeEvents::record( $event );
	}

	/**
	 * Build a learner-safe player response with the same templates as formal quizzes.
	 *
	 * @param array $session Owned session.
	 * @return array Player state; private answers and keys never enter an active view.
	 */
	private static function view( array $session ) {
		$items = Sessions::items( $session['id'] );
		if ( ActivityModes::family( $session['policy']['activity_mode'] ?? '' ) ) {
			return GameActivity::view( $session, $items );
		}
		if ( 'image-quiz' === ( $session['policy']['activity_mode'] ?? '' ) ) {
			return ImageActivity::view( $session, $items );
		}
		if ( 'memory-recall' === ( $session['policy']['activity_mode'] ?? '' ) ) {
			return RecallActivity::view( $session, $items );
		}
		if ( 'standard' !== ( $session['policy']['activity_mode'] ?? 'standard' ) ) {
			$handler = in_array( $session['policy']['activity_mode'], array( 'hangman', 'spell-word' ), true ) ? WordActivity::class : StudyActivity::class;
			return $handler::view( $session, $items );
		}
		$result = array(
			'session'       => $session['uuid'],
			'status'        => $session['status'],
			'graded'        => (bool) $session['policy']['graded'],
			'preview'       => (bool) $session['policy']['preview'],
			'affectsSkills' => (bool) $session['policy']['affects_skills'],
			'questions'     => array(),
		);
		if ( 'complete' === $session['status'] ) {
			$result['results'] = array();
			$earned            = 0;
			$maximum           = 0;
			foreach ( $items as $item ) {
				if ( $result['graded'] ) {
					$snapshot            = AttemptItems::snapshot( $item );
					$marks               = max( 0, (float) ( $snapshot ? $snapshot->get_settings()['score']['value'] ?? 1 : 1 ) );
					$earned             += $marks * (float) $item['fraction'];
					$maximum            += $marks;
					$result['results'][] = array(
						'position' => (int) $item['position'],
						'correct'  => (bool) $item['correct'],
						'fraction' => (float) $item['fraction'],
					);
				}
			}
			if ( $result['graded'] ) {
				$result['score'] = array(
					'earned'  => round( $earned, 4 ),
					'maximum' => round( $maximum, 4 ),
				);
			}
			return $result;
		}
		foreach ( $items as $item ) {
			$view = Sessions::item_view( $item );
			if ( ! $view ) {
				continue;
			}
			ob_start();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared renderer escapes text and intentionally returns native answer inputs.
			echo '<div class="ohmylms-question-player-prompt">' . \OhMyLMS\Assessment\InlineBlanks::render( $view, array( 'id' => (int) $session['id'] ) ) . '</div>';
			echo '<div class="ohmylms-question-player-description">' . wp_kses_post( $view['description'] ) . '</div>';
			if ( ! empty( $view['image_src'] ) ) {
				echo '<img class="question-image" src="' . esc_url( $view['image_src'] ) . '" alt="">';
			}
			\OhMyLMS\Extensions\QuestionTypes::render( $view, array( 'id' => (int) $session['id'] ) );
			$result['questions'][] = array(
				'id'       => (int) $item['id'],
				'type'     => $view['settings']['type'],
				'required' => ! empty( $view['settings']['required'] ),
				'html'     => \OhMyLMS\Assessment\MathLive::content( ob_get_clean() ),
			);
		}
		return $result;
	}
}
