<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubSampleSchoolController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Schools\SamplePreview;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * The sample school at `ohmylms/v1/account-hub/sample-school` (docs/api/account-hub.md, "Sample school"):
 * read its state, create or complete it, remove it. Platform administrators only. `Schools\SamplePreview`
 * owns the rules: only marked records are created or removed, and removal is refused while real people
 * or activity are attached. Replaces `ohmylms/v1/school/sample-preview*`.
 *
 * @since 1.0.0
 */
class AccountHubSampleSchoolController extends AccountHubController {

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$admin = array( $this, 'sample_permissions_check' );
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sample-school',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sample' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_sample' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_sample' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/**
	 * Platform administrators only.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function sample_permissions_check( $request ) {
		if ( current_user_can( self::CAPABILITY ) ) {
			return true;
		}

		return new WP_Error( 'ohmylms_account_hub_cannot_sample', __( 'Sorry, only site administrators can manage the sample school.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Public state: what exists and what removal would do.
	 *
	 * @return array
	 */
	private function state() {
		$status  = SamplePreview::status();
		$removal = SamplePreview::remove( false );
		$people  = array();
		$names   = SamplePreview::definitions()['people'];
		foreach ( $status['people'] as $key => $person ) {
			$people[] = array(
				'key'          => (string) $key,
				'id'           => (int) $person['id'],
				'display_name' => (string) $person['name'],
				'role'         => '' !== $names[ $key ]['member'] ? (string) $names[ $key ]['member'] : 'parent',
			);
		}
		$classes = array();
		foreach ( $status['classes'] as $key => $id ) {
			$classes[] = array(
				'key'  => (string) $key,
				'id'   => (int) $id,
				'name' => (string) SamplePreview::definitions()['classes'][ $key ]['name'],
			);
		}

		return array(
			'exists'    => (bool) $status['exists'],
			'complete'  => (bool) $status['complete'],
			'school_id' => (int) $status['school_id'],
			'classes'   => $classes,
			'people'    => $people,
			'removal'   => array(
				'allowed'  => (bool) $removal['allowed'],
				'remove'   => array_values( $removal['remove'] ),
				'skip'     => array_values( $removal['skip'] ),
				'blockers' => array_values( $removal['blockers'] ),
			),
		);
	}

	/**
	 * Private response.
	 *
	 * @param array $data   Body.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	private function no_store( array $data, $status = 200 ) {
		$response = rest_ensure_response( $data );
		$response->set_status( $status );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Read the sample school state.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_sample( $request ) {
		try {
			return $this->no_store( $this->state() );
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
	}

	/**
	 * Create or complete the sample school. Repeating it changes nothing.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_sample( $request ) {
		global $wpdb;
		$lock = 'ohmylms_sample_school';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Two clicks must not create two sets.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			return $this->error( 'sample_busy', __( 'The sample school is being created. Try again in a moment.', 'ohmylms' ), 409 );
		}
		try {
			$before = SamplePreview::status()['complete'];
			SamplePreview::create();
			$state = $this->state();
		} catch ( \Throwable $failure ) {
			$message = $failure instanceof \RuntimeException && 400 === (int) $failure->getCode() ? $failure->getMessage() : '';
			return '' !== $message ? $this->error( 'sample_conflict', $message, 409 ) : $this->service_error( $failure );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the lock.
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}

		return $this->no_store( $state, $before ? 200 : 201 );
	}

	/**
	 * Remove every marked sample record in one step; refused while real people or activity are attached.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_sample( $request ) {
		try {
			$result = SamplePreview::remove( true );
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		if ( ! $result['removed'] ) {
			if ( $result['blockers'] ) {
				return $this->error( 'sample_in_use', __( 'The sample school is in use and was not removed.', 'ohmylms' ), 409, array( 'blockers' => array_values( $result['blockers'] ) ) );
			}

			return $this->error( 'sample_not_found', __( 'There is no sample school to remove.', 'ohmylms' ), 404 );
		}

		return $this->no_store(
			array(
				'removed' => true,
				'remove'  => array_values( $result['remove'] ),
			)
		);
	}
}
