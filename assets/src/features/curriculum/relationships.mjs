import { __ } from '@wordpress/i18n';

/** Labels retain the source-to-shared direction of every relationship. */
export const relationshipLabels = () => ( {
	equivalent: __( 'Equivalent', 'ohmylms' ),
	broader: __( 'Source is broader', 'ohmylms' ),
	narrower: __( 'Source is narrower', 'ohmylms' ),
	related: __( 'Related', 'ohmylms' ),
	combined: __( 'Required together', 'ohmylms' ),
} );

/**
 * Group a combined proposal by its actual syllabus membership.
 * @param {Array} selected Selected objective rows.
 * @param {Array} pairs    Revision-bearing pair payloads.
 * @return {Array} Explicit source groups.
 */
export function combinedGroups( selected, pairs ) {
	const groups = new Map();
	for ( const row of selected ) {
		if ( ! groups.has( row.syllabus_id ) ) {
			groups.set( row.syllabus_id, [] );
		}
		const pair = pairs.find(
			( entry ) => entry.specific_id === row.term_id
		);
		if ( pair ) {
			groups.get( row.syllabus_id ).push( {
				specific_id: pair.specific_id,
				expected_revision: pair.expected_revision,
			} );
		}
	}
	return [ ...groups ].map( ( [ sourceId, entries ] ) => ( {
		source_id: sourceId,
		pairs: entries,
	} ) );
}
