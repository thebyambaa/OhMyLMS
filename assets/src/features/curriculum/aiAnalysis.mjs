/**
 * Pure helpers for the AI analysis review screen. The server decides everything; these only
 * describe a run and a suggestion for display.
 */

/** Statuses in which a run still has work to do. */
export const ACTIVE_STATUSES = [ 'queued', 'running' ];

/**
 * Whether a run should keep being advanced.
 * @param {Object|null} run Run.
 * @return {boolean} Still working.
 */
export const isActive = ( run ) =>
	Boolean( run && ACTIVE_STATUSES.includes( run.status ) );

/**
 * Share of a run done, 0 to 100.
 * @param {Object|null} run Run.
 * @return {number} Percent.
 */
export function progress( run ) {
	if ( ! run || ! run.total ) {
		return 0;
	}
	return Math.min( 100, Math.round( ( run.processed / run.total ) * 100 ) );
}

/**
 * A level band as words. With the subject's levels (value, label) it uses their names, so "A1–B2" or
 * "Grade 7"; without them it reads the built-in Mathematics grades.
 * @param {number}   from     First level, or 0.
 * @param {number}   to       Last level, or 0.
 * @param {Object[]} [levels] The subject profile's levels.
 * @return {string} Text such as "Grades 7–9", or empty when not set.
 */
export function gradeBand( from, to, levels ) {
	const first = Number( from ) || 0;
	if ( ! first ) {
		return '';
	}
	const last = Math.max( first, Number( to ) || 0 );
	if ( Array.isArray( levels ) && levels.length ) {
		const label = ( value ) =>
			(
				levels.find( ( level ) => level.value === value ) ||
				levels.at( -1 )
			).label;
		return first === last
			? label( first )
			: `${ label( first ) }–${ label( last ) }`;
	}
	const name = ( grade ) =>
		grade >= 13 ? 'beyond grade 12' : String( grade );
	if ( first === last ) {
		return first >= 13 ? 'Beyond grade 12' : `Grade ${ first }`;
	}
	return `Grades ${ name( first ) }–${ name( last ) }`;
}

/**
 * Confidence as a whole percentage.
 * @param {number} value 0 to 1.
 * @return {number} Percent.
 */
export const percent = ( value ) =>
	Math.round( Math.max( 0, Math.min( 1, Number( value ) || 0 ) ) * 100 );

/**
 * What a suggestion proposes, in a few words.
 * @param {Object}   suggestion Suggestion from the server.
 * @param {Object[]} [levels]   The subject profile's levels.
 * @return {string} Summary.
 */
export function proposes( suggestion, levels ) {
	if ( suggestion.kind === 'new_skill' ) {
		return suggestion.proposal?.name || '';
	}
	if ( suggestion.kind === 'placement' ) {
		return [
			suggestion.placement?.domain || '',
			gradeBand(
				suggestion.placement?.grade_from,
				suggestion.placement?.grade_to,
				levels
			),
		]
			.filter( Boolean )
			.join( ' · ' );
	}
	return suggestion.shared?.name || suggestion.proposal?.name || '';
}

/**
 * The weight of an encompassing link kept within the allowed range, as a percentage.
 * @param {number} weight 0.05 to 1.
 * @return {number} Percent, 5 to 100.
 */
export const weightPercent = ( weight ) =>
	Math.round( Math.max( 0.05, Math.min( 1, Number( weight ) || 0 ) ) * 100 );

/**
 * A skill's grade band and domain on one line, for lists of linked skills.
 * @param {Object}   skill    Skill with domain, grade_from and grade_to.
 * @param {Object[]} [levels] The subject profile's levels.
 * @return {string} Text such as "algebra · Grades 7–8".
 */
export const skillSummary = ( skill, levels ) =>
	[ skill?.domain, gradeBand( skill?.grade_from, skill?.grade_to, levels ) ]
		.filter( Boolean )
		.join( ' · ' );

/**
 * Whether a mapping suggestion has to wait for the new skill it points to.
 * @param {Object} suggestion Suggestion from the server.
 * @return {boolean} Waiting.
 */
export const waitsForProposal = ( suggestion ) =>
	suggestion.kind === 'mapping' &&
	! suggestion.shared &&
	suggestion.proposal?.status === 'pending';

/**
 * The overrides a reviewer's edits add to an accept request, keyed by suggestion id.
 * @param {Object} edits Suggestion id => edited fields.
 * @param {Array}  ids   Suggestions being accepted.
 * @return {Object} Overrides to send.
 */
export function overridesFor( edits, ids ) {
	const result = {};
	for ( const id of ids ) {
		if ( edits[ id ] && Object.keys( edits[ id ] ).length ) {
			result[ id ] = edits[ id ];
		}
	}
	return result;
}
