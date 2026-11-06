<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Front-dashboard course builder, quiz builder and skills screen.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Dashboard\Front;
use OhMyLMS\Learning\QuestionAliases;
use OhMyLMS\Performance\Levels;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\Quiz\QuizSettings;
use OhMyLMS\Quiz\QuizSkills;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the existing course, quiz, question and skill stores.
 */
class StudioController extends RestController {

	/** Register the studio routes. */
	public function register_routes() {
		$read = array(
			'methods'             => \WP_REST_Server::READABLE,
			'permission_callback' => array( $this, 'can_author' ),
		);
		register_rest_route( $this->namespace, '/front-dashboard/studio/courses', array( $read + array( 'callback' => array( $this, 'courses' ) ) ) );
		register_rest_route( $this->namespace, '/front-dashboard/studio/courses/(?P<id>\d+)', array( $read + array( 'callback' => array( $this, 'course' ) ) ) );
		register_rest_route(
			$this->namespace,
			'/front-dashboard/studio/courses/(?P<id>\d+)/order',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_order' ),
					'permission_callback' => array( $this, 'can_author' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/studio/courses/(?P<id>\d+)/units',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_unit' ),
					'permission_callback' => array( $this, 'can_author' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/studio/units/(?P<id>\d+)/items',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_item' ),
					'permission_callback' => array( $this, 'can_author' ),
				),
			)
		);
		register_rest_route( $this->namespace, '/front-dashboard/studio/quizzes', array( $read + array( 'callback' => array( $this, 'quizzes' ) ) ) );
		register_rest_route(
			$this->namespace,
			'/front-dashboard/studio/quizzes/(?P<id>\d+)',
			array(
				$read + array( 'callback' => array( $this, 'quiz' ) ),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_quiz' ),
					'permission_callback' => array( $this, 'can_author' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/studio/skills',
			array(
				$read + array( 'callback' => array( $this, 'skills' ) ),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_skill' ),
					'permission_callback' => array( $this, 'can_author' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/studio/skills/import',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_skills' ),
					'permission_callback' => array( $this, 'can_author' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/front-dashboard/studio/skills/(?P<id>\d+)/prerequisites',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'prerequisites' ),
					'permission_callback' => array( $this, 'can_author' ),
				),
			)
		);
	}

	/**
	 * Content authors and people who may open the dashboard content section.
	 *
	 * @return bool
	 */
	public function can_author() {
		return is_user_logged_in() && ( current_user_can( 'edit_posts' ) || Front::allows( Front::scope(), 'content' ) );
	}

	/**
	 * Whether this user may change a post.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	private function can_edit( $id ) {
		return current_user_can( 'edit_post', $id ) || current_user_can( 'edit_posts' );
	}

	/**
	 * Courses the author can open.
	 *
	 * @return \WP_REST_Response
	 */
	public function courses() {
		return rest_ensure_response(
			array(
				'canEdit' => current_user_can( 'edit_posts' ),
				'courses' => $this->posts( OHMYLMS_COURSE_CPT ),
			)
		);
	}

	/**
	 * Units and items of one course.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function course( $request ) {
		$course = get_post( (int) $request['id'] );
		if ( ! $course || OHMYLMS_COURSE_CPT !== $course->post_type ) {
			return new \WP_Error( 'ohmylms_studio_course', __( 'Course not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'id'      => (int) $course->ID,
				'title'   => get_the_title( $course ),
				'canEdit' => $this->can_edit( $course->ID ),
				'units'   => $this->units( (int) $course->ID ),
				'skills'  => $this->skill_options(),
			)
		);
	}

	/**
	 * Save unit and item order. Keyboard and drag-and-drop both call this.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_order( $request ) {
		$course = (int) $request['id'];
		if ( ! $this->can_edit( $course ) || OHMYLMS_COURSE_CPT !== get_post_type( $course ) ) {
			return new \WP_Error( 'ohmylms_studio_forbidden', __( 'You cannot reorder this course.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$body  = $request->get_json_params();
		$units = isset( $body['units'] ) && is_array( $body['units'] ) ? $body['units'] : array();
		global $wpdb;
		$position = 1;
		foreach ( $units as $unit ) {
			$chapter = isset( $unit['id'] ) ? absint( $unit['id'] ) : 0;
			if ( ! $chapter || ! $this->chapter_in_course( $chapter, $course ) ) {
				continue;
			}
			$wpdb->update(
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				array( 'order_number' => $position ),
				array(
					'course_id'  => $course,
					'chapter_id' => $chapter,
				),
				array( '%d' ),
				array( '%d', '%d' )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			++$position;
			$item_position = 1;
			$items         = isset( $unit['items'] ) && is_array( $unit['items'] ) ? $unit['items'] : array();
			foreach ( $items as $item ) {
				$content = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
				if ( ! $content ) {
					continue;
				}
				$wpdb->update(
					$wpdb->prefix . 'ohmylms_content_relationship',
					array( 'order_number' => $item_position ),
					array(
						'chapter_id' => $chapter,
						'content_id' => $content,
					),
					array( '%d' ),
					array( '%d', '%d' )
				); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				++$item_position;
			}
		}
		return $this->course( $request );
	}

	/**
	 * Add a unit (chapter) to a course.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_unit( $request ) {
		$course = (int) $request['id'];
		if ( ! $this->can_edit( $course ) ) {
			return new \WP_Error( 'ohmylms_studio_forbidden', __( 'You cannot edit this course.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$body  = $request->get_json_params();
		$title = sanitize_text_field( isset( $body['title'] ) ? (string) $body['title'] : '' );
		if ( '' === $title ) {
			return new \WP_Error( 'ohmylms_studio_title', __( 'A unit needs a name.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$chapter = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_CHAPTER_CPT,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $chapter ) ) {
			return $chapter;
		}
		global $wpdb;
		$next = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(order_number) FROM %i WHERE course_id=%d', $wpdb->prefix . 'ohmylms_chapter_relationship', $course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			array(
				'course_id'    => $course,
				'chapter_id'   => (int) $chapter,
				'order_number' => $next + 1,
			),
			array( '%d', '%d', '%d' )
		);
		return $this->course( $request );
	}

	/**
	 * Add a lesson, video, article, exercise or unit test to a unit.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_item( $request ) {
		$chapter = (int) $request['id'];
		if ( OHMYLMS_CHAPTER_CPT !== get_post_type( $chapter ) || ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_studio_forbidden', __( 'You cannot add to this unit.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$body  = $request->get_json_params();
		$kind  = sanitize_key( isset( $body['kind'] ) ? (string) $body['kind'] : 'lesson' );
		$title = sanitize_text_field( isset( $body['title'] ) ? (string) $body['title'] : '' );
		if ( '' === $title ) {
			return new \WP_Error( 'ohmylms_studio_title', __( 'An item needs a name.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$quiz = in_array( $kind, array( 'exercise', 'quiz', 'unit_test' ), true );
		$post = wp_insert_post(
			array(
				'post_type'    => $quiz ? OHMYLMS_QUIZ_CPT : OHMYLMS_LESSON_CPT,
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_content' => $quiz ? '' : $this->starter( $kind ),
			),
			true
		);
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'unit_test' === $kind ) {
			update_post_meta( (int) $post, '_ohmylms_unit_test', 1 );
		}
		$skills = isset( $body['skills'] ) && is_array( $body['skills'] ) ? array_map( 'absint', $body['skills'] ) : array();
		if ( $skills ) {
			wp_set_object_terms( (int) $post, $skills, Taxonomy::NAME, false );
		}
		if ( $quiz && $skills ) {
			QuizSkills::set( (int) $post, $skills );
		}
		global $wpdb;
		$next = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(order_number) FROM %i WHERE chapter_id=%d', $wpdb->prefix . 'ohmylms_content_relationship', $chapter ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$type = $quiz ? 'quiz' : ( 'video' === $kind ? 'video' : ( 'article' === $kind ? 'text' : 'lesson' ) );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => (int) $post,
				'content_type' => $type,
				'order_number' => $next + 1,
			),
			array( '%d', '%d', '%s', '%d' )
		);
		$course = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT course_id FROM %i WHERE chapter_id=%d LIMIT 1', $wpdb->prefix . 'ohmylms_chapter_relationship', $chapter ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$request->set_param( 'id', $course );
		return $this->course( $request );
	}

	/**
	 * Quizzes the author can open.
	 *
	 * @return \WP_REST_Response
	 */
	public function quizzes() {
		return rest_ensure_response(
			array(
				'canEdit' => current_user_can( 'edit_posts' ),
				'quizzes' => $this->posts( OHMYLMS_QUIZ_CPT ),
				'levels'  => $this->levels(),
			)
		);
	}

	/**
	 * One quiz, its questions and settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function quiz( $request ) {
		$quiz = get_post( (int) $request['id'] );
		if ( ! $quiz || OHMYLMS_QUIZ_CPT !== $quiz->post_type ) {
			return new \WP_Error( 'ohmylms_studio_quiz', __( 'Quiz not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$settings = QuizSettings::read( $quiz->ID );
		return rest_ensure_response(
			array(
				'id'        => (int) $quiz->ID,
				'title'     => get_the_title( $quiz ),
				'canEdit'   => $this->can_edit( $quiz->ID ),
				'settings'  => array(
					'pass'     => $settings['passing_score'],
					'attempts' => $settings['attempts_allowed'],
					'shuffle'  => (bool) $settings['randomize_questions'],
				),
				'levels'    => $this->levels(),
				'questions' => $this->questions( (int) $quiz->ID ),
				'skills'    => $this->skill_options(),
				'skillIds'  => QuizSkills::get( $quiz->ID ),
			)
		);
	}

	/**
	 * Save quiz settings and questions through the question bank writer.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_quiz( $request ) {
		$quiz = get_post( (int) $request['id'] );
		if ( ! $quiz || OHMYLMS_QUIZ_CPT !== $quiz->post_type || ! $this->can_edit( $quiz->ID ) ) {
			return new \WP_Error( 'ohmylms_studio_forbidden', __( 'You cannot edit this quiz.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$body     = $request->get_json_params();
		$body     = is_array( $body ) ? $body : array();
		$current  = QuizSettings::read( $quiz->ID );
		$settings = isset( $body['settings'] ) && is_array( $body['settings'] ) ? $body['settings'] : array();
		$checked  = QuizSettings::validate(
			array(
				'passing_score'       => isset( $settings['pass'] ) ? (float) $settings['pass'] : (float) $current['passing_score'],
				'attempts_allowed'    => isset( $settings['attempts'] ) ? (int) $settings['attempts'] : (int) $current['attempts_allowed'],
				'randomize_questions' => array_key_exists( 'shuffle', $settings ) ? (bool) $settings['shuffle'] : (bool) $current['randomize_questions'],
			),
			$current
		);
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		$saved = QuizSettings::save( $quiz->ID, $checked );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( isset( $body['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $quiz->ID,
					'post_title' => sanitize_text_field( (string) $body['title'] ),
				)
			);
		}
		if ( isset( $body['skillIds'] ) && is_array( $body['skillIds'] ) ) {
			$result = QuizSkills::set( $quiz->ID, $body['skillIds'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		$questions = isset( $body['questions'] ) && is_array( $body['questions'] ) ? $body['questions'] : array();
		foreach ( array_values( $questions ) as $index => $question ) {
			if ( ! is_array( $question ) ) {
				continue;
			}
			$result = $this->save_question( $question, (int) $quiz->ID, $index + 1 );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return $this->quiz( $request );
	}

	/**
	 * Subject, grade and skill tree, plus skills with no exercise.
	 *
	 * @return \WP_REST_Response
	 */
	public function skills() {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
			)
		);
		$rows  = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$rows[] = $this->skill_row( $term );
			}
		}
		return rest_ensure_response(
			array(
				'canEdit'  => current_user_can( 'edit_posts' ),
				'skills'   => $rows,
				'coverage' => array_values(
					array_filter(
						$rows,
						static function ( $row ) {
							return 'skill' === $row['depth'] && ! $row['uses'];
						}
					)
				),
			)
		);
	}

	/**
	 * Create or rename a subject, grade or skill.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_skill( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_studio_forbidden', __( 'You cannot edit skills.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$body   = $request->get_json_params();
		$body   = is_array( $body ) ? $body : array();
		$name   = sanitize_text_field( isset( $body['name'] ) ? (string) $body['name'] : '' );
		$parent = isset( $body['parent'] ) ? absint( $body['parent'] ) : 0;
		$id     = isset( $body['id'] ) ? absint( $body['id'] ) : 0;
		if ( '' === $name ) {
			return new \WP_Error( 'ohmylms_studio_title', __( 'A skill needs a name.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( $id ) {
			$updated = wp_update_term(
				$id,
				Taxonomy::NAME,
				array(
					'name'   => $name,
					'parent' => $parent,
				)
			);
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		} else {
			$created = wp_insert_term( $name, Taxonomy::NAME, array( 'parent' => $parent ) );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$id = (int) $created['term_id'];
		}
		if ( isset( $body['mn'] ) ) {
			update_term_meta( $id, '_ohmylms_name_mn', sanitize_text_field( (string) $body['mn'] ) );
		}
		if ( isset( $body['subject'] ) ) {
			update_term_meta( $id, Comparison::SUBJECT_META, sanitize_text_field( (string) $body['subject'] ) );
		}
		return $this->skills();
	}

	/**
	 * Set prerequisites. Cycles are rejected by the skill catalogue.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function prerequisites( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_studio_forbidden', __( 'You cannot edit skills.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$body  = $request->get_json_params();
		$ids   = isset( $body['ids'] ) && is_array( $body['ids'] ) ? $body['ids'] : array();
		$saved = Taxonomy::set_prerequisites( (int) $request['id'], $ids );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return $this->skills();
	}

	/**
	 * Import subject, grade, skill and translation rows from CSV text or a file.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import_skills( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_studio_forbidden', __( 'You cannot import skills.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$csv   = (string) $request->get_param( 'csv' );
		$files = $request->get_file_params();
		if ( isset( $files['file']['tmp_name'] ) && is_uploaded_file( $files['file']['tmp_name'] ) ) {
			$contents = file_get_contents( $files['file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$csv      = is_string( $contents ) ? $contents : $csv;
		}
		$rows = $this->parse_skill_csv( $csv );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$made = 0;
		foreach ( $rows as $row ) {
			$subject = $this->ensure_term( $row['subject'], 0, $row['subject'] );
			$grade   = $this->ensure_term( $row['grade'], $subject, $row['subject'] );
			$skill   = $this->ensure_term( $row['skill'], $grade, $row['subject'] );
			if ( '' !== $row['mn'] ) {
				update_term_meta( $skill, '_ohmylms_name_mn', $row['mn'] );
			}
			++$made;
		}
		return rest_ensure_response( array( 'imported' => $made ) );
	}

	/**
	 * Posts of one type for the picker.
	 *
	 * @param string $type Post type.
	 * @return array
	 */
	private function posts( $type ) {
		$query = new \WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 40,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out   = array();
		foreach ( $query->posts as $post ) {
			$out[] = array(
				'id'     => (int) $post->ID,
				'title'  => get_the_title( $post ),
				'status' => (string) $post->post_status,
				'edit'   => get_edit_post_link( $post->ID, 'raw' ) ? get_edit_post_link( $post->ID, 'raw' ) : '',
			);
		}
		return $out;
	}

	/**
	 * Units and their items.
	 *
	 * @param int $course Course ID.
	 * @return array
	 */
	private function units( $course ) {
		global $wpdb;
		$chapters = $wpdb->get_results( $wpdb->prepare( 'SELECT chapter_id, order_number FROM %i WHERE course_id=%d ORDER BY order_number, chapter_id', $wpdb->prefix . 'ohmylms_chapter_relationship', $course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$out      = array();
		foreach ( (array) $chapters as $chapter ) {
			$items = $wpdb->get_results( $wpdb->prepare( 'SELECT content_id, content_type, order_number FROM %i WHERE chapter_id=%d ORDER BY order_number, content_id', $wpdb->prefix . 'ohmylms_content_relationship', $chapter->chapter_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows  = array();
			foreach ( (array) $items as $item ) {
				$post = get_post( (int) $item->content_id );
				if ( ! $post ) {
					continue;
				}
				$terms  = wp_get_object_terms( $post->ID, Taxonomy::NAME, array( 'fields' => 'ids' ) );
				$rows[] = array(
					'id'     => (int) $post->ID,
					'title'  => get_the_title( $post ),
					'type'   => (string) $item->content_type,
					'kind'   => OHMYLMS_QUIZ_CPT === $post->post_type ? 'quiz' : 'lesson',
					'edit'   => OHMYLMS_LESSON_CPT === $post->post_type ? (string) get_edit_post_link( $post->ID, 'raw' ) : '',
					'skills' => array_map( 'intval', is_array( $terms ) ? $terms : array() ),
				);
			}
			$out[] = array(
				'id'    => (int) $chapter->chapter_id,
				'title' => get_the_title( (int) $chapter->chapter_id ),
				'items' => $rows,
			);
		}
		return $out;
	}

	/**
	 * Whether the chapter belongs to the course.
	 *
	 * @param int $chapter Chapter ID.
	 * @param int $course  Course ID.
	 * @return bool
	 */
	private function chapter_in_course( $chapter, $course ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id=%d AND chapter_id=%d', $wpdb->prefix . 'ohmylms_chapter_relationship', $course, $chapter ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Questions placed on a quiz. Bodies are read from the question posts, not copied.
	 *
	 * @param int $quiz Quiz ID.
	 * @return array
	 */
	private function questions( $quiz ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT question_id FROM %i WHERE quiz_id=%d ORDER BY order_number, question_id', $wpdb->prefix . 'ohmylms_quiz_questions_relationship', $quiz ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$post = get_post( (int) $row->question_id );
			if ( ! $post ) {
				continue;
			}
			$settings = get_post_meta( $post->ID, '_question_settings', true );
			$settings = is_array( $settings ) ? $settings : array();
			$answers  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, answer, is_correct FROM %i WHERE question_id=%d ORDER BY order_number, id', $wpdb->prefix . 'ohmylms_question_answers', $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$choices  = array();
			foreach ( (array) $answers as $answer ) {
				$choices[] = array(
					'id'      => (int) $answer->id,
					'text'    => wp_strip_all_tags( (string) $answer->answer ),
					'correct' => (bool) $answer->is_correct,
				);
			}
			$terms = wp_get_object_terms( $post->ID, Taxonomy::NAME, array( 'fields' => 'ids' ) );
			$out[] = array(
				'id'          => (int) $post->ID,
				'prompt'      => get_the_title( $post ),
				'type'        => isset( $settings['type'] ) ? (string) $settings['type'] : 'single-choice',
				'choices'     => $choices,
				'hint'        => (string) get_post_meta( $post->ID, '_ohmylms_hint', true ),
				'explanation' => (string) get_post_meta( $post->ID, '_ohmylms_explanation', true ),
				'skill'       => (int) ( is_array( $terms ) && $terms ? $terms[0] : 0 ),
			);
		}
		return $out;
	}

	/**
	 * Save one question by reference to the question bank.
	 *
	 * @param array $question Raw question.
	 * @param int   $quiz     Quiz ID.
	 * @param int   $order    Position.
	 * @return array|\WP_Error
	 */
	private function save_question( array $question, $quiz, $order ) {
		$type = QuestionAliases::canonical( isset( $question['type'] ) ? (string) $question['type'] : '' );
		if ( '' === $type ) {
			return new \WP_Error( 'ohmylms_studio_type', __( 'Choose a question type.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$prompt  = sanitize_text_field( isset( $question['prompt'] ) ? (string) $question['prompt'] : '' );
		$choices = isset( $question['choices'] ) && is_array( $question['choices'] ) ? $question['choices'] : array();
		$options = array();
		foreach ( array_values( $choices ) as $index => $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}
			$options[] = array(
				'id'           => isset( $choice['id'] ) ? absint( $choice['id'] ) : 0,
				'answer'       => sanitize_text_field( isset( $choice['text'] ) ? (string) $choice['text'] : '' ),
				'is_correct'   => ! empty( $choice['correct'] ),
				'order_number' => $index + 1,
			);
		}
		$settings = array( 'type' => $type );
		if ( 'numerical' === $type ) {
			$settings['answer']    = isset( $question['answer'] ) ? (float) $question['answer'] : 0;
			$settings['tolerance'] = 0;
		}
		$skill   = isset( $question['skill'] ) ? absint( $question['skill'] ) : 0;
		$payload = array(
			'id'           => isset( $question['id'] ) ? absint( $question['id'] ) : 0,
			'name'         => $prompt,
			'description'  => $prompt,
			'status'       => 'publish',
			'settings'     => $settings,
			'questions'    => $options,
			'order_number' => $order,
		);
		if ( $skill ) {
			$payload['skills'] = array(
				'question' => array(
					'primary'    => $skill,
					'supporting' => array(),
				),
			);
		}
		$result = DraftWriter::save( $payload, $quiz );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		update_post_meta( (int) $result['id'], '_ohmylms_hint', sanitize_text_field( isset( $question['hint'] ) ? (string) $question['hint'] : '' ) );
		update_post_meta( (int) $result['id'], '_ohmylms_explanation', sanitize_textarea_field( isset( $question['explanation'] ) ? (string) $question['explanation'] : '' ) );
		return $result;
	}

	/**
	 * Skill choices for chips.
	 *
	 * @return array
	 */
	private function skill_options() {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
			)
		);
		$out   = array();
		if ( ! is_array( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $term ) {
			$row = $this->skill_row( $term );
			if ( 'skill' === $row['depth'] ) {
				$out[] = array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			}
		}
		return $out;
	}

	/**
	 * One skill row for the tree.
	 *
	 * @param \WP_Term $term Term.
	 * @return array
	 */
	private function skill_row( $term ) {
		$parent = (int) $term->parent;
		$depth  = 'subject';
		if ( $parent ) {
			$grade = get_term( $parent, Taxonomy::NAME );
			$depth = ( $grade && ! is_wp_error( $grade ) && $grade->parent ) ? 'skill' : 'grade';
		}
		$uses = (int) $term->count;
		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'ohmylms_quiz_skills' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$uses += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE term_id=%d', $wpdb->prefix . 'ohmylms_quiz_skills', $term->term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		return array(
			'id'            => (int) $term->term_id,
			'name'          => $term->name,
			'mn'            => (string) get_term_meta( $term->term_id, '_ohmylms_name_mn', true ),
			'parent'        => $parent,
			'depth'         => $depth,
			'uses'          => $uses,
			'prerequisites' => Taxonomy::prerequisites( $term->term_id ),
		);
	}

	/**
	 * Find or create a term under a parent.
	 *
	 * @param string $name    Name.
	 * @param int    $parent  Parent term.
	 * @param string $subject Subject label stored on the term.
	 * @return int
	 */
	private function ensure_term( $name, $parent, $subject ) {
		$existing = term_exists( $name, Taxonomy::NAME, $parent );
		if ( is_array( $existing ) ) {
			$id = (int) $existing['term_id'];
		} else {
			$created = wp_insert_term( $name, Taxonomy::NAME, array( 'parent' => $parent ) );
			$id      = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
		}
		if ( $id && $subject ) {
			update_term_meta( $id, Comparison::SUBJECT_META, $subject );
		}
		return $id;
	}

	/**
	 * Parse a skills CSV.
	 *
	 * @param string $csv CSV text.
	 * @return array|\WP_Error
	 */
	private function parse_skill_csv( $csv ) {
		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $stream, (string) $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $stream );
		$header = fgetcsv( $stream );
		if ( ! is_array( $header ) ) {
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new \WP_Error( 'ohmylms_studio_csv', __( 'The CSV needs a header row.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$map = array();
		foreach ( $header as $index => $label ) {
			$map[ sanitize_key( (string) $label ) ] = $index;
		}
		foreach ( array( 'subject', 'grade', 'skill' ) as $column ) {
			if ( ! isset( $map[ $column ] ) ) {
				fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return new \WP_Error( 'ohmylms_studio_csv', __( 'The CSV needs subject, grade and skill columns.', 'ohmylms' ), array( 'status' => 400 ) );
			}
		}
		$rows = array();
		while ( ( $values = fgetcsv( $stream ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$skill = sanitize_text_field( $values[ $map['skill'] ] ?? '' );
			if ( '' === $skill ) {
				continue;
			}
			$rows[] = array(
				'subject' => sanitize_text_field( $values[ $map['subject'] ] ?? '' ),
				'grade'   => sanitize_text_field( $values[ $map['grade'] ] ?? '' ),
				'skill'   => $skill,
				'mn'      => sanitize_text_field( $values[ $map['mn'] ?? -1 ] ?? '' ),
			);
			if ( count( $rows ) >= 200 ) {
				break;
			}
		}
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! $rows ) {
			return new \WP_Error( 'ohmylms_studio_csv', __( 'No skills were found.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return $rows;
	}

	/**
	 * Mastery ladder labels.
	 *
	 * @return string[]
	 */
	private function levels() {
		$out = array();
		foreach ( array( 'attempted', 'familiar', 'proficient', 'mastered' ) as $level ) {
			if ( in_array( $level, Levels::SCALE, true ) ) {
				$out[] = Levels::label( $level );
			}
		}
		return $out;
	}

	/**
	 * Starter block markup for a new lesson item.
	 *
	 * @param string $kind Item kind.
	 * @return string
	 */
	private function starter( $kind ) {
		if ( 'video' === $kind ) {
			return '<!-- wp:ohmylms/lesson-video {"url":"","title":""} /-->';
		}
		if ( 'article' === $kind ) {
			return '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
		}
		return '';
	}
}
