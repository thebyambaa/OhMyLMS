<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling assignment REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Course;
use OhMyLMS\Data\Chapter;
use OhMyLMS\Data\Lesson;
use OhMyLMS\Data\Quiz;
use OhMyLMS\Data\Assignment;
use OhMyLMS\DataException;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WP_HTTP_Response;
use WP_Query;

/**
 * Controller for handling assignment REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for assignment-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class AIController extends RestController {

	/**
	 * The base route for assignment base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'ai';

	/**
	 * Check if the current user has permission to edit posts.
	 *
	 * This function checks if the current user has the 'edit_posts' capability.
	 *
	 * @return bool True if the user has the 'edit_posts' capability, false otherwise.
	 * @since 1.0.0
	 */
	public function check_ai_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Registers REST API routes for assignment operations.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/course/',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_or_update_item' ),
					'permission_callback' => array( $this, 'check_ai_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);
	}


	/**
	 * Create course with AI
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update_item( $request ) {
		try {
			$course_id = $this->save_course( $request );
			$post      = get_post( $course_id );

			/**
			 * Fires after a course is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the course.
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_course', $post, $request );

			if ( $course_id ) {
				$this->delete_exiting_contents( $course_id );
				$chapters = $request->get_param( 'chapters' );
				if ( $chapters ) {
					$chapter_order   = 0;
					$course_chapters = array();
					foreach ( $chapters as $chapter ) {
						$chapter_id = $this->save_chapter( $chapter, $course_id );
						$post       = get_post( $chapter_id );

						/**
						 * Fires after a chapter is inserted via the REST API.
						 *
						 * @param \WP_Post         $post    The post object for the chapter.
						 * @param \WP_REST_Request $request The request object.
						 *
						 * @since 1.0.0
						 */
						do_action( 'ohmylms_rest_insert_chapter', $post, $request );

						$contents = isset( $chapter['contents'] ) ? $chapter['contents'] : array();
						$lessons  = array();
						if ( $contents ) {
							$order_number = 0;
							foreach ( $contents as $content ) {
								$content_id = $this->save_content( $content, $chapter_id );
								$post       = get_post( $content_id );
								/**
								 * Fires after a content is inserted via the REST API.
								 *
								 * @param \WP_Post         $post    The post object for the content.
								 * @param \WP_REST_Request $request The request object.
								 *
								 * @since 1.0.0
								 */
								do_action( 'ohmylms_rest_insert_content', $post, $request );
								++$order_number;

								$content   = array(
									'id'           => $content_id,
									'order_number' => $order_number,
									'title'        => isset( $content['title'] ) ? $content['title'] : '',
									'description'  => isset( $content['description'] ) ? $content['description'] : '',
									'type'         => isset( $content['type'] ) ? $content['type'] : '',
								);
								$lessons[] = $content;
							}
						}

						$chapter_obj = ohmylms_get_chapter( $chapter_id );
						$chapter_obj->set_contents( $lessons );
						$chapter_obj->set_parent_id( $course_id );
						$chapter_obj->save();
						++$chapter_order;
						$chapter           = array(
							'id'           => $chapter_id,
							'order_number' => $chapter_order,
							'title'        => isset( $chapter['title'] ) ? $chapter['title'] : '',
							'description'  => isset( $chapter['description'] ) ? $chapter['description'] : '',
						);
						$course_chapters[] = $chapter;
					}
				}
				$course_obj = ohmylms_get_course( $course_id );
				$course_obj->set_chapters( $course_chapters );
				$course_obj->save();
			}

			do_action( 'ohmylms_ai_course_outline_created', $course_id );

			$request->set_param( 'context', 'edit' );

			$response = array(
				'course_id' => $course_id,
				'message'   => 'Course created successfully',
				'status'    => 201,
				'success'   => true,
			);

			$response = rest_ensure_response( $response );
			$response->set_status( 201 );
			return $response;
		} catch ( DataException $e ) {
			return new WP_Error( 400, $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	/**
	 * Save chapter for Aicontroller.
	 *
	 * @param mixed $request REST request.
	 * @param mixed $course_id Course identifier.
	 */
	public function save_chapter( $request, $course_id ) {
		$chapter = $this->prepare_chapter_for_database( $request );
		return $chapter->save();
	}

	/**
	 * Save content for Aicontroller.
	 *
	 * @param mixed $request REST request.
	 * @param mixed $chapter_id Chapter identifier.
	 */
	public function save_content( $request, $chapter_id ) {
		$content_id = $this->prepare_content_for_database( $request, $chapter_id );
		return $content_id;
	}


	/**
	 * Prepare content for database for Aicontroller.
	 *
	 * @param mixed $request REST request.
	 * @param mixed $chapter_id Chapter identifier.
	 */
	public function prepare_content_for_database( $request, $chapter_id ) {
		$chapter = ohmylms_get_chapter( $chapter_id );
		if ( $chapter instanceof Chapter ) {
			$lesson_types = apply_filters( 'ohmylms_lesson_types', array( 'text', 'audio', 'video' ) );

			if ( in_array( $request['type'], $lesson_types ) ) {
				if ( isset( $request['id'] ) ) {
					$lesson_obj = ohmylms_get_lesson( $request['id'] );
				} else {
					$lesson_obj = new Lesson( $chapter );
				}
			} elseif ( 'quiz' == $request['type'] ) {
				$lesson_obj = new Quiz( $chapter );
			} elseif ( 'assignment' == $request['type'] ) {
				$lesson_obj = new Assignment( $chapter );
			}

			if ( isset( $request['title'] ) ) {
				$lesson_obj->set_name( $request['title'] );
			}
			if ( isset( $request['description'] ) ) {
				$lesson_obj->set_description( $request['description'] );
			}
			if ( isset( $request['type'] ) && in_array( $request['type'], $lesson_types ) ) {
				$lesson_obj->set_type( $request['type'] );
			}
			$lesson_obj->save();

			return $lesson_obj->get_id();
		}
	}

	/**
	 * Prepare a single course for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {

		$course      = ohmylms_get_course( $post );
		$data        = $this->get_course_data( $course );
		$chapters    = $course->get_chapters();
		$certificate = $course->get_certificate();
		if ( $certificate ) {
			$data['certificate'] = array(
				'name'         => $certificate->get_name(),
				'id'           => $certificate->get_id(),
				'date_created' => $certificate->get_date_created(),
				'status'       => $certificate->get_status(),
				'thumbnail_id' => $certificate->get_thumbnail_id(),
				'image_src'    => wp_get_attachment_image_src( $certificate->get_thumbnail_id(), 'large' ) ? wp_get_attachment_image_src( $certificate->get_thumbnail_id(), 'large' )[0] : '',
			);
		}
		$data['chapters']              = $chapters ? $chapters : array();
		$data['first_chapter_content'] = array();
		$automation                    = get_post_meta( $course->get_id(), '_mm_automation_id', true );

		$data['has_automation'] = is_array( $automation ) && count( $automation ) ? true : false;
		$chapter_id             = is_array( $chapters ) && count( $chapters ) ? $chapters[0]['id'] : '';
		if ( $chapter_id ) {
			$chapter = ohmylms_get_chapter( $chapter_id );
			if ( ( $chapter instanceof Chapter ) ) {
				$lessons                       = $chapter->get_lessons();
				$data['first_chapter_content'] = $lessons;
				$data['first_chapter_id']      = $chapter_id;
			}
		}
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $course, $request ) );

		/**
		 * Filters the response for the course in the REST API.
		 *
		 * This filter allows developers to modify the course response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the course.
		 * @param \WP_Post $post The WP_Post object representing the course.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_course', $response, $post, $request );
	}

	/**
	 * Delete exiting contents for Aicontroller.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	public function delete_exiting_contents( $course_id ) {
		$course = ohmylms_get_course( $course_id );
		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_CHAPTER_RELATIONSHIP;
		$wpdb->delete(
			$table_name,
			array(
				'course_id' => $course_id,
			),
			array(
				'%d',
			)
		);

		if ( $course instanceof Course ) {
			$chapters = $course->get_chapters();

			if ( $chapters ) {
				foreach ( $chapters as $chapter ) {
					$chapter_obj = ohmylms_get_chapter( $chapter['id'] );
					if ( $chapter_obj instanceof Chapter ) {
						$lessons = $chapter_obj->get_lessons();
						if ( $lessons ) {
							foreach ( $lessons as $lesson ) {
								$lesson_obj = ohmylms_get_lesson( $lesson['id'] );
								if ( $lesson_obj instanceof Lesson ) {
									$lesson_obj->delete();
								}
							}
						}
						$chapter_obj->delete();
					}
				}
			}
		}
	}


	/**
	 * Saves a course to the database.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function save_course( $request ) {
		$course = $this->prepare_course_for_database( $request );
		return $course->save();
	}


	/**
	 * Prepare a single course for create or update.
	 *
	 * @param mixed $request REST request.
	 * @return bool|Course|object|WP_Error
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function prepare_course_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( isset( $request['id'] ) ) {
			$course = ohmylms_get_course( $id );
		} else {
			$course = ohmylms_is_pro() ? new \OhMyLMS\Data\Course() : new Course();
		}

		if ( isset( $request['title'] ) ) {
			$course->set_name( wp_filter_post_kses( sanitize_text_field( $request['title'] ) ) );
		}

		if ( isset( $request['description'] ) ) {
			$course->set_description( wp_filter_post_kses( sanitize_text_field( $request['description'] ) ) );
		}

		if ( isset( $request['course_type'] ) ) {
			$course->set_type( $request['course_type'] );
		}

		if ( isset( $request['status'] ) ) {
			$course->set_status( get_post_status_object( $request['status'] ) ? sanitize_text_field( $request['status'] ) : 'draft' );
		}
		return $course;
	}


	/**
	 * Prepare a single chapter for create or update.
	 *
	 * @param mixed $request REST request.
	 * @return bool|Chapter|object|WP_Error
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function prepare_chapter_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( $id > 0 ) {
			$chapter = ohmylms_get_chapter( $id );
		} else {
			$chapter = new Chapter();
		}

		if ( isset( $request['title'] ) ) {
			$chapter->set_name( wp_filter_post_kses( $request['title'] ) );
		}

		if ( isset( $request['description'] ) ) {
			$chapter->set_description( wp_filter_post_kses( $request['description'] ) );
		}

		if ( isset( $request['status'] ) ) {
			$chapter->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}

		return $chapter;
	}

	/**
	 * Update course with AI
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function update_item( $request ) {

		$post_id = (int) $request['id'];

		if ( empty( $post_id ) || get_post_type( $post_id ) !== OHMYLMS_COURSE_CPT ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		return null;
	}
}
