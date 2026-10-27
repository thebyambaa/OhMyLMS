<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Shared skill MCP tools.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP;

defined( 'ABSPATH' ) || exit;

/** Explicit administrator writes through the same REST services as the UI. */
final class ComparisonTools {
	/**
	 * Bounded tool schemas, with no arbitrary routes or student IDs.
	 *
	 * @return array
	 */
	public static function definitions() {
		$id                                       = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		$text                                     = array(
			'type'      => 'string',
			'maxLength' => 100,
		);
		$revision                                 = array(
			'type'      => 'string',
			'maxLength' => 64,
		);
		$mapping                                  = array(
			'specific_id'       => $id,
			'shared_id'         => $id,
			'expected_revision' => $revision,
		);
		$definitions                              = array(
			'list_syllabuses'              => array( 'GET', '/syllabuses', 'List syllabuses and roadmaps, versions, paths and subject selections.', array(), array() ),
			'list_syllabus_objectives'     => array(
				'GET',
				'/objectives/{id}',
				'Read 30 source objectives per page, mappings and coverage. Row positions do not imply equivalence.',
				array(
					'id'        => $id,
					'page'      => $id,
					'search'    => $text,
					'topic'     => $id,
					'category'  => $text,
					'shared_id' => $id,
					'status'    => array(
						'type' => 'string',
						'enum' => array( 'equivalent', 'related_only', 'unmapped' ),
					),
				),
				array( 'id' ),
			),
			'list_shared_skills'           => array(
				'GET',
				'/shared-skills',
				'List reusable curriculum-independent shared skills, 30 per page. Subject is an optional library filter.',
				array(
					'subject' => $text,
					'page'    => $id,
					'search'  => $text,
				),
				array(),
			),
			'get_shared_skill'             => array( 'GET', '/shared-skills/{id}', 'Read stable identity, revision and all affected objectives before editing scope.', array( 'id' => $id ), array( 'id' ) ),
			'save_shared_skill'            => array(
				'POST',
				'/shared-skills/save',
				'Create (id=0) or edit a reusable shared skill. Edits require expected_revision. Review affected objectives before scope changes. Identity and evidence remain intact.',
				array(
					'id'                => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'subject'           => $text,
					'roadmap_id'        => $id,
					'name'              => array(
						'type'      => 'string',
						'maxLength' => 190,
					),
					'description'       => array(
						'type'      => 'string',
						'maxLength' => 2000,
					),
					'topic'             => $text,
					'expected_revision' => $revision,
				),
				array( 'name' ),
			),
			'save_skill_mapping'           => array(
				'PUT',
				'/mapping',
				'Save one explicitly reviewed mapping. Only Equivalent pools evidence. Broader means the source covers more than the shared skill; narrower means it covers less. Related is descriptive. Combined coverage uses save_combined_skill_mapping. New pairs use empty expected_revision; edits use current objective revisions.',
				$mapping + array(
					'relation' => array(
						'type' => 'string',
						'enum' => array( 'equivalent', 'broader', 'narrower', 'related' ),
					),
					'note'     => array(
						'type'      => 'string',
						'maxLength' => 1000,
					),
				),
				array( 'specific_id', 'shared_id', 'expected_revision', 'relation' ),
			),
			'remove_skill_mapping'         => array( 'POST', '/mapping/remove', 'Remove a mapping using its current revision. Evidence and objectives remain intact.', $mapping, array_keys( $mapping ) ),
			'configure_subject_syllabuses' => array(
				'PUT',
				'/configuration',
				'Select up to ten syllabuses or roadmaps for a subject or level profile. Only published syllabus courses are visible to learners. Does not publish a course.',
				array(
					'subject'      => $text,
					'syllabus_ids' => array(
						'type'        => 'array',
						'items'       => $id,
						'maxItems'    => 10,
						'uniqueItems' => true,
					),
				),
				array( 'subject', 'syllabus_ids' ),
			),
		);
		$tools                                    = array();
		$definitions['list_shared_skill_content'] = array(
			'GET',
			'/shared-skills/{id}/content',
			'List connected reusable content or search editable targets, 20 per page. Content access remains unchanged.',
			array(
				'id'             => $id,
				'kind'           => $text,
				'page'           => $id,
				'search'         => $text,
				'search_targets' => array(
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => 1,
				),
			),
			array( 'id' ),
		);
		$definitions['connect_shared_skill_content'] = array(
			'PUT',
			'/shared-skills/{id}/content',
			'Attach or detach one lesson, quiz, assignment, course or extension resource to a shared skill without replacing its other links.',
			array(
				'id'         => $id,
				'content_id' => $id,
				'linked'     => array( 'type' => 'boolean' ),
			),
			array( 'id', 'content_id', 'linked' ),
		);
		$definitions['create_shared_skill_roadmap']  = array(
			'POST',
			'/roadmaps',
			'Create a general draft roadmap from ordered shared skill references. Skills, content and evidence are never copied. Open the syllabus editor to rearrange or publish it.',
			array(
				'name'      => array(
					'type'      => 'string',
					'maxLength' => 190,
				),
				'subject'   => $text,
				'grade'     => $text,
				'skill_ids' => array(
					'type'        => 'array',
					'items'       => $id,
					'minItems'    => 1,
					'maxItems'    => 200,
					'uniqueItems' => true,
				),
			),
			array( 'name', 'skill_ids' ),
		);
		$definitions['save_combined_skill_mapping']  = array(
			'POST',
			'/mapping-groups',
			'Save reviewed objectives required together, grouped by syllabus. Descriptive coverage only: no evidence pooling or inferred mastery. Every source group requires at least two distinct objectives with current pair revisions.',
			array(
				'shared_id' => $id,
				'note'      => array(
					'type'      => 'string',
					'maxLength' => 1000,
				),
				'groups'    => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => 10,
					'items'    => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'source_id', 'pairs' ),
						'properties'           => array(
							'source_id' => $id,
							'pairs'     => array(
								'type'     => 'array',
								'minItems' => 2,
								'maxItems' => 200,
								'items'    => array(
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => array( 'specific_id', 'expected_revision' ),
									'properties'           => array(
										'specific_id' => $id,
										'expected_revision' => $revision,
									),
								),
							),
						),
					),
				),
			),
			array( 'shared_id', 'groups' ),
		);
		$definitions['audit_my_study_plans']         = array( 'GET', '/audit', 'Read authenticated account study plans: shared mastery, direct syllabus progress, coverage and unassessed/developing objective IDs. Another student account cannot be requested.', array(), array() );
		$definitions['audit_student_study_plans']    = array( 'GET', '/audit/{student_id}', 'Administrator-only audit of a learner study plan: shared mastery, direct syllabus progress, coverage and unassessed/developing requirements. Returns no answer bodies or assessment keys.', array( 'student_id' => $id ), array( 'student_id' ) );
		$definitions['get_skill_map']                = array(
			'GET',
			'/skill-map',
			'The coherence map of one subject: its shared skills by domain and grade band, their prerequisites and "includes" links, skills with no domain or grade yet, and coherence issues (later_prerequisite: a prerequisite first taught after the skill; long_gap: a prerequisite four or more grades earlier; no_prerequisites: a skill above the first grade of its domain that needs nothing). Optional syllabus_id adds which skills that syllabus teaches; optional student_id adds the level of that learner on each skill and strength per domain. Subjects come from the skill-map/subjects route.',
			array(
				'subject'     => $text,
				'syllabus_id' => $id,
				'student_id'  => $id,
			),
			array( 'subject' ),
		);
		$definitions['compare_syllabuses']           = array(
			'GET',
			'/compare',
			'Compare two syllabuses through the shared skills their objectives map onto (equivalent, broader, narrower or combined mappings; related ones do not count). Returns the skills both teach, only A, only B, objectives with no mapping on each side, the overlap percentage and counts per domain.',
			array(
				'a' => $id,
				'b' => $id,
			),
			array( 'a', 'b' ),
		);
		foreach ( $definitions as $name => $definition ) {
			$tools[ $name ] = array(
				'method'      => $definition[0],
				'route'       => '/syllabus-comparison' . $definition[1],
				'description' => $definition[2],
				'properties'  => $definition[3],
				'required'    => $definition[4],
				'list'        => 'GET' === $definition[0],
				'readonly'    => 'GET' === $definition[0],
				'destructive' => 'remove_skill_mapping' === $name,
				'idempotent'  => 'GET' === $definition[0],
			);
		}
		return $tools;
	}
}
