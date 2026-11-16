// @ts-check
import { request as defaultRequest } from '../rest.mjs';

/** Questions sent in one suggestion request; the server accepts at most this many. */
export const SUGGEST_BATCH = 10;

/**
 * @typedef {{ id: number, name: string }} SkillRef
 * @typedef {{
 *   question: number,
 *   title: string,
 *   current: SkillRef|null,
 *   skill: SkillRef|null,
 *   confidence: number,
 *   reason: string,
 *   status: 'suggested'|'no-match'|'has-skill'|'unavailable'
 * }} SuggestionRow
 * @typedef {{ id: number, name: string, type: string, depth: number, skills: number }} Curriculum
 * @typedef {Record<string, { primary: number, supporting: number[] }>} SkillMap
 * @typedef {(options: Object) => Promise<unknown>} Transport
 */

/**
 * Split a list into consecutive groups.
 *
 * @template T
 * @param {T[]}    list Items.
 * @param {number} size Group size.
 * @return {T[][]} Groups, in order.
 */
export function chunk( list, size ) {
	const groups = [];
	for ( let at = 0; at < list.length; at += size ) {
		groups.push( list.slice( at, at + size ) );
	}
	return groups;
}

/**
 * The skill a row is saved with unless the author changes it: the suggestion, for a question that
 * has no skill yet. A question that already has a skill is never changed without the author's say.
 *
 * @param {SuggestionRow} row Suggestion.
 * @return {number} Term ID, or 0 to leave the question as it is.
 */
export function defaultChoice( row ) {
	return 'suggested' === row.status && row.skill && ! row.current
		? row.skill.id
		: 0;
}

/**
 * A skill map whose first part has the given primary skill, keeping its supporting skills.
 *
 * @param {SkillMap|null|undefined} map    Current map.
 * @param {number}                  termId New primary skill.
 * @return {SkillMap} New map.
 */
export function withPrimarySkill( map, termId ) {
	const next = { ...( map || {} ) };
	const part = next.p1 || { primary: 0, supporting: [] };
	next.p1 = {
		primary: termId,
		supporting: ( part.supporting || [] ).filter( ( id ) => id !== termId ),
	};
	return next;
}

/**
 * Curriculum items that have skills to match against.
 *
 * @param {Transport} [fetch] Transport.
 * @return {Promise<Curriculum[]>} Items.
 */
export const loadCurricula = ( fetch = defaultRequest ) =>
	/** @type {Promise<Curriculum[]>} */ (
		fetch( { path: '/ohmylms/v1/question-skill-suggestions/curricula' } )
	);

/**
 * Suggestions for every question, a batch at a time, in order.
 *
 * @param {number[]}                              questionIds   Questions.
 * @param {number}                                itemId        Curriculum item, or 0 for every skill.
 * @param {boolean}                               includeMapped Also for questions that have a skill.
 * @param {(done: number, total: number) => void} [onProgress]  Called after each batch.
 * @param {Transport}                             [fetch]       Transport.
 * @return {Promise<SuggestionRow[]>} One row per question.
 */
export async function suggestSkills(
	questionIds,
	itemId,
	includeMapped,
	onProgress = () => {},
	fetch = defaultRequest
) {
	/** @type {SuggestionRow[]} */
	const rows = [];
	for ( const batch of chunk( questionIds, SUGGEST_BATCH ) ) {
		const reply = /** @type {{ rows?: SuggestionRow[] }} */ (
			await fetch( {
				path: '/ohmylms/v1/question-skill-suggestions',
				method: 'POST',
				data: {
					question_ids: batch,
					item_id: itemId,
					include_mapped: includeMapped,
				},
			} )
		);
		rows.push( ...( reply?.rows || [] ) );
		onProgress( rows.length, questionIds.length );
	}
	return rows;
}
