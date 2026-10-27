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
 * Migration class for LearnPress.
 * This class is responsible for migrating LearnPress courses to OhMyLMS.
 *
 * @author OhMyLMS
 * @since 1.0.0
 */
class LearnPress {

	/**
	 * LearnPress course id.
	 *
	 * @var int
	 */
	public $lp_course_id = null;

	/**
	 * LearnPress course data.
	 *
	 * @var array
	 */
	public $lp_course = null;

	/**
	 * Initializes the migration process for a specific LearnPress course.
	 *
	 * @param int|null $lp_course_id The ID of the LearnPress course to migrate.
	 * @return bool Returns true if the initialization was successful, false if not.
	 */
	public function init( $lp_course_id = null ) {
		if ( ! $this->should_init() ) {
			return false;
		}

		$this->lp_course_id = $lp_course_id;

		return true;
	}


	/**
	 * Checks if the migration should proceed based on the existence of the LearnPress plugin.
	 *
	 * This method ensures that the migration or initialization process only runs if the LearnPress
	 * plugin is active, indicated by the defined 'LEARNPRESS_VERSION' constant.
	 *
	 * @return bool Returns true if the LearnPress plugin is active, false otherwise.
	 */
	private function should_init() {
		// Check if the LEARNPRESS_VERSION constant is defined, indicating that the plugin is active.
		return defined( 'LEARNPRESS_VERSION' );
	}

