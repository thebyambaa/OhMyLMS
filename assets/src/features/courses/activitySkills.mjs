/**
 * Connect tag selections and introduce their course outcomes in the same local edit.
 * @param {Object}   draft      Current outline.
 * @param {string}   activityId Selected placement ID.
 * @param {number[]} ids        Complete selected skill IDs.
 * @param {Object[]} rows       Available skill records.
 * @return {Object} Updated outline, preserving existing outcome requirements.
 */
export function connectActivitySkills( draft, activityId, ids, rows ) {
	const selected = [ ...new Set( ids ) ];
	const outcomes = [ ...draft.program.outcomes ];
	const known = new Set( outcomes.map( ( row ) => row.term_id ) );
	const names = { ...draft.skill_names };
	const codes = { ...draft.skill_codes };
	const activity = draft.program.items.find(
		( item ) => item.id === activityId
	);
	for ( const row of rows ) {
		if ( ! selected.includes( row.term_id ) ) {
			continue;
		}
		names[ row.term_id ] = row.name;
		if ( row.code ) {
			codes[ row.term_id ] = row.code;
		}
		if ( ! known.has( row.term_id ) ) {
			known.add( row.term_id );
			outcomes.push( {
				term_id: row.term_id,
				target: 'proficient',
				required: false,
				...( activity?.chapter_id
					? { chapter_id: activity.chapter_id }
					: {} ),
			} );
		}
	}
	return {
		...draft,
		skill_names: names,
		skill_codes: codes,
		program: {
			...draft.program,
			outcomes,
			items: draft.program.items.map( ( item ) =>
				item.id === activityId ? { ...item, skill_ids: selected } : item
			),
		},
	};
}
