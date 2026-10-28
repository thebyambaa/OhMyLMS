/**
 * Build a curriculum tree, preserving sibling order and ancestors during search.
 * @param {Object[]} items  Curriculum records.
 * @param {string}   search Category search.
 * @return {Object[]} Visible root nodes.
 */
export function libraryCurriculum( items, search = '' ) {
	const nodes = new Map(
		items.map( ( item ) => [
			Number( item.id ),
			{ ...item, children: [] },
		] )
	);
	const roots = [];
	for ( const node of nodes.values() ) {
		const parent = nodes.get( Number( node.parent_id ) );
		if ( parent && parent !== node ) {
			parent.children.push( node );
		} else {
			roots.push( node );
		}
	}
	const words = search
		.trim()
		.toLocaleLowerCase()
		.split( /\s+/ )
		.filter( Boolean );
	const visit = ( siblings, path = new Set(), ancestorMatch = false ) =>
		siblings
			.filter( ( node ) => ! path.has( node.id ) )
			.sort(
				( a, b ) =>
					( a.position || 0 ) - ( b.position || 0 ) || a.id - b.id
			)
			.map( ( node ) => {
				const match =
					ancestorMatch ||
					words.every( ( word ) =>
						node.name.toLocaleLowerCase().includes( word )
					);
				return {
					...node,
					match,
					children: visit(
						node.children,
						new Set( [ ...path, node.id ] ),
						match
					),
				};
			} )
			.filter( ( node ) => node.match || node.children.length );
	return visit( roots );
}
