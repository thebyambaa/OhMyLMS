/**
 * Pure helpers for skill provenance and course skill suggestions in the outline editor.
 */

const BUILT_IN_SOURCES = [ 'manual', 'syllabus', 'import' ];

/**
 * Whether a source is a generated one (AI analysis or a registered skill source).
 * @param {string} source Source id.
 * @return {boolean} Generated.
 */
export function generatedSource( source ) {
	return !! source && ! BUILT_IN_SOURCES.includes( source );
}

/**
 * The badge to show beside a skill, or null for an approved, active skill.
 * @param {Object} [meta] Skill meta: source, review, status.
 * @return {?{kind: string, warning: boolean}} Badge kind: archived, pending, ai-pending or rejected.
 */
export function provenanceBadge( meta ) {
	if ( ! meta ) {
		return null;
	}
	if ( 'archived' === meta.status ) {
		return { kind: 'archived', warning: true };
	}
	if ( 'rejected' === meta.review ) {
		return { kind: 'rejected', warning: true };
	}
	if ( 'pending' === meta.review ) {
		return {
			kind: generatedSource( meta.source ) ? 'ai-pending' : 'pending',
			warning: true,
		};
	}
	return null;
}

/**
 * Suggestions that belong to one place of the outline.
 * @param {Object[]} suggestions Suggestions from the server.
 * @param {Object}   place       unitId and groupId (lesson UUID, or empty for the unit).
 * @return {Object[]} Suggestions for that place.
 */
export function suggestionsFor( suggestions, place ) {
	return suggestions.filter(
		( row ) =>
			'pending' === row.status &&
			String( row.target?.unit_id || 0 ) === String( place.unitId ) &&
			( row.target?.group_id || '' ) === ( place.groupId || '' )
	);
}

/**
 * What an accepted suggestion adds to the outline: the skill, its name and its meta.
 * @param {Object} suggestion Accepted suggestion (result_id set).
 * @param {Object} [edits]    Name the reviewer typed for a new skill.
 * @return {?{termId: number, name: string, meta: Object}} Skill to link, or null when nothing was created.
 */
export function acceptedSkill( suggestion, edits = {} ) {
	if ( ! suggestion?.result_id ) {
		return null;
	}
	const reused = !! suggestion.shared;
	return {
		termId: suggestion.result_id,
		name: reused
			? suggestion.shared.name
			: edits.name || suggestion.proposal?.name || '',
		meta: reused
			? { source: 'manual', review: 'approved', status: 'active' }
			: {
					source: suggestion.source,
					review: 'pending',
					status: 'active',
					model: suggestion.proposal?.meta?.model || '',
					confidence: suggestion.confidence,
				},
	};
}

/**
 * Whether a lesson (or a unit's activities outside lessons) exists in the saved outline, so a source can
 * read it. New units and lessons need saving first.
 * @param {string} baseline JSON of the saved units and program.
 * @param {Object} place    unitId and groupId.
 * @return {boolean} Saved.
 */
export function placeSaved( baseline, place ) {
	let saved;
	try {
		saved = JSON.parse( baseline || '{}' );
	} catch {
		return false;
	}
	const unitSaved = ( saved.units || [] ).some(
		( unit ) =>
			String( unit.id ) === String( place.unitId ) &&
			'number' === typeof unit.id
	);
	return (
		unitSaved &&
		( ! place.groupId ||
			( saved.program?.groups || [] ).some(
				( group ) => group.id === place.groupId
			) )
	);
}
