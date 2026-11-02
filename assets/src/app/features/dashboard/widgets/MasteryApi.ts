import { get, post } from '../../../lib/api';
import { parseMasteryLearners } from './MasteryModel';
import { parseAssignResult } from './ReviewSetsModel';

const WIDGETS = '/ohmylms/v1/dashboard/widgets';

/**
 * Learners of a heatmap row who are below Familiar on a skill.
 *
 * @param scope  Scope id.
 * @param row    Row key (`class:9`).
 * @param skill  Skill id.
 * @param signal Cancels the request.
 */
export async function getMasteryLearners(
	scope: string,
	row: string,
	skill: number,
	signal?: AbortSignal
) {
	return parseMasteryLearners(
		await get< unknown >( `${ WIDGETS }/mastery-heatmap/learners`, {
			signal,
			query: { scope, row, skill },
		} )
	);
}

/**
 * Create the draft review set of a group, or get the one that exists.
 *
 * @param scope   Scope id.
 * @param classId Class of the group.
 * @param skillId Skill.
 */
export async function assignReviewSet(
	scope: string,
	classId: number,
	skillId: number
) {
	return parseAssignResult(
		await post< unknown >( `${ WIDGETS }/review-sets/assign`, {
			scope,
			class_id: classId,
			skill_id: skillId,
		} )
	);
}
