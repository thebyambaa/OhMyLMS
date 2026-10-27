<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Template data and rendering context.
 *
 * @access public
 * @package OhMyLMS\Migrations
 *
 * @author OhMyLMS
 * @since 1.0.0
 */
namespace OhMyLMS\Migrations;

/**
 * Migration class for LearnDash.
 * This class is responsible for migrating LearnDash courses to OhMyLMS.
 *
 * @author OhMyLMS
 * @since 1.0.0
 */
class LearnDash {

	/**
	 * LearnDash course id.
	 *
	 * @var int
	 */
	public $ld_course_id = null;

	/**
	 * LearnDash course data.
	 *
	 * @var array
	 */
	public $ld_course = null;

	/**
	 * Initializes the migration process for a specific LearnDash course.
	 *
	 * @param int|null $ld_course_id The ID of the LearnDash course to migrate.
	 * @return bool Returns true if the initialization was successful, false if not.
	 */
	public function init( $ld_course_id = null ) {
		if ( ! $this->should_init() ) {
			return false;
		}

		$this->ld_course_id = $ld_course_id;

		return true;
	}


	/**
	 * Checks if the migration should proceed based on the existence of the LearnDash LMS plugin.
	 *
	 * This method ensures that the migration or initialization process only runs if the LearnDash LMS
	 * plugin is active, indicated by the defined 'LEARNDASH_VERSION' constant.
	 *
	 * @return bool Returns true if the LearnDash LMS plugin is active, false otherwise.
	 */
	private function should_init() {
		// Check if the LEARNDASH_VERSION constant is defined, indicating that the plugin is active.
		return defined( 'LEARNDASH_VERSION' );
	}

	/**
	 * Migrates course data from LearnDash to OhMyLMS.
	 *
	 * @return \WP_Error|int Returns the new OhMyLMS course ID on success or WP_Error on failure.
	 */
	public function migrate_course() {

		// Fetch the course data.
		$this->ld_course = $this->get_course_data();
		// Check if fetching course data resulted in an error.
		if ( is_wp_error( $this->ld_course ) ) {
			return $this->ld_course; // Return error if data fetch failed.
		}

		// Prepare the course data for insertion.
		$post_data = array(
			'post_title'    => sanitize_text_field( $this->ld_course['post_title'] ),
			'post_content'  => wp_kses_post( $this->ld_course['post_content'] ),
			'post_excerpt'  => isset( $this->ld_course['post_excerpt'] ) ? sanitize_text_field( $this->ld_course['post_excerpt'] ) : '',
			'post_status'   => 'draft',
			'post_author'   => isset( $this->ld_course['post_author'] ) ? intval( $this->ld_course['post_author'] ) : get_current_user_id(),
			'post_type'     => 'ohmylms-course', // Custom post type for OhMyLMS course.
			'post_date'     => isset( $this->ld_course['post_date'], $this->ld_course['post_status'] ) && 'future' === $this->ld_course['post_status'] ? gmdate( 'Y-m-d H:i:s', strtotime( $this->ld_course['post_date'] ) ) : current_datetime()->format( 'Y-m-d H:i:s' ),
			'post_password' => isset( $this->ld_course['post_password'] ) ? $this->ld_course['post_password'] : '',
		);

		// Insert the course post into WordPress.
		$new_course_id = wp_insert_post( $post_data );
		if ( is_wp_error( $new_course_id ) ) {
			return $new_course_id; // Return error if insertion failed.
		}

		// If the course has a password, set the access type to 'password_protected'.
		if ( ! empty( $post_data['post_password'] ) ) {
			update_post_meta( $new_course_id, '_access_type', 'password_protected' );
		} else {
			update_post_meta( $new_course_id, '_access_type', 'public' ); // Default to public if no password.
		}

		// Migrate course metadata if available.
		if ( ! empty( $this->ld_course['meta'] ) && is_array( $this->ld_course['meta'] ) ) {
			$this->migrate_course_metadata( $new_course_id, $this->ld_course['meta'] );
		}

		// Migrate course terms and taxonomies.
		$this->migrate_terms_and_taxonomies( $new_course_id );

		// Migrate course chapters (lessons or sections).
		$this->migrate_chapters( $new_course_id );

		$this->migrate_students( $new_course_id );
	}


