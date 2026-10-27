/**
 * Build a cycle-safe forest without losing orphaned items or changing their order.
 * @param items
 */
export function buildMindmapTree( items ) {
	const nodes = new Map(
		items.map( ( item ) => [
			String( item.id ),
			{ ...item, children: [] },
		] )
	);
	const roots = [];
	for ( const node of nodes.values() ) {
		let parent = nodes.get( String( node.parent ) );
		const seen = new Set( [ String( node.id ) ] );
		let ancestor = parent;
		while ( ancestor && ! seen.has( String( ancestor.id ) ) ) {
			seen.add( String( ancestor.id ) );
			ancestor = nodes.get( String( ancestor.parent ) );
		}
		if ( ancestor ) {
			parent = null;
		}
		if ( parent ) {
			parent.children.push( node );
		} else {
			roots.push( node );
		}
	}
	return roots;
}
