<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Content authoring and course objective REST endpoints.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\Curriculum\Schema as CurriculumSchema;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\Schema;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Content Hub: the catalog of a grade, exam or subject (a course) built from chapters and skills, the
 * lesson library, and the pickers behind both. Every write goes through the same validation as the
 * course's Learning tab; learners see changes only after `catalog/{id}/publish`.
 */
class ContentHubController extends RestController {
	/**
	 * Register Content Hub and objective mapping routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$edit   = array( $this, 'author' );
		$course = array( $this, 'course' );
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/syllabus-objectives',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'objectives' ),
					'permission_callback' => array( $this, 'objective_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_objectives' ),
					'permission_callback' => array( $this, 'objective_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/courses/from-syllabus',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'syllabus_choices' ),
					'permission_callback' => array( $this, 'author' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'course_from_syllabus' ),
					'permission_callback' => array( $this, 'author' ),
					'args'                => array(
						'syllabus_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
						'title'       => array( 'type' => 'string' ),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/courses/from-syllabus/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'author' ),
				'callback'            => array( $this, 'syllabus_preview' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/discard-if-blank',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function ( WP_REST_Request $request ) {
						return array( 'discarded' => \OhMyLMS\Learning\BlankDraft::discard( (int) $request['id'] ) );
					},
					'permission_callback' => $course,
				),
			)
		);
		$base = '/content-hub';
		register_rest_route(
			$this->namespace,
			$base . '/courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'courses' ),
					'permission_callback' => $edit,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/targets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'targets' ),
					'permission_callback' => $edit,
				),
			)
		);
		$catalog = $base . '/catalog/(?P<id>\d+)';
		register_rest_route(
			$this->namespace,
			$catalog,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $course,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $course,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$catalog . '/publish',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish' ),
					'permission_callback' => $course,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$catalog . '/chapters',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_chapter' ),
					'permission_callback' => $course,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$catalog . '/chapters/order',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'reorder_chapters' ),
					'permission_callback' => $course,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$catalog . '/chapters/(?P<chapter_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rename_chapter' ),
					'permission_callback' => $course,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_chapter' ),
					'permission_callback' => $course,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/lessons',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'lessons' ),
					'permission_callback' => $edit,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/lessons/trash',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'trash_lessons' ),
					'permission_callback' => $edit,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/lessons/(?P<id>\d+)/skills',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'lesson_skills' ),
					'permission_callback' => $edit,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/lessons/(?P<id>\d+)/duplicate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_lesson' ),
					'permission_callback' => $edit,
				),
			)
		);
	}

	/**
	 * Authorize content authors when learning storage is ready.
	 *
	 * @return true|\WP_Error
	 */
	public function author() {
		if ( ! Schema::ready() ) {
			return Catalog::error( __( 'Learning storage is unavailable.', 'ohmylms' ), 503, 'ohmylms_learning_unavailable' ); }
		return current_user_can( 'edit_posts' ) ? true : Catalog::error( __( 'Sorry, you are not allowed to manage content.', 'ohmylms' ), rest_authorization_required_code(), 'ohmylms_rest_forbidden' );
	}

	/**
	 * The syllabuses a course can be made from.
	 *
	 * @return array
	 */
	public function syllabus_choices() {
		return CurriculumSchema::ready() ? \OhMyLMS\Curriculum\CourseObjectives::syllabuses() : array();
	}

	/**
	 * Preview the source topics, lessons and skills for an independent course.
	 *
	 * @param WP_REST_Request $request Source request.
	 * @return array|\WP_Error
	 */
	public function syllabus_preview( WP_REST_Request $request ) {
		return CurriculumSchema::ready() ? \OhMyLMS\Curriculum\CourseFromSyllabus::preview( (int) $request['id'] ) : Catalog::error( __( 'Curriculum storage is unavailable.', 'ohmylms' ), 503 );
	}

