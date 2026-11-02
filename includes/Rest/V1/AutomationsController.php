<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AutomationsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Automation\Rules;
use OhMyLMS\Automation\RuleStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/automations`: rule list and editor, editor options, pickers and the run log. Every
 * route requires `manage_options`. See docs/api/automations.md.
 *
 * @since 1.0.0
 */
class AutomationsController extends RestController {

	const CAPABILITY = 'manage_options';

	/**
	 * Post types the pickers search.
	 */
	const CHOICES = array(
		'course'     => 'ohmylms-course',
		'membership' => 'ohmylms-membership',
		'lesson'     => 'ohmylms-lesson',
		'quiz'       => 'ohmylms-quiz',
		'assignment' => 'ohmylms-assignment',
		'coupon'     => 'ohmylms_coupon',
	);

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'automations';

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
							'search' => array(
								'type'      => 'string',
								'maxLength' => 100,
								'default'   => '',
							),
							'status' => array(
								'type'    => 'string',
								'enum'    => array( 'any', 'active', 'paused' ),
								'default' => 'any',
							),
							'event'  => array(
								'type' => 'string',
								'enum' => array_keys( Rules::events() ),
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
			'/' . $this->rest_base . '/options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_options' ),
					'permission_callback' => $permission,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/choices',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_choices' ),
					'permission_callback' => $permission,
					'args'                => array(
						'type'    => array(
							'type'     => 'string',
							'enum'     => array_merge( array_keys( self::CHOICES ), array( 'cohort' ) ),
							'required' => true,
						),
						'search'  => array(
							'type'      => 'string',
							'maxLength' => 100,
							'default'   => '',
						),
						'include' => array(
							'type'     => 'array',
							'items'    => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'maxItems' => Rules::MAX_IDS,
							'default'  => array(),
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/log',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log' ),
					'permission_callback' => $permission,
					'args'                => array_merge(
						$this->paging(),
						array(
							'status' => array(
								'type'    => 'string',
								'enum'    => array( 'any', 'success', 'skipped', 'failed' ),
								'default' => 'any',
							),
						)
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
			'/' . $this->rest_base . '/(?P<id>[\d]+)/log',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log' ),
					'permission_callback' => $permission,
					'args'                => array_merge(
						$this->paging(),
						array(
							'status' => array(
								'type'    => 'string',
								'enum'    => array( 'any', 'success', 'skipped', 'failed' ),
								'default' => 'any',
							),
						)
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
			return new WP_Error( 'ohmylms_automation_forbidden', __( 'Sorry, you are not allowed to manage automations.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Stored rule or 404.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error Prepared rule.
	 */
	private function found( $request ) {
		$row = RuleStore::row( (int) $request['id'] );
		return $row ? RuleStore::prepare( $row ) : new WP_Error( 'ohmylms_automation_not_found', __( 'Rule not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Paged response.
	 *
	 * @param array           $page    `{items, total}`.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function paged( array $page, $request ) {
		$pages = max( 1, (int) ceil( $page['total'] / (int) $request['per_page'] ) );
		if ( (int) $request['page'] > $pages && $page['total'] > 0 ) {
			return new WP_Error( 'ohmylms_automation_invalid_page', __( 'The page number is beyond the last page.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$response = rest_ensure_response( $page['items'] );
		$response->header( 'X-WP-Total', (string) $page['total'] );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * List rules.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		return $this->paged(
			RuleStore::page(
				array(
					'page'     => $request['page'],
					'per_page' => $request['per_page'],
					'search'   => $request['search'],
					'status'   => $request['status'],
					'event'    => (string) $request['event'],
				)
			),
			$request
		);
	}

	/**
	 * Editor options.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_options( $request ) {
		return rest_ensure_response( Rules::options() );
	}

	/**
	 * Picker search (at most 20), or the labels of `include`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_choices( $request ) {
		$include = array_map( 'absint', (array) $request['include'] );
		if ( 'cohort' === $request['type'] ) {
			$page  = \OhMyLMS\Cohorts\Cohorts::page(
				array(
					'page'     => 1,
					'per_page' => 50,
					'search'   => $request['search'],
					'status'   => 'active',
				)
			);
			$items = array();
			foreach ( $page['items'] as $cohort ) {
				if ( ! $include || in_array( $cohort['id'], $include, true ) ) {
					$items[] = array(
						'id'    => $cohort['id'],
						'title' => $cohort['title'] . ' — ' . $cohort['target_title'],
					);
				}
			}
			return rest_ensure_response( array_slice( $items, 0, 20 ) );
		}
		$args = array(
			'post_type'              => self::CHOICES[ $request['type'] ],
			'post_status'            => array( 'publish', 'draft', 'private', 'future', 'pending' ),
			'posts_per_page'         => $include ? count( $include ) : 20,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( $include ) {
			$args['post__in'] = $include;
		} elseif ( '' !== $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}
		$items = array();
		foreach ( ( new \WP_Query( $args ) )->posts as $post ) {
			$items[] = array(
				'id'     => (int) $post->ID,
				'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'status' => (string) $post->post_status,
			);
		}
		return rest_ensure_response( $items );
	}

	/**
	 * Create a rule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$rule = Rules::validate( $request->get_json_params() );
		if ( is_wp_error( $rule ) ) {
			return $rule;
		}
		$created = RuleStore::create( $rule, get_current_user_id() );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$response = rest_ensure_response( $created );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $created['id'] ) );
		return $response;
	}

	/**
	 * Read a rule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$rule = $this->found( $request );
		return is_wp_error( $rule ) ? $rule : rest_ensure_response( $rule );
	}

	/**
	 * Partially update a rule (validated as a whole after merging).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$current = $this->found( $request );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$current['conditions'] = (array) $current['conditions'];
		$rule                  = Rules::validate( $request->get_json_params(), $current );
		if ( is_wp_error( $rule ) ) {
			return $rule;
		}
		$updated = RuleStore::update( $current['id'], $rule );
		return is_wp_error( $updated ) ? $updated : rest_ensure_response( $updated );
	}

	/**
	 * Delete a rule and its log.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$current = $this->found( $request );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		if ( ! RuleStore::delete( $current['id'] ) ) {
			return new WP_Error( 'ohmylms_automation_not_deleted', __( 'The rule could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $current,
			)
		);
	}

	/**
	 * Run log of all rules or of one.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_log( $request ) {
		if ( isset( $request['id'] ) ) {
			$current = $this->found( $request );
			if ( is_wp_error( $current ) ) {
				return $current;
			}
		}
		return $this->paged(
			RuleStore::log_page(
				array(
					'page'          => $request['page'],
					'per_page'      => $request['per_page'],
					'automation_id' => isset( $request['id'] ) ? (int) $request['id'] : 0,
					'status'        => $request['status'],
				)
			),
			$request
		);
	}
}