	/**
	 * Migrates course data from LearnPress to OhMyLMS.
	 *
	 * @return \WP_Error|int Returns the new OhMyLMS course ID on success or WP_Error on failure.
	 */
	public function migrate_course() {

		// Fetch the course data.
		$this->lp_course = $this->get_course_data();
		// Check if fetching course data resulted in an error.
		if ( is_wp_error( $this->lp_course ) ) {
			return $this->lp_course;
		}

		// Prepare the course data for insertion.
		$post_data = array(
			'post_title'    => sanitize_text_field( $this->lp_course['post_title'] ),
			'post_content'  => wp_kses_post( $this->lp_course['post_content'] ),
			'post_excerpt'  => isset( $this->lp_course['post_excerpt'] ) ? sanitize_text_field( $this->lp_course['post_excerpt'] ) : '',
			'post_status'   => 'draft',
			'post_author'   => isset( $this->lp_course['post_author'] ) ? intval( $this->lp_course['post_author'] ) : get_current_user_id(),
			'post_type'     => 'ohmylms-course', // Custom post type for OhMyLMS course.
			'post_date'     => isset( $this->lp_course['post_date'], $this->lp_course['post_status'] ) && 'future' === $this->lp_course['post_status'] ? gmdate( 'Y-m-d H:i:s', strtotime( $this->lp_course['post_date'] ) ) : current_datetime()->format( 'Y-m-d H:i:s' ),
			'post_password' => isset( $this->lp_course['post_password'] ) ? $this->lp_course['post_password'] : '',
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
		if ( ! empty( $this->lp_course['meta'] ) && is_array( $this->lp_course['meta'] ) ) {
			$this->migrate_course_metadata( $new_course_id, $this->lp_course['meta'] );
		}

		// Migrate course terms and taxonomies.
		$this->migrate_terms_and_taxonomies( $new_course_id );

		// Migrate course sections (chapters in OhMyLMS).
		$this->migrate_sections( $new_course_id );

		// Migrate enrolled students.
		$this->migrate_students( $new_course_id );

		return $new_course_id;
	}


	/**
	 * Migrates course metadata from LearnPress to OhMyLMS.
	 *
	 * This method transfers relevant metadata from the old course to the new course
	 * in OhMyLMS, including price, duration, level, and course settings.
	 *
	 * @param int   $new_course_id The ID of the newly created course in OhMyLMS.
	 * @param array $meta_data The metadata from the old course to be migrated.
	 */
	private function migrate_course_metadata( $new_course_id, $meta_data ) {
		// Migrate regular price first.
		if ( isset( $meta_data['_lp_regular_price'][0] ) && ! empty( $meta_data['_lp_regular_price'][0] ) && $meta_data['_lp_regular_price'][0] > 0 ) {
			$regular_price = $meta_data['_lp_regular_price'][0];
			update_post_meta( $new_course_id, '_regular_price', $regular_price );
			update_post_meta( $new_course_id, '_price', $regular_price );
			update_post_meta( $new_course_id, '_price_type', 'paid' );
		} else {
			update_post_meta( $new_course_id, '_price_type', 'free' );
		}

		// Migrate sale price (this will override _price if sale price exists).
		if ( isset( $meta_data['_lp_sale_price'][0] ) && ! empty( $meta_data['_lp_sale_price'][0] ) && $meta_data['_lp_sale_price'][0] > 0 ) {
			update_post_meta( $new_course_id, '_sale_price', $meta_data['_lp_sale_price'][0] );
			update_post_meta( $new_course_id, '_price', $meta_data['_lp_sale_price'][0] );
		}

		// Loop through the metadata of the old course.
		foreach ( $meta_data as $key => $value ) {
			// Migrate the course thumbnail (featured image).
			if ( '_thumbnail_id' === $key ) {
				update_post_meta( $new_course_id, '_thumbnail_id', $value[0] );
			}

			// Migrate course duration.
			if ( '_lp_duration' === $key && ! empty( $value[0] ) ) {
				$lp_duration  = $value[0];
				$new_duration = $this->convert_duration( $lp_duration );
				if ( ! empty( $new_duration ) ) {
					update_post_meta( $new_course_id, '_duration', $new_duration );
				}
			}

			// Migrate max students.
			if ( '_lp_max_students' === $key && ! empty( $value[0] ) && $value[0] > 0 ) {
				update_post_meta( $new_course_id, '_has_capacity', true );
				update_post_meta( $new_course_id, '_capacity', $value[0] );
			}

			// Migrate passing grade.
			if ( '_lp_passing_condition' === $key && ! empty( $value[0] ) ) {
				$passing_grade = array(
					'enabled' => true,
					'value'   => $value[0],
				);
				update_post_meta( $new_course_id, '_passing_grade', $passing_grade );
			}

			// Migrate retake count.
			if ( '_lp_retake_count' === $key && ! empty( $value[0] ) ) {
				update_post_meta( $new_course_id, '_retake_count', $value[0] );
			}
		}
	}

	/**
	 * Convert LearnPress duration format to OhMyLMS format.
	 *
	 * LearnPress format: "10 weeks", "5 days", "3 hours", etc.
	 * OhMyLMS format: array with 'week', 'day', 'hour', 'min'
	 *
	 * @param string $lp_duration LearnPress duration string.
	 * @return array OhMyLMS duration array.
	 */
	private function convert_duration( $lp_duration ) {
		$duration = array();

		if ( empty( $lp_duration ) || '0' === $lp_duration ) {
			return $duration;
		}

		// Parse the duration string.
		$parts = explode( ' ', trim( $lp_duration ) );
		if ( count( $parts ) >= 2 ) {
			$value = intval( $parts[0] );
			$unit  = strtolower( $parts[1] );

			// Map LearnPress units to OhMyLMS units.
			$unit_map = array(
				'week'    => 'week',
				'weeks'   => 'week',
				'day'     => 'day',
				'days'    => 'day',
				'hour'    => 'hour',
				'hours'   => 'hour',
				'minute'  => 'min',
				'minutes' => 'min',
			);

			if ( isset( $unit_map[ $unit ] ) && $value > 0 ) {
				$duration[ $unit_map[ $unit ] ] = strval( $value );
			}
		}

		return $duration;
	}

	/**
	 * Migrates terms and taxonomies from LearnPress to OhMyLMS.
	 *
	 * This method transfers course categories and tags from the old course to the new course.
	 *
	 * @param int $new_course_id The ID of the newly created course in OhMyLMS.
	 */
	private function migrate_terms_and_taxonomies( $new_course_id ) {
		// LearnPress categories become curriculum items (keeping their parent chain) and its tags
		// become Course Bundles.
		$categories = wp_get_post_terms( $this->lp_course_id, 'course_category' );
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$paths = array();
			foreach ( $categories as $category ) {
				$paths[] = \OhMyLMS\Curriculum\Placement::term_path( $category );
			}
			\OhMyLMS\Curriculum\Placement::import_categories( $new_course_id, $paths );
		}

		$tags = wp_get_post_terms( $this->lp_course_id, 'course_tag', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			\OhMyLMS\Curriculum\Placement::import_tags( $new_course_id, $tags );
		}
	}

