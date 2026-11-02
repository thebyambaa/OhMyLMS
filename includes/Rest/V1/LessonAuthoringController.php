<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bounded lesson library and validated editing commands.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\LessonAuthoring;
use OhMyLMS\Learning\LessonObjectiveLinks;

defined( 'ABSPATH' ) || exit;

/** Restrict authoring independently of client navigation. */
class LessonAuthoringController extends \WP_REST_Controller {
	/** Register the typed library and lesson resources. */
	public function register_routes() {
		register_rest_route(
			'ohmylms/v1',
			'/authoring/lessons',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'get_items' ),
					'args'                => array(
						'page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100000,
							'default' => 1,
						),
						'search' => array(
							'type'              => 'string',
							'maxLength'         => 100,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'create_item' ),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/authoring/lessons/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'get_item' ),
				),
				array(
					'methods'             => 'PUT',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'update_item' ),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/authoring/lessons/(?P<id>\d+)/publish',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permission' ),
				'callback'            => array( $this, 'publish' ),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/authoring/lessons/(?P<id>\d+)/objectives',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'get_objectives' ),
				),
				array(
					'methods'             => 'PUT',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'update_objectives' ),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/authoring/lessons/(?P<id>\d+)/objective-options',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'permission' ),
				'callback'            => array( $this, 'objective_options' ),
				'args'                => array(
					'type'        => array(
						'type'     => 'string',
						'enum'     => array( 'syllabuses', 'objectives', 'skills' ),
						'required' => true,
					),
					'syllabus_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
					'search'      => array(
						'type'              => 'string',
						'maxLength'         => 100,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'        => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 10000,
						'default' => 1,
					),
				),
			)
		);
	}

	/**
	 * Linked skills, objective mappings and the placing courses' objectives.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function get_objectives( $request ) {
		$result = LessonObjectiveLinks::read( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * Replace skills and mappings under the read revision.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update_objectives( $request ) {
		return LessonObjectiveLinks::save( (int) $request['id'], $request->get_json_params() );
	}

	/**
	 * Picker choices for skills and syllabus objectives.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function objective_options( $request ) {
		if ( 'objectives' === $request['type'] && (int) $request['syllabus_id'] < 1 ) {
			return Catalog::error( __( 'Choose a syllabus.', 'ohmylms' ) );
		}
		return LessonObjectiveLinks::options( (string) $request['type'], (int) $request['syllabus_id'], (string) $request['search'], (int) $request['page'] );
	}

	/**
	 * Check both preview administration and the actual lesson capability.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permission( $request ) {
		$type    = get_post_type_object( OHMYLMS_LESSON_CPT );
		$allowed = current_user_can( 'manage_options' ) && $type;
		if ( $request['id'] ) {
			$allowed = $allowed && OHMYLMS_LESSON_CPT === get_post_type( (int) $request['id'] ) && current_user_can( 'edit_post', (int) $request['id'] );
		} else {
			$allowed = $allowed && current_user_can( $type->cap->create_posts );
		}
		return $allowed ? true : Catalog::error( __( 'You cannot author this lesson.', 'ohmylms' ), rest_authorization_required_code() );
	}

	/**
	 * Search stable, bounded summaries; never return lesson bodies in the library.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public function get_items( $request ) {
		$type = get_post_type_object( OHMYLMS_LESSON_CPT );
		$args = array(
			'post_type'      => OHMYLMS_LESSON_CPT,
			'post_status'    => array( 'draft', 'pending', 'private', 'publish' ),
			'posts_per_page' => 20,
			'paged'          => (int) $request['page'],
			's'              => $request['search'],
			'orderby'        => array(
				'title' => 'ASC',
				'ID'    => 'ASC',
			),
		);
		if ( ! current_user_can( $type->cap->edit_others_posts ) ) {
			$args['author'] = get_current_user_id();
		}
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = array(
					'id'     => (int) $post->ID,
					'title'  => $post->post_title,
					'status' => $post->post_status,
				);
			}
		}
		return array(
			'items' => $items,
			'pages' => (int) $query->max_num_pages,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Create or recover a named draft through the native REST writer.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function create_item( $request ) {
		return LessonAuthoring::create( $request->get_json_params() );
	}

	/**
	 * Read a coherent lesson and its concurrency token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		return LessonAuthoring::locked(
			$id,
			static function () use ( $id ) {
				$response = rest_ensure_response( LessonAuthoring::get( $id ) );
				$response->header( 'Cache-Control', 'private, no-store' );
				return $response;
			}
		);
	}

	/**
	 * Save the editable subset without changing status or placement.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update_item( $request ) {
		return LessonAuthoring::save( (int) $request['id'], $request->get_json_params() );
	}

	/**
	 * Explicitly publish the reviewed saved lesson, preserving course placement.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function publish( $request ) {
		return LessonAuthoring::save( (int) $request['id'], $request->get_json_params(), true );
	}
}
