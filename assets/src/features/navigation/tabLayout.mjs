const color = ( value, fallback ) =>
	/^#[0-9a-f]{6}$/i.test( value || '' ) ? value : fallback;

/**
 * Recover saved layouts safely when tabs are added, removed or a stored value is damaged.
 * @param saved
 * @param ids
 */
export function normalizeLayout( saved, ids ) {
	const source = saved && typeof saved === 'object' ? saved : {};
	const order = [
		...new Set( [
			...( Array.isArray( source.order ) ? source.order : [] ),
			...ids,
		] ),
	].filter( ( id ) => ids.includes( id ) );
	const groups = [];
	for ( const group of Array.isArray( source.groups ) ? source.groups : [] ) {
		if (
			! group ||
			typeof group.id !== 'string' ||
			! /^[a-z0-9_-]{1,80}$/i.test( group.id ) ||
			groups.some( ( item ) => item.id === group.id )
		) {
			continue;
		}
		groups.push( {
			id: group.id,
			name: ( typeof group.name === 'string'
				? group.name
				: 'Group'
			).slice( 0, 80 ),
			background: color( group.background, '#ede7f6' ),
			text: color( group.text, '#45278b' ),
			collapsed: Boolean( group.collapsed ),
		} );
	}
	const assignments = {};
	for ( const id of order ) {
		const group = source.assignments?.[ id ];
		if ( groups.some( ( item ) => item.id === group ) ) {
			assignments[ id ] = group;
		}
	}
	// A group is one contiguous block, just like browser tab groups.
	const contiguous = [];
	const seen = new Set();
	for ( const id of order ) {
		const group = assignments[ id ];
		if ( ! group ) {
			contiguous.push( id );
		} else if ( ! seen.has( group ) ) {
			seen.add( group );
			contiguous.push(
				...order.filter( ( tab ) => assignments[ tab ] === group )
			);
		}
	}
	return { order: contiguous, groups, assignments };
}

export function layoutBlocks( layout ) {
	const blocks = [];
	const seen = new Set();
	for ( const id of layout.order ) {
		const group = layout.groups.find(
			( item ) => item.id === layout.assignments[ id ]
		);
		if ( ! group ) {
			blocks.push( { id: `tab:${ id }`, tabs: [ id ] } );
		} else if ( ! seen.has( group.id ) ) {
			seen.add( group.id );
			blocks.push( {
				id: `group:${ group.id }`,
				group,
				tabs: layout.order.filter(
					( tab ) => layout.assignments[ tab ] === group.id
				),
			} );
		}
	}
	return blocks;
}

/**
 * Drop a tab beside another tab, onto a group label, or outside all groups.
 * @param layout
 * @param id
 * @param target
 * @param after
 */
export function moveTab( layout, id, target, after = false ) {
	if ( ! layout.order.includes( id ) || id === target ) {
		return layout;
	}
	const assignments = { ...layout.assignments };
	const groupTarget = target?.startsWith( 'group:' )
		? target.slice( 6 )
		: null;
	const group = groupTarget || layout.assignments[ target ];
	if ( group && layout.groups.some( ( item ) => item.id === group ) ) {
		assignments[ id ] = group;
	} else {
		delete assignments[ id ];
	}
	const order = layout.order.filter( ( item ) => item !== id );
	let index = order.length;
	if ( groupTarget ) {
		const members = order.filter(
			( tab ) => assignments[ tab ] === groupTarget
		);
		if ( members.length ) {
			index = order.indexOf( members.at( -1 ) ) + 1;
		}
	} else if ( order.includes( target ) ) {
		index = order.indexOf( target ) + ( after ? 1 : 0 );
	}
	order.splice( index, 0, id );
	return normalizeLayout( { ...layout, order, assignments }, layout.order );
}

/**
 * Move a whole group or ungrouped tab, preserving all tabs inside the group.
 * @param layout
 * @param source
 * @param target
 * @param after
 */
export function moveBlock( layout, source, target, after = false ) {
	const blocks = layoutBlocks( layout );
	const block = blocks.find( ( item ) => item.id === source );
	if ( ! block || source === target ) {
		return layout;
	}
	const remaining = blocks.filter( ( item ) => item.id !== source );
	const index = remaining.findIndex( ( item ) => item.id === target );
	remaining.splice(
		index < 0 ? remaining.length : index + ( after ? 1 : 0 ),
		0,
		block
	);
	return { ...layout, order: remaining.flatMap( ( item ) => item.tabs ) };
}

export function assignTab( layout, id, group ) {
	const assignments = { ...layout.assignments };
	delete assignments[ id ];
	const ungrouped = normalizeLayout(
		{ ...layout, assignments },
		layout.order
	);
	return group ? moveTab( ungrouped, id, `group:${ group }` ) : ungrouped;
}