	/**
	 * Migrates course metadata from learnDash LMS to OhMyLMS.
	 *
	 * This method transfers relevant metadata from the old course to the new course
	 * in OhMyLMS, including price, duration, level, and course settings.
	 *
	 * @param int   $new_course_id The ID of the newly created course in OhMyLMS.
	 * @param array $meta_data The metadata from the old course to be migrated.
	 */
	private function migrate_course_metadata( $new_course_id, $meta_data ) {
		// Loop through the metadata of the old course.
		foreach ( $meta_data as $key => $value ) {

			// Migrate the course price type.
			if ( '_ld_price_type' === $key ) {
				if ( in_array( $value[0], array( 'free', 'paid', 'closed' ) ) ) {
					$actual_value = 'closed' === $value[0] ? 'paid' : $value[0];
					update_post_meta( $new_course_id, '_price_type', $actual_value );
				} else {
					update_post_meta( $new_course_id, '_price_type', 'free' ); // Default to free if not recognized.
				}
			}

			// Migrate the course thumbnail (featured image).
			if ( '_thumbnail_id' === $key ) {
				update_post_meta( $new_course_id, '_thumbnail_id', $value[0] );
			}

			if ( '_sfwd-courses' === $key ) {
				$data = maybe_unserialize( $value[0] );
				if ( is_array( $data ) ) {
					foreach ( $data as $meta_key => $meta_value ) {
						// Migrate specific course metadata.
						switch ( $meta_key ) {
							case 'sfwd-courses_course_price':
								update_post_meta( $new_course_id, '_regular_price', $meta_value );
								$sale_price = get_post_meta( $new_course_id, '_price', true );
								if ( ! $sale_price ) {
									update_post_meta( $new_course_id, '_price', $meta_value );
								}
								break;
							case 'sfwd-courses_course_seats_limit':
								if ( $meta_value > 0 ) {
									update_post_meta( $new_course_id, '_has_capacity', true ); // Migrate capacity flag.
									update_post_meta( $new_course_id, '_capacity', $meta_value ); // Migrate maximum students.
								}
								break;
						}
					}
				}
			}

			if ( '_learndash_course_grid_duration' === $key ) {
				if ( $value[0] ) {
					$duration_second = $value[0];
					$hours           = floor( $duration_second / HOUR_IN_SECONDS );
					$minutes         = floor( ( $duration_second % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

					$new_duration = array();
					if ( $hours > 0 ) {
						$new_duration['hour'] = strval( $hours ); // Migrate hours.
					}
					if ( $minutes > 0 ) {
						$new_duration['min'] = strval( $minutes ); // Migrate minutes.
					}
					update_post_meta( $new_course_id, '_duration', $new_duration );
				}
			}
		}
	}


	/**
	 * Migrate course terms and taxonomies.
	 *
	 * @param int $new_course_id The newly created course ID.
	 * @since 1.0.0
	 */
	private function migrate_terms_and_taxonomies( $new_course_id ) {
		// Get all terms associated with the LearnDash course.
		$terms = wp_get_post_terms( $this->ld_course_id, array( 'ld_course_category', 'ld_course_tag' ) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		// LearnDash categories become curriculum items (keeping their parent chain) and LearnDash tags
		// become Course Bundles. Items and bundles with the same name are reused, so migrating many
		// courses does not duplicate structure.
		$curriculum_paths = array();
		$bundle_titles    = array();

		foreach ( $terms as $term ) {
			if ( 'ld_course_category' === $term->taxonomy ) {
				$curriculum_paths[] = \OhMyLMS\Curriculum\Placement::term_path( $term );
			} elseif ( 'ld_course_tag' === $term->taxonomy ) {
				$bundle_titles[] = $term->name;
			}
		}

		\OhMyLMS\Curriculum\Placement::import_categories( $new_course_id, $curriculum_paths );
		\OhMyLMS\Curriculum\Placement::import_tags( $new_course_id, $bundle_titles );
	}
	/**
	 * Migrates chapters (topics) from learnDash LMS to OhMyLMS for a given course.
	 *
	 * This method retrieves all chapters associated with the source course and
	 * inserts them into the OhMyLMS system. It also maintains the order and
	 * associates each chapter with the newly created course. After the chapters are
	 * migrated, it proceeds to migrate any lessons associated with the chapters.
	 *
	 * @param int $new_course_id The ID of the newly created course in OhMyLMS.
	 */
	private function migrate_chapters( $new_course_id ) {
		global $wpdb;

		$total_data = \LDLMS_Factory_Post::course_steps( $this->ld_course_id );
		$total_data = $total_data->get_steps();

		if ( empty( $total_data ) ) {
			return;
		}

		$i              = 0;
		$section_count  = 0;
		$new_chapter_id = 0;
		foreach ( $total_data['sfwd-lessons'] as $lesson_key => $lesson_data ) {

			$author_id = get_post_field( 'post_author', $this->ld_course_id );
			$chapter   = get_post( $lesson_key );
			if ( ! $chapter ) {
				continue; // Skip if chapter does not exist.
			}
			$new_chapter_data = array(
				'post_title'   => sanitize_text_field( $chapter->post_title ),
				'post_content' => wp_kses_post( $chapter->post_content ),
				'post_excerpt' => sanitize_text_field( $chapter->post_excerpt ),
				'post_status'  => $chapter->post_status, // Retain original status.
				'post_author'  => intval( $chapter->post_author ),
				'post_type'    => 'ohmylms-chapter', // Custom post type for OhMyLMS chapters.
				'post_parent'  => 0, // Chapters in OhMyLMS don't have parents.
			);

			// Insert the new chapter into the database.
			$new_chapter_id = wp_insert_post( $new_chapter_data );

			// If insertion fails, skip this chapter and continue with the next one.
			if ( is_wp_error( $new_chapter_id ) ) {
				continue; // Skip chapter if insertion failed.
			}

			// Insert the relationship between the new course and chapter into the custom table.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				array(
					'course_id'    => $new_course_id,  // New course ID.
					'chapter_id'   => $new_chapter_id, // New chapter ID.
					'order_number' => $i,          // Maintain chapter order.
				),
				array( '%d', '%d', '%d' )
			);

			// Migrate the chapter metadata.
			$topics = learndash_course_get_topics( $this->ld_course_id, $chapter->ID );
			if ( is_array( $topics ) && ! empty( $topics ) ) {
				$lesson_index = 0;
				$quiz_index   = 0;
				foreach ( $topics as $topic_id => $topic_data ) {
					if ( 'sfwd-topic' === $topic_data->post_type ) {
						$this->migrate_lesson( $new_chapter_id, $topic_id, $lesson_index );
						++$lesson_index;
					}
				}
			}

			$chapter_quizzes = learndash_course_get_quizzes(
				$this->ld_course_id,
				$chapter->ID,
				array(
					'return_type' => 'WP_Post',
					'per_page'    => 0,
				)
			);

			if ( ( is_array( $chapter_quizzes ) ) && ( ! empty( $chapter_quizzes ) ) ) {
				// Loop lesson's quizzes.
				foreach ( $chapter_quizzes as $quiz ) {
					if ( ! is_a( $quiz, 'WP_Post' ) ) {
						continue;
					}

					$this->migrate_quiz( $new_chapter_id, $quiz, $quiz_index );
					++$quiz_index;
				}
			}
			++$i;
		}
	}

	/**
	 * Migrates quizzes from the old chapter to the new chapter in OhMyLMS.
	 *
	 * This method retrieves all quizzes associated with the old chapter and inserts them
	 * as new quizzes into the OhMyLMS system. The quiz metadata is migrated as well,
	 * and the relationship between the new chapter and quizzes is established.
	 *
	 * @param int     $new_chapter_id The ID of the newly created chapter in OhMyLMS.
	 * @param WP_Post $quiz The quiz post object from the old chapter.
	 * @param int     $index The index of the quiz in the chapter, used for maintaining order.
	 */
	private function migrate_quiz( $new_chapter_id, $quiz, $index = 0 ) {
		global $wpdb;

		$new_quiz_data = array(
			'post_title'   => sanitize_text_field( $quiz->post_title ),
			'post_content' => wp_kses_post( $quiz->post_content ),
			'post_excerpt' => sanitize_text_field( $quiz->post_excerpt ),
			'post_status'  => $quiz->post_status, // Retain the quiz's status.
			'post_author'  => intval( $quiz->post_author ),
			'post_type'    => 'ohmylms-quiz', // Custom post type for OhMyLMS quizzes.
			'post_parent'  => 0, // Reset parent (quizzes in OhMyLMS don't have a parent chapter).
		);

		// Insert the new quiz into the database.
		$new_quiz_id = wp_insert_post( $new_quiz_data );

		// If quiz insertion fails, skip to the next one.
		if ( is_wp_error( $new_quiz_id ) ) {
			return;
		}

		// Insert the lesson-content relationship into the custom table.
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $new_chapter_id,  // ID of the new chapter.
				'content_id'   => $new_quiz_id,   // ID of the new lesson.
				'content_type' => 'quiz', // If no type, default to 'text'.
				'order_number' => $index,           // Maintain lesson order.
			),
			array( '%d', '%d', '%s', '%d' ) // SQL formatting placeholders.
		);

