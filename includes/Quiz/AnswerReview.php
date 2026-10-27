<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Answer review for the learner's result screen.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\AttemptReport;
use OhMyLMS\Assessment\Engine;
use OhMyLMS\Assessment\Grader;
use OhMyLMS\Assessment\InlineBlanks;
use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\ReviewLines;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Assessment\Structured;
use OhMyLMS\Assessment\StructuredPart;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a learner see, after submitting, what they answered next to the correct answer.
 *
 * It is on unless a quiz turns it off (the "Show correct answers" switch on the quiz
 * introduction tab) and never reveals more than the quiz allows: the older "Hide Answers On
 * Results Page" switch still wins, a submitted attempt is required, and exam attempts stay
 * hidden until their results are released. Versioned attempts are reviewed against the questions frozen
 * into the attempt; older attempts use the question as it is now.
 */
final class AnswerReview {
	/** Quiz setting that turns the review on. */
	const SETTING = 'show_correct_answers';

	/** Address parameter that picks the attempt to review. */
	const QUERY_VAR = 'review';

	/** Verdicts the "retry the questions I missed" action offers again. */
	const MISSED = array( 'incorrect', 'partial', 'unanswered' );

	/** Smallest difference between two mark values that counts as different. */
	const EPSILON = 0.00005;

	/**
	 * Are answers shown on the result screen of this quiz?
	 *
	 * A quiz that never set the switch shows them, so learners see what they got wrong. A
	 * quiz that switched it off, and the older "Hide Answers On Results Page" switch, keep
	 * them hidden.
	 *
	 * @param array $settings Quiz settings.
	 * @return bool
	 */
	public static function enabled( array $settings ) {
		$chosen = isset( $settings[ self::SETTING ] ) ? rest_sanitize_boolean( $settings[ self::SETTING ] ) : true;
		return $chosen && ! rest_sanitize_boolean( $settings['hide_answers'] ?? false );
	}

	/**
	 * May this learner see the answers of this attempt right now?
	 *
	 * @param array $settings Quiz settings.
	 * @param array $attempt  Attempt row (needs quiz_attempt_id and status).
	 * @return bool
	 */
	public static function can_review( array $settings, array $attempt ) {
		if ( ! self::enabled( $settings ) || 'in-progress' === ( $attempt['status'] ?? 'in-progress' ) ) {
			return false;
		}
		return ! Schema::ready() || AssessmentSettings::feedback_visible( (int) $attempt['quiz_attempt_id'] );
	}

	/**
	 * The most recent submitted attempt of a learner, or null.
	 *
	 * @param array[] $attempts Rows from Quiz::get_all_quiz_attempts().
	 * @return array|null
	 */
	public static function latest_submitted( array $attempts ) {
		$latest = null;
		foreach ( $attempts as $attempt ) {
			if ( 'in-progress' === $attempt['status'] ) {
				continue;
			}
			if ( null === $latest || (int) $attempt['quiz_attempt_id'] > (int) $latest['quiz_attempt_id'] ) {
				$latest = $attempt;
			}
		}
		return $latest;
	}

