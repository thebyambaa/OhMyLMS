<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class CourseExporter.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exporters;

defined( 'ABSPATH' ) || exit;

/**
 * Class CourseExporter
 *
 * Exports one or multiple courses along with their chapters, contents, and metadata as JSON.
 */
class CourseExporter {

	/**
	 * Course ids.
	 *
	 * @var mixed
	 */
	public $course_ids;

	/**
	 * CourseExporter constructor.
	 *
	 * @param array $course_ids An array of course IDs to export.
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function __construct( $course_ids ) {
		if ( empty( $course_ids ) || ! is_array( $course_ids ) ) {
			throw new \Exception( __( 'Invalid course IDs. Provide an array of course IDs.', 'ohmylms' ) );
		}

		$this->course_ids = $course_ids;
	}

	/**
	 * Export multiple courses with all contents, chapters, and metadata as a single JSON file.
	 *
	 * @return void
	 */
	public function export_courses_as_json() {
		$export_data = array();
		foreach ( $this->course_ids as $course_id ) {
			if ( ! is_numeric( $course_id ) ) {
				continue; // Skip invalid course IDs.
			}

			// Get the course data.
			$course_data = $this->get_course_data( $course_id );
			if ( $course_data ) {
				$export_data[] = $course_data;
			}
		}

		// Convert data to JSON.
		$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! $json_data ) {
			wp_die( esc_html__( 'Error encoding course data to JSON.', 'ohmylms' ) );
		}

