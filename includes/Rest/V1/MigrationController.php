<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MigrationController class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;

/**
 * MigrationController class.
 *
 * Handles REST API endpoints for migration.
 *
 * @since 1.0.0
 */
class MigrationController extends RestController {

	/**
	 * The base route for migration endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'migrations/(?P<source>[\w-]+)';


	/**
	 * Check migration permission for Migration controller.
	 */
	public function check_migration_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register the routes for the migration endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				'args' => array(
					'source' => array(
						'description' => __( 'Source.', 'ohmylms' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'migrate' ),
					'permission_callback' => array( $this, 'check_migration_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/courses',
			array(
				'args' => array(
					'source' => array(
						'description' => __( 'Source.', 'ohmylms' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => array( $this, 'check_migration_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Handles migration of courses from the source system to the current platform.
	 *
	 * This function performs a migration of a course, checking if LMS is installed,
	 * and migrating the course data accordingly.
	 *
	 * @param \WP_REST_Request $request The request object containing necessary parameters.
	 *
	 * @return \WP_REST_Response|\WP_Error The response with migration status or an error.
	 */
	public function migrate( \WP_REST_Request $request ) {
		// Extract 'source' from the request.
		$source = sanitize_text_field( $request['source'] );

		// Initialize the migration class for the given source.
		$migration     = new \OhMyLMS\Migrations\Migration( $source );
		$source_object = $migration->run();
		// Check if 'course_id' is set in the request for LMS.
		if ( isset( $request[ $source ]['course_id'] ) ) {
			$course_id = intval( $request[ $source ]['course_id'] ); // Ensure course_id is an integer.

			// If a valid course_id is provided, initialize the migration process.
			if ( $course_id ) {

				// Attempt to initialize the source object with the provided course_id.
				$is_init = $source_object->init( $course_id );

				// If initialization fails, return an error indicating LMS is not installed.
				if ( ! $is_init ) {
					return new \WP_Error(
						'ohmylms_not_found',
						sprintf(
							/* translators: %s: source LMS name. */
							__( '%s is not installed.', 'ohmylms' ),
							$source
						),
						array( 'status' => 400 )
					);
				}
				// Migrate the course data.
				$source_object->migrate_course();

				// Return a success response after migration.
				$response = array(
					'status'  => 'success',
					'message' => __( 'Course migrated successfully', 'ohmylms' ),
				);
				do_action( 'ohmylms_migration_completed', $source, $course_id );
				return rest_ensure_response( $response );
			}
		}

		// If no course_id is provided or the migration fails, return an error.
		return new \WP_Error(
			'ohmylms_migration_error',
			__( 'Migration failed', 'ohmylms' ),
			array( 'status' => 400 )
		);
	}



	/**
	 * Fetches the list of courses from the database.
	 *
	 * This function retrieves all posts of type 'courses' from the WordPress database
	 * and returns them in a structured response format for the REST API.
	 *
	 * @param \WP_REST_Request $request The request object containing parameters.
	 *
	 * @return \WP_REST_Response The response object containing the course data.
	 */
	public function get_courses( \WP_REST_Request $request ) {
		global $wpdb;

		// Sanitize the 'source' parameter from the request to prevent security issues.
		$source    = sanitize_text_field( $request->get_param( 'source' ) );
		$post_type = 'courses';

		if ( 'tutorLMS' === $source ) {
			if ( ! defined( 'TUTOR_VERSION' ) ) {
				return new \WP_Error(
					'ohmylms_tutor_not_found',
					__( 'Tutor LMS is not installed.', 'ohmylms' ),
					array( 'status' => 400 )
				);
			}
			$post_type = 'courses';
		} elseif ( 'learnDash' === $source ) {
			if ( ! defined( 'LEARNDASH_VERSION' ) ) {
				return new \WP_Error(
					'ohmylms_learndash_not_found',
					__( 'LearnDash LMS is not installed.', 'ohmylms' ),
					array( 'status' => 400 )
				);
			}
			$post_type = 'sfwd-courses';
		} elseif ( 'learnPress' === $source ) {
			if ( ! defined( 'LEARNPRESS_VERSION' ) ) {
				return new \WP_Error(
					'ohmylms_learnpress_not_found',
					__( 'LearnPress is not installed.', 'ohmylms' ),
					array( 'status' => 400 )
				);
			}
			$post_type = 'lp_course';
		} elseif ( 'masterStudy' === $source ) {
			$is_masterstudy_active = defined( 'STM_LMS_VERSION' ) || defined( 'STM_LMS_FILE' ) || defined( 'MASTERSTUDY_LMS_VERSION' ) || class_exists( 'STM_LMS' ) || post_type_exists( 'stm-courses' );
			if ( ! $is_masterstudy_active ) {
				return new \WP_Error(
					'ohmylms_masterstudy_not_found',
					__( 'MasterStudy LMS is not installed.', 'ohmylms' ),
					array( 'status' => 400 )
				);
			}
			$post_type = 'stm-courses';
		}

		try {
			// Prepare the SQL query to fetch courses using $wpdb->prepare for security.
			$query = $wpdb->prepare(
				'SELECT ID, post_title FROM %i WHERE post_type = %s AND post_status != %s',
				$wpdb->prefix . 'posts',
				$post_type,
				// Specify the post type for courses.
				'trash' // Exclude trashed courses.
			);

			// Execute the query to get the results.
			$results = $wpdb->get_results( $query );

			// Initialize an empty array to store course data.
			$courses = array();

			// Check if the query returned any results.
			if ( ! empty( $results ) ) {
				// Loop through each course result and add it to the $courses array.
				foreach ( $results as $result ) {
					$course_id = intval( $result->ID );

					// Get the thumbnail URL (featured image)
					// All LMS plugins (LearnPress, LearnDash, TutorLMS) use WordPress's standard featured image system
					// The featured image ID is stored in wp_postmeta with meta_key '_thumbnail_id'.
					$thumbnail_url = get_the_post_thumbnail_url( $course_id, 'medium' );

					// If no thumbnail is set, provide a fallback empty string.
					if ( ! $thumbnail_url ) {
						$thumbnail_url = '';
					}

					$courses[] = array(
						'id'        => $course_id,  // Ensure the course ID is an integer.
						'title'     => esc_html( $result->post_title ),  // Escape course title for safe output.
						'thumbnail' => esc_url( $thumbnail_url ),  // Course thumbnail/featured image URL.
					);
				}
			}

			// Return a response with the courses data.
			return rest_ensure_response(
				array(
					'status'  => 'success',  // Indicate success in the response.
					'message' => __( 'Courses fetched successfully', 'ohmylms' ), // Provide a success message.
					'courses' => $courses,  // Include the list of courses in the response.
				)
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'ohmylms_source_not_supported',
				__( 'Source is not supported.', 'ohmylms' ),
				array( 'status' => 400 )
			);
		}
	}
}
