<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * CategoryController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\Curriculum\Schema;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * CategoryController class.
 *
 * Course categories were replaced by the curriculum, so this is a read-only, paged view of the
 * curriculum items in the shape of the former category terms. Writes are refused with 410: structure
 * is managed with `/curriculum/items`, a course's placement with `/courses/{id}/organization`.
 * TagController extends this class for Course Bundles. See docs/api/taxonomies.md.
 *
 * Reading needs READ_CAPABILITY (course authors fill filters and pickers with it); the refused write
 * routes and the `edit` context (course titles) need WRITE_CAPABILITY, so a non-administrator gets
 * 403 and never learns more than the public names.
 *
 * @since 1.0.0
 */
class CategoryController extends RestController {

	/**
	 * Capability required to read the collection and single items.
	 */
	const READ_CAPABILITY = 'edit_posts';

	/**
	 * Capability required for the `edit` context and for the (refused) write routes.
	 */
	const WRITE_CAPABILITY = 'manage_options';

	/**
	 * Maximum number of courses embedded in a single item.
	 */
	const COURSE_LIMIT = 100;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'categories';

	/**
	 * Singular name used in error codes (`ohmylms_<singular>_not_found`).
	 *
	 * @var string
	 */
	protected $singular = 'category';

	/**
	 * Whether items have a parent.
	 *
	 * @var bool
	 */
	protected $hierarchical = true;

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'replaced' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'replaced' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the item.', 'ohmylms' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'replaced' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'replaced' ),
					'permission_callback' => array( $this, 'write_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Check if the current user can list items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( self::READ_CAPABILITY ) ) {
			return new WP_Error( "ohmylms_{$this->singular}_cannot_view", __( 'Sorry, you are not allowed to view this list.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		if ( 'edit' === $request['context'] && ! current_user_can( self::WRITE_CAPABILITY ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit this resource.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Check if the current user can read one item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check the capability of the refused write routes.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function write_permissions_check( $request ) {
		if ( ! current_user_can( self::WRITE_CAPABILITY ) ) {
			return new WP_Error( "ohmylms_{$this->singular}_cannot_edit", __( 'Sorry, you are not allowed to edit this resource.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Refuse a write: the data is managed elsewhere.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error
	 */
	public function replaced( $request ) {
		return new WP_Error(
			'ohmylms_categories_replaced',
			__( 'Course categories were replaced by the curriculum. Manage them under OhMyLMS > Curriculum.', 'ohmylms' ),
			array( 'status' => 410 )
		);
	}

	/**
	 * Read every item as a normalised row (no course counts yet).
	 *
	 * @return array[] Rows with id, name, slug, description and parent.
	 */
	protected function load_rows() {
		if ( ! Schema::ready() ) {
			return array();
		}

		$rows = array();
		foreach ( Items::all() as $row ) {
			$id     = (int) $row['id'];
			$rows[] = array(
				'id'          => $id,
				'name'        => (string) $row['name'],
				'slug'        => Placement::item_slug( $id ),
				'description' => (string) $row['description'],
				'parent'      => (int) $row['parent_id'],
			);
		}
		return $rows;
	}

	/**
	 * Count linked courses for several items with one query.
	 *
	 * @param int[] $ids Item IDs.
	 * @return array<int,int> Counts keyed by ID.
	 */
	protected function count_courses( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT item_id, COUNT(DISTINCT object_id) AS total FROM %i WHERE object_type = 'course' AND item_id IN ($placeholders) GROUP BY item_id", array_merge( array( Links::table() ), $ids ) ), ARRAY_A );

		$counts = array_fill_keys( $ids, 0 );
		foreach ( is_array( $found ) ? $found : array() as $row ) {
			$counts[ (int) $row['item_id'] ] = (int) $row['total'];
		}
		return $counts;
	}

	/**
	 * IDs of the courses linked to one item.
	 *
	 * @param int $id Item ID.
	 * @return int[]
	 */
	protected function course_ids( $id ) {
		return Links::object_ids( array( $id ), 'course' );
	}

	/**
	 * Get a page of items.
	 *
	 * The whole taxonomy is read once (it is curated and small), filtered and sorted in PHP and then
	 * sliced, so only the rows of the requested page cost a count query.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$search   = (string) $request['search'];

		$rows = $this->load_rows();
		if ( '' !== $search ) {
			$rows = array_filter(
				$rows,
				static function ( $row ) use ( $search ) {
					return false !== mb_stripos( $row['name'], $search );
				}
			);
		}
		if ( $this->hierarchical && null !== $request['parent'] ) {
			$parent = (int) $request['parent'];
			$rows   = array_filter(
				$rows,
				static function ( $row ) use ( $parent ) {
					return $row['parent'] === $parent;
				}
			);
		}

		$descending = 'desc' === $request['order'];
		$by_id      = 'id' === $request['orderby'];
		usort(
			$rows,
			static function ( $a, $b ) use ( $descending, $by_id ) {
				$result = $by_id ? $a['id'] <=> $b['id'] : strnatcasecmp( $a['name'], $b['name'] );
				if ( 0 === $result ) {
					$result = $a['id'] <=> $b['id'];
				}
				return $descending ? -$result : $result;
			}
		);

		$total       = count( $rows );
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error( "ohmylms_{$this->singular}_invalid_page", __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$rows   = array_slice( $rows, ( $page - 1 ) * $per_page, $per_page );
		$counts = $this->count_courses( wp_list_pluck( $rows, 'id' ) );

		$data = array();
		foreach ( $rows as $row ) {
			$row['count'] = $counts[ $row['id'] ] ?? 0;
			$data[]       = $this->prepare_response_for_collection( $this->prepare_item_for_response( $row, $request ) );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $total_pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}
		return $response;
	}

	/**
	 * Get one item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id = absint( $request['id'] );
		foreach ( $this->load_rows() as $row ) {
			if ( $row['id'] === $id ) {
				$row['count'] = ( $this->count_courses( array( $id ) )[ $id ] ?? 0 );
				return $this->prepare_item_for_response( $row, $request );
			}
		}
		return new WP_Error( "ohmylms_{$this->singular}_not_found", __( 'Item not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Matches the REST schema: turn a row into a response.
	 *
	 * @param array           $item    Row from load_rows() plus `count`.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = array(
			'id'          => $item['id'],
			'name'        => $item['name'],
			'slug'        => $item['slug'],
			'description' => $item['description'],
			'parent'      => $this->hierarchical ? $item['parent'] : 0,
			'count'       => $item['count'],
		);

		if ( 'edit' === $context ) {
			$courses = array();
			foreach ( array_slice( $this->course_ids( $item['id'] ), 0, self::COURSE_LIMIT ) as $course_id ) {
				$courses[] = array(
					'id'    => (int) $course_id,
					'title' => html_entity_decode( get_the_title( $course_id ), ENT_QUOTES, 'UTF-8' ),
				);
			}
			$data['courses'] = $courses;
		}

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( $this->namespace . '/' . $this->rest_base . '/' . $item['id'] ) ),
				'collection' => array( 'href' => rest_url( $this->namespace . '/' . $this->rest_base ) ),
			)
		);
		return $response;
	}

	/**
	 * Get the item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->singular,
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier (a curriculum item or bundle ID).', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'name'        => array(
					'description' => __( 'Display name.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'slug'        => array(
					'description' => __( 'Synthetic filter slug used by public course filters.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'description' => array(
					'description' => __( 'Description.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'parent'      => array(
					'description' => __( 'Parent item ID, 0 for top level (always 0 for bundles).', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'count'       => array(
					'description' => __( 'Number of linked courses.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'courses'     => array(
					'description' => __( 'Linked courses (at most 100, edit context only).', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array( 'type' => 'integer' ),
							'title' => array( 'type' => 'string' ),
						),
					),
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Get the collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page', 'search' ) ) );

		$params['per_page']['default']   = 20;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to items whose name contains the string.', 'ohmylms' );
		$params['search']['maxLength']   = 100;

		if ( $this->hierarchical ) {
			$params['parent'] = array(
				'description' => __( 'Limit results to the children of one item; 0 for top level.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 0,
			);
		}

		$params['orderby'] = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'name',
			'enum'        => array( 'name', 'id' ),
		);

		$params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'asc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}
}
