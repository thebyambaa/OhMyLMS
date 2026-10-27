<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class QuizStore
 * Handles CRUD operations for Quiz data.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Chapter;
use OhMyLMS\Data\Quiz;

defined( 'ABSPATH' ) || exit;

/**
 * Class QuizStore
 * Handles CRUD operations for Quiz data.
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class QuizStore extends DataStore {

	/**
	 * Must exist meta keys.
	 *
	 * @var mixed
	 */
	protected $must_exist_meta_keys = array(
		'_quiz_settings',
	);
	/**
	 * Create a new quiz.
	 *
	 * @param Quiz $quiz Quiz.
	 * @since 1.0.0
	 */
	public function create( &$quiz ): void {
		if ( ! $quiz->get_date_created( 'edit' ) ) {
			$quiz->set_date_created( time() );
		}
		/**
		 * Fires before quiz add
		 *
		 * @param string $quiz quiz object
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_creating_new_quiz', $quiz );

		$slug = $quiz->get_slug( 'edit' );
		$slug = $this->generate_unique_slug( $slug, OHMYLMS_QUIZ_CPT );

		$quiz_id = wp_insert_post(
			apply_filters(
				'ohmylms_new_quiz_data', // Custom filter hook name.
				array(
					'post_title'    => $quiz->get_name() ? $quiz->get_name() : __( 'Untitled', 'ohmylms' ),
					'post_content'  => $quiz->get_description(),
					'post_author'   => get_current_user_id(),
					'post_type'     => OHMYLMS_QUIZ_CPT,
					'post_status'   => 'publish',
					'post_name'     => $slug,
					'post_date'     => gmdate( 'Y-m-d H:i:s', $quiz->get_date_created( 'edit' )->getOffsetTimestamp() ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $quiz->get_date_created( 'edit' )->getTimestamp() ),
				)
			),
			true
		);
		if ( $quiz_id && ! is_wp_error( $quiz_id ) ) {
			$quiz->set_id( $quiz_id );
			flush_rewrite_rules( true );
			$this->update_post_meta( $quiz );

			/**
			 * Fires after quiz add
			 *
			 * @param string $quiz quiz object
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_after_creating_new_quiz', $quiz );

			if ( ! get_option( 'ohmylms_first_content_created', false ) ) {
				do_action( 'ohmylms_after_creating_first_content', $id, $lesson );
			}
		}
	}

	/**
	 * Read for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 */
	public function read( &$quiz ) {
		$post_object = get_post( $quiz->get_id() );
		if ( ! $quiz->get_id() || ! $post_object || OHMYLMS_QUIZ_CPT !== $post_object->post_type ) {
			return ( __( 'Invalid Quiz.', 'ohmylms' ) );
		}

		$quiz->set_props(
			array(
				'name'          => $post_object->post_title,
				'slug'          => $post_object->post_name,
				'status'        => $post_object->post_status,
				'date_created'  => $post_object->post_date_gmt,
				'date_modified' => $post_object->post_modified_gmt,
				'description'   => $post_object->post_content,
			)
		);

		$this->read_quiz_data( $quiz );
	}

	/**
	 * Update an existing quiz.
	 *
	 * @param Quiz $quiz Quiz.
	 * @return void
	 * @since 1.0.0
	 */
	public function update( &$quiz ) {
		$slug = $quiz->get_slug( 'edit' );
		$slug = $this->generate_unique_slug( $slug, OHMYLMS_QUIZ_CPT );

		/**
		 * Before quiz update
		 *
		 * @param Quiz $quiz
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_updating_quiz', $quiz );

		$post_data = array(
			'post_content' => $quiz->get_description( 'edit' ),
			'post_excerpt' => $quiz->get_short_description( 'edit' ),
			'post_title'   => $quiz->get_name( 'edit' ),
			'post_status'  => $quiz->get_status( 'edit' ) ? $quiz->get_status( 'edit' ) : 'publish',
			'post_name'    => $quiz->get_name( 'edit' ),
			'post_type'    => OHMYLMS_QUIZ_CPT,
		);

		if ( $quiz->get_date_created( 'edit' ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $quiz->get_date_created( 'edit' )->getOffsetTimestamp() );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $quiz->get_date_created( 'edit' )->getTimestamp() );
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );

		wp_update_post( array_merge( array( 'ID' => $quiz->get_id() ), $post_data ) );

		$this->update_post_meta( $quiz );
		/**
		 * Action hook to perform additional actions after a lesson is updated.
		 *
		 * @param int    $quiz_id The ID of the updated lesson.
		 * @param Quiz $quiz    The quiz object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_updating_quiz', $quiz->get_id(), $quiz );
	}

	/**
	 * Delete a quiz.
	 *
	 * @param Quiz  $quiz Quiz.
	 * @param array $args Operation arguments.
	 * @since 1.0.0
	 */
	public function delete( &$quiz, $args = array() ) {

		/**
		 * Before quiz delete
		 *
		 * @param Quiz $quiz
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_quiz_delete', $quiz );

		wp_delete_post( $quiz->get_id(), true );

		/**
		 * After quiz delete
		 *
		 * @param Quiz $quiz
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_quiz_delete', $quiz );

		return array(
			'status'  => 'success',
			'message' => __( 'Quiz has been deleted.', 'ohmylms' ),
		);
	}

	/**
	 * Read quiz data for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 */
	protected function read_quiz_data( &$quiz ) {
		$id               = $quiz->get_id();
		$post_meta_values = get_post_meta( $id );

		$meta_key_to_props = $this->quiz_meta_key();

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$meta_value         = isset( $post_meta_values[ $meta_key ][0] ) ? $post_meta_values[ $meta_key ][0] : null;
			$set_props[ $prop ] = maybe_unserialize( $meta_value );
		}
		$quiz->set_props( $set_props );
	}

	/**
	 * Update post meta for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 * @param bool  $force Force.
	 */
	protected function update_post_meta( &$quiz, $force = false ) {
		$props_to_update = $this->quiz_meta_key();
		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $quiz->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			$this->update_or_delete_post_meta( $quiz, $meta_key, $value );
		}

		/**
		 * Fires after the meta data for a lesson is updated.
		 *
		 * @param WP_Post $quiz The updated lesson object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_lesson_meta_updated', $quiz );
	}

	/**
	 *  Get the quiz meta key
	 *
	 * @return mixed|null
	 */
	protected function quiz_meta_key() {
		return apply_filters(
			'ohmylms_quiz_meta_key_to_props',
			array(
				'_quiz_settings' => 'settings',
				'_drip_settings' => 'drip_settings',
			)
		);
	}


	/**
	 * Get the quiz questions.
	 *
	 * @param Quiz   $quiz The quiz object.
	 * @param string $return_type Return type.
	 * @return array The list of questions.
	 *
	 * @since 1.0.0
	 */
	public function get_questions( &$quiz, $return_type = 'array' ) {
		global $wpdb;
		$table_name         = $wpdb->prefix . 'ohmylms_quiz_questions_relationship';
		$quiz_id            = $quiz->get_id();
		$questions          = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE quiz_id = %d ORDER BY order_number ASC', $table_name, $quiz_id )
		);
		$filtered_questions = array();
		$pins               = get_post_meta( $quiz_id, '_ohmylms_version_pins', true );
		$bank_ready         = \OhMyLMS\Assessment\Schema::ready();
		if ( $questions ) {
			foreach ( $questions as $question ) {
				$question_obj = ohmylms_get_question( $question->question_id );

				if ( 'objects' === $return_type ) {
					$filtered_questions[] = $question_obj;
					continue;
				}

				$question_settings = $question_obj->get_settings();

				$filtered_questions[] = array(
					'id'           => $question_obj->get_id(),
					'quiz_id'      => $quiz_id,
					'name'         => $question_obj->get_name(),
					'description'  => $question_obj->get_description(),
					'order_number' => $question->order_number,
					'thumbnail_id' => $question_obj->get_thumbnail_id(),
					'video_id'     => $question_obj->get_video_id(),
					'video_src'    => $question_obj->get_video_url(),
					'image_src'    => $question_obj->get_image_url(),
					'settings'     => $question_settings,
					'questions'    => $question_obj->get_questions(),
					'modified'     => get_post_field( 'post_modified_gmt', $question_obj->get_id() ),
				);
				if ( $bank_ready && \OhMyLMS\QuestionBank\AccessPolicy::can_edit_quiz( $quiz_id ) ) {
					// Authoring context: identity, version and whether this user may edit the question.
					$identity = \OhMyLMS\QuestionBank\VersionPublisher::identity( $question_obj->get_id(), false );
					$filtered_questions[ count( $filtered_questions ) - 1 ] += array(
						'uuid'              => $identity ? $identity['uuid'] : '',
						'version'           => $identity ? (int) $identity['latest_version_no'] : 0,
						'approved_version'  => $identity ? (int) $identity['approved_version_id'] : 0,
						'pinned_version_id' => is_array( $pins ) ? (int) ( $pins[ $question_obj->get_id() ] ?? 0 ) : 0,
						'readonly'          => ! \OhMyLMS\QuestionBank\AccessPolicy::can_edit_question( $question_obj->get_id() ),
						'usage_count'       => count( \OhMyLMS\QuestionBank\Usage::quizzes( $question_obj->get_id() ) ),
						'skill_map'         => (object) \OhMyLMS\QuestionBank\SkillMap::current( $question_obj->get_id() ),
					);
				}
			}
		}
		return $filtered_questions;
	}

	/**
	 * Set the contents of the chapter.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $questions Questions.
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function set_contents( $quiz, $questions ) {
		global $wpdb;
		// $table_name = $wpdb->prefix . 'ohmylms_quiz_questions_relationship';
		// $quiz_id = $quiz->get_id();
		// $values = array();
		// foreach ( $questions as $index => $question ) {
		// $values[] = $quiz_id;
		// $values[] = $question['id'];
		// $values[] = $question['order_number'];
		// $values[] = 'text';
		//
		// Create placeholders for each set of values
		// $placeholders[] = "(%d, %d, %d, %s)";
		// }
		//
		// Construct the query with ON DUPLICATE KEY UPDATE
		// $insert_query = "
		// INSERT INTO $table_name (chapter_id, content_id, order_number, content_type)
		// VALUES " . implode( ', ', $placeholders ) . "
		// ON DUPLICATE KEY UPDATE
		// order_number = VALUES(order_number)
		// ";
		//
		// Execute the query using prepared statements to prevent SQL injection
		// $wpdb->query( $wpdb->prepare( $insert_query, $values ) );

		return true;
	}

	/**
	 * Get quiz attempt for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $student_id Student identifier.
	 */
	public function get_quiz_attempt( $quiz, $student_id ) {
		global $wpdb;

		$attempt = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE quiz_id = %d AND student_id = %d AND start_date IS NOT NULL AND start_date != \'0000-00-00 00:00:00\' AND status = \'in-progress\'', $wpdb->prefix . 'ohmylms_quiz_attempts', $quiz->get_id(), $student_id ),
			ARRAY_A
		);
		return $attempt;
	}

	/**
	 * Save attempt for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $arg Arg.
	 */
	public function save_attempt( $quiz, $student_id, $arg ) {
		global $wpdb;

		$existing_attempt = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE quiz_id = %d AND student_id = %d AND start_date IS NOT NULL AND start_date != \'0000-00-00 00:00:00\' AND status = \'in-progress\'', $wpdb->prefix . 'ohmylms_quiz_attempts', $quiz->get_id(), $student_id )
		);

		if ( $existing_attempt ) {
			$wpdb->update(
				"{$wpdb->prefix}ohmylms_quiz_attempts",
				array(
					'course_id' => $arg['course_id'],
					'total'     => $arg['total'],
					'status'    => $arg['status'],
					'end_date'  => $arg['end_date'],
				),
				array(
					'id' => $existing_attempt->id,
				),
				array(
					'%d',
					'%f',
					'%s',
					'%s',
				),
				array(
					'%d',
				)
			);
		} else {
			$wpdb->insert(
				"{$wpdb->prefix}ohmylms_quiz_attempts",
				array(
					'course_id'  => $arg['course_id'],
					'quiz_id'    => $quiz->get_id(),
					'student_id' => $student_id,
					'total'      => $arg['total'],
					'status'     => $arg['status'],
					'start_date' => current_time( 'mysql' ),
				),
				array(
					'%d',
					'%d',
					'%d',
					'%f',
					'%s',
					'%s',
				)
			);
		}
	}

	/**
	 * Update attempt for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $arg Arg.
	 */
	public function update_attempt( $quiz, $student_id, $attempt_id, $arg ) {
		global $wpdb;

		$wpdb->update(
			"{$wpdb->prefix}ohmylms_quiz_attempts",
			array(
				'course_id' => $arg['course_id'],
				'total'     => $arg['total'],
				'status'    => $arg['status'],
				'end_date'  => $arg['end_date'],
			),
			array(
				'id' => $attempt_id,
			),
			array(
				'%d',
				'%f',
				'%s',
				'%s',
			),
			array(
				'%d',
			)
		);

		$get_attempts = $quiz->get_all_quiz_attempts_by_attempt_id( $student_id, $arg['course_id'], $attempt_id );
		if ( $get_attempts['total_achieved_marks'] >= $quiz->get_passing_grade() ) {
			$student = new \OhMyLMS\Data\Student( $student_id );
			$student->complete_lesson( $quiz->get_id(), $arg['course_id'] );
		}
	}


	/**
	 * Get all quiz attempts for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 */
	public function get_all_quiz_attempts( $quiz, $student_id, $course_id ) {
		global $wpdb;

		$attempts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
            qa.id AS quiz_attempt_id,
            qa.course_id,
            qa.quiz_id,
            qa.student_id,
            qa.total AS total_marks,
            qa.status,
            qa.start_date,
            qa.end_date,
            GROUP_CONCAT(qa_answers.given_answer SEPARATOR \', \') AS answers,
            COUNT(qa_answers.given_answer) AS total_answers_count, -- Count of given answers
            SUM(qa_answers.achive_mark) AS total_achieved_marks,
            SUM(qa_answers.minus_mark) AS total_minus_marks
         FROM
            %i qa
         LEFT JOIN
            %i qa_answers
         ON
            qa.id = qa_answers.quiz_attempt_id
         WHERE
            qa.quiz_id = %d
            AND qa.student_id = %d
            AND qa.course_id = %d
         GROUP BY
            qa.id',
				$wpdb->prefix . 'ohmylms_quiz_attempts',
				$wpdb->prefix . 'ohmylms_quiz_attempts_answers',
				$quiz->get_id(),
				$student_id,
				$course_id
			),
			ARRAY_A
		);

		return array_map( array( $this, 'numeric_scores' ), (array) $attempts );
	}

	/**
	 * Decimal score columns come back as strings ("5.0000"); expose numbers to callers and JSON.
	 *
	 * @param array $row Aggregate attempt row.
	 * @return array
	 */
	public function numeric_scores( $row ) {
		foreach ( array( 'total_marks', 'total_achieved_marks', 'total_minus_marks' ) as $key ) {
			if ( is_array( $row ) && isset( $row[ $key ] ) && is_numeric( $row[ $key ] ) ) {
				$row[ $key ] = (float) $row[ $key ];
			}
		}
		return $row;
	}

	/**
	 * Get all quiz attempts by attempt id for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public function get_all_quiz_attempts_by_attempt_id( $quiz, $student_id, $course_id, $attempt_id ) {
		global $wpdb;

		$attempts = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
            qa.id AS quiz_attempt_id,
            qa.course_id,
            qa.quiz_id,
            qa.student_id,
            qa.total AS total_marks,
            qa.status,
            qa.start_date,
            qa.end_date,
            GROUP_CONCAT(qa_answers.given_answer SEPARATOR \', \') AS answers,
            COUNT(qa_answers.given_answer) AS total_answers_count, -- Count of given answers
            SUM(qa_answers.achive_mark) AS total_achieved_marks,
            SUM(qa_answers.minus_mark) AS total_minus_marks
         FROM
            %i qa
         LEFT JOIN
            %i qa_answers
         ON
            qa.id = qa_answers.quiz_attempt_id
         WHERE
            qa.quiz_id = %d
            AND qa.student_id = %d
            AND qa.course_id = %d
            AND qa.id = %d
         GROUP BY
            qa.id',
				$wpdb->prefix . 'ohmylms_quiz_attempts',
				$wpdb->prefix . 'ohmylms_quiz_attempts_answers',
				$quiz->get_id(),
				$student_id,
				$course_id,
				$attempt_id
			),
			ARRAY_A
		);

		return $attempts ? $this->numeric_scores( $attempts ) : $attempts;
	}


	/**
	 * Get report for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 */
	public function get_report( $quiz ) {
		global $wpdb;

		$report = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
		qa.id AS quiz_attempt_id,
		qa.course_id,
		qa.quiz_id,
		qa.student_id,
		qa.total AS total_marks,
		qa.status,
		qa.start_date,
		qa.end_date,
		GROUP_CONCAT(qa_answers.given_answer SEPARATOR \', \') AS answers,
		COUNT(qa_answers.given_answer) AS total_answers_count, -- Count of given answers
		SUM(qa_answers.achive_mark) AS total_achieved_marks,
		SUM(qa_answers.minus_mark) AS total_minus_marks,
		u.user_email AS student_email,
		u.display_name AS student_name
	 FROM
		%i qa
	 LEFT JOIN
		%i qa_answers
	 ON
		qa.id = qa_answers.quiz_attempt_id
	 LEFT JOIN
		%i u
	 ON
		qa.student_id = u.ID
	 WHERE
		qa.quiz_id = %d
	 GROUP BY
		qa.id',
				$wpdb->prefix . 'ohmylms_quiz_attempts',
				$wpdb->prefix . 'ohmylms_quiz_attempts_answers',
				$wpdb->prefix . 'users',
				$quiz->get_id()
			),
			ARRAY_A
		);
		return array_map( array( $this, 'numeric_scores' ), (array) $report );
	}

	/**
	 * Get the report of a specific quiz attempt.
	 *
	 * @param Quiz $quiz The quiz object.
	 * @param int  $attempt_id The ID of the quiz attempt.
	 * @return array The report of the quiz attempt.
	 * @since 1.0.0
	 */
	public function get_attempt_report( $quiz, $attempt_id ) {
		global $wpdb;
		// Versioned attempts report exactly what the learner saw.
		if ( \OhMyLMS\Assessment\Schema::ready() && \OhMyLMS\Assessment\AttemptItems::is_versioned( $attempt_id ) ) {
			return \OhMyLMS\Assessment\AttemptReport::versioned( $quiz->get_id(), (int) $attempt_id );
		}
		$report      = array();
		$quiz_result = array();
		$result      = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * from %i WHERE quiz_attempt_id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $attempt_id )
		);
		foreach ( $result as $key => $value ) {
			$quiz_result[ $value->question_id ] = (array) $value;
		}
		$questions = $this->get_questions( $quiz );
		// Legacy attempts: also show answered questions that were later removed from the quiz.
		$linked = array_map( 'intval', array_column( $questions, 'id' ) );
		foreach ( array_diff( array_map( 'intval', array_keys( $quiz_result ) ), $linked ) as $removed_id ) {
			$removed = ohmylms_get_question( $removed_id );
			if ( ! $removed || ! $removed->get_id() ) {
				continue;
			}
			$questions[] = array(
				'id'           => $removed_id,
				'quiz_id'      => $quiz->get_id(),
				'name'         => $removed->get_name(),
				'description'  => $removed->get_description(),
				'order_number' => PHP_INT_MAX,
				'settings'     => $removed->get_settings(),
				'questions'    => $removed->get_questions(),
				'removed'      => true,
			);
		}
		$total_marks = 0;
		// Grade-report status is supplied by the registered question type.

		foreach ( $questions as $key => $question ) {
			$question_id                       = $question['id'];
			$questions[ $key ]['given_answer'] = isset( $quiz_result[ $question_id ]['given_answer'] ) ? ( maybe_unserialize( $quiz_result[ $question_id ]['given_answer'] ) ) : null;
			$obj                               = ohmylms_get_question( $question_id );
			$question_settings                 = $obj->get_settings();
			$question_type                     = isset( $question_settings['type'] ) ? $question_settings['type'] : '';
			$definition                        = \OhMyLMS\Extensions\Registry::get( 'question', $question_type );
			$questions[ $key ]['status']       = ! empty( $quiz_result[ $question_id ]['is_manually_reviewed'] ) || ( isset( $quiz_result[ $question_id ] ) && ! empty( $definition ) && empty( $definition['manual'] ) ) ? 'graded' : 'in-review';
			$questions[ $key ]['image']        = $obj->get_image_url();
			$questions[ $key ]['video']        = $obj->get_video_url();
			$questions[ $key ]['achive_mark']  = (float) ( $quiz_result[ $question_id ]['achive_mark'] ?? 0 );
			$questions[ $key ]['quiz_attempts_answers_id'] = isset( $quiz_attempts_answers_id[ $key ] ) ? $quiz_attempts_answers_id[ $key ] : null;

			$total_marks = $total_marks + $questions[ $key ]['achive_mark'];
		}
		$report['questions']            = $questions;
		$report['quiz_attempt_id']      = $attempt_id;
		$report['status']               = $wpdb->get_var(
			$wpdb->prepare( 'SELECT status FROM %i WHERE id = %d AND quiz_id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts', $attempt_id, $quiz->get_id() )
		);
		$report['total_achieved_marks'] = $total_marks;
		// Recorded before versioning: question content shown is the current content, which may differ from what was seen.
		$report['engine'] = 'legacy';
		return $report;
	}

	/**
	 * Attempts are shared by every course that places the quiz: a learner has one attempt allowance and
	 * one history per quiz. `$course_id` stays in the signature for compatibility.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 */
	public function count_total_attempt( $quiz, $student_id, $course_id ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE quiz_id = %d AND student_id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts', $quiz->get_id(), $student_id )
		);

		return $count;
	}

	/**
	 * Update attempt report manually for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $quiz_attempt_answer_id Quiz attempt answer identifier.
	 * @param mixed $request REST request.
	 */
	public function update_attempt_report_manually( $quiz, $quiz_attempt_answer_id, $request ) {
		global $wpdb;
		$attempt_id             = isset( $request['attempt_id'] ) ? (int) $request['attempt_id'] : '';
		$quiz_attempt_answer_id = isset( $request['quiz_attempt_answer_id'] ) ? (int) $request['quiz_attempt_answer_id'] : '';
		$wpdb->update(
			"{$wpdb->prefix}ohmylms_quiz_attempts_answers",
			array(
				'is_manually_reviewed' => 1,
				'achive_mark'          => $request['marks'],
				'is_correct'           => 1,
			),
			array(
				'question_id'     => $quiz_attempt_answer_id,
				'quiz_attempt_id' => $attempt_id,
			),
			array(
				'%d',
				'%d',
			),
			array(
				'%d',
				'%d',
			)
		);
		$wpdb->update(
			"{$wpdb->prefix}ohmylms_quiz_attempts",
			array(
				'status' => 'completed',
			),
			array(
				'id' => $request['attempt_id'],
			),
			array(
				'%s',
			),
			array(
				'%d',
			)
		);
	}

	/**
	 * Review question for Quiz store.
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $question_id Question identifier.
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $data Input data.
	 */
	public function review_question( &$quiz, $question_id, $attempt_id, $data ) {
		if ( isset( $data['achive_mark'] ) ) {
			$achieve_mark = $data['achive_mark'];
			global $wpdb;

			$wpdb->update(
				"{$wpdb->prefix}ohmylms_quiz_attempts_answers",
				array(
					'is_manually_reviewed' => 1,
					'achive_mark'          => $achieve_mark,
					'is_correct'           => 1,
				),
				array(
					'question_id'     => $question_id,
					'quiz_attempt_id' => $attempt_id,
				),
				array(
					'%d',
					'%d',
				),
				array(
					'%d',
					'%d',
				)
			);
		}
	}
}
