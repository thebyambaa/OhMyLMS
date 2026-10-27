/**
 * Stable token IDs keep repeated answer words independently draggable.
 * @param text
 */
export function parseInlineBlankPrompt( text ) {
	const parts = [];
	const answers = [];
	let offset = 0;
	for ( const match of String( text || '' ).matchAll( /\{([^{}<>]+)\}/gu ) ) {
		if ( ! match[ 1 ].trim() ) {
			continue;
		}
		parts.push( {
			text: text.slice( offset, match.index ),
		} );
		parts.push( {
			blank: true,
			index: answers.length,
		} );
		answers.push( {
			id: String( answers.length ),
			text: match[ 1 ],
		} );
		offset = match.index + match[ 0 ].length;
	}
	parts.push( {
		text: String( text || '' ).slice( offset ),
	} );
	return {
		parts,
		answers,
	};
}
export function placeBlankToken( assignments, token, position ) {
	if (
		! Number.isInteger( position ) ||
		position < 0 ||
		position >= assignments.length
	) {
		return assignments;
	}
	return assignments.map( ( value, index ) =>
		( () => {
			if ( index === position ) {
				return token;
			}
			if ( value === token ) {
				return null;
			}
			return value;
		} )()
	);
}