		$this->migrate_quiz_metadata( $new_quiz_id, get_post_meta( $quiz->ID ) );
		// Migrate quiz questions and answers.
		$this->migrate_quiz_questions( $new_quiz_id, $quiz->ID );
	}


	/**
	 * Migrates quiz metadata from Tutor LMS to OhMyLMS.
	 *
	 * This function transfers quiz-specific metadata, including settings,
	 * question types, and question data, from the old system (Tutor LMS) to
	 * the new system (OhMyLMS).
	 *
	 * @param int   $new_quiz_id The ID of the newly created quiz in OhMyLMS.
	 * @param array $meta_data The metadata from the old quiz to be migrated.
	 * .
	 * @since 1.0.0
	 * @return void
	 */
	private function migrate_quiz_metadata( $new_quiz_id, $meta_data ) {
		// Migrate quiz metadata.
		$new_settings = array(
			'layout'               => 'number_of_questions_per_page',
			'allow_attempts'       => '',
			'question_in_one_page' => '5',
			'time_limit'           => array(
				'value' => '0',
				'type'  => 'minutes',
			),
			'passing_grade'        => array(
				'enabled' => false,
				'value'   => '',
			),
			'hide_answers'         => true,
			'move_to_next_section' => true,
			'randomize_questions'  => false,
			'hide_question_number' => true,
			'short_text_limit'     => 200,
			'long_text_limit'      => 500,
		);

		// Loop through the metadata of the old course.
		foreach ( $meta_data as $key => $value ) {
			if ( '_sfwd-quiz' === $key ) {
				$settings = maybe_unserialize( $value[0] );
				if ( is_array( $settings ) && ! empty( $settings ) ) {
					foreach ( $settings  as $key => $meta_value ) {
						if ( 'sfwd-quiz_retry_restrictions' === $key ) {
							if ( isset( $settings['sfwd-quiz_repeats'] ) && $settings['sfwd-quiz_repeats'] > 0 ) {
								$new_settings['allow_attempts'] = $settings['sfwd-quiz_repeats'];
							}
						} elseif ( 'sfwd-quiz_questionRandom' === $key && $meta_value ) {
							$new_settings['randomize_questions'] = true;
						} elseif ( 'sfwd-quiz_quizModus_multiple_questionsPerPage' === $key ) {
							$layout = 'one_question_per_page';
							if ( 1 === $meta_value ) {
								$layout = 'one_question_per_page';
							} elseif ( 1 < $meta_value ) {
								$layout = 'number_of_questions_per_page';
							}
							$new_settings['layout']               = $layout;
							$new_settings['question_in_one_page'] = $meta_value;
						} elseif ( 'sfwd-quiz_passingpercentage' === $key ) {

							$new_settings['passing_grade'] = array(
								'enabled' => true,
								'value'   => $meta_value,
							);
						} elseif ( 'sfwd-quiz_quiz_time_limit_enabled' === $key && 'on' === $meta_value ) {
							$minutes = floor( $settings['sfwd-quiz_timeLimit'] / MINUTE_IN_SECONDS );

							$new_settings['time_limit'] = array(
								'value' => $minutes,
								'type'  => 'minutes',
							);
						}
					}
				}
			}
		}
		update_post_meta( $new_quiz_id, '_quiz_settings', $new_settings );
	}


	/**
	 * Migrates quiz questions from the old quiz to the new quiz in OhMyLMS.
	 *
	 * This method retrieves all questions associated with the old quiz and inserts them
	 * as new questions into the OhMyLMS system. The question metadata is migrated as well,
	 * and the relationship between the new quiz and questions is established.
	 *
	 * @param int $new_quiz_id The ID of the newly created quiz in OhMyLMS.
	 * @param int $old_quiz_id The ID of the old quiz from the LearnDash LMS system.
	 */
	private function migrate_quiz_questions( $new_quiz_id, $old_quiz_id ) {
		global $wpdb;
		/**
		 * After we move the primary marker we also need to move the questions.
		 */
		$questions = get_post_meta( $old_quiz_id, 'ld_quiz_questions', true );
		if ( $questions ) {
			foreach ( $questions as $key => $id ) {
				// Fetch question data from wp_learndash_pro_quiz_question.
				$question_data = $wpdb->get_row(
					$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $wpdb->prefix . 'learndash_pro_quiz_question', $id ),
					ARRAY_A
				);

				if ( $question_data ) {
					if ( isset( $question_data['answer_data'] ) ) {
						$question_data['answer_data'] = maybe_unserialize( $question_data['answer_data'] );
					}

					// Insert question as a new post of type 'ohmylms-question'.
					$question_post = array(
						'post_title'   => wp_strip_all_tags( $question_data['title'] ),
						'post_content' => $question_data['question'],
						'post_status'  => 'publish',
						'post_type'    => 'ohmylms-question',
					);

					$new_question_id = wp_insert_post( $question_post );

					if ( ! $new_question_id ) {
						continue; // Skip if question creation fails.
					}

					// Insert relationship into `ohmylms_quiz_questions_relationship`.
					$wpdb->insert(
						"{$wpdb->prefix}ohmylms_quiz_questions_relationship",
						array(
							'quiz_id'      => $new_quiz_id,
							'question_id'  => $new_question_id,
							'order_number' => $question_post['sort'] ?? 0,
						),
						array( '%d', '%d', '%d' )
					);

					$this->migrate_question_metadata( $new_question_id, $question_data );
					$this->migrate_answers( $new_question_id, $question_data['answer_data'] );

				}
			}
		}
	}


	/**
	 * Migrates question metadata from LearnDash LMS to OhMyLMS.
	 *
	 * This function transfers question-specific metadata, including type,
	 * required status, randomization, and score, from the old system (LearnDash LMS)
	 * to the new system (OhMyLMS).
	 *
	 * @param int   $new_question_id The ID of the newly created question in OhMyLMS.
	 * @param array $meta_data The metadata from the old question to be migrated.
	 * .
	 * @since 1.0.0
	 * @return void
	 */
	private function migrate_question_metadata( $new_question_id, $meta_data ) {
		$new_settings = array(
			'type'      => 'short-text',
			'required'  => false,
			'randomize' => false,
			'score'     => array(
				'enabled' => true,
				'value'   => 1,
			),
		);
		if ( isset( $meta_data['answer_type'] ) ) {
			if ( 'single' === $meta_data['answer_type'] ) {
				$new_settings['type'] = 'single-choice';
			}
			if ( 'multiple' === $meta_data['answer_type'] ) {
				$new_settings['type'] = 'multiple-choice';
			}
			if ( 'essay' === $meta_data['answer_type'] ) {
				$new_settings['type'] = 'long-text';
			}
			if ( 'cloze_answer' === $meta_data['answer_type'] ) {
				$new_settings['type'] = 'fill-in-the-blank';
			}
		}
		if ( isset( $meta_data['points'] ) ) {
			$new_settings['score'] = array(
				'enabled' => true,
				'value'   => $meta_data['points'],
			);
		}
		update_post_meta( $new_question_id, '_question_settings', $new_settings );
	}


	/**
	 * Migrates answers from LearnDash LMS to OhMyLMS.
	 *
	 * This function transfers answers associated with a question from the old system (LearnDash LMS)
	 * to the new system (OhMyLMS).
	 *
	 * @param int   $new_question_id The ID of the newly created question in OhMyLMS.
	 * @param mixed $answers Answers.
	 * @since 1.0.0
	 * @return void
	 */
	private function migrate_answers( $new_question_id, $answers ) {
		global $wpdb;

		if ( empty( $answers ) ) {
			return;
		}

		foreach ( $answers as $answer ) {
			$wpdb->insert(
				"{$wpdb->prefix}ohmylms_question_answers",
				array(
					'question_id'  => $new_question_id,
					'answer'       => $answer->getAnswer(),
					'order_number' => $answer->getSortString() ?? 0,
					'is_correct'   => $answer->isCorrect() ?? 0,
				),
				array( '%d', '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * Migrates lessons from the old chapter to the new chapter in OhMyLMS.
	 *
	 * This method retrieves all lessons associated with the old chapter and inserts them
	 * as new lessons into the OhMyLMS system. The lesson metadata is migrated as well,
	 * and the relationship between the new chapter and lessons is established. If the lesson
	 * has a video or other content types, they are properly handled.
	 *
	 * @param int   $new_chapter_id The ID of the newly created chapter in OhMyLMS.
	 * @param mixed $old_lesson_id Old lesson identifier.
	 * @param int   $index The index of the lesson in the chapter, used for maintaining order.
	 */
	private function migrate_lesson( $new_chapter_id, $old_lesson_id, $index = 0 ) {
		global $wpdb;

		$lesson = get_post( $old_lesson_id );
		if ( ! $lesson ) {
			return;
		}

		$new_lesson_data = array(
			'post_title'   => sanitize_text_field( $lesson->post_title ),
			'post_content' => wp_kses_post( $lesson->post_content ),
			'post_excerpt' => sanitize_text_field( $lesson->post_excerpt ),
			'post_status'  => $lesson->post_status, // Retain the lesson's status.
			'post_author'  => intval( $lesson->post_author ),
			'post_type'    => 'ohmylms-lesson', // Custom post type for OhMyLMS lessons.
			'post_parent'  => 0, // Reset parent (lessons in OhMyLMS don't have a parent chapter).
		);

		// Insert the new lesson into the database.
		$new_lesson_id = wp_insert_post( $new_lesson_data );

		// If lesson insertion fails, skip to the next one.
		if ( is_wp_error( $new_lesson_id ) ) {
			return;
		}

		// Insert the lesson-content relationship into the custom table.
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $new_chapter_id,  // ID of the new chapter.
				'content_id'   => $new_lesson_id,   // ID of the new lesson.
				'content_type' => 'text', // If no type, default to 'text'.
				'order_number' => $index,           // Maintain lesson order.
			),
			array( '%d', '%d', '%s', '%d' ) // SQL formatting placeholders.
		);

		// Get metadata associated with the lesson.
		$meta_data = get_post_meta( $old_lesson_id );

		// Migrate lesson metadata.
		$this->migrate_lesson_metadata( $new_lesson_id, $meta_data );
	}


	/**
	 * Migrates lesson metadata from Tutor LMS to OhMyLMS.
	 *
	 * This function transfers lesson-specific metadata, including attachments,
	 * video links, and thumbnail images, from the old system (Tutor LMS) to
	 * the new system (OhMyLMS).
	 *
	 * @param int   $new_lesson_id The ID of the newly created lesson in OhMyLMS.
	 * @param array $meta_data The metadata from the old lesson to be migrated.
	 */
	private function migrate_lesson_metadata( $new_lesson_id, $meta_data ) {
		// Loop through the lesson metadata and migrate each key-value pair.
		foreach ( $meta_data as $key => $value ) {

			// Migrate the lesson thumbnail (featured image).
			if ( '_thumbnail_id' === $key ) {
				update_post_meta( $new_lesson_id, '_thumbnail_id', $value[0] );
				update_post_meta( $new_lesson_id, '_cover_image_id', $value[0] );
			}
		}
	}

	/**
	 * Get course data.
	 *
	 * @return array|\WP_Error The course data or error object.
	 * @since 1.0.0
	 */
	private function get_course_data() {
		if ( ! $this->ld_course_id ) {
			return new \WP_Error( 'no_course_id', 'No course ID provided.' );
		}

		// Get course post data.
		$course = get_post( $this->ld_course_id );
		if ( ! $course || 'sfwd-courses' !== $course->post_type ) {
			return new \WP_Error( 'invalid_course', 'Invalid course ID or post type.' );
		}

		// Get course meta data.
		$course_meta = get_post_meta( $this->ld_course_id );

		// Prepare course data.
		$course_data = array(
			'ID'            => $course->ID,
			'post_title'    => $course->post_title,
			'post_content'  => $course->post_content,
			'post_status'   => $course->post_status,
			'post_excerpt'  => $course->post_excerpt,
			'post_author'   => $course->post_author,
			'post_date'     => $course->post_date,
			'post_password' => $course->post_password,
			'meta'          => $course_meta,
		);

		return $course_data;
	}


	/**
	 * Migrates students from the old course to the new course in OhMyLMS.
	 *
	 * This method retrieves all students enrolled in the old course and enrolls them
	 * in the newly created course in OhMyLMS. It establishes the relationship
	 * between the students and the new course.
	 *
	 * @param int $new_course_id The ID of the newly created course in OhMyLMS.
	 */
	private function migrate_students( $new_course_id ) {
		$enrolled_students_ids = $this->get_enrolled_students_ids();
		if ( empty( $enrolled_students_ids ) ) {
			return; // No students to migrate.
		}
		global $wpdb;
		// Insert the relationship between the new course and students into the custom table.
		foreach ( $enrolled_students_ids as $user_id ) {
			$this->enroll_student( $user_id, $new_course_id );
		}
	}


	/**
	 * Enrolls a student in a course.
	 *
	 * This function enrolls a student in a course by adding their enrollment data to the database.
	 *
	 * @param int $student_id The ID of the student to enroll.
	 * @param int $course_id The ID of the course to enroll the student in.
	 * .
	 * @since 1.0.0
	 * @return void
	 */
	private function enroll_student( $student_id, $course_id ) {
		global $wpdb;
		$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';

		$enrollment_data = array(
			'course_id'  => $course_id,
			'user_id'    => $student_id,
			'status'     => 'enrolled',
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		);
		$student         = new \OhMyLMS\Data\Student( $student_id );

		if ( $student && ! $student->maybe_enrolled( $course_id ) ) {
			// Check if the record exists.
			$existing_record = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $wpdb->prefix . 'ohmylms_user_enrollment', $student_id, $course_id )
			);

			if ( ! $existing_record ) {
				$wpdb->insert(
					$enrollment_table,
					$enrollment_data
				);
			}

			update_user_meta( $student_id, '_is_ohmylms_student', 'yes' );
		}
	}


	/**
	 * Get enrolled students IDs for the course.
	 *
	 * This method retrieves the IDs of all students enrolled in the course.
	 * It checks both direct enrollments and group enrollments if applicable.
	 *
	 * @return array An array of user IDs of enrolled students.
	 */
	private function get_enrolled_students_ids() {
		global $wpdb;
		$course_groups_ids = learndash_get_course_groups( $this->ld_course_id );
		$meta_keys         = array( 'course_' . $this->ld_course_id . '_access_from' );
		if ( ! empty( $course_groups_ids ) ) {
			foreach ( $course_groups_ids as $group_id ) {
				$meta_keys[] = "learndash_group_users_{$group_id}";
			}
		}

		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ($placeholders)",
			...$meta_keys
		);

		$results = $wpdb->get_col( $sql );

		return array_map( 'intval', $results );
	}
}