	/**
	 * Make a draft course from a syllabus: a unit for each domain, with its skills linked.
	 *
	 * @param WP_REST_Request $request Request with the syllabus and an optional title.
	 * @return WP_REST_Response|WP_Error
	 */
	public function course_from_syllabus( WP_REST_Request $request ) {
		if ( ! CurriculumSchema::ready() ) {
			return Catalog::error( __( 'Curriculum storage is unavailable.', 'ohmylms' ), 503 );
		}
		$data = (array) $request->get_json_params();
		if ( $request->has_param( 'skill_ids' ) ) {
			$data['skill_ids'] = $request->get_param( 'skill_ids' );
			$data['revision']  = $request->get_param( 'revision' );
		}
		$course = \OhMyLMS\Curriculum\CourseFromSyllabus::create( (int) $request['syllabus_id'], (string) $request['title'], $data );
		return is_wp_error( $course ) ? $course : new \WP_REST_Response( $course, 201 );
	}

	/**
	 * Course authors may reference objectives without permission to edit curricula.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function objective_permission( WP_REST_Request $request ) {
		if ( ! CurriculumSchema::ready() ) {
			return Catalog::error( __( 'Curriculum storage is unavailable.', 'ohmylms' ), 503 );
		}
		return $this->course( $request );
	}

	/**
	 * Read saved mappings, or browse one existing syllabus without changing it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function objectives( WP_REST_Request $request ) {
		if ( $request->has_param( 'syllabus_id' ) ) {
			$rows = CourseObjectives::objectives( absint( $request['syllabus_id'] ) );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			$search = sanitize_text_field( (string) $request['search'] );
			if ( '' !== $search ) {
				$words = preg_split( '/\s+/u', trim( $search ) );
				$rows  = array_filter(
					$rows,
					static function ( $row ) use ( $words ) {
						$text = implode( ' ', array( $row['name'], $row['code'], $row['objective'], $row['topic'] ) );
						foreach ( $words as $word ) {
							if ( false === mb_stripos( $text, $word ) ) {
								return false;
							}
						}
						return true;
					}
				);
			}
			return $this->objective_response( array( 'objectives' => array_values( $rows ) ), 'objectives', $request );
		}
		return $this->objective_response( CourseObjectives::payload( (int) $request['id'] ), 'mappings', $request );
	}

	/**
	 * Bound MCP responses while retaining the unpaginated course editor contract.
	 *
	 * @param array           $payload Response data.
	 * @param string          $key List to paginate.
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	private function objective_response( array $payload, $key, WP_REST_Request $request ) {
		if ( ! $request->has_param( 'page' ) && ! $request->has_param( 'per_page' ) ) {
			return rest_ensure_response( $payload );
		}
		$size                   = max( 1, min( 50, (int) ( $request['per_page'] ?? 10 ) ) );
		$page                   = max( 1, (int) $request['page'] );
		$payload['total']       = count( $payload[ $key ] );
		$payload['total_pages'] = (int) ceil( $payload['total'] / $size );
		$payload['page']        = $page;
		$payload[ $key ]        = array_slice( $payload[ $key ], ( $page - 1 ) * $size, $size );
		$response               = rest_ensure_response( $payload );
		$response->header( 'X-WP-Total', $payload['total'] );
		$response->header( 'X-WP-TotalPages', $payload['total_pages'] );
		return $response;
	}

	/**
	 * Save target mappings independently of learning requirements.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_objectives( WP_REST_Request $request ) {
		$result = CourseObjectives::save( (int) $request['id'], $this->body( $request ) );
		return is_wp_error( $result ) ? $result : $this->objective_response( CourseObjectives::payload( (int) $request['id'] ), 'mappings', $request );
	}

	/**
	 * Authorize edits to the requested course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function course( WP_REST_Request $request ) {
		$ready = $this->author();
		if ( is_wp_error( $ready ) ) {
			return $ready; }
		return Catalog::can_edit( (int) $request['id'] ) ? true : Catalog::error( __( 'Sorry, you are not allowed to manage this course.', 'ohmylms' ), rest_authorization_required_code(), 'ohmylms_rest_forbidden' );
	}

	/**
	 * Read a JSON object body without accepting scalar payloads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private function body( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Return the current course catalog with the requested status.
	 *
	 * @param int $course_id Course ID.
	 * @param int $status HTTP status.
	 * @return \WP_REST_Response
	 */
	private function catalog( $course_id, $status = 200 ) {
		$response = rest_ensure_response( Catalog::payload( (int) $course_id ) );
		$response->set_status( $status );
		return $response;
	}

