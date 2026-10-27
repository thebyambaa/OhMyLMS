<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling course REST API endpoints.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Course\CourseHelper;
use OhMyLMS\Data\Course;
use OhMyLMS\Data\Chapter;
use OhMyLMS\DataException;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Controller for handling course REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for course-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class CourseController extends RestController {


	/**
	 * The base route for course base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'courses';

	/**
	 * Check course permission for Course controller.
	 */
	public function check_course_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check course scorm export permission for Course controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function check_course_scorm_export_permission( $request ) {
		// Allow cookie authentication with nonce for direct download links
		// WordPress REST API automatically handles cookie + nonce authentication
		// The nonce can be sent via X-WP-Nonce header or _wpnonce query parameter.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return current_user_can( 'edit_posts' );
	}


	/**
	 * Check course delete permission for Course controller.
	 */
	public function check_course_delete_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Registers REST API routes for course operations.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the course.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_course_delete_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)/status',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_status' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => array(
						'status' => array(
							'description' => __( 'The new status for the course.', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
						),
						'id'     => array(
							'description' => __( 'Unique identifier for the course.', 'ohmylms' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'check_course_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/export-scorm',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_scorm' ),
				'permission_callback' => array( $this, 'check_course_scorm_export_permission' ),
				'args'                => array(
					'course_ids'    => array(
						'required'          => true,
						'type'              => 'array',
						'description'       => __( 'Array of course IDs to export', 'ohmylms' ),
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					),
					'scorm_version' => array(
						'required'    => false,
						'type'        => 'string',
						'default'     => '1.2',
						'description' => __( 'SCORM version (1.2 or 2004)', 'ohmylms' ),
						'enum'        => array( '1.2', '2004' ),
					),
					'_wpnonce'      => array(
						'required'    => false,
						'type'        => 'string',
						'description' => __( 'WordPress nonce for authentication', 'ohmylms' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)/enroll',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'enroll_manually' ),
				'permission_callback' => array( $this, 'check_course_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)/clone',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => array( $this, 'check_course_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)/enroll/(?P<student_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_enroll_manually' ),
				'permission_callback' => array( $this, 'check_course_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)/enroll-by-email',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'enroll_by_email' ),
				'permission_callback' => array( $this, 'check_course_permission' ),
				'args'                => array(
					'email'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'first_name' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/import',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'check_course_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/import-scorm',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'import_scorm' ),
				'permission_callback' => array( $this, 'check_course_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/trash-bulk/',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'trash_bulk' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/chapters',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the course.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_chapters' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_chapters' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/update-map/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_course_map' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/settings/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_course_settings' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/settings/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course_settings' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/get-map/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course_map' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/get-map/default',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course_map_default' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/filtered-courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_filtered_courses' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/featured_image/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_featured_image' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_featured_image' ),
					'permission_callback' => array( $this, 'check_course_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>\d+)/terms',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'assign_terms_to_course' ),
				'permission_callback' => array( $this, 'check_course_permission' ),
				'args'                => array(
					'taxonomy' => array(
						'description' => __( 'The taxonomy to assign (e.g., course_tag, course_category).', 'ohmylms' ),
						'type'        => 'string',
						'required'    => true,
					),
					'terms'    => array(
						'description' => __( 'The terms to assign.', 'ohmylms' ),
						'type'        => 'array',
						'items'       => array(
							'type' => 'integer',
						),
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Check if a given request has access to read an item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 *
	 * @since 1.0.0
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( (int) $request['id'] );

		if ( $post && ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_view', __( 'Sorry, you cannot view this resource.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get collection of courses with caching.
	 *
	 * @param \WP_REST_Request $request The REST request object containing query parameters.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the courses data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_items( $request ) {

		$args = array(
			'offset'              => isset( $request['offset'] ) ? intval( $request['offset'] ) : 0,
			'order'               => isset( $request['order'] ) ? sanitize_text_field( $request['order'] ) : 'DESC',
			'orderby'             => isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'date',
			'paged'               => isset( $request['page'] ) ? intval( $request['page'] ) : 1,
			'post__in'            => isset( $request['include'] ) ? array_map( 'intval', (array) $request['include'] ) : array(),
			'post__not_in'        => isset( $request['exclude'] ) ? array_map( 'intval', (array) $request['exclude'] ) : array(),
			'posts_per_page'      => isset( $request['per_page'] ) ? intval( $request['per_page'] ) : 10,
			'name'                => isset( $request['slug'] ) ? sanitize_text_field( $request['slug'] ) : '',
			'post_parent__in'     => isset( $request['parent'] ) ? array_map( 'intval', (array) $request['parent'] ) : array(),
			'post_parent__not_in' => isset( $request['parent_exclude'] ) ? array_map( 'intval', (array) $request['parent_exclude'] ) : array(),
			's'                   => isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '',
			'post_type'           => OHMYLMS_COURSE_CPT,
			'post_status'         => isset( $request['post_status'] ) ? sanitize_text_field( $request['post_status'] ) : array( 'draft', 'publish', 'future' ),
			'meta_query'          => isset( $request['price_type'] ) && 'all' !== $request['price_type'] ? array(
				array(
					'key'     => '_price_type',
					'value'   => sanitize_text_field( $request['price_type'] ),
					'compare' => 'LIKE',
				),
			) : array(),
		);

		if ( 'any' === $args['post_status'] || ! in_array( $args['post_status'], array( 'draft', 'publish', 'future' ) ) ) {
			$args['post_status'] = array( 'draft', 'publish', 'future' );
		}

		$args['date_query'] = array();

		if ( ! empty( $request['date_filter'] ) ) {
			$filter = sanitize_text_field( $request['date_filter'] );
			$today  = current_time( 'Y-m-d' );

			switch ( $filter ) {
				case 'last_30_days':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-d', strtotime( '-30 days' ) ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;

				case 'current_month':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-01' ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;

				case 'previous_month':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-01', strtotime( 'first day of last month' ) ),
						'before'    => date( 'Y-m-t', strtotime( 'last month' ) ),
						'inclusive' => true,
					);
					break;

				case 'current_year':
					$args['date_query'][] = array(
						'after'     => date( 'Y-01-01' ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;

				case 'last_12_months':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-d', strtotime( '-12 months' ) ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;

				case 'custom':
					$start_date           = isset( $request['start_date'] ) ? sanitize_text_field( $request['start_date'] ) : '';
					$end_date             = isset( $request['end_date'] ) ? sanitize_text_field( $request['end_date'] ) : '';
					$args['date_query'][] = array(
						'after'     => $start_date,
						'before'    => $end_date,
						'inclusive' => true,
					);
					break;
			}
		}

		// If custom before/after are passed directly.
		if ( isset( $request['before'] ) || isset( $request['after'] ) ) {
			$manual_query = array();
			if ( isset( $request['before'] ) ) {
				$manual_query['before'] = sanitize_text_field( $request['before'] );
			}
			if ( isset( $request['after'] ) ) {
				$manual_query['after'] = sanitize_text_field( $request['after'] );
			}
			$manual_query['inclusive'] = true;

			$args['date_query'][] = $manual_query;
		}

		if ( isset( $request['filter'] ) && is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $args['filter'] );
		}

		if ( isset( $request['orderby'] ) && in_array( $request['orderby'], array( 'price' ) ) ) {
			if ( 'price' === $request['orderby'] ) {
				$args['meta_key'] = '_price'; // actual meta key in DB.
				$args['orderby']  = 'meta_value_num'; // sort numerically.
			}
		}

		// Filter by curriculum item (courses linked beneath it count too) and by course bundle.
		// category_id and tag_id are the former category and tag parameters: they now mean a curriculum
		// item ID and a course bundle ID, so existing list filters keep working.
		$item_filter   = array_filter( array( $request['curriculum_id'] ?? null, $request['category_id'] ?? null ), 'is_numeric' );
		$bundle_filter = array_filter( array( $request['bundle_id'] ?? null, $request['tag_id'] ?? null ), 'is_numeric' );
		if ( $item_filter || $bundle_filter ) {
			$args = \OhMyLMS\Curriculum\Placement::narrow_query( $args, array_map( 'intval', $item_filter ), array_map( 'intval', $bundle_filter ) );
		}

		$args       = apply_filters( 'ohmylms_rest_ohmylms_course_query', $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = array();

		foreach ( $query_result as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}
			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}

		$page        = (int) $query_args['paged'];
		$total_posts = $posts_query->found_posts;

		if ( 1 > $total_posts && 1 < $page ) {
			unset( $query_args['paged'] );
			$count_query = new WP_Query();
			$count_query->query( $query_args );
			$total_posts = $count_query->found_posts;
		}

		$max_pages            = ceil( $total_posts / (int) $query_args['posts_per_page'] );
		$total_filtered_posts = $total_posts;

		$args = array(
			'post_type'      => 'ohmylms-course',
			'post_status'    => array( 'publish', 'draft', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids', // Only retrieve IDs to improve performance.
		);

		$query       = new WP_Query( $args );
		$total_posts = $query->found_posts;

		if ( isset( $request['orderby'] ) && in_array( $request['orderby'], array( 'enrollment' ) ) ) {
			usort(
				$posts,
				function ( $a, $b ) use ( $request ) {
					if ( strtoupper( $request['order'] ) === 'DESC' ) {
						return $a['total_enrollment'] <=> $b['total_enrollment'];
					} else {
						return $b['total_enrollment'] <=> $a['total_enrollment'];
					}
				}
			);
		}

		$response = rest_ensure_response( $posts );
		$response->header( 'X-WP-Total', (int) $total_posts );
		$response->header( 'X-WP-TotalCourses', (int) $total_posts );
		$response->header( 'X-WP-NoOfFilteredCourses', (int) $total_filtered_posts );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );
		return $response;
	}


	/**
	 * Import a course from a JSON file.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The result of the import operation or an error.
	 */
	public function import( \WP_REST_Request $request ) {
		if ( ! ohmylms_is_pro() ) {
			return new \WP_Error( 'pro_feature', __( 'This is pro feature.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$file = $request->get_file_params();
		if ( ! isset( $file['file'] ) || empty( $file['file']['tmp_name'] ) ) {
			return new \WP_Error( 'file_missing', __( 'No file uploaded.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Instantiate the CourseImporter class.
		$importer = new \OhMyLMS\Importers\CourseImporter( $file['file'] );
		$result   = $importer->import_course_from_json();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Return success response with the imported course ID.
		return new \WP_REST_Response(
			array(
				'success'   => true,
				'course_id' => $result,
				'message'   => __( 'Courses imported successfully.', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Import a course from a SCORM package.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The result of the import operation or an error.
	 */
	public function import_scorm( \WP_REST_Request $request ) {
		$file = $request->get_file_params();
		if ( ! isset( $file['file'] ) || empty( $file['file']['tmp_name'] ) ) {
			return new \WP_Error( 'file_missing', __( 'No file uploaded.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			// Instantiate the ScormImporter class.
			$importer = new \OhMyLMS\Importers\ScormImporter( $file['file'] );
			$result   = $importer->import_scorm_package();

			// Return success response with detailed report.
			return new \WP_REST_Response(
				array(
					'success' => $result['success'],
					'data'    => array(
						'course_id'           => isset( $result['course_id'] ) ? $result['course_id'] : null,
						'chapters_created'    => isset( $result['chapters_created'] ) ? $result['chapters_created'] : 0,
						'lessons_created'     => isset( $result['lessons_created'] ) ? $result['lessons_created'] : 0,
						'quizzes_created'     => isset( $result['quizzes_created'] ) ? $result['quizzes_created'] : 0,
						'assignments_created' => isset( $result['assignments_created'] ) ? $result['assignments_created'] : 0,
						'media_imported'      => isset( $result['media_imported'] ) ? $result['media_imported'] : 0,
					),
					'report'  => array(
						'skipped_items' => $result['skipped_items'],
						'warnings'      => $result['warnings'],
						'errors'        => $result['errors'],
					),
					'message' => __( 'SCORM package imported successfully.', 'ohmylms' ),
				),
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'import_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}


	/**
	 * Export a course by ID.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The exported course data or an error.
	 */
	public function export( \WP_REST_Request $request ) {
		$course_ids = $request->get_param( 'course_ids' );
		if ( ! ohmylms_is_pro() ) {
			return new \WP_Error( 'pro_feature', __( 'This is pro feature.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $course_ids ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			$exporter = new \OhMyLMS\Exporters\CourseExporter( $course_ids );
			$exporter->export_courses_as_json();
		} catch ( \Exception $e ) {
			return new \WP_Error( 'export_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}


	/**
	 * Export courses as SCORM package.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The SCORM package or an error.
	 */
	public function export_scorm( \WP_REST_Request $request ) {
		$course_ids    = $request->get_param( 'course_ids' );
		$scorm_version = $request->get_param( 'scorm_version' );

		if ( ! ohmylms_is_pro() ) {
			return new \WP_Error( 'pro_feature', __( 'SCORM export is a pro feature.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $course_ids ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID. Please select at least one course.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Validate course IDs.
		foreach ( $course_ids as $course_id ) {
			$course = get_post( $course_id );
			if ( ! $course || OHMYLMS_COURSE_CPT !== $course->post_type ) {
				return new \WP_Error(
					'invalid_course',
					sprintf( /* translators: %d: number. */
					__( 'Invalid course ID: %d', 'ohmylms' ), $course_id ),
					array( 'status' => 400 )
				);
			}
		}

		// Default to SCORM 1.2 if not specified.
		if ( empty( $scorm_version ) || ! in_array( $scorm_version, array( '1.2', '2004' ) ) ) {
			$scorm_version = '1.2';
		}

		try {
			// Increase memory limit for large courses.
			if ( function_exists( 'ini_set' ) ) {
				wp_raise_memory_limit( 'admin' );
			}

			$exporter = new \OhMyLMS\Exporters\ScormExporter( $course_ids, $scorm_version );
			$exporter->export_courses_as_scorm();

			// The export method handles the download and exits, so this won't be reached
			// But we keep it for consistency.
			exit;

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'scorm_export_error',
				sprintf( /* translators: %s: text value. */
				__( 'SCORM export failed: %s', 'ohmylms' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}


	/**
	 * Enroll student manually to a course.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The exported course data or an error.
	 */
	public function enroll_manually( \WP_REST_Request $request ) {
		$course_id  = isset( $request['id'] ) ? (int) $request['id'] : '';
		$student_id = isset( $request['student_id'] ) ? (int) $request['student_id'] : '';

		if ( empty( $course_id ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $student_id ) ) {
			return new \WP_Error( 'invalid_student_id', __( 'Invalid student ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
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
			// update user meta.
			update_user_meta( $student_id, '_is_ohmylms_student', 'yes' );
			update_user_meta( $student_id, '_is_ohmylms_user', 'yes' );
			if ( $student && ! $student->maybe_enrolled( $course_id ) ) {
				// Check if the record exists.
				$existing_record = $wpdb->get_var(
					$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $enrollment_table, $student_id, $course_id )
				);

				if ( $existing_record ) {
					$response = array(
						'success' => false,
						'message' => __( 'Already enrolled.', 'ohmylms' ),
					);
				} else {
					$wpdb->insert(
						$enrollment_table,
						$enrollment_data
					);

					// Trigger action hook for manual enrollment to enable community access and other integrations.
					do_action( 'ohmylms_manual_student_enrollment', $student_id, $course_id );

					$response = array(
						'success' => true,
						'message' => __( 'Enrolled successfully', 'ohmylms' ),
					);
				}
				return new \WP_REST_Response( $response, 200 );
			} else {
				$response = array(
					'success' => true,
					'message' => __( 'Already enrolled.', 'ohmylms' ),
				);
				return new \WP_REST_Response( $response, 200 );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'enroll_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}


	/**
	 * Delete a student's enrollment from a course.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error Success message or an error.
	 */
	public function delete_enroll_manually( \WP_REST_Request $request ) {
		$course_id  = isset( $request['id'] ) ? (int) $request['id'] : '';
		$student_id = isset( $request['student_id'] ) ? (int) $request['student_id'] : '';

		if ( empty( $course_id ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $student_id ) ) {
			return new \WP_Error( 'invalid_student_id', __( 'Invalid student ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			global $wpdb;
			$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';

			// Check if the enrollment exists.
			$existing_record = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $enrollment_table, $student_id, $course_id )
			);

			if ( ! $existing_record ) {
				return new \WP_Error(
					'enrollment_not_found',
					__( 'The enrollment record does not exist.', 'ohmylms' ),
					array( 'status' => 404 )
				);
			}

			// Delete the enrollment.
			$deleted = $wpdb->delete(
				$enrollment_table,
				array(
					'user_id'   => $student_id,
					'course_id' => $course_id,
				),
				array(
					'%d',
					'%d',
				)
			);

			if ( $deleted ) {
				$response = array(
					'success' => true,
					'message' => __( 'Student enrollment deleted successfully.', 'ohmylms' ),
				);
			} else {
				$response = array(
					'success' => false,
					'message' => __( 'Failed to delete student enrollment.', 'ohmylms' ),
				);
			}

			return new \WP_REST_Response( $response, 200 );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'delete_enroll_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}



	/**
	 * Generate a unique alphanumeric-only username from a name or email fallback.
	 *
	 * @param string $first_name First name.
	 * @param string $last_name Last name.
	 * @param string $email Email.
	 * @return string
	 */
	private function generate_username( $first_name, $last_name, $email ) {
		$first_name = trim( $first_name );
		$last_name  = trim( $last_name );

		if ( $first_name || $last_name ) {
			$base = strtolower( $first_name . $last_name );
		} else {
			$base = strtolower( explode( '@', $email )[0] );
		}

		$base = preg_replace( '/[^a-z0-9]/', '', $base );

		if ( empty( $base ) ) {
			$base = 'student';
		}

		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			++$suffix;
		}

		return $username;
	}


	/**
	 * Enroll a student by email. Creates a new account if no user with that email exists.
	 * Fires ohmylms_manual_enrollment_by_email after enrollment so emails can be sent.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function enroll_by_email( \WP_REST_Request $request ) {
		$course_id  = isset( $request['id'] ) ? (int) $request['id'] : 0;
		$email      = isset( $request['email'] ) ? sanitize_email( $request['email'] ) : '';
		$first_name = isset( $request['first_name'] ) ? sanitize_text_field( $request['first_name'] ) : '';
		$last_name  = isset( $request['last_name'] ) ? sanitize_text_field( $request['last_name'] ) : '';

		if ( empty( $course_id ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$is_new_user    = false;
		$plain_password = '';
		$username       = '';

		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			$student_id = $existing_user->ID;
			$username   = $existing_user->user_login;
		} else {
			$username       = $this->generate_username( $first_name, $last_name, $email );
			$plain_password = wp_generate_password( 12, false );

			$user_args = array();
			if ( $first_name ) {
				$user_args['first_name'] = $first_name;
			}
			if ( $last_name ) {
				$user_args['last_name']    = $last_name;
				$user_args['display_name'] = trim( $first_name . ' ' . $last_name );
			}

			$student_id = ohmylms_create_new_student( $email, $username, $plain_password, $user_args );

			if ( is_wp_error( $student_id ) ) {
				return new \WP_Error( 'user_creation_failed', $student_id->get_error_message(), array( 'status' => 400 ) );
			}

			$is_new_user = true;
		}

		try {
			global $wpdb;
			$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';

			update_user_meta( $student_id, '_is_ohmylms_student', 'yes' );
			update_user_meta( $student_id, '_is_ohmylms_user', 'yes' );

			$existing_status = $wpdb->get_var(
				$wpdb->prepare( 'SELECT status FROM %i WHERE user_id = %d AND course_id = %d', $enrollment_table, $student_id, $course_id )
			);

			if ( 'enrolled' === $existing_status ) {
				return new \WP_REST_Response(
					array(
						'success'     => false,
						'message'     => __( 'Student is already enrolled in this course.', 'ohmylms' ),
						'is_new_user' => $is_new_user,
					),
					200
				);
			}

			if ( $existing_status ) {
				$wpdb->update(
					$enrollment_table,
					array(
						'status'     => 'enrolled',
						'start_date' => current_time( 'mysql' ),
					),
					array(
						'user_id'   => $student_id,
						'course_id' => $course_id,
					),
					array( '%s', '%s' ),
					array( '%d', '%d' )
				);
			} else {
				$wpdb->insert(
					$enrollment_table,
					array(
						'course_id'  => $course_id,
						'user_id'    => $student_id,
						'status'     => 'enrolled',
						'progress'   => 'running',
						'start_date' => current_time( 'mysql' ),
					)
				);
			}

			do_action( 'ohmylms_manual_student_enrollment', $student_id, $course_id );
			do_action( 'ohmylms_manual_enrollment_by_email', $student_id, $course_id, $username, $plain_password, $is_new_user );

			return new \WP_REST_Response(
				array(
					'success'     => true,
					'message'     => $is_new_user
						? __( 'New student account created and enrolled successfully.', 'ohmylms' )
						: __( 'Student enrolled successfully.', 'ohmylms' ),
					'is_new_user' => $is_new_user,
					'student_id'  => $student_id,
					'username'    => $username,
				),
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'enroll_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}


	/**
	 * Create a single course
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			// Translators: %s is replaced with object name.
			return new WP_Error( 'ohmylms_rest_course_exists', sprintf( __( 'Cannot create existing %s.', 'ohmylms' ), 'course' ), array( 'status' => 400 ) );
		}

		try {
			/**
			 * Fires before a course is created via the REST API.
			 *
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_before_create_course', $request );

			$course_id = $this->save_course( $request );
			$post      = get_post( $course_id );

			/**
			 * Fires after a course is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the course.
			 * @param int              $course_id The course ID.
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_course', $post, $course_id, $request );

			/**
			 * Fires after a course is created via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the course.
			 * @param int              $course_id The course ID.
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_after_create_course', $post, $course_id, $request );

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );
			$response = rest_ensure_response( $response );
			$response->set_status( 201 );
			return $response;
		} catch ( DataException $e ) {
			return new WP_Error( 400, $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}


	/**
	 * Retrieves a single course by ID.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the course ID.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the course data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_COURSE_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_course_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$data = $this->prepare_item_for_response( $post, $request );

		$response = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;
	}


	/**
	 * Updates a single course.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the course ID and data.
	 * @return WP_Error|\WP_REST_Response|\WP_HTTP_Response The response object containing the updated course data or an error.
	 *
	 * @since 1.0.0
	 */
	public function update_item( $request ) {
		$post_id = (int) $request['id'];
		if ( empty( $post_id ) || get_post_type( $post_id ) !== OHMYLMS_COURSE_CPT ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			/**
			 * Fires before a course is updated via the REST API.
			 *
			 * @param int              $post_id The course ID.
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_before_update_course', $post_id, $request );

			$course_id = $this->save_course( $request );
			$post      = get_post( $course_id );

			/**
			 * Fires after a course is updated via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the course.
			 * @param int              $course_id The course ID.
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_update_course', $post, $course_id, $request );

			/**
			 * Fires after a course is updated via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the course.
			 * @param int              $course_id The course ID.
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_after_update_course', $post, $course_id, $request );

			$this->update_course_status_and_password( $course_id, $request );

			$this->update_additional_fields_for_object( $post, $request );
			$this->update_post_meta_fields( $post, $request );
			$course = ohmylms_get_course( $course_id );
			if ( $course && 'cohort-based' === $course->get_type() ) {
				$this->save_cohort_settings( $course_id, $request );
			}
			$terms_update = $this->handle_terms( $post->ID, $request );
			if ( is_wp_error( $terms_update ) ) {
				return $terms_update;
			}

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );
			return rest_ensure_response( $response );
		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}


	/**
	 * Delete course
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		// Get course id.
		$course_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

		// Check the course id exist or not.
		if ( ! $course_id ) {
			return new WP_Error( 'ohmylms_rest_course_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing course by course id.
		$course = ohmylms_get_course( $course_id );

		// Check the course exist or not.
		if ( ! ( $course instanceof Course ) ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		/**
		 * Fires before a course is deleted via the REST API.
		 *
		 * @param int              $course_id The course ID.
		 * @param Course           $course    The course object.
		 * @param \WP_REST_Request $request   The request object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_before_delete_course', $course_id, $course, $request );

		// Delete the course.
		$course->delete();

		/**
		 * Fires after a course is deleted via the REST API.
		 *
		 * @param int              $course_id The course ID.
		 * @param \WP_REST_Request $request   The request object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_course', $course_id, $request );

		/**
		 * Fires after a course is deleted via the REST API.
		 *
		 * @param int $course_id The course ID.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_course', $course_id );

		/**
		 * Fires after a course is deleted via the REST API.
		 *
		 * @param int              $course_id The course ID.
		 * @param Course           $course    The course object.
		 * @param \WP_REST_Request $request   The request object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_after_delete_course', $course_id, $course, $request );

		$response = array(
			'status'  => 'success',
			'message' => __( 'Course deleted successfully', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}


	/**
	 * Duplicate a course
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function duplicate_item( $request ) {
		if ( ! ohmylms_is_pro() ) {
			return new \WP_Error( 'pro_feature', __( 'This is pro feature.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get course id.
		$course_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

		// Check the course id exist or not.
		if ( ! $course_id ) {
			return new WP_Error( 'ohmylms_rest_course_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		// Get existing course by course id.
		$course = ohmylms_get_course( $course_id );
		// Check the course exist or not.
		if ( ! ( $course instanceof Course ) ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$duplicate_course_obj = new \OhMyLMS\Duplicate\Course( $course_id );
		$new_course_id        = \OhMyLMS\Learning\UnitSnapshots::authoring( array( $duplicate_course_obj, 'duplicate' ) );
		$response             = array(
			'status'  => 'success',
			'message' => __( 'Course duplicated successfully', 'ohmylms' ),
			'data'    => array(
				'id' => $new_course_id,
			),
		);
		return rest_ensure_response( $response );
	}


	/**
	 * Retrieves the chapters of a course.
	 *
	 * This method fetches the chapters associated with a specific course ID.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the course ID.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the chapters data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_chapters( $request ) {

		$course_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

		// Check the course id exist or not.
		if ( ! $course_id ) {
			return new WP_Error( 'ohmylms_rest_course_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing course by course id.
		$course = ohmylms_get_course( $course_id );

		// Check the course exist or not.
		if ( ! ( $course instanceof Course ) ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$chapters = $course->get_chapters();

		$response = array(
			'status'  => 'success',
			'message' => __( 'Chapters fetched successfully', 'ohmylms' ),
			'data'    => $chapters,
		);

		return rest_ensure_response( $response );
	}


	/**
	 * Updates the chapters of a course.
	 *
	 * This method handles the updating of chapters for a specific course based on the provided request data.
	 *
	 * @param WP_REST_Request $request The REST request object containing the course ID and chapter data.
	 * @return WP_REST_Response The response object containing the result of the update operation.
	 *
	 * @since 1.0.0
	 */
	public function update_chapters( $request ) {
		$course_id = (int) $request['id'];

		// Check the course id exist or not.
		if ( ! $course_id ) {
			return new WP_Error( 'ohmylms_rest_course_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing course by course id.
		$course = ohmylms_get_course( $course_id );

		// Check the course exist or not.
		if ( ! ( $course instanceof Course ) ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$chapters    = array();
		$chapter_ids = array();
		foreach ( $request->get_json_params() as $chapter ) {

			$chapter_id = isset( $chapter['id'] ) ? (int) $chapter['id'] : 0;
			if ( ! $chapter_id ) {
				$chapter_obj = new Chapter();
			} else {
				$chapter_obj = ohmylms_get_chapter( $chapter_id );
			}
			$chapter_obj->set_name( $chapter['name'] );
			$chapter_obj->set_description( $chapter['description'] );
			$chapter_obj->save();
			$chapter_obj->set_parent_id( $course_id );
			$chapters[]    = $chapter;
			$chapter_ids[] = $chapter_obj->get_id();
		}

		$course->set_chapters( $chapters );
		$single_chapter = count( $chapters ) === 1 ? 'chapter' : 'chapters';
		$response       = array(
			'status'  => 'success',
			'message' => __( 'Chapters updated successfully', 'ohmylms' ),
		);
		if ( 'chapter' === $single_chapter ) {
			$response['chapter'] = $chapters[0];
		}
		return rest_ensure_response( $response );
	}

	/**
	 * Delete bulk courses.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function trash_bulk( $request ) {
		$course_ids = $request->get_param( 'course_ids' );

		/**
		 * Fires before bulk courses are deleted via the REST API.
		 *
		 * @param array            $course_ids Array of course IDs to be deleted.
		 * @param \WP_REST_Request $request    The request object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_before_bulk_delete_courses', $course_ids, $request );

		if ( is_array( $course_ids ) ) {
			foreach ( $course_ids as $course_id ) {
				if ( get_post_type( $course_id ) !== 'ohmylms-course' ) {
					return new \WP_REST_Response( array( 'message' => 'Invalid course ID.' ), 400 );
				}

				/**
				 * Fires before a single course is deleted in bulk operation.
				 *
				 * @param int $course_id The course ID being deleted.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ohmylms_rest_before_bulk_delete_single_course', $course_id );

				wp_trash_post( $course_id );

				/**
				 * Fires after a single course is deleted in bulk operation.
				 *
				 * @param int $course_id The course ID that was deleted.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ohmylms_rest_delete_course', $course_id );
			}

			/**
			 * Fires after bulk courses are deleted via the REST API.
			 *
			 * @param array            $course_ids Array of course IDs that were deleted.
			 * @param \WP_REST_Request $request    The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_after_bulk_delete_courses', $course_ids, $request );

			return new \WP_REST_Response( array( 'message' => 'Deleted Successfully' ), 200 );
		}
		return new \WP_REST_Response( array( 'message' => 'Failed to trash the course.' ), 500 );
	}


	/**
	 * Prepares the query arguments for fetching items.
	 *
	 * This function filters and constructs the query arguments based on the allowed query variables.
	 * It ensures that only valid query variables are included in the final query arguments.
	 *
	 * @param array                $prepared_args The prepared arguments for the query.
	 * @param WP_REST_Request|null $request The REST request object.
	 * .
	 * @return array The filtered and prepared query arguments.
	 * @since 1.0.0
	 */
	protected function prepare_items_query( $prepared_args = array(), $request = null ) {

		$valid_vars = array_flip( $this->get_allowed_query_vars() );
		$query_args = array();
		foreach ( $valid_vars as $var => $index ) {
			if ( isset( $prepared_args[ $var ] ) ) {
				/**
				 * Filter the query_vars used in `get_items` for the constructed query.
				 *
				 * The dynamic portion of the hook name, $var, refers to the query_var key.
				 *
				 * @param mixed $prepared_args[ $var ] The query_var value.
				 */
				$query_args[ $var ] = apply_filters( "ohmylms_rest_query_var-{$var}", $prepared_args[ $var ] );
			}
		}

		$query_args['ignore_sticky_posts'] = true;

		if ( 'include' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'post__in';
		} elseif ( 'id' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'ID'; // ID must be capitalized.
		} elseif ( 'slug' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'name';
		}

		return $query_args;
	}


	/**
	 * Get the allowed query variables for the REST API.
	 *
	 * This method retrieves the list of query variables that are allowed to be used
	 * in REST API requests for courses. It merges the public and private query variables
	 * and applies filters to allow customization.
	 *
	 * @return array The array of allowed query variables.
	 *
	 * @since 1.0.0
	 */
	protected function get_allowed_query_vars() {
		global $wp;

		/**
		 * Filter the publicly allowed query vars.
		 *
		 * Allows adjusting of the default query vars that are made public.
		 *
		 * @param array  Array of allowed WP_Query query vars.
		 */
		$valid_vars = apply_filters( 'query_vars', $wp->public_query_vars );

		$post_type_obj = get_post_type_object( OHMYLMS_COURSE_CPT );
		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			$valid_vars = array_merge( $valid_vars, $wp->private_query_vars );
		}
		$rest_valid = array(
			'date_query',
			'ignore_sticky_posts',
			'offset',
			'post__in',
			'post__not_in',
			'post_parent',
			'post_parent__in',
			'post_parent__not_in',
			'posts_per_page',
			'meta_query',
			'tax_query',
			'meta_key',
			'meta_value',
			'meta_compare',
			'meta_value_num',
			'orderby',
		);
		$valid_vars = array_merge( $valid_vars, $rest_valid );

		/**
		 * Filter the valid query variables for the REST API.
		 *
		 * This filter allows developers to modify the list of valid query variables
		 * that can be used in REST API requests for courses.
		 *
		 * @param array $valid_vars The array of valid query variables.
		 */
		$valid_vars = apply_filters( 'ohmylms_rest_query_vars', $valid_vars );

		return $valid_vars;
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
		$course = $this->prepare_item_for_database( $request );

		if ( $course->get_slug() === '' ) {
			$course->set_slug( 'untitled' );
		}

		/**
		 * Fires before a course is saved to the database.
		 *
		 * @param Course           $course  The course object.
		 * @param \WP_REST_Request $request The request object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_before_save_course', $course, $request );

		// Save the course.
		$course_id = $course->save();

		/**
		 * Fires after a course is saved to the database.
		 *
		 * @param int              $course_id The course ID.
		 * @param Course           $course    The course object.
		 * @param \WP_REST_Request $request   The request object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_after_save_course', $course_id, $course, $request );

		return $course_id;
	}


	/**
	 * Update post meta fields for a course.
	 *
	 * This method updates the meta fields for a given course post based on the provided request data.
	 *
	 * @param \WP_Post         $post The post object representing the course.
	 * @param \WP_REST_Request $request The REST request object containing the meta data.
	 * @return bool True on success, false on failure.
	 *
	 * @throws DataException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function update_post_meta_fields( $post, $request ) {
		$course = ohmylms_get_course( $post );
		if ( isset( $request['image_id'] ) && intval( $request['image_id'] ) > 0 ) {
			$course = $this->set_course_cover_image( $course, $request['image_id'] );
		}

		if ( isset( $request['thumbnail_id'] ) && intval( $request['thumbnail_id'] ) > 0 ) {
			$course = $this->set_course_cover_image( $course, $request['thumbnail_id'] );
		}

		// Save course meta fields.
		$course = $this->set_course_meta( $course, $request );

		// Set tracking meta fields if this is a new course creation.
		if ( isset( $request['creation_source'] ) ) {
			update_post_meta( $course->get_id(), '_ohmylms_creation_source', sanitize_text_field( $request['creation_source'] ) );
		}
		if ( isset( $request['course_type'] ) ) {
			update_post_meta( $course->get_id(), '_ohmylms_course_type', sanitize_text_field( $request['course_type'] ) );
		}

		// Save the course data.
		$course->save();
		do_action( 'ohmylms_rest_after_save_course_meta', $course, $request );
		return true;
	}


	/**
	 * Set product meta data for a course.
	 *
	 * @param Course          $course The course object.
	 * @param WP_REST_Request $request The REST request object containing the meta data.
	 * @return Course The updated course object.
	 *
	 * @since 1.0.0
	 */
	protected function set_course_meta( $course, $request ) {
		if ( isset( $request['price_type'] ) ) {
			$course->set_price_type( $request['price_type'] );
		}

		if ( isset( $request['regular_price'] ) ) {
			$course->set_regular_price( $request['regular_price'] );
		}

		if ( isset( $request['purchase_point'] ) ) {
			if ( method_exists( $course, 'set_purchase_point' ) ) {
				$course->set_purchase_point( $request['purchase_point'] );
			}
		}

		if ( isset( $request['sale_price'] ) ) {
			$course->set_sale_price( $request['sale_price'] );
		}

		if ( isset( $request['level'] ) ) {
			$course->set_level( trim( $request['level'] ) );
		}

		if ( isset( $request['availability'] ) ) {
			$course->set_availability( $request['availability'] );
		}

		if ( isset( $request['available_date'] ) ) {
			$course->set_available_date( $request['available_date'] );
		}

		if ( isset( $request['access_type'] ) ) {
			$course->set_access_type( $request['access_type'] );
		}

		if ( isset( $request['has_capacity'] ) ) {
			$course->set_has_capacity( $request['has_capacity'] );
		}

		if ( isset( $request['capacity'] ) ) {
			$course->set_capacity( $request['capacity'] );
		}
		if ( isset( $request['password_protected'] ) ) {
			$course->set_password_protected( $request['password_protected'] );
		}
		if ( isset( $request['duration'] ) ) {
			$course->set_duration( $request['duration'] );
		}
		if ( isset( $request['enable_reviews'] ) ) {
			$course->set_enable_reviews( $request['enable_reviews'] );
		}
		if ( isset( $request['benefit_description'] ) ) {
			$course->set_benefit_description( $request['benefit_description'] );
		}
		if ( isset( $request['benefiter_description'] ) ) {
			$course->set_benefiter_description( $request['benefiter_description'] );
		}
		if ( isset( $request['requirement'] ) ) {
			$course->set_requirement( $request['requirement'] );
		}
		if ( isset( $request['sale_price_dates_from'] ) && ! empty( $request['sale_price_dates_from'] ) ) {
			$course->set_sale_price_dates_from( $request['sale_price_dates_from']['date'] );
		}
		if ( isset( $request['sale_price_dates_to'] ) && ! empty( $request['sale_price_dates_to'] ) ) {
			$course->set_sale_price_dates_to( $request['sale_price_dates_to']['date'] );
		}
		if ( isset( $request['download_resource'] ) ) {
			$course->set_download_resource( $request['download_resource'] );
		}

		if ( isset( $request['leaderboard_disabled'] ) ) {
			if ( method_exists( $course, 'set_leaderboard_disabled' ) ) {
				$course->set_leaderboard_disabled( $request['leaderboard_disabled'] );
			}
		}

		if ( isset( $request['point_disabled'] ) ) {
			if ( method_exists( $course, 'set_point_disabled' ) ) {
				$course->set_point_disabled( $request['point_disabled'] );
			}
		}
		if ( isset( $request['reward_disabled'] ) ) {
			if ( method_exists( $course, 'set_reward_disabled' ) ) {
				$course->set_reward_disabled( $request['reward_disabled'] );
			}
		}

		if ( isset( $request['sequential_mode'] ) && apply_filters( 'ohmylms_can_save_sequential_mode', false ) ) {
			if ( method_exists( $course, 'set_sequential_mode' ) ) {
				$course->set_sequential_mode( $request['sequential_mode'] );
			}
		}

		if ( isset( $request['certificate_id'] ) ) {
			$course->set_certificate( $request['certificate_id'] );
			// Track certificate enabled status.
			$has_certificate = ! empty( $request['certificate_id'] ) ? 'yes' : 'no';
			update_post_meta( $course->get_id(), '_ohmylms_certificate_enabled', $has_certificate );
		}
		if ( isset( $request['has_community'] ) ) {
			$course->set_has_community( $request['has_community'] );
		}

		if ( isset( $request['space_title'] ) ) {
			$course->set_space_title( $request['space_title'] );
		}

		if ( isset( $request['space_description'] ) ) {
			$course->set_space_description( $request['space_description'] );
		}
		if ( isset( $request['funnel_steps'] ) && is_array( $request['funnel_steps'] ) ) {
			if ( method_exists( $course, 'set_funnel_steps' ) ) {
				$course->set_funnel_steps( $request['funnel_steps'] );
				// Track automation enabled status.
				$has_automation = ! empty( $request['funnel_steps'] ) ? 'yes' : 'no';
				update_post_meta( $course->get_id(), '_ohmylms_automation_enabled', $has_automation );
			}
		}

		return $course;
	}


	/**
	 * Set the cover image for a course.
	 *
	 * @param Course $course The course object.
	 * @param int    $attachment_id The attachment ID of the image.
	 * @return Course The updated course object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_course_cover_image( $course, $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			// Translators: %s is replaced with attachment_id.
			throw new DataException( 'ohmylms_course_invalid_image_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$course->set_thumbnail_id( $attachment_id );

		return $course;
	}


	/**
	 * Update course map
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function update_course_map( $request ) {
		$course_id = (int) $request['id'];
		$map       = $request->get_json_params();

		$response = CourseHelper::update_course_map( $course_id, $map );
		return rest_ensure_response( $response );
	}

	/**
	 * Get course map
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_course_map( $request ) {
		$course_id = (int) $request['id'];
		$response  = CourseHelper::get_course_map( $course_id );
		return rest_ensure_response( $response );
	}

	/**
	 * Get default course map
	 *
	 * @param mixed $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_course_map_default( $request ) {
		$course_id = (int) $request['id'];
		$response  = CourseHelper::get_course_map( $course_id );
		return rest_ensure_response( $response );
	}

	/**
	 * Fetch course settings
	 *
	 * @param mixed $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_course_settings( $request ) {
		$course_id = $request['id'];
		$course    = get_post( $course_id );

		if ( ! $course || 'ohmylms-course' !== $course->post_type ) {
			$response = array(
				'status'  => 'error',
				'message' => __( 'Course not found.', 'ohmylms' ),
			);

			return rest_ensure_response( $response );
		}

		$settings_data = get_post_meta( $course_id, 'ohmylms_course_settings', true );

		$response = array(
			'status'  => 'success',
			'message' => __( 'Course settings fetched', 'ohmylms' ),
			'data'    => $settings_data,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Save course settings
	 *
	 * @param mixed $request REST request.
	 * @return WP_Error|WP_REST_Response|\WP_HTTP_Response
	 * @since 1.0.0
	 */
	public function save_course_settings( $request ) {
		$course_id     = (int) $request['id'];
		$settings_data = $request->get_json_params();

		$response = CourseHelper::save_course_settings( $course_id, $settings_data );
		return rest_ensure_response( $response );
	}

	/**
	 * Get filtered courses for Course controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_filtered_courses( $request ) {
		$args = array(
			'post_type'    => 'ohmylms-course',
			's'            => isset( $request['search'] ) ? $request['search'] : '',
			'post__not_in' => isset( $request['exclude'] ) ? explode( ',', $request['exclude'] ) : array(),
		);

		$query = new WP_Query( $args );

		$courses = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$courses[] = array(
					'id'        => get_the_ID(),
					'title'     => get_the_title(),
					'thumbnail' => get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' ),
				);
			}
		}

		wp_reset_postdata();

		return rest_ensure_response( $courses );
	}


	/**
	 * Save featured image for a course.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function save_featured_image( \WP_REST_Request $request ) {
		$course_id     = (int) $request['id'];
		$attachment_id = (int) $request->get_param( 'attachment_id' );
		$media_type    = $request->get_param( 'type' );
		if ( ! $course_id || ! $attachment_id ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid course ID or attachment ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( 'video' === $media_type ) {
			$course = ohmylms_get_course( $course_id );
			$course->set_video_id( $attachment_id );
			$course->save();
			return rest_ensure_response(
				array(
					'status'  => 'success',
					'message' => __( 'Cover Video set successfully.', 'ohmylms' ),
				)
			);
		}

		$result = set_post_thumbnail( $course_id, $attachment_id );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( 'failed_to_set_thumbnail', __( 'Failed to set featured image.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Cover image set successfully.', 'ohmylms' ),
			)
		);
	}


	/**
	 * Remove featured image for a course.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function remove_featured_image( \WP_REST_Request $request ) {
		$course_id = (int) $request['id'];
		$type      = $request->get_param( 'type' );
		$course    = ohmylms_get_course( $course_id );

		if ( ! $course_id ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( 'video' === $type ) {
			$course->set_video_id( 0 );
			$course->save();
			$message = __( 'Cover Video removed successfully.', 'ohmylms' );
		} else {
			$course->set_thumbnail_id( '' );
			$course->save();
			$result = delete_post_thumbnail( $course_id );
			if ( is_wp_error( $result ) ) {
				return new \WP_Error( 'failed_to_remove_thumbnail', __( 'Failed to remove featured image.', 'ohmylms' ), array( 'status' => 500 ) );
			}
			$message = __( 'Cover image removed successfully.', 'ohmylms' );
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => $message,
			)
		);
	}


	/**
	 * Get course data.
	 *
	 * @param Course $course Course.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	protected function get_course_data( $course ) {
		// Get WP timezone as DateTimeZone object.
		$wp_timezone = wp_timezone();
		// Get post datetime in WP timezone.
		$datetime = get_post_datetime( $course->get_id(), 'date', $wp_timezone );

		$data = array(
			'id'                    => $course->get_id(),
			'name'                  => $course->get_name(),
			'slug'                  => $course->get_slug(),
			'status'                => $course->get_status(),
			'password_protected'    => $course->get_password_protected(),
			'post_date'             => $datetime,
			'description'           => $course->get_description(),
			'price_type'            => $course->get_price_type(),
			'price'                 => $course->get_price(),
			'regular_price'         => $course->get_regular_price(),
			'sale_price'            => $course->get_sale_price(),
			'sale_price_dates_from' => $course->get_sale_price_dates_from(),
			'sale_price_dates_to'   => $course->get_sale_price_dates_to(),
			'availability'          => $course->get_availability(),
			'available_date'        => $course->get_available_date(),
			'access_type'           => $course->get_access_type(),
			'has_capacity'          => $course->get_has_capacity(),
			'capacity'              => $course->get_capacity(),
			'image_id'              => $course->get_thumbnail_id(),
			'date_created'          => $course->get_date_created(),
			'date_modified'         => $course->get_date_modified(),
			'type'                  => $course->get_type(),
			'creation_method'       => $course->get_creation_method(),
			'image_src'             => wp_get_attachment_image_src( $course->get_thumbnail_id(), 'large' ) ? wp_get_attachment_image_src( $course->get_thumbnail_id(), 'large' )[0] : '',
			'level'                 => $course->get_level(),
			'thumbnail_id'          => $course->get_thumbnail_id(),
			'video_id'              => $course->get_video_id(),
			'video_src'             => wp_get_attachment_url( $course->get_video_id() ),
			'categories'            => $this->get_taxonomy_terms( $course ),
			'tags'                  => $this->get_taxonomy_terms( $course, 'tag' ),
			'curriculum'            => \OhMyLMS\Curriculum\Placement::items( $course->get_id() ),
			'learning_bundles'      => \OhMyLMS\Curriculum\Placement::bundles( $course->get_id() ),
			'course_url'            => get_permalink( $course->get_id() ),
			'content'               => array(
				'chapters' => count( $course->get_chapters() ),
				'lessons'  => intval( $course->get_lessons_count() ),
				'quizzes'  => intval( $course->get_quiz_count() ),
			),
			'total_enrollment'      => $course->get_total_enrolled_users(),
			'duration'              => $course->get_duration(),
			'enable_reviews'        => $course->get_enable_reviews(),
			'benefit_description'   => $course->get_benefit_description(),
			'benefiter_description' => $course->get_benefiter_description(),
			'requirement'           => $course->get_requirement(),
			'download_resource'     => method_exists( $course, 'get_download_resource' ) ? $course->get_download_resource() : '',
			'leaderboard_disabled'  => method_exists( $course, 'get_leaderboard_disabled' ) ? $course->get_leaderboard_disabled() : 'no',
			'point_disabled'        => method_exists( $course, 'get_point_disabled' ) ? $course->get_point_disabled() : 'no',
			'reward_disabled'       => method_exists( $course, 'get_reward_disabled' ) ? $course->get_reward_disabled() : 'no',
			'sequential_mode'       => method_exists( $course, 'get_sequential_mode' ) ? $course->get_sequential_mode() : 'no',
			'purchase_point'        => method_exists( $course, 'get_purchase_point' ) ? $course->get_purchase_point() : '',
			'currency'              => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
			'currency_pos'          => get_ohmylms_currency_position(),
			'is_cohort'             => 'cohort-based' === $course->get_type(),
			'cohort'                => $course->get_cohort(),
			'funnel_steps'          => method_exists( $course, 'get_funnel_steps' ) ? $course->get_funnel_steps() : array(),
		);
		return $data;
	}


	/**
	 * Prepare links for the request.
	 *
	 * @param mixed $course Course.
	 * @param mixed $request REST request.
	 * @return array[]
	 *
	 * @since 1.0.0
	 */
	protected function prepare_links( $course, $request ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->base, $course->get_id() ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->base ) ),
			),
		);

		return $links;
	}


	/**
	 * Prepare a single course for create or update.
	 *
	 * @param mixed $request REST request.
	 * @return bool|Course|object|WP_Error
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( isset( $request['id'] ) ) {
			$course = ohmylms_get_course( $id );
		} else {
			$course = ohmylms_is_pro() ? new \OhMyLMS\Data\Course() : new Course();
		}

		if ( isset( $request['name'] ) ) {
			$course->set_name( sanitize_text_field( wp_unslash( $request['name'] ) ) );
		}

		if ( isset( $request['slug'] ) ) {
			$course->set_slug( sanitize_text_field( wp_unslash( $request['slug'] ) ) );
		}

		if ( isset( $request['description'] ) ) {
			$course->set_description( $request['description'] );
		}

		if ( isset( $request['status'] ) ) {
			$course->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}

		if ( isset( $request['certificate_id'] ) ) {
			$course->set_certificate( (int) $request['certificate_id'] );
		}

		if ( isset( $request['course_type'] ) ) {
			$course->set_type( $request['course_type'] );
		}

		if ( isset( $request['creation_method'] ) ) {
			$course->set_creation_method( $request['creation_method'] );
		}

		return $course;
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

		$course              = ohmylms_get_course( $post );
		$data                = $this->get_course_data( $course );
		$data['syllabus_id'] = \OhMyLMS\Curriculum\SyllabusCourse::owner( $course->get_id() );
		$chapters            = $course->get_chapters();
		$certificate         = $course->get_certificate();
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
		if ( $chapters ) {
			foreach ( $chapters as $key => $chapter ) {
				$chapter_obj                  = ohmylms_get_chapter( $chapter['id'] );
				$chapters[ $key ]['contents'] = array();
				if ( ( $chapter_obj instanceof Chapter ) ) {
					$chapters[ $key ]['contents'] = $chapter_obj->get_lessons();
				}
			}
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
		$data     = apply_filters( 'ohmylms_rest_get_course_data', $data, $course );
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
	 * Update the status of a course.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_Error|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function update_status( $request ) {
		// Get the status and course ID from the request.
		$status    = sanitize_text_field( $request['status'] );
		$course_id = (int) $request['id'];

		// Get the course object.
		$course = ohmylms_get_course( $course_id );

		if ( ! $course ) {
			return new WP_Error( 'rest_course_not_found', __( 'Course not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		// Get the current post to check previous status.
		$post         = get_post( $course_id );
		$current_time = current_time( 'mysql' );

		// Handle post_date update when changing from 'future' status.
		$update_data = array(
			'ID'          => $course_id,
			'post_status' => $status,
		);

		// If changing from future status to publish/draft, update post_date.
		if ( 'future' === $post->post_status && in_array( $status, array( 'publish', 'draft' ) ) ) {
			$update_data['post_date']     = current_time( 'mysql' );
			$update_data['post_date_gmt'] = get_gmt_from_date( current_time( 'mysql' ) );
		}
		$updated = wp_update_post( $update_data, true );
		if ( ! $updated || is_wp_error( $updated ) ) {
			return new WP_Error( 'rest_course_status_update_failed', __( 'Failed to update course status.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		/*
		 * Fires after a course status is updated via the REST API.
		 *
		 * @param int $course_id The course ID.
		 * @param string $status The new status for the course.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_course_status_updated', $course_id, $status );

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Course status updated.', 'ohmylms' ),
			)
		);
	}


	/**
	 * Assign terms to a course.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_Error|\WP_REST_Response The response object containing the result of the term assignment or an error.
	 *
	 * @since 1.0.0
	 */
	public function assign_terms_to_course( $request ) {
		$course_id = (int) $request['id'];
		$taxonomy  = $request['taxonomy'];
		$terms     = $request['terms'];

		if ( empty( $course_id ) || empty( $taxonomy ) || empty( $terms ) ) {
			return new WP_Error( 'missing_params', 'Missing parameters', array( 'status' => 400 ) );
		}
		if ( in_array( $taxonomy, array( 'course_category', 'course_tag' ), true ) ) {
			return new WP_Error( 'ohmylms_taxonomy_replaced', __( 'Course categories and tags were replaced by curriculum items and course bundles. Use /courses/{id}/organization.', 'ohmylms' ), array( 'status' => 410 ) );
		}
		$result = wp_set_object_terms( $course_id, $terms, $taxonomy );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'terms'   => $result,
			)
		);
	}

	/**
	 * Get taxonomy terms for a course.
	 *
	 * @param Course $course The course object.
	 * @param string $taxonomy The taxonomy to retrieve terms for. Default is 'category'.
	 * @return array The array of terms with their IDs and names.
	 *
	 * @since 1.0.0
	 */
	protected function get_taxonomy_terms( $course, $taxonomy = 'category' ) {
		$terms = array();
		// Course categories and tags were replaced by curriculum items and course bundles. The admin app still
		// reads categories and tags from the course, so these keys now carry the replacements.
		if ( 'tag' === $taxonomy ) {
			foreach ( \OhMyLMS\Curriculum\Placement::bundles( $course->get_id() ) as $bundle ) {
				$terms[] = array(
					'id'   => $bundle['id'],
					'name' => $bundle['title'],
					'slug' => $bundle['slug'],
				);
			}
			return $terms;
		}
		foreach ( \OhMyLMS\Curriculum\Placement::items( $course->get_id() ) as $item ) {
			$terms[] = array(
				'id'   => $item['id'],
				'name' => $item['name'],
				'slug' => $item['slug'],
			);
		}
		return $terms;
	}


	/**
	 * Handle terms for a course.
	 *
	 * This method processes and assigns terms to a course based on the provided request data.
	 *
	 * @param int              $post_id The ID of the course post.
	 * @param \WP_REST_Request $request The REST request object containing the term data.
	 * .
	 * @since 1.0.0
	 */
	protected function handle_terms( $post_id, $request ) {
		$taxonomies = wp_list_filter( get_object_taxonomies( OHMYLMS_COURSE_CPT, 'objects' ), array( 'show_in_rest' => true ) );
		foreach ( $taxonomies as $taxonomy ) {
			// Categories and tags are replaced by curriculum and course bundles (see CourseOrganizationController).
			if ( in_array( $taxonomy->name, array( 'course_category', 'course_tag' ), true ) ) {
				continue;
			}
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;
			if ( 'course_category' === $base ) {
				$base = 'categories';
			} elseif ( 'course_tag' === $base ) {
				$base = 'tags';
			}
			if ( ! isset( $request[ $base ] ) ) {
				continue;
			}

			$result = wp_set_object_terms( $post_id, array_column( $request[ $base ], 'id' ), $taxonomy->name );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
	}
	/**
	 * Updates the status and password of a course.
	 *
	 * @param int             $course_id The ID of the course to update.
	 * @param WP_REST_Request $request The request object containing the updated data.
	 * @return void
	 */
	protected function update_course_status_and_password( $course_id, $request ) {
		$current_time = current_time( 'mysql' ); // Get the current date and time.
		$post         = get_post( $course_id ); // Retrieve the post details.

		if ( ! $post ) {
			return;
		}

		// Get the current post_date.
		$existing_post_date = $post->post_date;

		if ( isset( $request['status'] ) ) {
			$status      = $request['status'];
			$update_data = array(
				'ID'          => $course_id,
				'post_status' => $status,
			);

			if ( 'future' === $status && isset( $request['post_date']['date'] ) ) {
				$update_data['post_date'] = $request['post_date']['date'];
			} elseif ( in_array( $status, array( 'publish', 'draft' ) ) ) {
				$update_data['post_date'] = ( $existing_post_date < $current_time ) ? $existing_post_date : $current_time;
			}

			wp_update_post( $update_data );
		}
	}


	/**
	 * Save cohort settings for a course.
	 *
	 * @param int             $course_id Course identifier.
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	protected function save_cohort_settings( $course_id, $request ) {
		global $wpdb;
		$table        = $wpdb->prefix . 'ohmylms_cohorts';
		$cohorts      = isset( $request['cohort'] ) && is_array( $request['cohort'] ) ? $request['cohort'] : array();
		$existing_ids = array();
		$course       = ohmylms_get_course( $course_id );
		foreach ( $cohorts as $cohort ) {
			$data = array(
				'course_id'      => $course_id,
				'title'          => isset( $cohort['title'] ) ? sanitize_text_field( $cohort['title'] ) : 'Untitled Cohort',
				'start_date'     => ! empty( $cohort['start_date'] ) ? date( 'Y-m-d H:i:s', strtotime( $cohort['start_date'] ) ) : null,
				'end_date'       => ! empty( $cohort['end_date'] ) ? date( 'Y-m-d H:i:s', strtotime( $cohort['end_date'] ) ) : null,
				'enrollment_end' => ! empty( $cohort['enrollment_deadline'] ) ? date( 'Y-m-d H:i:s', strtotime( $cohort['enrollment_deadline'] ) ) : null,
				'has_capacity'   => ! empty( $cohort['has_capacity'] ) ? 1 : 0,
				'capacity'       => isset( $cohort['capacity'] ) ? intval( $cohort['capacity'] ) : null,
				'status'         => isset( $cohort['status'] ) ? sanitize_text_field( $cohort['status'] ) : 'active',
				'meta'           => isset( $cohort['meta'] ) ? maybe_serialize( $cohort['meta'] ) : null,
			);

			if ( ! empty( $cohort['id'] ) ) {
				$wpdb->update(
					$table,
					$data,
					array( 'id' => intval( $cohort['id'] ) ),
					array(
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d',
						'%f',
						'%s',
						'%s',
					),
					array( '%d' )
				);
				$existing_ids[] = intval( $cohort['id'] );
			} else {
				$wpdb->insert(
					$table,
					$data,
					array(
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d',
						'%f',
						'%s',
						'%s',
					)
				);
				$existing_ids[] = $wpdb->insert_id;
			}
		}

		if ( ! empty( $existing_ids ) ) {
			$ids_placeholder = implode( ',', array_fill( 0, count( $existing_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM %i WHERE course_id = %d AND id NOT IN ($ids_placeholder)",
					array_merge( array( $table, $course_id ), $existing_ids )
				)
			);
		} else {
			$wpdb->delete( $table, array( 'course_id' => $course_id ), array( '%d' ) );
		}

		return true;
	}
}
