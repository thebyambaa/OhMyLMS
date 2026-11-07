<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Content Bank folders and tags.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\ContentBank\Organization;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/content-bank/*`: read and create folders and tags, see which each item has, and assign them
 * to many items at once. Lists of lessons, quizzes, exams, assignments and questions are narrowed with the
 * `bank_folder` and `bank_tag` parameters (see {@see Organization}).
 */
class ContentBankController extends RestController {

	/**
	 * Most items one request may organize.
	 */
	const MAX_ITEMS = 100;

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/content-bank/terms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'terms' ),
					'permission_callback' => array( $this, 'permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_term' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'kind'   => array(
							'type'     => 'string',
							'enum'     => array( 'folder', 'tag' ),
							'required' => true,
						),
						'name'   => array(
							'type'     => 'string',
							'required' => true,
						),
						'parent' => array( 'type' => 'integer' ),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/content-bank/assignments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'assignments' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'ids' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/content-bank/organize',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'organize' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'ids'        => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
							'required' => true,
						),
						'folder_ids' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'tag_ids'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'mode'       => array(
							'type'    => 'string',
							'enum'    => array( 'add', 'replace', 'remove' ),
							'default' => 'add',
						),
					),
				),
			)
		);
	}

	/**
	 * Content authors may organize; each item is still checked against its own edit capability.
	 *
	 * @return true|WP_Error
	 */
	public function permission() {
		return current_user_can( 'edit_posts' ) ? true : new WP_Error( 'ohmylms_rest_forbidden', __( 'Sorry, you are not allowed to manage content.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Every folder and tag.
	 *
	 * @return WP_REST_Response
	 */
	public function terms() {
		return rest_ensure_response(
			array(
				'folders' => $this->list_terms( Organization::FOLDER ),
				'tags'    => $this->list_terms( Organization::TAG ),
			)
		);
	}

	/**
	 * Terms of one taxonomy, by name.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array[]
	 */
	private function list_terms( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$rows  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$rows[] = array(
				'id'     => (int) $term->term_id,
				'name'   => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'parent' => (int) $term->parent,
			);
		}
		return $rows;
	}

	/**
	 * Create a folder (optionally inside another) or a tag.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_term( WP_REST_Request $request ) {
		$folder = 'folder' === $request['kind'];
		$name   = trim( sanitize_text_field( (string) $request['name'] ) );
		if ( '' === $name ) {
			return new WP_Error( 'ohmylms_bank_name', __( 'Enter a name.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$parent = $folder ? absint( $request['parent'] ) : 0;
		if ( $parent && ! term_exists( $parent, Organization::FOLDER ) ) {
			return new WP_Error( 'ohmylms_bank_parent', __( 'That folder does not exist.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$taxonomy = $folder ? Organization::FOLDER : Organization::TAG;
		$created  = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
		if ( is_wp_error( $created ) ) {
			$created->add_data( array( 'status' => 400 ) );
			return $created;
		}
		$response = rest_ensure_response(
			array(
				'id'     => (int) $created['term_id'],
				'name'   => $name,
				'parent' => $parent,
			)
		);
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Folder and tag ids of each requested item the user may read.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function assignments( WP_REST_Request $request ) {
		$out = array();
		foreach ( $this->ids( (array) $request['ids'] ) as $id ) {
			if ( ! $this->organizable( $id ) || ! current_user_can( 'read_post', $id ) ) {
				continue;
			}
			$out[ $id ] = array(
				'folders' => $this->term_ids( $id, Organization::FOLDER ),
				'tags'    => $this->term_ids( $id, Organization::TAG ),
			);
		}
		return rest_ensure_response( (object) $out );
	}

	/**
	 * Add, replace or remove folders and tags on many items. Items the user cannot edit are skipped, not fatal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function organize( WP_REST_Request $request ) {
		$ids = $this->ids( (array) $request['ids'] );
		if ( ! $ids || count( $ids ) > self::MAX_ITEMS ) {
			return new WP_Error( 'ohmylms_bank_ids', __( 'Choose between 1 and 100 items.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$sets = array();
		foreach ( array(
			'folder_ids' => Organization::FOLDER,
			'tag_ids'    => Organization::TAG,
		) as $param => $taxonomy ) {
			if ( ! $request->has_param( $param ) ) {
				continue;
			}
			$terms = $this->ids( (array) $request[ $param ] );
			foreach ( $terms as $term ) {
				if ( ! term_exists( $term, $taxonomy ) ) {
					return new WP_Error( 'ohmylms_bank_term', __( 'A folder or tag does not exist.', 'ohmylms' ), array( 'status' => 400 ) );
				}
			}
			$sets[ $taxonomy ] = $terms;
		}
		if ( ! $sets ) {
			return new WP_Error( 'ohmylms_bank_nothing', __( 'Choose folders or tags to apply.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$updated = array();
		$skipped = array();
		foreach ( $ids as $id ) {
			if ( ! $this->organizable( $id ) ) {
				$skipped[] = array(
					'id'     => $id,
					'reason' => 'invalid',
				);
				continue;
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				$skipped[] = array(
					'id'     => $id,
					'reason' => 'forbidden',
				);
				continue;
			}
			foreach ( $sets as $taxonomy => $terms ) {
				if ( 'remove' === $request['mode'] ) {
					wp_remove_object_terms( $id, $terms, $taxonomy );
				} else {
					wp_set_object_terms( $id, $terms, $taxonomy, 'add' === $request['mode'] );
				}
			}
			$updated[] = $id;
		}
		return rest_ensure_response(
			array(
				'updated' => $updated,
				'skipped' => $skipped,
			)
		);
	}

	/**
	 * Unique positive integers, in order.
	 *
	 * @param array $values Raw values.
	 * @return int[]
	 */
	private function ids( array $values ) {
		return array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
	}

	/**
	 * Whether a post is content the Content Bank organizes.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	private function organizable( $id ) {
		return in_array( get_post_type( $id ), array_values( Organization::types() ), true );
	}

	/**
	 * Term ids an item has in a taxonomy.
	 *
	 * @param int    $id       Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]
	 */
	private function term_ids( $id, $taxonomy ) {
		$ids = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}
}
