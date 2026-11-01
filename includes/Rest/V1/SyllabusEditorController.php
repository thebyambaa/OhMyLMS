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
use OhMyLMS\Curriculum\SyllabusSettings;

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
		return is_wp_error( $outline ) ? $outline : SyllabusCourse::decorate( $outline, $syllabus_id ) + array(
			'course'       => SyllabusCourse::summary( $syllabus_id ),
			'settings'     => SyllabusSettings::get( $syllabus_id ),
			'requirements' => SyllabusCourse::requirements( $syllabus_id ),
		);
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
		$profile                           = array(
			'type'      => 'string',
			'maxLength' => 100,
		);
		$group_fields                      = $fields;
		$group_fields['code']['maxLength'] = 60;
		return array(
			'add_group'        => array(
				'fields'   => $group_fields + array( 'item_id' => $id ),
				'required' => array( 'name', 'item_id' ),
			),
			'edit_group'       => array(
				'fields'   => $group_fields + array( 'group_id' => $id ),
				'required' => array( 'group_id', 'name' ),
			),
			'move_group'       => array(
				'fields'   => array(
					'group_id' => $id,
					'item_id'  => $id,
					'position' => $position,
				),
				'required' => array( 'group_id', 'item_id', 'position' ),
			),
			'create_skill'     => array(
				'fields'   => $fields + array( 'group_id' => $id ),
				'required' => array( 'group_id', 'name' ),
			),
			'edit_skill'       => array(
				'fields'   => $fields + array( 'term_id' => $id ),
				'required' => array( 'term_id', 'name' ),
			),
			'attach_skill'     => array(
				'fields'   => array(
					'group_id' => $id,
					'term_id'  => $id,
				),
				'required' => array( 'group_id', 'term_id' ),
			),
			'move_skill'       => array(
				'fields'   => array(
					'group_id'    => $id,
					'term_id'     => $id,
					'destination' => $id,
					'position'    => $position,
				),
				'required' => array( 'group_id', 'term_id', 'destination', 'position' ),
			),
			'detach_skill'     => array(
				'fields'   => array(
					'group_id' => $id,
					'term_id'  => $id,
				),
				'required' => array( 'group_id', 'term_id' ),
			),
			'delete_group'     => array(
				'fields'   => array(
					'group_id' => $id,
					'confirm'  => array( 'type' => 'boolean' ),
				),
				'required' => array( 'group_id' ),
			),
			'edit_source'      => array(
				'fields'   => array(
					'name'            => $fields['name'],
					'code'            => $group_fields['code'],
					'version'         => $group_fields['code'],
					'description'     => $fields['description'],
					'subject'         => $profile,
					'grade'           => $profile,
					'language'        => $profile,
					'categories'      => array(
						'type'     => 'array',
						'maxItems' => 50,
						'items'    => array(
							'type'      => 'string',
							'maxLength' => 60,
						),
					),
					'category_styles' => array(
						'type'                 => 'object',
						'maxProperties'        => 50,
						'additionalProperties' => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'icon'  => array(
									'type'      => 'string',
									'maxLength' => 60,
								),
								'color' => array(
									'type'    => 'string',
									'pattern' => '^#[0-9a-fA-F]{6}$',
								),
							),
						),
					),
				),
				'required' => array(),
			),
			'set_requirements' => array(
				'fields'   => array(
					'skills' => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => Syllabus::MAX_SKILLS,
						'items'    => array(
							'type'                 => 'object',
							'required'             => array( 'term_id' ),
							'additionalProperties' => false,
							'properties'           => array(
								'term_id'  => $id,
								'required' => array( 'type' => 'boolean' ),
								'target'   => array(
									'type' => 'string',
									'enum' => array( 'proficient', 'mastered' ),
								),
							),
						),
					),
				),
				'required' => array( 'skills' ),
			),
			'sync_course'      => array(
				'fields'   => array(),
				'required' => array(),
			),
			'sheet_edit'       => array(
				'fields'   => array(
					'changes' => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => \OhMyLMS\Curriculum\SyllabusSheet::MAX_CHANGES,
						'items'    => array(
							'type'                 => 'object',
							'required'             => array( 'kind', 'id', 'field', 'previous', 'value' ),
							'additionalProperties' => false,
							'properties'           => array(
								'kind'     => array(
									'type' => 'string',
									'enum' => array( 'item', 'group', 'skill' ),
								),
								'id'       => $id,
								'field'    => array(
									'type' => 'string',
									'enum' => array( 'name', 'code' ),
								),
								'previous' => array(
									'type'      => 'string',
									'maxLength' => 400,
								),
								'value'    => array(
									'type'      => 'string',
									'maxLength' => 400,
								),
							),
						),
					),
				),
				'required' => array( 'changes' ),
			),
			'import'           => array(
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
			case 'delete_group':
				$result = Syllabus::delete_group( $id, $data['group_id'], rest_sanitize_boolean( $data['confirm'] ?? false ) );
				break;
			case 'edit_source':
				$result = SyllabusSettings::save_source(
					$id,
					array_intersect_key( $data, array_flip( array( 'name', 'code', 'version', 'description' ) ) ),
					array_intersect_key( $data, array_flip( array( 'subject', 'grade', 'language', 'categories', 'category_styles' ) ) )
				);
				break;
			case 'set_requirements':
				$mine    = Syllabus::skill_ids( Items::with_descendants( $id ) );
				$changes = array();
				foreach ( $data['skills'] as $skill ) {
					if ( ! in_array( $skill['term_id'], $mine, true ) ) {
						return Access::error( 'ohmylms_syllabus_invalid', __( 'Choose skills placed in this syllabus.', 'ohmylms' ), 400 );
					}
					if ( array_key_exists( 'required', $skill ) ) {
						$skill['required'] = rest_sanitize_boolean( $skill['required'] );
					}
					$changes[] = $skill;
				}
				$result = SyllabusCourse::set_requirements( $id, $changes );
				break;
			case 'sheet_edit':
				$result = \OhMyLMS\Curriculum\SyllabusSheet::apply( $id, $data['changes'] );
				break;
			case 'import':
				$result = Syllabus::import_reviewed( $id, $data['rows'], $data['dry_run'], $data['review_revision'] ?? '' );
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
}
