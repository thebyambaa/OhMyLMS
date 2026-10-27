<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MCP schemas for course targets backed by the course editor's REST service.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP;

defined( 'ABSPATH' ) || exit;

/** Explicit course target operations, independent of mastery and learning requirements. */
final class CourseObjectiveTools {

	/**
	 * Return bounded schemas for discovery by the dedicated LMS MCP server.
	 *
	 * @return array
	 */
	public static function definitions() {
		$id       = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		$read     = array(
			'id'       => $id,
			'page'     => $id,
			'per_page' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 50,
			),
		);
		$common   = array(
			'route'       => '/courses/{id}/syllabus-objectives',
			// Query defaults also bound the response to a successful write.
			'list'        => true,
			'properties'  => $read,
			'required'    => array( 'id' ),
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
		$options  = $read + array(
			'syllabus_id' => $id,
			'search'      => array(
				'type'      => 'string',
				'maxLength' => 200,
			),
		);
		$mappings = array(
			'type'     => 'array',
			'maxItems' => 200,
			'items'    => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'syllabus_id', 'term_id' ),
				'properties'           => array(
					'syllabus_id' => $id,
					'term_id'     => $id,
				),
			),
		);
		return array(
			'get_course_objectives'         => array(
				'method'      => 'GET',
				'description' => 'Read course target objectives, existing content connections, available syllabuses and the mapping revision. Paginated, 10 per page by default. Read every page before replacing mappings. Connections are not verified suitability or student achievement.',
			) + $common,
			'list_course_objective_options' => array(
				'method'      => 'GET',
				'description' => 'Browse existing official objectives in a syllabus for mapping to this course. Search codes, titles, full objective wording or topics. Returns stable syllabus_id and term_id references. Does not edit the syllabus.',
				'properties'  => $options,
				'required'    => array( 'id', 'syllabus_id' ),
			) + $common,
			'set_course_objectives'         => array(
				'method'      => 'PUT',
				'description' => 'Replace ALL target objective mappings for a course using the revision from get_course_objectives. Read all mapping pages and retain every reference you want to keep. Omitted references are removed; an empty mappings list clears targets. A stale revision fails with HTTP 409: reload and reconcile. Saves separately from course content; does not change official objectives, learning requirements or achievement. Returns the first 10 saved mappings, totals and the new revision.',
				'properties'  => array(
					'id'       => $id,
					'revision' => array(
						'type'    => 'string',
						'pattern' => '^[a-f0-9]{32}$',
					),
					'mappings' => $mappings,
				),
				'required'    => array( 'id', 'revision', 'mappings' ),
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			) + $common,
		);
	}
}