	/**
	 * Migrates sections (chapters) from LearnPress to OhMyLMS.
	 *
	 * This method retrieves all sections from the LearnPress course and creates
	 * corresponding chapters in OhMyLMS, then migrates lessons and quizzes.
	 *
	 * @param int $new_course_id The ID of the newly created course in OhMyLMS.
	 */
	private function migrate_sections( $new_course_id ) {
		global $wpdb;

		// Get LearnPress course object.
		$lp_course = learn_press_get_course( $this->lp_course_id );
		if ( ! $lp_course ) {
			return;
		}

		// Try get_sections_data_arr() method which returns proper array format.
		$curriculum = array();
		if ( method_exists( $lp_course, 'get_sections_data_arr' ) ) {
			$curriculum = $lp_course->get_sections_data_arr();
		} elseif ( method_exists( $lp_course, 'get_curriculum_raw' ) ) {
			$curriculum = $lp_course->get_curriculum_raw();
		}

		if ( empty( $curriculum ) ) {
			return;
		}

		$author_id = get_post_field( 'post_author', $this->lp_course_id );
		$i         = 0;

		foreach ( $curriculum as $section ) {
			// Check if section is an array.
			if ( ! is_array( $section ) ) {
				continue;
			}

			// Get section properties - try multiple key names for compatibility.
			$section_id          = $section['section_id'] ?? $section['id'] ?? 0;
			$section_title       = $section['section_name'] ?? $section['title'] ?? __( 'Section', 'ohmylms' );
			$section_description = $section['section_description'] ?? $section['description'] ?? '';

			if ( ! $section_id ) {
				continue;
			}

			// Create new chapter (section in OhMyLMS).
			$new_chapter_data = array(
				'post_title'   => sanitize_text_field( $section_title ),
				'post_content' => wp_kses_post( $section_description ),
				'post_status'  => 'publish',
				'post_author'  => $author_id,
				'post_type'    => 'ohmylms-chapter',
			);

			// Insert the new chapter.
			$new_chapter_id = wp_insert_post( $new_chapter_data );

			if ( is_wp_error( $new_chapter_id ) ) {
				continue; // Skip if chapter creation fails.
			}

			// Insert the chapter-course relationship into the custom table.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				array(
					'course_id'    => $new_course_id,
					'chapter_id'   => $new_chapter_id,
					'order_number' => $i,
				),
				array( '%d', '%d', '%d' )
			);

			// Get section items (lessons and quizzes) from array.
			$section_items = $section['items'] ?? array();

			// Migrate section items (lessons and quizzes).
			if ( ! empty( $section_items ) && is_array( $section_items ) ) {
				$this->migrate_section_items( $new_chapter_id, $section_items );
			}

