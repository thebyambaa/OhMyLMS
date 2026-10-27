<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * MCP tools for building mixed-skill assessments with AI, as drafts a person reviews.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP;

defined( 'ABSPATH' ) || exit;

/** Find a unit's skills and propose a quiz, unit test or course challenge for them. */
final class SkillAssessmentTools {

	/**
	 * Tool definitions in the shape the MCP server and abilities layer share.
	 *
	 * @return array
	 */
	public static function definitions() {
		$tools  = array(
			'list_unit_skills'        => array(
				'GET',
				'/unit-skills',
				'List the skills taught in one unit (chapter): skills on its lessons and the skills its quizzes are connected to, each with how many different approved questions the bank has for it. Use get_course_chapters to find unit IDs. A skill with 0 available cannot be used in an assessment yet.',
				array(
					'chapter_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array( 'chapter_id' ),
				true,
			),
			'create_skill_assessment' => array(
				'POST',
				'/create',
				'Create a NEW DRAFT mixed-skill assessment that draws questions from the bank for each chosen skill: a quiz (a short check), a unit-test (every skill of a unit) or a course-challenge. It uses existing approved questions only, in their own question types, and it is never published here: a person publishes it from the quiz editor. Check list_unit_skills first and choose only skills that have at least per_skill questions available. chapter_id places it at the end of that unit (0 for none); time_limit is minutes (0 for no limit).',
				array(
					'title'      => array(
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 190,
					),
					'style'      => array(
						'type' => 'string',
						'enum' => array( 'quiz', 'unit-test', 'course-challenge' ),
					),
					'skills'     => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 40,
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'per_skill'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 10,
					),
					'chapter_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'time_limit' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'maximum' => 600,
					),
				),
				array( 'title', 'style', 'skills', 'per_skill' ),
				false,
			),
		);
		$result = array();
		foreach ( $tools as $name => $definition ) {
			$result[ $name ] = array(
				'method'      => $definition[0],
				'route'       => '/mcp-skill-assessments' . $definition[1],
				'description' => $definition[2],
				'properties'  => $definition[3],
				'required'    => $definition[4],
				'list'        => $definition[5],
				'readonly'    => 'GET' === $definition[0],
				'destructive' => 'GET' !== $definition[0],
				'idempotent'  => false,
			);
		}
		return $result;
	}
}