	/** Register the shortcode that places the review on any page. */
	public static function init() {
		add_shortcode( 'ohmylms_quiz_review', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * [ohmylms_quiz_review quiz="ID"]: the signed-in learner's score and answer review for a
	 * quiz, on any page or post. Without an ID it uses the quiz being viewed.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $attributes ) {
		$attributes = shortcode_atts( array( 'quiz' => 0 ), (array) $attributes, 'ohmylms_quiz_review' );
		$quiz_id    = absint( $attributes['quiz'] );
		if ( ! $quiz_id && OHMYLMS_QUIZ_CPT === get_post_type() ) {
			$quiz_id = (int) get_the_ID();
		}
		if ( ! $quiz_id ) {
			return '';
		}
		if ( ! is_user_logged_in() ) {
			return '<p class="ohmylms-answer-review-login"><a href="' . esc_url( wp_login_url( get_permalink() ?: home_url( '/' ) ) ) . '">' . esc_html__( 'Log in to see your quiz results.', 'ohmylms' ) . '</a></p>';
		}
		return self::for_quiz( $quiz_id );
	}

	/**
	 * Score and answer review of the signed-in learner for a published quiz, wherever it is
	 * shown. Empty when there is nothing the learner may see yet.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string HTML.
	 */
	public static function for_quiz( $quiz_id ) {
		$quiz_id = absint( $quiz_id );
		if ( ! is_user_logged_in() || OHMYLMS_QUIZ_CPT !== get_post_type( $quiz_id ) || 'publish' !== get_post_status( $quiz_id ) ) {
			return '';
		}
		$quiz = ohmylms_get_quiz( $quiz_id );
		return $quiz ? self::render( $quiz, self::attempts( $quiz, get_current_user_id() ) ) : '';
	}

	/**
	 * Every attempt of a learner on a quiz, whichever course it was taken in.
	 *
	 * @param object $quiz       Quiz.
	 * @param int    $student_id Learner ID.
	 * @return array[] Rows like Quiz::get_all_quiz_attempts().
	 */
	public static function attempts( $quiz, $student_id ) {
		global $wpdb;
		$courses = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT course_id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE quiz_id=%d AND student_id=%d", (int) $quiz->get_id(), (int) $student_id ) );
		$rows    = array();
		foreach ( $courses as $course_id ) {
			$rows = array_merge( $rows, (array) $quiz->get_all_quiz_attempts( (int) $student_id, (int) $course_id ) );
		}
		return $rows;
	}

	/**
	 * The review markup for the learner's attempts, or an empty string when none can be shown.
	 * Used by the lesson quiz page, the Quiz Player block, the shortcode and themes.
	 *
	 * @param object  $quiz     Quiz.
	 * @param array[] $attempts The learner's attempt rows for this quiz.
	 * @return string HTML.
	 */
	public static function render( $quiz, array $attempts ) {
		$reviewable = self::reviewable( (array) $quiz->get_settings(), $attempts, $quiz );
		$requested  = self::requested();
		$attempt    = self::select( $reviewable, $requested );
		if ( ! $attempt ) {
			return '';
		}
		$entries = self::for_attempt( $attempt['quiz_attempt_id'] );
		ob_start();
		ohmylms_get_template(
			'single-lesson/quiz-review.php',
			array(
				'entries'  => $entries,
				'summary'  => self::summary( $entries, $attempt, $quiz ),
				'attempts' => $reviewable,
				'attempt'  => $attempt,
				'retry'    => self::retry_items( $entries ),
				// What this attempt did to the learner's skill levels.
				'levels'   => \OhMyLMS\Performance\Engine::changes( get_current_user_id(), 'quiz', (int) $attempt['quiz_attempt_id'] ),
				// Practice opens only on the attempt the address names, so other quizzes on the page stay closed.
				'retrying' => (int) $attempt['quiz_attempt_id'] === $requested && isset( $_GET['retry'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['retry'] ) ), // phpcs:ignore WordPress.Security.NonceVerification -- display only.
				'anchor'   => 'ohmylms-answer-review-' . (int) $quiz->get_id(),
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Attempt the address asks to review (?review=ID), or 0.
	 *
	 * @return int
	 */
	public static function requested() {
		return isset( $_GET[ self::QUERY_VAR ] ) ? absint( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- display only.
	}

	/**
	 * Submitted attempts the learner may review, newest first.
	 *
	 * @param array   $settings Quiz settings.
	 * @param array[] $attempts Rows from Quiz::get_all_quiz_attempts().
	 * @param object  $quiz     Quiz; when given, each row also gets its "review_result" (see result()).
	 * @return array[]
	 */
	public static function reviewable( array $settings, array $attempts, $quiz = null ) {
		$list = array();
		foreach ( $attempts as $attempt ) {
			if ( self::can_review( $settings, $attempt ) ) {
				if ( $quiz ) {
					$basis                    = AttemptReport::basis( (int) $attempt['quiz_attempt_id'], $quiz );
					$attempt['review_result'] = self::result( (float) ( $attempt['total_achieved_marks'] ?? 0 ), $basis['max'], $basis['passing'], (string) ( $attempt['status'] ?? '' ) );
				}
				$list[] = $attempt;
			}
		}
		usort(
			$list,
			static function ( $left, $right ) {
				return (int) $right['quiz_attempt_id'] <=> (int) $left['quiz_attempt_id'];
			}
		);
		return $list;
	}

	/**
	 * The attempt to review: the one asked for when the learner may review it, else the newest.
	 *
	 * @param array[] $reviewable Output of reviewable().
	 * @param int     $requested  Output of requested().
	 * @return array|null
	 */
	public static function select( array $reviewable, $requested ) {
		foreach ( $reviewable as $attempt ) {
			if ( (int) $attempt['quiz_attempt_id'] === (int) $requested ) {
				return $attempt;
			}
		}
		return $reviewable ? $reviewable[0] : null;
	}

	/**
	 * Address of the quiz page showing the review of an attempt. It starts from the page being
	 * viewed, so it also works where the quiz has no pretty permalink.
	 *
	 * @param int   $attempt_id Attempt ID.
	 * @param array $args       Extra query arguments.
	 * @return string
	 */
	public static function url( $attempt_id, array $args = array() ) {
		// Practice (retry) is dropped unless asked for, so moving between attempts or leaving practice does not keep it open.
		return remove_query_arg(
			'quiz',
			add_query_arg(
				array_merge(
					array(
						self::QUERY_VAR => (int) $attempt_id,
						'retry'         => false,
					),
					$args
				)
			)
		);
	}

	/**
	 * Overall outcome of a submitted attempt: review (awaiting a teacher), pass or fail.
	 *
	 * @param float  $earned  Marks earned.
	 * @param float  $max     Marks available.
	 * @param float  $passing Passing mark.
	 * @param string $status  Attempt status.
	 * @return string
	 */
	public static function result( $earned, $max, $passing, $status ) {
		if ( 'in-review' === $status ) {
			return 'review';
		}
		return ( ( $max > 0 && $earned >= $max ) || $earned >= $passing ) ? 'pass' : 'fail';
	}

	/**
	 * Score and verdict counts for the top of a review.
	 *
	 * @param array[] $entries Output of for_attempt().
	 * @param array   $attempt Attempt row.
	 * @param object  $quiz    Quiz.
	 * @return array result, earned, max, passing, percent, counts and total.
	 */
	public static function summary( array $entries, array $attempt, $quiz ) {
		$basis = AttemptReport::basis( (int) $attempt['quiz_attempt_id'], $quiz );
		return self::summarize( $entries, (float) ( $attempt['total_achieved_marks'] ?? 0 ), $basis['max'], $basis['passing'], (string) ( $attempt['status'] ?? '' ) );
	}

	/**
	 * Score and verdict counts from known marks, for attempts that are not stored (previews).
	 *
	 * @param array[] $entries Review entries.
	 * @param float   $earned  Marks earned.
	 * @param float   $max     Marks available.
	 * @param float   $passing Passing mark.
	 * @param string  $status  Attempt status ("in-review" while a teacher still has to mark).
	 * @return array result, earned, max, passing, percent, counts and total.
	 */
	public static function summarize( array $entries, $earned, $max, $passing, $status ) {
		$earned = round( (float) $earned, 4 );
		$basis  = array(
			'max'     => (float) $max,
			'passing' => (float) $passing,
		);
		$counts = array(
			'correct'    => 0,
			'partial'    => 0,
			'incorrect'  => 0,
			'pending'    => 0,
			'unanswered' => 0,
		);
		foreach ( $entries as $entry ) {
			if ( isset( $counts[ $entry['verdict'] ] ) ) {
				++$counts[ $entry['verdict'] ];
			}
		}
		return array(
			'result'  => self::result( $earned, $basis['max'], $basis['passing'], (string) $status ),
			'earned'  => $earned,
			'max'     => $basis['max'],
			'passing' => $basis['passing'],
			'percent' => $basis['max'] > 0 ? round( min( 100, $earned / $basis['max'] * 100 ), 1 ) : null,
			'counts'  => $counts,
			'total'   => count( $entries ),
		);
	}

	/**
	 * Review entries for an instructor preview, where nothing is stored: each item is the
	 * question as shown, what was answered, its grade and its marks. Editors can always see
	 * the answers, so the quiz's switch for learners does not apply.
	 *
	 * @param array[] $items Each with snapshot (QuestionSnapshot), given (array), grade (array with fraction) and marks (float).
	 * @return array[] Entries like for_attempt(), without per-part verdicts.
	 */
	public static function preview( array $items ) {
		$entries = array();
		foreach ( $items as $item ) {
			$grade = (array) $item['grade'];
			$marks = (float) $item['marks'];
			// A question with a teacher-marked part still credits its automatically marked parts, as a stored attempt does.
			$fraction = ! empty( $grade['pending'] ) ? (float) ( $grade['auto_fraction'] ?? 0 ) : (float) ( $grade['fraction'] ?? 0 );
			$entry    = self::entry(
				$item['snapshot'],
				array(
					'given_answer'         => (array) $item['given'],
					'achive_mark'          => $marks * $fraction,
					'is_correct'           => $fraction + self::EPSILON >= 1 ? 1 : 0,
					'is_manually_reviewed' => 0,
				),
				$marks
			);
			if ( 'structured' === $entry['type'] && ! empty( $grade['parts'] ) ) {
				$weights = Structured::weights( $item['snapshot']->get_settings() );
				$map     = array();
				foreach ( $grade['parts'] as $part_id => $part_fraction ) {
					$max             = round( $marks * ( $weights[ $part_id ] ?? 0 ), 4 );
					$map[ $part_id ] = array(
						'max'     => $max,
						'awarded' => null === $part_fraction ? null : $max * (float) $part_fraction,
					);
				}
				$entry['parts'] = self::part_rows( $item['snapshot']->get_settings(), (array) $item['given'], $map );
			}
			$entry['number'] = count( $entries ) + 1;
			$entries[]       = $entry;
		}
		return $entries;
	}

	/**
	 * UUID of a question the practice engine can serve again (approved, not exam-only, not
	 * archived and marked automatically), or an empty string.
	 *
	 * @param int $question_id Question ID.
	 * @return string
	 */
	public static function practice_uuid( $question_id ) {
		$identity = VersionPublisher::identity( (int) $question_id, false );
		if ( ! $identity || (int) $identity['secure'] || ! (int) $identity['approved_version_id'] || Usage::ARCHIVED === get_post_status( (int) $question_id ) ) {
			return '';
		}
		$version    = VersionPublisher::version( (int) $identity['approved_version_id'] );
		$definition = $version ? Registry::get( 'question', (string) $version['type'] ) : null;
		return $definition && empty( $definition['manual'] ) ? (string) $identity['uuid'] : '';
	}

	/**
	 * The missed questions of a review that can be practised again, as number and UUID.
	 *
	 * @param array[] $entries Output of for_attempt().
	 * @return array[]
	 */
	public static function retry_items( array $entries ) {
		if ( ! Schema::ready() || ! Engine::practice() ) {
			return array();
		}
		$items = array();
		foreach ( $entries as $entry ) {
			if ( ! in_array( $entry['verdict'], self::MISSED, true ) || empty( $entry['question_id'] ) ) {
				continue;
			}
			$uuid = self::practice_uuid( (int) $entry['question_id'] );
			if ( '' !== $uuid ) {
				$items[] = array(
					'number' => (int) $entry['number'],
					'uuid'   => $uuid,
				);
			}
		}
		return $items;
	}

	/**
	 * One entry per question of an attempt the signed-in learner owns.
	 *
	 * Each entry: name (HTML), type, verdict (correct|partial|incorrect|pending|unanswered),
	 * awarded, max, given (string[]), expected (string[]|null when the type has no readable
	 * key, such as a teacher-marked answer) and explanation (HTML).
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return array[]
	 */
	public static function for_attempt( $attempt_id ) {
		global $wpdb;
		$attempt_id = (int) $attempt_id;
		$owned      = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d AND student_id=%d AND status<>'in-progress'", $attempt_id, get_current_user_id() ) );
		if ( ! $owned ) {
			return array();
		}
		$rows = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ohmylms_quiz_attempts_answers WHERE quiz_attempt_id=%d ORDER BY id", $attempt_id ), ARRAY_A ) as $row ) {
			$rows[ (int) $row['question_id'] ] = $row;
		}
		$entries = array();
		if ( Schema::ready() && AttemptItems::context( $attempt_id ) ) {
			foreach ( AttemptItems::items( $attempt_id ) as $item ) {
				$question = AttemptItems::snapshot( $item );
				if ( $question ) {
					$row   = $rows[ (int) $item['question_id'] ] ?? null;
					$entry = self::entry( $question, $row, (float) $item['marks'] );
					if ( 'structured' === $entry['type'] ) {
						$entry['parts'] = self::parts( $attempt_id, $item, $question, $row );
					}
					$entry['number'] = count( $entries ) + 1;
					$entries[]       = $entry;
				}
			}
			return $entries;
		}
		foreach ( $rows as $question_id => $row ) {
			$question = ohmylms_get_question( $question_id );
			if ( $question ) {
				$entry           = self::entry( $question, $row, (float) $row['question_marks'] );
				$entry['number'] = count( $entries ) + 1;
				$entries[]       = $entry;
			}
		}
		return $entries;
	}

	/**
	 * How each part of a structured question went.
	 *
	 * @param int    $attempt_id Attempt ID.
	 * @param array  $item       Attempt item.
	 * @param object $question   Frozen snapshot.
	 * @param array  $row        Stored answer row or null.
	 * @return array[] id, label, prompt, kind, verdict, given, expected, awarded and max for each part.
	 */
	private static function parts( $attempt_id, array $item, $question, $row ) {
		$given = $row ? maybe_unserialize( $row['given_answer'] ) : array();
		return self::part_rows( $question->get_settings(), is_array( $given ) ? $given : array(), AttemptReport::parts( $attempt_id, $item, $question ) );
	}

	/**
	 * Part verdicts from each part's given answer and its marks.
	 *
	 * @param array $settings Structured question settings.
	 * @param array $given    Answers by part ID.
	 * @param array $marks    Part ID => max, awarded (null while a teacher has to mark it).
	 * @return array[]
	 */
	private static function part_rows( array $settings, array $given, array $marks ) {
		$parts = array();
		foreach ( Structured::parts( $settings ) as $part ) {
			$id   = $part['id'];
			$text = isset( $given[ $id ] ) && is_scalar( $given[ $id ] ) ? trim( (string) $given[ $id ] ) : '';
			if ( 'question' === $part['kind'] ) {
				$child = StructuredPart::snapshot( $part );
				$text  = implode( '; ', ReviewLines::given( $child->get_type(), (array) ( $given[ $id ] ?? array() ), $child->get_settings() ) ?? self::given_lines( $child->get_type(), self::options( $child ), (array) ( $given[ $id ] ?? array() ) ) );
			}
			$mark    = $marks[ $id ] ?? null;
			$max     = $mark ? (float) $mark['max'] : (float) $part['marks'];
			$awarded = $mark && null !== $mark['awarded'] ? (float) $mark['awarded'] : null;
			if ( '' === $text ) {
				$verdict = 'unanswered';
			} elseif ( null === $awarded ) {
				$verdict = 'pending';
			} elseif ( $max <= 0 ) {
				$verdict = 'ungraded';
			} else {
				$verdict = $awarded + self::EPSILON >= $max ? 'correct' : ( $awarded > self::EPSILON ? 'partial' : 'incorrect' );
			}
			$unit     = trim( (string) ( $part['unit'] ?? '' ) );
			$expected = null;
			if ( 'question' === $part['kind'] ) {
				$expected_lines = ReviewLines::expected( $child->get_type(), $child->get_settings() ) ?: self::expected_lines( $child->get_type(), $child, self::options( $child ), $child->get_settings() );
				$expected       = $expected_lines ? implode( '; ', $expected_lines ) : null;
			} elseif ( 'numerical' === $part['kind'] ) {
				$numbers  = isset( $part['answers'] ) && is_array( $part['answers'] ) ? $part['answers'] : ( isset( $part['answer'] ) ? array( $part['answer'] ) : array() );
				$expected = trim( implode( ' / ', array_map( 'strval', array_filter( $numbers, 'is_scalar' ) ) ) . ' ' . $unit );
			} elseif ( 'text' === $part['kind'] ) {
				$accepted = array_values( array_filter( array_map( 'strval', array_filter( (array) ( $part['accepted'] ?? array() ), 'is_scalar' ) ), 'strlen' ) );
				$expected = $accepted ? $accepted[0] : null;
			}
			$parts[] = array(
				'id'       => $id,
				'label'    => (string) $part['label'],
				'prompt'   => wp_kses_post( (string) ( $part['prompt'] ?? '' ) ),
				'kind'     => $part['kind'],
				'media'    => 'question' === $part['kind'] && in_array( $child->get_type(), array( 'audio-response', 'video-response' ), true ) ? ( 'video-response' === $child->get_type() ? 'video' : 'audio' ) : '',
				'verdict'  => $verdict,
				'given'    => $text,
				'expected' => $expected ? $expected : null,
				'awarded'  => 'pending' === $verdict ? null : round( (float) $awarded, 4 ),
				'max'      => round( $max, 4 ),
			);
		}
		return $parts;
	}

	/**
	 * Build one review entry.
	 *
	 * @param object     $question Frozen snapshot or live question; both expose name, settings and options.
	 * @param array|null $row      Stored answer row, null when the learner left it out.
	 * @param float      $max      Marks the question was worth in this attempt.
	 * @return array
	 */
	private static function entry( $question, $row, $max ) {
		$settings = $question->get_settings();
		$type     = (string) ( $settings['type'] ?? '' );
		$given    = $row ? maybe_unserialize( $row['given_answer'] ) : array();
		$awarded  = $row ? (float) $row['achive_mark'] : 0.0;
		$options  = self::options( $question );
		$present  = Grader::present( $given );
		if ( ! $present ) {
			$verdict = 'unanswered';
		} elseif ( Grader::manual_type( $type ) && empty( $row['is_manually_reviewed'] ) ) {
			$verdict = 'pending';
		} elseif ( ! empty( $row['is_correct'] ) ) {
			$verdict = 'correct';
		} else {
			$verdict = $awarded > 0 ? 'partial' : 'incorrect';
		}
		return array(
			'question_id' => (int) $question->get_id(),
			'name'        => self::prompt( $question, $type ),
			'type'        => $type,
			'verdict'     => $verdict,
			'awarded'     => $awarded,
			'max'         => $max,
			'given'       => $present ? ( ReviewLines::given( $type, (array) $given, $settings ) ?? self::given_lines( $type, $options, (array) $given ) ) : array(),
			'expected'    => ReviewLines::expected( $type, $settings ) ?: self::expected_lines( $type, $question, $options, $settings ),
			'explanation' => wp_kses_post( (string) ( $settings['explanation'] ?? '' ) ),
		);
	}

	/**
	 * Option rows indexed by ID.
	 *
	 * @param object $question Snapshot or live question.
	 * @return array[]
	 */
	private static function options( $question ) {
		$options = array();
		foreach ( $question->get_questions() as $option ) {
			$options[ (string) $option['id'] ] = $option;
		}
		return $options;
	}

	/**
	 * Question text; inline blanks are shown as gaps, not as their answers.
	 *
	 * @param object $question Snapshot or live question.
	 * @param string $type     Question type.
	 * @return string
	 */
	private static function prompt( $question, $type ) {
		$name = (string) $question->get_name();
		if ( 'fill-in-the-blank' === $type ) {
			$parsed = InlineBlanks::parse( $name );
			if ( $parsed['answers'] ) {
				$name = implode(
					'',
					array_map(
						static function ( $part ) {
							return $part['text'] ?? '_____';
						},
						$parsed['parts']
					)
				);
			}
		}
		return wp_kses_post( $name );
	}

	/**
	 * The learner's answer as readable lines.
	 *
	 * @param string  $type    Question type.
	 * @param array[] $options Option rows by ID.
	 * @param array   $given   Stored answer.
	 * @return string[]
	 */
	private static function given_lines( $type, array $options, array $given ) {
		$text = static function ( $value ) use ( $options ) {
			return isset( $options[ (string) $value ] ) ? (string) $options[ (string) $value ]['answer'] : (string) $value;
		};
		if ( in_array( $type, array( 'single-choice', 'multiple-choice', 'true-false', 'reorder' ), true ) ) {
			return array_values( array_filter( array_map( $text, array_values( $given ) ), 'strlen' ) );
		}
		if ( 'matching' === $type ) {
			$lines = array();
			foreach ( $given as $term_id => $match_id ) {
				$lines[] = $text( $term_id ) . ' → ' . self::definition( $options, $match_id );
			}
			return $lines;
		}
		$lines = array();
		foreach ( $given as $key => $value ) {
			$value = is_array( $value ) ? implode(
				', ',
				array_filter(
					array_map(
						static function ( $item ) {
							return is_scalar( $item ) ? (string) $item : wp_json_encode( $item );
						},
						$value
					),
					'strlen'
				)
			) : trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			// Slot, item and blank answers are keyed by an ID; show it so the lines can be told apart.
			$lines[] = is_string( $key ) && ! is_numeric( $key ) ? $key . ' = ' . $value : $value;
		}
		return $lines;
	}

	/**
	 * The correct answer as readable lines, or null when the type has no readable key.
	 *
	 * @param string  $type     Question type.
	 * @param object  $question Snapshot or live question.
	 * @param array[] $options  Option rows by ID.
	 * @param array   $settings Question settings.
	 * @return string[]|null
	 */
	private static function expected_lines( $type, $question, array $options, array $settings ) {
		$lines = null;
		if ( Interactive::is_interactive( $type ) ) {
			$lines = array_map( 'strval', Interactive::expected( $type, $settings ) );
		} elseif ( 'numerical' === $type ) {
			$accepted = isset( $settings['answers'] ) && is_array( $settings['answers'] ) ? $settings['answers'] : array( $settings['answer'] ?? null );
			$unit     = (string) ( $settings['unit'] ?? '' );
			$lines    = array_map(
				static function ( $value ) use ( $unit ) {
					return trim( $value . ' ' . $unit );
				},
				array_filter( $accepted, 'is_numeric' )
			);
		} elseif ( 'reorder' === $type ) {
			$lines = array_map(
				static function ( $option ) {
					return (string) $option['answer'];
				},
				array_values( $options )
			);
		} elseif ( 'matching' === $type ) {
			$lines = array();
			foreach ( $options as $id => $option ) {
				$lines[] = (string) $option['answer'] . ' → ' . self::definition( $options, $id );
			}
		} elseif ( in_array( $type, array( 'single-choice', 'multiple-choice', 'true-false', 'statement', 'short-text', 'fill-in-the-blank' ), true ) ) {
			$inline = 'fill-in-the-blank' === $type ? InlineBlanks::parse( (string) $question->get_name() )['answers'] : array();
			$lines  = $inline ? $inline : array_values(
				array_map(
					static function ( $option ) {
						return (string) $option['answer'];
					},
					array_filter(
						$options,
						static function ( $option ) {
							return ! empty( $option['is_correct'] );
						}
					)
				)
			);
		}
		// An empty key means the type has nothing to show (for example, teacher-marked work).
		return $lines ? array_values( array_filter( $lines, 'strlen' ) ) : null;
	}

	/**
	 * Matching definition (the label) of an option.
	 *
	 * @param array[]    $options Option rows by ID.
	 * @param string|int $id      Option ID.
	 * @return string
	 */
	private static function definition( array $options, $id ) {
		$option = $options[ (string) $id ] ?? null;
		if ( ! $option ) {
			return (string) $id;
		}
		$matching = is_array( $option['matching_data'] ?? null ) ? $option['matching_data'] : array();
		return (string) ( $matching['label'] ?? $option['answer'] );
	}
}
