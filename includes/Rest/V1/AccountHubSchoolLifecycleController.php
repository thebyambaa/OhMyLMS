<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubSchoolLifecycleController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Schools\Rollover;
use OhMyLMS\Schools\SchoolRemoval;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * School removal and year rollover under `ohmylms/v1/account-hub/schools/{id}` (docs/api/account-hub.md,
 * "School removal" and "Year rollover"). Archiving, restoring and deleting a school are for platform
 * administrators only: a school administrator would lose access to the school they archive. Rollover is
 * for anyone who manages the school. Domain rules live in `Schools\SchoolRemoval` and `Schools\Rollover`.
 *
 * @since 1.0.0
 */
class AccountHubSchoolLifecycleController extends AccountHubController {

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base  = '/' . $this->rest_base . '/schools/(?P<id>[\d]+)';
		$id    = array(
			'description' => __( 'Unique identifier.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$admin = array( $this, 'remove_permissions_check' );

		foreach ( array( 'archive', 'restore' ) as $action ) {
			register_rest_route(
				$this->namespace,
				$base . '/' . $action,
				array(
					'args' => array( 'id' => $id ),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $action . '_school' ),
						'permission_callback' => $admin,
					),
				)
			);
		}

		register_rest_route(
			$this->namespace,
			$base . '/removal',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_removal' ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base,
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_school' ),
					'permission_callback' => $admin,
					'args'                => array(
						'confirm' => array(
							'description' => __( 'The exact school name, typed to confirm the permanent deletion.', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
							'maxLength'   => 190,
						),
					),
				),
			)
		);

		$rollover = array(
			'from_year_id'  => array_merge(
				$id,
				array(
					'description' => __( 'Academic year whose active classes move.', 'ohmylms' ),
					'required'    => true,
				)
			),
			'to_year_id'    => array_merge(
				$id,
				array(
					'description' => __( 'Active academic year that receives the new classes.', 'ohmylms' ),
					'required'    => true,
				)
			),
			'grade_map'     => array(
				'description' => __( 'What each grade becomes; an empty `to` means the class leaves (graduates). Unlisted grades stay the same.', 'ohmylms' ),
				'type'        => 'array',
				'default'     => array(),
				'maxItems'    => 100,
				'items'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'from' => array(
							'type'      => 'string',
							'maxLength' => 50,
						),
						'to'   => array(
							'type'      => 'string',
							'maxLength' => 50,
						),
					),
					'required'             => array( 'from', 'to' ),
					'additionalProperties' => false,
				),
			),
			'copy_teachers' => array(
				'description' => __( 'Also add each class\'s teachers to its new class.', 'ohmylms' ),
				'type'        => 'boolean',
				'default'     => false,
			),
		);

		register_rest_route(
			$this->namespace,
			$base . '/rollover/preview',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'preview_rollover' ),
					'permission_callback' => array( $this, 'edit_permissions_check' ),
					'args'                => $rollover,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/rollover',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_rollover' ),
					'permission_callback' => array( $this, 'edit_permissions_check' ),
					'args'                => array_merge(
						$rollover,
						array(
							'review' => array(
								'description' => __( 'Review token of the preview being applied.', 'ohmylms' ),
								'type'        => 'string',
								'required'    => true,
								'pattern'     => '^[a-f0-9]{64}$',
							),
						)
					),
				),
			)
		);
	}

	/**
	 * Only platform administrators archive, restore or delete schools.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function remove_permissions_check( $request ) {
		if ( current_user_can( self::CAPABILITY ) ) {
			return true;
		}

		return new WP_Error( 'ohmylms_account_hub_cannot_remove', __( 'Sorry, only site administrators can archive or delete schools.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Private response.
	 *
	 * @param array $data Body.
	 * @return WP_REST_Response
	 */
	private function no_store( array $data ) {
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Translate a removal or rollover exception.
	 *
	 * @param \Throwable $failure Thrown error.
	 * @return WP_Error
	 */
	private function lifecycle_error( \Throwable $failure ) {
		$code = $failure instanceof \RuntimeException ? (int) $failure->getCode() : 500;
		switch ( $code ) {
			case 404:
				return $this->error( 'school_not_found', __( 'School not found.', 'ohmylms' ), 404 );
			case 409:
				return $this->error( 'state_conflict', $failure->getMessage(), 409 );
			case 412:
				return $this->error( 'review_stale', $failure->getMessage(), 409 );
			case 422:
				return $this->error( 'invalid_year', $failure->getMessage(), 422 );
			case 423:
				return $this->error( 'rollover_busy', $failure->getMessage(), 409 );
		}

		return $this->service_error( $failure );
	}

	/**
	 * Archive a school (reversible).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function archive_school( $request ) {
		return $this->set_archived( $request, true );
	}

	/**
	 * Restore an archived school.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_school( $request ) {
		return $this->set_archived( $request, false );
	}

	/**
	 * Archive or restore.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param bool            $archive True to archive.
	 * @return WP_REST_Response|WP_Error
	 */
	private function set_archived( $request, $archive ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		if ( $archive && 'active' !== $school['status'] ) {
			return $this->error( 'school_archived', __( 'This school is already archived.', 'ohmylms' ), 409 );
		}
		if ( ! $archive && 'archived' !== $school['status'] ) {
			return $this->error( 'school_active', __( 'This school is not archived.', 'ohmylms' ), 409 );
		}
		try {
			SchoolRemoval::set_archived( (int) $school['id'], $archive );
		} catch ( \Throwable $failure ) {
			return $this->lifecycle_error( $failure );
		}

		return rest_ensure_response( $this->school_detail( (int) $school['id'] ) );
	}

	/**
	 * What a permanent deletion would remove.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_removal( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}

		return $this->no_store(
			array(
				'id'         => (int) $school['id'],
				'name'       => (string) $school['name'],
				'status'     => (string) $school['status'],
				'can_delete' => 'archived' === $school['status'],
				'confirm'    => (string) $school['name'],
				'impact'     => SchoolRemoval::impact( (int) $school['id'] ),
			)
		);
	}

	/**
	 * Permanently delete an archived school after the name was typed. Accounts are never deleted.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_school( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		if ( 'archived' !== $school['status'] ) {
			return $this->error( 'school_active', __( 'Archive the school before deleting it.', 'ohmylms' ), 409 );
		}
		if ( trim( (string) $request['confirm'] ) !== (string) $school['name'] ) {
			return $this->error( 'confirmation_mismatch', __( 'Type the school name exactly to delete it.', 'ohmylms' ), 422 );
		}
		try {
			$removed = SchoolRemoval::delete( (int) $school['id'] );
		} catch ( \Throwable $failure ) {
			return $this->lifecycle_error( $failure );
		}

		return $this->no_store(
			array(
				'deleted' => true,
				'id'      => (int) $school['id'],
				'removed' => $removed,
			)
		);
	}

	/**
	 * Load the school and the rollover inputs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error School row, grade map and teacher flag.
	 */
	private function rollover_input( $request ) {
		$school = $this->writable_school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}

		return array( $school, Rollover::grade_map( (array) $request['grade_map'] ), (bool) $request['copy_teachers'] );
	}

	/**
	 * Preview a rollover: nothing is written; the answer carries the review token to apply it.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_rollover( $request ) {
		$input = $this->rollover_input( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		list( $school, $map, $teachers ) = $input;
		try {
			$plan = Rollover::plan( (int) $school['id'], (int) $request['from_year_id'], (int) $request['to_year_id'], $map, $teachers );
		} catch ( \Throwable $failure ) {
			return $this->lifecycle_error( $failure );
		}

		return $this->no_store( array_merge( $plan, array( 'review' => Rollover::review( (int) $school['id'], $plan ) ) ) );
	}

	/**
	 * Apply a reviewed rollover. Repeating the same review answers the first result.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_rollover( $request ) {
		$input = $this->rollover_input( $request );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		list( $school, $map, $teachers ) = $input;
		try {
			$result = Rollover::apply( (int) $school['id'], (int) $request['from_year_id'], (int) $request['to_year_id'], $map, $teachers, (string) $request['review'] );
		} catch ( \Throwable $failure ) {
			return $this->lifecycle_error( $failure );
		}

		return $this->no_store( $result );
	}
}
