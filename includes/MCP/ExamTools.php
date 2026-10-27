<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * MCP tools for exams: create and fill a draft, never publish.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP;

defined( 'ABSPATH' ) || exit;

/** Exams are drafts here; a person publishes them from the exam's quiz editor, which approves the questions. */
final class ExamTools {

	/**
	 * Tool definitions in the shape the MCP server and abilities layer share.
	 *
	 * @return array
	 */
	public static function definitions() {
		$id     = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		$tools  = array(
			'list_exams'            => array(
				'GET',
				'/exams',
				'List your exams, newest first, 20 per page. Each has id, title, status (draft or publish), duration_minutes, total_marks, question_ids, quiz_id and, for exams written in OhMyLMS, editor_url (the Gutenberg quiz editor where its questions are edited) problems (what still blocks publishing; empty means ready for a person to publish) and warnings (practice sets and inline checks that publishing would break; a person confirms when publishing).',
				array(
					'page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				array(),
				true,
			),
			'create_exam'           => array(
				'POST',
				'/exams/compose',
				'Create a NEW DRAFT exam with an empty question list. It is never published here: a person adds or reviews the questions, then publishes it from its editor_url. Returns the exam with its id, quiz_id and editor_url. Add questions with add_exam_questions.',
				array(
					'title'            => array(
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 190,
					),
					'duration_minutes' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 1440,
					),
				),
				array( 'title', 'duration_minutes' ),
				false,
			),
			'set_exam_instructions' => array(
				'POST',
				'/exams/{id}/settings',
				'Set the instructions learners read before the exam, replacing any existing text. Only for a draft exam written in OhMyLMS.',
				array(
					'id'           => $id,
					'instructions' => array(
						'type'      => 'string',
						'maxLength' => 4000,
					),
				),
				array( 'id', 'instructions' ),
				false,
			),
			'add_exam_questions'    => array(
				'POST',
				'/exams/{id}/questions',
				'Place existing question bank questions at the end of a draft exam, in the order given (create them first with save_question or import_questions). Questions already in the exam keep their place. Ungraded activity types (poll, word-cloud, slide, spin-wheel, open-box, speaking-cards, flip-tiles, flashcards) add no marks and block publishing. Nothing is approved or published; check list_exams problems afterwards.',
				array(
					'id'           => $id,
					'question_ids' => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 50,
						'items'    => $id,
					),
				),
				array( 'id', 'question_ids' ),
				false,
			),
		);
		$result = array();
		foreach ( $tools as $name => $definition ) {
			$result[ $name ] = array(
				'method'      => $definition[0],
				'route'       => $definition[1],
				'description' => $definition[2],
				'properties'  => $definition[3],
				'required'    => $definition[4],
				'list'        => $definition[5],
				'readonly'    => 'GET' === $definition[0],
				'destructive' => false,
				'idempotent'  => 'GET' === $definition[0] || 'set_exam_instructions' === $name,
			);
		}
		return $result;
	}
}
