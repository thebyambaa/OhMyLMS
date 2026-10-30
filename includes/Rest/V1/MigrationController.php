<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MigrationController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Migrations\Migration;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Course migration from other LMS plugins under `ohmylms/v1/migrations`: sources, a paged list of a
 * source's courses and migrating one course. Administrators only. See docs/api/settings.md
 * ("Migration").
 *
 * @since 1.0.0
 */
class MigrationController extends RestController {

	/**
	 * Capability of every migration route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Meta key that links a migrated OhMyLMS course to its source (`<source>:<id>`).
	 */
	const SOURCE_META = '_ohmylms_migrated_from';

	/**
	 * Largest page.
	 */
	const MAX_PER_PAGE = 50;

	/**
	 * Course post type of each built-in source.
	 */
	const POST_TYPES = array(
		'tutorLMS'    => 'courses',
		'learnDash'   => 'sfwd-courses',
		'learnPress'  => 'lp_course',
		'masterStudy' => 'stm-courses',
	);

	/**
	 * The base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'migrations';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$source = array(
			'source' => array(
				'description' => __( 'Source plugin id, such as tutorLMS.', 'ohmylms' ),
				'type'        => 'string',
			),
		);
		$base   = '/' . $this->rest_base . '/(?P<source>[A-Za-z][A-Za-z0-9_-]{0,39})';

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sources' ),
					'permission_callback' => array( $this, 'check_migration_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base,
			array(
				'args' => $source,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'migrate' ),
					'permission_callback' => array( $this, 'check_migration_permission' ),
					'args'                => array(
						'course_id' => array(
							'description' => __( 'Course ID in the source plugin.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/courses',
			array(
				'args' => $source,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => array( $this, 'check_migration_permission' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
						),
						'search'   => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
					),
				),
			)
		);
	}

	/**
	 * Only administrators may read another plugin's courses or create courses from them.
	 *
	 * @return true|WP_Error
	 */
	public function check_migration_permission() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_migration_forbidden', __( 'Sorry, you are not allowed to migrate courses.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Whether a source plugin is active.
	 *
	 * @param string $id     Source id.
	 * @param array  $source Source definition.
	 * @return bool
	 */
	private function is_available( $id, array $source ) {
		switch ( $id ) {
			case 'tutorLMS':
				return defined( 'TUTOR_VERSION' );
			case 'learnDash':
				return defined( 'LEARNDASH_VERSION' );
			case 'learnPress':
				return defined( 'LEARNPRESS_VERSION' );
			case 'masterStudy':
				return defined( 'STM_LMS_VERSION' ) || defined( 'STM_LMS_FILE' ) || defined( 'MASTERSTUDY_LMS_VERSION' ) || class_exists( 'STM_LMS' ) || post_type_exists( 'stm-courses' );
		}
		return ! empty( $source['post_type'] ) && post_type_exists( (string) $source['post_type'] );
	}

	/**
	 * Course post type of a source.
	 *
	 * @param string $id     Source id.
	 * @param array  $source Source definition.
	 * @return string
	 */
	private function post_type( $id, array $source ) {
		return self::POST_TYPES[ $id ] ?? ( isset( $source['post_type'] ) ? (string) $source['post_type'] : '' );
	}

