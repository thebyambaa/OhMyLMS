<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Base class of the gamification REST controllers.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Engagement\Rest;

use OhMyLMS\Abstracts\RestController;
use WP_Error;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Shared capability check, error helper, pagination and award counts.
 *
 * @since 1.0.0
 */
abstract class GamificationController extends RestController {

	/**
	 * Capability required by every gamification route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Allow only users with CAPABILITY. Anonymous callers get 401, others 403.
	 * Cookie requests also need the core REST nonce, which WordPress verifies first.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permissions_check( $request ) {
		unset( $request );
		if ( current_user_can( self::CAPABILITY ) ) {
			return true;
		}
		return new WP_Error(
			'ohmylms_gamification_forbidden',
			__( 'Sorry, you are not allowed to manage gamification.', 'ohmylms' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Permission check for listing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->permissions_check( $request );
	}

	/**
	 * Permission check for reading one item.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->permissions_check( $request );
	}

	/**
	 * Permission check for creating.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->permissions_check( $request );
	}

	/**
	 * Permission check for updating.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->permissions_check( $request );
	}

	/**
	 * Permission check for deleting.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->permissions_check( $request );
	}

	/**
	 * Page an in-memory list and set the standard pagination headers.
	 *
	 * @param array            $items   Prepared items.
	 * @param \WP_REST_Request $request Request with `page` and `per_page`.
	 * @param string           $route   Route for Link headers.
	 * @return WP_REST_Response|WP_Error
	 */
	protected function paginate( array $items, $request, $route ) {
		$page     = (int) $request['page'];
		$per_page = (int) $request['per_page'];
		$total    = count( $items );
		$pages    = (int) ceil( $total / $per_page );
		if ( $page > max( 1, $pages ) ) {
			return new WP_Error( 'ohmylms_gamification_invalid_page', __( 'The page number is past the last page.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$response = rest_ensure_response( array_slice( $items, ( $page - 1 ) * $per_page, $per_page ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		$base = rest_url( $this->namespace . '/' . $route );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}
		return $response;
	}

	/**
	 * Pagination arguments shared by the collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'ohmylms' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items per page.', 'ohmylms' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Number of distinct learners holding each badge or level.
	 *
	 * One grouped, bounded query for the whole page of results, never one per item.
	 *
	 * @param string $type Achievement type: `badge` or `level`.
	 * @return array<string,int> Slug => learners.
	 */
	protected function earned_counts( $type ) {
		global $wpdb;
		$column = 'level' === $type ? 'level_id' : 'badge_id';
		$rows   = $wpdb->get_results(
			$wpdb->prepare( 'SELECT %i AS slug, COUNT(DISTINCT user_id) AS learners FROM %i WHERE type = %s AND status = %s AND %i IS NOT NULL GROUP BY %i LIMIT 500', $column, $wpdb->prefix . 'ohmylms_user_achievement', $type, 'active', $column, $column )
		);
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row->slug ] = (int) $row->learners;
		}
		return $counts;
	}
}
