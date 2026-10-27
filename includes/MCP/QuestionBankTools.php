<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * MCP tools for classifying and importing questions with AI, with human approval.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP;

defined( 'ABSPATH' ) || exit;

/** Skill lookup, checked import and review of AI-classified questions. */
final class QuestionBankTools {

	/**
	 * Tool definitions in the shape the MCP server and abilities layer share.
	 *
	 * @return array
	 */
	public static function definitions() {
		$page   = array(
			'type'    => 'integer',
			'minimum' => 1,
			'default' => 1,
		);
		$tools  = array(
			'search_skill_catalogue'    => array(
				'GET',
				'/skills',
				'Find skills to classify questions with. Search by words in the skill name, by its code (for example DSAT.D.1.5) or by UUID. Returns code, name, full objective, parent path and child count. Use the returned code in the "skills" field of import_questions. Pass parent_id to browse the direct children of one skill. Skills with children are usually topics; choose the most specific skill that the question actually tests.',
				array(
					'search'    => array(
						'type'      => 'string',
						'maxLength' => 200,
					),
					'parent_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'page'      => $page,
					'per_page'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 50,
						'default' => 20,
					),
				),
				array(),
				true,
			),
			'import_questions'          => array(
				'POST',
				'/import',
				'Check or import question rows into the question bank. dry_run defaults to true: call it first, fix every reported error, then call again with dry_run false. Imported questions are ALWAYS unapproved drafts flagged for human review, so they do not count toward practice or learner progress until a person approves them. Each row identifies a question by exam, paper, year, section, number and language, so sending the same rows again updates instead of duplicating. Row fields: exam, paper, year, section, number, language, type (single-choice, multiple-choice, true-false, numerical, fill-in-the-blank, or another registered type with settings), question, answers (list), correct (letter, number or text; list for several), explanation, skills (codes from search_skill_catalogue, first is primary), family, difficulty (easy, standard, challenge), marks, tolerance, unit, image (media ID or URL), confidence (0 to 1, your certainty about the skills), notes (why you chose them). Maximum 100 rows per call.',
				array(
					'rows'            => array(
						'type'     => 'array',
						'maxItems' => 100,
						'items'    => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
					'dry_run'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'bank_id'         => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'update_existing' => array( 'type' => 'boolean' ),
					'skip_invalid'    => array( 'type' => 'boolean' ),
				),
				array( 'rows' ),
				false,
			),
			'list_questions_for_review' => array(
				'GET',
				'/review',
				'List AI-imported questions waiting for human review, oldest first, with their text, skills, difficulty, family, confidence and notes. Use review_questions only on questions a person has told you to approve.',
				array(
					'page'     => $page,
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 20,
						'default' => 10,
					),
					'search'   => array(
						'type'      => 'string',
						'maxLength' => 200,
					),
				),
				array(),
				true,
			),
			'export_questions'          => array(
				'GET',
				'/export',
				'Export questions as import rows, in CSV or JSON, for every question type. Choose them by ids (up to 200) or give quiz_id to export that quiz\'s questions in order, with their sections. The content holds every answer key. Re-importing the file with import_questions creates the questions again; warnings list questions that have no skill, which the importer refuses until one is added. label names the exam written into the rows.',
				array(
					'ids'     => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 200,
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'quiz_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'format'  => array(
						'type' => 'string',
						'enum' => array( 'json', 'csv' ),
					),
					'label'   => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
				),
				array(),
				true,
			),
			'review_questions'          => array(
				'POST',
				'/review',
				'Decide on questions waiting for review. approve approves the current version so it counts toward practice and learner progress. dismiss only removes the review flag without approving. Approval changes what learners are measured on: only approve questions a person has asked you to approve.',
				array(
					'ids'      => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 50,
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'decision' => array(
						'type' => 'string',
						'enum' => array( 'approve', 'dismiss' ),
					),
				),
				array( 'ids', 'decision' ),
				false,
			),
		);
		$result = array();
		foreach ( $tools as $name => $definition ) {
			$result[ $name ] = array(
				'method'      => $definition[0],
				'route'       => '/mcp-question-bank' . $definition[1],
				'description' => $definition[2],
				'properties'  => $definition[3],
				'required'    => $definition[4],
				'list'        => $definition[5],
				'readonly'    => 'GET' === $definition[0],
				'destructive' => 'GET' !== $definition[0],
				'idempotent'  => 'review_questions' !== $name,
			);
		}
		return $result;
	}
}