		// Set headers for JSON file download.
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="courses-export.json"' );
		header( 'Content-Length: ' . strlen( $json_data ) );
		// Output the JSON data.
		echo $json_data;
		exit;
	}


	/**
	 * Get data for a single course.
	 *
	 * @param int $course_id The course ID.
	 * @return array|null Course data or null if invalid.
	 */
	private function get_course_data( $course_id ) {
		// Validate the course ID.
		if ( empty( $course_id ) || ! is_numeric( $course_id ) ) {
			return null;
		}

		// Get the course post object.
		$course_post = get_post( $course_id );

		if ( ! $course_post || OHMYLMS_COURSE_CPT !== $course_post->post_type ) {
			return null;
		}

		// Retrieve all metadata for the course. The learning program is exported separately (below) because
		// it refers to unit and content IDs that only mean something next to the exported contents.
		$course_meta = get_post_meta( $course_id );
		unset( $course_meta[ \OhMyLMS\Learning\CourseProgram::DRAFT ], $course_meta[ \OhMyLMS\Learning\CourseProgram::CURRENT ] );

		if ( ! empty( $course_meta['_video_id'][0] ) ) {
			$course_meta['_video_url'][] = wp_get_attachment_url( $course_meta['_video_id'][0] );
		}

		// Fetch course contents (chapters and their contents).
		$contents = $this->get_course_contents( $course_id );

		$thumbnail_id  = get_post_thumbnail_id( $course_id );
		$thumbnail_url = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : null;
		$course_terms  = array();
		// Get terms associated with the course.
		$taxonomies = get_object_taxonomies( OHMYLMS_COURSE_CPT, 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			// Course categories and tags were replaced by the curriculum and Course Bundles (exported below).
			if ( in_array( $taxonomy->name, array( 'course_category', 'course_tag' ), true ) ) {
				continue;
			}
			$terms = wp_get_post_terms( $course_id, $taxonomy->name, array( 'fields' => 'all' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$course_terms[] = array(
						'taxonomy' => $taxonomy->name,
						'term'     => $term->name,
						'slug'     => $term->slug,
					);
				}
			}
		}

		// Prepare the data for export.
		return array(
			'id'               => $course_post->ID,
			'title'            => $course_post->post_title,
			'thumbnail'        => array(
				'id'  => $thumbnail_id,
				'url' => $thumbnail_url,
			),
			'type'             => $course_post->post_type,
			'slug'             => $course_post->post_name,
			'content'          => $course_post->post_content,
			'excerpt'          => $course_post->post_excerpt,
			'status'           => $course_post->post_status,
			'date_created'     => $course_post->post_date,
			'date_modified'    => $course_post->post_modified,
			'meta'             => $course_meta,
			'terms'            => $course_terms,
			'curriculum'       => $this->get_curriculum_paths( $course_id ),
			'course_links'     => \OhMyLMS\Curriculum\CourseLinks::export( $course_id ),
			'learning_program' => \OhMyLMS\Learning\CourseProgram::portable( $course_id ),
			'learning_bundles' => array_values(
				array_map(
					static function ( $bundle ) {
						return $bundle['title'];
					},
					\OhMyLMS\Curriculum\Placement::bundles( $course_id )
				)
			),
			'contents'         => $contents, // Include all chapters and their associated contents.
		);
	}

	/**
	 * The curriculum items a course is linked to, each as the names from the top level down.
	 *
	 * @param int $course_id The course ID.
	 * @return array[] For example [['Science', 'Physics']].
	 */
	private function get_curriculum_paths( $course_id ) {
		$paths = array();
		foreach ( \OhMyLMS\Curriculum\Placement::items( $course_id ) as $item ) {
			$paths[] = array_merge( (array) $item['path'], array( $item['name'] ) );
		}
		return $paths;
	}

	/**
	 * Retrieve all chapters and their contents for a course.
	 *
	 * @param int $course_id The course ID.
	 * @return array An array of chapters and their associated contents.
	 */
	private function get_course_contents( $course_id ) {
		global $wpdb;

		$chapters = array();

		// Fetch chapters for the course.
		$chapter_query = $wpdb->prepare(
			'
            SELECT * 
            FROM %i
            WHERE course_id = %d 
            ORDER BY order_number ASC
        ',
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			$course_id
		);

		$chapter_results = $wpdb->get_results( $chapter_query, ARRAY_A );

		foreach ( $chapter_results as $chapter ) {
			$chapter_id   = $chapter['chapter_id'];
			$chapter_post = get_post( $chapter_id );

			// Fetch chapter metadata.
			$chapter_meta = get_post_meta( $chapter_id );

			if ( ! empty( $chapter_meta['_video_id'][0] ) ) {
				$chapter_meta['_video_url'][] = wp_get_attachment_url( $chapter_meta['_video_id'][0] );
			}

			// Fetch contents for each chapter.
			$content_query = $wpdb->prepare(
				'
                SELECT * 
                FROM %i
                WHERE chapter_id = %d 
                ORDER BY order_number ASC
            ',
				$wpdb->prefix . 'ohmylms_content_relationship',
				$chapter_id
			);

			$content_results = $wpdb->get_results( $content_query, ARRAY_A );

			$contents = array();

			foreach ( $content_results as $content ) {
				$content_id   = $content['content_id'];
				$content_post = get_post( $content_id );

				if ( $content_post ) {
					$thumbnail_id  = get_post_thumbnail_id( $content_id ) ? get_post_thumbnail_id( $content_id ) : get_post_meta( $content_id, '_cover_image_id', true );
					$thumbnail_url = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : null;
					$content_meta  = get_post_meta( $content_id );
					if ( ! empty( $content_meta['_video_id'][0] ) ) {
						$content_meta['_video_url'][] = wp_get_attachment_url( $content_meta['_video_id'][0] );
					}

					$quiz_questions = array();
					if ( 'ohmylms-quiz' === $content_post->post_type ) {
						// Fetch questions for the quiz.
						$questions_query = $wpdb->prepare(
							'
                            SELECT * 
                            FROM %i
                            WHERE quiz_id = %d 
                            ORDER BY order_number ASC
                        ',
							$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
							$content_id
						);

						$question_results = $wpdb->get_results( $questions_query, ARRAY_A );

						foreach ( $question_results as $question ) {
							$question_id   = $question['question_id'];
							$question_post = get_post( $question_id );

							if ( $question_post ) {
								$question_meta = get_post_meta( $question_id );

								// Fetch answers for the question.
								$answers_query = $wpdb->prepare(
									'
                                    SELECT * 
                                    FROM %i
                                    WHERE question_id = %d 
                                    ORDER BY order_number ASC
                                ',
									$wpdb->prefix . 'ohmylms_question_answers',
									$question_id
								);

								$answers_results = $wpdb->get_results( $answers_query, ARRAY_A );

								$answers = array();
								foreach ( $answers_results as $answer ) {
									// Fetch answer metadata.
									$answer_meta_query = $wpdb->prepare(
										'
                                        SELECT * 
                                        FROM %i
                                        WHERE answer_id = %d
                                    ',
										$wpdb->prefix . 'ohmylms_question_answermeta',
										$answer['id']
									);

									$answer_meta_results = $wpdb->get_results( $answer_meta_query, ARRAY_A );

									$meta = array();
									foreach ( $answer_meta_results as $meta_entry ) {
										$meta[ $meta_entry['meta_key'] ] = $meta_entry['meta_value'];
									}

									$answers[] = array(
										'id'           => $answer['id'],
										'answer'       => $answer['answer'],
										'order_number' => $answer['order_number'],
										'is_correct'   => (bool) $answer['is_correct'],
										'meta'         => $meta, // Include all metadata for the answer.
									);
								}

								$quiz_questions[] = array(
									'id'            => $question_post->ID,
									'title'         => $question_post->post_title,
									'type'          => $question_post->post_type,
									'slug'          => $question_post->post_name,
									'content'       => $question_post->post_content,
									'excerpt'       => $question_post->post_excerpt,
									'status'        => $question_post->post_status,
									'date_created'  => $question_post->post_date,
									'date_modified' => $question_post->post_modified,
									'meta'          => $question_meta, // Include all metadata for the question.
									'answers'       => $answers, // Add answers for the question.
								);
							}
						}
					}

					$contents[] = array(
						'id'            => $content_id,
						'title'         => $content_post->post_title,
						'thumbnail'     => array(
							'id'  => $thumbnail_id,
							'url' => $thumbnail_url,
						),
						'type'          => $content_post->post_type,
						'content_type'  => isset( $content['content_type'] ) ? $content['content_type'] : $content_post->post_type,
						'slug'          => $content_post->post_name,
						'content'       => $content_post->post_content,
						'excerpt'       => $content_post->post_excerpt,
						'status'        => $content_post->post_status,
						'date_created'  => $content_post->post_date,
						'date_modified' => $content_post->post_modified,
						'meta'          => $content_meta, // Fetch all metadata for the content.
						'questions'     => $quiz_questions, // Add questions if it's a quiz.
					);
				}
			}

			// Add chapter and its contents.
			if ( $chapter_post ) {
				$thumbnail_id  = get_post_thumbnail_id( $chapter_id );
				$thumbnail_url = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : null;

				$chapters[] = array(
					'id'            => $chapter_id,
					'title'         => $chapter_post->post_title,
					'thumbnail'     => array(
						'id'  => $thumbnail_id,
						'url' => $thumbnail_url,
					),
					'type'          => $chapter_post->post_type,
					'slug'          => $chapter_post->post_name,
					'content'       => $chapter_post->post_content,
					'excerpt'       => $chapter_post->post_excerpt,
					'status'        => $chapter_post->post_status,
					'date_created'  => $chapter_post->post_date,
					'date_modified' => $chapter_post->post_modified,
					'meta'          => $chapter_meta, // Include all metadata for the chapter.
					'contents'      => $contents,
				);
			}
		}

		return $chapters;
	}
}