	/**
	 * List editable courses with pagination.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function courses( WP_REST_Request $request ) {
		$per_page = $request['per_page'];
		if ( ! $per_page ) {
			$per_page = 24;
		}
		return rest_ensure_response( Catalog::courses( (string) $request['search'], (int) $request['page'], $per_page ) );
	}

	/**
	 * Search reusable content after checking course permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function targets( WP_REST_Request $request ) {
		$course = (int) $request['course'];
		if ( $course && ! Catalog::can_edit( $course ) ) {
			return Catalog::error( __( 'Sorry, you are not allowed to manage this course.', 'ohmylms' ), rest_authorization_required_code(), 'ohmylms_rest_forbidden' ); }
		$targets = Catalog::targets( (string) $request['type'], (string) $request['search'], $course, (int) $request['page'] );
		return is_wp_error( $targets ) ? $targets : rest_ensure_response( $targets );
	}

	/**
	 * Read one course catalog.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		return $this->catalog( $request['id'] ); }

	/**
	 * Save the course catalog draft.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$saved = Catalog::save_draft( (int) $request['id'], $this->body( $request ) );
		return is_wp_error( $saved ) ? $saved : $this->catalog( $request['id'] );
	}

	/**
	 * Publish a validated course catalog.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function publish( WP_REST_Request $request ) {
		$data      = $this->body( $request );
		$published = Catalog::publish( (int) $request['id'], ! empty( $data['apply_existing'] ) );
		return is_wp_error( $published ) ? $published : $this->catalog( $request['id'] );
	}

	/**
	 * Create a chapter in this course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_chapter( WP_REST_Request $request ) {
		$chapter = Catalog::add_chapter( (int) $request['id'], (string) ( $this->body( $request )['name'] ?? '' ) );
		return is_wp_error( $chapter ) ? $chapter : $this->catalog( $request['id'], 201 );
	}

	/**
	 * Rename a chapter belonging to this course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rename_chapter( WP_REST_Request $request ) {
		$chapter = Catalog::update_chapter( (int) $request['id'], (int) $request['chapter_id'], $this->body( $request ) );
		return is_wp_error( $chapter ) ? $chapter : $this->catalog( $request['id'] );
	}

	/**
	 * Save the chapter order for this course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reorder_chapters( WP_REST_Request $request ) {
		$done = Catalog::reorder_chapters( (int) $request['id'], (array) ( $this->body( $request )['ids'] ?? array() ) );
		return is_wp_error( $done ) ? $done : $this->catalog( $request['id'] );
	}

	/**
	 * Remove a chapter after checking its current usage.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_chapter( WP_REST_Request $request ) {
		$done = Catalog::delete_chapter( (int) $request['id'], (int) $request['chapter_id'] );
		return is_wp_error( $done ) ? $done : $this->catalog( $request['id'] );
	}

	/**
	 * List reusable lessons using the requested filters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function lessons( WP_REST_Request $request ) {
		return rest_ensure_response(
			Catalog::lessons(
				array(
					'search'   => (string) $request['search'],
					'status'   => (string) $request['status'],
					'page'     => (int) $request['page'],
					'per_page' => (int) $request['per_page'],
					'orderby'  => (string) $request['orderby'],
					'order'    => (string) $request['order'],
				)
			)
		);
	}

	/**
	 * Trash lessons the current author can edit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function trash_lessons( WP_REST_Request $request ) {
		return rest_ensure_response( Catalog::trash_lessons( (array) ( $this->body( $request )['ids'] ?? array() ) ) );
	}

	/**
	 * Replace skill connections of a lesson.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function lesson_skills( WP_REST_Request $request ) {
		$skills = Catalog::set_lesson_skills( (int) $request['id'], (array) ( $this->body( $request )['skill_ids'] ?? array() ) );
		return is_wp_error( $skills ) ? $skills : rest_ensure_response(
			array(
				'id'        => (int) $request['id'],
				'skill_ids' => $skills,
			)
		);
	}

	/**
	 * Create a draft copy of a lesson.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate_lesson( WP_REST_Request $request ) {
		$copy = Catalog::duplicate_lesson( (int) $request['id'] );
		if ( is_wp_error( $copy ) ) {
			return $copy; }
		$response = rest_ensure_response( $copy );
		$response->set_status( 201 );
		return $response;
	}
}
