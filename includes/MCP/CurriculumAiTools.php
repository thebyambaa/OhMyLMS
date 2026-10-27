<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * MCP tools for AI analysis of syllabuses and shared skills.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\MCP;

defined( 'ABSPATH' ) || exit;

/** Start and follow analyses, and review what they suggest. Nothing changes the curriculum until a suggestion is accepted. */
final class CurriculumAiTools {

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
			'get_skill_placement_options'     => array(
				'GET',
				'/syllabus-comparison/skill-placement',
				'The domains and levels a shared skill of a subject can be placed in, from that subject\'s profile (the default profile when subject is empty or has no profile of its own), the profile key, and whether AI analysis can run on this site. Levels are ordinals 1 … n with labels; grades repeats them for older clients.',
				array(
					'subject' => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
				),
				array(),
				false,
			),
			'list_skill_sources'              => array(
				'GET',
				'/skill-sources',
				'Skill sources that can propose skills (for example course-content, which reads a lesson with AI), and whether each can run on this site now.',
				array(),
				array(),
				false,
			),
			'suggest_course_skills'           => array(
				'POST',
				'/courses/{id}/structure/skill-suggestions',
				'Ask a skill source which skills a unit (unit_id) or one of its lessons (group_id, a lesson UUID) of a course teaches. Runs at once and returns the run with its suggestions; each either reuses a library skill (shared) or proposes a new one (proposal with name, description, topic, domain, levels). Nothing is created until a suggestion is accepted. subject selects the subject profile and library.',
				array(
					'id'       => $id,
					'unit_id'  => $id,
					'group_id' => array(
						'type'      => 'string',
						'maxLength' => 36,
					),
					'subject'  => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
					'source'   => array(
						'type'      => 'string',
						'maxLength' => 40,
					),
				),
				array( 'id', 'unit_id' ),
				false,
			),
			'list_course_skill_suggestions'   => array(
				'GET',
				'/courses/{id}/structure/skill-suggestions',
				'Skill suggestions made for a course, pending by default (status: pending, accepted, rejected or all), with their unit and lesson target and the available skill sources.',
				array(
					'id'     => $id,
					'status' => array(
						'type' => 'string',
						'enum' => array( 'pending', 'accepted', 'rejected', 'all' ),
					),
				),
				array( 'id' ),
				false,
			),
			'review_course_skill_suggestions' => array(
				'POST',
				'/courses/{id}/structure/skill-suggestions/review',
				'Accept or reject course skill suggestions by id. Accepting reuses the named library skill, or creates the proposed skill in the library as pending review (usable in course drafts; publishing waits until it is approved). The accepted suggestion returns result_id: link that skill to the lesson in the course outline. overrides maps a suggestion id to changes (name, description, topic, domain, grade_from, grade_to).',
				array(
					'id'        => $id,
					'ids'       => array(
						'type'     => 'array',
						'items'    => $id,
						'minItems' => 1,
						'maxItems' => 50,
					),
					'action'    => array(
						'type' => 'string',
						'enum' => array( 'accept', 'reject' ),
					),
					'overrides' => array( 'type' => 'object' ),
				),
				array( 'id', 'ids', 'action' ),
				false,
			),
			'review_skill'                    => array(
				'POST',
				'/skills/{id}/review',
				'Approve or reject a skill in the library (review: approved or rejected). Skills proposed by a skill source start as pending; courses using a pending or rejected skill cannot be published.',
				array(
					'id'     => $id,
					'review' => array(
						'type' => 'string',
						'enum' => array( 'approved', 'rejected' ),
					),
				),
				array( 'id', 'review' ),
				false,
			),
			'archive_skill'                   => array(
				'POST',
				'/skills/{id}/archive',
				'Archive a skill: it stays for every course, question and learner record that uses it, but is not offered for new matches. Returns what uses it.',
				array( 'id' => $id ),
				array( 'id' ),
				false,
			),
			'restore_skill'                   => array(
				'POST',
				'/skills/{id}/restore',
				'Return an archived skill to normal use.',
				array( 'id' => $id ),
				array( 'id' ),
				false,
			),
			'list_subject_profiles'           => array(
				'GET',
				'/syllabus-comparison/subject-profiles',
				'Subject profiles: for each subject its domains, ordered levels (school grades, CEFR levels, exam bands …) and the AI context used to analyse its skills. Also the default profile used for subjects without their own, the subjects shared skills use with their counts and resolved profile, the limits, and the revision needed to save.',
				array(),
				array(),
				false,
			),
			'save_subject_profile'            => array(
				'POST',
				'/syllabus-comparison/subject-profiles/save',
				'Create or replace one subject profile. A new profile\'s key comes from its label (for example "Mathematics" is "mathematics"), and shared skills whose subject has that key use it. Send the whole profile: label, optional language code, domains (id and label; ids are lower-case letters, digits and hyphens), levels in order (labels; stored skill bands are their positions 1 … n) and ai (role, context, level_hints). Editing a built-in profile stores a copy. Requires expected_revision from list_subject_profiles.',
				array(
					'key'               => array(
						'type'      => 'string',
						'maxLength' => 40,
					),
					'label'             => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
					'language'          => array(
						'type'      => 'string',
						'maxLength' => 12,
					),
					'domains'           => array(
						'type'     => 'array',
						'maxItems' => 60,
						'items'    => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => array( 'id', 'label' ),
							'properties'           => array(
								'id'    => array(
									'type'    => 'string',
									'pattern' => '^[a-z][a-z0-9-]{0,39}$',
								),
								'label' => array(
									'type'      => 'string',
									'maxLength' => 100,
								),
							),
						),
					),
					'levels'            => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 30,
						'items'    => array(
							'type'      => 'string',
							'maxLength' => 60,
						),
					),
					'ai'                => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'role'        => array(
								'type'      => 'string',
								'maxLength' => 2000,
							),
							'context'     => array(
								'type'      => 'string',
								'maxLength' => 2000,
							),
							'level_hints' => array(
								'type'      => 'string',
								'maxLength' => 2000,
							),
						),
					),
					'expected_revision' => array( 'type' => 'string' ),
				),
				array( 'label', 'levels', 'expected_revision' ),
				false,
			),
			'remove_subject_profile'          => array(
				'POST',
				'/syllabus-comparison/subject-profiles/remove',
				'Remove a saved subject profile. A customised built-in profile (generic, mathematics) returns to its built-in values. Skills keep their stored domain and level positions. Requires expected_revision.',
				array(
					'key'               => array(
						'type'      => 'string',
						'maxLength' => 40,
					),
					'expected_revision' => array( 'type' => 'string' ),
				),
				array( 'key', 'expected_revision' ),
				false,
			),
			'set_default_subject_profile'     => array(
				'POST',
				'/syllabus-comparison/subject-profiles/default',
				'Choose the profile used for subjects without their own profile. Requires expected_revision.',
				array(
					'key'               => array(
						'type'      => 'string',
						'maxLength' => 40,
					),
					'expected_revision' => array( 'type' => 'string' ),
				),
				array( 'key', 'expected_revision' ),
				false,
			),
			'start_syllabus_analysis'         => array(
				'POST',
				'/syllabus-comparison/ai/runs',
				'Start an AI analysis. kind "syllabus" (with syllabus_id) places each objective in a domain and grade band, then suggests the shared skills it maps to or proposes new shared skills; by default only unmapped objectives, include_mapped analyses all. kind "placement" (with subject) suggests a domain and grades for shared skills that have none. kind "links" (with subject) suggests prerequisites and encompassed simpler skills for placed shared skills; by default only skills without prerequisites, include_linked analyses all. Suggestions wait for review; call advance_syllabus_analysis until the status is done.',
				array(
					'kind'           => array(
						'type' => 'string',
						'enum' => array( 'syllabus', 'placement', 'links' ),
					),
					'syllabus_id'    => $id,
					'subject'        => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
					'include_mapped' => array( 'type' => 'boolean' ),
					'include_linked' => array( 'type' => 'boolean' ),
				),
				array( 'kind' ),
				false,
			),
			'advance_syllabus_analysis'       => array(
				'POST',
				'/syllabus-comparison/ai/runs/{id}/step',
				'Process the next slice of an analysis (about eight objectives) and return its progress: status, total, processed, suggested, failures and how many suggestions are pending.',
				array( 'id' => $id ),
				array( 'id' ),
				false,
			),
			'list_skill_suggestions'          => array(
				'GET',
				'/syllabus-comparison/ai/suggestions',
				'AI suggestions to review, 30 per page, new skills first and then by confidence. Each has kind (new_skill, mapping or placement), the objective with its estimated domain, grades and English meaning, the shared skill or proposed skill, the relation, confidence and reason. Filter by run_id, status (pending by default, or accepted, rejected, all), kind and min_confidence.',
				array(
					'run_id'         => $id,
					'status'         => array(
						'type' => 'string',
						'enum' => array( 'pending', 'accepted', 'rejected', 'all' ),
					),
					'kind'           => array(
						'type' => 'string',
						'enum' => array( 'new_skill', 'mapping', 'placement', 'link' ),
					),
					'min_confidence' => array(
						'type'    => 'number',
						'minimum' => 0,
						'maximum' => 1,
					),
				),
				array(),
				true,
			),
			'get_skill_links'                 => array(
				'GET',
				'/syllabus-comparison/skill-links/{id}',
				'The links of a shared skill: its prerequisites, the simpler skills it encompasses (with a weight from 0 to 1: how much of that skill is practised by working this one) and the skills that encompass it, each with who suggested it (manual or ai) and the confidence.',
				array( 'id' => $id ),
				array( 'id' ),
				false,
			),
			'save_skill_link'                 => array(
				'POST',
				'/syllabus-comparison/skill-links',
				'Add a link between two shared skills. type "prerequisite": skill_id needs other_id first (refused when it would form a loop). type "encompasses": practising skill_id also practises other_id, with a weight from 0.05 to 1.',
				array(
					'skill_id' => $id,
					'other_id' => $id,
					'type'     => array(
						'type' => 'string',
						'enum' => array( 'prerequisite', 'encompasses' ),
					),
					'weight'   => array(
						'type'    => 'number',
						'minimum' => 0.05,
						'maximum' => 1,
					),
				),
				array( 'skill_id', 'other_id', 'type' ),
				false,
			),
			'review_skill_suggestions'        => array(
				'POST',
				'/syllabus-comparison/ai/suggestions/review',
				'Accept or reject up to 200 suggestions. Accepting a new_skill creates the shared skill and maps its objective; a mapping saves it through the normal guarded mapping save and records that AI suggested it; a placement stores the domain and grades. overrides maps a suggestion id to changes to apply when accepting: relation, shared_id, name, description, topic, domain, grade_from, grade_to. Accept a proposed new skill before the mappings that point to it.',
				array(
					'ids'       => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 200,
						'items'    => $id,
					),
					'action'    => array(
						'type' => 'string',
						'enum' => array( 'accept', 'reject' ),
					),
					'overrides' => array( 'type' => 'object' ),
				),
				array( 'ids', 'action' ),
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
				'idempotent'  => 'GET' === $definition[0],
			);
		}
		return $result;
	}
}
