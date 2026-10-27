<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Quiz class
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */

namespace OhMyLMS\Data;

use OhMyLMS\CPTData\PostTypeData;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Quiz class
 *
 * @package OhMyLMS\Data
 * @since 1.0.0
 */
class Quiz extends PostTypeData {

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'quiz';

	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'quiz';

	/**
	 * Quiz data
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'name'          => '',
		'description'   => '',
		'slug'          => '',
		'questions'     => array(),
		'settings'      => array(),
		'date_created'  => null,
		'date_modified' => null,
		'type'          => 'quiz',
		'status'        => '',
		'drip_settings' => '',
	);

	/**
	 * Quiz constructor.
	 *
	 * @param mixed $quiz Quiz.
	 * @throws \Exception When the quiz is not found.
	 * @since 1.0.0
	 */
	public function __construct( $quiz = '' ) {
		if ( is_numeric( $quiz ) && $quiz > 0 ) {
			$this->set_id( $quiz );
		} elseif ( $quiz instanceof self ) {
			$this->set_id( absint( $quiz->get_id() ) );
		} elseif ( ! empty( $quiz->ID ) ) {
			$this->set_id( absint( $quiz->ID ) );
		}

		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Save quiz data
	 *
	 * @return int Quiz ID
	 * @since 1.0.0
	 */
	public function save() {
		if ( ! $this->data_store ) {
			return $this->get_id();
		}

		/**
		 * Fires before saving the lesson object.
		 *
		 * This action allows developers to perform custom actions before the lesson object is saved.
		 *
		 * @param Quiz $this The lesson object being saved.
		 * @param DataStores $data_store The data store object handling the lesson data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_' . $this->object_type . '_object_save', $this, $this->data_store );

		if ( $this->get_id() ) {
			$this->data_store->update( $this );
		} else {
			$this->data_store->create( $this );
		}

		/**
		 * Fires after saving the lesson object.
		 *
		 * This action allows developers to perform custom actions after the lesson object is saved.
		 *
		 * @param Lesson $this The lesson object being saved.
		 * @param DataStores $data_store The data store object handling the lesson data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_' . $this->object_type . '_object_save', $this, $this->data_store );

		return $this->get_id();
	}


	/**
	 * Get permalink of the quiz
	 *
	 * @return false|string
	 * @since 1.0.0
	 */
	public function get_permalink(): string {
		return ohmylms_get_pretty_content_permalink( $this->get_id() ) ?? '';
	}


	/**
	 * Delete quiz data
	 *
	 * @param array $args Arguments.
	 * @since 1.0.0
	 */
	public function delete( $args = array() ) {
		$this->data_store->delete( $this, $args );
	}

	/**
	 * Get the quiz questions
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_questions(): array {
		return $this->data_store->get_questions( $this );
	}

	/**
	 * Get the quiz questions
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function set_contents(): array {
		return $this->data_store->set_contents( $this );
	}

	/**
	 * Set the quiz questions
	 *
	 * @param array $questions Questions.
	 * @since 1.0.0
	 */
	public function set_questions( array $questions ) {
		$this->data['questions'] = $questions;
	}

	/**
	 * Get the quiz settings
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_settings(): array {
		return $this->data['settings'];
	}

	/**
	 * Get course created date.
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 *
	 * @since 1.0.0
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->data['date_created'];
	}


	/**
	 * Set the quiz settings
	 *
	 * @param array $settings Settings.
	 * @since 1.0.0
	 */
	public function set_settings( array $settings ) {
		$this->data['settings'] = $settings;
	}

	/**
	 * Get drip settings.
	 *
	 * @param string $context Context.
	 * @return mixed|null
	 *
	 * @since 1.0.0
	 */
	public function get_drip_settings( $context = 'view' ) {
		return $this->data['drip_settings'];
	}

	/**
	 * Set drip settings.
	 *
	 * @param string $drip_settings Drip settings.
	 * @since 1.0.0
	 */
	public function set_drip_settings( $drip_settings ) {
		$this->data['drip_settings'] = $drip_settings;
	}

	/**
	 * Get quiz data as an array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Get quiz type.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_type() {
		return $this->data['type'];
	}

	/**
	 * Set quiz data from an array.
	 *
	 * @param array $data Array of data.
	 * @since 1.0.0
	 */
	public function set_data( array $data ) {
		$this->data = $data;
	}

	/**
	 * Get quiz attempt data from an array.
	 *
	 * @param int $student_id Student ID.
	 * @since 1.0.0
	 */
	public function get_quiz_attempt( $student_id ) {
		return $this->data_store->get_quiz_attempt( $this, $student_id );
	}

	/**
	 * Save quiz data from an array.
	 *
	 * @param int   $student_id Student ID.
	 * @param array $data Array of data.
	 * @since 1.0.0
	 */
	public function save_attempt( $student_id, $data ) {
		return $this->data_store->save_attempt( $this, $student_id, $data );
	}

	/**
	 * Update quiz data from an array.
	 *
	 * @param int   $student_id Student ID.
	 * @param int   $attempt_id Attempt ID.
	 * @param array $data Array of data.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function update_attempt( $student_id, $attempt_id, $data ) {
		return $this->data_store->update_attempt( $this, $student_id, $attempt_id, $data );
	}

	/**
	 * Get passing grade.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_passing_grade() {
		$settings = $this->get_settings();
		return ! empty( $settings['passing_grade']['enabled'] ) && $settings['passing_grade']['enabled'] && isset( $settings['passing_grade']['value'] ) ? $settings['passing_grade']['value'] : 0;
	}

	/**
	 * Get take attempts.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_take_attempts() {
		$settings = $this->get_settings();
		return ! empty( $settings['allow_attempts'] ) ? $settings['allow_attempts'] : 1;
	}


	/**
	 * Get timer.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_timer() {
		$settings = $this->get_settings();
		$total    = 0;
		if ( ! empty( $settings['time_limit']['value'] ) ) {
			$total = $settings['time_limit']['value'];
		}

		if ( ! empty( $settings['time_limit']['type'] ) && 'minutes' !== $settings['time_limit']['type'] && $total ) {
			$total = 'hours' === $settings['time_limit']['type'] ? (int) $total * 60 : (int) $total * 24 * 60;
		}
		return $total;
	}


	/**
	 * Get total marks.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_total_marks() {
		$questions   = $this->get_questions();
		$total_marks = 0;
		foreach ( $questions as $q ) {
			$question     = ohmylms_get_question( $q['id'] );
			$settings     = $question->get_settings();
			$total_marks += isset( $settings['score']['value'] ) && ! empty( $settings['score']['enabled'] ) && $settings['score']['enabled'] ? $settings['score']['value'] : 0;
		}
		return $total_marks;
	}

	/**
	 * Get total question.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_total_question() {
		$questions = $this->get_questions();
		return count( $questions );
	}

	/**
	 * Get quiz attempt data from an array.
	 *
	 * @param int $student_id Student ID.
	 * @param int $course_id Course ID.
	 * @since 1.0.0
	 */
	public function get_all_quiz_attempts( $student_id, $course_id ) {
		return $this->data_store->get_all_quiz_attempts( $this, $student_id, $course_id );
	}


	/**
	 * Count quiz attempt data from an array.
	 *
	 * @param int $student_id Student ID.
	 * @param int $course_id Course ID.
	 * @since 1.0.0
	 */
	public function count_total_attempt( $student_id, $course_id ) {
		return $this->data_store->count_total_attempt( $this, $student_id, $course_id );
	}


	/**
	 * Get quiz attempt data by attempt id.
	 *
	 * @param int $student_id Student ID.
	 * @param int $course_id Course ID.
	 * @param int $attempt_id Attempt ID.
	 *
	 * @since 1.0.0
	 */
	public function get_all_quiz_attempts_by_attempt_id( $student_id, $course_id, $attempt_id ) {
		return $this->data_store->get_all_quiz_attempts_by_attempt_id( $this, $student_id, $course_id, $attempt_id );
	}


	/**
	 * Get quiz report
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function get_report() {
		return $this->data_store->get_report( $this );
	}


	/**
	 * Get quiz attempt report
	 *
	 * @param int $attempt_id Attempt ID.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function get_attempt_report( $attempt_id ) {
		return $this->data_store->get_attempt_report( $this, $attempt_id );
	}

	/**
	 * Update quiz attempt report
	 *
	 * @param int   $quiz_attempt_answer_id Attempt ID.
	 * @param array $request Request.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function update_attempt_report_manually( $quiz_attempt_answer_id, $request ) {
		return $this->data_store->update_attempt_report_manually( $this, $quiz_attempt_answer_id, $request );
	}


	/**
	 * Review question
	 *
	 * @param int   $question_id Question ID.
	 * @param int   $attempt_id Attempt ID.
	 * @param array $data Data.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function review_question( $question_id, $attempt_id, $data ) {
		return $this->data_store->review_question( $this, $question_id, $attempt_id, $data );
	}
}