			++$i;
		}
	}

	/**
	 * Migrates section items (lessons and quizzes) from LearnPress to OhMyLMS.
	 *
	 * @param int   $new_chapter_id The ID of the newly created chapter.
	 * @param array $items The section items to migrate (array format from get_curriculum_raw).
	 */
	private function migrate_section_items( $new_chapter_id, $items ) {
		$index = 0;

		foreach ( $items as $item ) {
			// Handle both array and object formats.
			if ( is_array( $item ) ) {
				// Array format from get_curriculum_raw().
				$item_id   = isset( $item['id'] ) ? $item['id'] : 0;
				$item_type = isset( $item['type'] ) ? $item['type'] : '';
			} elseif ( is_object( $item ) ) {
				// Object format (LP_Course_Item).
				$item_id   = isset( $item->id ) ? $item->id : 0;
				$item_type = isset( $item->type ) ? $item->type : '';
			} else {
				continue;
			}

			if ( ! $item_id || ! $item_type ) {
				continue;
			}

			// Migrate based on item type.
			if ( 'lp_lesson' === $item_type ) {
				$this->migrate_lesson( $new_chapter_id, $item_id, $index );
			} elseif ( 'lp_quiz' === $item_type ) {
				$this->migrate_quiz( $new_chapter_id, $item_id, $index );
			}

			++$index;
		}
	}

	/**
	 * Migrates a lesson from LearnPress to OhMyLMS.
	 *
	 * @param int $new_chapter_id The ID of the chapter in OhMyLMS.
	 * @param int $old_lesson_id The ID of the lesson in LearnPress.
	 * @param int $index The order index of the lesson.
	 */
	private function migrate_lesson( $new_chapter_id, $old_lesson_id, $index = 0 ) {
		global $wpdb;

		$lesson = get_post( $old_lesson_id );
		if ( ! $lesson || 'lp_lesson' !== $lesson->post_type ) {
			return;
		}

		$new_lesson_data = array(
			'post_title'   => sanitize_text_field( $lesson->post_title ),
			'post_content' => wp_kses_post( $lesson->post_content ),
			'post_excerpt' => sanitize_text_field( $lesson->post_excerpt ),
			'post_status'  => 'publish',
			'post_author'  => $lesson->post_author,
			'post_type'    => 'ohmylms-lesson',
		);

		// Insert the new lesson.
		$new_lesson_id = wp_insert_post( $new_lesson_data );

		if ( is_wp_error( $new_lesson_id ) ) {
			return;
		}

		// Migrate lesson metadata first to set the type.
		$this->migrate_lesson_metadata( $new_lesson_id, get_post_meta( $old_lesson_id ) );

		// Get the lesson type (should be 'text' by default).
		$type = get_post_meta( $new_lesson_id, '_type', true );
		if ( ! $type ) {
			$type = 'text';
		}

		// Insert the lesson-content relationship into the custom table.
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $new_chapter_id,
				'content_id'   => $new_lesson_id,
				'content_type' => $type, // Use the actual lesson type (text, video, audio).
				'order_number' => $index,
			),
			array( '%d', '%d', '%s', '%d' )
		);
	}

	/**
	 * Migrates lesson metadata from LearnPress to OhMyLMS.
	 *
	 * @param int   $new_lesson_id The ID of the new lesson in OhMyLMS.
	 * @param array $meta_data The metadata from the old lesson.
	 */
	private function migrate_lesson_metadata( $new_lesson_id, $meta_data ) {
		// Set lesson type to 'text' by default (LearnPress lessons are typically text-based).
		update_post_meta( $new_lesson_id, '_type', 'text' );

		// Migrate the lesson thumbnail (featured image).
		if ( isset( $meta_data['_thumbnail_id'][0] ) ) {
			update_post_meta( $new_lesson_id, '_thumbnail_id', $meta_data['_thumbnail_id'][0] );
			update_post_meta( $new_lesson_id, '_cover_image_id', $meta_data['_thumbnail_id'][0] );
		}

		// Migrate lesson duration.
		if ( isset( $meta_data['_lp_duration'][0] ) && ! empty( $meta_data['_lp_duration'][0] ) ) {
			$lp_duration  = $meta_data['_lp_duration'][0];
			$new_duration = $this->convert_duration( $lp_duration );
			if ( ! empty( $new_duration ) ) {
				update_post_meta( $new_lesson_id, '_duration', $new_duration );
			}
		}

		// Migrate preview setting.
		if ( isset( $meta_data['_lp_preview'][0] ) && 'yes' === $meta_data['_lp_preview'][0] ) {
			update_post_meta( $new_lesson_id, '_is_preview', true );
		}
	}

	/**
	 * Migrates a quiz from LearnPress to OhMyLMS.
	 *
	 * @param int $new_chapter_id The ID of the chapter in OhMyLMS.
	 * @param int $old_quiz_id The ID of the quiz in LearnPress.
	 * @param int $index The order index of the quiz.
	 */
	private function migrate_quiz( $new_chapter_id, $old_quiz_id, $index = 0 ) {
		global $wpdb;

		$quiz = get_post( $old_quiz_id );
		if ( ! $quiz || 'lp_quiz' !== $quiz->post_type ) {
			return;
		}

		$new_quiz_data = array(
			'post_title'   => sanitize_text_field( $quiz->post_title ),
			'post_content' => wp_kses_post( $quiz->post_content ),
			'post_excerpt' => sanitize_text_field( $quiz->post_excerpt ),
			'post_status'  => 'publish',
			'post_author'  => $quiz->post_author,
			'post_type'    => 'ohmylms-quiz',
		);

		// Insert the new quiz.
		$new_quiz_id = wp_insert_post( $new_quiz_data );

		if ( is_wp_error( $new_quiz_id ) ) {
			return;
		}

		// Insert the quiz-content relationship into the custom table.
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $new_chapter_id,
				'content_id'   => $new_quiz_id,
				'content_type' => 'quiz',
				'order_number' => $index,
			),
			array( '%d', '%d', '%s', '%d' )
		);

		// Migrate quiz metadata and questions.
		$this->migrate_quiz_metadata( $new_quiz_id, get_post_meta( $old_quiz_id ) );
		$this->migrate_quiz_questions( $new_quiz_id, $old_quiz_id );
	}

	/**
	 * Migrates quiz metadata from LearnPress to OhMyLMS.
	 *
	 * @param int   $new_quiz_id The ID of the new quiz in OhMyLMS.
	 * @param array $meta_data The metadata from the old quiz.
	 */
	private function migrate_quiz_metadata( $new_quiz_id, $meta_data ) {
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

		// Migrate quiz duration.
		if ( isset( $meta_data['_lp_duration'][0] ) && ! empty( $meta_data['_lp_duration'][0] ) ) {
			$duration_minutes = intval( $meta_data['_lp_duration'][0] );
			if ( $duration_minutes > 0 ) {
				$new_settings['time_limit'] = array(
					'value' => strval( $duration_minutes ),
					'type'  => 'minutes',
				);
			}
		}

		// Migrate passing grade.
		if ( isset( $meta_data['_lp_passing_grade'][0] ) && ! empty( $meta_data['_lp_passing_grade'][0] ) ) {
			$new_settings['passing_grade'] = array(
				'enabled' => true,
				'value'   => strval( $meta_data['_lp_passing_grade'][0] ),
			);
		}

		// Migrate retake count.
		if ( isset( $meta_data['_lp_retake_count'][0] ) ) {
			$new_settings['allow_attempts'] = $meta_data['_lp_retake_count'][0];
		}

		// Migrate negative marking.
		if ( isset( $meta_data['_lp_negative_marking'][0] ) && 'yes' === $meta_data['_lp_negative_marking'][0] ) {
			$new_settings['negative_marking'] = true;
		}

		// Migrate instant check.
		if ( isset( $meta_data['_lp_instant_check'][0] ) && 'yes' === $meta_data['_lp_instant_check'][0] ) {
			$new_settings['hide_answers'] = false;
		}

		// Migrate show correct review.
		if ( isset( $meta_data['_lp_show_correct_review'][0] ) && 'yes' === $meta_data['_lp_show_correct_review'][0] ) {
			$new_settings['hide_answers'] = false;
		}

		update_post_meta( $new_quiz_id, '_settings', $new_settings );
	}

	/**
	 * Migrates quiz questions from LearnPress to OhMyLMS.
	 *
	 * @param int $new_quiz_id The ID of the new quiz in OhMyLMS.
	 * @param int $old_quiz_id The ID of the old quiz in LearnPress.
	 */
	private function migrate_quiz_questions( $new_quiz_id, $old_quiz_id ) {
		global $wpdb;

		// Get LearnPress quiz object to get questions.
		$lp_quiz = learn_press_get_quiz( $old_quiz_id );
		if ( ! $lp_quiz ) {
			return;
		}

		// Get quiz questions.
		$questions = $lp_quiz->get_questions();

		if ( empty( $questions ) ) {
			return;
		}

		$order = 0;

		foreach ( $questions as $old_question_id ) {
			$question_post = get_post( $old_question_id );

			if ( ! $question_post || 'lp_question' !== $question_post->post_type ) {
				continue;
			}

			// Get question type.
			$question_type = get_post_meta( $old_question_id, '_lp_type', true );

			$mapped_type = $this->map_question_type( $question_type );

			// Create new question.
			$new_question_data = array(
				'post_title'   => sanitize_text_field( $question_post->post_title ),
				'post_content' => wp_kses_post( $question_post->post_content ),
				'post_status'  => 'publish',
				'post_author'  => $question_post->post_author,
				'post_type'    => 'ohmylms-question',
			);

			$new_question_id = wp_insert_post( $new_question_data );

			if ( is_wp_error( $new_question_id ) ) {
				continue;
			}

			// Migrate question settings (type, score, etc.).
			$mark = get_post_meta( $old_question_id, '_lp_mark', true );
			$this->migrate_question_metadata( $new_question_id, $mapped_type, $mark );

			// Insert question-quiz relationship.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
				array(
					'quiz_id'      => $new_quiz_id,
					'question_id'  => $new_question_id,
					'order_number' => $order,
				),
				array( '%d', '%d', '%d' )
			);

			// Migrate question answers.
			$this->migrate_question_answers( $new_question_id, $old_question_id, $mapped_type );

			++$order;
		}
	}

	/**
	 * Map LearnPress question types to OhMyLMS question types.
	 *
	 * @param string $lp_type LearnPress question type.
	 * @return string OhMyLMS question type.
	 */
	private function map_question_type( $lp_type ) {
		$type_map = array(
			'true_or_false'  => 'true-false',
			'single_choice'  => 'single-choice',
			'multi_choice'   => 'multiple-choice',
			'fill_in_blanks' => 'fill-in-the-blank',
		);
		return isset( $type_map[ $lp_type ] ) ? $type_map[ $lp_type ] : 'single-choice';
	}

	/**
	 * Migrates question metadata from LearnPress to OhMyLMS.
	 *
	 * This function transfers question-specific metadata, including type,
	 * required status, randomization, and score, from the old system (LearnPress)
	 * to the new system (OhMyLMS).
	 *
	 * @param int    $new_question_id The ID of the newly created question in OhMyLMS.
	 * @param string $question_type The question type.
	 * @param mixed  $mark The question mark/points.
	 * .
	 * @since 1.0.0
	 * @return void
	 */
	private function migrate_question_metadata( $new_question_id, $question_type, $mark = null ) {
		$new_settings = array(
			'type'      => $question_type,
			'required'  => false,
			'randomize' => false,
			'score'     => array(
				'enabled' => true,
				'value'   => ! empty( $mark ) ? intval( $mark ) : 1,
			),
		);

		update_post_meta( $new_question_id, '_question_settings', $new_settings );

		// Also set the old meta keys for compatibility.
		update_post_meta( $new_question_id, '_question_type', $question_type );
		if ( ! empty( $mark ) ) {
			update_post_meta( $new_question_id, '_mark', $mark );
		}
	}

	/**
	 * Migrates question answers from LearnPress to OhMyLMS.
	 *
	 * @param int    $new_question_id The ID of the new question in OhMyLMS.
	 * @param int    $old_question_id The ID of the old question in LearnPress.
	 * @param string $question_type The question type.
	 */
	private function migrate_question_answers( $new_question_id, $old_question_id, $question_type ) {
		global $wpdb;

		// Get question answers from LearnPress (version 4.0+ structure).
		$answers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT question_answer_id, title, value, is_true, `order` 
				FROM {$wpdb->prefix}learnpress_question_answers 
				WHERE question_id = %d 
				ORDER BY `order` ASC",
				$old_question_id
			)
		);

		if ( empty( $answers ) ) {
			return;
		}

		foreach ( $answers as $answer ) {
			// Extract answer text from title column (LearnPress 4.0+).
			$answer_text = ! empty( $answer->title ) ? $answer->title : '';

			// Check if this answer is correct (LearnPress 4.0+ uses is_true column).
			$is_correct = ( isset( $answer->is_true ) && 'yes' === $answer->is_true ) ? 1 : 0;

			// Insert answer.
			$wpdb->insert(
				"{$wpdb->prefix}ohmylms_question_answers",
				array(
					'question_id'  => $new_question_id,
					'answer'       => wp_kses_post( $answer_text ),
					'order_number' => $answer->order ?? 0,
					'is_correct'   => $is_correct,
				),
				array( '%d', '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * Migrates students from LearnPress to OhMyLMS.
	 *
	 * This method retrieves all students enrolled in the old course and enrolls them
	 * in the new course in OhMyLMS.
	 *
	 * @param int $new_course_id The ID of the newly created course in OhMyLMS.
	 */
	private function migrate_students( $new_course_id ) {
		global $wpdb;

		// Get enrolled students from LearnPress.
		$students = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT user_id 
				FROM {$wpdb->prefix}learnpress_user_items 
				WHERE item_id = %d 
				AND item_type = 'lp_course' 
				AND status IN ('enrolled', 'finished')",
				$this->lp_course_id
			)
		);

		if ( empty( $students ) ) {
			return;
		}

		foreach ( $students as $student ) {
			$user_id = $student->user_id;

			// Check if user exists.
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				continue;
			}

			// Enroll student in the new course.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_course_enrollment',
				array(
					'user_id'     => $user_id,
					'course_id'   => $new_course_id,
					'status'      => 'enrolled',
					'enrolled_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Get course data.
	 *
	 * @return array|\WP_Error The course data or error object.
	 * @since 1.0.0
	 */
	private function get_course_data() {
		if ( ! $this->lp_course_id ) {
			return new \WP_Error( 'no_course_id', 'No course ID provided.' );
		}

		// Get course post data.
		$course = get_post( $this->lp_course_id );

		if ( ! $course || 'lp_course' !== $course->post_type ) {
			return new \WP_Error( 'invalid_course', 'Invalid course ID or post type.' );
		}

		// Get course meta data.
		$course_meta = get_post_meta( $this->lp_course_id );

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
}
