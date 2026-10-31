<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * CohortsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Cohorts\Cohorts;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/cohorts`: cohort list and editor, members, window sync and pickers. Every route
 * requires `manage_options`. See docs/api/cohorts.md.
 *
 * @since 1.0.0
 */
class CohortsController extends RestController {

	const CAPABILITY = 'manage_options';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'cohorts';

	/**
	 * Paging arguments.
	 *
	 * @return array
	 */
	private function paging() {
		return array(
			'page'     => array(
				'type'    => 'integer',
				'minimum' => 1,
				'default' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
				'default' => 20,
			),
			'search'   => array(
				'type'      => 'string',
				'maxLength' => 100,
				'default'   => '',
			),
		);
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$permission = array( $this, 'permissions_check' );
		$id         = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $permission,
					'args'                => array_merge(
						$this->paging(),
						array(
							'course_id'     => array(
								'type'    => 'integer',
								'minimum' => 0,
							),
							'membership_id' => array(
								'type'    => 'integer',
								'minimum' => 0,
							),
							'status'        => array(
								'type'    => 'string',
								'enum'    => array( 'any', 'active', 'archived' ),
								'default' => 'any',
							),
						)
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $permission,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/targets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'targets' ),
					'permission_callback' => $permission,
					'args'                => array(
						'scope'  => array(
							'type'     => 'string',
							'enum'     => array( 'course', 'membership' ),
							'required' => true,
						),
						'search' => array(
							'type'      => 'string',
							'maxLength' => 100,
							'default'   => '',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $permission,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/members',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_members' ),
					'permission_callback' => $permission,
					'args'                => $this->paging(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_member' ),
					'permission_callback' => $permission,
					'args'                => array(
						'user_id' => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/members/(?P<user_id>[\d]+)',
			array(
				'args' => array(
					'id'      => $id,
					'user_id' => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_member' ),
					'permission_callback' => $permission,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/candidates',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'candidates' ),
					'permission_callback' => $permission,
					'args'                => array(
						'search' => array(
							'type'      => 'string',
							'maxLength' => 100,
							'default'   => '',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/sync',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'sync' ),
					'permission_callback' => $permission,
					'args'                => array(
						'confirm' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Administrator capability.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permissions_check( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_cohort_forbidden', __( 'Sorry, you are not allowed to manage cohorts.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Stored row or 404.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	private function found( $request ) {
		$row = Cohorts::row( (int) $request['id'] );
		return $row ? $row : new WP_Error( 'ohmylms_cohort_not_found', __( 'Cohort not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Paged response with WordPress total headers.
	 *
	 * @param array           $page    `{items, total}`.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function paged( array $page, $request ) {
		$pages = max( 1, (int) ceil( $page['total'] / (int) $request['per_page'] ) );
		if ( (int) $request['page'] > $pages && $page['total'] > 0 ) {
			return new WP_Error( 'ohmylms_cohort_invalid_page', __( 'The page number is beyond the last page.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$response = rest_ensure_response( $page['items'] );
		$response->header( 'X-WP-Total', (string) $page['total'] );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * List cohorts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		return $this->paged(
			Cohorts::page(
				array(
					'page'          => $request['page'],
					'per_page'      => $request['per_page'],
					'search'        => $request['search'],
					'course_id'     => (int) $request['course_id'],
					'membership_id' => (int) $request['membership_id'],
					'status'        => $request['status'],
				)
			),
			$request
		);
	}

	/**
	 * Create a cohort.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$row = Cohorts::validate( $request->get_json_params() );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$cohort = Cohorts::create( $row );
		if ( is_wp_error( $cohort ) ) {
			return $cohort;
		}
		$response = rest_ensure_response( $cohort );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $cohort['id'] ) );
		return $response;
	}

	/**
	 * Read one cohort.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$row = $this->found( $request );
		return is_wp_error( $row ) ? $row : rest_ensure_response( Cohorts::prepare( $row ) );
	}

	/**
	 * Partially update a cohort.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$row = $this->found( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$changes = Cohorts::validate( $request->get_json_params(), $row );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}
		$cohort = Cohorts::update( (int) $row['id'], $changes );
		return is_wp_error( $cohort ) ? $cohort : rest_ensure_response( $cohort );
	}

	/**
	 * Delete an empty cohort.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$row = $this->found( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$previous = Cohorts::prepare( $row );
		$deleted  = Cohorts::delete( (int) $row['id'] );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * List members.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_members( $request ) {
		$row = $this->found( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return $this->paged(
			Cohorts::members_page(
				(int) $row['id'],
				array(
					'page'     => $request['page'],
					'per_page' => $request['per_page'],
					'search'   => $request['search'],
				)
			),
			$request
		);
	}

	/**
	 * Add a member by hand.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_member( $request ) {
		$row = $this->found( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$added = Cohorts::add_member( (int) $row['id'], (int) $request['user_id'], 'manual', 0, get_current_user_id() );
		if ( is_wp_error( $added ) ) {
			return $added;
		}
		$response = rest_ensure_response( Cohorts::prepare( Cohorts::row( (int) $row['id'] ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Remove a member.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_member( $request ) {
		$row = $this->found( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$removed = Cohorts::remove_member( (int) $row['id'], (int) $request['user_id'] );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}
		return rest_ensure_response( Cohorts::prepare( Cohorts::row( (int) $row['id'] ) ) );
	}

	/**
	 * Add every learner who enrolled inside the window.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync( $request ) {
		if ( true !== $request['confirm'] ) {
			return new WP_Error( 'ohmylms_cohort_confirm_required', __( 'Confirm adding the learners.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$row = $this->found( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$result = Cohorts::sync( (int) $row['id'], get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array_merge( $result, array( 'cohort' => Cohorts::prepare( Cohorts::row( (int) $row['id'] ) ) ) ) );
	}

	/**
	 * Courses or plans to attach a cohort to (at most 20, by title).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function targets( $request ) {
		$query = new \WP_Query(
			array(
				'post_type'              => 'course' === $request['scope'] ? OHMYLMS_COURSE_CPT : OHMYLMS_MEMBERSHIP_CPT,
				'post_status'            => array( 'publish', 'draft', 'private', 'future', 'pending' ),
				's'                      => (string) $request['search'],
				'posts_per_page'         => 20,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'    => (int) $post->ID,
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			);
		}
		return rest_ensure_response( $items );
	}

	/**
	 * Enrolled learners who could join (at most 20).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function candidates( $request ) {
		global $wpdb;
		$row = $this->found( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$plan   = (int) $row['membership_id'] > 0;
		$table  = $plan ? $wpdb->prefix . 'ohmylms_user_membership' : $wpdb->prefix . 'ohmylms_user_enrollment';
		$column = $plan ? 'membership_id' : 'course_id';
		$status = $plan ? "e.status IN ('enrolled','active','completed')" : "e.status = 'enrolled'";
		$like   = '%' . $wpdb->esc_like( (string) $request['search'] ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column and $status are fixed strings.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT u.ID, u.display_name, u.user_login FROM %i e JOIN %i u ON u.ID = e.user_id
				WHERE e.{$column} = %d AND {$status}
				AND u.ID NOT IN ( SELECT user_id FROM %i WHERE cohort_id = %d )
				AND ( u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s )
				ORDER BY u.display_name ASC LIMIT 20",
				$table,
				$wpdb->users,
				(int) $row[ $column ],
				\OhMyLMS\Cohorts\CohortSchema::members(),
				(int) $row['id'],
				$like,
				$like,
				$like
			),
			ARRAY_A
		);
		// phpcs:enable
		$items = array();
		foreach ( $rows ? $rows : array() as $user ) {
			$items[] = array(
				'user_id' => (int) $user['ID'],
				'name'    => (string) $user['display_name'],
				'login'   => (string) $user['user_login'],
			);
		}
		return rest_ensure_response( $items );
	}
}
