/** Card dimensions shared by the automatic layout and its renderer. */
export const MAP_CARD = {
	width: 240,
	height: 112,
	column: 300,
	row: 136,
	padding: 32,
};

/**
 * Lay out visible branches without changing saved syllabus order or identities.
 * Search keeps matching nodes and their ancestors, opening matching paths.
 *
 * @param {Object|null}              root      Syllabus root.
 * @param {Set}                      collapsed Collapsed placement keys.
 * @param {string}                   query     Search text.
 * @param {(node: Object) => string} label     Localized node label.
 * @return {Object} Positioned nodes, edges, and canvas bounds.
 */
export function layoutSyllabusMap( root, collapsed, query, label ) {
	const nodes = [];
	const edges = [];
	const matches = new Set();
	const needle = query.trim().toLocaleLowerCase();
	const retain = ( node ) => {
		const childMatches = node.children.map( retain ).some( Boolean );
		const found = label( node ).toLocaleLowerCase().includes( needle );
		if ( found || childMatches ) {
			matches.add( node.key );
		}
		return found || childMatches;
	};
	if ( ! root || ( needle && ! retain( root ) ) ) {
		return { nodes, edges, width: 0, height: 0 };
	}
	const hasConnections = ( node ) =>
		node.skill?.connections?.length > 0 ||
		node.children.some( hasConnections );
	const cardHeight = MAP_CARD.height + ( hasConnections( root ) ? 96 : 0 );
	const rowHeight = MAP_CARD.row + cardHeight - MAP_CARD.height;
	let row = 0;
	let maxDepth = 0;
	const visit = ( node, depth ) => {
		const children =
			needle || ! collapsed.has( node.key )
				? node.children.filter(
						( child ) => ! needle || matches.has( child.key )
					)
				: [];
		const positioned = {
			node,
			x: MAP_CARD.padding + depth * MAP_CARD.column,
			y: 0,
		};
		nodes.push( positioned );
		maxDepth = Math.max( maxDepth, depth );
		const descendants = children.map( ( child ) =>
			visit( child, depth + 1 )
		);
		positioned.y = descendants.length
			? ( descendants[ 0 ].y + descendants.at( -1 ).y ) / 2
			: MAP_CARD.padding + row++ * rowHeight;
		for ( const child of descendants ) {
			edges.push( { from: positioned, to: child } );
		}
		return positioned;
	};
	visit( root, 0 );
	return {
		nodes,
		edges,
		cardHeight,
		width:
			MAP_CARD.padding * 2 + maxDepth * MAP_CARD.column + MAP_CARD.width,
		height: MAP_CARD.padding * 2 + ( row - 1 ) * rowHeight + cardHeight,
	};
}
