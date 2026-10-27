/** Pure helpers for the course "Organize" panel: placing a course in the curriculum and Course Bundles. */

/**
 * Curriculum items in tree order (siblings by position), each with its depth. Items whose parent is missing are shown at the top level.
 * @param items
 */
export function orderItems( items ) {
	const rows = ( items || [] ).map( ( row ) => ( {
		...row,
		id: Number( row.id ),
		parent_id: Number( row.parent_id || 0 ),
		position: Number( row.position || 0 ),
	} ) );
	const known = new Set( rows.map( ( row ) => row.id ) );
	const children = new Map();
	for ( const row of rows ) {
		const parent = known.has( row.parent_id ) ? row.parent_id : 0;
		if ( ! children.has( parent ) ) {
			children.set( parent, [] );
		}
		children.get( parent ).push( row );
	}
	for ( const list of children.values() ) {
		list.sort( ( a, b ) => a.position - b.position || a.id - b.id );
	}
	const ordered = [];
	const seen = new Set();
	const walk = ( parent, depth ) => {
		for ( const row of children.get( parent ) || [] ) {
			if ( seen.has( row.id ) ) {
				continue;
			}
			seen.add( row.id );
			ordered.push( { ...row, depth } );
			walk( row.id, depth + 1 );
		}
	};
	walk( 0, 0 );
	return ordered;
}

/**
 * Items whose name or code contains the text, plus the ancestors needed to show them in context.
 * @param ordered
 * @param text
 */
export function filterItems( ordered, text ) {
	const needle = String( text || '' )
		.trim()
		.toLowerCase();
	if ( ! needle ) {
		return ordered;
	}
	const byId = new Map( ordered.map( ( row ) => [ row.id, row ] ) );
	const keep = new Set();
	for ( const row of ordered ) {
		if (
			! `${ row.name } ${ row.code || '' }`
				.toLowerCase()
				.includes( needle )
		) {
			continue;
		}
		let cursor = row;
		for (
			let guard = 0;
			cursor && ! keep.has( cursor.id ) && guard < 50;
			guard += 1
		) {
			keep.add( cursor.id );
			cursor = byId.get( cursor.parent_id );
		}
	}
	return ordered.filter( ( row ) => keep.has( row.id ) );
}

/**
 * The ids with one added or removed; the input is never changed.
 * @param ids
 * @param id
 * @param checked
 */
export function toggleId( ids, id, checked ) {
	const value = Number( id );
	const rest = ( ids || [] )
		.map( Number )
		.filter( ( existing ) => existing !== value );
	return checked ? [ ...rest, value ] : rest;
}

/**
 * Same members regardless of order.
 * @param a
 * @param b
 */
export function sameIds( a, b ) {
	const left = [ ...new Set( ( a || [] ).map( Number ) ) ].sort(
		( x, y ) => x - y
	);
	const right = [ ...new Set( ( b || [] ).map( Number ) ) ].sort(
		( x, y ) => x - y
	);
	return (
		left.length === right.length &&
		left.every( ( id, index ) => id === right[ index ] )
	);
}

/**
 * Course Bundles: published first, then drafts, each alphabetically.
 * @param bundles
 */
export function orderBundles( bundles ) {
	return [ ...( bundles || [] ) ]
		.map( ( bundle ) => ( { ...bundle, id: Number( bundle.id ) } ) )
		.sort(
			( a, b ) =>
				Number( b.status === 'published' ) -
					Number( a.status === 'published' ) ||
				String( a.title ).localeCompare( String( b.title ) )
		);
}
