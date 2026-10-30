<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Validated boundary for the authored syllabus editor.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Curriculum\SyllabusCourse;

defined( 'ABSPATH' ) || exit;

/** Reuse the curriculum domain services; expose only the operations this editor owns. */
class SyllabusEditorController extends \WP_REST_Controller {

	/** Register the bounded read and command endpoints. */
	public function register_routes() {
		$base = '/authoring/syllabuses/(?P<id>\d+)';
		register_rest_route(
			'ohmylms/v1',
			$base,
			array(
				'methods'             => 'GET',
				'permission_callback' => array( Access::class, 'admin' ),
				'callback'            => array( $this, 'get_item' ),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			$base . '/commands',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( Access::class, 'admin' ),
				'callback'            => array( $this, 'command' ),
				'args'                => array(
					'operation' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array_keys( $this->schemas() ),
					),
				),
			)
		);
	}

	/**
	 * Describe a syllabus without creating or synchronizing a course on GET.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function get_item( $request ) {
		return $this->snapshot( (int) $request['id'] );
	}

	/**
	 * Read the explicit syllabus and course relationship.
	 *
	 * @param int $syllabus_id Syllabus ID.
	 * @return array|\WP_Error
	 */
	private function snapshot( $syllabus_id ) {
		$outline = Syllabus::outline( $syllabus_id );
		return is_wp_error( $outline ) ? $outline : SyllabusCourse::decorate( $outline, $syllabus_id ) + array( 'course' => SyllabusCourse::summary( $syllabus_id ) );
	}

	/**
	 * Command schemas reject unexpected fields before any domain service writes.
	 *
	 * @return array
	 */
	private function schemas() {
		$id                                = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		$position                          = array(
			'type'    => 'integer',
			'minimum' => 0,
			'maximum' => 5000,
		);
		$fields                            = array(
			'name'        => array(
				'type'      => 'string',
				'minLength' => 1,
				'maxLength' => 190,
			),
			'code'        => array(
				'type'      => 'string',
				'maxLength' => 40,
			),
			'description' => array(
				'type'      => 'string',
				'maxLength' => 2000,
			),
		);
		$group_fields                      = $fields;
		$group_fields['code']['maxLength'] = 60;
		return array(
			'add_group'    => array(
				'fields'   => $group_fields + array( 'item_id' => $id ),
				'required' => array( 'name', 'item_id' ),
			),
			'edit_group'   => array(
				'fields'   => $group_fields + array( 'group_id' => $id ),
				'required' => array( 'group_id', 'name' ),
			),
			'move_group'   => array(
				'fields'   => array(
					'group_id' => $id,
					'item_id'  => $id,
					'position' => $position,
				),
				'required' => array( 'group_id', 'item_id', 'position' ),
			),
			'create_skill' => array(
				'fields'   => $fields + array( 'group_id' => $id ),
				'required' => array( 'group_id', 'name' ),
			),
			'edit_skill'   => array(
				'fields'   => $fields + array( 'term_id' => $id ),
				'required' => array( 'term_id', 'name' ),
			),
			'attach_skill' => array(
				'fields'   => array(
					'group_id' => $id,
					'term_id'  => $id,
				),
				'required' => array( 'group_id', 'term_id' ),
			),
			'move_skill'   => array(
				'fields'   => array(
					'group_id'    => $id,
					'term_id'     => $id,
					'destination' => $id,
					'position'    => $position,
				),
				'required' => array( 'group_id', 'term_id', 'destination', 'position' ),
			),
			'detach_skill' => array(
				'fields'   => array(
					'group_id' => $id,
					'term_id'  => $id,
				),
				'required' => array( 'group_id', 'term_id' ),
			),
			'sync_course'  => array(
				'fields'   => array(),
				'required' => array(),
			),
			'import'       => array(
				'fields'   => array(
					'rows'            => array(
						'type'     => 'array',
						'maxItems' => 5000,
						'minItems' => 1,
						'items'    => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => array( 'string', 'integer' ) ),
						),
					),
					'dry_run'         => array( 'type' => 'boolean' ),
					'review_revision' => array(
						'type'      => 'string',
						'maxLength' => 64,
					),
				),
				'required' => array( 'rows', 'dry_run' ),
			),
		);
	}

	/**
	 * Validate and apply one named command. Domain services enforce ownership again.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function command( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || strlen( wp_json_encode( $data ) ) > 5242880 ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'Send a JSON command of at most 5 MB.', 'ohmylms' ) );
		}
		$operation = $data['operation'] ?? '';
		$schema    = $this->schemas()[ $operation ] ?? null;
		if ( ! $schema ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'Unknown syllabus command.', 'ohmylms' ) );
		}
		unset( $data['operation'] );
		$valid = rest_validate_value_from_schema(
			$data,
			array(
				'type'                 => 'object',
				'properties'           => $schema['fields'],
				'required'             => $schema['required'],
				'additionalProperties' => false,
			),
			'command'
		);
		if ( is_wp_error( $valid ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', $valid->get_error_message(), 400 );
		}
		$id      = (int) $request['id'];
		$outline = Syllabus::outline( $id );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}
		switch ( $operation ) {
			case 'add_group':
				$result = Syllabus::add_group( $id, $data );
				break;
			case 'edit_group':
				$result = Syllabus::update_group( $id, $data['group_id'], $data );
				break;
			case 'move_group':
				$result = Syllabus::move_group( $id, $data['group_id'], $data['item_id'], $data['position'] );
				break;
			case 'create_skill':
				$result = Syllabus::create_skill( $id, $data['group_id'], $data );
				break;
			case 'edit_skill':
				$result = Syllabus::update_skill( $id, $data['term_id'], $data );
				break;
			case 'attach_skill':
				$result = Syllabus::add_skill( $id, $data['group_id'], $data['term_id'] );
				break;
			case 'move_skill':
				$result = Syllabus::move_skill( $id, $data['group_id'], $data['term_id'], $data['destination'], $data['position'] );
				break;
			case 'detach_skill':
				$result = Syllabus::remove_skill( $id, $data['group_id'], $data['term_id'] );
				break;
			case 'import':
				$result = $this->import_rows( $id, $data );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( $data['dry_run'] || ! $result['applied'] ) {
					return array( 'report' => $result );
				}
				$report = $result;
				break;
			default:
				$result = SyllabusCourse::ensure( $id );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$synced   = SyllabusCourse::sync_quietly( $id );
		$snapshot = $this->snapshot( $id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		if ( isset( $report ) ) {
			$snapshot['report'] = $report;
		}
		if ( is_wp_error( $synced ) ) {
			$snapshot['course_error'] = $synced->get_error_message();
		}
		return $snapshot;
	}

	/**
	 * Review and apply against the same syllabus state under the domain's named lock.
	 * The domain import owns its transaction; do not wrap its caught failures in another transaction.
	 *
	 * @param int   $syllabus_id Syllabus ID.
	 * @param array $data Validated rows, dry-run flag and optional reviewed revision.
	 * @return array|\WP_Error
	 */
	private function import_rows( $syllabus_id, array $data ) {
		global $wpdb;
		$lock = Items::LOCK . '-' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Non-cacheable domain advisory lock.
			return Access::error( 'ohmylms_syllabus_busy', __( 'Another author is changing the syllabus. Try again.', 'ohmylms' ), 409 );
		}
		try {
			$outline = Syllabus::outline( $syllabus_id );
			if ( is_wp_error( $outline ) ) {
				return $outline; }
			$revision = hash( 'sha256', wp_json_encode( array( $outline, $data['rows'] ) ) );
			if ( ! $data['dry_run'] && ! hash_equals( $revision, $data['review_revision'] ?? '' ) ) {
				return Access::error( 'ohmylms_syllabus_review_stale', __( 'The syllabus or import rows changed. Review the import again before applying it.', 'ohmylms' ), 409 );
			}
			$report = Syllabus::import( $syllabus_id, $data['rows'], $data['dry_run'] );
			if ( ! is_wp_error( $report ) ) {
				$report['review_revision'] = $revision; }
			return $report;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the non-cacheable domain advisory lock.
		}
	}
}
