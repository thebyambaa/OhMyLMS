<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Assignment delivery routes: prerequisite choices, the learner's state and submission comments.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Assignment\Comments;
use OhMyLMS\Assignment\Delivery;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under `ohmylms/v1/assignments/{id}` that complement {@see AssignmentsController} (see
 * docs/api/assignments.md). Prerequisite choices need `edit_post` on the assignment. The learner state
 * answers for the current user. A comment thread is open to the submission's learner and to people who
 * can edit the assignment; nobody else learns more than 403.
 */
class AssignmentDeliveryController extends \WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$item   = '/assignments/(?P<id>[\d]+)';
		$thread = $item . '/submissions/(?P<submission_id>[\d]+)/comments';
		$id     = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		register_rest_route(
			$this->namespace,
			$item . '/prerequisite-options',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'prerequisite_options' ),
					'permission_callback' => array( $this, 'edit_permission' ),
					'args'                => array(
						'search' => array(
							'type'              => 'string',
							'maxLength'         => 100,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 1000,
							'default' => 1,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/learner-state',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'learner_state' ),
					'permission_callback' => array( $this, 'learner_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$thread,
			array(
				'args' => array(
					'id'            => $id,
					'submission_id' => $id,
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_comments' ),
					'permission_callback' => array( $this, 'thread_permission' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 100,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_comment' ),
					'permission_callback' => array( $this, 'thread_permission' ),
					'args'                => array(
						'body' => array(
							'type'      => 'string',
							'required'  => true,
							'maxLength' => Comments::MAX_LENGTH,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$thread . '/(?P<comment_id>[\d]+)',
			array(
				'args' => array(
					'id'            => $id,
					'submission_id' => $id,
					'comment_id'    => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_comment' ),
					'permission_callback' => array( $this, 'thread_permission' ),
				),
			)
		);
	}

	/**
	 * Assignment post or 404.
	 *
	 * @param mixed $id ID.
	 * @return \WP_Post|WP_Error
	 */
	private function assignment( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type || in_array( $post->post_status, array( 'auto-draft', 'trash' ), true ) ) {
			return new WP_Error( 'ohmylms_assignment_not_found', __( 'Assignment not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * Permission error: 401 for guests, 403 otherwise.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function forbidden( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * `edit_post` on the assignment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function edit_permission( $request ) {
		$post = $this->assignment( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return current_user_can( 'edit_post', $post->ID ) ? true : $this->forbidden( 'ohmylms_assignment_cannot_edit', __( 'Sorry, you are not allowed to edit this assignment.', 'ohmylms' ) );
	}

	/**
	 * Any logged-in user, for a published assignment (editors also for drafts).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function learner_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return $this->forbidden( 'ohmylms_assignment_cannot_view', __( 'Log in to see this assignment.', 'ohmylms' ) );
		}
		$post = $this->assignment( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return 'publish' === $post->post_status || current_user_can( 'edit_post', $post->ID ) ? true : new WP_Error( 'ohmylms_assignment_not_found', __( 'Assignment not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * The submission's learner or an instructor of the assignment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function thread_permission( $request ) {
		$post = $this->assignment( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$submission = Comments::submission( $post->ID, (int) $request['submission_id'] );
		if ( ! $submission ) {
			return new WP_Error( 'ohmylms_assignment_submission_not_found', __( 'Submission not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return Comments::role( $submission, get_current_user_id() ) ? true : $this->forbidden( 'ohmylms_assignment_cannot_comment', __( 'Sorry, you are not allowed to see the comments on this submission.', 'ohmylms' ) );
	}

	/**
	 * Lessons, quizzes and assignments an assignment may require: the content of its courses, or the
	 * author's library when it is in no course yet. 20 per page, titles only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function prerequisite_options( $request ) {
		global $wpdb;
		$id      = (int) $request['id'];
		$courses = array_values( array_unique( array_filter( array_map( 'intval', array_merge( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ch.course_id FROM %i c INNER JOIN %i ch ON ch.chapter_id = c.chapter_id WHERE c.content_id = %d', $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship', $id ) ), \OhMyLMS\Learning\Placements::courses_for( $id ) ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded placement read.
		$args    = array(
			'post_type'      => array_keys( Delivery::PREREQUISITE_TYPES ),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 20,
			'paged'          => (int) $request['page'],
			's'              => (string) $request['search'],
			'post__not_in'   => array( $id ),
			'orderby'        => array(
				'title' => 'ASC',
				'ID'    => 'ASC',
			),
		);
		if ( $courses ) {
			$holders          = implode( ',', array_fill( 0, count( $courses ), '%d' ) );
			$content          = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT c.content_id FROM %i c INNER JOIN %i ch ON ch.chapter_id = c.chapter_id WHERE ch.course_id IN ({$holders})", array_merge( array( $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship' ), $courses ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders built for the course list.
			$content          = array_values( array_diff( $content, array( $id ) ) );
			$args['post__in'] = $content ? $content : array( 0 );
		} elseif ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		$query = new WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'     => (int) $post->ID,
				'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'type'   => Delivery::PREREQUISITE_TYPES[ $post->post_type ],
				'status' => (string) $post->post_status,
			);
		}
		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
				'scope' => $courses ? 'course' : 'library',
			)
		);
	}

	/**
	 * What the current user may do with the assignment: release, prerequisites and whether they can submit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function learner_state( $request ) {
		$response = rest_ensure_response( Delivery::state( (int) $request['id'], get_current_user_id() ) );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * The thread, oldest first, with the reader's role and permissions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_comments( $request ) {
		$submission = Comments::submission( (int) $request['id'], (int) $request['submission_id'] );
		$user       = get_current_user_id();
		$role       = Comments::role( $submission, $user );
		$page       = Comments::page( $submission->id, (int) $request['page'], (int) $request['per_page'] );
		$items      = array();
		foreach ( $page['items'] as $comment ) {
			$items[] = $comment + array( 'can_delete' => 'instructor' === $role || $comment['author']['id'] === $user );
		}
		$enabled  = Comments::enabled( (int) $submission->assignment_id );
		$response = rest_ensure_response(
			array(
				'items'            => $items,
				'total'            => $page['total'],
				'role'             => $role,
				'comments_enabled' => $enabled,
				'can_comment'      => $enabled,
			)
		);
		$response->header( 'X-WP-Total', (string) $page['total'] );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * Add a comment; the other side of the thread is emailed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_comment( $request ) {
		$submission = Comments::submission( (int) $request['id'], (int) $request['submission_id'] );
		$comment    = Comments::add( $submission, get_current_user_id(), $request['body'] );
		if ( is_wp_error( $comment ) ) {
			return $comment;
		}
		$response = rest_ensure_response( $comment + array( 'can_delete' => true ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Delete a comment (instructors any, others their own).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_comment( $request ) {
		$submission = Comments::submission( (int) $request['id'], (int) $request['submission_id'] );
		$deleted    = Comments::delete( $submission, (int) $request['comment_id'], get_current_user_id() );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $deleted,
			)
		);
	}
}
