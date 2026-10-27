<?php // phpcs:ignore WordPress.Files.FileName -- Existing PSR-4 class/file mapping.
/**
 * Small, explicit assistant tool surface backed by the existing MCP server.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

use OhMyLMS\MCP\Server;

defined( 'ABSPATH' ) || exit;

/** Creation is draft-only; publication, deletion, enrolment and grading are unavailable. */
final class AssistantTools {
	/**
	 * Narrow existing MCP schemas; no arbitrary routes or schema extensions.
	 *
	 * @return array
	 */
	public static function definitions() {
		$selection = array(
			'list_courses'             => array( 'search', 'page', 'per_page' ),
			'get_course'               => array( 'id' ),
			'get_course_chapters'      => array( 'id' ),
			'list_lessons'             => array( 'search', 'page', 'per_page' ),
			'get_lesson_for_edit'      => array( 'id' ),
			'list_syllabuses'          => array(),
			'list_syllabus_objectives' => array( 'id', 'page', 'search' ),
			'list_shared_skills'       => array( 'search', 'subject', 'page' ),
			'get_shared_skill'         => array( 'id' ),
			'save_lesson'              => array( 'name', 'content' ),
			'save_shared_skill'        => array( 'name', 'description', 'subject', 'topic' ),
			// Mixed-skill assessments: find skills, see a unit's skills, propose a draft quiz or unit test.
			'search_skill_catalogue'   => array( 'search', 'page' ),
			'list_unit_skills'         => array( 'chapter_id' ),
			'create_skill_assessment'  => array( 'title', 'style', 'skills', 'per_skill', 'chapter_id', 'time_limit' ),
		);
		$available = Server::tools();
		$tools     = array();
		foreach ( $selection as $name => $fields ) {
			if ( ! isset( $available[ $name ] ) ) {
				continue;
			}
			$definition               = $available[ $name ];
			$definition['properties'] = array_intersect_key( $definition['properties'], array_flip( $fields ) );
			$definition['required']   = $fields;
			$definition['write']      = in_array( $name, array( 'save_lesson', 'save_shared_skill', 'create_skill_assessment' ), true );
			if ( 'create_skill_assessment' === $name ) {
				$definition['description'] = 'Propose a NEW DRAFT quiz, unit test or course challenge that draws existing approved questions for the chosen skills; administrator review is required and it is never published here. ' . $definition['description'];
			} elseif ( $definition['write'] ) {
				$definition['description'] = 'Propose creating a NEW draft lesson or NEW shared skill; administrator review is required. Existing IDs, edits, placement and publication are not allowed. ' . $definition['description'];
			}
			if ( isset( $definition['properties']['content'] ) ) {
				$definition['properties']['content']['maxLength'] = 20000;
			}
			$tools[ $name ] = $definition;
		}
		return $tools;
	}

	/**
	 * Validate all model-generated arguments again on the server.
	 *
	 * @param string $name Tool name.
	 * @param mixed  $arguments Model arguments.
	 * @return true|\WP_Error
	 */
	public static function validate( $name, $arguments ) {
		$tools = self::definitions();
		if ( ! isset( $tools[ $name ] ) || ! is_array( $arguments ) ) {
			return new \WP_Error( 'ohmylms_assistant_tool_denied', __( 'This assistant action is not supported.', 'ohmylms' ) );
		}
		return rest_validate_value_from_schema( $arguments, self::schema( $tools[ $name ] ), 'arguments' );
	}

	/**
	 * Strict schemas keep optional existing MCP arguments outside the first version.
	 *
	 * @param array $tool Selected definition.
	 * @return array
	 */
	private static function schema( array $tool ) {
		return array(
			'type'                 => 'object',
			'properties'           => $tool['properties'],
			'required'             => $tool['required'],
			'additionalProperties' => false,
		);
	}

	/**
	 * OpenAI Responses function tools; execution uses MCP, not a second REST writer.
	 *
	 * @return array
	 */
	public static function openai() {
		$functions = array();
		foreach ( self::definitions() as $name => $tool ) {
			$schema = self::schema( $tool );
			if ( ! $schema['properties'] ) {
				$schema['properties'] = (object) array();
			}
			$functions[] = array(
				'type'        => 'function',
				'name'        => $name,
				'description' => $tool['description'],
				'parameters'  => $schema,
				'strict'      => true,
			);
		}
		return $functions;
	}
}
