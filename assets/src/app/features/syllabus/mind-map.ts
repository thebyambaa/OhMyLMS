import type { SyllabusOutline } from './model';

export type MapKind = 'syllabus' | 'item' | 'group' | 'skill';

/** One node of the syllabus tree: the syllabus, its content items, their skill groups and skills. */
export interface MapNode {
	key: string;
	kind: MapKind;
	/** Item, group or skill (term) ID. */
	id: number;
	label: string;
	code: string;
	children: MapNode[];
}

/** Card size and spacing shared by the layout and the SVG renderer. */
export const MAP_CARD = {
	width: 220,
	height: 44,
	column: 260,
	row: 56,
	padding: 16,
} as const;

/**
 * Build the tree in saved order: a content item's skill groups come before its child items.
 *
 * @param outline Syllabus outline.
 * @return The root node (the syllabus).
 */
export function syllabusTree( outline: SyllabusOutline ): MapNode {
	const byParent = new Map< number, SyllabusOutline[ 'contents' ] >();
	for ( const content of outline.contents ) {
		byParent.set( content.parent_id, [
			...( byParent.get( content.parent_id ) ?? [] ),
			content,
		] );
	}
	const root = outline.contents.find(
		( content ) => content.id === outline.syllabus.id
	);
	const seen = new Set< number >();
	const visit = (
		content: SyllabusOutline[ 'contents' ][ number ],
		kind: MapKind
	): MapNode => {
		seen.add( content.id );
		return {
			key: `c${ content.id }`,
			kind,
			id: content.id,
			label: content.name,
			code: content.code ?? '',
			children: [
				...content.groups.map( ( group ) => ( {
					key: `g${ group.id }`,
					kind: 'group' as const,
					id: group.id,
					label: group.name,
					code: group.code,
					children: group.skills.map( ( skill ) => ( {
						key: `g${ group.id }s${ skill.term_id }`,
						kind: 'skill' as const,
						id: skill.term_id,
						label: skill.name,
						code: skill.code,
						children: [],
					} ) ),
				} ) ),
				...( byParent.get( content.id ) ?? [] )
					.filter( ( child ) => ! seen.has( child.id ) )
					.map( ( child ) => visit( child, 'item' ) ),
			],
		};
	};
	return root
		? visit( root, 'syllabus' )
		: {
				key: `c${ outline.syllabus.id }`,
				kind: 'syllabus',
				id: outline.syllabus.id,
				label: outline.syllabus.name,
				code: '',
				children: [],
			};
}

/**
 * Keys of every node that has children (for "collapse all").
 * @param node
 */
export function branchKeys( node: MapNode ): string[] {
	return node.children.length
		? [ node.key, ...node.children.flatMap( branchKeys ) ]
		: [];
}

/**
 * Counts by kind, for the text summary.
 * @param node
 */
export function countNodes( node: MapNode ): Record< MapKind, number > {
	const counts: Record< MapKind, number > = {
		syllabus: 0,
		item: 0,
		group: 0,
		skill: 0,
	};
	const walk = ( current: MapNode ) => {
		counts[ current.kind ]++;
		current.children.forEach( walk );
	};
	walk( node );
	return counts;
}

export interface PlacedNode {
	node: MapNode;
	x: number;
	y: number;
	depth: number;
	/** The node has children that are hidden because it is collapsed. */
	folded: boolean;
}
export interface MapLayout {
	nodes: PlacedNode[];
	edges: { from: PlacedNode; to: PlacedNode }[];
	width: number;
	height: number;
	/** Keys of nodes that match the search (with their ancestors). */
	matches: Set< string >;
}

/**
 * Lay out the visible branches left to right: depth sets the column, leaves take one row each and a
 * parent sits beside the middle of its children. A search keeps matching nodes and their ancestors and
 * opens their branches. Saved order and identities are never changed.
 *
 * @param root      Tree root.
 * @param collapsed Keys of collapsed nodes.
 * @param query     Search text.
 * @return Positioned nodes, edges and canvas size.
 */
export function layoutMap(
	root: MapNode,
	collapsed: ReadonlySet< string >,
	query = ''
): MapLayout {
	const needle = query.trim().toLocaleLowerCase();
	const matches = new Set< string >();
	const retain = ( node: MapNode ): boolean => {
		const child = node.children.map( retain ).some( Boolean );
		const found =
			node.label.toLocaleLowerCase().includes( needle ) ||
			node.code.toLocaleLowerCase().includes( needle );
		if ( found || child ) {
			matches.add( node.key );
		}
		return found || child;
	};
	const nodes: PlacedNode[] = [];
	const edges: MapLayout[ 'edges' ] = [];
	if ( needle && ! retain( root ) ) {
		return { nodes, edges, width: 0, height: 0, matches };
	}
	let row = 0;
	let maxDepth = 0;
	const visit = ( node: MapNode, depth: number ): PlacedNode => {
		const open = needle ? true : ! collapsed.has( node.key );
		const children = open
			? node.children.filter(
					( child ) => ! needle || matches.has( child.key )
				)
			: [];
		const placed: PlacedNode = {
			node,
			x: MAP_CARD.padding + depth * MAP_CARD.column,
			y: 0,
			depth,
			folded: ! open && node.children.length > 0,
		};
		nodes.push( placed );
		maxDepth = Math.max( maxDepth, depth );
		const below = children.map( ( child ) => visit( child, depth + 1 ) );
		const first = below[ 0 ];
		const last = below[ below.length - 1 ];
		placed.y =
			first && last
				? ( first.y + last.y ) / 2
				: MAP_CARD.padding + row++ * MAP_CARD.row;
		for ( const child of below ) {
			edges.push( { from: placed, to: child } );
		}
		return placed;
	};
	visit( root, 0 );
	return {
		nodes,
		edges,
		matches,
		width:
			MAP_CARD.padding * 2 + maxDepth * MAP_CARD.column + MAP_CARD.width,
		height:
			MAP_CARD.padding * 2 +
			Math.max( 0, row - 1 ) * MAP_CARD.row +
			MAP_CARD.height,
	};
}

/**
 * A cubic link from the right edge of one card to the left edge of the next.
 * @param from
 * @param to
 */
export function edgePath( from: PlacedNode, to: PlacedNode ): string {
	const startX = from.x + MAP_CARD.width;
	const startY = from.y + MAP_CARD.height / 2;
	const endX = to.x;
	const endY = to.y + MAP_CARD.height / 2;
	const middle = ( startX + endX ) / 2;
	return `M${ startX } ${ startY } C${ middle } ${ startY }, ${ middle } ${ endY }, ${ endX } ${ endY }`;
}