	/**
	 * Resolve the source of a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{id:string,source:array}|WP_Error
	 */
	private function source( $request ) {
		$id      = (string) $request['source'];
		$sources = ( new Migration() )->get_sources();
		if ( ! isset( $sources[ $id ] ) || ! is_array( $sources[ $id ] ) ) {
			return new WP_Error( 'ohmylms_migration_unknown_source', __( 'Unknown migration source.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! $this->is_available( $id, $sources[ $id ] ) || '' === $this->post_type( $id, $sources[ $id ] ) ) {
			return new WP_Error(
				'ohmylms_migration_source_inactive',
				/* translators: %s: source plugin name. */
				sprintf( __( '%s is not installed and active.', 'ohmylms' ), (string) ( $sources[ $id ]['title'] ?? $id ) ),
				array( 'status' => 409 )
			);
		}
		return array(
			'id'     => $id,
			'source' => $sources[ $id ],
		);
	}

	/**
	 * Migration sources and whether each plugin is active.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_sources() {
		$data = array();
		foreach ( ( new Migration() )->get_sources() as $id => $source ) {
			if ( ! is_array( $source ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,39}$/', (string) $id ) ) {
				continue;
			}
			$data[] = array(
				'id'          => (string) $id,
				'title'       => wp_strip_all_tags( (string) ( $source['title'] ?? $id ) ),
				'description' => wp_strip_all_tags( (string) ( $source['description'] ?? '' ) ),
				'available'   => $this->is_available( (string) $id, $source ),
			);
		}
		return rest_ensure_response( $data );
	}

	/**
	 * OhMyLMS courses already migrated from some source courses.
	 *
	 * @param string $source Source id.
	 * @param int[]  $ids    Source course IDs.
	 * @return array<int,int> Source ID => OhMyLMS course ID.
	 */
	private function migrated( $source, array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$keys    = array_map(
			static function ( $id ) use ( $source ) {
				return $source . ':' . (int) $id;
			},
			$ids
		);
		$holders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders are generated from the key count.
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT m.meta_value, MAX(p.ID) AS course_id FROM %i m INNER JOIN %i p ON p.ID = m.post_id AND p.post_type = 'ohmylms-course' AND p.post_status NOT IN ('trash','auto-draft') WHERE m.meta_key = %s AND m.meta_value IN ({$holders}) GROUP BY m.meta_value", array_merge( array( $wpdb->postmeta, $wpdb->posts, self::SOURCE_META ), $keys ) ), ARRAY_A );
		$found = array();
		foreach ( $rows as $row ) {
			$found[ (int) substr( (string) $row['meta_value'], strlen( $source ) + 1 ) ] = (int) $row['course_id'];
		}
		return $found;
	}

	/**
	 * Paged courses of a source, with the OhMyLMS course each was migrated to.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_courses( WP_REST_Request $request ) {
		$resolved = $this->source( $request );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $request['per_page'] ) );
		$args     = array(
			'post_type'              => $this->post_type( $resolved['id'], $resolved['source'] ),
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => array(
				'title' => 'ASC',
				'ID'    => 'ASC',
			),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$search   = trim( (string) $request['search'] );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$query = new WP_Query( $args );
		$total = (int) $query->found_posts;
		$pages = (int) ceil( $total / $per_page );
		if ( $total > 0 && $page > $pages ) {
			return new WP_Error( 'ohmylms_migration_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$migrated = $this->migrated( $resolved['id'], wp_list_pluck( $query->posts, 'ID' ) );
		$items    = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'          => (int) $post->ID,
				'title'       => html_entity_decode( (string) $post->post_title, ENT_QUOTES, 'UTF-8' ),
				'status'      => (string) $post->post_status,
				'migrated_to' => $migrated[ (int) $post->ID ] ?? null,
			);
		}
		$response = rest_ensure_response( $items );
		( new StudentController() )->add_pagination_headers( $response, $request, $total, $pages, $page, $this->rest_base . '/' . $resolved['id'] . '/courses' );
		return $response;
	}

	/**
	 * Migrate one course into a new draft OhMyLMS course.
	 *
	 * @param WP_REST_Request $request Request with `course_id`.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function migrate( WP_REST_Request $request ) {
		$resolved = $this->source( $request );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$id        = $resolved['id'];
		$course_id = (int) $request['course_id'];
		if ( ! $course_id && isset( $request[ $id ]['course_id'] ) ) {
			// The legacy screen nests the ID under the source name.
			$course_id = absint( $request[ $id ]['course_id'] );
		}
		$course = $course_id ? get_post( $course_id ) : null;
		if ( ! $course || $this->post_type( $id, $resolved['source'] ) !== $course->post_type || in_array( $course->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'ohmylms_migration_course_not_found', __( 'That course does not exist in the source plugin.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$existing = $this->migrated( $id, array( $course_id ) );
		if ( isset( $existing[ $course_id ] ) ) {
			return new WP_Error(
				'ohmylms_migration_already_done',
				__( 'This course was already migrated. Delete the migrated copy to migrate it again.', 'ohmylms' ),
				array(
					'status'    => 409,
					'course_id' => $existing[ $course_id ],
				)
			);
		}

		$migrator = ( new Migration( $id ) )->run();
		if ( ! is_object( $migrator ) || ! method_exists( $migrator, 'init' ) || ! method_exists( $migrator, 'migrate_course' ) || ! $migrator->init( $course_id ) ) {
			return new WP_Error( 'ohmylms_migration_source_inactive', __( 'The source plugin is not ready for migration.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$new_id = $migrator->migrate_course();
		if ( is_wp_error( $new_id ) || ! is_numeric( $new_id ) || (int) $new_id < 1 ) {
			return new WP_Error( 'ohmylms_migration_failed', __( 'The course could not be migrated.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		update_post_meta( (int) $new_id, self::SOURCE_META, $id . ':' . $course_id );

		/**
		 * Fires after a course was migrated from another LMS.
		 *
		 * @param string $source    Source id.
		 * @param int    $course_id Source course ID.
		 */
		do_action( 'ohmylms_migration_completed', $id, $course_id );

		return rest_ensure_response(
			array(
				'source'      => $id,
				'course_id'   => $course_id,
				'migrated_to' => (int) $new_id,
			)
		);
	}
}
