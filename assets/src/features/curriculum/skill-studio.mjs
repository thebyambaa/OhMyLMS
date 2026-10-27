import { readComparison } from './comparison.mjs';

/**
 * Load the focused skill's links, including links beyond the first source page.
 * @param {Array}                                              sources Source syllabuses.
 * @param {number}                                             skillId Focused shared skill.
 * @param {(route: string, params: Object) => Promise<Object>} read    Comparison reader.
 * @return {Promise<Array>} Source pages with their linked objectives.
 */
export async function loadStudioLinks(
	sources,
	skillId,
	read = readComparison
) {
	return Promise.all(
		sources.map( async ( source ) => {
			const first = await read( `objectives/${ source.id }`, {
				shared_id: skillId,
				page: 1,
			} );
			const items = [ ...first.items ];
			for ( let page = 2; ( page - 1 ) * 30 < first.total; page++ ) {
				const next = await read( `objectives/${ source.id }`, {
					shared_id: skillId,
					page,
				} );
				items.push( ...next.items );
			}
			return { ...first, items, source };
		} )
	);
}

/**
 * Preserve source membership while deduplicating objectives on the canvas.
 * @param {Array} groups Loaded source pages.
 * @return {Array} Distinct objectives with all their source names.
 */
export function studioObjectives( groups ) {
	const rows = new Map();
	for ( const group of groups ) {
		for ( const item of group.items ) {
			const previous = rows.get( item.term_id );
			rows.set( item.term_id, {
				...item,
				source_id: previous?.source_id || group.source.id,
				source_names: [
					...new Set( [
						...( previous?.source_names || [] ),
						group.source.name,
					] ),
				],
			} );
		}
	}
	return [ ...rows.values() ];
}

/**
 * Ignore stale responses when the user switches the focused shared skill.
 * @param {Object} connections Loaded connection set and its owner.
 * @param {number} skillId     Current shared skill.
 * @return {Array} Only objectives mapped to the current skill.
 */
export function focusedStudioObjectives( connections, skillId ) {
	if ( connections.skillId !== skillId ) {
		return [];
	}
	return studioObjectives( connections.groups ).filter( ( row ) =>
		row.maps_to?.some( ( mapping ) => mapping.shared_id === skillId )
	);
}

/**
 * Build one revision-bearing proposal, refusing individual group edits.
 * @param {Object} proposal Connection editor state.
 * @param {number} sharedId Shared target.
 * @return {Object|null} Route, method and explicit save payload.
 */
export function studioWrite( proposal, sharedId ) {
	if ( ! proposal?.row || ! sharedId || proposal.mapping?.group_uuid ) {
		return null;
	}
	const row = proposal.replacement || proposal.row;
	const data = {
		specific_id: row.term_id,
		shared_id: sharedId,
		relation: proposal.relation,
		note: proposal.note,
		expected_revision: proposal.mapping?.revision || '',
	};
	if ( proposal.replacement && row.term_id !== proposal.row.term_id ) {
		return {
			route: 'mapping/reconnect',
			method: 'POST',
			data: {
				...data,
				old_specific_id: proposal.row.term_id,
				source_id: row.source_id,
			},
		};
	}
	return { route: 'mapping', method: 'PUT', data };
}
