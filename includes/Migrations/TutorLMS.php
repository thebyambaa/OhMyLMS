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
 * Migration class for TutorLMS.
 * This class is responsible for creating migrations for TutorLMS.
 *
 * @author OhMyLMS
 * @since 1.0.0
 */
class TutorLMS {

	/**
	 * Tutor course id.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public $tutor_course_id = null;


	/**
	 * Tutor course data.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $tutor_course = null;

	/**
	 * Initializes the migration process for a specific tutor course.
	 *
	 * This method checks if initialization should proceed based on a condition
	 * (e.g., validation or configuration check), and if so, sets the course ID
	 * for the migration process.
	 *
	 * @param int|null $tutor_course_id The ID of the tutor course to migrate (optional).
	 * @return bool Returns true if the initialization was successful, false if not.
	 */
	public function init( $tutor_course_id = null ) {
		// Check if initialization should occur.
		if ( ! $this->should_init() ) {
			return false;
		}

		// Proceed with the migration by setting the course ID.
		$this->tutor_course_id = $tutor_course_id;

		return true;
	}


	/**
	 * Checks if the migration should proceed based on the existence of the Tutor LMS plugin.
	 *
	 * This method ensures that the migration or initialization process only runs if the Tutor LMS
	 * plugin is active, indicated by the defined 'TUTOR_VERSION' constant.
	 *
	 * @return bool Returns true if the Tutor LMS plugin is active, false otherwise.
	 */
	private function should_init() {
		// Check if the TUTOR_VERSION constant is defined, indicating that the plugin is active.
		return defined( 'TUTOR_VERSION' );
	}

