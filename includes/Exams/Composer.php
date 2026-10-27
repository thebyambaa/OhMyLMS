<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Exams written in OhMyLMS: a blank exam whose questions are authored in the Gutenberg quiz editor.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\DeliveryPolicy;
use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Assessment\ExtendedQuestions;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\BankAttributes;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\ReviewQueue;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * The backing quiz stays the single source of truth: the exam record reads its questions, sections and
 * time limit from it, so nothing is authored twice.
 */
final class Composer {

	/** Register creation and the learner instructions field beside the other exam routes. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/** Create a blank exam; save exam-only fields. */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/exams/compose',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( Library::class, 'permission' ),
				'callback'            => array( __CLASS__, 'create' ),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/exams/(?P<id>\d+)/settings',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( Library::class, 'permission' ),
				'callback'            => array( __CLASS__, 'settings' ),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/exams/(?P<id>\d+)/questions',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( Library::class, 'permission' ),
				'callback'            => array( __CLASS__, 'add_questions' ),
			)
		);
	}

	/**
	 * Place existing bank questions at the end of a draft exam, in the order given. Questions already in
	 * the exam keep their position.
	 *
	 * @param \WP_REST_Request $request Exam ID and question IDs.
	 * @return array|\WP_Error Exam summary.
	 */
	public static function add_questions( $request ) {
		$id   = (int) $request['id'];
		$data = get_post_meta( $id, Library::META, true );
		if ( ! is_array( $data ) || empty( $data['composed'] ) ) {
			return self::error( 'composed', __( 'Questions can only be added to exams created in OhMyLMS.', 'ohmylms' ), 404 );
		}
		if ( 'publish' === get_post_status( $id ) ) {
			return self::error( 'locked', __( 'This exam is published and can no longer be edited.', 'ohmylms' ), 409 );
		}
		$ids = is_array( $request['question_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $request['question_ids'] ) ) ) ) : array();
		if ( ! $ids || count( $ids ) > 50 ) {
			return self::error( 'questions', __( 'Provide between 1 and 50 question IDs.', 'ohmylms' ) );
		}
		foreach ( $ids as $question_id ) {
			if ( ! DeliveryPolicy::allows( $question_id, DeliveryPolicy::QUIZ ) || ! AccessPolicy::can_use_question( $question_id ) ) {
				/* translators: %d: question ID. */
				return self::error( 'questions', sprintf( __( 'Question %d is unavailable to you.', 'ohmylms' ), $question_id ), 403 );
			}
		}
		try {
			Transaction::run(
				static function () use ( $data, $ids ) {
					foreach ( $ids as $question_id ) {
						DraftWriter::link( (int) $data['quiz_id'], $question_id );
					}
				}
			);
		} catch ( \Throwable $error ) {
			return self::error( 'storage', __( 'The questions could not be added. Nothing was changed.', 'ohmylms' ), 500 );
		}
		return Library::view( $id );
	}

	/**
	 * Create a draft exam with an empty backing quiz, ready to open in the Gutenberg editor.
	 *
	 * @param \WP_REST_Request $request Title and duration.
	 * @return array|\WP_Error Exam summary.
	 */
	public static function create( $request ) {
		$title   = is_string( $request['title'] ) ? sanitize_text_field( $request['title'] ) : '';
		$minutes = $request['duration_minutes'];
		if ( '' === trim( $title ) || ! is_numeric( $minutes ) || $minutes < 1 || $minutes > 1440 ) {
			return self::error( 'header', __( 'Provide a title and a duration from 1 to 1440 minutes.', 'ohmylms' ) );
		}
		try {
			$exam_id = Transaction::run(
				static function () use ( $title, $minutes ) {
					list( $course, $quiz_id, $chapter ) = Library::scaffold( $title, (int) $minutes );
					ErrorException::raise(
						AssessmentSettings::save(
							$quiz_id,
							array(
								'preset'           => 'exam',
								'feedback_release' => 'manual',
							)
						)
					);
					$exam_id = ErrorException::raise(
						wp_insert_post(
							array(
								'post_type'   => Library::TYPE,
								'post_status' => 'draft',
								'post_title'  => $title,
								'post_author' => get_current_user_id(),
							),
							true
						)
					);
					update_post_meta(
						$exam_id,
						Library::META,
						wp_slash(
							array(
								'composed'         => true,
								'job_id'           => 0,
								'course_id'        => $course,
								'chapter_id'       => $chapter,
								'quiz_id'          => $quiz_id,
								'question_ids'     => array(),
								'version_ids'      => array(),
								'duration_minutes' => (int) $minutes,
								'total_marks'      => 0,
								'source_pages'     => array(),
								'instructions'     => '',
							)
						)
					);
					update_post_meta( $quiz_id, '_ohmylms_exam_id', $exam_id );
					return $exam_id;
				}
			);
		} catch ( ErrorException $error ) {
			return $error->error;
		} catch ( \Throwable $error ) {
			return self::error( 'storage', __( 'The exam could not be created. Retry after reloading.', 'ohmylms' ), 500 );
		}
		return Library::view( $exam_id );
	}

	/**
	 * Save the learner instructions, the one exam field the quiz editor does not hold.
	 *
	 * @param \WP_REST_Request $request Exam ID and instructions.
	 * @return array|\WP_Error Exam summary.
	 */
	public static function settings( $request ) {
		$id   = (int) $request['id'];
		$data = get_post_meta( $id, Library::META, true );
		if ( ! is_array( $data ) || empty( $data['composed'] ) ) {
			return self::error( 'composed', __( 'Only exams created in OhMyLMS have editable instructions.', 'ohmylms' ), 404 );
		}
		if ( 'publish' === get_post_status( $id ) ) {
			return self::error( 'locked', __( 'This exam is published and can no longer be edited.', 'ohmylms' ), 409 );
		}
		$data['instructions'] = is_string( $request['instructions'] ) ? mb_substr( sanitize_textarea_field( $request['instructions'] ), 0, 4000 ) : '';
		update_post_meta( $id, Library::META, wp_slash( $data ) );
		return Library::view( $id );
	}

	/**
	 * The exam a quiz belongs to, when it was written in OhMyLMS.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int Exam ID, or 0.
	 */
	public static function exam_for_quiz( $quiz_id ) {
		$exam_id = (int) get_post_meta( $quiz_id, '_ohmylms_exam_id', true );
		$data    = $exam_id && Library::TYPE === get_post_type( $exam_id ) ? get_post_meta( $exam_id, Library::META, true ) : null;
		return is_array( $data ) && ! empty( $data['composed'] ) && (int) $data['quiz_id'] === (int) $quiz_id ? $exam_id : 0;
	}

	/**
	 * What the exam currently is, read from its backing quiz.
	 *
	 * @param int $id Exam ID.
	 * @return array title, duration_minutes, total_marks, question_ids and version_ids.
	 */
	public static function snapshot( $id ) {
		$data  = (array) get_post_meta( $id, Library::META, true );
		$quiz  = ohmylms_get_quiz( (int) $data['quiz_id'] );
		$ids   = array();
		$marks = 0.0;
		foreach ( $quiz->get_questions() as $link ) {
			$ids[] = (int) $link['id'];
			if ( ! empty( $link['settings']['score']['enabled'] ) ) {
				$marks += max( 0, (float) ( $link['settings']['score']['value'] ?? 0 ) );
			}
		}
		$versions = array();
		foreach ( $ids as $question_id ) {
			$identity = VersionPublisher::identity( $question_id );
			if ( $identity ) {
				$versions[ $question_id ] = (int) $identity['current_version_id'];
			}
		}
		return array(
			'title'            => get_the_title( (int) $data['quiz_id'] ),
			'duration_minutes' => (int) ceil( (float) $quiz->get_timer() ),
			'total_marks'      => $marks,
			'question_ids'     => $ids,
			'version_ids'      => $versions,
		);
	}

	/**
	 * Why the exam cannot be published yet.
	 *
	 * @param int $id Exam ID.
	 * @return string[] Messages, empty when it can be.
	 */
	public static function problems( $id ) {
		$snapshot = self::snapshot( $id );
		$problems = array();
		if ( '' === trim( $snapshot['title'] ) ) {
			$problems[] = __( 'Give the exam a title.', 'ohmylms' );
		}
		if ( ! $snapshot['question_ids'] ) {
			$problems[] = __( 'Add at least one question.', 'ohmylms' );
		}
		$data = (array) get_post_meta( $id, Library::META, true );
		foreach ( array_values( ohmylms_get_quiz( (int) $data['quiz_id'] )->get_questions() ) as $index => $link ) {
			if ( in_array( $link['settings']['type'] ?? '', ExtendedQuestions::UNSCORED_TYPES, true ) ) {
				/* translators: 1: question number, 2: question type. */
				$problems[] = sprintf( __( 'Question %1$d (%2$s) is an ungraded activity that adds no marks. Remove it from the exam.', 'ohmylms' ), $index + 1, $link['settings']['type'] );
			}
		}
		if ( $snapshot['duration_minutes'] < 1 || $snapshot['duration_minutes'] > 1440 ) {
			$problems[] = __( 'Turn on the time limit in Quiz settings (1 to 1440 minutes).', 'ohmylms' );
		}
		return $problems;
	}

	/**
	 * Places that would lose a question when the exam is published. Publishing makes its questions
	 * exam-only, so a practice set or inline check that uses one can no longer play it.
	 *
	 * @param int $id Exam ID.
	 * @return string[] Messages, empty when nothing else uses the exam's questions.
	 */
	public static function warnings( $id ) {
		$snapshot = self::snapshot( $id );
		$uses     = DeliveryPolicy::other_uses( $snapshot['question_ids'] );
		$messages = array();
		foreach ( array_values( $snapshot['question_ids'] ) as $index => $question_id ) {
			foreach ( $uses[ $question_id ] ?? array() as $use ) {
				$messages[] = sprintf(
					/* translators: 1: question number, 2: page or lesson title, 3: practice set or inline check. */
					__( 'Question %1$d is also used in "%2$s" (%3$s). Publishing the exam makes it exam-only, so it stops working there.', 'ohmylms' ),
					$index + 1,
					$use['title'],
					DeliveryPolicy::PRACTICE === $use['delivery'] ? __( 'practice set', 'ohmylms' ) : __( 'inline check', 'ohmylms' )
				);
			}
		}
		return $messages;
	}

	/**
	 * Copy the quiz's current questions, time limit and title into the exam record, and mark the
	 * questions secure like imported exam questions.
	 *
	 * @param int $id Exam ID.
	 */
	public static function refresh( $id ) {
		$data     = (array) get_post_meta( $id, Library::META, true );
		$snapshot = self::snapshot( $id );
		foreach ( $snapshot['question_ids'] as $question_id ) {
			BankAttributes::write( $question_id, array( 'secure' => 1 ) );
		}
		update_post_meta( (int) $data['quiz_id'], '_ohmylms_version_pins', $snapshot['version_ids'] );
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => $snapshot['title'],
			)
		);
		wp_update_post(
			array(
				'ID'         => (int) $data['course_id'],
				'post_title' => $snapshot['title'],
			)
		);
		update_post_meta(
			$id,
			Library::META,
			wp_slash( array_merge( $data, array_diff_key( $snapshot, array( 'title' => 1 ) ) ) )
		);
	}

	/**
	 * Approve the current version of every question so a composed exam can be published. The author of
	 * an exam is its reviewer; a question the author cannot approve stops publication with its message.
	 *
	 * @param int $id Exam ID.
	 * @return true|\WP_Error True, or the first approval failure.
	 */
	public static function approve( $id ) {
		$data = get_post_meta( $id, Library::META, true );
		foreach ( (array) $data['question_ids'] as $question_id ) {
			$result = ReviewQueue::approve( (int) $question_id );
			if ( true !== $result ) {
				return self::error( 'approval', $result, 409 );
			}
			$data['version_ids'][ $question_id ] = (int) VersionPublisher::identity( $question_id )['approved_version_id'];
		}
		update_post_meta( $id, Library::META, wp_slash( $data ) );
		update_post_meta( $data['quiz_id'], '_ohmylms_version_pins', $data['version_ids'] );
		return true;
	}

	/**
	 * Safe domain error.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error Error.
	 */
	private static function error( $code, $message, $status = 400 ) {
		return new \WP_Error( 'exam_' . $code, $message, array( 'status' => $status ) );
	}
}