	/**
	 * Migrates course data from Tutor LMS to OhMyLMS.
	 *
	 * This method takes the course data from the source system, sanitizes it,
	 * and creates a new course in OhMyLMS. It also migrates associated metadata,
	 * terms, and chapters.
	 *
	 * @return WP_Error|int Returns the new course ID on success or WP_Error on failure.
	 */
	public function migrate_course() {
		// Fetch the course data.
		$this->tutor_course = $this->get_course_data();

		// Check if fetching course data resulted in an error.
		if ( is_wp_error( $this->tutor_course ) ) {
			return $this->tutor_course; // Return error if data fetch failed.
		}

		// Prepare the course data for insertion.
		$post_data = array(
			'post_title'    => sanitize_text_field( $this->tutor_course['post_title'] ),
			'post_content'  => wp_kses_post( $this->tutor_course['post_content'] ),
			'post_excerpt'  => isset( $this->tutor_course['post_excerpt'] ) ? sanitize_text_field( $this->tutor_course['post_excerpt'] ) : '',
			'post_status'   => 'draft',
			'post_author'   => isset( $this->tutor_course['post_author'] ) ? intval( $this->tutor_course['post_author'] ) : get_current_user_id(),
			'post_type'     => 'ohmylms-course', // Custom post type for OhMyLMS course.
			'post_date'     => isset( $this->tutor_course['post_date'], $this->tutor_course['post_status'] ) && 'future' === $this->tutor_course['post_status'] ? gmdate( 'Y-m-d H:i:s', strtotime( $this->tutor_course['post_date'] ) ) : current_datetime()->format( 'Y-m-d H:i:s' ),
			'post_password' => isset( $this->tutor_course['post_password'] ) ? $this->tutor_course['post_password'] : '',
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
		if ( ! empty( $this->tutor_course['meta'] ) && is_array( $this->tutor_course['meta'] ) ) {
			$this->migrate_course_metadata( $new_course_id, $this->tutor_course['meta'] );
		}

		// Migrate terms and taxonomies (categories, tags, etc.).
		$this->migrate_terms_and_taxonomies( $new_course_id );

		// Migrate course chapters (lessons or sections).
		$this->migrate_chapters( $new_course_id );

		// Migrate students.
		$this->migrate_students( $new_course_id );
	}


	/**
	 * Get course data.
	 *
	 * @return array|\WP_Error The course data or error object.
	 * @since 1.0.0
	 */
	private function get_course_data() {
		if ( ! $this->tutor_course_id ) {
			return new \WP_Error( 'no_course_id', 'No course ID provided.' );
		}

		// Get course post data.
		$course = get_post( $this->tutor_course_id );

		if ( ! $course || 'courses' !== $course->post_type ) {
			return new \WP_Error( 'invalid_course', 'Invalid course ID or post type.' );
		}

		// Get course meta data.
		$course_meta = get_post_meta( $this->tutor_course_id );

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
	 * Migrates chapters (topics) from Tutor LMS to OhMyLMS for a given course.
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

		// Fetch all chapters (topics) associated with the source course.
		$chapters = get_posts(
			array(
				'post_parent' => $this->tutor_course_id, // Parent course ID.
				'post_type'   => 'topics',               // Post type for chapters (topics).
				'numberposts' => -1,                      // Retrieve all chapters.
			)
		);

		// If no chapters are found, exit the function.
		if ( empty( $chapters ) ) {
			return;
		}

		// Iterate through each chapter (topic) and migrate it.
		foreach ( $chapters as $index => $chapter ) {
			// Prepare the chapter data for insertion.
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
					'order_number' => $index,          // Maintain chapter order.
				),
				array( '%d', '%d', '%d' )
			);

			// Migrate any lessons associated with this chapter.
			$this->migrate_lessons( $new_chapter_id, $chapter->ID );
			$this->migrate_quizzes( $new_chapter_id, $chapter->ID );
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
	 * @param int $new_chapter_id The ID of the newly created chapter in OhMyLMS.
	 * @param int $old_chapter_id The ID of the old chapter from the Tutor LMS system.
	 */
	private function migrate_lessons( $new_chapter_id, $old_chapter_id ) {
		global $wpdb;

		// Fetch all lessons (posts of type 'lesson') associated with the old chapter.
		$lessons = get_posts(
			array(
				'post_type'   => 'lesson',            // Lesson post type.
				'post_parent' => $old_chapter_id,     // Parent chapter ID.
				'numberposts' => -1,                  // Retrieve all lessons.
			)
		);

		// If no lessons are found, exit the function.
		if ( empty( $lessons ) ) {
			return;
		}

		// Iterate through each lesson and migrate it.
		foreach ( $lessons as $index => $lesson ) {
			// Get metadata associated with the lesson.
			$meta_data = get_post_meta( $lesson->ID );

			// Prepare lesson data for insertion into OhMyLMS.
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
				continue; // Skip lesson if insertion fails.
			}

			// Migrate lesson metadata.
			$this->migrate_lesson_metadata( $new_lesson_id, $meta_data );

			$type = get_post_meta( $new_lesson_id, '_type', true );

			if ( ! $type ) {
				update_post_meta( $new_lesson_id, '_type', 'text' );
			}

			// Insert the lesson-content relationship into the custom table.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_content_relationship',
				array(
					'chapter_id'   => $new_chapter_id,  // ID of the new chapter.
					'content_id'   => $new_lesson_id,   // ID of the new lesson.
					'content_type' => $type ? $type : 'text', // If no type, default to 'text'.
					'order_number' => $index,           // Maintain lesson order.
				),
				array( '%d', '%d', '%s', '%d' ) // SQL formatting placeholders.
			);
		}
	}


	/**
	 * Migrates quizzes from the old chapter to the new chapter in OhMyLMS.
	 *
	 * This method retrieves all quizzes associated with the old chapter and inserts them
	 * as new quizzes into the OhMyLMS system. The quiz metadata is migrated as well,
	 * and the relationship between the new chapter and quizzes is established. If the quiz
	 * has a video or other content types, they are properly handled.
	 *
	 * @param int $new_chapter_id The ID of the newly created chapter in OhMyLMS.
	 * @param int $old_chapter_id The ID of the old chapter from the Tutor LMS system.
	 * .
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function migrate_quizzes( $new_chapter_id, $old_chapter_id ) {
		global $wpdb;

		// Fetch all Quizzes (posts of type 'tutor_quiz') associated with the old chapter.
		$quizzes = get_posts(
			array(
				'post_type'   => 'tutor_quiz',            // Quiz post type.
				'post_parent' => $old_chapter_id,     // Parent chapter ID.
				'numberposts' => -1,                  // Retrieve all Quizzes.
			)
		);
		// If no quizzes are found, exit the function.
		if ( empty( $quizzes ) ) {
			return;
		}

		// Iterate through each quiz and migrate it.
		foreach ( $quizzes as $index => $quiz ) {
			// Get metadata associated with the quiz
			// $meta_data = get_post_meta( $quiz->ID );.

			// Prepare quiz data for insertion into OhMyLMS.
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
				continue; // Skip quiz if insertion fails.
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
			$meta_data = get_post_meta( $quiz->ID );
			$this->migrate_quiz_metadata( $new_quiz_id, $meta_data );
			$this->migrate_questions( $new_quiz_id, $quiz->ID );
		}
	}


	/**
	 * Migrates course metadata from Tutor LMS to OhMyLMS.
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
			if ( '_tutor_course_price_type' === $key ) {
				update_post_meta( $new_course_id, '_price_type', $value[0] );
			}

			// Migrate the course thumbnail (featured image).
			if ( '_thumbnail_id' === $key ) {
				update_post_meta( $new_course_id, '_thumbnail_id', $value[0] );
			}

			// Migrate course duration.
			if ( '_course_duration' === $key ) {
				$duration     = maybe_unserialize( $value[0] ); // Deserialize if necessary.
				$new_duration = array();
				if ( ! empty( $duration['hours'] ) && $duration['hours'] ) {
					$new_duration['hour'] = $duration['hours']; // Migrate hours.
				}
				if ( ! empty( $duration['minutes'] ) && $duration['minutes'] ) {
					$new_duration['min'] = $duration['minutes']; // Migrate minutes.
				}

				update_post_meta( $new_course_id, '_duration', $new_duration );
			}

			// Migrate regular course price.
			if ( 'tutor_course_price' === $key ) {
				update_post_meta( $new_course_id, '_regular_price', $value[0] );
				$sale_price = get_post_meta( $new_course_id, '_price', true );
				if ( ! $sale_price ) {
					update_post_meta( $new_course_id, '_price', $value[0] );
				}
			}

			// Migrate sale price.
			if ( 'tutor_course_sale_price' === $key ) {
				if ( (int) $value[0] > 0 ) {
					update_post_meta( $new_course_id, '_sale_price', $value[0] );
					update_post_meta( $new_course_id, '_price', $value[0] );

				}
			}

			// Migrate course level.
			if ( '_tutor_course_level' === $key ) {
				$level = $value[0];
				// Adjust levels to match the OhMyLMS level system.
				if ( 'all_levels' === $level ) {
					$level = 'all'; // Convert 'all_levels' to 'all'.
				} elseif ( 'intermediate' === $level ) {
					$level = 'experience'; // Convert 'intermediate' to 'experience'.
				}
				update_post_meta( $new_course_id, '_level', $level );
			}

			// Migrate course settings.
			if ( '_tutor_course_settings' === $key ) {
				$settings = maybe_unserialize( $value[0] ); // Deserialize if necessary.
				if ( isset( $settings['maximum_students'] ) && $settings['maximum_students'] > 0 ) {
					update_post_meta( $new_course_id, '_has_capacity', true ); // Migrate capacity flag.
					update_post_meta( $new_course_id, '_capacity', $settings['maximum_students'] ); // Migrate maximum students.
				}
			}

			if ( '_video' === $key ) {
				$settings = maybe_unserialize( $value[0] );
				if ( isset( $settings['source_video_id'] ) ) {
					update_post_meta( $new_course_id, '_video_id', $settings['source_video_id'] );
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
		// Get all terms associated with the TutorLMS course.
		$terms = wp_get_post_terms( $this->tutor_course_id, array( 'course-category', 'course-tag' ) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		// Prepare taxonomy assignment.
		$course_categories = array();
		$course_tags       = array();

		foreach ( $terms as $term ) {
			if ( 'course-category' === $term->taxonomy ) {
				$course_categories[] = $term;
			} elseif ( 'course-tag' === $term->taxonomy ) {
				$course_tags[] = $term;
			}
		}

		// Tutor categories become curriculum items (keeping their parent chain) and its tags become
		// Course Bundles. Existing items and bundles with the same name are reused.
		$paths = array();
		foreach ( $course_categories as $category ) {
			$paths[] = \OhMyLMS\Curriculum\Placement::term_path( $category );
		}
		\OhMyLMS\Curriculum\Placement::import_categories( $new_course_id, $paths );
		\OhMyLMS\Curriculum\Placement::import_tags( $new_course_id, wp_list_pluck( $course_tags, 'name' ) );
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

			// Migrate lesson attachments (resources).
			if ( '_tutor_attachments' === $key ) {
				$attachment_data = maybe_unserialize( $value[0] ); // Unserialize attachment data.

				if ( is_array( $attachment_data ) && ! empty( $attachment_data ) ) {
					$formatted_resources = array( 'file' => array() );

					// Process each attachment and format the resource data.
					foreach ( $attachment_data as $attachment_id ) {
						$file_url  = wp_get_attachment_url( $attachment_id );
						$file_path = get_attached_file( $attachment_id );
						$file_name = basename( $file_path );
						$file_size = size_format( filesize( $file_path ), 2 );

						// Store formatted attachment data.
						$formatted_resources['file'][] = array(
							'id'   => intval( $attachment_id ),
							'url'  => esc_url( $file_url ),
							'name' => sanitize_text_field( $file_name ),
							'size' => sanitize_text_field( $file_size ),
						);
					}

					// Store the serialized resource data.
					update_post_meta( $new_lesson_id, '_download_resource', $formatted_resources );
				}
			}

			// Migrate video URL for the lesson.
			if ( '_video' === $key ) {
				$video_data = maybe_unserialize( $value[0] );
				if ( is_array( $video_data ) ) {
					if ( ! empty( $video_data['source_video_id'] ) ) {
						update_post_meta( $new_lesson_id, '_video_id', $video_data['source_video_id'] );
					}
					if ( ! empty( $video_data['source_external_url'] ) ) {
						update_post_meta( $new_lesson_id, '_external_url', $video_data['source_external_url'] );
					}
					if ( ! empty( $video_data['source_youtube'] ) ) {
						update_post_meta( $new_lesson_id, '_external_url', $video_data['source_youtube'] );
					}

					if ( ! empty( $video_data['source_vimeo'] ) ) {
						update_post_meta( $new_lesson_id, '_external_url', $video_data['source_vimeo'] );
					}
				}
				update_post_meta( $new_lesson_id, '_type', 'video' );
			}
		}
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
			if ( 'tutor_quiz_option' === $key ) {
				$settings = maybe_unserialize( $value[0] );
				if ( is_array( $settings ) && ! empty( $settings ) ) {
					foreach ( $settings  as $key => $meta_value ) {
						if ( 'attempts_allowed' === $key ) {
							$new_settings['allow_attempts'] = $meta_value;
						} elseif ( 'questions_order' === $key && 'rand' === $meta_value ) {
							$new_settings['randomize_questions'] = true;
						} elseif ( 'hide_question_number_overview' === $key ) {
							$new_settings['hide_question_number'] = $meta_value;
						} elseif ( 'short_answer_characters_limit' === $key ) {
							$new_settings['short_text_limit'] = $meta_value;
						} elseif ( 'open_ended_answer_characters_limit' === $key ) {
							$new_settings['long_text_limit'] = $meta_value;
						} elseif ( 'question_layout_view' === $key ) {
							$layout = 'one_question_per_page';
							if ( 'single_question' === $meta_value ) {
								$layout = 'one_question_per_page';
							} elseif ( 'question_below_each_other' === $meta_value ) {
								$layout = 'all_questions_in_one_page';
							} elseif ( 'question_pagination' === $meta_value ) {
								$layout = 'number_of_questions_per_page';
							}
							$new_settings['layout'] = $layout;
						} elseif ( 'passing_grade' === $key && (int) $meta_data > 0 ) {
							$new_settings['passing_grade'] = array(
								'enabled' => true,
								'value'   => (int) $meta_data,
							);
						} elseif ( 'time_limit' === $key ) {
							$new_settings['time_limit'] = array(
								'value' => $meta_value['time_value'],
								'type'  => $meta_value['time_type'],
							);
						}
					}
				}
			}
		}
		update_post_meta( $new_quiz_id, '_quiz_settings', $new_settings );
	}


	/**
	 * Migrates quiz questions from Tutor LMS to OhMyLMS.
	 *
	 * This function transfers quiz questions from the old system (Tutor LMS)
	 * to the new system (OhMyLMS).
	 *
	 * @param int $new_quiz_id The ID of the newly created quiz in OhMyLMS.
	 * @param int $old_quiz_id The ID of the old quiz in Tutor LMS.
	 * .
	 * @since 1.0.0
	 * @return void
	 */
	private function migrate_questions( $new_quiz_id, $old_quiz_id ) {
		global $wpdb;

		// Fetch all questions associated with the old quiz.
		$questions = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE quiz_id = %d', $wpdb->prefix . 'tutor_quiz_questions', $old_quiz_id )
		);

		if ( empty( $questions ) ) {
			return;
		}

		foreach ( $questions as $question ) {
			// Insert question as a new post of type 'ohmylms-question'.
			$question_post = array(
				'post_title'   => wp_strip_all_tags( $question->question_title ),
				'post_content' => $question->question_description,
				'post_status'  => 'draft',
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
					'order_number' => $question->question_order ?? 0,
				),
				array( '%d', '%d', '%d' )
			);
			$meta_data = maybe_unserialize( $question->question_settings );
			$this->migrate_question_metadata( $new_question_id, $meta_data );
			// Migrate answers for the new question.
			$this->migrate_answers( $new_question_id, $question->question_id );

		}
	}


	/**
	 * Migrates question metadata from Tutor LMS to OhMyLMS.
	 *
	 * This function transfers question-specific metadata, including type,
	 * required status, randomization, and score, from the old system (Tutor LMS)
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
				'enabled' => false,
				'value'   => 1,
			),
		);
		if ( isset( $meta_data['question_type'] ) ) {

			if ( 'true_false' === $meta_data['question_type'] ) {
				$new_settings['type'] = 'true-false';
			}
			if ( 'multiple_choice' === $meta_data['question_type'] ) {
				$new_settings['type'] = 'multiple-choice';
			}
			if ( 'open_ended' === $meta_data['question_type'] ) {
				$new_settings['type'] = 'long-text';
			}
			if ( 'fill_in_the_blank' === $meta_data['question_type'] ) {
				$new_settings['type'] = 'fill-in-the-blank';
			}
			if ( 'short_answer' === $meta_data['question_type'] ) {
				$new_settings['type'] = 'short-text';
			}
		}

		if ( isset( $meta_data['randomize_options'] ) ) {
			$new_settings['randomize'] = $meta_data['randomize_options'];
		}
		if ( isset( $meta_data['answer_required'] ) ) {
			$new_settings['required'] = $meta_data['answer_required'];
		}
		if ( isset( $meta_data['question_mark'] ) ) {
			$new_settings['score'] = array(
				'enabled' => true,
				'value'   => $meta_data['question_mark'],
			);
		}
		update_post_meta( $new_question_id, '_question_settings', $new_settings );
	}


	/**
	 * Migrates answers from Tutor LMS to OhMyLMS.
	 *
	 * This function transfers answers associated with a question from the old system (Tutor LMS)
	 * to the new system (OhMyLMS).
	 *
	 * @param int $new_question_id The ID of the newly created question in OhMyLMS.
	 * @param int $old_question_id The ID of the old question in Tutor LMS.
	 * .
	 * @since 1.0.0
	 * @return void
	 */
	private function migrate_answers( $new_question_id, $old_question_id ) {
		global $wpdb;

		// Fetch answers for the given question.
		$answers = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE belongs_question_id = %d', $wpdb->prefix . 'tutor_quiz_question_answers', $old_question_id )
		);

		if ( empty( $answers ) ) {
			return;
		}

		foreach ( $answers as $answer ) {
			$wpdb->insert(
				"{$wpdb->prefix}ohmylms_question_answers",
				array(
					'question_id'  => $new_question_id,
					'answer'       => $answer->answer_title,
					'order_number' => $answer->answer_order ?? 0,
					'is_correct'   => $answer->is_correct ?? 0,
				),
				array( '%d', '%s', '%d', '%d' )
			);
		}
	}


	/**
	 * Migrates students from Tutor LMS to OhMyLMS.
	 *
	 * This function transfers students associated with a course from the old system (Tutor LMS)
	 * to the new system (OhMyLMS).
	 *
	 * @param int    $new_course_id The ID of the newly created course in OhMyLMS.
	 * .
	 * @param string $field_name Field name.
	 * @since 1.0.0
	 * @return void
	 */
	private function migrate_students( $new_course_id, $field_name = 'ID' ) {
		global $wpdb;
		// Sanitize the column name.
		$sanitized_field_name = sanitize_key( $field_name );
		// Fetch students for the given course.
		$student_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT student.{$sanitized_field_name}, student.display_name as display_name, student.user_login as username, student.user_email
				FROM   	{$wpdb->posts} enrol
						INNER JOIN {$wpdb->users} student
								ON enrol.post_author = student.id
				WHERE  	enrol.post_type = %s
						AND enrol.post_parent = %d
						AND enrol.post_status = %s;
				",
				'tutor_enrolled',
				$this->tutor_course_id,
				'completed'
			)
		);

		if ( empty( $student_data ) ) {
			return;
		}
		foreach ( $student_data as $student ) {
			$user_id = isset( $student->{$field_name} ) ? $student->{$field_name} : 0;
			$user    = get_user_by( 'ID', $user_id );
			if ( $user ) {
				$this->enroll_student( $user_id, $new_course_id );
			}
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
		}
	}
}
